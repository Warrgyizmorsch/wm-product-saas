<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\ShiftChangeRequest;
use Illuminate\Http\Request;

interface ShiftChangeRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array;
    public function storeShiftChangeRequest(array $validated, Request $request): ShiftChangeRequest;
    public function updateStatus(ShiftChangeRequest $requestModel, array $validated, Request $request): bool;
}
