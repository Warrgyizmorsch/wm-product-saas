<?php

namespace Tests\Feature\Production;

use App\Core\Tenant\TenantContext;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderSpare;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MaintenanceSpareService;
use App\Domains\Production\Services\MaintenanceWorkOrderService;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MaintenanceWorkOrderCancellationStockReturnTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Warehouse $warehouse;
    private Product $productA;
    private Product $productB;
    private Product $productC;
    private MaintenanceWorkOrderService $woService;
    private MaintenanceSpareService $spareService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelBack();
        Carbon::setTestNow(null);
        app(TenantContext::class)->clear();

        $this->tenant = Tenant::create([
            'name'   => 'Test Maintenance Tenant',
            'slug'   => 'test-maint-tenant-' . uniqid(),
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Maint Admin',
            'email'     => 'maintadmin_' . uniqid() . '@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->actingAs($this->user);
        app(TenantContext::class)->setTenant($this->tenant);
        app(Tenancy::class)->setTenant($this->tenant);
        app()->instance('tenant', $this->tenant);

        $this->workCenter = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Main Machining Center',
            'code'                  => 'WC-MC-01',
            'cost_per_hour'         => 60.00,
            'capacity_per_hour'     => 10.00,
            'efficiency_percentage' => 100.00,
            'status'                => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'          => $this->tenant->id,
            'work_center_id'     => $this->workCenter->id,
            'name'               => 'Lathe 01',
            'code'               => 'MCH-LTH-01',
            'status'             => Machine::STATUS_ACTIVE,
            'current_state'      => 'Idle',
            'maintenance_status' => 'none',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Central Spare Store',
            'code'      => 'WH-CSS-01',
        ]);

        $this->productA = Product::create([
            'tenant_id'                  => $this->tenant->id,
            'name'                       => 'Bearing A',
            'sku'                        => 'SPR-BRG-A',
            'cost_price'                 => 50.00,
            'inventory_valuation_method' => 'FIFO',
        ]);

        $this->productB = Product::create([
            'tenant_id'                  => $this->tenant->id,
            'name'                       => 'Filter B',
            'sku'                        => 'SPR-FLT-B',
            'cost_price'                 => 20.00,
            'inventory_valuation_method' => 'FIFO',
        ]);

        $this->productC = Product::create([
            'tenant_id'                  => $this->tenant->id,
            'name'                       => 'Seal C',
            'sku'                        => 'SPR-SL-C',
            'cost_price'                 => 15.00,
            'inventory_valuation_method' => 'FIFO',
        ]);

        $this->woService = app(MaintenanceWorkOrderService::class);
        $this->spareService = app(MaintenanceSpareService::class);
    }

    /**
     * Helper to stock a product in the test warehouse.
     */
    private function stockProduct(Product $product, float $quantity, float $unitCost): StockTransaction
    {
        return StockService::recordInflow(
            $this->tenant->id,
            $product->id,
            $this->warehouse->id,
            $quantity,
            $unitCost,
            'Opening Stock'
        );
    }

    /** 1. A scheduled, not-started work order can be cancelled. */
    public function test_scheduled_not_started_work_order_can_be_cancelled(): void
    {
        $this->stockProduct($this->productA, 20.0, 50.00);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Scheduled inspection',
        ]);

        $wo = $this->woService->scheduleWorkOrder(
            $wo->id,
            $this->tenant->id,
            now()->addDay(),
            now()->addDays(2),
            $this->user->id,
            $this->user->id
        );

        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_SCHEDULED, $wo->status);
        $this->assertNull($wo->actual_start);

        // Add and issue spare part
        $spare = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->productA->id,
            $this->warehouse->id,
            4.0
        );

        $this->spareService->issueSparePart($spare->id, $this->tenant->id, 4.0, $this->user->id);

        $this->assertEquals(16.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));

        // Cancel the scheduled work order
        $cancelledWo = $this->woService->cancelWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Cancelled before start');

        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_CANCELLED, $cancelledWo->status);
        // Stock must be restored to initial 20.0
        $this->assertEquals(20.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));
    }

    /** 2. Cancellation of an in_progress work order is rejected and stock remains unchanged. */
    public function test_cancellation_of_in_progress_work_order_is_rejected_and_stock_remains_unchanged(): void
    {
        $this->stockProduct($this->productA, 20.0, 50.00);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'In progress test',
            'assignments'         => [[
                'assignment_type' => 'internal',
                'technician_id'   => $this->user->id,
                'technician_name' => $this->user->name,
                'hourly_rate'     => 40.00,
            ]],
        ]);

        $spare = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->productA->id,
            $this->warehouse->id,
            5.0
        );
        $this->spareService->issueSparePart($spare->id, $this->tenant->id, 5.0, $this->user->id);

        $this->assertEquals(15.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));

        // Start work order
        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $wo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS, $wo->status);
        $this->assertNotNull($wo->actual_start);

        // Attempt cancellation -> must be rejected
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel a Work Order that has already started.');

        try {
            $this->woService->cancelWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Attempt cancel');
        } finally {
            $wo->refresh();
            $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS, $wo->status);
            // Stock must remain deducted (15.0)
            $this->assertEquals(15.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));
        }
    }

    /** 3. Cancellation is rejected if authoritative start timestamp is populated, even if status is inconsistent. */
    public function test_cancellation_is_rejected_if_authoritative_start_timestamp_is_populated_even_if_status_is_inconsistent(): void
    {
        $this->stockProduct($this->productA, 20.0, 50.00);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Inconsistent status test',
        ]);

        $spare = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->productA->id,
            $this->warehouse->id,
            3.0
        );
        $this->spareService->issueSparePart($spare->id, $this->tenant->id, 3.0, $this->user->id);

        // Manually set actual_start while leaving status as scheduled
        $wo->update([
            'status'       => ProductionMaintenanceWorkOrder::STATUS_SCHEDULED,
            'actual_start' => now()->subHour(),
        ]);
        $wo->refresh();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel a Work Order that has already started.');

        try {
            $this->woService->cancelWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Attempt cancel inconsistent');
        } finally {
            $wo->refresh();
            $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_SCHEDULED, $wo->status);
            $this->assertEquals(17.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));
        }
    }

    /** 4, 5, 6, 7, 8. Comprehensive stock return verification on cancellation. */
    public function test_cancellation_returns_full_issued_qty_for_all_spares_skips_unissued_and_preserves_history(): void
    {
        $this->stockProduct($this->productA, 20.0, 50.00);
        $this->stockProduct($this->productB, 30.0, 20.00);
        $this->stockProduct($this->productC, 10.0, 15.00);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Multi spare return test',
        ]);

        // Spare 1: requested 5, fully issued 5
        $spare1 = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->productA->id,
            $this->warehouse->id,
            5.0
        );
        $this->spareService->issueSparePart($spare1->id, $this->tenant->id, 5.0, $this->user->id);

        // Spare 2: requested 10, partially issued 3
        $spare2 = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->productB->id,
            $this->warehouse->id,
            10.0
        );
        $this->spareService->issueSparePart($spare2->id, $this->tenant->id, 3.0, $this->user->id);

        // Spare 3: requested 4, unissued (0 issued)
        $spare3 = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->productC->id,
            $this->warehouse->id,
            4.0
        );

        // Verify stock post-issue
        $this->assertEquals(15.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));
        $this->assertEquals(27.0, (float) ProductWarehouseStock::where('product_id', $this->productB->id)->value('quantity'));
        $this->assertEquals(10.0, (float) ProductWarehouseStock::where('product_id', $this->productC->id)->value('quantity'));

        // Capture original OUT transactions
        $spare1->refresh();
        $spare2->refresh();
        $origOut1 = StockTransaction::findOrFail($spare1->stock_transaction_id);
        $origOut2 = StockTransaction::findOrFail($spare2->stock_transaction_id);

        $this->assertEquals('OUT', $origOut1->type);
        $this->assertEquals(5.0, (float) $origOut1->quantity);
        $this->assertEquals('OUT', $origOut2->type);
        $this->assertEquals(3.0, (float) $origOut2->quantity);

        // Cancel work order
        $cancelledWo = $this->woService->cancelWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'All parts to be returned');
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_CANCELLED, $cancelledWo->status);

        // Verify Rule 2: Stock balances restored correctly
        $this->assertEquals(20.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));
        $this->assertEquals(30.0, (float) ProductWarehouseStock::where('product_id', $this->productB->id)->value('quantity'));
        $this->assertEquals(10.0, (float) ProductWarehouseStock::where('product_id', $this->productC->id)->value('quantity'));

        // Verify original OUT transactions remain completely unchanged
        $origOut1Fresh = StockTransaction::findOrFail($origOut1->id);
        $this->assertEquals('OUT', $origOut1Fresh->type);
        $this->assertEquals(5.0, (float) $origOut1Fresh->quantity);
        $this->assertEquals((float) $origOut1->unit_cost, (float) $origOut1Fresh->unit_cost);

        $origOut2Fresh = StockTransaction::findOrFail($origOut2->id);
        $this->assertEquals('OUT', $origOut2Fresh->type);
        $this->assertEquals(3.0, (float) $origOut2Fresh->quantity);
        $this->assertEquals((float) $origOut2->unit_cost, (float) $origOut2Fresh->unit_cost);

        // Verify compensating IN return transactions
        $returnTxns = StockTransaction::where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'MaintenanceWorkOrder')
            ->where('reference_id', $wo->id)
            ->where('type', 'IN')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $returnTxns); // Product A and Product B returned; Product C skipped!

        // Return transaction for Product A
        $retA = $returnTxns->firstWhere('product_id', $this->productA->id);
        $this->assertNotNull($retA);
        $this->assertEquals($this->warehouse->id, $retA->warehouse_id);
        $this->assertEquals(5.0, (float) $retA->quantity); // Full issued_qty
        $this->assertEquals(50.00, (float) $retA->unit_cost);
        $this->assertEquals(250.00, (float) $retA->total_value);
        $this->assertEquals('MaintenanceWorkOrder', $retA->reference_type);
        $this->assertEquals($wo->id, $retA->reference_id);

        // Return transaction for Product B (issued was 3, requested was 10)
        $retB = $returnTxns->firstWhere('product_id', $this->productB->id);
        $this->assertNotNull($retB);
        $this->assertEquals($this->warehouse->id, $retB->warehouse_id);
        $this->assertEquals(3.0, (float) $retB->quantity); // Only issued_qty (3.0), NOT requested_qty (10.0)
        $this->assertEquals(20.00, (float) $retB->unit_cost);
        $this->assertEquals(60.00, (float) $retB->total_value);
        $this->assertEquals('MaintenanceWorkOrder', $retB->reference_type);
        $this->assertEquals($wo->id, $retB->reference_id);

        // No return transaction for unissued Product C
        $retC = $returnTxns->firstWhere('product_id', $this->productC->id);
        $this->assertNull($retC);
    }

    /** 9. Repeated cancellation does not create duplicate stock returns. */
    public function test_repeated_cancellation_does_not_create_duplicate_stock_returns(): void
    {
        $this->stockProduct($this->productA, 20.0, 50.00);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Idempotency test',
        ]);

        $spare = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->productA->id,
            $this->warehouse->id,
            5.0
        );
        $this->spareService->issueSparePart($spare->id, $this->tenant->id, 5.0, $this->user->id);

        // First cancellation
        $this->woService->cancelWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'First cancel');

        $this->assertEquals(20.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));
        $inCountFirst = StockTransaction::where('reference_type', 'MaintenanceWorkOrder')
            ->where('reference_id', $wo->id)
            ->where('type', 'IN')
            ->count();
        $this->assertEquals(1, $inCountFirst);

        // Second cancellation attempt
        $secondResult = $this->woService->cancelWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Second cancel attempt');

        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_CANCELLED, $secondResult->status);
        $this->assertEquals(20.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));

        $inCountSecond = StockTransaction::where('reference_type', 'MaintenanceWorkOrder')
            ->where('reference_id', $wo->id)
            ->where('type', 'IN')
            ->count();
        $this->assertEquals(1, $inCountSecond); // Unchanged: NO duplicate return!
    }

    /** 10. If a stock return fails, the cancellation and earlier returns are rolled back consistently. */
    public function test_if_stock_return_fails_cancellation_and_earlier_returns_are_rolled_back(): void
    {
        $this->stockProduct($this->productA, 20.0, 50.00);
        $this->stockProduct($this->productB, 20.0, 20.00);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Rollback test',
        ]);

        $spare1 = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->productA->id,
            $this->warehouse->id,
            4.0
        );
        $this->spareService->issueSparePart($spare1->id, $this->tenant->id, 4.0, $this->user->id);

        $spare2 = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->productB->id,
            $this->warehouse->id,
            3.0
        );
        $this->spareService->issueSparePart($spare2->id, $this->tenant->id, 3.0, $this->user->id);

        // Stock after issues: A = 16.0, B = 17.0
        $this->assertEquals(16.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));
        $this->assertEquals(17.0, (float) ProductWarehouseStock::where('product_id', $this->productB->id)->value('quantity'));

        $failOnProductB = true;
        StockTransaction::saving(function ($txn) use (&$failOnProductB) {
            if ($failOnProductB && $txn->type === 'IN' && $txn->reference_type === 'MaintenanceWorkOrder' && (int)$txn->product_id === (int)$this->productB->id) {
                throw new \RuntimeException('Simulated stock inflow ledger failure on spare 2.');
            }
        });

        try {
            $this->woService->cancelWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Will fail');
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e);
            $this->assertStringContainsString('Simulated stock inflow ledger failure on spare 2.', $e->getMessage());
        } finally {
            $failOnProductB = false;
        }

        // Verify transactional consistency: work order is NOT cancelled
        $wo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_DRAFT, $wo->status);

        // Verify spare 1 return was rolled back: Product A stock remains 16.0 (not 20.0)
        $this->assertEquals(16.0, (float) ProductWarehouseStock::where('product_id', $this->productA->id)->value('quantity'));
        $this->assertEquals(17.0, (float) ProductWarehouseStock::where('product_id', $this->productB->id)->value('quantity'));

        // Verify no IN transactions exist for this work order
        $inTxnCount = StockTransaction::where('reference_type', 'MaintenanceWorkOrder')
            ->where('reference_id', $wo->id)
            ->where('type', 'IN')
            ->count();
        $this->assertEquals(0, $inTxnCount);
    }

    /** 11. Completed work orders remain non-cancellable. */
    public function test_completed_work_orders_remain_non_cancellable(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Completed cancel guard',
            'assignments'         => [[
                'assignment_type' => 'internal',
                'technician_id'   => $this->user->id,
                'technician_name' => $this->user->name,
                'hourly_rate'     => 40.00,
            ]],
        ]);

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Done');

        $wo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_COMPLETED, $wo->status);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel a completed Work Order.');

        $this->woService->cancelWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Attempt cancel completed');
    }

    /** 12. Existing cancellation-reason validation and machine/downtime behavior continue to work. */
    public function test_breakdown_cancellation_validation_and_downtime_machine_restoration(): void
    {
        $breakdownWo = $this->woService->reportBreakdown(
            $this->tenant->id,
            $this->machine->id,
            'Overheating motor',
            $this->user->id,
            'critical'
        );

        $this->assertEquals('breakdown', $this->machine->fresh()->maintenance_status);
        $downtimeId = $breakdownWo->downtime_id;
        $this->assertNotNull($downtimeId);

        // Missing cancellation reason on breakdown throws
        try {
            $this->woService->cancelWorkOrder($breakdownWo->id, $this->tenant->id, $this->user->id, '');
            $this->fail('Expected exception for empty cancellation reason on breakdown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('A cancellation reason is required for breakdown work orders.', $e->getMessage());
        }

        // Cancel with valid reason
        $reason = 'Sensor false positive, recalibrated by technician.';
        $cancelledWo = $this->woService->cancelWorkOrder($breakdownWo->id, $this->tenant->id, $this->user->id, $reason);

        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_CANCELLED, $cancelledWo->status);
        $this->assertEquals($reason, $cancelledWo->decision_note);

        // Machine restored to Active / Idle
        $this->machine->refresh();
        $this->assertEquals(Machine::STATUS_ACTIVE, $this->machine->status);
        $this->assertEquals('none', $this->machine->maintenance_status);
        $this->assertEquals('Idle', $this->machine->current_state);

        // Associated downtime closed
        $downtime = ProductionMachineDowntime::findOrFail($downtimeId);
        $this->assertEquals(ProductionMachineDowntime::STATUS_CLOSED, $downtime->status);
        $this->assertNotNull($downtime->end_time);
        $this->assertNotNull($downtime->duration_minutes);
    }
}
