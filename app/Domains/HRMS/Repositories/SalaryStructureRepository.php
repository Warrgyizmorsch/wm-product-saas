<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\SalaryStructureItem;
use Illuminate\Support\Facades\DB;

class SalaryStructureRepository implements SalaryStructureRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        $companies = Company::all();
        $payGroupsQuery = PayGroup::with(['company']);
        if (isset($inputs['pg_status']) && $inputs['pg_status'] !== '') {
            $payGroupsQuery->where('status', $inputs['pg_status']);
        }
        if (!empty($inputs['pg_company'])) {
            $payGroupsQuery->where('company_id', $inputs['pg_company']);
        }
        if (!empty($inputs['pg_search'])) {
            $payGroupsQuery->where('name', 'like', '%' . $inputs['pg_search'] . '%');
        }
        if (!empty($inputs['pg_sort'])) {
            $pgSort = $inputs['pg_sort'];
            if ($pgSort === 'name_asc') {
                $payGroupsQuery->orderBy('name', 'asc');
            } elseif ($pgSort === 'name_desc') {
                $payGroupsQuery->orderBy('name', 'desc');
            } elseif ($pgSort === 'newest') {
                $payGroupsQuery->orderBy('created_at', 'desc');
            }
        } else {
            $payGroupsQuery->orderBy('name', 'asc');
        }
        $payGroups = $payGroupsQuery->get();
        
        $selectedPayGroupId = $inputs['pay_group_id'] ?? null;
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

        if (!empty($inputs['struct_search'])) {
            $structSearch = $inputs['struct_search'];
            $salaryStructuresQuery->where('name', 'like', "%{$structSearch}%");
        }

        if (isset($inputs['struct_status']) && $inputs['struct_status'] !== '') {
            $structStatus = $inputs['struct_status'];
            $salaryStructuresQuery->where('status', $structStatus);
        }

        if (!empty($inputs['struct_sort'])) {
            $structSort = $inputs['struct_sort'];
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
        
        // Recurring Components Pagination
        $recurringQuery = SalaryComponent::with(['company'])->where('is_adhoc', false);
        if ($selectedPayGroup) {
            $recurringQuery->where('pay_group_id', $selectedPayGroup->id);
        } else {
            $recurringQuery->whereNull('pay_group_id');
        }

        if (!empty($inputs['rec_status'])) {
            $recurringQuery->where('status', $inputs['rec_status']);
        }
        if (!empty($inputs['rec_type'])) {
            $recurringQuery->where('type', $inputs['rec_type']);
        }
        if (!empty($inputs['rec_search'])) {
            $recSearch = $inputs['rec_search'];
            $recurringQuery->where(function ($q) use ($recSearch) {
                $q->where('name', 'like', "%{$recSearch}%")
                  ->orWhere('code', 'like', "%{$recSearch}%");
            });
        }
        if (!empty($inputs['rec_sort'])) {
            $recSort = $inputs['rec_sort'];
            if ($recSort === 'name_asc') {
                $recurringQuery->orderBy('name', 'asc');
            } elseif ($recSort === 'name_desc') {
                $recurringQuery->orderBy('name', 'desc');
            } elseif ($recSort === 'code_asc') {
                $recurringQuery->orderBy('code', 'asc');
            } elseif ($recSort === 'code_desc') {
                $recurringQuery->orderBy('code', 'desc');
            }
        }

        $recurringComponents = $recurringQuery->paginate(10, ['*'], 'rec_page')->withQueryString();

        // Adhoc Components Pagination
        $adhocQuery = SalaryComponent::with(['company'])->where('is_adhoc', true);
        if ($selectedPayGroup) {
            $adhocQuery->where('pay_group_id', $selectedPayGroup->id);
        } else {
            $adhocQuery->whereNull('pay_group_id');
        }

        if (!empty($inputs['adhoc_status'])) {
            $adhocQuery->where('status', $inputs['adhoc_status']);
        }
        if (!empty($inputs['adhoc_type'])) {
            $adhocQuery->where('type', $inputs['adhoc_type']);
        }
        if (!empty($inputs['adhoc_search'])) {
            $adhocSearch = $inputs['adhoc_search'];
            $adhocQuery->where(function ($q) use ($adhocSearch) {
                $q->where('name', 'like', "%{$adhocSearch}%")
                  ->orWhere('code', 'like', "%{$adhocSearch}%");
            });
        }
        if (!empty($inputs['adhoc_sort'])) {
            $adhocSort = $inputs['adhoc_sort'];
            if ($adhocSort === 'name_asc') {
                $adhocQuery->orderBy('name', 'asc');
            } elseif ($adhocSort === 'name_desc') {
                $adhocQuery->orderBy('name', 'desc');
            } elseif ($adhocSort === 'code_asc') {
                $adhocQuery->orderBy('code', 'asc');
            } elseif ($adhocSort === 'code_desc') {
                $adhocQuery->orderBy('code', 'desc');
            }
        }

        $adhocComponents = $adhocQuery->paginate(10, ['*'], 'adhoc_page')->withQueryString();
        $salaryStructures = $salaryStructuresQuery->paginate(10, ['*'], 'struct_page')->withQueryString();

        return compact(
            'companies',
            'payGroups',
            'selectedPayGroup',
            'salaryComponents',
            'salaryStructures',
            'recurringComponents',
            'adhocComponents'
        );
    }

    public function storeStructure(array $validated): SalaryStructure
    {
        return DB::transaction(function () use ($validated) {
            $salaryStructure = SalaryStructure::create([
                'company_id' => $validated['company_id'] ?? (\App\Domains\HRMS\Models\Company::first()?->id ?? 1),
                'pay_group_id' => $validated['pay_group_id'] ?? null,
                'name' => $validated['name'],
                'min_ctc' => $validated['min_ctc'],
                'max_ctc' => $validated['max_ctc'],
                'status' => $validated['status'],
            ]);

            if (isset($validated['components']) && is_array($validated['components'])) {
                foreach ($validated['components'] as $componentId => $itemData) {
                    if (isset($itemData['calculation_type']) && $itemData['calculation_type'] !== 'not_included') {
                        SalaryStructureItem::create([
                            'salary_structure_id' => $salaryStructure->id,
                            'salary_component_id' => $componentId,
                            'calculation_type' => $itemData['calculation_type'],
                            'value' => $itemData['value'] ?? 0,
                        ]);
                    }
                }
            }

            return $salaryStructure;
        });
    }

    public function updateStructure(SalaryStructure $salaryStructure, array $validated): bool
    {
        return DB::transaction(function () use ($salaryStructure, $validated) {
            $salaryStructure->update([
                'company_id' => $validated['company_id'] ?? $salaryStructure->company_id ?? (\App\Domains\HRMS\Models\Company::first()?->id ?? 1),
                'pay_group_id' => $validated['pay_group_id'] ?? $salaryStructure->pay_group_id,
                'name' => $validated['name'],
                'min_ctc' => $validated['min_ctc'],
                'max_ctc' => $validated['max_ctc'],
                'status' => $validated['status'],
            ]);

            SalaryStructureItem::where('salary_structure_id', $salaryStructure->id)->delete();

            if (isset($validated['components']) && is_array($validated['components'])) {
                foreach ($validated['components'] as $componentId => $itemData) {
                    if (isset($itemData['calculation_type']) && $itemData['calculation_type'] !== 'not_included') {
                        SalaryStructureItem::create([
                            'salary_structure_id' => $salaryStructure->id,
                            'salary_component_id' => $componentId,
                            'calculation_type' => $itemData['calculation_type'],
                            'value' => $itemData['value'] ?? 0,
                        ]);
                    }
                }
            }

            return true;
        });
    }

    public function destroyStructure(SalaryStructure $salaryStructure): bool
    {
        return $salaryStructure->delete();
    }

    public function storeComponent(array $validated): SalaryComponent
    {
        if (empty($validated['company_id'])) {
            $validated['company_id'] = \App\Domains\HRMS\Models\Company::first()?->id ?? 1;
        }
        return SalaryComponent::create($validated);
    }

    public function updateComponent(SalaryComponent $salaryComponent, array $validated): bool
    {
        return $salaryComponent->update($validated);
    }

    public function destroyComponent(SalaryComponent $salaryComponent): bool
    {
        return $salaryComponent->delete();
    }
}
