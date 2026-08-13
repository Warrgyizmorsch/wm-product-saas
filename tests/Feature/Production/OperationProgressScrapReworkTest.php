<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ReworkService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationProgressScrapReworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_progress_scrap_and_rework_quantities_are_accurate(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@test-' . uniqid() . '.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $uom = Uom::create([
            'tenant_id' => $tenant->id,
            'name' => 'Units',
            'code' => 'PCS',
        ]);

        // Product
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Assembly Unit',
            'sku' => 'FG-ASSY-' . uniqid(),
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $uom->id,
        ]);

        // Work Center
        $workCenter = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'code' => 'WC-01',
            'name' => 'Assembly Center',
            'status' => 'active',
        ]);

        // Routing with 3 operations
        $routing = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'RT-TEST-' . uniqid(),
            'name' => 'Test Routing',
            'product_id' => $product->id,
            'status' => 'approved',
        ]);

        $rOp1 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Operation 10',
            'work_center_id' => $workCenter->id,
        ]);

        $rOp2 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Operation 20',
            'work_center_id' => $workCenter->id,
        ]);

        $rOp3 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Operation 30',
            'work_center_id' => $workCenter->id,
        ]);

        // Production Order for 10 units
        $order = ProductionOrder::create([
            'tenant_id' => $tenant->id,
            'order_number' => 'PO-TEST-' . uniqid(),
            'product_id' => $product->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10.00,
            'quantity_produced' => 0.00,
            'quantity_scrapped' => 0.00,
            'status' => 'released',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'due_date' => now()->addDays(5),
            'created_by' => $user->id,
        ]);

        $op1 = ProductionOrderOperation::create([
            'tenant_id' => $tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp1->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Operation 10',
            'work_center_id' => $workCenter->id,
            'status' => 'ready',
        ]);

        $op2 = ProductionOrderOperation::create([
            'tenant_id' => $tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp2->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Operation 20',
            'work_center_id' => $workCenter->id,
            'status' => 'waiting',
        ]);

        $op3 = ProductionOrderOperation::create([
            'tenant_id' => $tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp3->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Operation 30',
            'work_center_id' => $workCenter->id,
            'status' => 'waiting',
        ]);

        // Create Batch of 5
        $batchService = app(BatchProductionService::class);
        $batch = $batchService->createBatch($tenant->id, $order->id, $product->id, 5.00, $op1->id);

        $executionService = app(ProductionExecutionService::class);

        // OP-10: Log 5 produced
        $executionService->logProgress(
            $op1->id,
            5.00, // produced
            0.00, // rejected
            0.00, // scrapped
            0,
            10,
            'OP-10 all good',
            null,
            $user->id,
            true, // complete operation
            null,
            $batch->id
        );

        $op1->refresh();
        $this->assertEquals(5.00, $op1->quantity_produced);
        $this->assertEquals(0.00, $op1->quantity_scrapped);

        // OP-20: Log 3 produced, 2 rejected
        $executionService->logProgress(
            $op2->id,
            3.00, // produced
            2.00, // rejected
            0.00, // scrapped
            0,
            15,
            'OP-20 partial reject',
            null,
            $user->id,
            false,
            null,
            $batch->id
        );

        $op2->refresh();
        $this->assertEquals(3.00, $op2->quantity_produced);
        $this->assertEquals(2.00, $op2->quantity_rejected);
        $this->assertEquals(0.00, $op2->quantity_scrapped);

        // Rework the 2 rejected units on OP-20
        $ncr2 = \App\Domains\Production\Models\ProductionNcr::where('production_order_operation_id', $op2->id)->first();
        if ($ncr2) {
            $reworkOrder2 = app(ReworkService::class)->createReworkOrder($tenant->id, $ncr2->id, [
                'original_production_order_id' => $order->id,
                'work_center_id' => $workCenter->id,
            ]);
            foreach ($reworkOrder2->operations as $rwOp) {
                app(ReworkService::class)->startOperation($rwOp->id, $tenant->id);
                app(ReworkService::class)->completeOperation($rwOp->id, [], $tenant->id);
            }
        }

        $op2->refresh();
        $this->assertEquals(5.00, $op2->quantity_produced);
        $this->assertEquals(0.00, $op2->quantity_rejected);
        $this->assertEquals(0.00, $op2->quantity_scrapped);

        // Complete OP-20
        $op2->status = ProductionOrderOperation::STATUS_COMPLETED;
        $op2->save();
        $op3->status = ProductionOrderOperation::STATUS_READY;
        $op3->save();

        // OP-30: Log 3 produced, 1 rejected, 1 scrapped
        $executionService->logProgress(
            $op3->id,
            3.00, // produced
            1.00, // rejected
            1.00, // scrapped
            0,
            20,
            'OP-30 final step with 1 scrapped and 1 rejected',
            null,
            $user->id,
            false,
            null,
            $batch->id
        );

        $op3->refresh();
        // Crucial assertions: quantity_scrapped must be 1.00, NOT 2.00!
        $this->assertEquals(3.00, $op3->quantity_produced);
        $this->assertEquals(1.00, $op3->quantity_rejected);
        $this->assertEquals(1.00, $op3->quantity_scrapped);

        // Rework the 1 rejected unit on OP-30
        $ncr3 = \App\Domains\Production\Models\ProductionNcr::where('production_order_operation_id', $op3->id)
            ->where('disposition_type', 'rework')
            ->first();
        if ($ncr3) {
            $reworkOrder3 = app(ReworkService::class)->createReworkOrder($tenant->id, $ncr3->id, [
                'original_production_order_id' => $order->id,
                'work_center_id' => $workCenter->id,
            ]);
            foreach ($reworkOrder3->operations as $rwOp) {
                app(ReworkService::class)->startOperation($rwOp->id, $tenant->id);
                app(ReworkService::class)->completeOperation($rwOp->id, [], $tenant->id);
            }
        }

        $op3->refresh();
        // After rework of 1 rejected unit: produced becomes 4.00, rejected becomes 0.00, scrapped remains 1.00
        $this->assertEquals(4.00, $op3->quantity_produced);
        $this->assertEquals(0.00, $op3->quantity_rejected);
        $this->assertEquals(1.00, $op3->quantity_scrapped);

        // Verify order level scrap count is 1.00
        $order->refresh();
        $this->assertEquals(1.00, $order->quantity_scrapped);

        // Verify operation reconciliation method
        $executionService->reconcileOperationQuantities($op3->id);
        $op3->refresh();
        $this->assertEquals(1.00, $op3->quantity_scrapped);
    }
}
