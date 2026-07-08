@extends('layouts.duralux')

@section('title', 'CAPA Register | SaaS ERP')
@section('page-title', 'Corrective & Preventive Actions (CAPA)')
@section('breadcrumb', 'CAPA')

@section('page-actions')
    <a href="{{ route('production.capas.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Initiate CAPA Investigation
    </a>
@endsection

@section('content')
    {{-- Toast alerts --}}
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <div class="erp-single-panel">
        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">CAPA Log</h5>
            <div class="d-flex gap-2 ms-auto">
                <!-- Sort dropdown -->
                @php
                    $sortBy = request('sort_by', 'id');
                    $sortOrder = request('sort_order', 'desc');
                @endphp
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Newest First</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest First</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'target_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'target_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Target Date (Asc)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'target_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'target_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Target Date (Desc)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('production.capas.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.input name="search" placeholder="Search CAPA number or action..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.select name="status" :options="[
                                '' => 'All Statuses',
                                'draft' => 'Draft / Investigation Pending',
                                'active' => 'Active / Under Implementation',
                                'verified' => 'Verified Effective',
                                'closed' => 'Closed'
                            ]" selected="{{ request('status') }}" data-select2-selector="default" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.capas.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- CAPA Table -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 15%">CAPA Number</th>
                    <th style="width: 25%">Linked NCR</th>
                    <th style="width: 25%">Action Owner</th>
                    <th style="width: 15%">Status</th>
                    <th style="width: 15%">Target Date</th>
                    <th class="text-end" style="width: 5%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($capas as $capa)
                    <tr>
                        <td class="font-monospace fw-bold text-primary">
                            <a href="{{ route('production.capas.show', $capa->id) }}" class="text-primary hover-primary">
                                {{ $capa->capa_number }}
                            </a>
                        </td>
                        <td>
                            @if($capa->ncr)
                                <a href="{{ route('production.ncrs.show', $capa->ncr->id) }}" class="fw-semibold text-danger">
                                    {{ $capa->ncr->ncr_number }}
                                </a>
                            @else
                                <span class="text-muted">General Quality CAPA</span>
                            @endif
                        </td>
                        <td class="text-dark fw-medium">{{ $capa->owner->name ?? '—' }}</td>
                        <td>
                            @if($capa->status === 'closed')
                                <span class="erp-badge-active">Closed</span>
                            @elseif($capa->status === 'active')
                                <span class="erp-badge-pending">Active</span>
                            @else
                                <span class="erp-badge-draft text-uppercase">{{ $capa->status }}</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $capa->target_date ? $capa->target_date->format('d/m/Y') : '—' }}</td>
                        <td class="text-end">
                            <x-ui.action-dropdown :viewUrl="route('production.capas.show', $capa->id)" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="feather-alert-circle me-2 fs-16"></i>No CAPA records found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-4">
            {{ $capas->links() }}
        </div>
    </div>
@endsection
