<!-- PIP TAB -->
<div class="tab-pane fade {{ $activeTabName === 'pip' ? 'show active' : '' }}" id="pip-pane" role="tabpanel" aria-labelledby="pip-tab">
    <div class="card-custom mb-4">
        <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-custom-title mb-0">
                    <i class="feather-trending-up text-primary me-2"></i> {{ __('hrms.pip.title') }}
                </h5>
                <span class="text-muted fs-12">{{ __('hrms.pip.employee_tab_desc') }}</span>
            </div>
            @php
                $authUser = auth()->user();
                $canInitiatePip = $authUser && app(\App\Services\Access\AccessService::class)->allows($authUser, 'hrms.employees.update', ['tenant_id' => $authUser->tenant_id]);
            @endphp
            @if($canInitiatePip)
                <div>
                    <x-ui.button variant="primary" size="sm" icon="feather-plus" href="{{ route('hrms.pip.index') }}" class="fw-semibold">
                        {{ __('hrms.pip.initiate_pip') }}
                    </x-ui.button>
                </div>
            @endif
        </div>
        <div class="card-body p-0">
            @php
                $employeePips = $pipPlans ?? ($employee->pipPlans ?? collect());
            @endphp

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-13 text-dark" style="width: 100%;">
                    <thead class="table-light fs-11 text-uppercase tracking-wider">
                        <tr>
                            <th class="ps-3 py-3" style="width: 15%;">{{ __('hrms.pip.pip_number') }}</th>
                            <th class="py-3" style="width: 18%;">{{ __('hrms.pip.category_reason') }}</th>
                            <th class="py-3" style="width: 18%;">{{ __('hrms.common.duration') }}</th>
                            <th class="py-3 text-center" style="width: 14%;">{{ __('hrms.pip.objectives') }}</th>
                            <th class="py-3 text-center" style="width: 12%;">{{ __('hrms.pip.checkins') }}</th>
                            <th class="py-3 text-center" style="width: 13%;">{{ __('hrms.common.status') }}</th>
                            <th class="pe-3 py-3 text-end" style="width: 10%;">{{ __('hrms.common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employeePips as $pPlan)
                            @php
                                $badgeVariant = match($pPlan->status) {
                                    'active'            => 'success',
                                    'under_review'      => 'warning',
                                    'completed_success' => 'info',
                                    'extended'          => 'primary',
                                    'role_reassigned'   => 'secondary',
                                    'failed_terminated' => 'danger',
                                    default             => 'light'
                                };
                                $objectivesCount = $pPlan->objectives ? $pPlan->objectives->count() : 0;
                                $achievedCount = $pPlan->objectives ? $pPlan->objectives->where('status', 'achieved')->count() : 0;
                                $checkinsCount = $pPlan->checkins ? $pPlan->checkins->count() : 0;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('hrms.pip.show', $pPlan->id) }}" class="text-decoration-none text-primary fw-bold">
                                        {{ $pPlan->pip_number }}
                                    </a>
                                </td>
                                <td>
                                    <x-ui.badge soft variant="light" class="border px-2 py-1 text-dark">
                                        {{ $pPlan->category?->name ?? ($pPlan->reason_category ? __($pPlan->reason_category) : __('hrms.employees.tbl_performance')) }}
                                    </x-ui.badge>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark fs-12">
                                        {{ $pPlan->start_date ? $pPlan->start_date->format('d M') : __('hrms.common.na') }} - {{ $pPlan->end_date ? $pPlan->end_date->format('d M, Y') : __('hrms.common.na') }}
                                    </div>
                                    <small class="text-muted fs-11">{{ $pPlan->duration_days ?? 0 }} {{ __('hrms.wfh.days_count') }}</small>
                                </td>
                                <td class="text-center">
                                    <x-ui.badge soft variant="info" class="fs-11 px-2 py-1">
                                        {{ $achievedCount }} / {{ $objectivesCount }} {{ __('hrms.pip.achieved') }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-center">
                                    <x-ui.badge soft variant="secondary" class="fs-11 px-2 py-1">
                                        {{ $checkinsCount }} {{ __('hrms.pip.checkins') }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-center">
                                    <x-ui.badge soft :variant="$badgeVariant" class="px-2.5 py-1 text-uppercase fs-11 fw-bold">
                                        {{ __(ucwords(str_replace('_', ' ', (string) $pPlan->status))) }}
                                    </x-ui.badge>
                                </td>
                                <td class="pe-3 text-end">
                                    <x-ui.action-dropdown viewUrl="{{ route('hrms.pip.show', $pPlan->id) }}" align="end" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center">
                                        <i class="feather-trending-up fs-18"></i>
                                    </div>
                                    <h6 class="fw-bold mb-1 text-dark fs-13">{{ __('hrms.pip.no_records_for_employee') }}</h6>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

