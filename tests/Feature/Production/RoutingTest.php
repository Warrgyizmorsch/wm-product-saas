<?php

namespace Tests\Feature\Production;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\ProductionBom;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private User $adminA;
    private User $engineerA;
    private Product $finishedProductA;
    private WorkCenter $workCenterA;
    private Machine $machineA;

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

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin Manager A',
            'email' => 'manager.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_manager', // can approve, submit, cancel
        ]);

        $this->engineerA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Engineer A',
            'email' => 'engineer.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_engineer', // can create/submit, but cannot approve
        ]);

        $this->finishedProductA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Premium Cabinet Model Z',
            'sku' => 'FG-CABINET-Z',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-CABINET-Z',
            'bom_name' => 'Cabinet Z BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProductA->id,
            'base_quantity' => 1.0,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
        ]);

        $this->workCenterA = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Main Cutting Area',
            'code' => 'WC-CUT-01',
            'status' => 'active',
        ]);

        $this->machineA = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Saw Machine A',
            'code' => 'MCH-SAW-A',
            'status' => 'active',
        ]);
    }

    public function test_routing_creation_with_operations(): void
    {
        $payload = [
            'name' => 'Cabinet Z Cutting and Drilling',
            'product_id' => $this->finishedProductA->id,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'is_default' => '1',
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Raw sheet cutting',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'machine_id' => $this->machineA->id,
                    'setup_time_minutes' => 15,
                    'processing_time_minutes' => 5,
                    'expected_yield_percentage' => 98.00,
                    'overlap_enabled' => 1,
                    'transfer_batch_quantity' => 10,
                ],
                [
                    'sequence' => 20,
                    'name' => 'Hole drilling',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                    'machine_id' => null,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 2,
                    'expected_yield_percentage' => 100.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.store'), $payload);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('routings', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cabinet Z Cutting and Drilling',
            'product_id' => $this->finishedProductA->id,
            'status' => Routing::STATUS_DRAFT,
        ]);

        $routing = Routing::first();
        $this->assertCount(2, $routing->operations);
        $this->assertEquals(98.00, $routing->operations[0]->expected_yield_percentage);
        $this->assertTrue((bool) $routing->operations[0]->overlap_enabled);
        $this->assertEquals(10.00, $routing->operations[0]->transfer_batch_quantity);
    }

    public function test_validation_rejects_duplicate_sequences(): void
    {
        $payload = [
            'name' => 'Invalid Routing Seq',
            'product_id' => $this->finishedProductA->id,
            'version' => '1.0.0',
            'effective_from' => now()->toDateString(),
            'operations' => [
                [
                    'sequence' => 10, // Duplicate sequence
                    'name' => 'Cut 1',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                ],
                [
                    'sequence' => 10, // Duplicate sequence
                    'name' => 'Cut 2',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenterA->id,
                ]
            ]
        ];

        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.store'), $payload);

        $response->assertSessionHasErrors(['operations.1.sequence']);
    }

    public function test_approval_workflow_permissions_and_state_transitions(): void
    {
        // 1. Setup a routing draft with 1 operation
        $routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-2026-000001',
            'name' => 'Cabinet Z Assembly',
            'product_id' => $this->finishedProductA->id,
            'version' => '1.0.0',
            'status' => Routing::STATUS_DRAFT,
            'effective_from' => now()->toDateString(),
        ]);

        $routing->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Main Assembly',
            'work_center_id' => $this->workCenterA->id,
        ]);

        // 2. Engineer submits for approval
        $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.submit', $routing->id));

        $routing->refresh();
        $this->assertTrue($routing->isPendingApproval());

        // 3. Engineer tries to approve but lacks permission (engineer cannot approve)
        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.approve', $routing->id));
        $response->assertStatus(403); // Unauthorized

        // 4. Manager approves routing
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.approve', $routing->id));
        $response->assertRedirect();

        $routing->refresh();
        $this->assertTrue($routing->isActive());
    }

    public function test_routing_duplication_creates_new_revision(): void
    {
        // Create an active routing version
        $routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-2026-000001',
            'name' => 'Dupl Base Routing',
            'product_id' => $this->finishedProductA->id,
            'version' => '1.0.0',
            'revision' => 0,
            'status' => Routing::STATUS_ACTIVE,
            'effective_from' => now()->toDateString(),
        ]);

        $op = $routing->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Operation 1',
            'work_center_id' => $this->workCenterA->id,
        ]);

        $uom = \App\Domains\Inventory\Models\Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Piece',
            'code' => 'Pcs',
        ]);

        $rawMaterial = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Raw Wood Board',
            'sku' => 'RM-WOOD-01',
            'type' => 'raw_material',
            'uom_id' => $uom->id,
            'status' => 'active',
        ]);

        $op->materials()->create([
            'tenant_id' => $this->tenantA->id,
            'material_id' => $rawMaterial->id,
            'quantity' => 2.5,
            'uom_id' => $uom->id,
            'sequence' => 1,
        ]);

        // Duplicate via manager
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.duplicate', $routing->id), [
                'new_version' => '1.1.0'
            ]);

        $response->assertRedirect();
        
        $newRouting = Routing::where('version', '1.1.0')->first();
        $this->assertNotNull($newRouting);
        $this->assertNotNull($newRouting->routing_number);
        $this->assertNotEmpty($newRouting->routing_number);
        $this->assertEquals(Routing::STATUS_DRAFT, $newRouting->status);
        $this->assertEquals(1, $newRouting->revision);
        $this->assertCount(1, $newRouting->operations);
        $this->assertEquals('Operation 1', $newRouting->operations[0]->name);
        $this->assertCount(1, $newRouting->operations[0]->materials);
        $this->assertEquals($rawMaterial->id, $newRouting->operations[0]->materials[0]->material_id);
        $this->assertEquals(2.5, (float) $newRouting->operations[0]->materials[0]->quantity);

        // Verify update on cloned routing maintains routing_number
        $updateResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.routing.update', $newRouting->id), [
                'routing_number' => $newRouting->routing_number,
                'name' => 'Updated Duplicated Routing',
                'product_id' => $this->finishedProductA->id,
                'version' => '1.1.0',
                'effective_from' => now()->toDateString(),
                'operations' => [
                    [
                        'sequence' => 10,
                        'name' => 'Operation 1 Modified',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $this->workCenterA->id,
                    ]
                ]
            ]);

        $updateResponse->assertRedirect();
        $newRouting->refresh();
        $this->assertNotNull($newRouting->routing_number);
        $this->assertNotEmpty($newRouting->routing_number);
        $this->assertEquals('Updated Duplicated Routing', $newRouting->name);
    }

    public function test_bulk_action_routing_processing(): void
    {
        // 1. Setup multiple routing drafts for different products so both can be active
        $finishedProductB = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Premium Cabinet Model Y',
            'sku' => 'FG-CABINET-Y',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-CABINET-Y',
            'bom_name' => 'Cabinet Y BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $finishedProductB->id,
            'base_quantity' => 1.0,
            'status' => 'approved',
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
        ]);

        $routing1 = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-BULK-001',
            'name' => 'Bulk Routing 1',
            'product_id' => $this->finishedProductA->id,
            'version' => '1.0.0',
            'status' => Routing::STATUS_DRAFT,
            'effective_from' => now()->toDateString(),
        ]);
        $routing1->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Operation 1',
            'work_center_id' => $this->workCenterA->id,
        ]);

        $routing2 = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-BULK-002',
            'name' => 'Bulk Routing 2',
            'product_id' => $finishedProductB->id,
            'version' => '1.0.0',
            'status' => Routing::STATUS_DRAFT,
            'effective_from' => now()->toDateString(),
        ]);
        $routing2->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Operation 2',
            'work_center_id' => $this->workCenterA->id,
        ]);

        // 2. Perform bulk submit for approval
        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.bulk-action'), [
                'action' => 'submit_approval',
                'ids' => [$routing1->id, $routing2->id]
            ]);

        $response->assertRedirect();
        $routing1->refresh();
        $routing2->refresh();
        $this->assertTrue($routing1->isPendingApproval());
        $this->assertTrue($routing2->isPendingApproval());

        // 3. Perform bulk approve
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.bulk-action'), [
                'action' => 'approve',
                'ids' => [$routing1->id, $routing2->id]
            ]);

        $response->assertRedirect();
        $routing1->refresh();
        $routing2->refresh();
        $this->assertTrue($routing1->isActive());
        $this->assertTrue($routing2->isActive());

        // 4. Perform bulk cancel
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.bulk-action'), [
                'action' => 'cancel',
                'ids' => [$routing1->id, $routing2->id]
            ]);

        $response->assertRedirect();
        $routing1->refresh();
        $routing2->refresh();
        $this->assertTrue($routing1->isCancelled());
        $this->assertTrue($routing2->isCancelled());
    }

    public function test_routing_approval_blocked_until_linked_draft_bom_is_approved(): void
    {
        $draftBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-DRAFT-LINK',
            'bom_name' => 'Draft Linked BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProductA->id,
            'base_quantity' => 1.0,
            'status' => ProductionBom::STATUS_DRAFT,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-DRAFT-BOM-TEST',
            'name' => 'Test Routing with Draft BOM',
            'product_id' => $this->finishedProductA->id,
            'bom_id' => $draftBom->id,
            'version' => '1.0.0',
            'status' => Routing::STATUS_DRAFT,
            'is_default' => true,
            'created_by' => $this->engineerA->id,
        ]);

        $routing->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Initial Op',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenterA->id,
        ]);

        // Submitting routing with draft BOM should succeed
        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.submit', $routing->id));
        $response->assertRedirect();
        $routing->refresh();
        $this->assertTrue($routing->isPendingApproval());

        // Approving routing while BOM is still in draft should fail
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.approve', $routing->id));
        $response->assertSessionHas('error');
        $routing->refresh();
        $this->assertTrue($routing->isPendingApproval());

        // Now approve the BOM
        $draftBom->update(['status' => ProductionBom::STATUS_APPROVED]);

        // Approving routing should now succeed
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.routing.approve', $routing->id));
        $response->assertRedirect();
        $routing->refresh();
        $this->assertTrue($routing->isActive());
    }

    public function test_draft_bom_rejection_auto_rejects_linked_pending_routing(): void
    {
        $draftBom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-REJECT-CASCADE',
            'bom_name' => 'BOM to Reject',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProductA->id,
            'base_quantity' => 1.0,
            'status' => ProductionBom::STATUS_DRAFT,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-REJECT-CASCADE',
            'name' => 'Routing Pending BOM',
            'product_id' => $this->finishedProductA->id,
            'bom_id' => $draftBom->id,
            'version' => '1.0.0',
            'status' => Routing::STATUS_PENDING_APPROVAL,
            'is_default' => true,
            'created_by' => $this->engineerA->id,
        ]);

        $routing->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Initial Op',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenterA->id,
        ]);

        // Put BOM in pending approval
        $draftBom->update(['status' => ProductionBom::STATUS_PENDING_APPROVAL]);

        // Reject the BOM
        $bomService = app(\App\Domains\Production\Services\ProductionBomService::class);
        $bomService->reject($draftBom->id, $this->adminA->id, 'BOM engineering specifications rejected');

        // Check that routing was automatically rejected
        $routing->refresh();
        $this->assertEquals(Routing::STATUS_DRAFT, $routing->status);

        $latestLog = $routing->approvals()->latest()->first();
        $this->assertNotNull($latestLog);
        $this->assertEquals(RoutingApproval::ACTION_REJECTED, $latestLog->action);
        $this->assertStringContainsString('Automatically rejected because connected BOM', $latestLog->comments);
    }

    public function test_bom_deactivation_makes_linked_active_routing_historical(): void
    {
        $bom1 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-SUPERSEDE-1',
            'bom_name' => 'Old Active BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProductA->id,
            'base_quantity' => 1.0,
            'status' => ProductionBom::STATUS_APPROVED,
            'version' => '1.0.0',
            'effective_date' => now()->subMonths(2)->toDateString(),
        ]);

        $routing1 = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'routing_number' => 'RTG-SUPERSEDE-1',
            'name' => 'Routing for Old BOM',
            'product_id' => $this->finishedProductA->id,
            'bom_id' => $bom1->id,
            'version' => '1.0.0',
            'status' => Routing::STATUS_ACTIVE,
            'is_default' => true,
            'effective_from' => now()->subMonths(2)->toDateString(),
            'created_by' => $this->adminA->id,
        ]);

        $routing1->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Cutting',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenterA->id,
        ]);

        // Create and approve BOM v2
        $bom2 = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'bom_number' => 'BOM-SUPERSEDE-2',
            'bom_name' => 'New Active BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $this->finishedProductA->id,
            'base_quantity' => 1.0,
            'status' => ProductionBom::STATUS_PENDING_APPROVAL,
            'version' => '2.0.0',
            'effective_date' => now()->toDateString(),
        ]);

        $bomService = app(\App\Domains\Production\Services\ProductionBomService::class);
        $bomService->approve($bom2->id, $this->adminA->id);

        $bom1->refresh();
        $this->assertEquals(ProductionBom::STATUS_INACTIVE, $bom1->status);

        // Routing 1 connected to bom1 should now be historical with effective_to = today
        $routing1->refresh();
        $this->assertEquals(Routing::STATUS_HISTORICAL, $routing1->status);
        $this->assertEquals(now()->toDateString(), $routing1->effective_to->toDateString());
    }
}

