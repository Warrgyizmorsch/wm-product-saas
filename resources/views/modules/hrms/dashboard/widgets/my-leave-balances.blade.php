<div class="card border mb-0 h-100 shadow-sm">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="dash-card-icon-avatar bg-soft-info text-info">
                <i class="feather-pie-chart"></i>
            </span>
            <div>
                <h6 class="fw-bold mb-0 text-dark fs-14">My Leave Balances</h6>
                <span class="fs-11 text-muted">Allocated vs Used leave quota</span>
            </div>
        </div>
        <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.leaves.index') ? route('hrms.leaves.index', ['action' => 'apply']) : url('/hrms/leaves?action=apply') }}" class="btn btn-xs btn-primary rounded-pill px-2.5 py-1 fs-11">Apply Leave</a>
    </div>

    <div class="card-body p-3">
        <div class="row g-3">
            @if(!empty($myLeaveTypesList))
                @foreach($myLeaveTypesList as $lt)
                    @php
                        $pct = $lt['allocated'] > 0 ? min(100, round(($lt['used'] / $lt['allocated']) * 100)) : 0;
                    @endphp
                    <div class="col-6 col-md-4">
                        <div class="p-2.5 border rounded-3 bg-light">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-bold fs-12 text-dark">{{ $lt['name'] }}</span>
                                <span class="badge px-1.5 py-0.5 fs-10 text-white" style="background-color: {{ $lt['color'] }};">{{ $lt['code'] }}</span>
                            </div>
                            <div class="fs-16 fw-bolder text-dark">{{ $lt['remaining'] }} <span class="fs-11 text-muted font-normal">/ {{ $lt['allocated'] }} days</span></div>
                            <div class="progress mt-1.5" style="height: 4px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $pct }}%; background-color: {{ $lt['color'] }};" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-12 text-center py-3 text-muted fs-12">No leave balances assigned.</div>
            @endif
        </div>
    </div>
</div>
