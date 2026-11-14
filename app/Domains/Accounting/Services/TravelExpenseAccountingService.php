<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\ExpenseReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TravelExpenseAccountingService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    /**
     * Post Cash/Bank disbursement journal entry when cash advance is released.
     */
    public function postCashAdvanceDisbursementJournal(CashAdvance $cashAdvance, ?int $bankAccountId = null, ?string $paymentDate = null): ?Journal
    {
        $tenantId = (int) ($cashAdvance->tenant_id ?? current_tenant_id() ?? require_tenant_id());

        // Standing posting only: once reversed, the document can post again.
        $existingJournal = $this->journalService->activePosting($tenantId, 'CashAdvance', $cashAdvance->id);

        if ($existingJournal) {
            return $existingJournal;
        }

        $advancesAccount = ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1420')->first()
            ?? ChartOfAccount::create([
                'tenant_id'      => $tenantId,
                'code'           => '1420',
                'name'           => 'Advance to Employees',
                'type'           => ChartOfAccount::TYPE_ASSET,
                'subtype'        => ChartOfAccount::SUBTYPE_LOANS_ADVANCES,
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_active'      => true,
            ]);

        $bankAccount = $this->payingLedger($tenantId, $bankAccountId);

        $amount = round((float) ($cashAdvance->approved_amount ?? $cashAdvance->amount), 2);
        if ($amount <= 0) {
            return null;
        }

        $payoutDate = $paymentDate ? Carbon::parse($paymentDate)->toDateString() : now()->toDateString();
        $this->assertCashCovers($bankAccount, $amount, $payoutDate);

        $lines = [
            [
                'chart_of_account_id' => $advancesAccount->id,
                'debit'               => $amount,
                'credit'              => 0.00,
                'party_type'          => \App\Domains\Accounting\Models\JournalEntry::PARTY_EMPLOYEE,
                'party_id'            => $cashAdvance->employee_id,
                'description'         => "Disbursement of Advance: " . $cashAdvance->purpose,
            ],
            [
                'chart_of_account_id' => $bankAccount->id,
                'debit'               => 0.00,
                'credit'              => $amount,
                'description'         => "Disbursement of Advance from {$bankAccount->name}: " . $cashAdvance->purpose,
            ],
        ];

        try {
            return $this->journalService->postOnce($lines, [
                'tenant_id'      => $tenantId,
                'journal_date'   => $payoutDate,
                'source'         => 'expense',
                'reference_type' => 'CashAdvance',
                'reference_id'   => $cashAdvance->id,
                'memo'           => "Disbursed Cash Advance: " . $cashAdvance->purpose . " from {$bankAccount->code} {$bankAccount->name}",
                'posted_by'      => auth()->id(),
                // The advance is a bill the employee owes back (or settles with a claim).
                'bill_allocations' => $cashAdvance->employee_id
                    ? [BillAllocationService::newRef('receivable', 'ADV-' . $cashAdvance->id, Carbon::parse($payoutDate), Carbon::parse($payoutDate), 'CashAdvance', (int) $cashAdvance->id)]
                    : [],
            ]);
        } catch (\Throwable $e) {
            Log::error("Disbursement Journal Posting Failed: " . $e->getMessage(), [
                'cash_advance_id' => $cashAdvance->id,
            ]);
            throw $e;
        }
    }

    /**
     * Post journal entry when expense report is paid out / settled.
     */
    public function postExpenseReportPayoutJournal(ExpenseReport $expenseReport, ?int $bankAccountId = null, ?string $paymentDate = null): ?Journal
    {
        $tenantId = (int) ($expenseReport->tenant_id ?? current_tenant_id() ?? require_tenant_id());

        // Standing posting only: once reversed, the document can post again.
        $existingJournal = $this->journalService->activePosting($tenantId, 'ExpenseReport', $expenseReport->id);

        if ($existingJournal) {
            return $existingJournal;
        }

        $expenseAccount = ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5300')->first()
            ?? ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5900')->first()
            ?? ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5100')->first()
            ?? ChartOfAccount::create([
                'tenant_id'      => $tenantId,
                'code'           => '5300',
                'name'           => 'Travel & Expenses',
                'type'           => ChartOfAccount::TYPE_EXPENSE,
                'subtype'        => ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE,
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_active'      => true,
            ]);

        $advancesAccount = ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1420')->first()
            ?? ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1400')->first()
            ?? ChartOfAccount::create([
                'tenant_id'      => $tenantId,
                'code'           => '1420',
                'name'           => 'Advance to Employees',
                'type'           => ChartOfAccount::TYPE_ASSET,
                'subtype'        => ChartOfAccount::SUBTYPE_LOANS_ADVANCES,
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_active'      => true,
            ]);

        $bankAccount = $this->payingLedger($tenantId, $bankAccountId);

        $advanceAdjusted = round((float) ($expenseReport->advance_adjusted ?? 0.00), 2);
        $approvedNet = round((float) ($expenseReport->approved_net_reimbursement ?? 0.00), 2);

        // Strict double-entry balance: debits MUST always match credits exactly
        if ($approvedNet >= 0) {
            if ($approvedNet === 0.00 && $advanceAdjusted === 0.00) {
                $spentAmount = round((float) ($expenseReport->approved_amount ?? $expenseReport->total_amount ?? 0.00), 2);
                $approvedNet = $spentAmount;
            } else {
                $spentAmount = round($advanceAdjusted + $approvedNet, 2);
            }
        } else {
            $spentAmount = max(round($advanceAdjusted - abs($approvedNet), 2), 0.00);
        }

        $payoutDate = $paymentDate ? Carbon::parse($paymentDate)->toDateString() : now()->toDateString();
        if ($approvedNet > 0) {
            $this->assertCashCovers($bankAccount, $approvedNet, $payoutDate);
        }

        $lines = [];

        if ($spentAmount > 0) {
            $lines[] = [
                'chart_of_account_id' => $expenseAccount->id,
                'debit'               => $spentAmount,
                'credit'              => 0.00,
                'description'         => "Expense Claim Payout: " . $expenseReport->title,
            ];
        }

        if ($advanceAdjusted > 0) {
            $lines[] = [
                'chart_of_account_id' => $advancesAccount->id,
                'debit'               => 0.00,
                'credit'              => $advanceAdjusted,
                'party_type'          => \App\Domains\Accounting\Models\JournalEntry::PARTY_EMPLOYEE,
                'party_id'            => $expenseReport->employee_id,
                'description'         => "Clear Advance for Claim: " . $expenseReport->title,
            ];
        }

        if ($approvedNet > 0) {
            $lines[] = [
                'chart_of_account_id' => $bankAccount->id,
                'debit'               => 0.00,
                'credit'              => $approvedNet,
                'description'         => "Payout for Claim from {$bankAccount->name}: " . $expenseReport->title,
            ];
        } elseif ($approvedNet < 0) {
            $lines[] = [
                'chart_of_account_id' => $bankAccount->id,
                'debit'               => abs($approvedNet),
                'credit'              => 0.00,
                'description'         => "Advance surplus recovered into {$bankAccount->name} for Claim: " . $expenseReport->title,
            ];
        }

        if (empty($lines) || count($lines) < 2) {
            return null;
        }

        // The advance cleared by this claim settles the employee's ADV bill:
        // advances linked to this claim or its trip first, then their oldest.
        $billHints = [];
        if ($advanceAdjusted > 0 && $expenseReport->employee_id) {
            $employeeHint = [
                'party_type' => \App\Domains\Accounting\Models\JournalEntry::PARTY_EMPLOYEE,
                'party_id'   => (int) $expenseReport->employee_id,
                'account_id' => $advancesAccount->id,
            ];
            $tripAdvanceIds = CashAdvance::where('tenant_id', $tenantId)
                ->where('employee_id', $expenseReport->employee_id)
                ->where(function ($q) use ($expenseReport) {
                    $q->where('expense_report_id', $expenseReport->id);
                    if ($expenseReport->travel_request_id) {
                        $q->orWhere('travel_request_id', $expenseReport->travel_request_id);
                    }
                })
                ->orderBy('id')
                ->pluck('id');
            foreach ($tripAdvanceIds as $advanceId) {
                $billHints[] = BillAllocationService::againstRef('receivable', 'CashAdvance', (int) $advanceId) + $employeeHint;
            }
            $billHints[] = BillAllocationService::againstOldest('receivable') + $employeeHint;
        }

        try {
            return $this->journalService->postOnce($lines, [
                'tenant_id'      => $tenantId,
                'journal_date'   => $payoutDate,
                'source'         => 'expense',
                'reference_type' => 'ExpenseReport',
                'reference_id'   => $expenseReport->id,
                'memo'           => "Paid Expense Claim Portion: " . $expenseReport->title . " from {$bankAccount->code} {$bankAccount->name}",
                'posted_by'      => auth()->id(),
                'bill_allocations' => $billHints,
            ]);
        } catch (\Throwable $e) {
            Log::error("Payout Journal Posting Failed: " . $e->getMessage(), [
                'expense_report_id' => $expenseReport->id,
            ]);
            throw $e;
        }
    }

    /**
     * Resolve paying ledger (bank or cash account).
     */
    public function payingLedger(int $tenantId, ?int $accountId): ChartOfAccount
    {
        if ($accountId) {
            $account = ChartOfAccount::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($accountId)
                ->where('is_cash_or_bank', true)
                ->first();

            if ($account) {
                return $account;
            }
        }

        return app(SystemAccountService::class)->get(SystemAccount::BANK, $tenantId)
            ?? ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1020')->first()
            ?? ChartOfAccount::where('tenant_id', $tenantId)->where('is_cash_or_bank', true)->first()
            ?? ChartOfAccount::create([
                'tenant_id'      => $tenantId,
                'code'           => '1020',
                'name'           => 'Bank Account',
                'type'           => ChartOfAccount::TYPE_ASSET,
                'subtype'        => ChartOfAccount::SUBTYPE_CURRENT_ASSET,
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_cash_or_bank'=> true,
                'is_active'      => true,
            ]);
    }

    /**
     * Physical cash can't go below zero. Bank ledgers may (overdraft is normal).
     */
    private function assertCashCovers(ChartOfAccount $account, float $amount, string $date): void
    {
        $name = strtolower((string) $account->name);
        $isCash = ($account->system_key === 'cash' || $account->code === '1010' || (str_contains($name, 'cash') && !str_contains($name, 'bank') && !str_contains($name, 'credit')));
        if (!$isCash) {
            return;
        }

        $payrollBooks = app(\App\Domains\Accounting\Services\PayrollAccountingService::class);
        $available = $payrollBooks->balance($account, $date);

        if ($amount - $available > 0.009) {
            throw new InvalidArgumentException(sprintf(
                '%s %s has only %s on %s; it cannot pay %s. Pay from a bank account, or record the cash received first.',
                $account->code, $account->name, number_format(max($available, 0), 2), Carbon::parse($date)->format('d M Y'), number_format($amount, 2),
            ));
        }
    }
}
