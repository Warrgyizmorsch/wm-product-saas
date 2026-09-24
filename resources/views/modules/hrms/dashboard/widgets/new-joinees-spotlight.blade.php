<div class="card border mb-0 h-100 shadow-sm rounded-3">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="dash-card-icon-avatar bg-soft-info text-info" style="width:28px;height:28px;border-radius:6px;">
                <i class="feather-user-plus"></i>
            </span>
            <h6 class="fw-bold mb-0 text-dark fs-14">New Joinees Spotlight (Last 30 Days)</h6>
        </div>
        <span class="badge bg-soft-info text-info px-2 py-0.5 fs-10 fw-bold">{{ $newHiresThisMonth ?? 0 }} New Hires</span>
    </div>

    <div class="card-body p-4 text-center d-flex flex-column align-items-center justify-content-center">
        @if(!empty($newHiresList) && count($newHiresList) > 0)
            <div class="row g-3 w-100 text-start">
                @foreach($newHiresList as $nh)
                    <div class="col-md-4">
                        <div class="p-2.5 border rounded bg-light">
                            <div class="fw-bold text-dark fs-13">{{ $nh->full_name }}</div>
                            <div class="fs-11 text-muted">{{ $nh->designation?->name ?? 'Staff' }} &bull; {{ $nh->department?->name ?? 'Dept' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-info fs-1 mb-1"><i class="feather-user-check"></i></div>
            <span class="fs-12 text-muted">No new employees joined in the last 30 days.</span>
        @endif
    </div>
</div>
