<?php

namespace App\Domains\Accounting\Services\Gst;

use App\Domains\Accounting\Models\GstReturnDocument;
use App\Domains\Accounting\Models\GstReturnFiling;
use App\Domains\Accounting\Support\GstStates;
use App\Domains\HRMS\Models\Company;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\SalesReturn;
use App\Models\GstConfiguration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * GSTR-1 upload (Tally's "Upload GST Returns"): works out which invoices and
 * credit notes of a return period still have to go to the GST portal, which
 * were uploaded, which changed after upload and which must be deleted there,
 * and produces the portal's GSTR-1 JSON (b2b, b2cl, b2cs, cdnr, cdnur, hsn,
 * doc_issue).
 *
 * Summaries (B2CS, HSN, documents issued) are always sent for the whole
 * period, because the portal replaces them; invoice-level sections only carry
 * what is new, modified or to be deleted.
 */
class Gstr1ReturnService
{
    public const RETURN_TYPE = 'GSTR1';

    public const JSON_VERSION = 'GST3.2.2';

    /** Invoices that have been issued (Draft is not a supply yet; Cancelled is reported as a deletion if it was uploaded). */
    public const LIVE_INVOICE_STATUSES = ['Posted', 'Sent', 'Partially Paid', 'Partial', 'Paid', 'Overdue'];

    public const LIVE_RETURN_STATUSES = ['Completed'];

    public const VALID_RATES = [0, 0.1, 0.25, 1, 1.5, 3, 5, 6, 7.5, 12, 18, 28, 40];

    /** Inter-state B2C invoices above this are reported one by one (B2C Large). Notification 12/2024-CT, from 1 Aug 2024. */
    public const B2CL_LIMIT = 100000;
    public const B2CL_LIMIT_BEFORE_AUG_2024 = 250000;

    public const STATE_PENDING = 'pending';
    public const STATE_MODIFIED = 'modified';
    public const STATE_UPLOADED = 'uploaded';
    public const STATE_DELETE = 'delete';                 // uploaded, then cancelled / deleted here
    public const STATE_DELETE_REQUESTED = 'delete_requested';
    public const STATE_REMOVED = 'removed';               // deleted from the portal
    public const STATE_EXCEPTION = 'exception';

    public const SECTION_LABELS = [
        'b2b' => 'B2B Invoices - 4A, 4B, 6B, 6C',
        'b2cl' => 'B2C (Large) Invoices - 5A, 5B',
        'b2cs' => 'B2C (Small) Invoices - 7',
        'cdnr' => 'Credit/Debit Notes (Registered) - 9B',
        'cdnur' => 'Credit/Debit Notes (Unregistered) - 9B',
    ];

    /**
     * @return array{gstin: ?string, state: ?string, name: ?string, source: string}
     */
    public function seller(): array
    {
        $config = GstConfiguration::getForCurrentContext();

        if ($config && $config->seller_gstin) {
            $gstin = strtoupper($config->seller_gstin);

            return [
                'gstin' => $gstin,
                'state' => $config->state_code ? str_pad((string) $config->state_code, 2, '0', STR_PAD_LEFT) : GstStates::fromGstin($gstin),
                'name' => $config->legal_name ?: $config->trade_name,
                'source' => 'GST & E-Invoice Setup',
            ];
        }

        $companyId = current_company_id();
        $company = ($companyId ? Company::find($companyId) : null) ?? Company::first();
        $gstin = $company?->gst_number ? strtoupper(trim($company->gst_number)) : null;

        return [
            'gstin' => $gstin,
            'state' => GstStates::fromGstin($gstin),
            'name' => $company?->name,
            'source' => 'Company',
        ];
    }

    /**
     * The portal's return period ("fp", MMYYYY) for a full month or a full quarter.
     */
    public function returnPeriod(Carbon $from, Carbon $to): ?string
    {
        if (! $from->isSameDay($from->copy()->startOfMonth()) || ! $to->isSameDay($to->copy()->endOfMonth())) {
            return null;
        }

        $months = ($to->year - $from->year) * 12 + $to->month - $from->month + 1;

        if ($months === 1) {
            return $from->format('mY');
        }

        if ($months === 3 && in_array($from->month, [1, 4, 7, 10], true)) {
            return $to->format('mY');
        }

        return null;
    }

    /**
     * Everything the screen and the export need for one period.
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $seller = $this->seller();
        $fp = $this->returnPeriod($from, $to);
        $periodKey = $fp ?? $to->format('mY');

        $invoices = Invoice::withoutGlobalScope('branch')
            ->with(['customer', 'items.product.uom'])
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('invoice_date')->orderBy('id')
            ->get();

        $returns = SalesReturn::withoutGlobalScope('branch')
            ->with(['customer', 'invoice.customer', 'invoice.items', 'items.product.uom'])
            ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('return_date')->orderBy('id')
            ->get();

        $documents = [];

        foreach ($invoices as $invoice) {
            if (in_array($invoice->status, self::LIVE_INVOICE_STATUSES, true)) {
                $documents["invoice:{$invoice->id}"] = $this->analyseInvoice($invoice, $seller);
            }
        }

        foreach ($returns as $return) {
            if (in_array($return->status, self::LIVE_RETURN_STATUSES, true)) {
                $documents["credit_note:{$return->id}"] = $this->analyseCreditNote($return, $seller);
            }
        }

        $tracked = $this->trackedFor(array_keys($documents), $periodKey);

        foreach ($documents as $key => &$document) {
            $document['tracked'] = $tracked[$key] ?? null;
            $document['state'] = $this->stateOf($document);
        }
        unset($document);

        // Uploaded before, but no longer a live document of this period: delete it on the portal.
        foreach ($tracked as $key => $record) {
            if (isset($documents[$key]) || $record->return_period !== $periodKey) {
                continue;
            }

            if (in_array($record->status, [GstReturnDocument::STATUS_UPLOADED, GstReturnDocument::STATUS_DELETE_REQUESTED], true)) {
                $documents[$key] = $this->documentFromRecord($record, self::STATE_DELETE);
            } elseif ($record->status === GstReturnDocument::STATUS_DELETED) {
                $documents[$key] = $this->documentFromRecord($record, self::STATE_REMOVED);
            }
        }

        $reportable = array_filter($documents, fn ($doc) => $doc['live'] && ! in_array($doc['state'], [self::STATE_EXCEPTION, self::STATE_REMOVED], true));

        $b2cs = $this->b2csSummary($reportable);
        $hsn = $this->hsnSummary($reportable);
        $docIssue = $this->documentsIssued($invoices, $returns);

        $groups = [
            'pending' => [], 'uploaded' => [], 'removed' => [], 'exception' => [],
        ];

        foreach ($documents as $key => $doc) {
            $group = match ($doc['state']) {
                self::STATE_UPLOADED => 'uploaded',
                self::STATE_REMOVED => 'removed',
                self::STATE_EXCEPTION => 'exception',
                default => 'pending',
            };
            $groups[$group][$key] = $doc;
        }

        $summaryCount = count($b2cs) + count($hsn['b2b']) + count($hsn['b2c']) + count($docIssue);

        return [
            'from' => $from,
            'to' => $to,
            'seller' => $seller,
            'fp' => $fp,
            'period_key' => $periodKey,
            'documents' => $documents,
            'groups' => $groups,
            'b2cs' => $b2cs,
            'hsn' => $hsn,
            'doc_issue' => $docIssue,
            'sections' => $this->sectionTotals($reportable, $b2cs),
            'pending_count' => count($groups['pending']),
            'summary_count' => $summaryCount,
            'blockers' => $this->blockers($seller, $fp),
        ];
    }

    /**
     * Builds the GSTR-1 JSON for the chosen pending documents (all pending
     * when $keys is null) and, when asked, marks them uploaded.
     */
    public function export(array $built, ?array $keys, ?int $userId, bool $markUploaded = true): GstReturnFiling
    {
        if ($built['blockers'] !== []) {
            throw new InvalidArgumentException($built['blockers'][0]);
        }

        $selected = $this->selectPending($built, $keys);

        if ($selected === [] && ($built['b2cs'] === [] && $built['hsn']['b2b'] === [] && $built['hsn']['b2c'] === [])) {
            throw new InvalidArgumentException('There is nothing to upload for this period.');
        }

        $json = $this->json($built, $selected);
        $totals = $this->totalsOf($selected);

        return DB::transaction(function () use ($built, $selected, $json, $totals, $userId, $markUploaded) {
            $filing = GstReturnFiling::create([
                'tenant_id' => require_tenant_id(),
                'company_id' => current_company_id(),
                'branch_id' => current_branch_id(),
                'return_type' => self::RETURN_TYPE,
                'return_period' => $built['fp'],
                'gstin' => $built['seller']['gstin'],
                'action' => GstReturnFiling::ACTION_EXPORT,
                'voucher_count' => count(array_filter($selected, fn ($doc) => $doc['live'] && $doc['state'] !== self::STATE_DELETE_REQUESTED)),
                'delete_count' => count(array_filter($selected, fn ($doc) => ! $doc['live'] || $doc['state'] === self::STATE_DELETE_REQUESTED)),
                'file_name' => $this->fileName($built),
                'payload' => json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'totals' => $totals,
                'created_by' => $userId,
            ]);

            if ($markUploaded) {
                $this->record($built, $selected, $filing, $userId);
            }

            return $filing;
        });
    }

    /** Marks documents as uploaded without producing a file (uploaded some other way). */
    public function markUploaded(array $built, array $keys, ?int $userId): int
    {
        $selected = $this->selectPending($built, $keys);

        if ($selected === []) {
            throw new InvalidArgumentException('Select the vouchers to mark as uploaded.');
        }

        DB::transaction(function () use ($built, $selected, $userId) {
            $filing = GstReturnFiling::create([
                'tenant_id' => require_tenant_id(),
                'company_id' => current_company_id(),
                'branch_id' => current_branch_id(),
                'return_type' => self::RETURN_TYPE,
                'return_period' => $built['period_key'],
                'gstin' => $built['seller']['gstin'],
                'action' => GstReturnFiling::ACTION_MARK,
                'voucher_count' => count($selected),
                'totals' => $this->totalsOf($selected),
                'created_by' => $userId,
            ]);

            $this->record($built, $selected, $filing, $userId);
        });

        return count($selected);
    }

    /** Uploaded → ask for deletion on the portal with the next upload. */
    public function requestDelete(array $keys): int
    {
        return $this->trackedByKeys($keys)
            ->where('status', GstReturnDocument::STATUS_UPLOADED)
            ->each(fn ($record) => $record->update(['status' => GstReturnDocument::STATUS_DELETE_REQUESTED]))
            ->count();
    }

    public function resetDeleteRequest(array $keys): int
    {
        return $this->trackedByKeys($keys)
            ->where('status', GstReturnDocument::STATUS_DELETE_REQUESTED)
            ->each(fn ($record) => $record->update(['status' => GstReturnDocument::STATUS_UPLOADED]))
            ->count();
    }

    /** Forget the upload status: the documents become pending again. */
    public function resetStatus(array $keys): int
    {
        $records = $this->trackedByKeys($keys);
        $records->each->delete();

        return $records->count();
    }

    // ------------------------------------------------------------ analysis

    private function analyseInvoice(Invoice $invoice, array $seller): array
    {
        $issues = [];
        $customer = $invoice->customer;
        $gstin = strtoupper(trim((string) $customer?->gstin));
        $registered = $gstin !== '';
        $inter = $invoice->gst_type === 'igst' || (float) $invoice->igst_amount > 0;

        if ($registered && ! GstStates::isValidGstin($gstin)) {
            $issues[] = "Customer GSTIN {$gstin} is not a valid GSTIN.";
        }

        $pos = $this->placeOfSupply($customer, $registered ? $gstin : null, $inter, $seller);
        $issues = array_merge($issues, $this->posIssues($pos, $inter, $seller));
        $issues = array_merge($issues, $this->numberIssues((string) $invoice->invoice_number, 'Invoice'));

        [$lines, $lineIssues] = $this->invoiceLines($invoice, $inter);
        $issues = array_merge($issues, $lineIssues);

        $date = Carbon::parse($invoice->invoice_date);
        $value = round((float) $invoice->total_amount, 2);
        $limit = $date->lt(Carbon::create(2024, 8, 1)) ? self::B2CL_LIMIT_BEFORE_AUG_2024 : self::B2CL_LIMIT;
        $section = $registered ? 'b2b' : (($inter && $value > $limit) ? 'b2cl' : 'b2cs');
        $items = $this->rateItems($lines);

        $document = $this->document([
            'key' => "invoice:{$invoice->id}",
            'document_type' => GstReturnDocument::TYPE_INVOICE,
            'document_id' => $invoice->id,
            'vch_type' => 'Sales',
            'number' => (string) $invoice->invoice_number,
            'date' => $date,
            'party' => $customer?->name ?? '—',
            'ctin' => $registered ? $gstin : null,
            'pos' => $pos,
            'inter' => $inter,
            'section' => $section,
            'value' => $value,
            'items' => $items,
            'lines' => $lines,
            'issues' => $issues,
            'sign' => 1,
            'url' => route('sales.invoices.show', $invoice->id, false),
        ]);

        $inv = [
            'inum' => (string) $invoice->invoice_number,
            'idt' => $date->format('d-m-Y'),
            'val' => $value,
        ];

        $document['payload'] = match ($section) {
            'b2b' => ['ctin' => $gstin, 'entry' => $inv + ['pos' => $pos, 'rchrg' => 'N', 'inv_typ' => 'R', 'itms' => $this->jsonItems($items, $inter)]],
            'b2cl' => ['pos' => $pos, 'entry' => $inv + ['itms' => $this->jsonItems($items, true)]],
            default => ['pos' => $pos, 'items' => $items],
        };

        return $this->withFingerprint($document);
    }

    private function analyseCreditNote(SalesReturn $return, array $seller): array
    {
        $issues = [];
        $source = $return->invoice;
        $customer = $return->customer ?? $source?->customer;
        $gstin = strtoupper(trim((string) $customer?->gstin));
        $registered = $gstin !== '';
        $sellerState = $seller['state'];

        if ($source) {
            $inter = $source->gst_type === 'igst' || (float) $source->igst_amount > 0;
        } else {
            $guess = $this->placeOfSupply($customer, $registered ? $gstin : null, false, $seller);
            $inter = $guess !== null && $sellerState !== null && $guess !== $sellerState;
        }

        if ($registered && ! GstStates::isValidGstin($gstin)) {
            $issues[] = "Customer GSTIN {$gstin} is not a valid GSTIN.";
        }

        $pos = $this->placeOfSupply($customer, $registered ? $gstin : null, $inter, $seller);
        $issues = array_merge($issues, $this->posIssues($pos, $inter, $seller));
        $issues = array_merge($issues, $this->numberIssues((string) $return->return_number, 'Credit note'));

        [$lines, $lineIssues] = $this->creditNoteLines($return, $source, $inter);
        $issues = array_merge($issues, $lineIssues);

        $date = Carbon::parse($return->return_date);
        $value = round((float) ($return->total_refund_amount ?: $return->total_amount), 2);

        $sourceWasLarge = false;
        if ($source && ! $registered && $inter) {
            $sourceDate = Carbon::parse($source->invoice_date);
            $limit = $sourceDate->lt(Carbon::create(2024, 8, 1)) ? self::B2CL_LIMIT_BEFORE_AUG_2024 : self::B2CL_LIMIT;
            $sourceWasLarge = (float) $source->total_amount > $limit;
        }

        $section = $registered ? 'cdnr' : ($sourceWasLarge ? 'cdnur' : 'b2cs');
        $items = $this->rateItems($lines);

        $document = $this->document([
            'key' => "credit_note:{$return->id}",
            'document_type' => GstReturnDocument::TYPE_CREDIT_NOTE,
            'document_id' => $return->id,
            'vch_type' => 'Credit Note',
            'number' => (string) $return->return_number,
            'date' => $date,
            'party' => $customer?->name ?? '—',
            'ctin' => $registered ? $gstin : null,
            'pos' => $pos,
            'inter' => $inter,
            'section' => $section,
            'value' => $value,
            'items' => $items,
            'lines' => $lines,
            'issues' => $issues,
            'sign' => -1,
            'against' => $source?->invoice_number,
            'url' => route('sales.returns.show', $return->id, false),
        ]);

        $note = [
            'ntty' => 'C',
            'nt_num' => (string) $return->return_number,
            'nt_dt' => $date->format('d-m-Y'),
            'val' => $value,
        ];

        $document['payload'] = match ($section) {
            'cdnr' => ['ctin' => $gstin, 'entry' => $note + ['pos' => $pos, 'rchrg' => 'N', 'inv_typ' => 'R', 'itms' => $this->jsonItems($items, $inter)]],
            'cdnur' => ['entry' => ['typ' => 'B2CL'] + $note + ['pos' => $pos, 'itms' => $this->jsonItems($items, true)]],
            default => ['pos' => $pos, 'items' => $items],
        };

        return $this->withFingerprint($document);
    }

    /**
     * Invoice lines at their GST rate, with taxable value net of any header
     * discount, billed freight spread over the lines at the freight's rate,
     * and the tax actually charged shared out by rate.
     *
     * @return array{0: list<array>, 1: list<string>}
     */
    private function invoiceLines(Invoice $invoice, bool $inter): array
    {
        $issues = [];
        $lines = [];

        foreach ($invoice->items as $item) {
            $lines[] = $this->line(
                $this->invoiceItemRate($invoice, $item),
                max(0.0, (float) $item->quantity * (float) $item->unit_price - (float) ($item->discount ?? 0)),
                $item->product?->hsn_sac,
                $item->item_name ?: $item->product?->name,
                $item->product?->uom?->code ?: $item->product?->uom?->name,
                (float) $item->quantity,
            );
        }

        $net = round((float) $invoice->subtotal - (float) $invoice->discount_amount, 2);
        $headerTax = round((float) $invoice->cgst_amount + (float) $invoice->sgst_amount + (float) $invoice->igst_amount, 2)
            ?: round((float) $invoice->tax_amount, 2);

        if ($lines === []) {
            $rate = $net > 0 ? $this->nearestRate($headerTax / $net * 100) : 0;
            $lines[] = $this->line($rate, $net, null, 'Invoice total', null, 0);
        } else {
            $this->scaleTo($lines, $net);
        }

        $freight = ($invoice->freight_terms === 'To Be Billed') ? round((float) $invoice->freight_amount, 2) : 0.0;

        if ($freight > 0) {
            $rate = $this->freightRate($invoice, $lines);
            $sameRate = array_keys(array_filter($lines, fn ($line) => $line['rt'] == $rate && $line['txval'] > 0));

            if ($sameRate === []) {
                $lines[] = $this->line($rate, $freight, '9965', 'Freight', 'NA', 0);
            } else {
                // Freight billed with the goods is part of that supply: it follows their HSN.
                $base = array_sum(array_map(fn ($i) => $lines[$i]['txval'], $sameRate));
                $left = $freight;
                foreach ($sameRate as $n => $i) {
                    $share = $n === array_key_last($sameRate) ? $left : round($freight * $lines[$i]['txval'] / $base, 2);
                    $lines[$i]['txval'] = round($lines[$i]['txval'] + $share, 2);
                    $left = round($left - $share, 2);
                }
            }
        }

        $issues = array_merge($issues, $this->shareTax($lines, $headerTax, $inter), $this->lineIssues($lines));

        return [$lines, $issues];
    }

    /**
     * @return array{0: list<array>, 1: list<string>}
     */
    private function creditNoteLines(SalesReturn $return, ?Invoice $source, bool $inter): array
    {
        $lines = [];
        $sourceItems = $source ? $source->items->keyBy('id') : collect();

        foreach ($return->items as $item) {
            $sourceItem = $item->invoice_item_id ? $sourceItems->get($item->invoice_item_id) : null;
            $rate = $sourceItem && $source ? $this->invoiceItemRate($source, $sourceItem) : null;

            $lines[] = $this->line(
                $rate ?? -1,
                max(0.0, (float) $item->total_amount ?: (float) $item->quantity * (float) $item->unit_price),
                $item->product?->hsn_sac,
                $item->product?->name ?? $sourceItem?->item_name,
                $item->product?->uom?->code ?: $item->product?->uom?->name,
                (float) $item->quantity,
            );
        }

        $taxable = round((float) $return->total_amount, 2);
        $tax = max(0.0, round((float) $return->total_refund_amount - $taxable, 2));
        $inferred = $taxable > 0 ? $this->nearestRate($tax / $taxable * 100) : 0;

        if ($lines === []) {
            $lines[] = $this->line($inferred, $taxable, null, 'Credit note total', null, 0);
        } else {
            foreach ($lines as &$line) {
                if ($line['rt'] < 0) {
                    $line['rt'] = $inferred;
                }
            }
            unset($line);
            $this->scaleTo($lines, $taxable);
        }

        $issues = array_merge($this->shareTax($lines, $tax, $inter), $this->lineIssues($lines));

        return [$lines, $issues];
    }

    private function invoiceItemRate(Invoice $invoice, InvoiceItem $item): float
    {
        if ($invoice->tax_type === 'without_tax') {
            return 0.0;
        }

        if ($invoice->tax_type === 'order_wise_tax') {
            return (float) $invoice->order_tax_rate;
        }

        $rate = (float) $item->tax_rate;

        if ($rate <= 0) {
            $rate = (float) $item->igst_percent ?: (float) $item->cgst_percent + (float) $item->sgst_percent;
        }

        return round($rate, 2);
    }

    private function freightRate(Invoice $invoice, array $lines): float
    {
        if ($invoice->tax_type === 'without_tax') {
            return 0.0;
        }

        $option = $invoice->freight_tax_rate;

        if ($option !== null && $option !== '' && $option !== 'highest' && is_numeric($option)) {
            return round((float) $option, 2);
        }

        if ($invoice->tax_type === 'order_wise_tax') {
            return round((float) $invoice->order_tax_rate, 2);
        }

        $highest = max(array_map(fn ($line) => $line['rt'], $lines) ?: [0]);

        return $highest > 0 ? $highest : 18.0;
    }

    private function line(float $rate, float $taxable, ?string $hsn, ?string $description, ?string $unit, float $qty): array
    {
        $hsn = preg_replace('/\s+/', '', (string) $hsn);
        $service = str_starts_with($hsn, '99');

        return [
            'rt' => round($rate, 2),
            'txval' => round($taxable, 2),
            'hsn' => $hsn !== '' ? $hsn : null,
            'desc' => mb_substr(trim((string) $description), 0, 30),
            'uqc' => $service || $unit === 'NA' ? 'NA' : GstStates::uqc($unit),
            'qty' => $service ? 0 : round($qty, 3),
            'iamt' => 0.0, 'camt' => 0.0, 'samt' => 0.0,
        ];
    }

    /** Scales line taxable values so they add up to the document's taxable value. */
    private function scaleTo(array &$lines, float $target): void
    {
        $sum = array_sum(array_column($lines, 'txval'));

        if ($sum <= 0 || abs($sum - $target) < 0.005) {
            return;
        }

        $left = $target;
        $last = array_key_last($lines);
        foreach ($lines as $i => &$line) {
            $line['txval'] = $i === $last ? round($left, 2) : round($line['txval'] * $target / $sum, 2);
            $left -= $line['txval'];
        }
        unset($line);
    }

    /**
     * Shares the tax actually charged over the lines by rate × taxable value,
     * so the return ties to the invoice; reports a mismatch over ₹1.
     *
     * @return list<string>
     */
    private function shareTax(array &$lines, float $tax, bool $inter): array
    {
        $issues = [];
        $weights = array_map(fn ($line) => $line['txval'] * $line['rt'] / 100, $lines);
        $expected = round(array_sum($weights), 2);

        if (abs($expected - $tax) > 1.0) {
            $issues[] = 'Tax charged (' . number_format($tax, 2) . ') does not agree with rate × taxable value (' . number_format($expected, 2) . ').';
        }

        $left = $tax;
        $weighted = array_keys(array_filter($weights, fn ($w) => $w > 0));
        $last = end($weighted);

        foreach ($lines as $i => &$line) {
            $share = 0.0;
            if ($expected > 0 && $weights[$i] > 0) {
                $share = $i === $last ? round($left, 2) : round($tax * $weights[$i] / $expected, 2);
                $left -= $share;
            }

            if ($inter) {
                $line['iamt'] = $share;
            } else {
                $line['camt'] = round($share / 2, 2);
                $line['samt'] = round($share - $line['camt'], 2);
            }
        }
        unset($line);

        return $issues;
    }

    /** @return list<string> */
    private function lineIssues(array $lines): array
    {
        $issues = [];
        $badRates = array_unique(array_filter(array_column($lines, 'rt'), fn ($rt) => ! in_array((float) $rt, self::VALID_RATES, false)));

        if ($badRates !== []) {
            $issues[] = 'GST rate ' . implode(', ', array_map(fn ($r) => rtrim(rtrim(number_format($r, 2), '0'), '.') . '%', $badRates)) . ' is not a GST rate.';
        }

        $noHsn = array_filter($lines, fn ($line) => $line['hsn'] === null && $line['txval'] > 0);
        if ($noHsn !== []) {
            $names = array_slice(array_unique(array_map(fn ($line) => $line['desc'] ?: 'item', $noHsn)), 0, 3);
            $issues[] = 'HSN/SAC missing for ' . implode(', ', $names) . '.';
        }

        $badHsn = array_filter($lines, fn ($line) => $line['hsn'] !== null && ! preg_match('/^\d{4}(\d{2})?(\d{2})?$/', $line['hsn']));
        if ($badHsn !== []) {
            $issues[] = 'HSN/SAC ' . implode(', ', array_unique(array_column($badHsn, 'hsn'))) . ' should be 4, 6 or 8 digits.';
        }

        return $issues;
    }

    private function placeOfSupply($customer, ?string $gstin, bool $inter, array $seller): ?string
    {
        if ($gstin !== null) {
            return GstStates::fromGstin($gstin);
        }

        $state = GstStates::findInText($customer?->shipping_address) ?? GstStates::findInText($customer?->billing_address);

        return $state ?? ($inter ? null : $seller['state']);
    }

    /** @return list<string> */
    private function posIssues(?string $pos, bool $inter, array $seller): array
    {
        if ($pos === null) {
            return ["Place of supply is unknown — add the customer's state to their address."];
        }

        if ($seller['state'] !== null && ($pos === $seller['state']) === $inter) {
            return [$inter
                ? 'IGST charged, but the place of supply (' . GstStates::name($pos) . ') is your own state.'
                : 'CGST/SGST charged, but the place of supply is ' . GstStates::name($pos) . '.'];
        }

        return [];
    }

    /** @return list<string> */
    private function numberIssues(string $number, string $label): array
    {
        return preg_match('#^[A-Za-z0-9/\-]{1,16}$#', $number)
            ? []
            : ["{$label} number \"{$number}\" must be at most 16 letters, digits, / or -."];
    }

    private function nearestRate(float $rate): float
    {
        $best = 0;
        foreach (self::VALID_RATES as $valid) {
            if (abs($valid - $rate) < abs($best - $rate)) {
                $best = $valid;
            }
        }

        return (float) $best;
    }

    /** Lines grouped by rate. */
    private function rateItems(array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            $key = (string) $line['rt'];
            $items[$key] ??= ['rt' => $line['rt'], 'txval' => 0.0, 'iamt' => 0.0, 'camt' => 0.0, 'samt' => 0.0];
            foreach (['txval', 'iamt', 'camt', 'samt'] as $field) {
                $items[$key][$field] = round($items[$key][$field] + $line[$field], 2);
            }
        }

        ksort($items, SORT_NUMERIC);

        return array_values($items);
    }

    private function jsonItems(array $items, bool $inter): array
    {
        $out = [];

        foreach (array_values($items) as $n => $item) {
            $detail = ['txval' => $item['txval'], 'rt' => $item['rt']];
            $detail += $inter ? ['iamt' => $item['iamt']] : ['camt' => $item['camt'], 'samt' => $item['samt']];
            $detail['csamt'] = 0;
            $out[] = ['num' => $n + 1, 'itm_det' => $detail];
        }

        return $out;
    }

    private function document(array $data): array
    {
        $items = $data['items'];
        $iamt = round(array_sum(array_column($items, 'iamt')), 2);
        $camt = round(array_sum(array_column($items, 'camt')), 2);
        $samt = round(array_sum(array_column($items, 'samt')), 2);

        return $data + [
            'live' => true,
            'taxable' => round(array_sum(array_column($items, 'txval')), 2),
            'iamt' => $iamt,
            'camt' => $camt,
            'samt' => $samt,
            'tax' => round($iamt + $camt + $samt, 2),
            'against' => $data['against'] ?? null,
            'payload' => null,
            'tracked' => null,
            'state' => self::STATE_PENDING,
        ];
    }

    private function withFingerprint(array $document): array
    {
        $document['fingerprint'] = sha1(json_encode([$document['section'], $document['payload'], $document['lines']]));

        return $document;
    }

    private function stateOf(array $document): string
    {
        $record = $document['tracked'];

        if ($record?->status === GstReturnDocument::STATUS_DELETED) {
            return self::STATE_REMOVED;
        }

        if ($record?->status === GstReturnDocument::STATUS_DELETE_REQUESTED) {
            return self::STATE_DELETE_REQUESTED;
        }

        if ($document['issues'] !== []) {
            return self::STATE_EXCEPTION;
        }

        if ($record === null) {
            return self::STATE_PENDING;
        }

        return $record->fingerprint === $document['fingerprint'] ? self::STATE_UPLOADED : self::STATE_MODIFIED;
    }

    /** A document that is gone (cancelled / deleted) rebuilt from what was reported. */
    private function documentFromRecord(GstReturnDocument $record, string $state): array
    {
        $payload = $record->payload ?? [];
        $entry = $payload['entry'] ?? [];
        $items = $payload['items'] ?? array_map(fn ($itm) => [
            'rt' => (float) ($itm['itm_det']['rt'] ?? 0),
            'txval' => (float) ($itm['itm_det']['txval'] ?? 0),
            'iamt' => (float) ($itm['itm_det']['iamt'] ?? 0),
            'camt' => (float) ($itm['itm_det']['camt'] ?? 0),
            'samt' => (float) ($itm['itm_det']['samt'] ?? 0),
        ], $entry['itms'] ?? []);
        $date = $entry['idt'] ?? $entry['nt_dt'] ?? null;

        $document = $this->document([
            'key' => $record->key(),
            'document_type' => $record->document_type,
            'document_id' => $record->document_id,
            'vch_type' => $record->document_type === GstReturnDocument::TYPE_CREDIT_NOTE ? 'Credit Note' : 'Sales',
            'number' => (string) $record->document_number,
            'date' => $date ? Carbon::createFromFormat('d-m-Y', $date)->startOfDay() : $record->uploaded_at,
            'party' => $payload['party'] ?? '—',
            'ctin' => $payload['ctin'] ?? null,
            'pos' => $payload['pos'] ?? ($entry['pos'] ?? null),
            'inter' => false,
            'section' => $record->section,
            'value' => (float) ($entry['val'] ?? array_sum(array_column($items, 'txval'))),
            'items' => $items,
            'lines' => [],
            'issues' => [],
            'sign' => $record->document_type === GstReturnDocument::TYPE_CREDIT_NOTE ? -1 : 1,
            'url' => null,
        ]);

        $document['live'] = false;
        $document['payload'] = $payload;
        $document['tracked'] = $record;
        $document['state'] = $state;
        $document['fingerprint'] = $record->fingerprint;
        $document['gone_reason'] = 'Cancelled or deleted after upload';

        return $document;
    }

    // ------------------------------------------------------------ summaries

    private function b2csSummary(array $documents): array
    {
        $rows = [];

        foreach ($documents as $doc) {
            if ($doc['section'] !== 'b2cs') {
                continue;
            }

            foreach ($doc['items'] as $item) {
                $key = $doc['pos'] . '|' . $item['rt'] . '|' . ($doc['inter'] ? 'INTER' : 'INTRA');
                $rows[$key] ??= [
                    'sply_ty' => $doc['inter'] ? 'INTER' : 'INTRA',
                    'pos' => $doc['pos'],
                    'typ' => 'OE',
                    'rt' => $item['rt'],
                    'txval' => 0.0, 'iamt' => 0.0, 'camt' => 0.0, 'samt' => 0.0,
                ];

                foreach (['txval', 'iamt', 'camt', 'samt'] as $field) {
                    $rows[$key][$field] = round($rows[$key][$field] + $doc['sign'] * $item[$field], 2);
                }
            }
        }

        ksort($rows);

        return array_values($rows);
    }

    /** Table 12, split into B2B and B2C as the portal requires. */
    private function hsnSummary(array $documents): array
    {
        $groups = ['b2b' => [], 'b2c' => []];

        foreach ($documents as $doc) {
            $bucket = in_array($doc['section'], ['b2b', 'cdnr'], true) ? 'b2b' : 'b2c';

            foreach ($doc['lines'] as $line) {
                if ($line['hsn'] === null) {
                    continue;
                }

                $key = $line['hsn'] . '|' . $line['rt'] . '|' . $line['uqc'];
                $groups[$bucket][$key] ??= [
                    'hsn_sc' => $line['hsn'], 'desc' => $line['desc'], 'uqc' => $line['uqc'], 'rt' => $line['rt'],
                    'qty' => 0.0, 'txval' => 0.0, 'iamt' => 0.0, 'camt' => 0.0, 'samt' => 0.0,
                ];

                foreach (['qty', 'txval', 'iamt', 'camt', 'samt'] as $field) {
                    $groups[$bucket][$key][$field] = round($groups[$bucket][$key][$field] + $doc['sign'] * $line[$field], $field === 'qty' ? 3 : 2);
                }
            }
        }

        foreach ($groups as $bucket => $rows) {
            ksort($rows);
            $groups[$bucket] = array_values($rows);
        }

        return $groups;
    }

    /** Table 13: number ranges of documents issued in the period, with cancellations. */
    private function documentsIssued(Collection $invoices, Collection $returns): array
    {
        $rows = [];
        $issued = $invoices->reject(fn ($invoice) => $invoice->status === 'Draft');

        foreach ([
            [1, 'Invoices for outward supply', $issued, 'invoice_number', fn ($invoice) => $invoice->status === 'Cancelled'],
            [5, 'Credit Note', $returns->whereIn('status', ['Completed', 'Cancelled']), 'return_number', fn ($return) => $return->status === 'Cancelled'],
        ] as [$num, $label, $documents, $field, $isCancelled]) {
            if ($documents->isEmpty()) {
                continue;
            }

            $numbers = $documents->pluck($field)->map(fn ($n) => (string) $n)->sort(SORT_NATURAL)->values();
            $cancelled = $documents->filter($isCancelled)->count();

            $rows[] = [
                'doc_num' => $num,
                'doc_typ' => $label,
                'from' => $numbers->first(),
                'to' => $numbers->last(),
                'totnum' => $documents->count(),
                'cancel' => $cancelled,
                'net_issue' => $documents->count() - $cancelled,
            ];
        }

        return $rows;
    }

    private function sectionTotals(array $documents, array $b2cs): array
    {
        $sections = [];

        foreach (array_keys(self::SECTION_LABELS) as $section) {
            $sections[$section] = ['label' => self::SECTION_LABELS[$section], 'count' => 0, 'taxable' => 0.0, 'iamt' => 0.0, 'camt' => 0.0, 'samt' => 0.0, 'value' => 0.0];
        }

        foreach ($documents as $doc) {
            $row = &$sections[$doc['section']];
            $sign = $doc['section'] === 'b2cs' ? $doc['sign'] : 1;
            $row['count']++;
            foreach (['taxable', 'iamt', 'camt', 'samt', 'value'] as $field) {
                $row[$field] = round($row[$field] + $sign * $doc[$field], 2);
            }
            unset($row);
        }

        return $sections;
    }

    /** @return list<string> */
    private function blockers(array $seller, ?string $fp): array
    {
        $blockers = [];

        if (! GstStates::isValidGstin($seller['gstin'])) {
            $blockers[] = 'Your GSTIN is missing or invalid. Set it in Platform → GST & E-Invoice Setup.';
        }

        if ($fp === null) {
            $blockers[] = 'Choose a full month (or a full quarter) as the return period to export.';
        }

        return $blockers;
    }

    // ------------------------------------------------------------ export

    private function selectPending(array $built, ?array $keys): array
    {
        $pending = $built['groups']['pending'];

        if ($keys === null) {
            return $pending;
        }

        return array_intersect_key($pending, array_flip($keys));
    }

    private function json(array $built, array $selected): array
    {
        $b2b = $b2cl = $cdnr = [];
        $cdnur = [];

        $add = function (string $section, array $payload, bool $delete) use (&$b2b, &$b2cl, &$cdnr, &$cdnur) {
            $entry = $payload['entry'] ?? null;
            if ($entry === null) {
                return; // B2CS: covered by the summary
            }

            if ($delete) {
                $entry['flag'] = 'D';
            }

            match ($section) {
                'b2b' => $b2b[$payload['ctin']][] = $entry,
                'b2cl' => $b2cl[$payload['pos']][] = $entry,
                'cdnr' => $cdnr[$payload['ctin']][] = $entry,
                'cdnur' => $cdnur[] = $entry,
                default => null,
            };
        };

        foreach ($selected as $doc) {
            $delete = ! $doc['live'] || $doc['state'] === self::STATE_DELETE_REQUESTED;
            $payload = $delete ? ($doc['tracked']?->payload ?? $doc['payload']) : $doc['payload'];
            $section = $delete ? ($doc['tracked']?->section ?? $doc['section']) : $doc['section'];

            // Moved to another table since it was uploaded: remove it from the old one.
            if (! $delete && $doc['state'] === self::STATE_MODIFIED && $doc['tracked']->section !== $doc['section']) {
                $add($doc['tracked']->section, $doc['tracked']->payload ?? [], true);
            }

            $add($section, $payload, $delete);
        }

        $json = [
            'gstin' => $built['seller']['gstin'],
            'fp' => $built['fp'],
            'version' => self::JSON_VERSION,
            'hash' => 'hash',
        ];

        if ($b2b !== []) {
            $json['b2b'] = array_map(fn ($ctin, $inv) => ['ctin' => $ctin, 'inv' => $inv], array_keys($b2b), $b2b);
        }
        if ($b2cl !== []) {
            $json['b2cl'] = array_map(fn ($pos, $inv) => ['pos' => (string) $pos, 'inv' => $inv], array_keys($b2cl), $b2cl);
        }
        if ($built['b2cs'] !== []) {
            $json['b2cs'] = array_map(function ($row) {
                $out = ['sply_ty' => $row['sply_ty'], 'pos' => $row['pos'], 'typ' => $row['typ'], 'txval' => $row['txval'], 'rt' => $row['rt']];
                $out += $row['sply_ty'] === 'INTER' ? ['iamt' => $row['iamt']] : ['camt' => $row['camt'], 'samt' => $row['samt']];

                return $out + ['csamt' => 0];
            }, $built['b2cs']);
        }
        if ($cdnr !== []) {
            $json['cdnr'] = array_map(fn ($ctin, $nt) => ['ctin' => $ctin, 'nt' => $nt], array_keys($cdnr), $cdnr);
        }
        if ($cdnur !== []) {
            $json['cdnur'] = $cdnur;
        }

        $hsn = [];
        foreach (['b2b' => 'hsn_b2b', 'b2c' => 'hsn_b2c'] as $bucket => $jsonKey) {
            if ($built['hsn'][$bucket] !== []) {
                $hsn[$jsonKey] = array_map(fn ($row, $n) => [
                    'num' => $n + 1,
                    'hsn_sc' => $row['hsn_sc'],
                    'desc' => $row['desc'],
                    'uqc' => $row['uqc'],
                    'qty' => $row['qty'],
                    'rt' => $row['rt'],
                    'txval' => $row['txval'],
                    'iamt' => $row['iamt'],
                    'camt' => $row['camt'],
                    'samt' => $row['samt'],
                    'csamt' => 0,
                ], $built['hsn'][$bucket], array_keys($built['hsn'][$bucket]));
            }
        }
        if ($hsn !== []) {
            $json['hsn'] = $hsn;
        }

        if ($built['doc_issue'] !== []) {
            $json['doc_issue'] = ['doc_det' => array_map(fn ($row) => [
                'doc_num' => $row['doc_num'],
                'doc_typ' => $row['doc_typ'],
                'docs' => [[
                    'num' => 1, 'from' => $row['from'], 'to' => $row['to'],
                    'totnum' => $row['totnum'], 'cancel' => $row['cancel'], 'net_issue' => $row['net_issue'],
                ]],
            ], $built['doc_issue'])];
        }

        return $json;
    }

    private function record(array $built, array $selected, GstReturnFiling $filing, ?int $userId): void
    {
        foreach ($selected as $doc) {
            $attributes = [
                'tenant_id' => require_tenant_id(),
                'return_type' => self::RETURN_TYPE,
                'document_type' => $doc['document_type'],
                'document_id' => $doc['document_id'],
            ];

            if (! $doc['live'] || $doc['state'] === self::STATE_DELETE_REQUESTED) {
                GstReturnDocument::query()->where($attributes)->update([
                    'status' => GstReturnDocument::STATUS_DELETED,
                    'gst_return_filing_id' => $filing->id,
                    'updated_at' => now(),
                ]);

                continue;
            }

            GstReturnDocument::query()->updateOrCreate($attributes, [
                'company_id' => current_company_id(),
                'branch_id' => current_branch_id(),
                'return_period' => $built['period_key'],
                'document_number' => $doc['number'],
                'section' => $doc['section'],
                'status' => GstReturnDocument::STATUS_UPLOADED,
                'fingerprint' => $doc['fingerprint'],
                'payload' => $doc['payload'] + ['party' => $doc['party'], 'pos' => $doc['pos']],
                'gst_return_filing_id' => $filing->id,
                'uploaded_at' => now(),
                'uploaded_by' => $userId,
            ]);
        }
    }

    private function totalsOf(array $documents): array
    {
        return [
            'taxable' => round(array_sum(array_map(fn ($doc) => $doc['sign'] * $doc['taxable'], $documents)), 2),
            'tax' => round(array_sum(array_map(fn ($doc) => $doc['sign'] * $doc['tax'], $documents)), 2),
            'value' => round(array_sum(array_map(fn ($doc) => $doc['sign'] * $doc['value'], $documents)), 2),
        ];
    }

    private function fileName(array $built): string
    {
        return 'GSTR1_' . $built['seller']['gstin'] . '_' . $built['fp'] . '_' . now()->format('YmdHis') . '.json';
    }

    /** @return array<string, GstReturnDocument> */
    private function trackedFor(array $keys, string $periodKey): array
    {
        $byKey = [];
        $ids = ['invoice' => [], 'credit_note' => []];

        foreach ($keys as $key) {
            [$type, $id] = explode(':', $key);
            $ids[$type][] = (int) $id;
        }

        GstReturnDocument::query()
            ->where('return_type', self::RETURN_TYPE)
            ->where(function ($query) use ($ids, $periodKey) {
                $query->where('return_period', $periodKey);
                foreach ($ids as $type => $list) {
                    if ($list !== []) {
                        $query->orWhere(fn ($q) => $q->where('document_type', $type)->whereIn('document_id', $list));
                    }
                }
            })
            ->get()
            ->each(function ($record) use (&$byKey) {
                $byKey[$record->key()] = $record;
            });

        return $byKey;
    }

    private function trackedByKeys(array $keys): Collection
    {
        $pairs = [];
        foreach ($keys as $key) {
            if (preg_match('/^(invoice|credit_note):(\d+)$/', (string) $key, $m)) {
                $pairs[] = [$m[1], (int) $m[2]];
            }
        }

        if ($pairs === []) {
            return collect();
        }

        return GstReturnDocument::query()
            ->where('return_type', self::RETURN_TYPE)
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as [$type, $id]) {
                    $query->orWhere(fn ($q) => $q->where('document_type', $type)->where('document_id', $id));
                }
            })
            ->get();
    }
}
