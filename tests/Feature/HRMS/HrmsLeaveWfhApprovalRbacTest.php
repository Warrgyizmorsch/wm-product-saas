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
 * LeaveRequestController::updateStatus() and WfhRequestController::updateStatus()
 * (app/Domains/HRMS/Controllers/LeaveRequestController.php:108-110 and
 * WfhRequestController.php:109-111) treat ANY user with a non-null `role_id`
 * as an HR admin: `hasHrPermission(...) || !empty($user->role_id)`. Every user
 * created through the normal admin flow (App\Domains\Access\Services\UserService::create)
 * always sets `role_id`, so this fallback is not a rare edge case — it fires for
 * every real user account regardless of which role they hold.
 *
 * The tests below assert the CORRECT behavior (only an HR-permitted user may
 * approve/reject another employee's leave/WFH request). The "*_but_currently_can"
 * tests are expected to FAIL against the current code — a red test here is the
 * audit finding, not a bug in the test. Do not "fix" these by weakening the
 * assertions; fix the source (drop the `!empty($user->role_id)` fallback and
 * require a real hasHrPermission() check) instead.
 */
class HrmsLeaveWfhApprovalRbacTest extends TestCase
{
    use RefreshDatabase;
    use BuildsHrmsOrgStructure;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = $this->makeTenant('test-tenant');
    }

    /** Mirrors App\Domains\Access\Services\UserService::create(), which always sets role_id. */
    private function createUserWithRole(string $email, string $roleSlug): User
    {
        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();

        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $user;
    }

    private function createUserWithNoRole(string $email): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
    }

    private function makeLeaveRequest(): LeaveRequest
    {
        $employee = $this->makeEmployee($this->tenant, 'E1');
        $leaveType = $this->makeLeaveType($this->tenant, $employee, 'E1');

        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        return LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay(),
            'duration' => 1.0,
            'reason' => 'Personal',
            'status' => 'pending',
        ]);
    }

    private function makeWfhRequest(): WfhRequest
    {
        $employee = $this->makeEmployee($this->tenant, 'E2');

        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        return WfhRequest::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay(),
            'duration' => 1.0,
            'reason' => 'Home repair',
            'status' => 'pending',
        ]);
    }

    public function test_user_with_no_role_at_all_cannot_approve_a_leave_request(): void
    {
        $leaveRequest = $this->makeLeaveRequest();
        $bystander = $this->createUserWithNoRole('bystander@example.com');

        $response = $this->actingAs($bystander)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('hrms.leaves.update-status', $leaveRequest->id), [
                'action' => 'approved',
            ]);

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_sales_executive_without_hr_permission_cannot_approve_a_leave_request_but_currently_can(): void
    {
        $leaveRequest = $this->makeLeaveRequest();
        $salesExecutive = $this->createUserWithRole('exec@example.com', 'sales_executive');

        $this->actingAs($salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('hrms.leaves.update-status', $leaveRequest->id), [
                'action' => 'approved',
            ]);

        // CRITICAL finding: this currently fails because `!empty($user->role_id)`
        // treats any role-holding user as an HR admin. See class docblock.
        $this->assertSame(
            'pending',
            $leaveRequest->fresh()->status,
            'sales_executive has no hr.settings.manage/hr.leaves.manage permission and must not be able to approve leave requests.'
        );
    }

    public function test_sales_executive_without_hr_permission_cannot_approve_a_wfh_request_but_currently_can(): void
    {
        $wfhRequest = $this->makeWfhRequest();
        $salesExecutive = $this->createUserWithRole('exec2@example.com', 'sales_executive');

        $this->actingAs($salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('hrms.wfh.approve', $wfhRequest->id));

        // CRITICAL finding: same `!empty($user->role_id)` fallback in
        // WfhRequestController::updateStatus() (line 109-111).
        $this->assertSame(
            'pending',
            $wfhRequest->fresh()->status,
            'sales_executive has no hr.settings.manage/hr.leaves.manage permission and must not be able to approve WFH requests.'
        );
    }
}
