<?php

namespace Tests\Feature;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrmsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $hrManager;
    private User $salesExecutive;

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

        $this->hrManager = $this->createUserWithRole('hr@example.com', 'hr_manager');
        $this->salesExecutive = $this->createUserWithRole('exec@example.com', 'sales_executive');
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
    public function a_role_without_hr_permission_cannot_view_salary_structures(): void
    {
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.salary-structure.index'));

        $response->assertForbidden();
    }

    /** @test */
    public function hr_manager_can_view_salary_structures(): void
    {
        $response = $this->actingAs($this->hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.salary-structure.index'));

        $response->assertOk();
    }

    /** @test */
    public function a_role_without_hr_permission_cannot_view_org_structure(): void
    {
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.org.index'));

        $response->assertForbidden();
    }

    /** @test */
    public function a_role_without_hr_permission_cannot_view_leave_structure(): void
    {
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.leave-structure.index'));

        $response->assertForbidden();
    }

    /** @test */
    public function a_role_without_hr_permission_cannot_view_penalization_policy(): void
    {
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.penalization-policy.index'));

        $response->assertForbidden();
    }
}
