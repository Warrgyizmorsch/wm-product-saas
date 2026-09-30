<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\LeaveEncashment;
use App\Models\User;
use Illuminate\Http\Request;

interface LeaveEncashmentRepositoryInterface
{
    public function storeEncashment(array $validated, Request $request, ?User $user): array;

    public function approve(LeaveEncashment $leaveEncashment, Request $request, ?User $user): array;

    public function reject(LeaveEncashment $leaveEncashment, ?string $reason, ?User $user): array;

    public function delete(LeaveEncashment $leaveEncashment): bool;

    public function getExportData(array $inputs, ?User $user, int $tenantId): array;
}
