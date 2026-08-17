<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WipReconciliationAndPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_wip_transfer_reconciles_available_quantity_and_pipeline(): void
    {
        $tenant = Tenant::create([
            'name' => 'WIP Test Tenant',
            'slug' => 'wip-test-tenant-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@wiptest-' . uniqid() . '.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $uom = Uom::create([
            'tenant_id' => $tenant->id,
            'name' => 'Units',
            'code' => 'PCS',
        ]);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Radiator Sub-Assembly',
            'sku' => 'FG-RAD-' . uniqid(),
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $uom->id,
        ]);

        $wc1 = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'code' => 'WC-01',
            'name' => 'Fin Milling Center',
            'status' => 'active',
        ]);

        $wc2 = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'code' => 'WC-02',
            'name' => 'CAB Furnace Center',
            'status' => 'active',
        ]);

        $routing = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'RT-RAD-' . uniqid(),
            'name' => 'Radiator Assembly Routing',
            'product_id' => $product->id,
            'status' => 'approved',
        ]);

        $rOp1 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Fin Milling',
            'work_center_id' => $wc1->id,
        ]);

        $rOp2 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Core Fluxing',
            'work_center_id' => $wc2->id,
        ]);

        $rOp3 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Continuous Brazing',
            'work_center_id' => $wc2->id,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $tenant->id,
            'order_number' => 'PO-WIP-' . uniqid(),
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
            'name' => 'Fin Milling',
            'work_center_id' => $wc1->id,
            'status' => 'ready',
        ]);

        $op2 = ProductionOrderOperation::create([
            'tenant_id' => $tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp2->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Core Fluxing',
            'work_center_id' => $wc2->id,
            'status' => 'waiting',
        ]);

        $op3 = ProductionOrderOperation::create([
            'tenant_id' => $tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $rOp3->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Continuous Brazing',
            'work_center_id' => $wc2->id,
            'status' => 'waiting',
        ]);

        $wipService = app(ProductionWipService::class);
        $wipService->initializeWip($order->id);

        $batchService = app(BatchProductionService::class);
        $batch = $batchService->createBatch($tenant->id, $order->id, $product->id, 5.00, $op1->id);

        $executionService = app(ProductionExecutionService::class);

        // Op 1: Log 3 produced, 1 rejected, 1 scrapped -> 3 good output transferred to Op 2
        $executionService->logProgress($op1->id, 3.00, 1.00, 1.00, 0, 10, 'Op 10', null, $user->id, true, null, $batch->id);

        // Op 2: Log 3 produced (matches 3 good output transferred from Op 10)
        $executionService->logProgress($op2->id, 3.00, 0.00, 0.00, 0, 15, 'Op 20', null, $user->id, true, null, $batch->id);

        // Op 3: Log 1 produced, 1 rejected, 1 scrapped
        $executionService->logProgress($op3->id, 1.00, 1.00, 1.00, 0, 20, 'Op 30', null, $user->id, false, null, $batch->id);

        // Self-healing reconciliation test
        $wipService->reconcileOrderWipCards($order->id);

        $wipOp1 = ProductionWip::where('production_order_id', $order->id)
            ->where('production_batch_id', $batch->id)
            ->where('current_routing_operation_id', $rOp1->id)
            ->first();

        $wipOp2 = ProductionWip::where('production_order_id', $order->id)
            ->where('production_batch_id', $batch->id)
            ->where('current_routing_operation_id', $rOp2->id)
            ->first();

        $wipOp3 = ProductionWip::where('production_order_id', $order->id)
            ->where('production_batch_id', $batch->id)
            ->where('current_routing_operation_id', $rOp3->id)
            ->first();

        $mainWip = ProductionWip::where('production_order_id', $order->id)
            ->whereNull('production_batch_id')
            ->first();

        // Main Order WIP = 5.00 unbatched
        $this->assertEquals(5.00, $mainWip->available_quantity);

        // Transferred upstream WIP cards have 0.00 available_quantity and status 'transferred'
        $this->assertEquals(0.00, $wipOp1->available_quantity);
        $this->assertEquals('transferred', $wipOp1->status);

        $this->assertEquals(0.00, $wipOp2->available_quantity);
        $this->assertEquals('transferred', $wipOp2->status);

        // Op 30 Active WIP = 1.00 active available
        $this->assertEquals(1.00, $wipOp3->available_quantity);
        $this->assertEquals('active', $wipOp3->status);

        // Test Batch Pipeline Stepper Data
        $pipelineData = $wipService->getBatchPipelineData($order->id);
        $this->assertCount(1, $pipelineData);

        $batchPipeline = $pipelineData->first();
        $this->assertEquals($batch->batch_number, $batchPipeline['batch_number']);
        $this->assertCount(3, $batchPipeline['stages']);

        $stage1 = $batchPipeline['stages'][0];
        $this->assertEquals('OP-10', $stage1['operation_number']);
        $this->assertEquals(3.00, $stage1['good_output']);
        $this->assertEquals(1.00, $stage1['scrapped']);

        $stage3 = $batchPipeline['stages'][2];
        $this->assertEquals('OP-30', $stage3['operation_number']);
        $this->assertEquals(1.00, $stage3['good_output']);
        $this->assertEquals(1.00, $stage3['scrapped']);
        $this->assertTrue($stage3['is_current']);
    }
}
