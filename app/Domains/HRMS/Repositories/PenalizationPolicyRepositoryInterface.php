<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\AttendancePenalty;
use App\Domains\HRMS\Models\AttendanceRule;
use Illuminate\Http\Request;

interface PenalizationPolicyRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeRule(array $validated): AttendancePenalty;

    public function queryAttendanceRule(array $inputs): ?AttendanceRule;

    public function saveAttendanceRule(array $validated): AttendanceRule;
}
