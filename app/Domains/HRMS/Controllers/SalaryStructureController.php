<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\SalaryStructureItem;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Programmatically run schema updates if they haven't been applied yet
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('salary_components')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--path' => 'database/migrations/2026_07_01_160000_create_salary_components_table.php',
                    '--force' => true
                ]);
            }
            if (!\Illuminate\Support\Facades\Schema::hasTable('salary_structures')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--path' => 'database/migrations/2026_07_02_160000_create_salary_structures_tables.php',
                    '--force' => true
                ]);
            }

        } catch (\Exception $e) {
            // Silently capture any setup errors
        }

        $companies = Company::all();
        $salaryComponents = SalaryComponent::with(['company'])->get();
        $salaryStructures = SalaryStructure::with(['company', 'items.component'])->get();

        return view('modules.hrms.salary-structure.index', compact('companies', 'salaryComponents', 'salaryStructures'));
    }

    public function storeComponent(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

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

        return redirect()->route('hrms.salary-structure.index', ['tab' => 'components'])->with('success', 'Salary Component created successfully.');
    }

    public function updateComponent(Request $request, SalaryComponent $salaryComponent)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

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

        return redirect()->route('hrms.salary-structure.index', ['tab' => 'components'])->with('success', 'Salary Component updated successfully.');
    }

    public function destroyComponent(SalaryComponent $salaryComponent)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $salaryComponent->delete();
        return redirect()->route('hrms.salary-structure.index', ['tab' => 'components'])->with('success', 'Salary Component deleted successfully.');
    }

    public function storeStructure(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'min_ctc' => 'required|numeric|min:0',
            'max_ctc' => 'required|numeric|gte:min_ctc',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        // Validation for overlapping slabs
        $overlap = SalaryStructure::where('company_id', $request->company_id)
            ->where(function ($query) use ($request) {
                $query->whereBetween('min_ctc', [$request->min_ctc, $request->max_ctc])
                    ->orWhereBetween('max_ctc', [$request->min_ctc, $request->max_ctc])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('min_ctc', '<=', $request->min_ctc)
                            ->where('max_ctc', '>=', $request->max_ctc);
                    });
            })
            ->exists();

        if ($overlap) {
            return redirect()->back()->withInput()->with('error', 'Salary Structure ranges cannot overlap with existing slabs.');
        }

        $structure = SalaryStructure::create([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'min_ctc' => $request->min_ctc,
            'max_ctc' => $request->max_ctc,
            'status' => $status,
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
                    
                    // Sort order: Basic = 1, Percentage of Basic = 3, Balancing = 5, others = 2
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
                        'calculation_type' => $calcType,
                        'value' => $value,
                        'sort_order' => $sortOrder,
                    ]);
                }
            }
        }

        return redirect()->route('hrms.salary-structure.index', ['tab' => 'structures'])->with('success', 'Salary Structure slab created successfully.');
    }

    public function updateStructure(Request $request, SalaryStructure $salaryStructure)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'min_ctc' => 'required|numeric|min:0',
            'max_ctc' => 'required|numeric|gte:min_ctc',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        // Validation for overlapping slabs (exclude self)
        $overlap = SalaryStructure::where('company_id', $request->company_id)
            ->where('id', '!=', $salaryStructure->id)
            ->where(function ($query) use ($request) {
                $query->whereBetween('min_ctc', [$request->min_ctc, $request->max_ctc])
                    ->orWhereBetween('max_ctc', [$request->min_ctc, $request->max_ctc])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('min_ctc', '<=', $request->min_ctc)
                            ->where('max_ctc', '>=', $request->max_ctc);
                    });
            })
            ->exists();

        if ($overlap) {
            return redirect()->back()->withInput()->with('error', 'Salary Structure ranges cannot overlap with existing slabs.');
        }

        $salaryStructure->update([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'min_ctc' => $request->min_ctc,
            'max_ctc' => $request->max_ctc,
            'status' => $status,
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

                    // Sort order: Basic = 1, Percentage of Basic = 3, Balancing = 5, others = 2
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
                        'calculation_type' => $calcType,
                        'value' => $value,
                        'sort_order' => $sortOrder,
                    ]);
                }
            }
        }

        return redirect()->route('hrms.salary-structure.index', ['tab' => 'structures'])->with('success', 'Salary Structure slab updated successfully.');
    }

    public function destroyStructure(SalaryStructure $salaryStructure)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $salaryStructure->items()->delete();
        $salaryStructure->delete();
        return redirect()->route('hrms.salary-structure.index', ['tab' => 'structures'])->with('success', 'Salary Structure slab deleted successfully.');
    }
}

