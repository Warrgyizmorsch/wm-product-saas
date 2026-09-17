<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\AccountingAuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns the data an accounting report passes to its view into titled tables
 * for PDF and Excel export. The report's own controller still does every
 * calculation and permission check; this only lays the result out. Amounts
 * stay numeric so Excel can total them.
 *
 * build() returns: title, meta (label/value lines), sections (title, header,
 * rows, footer) and page orientation.
 */
final class ReportTables
{
    public const TITLES = [
        'day-book' => 'Day Book',
        'trial-balance' => 'Trial Balance',
        'general-ledger' => 'General Ledger',
        'party-ledger' => 'Party Ledger',
        'balance-sheet' => 'Balance Sheet',
        'profit-loss' => 'Profit & Loss',
        'ar-aging' => 'AR Aging',
        'ap-aging' => 'AP Aging',
        'cash-flow' => 'Cash Flow',
        'gst-summary' => 'GST Summary',
        'gstr1' => 'GSTR-1',
        'gstr3b' => 'GSTR-3B',
        'audit-trail' => 'Audit Trail',
        'budget-vs-actual' => 'Budget vs Actual',
        'vouchers-by-staff' => 'Vouchers by Staff',
    ];

    private const LANDSCAPE = ['day-book', 'general-ledger', 'party-ledger', 'ar-aging', 'ap-aging', 'gstr1', 'audit-trail', 'budget-vs-actual', 'vouchers-by-staff'];

    /** The audit trail is paginated on screen; an export takes up to this many rows. */
    private const MAX_AUDIT_ROWS = 5000;

    private const BUCKETS = ['not_due' => 'Not due', '0_30' => '1–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => '90+ days'];

    public function __construct(
        private readonly AccountingAuditLogService $auditLogs,
    ) {
    }

    /**
     * @param array<string, mixed> $data the view data of the report's index()
     * @return array{title: string, meta: list<array{0: string, 1: string}>, sections: list<array{title: string, header: list<string>, rows: list<list<mixed>>, footer: list<list<mixed>>}>, orientation: string}
     */
    public function build(string $report, array $data): array
    {
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $report))));
        $table = $this->{$method}($data);

        $meta = $table['meta'];
        if (company()) {
            $meta[] = ['Amounts', 'In '.company_currency()['code']];
        }
        $meta[] = ['Generated', now()->format('d M Y H:i')];

        return [
            'title' => self::TITLES[$report],
            'meta' => $meta,
            'sections' => $table['sections'],
            'orientation' => in_array($report, self::LANDSCAPE, true) ? 'landscape' : 'portrait',
        ];
    }

    private function dayBook(array $d): array
    {
        $vouchers = [];
        $lines = [];

        foreach ($d['journals'] as $journal) {
            $type = $journal->voucher_type ? ucwords(str_replace('_', ' ', $journal->voucher_type)) : ucfirst((string) $journal->source);
            $vouchers[] = [$journal->journal_number, $type, (string) ($journal->voucherDetail?->party_name ?? $journal->memo), ucfirst($journal->status), (float) $journal->total_debit];

            foreach ($journal->entries as $entry) {
                $lines[] = [$journal->journal_number, trim(($entry->account?->code ?? '').' '.($entry->account?->name ?? '')), (string) $entry->description, (float) $entry->debit, (float) $entry->credit];
            }
        }

        return [
            'meta' => [['Date', $d['date']->format('d M Y')]],
            'sections' => [
                $this->section('Vouchers & journals', ['Voucher #', 'Type', 'Memo / Party', 'Status', 'Amount'], $vouchers, [['Total', '', '', '', (float) $d['totalDebit']]]),
                $this->section('Entry lines', ['Voucher #', 'Account', 'Description', 'Debit', 'Credit'], $lines, [['Total', '', '', (float) $d['totalDebit'], (float) $d['totalCredit']]]),
            ],
        ];
    }

    private function trialBalance(array $d): array
    {
        $meta = $this->periodMeta($d['period']);

        if ($d['costCenterId']) {
            $costCenter = $d['costCenters']->firstWhere('id', $d['costCenterId']);
            $meta[] = ['Cost center', $costCenter ? "{$costCenter->code} — {$costCenter->name}" : '#'.$d['costCenterId']];
        }

        if (! $d['period']) {
            return ['meta' => $meta, 'sections' => [$this->note('No accounting period exists yet.')]];
        }

        $rows = $d['rows']->map(fn (array $row) => [
            $row['account']?->code ?? '', $row['account']?->name ?? 'Unknown account', $row['debit'], $row['credit'], $row['balance'],
        ])->all();

        return ['meta' => $meta, 'sections' => [
            $this->section('Trial balance', ['Code', 'Account', 'Debit', 'Credit', 'Balance'], $rows, [['Total', '', (float) $d['totals']['debit'], (float) $d['totals']['credit'], '']]),
        ]];
    }

    private function generalLedger(array $d): array
    {
        $meta = $this->periodMeta($d['period']);

        if (! $d['period'] || ! $d['account']) {
            return ['meta' => $meta, 'sections' => [$this->note('Select an account and an accounting period.')]];
        }

        $meta[] = ['Account', "{$d['account']->code} — {$d['account']->name}"];

        return ['meta' => $meta, 'sections' => [
            $this->ledgerSection($d['rows'], (float) $d['openingBalance'], (float) $d['closingBalance']),
        ]];
    }

    private function partyLedger(array $d): array
    {
        $meta = [['Period', $d['from']->format('d M Y').' – '.$d['to']->format('d M Y')]];

        if (! $d['party'] || ! $d['ledger']) {
            return ['meta' => $meta, 'sections' => [$this->note('Select a customer or vendor.')]];
        }

        array_unshift($meta, ['Party', $d['party']->name.' ('.ucfirst((string) $d['partyType']).')']);

        return ['meta' => $meta, 'sections' => [
            $this->ledgerSection($d['ledger']['entries'], (float) $d['ledger']['opening'], (float) $d['ledger']['closing']),
        ]];
    }

    private function balanceSheet(array $d): array
    {
        $meta = $this->periodMeta($d['period']);

        if (! $d['period']) {
            return ['meta' => $meta, 'sections' => [$this->note('No accounting period exists yet.')]];
        }

        $accounts = fn (Collection $rows) => $rows->map(fn (array $row) => [$row['account']->code, $row['account']->name, (float) $row['balance']])->all();
        $sections = $d['sections'];
        $totals = $d['totals'];
        $header = ['Code', 'Account', 'Balance'];
        $equity = $accounts($sections['equity']);
        $equity[] = ['', 'Net income for the period', (float) $d['netIncome']];

        return ['meta' => $meta, 'sections' => [
            $this->section('Current assets', $header, $accounts($sections['asset']['current']), [['', 'Total current assets', (float) $totals['asset_current']]]),
            $this->section('Non-current assets', $header, $accounts($sections['asset']['non_current']), [['', 'Total non-current assets', (float) $totals['asset_non_current']]]),
            $this->section('Current liabilities', $header, $accounts($sections['liability']['current']), [['', 'Total current liabilities', (float) $totals['liability_current']]]),
            $this->section('Non-current liabilities', $header, $accounts($sections['liability']['non_current']), [['', 'Total non-current liabilities', (float) $totals['liability_non_current']]]),
            $this->section('Equity', $header, $equity, [['', 'Total equity', (float) $totals['equity']]]),
            $this->section('Summary', ['Line', 'Amount'], [
                ['Total assets', (float) $totals['asset']],
                ['Total liabilities', (float) $totals['liability']],
                ['Total equity', (float) $totals['equity']],
                ['Liabilities + equity', (float) ($totals['liability'] + $totals['equity'])],
                ['Balanced', $d['isBalanced'] ? 'Yes' : 'No'],
            ]),
        ]];
    }

    private function profitLoss(array $d): array
    {
        $meta = $this->periodMeta($d['period']);

        if (! $d['period']) {
            return ['meta' => $meta, 'sections' => [$this->note('No accounting period exists yet.')]];
        }

        $groups = [
            ChartOfAccount::SUBTYPE_DIRECT_INCOME => 'Direct income',
            ChartOfAccount::SUBTYPE_COGS => 'Cost of goods sold',
            ChartOfAccount::SUBTYPE_OPERATING_EXPENSE => 'Operating expenses',
            ChartOfAccount::SUBTYPE_INDIRECT_INCOME => 'Indirect income',
            ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE => 'Indirect expenses',
        ];

        $sections = [];
        foreach ($groups as $key => $label) {
            $rows = $d['sections'][$key]->map(fn (array $row) => [$row['account']->code, $row['account']->name, (float) $row['amount']])->all();
            $sections[] = $this->section($label, ['Code', 'Account', 'Amount'], $rows, [['', 'Total '.strtolower($label), (float) $d['totals'][$key]]]);
        }

        $sections[] = $this->section('Summary', ['Line', 'Amount'], [
            ['Direct income', (float) $d['directIncome']],
            ['Cost of goods sold', -(float) $d['cogs']],
            ['Gross profit', (float) $d['grossProfit']],
            ['Operating expenses', -(float) $d['operatingExpense']],
            ['Operating profit', (float) $d['operatingProfit']],
            ['Indirect income', (float) $d['indirectIncome']],
            ['Indirect expenses', -(float) $d['indirectExpense']],
        ], [['Net profit', (float) $d['netProfit']]]);

        return ['meta' => $meta, 'sections' => $sections];
    }

    private function arAging(array $d): array
    {
        return $this->aging($d, 'customers', 'invoices', 'Customer', 'invoice', 'invoice_number', 'Invoice #');
    }

    private function apAging(array $d): array
    {
        return $this->aging($d, 'vendors', 'bills', 'Vendor', 'bill', 'bill_number', 'Bill #');
    }

    private function cashFlow(array $d): array
    {
        $meta = $this->periodMeta($d['period']);

        if (! $d['period']) {
            return ['meta' => $meta, 'sections' => [$this->note('No accounting period exists yet.')]];
        }

        $rows = fn (Collection $items) => $items->map(fn (array $row) => [$row['account']->code, $row['account']->name, (float) $row['amount']])->all();
        $header = ['Code', 'Account', 'Amount'];

        return ['meta' => $meta, 'sections' => [
            $this->section('Operating activities', $header, array_merge([['', 'Net profit', (float) $d['netProfit']]], $rows($d['operating'])), [['', 'Net cash from operating activities', (float) $d['operatingTotal']]]),
            $this->section('Investing activities', $header, $rows($d['investing']), [['', 'Net cash from investing activities', (float) $d['investingTotal']]]),
            $this->section('Financing activities', $header, $rows($d['financing']), [['', 'Net cash from financing activities', (float) $d['financingTotal']]]),
            $this->section('Summary', ['Line', 'Amount'], [
                ['Net change in cash', (float) $d['netChangeInCash']],
                ['Opening cash & bank', (float) $d['openingCash']],
                ['Closing cash & bank', (float) $d['closingCash']],
                ['Reconciles to cash & bank', $d['isReconciled'] ? 'Yes' : 'No'],
            ]),
        ]];
    }

    private function gstSummary(array $d): array
    {
        $line = fn (string $label, array $values) => [$label, (float) $values['taxable'], (float) $values['cgst'], (float) $values['sgst'], (float) $values['igst']];

        return ['meta' => $this->gstMeta($d), 'sections' => [
            $this->section('GST position', ['Line', 'Taxable value', 'CGST', 'SGST', 'IGST'], [
                $line('Output tax (sales less returns)', $d['output']),
                $line('Input tax credit (purchases less returns)', $d['input']),
                $line('Reverse charge liability', $d['rcm']),
                $line('Reverse charge ITC', $d['rcmItc']),
            ], [['Net GST payable', '', (float) $d['payable']['cgst'], (float) $d['payable']['sgst'], (float) $d['payable']['igst']]]),
            $this->section('Net payable', ['Tax', 'Amount'], [
                ['CGST', (float) $d['payable']['cgst']],
                ['SGST', (float) $d['payable']['sgst']],
                ['IGST', (float) $d['payable']['igst']],
            ], [['Total', (float) $d['payable']['total']]]),
        ]];
    }

    private function gstr1(array $d): array
    {
        $invoice = fn ($invoice, bool $withGstin) => array_values(array_filter([
            $invoice->invoice_number,
            $this->date($invoice->invoice_date),
            $invoice->customer?->name ?? '',
            $withGstin ? ($invoice->customer?->gstin ?? '') : null,
            (float) $invoice->subtotal,
            (float) $invoice->cgst_amount,
            (float) $invoice->sgst_amount,
            (float) $invoice->igst_amount,
        ], fn ($value) => $value !== null));

        $return = fn (array $row) => [
            $row['return']->return_number, $this->date($row['return']->return_date), $row['return']->customer?->name ?? '',
            (float) $row['split']['taxable'], (float) $row['split']['cgst'], (float) $row['split']['sgst'], (float) $row['split']['igst'],
        ];

        $total = fn (array $totals, int $blanks) => [array_merge(['Total ('.$totals['count'].')'], array_fill(0, $blanks, ''), [
            (float) $totals['taxable'], (float) $totals['cgst'], (float) $totals['sgst'], (float) $totals['igst'],
        ])];

        $taxColumns = ['Taxable value', 'CGST', 'SGST', 'IGST'];

        return ['meta' => $this->gstMeta($d), 'sections' => [
            $this->section('B2B invoices', array_merge(['Invoice #', 'Date', 'Customer', 'GSTIN'], $taxColumns), $d['b2b']->map(fn ($i) => $invoice($i, true))->all(), $total($d['b2bTotals'], 3)),
            $this->section('B2C invoices', array_merge(['Invoice #', 'Date', 'Customer'], $taxColumns), $d['b2c']->map(fn ($i) => $invoice($i, false))->all(), $total($d['b2cTotals'], 2)),
            $this->section('B2B credit notes', array_merge(['Return #', 'Date', 'Customer'], $taxColumns), $d['b2bReturns']->map($return)->all(), $total($d['b2bReturnTotals'], 2)),
            $this->section('B2C credit notes', array_merge(['Return #', 'Date', 'Customer'], $taxColumns), $d['b2cReturns']->map($return)->all(), $total($d['b2cReturnTotals'], 2)),
        ]];
    }

    private function gstr3b(array $d): array
    {
        $line = fn (string $label, array $values) => [$label, (float) $values['taxable'], (float) $values['cgst'], (float) $values['sgst'], (float) $values['igst']];
        $header = ['Nature of supplies', 'Taxable value', 'CGST', 'SGST', 'IGST'];

        return ['meta' => $this->gstMeta($d), 'sections' => [
            $this->section('3.1 Outward and reverse-charge supplies', $header, [
                $line('(a) Outward taxable supplies (net of credit notes)', $d['outward']),
                $line('(d) Inward supplies liable to reverse charge', $d['inwardRcm']),
            ]),
            $this->section('4 Eligible ITC', $header, [
                $line('(A)(3) Inward supplies liable to reverse charge', $d['rcmItc']),
                $line('(A)(5) All other ITC', $d['itc']),
            ]),
            $this->section('Net tax payable', ['Tax', 'Amount'], [
                ['CGST', (float) $d['netPayable']['cgst']],
                ['SGST', (float) $d['netPayable']['sgst']],
                ['IGST', (float) $d['netPayable']['igst']],
            ], [['Total', (float) $d['netPayable']['total']]]),
        ]];
    }

    private function auditTrail(array $d): array
    {
        $filters = $d['filters'];
        $logs = $this->auditLogs->paginate($filters, self::MAX_AUDIT_ROWS);

        $meta = [];
        foreach (['event_type' => 'Event', 'from' => 'From', 'to' => 'To', 'search' => 'Search'] as $key => $label) {
            if (! empty($filters[$key])) {
                $meta[] = [$label, (string) $filters[$key]];
            }
        }
        if ($logs->total() > self::MAX_AUDIT_ROWS) {
            $meta[] = ['Rows', 'First '.self::MAX_AUDIT_ROWS.' of '.$logs->total().' — narrow the filters to export the rest'];
        }

        $rows = $logs->getCollection()->map(fn ($log) => [
            $log->created_at?->format('d M Y H:i') ?? '', $log->event_type, $log->title, (string) $log->description, $log->triggeredBy?->name ?? 'System',
        ])->all();

        return ['meta' => $meta, 'sections' => [
            $this->section('Audit trail', ['Date', 'Event', 'Title', 'Description', 'By'], $rows),
        ]];
    }

    private function budgetVsActual(array $d): array
    {
        if (! $d['budget']) {
            return ['meta' => [], 'sections' => [$this->note('No approved budget to report on.')]];
        }

        $summary = $d['summary'];
        $meta = [
            ['Budget', $d['budget']->name],
            ['Fiscal year', $d['budget']->fiscalYear?->name ?? ''],
            ['On track / near limit / over', "{$summary['ok']} / {$summary['warning']} / {$summary['over']}"],
        ];

        $rows = array_map(fn (array $row) => [
            trim(($row['line']->account?->code ?? '').' '.($row['line']->account?->name ?? '')),
            $row['line']->costCenter?->name ?? '',
            (float) $row['budgeted'],
            $row['has_actuals'] ? (float) $row['actual'] : 'n/a',
            $row['has_actuals'] ? (float) $row['variance'] : 'n/a',
            $row['has_actuals'] ? number_format($row['percent_used'] * 100, 1).'%' : 'n/a',
            $row['has_actuals'] ? ucfirst($row['status']) : 'No dimension data',
        ], $d['rows']);

        return ['meta' => $meta, 'sections' => [
            $this->section('Budget vs actual', ['Account', 'Cost center', 'Budgeted', 'Actual', 'Variance', 'Used', 'Status'], $rows),
        ]];
    }

    private function vouchersByStaff(array $d): array
    {
        $types = array_keys($d['documentTypes']);
        $line = fn (string $name, array $row) => array_merge([$name], array_map(fn (string $type) => $row['counts'][$type], $types), [
            $row['documents'], (float) $row['amount'], $row['reversed'], $row['reconciliations'],
        ]);

        return [
            'meta' => [['Period', $d['from']->format('d M Y').' – '.$d['to']->format('d M Y')]],
            'sections' => [
                $this->section(
                    'Documents posted per person',
                    array_merge(['Staff'], array_values($d['documentTypes']), ['Total', 'Amount', 'Reversed', 'Bank reconciliations']),
                    array_map(fn (array $row) => $line($row['name'], $row), $d['rows']),
                    $d['rows'] === [] ? [] : [$line('Total', $d['totals'])],
                ),
            ],
        ];
    }

    private function aging(array $d, string $partiesKey, string $documentsKey, string $partyLabel, string $documentKey, string $numberField, string $numberLabel): array
    {
        $bucketKeys = array_keys(self::BUCKETS);
        $parties = [];
        $documents = [];

        foreach ($d[$partiesKey] as $party) {
            $parties[] = array_merge([$party['name']], array_map(fn (string $key) => (float) $party['buckets'][$key], $bucketKeys), [(float) $party['total']]);

            foreach ($party[$documentsKey] as $item) {
                $documents[] = [
                    $party['name'],
                    $item[$documentKey]->{$numberField},
                    $item['due_date']?->format('d M Y') ?? '',
                    (int) $item['days_overdue'],
                    self::BUCKETS[$item['bucket']] ?? $item['bucket'],
                    (float) $item['balance'],
                ];
            }
        }

        return [
            'meta' => [['As of', $d['asOf']->format('d M Y')]],
            'sections' => [
                $this->section('Summary by '.strtolower($partyLabel), array_merge([$partyLabel], array_values(self::BUCKETS), ['Total']), $parties,
                    [array_merge(['Total'], array_map(fn (string $key) => (float) $d['buckets'][$key], $bucketKeys), [(float) $d['grandTotal']])]),
                $this->section('Open documents', [$partyLabel, $numberLabel, 'Due date', 'Days overdue', 'Bucket', 'Balance'], $documents),
            ],
        ];
    }

    private function ledgerSection(iterable $rows, float $opening, float $closing): array
    {
        $lines = [['', '', 'Opening balance', '', '', $opening]];

        foreach ($rows as $row) {
            $entry = $row['entry'];
            $lines[] = [
                $this->date($entry->journal?->journal_date),
                $entry->journal?->journal_number ?? '',
                (string) ($entry->description ?: $entry->journal?->memo),
                (float) $entry->debit,
                (float) $entry->credit,
                (float) $row['running_balance'],
            ];
        }

        return $this->section('Ledger', ['Date', 'Journal #', 'Description', 'Debit', 'Credit', 'Balance'], $lines, [['', '', 'Closing balance', '', '', $closing]]);
    }

    private function periodMeta($period): array
    {
        if (! $period) {
            return [['Period', 'No accounting period']];
        }

        return [['Period', trim(($period->fiscalYear?->name ? $period->fiscalYear->name.' — ' : '').$period->name)
            .' ('.$this->date($period->start_date).' – '.$this->date($period->end_date).')']];
    }

    private function gstMeta(array $d): array
    {
        $meta = [['Period', $d['from']->format('d M Y').' – '.$d['to']->format('d M Y')]];

        if (! empty($d['filerGstin'])) {
            $meta[] = ['GSTIN', $d['filerGstin']];
        }

        return $meta;
    }

    private function date($value): string
    {
        return $value ? Carbon::parse($value)->format('d M Y') : '';
    }

    private function note(string $text): array
    {
        return $this->section('Note', ['Note'], [[$text]]);
    }

    /**
     * @param list<string> $header
     * @param iterable<list<mixed>> $rows
     * @param list<list<mixed>> $footer
     */
    private function section(string $title, array $header, iterable $rows, array $footer = []): array
    {
        return ['title' => $title, 'header' => $header, 'rows' => array_values(is_array($rows) ? $rows : iterator_to_array($rows)), 'footer' => $footer];
    }
}
