<?php

namespace App\Domains\Accounting\Services\Integration\Tally;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Turns Tally's XML into the kit's canonical arrays. Reads by element name
 * wherever it appears, because the wrapping differs between report and
 * collection exports. Entities are never expanded and the network is off.
 */
class TallyXmlParser
{
    /** Voucher types that never touch the ledgers, so they are not imported. */
    private const NON_ACCOUNTING_TYPES = [
        'sales order', 'purchase order', 'delivery note', 'receipt note', 'rejections in', 'rejections out',
        'stock journal', 'physical stock', 'material in', 'material out', 'job work in order', 'job work out order',
        'memorandum', 'reversing journal', 'attendance', 'payroll',
    ];

    private const VOUCHER_TYPES = [
        'payment' => 'payment',
        'receipt' => 'receipt',
        'contra' => 'contra',
        'journal' => 'journal',
        'sales' => 'sales',
        'purchase' => 'purchase',
        'credit note' => 'credit_note',
        'debit note' => 'debit_note',
    ];

    /** Tally's built-in P&L ledger has no place under a group; the ERP has its own year-end. */
    private const SKIPPED_LEDGERS = ['profit & loss a/c'];

    /**
     * @return array<int, array{external_id: string, name: string, parent_name: ?string, nature: ?string, root_group: ?string}>
     *         parents before children
     */
    public function groups(string $xml): array
    {
        $rows = [];
        foreach ($this->load($xml)->xpath('//GROUP') ?: [] as $node) {
            $name = $this->name($node);
            if ($name === null) {
                continue;
            }
            $rows[mb_strtolower($name)] = [
                'external_id' => $this->text($node, 'GUID') ?: 'name:'.$name,
                'name' => $name,
                'parent_name' => $this->parent($node),
            ];
        }

        $out = [];
        foreach ($rows as $key => $row) {
            [$root, $depth] = $this->walkToRoot($key, $rows);
            $out[] = $row + [
                'root_group' => $root,
                'nature' => TallyGroupMap::classify($root)['type'] ?? null,
                '_depth' => $depth,
            ];
        }

        usort($out, fn ($a, $b) => $a['_depth'] <=> $b['_depth']);

        return array_map(function ($row) {
            unset($row['_depth']);

            return $row;
        }, $out);
    }

    /**
     * @param array<string, string> $groupRoots lowercased custom group name => its predefined root
     * @return array<int, array<string, mixed>>
     */
    public function ledgers(string $xml, array $groupRoots = []): array
    {
        $out = [];
        foreach ($this->load($xml)->xpath('//LEDGER') ?: [] as $node) {
            $name = $this->name($node);
            if ($name === null || in_array(mb_strtolower($name), self::SKIPPED_LEDGERS, true)) {
                continue;
            }

            $parent = $this->parent($node);
            $root = TallyGroupMap::isPredefined($parent) ? $parent : ($groupRoots[mb_strtolower((string) $parent)] ?? null);
            $class = TallyGroupMap::classify($root);

            $out[] = [
                'external_id' => $this->text($node, 'GUID') ?: 'name:'.$name,
                'name' => $name,
                'group_name' => $parent,
                'type' => $class['type'] ?? null,
                'subtype' => $class['subtype'] ?? null,
                'is_cash_or_bank' => $class['is_cash_or_bank'] ?? false,
            ];
        }

        return $out;
    }

    /**
     * Vouchers in canonical form. Optional ones and non-accounting types are
     * dropped here. A custom type is resolved through $typeMap, or left to fail
     * in the ingest so somebody can map it.
     *
     * @param array<string, string> $typeMap lowercased Tally type => canonical type
     * @return array<int, array<string, mixed>>
     */
    public function vouchers(string $xml, array $typeMap = []): array
    {
        $out = [];
        foreach ($this->load($xml)->xpath('//VOUCHER') ?: [] as $node) {
            $tallyType = $this->text($node, 'VOUCHERTYPENAME') ?: (string) ($node['VCHTYPE'] ?? '');
            $typeKey = mb_strtolower(trim($tallyType));

            if (in_array($typeKey, self::NON_ACCOUNTING_TYPES, true) || $this->yes($node, 'ISOPTIONAL')) {
                continue;
            }

            $externalId = $this->text($node, 'GUID') ?: (string) ($node['REMOTEID'] ?? '') ?: (string) ($node['VCHKEY'] ?? '');

            $out[] = [
                'external_id' => $externalId,
                'remote_id' => (string) ($node['REMOTEID'] ?? '') ?: $this->text($node, 'REMOTEID'),
                'voucher_type' => $typeMap[$typeKey] ?? self::VOUCHER_TYPES[$typeKey] ?? null,
                'source_type_name' => $tallyType,
                'date' => $this->date($this->text($node, 'DATE')),
                'number' => $this->text($node, 'VOUCHERNUMBER'),
                'narration' => $this->text($node, 'NARRATION'),
                'party_name' => $this->text($node, 'PARTYLEDGERNAME'),
                'is_cancelled' => $this->yes($node, 'ISCANCELLED') || $this->yes($node, 'ISDELETED'),
                'lines' => $this->lines($node),
            ];
        }

        return $out;
    }

    /**
     * Reads an import response. Tally reports totals rather than per-record
     * results, which is why vouchers are pushed one to a job.
     *
     * @return array{ok: bool, created: int, altered: int, errors: int, last_voucher_id: ?string, error: ?string}
     */
    public function importResponse(string $xml): array
    {
        $root = $this->load($xml);
        $count = function (string $tag) use ($root): int {
            $nodes = $root->xpath('//'.$tag);

            return $nodes ? (int) (string) $nodes[0] : 0;
        };

        $lineErrors = array_map(fn ($n) => trim((string) $n), $root->xpath('//LINEERROR') ?: []);
        $created = $count('CREATED');
        $altered = $count('ALTERED');
        $combined = $count('COMBINED');
        $ignored = $count('IGNORED');
        $errors = $count('ERRORS');
        $status = $root->xpath('//STATUS');
        $failedStatus = $status && (string) $status[0] === '0';
        $accepted = $created + $altered + $combined + $ignored;
        $lastId = $root->xpath('//LASTVCHID');

        $error = $lineErrors !== [] ? implode('; ', $lineErrors) : null;
        if ($error === null && ($errors > 0 || $failedStatus || $accepted === 0)) {
            $data = $root->xpath('//DATA');
            $error = ($data && trim((string) $data[0]) !== '') ? trim((string) $data[0]) : 'Tally accepted nothing and gave no reason.';
        }

        return [
            'ok' => $error === null,
            'created' => $created,
            'altered' => $altered,
            'errors' => $errors,
            'last_voucher_id' => $lastId && (string) $lastId[0] !== '' && (string) $lastId[0] !== '0' ? (string) $lastId[0] : null,
            'error' => $error,
        ];
    }

    /** @return array<int, array{ledger_name: string, debit: float, credit: float}> */
    private function lines(SimpleXMLElement $voucher): array
    {
        $nodes = $voucher->xpath('./ALLLEDGERENTRIES.LIST') ?: [];
        if ($nodes === []) {
            $nodes = $voucher->xpath('./LEDGERENTRIES.LIST') ?: [];
        }

        // Item invoices keep the sales or purchase ledger amount under the inventory lines.
        $allocations = $voucher->xpath('./ALLINVENTORYENTRIES.LIST/ACCOUNTINGALLOCATIONS.LIST') ?: [];
        if ($allocations === []) {
            $allocations = $voucher->xpath('./INVENTORYENTRIES.LIST/ACCOUNTINGALLOCATIONS.LIST') ?: [];
        }

        $lines = [];
        foreach ([...$nodes, ...$allocations] as $node) {
            $ledger = $this->text($node, 'LEDGERNAME');
            $amount = $this->amount($this->text($node, 'AMOUNT'));

            if ($ledger === null || $amount === 0.0) {
                continue;
            }

            $lines[] = [
                'ledger_name' => $ledger,
                'debit' => $amount < 0 ? abs($amount) : 0.0,
                'credit' => $amount > 0 ? $amount : 0.0,
            ];
        }

        return $lines;
    }

    /** Strips "Rate = ..." style suffixes and forex prefixes down to the base-currency figure. */
    private function amount(?string $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        $value = trim($raw);
        if (str_contains($value, '=')) {
            $value = trim(substr($value, strrpos($value, '=') + 1));
        }

        $value = preg_replace('/[^0-9.\-]/', '', $value);

        return round((float) $value, 2);
    }

    private function date(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return preg_match('/^\d{8}$/', $raw)
                ? Carbon::createFromFormat('Ymd', $raw)->toDateString()
                : Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0: ?string, 1: int} predefined root name and depth */
    private function walkToRoot(string $key, array $rows): array
    {
        $seen = [];
        $depth = 0;
        $current = $key;

        while (true) {
            $row = $rows[$current] ?? null;
            $name = $row['name'] ?? $current;

            if (TallyGroupMap::isPredefined($name)) {
                return [$name, $depth];
            }

            $parent = $row['parent_name'] ?? null;
            if ($parent === null || isset($seen[$current])) {
                return [null, $depth];
            }

            $seen[$current] = true;
            $current = mb_strtolower($parent);
            $depth++;
        }
    }

    private function name(SimpleXMLElement $node): ?string
    {
        $attribute = trim((string) ($node['NAME'] ?? ''));
        if ($attribute !== '') {
            return $attribute;
        }

        return $this->text($node, 'NAME') ?: $this->firstText($node, './LANGUAGENAME.LIST/NAME.LIST/NAME');
    }

    /** Null means a top-level group. */
    private function parent(SimpleXMLElement $node): ?string
    {
        $parent = $this->text($node, 'PARENT');

        return $parent === null || $parent === '' || strcasecmp($parent, 'Primary') === 0 ? null : $parent;
    }

    private function yes(SimpleXMLElement $node, string $tag): bool
    {
        return strcasecmp((string) $this->text($node, $tag), 'Yes') === 0;
    }

    private function text(SimpleXMLElement $node, string $tag): ?string
    {
        return $this->firstText($node, './'.$tag);
    }

    private function firstText(SimpleXMLElement $node, string $path): ?string
    {
        $found = $node->xpath($path);
        if (! $found) {
            return null;
        }

        $value = trim((string) $found[0]);

        return $value === '' ? null : $value;
    }

    private function load(string $xml): SimpleXMLElement
    {
        if (str_starts_with($xml, "\xFF\xFE") || str_starts_with($xml, "\xFE\xFF")) {
            $xml = mb_convert_encoding($xml, 'UTF-8', 'UTF-16');
            $xml = preg_replace('/^\s*<\?xml[^>]*\?>/', '', $xml) ?? $xml;
        }

        // Tally writes control characters as numeric references, which strict XML refuses.
        $xml = preg_replace('/&#(?:[0-8]|1[0-2]|14|15|1[6-9]|2\d|3[01]);/', '', $xml) ?? $xml;
        $xml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $xml) ?? $xml;

        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new InvalidArgumentException('Tally XML must not contain a DOCTYPE.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
            if ($root === false) {
                $first = libxml_get_errors()[0] ?? null;
                throw new InvalidArgumentException('Could not read the XML from Tally'.($first ? ': '.trim($first->message) : '.'));
            }

            return $root;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
