<?php

namespace Tests\Feature\Purchase;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Vendor $vendor;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-po',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        session(['tenant_slug' => $this->tenant->slug]);

        $this->seed(RbacSeeder::class);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier Global Corp',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Distribution Center',
            'code' => 'WH-MAIN',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Industrial Steel Pipe',
            'sku' => 'STEEL-001',
            'unit_price' => 250.00,
        ]);
    }

    public function test_user_can_create_and_approve_purchase_requisition(): void
    {
        $this->actingAs($this->user);
        session(['tenant_slug' => $this->tenant->slug]);

        $pr = PurchaseRequisition::create([
            'tenant_id' => $this->tenant->id,
            'requisition_number' => 'PR-2026-0001',
            'requested_by' => $this->user->id,
            'requisition_date' => now()->toDateString(),
            'status' => 'Draft',
        ]);

        $this->assertDatabaseHas('purchase_requisitions', [
            'id' => $pr->id,
            'status' => 'Draft',
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->from(route('purchase.requisitions.index'))
            ->post(route('purchase.requisitions.approve', $pr->id));
        
        $response->assertRedirect(route('purchase.requisitions.index'));

        $this->assertDatabaseHas('purchase_requisitions', [
            'id' => $pr->id,
            'status' => 'Approved',
        ]);
    }

    public function test_purchase_order_creation_and_approval_flow(): void
    {
        $this->actingAs($this->user);
        session(['tenant_slug' => $this->tenant->slug]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'purchase_order_number' => 'PO-2026-0001',
            'date' => now()->toDateString(),
            'status' => 'Draft',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'Draft',
        ]);

        $response = $this->from(route('purchase.orders.index'))
            ->post(route('purchase.orders.approve', $po->id));
        
        $response->assertRedirect(route('purchase.orders.index'));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'Approved',
        ]);
    }

    public function test_tenant_isolation_prevents_cross_tenant_purchase_order_access(): void
    {
        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-po',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        $poA = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'purchase_order_number' => 'PO-SECRET-001',
            'date' => now()->toDateString(),
            'status' => 'Draft',
            'subtotal' => 500.00,
            'grand_total' => 500.00,
        ]);

        $this->actingAs($userB);
        session(['tenant_slug' => $tenantB->slug]);

        $response = $this->get(route('purchase.orders.show', $poA->id));
        $response->assertStatus(404);
    }
}
