<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Services\ProductionCostVarianceService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private ProductionOrder $order;
    private Product $component;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create([
            'id'   => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role'      => 'admin',
        ]);
        $this->actingAs($this->user);

        $uom = \App\Domains\Inventory\Models\Uom::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Pieces',
            'code'      => 'PCS',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Finished Widget',
            'sku'       => 'FG-WIDGET-001',
            'type'      => 'finished_good',
            'unit_cost' => 100.00,
            'uom_id'    => $uom->id,
        ]);

        $this->component = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Steel Bar',
            'sku'       => 'RAW-STEEL-001',
            'type'      => 'raw_material',
            'unit_cost' => 50.00,
            'uom_id'    => $uom->id,
        ]);

        $this->order = ProductionOrder::create([
            'tenant_id'        => $this->tenantId,
            'order_number'     => 'MO-2026-99001',
            'product_id'       => $product->id,
            'quantity_ordered' => 100.0,
            'quantity_produced'=> 0.0,
            'status'           => 'released',
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(5)->toDateString(),
            'created_by'       => $this->user->id,
        ]);

        ProductionOrderReservation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order->id,
            'product_id'          => $this->component->id,
            'quantity_planned'    => 200.0,
            'quantity_reserved'   => 50.0,
            'quantity_issued'     => 50.0,
            'uom_id'              => $uom->id,
        ]);
    }

    /** @test */
    public function authorized_user_can_submit_ad_hoc_additional_material_request()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.orders.request-additional-material', $this->order->id), [
                'items' => [
                    [
                        'product_id' => $this->component->id,
                        'quantity'   => 150.0,
                        'notes'      => 'Extra scrap expected in cutting stage',
                    ]
                ],
                'notes' => 'Urgent replenishment required',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_requisition_slips', [
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order->id,
            'requested_by'        => $this->user->id,
            'status'              => 'pending',
        ]);

        $slip = ProductionRequisitionSlip::where('production_order_id', $this->order->id)->latest('id')->first();
        $this->assertNotNull($slip);
        $this->assertDatabaseHas('production_requisition_slip_items', [
            'production_requisition_slip_id' => $slip->id,
            'product_id'                     => $this->component->id,
            'quantity_planned'               => 150.0,
        ]);
    }

    /** @test */
    public function ad_hoc_material_request_rejects_completed_orders()
    {
        $this->order->update(['status' => 'completed']);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.orders.request-additional-material', $this->order->id), [
                'items' => [
                    [
                        'product_id' => $this->component->id,
                        'quantity'   => 10.0,
                    ]
                ],
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function ad_hoc_material_request_rejects_cross_tenant_access()
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant']);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role'      => 'admin',
        ]);

        $response = $this->actingAs($otherUser)
            ->withHeader('X-Tenant', 'other-tenant')
            ->post(route('production.orders.request-additional-material', $this->order->id), [
                'items' => [
                    [
                        'product_id' => $this->component->id,
                        'quantity'   => 10.0,
                    ]
                ],
            ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function daily_cost_history_service_calculates_day_wise_output_and_costs()
    {
        $service = new ProductionCostVarianceService();
        $history = $service->getDailyCostHistory($this->order);

        $this->assertIsArray($history);
    }

    /** @test */
    public function production_order_show_page_renders_procurement_tab_and_costing_history()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $this->order->id));

        $response->assertStatus(200);
        $response->assertSee('Material Requests &amp; Procurement', false);
        $response->assertSee('Day-Wise Production &amp; Costing History', false);
        $response->assertSee('Request Additional Material');
    }
}
