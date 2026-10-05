<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Events\IssueLogged;
use App\Domains\Projects\Events\IssueResolved;
use App\Domains\Projects\Events\IssueRetested;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Repositories\IssueRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueService
{
    public function __construct(
        private readonly IssueRepositoryInterface $issues,
        private readonly ActivityLogService $activityLogs,
    ) {
    }

    public function create(Project $project, array $data, User $reporter): Issue
    {
        // Validate assignee if provided
        if (!empty($data['assignee_id'])) {
            $this->validateAssigneeIsCollaborator($project, (int) $data['assignee_id']);
        }

        return DB::transaction(function () use ($project, $data, $reporter) {
            $issueNumber = $this->issues->nextIssueNumber($project);

            $issue = $this->issues->create([
                'tenant_id'    => $project->tenant_id,
                'company_id'   => $project->company_id,
                'branch_id'    => $project->branch_id,
                'project_id'   => $project->id,
                'task_id'      => $data['task_id'] ?? null,
                'issue_number' => $issueNumber,
                'title'        => $data['title'],
                'description'  => $data['description'] ?? null,
                'reporter_id'  => $reporter->id,
                'assignee_id'  => $data['assignee_id'] ?? null,
                'priority'           => $data['priority'] ?? Issue::PRIORITY_MEDIUM,
                'severity'           => $data['severity'] ?? Issue::SEVERITY_MAJOR,
                'status'             => !empty($data['assignee_id']) ? Issue::STATUS_ASSIGNED : Issue::STATUS_OPEN,
                'steps_to_reproduce' => $data['steps_to_reproduce'] ?? null,
            ]);

            $this->activityLogs->record(
                $project,
                'project.issue_created',
                __('projects.issue_created_title', [
                    'number'  => $issue->issue_number,
                    'title'   => $issue->title,
                    'user'    => $reporter->name,
                    'default' => ":user reported issue :number: :title",
                ]),
                null,
                $issue,
                [
                    'issue_number' => $issue->issue_number,
                    'severity'     => $issue->severity,
                    'priority'     => $issue->priority,
                ]
            );

            event(new IssueLogged($issue, $reporter));

            return $issue;
        });
    }

    public function update(Issue $issue, array $data, User $actor): Issue
    {
        $project = $issue->project;

        if (array_key_exists('assignee_id', $data) && !empty($data['assignee_id'])) {
            $this->validateAssigneeIsCollaborator($project, (int) $data['assignee_id']);
        }

        // If status changing to Resolved directly via update, ensure resolution_notes exists
        if (($data['status'] ?? null) === Issue::STATUS_RESOLVED && $issue->status !== Issue::STATUS_RESOLVED) {
            if (empty($data['resolution_notes'])) {
                throw ValidationException::withMessages([
                    'resolution_notes' => [__('projects.resolution_notes_required', [
                        'default' => 'Resolution notes are required when marking an issue as resolved.',
                    ])],
                ]);
            }
            $data['resolution_date'] = now();
        }

        return DB::transaction(function () use ($issue, $data, $project, $actor) {
            $originalStatus = $issue->status;
            $updated = $this->issues->update($issue->id, $data);

            if ($originalStatus !== $updated->status) {
                if ($updated->status === Issue::STATUS_RESOLVED) {
                    $this->activityLogs->record(
                        $project,
                        'project.issue_resolved',
                        __('projects.issue_resolved_title', [
                            'number'  => $updated->issue_number,
                            'user'    => $actor->name,
                            'default' => ":user resolved issue :number",
                        ]),
                        null,
                        $updated,
                        ['resolution_notes' => $updated->resolution_notes]
                    );

                    event(new IssueResolved($updated, $actor));
                } else {
                    $this->activityLogs->record(
                        $project,
                        'project.issue_status_changed',
                        __('projects.issue_status_changed_title', [
                            'number'  => $updated->issue_number,
                            'from'    => $originalStatus,
                            'to'      => $updated->status,
                            'user'    => $actor->name,
                            'default' => ":user changed status of :number from :from to :to",
                        ]),
                        null,
                        $updated,
                        [
                            'from' => $originalStatus,
                            'to'   => $updated->status,
                        ]
                    );
                }
            }

            return $updated;
        });
    }

    public function resolve(Issue $issue, string $notes, User $actor): Issue
    {
        if (trim($notes) === '') {
            throw ValidationException::withMessages([
                'resolution_notes' => [__('projects.resolution_notes_required', [
                    'default' => 'Resolution notes are required when resolving an issue.',
                ])],
            ]);
        }

        return DB::transaction(function () use ($issue, $notes, $actor) {
            $updated = $this->issues->update($issue->id, [
                'status'           => Issue::STATUS_RESOLVED,
                'resolution_date'  => now(),
                'resolution_notes' => $notes,
            ]);

            $this->activityLogs->record(
                $issue->project,
                'project.issue_resolved',
                __('projects.issue_resolved_title', [
                    'number'  => $updated->issue_number,
                    'user'    => $actor->name,
                    'default' => ":user resolved issue :number",
                ]),
                null,
                $updated,
                ['resolution_notes' => $notes]
            );

            event(new IssueResolved($updated, $actor));

            return $updated;
        });
    }

    public function retest(Issue $issue, bool $passed, string $notes, User $tester): Issue
    {
        if ($issue->status !== Issue::STATUS_RESOLVED) {
            throw ValidationException::withMessages([
                'status' => [__('projects.issue_must_be_resolved_for_retest', [
                    'default' => 'Only resolved issues can be retested.',
                ])],
            ]);
        }

        // Separation of duty: developer who fixed it cannot retest their own issue
        // unless they are tenant owner or project manager
        $isAssignee = (int) $issue->assignee_id === (int) $tester->id;
        $isManager = (int) $issue->project?->manager_id === (int) $tester->id
            || (int) $issue->project?->owner_id === (int) $tester->id
            || (method_exists($tester, 'hasRole') && ($tester->hasRole('tenant_owner') || $tester->hasRole('admin')));

        if ($isAssignee && !$isManager) {
            throw ValidationException::withMessages([
                'retest' => [__('projects.cannot_retest_own_resolved_issue', [
                    'default' => 'A team member cannot perform retest verification on their own resolved issue.',
                ])],
            ]);
        }

        return DB::transaction(function () use ($issue, $passed, $notes, $tester) {
            if ($passed) {
                $updated = $this->issues->update($issue->id, [
                    'status'       => Issue::STATUS_CLOSED,
                    'retest_notes' => $notes,
                ]);

                $this->activityLogs->record(
                    $issue->project,
                    'project.issue_retested',
                    __('projects.issue_retest_passed_title', [
                        'number'  => $updated->issue_number,
                        'user'    => $tester->name,
                        'default' => ":user verified and closed issue :number (Retest Passed)",
                    ]),
                    null,
                    $updated,
                    ['retest_notes' => $notes, 'result' => 'passed', 'status' => Issue::STATUS_CLOSED]
                );
            } else {
                $updated = $this->issues->update($issue->id, [
                    'status'          => Issue::STATUS_IN_PROGRESS,
                    'resolution_date' => null,
                    'retest_notes'    => $notes,
                ]);

                $this->activityLogs->record(
                    $issue->project,
                    'project.issue_retested',
                    __('projects.issue_retest_failed_title', [
                        'number'  => $updated->issue_number,
                        'user'    => $tester->name,
                        'default' => ":user rejected fix for :number (Retest Failed - Rework Required)",
                    ]),
                    null,
                    $updated,
                    ['retest_notes' => $notes, 'result' => 'failed', 'status' => Issue::STATUS_IN_PROGRESS]
                );
            }

            event(new IssueRetested($updated, $tester, $passed));

            return $updated;
        });
    }

    public function delete(Issue $issue, User $actor): bool
    {
        $project = $issue->project;
        $issueNumber = $issue->issue_number;
        $deleted = $this->issues->delete($issue->id);

        if ($deleted && $project) {
            $this->activityLogs->record(
                $project,
                'project.issue_deleted',
                __('projects.issue_deleted_title', [
                    'number'  => $issueNumber,
                    'user'    => $actor->name,
                    'default' => ":user deleted issue :number",
                ]),
                null,
                null,
                ['issue_number' => $issueNumber]
            );
        }

        return $deleted;
    }

    public function updateField(Issue $issue, string $field, mixed $value, User $actor): Issue
    {
        return $this->update($issue, [$field => $value], $actor);
    }

    private function validateAssigneeIsCollaborator(Project $project, int $userId): void
    {
        $isMember = ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();

        if (!$isMember) {
            throw ValidationException::withMessages([
                'assignee_id' => [__('projects.assignee_must_be_collaborator', [
                    'default' => 'Issue assignee must be an active collaborator on this project.',
                ])],
            ]);
        }
    }
}
