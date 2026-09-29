<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\EmployeeProfileUpdateRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EmployeeProfileRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function approveRequest(EmployeeProfileUpdateRequest $profileRequest): bool;

    public function rejectRequest(EmployeeProfileUpdateRequest $profileRequest, string $reason): bool;
}
