@php
    $holidays = !empty($upcomingHolidays) && count($upcomingHolidays) > 0 ? $upcomingHolidays : [
        (object)['name' => 'Christmas Day', 'holiday_date' => '2026-12-25'],
        (object)['name' => 'New Year\'s Day', 'holiday_date' => '2027-01-01'],
        (object)['name' => 'Republic Day', 'holiday_date' => '2027-01-26'],
        (object)['name' => 'Holi Festival', 'holiday_date' => '2027-03-22'],
    ];
@endphp

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between border-bottom-0">
        <div class="d-flex align-items-center gap-2.5" style="gap: 10px;">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; background: #fff1f2; border-radius: 8px; color: #e11d48;">
                <i class="feather-gift fs-14"></i>
            </div>
            <h6 class="fw-bold mb-0 text-dark" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Upcoming Holidays</h6>
        </div>
        <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.holidays.index') ? route('hrms.holidays.index') : '#' }}" class="fs-12 fw-bold text-decoration-none" style="color: var(--bs-primary, #6337fa);">Calendar &rarr;</a>
    </div>

    <div class="card-body p-0 overflow-auto" style="min-height: 240px;">
        <div class="table-responsive">
            <table class="table align-middle mb-0 fs-13">
                <tbody>
                    @foreach($holidays as $hol)
                        @php
                            $dateFormatted = \Carbon\Carbon::parse($hol->holiday_date)->format('d M (D)');
                        @endphp
                        <tr>
                            <td class="py-3 ps-4 border-bottom" style="border-color: #f1f5f9;">
                                <div class="fw-bold text-dark fs-14" style="color: #1e293b !important; font-weight: 700;">{{ $hol->name }}</div>
                            </td>
                            <td class="py-3 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                <span class="fw-bold fs-13" style="color: var(--bs-primary, #4f46e5) !important;">{{ $dateFormatted }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

