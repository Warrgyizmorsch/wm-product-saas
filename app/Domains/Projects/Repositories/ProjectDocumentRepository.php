<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ProjectDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProjectDocumentRepository implements ProjectDocumentRepositoryInterface
{
    public function getForProject(int $projectId, array $filters = []): Collection
    {
        $query = ProjectDocument::with(['uploader', 'attachable'])
            ->where('project_id', $projectId);

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('file_name', 'like', $term)
                  ->orWhere('remarks', 'like', $term);
            });
        }

        return $query->latest('id')->get();
    }

    public function getForAttachable(Model $attachable): Collection
    {
        return ProjectDocument::with('uploader')
            ->where('attachable_type', $attachable->getMorphClass())
            ->where('attachable_id', $attachable->getKey())
            ->latest('id')
            ->get();
    }

    public function find(int $id): ?ProjectDocument
    {
        return ProjectDocument::with(['project', 'uploader', 'attachable'])->find($id);
    }

    public function create(array $data): ProjectDocument
    {
        return ProjectDocument::create($data);
    }

    public function delete(int $id): bool
    {
        $doc = ProjectDocument::findOrFail($id);
        return (bool) $doc->delete();
    }
}
