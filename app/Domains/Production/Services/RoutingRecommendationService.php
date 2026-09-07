<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\Routing;
use InvalidArgumentException;

class RoutingRecommendationService
{
    public const TYPE_REVIEW_RUN_TIME = 'REVIEW_STANDARD_RUN_TIME';
    public const TYPE_REVIEW_SETUP_TIME = 'REVIEW_SETUP_TIME';
    public const TYPE_REVIEW_SCRAP_FACTOR = 'REVIEW_SCRAP_FACTOR';
    public const TYPE_REVIEW_MACHINE_ASSIGNMENT = 'REVIEW_MACHINE_ASSIGNMENT';

    public function __construct(
        private readonly ProductionVarianceAnalysisService $varianceService,
        private readonly ProductionEcoService $ecoService
    ) {}

    /**
     * Generate evidence-based routing recommendations across completed/active production orders.
     */
    public function generateRecommendations(int $tenantId, int $limit = 10): array
    {
        $recurringData = $this->varianceService->analyzeRecurringRoutingVariances($tenantId, 50);

        $recommendations = [];

        foreach ($recurringData as $item) {
            $sampleSize = $item['sample_count'];

            // Require minimum 3 orders sample size to establish a trend
            if ($sampleSize < 3) {
                continue;
            }

            $avgVariancePct = $item['average_variance_percentage'];
            $scrapRate = ($item['scrap_occurrences'] / $sampleSize) * 100;
            $altMachineRate = ($item['alternate_machine_occurrences'] / $sampleSize) * 100;

            // 1. High Run Time Variance Recommendation
            if ($avgVariancePct > 15.0) {
                $recommendations[] = [
                    'type' => self::TYPE_REVIEW_RUN_TIME,
                    'tenant_id' => $tenantId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'routing_id' => $item['routing_id'],
                    'routing_operation_id' => $item['routing_operation_id'],
                    'operation_name' => $item['operation_name'],
                    'title' => "Review Standard Run Time for Operation '{$item['operation_name']}'",
                    'description' => "Operation '{$item['operation_name']}' consistently exceeds standard run time across completed orders.",
                    'evidence' => "Exceeded standard runtime in {$item['significant_variance_count']} of {$sampleSize} orders (avg variance: +{$avgVariancePct}%).",
                    'suggested_action' => "Increase standard run time in routing definition via ECO.",
                    'sample_size' => $sampleSize,
                    'affected_orders_count' => $item['significant_variance_count'],
                    'average_variance_percentage' => $avgVariancePct,
                    'eco_applicable' => true,
                ];
            }

            // 2. High Scrap Factor Recommendation
            if ($scrapRate >= 50.0) {
                $recommendations[] = [
                    'type' => self::TYPE_REVIEW_SCRAP_FACTOR,
                    'tenant_id' => $tenantId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'routing_id' => $item['routing_id'],
                    'routing_operation_id' => $item['routing_operation_id'],
                    'operation_name' => $item['operation_name'],
                    'title' => "Review Scrap Factor for Operation '{$item['operation_name']}'",
                    'description' => "Material scrap occurs frequently in operation '{$item['operation_name']}'.",
                    'evidence' => "Scrap recorded in {$item['scrap_occurrences']} of {$sampleSize} recent orders ({$scrapRate}% of executions).",
                    'suggested_action' => "Update BOM material scrap percentage allowance via ECO.",
                    'sample_size' => $sampleSize,
                    'affected_orders_count' => $item['scrap_occurrences'],
                    'average_variance_percentage' => round($scrapRate, 2),
                    'eco_applicable' => true,
                ];
            }

            // 3. Alternate Machine Assignment Recommendation
            if ($altMachineRate >= 50.0 && abs($avgVariancePct) > 10.0) {
                $recommendations[] = [
                    'type' => self::TYPE_REVIEW_MACHINE_ASSIGNMENT,
                    'tenant_id' => $tenantId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'routing_id' => $item['routing_id'],
                    'routing_operation_id' => $item['routing_operation_id'],
                    'operation_name' => $item['operation_name'],
                    'title' => "Review Machine Assignment for Operation '{$item['operation_name']}'",
                    'description' => "Operation is frequently executed on alternate machines instead of primary assigned machine.",
                    'evidence' => "Ran on alternate machine in {$item['alternate_machine_occurrences']} of {$sampleSize} orders ({$altMachineRate}% of executions).",
                    'suggested_action' => "Reassign primary machine or update alternate machine routing standards via ECO.",
                    'sample_size' => $sampleSize,
                    'affected_orders_count' => $item['alternate_machine_occurrences'],
                    'average_variance_percentage' => round($altMachineRate, 2),
                    'eco_applicable' => true,
                ];
            }
        }

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Create a draft ECO from a routing recommendation, preserving ECO approval & release lifecycle.
     */
    public function createDraftEcoFromRecommendation(array $recommendation, int $userId): ProductionEco
    {
        $tenantId = $recommendation['tenant_id'];
        $productId = $recommendation['product_id'];

        // Locate current active/approved BOM and Routing
        $currentBom = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->first();

        $currentRouting = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->first();

        $changeType = ProductionEco::CHANGE_TYPE_ROUTING;
        if (($recommendation['type'] ?? '') === self::TYPE_REVIEW_SCRAP_FACTOR) {
            $changeType = ProductionEco::CHANGE_TYPE_BOM;
        }

        $title = "ECO: " . ($recommendation['title'] ?? 'Routing Optimization');
        $description = ($recommendation['description'] ?? '') . "\nEvidence: " . ($recommendation['evidence'] ?? '') . "\nAction: " . ($recommendation['suggested_action'] ?? '');

        return $this->ecoService->createEco([
            'tenant_id' => $tenantId,
            'title' => substr($title, 0, 250),
            'change_type' => $changeType,
            'product_id' => $productId,
            'current_bom_id' => $currentBom?->id,
            'current_routing_id' => $currentRouting?->id,
            'proposed_bom_id' => $currentBom?->id, // Draft ECO points to proposed BOM/Routing copy
            'proposed_routing_id' => $currentRouting?->id,
            'description' => $description,
            'status' => ProductionEco::STATUS_DRAFT,
        ], $userId);
    }
}
