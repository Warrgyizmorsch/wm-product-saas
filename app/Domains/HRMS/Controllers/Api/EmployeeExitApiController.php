<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\ExitClearanceTemplate;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Domains\HRMS\Services\ExitClearanceService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeExitApiController extends Controller
{
    public function __construct(
        private readonly FnFCalculationService $fnfService,
        private readonly ExitClearanceService $clearanceService
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
            ->with(['employee.department', 'employee.designation', 'employee.company', 'clearances', 'fnfSettlement', 'documents'])
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

        // Auto-generate dynamic clearance items
        $this->clearanceService->generateClearancesForExit($exit, $tenantId);

        $employee->update(['employee_stage' => 'Notice Period']);

        $computedFnF = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computedFnF);

        return $this->sendSuccess($exit->load('clearances', 'fnfSettlement'), 'Exit initiated successfully', 201);
    }

    public function show(EmployeeExit $exit): JsonResponse
    {
        $exit->load(['employee.company', 'employee.department', 'employee.designation', 'clearances.clearedByUser', 'fnfSettlement', 'documents']);
        return $this->sendSuccess($exit, 'Exit details retrieved successfully');
    }

    public function listTemplates(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? auth()->user()->tenant_id;
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $categories = $this->clearanceService->getCategoriesSummary($companyId, $tenantId);
        $templates = $this->clearanceService->getAllTemplatesForManagement($companyId, $tenantId);

        return $this->sendSuccess([
            'categories' => $categories,
            'templates' => $templates,
        ], 'Clearance templates retrieved successfully');
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'clearance_category' => 'required|string|max:100',
            'category_name' => 'required|string|max:150',
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_mandatory' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $tenantId = tenant_id() ?? auth()->user()->tenant_id;
        $categoryKey = Str::slug($validated['clearance_category'], '_');

        $template = ExitClearanceTemplate::create([
            'tenant_id' => $tenantId,
            'company_id' => $validated['company_id'] ?: null,
            'clearance_category' => $categoryKey,
            'category_name' => trim($validated['category_name']),
            'item_name' => trim($validated['item_name']),
            'description' => $validated['description'] ?? null,
            'is_mandatory' => $request->boolean('is_mandatory', true),
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => true,
        ]);

        return $this->sendSuccess($template, 'Clearance template point created successfully', 201);
    }

    public function updateTemplate(Request $request, ExitClearanceTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'clearance_category' => 'required|string|max:100',
            'category_name' => 'required|string|max:150',
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_mandatory' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
        ]);

        $categoryKey = Str::slug($validated['clearance_category'], '_');

        $template->update([
            'company_id' => $validated['company_id'] ?: null,
            'clearance_category' => $categoryKey,
            'category_name' => trim($validated['category_name']),
            'item_name' => trim($validated['item_name']),
            'description' => $validated['description'] ?? null,
            'is_mandatory' => $request->boolean('is_mandatory'),
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => $request->boolean('status', true),
        ]);

        return $this->sendSuccess($template, 'Clearance template point updated successfully');
    }

    public function destroyTemplate(ExitClearanceTemplate $template): JsonResponse
    {
        $template->delete();
        return $this->sendSuccess(null, 'Clearance template point deleted successfully');
    }

    public function resetTemplates(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? auth()->user()->tenant_id;
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $this->clearanceService->resetTemplatesToDefaults($tenantId, $companyId);

        return $this->sendSuccess(null, 'Clearance templates reset to system defaults');
    }

    public function storeAdhocClearance(Request $request, EmployeeExit $exit): JsonResponse
    {
        $validated = $request->validate([
            'clearance_category' => 'required|string|max:100',
            'item_name' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:500',
            'deduction_amount' => 'nullable|numeric|min:0',
        ]);

        $item = $this->clearanceService->addAdhocClearanceItem($exit, $validated);

        return $this->sendSuccess($item, 'Ad-hoc clearance point added successfully', 201);
    }

    public function destroyAdhocClearance(EmployeeExitClearance $clearance): JsonResponse
    {
        $exit = $clearance->exit;
        $clearance->delete();

        if ($exit) {
            $computedFnF = $this->fnfService->calculateFnF($exit);
            $this->fnfService->saveSettlement($exit, $computedFnF);
        }

        return $this->sendSuccess(null, 'Clearance item deleted successfully');
    }
}

