<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderItem;
use App\Domains\Sales\Services\SalesOrderProjectBridgeService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class Phase12SalesOrderConversionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private User $unauthorizedUser;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);
        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        $readOnlyRole = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, 'role_id' => $ownerRole->id]);

        $this->unauthorizedUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ReadOnly User',
            'email' => 'readonly@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->unauthorizedUser->id, 'role_id' => $readOnlyRole->id]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Global Logistics Inc',
            'email' => 'contact@globallogistics.com',
        ]);

        $this->withHeader('X-Tenant', $this->tenant->slug);
    }

    public function test_confirmed_sales_order_converts_to_project_with_milestones_and_tasks(): void
    {
        $this->actingAs($this->owner);

        $so = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => '1001',
            'order_date' => now()->format('Y-m-d'),
            'shipment_date' => now()->addDays(20)->format('Y-m-d'),
            'status' => 'Confirmed',
            'subtotal' => 15000.00,
            'total_amount' => 15000.00,
        ]);

        $item1 = SalesOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'item_name' => 'Custom Steel Enclosure',
            'description' => 'Fabricate 10 custom steel enclosures',
            'quantity' => 10,
            'unit_price' => 1000.00,
            'amount' => 10000.00,
        ]);

        $item2 = SalesOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'item_name' => 'Assembly Service',
            'description' => 'Assembly and wiring',
            'quantity' => 5,
            'unit_price' => 1000.00,
            'amount' => 5000.00,
        ]);

        $bridge = app(SalesOrderProjectBridgeService::class);
        $project = $bridge->convertSalesOrderToProject($so);

        $this->assertInstanceOf(Project::class, $project);
        $this->assertEquals($so->id, $project->sales_order_id);
        $this->assertEquals($this->customer->id, $project->customer_id);
        $this->assertEquals(15000.00, (float) $project->budget_amount);

        // Check milestones created
        $milestones = $project->milestones()->get();
        $this->assertCount(2, $milestones);

        $m1 = $milestones->where('sales_order_item_id', $item1->id)->first();
        $this->assertNotNull($m1);
        $this->assertEquals(10000.00, (float) $m1->billing_amount);

        // Check tasks created
        $tasks = $project->tasks()->get();
        $this->assertCount(2, $tasks);

        $t1 = $tasks->where('title', 'Fulfill ' . $item1->item_name)->first();
        $this->assertNotNull($t1);
        $this->assertEquals($m1->id, $t1->milestone_id);

        // Verify relationships
        $this->assertEquals($project->id, $so->projects()->first()->id);
        $this->assertEquals($so->id, $project->salesOrder->id);
        $this->assertEquals($item1->id, $m1->salesOrderItem->id);
    }

    public function test_draft_sales_order_cannot_be_converted(): void
    {
        $this->actingAs($this->owner);

        $so = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => '1002',
            'order_date' => now()->format('Y-m-d'),
            'status' => 'Draft',
            'subtotal' => 5000.00,
            'total_amount' => 5000.00,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $bridge = app(SalesOrderProjectBridgeService::class);
        $bridge->convertSalesOrderToProject($so);
    }

    public function test_sales_order_line_item_can_link_to_multiple_milestones(): void
    {
        $this->actingAs($this->owner);

        $so = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => '1003',
            'order_date' => now()->format('Y-m-d'),
            'status' => 'Confirmed',
            'subtotal' => 20000.00,
            'total_amount' => 20000.00,
        ]);

        $item = SalesOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'item_name' => 'Phase-able deliverable',
            'quantity' => 100,
            'unit_price' => 200.00,
            'amount' => 20000.00,
        ]);

        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-1003',
            'name' => 'Phased Project',
            'customer_id' => $this->customer->id,
            'sales_order_id' => $so->id,
            'owner_id' => $this->owner->id,
            'start_date' => now()->format('Y-m-d'),
            'status' => Project::STATUS_ACTIVE,
        ]);

        // Create 2 milestones linked to the same Sales Order line item
        $m1 = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'sales_order_item_id' => $item->id,
            'name' => 'Phase 1 Delivery (50 units)',
            'billing_amount' => 10000.00,
            'status' => Milestone::STATUS_ACTIVE,
        ]);

        $m2 = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'sales_order_item_id' => $item->id,
            'name' => 'Phase 2 Delivery (50 units)',
            'billing_amount' => 10000.00,
            'status' => Milestone::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('project_milestones', ['id' => $m1->id, 'sales_order_item_id' => $item->id]);
        $this->assertDatabaseHas('project_milestones', ['id' => $m2->id, 'sales_order_item_id' => $item->id]);
        $this->assertEquals(2, $item->milestones()->count());
    }

    public function test_controller_convert_to_project_route(): void
    {
        $this->actingAs($this->owner);

        $so = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => '1004',
            'order_date' => now()->format('Y-m-d'),
            'shipment_date' => now()->addDays(15)->format('Y-m-d'),
            'status' => 'Confirmed',
            'subtotal' => 8000.00,
            'total_amount' => 8000.00,
        ]);

        SalesOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'item_name' => 'Batch Deliverable',
            'quantity' => 1,
            'unit_price' => 8000.00,
            'amount' => 8000.00,
        ]);

        $response = $this->post(route('sales.orders.convert-to-project', $so->id), [
            'project_name' => 'Custom Delivery Title',
            'budget_amount' => 8000.00,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'name' => 'Custom Delivery Title',
        ]);
    }

    public function test_projects_create_modal_shows_confirmed_sales_orders_and_creates_project(): void
    {
        $this->actingAs($this->owner);

        // 1. Confirmed SO available for conversion
        $so = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => '1005',
            'order_date' => now()->format('Y-m-d'),
            'shipment_date' => now()->addDays(20)->format('Y-m-d'),
            'status' => 'Confirmed',
            'subtotal' => 12000.00,
            'total_amount' => 12000.00,
        ]);

        SalesOrderItem::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'item_name' => 'Solar Inverter 5kW',
            'quantity' => 2,
            'unit_price' => 6000.00,
            'amount' => 12000.00,
        ]);

        // 2. Draft SO (should NOT appear)
        $draftSo = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => '1006',
            'order_date' => now()->format('Y-m-d'),
            'status' => 'Draft',
            'subtotal' => 3000.00,
            'total_amount' => 3000.00,
        ]);

        // Visit index page: confirmed SO should be visible in modal dropdown, draft should not
        $response = $this->get(route('projects.index'));
        $response->assertOk();
        $response->assertSee('SO-1005', false);
        $response->assertDontSee('SO-1006', false);

        // 3. Post to projects.store with sales_order_id
        $storeResponse = $this->post(route('projects.store'), [
            '_modal' => 'createProjectModal',
            'sales_order_id' => $so->id,
            'name' => 'Custom Solar Installation Project',
            'owner_id' => $this->owner->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_HIGH,
            'status' => Project::STATUS_DRAFT,
        ]);

        $project = Project::where('sales_order_id', $so->id)->first();
        $this->assertNotNull($project);
        $this->assertEquals('Custom Solar Installation Project', $project->name);
        $this->assertEquals($this->customer->id, $project->customer_id);
        $this->assertEquals(Project::STATUS_ACTIVE, $project->status);

        // Check milestones and tasks were created
        $this->assertCount(1, $project->milestones);
        $this->assertEquals('Deliver: Solar Inverter 5kW', $project->milestones->first()->name);
        $this->assertCount(1, $project->tasks);

        $storeResponse->assertRedirect(route('projects.show', $project));

        // 4. Once converted, SO-1005 should NO LONGER appear in modal dropdown
        $recheckResponse = $this->get(route('projects.index'));
        $recheckResponse->assertOk();
        $recheckResponse->assertDontSee('data-suggested-name="' . $this->customer->name . ' - SO-1005"', false);
    }

    public function test_projects_create_modal_rejects_unconfirmed_sales_order(): void
    {
        $this->actingAs($this->owner);

        $draftSo = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => '1007',
            'order_date' => now()->format('Y-m-d'),
            'status' => 'Draft',
            'subtotal' => 4000.00,
            'total_amount' => 4000.00,
        ]);

        $response = $this->from(route('projects.index'))->post(route('projects.store'), [
            '_modal' => 'createProjectModal',
            'sales_order_id' => $draftSo->id,
            'name' => 'Invalid Draft Conversion',
            'owner_id' => $this->owner->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHasErrors('sales_order_id');
        $this->assertDatabaseMissing('projects', ['name' => 'Invalid Draft Conversion']);
    }

    public function test_projects_create_modal_rejects_already_linked_sales_order(): void
    {
        $this->actingAs($this->owner);

        $so = SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'sales_order_number' => '1008',
            'order_date' => now()->format('Y-m-d'),
            'status' => 'Confirmed',
            'subtotal' => 5000.00,
            'total_amount' => 5000.00,
        ]);

        // Manually link an existing project
        Project::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $so->id,
            'project_code' => 'PRJ-9999',
            'name' => 'Pre-existing Project',
            'owner_id' => $this->owner->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_ACTIVE,
        ]);

        $response = $this->from(route('projects.index'))->post(route('projects.store'), [
            '_modal' => 'createProjectModal',
            'sales_order_id' => $so->id,
            'name' => 'Duplicate Link Attempt',
            'owner_id' => $this->owner->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHasErrors('sales_order_id');
        $this->assertDatabaseMissing('projects', ['name' => 'Duplicate Link Attempt']);
    }
}

