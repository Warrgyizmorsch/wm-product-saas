@extends('layouts.duralux')

@section('title', 'Delivery Order ' . $delivery->delivery_number . ' | SaaS ERP')
@section('page-title', 'Delivery Order ' . $delivery->delivery_number)
@section('breadcrumb', 'Sales / Deliveries / ' . $delivery->delivery_number)

@section('content')

    {{-- Session Alerts --}}
    @if (session('success'))
        <x-ui.alert variant="success" :dismissible="true" icon="feather-check-circle" class="shadow-sm mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="danger" :dismissible="true" icon="feather-alert-triangle" class="shadow-sm mb-4">
            <strong>Error!</strong> {{ session('error') }}
        </x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" :dismissible="true" icon="feather-alert-triangle" class="shadow-sm mb-4">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach ($errors->all() as $error)
                    <li class="fs-12">{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @php
        $doStatusClass = 'secondary';
        if (in_array($delivery->status, ['Ready', 'Delivered']))               $doStatusClass = 'success';
        elseif (in_array($delivery->status, ['Partially Ready', 'Processing'])) $doStatusClass = 'info';
        elseif ($delivery->status === 'Dispatched')                             $doStatusClass = 'dark';
        elseif ($delivery->status === 'Cancelled')                              $doStatusClass = 'danger';
        elseif (in_array($delivery->status, ['Picked', 'Packed']))              $doStatusClass = 'primary';
    @endphp

    {{-- ─── Odoo-style Sheet ─── --}}
    <x-ui.odoo-form-ui type="sheet" class="p-0">

        {{-- Status Bar --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 px-4 pt-4 pb-3 border-bottom">

            {{-- Left: DO number + links --}}
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">Fulfillment Shipment</span>
                <h4 class="fw-bold text-dark mb-1">{{ $delivery->delivery_number }}</h4>
                <span class="fs-13 text-muted">
                    SO:&nbsp;<a href="{{ route('sales.orders.show', $delivery->sales_order_id) }}" class="fw-semibold text-primary">{{ $delivery->salesOrder->sales_order_number }}</a>
                    &nbsp;·&nbsp;Customer:&nbsp;<strong class="text-dark">{{ $delivery->salesOrder->customer?->name }}</strong>
                </span>
            </div>



            {{-- Right: DO status badge + action buttons --}}
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <x-ui.badge :soft="true" :variant="$doStatusClass" class="px-3 py-1 fs-12 fw-semibold">
                    {{ $delivery->status }}
                </x-ui.badge>

                <a href="{{ route('sales.orders.show', $delivery->sales_order_id) }}" class="btn btn-light border btn-sm">
                    <i class="feather-arrow-left me-1"></i>SO Details
                </a>

                @if (in_array($delivery->status, ['Processing', 'Partially Ready', 'Ready']))
                    <form action="{{ route('sales.deliveries.picking', $delivery->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="feather-truck me-1"></i>Start Picking
                        </button>
                    </form>
                @elseif ($delivery->status === 'Picked')
                    <form action="{{ route('sales.deliveries.pack', $delivery->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-info text-white btn-sm">
                            <i class="feather-box me-1"></i>Pack Items
                        </button>
                    </form>
                @elseif ($delivery->status === 'Packed')
                    <a href="{{ route('sales.dispatches.create', ['delivery_order_id' => $delivery->id]) }}" class="btn btn-success btn-sm">
                        <i class="feather-send me-1"></i>Dispatch DO
                    </a>
                @elseif ($delivery->status === 'Dispatched')
                    <form action="{{ route('sales.deliveries.deliver', $delivery->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="feather-check-circle me-1"></i>Mark Delivered
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ─── Body: 2-column layout ─── --}}
        <div class="row g-0">

            {{-- Delivery Order Lines --}}
            <div class="col-lg-12">
                <div class="px-4 py-3">

                    <h6 class="fw-bold text-dark mb-3 fs-13 text-uppercase text-muted letter-spacing-1">
                        <i class="feather-list me-1 text-primary"></i> Delivery Order Lines
                    </h6>

                    <div class="table-responsive" style="overflow: visible !important;">
                        <x-ui.odoo-form-ui type="table" class="align-middle fs-13 mb-0" style="margin-top:0;">
                            <thead class="fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th style="width:26%">Product</th>
                                    <th style="width:9%">Method</th>
                                    <th style="width:8%" class="text-end">Order Qty</th>
                                    <th style="width:8%" class="text-end">Reserved</th>
                                    <th style="width:8%" class="text-end">Pending</th>
                                    <th style="width:16%">Warehouse</th>
                                    <th style="width:7%" class="text-end">Avail.</th>
                                    <th style="width:10%" class="text-center">Status</th>
                                    <th style="width:8%" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-dark">
                                @foreach ($delivery->items as $item)
                                    @php
                                        $method      = strtolower($item->product?->supplier_method ?? 'buy');
                                        $isService   = $item->product?->item_type === 'Service';
                                        $orderedQty  = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                                        $reservedQty = (float)$item->quantity_reserved;
                                        $pendingQty  = max(0, $orderedQty - $reservedQty);
                                        $availableQty= (float)($item->available_qty ?? 0);
                                        $isLocked    = in_array($delivery->status, ['Dispatched', 'Delivered', 'Cancelled']);

                                        $lineBadge = 'secondary';
                                        if (in_array($item->status, ['Reserved', 'Ready']))  $lineBadge = 'success';
                                        elseif ($item->status === 'Waiting Purchase')         $lineBadge = 'warning';
                                        elseif ($item->status === 'Waiting Production')       $lineBadge = 'danger';
                                        elseif ($item->status === 'Partially Reserved')       $lineBadge = 'info';
                                        elseif ($item->status === 'Picked')                   $lineBadge = 'primary';
                                        elseif ($item->status === 'Packed')                   $lineBadge = 'info';
                                        elseif (in_array($item->status, ['Dispatched','Delivered'])) $lineBadge = 'dark';
                                    @endphp
                                    <tr>
                                        {{-- Product --}}
                                        <td>
                                            <strong class="text-dark">{{ $item->product?->name ?? 'Unknown' }}</strong>
                                            @if ($item->product?->sku)
                                                <small class="text-muted d-block font-monospace fs-10">SKU: {{ $item->product->sku }}</small>
                                            @endif
                                        </td>

                                        {{-- Method --}}
                                        <td>
                                            @if ($method === 'manufacture')
                                                <x-ui.badge :soft="true" variant="warning" class="fs-11 px-2">Mfg</x-ui.badge>
                                            @else
                                                <x-ui.badge :soft="true" variant="success" class="fs-11 px-2">Buy</x-ui.badge>
                                            @endif
                                        </td>

                                        <td class="text-end fw-semibold">{{ (int)$orderedQty }}</td>
                                        <td class="text-end fw-bold text-success">{{ (int)$reservedQty }}</td>
                                        <td class="text-end text-muted">{{ (int)$pendingQty }}</td>
                                        
                                        {{-- Warehouse Dropdown --}}
                                        <td>
                                            @if ($isService)
                                                <span class="text-muted">—</span>
                                            @else
                                                <select
                                                    id="warehouse-select-{{ $item->id }}"
                                                    class="odoo-table-select"
                                                    onchange="changeWarehouse({{ $item->id }}, this)"
                                                    {{ $isLocked ? 'disabled' : '' }}
                                                >
                                                    @foreach ($warehouses as $w)
                                                        <option value="{{ $w->id }}" {{ ($item->warehouse_id ?: $defaultWarehouseId) == $w->id ? 'selected' : '' }}>
                                                            {{ $w->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>

                                        {{-- Available Qty --}}
                                        <td class="text-end fw-bold">
                                            @if ($isService)
                                                <span class="text-muted">—</span>
                                            @else
                                                <span
                                                    id="available-qty-{{ $item->id }}"
                                                    class="{{ $availableQty >= $orderedQty ? 'text-success' : 'text-danger' }}"
                                                 >{{ (int)$availableQty }}</span>
                                            @endif
                                        </td>

                                        {{-- Line Status --}}
                                        <td class="text-center">
                                            <x-ui.badge :soft="true" :variant="$lineBadge" class="fs-11 px-2">
                                                {{ $item->status }}
                                            </x-ui.badge>
                                        </td>

                                        {{-- Action --}}
                                        <td class="text-center">
                                            @if (!$isLocked)
                                                <div class="d-flex flex-column align-items-center gap-1">
                                                    @if ($pendingQty > 0)
                                                        @if ($availableQty > 0)
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-soft-primary px-2 py-1 fs-11 fw-semibold w-100"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#reserveModal-{{ $item->id }}"
                                                            ><i class="feather-archive me-1"></i>Reserve</button>
                                                        @endif

                                                        @if ($method === 'buy')
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-soft-warning px-2 py-1 fs-11 fw-semibold w-100"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#indentModal-{{ $item->id }}"
                                                            ><i class="feather-file-text me-1"></i>Indent</button>
                                                        @elseif ($method === 'manufacture')
                                                            @if ($item->status === 'Pending')
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-soft-danger px-2 py-1 fs-11 fw-semibold w-100"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#generateMoModal-{{ $item->id }}"
                                                                ><i class="feather-cpu me-1"></i>Gen MO</button>
                                                            @else
                                                                <x-ui.badge :soft="true" variant="warning" class="fs-11 px-2">
                                                                    <i class="feather-clock me-1"></i>MO Raised
                                                                </x-ui.badge>
                                                            @endif
                                                        @endif
                                                    @else
                                                        <x-ui.badge :soft="true" variant="success" class="fs-11 px-2">
                                                            <i class="feather-check me-1"></i>Fulfilled
                                                        </x-ui.badge>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted fs-12">Locked</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>

                </div>{{-- /px-4 py-3 --}}
            </div>{{-- /col-lg-12 --}}
        </div>{{-- /row g-0 --}}
    </x-ui.odoo-form-ui>{{-- /sheet --}}


    {{-- ============================================================ --}}
    {{-- Per-item Modals                                              --}}
    {{-- ============================================================ --}}
    @foreach ($delivery->items as $item)
        @php
            $method       = strtolower($item->product?->supplier_method ?? 'buy');
            $isService    = $item->product?->item_type === 'Service';
            $orderedQty   = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
            $reservedQty  = (float)$item->quantity_reserved;
            $pendingQty   = max(0, $orderedQty - $reservedQty);
            $availableQty = (float)($item->available_qty ?? 0);
            $shortageQty  = max(0, $pendingQty - $availableQty);
        @endphp

        @if (($method === 'buy' || $method === 'manufacture') && $pendingQty > 0)

            {{-- ─── Reserve Stock Modal ─── --}}
            <x-ui.modal
                id="reserveModal-{{ $item->id }}"
                title="Reserve Stock — {{ $item->product?->name }}"
                submitText="Confirm Reservation"
                formAction="{{ route('sales.deliveries.reserve-qty', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    {{-- Product info banner --}}
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-primary text-primary">
                            <i class="feather-package"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product?->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product?->sku ?? '—' }}</small>
                        </div>
                    </div>

                    {{-- Warehouse Selector --}}
                    <div class="odoo-form-group mb-3">
                        <label class="odoo-form-label" for="reserve-warehouse-{{ $item->id }}">Warehouse</label>
                        <div class="flex-grow-1">
                            <select
                                id="reserve-warehouse-{{ $item->id }}"
                                name="warehouse_id"
                                class="odoo-form-control form-select-sm"
                                onchange="updateReserveAvailable({{ $item->id }}, this)"
                                style="border-radius:0;"
                            >
                                @foreach ($warehouses as $w)
                                    <option
                                        value="{{ $w->id }}"
                                        data-avail="{{ \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $w->id) }}"
                                        {{ ($item->warehouse_id ?: $defaultWarehouseId) == $w->id ? 'selected' : '' }}
                                    >{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Stock Summary --}}
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Available</span>
                                <span id="reserve-modal-avail-{{ $item->id }}" class="fs-16 fw-bold text-success">{{ (int)$availableQty }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Order Qty</span>
                                <span class="fs-16 fw-bold text-dark">{{ (int)$orderedQty }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Pending</span>
                                <span class="fs-16 fw-bold text-danger">{{ (int)$pendingQty }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Qty to Reserve --}}
                    <div class="odoo-form-group mb-0">
                        <label class="odoo-form-label" for="reserve-qty-input-{{ $item->id }}">
                            Qty to Reserve <span class="text-danger">*</span>
                        </label>
                        <div class="flex-grow-1">
                            <input
                                type="number"
                                name="quantity_reserve"
                                id="reserve-qty-input-{{ $item->id }}"
                                class="odoo-form-control"
                                min="1"
                                max="{{ min((int)$pendingQty, (int)$availableQty) }}"
                                value="{{ min((int)$pendingQty, (int)$availableQty) }}"
                                required
                            >
                            <div class="text-muted fs-11 mt-1">
                                Max: <span id="reserve-max-label-{{ $item->id }}" class="fw-bold">{{ min((int)$pendingQty, (int)$availableQty) }}</span> units
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.modal>

            {{-- ─── Create Indent Modal ─── --}}
            <x-ui.modal
                id="indentModal-{{ $item->id }}"
                title="Create Purchase Indent — {{ $item->product?->name }}"
                submitText="Submit Indent Request"
                :centered="true"
                :showFooter="true"
            >
                <div class="fs-13 text-dark">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-warning text-warning">
                            <i class="feather-file-text"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product?->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product?->sku ?? '—' }}</small>
                        </div>
                    </div>

                    <x-ui.alert variant="warning" icon="feather-info" class="border-0 fs-12 py-2 mb-3">
                        This will raise a <strong>Purchase Indent</strong> to procure the shortage quantity.
                    </x-ui.alert>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Order Qty</span>
                                <span class="fs-16 fw-bold text-dark">{{ (int)$orderedQty }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Reserved</span>
                                <span class="fs-16 fw-bold text-success">{{ (int)$reservedQty }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Shortage</span>
                                <span class="fs-16 fw-bold text-danger">{{ (int)($shortageQty ?: $pendingQty) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="odoo-form-group">
                        <label class="odoo-form-label">Qty to Indent <span class="text-danger">*</span></label>
                        <div class="flex-grow-1">
                            <input type="number" class="odoo-form-control" name="quantity_indent_preview" min="1" value="{{ (int)($shortageQty ?: $pendingQty) }}">
                            <div class="text-muted fs-11 mt-1">Pre-filled with shortage. You can adjust.</div>
                        </div>
                    </div>

                    <div class="odoo-form-group mb-0">
                        <label class="odoo-form-label">Notes</label>
                        <div class="flex-grow-1">
                            <textarea class="odoo-form-control" rows="2" placeholder="Reason, urgency, etc…"></textarea>
                        </div>
                    </div>
                </div>
            </x-ui.modal>

        @endif

        @if ($method === 'manufacture' && $item->status === 'Pending')

            {{-- ─── Generate MO Modal ─── --}}
            <x-ui.modal
                id="generateMoModal-{{ $item->id }}"
                title="Generate Manufacturing Order — {{ $item->product?->name }}"
                submitText="Raise MO Request"
                formAction="{{ route('sales.deliveries.mock-mo', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-danger text-danger">
                            <i class="feather-cpu"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product?->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product?->sku ?? '—' }}</small>
                        </div>
                    </div>

                    <x-ui.alert variant="danger" icon="feather-cpu" class="border-0 fs-12 py-2 mb-3">
                        This will create a <strong>Manufacturing Order</strong> for the required quantity.
                    </x-ui.alert>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Order Qty</span>
                                <span class="fs-16 fw-bold text-dark">{{ (int)$orderedQty }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">To Manufacture</span>
                                <span class="fs-16 fw-bold text-danger">{{ (int)$pendingQty }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="odoo-form-group">
                        <label class="odoo-form-label">Qty to Mfg <span class="text-danger">*</span></label>
                        <div class="flex-grow-1">
                            <input type="number" name="quantity_mfg" class="odoo-form-control" value="{{ (int)$pendingQty }}" min="1" required>
                        </div>
                    </div>

                    <div class="odoo-form-group mb-0">
                        <label class="odoo-form-label">Notes / Priority</label>
                        <div class="flex-grow-1">
                            <textarea name="notes" class="odoo-form-control" rows="2" placeholder="Priority, due date, special instructions…"></textarea>
                        </div>
                    </div>
                </div>
            </x-ui.modal>

        @endif

    @endforeach

    {{-- ─── Dispatch Modal ─── --}}
    @if ($delivery->status === 'Packed')
        <x-ui.modal
            id="dispatchModal"
            title="Transporter & Shipment Details"
            formAction="{{ route('sales.deliveries.dispatch', $delivery->id) }}"
            submitText="Confirm Dispatch"
            :centered="true"
        >
            <div class="fs-13 text-dark">
                <div class="odoo-form-group">
                    <label class="odoo-form-label">Carrier <span class="text-danger">*</span></label>
                    <div class="flex-grow-1">
                        <input type="text" name="carrier" class="odoo-form-control" placeholder="e.g. DHL, BlueDart, SafeExpress" required>
                    </div>
                </div>
                <div class="odoo-form-group">
                    <label class="odoo-form-label">Tracking No. <span class="text-danger">*</span></label>
                    <div class="flex-grow-1">
                        <input type="text" name="tracking_number" class="odoo-form-control" placeholder="e.g. TRK983742 or MH-12-XX-XXXX" required>
                    </div>
                </div>
                <div class="odoo-form-group mb-0">
                    <label class="odoo-form-label">Notes</label>
                    <div class="flex-grow-1">
                        <textarea name="notes" class="odoo-form-control" rows="3" placeholder="Remarks regarding dispatch…"></textarea>
                    </div>
                </div>
            </div>
        </x-ui.modal>
    @endif

@endsection


@push('scripts')
    <script>
        /**
         * Called when the Warehouse dropdown changes on the main table row.
         */
        function changeWarehouse(itemId, select) {
            const warehouseId = select.value;
            const url = `/sales/deliveries/items/${itemId}/warehouse`;

            $.ajax({
                url:    url,
                method: 'POST',
                data:   { _token: '{{ csrf_token() }}', warehouse_id: warehouseId },
                success: function (response) {
                    if (!response.success) return;
                    const avail = parseInt(response.available_qty) || 0;
                    $(`#available-qty-${itemId}`).text(avail);

                    const $reserveSelect = $(`#reserve-warehouse-${itemId}`);
                    if ($reserveSelect.length) $reserveSelect.val(warehouseId);

                    updateReserveAvailableFromQty(itemId, avail);
                },
                error: function (err) { console.error('Error updating warehouse stock: ', err); }
            });
        }

        /**
         * Called when warehouse changes inside the Reserve Stock modal.
         */
        function updateReserveAvailable(itemId, select) {
            const selectedOption = select.options[select.selectedIndex];
            const avail = parseInt(selectedOption.getAttribute('data-avail')) || 0;

            const $mainSelect = $(`#warehouse-select-${itemId}`);
            if ($mainSelect.length) $mainSelect.val(select.value);

            updateReserveAvailableFromQty(itemId, avail);

            $.ajax({
                url:    `/sales/deliveries/items/${itemId}/warehouse`,
                method: 'POST',
                data:   { _token: '{{ csrf_token() }}', warehouse_id: select.value },
                success: function (response) {
                    if (response.success) {
                        const serverAvail = parseInt(response.available_qty) || 0;
                        $(`#available-qty-${itemId}`).text(serverAvail);
                        updateReserveAvailableFromQty(itemId, serverAvail);
                    }
                }
            });
        }

        /**
         * Shared helper — updates available label + max of qty input in reserve modal.
         */
        function updateReserveAvailableFromQty(itemId, avail) {
            $(`#reserve-modal-avail-${itemId}`).text(avail);
            $(`#available-qty-${itemId}`).text(avail);

            const ordered = parseInt($(`#reserve-modal-avail-${itemId}`).closest('.modal').find('.fs-16.text-dark').first().text()) || 0;
            const $availCell = $(`#available-qty-${itemId}`);
            $availCell.removeClass('text-success text-danger');
            $availCell.addClass(avail >= ordered ? 'text-success' : 'text-danger');

            const $input = $(`#reserve-qty-input-${itemId}`);
            const pendingAttr = parseInt($input.data('pending')) || 0;
            const maxVal = Math.min(pendingAttr, avail);
            $input.attr('max', maxVal);
            $input.val(maxVal > 0 ? maxVal : '');
            $(`#reserve-max-label-${itemId}`).text(maxVal);
        }

        $(document).ready(function () {
            @foreach ($delivery->items as $item)
                @php
                    $orderedQty2 = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                    $pendingQty2 = max(0, $orderedQty2 - (float)$item->quantity_reserved);
                @endphp
                $('#reserve-qty-input-{{ $item->id }}').data('pending', {{ (int)$pendingQty2 }});
            @endforeach
        });
    </script>
@endpush
