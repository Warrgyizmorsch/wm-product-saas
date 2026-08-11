<?php

namespace Tests\Feature\HRMS;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\OvertimeRequest;
use App\Domains\Production\Models\ProductionShift;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\HRMS\Concerns\BuildsHrmsOrgStructure;
use Tests\TestCase;

class HrmsOvertimeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsHrmsOrgStructure;

    private function makeHrManager(Tenant $tenant, string $email): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'HR Manager',
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

    public function test_validation_succeeds_when_both_fields_are_provided(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = $this->makeTenant('test-tenant');
        $hrManager = $this->makeHrManager($tenant, 'hr@example.com');

        $response = $this->actingAs($hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('hrms.overtime.update-settings'), [
                'auto_overtime_threshold_hours' => 2.0,
                'min_overtime_request_hours' => 1.5,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertEquals(2.0, $tenant->settings['auto_overtime_threshold_hours']);
        $this->assertEquals(1.5, $tenant->settings['min_overtime_request_hours']);
    }

    public function test_validation_succeeds_when_only_threshold_is_provided(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = $this->makeTenant('test-tenant');
        $hrManager = $this->makeHrManager($tenant, 'hr@example.com');

        $response = $this->actingAs($hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('hrms.overtime.update-settings'), [
                'auto_overtime_threshold_hours' => 2.0,
                'min_overtime_request_hours' => '',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertEquals(2.0, $tenant->settings['auto_overtime_threshold_hours']);
        $this->assertNull($tenant->settings['min_overtime_request_hours']);
    }

    public function test_validation_succeeds_when_only_manual_request_is_provided(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = $this->makeTenant('test-tenant');
        $hrManager = $this->makeHrManager($tenant, 'hr@example.com');

        $response = $this->actingAs($hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('hrms.overtime.update-settings'), [
                'auto_overtime_threshold_hours' => '',
                'min_overtime_request_hours' => 1.5,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertNull($tenant->settings['auto_overtime_threshold_hours']);
        $this->assertEquals(1.5, $tenant->settings['min_overtime_request_hours']);
    }

    public function test_validation_fails_when_both_fields_are_blank(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = $this->makeTenant('test-tenant');
        $hrManager = $this->makeHrManager($tenant, 'hr@example.com');

        $response = $this->actingAs($hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('hrms.overtime.update-settings'), [
                'auto_overtime_threshold_hours' => '',
                'min_overtime_request_hours' => '',
            ]);

        $response->assertSessionHasErrors(['auto_overtime_threshold_hours', 'min_overtime_request_hours']);
    }

    public function test_auto_overtime_not_generated_when_threshold_is_blank(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = $this->makeTenant('test-tenant');

        // Set threshold to null (blank)
        $tenant->update([
            'settings' => [
                'auto_overtime_threshold_hours' => null,
                'min_overtime_request_hours' => 1.0,
            ]
        ]);

        app(TenantContext::class)->set($tenant);

        $employee = $this->makeEmployee($tenant, 'Emp1');
        $shift = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $employee->company_id,
            'name' => 'Day Shift',
            'code' => 'DS-1',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'overtime_allowed' => true,
            'active' => true,
        ]);
        $employee->update(['shift_id' => $shift->id]);

        // Shift expected hours: 7.0 hours
        // Work hours: 9.0 hours => extraHours = 2.0 hours
        $attendance = Attendance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'date' => '2026-08-10',
            'check_in' => '2026-08-10 09:00:00',
            'check_out' => '2026-08-10 19:00:00',
            'location_type' => 'office',
            'status' => 'present',
            'total_break_hours' => 1.0,
            'total_work_hours' => 9.0,
        ]);

        $overtimeRequests = OvertimeRequest::where('employee_id', $employee->id)->get();
        $this->assertCount(0, $overtimeRequests);
    }

    public function test_auto_overtime_generated_when_threshold_is_met(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = $this->makeTenant('test-tenant');

        // Set threshold to 1.5 hours
        $tenant->update([
            'settings' => [
                'auto_overtime_threshold_hours' => 1.5,
                'min_overtime_request_hours' => 1.0,
            ]
        ]);

        app(TenantContext::class)->set($tenant);

        $employee = $this->makeEmployee($tenant, 'Emp2');
        $shift = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $employee->company_id,
            'name' => 'Day Shift',
            'code' => 'DS-2',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'overtime_allowed' => true,
            'active' => true,
        ]);
        $employee->update(['shift_id' => $shift->id]);

        // Shift expected hours: 7.0 hours
        // Work hours: 9.0 hours => extraHours = 2.0 hours
        // 2.0 >= 1.5 => should generate overtime request!
        $attendance = Attendance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'date' => '2026-08-10',
            'check_in' => '2026-08-10 09:00:00',
            'check_out' => '2026-08-10 19:00:00',
            'location_type' => 'office',
            'status' => 'present',
            'total_break_hours' => 1.0,
            'total_work_hours' => 9.0,
        ]);

        $overtimeRequests = OvertimeRequest::where('employee_id', $employee->id)->get();
        $this->assertCount(1, $overtimeRequests);
        $overtime = $overtimeRequests->first();
        $this->assertEquals('approved', $overtime->status);
        $this->assertEquals(2.0, $overtime->duration_hours);
        $this->assertEquals('17:00:00', $overtime->start_time);
        $this->assertEquals('19:00:00', $overtime->end_time);
    }

    public function test_auto_overtime_not_generated_when_threshold_is_not_met(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = $this->makeTenant('test-tenant');

        // Set threshold to 2.5 hours
        $tenant->update([
            'settings' => [
                'auto_overtime_threshold_hours' => 2.5,
                'min_overtime_request_hours' => 1.0,
            ]
        ]);

        app(TenantContext::class)->set($tenant);

        $employee = $this->makeEmployee($tenant, 'Emp3');
        $shift = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'company_id' => $employee->company_id,
            'name' => 'Day Shift',
            'code' => 'DS-3',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'overtime_allowed' => true,
            'active' => true,
        ]);
        $employee->update(['shift_id' => $shift->id]);

        // Shift expected hours: 7.0 hours
        // Work hours: 9.0 hours => extraHours = 2.0 hours
        // 2.0 < 2.5 => should NOT generate overtime request!
        $attendance = Attendance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'date' => '2026-08-10',
            'check_in' => '2026-08-10 09:00:00',
            'check_out' => '2026-08-10 19:00:00',
            'location_type' => 'office',
            'status' => 'present',
            'total_break_hours' => 1.0,
            'total_work_hours' => 9.0,
        ]);

        $overtimeRequests = OvertimeRequest::where('employee_id', $employee->id)->get();
        $this->assertCount(0, $overtimeRequests);
    }
}
