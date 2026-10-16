<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionReadinessService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingMaterialAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $engineer;
    private Product $finishedProduct;
    private Product $fastenerProduct;
    private Product $legProduct;
    private Product $unrelatedProduct;
    private Uom $pieceUom;
    private ProductionBom $bom;
    private WorkCenter $workCenter;
    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Manufacturing Tenant',
            'slug' => 'mfg-tenant-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->withHeader('X-Tenant', $this->tenant->slug);

        $this->engineer = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lead Engineer',
            'email' => 'engineer-' . uniqid() . '@example.com',
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
            'name' => 'Dining Table',
            'sku' => 'FG-TABLE-' . uniqid(),
            'type' => 'finished_good',
            'uom_id' => $this->pieceUom->id,
            'status' => 'active',
        ]);

        $this->fastenerProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'M8 Bolt Fastener',
            'sku' => 'RM-FASTENER-' . uniqid(),
            'type' => 'raw_material',
            'uom_id' => $this->pieceUom->id,
            'status' => 'active',
        ]);

        $this->legProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Table Wooden Leg',
            'sku' => 'RM-LEG-' . uniqid(),
            'type' => 'raw_material',
            'uom_id' => $this->pieceUom->id,
            'status' => 'active',
        ]);

        $this->unrelatedProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Unrelated Plastic Cap',
            'sku' => 'RM-CAP-' . uniqid(),
            'type' => 'raw_material',
            'uom_id' => $this->pieceUom->id,
            'status' => 'active',
        ]);

        // BOM: Base Qty = 10 Dining Tables
        // Components: 400 Fasteners (40/table) + 40 Legs (4/table)
        $this->bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-TABLE-10',
            'bom_name' => 'Dining Table 10-Unit Batch BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProduct->id,
            'base_quantity' => 10.0,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $this->bom->id,
            'material_id' => $this->fastenerProduct->id,
            'quantity' => 400.0,
            'uom_id' => $this->pieceUom->id,
            'sequence' => 1,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $this->bom->id,
            'material_id' => $this->legProduct->id,
            'quantity' => 40.0,
            'uom_id' => $this->pieceUom->id,
            'sequence' => 2,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Bay 1',
            'code' => 'WC-ASSY-01',
            'status' => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'Assembly Rig A',
            'code' => 'MCH-RIG-A',
            'status' => 'active',
        ]);
    }

    public function test_saves_and_reloads_routing_in_per_unit_mode_with_actual_quantities(): void
    {
        $payload = [
            'name' => 'Table Assembly Flow Per Unit',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'material_quantity_mode' => Routing::MODE_PER_UNIT,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Leg Attachment',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'machine_id' => $this->machine->id,
                    'setup_time_minutes' => 10,
                    'processing_time_minutes' => 15,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 20.0, // 20 per unit
                            'uom_id' => $this->pieceUom->id,
                        ],
                        [
                            'material_id' => $this->legProduct->id,
                            'quantity' => 4.0, // 4 per unit
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
                [
                    'sequence' => 20,
                    'name' => 'Under-frame Fastening',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'machine_id' => $this->machine->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 10,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 15.0, // 15 per unit
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->engineer)
            ->post(route('production.routing.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('routings', [
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'material_quantity_mode' => 'per_unit',
        ]);

        $routing = Routing::where('product_id', $this->finishedProduct->id)->firstOrFail();
        $this->assertEquals(Routing::MODE_PER_UNIT, $routing->material_quantity_mode);
        $this->assertTrue($routing->isPerUnit());

        $op1 = $routing->operations()->where('sequence', 10)->firstOrFail();
        $op1Materials = $op1->materials()->get();
        $this->assertCount(2, $op1Materials);

        // Verify quantities are NOT hardcoded 1.0
        $op1Fasteners = $op1Materials->firstWhere('material_id', $this->fastenerProduct->id);
        $this->assertNotNull($op1Fasteners);
        $this->assertEquals(20.0, (float) $op1Fasteners->quantity);

        $op1Legs = $op1Materials->firstWhere('material_id', $this->legProduct->id);
        $this->assertNotNull($op1Legs);
        $this->assertEquals(4.0, (float) $op1Legs->quantity);

        $op2 = $routing->operations()->where('sequence', 20)->firstOrFail();
        $op2Materials = $op2->materials()->get();
        $this->assertCount(1, $op2Materials);
        $this->assertEquals(15.0, (float) $op2Materials->first()->quantity);
    }

    public function test_saves_and_reloads_routing_in_per_bom_base_quantity_mode(): void
    {
        $payload = [
            'name' => 'Table Assembly Flow Per BOM Base',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'material_quantity_mode' => Routing::MODE_PER_BOM_BASE_QUANTITY,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Leg Attachment',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 10,
                    'processing_time_minutes' => 15,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 200.0, // 200 per 10 tables (= 20/table)
                            'uom_id' => $this->pieceUom->id,
                        ],
                        [
                            'material_id' => $this->legProduct->id,
                            'quantity' => 40.0, // 40 per 10 tables (= 4/table)
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
                [
                    'sequence' => 20,
                    'name' => 'Frame Bolting',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 10,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 150.0, // 150 per 10 tables (= 15/table)
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->engineer)
            ->post(route('production.routing.store'), $payload);

        $response->assertRedirect();
        $routing = Routing::where('product_id', $this->finishedProduct->id)->firstOrFail();
        $this->assertEquals(Routing::MODE_PER_BOM_BASE_QUANTITY, $routing->material_quantity_mode);
        $this->assertTrue($routing->isPerBomBaseQuantity());

        $op1 = $routing->operations()->where('sequence', 10)->firstOrFail();
        $op1Fasteners = $op1->materials()->where('material_id', $this->fastenerProduct->id)->first();
        $this->assertEquals(200.0, (float) $op1Fasteners->quantity);
    }

    public function test_rule_a_blocks_saving_if_a_bom_component_is_not_assigned_to_any_operation(): void
    {
        // BOM requires Fasteners (400) AND Legs (40).
        // Here we only assign Fasteners. Legs is unassigned.
        $payload = [
            'name' => 'Table Incomplete Allocation',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'material_quantity_mode' => Routing::MODE_PER_UNIT,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Leg Attachment',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 10,
                    'processing_time_minutes' => 15,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 20.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                        // Missing legProduct!
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->engineer)
            ->post(route('production.routing.store'), $payload);

        // Must fail validation and mention the missing component name
        $response->assertSessionHasErrors('operations');
        $errors = session('errors')->get('operations');
        $errorMessage = implode(' ', $errors);
        $this->assertStringContainsString('Every BOM component must be assigned', $errorMessage);
        $this->assertStringContainsString($this->legProduct->name, $errorMessage);
    }

    public function test_rule_b_permits_partial_allocation_with_warning(): void
    {
        // Fasteners BOM norm is 40/unit, Legs BOM norm is 4/unit.
        // We allocate 20 Fasteners (leaving 20 unallocated) and 4 Legs (fully allocated).
        $payload = [
            'name' => 'Table Partial Allocation',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'material_quantity_mode' => Routing::MODE_PER_UNIT,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Leg Attachment',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 10,
                    'processing_time_minutes' => 15,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 20.0, // 20 of 40 allocated
                            'uom_id' => $this->pieceUom->id,
                        ],
                        [
                            'material_id' => $this->legProduct->id,
                            'quantity' => 4.0, // 4 of 4 allocated
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->engineer)
            ->post(route('production.routing.store'), $payload);

        $response->assertRedirect();
        // Warning should be placed in session
        $response->assertSessionHas('warning');
        $warning = session('warning');
        $this->assertStringContainsString('Partially Allocated Components', $warning);
        $this->assertStringContainsString($this->fastenerProduct->name, $warning);
    }

    public function test_rule_c_blocks_over_allocation_across_operations_in_per_unit_mode(): void
    {
        // Fastener normalized BOM requirement: 400 / 10 = 40 per unit.
        // Op 10 allocates 25, Op 20 allocates 20 -> Total 45 per unit (exceeds 40 by 5).
        $payload = [
            'name' => 'Table Over-allocated Per Unit',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'material_quantity_mode' => Routing::MODE_PER_UNIT,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Op 1',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 5,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 25.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                        [
                            'material_id' => $this->legProduct->id,
                            'quantity' => 4.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
                [
                    'sequence' => 20,
                    'name' => 'Op 2',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 5,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 20.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->engineer)
            ->post(route('production.routing.store'), $payload);

        $response->assertSessionHasErrors('operations');
        $errorMessage = implode(' ', session('errors')->get('operations'));
        $this->assertStringContainsString('is over-allocated', $errorMessage);
        $this->assertStringContainsString($this->fastenerProduct->name, $errorMessage);
        $this->assertStringContainsString('exceeds by', $errorMessage);
    }

    public function test_rule_c_blocks_over_allocation_across_operations_in_per_bom_base_quantity_mode(): void
    {
        // BOM requirement: 400 fasteners for 10 tables.
        // Op 10 allocates 250, Op 20 allocates 180 -> Total 430 (> 400).
        $payload = [
            'name' => 'Table Over-allocated Per BOM Base',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'material_quantity_mode' => Routing::MODE_PER_BOM_BASE_QUANTITY,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Op 1',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 5,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 250.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                        [
                            'material_id' => $this->legProduct->id,
                            'quantity' => 40.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
                [
                    'sequence' => 20,
                    'name' => 'Op 2',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 5,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 180.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->engineer)
            ->post(route('production.routing.store'), $payload);

        $response->assertSessionHasErrors('operations');
        $errorMessage = implode(' ', session('errors')->get('operations'));
        $this->assertStringContainsString('is over-allocated', $errorMessage);
        $this->assertStringContainsString($this->fastenerProduct->name, $errorMessage);
    }

    public function test_rejects_material_not_belonging_to_selected_bom(): void
    {
        $payload = [
            'name' => 'Table Foreign Material',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'material_quantity_mode' => Routing::MODE_PER_UNIT,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Op 1',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 5,
                    'expected_yield_percentage' => 100.00,
                    'materials' => [
                        [
                            'material_id' => $this->unrelatedProduct->id, // NOT in selected BOM!
                            'quantity' => 5.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                        [
                            'material_id' => $this->fastenerProduct->id,
                            'quantity' => 40.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                        [
                            'material_id' => $this->legProduct->id,
                            'quantity' => 4.0,
                            'uom_id' => $this->pieceUom->id,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->engineer)
            ->post(route('production.routing.store'), $payload);

        $response->assertSessionHasErrors('operations');
    }

    public function test_production_order_operation_target_scales_using_saved_routing_allocations(): void
    {
        // 1. Create a routing with per_unit mode
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'routing_number' => 'RT-TABLE-UNIT-01',
            'name' => 'Table Unit Routing',
            'version' => '1.0.0',
            'status' => Routing::STATUS_ACTIVE,
            'material_quantity_mode' => Routing::MODE_PER_UNIT,
            'created_by' => $this->engineer->id,
        ]);

        $op1 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Leg Fastening',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 5,
            'processing_time_minutes' => 10,
            'expected_yield_percentage' => 100.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op1->id,
            'material_id' => $this->fastenerProduct->id,
            'quantity' => 20.0, // 20 fasteners per table
            'uom_id' => $this->pieceUom->id,
            'consumption_type' => 'variable',
            'sequence' => 1,
        ]);

        $op2 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Final Bolting',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 5,
            'processing_time_minutes' => 10,
            'expected_yield_percentage' => 100.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op2->id,
            'material_id' => $this->fastenerProduct->id,
            'quantity' => 15.0, // 15 fasteners per table
            'uom_id' => $this->pieceUom->id,
            'consumption_type' => 'variable',
            'sequence' => 1,
        ]);

        // Create Production Order for 5 tables
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-TABLE-5',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 5.0,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $orderService = app(ProductionOrderService::class);
        $orderService->snapshotMultiLevelRoutings($order, $this->bom, $routing, (float) $order->quantity_ordered, $this->tenant->id, $this->engineer->id);

        $orderOps = $order->operations()->orderBy('sequence')->get();
        $this->assertCount(2, $orderOps);

        // Op 10 expected target: 20 per table * 5 tables = 100 fasteners!
        $this->assertEquals(100.0, (float) $orderOps[0]->target_produced_qty);

        // Op 20 expected target: 15 per table * 5 tables = 75 fasteners!
        $this->assertEquals(75.0, (float) $orderOps[1]->target_produced_qty);

        // 2. Test now with per_bom_base_quantity mode
        $routingBom = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'routing_number' => 'RT-TABLE-BOM-01',
            'name' => 'Table BOM Base Routing',
            'version' => '1.0.0',
            'status' => Routing::STATUS_ACTIVE,
            'material_quantity_mode' => Routing::MODE_PER_BOM_BASE_QUANTITY,
            'created_by' => $this->engineer->id,
        ]);

        $opBom1 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routingBom->id,
            'sequence' => 10,
            'operation_number' => 'OP-10-BOM',
            'name' => 'Leg Fastening BOM Base',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 5,
            'processing_time_minutes' => 10,
            'expected_yield_percentage' => 100.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $opBom1->id,
            'material_id' => $this->fastenerProduct->id,
            'quantity' => 200.0, // 200 per 10 tables
            'uom_id' => $this->pieceUom->id,
            'consumption_type' => 'variable',
            'sequence' => 1,
        ]);

        // Order for 25 tables
        $order25 = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-TABLE-25',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $routingBom->id,
            'quantity_ordered' => 25.0,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $orderService->snapshotMultiLevelRoutings($order25, $this->bom, $routingBom, (float) $order25->quantity_ordered, $this->tenant->id, $this->engineer->id);
        $order25Ops = $order25->operations()->orderBy('sequence')->get();

        // Expected: (200 / 10) * 25 = 500 fasteners!
        $this->assertEquals(500.0, (float) $order25Ops[0]->target_produced_qty);
    }

    public function test_production_readiness_uses_operation_specific_material_requirements(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Materials Warehouse',
            'code' => 'WH-MAIN-01',
            'status' => 'active',
        ]);

        // Stock: 500 Fasteners available, 0 Legs available
        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->fastenerProduct->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 500.0,
            'available_qty' => 500.0,
            'reserved_qty' => 0.0,
        ]);

        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->legProduct->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0.0,
            'available_qty' => 0.0,
            'reserved_qty' => 0.0,
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'routing_number' => 'RT-READINESS-01',
            'name' => 'Readiness Test Routing',
            'version' => '1.0.0',
            'status' => Routing::STATUS_ACTIVE,
            'material_quantity_mode' => Routing::MODE_PER_UNIT,
            'created_by' => $this->engineer->id,
        ]);

        // Op 10 ONLY consumes Fasteners (20 per unit)
        $op1 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Fastener Assembly',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 5,
            'processing_time_minutes' => 10,
            'expected_yield_percentage' => 100.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op1->id,
            'material_id' => $this->fastenerProduct->id,
            'quantity' => 20.0,
            'uom_id' => $this->pieceUom->id,
            'consumption_type' => 'variable',
            'sequence' => 1,
        ]);

        // Op 20 ONLY consumes Legs (4 per unit)
        $op2 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Leg Assembly',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 5,
            'processing_time_minutes' => 10,
            'expected_yield_percentage' => 100.0,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op2->id,
            'material_id' => $this->legProduct->id,
            'quantity' => 4.0,
            'uom_id' => $this->pieceUom->id,
            'consumption_type' => 'variable',
            'sequence' => 1,
        ]);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-READINESS-10',
            'product_id' => $this->finishedProduct->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10.0,
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        // Create reservations for the order
        \App\Domains\Production\Models\ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->fastenerProduct->id,
            'quantity_planned' => 200.0,
            'quantity_reserved' => 0.0,
            'quantity_issued' => 0.0,
            'uom_id' => $this->pieceUom->id,
        ]);

        \App\Domains\Production\Models\ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->legProduct->id,
            'quantity_planned' => 40.0,
            'quantity_reserved' => 0.0,
            'quantity_issued' => 0.0,
            'uom_id' => $this->pieceUom->id,
        ]);

        $orderOp1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $op1->id,
            'source_routing_id' => $routing->id,
            'source_bom_id' => $this->bom->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Fastener Assembly',
            'work_center_id' => $this->workCenter->id,
            'status' => 'pending',
            'planned_setup_time_minutes' => 5,
            'planned_run_time_minutes' => 10,
        ]);

        $orderOp2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $op2->id,
            'source_routing_id' => $routing->id,
            'source_bom_id' => $this->bom->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Leg Assembly',
            'work_center_id' => $this->workCenter->id,
            'status' => 'pending',
            'planned_setup_time_minutes' => 5,
            'planned_run_time_minutes' => 10,
        ]);

        $readinessService = app(ProductionReadinessService::class);

        // Op 10 readiness: Only needs 20 * 10 = 200 Fasteners. 500 in stock.
        // Fasteners are available -> Op 10 material dimension should be READY!
        $readinessOp1 = $readinessService->evaluateOperationReadiness($orderOp1);
        $this->assertEquals(ProductionReadinessService::STATUS_READY, $readinessOp1['material']['status']);

        // Op 20 readiness: Needs 4 * 10 = 40 Legs. 0 in stock.
        // Legs out of stock -> Op 20 material dimension should be BLOCKED!
        $readinessOp2 = $readinessService->evaluateOperationReadiness($orderOp2);
        $this->assertEquals(ProductionReadinessService::STATUS_BLOCKED, $readinessOp2['material']['status']);
        $this->assertNotEmpty($readinessOp2['material']['blockers']);
    }
}
