<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Broadcast;
use App\Domains\HRMS\Models\BroadcastComment;
use App\Domains\HRMS\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;

interface BroadcastRepositoryInterface
{
    public function getIndexData(array $inputs, bool $isHrAdmin, ?Employee $employee, int $tenantId): array;

    public function getShowData(int $id, bool $isHrAdmin, ?Employee $employee, int $tenantId): array;

    public function storeBroadcast(array $validated, Request $request, int $tenantId): Broadcast;

    public function updateBroadcast(int $id, array $validated, Request $request, int $tenantId): Broadcast;

    public function deleteBroadcast(int $id, int $tenantId): bool;

    public function acknowledge(int $id, ?Employee $employee, ?string $signatureData, int $tenantId): bool;

    public function storeComment(int $id, array $validated, ?User $user, ?Employee $employee, int $tenantId): BroadcastComment;

    public function deleteComment(int $commentId, ?User $user, bool $isHrAdmin, int $tenantId): bool;
}
