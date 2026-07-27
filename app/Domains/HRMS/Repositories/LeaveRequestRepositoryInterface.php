<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\LeaveRequest;
use Illuminate\Http\Request;

interface LeaveRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeLeaveRequest(array $validated, Request $request): LeaveRequest;

    public function updateStatus(LeaveRequest $leaveRequest, array $validated, Request $request): bool;

    public function getPolicyRules(int $employeeId, int $leaveTypeId): array;
}
