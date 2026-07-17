<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Services\MilestoneService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MilestoneHealthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private Project $project;
    private MilestoneService $service;

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

    private function makeMilestone(array $overrides = []): Milestone
    {
        return Milestone::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Design Phase',
            'status' => Milestone::STATUS_ACTIVE,
            'completion_percentage' => 0,
        ], $overrides));
    }

    /** @test */
    public function draft_milestones_are_not_applicable_regardless_of_dates(): void
    {
        $milestone = $this->makeMilestone([
            'status' => Milestone::STATUS_DRAFT,
            'start_date' => Carbon::today()->subDays(20),
            'due_date' => Carbon::today()->subDays(5),
            'completion_percentage' => 0,
        ]);

        $health = $this->service->resolveHealth($milestone);

        $this->assertSame(MilestoneService::HEALTH_NOT_APPLICABLE, $health['state']);
        $this->assertNull($health['reason']);
    }

    /** @test */
    public function on_hold_milestones_are_not_applicable(): void
    {
        $milestone = $this->makeMilestone([
            'status' => Milestone::STATUS_ON_HOLD,
            'start_date' => Carbon::today()->subDays(10),
            'due_date' => Carbon::today()->addDays(10),
        ]);

        $health = $this->service->resolveHealth($milestone);

        $this->assertSame(MilestoneService::HEALTH_NOT_APPLICABLE, $health['state']);
    }

    /** @test */
    public function active_milestone_without_dates_is_not_applicable(): void
    {
        $milestone = $this->makeMilestone([
            'status' => Milestone::STATUS_ACTIVE,
            'start_date' => null,
            'due_date' => null,
        ]);

        $health = $this->service->resolveHealth($milestone);

        $this->assertSame(MilestoneService::HEALTH_NOT_APPLICABLE, $health['state']);
    }

    /** @test */
    public function overdue_incomplete_active_milestone_is_off_track(): void
    {
        $milestone = $this->makeMilestone([
            'status' => Milestone::STATUS_ACTIVE,
            'start_date' => Carbon::today()->subDays(20),
            'due_date' => Carbon::today()->subDays(3),
            'completion_percentage' => 60,
        ]);

        $health = $this->service->resolveHealth($milestone);

        $this->assertSame(MilestoneService::HEALTH_OFF_TRACK, $health['state']);
        $this->assertStringContainsString('Overdue by 3 day', $health['reason']);
    }

    /** @test */
    public function milestone_far_behind_expected_pace_is_at_risk(): void
    {
        // 20 days elapsed of a 30-day span => ~67% expected. 20% actual is
        // well past the 15-point threshold, so this should read "at risk".
        $milestone = $this->makeMilestone([
            'status' => Milestone::STATUS_ACTIVE,
            'start_date' => Carbon::today()->subDays(20),
            'due_date' => Carbon::today()->addDays(10),
            'completion_percentage' => 20,
        ]);

        $health = $this->service->resolveHealth($milestone);

        $this->assertSame(MilestoneService::HEALTH_AT_RISK, $health['state']);
        $this->assertStringContainsString('behind expected pace', $health['reason']);
    }

    /** @test */
    public function milestone_matching_expected_pace_is_on_track(): void
    {
        // 10 days elapsed of a 20-day span => 50% expected. 60% actual is
        // ahead of pace, well within the threshold.
        $milestone = $this->makeMilestone([
            'status' => Milestone::STATUS_ACTIVE,
            'start_date' => Carbon::today()->subDays(10),
            'due_date' => Carbon::today()->addDays(10),
            'completion_percentage' => 60,
        ]);

        $health = $this->service->resolveHealth($milestone);

        $this->assertSame(MilestoneService::HEALTH_ON_TRACK, $health['state']);
        $this->assertNull($health['reason']);
    }

    /** @test */
    public function milestone_with_open_blocking_dependency_is_blocked(): void
    {
        $milestone = $this->makeMilestone([
            'status' => Milestone::STATUS_ACTIVE,
            'start_date' => Carbon::today()->subDays(5),
            'due_date' => Carbon::today()->addDays(5),
            'completion_percentage' => 40,
        ]);

        $taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'name' => 'Design Tasks',
        ]);

        $blockingTask = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'TASK-0001',
            'title' => 'Provision infrastructure',
            'status' => Task::STATUS_OPEN,
        ]);

        $milestoneTask = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'TASK-0002',
            'title' => 'Finalize design',
            'status' => Task::STATUS_OPEN,
        ]);

        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_id' => $milestoneTask->id,
            'depends_on_task_id' => $blockingTask->id,
        ]);

        $health = $this->service->resolveHealth($milestone);

        $this->assertSame(MilestoneService::HEALTH_BLOCKED, $health['state']);
        $this->assertStringContainsString('incomplete dependency', $health['reason']);
    }

    /** @test */
    public function blocking_dependency_stops_mattering_once_the_upstream_task_is_completed(): void
    {
        $milestone = $this->makeMilestone([
            'status' => Milestone::STATUS_ACTIVE,
            'start_date' => Carbon::today()->subDays(5),
            'due_date' => Carbon::today()->addDays(5),
            'completion_percentage' => 40,
        ]);

        $taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'name' => 'Design Tasks',
        ]);

        $completedBlockingTask = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'TASK-0001',
            'title' => 'Provision infrastructure',
            'status' => Task::STATUS_COMPLETED,
        ]);

        $milestoneTask = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'TASK-0002',
            'title' => 'Finalize design',
            'status' => Task::STATUS_OPEN,
        ]);

        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_id' => $milestoneTask->id,
            'depends_on_task_id' => $completedBlockingTask->id,
        ]);

        $health = $this->service->resolveHealth($milestone);

        $this->assertNotSame(MilestoneService::HEALTH_BLOCKED, $health['state']);
    }
}
