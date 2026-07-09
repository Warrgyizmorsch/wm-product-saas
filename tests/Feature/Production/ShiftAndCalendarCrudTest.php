<?php

namespace Tests\Feature\Production;

use App\Models\User;
use App\Models\Tenant;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\ProductionCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftAndCalendarCrudTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create([
            'id' => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'production_manager',
        ]);

        // Mock supervisor permissions to pass authorization checks
        $this->user->roles()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Production Manager',
            'slug' => 'production_manager'
        ]);

        $this->actingAs($this->user);
    }

    /** @test */
    public function can_list_shifts_and_calendars()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.shifts.index'));
        $response->assertOk();

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.calendars.index'));
        $response->assertOk();
    }

    /** @test */
    public function can_create_shift()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.shifts.store'), [
                'name' => 'Morning Shift A',
                'code' => 'MORN-A',
                'start_time' => '06:00',
                'end_time' => '14:00',
                'break_minutes' => 30,
                'overtime_allowed' => true,
                'active' => true,
            ]);

        $response->assertRedirect(route('production.shifts.index'));
        $this->assertDatabaseHas('production_shifts', [
            'tenant_id' => $this->tenantId,
            'name' => 'Morning Shift A',
            'code' => 'MORN-A',
        ]);
    }

    /** @test */
    public function can_create_calendar_with_working_days()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.calendars.store'), [
                'name' => 'Standard Production Calendar',
                'working_days' => [1, 2, 3, 4, 5],
                'is_default' => true,
            ]);

        $response->assertRedirect(route('production.calendars.index'));
        $this->assertDatabaseHas('production_calendars', [
            'tenant_id' => $this->tenantId,
            'name' => 'Standard Production Calendar',
            'is_default' => true,
        ]);
    }
}
