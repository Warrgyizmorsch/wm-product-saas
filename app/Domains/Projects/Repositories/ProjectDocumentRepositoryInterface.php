<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\ProjectDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ProjectDocumentRepositoryInterface
{
    public function getForProject(int $projectId, array $filters = []): Collection;

    public function getForAttachable(Model $attachable): Collection;

    public function find(int $id): ?ProjectDocument;

    public function create(array $data): ProjectDocument;

    public function delete(int $id): bool;
}
