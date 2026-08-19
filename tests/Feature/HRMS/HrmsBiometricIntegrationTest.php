<?php

namespace Tests\Feature\HRMS;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceBreak;
use App\Domains\HRMS\Models\BiometricDevice;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\HRMS\Concerns\BuildsHrmsOrgStructure;
use Tests\TestCase;

class HrmsBiometricIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsHrmsOrgStructure;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant('test-tenant');
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_biometric_sync_endpoint_records_punch_logs_and_calculates_attendance_hours(): void
    {
        $employee = $this->makeEmployee($this->tenant, 'Bio');
        
        // Set employee code to '9999'
        $employee->update(['employee_id' => '9999']);

        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        // Register virtual simulator device
        $device = BiometricDevice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $employee->company_id,
            'name' => 'Office Entrance Scanner',
            'device_serial' => 'SN-1029384756',
            'status' => true,
            'port' => 4370,
        ]);

        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant');

        // 1. Simulate check-in punch at 09:00 AM on August 18, 2026
        $response1 = $this->post(route('api.hrms.attendance.biometric-sync'), [
            'biometric_device_id' => $device->id,
            'logs' => [
                [
                    'biometric_id' => '9999',
                    'timestamp' => '2026-08-18 09:00:00',
                    'punch_type' => 'auto',
                ]
            ]
        ]);

        $response1->assertStatus(200)
            ->assertJson(['success' => true]);

        // Assert raw log is stored
        $this->assertDatabaseHas('biometric_punch_logs', [
            'employee_id' => $employee->id,
            'punch_time' => '2026-08-18 09:00:00',
        ]);

        // Since queue runs synchronously in testing default config or we can run calculations manually
        // Let's assert attendance is created with check-in
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', '2026-08-18')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertEquals('2026-08-18 09:00:00', $attendance->check_in->toDateTimeString());
        $this->assertNull($attendance->check_out);

        // 2. Simulate check-out punch at 06:00 PM (18:00) on August 18, 2026
        $response2 = $this->post(route('api.hrms.attendance.biometric-sync'), [
            'biometric_device_id' => $device->id,
            'logs' => [
                [
                    'biometric_id' => '9999',
                    'timestamp' => '2026-08-18 18:00:00',
                    'punch_type' => 'auto',
                ]
            ]
        ]);

        $response2->assertStatus(200);

        // Assert check-out and hours are correctly calculated
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);
        $attendance = $attendance->fresh();

        $this->assertEquals('2026-08-18 18:00:00', $attendance->check_out->toDateTimeString());

        // Total time = 9 hours (09:00 to 18:00).
        $this->assertEquals(9.00, (float)$attendance->total_work_hours);
        $this->assertEquals(0.00, (float)$attendance->total_break_hours);
    }
}
