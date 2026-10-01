<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectReview;
use App\Domains\Projects\Models\SubTask;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Repositories\ProjectRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectClosureService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly ActivityLogService $activityLogs,
    ) {
    }

    /**
     * Evaluate the 5 mandatory business gates for project closure.
     */
    public function evaluateGates(Project $project): array
    {
        $blockers = [];

        // Gate 1: Tasks & Subtasks
        $openTasks = Task::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
            ->get();
        $openTasksCount = $openTasks->count();

        $incompleteSubtasksCount = SubTask::query()
            ->whereHas('task', fn ($q) => $q->where('project_id', $project->id))
            ->where('is_completed', false)
            ->count();

        $gate1Passed = ($openTasksCount === 0) && ($incompleteSubtasksCount === 0);
        if ($openTasksCount > 0) {
            $blockers[] = __('projects.closure_blocked_by_open_tasks', ['count' => $openTasksCount]);
        }
        if ($incompleteSubtasksCount > 0) {
            $blockers[] = __('projects.closure_blocked_by_open_tasks', ['count' => $incompleteSubtasksCount]);
        }

        // Gate 2: Issues
        $unresolvedIssues = Issue::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', [Issue::STATUS_RESOLVED, Issue::STATUS_CLOSED])
            ->get();
        $unresolvedIssuesCount = $unresolvedIssues->count();
        $gate2Passed = ($unresolvedIssuesCount === 0);
        if ($unresolvedIssuesCount > 0) {
            $blockers[] = __('projects.closure_blocked_by_open_issues', ['count' => $unresolvedIssuesCount]);
        }

        // Gate 3: Reviews & Governance (UAT Sign-off & Change Requests)
        $hasMilestones = Milestone::query()->where('project_id', $project->id)->exists();
        $hasApprovedReview = ProjectReview::query()
            ->where('project_id', $project->id)
            ->where('status', ProjectReview::STATUS_APPROVED)
            ->exists();
        $pendingReviewsCount = ProjectReview::query()
            ->where('project_id', $project->id)
            ->where('status', ProjectReview::STATUS_PENDING)
            ->count();
        $pendingCrsCount = ChangeRequest::query()
            ->where('project_id', $project->id)
            ->where('status', ChangeRequest::STATUS_PENDING)
            ->count();

        // If milestones exist, at least one approved review is required.
        // Active reviews must not be pending. CRs must not be pending.
        $reviewApprovalSatisfied = !$hasMilestones || $hasApprovedReview;
        $gate3Passed = $reviewApprovalSatisfied && ($pendingReviewsCount === 0) && ($pendingCrsCount === 0);

        if (!$reviewApprovalSatisfied) {
            $blockers[] = __('projects.closure_blocked_by_missing_uat');
        }
        if ($pendingReviewsCount > 0) {
            $blockers[] = __('projects.closure_blocked_by_pending_reviews', ['count' => $pendingReviewsCount]);
        }
        if ($pendingCrsCount > 0) {
            $blockers[] = __('projects.closure_blocked_by_pending_change_requests', ['count' => $pendingCrsCount]);
        }

        // Gate 4: Billing Settlement
        $unbilledTimeLogs = TimeLog::query()
            ->where('project_id', $project->id)
            ->where('is_billable', true)
            ->where('approval_status', TimeLog::STATUS_APPROVED)
            ->where('is_invoiced', false)
            ->get();
        $unbilledTimeLogsCount = $unbilledTimeLogs->count();
        $unbilledHours = round($unbilledTimeLogs->sum('hours'), 2);

        $pendingTimeLogsCount = TimeLog::query()
            ->where('project_id', $project->id)
            ->where('approval_status', TimeLog::STATUS_PENDING)
            ->count();

        $unbilledMilestonesCount = Milestone::query()
            ->where('project_id', $project->id)
            ->where('billing_amount', '>', 0)
            ->where('status', Milestone::STATUS_COMPLETED)
            ->where('is_invoiced', false)
            ->count();

        $gate4Passed = ($unbilledTimeLogsCount === 0) && ($pendingTimeLogsCount === 0) && ($unbilledMilestonesCount === 0);
        if ($unbilledTimeLogsCount > 0) {
            $blockers[] = __('projects.closure_blocked_by_unbilled_timelogs', [
                'count' => $unbilledTimeLogsCount,
                'hours' => $unbilledHours,
            ]);
        }
        if ($pendingTimeLogsCount > 0) {
            $blockers[] = __('projects.closure_blocked_by_pending_timelogs', ['count' => $pendingTimeLogsCount]);
        }
        if ($unbilledMilestonesCount > 0) {
            $blockers[] = __('projects.closure_blocked_by_unbilled_milestones', ['count' => $unbilledMilestonesCount]);
        }

        // Gate 5: Milestones Completion
        $incompleteMilestones = Milestone::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', [Milestone::STATUS_COMPLETED, Milestone::STATUS_CLOSED])
            ->get();
        $incompleteMilestonesCount = $incompleteMilestones->count();
        $gate5Passed = ($incompleteMilestonesCount === 0);
        if ($incompleteMilestonesCount > 0) {
            $blockers[] = __('projects.closure_blocked_by_open_milestones', ['count' => $incompleteMilestonesCount]);
        }

        $canClose = $gate1Passed && $gate2Passed && $gate3Passed && $gate4Passed && $gate5Passed;

        return [
            'can_close' => $canClose,
            'gates' => [
                'tasks' => [
                    'passed'                    => $gate1Passed,
                    'open_tasks_count'          => $openTasksCount,
                    'incomplete_subtasks_count' => $incompleteSubtasksCount,
                ],
                'issues' => [
                    'passed'            => $gate2Passed,
                    'unresolved_count'  => $unresolvedIssuesCount,
                ],
                'reviews' => [
                    'passed'                => $gate3Passed,
                    'has_approved_review'   => $hasApprovedReview,
                    'has_milestones'        => $hasMilestones,
                    'pending_reviews_count' => $pendingReviewsCount,
                    'pending_crs_count'     => $pendingCrsCount,
                ],
                'billing' => [
                    'passed'                    => $gate4Passed,
                    'unbilled_time_logs_count'  => $unbilledTimeLogsCount,
                    'pending_time_logs_count'   => $pendingTimeLogsCount,
                    'unbilled_milestones_count' => $unbilledMilestonesCount,
                ],
                'milestones' => [
                    'passed'           => $gate5Passed,
                    'incomplete_count' => $incompleteMilestonesCount,
                ],
            ],
            'blockers' => $blockers,
        ];
    }

    /**
     * Assert all 5 gates pass, or throw ValidationException.
     */
    public function assertClosable(Project $project): void
    {
        $evaluation = $this->evaluateGates($project);

        if (!$evaluation['can_close']) {
            throw ValidationException::withMessages([
                'closure' => $evaluation['blockers'],
            ]);
        }
    }

    /**
     * Execute transactional closure with row-locking and activity logging.
     */
    public function close(Project $project, array $data, User $closer): Project
    {
        return DB::transaction(function () use ($project, $data, $closer) {
            // Lock row against concurrent mutations
            /** @var Project $lockedProject */
            $lockedProject = Project::query()
                ->where('id', $project->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProject->isClosed()) {
                throw ValidationException::withMessages([
                    'status' => [__('projects.project_already_closed', [
                        'default' => 'This project is already closed.',
                    ])],
                ]);
            }

            // Strictly enforce all 5 gates
            $this->assertClosable($lockedProject);

            $closureDate = $data['closure_date'] ?? now()->toDateString();
            $closureStatus = $data['closure_status'] ?? Project::CLOSURE_STATUS_COMPLETED;
            $clientApprovalRef = $data['client_approval_ref'] ?? null;
            $finalRemarks = $data['final_remarks'] ?? null;

            $this->projects->update($lockedProject->id, [
                'status'              => Project::STATUS_CLOSED,
                'closure_date'        => $closureDate,
                'closure_status'      => $closureStatus,
                'client_approval_ref' => $clientApprovalRef,
                'final_remarks'       => $finalRemarks,
                'closed_by'           => $closer->id,
            ]);

            $freshProject = $this->projects->find($lockedProject->id);

            $this->activityLogs->record(
                $freshProject,
                'project.closed',
                __('projects.project_closed_title', [
                    'code' => $freshProject->project_code,
                    'default' => "Project :code closed",
                ]),
                __('projects.project_closed_description', [
                    'name'    => $freshProject->name,
                    'status'  => $closureStatus,
                    'closer'  => $closer->name,
                    'default' => "Project ':name' was formally closed as ':status' by :closer.",
                ]),
                $freshProject,
                [
                    'closure_status'      => $closureStatus,
                    'closure_date'        => $closureDate,
                    'client_approval_ref' => $clientApprovalRef,
                    'closed_by'           => $closer->id,
                ]
            );

            return $freshProject;
        });
    }
}
