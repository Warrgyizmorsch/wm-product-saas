<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\PipCategory;
use App\Domains\HRMS\Models\PipCheckin;
use App\Domains\HRMS\Models\PipObjective;
use App\Domains\HRMS\Models\PipPolicyTemplate;
use App\Models\User;
use Illuminate\Http\Request;

interface PipRepositoryInterface
{
    public function getIndexData(array $inputs, ?User $user, int $tenantId): array;

    public function getShowData(int $id, int $tenantId): array;

    public function storePlan(array $validated, int $tenantId): PerformanceImprovementPlan;

    public function updatePlan(int $id, array $validated, int $tenantId): PerformanceImprovementPlan;

    public function deletePlan(int $id, int $tenantId): bool;

    public function storeObjective(int $pipId, array $validated, int $tenantId): PipObjective;

    public function updateObjective(int $objectiveId, array $validated, int $tenantId): PipObjective;

    public function deleteObjective(int $objectiveId, int $tenantId): bool;

    public function storeCheckin(int $pipId, array $validated, ?int $loggedById, int $tenantId): PipCheckin;

    public function acknowledge(int $id, string $signatureData, int $tenantId): PerformanceImprovementPlan;

    public function conclude(int $id, array $validated, int $tenantId): PerformanceImprovementPlan;

    public function storeCategory(array $validated, int $tenantId): PipCategory;

    public function storeTemplate(array $validated, int $tenantId): PipPolicyTemplate;
}
