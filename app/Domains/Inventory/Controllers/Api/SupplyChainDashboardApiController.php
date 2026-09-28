<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Inventory\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SupplyChainDashboardApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/inventory/dashboard/stats
     */
    public function stats(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $preset   = $request->get('preset', 'this_month');
        $fromDate = $request->get('from');
        $toDate   = $request->get('to');

        [$startDate, $endDate] = $this->resolveDatePeriod($preset, $fromDate, $toDate);

        // 1. Sales & Customer Orders Metrics
        $salesOrderQuery         = SalesOrder::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $totalSalesValue         = (float) (clone $salesOrderQuery)->sum('total_amount');
        $salesOrdersCount        = (clone $salesOrderQuery)->count();
        $pendingSalesOrdersCount = SalesOrder::where('tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(status)'), ['pending', 'draft', 'processing', 'in_progress'])
            ->count();

        $invoiceQuery       = Invoice::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $invoicesTotalValue = (float) (clone $invoiceQuery)->sum('total_amount');
        $invoicesCount      = (clone $invoiceQuery)->count();

        $paymentsQuery      = CustomerPayment::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $paymentsTotalValue = (float) (clone $paymentsQuery)->sum('amount');
        $paymentsCount      = (clone $paymentsQuery)->count();

        // 2. Inventory & Stock Valuation Metrics
        $totalProductsCount      = Product::where('tenant_id', $tenantId)->sellable()->count();
        $totalInventoryValuation = (float) ProductWarehouseStock::where('tenant_id', $tenantId)
            ->selectRaw('SUM(quantity * unit_cost) as total_val')
            ->value('total_val') ?: 0.0;

        $lowStockProductsList = Product::where('tenant_id', $tenantId)
            ->sellable()
            ->with('warehouseStocks')
            ->get()
            ->filter(function ($p) {
                $reorder = (float) ($p->reorder_point ?: 10);
                return $p->total_stock <= $reorder;
            })->take(10)->values();

        $lowStockCount         = $lowStockProductsList->count();
        $materialRequestsCount = MaterialRequirement::where('tenant_id', $tenantId)->count();
        $dispatchOrdersCount   = DispatchOrder::where('tenant_id', $tenantId)->count();

        // 3. Purchase & Goods Receipt Metrics
        $poQuery                    = PurchaseOrder::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $totalPurchaseValue         = (float) (clone $poQuery)->sum('grand_total');
        $purchaseOrdersCount        = (clone $poQuery)->count();
        $pendingPurchaseOrdersCount = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(status)'), ['pending', 'draft', 'issued', 'sent'])
            ->count();

        $grnQuery           = GoodsReceiptNote::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $goodsReceiptsCount = (clone $grnQuery)->count();
        $vendorsCount       = Vendor::where('tenant_id', $tenantId)->count();

        // 4. Monthly Trend Data (Last 6 Months)
        $monthlyTrend = $this->calculateMonthlyTrend($tenantId);

        // 5. Recent Records
        $recentSalesOrders = SalesOrder::where('tenant_id', $tenantId)
            ->with('customer:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentPurchaseOrders = PurchaseOrder::where('tenant_id', $tenantId)
            ->with('vendor:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentGoodsReceipts = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with(['vendor:id,name', 'warehouse:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'period' => [
                    'preset'     => $preset,
                    'start_date' => $startDate->toDateString(),
                    'end_date'   => $endDate->toDateString(),
                ],
                'sales' => [
                    'total_sales_value'         => round($totalSalesValue, 2),
                    'sales_orders_count'        => $salesOrdersCount,
                    'pending_sales_orders_count'=> $pendingSalesOrdersCount,
                    'invoices_total_value'      => round($invoicesTotalValue, 2),
                    'invoices_count'            => $invoicesCount,
                    'payments_total_value'      => round($paymentsTotalValue, 2),
                    'payments_count'            => $paymentsCount,
                ],
                'inventory' => [
                    'total_products_count'      => $totalProductsCount,
                    'total_inventory_valuation' => round($totalInventoryValuation, 2),
                    'low_stock_count'           => $lowStockCount,
                    'low_stock_sample'          => $lowStockProductsList,
                    'material_requests_count'   => $materialRequestsCount,
                    'dispatch_orders_count'     => $dispatchOrdersCount,
                ],
                'purchase' => [
                    'total_purchase_value'          => round($totalPurchaseValue, 2),
                    'purchase_orders_count'         => $purchaseOrdersCount,
                    'pending_purchase_orders_count' => $pendingPurchaseOrdersCount,
                    'goods_receipts_count'          => $goodsReceiptsCount,
                    'vendors_count'                 => $vendorsCount,
                ],
                'monthly_trend' => $monthlyTrend,
                'recent' => [
                    'sales_orders'    => $recentSalesOrders,
                    'purchase_orders' => $recentPurchaseOrders,
                    'goods_receipts'  => $recentGoodsReceipts,
                ],
            ],
        ]);
    }

    private function resolveDatePeriod(string $preset, ?string $from, ?string $to): array
    {
        $now = Carbon::now();

        if ($preset === 'custom' && $from && $to) {
            return [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ];
        }

        return match ($preset) {
            'today'        => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'last_month'   => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'this_year'    => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'all'          => [Carbon::parse('2020-01-01')->startOfDay(), $now->copy()->endOfDay()],
            default        => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    private function calculateMonthlyTrend(int $tenantId): array
    {
        $months          = [];
        $salesOrdersData = [];
        $invoicesData    = [];
        $paymentsData    = [];
        $purchaseData    = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $start     = $monthDate->copy()->startOfMonth();
            $end       = $monthDate->copy()->endOfMonth();

            $months[] = $monthDate->format('M Y');

            $soSum = (float) SalesOrder::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_amount');

            $invSum = (float) Invoice::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_amount');

            $paySum = (float) CustomerPayment::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount');

            $purchaseSum = (float) PurchaseOrder::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$start, $end])
                ->sum('grand_total');

            $salesOrdersData[] = round($soSum, 2);
            $invoicesData[]    = round($invSum, 2);
            $paymentsData[]    = round($paySum, 2);
            $purchaseData[]    = round($purchaseSum, 2);
        }

        return [
            'labels'       => $months,
            'sales_orders' => $salesOrdersData,
            'invoices'     => $invoicesData,
            'payments'     => $paymentsData,
            'purchase'     => $purchaseData,
        ];
    }
}
