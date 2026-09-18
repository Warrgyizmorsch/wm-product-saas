@extends('layouts.duralux')

@section('title', __('purchase.purchase_returns') . ' ' . $return->return_number . ' | SaaS ERP')
@section('page-title', __('purchase.purchase_return_details'))
@section('breadcrumb')
    <a href="{{ route('purchase.returns.index') }}">{{ __('purchase.purchase_returns') }}</a> &gt; {{ $return->return_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('purchase.returns.index') }}" class="btn btn-light border fs-12">
            <i class="feather-arrow-left me-1.5"></i>{{ __('purchase.back_to_returns') }}
        </a>

        @if (in_array($return->status, ['Pending', 'Draft']))
            <form action="{{ route('purchase.returns.approve', $return->id) }}" method="POST" id="approveReturnForm" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success text-white fs-12 fw-semibold">
                    <i class="feather-check-circle me-1.5"></i>{{ __('purchase.approve_and_remove_inventory') }}
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom">
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">{{ __('purchase.purchase_returns') }}</span>
                <h4 class="fw-bold text-dark mb-1">{{ $return->return_number }}</h4>
                <span class="fs-13 text-muted">
                    {{ __('purchase.supplier_vendor') }}:&nbsp;
                    @if($return->vendor)
                        <a href="{{ route('purchase.vendors.show', $return->vendor->id) }}" class="fw-semibold text-dark text-decoration-none">
                            {{ $return->vendor->name }}
                        </a>
                    @else
                        <strong class="text-dark">—</strong>
                    @endif
                </span>
            </div>

            <div>
                @php
                    $badgeClass = 'bg-soft-secondary text-secondary';
                    if ($return->status == 'Completed' || $return->status == 'Approved') $badgeClass = 'bg-soft-success text-success';
                    elseif ($return->status == 'Cancelled' || $return->status == 'Rejected') $badgeClass = 'bg-soft-danger text-danger';
                    elseif ($return->status == 'Pending' || $return->status == 'Draft') $badgeClass = 'bg-soft-warning text-warning';
                @endphp
                <span class="badge {{ $badgeClass }} px-3 py-1.5 fs-13 fw-bold">{{ $return->status }}</span>
            </div>
        </div>

        <div class="row g-3 mb-4 fs-13 text-dark">
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.return_number') }}</span>
                <strong class="font-monospace fs-13">{{ $return->return_number }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.return_date') }}</span>
                <strong>{{ $return->return_date ? date('d-M-Y', strtotime($return->return_date)) : '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.supplier_vendor') }}</span>
                <strong>{{ $return->vendor?->name ?: '—' }}</strong>
            </div>
            <div class="col-md-3 text-md-end">
                @php
                    $displayRefundTotal = $return->total_refund_amount > 0
                        ? $return->total_refund_amount
                        : ($return->total_amount > 0 ? $return->total_amount : $return->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price));
                @endphp
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.total_refund') }}</span>
                <strong class="fs-18 font-monospace text-danger">{{ format_currency($displayRefundTotal) }}</strong>
            </div>
        </div>

        @if ($return->reason)
            <div class="border rounded p-3 mb-4 bg-light">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold mb-1">{{ __('purchase.reason') }}</span>
                <p class="fs-13 text-dark mb-0">{{ $return->reason }}</p>
            </div>
        @endif

        <h6 class="fw-bold text-dark mb-2">{{ __('purchase.returned_items') }}</h6>
        <div class="table-responsive rounded border mb-4">
            <table class="table table-bordered align-middle fs-13 text-dark mb-0">
                <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                    <tr>
                        <th class="ps-3" style="width: 40%;">{{ __('purchase.product') }}</th>
                        <th style="width: 25%;">{{ __('purchase.warehouse') ?? 'Warehouse' }}</th>
                        <th class="text-end" style="width: 15%;">{{ __('purchase.quantity') }}</th>
                        <th class="text-end pe-3" style="width: 20%;">{{ __('purchase.unit_price') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($return->items as $item)
                        <tr>
                            <td class="ps-3 fw-semibold text-dark">{{ $item->product?->name ?: '—' }}</td>
                            <td>{{ $item->warehouse?->name ?: '—' }}</td>
                            <td class="text-end font-monospace">{{ (float) $item->quantity }}</td>
                            <td class="text-end pe-3 font-monospace fw-semibold">{{ format_currency($item->unit_price) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold fs-13 bg-light">
                        <td class="ps-3" colspan="3">{{ __('purchase.total_refund') }}</td>
                        <td class="text-end pe-3 font-monospace text-danger">{{ format_currency($displayRefundTotal) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

@endsection
