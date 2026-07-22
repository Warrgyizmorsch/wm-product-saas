@extends('layouts.duralux')

@section('title', 'Purchase Requests | SaaS ERP')
@section('page-title', 'Purchase Requests')
@section('breadcrumb', 'Purchase / Requests')

@section('page-actions')
    <x-ui.button href="{{ route('purchase.requisitions.create') }}" variant="primary" icon="feather-plus">
        Create Purchase Request
    </x-ui.button>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        <!-- Toast Notifications -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Purchase Requests Listing</h5>
            
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown :label="__('crm.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requisition_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Date (Latest)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requisition_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Date (Oldest)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requisition_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Req Number (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requisition_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Req Number (Z-A)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('purchase.requisitions.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keyword</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search by Requisition No..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="Draft" @selected(request('status') === 'Draft')>Draft</option>
                                <option value="Approved" @selected(request('status') === 'Approved')>Approved</option>
                                <option value="Cancelled" @selected(request('status') === 'Cancelled')>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Source Type</label>
                            <x-ui.odoo-form-ui type="select" name="source_type">
                                <option value="">All Sources</option>
                                <option value="direct" @selected(request('source_type') === 'direct')>Direct / Manual</option>
                                <option value="so" @selected(request('source_type') === 'so')>Sales Order</option>
                                <option value="mo" @selected(request('source_type') === 'mo')>Manufacturing Order</option>
                                <option value="material_request" @selected(request('source_type') === 'material_request')>Material Request (Prod)</option>
                                <option value="material_requirement" @selected(request('source_type') === 'material_requirement')>Material Requirement</option>
                                <option value="requisition_slip" @selected(request('source_type') === 'requisition_slip')>Requisition Slip</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.requisitions.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
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
                        <th style="width: 15%">Req Number</th>
                        <th style="width: 15%">Requested By</th>
                        <th style="width: 15%">Req Date</th>
                        <th style="width: 15%">Source Type</th>
                        <th style="width: 17%">Source Reference</th>
                        <th style="width: 10%">Status</th>
                        <th style="width: 10%" class="text-end">Actions</th>
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
                            <td>{{ $req->requester->name ?? 'System' }}</td>
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
                                    {{ str_replace('_', ' ', $req->source_type) }}
                                </x-ui.badge>
                            </td>
                            <td>
                                @if($req->source_type === 'mo' && $req->sourceable)
                                    <a href="{{ route('production.orders.show', $req->source_id) }}" class="text-primary fw-medium">
                                        {{ $req->sourceable->order_number }}
                                    </a>
                                @elseif($req->source_type === 'material_request' && $req->sourceable)
                                    <a href="{{ route('sales.material-requests.show', $req->source_id) }}" class="text-primary fw-medium">
                                        {{ $req->sourceable->requisition_number }}
                                    </a>
                                @elseif($req->source_type === 'material_requirement' && $req->sourceable)
                                    <a href="{{ route('sales.material-requirements.show', $req->source_id) }}" class="text-primary fw-medium">
                                        {{ $req->sourceable->requirement_number }}
                                    </a>
                                @elseif($req->source_type === 'so' && $req->sourceable)
                                    <a href="{{ route('sales.orders.show', $req->source_id) }}" class="text-primary fw-medium">
                                        {{ $req->sourceable->sales_order_number }}
                                    </a>
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
                                    {{ $req->status }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown id="reqActions-{{ $req->id }}">
                                    <li>
                                        <a class="dropdown-item py-2" href="{{ route('purchase.requisitions.show', $req->id) }}">
                                            <i class="feather-eye me-1.5 text-muted"></i> View
                                        </a>
                                    </li>
                                    @if($req->status === 'Draft')
                                        <li>
                                            <a class="dropdown-item py-2" href="{{ route('purchase.requisitions.edit', $req->id) }}">
                                                <i class="feather-edit me-1.5 text-muted"></i> Edit
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('purchase.requisitions.destroy', $req->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this requisition?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item py-2 text-danger">
                                                    <i class="feather-trash-2 me-1.5"></i> Delete
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
                                No purchase requests found.
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
        $(document).ready(function() {
            // Select All Checkboxes
            $('.select-all').on('change', function() {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
            });
        });
    </script>
@endpush
