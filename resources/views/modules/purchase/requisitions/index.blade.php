@extends('layouts.duralux')

@section('title', __('purchase.purchase_requests') . ' | SaaS ERP')
@section('page-title', __('purchase.purchase_requests'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.purchase_requests'))

@section('page-actions')
    <x-ui.button href="{{ route('purchase.requisitions.create') }}" variant="primary" icon="feather-plus">
        {{ __('purchase.create_purchase_request') }}
    </x-ui.button>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        <!-- Toast Notifications -->

        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('purchase.purchase_requests_listing') }}</h5>

            <div class="d-flex gap-2 ms-auto align-items-center flex-wrap">
                <!-- Quick Search (HRMS Common Component Style) -->
                <form method="GET" action="{{ route('purchase.requisitions.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="{{ __('purchase.search_req_placeholder') }}" 
                        value="{{ request('search') }}"
                        style="box-shadow: none; height: 32px; width: 220px;"
                    >
                </form>

                <!-- Sort -->
                <x-ui.sort-dropdown :label="__('crm.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requisition_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_date_latest') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requisition_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_date_oldest') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requisition_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_req_asc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requisition_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_req_desc') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter -->
                <form method="GET" action="{{ route('purchase.requisitions.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_req_placeholder') }}" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('purchase.all_statuses') }}</option>
                                <option value="Draft"    @selected(request('status') === 'Draft')>{{ __('purchase.status_draft') }}</option>
                                <option value="Approved" @selected(request('status') === 'Approved')>{{ __('purchase.status_approved') }}</option>
                                <option value="Cancelled" @selected(request('status') === 'Cancelled')>{{ __('purchase.status_cancelled') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.source_type') }}</label>
                            <x-ui.odoo-form-ui type="select" name="source_type">
                                <option value="">{{ __('purchase.all_sources') }}</option>
                                <option value="direct"               @selected(request('source_type') === 'direct')>{{ __('purchase.source_direct') }}</option>
                                <option value="so"                   @selected(request('source_type') === 'so')>{{ __('purchase.source_so') }}</option>
                                <option value="mo"                   @selected(request('source_type') === 'mo')>{{ __('purchase.source_mo') }}</option>
                                <option value="material_request"     @selected(request('source_type') === 'material_request')>{{ __('purchase.source_material_request') }}</option>
                                <option value="material_requirement" @selected(request('source_type') === 'material_requirement')>{{ __('purchase.source_material_requirement') }}</option>
                                <option value="requisition_slip"     @selected(request('source_type') === 'requisition_slip')>{{ __('purchase.source_requisition_slip') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.requisitions.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Requisitions List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="prTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input select-all">
                        </th>
                        <th style="width: 15%">{{ __('purchase.requisition_number') }}</th>
                        <th style="width: 15%">{{ __('purchase.requested_by') }}</th>
                        <th style="width: 13%">{{ __('purchase.requisition_date') }}</th>
                        <th style="width: 15%">{{ __('purchase.source_type') }}</th>
                        <th style="width: 17%">{{ __('purchase.source') }}</th>
                        <th style="width: 10%">{{ __('purchase.status') }}</th>
                        <th style="width: 12%" class="text-end">{{ __('purchase.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requisitions as $req)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-checkbox" value="{{ $req->id }}">
                            </td>
                            <td class="fw-bold">
                                <a href="{{ route('purchase.requisitions.show', $req->id) }}" class="text-primary text-decoration-none">
                                    {{ $req->requisition_number }}
                                </a>
                            </td>
                            <td>{{ $req->requester->name ?? __('purchase.system') }}</td>
                            <td>{{ $req->requisition_date ? $req->requisition_date->format('d-m-Y') : '—' }}</td>
                            <td>
                                @php
                                    $sourceBadge = 'secondary';
                                    if($req->source_type === 'mo') $sourceBadge = 'warning';
                                    elseif($req->source_type === 'material_request') $sourceBadge = 'info';
                                    elseif($req->source_type === 'material_requirement') $sourceBadge = 'success';
                                    elseif($req->source_type === 'so') $sourceBadge = 'danger';
                                @endphp
                                <x-ui.badge :soft="true" :variant="$sourceBadge" class="fs-10 text-uppercase">
                                    {{ __('purchase.source_' . $req->source_type) }}
                                </x-ui.badge>
                            </td>
                            <td>
                                @if($req->source_type === 'mo' && $req->sourceable)
                                    <a href="{{ route('production.orders.show', $req->source_id) }}" class="text-primary fw-medium">{{ $req->sourceable->order_number }}</a>
                                @elseif($req->source_type === 'material_request' && $req->sourceable)
                                    <a href="{{ route('sales.material-requests.show', $req->source_id) }}" class="text-primary fw-medium">{{ $req->sourceable->requisition_number }}</a>
                                @elseif($req->source_type === 'material_requirement' && $req->sourceable)
                                    <a href="{{ route('sales.material-requirements.show', $req->source_id) }}" class="text-primary fw-medium">{{ $req->sourceable->requirement_number }}</a>
                                @elseif($req->source_type === 'so' && $req->sourceable)
                                    <a href="{{ route('sales.orders.show', $req->source_id) }}" class="text-primary fw-medium">{{ $req->sourceable->sales_order_number }}</a>
                                @elseif($req->source_type === 'requisition_slip')
                                    <span class="text-muted font-monospace">{{ $req->requisition_slip_number ?: '—' }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusClass = 'warning';
                                    if ($req->status === 'Approved') $statusClass = 'success';
                                    elseif ($req->status === 'Cancelled') $statusClass = 'danger';
                                @endphp
                                <x-ui.badge :soft="true" :variant="$statusClass">
                                    {{ __('purchase.status_' . strtolower($req->status)) }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                {{-- View → show page --}}
                                <x-ui.action-dropdown :viewUrl="route('purchase.requisitions.show', $req->id)" id="reqActions-{{ $req->id }}">
                                    @if($req->status === 'Draft')
                                        <li>
                                            <a class="dropdown-item py-2" href="{{ route('purchase.requisitions.edit', $req->id) }}">
                                                <i class="feather-edit me-1.5 text-muted"></i> {{ __('purchase.edit') }}
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('purchase.requisitions.destroy', $req->id) }}" method="POST" id="deletePrListForm_{{ $req->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction({ title: 'Delete Requisition', message: '{{ __('purchase.confirm_delete') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deletePrListForm_{{ $req->id }}').submit(); })">
                                                    <i class="feather-trash-2 me-1.5"></i> {{ __('purchase.delete') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted fs-14">
                                <i class="feather-truck fs-24 mb-1.5 d-block opacity-50"></i>
                                {{ __('purchase.no_purchase_requests') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Links -->
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$requisitions->currentPage()"
                :totalPages="$requisitions->lastPage()"
                :totalResults="$requisitions->total()"
                :perPage="$requisitions->perPage()" />
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            $('.select-all').on('change', function () {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
            });
        });
    </script>
@endpush
