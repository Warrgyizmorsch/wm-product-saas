<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\EmployeeExitDocument;
use App\Domains\HRMS\Models\EmployeeFnfSettlement;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\ExitClearanceTemplate;
use App\Domains\HRMS\Models\PayrollHold;
use App\Domains\HRMS\Services\ExitClearanceService;
use App\Domains\HRMS\Services\ExitDocumentationService;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use App\Services\Notification\NotificationService;
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
            $exitsQuery->where(function($masterQ) use ($search) {
                $masterQ->whereHas('employee', function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('employee_id', 'like', "%{$search}%")
                      ->orWhere('personal_email', 'like', "%{$search}%")
                      ->orWhere('office_email', 'like', "%{$search}%");
                })
                ->orWhere('separation_type', 'like', "%{$search}%")
                ->orWhere('reason_category', 'like', "%{$search}%")
                ->orWhere('reason_details', 'like', "%{$search}%");
            });
        }

        if ($statusFilter) {
            $exitsQuery->where('employee_exits.status', $statusFilter);
        }

        $departmentId = $inputs['department_id'] ?? null;
        $selectedCompanyId = $inputs['company_id'] ?? null;
        $sortBy = $inputs['sort_by'] ?? 'created_at';
        $sortOrder = in_array(strtolower($inputs['sort_order'] ?? 'desc'), ['asc', 'desc']) ? strtolower($inputs['sort_order'] ?? 'desc') : 'desc';

        if ($departmentId) {
            $exitsQuery->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        }

        if ($selectedCompanyId) {
            $exitsQuery->whereHas('employee', fn($q) => $q->where('company_id', $selectedCompanyId));
        }

        if ($sortBy === 'lwd') {
            $exitsQuery->orderBy(DB::raw('COALESCE(employee_exits.approved_lwd, employee_exits.preferred_lwd, employee_exits.resignation_date)'), $sortOrder);
        } elseif ($sortBy === 'employee') {
            $exitsQuery->join('employees', 'employees.id', '=', 'employee_exits.employee_id')
                ->orderBy('employees.full_name', $sortOrder)
                ->select('employee_exits.*');
        } elseif (in_array($sortBy, ['created_at', 'resignation_date', 'approved_lwd', 'status'])) {
            $exitsQuery->orderBy('employee_exits.' . $sortBy, $sortOrder);
        } else {
            $exitsQuery->orderBy('employee_exits.created_at', 'desc');
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

        // Dispatch notifications on Exit creation
        try {
            if ($validated['initiated_by'] === 'employee') {
                $employee->loadMissing('reportingManager');
                if ($employee->reportingManager) {
                    NotificationService::sendToEmployee(
                        $employee->reportingManager,
                        'Resignation Request Submitted',
                        "{$employee->full_name} has submitted a resignation request with preferred LWD: {$exit->preferred_lwd}.",
                        route('hrms.exits.index'),
                        'hrms',
                        'resignation_submitted',
                        'feather-user-minus'
                    );
                }

                NotificationService::sendToHrAdmins(
                    'New Resignation Request',
                    "{$employee->full_name} has submitted a resignation request (Preferred LWD: {$exit->preferred_lwd}).",
                    route('hrms.exits.index'),
                    'resignation_hr_alert',
                    'feather-user-minus'
                );
            } else {
                NotificationService::sendToEmployee(
                    $employee,
                    'Offboarding Clearance Initiated',
                    "Your exit and offboarding clearance workflow has been initiated.",
                    route('hrms.exits.index'),
                    'hrms',
                    'exit_initiated',
                    'feather-user-x'
                );
            }
        } catch (\Throwable $e) {
            // Suppress notification errors
        }

        return $exit;
    }

    public function approveManager(EmployeeExit $exit, array $validated, ?int $userId): bool
    {
        $updated = $exit->update([
            'status' => 'pending_hr',
            'manager_recommended_lwd' => $validated['manager_recommended_lwd'] ?? $exit->preferred_lwd,
            'manager_remarks' => $validated['manager_remarks'] ?? null,
            'approved_by' => $userId,
        ]);

        try {
            $exit->loadMissing('employee');
            if ($exit->employee) {
                NotificationService::sendToEmployee(
                    $exit->employee,
                    'Resignation Approved by Manager',
                    "Your manager has approved your resignation request. It is now awaiting HR approval.",
                    route('hrms.exits.index'),
                    'hrms',
                    'resignation_manager_approved',
                    'feather-check-circle'
                );
            }

            NotificationService::sendToHrAdmins(
                'Resignation Manager Approved',
                "Manager approval completed for {$exit->employee?->full_name}'s resignation (Recommended LWD: {$exit->manager_recommended_lwd}).",
                route('hrms.exits.index'),
                'resignation_hr_pending',
                'feather-clock'
            );
        } catch (\Throwable $e) {
            // Suppress notification errors
        }

        return $updated;
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

        try {
            $exit->loadMissing('employee');
            if ($exit->employee) {
                NotificationService::sendToEmployee(
                    $exit->employee,
                    'Resignation Approved by HR — Clearance Active',
                    "Your resignation is officially approved with Approved LWD: {$exit->approved_lwd}. Department clearance items are now active.",
                    route('hrms.exits.index'),
                    'hrms',
                    'resignation_hr_approved',
                    'feather-check-circle'
                );
            }

            NotificationService::sendToHrAdmins(
                'Exit Clearance In-Progress',
                "Departmental clearances active for {$exit->employee?->full_name} (Approved LWD: {$exit->approved_lwd}).",
                route('hrms.exits.index'),
                'clearance_in_progress',
                'feather-clipboard'
            );
        } catch (\Throwable $e) {
            // Suppress notification errors
        }

        return true;
    }

    public function rejectExit(EmployeeExit $exit, ?string $reason): bool
    {
        $rejected = $exit->update([
            'status' => 'rejected',
            'hr_remarks' => $reason,
        ]);

        try {
            $exit->loadMissing('employee');
            if ($exit->employee) {
                NotificationService::sendToEmployee(
                    $exit->employee,
                    'Resignation Request Rejected',
                    "Your resignation request has been rejected. Reason: " . ($reason ?: 'Not specified'),
                    route('hrms.exits.index'),
                    'hrms',
                    'resignation_rejected',
                    'feather-x-circle'
                );
            }
        } catch (\Throwable $e) {
            // Suppress notification errors
        }

        return $rejected;
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
        $exit = $settlement->employeeExit;

        DB::transaction(function () use ($settlement, $validated, $exit) {
            $settlement->update([
                'status' => 'paid',
                'payment_mode' => $validated['payment_mode'],
                'payment_reference' => $validated['payment_reference'] ?? null,
                'paid_at' => $validated['paid_at'] ?? now(),
            ]);

            $exit->update(['status' => 'settled']);

            $exit->employee->update([
                'status' => false,
                'employee_stage' => 'Relieved',
                'date_of_exit' => $exit->approved_lwd ?? $exit->preferred_lwd ?? now()->format('Y-m-d'),
            ]);

            // Dependent Module Reconciliations
            // 1. Mark open cash advances as settled
            CashAdvance::where('employee_id', $exit->employee_id)
                ->whereIn('status', ['approved', 'disbursed'])
                ->update(['status' => 'settled']);

            // 2. Mark approved expense reports as paid/settled
            ExpenseReport::where('employee_id', $exit->employee_id)
                ->where('status', 'approved')
                ->update(['status' => 'paid']);

            // 3. Release any active payroll holds
            PayrollHold::where('employee_id', $exit->employee_id)
                ->where('status', 'on_hold')
                ->update(['status' => 'released']);
        });

        try {
            $exit->loadMissing('employee');
            if ($exit->employee) {
                NotificationService::sendToEmployee(
                    $exit->employee,
                    'Full & Final Settlement Settled',
                    "Your Full & Final Settlement has been processed and paid via {$validated['payment_mode']}. Exit certificates are now generated.",
                    route('hrms.exits.index'),
                    'hrms',
                    'fnf_settled',
                    'feather-award'
                );
            }

            NotificationService::sendToHrAdmins(
                'Employee Relieved & FnF Settled',
                "FnF settlement completed and employee {$exit->employee?->full_name} marked Relieved.",
                route('hrms.exits.index'),
                'fnf_completed_hr',
                'feather-check-circle'
            );
        } catch (\Throwable $e) {
            // Suppress notification errors
        }

        return true;
    }

    public function generateDoc(EmployeeExit $exit, string $docType, int $tenantId): EmployeeExitDocument
    {
        return $this->docService->generateExitDocument($exit, $docType, $tenantId);
    }
}
