<?php

namespace App\Domains\Accounting\Services\Tds;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\TdsChallan;
use App\Domains\Accounting\Models\TdsDeduction;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Depositing TDS (Tally's "Stat Payment"): the chosen deductions are linked
 * to the challan and the journal clears each section's payable ledger,
 *   Dr TDS Payable - <section>   (tax)
 *   Dr Other Expenses            (interest + late fee, not deductible)
 *     Cr Bank
 */
class TdsChallanService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly TdsSectionService $sections,
        private readonly SystemAccountService $systemAccounts,
    ) {
    }

    /**
     * @param array{
     *     tenant_id: int, challan_number: string, bsr_code?: ?string, cin?: ?string,
     *     deposit_date: string, period_month?: ?string, interest?: float, late_fee?: float,
     *     bank_account_id: int, deduction_ids: list<int>, notes?: ?string, created_by?: ?int,
     *     already_paid?: bool
     * } $data  already_paid: the payment is already in the books (payroll statutory dues); record the challan only
     */
    public function deposit(array $data): TdsChallan
    {
        $tenantId = (int) $data['tenant_id'];
        $ids = array_values(array_unique(array_map('intval', $data['deduction_ids'] ?? [])));

        if ($ids === []) {
            throw new InvalidArgumentException('Choose the deductions this challan pays.');
        }

        $bank = ChartOfAccount::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereKey($data['bank_account_id'])->where('is_cash_or_bank', true)->first();

        if ($bank === null) {
            throw new InvalidArgumentException('Choose the bank ledger the tax was paid from.');
        }

        return DB::transaction(function () use ($data, $tenantId, $ids, $bank) {
            $deductions = TdsDeduction::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->whereIn('id', $ids)->undeposited()->with('section')->lockForUpdate()->get();

            if ($deductions->count() !== count($ids)) {
                throw new InvalidArgumentException('Some of the chosen deductions are already deposited or no longer exist.');
            }

            $interest = round((float) ($data['interest'] ?? 0), 2);
            $lateFee = round((float) ($data['late_fee'] ?? 0), 2);
            $tax = round((float) $deductions->sum('tds_amount'), 2);

            $challan = TdsChallan::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'challan_number' => $data['challan_number'],
                'bsr_code' => $data['bsr_code'] ?? null,
                'cin' => $data['cin'] ?? null,
                'deposit_date' => $data['deposit_date'],
                'period_month' => $data['period_month'] ?? substr((string) $deductions->min('deduction_date')?->format('Y-m'), 0, 7),
                'tax_amount' => $tax,
                'interest' => $interest,
                'late_fee' => $lateFee,
                'bank_account_id' => $bank->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            // Salary TDS often already left the bank through Payroll > Statutory
            // dues (Dr TDS Payable / Cr Bank). Then the challan only records the
            // deposit details for 24Q; posting again would pay it twice.
            if (! empty($data['already_paid'])) {
                TdsDeduction::query()->withoutGlobalScopes()->whereIn('id', $ids)->update(['tds_challan_id' => $challan->id]);

                return $challan->fresh('deductions');
            }

            $lines = [];
            foreach ($deductions->groupBy('tds_section_id') as $rows) {
                $section = $rows->first()->section;
                $lines[] = [
                    'chart_of_account_id' => $this->sections->payableAccount($section)->id,
                    'debit' => round((float) $rows->sum('tds_amount'), 2),
                    'credit' => 0,
                    'description' => "TDS {$section->code} deposited - challan {$challan->challan_number}",
                ];
            }

            if ($interest + $lateFee > 0) {
                $expense = $this->systemAccounts->require(SystemAccount::OTHER_EXPENSE, $tenantId);
                $lines[] = [
                    'chart_of_account_id' => $expense->id,
                    'debit' => round($interest + $lateFee, 2),
                    'credit' => 0,
                    'description' => "Interest / late fee on TDS - challan {$challan->challan_number}",
                ];
            }

            $lines[] = [
                'chart_of_account_id' => $bank->id,
                'debit' => 0,
                'credit' => $challan->totalAmount(),
                'description' => "TDS challan {$challan->challan_number}",
            ];

            $journal = $this->journals->postOnce($lines, [
                'tenant_id' => $tenantId,
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'journal_date' => $data['deposit_date'],
                'source' => Journal::SOURCE_MANUAL,
                'journal_number_prefix' => 'TDS',
                'reference_type' => 'tds_challan',
                'reference_id' => $challan->id,
                'memo' => "TDS deposited - challan {$challan->challan_number}",
                'posted_by' => $data['created_by'] ?? null,
            ]);

            $challan->forceFill(['journal_id' => $journal->id])->save();
            TdsDeduction::query()->withoutGlobalScopes()->whereIn('id', $ids)->update(['tds_challan_id' => $challan->id]);

            return $challan->fresh('deductions');
        });
    }
}
