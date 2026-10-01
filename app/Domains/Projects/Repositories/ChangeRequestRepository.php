<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ChangeRequestRepository implements ChangeRequestRepositoryInterface
{
    public function getForProject(int $projectId, array $filters = []): Collection
    {
        $query = ChangeRequest::with(['requester', 'approver', 'creator', 'review'])
            ->where('project_id', $projectId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('cr_number', 'like', $term)
                  ->orWhere('title', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        return $query->latest('id')->get();
    }

    public function find(int $id): ?ChangeRequest
    {
        return ChangeRequest::with(['project', 'requester', 'approver', 'creator', 'review'])->find($id);
    }

    public function nextCrNumber(Project $project): string
    {
        $lastCr = ChangeRequest::withTrashed()
            ->where('project_id', $project->id)
            ->latest('id')
            ->first();

        $nextSeq = 1;
        if ($lastCr && preg_match('/CR-(\d+)$/', $lastCr->cr_number, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-CR-%03d', $project->project_code, $nextSeq);
    }

    public function create(array $data): ChangeRequest
    {
        return ChangeRequest::create($data);
    }

    public function update(int $id, array $data): ChangeRequest
    {
        $cr = ChangeRequest::findOrFail($id);
        $cr->update($data);

        return $cr->fresh(['requester', 'approver', 'creator', 'review']);
    }

    public function delete(int $id): bool
    {
        $cr = ChangeRequest::find($id);
        if ($cr) {
            return (bool) $cr->delete();
        }

        return false;
    }
}
