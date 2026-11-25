<?php

namespace Tests\Feature\HRMS;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceRule;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\HRMS\Concerns\BuildsHrmsOrgStructure;
use Tests\TestCase;

class MockGpsDetectionTest extends TestCase
{
    use RefreshDatabase;
    use BuildsHrmsOrgStructure;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->makeTenant('mock-gps-tenant');
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'HR Admin',
            'email'     => 'admin@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);
    }

    public function test_check_in_rejects_mocked_location_flag(): void
    {
        $employee = $this->makeEmployee($this->tenant, 'MockTester');
        app(TenantContext::class)->set($this->tenant);

        $this->actingAs($this->user)->withHeader('X-Tenant', 'mock-gps-tenant');

        $response = $this->postJson(route('api.hrms.attendance.check-in'), [
            'employee_id' => $employee->id,
            'latitude'    => 19.0760,
            'longitude'   => 72.8777,
            'is_mocked'   => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Mock/fake location detected. Please disable mock location apps to record attendance.');
    }

    public function test_check_out_rejects_impossible_teleportation_speed(): void
    {
        $employee = $this->makeEmployee($this->tenant, 'SpeedTester');
        app(TenantContext::class)->set($this->tenant);

        // Create an attendance record checked in 10 minutes ago in Mumbai (19.0760, 72.8777)
        $attendance = Attendance::create([
            'tenant_id'           => $this->tenant->id,
            'employee_id'         => $employee->id,
            'date'                => Carbon::today()->toDateString(),
            'check_in'            => Carbon::now()->subMinutes(10),
            'check_in_latitude'   => 19.0760,
            'check_in_longitude'  => 72.8777,
            'location_type'       => 'office',
            'status'              => 'present',
        ]);

        $this->actingAs($this->user)->withHeader('X-Tenant', 'mock-gps-tenant');

        // Clock out in Bangalore (12.9716, 77.5946 - 840 km away in 10 minutes = 5040 km/h)
        $response = $this->postJson(route('api.hrms.attendance.check-out', $attendance->id), [
            'latitude'  => 12.9716,
            'longitude' => 77.5946,
            'is_mocked' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('impossible travel speed', $response->json('message'));
    }
}
