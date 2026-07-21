@extends('layouts.duralux')

@section('title', 'RFQ Purchase Savings Dashboard | SaaS ERP')
@section('page-title', 'RFQ Purchase Savings Dashboard')
@section('breadcrumb')
    <a href="{{ route('purchase.rfqs.index') }}">RFQs</a> &gt; Purchase Savings Dashboard
@endsection

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
        $currencySymbol = ($currency === 'INR') ? '₹' : $currency . ' ';
    @endphp

    <div class="row text-dark">
        <div class="col-12">

            <!-- Page Header Card -->
            <div class="card border-0 shadow-sm mb-4 bg-white">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                            <i class="feather-trending-up text-primary fs-22"></i> RFQ Purchase Savings Dashboard
                        </h4>
                        <p class="text-muted fs-13 mb-0">Track cost savings achieved through competitive RFQ-based Purchase Orders.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary border btn-sm px-3" onclick="window.print()">
                            <i class="feather-printer me-1"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Top 6 KPI Summary Cards -->
            <div class="row g-3 mb-4">
                <!-- 1. Total Savings -->
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bg-white" style="border-left: 4px solid #10b981 !important;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted fs-11 fw-bold text-uppercase" style="letter-spacing:0.5px;">Total Savings</span>
                                <div class="avatar-text bg-soft-success text-success rounded-circle" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                    <i class="feather-dollar-sign fs-14"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold text-success mb-1 fs-18">{{ $currencySymbol }}{{ number_format($totalSavings, 2) }}</h4>
                            <span class="fs-11 text-muted">Cost Saved via RFQ</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Total Purchase Value -->
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bg-white" style="border-left: 4px solid #3b82f6 !important;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted fs-11 fw-bold text-uppercase" style="letter-spacing:0.5px;">Purchase Value</span>
                                <div class="avatar-text bg-soft-primary text-primary rounded-circle" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                    <i class="feather-shopping-bag fs-14"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold text-dark mb-1 fs-18">{{ $currencySymbol }}{{ number_format($totalSpend, 2) }}</h4>
                            <span class="fs-11 text-muted">Total RFQ PO Spend</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Total RFQ POs -->
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bg-white" style="border-left: 4px solid #8b5cf6 !important;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted fs-11 fw-bold text-uppercase" style="letter-spacing:0.5px;">RFQ Orders</span>
                                <div class="avatar-text bg-soft-purple text-purple rounded-circle" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                    <i class="feather-layers fs-14"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold text-dark mb-1 fs-18">{{ count($processedOrders) }}</h4>
                            <span class="fs-11 text-muted">PO Count from RFQ</span>
                        </div>
                    </div>
                </div>

                <!-- 4. Average Saving % -->
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bg-white" style="border-left: 4px solid #f59e0b !important;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted fs-11 fw-bold text-uppercase" style="letter-spacing:0.5px;">Avg Saving %</span>
                                <div class="avatar-text bg-soft-warning text-warning rounded-circle" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                    <i class="feather-percent fs-14"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold text-warning mb-1 fs-18">{{ number_format($avgSavingPercent, 2) }}%</h4>
                            <span class="fs-11 text-muted">Average Net Saving</span>
                        </div>
                    </div>
                </div>

                <!-- 5. Best Purchaser -->
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bg-white" style="border-left: 4px solid #ec4899 !important;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted fs-11 fw-bold text-uppercase" style="letter-spacing:0.5px;">Best Purchaser</span>
                                <div class="avatar-text bg-soft-pink text-pink rounded-circle" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                    <i class="feather-user fs-14"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold text-dark mb-1 fs-15 text-truncate" title="{{ $bestPurchaserName }}">{{ $bestPurchaserName }}</h4>
                            <span class="fs-11 text-muted">Top Negotiator</span>
                        </div>
                    </div>
                </div>

                <!-- 6. Highest Single PO Saving -->
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bg-white" style="border-left: 4px solid #06b6d4 !important;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted fs-11 fw-bold text-uppercase" style="letter-spacing:0.5px;">Max PO Saving</span>
                                <div class="avatar-text bg-soft-info text-info rounded-circle" style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                    <i class="feather-award fs-14"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold text-info mb-1 fs-18">{{ $currencySymbol }}{{ number_format($highestSingleSavings, 2) }}</h4>
                            <span class="fs-11 text-muted">Single PO Peak</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="card border-0 shadow-sm mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-filter me-2 text-primary"></i>Filters & Parameters</h6>
                    <a href="{{ route('purchase.rfqs.savings') }}" class="btn btn-xs btn-light border">Reset Filters</a>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('purchase.rfqs.savings') }}" method="GET" class="row g-3 fs-12">
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label fw-bold text-muted mb-1 fs-11">From Date</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label fw-bold text-muted mb-1 fs-11">To Date</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                        </div>

                        @if($isAdmin)
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label fw-bold text-muted mb-1 fs-11">Purchaser (Buyer)</label>
                                <select name="purchaser_id" class="form-select form-select-sm">
                                    <option value="">All Purchasers</option>
                                    @foreach($allPurchasers as $u)
                                        <option value="{{ $u->id }}" @selected(request('purchaser_id') == $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label fw-bold text-muted mb-1 fs-11">Vendor</label>
                            <select name="vendor_id" class="form-select form-select-sm">
                                <option value="">All Vendors</option>
                                @foreach($allVendors as $v)
                                    <option value="{{ $v->id }}" @selected(request('vendor_id') == $v->id)>{{ $v->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label fw-bold text-muted mb-1 fs-11">PO No.</label>
                            <input type="text" name="po_number" class="form-control form-control-sm" placeholder="e.g. PO-2026-..." value="{{ request('po_number') }}">
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label fw-bold text-muted mb-1 fs-11">RFQ No.</label>
                            <input type="text" name="rfq_number" class="form-control form-control-sm" placeholder="e.g. RFQ-2026-..." value="{{ request('rfq_number') }}">
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-2 pt-2 border-top">
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">
                                <i class="feather-search me-1"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Visual Analytics Grid -->
            <div class="row g-4 mb-4">
                <!-- Monthly Savings Trend Graph -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100 bg-white">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-bar-chart-2 text-primary me-2"></i>Monthly Savings Trend</h6>
                            <span class="badge bg-soft-primary text-primary fs-10 fw-bold">Year {{ date('Y') }}</span>
                        </div>
                        <div class="card-body p-4">
                            @php
                                $monthsList = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                $maxMonthVal = max($monthlySavings) ?: 1;
                            @endphp
                            <div class="d-flex align-items-end justify-content-between gap-2 pt-3" style="height: 220px;">
                                @foreach($monthsList as $idx => $mName)
                                    @php
                                        $val = $monthlySavings[$idx + 1] ?? 0;
                                        $pct = round(($val / $maxMonthVal) * 100);
                                        if ($pct < 5 && $val > 0) $pct = 5;
                                    @endphp
                                    <div class="d-flex flex-column align-items-center flex-grow-1 h-100 justify-content-end">
                                        <small class="fs-10 text-success fw-bold mb-1" style="font-size:9px;">
                                            @if($val > 0) {{ $currencySymbol }}{{ $val > 1000 ? round($val/1000, 1).'K' : number_format($val) }} @endif
                                        </small>
                                        <div class="w-100 rounded-top" style="height: {{ $pct }}%; background: linear-gradient(180deg, #10b981 0%, #059669 100%); transition: height 0.4s ease;" title="{{ $mName }}: {{ $currencySymbol }}{{ number_format($val, 2) }}"></div>
                                        <span class="fs-11 text-muted fw-semibold mt-2">{{ $mName }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department-Wise Savings -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100 bg-white">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-briefcase text-primary me-2"></i>Department-Wise Savings</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                                <table class="table table-hover align-middle fs-12 mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Department</th>
                                            <th class="text-end">Total Spend</th>
                                            <th class="text-end">Savings ({{ $currencySymbol }})</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($deptSavings as $d)
                                            <tr>
                                                <td class="fw-semibold text-dark">{{ $d['department'] }}</td>
                                                <td class="text-end text-muted">{{ $currencySymbol }}{{ number_format($d['total_spend'], 2) }}</td>
                                                <td class="text-end fw-bold text-success">{{ $currencySymbol }}{{ number_format($d['total_savings'], 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-3">No department data found</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Purchaser Leaderboard (Admin Only) -->
            @if($isAdmin)
                <div class="card border-0 shadow-sm mb-4 bg-white">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-award text-warning me-2"></i>Purchaser Performance Leaderboard</h6>
                        <span class="badge bg-soft-warning text-warning fs-10 fw-bold">Top Negotiators</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle fs-12 mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;" class="text-center">Rank</th>
                                        <th>Purchaser Name</th>
                                        <th class="text-center">Total POs</th>
                                        <th class="text-end">Purchase Amount</th>
                                        <th class="text-end">Total Savings</th>
                                        <th class="text-end">Avg Saving %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $rank = 1; @endphp
                                    @forelse($purchaserSavings as $p)
                                        @php
                                            $medal = match($rank) {
                                                1 => '<span class="badge bg-primary text-white font-monospace px-2 py-1"><i class="feather-award me-1"></i>Rank 1</span>',
                                                2 => '<span class="badge bg-info text-white font-monospace px-2 py-1"><i class="feather-award me-1"></i>Rank 2</span>',
                                                3 => '<span class="badge bg-secondary text-white font-monospace px-2 py-1"><i class="feather-award me-1"></i>Rank 3</span>',
                                                default => "<span class=\"text-muted font-monospace fw-bold\">#{$rank}</span>"
                                            };
                                            $pSavingPercent = ($p['total_spend'] + $p['total_savings']) > 0 ? ($p['total_savings'] / ($p['total_spend'] + $p['total_savings'])) * 100 : 0;
                                        @endphp
                                        <tr>
                                            <td class="text-center">{!! $medal !!}</td>
                                            <td class="fw-bold text-dark">{{ $p['name'] }}</td>
                                            <td class="text-center font-monospace">{{ $p['po_count'] }}</td>
                                            <td class="text-end text-muted font-monospace">{{ $currencySymbol }}{{ number_format($p['total_spend'], 2) }}</td>
                                            <td class="text-end fw-bold text-success font-monospace">{{ $currencySymbol }}{{ number_format($p['total_savings'], 2) }}</td>
                                            <td class="text-end font-monospace">
                                                <span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-bold">{{ number_format($pSavingPercent, 2) }}%</span>
                                            </td>
                                        </tr>
                                        @php $rank++; @endphp
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">No purchaser data available</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Vendor Savings Performance -->
            <div class="card border-0 shadow-sm mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-users text-primary me-2"></i>Vendor Competitive Performance</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle fs-12 mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Vendor Name</th>
                                    <th class="text-center">RFQs Won</th>
                                    <th class="text-end">Total PO Value</th>
                                    <th class="text-end">Savings Given (vs High Quote)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($vendorStats as $v)
                                    <tr>
                                        <td class="fw-bold text-dark">{{ $v['name'] }}</td>
                                        <td class="text-center font-monospace fw-bold">{{ $v['rfqs_won'] }}</td>
                                        <td class="text-end text-muted font-monospace">{{ $currencySymbol }}{{ number_format($v['total_spend'], 2) }}</td>
                                        <td class="text-end fw-bold text-success font-monospace">{{ $currencySymbol }}{{ number_format($v['total_savings'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No vendor performance data found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Detailed PO Savings Data Grid -->
            <div class="card border-0 shadow-sm mb-4 bg-white">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-list text-primary me-2"></i>Detailed PO Savings Breakdown</h6>
                    <span class="text-muted fs-11">Click details to view item-level rate comparison</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle fs-12 mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>PO No.</th>
                                    <th>RFQ No.</th>
                                    <th>Quote No.</th>
                                    <th>Purchaser</th>
                                    <th>Vendor</th>
                                    <th class="text-end">Highest Quote</th>
                                    <th class="text-end">Selected PO Amt</th>
                                    <th class="text-end">Savings ({{ $currencySymbol }})</th>
                                    <th class="text-end">Saving %</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 140px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($processedOrders as $row)
                                    <tr>
                                        <td class="fw-bold text-primary">
                                            <a href="{{ route('purchase.orders.show', $row['id']) }}" class="text-primary text-decoration-underline">{{ $row['po_number'] }}</a>
                                        </td>
                                        <td class="font-monospace text-muted">{{ $row['rfq_number'] }}</td>
                                        <td class="font-monospace text-dark fw-semibold">{{ $row['supplier_quotation_number'] }}</td>
                                        <td class="fw-semibold text-dark">{{ $row['purchaser_name'] }}</td>
                                        <td class="text-dark">{{ $row['vendor_name'] }}</td>
                                        <td class="text-end font-monospace text-muted">{{ $currencySymbol }}{{ number_format($row['highest_quote_amount'], 2) }}</td>
                                        <td class="text-end font-monospace fw-bold text-dark">{{ $currencySymbol }}{{ number_format($row['po_amount'], 2) }}</td>
                                        <td class="text-end font-monospace fw-bold text-success">+{{ $currencySymbol }}{{ number_format($row['savings_amount'], 2) }}</td>
                                        <td class="text-end font-monospace">
                                            <span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-bold">{{ $row['savings_percent'] }}%</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-success text-success fs-10 px-2 py-0.5">{{ $row['status'] }}</span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-2 btn-show-po-savings" data-order-id="{{ $row['id'] }}">
                                                <i class="feather-eye me-1"></i> Details
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">No RFQ Purchase Orders found matching filters</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- PO Savings Item-Level Details Modal -->
    <x-ui.modal id="poSavingsModal" title="PO Item Savings Details" size="lg" :centered="true" :showFooter="false">
        <div id="po-savings-modal-content" class="fs-13">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="text-muted fs-12 mt-2">Fetching item savings calculation...</div>
            </div>
        </div>
    </x-ui.modal>

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('.btn-show-po-savings').on('click', function() {
                const orderId = $(this).attr('data-order-id');
                const modal = new bootstrap.Modal(document.getElementById('poSavingsModal'));
                const contentContainer = $('#po-savings-modal-content');

                contentContainer.html(`
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                        <div class="text-muted fs-12 mt-2">Fetching item savings calculation...</div>
                    </div>
                `);

                modal.show();

                $.ajax({
                    url: "{{ url('purchase/rfqs/savings-details') }}/" + orderId,
                    type: 'GET',
                    success: function(res) {
                        if (!res.success) {
                            contentContainer.html('<div class="alert alert-danger mb-0">Failed to load PO details.</div>');
                            return;
                        }

                        let currencySymbol = "{{ $currencySymbol }}";
                        let itemsHtml = '';
                        res.items.forEach(function(item, idx) {
                            itemsHtml += `
                                <tr>
                                    <td class="text-muted">${idx + 1}</td>
                                    <td class="fw-semibold text-dark">
                                        ${item.product_name}
                                        <small class="d-block text-muted font-monospace fs-10">SKU: ${item.sku}</small>
                                    </td>
                                    <td class="text-end font-monospace">${currencySymbol}${item.highest_rate.toFixed(2)}</td>
                                    <td class="text-end font-monospace fw-bold text-primary">${currencySymbol}${item.selected_rate.toFixed(2)}</td>
                                    <td class="text-center font-monospace fw-bold">${item.quantity}</td>
                                    <td class="text-end font-monospace fw-bold text-success">+${currencySymbol}${item.savings.toFixed(2)}</td>
                                </tr>
                            `;
                        });

                        contentContainer.html(`
                            <div class="row g-3 mb-3 p-3 bg-light rounded border text-dark">
                                <div class="col-md-4">
                                    <div class="text-muted fs-11 text-uppercase fw-bold">Purchase Order</div>
                                    <div class="fw-bold text-primary fs-14">${res.po_number}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted fs-11 text-uppercase fw-bold">RFQ Reference</div>
                                    <div class="fw-bold text-dark fs-13">${res.rfq_number}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted fs-11 text-uppercase fw-bold">Supplier Quote No.</div>
                                    <div class="fw-bold text-dark fs-13">${res.supplier_quotation_number}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted fs-11 text-uppercase fw-bold">Purchaser / Buyer</div>
                                    <div class="fw-semibold text-dark">${res.purchaser_name}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted fs-11 text-uppercase fw-bold">Vendor Name</div>
                                    <div class="fw-semibold text-dark">${res.vendor_name}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted fs-11 text-uppercase fw-bold">Net Savings</div>
                                    <div class="fw-bold text-success fs-14">+${currencySymbol}${res.net_savings.toFixed(2)} (${res.savings_percent}%)</div>
                                </div>
                            </div>

                            <h6 class="fw-bold text-primary mb-2 fs-12 text-uppercase">Item-Level Rate Comparison</h6>
                            <div class="table-responsive rounded border mb-3">
                                <table class="table table-sm align-middle fs-12 mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Item Description</th>
                                            <th class="text-end">Highest Quote Rate</th>
                                            <th class="text-end">Selected PO Rate</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Item Savings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${itemsHtml}
                                    </tbody>
                                </table>
                            </div>
                        `);
                    },
                    error: function() {
                        contentContainer.html('<div class="alert alert-danger mb-0">Error fetching PO savings details.</div>');
                    }
                });
            });
        });
    </script>
@endpush
