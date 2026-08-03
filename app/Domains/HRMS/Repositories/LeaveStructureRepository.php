<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;

class LeaveStructureRepository implements LeaveStructureRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        $companies = Company::all();
        $leavePlansQuery = LeavePlan::with(['company', 'types']);
        if (isset($inputs['lp_status']) && $inputs['lp_status'] !== '') {
            $leavePlansQuery->where('status', $inputs['lp_status']);
        }
        if (!empty($inputs['lp_company'])) {
            $leavePlansQuery->where('company_id', $inputs['lp_company']);
        }
        if (!empty($inputs['lp_search'])) {
            $leavePlansQuery->where('name', 'like', '%' . $inputs['lp_search'] . '%');
        }
        if (!empty($inputs['lp_sort'])) {
            $lpSort = $inputs['lp_sort'];
            if ($lpSort === 'name_asc') {
                $leavePlansQuery->orderBy('name', 'asc');
            } elseif ($lpSort === 'name_desc') {
                $leavePlansQuery->orderBy('name', 'desc');
            } elseif ($lpSort === 'newest') {
                $leavePlansQuery->orderBy('created_at', 'desc');
            }
        } else {
            $leavePlansQuery->orderBy('name', 'asc');
        }
        $leavePlans = $leavePlansQuery->get();

        $selectedPlanId = $inputs['plan_id'] ?? null;
        $selectedPlan = null;
        if ($selectedPlanId) {
            $selectedPlan = $leavePlans->firstWhere('id', $selectedPlanId);
        }
        if (!$selectedPlan && $leavePlans->isNotEmpty()) {
            $selectedPlan = $leavePlans->first();
        }

        $leaveTypes = collect();

        $ltSearch = trim((string) ($inputs['lt_search'] ?? ''));
        $ltSort = !empty($inputs['lt_sort']) ? (string) $inputs['lt_sort'] : 'name_asc';
        $ltType = isset($inputs['lt_type']) && $inputs['lt_type'] !== '' ? (string) $inputs['lt_type'] : null;

        if ($selectedPlan) {
            $typesQuery = $selectedPlan->types();

            if ($ltSearch !== '') {
                $typesQuery->where(function ($query) use ($ltSearch): void {
                    $query->where('name', 'like', "%{$ltSearch}%")
                        ->orWhere('code', 'like', "%{$ltSearch}%");
                });
            }

            if ($ltType !== null && $ltType !== '') {
                $typesQuery->where('type', $ltType);
            }

            switch ($ltSort) {
                case 'name_desc':
                    $typesQuery->orderBy('name', 'desc');
                    break;
                case 'quota_asc':
                    $typesQuery->orderBy('quota', 'asc');
                    break;
                case 'quota_desc':
                    $typesQuery->orderBy('quota', 'desc');
                    break;
                case 'name_asc':
                default:
                    $typesQuery->orderBy('name', 'asc');
                    break;
            }

            $leaveTypes = $typesQuery->paginate(10, ['*'], 'lt_page')->withQueryString();
        }

        return compact('companies', 'leavePlans', 'leaveTypes', 'selectedPlan', 'ltSearch', 'ltSort', 'ltType');
    }

    public function storePlan(array $validated): LeavePlan
    {
        return LeavePlan::create($validated);
    }

    public function updatePlan(LeavePlan $leavePlan, array $validated): bool
    {
        return $leavePlan->update($validated);
    }

    public function destroyPlan(LeavePlan $leavePlan): bool
    {
        return $leavePlan->delete();
    }

    public function storeType(array $validated): LeaveType
    {
        return LeaveType::create($validated);
    }

    public function updateType(LeaveType $leaveType, array $validated): bool
    {
        return $leaveType->update($validated);
    }

    public function destroyType(LeaveType $leaveType): bool
    {
        return $leaveType->delete();
    }
}
