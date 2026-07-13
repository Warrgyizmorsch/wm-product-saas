<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the task list "show more" / collapse Blade rendering
 * (resources/views/modules/projects/tasklists/_list-card.blade.php).
 *
 * Deliberately scoped to server-rendered structure only - Bootstrap Collapse
 * behavior, localStorage persistence, and styling are not covered here.
 */
class TaskListShowMoreRenderingTest extends TestCase
{
    use RefreshDatabase;

    private const VISIBLE_LIMIT = 10;

    private Tenant $tenant;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create([
            'user_id' => $this->tenantOwner->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function createProjectWithTaskList(string $code, int $taskCount): TaskList
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => $code,
            'name' => "Project {$code}",
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Backlog',
            'position' => 1,
        ]);

        for ($i = 1; $i <= $taskCount; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $project->id,
                'task_list_id' => $taskList->id,
                'task_code' => sprintf('%s-T-%03d', $code, $i),
                'title' => "Task {$i}",
                'position' => $i,
                'status' => 'Open',
            ]);
        }

        return $taskList;
    }

    private function crawl(string $html): DOMXPath
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function getProjectShowHtml(Project $project): string
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.show', $project) . '?tab=tasklists');

        $response->assertOk();

        return $response->getContent();
    }

    /** @test */
    public function a_task_list_at_the_visible_limit_renders_no_show_more_button(): void
    {
        $taskList = $this->createProjectWithTaskList('PRJ-0001', self::VISIBLE_LIMIT);
        $xpath = $this->crawl($this->getProjectShowHtml($taskList->project));

        $this->assertSame(0, $xpath->query('//*[@data-task-list-show-more]')->length);
    }

    /** @test */
    public function a_task_list_beyond_the_visible_limit_renders_a_show_more_button(): void
    {
        $taskList = $this->createProjectWithTaskList('PRJ-0002', self::VISIBLE_LIMIT + 1);
        $xpath = $this->crawl($this->getProjectShowHtml($taskList->project));

        $this->assertSame(1, $xpath->query('//*[@data-task-list-show-more]')->length);
    }

    /** @test */
    public function tasks_beyond_the_visible_limit_are_initially_hidden(): void
    {
        $taskList = $this->createProjectWithTaskList('PRJ-0003', self::VISIBLE_LIMIT + 2);
        $xpath = $this->crawl($this->getProjectShowHtml($taskList->project));

        $rows = $xpath->query('//*[@data-task-row]');
        $this->assertSame(self::VISIBLE_LIMIT + 2, $rows->length);

        foreach ($rows as $row) {
            $index = (int) $row->getAttribute('data-task-index');
            $isHidden = str_contains($row->getAttribute('class'), 'd-none');

            $this->assertSame(
                $index >= self::VISIBLE_LIMIT,
                $isHidden,
                "Task row at index {$index} has an unexpected hidden state."
            );
        }
    }

    /** @test */
    public function multiple_task_lists_render_unique_collapse_container_ids(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0004',
            'name' => 'Multiple Task Lists Project',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Backlog A',
            'position' => 1,
        ]);
        TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Backlog B',
            'position' => 2,
        ]);

        $xpath = $this->crawl($this->getProjectShowHtml($project));

        $bodyIds = [];
        foreach ($xpath->query('//*[@data-task-list-body]') as $body) {
            $bodyIds[] = $body->getAttribute('id');
        }

        $this->assertCount(2, $bodyIds);
        $this->assertSame(array_unique($bodyIds), $bodyIds, 'Task list collapse container ids must be unique.');
        $this->assertNotContains('', $bodyIds, 'Task list collapse container ids must not be empty.');
    }
}
