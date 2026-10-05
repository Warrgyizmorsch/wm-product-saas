<?php

namespace Tests\Feature;

use App\Domains\Platform\Services\NotificationEventCatalog;
use App\Domains\Projects\Events\ChangeRequestCreated;
use App\Domains\Projects\Events\IssueLogged;
use App\Domains\Projects\Events\IssueResolved;
use App\Domains\Projects\Events\IssueRetested;
use App\Domains\Projects\Events\ProjectClosed;
use App\Domains\Projects\Events\ProjectReviewRequested;
use App\Domains\Projects\Events\ProjectReviewSignedOff;
use App\Domains\Projects\Events\TaskAssigned;
use App\Domains\Projects\Events\TaskCompleted;
use App\Domains\Projects\Events\TimesheetApproved;
use App\Domains\Projects\Events\TimesheetRejected;
use App\Domains\Projects\Events\TimesheetSubmitted;
use App\Domains\Projects\Jobs\SendProjectNotificationEmailJob;
use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\ProjectReview;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Services\ChangeRequestService;
use App\Domains\Projects\Services\IssueService;
use App\Domains\Projects\Services\ProjectClosureService;
use App\Domains\Projects\Services\ProjectNotificationService;
use App\Domains\Projects\Services\ProjectReviewService;
use App\Domains\Projects\Services\TaskService;
use App\Domains\Projects\Services\TimeLogService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProjectNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private User $developer;
    private Project $project;
    private TaskList $taskList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Notification Workspace',
            'slug'   => 'notification-workspace',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->withHeader('X-Tenant', $this->tenant->slug);
        session(['tenant_id' => $this->tenant->id]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Project Manager',
            'email'     => 'pm@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->manager->id, 'role_id' => $ownerRole->id]);

        $this->developer = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Lead Developer',
            'email'     => 'dev@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->developer->id, 'role_id' => $ownerRole->id]);

        $this->project = Project::create([
            'tenant_id'    => $this->tenant->id,
            'project_code' => 'PRJ-NOTIF',
            'name'         => 'Notification Project',
            'manager_id'   => $this->manager->id,
            'owner_id'     => $this->manager->id,
            'status'       => Project::STATUS_ACTIVE,
            'start_date'   => now()->toDateString(),
        ]);

        ProjectMember::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'user_id'       => $this->developer->id,
            'project_role'  => 'collaborator',
            'rate_per_hour' => 50.00,
            'is_active'     => true,
        ]);

        $this->taskList = TaskList::create([
            'tenant_id'  => $this->tenant->id,
            'project_id' => $this->project->id,
            'name'       => 'Default Sprint',
            'position'   => 1,
        ]);
    }

    public function test_task_assigned_dispatches_event_and_creates_notification(): void
    {
        $this->actingAs($this->manager);

        $taskService = app(TaskService::class);
        $task = $taskService->create($this->project, [
            'task_list_id' => $this->taskList->id,
            'title'        => 'Build Notification Engine',
            'assigned_to'  => $this->developer->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->developer->id,
            'module'    => 'projects',
            'type'      => 'task.assigned',
        ]);

        $notification = Notification::where('user_id', $this->developer->id)
            ->where('type', 'task.assigned')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString($task->task_code, $notification->title);
        $this->assertEquals('feather-user-check', $notification->icon_class);
    }

    public function test_self_action_suppression_prevents_self_notification(): void
    {
        // When manager assigns task to themselves, no notification should be generated
        $this->actingAs($this->manager);

        $taskService = app(TaskService::class);
        $taskService->create($this->project, [
            'task_list_id' => $this->taskList->id,
            'title'        => 'Self Assigned Task',
            'assigned_to'  => $this->manager->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->manager->id,
            'type'    => 'task.assigned',
        ]);
    }

    public function test_task_completed_notifies_project_leadership(): void
    {
        $this->actingAs($this->developer);

        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'PRJ-NOTIF-T-001',
            'title'        => 'Finish UI Module',
            'assigned_to'  => $this->developer->id,
            'status'       => Task::STATUS_REVIEW,
        ]);

        $taskService = app(TaskService::class);
        $taskService->updateStatus($task, Task::STATUS_COMPLETED);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->manager->id,
            'module'    => 'projects',
            'type'      => 'task.completed',
        ]);
    }

    public function test_issue_logged_and_resolved_notifications(): void
    {
        $this->actingAs($this->manager);
        $issueService = app(IssueService::class);

        // 1. Issue Logged
        $issue = $issueService->create($this->project, [
            'title'       => 'Dropdown click issue',
            'assignee_id' => $this->developer->id,
            'severity'    => Issue::SEVERITY_CRITICAL,
            'priority'    => Issue::PRIORITY_HIGH,
        ], $this->manager);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->developer->id,
            'type'    => 'issue.logged',
        ]);

        // 2. Issue Resolved
        $this->actingAs($this->developer);
        $issueService->resolve($issue, 'Fixed CSS z-index and event bubbling', $this->developer);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->manager->id,
            'type'    => 'issue.resolved',
        ]);
    }

    public function test_issue_retest_passed_and_failed_notifications(): void
    {
        $issue = Issue::create([
            'tenant_id'        => $this->tenant->id,
            'project_id'       => $this->project->id,
            'issue_number'     => 'ISS-001',
            'title'            => 'Memory leak in scheduler',
            'reporter_id'      => $this->manager->id,
            'assignee_id'      => $this->developer->id,
            'status'           => Issue::STATUS_RESOLVED,
            'severity'         => Issue::SEVERITY_MAJOR,
            'priority'         => Issue::PRIORITY_MEDIUM,
            'resolution_notes' => 'Cleaned timer handlers',
        ]);

        $this->actingAs($this->manager);
        $issueService = app(IssueService::class);

        // Retest Passed
        $issueService->retest($issue, true, 'Verified heap allocation stable', $this->manager);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->developer->id,
            'type'    => 'issue.retested',
            'icon_class' => 'feather-check',
        ]);
    }

    public function test_timesheet_submitted_and_approved_notifications(): void
    {
        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'PRJ-NOTIF-T-002',
            'title'        => 'API integration',
            'status'       => Task::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($this->developer);
        $timeLogService = app(TimeLogService::class);

        // 1. Submit Time
        $log = $timeLogService->logTime($this->project, $task, $this->developer, [
            'log_date' => now()->toDateString(),
            'hours'    => 4.5,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->manager->id,
            'type'    => 'timesheet.submitted',
        ]);

        // 2. Approve Time
        $this->actingAs($this->manager);
        $timeLogService->approve($log, $this->manager);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->developer->id,
            'type'    => 'timesheet.approved',
        ]);
    }

    public function test_timesheet_rejected_notification(): void
    {
        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'PRJ-NOTIF-T-003',
            'title'        => 'Database migration',
            'status'       => Task::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($this->developer);
        $timeLogService = app(TimeLogService::class);

        $log = $timeLogService->logTime($this->project, $task, $this->developer, [
            'log_date' => now()->toDateString(),
            'hours'    => 8.0,
        ]);

        $this->actingAs($this->manager);
        $timeLogService->reject($log, $this->manager, 'Overlapping with sprint planning');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->developer->id,
            'type'    => 'timesheet.rejected',
        ]);

        $notification = Notification::where('user_id', $this->developer->id)
            ->where('type', 'timesheet.rejected')
            ->first();

        $this->assertStringContainsString('Overlapping with sprint planning', $notification->message);
    }

    public function test_project_review_and_change_request_notifications(): void
    {
        $milestone = Milestone::create([
            'tenant_id'      => $this->tenant->id,
            'project_id'     => $this->project->id,
            'milestone_code' => 'M1',
            'name'           => 'Phase 1 MVP',
            'status'         => Milestone::STATUS_COMPLETED,
        ]);

        $this->actingAs($this->manager);
        $reviewService = app(ProjectReviewService::class);

        // 1. Request Review with Developer as reviewer
        $review = $reviewService->create($this->project, [
            'reviewer_id' => $this->developer->id,
            'review_date' => now()->toDateString(),
            'comments'    => 'Ready for UAT test pass',
        ], $this->manager);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->developer->id,
            'type'    => 'review.requested',
        ]);

        // 2. Sign Off as Rework Required
        $this->actingAs($this->developer);
        $reviewService->signoff($review, ProjectReview::STATUS_REWORK_REQUIRED, 'Missing edge-case validation', 'CR-001', $this->developer);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->manager->id,
            'type'    => 'review.signed_off',
        ]);

        // 3. Create Change Request
        $crService = app(ChangeRequestService::class);
        $cr = $crService->create($this->project, [
            'project_review_id'    => $review->id,
            'title'                => 'Add edge-case validation',
            'description'          => 'Scope extension for edge-cases',
            'impact_schedule_days' => 2,
            'impact_budget_amount' => 500,
            'impact_budget_hours'  => 8,
        ], $this->developer);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->manager->id,
            'type'    => 'change_request.created',
        ]);
    }

    public function test_deduplication_window_updates_existing_unread_notification(): void
    {
        $this->actingAs($this->manager);
        $service = app(ProjectNotificationService::class);

        $first = $service->notifyUser(
            recipient: $this->developer,
            actor: $this->manager,
            project: $this->project,
            type: 'task.assigned',
            title: 'Task Assigned: T-1',
            message: 'Original Message',
            actionUrl: '/projects/1/tasks/1',
            iconClass: 'feather-user-check',
            data: ['entity_id' => 101]
        );

        $this->assertNotNull($first);
        $this->assertEquals(1, Notification::where('user_id', $this->developer->id)->count());

        // Second notification within 15 mins for same entity
        $second = $service->notifyUser(
            recipient: $this->developer,
            actor: $this->manager,
            project: $this->project,
            type: 'task.assigned',
            title: 'Task Re-assigned: T-1',
            message: 'Updated Message',
            actionUrl: '/projects/1/tasks/1',
            iconClass: 'feather-user-check',
            data: ['entity_id' => 101]
        );

        $this->assertEquals(1, Notification::where('user_id', $this->developer->id)->count());
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals('Updated Message', $second->fresh()->message);
    }

    public function test_user_preferences_suppress_in_app_and_dispatch_email_job(): void
    {
        Queue::fake();

        // Configure developer to disable in-app and enable email
        $this->developer->update([
            'settings' => [
                'notifications' => [
                    'in_app' => false,
                    'email'  => true,
                ],
            ],
        ]);

        $service = app(ProjectNotificationService::class);
        $result = $service->notifyUser(
            recipient: $this->developer,
            actor: $this->manager,
            project: $this->project,
            type: 'task.assigned',
            title: 'Task Assigned',
            message: 'Check email',
            actionUrl: '/tasks/10'
        );

        $this->assertNull($result);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->developer->id,
        ]);

        Queue::assertPushed(SendProjectNotificationEmailJob::class, function ($job) {
            return $job->toEmail === 'dev@example.com';
        });
    }

    public function test_notification_event_catalog_contains_projects_module_with_all_12_events(): void
    {
        $events = NotificationEventCatalog::getEvents();

        $this->assertArrayHasKey('projects', $events);
        $this->assertEquals('Project Management', $events['projects']['label']);

        $expectedKeys = [
            'projects.task.assigned',
            'projects.task.completed',
            'projects.issue.logged',
            'projects.issue.resolved',
            'projects.issue.retested',
            'projects.timesheet.submitted',
            'projects.timesheet.approved',
            'projects.timesheet.rejected',
            'projects.review.requested',
            'projects.review.signed_off',
            'projects.change_request.created',
            'projects.project.closed',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $events['projects']['events'], "Missing event key: {$key}");
            $details = NotificationEventCatalog::getEventDetails($key);
            $this->assertNotNull($details, "Failed to resolve details for {$key}");
            $this->assertEquals('projects', $details['module']);
        }
    }

    public function test_localization_parity_for_phase_9_notification_strings(): void
    {
        $enKeys = require resource_path('../lang/en/projects.php');
        $hiKeys = require resource_path('../lang/hi/projects.php');
        $bgKeys = require resource_path('../lang/bg/projects.php');

        $requiredNotificationKeys = [
            'notification_task_assigned_title',
            'notification_task_assigned_body',
            'notification_task_completed_title',
            'notification_task_completed_body',
            'notification_issue_logged_title',
            'notification_issue_logged_body',
            'notification_issue_resolved_title',
            'notification_issue_resolved_body',
            'notification_issue_retested_passed_title',
            'notification_issue_retested_passed_body',
            'notification_issue_retested_failed_title',
            'notification_issue_retested_failed_body',
            'notification_timesheet_submitted_title',
            'notification_timesheet_submitted_body',
            'notification_timesheet_approved_title',
            'notification_timesheet_approved_body',
            'notification_timesheet_rejected_title',
            'notification_timesheet_rejected_body',
            'notification_review_requested_title',
            'notification_review_requested_body',
            'notification_review_signed_off_title',
            'notification_review_signed_off_body',
            'notification_change_request_created_title',
            'notification_change_request_created_body',
            'notification_project_closed_title',
            'notification_project_closed_body',
        ];

        foreach ($requiredNotificationKeys as $key) {
            $this->assertArrayHasKey($key, $enKeys, "Missing EN key: {$key}");
            $this->assertArrayHasKey($key, $hiKeys, "Missing HI key: {$key}");
            $this->assertArrayHasKey($key, $bgKeys, "Missing BG key: {$key}");
        }
    }

    public function test_project_closed_dispatches_event_and_notifies_members(): void
    {
        $this->actingAs($this->manager);
        $closureService = app(ProjectClosureService::class);

        $closed = $closureService->close($this->project, [
            'closure_status'      => Project::CLOSURE_STATUS_COMPLETED,
            'closure_date'        => now()->toDateString(),
            'client_approval_ref' => 'REF-CLOSE-001',
            'final_remarks'       => 'All deliverables achieved successfully',
        ], $this->manager);

        $this->assertEquals(Project::STATUS_CLOSED, $closed->status);

        // Active project member (developer) should receive notification
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->developer->id,
            'type'      => 'project.closed',
        ]);

        // Manager performed closure, so manager self-action is suppressed
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->manager->id,
            'type'    => 'project.closed',
        ]);
    }
}
