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

<div class="table-responsive border rounded-3 mb-4">
    <table class="table table-bordered align-middle fs-12 mb-0 w-100">
        <thead class="table-light text-uppercase text-muted fs-10 letter-spacing-1">
            <tr>
                <th>Product</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Rate</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody class="bg-white">
            @php $grandTotal = 0; @endphp
            @foreach($quotation->items as $item)
                @php
                    $lineTotal = $item->quantity * $item->unit_price;
                    $grandTotal += $lineTotal;
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold text-dark">{{ $item->product->name ?? $item->description ?? '—' }}</div>
                        @if($item->product && $item->product->sku)
                            <div class="text-muted fs-10">SKU: {{ $item->product->sku }}</div>
                        @endif
                    </td>
                    <td class="text-center fw-semibold">{{ (float)$item->quantity }}</td>
                    <td class="text-end">₹{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-end fw-bold">₹{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="3" class="text-end text-uppercase fs-10 text-muted letter-spacing-1">Grand Total</td>
                <td class="text-end text-primary fs-14">₹{{ number_format($quotation->total_amount ?? $grandTotal, 2) }}</td>
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


