<?php

namespace App\Domains\Accounting\Services\Gst;

use App\Domains\Accounting\Models\Gstr2bImport;
use App\Domains\Accounting\Models\Gstr2bLine;
use App\Domains\Purchase\Models\Vendor;
use App\Domains\Purchase\Models\VendorBill;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * GSTR-2B reconciliation.
 *
 * The person downloads the GSTR-2B JSON from the GST portal and uploads it here.
 * Every supplier document in it becomes a Gstr2bLine, which is matched against
 * the vendor bills in the books:
 *
 *  - matched           same supplier GSTIN + invoice number, amounts and date agree
 *  - mismatch          found, but taxable value / tax / total / date / number differ
 *  - missing_in_books  in 2B, but no bill booked for it
 *  - note              credit / debit notes — listed for review, not auto-matched
 *
 * Bills in the books for the period that the 2B doesn't contain ("vendor hasn't
 * filed") are computed live by booksOnly(), so they update as bills are booked.
 */
class Gstr2bReconciliationService
{
    /** Rupee tolerance on every amount comparison. */
    public const TOLERANCE = 1.00;

    /** Days either side for a "same amount, different invoice number" probable match. */
    public const PROBABLE_DATE_WINDOW = 3;

    private const SECTION_TYPES = [
        'b2b' => 'invoice',
        'b2ba' => 'invoice',
        'cdnr' => 'note',
        'cdnra' => 'note',
    ];

    public function __construct(private readonly Gstr1ReturnService $gstr1)
    {
    }

    // ------------------------------------------------------------------
    // Import
    // ------------------------------------------------------------------

    /**
     * Parse an uploaded GSTR-2B JSON, store it (replacing any earlier upload of
     * the same period for this company) and run the matching.
     */
    public function import(string $json, ?string $fileName, ?int $userId): Gstr2bImport
    {
        $parsed = $this->parse($json);

        $ownGstin = $this->gstr1->seller()['gstin'] ?? null;
        if ($ownGstin && $parsed['gstin'] && strtoupper($parsed['gstin']) !== strtoupper($ownGstin)) {
            throw new InvalidArgumentException(
                "This GSTR-2B belongs to GSTIN {$parsed['gstin']}, but this company's GSTIN is {$ownGstin}."
            );
        }

        $companyId = $this->companyId();

        return DB::transaction(function () use ($parsed, $fileName, $userId, $companyId) {
            Gstr2bImport::query()
                ->where('return_period', $parsed['period'])
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->get()
                ->each(function (Gstr2bImport $old) {
                    $old->lines()->delete();
                    $old->delete();
                });

            $import = Gstr2bImport::create([
                'company_id' => $companyId,
                'return_period' => $parsed['period'],
                'gstin' => $parsed['gstin'],
                'generated_on' => $parsed['generated_on'],
                'file_name' => $fileName,
                'line_count' => count($parsed['lines']),
                'imported_by' => $userId,
            ]);

            foreach ($parsed['lines'] as $line) {
                $import->lines()->create($line + ['tenant_id' => $import->tenant_id]);
            }

            return $this->match($import);
        });
    }

    /**
     * Turn the portal JSON into flat line arrays.
     *
     * Accepts the portal file as downloaded ({"data": {...}}) or its inner object.
     *
     * @return array{gstin: ?string, period: string, generated_on: ?string, lines: array<int, array<string, mixed>>}
     */
    public function parse(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The file is not valid JSON. Upload the GSTR-2B JSON downloaded from the GST portal.');
        }

        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
        $period = (string) ($data['rtnprd'] ?? '');
        $docdata = $data['docdata'] ?? null;

        if (! preg_match('/^(0[1-9]|1[0-2])\d{4}$/', $period) || ! is_array($docdata)) {
            throw new InvalidArgumentException(
                'This does not look like a GSTR-2B file (return period "rtnprd" or "docdata" is missing). Download it from GST portal → Returns → GSTR-2B → Download JSON.'
            );
        }

        $lines = [];
        foreach (self::SECTION_TYPES as $section => $kind) {
            foreach ((array) ($docdata[$section] ?? []) as $supplier) {
                $docs = $kind === 'invoice' ? ($supplier['inv'] ?? []) : ($supplier['nt'] ?? []);
                foreach ((array) $docs as $doc) {
                    $lines[] = $this->lineFrom($section, $kind, (array) $supplier, (array) $doc);
                }
            }
        }

        return [
            'gstin' => isset($data['gstin']) ? strtoupper(trim((string) $data['gstin'])) : null,
            'period' => $period,
            'generated_on' => $this->date($data['gendt'] ?? null),
            'lines' => $lines,
        ];
    }

    private function lineFrom(string $section, string $kind, array $supplier, array $doc): array
    {
        $number = (string) ($kind === 'invoice' ? ($doc['inum'] ?? '') : ($doc['ntnum'] ?? ''));

        // Invoice-level totals when present, otherwise the sum of the rate-wise items.
        $items = (array) ($doc['items'] ?? []);
        $sum = fn (string $key) => array_key_exists($key, $doc)
            ? (float) $doc[$key]
            : array_sum(array_map(fn ($i) => (float) ($i[$key] ?? 0), $items));

        $documentType = 'invoice';
        if ($kind === 'note') {
            $documentType = strtoupper((string) ($doc['typ'] ?? 'C')) === 'D' ? 'debit_note' : 'credit_note';
        }

        return [
            'section' => $section,
            'document_type' => $documentType,
            'supplier_gstin' => strtoupper(trim((string) ($supplier['ctin'] ?? ''))),
            'supplier_name' => $supplier['trdnm'] ?? null,
            'document_number' => mb_substr($number, 0, 50),
            'normalized_number' => self::normalizeNumber($number),
            'document_date' => $this->date($doc['dt'] ?? null),
            'supplier_period' => $supplier['supprd'] ?? null,
            'supplier_filed_on' => $this->date($supplier['supfildt'] ?? null),
            'place_of_supply' => isset($doc['pos']) ? str_pad((string) $doc['pos'], 2, '0', STR_PAD_LEFT) : null,
            'reverse_charge' => strtoupper((string) ($doc['rev'] ?? 'N')) === 'Y',
            'itc_available' => isset($doc['itcavl']) ? strtoupper(substr((string) $doc['itcavl'], 0, 1)) : null,
            'itc_reason' => ($doc['rsn'] ?? null) ?: null,
            'taxable_value' => round($sum('txval'), 2),
            'igst' => round($sum('igst'), 2),
            'cgst' => round($sum('cgst'), 2),
            'sgst' => round($sum('sgst'), 2),
            'cess' => round($sum('cess'), 2),
            'total_value' => round((float) ($doc['val'] ?? 0), 2),
            'match_status' => $kind === 'note' ? Gstr2bLine::NOTE : Gstr2bLine::MISSING_IN_BOOKS,
        ];
    }

    // ------------------------------------------------------------------
    // Matching
    // ------------------------------------------------------------------

    /** (Re-)match every invoice line of an import against the vendor bills. */
    public function match(Gstr2bImport $import): Gstr2bImport
    {
        $lines = $import->lines()->get();
        $gstins = $lines->pluck('supplier_gstin')->filter()->unique()->values();

        $vendorIds = Vendor::query()
            ->whereIn(DB::raw('UPPER(gstin)'), $gstins->all())
            ->get(['id', 'gstin'])
            ->groupBy(fn ($v) => strtoupper(trim((string) $v->gstin)))
            ->map(fn ($g) => $g->pluck('id')->all());

        $bills = $this->billQuery($import)
            ->whereIn('vendor_id', $vendorIds->flatten()->all() ?: [0])
            ->get();

        $billsByVendor = $bills->groupBy('vendor_id');
        $used = [];

        // Exact number matches first, so a probable (amount/date) match can never
        // steal a bill that another line matches by number.
        $invoiceLines = $lines->where('document_type', 'invoice');
        $pending = [];

        foreach ($invoiceLines as $line) {
            $candidates = collect($vendorIds->get($line->supplier_gstin, []))
                ->flatMap(fn ($id) => $billsByVendor->get($id, collect()));

            $bill = $candidates->first(fn (VendorBill $b) => ! isset($used[$b->id])
                && $this->sameNumber($line->document_number, $this->billInvoiceNumber($b)));

            if ($bill) {
                $used[$bill->id] = true;
                $this->applyResult($line, $bill, false);
            } else {
                $pending[] = [$line, $candidates];
            }
        }

        foreach ($pending as [$line, $candidates]) {
            $bill = $candidates->first(fn (VendorBill $b) => ! isset($used[$b->id]) && $this->isProbable($line, $b));

            if ($bill) {
                $used[$bill->id] = true;
                $this->applyResult($line, $bill, true);
            } else {
                $line->forceFill([
                    'match_status' => Gstr2bLine::MISSING_IN_BOOKS,
                    'vendor_bill_id' => null,
                    'differences' => $this->itcNotes($line, $vendorIds->has($line->supplier_gstin)
                        ? []
                        : ['No vendor in your books has GSTIN ' . $line->supplier_gstin . '.']),
                ])->save();
            }
        }

        foreach ($lines->where('document_type', '!=', 'invoice') as $line) {
            $line->forceFill(['match_status' => Gstr2bLine::NOTE, 'differences' => $this->itcNotes($line, [])])->save();
        }

        $import->forceFill([
            'matched_at' => now(),
            'summary' => $this->summarize($import),
        ])->save();

        return $import->refresh();
    }

    private function applyResult(Gstr2bLine $line, VendorBill $bill, bool $probable): void
    {
        $differences = $this->compare($line, $bill);

        if ($probable) {
            array_unshift($differences, 'Invoice number differs: 2B has "' . $line->document_number
                . '", books have "' . ($this->billInvoiceNumber($bill) ?: $bill->bill_number) . '".');
        }

        $line->forceFill([
            'match_status' => $differences ? Gstr2bLine::MISMATCH : Gstr2bLine::MATCHED,
            'vendor_bill_id' => $bill->id,
            'differences' => $this->itcNotes($line, $differences),
        ])->save();
    }

    /** @return list<string> human-readable differences between the 2B line and the bill */
    public function compare(Gstr2bLine $line, VendorBill $bill): array
    {
        $books = $this->billAmounts($bill);
        $out = [];

        $check = function (string $label, float $portal, float $book) use (&$out) {
            if (abs($portal - $book) > self::TOLERANCE) {
                $out[] = sprintf('%s: 2B %s, books %s (diff %s)', $label,
                    number_format($portal, 2), number_format($book, 2), number_format($portal - $book, 2));
            }
        };

        $check('Taxable value', $line->taxable_value, $books['taxable']);

        if ($books['split_known']) {
            $check('IGST', $line->igst, $books['igst']);
            $check('CGST', $line->cgst, $books['cgst']);
            $check('SGST', $line->sgst, $books['sgst']);
        } else {
            $check('Total tax', round($line->igst + $line->cgst + $line->sgst, 2), $books['tax']);
        }

        if ($line->total_value > 0) {
            $check('Invoice value', $line->total_value, $books['total']);
        }

        if ($line->document_date && $bill->bill_date && ! $line->document_date->isSameDay($bill->bill_date)) {
            $out[] = 'Invoice date: 2B ' . $line->document_date->format('d-m-Y') . ', books ' . $bill->bill_date->format('d-m-Y');
        }

        return $out;
    }

    /** Taxable value, tax split and total as booked on the bill. */
    public function billAmounts(VendorBill $bill): array
    {
        $tax = (float) $bill->tax_amount;
        $igst = (float) $bill->igst_amount;
        $cgst = (float) $bill->cgst_amount;
        $sgst = (float) $bill->sgst_amount;
        $total = (float) $bill->grand_total;

        return [
            'taxable' => round($total - $tax - (float) ($bill->adjustment ?? 0), 2),
            'igst' => $igst,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'split_known' => abs(($igst + $cgst + $sgst) - $tax) <= self::TOLERANCE && ($igst + $cgst + $sgst) > 0,
        ];
    }

    private function isProbable(Gstr2bLine $line, VendorBill $bill): bool
    {
        if (! $line->document_date || ! $bill->bill_date || $line->total_value <= 0) {
            return false;
        }

        return abs($line->total_value - (float) $bill->grand_total) <= self::TOLERANCE
            && abs($line->document_date->diffInDays($bill->bill_date, false)) <= self::PROBABLE_DATE_WINDOW;
    }

    private function itcNotes(Gstr2bLine $line, array $differences): ?array
    {
        if ($line->itc_available === 'N') {
            $differences[] = 'ITC not available as per 2B' . ($line->itc_reason ? ' (' . $line->itc_reason . ')' : '') . '.';
        }
        if ($line->reverse_charge) {
            $differences[] = 'Reverse charge — pay the tax yourself before claiming ITC.';
        }

        return $differences ?: null;
    }

    // ------------------------------------------------------------------
    // Books-only bills and summary
    // ------------------------------------------------------------------

    /**
     * Taxed bills dated in the import's period, from vendors with a GSTIN, that no
     * 2B line matched — the vendor hasn't filed them (or filed late / under the
     * wrong GSTIN). ITC on these is at risk.
     */
    public function booksOnly(Gstr2bImport $import): Collection
    {
        [$from, $to] = $this->periodRange($import->return_period);

        $matchedIds = $import->lines()->whereNotNull('vendor_bill_id')->pluck('vendor_bill_id')->all();

        return $this->billQuery($import)
            ->with('vendor:id,name,company_name,gstin')
            ->whereBetween('bill_date', [$from->toDateString(), $to->toDateString()])
            ->where('tax_amount', '>', 0)
            ->whereNotIn('id', $matchedIds ?: [0])
            ->whereHas('vendor', fn ($q) => $q->whereNotNull('gstin')->where('gstin', '!=', ''))
            ->orderBy('bill_date')
            ->get();
    }

    /** Taxed bills in the period whose vendor has no GSTIN — they can never appear in 2B. */
    public function billsWithoutVendorGstin(Gstr2bImport $import): int
    {
        [$from, $to] = $this->periodRange($import->return_period);

        return $this->billQuery($import)
            ->whereBetween('bill_date', [$from->toDateString(), $to->toDateString()])
            ->where('tax_amount', '>', 0)
            ->whereHas('vendor', fn ($q) => $q->whereNull('gstin')->orWhere('gstin', ''))
            ->count();
    }

    public function summarize(Gstr2bImport $import): array
    {
        $lines = $import->lines()->get();
        $booksOnly = $this->booksOnly($import);

        $bucket = function (Collection $rows) {
            return [
                'count' => $rows->count(),
                'taxable' => round($rows->sum('taxable_value'), 2),
                'tax' => round($rows->sum(fn (Gstr2bLine $l) => $l->totalTax()), 2),
            ];
        };

        $eligible = $lines->where('document_type', 'invoice')->where('itc_available', '!=', 'N');

        return [
            Gstr2bLine::MATCHED => $bucket($lines->where('match_status', Gstr2bLine::MATCHED)),
            Gstr2bLine::MISMATCH => $bucket($lines->where('match_status', Gstr2bLine::MISMATCH)),
            Gstr2bLine::MISSING_IN_BOOKS => $bucket($lines->where('match_status', Gstr2bLine::MISSING_IN_BOOKS)),
            Gstr2bLine::NOTE => $bucket($lines->where('match_status', Gstr2bLine::NOTE)),
            'books_only' => [
                'count' => $booksOnly->count(),
                'taxable' => round($booksOnly->sum(fn ($b) => $this->billAmounts($b)['taxable']), 2),
                'tax' => round($booksOnly->sum(fn ($b) => (float) $b->tax_amount), 2),
            ],
            'itc_as_per_2b' => round($eligible->sum(fn (Gstr2bLine $l) => $l->totalTax()), 2),
            'itc_not_available' => round($lines->where('itc_available', 'N')->sum(fn (Gstr2bLine $l) => $l->totalTax()), 2),
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Upper-case alphanumerics only: "inv/2026-001" → "INV2026001". */
    public static function normalizeNumber(?string $number): string
    {
        return mb_substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $number)) ?? '', 0, 50);
    }

    private function sameNumber(?string $a, ?string $b): bool
    {
        $na = self::normalizeNumber($a);
        $nb = self::normalizeNumber($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        // "INV-0042" in the portal vs "INV-42" in the books.
        return $na === $nb || preg_replace('/(?<=[A-Z]|^)0+(?=\d)/', '', $na) === preg_replace('/(?<=[A-Z]|^)0+(?=\d)/', '', $nb);
    }

    private function billInvoiceNumber(VendorBill $bill): ?string
    {
        return $bill->vendor_invoice_number ?: $bill->vendor_bill_number;
    }

    /** Bills of the import's company, across all its branches (2B is per GSTIN). */
    private function billQuery(Gstr2bImport $import)
    {
        return VendorBill::withoutGlobalScope('branch')
            ->withoutGlobalScopes(['branch', 'company'])
            ->when($import->company_id, fn ($q) => $q->where('company_id', $import->company_id))
            ->whereNotIn('status', ['Cancelled', 'Draft']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function periodRange(string $period): array
    {
        $from = Carbon::createFromDate((int) substr($period, 2), (int) substr($period, 0, 2), 1)->startOfDay();

        return [$from, $from->copy()->endOfMonth()];
    }

    private function companyId(): ?int
    {
        return company_id() ?? current_company_id();
    }

    private function date($value): ?string
    {
        if (! $value) {
            return null;
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, (string) $value);
                if ($date && $date->format($format) === (string) $value) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // try the next format
            }
        }

        return null;
    }
}
