<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\SalaryComponent;

class OrgRepository implements OrgRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        $activeTab = $inputs['tab'] ?? 'legal-entities';
        $co_pageName = ($activeTab === 'legal-entities') ? 'page' : 'co_page';
        $bu_pageName = ($activeTab === 'business-units') ? 'page' : 'bu_page';
        $br_pageName = ($activeTab === 'branches') ? 'page' : 'br_page';
        $dp_pageName = ($activeTab === 'departments') ? 'page' : 'dp_page';
        $ds_pageName = ($activeTab === 'designations') ? 'page' : 'ds_page';

        $companiesList = Company::orderBy('company_name')->get();
        $businessUnitsList = BusinessUnit::orderBy('name')->get();
        $branchesList = Branch::orderBy('name')->get();
        $departmentsList = Department::orderBy('name')->get();
        $employeesList = Employee::orderBy('full_name')->get();

        // 1. Legal Entities
        $co_search = trim((string) ($inputs['co_search'] ?? ''));
        $co_status = isset($inputs['co_status']) && $inputs['co_status'] !== '' ? (string) $inputs['co_status'] : null;
        $co_sort = !empty($inputs['co_sort']) ? (string) $inputs['co_sort'] : 'name_asc';

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

        // 2. Business Units
        $bu_search = trim((string) ($inputs['bu_search'] ?? ''));
        $bu_company_id = !empty($inputs['bu_company_id']) ? (int) $inputs['bu_company_id'] : null;
        $bu_status = isset($inputs['bu_status']) && $inputs['bu_status'] !== '' ? (string) $inputs['bu_status'] : null;
        $bu_sort = !empty($inputs['bu_sort']) ? (string) $inputs['bu_sort'] : 'name_asc';

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

        // 3. Branches
        $br_search = trim((string) ($inputs['br_search'] ?? ''));
        $br_company_id = !empty($inputs['br_company_id']) ? (int) $inputs['br_company_id'] : null;
        $br_business_unit_id = !empty($inputs['br_business_unit_id']) ? (int) $inputs['br_business_unit_id'] : null;
        $br_status = isset($inputs['br_status']) && $inputs['br_status'] !== '' ? (string) $inputs['br_status'] : null;
        $br_sort = !empty($inputs['br_sort']) ? (string) $inputs['br_sort'] : 'name_asc';

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

        // 4. Departments
        $dp_search = trim((string) ($inputs['dp_search'] ?? ''));
        $dp_company_id = !empty($inputs['dp_company_id']) ? (int) $inputs['dp_company_id'] : null;
        $dp_business_unit_id = !empty($inputs['dp_business_unit_id']) ? (int) $inputs['dp_business_unit_id'] : null;
        $dp_branch_id = !empty($inputs['dp_branch_id']) ? (int) $inputs['dp_branch_id'] : null;
        $dp_status = isset($inputs['dp_status']) && $inputs['dp_status'] !== '' ? (string) $inputs['dp_status'] : null;
        $dp_sort = !empty($inputs['dp_sort']) ? (string) $inputs['dp_sort'] : 'name_asc';

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

        // 5. Designations
        $ds_search = trim((string) ($inputs['ds_search'] ?? ''));
        $ds_department_id = !empty($inputs['ds_department_id']) ? (int) $inputs['ds_department_id'] : null;
        $ds_status = isset($inputs['ds_status']) && $inputs['ds_status'] !== '' ? (string) $inputs['ds_status'] : null;
        $ds_sort = !empty($inputs['ds_sort']) ? (string) $inputs['ds_sort'] : 'name_asc';

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
            'co_search' => $co_search, 'co_status' => $co_status, 'co_sort' => $co_sort,
            'bu_search' => $bu_search, 'bu_company_id' => $bu_company_id, 'bu_status' => $bu_status, 'bu_sort' => $bu_sort,
            'br_search' => $br_search, 'br_company_id' => $br_company_id, 'br_business_unit_id' => $br_business_unit_id, 'br_status' => $br_status, 'br_sort' => $br_sort,
            'dp_search' => $dp_search, 'dp_company_id' => $dp_company_id, 'dp_business_unit_id' => $dp_business_unit_id, 'dp_branch_id' => $dp_branch_id, 'dp_status' => $dp_status, 'dp_sort' => $dp_sort,
            'ds_search' => $ds_search, 'ds_department_id' => $ds_department_id, 'ds_status' => $ds_status, 'ds_sort' => $ds_sort,
        ];

        return compact(
            'companies', 'businessUnits', 'branches', 'departments', 'designations', 'employees', 'salaryComponents',
            'companiesList', 'businessUnitsList', 'branchesList', 'departmentsList', 'employeesList', 'filters'
        );
    }

    public function storeCompany(array $validated): Company { return Company::create($validated); }
    public function updateCompany(Company $company, array $validated): bool { return $company->update($validated); }
    public function destroyCompany(Company $company): bool { return $company->delete(); }

    public function storeBusinessUnit(array $validated): BusinessUnit { return BusinessUnit::create($validated); }
    public function updateBusinessUnit(BusinessUnit $businessUnit, array $validated): bool { return $businessUnit->update($validated); }
    public function destroyBusinessUnit(BusinessUnit $businessUnit): bool { return $businessUnit->delete(); }

    public function storeBranch(array $validated): Branch { return Branch::create($validated); }
    public function updateBranch(Branch $branch, array $validated): bool { return $branch->update($validated); }
    public function destroyBranch(Branch $branch): bool { return $branch->delete(); }

    public function storeDepartment(array $validated): Department { return Department::create($validated); }
    public function updateDepartment(Department $department, array $validated): bool { return $department->update($validated); }
    public function destroyDepartment(Department $department): bool { return $department->delete(); }

    public function storeDesignation(array $validated): Designation { return Designation::create($validated); }
    public function updateDesignation(Designation $designation, array $validated): bool { return $designation->update($validated); }
    public function destroyDesignation(Designation $designation): bool { return $designation->delete(); }
}
