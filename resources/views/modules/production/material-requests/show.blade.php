@extends('layouts.duralux')

@section('title', 'Slip Details | SaaS ERP')
@section('page-title', 'Material Requisition Slip Details')
@section('breadcrumb')
    <a href="{{ route('production.material-requests.index') }}">Material Requests</a> &gt; Details
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

        <x-ui.odoo-form-ui type="sheet">
            <!-- Header section with back button -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <div>
                    <h4 class="fw-bold text-dark mb-0">{{ $slip->requisition_number }}</h4>
                    <small class="text-muted fs-12">Generated on {{ date('d-m-Y', strtotime($slip->requisition_date)) }}</small>
                </div>
                <a href="{{ route('production.material-requests.index') }}" class="btn btn-sm btn-light border">
                    <i class="feather-arrow-left me-1"></i> Back to Slips
                </a>
            </div>

            <!-- Slip Summary Widgets -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border bg-light py-2 px-3">
                        <div class="fs-11 text-muted text-uppercase fw-semibold">Production Order</div>
                        <div class="fs-15 fw-bold text-dark">{{ $slip->order->order_number ?? 'MO #' . $slip->production_order_id }}</div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card border bg-light py-2 px-3">
                        <div class="fs-11 text-muted text-uppercase fw-semibold">Target Product (to Manufacture)</div>
                        <div class="fs-14 fw-bold text-dark">{{ $slip->order->product->name ?? '—' }} ({{ $slip->order->product->sku ?? '—' }})</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border bg-light py-2 px-3">
                        <div class="fs-11 text-muted text-uppercase fw-semibold">Qty Ordered</div>
                        <div class="fs-15 fw-bold text-dark">{{ (float) ($slip->order->quantity_ordered ?? 0.0) }}</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border bg-light py-2 px-3">
                        <div class="fs-11 text-muted text-uppercase fw-semibold">Slip Status</div>
                        <div>
                            @if($slip->status === 'completed')
                                <span class="badge bg-success-soft text-success px-2 py-1 fs-12 mt-1">Completed</span>
                            @elseif($slip->status === 'partial')
                                <span class="badge bg-warning-soft text-warning px-2 py-1 fs-12 mt-1">Partial</span>
                            @else
                                <span class="badge bg-danger-soft text-danger px-2 py-1 fs-12 mt-1">Pending</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <h5 class="fw-bold text-dark mb-3"><i class="feather-layers text-primary me-2"></i>Requested Components &amp; Raw Materials</h5>
            <div class="table-responsive">
                <table class="table table-bordered align-middle text-dark mb-0 fs-13">
                    <thead class="bg-soft-light text-uppercase fs-11 fw-semibold text-muted">
                        <tr>
                            <th style="width: 25%">Component Product</th>
                            <th class="text-center" style="width: 10%">Planned Qty</th>
                            <th class="text-center" style="width: 10%">Reserved Qty</th>
                            <th class="text-center" style="width: 10%">Issued Qty</th>
                            <th class="text-center" style="width: 15%">Total Available Stock</th>
                            <th style="width: 20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            @php
                                $remainingToIssue = max(0.0, $item->quantity_planned - $item->quantity_issued);
                                $remainingToReserve = max(0.0, $item->quantity_planned - ($item->quantity_issued + $item->quantity_reserved));
                                $totalAvailableStock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $slip->tenant_id)
                                    ->where('product_id', $item->product_id)
                                    ->sum('available_qty');
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $item->product->name }}</div>
                                    <div class="text-muted fs-11">SKU: {{ $item->product->sku }} | Type: {{ ucfirst(str_replace('_', ' ', $item->product->type)) }}</div>
                                </td>
                                <td class="text-center fw-semibold">{{ (float) $item->quantity_planned }} {{ $item->uom->code }}</td>
                                <td class="text-center text-primary fw-semibold">{{ (float) $item->quantity_reserved }} {{ $item->uom->code }}</td>
                                <td class="text-center text-success fw-bold">{{ (float) $item->quantity_issued }} {{ $item->uom->code }}</td>
                                <td class="text-center text-muted fw-semibold">
                                    {{ (float) $totalAvailableStock }} {{ $item->uom->code }}
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @if($remainingToReserve > 0 && $totalAvailableStock > 0)
                                            <button type="button" class="btn btn-sm btn-soft-primary px-2 py-1 fs-11 fw-semibold w-100 text-start" data-bs-toggle="modal" data-bs-target="#reserveModal-{{ $item->id }}">
                                                <i class="feather-archive me-1"></i>Reserve
                                            </button>
                                        @endif

                                        @if($item->quantity_reserved > 0)
                                            <button type="button" class="btn btn-sm btn-soft-success px-2 py-1 fs-11 fw-semibold w-100 text-start" data-bs-toggle="modal" data-bs-target="#issueModal-{{ $item->id }}">
                                                <i class="feather-check-circle me-1"></i>Issue
                                            </button>
                                        @endif

                                        @if($remainingToIssue > 0 && $totalAvailableStock <= 0)
                                            <button type="button" class="btn btn-sm btn-soft-danger px-2 py-1 fs-11 fw-semibold w-100 text-start" data-bs-toggle="modal" data-bs-target="#shortageModal-{{ $item->id }}">
                                                <i class="feather-shopping-cart me-1"></i>Create Indent
                                            </button>
                                        @endif

                                        @if($remainingToIssue <= 0)
                                            <span class="text-success fs-12 fw-bold"><i class="feather-check-circle me-1"></i> Fully Issued</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.odoo-form-ui>
    </div>

    <!-- Modals outside the card block using common x-ui.modal element -->
    @foreach($items as $item)
        @php
            $remainingToIssue = max(0.0, $item->quantity_planned - $item->quantity_issued);
            $remainingToReserve = max(0.0, $item->quantity_planned - ($item->quantity_issued + $item->quantity_reserved));
            $totalAvailableStock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $slip->tenant_id)
                ->where('product_id', $item->product_id)
                ->sum('available_qty');
        @endphp

        <!-- Reserve Modal -->
        @if($remainingToReserve > 0 && $totalAvailableStock > 0)
            <x-ui.modal
                id="reserveModal-{{ $item->id }}"
                title="Reserve Stock — {{ $item->product->name }}"
                submitText="Confirm Reservation"
                formAction="{{ route('production.material-requests.reserve', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-primary text-primary">
                            <i class="feather-package"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product->sku }} | Planned: {{ (float) $item->quantity_planned }}</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Select Warehouse</label>
                        <select class="form-select form-select-sm reserve-warehouse-select" data-item-id="{{ $item->id }}" data-remaining="{{ $remainingToReserve }}" name="warehouse_id" onchange="updateReserveQtyLimit({{ $item->id }}, this, {{ $remainingToReserve }})">
                            @foreach($warehouses as $wh)
                                @php
                                    $whAvail = \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $wh->id);
                                @endphp
                                <option value="{{ $wh->id }}" data-avail="{{ $whAvail }}">
                                    {{ $wh->name }} (Available: {{ (float)$whAvail }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Qty to Reserve (Max: <span id="reserve-max-label-{{ $item->id }}" class="fw-bold text-dark">0</span>)</label>
                        <input type="number" id="reserve-qty-input-{{ $item->id }}" name="quantity" class="form-control" step="0.0001" min="0.0001" required>
                    </div>
                </div>
            </x-ui.modal>
        @endif

        <!-- Issue Modal -->
        @if($item->quantity_reserved > 0)
            <x-ui.modal
                id="issueModal-{{ $item->id }}"
                title="Issue Reserved Stock — {{ $item->product->name }}"
                submitText="Confirm Issue"
                formAction="{{ route('production.material-requests.issue', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-success text-success">
                            <i class="feather-check-circle"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product->sku }} | Reserved: {{ (float) $item->quantity_reserved }}</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Select Warehouse</label>
                        <select class="form-select form-select-sm" name="warehouse_id">
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ $item->warehouse_id == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Qty to Issue (Max: {{ (float)$item->quantity_reserved }})</label>
                        <input type="number" name="quantity" class="form-control" step="0.0001" min="0.0001" max="{{ $item->quantity_reserved }}" value="{{ $item->quantity_reserved }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Remarks</label>
                        <input type="text" name="remarks" class="form-control" placeholder="e.g. Issued to shop floor">
                    </div>
                </div>
            </x-ui.modal>
        @endif

        <!-- Shortage Modal (Indent) -->
        @if($remainingToIssue > 0 && $totalAvailableStock <= 0)
            <x-ui.modal
                id="shortageModal-{{ $item->id }}"
                title="Create Indent — {{ $item->product->name }}"
                submitText="Raise Purchase Requisition"
                formAction="{{ route('production.material-requests.create-pr', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-danger text-danger">
                            <i class="feather-shopping-cart"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product->sku }} | Shortage: {{ $remainingToIssue }}</small>
                        </div>
                    </div>

                    <p class="mb-0">This will generate a Draft Purchase Requisition for the shortage quantity of <strong>{{ $remainingToIssue }} {{ $item->uom->code }}</strong>.</p>
                </div>
            </x-ui.modal>
        @endif
    @endforeach

@endsection

@push('scripts')
    <script>
        function updateReserveQtyLimit(itemId, select, remainingToReserve) {
            const selectedOption = select.options[select.selectedIndex];
            const avail = parseFloat(selectedOption.getAttribute('data-avail')) || 0.0;
            
            // Limit reserve quantity: min of remaining to reserve and warehouse available stock
            const maxVal = Math.min(remainingToReserve, avail);
            
            const $input = $(`#reserve-qty-input-${itemId}`);
            $input.attr('max', maxVal);
            $input.val(maxVal > 0 ? maxVal : '');
            $(`#reserve-max-label-${itemId}`).text(maxVal);
        }

        $(document).ready(function () {
            $('.reserve-warehouse-select').each(function () {
                const itemId = $(this).data('item-id');
                const remaining = parseFloat($(this).data('remaining')) || 0.0;
                updateReserveQtyLimit(itemId, this, remaining);
            });
        });
    </script>
@endpush

