<?php

namespace Tests\Feature;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueCycleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $salesManager;
    private User $hrManager;

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

        $this->salesManager = $this->createUserWithRole('manager@example.com', 'sales_manager');
        $this->hrManager = $this->createUserWithRole('hr@example.com', 'hr_manager');
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

    public static function indexRouteProvider(): array
    {
        return [
            'dispatches' => ['sales.dispatches.index'],
            'invoices' => ['sales.invoices.index'],
            'payments' => ['sales.payments.index'],
            'returns' => ['sales.returns.index'],
        ];
    }

    /** @test */
    public function a_role_without_any_sales_permission_cannot_view_revenue_cycle_screens(): void
    {
        foreach (self::indexRouteProvider() as $route) {
            $response = $this->actingAs($this->hrManager)
                ->withHeader('X-Tenant', 'test-tenant')
                ->get(route($route[0]));

            $response->assertForbidden();
        }
    }

    /** @test */
    public function a_sales_manager_can_view_revenue_cycle_screens(): void
    {
        foreach (self::indexRouteProvider() as $route) {
            $response = $this->actingAs($this->salesManager)
                ->withHeader('X-Tenant', 'test-tenant')
                ->get(route($route[0]));

            $response->assertOk();
        }
    }

    /** @test */
    public function a_role_without_permission_cannot_create_a_payment(): void
    {
        $response = $this->actingAs($this->hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('sales.payments.store'), [
                'customer_id' => 1,
                'payment_number' => 'PAY-9001',
                'payment_date' => now()->toDateString(),
                'amount' => 10,
                'payment_method' => 'Cash',
                'allocate_to' => 'unallocated',
            ]);

        $response->assertForbidden();
    }
}
