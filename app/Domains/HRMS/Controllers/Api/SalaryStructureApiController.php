<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\SalaryStructureItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SalaryStructureApiController extends Controller
{
    /**
     * Helper for standardized success JSON response.
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
     * Helper for standardized error JSON response.
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
     * Parse flexible boolean input ('1', 1, true, 'true', 'active', 'success', 'yes', 'on').
     */
    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        $str = strtolower(trim((string)$value));
        return in_array($str, ['1', 'true', 'active', 'success', 'yes', 'on'], true);
    }

    /**
     * Null-safe authorization check supporting Web Sessions & HTTP Basic Auth.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth username or password.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please log in or provide HTTP Basic Auth credentials.', 401);
            }
        }

        return null;
    }

    // ==========================================
    // DATA TRANSFORMERS (CONCISE API PAYLOADS)
    // ==========================================

    private function formatPayGroup(PayGroup $payGroup, bool $detailed = false): array
    {
        $data = [
            'id'           => $payGroup->id,
            'company_id'   => $payGroup->company_id,
            'company_name' => $payGroup->company?->company_name ?? 'Default Company',
            'name'         => $payGroup->name,
            'description'  => $payGroup->description,
            'status'       => $payGroup->status ? 'active' : 'inactive',
            'is_active'    => (bool) $payGroup->status,
        ];

        if ($detailed) {
            $data['payroll_rules'] = $payGroup->payroll_rules ?? [
                'proration_rule'         => 'calendar_days',
                'lop_splicing_rule'      => 'proportionate_gross',
                'attendance_lock_day'    => 25,
                'variable_lock_day'      => 25,
                'enable_pf'              => true,
                'restrict_pf_ceiling'    => true,
                'enable_esi'             => true,
                'restrict_esi_threshold' => true,
            ];

            if ($payGroup->relationLoaded('components')) {
                $data['components'] = $payGroup->components->map(fn($c) => $this->formatComponent($c))->values();
            }

            if ($payGroup->relationLoaded('structures')) {
                $data['structures'] = $payGroup->structures->map(fn($s) => $this->formatStructure($s, false))->values();
            }
        }

        return $data;
    }

    private function formatComponent(SalaryComponent $component): array
    {
        return [
            'id'               => $component->id,
            'company_id'       => $component->company_id,
            'company_name'     => $component->company?->company_name ?? 'Default Company',
            'pay_group_id'     => $component->pay_group_id,
            'pay_group_name'   => $component->payGroup?->name ?? 'All Pay Groups',
            'name'             => $component->name,
            'code'             => $component->code,
            'type'             => $component->type,
            'calculation_type' => $component->calculation_type ?? 'fixed',
            'default_value'    => $component->default_value,
            'description'      => $component->description,
            'is_adhoc'         => (bool) $component->is_adhoc,
            'status'           => $component->status ? 'active' : 'inactive',
            'is_active'        => (bool) $component->status,
        ];
    }

    private function formatStructure(SalaryStructure $structure, bool $includeItems = true): array
    {
        $data = [
            'id'             => $structure->id,
            'company_id'     => $structure->company_id,
            'company_name'   => $structure->company?->company_name ?? 'Default Company',
            'pay_group_id'   => $structure->pay_group_id,
            'pay_group_name' => $structure->payGroup?->name ?? 'All Pay Groups',
            'name'           => $structure->name,
            'min_ctc'        => (float) $structure->min_ctc,
            'max_ctc'        => (float) $structure->max_ctc,
            'status'         => $structure->status ? 'active' : 'inactive',
            'is_active'      => (bool) $structure->status,
        ];

        if ($includeItems && $structure->relationLoaded('items')) {
            $data['items_count'] = $structure->items->count();
            $data['items']       = $structure->items->map(function ($item) {
                return [
                    'id'                  => $item->id,
                    'salary_component_id' => $item->salary_component_id,
                    'component_name'      => $item->component?->name,
                    'component_code'      => $item->component?->code,
                    'component_type'      => $item->component?->type,
                    'calculation_type'    => $item->calculation_type,
                    'value'               => (float) $item->value,
                    'sort_order'          => (int) $item->sort_order,
                ];
            })->values();
        }

        return $data;
    }

    private function formatPaginated($paginator, callable $transformCallback): array
    {
        return [
            'items' => collect($paginator->items())->map($transformCallback)->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ];
    }

    // ==========================================
    // SUMMARY DASHBOARD API
    // ==========================================

    public function summary(Request $request): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $payGroups = PayGroup::with(['company'])->orderBy('name', 'asc')->get();
            $selectedPayGroupId = $request->get('pay_group_id');
            $selectedPayGroup = $selectedPayGroupId
                ? PayGroup::with(['company', 'components', 'structures.items.component'])->find($selectedPayGroupId)
                : ($payGroups->first() ? PayGroup::with(['company', 'components', 'structures.items.component'])->find($payGroups->first()->id) : null);

            return $this->sendSuccess([
                'metrics' => [
                    'pay_groups_count' => PayGroup::count(),
                    'components_count' => SalaryComponent::count(),
                    'structures_count' => SalaryStructure::count(),
                ],
                'companies' => Company::orderBy('company_name')->get(['id', 'company_name as name']),
                'pay_groups' => $payGroups->map(fn($pg) => $this->formatPayGroup($pg)),
                'selected_pay_group' => $selectedPayGroup ? $this->formatPayGroup($selectedPayGroup, true) : null,
            ], 'Salary structure summary loaded successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to load summary: ' . $e->getMessage(), 500);
        }
    }

    // ==========================================
    // 1. PAY GROUPS API
    // ==========================================

    public function indexPayGroups(Request $request): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $query = PayGroup::with(['company']);

            if ($request->filled('status')) {
                $query->where('status', $this->parseBoolean($request->get('status')));
            }
            if ($request->filled('company_id')) {
                $query->where('company_id', $request->get('company_id'));
            }
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where('name', 'like', "%{$search}%");
            }

            $payGroups = $query->orderBy('name', 'asc')->get();
            $formatted = $payGroups->map(fn($pg) => $this->formatPayGroup($pg));

            return $this->sendSuccess($formatted, 'Pay groups retrieved successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to fetch pay groups: ' . $e->getMessage(), 500);
        }
    }

    public function showPayGroup(mixed $payGroup): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $payGroup instanceof PayGroup ? $payGroup : PayGroup::find($payGroup);
            if (!$model) {
                return $this->sendError('Pay group not found', 404);
            }

            $model->load(['company', 'components', 'structures.items.component']);

            return $this->sendSuccess($this->formatPayGroup($model, true), 'Pay group details loaded');
        } catch (Throwable $e) {
            return $this->sendError('Failed to fetch pay group: ' . $e->getMessage(), 500);
        }
    }

    public function storePayGroup(Request $request): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            if ($request->has('company_id') && (empty($request->company_id) || $request->company_id === 'null' || $request->company_id === 0 || $request->company_id === '0')) {
                $request->merge(['company_id' => null]);
            }

            $validator = Validator::make($request->all(), [
                'name'        => 'required|string|max:255',
                'company_id'  => 'nullable|integer|exists:companies,id',
                'description' => 'nullable|string',
                'status'      => 'required',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed.', 422, $validator->errors());
            }

            $status = $this->parseBoolean($request->input('status'));

            $payGroup = PayGroup::create([
                'company_id'  => $request->input('company_id') ?: null,
                'name'        => $request->input('name'),
                'description' => $request->input('description') ?: null,
                'status'      => $status,
            ]);

            $payGroup->load(['company']);

            return $this->sendSuccess($this->formatPayGroup($payGroup, true), 'Pay group created successfully', 201);
        } catch (Throwable $e) {
            return $this->sendError('Failed to create pay group: ' . $e->getMessage(), 500);
        }
    }

    public function updatePayGroup(Request $request, mixed $payGroup): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $payGroup instanceof PayGroup ? $payGroup : PayGroup::find($payGroup);
            if (!$model) {
                return $this->sendError('Pay group not found', 404);
            }

            if ($request->has('company_id') && (empty($request->company_id) || $request->company_id === 'null' || $request->company_id === 0 || $request->company_id === '0')) {
                $request->merge(['company_id' => null]);
            }

            $validator = Validator::make($request->all(), [
                'name'        => 'required|string|max:255',
                'company_id'  => 'nullable|integer|exists:companies,id',
                'description' => 'nullable|string',
                'status'      => 'required',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed.', 422, $validator->errors());
            }

            $status = $this->parseBoolean($request->input('status'));

            $model->update([
                'company_id'  => $request->input('company_id') ?: null,
                'name'        => $request->input('name'),
                'description' => $request->input('description') ?: null,
                'status'      => $status,
            ]);

            $model->load(['company']);

            return $this->sendSuccess($this->formatPayGroup($model, true), 'Pay group updated successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to update pay group: ' . $e->getMessage(), 500);
        }
    }

    public function destroyPayGroup(mixed $payGroup): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $payGroup instanceof PayGroup ? $payGroup : PayGroup::find($payGroup);
            if (!$model) {
                return $this->sendError('Pay group not found', 404);
            }

            $model->delete();

            return $this->sendSuccess(['id' => (int)$model->id], 'Pay group deleted successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to delete pay group: ' . $e->getMessage(), 500);
        }
    }

    public function updatePayGroupRules(Request $request, mixed $payGroup): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $payGroup instanceof PayGroup ? $payGroup : PayGroup::find($payGroup);
            if (!$model) {
                return $this->sendError('Pay group not found', 404);
            }

            $rules = $model->payroll_rules ?? [];
            $request->merge([
                'enable_pf'              => $request->exists('enable_pf') ? filter_var($request->input('enable_pf'), FILTER_VALIDATE_BOOLEAN) : ($rules['enable_pf'] ?? true),
                'restrict_pf_ceiling'    => $request->exists('restrict_pf_ceiling') ? filter_var($request->input('restrict_pf_ceiling'), FILTER_VALIDATE_BOOLEAN) : ($rules['restrict_pf_ceiling'] ?? true),
                'enable_esi'             => $request->exists('enable_esi') ? filter_var($request->input('enable_esi'), FILTER_VALIDATE_BOOLEAN) : ($rules['enable_esi'] ?? true),
                'restrict_esi_threshold' => $request->exists('restrict_esi_threshold') ? filter_var($request->input('restrict_esi_threshold'), FILTER_VALIDATE_BOOLEAN) : ($rules['restrict_esi_threshold'] ?? true),
            ]);

            $validator = Validator::make($request->all(), [
                'proration_rule'         => 'required|in:calendar_days,fixed_30_days,working_days',
                'lop_splicing_rule'      => 'required|in:proportionate_gross,basic_hra_only',
                'attendance_lock_day'    => 'required|integer|min:1|max:31',
                'variable_lock_day'      => 'required|integer|min:1|max:31',
                'enable_pf'              => 'required|boolean',
                'restrict_pf_ceiling'    => 'required|boolean',
                'enable_esi'             => 'required|boolean',
                'restrict_esi_threshold' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed.', 422, $validator->errors());
            }

            $model->update([
                'payroll_rules' => $validator->validated(),
            ]);

            return $this->sendSuccess($this->formatPayGroup($model, true), 'Pay group rules updated successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to update rules: ' . $e->getMessage(), 500);
        }
    }

    // ==========================================
    // 2. SALARY COMPONENTS API
    // ==========================================

    public function indexComponents(Request $request): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $query = SalaryComponent::with(['company', 'payGroup']);

            if ($request->filled('pay_group_id')) {
                $query->where('pay_group_id', $request->get('pay_group_id'));
            }
            if ($request->filled('is_adhoc')) {
                $query->where('is_adhoc', $this->parseBoolean($request->get('is_adhoc')));
            }
            if ($request->filled('status')) {
                $query->where('status', $this->parseBoolean($request->get('status')));
            }
            if ($request->filled('type')) {
                $query->where('type', $request->get('type'));
            }
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            $sort = $request->get('sort', 'name_asc');
            switch ($sort) {
                case 'name_desc': $query->orderBy('name', 'desc'); break;
                case 'code_asc':  $query->orderBy('code', 'asc'); break;
                case 'code_desc': $query->orderBy('code', 'desc'); break;
                case 'name_asc':
                default: $query->orderBy('name', 'asc'); break;
            }

            $perPage = max(1, min(100, $request->integer('per_page', 10)));
            $components = $query->paginate($perPage);
            $result = $this->formatPaginated($components, fn($c) => $this->formatComponent($c));

            return $this->sendSuccess($result, 'Salary components retrieved successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to fetch components: ' . $e->getMessage(), 500);
        }
    }

    public function showComponent(mixed $salaryComponent): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $salaryComponent instanceof SalaryComponent ? $salaryComponent : SalaryComponent::find($salaryComponent);
            if (!$model) {
                return $this->sendError('Salary component not found', 404);
            }

            $model->load(['company', 'payGroup']);

            return $this->sendSuccess($this->formatComponent($model), 'Salary component details loaded');
        } catch (Throwable $e) {
            return $this->sendError('Failed to fetch component: ' . $e->getMessage(), 500);
        }
    }

    public function storeComponent(Request $request): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            if ($request->has('pay_group_id') && (empty($request->pay_group_id) || $request->pay_group_id === 'null' || $request->pay_group_id === 0 || $request->pay_group_id === '0')) {
                $request->merge(['pay_group_id' => null]);
            }

            $validator = Validator::make($request->all(), [
                'pay_group_id'     => 'nullable|integer|exists:pay_groups,id',
                'name'             => 'required|string|max:255',
                'code'             => 'required|string|max:50',
                'type'             => 'required|in:earning,deduction',
                'calculation_type' => 'nullable|string|max:50',
                'is_adhoc'         => 'required',
                'status'           => 'required',
                'description'      => 'nullable|string',
                'default_value'    => 'nullable|max:255',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed.', 422, $validator->errors());
            }

            $calculationType = $request->input('calculation_type') ?: 'fixed';
            $status          = $this->parseBoolean($request->input('status'));
            $isAdhoc         = $this->parseBoolean($request->input('is_adhoc'));

            $companyId = null;
            if (!empty($request->input('pay_group_id'))) {
                $payGroup = PayGroup::find($request->input('pay_group_id'));
                if ($payGroup) {
                    $companyId = $payGroup->company_id;
                }
            }

            $component = SalaryComponent::create([
                'company_id'       => $companyId,
                'pay_group_id'     => $request->input('pay_group_id') ?: null,
                'name'             => $request->input('name'),
                'code'             => strtoupper($request->input('code')),
                'type'             => $request->input('type'),
                'calculation_type' => $calculationType,
                'default_value'    => $request->input('default_value') ?: null,
                'description'      => $request->input('description') ?: null,
                'status'           => $status,
                'is_adhoc'         => $isAdhoc,
            ]);

            $component->load(['company', 'payGroup']);

            return $this->sendSuccess($this->formatComponent($component), 'Salary component created successfully', 201);
        } catch (Throwable $e) {
            return $this->sendError('Failed to create component: ' . $e->getMessage(), 500);
        }
    }

    public function updateComponent(Request $request, mixed $salaryComponent): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $salaryComponent instanceof SalaryComponent ? $salaryComponent : SalaryComponent::find($salaryComponent);
            if (!$model) {
                return $this->sendError('Salary component not found', 404);
            }

            if ($request->has('pay_group_id') && (empty($request->pay_group_id) || $request->pay_group_id === 'null' || $request->pay_group_id === 0 || $request->pay_group_id === '0')) {
                $request->merge(['pay_group_id' => null]);
            }

            $validator = Validator::make($request->all(), [
                'pay_group_id'     => 'nullable|integer|exists:pay_groups,id',
                'name'             => 'required|string|max:255',
                'code'             => 'required|string|max:50',
                'type'             => 'required|in:earning,deduction',
                'calculation_type' => 'nullable|string|max:50',
                'is_adhoc'         => 'required',
                'status'           => 'required',
                'description'      => 'nullable|string',
                'default_value'    => 'nullable|max:255',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed.', 422, $validator->errors());
            }

            $calculationType = $request->input('calculation_type') ?: 'fixed';
            $status          = $this->parseBoolean($request->input('status'));
            $isAdhoc         = $this->parseBoolean($request->input('is_adhoc'));

            $companyId = null;
            if (!empty($request->input('pay_group_id'))) {
                $payGroup = PayGroup::find($request->input('pay_group_id'));
                if ($payGroup) {
                    $companyId = $payGroup->company_id;
                }
            }

            $model->update([
                'company_id'       => $companyId,
                'pay_group_id'     => $request->input('pay_group_id') ?: null,
                'name'             => $request->input('name'),
                'code'             => strtoupper($request->input('code')),
                'type'             => $request->input('type'),
                'calculation_type' => $calculationType,
                'default_value'    => $request->input('default_value') ?: null,
                'description'      => $request->input('description') ?: null,
                'status'           => $status,
                'is_adhoc'         => $isAdhoc,
            ]);

            $model->load(['company', 'payGroup']);

            return $this->sendSuccess($this->formatComponent($model), 'Salary component updated successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to update component: ' . $e->getMessage(), 500);
        }
    }

    public function destroyComponent(mixed $salaryComponent): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $salaryComponent instanceof SalaryComponent ? $salaryComponent : SalaryComponent::find($salaryComponent);
            if (!$model) {
                return $this->sendError('Salary component not found', 404);
            }

            $model->delete();

            return $this->sendSuccess(['id' => (int)$model->id], 'Salary component deleted successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to delete component: ' . $e->getMessage(), 500);
        }
    }

    // ==========================================
    // 3. SALARY STRUCTURE SLABS API
    // ==========================================

    public function indexStructures(Request $request): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $query = SalaryStructure::with(['company', 'payGroup', 'items.component']);

            if ($request->filled('pay_group_id')) {
                $query->where('pay_group_id', $request->get('pay_group_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $this->parseBoolean($request->get('status')));
            }
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where('name', 'like', "%{$search}%");
            }

            $sort = $request->get('sort', 'name_asc');
            switch ($sort) {
                case 'name_desc':    $query->orderBy('name', 'desc'); break;
                case 'min_ctc_asc':  $query->orderBy('min_ctc', 'asc'); break;
                case 'min_ctc_desc': $query->orderBy('min_ctc', 'desc'); break;
                case 'max_ctc_asc':  $query->orderBy('max_ctc', 'asc'); break;
                case 'max_ctc_desc': $query->orderBy('max_ctc', 'desc'); break;
                case 'name_asc':
                default: $query->orderBy('name', 'asc'); break;
            }

            $perPage = max(1, min(100, $request->integer('per_page', 10)));
            $structures = $query->paginate($perPage);
            $result = $this->formatPaginated($structures, fn($s) => $this->formatStructure($s, true));

            return $this->sendSuccess($result, 'Salary structures retrieved successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to fetch structures: ' . $e->getMessage(), 500);
        }
    }

    public function showStructure(mixed $salaryStructure): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $salaryStructure instanceof SalaryStructure ? $salaryStructure : SalaryStructure::find($salaryStructure);
            if (!$model) {
                return $this->sendError('Salary structure not found', 404);
            }

            $model->load(['company', 'payGroup', 'items.component']);

            return $this->sendSuccess($this->formatStructure($model, true), 'Salary structure details loaded');
        } catch (Throwable $e) {
            return $this->sendError('Failed to fetch structure: ' . $e->getMessage(), 500);
        }
    }

    public function storeStructure(Request $request): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            if ($request->has('pay_group_id') && (empty($request->pay_group_id) || $request->pay_group_id === 'null' || $request->pay_group_id === 0 || $request->pay_group_id === '0')) {
                $request->merge(['pay_group_id' => null]);
            }

            $validator = Validator::make($request->all(), [
                'name'         => 'required|string|max:255',
                'pay_group_id' => 'nullable|integer|exists:pay_groups,id',
                'min_ctc'      => 'required|numeric|min:0',
                'max_ctc'      => 'required|numeric|gte:min_ctc',
                'status'       => 'required',
                'components'   => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed.', 422, $validator->errors());
            }

            $status     = $this->parseBoolean($request->input('status'));
            $payGroupId = $request->input('pay_group_id') ?: null;
            $companyId  = null;

            if ($payGroupId) {
                $payGroup = PayGroup::find($payGroupId);
                if ($payGroup) {
                    $companyId = $payGroup->company_id;
                }
            }
            if (!$companyId) {
                $companyId = Company::first()?->id ?? 1;
            }

            // Validation for overlapping slabs within the same Pay Group
            $overlapQuery = SalaryStructure::where('company_id', $companyId);
            if ($payGroupId) {
                $overlapQuery->where('pay_group_id', $payGroupId);
            } else {
                $overlapQuery->whereNull('pay_group_id');
            }

            $minCtc = (float)$request->input('min_ctc');
            $maxCtc = (float)$request->input('max_ctc');

            $overlap = $overlapQuery->where(function ($query) use ($minCtc, $maxCtc) {
                $query->whereBetween('min_ctc', [$minCtc, $maxCtc])
                    ->orWhereBetween('max_ctc', [$minCtc, $maxCtc])
                    ->orWhere(function ($q) use ($minCtc, $maxCtc) {
                        $q->where('min_ctc', '<=', $minCtc)
                          ->where('max_ctc', '>=', $maxCtc);
                    });
            })->exists();

            if ($overlap) {
                return $this->sendError('Salary Structure ranges cannot overlap with existing slabs.', 422);
            }

            $structure = SalaryStructure::create([
                'company_id'   => $companyId,
                'pay_group_id' => $payGroupId,
                'name'         => $request->input('name'),
                'min_ctc'      => $minCtc,
                'max_ctc'      => $maxCtc,
                'status'       => $status,
            ]);

            // Process component items
            if ($request->has('components') && is_array($request->components)) {
                foreach ($request->components as $componentId => $componentData) {
                    $calcType = $componentData['calculation_type'] ?? null;
                    if ($calcType && $calcType !== 'not_included') {
                        $value = $componentData['value'] ?? 0.00;
                        if ($calcType === 'balancing') {
                            $value = 0.00;
                        }

                        $sortOrder = 2;
                        $comp = SalaryComponent::find($componentId);
                        if ($comp) {
                            if (strtolower($comp->code) === 'basic') {
                                $sortOrder = 1;
                            } elseif ($calcType === 'percentage_of_basic') {
                                $sortOrder = 3;
                            } elseif ($calcType === 'balancing') {
                                $sortOrder = 5;
                            }
                        }

                        SalaryStructureItem::create([
                            'salary_structure_id' => $structure->id,
                            'salary_component_id' => $componentId,
                            'calculation_type'   => $calcType,
                            'value'              => $value,
                            'sort_order'         => $sortOrder,
                        ]);
                    }
                }
            }

            $structure->load(['company', 'payGroup', 'items.component']);

            return $this->sendSuccess($this->formatStructure($structure, true), 'Salary structure slab created successfully', 201);
        } catch (Throwable $e) {
            return $this->sendError('Failed to create structure: ' . $e->getMessage(), 500);
        }
    }

    public function updateStructure(Request $request, mixed $salaryStructure): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $salaryStructure instanceof SalaryStructure ? $salaryStructure : SalaryStructure::find($salaryStructure);
            if (!$model) {
                return $this->sendError('Salary structure not found', 404);
            }

            if ($request->has('pay_group_id') && (empty($request->pay_group_id) || $request->pay_group_id === 'null' || $request->pay_group_id === 0 || $request->pay_group_id === '0')) {
                $request->merge(['pay_group_id' => null]);
            }

            $validator = Validator::make($request->all(), [
                'name'         => 'required|string|max:255',
                'pay_group_id' => 'nullable|integer|exists:pay_groups,id',
                'min_ctc'      => 'required|numeric|min:0',
                'max_ctc'      => 'required|numeric|gte:min_ctc',
                'status'       => 'required',
                'components'   => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed.', 422, $validator->errors());
            }

            $status     = $this->parseBoolean($request->input('status'));
            $payGroupId = $request->input('pay_group_id') ?: null;
            $companyId  = null;

            if ($payGroupId) {
                $payGroup = PayGroup::find($payGroupId);
                if ($payGroup) {
                    $companyId = $payGroup->company_id;
                }
            }
            if (!$companyId) {
                $companyId = Company::first()?->id ?? 1;
            }

            $minCtc = (float)$request->input('min_ctc');
            $maxCtc = (float)$request->input('max_ctc');

            // Validation for overlapping slabs (exclude self)
            $overlapQuery = SalaryStructure::where('company_id', $companyId)
                ->where('id', '!=', $model->id);

            if ($payGroupId) {
                $overlapQuery->where('pay_group_id', $payGroupId);
            } else {
                $overlapQuery->whereNull('pay_group_id');
            }

            $overlap = $overlapQuery->where(function ($query) use ($minCtc, $maxCtc) {
                $query->whereBetween('min_ctc', [$minCtc, $maxCtc])
                    ->orWhereBetween('max_ctc', [$minCtc, $maxCtc])
                    ->orWhere(function ($q) use ($minCtc, $maxCtc) {
                        $q->where('min_ctc', '<=', $minCtc)
                          ->where('max_ctc', '>=', $maxCtc);
                    });
            })->exists();

            if ($overlap) {
                return $this->sendError('Salary Structure ranges cannot overlap with existing slabs.', 422);
            }

            $model->update([
                'company_id'   => $companyId,
                'pay_group_id' => $payGroupId,
                'name'         => $request->input('name'),
                'min_ctc'      => $minCtc,
                'max_ctc'      => $maxCtc,
                'status'       => $status,
            ]);

            // Recreate component items
            $model->items()->delete();

            if ($request->has('components') && is_array($request->components)) {
                foreach ($request->components as $componentId => $componentData) {
                    $calcType = $componentData['calculation_type'] ?? null;
                    if ($calcType && $calcType !== 'not_included') {
                        $value = $componentData['value'] ?? 0.00;
                        if ($calcType === 'balancing') {
                            $value = 0.00;
                        }

                        $sortOrder = 2;
                        $comp = SalaryComponent::find($componentId);
                        if ($comp) {
                            if (strtolower($comp->code) === 'basic') {
                                $sortOrder = 1;
                            } elseif ($calcType === 'percentage_of_basic') {
                                $sortOrder = 3;
                            } elseif ($calcType === 'balancing') {
                                $sortOrder = 5;
                            }
                        }

                        SalaryStructureItem::create([
                            'salary_structure_id' => $model->id,
                            'salary_component_id' => $componentId,
                            'calculation_type'   => $calcType,
                            'value'              => $value,
                            'sort_order'         => $sortOrder,
                        ]);
                    }
                }
            }

            $model->load(['company', 'payGroup', 'items.component']);

            return $this->sendSuccess($this->formatStructure($model, true), 'Salary structure slab updated successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to update structure: ' . $e->getMessage(), 500);
        }
    }

    public function destroyStructure(mixed $salaryStructure): JsonResponse
    {
        try {
            if ($authError = $this->authorizeUser()) {
                return $authError;
            }

            $model = $salaryStructure instanceof SalaryStructure ? $salaryStructure : SalaryStructure::find($salaryStructure);
            if (!$model) {
                return $this->sendError('Salary structure not found', 404);
            }

            $model->items()->delete();
            $model->delete();

            return $this->sendSuccess(['id' => (int)$model->id], 'Salary structure slab deleted successfully');
        } catch (Throwable $e) {
            return $this->sendError('Failed to delete structure: ' . $e->getMessage(), 500);
        }
    }
}
