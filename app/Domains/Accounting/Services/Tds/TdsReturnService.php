<?php

namespace App\Domains\Accounting\Services\Tds;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TdsChallan;
use App\Domains\Accounting\Models\TdsConfiguration;
use App\Domains\Accounting\Models\TdsDeduction;
use Illuminate\Support\Collection;

/**
 * Quarterly non-salary TDS return (Form 26Q) and the Form 16A certificates
 * built from the same register. Phase 1 gives the 26Q data as a sheet in the
 * return's layout (deductor, challans, deductees) plus the problems that
 * would make the return fail validation; it does not write the FVU file.
 */
class TdsReturnService
{
    public const QUARTERS = [1 => 'Q1 (Apr-Jun)', 2 => 'Q2 (Jul-Sep)', 3 => 'Q3 (Oct-Dec)', 4 => 'Q4 (Jan-Mar)'];

    /** Non-salary TDS (vendors). */
    public const FORM_26Q = '26Q';
    /** TDS on salary, section 192 (employees, from payroll). */
    public const FORM_24Q = '24Q';
    public const FORMS = [self::FORM_26Q => '26Q - Non-salary', self::FORM_24Q => '24Q - Salary'];

    /** Live register rows belonging to a return form. */
    private function formQuery(string $form): \Illuminate\Database\Eloquent\Builder
    {
        return TdsDeduction::query()->live()->whereHas('section', fn ($s) => $form === self::FORM_24Q
            ? $s->where('code', \App\Domains\Accounting\Models\TdsSection::SALARY_CODE)
            : $s->where('code', '!=', \App\Domains\Accounting\Models\TdsSection::SALARY_CODE));
    }

    /**
     * Each employee's salary and TDS for the financial year (24Q Annexure II,
     * Form 16 Part B totals).
     */
    public function annualSalary(string $fiscalYear): Collection
    {
        return $this->formQuery(self::FORM_24Q)
            ->where('fiscal_year', $fiscalYear)
            ->selectRaw('party_id, max(party_name) as party_name, max(pan) as pan, sum(base_amount) as salary, sum(tds_amount) as tds_amount, count(*) as months')
            ->groupBy('party_id')->orderBy('party_name')->get();
    }

    /**
     * Form 16 data for one employee and financial year: Part A (TDS deducted
     * and deposited, by quarter) and the salary/TDS totals for Part B.
     */
    public function form16(int $employeeId, string $fiscalYear): array
    {
        $rows = $this->formQuery(self::FORM_24Q)
            ->where('party_type', JournalEntry::PARTY_EMPLOYEE)->where('party_id', $employeeId)
            ->where('fiscal_year', $fiscalYear)
            ->with('challan')->orderBy('deduction_date')->get();

        $quarters = collect(self::QUARTERS)->map(fn (string $label, int $q) => [
            'label' => $label,
            'salary' => round((float) $rows->where('quarter', $q)->sum('base_amount'), 2),
            'tds' => round((float) $rows->where('quarter', $q)->sum('tds_amount'), 2),
            'deposited' => round((float) $rows->where('quarter', $q)->whereNotNull('tds_challan_id')->sum('tds_amount'), 2),
        ]);

        return [
            'config' => TdsConfiguration::current(),
            'employee' => \App\Domains\HRMS\Models\Employee::find($employeeId),
            'fiscal_year' => $fiscalYear,
            'months' => $rows,
            'quarters' => $quarters,
            'challans' => $rows->pluck('challan')->filter()->unique('id')->values(),
            'total_salary' => round((float) $rows->sum('base_amount'), 2),
            'total_tds' => round((float) $rows->sum('tds_amount'), 2),
            'pan' => $rows->last()?->pan,
        ];
    }

    /**
     * @return array{
     *     fiscal_year: string, quarter: int, config: ?TdsConfiguration,
     *     deductions: Collection<int, TdsDeduction>, challans: Collection<int, TdsChallan>,
     *     totals: array{base: float, tds: float, deposited: float}, issues: list<string>
     * }
     */
    public function quarter(string $fiscalYear, int $quarter, string $form = self::FORM_26Q): array
    {
        // 26Q: rows below threshold carry no TDS and are not reported;
        // lower-rate and nil-rate rows under a certificate are.
        // 24Q: salary months on which tax was actually deducted.
        $deductions = $this->formQuery($form)
            ->where('fiscal_year', $fiscalYear)
            ->where('quarter', $quarter)
            ->when($form === self::FORM_24Q,
                fn ($q) => $q->where('tds_amount', '>', 0),
                fn ($q) => $q->where('reason', '!=', TdsDeduction::REASON_BELOW_THRESHOLD))
            ->with(['section', 'challan'])
            ->orderBy('deduction_date')->orderBy('party_name')->orderBy('id')
            ->get();

        $challans = TdsChallan::query()
            ->whereIn('id', $deductions->pluck('tds_challan_id')->filter()->unique())
            ->orderBy('deposit_date')
            ->get();

        $config = TdsConfiguration::current();

        return [
            'form' => $form,
            'fiscal_year' => $fiscalYear,
            'quarter' => $quarter,
            'config' => $config,
            // 24Q Q4 carries Annexure II: each employee's salary and TDS for the whole year.
            'annual_salary' => $form === self::FORM_24Q && $quarter === 4 ? $this->annualSalary($fiscalYear) : collect(),
            'deductions' => $deductions,
            'challans' => $challans,
            'totals' => [
                'base' => round((float) $deductions->sum(fn (TdsDeduction $d) => $d->base_amount + $d->catchup_base), 2),
                'tds' => round((float) $deductions->sum('tds_amount'), 2),
                'deposited' => round((float) $deductions->whereNotNull('tds_challan_id')->sum('tds_amount'), 2),
            ],
            'issues' => $this->issues($config, $deductions),
        ];
    }

    /** @return list<string> */
    private function issues(?TdsConfiguration $config, Collection $deductions): array
    {
        $issues = [];

        if (blank($config?->tan)) {
            $issues[] = 'The deductor TAN is not set (TDS > Settings).';
        }
        if (blank($config?->responsible_person_name)) {
            $issues[] = 'The responsible person is not set (TDS > Settings).';
        }

        $noPan = $deductions->filter(fn (TdsDeduction $d) => blank($d->pan))->pluck('party_name')->unique()->filter();
        if ($noPan->isNotEmpty()) {
            $issues[] = 'No PAN for: ' . $noPan->implode(', ') . ' (deducted at the higher 206AA rate; reported as PANNOTAVBL).';
        }

        $undeposited = $deductions->filter(fn (TdsDeduction $d) => $d->tds_amount > 0 && $d->tds_challan_id === null);
        if ($undeposited->isNotEmpty()) {
            $issues[] = $undeposited->count() . ' deduction(s) totalling ' . number_format((float) $undeposited->sum('tds_amount'), 2) . ' are not yet linked to a challan.';
        }

        return $issues;
    }

    /**
     * Sheet for the Excel / PDF export, in ReportTables' shape.
     *
     * @return array{title: string, meta: list<array{0: string, 1: string}>, sections: list<array>, orientation: string}
     */
    public function exportTable(array $data): array
    {
        $config = $data['config'];

        $challanRows = $data['challans']->values()->map(fn (TdsChallan $c, int $i) => [
            $i + 1, $c->challan_number, (string) $c->bsr_code, $c->deposit_date->format('d/m/Y'),
            $c->tax_amount, $c->interest, $c->late_fee, $c->totalAmount(),
        ])->all();

        $challanSerial = $data['challans']->values()->mapWithKeys(fn (TdsChallan $c, int $i) => [$c->id => $i + 1]);

        if (($data['form'] ?? self::FORM_26Q) === self::FORM_24Q) {
            return $this->salaryExportTable($data, $challanRows, $challanSerial);
        }

        $deducteeRows = $data['deductions']->map(fn (TdsDeduction $d) => [
            $challanSerial[$d->tds_challan_id] ?? '',
            $d->section?->code,
            $d->pan ?: 'PANNOTAVBL',
            (string) $d->party_name,
            $this->deducteeCode($d->deductee_type),
            $d->deduction_date->format('d/m/Y'),
            round($d->base_amount + $d->catchup_base, 2),
            $d->rate,
            $d->tds_amount,
            $this->remarkCode($d->reason),
            (string) $d->source_number,
        ])->all();

        return [
            'title' => 'Form 26Q - ' . self::QUARTERS[$data['quarter']] . ' FY ' . $data['fiscal_year'],
            'meta' => [
                ['TAN', (string) $config?->tan],
                ['Deductor', (string) $config?->deductor_name],
                ['Responsible person', trim(($config?->responsible_person_name ?? '') . ' ' . ($config?->responsible_person_designation ? '(' . $config->responsible_person_designation . ')' : ''))],
                ['Generated', now()->format('d M Y H:i')],
            ],
            'sections' => [
                [
                    'title' => 'Challans',
                    'header' => ['Sr', 'Challan no.', 'BSR code', 'Deposited on', 'Tax', 'Interest', 'Late fee', 'Total'],
                    'rows' => $challanRows,
                    'footer' => [['Total', '', '', '', (float) $data['challans']->sum('tax_amount'), (float) $data['challans']->sum('interest'), (float) $data['challans']->sum('late_fee'), (float) $data['challans']->sum(fn (TdsChallan $c) => $c->totalAmount())]],
                ],
                [
                    'title' => 'Deductee details',
                    'header' => ['Challan Sr', 'Section', 'PAN', 'Name', 'Deductee code', 'Date of credit', 'Amount paid/credited', 'Rate %', 'TDS', 'Remark', 'Document'],
                    'rows' => $deducteeRows,
                    'footer' => [['Total', '', '', '', '', '', $data['totals']['base'], '', $data['totals']['tds'], '', '']],
                ],
            ],
            'orientation' => 'landscape',
        ];
    }

    private function salaryExportTable(array $data, array $challanRows, Collection $challanSerial): array
    {
        $config = $data['config'];
        $challans = $data['challans'];

        $sections = [
            [
                'title' => 'Challans',
                'header' => ['Sr', 'Challan no.', 'BSR code', 'Deposited on', 'Tax', 'Interest', 'Late fee', 'Total'],
                'rows' => $challanRows,
                'footer' => [['Total', '', '', '', (float) $challans->sum('tax_amount'), (float) $challans->sum('interest'), (float) $challans->sum('late_fee'), (float) $challans->sum(fn (TdsChallan $c) => $c->totalAmount())]],
            ],
            [
                'title' => 'Annexure I - deductee details (salary)',
                'header' => ['Challan Sr', 'Section', 'PAN', 'Employee', 'Salary month', 'Amount paid/credited', 'TDS', 'Remark'],
                'rows' => $data['deductions']->map(fn (TdsDeduction $d) => [
                    $challanSerial[$d->tds_challan_id] ?? '',
                    '192',
                    $d->pan ?: 'PANNOTAVBL',
                    (string) $d->party_name,
                    $d->deduction_date->format('M Y'),
                    round($d->base_amount, 2),
                    $d->tds_amount,
                    $this->remarkCode($d->reason),
                ])->all(),
                'footer' => [['Total', '', '', '', '', $data['totals']['base'], $data['totals']['tds'], '']],
            ],
        ];

        if ($data['annual_salary']->isNotEmpty()) {
            $sections[] = [
                'title' => 'Annexure II - salary details for the year',
                'header' => ['PAN', 'Employee', 'Months paid', 'Gross salary', 'TDS for the year'],
                'rows' => $data['annual_salary']->map(fn ($r) => [$r->pan ?: 'PANNOTAVBL', (string) $r->party_name, (int) $r->months, round((float) $r->salary, 2), round((float) $r->tds_amount, 2)])->all(),
                'footer' => [['Total', '', '', round((float) $data['annual_salary']->sum('salary'), 2), round((float) $data['annual_salary']->sum('tds_amount'), 2)]],
            ];
        }

        return [
            'title' => 'Form 24Q - ' . self::QUARTERS[$data['quarter']] . ' FY ' . $data['fiscal_year'],
            'meta' => [
                ['TAN', (string) $config?->tan],
                ['Deductor', (string) $config?->deductor_name],
                ['Responsible person', trim(($config?->responsible_person_name ?? '') . ' ' . ($config?->responsible_person_designation ? '(' . $config->responsible_person_designation . ')' : ''))],
                ['Generated', now()->format('d M Y H:i')],
            ],
            'sections' => $sections,
            'orientation' => 'landscape',
        ];
    }

    /**
     * Form 16A data for one vendor and quarter.
     *
     * @return array{config: ?TdsConfiguration, vendor: ?\App\Domains\Inventory\Models\Vendor, fiscal_year: string, quarter: int, deductions: Collection, challans: Collection, total_base: float, total_tds: float}
     */
    public function form16a(int $vendorId, string $fiscalYear, int $quarter): array
    {
        $deductions = TdsDeduction::query()->live()
            ->where('party_type', JournalEntry::PARTY_VENDOR)->where('party_id', $vendorId)
            ->where('fiscal_year', $fiscalYear)->where('quarter', $quarter)
            ->where('tds_amount', '>', 0)
            ->with(['section', 'challan'])->orderBy('deduction_date')->get();

        return [
            'config' => TdsConfiguration::current(),
            'vendor' => \App\Domains\Inventory\Models\Vendor::find($vendorId),
            'fiscal_year' => $fiscalYear,
            'quarter' => $quarter,
            'deductions' => $deductions,
            'challans' => $deductions->pluck('challan')->filter()->unique('id')->values(),
            'total_base' => round((float) $deductions->sum(fn (TdsDeduction $d) => $d->base_amount + $d->catchup_base), 2),
            'total_tds' => round((float) $deductions->sum('tds_amount'), 2),
        ];
    }

    /** Vendors with deducted TDS in a quarter (who need a Form 16A). */
    public function deducteesFor(string $fiscalYear, int $quarter): Collection
    {
        return TdsDeduction::query()->live()
            ->where('party_type', JournalEntry::PARTY_VENDOR)
            ->where('fiscal_year', $fiscalYear)->where('quarter', $quarter)->where('tds_amount', '>', 0)
            ->selectRaw('party_id, max(party_name) as party_name, max(pan) as pan, sum(tds_amount) as tds_amount')
            ->groupBy('party_id')->orderBy('party_name')->get();
    }

    /** 26Q deductee code: 01 company, 02 anyone else. */
    private function deducteeCode(?string $type): string
    {
        return $type === 'company' ? '01' : '02';
    }

    /** 26Q remark: A lower deduction (197), C higher rate (no PAN). */
    private function remarkCode(string $reason): string
    {
        return match ($reason) {
            TdsDeduction::REASON_LOWER_CERT => 'A',
            TdsDeduction::REASON_NO_PAN => 'C',
            default => '',
        };
    }
}
