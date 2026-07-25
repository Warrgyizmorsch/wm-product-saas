<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use Illuminate\Http\Request;

interface LeaveStructureRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storePlan(array $validated): LeavePlan;

    public function updatePlan(LeavePlan $leavePlan, array $validated): bool;

    public function destroyPlan(LeavePlan $leavePlan): bool;

    public function storeType(array $validated): LeaveType;

    public function updateType(LeaveType $leaveType, array $validated): bool;

    public function destroyType(LeaveType $leaveType): bool;
}
