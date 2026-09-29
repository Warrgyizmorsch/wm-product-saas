<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeProbationEvaluation;
use App\Domains\HRMS\Services\ExitClearanceService;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use Carbon\Carbon;

class ProbationRepository implements ProbationRepositoryInterface
{
    public function __construct(
        private readonly HrmsScopeService $scopeService,
        private readonly ?FnFCalculationService $fnfService = null
    ) {}

    public function getIndexData(array $inputs, ?User $user, int $tenantId): array
    {
        $today = Carbon::today();
        $in15Days = Carbon::today()->addDays(15);

        $baseQuery = Employee::query()->where('tenant_id', $tenantId);
        $this->scopeService->applyEmployeeScope($baseQuery, $user);

        $totalInProbation = (clone $baseQuery)->where('employee_stage', 'Probation')->count();
        $dueSoonCount = (clone $baseQuery)->where('employee_stage', 'Probation')
            ->whereBetween('probation_end_date', [$today->format('Y-m-d'), $in15Days->format('Y-m-d')])
            ->count();
        $overdueCount = (clone $baseQuery)->where('employee_stage', 'Probation')
            ->where('probation_end_date', '<', $today->format('Y-m-d'))
            ->count();
        $confirmedThisMonthCount = (clone $baseQuery)->where('employee_stage', 'Confirmed')
            ->whereMonth('confirmation_date', $today->month)
            ->whereYear('confirmation_date', $today->year)
            ->count();

        $filterStatus = $inputs['status'] ?? 'in_probation';
        $search = $inputs['search'] ?? null;
        $departmentId = $inputs['department_id'] ?? null;

        $query = Employee::query()->where('tenant_id', $tenantId);
        $this->scopeService->applyEmployeeScope($query, $user);
        $query->with(['department', 'designation', 'reportingManager', 'probationEvaluations.reviewer']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('personal_email', 'like', "%{$search}%");
            });
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($filterStatus === 'in_probation') {
            $query->where('employee_stage', 'Probation');
        } elseif ($filterStatus === 'due_soon') {
            $query->where('employee_stage', 'Probation')
                  ->whereBetween('probation_end_date', [$today->format('Y-m-d'), $in15Days->format('Y-m-d')]);
        } elseif ($filterStatus === 'overdue') {
            $query->where('employee_stage', 'Probation')
                  ->where('probation_end_date', '<', $today->format('Y-m-d'));
        } elseif ($filterStatus === 'confirmed') {
            $query->where('employee_stage', 'Confirmed');
        }

        $evalStatus = $inputs['eval_status'] ?? null;
        if ($evalStatus === 'reviewed') {
            $query->has('probationEvaluations');
        } elseif ($evalStatus === 'unreviewed') {
            $query->doesntHave('probationEvaluations');
        }

        $sortBy = $inputs['sort_by'] ?? 'probation_end_date';
        $sortOrder = $inputs['sort_order'] ?? 'asc';
        $validSortColumns = ['probation_end_date', 'full_name', 'date_of_joining', 'employee_id'];
        if (in_array($sortBy, $validSortColumns)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('probation_end_date', 'asc');
        }

        $employees = $query->paginate(15)->withQueryString();
        $departments = Department::where('status', true)->orderBy('name')->get();

        return compact(
            'employees',
            'departments',
            'totalInProbation',
            'dueSoonCount',
            'overdueCount',
            'confirmedThisMonthCount',
            'filterStatus',
            'search',
            'departmentId',
            'sortBy',
            'sortOrder',
            'evalStatus'
        );
    }

    public function evaluate(Employee $employee, array $validated, ?int $userId, int $tenantId): string
    {
        $newProbationEnd = null;

        if ($validated['recommendation'] === 'extend') {
            $currentEnd = $employee->probation_end_date ? Carbon::parse($employee->probation_end_date) : Carbon::today();
            $newProbationEnd = $currentEnd->copy()->addDays((int) $validated['extension_days']);
        }

        EmployeeProbationEvaluation::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'reviewer_id' => $userId,
            'evaluation_date' => Carbon::today()->format('Y-m-d'),
            'performance_rating' => $validated['performance_rating'],
            'attendance_rating' => $validated['attendance_rating'],
            'culture_rating' => $validated['culture_rating'],
            'recommendation' => $validated['recommendation'],
            'extension_days' => $validated['extension_days'] ?? null,
            'new_probation_end_date' => $newProbationEnd ? $newProbationEnd->format('Y-m-d') : null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'completed',
        ]);

        if ($validated['recommendation'] === 'confirm') {
            $employee->update([
                'employee_stage' => 'Confirmed',
                'confirmation_date' => Carbon::today()->format('Y-m-d'),
            ]);
            return "Employee {$employee->full_name} has been formally evaluated and confirmed.";
        } elseif ($validated['recommendation'] === 'extend') {
            $employee->update([
                'probation_end_date' => $newProbationEnd->format('Y-m-d'),
            ]);
            return "Probation period for {$employee->full_name} extended by {$validated['extension_days']} days (New End Date: " . $newProbationEnd->format('d M, Y') . ").";
        } else {
            $mode = $validated['termination_mode'] ?? 'notice';
            $noticeDays = ($mode === 'immediate') ? 0 : (int) ($validated['termination_notice_days'] ?? 15);
            $lwd = Carbon::today()->addDays($noticeDays);
            $reasonCat = $validated['termination_reason_category'] ?? 'Probation Unsuccessful';

            $exit = EmployeeExit::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'status' => 'in_clearance',
                ],
                [
                    'separation_type' => 'termination',
                    'resignation_date' => Carbon::today()->format('Y-m-d'),
                    'preferred_lwd' => $lwd->format('Y-m-d'),
                    'approved_lwd' => $lwd->format('Y-m-d'),
                    'notice_period_days' => $noticeDays,
                    'notice_shortfall_days' => 0,
                    'notice_action' => ($mode === 'immediate') ? 'waive' : 'serve',
                    'reason_category' => $reasonCat,
                    'reason_details' => $validated['remarks'] ?? 'Involuntary separation initiated following unsuccessful probation evaluation.',
                    'initiated_by' => 'employer',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                ]
            );

            app(ExitClearanceService::class)->generateClearancesForExit($exit, $tenantId);

            $fnfService = $this->fnfService ?? app(FnFCalculationService::class);
            $computedFnF = $fnfService->calculateFnF($exit);
            $fnfService->saveSettlement($exit, $computedFnF);

            $employee->update(['employee_stage' => 'Notice Period']);

            return "Probation review completed. Involuntary separation initiated for {$employee->full_name}. Last Working Day is set to " . $lwd->format('d M, Y') . " (" . ($mode === 'immediate' ? 'Immediate' : "{$noticeDays} Days Notice") . "). Exit case & clearance checklists created in Offboarding Hub.";
        }
    }

    public function quickConfirm(Employee $employee, ?string $confirmationDate, ?string $remarks, ?int $userId, int $tenantId): string
    {
        $confDate = $confirmationDate 
            ? Carbon::parse($confirmationDate)->format('Y-m-d') 
            : Carbon::today()->format('Y-m-d');
        $remarkText = $remarks ?: 'Directly confirmed from probation review.';

        EmployeeProbationEvaluation::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'reviewer_id' => $userId,
            'evaluation_date' => Carbon::today()->format('Y-m-d'),
            'performance_rating' => 4,
            'attendance_rating' => 4,
            'culture_rating' => 4,
            'recommendation' => 'confirm',
            'remarks' => $remarkText,
            'status' => 'completed',
        ]);

        $employee->update([
            'employee_stage' => 'Confirmed',
            'confirmation_date' => $confDate,
        ]);

        return "Employee {$employee->full_name} confirmed successfully.";
    }
}
