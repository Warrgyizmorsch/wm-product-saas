<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherEngineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private ChartOfAccount $bankAccount;
    private ChartOfAccount $cashAccount;
    private ChartOfAccount $expenseAccount;
    private ChartOfAccount $incomeAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->bankAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1020',
            'name' => 'Bank Account',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_cash_or_bank' => true,
        ]);

        $this->cashAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1010',
            'name' => 'Cash in Hand',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_cash_or_bank' => true,
        ]);

        $this->expenseAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '5010',
            'name' => 'Office Expense',
            'type' => ChartOfAccount::TYPE_EXPENSE,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $this->incomeAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '4010',
            'name' => 'Sales Revenue',
            'type' => ChartOfAccount::TYPE_INCOME,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => ucfirst($slug) . ' User',
            'email' => $slug . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $slug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        return $user;
    }

    private function postVoucher(User $user, string $type, array $items, array $overrides = [])
    {
        return $this->actingAs($user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route("accounting.vouchers.{$type}.store"), array_merge([
                'voucher_date' => now()->toDateString(),
                'memo' => 'Test voucher',
                'items' => $items,
            ], $overrides));
    }

    /** @test */
    public function accountant_can_post_all_five_voucher_types(): void
    {
        $accountant = $this->userWithRole('accountant');

        $cases = [
            'payment' => [$this->bankAccount, $this->expenseAccount],
            'receipt' => [$this->bankAccount, $this->incomeAccount],
            'contra' => [$this->bankAccount, $this->cashAccount],
            'credit_note' => [$this->incomeAccount, $this->bankAccount],
            'debit_note' => [$this->expenseAccount, $this->bankAccount],
        ];

        foreach ($cases as $type => [$debitAccount, $creditAccount]) {
            $response = $this->postVoucher($accountant, $type, [
                ['chart_of_account_id' => $debitAccount->id, 'debit' => 100, 'credit' => 0],
                ['chart_of_account_id' => $creditAccount->id, 'debit' => 0, 'credit' => 100],
            ]);

            $response->assertRedirect();

            $journal = Journal::withoutGlobalScopes()->where('voucher_type', $type)->latest('id')->first();
            $this->assertNotNull($journal, "Expected a {$type} journal to be posted.");
            $this->assertSame(100.0, (float) $journal->total_debit);
            $this->assertSame(100.0, (float) $journal->total_credit);
            $this->assertStringStartsWith(\App\Domains\Accounting\Support\VoucherType::prefix($type) . '-', $journal->journal_number);
            $this->assertNotNull($journal->voucherDetail);
        }
    }

    /** @test */
    public function unbalanced_voucher_is_rejected(): void
    {
        $accountant = $this->userWithRole('accountant');

        $response = $this->postVoucher($accountant, 'payment', [
            ['chart_of_account_id' => $this->bankAccount->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $this->expenseAccount->id, 'debit' => 0, 'credit' => 50],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Journal::withoutGlobalScopes()->where('voucher_type', 'payment')->count());
    }

    /** @test */
    public function contra_voucher_rejects_non_cash_or_bank_accounts(): void
    {
        $accountant = $this->userWithRole('accountant');

        $response = $this->postVoucher($accountant, 'contra', [
            ['chart_of_account_id' => $this->bankAccount->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $this->expenseAccount->id, 'debit' => 0, 'credit' => 100],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Journal::withoutGlobalScopes()->where('voucher_type', 'contra')->count());
    }

    /** @test */
    public function accountant_cannot_reverse_but_tenant_owner_can(): void
    {
        $accountant = $this->userWithRole('accountant');
        $owner = $this->userWithRole('tenant_owner');

        $this->postVoucher($accountant, 'payment', [
            ['chart_of_account_id' => $this->bankAccount->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $this->expenseAccount->id, 'debit' => 0, 'credit' => 100],
        ])->assertRedirect();

        $journal = Journal::withoutGlobalScopes()->where('voucher_type', 'payment')->latest('id')->first();

        $this->actingAs($accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('accounting.vouchers.payment.reverse', $journal))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('accounting.vouchers.payment.reverse', $journal))
            ->assertRedirect();

        $journal->refresh();
        $this->assertSame(Journal::STATUS_REVERSED, $journal->status);

        $reversal = Journal::withoutGlobalScopes()->find($journal->reversed_journal_id);
        $this->assertSame('payment', $reversal->voucher_type);
    }

    /** @test */
    public function unrelated_role_is_forbidden_from_all_voucher_actions(): void
    {
        $reader = $this->userWithRole('read_only');

        $this->actingAs($reader)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.vouchers.payment.index'))
            ->assertForbidden();

        $this->actingAs($reader)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.vouchers.payment.create'))
            ->assertForbidden();

        $this->postVoucher($reader, 'payment', [
            ['chart_of_account_id' => $this->bankAccount->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $this->expenseAccount->id, 'debit' => 0, 'credit' => 100],
        ])->assertForbidden();
    }

    /** @test */
    public function numbering_is_sequential_and_unique_per_voucher_type(): void
    {
        $accountant = $this->userWithRole('accountant');

        for ($i = 0; $i < 3; $i++) {
            $this->postVoucher($accountant, 'receipt', [
                ['chart_of_account_id' => $this->bankAccount->id, 'debit' => 50, 'credit' => 0],
                ['chart_of_account_id' => $this->incomeAccount->id, 'debit' => 0, 'credit' => 50],
            ])->assertRedirect();
        }

        $numbers = Journal::withoutGlobalScopes()
            ->where('voucher_type', 'receipt')
            ->pluck('journal_number')
            ->all();

        $this->assertCount(3, $numbers);
        $this->assertCount(3, array_unique($numbers));
        foreach ($numbers as $number) {
            $this->assertStringStartsWith('REC-', $number);
        }
    }
}
