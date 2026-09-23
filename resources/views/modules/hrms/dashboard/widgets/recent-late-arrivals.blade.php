<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between border-bottom-0">
        <div class="d-flex align-items-center">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: #fff7ed; border-radius: 10px; color: #ea580c;">
                <i class="feather-clock fs-16"></i>
            </div>
            <h6 class="fw-bold mb-0 text-dark ms-2.5" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Late Arrivals (Last 7 Days)</h6>
        </div>
        <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #ffedd5; color: #c2410c;">
            {{ isset($recentLateArrivals) ? count($recentLateArrivals) : 0 }} Cases
        </span>
    </div>

    <div class="card-body p-0 overflow-auto" style="min-height: 240px;">
        @if(!empty($recentLateArrivals) && count($recentLateArrivals) > 0)
            <div class="table-responsive">
                <table class="table align-middle mb-0 fs-13">
                    <tbody>
                        @foreach($recentLateArrivals as $late)
                            <tr>
                                <td class="py-3 ps-4 border-bottom" style="border-color: #f1f5f9;">
                                    <div class="fw-bold text-dark fs-14" style="color: #1e293b !important; font-weight: 700;">{{ $late->employee?->full_name ?? 'Employee' }}</div>
                                </td>
                                <td class="py-3 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                    <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #ffedd5; color: #c2410c;">Late {{ $late->late_minutes ?? 15 }} mins</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="d-flex flex-column align-items-center justify-content-center text-center p-4" style="min-height: 220px;">
                <div class="d-flex align-items-center justify-content-center mb-2.5" style="width: 48px; height: 48px; background: #f0fdf4; border-radius: 50%; color: #16a34a;">
                    <i class="feather-check-circle fs-22"></i>
                </div>
                <span class="fs-13 text-secondary fw-medium" style="color: #64748b !important;">No late arrivals in the last 7 days.</span>
            </div>
        @endif
    </div>
</div>

