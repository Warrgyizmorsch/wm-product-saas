<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\ProductionEcoService;
use App\Domains\Production\Services\RoutingService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BomAndRoutingLifecycleDatesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private Product $productA;
    private Product $productB;
    private Product $rawMaterialA;
    private WorkCenter $workCenterA;
    private Machine $machineA;
    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Manager Tenant A',
            'email' => 'manager.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_manager',
        ]);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Manager Tenant B',
            'email' => 'manager.b@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_manager',
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Piece',
            'code' => 'Pcs',
        ]);

        $this->productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Finished Widget A',
            'sku' => 'FG-WIDGET-A',
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $this->uom->id,
        ]);

        $this->productB = Product::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Finished Widget B',
            'sku' => 'FG-WIDGET-B',
            'type' => 'finished_good',
            'status' => 'active',
            'uom_id' => $this->uom->id,
        ]);

        $this->rawMaterialA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Raw Steel',
            'sku' => 'RM-STEEL-01',
            'type' => 'raw_material',
            'status' => 'active',
            'uom_id' => $this->uom->id,
        ]);

        $this->workCenterA = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cutting Center',
            'code' => 'WC-CUT',
            'status' => 'active',
        ]);

        $this->machineA = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Laser Cutter',
            'code' => 'MC-LASER',
            'work_center_id' => $this->workCenterA->id,
            'status' => 'active',
        ]);
    }

    public function test_bom_approval_with_new_version_starting_today_retires_previous_version_without_gap(): void
    {
        $today = Carbon::today()->toDateString();

        // 1. Existing active BOM v1
        $bomV1 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-WIDGET-001',
            'bom_name' => 'Widget BOM v1',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $today,
            'expiry_date' => null,
        ]);

        // 2. Draft BOM v2 starting today
        $bomV2 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-WIDGET-002',
            'bom_name' => 'Widget BOM v2',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'pending_approval',
            'version' => '2.0.0',
            'effective_date' => $today,
            'expiry_date' => null,
        ]);

        // 3. Approve BOM v2
        $bomService = app(ProductionBomService::class);
        $bomService->approve($bomV2->id, $this->adminA->id);

        $bomV1->refresh();
        $bomV2->refresh();

        // Previous active version must be expired on the new version's start date
        $this->assertEquals('inactive', $bomV1->status);
        $this->assertEquals($today, $bomV1->expiry_date?->toDateString());

        // New version is approved and starts today
        $this->assertEquals('approved', $bomV2->status);
        $this->assertEquals($today, $bomV2->effective_date?->toDateString());
        $this->assertNull($bomV2->expiry_date);
    }

    public function test_bom_approval_with_future_dated_version_sets_previous_expiry_to_future_start_date(): void
    {
        $today = Carbon::today()->toDateString();
        $futureDate = Carbon::today()->addDays(15)->toDateString();

        $bomV1 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-WIDGET-001',
            'bom_name' => 'Widget BOM v1',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $today,
            'expiry_date' => null,
        ]);

        $bomV2 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-WIDGET-002',
            'bom_name' => 'Widget BOM v2 (Future)',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'pending_approval',
            'version' => '2.0.0',
            'effective_date' => $futureDate,
            'expiry_date' => null,
        ]);

        $bomService = app(ProductionBomService::class);
        $bomService->approve($bomV2->id, $this->adminA->id);

        $bomV1->refresh();
        $bomV2->refresh();

        // The previous active version ends on the new version's future start date - no gap
        // It stays active until that future start date arrives!
        $this->assertEquals('approved', $bomV1->status);
        $this->assertTrue($bomV1->isCurrentlyActive());
        $this->assertEquals($futureDate, $bomV1->expiry_date?->toDateString());

        // The new version preserves its future configured start date and is scheduled
        $this->assertEquals('scheduled', $bomV2->status);
        $this->assertTrue($bomV2->isScheduled());
        $this->assertFalse($bomV2->isCurrentlyActive());
        $this->assertEquals($futureDate, $bomV2->effective_date?->toDateString());
    }

    public function test_bom_creation_rejects_past_start_dates(): void
    {
        $pastDate = Carbon::today()->subDays(2)->toDateString();

        $payload = [
            'bom_number' => 'BOM-TEST-PAST',
            'bom_name' => 'Past BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => $pastDate,
            'items' => [
                [
                    'material_id' => $this->rawMaterialA->id,
                    'quantity' => 1.0,
                    'uom_id' => $this->uom->id,
                ]
            ]
        ];

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.store'), $payload);

        $response->assertSessionHasErrors('effective_date');
    }

    public function test_bom_creation_rejects_date_earlier_than_existing_active_version_start_date(): void
    {
        $activeDate = Carbon::today()->addDays(10)->toDateString();
        $earlierDate = Carbon::today()->addDays(5)->toDateString();

        // Active version starts in 10 days
        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-ACTIVE-V1',
            'bom_name' => 'Active BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $activeDate,
        ]);

        // Attempt to create new BOM starting earlier than active version's start date
        $payload = [
            'bom_number' => 'BOM-FAIL-EARLIER',
            'bom_name' => 'Earlier BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '2.0.0',
            'effective_date' => $earlierDate,
            'items' => [
                [
                    'material_id' => $this->rawMaterialA->id,
                    'quantity' => 1.0,
                    'uom_id' => $this->uom->id,
                ]
            ]
        ];

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.boms.store'), $payload);

        $response->assertSessionHasErrors('effective_date');
    }

    public function test_bom_cancellation_sets_expiry_date_to_today(): void
    {
        $today = Carbon::today()->toDateString();

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-CANCEL-TEST',
            'bom_name' => 'Cancel Test BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $today,
            'expiry_date' => null,
        ]);

        $bomService = app(ProductionBomService::class);
        $bomService->cancel($bom->id, $this->adminA->id, 'Discontinued product line.');

        $bom->refresh();
        $this->assertEquals('cancelled', $bom->status);
        $this->assertEquals($today, $bom->expiry_date?->toDateString());
    }

    public function test_routing_approval_with_new_version_starting_today_retires_previous_version_without_gap(): void
    {
        $today = Carbon::today()->toDateString();

        $activeBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-WIDGET-ACTIVE',
            'bom_name' => 'Widget Active BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $today,
        ]);

        $rtgV1 = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-WIDGET-001',
            'name' => 'Widget Routing v1',
            'product_id' => $this->productA->id,
            'bom_id' => $activeBom->id,
            'version' => '1.0.0',
            'revision' => 0,
            'status' => Routing::STATUS_ACTIVE,
            'effective_from' => $today,
            'effective_to' => null,
        ]);

        $rtgV2 = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-WIDGET-002',
            'name' => 'Widget Routing v2',
            'product_id' => $this->productA->id,
            'bom_id' => $activeBom->id,
            'version' => '2.0.0',
            'revision' => 1,
            'status' => Routing::STATUS_PENDING_APPROVAL,
            'effective_from' => $today,
            'effective_to' => null,
        ]);

        $rtgV2->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Laser Cut',
            'work_center_id' => $this->workCenterA->id,
        ]);

        $routingService = app(RoutingService::class);
        $routingService->approve($rtgV2->id, $this->adminA->id);

        $rtgV1->refresh();
        $rtgV2->refresh();

        // Previous active routing status is historical and effective_to is new version's start date
        $this->assertEquals(Routing::STATUS_HISTORICAL, $rtgV1->status);
        $this->assertEquals($today, $rtgV1->effective_to?->toDateString());

        // New version is active with configured start date
        $this->assertEquals(Routing::STATUS_ACTIVE, $rtgV2->status);
        $this->assertEquals($today, $rtgV2->effective_from?->toDateString());
        $this->assertNull($rtgV2->effective_to);
    }

    public function test_routing_approval_with_future_dated_version_sets_previous_effective_to_to_future_start_date(): void
    {
        $today = Carbon::today()->toDateString();
        $futureDate = Carbon::today()->addDays(20)->toDateString();

        $activeBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-WIDGET-ACTIVE-2',
            'bom_name' => 'Widget Active BOM 2',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $today,
        ]);

        $rtgV1 = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-WIDGET-001',
            'name' => 'Widget Routing v1',
            'product_id' => $this->productA->id,
            'bom_id' => $activeBom->id,
            'version' => '1.0.0',
            'revision' => 0,
            'status' => Routing::STATUS_ACTIVE,
            'effective_from' => $today,
            'effective_to' => null,
        ]);

        $rtgV2 = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-WIDGET-002',
            'name' => 'Widget Routing v2 (Future)',
            'product_id' => $this->productA->id,
            'bom_id' => $activeBom->id,
            'version' => '2.0.0',
            'revision' => 1,
            'status' => Routing::STATUS_PENDING_APPROVAL,
            'effective_from' => $futureDate,
            'effective_to' => null,
        ]);

        $rtgV2->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Laser Cut',
            'work_center_id' => $this->workCenterA->id,
        ]);

        $routingService = app(RoutingService::class);
        $routingService->approve($rtgV2->id, $this->adminA->id);

        $rtgV1->refresh();
        $rtgV2->refresh();

        // Previous active routing ends on the future start date - no gap
        // It stays active until that future start date arrives!
        $this->assertEquals(Routing::STATUS_ACTIVE, $rtgV1->status);
        $this->assertTrue($rtgV1->isCurrentlyActive());
        $this->assertEquals($futureDate, $rtgV1->effective_to?->toDateString());

        // New version is scheduled and preserves its future start date
        $this->assertEquals(Routing::STATUS_SCHEDULED, $rtgV2->status);
        $this->assertTrue($rtgV2->isScheduled());
        $this->assertFalse($rtgV2->isCurrentlyActive());
        $this->assertEquals($futureDate, $rtgV2->effective_from?->toDateString());
    }

    public function test_routing_creation_rejects_past_start_dates(): void
    {
        $pastDate = Carbon::today()->subDays(3)->toDateString();

        $payload = [
            'name' => 'Past Routing',
            'product_id' => $this->productA->id,
            'version' => '1.0.0',
            'effective_from' => $pastDate,
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Operation 1',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                ]
            ]
        ];

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.store'), $payload);

        $response->assertSessionHasErrors('effective_from');
    }

    public function test_routing_creation_rejects_date_earlier_than_existing_active_version_start_date(): void
    {
        $activeDate = Carbon::today()->addDays(8)->toDateString();
        $earlierDate = Carbon::today()->addDays(4)->toDateString();

        Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-ACT-001',
            'name' => 'Active Future Routing',
            'product_id' => $this->productA->id,
            'version' => '1.0.0',
            'status' => Routing::STATUS_ACTIVE,
            'effective_from' => $activeDate,
        ]);

        $payload = [
            'name' => 'Earlier Conflict Routing',
            'product_id' => $this->productA->id,
            'version' => '2.0.0',
            'effective_from' => $earlierDate,
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Operation 1',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                ]
            ]
        ];

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.store'), $payload);

        $response->assertSessionHasErrors('effective_from');
    }

    public function test_routing_cancellation_sets_effective_to_to_today(): void
    {
        $today = Carbon::today()->toDateString();

        $routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-CANCEL-001',
            'name' => 'Routing To Cancel',
            'product_id' => $this->productA->id,
            'version' => '1.0.0',
            'status' => Routing::STATUS_ACTIVE,
            'effective_from' => $today,
            'effective_to' => null,
        ]);

        $routingService = app(RoutingService::class);
        $routingService->cancel($routing->id, $this->adminA->id, 'Discontinued process.');

        $routing->refresh();
        $this->assertEquals(Routing::STATUS_CANCELLED, $routing->status);
        $this->assertEquals($today, $routing->effective_to?->toDateString());
    }

    public function test_tenant_and_product_isolation_for_lifecycle_dates(): void
    {
        $today = Carbon::today()->toDateString();

        // Tenant A: BOM for Product A
        $bomA = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-TENANT-A',
            'bom_name' => 'Tenant A BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $today,
            'expiry_date' => null,
        ]);

        // Tenant B: BOM for Product B
        $bomB = ProductionBom::create([
            'tenant_id' => $this->tenantB->id,
            'bom_number' => 'BOM-TENANT-B',
            'bom_name' => 'Tenant B BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productB->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $today,
            'expiry_date' => null,
        ]);

        // New draft BOM on Tenant A for Product A
        $bomA2 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-TENANT-A-V2',
            'bom_name' => 'Tenant A BOM v2',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'pending_approval',
            'version' => '2.0.0',
            'effective_date' => $today,
            'expiry_date' => null,
        ]);

        // Approve v2 on Tenant A
        $bomService = app(ProductionBomService::class);
        $bomService->approve($bomA2->id, $this->adminA->id);

        $bomA->refresh();
        $bomB->refresh();
        $bomA2->refresh();

        // Tenant A's v1 is retired
        $this->assertEquals('inactive', $bomA->status);
        $this->assertEquals($today, $bomA->expiry_date?->toDateString());

        // Tenant B's BOM is untouched and still approved with no expiry date
        $this->assertEquals('approved', $bomB->status);
        $this->assertNull($bomB->expiry_date);
    }

    public function test_eco_release_updates_lifecycle_dates_and_status(): void
    {
        $today = Carbon::today()->toDateString();
        $ecoEffectiveDate = Carbon::today()->addDays(7)->toDateString();

        $currentBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-ECO-CUR',
            'bom_name' => 'Current ECO BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'revision' => 0,
            'effective_date' => $today,
            'expiry_date' => null,
        ]);

        $proposedBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-ECO-PROP',
            'bom_name' => 'Proposed ECO BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'draft',
            'version' => '1.1.0',
            'revision' => 1,
            'effective_date' => $ecoEffectiveDate,
            'expiry_date' => null,
        ]);

        $currentRouting = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-ECO-CUR',
            'name' => 'Current ECO Routing',
            'product_id' => $this->productA->id,
            'version' => '1.0.0',
            'revision' => 0,
            'status' => Routing::STATUS_ACTIVE,
            'effective_from' => $today,
            'effective_to' => null,
        ]);

        $proposedRouting = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-ECO-PROP',
            'name' => 'Proposed ECO Routing',
            'product_id' => $this->productA->id,
            'version' => '1.1.0',
            'revision' => 1,
            'status' => Routing::STATUS_DRAFT,
            'effective_from' => $ecoEffectiveDate,
            'effective_to' => null,
        ]);

        $eco = ProductionEco::create([
            'tenant_id' => $this->tenantA->id,
            'eco_number' => 'ECO-2026-TEST',
            'title' => 'Design optimization',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM_AND_ROUTING,
            'product_id' => $this->productA->id,
            'current_bom_id' => $currentBom->id,
            'proposed_bom_id' => $proposedBom->id,
            'current_bom_revision' => 0,
            'proposed_bom_revision' => 1,
            'current_routing_id' => $currentRouting->id,
            'proposed_routing_id' => $proposedRouting->id,
            'current_routing_revision' => 0,
            'proposed_routing_revision' => 1,
            'effective_date' => $ecoEffectiveDate,
            'status' => ProductionEco::STATUS_APPROVED,
            'created_by' => $this->adminA->id,
        ]);

        $ecoService = app(ProductionEcoService::class);
        $ecoService->release($eco, $this->adminA->id);

        $currentBom->refresh();
        $proposedBom->refresh();
        $currentRouting->refresh();
        $proposedRouting->refresh();
        $eco->refresh();

        $this->assertEquals(ProductionEco::STATUS_RELEASED, $eco->status);

        // Current BOM expired on ECO effective date
        $this->assertEquals('inactive', $currentBom->status);
        $this->assertEquals($ecoEffectiveDate, $currentBom->expiry_date?->toDateString());

        // Proposed BOM approved and effective on ECO effective date
        $this->assertEquals('approved', $proposedBom->status);
        $this->assertEquals($ecoEffectiveDate, $proposedBom->effective_date?->toDateString());
        $this->assertNull($proposedBom->expiry_date);

        // Current Routing archived on ECO effective date
        $this->assertEquals(Routing::STATUS_HISTORICAL, $currentRouting->status);
        $this->assertEquals($ecoEffectiveDate, $currentRouting->effective_to?->toDateString());

        // Proposed Routing active on ECO effective date
        $this->assertEquals(Routing::STATUS_ACTIVE, $proposedRouting->status);
        $this->assertEquals($ecoEffectiveDate, $proposedRouting->effective_from?->toDateString());
        $this->assertNull($proposedRouting->effective_to);
    }

    public function test_bom_approval_when_planned_start_date_is_past_adjusts_effective_date_to_today_to_prevent_backdating(): void
    {
        $today = Carbon::today()->toDateString();
        $pastDate = Carbon::today()->subDays(5)->toDateString();

        $bomV1 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-PAST-001',
            'bom_name' => 'Widget BOM v1',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => Carbon::today()->subDays(10)->toDateString(),
            'expiry_date' => null,
        ]);

        // Draft was created in past with planned start date in past
        $bomV2 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-PAST-002',
            'bom_name' => 'Widget BOM v2',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'pending_approval',
            'version' => '2.0.0',
            'effective_date' => $pastDate,
            'expiry_date' => null,
        ]);

        $bomService = app(ProductionBomService::class);
        $bomService->approve($bomV2->id, $this->adminA->id);

        $bomV1->refresh();
        $bomV2->refresh();

        // Effective date automatically adjusts to today (approval date) to prevent back-dating
        $this->assertEquals('approved', $bomV2->status);
        $this->assertEquals($today, $bomV2->effective_date?->toDateString());
        $this->assertStringContainsString("Approval was granted on {$today} after the planned date ({$pastDate})", $bomV2->notes);

        // Previous version expires on today (not back-dated to pastDate)
        $this->assertEquals('inactive', $bomV1->status);
        $this->assertEquals($today, $bomV1->expiry_date?->toDateString());
    }

    public function test_routing_approval_when_planned_start_date_is_past_adjusts_effective_from_to_today_to_prevent_backdating(): void
    {
        $today = Carbon::today()->toDateString();
        $pastDate = Carbon::today()->subDays(5)->toDateString();

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-FOR-RTG-PAST',
            'bom_name' => 'Widget BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => Carbon::today()->subDays(10)->toDateString(),
            'expiry_date' => null,
        ]);

        $rtgV1 = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RT-PAST-001',
            'name' => 'Widget Routing v1',
            'product_id' => $this->productA->id,
            'bom_id' => $bom->id,
            'version' => '1.0.0',
            'revision' => 0,
            'is_default' => true,
            'effective_from' => Carbon::today()->subDays(10)->toDateString(),
            'effective_to' => null,
            'status' => Routing::STATUS_ACTIVE,
            'created_by' => $this->adminA->id,
        ]);

        $rtgV2 = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RT-PAST-002',
            'name' => 'Widget Routing v2',
            'product_id' => $this->productA->id,
            'bom_id' => $bom->id,
            'version' => '2.0.0',
            'revision' => 0,
            'is_default' => true,
            'effective_from' => $pastDate,
            'effective_to' => null,
            'status' => Routing::STATUS_PENDING_APPROVAL,
            'created_by' => $this->adminA->id,
        ]);

        $rtgV2->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Laser Cut',
            'work_center_id' => $this->workCenterA->id,
        ]);

        $routingService = app(RoutingService::class);
        $routingService->approve($rtgV2->id, $this->adminA->id);

        $rtgV1->refresh();
        $rtgV2->refresh();

        // Effective date automatically adjusts to today (approval date) to prevent back-dating
        $this->assertEquals(Routing::STATUS_ACTIVE, $rtgV2->status);
        $this->assertEquals($today, $rtgV2->effective_from?->toDateString());
        $this->assertStringContainsString("Approval was granted on {$today} after the planned date ({$pastDate})", $rtgV2->description);

        // Previous version expires on today (not back-dated to pastDate)
        $this->assertEquals(Routing::STATUS_HISTORICAL, $rtgV1->status);
        $this->assertEquals($today, $rtgV1->effective_to?->toDateString());
    }

    public function test_same_day_approval_within_buffer_does_not_append_delay_note(): void
    {
        $today = Carbon::today()->toDateString();

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-SAMEDAY-BUFFER',
            'bom_name' => 'Same Day BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'pending_approval',
            'version' => '1.0.0',
            'effective_date' => $today,
            'notes' => 'Original BOM note.',
        ]);

        $bomService = app(ProductionBomService::class);
        $bomService->approve($bom->id, $this->adminA->id);

        $bom->refresh();
        $this->assertEquals('Original BOM note.', $bom->notes);

        $routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RT-SAMEDAY-BUFFER',
            'name' => 'Same Day Routing',
            'product_id' => $this->productA->id,
            'bom_id' => $bom->id,
            'version' => '1.0.0',
            'revision' => 0,
            'is_default' => true,
            'effective_from' => $today,
            'status' => Routing::STATUS_PENDING_APPROVAL,
            'description' => 'Original Routing description.',
            'created_by' => $this->adminA->id,
        ]);

        $routing->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Laser Cut',
            'work_center_id' => $this->workCenterA->id,
        ]);

        $routingService = app(RoutingService::class);
        $routingService->approve($routing->id, $this->adminA->id);

        $routing->refresh();
        $this->assertEquals('Original Routing description.', $routing->description);
    }

    public function test_bom_approval_updates_unsubmitted_draft_routings_to_new_bom_while_leaving_submitted_routings_unchanged(): void
    {
        $today = Carbon::today()->toDateString();

        // 1. Initial approved BOM v1
        $bomV1 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-WIDGET-001',
            'bom_name' => 'Widget BOM v1',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $today,
        ]);

        // 2. Draft routing (unsubmitted, status = 'draft') pointing to BOM v1
        $draftRouting = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RT-WIDGET-DRAFT',
            'name' => 'Widget Routing Draft',
            'product_id' => $this->productA->id,
            'bom_id' => $bomV1->id,
            'version' => '1.0.0',
            'revision' => 0,
            'is_default' => true,
            'effective_from' => $today,
            'status' => Routing::STATUS_DRAFT,
            'created_by' => $this->adminA->id,
        ]);

        // 3. Submitted routing (pending_approval, status = 'pending_approval') pointing to BOM v1
        $submittedRouting = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RT-WIDGET-SUBMITTED',
            'name' => 'Widget Routing Submitted',
            'product_id' => $this->productA->id,
            'bom_id' => $bomV1->id,
            'version' => '1.1.0',
            'revision' => 0,
            'is_default' => false,
            'effective_from' => $today,
            'status' => Routing::STATUS_PENDING_APPROVAL,
            'created_by' => $this->adminA->id,
        ]);

        // 4. Create and approve new BOM v2
        $bomV2 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-WIDGET-002',
            'bom_name' => 'Widget BOM v2',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'pending_approval',
            'version' => '2.0.0',
            'effective_date' => $today,
        ]);

        $bomService = app(ProductionBomService::class);
        $bomService->approve($bomV2->id, $this->adminA->id);

        $draftRouting->refresh();
        $submittedRouting->refresh();

        // Unsubmitted draft routing MUST be updated to point to new BOM v2
        $this->assertEquals($bomV2->id, $draftRouting->bom_id);
        $this->assertEquals(Routing::STATUS_DRAFT, $draftRouting->status);

        // Submitted routing (pending approval) MUST NOT be changed
        $this->assertEquals($bomV1->id, $submittedRouting->bom_id);
        $this->assertEquals(Routing::STATUS_PENDING_APPROVAL, $submittedRouting->status);
    }

    public function test_bom_cancellation_cancels_scheduled_routings_with_reason_and_archives_active_routings_to_historical(): void
    {
        $today = Carbon::today()->toDateString();
        $futureDate = Carbon::today()->addDays(10)->toDateString();

        // 1. Approved BOM
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-WIDGET-CANCEL-TEST',
            'bom_name' => 'Widget BOM Cancel Test',
            'bom_type' => 'manufacturing',
            'product_id' => $this->productA->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => $today,
        ]);

        // 2. Active routing connected to this BOM
        $activeRouting = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RT-WIDGET-ACTIVE',
            'name' => 'Widget Routing Active',
            'product_id' => $this->productA->id,
            'bom_id' => $bom->id,
            'version' => '1.0.0',
            'revision' => 0,
            'is_default' => true,
            'effective_from' => $today,
            'status' => Routing::STATUS_ACTIVE,
            'created_by' => $this->adminA->id,
        ]);

        // 3. Scheduled routing connected to this BOM
        $scheduledRouting = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RT-WIDGET-SCHEDULED',
            'name' => 'Widget Routing Scheduled',
            'product_id' => $this->productA->id,
            'bom_id' => $bom->id,
            'version' => '2.0.0',
            'revision' => 0,
            'is_default' => false,
            'effective_from' => $futureDate,
            'status' => Routing::STATUS_SCHEDULED,
            'created_by' => $this->adminA->id,
        ]);

        // 4. Cancel the BOM with a specific cancellation reason
        $cancelReason = 'Design obsolete due to defect report #99.';
        $bomService = app(ProductionBomService::class);
        $bomService->cancel($bom->id, $this->adminA->id, $cancelReason);

        $bom->refresh();
        $activeRouting->refresh();
        $scheduledRouting->refresh();

        // BOM is cancelled
        $this->assertEquals('cancelled', $bom->status);
        $this->assertEquals($today, $bom->expiry_date?->toDateString());

        // Scheduled routing MUST be cancelled
        $this->assertEquals(Routing::STATUS_CANCELLED, $scheduledRouting->status);
        $this->assertEquals($today, $scheduledRouting->effective_to?->toDateString());

        // Scheduled routing approval log must contain ACTION_CANCELLED and the cancellation reason
        $scheduledApproval = RoutingApproval::where('routing_id', $scheduledRouting->id)
            ->where('action', RoutingApproval::ACTION_CANCELLED)
            ->latest('id')
            ->first();
        $this->assertNotNull($scheduledApproval);
        $this->assertStringContainsString($cancelReason, $scheduledApproval->comments);
        $this->assertStringContainsString($bom->bom_number, $scheduledApproval->comments);

        // Active routing transitions to historical (current flow)
        $this->assertEquals(Routing::STATUS_HISTORICAL, $activeRouting->status);
        $this->assertEquals($today, $activeRouting->effective_to?->toDateString());

        // Active routing approval log must contain ACTION_ARCHIVED
        $activeApproval = RoutingApproval::where('routing_id', $activeRouting->id)
            ->where('action', RoutingApproval::ACTION_ARCHIVED)
            ->latest('id')
            ->first();
        $this->assertNotNull($activeApproval);
    }
}
