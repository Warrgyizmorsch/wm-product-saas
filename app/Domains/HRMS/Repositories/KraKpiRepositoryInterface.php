<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\AppraisalCycle;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeGoalItem;
use App\Domains\HRMS\Models\EmployeeGoalPlan;
use App\Domains\HRMS\Models\GoalProgressLog;
use App\Domains\HRMS\Models\KpiMaster;
use App\Domains\HRMS\Models\KpiTemplate;
use App\Domains\HRMS\Models\KraCategory;
use App\Domains\HRMS\Models\PerformanceImprovementPlan;

interface KraKpiRepositoryInterface
{
    /**
     * Retrieve all data required for the main KRA & KPI dashboard / index view.
     */
    public function getIndexData(array $inputs, ?Employee $currentEmployee, bool $isHrOrAdmin, int $tenantId): array;

    /**
     * Retrieve data for single employee scorecard / appraisal breakdown.
     */
    public function getScorecardData(int $id, ?Employee $currentEmployee, bool $isHrOrAdmin, int $tenantId): array;

    /**
     * Store new Appraisal Cycle.
     */
    public function storeCycle(array $validated, int $tenantId): AppraisalCycle;

    /**
     * Update existing Appraisal Cycle.
     */
    public function updateCycle(int $id, array $validated, int $tenantId): AppraisalCycle;

    /**
     * Delete Appraisal Cycle.
     */
    public function deleteCycle(int $id, int $tenantId): bool;

    /**
     * Store KRA Category.
     */
    public function storeKraCategory(array $validated, int $tenantId): KraCategory;

    /**
     * Delete KRA Category.
     */
    public function deleteKraCategory(int $id, int $tenantId): bool;

    /**
     * Store KPI Master metric.
     */
    public function storeKpiMaster(array $validated, int $tenantId): KpiMaster;

    /**
     * Delete KPI Master metric.
     */
    public function deleteKpiMaster(int $id, int $tenantId): bool;

    /**
     * Store KPI Template with its items.
     */
    public function storeTemplate(array $validated, int $tenantId): KpiTemplate;

    /**
     * Update KPI Template with its items.
     */
    public function updateTemplate(int $id, array $validated, int $tenantId): KpiTemplate;

    /**
     * Delete KPI Template and its items.
     */
    public function deleteTemplate(int $id, int $tenantId): bool;

    /**
     * Bulk assign template or scorecard to target employees.
     */
    public function assignTemplateToEmployees(array $validated, int $tenantId): int;

    /**
     * Delete Employee Scorecard Plan and associated items & reviews.
     */
    public function deletePlan(int $planId, int $tenantId): bool;

    /**
     * Add single KPI goal item to an employee scorecard.
     */
    public function addGoalItem(int $planId, array $validated, int $tenantId): EmployeeGoalItem;

    /**
     * Delete single KPI goal item from scorecard and recalculate score.
     */
    public function deleteGoalItem(int $itemId, int $tenantId): bool;

    /**
     * Submit goals to Manager for sign-off.
     */
    public function submitGoals(int $planId, int $tenantId): EmployeeGoalPlan;

    /**
     * Manager approves goals.
     */
    public function approveGoals(int $planId, int $tenantId): EmployeeGoalPlan;

    /**
     * Log mid-cycle progress against a KPI item.
     */
    public function logProgress(int $itemId, array $validated, ?int $loggedById, int $tenantId): GoalProgressLog;

    /**
     * Submit Self-Appraisal.
     */
    public function submitSelfAppraisal(int $planId, array $validated, int $tenantId): EmployeeGoalPlan;

    /**
     * Submit Manager-Appraisal.
     */
    public function submitManagerAppraisal(int $planId, array $validated, bool $promotionRecommended, int $tenantId): EmployeeGoalPlan;

    /**
     * HR / Committee Calibration & Normalization.
     */
    public function calibrateAppraisal(int $planId, array $validated, int $tenantId): EmployeeGoalPlan;

    /**
     * Employee final sign-off.
     */
    public function signOffAppraisal(int $planId, int $tenantId): EmployeeGoalPlan;

    /**
     * Trigger PIP from low appraisal score.
     */
    public function triggerPip(int $planId, ?int $hrUserId, int $tenantId): PerformanceImprovementPlan;

    /**
     * Resolve KRA Category ID from ID or custom typed name string.
     */
    public function resolveKraCategoryId(mixed $kraValue, int $tenantId): ?int;
}
