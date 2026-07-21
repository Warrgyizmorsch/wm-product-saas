<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrgApiController extends Controller
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
            // Check if HTTP Basic Auth headers (username/password) were provided in Postman/API client
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                // Attempt authentication using HTTP Basic Auth credentials
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
     * GET /api/hrms/org/summary
     * Get organization structure summary & dropdown reference lists.
     */
    public function summary(): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess([
            'companies_count'      => Company::count(),
            'business_units_count' => BusinessUnit::count(),
            'branches_count'       => Branch::count(),
            'departments_count'    => Department::count(),
            'designations_count'   => Designation::count(),
            'companies'            => Company::orderBy('company_name')->get(),
            'business_units'       => BusinessUnit::orderBy('name')->get(),
            'branches'             => Branch::orderBy('name')->get(),
            'departments'          => Department::orderBy('name')->get(),
        ], 'Organization summary retrieved successfully');
    }

    // ==========================================
    // 1. COMPANIES (LEGAL ENTITIES) API
    // ==========================================

    public function indexCompanies(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $search = trim((string) $request->string('search'));
        $status = $request->filled('status') ? $request->string('status')->value() : null;
        $sort   = $request->string('sort')->value() ?: 'name_asc';

        $query = Company::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('legal_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status === '1' || $status === 'true');
        }

        switch ($sort) {
            case 'name_desc': $query->orderBy('company_name', 'desc'); break;
            case 'legal_asc': $query->orderBy('legal_name', 'asc'); break;
            case 'legal_desc': $query->orderBy('legal_name', 'desc'); break;
            case 'name_asc':
            default: $query->orderBy('company_name', 'asc'); break;
        }

        $companies = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($companies, 'Companies retrieved successfully');
    }

    public function showCompany(Company $company): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($company, 'Company details loaded');
    }

    public function storeCompany(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_name'        => 'required|max:255',
            'legal_name'          => 'required|max:255',
            'gst_number'          => 'nullable|max:50',
            'pan_number'          => 'nullable|max:50',
            'cin_number'          => 'nullable|max:100',
            'registration_number' => 'nullable|max:100',
            'email'               => 'nullable|email',
            'phone'               => 'nullable|max:20',
            'website'             => 'nullable',
            'address'             => 'nullable',
            'city'                => 'nullable|max:100',
            'state'               => 'nullable|max:100',
            'country'             => 'nullable|max:100',
            'postal_code'         => 'nullable|max:20',
            'currency'            => 'required|string|max:255',
            'time_zone'           => 'required|string|max:100',
            'status'              => 'required',
            'logo'                => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $logo = null;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store('legal_entities', 'public');
        }

        $currency = null;
        if ($request->currency) {
            $parts = explode('-', $request->currency);
            $currency = trim($parts[0]);
            $currency = substr($currency, 0, 10);
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $company = Company::create([
            'company_name'        => $validated['company_name'],
            'legal_name'          => $validated['legal_name'],
            'gst_number'          => $validated['gst_number'] ?? null,
            'pan_number'          => $validated['pan_number'] ?? null,
            'cin_number'          => $validated['cin_number'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'email'               => $validated['email'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'website'             => $validated['website'] ?? null,
            'address'             => $validated['address'] ?? null,
            'city'                => $validated['city'] ?? null,
            'state'               => $validated['state'] ?? null,
            'country'             => $validated['country'] ?? null,
            'postal_code'         => $validated['postal_code'] ?? null,
            'currency'            => $currency,
            'timezone'            => $validated['time_zone'],
            'status'              => $status,
            'logo'                => $logo,
        ]);

        return $this->sendSuccess($company, 'Company created successfully', 201);
    }

    public function updateCompany(Request $request, Company $company): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_name'        => 'required|max:255',
            'legal_name'          => 'required|max:255',
            'gst_number'          => 'nullable|max:50',
            'pan_number'          => 'nullable|max:50',
            'cin_number'          => 'nullable|max:100',
            'registration_number' => 'nullable|max:100',
            'email'               => 'nullable|email',
            'phone'               => 'nullable|max:20',
            'website'             => 'nullable',
            'address'             => 'nullable',
            'city'                => 'nullable|max:100',
            'state'               => 'nullable|max:100',
            'country'             => 'nullable|max:100',
            'postal_code'         => 'nullable|max:20',
            'currency'            => 'required|string|max:255',
            'time_zone'           => 'required|string|max:100',
            'status'              => 'required',
            'logo'                => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $logo = $company->logo;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store('legal_entities', 'public');
        }

        $currency = null;
        if ($request->currency) {
            $parts = explode('-', $request->currency);
            $currency = trim($parts[0]);
            $currency = substr($currency, 0, 10);
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $company->update([
            'company_name'        => $validated['company_name'],
            'legal_name'          => $validated['legal_name'],
            'gst_number'          => $validated['gst_number'] ?? null,
            'pan_number'          => $validated['pan_number'] ?? null,
            'cin_number'          => $validated['cin_number'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'email'               => $validated['email'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'website'             => $validated['website'] ?? null,
            'address'             => $validated['address'] ?? null,
            'city'                => $validated['city'] ?? null,
            'state'               => $validated['state'] ?? null,
            'country'             => $validated['country'] ?? null,
            'postal_code'         => $validated['postal_code'] ?? null,
            'currency'            => $currency,
            'timezone'            => $validated['time_zone'],
            'status'              => $status,
            'logo'                => $logo,
        ]);

        return $this->sendSuccess($company, 'Company updated successfully');
    }

    public function destroyCompany(Company $company): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        Employee::withTrashed()->where('company_id', $company->id)->update(['company_id' => null]);
        $departmentIds = Department::where('company_id', $company->id)->pluck('id');
        
        Employee::withTrashed()->whereIn('department_id', $departmentIds)->update([
            'department_id' => null,
            'designation_id' => null
        ]);

        Designation::whereIn('department_id', $departmentIds)->delete();
        Department::where('company_id', $company->id)->delete();
        Branch::where('company_id', $company->id)->delete();
        BusinessUnit::where('company_id', $company->id)->delete();

        $company->delete();

        return $this->sendSuccess(null, 'Company deleted successfully');
    }

    // ==========================================
    // 2. BUSINESS UNITS API
    // ==========================================

    public function indexBusinessUnits(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $search    = trim((string) $request->string('search'));
        $companyId = $request->integer('company_id') ?: null;
        $status    = $request->filled('status') ? $request->string('status')->value() : null;
        $sort      = $request->string('sort')->value() ?: 'name_asc';

        $query = BusinessUnit::with(['company', 'head']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $status === '1' || $status === 'true');
        }

        switch ($sort) {
            case 'name_desc': $query->orderBy('name', 'desc'); break;
            case 'code_asc':  $query->orderBy('code', 'asc'); break;
            case 'code_desc': $query->orderBy('code', 'desc'); break;
            case 'name_asc':
            default: $query->orderBy('name', 'asc'); break;
        }

        $businessUnits = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($businessUnits, 'Business units retrieved successfully');
    }

    public function showBusinessUnit(BusinessUnit $businessUnit): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($businessUnit->load(['company', 'head']), 'Business unit details loaded');
    }

    public function storeBusinessUnit(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'       => 'required|exists:companies,id',
            'name'             => 'required|max:255',
            'code'             => 'required|max:50',
            'description'      => 'nullable',
            'head_employee_id' => 'nullable|exists:employees,id',
            'status'           => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $businessUnit = BusinessUnit::create([
            'company_id'       => $validated['company_id'],
            'name'             => $validated['name'],
            'code'             => $validated['code'],
            'description'      => $validated['description'] ?? null,
            'head_employee_id' => $validated['head_employee_id'] ?? null,
            'status'           => $status,
        ]);

        return $this->sendSuccess($businessUnit, 'Business unit created successfully', 201);
    }

    public function updateBusinessUnit(Request $request, BusinessUnit $businessUnit): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'       => 'required|exists:companies,id',
            'name'             => 'required|max:255',
            'code'             => 'required|max:50',
            'description'      => 'nullable',
            'head_employee_id' => 'nullable|exists:employees,id',
            'status'           => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $businessUnit->update([
            'company_id'       => $validated['company_id'],
            'name'             => $validated['name'],
            'code'             => $validated['code'],
            'description'      => $validated['description'] ?? null,
            'head_employee_id' => $validated['head_employee_id'] ?? null,
            'status'           => $status,
        ]);

        return $this->sendSuccess($businessUnit, 'Business unit updated successfully');
    }

    public function destroyBusinessUnit(BusinessUnit $businessUnit): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        Employee::withTrashed()->where('business_unit_id', $businessUnit->id)->update(['business_unit_id' => null]);
        $departmentIds = Department::where('business_unit_id', $businessUnit->id)->pluck('id');
        
        Employee::withTrashed()->whereIn('department_id', $departmentIds)->update([
            'department_id' => null,
            'designation_id' => null
        ]);

        Designation::whereIn('department_id', $departmentIds)->delete();
        Department::where('business_unit_id', $businessUnit->id)->delete();
        Branch::where('business_unit_id', $businessUnit->id)->delete();

        $businessUnit->delete();

        return $this->sendSuccess(null, 'Business unit deleted successfully');
    }

    // ==========================================
    // 3. BRANCHES API
    // ==========================================

    public function indexBranches(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $search         = trim((string) $request->string('search'));
        $companyId      = $request->integer('company_id') ?: null;
        $businessUnitId = $request->integer('business_unit_id') ?: null;
        $status         = $request->filled('status') ? $request->string('status')->value() : null;
        $sort           = $request->string('sort')->value() ?: 'name_asc';

        $query = Branch::with(['businessUnit', 'company', 'manager']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($businessUnitId) {
            $query->where('business_unit_id', $businessUnitId);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $status === '1' || $status === 'true');
        }

        switch ($sort) {
            case 'name_desc': $query->orderBy('name', 'desc'); break;
            case 'code_asc':  $query->orderBy('code', 'asc'); break;
            case 'code_desc': $query->orderBy('code', 'desc'); break;
            case 'name_asc':
            default: $query->orderBy('name', 'asc'); break;
        }

        $branches = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($branches, 'Branches retrieved successfully');
    }

    public function showBranch(Branch $branch): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($branch->load(['businessUnit', 'company', 'manager']), 'Branch details loaded');
    }

    public function storeBranch(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'          => 'required_without:business_unit_id|nullable|exists:companies,id',
            'business_unit_id'    => 'required_without:company_id|nullable|exists:business_units,id',
            'name'                => 'required|max:255',
            'code'                => 'required|max:50',
            'manager_employee_id' => 'nullable|exists:employees,id',
            'phone'               => 'nullable|max:20',
            'email'               => 'nullable|email',
            'address'             => 'nullable',
            'city'                => 'nullable|max:100',
            'state'               => 'nullable|max:100',
            'country'             => 'nullable|max:100',
            'postal_code'         => 'nullable|max:20',
            'status'              => 'required',
        ]);

        $companyId = $validated['company_id'] ?? null;
        if (!empty($validated['business_unit_id'])) {
            $bu = BusinessUnit::find($validated['business_unit_id']);
            if ($bu) {
                $companyId = $bu->company_id;
            }
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $branch = Branch::create([
            'company_id'          => $companyId,
            'business_unit_id'    => $validated['business_unit_id'] ?? null,
            'name'                => $validated['name'],
            'code'                => $validated['code'],
            'manager_employee_id' => $validated['manager_employee_id'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'email'               => $validated['email'] ?? null,
            'address'             => $validated['address'] ?? null,
            'city'                => $validated['city'] ?? null,
            'state'               => $validated['state'] ?? null,
            'country'             => $validated['country'] ?? null,
            'postal_code'         => $validated['postal_code'] ?? null,
            'status'              => $status,
        ]);

        return $this->sendSuccess($branch, 'Branch created successfully', 201);
    }

    public function updateBranch(Request $request, Branch $branch): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'          => 'required_without:business_unit_id|nullable|exists:companies,id',
            'business_unit_id'    => 'required_without:company_id|nullable|exists:business_units,id',
            'name'                => 'required|max:255',
            'code'                => 'required|max:50',
            'manager_employee_id' => 'nullable|exists:employees,id',
            'phone'               => 'nullable|max:20',
            'email'               => 'nullable|email',
            'address'             => 'nullable',
            'city'                => 'nullable|max:100',
            'state'               => 'nullable|max:100',
            'country'             => 'nullable|max:100',
            'postal_code'         => 'nullable|max:20',
            'status'              => 'required',
        ]);

        $companyId = $validated['company_id'] ?? null;
        if (!empty($validated['business_unit_id'])) {
            $bu = BusinessUnit::find($validated['business_unit_id']);
            if ($bu) {
                $companyId = $bu->company_id;
            }
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $branch->update([
            'company_id'          => $companyId,
            'business_unit_id'    => $validated['business_unit_id'] ?? null,
            'name'                => $validated['name'],
            'code'                => $validated['code'],
            'manager_employee_id' => $validated['manager_employee_id'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'email'               => $validated['email'] ?? null,
            'address'             => $validated['address'] ?? null,
            'city'                => $validated['city'] ?? null,
            'state'               => $validated['state'] ?? null,
            'country'             => $validated['country'] ?? null,
            'postal_code'         => $validated['postal_code'] ?? null,
            'status'              => $status,
        ]);

        return $this->sendSuccess($branch, 'Branch updated successfully');
    }

    public function destroyBranch(Branch $branch): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        Employee::withTrashed()->where('branch_id', $branch->id)->update(['branch_id' => null]);
        $departmentIds = Department::where('branch_id', $branch->id)->pluck('id');
        
        Employee::withTrashed()->whereIn('department_id', $departmentIds)->update([
            'department_id' => null,
            'designation_id' => null
        ]);

        Designation::whereIn('department_id', $departmentIds)->delete();
        Department::where('branch_id', $branch->id)->delete();

        $branch->delete();

        return $this->sendSuccess(null, 'Branch deleted successfully');
    }

    // ==========================================
    // 4. DEPARTMENTS API
    // ==========================================

    public function indexDepartments(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $search         = trim((string) $request->string('search'));
        $companyId      = $request->integer('company_id') ?: null;
        $businessUnitId = $request->integer('business_unit_id') ?: null;
        $branchId       = $request->integer('branch_id') ?: null;
        $status         = $request->filled('status') ? $request->string('status')->value() : null;
        $sort           = $request->string('sort')->value() ?: 'name_asc';

        $query = Department::with(['branch', 'company', 'businessUnit', 'head']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($businessUnitId) {
            $query->where('business_unit_id', $businessUnitId);
        }
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $status === '1' || $status === 'true');
        }

        switch ($sort) {
            case 'name_desc': $query->orderBy('name', 'desc'); break;
            case 'code_asc':  $query->orderBy('code', 'asc'); break;
            case 'code_desc': $query->orderBy('code', 'desc'); break;
            case 'name_asc':
            default: $query->orderBy('name', 'asc'); break;
        }

        $departments = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($departments, 'Departments retrieved successfully');
    }

    public function showDepartment(Department $department): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($department->load(['branch', 'company', 'businessUnit', 'head']), 'Department details loaded');
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'       => 'required_without_all:branch_id,business_unit_id|nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'name'             => 'required|max:255',
            'code'             => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'description'      => 'nullable',
            'status'           => 'required',
        ]);

        $companyId      = $validated['company_id'] ?? null;
        $businessUnitId = $validated['business_unit_id'] ?? null;
        $branchId       = $validated['branch_id'] ?? null;

        if ($branchId) {
            $branch = Branch::find($branchId);
            if ($branch) {
                $businessUnitId = $branch->business_unit_id;
                $companyId      = $branch->company_id;
            }
        } elseif ($businessUnitId) {
            $bu = BusinessUnit::find($businessUnitId);
            if ($bu) {
                $companyId = $bu->company_id;
            }
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $department = Department::create([
            'company_id'       => $companyId,
            'business_unit_id' => $businessUnitId ?: null,
            'branch_id'        => $branchId ?: null,
            'name'             => $validated['name'],
            'code'             => $validated['code'],
            'head_employee_id' => $validated['head_employee_id'] ?? null,
            'description'      => $validated['description'] ?? null,
            'status'           => $status,
        ]);

        return $this->sendSuccess($department, 'Department created successfully', 201);
    }

    public function updateDepartment(Request $request, Department $department): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'company_id'       => 'required_without_all:branch_id,business_unit_id|nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id'        => 'nullable|exists:branches,id',
            'name'             => 'required|max:255',
            'code'             => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'description'      => 'nullable',
            'status'           => 'required',
        ]);

        $companyId      = $validated['company_id'] ?? null;
        $businessUnitId = $validated['business_unit_id'] ?? null;
        $branchId       = $validated['branch_id'] ?? null;

        if ($branchId) {
            $branch = Branch::find($branchId);
            if ($branch) {
                $businessUnitId = $branch->business_unit_id;
                $companyId      = $branch->company_id;
            }
        } elseif ($businessUnitId) {
            $bu = BusinessUnit::find($businessUnitId);
            if ($bu) {
                $companyId = $bu->company_id;
            }
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $department->update([
            'company_id'       => $companyId,
            'business_unit_id' => $businessUnitId ?: null,
            'branch_id'        => $branchId ?: null,
            'name'             => $validated['name'],
            'code'             => $validated['code'],
            'head_employee_id' => $validated['head_employee_id'] ?? null,
            'description'      => $validated['description'] ?? null,
            'status'           => $status,
        ]);

        return $this->sendSuccess($department, 'Department updated successfully');
    }

    public function destroyDepartment(Department $department): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        Employee::withTrashed()->where('department_id', $department->id)->update([
            'department_id'  => null,
            'designation_id' => null
        ]);

        Designation::where('department_id', $department->id)->delete();
        $department->delete();

        return $this->sendSuccess(null, 'Department deleted successfully');
    }

    // ==========================================
    // 5. DESIGNATIONS API
    // ==========================================

    public function indexDesignations(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $search       = trim((string) $request->string('search'));
        $departmentId = $request->integer('department_id') ?: null;
        $status       = $request->filled('status') ? $request->string('status')->value() : null;
        $sort         = $request->string('sort')->value() ?: 'name_asc';

        $query = Designation::with(['department']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('level', 'like', "%{$search}%");
            });
        }
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $status === '1' || $status === 'true');
        }

        switch ($sort) {
            case 'name_desc':  $query->orderBy('name', 'desc'); break;
            case 'level_asc':  $query->orderBy('level', 'asc'); break;
            case 'level_desc': $query->orderBy('level', 'desc'); break;
            case 'name_asc':
            default: $query->orderBy('name', 'asc'); break;
        }

        $designations = $query->paginate($request->integer('per_page', 10));

        return $this->sendSuccess($designations, 'Designations retrieved successfully');
    }

    public function showDesignation(Designation $designation): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($designation->load(['department']), 'Designation details loaded');
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'name'          => 'required|max:255',
            'level'         => 'nullable|max:50',
            'description'   => 'nullable',
            'status'        => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $designation = Designation::create([
            'department_id' => $validated['department_id'],
            'name'          => $validated['name'],
            'level'         => $validated['level'] ?? null,
            'description'   => $validated['description'] ?? null,
            'status'        => $status,
        ]);

        return $this->sendSuccess($designation, 'Designation created successfully', 201);
    }

    public function updateDesignation(Request $request, Designation $designation): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'name'          => 'required|max:255',
            'level'         => 'nullable|max:50',
            'description'   => 'nullable',
            'status'        => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $designation->update([
            'department_id' => $validated['department_id'],
            'name'          => $validated['name'],
            'level'         => $validated['level'] ?? null,
            'description'   => $validated['description'] ?? null,
            'status'        => $status,
        ]);

        return $this->sendSuccess($designation, 'Designation updated successfully');
    }

    public function destroyDesignation(Designation $designation): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        Employee::withTrashed()->where('designation_id', $designation->id)->update(['designation_id' => null]);
        $designation->delete();

        return $this->sendSuccess(null, 'Designation deleted successfully');
    }
}
