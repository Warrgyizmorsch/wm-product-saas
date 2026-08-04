@extends('layouts.duralux')

@section('title', __('production.manufacturing_intelligence') . ' | SaaS ERP')
@section('page-title', __('production.manufacturing_intelligence'))
@section('breadcrumb', __('production.manufacturing_intelligence'))

@section('page-actions')
    <form method="GET" action="{{ route('production.intelligence.dashboard') }}" class="d-inline me-2">
        <x-ui.filter label="{{ __('production.filter') ?? 'Filter' }}" offset="0, 5">
            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>
            
            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.col_work_center') }}</label>
                <x-ui.odoo-form-ui type="select" name="work_center_id">
                    <option value="">{{ __('production.all_work_centers') ?? 'All Work Centers' }}</option>
                    @foreach($workCenters as $wc)
                        <option value="{{ $wc->id }}" {{ request('work_center_id') == $wc->id ? 'selected' : '' }}>{{ $wc->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.start_date') }}</label>
                <x-ui.odoo-form-ui type="input" inputType="date" name="date_start" value="{{ request('date_start', today()->toDateString()) }}" />
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.end_date') }}</label>
                <x-ui.odoo-form-ui type="input" inputType="date" name="date_end" value="{{ request('date_end', today()->toDateString()) }}" />
            </div>

            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="{{ route('production.intelligence.dashboard') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
            </div>
        </x-ui.filter>
    </form>
    <button type="button" class="btn btn-primary me-2" onclick="saveDashboardPrefs()">
        <i class="feather-save me-2"></i>{{ __('production.save_layout_preferences') }}
    </button>
    <a href="{{ route('production.intelligence.dashboard') }}" class="btn btn-secondary">
        <i class="feather-rotate-cw me-2"></i>{{ __('production.reset') }}
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">

        {{-- Widget Grid --}}
        <div class="row g-4" id="executive-widgets">
            {{-- Today's OEE Metric card --}}
            @if(in_array('today_oee', $prefs['widgets']))
                <div class="col-md-4" data-widget="today_oee">
                    <div class="card border border-light h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-uppercase text-muted fw-bold mb-0">{{ __('production.overall_equipment_effectiveness') }}</h6>
                                <span class="badge {{ $oeeKpi['status'] === 'Above Target' ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                    {{ __('production.status_' . Str::snake(Str::lower($oeeKpi['status']))) != 'production.status_' . Str::snake(Str::lower($oeeKpi['status'])) ? __('production.status_' . Str::snake(Str::lower($oeeKpi['status']))) : $oeeKpi['status'] }}
                                </span>
                            </div>
                            <h2 class="fw-bold text-dark mb-2">{{ $oeeKpi['current_value'] }}%</h2>
                            <div class="text-muted fs-13">
                                {{ __('production.target') }}: <strong>{{ $oeeKpi['target_value'] }}%</strong> | 
                                {{ __('production.variance') }}: <span class="{{ $oeeKpi['variance'] >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold' }}">{{ $oeeKpi['variance'] >= 0 ? '+' : '' }}{{ $oeeKpi['variance'] }}%</span>
                            </div>
                            <div class="mt-3">
                                <x-ui.progress-bar :value="$oeeKpi['current_value']" color="primary" height="6px" />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Today's Production quantity card --}}
            @if(in_array('today_production', $prefs['widgets']))
                <div class="col-md-4" data-widget="today_production">
                    <div class="card border border-light h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-uppercase text-muted fw-bold mb-0">{{ __('production.production_volume') }}</h6>
                                <span class="badge bg-soft-info text-info">{{ __('production.steady') }}</span>
                            </div>
                            <h2 class="fw-bold text-dark mb-2">{{ $data['production_summary']['actual_quantity'] }} {{ __('production.units') }}</h2>
                            <div class="text-muted fs-13">
                                {{ __('production.planned') }}: <strong>{{ $data['production_summary']['planned_quantity'] }} {{ __('production.units') }}</strong> |
                                {{ __('production.adherence') }}: <strong>{{ $data['production_summary']['schedule_adherence'] }}%</strong>
                            </div>
                            <div class="mt-3">
                                <x-ui.progress-bar :value="$data['production_summary']['schedule_adherence']" color="info" height="6px" />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Today's Downtime card --}}
            @if(in_array('today_downtime', $prefs['widgets']))
                <div class="col-md-4" data-widget="today_downtime">
                    <div class="card border border-light h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-uppercase text-muted fw-bold mb-0">{{ __('production.todays_downtime') }}</h6>
                                <span class="badge {{ $downtimeKpi['status'] === 'Above Target' ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                    {{ __('production.status_' . Str::snake(Str::lower($downtimeKpi['status']))) != 'production.status_' . Str::snake(Str::lower($downtimeKpi['status'])) ? __('production.status_' . Str::snake(Str::lower($downtimeKpi['status']))) : $downtimeKpi['status'] }}
                                </span>
                            </div>
                            <h2 class="fw-bold text-dark mb-2">{{ $downtimeKpi['current_value'] }}%</h2>
                            <div class="text-muted fs-13">
                                {{ __('production.target_limit') }}: <strong>{{ $downtimeKpi['target_value'] }}%</strong> |
                                {{ __('production.variance') }}: <span class="{{ $downtimeKpi['variance'] <= 0 ? 'text-success fw-bold' : 'text-danger fw-bold' }}">{{ $downtimeKpi['variance'] >= 0 ? '+' : '' }}{{ $downtimeKpi['variance'] }}%</span>
                            </div>
                            <div class="mt-3">
                                <x-ui.progress-bar :value="$downtimeKpi['current_value']" color="danger" height="6px" />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Quality, scrap and rejects stats --}}
            @if(in_array('scrap_rejects', $prefs['widgets']))
                <div class="col-md-6" data-widget="scrap_rejects">
                    <div class="card border border-light shadow-sm">
                        <div class="card-body">
                            <h6 class="text-uppercase text-muted fw-bold mb-3">{{ __('production.yield_waste_analysis') }}</h6>
                            <div class="row text-center mb-3">
                                <div class="col">
                                    <div class="text-muted fs-11 text-uppercase">{{ __('production.yield_rate') }}</div>
                                    <h4 class="fw-bold text-success">{{ $data['scrap_stats']['yield'] }}%</h4>
                                </div>
                                <div class="col">
                                    <div class="text-muted fs-11 text-uppercase">{{ __('production.scrap_rate') }}</div>
                                    <h4 class="fw-bold text-danger">{{ $data['scrap_stats']['scrap_rate'] }}%</h4>
                                </div>
                                <div class="col">
                                    <div class="text-muted fs-11 text-uppercase">{{ __('production.rejects_rate') }}</div>
                                    <h4 class="fw-bold text-warning">{{ $data['scrap_stats']['reject_rate'] }}%</h4>
                                </div>
                            </div>
                            <div class="text-muted fs-13">
                                {{ __('production.target_scrap_limit') }}: <strong>{{ $scrapKpi['target_value'] }}%</strong> |
                                {{ __('production.variance') }}: <span class="{{ $scrapKpi['variance'] <= 0 ? 'text-success fw-bold' : 'text-danger fw-bold' }}">{{ $scrapKpi['variance'] >= 0 ? '+' : '' }}{{ $scrapKpi['variance'] }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Six Big Losses breakdown chart --}}
            <div class="col-md-6">
                <div class="card border border-light shadow-sm">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted fw-bold mb-3">{{ __('production.oee_loss_analysis') }}</h6>
                        
                        @php
                            $losses = $data['six_big_losses'] ?? [
                                'equipment_failure_minutes' => 0,
                                'setup_adjustment_minutes' => 0,
                                'minor_stops_minutes' => 0,
                                'reduced_speed_minutes' => 0,
                                'startup_rejects_count' => 0,
                                'production_rejects_count' => 0,
                            ];
                            $maxMins = max(1, $losses['equipment_failure_minutes'] + $losses['setup_adjustment_minutes'] + $losses['minor_stops_minutes'] + $losses['reduced_speed_minutes']);
                            $maxUnits = max(1, $losses['startup_rejects_count'] + $losses['production_rejects_count']);
                        @endphp
                        
                        <div class="space-y-3">
                            <div>
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>{{ __('production.equipment_failure_breakdowns') }}</span>
                                    <strong>{{ number_format($losses['equipment_failure_minutes'], 0) }} {{ __('production.mins') }}</strong>
                                </div>
                                <x-ui.progress-bar :value="($losses['equipment_failure_minutes'] / $maxMins) * 100" color="danger" height="5px" />
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>{{ __('production.setup_adjustment') }}</span>
                                    <strong>{{ number_format($losses['setup_adjustment_minutes'], 0) }} {{ __('production.mins') }}</strong>
                                </div>
                                <x-ui.progress-bar :value="($losses['setup_adjustment_minutes'] / $maxMins) * 100" color="warning" height="5px" />
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>{{ __('production.minor_stops_idling') }}</span>
                                    <strong>{{ number_format($losses['minor_stops_minutes'], 0) }} {{ __('production.mins') }}</strong>
                                </div>
                                <x-ui.progress-bar :value="($losses['minor_stops_minutes'] / $maxMins) * 100" color="info" height="5px" />
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>{{ __('production.reduced_speed_losses') }}</span>
                                    <strong>{{ number_format($losses['reduced_speed_minutes'], 0) }} {{ __('production.mins') }}</strong>
                                </div>
                                <x-ui.progress-bar :value="($losses['reduced_speed_minutes'] / $maxMins) * 100" color="primary" height="5px" />
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>{{ __('production.startup_rejects') }}</span>
                                    <strong>{{ number_format($losses['startup_rejects_count'], 0) }} {{ __('production.units') }}</strong>
                                </div>
                                <x-ui.progress-bar :value="($losses['startup_rejects_count'] / $maxUnits) * 100" color="secondary" height="5px" />
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>{{ __('production.production_rejects') }}</span>
                                    <strong>{{ number_format($losses['production_rejects_count'], 0) }} {{ __('production.units') }}</strong>
                                </div>
                                <x-ui.progress-bar :value="($losses['production_rejects_count'] / $maxUnits) * 100" color="dark" height="5px" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Asset Utilizations charts --}}
            @if(in_array('utilization_charts', $prefs['widgets']))
                <div class="col-md-12" data-widget="utilization_charts">
                    <div class="card border border-light shadow-sm">
                        <div class="card-body">
                            <h6 class="text-uppercase text-muted fw-bold mb-3">{{ __('production.asset_capacity_utilizations') }}</h6>
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="fs-13 text-muted">{{ __('production.machine_utilization') }}</div>
                                    <h3 class="fw-bold text-dark mt-1">{{ $data['utilizations']['machine_utilization'] }}%</h3>
                                    <div class="mt-2">
                                        <x-ui.progress-bar :value="$data['utilizations']['machine_utilization']" color="primary" height="5px" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fs-13 text-muted">{{ __('production.operator_utilization') }}</div>
                                    <h3 class="fw-bold text-dark mt-1">{{ $data['utilizations']['operator_utilization'] }}%</h3>
                                    <div class="mt-2">
                                        <x-ui.progress-bar :value="$data['utilizations']['operator_utilization']" color="success" height="5px" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fs-13 text-muted">{{ __('production.work_center_utilization') }}</div>
                                    <h3 class="fw-bold text-dark mt-1">{{ $data['utilizations']['work_center_utilization'] }}%</h3>
                                    <div class="mt-2">
                                        <x-ui.progress-bar :value="$data['utilizations']['work_center_utilization']" color="warning" height="5px" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Andon overview panel --}}
            @if(in_array('andon_overview', $prefs['widgets']))
                @php
                    $andon = $data['andon_counts'] ?? ['Running' => 0, 'Idle' => 0, 'Setup' => 0, 'Breakdown' => 0];
                @endphp
                <div class="col-md-12" data-widget="andon_overview">
                    <div class="card border border-light shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-uppercase text-muted fw-bold mb-0">{{ __('production.andon_summary') }}</h6>
                                <a href="{{ route('production.intelligence.andon') }}" class="btn btn-xs btn-outline-primary">{{ __('production.open_live_board') }}</a>
                            </div>
                            <div class="row text-center g-2">
                                <div class="col">
                                    <div class="p-2 bg-soft-success text-success rounded fw-bold">{{ __('production.running') }}: {{ $andon['Running'] ?? 0 }}</div>
                                </div>
                                <div class="col">
                                    <div class="p-2 bg-soft-warning text-warning rounded fw-bold">{{ __('production.idle') }}: {{ $andon['Idle'] ?? 0 }}</div>
                                </div>
                                <div class="col">
                                    <div class="p-2 bg-soft-primary text-primary rounded fw-bold">{{ __('production.setup') }}: {{ $andon['Setup'] ?? 0 }}</div>
                                </div>
                                <div class="col">
                                    <div class="p-2 bg-soft-danger text-danger rounded fw-bold">{{ __('production.breakdown') }}: {{ $andon['Breakdown'] ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        function saveDashboardPrefs() {
            const widgets = Array.from(document.querySelectorAll('#executive-widgets [data-widget]'))
                .map(el => el.getAttribute('data-widget'));
            
            fetch("{{ route('production.intelligence.dashboard.preferences') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    dashboard_type: "executive",
                    widgets: widgets,
                    layout: "grid"
                })
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message || 'Preferences updated!');
            })
            .catch(err => {
                console.error(err);
                alert('Failed to save preferences.');
            });
        }
    </script>
@endsection
