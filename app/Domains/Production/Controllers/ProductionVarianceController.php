<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\ProductionVarianceAnalysisService;
use App\Domains\Production\Services\RoutingRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionVarianceController extends Controller
{
    public function __construct(
        private readonly ProductionVarianceAnalysisService $varianceService,
        private readonly RoutingRecommendationService $recommendationService
    ) {}

    /**
     * Display Planning Exception View / Routing Variance Dashboard.
     */
    public function index(Request $request): View|JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $recommendations = $this->recommendationService->generateRecommendations($tenantId, 10);
        $recurringVariances = $this->varianceService->analyzeRecurringRoutingVariances($tenantId, 10);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'recommendations' => $recommendations,
                'recurring_variances' => $recurringVariances,
            ]);
        }

        return view('modules.production.variances.index', compact('recommendations', 'recurringVariances'));
    }

    /**
     * Display variance analysis for a specific Production Order.
     */
    public function show(Request $request, ProductionOrder $order): View|JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        abort_unless($order->tenant_id === $tenantId, 403, 'Unauthorized tenant access.');

        $analysis = $this->varianceService->analyzeProductionOrder($order);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'analysis' => $analysis,
            ]);
        }

        return view('modules.production.orders.partials.variance_tab', compact('order', 'analysis'));
    }

    /**
     * Create a draft ECO from a routing variance recommendation.
     */
    public function createEco(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer',
            'title' => 'required|string',
            'type' => 'nullable|string',
            'description' => 'nullable|string',
            'evidence' => 'nullable|string',
            'suggested_action' => 'nullable|string',
        ]);

        $tenantId = (int) auth()->user()->tenant_id;
        $userId = (int) auth()->id();

        $recommendation = [
            'tenant_id' => $tenantId,
            'product_id' => (int) $request->input('product_id'),
            'title' => (string) $request->input('title'),
            'type' => (string) $request->input('type'),
            'description' => (string) $request->input('description', ''),
            'evidence' => (string) $request->input('evidence', ''),
            'suggested_action' => (string) $request->input('suggested_action', ''),
        ];

        $eco = $this->recommendationService->createDraftEcoFromRecommendation($recommendation, $userId);

        return response()->json([
            'success' => true,
            'message' => "Draft ECO {$eco->eco_number} created successfully.",
            'eco' => $eco,
        ]);
    }
}
