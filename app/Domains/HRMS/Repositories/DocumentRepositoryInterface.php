<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;

interface DocumentRepositoryInterface
{
    public function getIndexData(array $inputs, ?User $user): array;

    public function storeDocument(array $validated, Request $request, ?User $user): Document;

    public function updateDocument(Document $document, array $validated, Request $request): bool;

    public function updateStatus(Document $document, string $status, ?string $reason, ?User $user): bool;

    public function deleteDocument(Document $document): bool;

    public function bulkApprove(array $ids, ?User $user): int;

    public function bulkReject(array $ids, ?string $reason, ?User $user): int;

    public function bulkDelete(array $ids): int;
}
