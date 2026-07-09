@extends('layouts.duralux')

@section('title', 'Production Scan Logs | SaaS ERP')
@section('page-title', 'Production Scan Logs Audit')
@section('breadcrumb', 'Scan Logs')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <a href="{{ route('production.scan-logs.export', request()->query()) }}" class="btn btn-success">
        <i class="feather-download me-2"></i>Export CSV
    </a>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'scanned_at');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Toolbar: Title + Sort + Filter --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Scan Log Audit Trail</h5>
            <div class="d-flex gap-2 ms-auto">
                {{-- Sort Dropdown --}}
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'scanned_at', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'scanned_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Newest Scans First</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'scanned_at', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'scanned_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest Scans First</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'scan_type', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'scan_type' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Scan Type (A&ndash;Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'entity_type', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'entity_type' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Entity Type (A&ndash;Z)</span>
                    </a>
                </x-ui.sort-dropdown>

                {{-- Filter Overlay --}}
                <form method="GET" action="{{ route('production.scan-logs.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Device / Operator</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search device, name..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Entity Type</label>
                            <x-ui.odoo-form-ui type="select" name="entity_type">
                                <option value="">All Entities</option>
                                <option value="order" @selected(request('entity_type') === 'order')>Production Order</option>
                                <option value="batch" @selected(request('entity_type') === 'batch')>Batch</option>
                                <option value="serial" @selected(request('entity_type') === 'serial')>Serial Number</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Operator</label>
                            <x-ui.odoo-form-ui type="select" name="scanned_by">
                                <option value="">All Operators</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(request('scanned_by') == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date Range</label>
                            <div class="d-flex gap-2">
                                <x-ui.odoo-form-ui type="input" name="date_start" value="{{ request('date_start') }}" />
                                <x-ui.odoo-form-ui type="input" name="date_end" value="{{ request('date_end') }}" />
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.scan-logs.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Scan Logs Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 7%">Log ID</th>
                        <th style="width: 10%">Scan Type</th>
                        <th style="width: 10%">Entity Type</th>
                        <th style="width: 18%">Resolved Code / Tag</th>
                        <th style="width: 15%">Scanned By</th>
                        <th style="width: 20%">Device Identifier</th>
                        <th style="width: 15%">Scan Timestamp</th>
                        <th style="width: 5%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="font-monospace text-muted">#{{ $log->id }}</td>
                            <td>
                                <span class="badge bg-soft-primary text-primary text-uppercase font-monospace fs-10 px-2 py-1">
                                    {{ $log->scan_type }}
                                </span>
                            </td>
                            <td class="text-uppercase text-muted fs-11 fw-semibold">{{ $log->entity_type }}</td>
                            <td class="font-monospace fw-bold text-dark">{{ $log->getEntityCode() }}</td>
                            <td class="fw-semibold">{{ $log->user ? $log->user->name : 'System' }}</td>
                            <td class="font-monospace text-muted fs-12">{{ $log->device_identifier ?: 'Browser / Unknown' }}</td>
                            <td class="text-muted">{{ $log->scanned_at->format('Y-m-d H:i:s') }}</td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('production.scan-logs.show', $log->id)" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="feather-info me-2 fs-16"></i>No barcode scan log registers found matching criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
@endsection
