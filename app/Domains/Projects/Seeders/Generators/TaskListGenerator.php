<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\Projects\Seeders\Context\TenantIsolationContext;

class TaskListGenerator
{
    protected static array $listNames = [
        'Product Backlog & User Stories',
        'Architecture & Technical Specs',
        'UI Components & Design System',
        'Backend Controllers & Services',
        'Database Migrations & Models',
        'API Route Guards & Middleware',
        'Unit & Integration Test Suites',
        'Code Review & Refactoring',
        'Quality Assurance & Edge Cases',
        'Bug Fixes & Regression Checks',
        'Performance Tuning & Indexing',
        'Documentation & OpenAPI Specs',
        'Security Hardening & Audit',
        'Deployment Scripts & CI/CD',
        'Release Verification Checklist',
    ];

    /**
     * Generate task list records array for a milestone.
     *
     * @param int[] $userIds
     * @return array[]
     */
    public static function generateForMilestone(
        TenantIsolationContext $context,
        int $projectId,
        int $milestoneId,
        array $userIds,
        int $listCount = 15
    ): array {
        $taskLists = [];
        $now = now()->toDateTimeString();

        for ($l = 0; $l < $listCount; $l++) {
            $nameIndex = $l % count(self::$listNames);
            $listName = self::$listNames[$nameIndex];
            if ($l >= count(self::$listNames)) {
                $suffix = floor($l / count(self::$listNames)) + 1;
                $listName .= " (Group {$suffix})";
            }

            $ownerId = $userIds[$l % count($userIds)];

            $taskLists[] = [
                'tenant_id' => $context->tenantId,
                'project_id' => $projectId,
                'milestone_id' => $milestoneId,
                'owner_id' => $ownerId,
                'name' => $listName,
                'description' => "Task breakdown group for {$listName}.",
                'position' => $l + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $taskLists;
    }
}
