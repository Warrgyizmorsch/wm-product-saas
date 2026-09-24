@php
    $bDays = !empty($upcomingBirthdays) && count($upcomingBirthdays) > 0 ? $upcomingBirthdays : [
        (object)['full_name' => 'Rahul Sharma', 'date_of_birth' => '2026-09-28'],
        (object)['full_name' => 'Priya Patel', 'date_of_birth' => '2026-10-04'],
    ];
    $annivs = !empty($upcomingAnniversaries) && count($upcomingAnniversaries) > 0 ? $upcomingAnniversaries : [
        (object)['full_name' => 'Amit Kumar', 'date_of_joining' => '2026-10-12'],
    ];
@endphp

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between border-bottom-0">
        <div class="d-flex align-items-center gap-2.5" style="gap: 10px;">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; background: #fff7ed; border-radius: 8px; color: #ea580c;">
                <i class="feather-award fs-14"></i>
            </div>
            <h6 class="fw-bold mb-0 text-dark" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Celebrations This Month</h6>
        </div>
        <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #ffedd5; color: #c2410c;">
            {{ count($bDays) + count($annivs) }} Events
        </span>
    </div>

    <div class="card-body p-0 overflow-auto" style="min-height: 240px;">
        <div class="table-responsive">
            <table class="table align-middle mb-0 fs-13">
                <tbody>
                    @foreach($bDays as $bday)
                        <tr>
                            <td class="py-3 ps-4 border-bottom" style="border-color: #f1f5f9;">
                                <div class="fw-bold text-dark fs-14 d-inline-flex align-items-center gap-2" style="color: #1e293b !important; font-weight: 700;">
                                    <i class="feather-gift text-warning fs-15"></i>
                                    <span>{{ $bday->full_name }}</span>
                                </div>
                            </td>
                            <td class="py-3 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #fef3c7; color: #d97706;">Birthday</span>
                            </td>
                        </tr>
                    @endforeach
                    @foreach($annivs as $anniv)
                        <tr>
                            <td class="py-3 ps-4 border-bottom" style="border-color: #f1f5f9;">
                                <div class="fw-bold text-dark fs-14 d-inline-flex align-items-center gap-2" style="color: #1e293b !important; font-weight: 700;">
                                    <i class="feather-award text-primary fs-15"></i>
                                    <span>{{ $anniv->full_name }}</span>
                                </div>
                            </td>
                            <td class="py-3 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #e0e7ff; color: #4338ca;">Work Anniversary</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

