<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\PipCategory;
use App\Domains\HRMS\Models\PipCheckin;
use App\Domains\HRMS\Models\PipObjective;
use App\Domains\HRMS\Models\PipPolicyTemplate;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Domains\HRMS\Services\PipService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PipRepository implements PipRepositoryInterface
{
    public function __construct(
        private readonly PipService $pipService,
        private readonly HrmsScopeService $scopeService
    ) {}

    public function getIndexData(array $inputs, ?User $user, int $tenantId): array
    {
        $activeTab = $inputs['active_tab'] ?? ($inputs['tab'] ?? 'plans');
        $search = $inputs['search'] ?? null;
        $status = $inputs['status'] ?? null;
        $departmentId = $inputs['department_id'] ?? null;
        $categoryId = $inputs['category_id'] ?? null;

        $plansQuery = PerformanceImprovementPlan::query()->where('tenant_id', $tenantId);
        $this->scopeService->applyRelatedScope($plansQuery, $user);
        $plansQuery->with(['employee.department', 'employee.designation', 'manager', 'hrRepresentative', 'category', 'objectives', 'checkins']);

        if ($search) {
            $plansQuery->where(function ($q) use ($search) {
                $q->where('pip_number', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($status) {
            $plansQuery->where('status', $status);
        }

        if ($departmentId) {
            $plansQuery->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($categoryId) {
            $plansQuery->where('pip_category_id', $categoryId);
        }

        $sort = $inputs['sort'] ?? 'newest';
        match ($sort) {
            'oldest'   => $plansQuery->oldest('id'),
            'pip_asc'  => $plansQuery->orderBy('pip_number', 'asc'),
            'pip_desc' => $plansQuery->orderBy('pip_number', 'desc'),
            default    => $plansQuery->latest('id'),
        };

        $plans = $plansQuery->paginate(15)->appends($inputs);

        $baseStats = PerformanceImprovementPlan::where('tenant_id', $tenantId);
        $this->scopeService->applyRelatedScope($baseStats, $user);
        $totalActive = (clone $baseStats)->whereIn('status', ['active', 'under_review', 'extended'])->count();
        $onTrackCount = (clone $baseStats)->where('status', 'active')->count();
        $atRiskCount = (clone $baseStats)->where('status', 'under_review')->count();
        $completedCount = (clone $baseStats)->where('status', 'completed_success')->count();
        $terminatedCount = (clone $baseStats)->where('status', 'failed_terminated')->count();

        $categoriesList = PipCategory::where('tenant_id', $tenantId)->get();
        $policyTemplatesList = PipPolicyTemplate::where('tenant_id', $tenantId)->get();

        $categories = PipCategory::where('tenant_id', $tenantId)->latest('id')->paginate(10, ['*'], 'categories_page')->appends($inputs);
        $policyTemplates = PipPolicyTemplate::where('tenant_id', $tenantId)->latest('id')->paginate(10, ['*'], 'templates_page')->appends($inputs);

        $employees = Employee::where('tenant_id', $tenantId)->orderBy('full_name')->get();
        $departments = Department::where('tenant_id', $tenantId)->get();

        return compact(
            'plans',
            'categories',
            'categoriesList',
            'policyTemplates',
            'policyTemplatesList',
            'employees',
            'departments',
            'totalActive',
            'onTrackCount',
            'atRiskCount',
            'completedCount',
            'terminatedCount',
            'activeTab'
        );
    }

    public function getShowData(int $id, int $tenantId): array
    {
        $plan = PerformanceImprovementPlan::with([
            'employee.department',
            'employee.designation',
            'manager',
            'hrRepresentative',
            'category',
            'template',
            'objectives',
            'checkins.conductor'
        ])->where('tenant_id', $tenantId)->findOrFail($id);

        $categories = PipCategory::where('tenant_id', $tenantId)->get();
        $employees = Employee::where('tenant_id', $tenantId)->orderBy('full_name')->get();

        return compact('plan', 'categories', 'employees');
    }

    public function storePlan(array $validated, int $tenantId): PerformanceImprovementPlan
    {
        $validated['tenant_id'] = $tenantId;
        return $this->pipService->createPlan($validated);
    }

    public function updatePlan(int $id, array $validated, int $tenantId): PerformanceImprovementPlan
    {
        $plan = PerformanceImprovementPlan::where('tenant_id', $tenantId)->findOrFail($id);
        $plan->update($validated);
        return $plan;
    }

    public function deletePlan(int $id, int $tenantId): bool
    {
        $plan = PerformanceImprovementPlan::where('tenant_id', $tenantId)->findOrFail($id);
        return DB::transaction(function () use ($plan) {
            $plan->objectives()->delete();
            $plan->checkins()->delete();
            return (bool) $plan->delete();
        });
    }

    public function storeObjective(int $pipId, array $validated, int $tenantId): PipObjective
    {
        $plan = PerformanceImprovementPlan::where('tenant_id', $tenantId)->findOrFail($pipId);
        $validated['tenant_id'] = $tenantId;
        $validated['pip_id'] = $plan->id;

        return PipObjective::create($validated);
    }

    public function updateObjective(int $objectiveId, array $validated, int $tenantId): PipObjective
    {
        $objective = PipObjective::where('tenant_id', $tenantId)->findOrFail($objectiveId);
        $objective->update($validated);
        return $objective;
    }

    public function deleteObjective(int $objectiveId, int $tenantId): bool
    {
        $objective = PipObjective::where('tenant_id', $tenantId)->findOrFail($objectiveId);
        return (bool) $objective->delete();
    }

    public function storeCheckin(int $pipId, array $validated, ?int $loggedById, int $tenantId): PipCheckin
    {
        $plan = PerformanceImprovementPlan::where('tenant_id', $tenantId)->findOrFail($pipId);

        $validated['tenant_id'] = $tenantId;
        $validated['pip_id'] = $plan->id;
        $validated['conducted_by_id'] = $loggedById;

        $checkin = PipCheckin::create($validated);

        if (!empty($validated['next_checkin_date'])) {
            $plan->update(['next_checkin_date' => $validated['next_checkin_date']]);
        }

        return $checkin;
    }

    public function acknowledge(int $id, string $signatureData, int $tenantId): PerformanceImprovementPlan
    {
        $plan = PerformanceImprovementPlan::with(['employee', 'manager'])->where('tenant_id', $tenantId)->findOrFail($id);
        $plan->update([
            'employee_acknowledged_at' => Carbon::now(),
            'employee_signature' => $signatureData,
        ]);

        try {
            \App\Services\Notification\NotificationService::sendToHrAdmins(
                'PIP Acknowledged by Employee',
                "Employee {$plan->employee?->full_name} has digitally acknowledged PIP #{$plan->pip_number}.",
                route('hrms.pip.show', $plan->id),
                'pip_acknowledged',
                'feather-check-square'
            );
        } catch (\Throwable $e) {
            // Suppress notification errors
        }

        return $plan;
    }

    public function conclude(int $id, array $validated, int $tenantId): PerformanceImprovementPlan
    {
        $plan = PerformanceImprovementPlan::where('tenant_id', $tenantId)->findOrFail($id);
        return $this->pipService->evaluateFinalOutcome($plan, $validated);
    }

    public function storeCategory(array $validated, int $tenantId): PipCategory
    {
        $validated['tenant_id'] = $tenantId;
        return PipCategory::create($validated);
    }

    public function storeTemplate(array $validated, int $tenantId): PipPolicyTemplate
    {
        $validated['tenant_id'] = $tenantId;
        return PipPolicyTemplate::create($validated);
    }
}
