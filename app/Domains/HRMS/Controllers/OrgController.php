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
use Illuminate\Http\Request;

class OrgController extends Controller {
    public function index() {
        $companies = Company::all();
        $businessUnits = BusinessUnit::with(['company', 'head'])->get();
        $employees = Employee::all();
        $branches = Branch::with(['businessUnit', 'manager'])->get();
        $departments = Department::with(['branch', 'head'])->get();
        $designations = Designation::with(['department'])->get();

        return view('modules.hrms.org-structure.org', compact(
            'companies', 'businessUnits', 'employees', 'branches', 'departments', 'designations'
        ));
    }

    public function create() {
        return view('modules.hrms.org-structure.create-company');
    }

    public function store(Request $request)
    {
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
            'currency' => 'nullable', // Removed max:20 constraint since select option labels are long
            'time_zone' => 'nullable|max:100',
            'status' => 'required',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Ensure default organization exists for foreign key constraint
        Organization::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Default Organization',
                'slug' => 'default-organization',
                'subscription_plan' => 'enterprise',
                'status' => true,
            ]
        );

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
            'organization_id' => 1,
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
            'currency' => 'nullable|max:50',
            'time_zone' => 'nullable|max:100',
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
        $companies = Company::all();
        $employees = Employee::all();
        return view('modules.hrms.org-structure.create-business-unit', compact('companies', 'employees'));
    }

    public function storeBusinessUnit(Request $request) {
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
        $businessUnits = BusinessUnit::all();
        $employees = Employee::all();
        return view('modules.hrms.org-structure.create-branch', compact('businessUnits', 'employees'));
    }

    public function storeBranch(Request $request) {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
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

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active');

        Branch::create([
            'business_unit_id' => $request->business_unit_id,
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
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
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

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $branch->update([
            'business_unit_id' => $request->business_unit_id,
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
        $branches = Branch::all();
        $employees = Employee::all();
        return view('modules.hrms.org-structure.create-department', compact('branches', 'employees'));
    }

    public function storeDepartment(Request $request) {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active');

        Department::create([
            'branch_id' => $request->branch_id,
            'name' => $request->name,
            'code' => $request->code,
            'head_employee_id' => $request->head_employee_id ?: null,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', 'Department created successfully.');
    }

    public function updateDepartment(Request $request, Department $department) {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'head_employee_id' => 'nullable|exists:employees,id',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $department->update([
            'branch_id' => $request->branch_id,
            'name' => $request->name,
            'code' => $request->code,
            'head_employee_id' => $request->head_employee_id ?: null,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.org.index', ['tab' => 'departments'])->with('success', 'Department updated successfully.');
    }

    public function createDesignation() {
        $departments = Department::all();
        return view('modules.hrms.org-structure.create-designation', compact('departments'));
    }

    public function storeDesignation(Request $request) {
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
}