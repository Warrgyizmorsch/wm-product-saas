<!-- PIP TAB -->
<div class="tab-pane fade {{ $activeTabName === 'pip' ? 'show active' : '' }}" id="pip-pane" role="tabpanel" aria-labelledby="pip-tab">
    <div class="card border-0 shadow-sm rounded-3 mt-3">
        <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold text-dark mb-0"><i class="feather-trending-up text-primary me-1.5"></i> {{ __('hrms.pip.title') }}</h6>
                <small class="text-muted fs-12">{{ __('hrms.pip.employee_tab_desc') }}</small>
            </div>
            @php
                $authUser = auth()->user();
                $canInitiatePip = $authUser && app(\App\Services\Access\AccessService::class)->allows($authUser, 'hrms.employees.update', ['tenant_id' => $authUser->tenant_id]);
            @endphp
            @if($canInitiatePip)
                <a href="{{ route('hrms.pip.index') }}" class="btn btn-sm btn-primary fw-bold px-3 py-1.5">
                    <i class="feather-plus me-1"></i> {{ __('hrms.pip.initiate_pip') }}
                </a>
            @endif
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
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">{{ __('hrms.pip.pip_number') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.pip.category_reason') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.common.duration') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.pip.objectives') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.pip.checkins') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.common.status') }}</th>
                            <th class="pe-3 py-3 text-end text-muted text-uppercase fs-11">{{ __('hrms.common.action') }}</th>
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
                                    <small class="text-muted fs-11">{{ $pPlan->duration_days }} {{ __('hrms.wfh.days_count') }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info px-2 py-1 fs-11">
                                        {{ $pPlan->objectives->where('status', 'achieved')->count() }} / {{ $pPlan->objectives->count() }} {{ __('hrms.pip.achieved') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1 fs-11">
                                        {{ $pPlan->checkins->count() }} {{ __('hrms.pip.checkins') }}
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
                                    <x-ui.action-dropdown viewUrl="{{ route('hrms.pip.show', $pPlan->id) }}" align="end" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="feather-trending-up fs-24 d-block mb-1 text-secondary"></i>
                                    {{ __('hrms.pip.no_records_for_employee') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
