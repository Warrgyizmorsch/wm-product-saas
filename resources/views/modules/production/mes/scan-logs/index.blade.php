@extends('layouts.duralux')

@section('title', 'Production Scan Logs | SaaS ERP')
@section('page-title', 'Production Scan Logs Audit')
@section('breadcrumb', 'Scan Logs')

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('production.scan-logs.export', request()->query()) }}" class="btn btn-success">
            <i class="feather-download me-2"></i>Export CSV
        </a>
    </div>
@endsection

@section('content')
    <div class="container-fluid py-2">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Filters & Search Panel --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('production.scan-logs.index') }}">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Entity Type</label>
                            <select name="entity_type" class="form-select">
                                <option value="">All Entities</option>
                                <option value="order" @selected(request('entity_type') === 'order')>Production Order</option>
                                <option value="batch" @selected(request('entity_type') === 'batch')>Batch</option>
                                <option value="serial" @selected(request('entity_type') === 'serial')>Serial Number</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Operator</label>
                            <select name="scanned_by" class="form-select">
                                <option value="">All Operators</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(request('scanned_by') == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">From Date</label>
                            <input type="date" name="date_start" class="form-control" value="{{ request('date_start') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">To Date</label>
                            <input type="date" name="date_end" class="form-control" value="{{ request('date_end') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Search Device / Operator</label>
                            <input type="text" name="search" class="form-control" placeholder="Search device, name..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-1 d-flex align-items-end gap-1">
                            <button type="submit" class="btn btn-primary w-100" title="Apply Filters">
                                <i class="feather-filter"></i>
                            </button>
                            <a href="{{ route('production.scan-logs.index') }}" class="btn btn-light w-100" title="Clear Filters">
                                <i class="feather-refresh-cw"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Data Panel --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table erp-table mb-0">
                        <thead class="bg-light text-dark">
                            <tr>
                                <th class="ps-4">Log ID</th>
                                <th>Scan Type</th>
                                <th>Entity Type</th>
                                <th>Resolved Code / Tag</th>
                                <th>Scanned By</th>
                                <th>Device Identifier</th>
                                <th>Scan Timestamp</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td class="ps-4 align-middle font-monospace text-muted">#{{ $log->id }}</td>
                                    <td class="align-middle">
                                        <span class="badge bg-soft-primary text-primary text-uppercase font-monospace fs-10 px-2 py-1">
                                            {{ $log->scan_type }}
                                        </span>
                                    </td>
                                    <td class="align-middle text-uppercase text-muted fs-11 font-semibold">{{ $log->entity_type }}</td>
                                    <td class="align-middle font-monospace fw-bold text-dark">{{ $log->getEntityCode() }}</td>
                                    <td class="align-middle fw-semibold">{{ $log->user ? $log->user->name : 'System' }}</td>
                                    <td class="align-middle font-monospace text-muted fs-12">{{ $log->device_identifier ?: 'Browser Console / Unknown' }}</td>
                                    <td class="align-middle text-muted">{{ $log->scanned_at->format('Y-m-d H:i:s') }}</td>
                                    <td class="text-end pe-4 align-middle">
                                        <x-ui.icon-btn href="{{ route('production.scan-logs.show', $log->id) }}" variant="light" size="sm" icon="feather-eye" title="View Details" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="feather-info me-2 fs-18 text-warning"></i>No barcode scan log registers found matching criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
@endsection
