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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MilestoneTaskCountsTest extends TestCase
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

    /** @test */
    public function it_returns_task_list_and_task_counts_per_milestone(): void
    {
        $milestone = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Design Phase',
            'status' => Milestone::STATUS_ACTIVE,
        ]);

        $listA = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'name' => 'List A',
        ]);

        $listB = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'name' => 'List B',
        ]);

        Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'task_list_id' => $listA->id,
            'task_code' => 'TASK-0001',
            'title' => 'Task 1',
            'status' => Task::STATUS_COMPLETED,
        ]);

        Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'task_list_id' => $listA->id,
            'task_code' => 'TASK-0002',
            'title' => 'Task 2',
            'status' => Task::STATUS_OPEN,
        ]);

        Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'task_list_id' => $listB->id,
            'task_code' => 'TASK-0003',
            'title' => 'Task 3',
            'status' => Task::STATUS_COMPLETED,
        ]);

        $milestones = $this->service->list($this->project);
        $result = $milestones->firstOrFail();

        $this->assertSame(2, $result->task_lists_count);
        $this->assertSame(3, $result->tasks_count);
        $this->assertSame(2, $result->completed_tasks_count);
    }

    /** @test */
    public function listing_milestones_with_counts_does_not_grow_queries_per_milestone(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $milestone = Milestone::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => 'Milestone '.$i,
                'status' => Milestone::STATUS_ACTIVE,
            ]);

            $taskList = TaskList::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'milestone_id' => $milestone->id,
                'name' => 'Tasks',
            ]);

            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'milestone_id' => $milestone->id,
                'task_list_id' => $taskList->id,
                'task_code' => 'TASK-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'title' => 'Task',
                'status' => Task::STATUS_COMPLETED,
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $milestones = $this->service->list($this->project);
        $milestones->each(fn (Milestone $milestone) => $milestone->owner);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(5, $milestones);
        // One query for milestones (with its withCount subqueries folded in)
        // plus one for the eager-loaded owner relation — not one pair per
        // milestone.
        $this->assertLessThanOrEqual(2, $queryCount);
    }
}
