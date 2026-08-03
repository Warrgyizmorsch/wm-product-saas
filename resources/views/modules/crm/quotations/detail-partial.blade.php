{{-- Quotation Detail Partial — loaded into offcanvas drawer via AJAX --}}
@php
    $badgeClass = 'bg-soft-secondary text-secondary';
    if ($quotation->status === 'Sent' || $quotation->status === 'Quotation Sent') $badgeClass = 'bg-soft-info text-info';
    elseif ($quotation->status === 'Accepted' || $quotation->status === 'Approved') $badgeClass = 'bg-soft-success text-success';
    elseif ($quotation->status === 'Declined' || $quotation->status === 'Rejected') $badgeClass = 'bg-soft-danger text-danger';
    elseif ($quotation->status === 'Pending Approval') $badgeClass = 'bg-soft-warning text-warning';
    elseif ($quotation->status === 'Quotation Rework' || $quotation->status === 'Rework') $badgeClass = 'bg-soft-warning text-warning';
@endphp

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="fw-bold text-dark mb-1">{{ $quotation->quotation_number }}</h5>
        <span class="badge {{ $badgeClass }} px-2 py-1 fs-11">{{ $quotation->status }}</span>
    </div>
    <div class="text-end">
        <div class="fs-12 text-muted mb-1">Total Amount</div>
        <h4 class="fw-bold text-primary mb-0">₹{{ number_format($quotation->total_amount, 2) }}</h4>
    </div>
</div>

<div class="card border-0 bg-light rounded-3 mb-4">
    <div class="card-body p-3">
        <div class="row g-2 fs-12">
            <div class="col-5 text-muted">Customer</div>
            <div class="col-7 fw-semibold text-dark">{{ $quotation->customer?->name ?? ($quotation->lead?->company_name ?? '—') }}</div>

            <div class="col-5 text-muted">Date</div>
            <div class="col-7 fw-semibold">{{ $quotation->quotation_date ? $quotation->quotation_date->format('d/m/Y') : '—' }}</div>

            <div class="col-5 text-muted">Valid Until</div>
            <div class="col-7 fw-semibold">{{ $quotation->expiry_date ? $quotation->expiry_date->format('d/m/Y') : '—' }}</div>

            @if($quotation->salesPerson)
                <div class="col-5 text-muted">Sales Rep</div>
                <div class="col-7 fw-semibold">{{ $quotation->salesPerson->name }}</div>
            @endif
        </div>
    </div>
</div>

<div class="mb-3 d-flex align-items-center justify-content-between">
    <h6 class="fw-bold text-dark mb-0 fs-13">Line Items</h6>
</div>

<style>
    .quotation-drawer-table {
        table-layout: fixed;
        width: 100% !important;
    }
    .quotation-drawer-table th,
    .quotation-drawer-table td {
        padding: 6px 6px !important;
        font-size: 11px !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
</style>

<div class="border rounded-3 mb-4 overflow-hidden">
    <table class="table table-bordered align-middle mb-0 quotation-drawer-table">
        <thead class="table-light text-uppercase text-muted fs-10 letter-spacing-1">
            <tr>
                <th style="width: 38%;">Product</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-end" style="width: 20%;">Rate</th>
                <th class="text-end" style="width: 14%;">Tax</th>
                <th class="text-end" style="width: 18%;">Total</th>
            </tr>
        </thead>
        <tbody class="bg-white">
            @php 
                $calcSubtotal = 0; 
                $calcTaxTotal = 0;
            @endphp
            @foreach($quotation->items as $item)
                @php
                    $lineSubtotal = $item->quantity * $item->unit_price;
                    $taxRate = (float)($item->tax_rate ?? 0);
                    $lineTax = $item->tax_amount ?? ($lineSubtotal * ($taxRate / 100));
                    $lineTotal = $item->amount ?? ($lineSubtotal + $lineTax);
                    
                    $calcSubtotal += $lineSubtotal;
                    $calcTaxTotal += $lineTax;
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold text-dark text-truncate">{{ $item->item_name ?? $item->product?->name ?? $item->description ?? '—' }}</div>
                        @if($item->product && $item->product->sku)
                            <div class="text-muted fs-10 text-truncate">SKU: {{ $item->product->sku }}</div>
                        @endif
                    </td>
                    <td class="text-center fw-semibold">{{ (float)$item->quantity }}</td>
                    <td class="text-end">₹{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-end text-muted">{{ $taxRate > 0 ? number_format($taxRate, 1) . '%' : '0%' }}</td>
                    <td class="text-end fw-bold">₹{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light fs-11 border-top">
            @php
                $subtotalVal = $quotation->subtotal ?: $calcSubtotal;
                $taxVal = ($quotation->tax_amount ?: $quotation->tax) ?: $calcTaxTotal;
                if (!$taxVal && $quotation->total_amount) {
                    $taxVal = max(0, $quotation->total_amount - $subtotalVal + ($quotation->discount ?: 0));
                }
            @endphp
            <tr>
                <td colspan="4" class="text-end text-muted fw-semibold py-1.5">Subtotal:</td>
                <td class="text-end fw-bold text-dark py-1.5">₹{{ number_format($subtotalVal, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-end text-muted fw-semibold py-1.5">Tax:</td>
                <td class="text-end fw-bold text-success py-1.5">+₹{{ number_format($taxVal, 2) }}</td>
            </tr>
            @if(($quotation->discount ?: 0) > 0)
                <tr>
                    <td colspan="4" class="text-end text-muted fw-semibold py-1.5">Discount:</td>
                    <td class="text-end fw-bold text-danger py-1.5">-₹{{ number_format($quotation->discount, 2) }}</td>
                </tr>
            @endif
            <tr class="table-primary border-top border-2">
                <td colspan="4" class="text-end text-uppercase fs-10 fw-bold text-dark letter-spacing-1 py-2">Grand Total:</td>
                <td class="text-end text-primary fs-13 fw-extrabold py-2">₹{{ number_format($quotation->total_amount ?: ($subtotalVal + $taxVal - ($quotation->discount ?: 0)), 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@if($quotation->terms_conditions || $quotation->notes)
    <div class="card border-0 bg-light rounded-3 mt-3">
        <div class="card-body py-3 px-3">
            @if($quotation->notes)
                <h6 class="fw-bold text-uppercase fs-10 text-muted letter-spacing-1 mb-2">
                    <i class="feather-file-text me-1 text-primary"></i> Notes
                </h6>
                <p class="fs-12 text-dark mb-3">{!! nl2br(e($quotation->notes)) !!}</p>
            @endif

            @if($quotation->terms_conditions)
                <h6 class="fw-bold text-uppercase fs-10 text-muted letter-spacing-1 mb-2">
                    <i class="feather-info me-1 text-primary"></i> Terms & Conditions
                </h6>
                <p class="fs-12 text-dark mb-0">{!! nl2br(e($quotation->terms_conditions)) !!}</p>
            @endif
        </div>
    </div>
@endif

