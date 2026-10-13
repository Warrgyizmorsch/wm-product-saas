<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase12PurchaseRequisitionTaskTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAlpha;
    private Tenant $tenantBeta;
    private User $alphaOwner;
    private Project $project;
    private Task $task;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAlpha = Tenant::create([
            'name' => 'Alpha Procurement Org',
            'slug' => 'alpha-procurement',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantBeta = Tenant::create([
            'name' => 'Beta Procurement Org',
            'slug' => 'beta-procurement',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);
        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->alphaOwner = User::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Alpha Owner',
            'email' => 'alpha.proc@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenantAlpha->id, 'user_id' => $this->alphaOwner->id, 'role_id' => $ownerRole->id]);

        $this->project = Project::create([
            'tenant_id' => $this->tenantAlpha->id,
            'project_code' => 'PRJ-3001',
            'name' => 'Solar Substation Installation',
            'owner_id' => $this->alphaOwner->id,
            'start_date' => now()->format('Y-m-d'),
            'status' => Project::STATUS_ACTIVE,
        ]);

        $taskList = TaskList::create([
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'name' => 'Procurement Tasks',
        ]);

        $this->task = Task::create([
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'PRJ-3001-T-001',
            'title' => 'Procure 50kVA Inverters',
            'priority' => 'High',
            'status' => Task::STATUS_OPEN,
            'assignee_id' => $this->alphaOwner->id,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => '50kVA Grid Inverter',
            'sku' => 'INV-50K',
            'purchase_price' => 4500.00,
        ]);

        $this->withHeader('X-Tenant', $this->tenantAlpha->slug);
    }

    public function test_pr_stores_with_project_source_type_and_task_link(): void
    {
        $this->actingAs($this->alphaOwner);

        $response = $this->post(route('purchase.requisitions.store'), [
            'requisition_date' => now()->format('Y-m-d'),
            'source_type' => 'project',
            'project_id' => $this->project->id,
            'notes' => 'Materials for Solar Project',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'project_task_id' => $this->task->id,
                    'quantity' => 4,
                    'estimated_cost' => 18000.00,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_requisitions', [
            'tenant_id' => $this->tenantAlpha->id,
            'source_type' => 'project',
            'source_id' => $this->project->id,
        ]);

        $this->assertDatabaseHas('purchase_requisition_items', [
            'tenant_id' => $this->tenantAlpha->id,
            'product_id' => $this->product->id,
            'project_task_id' => $this->task->id,
            'quantity' => 4,
        ]);

        // Verify Task relationship
        $this->assertEquals(1, $this->task->purchaseRequisitionItems()->count());
        $this->assertEquals($this->task->id, $this->task->purchaseRequisitionItems->first()->project_task_id);
    }

    public function test_link_and_unlink_purchase_requisition_controller(): void
    {
        $this->actingAs($this->alphaOwner);

        $pr = PurchaseRequisition::create([
            'tenant_id' => $this->tenantAlpha->id,
            'requisition_number' => 'PR-2026-000001',
            'requisition_date' => now()->format('Y-m-d'),
            'source_type' => 'direct',
            'status' => 'Draft',
        ]);

        $item = PurchaseRequisitionItem::create([
            'tenant_id' => $this->tenantAlpha->id,
            'purchase_requisition_id' => $pr->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'estimated_cost' => 9000.00,
        ]);

        // 1. Link to task
        $linkResponse = $this->post(route('projects.tasks.link-purchase-requisition', [$this->project, $this->task]), [
            'purchase_requisition_item_id' => $item->id,
        ]);

        $linkResponse->assertRedirect();
        $item->refresh();
        $this->assertEquals($this->task->id, $item->project_task_id);

        // 2. Unlink from task
        $unlinkResponse = $this->delete(route('projects.tasks.unlink-purchase-requisition', [$this->project, $this->task, $item->id]));

        $unlinkResponse->assertRedirect();
        $item->refresh();
        $this->assertNull($item->project_task_id);
    }

    public function test_tenant_isolation_prevents_linking_foreign_pr_item(): void
    {
        $this->actingAs($this->alphaOwner);

        $betaPr = PurchaseRequisition::create([
            'tenant_id' => $this->tenantBeta->id,
            'requisition_number' => 'PR-BETA-001',
            'requisition_date' => now()->format('Y-m-d'),
            'source_type' => 'direct',
            'status' => 'Draft',
        ]);

        $betaProduct = Product::create([
            'tenant_id' => $this->tenantBeta->id,
            'name' => 'Beta Cable',
            'sku' => 'CAB-BETA',
        ]);

        $betaItem = PurchaseRequisitionItem::create([
            'tenant_id' => $this->tenantBeta->id,
            'purchase_requisition_id' => $betaPr->id,
            'product_id' => $betaProduct->id,
            'quantity' => 10,
            'estimated_cost' => 500.00,
        ]);

        $response = $this->post(route('projects.tasks.link-purchase-requisition', [$this->project, $this->task]), [
            'purchase_requisition_item_id' => $betaItem->id,
        ]);

        $response->assertSessionHasErrors(['purchase_requisition_item_id']);
        $betaItem->refresh();
        $this->assertNull($betaItem->project_task_id);
    }
}
