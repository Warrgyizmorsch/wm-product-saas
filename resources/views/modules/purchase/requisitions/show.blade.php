@extends('layouts.duralux')

@section('title', 'Purchase Request Details | SaaS ERP')
@section('page-title', 'Purchase Request Details')
@section('breadcrumb')
    <a href="{{ route('purchase.requisitions.index') }}">Purchase Requests</a> &gt; Details
@endsection

@section('content')
    <div class="erp-single-panel">
        <!-- Toast Notifications -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <x-ui.odoo-form-ui type="sheet" class="p-0">
            <!-- Header bar with buttons -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 px-4 pt-4 pb-3 border-bottom">
                <div>
                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">Purchase Request</span>
                    <h4 class="fw-bold text-dark mb-1">{{ $requisition->requisition_number }}</h4>
                    <span class="fs-13 text-muted">
                        Requested By:&nbsp;<strong class="text-dark">{{ $requisition->requester->name ?? 'System' }}</strong>
                        &nbsp;·&nbsp;Date:&nbsp;<strong class="text-dark">{{ $requisition->requisition_date ? $requisition->requisition_date->format('d-m-Y') : '—' }}</strong>
                    </span>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @php
                        $statusClass = 'warning';
                        if ($requisition->status === 'Approved') $statusClass = 'success';
                        elseif ($requisition->status === 'Cancelled') $statusClass = 'danger';
                    @endphp
                    <x-ui.badge :soft="true" :variant="$statusClass" class="px-3 py-1.5 fs-12 fw-semibold me-2">
                        {{ $requisition->status }}
                    </x-ui.badge>

                    <x-ui.button href="{{ route('purchase.requisitions.index') }}" variant="light" size="sm" icon="feather-arrow-left">
                        Back to List
                    </x-ui.button>

                    @if($requisition->status === 'Draft')
                        <x-ui.button href="{{ route('purchase.requisitions.edit', $requisition->id) }}" variant="info" size="sm" icon="feather-edit">
                            Edit
                        </x-ui.button>

                        <form action="{{ route('purchase.requisitions.approve', $requisition->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to approve this purchase request?')">
                            @csrf
                            <x-ui.button type="submit" variant="success" size="sm" icon="feather-check-circle">
                                Approve PR
                            </x-ui.button>
                        </form>

                        <form action="{{ route('purchase.requisitions.destroy', $requisition->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this purchase request?')">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger" size="sm" icon="feather-trash-2">
                                Delete
                            </x-ui.button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Summary Block and Links -->
            <div class="px-4 py-4 border-bottom bg-light-50">
                <div class="row g-4 fs-13">
                    <div class="col-md-6 border-end-md">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">Traceability / Source Documents</h6>
                        <table class="table table-borderless table-sm mb-0 text-dark">
                            <tr>
                                <td class="text-muted ps-0" style="width: 35%">Source Type:</td>
                                <td class="fw-semibold text-uppercase">{{ str_replace('_', ' ', $requisition->source_type) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Origin Document:</td>
                                <td class="fw-bold">
                                    @if($requisition->source_type === 'mo' && $requisition->sourceable)
                                        <a href="{{ route('production.orders.show', $requisition->source_id) }}" class="text-primary hover-underline">
                                            <i class="feather-cpu me-1"></i>{{ $requisition->sourceable->order_number }}
                                        </a>
                                    @elseif($requisition->source_type === 'material_request' && $requisition->sourceable)
                                        <a href="{{ route('sales.material-requests.show', $requisition->source_id) }}" class="text-primary hover-underline">
                                            <i class="feather-file-text me-1"></i>{{ $requisition->sourceable->requisition_number }}
                                        </a>
                                    @elseif($requisition->source_type === 'material_requirement' && $requisition->sourceable)
                                        <a href="{{ route('sales.material-requirements.show', $requisition->source_id) }}" class="text-primary hover-underline">
                                            <i class="feather-archive me-1"></i>{{ $requisition->sourceable->requirement_number }}
                                        </a>
                                    @elseif($requisition->source_type === 'so' && $requisition->sourceable)
                                        <a href="{{ route('sales.orders.show', $requisition->source_id) }}" class="text-primary hover-underline">
                                            <i class="feather-shopping-cart me-1"></i>{{ $requisition->sourceable->sales_order_number }}
                                        </a>
                                    @elseif($requisition->source_type === 'requisition_slip')
                                        <span class="text-dark font-monospace">{{ $requisition->requisition_slip_number ?: '—' }}</span>
                                    @else
                                        <span class="text-muted">Direct Creation (No Document link)</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">Requisition Notes</h6>
                        <div class="text-muted bg-white p-3 border rounded" style="min-height: 80px;">
                            {!! nl2br(e($requisition->notes ?: 'No additional notes provided.')) !!}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="px-4 py-4">
                <h5 class="fw-bold text-dark mb-3"><i class="feather-list text-primary me-2"></i>Requisitioned Line Items</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-dark mb-0 fs-13">
                        <thead class="bg-soft-light text-uppercase fs-11 fw-semibold text-muted">
                            <tr>
                                <th style="width: 45%">Product</th>
                                <th style="width: 25%">Target Warehouse</th>
                                <th class="text-end" style="width: 15%">Quantity</th>
                                <th class="text-end" style="width: 15%">Estimated Cost</th>
                                <th class="text-end" style="width: 15%">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $grandTotal = 0.00; @endphp
                            @foreach($requisition->items as $item)
                                @php
                                    $lineTotal = $item->quantity * $item->estimated_cost;
                                    $grandTotal += $lineTotal;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $item->product->name }}</div>
                                        <div class="text-muted fs-11">SKU: {{ $item->product->sku ?: '—' }}</div>
                                    </td>
                                    <td>{{ $item->warehouse->name ?? '—' }}</td>
                                    <td class="text-end fw-semibold">{{ (float)$item->quantity }}</td>
                                    <td class="text-end">₹{{ number_format($item->estimated_cost, 2) }}</td>
                                    <td class="text-end fw-bold">₹{{ number_format($lineTotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-soft-light fw-bold text-dark">
                            <tr>
                                <td colspan="4" class="text-end text-uppercase fs-11 letter-spacing-1 text-muted">Estimated Requisition Total:</td>
                                <td class="text-end fs-15 text-primary">₹{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </x-ui.odoo-form-ui>
    </div>
@endsection
