<?php

namespace Tests\Feature\QA\Accounting;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\QA\Concerns\BuildsQaTenants;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Many Accounting write endpoints validate foreign keys with a bare
 * `exists:chart_of_accounts,id` rule, which checks the row exists in *any*
 * tenant. These tests submit tenant B's ids from inside tenant A and assert
 * nothing of A's ever points at B's ledger. Each assertion checks the stored
 * data, not just the HTTP status, because a silent 302 can hide a write.
 */
class CrossTenantReferenceTest extends TestCase
{
    use BuildsQaTenants;
    use RefreshDatabase;

    private User $accountantA;

    private User $ownerA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTwoTenants(withBooks: true);

        $this->accountantA = $this->userWithRole($this->tenantA, 'accountant');
        $this->ownerA = $this->userWithRole($this->tenantA, 'tenant_owner');
    }

    /** @return list<int> */
    private function tenantBAccountIds(): array
    {
        return ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenantB->id)->pluck('id')->all();
    }

    private function assertNoTenantAEntryTouchesTenantB(string $context): void
    {
        $leaks = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)
            ->whereIn('chart_of_account_id', $this->tenantBAccountIds())
            ->count();

        $this->assertSame(0, $leaks, "{$context}: tenant A posted {$leaks} journal line(s) to tenant B's accounts.");
    }

    public function test_a_manual_journal_cannot_post_to_another_tenants_accounts(): void
    {
        $this->as($this->accountantA, $this->tenantA)->post(route('accounting.journals.store'), [
            'journal_date' => now()->toDateString(),
            'memo' => 'cross-tenant probe',
            'items' => [
                ['chart_of_account_id' => $this->accountFor($this->tenantB, '1010')->id, 'debit' => 1000, 'credit' => 0],
                ['chart_of_account_id' => $this->accountFor($this->tenantB, '3010')->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $this->assertNoTenantAEntryTouchesTenantB('journal');
        $this->assertSame(0, Journal::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->where('memo', 'cross-tenant probe')->count());
    }

    public function test_a_journal_mixing_own_and_foreign_accounts_is_rejected_whole(): void
    {
        $this->as($this->accountantA, $this->tenantA)->post(route('accounting.journals.store'), [
            'journal_date' => now()->toDateString(),
            'memo' => 'half foreign',
            'items' => [
                ['chart_of_account_id' => $this->accountFor($this->tenantA, '1010')->id, 'debit' => 500, 'credit' => 0],
                ['chart_of_account_id' => $this->accountFor($this->tenantB, '3010')->id, 'debit' => 0, 'credit' => 500],
            ],
        ]);

        $this->assertNoTenantAEntryTouchesTenantB('mixed journal');
        $this->assertSame(0, Journal::withoutGlobalScopes()->where('memo', 'half foreign')->count(), 'Half of a foreign journal was saved');
    }

    public function test_a_journal_line_cannot_carry_another_tenants_cost_center(): void
    {
        $foreignCc = CostCenter::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id, 'code' => 'B-CC', 'name' => 'B Plant', 'is_active' => true,
        ]);

        $this->as($this->accountantA, $this->tenantA)->post(route('accounting.journals.store'), [
            'journal_date' => now()->toDateString(),
            'items' => [
                ['chart_of_account_id' => $this->accountFor($this->tenantA, '5200')->id, 'debit' => 100, 'credit' => 0, 'cost_center_id' => $foreignCc->id],
                ['chart_of_account_id' => $this->accountFor($this->tenantA, '1010')->id, 'debit' => 0, 'credit' => 100],
            ],
        ]);

        $this->assertSame(0, JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)->where('cost_center_id', $foreignCc->id)->count());
    }

    public static function voucherTypes(): array
    {
        return [
            'payment' => ['payment'],
            'receipt' => ['receipt'],
            'contra' => ['contra'],
            'credit_note' => ['credit_note'],
            'debit_note' => ['debit_note'],
            'purchase' => ['purchase'],
            'sales' => ['sales'],
        ];
    }

    #[DataProvider('voucherTypes')]
    public function test_no_voucher_type_can_post_to_another_tenants_accounts(string $type): void
    {
        $this->as($this->accountantA, $this->tenantA)->post(route("accounting.vouchers.{$type}.store"), [
            'voucher_date' => now()->toDateString(),
            'items' => [
                ['chart_of_account_id' => $this->accountFor($this->tenantB, '1010')->id, 'debit' => 250, 'credit' => 0],
                ['chart_of_account_id' => $this->accountFor($this->tenantB, '1020')->id, 'debit' => 0, 'credit' => 250],
            ],
        ]);

        $this->assertNoTenantAEntryTouchesTenantB("{$type} voucher");
    }

    public function test_a_new_account_cannot_be_parented_under_another_tenants_account(): void
    {
        $foreignParent = $this->accountFor($this->tenantB, '1010');

        $this->as($this->accountantA, $this->tenantA)->post(route('accounting.chart-of-accounts.store'), [
            'code' => '1019', 'name' => 'Petty Cash QA', 'type' => $foreignParent->type,
            'normal_balance' => $foreignParent->normal_balance, 'parent_id' => $foreignParent->id, 'is_active' => 1,
        ]);

        $this->assertSame(0, ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)->where('parent_id', $foreignParent->id)->count(),
            'Tenant A account was attached under tenant B\'s Cash account (and would now block B from posting to it).');
    }

    public function test_an_existing_account_cannot_be_moved_under_another_tenants_account(): void
    {
        $own = $this->accountFor($this->tenantA, '5200');
        $foreignParent = $this->accountFor($this->tenantB, '5200');

        $this->as($this->ownerA, $this->tenantA)->put(route('accounting.chart-of-accounts.update', $own), [
            'code' => $own->code, 'name' => $own->name, 'type' => $own->type,
            'normal_balance' => $own->normal_balance, 'parent_id' => $foreignParent->id, 'is_active' => 1,
        ]);

        $this->assertNotSame($foreignParent->id, (int) $own->fresh()->parent_id);
    }

    public function test_a_foreign_parent_does_not_freeze_the_other_tenants_ledger(): void
    {
        // Side-effect check for the bug above: if A can hang a child under B's
        // leaf account, JournalService's group-account guard (which reads
        // parent_id without a tenant scope) starts refusing B's postings.
        $foreignCash = $this->accountFor($this->tenantB, '1010');

        $this->as($this->accountantA, $this->tenantA)->post(route('accounting.chart-of-accounts.store'), [
            'code' => '1019', 'name' => 'Sneaky child', 'type' => $foreignCash->type,
            'normal_balance' => $foreignCash->normal_balance, 'parent_id' => $foreignCash->id, 'is_active' => 1,
        ]);

        $accountantB = $this->userWithRole($this->tenantB, 'accountant');
        $this->as($accountantB, $this->tenantB)->post(route('accounting.vouchers.receipt.store'), [
            'voucher_date' => now()->toDateString(),
            'items' => [
                ['chart_of_account_id' => $foreignCash->id, 'debit' => 100, 'credit' => 0],
                ['chart_of_account_id' => $this->accountFor($this->tenantB, '3010')->id, 'debit' => 0, 'credit' => 100],
            ],
        ])->assertSessionHasNoErrors();
    }

    public function test_a_tax_rate_cannot_point_at_another_tenants_payable_account(): void
    {
        $foreign = $this->accountFor($this->tenantB, '2010');

        $this->as($this->accountantA, $this->tenantA)->post(route('accounting.tax-rates.store'), [
            'name' => 'GST 18 QA', 'type' => 'gst', 'rate' => 18, 'tax_payable_account_id' => $foreign->id,
        ]);

        $this->assertSame(0, TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)->where('tax_payable_account_id', $foreign->id)->count());
    }

    public function test_a_budget_cannot_use_another_tenants_fiscal_year_or_accounts(): void
    {
        $foreignYear = FiscalYear::withoutGlobalScopes()->where('tenant_id', $this->tenantB->id)->firstOrFail();

        $this->as($this->accountantA, $this->tenantA)->post(route('accounting.budgets.store'), [
            'fiscal_year_id' => $foreignYear->id,
            'name' => 'Foreign budget',
            'lines' => [['chart_of_account_id' => $this->accountFor($this->tenantB, '5200')->id, 'amount' => 1000]],
        ]);

        $this->assertDatabaseMissing('budgets', ['name' => 'Foreign budget', 'tenant_id' => $this->tenantA->id]);
    }

    public function test_a_bank_reconciliation_cannot_be_opened_on_another_tenants_bank_account(): void
    {
        $foreignBank = $this->accountFor($this->tenantB, '1020');

        $this->as($this->ownerA, $this->tenantA)->post(route('accounting.bank-reconciliation.store'), [
            'chart_of_account_id' => $foreignBank->id,
            'statement_date' => now()->toDateString(),
            'closing_balance' => 0,
        ]);

        $this->assertSame(0, BankReconciliation::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)->where('chart_of_account_id', $foreignBank->id)->count());
    }
}
