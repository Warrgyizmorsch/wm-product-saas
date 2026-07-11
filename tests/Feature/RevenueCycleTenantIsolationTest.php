<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Sales\Models\CustomerPayment;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueCycleTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->userA = $this->createUserWithRole($this->tenantA, 'a@example.com', 'sales_manager');
        $this->userB = $this->createUserWithRole($this->tenantB, 'b@example.com', 'sales_manager');
    }

    private function createUserWithRole(Tenant $tenant, string $email, string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $tenant->id,
        ]);

        return $user;
    }

    /** @test */
    public function a_payment_created_by_a_tenant_user_is_stamped_with_that_users_tenant_not_a_hardcoded_default(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Acme Co',
            'email' => 'acme@example.com',
            'status' => 'active',
        ]);

        $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('sales.payments.store'), [
                'customer_id' => $customer->id,
                'payment_number' => 'PAY-0001',
                'payment_date' => now()->toDateString(),
                'amount' => 100,
                'payment_method' => 'Cash',
                'allocate_to' => 'unallocated',
            ]);

        $payment = CustomerPayment::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame($this->tenantA->id, $payment->tenant_id);
    }

    /** @test */
    public function a_tenant_b_user_cannot_see_tenant_as_payments_in_the_index(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Acme Co',
            'email' => 'acme@example.com',
            'status' => 'active',
        ]);

        $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('sales.payments.store'), [
                'customer_id' => $customer->id,
                'payment_number' => 'PAY-0002',
                'payment_date' => now()->toDateString(),
                'amount' => 250,
                'payment_method' => 'Cash',
                'allocate_to' => 'unallocated',
            ]);

        $response = $this->actingAs($this->userB)
            ->withHeader('X-Tenant', 'tenant-b')
            ->get(route('sales.payments.index'));

        $response->assertOk();
        $response->assertDontSee('PAY-0002');
    }
}
