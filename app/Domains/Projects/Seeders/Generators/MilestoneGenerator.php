<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Seeders\Context\TenantIsolationContext;
use Illuminate\Support\Carbon;

class MilestoneGenerator
{
    protected static array $milestonePhases = [
        'Initiation & Architecture Blueprint',
        'Database Schema & Tenant Isolation',
        'Core API Endpoint Design',
        'UI/UX Wireframing & Component System',
        'Authentication & RBAC Security Integration',
        'Front-End Dashboard Integration',
        'Real-Time WebSockets & Notifications',
        'Data Migration & ETL Pipelines',
        'Automated Unit & Integration Testing',
        'Performance Benchmarking & Caching',
        'Third-Party Gateway Integration',
        'User Acceptance Testing (UAT)',
        'Security Audit & Penetration Testing',
        'Disaster Recovery & Backup Systems',
        'Staging Environment Deployment',
        'Customer Beta Feedback Iteration',
        'Production Environment Setup',
        'Go-Live & Post-Launch Support',
        'Maintenance & SLA Monitoring',
        'Project Closure & Handoff Documentation',
    ];

    /**
     * Generate milestone records array for a project.
     *
     * @param array $project Raw project record or model
     * @param int[] $userIds
     * @return array[]
     */
    public static function generateForProject(
        TenantIsolationContext $context,
        int $projectId,
        string $projectStartDate,
        array $userIds,
        int $milestoneCount = 18
    ): array {
        $milestones = [];
        $baseDate = Carbon::parse($projectStartDate);
        $now = now()->toDateTimeString();

        for ($m = 0; $m < $milestoneCount; $m++) {
            $phaseIndex = $m % count(self::$milestonePhases);
            $phaseNumber = $m + 1;
            $phaseName = "Phase {$phaseNumber}: " . self::$milestonePhases[$phaseIndex];

            $startDate = (clone $baseDate)->addDays($m * 10);
            $dueDate = (clone $startDate)->addDays(14);
            $ownerId = $userIds[$m % count($userIds)];

            $status = $m < 5 ? Milestone::STATUS_COMPLETED : ($m < 12 ? Milestone::STATUS_ACTIVE : Milestone::STATUS_DRAFT);
            $pct = $status === Milestone::STATUS_COMPLETED ? 100 : ($status === Milestone::STATUS_ACTIVE ? rand(20, 85) : 0);

            $milestones[] = [
                'tenant_id' => $context->tenantId,
                'project_id' => $projectId,
                'owner_id' => $ownerId,
                'name' => $phaseName,
                'description' => "Key milestone deliverables for {$phaseName}.",
                'start_date' => $startDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'status' => $status,
                'completion_percentage' => $pct,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $milestones;
    }
}
