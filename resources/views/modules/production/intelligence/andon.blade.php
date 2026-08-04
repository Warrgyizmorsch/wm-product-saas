@extends('layouts.duralux')

@section('title', __('production.live_andon_monitoring_board') . ' | SaaS ERP')
@section('page-title', __('production.live_andon_board'))
@section('breadcrumb', __('production.andon_monitoring'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <span class="fs-12 text-muted"><i class="feather-clock me-1"></i>{{ __('production.auto_refresh_in') }} <strong id="refresh-countdown">15</strong>s</span>
        <button type="button" class="btn btn-sm btn-dark" onclick="window.location.reload()">
            <i class="feather-rotate-cw"></i>
        </button>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        {{-- Summary Cards using <x-ui.stat-widget> --}}
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <x-ui.stat-widget :title="__('production.total_machines')" :value="$andonData['total_machines'] ?? 0" icon="feather-cpu" color="dark" variant="compact" id="summary-total" />
            </div>
            <div class="col-md-2">
                <x-ui.stat-widget :title="__('production.running_machines')" :value="$andonData['running_count'] ?? 0" icon="feather-play-circle" color="success" variant="compact" id="summary-running" />
            </div>
            <div class="col-md-2">
                <x-ui.stat-widget :title="__('production.idle_waiting')" :value="$andonData['idle_count'] ?? 0" icon="feather-pause-circle" color="warning" variant="compact" id="summary-idle" />
            </div>
            <div class="col-md-2">
                <x-ui.stat-widget :title="__('production.setup_machines')" :value="$andonData['setup_count'] ?? 0" icon="feather-settings" color="primary" variant="compact" id="summary-setup" />
            </div>
            <div class="col-md-2">
                <x-ui.stat-widget :title="__('production.breakdown_machines')" :value="$andonData['breakdown_count'] ?? 0" icon="feather-alert-triangle" color="danger" variant="compact" id="summary-breakdown" />
            </div>
            <div class="col-md-2">
                <x-ui.stat-widget :title="__('production.avg_shop_floor_oee')" :value="number_format($andonData['avg_oee'] ?? 0, 2) . '%'" icon="feather-pie-chart" color="info" variant="compact" id="summary-oee" />
            </div>
        </div>

        {{-- Board Header Status Badge Legend --}}
        <div class="d-flex flex-wrap gap-3 mb-4 pb-3 border-bottom fs-13">
            <span class="d-flex align-items-center"><span class="badge bg-success me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> {{ __('production.running') }}</span>
            <span class="d-flex align-items-center"><span class="badge bg-warning me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> {{ __('production.idle_waiting') }}</span>
            <span class="d-flex align-items-center"><span class="badge bg-primary me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> {{ __('production.setup') }}</span>
            <span class="d-flex align-items-center"><span class="badge bg-danger me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> {{ __('production.breakdown') }}</span>
            <span class="d-flex align-items-center"><span class="badge bg-secondary me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> {{ __('production.maintenance_machines') }}</span>
            <span class="d-flex align-items-center"><span class="badge bg-dark me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> {{ __('production.offline_machines') }}</span>
        </div>

        {{-- Machine Cards Grid using <x-ui.card> --}}
        <div class="row g-4" id="andon-grid">
            @foreach($andonData['machines'] ?? [] as $m)
                @php
                    $state = strtolower($m['current_state'] ?? 'offline');
                    $cardBorder = match($state) {
                        'running'         => 'border-success',
                        'idle', 'waiting' => 'border-warning',
                        'setup'           => 'border-primary',
                        'breakdown'       => 'border-danger',
                        'maintenance'     => 'border-secondary',
                        default           => 'border-dark',
                    };
                    $stateBg = match($state) {
                        'running'         => 'bg-soft-success text-success',
                        'idle', 'waiting' => 'bg-soft-warning text-warning',
                        'setup'           => 'bg-soft-primary text-primary',
                        'breakdown'       => 'bg-soft-danger text-danger',
                        'maintenance'     => 'bg-soft-secondary text-secondary',
                        default           => 'bg-soft-dark text-dark',
                    };
                    $stateLabelKey = 'production.' . ($state === 'waiting' ? 'idle_waiting' : $state);
                @endphp
                <div class="col-md-4 col-lg-3">
                    <x-ui.card class="border border-2 {{ $cardBorder }} shadow-sm h-100 touch-card">
                        <x-slot name="headerAction">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <span class="fw-bold text-dark font-monospace me-2">{{ $m['code'] }}</span>
                                <span class="badge {{ $stateBg }} text-uppercase fs-10 px-2 py-1">
                                    {{ __($stateLabelKey) != $stateLabelKey ? __($stateLabelKey) : strtoupper($m['current_state']) }}
                                </span>
                            </div>
                        </x-slot>

                        <h5 class="fw-bold text-dark mb-1">{{ $m['name'] }}</h5>
                        <p class="text-muted fs-12 mb-2">{{ __('production.work_center') }}: <strong>{{ $m['work_center_name'] }}</strong></p>

                        <div class="fs-12 text-muted mb-2">
                            <div><i class="feather-user me-1"></i> {{ __('production.operator') }}: <strong>{{ $m['operator_name'] }}</strong></div>
                            <div><i class="feather-file-text me-1"></i> {{ __('production.active_order') }}: <strong>{{ $m['active_order'] }}</strong></div>
                            @if($m['active_op_name'] !== '—')
                                <div><i class="feather-layers me-1"></i> {{ __('production.active_operation') }}: <strong>{{ $m['active_op_name'] }}</strong></div>
                            @endif
                            <div><i class="feather-cpu me-1"></i> {{ __('production.reason') }}: <strong>{{ $m['current_state_reason'] }}</strong></div>
                        </div>

                        <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="fs-11 text-muted">{{ __('production.daily_oee') }}</span>
                            <strong class="fs-14 text-primary">{{ number_format($m['today_oee'], 2) }}%</strong>
                        </div>
                    </x-ui.card>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        let count = 15;
        const countdownEl = document.getElementById('refresh-countdown');
        
        setInterval(() => {
            count--;
            if (countdownEl) countdownEl.innerText = count;
            if (count <= 0) {
                count = 15;
                window.location.reload();
            }
        }, 1000);
    </script>
@endsection
