<?php

namespace Tests\Feature\Production;

use App\Domains\Production\DTO\WorkCenterSectionDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\SectionCalculationService;
use App\Domains\Production\Services\WorkCenterCalculationService;
use App\Domains\Production\Services\WorkCenterSectionService;
use App\Domains\Production\Services\WorkCenterService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkCenterCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private WorkCenter $workCenterA;
    private WorkCenter $workCenterB;
    private WorkCenterCalculationService $wcCalcService;
    private SectionCalculationService $secCalcService;
    private WorkCenterSectionService $sectionService;
    private WorkCenterService $workCenterService;

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
            'name'      => 'Main Work Center A',
            'code'      => 'WC-MAIN-A',
            'status'    => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->workCenterB = WorkCenter::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Main Work Center B',
            'code'      => 'WC-MAIN-B',
            'status'    => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->wcCalcService = app(WorkCenterCalculationService::class);
        $this->secCalcService = app(SectionCalculationService::class);
        $this->sectionService = app(WorkCenterSectionService::class);
        $this->workCenterService = app(WorkCenterService::class);
    }

    private function createShift(Tenant $tenant, string $name, string $code, string $start, string $end, ?float $rate = 100.0, int $breakMinutes = 30): ProductionShift
    {
        return ProductionShift::create([
            'tenant_id'         => $tenant->id,
            'name'              => $name,
            'code'              => $code,
            'start_time'        => $start,
            'end_time'          => $end,
            'break_minutes'     => $breakMinutes,
            'hourly_labor_rate' => $rate,
            'active'            => true,
        ]);
    }

    private function createMachine(Tenant $tenant, string $name, string $code, float $capacity = 50.0): Machine
    {
        return Machine::create([
            'tenant_id'     => $tenant->id,
            'name'          => $name,
            'code'          => $code,
            'status'        => Machine::STATUS_ACTIVE,
            'capacity'      => $capacity,
            'capacity_unit' => 'units/hr',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // CAPACITY TESTS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Test the exact 3-Section illustrative regression case:
     * - Section A: 80 units/hour, operating 08:00–16:00
     * - Section B: 50 units/hour, operating 12:00–20:00
     * - Section C: 30 units/hour, operating 20:00–04:00 next day
     *
     * Expected Windows:
     * 08:00–12:00: A (80), 4h -> 320
     * 12:00–16:00: A, B (130), 4h -> 520
     * 16:00–20:00: B (50), 4h -> 200
     * 20:00–04:00 next day: C (30), 8h -> 240
     * Gap: 04:00–08:00 (4h) excluded.
     *
     * Total: 20 unique operating hours, 1,280 theoretical capacity, average 64.0 units/hr.
     */
    public function test_capacity_illustrative_regression_case(): void
    {
        $shiftA = $this->createShift($this->tenantA, 'Shift 08-16', 'SH-0816', '08:00', '16:00');
        $shiftB = $this->createShift($this->tenantA, 'Shift 12-20', 'SH-1220', '12:00', '20:00');
        $shiftC = $this->createShift($this->tenantA, 'Shift 20-04', 'SH-2004', '20:00', '04:00');

        $this->workCenterA->shifts()->attach([$shiftA->id, $shiftB->id, $shiftC->id], ['tenant_id' => $this->tenantA->id]);

        $m1 = $this->createMachine($this->tenantA, 'Machine A', 'M-A', 80.0);
        $m2 = $this->createMachine($this->tenantA, 'Machine B', 'M-B', 50.0);
        $m3 = $this->createMachine($this->tenantA, 'Machine C', 'M-C', 30.0);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section A',
            code: 'SEC-A',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftA->id],
            shift_workers: [$shiftA->id => 2],
            machine_ids: [$m1->id]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section B',
            code: 'SEC-B',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftB->id],
            shift_workers: [$shiftB->id => 2],
            machine_ids: [$m2->id]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section C',
            code: 'SEC-C',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftC->id],
            shift_workers: [$shiftC->id => 2],
            machine_ids: [$m3->id]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.machines', 'sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateCapacity($wc);

        $this->assertEquals(20.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(1280.0, (float) $calc['total_theoretical_capacity']);
        $this->assertEquals(64.0, (float) $calc['capacity_per_hour']);

        // Check Windows
        $windows = $calc['windows'];
        $this->assertCount(4, $windows);

        // Window 1: 08:00–12:00 (4h)
        $this->assertEquals('08:00–12:00', $windows[0]['window_label']);
        $this->assertEquals(4.0, (float) $windows[0]['duration_hours']);
        $this->assertEquals(['Section A'], $windows[0]['active_section_names']);
        $this->assertEquals(80.0, (float) $windows[0]['combined_capacity_per_hour']);
        $this->assertEquals(320.0, (float) $windows[0]['theoretical_capacity']);
        $this->assertFalse($windows[0]['is_overlapping']);

        // Window 2: 12:00–16:00 (4h)
        $this->assertEquals('12:00–16:00', $windows[1]['window_label']);
        $this->assertEquals(4.0, (float) $windows[1]['duration_hours']);
        $this->assertCount(2, $windows[1]['active_sections']);
        $this->assertEquals(130.0, (float) $windows[1]['combined_capacity_per_hour']);
        $this->assertEquals(520.0, (float) $windows[1]['theoretical_capacity']);
        $this->assertTrue($windows[1]['is_overlapping']);

        // Window 3: 16:00–20:00 (4h)
        $this->assertEquals('16:00–20:00', $windows[2]['window_label']);
        $this->assertEquals(4.0, (float) $windows[2]['duration_hours']);
        $this->assertEquals(['Section B'], $windows[2]['active_section_names']);
        $this->assertEquals(50.0, (float) $windows[2]['combined_capacity_per_hour']);
        $this->assertEquals(200.0, (float) $windows[2]['theoretical_capacity']);
        $this->assertFalse($windows[2]['is_overlapping']);

        // Window 4: 20:00–04:00 next day (8h)
        $this->assertEquals('20:00–04:00 next day', $windows[3]['window_label']);
        $this->assertEquals(8.0, (float) $windows[3]['duration_hours']);
        $this->assertEquals(['Section C'], $windows[3]['active_section_names']);
        $this->assertEquals(30.0, (float) $windows[3]['combined_capacity_per_hour']);
        $this->assertEquals(240.0, (float) $windows[3]['theoretical_capacity']);
        $this->assertTrue($windows[3]['is_overnight']);
        $this->assertFalse($windows[3]['is_overlapping']);

        // Check gap
        $this->assertCount(1, $calc['gaps']);
        $this->assertEquals('04:00–08:00', $calc['gaps'][0]['window_label']);
        $this->assertEquals(4.0, (float) $calc['gaps'][0]['duration_hours']);

        // Verify persisted WorkCenter values match
        $saved = $this->wcCalcService->calculateAndSave($wc);
        $this->assertEquals(64.0, (float) $saved->capacity_per_hour);
        $this->assertEquals(1280.0, (float) $saved->total_theoretical_capacity);
        $this->assertEquals(20.0, (float) $saved->unique_operating_hours);
    }

    public function test_capacity_overlapping_sections_sum_active_capacities(): void
    {
        $shift1 = $this->createShift($this->tenantA, 'Shift 1', 'SH-1', '08:00', '14:00');
        $shift2 = $this->createShift($this->tenantA, 'Shift 2', 'SH-2', '10:00', '16:00');

        $this->workCenterA->shifts()->attach([$shift1->id, $shift2->id], ['tenant_id' => $this->tenantA->id]);

        $m1 = $this->createMachine($this->tenantA, 'Machine 1', 'M-1', 60.0);
        $m2 = $this->createMachine($this->tenantA, 'Machine 2', 'M-2', 40.0);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift1->id],
            shift_workers: [$shift1->id => 1],
            machine_ids: [$m1->id]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 2',
            code: 'SEC-2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift2->id],
            shift_workers: [$shift2->id => 1],
            machine_ids: [$m2->id]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.machines', 'sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateCapacity($wc);

        // Timeline: 08:00–16:00 = 8h total unique hours
        // 08:00–10:00: Sec 1 (60) * 2h = 120
        // 10:00–14:00: Sec 1 + Sec 2 (100) * 4h = 400
        // 14:00–16:00: Sec 2 (40) * 2h = 80
        // Total capacity = 600. Avg = 600 / 8 = 75.0 units/hr
        $this->assertEquals(8.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(600.0, (float) $calc['total_theoretical_capacity']);
        $this->assertEquals(75.0, (float) $calc['capacity_per_hour']);
        $this->assertCount(3, $calc['windows']);
    }

    public function test_capacity_excludes_gaps_between_shifts(): void
    {
        $shift1 = $this->createShift($this->tenantA, 'Morning', 'SH-MORN', '08:00', '12:00');
        $shift2 = $this->createShift($this->tenantA, 'Evening', 'SH-EVE', '16:00', '20:00');

        $this->workCenterA->shifts()->attach([$shift1->id, $shift2->id], ['tenant_id' => $this->tenantA->id]);

        $m1 = $this->createMachine($this->tenantA, 'Machine 1', 'M-1', 50.0);
        $m2 = $this->createMachine($this->tenantA, 'Machine 2', 'M-2', 50.0);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift1->id],
            shift_workers: [$shift1->id => 1],
            machine_ids: [$m1->id]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 2',
            code: 'SEC-2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift2->id],
            shift_workers: [$shift2->id => 1],
            machine_ids: [$m2->id]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.machines', 'sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateCapacity($wc);

        // Operating hours: 4h (08-12) + 4h (16-20) = 8h. Gaps: 12-16 (4h) and 20-08 (12h) excluded.
        // Total capacity: 50*4 + 50*4 = 400. Capacity/hr: 400/8 = 50.0
        $this->assertEquals(8.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(400.0, (float) $calc['total_theoretical_capacity']);
        $this->assertEquals(50.0, (float) $calc['capacity_per_hour']);
        $this->assertCount(2, $calc['windows']);
        $this->assertCount(2, $calc['gaps']);
    }

    public function test_capacity_multiple_overlaps_and_contained_shift(): void
    {
        // Section 1: 06:00–18:00 (12h), capacity 40
        // Section 2: 09:00–12:00 (3h), capacity 30 (fully contained)
        // Section 3: 10:00–15:00 (5h), capacity 20
        $s1 = $this->createShift($this->tenantA, 'All Day', 'SH-AD', '06:00', '18:00');
        $s2 = $this->createShift($this->tenantA, 'Midday', 'SH-MD', '09:00', '12:00');
        $s3 = $this->createShift($this->tenantA, 'Afternoon', 'SH-AF', '10:00', '15:00');

        $this->workCenterA->shifts()->attach([$s1->id, $s2->id, $s3->id], ['tenant_id' => $this->tenantA->id]);

        $m1 = $this->createMachine($this->tenantA, 'M1', 'M1', 40.0);
        $m2 = $this->createMachine($this->tenantA, 'M2', 'M2', 30.0);
        $m3 = $this->createMachine($this->tenantA, 'M3', 'M3', 20.0);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Sec 1',
            code: 'S1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$s1->id],
            shift_workers: [$s1->id => 1],
            machine_ids: [$m1->id]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Sec 2',
            code: 'S2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$s2->id],
            shift_workers: [$s2->id => 1],
            machine_ids: [$m2->id]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Sec 3',
            code: 'S3',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$s3->id],
            shift_workers: [$s3->id => 1],
            machine_ids: [$m3->id]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.machines', 'sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateCapacity($wc);

        // Windows:
        // 06:00–09:00 (3h): Sec 1 (40) -> 120
        // 09:00–10:00 (1h): Sec 1 + Sec 2 (70) -> 70
        // 10:00–12:00 (2h): Sec 1 + Sec 2 + Sec 3 (90) -> 180 (3 simultaneous)
        // 12:00–15:00 (3h): Sec 1 + Sec 3 (60) -> 180
        // 15:00–18:00 (3h): Sec 1 (40) -> 120
        // Total duration = 12h (06:00 to 18:00)
        // Total theoretical capacity = 120 + 70 + 180 + 180 + 120 = 670
        // Average = 670 / 12 = 55.8333
        $this->assertEquals(12.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(670.0, (float) $calc['total_theoretical_capacity']);
        $this->assertEquals(round(670 / 12, 4), (float) $calc['capacity_per_hour']);
    }

    public function test_capacity_zero_operating_intervals_handled_safely(): void
    {
        // WorkCenter with no sections
        $calc = $this->wcCalcService->calculateCapacity($this->workCenterA);

        $this->assertEquals(0.0, (float) $calc['capacity_per_hour']);
        $this->assertEquals(0.0, (float) $calc['total_theoretical_capacity']);
        $this->assertEquals(0.0, (float) $calc['unique_operating_hours']);
        $this->assertEmpty($calc['windows']);
    }

    public function test_capacity_tenant_isolation(): void
    {
        $shiftA = $this->createShift($this->tenantA, 'Shift A', 'SH-A', '08:00', '16:00');
        $shiftB = $this->createShift($this->tenantB, 'Shift B', 'SH-B', '08:00', '16:00');

        $this->workCenterA->shifts()->attach($shiftA->id, ['tenant_id' => $this->tenantA->id]);
        $this->workCenterB->shifts()->attach($shiftB->id, ['tenant_id' => $this->tenantB->id]);

        $mA = $this->createMachine($this->tenantA, 'Machine A', 'MA', 100.0);
        $mB = $this->createMachine($this->tenantB, 'Machine B', 'MB', 500.0);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section A',
            code: 'SA',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftA->id],
            shift_workers: [$shiftA->id => 1],
            machine_ids: [$mA->id]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterB->id,
            name: 'Section B',
            code: 'SB',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftB->id],
            shift_workers: [$shiftB->id => 1],
            machine_ids: [$mB->id]
        ), $this->tenantB->id);

        $calcA = $this->wcCalcService->calculateCapacity($this->workCenterA->fresh(['sections.machines', 'sections.shifts', 'shifts']));
        $this->assertEquals(100.0, (float) $calcA['capacity_per_hour']);
        $this->assertEquals(800.0, (float) $calcA['total_theoretical_capacity']);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // LABOR TESTS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Test the exact 3-assignment illustrative regression case:
     * - Section 1, Shift A: 08:00–12:00, 5 workers × ₹100/hr = ₹500/hr
     * - Section 1, Shift B: 08:00–12:00, 8 workers × ₹125/hr = ₹1,000/hr
     * - Section 2, Shift C: 10:00–14:00, 4 workers × ₹200/hr = ₹800/hr
     *
     * Expected Windows:
     * 08:00–10:00: Active A & B (13 workers), Combined cost ₹1,500/hr, 2h -> ₹3,000
     * 10:00–12:00: Active A, B & C (17 workers), Combined cost ₹2,300/hr, 2h -> ₹4,600
     * 12:00–14:00: Active C (4 workers), Combined cost ₹800/hr, 2h -> ₹1,600
     *
     * Total: 6 unique operating hours, ₹9,200 scheduled labor cost, ₹1,533.3333/hr average.
     */
    public function test_labor_illustrative_regression_case(): void
    {
        $shiftA = $this->createShift($this->tenantA, 'Shift A', 'SH-A', '08:00', '12:00', 100.0);
        $shiftB = $this->createShift($this->tenantA, 'Shift B', 'SH-B', '08:00', '12:00', 125.0);
        $shiftC = $this->createShift($this->tenantA, 'Shift C', 'SH-C', '10:00', '14:00', 200.0);

        $this->workCenterA->shifts()->attach([$shiftA->id, $shiftB->id, $shiftC->id], ['tenant_id' => $this->tenantA->id]);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftA->id, $shiftB->id],
            shift_workers: [$shiftA->id => 5, $shiftB->id => 8]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 2',
            code: 'SEC-2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftC->id],
            shift_workers: [$shiftC->id => 4]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateLaborCost($wc);

        $this->assertEquals(6.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(9200.0, (float) $calc['total_scheduled_labor_cost']);
        $this->assertEquals(1533.3333, (float) $calc['labor_cost_per_hour']);
        $this->assertTrue($calc['has_overlapping_assignments']);

        // Check Windows
        $windows = $calc['windows'];
        $this->assertCount(3, $windows);

        // Window 1: 08:00–10:00 (2h)
        $this->assertEquals('08:00–10:00', $windows[0]['window_label']);
        $this->assertEquals(2.0, (float) $windows[0]['duration_hours']);
        $this->assertEquals(13, $windows[0]['combined_workers']);
        $this->assertEquals(1500.0, (float) $windows[0]['combined_hourly_cost']);
        $this->assertEquals(3000.0, (float) $windows[0]['window_cost']);
        $this->assertTrue($windows[0]['is_overlapping']);

        // Window 2: 10:00–12:00 (2h)
        $this->assertEquals('10:00–12:00', $windows[1]['window_label']);
        $this->assertEquals(2.0, (float) $windows[1]['duration_hours']);
        $this->assertEquals(17, $windows[1]['combined_workers']);
        $this->assertEquals(2300.0, (float) $windows[1]['combined_hourly_cost']);
        $this->assertEquals(4600.0, (float) $windows[1]['window_cost']);
        $this->assertTrue($windows[1]['is_overlapping']);

        // Window 3: 12:00–14:00 (2h)
        $this->assertEquals('12:00–14:00', $windows[2]['window_label']);
        $this->assertEquals(2.0, (float) $windows[2]['duration_hours']);
        $this->assertEquals(4, $windows[2]['combined_workers']);
        $this->assertEquals(800.0, (float) $windows[2]['combined_hourly_cost']);
        $this->assertEquals(1600.0, (float) $windows[2]['window_cost']);
        $this->assertFalse($windows[2]['is_overlapping']);

        // Verify persisted WorkCenter values match
        $saved = $this->wcCalcService->calculateAndSave($wc);
        $this->assertEquals(1533.3333, (float) $saved->labor_cost_per_hour);
        $this->assertEquals(9200.0, (float) $saved->total_scheduled_labor_cost);
    }

    public function test_labor_single_shift_assignment_calculates_correctly(): void
    {
        $shift = $this->createShift($this->tenantA, 'Shift 1', 'SH-1', '08:00', '16:00', 120.0);
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 3]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateLaborCost($wc);

        // 3 workers @ ₹120 = ₹360/hr. 8h duration = ₹2,880 total cost. Avg: ₹360/hr.
        $this->assertEquals(8.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(2880.0, (float) $calc['total_scheduled_labor_cost']);
        $this->assertEquals(360.0, (float) $calc['labor_cost_per_hour']);
        $this->assertFalse($calc['has_overlapping_assignments']);
    }

    public function test_labor_same_shift_assigned_to_two_sections_uses_distinct_worker_counts(): void
    {
        $shift = $this->createShift($this->tenantA, 'Shared Shift', 'SH-SHR', '08:00', '16:00', 150.0);
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        // Section 1 uses 2 workers => ₹300/hr
        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 2]
        ), $this->tenantA->id);

        // Section 2 uses 5 workers => ₹750/hr
        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 2',
            code: 'SEC-2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 5]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateLaborCost($wc);

        // Combined: 7 workers @ ₹150/hr = ₹1,050/hr. 8h = ₹8,400 total.
        $this->assertEquals(8.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(8400.0, (float) $calc['total_scheduled_labor_cost']);
        $this->assertEquals(1050.0, (float) $calc['labor_cost_per_hour']);
        $this->assertCount(1, $calc['windows']);
        $this->assertEquals(7, $calc['windows'][0]['combined_workers']);
    }

    public function test_labor_three_or_more_simultaneous_assignments(): void
    {
        $shift1 = $this->createShift($this->tenantA, 'Shift 1', 'SH-1', '08:00', '16:00', 100.0);
        $shift2 = $this->createShift($this->tenantA, 'Shift 2', 'SH-2', '08:00', '16:00', 150.0);
        $shift3 = $this->createShift($this->tenantA, 'Shift 3', 'SH-3', '08:00', '16:00', 200.0);

        $this->workCenterA->shifts()->attach([$shift1->id, $shift2->id, $shift3->id], ['tenant_id' => $this->tenantA->id]);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift1->id, $shift2->id],
            shift_workers: [$shift1->id => 2, $shift2->id => 3] // 2*100=200, 3*150=450
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 2',
            code: 'SEC-2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift3->id],
            shift_workers: [$shift3->id => 4] // 4*200=800
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateLaborCost($wc);

        // Combined: 200 + 450 + 800 = 1,450/hr. 8h = 11,600.
        $this->assertEquals(8.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(11600.0, (float) $calc['total_scheduled_labor_cost']);
        $this->assertEquals(1450.0, (float) $calc['labor_cost_per_hour']);
        $this->assertEquals(9, $calc['windows'][0]['combined_workers']);
        $this->assertCount(3, $calc['windows'][0]['active_assignments']);
    }

    public function test_labor_fully_contained_assignment_contributes_only_during_its_interval(): void
    {
        $shiftLong = $this->createShift($this->tenantA, 'Long Shift', 'SH-LNG', '06:00', '18:00', 100.0);
        $shiftShort = $this->createShift($this->tenantA, 'Short Shift', 'SH-SHT', '10:00', '14:00', 200.0);

        $this->workCenterA->shifts()->attach([$shiftLong->id, $shiftShort->id], ['tenant_id' => $this->tenantA->id]);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftLong->id],
            shift_workers: [$shiftLong->id => 2] // 2*100=200/hr
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 2',
            code: 'SEC-2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftShort->id],
            shift_workers: [$shiftShort->id => 3] // 3*200=600/hr
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateLaborCost($wc);

        // Windows:
        // 06:00–10:00 (4h): 200/hr -> 800
        // 10:00–14:00 (4h): 200 + 600 = 800/hr -> 3200
        // 14:00–18:00 (4h): 200/hr -> 800
        // Total cost = 4800. Unique hours = 12. Rate = 4800 / 12 = 400.0
        $this->assertEquals(12.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(4800.0, (float) $calc['total_scheduled_labor_cost']);
        $this->assertEquals(400.0, (float) $calc['labor_cost_per_hour']);
        $this->assertCount(3, $calc['windows']);
    }

    public function test_labor_overnight_intervals_and_gaps_work_correctly(): void
    {
        $shiftOvernight = $this->createShift($this->tenantA, 'Night Shift', 'SH-NGT', '22:00', '06:00', 150.0);
        $shiftMorning   = $this->createShift($this->tenantA, 'Morning Shift', 'SH-MRN', '08:00', '16:00', 100.0);

        $this->workCenterA->shifts()->attach([$shiftOvernight->id, $shiftMorning->id], ['tenant_id' => $this->tenantA->id]);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftOvernight->id],
            shift_workers: [$shiftOvernight->id => 2] // 2*150=300/hr, 8h = 2400
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 2',
            code: 'SEC-2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftMorning->id],
            shift_workers: [$shiftMorning->id => 4] // 4*100=400/hr, 8h = 3200
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateLaborCost($wc);

        // Hours: 8h (morning) + 8h (night) = 16h. Gaps: 06-08 (2h) and 16-22 (6h) excluded.
        // Cost: 3200 + 2400 = 5600. Avg: 5600 / 16 = 350.0
        $this->assertEquals(16.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(5600.0, (float) $calc['total_scheduled_labor_cost']);
        $this->assertEquals(350.0, (float) $calc['labor_cost_per_hour']);
        $this->assertCount(2, $calc['gaps']);
    }

    public function test_labor_paid_breaks_do_not_reduce_scheduled_labor_cost_or_duration(): void
    {
        // 60 minutes paid break
        $shift = $this->createShift($this->tenantA, 'Shift with Break', 'SH-BRK', '08:00', '16:00', 100.0, 60);
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 2]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateLaborCost($wc);

        // Break minutes must NOT be subtracted from duration: full 8 hours counted
        $this->assertEquals(8.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(1600.0, (float) $calc['total_scheduled_labor_cost']);
        $this->assertEquals(200.0, (float) $calc['labor_cost_per_hour']);
    }

    public function test_labor_missing_rate_contributes_zero_cost_while_preserving_hours_and_workers(): void
    {
        // Shift with NULL rate
        $shiftMissing = $this->createShift($this->tenantA, 'Volunteer Shift', 'SH-VOL', '08:00', '16:00', null);
        $shiftPaid    = $this->createShift($this->tenantA, 'Paid Shift', 'SH-PD', '12:00', '20:00', 100.0);

        $this->workCenterA->shifts()->attach([$shiftMissing->id, $shiftPaid->id], ['tenant_id' => $this->tenantA->id]);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftMissing->id],
            shift_workers: [$shiftMissing->id => 5]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 2',
            code: 'SEC-2',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftPaid->id],
            shift_workers: [$shiftPaid->id => 2] // 2*100=200/hr, 8h = 1600
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateLaborCost($wc);

        // Timeline: 08:00 to 20:00 = 12 unique hours.
        // Missing rate shift contributes 0 cost, but 8h to timeline and 5 workers to combined workers
        // In window 12:00–16:00, combined workers = 5 + 2 = 7 workers.
        $this->assertEquals(12.0, (float) $calc['unique_operating_hours']);
        $this->assertEquals(1600.0, (float) $calc['total_scheduled_labor_cost']);
        $this->assertEquals(round(1600 / 12, 4), (float) $calc['labor_cost_per_hour']);
        $this->assertTrue($calc['has_missing_rates']);

        // Check window 12:00–16:00
        $win2 = $calc['windows'][1];
        $this->assertEquals('12:00–16:00', $win2['window_label']);
        $this->assertEquals(7, $win2['combined_workers']);
        $this->assertTrue($win2['has_missing_rates']);
    }

    public function test_labor_explicit_zero_rate_distinguishable_from_missing_rate(): void
    {
        $shiftExplicitZero = $this->createShift($this->tenantA, 'Zero Rate Shift', 'SH-ZERO', '08:00', '16:00', 0.0);
        $this->workCenterA->shifts()->attach($shiftExplicitZero->id, ['tenant_id' => $this->tenantA->id]);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section 1',
            code: 'SEC-1',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftExplicitZero->id],
            shift_workers: [$shiftExplicitZero->id => 4]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh(['sections.shifts', 'shifts']);
        $calc = $this->wcCalcService->calculateLaborCost($wc);

        $this->assertFalse($calc['has_missing_rates']);
        $this->assertFalse($calc['assignments'][0]['is_rate_missing']);
        $this->assertSame(0.0, (float)$calc['assignments'][0]['hourly_labor_rate']);
    }

    public function test_labor_zero_operating_hours_safe_no_division_by_zero(): void
    {
        $calc = $this->wcCalcService->calculateLaborCost($this->workCenterA);

        $this->assertEquals(0.0, (float) $calc['labor_cost_per_hour']);
        $this->assertEquals(0.0, (float) $calc['total_scheduled_labor_cost']);
        $this->assertEquals(0.0, (float) $calc['unique_operating_hours']);
        $this->assertEmpty($calc['windows']);
    }

    public function test_labor_tenant_isolation_enforced(): void
    {
        $shiftA = $this->createShift($this->tenantA, 'Shift A', 'SH-A', '08:00', '16:00', 100.0);
        $shiftB = $this->createShift($this->tenantB, 'Shift B', 'SH-B', '08:00', '16:00', 500.0);

        $this->workCenterA->shifts()->attach($shiftA->id, ['tenant_id' => $this->tenantA->id]);
        $this->workCenterB->shifts()->attach($shiftB->id, ['tenant_id' => $this->tenantB->id]);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section A',
            code: 'SA',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftA->id],
            shift_workers: [$shiftA->id => 2]
        ), $this->tenantA->id);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterB->id,
            name: 'Section B',
            code: 'SB',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shiftB->id],
            shift_workers: [$shiftB->id => 2]
        ), $this->tenantB->id);

        $calcA = $this->wcCalcService->calculateLaborCost($this->workCenterA->fresh(['sections.shifts', 'shifts']));
        $this->assertEquals(200.0, (float) $calcA['labor_cost_per_hour']);
        $this->assertEquals(1600.0, (float) $calcA['total_scheduled_labor_cost']);
    }

    public function test_summary_and_detailed_breakdown_values_agree(): void
    {
        $shift = $this->createShift($this->tenantA, 'Shift', 'SH', '08:00', '16:00', 120.0);
        $this->workCenterA->shifts()->attach($shift->id, ['tenant_id' => $this->tenantA->id]);

        $m = $this->createMachine($this->tenantA, 'Machine', 'M', 75.0);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'Section',
            code: 'SEC',
            supports_manual_production: true,
            manual_capacity_per_hour: 25.0,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift->id],
            shift_workers: [$shift->id => 3],
            machine_ids: [$m->id]
        ), $this->tenantA->id);

        $wc = $this->workCenterA->fresh();

        $this->assertEquals((float) $wc->capacity_per_hour, (float) $wc->calculation_details['capacity']['capacity_per_hour']);
        $this->assertEquals((float) $wc->total_theoretical_capacity, (float) $wc->calculation_details['capacity']['total_theoretical_capacity']);
        $this->assertEquals((float) $wc->labor_cost_per_hour, (float) $wc->calculation_details['labor_cost']['labor_cost_per_hour']);
        $this->assertEquals((float) $wc->total_scheduled_labor_cost, (float) $wc->calculation_details['labor_cost']['total_scheduled_labor_cost']);
        $this->assertEquals((float) $wc->unique_operating_hours, (float) $wc->calculation_details['capacity']['unique_operating_hours']);
    }

    public function test_ui_displays_work_center_summary_and_breakdown_correctly(): void
    {
        $shift1 = $this->createShift($this->tenantA, 'Morning Shift', 'SH-MORN', '08:00', '12:00', 100.0);
        $shift2 = $this->createShift($this->tenantA, 'Afternoon Shift', 'SH-AFT', '12:00', '16:00', 150.0);

        $this->workCenterA->shifts()->attach([$shift1->id, $shift2->id], ['tenant_id' => $this->tenantA->id]);

        $m1 = $this->createMachine($this->tenantA, 'CNC Mill', 'M-CNC', 50.0);

        $this->sectionService->create(new WorkCenterSectionDTO(
            work_center_id: $this->workCenterA->id,
            name: 'CNC Milling Section',
            code: 'SEC-CNC',
            supports_manual_production: false,
            manual_capacity_per_hour: null,
            status: WorkCenterSection::STATUS_ACTIVE,
            shift_ids: [$shift1->id, $shift2->id],
            shift_workers: [$shift1->id => 2, $shift2->id => 2],
            machine_ids: [$m1->id]
        ), $this->tenantA->id);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('production.work-centers.show', $this->workCenterA->id));

        $response->assertStatus(200);

        // Check Work Center Capacity per Hour
        $response->assertSee('50.00 units/hr');

        // Check Modal ID and Key Headers
        $response->assertSee('id="workCenterCalculationModal"', false);
        $response->assertSee('Work Center Calculation Breakdown');
        $response->assertSee('Work Center Capacity Breakdown');
        $response->assertSee('Work Center Labor Cost Breakdown');
        $response->assertSee('Total Theoretical Capacity');
        $response->assertSee('Contributing Sections');
        $response->assertSee('CNC Milling Section');
    }
}
