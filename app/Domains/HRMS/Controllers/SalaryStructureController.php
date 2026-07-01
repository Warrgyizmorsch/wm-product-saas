<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\SalaryComponent;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function index()
    {
        // Programmatically run schema updates if they haven't been applied yet
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('salary_components')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--path' => 'database/migrations/2026_07_01_160000_create_salary_components_table.php',
                    '--force' => true
                ]);
            }
        } catch (\Exception $e) {
            // Silently capture any setup errors
        }

        $companies = Company::all();
        $salaryComponents = SalaryComponent::with(['company'])->get();

        return view('modules.hrms.salary-structure.index', compact('companies', 'salaryComponents'));
    }

    public function storeComponent(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'type' => 'required',
            'calculation_type' => 'required',
            'default_value' => 'nullable|max:255',
            'description' => 'nullable',
            'company_id' => 'nullable|integer',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        SalaryComponent::create([
            'organization_id' => 1,
            'company_id' => $request->company_id,
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'calculation_type' => $request->calculation_type,
            'default_value' => $request->default_value,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.salary-structure.index')->with('success', 'Salary Component created successfully.');
    }

    public function updateComponent(Request $request, SalaryComponent $salaryComponent)
    {
        $request->validate([
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'type' => 'required',
            'calculation_type' => 'required',
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
            'calculation_type' => $request->calculation_type,
            'default_value' => $request->default_value,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.salary-structure.index')->with('success', 'Salary Component updated successfully.');
    }

    public function destroyComponent(SalaryComponent $salaryComponent)
    {
        $salaryComponent->delete();
        return redirect()->route('hrms.salary-structure.index')->with('success', 'Salary Component deleted successfully.');
    }
}
