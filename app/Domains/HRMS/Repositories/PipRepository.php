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
    ) {
        $this->ensureSchemaUpToDate();
    }

    public function ensureSchemaUpToDate(): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('performance_improvement_plans', 'employee_acknowledged_at')
                || !\Illuminate\Support\Facades\Schema::hasColumn('pip_checkins', 'rating')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            // Silently fallback if migration runner unavailable
        }
    }

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

        $categoriesQuery = PipCategory::where('tenant_id', $tenantId);

        if ($activeTab === 'categories') {
            if ($search) {
                $categoriesQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($status) {
                if ($status === 'active') {
                    $categoriesQuery->where(function ($q) {
                        $q->where('status', 'active')
                          ->orWhereNull('status')
                          ->orWhere('status', '1');
                    });
                } else {
                    $categoriesQuery->where(function ($q) use ($status) {
                        $q->where('status', $status)
                          ->orWhere('status', '0');
                    });
                }
            }

            $catSort = $inputs['sort'] ?? 'newest';
            match ($catSort) {
                'oldest'    => $categoriesQuery->oldest('id'),
                'name_asc'  => $categoriesQuery->orderBy('name', 'asc'),
                'name_desc' => $categoriesQuery->orderBy('name', 'desc'),
                default     => $categoriesQuery->latest('id'),
            };
        } else {
            $categoriesQuery->latest('id');
        }

        $categories = $categoriesQuery->paginate(10, ['*'], 'categories_page')->appends($inputs);
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

        $user = auth()->user();
        $employee = $user ? Employee::resolveForUser($user) : null;
        $isHrAdmin = $user && (
            $user->hasHrPermission('hrms.pip.manage')
            || $user->hasHrPermission('hrms.performance.manage')
            || $user->hasHrPermission('hr.settings.manage')
        );
        $canManagePip = $isHrAdmin
            || ($employee && $plan->manager_id === $employee->id)
            || ($plan->hr_representative_id === $user?->id);
        $canAcknowledge = ($employee && $plan->employee_id === $employee->id && empty($plan->employee_acknowledged_at));

        $pip = $plan;
        return compact('plan', 'pip', 'categories', 'employees', 'canManagePip', 'canAcknowledge');
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

        $data = [
            'tenant_id'            => $tenantId,
            'pip_id'               => $plan->id,
            'review_date'          => $validated['checkin_date'] ?? $validated['review_date'] ?? $validated['meeting_date'] ?? Carbon::today()->toDateString(),
            'reviewer_id'          => $loggedById,
            'rating_status'        => $validated['rating_status'] ?? 'on_track',
            'rating'               => $validated['rating'] ?? null,
            'manager_comments'     => $validated['manager_comments'] ?? $validated['progress_summary'] ?? null,
            'employee_comments'    => $validated['employee_comments'] ?? null,
            'action_items'         => $validated['action_items'] ?? null,
            'concerns_noted'       => $validated['concerns_noted'] ?? null,
            'support_action_items' => $validated['support_action_items'] ?? null,
            'next_checkin_date'    => $validated['next_checkin_date'] ?? null,
        ];

        return $this->pipService->createCheckin($plan, $data);
    }

    public function updateCheckin(int $checkinId, array $validated, int $tenantId): PipCheckin
    {
        $checkin = PipCheckin::where('tenant_id', $tenantId)->findOrFail($checkinId);

        $data = [];
        if (isset($validated['checkin_date']) || isset($validated['review_date'])) {
            $data['review_date'] = $validated['checkin_date'] ?? $validated['review_date'];
        }
        if (isset($validated['rating_status'])) {
            $data['rating_status'] = $validated['rating_status'];
        }
        if (isset($validated['rating'])) {
            $data['rating'] = $validated['rating'];
        }
        if (isset($validated['manager_comments']) || isset($validated['progress_summary'])) {
            $data['manager_comments'] = $validated['manager_comments'] ?? $validated['progress_summary'];
        }
        if (isset($validated['employee_comments'])) {
            $data['employee_comments'] = $validated['employee_comments'];
        }
        if (isset($validated['action_items'])) {
            $data['action_items'] = $validated['action_items'];
        }
        if (isset($validated['concerns_noted'])) {
            $data['concerns_noted'] = $validated['concerns_noted'];
        }
        if (isset($validated['support_action_items'])) {
            $data['support_action_items'] = $validated['support_action_items'];
        }
        if (isset($validated['next_checkin_date'])) {
            $data['next_checkin_date'] = $validated['next_checkin_date'];
        }

        $checkin->update($data);
        return $checkin;
    }

    public function deleteCheckin(int $checkinId, int $tenantId): bool
    {
        $checkin = PipCheckin::where('tenant_id', $tenantId)->findOrFail($checkinId);
        return (bool) $checkin->delete();
    }

    public function acknowledge(int $id, string $signatureData, int $tenantId): PerformanceImprovementPlan
    {
        $plan = PerformanceImprovementPlan::with(['employee', 'manager'])->where('tenant_id', $tenantId)->findOrFail($id);
        $plan->update([
            'employee_acknowledged_at' => Carbon::now(),
            'employee_signed_at' => Carbon::now(),
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
