<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeProbationEvaluation;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProbationController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $today = Carbon::today();
        $in15Days = Carbon::today()->addDays(15);

        // 1. Compute Stats
        $baseQuery = Employee::query()->where('tenant_id', $tenantId);

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

        // 2. Filter & Query
        $filterStatus = $request->input('status', 'in_probation');
        $search = $request->input('search');
        $departmentId = $request->input('department_id');

        $query = Employee::query()
            ->where('tenant_id', $tenantId)
            ->with(['department', 'designation', 'reportingManager', 'probationEvaluations.reviewer']);

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

        // Evaluation Status Filter
        $evalStatus = $request->input('eval_status');
        if ($evalStatus === 'reviewed') {
            $query->has('probationEvaluations');
        } elseif ($evalStatus === 'unreviewed') {
            $query->doesntHave('probationEvaluations');
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'probation_end_date');
        $sortOrder = $request->input('sort_order', 'asc');
        $validSortColumns = ['probation_end_date', 'full_name', 'date_of_joining', 'employee_id'];
        if (in_array($sortBy, $validSortColumns)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('probation_end_date', 'asc');
        }

        $employees = $query->paginate(15)->withQueryString();
        $departments = Department::where('status', true)->orderBy('name')->get();

        return view('modules.hrms.employees.probation.index', compact(
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
        ));
    }

    public function __construct(
        private readonly ?FnFCalculationService $fnfService = null
    ) {}

    public function evaluate(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'performance_rating' => 'required|integer|min:1|max:5',
            'attendance_rating' => 'required|integer|min:1|max:5',
            'culture_rating' => 'required|integer|min:1|max:5',
            'recommendation' => 'required|string|in:confirm,extend,terminate',
            'extension_days' => 'nullable|required_if:recommendation,extend|integer|min:1|max:180',
            'termination_mode' => 'nullable|required_if:recommendation,terminate|string|in:immediate,notice',
            'termination_notice_days' => 'nullable|integer|min:0|max:90',
            'termination_reason_category' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $newProbationEnd = null;

        if ($validated['recommendation'] === 'extend') {
            $currentEnd = $employee->probation_end_date ? Carbon::parse($employee->probation_end_date) : Carbon::today();
            $newProbationEnd = $currentEnd->copy()->addDays((int) $validated['extension_days']);
        }

        EmployeeProbationEvaluation::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'reviewer_id' => auth()->id(),
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
            $msg = "Employee {$employee->full_name} has been formally evaluated and confirmed.";
        } elseif ($validated['recommendation'] === 'extend') {
            $employee->update([
                'probation_end_date' => $newProbationEnd->format('Y-m-d'),
            ]);
            $msg = "Probation period for {$employee->full_name} extended by {$validated['extension_days']} days (New End Date: " . $newProbationEnd->format('d M, Y') . ").";
        } else {
            // Recommendation = TERMINATE
            $mode = $validated['termination_mode'] ?? 'notice';
            $noticeDays = ($mode === 'immediate') ? 0 : (int) ($validated['termination_notice_days'] ?? 15);
            $lwd = Carbon::today()->addDays($noticeDays);
            $reasonCat = $validated['termination_reason_category'] ?? 'Probation Unsuccessful';

            // Auto-create or update exit record
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
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]
            );

            // Auto-generate dynamic clearance items from company/tenant templates
            app(\App\Domains\HRMS\Services\ExitClearanceService::class)->generateClearancesForExit($exit, $tenantId);

            // Generate initial FnF settlement draft
            $fnfService = $this->fnfService ?? app(FnFCalculationService::class);
            $computedFnF = $fnfService->calculateFnF($exit);
            $fnfService->saveSettlement($exit, $computedFnF);

            // Update employee stage
            $employee->update(['employee_stage' => 'Notice Period']);

            $msg = "Probation review completed. Involuntary separation initiated for {$employee->full_name}. Last Working Day is set to " . $lwd->format('d M, Y') . " (" . ($mode === 'immediate' ? 'Immediate' : "{$noticeDays} Days Notice") . "). Exit case & clearance checklists created in Offboarding Hub.";
        }

        return redirect()->back()->with('success', $msg);
    }

    public function quickConfirm(Request $request, Employee $employee): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $confirmationDate = $request->filled('confirmation_date') 
            ? Carbon::parse($request->input('confirmation_date'))->format('Y-m-d') 
            : Carbon::today()->format('Y-m-d');
        $remarks = $request->input('remarks') ?: 'Directly confirmed from probation review.';

        EmployeeProbationEvaluation::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'reviewer_id' => auth()->id(),
            'evaluation_date' => Carbon::today()->format('Y-m-d'),
            'performance_rating' => 4,
            'attendance_rating' => 4,
            'culture_rating' => 4,
            'recommendation' => 'confirm',
            'remarks' => $remarks,
            'status' => 'completed',
        ]);

        $employee->update([
            'employee_stage' => 'Confirmed',
            'confirmation_date' => $confirmationDate,
        ]);

        return redirect()->back()->with('success', "Employee {$employee->full_name} confirmed successfully.");
    }
}
