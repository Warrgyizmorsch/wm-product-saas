<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOrderAndWipUiTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private Product $product;
    private ProductionOrder $order;
    private ProductionWip $wip;

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

        $this->product = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Widget Ultra',
            'sku'       => 'WDG-ULT-01',
            'type'      => 'finished_good',
        ]);

        $this->order = ProductionOrder::create([
            'tenant_id'         => $this->tenantId,
            'order_number'      => 'PO-2026-9901',
            'product_id'        => $this->product->id,
            'quantity_ordered'  => 50,
            'quantity_produced' => 10,
            'status'            => ProductionOrder::STATUS_RELEASED,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(7)->toDateString(),
        ]);

        $this->wip = ProductionWip::create([
            'tenant_id'            => $this->tenantId,
            'production_order_id'  => $this->order->id,
            'product_id'           => $this->product->id,
            'quantity'             => 50,
            'available_quantity'   => 40,
            'completed_quantity'   => 10,
            'status'               => 'active',
        ]);
    }

    /** @test */
    public function production_order_index_page_renders_and_excludes_other_tenant_orders()
    {
        $otherTenantId = 999;
        Tenant::factory()->create(['id' => $otherTenantId, 'slug' => 'other-tenant']);

        $otherProduct = Product::create([
            'tenant_id' => $otherTenantId,
            'name'      => 'Foreign Item',
            'sku'       => 'FRG-01',
            'type'      => 'finished_good',
        ]);

        $otherOrder = ProductionOrder::create([
            'tenant_id'         => $otherTenantId,
            'order_number'      => 'PO-OTHER-777',
            'product_id'        => $otherProduct->id,
            'quantity_ordered'  => 100,
            'quantity_produced' => 0,
            'status'            => ProductionOrder::STATUS_DRAFT,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.index'));

        $response->assertStatus(200);
        $response->assertSee('PO-2026-9901');
        $response->assertDontSee('PO-OTHER-777');
    }

    /** @test */
    public function production_order_show_page_renders_with_null_relations_and_timeline()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $this->order->id));

        $response->assertStatus(200);
        $response->assertSee('PO-2026-9901');
        $response->assertSee('Widget Ultra');
    }

    /** @test */
    public function cross_tenant_production_order_show_access_is_denied()
    {
        $otherTenantId = 999;
        Tenant::factory()->create(['id' => $otherTenantId, 'slug' => 'other-tenant']);

        $otherProduct = Product::create([
            'tenant_id' => $otherTenantId,
            'name'      => 'Secret Item',
            'sku'       => 'SCR-01',
            'type'      => 'finished_good',
        ]);

        $otherOrder = ProductionOrder::create([
            'tenant_id'         => $otherTenantId,
            'order_number'      => 'PO-SECRET-001',
            'product_id'        => $otherProduct->id,
            'quantity_ordered'  => 10,
            'quantity_produced' => 0,
            'status'            => ProductionOrder::STATUS_DRAFT,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $otherOrder->id));

        $response->assertStatus(404);
    }

    /** @test */
    public function wip_index_page_renders_kpi_summary_cards_and_filters()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.wip.index'));

        $response->assertStatus(200);
        $response->assertSee('Total WIP Cards');
        $response->assertSee('PO-2026-9901');
    }

    /** @test */
    public function wip_show_page_renders_quantity_summary_and_empty_movement_history()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.wip.show', $this->wip->id));

        $response->assertStatus(200);
        $response->assertSee('Widget Ultra');
    }

    /** @test */
    public function cross_tenant_wip_access_is_denied()
    {
        $otherTenantId = 999;
        Tenant::factory()->create(['id' => $otherTenantId, 'slug' => 'other-tenant']);

        $otherProduct = Product::create([
            'tenant_id' => $otherTenantId,
            'name'      => 'Other WIP Item',
            'sku'       => 'OWIP-01',
            'type'      => 'finished_good',
        ]);

        $otherOrder = ProductionOrder::create([
            'tenant_id'         => $otherTenantId,
            'order_number'      => 'PO-OTHER-WIP',
            'product_id'        => $otherProduct->id,
            'quantity_ordered'  => 10,
            'quantity_produced' => 0,
            'status'            => ProductionOrder::STATUS_DRAFT,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(5)->toDateString(),
        ]);

        $otherWip = ProductionWip::create([
            'tenant_id'           => $otherTenantId,
            'production_order_id' => $otherOrder->id,
            'product_id'          => $otherProduct->id,
            'quantity'            => 10,
            'available_quantity'  => 10,
            'completed_quantity'  => 0,
            'status'              => 'active',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.wip.show', $otherWip->id));

        $response->assertStatus(404);
    }

    /** @test */
    public function production_order_show_page_renders_generate_schedule_button_and_schedules_list()
    {
        // 1. Initially there are no schedules, verify the message is present
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $this->order->id));

        $response->assertStatus(200);
        $response->assertSee('No schedule has been generated for this production order yet.');

        // 2. Create a schedule for this production order
        $schedule = \App\Domains\Production\Models\ProductionSchedule::create([
            'tenant_id' => $this->tenantId,
            'schedule_number' => 'SCH-TEST-99',
            'production_order_id' => $this->order->id,
            'scheduling_type' => 'forward',
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'created_by' => $this->user->id,
        ]);

        // 3. Verify that the schedule is listed under the Overview tab and the alert is hidden
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $this->order->id));

        $response->assertStatus(200);
        $response->assertSee('SCH-TEST-99');
        $response->assertDontSee('No schedule has been generated for this production order yet.');
    }

    /** @test */
    public function wip_index_page_does_not_show_receive_completed_fg_when_intermediate_operation_completes()
    {
        // 1. Create an order with multi-operation routing: Op 10 (Intermediate SFG) and Op 60 (Final FG)
        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RT-MULTI-01',
            'product_id' => $this->product->id,
            'name' => 'Multi-stage Routing',
            'status' => 'active',
            'version' => '1.0',
        ]);

        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Cutting WC',
            'code' => 'WC-CUT-01',
            'is_active' => true,
        ]);
        $wc2 = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Assembly WC',
            'code' => 'WC-ASSY-01',
            'is_active' => true,
        ]);

        $rop10 = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'operation_number' => 'OP10',
            'sequence' => 10,
            'name' => 'Component Cutting',
            'work_center_id' => $wc1->id,
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 5,
        ]);

        $rop60 = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'operation_number' => 'OP60',
            'sequence' => 60,
            'name' => 'Final Assembly',
            'work_center_id' => $wc2->id,
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 15,
        ]);

        $multiOrder = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-MULTI-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'quantity_produced' => 0,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);

        $sfgProduct = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Table Leg SFG',
            'sku'       => 'SFG-LEG-01',
            'type'      => 'manufactured',
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'routing_operation_id' => $rop10->id,
            'source_product_id' => $sfgProduct->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Component Cutting',
            'work_center_id' => $wc1->id,
            'target_produced_qty' => 20,
            'is_intermediate' => true,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        $op60 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'routing_operation_id' => $rop60->id,
            'source_product_id' => $this->product->id,
            'sequence' => 60,
            'operation_number' => 'OP60',
            'name' => 'Final Assembly',
            'work_center_id' => $wc2->id,
            'target_produced_qty' => 5,
            'is_intermediate' => false,
            'status' => ProductionOrderOperation::STATUS_WAITING,
        ]);

        $wip = ProductionWip::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'product_id' => $this->product->id,
            'current_routing_operation_id' => $rop10->id,
            'current_work_center_id' => $wc1->id,
            'quantity' => 5,
            'available_quantity' => 5,
            'completed_quantity' => 0,
            'status' => 'active',
        ]);

        // Complete intermediate operation Op 10
        $execService = app(\App\Domains\Production\Services\ProductionExecutionService::class);
        $execService->logProgress(
            operationId: $op10->id,
            produced: 20.0,
            rejected: 0.0,
            scrapped: 0.0,
            setupMinutes: 10.0,
            runMinutes: 20.0,
            remarks: 'Op 10 completed',
            userId: $this->user->id,
            completeOperation: true
        );

        $op10->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_COMPLETED, $op10->status);

        // Verify WIP completed_quantity and summaries remain 0.00
        $wipService = app(\App\Domains\Production\Services\ProductionWipService::class);
        $summaries = $wipService->getWorkCenterWipSummaries($this->tenantId, $multiOrder->id);
        $this->assertEquals(0.0, (float) $summaries->sum('total_completed'));

        $wip->refresh();
        $this->assertEquals(0.0, (float) $wip->completed_quantity);
        $this->assertEquals('active', $wip->status);

        // UI check: visit WIP index page and verify "Receive Completed FG" is NOT present for this order
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.wip.index', ['view_mode' => 'order']));

        $response->assertStatus(200);
        $response->assertSee('PO-MULTI-001');
        $response->assertDontSee('Receive Completed FG (5)');

        // Complete final operation Op 60
        $execService->logProgress(
            operationId: $op60->id,
            produced: 5.0,
            rejected: 0.0,
            scrapped: 0.0,
            setupMinutes: 5.0,
            runMinutes: 15.0,
            remarks: 'Final Op 60 completed',
            userId: $this->user->id,
            completeOperation: true
        );

        $op60->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_COMPLETED, $op60->status);

        // Verify WIP completed_quantity and summaries now show 5.0 ready to receive
        $summariesAfterFinal = $wipService->getWorkCenterWipSummaries($this->tenantId, $multiOrder->id);
        $this->assertEquals(5.0, (float) $summariesAfterFinal->sum('total_completed'));

        // UI check: visit WIP index page and verify "Receive Completed FG (5)" IS present for this order
        $responseFinal = $this->withHeader('X-Tenant', 'test-tenant')
            ->actingAs($this->user)
            ->get(route('production.wip.index', ['view_mode' => 'order']));

        $responseFinal->assertStatus(200);
        $responseFinal->assertSee('Receive Completed FG (5)');

        // Execute Finished Goods Receipt via WIP transfer to warehouse
        $warehouse = \App\Domains\Inventory\Models\Warehouse::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Main FG Warehouse',
            'code' => 'WH-MAIN-01',
            'is_active' => true,
        ]);

        $transferResponse = $this->withHeader('X-Tenant', 'test-tenant')
            ->actingAs($this->user)
            ->post(route('production.wip.convert-order', $multiOrder->id), [
                'warehouse_id' => $warehouse->id,
                'quality_status' => 'passed',
                'remarks' => 'Received 5 finished units into warehouse inventory',
            ]);

        $transferResponse->assertRedirect();
        $transferResponse->assertSessionHas('success');

        // Verify receipt and warehouse transfer
        $this->assertDatabaseHas('production_order_receipts', [
            'tenant_id' => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'quantity_received' => 5.0,
            'warehouse_id' => $warehouse->id,
        ]);

        $multiOrder->refresh();
        $this->assertEquals(5.0, (float) $multiOrder->quantity_produced);

        // Verify WIP completed_quantity is cleared and button disappears
        $summariesAfterReceipt = $wipService->getWorkCenterWipSummaries($this->tenantId, $multiOrder->id);
        $this->assertEquals(0.0, (float) $summariesAfterReceipt->sum('total_completed'));

        $responseAfterReceipt = $this->withHeader('X-Tenant', 'test-tenant')
            ->actingAs($this->user)
            ->get(route('production.wip.index', ['view_mode' => 'order']));

        $responseAfterReceipt->assertStatus(200);
        $responseAfterReceipt->assertDontSee('Receive Completed FG (5)');
    }
}


