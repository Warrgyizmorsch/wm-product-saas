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

/**
 * The employee form writes its "System Role" straight to users.role_id.
 * It used to accept any role id, so an HR manager could make an employee's
 * account super_admin — a platform admin across every tenant.
 */
class HrmsEmployeeRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use BuildsHrmsOrgStructure;

    private Tenant $tenant;
    private User $hrManager;
    private User $employeeUser;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = $this->makeTenant('tenant-a');

        $this->hrManager = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'HR', 'email' => 'hr@example.com', 'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->hrManager->id, 'role_id' => $this->roleId('hr_manager'), 'tenant_id' => $this->tenant->id]);

        $this->employeeUser = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@example.com', 'password' => bcrypt('password'),
        ]);
        $this->employee = $this->makeEmployee($this->tenant, 'Staff', [
            'user_id' => $this->employeeUser->id,
            'employee_id' => 'EMP-1',
        ]);
    }

    private function roleId(string $slug): int
    {
        return Role::query()->whereNull('tenant_id')->where('slug', $slug)->value('id');
    }

    private function updateAs(User $actor, array $overrides)
    {
        $employee = $this->employee;

        return $this->actingAs($actor)
            ->withHeader('X-Tenant', 'tenant-a')
            ->from(route('hrms.employees.index'))
            ->post(route('hrms.employees.update', $employee), array_merge([
                'employee_id' => $employee->employee_id,
                'user_id' => $employee->user_id,
                'full_name' => $employee->full_name,
                'personal_email' => $employee->personal_email,
                'company_id' => $employee->company_id,
                'business_unit_id' => $employee->business_unit_id,
                'branch_id' => $employee->branch_id,
                'department_id' => $employee->department_id,
                'designation_id' => $employee->designation_id,
                'date_of_joining' => $employee->date_of_joining->format('Y-m-d'),
                'gender' => 'Other',
                'status' => '1',
            ], $overrides));
    }

    public function test_hr_manager_cannot_make_an_employee_account_super_admin(): void
    {
        $this->updateAs($this->hrManager, ['role_id' => $this->roleId('super_admin')])
            ->assertSessionHasErrors('role_id');

        $this->assertNull($this->employeeUser->fresh()->role_id);
    }

    public function test_hr_manager_can_assign_a_tenant_role(): void
    {
        $this->updateAs($this->hrManager, ['role_id' => $this->roleId('sales_manager')])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->roleId('sales_manager'), (int) $this->employeeUser->fresh()->role_id);
    }

    public function test_an_employee_editing_their_own_profile_cannot_change_their_role(): void
    {
        $this->updateAs($this->employeeUser, ['role_id' => $this->roleId('tenant_owner')]);

        $this->assertNull($this->employeeUser->fresh()->role_id);
    }
}
