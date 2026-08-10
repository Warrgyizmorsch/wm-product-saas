<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Sales\Models\SalesOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductionDashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();

        // 1. Pending Sales Orders to Create Production Orders
        $pendingRequests = ProductionOrderRequest::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->whereNull('production_order_id')
            ->with([
                'product',
                'materialRequirementItem.materialRequirement.salesOrder.customer',
                'materialRequirementItem.salesOrderItem.salesOrder.customer',
            ])
            ->orderByDesc('id')
            ->get();

        $pendingSalesOrderCount = $pendingRequests->count();

        // 2. Production Orders Status Metrics
        $rawOrderCounts = ProductionOrder::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $orderStatusCounts = [
            'draft'       => $rawOrderCounts['draft'] ?? 0,
            'released'    => $rawOrderCounts['released'] ?? 0,
            'in_progress' => $rawOrderCounts['in_progress'] ?? 0,
            'completed'   => $rawOrderCounts['completed'] ?? 0,
            'closed'      => $rawOrderCounts['closed'] ?? 0,
            'cancelled'   => $rawOrderCounts['cancelled'] ?? 0,
            'total'       => array_sum($rawOrderCounts),
        ];

        // 3. Requisition Slips Metrics (Store Material Issue Track)
        $rawReqCounts = ProductionRequisitionSlip::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $requisitionSummary = [
            'total'            => array_sum($rawReqCounts),
            'fully_issued'     => ($rawReqCounts['Fully Issued'] ?? 0) + ($rawReqCounts['completed'] ?? 0) + ($rawReqCounts['issued'] ?? 0),
            'partially_issued' => ($rawReqCounts['Partially Issued'] ?? 0) + ($rawReqCounts['partial'] ?? 0),
            'pending'          => ($rawReqCounts['Pending'] ?? 0) + ($rawReqCounts['pending'] ?? 0) + ($rawReqCounts['Pending Store Release'] ?? 0),
            'approved'         => ($rawReqCounts['Approved'] ?? 0) + ($rawReqCounts['reserved'] ?? 0),
        ];

        // 4. "Ready to Start" Production Orders (Released/In Progress with Store Material Issued)
        $allActiveOrders = ProductionOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['released', 'in_progress'])
            ->with(['product', 'requisitionSlips', 'schedules', 'operations.operatorAssignments'])
            ->orderByDesc('id')
            ->get();

        $readyToStartOrders = $allActiveOrders->filter(function ($order) {
            if ($order->requisitionSlips->isEmpty()) {
                return false;
            }

            return $order->requisitionSlips->contains(function ($slip) {
                $statusLower = strtolower($slip->status ?? '');
                return in_array($statusLower, ['fully issued', 'partially issued', 'completed', 'issued', 'partial']);
            });
        });

        $readyToStartCount = $readyToStartOrders->count();

        // 5. Operator Assigned Operations Count
        $operatorAssignedCount = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNotNull('operator_id')
                  ->orWhereHas('operatorAssignments');
            })
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['released', 'in_progress']);
            })
            ->count();

        // 6. Quality & Exceptions Tracking (Pending Reworks & Scrap Logged)
        $pendingReworkCount = ProductionOrderRework::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        $scrapLoggedCount = ProductionOrderScrap::where('tenant_id', $tenantId)
            ->count();

        // 7. Recent Production Orders List (with readiness flag & progress calculations)
        $recentOrders = ProductionOrder::where('tenant_id', $tenantId)
            ->with(['product', 'requisitionSlips', 'schedules', 'operations'])
            ->orderByDesc('id')
            ->take(10)
            ->get();

        return view('modules.production.dashboard', compact(
            'pendingRequests',
            'pendingSalesOrderCount',
            'orderStatusCounts',
            'requisitionSummary',
            'readyToStartOrders',
            'readyToStartCount',
            'operatorAssignedCount',
            'pendingReworkCount',
            'scrapLoggedCount',
            'recentOrders'
        ));
    }
}
