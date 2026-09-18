@extends('layouts.duralux')

@section('title', __('inventory.stock_transfers') . ' | SaaS ERP')
@section('page-title', __('inventory.stock_transfers'))
@section('breadcrumb', __('inventory.inventory_stock_transfers'))

@section('page-actions')
    <x-ui.button href="{{ route('inventory.transfers.create') }}" variant="primary" icon="feather-plus">
        {{ __('inventory.new_transfer') }}
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel text-dark">
        <x-ui.odoo-form-ui type="sheet">

            <!-- Toolbar: Tabs, Sort & Filters -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 pb-3 border-bottom">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold text-dark mb-0 me-2">{{ __('inventory.stock_transfers_listing') }}</h5>
                    <x-ui.button href="{{ request()->fullUrlWithQuery(['status' => null]) }}" variant="{{ !request('status') ? 'primary' : 'light' }}" class="{{ !request('status') ? '' : 'text-muted border' }}">
                        {{ __('inventory.all_transfers') }}
                    </x-ui.button>
                    <x-ui.button href="{{ request()->fullUrlWithQuery(['status' => 'Draft']) }}" variant="{{ request('status') === 'Draft' ? 'primary' : 'light' }}" class="{{ request('status') === 'Draft' ? '' : 'text-muted border' }}">
                        {{ __('inventory.draft') }}
                    </x-ui.button>
                    <x-ui.button href="{{ request()->fullUrlWithQuery(['status' => 'In Transit']) }}" variant="{{ request('status') === 'In Transit' ? 'primary' : 'light' }}" class="{{ request('status') === 'In Transit' ? '' : 'text-muted border' }}">
                        {{ __('inventory.in_transit') }}
                    </x-ui.button>
                    <x-ui.button href="{{ request()->fullUrlWithQuery(['status' => 'Completed']) }}" variant="{{ request('status') === 'Completed' ? 'primary' : 'light' }}" class="{{ request('status') === 'Completed' ? '' : 'text-muted border' }}">
                        {{ __('inventory.completed') }}
                    </x-ui.button>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Quick Search (HRMS Common Component Style) -->
                    <form method="GET" action="{{ route('inventory.transfers.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                        @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                        @if(request('warehouse_id')) <input type="hidden" name="warehouse_id" value="{{ request('warehouse_id') }}"> @endif
                        
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control border-0 bg-transparent p-0 fs-13" 
                            placeholder="{{ __('inventory.search_transfer_placeholder') }}" 
                            value="{{ request('search') }}"
                            style="box-shadow: none; height: 32px; width: 220px;"
                        >
                    </form>

                    <!-- Custom Sort Component -->
                    <x-ui.sort-dropdown :label="__('inventory.sort')">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('inventory.latest_created') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('inventory.oldest_created') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'transfer_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'transfer_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('inventory.transfer_number_az') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'transfer_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'transfer_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('inventory.transfer_date') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Custom Filter Component -->
                    <form method="GET" action="{{ route('inventory.transfers.index') }}" class="d-inline">
                        <x-ui.filter :label="__('inventory.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('inventory.filter_options') }}</h6>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('inventory.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('inventory.all_statuses') }}</option>
                                    <option value="Draft" {{ request('status') === 'Draft' ? 'selected' : '' }}>{{ __('inventory.draft') }}</option>
                                    <option value="In Transit" {{ request('status') === 'In Transit' ? 'selected' : '' }}>{{ __('inventory.in_transit') }}</option>
                                    <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>{{ __('inventory.completed') }}</option>
                                    <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>{{ __('inventory.cancelled') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('inventory.warehouse') }}</label>
                                <x-ui.odoo-form-ui type="select" name="warehouse_id">
                                    <option value="">{{ __('inventory.all_warehouses') }}</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('inventory.transfers.index') }}" class="btn btn-sm btn-light border">{{ __('inventory.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('inventory.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>
            </div>

            <!-- Stock Transfers Table -->
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" id="transfersTable">
                    <thead class="table-light bg-light">
                        <tr>
                            <th style="width: 3%" class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </th>
                            <th>{{ __('inventory.transfer_number') }}</th>
                            <th>{{ __('inventory.from_warehouse') }}</th>
                            <th>{{ __('inventory.to_warehouse') }}</th>
                            <th>{{ __('inventory.transfer_date') }}</th>
                            <th class="text-center">{{ __('inventory.status') }}</th>
                            <th>{{ __('inventory.created_by') }}</th>
                            <th class="text-end pe-4">{{ __('inventory.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @forelse ($transfers as $transfer)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input">
                                </td>
                                <td>
                                    @php
                                        $isSubcontractTransfer = ($transfer->toWarehouse?->type === 'subcontractor') || str_contains($transfer->notes ?? '', 'Subcontract') || str_contains($transfer->notes ?? '', 'MO-');
                                        $isWipDispatch = $isSubcontractTransfer && (str_contains($transfer->notes ?? '', 'WIP') || str_contains($transfer->notes ?? '', 'Op'));
                                    @endphp
                                    <a href="{{ route('inventory.transfers.show', $transfer->id) }}" class="fw-bold text-primary text-decoration-none">
                                        <i class="feather-box text-primary me-1 fs-12"></i>{{ $transfer->transfer_number }}
                                    </a>
                                    @if($isSubcontractTransfer)
                                        @if($isWipDispatch)
                                            <span class="badge bg-soft-warning text-dark border border-warning px-1.5 py-0.5 fs-10 fw-bold d-block mt-1">
                                                <i class="feather-truck me-1"></i>{{ __('inventory.subcontract_wip_dispatch') }}
                                            </span>
                                        @else
                                            <span class="badge bg-soft-info text-info border border-info px-1.5 py-0.5 fs-10 fw-bold d-block mt-1">
                                                <i class="feather-box me-1"></i>{{ __('inventory.subcontract_material_dispatch') }}
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark fs-12">
                                        <i class="feather-map-pin text-muted me-1 fs-11"></i>{{ $transfer->fromWarehouse?->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark fs-12">
                                        <i class="feather-map-pin text-muted me-1 fs-11"></i>{{ $transfer->toWarehouse?->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted fs-12">
                                        <i class="feather-calendar me-1 text-muted fs-11"></i>{{ $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') : 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <x-ui.status-badge :status="$transfer->status" size="sm" />
                                </td>
                            <td>
                                <span class="text-muted fs-12">{{ $transfer->creator?->name ?? 'Admin' }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown :viewUrl="route('inventory.transfers.show', $transfer->id)">
                                    @if($transfer->status === 'Draft')
                                        <li>
                                            <form action="{{ route('inventory.transfers.dispatch', $transfer->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-info fw-semibold">
                                                    <i class="feather-truck me-2 text-info fs-12"></i>{{ __('inventory.dispatch_transfer') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                    @if($transfer->status === 'In Transit')
                                        <li>
                                            <form action="{{ route('inventory.transfers.receive', $transfer->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success fw-semibold">
                                                    <i class="feather-check-circle me-2 text-success fs-12"></i>{{ __('inventory.receive_transfer') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                    @if(in_array($transfer->status, ['Draft', 'In Transit']))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('inventory.transfers.cancel', $transfer->id) }}" method="POST" id="cancelTransferForm_{{ $transfer->id }}">
                                                @csrf
                                                <button type="button" class="dropdown-item text-danger fw-semibold" onclick="if(confirm('{{ __('inventory.confirm_cancel_transfer') }}')) document.getElementById('cancelTransferForm_{{ $transfer->id }}').submit();">
                                                    <i class="feather-x-circle me-2 text-danger fs-12"></i>{{ __('inventory.cancel_transfer') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-box fs-1 d-block mb-3 text-light"></i>
                                {{ __('inventory.no_transfers_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Section -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$transfers->currentPage()" 
                :totalPages="$transfers->lastPage()" 
                :totalResults="$transfers->total()" 
                :perPage="$transfers->perPage()" />
        </div>
    </x-ui.odoo-form-ui>
</div>
@endsection
