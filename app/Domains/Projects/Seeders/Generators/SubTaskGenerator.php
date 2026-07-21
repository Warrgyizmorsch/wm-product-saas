<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\Projects\Seeders\Context\TenantIsolationContext;

class SubTaskGenerator
{
    protected static array $subtaskChecklists = [
        'Write unit & integration test cases',
        'Peer code review and security audit',
        'Update Swagger/OpenAPI documentation',
        'Verify UI responsiveness across viewports',
        'Check database query performance & indexing',
        'Validate tenant isolation boundary checks',
        'Test error handling and edge cases',
        'Verify RBAC permission access rules',
    ];

    /**
     * Generate subtask records for a given task ID.
     * Generates 2-4 subtasks for selected tasks.
     *
     * @param int[] $userIds
     * @return array[]
     */
    public static function generateForTask(
        TenantIsolationContext $context,
        int $taskId,
        array $userIds,
        int $count = 3
    ): array {
        $subtasks = [];
        $now = now()->toDateTimeString();

        for ($s = 0; $s < $count; $s++) {
            $titleIndex = ($s + $taskId) % count(self::$subtaskChecklists);
            $title = self::$subtaskChecklists[$titleIndex];
            $isCompleted = ($s % 2 === 0);
            $assigneeId = $userIds[$s % count($userIds)];

            $subtasks[] = [
                'tenant_id' => $context->tenantId,
                'task_id' => $taskId,
                'title' => $title,
                'assignee_id' => $assigneeId,
                'is_completed' => $isCompleted,
                'position' => $s + 1,
                'completed_at' => $isCompleted ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $subtasks;
    }
}
