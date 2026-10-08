<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Batch as InventoryBatch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Repositories\ProductionBatchRepository;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\QualityInspectionService;
use App\Domains\Production\Services\ReworkService;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use App\Models\User;
use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionAuditVerifiedFixesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Company $company1;
    private Company $company2;
    private Branch $branch1;
    private Branch $branch2;
    private Warehouse $warehouse;
    private Product $product;
    private Uom $uom;
    private WorkCenter $workCenter;
    private Routing $routing;
    private RoutingOperation $routingOp;
    private ProductionQualityPlan $qualityPlan;
    private ProductionOrderOperation $orderOp;
    private ProductionOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Audit Test Tenant',
            'slug'   => 'audit-tenant',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Audit Admin',
            'email'     => 'admin@audit.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->company1 = Company::create([
            'tenant_id'    => $this->tenant->id,
            'company_name' => 'Manufacturing Corp Alpha',
            'status'       => true,
        ]);

        $this->company2 = Company::create([
            'tenant_id'    => $this->tenant->id,
            'company_name' => 'Manufacturing Corp Beta',
            'status'       => true,
        ]);

        $this->branch1 = Branch::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company1->id,
            'name'       => 'Plant North',
            'code'       => 'PLT-N',
        ]);

        $this->branch2 = Branch::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company2->id,
            'name'       => 'Plant South',
            'code'       => 'PLT-S',
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Piece',
            'code'      => 'PCS',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company1->id,
            'branch_id'  => $this->branch1->id,
            'name'       => 'Plant North Warehouse',
            'code'       => 'WHS-PN-01',
            'status'     => 'active',
        ]);

        $this->product = Product::create([
            'tenant_id'    => $this->tenant->id,
            'company_id'   => $this->company1->id,
            'branch_id'    => $this->branch1->id,
            'name'         => 'Machined Gear',
            'sku'          => 'GEAR-001',
            'type'         => 'standard',
            'uom_id'       => $this->uom->id,
            'cost_price'   => 50.00,
            'selling_price'=> 80.00,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company1->id,
            'branch_id'  => $this->branch1->id,
            'code'       => 'WC-01',
            'name'       => 'CNC Lathe 1',
        ]);

        $this->routing = Routing::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company1->id,
            'branch_id'  => $this->branch1->id,
            'product_id' => $this->product->id,
            'name'       => 'Gear Machining Route',
            'code'       => 'R-GEAR-01',
        ]);

        $this->routingOp = RoutingOperation::create([
            'tenant_id'        => $this->tenant->id,
            'routing_id'       => $this->routing->id,
            'work_center_id'   => $this->workCenter->id,
            'operation_number' => 'OP-010',
            'name'             => 'Turning',
            'sequence'         => 10,
        ]);

        $this->order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'company_id'       => $this->company1->id,
            'branch_id'        => $this->branch1->id,
            'order_number'     => 'PO-TEST-001',
            'product_id'       => $this->product->id,
            'quantity_ordered' => 100,
            'quantity_produced'=> 0,
            'start_date'       => now(),
            'end_date'         => now()->addDays(2),
            'status'           => 'in_progress',
        ]);

        $this->qualityPlan = ProductionQualityPlan::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Standard Quality Plan',
            'version'   => '1.0',
            'status'    => 'approved',
            'type'      => 'in_process',
            'product_id'=> $this->product->id,
            'created_by'=> $this->admin->id,
        ]);

        $this->orderOp = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenant->id,
            'company_id'          => $this->company1->id,
            'branch_id'           => $this->branch1->id,
            'production_order_id' => $this->order->id,
            'routing_operation_id'=> $this->routingOp->id,
            'work_center_id'      => $this->workCenter->id,
            'sequence'            => 10,
            'operation_number'    => 'OP-010',
            'name'                => 'Turning',
            'target_produced_qty' => 100,
            'status'              => 'ready',
        ]);
    }

    /**
     * Issue 1: Company/Branch scoping inheritance on models without session context (Background/CLI/Queue).
     */
    public function test_models_inherit_company_and_branch_from_parent_order_without_session(): void
    {
        // Ensure no company session is set
        app(CompanyContext::class)->clear();
        app(BranchContext::class)->clear();

        // 1. ProductionOrderScrap creation without explicit company_id / branch_id
        $scrap = ProductionOrderScrap::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'quantity'            => 5,
            'reason'              => 'Tool breakage scrap',
        ]);

        $this->assertEquals($this->company1->id, $scrap->company_id, 'ProductionOrderScrap must inherit company_id from parent order');
        $this->assertEquals($this->branch1->id, $scrap->branch_id, 'ProductionOrderScrap must inherit branch_id from parent order');

        // 2. ProductionOrderRework creation without explicit company_id / branch_id
        $rework = ProductionOrderRework::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'rework_order_number' => 'RW-001',
            'quantity'            => 3,
            'reason'              => 'Surface roughness out of tolerance',
            'status'              => 'pending',
        ]);

        $this->assertEquals($this->company1->id, $rework->company_id, 'ProductionOrderRework must inherit company_id from parent order');
        $this->assertEquals($this->branch1->id, $rework->branch_id, 'ProductionOrderRework must inherit branch_id from parent order');

        // 3. ProductionQualityInspection creation without explicit company_id / branch_id
        $inspection = ProductionQualityInspection::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'quality_plan_id'     => $this->qualityPlan->id,
            'inspection_number'   => 'QC-001',
            'inspection_type'     => 'in_process',
            'stage'               => 'machining',
            'status'              => 'pending',
            'inspected_quantity'  => 10,
        ]);

        $this->assertEquals($this->company1->id, $inspection->company_id, 'ProductionQualityInspection must inherit company_id from parent order');
        $this->assertEquals($this->branch1->id, $inspection->branch_id, 'ProductionQualityInspection must inherit branch_id from parent order');

        // 4. ProductionWip creation without explicit company_id / branch_id
        $wip = ProductionWip::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'quantity'            => 10,
            'status'              => 'in_progress',
        ]);

        $this->assertEquals($this->company1->id, $wip->company_id, 'ProductionWip must inherit company_id from parent order');
        $this->assertEquals($this->branch1->id, $wip->branch_id, 'ProductionWip must inherit branch_id from parent order');
    }

    /**
     * Issue 1: Services pass explicit company_id and branch_id correctly during execution.
     */
    public function test_services_explicitly_set_company_and_branch_id(): void
    {
        // Set company context to null to ensure service relies on order ownership, not session
        app(CompanyContext::class)->clear();
        app(BranchContext::class)->clear();

        // QualityInspectionService::quickOperatorInspection
        $qcService = app(QualityInspectionService::class);
        $inspection = $qcService->quickOperatorInspection($this->tenant->id, [
            'production_order_id'           => $this->order->id,
            'production_order_operation_id' => $this->orderOp->id,
            'quality_plan_id'               => $this->qualityPlan->id,
            'stage'                         => 'in_process',
            'result'                        => 'passed',
        ]);

        $this->assertEquals($this->company1->id, $inspection->company_id);
        $this->assertEquals($this->branch1->id, $inspection->branch_id);

        // ProductionWipService::initializeWipForOrder
        $wipService = app(ProductionWipService::class);
        $wip = $wipService->initializeWipForOrder($this->order->id);

        $this->assertEquals($this->company1->id, $wip->company_id);
        $this->assertEquals($this->branch1->id, $wip->branch_id);

        // ProductionExecutionService::logScrap
        $execService = app(ProductionExecutionService::class);
        $scrap = $execService->logScrap(
            orderId: $this->order->id,
            operationId: $this->orderOp->id,
            productId: $this->product->id,
            quantity: 2.0,
            reason: 'Tool wear defect'
        );

        $this->assertEquals($this->company1->id, $scrap->company_id);
        $this->assertEquals($this->branch1->id, $scrap->branch_id);
    }

    /**
     * Issue 2: ProductionBatchRepository findBatchByNumber schema mismatch fix.
     */
    public function test_production_batch_repository_find_batch_by_number_uses_batch_number(): void
    {
        $batch = ProductionBatch::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'product_id'          => $this->product->id,
            'batch_number'        => 'BATCH-XYZ-999',
            'planned_quantity'    => 50,
            'status'              => 'in_progress',
        ]);

        $repository = app(ProductionBatchRepository::class);
        $foundBatch = $repository->findBatchByNumber('BATCH-XYZ-999');

        $this->assertNotNull($foundBatch, 'findBatchByNumber must find batch using batch_number');
        $this->assertEquals($batch->id, $foundBatch->id);
        $this->assertEquals('BATCH-XYZ-999', $foundBatch->batch_number);

        // Non-existent batch returns null
        $this->assertNull($repository->findBatchByNumber('NON-EXISTENT'));
    }

    /**
     * Issue 3: ProductionLotTrace morph map resolves Production polymorphic types cleanly.
     */
    public function test_production_lot_trace_morph_map_resolves_production_types(): void
    {
        // Verify Relation::morphMap mappings
        $morphMap = Relation::morphMap();
        $this->assertEquals(ProductionOrder::class, $morphMap['order'] ?? null);
        $this->assertEquals(ProductionBatch::class, $morphMap['batch'] ?? null);
        $this->assertEquals(ProductionSerialNumber::class, $morphMap['serial'] ?? null);
        $this->assertEquals(InventoryBatch::class, $morphMap['lot'] ?? null);

        // Create test entities
        $batch = ProductionBatch::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'product_id'          => $this->product->id,
            'batch_number'        => 'BATCH-TRACE-1',
            'planned_quantity'    => 20,
            'status'              => 'in_progress',
        ]);

        $serial = ProductionSerialNumber::create([
            'tenant_id'           => $this->tenant->id,
            'product_id'          => $this->product->id,
            'production_order_id' => $this->order->id,
            'serial_number'       => 'SN-TRACE-1',
            'status'              => 'in_progress',
        ]);

        $lot = InventoryBatch::create([
            'tenant_id'    => $this->tenant->id,
            'company_id'   => $this->company1->id,
            'branch_id'    => $this->branch1->id,
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'LOT-RAW-001',
            'quantity'     => 100,
        ]);

        // Create traces using morph aliases
        $trace1 = ProductionLotTrace::create([
            'tenant_id'    => $this->tenant->id,
            'company_id'   => $this->company1->id,
            'branch_id'    => $this->branch1->id,
            'source_type'  => 'order',
            'source_id'    => $this->order->id,
            'target_type'  => 'batch',
            'target_id'    => $batch->id,
            'trace_type'   => 'forward',
            'relationship' => 'order_to_batch',
            'quantity'     => 10,
        ]);

        $trace2 = ProductionLotTrace::create([
            'tenant_id'    => $this->tenant->id,
            'company_id'   => $this->company1->id,
            'branch_id'    => $this->branch1->id,
            'source_type'  => 'lot',
            'source_id'    => $lot->id,
            'target_type'  => 'serial',
            'target_id'    => $serial->id,
            'trace_type'   => 'forward',
            'relationship' => 'lot_to_serial',
            'quantity'     => 1,
        ]);

        // Test polymorphic resolution on source and target
        $this->assertInstanceOf(ProductionOrder::class, $trace1->source);
        $this->assertEquals($this->order->id, $trace1->source->id);

        $this->assertInstanceOf(ProductionBatch::class, $trace1->target);
        $this->assertEquals($batch->id, $trace1->target->id);

        $this->assertInstanceOf(InventoryBatch::class, $trace2->source);
        $this->assertEquals($lot->id, $trace2->source->id);

        $this->assertInstanceOf(ProductionSerialNumber::class, $trace2->target);
        $this->assertEquals($serial->id, $trace2->target->id);
    }

    /**
     * Multi-company / multi-branch isolation test:
     * Records created under Company 1 must not leak or be visible when Company 2 is active.
     */
    public function test_cross_company_isolation_on_created_production_records(): void
    {
        // Create records under company 1
        $scrap = ProductionOrderScrap::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'quantity'            => 2,
            'reason'              => 'Defect scrap',
        ]);

        $wip = ProductionWip::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'quantity'            => 10,
            'status'              => 'in_progress',
        ]);

        // When company 1 is active, records are visible
        app(CompanyContext::class)->set($this->company1);
        $this->assertNotNull(ProductionOrderScrap::find($scrap->id));
        $this->assertNotNull(ProductionWip::find($wip->id));

        // When company 2 is active, records must NOT be visible under standard tenancy scope
        app(CompanyContext::class)->set($this->company2);
        $this->assertNull(ProductionOrderScrap::find($scrap->id));
        $this->assertNull(ProductionWip::find($wip->id));
    }
}
