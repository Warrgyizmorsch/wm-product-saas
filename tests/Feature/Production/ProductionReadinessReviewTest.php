<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessReviewTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Readiness Tenant',
            'slug' => 'readiness-tenant',
            'domain' => 'readiness.test',
            'plan' => 'growth',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->actingAs($this->user);
        session(['tenant_id' => $this->tenant->id]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Finished Good',
            'sku' => 'FG-READINESS',
            'type' => 'finished_good',
            'unit_cost' => 12.50,
            'status' => 'active',
        ]);
    }

    /**
     * Test that manual WIP adjustment blocks negative values.
     */
    public function test_wip_adjustment_blocks_negative_quantity()
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-READ-01',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10.0,
            'status' => 'draft',
            'start_date' => today(),
            'end_date' => today()->addDays(2),
        ]);

        $wip = ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'available_quantity' => 10.0,
            'status' => 'active',
        ]);

        $service = app(ProductionWipService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("WIP quantity cannot be negative.");

        $service->adjustWip($wip->id, -5.0, 'Testing negative guard', $this->user->id);
    }

    /**
     * Test that double conversion of WIP is blocked when no available quantity remains.
     */
    public function test_double_wip_conversion_to_fg_is_blocked()
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-READ-02',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10.0,
            'status' => 'in_progress',
            'start_date' => today(),
            'end_date' => today()->addDays(2),
        ]);

        $wip = ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 0.0,
            'available_quantity' => 0.0,
            'status' => 'completed',
        ]);

        $service = app(ProductionWipService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot convert WIP: This tracking card has already been completed or has no remaining available quantity.");

        // Warehouse ID doesn't need to exist for validation checking to fail
        $service->convertWipToFinishedGoods($wip->id, 999, 'Double complete testing', $this->user->id);
    }
}
