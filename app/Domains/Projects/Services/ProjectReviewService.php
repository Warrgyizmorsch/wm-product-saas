<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Events\ProjectReviewRequested;
use App\Domains\Projects\Events\ProjectReviewSignedOff;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Projects\Models\ProjectReview;
use App\Domains\Projects\Repositories\MilestoneRepositoryInterface;
use App\Domains\Projects\Repositories\ProjectReviewRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectReviewService
{
    public function __construct(
        private readonly ProjectReviewRepositoryInterface $reviews,
        private readonly MilestoneRepositoryInterface $milestones,
        private readonly ActivityLogService $activityLogs,
        private readonly ProjectDocumentService $documents,
    ) {
    }

    public function list(Project $project, array $filters = []): Collection
    {
        return $this->reviews->getForProject($project->id, $filters);
    }

    public function find(int $id): ?ProjectReview
    {
        return $this->reviews->find($id);
    }

    /**
     * Check if project has a verified approved UAT review.
     * Helper for future Phase 8 closure gate.
     */
    public function hasApprovedUatReview(Project $project): bool
    {
        return ProjectReview::where('project_id', $project->id)
            ->where('status', ProjectReview::STATUS_APPROVED)
            ->exists();
    }

    public function create(
        Project $project,
        array $data,
        User $actor,
        ?UploadedFile $evidenceFile = null
    ): ProjectReview {
        $milestones = $this->milestones->getForProject($project->id);

        if ($milestones->isEmpty()) {
            throw ValidationException::withMessages([
                'review_date' => [__('projects.uat_milestones_required')],
            ]);
        }

        $incomplete = $milestones->first(fn (Milestone $m) => $m->status !== Milestone::STATUS_COMPLETED);
        if ($incomplete !== null) {
            throw ValidationException::withMessages([
                'review_date' => [__('projects.uat_milestones_incomplete')],
            ]);
        }

        if ($this->reviews->hasPendingReview($project->id)) {
            throw ValidationException::withMessages([
                'review_date' => [__('projects.uat_pending_review_exists')],
            ]);
        }

        return DB::transaction(function () use ($project, $data, $actor, $evidenceFile) {
            $review = $this->reviews->create([
                'tenant_id'     => $project->tenant_id,
                'company_id'    => $project->company_id,
                'branch_id'     => $project->branch_id,
                'project_id'    => $project->id,
                'reviewer_id'   => $data['reviewer_id'] ?? null,
                'reviewer_name' => $data['reviewer_name'] ?? null,
                'review_date'   => $data['review_date'],
                'status'        => ProjectReview::STATUS_PENDING,
                'sign_off_ref'  => $data['sign_off_ref'] ?? null,
                'comments'      => $data['comments'] ?? null,
                'created_by'    => $actor->id,
            ]);

            if ($evidenceFile !== null) {
                $this->documents->upload(
                    $project,
                    $evidenceFile,
                    [
                        'title'    => 'UAT Sign-off Evidence',
                        'category' => ProjectDocument::CATEGORY_TEST_CASE,
                    ],
                    $actor,
                    $review
                );
            }

            $this->activityLogs->record(
                $project,
                'project.review_created',
                __('projects.review_created_title', [
                    'user'    => $actor->name,
                    'project' => $project->name,
                ]),
                $review->comments,
                $review,
                [
                    'review_id'   => $review->id,
                    'review_date' => $review->review_date?->toDateString(),
                    'status'      => $review->status,
                ]
            );

            event(new ProjectReviewRequested($review, $actor));

            return $review;
        });
    }

    public function signoff(
        ProjectReview $review,
        string $outcome,
        ?string $comments,
        ?string $signOffRef,
        User $actor,
        ?UploadedFile $evidenceFile = null
    ): ProjectReview {
        if (! $review->isPending()) {
            throw ValidationException::withMessages([
                'status' => [__('projects.review_not_pending')],
            ]);
        }

        if (! in_array($outcome, [ProjectReview::STATUS_APPROVED, ProjectReview::STATUS_REWORK_REQUIRED], true)) {
            throw ValidationException::withMessages([
                'status' => [__('projects.invalid_review_outcome')],
            ]);
        }

        return DB::transaction(function () use ($review, $outcome, $comments, $signOffRef, $actor, $evidenceFile) {
            $project = $review->project;

            $updated = $this->reviews->update($review->id, [
                'status'       => $outcome,
                'comments'     => $comments ?? $review->comments,
                'sign_off_ref' => $signOffRef ?? $review->sign_off_ref,
            ]);

            if ($evidenceFile !== null) {
                $this->documents->upload(
                    $project,
                    $evidenceFile,
                    [
                        'title'    => 'UAT Sign-off Evidence',
                        'category' => ProjectDocument::CATEGORY_TEST_CASE,
                    ],
                    $actor,
                    $updated
                );
            }

            $event = $outcome === ProjectReview::STATUS_APPROVED
                ? 'project.review_approved'
                : 'project.review_rework_required';

            $title = $outcome === ProjectReview::STATUS_APPROVED
                ? __('projects.review_approved_title', [
                    'user'    => $actor->name,
                    'project' => $project->name,
                ])
                : __('projects.review_rework_title', [
                    'user'    => $actor->name,
                    'project' => $project->name,
                ]);

            $this->activityLogs->record(
                $project,
                $event,
                $title,
                $updated->comments,
                $updated,
                [
                    'review_id'    => $updated->id,
                    'outcome'      => $outcome,
                    'sign_off_ref' => $updated->sign_off_ref,
                ]
            );

            event(new ProjectReviewSignedOff($updated, $actor));

            return $updated;
        });
    }

    public function delete(ProjectReview $review, User $actor): bool
    {
        return DB::transaction(function () use ($review, $actor) {
            $project = $review->project;

            $this->activityLogs->record(
                $project,
                'project.review_deleted',
                __('projects.review_deleted_title', [
                    'user'    => $actor->name,
                    'project' => $project->name,
                ]),
                null,
                $review
            );

            return $this->reviews->delete($review->id);
        });
    }
}
