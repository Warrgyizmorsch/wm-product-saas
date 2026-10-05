<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Events\ChangeRequestCreated;
use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectReview;
use App\Domains\Projects\Repositories\ChangeRequestRepositoryInterface;
use App\Domains\Projects\Repositories\ProjectReviewRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeRequestService
{
    public function __construct(
        private readonly ChangeRequestRepositoryInterface $changeRequests,
        private readonly ProjectReviewRepositoryInterface $reviews,
        private readonly ActivityLogService $activityLogs,
    ) {
    }

    public function list(Project $project, array $filters = []): Collection
    {
        return $this->changeRequests->getForProject($project->id, $filters);
    }

    public function find(int $id): ?ChangeRequest
    {
        return $this->changeRequests->find($id);
    }

    public function create(Project $project, array $data, User $requester): ChangeRequest
    {
        $reviewId = !empty($data['project_review_id']) ? (int) $data['project_review_id'] : null;

        if ($reviewId !== null) {
            $review = $this->reviews->find($reviewId);

            if (! $review || (int) $review->project_id !== (int) $project->id) {
                throw ValidationException::withMessages([
                    'project_review_id' => [__('projects.invalid_review_link', [
                        'default' => 'Linked review does not belong to this project.',
                    ])],
                ]);
            }

            if ($review->status !== ProjectReview::STATUS_REWORK_REQUIRED) {
                throw ValidationException::withMessages([
                    'project_review_id' => [__('projects.review_must_be_rework_required', [
                        'default' => 'A Change Request can only be linked to a review with status Rework Required.',
                    ])],
                ]);
            }

            $existingActive = ChangeRequest::where('project_review_id', $review->id)
                ->whereIn('status', [ChangeRequest::STATUS_PENDING, ChangeRequest::STATUS_APPROVED])
                ->exists();

            if ($existingActive) {
                throw ValidationException::withMessages([
                    'project_review_id' => [__('projects.review_already_has_active_cr')],
                ]);
            }
        }

        return DB::transaction(function () use ($project, $data, $requester, $reviewId) {
            $crNumber = $this->changeRequests->nextCrNumber($project);

            $cr = $this->changeRequests->create([
                'tenant_id'            => $project->tenant_id,
                'company_id'           => $project->company_id,
                'branch_id'            => $project->branch_id,
                'project_id'           => $project->id,
                'project_review_id'    => $reviewId,
                'cr_number'            => $crNumber,
                'title'                => $data['title'],
                'description'          => $data['description'],
                'requested_by'         => $requester->id,
                'impact_schedule_days' => (int) ($data['impact_schedule_days'] ?? 0),
                'impact_budget_amount' => (float) ($data['impact_budget_amount'] ?? 0.00),
                'impact_budget_hours'  => (float) ($data['impact_budget_hours'] ?? 0.00),
                'status'               => ChangeRequest::STATUS_PENDING,
                'created_by'           => $requester->id,
            ]);

            $this->activityLogs->record(
                $project,
                'project.change_request_created',
                __('projects.change_request_created_title', [
                    'user'   => $requester->name,
                    'number' => $cr->cr_number,
                    'title'  => $cr->title,
                ]),
                $cr->description,
                $cr,
                [
                    'cr_number'            => $cr->cr_number,
                    'impact_budget_amount' => (float) $cr->impact_budget_amount,
                    'impact_budget_hours'  => (float) $cr->impact_budget_hours,
                    'impact_schedule_days' => (int) $cr->impact_schedule_days,
                ]
            );

            event(new ChangeRequestCreated($cr, $requester));

            return $cr;
        });
    }

    public function approve(ChangeRequest $cr, User $approver): ChangeRequest
    {
        if (! $cr->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('projects.cr_not_pending')],
            ]);
        }

        // Separation of duty: Requester cannot approve their own CR unless Project Manager or Project Owner
        if ((int) $cr->requested_by === (int) $approver->id) {
            $project = $cr->project;
            $isLead = (int) $project->manager_id === (int) $approver->id
                || (int) $project->owner_id === (int) $approver->id;

            if (! $isLead) {
                throw ValidationException::withMessages([
                    'approved_by' => [__('projects.cannot_approve_own_cr')],
                ]);
            }
        }

        return DB::transaction(function () use ($cr, $approver) {
            $project = Project::where('id', $cr->project_id)->lockForUpdate()->firstOrFail();
            $lockedCr = ChangeRequest::where('id', $cr->id)->lockForUpdate()->firstOrFail();

            if (! $lockedCr->isPending()) {
                throw ValidationException::withMessages([
                    'status' => [__('projects.cr_not_pending')],
                ]);
            }

            $prevBudgetAmount = (float) ($project->budget_amount ?? 0.00);
            $prevBudgetHours = (float) ($project->budget_hours ?? 0.00);

            $project->increment('budget_amount', (float) $lockedCr->impact_budget_amount);
            $project->increment('budget_hours', (float) $lockedCr->impact_budget_hours);

            $freshProject = $project->fresh();

            $lockedCr->update([
                'status'      => ChangeRequest::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $this->activityLogs->record(
                $freshProject,
                'project.change_request_approved',
                __('projects.change_request_approved_title', [
                    'approver' => $approver->name,
                    'number'   => $lockedCr->cr_number,
                ]),
                $lockedCr->title,
                $lockedCr,
                [
                    'cr_number'              => $lockedCr->cr_number,
                    'impact_budget_amount'   => (float) $lockedCr->impact_budget_amount,
                    'impact_budget_hours'    => (float) $lockedCr->impact_budget_hours,
                    'previous_budget_amount' => $prevBudgetAmount,
                    'new_budget_amount'      => (float) $freshProject->budget_amount,
                    'previous_budget_hours'  => $prevBudgetHours,
                    'new_budget_hours'       => (float) $freshProject->budget_hours,
                ]
            );

            return $lockedCr->fresh(['requester', 'approver', 'review']);
        });
    }

    public function reject(ChangeRequest $cr, string $remarks, User $rejecter): ChangeRequest
    {
        if (! $cr->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('projects.cr_not_pending')],
            ]);
        }

        if (trim($remarks) === '') {
            throw ValidationException::withMessages([
                'rejection_remarks' => [__('projects.rejection_remarks_required')],
            ]);
        }

        return DB::transaction(function () use ($cr, $remarks, $rejecter) {
            $cr->update([
                'status'            => ChangeRequest::STATUS_REJECTED,
                'rejection_remarks' => $remarks,
            ]);

            $this->activityLogs->record(
                $cr->project,
                'project.change_request_rejected',
                __('projects.change_request_rejected_title', [
                    'user'   => $rejecter->name,
                    'number' => $cr->cr_number,
                ]),
                $remarks,
                $cr,
                [
                    'cr_number'         => $cr->cr_number,
                    'rejection_remarks' => $remarks,
                ]
            );

            return $cr->fresh(['requester', 'approver', 'review']);
        });
    }

    public function markImplemented(ChangeRequest $cr, User $actor): ChangeRequest
    {
        if (! $cr->isApproved()) {
            throw ValidationException::withMessages([
                'status' => [__('projects.cr_not_approved_for_implementation')],
            ]);
        }

        return DB::transaction(function () use ($cr, $actor) {
            $cr->update([
                'status' => ChangeRequest::STATUS_IMPLEMENTED,
            ]);

            $this->activityLogs->record(
                $cr->project,
                'project.change_request_implemented',
                __('projects.change_request_implemented_title', [
                    'user'   => $actor->name,
                    'number' => $cr->cr_number,
                ]),
                null,
                $cr,
                [
                    'cr_number' => $cr->cr_number,
                ]
            );

            return $cr->fresh(['requester', 'approver', 'review']);
        });
    }

    public function delete(ChangeRequest $cr, User $actor): bool
    {
        if ($cr->isApproved() || $cr->isImplemented()) {
            throw ValidationException::withMessages([
                'status' => [__('projects.approved_cr_immutable', [
                    'default' => 'Approved or implemented change requests cannot be deleted.',
                ])],
            ]);
        }

        return DB::transaction(function () use ($cr, $actor) {
            $project = $cr->project;

            $this->activityLogs->record(
                $project,
                'project.change_request_deleted',
                __('projects.change_request_deleted_title', [
                    'user'    => $actor->name,
                    'number'  => $cr->cr_number,
                    'default' => ':user deleted change request :number',
                ]),
                null,
                $cr
            );

            return (bool) $cr->delete();
        });
    }
}
