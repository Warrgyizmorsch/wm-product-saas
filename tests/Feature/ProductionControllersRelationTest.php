<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Models\ProductionShift;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionControllersRelationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app()->instance('tenant', $this->tenant);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_rework_orders_index_page_loads_successfully_without_relation_error(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product',
            'sku' => 'PROD-001',
            'type' => 'finished_good',
            'unit' => 'pcs',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-1001',
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'status' => 'in_progress',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'due_date' => now()->addDays(5),
        ]);

        $ncr = ProductionNcr::create([
            'tenant_id' => $this->tenant->id,
            'ncr_number' => 'NCR-1001',
            'category' => 'defect',
            'description' => 'Test NCR Description',
            'status' => 'open',
            'production_order_id' => $order->id,
        ]);

        ProductionReworkOrder::create([
            'tenant_id' => $this->tenant->id,
            'rework_number' => 'RW-1001',
            'ncr_id' => $ncr->id,
            'original_production_order_id' => $order->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.rework.index'));

        $response->assertStatus(200);
    }

    public function test_scrap_disposals_index_page_loads_successfully_without_relation_error(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product 2',
            'sku' => 'PROD-002',
            'type' => 'finished_good',
            'unit' => 'pcs',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-1002',
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'status' => 'in_progress',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'due_date' => now()->addDays(5),
        ]);

        $ncr = ProductionNcr::create([
            'tenant_id' => $this->tenant->id,
            'ncr_number' => 'NCR-1002',
            'category' => 'defect',
            'description' => 'Test NCR Description 2',
            'status' => 'open',
            'production_order_id' => $order->id,
        ]);

        ProductionScrapDisposal::create([
            'tenant_id' => $this->tenant->id,
            'ncr_id' => $ncr->id,
            'category' => 'defect',
            'reason_code' => 'SCRAP',
            'quantity' => 5,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.scrap.index'));

        $response->assertStatus(200);
    }

    public function test_quality_dashboard_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.quality.dashboard'));

        $response->assertStatus(200);
    }

    public function test_shifts_index_page_loads_successfully(): void
    {
        ProductionShift::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Morning Shift',
            'code' => 'MS1',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.shifts.index'));

        $response->assertStatus(200);
    }

    public function test_wip_index_page_loads_successfully(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product 3',
            'sku' => 'PROD-003',
            'type' => 'finished_good',
            'unit' => 'pcs',
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-1003',
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'status' => 'in_progress',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'due_date' => now()->addDays(5),
        ]);

        \App\Domains\Production\Models\ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'available_quantity' => 10,
            'completed_quantity' => 0,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.wip.index'));

        $response->assertStatus(200);
    }

    public function test_routing_show_page_loads_successfully(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Routing Product',
            'sku' => 'PROD-RTG',
            'type' => 'finished_good',
            'unit' => 'pcs',
        ]);

        $routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RTG-001',
            'name' => 'Standard Assembly Routing',
            'product_id' => $product->id,
            'version' => '1.0.0',
            'revision' => 1,
            'status' => 'active',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.routing.show', $routing->id));

        $response->assertStatus(200);
    }
}
