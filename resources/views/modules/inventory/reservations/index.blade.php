@extends('layouts.duralux')

@section('title', __('inventory.stock_reservations') . ' | SaaS ERP')
@section('page-title', __('inventory.stock_reservations'))
@section('breadcrumb', __('inventory.inventory_stock_reservations'))

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
                    <h5 class="fw-bold text-dark mb-0 me-2">{{ __('inventory.stock_reservations_listing') }}</h5>
                    <x-ui.button href="{{ request()->fullUrlWithQuery(['status' => null]) }}" variant="{{ !request('status') ? 'primary' : 'light' }}" class="{{ !request('status') ? '' : 'text-muted border' }}">
                        {{ __('inventory.all_reservations') }}
                    </x-ui.button>
                    <x-ui.button href="{{ request()->fullUrlWithQuery(['status' => 'Active']) }}" variant="{{ request('status') === 'Active' ? 'primary' : 'light' }}" class="{{ request('status') === 'Active' ? '' : 'text-muted border' }}">
                        {{ __('inventory.active') }}
                    </x-ui.button>
                    <x-ui.button href="{{ request()->fullUrlWithQuery(['status' => 'Completed']) }}" variant="{{ request('status') === 'Completed' ? 'primary' : 'light' }}" class="{{ request('status') === 'Completed' ? '' : 'text-muted border' }}">
                        {{ __('inventory.released') }}
                    </x-ui.button>
                    <x-ui.button href="{{ request()->fullUrlWithQuery(['status' => 'Expired']) }}" variant="{{ request('status') === 'Expired' ? 'primary' : 'light' }}" class="{{ request('status') === 'Expired' ? '' : 'text-muted border' }}">
                        {{ __('inventory.expired') }}
                    </x-ui.button>
                </div>

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Quick Search (HRMS Common Component Style) -->
                    <form method="GET" action="{{ route('inventory.reservations.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                        @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                        
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control border-0 bg-transparent p-0 fs-13" 
                            placeholder="{{ __('inventory.search_product_sku_placeholder') }}" 
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
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'reserved_qty', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'reserved_qty' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('inventory.highest_reserved_qty') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- System Filter Component -->
                    <form method="GET" action="{{ route('inventory.reservations.index') }}" class="d-inline">
                        <x-ui.filter :label="__('inventory.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('inventory.filter_reservations') }}</h6>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('inventory.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('inventory.all_statuses') }}</option>
                                    <option value="Active" {{ request('status') === 'Active' ? 'selected' : '' }}>{{ __('inventory.active_reservations') }}</option>
                                    <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>{{ __('inventory.completed_released') }}</option>
                                    <option value="Expired" {{ request('status') === 'Expired' ? 'selected' : '' }}>{{ __('inventory.expired') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('inventory.reservations.index') }}" class="btn btn-sm btn-light border">{{ __('inventory.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('inventory.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>
            </div>

            <!-- Reservations Table -->
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" id="stockReservationsTable">
                    <thead class="table-light bg-light">
                        <tr>
                            <th style="width: 3%" class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </th>
                            <th>{{ __('inventory.product_name') }}</th>
                            <th>{{ __('inventory.warehouse') }}</th>
                            <th class="text-end">{{ __('inventory.reserved_qty') }}</th>
                            <th>{{ __('inventory.reference_doc') }}</th>
                            <th>{{ __('inventory.expires_at') }}</th>
                            <th class="text-center">{{ __('inventory.status') }}</th>
                            <th class="text-end pe-4">{{ __('inventory.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @forelse($reservations as $res)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input">
                                </td>
                                <td>
                                    <strong class="text-dark d-block fs-13">{{ $res->product->name ?? 'N/A' }}</strong>
                                    <small class="text-muted font-monospace fs-11">SKU: {{ $res->product->sku ?? '—' }}</small>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark fs-12">
                                        <i class="feather-map-pin text-muted me-1 fs-11"></i>{{ $res->warehouse->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-end font-monospace">
                                    @if($res->reserved_qty > 0)
                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-1 fw-bold fs-11">
                                            {{ number_format($res->reserved_qty, 2) }}
                                        </span>
                                    @else
                                        <span class="text-muted fs-12">0.00</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark font-monospace border fs-11">
                                        {{ $res->reference_type }} #{{ $res->reference_id }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted fs-12">
                                        <i class="feather-clock me-1 text-muted fs-11"></i>{{ $res->expires_at ? \Carbon\Carbon::parse($res->expires_at)->format('d M Y, h:i A') : __('inventory.no_expiry') }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <x-ui.status-badge :status="$res->status" size="sm" />
                                </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown>
                                    @if($res->status === 'Active')
                                        <li>
                                            <form action="{{ route('inventory.reservations.release', $res->id) }}" method="POST" id="releaseForm_{{ $res->id }}">
                                                @csrf
                                                <button type="button" class="dropdown-item text-danger fw-semibold" onclick="if(confirm('{{ __('inventory.confirm_release_reservation') }}')) document.getElementById('releaseForm_{{ $res->id }}').submit();">
                                                    <i class="feather-unlock me-2 text-danger fs-12"></i>{{ __('inventory.release_stock') }}
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
                                <i class="feather-lock fs-1 d-block mb-3 text-light"></i>
                                {{ __('inventory.no_stock_reservations_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Section (Matching Lead Module Component) -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$reservations->currentPage()" 
                :totalPages="$reservations->lastPage()" 
                :totalResults="$reservations->total()" 
                :perPage="$reservations->perPage()" />
        </div>
    </x-ui.odoo-form-ui>
</div>
@endsection
