@extends('layouts.duralux')

@section('title', 'Live Andon Monitoring Board | SaaS ERP')
@section('page-title', 'Live Andon Board')
@section('breadcrumb', 'Andon Monitoring')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <span class="fs-12 text-muted"><i class="feather-clock me-1"></i>Auto Refresh in <strong id="refresh-countdown">30</strong>s</span>
        <button type="button" class="btn btn-sm btn-dark" onclick="window.location.reload()">
            <i class="feather-rotate-cw"></i>
        </button>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        {{-- Board Header Status Badge Legend --}}
        <div class="d-flex flex-wrap gap-3 mb-4 pb-3 border-bottom fs-13">
            <span class="d-flex align-items-center"><span class="badge bg-success me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> Running</span>
            <span class="d-flex align-items-center"><span class="badge bg-warning me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> Waiting / Idle</span>
            <span class="d-flex align-items-center"><span class="badge bg-primary me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> Setup</span>
            <span class="d-flex align-items-center"><span class="badge bg-danger me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> Breakdown</span>
            <span class="d-flex align-items-center"><span class="badge bg-secondary me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> Maintenance</span>
            <span class="d-flex align-items-center"><span class="badge bg-dark me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span> Offline</span>
        </div>

        <div class="row g-4" id="andon-grid">
            @foreach($machines as $machine)
                @php
                    // Color mapping based on current_state
                    $cardBorder = match(strtolower($machine->current_state ?? '')) {
                        'running'    => 'border-success',
                        'idle'       => 'border-warning',
                        'waiting'    => 'border-warning',
                        'setup'      => 'border-primary',
                        'breakdown'  => 'border-danger',
                        'maintenance'=> 'border-secondary',
                        default      => 'border-dark',
                    };
                    $stateBg = match(strtolower($machine->current_state ?? '')) {
                        'running'    => 'bg-soft-success text-success',
                        'idle'       => 'bg-soft-warning text-warning',
                        'waiting'    => 'bg-soft-warning text-warning',
                        'setup'      => 'bg-soft-primary text-primary',
                        'breakdown'  => 'bg-soft-danger text-danger',
                        'maintenance'=> 'bg-soft-secondary text-secondary',
                        default      => 'bg-soft-dark text-dark',
                    };
                @endphp
                <div class="col-md-4 col-lg-3">
                    <div class="card border border-2 {{ $cardBorder }} shadow-sm h-100 touch-card">
                        <div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">
                            <span class="fw-bold text-dark font-monospace">{{ $machine->code }}</span>
                            <span class="badge {{ $stateBg }} text-uppercase fs-10 px-2 py-1">{{ $machine->current_state ?? 'Offline' }}</span>
                        </div>
                        <div class="card-body p-3">
                            <h5 class="fw-bold text-dark mb-1">{{ $machine->name }}</h5>
                            <p class="text-muted fs-12 mb-3">Work Center: <strong>{{ $machine->workCenter->name ?? '—' }}</strong></p>

                            <div class="fs-12 text-muted mb-2">
                                <div><i class="feather-user me-1"></i> Operator: <strong>{{ $machine->current_operator ?? '—' }}</strong></div>
                                <div><i class="feather-cpu me-1"></i> Reason: <strong>{{ $machine->current_state_reason ?? '—' }}</strong></div>
                            </div>

                            <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Daily OEE</span>
                                <strong class="fs-14 text-primary">85.00%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        let count = 30;
        const countdownEl = document.getElementById('refresh-countdown');
        setInterval(() => {
            count--;
            if (countdownEl) countdownEl.innerText = count;
            if (count <= 0) {
                window.location.reload();
            }
        }, 1000);
    </script>
@endsection
