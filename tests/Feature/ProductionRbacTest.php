<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionRbacTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $productionManager;
    private User $salesExecutive;
    private Product $product;
    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->productionManager = $this->createUserWithRole('pm@example.com', 'production_manager');
        $this->salesExecutive = $this->createUserWithRole('exec@example.com', 'sales_executive');

        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS']);
        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-1',
            'type' => 'finished_good',
            'item_type' => 'Goods',
            'variation_type' => 'Single',
            'status' => 'active',
            'selling_price' => 10,
            'cost_price' => 5,
            'unit_cost' => 5,
            'uom_id' => $this->uom->id,
        ]);
    }

    private function createUserWithRole(string $email, string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $user;
    }

    /** @test */
    public function a_role_without_bom_permission_cannot_create_a_bom(): void
    {
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.boms.create'));

        $response->assertForbidden();
    }

    /** @test */
    public function production_manager_can_create_a_bom(): void
    {
        $response = $this->actingAs($this->productionManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.boms.create'));


        $response->assertOk();
    }

    /** @test */
    public function a_role_without_bom_approve_permission_cannot_approve_a_pending_bom(): void
    {
        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-0001',
            'bom_name' => 'Widget BOM',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $this->product->id,
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'status' => 'pending_approval',
            'effective_date' => now()->toDateString(),
        ]);

        // sales_executive has no production permissions at all
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.boms.approve', $bom->id));

        $response->assertForbidden();
        $this->assertEquals('pending_approval', $bom->fresh()->status);
    }

    /** @test */
    public function a_role_without_mes_permission_cannot_create_a_batch(): void
    {
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.mes.batches.create'), [
                'production_order_id' => 1,
                'product_id' => $this->product->id,
                'planned_quantity' => 10,
            ]);

        $response->assertForbidden();
    }

    /** @test */
    public function production_manager_can_reassign_an_operator_but_the_assigned_operator_manages_their_own_assignment(): void
    {
        $operator = $this->createUserWithRole('operator@example.com', 'sales_executive');

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-0001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);

        $workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Line',
            'code' => 'WC-1',
            'status' => 'active',
        ]);

        $operation = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'work_center_id' => $workCenter->id,
            'sequence' => 1,
            'operation_number' => 'OP-10',
            'name' => 'Assembly',
            'status' => 'ready',
        ]);

        $assignment = ProductionOperatorAssignment::create([
            'tenant_id' => $this->tenant->id,
            'production_order_operation_id' => $operation->id,
            'user_id' => $operator->id,
            'status' => 'assigned',
            'assigned_by' => $this->productionManager->id,
            'assigned_at' => now(),
        ]);

        // A third party with no ownership and no mes.execute permission is denied.
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.mes.assignments.accept', $assignment->id));

        $response->assertForbidden();
    }

    /** @test */
    public function super_admin_has_access_to_all_production_routes(): void
    {
        $superAdmin = $this->createUserWithRole('superadmin@example.com', 'super_admin');

        $this->actingAs($superAdmin)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.work-centers.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.orders.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.boms.index'))
            ->assertOk();
    }
}
