<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Employee;
use Illuminate\Http\Request;

interface EmployeeRepositoryInterface
{
    public function getDirectoryData(array $inputs): array;

    public function getProfileData(Employee $employee, array $inputs): array;

    public function storeEmployee(array $validated, Request $request): Employee;

    public function updateEmployee(Employee $employee, array $validated, Request $request): bool;

    public function deleteEmployee(Employee $employee): bool;

    public function getDropdownOptions(): array;
}
