<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseBill;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Inventory\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplyChainDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $preset = $request->get('preset', 'this_month');
        $fromDate = $request->get('from');
        $toDate = $request->get('to');

        [$startDate, $endDate] = $this->resolveDatePeriod($preset, $fromDate, $toDate);

        // 1. Sales & Customer Orders Metrics
        $salesOrderQuery = SalesOrder::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $totalSalesValue = (float) (clone $salesOrderQuery)->sum('total_amount');
        $salesOrdersCount = (clone $salesOrderQuery)->count();
        $pendingSalesOrdersCount = SalesOrder::where('tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(status)'), ['pending', 'draft', 'processing', 'in_progress'])
            ->count();

        $invoiceQuery = Invoice::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $invoicesTotalValue = (float) (clone $invoiceQuery)->sum('total_amount');
        $invoicesCount = (clone $invoiceQuery)->count();

        $paymentsQuery = CustomerPayment::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $paymentsTotalValue = (float) (clone $paymentsQuery)->sum('amount');
        $paymentsCount = (clone $paymentsQuery)->count();

        // 2. Inventory & Stock Valuation Metrics
        $products = Product::where('tenant_id', $tenantId)->sellable()->get();
        $totalProductsCount = $products->count();
        $totalInventoryValuation = $products->sum(function ($p) {
            $stock = $p->total_stock;
            $price = $p->cost_price ?: ($p->unit_cost ?: ($p->selling_price ?: 0));
            return $stock * $price;
        });

        $lowStockProductsList = $products->filter(function ($p) {
            $reorder = (float) ($p->reorder_point ?: 10);
            return $p->total_stock <= $reorder;
        })->take(10);

        $lowStockCount = $lowStockProductsList->count();

        $materialRequestsCount = MaterialRequirement::where('tenant_id', $tenantId)->count();
        $dispatchOrdersCount = DispatchOrder::where('tenant_id', $tenantId)->count();

        // 3. Purchase & Goods Receipt Metrics
        $poQuery = PurchaseOrder::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $totalPurchaseValue = (float) (clone $poQuery)->sum('grand_total');
        $purchaseOrdersCount = (clone $poQuery)->count();
        $pendingPurchaseOrdersCount = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(status)'), ['pending', 'draft', 'issued', 'sent'])
            ->count();

        $grnQuery = GoodsReceiptNote::where('tenant_id', $tenantId)->whereBetween('created_at', [$startDate, $endDate]);
        $goodsReceiptsCount = (clone $grnQuery)->count();
        $vendorsCount = Vendor::where('tenant_id', $tenantId)->count();

        // 4. Monthly Trend Data (Last 6 Months)
        $monthlyTrend = $this->calculateMonthlyTrend($tenantId);

        // 5. Recent Records
        $recentSalesOrders = SalesOrder::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentPurchaseOrders = PurchaseOrder::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentGoodsReceipts = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('modules.inventory.supply_chain_dashboard', [
            'preset'                     => $preset,
            'startDate'                  => $startDate,
            'endDate'                    => $endDate,
            'totalSalesValue'            => $totalSalesValue,
            'salesOrdersCount'           => $salesOrdersCount,
            'pendingSalesOrdersCount'    => $pendingSalesOrdersCount,
            'invoicesTotalValue'         => $invoicesTotalValue,
            'invoicesCount'              => $invoicesCount,
            'paymentsTotalValue'         => $paymentsTotalValue,
            'paymentsCount'              => $paymentsCount,
            'totalProductsCount'         => $totalProductsCount,
            'totalInventoryValuation'    => $totalInventoryValuation,
            'lowStockCount'              => $lowStockCount,
            'lowStockProductsList'       => $lowStockProductsList,
            'materialRequestsCount'      => $materialRequestsCount,
            'dispatchOrdersCount'        => $dispatchOrdersCount,
            'totalPurchaseValue'         => $totalPurchaseValue,
            'purchaseOrdersCount'        => $purchaseOrdersCount,
            'pendingPurchaseOrdersCount' => $pendingPurchaseOrdersCount,
            'goodsReceiptsCount'         => $goodsReceiptsCount,
            'vendorsCount'               => $vendorsCount,
            'monthlyTrend'               => $monthlyTrend,
            'recentSalesOrders'          => $recentSalesOrders,
            'recentPurchaseOrders'       => $recentPurchaseOrders,
            'recentGoodsReceipts'        => $recentGoodsReceipts,
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
        $months = [];
        $salesOrdersData = [];
        $invoicesData = [];
        $paymentsData = [];
        $purchaseData = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $start = $monthDate->copy()->startOfMonth();
            $end = $monthDate->copy()->endOfMonth();

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
