<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\EmployeeExitDocument;
use App\Domains\HRMS\Models\EmployeeFnfSettlement;
use App\Models\User;
use Illuminate\Http\Request;

interface EmployeeExitRepositoryInterface
{
    public function getIndexData(array $inputs, ?User $user, int $tenantId): array;

    public function getShowData(int $id, int $tenantId): array;

    public function storeExit(array $validated, ?int $userId, int $tenantId): EmployeeExit;

    public function approveManager(EmployeeExit $exit, array $validated, ?int $userId): bool;

    public function approveHr(EmployeeExit $exit, array $validated, ?int $userId, int $tenantId): bool;

    public function rejectExit(EmployeeExit $exit, ?string $reason): bool;

    public function clearItem(EmployeeExitClearance $item, array $validated, ?int $userId): bool;

    public function recalculateFnf(EmployeeExit $exit): array;

    public function saveFnf(EmployeeExit $exit, array $validated): EmployeeFnfSettlement;

    public function approveFnf(EmployeeFnfSettlement $settlement, ?int $userId): bool;

    public function settleFnf(EmployeeFnfSettlement $settlement, array $validated, ?int $userId): bool;

    public function generateDoc(EmployeeExit $exit, string $docType, int $tenantId): EmployeeExitDocument;
}
