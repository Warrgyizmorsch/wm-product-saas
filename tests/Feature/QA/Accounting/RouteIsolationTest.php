<?php

namespace Tests\Feature\QA\Accounting;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\QA\Concerns\BuildsQaTenants;
use Tests\TestCase;

/**
 * Tenant B's owner holds every accounting permission inside B. With a guessed
 * id they must not be able to read or change any of tenant A's books.
 */
class RouteIsolationTest extends TestCase
{
    use BuildsQaTenants;
    use RefreshDatabase;

    private User $accountantA;

    private User $ownerA;

    private User $ownerB;

    private Journal $journalA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTwoTenants(withBooks: true);

        $this->accountantA = $this->userWithRole($this->tenantA, 'accountant');
        $this->ownerA = $this->userWithRole($this->tenantA, 'tenant_owner');
        $this->ownerB = $this->userWithRole($this->tenantB, 'tenant_owner');

        $this->as($this->accountantA, $this->tenantA)->post(route('accounting.vouchers.receipt.store'), [
            'voucher_date' => now()->toDateString(),
            'memo' => 'A capital',
            'items' => [
                ['chart_of_account_id' => $this->accountFor($this->tenantA, '1020')->id, 'debit' => 75000, 'credit' => 0],
                ['chart_of_account_id' => $this->accountFor($this->tenantA, '3010')->id, 'debit' => 0, 'credit' => 75000],
            ],
        ])->assertSessionHasNoErrors();

        $this->journalA = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->latest('id')->firstOrFail();
    }

    private function asB(): static
    {
        return $this->as($this->ownerB, $this->tenantB);
    }

    public function test_another_tenants_journal_cannot_be_viewed_or_reversed(): void
    {
        $this->assertDenied($this->asB()->get(route('accounting.journals.show', $this->journalA->id)), 'journal show');
        $this->assertDenied($this->asB()->get(route('accounting.vouchers.receipt.show', $this->journalA->id)), 'voucher show');
        $this->assertDenied($this->asB()->post(route('accounting.journals.reverse', $this->journalA->id), ['reason' => 'x']), 'journal reverse');
        $this->assertDenied($this->asB()->post(route('accounting.vouchers.receipt.reverse', $this->journalA->id), ['reason' => 'x']), 'voucher reverse');
        $this->assertDenied($this->asB()->post(route('accounting.approvals.approve', $this->journalA->id)), 'approve');
        $this->assertDenied($this->asB()->post(route('accounting.approvals.reject', $this->journalA->id), ['reason' => 'x']), 'reject');

        $this->assertSame(Journal::STATUS_POSTED, $this->journalA->fresh()->status);
        $this->assertSame(0, Journal::withoutGlobalScopes()->where('tenant_id', $this->tenantB->id)->count(), 'A reversal was booked into tenant B');
    }

    public function test_another_tenants_journal_never_appears_in_lists_or_reports(): void
    {
        $this->asB()->get(route('accounting.journals.index'))->assertOk()->assertDontSee($this->journalA->journal_number);
        $this->asB()->get(route('accounting.vouchers.receipt.index'))->assertOk()->assertDontSee($this->journalA->journal_number);
        $this->asB()->get(route('accounting.reports.day-book', ['date' => now()->toDateString()]))->assertOk()->assertDontSee($this->journalA->journal_number);
        $this->asB()->get(route('accounting.reports.trial-balance'))->assertOk()->assertDontSee('75,000')->assertDontSee('75000');
        $this->asB()->get(route('accounting.reports.balance-sheet'))->assertOk()->assertDontSee('75,000');
    }

    public function test_another_tenants_ledger_masters_cannot_be_changed_or_deleted(): void
    {
        $ownCustom = $this->accountFor($this->tenantA, '5200');
        $cc = CostCenter::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'code' => 'A-CC', 'name' => 'A Plant', 'is_active' => true]);
        $tax = TaxRate::withoutGlobalScopes()->create(['tenant_id' => $this->tenantA->id, 'name' => 'A GST', 'type' => 'gst', 'rate' => 18, 'is_compound' => false]);

        $this->assertDenied($this->asB()->put(route('accounting.chart-of-accounts.update', $ownCustom->id), [
            'code' => '5200', 'name' => 'Hijacked', 'type' => $ownCustom->type, 'normal_balance' => $ownCustom->normal_balance,
        ]), 'coa update');
        $this->assertDenied($this->asB()->delete(route('accounting.chart-of-accounts.destroy', $ownCustom->id)), 'coa delete');
        $this->assertDenied($this->asB()->put(route('accounting.cost-centers.update', $cc->id), ['code' => 'X', 'name' => 'Hijacked']), 'cc update');
        $this->assertDenied($this->asB()->delete(route('accounting.cost-centers.destroy', $cc->id)), 'cc delete');
        $this->assertDenied($this->asB()->put(route('accounting.tax-rates.update', $tax->id), ['name' => 'Hijacked', 'type' => 'gst', 'rate' => 0]), 'tax update');
        $this->assertDenied($this->asB()->delete(route('accounting.tax-rates.destroy', $tax->id)), 'tax delete');

        $this->assertNotSame('Hijacked', $ownCustom->fresh()->name);
        $this->assertNotNull(CostCenter::withoutGlobalScopes()->find($cc->id));
        $this->assertEquals(18, (float) TaxRate::withoutGlobalScopes()->find($tax->id)?->rate);
    }

    public function test_another_tenants_periods_and_fiscal_year_cannot_be_closed_locked_or_reopened(): void
    {
        $period = AccountingPeriod::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->orderBy('start_date')->firstOrFail();
        $year = FiscalYear::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();

        $this->assertDenied($this->asB()->post(route('accounting.periods.close', $period->id)), 'period close');
        $this->assertDenied($this->asB()->post(route('accounting.periods.lock', $period->id)), 'period lock');
        $this->assertDenied($this->asB()->post(route('accounting.periods.reopen', $period->id)), 'period reopen');
        $this->assertDenied($this->asB()->post(route('accounting.fiscal-years.close', $year->id)), 'year close');

        $this->assertSame(AccountingPeriod::STATUS_OPEN, $period->fresh()->status);
    }

    public function test_another_tenants_budget_cannot_be_opened_changed_approved_or_deleted(): void
    {
        $year = FiscalYear::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();
        $this->as($this->accountantA, $this->tenantA)->post(route('accounting.budgets.store'), [
            'fiscal_year_id' => $year->id,
            'name' => 'A Opex 2026',
            'lines' => [['chart_of_account_id' => $this->accountFor($this->tenantA, '5200')->id, 'amount' => 120000]],
        ]);
        $budget = Budget::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();

        $this->assertDenied($this->asB()->get(route('accounting.budgets.edit', $budget->id)), 'edit');
        $this->assertDenied($this->asB()->post(route('accounting.budgets.approve', $budget->id)), 'approve');
        $this->assertDenied($this->asB()->delete(route('accounting.budgets.destroy', $budget->id)), 'destroy');

        $this->assertNotNull(Budget::withoutGlobalScopes()->find($budget->id));
    }

    public function test_another_tenants_bank_reconciliation_cannot_be_opened_or_completed(): void
    {
        $this->as($this->ownerA, $this->tenantA)->post(route('accounting.bank-reconciliation.store'), [
            'chart_of_account_id' => $this->accountFor($this->tenantA, '1020')->id,
            'statement_date' => now()->toDateString(),
            'closing_balance' => 75000,
        ]);
        $reco = BankReconciliation::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();

        $this->assertDenied($this->asB()->get(route('accounting.bank-reconciliation.show', $reco->id)), 'show');
        $this->assertDenied($this->asB()->post(route('accounting.bank-reconciliation.complete', $reco->id)), 'complete');
        $this->assertDenied($this->asB()->post(route('accounting.bank-reconciliation.auto-match', $reco->id)), 'auto-match');
        $this->assertDenied($this->asB()->get(route('accounting.bank-reconciliation.export', [$reco->id, 'xlsx'])), 'export');
    }

    public function test_report_query_parameters_cannot_pull_another_tenants_account_or_party(): void
    {
        $foreignBank = $this->accountFor($this->tenantA, '1020');

        foreach ([
            route('accounting.reports.bank-book', ['account_id' => $foreignBank->id, 'chart_of_account_id' => $foreignBank->id]),
            route('accounting.reports.general-ledger', ['account_id' => $foreignBank->id, 'chart_of_account_id' => $foreignBank->id]),
        ] as $url) {
            $response = $this->asB()->get($url);
            $this->assertNotSame(500, $response->getStatusCode(), "{$url} crashed");
            $response->assertDontSee('75,000')->assertDontSee('A capital');
        }
    }
}
