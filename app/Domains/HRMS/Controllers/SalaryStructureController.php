<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\SalaryStructureItem;
use App\Domains\HRMS\Models\PayGroup;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $companies = Company::all();
        $payGroupsQuery = PayGroup::with(['company']);
        if ($request->filled('pg_status')) {
            $payGroupsQuery->where('status', $request->get('pg_status'));
        }
        if ($request->filled('pg_company')) {
            $payGroupsQuery->where('company_id', $request->get('pg_company'));
        }
        $payGroups = $payGroupsQuery->get();
        
        $selectedPayGroupId = $request->get('pay_group_id');
        $selectedPayGroup = null;
        
        if ($selectedPayGroupId) {
            $selectedPayGroup = PayGroup::with(['company'])->find($selectedPayGroupId);
        }
        
        if (!$selectedPayGroup && $payGroups->isNotEmpty()) {
            $selectedPayGroup = $payGroups->first();
        }

        $salaryComponentsQuery = SalaryComponent::with(['company']);
        $salaryStructuresQuery = SalaryStructure::with(['company', 'items.component']);

        if ($selectedPayGroup) {
            $salaryComponentsQuery->where('pay_group_id', $selectedPayGroup->id);
            $salaryStructuresQuery->where('pay_group_id', $selectedPayGroup->id);
        } else {
            $salaryComponentsQuery->whereNull('pay_group_id');
            $salaryStructuresQuery->whereNull('pay_group_id');
        }

        if ($request->filled('struct_search')) {
            $structSearch = $request->get('struct_search');
            $salaryStructuresQuery->where('name', 'like', "%{$structSearch}%");
        }

        if ($request->filled('struct_status')) {
            $structStatus = $request->get('struct_status');
            $salaryStructuresQuery->where('status', $structStatus);
        }

        if ($request->filled('struct_sort')) {
            $structSort = $request->get('struct_sort');
            if ($structSort === 'name_asc') {
                $salaryStructuresQuery->orderBy('name', 'asc');
            } elseif ($structSort === 'name_desc') {
                $salaryStructuresQuery->orderBy('name', 'desc');
            } elseif ($structSort === 'min_ctc_asc') {
                $salaryStructuresQuery->orderBy('min_ctc', 'asc');
            } elseif ($structSort === 'min_ctc_desc') {
                $salaryStructuresQuery->orderBy('min_ctc', 'desc');
            } elseif ($structSort === 'max_ctc_asc') {
                $salaryStructuresQuery->orderBy('max_ctc', 'asc');
            } elseif ($structSort === 'max_ctc_desc') {
                $salaryStructuresQuery->orderBy('max_ctc', 'desc');
            }
        }

        $salaryComponents = $salaryComponentsQuery->get();
        
        $recurringComponents = $salaryComponents->filter(fn($c) => !$c->is_adhoc);
        if ($request->filled('rec_status')) {
            $recStatus = $request->get('rec_status');
            $recurringComponents = $recurringComponents->filter(fn($c) => (string) $c->status === (string) $recStatus);
        }
        if ($request->filled('rec_type')) {
            $recType = $request->get('rec_type');
            $recurringComponents = $recurringComponents->filter(fn($c) => $c->type === $recType);
        }
        if ($request->filled('rec_search')) {
            $recSearch = strtolower($request->get('rec_search'));
            $recurringComponents = $recurringComponents->filter(fn($c) => 
                str_contains(strtolower($c->name), $recSearch) || 
                str_contains(strtolower($c->code), $recSearch)
            );
        }
        if ($request->filled('rec_sort')) {
            $recSort = $request->get('rec_sort');
            if ($recSort === 'name_asc') {
                $recurringComponents = $recurringComponents->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
            } elseif ($recSort === 'name_desc') {
                $recurringComponents = $recurringComponents->sortByDesc('name', SORT_NATURAL | SORT_FLAG_CASE);
            } elseif ($recSort === 'code_asc') {
                $recurringComponents = $recurringComponents->sortBy('code', SORT_NATURAL | SORT_FLAG_CASE);
            } elseif ($recSort === 'code_desc') {
                $recurringComponents = $recurringComponents->sortByDesc('code', SORT_NATURAL | SORT_FLAG_CASE);
            }
        }

        $adhocComponents = $salaryComponents->filter(fn($c) => $c->is_adhoc);
        if ($request->filled('adhoc_status')) {
            $adhocStatus = $request->get('adhoc_status');
            $adhocComponents = $adhocComponents->filter(fn($c) => (string) $c->status === (string) $adhocStatus);
        }
        if ($request->filled('adhoc_type')) {
            $adhocType = $request->get('adhoc_type');
            $adhocComponents = $adhocComponents->filter(fn($c) => $c->type === $adhocType);
        }
        if ($request->filled('adhoc_search')) {
            $adhocSearch = strtolower($request->get('adhoc_search'));
            $adhocComponents = $adhocComponents->filter(fn($c) => 
                str_contains(strtolower($c->name), $adhocSearch) || 
                str_contains(strtolower($c->code), $adhocSearch)
            );
        }
        if ($request->filled('adhoc_sort')) {
            $adhocSort = $request->get('adhoc_sort');
            if ($adhocSort === 'name_asc') {
                $adhocComponents = $adhocComponents->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
            } elseif ($adhocSort === 'name_desc') {
                $adhocComponents = $adhocComponents->sortByDesc('name', SORT_NATURAL | SORT_FLAG_CASE);
            } elseif ($adhocSort === 'code_asc') {
                $adhocComponents = $adhocComponents->sortBy('code', SORT_NATURAL | SORT_FLAG_CASE);
            } elseif ($adhocSort === 'code_desc') {
                $adhocComponents = $adhocComponents->sortByDesc('code', SORT_NATURAL | SORT_FLAG_CASE);
            }
        }

        $salaryStructures = $salaryStructuresQuery->paginate(10)->withQueryString();

        return view('modules.hrms.salary-structure.index', compact('companies', 'payGroups', 'selectedPayGroup', 'salaryComponents', 'recurringComponents', 'adhocComponents', 'salaryStructures'));
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
            'pay_group_id' => 'required|integer',
            'status' => 'required',
            'is_adhoc' => 'nullable|integer',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);
        $isAdhoc = $request->get('is_adhoc', 0) == 1;

        $payGroup = PayGroup::findOrFail($request->pay_group_id);

        SalaryComponent::create([
            'organization_id' => 1,
            'company_id' => $payGroup->company_id,
            'pay_group_id' => $request->pay_group_id,
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'calculation_type' => $request->calculation_type,
            'default_value' => $request->default_value,
            'description' => $request->description,
            'status' => $status,
            'is_adhoc' => $isAdhoc,
        ]);

        $subtab = $isAdhoc ? 'adhoc' : 'recurring';

        return redirect()->route('hrms.salary-structure.index', ['tab' => 'components', 'subtab' => $subtab, 'pay_group_id' => $request->pay_group_id])->with('success', 'Salary Component created successfully.');
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
            'pay_group_id' => 'required|integer',
            'status' => 'required',
            'is_adhoc' => 'nullable|integer',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);
        $isAdhoc = $request->get('is_adhoc', 0) == 1;

        $payGroup = PayGroup::findOrFail($request->pay_group_id);

        $salaryComponent->update([
            'company_id' => $payGroup->company_id,
            'pay_group_id' => $request->pay_group_id,
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'calculation_type' => $request->calculation_type,
            'default_value' => $request->default_value,
            'description' => $request->description,
            'status' => $status,
            'is_adhoc' => $isAdhoc,
        ]);

        $subtab = $isAdhoc ? 'adhoc' : 'recurring';

        return redirect()->route('hrms.salary-structure.index', ['tab' => 'components', 'subtab' => $subtab, 'pay_group_id' => $request->pay_group_id])->with('success', 'Salary Component updated successfully.');
    }

    public function destroyComponent(SalaryComponent $salaryComponent)
    {
        $payGroupId = $salaryComponent->pay_group_id;
        $isAdhoc = $salaryComponent->is_adhoc;
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $salaryComponent->delete();
        $subtab = $isAdhoc ? 'adhoc' : 'recurring';
        return redirect()->route('hrms.salary-structure.index', ['tab' => 'components', 'subtab' => $subtab, 'pay_group_id' => $payGroupId])->with('success', 'Salary Component deleted successfully.');
    }

    public function storeStructure(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'pay_group_id' => 'required|integer',
            'min_ctc' => 'required|numeric|min:0',
            'max_ctc' => 'required|numeric|gte:min_ctc',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $payGroup = PayGroup::findOrFail($request->pay_group_id);
        $companyId = $payGroup->company_id;

        // Validation for overlapping slabs within the same Pay Group
        $overlap = SalaryStructure::where('company_id', $companyId)
            ->where('pay_group_id', $request->pay_group_id)
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
            return redirect()->back()->withInput()->with('error', 'Salary Structure ranges cannot overlap with existing slabs in this Pay Group.');
        }

        $structure = SalaryStructure::create([
            'company_id' => $companyId,
            'pay_group_id' => $request->pay_group_id,
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

        return redirect()->route('hrms.salary-structure.index', ['tab' => 'structures', 'pay_group_id' => $request->pay_group_id])->with('success', 'Salary Structure slab created successfully.');
    }

    public function updateStructure(Request $request, SalaryStructure $salaryStructure)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'pay_group_id' => 'required|integer',
            'min_ctc' => 'required|numeric|min:0',
            'max_ctc' => 'required|numeric|gte:min_ctc',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $payGroup = PayGroup::findOrFail($request->pay_group_id);
        $companyId = $payGroup->company_id;

        // Validation for overlapping slabs within the same Pay Group (exclude self)
        $overlap = SalaryStructure::where('company_id', $companyId)
            ->where('pay_group_id', $request->pay_group_id)
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
            return redirect()->back()->withInput()->with('error', 'Salary Structure ranges cannot overlap with existing slabs in this Pay Group.');
        }

        $salaryStructure->update([
            'company_id' => $companyId,
            'pay_group_id' => $request->pay_group_id,
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

        return redirect()->route('hrms.salary-structure.index', ['tab' => 'structures', 'pay_group_id' => $request->pay_group_id])->with('success', 'Salary Structure slab updated successfully.');
    }

    public function destroyStructure(SalaryStructure $salaryStructure)
    {
        $payGroupId = $salaryStructure->pay_group_id;
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $salaryStructure->items()->delete();
        $salaryStructure->delete();
        return redirect()->route('hrms.salary-structure.index', ['tab' => 'structures', 'pay_group_id' => $payGroupId])->with('success', 'Salary Structure slab deleted successfully.');
    }

    public function storePayGroup(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $payGroup = PayGroup::create([
            'organization_id' => 1,
            'company_id' => $request->company_id,
            'name' => $request->name,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.salary-structure.index', ['pay_group_id' => $payGroup->id])->with('success', 'Pay Group created successfully.');
    }

    public function updatePayGroup(Request $request, PayGroup $payGroup)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        $payGroup->update([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.salary-structure.index', ['pay_group_id' => $payGroup->id])->with('success', 'Pay Group updated successfully.');
    }

    public function destroyPayGroup(PayGroup $payGroup)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $payGroup->delete();

        return redirect()->route('hrms.salary-structure.index')->with('success', 'Pay Group deleted successfully.');
    }
}
