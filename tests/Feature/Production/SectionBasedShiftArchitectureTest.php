<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\SchedulingService;
use App\Domains\Production\Services\WorkCenterSectionService;
use App\Domains\Production\Services\WorkCenterService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SectionBasedShiftArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $engineer;
    private SchedulingService $schedulingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name'   => 'Architecture Test Tenant',
            'slug'   => 'arch-tenant',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Admin User',
            'email'     => 'admin@arch.test',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->engineer = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Engineer User',
            'email'     => 'engineer@arch.test',
            'password'  => bcrypt('password'),
            'role'      => 'production_engineer',
        ]);

        $this->schedulingService = app(SchedulingService::class);
    }

    private function createShift(string $name, string $code, string $start = '08:00:00', string $end = '16:00:00', bool $active = true): ProductionShift
    {
        return ProductionShift::create([
            'tenant_id'        => $this->tenant->id,
            'name'             => $name,
            'code'             => $code,
            'start_time'       => $start,
            'end_time'         => $end,
            'break_minutes'    => 30,
            'overtime_allowed' => false,
            'active'           => $active,
        ]);
    }

    private function createWorkCenter(string $name = 'Fabrication Line', string $code = 'WC-FAB'): WorkCenter
    {
        return WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => $name,
            'code'                  => $code,
            'work_center_type'      => 'machining',
            'efficiency_percentage' => 100.0,
            'status'                => 'active',
        ]);
    }

    private function createSection(WorkCenter $wc, string $name, string $code, string $status = 'active'): WorkCenterSection
    {
        return WorkCenterSection::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => $name,
            'code'           => $code,
            'status'         => $status,
        ]);
    }

    /**
     * Requirement 1: A Work Center with multiple Sections derives the union of their shifts.
     */
    public function test_work_center_with_multiple_sections_derives_union_of_shifts(): void
    {
        $wc = $this->createWorkCenter();
        $sec1 = $this->createSection($wc, 'Section 1', 'SEC-01');
        $sec2 = $this->createSection($wc, 'Section 2', 'SEC-02');

        $shiftMorning = $this->createShift('Morning Shift', 'MORN', '06:00:00', '14:00:00');
        $shiftEvening = $this->createShift('Evening Shift', 'EVE', '14:00:00', '22:00:00');

        $sec1->shifts()->attach($shiftMorning->id, [
            'tenant_id'        => $this->tenant->id,
            'target_headcount' => 2,
            'active'           => true,
        ]);
        $sec2->shifts()->attach($shiftEvening->id, [
            'tenant_id'        => $this->tenant->id,
            'target_headcount' => 3,
            'active'           => true,
        ]);

        $effectiveShifts = $wc->getEffectiveShifts();

        $this->assertCount(2, $effectiveShifts);
        $this->assertTrue($effectiveShifts->contains('id', $shiftMorning->id));
        $this->assertTrue($effectiveShifts->contains('id', $shiftEvening->id));
    }

    /**
     * Requirement 2: A shift assigned to multiple Sections appears only once in the Work Center's effective shifts.
     */
    public function test_shared_shift_across_multiple_sections_is_deduplicated(): void
    {
        $wc = $this->createWorkCenter();
        $sec1 = $this->createSection($wc, 'Cutting Section', 'SEC-CUT');
        $sec2 = $this->createSection($wc, 'Welding Section', 'SEC-WLD');

        $sharedShift = $this->createShift('Day Shift', 'DAY', '08:00:00', '16:00:00');

        $sec1->shifts()->attach($sharedShift->id, [
            'tenant_id'        => $this->tenant->id,
            'target_headcount' => 2,
            'active'           => true,
        ]);
        $sec2->shifts()->attach($sharedShift->id, [
            'tenant_id'        => $this->tenant->id,
            'target_headcount' => 4,
            'active'           => true,
        ]);

        $effectiveShifts = $wc->getEffectiveShifts();

        $this->assertCount(1, $effectiveShifts);
        $this->assertEquals('Day Shift', $effectiveShifts->first()->name);
        $this->assertEquals($sharedShift->id, $effectiveShifts->first()->id);
    }

    /**
     * Requirement 3 & 4: Assigning a shift to a Section does not require a Work Center-Shift assignment,
     * and a Section may use a shift never assigned to its parent Work Center.
     */
    public function test_section_shift_assignment_does_not_require_work_center_shift_assignment(): void
    {
        $wc = $this->createWorkCenter();
        $shift = $this->createShift('Graveyard Shift', 'GRAVE', '00:00:00', '08:00:00');

        $sectionService = app(WorkCenterSectionService::class);
        $dto = new \App\Domains\Production\DTO\WorkCenterSectionDTO(
            work_center_id: $wc->id,
            name: 'Laser Cutting',
            code: 'SEC-LASER',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 2],
            machine_ids: []
        );

        $section = $sectionService->create($dto, $this->tenant->id);

        $this->assertNotNull($section);
        $this->assertDatabaseHas('production_section_shifts', [
            'section_id'       => $section->id,
            'shift_id'         => $shift->id,
            'tenant_id'        => $this->tenant->id,
            'target_headcount' => 2,
            'active'           => true,
        ]);

        // Effective shifts on Work Center should now reflect this shift directly
        $this->assertCount(1, $wc->getEffectiveShifts());
        $this->assertEquals('Graveyard Shift', $wc->getEffectiveShifts()->first()->name);
    }

    /**
     * Requirement 5: Inactive Section–Shift assignments (active = false) are excluded.
     */
    public function test_inactive_section_shift_assignment_pivot_is_excluded(): void
    {
        $wc = $this->createWorkCenter();
        $sec = $this->createSection($wc, 'Milling Section', 'SEC-MIL');

        $activeShift = $this->createShift('Active Shift', 'ACT', '08:00:00', '16:00:00');
        $inactivePivotShift = $this->createShift('Suspended Assignment Shift', 'SUSP', '16:00:00', '00:00:00');

        $sec->shifts()->attach($activeShift->id, [
            'tenant_id'        => $this->tenant->id,
            'target_headcount' => 2,
            'active'           => true,
        ]);
        $sec->shifts()->attach($inactivePivotShift->id, [
            'tenant_id'        => $this->tenant->id,
            'target_headcount' => 1,
            'active'           => false, // Inactive on pivot
        ]);

        $effectiveShifts = $wc->getEffectiveShifts(true);

        $this->assertCount(1, $effectiveShifts);
        $this->assertEquals('Active Shift', $effectiveShifts->first()->name);
        $this->assertFalse($effectiveShifts->contains('id', $inactivePivotShift->id));

        // When onlyActive=false, all assigned shifts should be returned
        $allAssignedShifts = $wc->getEffectiveShifts(false);
        $this->assertCount(2, $allAssignedShifts);
    }

    /**
     * Requirement 6: Inactive Sections and inactive shifts are handled according to verified status semantics.
     */
    public function test_inactive_sections_and_inactive_shifts_are_filtered_from_effective_shifts(): void
    {
        $wc = $this->createWorkCenter();

        $activeSec = $this->createSection($wc, 'Active Section', 'SEC-ACT', 'active');
        $inactiveSec = $this->createSection($wc, 'Inactive Section', 'SEC-INACT', 'inactive');

        $validShift = $this->createShift('Valid Shift', 'SH-VAL', '08:00:00', '16:00:00', true);
        $inactiveMasterShift = $this->createShift('Inactive Master Shift', 'SH-INACT-M', '16:00:00', '00:00:00', false);
        $shiftOnInactiveSec = $this->createShift('Shift on Inactive Sec', 'SH-INSEC', '00:00:00', '08:00:00', true);

        // Attach valid shift and disabled shift to active section
        $activeSec->shifts()->attach($validShift->id, ['tenant_id' => $this->tenant->id, 'target_headcount' => 1, 'active' => true]);
        $activeSec->shifts()->attach($inactiveMasterShift->id, ['tenant_id' => $this->tenant->id, 'target_headcount' => 1, 'active' => true]);

        // Attach valid shift to inactive section
        $inactiveSec->shifts()->attach($shiftOnInactiveSec->id, ['tenant_id' => $this->tenant->id, 'target_headcount' => 1, 'active' => true]);

        $effective = $wc->getEffectiveShifts(true);

        $this->assertCount(1, $effective);
        $this->assertEquals('Valid Shift', $effective->first()->name);
        $this->assertFalse($effective->contains('id', $inactiveMasterShift->id));
        $this->assertFalse($effective->contains('id', $shiftOnInactiveSec->id));
    }

    /**
     * Requirement 7: A Work Center with no applicable Section shifts does not silently fall back to STD or generic shift.
     */
    public function test_work_center_with_no_shifts_does_not_fall_back_to_std(): void
    {
        $wc = $this->createWorkCenter('Empty WC', 'WC-EMPTY');
        // No sections or shifts assigned

        $cachedShifts = $this->schedulingService->getCachedShifts($wc);

        $this->assertTrue($cachedShifts->isEmpty(), 'Expected getCachedShifts to return an empty collection rather than STD fallback.');
        $this->assertFalse($cachedShifts->contains('code', 'STD'));

        // Capacity calculation should be 0.0 when no shifts exist
        $capacity = $this->schedulingService->calculateCapacity($wc->id, Carbon::parse('2026-07-06'));
        $this->assertEquals(0.0, $capacity);
    }

    /**
     * Requirement 8: Scheduling uses the effective shifts derived from Sections.
     */
    public function test_scheduling_uses_effective_shifts_derived_from_sections(): void
    {
        $wc = $this->createWorkCenter('CNC Cell', 'WC-SCHED');
        $machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'CNC Mill 1',
            'code'           => 'M-CNC1',
            'status'         => 'active',
        ]);

        // Create Section and assign custom 06:00 - 14:00 shift (8h - 30m break = 450 mins)
        $shift = $this->createShift('Early Bird Shift', 'SH-EARLY', '06:00:00', '14:00:00');
        $sec = $this->createSection($wc, 'Precision Milling', 'SEC-PREC');
        $sec->shifts()->attach($shift->id, [
            'tenant_id'        => $this->tenant->id,
            'target_headcount' => 2,
            'active'           => true,
        ]);

        $date = Carbon::parse('2026-07-06'); // Monday

        // Calculate capacity: 450m * 100% efficiency = 450m
        $capacity = $this->schedulingService->calculateCapacity($wc->id, $date);
        $this->assertEquals(450.0, $capacity);
    }

    /**
     * Requirement 9: Existing date, calendar, routing, and production-order scheduling behavior remains correct.
     */
    public function test_scheduling_pipeline_respects_calendar_and_section_shifts(): void
    {
        $wc = $this->createWorkCenter('Assembly Area', 'WC-PIPELINE');
        $machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'Assembly Station',
            'code'           => 'M-ASSY',
            'status'         => 'active',
        ]);

        $cal = ProductionCalendar::create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Weekday Calendar',
            'working_days' => [1, 2, 3, 4, 5],
            'is_default'   => true,
        ]);
        $wc->update(['production_calendar_id' => $cal->id]);

        $shift = $this->createShift('Day Shift', 'DAY', '08:00:00', '16:00:00');
        $sec = $this->createSection($wc, 'Final Assembly', 'SEC-FINAL');
        $sec->shifts()->attach($shift->id, [
            'tenant_id'        => $this->tenant->id,
            'target_headcount' => 1,
            'active'           => true,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Widget',
            'sku'       => 'WDG-01',
            'type'      => 'finished_good',
            'status'    => 'active',
        ]);

        $routing = Routing::create([
            'tenant_id'  => $this->tenant->id,
            'product_id' => $product->id,
            'name'       => 'Widget Routing',
            'status'     => 'approved',
        ]);

        $routingOp = RoutingOperation::create([
            'tenant_id'               => $this->tenant->id,
            'routing_id'              => $routing->id,
            'sequence'                => 1,
            'operation_number'        => 'OP-10',
            'name'                    => 'Assembly Operation',
            'operation_type'          => 'manufacturing',
            'work_center_id'          => $wc->id,
            'machine_id'              => $machine->id,
            'setup_time_minutes'      => 0,
            'processing_time_minutes' => 60,
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-ARCH-01',
            'product_id'       => $product->id,
            'routing_id'       => $routing->id,
            'quantity_ordered' => 1.0,
            'start_date'       => now(),
            'end_date'         => now()->addDays(5),
            'status'           => ProductionOrder::STATUS_RELEASED,
        ]);

        ProductionOrderOperation::create([
            'tenant_id'               => $this->tenant->id,
            'production_order_id'     => $order->id,
            'routing_operation_id'    => $routingOp->id,
            'sequence'                => 1,
            'operation_number'        => 'OP-10',
            'name'                    => 'Assembly Operation',
            'work_center_id'          => $wc->id,
            'machine_id'              => $machine->id,
            'setup_time_planned'      => 0,
            'processing_time_planned' => 60,
            'status'                  => 'waiting',
        ]);

        $start = Carbon::parse('2026-07-06 09:00:00'); // Monday 9 AM
        $schedule = $this->schedulingService->generateSchedule($order, $start, 'forward');

        $this->assertNotNull($schedule);
        $this->assertCount(1, $schedule->operations);
        $op = $schedule->operations->first();

        $this->assertEquals('2026-07-06 09:00:00', $op->planned_start->toDateTimeString());
        $this->assertEquals('2026-07-06 10:00:00', $op->planned_finish->toDateTimeString());
        $this->assertEquals($wc->id, $op->work_center_id);
        $this->assertEquals($machine->id, $op->machine_id);
    }

    /**
     * Requirement 10: Work Center and Section CRUD no longer attempt to synchronize or validate obsolete shift pivot.
     */
    public function test_crud_endpoints_do_not_attempt_to_validate_or_sync_obsolete_pivot(): void
    {
        $shift = $this->createShift('Morning Shift', 'MORN-CRUD');

        // 1. Create Work Center via Controller without shifts field
        $wcResponse = $this->actingAs($this->engineer)
            ->withHeader('X-Tenant', 'arch-tenant')
            ->post(route('production.work-centers.store'), [
                'name'                  => 'New Stamping Station',
                'code'                  => 'WC-STAMP',
                'work_center_type'      => 'machining',
                'status'                => 'active',
                'efficiency_percentage' => 95.0,
                'cost_per_hour'         => 60.00,
            ]);

        $wcResponse->assertRedirect();
        $wc = WorkCenter::where('code', 'WC-STAMP')->firstOrFail();

        // 2. Create Section under WC with shift via Controller
        $secResponse = $this->actingAs($this->engineer)
            ->withHeader('X-Tenant', 'arch-tenant')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'             => $wc->id,
                'name'                       => 'Primary Stamping',
                'code'                       => 'SEC-STAMP-1',
                'status'                     => 'active',
                'supports_manual_production' => false,
                'shifts'                     => [
                    [
                        'shift_id'         => $shift->id,
                        'target_headcount' => 3,
                    ],
                ],
            ]);

        $secResponse->assertRedirect();
        $section = WorkCenterSection::where('code', 'SEC-STAMP-1')->firstOrFail();

        $this->assertDatabaseHas('production_section_shifts', [
            'section_id'       => $section->id,
            'shift_id'         => $shift->id,
            'target_headcount' => 3,
            'active'           => true,
        ]);

        // Effective shifts on Work Center automatically updated
        $this->assertCount(1, $wc->getEffectiveShifts());
        $this->assertEquals('Morning Shift', $wc->getEffectiveShifts()->first()->name);

        // 3. Update Work Center name without shifts
        $updateResponse = $this->actingAs($this->engineer)
            ->withHeader('X-Tenant', 'arch-tenant')
            ->put(route('production.work-centers.update', $wc->id), [
                'name'                  => 'Stamping Station Revised',
                'code'                  => 'WC-STAMP',
                'status'                => 'active',
                'efficiency_percentage' => 95.0,
                'cost_per_hour'         => 65.00,
            ]);

        $updateResponse->assertRedirect();
        $wc->refresh();
        $this->assertEquals('Stamping Station Revised', $wc->name);
        $this->assertCount(1, $wc->getEffectiveShifts());
    }

    /**
     * Requirement 11 & 12: Database migrations schema validation — obsolete table dropped, new table present.
     */
    public function test_schema_validates_production_section_shifts_and_absence_of_work_center_shifts(): void
    {
        $this->assertTrue(
            Schema::hasTable('production_section_shifts'),
            'Expected table production_section_shifts to exist.'
        );

        $this->assertFalse(
            Schema::hasTable('production_work_center_shifts'),
            'Expected legacy table production_work_center_shifts to be dropped.'
        );

        $columns = Schema::getColumnListing('production_section_shifts');
        $this->assertContains('tenant_id', $columns);
        $this->assertContains('section_id', $columns);
        $this->assertContains('shift_id', $columns);
        $this->assertContains('target_headcount', $columns);
        $this->assertContains('active', $columns);
    }
}
