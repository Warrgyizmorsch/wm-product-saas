<?php

namespace App\Services\Approval;

use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Models\Routing;
use App\Models\User;
use App\Services\Access\AccessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

class ApprovalCenterService
{
    private const MAX_HEADER_ITEMS = 5;

    public function __construct(
        private readonly AccessService $accessService,
    ) {
    }

    /**
     * Get pending Production approvals currently actionable by the authenticated user.
     *
     * @return array{
     *     count: int,
     *     items: list<array{
     *         module: string,
     *         type: string,
     *         title: string,
     *         subtitle: string,
     *         url: string,
     *         icon: string,
     *         time: string|null,
     *         created_at: string|null
     *     }>
     * }
     */
    public function getPendingApprovals(?User $user): array
    {
        if ($user === null || empty($user->tenant_id)) {
            return [
                'count' => 0,
                'items' => [],
            ];
        }

        // 1. Production Module Subscription Gating
        if (! $this->isProductionModuleEnabled()) {
            return [
                'count' => 0,
                'items' => [],
            ];
        }

        // 2. Production Domain Permission Checks
        $canApproveBom = $this->hasPermission($user, 'production.bom.approve');
        $canApproveRouting = $this->hasPermission($user, 'production.routing.approve');
        $canApprovePlan = $this->hasPermission($user, 'production.planning.approve') || $user->role === 'admin';
        $canApproveScrap = $this->hasPermission($user, 'production.quality.approve');
        // ECO changes involve BOMs and/or Routings; authorized engineering/production managers or admins can review
        $canApproveEco = $canApproveBom || $canApproveRouting || $user->role === 'admin';

        // Short-circuit if user has no Production approval permissions
        if (! $canApproveBom && ! $canApproveRouting && ! $canApprovePlan && ! $canApproveScrap && ! $canApproveEco) {
            return [
                'count' => 0,
                'items' => [],
            ];
        }

        $totalActionableCount = 0;
        $candidateItems = [];

        // 3. BOM Approvals (status = pending_approval)
        if ($canApproveBom) {
            $bomCount = ProductionBom::query()
                ->where('status', 'pending_approval')
                ->count();
            $totalActionableCount += $bomCount;

            if ($bomCount > 0) {
                $boms = ProductionBom::query()
                    ->where('status', 'pending_approval')
                    ->with(['product'])
                    ->orderBy('created_at', 'asc')
                    ->take(self::MAX_HEADER_ITEMS)
                    ->get();

                foreach ($boms as $bom) {
                    $subtitle = $bom->bom_name ?: ($bom->product?->name ? "Product: {$bom->product->name}" : 'Approval required');
                    $candidateItems[] = [
                        'module' => 'Production',
                        'type' => 'BOM',
                        'title' => (string) $bom->bom_number,
                        'subtitle' => $subtitle,
                        'url' => Route::has('production.boms.show') ? route('production.boms.show', $bom->id) : url("/production/boms/{$bom->id}"),
                        'icon' => 'feather-layers',
                        'time' => $bom->created_at?->diffForHumans(),
                        'created_at' => $bom->created_at?->toIso8601String(),
                    ];
                }
            }
        }

        // 4. Routing Approvals (status = pending_approval)
        if ($canApproveRouting) {
            $routingCount = Routing::query()
                ->where('status', Routing::STATUS_PENDING_APPROVAL)
                ->count();
            $totalActionableCount += $routingCount;

            if ($routingCount > 0) {
                $routings = Routing::query()
                    ->where('status', Routing::STATUS_PENDING_APPROVAL)
                    ->orderBy('created_at', 'asc')
                    ->take(self::MAX_HEADER_ITEMS)
                    ->get();

                foreach ($routings as $routing) {
                    $subtitle = $routing->routing_number ? "Routing #{$routing->routing_number}" : 'Approval required';
                    $candidateItems[] = [
                        'module' => 'Production',
                        'type' => 'Routing',
                        'title' => (string) ($routing->name ?: "Routing #{$routing->id}"),
                        'subtitle' => $subtitle,
                        'url' => Route::has('production.routing.show') ? route('production.routing.show', $routing->id) : url("/production/routing/{$routing->id}"),
                        'icon' => 'feather-git-commit',
                        'time' => $routing->created_at?->diffForHumans(),
                        'created_at' => $routing->created_at?->toIso8601String(),
                    ];
                }
            }
        }

        // 5. Production Plan Approvals (status = pending_approval)
        if ($canApprovePlan) {
            $planCount = ProductionPlan::query()
                ->where('status', ProductionPlan::STATUS_PENDING_APPROVAL)
                ->count();
            $totalActionableCount += $planCount;

            if ($planCount > 0) {
                $plans = ProductionPlan::query()
                    ->where('status', ProductionPlan::STATUS_PENDING_APPROVAL)
                    ->orderBy('created_at', 'asc')
                    ->take(self::MAX_HEADER_ITEMS)
                    ->get();

                foreach ($plans as $plan) {
                    $subtitle = $plan->name ? (string) $plan->name : 'Approval required';
                    $candidateItems[] = [
                        'module' => 'Production',
                        'type' => 'Production Plan',
                        'title' => (string) $plan->plan_number,
                        'subtitle' => $subtitle,
                        'url' => Route::has('production.plans.show') ? route('production.plans.show', $plan->id) : url("/production/plans/{$plan->id}"),
                        'icon' => 'feather-calendar',
                        'time' => $plan->created_at?->diffForHumans(),
                        'created_at' => $plan->created_at?->toIso8601String(),
                    ];
                }
            }
        }

        // 6. Quality Scrap Disposal Approvals (status = pending_approval)
        if ($canApproveScrap) {
            $scrapCount = ProductionScrapDisposal::query()
                ->where('status', 'pending_approval')
                ->count();
            $totalActionableCount += $scrapCount;

            if ($scrapCount > 0) {
                $scraps = ProductionScrapDisposal::query()
                    ->where('status', 'pending_approval')
                    ->orderBy('created_at', 'asc')
                    ->take(self::MAX_HEADER_ITEMS)
                    ->get();

                foreach ($scraps as $scrap) {
                    $subtitle = $scrap->category ? "Category: {$scrap->category} ({$scrap->quantity} units)" : 'Approval required';
                    $candidateItems[] = [
                        'module' => 'Production',
                        'type' => 'Scrap Disposal',
                        'title' => "Scrap #{$scrap->id}",
                        'subtitle' => $subtitle,
                        'url' => Route::has('production.quality.scrap.index') ? route('production.quality.scrap.index') : url('/production/quality/scrap'),
                        'icon' => 'feather-trash-2',
                        'time' => $scrap->created_at?->diffForHumans(),
                        'created_at' => $scrap->created_at?->toIso8601String(),
                    ];
                }
            }
        }

        // 7. Engineering Change Order Approvals (status = under_review)
        if ($canApproveEco) {
            $ecoCount = ProductionEco::query()
                ->where('status', ProductionEco::STATUS_UNDER_REVIEW)
                ->count();
            $totalActionableCount += $ecoCount;

            if ($ecoCount > 0) {
                $ecos = ProductionEco::query()
                    ->where('status', ProductionEco::STATUS_UNDER_REVIEW)
                    ->orderBy('created_at', 'asc')
                    ->take(self::MAX_HEADER_ITEMS)
                    ->get();

                foreach ($ecos as $eco) {
                    $subtitle = $eco->title ? (string) $eco->title : 'Engineering Change Review';
                    $candidateItems[] = [
                        'module' => 'Production',
                        'type' => 'ECO',
                        'title' => (string) $eco->eco_number,
                        'subtitle' => $subtitle,
                        'url' => Route::has('production.ecos.show') ? route('production.ecos.show', $eco->id) : url("/production/ecos/{$eco->id}"),
                        'icon' => 'feather-git-pull-request',
                        'time' => $eco->created_at?->diffForHumans(),
                        'created_at' => $eco->created_at?->toIso8601String(),
                    ];
                }
            }
        }

        // 8. Bounded Results Sorting: oldest pending first for workflow queue priority
        usort($candidateItems, function (array $a, array $b): int {
            $timeA = $a['created_at'] ?? '';
            $timeB = $b['created_at'] ?? '';
            return strcmp($timeA, $timeB);
        });

        $finalItems = array_slice($candidateItems, 0, self::MAX_HEADER_ITEMS);

        return [
            'count' => $totalActionableCount,
            'items' => $finalItems,
        ];
    }

    /**
     * Verify if the Production module is active in tenant's subscription plan.
     */
    private function isProductionModuleEnabled(): bool
    {
        $planModules = tenant_allowed_modules();

        if ($planModules === null || in_array('*', $planModules, true)) {
            return true;
        }

        return in_array('production', $planModules, true);
    }

    /**
     * Check if the user holds the given permission in their tenant context.
     */
    private function hasPermission(User $user, string $permission): bool
    {
        return $this->accessService->allows($user, $permission, [
            'tenant_id' => $user->tenant_id,
            'branch_id' => $user->branch_id,
            'company_id' => $user->company_id,
            'department_id' => $user->department_id,
            'owner_id' => $user->id,
        ]);
    }
}
