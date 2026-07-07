@extends('layouts.duralux')

@section('title', 'Executive Manufacturing Intelligence | SaaS ERP')
@section('page-title', 'Executive Manufacturing Intelligence')
@section('breadcrumb', 'Manufacturing Intelligence')

@section('page-actions')
    <button type="button" class="btn btn-primary me-2" onclick="saveDashboardPrefs()">
        <i class="feather-save me-2"></i>Save Layout Preferences
    </button>
    <a href="{{ route('production.intelligence.dashboard') }}" class="btn btn-secondary">
        <i class="feather-rotate-cw me-2"></i>Refresh
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        {{-- Filters Section --}}
        <form method="GET" action="{{ route('production.intelligence.dashboard') }}" class="row g-3 mb-4 pb-4 border-bottom align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">Work Center</label>
                <select name="work_center_id" class="form-select">
                    <option value="">All Work Centers</option>
                    @foreach($workCenters as $wc)
                        <option value="{{ $wc->id }}" {{ request('work_center_id') == $wc->id ? 'selected' : '' }}>{{ $wc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Start Date</label>
                <input type="date" name="date_start" class="form-control" value="{{ request('date_start', today()->toDateString()) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">End Date</label>
                <input type="date" name="date_end" class="form-control" value="{{ request('date_end', today()->toDateString()) }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100"><i class="feather-filter me-2"></i>Apply Filters</button>
            </div>
        </form>

        {{-- Widget Grid --}}
        <div class="row g-4" id="executive-widgets">
            {{-- Today's OEE Metric card --}}
            @if(in_array('today_oee', $prefs['widgets']))
                <div class="col-md-4" data-widget="today_oee">
                    <div class="card border border-light h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-uppercase text-muted fw-bold mb-0">Overall Equipment Effectiveness (OEE)</h6>
                                <span class="badge {{ $oeeKpi['status'] === 'Above Target' ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                    {{ $oeeKpi['status'] }}
                                </span>
                            </div>
                            <h2 class="fw-bold text-dark mb-2">{{ $oeeKpi['current_value'] }}%</h2>
                            <div class="text-muted fs-13">
                                Target: <strong>{{ $oeeKpi['target_value'] }}%</strong> | 
                                Variance: <span class="{{ $oeeKpi['variance'] >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold' }}">{{ $oeeKpi['variance'] >= 0 ? '+' : '' }}{{ $oeeKpi['variance'] }}%</span>
                            </div>
                            <div class="progress mt-3" style="height: 6px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $oeeKpi['current_value'] }}%"></div>
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
                                <h6 class="text-uppercase text-muted fw-bold mb-0">Production Volume</h6>
                                <span class="badge bg-soft-info text-info">Steady</span>
                            </div>
                            <h2 class="fw-bold text-dark mb-2">{{ $data['production_summary']['actual_quantity'] }} units</h2>
                            <div class="text-muted fs-13">
                                Planned: <strong>{{ $data['production_summary']['planned_quantity'] }} units</strong> |
                                Adherence: <strong>{{ $data['production_summary']['schedule_adherence'] }}%</strong>
                            </div>
                            <div class="progress mt-3" style="height: 6px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: {{ $data['production_summary']['schedule_adherence'] }}%"></div>
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
                                <h6 class="text-uppercase text-muted fw-bold mb-0">Today's Downtime</h6>
                                <span class="badge {{ $downtimeKpi['status'] === 'Above Target' ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                    {{ $downtimeKpi['status'] }}
                                </span>
                            </div>
                            <h2 class="fw-bold text-dark mb-2">{{ $downtimeKpi['current_value'] }}%</h2>
                            <div class="text-muted fs-13">
                                Target Limit: <strong>{{ $downtimeKpi['target_value'] }}%</strong> |
                                Variance: <span class="{{ $downtimeKpi['variance'] <= 0 ? 'text-success fw-bold' : 'text-danger fw-bold' }}">{{ $downtimeKpi['variance'] >= 0 ? '+' : '' }}{{ $downtimeKpi['variance'] }}%</span>
                            </div>
                            <div class="progress mt-3" style="height: 6px;">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $downtimeKpi['current_value'] }}%"></div>
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
                            <h6 class="text-uppercase text-muted fw-bold mb-3">Production Yield & Waste Analysis</h6>
                            <div class="row text-center mb-3">
                                <div class="col">
                                    <div class="text-muted fs-11 text-uppercase">Yield Rate</div>
                                    <h4 class="fw-bold text-success">{{ $data['scrap_stats']['yield'] }}%</h4>
                                </div>
                                <div class="col">
                                    <div class="text-muted fs-11 text-uppercase">Scrap Rate</div>
                                    <h4 class="fw-bold text-danger">{{ $data['scrap_stats']['scrap_rate'] }}%</h4>
                                </div>
                                <div class="col">
                                    <div class="text-muted fs-11 text-uppercase">Rejects Rate</div>
                                    <h4 class="fw-bold text-warning">{{ $data['scrap_stats']['reject_rate'] }}%</h4>
                                </div>
                            </div>
                            <div class="text-muted fs-13">
                                Target Scrap Limit: <strong>{{ $scrapKpi['target_value'] }}%</strong> |
                                Variance: <span class="{{ $scrapKpi['variance'] <= 0 ? 'text-success fw-bold' : 'text-danger fw-bold' }}">{{ $scrapKpi['variance'] >= 0 ? '+' : '' }}{{ $scrapKpi['variance'] }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Six Big Losses breakdown chart placeholder --}}
            <div class="col-md-6">
                <div class="card border border-light shadow-sm">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted fw-bold mb-3">OEE Loss Analysis (Six Big Losses)</h6>
                        
                        <div class="space-y-3">
                            <div>
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>Equipment Failure (Breakdowns)</span>
                                    <strong>15 mins</strong>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: 35%"></div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>Setup & Adjustment</span>
                                    <strong>25 mins</strong>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 50%"></div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>Minor Stops & Idling</span>
                                    <strong>8 mins</strong>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 20%"></div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>Reduced Speed Losses</span>
                                    <strong>12 mins</strong>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 30%"></div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>Startup Rejects</span>
                                    <strong>3 units</strong>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-secondary" role="progressbar" style="width: 10%"></div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>Production Rejects</span>
                                    <strong>8 units</strong>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-dark" role="progressbar" style="width: 25%"></div>
                                </div>
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
                            <h6 class="text-uppercase text-muted fw-bold mb-3">Asset Capacity Utilizations</h6>
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="fs-13 text-muted">Machine Utilization</div>
                                    <h3 class="fw-bold text-dark mt-1">{{ $data['utilizations']['machine_utilization'] }}%</h3>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-primary" style="width: {{ $data['utilizations']['machine_utilization'] }}%"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fs-13 text-muted">Operator Utilization</div>
                                    <h3 class="fw-bold text-dark mt-1">{{ $data['utilizations']['operator_utilization'] }}%</h3>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-success" style="width: {{ $data['utilizations']['operator_utilization'] }}%"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="fs-13 text-muted">Work Center Utilization</div>
                                    <h3 class="fw-bold text-dark mt-1">{{ $data['utilizations']['work_center_utilization'] }}%</h3>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-warning" style="width: {{ $data['utilizations']['work_center_utilization'] }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Andon overview panel --}}
            @if(in_array('andon_overview', $prefs['widgets']))
                <div class="col-md-12" data-widget="andon_overview">
                    <div class="card border border-light shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-uppercase text-muted fw-bold mb-0">Shop Floor Machine States (Andon Summary)</h6>
                                <a href="{{ route('production.intelligence.andon') }}" class="btn btn-xs btn-outline-primary">Open Live Board</a>
                            </div>
                            <div class="row text-center g-2">
                                <div class="col">
                                    <div class="p-2 bg-soft-success text-success rounded fw-bold">Running</div>
                                </div>
                                <div class="col">
                                    <div class="p-2 bg-soft-warning text-warning rounded fw-bold">Idle</div>
                                </div>
                                <div class="col">
                                    <div class="p-2 bg-soft-primary text-primary rounded fw-bold">Setup</div>
                                </div>
                                <div class="col">
                                    <div class="p-2 bg-soft-danger text-danger rounded fw-bold">Breakdown</div>
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
