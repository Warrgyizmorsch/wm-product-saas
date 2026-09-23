<div class="card border mb-0 h-100 shadow-sm">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="dash-card-icon-avatar bg-soft-warning text-warning">
                <i class="feather-eye"></i>
            </span>
            <div>
                <h6 class="fw-bold mb-0 text-dark fs-14">Probation Review Watch</h6>
                <span class="fs-11 text-muted">Employees approaching probation end date</span>
            </div>
        </div>
        <span class="badge bg-soft-warning text-warning rounded-pill px-2.5 py-1 fs-11 fw-bold">
            {{ count($upcomingProbationEmployees ?? []) }} Employees
        </span>
    </div>

    <div class="card-body p-0 overflow-auto" style="max-height: 320px;">
        @if(!empty($upcomingProbationEmployees) && count($upcomingProbationEmployees) > 0)
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0 fs-12">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-2">Employee</th>
                            <th class="py-2">Department</th>
                            <th class="py-2">Probation End</th>
                            <th class="text-end pe-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($upcomingProbationEmployees as $emp)
                            <tr>
                                <td class="ps-3 py-2 fw-semibold text-dark">{{ $emp->full_name }}</td>
                                <td class="py-2 text-muted">{{ $emp->department?->name ?? '—' }}</td>
                                <td class="py-2"><span class="badge bg-soft-warning text-warning px-2 py-0.5 fs-11">{{ $emp->probation_end_date ? \Carbon\Carbon::parse($emp->probation_end_date)->format('d M Y') : '—' }}</span></td>
                                <td class="text-end pe-3 py-2">
                                    <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.employees.show') ? route('hrms.employees.show', $emp->id) : '#' }}" class="btn btn-xs btn-outline-primary rounded-pill">View Profile</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-12">No employees nearing probation end.</div>
        @endif
    </div>
</div>
