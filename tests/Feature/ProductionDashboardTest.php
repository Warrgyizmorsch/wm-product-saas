<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_dashboard_renders_successfully_with_metrics_and_pending_sales_orders(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Test Radiator Core',
            'sku'       => 'PR-CORE-001',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        $request = ProductionOrderRequest::create([
            'tenant_id'          => $tenant->id,
            'product_id'         => $product->id,
            'quantity_requested' => 10.0,
            'status'             => 'draft',
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-2026-TEST-001',
            'product_id'       => $product->id,
            'quantity_ordered' => 10.0,
            'status'           => 'released',
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
        ]);

        ProductionRequisitionSlip::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $order->id,
            'requisition_number'  => 'REQ-TEST-001',
            'requisition_date'    => now()->toDateString(),
            'status'              => 'Fully Issued',
            'requested_by'        => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Production Dashboard');
        $response->assertSee('Pending Sales Orders to Manufacture');
        $response->assertSee('Ready To Start');
        $response->assertSee($order->order_number);
    }
}
