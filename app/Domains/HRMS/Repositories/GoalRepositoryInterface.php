<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Goal;
use App\Domains\HRMS\Models\GoalCategory;
use App\Domains\HRMS\Models\GoalCheckIn;
use App\Domains\HRMS\Models\GoalCycle;
use App\Models\User;

interface GoalRepositoryInterface
{
    /**
     * Get all data required for the Goals & OKRs dashboard / hub.
     */
    public function getIndexData(array $inputs, ?User $user, int $tenantId): array;

    /**
     * Get all data required for a single Goal / Objective detail page.
     */
    public function getShowData(int $id, ?User $user, int $tenantId): array;

    /**
     * Store a new Goal / Objective with its key results.
     */
    public function storeGoal(array $data, int $tenantId, ?User $user = null): Goal;

    /**
     * Update an existing Goal / Objective with its key results.
     */
    public function updateGoal(int $id, array $data, int $tenantId, ?User $user = null): Goal;

    /**
     * Delete an existing Goal / Objective.
     */
    public function deleteGoal(int $id, int $tenantId): bool;

    /**
     * Record a fast progress check-in on a Goal or Key Result.
     */
    public function recordCheckIn(int $goalId, array $data, int $tenantId, ?User $user = null): GoalCheckIn;

    /**
     * Store a new Goal Cycle.
     */
    public function storeCycle(array $data, int $tenantId, ?User $user = null): GoalCycle;

    /**
     * Delete a Goal Cycle.
     */
    public function deleteCycle(int $id, int $tenantId): bool;

    /**
     * Store a new Strategic Pillar / Category.
     */
    public function storeCategory(array $data, int $tenantId, ?User $user = null): GoalCategory;

    /**
     * Delete a Strategic Pillar / Category.
     */
    public function deleteCategory(int $id, int $tenantId): bool;

    /**
     * Get cascading hierarchy tree of goals for a cycle.
     */
    public function getCascadingTree(int $tenantId, ?int $cycleId = null): array;
}
