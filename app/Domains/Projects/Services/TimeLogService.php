<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Events\TimesheetApproved;
use App\Domains\Projects\Events\TimesheetRejected;
use App\Domains\Projects\Events\TimesheetSubmitted;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Repositories\TimeLogRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeLogService
{
    public function __construct(
        private readonly TimeLogRepositoryInterface $timeLogs,
        private readonly ActivityLogService $activityLogs,
    ) {
    }

    public function logTime(Project $project, Task $task, User $user, array $data): TimeLog
    {
        // 1. Verify Collaborator Invariant: User must be an active project member
        $isMember = ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (!$isMember) {
            throw ValidationException::withMessages([
                'user_id' => [__('projects.collaborator_required_for_timelog', [
                    'default' => 'Only active project members can log time on this project.',
                ])],
            ]);
        }

        // 2. Derive hourly rate from project_members if not explicitly supplied
        $hourlyRate = $data['hourly_rate'] ?? null;
        if ($hourlyRate === null) {
            $member = ProjectMember::query()
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->first();
            $hourlyRate = $member?->rate_per_hour;
        }

        return DB::transaction(function () use ($project, $task, $user, $data, $hourlyRate) {
            $log = $this->timeLogs->create([
                'tenant_id'       => $project->tenant_id,
                'company_id'      => $project->company_id,
                'branch_id'       => $project->branch_id,
                'project_id'      => $project->id,
                'task_id'         => $task->id,
                'user_id'         => $user->id,
                'log_date'        => $data['log_date'],
                'start_time'      => $data['start_time'] ?? null,
                'end_time'        => $data['end_time'] ?? null,
                'hours'           => $data['hours'],
                'is_billable'     => $data['is_billable'] ?? true,
                'hourly_rate'     => $hourlyRate,
                'description'     => $data['description'] ?? null,
                'approval_status' => TimeLog::STATUS_PENDING,
            ]);

            $this->activityLogs->record(
                $project,
                'timelog.created',
                __('projects.timelog_created_title', [
                    'hours' => $log->hours,
                    'task'  => $task->task_code,
                    'user'  => $user->name,
                    'default' => ":user logged :hours hrs on :task",
                ]),
                $log->description,
                $log,
                [
                    'time_log_id' => $log->id,
                    'task_id'     => $task->id,
                    'hours'       => (float) $log->hours,
                    'is_billable' => (bool) $log->is_billable,
                ]
            );

            event(new TimesheetSubmitted($log, $user, $project));

            return $log;
        });
    }

    public function updateTimeLog(TimeLog $timeLog, array $data, User $actor): TimeLog
    {
        if ($timeLog->isApproved()) {
            throw ValidationException::withMessages([
                'approval_status' => [__('projects.approved_timelog_immutable', [
                    'default' => 'Approved time logs are locked and cannot be modified.',
                ])],
            ]);
        }

        return DB::transaction(function () use ($timeLog, $data, $actor) {
            $updated = $this->timeLogs->update($timeLog->id, [
                'log_date'    => $data['log_date'] ?? $timeLog->log_date,
                'start_time'  => array_key_exists('start_time', $data) ? $data['start_time'] : $timeLog->start_time,
                'end_time'    => array_key_exists('end_time', $data) ? $data['end_time'] : $timeLog->end_time,
                'hours'       => $data['hours'] ?? $timeLog->hours,
                'is_billable' => array_key_exists('is_billable', $data) ? $data['is_billable'] : $timeLog->is_billable,
                'hourly_rate' => array_key_exists('hourly_rate', $data) ? $data['hourly_rate'] : $timeLog->hourly_rate,
                'description' => array_key_exists('description', $data) ? $data['description'] : $timeLog->description,
            ]);

            $project = $timeLog->project;
            if ($project) {
                $this->activityLogs->record(
                    $project,
                    'timelog.updated',
                    __('projects.timelog_updated_title', [
                        'hours' => $updated->hours,
                        'user'  => $actor->name,
                        'default' => ":user updated time log #:id (:hours hrs)",
                        'id'    => $updated->id,
                    ]),
                    $updated->description,
                    $updated
                );
            }

            return $updated;
        });
    }

    public function approve(TimeLog $timeLog, User $approver): TimeLog
    {
        // Separation of duty: cannot approve own timesheet
        if ((int) $timeLog->user_id === (int) $approver->id) {
            throw ValidationException::withMessages([
                'approved_by' => [__('projects.cannot_approve_own_timelog', [
                    'default' => 'A team member cannot approve their own timesheet entry.',
                ])],
            ]);
        }

        return DB::transaction(function () use ($timeLog, $approver) {
            $approved = $this->timeLogs->update($timeLog->id, [
                'approval_status'   => TimeLog::STATUS_APPROVED,
                'approved_by'       => $approver->id,
                'approved_at'       => now(),
                'rejection_remarks' => null,
            ]);

            $this->recalculateTaskActualHours($timeLog->task_id);

            $project = $timeLog->project;
            if ($project) {
                $this->activityLogs->record(
                    $project,
                    'timelog.approved',
                    __('projects.timelog_approved_title', [
                        'approver' => $approver->name,
                        'hours'    => $approved->hours,
                        'user'     => $approved->user?->name ?? 'User',
                        'default'  => ":approver approved :hours hrs logged by :user",
                    ]),
                    null,
                    $approved,
                    [
                        'time_log_id' => $approved->id,
                        'task_id'     => $approved->task_id,
                        'hours'       => (float) $approved->hours,
                    ]
                );
            }

            event(new TimesheetApproved($approved, $approver));

            return $approved;
        });
    }

    public function reject(TimeLog $timeLog, User $approver, ?string $remarks = null): TimeLog
    {
        if ((int) $timeLog->user_id === (int) $approver->id) {
            throw ValidationException::withMessages([
                'approved_by' => [__('projects.cannot_reject_own_timelog', [
                    'default' => 'A team member cannot reject their own timesheet entry.',
                ])],
            ]);
        }

        return DB::transaction(function () use ($timeLog, $approver, $remarks) {
            $rejected = $this->timeLogs->update($timeLog->id, [
                'approval_status'   => TimeLog::STATUS_REJECTED,
                'approved_by'       => $approver->id,
                'approved_at'       => null,
                'rejection_remarks' => $remarks,
            ]);

            $this->recalculateTaskActualHours($timeLog->task_id);

            $project = $timeLog->project;
            if ($project) {
                $this->activityLogs->record(
                    $project,
                    'timelog.rejected',
                    __('projects.timelog_rejected_title', [
                        'approver' => $approver->name,
                        'hours'    => $rejected->hours,
                        'user'     => $rejected->user?->name ?? 'User',
                        'default'  => ":approver rejected :hours hrs logged by :user",
                    ]),
                    $remarks,
                    $rejected,
                    [
                        'time_log_id' => $rejected->id,
                        'task_id'     => $rejected->task_id,
                        'remarks'     => $remarks,
                    ]
                );
            }

            event(new TimesheetRejected($rejected, $approver, $remarks));

            return $rejected;
        });
    }

    public function deleteTimeLog(TimeLog $timeLog, User $actor): bool
    {
        if ($timeLog->isApproved()) {
            throw ValidationException::withMessages([
                'approval_status' => [__('projects.approved_timelog_immutable', [
                    'default' => 'Approved time logs are locked and cannot be deleted.',
                ])],
            ]);
        }

        return DB::transaction(function () use ($timeLog, $actor) {
            $taskId = $timeLog->task_id;
            $project = $timeLog->project;

            $deleted = $this->timeLogs->delete($timeLog->id);

            $this->recalculateTaskActualHours($taskId);

            if ($project) {
                $this->activityLogs->record(
                    $project,
                    'timelog.deleted',
                    __('projects.timelog_deleted_title', [
                        'user'    => $actor->name,
                        'hours'   => $timeLog->hours,
                        'default' => ":user deleted time log (:hours hrs)",
                    ]),
                    null,
                    null,
                    [
                        'task_id' => $taskId,
                        'hours'   => (float) $timeLog->hours,
                    ]
                );
            }

            return $deleted;
        });
    }

    public function recalculateTaskActualHours(int $taskId): void
    {
        $totalApprovedHours = TimeLog::query()
            ->where('task_id', $taskId)
            ->where('approval_status', TimeLog::STATUS_APPROVED)
            ->sum('hours');

        Task::query()
            ->where('id', $taskId)
            ->update(['actual_hours' => round((float) $totalApprovedHours, 2)]);
    }

    public function getForTask(int $taskId): Collection
    {
        return $this->timeLogs->getForTask($taskId);
    }

    public function getForProject(int $projectId, array $filters = []): Collection
    {
        return $this->timeLogs->getForProject($projectId, $filters);
    }

    public function getPendingApprovals(array $filters = []): Collection
    {
        return $this->timeLogs->getPendingApprovals($filters);
    }
}
