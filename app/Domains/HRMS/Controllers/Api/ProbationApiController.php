<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeProbationEvaluation;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProbationApiController extends Controller
{
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $statusCode);
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? auth()->user()->tenant_id;
        $today = Carbon::today();
        $in15Days = Carbon::today()->addDays(15);

        $status = $request->input('status', 'in_probation');
        $query = Employee::query()->where('tenant_id', $tenantId)->with(['department', 'designation', 'probationEvaluations']);

        if ($status === 'in_probation') {
            $query->where('employee_stage', 'Probation');
        } elseif ($status === 'due_soon') {
            $query->where('employee_stage', 'Probation')
                  ->whereBetween('probation_end_date', [$today->format('Y-m-d'), $in15Days->format('Y-m-d')]);
        } elseif ($status === 'overdue') {
            $query->where('employee_stage', 'Probation')
                  ->where('probation_end_date', '<', $today->format('Y-m-d'));
        }

        $employees = $query->paginate($request->integer('per_page', 15));

        return $this->sendSuccess($employees, 'Probation list retrieved successfully');
    }

    public function evaluate(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'performance_rating' => 'required|integer|min:1|max:5',
            'attendance_rating' => 'required|integer|min:1|max:5',
            'culture_rating' => 'required|integer|min:1|max:5',
            'recommendation' => 'required|string|in:confirm,extend,terminate',
            'extension_days' => 'nullable|required_if:recommendation,extend|integer|min:1|max:180',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $tenantId = tenant_id() ?? auth()->user()->tenant_id;
        $newProbationEnd = null;

        if ($validated['recommendation'] === 'extend') {
            $currentEnd = $employee->probation_end_date ? Carbon::parse($employee->probation_end_date) : Carbon::today();
            $newProbationEnd = $currentEnd->copy()->addDays((int) $validated['extension_days']);
        }

        $eval = EmployeeProbationEvaluation::create([
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
        } elseif ($validated['recommendation'] === 'extend') {
            $employee->update([
                'probation_end_date' => $newProbationEnd->format('Y-m-d'),
            ]);
        } else {
            $mode = $request->input('termination_mode', 'notice');
            $noticeDays = ($mode === 'immediate') ? 0 : (int) ($request->input('termination_notice_days', 15));
            $lwd = Carbon::today()->addDays($noticeDays);
            $reasonCat = $request->input('termination_reason_category', 'Probation Unsuccessful');

            $exit = \App\Domains\HRMS\Models\EmployeeExit::updateOrCreate(
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
                    'reason_details' => $validated['remarks'] ?? 'Involuntary separation via probation evaluation API.',
                    'initiated_by' => 'employer',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]
            );

            if ($exit->clearances()->count() === 0) {
                $standardChecklist = [
                    ['department' => 'it', 'item_name' => 'Hardware Asset Recovery (Laptop/Accessories)'],
                    ['department' => 'it', 'item_name' => 'Email, Slack & ERP System Logins Deactivation'],
                    ['department' => 'it', 'item_name' => 'Cloud Data Backup & File Handover'],
                    ['department' => 'admin', 'item_name' => 'Company Physical ID Card & Access Badge Handover'],
                    ['department' => 'admin', 'item_name' => 'Office Keys, Drawer Keys & Parking Tag Handover'],
                    ['department' => 'finance', 'item_name' => 'Reconcile Open Cash Advances & Loan Accounts'],
                    ['department' => 'finance', 'item_name' => 'Verify Pending Travel & Expense Reimbursements'],
                    ['department' => 'finance', 'item_name' => 'Notice Period Shortfall / Buyout Verification'],
                    ['department' => 'hr', 'item_name' => 'Exit Interview & Feedback Questionnaire Completed'],
                    ['department' => 'hr', 'item_name' => 'PF, Gratuity & Pension Settlement Verification'],
                    ['department' => 'manager', 'item_name' => 'Knowledge Transfer (KT) & Task Handover Sign-off'],
                    ['department' => 'manager', 'item_name' => 'Client Contacts, Repo & Credentials Handover'],
                ];

                foreach ($standardChecklist as $item) {
                    \App\Domains\HRMS\Models\EmployeeExitClearance::create([
                        'tenant_id' => $tenantId,
                        'employee_exit_id' => $exit->id,
                        'department' => $item['department'],
                        'item_name' => $item['item_name'],
                        'status' => 'pending',
                    ]);
                }
            }

            $fnfService = app(\App\Domains\HRMS\Services\FnFCalculationService::class);
            $computedFnF = $fnfService->calculateFnF($exit);
            $fnfService->saveSettlement($exit, $computedFnF);

            $employee->update(['employee_stage' => 'Notice Period']);
        }

        return $this->sendSuccess($eval, 'Probation evaluation recorded successfully');
    }
}
