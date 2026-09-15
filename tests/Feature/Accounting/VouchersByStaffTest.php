<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VouchersByStaffTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $asha;
    private User $bala;
    private ChartOfAccount $bank;
    private ChartOfAccount $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY '.now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->asha = $this->makeUser('Asha Accountant', 'asha@acme.test', 'accountant');
        $this->bala = $this->makeUser('Bala Accountant', 'bala@acme.test', 'accountant');

        $this->bank = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1020', 'name' => 'Bank', 'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'is_cash_or_bank' => true,
        ]);
        $this->expense = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '5200', 'name' => 'Rent', 'type' => ChartOfAccount::TYPE_EXPENSE,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $journals = app(JournalService::class);
        $post = fn (float $amount, array $meta) => $journals->post([
            ['chart_of_account_id' => $this->expense->id, 'debit' => $amount],
            ['chart_of_account_id' => $this->bank->id, 'credit' => $amount],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()] + $meta);

        $first = $post(100, ['posted_by' => $this->asha->id]);
        $post(200, ['posted_by' => $this->asha->id]);
        $journals->reverse($first->id, null, $this->asha->id);
        $post(300, ['posted_by' => $this->bala->id, 'voucher_type' => 'payment']);
        $post(50, []); // auto-posted, no user

        BankReconciliation::create([
            'tenant_id' => $this->tenant->id, 'chart_of_account_id' => $this->bank->id, 'statement_date' => now()->toDateString(),
            'opening_balance' => 0, 'closing_balance' => 0, 'status' => BankReconciliation::STATUS_COMPLETED,
            'completed_by' => $this->bala->id, 'completed_at' => now(),
        ]);
    }

    private function makeUser(string $name, string $email, ?string $role): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'email' => $email, 'password' => bcrypt('password')]);

        if ($role !== null) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $role)->firstOrFail()->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        return $user;
    }

    private function get_as(User $user, string $url)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', 'acme')->get($url);
    }

    public function test_journal_list_filters_and_shows_who_posted(): void
    {
        $byAsha = $this->get_as($this->asha, route('accounting.journals.index', ['posted_by' => $this->asha->id]))
            ->assertOk()
            ->assertSee('Posted by')
            ->assertSee('Asha Accountant');
        // Two journals plus the reversal of the first.
        $this->assertSame(3, $byAsha->viewData('journals')->total());

        $system = $this->get_as($this->asha, route('accounting.journals.index', ['posted_by' => 'system']));
        $this->assertSame(1, $system->viewData('journals')->total());

        $outOfRange = $this->get_as($this->asha, route('accounting.journals.index', ['from' => now()->addDay()->toDateString()]));
        $this->assertSame(0, $outOfRange->viewData('journals')->total());
    }

    public function test_voucher_list_filters_by_poster(): void
    {
        $response = $this->get_as($this->bala, route('accounting.vouchers.payment.index', ['posted_by' => $this->bala->id]))->assertOk();
        $this->assertSame(1, $response->viewData('vouchers')->total());

        $none = $this->get_as($this->bala, route('accounting.vouchers.payment.index', ['posted_by' => $this->asha->id]));
        $this->assertSame(0, $none->viewData('vouchers')->total());
    }

    public function test_staff_report_counts_documents_reversals_and_reconciliations_per_person(): void
    {
        $response = $this->get_as($this->asha, route('accounting.reports.vouchers-by-staff'))
            ->assertOk()
            ->assertSee('Asha Accountant')
            ->assertSee('System (auto-posted)');

        $rows = collect($response->viewData('rows'))->keyBy('name');

        $asha = $rows['Asha Accountant'];
        $this->assertSame(3, $asha['counts']['journal']);
        $this->assertSame(3, $asha['documents']);
        $this->assertSame(1, $asha['reversed']);
        $this->assertEquals(400, $asha['amount']);

        $bala = $rows['Bala Accountant'];
        $this->assertSame(1, $bala['counts']['payment']);
        $this->assertSame(0, $bala['counts']['journal']);
        $this->assertSame(1, $bala['reconciliations']);

        $this->assertSame(1, $rows['System (auto-posted)']['documents']);
        $this->assertSame(5, $response->viewData('totals')['documents']);
        // "System" always sorts last.
        $this->assertSame('System (auto-posted)', collect($response->viewData('rows'))->last()['name']);
    }

    public function test_staff_report_needs_report_access(): void
    {
        $staff = $this->makeUser('Staff', 'staff@acme.test', null);

        $this->get_as($staff, route('accounting.reports.vouchers-by-staff'))->assertForbidden();
    }
}
