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
        $data = $this->orgRepository->getIndexData($request->all());

        return view('modules.hrms.org-structure.org', $data);
    }

    public function storeCompany(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|max:255',
            'legal_name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|max:50',
            'website' => 'nullable|max:255',
            'gst_number' => 'nullable|max:255',
            'pan_number' => 'nullable|max:255',
            'cin_number' => 'nullable|max:255',
            'registration_number' => 'nullable|max:255',
            'currency' => 'required|max:10',
            'time_zone' => 'required|max:50',
            'address' => 'nullable|max:500',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'status' => 'required',
        ]);

        $validated['timezone'] = $validated['time_zone'];
        unset($validated['time_zone']);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->storeCompany($validated);

        return redirect()->route('hrms.org.index', ['tab' => 'legal-entities'])->with('success', __('hrms.org.company_created'));
    }

    public function updateCompany(Request $request, Company $company)
    {
        $validated = $request->validate([
            'company_name' => 'required|max:255',
            'legal_name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|max:50',
            'website' => 'nullable|max:255',
            'gst_number' => 'nullable|max:255',
            'pan_number' => 'nullable|max:255',
            'cin_number' => 'nullable|max:255',
            'registration_number' => 'nullable|max:255',
            'currency' => 'required|max:10',
            'time_zone' => 'required|max:50',
            'address' => 'nullable|max:500',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'postal_code' => 'nullable|max:20',
            'status' => 'required',
        ]);

        $validated['timezone'] = $validated['time_zone'];
        unset($validated['time_zone']);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->updateCompany($company, $validated);

        return redirect()->route('hrms.org.index', ['tab' => 'legal-entities'])->with('success', __('hrms.org.company_updated'));
    }

    public function destroyCompany(Company $company)
    {
        $this->orgRepository->destroyCompany($company);

        return redirect()->route('hrms.org.index', ['tab' => 'legal-entities'])->with('success', __('hrms.org.company_deleted'));
    }

    public function storeBusinessUnit(Request $request)
    {
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
        $this->orgRepository->destroyBusinessUnit($businessUnit);

        return redirect()->route('hrms.org.index', ['tab' => 'business-units'])->with('success', __('hrms.org.bu_deleted'));
    }

    public function storeBranch(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required_without:business_unit_id|nullable|exists:companies,id',
            'business_unit_id' => 'required_without:company_id|nullable|exists:business_units,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'manager_employee_id' => 'nullable|exists:employees,id',
            'phone' => 'nullable|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|max:500',
            'postal_code' => 'nullable|max:20',
            'status' => 'required',
        ]);

        if ($request->filled('business_unit_id')) {
            $bu = BusinessUnit::find($request->business_unit_id);
            $validated['company_id'] = $bu ? $bu->company_id : $request->company_id;
        } else {
            $validated['company_id'] = $request->company_id;
        }
        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->orgRepository->storeBranch($validated);

        return redirect()->route('hrms.org.index', ['tab' => 'branches'])->with('success', __('hrms.org.branch_created'));
    }

    public function updateBranch(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'company_id' => 'required_without:business_unit_id|nullable|exists:companies,id',
            'business_unit_id' => 'required_without:company_id|nullable|exists:business_units,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'manager_employee_id' => 'nullable|exists:employees,id',
            'phone' => 'nullable|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|max:500',
            'postal_code' => 'nullable|max:20',
            'status' => 'required',
        ]);

        if ($request->filled('business_unit_id')) {
            $bu = BusinessUnit::find($request->business_unit_id);
            $validated['company_id'] = $bu ? $bu->company_id : $request->company_id;
        } else {
            $validated['company_id'] = $request->company_id;
        }
        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->orgRepository->updateBranch($branch, $validated);

        return redirect()->route('hrms.org.index', ['tab' => 'branches'])->with('success', __('hrms.org.branch_updated'));
    }

    public function destroyBranch(Branch $branch)
    {
        $this->orgRepository->destroyBranch($branch);

        return redirect()->route('hrms.org.index', ['tab' => 'branches'])->with('success', __('hrms.org.branch_deleted'));
    }

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required_without_all:business_unit_id,branch_id|nullable|exists:companies,id',
            'business_unit_id' => 'required_without_all:company_id,branch_id|nullable|exists:business_units,id',
            'branch_id' => 'required_without_all:company_id,business_unit_id|nullable|exists:branches,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'parent_department_id' => 'nullable|exists:departments,id',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        if ($request->filled('branch_id')) {
            $branch = Branch::find($request->branch_id);
            $validated['business_unit_id'] = $branch ? $branch->business_unit_id : $request->business_unit_id;
            $validated['company_id'] = $branch ? $branch->company_id : $request->company_id;
        } elseif ($request->filled('business_unit_id')) {
            $bu = BusinessUnit::find($request->business_unit_id);
            $validated['company_id'] = $bu ? $bu->company_id : $request->company_id;
            $validated['branch_id'] = null;
        } else {
            $validated['company_id'] = $request->company_id;
            $validated['business_unit_id'] = null;
            $validated['branch_id'] = null;
        }
        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->orgRepository->storeDepartment($validated);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', __('hrms.org.dept_created'));
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $validated = $request->validate([
            'company_id' => 'required_without_all:business_unit_id,branch_id|nullable|exists:companies,id',
            'business_unit_id' => 'required_without_all:company_id,branch_id|nullable|exists:business_units,id',
            'branch_id' => 'required_without_all:company_id,business_unit_id|nullable|exists:branches,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'parent_department_id' => 'nullable|exists:departments,id',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        if ($request->filled('branch_id')) {
            $branch = Branch::find($request->branch_id);
            $validated['business_unit_id'] = $branch ? $branch->business_unit_id : $request->business_unit_id;
            $validated['company_id'] = $branch ? $branch->company_id : $request->company_id;
        } elseif ($request->filled('business_unit_id')) {
            $bu = BusinessUnit::find($request->business_unit_id);
            $validated['company_id'] = $bu ? $bu->company_id : $request->company_id;
            $validated['branch_id'] = null;
        } else {
            $validated['company_id'] = $request->company_id;
            $validated['business_unit_id'] = null;
            $validated['branch_id'] = null;
        }
        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->orgRepository->updateDepartment($department, $validated);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', __('hrms.org.dept_updated'));
    }

    public function destroyDepartment(Department $department)
    {
        $this->orgRepository->destroyDepartment($department);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', __('hrms.org.dept_deleted'));
    }

    public function storeDesignation(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|max:255',
            'level' => 'nullable|max:50',
            'status' => 'required',
        ]);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->storeDesignation($validated);

        return redirect()->route('hrms.org.index', ['tab' => 'designations'])->with('success', __('hrms.org.desig_created'));
    }

    public function updateDesignation(Request $request, Designation $designation)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|max:255',
            'level' => 'nullable|max:50',
            'status' => 'required',
        ]);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);
        $this->orgRepository->updateDesignation($designation, $validated);

        return redirect()->route('hrms.org.index', ['tab' => 'designations'])->with('success', __('hrms.org.desig_updated'));
    }

    public function destroyDesignation(Designation $designation)
    {
        $this->orgRepository->destroyDesignation($designation);

        return redirect()->route('hrms.org.index', ['tab' => 'designations'])->with('success', __('hrms.org.desig_deleted'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Route aliases: web.php maps store/update/destroy → storeCompany etc.
    // ─────────────────────────────────────────────────────────────────────────

    public function create()
    {
        return redirect()->route('hrms.org.index');
    }

    public function store(\Illuminate\Http\Request $request)
    {
        return $this->storeCompany($request);
    }

    public function update(\Illuminate\Http\Request $request, \App\Domains\HRMS\Models\Company $company)
    {
        return $this->updateCompany($request, $company);
    }

    public function destroy(\App\Domains\HRMS\Models\Company $company)
    {
        return $this->destroyCompany($company);
    }

    public function createBusinessUnit()
    {
        return redirect()->route('hrms.org.index', ['tab' => 'business-units']);
    }

    public function createBranch()
    {
        return redirect()->route('hrms.org.index', ['tab' => 'branches']);
    }

    public function createDepartment()
    {
        return redirect()->route('hrms.org.index', ['tab' => 'departments']);
    }

    public function createDesignation()
    {
        return redirect()->route('hrms.org.index', ['tab' => 'designations']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Salary Component (delegated to SalaryStructureController)
    // ─────────────────────────────────────────────────────────────────────────

    public function storeSalaryComponent(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'pay_group_id' => 'nullable|exists:pay_groups,id',
            'name'         => 'required|string|max:255',
            'code'         => 'required|string|max:50',
            'type'         => 'required|in:earning,deduction',
            'is_adhoc'     => 'required|boolean',
            'status'       => 'required|boolean',
            'description'  => 'nullable|string',
        ]);

        if (!empty($validated['pay_group_id'])) {
            $payGroup = \App\Domains\HRMS\Models\PayGroup::find($validated['pay_group_id']);
            if ($payGroup) {
                $validated['company_id'] = $payGroup->company_id;
            }
        }

        \App\Domains\HRMS\Models\SalaryComponent::create($validated);

        return redirect()->route('hrms.org.index')->with('success', 'Salary component created successfully.');
    }

    public function updateSalaryComponent(\Illuminate\Http\Request $request, \App\Domains\HRMS\Models\SalaryComponent $salaryComponent)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50',
            'type'        => 'required|in:earning,deduction',
            'is_adhoc'    => 'required|boolean',
            'status'      => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $salaryComponent->update($validated);

        return redirect()->route('hrms.org.index')->with('success', 'Salary component updated successfully.');
    }

    public function destroySalaryComponent(\App\Domains\HRMS\Models\SalaryComponent $salaryComponent)
    {
        $salaryComponent->delete();

        return redirect()->route('hrms.org.index')->with('success', 'Salary component deleted successfully.');
    }
}

