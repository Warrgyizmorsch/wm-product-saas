<?php

namespace Tests\Feature\HRMS;

use App\Domains\HRMS\Models\LeaveRequest;
use App\Domains\HRMS\Models\WfhRequest;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\HRMS\Concerns\BuildsHrmsOrgStructure;
use Tests\TestCase;

/**
 * Locks in that HRMS's row-based tenant isolation (BaseModel's global
 * tenant scope) actually blocks cross-tenant access via guessed/sequential
 * IDs on route-model-bound HRMS endpoints, even for a user who holds the
 * `hr.settings.manage` permission in their own tenant.
 */
class HrmsTenantIsolationTest extends TestCase
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

    public function test_employee_profile_from_another_tenant_returns_404_not_someone_elses_data(): void
    {
        $this->seed(RbacSeeder::class);

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $hrManagerA = $this->makeHrManager($tenantA, 'hrA@example.com');
        $employeeB = $this->makeEmployee($tenantB, 'B1');

        $response = $this->actingAs($hrManagerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('hrms.employees.show', $employeeB->id));

        $response->assertNotFound();
    }

    public function test_employee_update_from_another_tenant_returns_404(): void
    {
        $this->seed(RbacSeeder::class);

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $hrManagerA = $this->makeHrManager($tenantA, 'hrA@example.com');
        $employeeB = $this->makeEmployee($tenantB, 'B2');

        $response = $this->actingAs($hrManagerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('hrms.employees.update', $employeeB->id), [
                'full_name' => 'Hijacked Name',
            ]);

        $response->assertNotFound();

        $this->assertSame('Employee B2', $employeeB->fresh()->full_name);
    }

    public function test_leave_request_update_status_from_another_tenant_returns_404(): void
    {
        $this->seed(RbacSeeder::class);

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $hrManagerA = $this->makeHrManager($tenantA, 'hrA@example.com');
        $employeeB = $this->makeEmployee($tenantB, 'B3');
        $leaveTypeB = $this->makeLeaveType($tenantB, $employeeB, 'B3');

        app(\App\Core\Tenant\TenantContext::class)->set($tenantB);
        $leaveRequestB = LeaveRequest::create([
            'tenant_id' => $tenantB->id,
            'company_id' => $employeeB->company_id,
            'employee_id' => $employeeB->id,
            'leave_type_id' => $leaveTypeB->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay(),
            'duration' => 1.0,
            'reason' => 'Personal',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($hrManagerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('hrms.leaves.update-status', $leaveRequestB->id), [
                'action' => 'approved',
            ]);

        $response->assertNotFound();

        $this->assertSame('pending', $leaveRequestB->fresh()->status);
    }

    public function test_wfh_request_approve_from_another_tenant_returns_404(): void
    {
        $this->seed(RbacSeeder::class);

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $hrManagerA = $this->makeHrManager($tenantA, 'hrA@example.com');
        $employeeB = $this->makeEmployee($tenantB, 'B4');

        app(\App\Core\Tenant\TenantContext::class)->set($tenantB);
        $wfhRequestB = WfhRequest::create([
            'tenant_id' => $tenantB->id,
            'company_id' => $employeeB->company_id,
            'employee_id' => $employeeB->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay(),
            'duration' => 1.0,
            'reason' => 'Home repair',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($hrManagerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('hrms.wfh.approve', $wfhRequestB->id));

        $response->assertNotFound();

        $this->assertSame('pending', $wfhRequestB->fresh()->status);
    }
}
