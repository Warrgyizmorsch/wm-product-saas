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

    /** @test */
    public function can_create_holiday_for_calendar()
    {
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Calendar ABC',
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->from(route('production.calendars.edit', $calendar->id))
            ->post(route('production.calendars.holidays.store', $calendar->id), [
                'name' => 'Labor Day',
                'holiday_date' => '2026-09-07',
                'holiday_type' => 'public_holiday',
                'description' => 'Labor Day shut-down',
                'is_full_day' => true,
                'active' => true,
            ]);

        $response->assertRedirect(route('production.calendars.edit', $calendar->id));
        $this->assertDatabaseHas('production_calendar_holidays', [
            'tenant_id' => $this->tenantId,
            'production_calendar_id' => $calendar->id,
            'name' => 'Labor Day',
            'holiday_date' => '2026-09-07 00:00:00',
            'active' => 1,
        ]);
    }

    /** @test */
    public function can_update_holiday_for_calendar()
    {
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Calendar ABC',
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $holiday = \App\Domains\Production\Models\ProductionCalendarHoliday::create([
            'tenant_id' => $this->tenantId,
            'production_calendar_id' => $calendar->id,
            'name' => 'Original Name',
            'holiday_date' => '2026-09-07',
            'holiday_type' => 'public_holiday',
            'active' => true,
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->from(route('production.calendars.edit', $calendar->id))
            ->put(route('production.calendars.holidays.update', [$calendar->id, $holiday->id]), [
                'name' => 'Updated Name',
                'holiday_date' => '2026-09-08',
                'holiday_type' => 'maintenance_shutdown',
                'is_full_day' => false,
                'start_time' => '08:00',
                'end_time' => '12:00',
                'active' => false,
            ]);

        $response->assertRedirect(route('production.calendars.edit', $calendar->id));
        $this->assertDatabaseHas('production_calendar_holidays', [
            'id' => $holiday->id,
            'name' => 'Updated Name',
            'holiday_date' => '2026-09-08 00:00:00',
            'holiday_type' => 'maintenance_shutdown',
            'is_full_day' => 0,
            'start_time' => '08:00',
            'end_time' => '12:00',
            'active' => 0,
        ]);
    }

    /** @test */
    public function can_delete_holiday_for_calendar()
    {
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Calendar ABC',
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $holiday = \App\Domains\Production\Models\ProductionCalendarHoliday::create([
            'tenant_id' => $this->tenantId,
            'production_calendar_id' => $calendar->id,
            'name' => 'Original Name',
            'holiday_date' => '2026-09-07',
            'holiday_type' => 'public_holiday',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->from(route('production.calendars.edit', $calendar->id))
            ->delete(route('production.calendars.holidays.destroy', [$calendar->id, $holiday->id]));

        $response->assertRedirect(route('production.calendars.edit', $calendar->id));
        $this->assertDatabaseMissing('production_calendar_holidays', [
            'id' => $holiday->id,
        ]);
    }

    /** @test */
    public function partial_day_holiday_requires_start_and_end_times()
    {
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Calendar ABC',
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.calendars.holidays.store', $calendar->id), [
                'name' => 'Partial Day Fail',
                'holiday_date' => '2026-09-07',
                'holiday_type' => 'public_holiday',
                'is_full_day' => false,
                'start_time' => '',
                'end_time' => '',
            ]);

        $response->assertSessionHasErrors(['start_time', 'end_time']);
    }

    /** @test */
    public function partial_day_holiday_end_time_must_be_after_start_time()
    {
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Calendar ABC',
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.calendars.holidays.store', $calendar->id), [
                'name' => 'Partial Day Order Fail',
                'holiday_date' => '2026-09-07',
                'holiday_type' => 'public_holiday',
                'is_full_day' => false,
                'start_time' => '17:00',
                'end_time' => '08:00',
            ]);

        $response->assertSessionHasErrors(['end_time']);
    }

    /** @test */
    public function prevents_duplicate_holiday_dates_on_same_calendar()
    {
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Calendar ABC',
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        \App\Domains\Production\Models\ProductionCalendarHoliday::create([
            'tenant_id' => $this->tenantId,
            'production_calendar_id' => $calendar->id,
            'name' => 'Existing Holiday',
            'holiday_date' => '2026-09-07',
            'holiday_type' => 'public_holiday',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.calendars.holidays.store', $calendar->id), [
                'name' => 'Duplicate Holiday',
                'holiday_date' => '2026-09-07',
                'holiday_type' => 'other',
            ]);

        $response->assertSessionHasErrors(['holiday_date']);
    }

    /** @test */
    public function non_managers_cannot_manage_holidays()
    {
        $calendar = ProductionCalendar::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Calendar ABC',
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        $unauthorizedUser = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'operator',
        ]);

        $response = $this->actingAs($unauthorizedUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.calendars.holidays.store', $calendar->id), [
                'name' => 'Forbidden Day',
                'holiday_date' => '2026-09-07',
                'holiday_type' => 'public_holiday',
            ]);

        $response->assertStatus(403);
    }
}
