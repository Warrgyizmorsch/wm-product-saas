<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeProbationEvaluation;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Services\FnFCalculationService;
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

    private function isHrAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->hasHrPermission('hr.settings.manage')
            || $user->hasHrPermission('hr.employees.manage')
            || $user->hasHrPermission('hrms.employees.manage');
    }

    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)
            ->where('tenant_id', tenant_id() ?? $user->tenant_id ?? 1)
            ->first()
            ?? Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $today = Carbon::today();
        $in15Days = Carbon::today()->addDays(15);
        $isHrAdmin = $this->isHrAdmin();
        $employee = $this->getAuthenticatedEmployee();

        $status = $request->input('status', 'in_probation');
        $query = Employee::query()->where('tenant_id', $tenantId)->with(['department', 'designation', 'probationEvaluations']);

        if (!$isHrAdmin) {
            if (!$employee) {
                return $this->sendError('Employee profile not found.', 404);
            }
            $query->where('id', $employee->id);
        }

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

        return $this->sendSuccess($employees, 'Probation list retrieved successfully.');
    }

    public function evaluate(Request $request, mixed $employee): JsonResponse
    {
        if (!$this->isHrAdmin()) {
            return $this->sendError('Unauthorized action. Admin permissions required to perform probation evaluation.', 403);
        }

        $empModel = Employee::find($employee);
        if (!$empModel) {
            return $this->sendError("Employee with ID '{$employee}' not found.", 404);
        }

        $validated = $request->validate([
            'performance_rating' => 'required|integer|min:1|max:5',
            'attendance_rating'  => 'required|integer|min:1|max:5',
            'culture_rating'     => 'required|integer|min:1|max:5',
            'recommendation'     => 'required|string|in:confirm,extend,terminate',
            'extension_days'     => 'nullable|required_if:recommendation,extend|integer|min:1|max:180',
            'remarks'            => 'nullable|string|max:1000',
        ]);

        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $newProbationEnd = null;

        if ($validated['recommendation'] === 'extend') {
            $currentEnd = $empModel->probation_end_date ? Carbon::parse($empModel->probation_end_date) : Carbon::today();
            $newProbationEnd = $currentEnd->copy()->addDays((int) $validated['extension_days']);
        }

        $eval = EmployeeProbationEvaluation::create([
            'tenant_id'              => $tenantId,
            'employee_id'            => $empModel->id,
            'reviewer_id'            => auth()->id(),
            'evaluation_date'        => Carbon::today()->format('Y-m-d'),
            'performance_rating'     => $validated['performance_rating'],
            'attendance_rating'      => $validated['attendance_rating'],
            'culture_rating'         => $validated['culture_rating'],
            'recommendation'         => $validated['recommendation'],
            'extension_days'         => $validated['extension_days'] ?? null,
            'new_probation_end_date' => $newProbationEnd ? $newProbationEnd->format('Y-m-d') : null,
            'remarks'                => $validated['remarks'] ?? null,
            'status'                 => 'completed',
        ]);

        if ($validated['recommendation'] === 'confirm') {
            $empModel->update([
                'employee_stage'    => 'Confirmed',
                'confirmation_date' => Carbon::today()->format('Y-m-d'),
            ]);
        } elseif ($validated['recommendation'] === 'extend') {
            $empModel->update([
                'probation_end_date' => $newProbationEnd->format('Y-m-d'),
            ]);
        } else {
            $mode = $request->input('termination_mode', 'notice');
            $noticeDays = ($mode === 'immediate') ? 0 : (int) ($request->input('termination_notice_days', 15));
            $lwd = Carbon::today()->addDays($noticeDays);
            $reasonCat = $request->input('termination_reason_category', 'Probation Unsuccessful');

            $exit = EmployeeExit::updateOrCreate(
                [
                    'tenant_id'   => $tenantId,
                    'employee_id' => $empModel->id,
                    'status'      => 'in_clearance',
                ],
                [
                    'separation_type'       => 'termination',
                    'resignation_date'      => Carbon::today()->format('Y-m-d'),
                    'preferred_lwd'         => $lwd->format('Y-m-d'),
                    'approved_lwd'          => $lwd->format('Y-m-d'),
                    'notice_period_days'    => $noticeDays,
                    'notice_shortfall_days' => 0,
                    'notice_action'         => ($mode === 'immediate') ? 'waive' : 'serve',
                    'reason_category'       => $reasonCat,
                    'reason_details'        => $validated['remarks'] ?? 'Involuntary separation via probation evaluation API.',
                    'initiated_by'          => 'employer',
                    'approved_by'           => auth()->id(),
                    'approved_at'           => now(),
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
                    EmployeeExitClearance::create([
                        'tenant_id'        => $tenantId,
                        'employee_exit_id' => $exit->id,
                        'department'       => $item['department'],
                        'item_name'        => $item['item_name'],
                        'status'           => 'pending',
                    ]);
                }
            }

            $fnfService = app(FnFCalculationService::class);
            $computedFnF = $fnfService->calculateFnF($exit);
            $fnfService->saveSettlement($exit, $computedFnF);

            $empModel->update(['employee_stage' => 'Notice Period']);
        }

        return $this->sendSuccess($eval, 'Probation evaluation recorded successfully.');
    }
}
