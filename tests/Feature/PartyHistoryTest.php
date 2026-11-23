<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Party\Contracts\PartyHistoryProvider;
use App\Domains\Party\Services\PartyHistoryService;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Sales\Models\Invoice;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PartyMergeService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** FRS 2.3: opening a party shows its full history; merge keeps all history. */
class PartyHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Hist Tenant', 'slug' => 'hist-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        app(\App\Support\Tenancy::class)->setTenant($this->tenant);
        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@hist.test', 'password' => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function customer(string $name): Customer
    {
        return Customer::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'email' => $name . '@h.test', 'status' => 'active']);
    }

    private function invoice(Customer $c, string $no): Invoice
    {
        return Invoice::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $c->id, 'invoice_number' => $no,
            'invoice_date' => now(), 'total_amount' => 500, 'status' => 'unpaid',
        ]);
    }

    public function test_customer_history_merges_crm_and_sales_rows_newest_first(): void
    {
        $c = $this->customer('Acme');
        $acct = CrmAccount::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme', 'customer_id' => $c->id]);
        Quotation::create([
            'tenant_id' => $this->tenant->id, 'crm_account_id' => $acct->id, 'quotation_number' => 'Q-1',
            'quotation_date' => now()->subDays(5), 'total_amount' => 900, 'status' => 'Sent',
        ]);
        $this->invoice($c, 'INV-1');

        $rows = app(PartyHistoryService::class)->entries('customer', $c->id, $this->user);

        $this->assertCount(2, $rows);
        $this->assertSame('INV-1', $rows[0]['number']);
        $this->assertStringContainsString('Q-1', $rows[1]['number']);
        $this->assertSame(['invoice', 'quotation'], $rows->pluck('type')->all());
    }

    public function test_vendor_history_lists_po_and_bill_not_other_vendors(): void
    {
        $v = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Co', 'status' => 'active']);
        $other = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Other Co', 'status' => 'active']);

        PurchaseOrder::unguarded(fn () => PurchaseOrder::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $v->id, 'purchase_order_number' => 'PO-1', 'date' => now(), 'status' => 'Draft', 'grand_total' => 100,
        ]));
        PurchaseOrder::unguarded(fn () => PurchaseOrder::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $other->id, 'purchase_order_number' => 'PO-X', 'date' => now(), 'status' => 'Draft', 'grand_total' => 1,
        ]));

        $rows = app(PartyHistoryService::class)->entries('vendor', $v->id, $this->user);

        $this->assertSame(['PO-1'], $rows->pluck('number')->all());
    }

    public function test_merge_keeps_all_history_on_surviving_customer(): void
    {
        $target = $this->customer('Target');
        $source = $this->customer('Source');
        $this->invoice($target, 'INV-T');
        $this->invoice($source, 'INV-S');

        app(PartyMergeService::class)->merge(PartyMergeService::CUSTOMER, $source->id, $target->id, $this->user);

        $rows = app(PartyHistoryService::class)->entries('customer', $target->id, $this->user);
        $this->assertEqualsCanonicalizing(['INV-T', 'INV-S'], $rows->pluck('number')->all());
    }

    public function test_rows_the_user_cannot_view_are_hidden(): void
    {
        $c = $this->customer('Locked');
        $this->invoice($c, 'INV-L');

        $nobody = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Nobody', 'email' => 'n@hist.test', 'password' => bcrypt('password'),
        ]);

        $rows = app(PartyHistoryService::class)->entries('customer', $c->id, $nobody);
        $this->assertTrue($rows->isEmpty());
    }

    public function test_customer_and_vendor_pages_show_history_tab(): void
    {
        $c = $this->customer('PageCo');
        $this->invoice($c, 'INV-PAGE');
        $v = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'PageVendor', 'status' => 'active']);

        $this->actingAs($this->user)->withHeader('X-Tenant', 'hist-tenant')
            ->get(route('crm.customers.show', $c->id))
            ->assertOk()->assertSee('Full History')->assertSee('INV-PAGE');

        $this->actingAs($this->user)->withHeader('X-Tenant', 'hist-tenant')
            ->get(route('purchase.vendors.show', $v->id))
            ->assertOk()->assertSee('Full History');
    }
}
