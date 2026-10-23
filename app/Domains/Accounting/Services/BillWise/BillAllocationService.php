<?php

namespace App\Domains\Accounting\Services\BillWise;

use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * The bill-wise register (Tally's New Ref / Agst Ref / On Account).
 *
 * Every Receivable / Payable journal line that carries a party is fully
 * explained by allocations, signed like the ledger (+ debit, - credit):
 *   - new_ref      opens a bill (invoice, vendor bill, opening due)
 *   - against_ref  settles an open bill (receipt, payment, return)
 *   - on_account   money not matched to any bill yet
 * So for every party, the sum of its allocations is its ledger balance, and
 * bill-wise outstanding / ageing always ties to the books.
 *
 * Posting code passes hints (journal meta 'bill_allocations'); whatever a
 * hint doesn't explain is On Account. Nothing is matched by guesswork.
 */
class BillAllocationService
{
    /** @var array<int, array<int, string>> tenant => [control account id => 'receivable'|'payable'] */
    private array $controls = [];

    private static ?bool $tableExists = null;

    public function __construct(private readonly SystemAccountService $systemAccounts)
    {
    }

    /** Hint: this line opens bill $refNumber (an invoice, a vendor bill, an opening due). */
    public static function newRef(string $ledger, string $refNumber, $billDate, $dueDate, string $sourceType, int $sourceId): array
    {
        return [
            'ref_type' => BillAllocation::NEW_REF,
            'ledger' => $ledger,
            'ref_number' => $refNumber,
            'bill_date' => Carbon::parse($billDate)->toDateString(),
            'due_date' => Carbon::parse($dueDate ?? $billDate)->toDateString(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ];
    }

    /** Hint: this line settles (up to $amount of) the bill that $sourceType #$sourceId opened. */
    public static function againstRef(string $ledger, string $sourceType, int $sourceId, ?float $amount = null): array
    {
        return array_filter([
            'ref_type' => BillAllocation::AGAINST_REF,
            'ledger' => $ledger,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'amount' => $amount,
        ], fn ($v) => $v !== null);
    }

    /** False until the register migration has run, so posting never breaks on an older schema. */
    public static function enabled(): bool
    {
        return self::$tableExists ??= Schema::hasTable('bill_allocations');
    }

    /** For tests that build the schema after this class was first used. */
    public static function resetSchemaCache(): void
    {
        self::$tableExists = null;
    }

    /**
     * Allocate every Receivable / Payable party line of a posted journal.
     *
     * Hint keys: ref_type (required), party_type/party_id (default: any party
     * line), ledger ('receivable'|'payable'), amount (magnitude; default: what
     * is left on the line), ref_number, bill_date, due_date, source_type,
     * source_id, bill_reference_id (for against_ref).
     *
     * @param list<array<string, mixed>> $hints
     */
    public function allocateJournal(Journal $journal, array $hints = []): void
    {
        if (! self::enabled() || BillAllocation::whereIn('journal_entry_id', $journal->entries()->pluck('id'))->exists()) {
            return;
        }

        $controls = $this->controlAccounts((int) $journal->tenant_id);
        $all = $journal->entries()->orderBy('id')->get()->values();
        $positions = $all->mapWithKeys(fn (JournalEntry $e, int $i) => [$e->id => $i])->all();
        $lines = $all
            ->filter(fn (JournalEntry $e) => isset($controls[$e->chart_of_account_id]) && $e->party_id !== null && $e->party_type !== null)
            ->values();

        if ($lines->isEmpty()) {
            return;
        }

        $remaining = $lines->mapWithKeys(fn (JournalEntry $e) => [$e->id => round((float) $e->debit - (float) $e->credit, 2)])->all();
        $date = Carbon::parse($journal->journal_date)->toDateString();

        foreach ($hints as $hint) {
            foreach ($lines as $line) {
                if ($remaining[$line->id] == 0.0 || ! $this->hintMatches($hint, $line, $controls, $positions)) {
                    continue;
                }

                $wanted = isset($hint['amount']) ? round(abs((float) $hint['amount']), 2) : abs($remaining[$line->id]);
                $magnitude = min($wanted, abs($remaining[$line->id]));
                if ($magnitude <= 0) {
                    continue;
                }

                $signed = $remaining[$line->id] > 0 ? $magnitude : -$magnitude;
                $used = $this->applyHint($journal, $line, $hint, $signed, $date);
                $remaining[$line->id] = round($remaining[$line->id] - $used, 2);

                // A hint with an amount is spent once; without one it covers one line.
                break;
            }
        }

        foreach ($lines as $line) {
            if (abs($remaining[$line->id]) >= 0.005) {
                $this->record($line, BillAllocation::ON_ACCOUNT, $remaining[$line->id], $date);
            }
        }
    }

    /**
     * A reversal undoes the original's bill effect line for line: a reversed
     * invoice's bill closes to zero, a reversed receipt reopens what it paid.
     */
    public function mirrorReversal(Journal $original, Journal $reversal): void
    {
        if (! self::enabled()) {
            return;
        }

        $originalLines = $original->entries()->orderBy('id')->pluck('id')->values();
        $reversalLines = $reversal->entries()->orderBy('id')->get()->values();
        $date = Carbon::parse($reversal->journal_date)->toDateString();

        foreach ($originalLines as $index => $originalLineId) {
            $target = $reversalLines[$index] ?? null;
            if ($target === null) {
                continue;
            }

            foreach (BillAllocation::where('journal_entry_id', $originalLineId)->get() as $allocation) {
                BillAllocation::create([
                    'tenant_id' => $allocation->tenant_id,
                    'journal_entry_id' => $target->id,
                    'bill_reference_id' => $allocation->bill_reference_id,
                    'party_type' => $allocation->party_type,
                    'party_id' => $allocation->party_id,
                    'control_account_id' => $allocation->control_account_id,
                    'ref_type' => $allocation->ref_type,
                    'amount' => -1 * (float) $allocation->amount,
                    'allocated_on' => $date,
                ]);
            }
        }
    }

    /**
     * Open bills (and what is still due on each) as at a date.
     *
     * @return Collection<int, object{bill: BillReference, outstanding: float}>
     */
    public function openBills(string $ledger, ?string $asOf = null, ?string $partyType = null, ?int $partyId = null, ?int $ledgerId = null): Collection
    {
        $controlIds = $ledgerId ? [$ledgerId] : $this->controlIdsFor($ledger);

        $totals = BillAllocation::query()
            ->whereIn('control_account_id', $controlIds)
            ->whereNotNull('bill_reference_id')
            ->when($asOf, fn ($q) => $q->whereDate('allocated_on', '<=', $asOf))
            ->when($partyType, fn ($q) => $q->where('party_type', $partyType)->where('party_id', $partyId))
            ->groupBy('bill_reference_id')
            ->selectRaw('bill_reference_id, SUM(amount) as outstanding')
            ->havingRaw('ABS(SUM(amount)) >= 0.005')
            ->pluck('outstanding', 'bill_reference_id');

        $bills = BillReference::query()->whereIn('id', $totals->keys())->get()->keyBy('id');

        return $totals->map(fn ($outstanding, $id) => (object) [
            'bill' => $bills[$id],
            'outstanding' => round((float) $outstanding, 2),
        ])->values();
    }

    /**
     * Money on the ledger not matched to any bill, per party, as at a date.
     *
     * @return Collection<string, float> "party_type:party_id" => signed amount
     */
    public function onAccount(string $ledger, ?string $asOf = null, ?int $ledgerId = null, ?string $refType = null): Collection
    {
        return BillAllocation::query()
            ->whereIn('control_account_id', $ledgerId ? [$ledgerId] : $this->controlIdsFor($ledger))
            ->whereNull('bill_reference_id')
            ->when($refType, fn ($q) => $q->where('ref_type', $refType))
            ->when($asOf, fn ($q) => $q->whereDate('allocated_on', '<=', $asOf))
            ->groupBy('party_type', 'party_id')
            ->selectRaw("party_type, party_id, SUM(amount) as balance")
            ->havingRaw('ABS(SUM(amount)) >= 0.005')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->party_type}:{$row->party_id}" => round((float) $row->balance, 2)]);
    }

    /** What is still open on one bill (signed). */
    public function billOutstanding(BillReference $bill): float
    {
        return round((float) BillAllocation::where('bill_reference_id', $bill->id)->sum('amount'), 2);
    }

    /** Find the bill a source document opened, e.g. ('Invoice', 12). */
    public function billFor(string $sourceType, int $sourceId): ?BillReference
    {
        return BillReference::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
    }

    /** @return list<int> */
    public function controlIdsFor(string $ledger): array
    {
        $tenantId = (int) (tenant_id() ?? 0);

        return array_keys(array_filter($this->controlAccounts($tenantId), fn ($kind) => $kind === $ledger));
    }

    /**
     * Every ledger kept bill-by-bill: 'receivable' for debit-normal ledgers
     * (customers, advances paid), 'payable' for credit-normal ones (suppliers,
     * salary payable, advances received).
     *
     * @return array<int, string> ledger id => 'receivable'|'payable'
     */
    public function controlAccounts(int $tenantId): array
    {
        if (! isset($this->controls[$tenantId])) {
            $map = [];

            $query = \App\Domains\Accounting\Models\ChartOfAccount::query()->withoutGlobalScopes()->where('tenant_id', $tenantId);
            $ledgers = Schema::hasColumn('chart_of_accounts', 'bill_wise')
                ? $query->where('bill_wise', true)->get(['id', 'normal_balance'])
                : collect();

            foreach ($ledgers as $ledger) {
                $map[$ledger->id] = $ledger->normal_balance === \App\Domains\Accounting\Models\ChartOfAccount::BALANCE_CREDIT ? 'payable' : 'receivable';
            }

            // Receivable / Payable are always bill-wise, whatever the flag says.
            foreach ([SystemAccount::AR => 'receivable', SystemAccount::AP => 'payable'] as $key => $kind) {
                if ($account = $this->systemAccounts->get($key, $tenantId)) {
                    $map[$account->id] = $kind;
                }
            }

            $this->controls[$tenantId] = $map;
        }

        return $this->controls[$tenantId];
    }

    /** Forget cached ledgers, e.g. after a ledger's bill-wise switch changed. */
    public function forgetControls(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            $this->controls = [];
        } else {
            unset($this->controls[$tenantId]);
        }
    }

    /**
     * @param array<int, string> $controls
     * @param array<int, int> $positions journal line id => its 0-based position in the journal
     */
    private function hintMatches(array $hint, JournalEntry $line, array $controls, array $positions = []): bool
    {
        if (isset($hint['line_index']) && ($positions[$line->id] ?? -1) !== (int) $hint['line_index']) {
            return false;
        }

        if (isset($hint['account_id']) && (int) $hint['account_id'] !== (int) $line->chart_of_account_id) {
            return false;
        }

        if (isset($hint['party_type']) && ($hint['party_type'] !== $line->party_type || (int) $hint['party_id'] !== (int) $line->party_id)) {
            return false;
        }

        return ! isset($hint['ledger']) || $controls[$line->chart_of_account_id] === $hint['ledger'];
    }

    /** Record one hint against a line; returns the signed amount it explained. */
    private function applyHint(Journal $journal, JournalEntry $line, array $hint, float $signed, string $date): float
    {
        $type = $hint['ref_type'] ?? BillAllocation::ON_ACCOUNT;

        if ($type === BillAllocation::NEW_REF) {
            $bill = BillReference::firstOrCreate([
                'tenant_id' => $line->tenant_id,
                'party_type' => $line->party_type,
                'party_id' => $line->party_id,
                'control_account_id' => $line->chart_of_account_id,
                'ref_number' => (string) ($hint['ref_number'] ?? $journal->journal_number),
            ], [
                'company_id' => $journal->company_id,
                'branch_id' => $journal->branch_id,
                'bill_date' => $hint['bill_date'] ?? $date,
                'due_date' => $hint['due_date'] ?? ($hint['bill_date'] ?? $date),
                'source_type' => $hint['source_type'] ?? $journal->reference_type,
                'source_id' => $hint['source_id'] ?? $journal->reference_id,
            ]);

            $this->record($line, BillAllocation::NEW_REF, $signed, $date, $bill->id);

            return $signed;
        }

        if ($type === BillAllocation::AGAINST_REF) {
            // One bill, or (an opening broken into old bills) several, oldest due first.
            $used = 0.0;

            foreach ($this->findBills($line, $hint) as $bill) {
                $left = round($signed - $used, 2);
                if (abs($left) < 0.005) {
                    break;
                }

                // Only settle what is open on the bill, on its opposite side.
                $open = $this->billOutstanding($bill);
                if ($open == 0.0 || ($open > 0) === ($left > 0)) {
                    continue;
                }

                $settle = min(abs($left), abs($open));
                $settleSigned = $left > 0 ? $settle : -$settle;
                $this->record($line, BillAllocation::AGAINST_REF, $settleSigned, $date, $bill->id);
                $used = round($used + $settleSigned, 2);
            }

            return $used;
        }

        $this->record($line, in_array($type, [BillAllocation::ADVANCE, BillAllocation::ON_ACCOUNT], true) ? $type : BillAllocation::ON_ACCOUNT, $signed, $date);

        return $signed;
    }

    /** @return Collection<int, BillReference> */
    private function findBills(JournalEntry $line, array $hint): Collection
    {
        $query = BillReference::query()
            ->where('party_type', $line->party_type)
            ->where('party_id', $line->party_id)
            ->where('control_account_id', $line->chart_of_account_id);

        if (! empty($hint['bill_reference_id'])) {
            return (clone $query)->whereKey($hint['bill_reference_id'])->get();
        }

        if (! empty($hint['source_type']) && ! empty($hint['source_id'])) {
            $bills = (clone $query)->where('source_type', $hint['source_type'])->where('source_id', $hint['source_id'])
                ->orderBy('due_date')->orderBy('bill_date')->orderBy('id')->get();
            if ($bills->isNotEmpty()) {
                return $bills;
            }
        }

        return ! empty($hint['ref_number']) ? (clone $query)->where('ref_number', $hint['ref_number'])->get() : collect();
    }

    private function record(JournalEntry $line, string $type, float $signed, string $date, ?int $billId = null): void
    {
        BillAllocation::create([
            'tenant_id' => $line->tenant_id,
            'journal_entry_id' => $line->id,
            'bill_reference_id' => $billId,
            'party_type' => $line->party_type,
            'party_id' => $line->party_id,
            'control_account_id' => $line->chart_of_account_id,
            'ref_type' => $type,
            'amount' => round($signed, 2),
            'allocated_on' => $date,
        ]);
    }
}
