<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\Projects\Seeders\Context\TenantIsolationContext;

class ProjectMemberGenerator
{
    protected static array $roles = [
        'Project Manager',
        'Tech Lead',
        'Backend Developer',
        'Frontend Developer',
        'UI/UX Designer',
        'QA Engineer',
        'DevOps Engineer',
        'Business Analyst',
    ];

    /**
     * Generate project member records for given project ID and user IDs pool.
     * Dynamic team size: 3 to 8 members per project.
     *
     * @param int[] $userIds
     * @return array[]
     */
    public static function generateForProject(TenantIsolationContext $context, int $projectId, array $userIds): array
    {
        $teamSize = rand(3, min(8, count($userIds)));
        // Shuffle user IDs deterministically or randomly to get team members
        $assignedUserIds = array_slice($userIds, 0, $teamSize);
        // Rotate user pool so different projects get different team compositions
        $now = now()->toDateTimeString();
        $members = [];

        foreach ($assignedUserIds as $index => $userId) {
            $role = self::$roles[$index % count(self::$roles)];
            $members[] = [
                'tenant_id' => $context->tenantId,
                'project_id' => $projectId,
                'user_id' => $userId,
                'project_role' => $role,
                'rate_per_hour' => rand(50, 150) * 1.0,
                'cost_per_hour' => rand(30, 90) * 1.0,
                'budget_hours' => rand(40, 200) * 1.0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $members;
    }
}
