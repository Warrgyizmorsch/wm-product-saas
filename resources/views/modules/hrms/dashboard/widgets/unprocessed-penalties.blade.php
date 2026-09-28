<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-header bg-white pt-3.5 pb-2.5 px-4 d-flex align-items-center justify-content-between border-bottom-0">
        <div class="d-flex align-items-center gap-2">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 30px; height: 30px; background: #fef2f2; border-radius: 8px; color: #ef4444;">
                <i class="feather-alert-triangle fs-13"></i>
            </div>
            <h6 class="fw-bold mb-0 text-dark fs-14" style="color: #0f172a !important;">Unprocessed Penalties</h6>
        </div>
        <span class="badge px-2.5 py-1 fs-11 fw-semibold rounded-pill" style="background: #fee2e2; color: #b91c1c;">
            {{ isset($unprocessedPenalties) ? count($unprocessedPenalties) : 0 }} Pending
        </span>
    </div>

    <div class="card-body p-0 overflow-auto" style="min-height: 220px;">
        @if(!empty($unprocessedPenalties) && count($unprocessedPenalties) > 0)
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @foreach($unprocessedPenalties as $pen)
                            <tr>
                                <td class="py-2.5 ps-4 border-bottom" style="border-color: #f1f5f9;">
                                    <div class="fw-semibold text-dark fs-13" style="color: #1e293b !important;">{{ $pen->employee?->full_name ?? 'Employee' }}</div>
                                </td>
                                <td class="py-2.5 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                    <span class="badge px-2.5 py-1 fs-11 fw-semibold rounded-pill" style="background: #fee2e2; color: #b91c1c;">Penalty Pending</span>
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
                <span class="fs-13 text-secondary fw-medium" style="color: #64748b !important;">No unprocessed penalties pending.</span>
            </div>
        @endif
    </div>
</div>

