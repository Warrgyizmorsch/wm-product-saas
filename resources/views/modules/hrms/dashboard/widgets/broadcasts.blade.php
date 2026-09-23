<div class="card border mb-0 h-100 shadow-sm">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="dash-card-icon-avatar bg-soft-primary text-primary">
                <i class="feather-radio"></i>
            </span>
            <div>
                <h6 class="fw-bold mb-0 text-dark fs-14">Company Broadcasts & Announcements</h6>
                <span class="fs-11 text-muted">Official notices, policy updates and notices</span>
            </div>
        </div>
        <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-1 fs-11 fw-bold">
            {{ $totalBroadcastsCount ?? (isset($latestBroadcasts) ? count($latestBroadcasts) : 0) }} Active
        </span>
    </div>

    <div class="card-body p-3 overflow-auto" style="max-height: 380px;">
        @if(!empty($latestBroadcasts) && count($latestBroadcasts) > 0)
            <div class="d-flex flex-column gap-2.5">
                @foreach($latestBroadcasts as $bc)
                    @php
                        $badgeVar = match($bc->priority ?? 'normal') {
                            'urgent' => 'danger',
                            'important' => 'warning',
                            default => 'info'
                        };
                        $empRec = ($currentEmployee ?? null) && $bc->receipts ? $bc->receipts->where('employee_id', $currentEmployee->id)->first() : null;
                        $isAcked = $empRec && $empRec->acknowledged_at;
                    @endphp
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-1.5 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-soft-{{ $badgeVar }} text-{{ $badgeVar }} text-uppercase fs-10 fw-bold px-2 py-0.5 rounded-pill border border-{{ $badgeVar }} border-opacity-20">
                                    {{ $bc->priority ?? 'normal' }} Priority
                                </span>
                                <span class="fs-11 text-muted">{{ $bc->published_at ? \Carbon\Carbon::parse($bc->published_at)->diffForHumans() : 'Recently' }}</span>
                            </div>
                            @if(($bc->is_acknowledgement_required ?? false) && !$isAcked)
                                <form action="{{ \Illuminate\Support\Facades\Route::has('hrms.broadcasts.acknowledge') ? route('hrms.broadcasts.acknowledge', $bc->id) : '#' }}" method="POST" class="d-inline m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-success rounded-pill px-2.5 py-1 fs-11">
                                        <i class="feather-check-circle me-1"></i> Acknowledge
                                    </button>
                                </form>
                            @elseif(($bc->is_acknowledgement_required ?? false) && $isAcked)
                                <span class="badge bg-soft-success text-success fs-10 px-2 py-1"><i class="feather-check me-1"></i> Acknowledged</span>
                            @endif
                        </div>
                        <h6 class="fw-bold text-dark fs-13 mb-1">{{ $bc->title }}</h6>
                        <p class="fs-12 text-muted mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($bc->content ?? ''), 180) }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-4 text-muted fs-12">No active announcements.</div>
        @endif
    </div>
</div>
