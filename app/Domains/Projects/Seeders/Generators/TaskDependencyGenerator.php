<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\Projects\Seeders\Context\TenantIsolationContext;

class TaskDependencyGenerator
{
    /**
     * Generate dependency pairs among inserted task IDs for a project.
     *
     * @param int[] $taskIds Array of generated task IDs
     * @return array[]
     */
    public static function generateForTaskIds(
        TenantIsolationContext $context,
        int $projectId,
        array $taskIds,
        int $maxDependencies = 20
    ): array {
        $dependencies = [];
        $now = now()->toDateTimeString();
        $count = count($taskIds);

        if ($count < 2) {
            return [];
        }

        $step = max(1, (int) floor($count / max(1, $maxDependencies)));
        for ($i = 1; $i < $count && count($dependencies) < $maxDependencies; $i += $step) {
            $taskId = $taskIds[$i];
            $dependsOnId = $taskIds[$i - 1];

            $dependencies[] = [
                'tenant_id' => $context->tenantId,
                'project_id' => $projectId,
                'task_id' => $taskId,
                'depends_on_task_id' => $dependsOnId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $dependencies;
    }
}
