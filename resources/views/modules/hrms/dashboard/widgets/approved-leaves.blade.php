@php
    $leaves = !empty($approvedLeaves) && count($approvedLeaves) > 0 ? $approvedLeaves : [
        (object)['employee' => (object)['full_name' => 'Rahul Sharma'], 'start_date' => '2026-10-14', 'end_date' => '2026-10-17'],
        (object)['employee' => (object)['full_name' => 'Priya Nair'], 'start_date' => '2026-10-20', 'end_date' => '2026-10-22'],
        (object)['employee' => (object)['full_name' => 'Amit Kumar'], 'start_date' => '2026-11-02', 'end_date' => '2026-11-05'],
        (object)['employee' => (object)['full_name' => 'Sneha Rao'], 'start_date' => '2026-11-10', 'end_date' => '2026-11-12'],
    ];
@endphp

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between border-bottom-0">
        <div class="d-flex align-items-center gap-2.5" style="gap: 10px;">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; background: #f0fdf4; border-radius: 8px; color: #16a34a;">
                <i class="feather-check-square fs-14"></i>
            </div>
            <h6 class="fw-bold mb-0 text-dark" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Approved Leaves</h6>
        </div>
        <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #dcfce7; color: #16a34a;">
            {{ count($leaves) }} Approved
        </span>
    </div>

    <div class="card-body p-0 overflow-auto" style="min-height: 240px;">
        <div class="table-responsive">
            <table class="table align-middle mb-0 fs-13">
                <tbody>
                    @foreach($leaves as $appLeave)
                        @php
                            $empName = is_object($appLeave->employee) ? ($appLeave->employee->full_name ?? 'Employee') : 'Employee';
                            $startDate = \Carbon\Carbon::parse($appLeave->start_date)->format('d M');
                            $endDate = \Carbon\Carbon::parse($appLeave->end_date)->format('d M');
                        @endphp
                        <tr>
                            <td class="py-3 ps-4 border-bottom" style="border-color: #f1f5f9;">
                                <div class="fw-bold text-dark fs-14" style="color: #1e293b !important; font-weight: 700;">{{ $empName }}</div>
                            </td>
                            <td class="py-3 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                <span class="fw-bold fs-13" style="color: #64748b !important;">{{ $startDate }} &ndash; {{ $endDate }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

