<?php

namespace Tests\Feature\Production;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
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
            'effective_from' => '2026-06-30',
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
    }

    public function test_validation_rejects_duplicate_sequences(): void
    {
        $payload = [
            'name' => 'Invalid Routing Seq',
            'product_id' => $this->finishedProductA->id,
            'version' => '1.0.0',
            'effective_from' => '2026-06-30',
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
            'effective_from' => '2026-06-30',
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
            'effective_from' => '2026-06-30',
        ]);

        $routing->operations()->create([
            'tenant_id' => $this->tenantA->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Operation 1',
            'work_center_id' => $this->workCenterA->id,
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
        $this->assertEquals(Routing::STATUS_DRAFT, $newRouting->status);
        $this->assertEquals(1, $newRouting->revision);
        $this->assertCount(1, $newRouting->operations);
        $this->assertEquals('Operation 1', $newRouting->operations[0]->name);
    }
}
