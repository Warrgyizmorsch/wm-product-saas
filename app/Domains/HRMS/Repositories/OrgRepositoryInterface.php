<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use Illuminate\Http\Request;

interface OrgRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeCompany(array $validated): Company;
    public function updateCompany(Company $company, array $validated): bool;
    public function destroyCompany(Company $company): bool;

    public function storeBusinessUnit(array $validated): BusinessUnit;
    public function updateBusinessUnit(BusinessUnit $businessUnit, array $validated): bool;
    public function destroyBusinessUnit(BusinessUnit $businessUnit): bool;

    public function storeBranch(array $validated): Branch;
    public function updateBranch(Branch $branch, array $validated): bool;
    public function destroyBranch(Branch $branch): bool;

    public function storeDepartment(array $validated): Department;
    public function updateDepartment(Department $department, array $validated): bool;
    public function destroyDepartment(Department $department): bool;

    public function storeDesignation(array $validated): Designation;
    public function updateDesignation(Designation $designation, array $validated): bool;
    public function destroyDesignation(Designation $designation): bool;
}
