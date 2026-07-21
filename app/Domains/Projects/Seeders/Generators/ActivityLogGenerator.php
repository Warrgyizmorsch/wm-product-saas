<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Seeders\Context\TenantIsolationContext;

class ActivityLogGenerator
{
    /**
     * Generate activity logs for a project.
     *
     * @param int[] $userIds
     * @return array[]
     */
    public static function generateForProject(
        TenantIsolationContext $context,
        int $projectId,
        string $projectName,
        array $userIds,
        int $logCount = 10
    ): array {
        $logs = [];
        $now = now()->toDateTimeString();
        $triggerUser = $userIds[0] ?? 1;

        // 1. Project Creation Event
        $logs[] = [
            'tenant_id' => $context->tenantId,
            'project_id' => $projectId,
            'subject_type' => Project::class,
            'subject_id' => $projectId,
            'event_type' => 'created',
            'title' => 'Project Created',
            'description' => "Project '{$projectName}' was created.",
            'triggered_by' => $triggerUser,
            'metadata' => json_encode(['action' => 'project_initialized']),
            'created_at' => $now,
        ];

        // 2. Additional Activity Events
        $events = [
            ['event' => 'status_changed', 'title' => 'Status Updated', 'desc' => 'Project status set to Active.'],
            ['event' => 'member_added', 'title' => 'Team Member Added', 'desc' => 'Assigned project team members.'],
            ['event' => 'milestone_completed', 'title' => 'Milestone Milestone Achieved', 'desc' => 'Phase 1 Milestone completed successfully.'],
            ['event' => 'task_assigned', 'title' => 'Sprint Tasks Assigned', 'desc' => 'Sprint task items allocated to team.'],
            ['event' => 'budget_updated', 'title' => 'Budget Hours Revised', 'desc' => 'Allocated additional budget hours for testing.'],
        ];

        for ($i = 1; $i < $logCount; $i++) {
            $eventTemplate = $events[($i - 1) % count($events)];
            $user = $userIds[$i % count($userIds)];

            $logs[] = [
                'tenant_id' => $context->tenantId,
                'project_id' => $projectId,
                'subject_type' => Project::class,
                'subject_id' => $projectId,
                'event_type' => $eventTemplate['event'],
                'title' => $eventTemplate['title'],
                'description' => $eventTemplate['desc'],
                'triggered_by' => $user,
                'metadata' => json_encode(['iteration' => $i]),
                'created_at' => $now,
            ];
        }

        return $logs;
    }
}
