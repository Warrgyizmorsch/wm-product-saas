<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ExpenseClaim;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\TravelRequest;
use App\Models\User;
use Illuminate\Http\Request;

interface TravelExpenseRepositoryInterface
{
    public function getIndexData(array $inputs, ?User $user, ?int $tenantId): array;

    public function storeTravelRequest(array $validated, ?int $tenantId): TravelRequest;

    public function updateTravelStatus(TravelRequest $travelRequest, array $validated, ?User $user): bool;

    public function storeCashAdvance(array $validated, ?int $tenantId): CashAdvance;

    public function updateAdvanceStatus(CashAdvance $cashAdvance, array $validated, ?User $user): bool;

    public function storeExpenseReport(array $validated, ?int $tenantId): ExpenseReport;

    public function getReportShowData(int $id, ?User $user, ?int $tenantId): array;

    public function updateReportStatus(ExpenseReport $report, array $validated, ?User $user): bool;

    public function addClaim(ExpenseReport $report, array $validated, Request $request, ?int $tenantId): ExpenseClaim;

    public function deleteClaim(ExpenseClaim $claim): bool;

    public function settleReport(ExpenseReport $report, array $validated, ?User $user): bool;

    public function getPolicyIndexData(array $inputs, ?int $tenantId): array;
}
