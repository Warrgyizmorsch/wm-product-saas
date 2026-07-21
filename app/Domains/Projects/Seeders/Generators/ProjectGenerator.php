<?php

namespace App\Domains\Projects\Seeders\Generators;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Seeders\Context\TenantIsolationContext;
use Illuminate\Support\Carbon;

class ProjectGenerator
{
    protected static array $projectTemplates = [
        ['name' => 'Enterprise ERP Platform Modernization', 'code' => 'PRJ-ERP'],
        ['name' => 'Mobile Banking & POS Application v3', 'code' => 'PRJ-MBANK'],
        ['name' => 'Cloud Infrastructure & Kubernetes Migration', 'code' => 'PRJ-CLOUD'],
        ['name' => 'AI-Powered Customer Support Bot Integration', 'code' => 'PRJ-AIBOT'],
        ['name' => 'Global Logistics & Inventory Tracking System', 'code' => 'PRJ-LOGIS'],
        ['name' => 'Healthcare E-Prescription Portal', 'code' => 'PRJ-HLTH'],
        ['name' => 'E-Commerce Omnichannel Gateway', 'code' => 'PRJ-ECOM'],
        ['name' => 'Automated HRMS & Payroll System Upgrade', 'code' => 'PRJ-HRMS'],
        ['name' => 'Real-Time Financial Analytics & BI Dashboard', 'code' => 'PRJ-BIAN'],
        ['name' => 'Cybersecurity & Zero-Trust Compliance', 'code' => 'PRJ-SEC'],
        ['name' => 'Smart Warehouse Manufacturing Execution (MES)', 'code' => 'PRJ-WMES'],
        ['name' => 'B2B SaaS Multi-Tenant Billing Gateway', 'code' => 'PRJ-SAAS'],
        ['name' => 'Customer Relationship Management (CRM) Revamp', 'code' => 'PRJ-CRM'],
        ['name' => 'Microservices Architecture Refactoring', 'code' => 'PRJ-MICRO'],
        ['name' => 'IoT Smart Factory Sensor Stream Engine', 'code' => 'PRJ-IOT'],
        ['name' => 'Omnichannel Marketing Automation Platform', 'code' => 'PRJ-MKTG'],
        ['name' => 'API Gateway & GraphQL Infrastructure', 'code' => 'PRJ-GWAPI'],
        ['name' => 'Data Lake & Snowflake ETL Pipeline', 'code' => 'PRJ-DLAKE'],
        ['name' => 'DevOps CI/CD Automation Pipeline', 'code' => 'PRJ-CICD'],
        ['name' => 'Digital Identity & SSO Single-Sign-On System', 'code' => 'PRJ-SSO'],
    ];

    /**
     * Generate project records and return inserted array mapping with IDs.
     *
     * @param int[] $userIds
     * @param int[] $customerIds
     * @return array Array of project records with generated IDs
     */
    public static function generate(
        TenantIsolationContext $context,
        array $userIds,
        array $customerIds = [],
        int $count = 20
    ): array {
        $projects = [];
        $now = now()->toDateTimeString();

        for ($i = 0; $i < $count; $i++) {
            $templateIndex = $i % count(self::$projectTemplates);
            $template = self::$projectTemplates[$templateIndex];
            $sequence = floor($i / count(self::$projectTemplates)) + 1;
            
            $code = $sequence > 1 ? "{$template['code']}-0{$sequence}" : "{$template['code']}-01";
            $name = $sequence > 1 ? "{$template['name']} (Phase {$sequence})" : $template['name'];

            $ownerId = $userIds[$i % count($userIds)];
            $managerId = $userIds[($i + 1) % count($userIds)];

            $customerId = !empty($customerIds) ? $customerIds[$i % count($customerIds)] : null;

            $startDate = Carbon::now()->subMonths(rand(1, 6))->addDays(rand(1, 15));
            $endDate = (clone $startDate)->addMonths(rand(3, 12));

            $projects[] = [
                'tenant_id' => $context->tenantId,
                'project_code' => $code,
                'name' => $name,
                'customer_id' => $customerId,
                'owner_id' => $ownerId,
                'manager_id' => $managerId,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'budget_type' => Project::BUDGET_TYPES[rand(0, 1)],
                'budget_amount' => rand(150, 1500) * 1000.00,
                'budget_hours' => rand(200, 2000) * 1.0,
                'billing_method' => Project::BILLING_METHODS[rand(0, count(Project::BILLING_METHODS) - 1)],
                'priority' => Project::PRIORITIES[rand(0, count(Project::PRIORITIES) - 1)],
                'status' => Project::STATUS_ACTIVE,
                'description' => "Enterprise project initiative for {$name}.",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $projects;
    }
}
