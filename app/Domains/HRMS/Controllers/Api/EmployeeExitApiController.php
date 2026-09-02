<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeExitApiController extends Controller
{
    public function __construct(
        private readonly FnFCalculationService $fnfService
    ) {}

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
        $exits = EmployeeExit::where('tenant_id', $tenantId)
            ->with(['employee.department', 'employee.designation', 'clearances', 'fnfSettlement', 'documents'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return $this->sendSuccess($exits, 'Exits list retrieved successfully');
    }

    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'separation_type' => 'required|string|in:resignation,termination,retirement,layoff,contract_end,absconding',
            'resignation_date' => 'required|date',
            'preferred_lwd' => 'nullable|date|after_or_equal:resignation_date',
            'notice_period_days' => 'required|integer|min:0|max:180',
            'reason_category' => 'nullable|string|max:255',
            'reason_details' => 'nullable|string|max:2000',
        ]);

        $tenantId = tenant_id() ?? auth()->user()->tenant_id;
        $employee = Employee::findOrFail($validated['employee_id']);

        $resignationDate = Carbon::parse($validated['resignation_date']);
        $calculatedLwd = $validated['preferred_lwd'] 
            ? Carbon::parse($validated['preferred_lwd']) 
            : $resignationDate->copy()->addDays((int) $validated['notice_period_days']);

        $exit = EmployeeExit::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'separation_type' => $validated['separation_type'],
            'resignation_date' => $validated['resignation_date'],
            'preferred_lwd' => $calculatedLwd->format('Y-m-d'),
            'approved_lwd' => $calculatedLwd->format('Y-m-d'),
            'notice_period_days' => $validated['notice_period_days'],
            'notice_shortfall_days' => 0,
            'notice_action' => 'serve',
            'reason_category' => $validated['reason_category'] ?? 'Career Growth',
            'reason_details' => $validated['reason_details'] ?? null,
            'status' => 'in_clearance',
            'initiated_by' => 'employee',
        ]);

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
                'tenant_id' => $tenantId,
                'employee_exit_id' => $exit->id,
                'department' => $item['department'],
                'item_name' => $item['item_name'],
                'status' => 'pending',
            ]);
        }

        $employee->update(['employee_stage' => 'Notice Period']);

        $computedFnF = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computedFnF);

        return $this->sendSuccess($exit->load('clearances', 'fnfSettlement'), 'Exit initiated successfully', 201);
    }

    public function show(EmployeeExit $exit): JsonResponse
    {
        $exit->load(['employee', 'clearances', 'fnfSettlement', 'documents']);
        return $this->sendSuccess($exit, 'Exit details retrieved successfully');
    }
}
