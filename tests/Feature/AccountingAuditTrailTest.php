<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\AccountingAuditLog;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\TaxRateService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Audit Trail Tenant',
            'slug' => 'audit-trail-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->accountant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accountant User',
            'email' => 'accountant@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->accountant->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->accountant->forceFill(['role_id' => $role->id])->save();
    }

    private function accountByCode(string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    /** @test */
    public function posting_and_reversing_a_journal_each_write_exactly_one_audit_log(): void
    {
        $this->actingAs($this->accountant);

        $cash = $this->accountByCode('1010');
        $capital = $this->accountByCode('3010');

        $journal = app(JournalService::class)->post([
            ['chart_of_account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $capital->id, 'debit' => 0, 'credit' => 1000],
        ], ['tenant_id' => $this->tenant->id]);

        $postedLogs = AccountingAuditLog::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('event_type', 'journal.posted')
            ->where('subject_id', $journal->id)
            ->get();

        $this->assertCount(1, $postedLogs);
        $this->assertSame($this->accountant->id, $postedLogs->first()->triggered_by);

        app(JournalService::class)->reverse($journal->id, 'Test reversal', $this->accountant->id);

        $reversedLogs = AccountingAuditLog::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('event_type', 'journal.reversed')
            ->where('subject_id', $journal->id)
            ->get();

        $this->assertCount(1, $reversedLogs);
        $this->assertSame($this->accountant->id, $reversedLogs->first()->triggered_by);
    }

    /** @test */
    public function updating_a_chart_of_account_writes_exactly_one_audit_log(): void
    {
        $this->actingAs($this->accountant);

        $account = $this->accountByCode('5900');

        app(ChartOfAccountsService::class)->update($account->id, ['name' => 'Other Expense (Renamed)']);

        $logs = AccountingAuditLog::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('event_type', 'chartofaccount.updated')
            ->where('subject_id', $account->id)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertSame($this->accountant->id, $logs->first()->triggered_by);
    }

    /** @test */
    public function creating_a_tax_rate_writes_exactly_one_audit_log(): void
    {
        $this->actingAs($this->accountant);

        $taxRate = app(TaxRateService::class)->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'GST 18%',
            'type' => 'gst',
            'rate' => 18,
            'is_compound' => false,
            'is_active' => true,
        ]);

        $logs = AccountingAuditLog::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('event_type', 'taxrate.created')
            ->where('subject_id', $taxRate->id)
            ->get();

        $this->assertCount(1, $logs);
        $this->assertSame($this->accountant->id, $logs->first()->triggered_by);
    }
}
