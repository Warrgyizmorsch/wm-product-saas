<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\OvertimeRequest;
use Illuminate\Http\Request;

interface OvertimeRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array;
    public function storeOvertimeRequest(array $validated, Request $request): OvertimeRequest;
    public function updateStatus(OvertimeRequest $requestModel, array $validated, Request $request): bool;
    public function updateGlobalSettings(array $settings): bool;
    public function processAutoOvertime(\App\Domains\HRMS\Models\Employee $employee, string $date, float $extraHours): ?OvertimeRequest;
}
