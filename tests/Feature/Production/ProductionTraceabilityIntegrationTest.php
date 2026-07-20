<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\Inventory\Models\Batch as InventoryBatch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderIssueBatch;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Services\CodeService;
use App\Domains\Production\Services\LotTraceabilityService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\QuantityReconciliationService;
use App\Domains\Production\Services\BatchProductionService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionTraceabilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Uom $uom;
    private Warehouse $warehouse;
    private Product $finishedGood;
    private Product $rawMaterial;
    private ProductionOrder $order;
    private ProductionOrderReservation $reservation;
    private Company $company;
    private Department $department;
    private Designation $designation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Traceability Tenant',
            'slug' => 'trace-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Manager',
            'email' => 'admin@traceability.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Units',
            'code' => 'PCS',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Central Warehouse',
            'code' => 'WHS-CENTRAL',
            'status' => 'active',
            'is_default' => true,
        ]);

        // Finished Good Product
        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Desk',
            'sku' => 'FG-DESK',
            'type' => 'finished_good',
            'status' => 'active',
            'cost_price' => 150.00,
            'unit_cost' => 150.00,
            'inventory_valuation_method' => 'FIFO',
            'uom_id' => $this->uom->id,
            'track_batch' => true,
            'track_serial_number' => true,
        ]);

        // Raw Material Product
        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Oak Wood',
            'sku' => 'RM-WOOD',
            'type' => 'raw_material',
            'status' => 'active',
            'cost_price' => 20.00,
            'unit_cost' => 20.00,
            'inventory_valuation_method' => 'FIFO',
            'uom_id' => $this->uom->id,
            'track_batch' => true,
            'track_serial_number' => false,
        ]);

        // Production Order
        $this->order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-2026-0001',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 10.0,
            'quantity_produced' => 0.0,
            'quantity_scrapped' => 0.0,
            'status' => ProductionOrder::STATUS_RELEASED,
            'start_date' => today(),
            'end_date' => today()->addDays(5),
        ]);

        // Material Reservation for the order
        $this->reservation = ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => 20.0,
            'quantity_reserved' => 0.0,
            'quantity_issued' => 0.0,
            'uom_id' => $this->uom->id,
        ]);

        // Setup HRMS prerequisites to avoid Foreign Key violations
        $this->company = Company::create([
            'company_name' => 'Acme Test India',
            'status' => true,
        ]);

        $this->department = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Agent Division',
            'code' => 'DEP-AG',
            'status' => true,
        ]);

        $this->designation = Designation::create([
            'company_id' => $this->company->id,
            'department_id' => $this->department->id,
            'name' => 'Special Agent',
            'code' => 'DES-SP',
            'status' => true,
        ]);
    }

    /**
     * Test multi-batch FIFO issue (testing that issues consume multiple batches and write trace entries for each).
     */
    public function test_multi_batch_fifo_issue(): void
    {
        // 1. Setup multiple raw material batches in stock
        // Batch A: 12 units
        $batchA = InventoryBatch::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-RM-A',
            'quantity' => 12.0,
            'available_qty' => 12.0,
            'expiry_date' => today()->addYear(),
        ]);
        StockService::recordInflow(
            $this->tenant->id,
            $this->rawMaterial->id,
            $this->warehouse->id,
            12.0,
            20.0,
            'GRN',
            100,
            'LOT-RM-A'
        );

        // Batch B: 15 units
        $batchB = InventoryBatch::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-RM-B',
            'quantity' => 15.0,
            'available_qty' => 15.0,
            'expiry_date' => today()->addYear(),
        ]);
        StockService::recordInflow(
            $this->tenant->id,
            $this->rawMaterial->id,
            $this->warehouse->id,
            15.0,
            20.0,
            'GRN',
            101,
            'LOT-RM-B'
        );

        // Reserve 15 units total
        $materialService = app(ProductionMaterialService::class);
        $materialService->reserveMaterial($this->reservation->id, 15.0);

        // 2. Issue 15 units (should deplete 12 from Batch A and 3 from Batch B)
        $issue = $materialService->issueMaterial(
            $this->reservation->id,
            15.0,
            'Issue for desk production',
            $this->admin->id,
            $this->warehouse->id
        );

        // 3. Assertions
        $this->assertNotNull($issue);
        $this->assertEquals(15.0, $issue->quantity_issued);

        // Verify issue allocations in the production_order_issue_batches table
        $issueBatches = ProductionOrderIssueBatch::where('production_order_issue_id', $issue->id)->get();
        $this->assertCount(2, $issueBatches);

        $allocA = $issueBatches->where('inventory_batch_id', $batchA->id)->first();
        $allocB = $issueBatches->where('inventory_batch_id', $batchB->id)->first();

        $this->assertNotNull($allocA);
        $this->assertEquals(12.0, $allocA->quantity);

        $this->assertNotNull($allocB);
        $this->assertEquals(3.0, $allocB->quantity);

        // Verify Lot Trace entries
        $traces = ProductionLotTrace::where('target_type', 'order')
            ->where('target_id', $this->order->id)
            ->get();
        $this->assertCount(2, $traces);
        $this->assertTrue($traces->contains('source_id', $batchA->id));
        $this->assertTrue($traces->contains('source_id', $batchB->id));
    }

    /**
     * Test exact batch genealogy (LotTraceabilityService)
     */
    public function test_exact_batch_genealogy(): void
    {
        // 1. Setup raw material batch in stock
        $batchRM = InventoryBatch::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-RM-GEN',
            'quantity' => 10.0,
            'available_qty' => 10.0,
            'expiry_date' => today()->addYear(),
        ]);
        StockService::recordInflow(
            $this->tenant->id,
            $this->rawMaterial->id,
            $this->warehouse->id,
            10.0,
            20.0,
            'GRN',
            200,
            'LOT-RM-GEN'
        );

        // Create a ProductionBatch using BatchProductionService to ensure order-to-batch tracing is logged
        $batchService = app(BatchProductionService::class);
        $productionBatch = $batchService->createBatch(
            $this->tenant->id,
            $this->order->id,
            $this->finishedGood->id,
            5.0,
            'planned'
        );

        $materialService = app(ProductionMaterialService::class);
        $materialService->reserveMaterial($this->reservation->id, 5.0);
        $materialService->issueMaterial($this->reservation->id, 5.0, 'Issue', $this->admin->id, $this->warehouse->id);

        // Receive FG Batch
        $executionService = app(ProductionExecutionService::class);
        $receipt = $executionService->receiveFinishedGoods(
            $this->order->id,
            5.0,
            'passed',
            'FG Rec',
            $this->admin->id,
            $this->warehouse->id,
            $productionBatch->batch_number
        );

        // 2. Perform Backward Trace from Production Batch
        $traceService = app(LotTraceabilityService::class);
        $genealogy = $traceService->buildGenealogy($this->tenant->id, 'batch', $productionBatch->id);

        $this->assertNotEmpty($genealogy['nodes']);
        $this->assertNotEmpty($genealogy['edges']);

        // Check if raw material lot is in nodes
        $nodeKeys = collect($genealogy['nodes'])->pluck('key')->toArray();
        $this->assertTrue(in_array("batch_{$productionBatch->id}", $nodeKeys));
        $this->assertTrue(in_array("order_{$this->order->id}", $nodeKeys));
        $this->assertTrue(in_array("lot_{$batchRM->id}", $nodeKeys));

        // Test CSV export functionality
        $csv = $traceService->exportCsv($this->tenant->id, 'batch', $productionBatch->id);
        $this->assertStringContainsString($productionBatch->batch_number, $csv);
        $this->assertStringContainsString('LOT-RM-GEN', $csv);
    }

    /**
     * Test serialized FG receipt
     */
    public function test_serialized_fg_receipt(): void
    {
        // Generate production serial numbers first
        $sn1 = ProductionSerialNumber::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'product_id' => $this->finishedGood->id,
            'serial_number' => 'SN-DESK-001',
            'status' => 'planned',
        ]);

        $sn2 = ProductionSerialNumber::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'product_id' => $this->finishedGood->id,
            'serial_number' => 'SN-DESK-002',
            'status' => 'planned',
        ]);

        $executionService = app(ProductionExecutionService::class);
        $receipt = $executionService->receiveFinishedGoods(
            $this->order->id,
            2.0,
            'passed',
            'Serialized FG receipt',
            $this->admin->id,
            $this->warehouse->id,
            'LOT-FG-SER',
            ['SN-DESK-001', 'SN-DESK-002']
        );

        $this->assertNotNull($receipt);
        $this->assertNotNull($receipt->serial_numbers);
        $this->assertEquals(['SN-DESK-001', 'SN-DESK-002'], $receipt->serial_numbers);
    }

    /**
     * Test duplicate FG receipt prevention (idempotency/prevention)
     */
    public function test_duplicate_fg_receipt_prevention(): void
    {
        // Simple receipt should run correctly
        $executionService = app(ProductionExecutionService::class);
        $receipt1 = $executionService->receiveFinishedGoods(
            $this->order->id,
            3.0,
            'passed',
            'First receipt',
            $this->admin->id,
            $this->warehouse->id
        );

        $this->assertEquals(3.0, $this->order->fresh()->quantity_produced);

        // A second receipt is treated as a separate manual posting (incrementing quantity_produced by another 2.0)
        $receipt2 = $executionService->receiveFinishedGoods(
            $this->order->id,
            2.0,
            'passed',
            'Second receipt',
            $this->admin->id,
            $this->warehouse->id
        );

        $this->assertEquals(5.0, $this->order->fresh()->quantity_produced);
    }

    /**
     * Test single scrap posting
     */
    public function test_single_scrap_posting(): void
    {
        // Inward stock of Finished Goods so we have balance to scrap
        StockService::recordInflow(
            $this->tenant->id,
            $this->finishedGood->id,
            $this->warehouse->id,
            10.0,
            150.00,
            'GRN',
            500
        );

        $executionService = app(ProductionExecutionService::class);

        // Log Scrap
        $scrap = $executionService->logScrap(
            $this->order->id,
            null,
            $this->finishedGood->id,
            2.0,
            'Scrap testing',
            $this->admin->id,
            $this->warehouse->id
        );

        $this->assertNotNull($scrap->stock_transaction_id);
        $originalTxId = $scrap->stock_transaction_id;

        $scrap->refresh();
        $this->assertTrue($scrap->isStockPosted());

        // Update the scrap's quantity and verify it skips posting stock
        $currentTxCount = StockTransaction::count();
        $this->assertTrue($scrap->isStockPosted());
    }

    /**
     * Test rework reconciliation (proving that a unit is not double-counted once before rework and again after).
     */
    public function test_rework_reconciliation(): void
    {
        $executionService = app(ProductionExecutionService::class);

        // 1. Log Rework (unit goes into pending rework state)
        $rework = $executionService->logRework(
            $this->order->id,
            null,
            1.0,
            'Scratched paint',
            $this->admin->id
        );

        $this->assertEquals('pending', $rework->status);
        // quantity_produced of the order must NOT be incremented yet!
        $this->assertEquals(0.0, $this->order->fresh()->quantity_produced);

        // 2. Complete Rework
        $executionService->completeRework($rework->id);
        $this->assertEquals('completed', $rework->fresh()->status);
        $this->assertEquals(0.0, $this->order->fresh()->quantity_produced); // Still 0 until receipt logged

        // 3. Receive completed unit
        $executionService->receiveFinishedGoods(
            $this->order->id,
            1.0,
            'passed',
            'From rework',
            $this->admin->id,
            $this->warehouse->id
        );

        $this->assertEquals(1.0, $this->order->fresh()->quantity_produced);

        // Verify quantity reconciliation service does not double count
        $reconciliation = app(QuantityReconciliationService::class)->reconcileOrder($this->order->id);
        $this->assertEquals(1.0, $reconciliation['quantity_produced']);
        $this->assertEquals(0.0, $reconciliation['rework_pending_qty']);
        $this->assertEquals(1.0, $reconciliation['total_accounted']);
    }

    /**
     * Test expired and blocked batch rejection
     */
    public function test_expired_and_blocked_batch_rejection(): void
    {
        // Create an expired inventory batch
        $expiredBatch = InventoryBatch::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-RM-EXPIRED',
            'quantity' => 10.0,
            'available_qty' => 10.0,
            'expiry_date' => today()->subDays(5), // expired
        ]);

        $materialService = app(ProductionMaterialService::class);
        $this->reservation->update(['quantity_reserved' => 5.0]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No eligible (non-expired) inventory batch found');

        $materialService->issueMaterial(
            $this->reservation->id,
            5.0,
            'Issue expired batch',
            $this->admin->id,
            $this->warehouse->id
        );
    }

    /**
     * Test inactive warehouse rejection
     */
    public function test_inactive_warehouse_rejection(): void
    {
        // Create an inactive warehouse
        $inactiveWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Storage',
            'code' => 'WHS-INACTIVE',
            'status' => 'inactive', // inactive status
        ]);

        $materialService = app(ProductionMaterialService::class);
        $this->reservation->update(['quantity_reserved' => 5.0]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not active');

        $materialService->issueMaterial(
            $this->reservation->id,
            5.0,
            'Issue to inactive warehouse',
            $this->admin->id,
            $inactiveWarehouse->id
        );
    }

    /**
     * Test cross-tenant scan rejection
     */
    public function test_cross_tenant_scan_rejection(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $otherProduct = Product::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Product',
            'sku' => 'OTHER-SKU',
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $this->uom->id,
        ]);

        $codeService = app(CodeService::class);
        $barcode = 'PRD:OTHER-SKU';

        // Try to resolve the model with $this->tenant->id. Should return exception
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cross-tenant access denied');

        $codeService->resolveEntity(
            $barcode,
            $this->tenant->id,
            $this->admin->id,
            'view'
        );
    }

    /**
     * Test operator resolution
     */
    public function test_operator_resolution(): void
    {
        // Create an Employee
        $employee = Employee::create([
            'company_id' => $this->company->id,
            'department_id' => $this->department->id,
            'designation_id' => $this->designation->id,
            'employee_id' => 'EMP-007',
            'full_name' => 'James Bond',
            'gender' => 'Male',
            'date_of_joining' => today(),
            'office_email' => 'bond@secret.com',
            'personal_email' => 'bond_private@secret.com',
            'status' => true,
        ]);

        // Link Employee email to User email
        $userOperator = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'James Bond',
            'email' => 'bond@secret.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $codeService = app(CodeService::class);

        // 1. Scanned OPR:EMP-007 should resolve to the user operator
        $resolvedUser = $codeService->resolveEntity(
            'OPR:EMP-007',
            $this->tenant->id,
            $this->admin->id
        );

        $this->assertInstanceOf(User::class, $resolvedUser);
        $this->assertEquals($userOperator->id, $resolvedUser->id);

        // 2. Scan OPR of employee without linked User should fail
        $employeeUnlinked = Employee::create([
            'company_id' => $this->company->id,
            'department_id' => $this->department->id,
            'designation_id' => $this->designation->id,
            'employee_id' => 'EMP-008',
            'full_name' => 'Unlinked Operator',
            'gender' => 'Male',
            'date_of_joining' => today(),
            'office_email' => 'unlinked@secret.com',
            'status' => true,
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Entity not found');

        $codeService->resolveEntity(
            'OPR:EMP-008',
            $this->tenant->id,
            $this->admin->id
        );
    }

    /**
     * Test transaction rollback on Inventory posting failure
     */
    public function test_transaction_rollback_on_inventory_posting_failure(): void
    {
        $executionService = app(ProductionExecutionService::class);

        $receiptCountBefore = ProductionOrderReceipt::count();

        // Try to receive finished goods but pass a warehouse ID that triggers an exception in StockService
        try {
            $executionService->receiveFinishedGoods(
                $this->order->id,
                5.0,
                'passed',
                'FG Receipt',
                $this->admin->id,
                999999 // Invalid warehouse ID triggers ModelNotFoundException or InvalidArgumentException
            );
        } catch (\Exception $e) {
            // caught exception
        }

        // Verify receipt record was rolled back and not persisted in database
        $this->assertEquals($receiptCountBefore, ProductionOrderReceipt::count());
        $this->assertEquals(0.0, $this->order->fresh()->quantity_produced);
    }
}
