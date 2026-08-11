<?php

namespace Tests\Feature\HRMS;

use App\Domains\HRMS\Models\Employee;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\HRMS\Concerns\BuildsHrmsOrgStructure;
use Tests\TestCase;

class HrmsEmployeeStatusTest extends TestCase
{
    use RefreshDatabase;
    use BuildsHrmsOrgStructure;

    private function makeHrManager(Tenant $tenant, string $email): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'hr_manager')->firstOrFail();

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $tenant->id,
        ]);

        return $user;
    }

    public function test_can_update_employee_status_via_ajax(): void
    {
        $this->seed(RbacSeeder::class);

        $tenant = $this->makeTenant('tenant-a');
        $hrManager = $this->makeHrManager($tenant, 'hr@example.com');
        $employee = $this->makeEmployee($tenant, 'Emp1');

        $this->assertTrue((bool)$employee->status);

        $response = $this->actingAs($hrManager)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('hrms.employees.update-status', $employee->id), [
                'status' => '0',
            ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Employee status updated successfully.'
        ]);

        $this->assertFalse((bool)$employee->fresh()->status);
    }
}
