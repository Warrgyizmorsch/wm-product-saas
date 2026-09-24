<?php

namespace Tests\Feature\Api\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionPlan;
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

class ProductionApiWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $planner;
    private Uom $uom;
    private Product $finishedGood;
    private Product $rawMaterial;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Warehouse $warehouse;
    private string $secret = 'wm-production-secret-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Titan Manufacturing',
            'slug' => 'titan-mfg',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->planner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Titan Chief Planner',
            'email' => 'chief@titan.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $managerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->planner->id,
            'role_id' => $managerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heavy Turbine Rotor',
            'sku' => 'FG-ROTOR-01',
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'unit_cost' => 500.0,
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Titanium Ingot',
            'sku' => 'RM-TITANIUM-01',
            'type' => 'raw_material',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'unit_cost' => 120.0,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Central Warehouse',
            'code' => 'WHS-CENTRAL',
            'status' => 'active',
            'is_default' => true,
        ]);

        StockService::recordInflow(
            $this->tenant->id,
            $this->rawMaterial->id,
            $this->warehouse->id,
            100.0,
            120.0,
            'Opening Stock'
        );

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'CNC Milling Cell 1',
            'code' => 'WC-MILL-01',
            'work_center_type' => 'work_center',
            'capacity_per_hour' => 10.0,
            'efficiency_percentage' => 95.0,
            'cost_per_hour' => 45.0,
            'status' => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'name' => '5-Axis CNC Mill Alpha',
            'code' => 'MCH-CNC-001',
            'machine_type' => 'cnc_mill',
            'status' => Machine::STATUS_ACTIVE,
            'capacity' => 1.0,
        ]);
    }

    private function headers(?string $idempotencyKey = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
            'X-API-SECRET' => $this->secret,
            'Authorization' => 'Bearer ' . $this->planner->createToken('test-token')->plainTextToken,
        ];

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    public function test_bom_lifecycle_crud_approve_and_clone(): void
    {
        // 1. Create BOM
        $createRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/boms', [
            'product_id' => $this->finishedGood->id,
            'bom_name' => 'Turbine Rotor Standard BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $this->rawMaterial->id,
                    'quantity' => 2.5,
                    'uom_id' => $this->uom->id,
                    'sequence' => 1,
                ],
            ],
        ]);

        $createRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'bom_name' => 'Turbine Rotor Standard BOM',
                ],
            ]);

        $bomId = $createRes->json('data.id');

        // 2. Read Detail
        $detailRes = $this->withHeaders($this->headers())->getJson('/api/v1/production/boms/' . $bomId);
        $detailRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $bomId,
                ],
            ]);

        // 3. Update BOM
        $updateRes = $this->withHeaders($this->headers())->putJson('/api/v1/production/boms/' . $bomId, [
            'bom_name' => 'Turbine Rotor High Precision BOM',
            'items' => [
                [
                    'material_id' => $this->rawMaterial->id,
                    'quantity' => 2.4,
                    'uom_id' => $this->uom->id,
                ],
            ],
        ]);
        $updateRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'bom_name' => 'Turbine Rotor High Precision BOM',
                ],
            ]);

        // 4. Submit & Approve BOM
        $submitRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/boms/' . $bomId . '/submit');
        $submitRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $approveRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/boms/' . $bomId . '/approve');
        $approveRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 5. Clone BOM
        $cloneRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/boms/' . $bomId . '/clone', [
            'version_type' => 'minor',
        ]);
        $cloneRes->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_routing_lifecycle_crud(): void
    {
        // 1. Create Routing
        $createRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/routings', [
            'name' => 'Turbine Rotor Machining Sequence',
            'product_id' => $this->finishedGood->id,
            'operations' => [
                [
                    'sequence' => 10,
                    'operation_number' => 'OP-010',
                    'name' => 'Rough Face Milling',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'machine_id' => $this->machine->id,
                    'setup_time_minutes' => 30,
                    'processing_time_minutes' => 60,
                ],
            ],
        ]);

        $createRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Turbine Rotor Machining Sequence',
                ],
            ]);

        $routingId = $createRes->json('data.id');

        // 2. Read Routing
        $detailRes = $this->withHeaders($this->headers())->getJson('/api/v1/production/routings/' . $routingId);
        $detailRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $routingId,
                ],
            ]);
    }

    public function test_production_plan_lifecycle_and_approval(): void
    {
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-PLAN-01',
            'bom_name' => 'Plan BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedGood->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->planner->id,
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plan Routing',
            'product_id' => $this->finishedGood->id,
            'status' => 'active',
        ]);

        $planRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/plans', [
            'name' => 'Q4 Turbine Production Master Plan',
            'product_id' => $this->finishedGood->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity' => 50,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
        ]);

        $planRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Q4 Turbine Production Master Plan',
                ],
            ]);

        $planId = $planRes->json('data.id');

        // Submit Plan
        $submitRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/plans/' . $planId . '/submit');
        $submitRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Approve Plan
        $approveRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/plans/' . $planId . '/approve');
        $approveRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_production_order_lifecycle_and_state_transitions(): void
    {
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-TURB-01',
            'bom_name' => 'Turbine BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedGood->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'active',
            'created_by' => $this->planner->id,
        ]);

        // 1. Create Production Order
        $orderRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/orders', [
            'product_id' => $this->finishedGood->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);

        $orderRes->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $orderId = $orderRes->json('data.id');

        // Create reservation & operation for execution actions
        $reservation = ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderId,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'uom_id' => $this->uom->id,
            'quantity_planned' => 20,
            'quantity_reserved' => 20,
            'quantity_issued' => 0,
            'status' => 'reserved',
        ]);

        $operation = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderId,
            'sequence' => 1,
            'operation_number' => 'OP-01',
            'name' => 'Assembly',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'status' => 'ready',
        ]);

        // 2. Release Order
        $releaseRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/orders/' . $orderId . '/release');
        $releaseRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 3. Issue Material
        $issueRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/orders/' . $orderId . '/issue-material', [
            'reservation_id' => $reservation->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
        ]);
        $issueRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 4. Log Progress
        $progressRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/orders/' . $orderId . '/progress', [
            'operation_id' => $operation->id,
            'quantity_produced' => 5,
            'complete_operation' => true,
        ]);
        $progressRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 5. Receive FG
        $receiveRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/orders/' . $orderId . '/receive-fg', [
            'quantity_received' => 5,
            'warehouse_id' => $this->warehouse->id,
            'quality_status' => 'passed',
        ]);
        $receiveRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 6. Log Scrap
        $scrapRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/orders/' . $orderId . '/scrap', [
            'quantity' => 1,
            'reason' => 'Porosity defect during casing inspection',
        ]);
        $scrapRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 7. Complete Order
        $completeRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/orders/' . $orderId . '/complete');
        $completeRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_mes_operator_execution_flow(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-MES-99',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 5,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'released',
            'created_by' => $this->planner->id,
        ]);

        $operation = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 1,
            'operation_number' => 'OP-100',
            'name' => 'Rough Surface Milling',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'status' => ProductionOrderOperation::STATUS_READY,
        ]);

        // 1. Operator Queue
        $queueRes = $this->withHeaders($this->headers())->getJson('/api/v1/production/mes/queue?work_center_id=' . $this->workCenter->id);
        $queueRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 2. Start Operation
        $startRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/mes/operations/' . $operation->id . '/start');
        $startRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 3. Pause Operation
        $pauseRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/mes/operations/' . $operation->id . '/pause', [
            'remarks' => 'Tool replacement required',
        ]);
        $pauseRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 4. Resume Operation
        $resumeRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/mes/operations/' . $operation->id . '/resume');
        $resumeRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 5. Complete Operation
        $compRes = $this->withHeaders($this->headers())->postJson('/api/v1/production/mes/operations/' . $operation->id . '/complete', [
            'quantity_produced' => 5,
        ]);
        $compRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_idempotency_key_prevents_duplicate_transactions(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-IDEM-01',
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
            'created_by' => $this->planner->id,
        ]);

        $operation = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 1,
            'operation_number' => 'OP-IDEM-01',
            'name' => 'Idempotency Op',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'status' => 'ready',
        ]);

        $idempotencyKey = 'unique-mes-key-' . uniqid();

        // First Request
        $res1 = $this->withHeaders($this->headers($idempotencyKey))
            ->postJson('/api/v1/production/orders/' . $order->id . '/progress', [
                'operation_id' => $operation->id,
                'quantity_produced' => 4,
            ]);

        $res1->assertStatus(200);

        // Immediate Duplicate Retry with Same Idempotency-Key
        $res2 = $this->withHeaders($this->headers($idempotencyKey))
            ->postJson('/api/v1/production/orders/' . $order->id . '/progress', [
                'operation_id' => $operation->id,
                'quantity_produced' => 4,
            ]);

        $res2->assertStatus(200);
        $this->assertEquals($res1->json(), $res2->json());
    }

    public function test_pagination_meta_and_limit_capping(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/production/orders?per_page=999');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        // Must be capped at ceiling of 100
        $this->assertLessThanOrEqual(100, (int) $response->json('meta.per_page'));
    }

    public function test_form_request_validation_error_contract_422(): void
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/production/orders', [
                // Missing required product_id, start_date, end_date, quantity_ordered
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ])
            ->assertJson([
                'success' => false,
                'message' => 'The given data was invalid.',
            ]);
    }
}
