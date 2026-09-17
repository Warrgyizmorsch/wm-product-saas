@extends('layouts.duralux')

@section('title', 'Supply Chain Dashboard | SaaS ERP')
@section('page-title', 'Supply Chain Dashboard')
@section('breadcrumb', 'Supply Chain / Dashboard')

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        {{-- Quick Action Shortcuts --}}
        <x-ui.button href="{{ route('sales.orders.create') }}" variant="primary" icon="feather-plus">
            New Sales Order
        </x-ui.button>
        <x-ui.button href="{{ route('purchase.orders.create') }}" variant="soft-success" icon="feather-shopping-bag">
            New PO
        </x-ui.button>
        <x-ui.button href="{{ route('grns.create') }}" variant="soft-info" icon="feather-package">
            New GRN
        </x-ui.button>
        <x-ui.button href="{{ route('inventory.adjustments.create') }}" variant="soft-primary" icon="feather-sliders">
            Stock Adjustment
        </x-ui.button>
    </div>
@endsection

@section('content')
@php
    $currencySymbol = active_currency_symbol();
    $formatCurrency = fn($amount) => active_currency_symbol() . ' ' . number_format((float) $amount, 2);
@endphp

{{-- Filter Card --}}
<div class="card stretch stretch-full mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('supply-chain.dashboard') }}" class="row g-2 align-items-end" id="supply-chain-filter-form">
            <div class="col-xl-3 col-md-4 col-sm-6">
                <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="preset">
                    <i class="feather-calendar me-1"></i>Time Period
                </label>
                <select name="preset" id="preset" class="form-select form-select-sm border-gray-300" onchange="this.form.submit()">
                    <option value="today" @selected($preset === 'today')>Today</option>
                    <option value="this_month" @selected($preset === 'this_month')>This Month</option>
                    <option value="last_month" @selected($preset === 'last_month')>Last Month</option>
                    <option value="this_quarter" @selected($preset === 'this_quarter')>This Quarter</option>
                    <option value="this_year" @selected($preset === 'this_year')>This Year</option>
                    <option value="all" @selected($preset === 'all')>All Time</option>
                    <option value="custom" @selected($preset === 'custom')>Custom Range...</option>
                </select>
            </div>

            @if ($preset === 'custom')
                <div class="col-xl-2 col-md-3">
                    <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="from">From Date</label>
                    <input type="date" name="from" id="from" class="form-control form-control-sm" value="{{ request('from', $startDate->toDateString()) }}">
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="to">To Date</label>
                    <input type="date" name="to" id="to" class="form-control form-control-sm" value="{{ request('to', $endDate->toDateString()) }}">
                </div>
                <div class="col-xl-1 col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="feather-filter"></i></button>
                </div>
            @endif

            <div class="col ms-auto text-end d-none d-md-block">
                <span class="fs-12 text-muted fw-semibold">
                    Period: <span class="text-dark fw-bold">{{ $startDate->format('d M Y') }}</span> to <span class="text-dark fw-bold">{{ $endDate->format('d M Y') }}</span>
                </span>
            </div>
        </form>
    </div>
</div>

{{-- Top 6 Supply Chain & Revenue KPI Cards --}}
<div class="row g-3 mb-4">
    {{-- Sales Orders Demand --}}
    <div class="col-xxl col-xl-4 col-md-6">
        <div class="card stretch stretch-full border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fs-11 fw-bold text-uppercase text-muted">Sales Orders (Demand)</span>
                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3">
                        <i class="feather-shopping-cart fs-16"></i>
                    </div>
                </div>
                <h4 class="fw-bolder mb-1 text-dark">{{ $formatCurrency($totalSalesValue) }}</h4>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <span class="badge bg-soft-info text-info fs-10 fw-semibold">{{ $salesOrdersCount }} Orders</span>
                    <span class="fs-10 text-muted">{{ $pendingSalesOrdersCount }} Pending</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Billed Revenue (Invoices) --}}
    <div class="col-xxl col-xl-4 col-md-6">
        <div class="card stretch stretch-full border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fs-11 fw-bold text-uppercase text-muted">Billed Revenue (Invoices)</span>
                    <div class="avatar-text avatar-md bg-soft-indigo text-indigo rounded-3">
                        <i class="feather-file-text fs-16"></i>
                    </div>
                </div>
                <h4 class="fw-bolder mb-1 text-dark">{{ $formatCurrency($invoicesTotalValue) }}</h4>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <span class="badge bg-soft-indigo text-indigo fs-10 fw-semibold">{{ $invoicesCount }} Invoices</span>
                    <span class="fs-10 text-muted">Billed Sales</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Total Stock Valuation --}}
    <div class="col-xxl col-xl-4 col-md-6">
        <div class="card stretch stretch-full border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fs-11 fw-bold text-uppercase text-muted">Stock Valuation</span>
                    <div class="avatar-text avatar-md bg-soft-success text-success rounded-3">
                        <i class="feather-box fs-16"></i>
                    </div>
                </div>
                <h4 class="fw-bolder mb-1 text-dark">{{ $formatCurrency($totalInventoryValuation) }}</h4>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <span class="badge bg-soft-success text-success fs-10 fw-semibold">{{ $totalProductsCount }} SKUs</span>
                    <span class="fs-10 text-muted">Cost Basis</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Low Stock Warnings --}}
    <div class="col-xxl col-xl-4 col-md-6">
        <div class="card stretch stretch-full border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fs-11 fw-bold text-uppercase text-muted">Low Stock Alerts</span>
                    <div class="avatar-text avatar-md bg-soft-danger text-danger rounded-3">
                        <i class="feather-alert-triangle fs-16"></i>
                    </div>
                </div>
                <h4 class="fw-bolder mb-1 text-danger">{{ number_format($lowStockCount) }}</h4>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <span class="badge bg-soft-danger text-danger fs-10 fw-semibold">Reorder Pt</span>
                    <span class="fs-10 text-muted">&le; Threshold</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Purchase PO Spend --}}
    <div class="col-xxl col-xl-4 col-md-6">
        <div class="card stretch stretch-full border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fs-11 fw-bold text-uppercase text-muted">PO Spend</span>
                    <div class="avatar-text avatar-md bg-soft-warning text-warning rounded-3">
                        <i class="feather-truck fs-16"></i>
                    </div>
                </div>
                <h4 class="fw-bolder mb-1 text-dark">{{ $formatCurrency($totalPurchaseValue) }}</h4>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <span class="badge bg-soft-warning text-warning fs-10 fw-semibold">{{ $purchaseOrdersCount }} POs</span>
                    <span class="fs-10 text-muted">{{ $pendingPurchaseOrdersCount }} Pending</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Supply Chain Movement Dynamic Multi-Metric Chart --}}
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card stretch stretch-full border-0 shadow-sm">
            <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark fs-14">
                        <i class="feather-trending-up text-primary me-2"></i>Supply Chain Trend & Revenue Analysis
                    </h5>
                    <span class="fs-11 text-muted">Compare Sales Orders, Billed Invoices, or Cash Flow against Purchase Spend</span>
                </div>

                {{-- Metric Switcher Dropdown --}}
                <div class="d-flex align-items-center gap-2">
                    <label for="chartMetricSelector" class="fs-11 text-muted me-1 mb-0 fw-semibold">Revenue Source:</label>
                    <select id="chartMetricSelector" class="form-select form-select-sm fs-12 fw-semibold text-dark shadow-sm border-gray-300" style="width: 210px; cursor: pointer;">
                        <option value="sales_orders" selected>Sales Orders (Demand)</option>
                        <option value="invoices">Invoices (Billed Revenue)</option>
                        <option value="payments">Payments (Cash Flow)</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-3">
                <div id="supplyChainTrendChart" style="min-height: 290px;"></div>
            </div>
        </div>
    </div>
</div>

{{-- Low Stock Warning & Supply Chain Transactions Row --}}
<div class="row g-3">
    {{-- Low Stock & Reorder Alert List --}}
    <div class="col-lg-6">
        <div class="card stretch stretch-full border-0 shadow-sm h-100">
            <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0 text-dark fs-14">
                    <i class="feather-alert-octagon text-danger me-2"></i>Low Stock Items (Reorder Warning)
                </h5>
                <x-ui.button href="{{ route('inventory.reports.low-stock') }}" variant="soft-primary" icon="feather-arrow-right" iconPosition="right" class="fs-11 py-1 px-2">
                    View Full Report
                </x-ui.button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-12">
                        <thead class="bg-light-50 text-muted fs-11 text-uppercase">
                            <tr>
                                <th class="ps-3">Item / SKU</th>
                                <th class="text-end">Current Stock</th>
                                <th class="text-end">Reorder Pt</th>
                                <th class="text-center pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lowStockProductsList as $prod)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark">{{ $prod->name }}</div>
                                        <div class="fs-10 text-muted font-monospace">{{ $prod->sku ?: 'No SKU' }}</div>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-soft-danger text-danger font-monospace fs-11 fw-bold">
                                            {{ number_format($prod->total_stock, 2) }}
                                        </span>
                                    </td>
                                    <td class="text-end font-monospace text-muted">
                                        {{ number_format((float)($prod->reorder_point ?: 10), 2) }}
                                    </td>
                                    <td class="text-center pe-3">
                                        <x-ui.button href="{{ route('purchase.orders.create') }}" variant="soft-primary" class="py-0 px-2 fs-10">
                                            Create PO
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted fs-12">
                                        <i class="feather-check-circle text-success fs-20 d-block mb-1"></i>
                                        All inventory levels are above reorder thresholds!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Sales & Purchase Orders Tab View --}}
    <div class="col-lg-6">
        <div class="card stretch stretch-full border-0 shadow-sm h-100">
            <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0 text-dark fs-14">
                    <i class="feather-clock text-info me-2"></i>Recent Supply Chain Orders
                </h5>
                <x-ui.horizontal-tabs id="orderTabNav" :tabs="[
                    ['id' => 'tab-recent-so', 'label' => 'Sales Orders', 'active' => true, 'icon' => 'feather-shopping-cart'],
                    ['id' => 'tab-recent-po', 'label' => 'Purchase Orders', 'active' => false, 'icon' => 'feather-shopping-bag'],
                    ['id' => 'tab-recent-grn', 'label' => 'GRNs', 'active' => false, 'icon' => 'feather-package'],
                ]" class="border-0 p-0" />
            </div>
            <div class="card-body p-0">
                <div class="tab-content" id="orderTabContent">
                    {{-- Tab 1: Recent Sales Orders --}}
                    <div class="tab-pane fade show active" id="tab-recent-so">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-12">
                                <thead class="bg-light-50 text-muted fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-3">SO #</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentSalesOrders as $so)
                                        <tr>
                                            <td class="ps-3">
                                                <a href="{{ route('sales.orders.show', $so->id) }}" class="fw-bold text-primary font-monospace">
                                                    {{ $so->so_number }}
                                                </a>
                                                <div class="fs-10 text-muted">{{ $so->created_at->format('d M Y') }}</div>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-info text-info fs-10 fw-semibold text-uppercase">
                                                    {{ $so->status }}
                                                </span>
                                            </td>
                                            <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                                {{ $formatCurrency($so->total_amount) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted fs-12">No recent sales orders recorded.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 2: Recent Purchase Orders --}}
                    <div class="tab-pane fade" id="tab-recent-po">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-12">
                                <thead class="bg-light-50 text-muted fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-3">PO #</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentPurchaseOrders as $po)
                                        <tr>
                                            <td class="ps-3">
                                                <a href="{{ route('purchase.orders.show', $po->id) }}" class="fw-bold text-warning font-monospace">
                                                    {{ $po->purchase_order_number ?: ($po->po_number ?: ('PO-#' . $po->id)) }}
                                                </a>
                                                <div class="fs-10 text-muted">{{ $po->created_at->format('d M Y') }}</div>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-warning text-warning fs-10 fw-semibold text-uppercase">
                                                    {{ $po->status }}
                                                </span>
                                            </td>
                                            <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                                {{ $formatCurrency($po->grand_total) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted fs-12">No recent purchase orders recorded.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 3: Recent Goods Receipts --}}
                    <div class="tab-pane fade" id="tab-recent-grn">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-12">
                                <thead class="bg-light-50 text-muted fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-3">GRN #</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Received Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentGoodsReceipts as $grn)
                                        <tr>
                                            <td class="ps-3">
                                                <a href="{{ route('grns.show', $grn->id) }}" class="fw-bold text-teal font-monospace">
                                                    {{ $grn->grn_number ?: ('GRN-#' . $grn->id) }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-teal text-teal fs-10 fw-semibold text-uppercase">
                                                    {{ $grn->status ?: 'Completed' }}
                                                </span>
                                            </td>
                                            <td class="text-end pe-3 fs-11 text-muted">
                                                {{ $grn->created_at->format('d M Y') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted fs-12">No recent goods receipts recorded.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var monthlyTrendData = @json($monthlyTrend);

        var metricNames = {
            'sales_orders': 'Sales Orders (Demand)',
            'invoices': 'Sales Invoices (Billed Revenue)',
            'payments': 'Customer Payments (Cash Realized)'
        };

        var currentMetric = 'sales_orders';

        var options = {
            series: [{
                name: metricNames[currentMetric],
                data: monthlyTrendData[currentMetric] || []
            }, {
                name: 'Purchase PO Spend',
                data: monthlyTrendData['purchase'] || []
            }],
            chart: {
                type: 'area',
                height: 290,
                toolbar: { show: false }
            },
            colors: ['#3b82f6', '#f59e0b'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: {
                categories: monthlyTrendData['labels'] || []
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return '₹' + val.toLocaleString('en-IN');
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return '₹' + val.toLocaleString('en-IN', {minimumFractionDigits: 2});
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            }
        };

        var chart = new ApexCharts(document.querySelector("#supplyChainTrendChart"), options);
        chart.render();

        // Handle Dynamic Metric Switcher Dropdown
        var metricSelect = document.getElementById('chartMetricSelector');
        if (metricSelect) {
            metricSelect.addEventListener('change', function() {
                var selectedMetric = this.value;
                if (monthlyTrendData[selectedMetric]) {
                    chart.updateSeries([{
                        name: metricNames[selectedMetric],
                        data: monthlyTrendData[selectedMetric]
                    }, {
                        name: 'Purchase PO Spend',
                        data: monthlyTrendData['purchase']
                    }]);
                }
            });
        }
    });
</script>
@endpush
