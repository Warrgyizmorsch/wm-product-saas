{{-- PO Detail Partial — loaded into offcanvas drawer via AJAX --}}
@php
    $statusClass = 'warning';
    if ($order->status === 'Completed') $statusClass = 'success';
    elseif ($order->status === 'Approved') $statusClass = 'primary';
    elseif ($order->status === 'Partially Received') $statusClass = 'info';
    elseif (in_array($order->status, ['Cancelled', 'Rejected'])) $statusClass = 'danger';
@endphp

{{-- ── Header ── --}}
<div class="px-1 pb-3 border-bottom mb-3">
    <div class="d-flex align-items-start justify-content-between mb-2">
        <div>
            <span class="fs-10 text-muted text-uppercase fw-bold letter-spacing-1 d-block mb-1">Purchase Order</span>
            <h5 class="fw-bold text-dark mb-1">{{ $order->purchase_order_number }}</h5>
            <div class="d-flex align-items-center gap-3 text-muted fs-12">
                <span><i class="feather-user me-1"></i>{{ $order->vendor->name ?? '—' }}</span>
                <span><i class="feather-calendar me-1"></i>{{ $order->date ? $order->date->format('d M Y') : '—' }}</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1.5">
            <x-ui.badge :soft="true" :variant="$statusClass" class="fs-11 fw-bold px-2 py-1">
                {{ $order->status }}
            </x-ui.badge>
            @if($order->reminder_count > 0)
                @php
                    $remData = $order->reminders->map(fn($r) => [
                        'user' => $r->user->name ?? 'User',
                        'time' => $r->created_at->format('d M Y h:i A'),
                        'note' => $r->note
                    ]);
                @endphp
                <button type="button" class="btn btn-xs btn-soft-danger border border-danger-subtle px-2 py-1 fs-11 fw-bold"
                        onclick="showReminderHistoryModal('{{ $order->purchase_order_number }}', {{ json_encode($remData) }})">
                    <i class="feather-bell me-1"></i>Reminded ({{ $order->reminder_count }})
                </button>
            @endif
        </div>
    </div>

    {{-- Approve / Reject / Remind inside drawer --}}
    <div class="d-flex gap-2 mt-3">
        <form action="{{ route('purchase.orders.approve', $order->id) }}" method="POST" class="d-inline" id="approvePoDrawerForm_{{ $order->id }}">
            @csrf
            <button type="button" class="btn btn-sm btn-success px-3" onclick="confirmAction({ title: 'Approve PO', message: 'Approve this Purchase Order?', variant: 'success', confirmText: 'Approve' }, function() { document.getElementById('approvePoDrawerForm_{{ $order->id }}').submit(); })">
                <i class="feather-check-circle me-1"></i> Approve
            </button>
        </form>
        <button type="button" class="btn btn-sm btn-danger px-3" onclick="openRejectModal('{{ route('purchase.orders.reject', $order->id) }}', '{{ $order->purchase_order_number }}')">
            <i class="feather-x-circle me-1"></i> Reject
        </button>
    </div>
</div>

{{-- ── Supplier & Reference Info ── --}}
<div class="card border-0 bg-light rounded-3 mb-3">
    <div class="card-body py-3 px-3">
        <h6 class="fw-bold text-uppercase fs-10 text-muted letter-spacing-1 mb-2">
            <i class="feather-link me-1 text-primary"></i> Order Info
        </h6>
        <div class="row g-2 fs-12">
            <div class="col-5 text-muted">Supplier</div>
            <div class="col-7 fw-semibold text-dark">{{ $order->vendor->name ?? '—' }}</div>

            <div class="col-5 text-muted">PO Date</div>
            <div class="col-7 fw-semibold">{{ $order->date ? $order->date->format('d M Y') : '—' }}</div>

            @if($order->delivery_date)
                <div class="col-5 text-muted">Expected Delivery Date</div>
                <div class="col-7 fw-semibold text-info">{{ $order->delivery_date->format('d M Y') }}</div>
            @endif

            @if($order->completed_at)
                <div class="col-5 text-muted">Completion Date</div>
                <div class="col-7 fw-bold text-success">{{ $order->completed_at->format('d M Y H:i') }}</div>
            @endif

            @if($order->reference)
                <div class="col-5 text-muted">Reference</div>
                <div class="col-7 fw-semibold font-monospace">{{ $order->reference }}</div>
            @endif

            @if($order->requisition)
                <div class="col-5 text-muted">PR Reference</div>
                <div class="col-7">
                    <a href="{{ route('purchase.requisitions.show', $order->requisition->id) }}" class="text-primary fw-semibold">
                        {{ $order->requisition->requisition_number }}
                    </a>
                </div>
            @endif

            @if($order->delivery_date)
                <div class="col-5 text-muted">Expected Delivery</div>
                <div class="col-7 fw-semibold">{{ $order->delivery_date->format('d M Y') }}</div>
            @endif
        </div>
    </div>
</div>

{{-- ── Line Items Table ── --}}
<div class="mb-2">
    <h6 class="fw-bold text-dark fs-12 mb-2">
        <i class="feather-list text-primary me-1"></i> Order Items
    </h6>
    <table class="table table-bordered align-middle fs-12 mb-0 w-100">
        <thead class="table-light text-uppercase text-muted fs-10 fw-semibold">
            <tr>
                <th style="width:45%">Product</th>
                <th class="text-center" style="width:15%">Qty</th>
                <th class="text-end" style="width:20%">Unit Price</th>
                <th class="text-end" style="width:20%">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($order->items as $item)
                @php
                    $lineTotal = $item->quantity * $item->rate;
                    $grandTotal += $lineTotal;
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold text-dark">{{ $item->product->name ?? '—' }}</div>
                        <div class="text-muted fs-10">SKU: {{ $item->product->sku ?? '—' }}</div>
                    </td>
                    <td class="text-center fw-semibold">{{ (float)$item->quantity }}</td>
                    <td class="text-end">₹{{ number_format($item->rate, 2) }}</td>
                    <td class="text-end fw-bold">₹{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="3" class="text-end text-uppercase fs-10 text-muted letter-spacing-1">Grand Total</td>
                <td class="text-end text-primary fs-14">₹{{ number_format($order->grand_total ?? $grandTotal, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@if($order->notes)
    <div class="card border-0 bg-light rounded-3 mt-3">
        <div class="card-body py-3 px-3">
            <h6 class="fw-bold text-uppercase fs-10 text-muted letter-spacing-1 mb-2">
                <i class="feather-file-text me-1 text-primary"></i> Notes
            </h6>
            <p class="fs-12 text-dark mb-0">{!! nl2br(e($order->notes)) !!}</p>
        </div>
    </div>
@endif
