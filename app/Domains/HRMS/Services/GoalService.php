<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Goal;
use App\Domains\HRMS\Models\GoalCategory;
use App\Domains\HRMS\Models\GoalCheckIn;
use App\Domains\HRMS\Models\GoalCycle;
use App\Domains\HRMS\Models\GoalKeyResult;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GoalService
{
    /**
     * Ensure goal_employees pivot table exists.
     */
    public function ensureGoalEmployeesTable(): void
    {
        if (!Schema::hasTable('goal_employees')) {
            Schema::create('goal_employees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('goal_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->timestamps();

                $table->foreign('goal_id')->references('id')->on('goals')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->unique(['goal_id', 'employee_id']);
            });
        }
    }

    /**
     * Generate unique sequential Goal Code (e.g. G-2026-001).
     */
    public function generateGoalCode(int $tenantId): string
    {
        $year = date('Y');
        $count = Goal::where('tenant_id', $tenantId)->withTrashed()->count() + 1;
        return sprintf('G-%s-%03d', $year, $count);
    }

    /**
     * Create a new Goal / Objective with key results.
     */
    public function createGoal(array $data, int $tenantId, ?User $user = null): Goal
    {
        return DB::transaction(function () use ($data, $tenantId, $user) {
            $this->ensureGoalEmployeesTable();

            $code = !empty($data['code']) ? trim($data['code']) : $this->generateGoalCode($tenantId);

            // Handle Custom / On-the-fly Goal Cycle
            if (!empty($data['custom_goal_cycle'])) {
                $cycleName = trim($data['custom_goal_cycle']);
                $cycle = GoalCycle::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => $cycleName],
                    [
                        'company_id'  => $data['company_id'] ?? $user?->company_id,
                        'code'        => 'CYC-' . strtoupper(Str::random(4)),
                        'start_date'  => now()->startOfYear()->toDateString(),
                        'end_date'    => now()->endOfYear()->toDateString(),
                        'status'      => 'active',
                        'description' => 'Custom created goal cycle',
                    ]
                );
                $data['goal_cycle_id'] = $cycle->id;
            } elseif (($data['goal_cycle_id'] ?? null) === '__custom__') {
                $data['goal_cycle_id'] = null;
            }

            // Handle Custom / On-the-fly Strategic Pillar
            if (!empty($data['custom_goal_category'])) {
                $catName = trim($data['custom_goal_category']);
                $cat = GoalCategory::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => $catName],
                    [
                        'company_id'  => $data['company_id'] ?? $user?->company_id,
                        'code'        => 'CAT-' . strtoupper(Str::random(4)),
                        'color'       => '#852d3c',
                        'icon'        => 'feather-target',
                        'status'      => 'active',
                        'description' => 'Custom created strategic pillar',
                    ]
                );
                $data['goal_category_id'] = $cat->id;
            } elseif (($data['goal_category_id'] ?? null) === '__custom__') {
                $data['goal_category_id'] = null;
            }

            $employeeIds = [];
            if (!empty($data['employee_ids']) && is_array($data['employee_ids'])) {
                $employeeIds = array_values(array_filter(array_map('intval', $data['employee_ids'])));
            } elseif (!empty($data['employee_id']) && is_numeric($data['employee_id'])) {
                $employeeIds = [(int) $data['employee_id']];
            }

            $primaryEmployeeId = !empty($data['employee_id']) && is_numeric($data['employee_id']) 
                ? (int) $data['employee_id'] 
                : (!empty($employeeIds) ? $employeeIds[0] : null);

            $goalCycleId = !empty($data['goal_cycle_id']) && is_numeric($data['goal_cycle_id']) ? (int) $data['goal_cycle_id'] : null;
            $goalCategoryId = !empty($data['goal_category_id']) && is_numeric($data['goal_category_id']) ? (int) $data['goal_category_id'] : null;
            $departmentId = !empty($data['department_id']) && is_numeric($data['department_id']) ? (int) $data['department_id'] : null;
            $parentGoalId = !empty($data['parent_goal_id']) && is_numeric($data['parent_goal_id']) ? (int) $data['parent_goal_id'] : null;

            $goal = Goal::create([
                'tenant_id'           => $tenantId,
                'company_id'          => $data['company_id'] ?? $user?->company_id,
                'code'                => $code,
                'title'               => $data['title'],
                'description'         => $data['description'] ?? null,
                'goal_cycle_id'       => $goalCycleId,
                'goal_category_id'    => $goalCategoryId,
                'owner_type'          => $data['owner_type'] ?? 'employee',
                'department_id'       => $departmentId,
                'employee_id'         => $primaryEmployeeId,
                'parent_goal_id'      => $parentGoalId,
                'visibility'          => $data['visibility'] ?? 'public',
                'priority'            => $data['priority'] ?? 'medium',
                'start_date'          => !empty($data['start_date']) ? $data['start_date'] : null,
                'due_date'            => !empty($data['due_date']) ? $data['due_date'] : null,
                'weightage'           => !empty($data['weightage']) ? (float) $data['weightage'] : 100.00,
                'progress_percentage' => 0.00,
                'health_status'       => $data['health_status'] ?? 'on_track',
                'status'              => $data['status'] ?? 'active',
                'created_by'          => $user?->id,
                'approved_by'         => ($data['status'] ?? 'active') === 'active' ? $user?->id : null,
                'approved_at'         => ($data['status'] ?? 'active') === 'active' ? now() : null,
            ]);

            // Sync employees into pivot table
            if (!empty($employeeIds)) {
                $syncData = [];
                foreach ($employeeIds as $empId) {
                    $syncData[$empId] = ['tenant_id' => $tenantId];
                }
                $goal->employees()->sync($syncData);
            }

            // Create Key Results if provided
            if (!empty($data['key_results']) && is_array($data['key_results'])) {
                foreach ($data['key_results'] as $krData) {
                    if (empty($krData['title'])) {
                        continue;
                    }

                    $kr = GoalKeyResult::create([
                        'tenant_id'           => $tenantId,
                        'goal_id'             => $goal->id,
                        'title'               => $krData['title'],
                        'description'         => $krData['description'] ?? null,
                        'metric_type'         => $krData['metric_type'] ?? 'percentage',
                        'unit'                => $krData['unit'] ?? '%',
                        'start_value'         => (float) ($krData['start_value'] ?? 0),
                        'target_value'        => (float) ($krData['target_value'] ?? 100),
                        'current_value'       => (float) ($krData['current_value'] ?? ($krData['start_value'] ?? 0)),
                        'weightage'           => (float) ($krData['weightage'] ?? 100),
                        'progress_percentage' => 0.00,
                        'health_status'       => $krData['health_status'] ?? 'on_track',
                        'owner_id'            => $krData['owner_id'] ?? $goal->employee_id,
                        'due_date'            => $krData['due_date'] ?? $goal->due_date,
                    ]);

                    $kr->progress_percentage = $kr->calculateProgress();
                    $kr->save();
                }

                $goal->load('keyResults');
                $goal->recalculateProgress();
            }

            return $goal;
        });
    }

    /**
     * Update an existing Goal / Objective.
     */
    public function updateGoal(Goal $goal, array $data, ?User $user = null): Goal
    {
        return DB::transaction(function () use ($goal, $data) {
            $this->ensureGoalEmployeesTable();

            $employeeIds = null;
            if (isset($data['employee_ids']) && is_array($data['employee_ids'])) {
                $employeeIds = array_values(array_filter(array_map('intval', $data['employee_ids'])));
            }

            // Handle Custom / On-the-fly Goal Cycle
            if (!empty($data['custom_goal_cycle'])) {
                $cycleName = trim($data['custom_goal_cycle']);
                $cycle = GoalCycle::firstOrCreate(
                    ['tenant_id' => $goal->tenant_id, 'name' => $cycleName],
                    [
                        'company_id'  => $goal->company_id,
                        'code'        => 'CYC-' . strtoupper(Str::random(4)),
                        'start_date'  => now()->startOfYear()->toDateString(),
                        'end_date'    => now()->endOfYear()->toDateString(),
                        'status'      => 'active',
                        'description' => 'Custom created goal cycle',
                    ]
                );
                $data['goal_cycle_id'] = $cycle->id;
            } elseif (($data['goal_cycle_id'] ?? null) === '__custom__') {
                $data['goal_cycle_id'] = null;
            }

            // Handle Custom / On-the-fly Strategic Pillar
            if (!empty($data['custom_goal_category'])) {
                $catName = trim($data['custom_goal_category']);
                $cat = GoalCategory::firstOrCreate(
                    ['tenant_id' => $goal->tenant_id, 'name' => $catName],
                    [
                        'company_id'  => $goal->company_id,
                        'code'        => 'CAT-' . strtoupper(Str::random(4)),
                        'color'       => '#852d3c',
                        'icon'        => 'feather-target',
                        'status'      => 'active',
                        'description' => 'Custom created strategic pillar',
                    ]
                );
                $data['goal_category_id'] = $cat->id;
            } elseif (($data['goal_category_id'] ?? null) === '__custom__') {
                $data['goal_category_id'] = null;
            }

            $primaryEmployeeId = !empty($data['employee_id']) && is_numeric($data['employee_id']) 
                ? (int) $data['employee_id'] 
                : ($employeeIds && count($employeeIds) > 0 ? $employeeIds[0] : $goal->employee_id);

            $goalCycleId = array_key_exists('goal_cycle_id', $data) 
                ? (!empty($data['goal_cycle_id']) && is_numeric($data['goal_cycle_id']) ? (int) $data['goal_cycle_id'] : null) 
                : $goal->goal_cycle_id;

            $goalCategoryId = array_key_exists('goal_category_id', $data) 
                ? (!empty($data['goal_category_id']) && is_numeric($data['goal_category_id']) ? (int) $data['goal_category_id'] : null) 
                : $goal->goal_category_id;

            $departmentId = array_key_exists('department_id', $data) 
                ? (!empty($data['department_id']) && is_numeric($data['department_id']) ? (int) $data['department_id'] : null) 
                : $goal->department_id;

            $parentGoalId = array_key_exists('parent_goal_id', $data) 
                ? (!empty($data['parent_goal_id']) && is_numeric($data['parent_goal_id']) ? (int) $data['parent_goal_id'] : null) 
                : $goal->parent_goal_id;

            $goal->update([
                'title'            => $data['title'] ?? $goal->title,
                'description'      => array_key_exists('description', $data) ? $data['description'] : $goal->description,
                'goal_cycle_id'    => $goalCycleId,
                'goal_category_id' => $goalCategoryId,
                'owner_type'       => $data['owner_type'] ?? $goal->owner_type,
                'department_id'    => $departmentId,
                'employee_id'      => $primaryEmployeeId,
                'parent_goal_id'   => $parentGoalId,
                'visibility'       => $data['visibility'] ?? $goal->visibility,
                'priority'         => $data['priority'] ?? $goal->priority,
                'start_date'       => array_key_exists('start_date', $data) ? (!empty($data['start_date']) ? $data['start_date'] : null) : $goal->start_date,
                'due_date'         => array_key_exists('due_date', $data) ? (!empty($data['due_date']) ? $data['due_date'] : null) : $goal->due_date,
                'weightage'        => array_key_exists('weightage', $data) ? (!empty($data['weightage']) ? (float) $data['weightage'] : 100.00) : $goal->weightage,
                'health_status'    => $data['health_status'] ?? $goal->health_status,
                'status'           => $data['status'] ?? $goal->status,
            ]);

            if ($employeeIds !== null) {
                $syncData = [];
                foreach ($employeeIds as $empId) {
                    $syncData[$empId] = ['tenant_id' => $goal->tenant_id];
                }
                $goal->employees()->sync($syncData);
            }

            // Sync Key Results if provided
            if (isset($data['key_results']) && is_array($data['key_results'])) {
                $existingIds = [];
                foreach ($data['key_results'] as $krData) {
                    if (empty($krData['title'])) {
                        continue;
                    }

                    if (!empty($krData['id'])) {
                        $kr = GoalKeyResult::where('goal_id', $goal->id)->find($krData['id']);
                        if ($kr) {
                            $kr->update([
                                'title'         => $krData['title'],
                                'description'   => $krData['description'] ?? $kr->description,
                                'metric_type'   => $krData['metric_type'] ?? $kr->metric_type,
                                'unit'          => $krData['unit'] ?? $kr->unit,
                                'start_value'   => (float) ($krData['start_value'] ?? $kr->start_value),
                                'target_value'  => (float) ($krData['target_value'] ?? $kr->target_value),
                                'current_value' => (float) ($krData['current_value'] ?? $kr->current_value),
                                'weightage'     => (float) ($krData['weightage'] ?? $kr->weightage),
                                'health_status' => $krData['health_status'] ?? $kr->health_status,
                                'owner_id'      => $krData['owner_id'] ?? $kr->owner_id,
                                'due_date'      => $krData['due_date'] ?? $kr->due_date,
                            ]);
                            $kr->progress_percentage = $kr->calculateProgress();
                            $kr->save();
                            $existingIds[] = $kr->id;
                        }
                    } else {
                        $newKr = GoalKeyResult::create([
                            'tenant_id'           => $goal->tenant_id,
                            'goal_id'             => $goal->id,
                            'title'               => $krData['title'],
                            'description'         => $krData['description'] ?? null,
                            'metric_type'         => $krData['metric_type'] ?? 'percentage',
                            'unit'                => $krData['unit'] ?? '%',
                            'start_value'         => (float) ($krData['start_value'] ?? 0),
                            'target_value'        => (float) ($krData['target_value'] ?? 100),
                            'current_value'       => (float) ($krData['current_value'] ?? 0),
                            'weightage'           => (float) ($krData['weightage'] ?? 100),
                            'progress_percentage' => 0.00,
                            'health_status'       => $krData['health_status'] ?? 'on_track',
                            'owner_id'            => $krData['owner_id'] ?? $goal->employee_id,
                            'due_date'            => $krData['due_date'] ?? $goal->due_date,
                        ]);
                        $newKr->progress_percentage = $newKr->calculateProgress();
                        $newKr->save();
                        $existingIds[] = $newKr->id;
                    }
                }

                // Delete removed key results
                if (!empty($existingIds)) {
                    GoalKeyResult::where('goal_id', $goal->id)->whereNotIn('id', $existingIds)->delete();
                }
            }

            $goal->load('keyResults');
            $goal->recalculateProgress();

            return $goal;
        });
    }

    /**
     * Record a continuous check-in update on a Goal / Key Result.
     */
    public function recordCheckIn(Goal $goal, array $data, ?User $user = null, ?Employee $employee = null): GoalCheckIn
    {
        return DB::transaction(function () use ($goal, $data, $user, $employee) {
            $krId = $data['goal_key_result_id'] ?? null;
            $prevVal = null;
            $newVal = null;
            $prevProg = $goal->progress_percentage;

            $kr = null;
            if ($krId) {
                $kr = GoalKeyResult::where('goal_id', $goal->id)->find($krId);
            }

            if ($kr) {
                $prevVal = $kr->current_value;
                $newVal = isset($data['new_value']) ? (float) $data['new_value'] : $kr->current_value;
                $kr->current_value = $newVal;
                $kr->health_status = $data['health_status'] ?? $kr->health_status;
                $kr->progress_percentage = $kr->calculateProgress();
                $kr->save();

                $goal->load('keyResults');
                $goal->recalculateProgress();
            } else {
                // Direct progress percentage update if no key result
                if (isset($data['new_progress'])) {
                    $goal->progress_percentage = (float) min(100.0, max(0.0, $data['new_progress']));
                }
            }

            if (!empty($data['health_status'])) {
                $goal->health_status = $data['health_status'];
            }
            $goal->save();

            $checkIn = GoalCheckIn::create([
                'tenant_id'          => $goal->tenant_id,
                'goal_id'            => $goal->id,
                'goal_key_result_id' => $kr?->id,
                'user_id'            => $user?->id,
                'employee_id'        => $employee?->id,
                'previous_value'     => $prevVal,
                'new_value'          => $newVal,
                'previous_progress'  => $prevProg,
                'new_progress'       => $goal->progress_percentage,
                'health_status'      => $data['health_status'] ?? $goal->health_status,
                'comment'            => $data['comment'] ?? 'Progress check-in logged.',
                'blockers'           => $data['blockers'] ?? null,
                'check_in_date'      => now(),
            ]);

            return $checkIn;
        });
    }

    /**
     * Build cascading hierarchical tree (Company Objectives -> Dept Goals -> Employee Goals).
     */
    public function getCascadingTree(int $tenantId, ?int $cycleId = null): array
    {
        $query = Goal::with(['category', 'department', 'employee', 'keyResults', 'childGoals.category', 'childGoals.employee', 'childGoals.department', 'childGoals.childGoals.category', 'childGoals.childGoals.employee'])
            ->where('tenant_id', $tenantId)
            ->whereNull('parent_goal_id');

        if ($cycleId) {
            $query->where('goal_cycle_id', $cycleId);
        }

        $rootGoals = $query->orderBy('priority', 'desc')->get();

        return $rootGoals->map(fn($g) => $this->transformTreeNode($g))->toArray();
    }

    private function transformTreeNode(Goal $goal): array
    {
        return [
            'id'                  => $goal->id,
            'code'                => $goal->code,
            'title'               => $goal->title,
            'owner_type'          => $goal->owner_type,
            'owner_name'          => match ($goal->owner_type) {
                'company'    => 'Organization-Wide',
                'department' => $goal->department?->name ?? 'Department',
                default      => $goal->employee?->full_name ?? 'Individual',
            },
            'category'            => $goal->category ? [
                'name'  => $goal->category->name,
                'color' => $goal->category->color,
                'icon'  => $goal->category->icon,
            ] : null,
            'progress_percentage' => (float) $goal->progress_percentage,
            'health_status'       => $goal->health_status,
            'priority'            => $goal->priority,
            'key_results_count'   => $goal->keyResults->count(),
            'children'            => $goal->childGoals->map(fn($child) => $this->transformTreeNode($child))->toArray(),
        ];
    }
}
