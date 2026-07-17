<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Services\MilestoneService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MilestoneKpiSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private Project $project;
    private MilestoneService $service;
    private int $taskSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'ERP Development',
            'owner_id' => $this->owner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $this->service = app(MilestoneService::class);
    }

    private function makeMilestoneWithTasks(array $overrides, int $totalTasks, int $completedTasks): Milestone
    {
        $milestone = Milestone::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Milestone',
            'status' => Milestone::STATUS_ACTIVE,
            'completion_percentage' => 0,
        ], $overrides));

        $taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'name' => 'Tasks',
        ]);

        for ($i = 0; $i < $totalTasks; $i++) {
            $this->taskSequence++;

            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'milestone_id' => $milestone->id,
                'task_list_id' => $taskList->id,
                'task_code' => 'TASK-'.str_pad((string) $this->taskSequence, 4, '0', STR_PAD_LEFT),
                'title' => 'Task '.$this->taskSequence,
                'status' => $i < $completedTasks ? Task::STATUS_COMPLETED : Task::STATUS_OPEN,
            ]);
        }

        return $milestone;
    }

    /** @test */
    public function it_counts_milestones_by_status(): void
    {
        $this->makeMilestoneWithTasks(['status' => Milestone::STATUS_ACTIVE], 0, 0);
        $this->makeMilestoneWithTasks(['status' => Milestone::STATUS_ACTIVE], 0, 0);
        $this->makeMilestoneWithTasks(['status' => Milestone::STATUS_COMPLETED], 0, 0);
        $this->makeMilestoneWithTasks(['status' => Milestone::STATUS_DRAFT], 0, 0);

        $milestones = $this->service->list($this->project);
        $summary = $this->service->buildKpiSummary($milestones);

        $this->assertSame(4, $summary['total']);
        $this->assertSame(2, $summary['active']);
        $this->assertSame(1, $summary['completed']);
    }

    /** @test */
    public function it_counts_overdue_active_milestones_but_excludes_finished_ones(): void
    {
        // Overdue and still active — counts.
        $this->makeMilestoneWithTasks([
            'status' => Milestone::STATUS_ACTIVE,
            'due_date' => Carbon::today()->subDays(2),
        ], 0, 0);

        // Overdue but already completed — does not count as overdue.
        $this->makeMilestoneWithTasks([
            'status' => Milestone::STATUS_COMPLETED,
            'due_date' => Carbon::today()->subDays(2),
        ], 0, 0);

        // Not yet due — does not count.
        $this->makeMilestoneWithTasks([
            'status' => Milestone::STATUS_ACTIVE,
            'due_date' => Carbon::today()->addDays(5),
        ], 0, 0);

        $milestones = $this->service->list($this->project);
        $summary = $this->service->buildKpiSummary($milestones);

        $this->assertSame(1, $summary['overdue']);
    }

    /** @test */
    public function overall_progress_is_task_weighted_not_a_naive_average_of_milestone_percentages(): void
    {
        // A tiny, fully-finished milestone (1/1 tasks)...
        $this->makeMilestoneWithTasks(['status' => Milestone::STATUS_ACTIVE, 'completion_percentage' => 100], 1, 1);

        // ...next to a large, barely-started one (0/9 tasks).
        $this->makeMilestoneWithTasks(['status' => Milestone::STATUS_ACTIVE, 'completion_percentage' => 0], 9, 0);

        $milestones = $this->service->list($this->project);
        $summary = $this->service->buildKpiSummary($milestones);

        // Naive average of (100% + 0%) / 2 would read 50% and badly overstate
        // progress. Task-weighted: 1 completed / 10 total = 10%.
        $this->assertSame(10, $summary['overall_progress']);
    }

    /** @test */
    public function overall_progress_is_zero_when_no_milestone_has_any_tasks(): void
    {
        $this->makeMilestoneWithTasks(['status' => Milestone::STATUS_ACTIVE], 0, 0);

        $milestones = $this->service->list($this->project);
        $summary = $this->service->buildKpiSummary($milestones);

        $this->assertSame(0, $summary['overall_progress']);
    }
}
