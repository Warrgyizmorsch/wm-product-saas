<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\Journal;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Services\PayrollCalculationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollAccountingService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly PayrollCalculationService $payrollCalculationService
    ) {}

    /**
     * Post balanced Double-Entry Accounting Journal for a completed Payroll Run.
     *
     * @param PayrollRun $run
     * @return Journal|null
     */
    public function postPayrollRunJournal(PayrollRun $run): ?Journal
    {
        // Fail closed rather than silently posting into tenant 1's ledger if
        // both the run's own tenant_id and the bound request context are
        // somehow unresolvable.
        $tenantId = $run->tenant_id ?? require_tenant_id();

        // Check if journal already posted for this payroll run to prevent duplicate entries
        $existingJournal = Journal::where('tenant_id', $tenantId)
            ->where('reference_type', 'PayrollRun')
            ->where('reference_id', $run->id)
            ->first();

        if ($existingJournal) {
            return $existingJournal;
        }

        // Exclude employees already processed in other runs for the same month
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
                }
            }
            $excludeEmployeeIds = array_unique($excludeEmployeeIds);
        }

        if ($run->employee_ids && count($run->employee_ids) > 0) {
            $employees = Employee::whereIn('id', $run->employee_ids)
                ->whereNotIn('id', $excludeEmployeeIds)
                ->where('status', true)
                ->whereNotNull('pay_group_id')
                ->get();
        } else {
            $employeesQuery = Employee::where('status', true)
                ->whereNotNull('pay_group_id')
                ->whereNotIn('id', $excludeEmployeeIds);

            if ($run->pay_group_id) {
                $employeesQuery->where('pay_group_id', $run->pay_group_id);
            }

            $employees = $employeesQuery->get();
        }

        if ($employees->isEmpty()) {
            return null;
        }

        // Auto-resolve or create default fallback COA accounts by code
        $defaultAccounts = [
            '5100' => $this->getOrCreateAccount($tenantId, '5100', 'Salary & Wages - Staff', ChartOfAccount::TYPE_EXPENSE, ChartOfAccount::BALANCE_DEBIT, ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE),
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

        foreach ($employees as $employee) {
            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
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
                    $codeUpper = strtoupper($code);
                    $nameUpper = strtoupper($componentModel?->name ?? $itemData['name'] ?? '');

                    if ($codeUpper === 'LOP' || str_contains($codeUpper, 'LOSS_OF_PAY') || str_contains($nameUpper, 'LOSS OF PAY')) {
                        $empLop += $val;
                        continue; // LOP reduces earning expense; do not credit LOP as a liability!
                    }

                    // Statutory & other deductions (PF, ESI, TDS, PT, Advance, etc.)
                    $accId = null;

                    if (str_contains($codeUpper, 'PF') || str_contains($codeUpper, 'PROVIDENT') || str_contains($nameUpper, 'PROVIDENT') || str_contains($nameUpper, 'PF')) {
                        $accId = $defaultAccounts['2160']->id;
                    } elseif (str_contains($codeUpper, 'ESI') || str_contains($nameUpper, 'ESI') || str_contains($nameUpper, 'STATE INSURANCE')) {
                        $accId = $defaultAccounts['2170']->id;
                    } elseif (str_contains($codeUpper, 'TDS') || str_contains($codeUpper, 'TAX') || str_contains($nameUpper, 'TDS') || str_contains($nameUpper, 'INCOME TAX') || str_contains($nameUpper, 'TAX DEDUCTION')) {
                        $accId = $defaultAccounts['2140']->id;
                    } elseif (str_contains($codeUpper, 'PT') || str_contains($codeUpper, 'PROFTAX') || str_contains($nameUpper, 'PROFESSIONAL TAX')) {
                        $accId = $defaultAccounts['2180']->id;
                    } elseif (str_contains($codeUpper, 'ADVANCE') || str_contains($codeUpper, 'LOAN') || str_contains($nameUpper, 'ADVANCE') || str_contains($nameUpper, 'LOAN')) {
                        $accId = $defaultAccounts['1420']->id;
                    } else {
                        // General fallback for any custom deduction component
                        $accId = $defaultAccounts['2080']->id;
                    }

                    if ($accId) {
                        $creditGroup[$accId] = ($creditGroup[$accId] ?? 0.00) + $val;
                    }
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

            $totalNetPayout += (float) ($calc['summary']['net_payout'] ?? 0.00);
        }

        // Add Net Salary Payable liability credit
        if ($totalNetPayout > 0) {
            $creditGroup[$salaryPayableAccount->id] = ($creditGroup[$salaryPayableAccount->id] ?? 0.00) + $totalNetPayout;
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

        $journalDate = $run->payroll_month . '-28';
        try {
            $journalDate = Carbon::parse($journalDate)->format('Y-m-d');
        } catch (\Throwable $e) {
            $journalDate = now()->format('Y-m-d');
        }

        $this->ensureOpenPeriodForDate($tenantId, $journalDate);

        $meta = [
            'tenant_id'             => $tenantId,
            'company_id'            => $run->company_id ?? require_company_id(),
            'journal_date'          => $journalDate,
            'source'                => 'payroll',
            'voucher_type'          => 'payroll',
            'journal_number_prefix' => 'PAY',
            'reference_type'        => 'PayrollRun',
            'reference_id'          => $run->id,
            'memo'                  => "Payroll Journal entry for month {$run->payroll_month} (Run ID: {$run->id})",
            // Null when run without a signed-in user (queue/console) — shown as "System",
            // rather than crediting user #1, who may not even belong to this tenant.
            'posted_by'             => auth()->id(),
        ];

        return $this->journalService->post($lines, $meta);
    }

    /**
     * Post Cash/Bank payout journal entry when payroll payout is released.
     */
    public function postPayrollPayoutJournal(PayrollRun $run): ?Journal
    {
        // Fail closed rather than silently posting into tenant 1's ledger if
        // both the run's own tenant_id and the bound request context are
        // somehow unresolvable.
        $tenantId = $run->tenant_id ?? require_tenant_id();

        $existingJournal = Journal::where('tenant_id', $tenantId)
            ->where('reference_type', 'PayrollRunPayout')
            ->where('reference_id', $run->id)
            ->first();

        if ($existingJournal) {
            return $existingJournal;
        }

        // Find Salary Payable account (2030) and Bank Account (1020)
        $salaryPayableAccount = $this->getOrCreateAccount($tenantId, '2030', 'Salary Payable', ChartOfAccount::TYPE_LIABILITY, ChartOfAccount::BALANCE_CREDIT, ChartOfAccount::SUBTYPE_CURRENT_LIABILITY);
        $bankAccount = ChartOfAccount::where('tenant_id', $tenantId)->where('is_cash_or_bank', true)->first()
            ?? $this->getOrCreateAccount($tenantId, '1020', 'Bank Account', ChartOfAccount::TYPE_ASSET, ChartOfAccount::BALANCE_DEBIT, ChartOfAccount::SUBTYPE_CURRENT_ASSET);

        // Calculate total net payout for run
        $totalNetPayout = 0.00;
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
                }
            }
            $excludeEmployeeIds = array_unique($excludeEmployeeIds);
        }

        if ($run->employee_ids && count($run->employee_ids) > 0) {
            $employees = Employee::whereIn('id', $run->employee_ids)
                ->whereNotIn('id', $excludeEmployeeIds)
                ->where('status', true)
                ->get();
        } else {
            $employeesQuery = Employee::where('status', true)->whereNotIn('id', $excludeEmployeeIds);
            if ($run->pay_group_id) {
                $employeesQuery->where('pay_group_id', $run->pay_group_id);
            }
            $employees = $employeesQuery->get();
        }

        foreach ($employees as $employee) {
            $calc = $this->payrollCalculationService->calculateSalary($employee, $run->payroll_month);
            $totalNetPayout += (float) ($calc['summary']['net_payout'] ?? 0.00);
        }

        $totalNetPayout = round($totalNetPayout, 2);
        if ($totalNetPayout <= 0) {
            return null;
        }

        $lines = [
            [
                'chart_of_account_id' => $salaryPayableAccount->id,
                'debit'               => $totalNetPayout,
                'credit'              => 0.00,
                'description'         => "Net salary payout release for month {$run->payroll_month}",
            ],
            [
                'chart_of_account_id' => $bankAccount->id,
                'debit'               => 0.00,
                'credit'              => $totalNetPayout,
                'description'         => "Bank transfer disbursement for month {$run->payroll_month}",
            ]
        ];

        $payoutDate = now()->format('Y-m-d');
        $this->ensureOpenPeriodForDate($tenantId, $payoutDate);

        $meta = [
            'tenant_id'             => $tenantId,
            'company_id'            => $run->company_id ?? require_company_id(),
            'journal_date'          => $payoutDate,
            'source'                => 'payroll',
            'voucher_type'          => 'payment',
            'journal_number_prefix' => 'PAY-DISB',
            'reference_type'        => 'PayrollRunPayout',
            'reference_id'          => $run->id,
            'memo'                  => "Bank salary disbursement for month {$run->payroll_month} (Run ID: {$run->id})",
            'posted_by'             => auth()->id(),
        ];

        return $this->journalService->post($lines, $meta);
    }

    /**
     * Helper method to ensure an open accounting period exists for a given date.
     */
    private function ensureOpenPeriodForDate(int $tenantId, string $dateStr): void
    {
        $date = Carbon::parse($dateStr);
        $period = AccountingPeriod::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if (!$period) {
            $yearStart = $date->copy()->startOfYear();
            $yearEnd = $date->copy()->endOfYear();

            $fiscalYear = FiscalYear::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => 'FY ' . $yearStart->format('Y')],
                [
                    'start_date' => $yearStart->format('Y-m-d'),
                    'end_date'   => $yearEnd->format('Y-m-d'),
                    'status'     => FiscalYear::STATUS_OPEN,
                ]
            );

            AccountingPeriod::firstOrCreate(
                [
                    'tenant_id'  => $tenantId,
                    'start_date' => $date->copy()->startOfMonth()->format('Y-m-d'),
                ],
                [
                    'fiscal_year_id' => $fiscalYear->id,
                    'name'           => $date->format('F Y'),
                    'end_date'       => $date->copy()->endOfMonth()->format('Y-m-d'),
                    'status'         => AccountingPeriod::STATUS_OPEN,
                ]
            );
        }
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
                'name'           => $name,
                'type'           => $type,
                'subtype'        => $subtype,
                'normal_balance' => $balance,
                'is_active'      => true,
            ]);
    }
}