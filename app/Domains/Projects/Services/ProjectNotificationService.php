<?php

namespace App\Domains\Projects\Services;

use App\Domains\HRMS\Models\Employee;
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
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\TimeLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ProjectNotificationService
{
    /**
     * Dispatch notification to a user with self-action suppression, preferences gating,
     * deduplication, and optional queued email.
     */
    public function notifyUser(
        User $recipient,
        User $actor,
        Project $project,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $iconClass = 'feather-bell',
        array $data = []
    ): ?Notification {
        // 1. Self-action suppression
        if ((int) $recipient->id === (int) $actor->id) {
            return null;
        }

        $settings = is_array($recipient->settings) ? $recipient->settings : [];
        $inAppEnabled = $settings['notifications']['in_app'] ?? true;
        $emailEnabled = $settings['notifications']['email'] ?? false;

        $notification = null;

        // 2. In-App Notification (Immediate Synchronous Write)
        if ($inAppEnabled) {
            $entityId = $data['entity_id'] ?? null;

            // 15-Minute Deduplication Window: update existing unread alert instead of spamming
            $existing = Notification::query()
                ->where('tenant_id', $project->tenant_id)
                ->where('user_id', $recipient->id)
                ->where('module', 'projects')
                ->where('type', $type)
                ->whereNull('read_at')
                ->where('created_at', '>=', now()->subMinutes(15))
                ->when($entityId !== null, function ($query) use ($entityId) {
                    $query->where('data->entity_id', $entityId);
                })
                ->first();

            if ($existing) {
                $existing->update([
                    'title'      => $title,
                    'message'    => $message,
                    'action_url' => $actionUrl ?? $existing->action_url,
                    'icon_class' => $iconClass,
                    'data'       => array_merge($existing->data ?? [], $data),
                    'updated_at' => now(),
                ]);
                $notification = $existing;
            } else {
                $employee = Employee::where('user_id', $recipient->id)->first();

                try {
                    $notification = Notification::create([
                        'tenant_id'        => $project->tenant_id,
                        'company_id'       => $project->company_id,
                        'business_unit_id' => $project->business_unit_id ?? null,
                        'branch_id'        => $project->branch_id,
                        'user_id'          => $recipient->id,
                        'employee_id'      => $employee?->id,
                        'module'           => 'projects',
                        'type'             => $type,
                        'title'            => $title,
                        'message'          => $message,
                        'action_url'       => $actionUrl,
                        'icon_class'       => $iconClass,
                        'data'             => $data,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Project notification in-app create failed: " . $e->getMessage());
                }
            }
        }

        // 3. Email Notification (Queued Asynchronous Job)
        if ($emailEnabled && !empty($recipient->email)) {
            $this->queueEmail(
                tenantId: (int) $project->tenant_id,
                toEmail: $recipient->email,
                subject: $title,
                messageBody: $message,
                actionUrl: $actionUrl,
                toName: $recipient->name
            );
        }

        return $notification;
    }

    /**
     * Notify an external client contact via queued email without creating a User record.
     */
    public function notifyExternalClient(
        Project $project,
        string $toEmail,
        string $subject,
        string $message,
        ?string $actionUrl = null,
        ?string $toName = null
    ): void {
        if (empty($toEmail)) {
            return;
        }

        $this->queueEmail(
            tenantId: (int) $project->tenant_id,
            toEmail: $toEmail,
            subject: $subject,
            messageBody: $message,
            actionUrl: $actionUrl,
            toName: $toName
        );
    }

    /**
     * Helper to dispatch the asynchronous queued email job.
     */
    protected function queueEmail(
        int $tenantId,
        string $toEmail,
        string $subject,
        string $messageBody,
        ?string $actionUrl = null,
        ?string $toName = null
    ): void {
        $htmlBody = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
                <h2 style="color: #2563eb; margin-top: 0;">' . htmlspecialchars($subject) . '</h2>
                <p style="font-size: 15px; line-height: 1.6;">' . nl2br(htmlspecialchars($messageBody)) . '</p>
                ' . ($actionUrl ? '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($actionUrl) . '" style="background: #2563eb; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">View in ERP</a></p>' : '') . '
                <hr style="border: none; border-top: 1px solid #eee; margin-top: 30px;">
                <p style="font-size: 12px; color: #777;">This is an automated notification from your Project Management workspace.</p>
            </div>
        ';

        try {
            SendProjectNotificationEmailJob::dispatch(
                $tenantId,
                $toEmail,
                $subject,
                $htmlBody,
                $actionUrl,
                $toName
            );
        } catch (\Throwable $e) {
            Log::warning("Failed to queue ProjectNotificationEmailJob: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 12 Strongly-Typed Lifecycle Event Handlers
    // ─────────────────────────────────────────────────────────────────────────

    public function handleTaskAssigned(TaskAssigned $event): void
    {
        $task = $event->task;
        $actor = $event->actor;
        $project = $task->project ?? Project::find($task->project_id);
        $assigneeId = $task->assignee_id ?? $task->assigned_to;

        if (!$project || !$assigneeId) {
            return;
        }

        $assignee = $task->assignee ?? $task->assignedTo ?? User::find($assigneeId);
        if (!$assignee) {
            return;
        }

        $actionUrl = $this->safeRoute('projects.tasks.show', [$project, $task]);

        $this->notifyUser(
            recipient: $assignee,
            actor: $actor,
            project: $project,
            type: 'task.assigned',
            title: __('projects.notification_task_assigned_title', ['code' => $task->task_code]),
            message: __('projects.notification_task_assigned_body', [
                'title'   => $task->title,
                'project' => $project->name,
                'actor'   => $actor->name,
            ]),
            actionUrl: $actionUrl,
            iconClass: 'feather-user-check',
            data: [
                'project_id' => $project->id,
                'entity_id'  => $task->id,
                'task_code'  => $task->task_code,
            ]
        );
    }

    public function handleTaskCompleted(TaskCompleted $event): void
    {
        $task = $event->task;
        $actor = $event->actor;
        $project = $task->project;

        if (!$project) {
            return;
        }

        $recipients = $this->resolveProjectLeadership($project);
        $actionUrl = $this->safeRoute('projects.tasks.show', [$project, $task]);

        foreach ($recipients as $recipient) {
            $this->notifyUser(
                recipient: $recipient,
                actor: $actor,
                project: $project,
                type: 'task.completed',
                title: __('projects.notification_task_completed_title', ['code' => $task->task_code]),
                message: __('projects.notification_task_completed_body', [
                    'title'   => $task->title,
                    'project' => $project->name,
                    'actor'   => $actor->name,
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-check-circle',
                data: [
                    'project_id' => $project->id,
                    'entity_id'  => $task->id,
                    'task_code'  => $task->task_code,
                ]
            );
        }
    }

    public function handleIssueLogged(IssueLogged $event): void
    {
        $issue = $event->issue;
        $actor = $event->actor;
        $project = $issue->project;

        if (!$project) {
            return;
        }

        $recipients = collect();
        if ($issue->assignee_id && $issue->assignee) {
            $recipients->push($issue->assignee);
        }
        foreach ($this->resolveProjectLeadership($project) as $leader) {
            $recipients->push($leader);
        }

        $recipients = $recipients->unique('id');
        $actionUrl = $this->safeRoute('projects.issues.show', [$project, $issue]);

        foreach ($recipients as $recipient) {
            $this->notifyUser(
                recipient: $recipient,
                actor: $actor,
                project: $project,
                type: 'issue.logged',
                title: __('projects.notification_issue_logged_title', ['number' => $issue->issue_number]),
                message: __('projects.notification_issue_logged_body', [
                    'title'    => $issue->title,
                    'severity' => $issue->severity,
                    'project'  => $project->name,
                    'actor'    => $actor->name,
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-alert-circle',
                data: [
                    'project_id'   => $project->id,
                    'entity_id'    => $issue->id,
                    'issue_number' => $issue->issue_number,
                ]
            );
        }
    }

    public function handleIssueResolved(IssueResolved $event): void
    {
        $issue = $event->issue;
        $actor = $event->actor;
        $project = $issue->project;

        if (!$project) {
            return;
        }

        $recipients = collect();
        if ($issue->reporter_id && $issue->reporter) {
            $recipients->push($issue->reporter);
        }
        foreach ($this->resolveProjectLeadership($project) as $leader) {
            $recipients->push($leader);
        }

        $recipients = $recipients->unique('id');
        $actionUrl = $this->safeRoute('projects.issues.show', [$project, $issue]);

        foreach ($recipients as $recipient) {
            $this->notifyUser(
                recipient: $recipient,
                actor: $actor,
                project: $project,
                type: 'issue.resolved',
                title: __('projects.notification_issue_resolved_title', ['number' => $issue->issue_number]),
                message: __('projects.notification_issue_resolved_body', [
                    'title'   => $issue->title,
                    'project' => $project->name,
                    'actor'   => $actor->name,
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-check-circle',
                data: [
                    'project_id'   => $project->id,
                    'entity_id'    => $issue->id,
                    'issue_number' => $issue->issue_number,
                ]
            );
        }
    }

    public function handleIssueRetested(IssueRetested $event): void
    {
        $issue = $event->issue;
        $actor = $event->actor;
        $project = $issue->project;

        if (!$project) {
            return;
        }

        $recipients = collect();
        if ($issue->assignee_id && $issue->assignee) {
            $recipients->push($issue->assignee);
        }
        foreach ($this->resolveProjectLeadership($project) as $leader) {
            $recipients->push($leader);
        }

        $recipients = $recipients->unique('id');
        $actionUrl = $this->safeRoute('projects.issues.show', [$project, $issue]);

        $title = $event->passed
            ? __('projects.notification_issue_retested_passed_title', ['number' => $issue->issue_number])
            : __('projects.notification_issue_retested_failed_title', ['number' => $issue->issue_number]);

        $message = $event->passed
            ? __('projects.notification_issue_retested_passed_body', [
                'title'   => $issue->title,
                'project' => $project->name,
                'actor'   => $actor->name,
            ])
            : __('projects.notification_issue_retested_failed_body', [
                'title'   => $issue->title,
                'project' => $project->name,
                'actor'   => $actor->name,
            ]);

        $iconClass = $event->passed ? 'feather-check' : 'feather-rotate-ccw';

        foreach ($recipients as $recipient) {
            $this->notifyUser(
                recipient: $recipient,
                actor: $actor,
                project: $project,
                type: 'issue.retested',
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                iconClass: $iconClass,
                data: [
                    'project_id'   => $project->id,
                    'entity_id'    => $issue->id,
                    'issue_number' => $issue->issue_number,
                    'passed'       => $event->passed,
                ]
            );
        }
    }

    public function handleTimesheetSubmitted(TimesheetSubmitted $event): void
    {
        $timeLogs = $event->timeLogs;
        $actor = $event->actor;

        if ($timeLogs->isEmpty()) {
            return;
        }

        // Batch consolidation by project
        $grouped = $timeLogs->groupBy('project_id');

        foreach ($grouped as $projectId => $logs) {
            /** @var Project $project */
            $project = $event->project ?? Project::find($projectId);
            if (!$project) {
                continue;
            }

            $count = $logs->count();
            $totalHours = round((float) $logs->sum('hours'), 2);
            $actionUrl = $this->safeRoute('projects.timesheets.approval');

            $recipients = $this->resolveProjectLeadership($project);

            foreach ($recipients as $recipient) {
                $this->notifyUser(
                    recipient: $recipient,
                    actor: $actor,
                    project: $project,
                    type: 'timesheet.submitted',
                    title: __('projects.notification_timesheet_submitted_title', ['project' => $project->name]),
                    message: __('projects.notification_timesheet_submitted_body', [
                        'actor'   => $actor->name,
                        'count'   => $count,
                        'hours'   => $totalHours,
                        'project' => $project->name,
                    ]),
                    actionUrl: $actionUrl,
                    iconClass: 'feather-clock',
                    data: [
                        'project_id' => $project->id,
                        'count'      => $count,
                        'hours'      => $totalHours,
                    ]
                );
            }
        }
    }

    public function handleTimesheetApproved(TimesheetApproved $event): void
    {
        $timeLogs = $event->timeLogs;
        $actor = $event->actor;

        if ($timeLogs->isEmpty()) {
            return;
        }

        // Batch consolidation per timesheet submitter user
        $groupedByUser = $timeLogs->groupBy('user_id');

        foreach ($groupedByUser as $userId => $logs) {
            $recipient = User::find($userId);
            if (!$recipient) {
                continue;
            }

            $firstLog = $logs->first();
            $project = $firstLog->project ?? Project::find($firstLog->project_id);
            if (!$project) {
                continue;
            }

            $count = $logs->count();
            $totalHours = round((float) $logs->sum('hours'), 2);
            $actionUrl = $this->safeRoute('projects.show', [$project, 'tab' => 'tasks']);

            $this->notifyUser(
                recipient: $recipient,
                actor: $actor,
                project: $project,
                type: 'timesheet.approved',
                title: __('projects.notification_timesheet_approved_title', ['project' => $project->name]),
                message: __('projects.notification_timesheet_approved_body', [
                    'actor'   => $actor->name,
                    'count'   => $count,
                    'hours'   => $totalHours,
                    'project' => $project->name,
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-check',
                data: [
                    'project_id' => $project->id,
                    'count'      => $count,
                    'hours'      => $totalHours,
                ]
            );
        }
    }

    public function handleTimesheetRejected(TimesheetRejected $event): void
    {
        $timeLogs = $event->timeLogs;
        $actor = $event->actor;

        if ($timeLogs->isEmpty()) {
            return;
        }

        // Batch consolidation per timesheet submitter user
        $groupedByUser = $timeLogs->groupBy('user_id');

        foreach ($groupedByUser as $userId => $logs) {
            $recipient = User::find($userId);
            if (!$recipient) {
                continue;
            }

            $firstLog = $logs->first();
            $project = $firstLog->project ?? Project::find($firstLog->project_id);
            if (!$project) {
                continue;
            }

            $count = $logs->count();
            $totalHours = round((float) $logs->sum('hours'), 2);
            $actionUrl = $this->safeRoute('projects.show', [$project, 'tab' => 'tasks']);

            $this->notifyUser(
                recipient: $recipient,
                actor: $actor,
                project: $project,
                type: 'timesheet.rejected',
                title: __('projects.notification_timesheet_rejected_title', ['project' => $project->name]),
                message: __('projects.notification_timesheet_rejected_body', [
                    'actor'   => $actor->name,
                    'count'   => $count,
                    'hours'   => $totalHours,
                    'project' => $project->name,
                    'reason'  => $event->rejectionRemarks ?? 'N/A',
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-x-circle',
                data: [
                    'project_id'        => $project->id,
                    'count'             => $count,
                    'hours'             => $totalHours,
                    'rejection_remarks' => $event->rejectionRemarks,
                ]
            );
        }
    }

    public function handleProjectReviewRequested(ProjectReviewRequested $event): void
    {
        $review = $event->review;
        $actor = $event->actor;
        $project = $review->project;

        if (!$project) {
            return;
        }

        $actionUrl = $this->safeRoute('projects.show', [$project, 'tab' => 'reviews']);

        // 1. Notify internal reviewer if assigned
        if ($review->reviewer_id && $review->reviewer) {
            $this->notifyUser(
                recipient: $review->reviewer,
                actor: $actor,
                project: $project,
                type: 'review.requested',
                title: __('projects.notification_review_requested_title', ['project' => $project->name]),
                message: __('projects.notification_review_requested_body', [
                    'project' => $project->name,
                    'actor'   => $actor->name,
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-clipboard',
                data: [
                    'project_id' => $project->id,
                    'entity_id'  => $review->id,
                ]
            );
        }

        // 2. Also notify project leadership
        foreach ($this->resolveProjectLeadership($project) as $leader) {
            $this->notifyUser(
                recipient: $leader,
                actor: $actor,
                project: $project,
                type: 'review.requested',
                title: __('projects.notification_review_requested_title', ['project' => $project->name]),
                message: __('projects.notification_review_requested_body', [
                    'project' => $project->name,
                    'actor'   => $actor->name,
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-clipboard',
                data: [
                    'project_id' => $project->id,
                    'entity_id'  => $review->id,
                ]
            );
        }

        // 3. Notify external client contact if project has customer email and no internal reviewer
        if (!$review->reviewer_id && $project->customer?->email) {
            $this->notifyExternalClient(
                project: $project,
                toEmail: $project->customer->email,
                subject: __('projects.notification_review_requested_title', ['project' => $project->name]),
                message: __('projects.notification_review_requested_body', [
                    'project' => $project->name,
                    'actor'   => $actor->name,
                ]),
                actionUrl: $actionUrl,
                toName: $project->customer->name
            );
        }
    }

    public function handleProjectReviewSignedOff(ProjectReviewSignedOff $event): void
    {
        $review = $event->review;
        $actor = $event->actor;
        $project = $review->project;

        if (!$project) {
            return;
        }

        $actionUrl = $this->safeRoute('projects.show', [$project, 'tab' => 'reviews']);
        $recipients = $this->resolveProjectLeadership($project);

        foreach ($recipients as $recipient) {
            $this->notifyUser(
                recipient: $recipient,
                actor: $actor,
                project: $project,
                type: 'review.signed_off',
                title: __('projects.notification_review_signed_off_title', ['project' => $project->name]),
                message: __('projects.notification_review_signed_off_body', [
                    'project' => $project->name,
                    'status'  => $review->status,
                    'actor'   => $actor->name,
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-award',
                data: [
                    'project_id' => $project->id,
                    'entity_id'  => $review->id,
                    'status'     => $review->status,
                ]
            );
        }
    }

    public function handleChangeRequestCreated(ChangeRequestCreated $event): void
    {
        $cr = $event->changeRequest;
        $actor = $event->actor;
        $project = $cr->project;

        if (!$project) {
            return;
        }

        $actionUrl = $this->safeRoute('projects.show', [$project, 'tab' => 'reviews']);
        $recipients = $this->resolveProjectLeadership($project);

        foreach ($recipients as $recipient) {
            $this->notifyUser(
                recipient: $recipient,
                actor: $actor,
                project: $project,
                type: 'change_request.created',
                title: __('projects.notification_change_request_created_title', ['cr_number' => $cr->cr_number]),
                message: __('projects.notification_change_request_created_body', [
                    'cr_number' => $cr->cr_number,
                    'title'     => $cr->title,
                    'project'   => $project->name,
                    'actor'     => $actor->name,
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-git-pull-request',
                data: [
                    'project_id' => $project->id,
                    'entity_id'  => $cr->id,
                    'cr_number'  => $cr->cr_number,
                ]
            );
        }
    }

    public function handleProjectClosed(ProjectClosed $event): void
    {
        $project = $event->project;
        $actor = $event->actor;

        $actionUrl = $this->safeRoute('projects.show', $project);

        // Gather all active project members
        $recipients = collect();
        foreach ($this->resolveProjectLeadership($project) as $leader) {
            $recipients->push($leader);
        }

        if ($project->relationLoaded('members') || method_exists($project, 'members')) {
            $memberUsers = $project->members()->where('is_active', true)->with('user')->get()->pluck('user')->filter();
            foreach ($memberUsers as $user) {
                $recipients->push($user);
            }
        }

        $recipients = $recipients->unique('id');

        foreach ($recipients as $recipient) {
            $this->notifyUser(
                recipient: $recipient,
                actor: $actor,
                project: $project,
                type: 'project.closed',
                title: __('projects.notification_project_closed_title', ['code' => $project->project_code]),
                message: __('projects.notification_project_closed_body', [
                    'name'   => $project->name,
                    'status' => $project->closure_status ?? 'Closed',
                    'actor'  => $actor->name,
                ]),
                actionUrl: $actionUrl,
                iconClass: 'feather-archive',
                data: [
                    'project_id'     => $project->id,
                    'closure_status' => $project->closure_status,
                ]
            );
        }

        // Notify external client contact if email present
        if ($project->customer?->email) {
            $this->notifyExternalClient(
                project: $project,
                toEmail: $project->customer->email,
                subject: __('projects.notification_project_closed_title', ['code' => $project->project_code]),
                message: __('projects.notification_project_closed_body', [
                    'name'   => $project->name,
                    'status' => $project->closure_status ?? 'Closed',
                    'actor'  => $actor->name,
                ]),
                actionUrl: $actionUrl,
                toName: $project->customer->name
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper Methods
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve project manager and owner.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveProjectLeadership(Project $project): \Illuminate\Support\Collection
    {
        $leaders = collect();

        if ($project->manager_id && $project->manager) {
            $leaders->push($project->manager);
        }
        if ($project->owner_id && $project->owner) {
            $leaders->push($project->owner);
        }

        return $leaders->unique('id');
    }

    /**
     * Safely generate route URL or fallback to string.
     */
    protected function safeRoute(string $name, mixed $parameters = []): ?string
    {
        try {
            return route($name, $parameters);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
