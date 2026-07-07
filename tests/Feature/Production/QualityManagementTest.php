<?php

namespace Tests\Feature\Production;

use App\Models\User;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityInspectionResult;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionReworkOperation;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\QualityInspectionService;
use App\Domains\Production\Services\NcrService;
use App\Domains\Production\Services\CapaService;
use App\Domains\Production\Services\ReworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;
    private ProductionOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Tenant::factory()->create([
            'id' => $this->tenantId,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        $this->actingAs($this->user);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Assembly Center',
            'code'      => 'WC-ASM-01',
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name'           => 'CNC Mill A',
            'code'           => 'MCH-CNC-A',
            'current_state'  => 'Idle',
        ]);

        $this->order = ProductionOrder::create([
            'tenant_id'          => $this->tenantId,
            'status'             => 'draft',
            'quantity_to_produce'=> 100,
        ]);
    }

    /** @test */
    public function quality_checklist_execution_and_auto_ncr_generation()
    {
        // 1. Create a quality plan template
        $plan = ProductionQualityPlan::create([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Screws Tension Plan',
            'version'    => '1.0',
            'type'       => 'process',
            'created_by' => $this->user->id,
        ]);

        // Numeric parameter
        $param1 = ProductionQualityPlanParameter::create([
            'tenant_id'       => $this->tenantId,
            'quality_plan_id' => $plan->id,
            'name'            => 'Tension level',
            'type'            => 'numeric',
            'min_value'       => 10.00,
            'max_value'       => 20.00,
            'is_mandatory'    => true,
        ]);

        // Pass/Fail parameter
        $param2 = ProductionQualityPlanParameter::create([
            'tenant_id'       => $this->tenantId,
            'quality_plan_id' => $plan->id,
            'name'            => 'Thread visual alignment',
            'type'            => 'pass_fail',
            'is_mandatory'    => true,
        ]);

        $service = app(QualityInspectionService::class);

        // 2. Initialize quality checklist
        $inspection = $service->createInspection($this->tenantId, [
            'quality_plan_id'     => $plan->id,
            'stage'               => 'in_process',
            'production_order_id' => $this->order->id,
            'machine_id'          => $this->machine->id,
            'operator_id'         => $this->user->id,
        ]);

        $this->assertDatabaseHas('production_quality_inspections', [
            'id'     => $inspection->id,
            'status' => 'draft',
        ]);

        // 3. Record results (fail numeric param: 8.50 is below min 10.00)
        $service->recordResults($inspection->id, [
            [
                'parameter_id'  => $param1->id,
                'value_numeric' => 8.50,
            ],
            [
                'parameter_id' => $param2->id,
                'value_pass'   => true,
            ]
        ]);

        $inspection->refresh();
        $this->assertEquals('failed', $inspection->result);
        $this->assertEquals('submitted', $inspection->status);

        // 4. Audit & Approve -> Verify Auto NCR triggers
        $service->approveInspection($inspection->id, $this->user->id, 'SIGNED-PIN');

        $this->assertDatabaseHas('production_ncrs', [
            'tenant_id'             => $this->tenantId,
            'quality_inspection_id' => $inspection->id,
            'status'                => 'open',
            'category'              => 'process',
        ]);
    }

    /** @test */
    public function ncr_disposition_to_rework_execution_costs()
    {
        $ncr = ProductionNcr::create([
            'tenant_id'           => $this->tenantId,
            'ncr_number'          => 'NCR-REWORK-TEST',
            'category'            => 'process',
            'status'              => 'open',
            'production_order_id' => $this->order->id,
            'description'         => 'Welding joint alignment defect',
        ]);

        $ncrService = app(NcrService::class);

        // Apply disposition: rework
        $ncrService->processDisposition($ncr->id, 'rework', [
            'original_production_order_id' => $this->order->id,
            'work_center_id'               => $this->workCenter->id,
            'cost_estimate'                => 200.00,
        ]);

        $ncr->refresh();
        $this->assertEquals('disposition', $ncr->status);
        $this->assertEquals('rework', $ncr->disposition_type);

        $rework = $ncr->reworkOrder;
        $this->assertNotNull($rework);
        $this->assertEquals('draft', $rework->status);
        $this->assertCount(2, $rework->operations);

        // Start & Complete operations to trigger costs accumulation
        $reworkService = app(ReworkService::class);
        $op = $rework->operations->first();

        $reworkService->startOperation($op->id);
        $op->refresh();
        $this->assertEquals('running', $op->status);

        // Mock 1.5 hrs of work completed
        $op->update(['actual_start' => now()->subMinutes(90)]);
        $reworkService->completeOperation($op->id, ['setup_time_actual' => 15]);

        $rework->refresh();
        // Labor rate $35/hr + Machine rate $50/hr = $85/hr * 1.5 hrs = $127.50
        $this->assertEquals(127.50, $rework->actual_cost);
    }

    /** @test */
    public function capa_rca_investigation_and_closure()
    {
        $capa = ProductionCapa::create([
            'tenant_id'       => $this->tenantId,
            'capa_number'     => 'CAPA-TEST-001',
            'status'          => 'draft',
            'action_owner_id' => $this->user->id,
        ]);

        $capaService = app(CapaService::class);

        // Log Root Cause (5 Whys and Fishbone)
        $capaService->recordRca($capa->id, [
            'Why 1: Tool was worn out',
            'Why 2: Scheduled maintenance missed',
            'Why 3: Operator shortage',
            'Why 4: System notifications skipped',
            'Why 5: Notification configs undefined'
        ], [
            'method'  => 'Missing warning dashboard',
            'machine' => 'Uncalibrated sensor calibration',
            'man'     => 'Insufficient supervisor alerts',
        ]);

        $capa->refresh();
        $this->assertEquals('active', $capa->status);
        $this->assertNotNull($capa->rca_analysis_json);

        // Try closing without review notes (should fail)
        try {
            $capaService->closeCapa($capa->id, $this->user->id, '', 'SIGN');
            $this->fail("Closed CAPA without effectiveness review.");
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals("Effectiveness review is required to close a CAPA.", $e->getMessage());
        }

        // Close successfully
        $capaService->closeCapa($capa->id, $this->user->id, 'Training done and configuration confirmed.', 'SIGN-TOKEN');
        $capa->refresh();
        $this->assertEquals('closed', $capa->status);
    }

    /** @test */
    public function quality_records_tenant_isolation()
    {
        $otherTenantId = 99;

        \App\Models\Tenant::factory()->create([
            'id' => $otherTenantId,
        ]);

        $plan = ProductionQualityPlan::create([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Tenant A Quality Plan',
            'type'       => 'process',
            'created_by' => $this->user->id,
        ]);

        $otherPlan = ProductionQualityPlan::create([
            'tenant_id'  => $otherTenantId,
            'name'       => 'Tenant B Quality Plan',
            'type'       => 'process',
            'created_by' => $this->user->id, // bypass user tenant for creation test
        ]);

        $this->assertEquals(1, ProductionQualityPlan::where('tenant_id', $this->tenantId)->count());
        $this->assertEquals(1, ProductionQualityPlan::where('tenant_id', $otherTenantId)->count());
    }
}
