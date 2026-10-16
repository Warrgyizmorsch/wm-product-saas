<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionPlanOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionOrderCompletionValidator;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SubcontractMaterialBalanceService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use InvalidArgumentException;
use Tests\TestCase;

class RoutingDrivenSubcontractingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $tenantId = 1;
    private Uom $uom;
    private WorkCenter $workCenter;
    private Vendor $vendor1;
    private Vendor $vendor2;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create([
            'id' => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->seed(RbacSeeder::class);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'admin',
        ]);

        foreach (['super_admin', 'production_manager'] as $roleSlug) {
            $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->first();
            if ($role) {
                UserRole::create([
                    'tenant_id' => $this->tenantId,
                    'user_id' => $this->user->id,
                    'role_id' => $role->id,
                ]);
            }
        }

        $this->actingAs($this->user);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Piece',
            'code' => 'PCS',
            'status' => 'active',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Main Machining Center',
            'code' => 'WC-MAIN',
            'status' => 'active',
        ]);

        $this->vendor1 = Vendor::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Apex Coatings Ltd',
            'code' => 'VEND-APEX',
            'status' => 'active',
        ]);

        $this->vendor2 = Vendor::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Zenith Heat Treatment',
            'code' => 'VEND-ZENITH',
            'status' => 'active',
        ]);

        session(['tenant_id' => $this->tenantId, 'errors' => new ViewErrorBag()]);
        view()->share('errors', new ViewErrorBag());
    }

    private function createProductWithBomAndRouting(string $sku, array $routingOpsData): array
    {
        $fg = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => "Product {$sku}",
            'sku' => $sku,
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
        ]);

        $raw = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => "Raw Material {$sku}",
            'sku' => "RAW-{$sku}",
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $fg->id,
            'bom_number' => "BOM-{$sku}",
            'bom_name' => "BOM for {$sku}",
            'version' => '1.0',
            'status' => 'approved',
            'bom_type' => 'manufacturing',
            'effective_date' => now()->toDateString(),
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantId,
            'bom_id' => $bom->id,
            'material_id' => $raw->id,
            'quantity' => 2.0,
            'uom_id' => $this->uom->id,
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $fg->id,
            'routing_number' => "RTG-{$sku}",
            'name' => "Routing for {$sku}",
            'version' => '1.0',
            'status' => 'active',
        ]);

        foreach ($routingOpsData as $idx => $opData) {
            RoutingOperation::create(array_merge([
                'tenant_id' => $this->tenantId,
                'routing_id' => $routing->id,
                'operation_number' => 'OP-' . str_pad((string) (($idx + 1) * 10), 3, '0', STR_PAD_LEFT),
                'name' => $opData['name'] ?? "Operation {$idx}",
                'sequence' => $idx + 1,
                'work_center_id' => $this->workCenter->id,
                'processing_time_minutes' => 30,
                'setup_time_minutes' => 10,
                'is_external' => false,
            ], $opData));
        }

        return [$fg, $bom, $routing, $raw];
    }

    /**
     * 1. Normal create view does not render the manual Production Model dropdown.
     */
    public function test_create_order_view_does_not_contain_production_model_dropdown(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.create'));

        $response->assertStatus(200);
        $response->assertDontSee('id="production_model_select"', false);
        $response->assertDontSee('name="production_model"', false);
    }

    /**
     * 2. Internal-only routing creates an order without requiring Production Model selection.
     */
    public function test_internal_only_routing_creates_pure_manufacturing_order(): void
    {
        [$fg, $bom, $routing] = $this->createProductWithBomAndRouting('INT-001', [
            ['name' => 'Internal Cutting', 'is_external' => false],
            ['name' => 'Internal Assembly', 'is_external' => false],
        ]);

        /** @var ProductionOrderService $service */
        $service = app(ProductionOrderService::class);
        $order = $service->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 50,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            // Intentionally no production_model provided!
        ], $this->user->id);

        $this->assertEquals(ProductionOrder::MODEL_PURE_MANUFACTURING, $order->production_model);
        $this->assertEquals(ProductionOrder::MODEL_PURE_MANUFACTURING, $order->effective_production_model);
        $this->assertFalse($order->operations->contains('is_external', true));
        $this->assertCount(2, $order->operations);

        // Subcontract vertical tab must NOT be visible for internal orders
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $order->id));
        $response->assertStatus(200);
        $response->assertDontSee('id="vtab-subcontract"', false);
    }

    /**
     * 3. Fully external routing uses configured vendor and operation settings.
     */
    public function test_fully_external_routing_creates_subcontract_order_with_operation_settings(): void
    {
        [$fg, $bom, $routing] = $this->createProductWithBomAndRouting('EXT-001', [
            [
                'name' => 'External Surface Coating',
                'is_external' => true,
                'vendor_id' => $this->vendor1->id,
                'material_supply_type' => 'company_supplied',
                'subcontract_input_type' => 'bom_raw_materials',
                'subcontract_lead_time_days' => 7,
                'subcontract_cost_per_unit' => 45.50,
                'dispatch_buffer_days' => 2,
                'return_buffer_days' => 1,
            ],
        ]);

        /** @var ProductionOrderService $service */
        $service = app(ProductionOrderService::class);
        $order = $service->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 100,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
        ], $this->user->id);

        $this->assertEquals(ProductionOrder::MODEL_SUBCONTRACT_COMPANY_MATERIAL, $order->production_model);
        $this->assertEquals(ProductionOrder::MODEL_SUBCONTRACT_COMPANY_MATERIAL, $order->effective_production_model);
        $this->assertTrue($order->operations->contains('is_external', true));
        $this->assertTrue($order->isSubcontractCompanyMaterial());

        $op = $order->operations->first();
        $this->assertTrue((bool) $op->is_external);
        $this->assertEquals($this->vendor1->id, $op->vendor_id);
        $this->assertEquals('company_supplied', $op->material_supply_type);
        $this->assertEquals('bom_raw_materials', $op->subcontract_input_type);
        $this->assertEquals(7, $op->subcontract_lead_time_days);
        $this->assertEquals(45.50, (float) $op->subcontract_cost_per_unit);
        $this->assertEquals(2, $op->dispatch_buffer_days);
        $this->assertEquals(1, $op->return_buffer_days);

        // Subcontract tab must be rendered
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $order->id));
        $response->assertStatus(200);
        $response->assertSee('id="vtab-subcontract"', false);
    }

    /**
     * 4. Mixed routing works without manually selecting a model (Hybrid).
     */
    public function test_mixed_internal_external_routing_derives_hybrid_model(): void
    {
        [$fg, $bom, $routing] = $this->createProductWithBomAndRouting('HYB-001', [
            ['name' => 'Internal Fabrication', 'is_external' => false],
            [
                'name' => 'External Anodizing',
                'is_external' => true,
                'vendor_id' => $this->vendor1->id,
                'material_supply_type' => 'company_supplied',
                'subcontract_lead_time_days' => 5,
            ],
            ['name' => 'Final Internal Inspection', 'is_external' => false],
        ]);

        /** @var ProductionOrderService $service */
        $service = app(ProductionOrderService::class);
        $order = $service->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 30,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ], $this->user->id);

        $this->assertEquals(ProductionOrder::MODEL_HYBRID, $order->production_model);
        $this->assertEquals(ProductionOrder::MODEL_HYBRID, $order->effective_production_model);
        $this->assertTrue($order->isHybrid());
        $this->assertTrue($order->operations->contains('is_external', true));

        // Subcontract vertical tab must be visible because external operation exists
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.show', $order->id));
        $response->assertStatus(200);
        $response->assertSee('id="vtab-subcontract"', false);
    }

    /**
     * 5. Multiple subcontract vendors are preserved correctly across operations.
     */
    public function test_multiple_subcontract_vendors_preserved_across_operations(): void
    {
        [$fg, $bom, $routing] = $this->createProductWithBomAndRouting('MULTI-VEND-001', [
            [
                'name' => 'Heat Treatment',
                'is_external' => true,
                'vendor_id' => $this->vendor2->id,
                'material_supply_type' => 'company_supplied',
            ],
            [
                'name' => 'Surface Plating',
                'is_external' => true,
                'vendor_id' => $this->vendor1->id,
                'material_supply_type' => 'company_supplied',
            ],
        ]);

        /** @var ProductionOrderService $service */
        $service = app(ProductionOrderService::class);
        $order = $service->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 25,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ], $this->user->id);

        $ops = $order->operations()->orderBy('sequence')->get();
        $this->assertEquals($this->vendor2->id, $ops[0]->vendor_id);
        $this->assertEquals($this->vendor1->id, $ops[1]->vendor_id);
    }

    /**
     * 6. Outstanding company-supplied material reconciliation blocks completion even if order model is pure_manufacturing.
     */
    public function test_completion_validation_blocks_outstanding_company_material_even_if_model_is_pure(): void
    {
        [$fg, $bom, $routing] = $this->createProductWithBomAndRouting('COMPL-001', [
            [
                'name' => 'Subcontract Polishing',
                'is_external' => true,
                'vendor_id' => $this->vendor1->id,
                'material_supply_type' => 'company_supplied',
            ],
        ]);

        /** @var ProductionOrderService $service */
        $service = app(ProductionOrderService::class);
        $order = $service->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ], $this->user->id);

        // Artificially simulate a legacy/stale order where production_model is pure_manufacturing
        $order->production_model = ProductionOrder::MODEL_PURE_MANUFACTURING;
        $order->status = ProductionOrder::STATUS_IN_PROGRESS;
        $order->save();

        $op = $order->operations->first();
        $op->status = ProductionOrderOperation::STATUS_COMPLETED;
        $op->quantity_produced = 10;
        $op->save();

        // Mock material balance service to simulate 5 units unreturned
        $this->mock(SubcontractMaterialBalanceService::class, function ($mock) {
            $mock->shouldReceive('getMaterialBalance')
                ->andReturn([
                    'dispatched' => 10.0,
                    'consumed' => 5.0,
                    'returned' => 0.0,
                    'remaining' => 5.0, // Outstanding balance!
                ]);
        });

        $validator = app(ProductionOrderCompletionValidator::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Company material remains at subcontractor warehouse');

        $validator->validateCompletion($order);
    }

    /**
     * 7. Vendor-supplied material is not blocked on company material reconciliation.
     */
    public function test_vendor_supplied_external_operation_does_not_block_on_company_material_balance(): void
    {
        [$fg, $bom, $routing] = $this->createProductWithBomAndRouting('VEND-SUPP-001', [
            [
                'name' => 'Turnkey External Assembly',
                'is_external' => true,
                'vendor_id' => $this->vendor1->id,
                'material_supply_type' => 'vendor_supplied',
            ],
        ]);

        /** @var ProductionOrderService $service */
        $service = app(ProductionOrderService::class);
        $order = $service->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ], $this->user->id);

        $this->assertEquals(ProductionOrder::MODEL_SUBCONTRACT_COMPLETE, $order->production_model);

        $op = $order->operations->first();
        $op->status = ProductionOrderOperation::STATUS_COMPLETED;
        $op->quantity_produced = 10;
        $op->save();

        $validator = app(ProductionOrderCompletionValidator::class);

        // Validation should not throw any exception for company material
        $validator->validateCompletion($order);
        $this->assertTrue(true);
    }

    /**
     * 8. Production Plan release snapshots routing settings consistently.
     */
    public function test_production_plan_release_snapshots_subcontracting_settings_consistently(): void
    {
        [$fg, $bom, $routing] = $this->createProductWithBomAndRouting('PLAN-REL-001', [
            ['name' => 'Plan Step 1 Internal', 'is_external' => false],
            [
                'name' => 'Plan Step 2 External',
                'is_external' => true,
                'vendor_id' => $this->vendor1->id,
                'material_supply_type' => 'company_supplied',
                'subcontract_lead_time_days' => 4,
                'subcontract_cost_per_unit' => 20.0,
            ],
        ]);

        $plan = ProductionPlan::create([
            'tenant_id' => $this->tenantId,
            'plan_number' => 'PLAN-001',
            'name' => 'Plan for ' . $fg->sku,
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_planned' => 40,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'status' => ProductionPlan::STATUS_APPROVED,
        ]);

        $rOps = $routing->operations;
        ProductionPlanOperation::create([
            'tenant_id' => $this->tenantId,
            'production_plan_id' => $plan->id,
            'routing_operation_id' => $rOps[0]->id,
            'sequence' => 1,
            'operation_number' => 'OP-010',
            'name' => 'Plan Step 1 Internal',
            'work_center_id' => $this->workCenter->id,
            'is_external' => false,
        ]);

        ProductionPlanOperation::create([
            'tenant_id' => $this->tenantId,
            'production_plan_id' => $plan->id,
            'routing_operation_id' => $rOps[1]->id,
            'sequence' => 2,
            'operation_number' => 'OP-020',
            'name' => 'Plan Step 2 External',
            'work_center_id' => $this->workCenter->id,
            'is_external' => true,
            'vendor_id' => $this->vendor1->id,
        ]);

        /** @var ProductionOrderService $service */
        $service = app(ProductionOrderService::class);
        $order = $service->createFromPlan($plan->id, $this->user->id);

        $this->assertEquals(ProductionOrder::MODEL_HYBRID, $order->production_model);
        $this->assertEquals(ProductionOrder::MODEL_HYBRID, $order->effective_production_model);
        $this->assertTrue($order->operations->contains('is_external', true));
    }

    /**
     * 9. Backward compatibility: Caller can still pass explicit production_model if needed by legacy integrations.
     */
    public function test_backward_compatibility_preserves_explicit_production_model(): void
    {
        [$fg, $bom, $routing] = $this->createProductWithBomAndRouting('LEGACY-001', [
            [
                'name' => 'Complete External Turnkey',
                'is_external' => true,
                'vendor_id' => $this->vendor1->id,
                'material_supply_type' => 'vendor_supplied',
            ],
        ]);

        /** @var ProductionOrderService $service */
        $service = app(ProductionOrderService::class);
        $order = $service->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 20,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'production_model' => 'complete_subcontracting',
        ], $this->user->id);

        $this->assertEquals('complete_subcontracting', $order->production_model);
    }
}
