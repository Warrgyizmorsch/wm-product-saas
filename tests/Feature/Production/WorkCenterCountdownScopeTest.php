<?php

namespace Tests\Feature\Production;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterActivityLog;
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
 * WC-IMPL-13 — empty countdown queue = Work Center roll-up only; button scopes; countdown_timer trigger.
 * WC-IMPL-15 — a Work Center snapshot records which section snapshots it used.
 */
class WorkCenterCountdownScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private WorkCenter $wc;
    private WorkCenterSection $secA;
    private WorkCenterSection $secB;
    private ProductionShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Cd', 'slug' => 'cd', 'status' => 'active', 'plan' => 'enterprise']);
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'cd@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $this->actingAs($user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->shift = ProductionShift::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Day', 'code' => 'D',
            'start_time' => '06:00:00', 'end_time' => '15:00:00', 'break_minutes' => 60, 'active' => true,
        ]);
        $this->wc = WorkCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'WC', 'code' => 'WC-C', 'status' => 'active']);
        $this->secA = $this->makeSection('S-A');
        $this->secB = $this->makeSection('S-B');
    }

    private function makeSection(string $code): WorkCenterSection
    {
        $sec = WorkCenterSection::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id,
            'name' => $code, 'code' => $code, 'status' => 'active', 'assigned_shifts' => [$this->shift->id],
        ]);
        $emp = Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'E-' . $code, 'full_name' => 'Worker ' . $code,
            'gender' => 'male', 'date_of_joining' => '2025-01-01', 'current_salary' => 360000,
            'shift_id' => $this->shift->id, 'status' => true,
        ]);
        $as = WorkCenterEmployeeAssignment::create([
            'tenant_id' => $this->tenant->id, 'work_center_id' => $this->wc->id,
            'section_id' => $sec->id, 'employee_id' => $emp->id, 'active' => true,
        ]);
        $batch = SectionManualProduction::create([
            'tenant_id' => $this->tenant->id, 'section_id' => $sec->id, 'shift_id' => $this->shift->id,
            'batch_name' => 'B-' . $code, 'capacity_per_hour' => 10, 'active' => true,
        ]);
        $batch->employees()->attach($as->id);

        return $sec;
    }

    private function engine(): CountdownCalculationEngine
    {
        return app(CountdownCalculationEngine::class);
    }

    private function secSnapCount(WorkCenterSection $s): int
    {
        return WorkCenterSectionCalculation::where('section_id', $s->id)->count();
    }

    private function wcSnapCount(): int
    {
        return WorkCenterCalculation::where('work_center_id', $this->wc->id)->count();
    }

    /** First full calculation → both sections have snapshot #1, Work Center snapshot #1. */
    private function baseline(): void
    {
        $this->engine()->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);
        $this->assertSame(1, $this->secSnapCount($this->secA));
        $this->assertSame(1, $this->secSnapCount($this->secB));
        $this->assertSame(1, $this->wcSnapCount());
    }

    private function http()
    {
        return $this->withHeaders(['X-Tenant' => 'cd']);
    }

    // ── WC-IMPL-13 ───────────────────────────────────────────────────────────

    public function test_empty_queue_is_work_center_roll_up_only(): void
    {
        $this->baseline();
        $logsBefore = WorkCenterActivityLog::where('work_center_id', $this->wc->id)->count();

        // A Work Center-level edit arms the countdown without any section
        $this->engine()->triggerCountdown($this->wc->fresh(), null);
        $this->assertSame([], $this->wc->fresh()->pending_calculation_section_ids);
        $this->engine()->recalculateNow($this->wc->fresh(), 'countdown_timer');

        $this->assertSame(1, $this->secSnapCount($this->secA), 'section A must not be recalculated');
        $this->assertSame(1, $this->secSnapCount($this->secB), 'section B must not be recalculated');
        $this->assertSame(2, $this->wcSnapCount());

        $newLogs = WorkCenterActivityLog::where('work_center_id', $this->wc->id)->orderBy('id')->get()->slice($logsBefore);
        $this->assertCount(1, $newLogs, 'exactly one activity log entry');
        $this->assertSame('work_center', $newLogs->first()->calculation_type);
    }

    public function test_section_without_any_snapshot_is_still_calculated_on_roll_up_only(): void
    {
        $this->baseline();
        $secC = $this->makeSection('S-C');
        $this->engine()->recalculateNow($this->wc->fresh(), 'manual'); // queue empty

        $this->assertSame(1, $this->secSnapCount($this->secA));
        $this->assertSame(1, $this->secSnapCount($secC), 'new section gets its first snapshot');
    }

    public function test_queue_scope_recalculates_only_queued_sections(): void
    {
        $this->baseline();
        $this->engine()->triggerCountdown($this->wc->fresh(), $this->secA->id);
        $this->engine()->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_QUEUE);

        $this->assertSame(2, $this->secSnapCount($this->secA));
        $this->assertSame(1, $this->secSnapCount($this->secB));
    }

    public function test_all_scope_recalculates_every_active_section(): void
    {
        $this->baseline();
        $this->engine()->recalculateNow($this->wc->fresh(), 'manual', CountdownCalculationEngine::SCOPE_ALL);

        $this->assertSame(2, $this->secSnapCount($this->secA));
        $this->assertSame(2, $this->secSnapCount($this->secB));
    }

    public function test_header_and_banner_buttons_send_their_scope(): void
    {
        $this->baseline();

        // header button: scope=all → every section, recorded as manual
        $this->http()->post(route('production.work-centers.recalculate-now', $this->wc->id), ['scope' => 'all'])->assertRedirect();
        $this->assertSame(2, $this->secSnapCount($this->secA));
        $this->assertSame(2, $this->secSnapCount($this->secB));
        $this->assertSame('manual', WorkCenterCalculation::where('work_center_id', $this->wc->id)->latest('id')->first()->trigger_type);

        // banner button: scope=queue with only B queued
        $this->engine()->triggerCountdown($this->wc->fresh(), $this->secB->id);
        $this->http()->post(route('production.work-centers.recalculate-now', $this->wc->id), ['scope' => 'queue'])->assertRedirect();
        $this->assertSame(2, $this->secSnapCount($this->secA));
        $this->assertSame(3, $this->secSnapCount($this->secB));

        $page = $this->http()->get(route('production.work-centers.show', $this->wc->id))->assertOk();
        $page->assertSee('name="scope" value="all"', false);
    }

    public function test_browser_timer_trigger_only_counts_when_countdown_really_ended(): void
    {
        $this->baseline();
        $url = route('production.work-centers.recalculate-now', $this->wc->id);

        // no countdown pending (already processed) → nothing runs
        $this->http()->post($url, ['scope' => 'queue', 'trigger' => 'countdown_timer'])->assertRedirect();
        $this->assertSame(1, $this->wcSnapCount());

        // countdown still running → nothing runs
        $this->engine()->triggerCountdown($this->wc->fresh(), $this->secA->id);
        $this->http()->post($url, ['scope' => 'queue', 'trigger' => 'countdown_timer'])->assertRedirect();
        $this->assertSame(1, $this->wcSnapCount());

        // countdown ended → runs the queue, recorded as countdown_timer
        $this->wc->forceFill(['calculation_countdown_end_at' => now()->subSecond()])->save();
        $this->http()->post($url, ['scope' => 'all', 'trigger' => 'countdown_timer'])->assertRedirect();
        $this->assertSame(2, $this->wcSnapCount());
        $this->assertSame('countdown_timer', WorkCenterCalculation::where('work_center_id', $this->wc->id)->latest('id')->first()->trigger_type);
        $this->assertSame(2, $this->secSnapCount($this->secA));
        $this->assertSame(1, $this->secSnapCount($this->secB), 'timer runs the queue only, even if scope=all is posted');
    }

    public function test_page_reload_after_expiry_processes_an_empty_queue_as_roll_up(): void
    {
        $this->baseline();
        $this->engine()->triggerCountdown($this->wc->fresh(), null);
        $this->wc->forceFill(['calculation_countdown_end_at' => now()->subSecond()])->save();

        $this->http()->get(route('production.work-centers.show', $this->wc->id))->assertOk();

        $this->assertSame(2, $this->wcSnapCount());
        $this->assertSame('countdown_timer', WorkCenterCalculation::where('work_center_id', $this->wc->id)->latest('id')->first()->trigger_type);
        $this->assertSame(1, $this->secSnapCount($this->secA));
        $this->assertNull($this->wc->fresh()->calculation_countdown_end_at);
    }

    // ── WC-IMPL-15 ───────────────────────────────────────────────────────────

    public function test_work_center_snapshot_records_the_section_snapshots_it_used(): void
    {
        $this->baseline();
        // Only A recalculated: snapshot #2 uses A #2 (fresh) and B #1 (reused)
        $this->engine()->triggerCountdown($this->wc->fresh(), $this->secA->id);
        $this->engine()->recalculateNow($this->wc->fresh(), 'manual');

        $wc2 = WorkCenterCalculation::where('work_center_id', $this->wc->id)->latest('id')->first();
        $contrib = collect($wc2->calculation_details['sections_contribution'])->keyBy('section_id');

        $a2 = WorkCenterSectionCalculation::where('section_id', $this->secA->id)->latest('id')->first();
        $b1 = WorkCenterSectionCalculation::where('section_id', $this->secB->id)->latest('id')->first();
        $this->assertSame((int) $a2->id, $contrib[$this->secA->id]['section_calculation_id']);
        $this->assertSame(2, (int) $contrib[$this->secA->id]['section_snapshot_number']);
        $this->assertSame((int) $b1->id, $contrib[$this->secB->id]['section_calculation_id']);
        $this->assertSame(1, (int) $contrib[$this->secB->id]['section_snapshot_number']);

        // Drill-down in the Work Center modal opens exactly those section snapshots
        $res = $this->http()->get(route('production.work-centers.snapshots.modal', [$this->wc->id, $wc2->id]))->assertOk();
        $res->assertSee('View 52 Audit Metrics for S-B — Snapshot #1', false);
        $res->assertSee('View 52 Audit Metrics for S-A — Snapshot #2', false);
        $res->assertSee('[S-B #1] ↗', false);
        $res->assertSee(route('production.work-centers.sections.snapshots.modal', [$this->wc->id, $this->secB->id, $b1->id]), false);
    }

    public function test_old_work_center_snapshot_without_references_falls_back_to_latest_section_modal(): void
    {
        $this->baseline();
        $wc1 = WorkCenterCalculation::where('work_center_id', $this->wc->id)->first();
        $details = $wc1->calculation_details;
        foreach ($details['sections_contribution'] as &$row) {
            unset($row['section_calculation_id'], $row['section_snapshot_number']);
        }
        unset($row);
        $wc1->update(['calculation_details' => $details]);

        $res = $this->http()->get(route('production.work-centers.snapshots.modal', [$this->wc->id, $wc1->id]))->assertOk();
        $res->assertSee('View 52 Audit Metrics for S-A — (latest snapshot)', false);
        $res->assertSee('data-bs-target="#sectionCalculationModal_' . $this->secA->id . '"', false);
    }
}
