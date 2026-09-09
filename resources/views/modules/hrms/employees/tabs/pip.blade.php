<!-- PIP TAB -->
<div class="tab-pane fade {{ $activeTabName === 'pip' ? 'show active' : '' }}" id="pip-pane" role="tabpanel" aria-labelledby="pip-tab">
    <div class="card border-0 shadow-sm rounded-3 mt-3">
        <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold text-dark mb-0"><i class="feather-trending-up text-primary me-1.5"></i> Performance Improvement Plans (PIP)</h6>
                <small class="text-muted fs-12">Performance improvement history, objectives, and evaluation milestones for this employee.</small>
            </div>
            <a href="{{ route('hrms.pip.index') }}" class="btn btn-sm btn-primary fw-bold px-3 py-1.5">
                <i class="feather-plus me-1"></i> Initiate PIP
            </a>
        </div>
        <div class="card-body p-0">
            @php
                $employeePips = \App\Domains\HRMS\Models\PerformanceImprovementPlan::where('employee_id', $employee->id)
                    ->with(['category', 'objectives', 'checkins'])
                    ->latest()
                    ->get();
            @endphp

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">PIP #</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Category / Reason</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Duration</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Objectives</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Check-ins</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Status</th>
                            <th class="pe-3 py-3 text-end text-muted text-uppercase fs-11">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employeePips as $pPlan)
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    <a href="{{ route('hrms.pip.show', $pPlan->id) }}" class="text-decoration-none text-primary fw-bold">
                                        {{ $pPlan->pip_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        {{ $pPlan->category?->name ?? $pPlan->reason_category ?? 'Performance' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark fs-12">{{ $pPlan->start_date->format('d M') }} - {{ $pPlan->end_date->format('d M, Y') }}</div>
                                    <small class="text-muted fs-11">{{ $pPlan->duration_days }} Days</small>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info px-2 py-1 fs-11">
                                        {{ $pPlan->objectives->where('status', 'achieved')->count() }} / {{ $pPlan->objectives->count() }} Achieved
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1 fs-11">
                                        {{ $pPlan->checkins->count() }} Check-ins
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match($pPlan->status) {
                                            'active' => 'bg-success-subtle text-success border border-success-subtle',
                                            'under_review' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                            'completed_success' => 'bg-info-subtle text-info border border-info-subtle',
                                            'extended' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                            'role_reassigned' => 'bg-purple-subtle text-purple border border-purple-subtle',
                                            'failed_terminated' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                            default => 'bg-light text-dark border'
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2.5 py-1 rounded-pill fs-11 fw-bold">
                                        {{ str_replace('_', ' ', ucfirst($pPlan->status)) }}
                                    </span>
                                </td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('hrms.pip.show', $pPlan->id) }}" class="btn btn-xs btn-outline-primary fw-bold px-2 py-1">
                                        <i class="feather-eye me-1"></i> Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="feather-trending-up fs-24 d-block mb-1 text-secondary"></i>
                                    No PIP records found for this employee.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
