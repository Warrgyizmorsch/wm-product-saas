<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Repositories\OrgRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrgController extends Controller
{
    public function __construct(
        private readonly OrgRepositoryInterface $orgRepository
    ) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $data = $this->orgRepository->getIndexData($request->all());

        return view('modules.hrms.org-structure.org', $data);
    }

    public function storeCompany(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_name' => 'required|max:255',
            'legal_name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|max:50',
            'website' => 'nullable|url|max:255',
            'tax_id' => 'nullable|max:100',
            'currency' => 'required|max:10',
            'timezone' => 'required|max:50',
            'fiscal_year_start' => 'required|max:20',
            'address' => 'nullable|max:500',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'status' => 'required',
        ]);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->storeCompany($validated);

        return redirect()->route('hrms.org.index', ['tab' => 'legal-entities'])->with('success', __('hrms.org.company_created'));
    }

    public function updateCompany(Request $request, Company $company)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_name' => 'required|max:255',
            'legal_name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|max:50',
            'website' => 'nullable|url|max:255',
            'tax_id' => 'nullable|max:100',
            'currency' => 'required|max:10',
            'timezone' => 'required|max:50',
            'fiscal_year_start' => 'required|max:20',
            'address' => 'nullable|max:500',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'status' => 'required',
        ]);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->updateCompany($company, $validated);

        return redirect()->route('hrms.org.index', ['tab' => 'legal-entities'])->with('success', __('hrms.org.company_updated'));
    }

    public function destroyCompany(Company $company)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $this->orgRepository->destroyCompany($company);

        return redirect()->route('hrms.org.index', ['tab' => 'legal-entities'])->with('success', __('hrms.org.company_deleted'));
    }

    public function storeBusinessUnit(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->storeBusinessUnit($validated);

        return redirect()->route('hrms.org.index', ['tab' => 'business-units'])->with('success', __('hrms.org.bu_created'));
    }

    public function updateBusinessUnit(Request $request, BusinessUnit $businessUnit)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->updateBusinessUnit($businessUnit, $validated);

        return redirect()->route('hrms.org.index', ['tab' => 'business-units'])->with('success', __('hrms.org.bu_updated'));
    }

    public function destroyBusinessUnit(BusinessUnit $businessUnit)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $this->orgRepository->destroyBusinessUnit($businessUnit);

        return redirect()->route('hrms.org.index', ['tab' => 'business-units'])->with('success', __('hrms.org.bu_deleted'));
    }

    public function storeBranch(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'timezone' => 'required|max:50',
            'manager_id' => 'nullable|exists:employees,id',
            'status' => 'required',
        ]);

        $bu = BusinessUnit::find($request->business_unit_id);
        $validated['company_id'] = $bu->company_id;
        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->orgRepository->storeBranch($validated);

        return redirect()->route('hrms.org.index', ['tab' => 'branches'])->with('success', __('hrms.org.branch_created'));
    }

    public function updateBranch(Request $request, Branch $branch)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'timezone' => 'required|max:50',
            'manager_id' => 'nullable|exists:employees,id',
            'status' => 'required',
        ]);

        $bu = BusinessUnit::find($request->business_unit_id);
        $validated['company_id'] = $bu->company_id;
        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->orgRepository->updateBranch($branch, $validated);

        return redirect()->route('hrms.org.index', ['tab' => 'branches'])->with('success', __('hrms.org.branch_updated'));
    }

    public function destroyBranch(Branch $branch)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $this->orgRepository->destroyBranch($branch);

        return redirect()->route('hrms.org.index', ['tab' => 'branches'])->with('success', __('hrms.org.branch_deleted'));
    }

    public function storeDepartment(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'parent_department_id' => 'nullable|exists:departments,id',
            'status' => 'required',
        ]);

        $branch = Branch::find($request->branch_id);
        $validated['company_id'] = $branch->company_id;
        $validated['business_unit_id'] = $branch->business_unit_id;
        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->orgRepository->storeDepartment($validated);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', __('hrms.org.dept_created'));
    }

    public function updateDepartment(Request $request, Department $department)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'parent_department_id' => 'nullable|exists:departments,id',
            'status' => 'required',
        ]);

        $branch = Branch::find($request->branch_id);
        $validated['company_id'] = $branch->company_id;
        $validated['business_unit_id'] = $branch->business_unit_id;
        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->orgRepository->updateDepartment($department, $validated);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', __('hrms.org.dept_updated'));
    }

    public function destroyDepartment(Department $department)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $this->orgRepository->destroyDepartment($department);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', __('hrms.org.dept_deleted'));
    }

    public function storeDesignation(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|max:255',
            'level' => 'required|max:50',
            'status' => 'required',
        ]);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->storeDesignation($validated);

        return redirect()->route('hrms.org.index', ['tab' => 'designations'])->with('success', __('hrms.org.desig_created'));
    }

    public function updateDesignation(Request $request, Designation $designation)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|max:255',
            'level' => 'required|max:50',
            'status' => 'required',
        ]);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->updateDesignation($designation, $validated);

        return redirect()->route('hrms.org.index', ['tab' => 'designations'])->with('success', __('hrms.org.desig_updated'));
    }

    public function destroyDesignation(Designation $designation)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $this->orgRepository->destroyDesignation($designation);

        return redirect()->route('hrms.org.index', ['tab' => 'designations'])->with('success', __('hrms.org.desig_deleted'));
    }
}
