<?php

namespace Tests\Feature\Production;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\RoutingCostService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkCenterRoutingIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\FillsRoutingFigures; // RT-IMPL-14: fill figures like the form does

    private Tenant $tenant;
    private User $user;
    private WorkCenter $workCenterA;
    private WorkCenter $workCenterB;
    private WorkCenterSection $sectionA;
    private WorkCenterSection $sectionB;
    private Machine $machineA;
    private SectionManualProduction $manualBatchA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Manufacturing Tenant',
            'slug' => 'mfg-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Production Admin',
            'email' => 'prod.admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_manager',
        ]);

        // Work Center A with Section A
        $this->workCenterA = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Work Center A',
            'code' => 'WC-ASM-01',
            'cost_per_hour' => 120.00,
            'status' => 'active',
        ]);

        $this->sectionA = WorkCenterSection::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Cutting Section A',
            'code' => 'SEC-CUT-A',
            'status' => 'active',
            'has_manual_production' => true,
        ]);

        // Work Center B with Section B
        $this->workCenterB = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Packaging Work Center B',
            'code' => 'WC-PKG-02',
            'cost_per_hour' => 90.00,
            'status' => 'active',
        ]);

        $this->sectionB = WorkCenterSection::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenterB->id,
            'name' => 'Wrapping Section B',
            'code' => 'SEC-WRP-B',
            'status' => 'active',
            'has_manual_production' => false,
        ]);

        // Machine A attached to Section A
        $this->machineA = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenterA->id,
            'section_id' => $this->sectionA->id,
            'name' => 'CNC Milling Machine 1',
            'code' => 'MCH-CNC-01',
            'capacity' => 60, // 60 units/hr -> 1 min/unit
            'operating_cost_per_hour' => 30.00,
            'status' => 'active',
        ]);

        $shift = ProductionShift::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Morning Shift',
            'code' => 'SHIFT-MORN',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ]);

        // Manual Batch A attached to Section A
        $this->manualBatchA = SectionManualProduction::create([
            'tenant_id' => $this->tenant->id,
            'section_id' => $this->sectionA->id,
            'shift_id' => $shift->id,
            'batch_name' => 'Manual Finishing Batch 1',
            'capacity_per_hour' => 30, // 30 units/hr -> 2 min/unit
            'active' => true,
        ]);
    }

    private function authRequest()
    {
        return $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id]);
    }

    public function test_internal_operation_with_machine_validates_and_stores_successfully(): void
    {
        $payload = [
            'name' => 'Precision Cabinet Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'row_uid' => 'uid-op-1',
                    'name' => 'CNC Profiling',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'section_id' => $this->sectionA->id,
                    'machine_id' => $this->machineA->id,
                    'section_manual_production_id' => '',
                    'is_alternative' => 0,
                    'setup_time_minutes' => 5.0,
                    'processing_time_minutes' => 1.0,
                    'wait_time_minutes' => 0.0,
                    'labor_cost_rate' => 0.50,
                    'machine_cost_rate' => 0.50,
                    'expected_yield_percentage' => 100.0,
                ],
            ],
        ];

        $response = $this->authRequest()
            ->post(route('production.routing.store'), $this->withRoutingFigures($payload));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $routing = Routing::where('name', 'Precision Cabinet Routing')->first();
        $this->assertNotNull($routing);
        $this->assertCount(1, $routing->operations);

        $op = $routing->operations->first();
        $this->assertEquals($this->workCenterA->id, $op->work_center_id);
        $this->assertEquals($this->sectionA->id, $op->section_id);
        $this->assertEquals($this->machineA->id, $op->machine_id);
        $this->assertNull($op->section_manual_production_id);
        $this->assertFalse((bool) $op->is_alternative);
        $this->assertGreaterThan(0, $op->estimatedCostPerUnit());
    }

    public function test_internal_operation_with_manual_batch_validates_and_stores_successfully(): void
    {
        $payload = [
            'name' => 'Handcrafted Furniture Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'row_uid' => 'uid-batch-1',
                    'name' => 'Manual Sanding and Polishing',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'section_id' => $this->sectionA->id,
                    'machine_id' => '',
                    'section_manual_production_id' => $this->manualBatchA->id,
                    'is_alternative' => 0,
                    'setup_time_minutes' => 2.0,
                    'processing_time_minutes' => 2.0,
                    'wait_time_minutes' => 0.0,
                    'labor_cost_rate' => 0.80,
                    'machine_cost_rate' => 0.00,
                    'expected_yield_percentage' => 100.0,
                ],
            ],
        ];

        $response = $this->authRequest()
            ->post(route('production.routing.store'), $this->withRoutingFigures($payload));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $routing = Routing::where('name', 'Handcrafted Furniture Routing')->first();
        $this->assertNotNull($routing);

        $op = $routing->operations->first();
        $this->assertEquals($this->sectionA->id, $op->section_id);
        $this->assertEquals($this->manualBatchA->id, $op->section_manual_production_id);
        $this->assertNull($op->machine_id);
    }

    public function test_mutual_exclusivity_fails_if_both_machine_and_batch_submitted(): void
    {
        $payload = [
            'name' => 'Conflict Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Illegal Both Resources Selected',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'section_id' => $this->sectionA->id,
                    'machine_id' => $this->machineA->id,
                    'section_manual_production_id' => $this->manualBatchA->id,
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 1,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->authRequest()
            ->post(route('production.routing.store'), $this->withRoutingFigures($payload));

        $response->assertSessionHasErrors(['operations.0.machine_id']);
        $this->assertDatabaseMissing('routings', ['name' => 'Conflict Routing']);
    }

    public function test_mutual_exclusivity_fails_if_neither_machine_nor_batch_submitted_for_internal_op(): void
    {
        $payload = [
            'name' => 'Missing Resource Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'No Resource Selected',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'section_id' => $this->sectionA->id,
                    'machine_id' => '',
                    'section_manual_production_id' => '',
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 1,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->authRequest()
            ->post(route('production.routing.store'), $this->withRoutingFigures($payload));

        $response->assertSessionHasErrors(['operations.0.machine_id']);
        $this->assertDatabaseMissing('routings', ['name' => 'Missing Resource Routing']);
    }

    public function test_section_must_belong_to_selected_work_center(): void
    {
        $payload = [
            'name' => 'Section WC Mismatch Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Mismatch Section',
                    'operation_type' => 'manufacturing',
                    // Selected Work Center A, but Section B belongs to Work Center B
                    'work_center_id' => $this->workCenterA->id,
                    'section_id' => $this->sectionB->id,
                    'machine_id' => $this->machineA->id,
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 1,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->authRequest()
            ->post(route('production.routing.store'), $this->withRoutingFigures($payload));

        $response->assertSessionHasErrors(['operations.0.section_id']);
    }

    public function test_machine_must_belong_to_selected_section(): void
    {
        // Machine A belongs to Section A, but operation submits Section B
        $payload = [
            'name' => 'Machine Section Mismatch Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Mismatch Machine',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterB->id,
                    'section_id' => $this->sectionB->id,
                    'machine_id' => $this->machineA->id, // Machine from Section A
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 1,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->authRequest()
            ->post(route('production.routing.store'), $this->withRoutingFigures($payload));

        $response->assertSessionHasErrors(['operations.0.machine_id']);
    }

    public function test_alternative_operation_with_priority_and_replaces_row_uid(): void
    {
        $payload = [
            'name' => 'Alternative Operations Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'row_uid' => 'uid-prim-10',
                    'name' => 'Primary Machine Cutting',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'section_id' => $this->sectionA->id,
                    'machine_id' => $this->machineA->id,
                    'section_manual_production_id' => '',
                    'is_alternative' => 0,
                    'setup_time_minutes' => 5.0,
                    'processing_time_minutes' => 1.0,
                    'expected_yield_percentage' => 100,
                ],
                [
                    'sequence' => 11,
                    'row_uid' => 'uid-alt-11',
                    'name' => 'Alternative Manual Cutting (Backup)',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'section_id' => $this->sectionA->id,
                    'machine_id' => '',
                    'section_manual_production_id' => $this->manualBatchA->id,
                    'is_alternative' => 1,
                    'replaces_row_uid' => 'uid-prim-10',
                    'priority' => 2,
                    'setup_time_minutes' => 2.0,
                    'processing_time_minutes' => 2.0,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->authRequest()
            ->post(route('production.routing.store'), $this->withRoutingFigures($payload));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $routing = Routing::where('name', 'Alternative Operations Routing')->first();
        $this->assertNotNull($routing);
        $this->assertCount(2, $routing->operations);

        $primaryOp = $routing->operations->where('sequence', 10)->first();
        $altOp = $routing->operations->where('sequence', 11)->first();

        $this->assertFalse((bool) $primaryOp->is_alternative);
        $this->assertNull($primaryOp->replaces_operation_id);

        $this->assertTrue((bool) $altOp->is_alternative);
        $this->assertEquals($primaryOp->id, $altOp->replaces_operation_id);
        $this->assertEquals(2, $altOp->priority);
        $this->assertEquals($primaryOp->id, $altOp->primaryOperation->id);
    }

    public function test_alternative_operation_priority_must_be_at_least_one(): void
    {
        $payload = [
            'name' => 'Invalid Priority Alt Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'row_uid' => 'uid-p1',
                    'name' => 'Primary Op',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'section_id' => $this->sectionA->id,
                    'machine_id' => $this->machineA->id,
                    'is_alternative' => 0,
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 1,
                    'expected_yield_percentage' => 100,
                ],
                [
                    'sequence' => 11,
                    'row_uid' => 'uid-a1',
                    'name' => 'Alternative Op Zero Priority',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'section_id' => $this->sectionA->id,
                    'machine_id' => '',
                    'section_manual_production_id' => $this->manualBatchA->id,
                    'is_alternative' => 1,
                    'replaces_row_uid' => 'uid-p1',
                    'priority' => 0, // Must be >= 1
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 1,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->authRequest()
            ->post(route('production.routing.store'), $this->withRoutingFigures($payload));

        $response->assertSessionHasErrors(['operations.1.priority']);
    }

    public function test_routing_cost_service_excludes_alternative_operations_from_primary_total(): void
    {
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-COST-001',
            'name' => 'Cost Calculation Routing',
            'version' => '1.0.0',
            'status' => Routing::STATUS_DRAFT,
        ]);

        $primOp = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Primary Operation',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenterA->id,
            'section_id' => $this->sectionA->id,
            'machine_id' => $this->machineA->id,
            'is_alternative' => false,
            'setup_time_minutes' => 0,
            'processing_time_minutes' => 10, // 10 mins
            'labor_cost_rate' => 1.00, // $10 labor
            'machine_cost_rate' => 0.50, // $5 machine -> Total = $15
            'expected_yield_percentage' => 100,
            // RT-IMPL-14: readers use the figures saved on the operation
            'labor_cost_per_unit' => 10.00,
            'machine_cost_per_unit' => 5.00,
            'cost_per_unit' => 15.00,
        ]);

        $altOp = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 15,
            'operation_number' => 'OP-015',
            'name' => 'Alternative Operation',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenterA->id,
            'section_id' => $this->sectionA->id,
            'section_manual_production_id' => $this->manualBatchA->id,
            'is_alternative' => true,
            'replaces_operation_id' => $primOp->id,
            'priority' => 2,
            'setup_time_minutes' => 0,
            'processing_time_minutes' => 20, // 20 mins
            'labor_cost_rate' => 2.00, // $40
            'machine_cost_rate' => 0.00,
            'expected_yield_percentage' => 100,
            'labor_cost_per_unit' => 40.00,
            'machine_cost_per_unit' => 0.00,
            'cost_per_unit' => 40.00,
        ]);

        $costService = app(RoutingCostService::class);
        $costData = $costService->calculateRoutingCost($routing->id);

        // The primary total cost should be exactly 15.00 (from primOp), excluding altOp (40.00)
        $this->assertEquals(15.00, $costData['total_cost']);
        $this->assertEquals(10.00, $costData['labor_cost']);
        $this->assertEquals(5.00, $costData['machine_cost']);

        // Individual estimatedCostPerUnit checks
        $this->assertEquals(15.00, $primOp->estimatedCostPerUnit());
        $this->assertEquals(40.00, $altOp->estimatedCostPerUnit());
    }

    public function test_section_resources_endpoint_returns_machines_batches_and_labor_cost(): void
    {
        // RT-IMPL-14: the endpoint reads the latest section snapshot — calculate the Work Center first
        app(\App\Domains\Production\Services\CountdownCalculationEngine::class)->recalculateNow(
            $this->workCenterA->fresh(), 'manual', \App\Domains\Production\Services\CountdownCalculationEngine::SCOPE_ALL
        );

        $response = $this->authRequest()
            ->getJson(route('production.sections.resources', $this->sectionA->id));

        $response->assertOk();
        $response->assertJsonStructure([
            'section_id',
            'section_name',
            'machines' => [
                '*' => ['id', 'name', 'code', 'capacity', 'operating_cost_per_hour', 'speed_minutes_per_unit', 'cost_per_unit'],
            ],
            'manual_batches' => [
                '*' => ['id', 'batch_name', 'capacity_per_hour', 'speed_minutes_per_unit', 'labor_cost_per_hour', 'cost_per_unit'],
            ],
            'operator_labor_cost_per_machine_per_hour',
            'unassigned_labor_hourly_total',
            'active_machines_count',
        ]);

        $data = $response->json();
        $this->assertCount(1, $data['machines']);
        $this->assertEquals($this->machineA->id, $data['machines'][0]['id']);
        $this->assertEquals(1.0, $data['machines'][0]['speed_minutes_per_unit']); // 60 / 60

        $this->assertCount(1, $data['manual_batches']);
        $this->assertEquals($this->manualBatchA->id, $data['manual_batches'][0]['id']);
        $this->assertEquals(2.0, $data['manual_batches'][0]['speed_minutes_per_unit']); // 60 / 30
    }

    public function test_outsourced_operation_does_not_require_work_center_or_section(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Precision Anodizing Vendor',
            'code' => 'VEND-ANOD-01',
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'Subcontracted Routing',
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'row_uid' => 'uid-sub-1',
                    'name' => 'External Anodizing Coating',
                    'operation_type' => 'outsourcing',
                    'is_external' => 1,
                    'vendor_id' => $vendor->id,
                    'work_center_id' => '',
                    'section_id' => '',
                    'machine_id' => '',
                    'section_manual_production_id' => '',
                    'subcontract_cost_per_unit' => 12.50,
                    'subcontract_lead_time_days' => 3,
                    'setup_time_minutes' => 0,
                    'processing_time_minutes' => 0,
                    'expected_yield_percentage' => 100,
                ],
            ],
        ];

        $response = $this->authRequest()
            ->post(route('production.routing.store'), $this->withRoutingFigures($payload));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $routing = Routing::where('name', 'Subcontracted Routing')->first();
        $this->assertNotNull($routing);

        $op = $routing->operations->first();
        $this->assertTrue((bool) $op->is_external);
        $this->assertNull($op->work_center_id);
        $this->assertNull($op->section_id);
        $this->assertEquals(12.50, $op->estimatedCostPerUnit());
    }
}