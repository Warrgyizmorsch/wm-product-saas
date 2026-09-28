<?php

namespace Tests\Feature\Api\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRemnantAllocation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionPhase2ApisTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $plannerA;
    private User $plannerB;
    private Uom $uom;
    private Product $finishedGood;
    private Product $rawMaterial;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Warehouse $warehouse;
    private ProductionBom $bom;
    private Routing $routing;
    private RoutingOperation $routingOp1;
    private RoutingOperation $routingOp2;
    private string $secret = 'wm-production-secret-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenantA = Tenant::create([
            'name' => 'Apex Manufacturing',
            'slug' => 'apex-mfg',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Beta Industries',
            'slug' => 'beta-ind',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->plannerA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Apex Chief Engineer',
            'email' => 'engineer@apex.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->plannerB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Operator',
            'email' => 'operator@beta.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $managerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();

        UserRole::create([
            'user_id' => $this->plannerA->id,
            'role_id' => $managerRole->id,
            'tenant_id' => $this->tenantA->id,
        ]);

        UserRole::create([
            'user_id' => $this->plannerB->id,
            'role_id' => $managerRole->id,
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Hydraulic Actuator Cyl',
            'sku' => 'FG-ACT-001',
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'unit_cost' => 350.0,
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Chrome Steel Rod',
            'sku' => 'RM-ROD-001',
            'type' => 'raw_material',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'unit_cost' => 80.0,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Plant Main Store',
            'code' => 'WHS-MAIN',
            'status' => 'active',
            'is_default' => true,
        ]);

        StockService::recordInflow(
            $this->tenantA->id,
            $this->rawMaterial->id,
            $this->warehouse->id,
            200.0,
            80.0,
            'Initial Stocking'
        );

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Machining Center 1',
            'code' => 'WC-MCH-01',
            'work_center_type' => 'work_center',
            'capacity_per_hour' => 5.0,
            'efficiency_percentage' => 90.0,
            'cost_per_hour' => 50.0,
            'status' => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'CNC Lathe 1',
            'code' => 'MCH-LTH-01',
            'machine_type' => 'cnc_lathe',
            'status' => Machine::STATUS_ACTIVE,
            'capacity' => 1.0,
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->finishedGood->id,
            'routing_number' => 'RTG-ACT-001',
            'name' => 'Standard Turning Routing',
            'version' => '1.0',
            'status' => Routing::STATUS_ACTIVE,
            'created_by' => $this->plannerA->id,
        ]);

        $this->routingOp1 = RoutingOperation::create([
            'tenant_id' => $this->tenantA->id,
            'routing_id' => $this->routing->id,
            'sequence' => 1,
            'operation_number' => 'OP-10',
            'name' => 'Turning & Facing',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'setup_time_minutes' => 15,
            'cycle_time_minutes' => 20,
        ]);

        $this->routingOp2 = RoutingOperation::create([
            'tenant_id' => $this->tenantA->id,
            'routing_id' => $this->routing->id,
            'sequence' => 2,
            'operation_number' => 'OP-20',
            'name' => 'Finishing & Inspection',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'setup_time_minutes' => 10,
            'cycle_time_minutes' => 15,
        ]);

        $this->bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->finishedGood->id,
            'routing_id' => $this->routing->id,
            'uom_id' => $this->uom->id,
            'bom_number' => 'BOM-ACT-001',
            'bom_name' => 'Actuator Main BOM',
            'version' => '1.0',
            'base_quantity' => 1.0,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'approved_by' => $this->plannerA->id,
            'approved_at' => now(),
            'created_by' => $this->plannerA->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantA->id,
            'bom_id' => $this->bom->id,
            'material_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'quantity' => 2.0,
            'cost_allocation_percentage' => 100.0,
        ]);
    }

    private function headers(User $user, ?string $idempotencyKey = null, ?Tenant $tenant = null): array
    {
        $tenantObj = $tenant ?? ($user->id === $this->plannerA->id ? $this->tenantA : $this->tenantB);
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant' => $tenantObj->slug,
            'X-API-SECRET' => $this->secret,
            'Authorization' => 'Bearer ' . $user->createToken('test-phase2')->plainTextToken,
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    // ── Phase 2A: Production Plans ──────────────────────────────────────────

    public function test_production_plan_full_lifecycle_and_operations(): void
    {
        // 1. Create Draft Plan
        $plan = ProductionPlan::create([
            'tenant_id' => $this->tenantA->id,
            'plan_number' => 'PLN-TST-001',
            'name' => 'Hydraulic Cyl Q3 Run',
            'product_id' => $this->finishedGood->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity' => 25.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionPlan::STATUS_DRAFT,
            'created_by' => $this->plannerA->id,
        ]);

        // Submit for approval
        $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/plans/{$plan->id}/submit")
            ->assertStatus(200);
        $this->assertEquals(ProductionPlan::STATUS_PENDING_APPROVAL, $plan->fresh()->status);

        // Reject back to draft
        $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/plans/{$plan->id}/reject")
            ->assertStatus(200);
        $this->assertEquals(ProductionPlan::STATUS_DRAFT, $plan->fresh()->status);

        // Submit again and Approve
        $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/plans/{$plan->id}/submit")
            ->assertStatus(200);
        $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/plans/{$plan->id}/approve")
            ->assertStatus(200);
        $this->assertEquals(ProductionPlan::STATUS_APPROVED, $plan->fresh()->status);

        // Run MRP
        $mrpRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/plans/{$plan->id}/run-mrp");
        $mrpRes->assertStatus(200);

        // Release Plan
        $releaseRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/plans/{$plan->id}/release");
        $releaseRes->assertStatus(200);
        $this->assertEquals(ProductionPlan::STATUS_RELEASED, $plan->fresh()->status);

        // Complete Plan
        $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/plans/{$plan->id}/complete")
            ->assertStatus(200);
        $this->assertEquals(ProductionPlan::STATUS_COMPLETED, $plan->fresh()->status);

        // Close Plan
        $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/plans/{$plan->id}/close")
            ->assertStatus(200);
        $this->assertEquals(ProductionPlan::STATUS_CLOSED, $plan->fresh()->status);

        // Export Plans
        $exportRes = $this->withHeaders($this->headers($this->plannerA))
            ->getJson('/api/v1/production/plans/export');
        $exportRes->assertStatus(200);

        // Delete draft plan test
        $draftPlan = ProductionPlan::create([
            'tenant_id' => $this->tenantA->id,
            'plan_number' => 'PLN-TST-DEL',
            'name' => 'To Be Deleted',
            'product_id' => $this->finishedGood->id,
            'quantity' => 5.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => ProductionPlan::STATUS_DRAFT,
            'created_by' => $this->plannerA->id,
        ]);
        $this->withHeaders($this->headers($this->plannerA))
            ->deleteJson("/api/v1/production/plans/{$draftPlan->id}")
            ->assertStatus(200);
        $this->assertNull(ProductionPlan::find($draftPlan->id));
    }

    // ── Phase 2B: Production Orders ─────────────────────────────────────────

    public function test_production_order_extended_parity(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'ORD-PHASE2-01',
            'product_id' => $this->finishedGood->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_RELEASED,
            'created_by' => $this->plannerA->id,
        ]);

        $resv = ProductionOrderReservation::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => 20.0,
            'quantity_reserved' => 20.0,
            'quantity_issued' => 15.0,
            'uom_id' => $this->uom->id,
        ]);

        $orderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'sequence' => 1,
            'operation_number' => 'OP-10',
            'name' => 'Turning',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'planned_quantity' => 10.0,
        ]);

        // 1. Return Material
        $returnRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/orders/{$order->id}/return-material", [
                'reservation_id' => $resv->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 2.0,
                'remarks' => 'Excess material returned',
            ]);
        $returnRes->assertStatus(200);

        // 2. Request Additional Material
        $reqRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/orders/{$order->id}/request-additional-material", [
                'items' => [
                    [
                        'product_id' => $this->rawMaterial->id,
                        'quantity' => 3.0,
                        'notes' => 'Extra stock for calibration test',
                    ],
                ],
                'notes' => 'Ad-hoc addition required',
            ]);
        $reqRes->assertStatus(201)
            ->assertJsonPath('success', true);

        // 3. Log Rework
        $reworkRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/orders/{$order->id}/rework", [
                'operation_id' => $orderOp->id,
                'quantity' => 1.0,
                'reason' => 'Surface scratch requires re-polishing',
            ]);
        $reworkRes->assertStatus(200);

        // 4. Export Orders
        $exportRes = $this->withHeaders($this->headers($this->plannerA))
            ->getJson('/api/v1/production/orders/export');
        $exportRes->assertStatus(200);

        // 5. Complete & Close Order
        $orderOp->update(['status' => ProductionOrderOperation::STATUS_COMPLETED]);
        $order->update(['status' => ProductionOrder::STATUS_IN_PROGRESS]);

        $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/orders/{$order->id}/complete")
            ->assertStatus(200);

        $closeRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/orders/{$order->id}/close");
        $closeRes->assertStatus(200);
        $this->assertEquals(ProductionOrder::STATUS_CLOSED, $order->fresh()->status);
    }

    // ── Phase 2C: MES / Shop Floor ──────────────────────────────────────────

    public function test_mes_hold_and_andon_alert(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'ORD-MES-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => ProductionOrder::STATUS_RELEASED,
            'created_by' => $this->plannerA->id,
        ]);

        $orderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'sequence' => 1,
            'operation_number' => 'OP-MES-10',
            'name' => 'Boring',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'status' => ProductionOrderOperation::STATUS_READY,
            'planned_quantity' => 10.0,
        ]);

        // Start operation
        $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/mes/operations/{$orderOp->id}/start")
            ->assertStatus(200);

        // Hold operation
        $holdRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/mes/operations/{$orderOp->id}/hold", [
                'remarks' => 'Operator shift change pause',
            ]);
        $holdRes->assertStatus(200);

        // Report Andon Alert
        $andonRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/mes/operations/{$orderOp->id}/andon-alert", [
                'category' => 'Machine Breakdown',
                'severity' => 'critical',
                'reason' => 'Spindle vibration above tolerance',
                'remarks' => 'Immediate maintenance inspection needed',
            ]);
        $andonRes->assertStatus(201);
    }

    // ── Phase 2D: Quality Management ────────────────────────────────────────

    public function test_quality_plans_crud(): void
    {
        // 1. Create Quality Plan
        $createRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson('/api/v1/production/quality-plans', [
                'name' => 'Actuator Dimensional Verification',
                'version' => '1.0',
                'type' => 'product',
                'product_id' => $this->finishedGood->id,
                'status' => 'draft',
                'parameters' => [
                    [
                        'name' => 'Piston Rod Diameter',
                        'type' => 'numeric',
                        'min_value' => 24.95,
                        'max_value' => 25.05,
                        'unit_of_measure' => 'mm',
                        'is_mandatory' => true,
                    ],
                ],
            ]);
        $createRes->assertStatus(201);
        $planId = $createRes->json('data.id');

        // 2. Show
        $this->withHeaders($this->headers($this->plannerA))
            ->getJson("/api/v1/production/quality-plans/{$planId}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Actuator Dimensional Verification');

        // 3. Update
        $this->withHeaders($this->headers($this->plannerA))
            ->putJson("/api/v1/production/quality-plans/{$planId}", [
                'name' => 'Actuator Dimensional Verification v2',
                'version' => '2.0',
                'type' => 'product',
                'product_id' => $this->finishedGood->id,
                'status' => 'approved',
                'parameters' => [
                    [
                        'name' => 'Piston Rod Diameter Strict',
                        'type' => 'numeric',
                        'min_value' => 24.98,
                        'max_value' => 25.02,
                        'unit_of_measure' => 'mm',
                        'is_mandatory' => true,
                    ],
                ],
            ])
            ->assertStatus(200);

        // 4. Index
        $this->withHeaders($this->headers($this->plannerA))
            ->getJson('/api/v1/production/quality-plans')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // 5. Delete
        $this->withHeaders($this->headers($this->plannerA))
            ->deleteJson("/api/v1/production/quality-plans/{$planId}")
            ->assertStatus(200);
        $this->assertNull(ProductionQualityPlan::find($planId));
    }

    public function test_ncr_lifecycle_and_scrap_approval(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'ORD-NCR-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 5.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => ProductionOrder::STATUS_RELEASED,
            'created_by' => $this->plannerA->id,
        ]);

        // 1. Create NCR
        $createRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson('/api/v1/production/quality/ncrs', [
                'category' => 'process',
                'description' => 'Turning radius off-spec by 0.5mm',
                'production_order_id' => $order->id,
            ]);
        $createRes->assertStatus(201);
        $ncrId = $createRes->json('data.id');

        // 2. Disposition NCR
        $dispRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/quality/ncrs/{$ncrId}/disposition", [
                'disposition_type' => 'scrap',
                'category' => 'finished_good',
                'reason_code' => 'dimensional_defect',
                'quantity' => 1.0,
                'cost' => 120.0,
            ]);
        $dispRes->assertStatus(200);

        // 3. Close NCR
        $closeRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/quality/ncrs/{$ncrId}/close", [
                'esignature' => 'NCR-VERIFY-001',
            ]);
        $closeRes->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        // 4. Scrap disposal approval
        $scrap = ProductionScrapDisposal::create([
            'tenant_id' => $this->tenantA->id,
            'category' => 'finished_good',
            'reason_code' => 'defect',
            'quantity' => 1.0,
            'cost' => 120.0,
            'status' => 'pending_approval',
            'created_by' => $this->plannerA->id,
        ]);

        $apprRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/quality/scrap/{$scrap->id}/approve");
        $apprRes->assertStatus(200);
        $this->assertEquals('approved', $scrap->fresh()->status);
    }

    // ── Phase 2E: WIP (Work-In-Progress) ────────────────────────────────────

    public function test_wip_tracking_without_monetary_valuation(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'ORD-WIP-01',
            'product_id' => $this->finishedGood->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'created_by' => $this->plannerA->id,
        ]);

        $op1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->routingOp1->id,
            'sequence' => 1,
            'operation_number' => 'OP-WIP-1',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'planned_quantity' => 10.0,
            'quantity_produced' => 10.0,
        ]);

        $op2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->routingOp2->id,
            'sequence' => 2,
            'operation_number' => 'OP-WIP-2',
            'name' => 'Finishing',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_READY,
            'planned_quantity' => 10.0,
        ]);

        $wip = ProductionWip::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'product_id' => $this->finishedGood->id,
            'current_routing_operation_id' => $this->routingOp1->id,
            'current_work_center_id' => $this->workCenter->id,
            'current_machine_id' => $this->machine->id,
            'quantity' => 10.0,
            'available_quantity' => 10.0,
            'status' => 'active',
            'started_at' => now(),
            'created_by' => $this->plannerA->id,
        ]);

        // 1. WIP Index
        $indexRes = $this->withHeaders($this->headers($this->plannerA))
            ->getJson('/api/v1/production/wip');
        $indexRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Verify NO monetary valuation fields are leaked in API response
        $wipItem = $indexRes->json('data.0');
        $this->assertArrayNotHasKey('material_cost', $wipItem);
        $this->assertArrayNotHasKey('labor_cost', $wipItem);
        $this->assertArrayNotHasKey('total_value', $wipItem);

        // 2. WIP Show
        $this->withHeaders($this->headers($this->plannerA))
            ->getJson("/api/v1/production/wip/{$wip->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.quantity', 10);

        // 3. WIP Transfer
        $transferRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/wip/{$wip->id}/transfer", [
                'from_operation_id' => $op1->id,
                'to_operation_id' => $op2->id,
                'quantity' => 5.0,
                'remarks' => 'First half moved to finishing',
            ]);
        $transferRes->assertStatus(200);

        // 4. WIP Convert to Finished Goods
        $convertRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/wip/{$wip->id}/convert", [
                'warehouse_id' => $this->warehouse->id,
                'quality_status' => 'passed',
                'remarks' => 'Completed and transferred to stock',
            ]);
        $convertRes->assertStatus(200);

        // 5. WIP Export
        $this->withHeaders($this->headers($this->plannerA))
            ->getJson('/api/v1/production/wip/export')
            ->assertStatus(200);
    }

    // ── Phase 2F: Production Scheduling ─────────────────────────────────────

    public function test_production_scheduling_lifecycle(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'ORD-SCH-01',
            'product_id' => $this->finishedGood->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 15.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_RELEASED,
            'created_by' => $this->plannerA->id,
        ]);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'sequence' => 1,
            'operation_number' => 'OP-SCH-01',
            'name' => 'Assembly',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'status' => ProductionOrderOperation::STATUS_READY,
            'planned_quantity' => 15.0,
            'standard_cycle_time_seconds' => 60,
        ]);

        // 1. Generate Schedule
        $storeRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson('/api/v1/production/schedules', [
                'production_order_id' => $order->id,
                'scheduling_type' => 'forward',
                'start_date' => now()->addDay()->toDateTimeString(),
                'notes' => 'Forward scheduled rush job',
            ]);
        $storeRes->assertStatus(201);
        $scheduleId = $storeRes->json('data.id');

        // 2. Show Schedule
        $this->withHeaders($this->headers($this->plannerA))
            ->getJson("/api/v1/production/schedules/{$scheduleId}")
            ->assertStatus(200)
            ->assertJsonPath('data.scheduling_type', 'forward');

        // 3. Release Schedule
        $releaseRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/schedules/{$scheduleId}/release");
        $releaseRes->assertStatus(200)
            ->assertJsonPath('data.status', ProductionSchedule::STATUS_RELEASED);

        // 4. Cancel Schedule
        $cancelRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/schedules/{$scheduleId}/cancel");
        $cancelRes->assertStatus(200)
            ->assertJsonPath('data.status', ProductionSchedule::STATUS_CANCELLED);

        // 5. Export Schedules
        $this->withHeaders($this->headers($this->plannerA))
            ->getJson('/api/v1/production/schedules/export')
            ->assertStatus(200);
    }

    // ── Phase 2G: Plant Maintenance ─────────────────────────────────────────

    public function test_plant_maintenance_work_orders(): void
    {
        // 1. Create Preventive Work Order
        $createRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson('/api/v1/production/maintenance/work-orders', [
                'machine_id' => $this->machine->id,
                'type' => 'preventive',
                'priority' => 'medium',
                'problem_description' => 'Monthly lubrication and alignment check',
            ]);
        $createRes->assertStatus(201);
        $woId = $createRes->json('data.id');

        // 2. Show Work Order
        $this->withHeaders($this->headers($this->plannerA))
            ->getJson("/api/v1/production/maintenance/work-orders/{$woId}")
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'preventive');

        // 3. Complete Work Order
        $completeRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/maintenance/work-orders/{$woId}/complete", [
                'work_performed' => 'Replaced spindle oil and recalibrated cross-slide',
                'labor_hours' => 2.5,
            ]);
        $completeRes->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        // 4. Report Breakdown
        $breakdownRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson('/api/v1/production/maintenance/work-orders/breakdown', [
                'machine_id' => $this->machine->id,
                'reason' => 'Emergency stop tripped due to overheating sensor',
                'priority' => 'critical',
            ]);
        $breakdownRes->assertStatus(201);
        $breakdownWoId = $breakdownRes->json('data.id');

        // 5. Cancel Work Order
        $cancelRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson("/api/v1/production/maintenance/work-orders/{$breakdownWoId}/cancel", [
                'reason' => 'False alarm, sensor reset and verified normal',
            ]);
        $cancelRes->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    // ── Phase 2H: Production Shifts ─────────────────────────────────────────

    public function test_production_shifts_crud(): void
    {
        // 1. Create Shift
        $storeRes = $this->withHeaders($this->headers($this->plannerA))
            ->postJson('/api/v1/production/shifts', [
                'name' => 'Morning Shift A',
                'code' => 'SFT-MORN-A',
                'start_time' => '06:00',
                'end_time' => '14:00',
                'break_minutes' => 45,
                'overtime_allowed' => true,
                'active' => true,
            ]);
        $storeRes->assertStatus(201);
        $shiftId = $storeRes->json('data.id');

        // 2. Show Shift
        $this->withHeaders($this->headers($this->plannerA))
            ->getJson("/api/v1/production/shifts/{$shiftId}")
            ->assertStatus(200)
            ->assertJsonPath('data.code', 'SFT-MORN-A');

        // 3. Update Shift
        $this->withHeaders($this->headers($this->plannerA))
            ->putJson("/api/v1/production/shifts/{$shiftId}", [
                'name' => 'Morning Shift A Extended',
                'code' => 'SFT-MORN-A',
                'start_time' => '06:00',
                'end_time' => '14:30',
                'break_minutes' => 60,
                'overtime_allowed' => false,
                'active' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.break_minutes', 60);

        // 4. Index Shifts
        $this->withHeaders($this->headers($this->plannerA))
            ->getJson('/api/v1/production/shifts')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // 5. Delete Shift
        $this->withHeaders($this->headers($this->plannerA))
            ->deleteJson("/api/v1/production/shifts/{$shiftId}")
            ->assertStatus(200);
        $this->assertNull(ProductionShift::find($shiftId));
    }

    // ── Tenant Isolation ────────────────────────────────────────────────────

    public function test_tenant_isolation_on_phase2_endpoints(): void
    {
        // Tenant A creates a Quality Plan
        $planA = ProductionQualityPlan::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Proprietary Quality Spec',
            'version' => '1.0',
            'type' => 'product',
            'status' => 'draft',
            'created_by' => $this->plannerA->id,
        ]);

        // Tenant B attempts to read Tenant A's Quality Plan
        $crossRes = $this->withHeaders($this->headers($this->plannerB))
            ->getJson("/api/v1/production/quality-plans/{$planA->id}");
        $crossRes->assertStatus(404);

        // Tenant A creates an NCR
        $ncrA = ProductionNcr::create([
            'tenant_id' => $this->tenantA->id,
            'ncr_number' => 'NCR-TENANT-A-01',
            'category' => 'process',
            'status' => 'open',
            'description' => 'Tenant A confidential issue',
        ]);

        // Tenant B attempts to disposition Tenant A's NCR
        $crossNcrRes = $this->withHeaders($this->headers($this->plannerB))
            ->postJson("/api/v1/production/quality/ncrs/{$ncrA->id}/disposition", [
                'disposition_type' => 'scrap',
            ]);
        $crossNcrRes->assertStatus(404);
    }
}
