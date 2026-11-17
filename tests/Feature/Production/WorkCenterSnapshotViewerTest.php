<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterCalculation;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\WorkCenterSectionCalculation;
use App\Domains\Production\Services\CountdownCalculationEngine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WC-IMPL-20 (WC-38 rule 3): every "#N ↗" button opens THAT exact snapshot, never the latest.
 */
class WorkCenterSnapshotViewerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private WorkCenter $wc;
    private WorkCenterSection $section;
    private ProductionShift $shift;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Snap', 'slug' => 'snap', 'status' => 'active', 'plan' => 'enterprise']);
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'snap@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $this->actingAs($user);
        $this->useTenant($this->tenant);

        $this->shift = ProductionShift::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'D',
            'start_time' => '06:00:00', 'end_time' => '15:00:00', 'break_minutes' => 60, 'active' => true,
        ]);
        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-S', 'status' => 'active']);
        $this->section = $this->makeSection($this->wc, 'S-1');

        $this->employee = Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-1', 'full_name' => 'Snapshot Worker',
            'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => 360000, // daily 1,000
            'shift_id' => $this->shift->id, 'status' => true,
        ]);
        $assignment = WorkCenterEmployeeAssignment::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id,
            'section_id' => $this->section->id, 'employee_id' => $this->employee->id, 'active' => true,
        ]);
        $batch = SectionManualProduction::create([
            'tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'shift_id' => $this->shift->id,
            'batch_name' => 'B1', 'capacity_per_hour' => 20, 'active' => true,
        ]);
        $batch->employees()->attach($assignment->id);
    }

    private function useTenant(Tenant $tenant): void
    {
        app(\App\Core\Tenant\TenantContext::class)->setTenant($tenant);
    }

    private function makeSection(WorkCenter $wc, string $code): WorkCenterSection
    {
        return WorkCenterSection::create([
            'tenant_id' => $wc->tenant_id, 'work_center_id' => $wc->id,
            'name' => $code, 'code' => $code, 'status' => 'active', 'assigned_shifts' => [$this->shift->id],
        ]);
    }

    /** Two recalculations: #1 at annual 360,000 (daily labor 1,000), #2 at annual 720,000 (daily labor 2,000). */
    private function twoSnapshots(): array
    {
        app(CountdownCalculationEngine::class)->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);
        $this->employee->update(['current_salary' => 720000]);
        app(CountdownCalculationEngine::class)->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);

        $wcSnaps = WorkCenterCalculation::where('work_center_id', $this->wc->id)->orderBy('id')->get();
        $secSnaps = WorkCenterSectionCalculation::where('section_id', $this->section->id)->orderBy('id')->get();
        $this->assertCount(2, $wcSnaps);
        $this->assertCount(2, $secSnaps);
        $this->assertEqualsWithDelta(1000.0, $secSnaps[0]->total_daily_labor_cost, 0.0001);
        $this->assertEqualsWithDelta(2000.0, $secSnaps[1]->total_daily_labor_cost, 0.0001);

        return [$wcSnaps, $secSnaps];
    }

    private function get_(string $url)
    {
        return $this->withHeaders(['X-Tenant' => 'snap'])->get($url);
    }

    public function test_section_snapshot_route_shows_that_exact_snapshot(): void
    {
        [, $secSnaps] = $this->twoSnapshots();

        $old = $this->get_(route('production.work-centers.sections.snapshots.modal', [$this->wc->id, $this->section->id, $secSnaps[0]->id]));
        $old->assertOk()
            ->assertSee('Snapshot #' . $secSnaps[0]->snapshot_number)
            ->assertSee('snapshot-not-latest-banner', false)
            ->assertSee('not the latest')
            ->assertSee('1,000.00')
            ->assertDontSee('2,000.00');

        $new = $this->get_(route('production.work-centers.sections.snapshots.modal', [$this->wc->id, $this->section->id, $secSnaps[1]->id]));
        $new->assertOk()
            ->assertSee('Snapshot #' . $secSnaps[1]->snapshot_number)
            ->assertDontSee('snapshot-not-latest-banner', false)
            ->assertSee('2,000.00');
    }

    public function test_work_center_snapshot_route_shows_that_exact_snapshot(): void
    {
        [$wcSnaps] = $this->twoSnapshots();

        $old = $this->get_(route('production.work-centers.snapshots.modal', [$this->wc->id, $wcSnaps[0]->id]));
        $old->assertOk()
            ->assertSee('Snapshot #' . $wcSnaps[0]->snapshot_number)
            ->assertSee('snapshot-not-latest-banner', false)
            ->assertSee(format_currency((float) $wcSnaps[0]->total_daily_cost))
            ->assertDontSee(format_currency((float) $wcSnaps[1]->total_daily_cost));

        $this->get_(route('production.work-centers.snapshots.modal', [$this->wc->id, $wcSnaps[1]->id]))
            ->assertOk()
            ->assertDontSee('snapshot-not-latest-banner', false)
            ->assertSee(format_currency((float) $wcSnaps[1]->total_daily_cost));
    }

    public function test_show_page_buttons_carry_their_own_snapshot_url(): void
    {
        [$wcSnaps, $secSnaps] = $this->twoSnapshots();

        $res = $this->get_(route('production.work-centers.show', $this->wc->id))->assertOk();
        foreach ([0, 1] as $i) {
            $res->assertSee(route('production.work-centers.snapshots.modal', [$this->wc->id, $wcSnaps[$i]->id]), false);
            $res->assertSee(route('production.work-centers.sections.snapshots.modal', [$this->wc->id, $this->section->id, $secSnaps[$i]->id]), false);
        }
        $res->assertSee('id="snapshotViewerModal"', false);
        // page-level modal still renders the latest snapshot
        $res->assertSee('id="workCenterCalculationModal"', false);
    }

    public function test_foreign_snapshots_return_404(): void
    {
        [$wcSnaps, $secSnaps] = $this->twoSnapshots();

        // section snapshot requested under another section of the same Work Center
        $other = $this->makeSection($this->wc, 'S-2');
        $this->get_(route('production.work-centers.sections.snapshots.modal', [$this->wc->id, $other->id, $secSnaps[0]->id]))->assertNotFound();

        // Work Center snapshot requested under another Work Center
        $wc2 = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC2', 'code' => 'WC-2', 'status' => 'active']);
        $this->get_(route('production.work-centers.snapshots.modal', [$wc2->id, $wcSnaps[0]->id]))->assertNotFound();
        // section belongs to another Work Center
        $this->get_(route('production.work-centers.sections.snapshots.modal', [$wc2->id, $this->section->id, $secSnaps[0]->id]))->assertNotFound();

        // another tenant's snapshot
        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'enterprise']);
        $this->useTenant($tenant2);
        $foreignWc = WorkCenter::create(['tenant_id' => $tenant2->id, 'name' => 'F', 'code' => 'WC-F', 'status' => 'active']);
        $foreignSnap = WorkCenterCalculation::create([
            'tenant_id' => $tenant2->id, 'work_center_id' => $foreignWc->id, 'snapshot_number' => 1,
            'trigger_type' => 'manual', 'batch_id' => 'foreign-batch', 'calculation_details' => [],
        ]);
        $this->useTenant($this->tenant);
        $this->get_(route('production.work-centers.snapshots.modal', [$this->wc->id, $foreignSnap->id]))->assertNotFound();
        $this->get_(route('production.work-centers.snapshots.modal', [$foreignWc->id, $foreignSnap->id]))->assertNotFound();
    }

    public function test_historical_section_snapshot_never_reads_live_batch_workers(): void
    {
        $batch = SectionManualProduction::where('section_id', $this->section->id)->first();
        // A legacy-style snapshot: batch row without stored worker cost details (would trigger the live fallback)
        $legacyDetails = ['manual_batches' => ['batches' => [[
            'id' => $batch->id, 'batch_name' => 'B1', 'shift_id' => $this->shift->id,
            'capacity_per_hour' => 20, 'daily_cap' => 180, 'shift_hours' => 9,
        ]]]];
        $old = WorkCenterSectionCalculation::create([
            'tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'snapshot_number' => 1,
            'trigger_type' => 'manual', 'batch_id' => 'legacy-' . uniqid(), 'calculation_details' => $legacyDetails,
        ]);
        $latest = WorkCenterSectionCalculation::create([
            'tenant_id' => $this->tenant->id, 'section_id' => $this->section->id, 'snapshot_number' => 2,
            'trigger_type' => 'manual', 'batch_id' => 'legacy-' . uniqid(), 'calculation_details' => $legacyDetails,
        ]);

        // historical: only what it stored — the live worker is not pulled in
        $this->get_(route('production.work-centers.sections.snapshots.modal', [$this->wc->id, $this->section->id, $old->id]))
            ->assertOk()
            ->assertSee('snapshot-not-latest-banner', false)
            ->assertDontSee('Snapshot Worker');

        // latest keeps the existing live fallback for legacy snapshots
        $this->get_(route('production.work-centers.sections.snapshots.modal', [$this->wc->id, $this->section->id, $latest->id]))
            ->assertOk()
            ->assertDontSee('snapshot-not-latest-banner', false)
            ->assertSee('Snapshot Worker');
    }
}
