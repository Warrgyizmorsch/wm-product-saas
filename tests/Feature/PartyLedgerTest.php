<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Platform\Models\Transporter;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private ChartOfAccount $ar;
    private ChartOfAccount $ap;
    private ChartOfAccount $revenue;

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
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->ar = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $this->ap = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '2010',
            'name' => 'Accounts Payable',
            'type' => ChartOfAccount::TYPE_LIABILITY,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        $this->revenue = ChartOfAccount::create([
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

    /** @test */
    public function customer_ledger_shows_opening_entries_running_and_closing_balance_and_nets_a_reversal_to_zero(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Retail',
            'opening_balance' => 500,
        ]);

        $journal = app(JournalService::class)->post([
            [
                'chart_of_account_id' => $this->ar->id,
                'debit' => 300,
                'party_type' => JournalEntry::PARTY_CUSTOMER,
                'party_id' => $customer->id,
            ],
            ['chart_of_account_id' => $this->revenue->id, 'credit' => 300],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.party-ledger', ['party_type' => 'customer', 'party_id' => $customer->id]));

        $response->assertOk();
        $response->assertSee('500.00'); // opening balance
        $response->assertSee('800.00'); // running balance after the debit entry

        app(JournalService::class)->reverse($journal->id);

        $afterReversal = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.party-ledger', ['party_type' => 'customer', 'party_id' => $customer->id]));

        $afterReversal->assertOk();
        // Closing balance row: back to the 500.00 opening once the reversal nets out.
        $afterReversal->assertSeeInOrder(['Closing Balance', '500.00']);
    }

    /** @test */
    public function vendor_ledger_uses_credit_minus_debit_convention(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bolt Supplies',
            'opening_balance' => 100,
        ]);

        app(JournalService::class)->post([
            ['chart_of_account_id' => ChartOfAccount::create([
                'tenant_id' => $this->tenant->id, 'code' => '5900', 'name' => 'Other Expense',
                'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            ])->id, 'debit' => 400],
            [
                'chart_of_account_id' => $this->ap->id,
                'credit' => 400,
                'party_type' => JournalEntry::PARTY_VENDOR,
                'party_id' => $vendor->id,
            ],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.party-ledger', ['party_type' => 'vendor', 'party_id' => $vendor->id]));

        $response->assertOk();
        $response->assertSee('100.00'); // opening
        $response->assertSeeInOrder(['Closing Balance', '500.00']); // 100 + 400 credit
    }

    /** @test */
    public function transporter_ledger_resolves_to_its_linked_vendors_entries(): void
    {
        $vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Speedy Freight Co']);
        $transporter = Transporter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Speedy Freight',
            'vendor_id' => $vendor->id,
            'status' => 'active',
        ]);

        app(JournalService::class)->post([
            ['chart_of_account_id' => ChartOfAccount::create([
                'tenant_id' => $this->tenant->id, 'code' => '5030', 'name' => 'Freight Expense',
                'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            ])->id, 'debit' => 250],
            [
                'chart_of_account_id' => $this->ap->id,
                'credit' => 250,
                'party_type' => JournalEntry::PARTY_VENDOR,
                'party_id' => $transporter->vendor_id,
            ],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        // Selecting the transporter in the picker resolves to party_type=vendor,
        // party_id=<transporter's linked vendor> — no separate transporter party_type.
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.party-ledger', ['party_type' => 'vendor', 'party_id' => $transporter->vendor_id]));

        $response->assertOk();
        $response->assertSee('250.00');
    }

    /** @test */
    public function tenant_cannot_see_another_tenants_party_ledger(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $otherCustomer = Customer::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Secret Customer Co',
            'opening_balance' => 999,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.party-ledger', ['party_type' => 'customer', 'party_id' => $otherCustomer->id]));

        $response->assertOk();
        $response->assertDontSee('Secret Customer Co');
        $response->assertSee('Select a customer, vendor, or transporter');
    }
}
