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
                                <x-ui.odoo-form-ui type="sheet">
                                    <x-ui.odoo-form-ui type="input" label="Threshold Limit" name="threshold" inputType="number" step="0.01" value="{{ $alert->threshold }}" :required="true" />
                                    
                                    <x-ui.odoo-form-ui type="select" label="Severity Level" name="severity" id="severity{{ $alert->id }}">
                                        <option value="info" @selected($alert->severity === 'info')>Info</option>
                                        <option value="warning" @selected($alert->severity === 'warning')>Warning</option>
                                        <option value="critical" @selected($alert->severity === 'critical')>Critical</option>
                                    </x-ui.odoo-form-ui>
                                    
                                    <div class="mt-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="active" value="1" id="activeSwitch{{ $alert->id }}" @checked($alert->active)>
                                        <label class="form-check-label fs-12 text-muted" for="activeSwitch{{ $alert->id }}">Enable monitoring</label>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-100 py-2">Save Config</button>
                                    </div>
                                </x-ui.odoo-form-ui>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
