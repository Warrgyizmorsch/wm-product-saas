<?php

namespace Tests\Feature\HRMS;

use App\Domains\HRMS\Jobs\ProcessBiometricAttendance;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceRule;
use App\Domains\HRMS\Models\BiometricDevice;
use App\Domains\HRMS\Models\BiometricPunchLog;
use App\Domains\Production\Models\ProductionShift;
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
            'name'      => 'Admin User',
            'email'     => 'admin@example.com',
            'password'  => bcrypt('password'),
        ]);
    }

    public function test_biometric_sync_endpoint_records_punch_logs_and_calculates_attendance_hours(): void
    {
        $employee = $this->makeEmployee($this->tenant, 'Bio');
        $employee->update(['employee_id' => '9999']);

        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        // Register virtual simulator device
        $device = BiometricDevice::create([
            'tenant_id'     => $this->tenant->id,
            'company_id'    => $employee->company_id,
            'name'          => 'Office Entrance Scanner',
            'device_serial' => 'SN-1029384756',
            'status'        => true,
            'port'          => 4370,
        ]);

        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant');

        // 1. Simulate check-in punch at 09:00 AM on August 18, 2026
        $response1 = $this->post(route('api.hrms.attendance.biometric-sync'), [
            'biometric_device_id' => $device->id,
            'logs' => [
                [
                    'biometric_id' => '9999',
                    'timestamp'    => '2026-08-18 09:00:00',
                    'punch_type'   => 'auto',
                ]
            ]
        ]);

        $response1->assertStatus(200)
            ->assertJson(['success' => true]);

        // Assert raw log is stored
        $this->assertDatabaseHas('biometric_punch_logs', [
            'employee_id' => $employee->id,
            'punch_time'  => '2026-08-18 09:00:00',
        ]);

        // Assert attendance is created with check-in
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
                    'timestamp'    => '2026-08-18 18:00:00',
                    'punch_type'   => 'auto',
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

    public function test_web_biometric_device_crud_lifecycle(): void
    {
        $employee = $this->makeEmployee($this->tenant, 'WebDev');
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        // Enable office biometric rule
        AttendanceRule::create([
            'tenant_id'        => $this->tenant->id,
            'company_id'       => $employee->company_id,
            'office_biometric' => true,
        ]);

        $this->actingAs($this->user)->withHeader('X-Tenant', 'test-tenant');

        // 1. Visit Index Page
        $responseIndex = $this->get(route('hrms.biometric-devices.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Biometric Device Master');

        // 2. Store Device via Web
        $responseStore = $this->post(route('hrms.biometric-devices.store'), [
            'name'             => 'Gate 1 Turnstile',
            'device_serial'    => 'ZK-GT1-001',
            'company_id'       => $employee->company_id,
            'business_unit_id' => $employee->business_unit_id,
            'branch_id'        => $employee->branch_id,
            'ip_address'       => '192.168.1.101',
            'port'             => 4370,
            'status'           => 1,
        ]);

        $responseStore->assertRedirect(route('hrms.biometric-devices.index'));
        $this->assertDatabaseHas('biometric_devices', [
            'tenant_id'     => $this->tenant->id,
            'device_serial' => 'ZK-GT1-001',
            'name'          => 'Gate 1 Turnstile',
        ]);

        $device = BiometricDevice::where('device_serial', 'ZK-GT1-001')->first();
        $this->assertNotNull($device);

        // 3. Update Device via Web
        $responseUpdate = $this->put(route('hrms.biometric-devices.update', $device->id), [
            'name'             => 'Gate 1 Turnstile (Renamed)',
            'device_serial'    => 'ZK-GT1-001',
            'company_id'       => $employee->company_id,
            'business_unit_id' => $employee->business_unit_id,
            'branch_id'        => $employee->branch_id,
            'ip_address'       => '192.168.1.102',
            'port'             => 5005,
            'status'           => 1,
        ]);

        $responseUpdate->assertRedirect(route('hrms.biometric-devices.index'));
        $this->assertDatabaseHas('biometric_devices', [
            'id'   => $device->id,
            'name' => 'Gate 1 Turnstile (Renamed)',
            'port' => 5005,
        ]);

        // 4. Web Simulator Trigger Punch
        $responseSim = $this->post(route('hrms.biometric-devices.simulate-punch'), [
            'employee_id'         => $employee->id,
            'biometric_device_id' => $device->id,
            'punch_time'          => '2026-08-20 09:15:00',
            'punch_type'          => 'in',
        ]);

        $responseSim->assertRedirect(route('hrms.biometric-devices.index', ['tab' => 'simulator']));
        $this->assertDatabaseHas('biometric_punch_logs', [
            'employee_id' => $employee->id,
            'punch_time'  => '2026-08-20 09:15:00',
        ]);

        // 5. Delete Device via Web
        $responseDelete = $this->delete(route('hrms.biometric-devices.destroy', $device->id));
        $responseDelete->assertRedirect(route('hrms.biometric-devices.index'));
        $this->assertDatabaseMissing('biometric_devices', [
            'id' => $device->id,
        ]);
    }

    public function test_api_biometric_device_crud_and_summary(): void
    {
        $employee = $this->makeEmployee($this->tenant, 'ApiDev');
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        $this->actingAs($this->user)->withHeader('X-Tenant', 'test-tenant');

        // 1. Create Device via API
        $responseCreate = $this->postJson(route('api.hrms.biometric-devices.store'), [
            'name'          => 'HQ Lobby Face Scanner',
            'device_serial' => 'ZK-HQ-LOBBY-01',
            'company_id'    => $employee->company_id,
            'ip_address'    => '10.0.0.50',
            'port'          => 4370,
            'status'        => true,
        ]);

        $responseCreate->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.device_serial', 'ZK-HQ-LOBBY-01');

        $deviceId = $responseCreate->json('data.id');

        // 2. Summary API
        $responseSummary = $this->getJson(route('api.hrms.biometric-devices.summary'));
        $responseSummary->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_devices', 1)
            ->assertJsonPath('data.active_devices', 1);

        // 3. Show Device API
        $responseShow = $this->getJson(route('api.hrms.biometric-devices.show', $deviceId));
        $responseShow->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'HQ Lobby Face Scanner');

        // 4. Update Device API
        $responseUpdate = $this->putJson(route('api.hrms.biometric-devices.update', $deviceId), [
            'name'          => 'HQ Lobby Face Scanner Pro',
            'device_serial' => 'ZK-HQ-LOBBY-01',
            'company_id'    => $employee->company_id,
            'port'          => 8080,
            'status'        => false,
        ]);

        $responseUpdate->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'HQ Lobby Face Scanner Pro');

        // 5. Simulate Punch API
        $responseSim = $this->postJson(route('api.hrms.biometric-devices.simulate-punch'), [
            'employee_id'         => $employee->id,
            'biometric_device_id' => $deviceId,
            'punch_time'          => '2026-08-21 08:55:00',
            'punch_type'          => 'in',
        ]);

        $responseSim->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee_id', $employee->id);

        // 6. Delete Device API
        $responseDelete = $this->deleteJson(route('api.hrms.biometric-devices.destroy', $deviceId));
        $responseDelete->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('biometric_devices', ['id' => $deviceId]);
    }

    public function test_adms_public_webhook_processes_punches_and_updates_ping(): void
    {
        $employee = $this->makeEmployee($this->tenant, 'AdmsUser');
        $employee->update(['employee_id' => 'EMP-ADMS-77']);

        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        $device = BiometricDevice::create([
            'tenant_id'     => $this->tenant->id,
            'company_id'    => $employee->company_id,
            'name'          => 'Plant ADMS Terminal',
            'device_serial' => 'ADMS-SN-998877',
            'status'        => true,
            'port'          => 4370,
        ]);

        // Push tab-separated records via ADMS webhook
        $payload = "EMP-ADMS-77\t2026-08-22 08:30:00\t0\nEMP-ADMS-77\t2026-08-22 17:30:00\t1\n";

        $response = $this->call(
            'POST',
            route('api.hrms.biometric.webhook', ['SN' => 'ADMS-SN-998877']),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $payload
        );

        $response->assertStatus(200);
        $this->assertStringContainsString('OK', $response->getContent());

        // Assert ping timestamp updated
        $device->refresh();
        $this->assertNotNull($device->last_ping_at);

        // Assert logs stored
        $this->assertDatabaseHas('biometric_punch_logs', [
            'employee_id' => $employee->id,
            'punch_time'  => '2026-08-22 08:30:00',
            'punch_type'  => 'in',
        ]);

        $this->assertDatabaseHas('biometric_punch_logs', [
            'employee_id' => $employee->id,
            'punch_time'  => '2026-08-22 17:30:00',
            'punch_type'  => 'out',
        ]);

        // Assert attendance calculation
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', '2026-08-22')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertEquals(9.00, (float)$attendance->total_work_hours);
    }

    public function test_late_arrival_status_is_marked_when_check_in_exceeds_shift_grace_period(): void
    {
        $employee = $this->makeEmployee($this->tenant, 'LateWorker');
        $employee->update(['employee_id' => 'LATE-01']);

        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        // Create a 09:00 AM shift with 15 min grace period
        $shift = ProductionShift::create([
            'tenant_id'            => $this->tenant->id,
            'company_id'           => $employee->company_id,
            'name'                 => 'Standard Morning Shift',
            'code'                 => 'SMS-09',
            'start_time'           => '09:00:00',
            'end_time'             => '18:00:00',
            'break_minutes'        => 60,
            'overtime_allowed'     => true,
            'active'               => true,
        ]);

        $employee->update(['shift_id' => $shift->id]);

        \App\Domains\HRMS\Models\AttendancePenalty::create([
            'tenant_id'            => $this->tenant->id,
            'company_id'           => $employee->company_id,
            'rule_type'            => 'late_arrival',
            'grace_period_minutes' => 15,
            'status'               => true,
        ]);

        // Punch in at 09:20 AM (Grace is 15 mins -> Late threshold 09:15)
        BiometricPunchLog::create([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $employee->id,
            'punch_time'  => Carbon::parse('2026-08-25 09:20:00'),
            'punch_type'  => 'in',
            'processed'   => false,
        ]);

        ProcessBiometricAttendance::dispatchSync($employee->id, '2026-08-25');

        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', '2026-08-25')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertEquals('late', $attendance->status);
    }

    public function test_biometric_device_test_connection_web_and_api(): void
    {
        $employee = $this->makeEmployee($this->tenant, 'PingTester');
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        $this->actingAs($this->user)->withHeader('X-Tenant', 'test-tenant');

        // Cloud ADMS device (without IP)
        $admsDevice = BiometricDevice::create([
            'tenant_id'     => $this->tenant->id,
            'company_id'    => $employee->company_id,
            'name'          => 'Cloud ADMS Device',
            'device_serial' => 'ADMS-001',
            'status'        => true,
            'last_ping_at'  => now(),
        ]);

        // Web endpoint ping test
        $resWeb = $this->post(route('hrms.biometric-devices.test-connection', $admsDevice->id));
        $resWeb->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('connected', true)
            ->assertJsonPath('mode', 'adms_push');

        // API endpoint ping test
        $resApi = $this->postJson(route('api.hrms.biometric-devices.test-connection', $admsDevice->id));
        $resApi->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.mode', 'adms_push');
    }
}
