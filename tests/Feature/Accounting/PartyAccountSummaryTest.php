<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PartyAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Vendor;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The vendor and customer pages read the party's account from the books, so
 * they show the same balance as Party Ledger and Bill Settlement, including
 * what was posted outside Purchase / Sales (an asset bought on credit).
 */
class PartyAccountSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 24, 12));
        BillAllocationService::resetSchemaCache();

        $this->tenant = Tenant::create(['name' => 'Books Co', 'slug' => 'books-co', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);
        $this->owner = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@books.example.com', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $this->owner->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->company = Company::create(['company_name' => 'Books Co']);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id, 'name' => 'FY 2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $key): ChartOfAccount
    {
        return ChartOfAccount::where('system_key', $key)->firstOrFail();
    }

    private function journal(array $lines, string $date, array $hints = []): Journal
    {
        return app(JournalService::class)->post($lines, [
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'journal_date' => $date,
            'bill_allocations' => $hints,
        ]);
    }

    /** Acme's books: opening 60,000, part paid; a bill paid in full; a machine bought on credit. */
    private function acme(): Vendor
    {
        $vendor = Vendor::create(['name' => 'Acme Supplies Ltd', 'code' => 'VEND-ACME', 'status' => 'active', 'company_id' => $this->company->id]);
        $ap = ['chart_of_account_id' => $this->account(SystemAccount::AP)->id, 'party_type' => 'vendor', 'party_id' => $vendor->id];
        $bank = $this->account(SystemAccount::BANK)->id;
        $expense = $this->account(SystemAccount::OTHER_EXPENSE)->id;

        $this->journal([['chart_of_account_id' => $expense, 'debit' => 60000], $ap + ['credit' => 60000]], '2026-04-01',
            [BillAllocationService::newRef('payable', 'Opening Balance', '2026-04-01', '2026-04-01', 'opening', $vendor->id)]);
        $this->journal([$ap + ['debit' => 25000], ['chart_of_account_id' => $bank, 'credit' => 25000]], '2026-10-23',
            [BillAllocationService::againstRef('payable', 'opening', $vendor->id)]);
        $this->journal([['chart_of_account_id' => $expense, 'debit' => 11800], $ap + ['credit' => 11800]], '2026-10-23',
            [BillAllocationService::newRef('payable', 'BILL-1', '2026-10-23', '2026-11-22', 'VendorBill', 1)]);
        $this->journal([$ap + ['debit' => 11800], ['chart_of_account_id' => $bank, 'credit' => 11800]], '2026-10-23',
            [BillAllocationService::againstRef('payable', 'VendorBill', 1)]);
        $this->journal([['chart_of_account_id' => $expense, 'debit' => 4500000], $ap + ['credit' => 4500000]], '2026-10-24',
            [BillAllocationService::newRef('payable', 'AST-MCH-001', '2026-10-24', '2026-10-24', 'Asset', 1)]);

        return $vendor;
    }

    /** @test */
    public function a_vendors_summary_is_its_ledger_including_what_was_posted_outside_purchase(): void
    {
        $vendor = $this->acme();

        $summary = app(PartyAccountService::class)->summary('vendor', $vendor->id);

        $this->assertEquals(4535000.0, $summary['balance']);
        $this->assertEquals(4535000.0, $summary['outstanding']);
        $this->assertEquals(0.0, $summary['advance']);
        $this->assertEquals(4571800.0, $summary['billed'], 'Opening + bill + machine');
        $this->assertEquals(36800.0, $summary['paid']);
        $this->assertSame(3, $summary['bills']);
        $this->assertEquals(35000.0, $summary['overdue'], 'Only the opening is past its due date');

        $statement = app(PartyAccountService::class)->statement('vendor', $vendor->id);
        $this->assertEquals(4535000.0, $statement['closing']);
        $this->assertEquals(4535000.0, end($statement['rows'])['running_balance']);
    }

    /** @test */
    public function a_payment_in_advance_shows_as_advance_not_as_a_due(): void
    {
        $vendor = Vendor::create(['name' => 'Prepaid Ltd', 'code' => 'V-PRE', 'status' => 'active', 'company_id' => $this->company->id]);
        $this->journal([
            ['chart_of_account_id' => $this->account(SystemAccount::AP)->id, 'debit' => 8000, 'party_type' => 'vendor', 'party_id' => $vendor->id],
            ['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'credit' => 8000],
        ], '2026-10-10');

        $summary = app(PartyAccountService::class)->summary('vendor', $vendor->id);

        $this->assertEquals(0.0, $summary['outstanding']);
        $this->assertEquals(8000.0, $summary['advance']);
        $this->assertEquals(0.0, $summary['billed']);
        $this->assertEquals(8000.0, $summary['paid']);
    }

    /** @test */
    public function the_vendor_page_shows_the_books_balance_and_statement(): void
    {
        $vendor = $this->acme();

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'books-co')
            ->get("/purchase/vendors/{$vendor->id}")
            ->assertOk()
            ->assertViewHas('outstandingPayable', 4535000.0)
            ->assertViewHas('totalBilled', 4571800.0)
            ->assertViewHas('totalPaid', 36800.0)
            ->assertViewHas('overdueAmount', 35000.0)
            ->assertViewHas('statement', fn ($s) => $s['closing'] == 4535000.0)
            ->assertSee('AST-MCH-001');

        // The directory's total is every supplier's balance, not just unpaid Purchase bills.
        $this->actingAs($this->owner)->withHeader('X-Tenant', 'books-co')
            ->get('/purchase/vendors')
            ->assertOk()
            ->assertViewHas('totalOutstandingPayable', 4535000.0);
    }

    /** @test */
    public function a_customers_page_counts_an_opening_due_and_a_receipt_voucher(): void
    {
        $customer = Customer::create(['name' => 'Ravi Traders', 'email' => 'ravi@example.com', 'company_id' => $this->company->id]);
        $ar = ['chart_of_account_id' => $this->account(SystemAccount::AR)->id, 'party_type' => 'customer', 'party_id' => $customer->id];
        $this->journal([$ar + ['debit' => 50000], ['chart_of_account_id' => $this->account(SystemAccount::SALES_REVENUE)->id, 'credit' => 50000]], '2026-04-01',
            [BillAllocationService::newRef('receivable', 'Opening Balance', '2026-04-01', '2026-05-01', 'opening', $customer->id)]);
        $this->journal([['chart_of_account_id' => $this->account(SystemAccount::BANK)->id, 'debit' => 20000], $ar + ['credit' => 20000]], '2026-10-01',
            [BillAllocationService::againstRef('receivable', 'opening', $customer->id)]);

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'books-co')
            ->get("/crm/customers/{$customer->id}")
            ->assertOk()
            ->assertViewHas('outstandingBalance', 30000.0)
            ->assertViewHas('totalBilled', 50000.0)
            ->assertViewHas('overdueAmount', 30000.0);
    }
}
