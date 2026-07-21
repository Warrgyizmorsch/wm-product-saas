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

        if (!auth()->user()->hasHrPermission('hr.settings.manage')) {
            return $this->sendError('Unauthorized access. Your user role does not have hr.settings.manage permission.', 403);
        }

        return null;
    }

    /**
     * GET /api/hrms/salary-structure/summary
     * Get summary metrics & pay group lists.
     */
    public function summary(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $payGroups = PayGroup::with(['company'])->get();
        $selectedPayGroupId = $request->get('pay_group_id');
        $selectedPayGroup = $selectedPayGroupId ? PayGroup::with(['company'])->find($selectedPayGroupId) : $payGroups->first();

        return $this->sendSuccess([
            'pay_groups_count'       => PayGroup::count(),
            'components_count'       => SalaryComponent::count(),
            'structures_count'       => SalaryStructure::count(),
            'companies'              => Company::orderBy('company_name')->get(),
            'pay_groups'             => $payGroups,
            'selected_pay_group'     => $selectedPayGroup,
        ], 'Salary structure summary loaded successfully');
    }

    // ==========================================
    // 1. PAY GROUPS API
    // ==========================================

    public function indexPayGroups(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = PayGroup::with(['company']);

        if ($request->filled('status')) {
            $query->where('status', $request->get('status') === '1' || $request->get('status') === 'true');
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $payGroups = $query->orderBy('name', 'asc')->get();

        return $this->sendSuccess($payGroups, 'Pay groups retrieved successfully');
    }

    public function showPayGroup(PayGroup $payGroup): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($payGroup->load(['company', 'components', 'structures']), 'Pay group details loaded');
    }

    public function storePayGroup(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name'        => 'required|max:255',
            'company_id'  => 'nullable|integer|exists:companies,id',
            'description' => 'nullable',
            'status'      => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $payGroup = PayGroup::create([
            'company_id'  => $validated['company_id'] ?? null,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status'      => $status,
        ]);

        return $this->sendSuccess($payGroup, 'Pay group created successfully', 201);
    }

    public function updatePayGroup(Request $request, PayGroup $payGroup): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name'        => 'required|max:255',
            'company_id'  => 'nullable|integer|exists:companies,id',
            'description' => 'nullable',
            'status'      => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $payGroup->update([
            'company_id'  => $validated['company_id'] ?? null,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status'      => $status,
        ]);

        return $this->sendSuccess($payGroup, 'Pay group updated successfully');
    }

    public function destroyPayGroup(PayGroup $payGroup): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $payGroup->delete();

        return $this->sendSuccess(null, 'Pay group deleted successfully');
    }

    // ==========================================
    // 2. SALARY COMPONENTS API
    // ==========================================

    public function indexComponents(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = SalaryComponent::with(['company', 'payGroup']);

        if ($request->filled('pay_group_id')) {
            $query->where('pay_group_id', $request->get('pay_group_id'));
        }
        if ($request->filled('is_adhoc')) {
            $query->where('is_adhoc', $request->get('is_adhoc') === '1' || $request->get('is_adhoc') === 'true');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status') === '1' || $request->get('status') === 'true');
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

        $components = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($components, 'Salary components retrieved successfully');
    }

    public function showComponent(SalaryComponent $salaryComponent): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($salaryComponent->load(['company', 'payGroup']), 'Salary component details loaded');
    }

    public function storeComponent(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name'             => 'required|max:255',
            'code'             => 'required|max:50',
            'type'             => 'required',
            'calculation_type' => 'required',
            'default_value'    => 'nullable|max:255',
            'description'      => 'nullable',
            'pay_group_id'     => 'required|integer|exists:pay_groups,id',
            'status'           => 'required',
            'is_adhoc'         => 'nullable',
        ]);

        $status  = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);
        $isAdhoc = $request->get('is_adhoc', 0) == 1 || $request->get('is_adhoc') === 'true';

        $payGroup = PayGroup::findOrFail($validated['pay_group_id']);

        $component = SalaryComponent::create([
            'company_id'       => $payGroup->company_id,
            'pay_group_id'     => $validated['pay_group_id'],
            'name'             => $validated['name'],
            'code'             => $validated['code'],
            'type'             => $validated['type'],
            'calculation_type' => $validated['calculation_type'],
            'default_value'    => $validated['default_value'] ?? null,
            'description'      => $validated['description'] ?? null,
            'status'           => $status,
            'is_adhoc'         => $isAdhoc,
        ]);

        return $this->sendSuccess($component, 'Salary component created successfully', 201);
    }

    public function updateComponent(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name'             => 'required|max:255',
            'code'             => 'required|max:50',
            'type'             => 'required',
            'calculation_type' => 'required',
            'default_value'    => 'nullable|max:255',
            'description'      => 'nullable',
            'pay_group_id'     => 'required|integer|exists:pay_groups,id',
            'status'           => 'required',
            'is_adhoc'         => 'nullable',
        ]);

        $status  = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);
        $isAdhoc = $request->get('is_adhoc', 0) == 1 || $request->get('is_adhoc') === 'true';

        $payGroup = PayGroup::findOrFail($validated['pay_group_id']);

        $salaryComponent->update([
            'company_id'       => $payGroup->company_id,
            'pay_group_id'     => $validated['pay_group_id'],
            'name'             => $validated['name'],
            'code'             => $validated['code'],
            'type'             => $validated['type'],
            'calculation_type' => $validated['calculation_type'],
            'default_value'    => $validated['default_value'] ?? null,
            'description'      => $validated['description'] ?? null,
            'status'           => $status,
            'is_adhoc'         => $isAdhoc,
        ]);

        return $this->sendSuccess($salaryComponent, 'Salary component updated successfully');
    }

    public function destroyComponent(SalaryComponent $salaryComponent): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $salaryComponent->delete();

        return $this->sendSuccess(null, 'Salary component deleted successfully');
    }

    // ==========================================
    // 3. SALARY STRUCTURE SLABS API
    // ==========================================

    public function indexStructures(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $query = SalaryStructure::with(['company', 'payGroup', 'items.component']);

        if ($request->filled('pay_group_id')) {
            $query->where('pay_group_id', $request->get('pay_group_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->get('status') === '1' || $request->get('status') === 'true');
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

        $structures = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($structures, 'Salary structures retrieved successfully');
    }

    public function showStructure(SalaryStructure $salaryStructure): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($salaryStructure->load(['company', 'payGroup', 'items.component']), 'Salary structure details loaded');
    }

    public function storeStructure(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name'         => 'required|max:255',
            'pay_group_id' => 'required|integer|exists:pay_groups,id',
            'min_ctc'      => 'required|numeric|min:0',
            'max_ctc'      => 'required|numeric|gte:min_ctc',
            'status'       => 'required',
            'components'   => 'nullable|array',
        ]);

        $status   = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);
        $payGroup = PayGroup::findOrFail($validated['pay_group_id']);
        $companyId = $payGroup->company_id;

        // Validation for overlapping slabs within the same Pay Group
        $overlap = SalaryStructure::where('company_id', $companyId)
            ->where('pay_group_id', $validated['pay_group_id'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('min_ctc', [$validated['min_ctc'], $validated['max_ctc']])
                    ->orWhereBetween('max_ctc', [$validated['min_ctc'], $validated['max_ctc']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('min_ctc', '<=', $validated['min_ctc'])
                            ->where('max_ctc', '>=', $validated['max_ctc']);
                    });
            })
            ->exists();

        if ($overlap) {
            return $this->sendError('Salary Structure ranges cannot overlap with existing slabs in this Pay Group.', 422);
        }

        $structure = SalaryStructure::create([
            'company_id'   => $companyId,
            'pay_group_id' => $validated['pay_group_id'],
            'name'         => $validated['name'],
            'min_ctc'      => $validated['min_ctc'],
            'max_ctc'      => $validated['max_ctc'],
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

        return $this->sendSuccess($structure->load('items.component'), 'Salary structure slab created successfully', 201);
    }

    public function updateStructure(Request $request, SalaryStructure $salaryStructure): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name'         => 'required|max:255',
            'pay_group_id' => 'required|integer|exists:pay_groups,id',
            'min_ctc'      => 'required|numeric|min:0',
            'max_ctc'      => 'required|numeric|gte:min_ctc',
            'status'       => 'required',
            'components'   => 'nullable|array',
        ]);

        $status   = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);
        $payGroup = PayGroup::findOrFail($validated['pay_group_id']);
        $companyId = $payGroup->company_id;

        // Validation for overlapping slabs (exclude self)
        $overlap = SalaryStructure::where('company_id', $companyId)
            ->where('pay_group_id', $validated['pay_group_id'])
            ->where('id', '!=', $salaryStructure->id)
            ->where(function ($query) use ($validated) {
                $query->whereBetween('min_ctc', [$validated['min_ctc'], $validated['max_ctc']])
                    ->orWhereBetween('max_ctc', [$validated['min_ctc'], $validated['max_ctc']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('min_ctc', '<=', $validated['min_ctc'])
                            ->where('max_ctc', '>=', $validated['max_ctc']);
                    });
            })
            ->exists();

        if ($overlap) {
            return $this->sendError('Salary Structure ranges cannot overlap with existing slabs in this Pay Group.', 422);
        }

        $salaryStructure->update([
            'company_id'   => $companyId,
            'pay_group_id' => $validated['pay_group_id'],
            'name'         => $validated['name'],
            'min_ctc'      => $validated['min_ctc'],
            'max_ctc'      => $validated['max_ctc'],
            'status'       => $status,
        ]);

        // Recreate component items
        $salaryStructure->items()->delete();

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
                        'salary_structure_id' => $salaryStructure->id,
                        'salary_component_id' => $componentId,
                        'calculation_type'   => $calcType,
                        'value'              => $value,
                        'sort_order'         => $sortOrder,
                    ]);
                }
            }
        }

        return $this->sendSuccess($salaryStructure->load('items.component'), 'Salary structure slab updated successfully');
    }

    public function destroyStructure(SalaryStructure $salaryStructure): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $salaryStructure->items()->delete();
        $salaryStructure->delete();

        return $this->sendSuccess(null, 'Salary structure slab deleted successfully');
    }
}
