<?php

namespace App\Domains\Projects\Policies;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Models\User;
use App\Services\Access\AccessService;

class ProjectDocumentPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user, Project $project): bool
    {
        return $this->authorizeOnProject($user, $project, 'projects.documents.view');
    }

    public function create(User $user, Project $project): bool
    {
        return $this->authorizeOnProject($user, $project, 'projects.documents.upload');
    }

    public function view(User $user, ProjectDocument $document): bool
    {
        if ($this->access->allows($user, 'projects.documents.view', [
            'tenant_id' => $document->tenant_id,
            'owner_id' => $document->uploaded_by,
        ])) {
            return true;
        }

        return $this->authorizeOnProject($user, $document->project, 'projects.documents.view');
    }

    public function delete(User $user, ProjectDocument $document): bool
    {
        if ($this->access->allows($user, 'projects.documents.delete', [
            'tenant_id' => $document->tenant_id,
            'owner_id' => $document->uploaded_by,
        ])) {
            return true;
        }

        return $this->authorizeOnProject($user, $document->project, 'projects.documents.delete');
    }

    private function authorizeOnProject(User $user, Project $project, string $permission): bool
    {
        return $this->access->allows($user, $permission, [
            'tenant_id' => $project->tenant_id,
            'owner_id' => $project->owner_id,
        ]);
    }
}
