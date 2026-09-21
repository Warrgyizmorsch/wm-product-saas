<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\ExpenseReport;
use Illuminate\Support\Facades\Log;

class TravelExpenseAccountingService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    /**
     * Post Cash/Bank disbursement journal entry when cash advance is released.
     */
    public function postCashAdvanceDisbursementJournal(CashAdvance $cashAdvance): ?Journal
    {
        $tenantId = $cashAdvance->tenant_id ?? current_tenant_id() ?? require_tenant_id();

        $existingJournal = Journal::where('tenant_id', $tenantId)
            ->where('reference_type', 'CashAdvance')
            ->where('reference_id', $cashAdvance->id)
            ->first();

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

        $bankAccount = ChartOfAccount::where('tenant_id', $tenantId)->where('is_cash_or_bank', true)->first()
            ?? ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1020')->first()
            ?? ChartOfAccount::create([
                'tenant_id'      => $tenantId,
                'code'           => '1020',
                'name'           => 'Bank Account',
                'type'           => ChartOfAccount::TYPE_ASSET,
                'subtype'        => ChartOfAccount::SUBTYPE_CURRENT_ASSET,
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_active'      => true,
            ]);

        $amount = round((float) ($cashAdvance->approved_amount ?? $cashAdvance->amount), 2);
        if ($amount <= 0) {
            return null;
        }

        $lines = [
            [
                'chart_of_account_id' => $advancesAccount->id,
                'debit'               => $amount,
                'credit'              => 0.00,
                'description'         => "Disbursement of Advance: " . $cashAdvance->purpose,
            ],
            [
                'chart_of_account_id' => $bankAccount->id,
                'debit'               => 0.00,
                'credit'              => $amount,
                'description'         => "Disbursement of Advance: " . $cashAdvance->purpose,
            ],
        ];

        try {
            return $this->journalService->post($lines, [
                'tenant_id'      => $tenantId,
                'journal_date'   => now()->format('Y-m-d'),
                'source'         => 'expense',
                'reference_type' => 'CashAdvance',
                'reference_id'   => $cashAdvance->id,
                'memo'           => "Disbursed Cash Advance: " . $cashAdvance->purpose,
                'posted_by'      => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Disbursement Journal Posting Failed: " . $e->getMessage(), [
                'cash_advance_id' => $cashAdvance->id,
            ]);
            return null;
        }
    }

    /**
     * Post journal entry when expense report is paid out / settled.
     */
    public function postExpenseReportPayoutJournal(ExpenseReport $expenseReport): ?Journal
    {
        $tenantId = $expenseReport->tenant_id ?? current_tenant_id() ?? require_tenant_id();

        $existingJournal = Journal::where('tenant_id', $tenantId)
            ->where('reference_type', 'ExpenseReport')
            ->where('reference_id', $expenseReport->id)
            ->first();

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

        $bankAccount = ChartOfAccount::where('tenant_id', $tenantId)->where('is_cash_or_bank', true)->first()
            ?? ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1020')->first()
            ?? ChartOfAccount::create([
                'tenant_id'      => $tenantId,
                'code'           => '1020',
                'name'           => 'Bank Account',
                'type'           => ChartOfAccount::TYPE_ASSET,
                'subtype'        => ChartOfAccount::SUBTYPE_CURRENT_ASSET,
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_active'      => true,
            ]);

        $spentAmount = round((float) ($expenseReport->approved_amount ?? $expenseReport->total_amount), 2);
        $advanceAdjusted = round((float) ($expenseReport->advance_adjusted ?? 0.00), 2);
        $approvedNet = round((float) ($expenseReport->approved_net_reimbursement ?? ($spentAmount - $advanceAdjusted)), 2);

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
                'description'         => "Clear Advance for Claim: " . $expenseReport->title,
            ];
        }

        if ($approvedNet > 0) {
            $lines[] = [
                'chart_of_account_id' => $bankAccount->id,
                'debit'               => 0.00,
                'credit'              => $approvedNet,
                'description'         => "Payout for Claim: " . $expenseReport->title,
            ];
        } elseif ($approvedNet < 0) {
            $lines[] = [
                'chart_of_account_id' => $bankAccount->id,
                'debit'               => abs($approvedNet),
                'credit'              => 0.00,
                'description'         => "Advance surplus recovered for Claim: " . $expenseReport->title,
            ];
        }

        if (empty($lines)) {
            return null;
        }

        try {
            return $this->journalService->post($lines, [
                'tenant_id'      => $tenantId,
                'journal_date'   => now()->format('Y-m-d'),
                'source'         => 'expense',
                'reference_type' => 'ExpenseReport',
                'reference_id'   => $expenseReport->id,
                'memo'           => "Paid Expense Claim Portion: " . $expenseReport->title,
                'posted_by'      => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Payout Journal Posting Failed: " . $e->getMessage(), [
                'expense_report_id' => $expenseReport->id,
            ]);
            return null;
        }
    }
}
