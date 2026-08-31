<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\PurchaseRequisition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubcontractPerformanceService
{
    public function __construct(
        protected SubcontractMaterialBalanceService $materialBalanceService,
        protected ProductionCostService $costService
    ) {}

    /**
     * Get Overall Subcontract Management Metrics across all vendors for a tenant.
     */
    public function getOverallMetrics(int $tenantId, array $filters = []): array
    {
        $delivery = $this->getDeliveryMetrics($tenantId, $filters);
        $quality  = $this->getQualityMetrics($tenantId, $filters);
        $cost     = $this->getCostMetrics($tenantId, $filters);
        $wip      = $this->getVendorWipMetrics($tenantId, $filters);
        $material = $this->getMaterialRiskMetrics($tenantId, $filters);
        $automation = $this->getProcurementAutomationMetrics($tenantId, $filters);

        $activeVendorsCount = Vendor::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereHas('operations', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->where('is_external', true);
            })
            ->count();

        return [
            'active_vendors_count'      => $activeVendorsCount,
            'active_ops_at_vendor'      => $wip['active_ops_count'],
            'wip_quantity_at_vendor'    => $wip['wip_quantity'],
            'wip_value_at_vendor'       => $wip['wip_value'],
            'on_time_delivery_pct'      => $delivery['on_time_delivery_pct'],
            'avg_actual_lead_time_days' => $delivery['avg_actual_lead_time_days'],
            'avg_late_delay_days'       => $delivery['avg_late_delay_days'],
            'total_completed_ops'       => $delivery['total_completed_ops'],
            'acceptance_rate'           => $quality['acceptance_rate'],
            'rejection_rate'            => $quality['rejection_rate'],
            'rework_rate'               => $quality['rework_rate'],
            'scrap_rate'                => $quality['scrap_rate'],
            'cost_variance_amount'      => $cost['cost_variance_amount'],
            'cost_variance_pct'         => $cost['cost_variance_pct'],
            'po_rate_variance_pct'      => $cost['po_rate_variance_pct'],
            'unreconciled_material_qty' => $material['total_remaining_qty'],
            'material_risk_count'       => $material['aged_risk_count'],
            'automation'                => $automation,
        ];
    }

    /**
     * SLA & Delivery Metrics.
     * Inclusion date: actual completion / return date (actual_end_time).
     */
    public function getDeliveryMetrics(int $tenantId, array $filters = []): array
    {
        $query = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true);

        $this->applyVendorAndProductFilters($query, $filters);

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $query->where(function ($q) use ($filters) {
                if (!empty($filters['date_from'])) {
                    $q->whereDate('actual_end_time', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $q->whereDate('actual_end_time', '<=', $filters['date_to']);
                }
            });
        }

        // Completed external ops
        $completedOps = (clone $query)->whereIn('status', ['completed', 'received'])->get();

        $totalCompleted = $completedOps->count();
        if ($totalCompleted === 0) {
            return [
                'total_completed_ops'       => 0,
                'on_time_ops_count'         => 0,
                'on_time_delivery_pct'      => 100.0,
                'avg_late_delay_days'       => 0.0,
                'avg_actual_lead_time_days' => 0.0,
                'planned_vs_actual_variance'=> 0.0,
            ];
        }

        $onTimeCount = 0;
        $lateDelaysSum = 0.0;
        $lateOpsCount = 0;
        $actualLeadTimesSum = 0.0;
        $plannedLeadTimesSum = 0.0;
        $validLeadTimeOps = 0;

        foreach ($completedOps as $op) {
            // Find latest delivery challan for expected return date
            $challan = DeliveryChallan::where('tenant_id', $tenantId)
                ->where('production_order_operation_id', $op->id)
                ->latest()
                ->first();

            $expectedReturn = $challan?->expected_return_date 
                ? Carbon::parse($challan->expected_return_date)
                : ($op->actual_start_time ? Carbon::parse($op->actual_start_time)->addDays($op->subcontract_lead_time_days ?: 1) : null);

            $actualReturn = $op->actual_end_time ? Carbon::parse($op->actual_end_time) : null;
            $dispatchDate = $challan?->challan_date 
                ? Carbon::parse($challan->challan_date)
                : ($op->actual_start_time ? Carbon::parse($op->actual_start_time) : null);

            if ($expectedReturn && $actualReturn) {
                if ($actualReturn->lte($expectedReturn->endOfDay())) {
                    $onTimeCount++;
                } else {
                    $delayDays = abs($actualReturn->diffInDays($expectedReturn));
                    $lateDelaysSum += $delayDays;
                    $lateOpsCount++;
                }
            } else {
                $onTimeCount++; // Default to on-time if no expected date set
            }

            if ($dispatchDate && $actualReturn) {
                $leadDays = max(0.1, $actualReturn->diffInDays($dispatchDate));
                $actualLeadTimesSum += $leadDays;
                $plannedLeadTimesSum += (float) ($op->subcontract_lead_time_days ?: 1);
                $validLeadTimeOps++;
            }
        }

        $onTimePct = round(($onTimeCount / $totalCompleted) * 100, 1);
        $avgLateDelay = $lateOpsCount > 0 ? round($lateDelaysSum / $lateOpsCount, 1) : 0.0;
        $avgActualLeadTime = $validLeadTimeOps > 0 ? round($actualLeadTimesSum / $validLeadTimeOps, 1) : 0.0;
        $avgPlannedLeadTime = $validLeadTimeOps > 0 ? round($plannedLeadTimesSum / $validLeadTimeOps, 1) : 0.0;

        return [
            'total_completed_ops'       => $totalCompleted,
            'on_time_ops_count'         => $onTimeCount,
            'on_time_delivery_pct'      => $onTimePct,
            'avg_late_delay_days'       => $avgLateDelay,
            'avg_actual_lead_time_days' => $avgActualLeadTime,
            'avg_planned_lead_time_days'=> $avgPlannedLeadTime,
            'planned_vs_actual_variance'=> round($avgActualLeadTime - $avgPlannedLeadTime, 1),
        ];
    }

    /**
     * Quality Performance Metrics.
     * Inclusion date: completion / log date.
     */
    public function getQualityMetrics(int $tenantId, array $filters = []): array
    {
        $query = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true);

        $this->applyVendorAndProductFilters($query, $filters);

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $query->where(function ($q) use ($filters) {
                if (!empty($filters['date_from'])) {
                    $q->whereDate('actual_end_time', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $q->whereDate('actual_end_time', '<=', $filters['date_to']);
                }
            });
        }

        $ops = $query->get(['id', 'quantity_produced', 'quantity_rejected', 'quantity_scrapped']);

        $acceptedQty = (float) $ops->sum('quantity_produced');
        $rejectedQty = (float) $ops->sum('quantity_rejected');
        $receivedQty = $acceptedQty + $rejectedQty;

        $opIds = $ops->pluck('id')->toArray();

        $reworkQty = 0.0;
        $scrapQty  = (float) $ops->sum('quantity_scrapped');

        if (!empty($opIds)) {
            $reworkQty = (float) ProductionOrderRework::where('tenant_id', $tenantId)
                ->whereIn('production_order_operation_id', $opIds)
                ->sum('quantity');

            $extraScrap = (float) ProductionOrderScrap::where('tenant_id', $tenantId)
                ->whereIn('production_order_operation_id', $opIds)
                ->sum('quantity');

            $scrapQty = max($scrapQty, $extraScrap);
        }

        if ($receivedQty <= 0) {
            return [
                'received_qty'    => 0.0,
                'accepted_qty'    => 0.0,
                'rejected_qty'    => 0.0,
                'rework_qty'      => 0.0,
                'scrap_qty'       => 0.0,
                'acceptance_rate' => 100.0,
                'rejection_rate'  => 0.0,
                'rework_rate'     => 0.0,
                'scrap_rate'      => 0.0,
            ];
        }

        return [
            'received_qty'    => round($receivedQty, 2),
            'accepted_qty'    => round($acceptedQty, 2),
            'rejected_qty'    => round($rejectedQty, 2),
            'rework_qty'      => round($reworkQty, 2),
            'scrap_qty'       => round($scrapQty, 2),
            'acceptance_rate' => round(($acceptedQty / $receivedQty) * 100, 1),
            'rejection_rate'  => round(($rejectedQty / $receivedQty) * 100, 1),
            'rework_rate'     => round(($reworkQty / $receivedQty) * 100, 1),
            'scrap_rate'      => round(($scrapQty / $receivedQty) * 100, 1),
        ];
    }

    /**
     * Cost & Price Variance Metrics.
     */
    public function getCostMetrics(int $tenantId, array $filters = []): array
    {
        $query = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true);

        $this->applyVendorAndProductFilters($query, $filters);

        $ops = $query->with('order')->get(['id', 'production_order_id', 'subcontract_cost_per_unit', 'target_produced_qty']);

        $plannedCost   = 0.0;
        $committedCost = 0.0;
        $actualCost    = 0.0;
        $rateVariances = [];

        foreach ($ops as $op) {
            $qty = $op->target_produced_qty ?: ($op->order?->quantity_ordered ?: 1.0);
            $plannedRate = (float) $op->subcontract_cost_per_unit;
            $opPlanned = $plannedRate * $qty;
            $plannedCost += $opPlanned;

            // Fetch PO item
            $poItem = PurchaseOrderItem::where('production_order_operation_id', $op->id)
                ->whereHas('order', function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->where('status', '!=', 'Cancelled');
                })
                ->with('order')
                ->first();

            if ($poItem) {
                $poRate = (float) $poItem->rate;
                $opCommitted = (float) ($poItem->total_amount ?? ($poItem->quantity * $poRate));
                $committedCost += $opCommitted;

                // Rate variance %
                if ($plannedRate > 0) {
                    $rateVariances[] = (($poRate - $plannedRate) / $plannedRate) * 100;
                }

                // Vendor Bill actual cost
                $vendorBillItem = DB::table('vendor_bill_items')
                    ->join('vendor_bills', 'vendor_bill_items.vendor_bill_id', '=', 'vendor_bills.id')
                    ->where('vendor_bills.tenant_id', $tenantId)
                    ->where('vendor_bills.purchase_order_id', $poItem->purchase_order_id)
                    ->where('vendor_bill_items.product_id', $poItem->product_id)
                    ->select('vendor_bill_items.*')
                    ->first();

                if ($vendorBillItem && isset($vendorBillItem->total_amount)) {
                    $actualCost += (float) $vendorBillItem->total_amount;
                } else {
                    $actualCost += $opCommitted;
                }
            } else {
                $committedCost += $opPlanned;
                $actualCost += $opPlanned;
            }
        }

        $costVarAmount = $actualCost - $plannedCost;
        $costVarPct = $plannedCost > 0 ? round(($costVarAmount / $plannedCost) * 100, 1) : 0.0;
        $poRateVarPct = !empty($rateVariances) ? round(array_sum($rateVariances) / count($rateVariances), 1) : 0.0;

        return [
            'planned_cost'          => round($plannedCost, 2),
            'committed_po_cost'     => round($committedCost, 2),
            'actual_cost'           => round($actualCost, 2),
            'cost_variance_amount'  => round($costVarAmount, 2),
            'cost_variance_pct'     => $costVarPct,
            'po_rate_variance_pct'  => $poRateVarPct,
        ];
    }

    /**
     * WIP at Vendor Metrics.
     */
    public function getVendorWipMetrics(int $tenantId, array $filters = []): array
    {
        $query = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true)
            ->where('status', 'vendor_dispatched');

        $this->applyVendorAndProductFilters($query, $filters);

        $activeOps = $query->with('order')->get();
        $activeOpsCount = $activeOps->count();

        $wipQty = 0.0;
        $wipValue = 0.0;

        foreach ($activeOps as $op) {
            $order = $op->order;
            $qty = (float) ($op->target_produced_qty ?: ($order?->quantity_ordered ?: 0.0));
            $wipQty += $qty;

            // Fetch accumulated WIP card value
            $wipCardValue = ProductionWip::where('tenant_id', $tenantId)
                ->where('production_order_id', $op->production_order_id)
                ->sum('total_value');

            if ($wipCardValue > 0) {
                $wipValue += (float) $wipCardValue;
            } else {
                // Estimate based on unit cost + subcontract rate
                $unitEst = ((float) $op->subcontract_cost_per_unit) + ($order?->product?->unit_cost ?? 10.0);
                $wipValue += ($unitEst * $qty);
            }
        }

        return [
            'active_ops_count' => $activeOpsCount,
            'wip_quantity'     => round($wipQty, 2),
            'wip_value'        => round($wipValue, 2),
        ];
    }

    /**
     * Material Risk Metrics (Company Supplied Raw Material Balances).
     */
    public function getMaterialRiskMetrics(int $tenantId, array $filters = []): array
    {
        $query = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true)
            ->where('material_supply_type', 'company_supplied');

        $this->applyVendorAndProductFilters($query, $filters);

        $ops = $query->get(['id', 'production_order_id', 'vendor_id', 'created_at']);

        $totalSent = 0.0;
        $totalConsumed = 0.0;
        $totalReturned = 0.0;
        $totalScrapped = 0.0;
        $totalRemaining = 0.0;
        $agedRiskCount = 0;

        foreach ($ops as $op) {
            $bal = $this->materialBalanceService->getMaterialBalance($tenantId, $op->production_order_id, $op->id);
            $totalSent += $bal['sent'];
            $totalConsumed += $bal['consumed'];
            $totalReturned += $bal['returned'];
            $totalScrapped += $bal['scrapped'];
            $totalRemaining += $bal['remaining'];

            if ($bal['remaining'] > 0 && $op->created_at && Carbon::parse($op->created_at)->lt(now()->subDays(30))) {
                $agedRiskCount++;
            }
        }

        return [
            'total_sent_qty'      => round($totalSent, 2),
            'total_consumed_qty'  => round($totalConsumed, 2),
            'total_returned_qty'  => round($totalReturned, 2),
            'total_scrapped_qty'  => round($totalScrapped, 2),
            'total_remaining_qty' => round($totalRemaining, 2),
            'aged_risk_count'     => $agedRiskCount,
        ];
    }

    /**
     * Procurement Automation Statistics.
     */
    public function getProcurementAutomationMetrics(int $tenantId, array $filters = []): array
    {
        $pos = PurchaseOrder::where('tenant_id', $tenantId)
            ->where('is_subcontract', true)
            ->where('status', '!=', 'Cancelled')
            ->get(['id', 'status', 'purchase_requisition_id', 'notes']);

        $manualPrPo = 0;
        $autoDraftPo = 0;
        $autoApprovedPo = 0;
        $autoApprovalFallback = 0;
        $fallbackReasons = [];

        foreach ($pos as $po) {
            $isPR = $po->purchase_requisition_id !== null;
            $notes = (string) $po->notes;

            if ($isPR) {
                $manualPrPo++;
            } elseif (str_contains($notes, '[Auto-Approval Skipped:')) {
                $autoDraftPo++;
                $autoApprovalFallback++;
                if (preg_match('/\[Auto-Approval Skipped: (.*?)\]/', $notes, $matches)) {
                    $reason = trim($matches[1]);
                    $fallbackReasons[$reason] = ($fallbackReasons[$reason] ?? 0) + 1;
                }
            } elseif ($po->status === 'Approved') {
                $autoApprovedPo++;
            } else {
                $autoDraftPo++;
            }
        }

        return [
            'manual_pr_po_count'        => $manualPrPo,
            'auto_draft_po_count'       => $autoDraftPo,
            'auto_approved_po_count'    => $autoApprovedPo,
            'auto_approval_fallback_cnt'=> $autoApprovalFallback,
            'fallback_reasons'          => $fallbackReasons,
        ];
    }

    /**
     * Per-Vendor Scorecard Comparison Table.
     */
    public function getVendorComparisonTable(int $tenantId, array $filters = []): array
    {
        $vendors = Vendor::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereHas('operations', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->where('is_external', true);
            })
            ->get();

        $comparison = [];

        foreach ($vendors as $v) {
            $vFilters = array_merge($filters, ['vendor_id' => $v->id]);
            $del = $this->getDeliveryMetrics($tenantId, $vFilters);
            $qual = $this->getQualityMetrics($tenantId, $vFilters);
            $cost = $this->getCostMetrics($tenantId, $vFilters);
            $wip = $this->getVendorWipMetrics($tenantId, $vFilters);

            $comparison[] = [
                'vendor_id'             => $v->id,
                'vendor_name'           => $v->name,
                'vendor_code'           => $v->vendor_code ?? 'VND',
                'active_ops'            => $wip['active_ops_count'],
                'completed_ops'         => $del['total_completed_ops'],
                'on_time_pct'           => $del['on_time_delivery_pct'],
                'avg_delay_days'        => $del['avg_late_delay_days'],
                'avg_lead_time_days'    => $del['avg_actual_lead_time_days'],
                'acceptance_rate'       => $qual['acceptance_rate'],
                'rework_rate'           => $qual['rework_rate'],
                'scrap_rate'            => $qual['scrap_rate'],
                'cost_variance_pct'     => $cost['cost_variance_pct'],
                'wip_quantity'          => $wip['wip_quantity'],
                'wip_value'             => $wip['wip_value'],
            ];
        }

        // Sort by on_time_pct desc, acceptance_rate desc
        usort($comparison, fn($a, $b) => $b['on_time_pct'] <=> $a['on_time_pct'] ?: $b['acceptance_rate'] <=> $a['acceptance_rate']);

        return $comparison;
    }

    /**
     * Delayed Vendor Operations Report with Successor Blockage Analysis.
     */
    public function getDelayedOperationsReport(int $tenantId, array $filters = []): array
    {
        $today = date('Y-m-d');

        // Query active external operations where expected return date is passed
        $query = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('is_external', true)
            ->whereNotIn('status', ['completed', 'cancelled', 'skipped']);

        $this->applyVendorAndProductFilters($query, $filters);

        $delayedOps = $query->with(['order.product', 'vendor', 'order.operations'])->get();
        $report = [];

        foreach ($delayedOps as $op) {
            $challan = DeliveryChallan::where('tenant_id', $tenantId)
                ->where('production_order_operation_id', $op->id)
                ->latest()
                ->first();

            $expectedReturn = $challan?->expected_return_date 
                ? Carbon::parse($challan->expected_return_date)
                : ($op->actual_start_time ? Carbon::parse($op->actual_start_time)->addDays($op->subcontract_lead_time_days ?: 1) : null);

            if ($expectedReturn && $expectedReturn->lt(now()->startOfDay())) {
                $daysOverdue = (int) abs(now()->startOfDay()->diffInDays($expectedReturn));

                // Successor blockage detection
                $blockingOp = null;
                if ($op->order && $op->order->operations) {
                    $nextOp = $op->order->operations
                        ->where('sequence', '>', $op->sequence)
                        ->whereNotIn('status', ['completed', 'cancelled'])
                        ->first();

                    if ($nextOp) {
                        $blockingOp = "Op #{$nextOp->sequence} — {$nextOp->name}";
                    }
                }

                $po = PurchaseOrder::where('tenant_id', $tenantId)
                    ->where('production_order_id', $op->production_order_id)
                    ->latest()
                    ->first();

                $report[] = [
                    'operation_id'        => $op->id,
                    'order_id'            => $op->production_order_id,
                    'order_number'        => $op->order?->order_number ?? 'MO-???',
                    'product_name'        => $op->order?->product?->name ?? 'Finished Product',
                    'operation_name'      => "Op #{$op->sequence} — {$op->name}",
                    'vendor_id'           => $op->vendor_id,
                    'vendor_name'         => $op->vendor?->name ?? 'Vendor',
                    'dispatch_date'       => $challan?->challan_date ?: ($op->actual_start_time ? date('Y-m-d', strtotime($op->actual_start_time)) : '—'),
                    'expected_return_date'=> $expectedReturn->format('Y-m-d'),
                    'days_overdue'        => $daysOverdue,
                    'quantity_outstanding'=> number_format($op->target_produced_qty ?: ($op->order?->quantity_ordered ?: 0), 2),
                    'po_number'           => $po?->purchase_order_number ?: '—',
                    'blocking_successor'  => $blockingOp,
                ];
            }
        }

        // Sort by days_overdue desc
        usort($report, fn($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);

        return $report;
    }

    /**
     * Vendor Detail Scorecard & Analytics.
     */
    public function getVendorDetailAnalytics(int $tenantId, int $vendorId, array $filters = []): array
    {
        $vFilters = array_merge($filters, ['vendor_id' => $vendorId]);

        $delivery = $this->getDeliveryMetrics($tenantId, $vFilters);
        $quality  = $this->getQualityMetrics($tenantId, $vFilters);
        $cost     = $this->getCostMetrics($tenantId, $vFilters);
        $wip      = $this->getVendorWipMetrics($tenantId, $vFilters);
        $material = $this->getMaterialRiskMetrics($tenantId, $vFilters);

        $recentDelays = $this->getDelayedOperationsReport($tenantId, $vFilters);

        $activeDispatches = DeliveryChallan::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendorId)
            ->where('status', 'dispatched')
            ->with(['productionOrder.product', 'items.product'])
            ->latest()
            ->take(5)
            ->get();

        return [
            'vendor_id'                 => $vendorId,
            'delivery'                  => $delivery,
            'quality'                   => $quality,
            'cost'                      => $cost,
            'wip'                       => $wip,
            'material'                  => $material,
            'recent_delays'             => array_slice($recentDelays, 0, 5),
            'active_dispatches'         => $activeDispatches,
        ];
    }

    protected function applyVendorAndProductFilters($query, array $filters): void
    {
        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->whereHas('order', function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }
    }
}
