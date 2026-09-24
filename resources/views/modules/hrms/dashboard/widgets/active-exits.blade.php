<div class="card border mb-0 h-100 shadow-sm">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="dash-card-icon-avatar bg-soft-danger text-danger">
                <i class="feather-log-out"></i>
            </span>
            <div>
                <h6 class="fw-bold mb-0 text-dark fs-14">Active Exit Pipeline</h6>
                <span class="fs-11 text-muted">Employees currently in offboarding clearance</span>
            </div>
        </div>
        <span class="badge bg-soft-danger text-danger rounded-pill px-2.5 py-1 fs-11 fw-bold">
            {{ count($activeExits ?? []) }} Active Exits
        </span>
    </div>

    <div class="card-body p-0 overflow-auto" style="max-height: 320px;">
        @if(!empty($activeExits) && count($activeExits) > 0)
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0 fs-12">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-2">Employee</th>
                            <th class="py-2">Department</th>
                            <th class="py-2">Status</th>
                            <th class="text-end pe-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activeExits as $exit)
                            <tr>
                                <td class="ps-3 py-2 fw-semibold text-dark">{{ $exit->employee?->full_name ?? 'Employee' }}</td>
                                <td class="py-2 text-muted">{{ $exit->employee?->department?->name ?? '—' }}</td>
                                <td class="py-2"><span class="badge bg-soft-danger text-danger px-2 py-0.5 fs-11">{{ ucfirst($exit->status ?? 'Initiated') }}</span></td>
                                <td class="text-end pe-3 py-2">
                                    <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.exits.index') ? route('hrms.exits.index') : url('/hrms/exits') }}" class="btn btn-xs btn-outline-danger rounded-pill">Clearance</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-4 text-muted fs-12">No active employee exits.</div>
        @endif
    </div>
</div>
