@extends('layouts.duralux')

@section('title', 'Alert Threshold Configurations | SaaS ERP')
@section('page-title', 'Production Alerts Configurations')
@section('breadcrumb', 'Alert Configuration')

@section('page-actions')
    <a href="{{ route('production.intelligence.alerts.index') }}?audit=1" class="btn btn-warning me-2">
        <i class="feather-zap me-2"></i>Run Alerts Audit Evaluation
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <h5 class="fw-bold text-dark mb-4"><i class="feather-alert-triangle me-2 text-danger"></i>Production Limits & Alert Configurations</h5>

        <div class="row g-4">
            @foreach($alerts as $alert)
                <div class="col-md-4">
                    <div class="card border border-light shadow-sm h-100 touch-card">
                        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 font-monospace text-uppercase text-dark">{{ str_replace('_', ' ', $alert->alert_type) }}</h6>
                            <span class="badge {{ $alert->active ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary' }}">
                                {{ $alert->active ? 'Active' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('production.intelligence.alerts.update', $alert->id) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fs-11 text-uppercase text-muted">Threshold Limit Value</label>
                                    <input type="number" step="0.01" name="threshold" class="form-control" value="{{ $alert->threshold }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fs-11 text-uppercase text-muted">Alert Severity Level</label>
                                    <select name="severity" class="form-select">
                                        <option value="info" {{ $alert->severity === 'info' ? 'selected' : '' }}>Info</option>
                                        <option value="warning" {{ $alert->severity === 'warning' ? 'selected' : '' }}>Warning</option>
                                        <option value="critical" {{ $alert->severity === 'critical' ? 'selected' : '' }}>Critical</option>
                                    </select>
                                </div>
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="active" value="1" id="activeSwitch{{ $alert->id }}" {{ $alert->active ? 'checked' : '' }}>
                                    <label class="form-check-label fs-12 text-muted" for="activeSwitch{{ $alert->id }}">Enable alert monitoring</label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-dark w-100">Save Config</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
