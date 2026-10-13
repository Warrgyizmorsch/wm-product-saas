<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Events\ProductionOrderCompleted;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\Routing;
use App\Domains\Projects\Models\ActivityLog;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class Phase12ProductionSyncTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAlpha;
    private Tenant $tenantBeta;
    private User $alphaOwner;
    private User $alphaReadOnly;
    private Project $project;
    private Milestone $milestone;
    private TaskList $taskList;
    private Task $task;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAlpha = Tenant::create([
            'name' => 'Alpha Industrial',
            'slug' => 'alpha-industrial',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantBeta = Tenant::create([
            'name' => 'Beta Industrial',
            'slug' => 'beta-industrial',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);
        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        $readOnlyRole = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();

        $this->alphaOwner = User::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Alpha Owner',
            'email' => 'alpha@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenantAlpha->id, 'user_id' => $this->alphaOwner->id, 'role_id' => $ownerRole->id]);

        $this->alphaReadOnly = User::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Alpha ReadOnly',
            'email' => 'readonly@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenantAlpha->id, 'user_id' => $this->alphaReadOnly->id, 'role_id' => $readOnlyRole->id]);

        $this->project = Project::create([
            'tenant_id' => $this->tenantAlpha->id,
            'project_code' => 'PRJ-2001',
            'name' => 'Machinery Overhaul',
            'owner_id' => $this->alphaOwner->id,
            'start_date' => now()->format('Y-m-d'),
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->milestone = Milestone::create([
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'name' => 'Component Fabrication',
            'status' => Milestone::STATUS_ACTIVE,
        ]);

        $this->taskList = TaskList::create([
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'name' => 'Manufacturing Operations',
        ]);

        $this->task = Task::create([
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'milestone_id' => $this->milestone->id,
            'task_list_id' => $this->taskList->id,
            'task_code' => 'PRJ-2001-T-001',
            'title' => 'Machine Rotor Assembly',
            'priority' => 'High',
            'status' => Task::STATUS_IN_PROGRESS,
            'assignee_id' => $this->alphaOwner->id,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Rotor Assembly Unit',
            'sku' => 'ROTOR-01',
            'type' => 'manufactured',
        ]);

        $this->withHeader('X-Tenant', $this->tenantAlpha->slug);
    }

    public function test_production_order_completed_event_fires_on_canonical_completion(): void
    {
        Event::fake([ProductionOrderCompleted::class]);

        $po = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-2001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'quantity_produced' => 10,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        Event::assertNotDispatched(ProductionOrderCompleted::class);

        // Transition status to completed
        $po->status = ProductionOrder::STATUS_COMPLETED;
        $po->save();

        Event::assertDispatched(ProductionOrderCompleted::class, function ($event) use ($po) {
            return $event->order->id === $po->id;
        });

        // Updating another field without changing status should NOT fire the event again
        $po->description = 'Updated description';
        $po->save();

        Event::assertDispatchedTimes(ProductionOrderCompleted::class, 1);
    }

    public function test_sync_listener_completes_task_when_production_quantity_is_satisfied(): void
    {
        $po = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-2002',
            'product_id' => $this->product->id,
            'quantity_ordered' => 20,
            'quantity_produced' => 20,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_milestone_id' => $this->milestone->id,
            'project_task_id' => $this->task->id,
        ]);

        $this->assertEquals(Task::STATUS_IN_PROGRESS, $this->task->status);

        // Complete the production order (fires event and triggers listener)
        $po->status = ProductionOrder::STATUS_COMPLETED;
        $po->save();

        $this->task->refresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $this->task->status);
        $this->assertNotNull($this->task->completed_at);

        // Milestone progress recalculated
        $this->milestone->refresh();
        $this->assertEquals(100, $this->milestone->completion_percentage);

        // Activity log recorded
        $this->assertDatabaseHas('project_activity_logs', [
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'event_type' => 'production.completed',
        ]);
    }

    public function test_sync_listener_keeps_task_in_progress_on_quantity_shortfall(): void
    {
        $po = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-2003',
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'quantity_produced' => 30, // 20 units short (e.g. scrapped/rejected)
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        $po->status = ProductionOrder::STATUS_COMPLETED;
        $po->save();

        $this->task->refresh();
        // Task must NOT be auto-completed due to shortfall
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $this->task->status);

        // Warning activity log created
        $this->assertDatabaseHas('project_activity_logs', [
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'event_type' => 'production.shortfall_warning',
        ]);
    }

    public function test_sync_listener_keeps_task_in_progress_on_qc_rejection_or_unresolved_rework(): void
    {
        // Production Order completed with QC rejects / scrapped quantities where good quantity_produced < quantity_ordered
        $po = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-2003-QC-FAIL',
            'product_id' => $this->product->id,
            'quantity_ordered' => 40,
            'quantity_produced' => 25, // Only 25 good units passed QC
            'quantity_rejected' => 10, // 10 units failed QC
            'quantity_scrapped' => 5,  // 5 units scrapped / unresolved rework
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        $po->status = ProductionOrder::STATUS_COMPLETED;
        $po->save();

        $this->task->refresh();
        // Task must NOT be marked completed because authoritative good production (25) is less than ordered (40)
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $this->task->status);
        $this->assertNull($this->task->completed_at);

        // Warning activity log created documenting the shortfall
        $this->assertDatabaseHas('project_activity_logs', [
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'subject_id' => $this->task->id,
            'event_type' => 'production.shortfall_warning',
        ]);
    }

    public function test_sync_listener_keeps_task_in_progress_if_sibling_order_open(): void
    {
        $po1 = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-2004-A',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'quantity_produced' => 10,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        $po2 = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-2004-B',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'quantity_produced' => 0,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        // Complete only the first PO
        $po1->status = ProductionOrder::STATUS_COMPLETED;
        $po1->save();

        $this->task->refresh();
        // Sibling order PO-2004-B is still in progress, so task remains in progress
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $this->task->status);

        // Now complete the second PO
        $po2->quantity_produced = 10;
        $po2->status = ProductionOrder::STATUS_COMPLETED;
        $po2->save();

        $this->task->refresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $this->task->status);
    }

    public function test_independent_production_order_without_pm_completes_flawlessly(): void
    {
        // Constraint 4: Production Orders must remain fully functional when no PM links exist
        $standalonePo = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-STANDALONE',
            'product_id' => $this->product->id,
            'quantity_ordered' => 100,
            'quantity_produced' => 100,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => null,
            'project_milestone_id' => null,
            'project_task_id' => null,
        ]);

        $standalonePo->status = ProductionOrder::STATUS_COMPLETED;
        $standalonePo->save();

        $this->assertEquals(ProductionOrder::STATUS_COMPLETED, $standalonePo->status);
    }

    public function test_link_and_unlink_production_order_controller(): void
    {
        $this->actingAs($this->alphaOwner);

        $po = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-LINKABLE',
            'product_id' => $this->product->id,
            'quantity_ordered' => 25,
            'quantity_produced' => 0,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_RELEASED,
        ]);

        // 1. Link PO to Task
        $response = $this->post(route('projects.tasks.link-production-order', [$this->project, $this->task]), [
            'production_order_id' => $po->id,
        ]);

        $response->assertRedirect();
        $po->refresh();
        $this->assertEquals($this->task->id, $po->project_task_id);
        $this->assertEquals($this->project->id, $po->project_id);

        // 2. Unlink PO from Task
        $unlinkResponse = $this->delete(route('projects.tasks.unlink-production-order', [$this->project, $this->task, $po->id]));

        $unlinkResponse->assertRedirect();
        $po->refresh();
        $this->assertNull($po->project_task_id);
    }

    public function test_tenant_isolation_prevents_linking_other_tenant_production_order(): void
    {
        $this->actingAs($this->alphaOwner);

        $betaProduct = Product::create([
            'tenant_id' => $this->tenantBeta->id,
            'name' => 'Beta Product',
            'sku' => 'BETA-01',
        ]);

        $betaPo = ProductionOrder::create([
            'tenant_id' => $this->tenantBeta->id,
            'order_number' => 'PO-BETA-001',
            'product_id' => $betaProduct->id,
            'quantity_ordered' => 50,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_RELEASED,
        ]);

        $response = $this->post(route('projects.tasks.link-production-order', [$this->project, $this->task]), [
            'production_order_id' => $betaPo->id,
        ]);

        $response->assertSessionHasErrors(['production_order_id']);
        $betaPo->refresh();
        $this->assertNull($betaPo->project_task_id);
    }

    public function test_sync_listener_keeps_task_in_progress_when_multiple_orders_completed_with_aggregate_shortfall(): void
    {
        // Case 4: Multiple orders completed, but aggregate good produced < aggregate ordered
        $po1 = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-MULTI-SHORT-1',
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'quantity_produced' => 30, // 20 short due to scrap/rejection
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        $po2 = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-MULTI-SHORT-2',
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'quantity_produced' => 50, // Fully satisfied for this order
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        // Complete PO1 first (produces shortfall warning)
        $po1->status = ProductionOrder::STATUS_COMPLETED;
        $po1->save();

        $this->task->refresh();
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $this->task->status);

        // Complete PO2 second (even though PO2 has full individual yield and all siblings are completed, aggregate yield is 80/100)
        $po2->status = ProductionOrder::STATUS_COMPLETED;
        $po2->save();

        $this->task->refresh();
        // Task MUST remain in progress because aggregate yield (80) is less than aggregate ordered (100)
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $this->task->status);
        $this->assertNull($this->task->completed_at);

        // Warning logged for aggregate shortfall
        $this->assertDatabaseHas('project_activity_logs', [
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'subject_id' => $this->task->id,
            'event_type' => 'production.shortfall_warning',
        ]);
    }

    public function test_sync_listener_completes_task_when_multiple_orders_reach_exact_aggregate_yield(): void
    {
        // Case 6: Unequal split (60 + 40 = 100) reaching exact aggregate yield
        $po1 = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-SPLIT-60',
            'product_id' => $this->product->id,
            'quantity_ordered' => 60,
            'quantity_produced' => 60,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        $po2 = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-SPLIT-40',
            'product_id' => $this->product->id,
            'quantity_ordered' => 40,
            'quantity_produced' => 40,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        // Complete first order
        $po1->status = ProductionOrder::STATUS_COMPLETED;
        $po1->save();

        $this->task->refresh();
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $this->task->status);

        // Complete second order
        $po2->status = ProductionOrder::STATUS_COMPLETED;
        $po2->save();

        $this->task->refresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $this->task->status);
        $this->assertNotNull($this->task->completed_at);
    }
}
