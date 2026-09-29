<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\EmployeeExitDocument;
use App\Domains\HRMS\Models\EmployeeFnfSettlement;
use App\Domains\HRMS\Models\ExitClearanceTemplate;
use App\Domains\HRMS\Services\ExitClearanceService;
use App\Domains\HRMS\Services\ExitDocumentationService;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeExitRepository implements EmployeeExitRepositoryInterface
{
    public function __construct(
        private readonly FnFCalculationService $fnfService,
        private readonly ExitDocumentationService $docService,
        private readonly ExitClearanceService $clearanceService,
        private readonly HrmsScopeService $scopeService
    ) {}

    public function getIndexData(array $inputs, ?User $user, int $tenantId): array
    {
        $activeTab = $inputs['tab'] ?? 'exits';
        $search = $inputs['search'] ?? null;
        $statusFilter = $inputs['status'] ?? null;

        $baseQuery = EmployeeExit::query()->where('tenant_id', $tenantId);
        $this->scopeService->applyRelatedScope($baseQuery, $user);

        $activeExitsCount = (clone $baseQuery)->whereIn('status', ['pending_manager', 'pending_hr', 'approved', 'in_clearance'])->count();
        $inClearanceCount = (clone $baseQuery)->where('status', 'in_clearance')->count();
        $pendingFnfCount = (clone $baseQuery)->whereHas('fnfSettlement', fn($q) => $q->whereIn('status', ['draft', 'approved']))->count();
        $settledThisMonthCount = (clone $baseQuery)->where('status', 'settled')
            ->whereMonth('updated_at', Carbon::today()->month)
            ->whereYear('updated_at', Carbon::today()->year)
            ->count();
        $settledExitsCount = (clone $baseQuery)->where(function($q) {
            $q->where('status', 'settled')
              ->orWhereHas('fnfSettlement', fn($fq) => $fq->where('status', 'paid'));
        })->count();

        $exitsQuery = EmployeeExit::query()->where('tenant_id', $tenantId);
        $this->scopeService->applyRelatedScope($exitsQuery, $user);
        $exitsQuery->with([
            'employee.department',
            'employee.designation',
            'employee.company',
            'employee.reportingManager',
            'employee.assets',
            'clearances.clearedByUser',
            'fnfSettlement',
            'documents'
        ]);

        if ($activeTab === 'documents') {
            $exitsQuery->where(function($q) {
                $q->where('status', 'settled')
                  ->orWhereHas('fnfSettlement', fn($fq) => $fq->where('status', 'paid'));
            });
        }

        if ($search) {
            $exitsQuery->whereHas('employee', function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('personal_email', 'like', "%{$search}%");
            });
        }

        if ($statusFilter) {
            $exitsQuery->where('status', $statusFilter);
        }

        $departmentId = $inputs['department_id'] ?? null;
        $selectedCompanyId = $inputs['company_id'] ?? null;
        $sortBy = $inputs['sort_by'] ?? 'created_at';
        $sortOrder = $inputs['sort_order'] ?? 'desc';

        if ($departmentId) {
            $exitsQuery->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        }

        if ($selectedCompanyId) {
            $exitsQuery->whereHas('employee', fn($q) => $q->where('company_id', $selectedCompanyId));
        }

        $validSortColumns = ['created_at', 'resignation_date', 'approved_lwd', 'status'];
        if (in_array($sortBy, $validSortColumns)) {
            $exitsQuery->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $exitsQuery->orderBy('created_at', 'desc');
        }

        $exits = $exitsQuery->paginate(15)->appends($inputs);

        $employees = Employee::where('tenant_id', $tenantId)->where('status', true)->orderBy('full_name')->get();
        $companies = Company::where('tenant_id', $tenantId)->get();
        $departments = Department::where('status', true)->get();

        return compact(
            'exits',
            'employees',
            'companies',
            'departments',
            'activeExitsCount',
            'inClearanceCount',
            'pendingFnfCount',
            'settledThisMonthCount',
            'settledExitsCount',
            'activeTab',
            'search',
            'statusFilter',
            'departmentId',
            'selectedCompanyId',
            'sortBy',
            'sortOrder'
        );
    }

    public function getShowData(int $id, int $tenantId): array
    {
        $exit = EmployeeExit::with([
            'employee.department',
            'employee.designation',
            'employee.company',
            'employee.reportingManager',
            'employee.assets',
            'clearances.clearedByUser',
            'fnfSettlement.processedByUser',
            'documents.generatedByUser'
        ])->where('tenant_id', $tenantId)->findOrFail($id);

        $computedFnF = $this->fnfService->calculateFnF($exit);
        $departments = Department::where('status', true)->get();

        return compact('exit', 'computedFnF', 'departments');
    }

    public function storeExit(array $validated, ?int $userId, int $tenantId): EmployeeExit
    {
        $employee = Employee::findOrFail($validated['employee_id']);
        $validated['tenant_id'] = $tenantId;

        $noticeDays = (int) ($validated['notice_period_days'] ?? $employee->notice_period_days ?? 30);
        $resignationDate = Carbon::parse($validated['resignation_date']);
        $preferredLwd = $validated['preferred_lwd'] ? Carbon::parse($validated['preferred_lwd']) : $resignationDate->copy()->addDays($noticeDays);

        $validated['notice_period_days'] = $noticeDays;
        $validated['preferred_lwd'] = $preferredLwd->format('Y-m-d');
        $validated['status'] = $validated['initiated_by'] === 'employee' ? 'pending_manager' : 'in_clearance';

        if ($validated['initiated_by'] === 'employer') {
            $validated['approved_lwd'] = $validated['preferred_lwd'];
            $validated['approved_by'] = $userId;
            $validated['approved_at'] = now();
        }

        $exit = EmployeeExit::create($validated);

        if ($exit->status === 'in_clearance') {
            $this->clearanceService->generateClearancesForExit($exit, $tenantId);
            $computedFnF = $this->fnfService->calculateFnF($exit);
            $this->fnfService->saveSettlement($exit, $computedFnF);
            $employee->update(['employee_stage' => 'Notice Period']);
        }

        return $exit;
    }

    public function approveManager(EmployeeExit $exit, array $validated, ?int $userId): bool
    {
        return $exit->update([
            'status' => 'pending_hr',
            'manager_recommended_lwd' => $validated['manager_recommended_lwd'] ?? $exit->preferred_lwd,
            'manager_remarks' => $validated['manager_remarks'] ?? null,
            'approved_by' => $userId,
        ]);
    }

    public function approveHr(EmployeeExit $exit, array $validated, ?int $userId, int $tenantId): bool
    {
        $approvedLwd = Carbon::parse($validated['approved_lwd']);
        $resignationDate = Carbon::parse($exit->resignation_date);
        $servedDays = $resignationDate->diffInDays($approvedLwd);
        $noticeShortfall = max(0, $exit->notice_period_days - $servedDays);

        $exit->update([
            'status' => 'in_clearance',
            'approved_lwd' => $validated['approved_lwd'],
            'notice_shortfall_days' => $noticeShortfall,
            'notice_action' => $validated['notice_action'] ?? 'serve',
            'hr_remarks' => $validated['hr_remarks'] ?? null,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->clearanceService->generateClearancesForExit($exit, $tenantId);
        $computedFnF = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computedFnF);

        $exit->employee->update(['employee_stage' => 'Notice Period']);

        return true;
    }

    public function rejectExit(EmployeeExit $exit, ?string $reason): bool
    {
        return $exit->update([
            'status' => 'rejected',
            'hr_remarks' => $reason,
        ]);
    }

    public function clearItem(EmployeeExitClearance $item, array $validated, ?int $userId): bool
    {
        $item->update([
            'status' => $validated['status'],
            'recovery_amount' => $validated['recovery_amount'] ?? 0,
            'remarks' => $validated['remarks'] ?? null,
            'cleared_by' => $userId,
            'cleared_at' => $validated['status'] === 'cleared' ? now() : null,
        ]);

        $exit = $item->employeeExit;
        $this->clearanceService->checkAllClearancesCompleted($exit);

        $computedFnF = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computedFnF);

        return true;
    }

    public function recalculateFnf(EmployeeExit $exit): array
    {
        $computed = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computed);
        return $computed;
    }

    public function saveFnf(EmployeeExit $exit, array $validated): EmployeeFnfSettlement
    {
        return $this->fnfService->saveSettlement($exit, $validated);
    }

    public function approveFnf(EmployeeFnfSettlement $settlement, ?int $userId): bool
    {
        return $settlement->update([
            'status' => 'approved',
            'processed_by' => $userId,
            'processed_at' => now(),
        ]);
    }

    public function settleFnf(EmployeeFnfSettlement $settlement, array $validated, ?int $userId): bool
    {
        $settlement->update([
            'status' => 'paid',
            'payment_mode' => $validated['payment_mode'],
            'payment_reference' => $validated['payment_reference'] ?? null,
            'paid_at' => $validated['paid_at'] ?? now(),
        ]);

        $exit = $settlement->employeeExit;
        $exit->update(['status' => 'settled']);

        $exit->employee->update([
            'status' => false,
            'employee_stage' => 'Relieved',
            'date_of_exit' => $exit->approved_lwd ?? $exit->preferred_lwd ?? now()->format('Y-m-d'),
        ]);

        return true;
    }

    public function generateDoc(EmployeeExit $exit, string $docType, int $tenantId): EmployeeExitDocument
    {
        return $this->docService->generateExitDocument($exit, $docType, $tenantId);
    }
}
