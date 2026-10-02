<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\AppraisalCycle;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeGoalItem;
use App\Domains\HRMS\Models\EmployeeGoalPlan;
use App\Domains\HRMS\Models\KpiTemplate;
use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\PipObjective;
use App\Domains\HRMS\Models\KraCategory;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class KraKpiService
{
    /**
     * Ensure all KRA & KPI database tables exist without requiring manual CLI migrations.
     */
    public function ensureTablesExist(int $tenantId): void
    {
        if (!Schema::hasTable('kra_categories')) {
            Schema::create('kra_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('color')->default('#3b82f6');
                $table->text('description')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('appraisal_cycles')) {
            Schema::create('appraisal_cycles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->enum('period_type', ['annual', 'semi_annual', 'quarterly', 'monthly'])->default('annual');
                $table->date('start_date');
                $table->date('end_date');
                $table->date('goal_setting_deadline')->nullable();
                $table->date('self_review_deadline')->nullable();
                $table->date('manager_review_deadline')->nullable();
                $table->enum('status', ['draft', 'goal_setting', 'in_progress', 'in_review', 'calibration', 'completed', 'archived'])->default('draft');
                $table->decimal('goal_weightage_percent', 5, 2)->default(70.00);
                $table->decimal('competency_weightage_percent', 5, 2)->default(30.00);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('kpi_masters')) {
            Schema::create('kpi_masters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('kra_category_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->enum('unit', ['percentage', 'currency', 'number', 'rating', 'boolean'])->default('percentage');
                $table->enum('calculation_type', ['higher_is_better', 'lower_is_better', 'milestone'])->default('higher_is_better');
                $table->decimal('default_target', 15, 2)->default(100.00);
                $table->decimal('default_weightage', 5, 2)->default(20.00);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('kpi_templates')) {
            Schema::create('kpi_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->unsignedBigInteger('designation_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('kpi_template_items')) {
            Schema::create('kpi_template_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('kpi_template_id')->index();
                $table->unsignedBigInteger('kra_category_id')->nullable()->index();
                $table->unsignedBigInteger('kpi_master_id')->nullable()->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('unit', ['percentage', 'currency', 'number', 'rating', 'boolean'])->default('percentage');
                $table->enum('calculation_type', ['higher_is_better', 'lower_is_better', 'milestone'])->default('higher_is_better');
                $table->decimal('target', 15, 2)->default(100.00);
                $table->decimal('weightage', 5, 2)->default(20.00);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employee_goal_plans')) {
            Schema::create('employee_goal_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('plan_number')->unique();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('appraisal_cycle_id')->index();
                $table->unsignedBigInteger('manager_id')->nullable()->index();
                $table->unsignedBigInteger('kpi_template_id')->nullable()->index();
                $table->enum('status', ['draft', 'submitted', 'approved', 'self_reviewed', 'manager_reviewed', 'calibrated', 'signed_off'])->default('draft');
                $table->decimal('total_weightage', 5, 2)->default(0.00);
                $table->decimal('goal_score', 6, 2)->nullable();
                $table->decimal('competency_score', 6, 2)->nullable();
                $table->decimal('final_score', 6, 2)->nullable();
                $table->string('final_grade')->nullable();
                $table->decimal('normalized_score', 6, 2)->nullable();
                $table->text('employee_comments')->nullable();
                $table->text('manager_comments')->nullable();
                $table->text('hr_comments')->nullable();
                $table->boolean('promotion_recommended')->default(false);
                $table->boolean('pip_triggered')->default(false);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('self_reviewed_at')->nullable();
                $table->timestamp('manager_reviewed_at')->nullable();
                $table->timestamp('calibrated_at')->nullable();
                $table->timestamp('signed_off_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('employee_goal_items')) {
            Schema::create('employee_goal_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_goal_plan_id')->index();
                $table->unsignedBigInteger('kra_category_id')->nullable()->index();
                $table->unsignedBigInteger('kpi_master_id')->nullable()->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('unit', ['percentage', 'currency', 'number', 'rating', 'boolean'])->default('percentage');
                $table->enum('calculation_type', ['higher_is_better', 'lower_is_better', 'milestone'])->default('higher_is_better');
                $table->decimal('target', 15, 2)->default(100.00);
                $table->decimal('actual', 15, 2)->nullable();
                $table->decimal('weightage', 5, 2)->default(20.00);
                $table->decimal('self_rating', 4, 2)->nullable();
                $table->decimal('self_score', 6, 2)->nullable();
                $table->text('self_comment')->nullable();
                $table->decimal('manager_rating', 4, 2)->nullable();
                $table->decimal('manager_score', 6, 2)->nullable();
                $table->text('manager_comment')->nullable();
                $table->decimal('final_score', 6, 2)->nullable();
                $table->enum('status', ['pending', 'in_progress', 'achieved', 'partially_achieved', 'not_achieved'])->default('pending');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('goal_progress_logs')) {
            Schema::create('goal_progress_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_goal_item_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('logged_by_id')->nullable()->index();
                $table->decimal('previous_value', 15, 2)->nullable();
                $table->decimal('current_value', 15, 2);
                $table->text('notes')->nullable();
                $table->string('attachment_path')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('appraisal_reviews')) {
            Schema::create('appraisal_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('employee_goal_plan_id')->index();
                $table->unsignedBigInteger('reviewer_id')->index();
                $table->enum('reviewer_role', ['self', 'manager', 'skip_level', 'hr_calibrator'])->default('manager');
                $table->decimal('overall_rating', 4, 2)->nullable();
                $table->decimal('overall_score', 6, 2)->nullable();
                $table->text('strengths')->nullable();
                $table->text('improvements')->nullable();
                $table->boolean('promotion_recommendation')->default(false);
                $table->decimal('increment_recommendation_percent', 5, 2)->nullable();
                $table->text('feedback_comments')->nullable();
                $table->enum('status', ['draft', 'submitted'])->default('draft');
                $table->timestamps();
            });
        }

        // Auto-seeding disabled: only user-defined KRAs, KPIs, Cycles and Plans will be tracked.
    }
    public function calculateItemScore(EmployeeGoalItem $item): float
    {
        $target = floatval($item->target);
        $actual = $item->actual !== null ? floatval($item->actual) : null;
        $managerRating = $item->manager_rating !== null ? floatval($item->manager_rating) : null;
        $selfRating = $item->self_rating !== null ? floatval($item->self_rating) : null;

        // If direct manager rating is provided (1-5 scale)
        if ($managerRating !== null && $managerRating > 0) {
            $score = ($managerRating / 5.0) * 100.0;
            return round(min(150, max(0, $score)), 2);
        }

        // If quantitative actual is logged against target
        if ($actual !== null && $target > 0) {
            if ($item->calculation_type === 'lower_is_better') {
                $score = $actual > 0 ? ($target / $actual) * 100.0 : 100.0;
            } elseif ($item->calculation_type === 'milestone') {
                $score = $actual >= $target ? 100.0 : 0.0;
            } else {
                // higher_is_better
                $score = ($actual / $target) * 100.0;
            }
            return round(min(150, max(0, $score)), 2);
        }

        // Fallback to self rating if available
        if ($selfRating !== null && $selfRating > 0) {
            $score = ($selfRating / 5.0) * 100.0;
            return round(min(150, max(0, $score)), 2);
        }

        return 0.0;
    }

    /**
     * Recalculate and update the overall composite score, grade, and status for a Goal Plan.
     */
    public function recalculatePlanScore(EmployeeGoalPlan $plan): EmployeeGoalPlan
    {
        $items = $plan->items()->get();
        $totalWeightage = $items->sum('weightage');
        $weightedGoalScore = 0.0;

        foreach ($items as $item) {
            $itemScore = $this->calculateItemScore($item);
            $item->final_score = $itemScore;
            
            if ($itemScore >= 100) {
                $item->status = 'achieved';
            } elseif ($itemScore >= 60) {
                $item->status = 'partially_achieved';
            } elseif ($item->actual !== null || $item->manager_rating !== null) {
                $item->status = 'not_achieved';
            } else {
                $item->status = 'in_progress';
            }
            $item->save();

            if ($totalWeightage > 0) {
                $weightedGoalScore += ($itemScore * (floatval($item->weightage) / $totalWeightage));
            }
        }

        $cycle = $plan->appraisalCycle;
        $goalWeightPercent = $cycle ? floatval($cycle->goal_weightage_percent) : 70.0;
        $compWeightPercent = $cycle ? floatval($cycle->competency_weightage_percent) : 30.0;
        $totalCycleWeight = ($goalWeightPercent + $compWeightPercent) ?: 100.0;

        $goalScore = round($weightedGoalScore, 2);
        $competencyScore = $plan->competency_score !== null ? floatval($plan->competency_score) : $goalScore;

        $finalScore = round((($goalScore * $goalWeightPercent) + ($competencyScore * $compWeightPercent)) / $totalCycleWeight, 2);

        // Grade Scale (O, A, B, C, D)
        $grade = 'Meets Expectations (B)';
        if ($finalScore >= 110) {
            $grade = 'Outstanding (O)';
        } elseif ($finalScore >= 90) {
            $grade = 'Exceeds Expectations (A)';
        } elseif ($finalScore >= 75) {
            $grade = 'Meets Expectations (B)';
        } elseif ($finalScore >= 60) {
            $grade = 'Needs Improvement (C)';
        } else {
            $grade = 'Unsatisfactory (D)';
        }

        $hasAnyEvaluations = $items->contains(function ($item) {
            return $item->actual !== null || $item->manager_rating !== null || $item->self_rating !== null;
        }) || in_array($plan->status, ['self_reviewed', 'manager_reviewed', 'calibrated', 'signed_off']);

        if (!$hasAnyEvaluations && in_array($plan->status, ['draft', 'submitted', 'approved', 'in_progress'])) {
            $plan->total_weightage = round($totalWeightage, 2);
            $plan->goal_score = null;
            $plan->final_score = null;
            $plan->final_grade = null;
            $plan->save();
            return $plan;
        }

        $plan->total_weightage = round($totalWeightage, 2);
        $plan->goal_score = $goalScore;
        $plan->final_score = $finalScore;
        $plan->final_grade = $grade;
        $plan->save();

        return $plan;
    }

    /**
     * Generate unique Scorecard Plan Number.
     */
    public function generatePlanNumber(int $tenantId): string
    {
        $year = date('Y');
        $prefix = "KRA-{$year}-";

        // Find highest existing sequence for this year across ALL records (including soft-deleted)
        $latest = EmployeeGoalPlan::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('plan_number', 'LIKE', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(plan_number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
            ->value('plan_number');

        $nextSeq = 1;
        if ($latest && preg_match('/KRA-\d{4}-(\d+)/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        // Guaranteed collision-free lookup
        do {
            $candidate = sprintf('KRA-%s-%04d', $year, $nextSeq);
            $exists = EmployeeGoalPlan::withTrashed()
                ->where('plan_number', $candidate)
                ->exists();
            if (!$exists) {
                return $candidate;
            }
            $nextSeq++;
        } while (true);
    }

    /**
     * Assign a KPI Template to an employee for an active Appraisal Cycle.
     */
    public function assignTemplateToEmployee(int $employeeId, int $cycleId, ?int $templateId, int $tenantId): EmployeeGoalPlan
    {
        return DB::transaction(function () use ($employeeId, $cycleId, $templateId, $tenantId) {
            $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);
            
            // Check if plan already exists for this cycle
            $existingPlan = EmployeeGoalPlan::with('items')->where('tenant_id', $tenantId)
                ->where('employee_id', $employeeId)
                ->where('appraisal_cycle_id', $cycleId)
                ->first();

            if ($existingPlan) {
                // If existing plan has no items and a template was chosen, populate the template items
                if ($templateId && $existingPlan->items->isEmpty()) {
                    $template = KpiTemplate::with('items')->where('tenant_id', $tenantId)->find($templateId);
                    if ($template && $template->items->isNotEmpty()) {
                        $existingPlan->update(['kpi_template_id' => $templateId]);
                        foreach ($template->items as $item) {
                            EmployeeGoalItem::create([
                                'tenant_id' => $tenantId,
                                'employee_goal_plan_id' => $existingPlan->id,
                                'kra_category_id' => $item->kra_category_id,
                                'kpi_master_id' => $item->kpi_master_id,
                                'title' => $item->title,
                                'description' => $item->description,
                                'unit' => $item->unit,
                                'calculation_type' => $item->calculation_type,
                                'target' => $item->target,
                                'weightage' => $item->weightage,
                                'status' => 'pending',
                            ]);
                        }
                        $this->recalculatePlanScore($existingPlan);
                    }
                }
                return $existingPlan;
            }

            $planNumber = $this->generatePlanNumber($tenantId);

            $plan = EmployeeGoalPlan::create([
                'tenant_id' => $tenantId,
                'company_id' => $employee->company_id,
                'plan_number' => $planNumber,
                'employee_id' => $employee->id,
                'appraisal_cycle_id' => $cycleId,
                'manager_id' => $employee->reporting_manager_id ?? $employee->reporting_to,
                'kpi_template_id' => $templateId,
                'status' => 'draft',
                'total_weightage' => 0.0,
            ]);

            if ($templateId) {
                $template = KpiTemplate::with('items')->where('tenant_id', $tenantId)->find($templateId);
                if ($template && $template->items->isNotEmpty()) {
                    foreach ($template->items as $item) {
                        EmployeeGoalItem::create([
                            'tenant_id' => $tenantId,
                            'employee_goal_plan_id' => $plan->id,
                            'kra_category_id' => $item->kra_category_id,
                            'kpi_master_id' => $item->kpi_master_id,
                            'title' => $item->title,
                            'description' => $item->description,
                            'unit' => $item->unit,
                            'calculation_type' => $item->calculation_type,
                            'target' => $item->target,
                            'weightage' => $item->weightage,
                            'status' => 'pending',
                        ]);
                    }
                }
            }

            $this->recalculatePlanScore($plan);

            // Notify Employee of Assigned Goal Sheet
            NotificationService::sendToEmployee(
                employee: $employee,
                title: 'New Performance Goal Sheet Assigned',
                message: "A new KPI goal scorecard ({$planNumber}) has been assigned to you for {$plan->appraisalCycle?->name}.",
                actionUrl: route('hrms.kra-kpi.show', $plan->id),
                module: 'hrms',
                type: 'performance',
                iconClass: 'feather-target'
            );

            return $plan;
        });
    }

    /**
     * 1-Click Bridge to PIP module if employee scored low on KRA/KPI appraisal.
     */
    public function triggerPipFromLowPerformance(EmployeeGoalPlan $plan, ?int $hrUserId = null): PerformanceImprovementPlan
    {
        return DB::transaction(function () use ($plan, $hrUserId) {
            $tenantId = $plan->tenant_id;
            $employee = $plan->employee;

            // Generate unique PIP number
            $year = date('Y');
            $pipPrefix = "PIP-{$year}-";
            $latestPip = PerformanceImprovementPlan::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('pip_number', 'LIKE', "{$pipPrefix}%")
                ->orderByRaw('CAST(SUBSTRING(pip_number, ' . (strlen($pipPrefix) + 1) . ') AS UNSIGNED) DESC')
                ->value('pip_number');

            $pipSeq = 1;
            if ($latestPip && preg_match('/PIP-\d{4}-(\d+)/', $latestPip, $m)) {
                $pipSeq = (int) $m[1] + 1;
            }

            do {
                $pipNumber = sprintf('PIP-%s-%04d', $year, $pipSeq);
                $exists = PerformanceImprovementPlan::withTrashed()
                    ->where('pip_number', $pipNumber)
                    ->exists();
                if (!$exists) {
                    break;
                }
                $pipSeq++;
            } while (true);

            $startDate = Carbon::today();
            $endDate = Carbon::today()->addDays(60);

            $pip = PerformanceImprovementPlan::create([
                'tenant_id' => $tenantId,
                'company_id' => $plan->company_id,
                'pip_number' => $pipNumber,
                'employee_id' => $plan->employee_id,
                'manager_id' => $plan->manager_id ?: ($employee->reporting_manager_id ?? $employee->reporting_to),
                'hr_representative_id' => $hrUserId ?: auth()->id(),
                'reason_category' => 'Annual KRA/KPI Appraisal Underperformance',
                'reason_details' => sprintf('Triggered from Appraisal Scorecard #%s. Overall Final Score: %s%% (%s). Specific goals failed to meet passing standard.', $plan->plan_number, $plan->final_score, $plan->final_grade),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'duration_days' => 60,
                'checkin_frequency' => 'biweekly',
                'status' => 'active',
            ]);

            // Add underperforming goal items as SMART objectives in the PIP
            $underperformingItems = $plan->items()->where('final_score', '<', 70)->get();
            if ($underperformingItems->isEmpty()) {
                $underperformingItems = $plan->items;
            }

            $weightPerItem = $underperformingItems->isNotEmpty() ? round(100.0 / $underperformingItems->count(), 2) : 100.0;

            foreach ($underperformingItems as $item) {
                PipObjective::create([
                    'tenant_id' => $tenantId,
                    'pip_id' => $pip->id,
                    'title' => 'Improve ' . $item->title,
                    'description' => sprintf('Target was %s %s. Actual achieved was %s %s.', $item->target, $item->unit, $item->actual ?? '0', $item->unit),
                    'target_criteria' => sprintf('Achieve at least 85%% of designated KPI metric (%s %s).', $item->target, $item->unit),
                    'support_provided' => 'Manager coaching and bi-weekly milestone check-ins.',
                    'weightage' => $weightPerItem,
                    'status' => 'pending',
                ]);
            }

            $plan->pip_triggered = true;
            $plan->save();

            // Notify Employee and Supervisor of PIP Action Plan
            NotificationService::sendToEmployee(
                employee: $employee,
                title: 'Performance Improvement Plan (PIP) Initiated',
                message: "A 60-day Performance Improvement Plan (#{$pipNumber}) has been initiated. Review your objectives and support guidelines.",
                actionUrl: route('hrms.pip.show', $pip->id),
                module: 'hrms',
                type: 'pip',
                iconClass: 'feather-alert-triangle'
            );

            if ($pip->manager_id) {
                NotificationService::sendToEmployee(
                    employee: $pip->manager_id,
                    title: 'PIP Supervised Action Plan Assigned',
                    message: "You are assigned as supervisor for {$employee?->full_name}'s PIP (#{$pipNumber}).",
                    actionUrl: route('hrms.pip.show', $pip->id),
                    module: 'hrms',
                    type: 'pip',
                    iconClass: 'feather-user-check'
                );
            }

            return $pip;
        });
    }
}
