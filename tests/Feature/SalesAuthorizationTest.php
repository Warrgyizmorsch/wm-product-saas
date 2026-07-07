<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $salesExecutive;
    private User $otherSalesExecutive;
    private User $salesManager;
    private Customer $customer;

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

        $this->salesExecutive = $this->createUserWithRole('exec@example.com', 'sales_executive');
        $this->otherSalesExecutive = $this->createUserWithRole('exec2@example.com', 'sales_executive');
        $this->salesManager = $this->createUserWithRole('manager@example.com', 'sales_manager');

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Co',
            'email' => 'acme@example.com',
            'status' => 'active',
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

    private function orderPayload(User $owner, string $number): array
    {
        return [
            'customer_id' => $this->customer->id,
            'sales_person_id' => $owner->id,
            'sales_order_number' => $number,
            'order_date' => now()->toDateString(),
        ];
    }

    /** @test */
    public function guest_is_redirected_to_login_instead_of_reaching_sales(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('sales.orders.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function sales_executive_can_create_and_view_their_own_order(): void
    {
        $store = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.orders.store'), $this->orderPayload($this->salesExecutive, 'SO-0001'));

        $order = SalesOrder::latest('id')->firstOrFail();
        $store->assertRedirect(route('sales.orders.show', $order->id));

        $show = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('sales.orders.show', $order->id));

        $show->assertOk();
    }

    /** @test */
    public function sales_executive_cannot_view_an_order_owned_by_someone_else(): void
    {
        $this->actingAs($this->otherSalesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.orders.store'), $this->orderPayload($this->otherSalesExecutive, 'SO-0002'));

        $order = SalesOrder::latest('id')->firstOrFail();

        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('sales.orders.show', $order->id));

        $response->assertForbidden();
    }

    /** @test */
    public function sales_executive_cannot_delete_their_own_order(): void
    {
        $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.orders.store'), $this->orderPayload($this->salesExecutive, 'SO-0003'));

        $order = SalesOrder::latest('id')->firstOrFail();

        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('sales.orders.destroy', $order->id));

        $response->assertForbidden();
        $this->assertNotNull(SalesOrder::find($order->id));
    }

    /** @test */
    public function sales_manager_can_delete_any_order_in_the_tenant(): void
    {
        $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.orders.store'), $this->orderPayload($this->salesExecutive, 'SO-0004'));

        $order = SalesOrder::latest('id')->firstOrFail();

        $response = $this->actingAs($this->salesManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('sales.orders.destroy', $order->id));

        $response->assertRedirect(route('sales.orders.index'));
        $this->assertNull(SalesOrder::find($order->id));
    }
}
