<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Organization;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\SalaryComponent;
use Illuminate\Http\Request;

class OrgController extends Controller {
    public function index(Request $request) {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Programmatically run schema updates if they haven't been applied yet
        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('branches', 'company_id')) {
                \Illuminate\Support\Facades\Schema::table('branches', function (\Illuminate\Database\Schema\Blueprint $table) {
                    try {
                        $table->dropForeign('branches_business_unit_id_foreign');
                    } catch (\Exception $ex) {
                        // ignore if doesn't exist
                    }
                    $table->unsignedBigInteger('business_unit_id')->nullable()->change();
                    $table->foreign('business_unit_id')->references('id')->on('branches')->nullOnDelete();

                    $table->unsignedBigInteger('company_id')->nullable()->after('business_unit_id');
                    $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
                });
            }
            if (!\Illuminate\Support\Facades\Schema::hasColumn('departments', 'company_id')) {
                \Illuminate\Support\Facades\Schema::table('departments', function (\Illuminate\Database\Schema\Blueprint $table) {
                    try {
                        $table->dropUnique('departments_branch_id_code_unique');
                    } catch (\Exception $ex) {
                        // ignore if unique constraint name is different
                    }
                    try {
                        $table->dropForeign('departments_branch_id_foreign');
                    } catch (\Exception $ex) {
                        // ignore if doesn't exist
                    }
                    $table->unsignedBigInteger('branch_id')->nullable()->change();
                    $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

                    $table->unsignedBigInteger('company_id')->nullable()->after('branch_id');
                    $table->unsignedBigInteger('business_unit_id')->nullable()->after('company_id');
                    $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
                    $table->foreign('business_unit_id')->references('id')->on('business_units')->nullOnDelete();
                });
            }

        } catch (\Exception $e) {
            // Silently capture any setup errors
        }

        $activeTab = $request->string('tab')->value() ?: 'legal-entities';
        $co_pageName = ($activeTab === 'legal-entities') ? 'page' : 'co_page';
        $bu_pageName = ($activeTab === 'business-units') ? 'page' : 'bu_page';
        $br_pageName = ($activeTab === 'branches') ? 'page' : 'br_page';
        $dp_pageName = ($activeTab === 'departments') ? 'page' : 'dp_page';
        $ds_pageName = ($activeTab === 'designations') ? 'page' : 'ds_page';

        // Full Lists for dropdowns & modals
        $companiesList = Company::orderBy('company_name')->get();
        $businessUnitsList = BusinessUnit::orderBy('name')->get();
        $branchesList = Branch::orderBy('name')->get();
        $departmentsList = Department::orderBy('name')->get();
        $employeesList = Employee::orderBy('full_name')->get();

        // 1. Legal Entities Filters & Sorting
        $co_search = trim((string) $request->string('co_search'));
        $co_status = $request->filled('co_status') ? $request->string('co_status')->value() : null;
        $co_sort = $request->string('co_sort')->value() ?: 'name_asc';

        $companiesQuery = Company::query();
        if ($co_search !== '') {
            $companiesQuery->where(function ($q) use ($co_search) {
                $q->where('company_name', 'like', "%{$co_search}%")
                  ->orWhere('legal_name', 'like', "%{$co_search}%")
                  ->orWhere('email', 'like', "%{$co_search}%");
            });
        }
        if ($co_status !== null && $co_status !== '') {
            $companiesQuery->where('status', $co_status === '1');
        }
        switch ($co_sort) {
            case 'name_desc': $companiesQuery->orderBy('company_name', 'desc'); break;
            case 'legal_asc': $companiesQuery->orderBy('legal_name', 'asc'); break;
            case 'legal_desc': $companiesQuery->orderBy('legal_name', 'desc'); break;
            case 'name_asc':
            default: $companiesQuery->orderBy('company_name', 'asc'); break;
        }
        $companies = $companiesQuery->paginate(10, ['*'], $co_pageName)->withQueryString();

        // 2. Business Units Filters & Sorting
        $bu_search = trim((string) $request->string('bu_search'));
        $bu_company_id = $request->integer('bu_company_id') ?: null;
        $bu_status = $request->filled('bu_status') ? $request->string('bu_status')->value() : null;
        $bu_sort = $request->string('bu_sort')->value() ?: 'name_asc';

        $businessUnitsQuery = BusinessUnit::with(['company', 'head']);
        if ($bu_search !== '') {
            $businessUnitsQuery->where(function ($q) use ($bu_search) {
                $q->where('name', 'like', "%{$bu_search}%")
                  ->orWhere('code', 'like', "%{$bu_search}%");
            });
        }
        if ($bu_company_id) {
            $businessUnitsQuery->where('company_id', $bu_company_id);
        }
        if ($bu_status !== null && $bu_status !== '') {
            $businessUnitsQuery->where('status', $bu_status === '1');
        }
        switch ($bu_sort) {
            case 'name_desc': $businessUnitsQuery->orderBy('name', 'desc'); break;
            case 'code_asc': $businessUnitsQuery->orderBy('code', 'asc'); break;
            case 'code_desc': $businessUnitsQuery->orderBy('code', 'desc'); break;
            case 'name_asc':
            default: $businessUnitsQuery->orderBy('name', 'asc'); break;
        }
        $businessUnits = $businessUnitsQuery->paginate(10, ['*'], $bu_pageName)->withQueryString();

        // 3. Branches Filters & Sorting
        $br_search = trim((string) $request->string('br_search'));
        $br_company_id = $request->integer('br_company_id') ?: null;
        $br_business_unit_id = $request->integer('br_business_unit_id') ?: null;
        $br_status = $request->filled('br_status') ? $request->string('br_status')->value() : null;
        $br_sort = $request->string('br_sort')->value() ?: 'name_asc';

        $branchesQuery = Branch::with(['businessUnit', 'company', 'manager']);
        if ($br_search !== '') {
            $branchesQuery->where(function ($q) use ($br_search) {
                $q->where('name', 'like', "%{$br_search}%")
                  ->orWhere('code', 'like', "%{$br_search}%")
                  ->orWhere('city', 'like', "%{$br_search}%");
            });
        }
        if ($br_company_id) {
            $branchesQuery->where('company_id', $br_company_id);
        }
        if ($br_business_unit_id) {
            $branchesQuery->where('business_unit_id', $br_business_unit_id);
        }
        if ($br_status !== null && $br_status !== '') {
            $branchesQuery->where('status', $br_status === '1');
        }
        switch ($br_sort) {
            case 'name_desc': $branchesQuery->orderBy('name', 'desc'); break;
            case 'code_asc': $branchesQuery->orderBy('code', 'asc'); break;
            case 'code_desc': $branchesQuery->orderBy('code', 'desc'); break;
            case 'name_asc':
            default: $branchesQuery->orderBy('name', 'asc'); break;
        }
        $branches = $branchesQuery->paginate(10, ['*'], $br_pageName)->withQueryString();

        // 4. Departments Filters & Sorting
        $dp_search = trim((string) $request->string('dp_search'));
        $dp_company_id = $request->integer('dp_company_id') ?: null;
        $dp_business_unit_id = $request->integer('dp_business_unit_id') ?: null;
        $dp_branch_id = $request->integer('dp_branch_id') ?: null;
        $dp_status = $request->filled('dp_status') ? $request->string('dp_status')->value() : null;
        $dp_sort = $request->string('dp_sort')->value() ?: 'name_asc';

        $departmentsQuery = Department::with(['branch', 'company', 'businessUnit', 'head']);
        if ($dp_search !== '') {
            $departmentsQuery->where(function ($q) use ($dp_search) {
                $q->where('name', 'like', "%{$dp_search}%")
                  ->orWhere('code', 'like', "%{$dp_search}%");
            });
        }
        if ($dp_company_id) {
            $departmentsQuery->where('company_id', $dp_company_id);
        }
        if ($dp_business_unit_id) {
            $departmentsQuery->where('business_unit_id', $dp_business_unit_id);
        }
        if ($dp_branch_id) {
            $departmentsQuery->where('branch_id', $dp_branch_id);
        }
        if ($dp_status !== null && $dp_status !== '') {
            $departmentsQuery->where('status', $dp_status === '1');
        }
        switch ($dp_sort) {
            case 'name_desc': $departmentsQuery->orderBy('name', 'desc'); break;
            case 'code_asc': $departmentsQuery->orderBy('code', 'asc'); break;
            case 'code_desc': $departmentsQuery->orderBy('code', 'desc'); break;
            case 'name_asc':
            default: $departmentsQuery->orderBy('name', 'asc'); break;
        }
        $departments = $departmentsQuery->paginate(10, ['*'], $dp_pageName)->withQueryString();

        // 5. Designations Filters & Sorting
        $ds_search = trim((string) $request->string('ds_search'));
        $ds_department_id = $request->integer('ds_department_id') ?: null;
        $ds_status = $request->filled('ds_status') ? $request->string('ds_status')->value() : null;
        $ds_sort = $request->string('ds_sort')->value() ?: 'name_asc';

        $designationsQuery = Designation::with(['department']);
        if ($ds_search !== '') {
            $designationsQuery->where(function ($q) use ($ds_search) {
                $q->where('name', 'like', "%{$ds_search}%")
                  ->orWhere('level', 'like', "%{$ds_search}%");
            });
        }
        if ($ds_department_id) {
            $designationsQuery->where('department_id', $ds_department_id);
        }
        if ($ds_status !== null && $ds_status !== '') {
            $designationsQuery->where('status', $ds_status === '1');
        }
        switch ($ds_sort) {
            case 'name_desc': $designationsQuery->orderBy('name', 'desc'); break;
            case 'level_asc': $designationsQuery->orderBy('level', 'asc'); break;
            case 'level_desc': $designationsQuery->orderBy('level', 'desc'); break;
            case 'name_asc':
            default: $designationsQuery->orderBy('name', 'asc'); break;
        }
        $designations = $designationsQuery->paginate(10, ['*'], $ds_pageName)->withQueryString();

        $employees = Employee::all();
        $salaryComponents = SalaryComponent::with(['company'])->get();

        $filters = [
            'co_search' => $co_search,
            'co_status' => $co_status,
            'co_sort' => $co_sort,

            'bu_search' => $bu_search,
            'bu_company_id' => $bu_company_id,
            'bu_status' => $bu_status,
            'bu_sort' => $bu_sort,

            'br_search' => $br_search,
            'br_company_id' => $br_company_id,
            'br_business_unit_id' => $br_business_unit_id,
            'br_status' => $br_status,
            'br_sort' => $br_sort,

            'dp_search' => $dp_search,
            'dp_company_id' => $dp_company_id,
            'dp_business_unit_id' => $dp_business_unit_id,
            'dp_branch_id' => $dp_branch_id,
            'dp_status' => $dp_status,
            'dp_sort' => $dp_sort,

            'ds_search' => $ds_search,
            'ds_department_id' => $ds_department_id,
            'ds_status' => $ds_status,
            'ds_sort' => $ds_sort,
        ];

        return view('modules.hrms.org-structure.org', compact(
            'companies', 'businessUnits', 'employees', 'branches', 'departments', 'designations', 'salaryComponents',
            'companiesList', 'businessUnitsList', 'branchesList', 'departmentsList', 'employeesList', 'filters'
        ));
    }

    public function create() {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        return view('modules.hrms.org-structure.create-company');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'company_name' => 'required|max:255',
            'legal_name' => 'required|max:255',
            'gst_number' => 'nullable|max:50',
            'pan_number' => 'nullable|max:50',
            'cin_number' => 'nullable|max:100',
            'registration_number' => 'nullable|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|max:20',
            'website' => 'nullable',
            'address' => 'nullable',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'currency' => 'required|string|max:255',
            'time_zone' => 'required|string|max:100',
            'status' => 'required',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $organization = Organization::currentDefault();

        $logo = null;

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store('legal_entities', 'public');
        }

        // Clean currency input (e.g. "USD - US Dollar - $" -> "USD")
        $currency = null;
        if ($request->currency) {
            $parts = explode('-', $request->currency);
            $currency = trim($parts[0]);
            $currency = substr($currency, 0, 10);
        }

        // Normalize status to boolean
        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active');

        Company::create([
            'organization_id' => $organization->id,
            'company_name' => $request->company_name,
            'legal_name' => $request->legal_name,
            'gst_number' => $request->gst_number,
            'pan_number' => $request->pan_number,
            'cin_number' => $request->cin_number,
            'registration_number' => $request->registration_number,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'currency' => $currency,
            'timezone' => $request->time_zone, // mapped to correct Eloquent model field 'timezone'
            'status' => $status,
            'logo' => $logo,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'legal-entities'])->with('success', 'Legal Entity created successfully.');
    }

    public function update(Request $request, Company $company)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'company_name' => 'required|max:255',
            'legal_name' => 'required|max:255',
            'gst_number' => 'nullable|max:50',
            'pan_number' => 'nullable|max:50',
            'cin_number' => 'nullable|max:100',
            'registration_number' => 'nullable|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|max:20',
            'website' => 'nullable',
            'address' => 'nullable',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'currency' => 'required|string|max:255',
            'time_zone' => 'required|string|max:100',
            'status' => 'required',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $logo = $company->logo;

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store('legal_entities', 'public');
        }

        // Clean currency input (e.g. "USD - US Dollar - $" -> "USD")
        $currency = null;
        if ($request->currency) {
            $parts = explode('-', $request->currency);
            $currency = trim($parts[0]);
            $currency = substr($currency, 0, 10);
        }

        // Normalize status to boolean
        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $company->update([
            'company_name' => $request->company_name,
            'legal_name' => $request->legal_name,
            'gst_number' => $request->gst_number,
            'pan_number' => $request->pan_number,
            'cin_number' => $request->cin_number,
            'registration_number' => $request->registration_number,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'currency' => $currency,
            'timezone' => $request->time_zone,
            'status' => $status,
            'logo' => $logo,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'legal-entities'])->with('success', 'Legal Entity updated successfully.');
    }

    public function createBusinessUnit() {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $companies = Company::all();
        $employees = Employee::all();
        return view('modules.hrms.org-structure.create-business-unit', compact('companies', 'employees'));
    }

    public function storeBusinessUnit(Request $request) {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'description' => 'nullable',
            'head_employee_id' => 'nullable|exists:employees,id',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active');

        BusinessUnit::create([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'head_employee_id' => $request->head_employee_id ?: null,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'business-units'])->with('success', 'Business Unit created successfully.');
    }

    public function updateBusinessUnit(Request $request, BusinessUnit $businessUnit) {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'description' => 'nullable',
            'head_employee_id' => 'nullable|exists:employees,id',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $businessUnit->update([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'head_employee_id' => $request->head_employee_id ?: null,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'business-units'])->with('success', 'Business Unit updated successfully.');
    }

    public function createBranch() {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $companies = Company::orderBy('company_name')->get();
        $businessUnits = BusinessUnit::all();
        $employees = Employee::all();
        return view('modules.hrms.org-structure.create-branch', compact('companies', 'businessUnits', 'employees'));
    }

    public function storeBranch(Request $request) {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'company_id' => 'required_without:business_unit_id|nullable|exists:companies,id',
            'business_unit_id' => 'required_without:company_id|nullable|exists:business_units,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'manager_employee_id' => 'nullable|exists:employees,id',
            'phone' => 'nullable|max:20',
            'email' => 'nullable|email',
            'address' => 'nullable',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'status' => 'required',
        ]);

        $companyId = $request->company_id;
        if ($request->business_unit_id) {
            $bu = BusinessUnit::find($request->business_unit_id);
            if ($bu) {
                $companyId = $bu->company_id;
            }
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active');

        Branch::create([
            'company_id' => $companyId,
            'business_unit_id' => $request->business_unit_id ?: null,
            'name' => $request->name,
            'code' => $request->code,
            'manager_employee_id' => $request->manager_employee_id ?: null,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'branches'])->with('success', 'Branch created successfully.');
    }

    public function updateBranch(Request $request, Branch $branch) {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'company_id' => 'required_without:business_unit_id|nullable|exists:companies,id',
            'business_unit_id' => 'required_without:company_id|nullable|exists:business_units,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'manager_employee_id' => 'nullable|exists:employees,id',
            'phone' => 'nullable|max:20',
            'email' => 'nullable|email',
            'address' => 'nullable',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'status' => 'required',
        ]);

        $companyId = $request->company_id;
        if ($request->business_unit_id) {
            $bu = BusinessUnit::find($request->business_unit_id);
            if ($bu) {
                $companyId = $bu->company_id;
            }
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $branch->update([
            'company_id' => $companyId,
            'business_unit_id' => $request->business_unit_id ?: null,
            'name' => $request->name,
            'code' => $request->code,
            'manager_employee_id' => $request->manager_employee_id ?: null,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'branches'])->with('success', 'Branch updated successfully.');
    }

    public function createDepartment() {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $companies = Company::orderBy('company_name')->get();
        $businessUnits = BusinessUnit::all();
        $branches = Branch::all();
        $employees = Employee::all();
        return view('modules.hrms.org-structure.create-department', compact('companies', 'businessUnits', 'branches', 'employees'));
    }

    public function storeDepartment(Request $request) {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'company_id' => 'required_without_all:branch_id,business_unit_id|nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $companyId = $request->company_id;
        $businessUnitId = $request->business_unit_id;
        $branchId = $request->branch_id;

        if ($branchId) {
            $branch = Branch::find($branchId);
            if ($branch) {
                $businessUnitId = $branch->business_unit_id;
                $companyId = $branch->company_id;
            }
        } elseif ($businessUnitId) {
            $bu = BusinessUnit::find($businessUnitId);
            if ($bu) {
                $companyId = $bu->company_id;
            }
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active');

        Department::create([
            'company_id' => $companyId,
            'business_unit_id' => $businessUnitId ?: null,
            'branch_id' => $branchId ?: null,
            'name' => $request->name,
            'code' => $request->code,
            'head_employee_id' => $request->head_employee_id ?: null,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', 'Department created successfully.');
    }

    public function updateDepartment(Request $request, Department $department) {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'company_id' => 'required_without_all:branch_id,business_unit_id|nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $companyId = $request->company_id;
        $businessUnitId = $request->business_unit_id;
        $branchId = $request->branch_id;

        if ($branchId) {
            $branch = Branch::find($branchId);
            if ($branch) {
                $businessUnitId = $branch->business_unit_id;
                $companyId = $branch->company_id;
            }
        } elseif ($businessUnitId) {
            $bu = BusinessUnit::find($businessUnitId);
            if ($bu) {
                $companyId = $bu->company_id;
            }
        }

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $department->update([
            'company_id' => $companyId,
            'business_unit_id' => $businessUnitId ?: null,
            'branch_id' => $branchId ?: null,
            'name' => $request->name,
            'code' => $request->code,
            'head_employee_id' => $request->head_employee_id ?: null,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', 'Department updated successfully.');
    }

    public function createDesignation() {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $departments = Department::all();
        return view('modules.hrms.org-structure.create-designation', compact('departments'));
    }

    public function storeDesignation(Request $request) {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|max:255',
            'level' => 'nullable|max:50',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active');

        Designation::create([
            'department_id' => $request->department_id,
            'name' => $request->name,
            'level' => $request->level,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'designations'])->with('success', 'Designation created successfully.');
    }

    public function updateDesignation(Request $request, Designation $designation) {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|max:255',
            'level' => 'nullable|max:50',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $designation->update([
            'department_id' => $request->department_id,
            'name' => $request->name,
            'level' => $request->level,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'designations'])->with('success', 'Designation updated successfully.');
    }

    public function destroy(Company $company)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Dissociate employees from this company
        Employee::withTrashed()->where('company_id', $company->id)->update(['company_id' => null]);

        $departmentIds = Department::where('company_id', $company->id)->pluck('id');
        
        // Dissociate employees from departments/designations that will be deleted
        Employee::withTrashed()->whereIn('department_id', $departmentIds)->update([
            'department_id' => null,
            'designation_id' => null
        ]);

        Designation::whereIn('department_id', $departmentIds)->delete();
        Department::where('company_id', $company->id)->delete();
        Branch::where('company_id', $company->id)->delete();
        BusinessUnit::where('company_id', $company->id)->delete();

        $company->delete();
        return redirect()->route('hrms.org.index', ['tab' => 'legal-entities'])->with('success', 'Legal Entity deleted successfully.');
    }

    public function destroyBusinessUnit(BusinessUnit $businessUnit)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Dissociate employees
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
        return redirect()->route('hrms.org.index', ['tab' => 'business-units'])->with('success', 'Business Unit deleted successfully.');
    }

    public function destroyBranch(Branch $branch)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Dissociate employees
        Employee::withTrashed()->where('branch_id', $branch->id)->update(['branch_id' => null]);

        $departmentIds = Department::where('branch_id', $branch->id)->pluck('id');
        
        Employee::withTrashed()->whereIn('department_id', $departmentIds)->update([
            'department_id' => null,
            'designation_id' => null
        ]);

        Designation::whereIn('department_id', $departmentIds)->delete();
        Department::where('branch_id', $branch->id)->delete();

        $branch->delete();
        return redirect()->route('hrms.org.index', ['tab' => 'branches'])->with('success', 'Branch deleted successfully.');
    }

    public function destroyDepartment(Department $department)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Dissociate employees
        Employee::withTrashed()->where('department_id', $department->id)->update([
            'department_id' => null,
            'designation_id' => null
        ]);

        // Designations under this department
        Designation::where('department_id', $department->id)->delete();

        $department->delete();
        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', 'Department deleted successfully.');
    }

    public function destroyDesignation(Designation $designation)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Dissociate employees
        Employee::withTrashed()->where('designation_id', $designation->id)->update(['designation_id' => null]);

        $designation->delete();
        return redirect()->route('hrms.org.index', ['tab' => 'designations'])->with('success', 'Designation deleted successfully.');
    }

    public function storeSalaryComponent(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'type' => 'required',
            'calculation_type' => 'nullable',
            'default_value' => 'nullable|max:255',
            'description' => 'nullable',
            'company_id' => 'nullable|integer',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        SalaryComponent::create([
            'organization_id' => Organization::currentDefault()->id,
            'company_id' => $request->company_id,
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'calculation_type' => $request->calculation_type ?? 'fixed',
            'default_value' => $request->default_value,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'salary-structure'])->with('success', 'Salary Component created successfully.');
    }

    public function updateSalaryComponent(Request $request, SalaryComponent $salaryComponent)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'type' => 'required',
            'calculation_type' => 'nullable',
            'default_value' => 'nullable|max:255',
            'description' => 'nullable',
            'company_id' => 'nullable|integer',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $salaryComponent->update([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'calculation_type' => $request->calculation_type ?? 'fixed',
            'default_value' => $request->default_value,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'salary-structure'])->with('success', 'Salary Component updated successfully.');
    }

    public function destroySalaryComponent(SalaryComponent $salaryComponent)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $salaryComponent->delete();
        return redirect()->route('hrms.org.index', ['tab' => 'salary-structure'])->with('success', 'Salary Component deleted successfully.');
    }
}
