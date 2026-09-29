<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use App\Models\User;

interface ProbationRepositoryInterface
{
    public function getIndexData(array $inputs, ?User $user, int $tenantId): array;

    public function evaluate(Employee $employee, array $validated, ?int $userId, int $tenantId): string;

    public function quickConfirm(Employee $employee, ?string $confirmationDate, ?string $remarks, ?int $userId, int $tenantId): string;
}
