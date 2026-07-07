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
            
            // Ensure pay_groups table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('pay_groups')) {
                try {
                    \Illuminate\Support\Facades\Artisan::call('migrate', [
                        '--path' => 'database/migrations/2026_07_07_000000_create_pay_groups_table.php',
                        '--force' => true
                    ]);
                } catch (\Exception $ex) {
                    // Fallback to manual creation if artisan fails
                    \Illuminate\Support\Facades\Schema::create('pay_groups', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->id();
                        $table->unsignedBigInteger('organization_id')->default(1);
                        $table->unsignedBigInteger('company_id')->nullable();
                        $table->string('name');
                        $table->text('description')->nullable();
                        $table->boolean('status')->default(true);
                        $table->timestamps();
                    });
                }
            }

            // Guarantee pay_group_id columns exist in components and structures
            if (\Illuminate\Support\Facades\Schema::hasTable('salary_components') && !\Illuminate\Support\Facades\Schema::hasColumn('salary_components', 'pay_group_id')) {
                \Illuminate\Support\Facades\Schema::table('salary_components', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->unsignedBigInteger('pay_group_id')->nullable()->after('company_id');
                    $table->foreign('pay_group_id')->references('id')->on('pay_groups')->nullOnDelete();
                });
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('salary_structures') && !\Illuminate\Support\Facades\Schema::hasColumn('salary_structures', 'pay_group_id')) {
                \Illuminate\Support\Facades\Schema::table('salary_structures', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->unsignedBigInteger('pay_group_id')->nullable()->after('company_id');
                    $table->foreign('pay_group_id')->references('id')->on('pay_groups')->nullOnDelete();
                });
            }

            // Guarantee is_adhoc column exists in components
            if (\Illuminate\Support\Facades\Schema::hasTable('salary_components') && !\Illuminate\Support\Facades\Schema::hasColumn('salary_components', 'is_adhoc')) {
                \Illuminate\Support\Facades\Schema::table('salary_components', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->boolean('is_adhoc')->default(false)->after('status');
                });
            }

            // Guarantee employee_adhoc_components table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('employee_adhoc_components')) {
                \Illuminate\Support\Facades\Schema::create('employee_adhoc_components', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('employee_id');
                    $table->unsignedBigInteger('salary_component_id');
                    $table->decimal('amount', 15, 2);
                    $table->string('payroll_month');
                    $table->string('status')->default('pending');
                    $table->text('remarks')->nullable();
                    $table->timestamps();
                });
            }

        } catch (\Exception $e) {
            // Log or handle schema exceptions gracefully
        }

        $companies = Company::all();
        $payGroups = PayGroup::with(['company'])->get();
        
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

        $salaryComponents = $salaryComponentsQuery->get();
        $recurringComponents = $salaryComponents->filter(fn($c) => !$c->is_adhoc);
        $adhocComponents = $salaryComponents->filter(fn($c) => $c->is_adhoc);

        $salaryStructures = $salaryStructuresQuery->get();

        return view('modules.hrms.salary-structure.index', compact('companies', 'payGroups', 'selectedPayGroup', 'salaryComponents', 'recurringComponents', 'adhocComponents', 'salaryStructures'));
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
            'pay_group_id' => 'required|integer',
            'status' => 'required',
            'is_adhoc' => 'nullable|integer',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);
        $isAdhoc = $request->get('is_adhoc', 0) == 1;

        SalaryComponent::create([
            'organization_id' => 1,
            'company_id' => $request->company_id,
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
        $request->validate([
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'type' => 'required',
            'calculation_type' => 'required',
            'default_value' => 'nullable|max:255',
            'description' => 'nullable',
            'company_id' => 'nullable|integer',
            'pay_group_id' => 'required|integer',
            'status' => 'required',
            'is_adhoc' => 'nullable|integer',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);
        $isAdhoc = $request->get('is_adhoc', 0) == 1;

        $salaryComponent->update([
            'company_id' => $request->company_id,
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
        $salaryComponent->delete();
        $subtab = $isAdhoc ? 'adhoc' : 'recurring';
        return redirect()->route('hrms.salary-structure.index', ['tab' => 'components', 'subtab' => $subtab, 'pay_group_id' => $payGroupId])->with('success', 'Salary Component deleted successfully.');
    }

    public function storeStructure(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'pay_group_id' => 'required|integer',
            'min_ctc' => 'required|numeric|min:0',
            'max_ctc' => 'required|numeric|gte:min_ctc',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        // Validation for overlapping slabs within the same Pay Group
        $overlap = SalaryStructure::where('company_id', $request->company_id)
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
            'company_id' => $request->company_id,
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
        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'pay_group_id' => 'required|integer',
            'min_ctc' => 'required|numeric|min:0',
            'max_ctc' => 'required|numeric|gte:min_ctc',
            'status' => 'required',
        ]);

        $status = ($request->status === 'success' || $request->status === '1' || $request->status === 'active' || $request->status === true);

        // Validation for overlapping slabs within the same Pay Group (exclude self)
        $overlap = SalaryStructure::where('company_id', $request->company_id)
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
            'company_id' => $request->company_id,
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
        $salaryStructure->items()->delete();
        $salaryStructure->delete();
        return redirect()->route('hrms.salary-structure.index', ['tab' => 'structures', 'pay_group_id' => $payGroupId])->with('success', 'Salary Structure slab deleted successfully.');
    }

    public function storePayGroup(Request $request)
    {
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
        // Nullify components and structures linked to this pay group
        $payGroup->components()->update(['pay_group_id' => null]);
        $payGroup->structures()->update(['pay_group_id' => null]);

        $payGroup->delete();

        return redirect()->route('hrms.salary-structure.index')->with('success', 'Pay Group deleted successfully.');
    }
}

