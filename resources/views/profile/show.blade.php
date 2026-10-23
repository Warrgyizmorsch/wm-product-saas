@extends('layouts.duralux')

@section('title', __('hrms.employees.my_profile') . ' | SaaS ERP')
@section('page-title', __('hrms.employees.my_profile'))
@section('breadcrumb', __('hrms.employees.account') . ' / ' . __('hrms.employees.my_profile'))

@php
    $rawTab = request('tab', request('active_tab', session('active_tab')));
    $activeTabName = $rawTab ? str_replace(['#', '-pane', 'tab-'], '', $rawTab) : 'overview';
    $authUser = auth()->user();
    $isHrOrAdmin = $authUser && app(\App\Services\Access\AccessService::class)->allows($authUser, 'hrms.employees.update', ['tenant_id' => $authUser->tenant_id]);
    $isOwnProfile = true;
    $canEditProfile = true;
    $showOfficeSection = false;
@endphp

@if($employee)
    @php
        $formatLeaveRuleText = static function (?array $rules): array {
            if (empty($rules)) return [];
            $humanize = static fn ($value) => ucwords(str_replace('_', ' ', (string) $value));
            $formatNumber = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            $items = [];
            if (!empty($rules['accrual'])) {
                $accrual = $rules['accrual'];
                $unit = $humanize($accrual['calculate_in'] ?? 'days');
                $quota = ($accrual['quota_type'] ?? 'fixed') === 'unlimited' ? 'Unlimited' : $formatNumber($accrual['quota_value'] ?? 0) . ' ' . strtolower($unit);
                $rate = $humanize($accrual['rate'] ?? 'immediate');
                $items[] = ['label' => 'Accrual', 'value' => "{$quota} • {$rate}"];
                if (!empty($accrual['limit_carry'])) {
                    $items[] = ['label' => 'Max Balance', 'value' => ($accrual['max_accum'] ?? 0) . ' days'];
                }
            }
            if (!empty($rules['application'])) {
                $application = $rules['application'];
                $duration = ($application['min_duration'] ?? 1) . '–' . ($application['max_duration'] ?? 10) . ' days';
                $items[] = ['label' => 'Duration', 'value' => $duration];
                if (!empty($application['apply_in_advance'])) {
                    $items[] = ['label' => 'Advance', 'value' => ($application['advance_days'] ?? 0) . ' days'];
                }
                if (!empty($application['require_attachment'])) {
                    $items[] = ['label' => 'Attachment', 'value' => 'After ' . ($application['attachment_days'] ?? 0) . ' days'];
                }
            }
            if (!empty($rules['approval'])) {
                $approval = $rules['approval'];
                $workflow = $humanize($approval['workflow_level'] ?? '1_level');
                if (($approval['workflow_level'] ?? null) === 'auto') {
                    $items[] = ['label' => 'Approval', 'value' => 'Auto approved'];
                } else {
                    $approvers = array_filter([
                        $humanize($approval['first_approver'] ?? ''),
                        ($approval['workflow_level'] ?? '') === '2_level' ? $humanize($approval['second_approver'] ?? '') : null,
                    ]);
                    $items[] = ['label' => 'Approval', 'value' => $workflow . (!empty($approvers) ? ' • ' . implode(' → ', $approvers) : '')];
                }
            }
            if (!empty($rules['yearend'])) {
                $yearend = $rules['yearend'];
                $action = $humanize($yearend['action'] ?? 'lapse');
                $limit = match ($yearend['action'] ?? null) {
                    'carry_forward' => ' • Max ' . ($yearend['max_carry'] ?? 0) . ' days',
                    'encash' => ' • Max ' . ($yearend['max_encash'] ?? 0) . ' days',
                    default => '',
                };
                $items[] = ['label' => 'Year End', 'value' => $action . $limit];
            }
            if (!empty($rules['probation'])) {
                $probation = $rules['probation'];
                $value = match ($probation['rule'] ?? 'allow') {
                    'disallow' => 'Not allowed',
                    'allow_after_months' => 'After ' . ($probation['months'] ?? 0) . ' months',
                    default => 'Allowed',
                };
                $items[] = ['label' => 'Probation', 'value' => $value];
            }
            if (!empty($rules['notice'])) {
                $notice = $rules['notice'];
                $value = match ($notice['rule'] ?? 'allow') {
                    'disallow' => 'Not allowed',
                    'special_approval' => 'Special approval',
                    default => 'Allowed',
                };
                $items[] = ['label' => 'Notice', 'value' => $value];
            }
            return array_slice($items, 0, 6);
        };

        $formatLeaveRuleDetails = static function (?array $rules): array {
            if (empty($rules)) return [];
            $humanize = static fn ($value) => ucwords(str_replace('_', ' ', (string) $value));
            $formatNumber = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            $yesNo = static fn ($value) => $value ? 'Yes' : 'No';
            $sections = [];
            if (!empty($rules['accrual'])) {
                $accrual = $rules['accrual'];
                $quota = ($accrual['quota_type'] ?? 'fixed') === 'unlimited' ? 'Unlimited' : $formatNumber($accrual['quota_value'] ?? 0) . ' ' . strtolower($humanize($accrual['calculate_in'] ?? 'days'));
                $rows = [
                    ['label' => 'Calculate In', 'value' => $humanize($accrual['calculate_in'] ?? 'days')],
                    ['label' => 'Quota', 'value' => $quota],
                    ['label' => 'Accrual Rate', 'value' => $humanize($accrual['rate'] ?? 'immediate')],
                    ['label' => 'Limit Max Balance', 'value' => $yesNo(!empty($accrual['limit_carry']))],
                ];
                if (($accrual['rate'] ?? null) === 'attendance') {
                    $rows[] = ['label' => 'Attendance Earning', 'value' => ($accrual['attendance_earn'] ?? 1) . ' day per ' . ($accrual['attendance_period'] ?? 20) . ' present days'];
                }
                if (!empty($accrual['limit_carry'])) {
                    $rows[] = ['label' => 'Maximum Balance', 'value' => ($accrual['max_accum'] ?? 0) . ' days'];
                }
                $sections[] = ['title' => 'Accrual', 'icon' => 'feather-calendar', 'rows' => $rows];
            }
            return $sections;
        };

        $formatLeaveRulePoints = static function (?array $rules): array {
            if (empty($rules)) return [];
            $humanize = static fn ($value) => strtolower(str_replace('_', ' ', (string) $value));
            $formatNumber = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            $sections = [];
            if (!empty($rules['application'])) {
                $application = $rules['application'];
                $points = [];
                if (!empty($application['apply_in_advance'])) {
                    $points[] = 'You have to apply for this leave at least ' . ($application['advance_days'] ?? 0) . ' day(s) in advance.';
                } else {
                    $points[] = 'You can apply for this leave without an advance-day restriction.';
                }
                $points[] = 'One request can be from ' . ($application['min_duration'] ?? 1) . ' to ' . ($application['max_duration'] ?? 10) . ' day(s).';
                if (!empty($application['require_attachment'])) {
                    $points[] = 'You must attach supporting documents when the leave duration is more than ' . ($application['attachment_days'] ?? 0) . ' day(s).';
                }
                $sections[] = ['title' => 'Application Rules', 'icon' => 'feather-file-text', 'points' => $points];
            }
            return $sections;
        };
    @endphp
@endif

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('account.settings') }}" variant="secondary" icon="feather-settings">
            {{ __('hrms.employees.account_settings') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
    @if($employee)
        <style>
            /* Document Badge Styles */
            .badge-mandatory { background-color: rgba(239, 68, 68, 0.08) !important; color: #ef4444 !important; font-weight: 600; }
            .badge-optional { background-color: rgba(100, 116, 139, 0.08) !important; color: #64748b !important; font-weight: 500; }
            .badge-expiry { background-color: rgba(245, 158, 11, 0.08) !important; color: #f59e0b !important; font-weight: 500; }
            .info-label { color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 4px; }
            .info-value { color: #0f172a; font-size: 14px; font-weight: 600; }
            .card-custom { border: 1px solid #e2e8f0; border-radius: 16px; background-color: #fff; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.02); margin-bottom: 24px; }
            .card-custom-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
            .card-custom-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; }
            .file-card-container { border: 1px solid #e2e8f0 !important; background-color: #f8fafc; border-radius: 8px; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; max-width: 280px; transition: all 0.2s ease-in-out; }
            .file-card-container:hover { border-color: #cbd5e1 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
            .file-action-btn { width: 28px; height: 28px; min-width: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50% !important; border: 1px solid #e2e8f0 !important; background-color: #ffffff !important; color: #64748b !important; transition: all 0.2s ease; }
            .file-action-btn:hover { color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; background-color: color-mix(in srgb, var(--bs-primary) 5%, transparent) !important; }
        </style>
    @endif

    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">

        <!-- Notification Banners -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" :title="session('success')" />
            <x-ui.alert variant="success" icon="feather-check-circle" class="mb-4" dismissible>
                {{ session('success') }}
            </x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" :title="$errors->first()" />
            <x-ui.alert variant="danger" icon="feather-alert-triangle" class="mb-4" dismissible>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        @if($employee && $employee->pendingProfileUpdateRequest)
            @php
                $pendingReq = $employee->pendingProfileUpdateRequest;
                $pChanges = $pendingReq->changes ?? [];
            @endphp
            <div class="alert alert-warning border border-warning-subtle rounded-3 p-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 bg-warning-subtle shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 44px; height: 44px; min-width: 44px; min-height: 44px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff;">
                        <i class="feather-clock fs-18 text-white"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1 fs-14">{{ __('hrms.employees.pending_profile_request_title') }}</h6>
                        <p class="text-muted fs-12 mb-0">
                            {{ __('hrms.employees.pending_profile_request_self_desc', ['date' => $pendingReq->created_at ? $pendingReq->created_at->format('M d, Y h:i A') : __('hrms.common.na')]) }}
                        </p>
                    </div>
                </div>
                <x-ui.button type="button" variant="primary" size="sm" icon="feather-eye" data-bs-toggle="modal" data-bs-target="#viewMyPendingRequestModal" class="fw-bold text-nowrap">
                    {{ __('hrms.employees.view_requested_changes') }}
                </x-ui.button>
            </div>
        @endif

        <!-- Profile Details Header Grid -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar-container flex-shrink-0" style="width: 72px; height: 72px;">
                    @if($employee && $employee->photo)
                        <img src="{{ asset('storage/' . $employee->photo) }}" alt="{{ $employee->display_name }}" class="rounded-circle shadow-sm" style="width: 72px; height: 72px; min-width: 72px; min-height: 72px; max-width: 72px; max-height: 72px; object-fit: cover; border: 3px solid #e2e8f0; display: block;" onerror="this.onerror=null; this.src='/assets/images/avatar/default.png';">
                    @else
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="rounded-circle shadow-sm" style="width: 72px; height: 72px; min-width: 72px; min-height: 72px; max-width: 72px; max-height: 72px; object-fit: cover; border: 3px solid #e2e8f0; display: block;" onerror="this.onerror=null; this.src='/assets/images/avatar/default.png';">
                    @endif
                </div>
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0">{{ $employee ? $employee->display_name : $user->name }}</h4>
                        <x-ui.badge soft variant="success">{{ __('hrms.common.active') }}</x-ui.badge>
                        <x-ui.badge soft variant="primary">{{ __($user->primaryRole?->name ?? ucfirst($user->role ?: 'User')) }}</x-ui.badge>
                        @if ($tenant)
                            <x-ui.badge soft variant="info">
                                <i class="feather-briefcase me-1"></i>{{ $tenant->name }}
                            </x-ui.badge>
                        @endif
                    </div>
                    <p class="text-muted fs-13 mb-0">
                        @if($employee)
                            @if($employee->employee_id)
                                <span><i class="feather-tag me-1"></i><code class="fs-13 fw-bold">{{ $employee->employee_id }}</code></span>
                                <span class="ms-2">&bull; </span>
                            @endif
                            @if($employee->job_title)
                                <span><i class="feather-briefcase me-1"></i>{{ $employee->job_title }}</span>
                                <span class="ms-2">&bull; </span>
                            @endif
                        @endif
                        <span><i class="feather-mail me-1"></i>{{ $employee && $employee->office_email ? $employee->office_email : $user->email }}</span>
                        @if ($employee && $employee->personal_mobile_number)
                            <span class="ms-2">&bull; <i class="feather-phone me-1"></i>{{ $employee->personal_mobile_number }}</span>
                        @elseif ($user->effective_phone)
                            <span class="ms-2">&bull; <i class="feather-phone me-1"></i>{{ $user->effective_phone }}</span>
                        @endif
                        @if ($employee && $employee->department)
                            <span class="ms-2">&bull; <i class="feather-layers me-1"></i>{{ $employee->department->name }}</span>
                        @elseif ($user->department)
                            <span class="ms-2">&bull; <i class="feather-layers me-1"></i>{{ $user->department->name }}</span>
                        @endif
                        <span class="ms-2">&bull; <i class="feather-hash me-1"></i>{{ __('hrms.employees.lbl_user_id') }}: #{{ $user->id }}</span>
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($employee)
                    <x-ui.button type="button" variant="light-brand" icon="feather-edit" data-bs-toggle="modal" data-bs-target="#editEmployeeModal">
                        {{ __('hrms.employees.edit_profile') }}
                    </x-ui.button>
                @else
                    <x-ui.button type="button" variant="light-brand" icon="feather-edit" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        {{ __('hrms.employees.edit_profile') }}
                    </x-ui.button>
                @endif
            </div>
        </div>

        @if($employee)
            <!-- TAB NAVIGATION FOR EMPLOYEES -->
            @php
                $employeeProfileTabs = [
                    ['id' => 'overview-pane', 'label' => __('hrms.employees.tab_overview'), 'active' => ($activeTabName === 'overview'), 'icon' => 'feather-user'],
                    ['id' => 'compensation-pane', 'label' => __('hrms.employees.tab_compensation'), 'active' => ($activeTabName === 'compensation'), 'icon' => 'feather-dollar-sign'],
                    ['id' => 'documents-pane', 'label' => __('hrms.employees.tab_documents'), 'active' => ($activeTabName === 'documents'), 'icon' => 'feather-file-text'],
                    ['id' => 'probation-pane', 'label' => __('hrms.employees.tab_probation'), 'active' => ($activeTabName === 'probation'), 'icon' => 'feather-award'],
                    ['id' => 'exit-clearance-pane', 'label' => __('hrms.employees.tab_exit_noc'), 'active' => ($activeTabName === 'exit-clearance'), 'icon' => 'feather-log-out'],
                    ['id' => 'pip-pane', 'label' => __('hrms.employees.tab_pip'), 'active' => ($activeTabName === 'pip'), 'icon' => 'feather-trending-up'],
                    ['id' => 'security-pane', 'label' => __('hrms.employees.tab_security'), 'active' => ($activeTabName === 'security'), 'icon' => 'feather-lock'],
                    ['id' => 'activity-pane', 'label' => __('hrms.employees.tab_activity'), 'active' => ($activeTabName === 'activity'), 'icon' => 'feather-activity'],
                ];
            @endphp
            <x-ui.horizontal-tabs id="profileTabs" :tabs="$employeeProfileTabs" :syncUrl="true" />

            <!-- TAB CONTENT CONTAINER FOR EMPLOYEES -->
            <div class="tab-content" id="profileTabsContent">
                @include('modules.hrms.employees.tabs.overview')
                @include('modules.hrms.employees.tabs.compensation')
                @include('modules.hrms.employees.tabs.documents')
                @include('modules.hrms.employees.tabs.penalization')
                @include('modules.hrms.employees.tabs.probation')
                @include('modules.hrms.employees.tabs.exit-clearance')
                @include('modules.hrms.employees.tabs.pip')

                <!-- Security & Password Tab Pane -->
                <div class="tab-pane fade {{ $activeTabName === 'security' ? 'show active' : '' }}" id="security-pane" role="tabpanel" aria-labelledby="security-tab">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <x-ui.odoo-form-ui type="sheet">
                                <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                                    <i class="feather-key text-primary me-2"></i>{{ __('hrms.security.change_password') }}
                                </h5>

                                <form action="{{ route('profile.password.update') }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('hrms.security.current_password')" name="current_password" id="current_password" :placeholder="__('hrms.security.enter_current_password')" :required="true" :error-text="$errors->first('current_password')" />

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('hrms.security.new_password')" name="password" id="password" :placeholder="__('hrms.security.minimum_8_chars')" :required="true" :helperText="__('hrms.security.password_min_length')" :error-text="$errors->first('password')" />

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('hrms.security.confirm_password')" name="password_confirmation" id="password_confirmation" :placeholder="__('hrms.security.repeat_new_password')" :required="true" />

                                    <div class="d-flex justify-content-end mt-4">
                                        <x-ui.button type="submit" variant="primary" icon="feather-save">
                                            {{ __('hrms.security.update_password') }}
                                        </x-ui.button>
                                    </div>
                                </form>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-lg-5">
                            <div class="bg-light p-4 rounded border">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-shield text-primary me-2"></i>{{ __('hrms.security.session_security_info') }}
                                </h6>
                                <div class="d-flex align-items-start gap-3 mb-4">
                                    <div class="p-2 rounded-circle bg-soft-success text-success">
                                        <i class="feather-monitor fs-20"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-14">{{ __('hrms.security.current_session') }}</div>
                                        <div class="text-muted fs-12">{{ __('hrms.security.ip_label') }}: {{ request()->ip() }}</div>
                                        <div class="text-muted fs-11 mt-1 text-truncate" style="max-width: 260px;" title="{{ request()->userAgent() }}">
                                            {{ request()->userAgent() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-soft-primary border-0 fs-12 mb-0">
                                    <div class="fw-bold mb-1"><i class="feather-info me-1"></i>{{ __('hrms.security.password_guidelines') }}</div>
                                    <ul class="mb-0 ps-3">
                                        <li>{{ __('hrms.security.guideline_min_chars') }}</li>
                                        <li>{{ __('hrms.security.guideline_mix_chars') }}</li>
                                        <li>{{ __('hrms.security.guideline_reuse') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity & Sessions Tab Pane -->
                <div class="tab-pane fade {{ $activeTabName === 'activity' ? 'show active' : '' }}" id="activity-pane" role="tabpanel" aria-labelledby="activity-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">{{ __('hrms.security.recent_activity') }}</h5>
                    </div>

                    @if ($recentActivity->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th style="width: 30%">{{ __('hrms.security.action') }}</th>
                                        <th style="width: 40%">{{ __('hrms.security.target_scope') }}</th>
                                        <th style="width: 30%">{{ __('hrms.security.timestamp') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentActivity as $log)
                                        <tr>
                                            <td>
                                                <x-ui.badge soft variant="primary" class="fw-bold">
                                                    {{ __(ucwords(str_replace(['.', '_'], ' ', $log->action))) }}
                                                </x-ui.badge>
                                            </td>
                                            <td class="fs-13 text-muted">
                                                {{ class_basename($log->subject_type ?: 'System') }}
                                                @if ($log->subject_id)
                                                    #{{ $log->subject_id }}
                                                @endif
                                            </td>
                                            <td class="fs-12 text-muted">
                                                {{ $log->created_at ? $log->created_at->format('d M, Y h:i A') : __('hrms.common.na') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <div class="p-4 text-center border rounded bg-light text-muted fs-13">
                            <i class="feather-info me-2 text-primary"></i>{{ __('hrms.security.no_recent_activity') }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- EDIT EMPLOYEE MODAL -->
            <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="editEmployeeModalLabel">
                                <i class="feather-edit-3 me-2 text-primary"></i>{{ __('hrms.employees.edit_profile') }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form
                            id="editEmployeeForm"
                            action="{{ route('hrms.employees.update', ['employee' => $employee->id]) }}"
                            method="POST"
                            enctype="multipart/form-data"
                            style="display: flex; flex-direction: column; max-height: calc(100vh - 3.5rem); overflow: hidden;"
                        >
                            @csrf
                            <input type="hidden" name="form_mode" value="edit">
                            <input type="hidden" name="editing_employee_id" id="editing_employee_id" value="{{ $employee->id }}">
                            <div class="modal-body p-4" style="overflow-y: auto; max-height: calc(85vh - 120px);">
                                @include('modules.hrms.employees.form-fields', ['mode' => 'edit', 'employee' => $employee, 'isHrOrAdmin' => $showOfficeSection])
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                                <x-ui.button type="submit" variant="primary">{{ __('hrms.employees.mdl_btn_update_employee') }}</x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        @else
            <!-- TAB NAVIGATION FOR NON-EMPLOYEES -->
            <x-ui.horizontal-tabs id="profileTabs" :tabs="[
                ['id' => 'tab-overview', 'label' => __('hrms.employees.tab_overview'), 'active' => ($activeTabName === 'overview'), 'icon' => 'feather-user'],
                ['id' => 'tab-security', 'label' => __('hrms.employees.tab_security'), 'active' => ($activeTabName === 'security'), 'icon' => 'feather-lock'],
                ['id' => 'tab-activity', 'label' => __('hrms.employees.tab_activity'), 'active' => ($activeTabName === 'activity'), 'icon' => 'feather-activity']
            ]" :syncUrl="true" />

            <!-- TAB CONTENT CONTAINER FOR NON-EMPLOYEES -->
            <div class="tab-content mt-5">

                <!-- 1. OVERVIEW TAB -->
                <div class="tab-pane fade {{ $activeTabName === 'overview' ? 'show active' : '' }}" id="tab-overview" role="tabpanel" aria-labelledby="tab-overview-tab">
                    <x-ui.odoo-form-ui type="sheet">
                        <div class="row g-4 mb-4">
                            <!-- Left Column: Personal & Account Details -->
                            <div class="col-md-6 border-end">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-user text-primary me-2"></i>{{ __('hrms.employees.personal_account_details') }}
                                </h6>
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.full_name')" name="view_name" :value="$user->name" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.email_address')" name="view_email" :value="$user->email" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.phone_mobile')" name="view_phone" :value="$user->effective_phone ?: __('hrms.common.not_set')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.assigned_role')" name="view_role" :value="$user->primaryRole?->name ?? ucfirst($user->role ?: 'User')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.system_user_id')" name="view_id" :value="'#' . $user->id" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.registered_on')" name="view_registered" :value="$user->created_at ? $user->created_at->format('M d, Y h:i A') : __('hrms.common.na')" :readonly="true" />
                            </div>

                            <!-- Right Column: Organization Context -->
                            <div class="col-md-6">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-briefcase text-primary me-2"></i>{{ __('hrms.employees.org_context') }}
                                </h6>
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.tenant_workspace')" name="view_tenant" :value="$tenant?->name ?? 'Default Tenant'" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.tenant_identifier')" name="view_slug" :value="$tenant?->slug ?? 'default'" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.company')" name="view_company" :value="$user->company?->company_name ?? __('hrms.employees.default_company')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.branch')" name="view_branch" :value="$user->branch?->branch_name ?? __('hrms.employees.default_branch')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.tbl_department')" name="view_department" :value="$user->department?->name ?? __('hrms.employees.not_assigned')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.subscription_plan')" name="view_plan" :value="ucfirst($tenant?->plan ?? 'Enterprise')" :readonly="true" />
                            </div>
                        </div>

                        <!-- Bottom Section: Regional & System Preferences -->
                        <div class="border-top pt-4 mt-3">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="feather-globe text-primary me-2"></i>{{ __('hrms.employees.regional_system_preferences') }}
                            </h6>
                            <div class="row g-4">
                                <div class="col-md-6 border-end">
                                    <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.system_timezone')" name="view_timezone" :value="$tenant?->timezone ?? config('app.timezone', 'UTC')" :readonly="true" />
                                    <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.system_language')" name="view_locale" :value="strtoupper($tenant?->locale ?? config('app.locale', 'en'))" :readonly="true" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.default_currency')" name="view_currency" :value="$tenant?->settings['currency'] ?? 'INR (₹)'" :readonly="true" />
                                    <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.financial_year')" name="view_fy" :value="$tenant?->settings['financial_year'] ?? '2026-2027'" :readonly="true" />
                                </div>
                            </div>
                        </div>
                    </x-ui.odoo-form-ui>
                </div>

                <!-- 2. SECURITY & PASSWORD TAB -->
                <div class="tab-pane fade {{ $activeTabName === 'security' ? 'show active' : '' }}" id="tab-security" role="tabpanel" aria-labelledby="tab-security-tab">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <x-ui.odoo-form-ui type="sheet">
                                <h5 class="fw-bold text-dark mb-4 pb-2 border-bottom">
                                    <i class="feather-key text-primary me-2"></i>{{ __('hrms.security.change_password') }}
                                </h5>

                                <form action="{{ route('profile.password.update') }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('hrms.security.current_password')" name="current_password" id="current_password_alt" :placeholder="__('hrms.security.enter_current_password')" :required="true" :error-text="$errors->first('current_password')" />

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('hrms.security.new_password')" name="password" id="password_alt" :placeholder="__('hrms.security.minimum_8_chars')" :required="true" :helperText="__('hrms.security.password_min_length')" :error-text="$errors->first('password')" />

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('hrms.security.confirm_password')" name="password_confirmation" id="password_confirmation_alt" :placeholder="__('hrms.security.repeat_new_password')" :required="true" />

                                    <div class="d-flex justify-content-end mt-4">
                                        <x-ui.button type="submit" variant="primary" icon="feather-save">
                                            {{ __('hrms.security.update_password') }}
                                        </x-ui.button>
                                    </div>
                                </form>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-lg-5">
                            <div class="bg-light p-4 rounded border">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-shield text-primary me-2"></i>{{ __('hrms.security.session_security_info') }}
                                </h6>
                                <div class="d-flex align-items-start gap-3 mb-4">
                                    <div class="p-2 rounded-circle bg-soft-success text-success">
                                        <i class="feather-monitor fs-20"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-14">{{ __('hrms.security.current_session') }}</div>
                                        <div class="text-muted fs-12">{{ __('hrms.security.ip_label') }}: {{ request()->ip() }}</div>
                                        <div class="text-muted fs-11 mt-1 text-truncate" style="max-width: 260px;" title="{{ request()->userAgent() }}">
                                            {{ request()->userAgent() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-soft-primary border-0 fs-12 mb-0">
                                    <div class="fw-bold mb-1"><i class="feather-info me-1"></i>{{ __('hrms.security.password_guidelines') }}</div>
                                    <ul class="mb-0 ps-3">
                                        <li>{{ __('hrms.security.guideline_min_chars') }}</li>
                                        <li>{{ __('hrms.security.guideline_mix_chars') }}</li>
                                        <li>{{ __('hrms.security.guideline_reuse') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. ACTIVITY & SESSIONS TAB -->
                <div class="tab-pane fade {{ $activeTabName === 'activity' ? 'show active' : '' }}" id="tab-activity" role="tabpanel" aria-labelledby="tab-activity-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">{{ __('hrms.security.recent_activity') }}</h5>
                    </div>

                    @if ($recentActivity->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th style="width: 30%">{{ __('hrms.security.action') }}</th>
                                        <th style="width: 40%">{{ __('hrms.security.target_scope') }}</th>
                                        <th style="width: 30%">{{ __('hrms.security.timestamp') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentActivity as $log)
                                        <tr>
                                            <td>
                                                <x-ui.badge soft variant="primary" class="fw-bold">
                                                    {{ __(ucwords(str_replace(['.', '_'], ' ', $log->action))) }}
                                                </x-ui.badge>
                                            </td>
                                            <td class="fs-13 text-muted">
                                                {{ class_basename($log->subject_type ?: 'System') }}
                                                @if ($log->subject_id)
                                                    #{{ $log->subject_id }}
                                                @endif
                                            </td>
                                            <td class="fs-12 text-muted">
                                                {{ $log->created_at ? $log->created_at->format('d M, Y h:i A') : __('hrms.common.na') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <div class="p-4 text-center border rounded bg-light text-muted fs-13">
                            <i class="feather-info me-2 text-primary"></i>{{ __('hrms.security.no_recent_activity') }}
                        </div>
                    @endif
                </div>

            </div>

            <!-- Edit Profile Modal for Non-Employees -->
            <x-ui.modal id="editProfileModal" :title="__('hrms.employees.edit_profile')" :centered="true">
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" id="editProfileForm">
                    @csrf
                    @method('PUT')

                    <x-ui.odoo-form-ui type="sheet">
                        <x-ui.odoo-form-ui type="input" :label="__('hrms.employees.full_name')" name="name" id="edit_name" :value="old('name', $user->name)" :required="true" :error-text="$errors->first('name')" />

                        <x-ui.odoo-form-ui type="input" inputType="email" :label="__('hrms.employees.email_address')" name="email" id="edit_email" :value="old('email', $user->email)" :required="true" :error-text="$errors->first('email')" />

                        <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('hrms.employees.phone_mobile')" name="phone" id="edit_phone" :value="old('phone', $user->effective_phone)" placeholder="+91 9876543210" :error-text="$errors->first('phone')" />

                        <x-ui.odoo-form-ui type="file" :label="__('hrms.employees.profile_photo')" name="avatar" id="edit_avatar" :placeholder="__('hrms.employees.photo_placeholder')" :error-text="$errors->first('avatar')" />
                    </x-ui.odoo-form-ui>
                </form>

                <x-slot name="footer">
                    <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">
                        {{ __('hrms.common.cancel') }}
                    </x-ui.button>
                    <x-ui.button type="button" variant="primary" icon="feather-save" onclick="document.getElementById('editProfileForm').submit();">
                        {{ __('hrms.common.save_changes') }}
                    </x-ui.button>
                </x-slot>
            </x-ui.modal>
        @endif

        @if($employee && $employee->pendingProfileUpdateRequest)
            @php
                $pendingReq = $employee->pendingProfileUpdateRequest;
                $pChanges = $pendingReq->changes ?? [];
            @endphp
            <!-- MODAL FOR EMPLOYEE TO VIEW THEIR PENDING CHANGES -->
            <x-ui.modal id="viewMyPendingRequestModal" size="lg" :centered="true" :title="'<i class=\'feather-clock me-2 text-warning\'></i>' . __('hrms.employees.pending_profile_request_title')">
                <div class="border rounded-3 overflow-hidden mb-3">
                    <table class="table table-bordered align-middle mb-0 fs-13" style="table-layout: fixed; width: 100%;">
                        <thead class="bg-light text-uppercase fs-11 text-muted">
                            <tr>
                                <th style="width: 28%;" class="ps-3">{{ __('hrms.employees.field_name') }}</th>
                                <th style="width: 36%;" class="text-danger bg-soft-danger">{{ __('hrms.employees.current_value') }}</th>
                                <th style="width: 36%;" class="text-success bg-soft-success">{{ __('hrms.employees.requested_new_value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pChanges as $fKey => $fData)
                                <tr>
                                    <td class="fw-bold text-dark ps-3 text-break" style="white-space: normal; word-break: break-word;">{{ $fData['label'] ?? ucwords(str_replace('_', ' ', $fKey)) }}</td>
                                    <td class="text-muted text-break" style="white-space: normal; word-break: break-word;">
                                        @if(!empty($fData['is_image']))
                                            @if(!empty($fData['old']) && $fData['old'] !== '—')
                                                <img src="{{ asset('storage/' . $fData['old']) }}" alt="Old Photo" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px;">
                                            @else
                                                <span class="text-muted">{{ __('hrms.employees.no_photo') }}</span>
                                            @endif
                                        @else
                                            @if(empty($fData['old']) || $fData['old'] === '—')
                                                <span class="text-muted">&mdash;</span>
                                            @else
                                                <span class="text-danger text-decoration-line-through d-inline-block" style="line-height: 1.45;">{{ $fData['old'] }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="fw-bold text-success text-break" style="white-space: normal; word-break: break-word;">
                                        @if(!empty($fData['is_image']))
                                            <img src="{{ asset('storage/' . $fData['new']) }}" alt="New Photo" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 2px solid #22c55e;">
                                        @else
                                            @if(empty($fData['new']) || $fData['new'] === '—')
                                                <span class="text-muted">&mdash;</span>
                                            @else
                                                <div class="p-2 rounded-2 bg-success-subtle text-success border border-success-subtle fs-13 fw-semibold text-break d-block" style="white-space: normal; word-break: break-word; line-height: 1.45;">{{ $fData['new'] }}</div>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-slot name="footer">
                    <x-ui.button type="button" variant="light" class="border" data-bs-dismiss="modal">
                        {{ __('hrms.common.close') }}
                    </x-ui.button>
                </x-slot>
            </x-ui.modal>
        @endif

    </div>
@endsection

@push('scripts')
    @if($employee)
        <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
        <script>
            $(document).ready(function() {
                // Move all modals to body root to prevent Bootstrap backdrop overlay / blur issues
                $('.modal').each(function() {
                    $(this).appendTo('body');
                });

                // Initialize select2 inside edit modal with dropdownParent
                $('#editEmployeeModal select').each(function() {
                    var $select = $(this);
                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }
                    if ($.fn.select2) {
                        $select.select2({
                            dropdownParent: $select.closest('.modal-content')
                        });
                    }
                });

                // Keep active tab on refresh / redirect
                const urlParams = new URLSearchParams(window.location.search);
                const activeTab = urlParams.get('tab');
                if (activeTab) {
                    const cleanTab = activeTab.replace(/(-pane|tab-)/g, '');
                    const tabEl = document.querySelector(`#${cleanTab}-pane-tab`) || document.querySelector(`#${cleanTab}-tab`) || document.querySelector(`#tab-${cleanTab}-tab`);
                    if (tabEl && window.bootstrap) {
                        const tab = bootstrap.Tab.getOrCreateInstance(tabEl);
                        tab.show();
                    }
                }
            });
        </script>
    @else
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                $('.modal').each(function() {
                    $(this).appendTo('body');
                });

                const urlParams = new URLSearchParams(window.location.search);
                const activeTab = urlParams.get('tab');
                if (activeTab) {
                    const tabEl = document.querySelector(`#tab-${activeTab}-tab`) || document.querySelector(`#${activeTab}-tab`);
                    if (tabEl) {
                        const tab = new bootstrap.Tab(tabEl);
                        tab.show();
                    }
                }
            });
        </script>
    @endif
@endpush
