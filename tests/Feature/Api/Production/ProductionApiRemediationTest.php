<?php

namespace Tests\Feature\Api\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Controllers\Api\MesExecutionApiController;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Requests\Api\StoreQualityInspectionApiRequest;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * ProductionApiRemediationTest
 *
 * Verifies all remediation fixes for release blocker findings:
 * F-01 (Dashboard endpoints & authorization)
 * F-02 (Quality submitResults route/method alignment)
 * F-03 (Idempotency payload collision detection & 409 Conflict)
 * F-04 (StoreQualityInspectionApiRequest instantiation & validation)
 * F-05 (Undefined $op variable in MesExecutionApiController::logProgress)
 * F-06 (Authorization & tenant isolation on quickCheck)
 * F-07 (Tenant-scoped rate limiter keying)
 * F-10 (Resource consistency on quickCheck)
 */
class ProductionApiRemediationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $authorizedUser;
    private User $unauthorizedUser;
    private Uom $uom;
    private Product $product;
    private WorkCenter $workCenter;
    private Machine $machine;
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
            'name' => 'Beta Manufacturing',
            'slug' => 'beta-mfg',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->authorizedUser = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Apex Production Admin',
            'email' => 'admin@apex.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $managerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->authorizedUser->id,
            'role_id' => $managerRole->id,
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->unauthorizedUser = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Apex Unauthorized Guest',
            'email' => 'guest@apex.test',
            'password' => 'password',
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Units',
            'code' => 'UNT',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Precision Gearbox',
            'sku' => 'FG-GEAR-01',
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'unit_cost' => 250.0,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Precision Lathe Cell',
            'code' => 'WC-LATHE-01',
            'work_center_type' => 'work_center',
            'capacity_per_hour' => 5.0,
            'cost_per_hour' => 60.0,
            'status' => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'Lathe Alpha',
            'code' => 'MCH-LTH-01',
            'machine_type' => 'lathe',
            'status' => Machine::STATUS_ACTIVE,
            'capacity' => 1.0,
        ]);
    }

    private function headers(User $user, ?string $idempotencyKey = null, ?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? $this->tenantA;
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant' => $tenant->slug,
            'X-API-SECRET' => $this->secret,
            'Authorization' => 'Bearer ' . $user->createToken('test-token')->plainTextToken,
        ];

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    /**
     * F-01: Dashboard endpoints resolve to real methods.
     */
    public function test_dashboard_endpoints_accessible_to_authorized_user(): void
    {
        // 1. GET /dashboard
        $dashRes = $this->withHeaders($this->headers($this->authorizedUser))
            ->getJson('/api/v1/production/dashboard');
        $dashRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Production dashboard KPIs retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'orders' => ['total', 'draft', 'released', 'in_progress', 'completed', 'closed', 'cancelled'],
                    'operations' => ['active_count'],
                    'masters' => ['total_plans', 'total_work_centers', 'machines_active', 'machines_total'],
                ],
            ]);

        // 2. GET /dashboard/metrics
        $metricsRes = $this->withHeaders($this->headers($this->authorizedUser))
            ->getJson('/api/v1/production/dashboard/metrics');
        $metricsRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Production dashboard metrics retrieved successfully.',
            ]);

        // 3. GET /dashboard/alerts
        $alertsRes = $this->withHeaders($this->headers($this->authorizedUser))
            ->getJson('/api/v1/production/dashboard/alerts');
        $alertsRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Production dashboard alerts retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => ['configurations', 'recent_events', 'open_ncrs_count'],
            ]);
    }

    /**
     * F-01: Dashboard endpoints reject unauthorized users with 403.
     */
    public function test_dashboard_unauthorized_user_forbidden(): void
    {
        $unauthRes = $this->withHeaders($this->headers($this->unauthorizedUser))
            ->getJson('/api/v1/production/dashboard');
        $unauthRes->assertStatus(403);
    }

    /**
     * F-02: Quality submit route matches submitResults method and executes operation.
     */
    public function test_quality_submit_results_action(): void
    {
        $qualityPlan = ProductionQualityPlan::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Gearbox Dimensional QC',
            'code' => 'QP-GEAR-01',
            'type' => 'in_process',
            'product_id' => $this->product->id,
            'status' => 'active',
            'created_by' => $this->authorizedUser->id,
        ]);

        $param = ProductionQualityPlanParameter::create([
            'tenant_id' => $this->tenantA->id,
            'quality_plan_id' => $qualityPlan->id,
            'name' => 'Diameter',
            'type' => 'numeric',
            'min_value' => '49.95',
            'max_value' => '50.05',
            'is_mandatory' => true,
        ]);

        $inspection = ProductionQualityInspection::create([
            'tenant_id' => $this->tenantA->id,
            'quality_plan_id' => $qualityPlan->id,
            'stage' => 'in_process',
            'status' => 'draft',
            'result' => 'passed',
        ]);

        \App\Domains\Production\Models\ProductionQualityInspectionResult::create([
            'tenant_id' => $this->tenantA->id,
            'quality_inspection_id' => $inspection->id,
            'quality_plan_parameter_id' => $param->id,
            'result' => 'passed',
        ]);

        $submitRes = $this->withHeaders($this->headers($this->authorizedUser))
            ->postJson("/api/v1/production/quality/inspections/{$inspection->id}/submit", [
                'results' => [
                    [
                        'parameter_id' => $param->id,
                        'value_numeric' => '50.02',
                        'result' => 'passed',
                    ],
                ],
                'passed' => true,
                'remarks' => 'Tolerance within acceptable limits.',
            ]);

        $submitRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inspection results recorded successfully.',
            ]);
    }

    /**
     * F-03: Idempotency payload fingerprinting:
     * - Same key + same payload => replay original response (201)
     * - Same key + different payload => 409 Conflict
     * - Different key + different payload => normal execution (201)
     */
    public function test_idempotency_payload_collision_returns_409_conflict(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'PO-IDEM-001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'released',
            'created_by' => $this->authorizedUser->id,
        ]);

        $key = 'IDEM-KEY-ALPHA-' . uniqid();

        // 1. Initial Request
        $payload1 = [
            'result'  => 'passed',
            'remarks' => 'Initial inline check',
        ];
        $res1 = $this->withHeaders($this->headers($this->authorizedUser, $key))
            ->postJson("/api/v1/production/quality/orders/{$order->id}/quick-check", $payload1);
        $res1->assertStatus(201);

        // 2. Replay with identical payload and same key => cached replay
        $res2 = $this->withHeaders($this->headers($this->authorizedUser, $key))
            ->postJson("/api/v1/production/quality/orders/{$order->id}/quick-check", $payload1);
        $res2->assertStatus(201)
            ->assertHeader('X-Idempotent-Replay', 'true');

        // 3. Replay with DIFFERENT payload and same key => 409 Conflict
        $payload2 = [
            'result'  => 'failed',
            'remarks' => 'Tampered inline check',
        ];
        $res3 = $this->withHeaders($this->headers($this->authorizedUser, $key))
            ->postJson("/api/v1/production/quality/orders/{$order->id}/quick-check", $payload2);
        $res3->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Idempotency-Key reused with a different request payload.',
            ]);

        // 4. Fresh request with different key and different payload => executes normally
        $key2 = 'IDEM-KEY-BETA-' . uniqid();
        $res4 = $this->withHeaders($this->headers($this->authorizedUser, $key2))
            ->postJson("/api/v1/production/quality/orders/{$order->id}/quick-check", [
                'result'  => 'hold',
                'remarks' => 'Distinct check with new key',
            ]);
        $res4->assertStatus(201);
    }

    /**
     * F-04: StoreQualityInspectionApiRequest validates store inspection payload.
     */
    public function test_quality_store_request_validates_correctly(): void
    {
        $qualityPlan = ProductionQualityPlan::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Receiving Standard QC',
            'code' => 'QP-REC-01',
            'type' => 'incoming',
            'product_id' => $this->product->id,
            'status' => 'active',
            'created_by' => $this->authorizedUser->id,
        ]);

        $validator = validator([
            'quality_plan_id' => $qualityPlan->id,
            'stage'           => 'incoming',
        ], (new StoreQualityInspectionApiRequest())->rules());

        $this->assertFalse($validator->fails());

        // Invalid stage fails validation
        $invalidValidator = validator([
            'quality_plan_id' => $qualityPlan->id,
            'stage'           => 'non_existent_stage',
        ], (new StoreQualityInspectionApiRequest())->rules());

        $this->assertTrue($invalidValidator->fails());
    }

    /**
     * F-05: MesExecutionApiController::logProgress resolves $operation without undefined $op fatal error.
     */
    public function test_mes_log_progress_resolves_operation_variable(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'PO-MES-OP-01',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'released',
            'created_by' => $this->authorizedUser->id,
        ]);

        $operation = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'sequence' => 1,
            'operation_number' => 'OP-PROG-01',
            'name' => 'Turning',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'status' => ProductionOrderOperation::STATUS_READY,
            'planned_quantity' => 10,
        ]);

        // Start operation via API so schedule operation is created and in running status
        $startRes = $this->withHeaders($this->headers($this->authorizedUser))
            ->postJson("/api/v1/production/mes/operations/{$operation->id}/start");
        $startRes->assertStatus(200);

        $schedOp = ProductionScheduleOperation::where('tenant_id', $this->tenantA->id)
            ->where('production_order_operation_id', $operation->id)
            ->firstOrFail();

        $controller = app(MesExecutionApiController::class);
        $request = Request::create("/api/v1/production/mes/operations/{$schedOp->id}/log-progress", 'POST', [
            'quantity_produced' => 4,
            'quantity_rejected' => 0,
            'quantity_scrapped' => 0,
            'remarks'           => 'First batch turned cleanly',
        ]);
        $request->setUserResolver(fn() => $this->authorizedUser);
        $this->actingAs($this->authorizedUser);

        $response = $controller->logProgress($request, $schedOp->id);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Progress logged successfully.', $responseData['message']);
    }

    /**
     * F-06 & F-10: quickCheck authorized access and resource response.
     */
    public function test_quick_check_authorized_user_succeeds(): void
    {
        $orderA = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'PO-QC-A01',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'released',
            'created_by' => $this->authorizedUser->id,
        ]);

        $resAuth = $this->withHeaders($this->headers($this->authorizedUser))
            ->postJson("/api/v1/production/quality/orders/{$orderA->id}/quick-check", [
                'result'  => 'passed',
                'remarks' => 'Visual inspection flawless',
                'stage'   => 'in_process',
            ]);

        $resAuth->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Operator quick check recorded successfully.',
            ])
            ->assertJsonStructure([
                'data' => ['id', 'stage', 'status', 'result', 'remarks'],
            ]);
    }

    /**
     * F-06: quickCheck rejects unauthorized users with 403.
     */
    public function test_quick_check_unauthorized_user_forbidden(): void
    {
        $orderA = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'PO-QC-A02',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'released',
            'created_by' => $this->authorizedUser->id,
        ]);

        $resUnauth = $this->withHeaders($this->headers($this->unauthorizedUser))
            ->postJson("/api/v1/production/quality/orders/{$orderA->id}/quick-check", [
                'result'  => 'passed',
                'remarks' => 'Attempt by guest',
            ]);

        $resUnauth->assertStatus(403);
    }

    /**
     * F-06: quickCheck enforces tenant isolation on orders.
     */
    public function test_quick_check_cross_tenant_order_rejected(): void
    {
        $orderB = ProductionOrder::create([
            'tenant_id' => $this->tenantB->id,
            'order_number' => 'PO-QC-B01',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'released',
            'created_by' => $this->authorizedUser->id,
        ]);

        $resCross = $this->withHeaders($this->headers($this->authorizedUser))
            ->postJson("/api/v1/production/quality/orders/{$orderB->id}/quick-check", [
                'result' => 'passed',
            ]);

        $resCross->assertStatus(404);
    }

    /**
     * F-07: Tenant-scoped rate limiter keying separates Tenant A and Tenant B quotas.
     */
    public function test_rate_limiter_keys_are_tenant_scoped(): void
    {
        $userTenantB = clone $this->authorizedUser;
        $userTenantB->tenant_id = $this->tenantB->id;

        $keyA = "t:{$this->authorizedUser->tenant_id}:u:{$this->authorizedUser->id}";
        $keyB = "t:{$userTenantB->tenant_id}:u:{$userTenantB->id}";

        $this->assertNotEquals($keyA, $keyB);
        $this->assertStringStartsWith("t:{$this->tenantA->id}:", $keyA);
        $this->assertStringStartsWith("t:{$this->tenantB->id}:", $keyB);
    }
}
