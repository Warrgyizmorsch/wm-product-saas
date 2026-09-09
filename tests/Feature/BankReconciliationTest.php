<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private ChartOfAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Bank Rec Tenant',
            'slug' => 'bank-rec-tenant',
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

        $this->bank = $this->accountByCode('1020');
    }

    private function accountByCode(string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    /** @test */
    public function importing_matching_and_completing_a_reconciliation_flows_end_to_end(): void
    {
        $this->actingAs($this->accountant);

        $capital = $this->accountByCode('3010');
        $rent = $this->accountByCode('5200');

        // Deposit: Dr Bank 5000 / Cr Capital 5000 — signed bank movement +5000.
        app(JournalService::class)->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 5000, 'credit' => 0],
            ['chart_of_account_id' => $capital->id, 'debit' => 0, 'credit' => 5000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        // Withdrawal: Dr Rent 1000 / Cr Bank 1000 — signed bank movement -1000.
        app(JournalService::class)->post([
            ['chart_of_account_id' => $rent->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $this->bank->id, 'debit' => 0, 'credit' => 1000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $reconciliations = app(BankReconciliationService::class);

        $reconciliation = $reconciliations->start([
            'tenant_id' => $this->tenant->id,
            'chart_of_account_id' => $this->bank->id,
            'statement_date' => now()->toDateString(),
            'opening_balance' => 0,
            'closing_balance' => 4000,
        ]);

        $csv = "date,description,amount\n"
            . now()->toDateString() . ",Deposit,5000\n"
            . now()->toDateString() . ",Rent Payment,-1000\n";

        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $imported = $reconciliations->importStatementLines($reconciliation, $file);
        $this->assertSame(2, $imported);

        $matched = $reconciliations->autoMatch($reconciliation);
        $this->assertSame(2, $matched);

        $reconciliation->refresh();
        $this->assertCount(0, $reconciliation->statementLines()->unmatched()->get());

        $reconciledEntries = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('chart_of_account_id', $this->bank->id)
            ->where('is_reconciled', true)
            ->get();
        $this->assertCount(2, $reconciledEntries);

        $completed = $reconciliations->complete($reconciliation, $this->accountant->id);
        $this->assertSame(BankReconciliation::STATUS_COMPLETED, $completed->status);
        $this->assertSame($this->accountant->id, $completed->completed_by);

        // Locked: no further mutation is accepted once completed.
        $this->expectException(InvalidArgumentException::class);
        $reconciliations->autoMatch($completed);
    }

    /** @test */
    public function completion_is_rejected_when_statement_lines_remain_unmatched(): void
    {
        $this->actingAs($this->accountant);

        $reconciliations = app(BankReconciliationService::class);

        $reconciliation = $reconciliations->start([
            'tenant_id' => $this->tenant->id,
            'chart_of_account_id' => $this->bank->id,
            'statement_date' => now()->toDateString(),
            'opening_balance' => 0,
            'closing_balance' => 5000,
        ]);

        $csv = "date,description,amount\n" . now()->toDateString() . ",Deposit,5000\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);
        $reconciliations->importStatementLines($reconciliation, $file);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All statement lines must be matched');
        $reconciliations->complete($reconciliation, $this->accountant->id);
    }
}
