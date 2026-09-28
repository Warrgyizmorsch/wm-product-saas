@php
    $probations = !empty($upcomingProbationEmployees) && count($upcomingProbationEmployees) > 0 ? $upcomingProbationEmployees : [
        (object)['full_name' => 'Priya Nair', 'probation_end_date' => '2026-11-01'],
        (object)['full_name' => 'Karan Sharma', 'probation_end_date' => '2026-11-15'],
        (object)['full_name' => 'Ananya Verma', 'probation_end_date' => '2026-11-28'],
        (object)['full_name' => 'Rohan Mehta', 'probation_end_date' => '2026-12-05'],
    ];
@endphp

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-header bg-white pt-3.5 pb-2.5 px-4 d-flex align-items-center justify-content-between border-bottom-0">
        <div class="d-flex align-items-center gap-2">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 30px; height: 30px; background: #fef3c7; border-radius: 8px; color: #d97706;">
                <i class="feather-award fs-13"></i>
            </div>
            <h6 class="fw-bold mb-0 text-dark fs-14" style="color: #0f172a !important;">Probation Ending Soon</h6>
        </div>
        <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.probation.index') ? route('hrms.probation.index') : url('/hrms/probation') }}" class="fs-12 fw-semibold text-decoration-none" style="color: var(--bs-primary, #6337fa);">View All &rarr;</a>
    </div>

    <div class="card-body p-0 overflow-auto" style="min-height: 220px;">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <tbody>
                    @foreach($probations as $prob)
                        @php
                            $dateStr = isset($prob->probation_end_date) && $prob->probation_end_date ? \Carbon\Carbon::parse($prob->probation_end_date)->format('d M Y') : '—';
                        @endphp
                        <tr>
                            <td class="py-2.5 ps-4 border-bottom" style="border-color: #f1f5f9;">
                                <div class="fw-semibold text-dark fs-13" style="color: #1e293b !important;">{{ $prob->full_name }}</div>
                            </td>
                            <td class="py-2.5 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                <span class="badge px-2.5 py-1 fs-11 fw-semibold rounded-pill" style="background: #fef3c7; color: #d97706;">{{ $dateStr }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

