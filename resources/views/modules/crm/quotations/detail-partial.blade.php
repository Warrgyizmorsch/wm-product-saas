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
        <span class="badge {{ $badgeClass }} px-2 py-1 fs-11">
            @if($quotation->status === 'Draft') {{ __('crm.status_draft') }}
            @elseif($quotation->status === 'Pending Approval') {{ __('crm.status_pending_approval') }}
            @elseif($quotation->status === 'Approved') {{ __('crm.status_approved') }}
            @elseif($quotation->status === 'Sent') {{ __('crm.status_sent') }}
            @elseif($quotation->status === 'Quotation Sent') {{ __('crm.status_quotation_sent') }}
            @elseif($quotation->status === 'Accepted') {{ __('crm.status_accepted') }}
            @elseif($quotation->status === 'Rejected') {{ __('crm.status_rejected') }}
            @elseif($quotation->status === 'Quotation Rework') {{ __('crm.status_quotation_rework') }}
            @else {{ $quotation->status }}
            @endif
        </span>
    </div>
    <div class="text-end">
        <div class="fs-12 text-muted mb-1">{{ __('crm.total_amount') }}</div>
        <h4 class="fw-bold text-primary mb-0">{{ format_currency($quotation->total_amount) }}</h4>
    </div>
</div>

<div class="card border-0 bg-light rounded-3 mb-4">
    <div class="card-body p-3">
        <div class="row g-2 fs-12">
            <div class="col-5 text-muted">{{ __('crm.customer') }}</div>
            <div class="col-7 fw-semibold text-dark">{{ $quotation->prepared_for_name }}</div>

            <div class="col-5 text-muted">{{ __('crm.date') }}</div>
            <div class="col-7 fw-semibold">{{ $quotation->quotation_date ? $quotation->quotation_date->format('d/m/Y') : '—' }}</div>

            <div class="col-5 text-muted">{{ __('crm.valid_until') }}</div>
            <div class="col-7 fw-semibold">{{ $quotation->expiry_date ? $quotation->expiry_date->format('d/m/Y') : '—' }}</div>

            @if($quotation->salesPerson)
                <div class="col-5 text-muted">{{ __('crm.sales_rep') }}</div>
                <div class="col-7 fw-semibold">{{ $quotation->salesPerson->name }}</div>
            @endif
        </div>
    </div>
</div>

<div class="mb-3 d-flex align-items-center justify-content-between">
    <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('crm.line_items') }}</h6>
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
                <th style="width: 38%;">{{ __('crm.product') }}</th>
                <th class="text-center" style="width: 10%;">{{ __('crm.qty') }}</th>
                <th class="text-end" style="width: 20%;">{{ __('crm.rate') }}</th>
                <th class="text-end" style="width: 14%;">{{ __('crm.tax') }}</th>
                <th class="text-end" style="width: 18%;">{{ __('crm.total') }}</th>
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
                    <td class="text-end">{{ format_currency($item->unit_price) }}</td>
                    <td class="text-end text-muted">{{ $taxRate > 0 ? number_format($taxRate, 1) . '%' : '0%' }}</td>
                    <td class="text-end fw-bold">{{ format_currency($lineTotal) }}</td>
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
                <td colspan="4" class="text-end text-muted fw-semibold py-1.5">{{ __('crm.subtotal_colon') }}</td>
                <td class="text-end fw-bold text-dark py-1.5">{{ format_currency($subtotalVal) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-end text-muted fw-semibold py-1.5">{{ __('crm.tax_colon') }}</td>
                <td class="text-end fw-bold text-success py-1.5">+{{ format_currency($taxVal) }}</td>
            </tr>
            @if(($quotation->discount ?: 0) > 0)
                <tr>
                    <td colspan="4" class="text-end text-muted fw-semibold py-1.5">{{ __('crm.discount_colon') }}</td>
                    <td class="text-end fw-bold text-danger py-1.5">-{{ format_currency($quotation->discount) }}</td>
                </tr>
            @endif
            <tr class="table-primary border-top border-2">
                <td colspan="4" class="text-end text-uppercase fs-10 fw-bold text-dark letter-spacing-1 py-2">{{ __('crm.grand_total_colon') }}</td>
                <td class="text-end text-primary fs-13 fw-extrabold py-2">{{ format_currency($quotation->total_amount ?: ($subtotalVal + $taxVal - ($quotation->discount ?: 0))) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@if($quotation->terms_conditions || $quotation->notes)
    <div class="card border-0 bg-light rounded-3 mt-3">
        <div class="card-body py-3 px-3">
            @if($quotation->notes)
                <h6 class="fw-bold text-uppercase fs-10 text-muted letter-spacing-1 mb-2">
                    <i class="feather-file-text me-1 text-primary"></i> {{ __('crm.notes') }}
                </h6>
                <p class="fs-12 text-dark mb-3">{!! nl2br(e($quotation->notes)) !!}</p>
            @endif

            @if($quotation->terms_conditions)
                <h6 class="fw-bold text-uppercase fs-10 text-muted letter-spacing-1 mb-2">
                    <i class="feather-info me-1 text-primary"></i> {{ __('crm.terms_and_conditions') }}
                </h6>
                <p class="fs-12 text-dark mb-0">{!! nl2br(e($quotation->terms_conditions)) !!}</p>
            @endif
        </div>
    </div>
@endif

