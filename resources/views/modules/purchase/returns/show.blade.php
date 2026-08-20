@extends('layouts.duralux')

@section('title', 'Purchase Return ' . $return->return_number)
@section('page-title', 'Purchase Return ' . $return->return_number)
@section('breadcrumb', 'Purchase / Returns / Details')

@section('page-actions')
    <x-ui.button href="{{ route('purchase.returns.index') }}" variant="light" icon="feather-arrow-left" class="border">
        Back to Returns
    </x-ui.button>

    @if (in_array($return->status, ['Pending', 'Draft']))
        <form action="{{ route('purchase.returns.approve', $return->id) }}" method="POST" id="approveReturnForm" class="d-inline">
            @csrf
            <x-ui.button type="submit" variant="success" size="md" icon="feather-check-circle">
                Approve &amp; Remove from Inventory
            </x-ui.button>
        </form>
    @endif
@endsection

@section('content')
    <x-ui.card class="mb-4">
        <div class="row g-4 fs-13">
            <div class="col-md-3">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Return Number</span>
                <span class="fw-bold text-dark font-monospace">{{ $return->return_number }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Date</span>
                <span class="fw-bold text-dark">{{ date('d M Y', strtotime($return->return_date)) }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Vendor</span>
                <span class="fw-bold text-dark">{{ $return->vendor?->name ?: '—' }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Status</span>
                @php
                    $badgeVariant = $return->status === 'Completed' ? 'success' : ($return->status === 'Cancelled' ? 'danger' : 'warning');
                @endphp
                <x-ui.badge :variant="$badgeVariant" soft>{{ $return->status }}</x-ui.badge>
            </div>
        </div>
        @if ($return->reason)
            <div class="mt-3 pt-3 border-top fs-13">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Reason</span>
                {{ $return->reason }}
            </div>
        @endif
    </x-ui.card>

    <x-ui.card bodyClass="p-0">
        <x-slot:title>Returned Items</x-slot:title>
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Product</th>
                    <th>Warehouse</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end pe-4">Unit Price</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @foreach ($return->items as $item)
                    <tr>
                        <td class="ps-4">{{ $item->product?->name ?: '—' }}</td>
                        <td>{{ $item->warehouse?->name ?: '—' }}</td>
                        <td class="text-end">{{ (float) $item->quantity }}</td>
                        <td class="text-end pe-4">{{ number_format($item->unit_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @php
                    $displayRefundTotal = $return->total_refund_amount > 0
                        ? $return->total_refund_amount
                        : ($return->total_amount > 0 ? $return->total_amount : $return->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price));
                @endphp
                <tr class="fw-bold fs-13 bg-light">
                    <td class="ps-4" colspan="3">Total Refund</td>
                    <td class="text-end pe-4">{{ number_format($displayRefundTotal, 2) }}</td>
                </tr>
            </tfoot>
        </x-ui.table>
    </x-ui.card>
@endsection
