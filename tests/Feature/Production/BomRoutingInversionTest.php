<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\DTO\ProductionBomItemDTO;
use App\Domains\Production\DTO\RoutingDTO;
use App\Domains\Production\DTO\RoutingOperationDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomApproval;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\RoutingService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BomRoutingInversionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $engineer;
    private Product $finishedProduct;
    private Product $otherProduct;
    private Product $rawMaterial1;
    private Product $rawMaterial2;
    private Uom $pieceUom;
    private WorkCenter $workCenter;
    private RoutingService $routingService;
    private ProductionBomService $bomService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Inversion Test Tenant',
            'slug' => 'inv-tenant-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->withHeader('X-Tenant', $this->tenant->slug);

        $this->engineer = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Production Engineer',
            'email' => 'eng-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_engineer',
        ]);

        $this->pieceUom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'status' => 'active',
        ]);

        $this->finishedProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Executive Office Desk',
            'sku' => 'FG-DESK-' . uniqid(),
            'type' => 'finished_good',
            'uom_id' => $this->pieceUom->id,
            'status' => 'active',
        ]);

        $this->otherProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bookshelf Unit',
            'sku' => 'FG-SHELF-' . uniqid(),
            'type' => 'finished_good',
            'uom_id' => $this->pieceUom->id,
            'status' => 'active',
        ]);

        $this->rawMaterial1 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Oak Wood Panel',
            'sku' => 'RM-OAK-' . uniqid(),
            'type' => 'raw_material',
            'uom_id' => $this->pieceUom->id,
            'status' => 'active',
        ]);

        $this->rawMaterial2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Bracket',
            'sku' => 'RM-BRACKET-' . uniqid(),
            'type' => 'raw_material',
            'uom_id' => $this->pieceUom->id,
            'status' => 'active',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Carpentry Workshop',
            'code' => 'WC-CARP-01',
            'status' => 'active',
        ]);

        $this->routingService = app(RoutingService::class);
        $this->bomService = app(ProductionBomService::class);
    }

    /**
     * Helper to create a standalone routing for the finished product.
     */
    private function createSampleRouting(string $name = 'Desk Routing', string $version = '1.0.0'): Routing
    {
        $dto = new RoutingDTO(
            name: $name,
            version: $version,
            effective_from: now()->toDateString(),
            operations: [
                new RoutingOperationDTO(
                    sequence: 10,
                    operation_number: 'OP-010',
                    name: 'Cutting Operation',
                    operation_type: 'manufacturing',
                    work_center_id: $this->workCenter->id,
                    setup_time_minutes: 15.0,
                    processing_time_minutes: 45.0
                ),
                new RoutingOperationDTO(
                    sequence: 20,
                    operation_number: 'OP-020',
                    name: 'Assembly Operation',
                    operation_type: 'manufacturing',
                    work_center_id: $this->workCenter->id,
                    setup_time_minutes: 10.0,
                    processing_time_minutes: 60.0
                ),
            ]
        );

        return $this->routingService->create($dto, $this->tenant->id, $this->engineer->id);
    }

    /**
     * TRACK-03: Routing can be created, submitted, and approved with zero BOM dependency.
     */
    public function test_routing_can_be_created_submitted_and_approved_without_any_bom(): void
    {
        $this->actingAs($this->engineer);

        $routing = $this->createSampleRouting('Independent Desk Routing', '1.0.0');
        $this->assertEquals(Routing::STATUS_DRAFT, $routing->status);
        $this->assertCount(2, $routing->operations);

        // Submit for approval without any BOM
        $submitted = $this->routingService->submitApproval($routing->id, $this->engineer->id);
        $this->assertEquals(Routing::STATUS_PENDING_APPROVAL, $submitted->status);

        // Approve without any BOM
        $approved = $this->routingService->approve($routing->id, $this->engineer->id);
        $this->assertEquals(Routing::STATUS_ACTIVE, $approved->status);
    }

    /**
     * TRACK-01: BOM creation requires a valid routing_id.
     */
    public function test_bom_requires_valid_routing_id(): void
    {
        $this->actingAs($this->engineer);

        $payload = [
            'bom_number' => 'BOM-TEST-001',
            'bom_name' => 'Desk BOM without routing',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProduct->id,
            // 'routing_id' missing!
            'base_quantity' => 1.0,
            'base_uom_id' => $this->pieceUom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $this->rawMaterial1->id,
                    'quantity' => 2.0,
                    'uom_id' => $this->pieceUom->id,
                    'routing_operation_id' => 99999,
                ],
            ],
        ];

        $response = $this->post(route('production.boms.store'), $payload);
        $response->assertSessionHasErrors(['routing_id']);
    }

    /**
     * TRACK-01: Decoupled Architecture: A generic Routing can be reused across different products and BOMs.
     */
    public function test_routing_can_be_reused_across_different_products_and_boms(): void
    {
        $this->actingAs($this->engineer);

        // Standalone generic routing without product dependency
        $genericRoutingDTO = new RoutingDTO(
            name: 'Standard Woodworking Process',
            version: '1.0.0',
            effective_from: now()->toDateString(),
            operations: [
                new RoutingOperationDTO(
                    sequence: 10,
                    operation_number: 'OP-010',
                    name: 'Wood Cutting Op',
                    operation_type: 'manufacturing',
                    work_center_id: $this->workCenter->id
                ),
            ]
        );
        $genericRouting = $this->routingService->create($genericRoutingDTO, $this->tenant->id, $this->engineer->id);
        $op = $genericRouting->operations->first();

        // 1. Link to Desk BOM
        $deskPayload = [
            'bom_number' => 'BOM-DESK-REUSE',
            'bom_name' => 'Desk BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProduct->id, // Desk
            'routing_id' => $genericRouting->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->pieceUom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $this->rawMaterial1->id,
                    'quantity' => 2.0,
                    'uom_id' => $this->pieceUom->id,
                    'routing_operation_id' => $op->id,
                ],
            ],
        ];

        $resp1 = $this->post(route('production.boms.store'), $deskPayload);
        $resp1->assertSessionHasNoErrors();
        $resp1->assertRedirect();

        // 2. Link same generic routing to Bookshelf BOM
        $shelfPayload = [
            'bom_number' => 'BOM-SHELF-REUSE',
            'bom_name' => 'Bookshelf BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->otherProduct->id, // Bookshelf
            'routing_id' => $genericRouting->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->pieceUom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $this->rawMaterial1->id,
                    'quantity' => 4.0,
                    'uom_id' => $this->pieceUom->id,
                    'routing_operation_id' => $op->id,
                ],
            ],
        ];

        $resp2 = $this->post(route('production.boms.store'), $shelfPayload);
        $resp2->assertSessionHasNoErrors();
        $resp2->assertRedirect();

        $this->assertCount(2, $genericRouting->fresh()->boms);
    }

    /**
     * TRACK-05: Component rows require a routing_operation_id belonging to the chosen routing.
     */
    public function test_bom_items_require_routing_operation_id_belonging_to_selected_routing(): void
    {
        $this->actingAs($this->engineer);

        $deskRouting = $this->createSampleRouting();
        $deskOp = $deskRouting->operations->first();

        // Pass invalid operation ID (99999)
        $payload = [
            'bom_number' => 'BOM-OP-TEST',
            'bom_name' => 'Invalid Op Test',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProduct->id,
            'routing_id' => $deskRouting->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->pieceUom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $this->rawMaterial1->id,
                    'quantity' => 2.0,
                    'uom_id' => $this->pieceUom->id,
                    'routing_operation_id' => 999999, // non-existent
                ],
            ],
        ];

        $response = $this->post(route('production.boms.store'), $payload);
        $response->assertSessionHasErrors(['items.0.routing_operation_id']);
    }

    /**
     * TRACK-05: Duplicate component allowed across distinct operations, but rejected in same operation.
     */
    public function test_duplicate_component_allowed_across_different_operations_but_blocked_in_same_operation(): void
    {
        $this->actingAs($this->engineer);

        $routing = $this->createSampleRouting();
        $op1 = $routing->operations[0];
        $op2 = $routing->operations[1];

        // Case A: Same material in same operation -> rejected
        $badPayload = [
            'bom_number' => 'BOM-DUP-SAME',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProduct->id,
            'routing_id' => $routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->pieceUom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $this->rawMaterial1->id,
                    'routing_operation_id' => $op1->id,
                    'quantity' => 2.0,
                    'uom_id' => $this->pieceUom->id,
                ],
                [
                    'material_id' => $this->rawMaterial1->id, // duplicate for op1
                    'routing_operation_id' => $op1->id,
                    'quantity' => 3.0,
                    'uom_id' => $this->pieceUom->id,
                ],
            ],
        ];

        $res1 = $this->post(route('production.boms.store'), $badPayload);
        $res1->assertSessionHasErrors(['items.1.material_id']);

        // Case B: Same material split across Op 1 and Op 2 -> accepted!
        $goodPayload = [
            'bom_number' => 'BOM-SPLIT-OK',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProduct->id,
            'routing_id' => $routing->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->pieceUom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'material_id' => $this->rawMaterial1->id,
                    'routing_operation_id' => $op1->id, // 2 panels at cutting
                    'quantity' => 2.0,
                    'uom_id' => $this->pieceUom->id,
                ],
                [
                    'material_id' => $this->rawMaterial1->id, // 1 panel at assembly
                    'routing_operation_id' => $op2->id,
                    'quantity' => 1.0,
                    'uom_id' => $this->pieceUom->id,
                ],
            ],
        ];

        $res2 = $this->post(route('production.boms.store'), $goodPayload);
        $res2->assertSessionHasNoErrors();
        $this->assertDatabaseHas('production_boms', ['bom_number' => 'BOM-SPLIT-OK']);
    }

    /**
     * TRACK-02: Saving a BOM populates RoutingOperationMaterial with bom_id set.
     */
    public function test_saving_bom_syncs_records_to_production_routing_operation_materials_with_bom_id(): void
    {
        $this->actingAs($this->engineer);

        $routing = $this->createSampleRouting();
        $op1 = $routing->operations[0];
        $op2 = $routing->operations[1];

        $dto = new ProductionBomDTO(
            bom_number: 'BOM-SYNC-TEST',
            bom_name: 'Sync Routing Operation Materials Test',
            bom_type: 'manufacturing',
            product_id: $this->finishedProduct->id,
            routing_id: $routing->id,
            base_quantity: 1.0,
            base_uom_id: $this->pieceUom->id,
            version: '1.0.0',
            effective_date: now()->toDateString(),
            items: [
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial1->id,
                    quantity: 4.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $op1->id
                ),
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial2->id,
                    quantity: 12.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $op2->id
                ),
            ]
        );

        $bom = $this->bomService->create($dto, $this->engineer->id);

        $this->assertDatabaseHas('production_routing_operation_materials', [
            'bom_id' => $bom->id,
            'routing_operation_id' => $op1->id,
            'material_id' => $this->rawMaterial1->id,
            'quantity' => 4.0,
        ]);

        $this->assertDatabaseHas('production_routing_operation_materials', [
            'bom_id' => $bom->id,
            'routing_operation_id' => $op2->id,
            'material_id' => $this->rawMaterial2->id,
            'quantity' => 12.0,
        ]);
    }

    /**
     * TRACK-06: BOM cannot be approved unless its linked Routing is approved or active.
     */
    public function test_bom_cannot_be_approved_unless_linked_routing_is_approved_or_active(): void
    {
        $this->actingAs($this->engineer);

        $draftRouting = $this->createSampleRouting('Draft Routing Only');
        $op1 = $draftRouting->operations[0];

        $dto = new ProductionBomDTO(
            bom_number: 'BOM-GATE-TEST',
            bom_name: 'Approval Gate Test',
            bom_type: 'manufacturing',
            product_id: $this->finishedProduct->id,
            routing_id: $draftRouting->id,
            base_quantity: 1.0,
            base_uom_id: $this->pieceUom->id,
            version: '1.0.0',
            effective_date: now()->toDateString(),
            items: [
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial1->id,
                    quantity: 1.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $op1->id
                ),
            ]
        );

        $bom = $this->bomService->create($dto, $this->engineer->id);
        $this->bomService->submitApproval($bom->id);

        // Attempt to approve BOM while routing is still in 'draft' status
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The linked Routing must be approved and active first');

        $this->bomService->approve($bom->id, $this->engineer->id);
    }

    /**
     * TRACK-06: Decoupled routing lifecycle: Multiple parallel active routings are permitted.
     * Approving Routing v2 does NOT auto-retire Routing v1, and existing BOMs stay active.
     */
    public function test_approving_new_routing_keeps_existing_active_routings_and_boms_parallel(): void
    {
        $this->actingAs($this->engineer);

        // 1. Create and approve Routing v1
        $routingV1 = $this->createSampleRouting('Desk Routing V1', '1.0.0');
        $this->routingService->submitApproval($routingV1->id, $this->engineer->id);
        $routingV1 = $this->routingService->approve($routingV1->id, $this->engineer->id);
        $this->assertEquals(Routing::STATUS_ACTIVE, $routingV1->status);

        // 2. Create and approve BOM v1 linked to Routing v1
        $dto = new ProductionBomDTO(
            bom_number: 'BOM-CASCADE-01',
            bom_name: 'BOM V1',
            bom_type: 'manufacturing',
            product_id: $this->finishedProduct->id,
            routing_id: $routingV1->id,
            base_quantity: 1.0,
            base_uom_id: $this->pieceUom->id,
            version: '1.0.0',
            effective_date: now()->toDateString(),
            items: [
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial1->id,
                    quantity: 2.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $routingV1->operations[0]->id
                ),
            ]
        );
        $bom = $this->bomService->create($dto, $this->engineer->id);
        $this->bomService->submitApproval($bom->id);
        $bom = $this->bomService->approve($bom->id, $this->engineer->id);
        $this->assertEquals(ProductionBom::STATUS_APPROVED, $bom->status);

        // 3. Create and approve Routing v2
        $routingV2 = $this->createSampleRouting('Desk Routing V2', '2.0.0');
        $this->routingService->submitApproval($routingV2->id, $this->engineer->id);
        $this->routingService->approve($routingV2->id, $this->engineer->id);

        // 4. In decoupled architecture: Routing v1 remains ACTIVE, and BOM remains APPROVED/ACTIVE
        $routingV1->refresh();
        $this->assertEquals(Routing::STATUS_ACTIVE, $routingV1->status);

        $bom->refresh();
        $this->assertEquals(ProductionBom::STATUS_APPROVED, $bom->status);
    }

    /**
     * TRACK-06: Cancelling a Routing inactivates active BOMs and attaches warnings to draft BOMs.
     */
    public function test_cancelling_routing_inactivates_connected_active_boms_and_warns_draft_boms(): void
    {
        $this->actingAs($this->engineer);

        $routing = $this->createSampleRouting('Desk Routing To Cancel');
        $this->routingService->submitApproval($routing->id, $this->engineer->id);
        $routing = $this->routingService->approve($routing->id, $this->engineer->id);

        // Active BOM
        $activeDto = new ProductionBomDTO(
            bom_number: 'BOM-ACT-CANCEL',
            bom_type: 'manufacturing',
            product_id: $this->finishedProduct->id,
            routing_id: $routing->id,
            base_quantity: 1.0,
            base_uom_id: $this->pieceUom->id,
            version: '1.0.0',
            effective_date: now()->toDateString(),
            items: [
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial1->id,
                    quantity: 2.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $routing->operations[0]->id
                ),
            ]
        );
        $activeBom = $this->bomService->create($activeDto, $this->engineer->id);
        $this->bomService->submitApproval($activeBom->id);
        $activeBom = $this->bomService->approve($activeBom->id, $this->engineer->id);

        // Draft BOM
        $draftDto = new ProductionBomDTO(
            bom_number: 'BOM-DFT-CANCEL',
            bom_type: 'manufacturing',
            product_id: $this->finishedProduct->id,
            routing_id: $routing->id,
            base_quantity: 1.0,
            base_uom_id: $this->pieceUom->id,
            version: '2.0.0',
            effective_date: now()->toDateString(),
            items: [
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial2->id,
                    quantity: 4.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $routing->operations[1]->id
                ),
            ]
        );
        $draftBom = $this->bomService->create($draftDto, $this->engineer->id);

        // Cancel routing
        $this->routingService->cancel($routing->id, $this->engineer->id, 'Discontinued process line.');

        $activeBom->refresh();
        $this->assertEquals(ProductionBom::STATUS_INACTIVE, $activeBom->status);
        $this->assertTrue($activeBom->has_warning);

        $draftBom->refresh();
        $this->assertEquals(ProductionBom::STATUS_DRAFT, $draftBom->status);
        $this->assertTrue($draftBom->has_warning);
    }

    /**
     * TRACK-10: Duplicating a BOM clones routing_id and routing_operation_id.
     */
    public function test_duplicating_bom_clones_routing_id_and_routing_operation_id(): void
    {
        $this->actingAs($this->engineer);

        $routing = $this->createSampleRouting();
        $op = $routing->operations[0];

        $dto = new ProductionBomDTO(
            bom_number: 'BOM-CLONE-SRC',
            bom_type: 'manufacturing',
            product_id: $this->finishedProduct->id,
            routing_id: $routing->id,
            base_quantity: 1.0,
            base_uom_id: $this->pieceUom->id,
            version: '1.0.0',
            effective_date: now()->toDateString(),
            items: [
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial1->id,
                    quantity: 5.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $op->id
                ),
            ]
        );

        $srcBom = $this->bomService->create($dto, $this->engineer->id);
        $this->bomService->submitApproval($srcBom->id);

        $clonedBom = $this->bomService->duplicateVersion($srcBom->id, '1.1.0', $this->engineer->id);

        $this->assertEquals($routing->id, $clonedBom->routing_id);
        $this->assertCount(1, $clonedBom->items);
        $this->assertEquals($op->id, $clonedBom->operationMaterials->first()->routing_operation_id);

        // Assert RoutingOperationMaterial was populated for the cloned BOM
        $this->assertDatabaseHas('production_routing_operation_materials', [
            'bom_id' => $clonedBom->id,
            'routing_operation_id' => $op->id,
            'material_id' => $this->rawMaterial1->id,
            'quantity' => 5.0,
        ]);
    }

    public function test_two_tier_storage_aggregates_master_bom_items_and_splits_operation_materials(): void
    {
        $this->actingAs($this->engineer);

        $routing = $this->createSampleRouting('2-Tier Routing', '2.0.0');
        $this->routingService->submitApproval($routing->id, $this->engineer->id);
        $routing = $this->routingService->approve($routing->id, $this->engineer->id);
        $routing->load('operations');

        $op10 = $routing->operations[0];
        $op20 = $routing->operations[1];

        $dto = new ProductionBomDTO(
            bom_number: 'BOM-2TIER-001',
            bom_type: 'manufacturing',
            product_id: $this->finishedProduct->id,
            routing_id: $routing->id,
            base_quantity: 1.0,
            base_uom_id: $this->pieceUom->id,
            version: '1.0.0',
            effective_date: now()->toDateString(),
            items: [
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial1->id,
                    quantity: 4.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $op10->id,
                    row_uid: 'row_mat1_op10'
                ),
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial1->id,
                    quantity: 4.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $op20->id,
                    row_uid: 'row_mat1_op20'
                ),
                new ProductionBomItemDTO(
                    material_id: $this->rawMaterial2->id,
                    quantity: 4.0,
                    uom_id: $this->pieceUom->id,
                    routing_operation_id: $op20->id,
                    is_alternative: true,
                    replaces_row_uid: 'row_mat1_op20',
                    priority: 1,
                    row_uid: 'row_mat2_op20_alt'
                ),
            ]
        );

        $bom = $this->bomService->create($dto, $this->engineer->id);

        // 1. Assert Table 1 (production_bom_items): 1 aggregated row per unique material
        $bomItems = $bom->items()->get();
        $this->assertCount(2, $bomItems); // Only 2 unique materials (Material 1 and Material 2)

        $bomItem1 = $bomItems->where('material_id', $this->rawMaterial1->id)->first();
        $this->assertNotNull($bomItem1);
        $this->assertEquals(8.0, $bomItem1->quantity); // 4 + 4 = 8
        $this->assertEquals('primary', $bomItem1->allocation_role);

        $bomItem2 = $bomItems->where('material_id', $this->rawMaterial2->id)->first();
        $this->assertNotNull($bomItem2);
        $this->assertEquals(4.0, $bomItem2->quantity);
        $this->assertEquals('alternative', $bomItem2->allocation_role);

        // 2. Assert Table 2 (production_routing_operation_materials): 3 workstation allocations
        $opMats = $bom->operationMaterials()->get();
        $this->assertCount(3, $opMats);

        $op10Mat = $opMats->where('routing_operation_id', $op10->id)->where('material_id', $this->rawMaterial1->id)->first();
        $this->assertNotNull($op10Mat);
        $this->assertEquals(4.0, $op10Mat->quantity);
        $this->assertFalse($op10Mat->is_alternative);
        $this->assertNull($op10Mat->priority); // Strictly null for primary
        $this->assertEquals($bomItem1->id, $op10Mat->bom_item_id);

        $op20Primary = $opMats->where('routing_operation_id', $op20->id)->where('material_id', $this->rawMaterial1->id)->first();
        $this->assertNotNull($op20Primary);
        $this->assertEquals(4.0, $op20Primary->quantity);
        $this->assertFalse($op20Primary->is_alternative);
        $this->assertNull($op20Primary->priority);
        $this->assertEquals($bomItem1->id, $op20Primary->bom_item_id);

        $op20Alt = $opMats->where('routing_operation_id', $op20->id)->where('material_id', $this->rawMaterial2->id)->first();
        $this->assertNotNull($op20Alt);
        $this->assertEquals(4.0, $op20Alt->quantity);
        $this->assertTrue($op20Alt->is_alternative);
        $this->assertEquals(1, $op20Alt->priority);
        $this->assertEquals($bomItem2->id, $op20Alt->bom_item_id);
        $this->assertEquals($op20Primary->id, $op20Alt->replaces_routing_material_id);
    }
}

