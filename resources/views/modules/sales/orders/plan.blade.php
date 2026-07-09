@extends('layouts.duralux')

@section('title', 'Procurement & Replenishment Planning | SaaS ERP')
@section('page-title', 'Replenishment Planning')
@section('breadcrumb', 'Sales / Orders / Planning')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <div class="avatar-text avatar-md bg-danger text-white me-3">
                        <i class="feather-alert-triangle"></i>
                    </div>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                        <ul class="fs-12 mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form action="{{ route('sales.orders.processPlan', $order->id) }}" method="POST" id="planningForm">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Replenishment Planning Strategy</h5>
                        <span class="fs-12 text-muted">Sales Order: <strong class="text-primary">{{ $order->sales_order_number }}</strong></span>
                    </div>
                    <a href="{{ route('sales.orders.show', $order->id) }}" class="btn btn-sm btn-light border">Cancel & Skip</a>
                </div>

                <div class="alert alert-soft-primary border-0 shadow-sm d-flex align-items-center mb-4 py-2.5 px-3 fs-13 text-primary">
                    <i class="feather-info me-2 fw-bold"></i>
                    Select a source warehouse to reserve stock. If there is a shortage, choose whether to route it to a Purchase Requisition (PR) or a Manufacturing Order (MO).
                </div>

                <!-- Planning Items Table -->
                <div class="table-responsive mt-3">
                    <table class="table odoo-table align-middle bg-white rounded border fs-13 text-dark">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-3" style="width: 25%;">Product Details</th>
                                <th class="text-end" style="width: 10%;">Ordered Qty</th>
                                <th style="width: 25%;">Warehouse Select</th>
                                <th class="text-end" style="width: 12%;">Available Stock</th>
                                <th class="text-end" style="width: 10%;">Shortage Qty</th>
                                <th class="text-center" style="width: 18%;">Fulfillment Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($planningItems as $index => $pItem)
                                <tr class="planning-row" data-index="{{ $index }}">
                                    <td class="ps-3">
                                        <strong class="text-dark">{{ $pItem['item']->product?->name }}</strong>
                                        <span class="text-muted d-block fs-11 mt-0.5">SKU: {{ $pItem['item']->product?->sku }}</span>
                                        <input type="hidden" name="items[{{ $index }}][sales_order_item_id]" value="{{ $pItem['item']->id }}">
                                        <input type="hidden" id="default-strategy-{{ $index }}" value="{{ $pItem['item']->product?->planning_type ?: 'purchase' }}">
                                        <input type="hidden" id="ordered-qty-{{ $index }}" value="{{ $pItem['ordered'] }}">
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold fs-14 text-dark">{{ (int)$pItem['ordered'] }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $savedAlloc = $pItem['saved_allocations']->first();
                                            $selectedWhId = $savedAlloc ? $savedAlloc->warehouse_id : ($warehouses->first()?->id ?? null);
                                        @endphp
                                        <select name="items[{{ $index }}][warehouse_id]" class="form-select form-select-sm warehouse-select" style="max-width: 240px;" required>
                                            <option value="">Select Warehouse...</option>
                                            @foreach ($warehouses as $wh)
                                                @php
                                                    $whStockInfo = $pItem['warehouse_stocks'][$wh->id] ?? null;
                                                    $whAvail = $whStockInfo ? $whStockInfo['available'] : 0;
                                                @endphp
                                                <option value="{{ $wh->id }}" data-avail="{{ $whAvail }}" {{ $selectedWhId == $wh->id ? 'selected' : '' }}>
                                                    {{ $wh->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-end">
                                        <span id="avail-display-{{ $index }}" class="fw-semibold text-muted">0</span>
                                    </td>
                                    <td class="text-end">
                                        <span id="shortage-display-{{ $index }}" class="fw-bold fs-14 text-danger shortage-display">0</span>
                                    </td>
                                    <td>
                                        @php
                                            // Check if already planned
                                            $hasMo = $pItem['existing_mo'];
                                            $hasPr = $pItem['existing_pr_item'];
                                            $defaultAction = 'None';
                                            if ($pItem['item']->product?->planning_type === 'manufacture') {
                                                $defaultAction = 'Manufacture';
                                            } elseif ($pItem['item']->product?->planning_type === 'purchase') {
                                                $defaultAction = 'Purchase';
                                            }
                                        @endphp
                                        @if ($hasMo)
                                            <div class="text-center">
                                                <span class="badge bg-soft-success text-success px-2 py-1 fs-11">
                                                    MO: {{ $hasMo->order_number }}
                                                </span>
                                                <input type="hidden" name="items[{{ $index }}][action]" value="None">
                                            </div>
                                        @elseif ($hasPr)
                                            <div class="text-center">
                                                <span class="badge bg-soft-info text-info px-2 py-1 fs-11">
                                                    PR: {{ $hasPr->requisition->requisition_number }}
                                                </span>
                                                <input type="hidden" name="items[{{ $index }}][action]" value="None">
                                            </div>
                                        @else
                                            <select name="items[{{ $index }}][action]" id="action-select-{{ $index }}" class="form-select form-select-sm action-select mx-auto" style="max-width: 140px;">
                                                <option value="None" {{ $defaultAction === 'None' ? 'selected' : '' }}>None (Stock)</option>
                                                <option value="Purchase" {{ $defaultAction === 'Purchase' ? 'selected' : '' }}>Purchase (PR)</option>
                                                <option value="Manufacture" {{ $defaultAction === 'Manufacture' ? 'selected' : '' }}>Manufacture (MO)</option>
                                            </select>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No tangible products found on this Sales Order.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('sales.orders.show', $order->id) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">Discard</a>
                    <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12" style="background-color: #1e40af; border-color: #1e40af;">
                        <i class="feather-activity me-1.5"></i>Execute Replenishment Plan
                    </button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Function to update calculations dynamically per line
            function updateLineCalculations(row) {
                const idx = row.data('index');
                const ordered = parseFloat($('#ordered-qty-' + idx).val()) || 0;
                
                // Get selected option details
                const selectOpt = row.find('.warehouse-select option:selected');
                const avail = parseFloat(selectOpt.data('avail')) || 0;
                
                // Display available stock
                row.find('#avail-display-' + idx).text(Math.round(avail));
                
                // Calculate shortage
                const shortage = Math.max(0, ordered - avail);
                row.find('#shortage-display-' + idx).text(Math.round(shortage));
                
                // Configure action select dropdown
                const actionSelect = row.find('#action-select-' + idx);
                if (actionSelect.length > 0) {
                    if (shortage <= 0) {
                        actionSelect.val('None');
                        actionSelect.attr('disabled', true);
                    } else {
                        actionSelect.attr('disabled', false);
                        // Default strategy if action is currently None
                        if (actionSelect.val() === 'None') {
                            const defaultStrategy = $('#default-strategy-' + idx).val();
                            if (defaultStrategy === 'manufacture') {
                                actionSelect.val('Manufacture');
                            } else if (defaultStrategy === 'purchase') {
                                actionSelect.val('Purchase');
                            } else {
                                actionSelect.val('Purchase');
                            }
                        }
                    }
                }
            }

            // Run initial calculations for all lines
            $('.planning-row').each(function() {
                updateLineCalculations($(this));
            });

            // Handle warehouse dropdown selection changes
            $(document).on('change', '.warehouse-select', function() {
                const row = $(this).closest('.planning-row');
                updateLineCalculations(row);
            });

            // Ensure disabled dropdowns still post their values on submit
            $('#planningForm').on('submit', function() {
                $('.action-select:disabled').each(function() {
                    $(this).attr('disabled', false);
                });
            });
        });
    </script>
@endpush
