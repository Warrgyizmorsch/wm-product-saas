<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionPlanRequirement;
use App\Domains\Production\Models\ProductionPlanOperation;
use App\Domains\Production\Services\PlanningValidationService;
use App\Domains\Production\Services\MrpEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionPlanningTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private Product $finishedGood;
    private Product $rawMaterial;
    private ProductionBom $bom;
    private Routing $routing;
    private WorkCenter $workCenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Planning Test Tenant',
            'slug' => 'planning-test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Planning Admin',
            'email' => 'planning-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        // Products
        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Bicycle',
            'sku' => 'FG-BIKE',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Frame',
            'sku' => 'RM-FRAME',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        // BOM
        $this->bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-001',
            'bom_name' => 'BOM Bike',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedGood->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => date('Y-m-d'),
            'status' => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $this->bom->id,
            'material_id' => $this->rawMaterial->id,
            'quantity' => 2.0,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 10.0, // 10% scrap
        ]);

        // Work Center
        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly WC',
            'code' => 'WC-ASSY',
            'type' => 'work_center',
            'capacity_per_hour' => 1.0, // 1 capacity unit per hour
            'efficiency_percentage' => 100.0,
            'cost_per_hour' => 20.0,
            'overhead_rate' => 10.0,
            'status' => 'active',
        ]);

        // Routing
        $this->routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-001',
            'name' => 'Routing Bike',
            'product_id' => $this->finishedGood->id,
            'version' => '1.0.0',
            'status' => 'active',
            'is_default' => true,
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Frame Assembly',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 15.00,
            'processing_time_minutes' => 30.00,
            'labor_cost_rate' => 15.00,
            'machine_cost_rate' => 10.00,
        ]);
    }

    /**
     * Test plan CRUD and standard workflow status transitions.
     */
    public function test_plan_crud_and_status_transitions(): void
    {
        // 1. Create plan
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'planning-test')
            ->post(route('production.plans.store'), [
                'name' => 'Batch 001',
                'product_id' => $this->finishedGood->id,
                'quantity' => 10.0,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+5 days')),
                'bom_id' => $this->bom->id,
                'routing_id' => $this->routing->id,
                'description' => 'Test plan',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_plans', [
            'name' => 'Batch 001',
            'quantity' => 10.0,
            'status' => 'draft',
        ]);

        $plan = ProductionPlan::first();

        // 2. Submit Approval
        $response = $this->actingAs($this->user)
            ->post(route('production.plans.submit', $plan->id));
        
        $this->assertEquals(ProductionPlan::STATUS_PENDING_APPROVAL, $plan->fresh()->status);

        // 3. Approve Plan
        $response = $this->actingAs($this->user)
            ->post(route('production.plans.approve', $plan->id));

        $this->assertEquals(ProductionPlan::STATUS_APPROVED, $plan->fresh()->status);

        // 4. Run MRP
        $response = $this->actingAs($this->user)
            ->post(route('production.plans.run-mrp', $plan->id));

        $this->assertEquals(ProductionPlan::STATUS_MRP_GENERATED, $plan->fresh()->status);
        $this->assertDatabaseHas('production_plan_requirements', [
            'production_plan_id' => $plan->id,
            'product_id' => $this->rawMaterial->id,
            // 2 qty * 10 plan qty * 1.10 scrap factor = 22 qty
            'required_quantity' => 22.0,
        ]);

        $this->assertDatabaseHas('production_plan_operations', [
            'production_plan_id' => $plan->id,
            'operation_number' => 'OP-010',
            // 15 setup + 30 processing * 10 = 315 mins
            'total_time_minutes' => 315.0,
        ]);

        // 5. Release
        $response = $this->actingAs($this->user)
            ->post(route('production.plans.release', $plan->id));
        $this->assertEquals(ProductionPlan::STATUS_RELEASED, $plan->fresh()->status);

        // 6. Complete
        $response = $this->actingAs($this->user)
            ->post(route('production.plans.complete', $plan->id));
        $this->assertEquals(ProductionPlan::STATUS_COMPLETED, $plan->fresh()->status);

        // 7. Close
        $response = $this->actingAs($this->user)
            ->post(route('production.plans.close', $plan->id));
        $this->assertEquals(ProductionPlan::STATUS_CLOSED, $plan->fresh()->status);
    }

    /**
     * Test plan freezing constraints.
     */
    public function test_plan_cannot_be_updated_when_frozen(): void
    {
        $plan = ProductionPlan::create([
            'tenant_id' => $this->tenant->id,
            'plan_number' => 'PLN-2026-000100',
            'name' => 'Frozen Batch',
            'product_id' => $this->finishedGood->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity' => 10.0,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d'),
            'status' => ProductionPlan::STATUS_APPROVED, // Approved state is frozen
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'planning-test')
            ->put(route('production.plans.update', $plan->id), [
                'name' => 'Modified Name',
                'product_id' => $this->finishedGood->id,
                'quantity' => 20.0, // should not update
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d'),
            ]);

        // Assert redirect back with error or session error
        $response->assertStatus(403);
        $this->assertEquals(10.0, $plan->fresh()->quantity);
    }

    /**
     * Test capacity overload warning checks.
     */
    public function test_capacity_overload_generates_warnings(): void
    {
        // 1. Create a plan for a very large quantity over 1 day planning window
        $plan = ProductionPlan::create([
            'tenant_id' => $this->tenant->id,
            'plan_number' => 'PLN-2026-000200',
            'name' => 'Overload Batch',
            'product_id' => $this->finishedGood->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity' => 20.0, // 20 units * 30 min = 600 min + 15 setup = 615 min. (Available capacity is 8 hours * 60 min = 480 mins)
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d'), // 1 day window
            'status' => 'draft',
        ]);

        // Run MRP to capture capacity load snapshot
        app(MrpEngineService::class)->runMrp($plan);

        // Run validation engine
        $warnings = app(PlanningValidationService::class)->validatePlan($plan);

        $hasCapacityOverload = false;
        foreach ($warnings as $warn) {
            if ($warn['type'] === 'capacity_overload') {
                $hasCapacityOverload = true;
                $this->assertStringContainsString('overload detected', $warn['message']);
            }
        }

        $this->assertTrue($hasCapacityOverload, "Capacity warning should have been flagged.");
    }
}
