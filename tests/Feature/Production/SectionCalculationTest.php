<?php

namespace Tests\Feature\Production;

use App\Domains\Production\DTO\WorkCenterSectionDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\SectionCalculationService;
use App\Domains\Production\Services\WorkCenterSectionService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private WorkCenter $workCenterA;
    private WorkCenter $workCenterB;
    private SectionCalculationService $calcService;
    private WorkCenterSectionService $sectionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name'   => 'Tenant A',
            'slug'   => 'tenant-a',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Admin A',
            'email'     => 'admina@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->tenantB = Tenant::create([
            'name'   => 'Tenant B',
            'slug'   => 'tenant-b',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Admin B',
            'email'     => 'adminb@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->workCenterA = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Assembly Dept A',
            'code'      => 'WC-ASM-A',
            'status'    => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->workCenterB = WorkCenter::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Assembly Dept B',
            'code'      => 'WC-ASM-B',
            'status'    => WorkCenter::STATUS_ACTIVE,
        ]);

        $calcService = new class extends SectionCalculationService {
            public array $rates = [];
            public function calculateLaborCost(WorkCenterSection $section): array
            {
                if ($section->relationLoaded('shifts')) {
                    foreach ($section->shifts as $shift) {
                        if (array_key_exists($shift->id, $this->rates)) {
                            $shift->hourly_labor_rate = $this->rates[$shift->id];
                        }
                    }
                }
                return parent::calculateLaborCost($section);
            }
            public function recalculateSection(WorkCenterSection $section): WorkCenterSection
            {
                $shifts = $section->shifts()->where('production_shifts.tenant_id', $section->tenant_id)->get();
                foreach ($shifts as $shift) {
                    if (array_key_exists($shift->id, $this->rates)) {
                        $shift->hourly_labor_rate = $this->rates[$shift->id];
                    }
                }
                $section->setRelation('shifts', $shifts);
                return parent::recalculateSection($section);
            }
        };
        $this->calcService = $calcService;
        $this->app->instance(SectionCalculationService::class, $calcService);
        $this->sectionService = app(WorkCenterSectionService::class);
    }

    private function createShift(Tenant $tenant, string $name, string $code, string $start, string $end, ?float $rate = 100.0, int $breakMinutes = 30): ProductionShift
    {
        $shift = ProductionShift::create([
            'tenant_id'         => $tenant->id,
            'name'              => $name,
            'code'              => $code,
            'start_time'        => $start,
            'end_time'          => $end,
            'break_minutes'     => $breakMinutes,
            'active'            => true,
        ]);

        $this->calcService->rates[$shift->id] = $rate;

        return $shift;
    }

    private function createMachine(Tenant $tenant, string $name, string $code, float $capacity = 50.0, string $unit = 'units/hr'): Machine
    {
        return Machine::create([
            'tenant_id'     => $tenant->id,
            'name'          => $name,
            'code'          => $code,
            'status'        => Machine::STATUS_ACTIVE,
            'capacity'      => $capacity,
            'capacity_unit' => $unit,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // CAPACITY TESTS
    // ─────────────────────────────────────────────────────────────────────────────

    public function test_capacity_sums_multiple_assigned_machines(): void
    {
        $m1 = $this->createMachine($this->tenantA, 'CNC 1', 'M-01', 40.0);
        $m2 = $this->createMachine($this->tenantA, 'CNC 2', 'M-02', 30.0);
        $shift = $this->createShift($this->tenantA, 'Day', 'SH-DAY', '08:00', '16:00');
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $dto = new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Milling Cell',
            code: 'SEC-MILL',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 2],
            machine_ids: [$m1->id, $m2->id]
        );

        $section = $this->sectionService->create($dto, $this->tenantA->id);

        $this->assertEquals(70.0, (float)$section->capacity_per_hour);
        $this->assertEquals(70.0, (float)$section->calculation_details['capacity']['capacity_per_hour']);
        $this->assertCount(2, $section->calculation_details['capacity']['machines']);
    }

    public function test_capacity_adds_manual_capacity_when_enabled(): void
    {
        $m1 = $this->createMachine($this->tenantA, 'Press 1', 'M-PR1', 40.0);
        $shift = $this->createShift($this->tenantA, 'Day', 'SH-DAY', '08:00', '16:00');
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $dto = new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Hybrid Section',
            code: 'SEC-HYB',
            supports_manual_production: true,
            manual_capacity_per_hour: 15.5,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 3],
            machine_ids: [$m1->id]
        );

        $section = $this->sectionService->create($dto, $this->tenantA->id);

        $this->assertEquals(55.5, (float)$section->capacity_per_hour);
        $this->assertTrue($section->calculation_details['capacity']['manual_production']['enabled']);
        $this->assertEquals(15.5, (float)$section->calculation_details['capacity']['manual_production']['effective_capacity']);
    }

    public function test_capacity_excludes_manual_capacity_when_disabled(): void
    {
        $m1 = $this->createMachine($this->tenantA, 'Press 2', 'M-PR2', 40.0);
        $shift = $this->createShift($this->tenantA, 'Day', 'SH-DAY', '08:00', '16:00');
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $dto = new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Machine Only',
            code: 'SEC-MCH',
            supports_manual_production: false,
            manual_capacity_per_hour: 25.0, // Stored in DB but disabled
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 1],
            machine_ids: [$m1->id]
        );

        $section = $this->sectionService->create($dto, $this->tenantA->id);

        $this->assertEquals(40.0, (float)$section->capacity_per_hour);
        $this->assertFalse($section->calculation_details['capacity']['manual_production']['enabled']);
        $this->assertEquals(0.0, (float)$section->calculation_details['capacity']['manual_production']['effective_capacity']);
    }

    public function test_capacity_returns_zero_when_no_contributing_resources(): void
    {
        $shift = $this->createShift($this->tenantA, 'Day', 'SH-DAY', '08:00', '16:00');
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $dto = new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Empty Section',
            code: 'SEC-EMPTY',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 1],
            machine_ids: []
        );

        $section = $this->sectionService->create($dto, $this->tenantA->id);

        $this->assertEquals(0.0, (float)$section->capacity_per_hour);
    }

    public function test_worker_counts_and_shift_overlaps_do_not_alter_hourly_capacity(): void
    {
        $m1 = $this->createMachine($this->tenantA, 'Lathe 1', 'M-L1', 50.0);
        $s1 = $this->createShift($this->tenantA, 'Morning', 'SH-M1', '08:00', '16:00');
        $s2 = $this->createShift($this->tenantA, 'Overlap', 'SH-OV1', '12:00', '20:00');
        $this->workCenterA->shifts()->attach([$s1->id, $s2->id], ['tenant_id' => $this->tenantA->id]);

        $dto = new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Multi Shift Section',
            code: 'SEC-MS',
            supports_manual_production: true,
            manual_capacity_per_hour: 20.0,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$s1->id, $s2->id],
            shift_workers: [$s1->id => 10, $s2->id => 50], // Large worker count
            machine_ids: [$m1->id]
        );

        $section = $this->sectionService->create($dto, $this->tenantA->id);

        // Capacity is 50 + 20 = 70, unchanged by 60 total workers or 4-hour overlap
        $this->assertEquals(70.0, (float)$section->capacity_per_hour);
    }

    public function test_machine_assigned_to_another_section_is_not_included(): void
    {
        $m1 = $this->createMachine($this->tenantA, 'M1', 'M-1', 40.0);
        $m2 = $this->createMachine($this->tenantA, 'M2', 'M-2', 60.0);
        $shift = $this->createShift($this->tenantA, 'Day', 'SH-D', '08:00', '16:00');
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $sec1 = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Sec 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 1],
            machine_ids: [$m1->id]
        ), $this->tenantA->id);

        $sec2 = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Sec 2',
            code: 'SEC-2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 1],
            machine_ids: [$m2->id]
        ), $this->tenantA->id);

        $this->assertEquals(40.0, (float)$sec1->capacity_per_hour);
        $this->assertEquals(60.0, (float)$sec2->capacity_per_hour);
    }

    public function test_capacity_respects_tenant_isolation(): void
    {
        $mB = $this->createMachine($this->tenantB, 'Machine B', 'M-B', 100.0);
        $shiftB = $this->createShift($this->tenantB, 'Shift B', 'SH-B', '08:00', '16:00');
        $this->workCenterB->shifts()->attach($shiftB->id, ['tenant_id' => $this->tenantB->id]);

        $shiftA = $this->createShift($this->tenantA, 'Shift A', 'SH-A', '08:00', '16:00');
        $this->workCenterA->shifts()->attach($shiftA->id, ['tenant_id' => $this->tenantA->id]);

        $secA = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Sec A',
            code: 'SEC-A',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftA->id],
            shift_workers: [$shiftA->id => 1],
            machine_ids: []
        ), $this->tenantA->id);

        $this->assertEquals(0.0, (float)$secA->capacity_per_hour);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // LABOR COST TESTS
    // ─────────────────────────────────────────────────────────────────────────────

    public function test_single_shift_labor_cost_calculation(): void
    {
        $shift = $this->createShift($this->tenantA, 'Single Shift', 'SH-SGL', '08:00', '16:00', 120.0);
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $section = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Single Shift Sec',
            code: 'SEC-SGL',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 5], // 5 workers * 120 = 600/hr
            machine_ids: []
        ), $this->tenantA->id);

        // 8 hours operating, cost = 8 * 600 = 4,800. Avg = 600/hr.
        $this->assertEquals(600.0, (float)$section->labor_cost_per_hour);
        $this->assertEquals(4800.0, (float)$section->total_scheduled_labor_cost);
        $this->assertEquals(8.0, (float)$section->unique_operating_hours);
        $this->assertFalse($section->hasOverlappingShifts());
    }

    public function test_multiple_shifts_without_overlap_with_gap(): void
    {
        // Shift 1: 08:00 - 12:00 (4h), 2 workers @ 100 = 200/h -> 800
        // Shift 2: 14:00 - 18:00 (4h), 3 workers @ 150 = 450/h -> 1800
        // Gap: 12:00 - 14:00 (2h) excluded from denominator!
        $s1 = $this->createShift($this->tenantA, 'Morning', 'SH-1', '08:00', '12:00', 100.0);
        $s2 = $this->createShift($this->tenantA, 'Afternoon', 'SH-2', '14:00', '18:00', 150.0);
        $this->workCenterA->shifts()->attach([$s1->id, $s2->id], ['tenant_id' => $this->tenantA->id]);

        $section = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Gapped Shifts',
            code: 'SEC-GAP',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$s1->id, $s2->id],
            shift_workers: [$s1->id => 2, $s2->id => 3],
            machine_ids: []
        ), $this->tenantA->id);

        // Unique operating hours = 4 + 4 = 8 (the 2h gap is excluded)
        // Total cost = 800 + 1800 = 2600
        // Labor cost per hour = 2600 / 8 = 325.00
        $this->assertEquals(8.0, (float)$section->unique_operating_hours);
        $this->assertEquals(2600.0, (float)$section->total_scheduled_labor_cost);
        $this->assertEquals(325.0, (float)$section->labor_cost_per_hour);
        $this->assertFalse($section->hasOverlappingShifts());
    }

    public function test_two_overlapping_shifts(): void
    {
        // Shift 1: 08:00 - 14:00 (6h), 2 workers @ 100 = 200/h
        // Shift 2: 12:00 - 18:00 (6h), 3 workers @ 100 = 300/h
        // Windows:
        // 08:00 - 12:00: 4h, active: S1, rate: 200/h, cost: 800
        // 12:00 - 14:00: 2h, active: S1+S2, rate: 500/h, cost: 1000
        // 14:00 - 18:00: 4h, active: S2, rate: 300/h, cost: 1200
        // Total duration: 10h (08:00 to 18:00 unique)
        // Total cost: 800 + 1000 + 1200 = 3000
        // Labor cost per hour: 3000 / 10 = 300/h
        $s1 = $this->createShift($this->tenantA, 'S1', 'S-1', '08:00', '14:00', 100.0);
        $s2 = $this->createShift($this->tenantA, 'S2', 'S-2', '12:00', '18:00', 100.0);
        $this->workCenterA->shifts()->attach([$s1->id, $s2->id], ['tenant_id' => $this->tenantA->id]);

        $section = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Overlap 2',
            code: 'SEC-OV2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$s1->id, $s2->id],
            shift_workers: [$s1->id => 2, $s2->id => 3],
            machine_ids: []
        ), $this->tenantA->id);

        $this->assertEquals(10.0, (float)$section->unique_operating_hours);
        $this->assertEquals(3000.0, (float)$section->total_scheduled_labor_cost);
        $this->assertEquals(300.0, (float)$section->labor_cost_per_hour);
        $this->assertTrue($section->hasOverlappingShifts());
    }

    public function test_contained_shift_does_not_extend_unique_operating_hours(): void
    {
        // Shift 1: 08:00 - 18:00 (10h), 1 worker @ 100 = 100/h
        // Shift 2 (contained): 10:00 - 14:00 (4h), 2 workers @ 150 = 300/h
        // Windows:
        // 08:00 - 10:00 (2h): S1, rate 100/h -> 200
        // 10:00 - 14:00 (4h): S1+S2, rate 400/h -> 1600
        // 14:00 - 18:00 (4h): S1, rate 100/h -> 400
        // Unique operating hours: 10h
        // Total cost: 200 + 1600 + 400 = 2200
        // Labor cost per hour: 2200 / 10 = 220/h
        $s1 = $this->createShift($this->tenantA, 'Parent Shift', 'SH-P', '08:00', '18:00', 100.0);
        $s2 = $this->createShift($this->tenantA, 'Inside Shift', 'SH-IN', '10:00', '14:00', 150.0);
        $this->workCenterA->shifts()->attach([$s1->id, $s2->id], ['tenant_id' => $this->tenantA->id]);

        $section = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Contained Shift Sec',
            code: 'SEC-CONT',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$s1->id, $s2->id],
            shift_workers: [$s1->id => 1, $s2->id => 2],
            machine_ids: []
        ), $this->tenantA->id);

        $this->assertEquals(10.0, (float)$section->unique_operating_hours);
        $this->assertEquals(2200.0, (float)$section->total_scheduled_labor_cost);
        $this->assertEquals(220.0, (float)$section->labor_cost_per_hour);
    }

    public function test_overnight_shift_crossing_midnight(): void
    {
        // Shift: 22:00 - 06:00 (8h), 4 workers @ 200 = 800/h
        // Operating hours: 8h, cost: 6400, avg: 800/h
        $sNight = $this->createShift($this->tenantA, 'Night Shift', 'SH-N', '22:00', '06:00', 200.0);
        $this->workCenterA->shifts()->attach($sNight->id, ['tenant_id' => $this->tenantA->id]);

        $section = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Night Sec',
            code: 'SEC-NIGHT',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$sNight->id],
            shift_workers: [$sNight->id => 4],
            machine_ids: []
        ), $this->tenantA->id);

        $this->assertEquals(8.0, (float)$section->unique_operating_hours);
        $this->assertEquals(6400.0, (float)$section->total_scheduled_labor_cost);
        $this->assertEquals(800.0, (float)$section->labor_cost_per_hour);
    }

    public function test_paid_breaks_are_not_deducted(): void
    {
        // Shift: 08:00 - 16:00 (8h) with 60 min break. 2 workers @ 100 = 200/h.
        // Full 8 hours paid: cost = 8 * 200 = 1600.
        $shift = $this->createShift($this->tenantA, 'Break Shift', 'SH-BRK', '08:00', '16:00', 100.0, breakMinutes: 60);
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $section = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Paid Break Sec',
            code: 'SEC-PBRK',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 2],
            machine_ids: []
        ), $this->tenantA->id);

        $this->assertEquals(8.0, (float)$section->unique_operating_hours);
        $this->assertEquals(1600.0, (float)$section->total_scheduled_labor_cost);
        $this->assertEquals(200.0, (float)$section->labor_cost_per_hour);
    }

    public function test_missing_hourly_rate_treated_as_zero_retaining_shift_in_timeline(): void
    {
        // Shift 1: 08:00 - 12:00 (4h), rate = null (0). 5 workers -> cost 0
        // Shift 2: 12:00 - 16:00 (4h), rate = 100. 2 workers -> 200/h -> cost 800
        // Unique operating hours: 8h
        // Total cost: 800
        // Average labor cost: 800 / 8 = 100/h
        $s1 = $this->createShift($this->tenantA, 'No Rate Shift', 'SH-NORATE', '08:00', '12:00', null);
        $s2 = $this->createShift($this->tenantA, 'Rated Shift', 'SH-RATED', '12:00', '16:00', 100.0);
        $this->workCenterA->shifts()->attach([$s1->id, $s2->id], ['tenant_id' => $this->tenantA->id]);

        $section = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Zero Rate Sec',
            code: 'SEC-ZRATE',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$s1->id, $s2->id],
            shift_workers: [$s1->id => 5, $s2->id => 2],
            machine_ids: []
        ), $this->tenantA->id);

        $this->assertEquals(8.0, (float)$section->unique_operating_hours);
        $this->assertEquals(800.0, (float)$section->total_scheduled_labor_cost);
        $this->assertEquals(100.0, (float)$section->labor_cost_per_hour);

        // Verify active workers and shift in window breakdown
        $windows = $section->calculation_details['labor_cost']['timeline_windows'];
        $this->assertEquals(5, $windows[0]['active_workers']);
        $this->assertEquals(0.0, (float)$windows[0]['combined_hourly_cost']);
        $this->assertContains('No Rate Shift', $windows[0]['active_shift_names']);
    }

    /**
     * EXACT 4-SHIFT REGRESSION TEST SPECIFIED IN SPECIFICATION SECTION 6
     * Shift 1: 08:00–14:00, 10 workers, ₹100/worker -> ₹1,000/hr
     * Shift 2: 12:00–18:00, 20 workers, ₹150/worker -> ₹3,000/hr
     * Shift 3: 18:00–06:00 next day, 12 workers, ₹200/worker -> ₹2,400/hr
     * Shift 4: 16:00–20:00, 4 workers, ₹200/worker -> ₹800/hr
     *
     * Expected results:
     * - Unique elapsed operating hours: 22 hours
     * - Total scheduled labor cost: ₹56,000
     * - Section Labor Cost Per Hour: ₹2,545.4545... -> ₹2,545.45/hr
     */
    public function test_exact_four_shift_specification_example(): void
    {
        $shift1 = $this->createShift($this->tenantA, 'Shift 1', 'SH-1', '08:00', '14:00', 100.0);
        $shift2 = $this->createShift($this->tenantA, 'Shift 2', 'SH-2', '12:00', '18:00', 150.0);
        $shift3 = $this->createShift($this->tenantA, 'Shift 3', 'SH-3', '18:00', '06:00', 200.0);
        $shift4 = $this->createShift($this->tenantA, 'Shift 4', 'SH-4', '16:00', '20:00', 200.0);

        $this->workCenterA->shifts()->attach([$shift1->id, $shift2->id, $shift3->id, $shift4->id], [
            'tenant_id' => $this->tenantA->id,
        ]);

        $section = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Complex Assembly Section',
            code: 'SEC-COMPLEX',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift1->id, $shift2->id, $shift3->id, $shift4->id],
            shift_workers: [
                $shift1->id => 10,
                $shift2->id => 20,
                $shift3->id => 12,
                $shift4->id => 4,
            ],
            machine_ids: []
        ), $this->tenantA->id);

        // Core aggregate assertions
        $this->assertEquals(22.0, (float)$section->unique_operating_hours);
        $this->assertEquals(56000.0, (float)$section->total_scheduled_labor_cost);
        $this->assertEquals(2545.4545, round((float)$section->labor_cost_per_hour, 4));
        $this->assertTrue($section->hasOverlappingShifts());

        // Detailed timeline window validation
        $windows = $section->calculation_details['labor_cost']['timeline_windows'];
        $this->assertCount(6, $windows);

        // Window 1: 08:00–12:00 (4h), Shift 1, ₹1,000/hr, cost ₹4,000
        $this->assertEquals('08:00–12:00', $windows[0]['window']);
        $this->assertEquals(4.0, (float)$windows[0]['duration_hours']);
        $this->assertEquals(['Shift 1'], $windows[0]['active_shift_names']);
        $this->assertEquals(10, $windows[0]['active_workers']);
        $this->assertEquals(1000.0, (float)$windows[0]['combined_hourly_cost']);
        $this->assertEquals(4000.0, (float)$windows[0]['window_cost']);
        $this->assertFalse($windows[0]['is_overlap']);

        // Window 2: 12:00–14:00 (2h), Shifts 1 + 2, ₹4,000/hr, cost ₹8,000
        $this->assertEquals('12:00–14:00', $windows[1]['window']);
        $this->assertEquals(2.0, (float)$windows[1]['duration_hours']);
        $this->assertEquals(['Shift 1', 'Shift 2'], $windows[1]['active_shift_names']);
        $this->assertEquals(30, $windows[1]['active_workers']);
        $this->assertEquals(4000.0, (float)$windows[1]['combined_hourly_cost']);
        $this->assertEquals(8000.0, (float)$windows[1]['window_cost']);
        $this->assertTrue($windows[1]['is_overlap']);

        // Window 3: 14:00–16:00 (2h), Shift 2, ₹3,000/hr, cost ₹6,000
        $this->assertEquals('14:00–16:00', $windows[2]['window']);
        $this->assertEquals(2.0, (float)$windows[2]['duration_hours']);
        $this->assertEquals(['Shift 2'], $windows[2]['active_shift_names']);
        $this->assertEquals(20, $windows[2]['active_workers']);
        $this->assertEquals(3000.0, (float)$windows[2]['combined_hourly_cost']);
        $this->assertEquals(6000.0, (float)$windows[2]['window_cost']);
        $this->assertFalse($windows[2]['is_overlap']);

        // Window 4: 16:00–18:00 (2h), Shifts 2 + 4, ₹3,800/hr, cost ₹7,600
        $this->assertEquals('16:00–18:00', $windows[3]['window']);
        $this->assertEquals(2.0, (float)$windows[3]['duration_hours']);
        $this->assertEquals(['Shift 2', 'Shift 4'], $windows[3]['active_shift_names']);
        $this->assertEquals(24, $windows[3]['active_workers']);
        $this->assertEquals(3800.0, (float)$windows[3]['combined_hourly_cost']);
        $this->assertEquals(7600.0, (float)$windows[3]['window_cost']);
        $this->assertTrue($windows[3]['is_overlap']);

        // Window 5: 18:00–20:00 (2h), Shifts 3 + 4, ₹3,200/hr, cost ₹6,400
        $this->assertEquals('18:00–20:00', $windows[4]['window']);
        $this->assertEquals(2.0, (float)$windows[4]['duration_hours']);
        $this->assertEquals(['Shift 3', 'Shift 4'], $windows[4]['active_shift_names']);
        $this->assertEquals(16, $windows[4]['active_workers']);
        $this->assertEquals(3200.0, (float)$windows[4]['combined_hourly_cost']);
        $this->assertEquals(6400.0, (float)$windows[4]['window_cost']);
        $this->assertTrue($windows[4]['is_overlap']);

        // Window 6: 20:00–06:00 next day (10h), Shift 3, ₹2,400/hr, cost ₹24,000
        $this->assertEquals('20:00–06:00 next day', $windows[5]['window']);
        $this->assertEquals(10.0, (float)$windows[5]['duration_hours']);
        $this->assertEquals(['Shift 3'], $windows[5]['active_shift_names']);
        $this->assertEquals(12, $windows[5]['active_workers']);
        $this->assertEquals(2400.0, (float)$windows[5]['combined_hourly_cost']);
        $this->assertEquals(24000.0, (float)$windows[5]['window_cost']);
        $this->assertFalse($windows[5]['is_overlap']);
    }

    public function test_ui_renders_calculated_values_and_breakdown_tables(): void
    {
        $shift1 = $this->createShift($this->tenantA, 'Shift 1', 'SH-1', '08:00', '14:00', 100.0);
        $shift2 = $this->createShift($this->tenantA, 'Shift 2', 'SH-2', '12:00', '18:00', 150.0);
        $machine = $this->createMachine($this->tenantA, 'Milling Machine', 'M-MILL', 60.0);

        $this->workCenterA->shifts()->attach([$shift1->id, $shift2->id], ['tenant_id' => $this->tenantA->id]);

        $section = $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Render Test Section',
            code: 'SEC-RND',
            supports_manual_production: true,
            manual_capacity_per_hour: 20.0,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift1->id, $shift2->id],
            shift_workers: [$shift1->id => 2, $shift2->id => 3],
            machine_ids: [$machine->id]
        ), $this->tenantA->id);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('production.work-centers.show', $this->workCenterA->id));

        $response->assertStatus(200);

        // Check Capacity Per Hour: 60 + 20 = 80
        $response->assertSee('80.00');

        // Check Breakdown Modal Presence
        $response->assertSee("sectionCalculationModal_{$section->id}");
        $response->assertSee('Operating Hours Assumption');
        $response->assertSee('Capacity Breakdown');
        $response->assertSee('Shift Labor Configuration');
        $response->assertSee('Timeline Windows');
        $response->assertSee('Overlapping Shifts');
        $response->assertSee('Milling Machine');
    }
}
