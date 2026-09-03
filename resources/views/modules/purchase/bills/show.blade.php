@extends('layouts.duralux')

@section('title', __('purchase.bill') . " {$bill->bill_number} | SaaS ERP")
@section('page-title', __('purchase.vendor_bill_details'))
@section('breadcrumb')
    <a href="{{ route('purchase.bills.index') }}">{{ __('purchase.vendor_bills') }}</a> &gt; {{ $bill->bill_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap text-dark">
        <a href="{{ route('purchase.bills.index') }}" class="btn btn-light border fs-12">
            <i class="feather-arrow-left me-2"></i>{{ __('purchase.back_to_bills') }}
        </a>

        @if($bill->due_amount > 0)
            @php
                $availAdvAction = max($availableAdvance ?? 0, $bill->goodsReceiptNote?->purchaseOrder?->total_advance_paid ?? 0);
            @endphp
            @if($availAdvAction > 0)
                <form action="{{ route('purchase.bills.apply-advance', $bill->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary text-white fs-12 fw-bold shadow-sm me-1" style="background-color: #714B67; border-color: #714B67;">
                        <i class="feather-check-circle me-1.5"></i>Apply Vendor Advance Credit
                    </button>
                </form>
            @endif
            <a href="{{ route('purchase.payments.create', ['bill_id' => $bill->id]) }}" class="btn btn-success text-white fs-12 fw-bold shadow-sm">
                <i class="feather-credit-card me-2"></i>{{ __('purchase.register_vendor_payment') }}
            </a>
        @endif
    </div>
@endsection

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Status Bar -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom">
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">{{ __('purchase.vendor_bill') }}</span>
                <h4 class="fw-bold text-dark mb-1">{{ $bill->bill_number }}</h4>
                <span class="fs-13 text-muted">
                    {{ __('purchase.supplier_vendor') }}:&nbsp;<strong class="text-dark">{{ $bill->vendor?->name }}</strong>
                    @if($bill->goodsReceiptNote)
                        &nbsp;·&nbsp;GRN:&nbsp;<a href="{{ route('grns.show', $bill->goodsReceiptNote->id) }}" class="fw-semibold text-primary">{{ $bill->goodsReceiptNote->grn_number }}</a>
                    @endif
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                @php
                    $statusText = match($bill->status) {
                        'Paid' => 'PAID',
                        'Partially Paid' => 'PARTIALLY PAID',
                        'Unpaid' => 'UNPAID',
                        'Posted' => 'POSTED',
                        'Cancelled' => 'CANCELLED',
                        default => strtoupper($bill->status),
                    };
                    $badgeClass = match($bill->status) {
                        'Paid' => 'success',
                        'Partially Paid' => 'info',
                        'Unpaid' => 'danger',
                        'Posted', 'Draft' => 'warning',
                        'Cancelled' => 'secondary',
                        default => 'secondary',
                    };
                @endphp
                <span class="badge bg-soft-{{ $badgeClass }} text-{{ $badgeClass }} px-3 py-1.5 fs-13 fw-bold">
                    {{ $statusText }}
                </span>
            </div>
        </div>

        @php
            $availAdv = max($availableAdvance ?? 0, $bill->goodsReceiptNote?->purchaseOrder?->total_advance_paid ?? 0);
            $isFreightEligible = ($bill->freight_terms === 'to_pay' || $bill->freight_terms === 'to_be_billed');
            $isProRata = ($bill->tax_type === 'item_wise_tax' && $isFreightEligible && $bill->freight_tax_method === 'pro_rata' && $bill->freight_amount > 0);
        @endphp

        @if($availAdv > 0)
            <div class="alert alert-info border-info p-3 mb-4 rounded shadow-sm">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <strong class="text-dark fs-13"><i class="feather-info text-info me-1.5"></i>Vendor Advance Credit Available:</strong>
                        <span class="text-success fw-bold font-monospace fs-14 ms-1">₹{{ number_format($availAdv, 2) }}</span>
                        <small class="text-muted d-block fs-11 mt-0.5">This supplier has available advance credit that can be applied to settle this bill.</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($bill->due_amount > 0)
                            <form action="{{ route('purchase.bills.apply-advance', $bill->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm fw-bold shadow-sm" style="background-color: #714B67; border-color: #714B67;">
                                    <i class="feather-check-circle me-1"></i>Apply Vendor Advance Credit
                                </button>
                            </form>
                        @endif
                        <span class="badge bg-primary text-white p-2 fs-12">{{ __('purchase.net_payable_from_bank') }}: ₹{{ number_format(max(0, $bill->due_amount - $availAdv), 2) }}</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- Metadata Row -->
        <div class="row g-3 mb-4 fs-13 text-dark border-bottom pb-3">
            <div class="col-md-2">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.vendor_invoice_no') }}</span>
                <strong class="font-monospace fs-14">{{ $bill->vendor_invoice_number ?: '—' }}</strong>
            </div>
            <div class="col-md-2">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.bill_date') }}</span>
                <strong>{{ $bill->bill_date ? $bill->bill_date->format('d-M-Y') : '—' }}</strong>
            </div>
            <div class="col-md-2">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">Discount / Tax Options</span>
                <strong class="text-capitalize d-block">{{ str_replace('_', ' ', $bill->discount_type ?: 'without_discount') }}</strong>
                <small class="text-muted text-capitalize d-block">{{ str_replace('_', ' ', $bill->tax_type ?: 'order_wise_tax') }}</small>
            </div>
            <div class="col-md-2">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">GST Type</span>
                <strong class="text-capitalize">{{ $bill->gst_type === 'igst' ? 'Inter-State (IGST)' : 'Intra-State (CGST+SGST)' }}</strong>
            </div>
            <div class="col-md-2">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">Freight Terms &amp; Method</span>
                <strong class="text-capitalize d-block">{{ str_replace('_', ' ', $bill->freight_terms ?: 'To Pay') }}</strong>
                @if($bill->freight_amount > 0)
                    <span class="text-primary fw-bold font-monospace d-inline-block">₹{{ number_format($bill->freight_amount, 2) }}</span>
                    <span class="badge bg-soft-primary text-primary fs-11 ms-1">
                        {{ $bill->freight_tax_method === 'pro_rata' ? 'Pro-Rata Apportionment' : ($bill->freight_tax_method === 'manual' ? 'Custom Tax Rate' : 'Highest GST Rate') }}
                    </span>
                @endif
            </div>
            <div class="col-md-2 text-md-end">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.grand_total') }}</span>
                <strong class="fs-16 font-monospace text-primary">₹{{ number_format($bill->grand_total, 2) }}</strong>
            </div>
        </div>

        @php
            // Calculate total gross taxable value for Pro-Rata apportionment
            $grossTaxableValue = 0;
            foreach ($bill->items as $bItem) {
                $grossTaxableValue += ((float)$bItem->quantity * (float)$bItem->unit_rate);
            }
            if ($bill->discount_amount > 0 && $bill->discount_type === 'order_wise') {
                $grossTaxableValue = max(0.01, $grossTaxableValue - (float)$bill->discount_amount);
            }
        @endphp

        <!-- Billed Items Table -->
        <h6 class="fw-bold text-dark mb-2">{{ __('purchase.billed_items') }}</h6>
        <div class="table-responsive rounded border mb-4">
            <table class="table table-bordered table-sm align-middle fs-13 text-dark mb-0">
                <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                    <tr>
                        <th class="ps-3" style="width: 5%;">#</th>
                        <th style="width: 32%;">{{ __('purchase.product') }}</th>
                        <th class="text-center" style="width: 10%;">{{ __('purchase.billed_qty') }}</th>
                        <th class="text-end" style="width: 12%;">{{ __('purchase.unit_rate') }}</th>
                        <th class="text-end" style="width: 12%;">Amount (₹)</th>

                        @if($isProRata)
                            <th class="text-end text-primary" style="width: 12%;">Freight Share (₹)</th>
                        @endif

                        <th class="text-center" style="width: 9%;">Tax Rate</th>
                        <th class="text-end" style="width: 11%;">Tax Amount</th>
                        <th class="text-end pe-3" style="width: 13%;">{{ __('purchase.total_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bill->items as $idx => $item)
                        @php
                            $qty = (float)$item->quantity;
                            $rate = (float)$item->unit_rate;
                            $lineSub = $qty * $rate;

                            $itemFreightShare = 0;
                            if ($isProRata && $grossTaxableValue > 0) {
                                $ratio = $lineSub / $grossTaxableValue;
                                $itemFreightShare = (float)$bill->freight_amount * $ratio;
                            }

                            $lineTaxableBase = $lineSub + $itemFreightShare;
                            $taxRate = (float)($item->tax_percentage ?? 0);
                            $lineTax = $lineTaxableBase * ($taxRate / 100);
                            $lineTotal = (float)$item->total_amount;
                            if ($lineTotal <= 0) {
                                $lineTotal = $lineTaxableBase + $lineTax;
                            }
                        @endphp
                        <tr>
                            <td class="ps-3 text-muted fw-semibold">{{ $idx + 1 }}</td>
                            <td>
                                <strong class="text-dark">{{ $item->product?->name }}</strong>
                                @if($item->product?->sku)
                                    <small class="text-muted d-block">{{ __('purchase.sku') }}: {{ $item->product->sku }}</small>
                                @endif
                            </td>
                            <td class="text-center font-monospace">{{ $qty }}</td>
                            <td class="text-end font-monospace">₹{{ number_format($rate, 2) }}</td>
                            <td class="text-end font-monospace">₹{{ number_format($lineSub, 2) }}</td>

                            @if($isProRata)
                                <td class="text-end font-monospace text-primary fw-semibold">+₹{{ number_format($itemFreightShare, 2) }}</td>
                            @endif

                            <td class="text-center font-monospace">{{ $taxRate }}%</td>
                            <td class="text-end font-monospace text-muted">₹{{ number_format($lineTax, 2) }}</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-dark">₹{{ number_format($lineTotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Financial Summary Breakdown Card (Matching Absolute ERP Standards) -->
        <div class="row mb-4">
            <div class="col-md-6 offset-md-6">
                <div class="border rounded p-3 bg-light-50 fs-13 text-dark">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal (Excl. Tax):</span>
                        <strong class="font-monospace">₹{{ number_format($bill->subtotal, 2) }}</strong>
                    </div>

                    @if($bill->discount_amount > 0)
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Less: Discount:</span>
                            <strong class="font-monospace">-₹{{ number_format($bill->discount_amount, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 border-top pt-1">
                            <span class="text-muted">Items Taxable Value:</span>
                            <strong class="font-monospace">₹{{ number_format(max(0, $bill->subtotal - $bill->discount_amount), 2) }}</strong>
                        </div>
                    @endif

                    @if($bill->freight_amount > 0 && !$isProRata && $bill->tax_type !== 'order_wise_tax')
                        <div class="d-flex justify-content-between mb-2 text-primary">
                            <span>Add: Freight Charges:</span>
                            <strong class="font-monospace">+₹{{ number_format($bill->freight_amount, 2) }}</strong>
                        </div>
                    @elseif($bill->freight_amount > 0 && $isProRata)
                        <div class="d-flex justify-content-between mb-2 text-primary">
                            <span>Freight Charges (Apportioned Pro-Rata to Items):</span>
                            <strong class="font-monospace">+₹{{ number_format($bill->freight_amount, 2) }}</strong>
                        </div>
                    @elseif($bill->freight_amount > 0 && $bill->tax_type === 'order_wise_tax')
                        <div class="d-flex justify-content-between mb-2 text-primary">
                            <span>Add: Freight Charges:</span>
                            <strong class="font-monospace">+₹{{ number_format($bill->freight_amount, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 border-top pt-1 fw-bold">
                            <span>Total Taxable Base (Items + Freight):</span>
                            <strong class="font-monospace">₹{{ number_format(max(0, $bill->subtotal - $bill->discount_amount) + $bill->freight_amount, 2) }}</strong>
                        </div>
                    @endif

                    <hr class="my-2">

                    @php
                        $displayCgst = $bill->cgst_amount > 0 ? (float)$bill->cgst_amount : ($bill->gst_type !== 'igst' ? round((float)$bill->tax_amount / 2, 2) : 0);
                        $displaySgst = $bill->sgst_amount > 0 ? (float)$bill->sgst_amount : ($bill->gst_type !== 'igst' ? round((float)$bill->tax_amount / 2, 2) : 0);
                        $displayIgst = $bill->igst_amount > 0 ? (float)$bill->igst_amount : ($bill->gst_type === 'igst' ? round((float)$bill->tax_amount, 2) : 0);
                    @endphp

                    @if($bill->gst_type === 'igst')
                        <div class="d-flex justify-content-between mb-2 text-muted">
                            <span>IGST (Integrated Tax):</span>
                            <strong class="font-monospace">+₹{{ number_format($displayIgst, 2) }}</strong>
                        </div>
                    @else
                        <div class="d-flex justify-content-between mb-2 text-muted">
                            <span>CGST (Central Tax):</span>
                            <strong class="font-monospace">+₹{{ number_format($displayCgst, 2) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-muted">
                            <span>SGST (State Tax):</span>
                            <strong class="font-monospace">+₹{{ number_format($displaySgst, 2) }}</strong>
                        </div>
                    @endif

                    <hr class="my-2">

                    <div class="d-flex justify-content-between fw-bold fs-15 text-primary">
                        <span>Grand Total:</span>
                        <strong class="font-monospace">₹{{ number_format($bill->grand_total, 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Breakdown Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light-50">
                    <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('purchase.grand_total') }}</span>
                    <h4 class="fw-bold text-dark mb-0">₹{{ number_format($bill->grand_total, 2) }}</h4>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light-50">
                    <span class="fs-11 text-uppercase text-success fw-bold d-block mb-1">{{ __('purchase.paid_settled_amount') }}</span>
                    <h4 class="fw-bold text-success mb-0">₹{{ number_format($bill->paid_amount, 2) }}</h4>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light-50">
                    <span class="fs-11 text-uppercase text-danger fw-bold d-block mb-1">{{ __('purchase.net_balance_due') }}</span>
                    <h4 class="fw-bold text-danger mb-0">₹{{ number_format($bill->due_amount, 2) }}</h4>
                </div>
            </div>
        </div>

        <!-- Allocated Payments History -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold text-dark mb-0">{{ __('purchase.allocated_payments_ledger') }}</h6>
            @if($bill->due_amount > 0)
                <a href="{{ route('purchase.payments.create', ['bill_id' => $bill->id]) }}" class="btn btn-sm btn-success text-white fw-bold py-1 px-3 fs-12">
                    <i class="feather-plus me-1"></i>{{ __('purchase.register_payment') }}
                </a>
            @endif
        </div>

        @if($bill->allocations->count() > 0)
            <div class="table-responsive rounded border mb-4">
                <table class="table table-bordered table-sm align-middle fs-13 text-dark mb-0">
                    <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                        <tr>
                            <th class="ps-3">{{ __('purchase.payment_number') }}</th>
                            <th>{{ __('purchase.date') }}</th>
                            <th>{{ __('purchase.payment_method') }}</th>
                            <th>{{ __('purchase.reference_utr_no') }}</th>
                            <th class="text-end pe-3">{{ __('purchase.allocated_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bill->allocations as $alloc)
                            <tr>
                                <td class="ps-3 fw-bold text-primary">{{ $alloc->payment?->payment_number }}</td>
                                <td>{{ $alloc->payment?->payment_date ? $alloc->payment->payment_date->format('d-M-Y') : '—' }}</td>
                                <td><span class="badge bg-soft-info text-info fs-11 fw-semibold">{{ $alloc->payment?->payment_method }}</span></td>
                                <td class="font-monospace">{{ $alloc->payment?->reference_number ?: 'N/A' }}</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-success">₹{{ number_format($alloc->allocated_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-3 text-muted fs-12 border rounded mb-4">
                <i class="feather-info me-1"></i>{{ __('purchase.no_payments_registered_yet') }}
            </div>
        @endif

        <!-- Freight & Stock Valuation Revaluation Notes (Positioned at Bottom of Bill) -->
        @if($bill->freight_terms === 'to_pay')
            <div class="alert alert-warning border-warning p-3 mb-4 rounded shadow-sm">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-info text-warning fs-18"></i>
                    <div>
                        <strong class="text-dark fs-13">Freight Terms: To Pay (Freight Collect on Delivery)</strong>
                        <p class="mb-0 fs-12 text-muted">Freight charges were <strong>not</strong> added to this Material Vendor Invoice. Pay 3rd party Transporter via <strong>Landed Cost Voucher</strong> to update item stock valuation.</p>
                    </div>
                </div>
            </div>
        @endif

        @if(!empty($bill->landed_cost_revaluation_data) && !empty($bill->landed_cost_revaluation_data['revaluation_items']))
            <div class="alert alert-info border-info p-3 mb-4 rounded shadow-sm">
                <div class="d-flex align-items-start gap-2">
                    <i class="feather-info text-info fs-18 mt-0.5"></i>
                    <div class="w-100">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <strong class="text-dark fs-13"><i class="feather-box text-primary me-1"></i>Stock Valuation Note: Landed Cost Updated for Received Items</strong>
                            <span class="badge bg-primary text-white font-monospace fs-11 px-2.5 py-1">
                                Allocation: {{ ($bill->landed_cost_revaluation_data['allocation_method'] ?? '') === 'by_quantity' ? 'Equal Allocation per Unit Qty' : 'Proportional to Item Value (By Amount)' }}
                            </span>
                        </div>
                        <p class="mb-2 fs-12 text-muted">Base freight of <strong>₹{{ number_format($bill->landed_cost_revaluation_data['total_base_freight'] ?? $bill->freight_amount, 2) }}</strong> was allocated to GRN received items. Effective Landed Stock Rate updated in Stock Ledger:</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white fs-12 mb-0 shadow-xs rounded">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Product Item</th>
                                        <th class="text-center">SKU</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Base PO Cost</th>
                                        <th class="text-end">Allocated Freight</th>
                                        <th class="text-end">Freight / Unit</th>
                                        <th class="text-end text-primary">Effective Landed Cost / Unit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bill->landed_cost_revaluation_data['revaluation_items'] as $revItem)
                                        <tr>
                                            <td><strong class="text-dark">{{ $revItem['product_name'] }}</strong></td>
                                            <td class="text-center"><span class="font-monospace text-muted">{{ $revItem['sku'] }}</span></td>
                                            <td class="text-center">{{ $revItem['quantity'] }}</td>
                                            <td class="text-end font-monospace">₹{{ number_format($revItem['base_unit_cost'], 2) }}</td>
                                            <td class="text-end font-monospace text-success">+₹{{ number_format($revItem['freight_share'], 2) }}</td>
                                            <td class="text-end font-monospace text-success">+₹{{ number_format($revItem['freight_per_unit'], 2) }}</td>
                                            <td class="text-end font-monospace fw-bold text-primary">₹{{ number_format($revItem['new_landed_cost'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>

@endsection
