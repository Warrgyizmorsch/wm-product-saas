<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ProjectReview;
use Illuminate\Database\Eloquent\Collection;

class ProjectReviewRepository implements ProjectReviewRepositoryInterface
{
    public function getForProject(int $projectId, array $filters = []): Collection
    {
        $query = ProjectReview::with(['reviewer', 'creator', 'documents'])
            ->where('project_id', $projectId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest('review_date')->latest('id')->get();
    }

    public function find(int $id): ?ProjectReview
    {
        return ProjectReview::with(['project', 'reviewer', 'creator', 'documents', 'changeRequests'])->find($id);
    }

    public function hasPendingReview(int $projectId): bool
    {
        return ProjectReview::where('project_id', $projectId)
            ->where('status', ProjectReview::STATUS_PENDING)
            ->exists();
    }

    public function create(array $data): ProjectReview
    {
        return ProjectReview::create($data);
    }

    public function update(int $id, array $data): ProjectReview
    {
        $review = ProjectReview::findOrFail($id);
        $review->update($data);

        return $review->fresh(['reviewer', 'creator', 'documents']);
    }

    public function delete(int $id): bool
    {
        $review = ProjectReview::find($id);
        if ($review) {
            return (bool) $review->delete();
        }

        return false;
    }
}
