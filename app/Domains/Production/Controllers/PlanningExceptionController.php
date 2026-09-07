<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\PlanningExceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanningExceptionController extends Controller
{
    public function __construct(
        private readonly PlanningExceptionService $exceptionService
    ) {}

    /**
     * Display Planning Exceptions & At-Risk Production Orders dashboard.
     */
    public function index(Request $request): View|JsonResponse
    {
        $tenantId = (int) (auth()->user()->tenant_id ?? require_tenant_id());
        $filters = [
            'severity' => $request->query('severity'),
            'exception_type' => $request->query('exception_type'),
            'product_id' => $request->query('product_id'),
            'production_order_id' => $request->query('production_order_id'),
        ];

        $analysis = $this->exceptionService->evaluateAllOrders($tenantId, array_filter($filters));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'summary' => $analysis['summary'],
                'orders' => $analysis['orders'],
            ]);
        }

        return view('modules.production.exceptions.index', [
            'summary' => $analysis['summary'],
            'orders' => $analysis['orders'],
            'filters' => $filters,
        ]);
    }

    /**
     * Display detailed 9-vector risk analysis for a specific Production Order.
     */
    public function show(Request $request, ProductionOrder $order): View|JsonResponse
    {
        $tenantId = (int) (auth()->user()->tenant_id ?? require_tenant_id());
        abort_unless((int) $order->tenant_id === $tenantId, 403, 'Unauthorized tenant access.');

        $riskAnalysis = $this->exceptionService->evaluateOrderRisk($order);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'analysis' => $riskAnalysis,
            ]);
        }

        return view('modules.production.exceptions.show', [
            'order' => $order,
            'analysis' => $riskAnalysis,
        ]);
    }
}
