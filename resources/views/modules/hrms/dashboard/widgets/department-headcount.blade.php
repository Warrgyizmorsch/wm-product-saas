<div class="card border mb-0 h-100 shadow-sm rounded-3">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="dash-card-icon-avatar bg-soft-info text-info" style="width:28px;height:28px;border-radius:6px;">
                <i class="feather-layers"></i>
            </span>
            <h6 class="fw-bold mb-0 text-dark fs-14">Department Headcount</h6>
        </div>
        <span class="badge bg-soft-info text-info px-2 py-0.5 fs-10 fw-bold">{{ isset($departments) ? count($departments) : 0 }} Depts</span>
    </div>

    <div class="card-body p-3.5">
        @if(!empty($departments) && count($departments) > 0)
            <div class="d-flex flex-column gap-3">
                @foreach($departments as $dept)
                    @php
                        $tot = $totalEmployees ?? 1;
                        $cnt = $dept->employees_count ?? 0;
                        $pct = $tot > 0 ? round(($cnt / $tot) * 100) : 0;
                    @endphp
                    <div>
                        <div class="d-flex align-items-center justify-content-between fs-12 mb-1">
                            <span class="fw-bold text-dark">{{ $dept->name }}</span>
                            <span class="text-muted fs-11">{{ $cnt }} ({{ $pct }}%)</span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $pct }}%;" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-4 text-muted fs-12">No department data available.</div>
        @endif
    </div>
</div>
