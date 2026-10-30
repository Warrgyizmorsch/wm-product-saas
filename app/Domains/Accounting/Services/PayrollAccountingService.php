<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Services\PayrollCalculationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Payroll in the ledger, in three steps:
 *
 * 1. Accrual, dated the last day of the payroll month:
 *      Dr Salary & Wages (earned gross)       Cr PF / ESI / PT / TDS Payable (employee deductions)
 *      Dr Employer Contribution to PF & ESI   Cr PF / ESI Payable (employer share + PF charges)
 *                                             Cr Salary Payable (net pay)
 * 2. Payout, from the bank/cash ledger chosen at release:
 *      Dr Salary Payable (net pay booked in step 1, less held payslips)   Cr Bank
 * 3. Statutory dues, when the challan is paid:
 *      Dr PF / ESI / PT / TDS Payable   Cr Bank
 *
 * Every failure throws, so the payroll screen can say why instead of the run
 * being marked paid with nothing in the books.
 */
class PayrollAccountingService
{
    public const ACCRUAL_REFERENCE = 'PayrollRun';
    public const PAYOUT_REFERENCE = 'PayrollRunPayout';
    public const STATUTORY_REFERENCE = 'payroll_statutory_payment';

    /** Liabilities a payroll leaves behind and that are paid by challan. */
    public const STATUTORY_DUES = [
        'pf_payable' => 'Provident Fund (EPF)',
        'esi_payable' => 'Employee State Insurance (ESI)',
        'professional_tax_payable' => 'Professional Tax',
        'tds_payable' => 'TDS on Salary',
        'statutory_dues_payable' => 'Other Statutory Dues',
    ];

    public function __construct(
        private readonly JournalService $journalService,
        private readonly PayrollCalculationService $payrollCalculationService,
        private readonly SystemAccountService $systemAccounts,
    ) {}

    /**
     * Post balanced Double-Entry Accounting Journal for a completed Payroll Run.
     */
    public function postPayrollRunJournal(PayrollRun $run): ?Journal
    {
        // Fail closed rather than silently posting into tenant 1's ledger if
        // both the run's own tenant_id and the bound request context are
        // somehow unresolvable.
        $tenantId = (int) ($run->tenant_id ?? current_tenant_id() ?? require_tenant_id());

        // Check if journal already posted for this payroll run to prevent duplicate entries
        $existingJournal = $this->journalService->activePosting($tenantId, self::ACCRUAL_REFERENCE, $run->id);

        if ($existingJournal) {
            return $existingJournal;
        }

        $employees = $this->runEmployees($run);

        if ($employees->isEmpty()) {
            return null;
        }

        // Auto-resolve or create default fallback COA accounts by code
        $defaultAccounts = [
            '5100' => $this->getOrCreateAccount($tenantId, '5100', 'Salary & Wages - Staff', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::BALANCE_DEBIT, ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE),
            '5110' => $this->getOrCreateAccount($tenantId, '5110', 'Employer Contribution to PF & ESI', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::BALANCE_DEBIT, ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE),
            '2030' => $this->getOrCreateAccount($tenantId, '2030', 'Salary Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::BALANCE_CREDIT, ChartOfAccount::SUBTYPE_CURRENT_LIABILITY),
            '2160' => $this->getOrCreateAccount($tenantId, '2160', 'PF Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::BALANCE_CREDIT, ChartOfAccount::SUBTYPE_DUTIES_TAXES),
            '2170' => $this->getOrCreateAccount($tenantId, '2170', 'ESI Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::BALANCE_CREDIT, ChartOfAccount::SUBTYPE_DUTIES_TAXES),
            '2140' => $this->getOrCreateAccount($tenantId, '2140', 'TDS Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::BALANCE_CREDIT, ChartOfAccount::SUBTYPE_DUTIES_TAXES),
            '2180' => $this->getOrCreateAccount($tenantId, '2180', 'Professional Tax Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::BALANCE_CREDIT, ChartOfAccount::SUBTYPE_DUTIES_TAXES),
            '1420' => $this->getOrCreateAccount($tenantId, '1420', 'Advance to Employees', ChartOfAccount::TYPE_ASSET, ChartOfAccount::BALANCE_DEBIT, ChartOfAccount::SUBTYPE_LOANS_ADVANCES),
            '2080' => $this->getOrCreateAccount($tenantId, '2080', 'Statutory Dues Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::BALANCE_CREDIT, ChartOfAccount::SUBTYPE_CURRENT_LIABILITY),
        ];

        $salaryExpenseAccount = $defaultAccounts['5100'];
        $salaryPayableAccount = $defaultAccounts['2030'];

        $allComponents = SalaryComponent::get()->keyBy('code');

        $debitGroup = [];   // Account ID => Amount (Debits for actual earned salary expenses)
        $creditGroup = [];  // Account ID => Amount (Credits for statutory & other deductions)
        $totalNetPayout = 0.00;
        $netByEmployee = [];      // employee id => net pay (one Salary Payable line each)
        $recoveryByEmployee = []; // employee id => advance / loan recovered this month
        $tdsByEmployee = [];      // employee id => TDS on salary (section 192)
        $grossByEmployee = [];    // employee id => salary earned after LOP
        $calcs = [];              // employee id => salary calculation (PF / ESI returns)

        foreach ($employees as $employee) {
            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month, $run);
            $calcs[$employee->id] = $calc;
            $items = $calc['items'] ?? $calc['computed_components'] ?? [];

            $empGrossEarned = 0.00;
            $empLop = 0.00;
            $empEarnedByAccount = [];

            foreach ($items as $code => $itemData) {
                $val = (float) ($itemData['calculated_value'] ?? 0.00);
                if ($val <= 0) {
                    continue;
                }

                $type = $itemData['type'] ?? 'earning';
                $componentModel = $allComponents[$code] ?? null;

                if ($type === 'earning') {
                    // Actual earned earnings
                    $accId = $salaryExpenseAccount->id;
                    $empEarnedByAccount[$accId] = ($empEarnedByAccount[$accId] ?? 0.00) + $val;
                    $empGrossEarned += $val;
                } else {
                    $bucket = self::deductionBucket((string) $code, (string) ($componentModel?->name ?? $itemData['name'] ?? ''));

                    if ($bucket === 'lop') {
                        $empLop += $val;
                        continue; // LOP reduces earning expense; do not credit LOP as a liability!
                    }

                    // Statutory & other deductions (PF, ESI, TDS, PT, Advance, etc.)
                    $accId = $defaultAccounts[$bucket]->id;
                    if ($bucket === '2140') {
                        $tdsByEmployee[$employee->id] = ($tdsByEmployee[$employee->id] ?? 0.00) + $val;
                    }

                    // Advance / loan recovery reduces that employee's own advance.
                    if ($accId === $defaultAccounts['1420']->id) {
                        $recoveryByEmployee[$employee->id] = ($recoveryByEmployee[$employee->id] ?? 0.00) + $val;
                        continue;
                    }

                    $creditGroup[$accId] = ($creditGroup[$accId] ?? 0.00) + $val;
                }
            }

            // Deduct LOP from total gross earning expenses for this employee
            if ($empGrossEarned > 0 && $empLop > 0) {
                $lopRatio = min(1.0, $empLop / $empGrossEarned);
                foreach ($empEarnedByAccount as $accId => $grossVal) {
                    $actualEarned = $grossVal * (1.0 - $lopRatio);
                    $debitGroup[$accId] = ($debitGroup[$accId] ?? 0.00) + $actualEarned;
                }
            } else {
                foreach ($empEarnedByAccount as $accId => $grossVal) {
                    $debitGroup[$accId] = ($debitGroup[$accId] ?? 0.00) + $grossVal;
                }
            }
            $grossByEmployee[$employee->id] = $empGrossEarned > 0 ? $empGrossEarned * (1.0 - min(1.0, $empLop / $empGrossEarned)) : 0.00;

            $net = (float) ($calc['summary']['net_payout'] ?? 0.00);
            $totalNetPayout += $net;
            if ($net > 0) {
                $netByEmployee[$employee->id] = ($netByEmployee[$employee->id] ?? 0.00) + $net;
            }
        }

        // Build Journal Entry Lines
        $lines = [];

        foreach ($debitGroup as $accId => $debitVal) {
            $debitVal = round($debitVal, 2);
            if ($debitVal > 0) {
                $lines[] = [
                    'chart_of_account_id' => $accId,
                    'debit'               => $debitVal,
                    'credit'              => 0.00,
                    'description'         => "Gross earned salary expense for month {$run->payroll_month}",
                ];
            }
        }

        foreach ($creditGroup as $accId => $creditVal) {
            $creditVal = round($creditVal, 2);
            if ($creditVal > 0) {
                $accountModel = ChartOfAccount::find($accId);
                $accountName = $accountModel?->name ?? 'Deductions Payable';

                $lines[] = [
                    'chart_of_account_id' => $accId,
                    'debit'               => 0.00,
                    'credit'              => $creditVal,
                    'description'         => "{$accountName} deduction for month {$run->payroll_month}",
                ];
            }
        }

        // Net pay owed to each employee on their own Salary Payable line, so
        // the bill-wise register knows who is still unpaid (Tally payroll).
        $monthEnd = $this->monthEnd($run);
        $billHints = [];
        foreach ($netByEmployee as $employeeId => $net) {
            $lines[] = [
                'chart_of_account_id' => $salaryPayableAccount->id,
                'debit'               => 0.00,
                'credit'              => round($net, 2),
                'party_type'          => \App\Domains\Accounting\Models\JournalEntry::PARTY_EMPLOYEE,
                'party_id'            => $employeeId,
                'description'         => "Net salary payable for month {$run->payroll_month}",
            ];
            $billHints[] = BillWise\BillAllocationService::newRef('payable', "SAL-{$run->payroll_month}", $monthEnd, $monthEnd, 'payroll_salary', (int) $run->id)
                + ['party_type' => \App\Domains\Accounting\Models\JournalEntry::PARTY_EMPLOYEE, 'party_id' => $employeeId, 'account_id' => $salaryPayableAccount->id];
        }

        foreach ($recoveryByEmployee as $employeeId => $recovered) {
            if (round($recovered, 2) > 0) {
                $lines[] = [
                    'chart_of_account_id' => $defaultAccounts['1420']->id,
                    'debit'               => 0.00,
                    'credit'              => round($recovered, 2),
                    'party_type'          => \App\Domains\Accounting\Models\JournalEntry::PARTY_EMPLOYEE,
                    'party_id'            => $employeeId,
                    'description'         => "Advance / loan recovered for month {$run->payroll_month}",
                ];
                // Recovery pays back the employee's open advances, oldest first.
                $billHints[] = BillWise\BillAllocationService::againstOldest('receivable')
                    + ['party_type' => \App\Domains\Accounting\Models\JournalEntry::PARTY_EMPLOYEE, 'party_id' => $employeeId, 'account_id' => $defaultAccounts['1420']->id];
            }
        }

        // Verify Debits & Credits balance down to exact cent
        $totalDebit = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

        if (abs($totalDebit - $totalCredit) > 0.001) {
            // Adjust any rounding difference of a few cents on net salary payable line
            $diff = round($totalDebit - $totalCredit, 2);
            foreach ($lines as &$line) {
                if ($line['chart_of_account_id'] === $salaryPayableAccount->id && $line['credit'] > 0) {
                    $line['credit'] = round($line['credit'] + $diff, 2);
                    break;
                }
            }
            unset($line);
        }

        // The employer's own PF / ESI on top of what employees paid: a cost of
        // the company, owed to the same funds. Balanced on its own.
        array_push($lines, ...$this->employerContributionLines(
            round((float) ($creditGroup[$defaultAccounts['2160']->id] ?? 0), 2),
            round((float) ($creditGroup[$defaultAccounts['2170']->id] ?? 0), 2),
            $defaultAccounts['5110'],
            $defaultAccounts['2160'],
            $defaultAccounts['2170'],
            $run->payroll_month,
        ));

        // Salary is an expense of the month it was earned in: its last day.
        // No period for it means the financial year hasn't been set up; posting
        // fails with that message rather than inventing a calendar-year FY.
        $meta = [
            'tenant_id'             => $tenantId,
            'company_id'            => $run->company_id ?? require_company_id(),
            'journal_date'          => $this->monthEnd($run),
            'source'                => 'payroll',
            'voucher_type'          => 'payroll',
            'journal_number_prefix' => 'PAY',
            'reference_type'        => self::ACCRUAL_REFERENCE,
            'reference_id'          => $run->id,
            'memo'                  => "Payroll Journal entry for month {$run->payroll_month} (Run ID: {$run->id})",
            'bill_allocations'      => $billHints,
            // Null when run without a signed-in user (queue/console) — shown as "System",
            // rather than crediting user #1, who may not even belong to this tenant.
            'posted_by'             => auth()->id(),
        ];

        $journal = $this->journalService->postOnce($lines, $meta);

        // The books are right either way; a register hiccup must not fail the
        // payroll. `php artisan tds:sync-payroll` fills any gap later.
        try {
            $this->recordSalaryTds($run, $journal, $employees, $grossByEmployee, $tdsByEmployee);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Payroll: could not record salary TDS in the TDS register', ['run_id' => $run->id, 'error' => $e->getMessage()]);
        }

        // Same for the PF ECR / ESIC return figures; `payroll:sync-statutory` fills gaps.
        try {
            app(\App\Domains\HRMS\Services\PayrollStatutoryService::class)->captureRun($run, $employees, $calcs, $journal->company_id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Payroll: could not record PF / ESI return lines', ['run_id' => $run->id, 'error' => $e->getMessage()]);
        }

        return $journal;
    }

    /**
     * Which ledger a payroll deduction goes to, by its component code/name:
     * 'lop' (reduces earnings, not a liability) or a default account code.
     * Order matters: a code containing both PF and TAX is PF.
     */
    public static function deductionBucket(string $code, string $name): string
    {
        $codeUpper = strtoupper($code);
        $nameUpper = strtoupper($name);

        return match (true) {
            $codeUpper === 'LOP' || str_contains($codeUpper, 'LOSS_OF_PAY') || str_contains($nameUpper, 'LOSS OF PAY') => 'lop',
            str_contains($codeUpper, 'PF') || str_contains($codeUpper, 'PROVIDENT') || str_contains($nameUpper, 'PROVIDENT') || str_contains($nameUpper, 'PF') => '2160',
            str_contains($codeUpper, 'ESI') || str_contains($nameUpper, 'ESI') || str_contains($nameUpper, 'STATE INSURANCE') => '2170',
            str_contains($codeUpper, 'TDS') || str_contains($codeUpper, 'TAX') || str_contains($nameUpper, 'TDS') || str_contains($nameUpper, 'INCOME TAX') || str_contains($nameUpper, 'TAX DEDUCTION') => '2140',
            str_contains($codeUpper, 'PT') || str_contains($codeUpper, 'PROFTAX') || str_contains($nameUpper, 'PROFESSIONAL TAX') => '2180',
            str_contains($codeUpper, 'ADVANCE') || str_contains($codeUpper, 'LOAN') || str_contains($nameUpper, 'ADVANCE') || str_contains($nameUpper, 'LOAN') => '1420',
            default => '2080', // any other custom deduction
        };
    }

    /**
     * Each employee's salary and TDS for a run, into the TDS register under
     * section 192 (the 24Q and Form 16 data). Every paid employee gets a row,
     * TDS or not, so the year's salary totals for Form 16 are complete.
     *
     * @param array<int, float> $grossByEmployee
     * @param array<int, float> $tdsByEmployee
     */
    public function recordSalaryTds(PayrollRun $run, Journal $journal, Collection $employees, array $grossByEmployee, array $tdsByEmployee): void
    {
        $tenantId = (int) $journal->tenant_id;
        $section = app(Tds\TdsSectionService::class)->salarySection($tenantId);
        $recorder = app(Tds\TdsDeductionRecorder::class);
        $date = $this->monthEnd($run);

        foreach ($employees as $employee) {
            $gross = round((float) ($grossByEmployee[$employee->id] ?? 0), 2);
            $tds = round((float) ($tdsByEmployee[$employee->id] ?? 0), 2);

            if ($gross <= 0 && $tds <= 0) {
                continue;
            }

            $recorder->record([
                'tenant_id' => $tenantId,
                'company_id' => $journal->company_id,
                'branch_id' => $employee->branch_id,
                'tds_section_id' => $section->id,
                'party_type' => JournalEntry::PARTY_EMPLOYEE,
                'party_id' => (int) $employee->id,
                'party_name' => $employee->full_name,
                'pan' => $employee->pan_card_number,
                'deductee_type' => 'individual',
                'source_type' => \App\Domains\Accounting\Models\TdsDeduction::SOURCE_PAYROLL_RUN,
                'source_id' => (int) $run->id,
                'source_number' => "Payroll {$run->payroll_month}",
                'journal_id' => $journal->id,
                'deduction_date' => $date,
                'base_amount' => $gross,
                'rate' => $gross > 0 ? round($tds / $gross * 100, 3) : 0,
                'tds_amount' => $tds,
                'reason' => blank($employee->pan_card_number) && $tds > 0
                    ? \App\Domains\Accounting\Models\TdsDeduction::REASON_NO_PAN
                    : \App\Domains\Accounting\Models\TdsDeduction::REASON_NORMAL,
            ]);
        }
    }

    /**
     * PF / ESI return lines for a run posted before they were kept: recalculates
     * each employee and saves them only if PF and ESI (employee share plus the
     * employer contribution booked on it) still add up to what the run's
     * journal credited to PF Payable and ESI Payable.
     */
    public function backfillStatutoryLines(PayrollRun $run): bool
    {
        $tenantId = (int) $run->tenant_id;
        $journal = $this->journalService->activePosting($tenantId, self::ACCRUAL_REFERENCE, $run->id);
        $pfAccount = $this->systemAccounts->get('pf_payable', $tenantId);
        $esiAccount = $this->systemAccounts->get('esi_payable', $tenantId);
        $expense = $this->systemAccounts->get('employer_statutory_contribution', $tenantId);

        if ($journal === null || $pfAccount === null || $esiAccount === null || $expense === null) {
            return false;
        }

        $employees = $this->runEmployees($run);
        $calcs = [];
        $pf = 0.00;
        $esi = 0.00;
        foreach ($employees as $employee) {
            $calcs[$employee->id] = $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month, $run);
            $pf += (float) ($calc['items']['PF']['calculated_value'] ?? 0);
            $esi += (float) ($calc['items']['ESI']['calculated_value'] ?? 0);
        }

        $expected = [$pfAccount->id => round($pf, 2), $esiAccount->id => round($esi, 2)];
        foreach ($this->employerContributionLines(round($pf, 2), round($esi, 2), $expense, $pfAccount, $esiAccount, (string) $run->payroll_month) as $line) {
            if (isset($expected[$line['chart_of_account_id']])) {
                $expected[$line['chart_of_account_id']] += (float) $line['credit'];
            }
        }

        foreach ($expected as $accountId => $amount) {
            $posted = (float) $journal->entries()->where('chart_of_account_id', $accountId)->sum('credit');
            if (abs(round($amount, 2) - round($posted, 2)) > 0.01) {
                return false;
            }
        }

        app(\App\Domains\HRMS\Services\PayrollStatutoryService::class)->captureRun($run, $employees, $calcs, $journal->company_id);

        return true;
    }

    /**
     * Salary TDS for a run that was posted before the TDS register existed:
     * recalculates each employee and records them, but only if the TDS adds up
     * to what the run's journal actually credited to TDS Payable — otherwise
     * (salary inputs changed since) nothing is recorded and false is returned.
     */
    public function backfillSalaryTds(PayrollRun $run): bool
    {
        $tenantId = (int) $run->tenant_id;
        $journal = $this->journalService->activePosting($tenantId, self::ACCRUAL_REFERENCE, $run->id);
        $tdsAccount = $this->systemAccounts->get(SystemAccount::TDS_PAYABLE, $tenantId);

        if ($journal === null || $tdsAccount === null) {
            return false;
        }

        $allComponents = SalaryComponent::get()->keyBy('code');
        $employees = $this->runEmployees($run);
        $gross = [];
        $tds = [];

        foreach ($employees as $employee) {
            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month, $run);
            $earned = 0.00;
            $lop = 0.00;

            foreach ($calc['items'] ?? $calc['computed_components'] ?? [] as $code => $item) {
                $val = (float) ($item['calculated_value'] ?? 0);
                if ($val <= 0) {
                    continue;
                }
                if (($item['type'] ?? 'earning') === 'earning') {
                    $earned += $val;
                    continue;
                }
                $bucket = self::deductionBucket((string) $code, (string) ($allComponents[$code]?->name ?? $item['name'] ?? ''));
                if ($bucket === 'lop') {
                    $lop += $val;
                } elseif ($bucket === '2140') {
                    $tds[$employee->id] = ($tds[$employee->id] ?? 0) + $val;
                }
            }

            $gross[$employee->id] = $earned > 0 ? $earned * (1.0 - min(1.0, $lop / $earned)) : 0.00;
        }

        $posted = (float) $journal->entries()->where('chart_of_account_id', $tdsAccount->id)->sum('credit');
        if (abs(round(array_sum($tds), 2) - round($posted, 2)) > 0.01) {
            return false;
        }

        $this->recordSalaryTds($run, $journal, $employees, $gross, $tds);

        return true;
    }

    /**
     * Employer PF (matching the employee's 12%) plus EDLI/admin charges, and
     * employer ESI (3.25% against the employee's 0.75%). Rates: config/payroll.php.
     *
     * @return list<array<string, mixed>>
     */
    public function employerContributionLines(float $employeePf, float $employeeEsi, ChartOfAccount $expense, ChartOfAccount $pfPayable, ChartOfAccount $esiPayable, string $month): array
    {
        $rates = config('payroll.employer_contributions');
        $lines = [];

        if ($employeePf > 0 && ($rates['employer_pf_matches_employee'] ?? true)) {
            $pfWages = $employeePf / ((float) ($rates['employee_pf_percent'] ?? 12) / 100);
            $employerPf = round($employeePf + $pfWages * (float) ($rates['pf_charges_percent'] ?? 1.0) / 100, 2);

            $lines[] = ['chart_of_account_id' => $expense->id, 'debit' => $employerPf, 'credit' => 0.00, 'description' => "Employer PF + EDLI/admin charges for month {$month}"];
            $lines[] = ['chart_of_account_id' => $pfPayable->id, 'debit' => 0.00, 'credit' => $employerPf, 'description' => "Employer PF contribution for month {$month}"];
        }

        if ($employeeEsi > 0) {
            $employerEsi = round($employeeEsi * (float) ($rates['employer_esi_percent'] ?? 3.25) / (float) ($rates['employee_esi_percent'] ?? 0.75), 2);

            $lines[] = ['chart_of_account_id' => $expense->id, 'debit' => $employerEsi, 'credit' => 0.00, 'description' => "Employer ESI for month {$month}"];
            $lines[] = ['chart_of_account_id' => $esiPayable->id, 'debit' => 0.00, 'credit' => $employerEsi, 'description' => "Employer ESI contribution for month {$month}"];
        }

        return $lines;
    }

    /**
     * Post Cash/Bank payout journal entry when payroll payout is released.
     *
     * Pays exactly the net salary booked by this run's accrual (less held
     * payslips) out of the chosen cash/bank ledger; refuses a cash payment
     * larger than the cash actually on hand.
     */
    public function postPayrollPayoutJournal(PayrollRun $run, ?int $bankAccountId = null, ?string $payoutDate = null): ?Journal
    {
        // Fail closed rather than silently posting into tenant 1's ledger if
        // both the run's own tenant_id and the bound request context are
        // somehow unresolvable.
        $tenantId = (int) ($run->tenant_id ?? current_tenant_id() ?? require_tenant_id());

        // Standing posting only: once reversed, the document can post again.
        $existingJournal = $this->journalService->activePosting($tenantId, self::PAYOUT_REFERENCE, $run->id);

        if ($existingJournal) {
            return $existingJournal;
        }

        $accrual = $this->journalService->activePosting($tenantId, self::ACCRUAL_REFERENCE, $run->id)
            ?? $this->postPayrollRunJournal($run);

        if ($accrual === null) {
            return null;
        }

        $salaryPayableAccount = $this->getOrCreateAccount($tenantId, '2030', 'Salary Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::BALANCE_CREDIT, ChartOfAccount::SUBTYPE_CURRENT_LIABILITY);

        // What the accrual actually owes staff, not a fresh recalculation that
        // could differ if attendance or salary changed since.
        $bookedLines = JournalEntry::withoutGlobalScopes()
            ->where('journal_id', $accrual->id)
            ->where('chart_of_account_id', $salaryPayableAccount->id)
            ->where('credit', '>', 0)
            ->get(['party_type', 'party_id', 'credit']);

        $lines = [];
        $billHints = [];
        $employeeLines = $bookedLines->where('party_type', JournalEntry::PARTY_EMPLOYEE);

        if ($employeeLines->isNotEmpty()) {
            // One line per employee; a payslip on hold stays owed (its SAL bill open).
            $held = $this->heldEmployeeIds($run);
            foreach ($employeeLines as $booked) {
                if (in_array((int) $booked->party_id, $held, true)) {
                    continue;
                }
                $lines[] = [
                    'chart_of_account_id' => $salaryPayableAccount->id,
                    'debit'               => round((float) $booked->credit, 2),
                    'credit'              => 0.00,
                    'party_type'          => JournalEntry::PARTY_EMPLOYEE,
                    'party_id'            => (int) $booked->party_id,
                    'description'         => "Net salary paid for month {$run->payroll_month}",
                ];
                $billHints[] = BillWise\BillAllocationService::againstRef('payable', 'payroll_salary', (int) $run->id)
                    + ['party_type' => JournalEntry::PARTY_EMPLOYEE, 'party_id' => (int) $booked->party_id, 'account_id' => $salaryPayableAccount->id];
            }
        } else {
            // Salary entries posted before per-employee lines: one lump line.
            $lump = round((float) $bookedLines->sum('credit') - $this->heldNetPay($run), 2);
            if ($lump > 0) {
                $lines[] = [
                    'chart_of_account_id' => $salaryPayableAccount->id,
                    'debit'               => $lump,
                    'credit'              => 0.00,
                    'description'         => "Net salary payout release for month {$run->payroll_month}",
                ];
            }
        }

        $totalNetPayout = round(array_sum(array_column($lines, 'debit')), 2);
        if ($totalNetPayout <= 0) {
            return null;
        }

        $bankAccount = $this->payingLedger($tenantId, $bankAccountId);
        $payoutDate = Carbon::parse($payoutDate ?? now())->toDateString();

        $this->assertCashCovers($bankAccount, $totalNetPayout, $payoutDate);

        $lines[] = [
            'chart_of_account_id' => $bankAccount->id,
            'debit'               => 0.00,
            'credit'              => $totalNetPayout,
            'description'         => "Salary disbursement for month {$run->payroll_month} from {$bankAccount->name}",
        ];

        $meta = [
            'tenant_id'             => $tenantId,
            'company_id'            => $run->company_id ?? require_company_id(),
            'journal_date'          => $payoutDate,
            'source'                => 'payroll',
            'voucher_type'          => 'payment',
            'journal_number_prefix' => 'PAY-DISB',
            'reference_type'        => self::PAYOUT_REFERENCE,
            'reference_id'          => $run->id,
            'memo'                  => "Salary disbursement for month {$run->payroll_month} (Run ID: {$run->id}) from {$bankAccount->code} {$bankAccount->name}",
            'posted_by'             => auth()->id(),
            'bill_allocations'      => $billHints,
        ];

        return $this->journalService->postOnce($lines, $meta);
    }

    /**
     * What is still owed on each statutory liability (credit balance).
     *
     * @return array<string, array{label: string, account: ChartOfAccount, balance: float}>
     */
    public function statutoryDues(int $tenantId): array
    {
        $dues = [];

        foreach (self::STATUTORY_DUES as $key => $label) {
            $account = $this->systemAccounts->get($key, $tenantId);
            if ($account === null) {
                continue;
            }

            $dues[$key] = ['label' => $label, 'account' => $account, 'balance' => $this->balance($account, null, credit: true)];
        }

        return $dues;
    }

    /**
     * Pay a statutory due (PF / ESI / PT / TDS challan): Dr the payable,
     * Cr the bank. Never more than is owed, never more cash than is on hand.
     */
    public function postStatutoryPayment(int $tenantId, string $dueKey, float $amount, int $bankAccountId, string $paymentDate, ?string $reference = null): Journal
    {
        if (! array_key_exists($dueKey, self::STATUTORY_DUES)) {
            throw new InvalidArgumentException("Unknown statutory due '{$dueKey}'.");
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Enter the amount paid.');
        }

        $payable = $this->systemAccounts->require($dueKey, $tenantId);
        $owed = $this->balance($payable, null, credit: true);
        if ($amount - $owed > 0.009) {
            throw new InvalidArgumentException(sprintf('%s owed is only %s; you cannot pay %s against it.', self::STATUTORY_DUES[$dueKey], number_format($owed, 2), number_format($amount, 2)));
        }

        $bank = $this->payingLedger($tenantId, $bankAccountId);
        $this->assertCashCovers($bank, $amount, $paymentDate);

        $label = self::STATUTORY_DUES[$dueKey];
        $memo = trim("{$label} paid" . ($reference ? " - challan {$reference}" : ''));

        return $this->journalService->post([
            ['chart_of_account_id' => $payable->id, 'debit' => $amount, 'credit' => 0.00, 'description' => $memo],
            ['chart_of_account_id' => $bank->id, 'debit' => 0.00, 'credit' => $amount, 'description' => $memo],
        ], [
            'tenant_id' => $tenantId,
            'journal_date' => Carbon::parse($paymentDate)->toDateString(),
            'source' => 'payroll',
            'voucher_type' => 'payment',
            'journal_number_prefix' => 'PAY-STAT',
            'reference_type' => self::STATUTORY_REFERENCE,
            'reference_id' => $payable->id,
            'memo' => $memo,
            'posted_by' => auth()->id(),
        ]);
    }

    /** Cash and bank ledgers a payment can go out of. */
    public function payingLedgers(): Collection
    {
        return ChartOfAccount::query()->where('is_cash_or_bank', true)->where('is_active', true)->orderBy('code')->get();
    }

    /** Debit-positive balance of a ledger up to a date (credit-positive when $credit). */
    public function balance(ChartOfAccount $account, ?string $asOf = null, bool $credit = false): float
    {
        $totals = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $account->tenant_id)
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journal', fn ($q) => $q->withoutGlobalScopes()
                ->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])
                ->when($asOf, fn ($w) => $w->whereDate('journal_date', '<=', $asOf)))
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
            ->first();

        $balance = round((float) $totals->d - (float) $totals->c, 2);

        return $credit ? -$balance : $balance;
    }

    private function payingLedger(int $tenantId, ?int $accountId): ChartOfAccount
    {
        if ($accountId) {
            $account = ChartOfAccount::query()->where('tenant_id', $tenantId)->whereKey($accountId)->first();
            if ($account === null || ! $account->is_cash_or_bank) {
                throw new InvalidArgumentException('Choose the cash or bank account the money is paid from.');
            }

            return $account;
        }

        // Salaries go out by bank transfer by default: the Bank Account ledger,
        // never "the first cash/bank ledger" (that was Cash-in-Hand).
        return $this->systemAccounts->get(SystemAccount::BANK, $tenantId)
            ?? $this->getOrCreateAccount($tenantId, '1020', 'Bank Account', ChartOfAccount::TYPE_ASSET, ChartOfAccount::BALANCE_DEBIT, ChartOfAccount::SUBTYPE_CURRENT_ASSET);
    }

    /**
     * Physical cash can't go below zero. Bank ledgers may (an overdraft or
     * cash credit limit is normal), so only cash ledgers are checked.
     */
    private function assertCashCovers(ChartOfAccount $account, float $amount, string $date): void
    {
        if (! $this->isCashLedger($account)) {
            return;
        }

        $available = $this->balance($account, $date);

        if ($amount - $available > 0.009) {
            throw new InvalidArgumentException(sprintf(
                '%s %s has only %s on %s; it cannot pay %s. Pay from a bank account, or record the cash received first.',
                $account->code, $account->name, number_format(max($available, 0), 2), Carbon::parse($date)->format('d M Y'), number_format($amount, 2),
            ));
        }
    }

    private function isCashLedger(ChartOfAccount $account): bool
    {
        if ($account->system_key === SystemAccount::CASH || $account->code === SystemAccount::templateCode(SystemAccount::CASH)) {
            return true;
        }

        $name = strtolower((string) $account->name);

        return str_contains($name, 'cash') && ! str_contains($name, 'bank') && ! str_contains($name, 'credit');
    }

    /** @return list<int> employees whose payslip for this run's month is on hold */
    private function heldEmployeeIds(PayrollRun $run): array
    {
        return PayrollHold::where('payroll_month', $run->payroll_month)
            ->where('status', 'on_hold')
            ->pluck('employee_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Net pay of payslips on hold for this run's month: not paid out yet. */
    private function heldNetPay(PayrollRun $run): float
    {
        $held = 0.00;

        foreach ($this->runEmployees($run) as $employee) {
            $isHeld = PayrollHold::where('employee_id', $employee->id)
                ->where('payroll_month', $run->payroll_month)
                ->where('status', 'on_hold')
                ->exists();

            if ($isHeld) {
                $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month, $run);
                $held += max(0.0, (float) ($calc['summary']['net_payout'] ?? 0.00));
            }
        }

        return round($held, 2);
    }

    private function monthEnd(PayrollRun $run): string
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $run->payroll_month)->endOfMonth()->toDateString();
        } catch (\Throwable) {
            return Carbon::parse($run->end_date ?? now())->toDateString();
        }
    }

    /**
     * Employees this run pays: its own list, else its pay group / everyone on
     * a salary structure, minus those already paid by another run this month.
     */
    private function runEmployees(PayrollRun $run): Collection
    {
        $excludeEmployeeIds = [];
        if (empty($run->employee_ids)) {
            $otherRuns = PayrollRun::where('payroll_month', $run->payroll_month)
                ->where('id', '!=', $run->id)
                ->get();

            foreach ($otherRuns as $or) {
                if ($or->employee_ids && count($or->employee_ids) > 0) {
                    $excludeEmployeeIds = array_merge($excludeEmployeeIds, $or->employee_ids);
                } elseif ($or->pay_group_id) {
                    $pgEmpIds = Employee::where('pay_group_id', $or->pay_group_id)->pluck('id')->toArray();
                    $excludeEmployeeIds = array_merge($excludeEmployeeIds, $pgEmpIds);
                } else {
                    $genEmpIds = Employee::where('status', true)
                        ->where(function ($q) {
                            $q->whereNotNull('pay_group_id')->orWhereNotNull('salary_structure_id');
                        })
                        ->pluck('id')->toArray();
                    $excludeEmployeeIds = array_merge($excludeEmployeeIds, $genEmpIds);
                }
            }
            $excludeEmployeeIds = array_unique($excludeEmployeeIds);
        }

        if ($run->employee_ids && count($run->employee_ids) > 0) {
            return Employee::whereIn('id', $run->employee_ids)
                ->whereNotIn('id', $excludeEmployeeIds)
                ->where('status', true)
                ->where(function ($q) {
                    $q->whereNotNull('pay_group_id')->orWhereNotNull('salary_structure_id');
                })
                ->get();
        }

        $employeesQuery = Employee::where('status', true)
            ->where(function ($q) {
                $q->whereNotNull('pay_group_id')->orWhereNotNull('salary_structure_id');
            })
            ->where(function ($q) use ($run) {
                $q->whereNull('date_of_joining')->orWhere('date_of_joining', '<=', $run->end_date);
            })
            ->whereNotIn('id', $excludeEmployeeIds);

        if ($run->pay_group_id) {
            $employeesQuery->where('pay_group_id', $run->pay_group_id);
        } else {
            $otherProcessedPayGroupIds = PayrollRun::where('payroll_month', $run->payroll_month)
                ->where('id', '!=', $run->id)
                ->whereNotNull('pay_group_id')
                ->pluck('pay_group_id')
                ->toArray();

            if (!empty($otherProcessedPayGroupIds)) {
                $employeesQuery->where(function ($sub) use ($otherProcessedPayGroupIds) {
                    $sub->whereNotIn('pay_group_id', $otherProcessedPayGroupIds)
                        ->orWhereNull('pay_group_id');
                });
            }
        }

        return $employeesQuery->get();
    }

    /**
     * Helper method to get existing Chart of Account or create it on-the-fly.
     */
    private function getOrCreateAccount(int $tenantId, string $code, string $name, string $type, string $balance, string $subtype): ChartOfAccount
    {
        return ChartOfAccount::where('tenant_id', $tenantId)->where('code', $code)->first()
            ?? ChartOfAccount::create([
                'tenant_id'      => $tenantId,
                'code'           => $code,
                'system_key'     => SystemAccount::keyForTemplateCode($code),
                'name'           => $name,
                'type'           => $type,
                'subtype'        => $subtype,
                'normal_balance' => $balance,
                'is_active'      => true,
            ]);
    }
}
