@php
    $exits = !empty($activeExits) && count($activeExits) > 0 ? $activeExits : [
        (object)['employee' => (object)['full_name' => 'Sneha Rao'], 'status' => 'In_clearance'],
        (object)['employee' => (object)['full_name' => 'Vikram Patel'], 'status' => 'Initiated'],
        (object)['employee' => (object)['full_name' => 'Manish Gupta'], 'status' => 'Pending_fnf'],
        (object)['employee' => (object)['full_name' => 'Divya Saxena'], 'status' => 'In_clearance'],
    ];
@endphp

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-header bg-white pt-4 pb-3 px-4 d-flex align-items-center justify-content-between border-bottom-0">
        <div class="d-flex align-items-center gap-2.5" style="gap: 10px;">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; background: #fff1f2; border-radius: 8px; color: #e11d48;">
                <i class="feather-user-x fs-14"></i>
            </div>
            <h6 class="fw-bold mb-0 text-dark" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Active Exits & Offboarding</h6>
        </div>
        <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.exits.index') ? route('hrms.exits.index') : url('/hrms/exits') }}" class="fs-12 fw-bold text-decoration-none" style="color: var(--bs-primary, #6337fa);">Manage Exits &rarr;</a>
    </div>

    <div class="card-body p-0 overflow-auto" style="min-height: 240px;">
        <div class="table-responsive">
            <table class="table align-middle mb-0 fs-13">
                <tbody>
                    @foreach($exits as $ex)
                        @php
                            $empName = is_object($ex->employee) ? ($ex->employee->full_name ?? 'Employee') : 'Employee';
                            $statusStr = str_replace('_', ' ', ucfirst($ex->status ?? 'Initiated'));
                        @endphp
                        <tr>
                            <td class="py-3 ps-4 border-bottom" style="border-color: #f1f5f9;">
                                <div class="fw-bold text-dark fs-14" style="color: #1e293b !important; font-weight: 700;">{{ $empName }}</div>
                            </td>
                            <td class="py-3 pe-4 text-end border-bottom" style="border-color: #f1f5f9;">
                                <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #fff1f2; color: #e11d48;">{{ $statusStr }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

