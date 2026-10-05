<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankReconciliationRule;
use App\Domains\Accounting\Models\BankStatementLine;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Narration rules for bank reconciliation: which ledger (and party) a bank
 * line belongs to, based on its narration, direction and amount.
 *
 * Rules are checked in priority order (lower first); manual rules win over
 * learned ones at equal priority. Learned rules come from entries people post
 * while reconciling: the narration is reduced to its distinctive words
 * ("NEFT DR-LANDMARK PROPERTIES RENT" → "landmark properties rent") so the
 * next month's line with a different UTR still matches.
 */
class BankReconciliationRuleService
{
    /** Words that say how money moved, not who/what it was for. */
    private const STOP_WORDS = [
        'neft', 'rtgs', 'imps', 'upi', 'nach', 'ach', 'ecs', 'cr', 'dr', 'chq', 'cheque', 'clg', 'clearing', 'cts', 'micr',
        'paid', 'deposit', 'dep', 'transfer', 'trf', 'to', 'from', 'by', 'inb', 'mb', 'ib', 'net', 'banking', 'ref', 'no',
        'the', 'and', 'for', 'of', 'pos', 'txn', 'via', 'at', 'on', 'in', 'is', 'self', 'mmt', 'bil', 'onl', 'inf',
        'hdfc', 'icici', 'sbi', 'axis', 'kotak', 'bank', 'ltd', 'pvt', 'limited', 'private', 'co', 'payment', 'received',
    ];

    /**
     * Active rules that can apply to this bank account, best first.
     *
     * @return Collection<int, BankReconciliationRule>
     */
    public function rulesFor(BankReconciliation $reconciliation): Collection
    {
        return BankReconciliationRule::withoutGlobalScope('tenant')
            ->where('tenant_id', $reconciliation->tenant_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('bank_account_id')->orWhere('bank_account_id', $reconciliation->chart_of_account_id))
            ->with(['targetAccount' => fn ($q) => $q->withoutGlobalScope('tenant')])
            ->get()
            ->sortBy(fn (BankReconciliationRule $rule) => [
                $rule->priority,
                $rule->source === BankReconciliationRule::SOURCE_MANUAL ? 0 : 1,
                -$rule->hits,
                $rule->id,
            ])
            ->values();
    }

    /**
     * The first rule that matches each unmatched line.
     *
     * @param Collection<int, BankStatementLine> $lines
     * @param Collection<int, BankReconciliationRule> $rules
     * @return array<int, BankReconciliationRule> statement line id => rule
     */
    public function suggestions(Collection $lines, Collection $rules): array
    {
        $out = [];

        foreach ($lines as $line) {
            if ($line->is_matched) {
                continue;
            }

            $rule = $rules->first(fn (BankReconciliationRule $rule) => $this->matches($rule, $line));

            if ($rule !== null && $rule->targetAccount?->is_active) {
                $out[$line->id] = $rule;
            }
        }

        return $out;
    }

    public function matches(BankReconciliationRule $rule, BankStatementLine $line): bool
    {
        $amount = (float) $line->amount;

        if ($rule->direction === BankReconciliationRule::DIRECTION_IN && $amount <= 0) {
            return false;
        }
        if ($rule->direction === BankReconciliationRule::DIRECTION_OUT && $amount >= 0) {
            return false;
        }

        $absolute = abs($amount);
        if ($rule->min_amount !== null && $absolute < $rule->min_amount) {
            return false;
        }
        if ($rule->max_amount !== null && $absolute > $rule->max_amount) {
            return false;
        }

        $text = $this->normalise(trim(($line->description ?? '') . ' ' . ($line->reference ?? '')));
        if ($text === '') {
            return false;
        }

        if ($rule->match_type === BankReconciliationRule::MATCH_REGEX) {
            return @preg_match($this->regex($rule->pattern), $line->description ?? '') === 1;
        }

        $pattern = $this->normalise($rule->pattern);
        if ($pattern === '') {
            return false;
        }

        return match ($rule->match_type) {
            BankReconciliationRule::MATCH_STARTS_WITH => str_starts_with($text, $pattern),
            BankReconciliationRule::MATCH_EQUALS => $this->normalise($line->description ?? '') === $pattern,
            default => str_contains(" {$text} ", " {$pattern} ") || str_contains($text, $pattern),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, ?BankReconciliationRule $rule = null, ?int $userId = null): BankReconciliationRule
    {
        $data['match_type'] ??= BankReconciliationRule::MATCH_CONTAINS;
        $data['direction'] ??= BankReconciliationRule::DIRECTION_ANY;

        if ($data['match_type'] === BankReconciliationRule::MATCH_REGEX && @preg_match($this->regex($data['pattern']), '') === false) {
            throw new InvalidArgumentException('The pattern is not a valid regular expression.');
        }

        if (isset($data['min_amount'], $data['max_amount']) && $data['min_amount'] !== null && $data['max_amount'] !== null && $data['min_amount'] > $data['max_amount']) {
            throw new InvalidArgumentException('Minimum amount cannot be more than maximum amount.');
        }

        if ($rule === null) {
            return BankReconciliationRule::create($data + [
                'tenant_id' => require_tenant_id(),
                'source' => BankReconciliationRule::SOURCE_MANUAL,
                'created_by' => $userId,
            ]);
        }

        // Editing a learned rule makes it the user's own.
        $rule->update($data + ['source' => BankReconciliationRule::SOURCE_MANUAL]);

        return $rule->fresh();
    }

    /**
     * Record what a person just posted for a line, so the same narration is
     * suggested (to the same ledger and party) next time. An existing rule
     * that already matched just gets credit for the hit.
     */
    public function learnFrom(BankReconciliation $reconciliation, BankStatementLine $line, int $accountId, ?string $partyName, ?int $userId = null): ?BankReconciliationRule
    {
        $existing = $this->rulesFor($reconciliation)->first(fn ($rule) => $this->matches($rule, $line));

        if ($existing !== null && (int) $existing->target_account_id === $accountId) {
            $this->recordHit($existing);

            return $existing;
        }

        $keyword = $this->keywordFor($line->description);
        if ($keyword === null) {
            return null;
        }

        $direction = (float) $line->amount > 0 ? BankReconciliationRule::DIRECTION_IN : BankReconciliationRule::DIRECTION_OUT;

        $rule = BankReconciliationRule::withoutGlobalScope('tenant')->firstOrNew([
            'tenant_id' => $reconciliation->tenant_id,
            'bank_account_id' => $reconciliation->chart_of_account_id,
            'match_type' => BankReconciliationRule::MATCH_CONTAINS,
            'pattern' => $keyword,
            'direction' => $direction,
        ]);

        // Never overwrite a rule the user wrote themselves.
        if ($rule->exists && $rule->source === BankReconciliationRule::SOURCE_MANUAL) {
            return $rule;
        }

        $rule->fill([
            'name' => ucwords($keyword),
            'target_account_id' => $accountId,
            'party_name' => $partyName ?: $rule->party_name,
            'priority' => $rule->priority ?? 100,
            'is_active' => true,
            'source' => BankReconciliationRule::SOURCE_LEARNED,
            'hits' => ($rule->hits ?? 0) + 1,
            'last_used_at' => now(),
            'created_by' => $rule->created_by ?? $userId,
        ])->save();

        return $rule;
    }

    public function recordHit(BankReconciliationRule $rule): void
    {
        $rule->forceFill(['hits' => $rule->hits + 1, 'last_used_at' => now()])->saveQuietly();
    }

    /**
     * The distinctive words of a narration: payment-mode words, bank names,
     * numbers and reference codes removed; the first three words left.
     */
    public function keywordFor(?string $narration): ?string
    {
        $words = array_filter(
            explode(' ', $this->normalise((string) $narration)),
            fn ($word) => strlen($word) >= 3
                && ! preg_match('/\d/', $word)
                && ! in_array($word, self::STOP_WORDS, true)
        );

        $words = array_slice(array_values($words), 0, 3);

        return $words === [] ? null : implode(' ', $words);
    }

    public function normalise(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', strtolower($text))));
    }

    private function regex(string $pattern): string
    {
        return '/' . str_replace('/', '\/', $pattern) . '/i';
    }
}
