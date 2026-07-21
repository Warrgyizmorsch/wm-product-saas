<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Seeders\Context\TenantIsolationContext;
use App\Domains\Projects\Seeders\Distributions\TaskPriorityDistribution;
use App\Domains\Projects\Seeders\Distributions\TaskStatusDistribution;
use Illuminate\Support\Carbon;

class TaskGenerator
{
    protected static array $taskTitles = [
        'Implement authentication middleware and session token refresh',
        'Design responsive layout for desktop and mobile viewports',
        'Setup Redis caching layer for heavy analytics query endpoints',
        'Add soft deletes and tenant scoping verification unit tests',
        'Optimize MySQL database indexes on multi-tenant foreign key columns',
        'Refactor API response DTO transformation pipeline',
        'Integrate third-party payment gateway webhooks & signature verification',
        'Build drag-and-drop Kanban task board with status transition guards',
        'Implement export to PDF/Excel report generator service',
        'Write end-to-end integration tests for user lifecycle workflows',
        'Configure Prometheus metrics exporter for application health monitoring',
        'Resolve edge case bug in multi-currency rate calculation module',
        'Implement rate limiting and DDoS throttling headers for public routes',
        'Audit RBAC permission matrix for administrative role overrides',
        'Setup CI/CD automated deployment pipeline with zero-downtime rollback',
        'Build user activity audit log trail viewer component',
        'Implement bulk import for CSV customer records with validation errors',
        'Add webhooks notification handler for external web sockets',
        'Configure S3 bucket secure file upload with presigned URLs',
        'Perform load testing and database connection pool optimization',
    ];

    /**
     * Generate task records array for a task list.
     *
     * @param int[] $userIds
     * @return array[]
     */
    public static function generateForTaskList(
        TenantIsolationContext $context,
        int $projectId,
        int $milestoneId,
        int $taskListId,
        string $startDateString,
        array $userIds,
        int $taskCount = 18,
        string $profile = TaskStatusDistribution::PROFILE_ACTIVE_DEV,
        int &$taskCodeCounter = 1
    ): array {
        $tasks = [];
        $baseDate = Carbon::parse($startDateString);
        $now = now()->toDateTimeString();

        for ($t = 0; $t < $taskCount; $t++) {
            $titleIndex = ($t + $taskListId) % count(self::$taskTitles);
            $title = self::$taskTitles[$titleIndex];

            $codeFormatted = sprintf('TSK-%05d', $taskCodeCounter++);

            $assigneeId = $userIds[$t % count($userIds)];
            $reviewerId = $userIds[($t + 1) % count($userIds)];

            $status = TaskStatusDistribution::randomStatus($profile);
            $priority = TaskPriorityDistribution::randomPriority($profile);

            $startDate = (clone $baseDate)->addDays(rand(0, 10));
            $dueDate = (clone $startDate)->addDays(rand(2, 14));
            $estHours = rand(4, 40) * 1.0;
            $actHours = $status === Task::STATUS_COMPLETED ? $estHours * rand(8, 12) / 10 : ($status === Task::STATUS_IN_PROGRESS ? $estHours * rand(2, 7) / 10 : 0.0);

            $completedAt = $status === Task::STATUS_COMPLETED ? (clone $dueDate)->subDays(rand(0, 3))->toDateTimeString() : null;

            $tasks[] = [
                'tenant_id' => $context->tenantId,
                'project_id' => $projectId,
                'milestone_id' => $milestoneId,
                'task_list_id' => $taskListId,
                'task_code' => $codeFormatted,
                'title' => $title,
                'description' => "Detailed requirements and specifications for {$title}.",
                'assignee_id' => $assigneeId,
                'reviewer_id' => $reviewerId,
                'priority' => $priority,
                'status' => $status,
                'start_date' => $startDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'estimated_hours' => $estHours,
                'actual_hours' => $actHours,
                'position' => $t + 1,
                'completed_at' => $completedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $tasks;
    }
}
