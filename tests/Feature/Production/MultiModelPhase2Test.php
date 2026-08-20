<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\RoutingService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiModelPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected int $tenantId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Tenant::factory()->create([
            'id' => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'production_manager',
        ]);
        $this->actingAs($this->user);

        // Create UOM
        \App\Domains\Inventory\Models\Uom::create([
            'id' => 1,
            'tenant_id' => $this->tenantId,
            'name' => 'Piece',
            'code' => 'PCS',
            'status' => 'active',
        ]);

        // Ensure session tenant_id is set
        session(['tenant_id' => $this->tenantId]);
    }

    /** Helper to create a basic test vendor */
    protected function createVendor(array $attributes = []): Vendor
    {
        return Vendor::create(array_merge([
            'tenant_id' => $this->tenantId,
            'name' => 'Vendor Test Subcontractor',
            'code' => 'VND-' . rand(1000, 9999),
            'status' => 'active',
        ], $attributes));
    }

    /** Helper to create a service product */
    protected function createServiceProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenantId,
            'name' => 'Subcontract Machining Service',
            'sku' => 'SVC-' . rand(1000, 9999),
            'type' => 'service',
            'status' => 'active',
        ], $attributes));
    }

    /** Helper to create a finished product */
    protected function createFinishedProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenantId,
            'name' => 'Finished Widget',
            'sku' => 'FG-' . rand(1000, 9999),
            'type' => 'finished_good',
            'status' => 'active',
            'default_production_model' => 'pure_manufacturing',
        ], $attributes));
    }

    /** Helper to create a raw material product */
    protected function createRawProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenantId,
            'name' => 'Steel Plate',
            'sku' => 'RAW-' . rand(1000, 9999),
            'type' => 'raw_material',
            'status' => 'active',
        ], $attributes));
    }

    /** Helper to create a work center and optional machine */
    protected function createWorkCenter(array $attributes = []): WorkCenter
    {
        return WorkCenter::create(array_merge([
            'tenant_id' => $this->tenantId,
            'code' => 'WC-' . rand(1000, 9999),
            'name' => 'CNC Milling Station',
            'status' => 'active',
            'efficiency_percentage' => 100.0,
        ], $attributes));
    }

    protected function createMachine(WorkCenter $wc, array $attributes = []): Machine
    {
        return Machine::create(array_merge([
            'tenant_id' => $this->tenantId,
            'work_center_id' => $wc->id,
            'code' => 'MCH-' . rand(1000, 9999),
            'name' => 'Haas CNC VMC',
            'status' => Machine::STATUS_ACTIVE,
        ], $attributes));
    }

    /** Helper to create a basic approved BOM */
    protected function createApprovedBom(Product $fg, Product $raw): ProductionBom
    {
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'bom_number' => 'BOM-' . rand(1000, 9999),
            'name' => 'Widget Standard BOM',
            'product_id' => $fg->id,
            'bom_type' => 'manufacturing',
            'version' => '1.0',
            'status' => 'approved',
            'base_quantity' => 1.0,
            'effective_date' => now()->toDateString(),
            'is_default' => true,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantId,
            'bom_id' => $bom->id,
            'material_id' => $raw->id,
            'quantity' => 2.0,
            'uom_id' => 1,
        ]);

        return $bom;
    }

    /** ------------------------------------------------------------------------
     * TEST 1: Routing Validation - Vendor required for is_external = true
     * ------------------------------------------------------------------------ */
    public function test_vendor_is_required_when_operation_is_external(): void
    {
        $fg = $this->createFinishedProduct();

        $payload = [
            'product_id' => $fg->id,
            'name' => 'Outsourced Routing Test',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'is_default' => true,
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Outsourced Coating',
                    'operation_type' => 'outsourcing',
                    'is_external' => true,
                    'vendor_id' => null, // Missing vendor!
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 0,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->withHeader('X-Tenant', 'test-tenant')->post(route('production.routing.store'), $payload);
        $response->assertSessionHasErrors(['operations.0.vendor_id']);
    }

    /** ------------------------------------------------------------------------
     * TEST 2: Routing Validation - Work Center required when is_external = false
     * ------------------------------------------------------------------------ */
    public function test_work_center_is_required_for_internal_operation(): void
    {
        $fg = $this->createFinishedProduct();

        $payload = [
            'product_id' => $fg->id,
            'name' => 'Internal Routing Test',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'is_default' => true,
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Internal Machining',
                    'operation_type' => 'manufacturing',
                    'is_external' => false,
                    'work_center_id' => null, // Missing Work Center for internal op!
                    'setup_time_minutes' => 15,
                    'processing_time_minutes' => 30,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->withHeader('X-Tenant', 'test-tenant')->post(route('production.routing.store'), $payload);
        $response->assertSessionHasErrors(['operations.0.work_center_id']);
    }

    /** ------------------------------------------------------------------------
     * TEST 3: Routing Validation - Negative lead time/buffer rejection
     * ------------------------------------------------------------------------ */
    public function test_negative_subcontract_parameters_are_rejected(): void
    {
        $fg = $this->createFinishedProduct();
        $vendor = $this->createVendor();

        $payload = [
            'product_id' => $fg->id,
            'name' => 'Invalid Subcontract Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'is_default' => true,
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Outsourced Heat Treat',
                    'operation_type' => 'outsourcing',
                    'is_external' => true,
                    'vendor_id' => $vendor->id,
                    'subcontract_lead_time_days' => -5, // Negative lead time!
                    'dispatch_buffer_days' => -1, // Negative buffer!
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 0,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->withHeader('X-Tenant', 'test-tenant')->post(route('production.routing.store'), $payload);
        $response->assertSessionHasErrors([
            'operations.0.subcontract_lead_time_days',
            'operations.0.dispatch_buffer_days',
        ]);
    }

    /** ------------------------------------------------------------------------
     * TEST 4: Successful Creation & Editing of External Routing Operation
     * ------------------------------------------------------------------------ */
    public function test_can_create_and_update_routing_with_subcontract_fields(): void
    {
        $fg = $this->createFinishedProduct();
        $vendor = $this->createVendor();
        $wc = $this->createWorkCenter();
        $serviceProd = $this->createServiceProduct();

        $payload = [
            'routing_number' => 'RTG-SUB-001',
            'name' => 'Subcontract Surface Treatment',
            'product_id' => $fg->id,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'is_default' => true,
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Anodizing',
                    'operation_type' => 'outsourcing',
                    'is_external' => true,
                    'vendor_id' => $vendor->id,
                    'work_center_id' => $wc->id,
                    'subcontract_lead_time_days' => 5,
                    'subcontract_cost_per_unit' => 12.50,
                    'subcontract_service_product_id' => $serviceProd->id,
                    'material_supply_type' => 'company_supplied',
                    'dispatch_buffer_days' => 1,
                    'return_buffer_days' => 1,
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 0,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->withHeader('X-Tenant', 'test-tenant')->post(route('production.routing.store'), $payload);
        $response->assertRedirect();

        $routing = Routing::where('name', 'Subcontract Surface Treatment')->firstOrFail();
        $op = $routing->operations->first();

        $this->assertTrue((bool) $op->is_external);
        $this->assertEquals($vendor->id, $op->vendor_id);
        $this->assertEquals(5, $op->subcontract_lead_time_days);
        $this->assertEquals(12.50, (float) $op->subcontract_cost_per_unit);
        $this->assertEquals($serviceProd->id, $op->subcontract_service_product_id);
        $this->assertEquals('company_supplied', $op->material_supply_type);
        $this->assertEquals(1, $op->dispatch_buffer_days);
        $this->assertEquals(1, $op->return_buffer_days);
    }

    /** ------------------------------------------------------------------------
     * TEST 5: Snapshot Immutability from Routing to Production Order Operations
     * ------------------------------------------------------------------------ */
    public function test_subcontract_fields_are_snapshotted_immutably_to_order_operations(): void
    {
        $fg = $this->createFinishedProduct();
        $raw = $this->createRawProduct();
        $vendor = $this->createVendor();
        $wc = $this->createWorkCenter();
        $serviceProd = $this->createServiceProduct();
        $bom = $this->createApprovedBom($fg, $raw);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RTG-SNAP-01',
            'name' => 'Snapshot Test Routing',
            'product_id' => $fg->id,
            'version' => '1.0.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        $routingOp = RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Outsourced Electroplating',
            'operation_type' => 'outsourcing',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'work_center_id' => $wc->id,
            'subcontract_lead_time_days' => 4,
            'subcontract_cost_per_unit' => 25.00,
            'subcontract_service_product_id' => $serviceProd->id,
            'material_supply_type' => 'company_supplied',
            'dispatch_buffer_days' => 1,
            'return_buffer_days' => 2,
        ]);

        /** @var ProductionOrderService $orderService */
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(),
            'production_model' => 'complete_subcontracting',
        ], $this->user->id);

        $orderOp = $order->operations->first();
        $this->assertNotNull($orderOp);
        $this->assertTrue((bool) $orderOp->is_external);
        $this->assertEquals($vendor->id, $orderOp->vendor_id);
        $this->assertEquals(4, $orderOp->subcontract_lead_time_days);
        $this->assertEquals(25.00, (float) $orderOp->subcontract_cost_per_unit);
        $this->assertEquals(1, $orderOp->dispatch_buffer_days);
        $this->assertEquals(2, $orderOp->return_buffer_days);

        // Mutate original RoutingOperation
        $routingOp->update([
            'subcontract_lead_time_days' => 10,
            'subcontract_cost_per_unit' => 99.00,
        ]);

        // Order operation snapshot MUST remain untouched
        $orderOp->refresh();
        $this->assertEquals(4, $orderOp->subcontract_lead_time_days);
        $this->assertEquals(25.00, (float) $orderOp->subcontract_cost_per_unit);
    }

    /** ------------------------------------------------------------------------
     * TEST 6: Forward Scheduling - External Lead Time Timeline Calculation
     * ------------------------------------------------------------------------ */
    public function test_forward_scheduling_calculates_external_lead_time_duration(): void
    {
        $fg = $this->createFinishedProduct();
        $raw = $this->createRawProduct();
        $vendor = $this->createVendor();
        $wc = $this->createWorkCenter();
        $bom = $this->createApprovedBom($fg, $raw);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RTG-FWD-01',
            'name' => 'Forward External Test',
            'product_id' => $fg->id,
            'version' => '1.0.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'External Painting',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'work_center_id' => $wc->id,
            'dispatch_buffer_days' => 1,
            'subcontract_lead_time_days' => 3,
            'return_buffer_days' => 1, // Total = 5 days = 7200 minutes
        ]);

        /** @var ProductionOrderService $orderService */
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 100,
            'start_date' => '2026-09-01 08:00:00',
            'end_date' => '2026-09-20 17:00:00',
            'production_model' => 'subcontracting_company_material',
        ], $this->user->id);

        /** @var SchedulingService $schedulingService */
        $schedulingService = app(SchedulingService::class);
        $startDate = Carbon::parse('2026-09-01 08:00:00');
        $schedule = $schedulingService->generateForwardSchedule($order, $startDate);

        $schedOp = $schedule->operations->first();
        $this->assertNotNull($schedOp);

        // Expected duration = (1 + 3 + 1) * 1440 = 7200 minutes
        $this->assertEquals(7200, (int) $schedOp->planned_duration_minutes);
        $this->assertNull($schedOp->machine_id);

        // Planned finish should be start + 7200 minutes (5 days)
        $expectedFinish = $startDate->copy()->addMinutes(7200);
        $this->assertEquals($expectedFinish->toDateTimeString(), $schedOp->planned_finish->toDateTimeString());
    }

    /** ------------------------------------------------------------------------
     * TEST 7: Backward Scheduling - External Lead Time Calculation
     * ------------------------------------------------------------------------ */
    public function test_backward_scheduling_calculates_external_start_from_due_date(): void
    {
        $fg = $this->createFinishedProduct();
        $raw = $this->createRawProduct();
        $vendor = $this->createVendor();
        $wc = $this->createWorkCenter();
        $bom = $this->createApprovedBom($fg, $raw);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RTG-BWD-01',
            'name' => 'Backward External Test',
            'product_id' => $fg->id,
            'version' => '1.0.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Subcontract Galvanizing',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'work_center_id' => $wc->id,
            'dispatch_buffer_days' => 1,
            'subcontract_lead_time_days' => 4,
            'return_buffer_days' => 1, // Total = 6 days = 8640 minutes
        ]);

        /** @var ProductionOrderService $orderService */
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 50,
            'start_date' => '2026-09-01 08:00:00',
            'end_date' => '2026-09-15 17:00:00',
            'production_model' => 'subcontracting_company_material',
        ], $this->user->id);

        /** @var SchedulingService $schedulingService */
        $schedulingService = app(SchedulingService::class);
        $dueDate = Carbon::parse('2026-09-15 17:00:00');
        $schedule = $schedulingService->generateBackwardSchedule($order, $dueDate);

        $schedOp = $schedule->operations->first();
        $this->assertNotNull($schedOp);

        $this->assertEquals(8640, (int) $schedOp->planned_duration_minutes);
        $this->assertEquals($dueDate->toDateTimeString(), $schedOp->planned_finish->toDateTimeString());

        $expectedStart = $dueDate->copy()->subMinutes(8640);
        $this->assertEquals($expectedStart->toDateTimeString(), $schedOp->planned_start->toDateTimeString());
    }

    /** ------------------------------------------------------------------------
     * TEST 8: Hybrid Manufacturing + Subcontracting Sequential Scheduling
     * ------------------------------------------------------------------------ */
    public function test_hybrid_routing_schedules_internal_and_external_operations_in_sequence(): void
    {
        $fg = $this->createFinishedProduct();
        $raw = $this->createRawProduct();
        $vendor = $this->createVendor();
        $wc = $this->createWorkCenter();
        $mch = $this->createMachine($wc);
        $bom = $this->createApprovedBom($fg, $raw);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RTG-HYBRID-01',
            'name' => 'Hybrid Process Routing',
            'product_id' => $fg->id,
            'version' => '1.0.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        // Seq 10: Internal Cutting
        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Internal Cutting',
            'is_external' => false,
            'work_center_id' => $wc->id,
            'machine_id' => $mch->id,
            'setup_time_minutes' => 60,
            'processing_time_minutes' => 2, // for 10 units = 20 mins -> total 80 mins
        ]);

        // Seq 20: Subcontract Heat Treatment
        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Outsourced Heat Treat',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'work_center_id' => $wc->id,
            'dispatch_buffer_days' => 0,
            'subcontract_lead_time_days' => 2, // 2880 mins
            'return_buffer_days' => 0,
        ]);

        // Seq 30: Internal Final Polish
        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 30,
            'operation_number' => 'OP30',
            'name' => 'Internal Polish',
            'is_external' => false,
            'work_center_id' => $wc->id,
            'machine_id' => $mch->id,
            'setup_time_minutes' => 30,
            'processing_time_minutes' => 1, // for 10 units = 10 mins -> total 40 mins
        ]);

        /** @var ProductionOrderService $orderService */
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10,
            'start_date' => '2026-09-01 08:00:00',
            'end_date' => '2026-09-10 17:00:00',
            'production_model' => 'manufacturing_and_subcontracting',
        ], $this->user->id);

        /** @var SchedulingService $schedulingService */
        $schedulingService = app(SchedulingService::class);
        $startDate = Carbon::parse('2026-09-01 08:00:00');
        $schedule = $schedulingService->generateForwardSchedule($order, $startDate);

        $ops = $schedule->operations->sortBy('sequence')->values();
        $this->assertCount(3, $ops);

        // Op 1 (Internal)
        $this->assertEquals(10, $ops[0]->sequence);
        $this->assertEquals($mch->id, $ops[0]->machine_id);

        // Op 2 (External) must start at or after Op 1 finish
        $this->assertEquals(20, $ops[1]->sequence);
        $this->assertNull($ops[1]->machine_id);
        $this->assertTrue($ops[1]->planned_start->gte($ops[0]->planned_finish));
        $this->assertEquals(2880, (int) $ops[1]->planned_duration_minutes);

        // Op 3 (Internal) must start at or after Op 2 (External) return/finish
        $this->assertEquals(30, $ops[2]->sequence);
        $this->assertTrue($ops[2]->planned_start->gte($ops[1]->planned_finish));
    }

    /** ------------------------------------------------------------------------
     * TEST 9: Zero Internal Work Center Capacity Loading for External Operations
     * ------------------------------------------------------------------------ */
    public function test_external_operations_do_not_consume_work_center_capacity(): void
    {
        $fg = $this->createFinishedProduct();
        $raw = $this->createRawProduct();
        $vendor = $this->createVendor();
        $wc = $this->createWorkCenter();
        $bom = $this->createApprovedBom($fg, $raw);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RTG-CAP-01',
            'name' => 'Capacity Test Routing',
            'product_id' => $fg->id,
            'version' => '1.0.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Subcontract Plating',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'work_center_id' => $wc->id, // WC linked but op is external
            'subcontract_lead_time_days' => 10,
        ]);

        /** @var ProductionOrderService $orderService */
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 100,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'production_model' => 'complete_subcontracting',
        ], $this->user->id);

        /** @var SchedulingService $schedulingService */
        $schedulingService = app(SchedulingService::class);
        $schedule = $schedulingService->generateForwardSchedule($order, now());

        $overloads = $schedulingService->detectOverloads($this->tenantId);
        $this->assertEmpty($overloads, 'External operation must not trigger work center capacity overloads.');
    }

    /** ------------------------------------------------------------------------
     * TEST 10: External Operations Bypass Machine Downtime & Conflict Checks
     * ------------------------------------------------------------------------ */
    public function test_external_operations_bypass_machine_downtime_and_conflicts(): void
    {
        $fg = $this->createFinishedProduct();
        $raw = $this->createRawProduct();
        $vendor = $this->createVendor();
        $wc = $this->createWorkCenter();
        $mch = $this->createMachine($wc);
        $bom = $this->createApprovedBom($fg, $raw);

        // Add machine downtime for machine
        ProductionMachineDowntime::create([
            'tenant_id' => $this->tenantId,
            'work_center_id' => $wc->id,
            'machine_id' => $mch->id,
            'category' => 'maintenance',
            'reason' => 'Scheduled Overhaul',
            'start_time' => now()->startOfDay(),
            'end_time' => now()->addDays(10)->endOfDay(),
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RTG-DOWNTIME-01',
            'name' => 'Downtime Bypass Routing',
            'product_id' => $fg->id,
            'version' => '1.0.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Subcontract Machining',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'work_center_id' => $wc->id,
            'subcontract_lead_time_days' => 3,
        ]);

        /** @var ProductionOrderService $orderService */
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'production_model' => 'complete_subcontracting',
        ], $this->user->id);

        /** @var SchedulingService $schedulingService */
        $schedulingService = app(SchedulingService::class);
        $schedule = $schedulingService->generateForwardSchedule($order, now());

        $conflicts = $schedulingService->detectConflicts($this->tenantId);
        $this->assertEmpty($conflicts, 'External operations must not produce machine conflict warnings.');
    }

    /** ------------------------------------------------------------------------
     * TEST 11: Overlap Scheduling with External Predecessor
     * ------------------------------------------------------------------------ */
    public function test_internal_successor_waits_for_external_predecessor_completion_even_if_overlap_set(): void
    {
        $fg = $this->createFinishedProduct();
        $raw = $this->createRawProduct();
        $vendor = $this->createVendor();
        $wc = $this->createWorkCenter();
        $mch = $this->createMachine($wc);
        $bom = $this->createApprovedBom($fg, $raw);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RTG-OVERLAP-EXT',
            'name' => 'Overlap External Predecessor Routing',
            'product_id' => $fg->id,
            'version' => '1.0.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        // Seq 10: External operation with overlap_enabled = true
        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'External Painting',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'work_center_id' => $wc->id,
            'subcontract_lead_time_days' => 2, // 2880 mins
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 5,
        ]);

        // Seq 20: Internal inspection
        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Internal Quality Inspection',
            'is_external' => false,
            'work_center_id' => $wc->id,
            'machine_id' => $mch->id,
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 2,
        ]);

        /** @var ProductionOrderService $orderService */
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 20,
            'start_date' => '2026-09-01 08:00:00',
            'end_date' => '2026-09-10 17:00:00',
            'production_model' => 'manufacturing_and_subcontracting',
        ], $this->user->id);

        /** @var SchedulingService $schedulingService */
        $schedulingService = app(SchedulingService::class);
        $startDate = Carbon::parse('2026-09-01 08:00:00');
        $schedule = $schedulingService->generateForwardSchedule($order, $startDate);

        $ops = $schedule->operations->sortBy('sequence')->values();

        // Op 20 (Internal) start MUST be >= Op 10 (External) finish because goods must return from vendor first!
        $this->assertTrue(
            $ops[1]->planned_start->gte($ops[0]->planned_finish),
            'Internal successor cannot start until external predecessor fully completes and returns from vendor.'
        );
    }

    /** ------------------------------------------------------------------------
     * TEST 12: Complete Subcontracting Production Model Full Execution Test
     * ------------------------------------------------------------------------ */
    public function test_complete_subcontracting_model_order_schedules_without_machine_dependencies(): void
    {
        $fg = $this->createFinishedProduct(['default_production_model' => 'complete_subcontracting']);
        $raw = $this->createRawProduct();
        $vendor = $this->createVendor();
        $wc = $this->createWorkCenter();
        $bom = $this->createApprovedBom($fg, $raw);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RTG-PURE-SUB-01',
            'name' => '100% Subcontracting Routing',
            'product_id' => $fg->id,
            'version' => '1.0.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Full Subcontract Manufacturing',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'work_center_id' => $wc->id,
            'dispatch_buffer_days' => 2,
            'subcontract_lead_time_days' => 10,
            'return_buffer_days' => 2,
        ]);

        /** @var ProductionOrderService $orderService */
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 200,
            'start_date' => '2026-10-01 08:00:00',
            'end_date' => '2026-10-25 17:00:00',
            'production_model' => 'complete_subcontracting',
        ], $this->user->id);

        $this->assertEquals('complete_subcontracting', $order->production_model);

        /** @var SchedulingService $schedulingService */
        $schedulingService = app(SchedulingService::class);
        $schedule = $schedulingService->generateForwardSchedule($order, Carbon::parse('2026-10-01 08:00:00'));

        $this->assertCount(1, $schedule->operations);
        $schedOp = $schedule->operations->first();
        $this->assertNull($schedOp->machine_id);

        // 2 + 10 + 2 = 14 days = 20160 minutes
        $this->assertEquals(20160, (int) $schedOp->planned_duration_minutes);
    }
}
