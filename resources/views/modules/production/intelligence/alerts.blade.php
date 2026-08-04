@extends('layouts.duralux')

@section('title', __('production.alert_threshold_configurations') . ' | SaaS ERP')
@section('page-title', __('production.production_alerts_configurations'))
@section('breadcrumb', __('production.alert_configuration'))

@section('page-actions')
    <a href="{{ route('production.intelligence.alerts.index') }}?audit=1" class="btn btn-warning me-2">
        <i class="feather-zap me-2"></i>{{ __('production.run_alerts_audit_evaluation') }}
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        <h5 class="fw-bold text-dark mb-4"><i class="feather-alert-triangle me-2 text-danger"></i>{{ __('production.production_limits_alert_config') }}</h5>

        {{-- Alert Configurations Grid --}}
        <div class="row g-4 mb-5">
            @foreach($alerts as $alert)
                @php
                    $alertLabelKey = 'production.' . $alert->alert_type;
                    $alertTitle = __($alertLabelKey) != $alertLabelKey ? __($alertLabelKey) : str_replace('_', ' ', strtoupper($alert->alert_type));
                    $unit = match($alert->alert_type) {
                        'oee_below_threshold', 'scrap_rate_high' => '%',
                        'machine_idle_limit' => 'mins',
                        default => '',
                    };
                @endphp
                <div class="col-md-4">
                    <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                        <x-slot name="headerAction">
                            <div class="d-flex justify-content-between align-items-center w-100 py-1">
                                <h6 class="fw-bold mb-0 font-monospace text-uppercase text-dark">{{ $alertTitle }}</h6>
                                <span class="badge {{ $alert->active ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary' }}">
                                    {{ $alert->active ? __('production.status_active') : __('production.status_disabled') }}
                                </span>
                            </div>
                        </x-slot>

                        <form method="POST" action="{{ route('production.intelligence.alerts.update', $alert->id) }}">
                            @csrf
                            <x-ui.odoo-form-ui type="sheet">
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui 
                                        type="input" 
                                        :label="__('production.threshold_limit') . ($unit ? ' (' . $unit . ')' : '')" 
                                        name="threshold" 
                                        inputType="number" 
                                        step="0.01" 
                                        value="{{ $alert->threshold }}" 
                                        :required="true" 
                                    />
                                </div>
                                
                                {{-- Severity Level Dropdown --}}
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="select" :label="__('production.severity_level')" name="severity" id="severity{{ $alert->id }}">
                                        <option value="info" @selected($alert->severity === 'info')>{{ __('production.info') }}</option>
                                        <option value="warning" @selected($alert->severity === 'warning')>{{ __('production.warning') }}</option>
                                        <option value="critical" @selected($alert->severity === 'critical')>{{ __('production.critical') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                {{-- Monitoring Active Radio Group --}}
                                <div class="mb-4">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-2 d-block">{{ __('production.status_rule') }}</label>
                                    <div class="d-flex gap-3">
                                        <x-ui.radio name="active" value="1" :label="__('production.status_active')" :checked="$alert->active" id="active_1_{{ $alert->id }}" />
                                        <x-ui.radio name="active" value="0" :label="__('production.status_disabled')" :checked="!$alert->active" id="active_0_{{ $alert->id }}" />
                                    </div>
                                </div>
                                
                                <div>
                                    <button type="submit" class="btn btn-primary w-100 py-2"><i class="feather-save me-1"></i> {{ __('production.save_config') }}</button>
                                </div>
                            </x-ui.odoo-form-ui>
                        </form>
                    </x-ui.card>
                </div>
            @endforeach
        </div>

        {{-- Recent Alert Events Log Table --}}
        <div class="mt-4 pt-4 border-top">
            <h5 class="fw-bold text-dark mb-3"><i class="feather-bell me-2 text-warning"></i>{{ __('production.recent_alert_events') }}</h5>
            
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th>{{ __('production.timestamp') }}</th>
                        <th>{{ __('production.event_title') }}</th>
                        <th>{{ __('production.severity') }}</th>
                        <th>{{ __('production.col_description') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentEvents as $event)
                        <tr>
                            <td class="fw-bold text-dark fs-12">{{ $event->created_at }}</td>
                            <td class="fw-semibold text-primary">{{ $event->title }}</td>
                            <td>
                                <span class="badge {{ $event->severity === 'critical' ? 'bg-soft-danger text-danger' : ($event->severity === 'warning' ? 'bg-soft-warning text-warning' : 'bg-soft-info text-info') }}">
                                    {{ strtoupper($event->severity) }}
                                </span>
                            </td>
                            <td class="fs-13 text-muted">{{ $event->description }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="feather-check-circle me-2 text-success"></i>{{ __('production.no_recent_alerts') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>
    </div>
@endsection
