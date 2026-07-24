@extends('layouts.duralux')

@section('title', __('production.wip_details') . ' | SaaS ERP')
@section('page-title', __('production.wip_details') . ' - WIP-#' . str_pad($wip->id, 5, '0', STR_PAD_LEFT))
@section('breadcrumb', __('production.wip_details'))

@push('styles')
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
        }

        select.odoo-form-control {
            height: 30px !important;
            border-bottom: 1px solid #ced4da !important;
            background: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") no-repeat right .25rem center/8px 10px !important;
            padding-right: 1.25rem !important;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            cursor: pointer;
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('production.wip.index') }}" variant="light" class="border px-3"
            icon="feather-arrow-left">
            {{ __('production.back_to_list') }}
        </x-ui.button>
        @if($wip->status !== 'completed' && $wip->available_quantity > 0)
            <x-ui.button variant="warning" data-bs-toggle="modal" data-bs-target="#transferWipModal" icon="feather-git-commit">
                {{ __('production.transfer') }}
            </x-ui.button>
        @endif
        <x-ui.button variant="light" class="border" data-bs-toggle="modal" data-bs-target="#adjustWipModal"
            icon="feather-sliders">
            {{ __('production.adjust') }}
        </x-ui.button>
        @if($wip->status === 'completed')
            <x-ui.button variant="primary" data-bs-toggle="modal" data-bs-target="#convertToFgModal"
                icon="feather-check-circle">
                {{ __('production.convert') }}
            </x-ui.button>
        @endif
    </div>
@endsection

@section('content')
    <div class="erp-single-panel">
        <!-- Error & Success alerts -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <div class="row g-4 text-dark fs-13 mb-4">
            {{-- WIP Info Card --}}
            <div class="col-md-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-bottom pt-4 pb-3">
                        <h5 class="fw-bold mb-0"><i class="feather-info text-primary me-2"></i> WIP Specification</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <span class="text-muted d-block fs-11 uppercase font-semibold">Production Order</span>
                                <a href="{{ route('production.orders.show', $wip->production_order_id) }}"
                                    class="fw-bold text-primary">
                                    {{ $wip->order->order_number ?? 'Order #' . $wip->production_order_id }}
                                </a>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11 uppercase font-semibold">Batch / Lot Reference</span>
                                <span
                                    class="fw-semibold text-dark">{{ $wip->batch ? $wip->batch->batch_number : 'Direct Order Lot' }}</span>
                            </div>
                            <div class="col-12 pt-2 border-top">
                                <span class="text-muted d-block fs-11 uppercase font-semibold">Product Definition</span>
                                <span class="fw-bold text-dark">{{ $wip->product->name }}</span>
                                <small class="text-muted font-monospace d-block fs-10">{{ $wip->product->sku }}</small>
                            </div>
                            <div class="col-6 pt-2 border-top">
                                <span class="text-muted d-block fs-11 uppercase font-semibold">Current Process Stage</span>
                                <span class="badge bg-soft-primary text-primary px-3 py-1 font-monospace mt-1">
                                    @if($wip->status === 'completed')
                                        Finished Goods (Ready)
                                    @else
                                        {{ $wip->currentRoutingOperation ? $wip->currentRoutingOperation->name : 'Finished Goods conversion' }}
                                    @endif
                                </span>
                            </div>
                            <div class="col-6 pt-2 border-top">
                                <span class="text-muted d-block fs-11 uppercase font-semibold">Current Work Center</span>
                                <span class="fw-semibold text-dark">
                                    @if($wip->status === 'completed')
                                        Warehouse Inflow / Stock
                                    @else
                                        {{ $wip->currentWorkCenter ? $wip->currentWorkCenter->name : 'N/A' }}
                                    @endif
                                </span>
                            </div>
                            <div class="col-6 pt-2 border-top">
                                <span class="text-muted d-block fs-11 uppercase font-semibold">Available Qty</span>
                                <span
                                    class="fw-bold text-dark fs-16">{{ number_format($wip->available_quantity, 2) }}</span>
                            </div>
                            <div class="col-6 pt-2 border-top">
                                <span class="text-muted d-block fs-11 uppercase font-semibold">Completed Qty</span>
                                <span
                                    class="fw-bold text-success fs-16">{{ number_format($wip->completed_quantity, 2) }}</span>
                            </div>
                            <div class="col-6 pt-2 border-top">
                                <span class="text-muted d-block fs-11 uppercase font-semibold">Rejects & Scrap</span>
                                <span class="fw-bold text-danger">{{ number_format($wip->rejected_quantity, 2) }}
                                    Rejects</span> |
                                <span class="fw-bold text-muted">{{ number_format($wip->scrap_quantity, 2) }} Scrap</span>
                            </div>
                            <div class="col-6 pt-2 border-top">
                                <span class="text-muted d-block fs-11 uppercase font-semibold">WIP Status</span>
                                @if($wip->status === 'active')
                                    <span class="badge bg-soft-success text-success text-uppercase">Active</span>
                                @elseif($wip->status === 'quality_hold')
                                    <span class="badge bg-soft-warning text-warning text-uppercase">Quality Hold</span>
                                @elseif($wip->status === 'rework')
                                    <span class="badge bg-soft-danger text-danger text-uppercase">Rework</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary text-uppercase">Completed</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- WIP Cost Value Breakdown --}}
            <div class="col-md-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-bottom pt-4 pb-3">
                        <h5 class="fw-bold mb-0"><i class="feather-pie-chart text-success me-2"></i>
                            {{ __('production.wip_valuation_breakdown') }}</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 pt-0">
                                <div>
                                    <h6 class="mb-0 fw-semibold text-dark">{{ __('production.material_cost') }}</h6>
                                    <small class="text-muted">Issued component materials cost</small>
                                </div>
                                <span class="fw-bold text-dark">{{ format_currency($wip->material_cost) }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <h6 class="mb-0 fw-semibold text-dark">{{ __('production.labor_cost') }}</h6>
                                    <small class="text-muted">Routing labor setup & runtime</small>
                                </div>
                                <span class="fw-bold text-dark">{{ format_currency($wip->labor_cost) }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <h6 class="mb-0 fw-semibold text-dark">{{ __('production.machine_cost') }}</h6>
                                    <small class="text-muted">Routing machine operation costs</small>
                                </div>
                                <span class="fw-bold text-dark">{{ format_currency($wip->machine_cost) }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <h6 class="mb-0 fw-semibold text-dark">{{ __('production.overhead_cost') }}</h6>
                                    <small class="text-muted">Work center overhead rate</small>
                                </div>
                                <span class="fw-bold text-dark">{{ format_currency($wip->overhead_cost) }}</span>
                            </li>
                            <li
                                class="list-group-item d-flex justify-content-between align-items-center px-0 pb-0 border-0 bg-soft-primary rounded p-3 mt-3">
                                <div>
                                    <h5 class="mb-0 fw-bold text-primary">{{ __('production.total_value') }}</h5>
                                    <small class="text-primary-emphasis">Total accrued manufacturing value</small>
                                </div>
                                <span class="fs-18 fw-bold text-primary">{{ format_currency($wip->total_value) }}</span>
                            </li>

                        </ul>
                    </div>
                </div>
            </div>

            {{-- WIP Transactions History Logs --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-bottom pt-4 pb-3">
                        <h5 class="fw-bold mb-0"><i class="feather-activity text-info me-2"></i>
                            {{ __('production.transaction_history') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('production.transaction_at') }}</th>
                                        <th>{{ __('production.transaction_type') }}</th>
                                        <th>{{ __('production.from_stage') }}</th>
                                        <th>{{ __('production.to_stage') }}</th>
                                        <th class="text-end">{{ __('production.quantity') }}</th>
                                        <th class="text-end">{{ __('production.cost_added') }}</th>
                                        <th>{{ __('production.remarks') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($wip->transactions as $tx)
                                                    <tr>
                                                        <td class="text-muted font-monospace">
                                                            {{ $tx->transaction_at->format('d/m/Y H:i:s') }}</td>
                                                        <td>
                                                            @php
                                                                $badgeClass = match ($tx->transaction_type) {
                                                                    'created' => 'bg-soft-primary text-primary',
                                                                    'operation_started' => 'bg-soft-info text-info',
                                                                    'operation_completed' => 'bg-soft-success text-success',
                                                                    'transferred' => 'bg-soft-warning text-warning',
                                                                    'converted_to_finished_goods' => 'bg-success text-white',
                                                                    default => 'bg-soft-secondary text-secondary'
                                                                };
                                                            @endphp
                                        <span
                                                                class="badge {{ $badgeClass }}">{{ strtoupper(str_replace('_', ' ', $tx->transaction_type)) }}</span>
                                                        </td>
                                                        <td>
                                                            <span
                                                                class="font-monospace text-muted">{{ $tx->fromOperation ? $tx->fromOperation->name : '—' }}</span>
                                                        </td>
                                                        <td>
                                                            <span
                                                                class="font-monospace text-dark">{{ $tx->toOperation ? $tx->toOperation->name : '—' }}</span>
                                                        </td>
                                                        <td class="text-end fw-semibold">
                                                            {{ number_format($tx->quantity, 2) }}
                                                            @if($tx->rejected_quantity > 0 || $tx->scrap_quantity > 0)
                                                                <div class="fs-10 text-danger mt-1">
                                                                    @if($tx->rejected_quantity > 0)
                                                                        <div><i class="feather-alert-triangle"></i>
                                                                            {{ number_format($tx->rejected_quantity, 2) }} Rej</div>
                                                                    @endif
                                                                    @if($tx->scrap_quantity > 0)
                                                                        <div><i class="feather-trash-2"></i>
                                                                            {{ number_format($tx->scrap_quantity, 2) }} Scr</div>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td class="text-end text-success fw-semibold">
                                                             @if($tx->cost_added > 0)
                                                                 +{{ format_currency($tx->cost_added) }}
                                                             @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <span class="text-muted">{{ $tx->remarks ?: 'No remarks' }}</span>
                                                            @if($tx->operator)
                                                                <div class="fs-10 text-muted-2">Logged by: {{ $tx->operator->name }}</div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                No transactions registered for this WIP tracking card yet.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Transfer WIP Modal --}}
    @if($wip->status !== 'completed' && $wip->available_quantity > 0)
        <x-ui.modal id="transferWipModal" title="{{ __('production.transfer_wip') }}"
            formAction="{{ route('production.wip.transfer', $wip->id) }}" submitText="Transfer WIP" closeText="Cancel">
            <input type="hidden" name="from_operation_id" value="{{ $wip->current_routing_operation_id }}">
            <x-ui.odoo-form-ui type="input" label="From Stage" name="from_stage_dummy" :value="$wip->currentRoutingOperation ? $wip->currentRoutingOperation->name : 'Start'" readonly />

            <x-ui.odoo-form-ui type="select" label="To Stage" name="to_operation_id" :searchable="false" required>
                @if($wip->order)
                    @foreach($wip->order->operations as $op)
                        @if($op->routing_operation_id !== $wip->current_routing_operation_id)
                            <option value="{{ $op->routing_operation_id }}">{{ $op->name }} (Seq: {{ $op->sequence }})</option>
                        @endif
                    @endforeach
                @endif
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="input" inputType="number" label="Qty to Move" name="quantity" step="0.0001"
                :value="$wip->available_quantity" :max="$wip->available_quantity" :helperText="'Maximum transfer quantity is ' . number_format($wip->available_quantity, 2) . ' units.'" required />

            <x-ui.odoo-form-ui type="input" label="Remarks" name="remarks" placeholder="Optional comments..." />
        </x-ui.modal>
    @endif

    {{-- Adjust WIP Modal --}}
    <x-ui.modal id="adjustWipModal" title="{{ __('production.adjust_wip') }}"
        formAction="{{ route('production.wip.adjust', $wip->id) }}" submitText="Apply Adjustment" closeText="Cancel">
        <x-ui.odoo-form-ui type="input" label="Current Total Qty" name="current_quantity_dummy"
            :value="number_format($wip->quantity, 2)" readonly />

        <x-ui.odoo-form-ui type="input" inputType="number" label="New Total Qty (Good + Scrap + Rejects)" name="quantity"
            step="0.0001" :value="$wip->quantity" required />

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label text-muted fw-semibold fs-12">Scrap Quantity</label>
                <input type="number" name="scrap_quantity" class="form-control form-control-sm"
                    value="{{ $wip->scrap_quantity }}" min="0" step="any" required>
            </div>
            <div class="col-6">
                <label class="form-label text-muted fw-semibold fs-12">Rejects Quantity</label>
                <input type="number" name="rejected_quantity" class="form-control form-control-sm"
                    value="{{ $wip->rejected_quantity }}" min="0" step="any" required>
            </div>
        </div>

        <x-ui.odoo-form-ui type="input" label="Reason" name="reason" placeholder="Why are you adjusting this?" required />
    </x-ui.modal>

    {{-- Convert WIP Modal --}}
    @if($wip->status === 'completed' || $wip->available_quantity > 0)
        <x-ui.modal id="convertToFgModal" title="{{ __('production.convert_to_fg') }}"
            formAction="{{ route('production.wip.convert', $wip->id) }}" submitText="Complete FG Inflow" closeText="Cancel">
            <x-ui.odoo-form-ui type="input" label="Convert Qty" name="convert_qty_dummy"
                :value="number_format($wip->available_quantity, 2) . ' units'" readonly />


            <x-ui.odoo-form-ui type="select" label="Warehouse" name="warehouse_id" :searchable="false" required>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->name }} {{ $w->is_default ? '(Default)' : '' }}</option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="input" label="Remarks" name="remarks" placeholder="Comments..." />
        </x-ui.modal>
    @endif
@endsection