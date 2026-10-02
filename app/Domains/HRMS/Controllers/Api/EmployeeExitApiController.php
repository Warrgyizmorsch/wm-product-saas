<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetAllocation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\EmployeeExitDocument;
use App\Domains\HRMS\Models\ExitClearanceTemplate;
use App\Domains\HRMS\Repositories\EmployeeExitRepositoryInterface;
use App\Domains\HRMS\Repositories\ExitClearancePolicyRepositoryInterface;
use App\Domains\HRMS\Services\ExitClearanceService;
use App\Domains\HRMS\Services\ExitDocumentationService;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmployeeExitApiController extends Controller
{
    public function __construct(
        private readonly EmployeeExitRepositoryInterface $exitRepository,
        private readonly ExitDocumentationService $docService,
        private readonly ExitClearanceService $clearanceService,
        private readonly FnFCalculationService $fnfService,
        private readonly ExitClearancePolicyRepositoryInterface $policyRepository,
        private readonly HrmsScopeService $scopeService
    ) {}

    /**
     * Standardized JSON success response envelope.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Standardized JSON error response envelope.
     */
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

    /**
     * Resolve tenant ID, user, current employee, and HR admin status.
     */
    private function resolveContext(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);

        $access = app(AccessService::class);
        $context = ['tenant_id' => $tenantId];

        $isHrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $access->allows($user, 'hr.settings.manage', $context) ||
            $access->allows($user, 'hrms.exit_policies.manage', $context) ||
            $access->allows($user, 'hrms.employee_exits.view', $context) ||
            $access->allows($user, 'hrms.employees.manage', $context) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hr.employees.manage') ||
            $user->hasHrPermission('hrms.employees.manage')
        );

        return [$tenantId, $user, $currentEmployee, (bool) $isHrAdmin];
    }

    // =========================================================================
    // 1. SUMMARY / DASHBOARD HUB
    // =========================================================================

    /**
     * GET /api/hrms/exits/summary
     * Total counters for employee exits and offboarding status.
     */
    public function summary(Request $request): JsonResponse
    {
        [$tenantId, , $currentEmployee, $isHrAdmin] = $this->resolveContext();

        try {
            $baseQuery = EmployeeExit::where('tenant_id', $tenantId);

            if (!$isHrAdmin && $currentEmployee) {
                $baseQuery->where('employee_id', $currentEmployee->id);
            }

            $stats = [
                'total_exits'          => (clone $baseQuery)->count(),
                'in_clearance_count'   => (clone $baseQuery)->where('status', 'in_clearance')->count(),
                'notice_period_count'  => (clone $baseQuery)->where('status', 'pending_approval')->count(),
                'settled_count'        => (clone $baseQuery)->where('status', 'completed')->count(),
                'pending_hr_count'     => (clone $baseQuery)->where('status', 'pending_approval')->whereNull('approved_at')->count(),
                'pending_manager_count'=> (clone $baseQuery)->where('status', 'pending_approval')->whereNull('manager_recommended_lwd')->count(),
            ];

            return $this->sendSuccess($stats, 'Exit summary retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 2. EXITS DIRECTORY & INITIATION API
    // =========================================================================

    /**
     * GET /api/hrms/exits
     * List employee exits with status, department, search filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId, , $currentEmployee, $isHrAdmin] = $this->resolveContext();

        $query = EmployeeExit::with([
            'employee:id,tenant_id,full_name,employee_id,department_id,designation_id,company_id,photo,date_of_joining',
            'employee.department:id,name',
            'employee.designation:id,name',
            'employee.company:id,company_name',
            'fnfSettlement:id,employee_exit_id,net_payable,status,is_settled',
        ])
            ->withCount('clearances')
            ->where('tenant_id', $tenantId);

        if (!$isHrAdmin) {
            if (!$currentEmployee) {
                return $this->sendError('Employee profile not found.', 404);
            }
            $query->where('employee_id', $currentEmployee->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('separation_type')) {
            $query->where('separation_type', $request->separation_type);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', $search)
                  ->orWhere('employee_id', 'like', $search);
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $exits = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $exits->getCollection()->transform(function ($exit) use ($currentEmployee, $isHrAdmin) {
            $isOwner = $currentEmployee && (int)$exit->employee_id === (int)$currentEmployee->id;
            $isPending = in_array($exit->status, ['pending_approval', 'submitted', 'in_clearance']);
            $exit->capabilities = [
                'can_view'            => true,
                'can_approve_manager' => ($isHrAdmin || ($currentEmployee && $exit->employee && $exit->employee->reporting_manager_id === $currentEmployee->id)) && $isPending,
                'can_approve_hr'      => $isHrAdmin && $isPending,
                'can_clear'           => $isHrAdmin,
                'can_settle_fnf'      => $isHrAdmin && in_array($exit->status, ['in_clearance', 'approved', 'completed']),
                'can_cancel'          => ($isOwner || $isHrAdmin) && $isPending,
            ];
            return $exit;
        });

        return $this->sendSuccess($exits, 'Exits list retrieved successfully.');
    }

    /**
     * POST /api/hrms/exits/initiate
     * Initiate employee exit / resignation.
     */
    public function initiate(Request $request): JsonResponse
    {
        [$tenantId, , $authEmployee, $isHrAdmin] = $this->resolveContext();

        $validator = Validator::make($request->all(), [
            'employee_id'        => 'required|exists:employees,id',
            'separation_type'    => 'required|string|in:resignation,termination,retirement,layoff,contract_end,absconding,death',
            'resignation_date'   => 'required|date',
            'preferred_lwd'      => 'nullable|date|after_or_equal:resignation_date',
            'notice_period_days' => 'nullable|integer|min:0|max:180',
            'reason_category'    => 'required|string|max:255',
            'reason_details'     => 'nullable|string|max:2000',
            'feedback_text'      => 'nullable|string',
            'initiated_by'       => 'nullable|in:employee,employer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $validated = $validator->validated();

        if (!$isHrAdmin && $authEmployee && (int)$validated['employee_id'] !== (int)$authEmployee->id) {
            return $this->sendError('Unauthorized action. You can only initiate resignation for yourself.', 403);
        }

        $employee = Employee::find($validated['employee_id']);
        if (!$employee) {
            return $this->sendError("Employee not found.", 404);
        }

        try {
            $resignationDate = Carbon::parse($validated['resignation_date']);
            $noticeDays = (int) ($validated['notice_period_days'] ?? 30);
            $calculatedLwd = !empty($validated['preferred_lwd'])
                ? Carbon::parse($validated['preferred_lwd'])
                : $resignationDate->copy()->addDays($noticeDays);

            $exit = EmployeeExit::create([
                'tenant_id'             => $tenantId,
                'employee_id'           => $employee->id,
                'separation_type'       => $validated['separation_type'],
                'resignation_date'      => $validated['resignation_date'],
                'preferred_lwd'         => $calculatedLwd->format('Y-m-d'),
                'approved_lwd'          => $calculatedLwd->format('Y-m-d'),
                'notice_period_days'    => $noticeDays,
                'notice_shortfall_days' => 0,
                'notice_action'         => 'serve',
                'reason_category'       => $validated['reason_category'],
                'reason_details'        => $validated['reason_details'] ?? null,
                'status'                => 'in_clearance',
                'initiated_by'          => $validated['initiated_by'] ?? ($isHrAdmin ? 'employer' : 'employee'),
            ]);

            $this->clearanceService->generateClearancesForExit($exit, $tenantId);
            $employee->update(['employee_stage' => 'Notice Period']);

            $computedFnF = $this->fnfService->calculateFnF($exit);
            $this->fnfService->saveSettlement($exit, $computedFnF);

            return $this->sendSuccess($exit->load('clearances', 'fnfSettlement'), 'Exit initiated successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/hrms/exits/{id}
     * Detailed single exit case with clearances, assets, and FnF settlement.
     */
    public function show(mixed $id): JsonResponse
    {
        [$tenantId, , $employee, $isHrAdmin] = $this->resolveContext();

        $exit = EmployeeExit::with([
            'employee.company:id,company_name,legal_name,code',
            'employee.department:id,name',
            'employee.designation:id,name',
            'clearances.clearedByUser:id,name,email',
            'fnfSettlement',
            'documents:id,employee_exit_id,document_type,reference_number,issue_date',
        ])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$exit) {
            return $this->sendError("Employee exit record not found.", 404);
        }

        if (!$isHrAdmin && $employee && $exit->employee_id !== $employee->id) {
            return $this->sendError('Unauthorized action. You can only view your own exit record.', 403);
        }

        $isOwner = $employee && (int)$exit->employee_id === (int)$employee->id;
        $isPending = in_array($exit->status, ['pending_approval', 'submitted', 'in_clearance']);
        $exit->capabilities = [
            'can_view'            => true,
            'can_approve_manager' => ($isHrAdmin || ($employee && $exit->employee && $exit->employee->reporting_manager_id === $employee->id)) && $isPending,
            'can_approve_hr'      => $isHrAdmin && $isPending,
            'can_clear'           => $isHrAdmin || ($employee && $exit->clearances->contains('assigned_to_user_id', auth()->id())),
            'can_settle_fnf'      => $isHrAdmin && in_array($exit->status, ['in_clearance', 'approved', 'completed']),
            'can_cancel'          => ($isOwner || $isHrAdmin) && $isPending,
        ];

        return $this->sendSuccess($exit, 'Exit details retrieved successfully.');
    }

    // =========================================================================
    // 3. APPROVAL WORKFLOW API
    // =========================================================================

    /**
     * POST /api/hrms/exits/{id}/approve
     * POST /api/hrms/exits/{id}/approve-hr
     * HR approval with final approved LWD, notice shortfall handling, and remarks.
     */
    public function approve(Request $request, mixed $id): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized to approve employee exit.', 403);
        }

        $exit = EmployeeExit::where('tenant_id', $tenantId)->find($id);
        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        $validator = Validator::make($request->all(), [
            'approved_lwd'          => 'required|date',
            'notice_action'         => 'required|in:serve,waive,recover',
            'notice_shortfall_days' => 'nullable|integer|min:0',
            'hr_remarks'            => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $this->exitRepository->approveHr($exit, $validator->validated(), auth()->id(), $tenantId);
            return $this->sendSuccess($exit->fresh(['clearances', 'fnfSettlement']), 'Exit request approved by HR.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/exits/{id}/approve-manager
     * Manager endorsement of exit with recommended LWD.
     */
    public function approveManager(Request $request, mixed $id): JsonResponse
    {
        [$tenantId] = $this->resolveContext();

        $exit = EmployeeExit::where('tenant_id', $tenantId)->find($id);
        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        $validator = Validator::make($request->all(), [
            'manager_recommended_lwd' => 'nullable|date',
            'manager_remarks'         => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $this->exitRepository->approveManager($exit, $validator->validated(), auth()->id());
            return $this->sendSuccess($exit->fresh(), 'Exit request endorsed by manager and routed to HR.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/exits/{id}/reject
     * Reject employee exit request.
     */
    public function reject(Request $request, mixed $id): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized to reject exit request.', 403);
        }

        $exit = EmployeeExit::where('tenant_id', $tenantId)->find($id);
        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        $reason = $request->input('reason', 'Exit request rejected by management.');

        try {
            $this->exitRepository->rejectExit($exit, $reason);
            return $this->sendSuccess(['id' => $exit->id, 'status' => 'rejected'], 'Exit request rejected.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 4. CLEARANCE EXECUTION & AD-HOC ITEMS API
    // =========================================================================

    /**
     * PUT /api/hrms/exits/clearances/{id}
     * Update single clearance item status and deduction/recovery amount.
     */
    public function updateClearance(Request $request, mixed $id): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $clearance = EmployeeExitClearance::where('tenant_id', $tenantId)->find($id);
        if (!$clearance) {
            return $this->sendError("Clearance item not found.", 404);
        }

        $validator = Validator::make($request->all(), [
            'status'           => 'required|in:cleared,pending,waived,issue_found',
            'remarks'          => 'nullable|string|max:500',
            'deduction_amount' => 'nullable|numeric|min:0',
            'recovery_amount'  => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $validated = $validator->validated();
            $validated['recovery_amount'] = $validated['recovery_amount'] ?? ($validated['deduction_amount'] ?? 0);

            $this->exitRepository->clearItem($clearance, $validated, auth()->id());

            return $this->sendSuccess($clearance->fresh(), "Clearance item updated successfully.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/hrms/exits/{id}/clearances/department/{department}
     * Batch update all clearance items for a department.
     */
    public function updateDepartmentClearances(Request $request, mixed $id, string $department): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $exit = EmployeeExit::where('tenant_id', $tenantId)->find($id);
        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        $items = $request->input('items', []);
        $userId = auth()->id();

        try {
            DB::transaction(function () use ($items, $userId, $exit, $tenantId) {
                foreach ($items as $itemId => $itemData) {
                    $c = EmployeeExitClearance::where('tenant_id', $tenantId)
                        ->where('employee_exit_id', $exit->id)
                        ->find($itemId);

                    if ($c) {
                        $status = $itemData['status'] ?? $c->status;
                        $deduction = isset($itemData['deduction_amount']) ? (float) $itemData['deduction_amount'] : ($c->deduction_amount ?? 0);
                        $remarks = $itemData['remarks'] ?? $c->remarks;

                        $c->update([
                            'status'           => $status,
                            'deduction_amount' => $deduction,
                            'recovery_amount'  => $deduction,
                            'remarks'          => $remarks,
                            'cleared_by'       => $userId,
                            'cleared_at'       => in_array($status, ['cleared', 'waived']) ? now() : null,
                        ]);
                    }
                }
            });

            $this->exitRepository->recalculateFnf($exit);

            return $this->sendSuccess(null, "Clearances for department '{$department}' updated successfully.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/exits/{id}/clearances/adhoc
     */
    public function storeAdhocClearance(Request $request, mixed $id): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $exit = EmployeeExit::where('tenant_id', $tenantId)->find($id);
        if (!$exit) {
            return $this->sendError("Employee exit record not found.", 404);
        }

        $validator = Validator::make($request->all(), [
            'clearance_category' => 'nullable|string|max:100',
            'department'         => 'nullable|string|max:100',
            'item_name'          => 'required|string|max:255',
            'remarks'            => 'nullable|string|max:500',
            'deduction_amount'   => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $validated['clearance_category'] = $validated['clearance_category'] ?? ($validated['department'] ?? 'general');

        try {
            $item = $this->clearanceService->addAdhocClearanceItem($exit, $validated);
            return $this->sendSuccess($item, 'Ad-hoc clearance point added successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/exits/clearances/{id}/adhoc
     */
    public function destroyAdhocClearance(mixed $id): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $clearance = EmployeeExitClearance::where('tenant_id', $tenantId)->find($id);
        if (!$clearance) {
            return $this->sendError("Clearance item not found.", 404);
        }

        try {
            $exit = $clearance->exit;
            $clearance->delete();

            if ($exit) {
                $this->exitRepository->recalculateFnf($exit);
            }

            return $this->sendSuccess(['id' => (int)$id], 'Clearance item deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 5. ASSET HANDOVER & RETURN API
    // =========================================================================

    /**
     * POST /api/hrms/exits/{id}/assets/{assetId}/return
     * Return company asset during exit clearance and recalculate FnF asset deduction.
     */
    public function returnAsset(Request $request, mixed $id, mixed $assetId): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $exit = EmployeeExit::where('tenant_id', $tenantId)->find($id);
        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        $asset = Asset::where('tenant_id', $tenantId)->find($assetId);
        if (!$asset) {
            return $this->sendError("Asset record not found.", 404);
        }

        $validator = Validator::make($request->all(), [
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
            'notes'               => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $validated = $validator->validated();

        try {
            $allocation = AssetAllocation::where('asset_id', $asset->id)
                ->where('employee_id', $exit->employee_id)
                ->whereNull('returned_at')
                ->first();

            DB::transaction(function () use ($asset, $allocation, $validated) {
                $newStatus = match ($validated['condition_on_return']) {
                    'damaged'  => 'maintenance',
                    'scrapped' => 'scrapped',
                    default    => 'available',
                };

                if ($allocation) {
                    $allocation->update([
                        'returned_at'      => now(),
                        'return_condition' => $validated['condition_on_return'],
                        'notes'            => $validated['notes'] ?? $allocation->notes,
                    ]);
                }

                $asset->update([
                    'status'               => $newStatus,
                    'condition'            => $validated['condition_on_return'],
                    'assigned_employee_id' => null,
                    'allocated_at'         => null,
                    'expected_return_date' => null,
                ]);
            });

            $this->exitRepository->recalculateFnf($exit);

            return $this->sendSuccess([
                'asset_id'  => $asset->id,
                'condition' => $validated['condition_on_return'],
            ], "Asset '{$asset->name}' marked as returned successfully.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 6. FULL & FINAL (FNF) SETTLEMENT API
    // =========================================================================

    /**
     * POST /api/hrms/exits/{id}/fnf/recalculate
     */
    public function recalculateFnF(mixed $id): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $exit = EmployeeExit::where('tenant_id', $tenantId)->find($id);
        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        try {
            $settlement = $this->exitRepository->recalculateFnf($exit);
            return $this->sendSuccess($settlement, 'FnF settlement recalculated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/exits/{id}/fnf/finalize
     */
    public function finalizeFnF(Request $request, mixed $id): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $exit = EmployeeExit::where('tenant_id', $tenantId)->find($id);
        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        $validator = Validator::make($request->all(), [
            'unpaid_salary'           => 'required|numeric|min:0',
            'leave_encashment_amount' => 'required|numeric|min:0',
            'gratuity_amount'         => 'required|numeric|min:0',
            'bonus_amount'            => 'required|numeric|min:0',
            'other_earnings'          => 'nullable|numeric|min:0',
            'notice_recovery_amount'  => 'required|numeric|min:0',
            'asset_recovery_amount'   => 'required|numeric|min:0',
            'loan_deduction_amount'   => 'required|numeric|min:0',
            'tax_deduction_amount'    => 'required|numeric|min:0',
            'other_deductions'        => 'nullable|numeric|min:0',
            'remarks'                 => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $settlement = $this->exitRepository->saveFnf($exit, $validator->validated());
            return $this->sendSuccess($settlement, 'FnF settlement finalized and saved successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 7. EXIT CERTIFICATES & DOCUMENTS API
    // =========================================================================

    /**
     * GET /api/hrms/exits/{id}/documents/relieving-letter
     */
    public function getRelievingLetter(mixed $id): JsonResponse
    {
        [$tenantId, , $employee, $isHrAdmin] = $this->resolveContext();

        $exit = EmployeeExit::with(['employee.company', 'employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        if (!$isHrAdmin && $employee && $exit->employee_id !== $employee->id) {
            return $this->sendError('Unauthorized.', 403);
        }

        try {
            $doc = $this->docService->generateRelievingLetter($exit);
            return $this->sendSuccess($doc, 'Relieving letter generated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/hrms/exits/{id}/documents/experience-certificate
     */
    public function getExperienceCertificate(mixed $id): JsonResponse
    {
        [$tenantId, , $employee, $isHrAdmin] = $this->resolveContext();

        $exit = EmployeeExit::with(['employee.company', 'employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        if (!$isHrAdmin && $employee && $exit->employee_id !== $employee->id) {
            return $this->sendError('Unauthorized.', 403);
        }

        try {
            $doc = $this->docService->generateExperienceCertificate($exit);
            return $this->sendSuccess($doc, 'Experience certificate generated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/hrms/exits/{id}/documents/noc-certificate
     */
    public function getNocCertificate(mixed $id): JsonResponse
    {
        [$tenantId, , $employee, $isHrAdmin] = $this->resolveContext();

        $exit = EmployeeExit::with(['employee.company', 'employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        if (!$isHrAdmin && $employee && $exit->employee_id !== $employee->id) {
            return $this->sendError('Unauthorized.', 403);
        }

        try {
            $doc = $this->docService->generateNocCertificate($exit);
            return $this->sendSuccess($doc, 'NOC certificate generated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/hrms/exits/{id}/documents/fnf-statement
     */
    public function getFnfStatement(mixed $id): JsonResponse
    {
        [$tenantId, , $employee, $isHrAdmin] = $this->resolveContext();

        $exit = EmployeeExit::with(['employee.company', 'employee.department', 'employee.designation', 'fnfSettlement'])
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$exit) {
            return $this->sendError("Exit record not found.", 404);
        }

        if (!$isHrAdmin && $employee && $exit->employee_id !== $employee->id) {
            return $this->sendError('Unauthorized.', 403);
        }

        try {
            $fnf = $exit->fnfSettlement ?: $this->fnfService->saveSettlement($exit, $this->fnfService->calculateFnF($exit));
            return $this->sendSuccess($fnf, 'FnF statement retrieved.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 8. MASTER CLEARANCE POLICY TEMPLATES API
    // =========================================================================

    /**
     * GET /api/hrms/exits/clearance-templates
     */
    public function listTemplates(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveContext();
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $categories = $this->clearanceService->getCategoriesSummary($companyId, $tenantId);
        $templates = $this->clearanceService->getAllTemplatesForManagement($companyId, $tenantId);

        return $this->sendSuccess([
            'categories' => $categories,
            'templates'  => $templates,
        ], 'Clearance templates retrieved successfully.');
    }

    /**
     * POST /api/hrms/exits/clearance-templates
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $validator = Validator::make($request->all(), [
            'company_id'         => 'nullable|exists:companies,id',
            'clearance_category' => 'required|string|max:100',
            'category_name'      => 'required|string|max:150',
            'item_name'          => 'required|string|max:255',
            'description'        => 'nullable|string|max:1000',
            'is_mandatory'       => 'nullable|boolean',
            'sort_order'         => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $categoryKey = Str::slug($validated['clearance_category'], '_');

        try {
            $template = ExitClearanceTemplate::create([
                'tenant_id'          => $tenantId,
                'company_id'         => $validated['company_id'] ?: null,
                'clearance_category' => $categoryKey,
                'category_name'      => trim($validated['category_name']),
                'item_name'          => trim($validated['item_name']),
                'description'        => $validated['description'] ?? null,
                'is_mandatory'       => $request->boolean('is_mandatory', true),
                'sort_order'         => $validated['sort_order'] ?? 0,
                'status'             => true,
            ]);

            return $this->sendSuccess($template, 'Clearance template point created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/hrms/exits/clearance-templates/{id}
     */
    public function updateTemplate(Request $request, mixed $id): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $template = ExitClearanceTemplate::where('tenant_id', $tenantId)->find($id);
        if (!$template) {
            return $this->sendError("Clearance template point not found.", 404);
        }

        $validator = Validator::make($request->all(), [
            'company_id'         => 'nullable|exists:companies,id',
            'clearance_category' => 'required|string|max:100',
            'category_name'      => 'required|string|max:150',
            'item_name'          => 'required|string|max:255',
            'description'        => 'nullable|string|max:1000',
            'is_mandatory'       => 'nullable|boolean',
            'sort_order'         => 'nullable|integer|min:0',
            'status'             => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $categoryKey = Str::slug($validated['clearance_category'], '_');

        try {
            $template->update([
                'company_id'         => $validated['company_id'] ?: null,
                'clearance_category' => $categoryKey,
                'category_name'      => trim($validated['category_name']),
                'item_name'          => trim($validated['item_name']),
                'description'        => $validated['description'] ?? null,
                'is_mandatory'       => $request->boolean('is_mandatory'),
                'sort_order'         => $validated['sort_order'] ?? 0,
                'status'             => $request->boolean('status', true),
            ]);

            return $this->sendSuccess($template->fresh(), 'Clearance template point updated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/exits/clearance-templates/{id}
     */
    public function destroyTemplate(mixed $id): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $template = ExitClearanceTemplate::where('tenant_id', $tenantId)->find($id);
        if (!$template) {
            return $this->sendError("Clearance template point not found.", 404);
        }

        try {
            $template->delete();
            return $this->sendSuccess(['id' => (int)$id], 'Clearance template point deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/exits/clearance-templates/categories/{category}
     */
    public function destroyCategory(Request $request, string $category): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        try {
            $count = $this->policyRepository->deleteCategory($category, $companyId);
            return $this->sendSuccess(['deleted_count' => $count], "Clearance category deleted successfully.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/exits/clearance-templates/reset
     */
    public function resetTemplates(Request $request): JsonResponse
    {
        [$tenantId, , , $isHrAdmin] = $this->resolveContext();

        if (!$isHrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        try {
            $this->clearanceService->resetTemplatesToDefaults($tenantId, $companyId);
            return $this->sendSuccess(null, 'Clearance templates reset to system defaults.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }
}
