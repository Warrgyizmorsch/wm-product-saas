<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\InventoryRemnant;
use App\Domains\Inventory\Models\InventoryRemnantConsumption;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\RemnantInventoryService;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRemnantAllocation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Services\MrpShortageService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionReadinessService;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Production\Services\RemnantAllocationService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RemnantScrapLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $user;
    protected Warehouse $warehouse;
    protected Uom $uomMtr;
    protected Uom $uomPcs;
    protected Uom $uomKg;
    protected Uom $uomSqm;
    protected Product $steelPipe;
    protected Product $steelSheet;
    protected Product $resinWeight;
    protected Product $bracketCount;
    protected ProductionOrder $orderA;
    protected ProductionOrder $orderB;
    protected ProductionOrderReservation $resPipeA;
    protected ProductionOrderReservation $resPipeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Manufacturing Tenant Alpha',
            'slug' => 'tenant-alpha',
            'status' => 'active',
            'plan' => 'enterprise',
            'settings' => [
                'remnant_release_policy' => 'immediate',
            ],
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Competitor Tenant Beta',
            'slug' => 'tenant-beta',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Shopfloor Lead',
            'email' => 'lead@alpha.test',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $managerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->first();
        if ($managerRole) {
            UserRole::create([
                'user_id' => $this->user->id,
                'role_id' => $managerRole->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        $this->actingAs($this->user);

        // UOMs
        $this->uomMtr = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Meters', 'code' => 'Mtr', 'category' => 'Goods']);
        $this->uomPcs = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'Pcs', 'category' => 'Goods']);
        $this->uomKg  = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Kilograms', 'code' => 'Kg', 'category' => 'Goods']);
        $this->uomSqm = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Square Meters', 'code' => 'SqM', 'category' => 'Goods']);

        // Warehouses
        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Factory Raw Store',
            'code' => 'WH-RAW-01',
            'type' => 'standard',
            'status' => 'active',
            'is_default' => true,
        ]);

        // Products
        // Linear: Steel Pipe (Unit cost ₹1000/Mtr)
        $this->steelPipe = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heavy Steel Pipe 50x50',
            'sku' => 'RM-PIPE-50',
            'type' => 'raw_material',
            'uom_id' => $this->uomMtr->id,
            'unit_cost' => 1000.0,
            'cost_price' => 1000.0,
        ]);

        // Sheet: Steel Plate (Unit cost ₹2500/SqM)
        $this->steelSheet = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Plate 2mm Cold Rolled',
            'sku' => 'RM-PLATE-2MM',
            'type' => 'raw_material',
            'uom_id' => $this->uomSqm->id,
            'unit_cost' => 2500.0,
            'cost_price' => 2500.0,
        ]);

        // Weight: Resin (Unit cost ₹500/Kg)
        $this->resinWeight = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Industrial Epoxy Resin',
            'sku' => 'RM-RESIN-EP',
            'type' => 'raw_material',
            'uom_id' => $this->uomKg->id,
            'unit_cost' => 500.0,
            'cost_price' => 500.0,
        ]);

        // Count: Bracket (Unit cost ₹150/Pcs)
        $this->bracketCount = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Heavy Angle Bracket',
            'sku' => 'RM-BRACKET-90',
            'type' => 'raw_material',
            'uom_id' => $this->uomPcs->id,
            'unit_cost' => 150.0,
            'cost_price' => 150.0,
        ]);

        // Production Orders
        $this->orderA = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'WO-2026-001',
            'product_id' => $this->steelPipe->id,
            'status' => 'in_progress',
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);

        $this->orderB = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'WO-2026-002',
            'product_id' => $this->steelPipe->id,
            'status' => 'in_progress',
            'quantity_ordered' => 5,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);

        // Requirements / Reservations
        // Order A requirement for 6 meters (6000 mm) of steel pipe
        $this->resPipeA = ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'product_id' => $this->steelPipe->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => 6.0,
            'quantity_reserved' => 6.0,
            'quantity_issued' => 6.0,
            'uom_id' => $this->uomMtr->id,
        ]);

        // Order B requirement for 2 meters (2000 mm) of steel pipe
        $this->resPipeB = ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderB->id,
            'product_id' => $this->steelPipe->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => 2.0,
            'quantity_reserved' => 0.0,
            'quantity_issued' => 0.0,
            'uom_id' => $this->uomMtr->id,
        ]);

        // Initialize WIP tracking for Order A & Order B
        ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'product_id' => $this->steelPipe->id,
            'status' => 'active',
            'material_cost' => 6000.0, // Initial 6000 mm issued = ₹6000
            'total_value' => 6000.0,
        ]);

        ProductionWip::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderB->id,
            'product_id' => $this->steelPipe->id,
            'status' => 'active',
            'material_cost' => 0.0,
            'total_value' => 0.0,
        ]);
    }

    // =========================================================================
    // 1. SCRAP & DECOUPLED PROCUREMENT TESTS
    // =========================================================================

    public function test_genuine_scrap_records_without_automatic_procurement(): void
    {
        $executionService = app(ProductionExecutionService::class);

        $scrap = $executionService->logScrap(
            $this->orderA->id,
            null,
            $this->steelPipe->id,
            0.5,
            'Cutting torch damage'
        );

        $this->assertInstanceOf(ProductionOrderScrap::class, $scrap);
        $this->assertEquals(0.5, $scrap->quantity);
        $this->assertEquals('Cutting torch damage', $scrap->reason);

        // Assert: NO Requisition slip created automatically
        $reqCount = ProductionRequisitionSlip::where('production_order_id', $this->orderA->id)->count();
        $this->assertEquals(0, $reqCount, 'Scrap logging must never directly create procurement requisitions.');
    }

    public function test_mes_scrap_recording_is_decoupled_from_procurement(): void
    {
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 1,
            'operation_number' => 'OP-010',
            'name' => 'Pipe Cutting',
            'status' => 'running',
            'product_id' => $this->steelPipe->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->steelPipe->id,
                'quantity' => 1.5,
                'reason' => 'Defective wall thickness',
                'scrap_type' => 'unusable_defect',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_order_scraps', [
            'production_order_id' => $this->orderA->id,
            'quantity' => 1.5,
            'scrap_type' => 'unusable_defect',
        ]);

        // Procurement requisition must not be generated
        $this->assertDatabaseMissing('production_requisition_slips', [
            'production_order_id' => $this->orderA->id,
        ]);
    }

    public function test_linear_dimensional_scrap_records_physical_measurements_and_canonical_qty(): void
    {
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 1,
            'operation_number' => 'OP-020',
            'name' => 'Pipe Profiling',
            'status' => 'running',
            'product_id' => $this->steelPipe->id,
        ]);

        $initialRemnantCount = InventoryRemnant::count();

        // 1800 mm damaged pipe (Canonical UOM: Meters -> 1800 mm = 1.8 Mtr)
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 1800.0,
                'pieces' => 1,
                'reason' => 'Damaged / Bent',
                'scrap_type' => 'offcut_scrap',
                'scrap_warehouse_id' => $this->warehouse->id,
                'storage_location' => 'Scrap Hopper B-01',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('production_order_scraps', [
            'production_order_id' => $this->orderA->id,
            'product_id' => $this->steelPipe->id,
            'quantity' => 1.8,
            'measurement_type' => 'linear',
            'length' => 1800.0,
            'pieces' => 1,
            'reason' => 'Damaged / Bent',
            'scrap_type' => 'offcut_scrap',
            'scrap_warehouse_id' => $this->warehouse->id,
            'storage_location' => 'Scrap Hopper B-01',
        ]);

        // Assert: Dimensional scrap does NOT create a remnant or procurement
        $this->assertEquals($initialRemnantCount, InventoryRemnant::count());
        $this->assertDatabaseMissing('production_requisition_slips', [
            'production_order_id' => $this->orderA->id,
        ]);
    }

    public function test_sheet_dimensional_scrap_calculates_canonical_sqm(): void
    {
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 2,
            'operation_number' => 'OP-030',
            'name' => 'Plate Stamping',
            'status' => 'running',
            'product_id' => $this->steelSheet->id,
        ]);

        // Sheet scrap: 1200 mm x 800 mm x 1 piece -> 0.96 SqM
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->steelSheet->id,
                'measurement_type' => 'sheet',
                'length' => 1200.0,
                'width' => 800.0,
                'thickness' => 2.0,
                'pieces' => 1,
                'reason' => 'Cutting Error / Wrong Dimension',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('production_order_scraps', [
            'production_order_id' => $this->orderA->id,
            'product_id' => $this->steelSheet->id,
            'quantity' => 0.96,
            'measurement_type' => 'sheet',
            'length' => 1200.0,
            'width' => 800.0,
            'thickness' => 2.0,
            'pieces' => 1,
        ]);
    }

    public function test_weight_and_count_dimensional_scrap(): void
    {
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 3,
            'operation_number' => 'OP-040',
            'name' => 'Molding & Fastening',
            'status' => 'running',
        ]);

        // Weight scrap in kg: 2.5 kg -> 2.5
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->resinWeight->id,
                'measurement_type' => 'weight',
                'weight' => 2.5,
                'weight_unit' => 'kg',
                'reason' => 'Contamination',
            ]);

        $this->assertDatabaseHas('production_order_scraps', [
            'product_id' => $this->resinWeight->id,
            'quantity' => 2.5,
            'measurement_type' => 'weight',
            'weight' => 2.5,
            'weight_unit' => 'kg',
        ]);

        // Weight scrap in grams: 500 g -> 0.5 kg
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->resinWeight->id,
                'measurement_type' => 'weight',
                'weight' => 500.0,
                'weight_unit' => 'g',
                'reason' => 'Contamination',
            ]);

        $this->assertDatabaseHas('production_order_scraps', [
            'product_id' => $this->resinWeight->id,
            'quantity' => 0.5,
            'measurement_type' => 'weight',
            'weight' => 500.0,
            'weight_unit' => 'g',
        ]);

        // Count scrap: 3 pieces -> 3.0
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->bracketCount->id,
                'measurement_type' => 'count',
                'pieces' => 3,
                'reason' => 'Stripped threads',
            ]);

        $this->assertDatabaseHas('production_order_scraps', [
            'product_id' => $this->bracketCount->id,
            'quantity' => 3.0,
            'measurement_type' => 'count',
            'pieces' => 3,
        ]);
    }

    public function test_scrappable_materials_auto_infers_measurement_type_and_issued_context(): void
    {
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 1,
            'operation_number' => 'OP-050',
            'name' => 'Assembly Inspection',
            'status' => 'running',
            'product_id' => $this->steelPipe->id,
        ]);

        $materials = $op->scrappable_materials;
        $pipeEntry = $materials->firstWhere('id', $this->steelPipe->id);

        $this->assertNotNull($pipeEntry);
        $this->assertEquals('linear', $pipeEntry['measurement_type']);
        $this->assertStringContainsString('Issued:', $pipeEntry['issued_display']);
        $this->assertEquals($this->warehouse->id, $pipeEntry['warehouse_id']);
    }

    public function test_return_unused_material_restores_warehouse_stock(): void
    {
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 1,
            'operation_number' => 'OP-060',
            'name' => 'Final Trimming',
            'status' => 'running',
        ]);

        $initialStock = ProductWarehouseStock::where('product_id', $this->steelPipe->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity') ?? 0.0;

        // Order A has 6.0 Mtr issued on resPipeA. Return 2.0 Mtr intact.
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.return-material', $op->id), [
                'reservation_id' => $this->resPipeA->id,
                'quantity' => 2.0,
                'remarks' => 'Intact pipe returned to store',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Issued drops from 6.0 to 4.0
        $this->resPipeA->refresh();
        $this->assertEquals(4.0, $this->resPipeA->quantity_issued);

        // Warehouse stock increases by 2.0
        $finalStock = ProductWarehouseStock::where('product_id', $this->steelPipe->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');
        $this->assertEquals($initialStock + 2.0, $finalStock);

        // WIP balance credited by 2.0 Mtr * ₹1000 = ₹2000 (WIP drops from 6000 to 4000)
        $wipA = ProductionWip::where('production_order_id', $this->orderA->id)->first();
        $this->assertEquals(4000.0, $wipA->material_cost);

        // Does NOT create scrap or remnant
        $this->assertDatabaseMissing('production_order_scraps', [
            'reason' => 'Intact pipe returned to store',
        ]);
        $this->assertDatabaseMissing('inventory_remnants', [
            'notes' => 'Intact pipe returned to store',
        ]);
    }

    // =========================================================================
    // 2. REMNANT CREATION & TYPE-SAFE MEASUREMENT CONVERSIONS
    // =========================================================================

    public function test_linear_remnant_creation_and_type_safe_conversion(): void
    {
        $remnantService = app(RemnantInventoryService::class);

        // 1800 mm steel pipe (UOM is Meters: 1800 mm = 1.8 Mtr)
        $remnant = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 1800.0,
            'pieces' => 1,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location' => 'Rack A-05',
            'source_production_order_id' => $this->orderA->id,
        ], $this->user->id);

        $this->assertInstanceOf(InventoryRemnant::class, $remnant);
        $this->assertEquals('linear', $remnant->measurement_type);
        $this->assertEquals(1800.0, $remnant->current_length);
        $this->assertEquals(1.8, $remnant->current_quantity);
        $this->assertEquals(1800.0, $remnant->available_length);
        $this->assertEquals(1.8, $remnant->available_quantity);
        $this->assertEquals(1800.0, $remnant->total_valuation); // 1.8 Mtr * ₹1000/Mtr = ₹1800
        $this->assertEquals(InventoryRemnant::STATUS_AVAILABLE, $remnant->status);

        // WIP Credit: Order A should have ₹1800 credited from WIP (6000 - 1800 = 4200)
        $wipA = ProductionWip::where('production_order_id', $this->orderA->id)->first();
        $this->assertEquals(4200.0, $wipA->material_cost);
    }

    public function test_sheet_remnant_creation_and_conversion(): void
    {
        $remnantService = app(RemnantInventoryService::class);

        // 2000 x 1000 mm sheet plate (Area = 2.0 SqM)
        $remnant = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelSheet->id,
            'measurement_type' => 'sheet',
            'length' => 2000.0,
            'width' => 1000.0,
            'thickness' => 2.0,
            'pieces' => 1,
        ], $this->user->id);

        $this->assertEquals('sheet', $remnant->measurement_type);
        $this->assertEquals(2000.0, $remnant->current_length);
        $this->assertEquals(1000.0, $remnant->current_width);
        $this->assertEquals(2.0, $remnant->thickness);
        $this->assertEquals(2.0, $remnant->current_quantity); // 2 SqM
        $this->assertEquals(5000.0, $remnant->total_valuation); // 2 SqM * ₹2500/SqM = ₹5000
    }

    public function test_weight_remnant_creation_with_deterministic_kg_g_conversion(): void
    {
        $remnantService = app(RemnantInventoryService::class);

        // 1. kg input for kg canonical product
        $remnantKg = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->resinWeight->id,
            'measurement_type' => 'weight',
            'weight' => 5.25,
            'weight_unit' => 'kg',
        ], $this->user->id);

        $this->assertEquals(5.25, $remnantKg->current_quantity);

        // 2. g input for kg canonical product (1500 g = 1.5 kg)
        $remnantG = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->resinWeight->id,
            'measurement_type' => 'weight',
            'weight' => 1500.0,
            'weight_unit' => 'g',
        ], $this->user->id);

        $this->assertEquals(1.5, $remnantG->current_quantity);

        // 3. Unsupported weight unit rejected
        $this->expectException(InvalidArgumentException::class);
        $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->resinWeight->id,
            'measurement_type' => 'weight',
            'weight' => 10.0,
            'weight_unit' => 'lbs',
        ], $this->user->id);
    }

    public function test_remnant_approval_policy_pending_confirmation(): void
    {
        // Set tenant policy to require_approval
        $this->tenant->update([
            'settings' => ['remnant_release_policy' => 'require_approval'],
        ]);

        $remnantService = app(RemnantInventoryService::class);

        $remnant = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 1500.0,
        ], $this->user->id);

        $this->assertEquals(InventoryRemnant::STATUS_PENDING_CONFIRMATION, $remnant->status);
        $this->assertFalse($remnant->isAvailable());

        // Pending remnant cannot be reserved
        $allocationService = app(RemnantAllocationService::class);
        $compatibles = $allocationService->findCompatibleRemnants($this->tenant->id, $this->steelPipe->id, ['required_length' => 1000]);
        $this->assertFalse($compatibles->contains('id', $remnant->id), 'Pending remnant must not be available for allocation.');

        // Confirm remnant
        $confirmed = $remnantService->confirmRemnant($remnant->id, $this->user->id);
        $this->assertEquals(InventoryRemnant::STATUS_AVAILABLE, $confirmed->status);
        $this->assertTrue($confirmed->isAvailable());
    }

    // =========================================================================
    // 3. RESERVATION & OWNERSHIP: 1-TO-MANY REMNANT ALLOCATION (CRITICAL ISSUE 1 & 2)
    // =========================================================================

    public function test_multiple_remnants_fulfill_single_production_requirement(): void
    {
        $remnantService = app(RemnantInventoryService::class);
        $allocationService = app(RemnantAllocationService::class);

        // Create 3 remnants:
        // REM-A = 800 mm (0.8 m)
        // REM-B = 700 mm (0.7 m)
        // REM-C = 500 mm (0.5 m)
        // Total = 2000 mm (2.0 m)
        $remA = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 800.0,
        ], $this->user->id);

        $remB = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 700.0,
        ], $this->user->id);

        $remC = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 500.0,
        ], $this->user->id);

        // Allocate all 3 remnants to Order B requirement (resPipeB requires 2.0 m / 2000 mm)
        $allocations = $allocationService->allocateRemnants(
            $this->tenant->id,
            $this->orderB->id,
            $this->resPipeB->id,
            [
                ['remnant_id' => $remA->id, 'allocated_length' => 800.0],
                ['remnant_id' => $remB->id, 'allocated_length' => 700.0],
                ['remnant_id' => $remC->id, 'allocated_length' => 500.0],
            ],
            $this->user->id
        );

        $this->assertCount(3, $allocations);

        // Check ownership records
        $this->assertDatabaseHas('production_order_remnant_allocations', [
            'production_order_id' => $this->orderB->id,
            'production_order_reservation_id' => $this->resPipeB->id,
            'remnant_id' => $remA->id,
            'allocated_length' => 800.0,
            'status' => 'reserved',
        ]);

        $this->assertDatabaseHas('production_order_remnant_allocations', [
            'production_order_id' => $this->orderB->id,
            'production_order_reservation_id' => $this->resPipeB->id,
            'remnant_id' => $remB->id,
            'allocated_length' => 700.0,
            'status' => 'reserved',
        ]);

        $this->assertDatabaseHas('production_order_remnant_allocations', [
            'production_order_id' => $this->orderB->id,
            'production_order_reservation_id' => $this->resPipeB->id,
            'remnant_id' => $remC->id,
            'allocated_length' => 500.0,
            'status' => 'reserved',
        ]);

        // Reservation on order B should now have 2.0 m reserved
        $this->resPipeB->refresh();
        $this->assertEquals(2.0, $this->resPipeB->quantity_reserved);

        // Remnants should be fully reserved
        $remA->refresh();
        $remB->refresh();
        $remC->refresh();

        $this->assertEquals(0.0, $remA->available_length);
        $this->assertEquals(InventoryRemnant::STATUS_FULLY_RESERVED, $remA->status);
        $this->assertEquals(0.0, $remB->available_length);
        $this->assertEquals(InventoryRemnant::STATUS_FULLY_RESERVED, $remB->status);
        $this->assertEquals(0.0, $remC->available_length);
        $this->assertEquals(InventoryRemnant::STATUS_FULLY_RESERVED, $remC->status);
    }

    public function test_remnant_reservation_and_release_lifecycle(): void
    {
        $remnantService = app(RemnantInventoryService::class);
        $allocationService = app(RemnantAllocationService::class);

        // REM = 1800 mm
        $rem = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 1800.0,
        ], $this->user->id);

        // Reserve 600 mm (0.6 m)
        $allocations = $allocationService->allocateRemnants(
            $this->tenant->id,
            $this->orderB->id,
            $this->resPipeB->id,
            [['remnant_id' => $rem->id, 'allocated_length' => 600.0]],
            $this->user->id
        );

        $alloc = $allocations[0];
        $rem->refresh();

        $this->assertEquals(1800.0, $rem->current_length, 'Physical length must NOT change upon reservation.');
        $this->assertEquals(600.0, $rem->reserved_length);
        $this->assertEquals(1200.0, $rem->available_length);
        $this->assertEquals(InventoryRemnant::STATUS_PARTIALLY_RESERVED, $rem->status);

        // Release reservation
        $allocationService->releaseAllocation($alloc->id, $this->user->id);

        $rem->refresh();
        $this->assertEquals(1800.0, $rem->current_length);
        $this->assertEquals(0.0, $rem->reserved_length);
        $this->assertEquals(1800.0, $rem->available_length);
        $this->assertEquals(InventoryRemnant::STATUS_AVAILABLE, $rem->status);

        $alloc->refresh();
        $this->assertEquals('released', $alloc->status);
    }

    public function test_competing_reservations_exceeding_availability_are_rejected(): void
    {
        $remnantService = app(RemnantInventoryService::class);
        $allocationService = app(RemnantAllocationService::class);

        // REM = 600 mm
        $rem = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 600.0,
        ], $this->user->id);

        // Order A reserves 500 mm -> succeeds
        $allocationService->allocateRemnants(
            $this->tenant->id,
            $this->orderA->id,
            $this->resPipeA->id,
            [['remnant_id' => $rem->id, 'allocated_length' => 500.0]],
            $this->user->id
        );

        // Order B tries to reserve 300 mm (only 100 mm available) -> fails
        $this->expectException(InvalidArgumentException::class);
        $allocationService->allocateRemnants(
            $this->tenant->id,
            $this->orderB->id,
            $this->resPipeB->id,
            [['remnant_id' => $rem->id, 'allocated_length' => 300.0]],
            $this->user->id
        );
    }

    // =========================================================================
    // 4. PHYSICAL CONSUMPTION & WIP COSTING (CRITICAL ISSUE 12)
    // =========================================================================

    public function test_full_consumption_lifecycle_and_wip_costing(): void
    {
        $remnantService = app(RemnantInventoryService::class);
        $allocationService = app(RemnantAllocationService::class);

        // Scenario:
        // 6000 mm pipe -> 4200 mm consumed -> 1800 mm reusable remnant REM-001 created
        // Source Order A WIP credited ₹1800
        $rem = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 1800.0,
            'source_production_order_id' => $this->orderA->id,
        ], $this->user->id);

        $wipA = ProductionWip::where('production_order_id', $this->orderA->id)->first();
        $this->assertEquals(4200.0, $wipA->material_cost);

        // Step 1: Order B reserves 600 mm (0.6 m)
        $allocs1 = $allocationService->allocateRemnants(
            $this->tenant->id,
            $this->orderB->id,
            $this->resPipeB->id,
            [['remnant_id' => $rem->id, 'allocated_length' => 600.0]],
            $this->user->id
        );

        // Step 2: Actual saw cut consumes 600 mm for Order B
        $consumption1 = $allocationService->consumeAllocatedRemnant($allocs1[0]->id, 600.0, 0.6, $this->user->id);

        $rem->refresh();
        $this->assertEquals(1200.0, $rem->current_length);
        $this->assertEquals(1.2, $rem->current_quantity);
        $this->assertEquals(InventoryRemnant::STATUS_AVAILABLE, $rem->status);

        // WIP Costing: Order B debited ₹600 (0.6 m * ₹1000/m)
        $wipB = ProductionWip::where('production_order_id', $this->orderB->id)->first();
        $this->assertEquals(600.0, $wipB->material_cost);

        // Step 3: Direct consumption of 500 mm (0.5 m)
        $consumption2 = $remnantService->consumeRemnant($rem->id, 0.5, 500.0, $this->orderB->id, null, $this->user->id);

        $rem->refresh();
        $this->assertEquals(700.0, $rem->current_length);
        $this->assertEquals(0.7, $rem->current_quantity);

        // Step 4: Final 700 mm (0.7 m) consumed -> remnant becomes consumed
        $consumption3 = $remnantService->consumeRemnant($rem->id, 0.7, 700.0, $this->orderB->id, null, $this->user->id);

        $rem->refresh();
        $this->assertEquals(0.0, $rem->current_length);
        $this->assertEquals(0.0, $rem->current_quantity);
        $this->assertEquals(InventoryRemnant::STATUS_CONSUMED, $rem->status);

        // Assert all 3 events are recorded in immutable ledger as 'consumption'
        $consumptions = InventoryRemnantConsumption::where('remnant_id', $rem->id)->get();
        $this->assertCount(3, $consumptions);
        foreach ($consumptions as $c) {
            $this->assertEquals('consumption', $c->event_type);
        }
    }

    // =========================================================================
    // 5. PHYSICAL SPLIT IS NOT CONSUMPTION (CRITICAL ISSUE 3)
    // =========================================================================

    public function test_remnant_split_is_recorded_as_split_event_and_preserves_lineage(): void
    {
        $remnantService = app(RemnantInventoryService::class);

        // REM-001 = 1800 mm
        $parent = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 1800.0,
            'heat_number' => 'HEAT-9942',
        ], $this->user->id);

        $parentOriginalCode = $parent->remnant_code;

        // Split 800 mm off
        $result = $remnantService->splitRemnant($parent->id, [
            'split_length' => 800.0,
            'split_pieces' => 1,
            'warehouse_location' => 'Rack B-10',
            'notes' => 'Custom length cut for stock preservation',
        ], $this->user->id);

        $parentFresh = $result['parent'];
        $childFresh = $result['child'];

        // Parent retains identity, reduced by 800 mm -> 1000 mm
        $this->assertEquals($parentOriginalCode, $parentFresh->remnant_code);
        $this->assertEquals(1000.0, $parentFresh->current_length);
        $this->assertEquals(1.0, $parentFresh->current_quantity);

        // Child receives new code, length 800 mm, links to parent
        $this->assertNotEquals($parentOriginalCode, $childFresh->remnant_code);
        $this->assertEquals(800.0, $childFresh->current_length);
        $this->assertEquals(0.8, $childFresh->current_quantity);
        $this->assertEquals($parent->id, $childFresh->parent_remnant_id);
        $this->assertEquals('HEAT-9942', $childFresh->heat_number);

        // Ledger audit check: Event MUST be 'split', NOT 'consumption'
        $this->assertDatabaseHas('inventory_remnant_consumptions', [
            'remnant_id' => $parent->id,
            'event_type' => 'split',
            'split_remnant_id' => $childFresh->id,
            'consumed_length' => 800.0,
            'consumed_quantity' => 0.8,
            'remaining_length' => 1000.0,
            'remaining_quantity' => 1.0,
        ]);

        // Combined valuation must equal original: ₹1000 + ₹800 = ₹1800
        $this->assertEquals(1800.0, $parentFresh->total_valuation + $childFresh->total_valuation);
    }

    // =========================================================================
    // 6. MRP & MATERIAL READINESS COMPATIBILITY
    // =========================================================================

    public function test_mrp_considers_compatible_remnants_in_stock_availability(): void
    {
        $remnantService = app(RemnantInventoryService::class);
        $mrpService = app(MrpShortageService::class);

        // Create available remnant: 1500 mm (1.5 mtr)
        $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelPipe->id,
            'measurement_type' => 'linear',
            'length' => 1500.0,
            'warehouse_id' => $this->warehouse->id,
        ], $this->user->id);

        $snapshot = $mrpService->getInventorySnapshot($this->tenant->id, $this->steelPipe->id, $this->warehouse->id);

        // Remnant quantity (1.5 mtr) should be included in on_hand & available
        $this->assertGreaterThanOrEqual(1.5, $snapshot['available']);
    }

    public function test_sheet_dimensional_compatibility_checker(): void
    {
        $remnantService = app(RemnantInventoryService::class);
        $allocationService = app(RemnantAllocationService::class);

        // Plate: 2000 x 1000 mm, thickness 2mm
        $plate = $remnantService->registerRemnant($this->tenant->id, [
            'product_id' => $this->steelSheet->id,
            'measurement_type' => 'sheet',
            'length' => 2000.0,
            'width' => 1000.0,
            'thickness' => 2.0,
        ], $this->user->id);

        // Requirement 1: 1500 x 800 mm, thickness 2mm -> compatible (standard fit)
        $compat1 = $allocationService->findCompatibleRemnants($this->tenant->id, $this->steelSheet->id, [
            'required_length' => 1500.0,
            'required_width' => 800.0,
            'thickness' => 2.0,
        ]);
        $this->assertTrue($compat1->contains('id', $plate->id));

        // Requirement 2: 900 x 1800 mm, thickness 2mm -> compatible (rotated fit)
        $compat2 = $allocationService->findCompatibleRemnants($this->tenant->id, $this->steelSheet->id, [
            'required_length' => 900.0,
            'required_width' => 1800.0,
            'thickness' => 2.0,
        ]);
        $this->assertTrue($compat2->contains('id', $plate->id));

        // Requirement 3: 2500 x 1000 mm -> incompatible (too long)
        $compat3 = $allocationService->findCompatibleRemnants($this->tenant->id, $this->steelSheet->id, [
            'required_length' => 2500.0,
            'required_width' => 1000.0,
            'thickness' => 2.0,
        ]);
        $this->assertFalse($compat3->contains('id', $plate->id));

        // Requirement 4: thickness 3mm -> incompatible (wrong thickness)
        $compat4 = $allocationService->findCompatibleRemnants($this->tenant->id, $this->steelSheet->id, [
            'required_length' => 1500.0,
            'required_width' => 800.0,
            'thickness' => 3.0,
        ]);
        $this->assertFalse($compat4->contains('id', $plate->id));
    }

    // =========================================================================
    // 7. API ENDPOINT COMPATIBILITY & TENANT ISOLATION
    // =========================================================================

    private function apiHeaders(Tenant $tenant, User $user): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant' => $tenant->slug,
            'X-API-SECRET' => config('production.api_secret', 'wm-production-secret-test-key'),
            'Authorization' => 'Bearer ' . $user->createToken('test-token')->plainTextToken,
        ];
    }

    public function test_legacy_scrap_api_payload_is_backward_compatible(): void
    {
        $response = $this->withHeaders($this->apiHeaders($this->tenant, $this->user))
            ->postJson("/api/v1/production/orders/{$this->orderA->id}/scrap", [
                'product_id' => $this->steelPipe->id,
                'quantity' => 2.0,
                'reason' => 'Defect',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('production_order_scraps', [
            'production_order_id' => $this->orderA->id,
            'product_id' => $this->steelPipe->id,
            'quantity' => 2.0,
            'reason' => 'Defect',
        ]);
    }

    public function test_api_remnant_registration_and_tenant_isolation(): void
    {
        // 1. Valid API remnant registration
        $response = $this->withHeaders($this->apiHeaders($this->tenant, $this->user))
            ->postJson("/api/v1/production/orders/{$this->orderA->id}/remnants", [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 1200.0,
                'pieces' => 1,
                'warehouse_id' => $this->warehouse->id,
                'warehouse_location' => 'Rack A-01',
                'notes' => 'API registered remnant',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.canonical_quantity', 1.2);

        // 2. Tenant isolation: Other tenant user cannot access or manipulate this remnant
        $otherUser = User::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Intruder',
            'email' => 'intruder@beta.test',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $managerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->first();
        if ($managerRole) {
            UserRole::create([
                'user_id' => $otherUser->id,
                'role_id' => $managerRole->id,
                'tenant_id' => $this->otherTenant->id,
            ]);
        }

        $otherOrder = ProductionOrder::create([
            'tenant_id' => $this->otherTenant->id,
            'order_number' => 'WO-BETA-001',
            'product_id' => $this->steelPipe->id,
            'status' => 'in_progress',
            'quantity_ordered' => 1,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);

        $remnantId = $response->json('data.id');

        // Other tenant cannot allocate Alpha's remnant
        $intrudeResponse = $this->withHeaders($this->apiHeaders($this->otherTenant, $otherUser))
            ->postJson("/api/v1/production/orders/{$otherOrder->id}/allocate-remnant", [
                'reservation_id' => 99999,
                'allocations' => [
                    ['remnant_id' => $remnantId, 'allocated_length' => 500.0],
                ],
            ]);

        $this->assertTrue(in_array($intrudeResponse->status(), [404, 422, 403]));
    }

    public function test_repeated_and_over_return_unused_is_prevented(): void
    {
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 1,
            'operation_number' => 'OP-070',
            'name' => 'Over Return Test Op',
            'status' => 'running',
        ]);

        // Attempt 1: Try to return 8.0 when only 6.0 is issued -> Should fail
        $resOver = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.return-material', $op->id), [
                'reservation_id' => $this->resPipeA->id,
                'quantity' => 8.0,
            ]);

        $resOver->assertRedirect();
        $this->assertNotEmpty(session('error'));
        $this->resPipeA->refresh();
        $this->assertEquals(6.0, $this->resPipeA->quantity_issued);

        // Attempt 2: Return 4.0 successfully -> quantity_issued becomes 2.0
        $resValid = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.return-material', $op->id), [
                'reservation_id' => $this->resPipeA->id,
                'quantity' => 4.0,
            ]);

        $resValid->assertRedirect();
        $resValid->assertSessionHasNoErrors();
        $this->resPipeA->refresh();
        $this->assertEquals(2.0, $this->resPipeA->quantity_issued);

        // Attempt 3: Try to return 3.0 when only 2.0 remains -> Should fail
        $resExceed = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.return-material', $op->id), [
                'reservation_id' => $this->resPipeA->id,
                'quantity' => 3.0,
            ]);

        $resExceed->assertRedirect();
        $this->assertNotEmpty(session('error'));
        $this->resPipeA->refresh();
        $this->assertEquals(2.0, $this->resPipeA->quantity_issued);

        // Attempt 4: Return remaining 2.0 -> quantity_issued becomes 0.0
        $resFinal = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.return-material', $op->id), [
                'reservation_id' => $this->resPipeA->id,
                'quantity' => 2.0,
            ]);

        $resFinal->assertRedirect();
        $resFinal->assertSessionHasNoErrors();
        $this->resPipeA->refresh();
        $this->assertEquals(0.0, $this->resPipeA->quantity_issued);
    }

    public function test_cross_tenant_isolation_on_scrap_and_remnant_submission(): void
    {
        $foreignWarehouse = Warehouse::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Foreign Competitor Store',
            'code' => 'WH-COMPETITOR',
            'type' => 'standard',
            'status' => 'active',
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 1,
            'operation_number' => 'OP-080',
            'name' => 'Cross Tenant Op',
            'status' => 'running',
        ]);

        // 1. Operator in tenant Alpha attempts to save remnant into Beta's warehouse -> Rejected
        $remnantResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.remnant', $op->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 1500,
                'pieces' => 1,
                'warehouse_id' => $foreignWarehouse->id,
            ]);

        $remnantResponse->assertRedirect();
        $this->assertStringContainsString('Warehouse does not belong to this tenant', session('error') ?? '');
        $this->assertDatabaseMissing('inventory_remnants', [
            'warehouse_id' => $foreignWarehouse->id,
        ]);

        // 2. Operator in tenant Alpha attempts to log scrap to Beta's warehouse -> Rejected
        $scrapResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 500,
                'pieces' => 1,
                'reason' => 'Scrap Isolation Test',
                'scrap_warehouse_id' => $foreignWarehouse->id,
            ]);

        $scrapResponse->assertRedirect();
        $this->assertStringContainsString('Warehouse does not belong to this tenant', session('error') ?? '');
        $this->assertDatabaseMissing('production_order_scraps', [
            'scrap_warehouse_id' => $foreignWarehouse->id,
        ]);

        // 3. Operator in tenant Alpha attempts to return material to Beta's warehouse -> Rejected
        $returnResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.return-material', $op->id), [
                'reservation_id' => $this->resPipeA->id,
                'quantity' => 1.0,
                'warehouse_id' => $foreignWarehouse->id,
            ]);

        $returnResponse->assertRedirect();
        $this->assertStringContainsString('does not belong to this tenant', session('error') ?? '');
    }

    public function test_operator_execution_ui_renders_unified_material_disposition(): void
    {
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 1,
            'operation_number' => 'OP-DISP-01',
            'name' => 'Cutting Operation',
            'status' => 'running',
            'quantity_consumed' => 4.2,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.operator.execution', $op->id));

        $response->assertOk();
        // 1. Primary unified button exists
        $response->assertSee('MATERIAL DISPOSITION');
        // 2. Three disposition choices exist in modal
        $response->assertSee('What happened to this material?');
        $response->assertSee('SCRAP');
        $response->assertSee('OFFCUT');
        $response->assertSee('RETURN UNUSED');
        // 3. Material Balance Context
        $response->assertSee('Issued: 6000 mm');
        $response->assertSee('Consumed: 4200 mm');
        $response->assertSee('Remaining: 1800 mm');
        // 4. Dimensional field groups exist
        $response->assertSee('Scrap Length (mm)');
        $response->assertSee('Cut Off Length (mm)');
        $response->assertSee('Select Material Reservation Line');
    }

    public function test_mes_dashboard_renders_unified_material_disposition_modal(): void
    {
        $sched = \App\Domains\Production\Models\ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'schedule_number' => 'SCHED-TEST-DISP',
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 1,
            'operation_number' => 'OP-DASH-01',
            'name' => 'Dashboard Op',
            'status' => 'running',
        ]);

        \App\Domains\Production\Models\ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $sched->id,
            'production_order_id' => $this->orderA->id,
            'production_order_operation_id' => $op->id,
            'sequence' => 1,
            'status' => 'ready',
            'planned_start' => now(),
            'planned_finish' => now()->addHours(2),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));

        $response->assertOk();
        // Unified modal partial is included and legacy scrap modal is replaced
        $response->assertSee('What happened to this material?');
        $response->assertSee('materialDispositionModal' . $op->id);
    }

    public function test_material_balance_enforcement_issued_6000_consumed_4200_remaining_1800(): void
    {
        // Setup:
        // Order A has $this->resPipeA with quantity_issued = 6.0 Mtr (6000 mm).
        // Create an operation with quantity_consumed = 4.2 Mtr (4200 mm).
        // Remaining disposable balance = 1.8 Mtr (1800 mm).
        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->orderA->id,
            'sequence' => 1,
            'operation_number' => 'OP-BAL-01',
            'name' => 'Pipe Precision Cut',
            'status' => 'running',
            'quantity_consumed' => 4.2,
        ]);

        $balance = $op->getMaterialBalance($this->steelPipe->id);
        $this->assertEquals(6.0, $balance['issued_qty']);
        $this->assertEquals(4.2, $balance['consumed_qty']);
        $this->assertEquals(1.8, $balance['remaining_qty']);

        // 1. Scrap 2000 mm (2.0 Mtr) fails because 2.0 > 1.8 remaining
        $overScrapResp = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 2000, // 2000 mm = 2.0 Mtr > 1.8 Mtr remaining
                'pieces' => 1,
                'reason' => 'Damaged End',
            ]);
        $overScrapResp->assertRedirect();
        $this->assertStringContainsString('Exceeds remaining disposable material quantity', session('error') ?? '');
        $this->assertDatabaseMissing('production_order_scraps', [
            'production_order_operation_id' => $op->id,
            'quantity' => 2.0,
        ]);

        // 2. Offcut 2000 mm (2.0 Mtr) fails because 2.0 > 1.8 remaining
        $overOffcutResp = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.remnant', $op->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 2000,
                'pieces' => 1,
                'warehouse_id' => $this->warehouse->id,
            ]);
        $overOffcutResp->assertRedirect();
        $this->assertStringContainsString('Exceeds remaining disposable material quantity', session('error') ?? '');
        $this->assertDatabaseMissing('inventory_remnants', [
            'source_production_order_operation_id' => $op->id,
            'initial_quantity' => 2.0,
        ]);

        // 3. Scrap 1800 mm (1.8 Mtr) succeeds (exactly remaining quantity)
        $validScrapResp = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 1800,
                'pieces' => 1,
                'reason' => 'Cutting End Defect',
            ]);
        $validScrapResp->assertRedirect();
        $this->assertDatabaseHas('production_order_scraps', [
            'production_order_operation_id' => $op->id,
            'quantity' => 1.8,
        ]);

        // 4. Scrap 1800 followed by another disposition of 1800 against the same remaining material fails
        $doubleScrapResp = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 1800,
                'pieces' => 1,
                'reason' => 'Attempt Duplicate Scrap',
            ]);
        $doubleScrapResp->assertRedirect();
        $this->assertStringContainsString('Exceeds remaining disposable material quantity', session('error') ?? '');

        // Also Offcut against the exhausted remaining material fails
        $exhaustedOffcutResp = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.remnant', $op->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 500,
                'pieces' => 1,
                'warehouse_id' => $this->warehouse->id,
            ]);
        $exhaustedOffcutResp->assertRedirect();
        $this->assertStringContainsString('Exceeds remaining disposable material quantity', session('error') ?? '');

        // 5. Offcut 1800 mm succeeds in a fresh separate scenario
        $orderFresh = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'WO-FRESH-001',
            'product_id' => $this->steelPipe->id,
            'status' => 'in_progress',
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);
        ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderFresh->id,
            'product_id' => $this->steelPipe->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => 6.0,
            'quantity_reserved' => 6.0,
            'quantity_issued' => 6.0,
            'uom_id' => $this->uomMtr->id,
        ]);
        $opFresh = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderFresh->id,
            'sequence' => 1,
            'operation_number' => 'OP-FRESH-01',
            'name' => 'Fresh Pipe Cut',
            'status' => 'running',
            'quantity_consumed' => 4.2,
        ]);

        $freshOffcutResp = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.remnant', $opFresh->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 1800,
                'pieces' => 1,
                'warehouse_id' => $this->warehouse->id,
            ]);
        $freshOffcutResp->assertRedirect();
        $this->assertDatabaseHas('inventory_remnants', [
            'source_production_order_operation_id' => $opFresh->id,
            'initial_quantity' => 1.8,
            'initial_length' => 1800,
        ]);

        // 6. Separate reservation/order of same product can still be disposed independently
        $orderIndependent = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'WO-INDEP-002',
            'product_id' => $this->steelPipe->id,
            'status' => 'in_progress',
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);
        ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderIndependent->id,
            'product_id' => $this->steelPipe->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => 6.0,
            'quantity_reserved' => 6.0,
            'quantity_issued' => 6.0,
            'uom_id' => $this->uomMtr->id,
        ]);
        $opIndependent = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $orderIndependent->id,
            'sequence' => 1,
            'operation_number' => 'OP-INDEP-01',
            'name' => 'Independent Pipe Cut',
            'status' => 'running',
            'quantity_consumed' => 4.2,
        ]);

        $indepScrapResp = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $opIndependent->id), [
                'product_id' => $this->steelPipe->id,
                'measurement_type' => 'linear',
                'length' => 1800,
                'pieces' => 1,
                'reason' => 'Independent Order Scrap',
            ]);
        $indepScrapResp->assertRedirect();
        $this->assertDatabaseHas('production_order_scraps', [
            'production_order_operation_id' => $opIndependent->id,
            'quantity' => 1.8,
        ]);
    }

    /**
     * Test Production Order Show page and Production MIS Reports display physical scrap measurements
     * in mm and display reusable offcuts/remnants placed in storage for future production.
     */
    public function test_production_order_show_page_and_reports_display_scrap_measurements_and_reusable_offcuts(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-MEASURE-001',
            'product_id' => $this->steelPipe->id,
            'quantity_ordered' => 10,
            'quantity_produced' => 8,
            'quantity_scrapped' => 1.6,
            'status' => 'in_progress',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 1,
            'operation_number' => 'OP-CUT-01',
            'name' => 'Table Leg Pipe Cutting',
            'status' => 'running',
            'quantity_consumed' => 8.0,
        ]);

        $executionService = app(\App\Domains\Production\Services\ProductionExecutionService::class);

        // 1. Log scrap with physical measurement: 400 mm length (0.400 MTR)
        $scrap400 = $executionService->logScrap(
            orderId: $order->id,
            operationId: $op->id,
            productId: $this->steelPipe->id,
            quantity: 0.4,
            reason: 'Damaged / Bent',
            userId: $this->user->id,
            extra: [
                'measurement_type' => 'linear',
                'length' => 400.0,
                'pieces' => 1,
            ]
        );

        // 2. Log scrap with physical measurement: 1200 mm length (1.200 MTR)
        $scrap1200 = $executionService->logScrap(
            orderId: $order->id,
            operationId: $op->id,
            productId: $this->steelPipe->id,
            quantity: 1.2,
            reason: 'Defective Edge',
            userId: $this->user->id,
            extra: [
                'measurement_type' => 'linear',
                'length' => 1200.0,
                'pieces' => 1,
            ]
        );

        // 3. Create a reusable offcut remnant generated from this order (1500 mm length)
        $offcutRemnant = InventoryRemnant::create([
            'tenant_id' => $this->tenant->id,
            'remnant_code' => 'REM-OFFCUT-0001',
            'product_id' => $this->steelPipe->id,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_location' => 'Rack Pipe-B2',
            'measurement_type' => 'linear',
            'status' => 'available',
            'source_production_order_id' => $order->id,
            'source_production_order_operation_id' => $op->id,
            'initial_length' => 1500.0,
            'current_length' => 1500.0,
            'pieces' => 1,
            'uom_id' => $this->uomMtr->id,
            'initial_quantity' => 1.5,
            'current_quantity' => 1.5,
            'unit_cost' => 1000.0,
        ]);

        // A. Verify Production Order Show Page
        $showResp = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.orders.show', $order->id));

        $showResp->assertStatus(200);
        // Header must not say ORDERED QTY for scrap column
        $showResp->assertSee('Scrap Quantity / Dimensions');
        // Must show physical measurements in mm
        $showResp->assertSee('400.0 mm');
        $showResp->assertSee('1,200.0 mm');
        // Must show Reusable Offcuts subtab and offcut remnant code
        $showResp->assertSee('Reusable Offcuts / Remnants');
        $showResp->assertSee('REM-OFFCUT-0001');
        $showResp->assertSee('1,500.0 mm');

        // B. Verify ReportingService generateOrderDetailReport
        $reportService = app(\App\Domains\Production\Services\ReportingService::class);
        $orderReport = $reportService->generateOrderDetailReport($this->tenant->id, $order->id);

        $this->assertArrayHasKey('remnants', $orderReport);
        $this->assertCount(1, $orderReport['remnants']);
        $this->assertEquals('REM-OFFCUT-0001', $orderReport['remnants'][0]['remnant_code']);
        $this->assertEquals(1500.0, $orderReport['remnants'][0]['current_length']);

        $this->assertCount(2, $orderReport['scrap_events']);
        $this->assertEquals('linear', $orderReport['scrap_events'][0]['measurement_type']);
        $this->assertEquals(400.0, $orderReport['scrap_events'][0]['length']);
        $this->assertEquals(1200.0, $orderReport['scrap_events'][1]['length']);

        // C. Verify Order Detail Report Web View
        $orderReportView = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.intelligence.reports.show', [
                'type' => 'order-detail',
                'order_id' => $order->id,
            ]));

        $orderReportView->assertStatus(200);
        $orderReportView->assertSee('Reusable Offcuts');
        $orderReportView->assertSee('REM-OFFCUT-0001');
        $orderReportView->assertSee('400.0 mm');
        $orderReportView->assertSee('1,200.0 mm');

        // D. Verify ReportingService generateDailyProductionReport
        $dailyReport = $reportService->generateDailyProductionReport($this->tenant->id, [
            'date_start' => now()->subDay()->toDateString(),
            'date_end'   => now()->addDay()->toDateString(),
        ]);

        $this->assertArrayHasKey('offcuts', $dailyReport);
        $this->assertGreaterThanOrEqual(1, count($dailyReport['offcuts']));
        $this->assertEquals(1, $dailyReport['summary']['total_offcuts_count']);
        $this->assertEquals(1, $dailyReport['summary']['available_offcuts_count']);

        $this->assertArrayHasKey('scraps', $dailyReport);
        $this->assertCount(2, $dailyReport['scraps']);
        $this->assertEquals(2, $dailyReport['summary']['operational_scraps_count']);
        $this->assertEquals(1.60, $dailyReport['summary']['total_scrapped']);
        $this->assertEquals(1.60, $dailyReport['summary']['total_operational_scrapped']);

        // E. Verify Daily Production Report Web View
        $dailyReportView = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.intelligence.reports.show', [
                'type' => 'daily-production',
                'date_start' => now()->subDay()->toDateString(),
                'date_end'   => now()->addDay()->toDateString(),
            ]));

        $dailyReportView->assertStatus(200);
        $dailyReportView->assertSee('Operational Scrap Log Entries');
        $dailyReportView->assertSee('400.0 mm');
        $dailyReportView->assertSee('1,200.0 mm');
        $dailyReportView->assertSee('Reusable Offcuts');
        $dailyReportView->assertSee('Reusable Offcut Remnants Placed in Storage');
        $dailyReportView->assertSee('REM-OFFCUT-0001');

        // F. Verify Quality Scrap Disposals Queue (/production/quality/scrap)
        $qualityScrapView = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.scrap.index'));

        $qualityScrapView->assertStatus(200);
        $qualityScrapView->assertSee('Scrap Logs & Disposals Queue', false);
        $qualityScrapView->assertSee($order->order_number);
        $qualityScrapView->assertSee('400.0 mm');
        $qualityScrapView->assertSee('1,200.0 mm');

        // Approve one of the scrap disposals
        $disposal = \App\Domains\Production\Models\ProductionScrapDisposal::where('tenant_id', $this->tenant->id)
            ->where('status', 'pending_approval')
            ->first();
        $this->assertNotNull($disposal);

        $approveRes = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.quality.scrap.approve', $disposal->id));

        $approveRes->assertSessionHas('success');
        $disposal->refresh();
        $this->assertEquals('approved', $disposal->status);
    }
}

