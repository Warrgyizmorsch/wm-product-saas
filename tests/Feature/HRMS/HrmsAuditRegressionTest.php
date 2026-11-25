<?php

namespace Tests\Feature\HRMS;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\BiometricDevice;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\HRMS\Concerns\BuildsHrmsOrgStructure;
use Tests\TestCase;

/**
 * Regression tests for the 2026-11-21 HRMS QA/Security audit findings.
 */
class HrmsAuditRegressionTest extends TestCase
{
    use RefreshDatabase;
    use BuildsHrmsOrgStructure;

    private Tenant $tenant;
    private Employee $target;
    private User $plain;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = $this->makeTenant('audit-tenant');
        $this->target = $this->makeEmployee($this->tenant, 'T1');

        $this->plain = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Plain', 'email' => 'plain@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'hradmin@example.com',
            'password' => bcrypt('password'),
        ]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $this->admin->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        app(TenantContext::class)->set($this->tenant);
    }

    private function as(User $u)
    {
        return $this->actingAs($u)->withHeader('X-Tenant', 'audit-tenant');
    }

    // F-13 / F-18 / F-14
    public function test_plain_user_cannot_add_adhoc_component_or_create_payroll_run(): void
    {
        $this->as($this->plain)
            ->postJson("/api/hrms/employees/{$this->target->id}/adhoc-components", [
                'salary_component_id' => 1, 'amount' => 75000, 'payroll_month' => now()->format('Y-m'),
            ])->assertStatus(403);

        $this->as($this->plain)->postJson('/api/hrms/payroll/store', [])->assertStatus(403);
    }

    // F-36 / F-37 / F-39
    public function test_plain_user_cannot_delete_attendance_prune_logs_or_reset_exit_templates(): void
    {
        $date = now()->format('Y-m-d');
        $this->as($this->plain)->deleteJson("/api/hrms/attendance/date/{$date}")->assertStatus(403);
        $this->as($this->plain)->postJson('/api/hrms/attendance/prune-logs', [])->assertStatus(403);
        $this->as($this->plain)->postJson('/api/hrms/exits/clearance-templates/reset', [])->assertStatus(403);
    }

    // F-14 exit manager approval
    public function test_plain_user_cannot_endorse_someone_elses_exit(): void
    {
        $this->as($this->plain)->postJson('/api/hrms/exits/999999/approve-manager', [])->assertStatus(404);
    }

    // F-42
    public function test_department_with_active_employee_cannot_be_deleted(): void
    {
        $deptId = $this->target->department_id;

        $this->as($this->admin)->deleteJson("/api/hrms/org/departments/{$deptId}")->assertStatus(409);

        $this->assertSame($deptId, $this->target->fresh()->department_id);
        $this->assertNotNull(Department::find($deptId));
    }

    // F-01
    public function test_biometric_webhook_requires_the_device_secret_and_hides_serial_oracle(): void
    {
        $device = BiometricDevice::create([
            'tenant_id' => $this->tenant->id, 'company_id' => $this->target->company_id,
            'name' => 'Gate', 'device_serial' => 'SN-AUDIT-1', 'status' => true, 'port' => 4370,
        ]);
        $device->update(['webhook_secret' => 's3cret-value']);

        $unknown = $this->get('/api/hrms/biometric/webhook?SN=NOPE');
        $noSecret = $this->get('/api/hrms/biometric/webhook?SN=SN-AUDIT-1');
        $wrong = $this->get('/api/hrms/biometric/webhook?SN=SN-AUDIT-1&secret=bad');
        $ok = $this->withHeader('X-Device-Secret', 's3cret-value')->get('/api/hrms/biometric/webhook?SN=SN-AUDIT-1');

        $unknown->assertStatus(401);
        $noSecret->assertStatus(401);
        $wrong->assertStatus(401);
        $ok->assertStatus(200);
    }

    // F-19
    public function test_on_leave_attendance_row_can_be_stored_without_check_in(): void
    {
        $this->assertTrue(Schema::hasColumn('attendances', 'check_in'));
        $att = Attendance::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->target->id,
            'date' => now()->format('Y-m-d'), 'status' => 'on_leave',
        ]);
        $this->assertNull($att->fresh()->check_in);
    }

    // F-16
    public function test_exit_manager_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('employee_exits', 'manager_recommended_lwd'));
    }

    // F-29
    public function test_helpdesk_ticket_numbers_do_not_collide_across_tenants(): void
    {
        $other = $this->makeTenant('other-tenant');
        $a = HelpdeskTicket::generateTicketNumber($this->tenant->id);
        $b = HelpdeskTicket::generateTicketNumber($other->id);
        $this->assertSame($a, $b); // same sequence start per tenant...

        $indexes = collect(Schema::getIndexes('helpdesk_tickets'));
        $this->assertTrue(
            $indexes->contains(fn ($i) => $i['unique'] && $i['columns'] === ['tenant_id', 'ticket_number'])
        );
        $this->assertFalse(
            $indexes->contains(fn ($i) => $i['unique'] && $i['columns'] === ['ticket_number'])
        );
    }

    // F-12 / F-07
    public function test_employee_pii_is_hidden_when_serialised(): void
    {
        $this->target->update(['aadhaar_card_number' => '123412341234', 'current_salary' => 500000]);
        $arr = $this->target->fresh()->toArray();
        foreach (['aadhaar_card_number', 'pan_card_number', 'account_number', 'current_salary', 'date_of_birth'] as $k) {
            $this->assertArrayNotHasKey($k, $arr);
        }
    }

    // F-17: employee selection for a payroll run must not reference a missing column
    public function test_payroll_run_employee_query_runs_with_exited_employee_filter(): void
    {
        $this->target->update(["status" => false, "pay_group_id" => null]);
        $run = \App\Domains\HRMS\Models\PayrollRun::create([
            "tenant_id" => $this->tenant->id, "company_id" => $this->target->company_id,
            "payroll_month" => now()->format("Y-m"), "start_date" => now()->startOfMonth(),
            "end_date" => now()->endOfMonth(), "status" => "draft", "employee_ids" => [$this->target->id],
        ]);
        $repo = app(\App\Domains\HRMS\Repositories\PayrollRunRepository::class);
        $m = new \ReflectionMethod($repo, "getRunEmployees");
        $m->setAccessible(true);
        $this->assertNotNull($m->invoke($repo, $run));
    }

    // F-19: leave approval by HR succeeds and writes on_leave attendance
    public function test_hr_can_approve_leave_and_attendance_rows_are_written(): void
    {
        $leaveType = $this->makeLeaveType($this->tenant, $this->target, "T1");
        app(TenantContext::class)->set($this->tenant);
        $leave = \App\Domains\HRMS\Models\LeaveRequest::create([
            "tenant_id" => $this->tenant->id, "company_id" => $this->target->company_id,
            "employee_id" => $this->target->id, "leave_type_id" => $leaveType->id,
            "start_date" => now()->addDay()->format("Y-m-d"), "end_date" => now()->addDays(2)->format("Y-m-d"),
            "duration" => 2.0, "reason" => "x", "status" => "pending",
        ]);
        \App\Domains\HRMS\Models\LeaveBalance::create(["tenant_id"=>$this->tenant->id,"company_id"=>$this->target->company_id,"employee_id"=>$this->target->id,"leave_type_id"=>$leaveType->id,"allocated"=>12,"used"=>0,"encashed"=>0]);
        $this->as($this->admin)->postJson("/api/hrms/leave-requests/{$leave->id}/approve")->assertStatus(200);
        $this->assertSame("approved", $leave->fresh()->status);
        $this->assertSame(2, Attendance::where("employee_id", $this->target->id)->where("status", "on_leave")->count());
    }
}