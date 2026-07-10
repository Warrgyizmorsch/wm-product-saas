@extends('layouts.duralux')

@section('title', 'Scan Log Details | SaaS ERP')
@section('page-title', 'Production Scan Log Details')
@section('breadcrumb', 'Scan Log Details')

@section('page-actions')
    <a href="{{ route('production.scan-logs.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>Back to List
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        <x-ui.odoo-form-ui type="sheet">
            {{-- Header --}}
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <h4 class="fw-bold text-dark mb-0"><i class="feather-sliders text-primary me-2"></i>Scan Log Entry #{{ $log->id }}</h4>
                <a href="{{ route('production.scan-logs.index') }}" class="text-muted hover-danger fs-18">
                    <i class="feather-x"></i>
                </a>
            </div>

            <div class="row g-4 mb-4 text-dark fs-13">
                <!-- Left Column: Scan Metadata -->
                <div class="col-md-6 border-end pe-md-4">
                    <h5 class="fw-bold mb-3 text-primary border-bottom pb-1"><i class="feather-info me-2"></i>Scan Metadata</h5>
                    
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted">Scan Type:</span>
                        <span class="badge bg-soft-primary text-primary text-uppercase font-monospace fs-11">{{ $log->scan_type }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted">Scanned By:</span>
                        <span class="fw-semibold">{{ $log->user ? $log->user->name : 'System' }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted">Email / Operator Identifier:</span>
                        <span class="font-monospace text-muted">{{ $log->user ? $log->user->email : '—' }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted">Device / Terminal ID:</span>
                        <span class="font-monospace text-muted">{{ $log->device_identifier ?: 'Unknown Device / Console Log' }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted">Logged Timestamp:</span>
                        <span class="fw-semibold">{{ $log->scanned_at->format('Y-m-d H:i:s') }}</span>
                    </div>
                </div>

                <!-- Right Column: Scanned Entity Details -->
                <div class="col-md-6 ps-md-4">
                    <h5 class="fw-bold mb-3 text-success border-bottom pb-1"><i class="feather-box me-2"></i>Scanned Entity Info</h5>
                    
                    @if($entityInfo)
                        <div class="mb-3 d-flex justify-content-between">
                            <span class="text-muted">Entity Resolved:</span>
                            <span class="badge bg-soft-success text-success text-uppercase font-monospace fs-11">{{ $log->entity_type }}</span>
                        </div>
                        <div class="mb-3 d-flex justify-content-between">
                            <span class="text-muted">Entity Code / Number:</span>
                            <span class="font-monospace fw-bold text-dark">{{ $entityInfo['code'] }}</span>
                        </div>
                        <div class="mb-3 d-flex justify-content-between">
                            <span class="text-muted">Related Product:</span>
                            <span class="fw-semibold">{{ $entityInfo['name'] }}</span>
                        </div>
                        <div class="mb-3 d-flex justify-content-between">
                            <span class="text-muted">Logged Quantity:</span>
                            <span class="fw-semibold">{{ $entityInfo['qty'] }}</span>
                        </div>
                        <div class="mb-3 d-flex justify-content-between">
                            <span class="text-muted">Current System Status:</span>
                            <span class="badge bg-light text-dark font-monospace text-uppercase">{{ $entityInfo['status'] }}</span>
                        </div>
                    @else
                        <div class="alert alert-soft-warning border-0">
                            <i class="feather-alert-circle me-2"></i> The entity referenced by this scan log (Type: `{{ $log->entity_type }}`, ID: `{{ $log->entity_id }}`) could not be resolved or has been deleted.
                        </div>
                    @endif
                </div>
            </div>
        </x-ui.odoo-form-ui>
    </div>
@endsection
