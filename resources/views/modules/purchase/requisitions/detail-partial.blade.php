{{--
    Partial view for Purchase Requisition details — loaded inside the offcanvas drawer.
    Route: GET /purchase/requisitions/{id}/detail-partial
--}}
@php
    $statusClass = 'warning';
    if ($requisition->status === 'Approved') $statusClass = 'success';
    elseif ($requisition->status === 'Cancelled') $statusClass = 'danger';

    $statusColors = [
        'Draft'     => ['bg' => '#fff8e1', 'color' => '#b45309', 'border' => '#fde68a'],
        'Approved'  => ['bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#bbf7d0'],
        'Cancelled' => ['bg' => '#fff1f2', 'color' => '#dc2626', 'border' => '#fecaca'],
    ];
    $sc = $statusColors[$requisition->status] ?? $statusColors['Draft'];
@endphp

{{-- ── Header ── --}}
<div class="px-1 pb-3 border-bottom mb-3">
    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
        <div>
            <span class="fs-10 text-muted text-uppercase fw-bold letter-spacing-1 d-block mb-1">
                {{ __('purchase.purchase_requests') }}
            </span>
            <h5 class="fw-bold text-dark mb-0">{{ $requisition->requisition_number }}</h5>
        </div>
        <div class="d-flex align-items-center gap-1.5">
            <span class="badge fs-11 fw-semibold px-3 py-1 rounded-pill"
                  style="background:{{ $sc['bg'] }}; color:{{ $sc['color'] }}; border:1px solid {{ $sc['border'] }};">
                {{ __('purchase.status_' . strtolower($requisition->status)) }}
            </span>
            @if($requisition->reminder_count > 0)
                @php
                    $remData = $requisition->reminders->map(fn($r) => [
                        'user' => $r->user->name ?? 'User',
                        'time' => $r->created_at->format('d M Y h:i A'),
                        'note' => $r->note
                    ]);
                @endphp
                <button type="button" class="btn btn-xs btn-soft-danger border border-danger-subtle px-2 py-1 fs-11 fw-bold"
                        onclick="showReminderHistoryModal('{{ $requisition->requisition_number }}', {{ json_encode($remData) }})">
                    <i class="feather-bell me-1"></i>Reminded ({{ $requisition->reminder_count }})
                </button>
            @endif
        </div>
    </div>
    <div class="d-flex flex-wrap gap-3 fs-12 text-muted mt-2">
        <span><i class="feather-user me-1"></i>
            <strong class="text-dark">{{ $requisition->requester->name ?? __('purchase.system') }}</strong>
        </span>
        <span><i class="feather-calendar me-1"></i>
            Req Date: <strong class="text-dark">{{ $requisition->requisition_date ? $requisition->requisition_date->format('d M Y') : '—' }}</strong>
        </span>
        @if($requisition->expected_date)
            <span><i class="feather-clock me-1 text-primary"></i>
                Exp Date: <strong class="text-primary">{{ $requisition->expected_date->format('d M Y') }}</strong>
            </span>
        @endif
    </div>
</div>

{{-- ── Approve / Reject (if Draft) ── --}}
@if($requisition->status === 'Draft')
    <div class="d-flex gap-2 mb-3">
        <form action="{{ route('purchase.requisitions.approve', $requisition->id) }}" method="POST" class="flex-fill" id="approvePrDrawerForm_{{ $requisition->id }}">
            @csrf
            <button type="button" class="btn btn-success btn-sm w-100" onclick="confirmAction({ title: 'Approve Requisition', message: '{{ __('purchase.confirm_approve') }}', variant: 'success', confirmText: 'Approve' }, function() { document.getElementById('approvePrDrawerForm_{{ $requisition->id }}').submit(); })">
                <i class="feather-check-circle me-1"></i> {{ __('purchase.approve') }}
            </button>
        </form>
        <button type="button" class="btn btn-danger btn-sm flex-fill" onclick="openRejectModal('{{ route('purchase.requisitions.reject', $requisition->id) }}', '{{ $requisition->requisition_number }}')">
            <i class="feather-x-circle me-1"></i> {{ __('purchase.reject') }}
        </button>
    </div>
@endif

{{-- ── Traceability ── --}}
<div class="card border-0 bg-light rounded-3 mb-3">
    <div class="card-body py-3 px-3">
        <h6 class="fw-bold text-uppercase fs-10 text-muted letter-spacing-1 mb-3">
            <i class="feather-link-2 me-1 text-primary"></i>{{ __('purchase.traceability') }}
        </h6>
        <div class="row g-2 fs-13">
            <div class="col-5 text-muted">{{ __('purchase.source_type') }}</div>
            <div class="col-7 fw-semibold text-dark text-uppercase">
                {{ __('purchase.source_' . $requisition->source_type) }}
            </div>
            <div class="col-5 text-muted">{{ __('purchase.origin_document') }}</div>
            <div class="col-7 fw-bold">
                @if($requisition->source_type === 'mo' && $requisition->sourceable)
                    <a href="{{ route('production.orders.show', $requisition->source_id) }}" class="text-primary">
                        <i class="feather-cpu me-1"></i>{{ $requisition->sourceable->order_number }}
                    </a>
                @elseif($requisition->source_type === 'material_request' && $requisition->sourceable)
                    <a href="{{ route('sales.material-requests.show', $requisition->source_id) }}" class="text-primary">
                        <i class="feather-file-text me-1"></i>{{ $requisition->sourceable->requisition_number }}
                    </a>
                @elseif($requisition->source_type === 'material_requirement' && $requisition->sourceable)
                    <a href="{{ route('sales.material-requirements.show', $requisition->source_id) }}" class="text-primary">
                        <i class="feather-archive me-1"></i>{{ $requisition->sourceable->requirement_number }}
                    </a>
                @elseif($requisition->source_type === 'so' && $requisition->sourceable)
                    <a href="{{ route('sales.orders.show', $requisition->source_id) }}" class="text-primary">
                        <i class="feather-shopping-cart me-1"></i>{{ $requisition->sourceable->sales_order_number }}
                    </a>
                @elseif($requisition->source_type === 'requisition_slip')
                    <span class="font-monospace text-dark">{{ $requisition->requisition_slip_number ?: '—' }}</span>
                @else
                    <span class="text-muted">{{ __('purchase.direct_creation') }}</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Notes ── --}}
@if($requisition->notes)
    <div class="card border-0 bg-light rounded-3 mb-3">
        <div class="card-body py-3 px-3">
            <h6 class="fw-bold text-uppercase fs-10 text-muted letter-spacing-1 mb-2">
                <i class="feather-file-text me-1 text-primary"></i>{{ __('purchase.notes') }}
            </h6>
            <p class="fs-13 text-dark mb-0">{!! nl2br(e($requisition->notes)) !!}</p>
        </div>
    </div>
@endif

{{-- ── Line Items ── --}}
<div class="mb-2">
    <h6 class="fw-bold text-dark fs-12 mb-2">
        <i class="feather-list text-primary me-1"></i>{{ __('purchase.requisition_line_items') }}
    </h6>
    <table class="table table-bordered align-middle fs-12 mb-0 w-100">
        <thead class="table-light text-uppercase text-muted fs-10 fw-semibold">
            <tr>
                <th style="width:45%">{{ __('purchase.product') }}</th>
                <th class="text-center" style="width:15%">{{ __('purchase.quantity') }}</th>
                <th class="text-end" style="width:20%">{{ __('purchase.estimated_cost') }}</th>
                <th class="text-end" style="width:20%">{{ __('purchase.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($requisition->items as $item)
                @php
                    $lineTotal = $item->quantity * $item->estimated_cost;
                    $grandTotal += $lineTotal;
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold text-dark">{{ $item->product->name }}</div>
                        <div class="text-muted fs-10">SKU: {{ $item->product->sku ?: '—' }}</div>
                        @if($item->warehouse)
                            <div class="text-muted fs-10"><i class="feather-map-pin me-1"></i>{{ $item->warehouse->name }}</div>
                        @endif
                    </td>
                    <td class="text-center fw-semibold">{{ (float)$item->quantity }}</td>
                    <td class="text-end">₹{{ number_format($item->estimated_cost, 2) }}</td>
                    <td class="text-end fw-bold">₹{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="3" class="text-end text-uppercase fs-10 text-muted letter-spacing-1">
                    {{ __('purchase.estimated_requisition_total') }}
                </td>
                <td class="text-end text-primary fs-14">₹{{ number_format($grandTotal, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
