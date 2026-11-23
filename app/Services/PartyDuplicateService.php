<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * FRS 2.3: before a customer or vendor is created, search the existing parties of the same
 * type by name, GSTIN, PAN, mobile, email and address so the same company is not created twice.
 *
 * Read-only. It never blocks by itself: callers decide whether to ask for confirmation
 * (web forms, API) or to refuse (quick-create). Matching is tenant-wide on purpose, so it
 * ignores the branch/company/RBAC scopes that would hide a party owned by someone else.
 */
class PartyDuplicateService
{
    public const CUSTOMER = 'customer';
    public const VENDOR = 'vendor';

    /** Matches on these fields mean "same person or company", not just a similar name. */
    private const IDENTITY = ['gstin', 'pan', 'email', 'phone'];

    private const LABELS = [
        'gstin' => 'GSTIN',
        'pan' => 'PAN',
        'email' => 'email',
        'phone' => 'mobile',
        'name' => 'name',
        'address' => 'address',
    ];

    private const NAME_NOISE = ['pvt', 'private', 'ltd', 'limited', 'llp', 'inc', 'co', 'company', 'corp', 'corporation', 'the'];

    private const MAX_RESULTS = 5;

    private const SEARCH_FIELDS = ['name', 'company_name', 'email', 'phone', 'gstin', 'pan', 'billing_address', 'address'];

    /**
     * @param  array<string, mixed>  $input  name, company_name, email, phone, gstin, pan, billing_address, address
     * @return list<array{id:int,name:string,status:?string,url:string,matched:list<string>,identity:bool}>
     */
    public function find(string $partyType, array $input, ?int $tenantId = null, ?int $ignoreId = null): array
    {
        $tenantId ??= tenant_id() ?? current_tenant_id();
        $needle = $this->fingerprint($input);

        if ($tenantId === null || $needle === []) {
            return [];
        }

        $table = $partyType === self::VENDOR ? 'vendors' : 'customers';
        $columns = ['id', 'name', 'company_name', 'email', 'phone', 'gstin', 'pan', 'status', 'billing_address'];
        if ($partyType === self::VENDOR) {
            $columns[] = 'address';
        }

        $branchGstins = $this->branchGstinOwners($partyType, $tenantId, $needle['gstin'] ?? null);

        $matches = [];
        // A party already merged into another record is a retired duplicate, not a live match.
        $rows = DB::table($table)->where('tenant_id', $tenantId)->whereNull('merged_into_id')->select($columns)->cursor();

        foreach ($rows as $row) {
            if ($ignoreId !== null && (int) $row->id === $ignoreId) {
                continue;
            }

            $matched = $this->matchedFields($needle, $this->fingerprint((array) $row));
            if (isset($branchGstins[$row->id]) && ! in_array('gstin', $matched, true)) {
                $matched[] = 'gstin';
            }

            if ($matched === []) {
                continue;
            }

            $matches[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'status' => $row->status,
                'url' => route($partyType === self::VENDOR ? 'purchase.vendors.show' : 'crm.customers.show', $row->id),
                'matched' => array_map(fn (string $field) => self::LABELS[$field], $matched),
                'identity' => count(array_intersect($matched, self::IDENTITY)) > 0,
            ];
        }

        usort($matches, fn (array $a, array $b) => count($b['matched']) <=> count($a['matched']));

        return array_slice($matches, 0, self::MAX_RESULTS);
    }

    /**
     * Edit-time search: only the fields whose value actually changed are searched, so saving a party
     * that already has an old look-alike (or changing an unrelated field) does not ask for confirmation.
     *
     * @param  array<string, mixed>  $input  the submitted values (fields not submitted count as unchanged)
     */
    public function findOnUpdate(string $partyType, object $party, array $input, ?int $tenantId = null): array
    {
        $changed = [];

        foreach (self::SEARCH_FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $new = $this->fingerprint([$field => $input[$field]]);
            $old = $this->fingerprint([$field => $party->getAttribute($field)]);

            if ($new !== $old) {
                $changed[$field] = $input[$field];
            }
        }

        return $changed === [] ? [] : $this->find($partyType, $changed, $tenantId, (int) $party->id);
    }

    /**
     * Only the matches that point at the same person or company (GSTIN, PAN, email, mobile).
     * Quick-create uses this so a similar name alone does not block it.
     */
    public function identityMatches(array $matches): array
    {
        return array_values(array_filter($matches, fn (array $m) => $m['identity']));
    }

    /** One readable line for error messages, e.g. "Acme Traders (same GSTIN, email)". */
    public function summary(array $matches): string
    {
        return implode('; ', array_map(
            fn (array $m) => $m['name'].' (same '.implode(', ', $m['matched']).')',
            $matches
        ));
    }

    /** @return array<string, string|list<string>> normalised values, only for fields that have a usable value */
    private function fingerprint(array $data): array
    {
        $print = [];

        $gstin = TaxIdentityService::normalizeGstin($this->text($data['gstin'] ?? null));
        if ($gstin !== null && strlen($gstin) === 15) {
            $print['gstin'] = $gstin;
        }

        $pan = TaxIdentityService::normalizePan($this->text($data['pan'] ?? null));
        if ($pan !== null && strlen($pan) === 10) {
            $print['pan'] = $pan;
        }

        $email = strtolower(trim($this->text($data['email'] ?? null)));
        if ($email !== '') {
            $print['email'] = $email;
        }

        $digits = preg_replace('/\D+/', '', $this->text($data['phone'] ?? null));
        if (strlen($digits) >= 8) {
            $print['phone'] = substr($digits, -10);
        }

        $names = array_values(array_unique(array_filter([
            $this->normalizeName($this->text($data['name'] ?? null)),
            $this->normalizeName($this->text($data['company_name'] ?? null)),
        ], fn (string $n) => strlen($n) >= 3)));
        if ($names !== []) {
            $print['names'] = $names;
        }

        $addresses = array_values(array_unique(array_filter([
            $this->normalizeAddress($this->text($data['billing_address'] ?? null)),
            $this->normalizeAddress($this->text($data['address'] ?? null)),
        ], fn (string $a) => strlen($a) >= 12)));
        if ($addresses !== []) {
            $print['addresses'] = $addresses;
        }

        return $print;
    }

    /** @return list<string> */
    private function matchedFields(array $needle, array $existing): array
    {
        $matched = [];

        foreach (['gstin', 'pan', 'email', 'phone'] as $field) {
            if (isset($needle[$field], $existing[$field]) && $needle[$field] === $existing[$field]) {
                $matched[] = $field;
            }
        }

        $nameMatched = false;
        foreach ($needle['names'] ?? [] as $needleName) {
            foreach ($existing['names'] ?? [] as $existingName) {
                if ($this->isNameSimilar($needleName, $existingName)) {
                    $nameMatched = true;
                    break 2;
                }
            }
        }
        if ($nameMatched) {
            $matched[] = 'name';
        }

        if (array_intersect($needle['addresses'] ?? [], $existing['addresses'] ?? []) !== []) {
            $matched[] = 'address';
        }

        return $matched;
    }

    /**
     * Parties that already own this GSTIN as one of their branches/sites (FRS 2.3 / C-17):
     * a new party with that GSTIN should be a branch of the existing one instead.
     *
     * @return array<int, true> party id => true
     */
    private function branchGstinOwners(string $partyType, int $tenantId, ?string $gstin): array
    {
        if ($gstin === null) {
            return [];
        }

        return DB::table('party_branches')
            ->where('tenant_id', $tenantId)
            ->where('party_type', $partyType)
            ->where('gstin', $gstin)
            ->whereNull('deleted_at')
            ->pluck('party_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * Near-spelling match (FRS 2.3): same number of words, and every differing word is a spelling
     * variant of its counterpart. Short words (Ram/Sai, Patel/Patil) and extra or missing words
     * ("Shree" vs "Shree Ram") are different names, so they are not reported.
     */
    private function isNameSimilar(string $nameA, string $nameB): bool
    {
        if ($nameA === $nameB) {
            return true;
        }

        $wordsA = explode(' ', $nameA);
        $wordsB = explode(' ', $nameB);
        if (count($wordsA) !== count($wordsB)) {
            return false;
        }

        foreach ($wordsA as $i => $word) {
            if ($word !== $wordsB[$i] && ! $this->isSpellingVariant($word, $wordsB[$i])) {
                return false;
            }
        }

        return true;
    }

    /** Typo, inflection or phonetic variant of one word, e.g. aggarwal/agarwal, traders/trading, ram/rama, plyboards/plyboard. */
    private function isSpellingVariant(string $a, string $b): bool
    {
        $lenA = strlen($a);
        $lenB = strlen($b);
        $minLen = min($lenA, $lenB);
        $lenDiff = abs($lenA - $lenB);

        $common = 0;
        while ($common < $minLen && $a[$common] === $b[$common]) {
            $common++;
        }

        // 1. Same stem, different ending (store/stores, supply/supplies, trader/traders/trading, plyboard/plyboards).
        if ($common >= 4 && $minLen >= 5 && $lenDiff <= 3) {
            return true;
        }

        // 2. Trailing single-letter inflection / spelling variant for names (ram/rama, shiv/shiva, krishn/krishna).
        if ($common >= 3 && $lenDiff <= 1 && levenshtein($a, $b) <= 1) {
            return true;
        }

        if ($minLen < 6) {
            return false;
        }

        $distance = levenshtein($a, $b);

        return $distance <= 1
            || (max($lenA, $lenB) >= 8 && $distance <= 2)
            || metaphone($a) === metaphone($b);
    }

    private function normalizeName(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_values(array_diff($words, self::NAME_NOISE)));
    }

    private function normalizeAddress(string $address): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($address)));
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
