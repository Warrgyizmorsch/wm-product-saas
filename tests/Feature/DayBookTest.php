<?php

namespace Tests\Feature;

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

class DayBookTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private ChartOfAccount $bank;
    private ChartOfAccount $capital;

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

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accountant User',
            'email' => 'accountant@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $this->bank = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1020',
            'name' => 'Bank Account',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $this->capital = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '3010',
            'name' => "Owner's Capital",
            'type' => ChartOfAccount::TYPE_EQUITY,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }

    /** @test */
    public function day_book_lists_every_journal_posted_today_with_line_detail(): void
    {
        $journals = app(JournalService::class);

        $journals->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 10000, 'credit' => 0],
            ['chart_of_account_id' => $this->capital->id, 'debit' => 0, 'credit' => 10000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now(), 'memo' => 'Capital injection']);

        $journals->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $this->capital->id, 'debit' => 0, 'credit' => 500],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now(), 'memo' => 'Second entry']);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.day-book', ['date' => now()->toDateString()]));

        $response->assertOk();
        $response->assertSee('Capital injection');
        $response->assertSee('Second entry');
        $response->assertSee('10,000.00');
        $response->assertSee('500.00');
        // Total of the day = 10,000 + 500
        $response->assertSee('10,500.00');
    }

    /** @test */
    public function day_book_is_empty_for_a_date_with_no_activity(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.day-book', ['date' => now()->subYears(2)->toDateString()]));

        $response->assertOk();
        $response->assertSee('No vouchers or journals posted on this date.');
    }
}
