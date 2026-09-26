@extends('layouts.duralux')

@section('title', __('My Profile') . ' | SaaS ERP')
@section('page-title', __('My Profile'))
@section('breadcrumb', 'Account / ' . __('My Profile'))

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
            {{ __('Account Settings') }}
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
            .btn-doc-card { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 13px; font-weight: 600; color: #1e293b; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04); text-decoration: none; transition: all 0.15s ease-in-out; }
            .btn-doc-card:hover { color: var(--bs-primary); border-color: var(--bs-primary); background-color: rgba(var(--bs-primary-rgb), 0.04); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08); transform: translateY(-1px); }
            .tab-nav-custom { border-bottom: 2px solid #e2e8f0; gap: 8px; margin-bottom: 24px; flex-wrap: nowrap !important; overflow-x: auto !important; overflow-y: hidden !important; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
            .tab-nav-custom::-webkit-scrollbar { display: none; }
            .tab-nav-custom .nav-item { flex-shrink: 0; }
            .tab-nav-custom .nav-link { border: none !important; border-bottom: 3px solid transparent !important; background: transparent !important; color: #64748b !important; font-size: 14px; font-weight: 600; padding: 12px 16px; transition: all 0.2s ease; display: flex; align-items: center; gap: 8px; }
            .tab-nav-custom .nav-link:hover { color: var(--bs-primary) !important; border-bottom-color: #cbd5e1 !important; }
            .tab-nav-custom .nav-link.active { color: var(--bs-primary) !important; border-bottom-color: var(--bs-primary) !important; font-weight: 700; }
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
                        <h6 class="fw-bold text-dark mb-1 fs-14">Pending Profile Edit Request Under Review</h6>
                        <p class="text-muted fs-12 mb-0">
                            You submitted a profile edit request with <strong class="text-dark">{{ count($pChanges) }} edited field(s)</strong> on {{ $pendingReq->created_at ? $pendingReq->created_at->format('M d, Y h:i A') : 'recently' }}. Your live profile will update once reviewed and approved by HR.
                        </p>
                    </div>
                </div>
                <x-ui.button type="button" variant="primary" size="sm" icon="feather-eye" data-bs-toggle="modal" data-bs-target="#viewMyPendingRequestModal" class="fw-bold text-nowrap">
                    View Edit Request
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
                        <span class="erp-badge-active">{{ __('Active') }}</span>
                        <span class="badge bg-soft-primary text-primary">{{ $user->primaryRole?->name ?? ucfirst($user->role ?: 'User') }}</span>
                        @if ($tenant)
                            <span class="badge bg-soft-info text-info border border-info-subtle">
                                <i class="feather-briefcase me-1"></i>{{ $tenant->name }}
                            </span>
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
                        <span class="ms-2">&bull; <i class="feather-hash me-1"></i>User ID: #{{ $user->id }}</span>
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($employee)
                    <x-ui.button type="button" variant="light-brand" icon="feather-edit" data-bs-toggle="modal" data-bs-target="#editEmployeeModal">
                        {{ __('Edit Profile') }}
                    </x-ui.button>
                @else
                    <x-ui.button type="button" variant="light-brand" icon="feather-edit" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        {{ __('Edit Profile') }}
                    </x-ui.button>
                @endif
            </div>
        </div>

        @if($employee)
            <!-- TAB NAVIGATION FOR EMPLOYEES -->
            <ul class="nav nav-tabs tab-nav-custom mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTabName === 'overview' ? 'active' : '' }}" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="{{ $activeTabName === 'overview' ? 'true' : 'false' }}">
                        <i class="feather-user me-1.5"></i> {{ __('hrms.employees.tab_overview') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTabName === 'compensation' ? 'active' : '' }}" id="compensation-tab" data-bs-toggle="tab" data-bs-target="#compensation-pane" type="button" role="tab" aria-controls="compensation-pane" aria-selected="{{ $activeTabName === 'compensation' ? 'true' : 'false' }}">
                        <i class="feather-dollar-sign me-1.5"></i> {{ __('hrms.employees.tab_compensation') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTabName === 'documents' ? 'active' : '' }}" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-pane" type="button" role="tab" aria-controls="documents-pane" aria-selected="{{ $activeTabName === 'documents' ? 'true' : 'false' }}">
                        <i class="feather-file-text me-1.5"></i> {{ __('hrms.employees.tab_documents') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTabName === 'probation' ? 'active' : '' }}" id="probation-tab" data-bs-toggle="tab" data-bs-target="#probation-pane" type="button" role="tab" aria-controls="probation-pane" aria-selected="{{ $activeTabName === 'probation' ? 'true' : 'false' }}">
                        <i class="feather-award me-1.5"></i> Probation
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTabName === 'exit-clearance' ? 'active' : '' }}" id="exit-clearance-tab" data-bs-toggle="tab" data-bs-target="#exit-clearance-pane" type="button" role="tab" aria-controls="exit-clearance-pane" aria-selected="{{ $activeTabName === 'exit-clearance' ? 'true' : 'false' }}">
                        <i class="feather-log-out me-1.5"></i> Exit & NOC
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTabName === 'pip' ? 'active' : '' }}" id="pip-tab" data-bs-toggle="tab" data-bs-target="#pip-pane" type="button" role="tab" aria-controls="pip-pane" aria-selected="{{ $activeTabName === 'pip' ? 'true' : 'false' }}">
                        <i class="feather-trending-up me-1.5"></i> PIP Plans
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTabName === 'security' ? 'active' : '' }}" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-pane" type="button" role="tab" aria-controls="security-pane" aria-selected="{{ $activeTabName === 'security' ? 'true' : 'false' }}">
                        <i class="feather-lock me-1.5"></i> {{ __('Security & Password') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTabName === 'activity' ? 'active' : '' }}" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity-pane" type="button" role="tab" aria-controls="activity-pane" aria-selected="{{ $activeTabName === 'activity' ? 'true' : 'false' }}">
                        <i class="feather-activity me-1.5"></i> {{ __('Activity & Sessions') }}
                    </button>
                </li>
            </ul>

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
                                    <i class="feather-key text-primary me-2"></i>{{ __('Change Account Password') }}
                                </h5>

                                <form action="{{ route('profile.password.update') }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('Current Password')" name="current_password" id="current_password" placeholder="Enter current password" :required="true" :error-text="$errors->first('current_password')" />

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('New Password')" name="password" id="password" placeholder="Minimum 8 characters" :required="true" helperText="{{ __('Must be at least 8 characters long.') }}" :error-text="$errors->first('password')" />

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('Confirm Password')" name="password_confirmation" id="password_confirmation" placeholder="Repeat new password" :required="true" />

                                    <div class="d-flex justify-content-end mt-4">
                                        <x-ui.button type="submit" variant="primary" icon="feather-save">
                                            {{ __('Update Password') }}
                                        </x-ui.button>
                                    </div>
                                </form>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-lg-5">
                            <div class="bg-light p-4 rounded border">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-shield text-primary me-2"></i>{{ __('Session & Security Info') }}
                                </h6>
                                <div class="d-flex align-items-start gap-3 mb-4">
                                    <div class="p-2 rounded-circle bg-soft-success text-success">
                                        <i class="feather-monitor fs-20"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-14">{{ __('Current Session') }}</div>
                                        <div class="text-muted fs-12">IP: {{ request()->ip() }}</div>
                                        <div class="text-muted fs-11 mt-1 text-truncate" style="max-width: 260px;" title="{{ request()->userAgent() }}">
                                            {{ request()->userAgent() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-soft-primary border-0 fs-12 mb-0">
                                    <div class="fw-bold mb-1"><i class="feather-info me-1"></i>{{ __('Password Guidelines') }}</div>
                                    <ul class="mb-0 ps-3">
                                        <li>Use at least 8 characters.</li>
                                        <li>Mix uppercase, lowercase, numbers, and symbols.</li>
                                        <li>Avoid reusing passwords from other systems.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity & Sessions Tab Pane -->
                <div class="tab-pane fade {{ $activeTabName === 'activity' ? 'show active' : '' }}" id="activity-pane" role="tabpanel" aria-labelledby="activity-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">{{ __('Recent Account Activity') }}</h5>
                    </div>

                    @if ($recentActivity->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th style="width: 30%">{{ __('Action') }}</th>
                                        <th style="width: 40%">{{ __('Target / Scope') }}</th>
                                        <th style="width: 30%">{{ __('Timestamp') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentActivity as $log)
                                        <tr>
                                            <td>
                                                <span class="badge bg-soft-primary text-primary fw-bold">
                                                    {{ ucwords(str_replace(['.', '_'], ' ', $log->action)) }}
                                                </span>
                                            </td>
                                            <td class="fs-13 text-muted">
                                                {{ class_basename($log->subject_type ?: 'System') }}
                                                @if ($log->subject_id)
                                                    #{{ $log->subject_id }}
                                                @endif
                                            </td>
                                            <td class="fs-12 text-muted">
                                                {{ $log->created_at ? $log->created_at->format('d M, Y h:i A') : 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <div class="p-4 text-center border rounded bg-light text-muted fs-13">
                            <i class="feather-info me-2 text-primary"></i>{{ __('No recent audit activity found for your account.') }}
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
                                <i class="feather-edit-3 me-2 text-primary"></i>{{ __('Edit Profile') }}
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
                                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                                <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        @else
            <!-- TAB NAVIGATION FOR NON-EMPLOYEES -->
            <x-ui.horizontal-tabs id="profileTabs" :tabs="[
                ['id' => 'tab-overview', 'label' => __('Overview'), 'active' => ($activeTabName === 'overview'), 'icon' => 'feather-user'],
                ['id' => 'tab-security', 'label' => __('Security & Password'), 'active' => ($activeTabName === 'security'), 'icon' => 'feather-lock'],
                ['id' => 'tab-activity', 'label' => __('Activity & Sessions'), 'active' => ($activeTabName === 'activity'), 'icon' => 'feather-activity']
            ]" />

            <!-- TAB CONTENT CONTAINER FOR NON-EMPLOYEES -->
            <div class="tab-content mt-5">

                <!-- 1. OVERVIEW TAB -->
                <div class="tab-pane fade {{ $activeTabName === 'overview' ? 'show active' : '' }}" id="tab-overview" role="tabpanel" aria-labelledby="tab-overview-tab">
                    <x-ui.odoo-form-ui type="sheet">
                        <div class="row g-4 mb-4">
                            <!-- Left Column: Personal & Account Details -->
                            <div class="col-md-6 border-end">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-user text-primary me-2"></i>{{ __('Personal & Account Details') }}
                                </h6>
                                <x-ui.odoo-form-ui type="input" :label="__('Full Name')" name="view_name" :value="$user->name" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('Email Address')" name="view_email" :value="$user->email" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('Phone / Mobile')" name="view_phone" :value="$user->effective_phone ?: __('Not set')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('Assigned Role')" name="view_role" :value="$user->primaryRole?->name ?? ucfirst($user->role ?: 'User')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('System User ID')" name="view_id" :value="'#' . $user->id" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('Registered On')" name="view_registered" :value="$user->created_at ? $user->created_at->format('M d, Y h:i A') : 'N/A'" :readonly="true" />
                            </div>

                            <!-- Right Column: Organization Context -->
                            <div class="col-md-6">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-briefcase text-primary me-2"></i>{{ __('Organization Context') }}
                                </h6>
                                <x-ui.odoo-form-ui type="input" :label="__('Tenant / Workspace')" name="view_tenant" :value="$tenant?->name ?? 'Default Tenant'" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('Tenant Identifier')" name="view_slug" :value="$tenant?->slug ?? 'default'" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('Company')" name="view_company" :value="$user->company?->company_name ?? __('Default Company')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('Branch')" name="view_branch" :value="$user->branch?->branch_name ?? __('Default Branch')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('Department')" name="view_department" :value="$user->department?->name ?? __('Not Assigned')" :readonly="true" />
                                <x-ui.odoo-form-ui type="input" :label="__('Subscription Plan')" name="view_plan" :value="ucfirst($tenant?->plan ?? 'Enterprise')" :readonly="true" />
                            </div>
                        </div>

                        <!-- Bottom Section: Regional & System Preferences -->
                        <div class="border-top pt-4 mt-3">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="feather-globe text-primary me-2"></i>{{ __('Regional & System Preferences') }}
                            </h6>
                            <div class="row g-4">
                                <div class="col-md-6 border-end">
                                    <x-ui.odoo-form-ui type="input" :label="__('System Timezone')" name="view_timezone" :value="$tenant?->timezone ?? config('app.timezone', 'UTC')" :readonly="true" />
                                    <x-ui.odoo-form-ui type="input" :label="__('System Language')" name="view_locale" :value="strtoupper($tenant?->locale ?? config('app.locale', 'en'))" :readonly="true" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('Default Currency')" name="view_currency" :value="$tenant?->settings['currency'] ?? 'INR (₹)'" :readonly="true" />
                                    <x-ui.odoo-form-ui type="input" :label="__('Financial Year')" name="view_fy" :value="$tenant?->settings['financial_year'] ?? '2026-2027'" :readonly="true" />
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
                                    <i class="feather-key text-primary me-2"></i>{{ __('Change Account Password') }}
                                </h5>

                                <form action="{{ route('profile.password.update') }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('Current Password')" name="current_password" id="current_password_alt" placeholder="Enter current password" :required="true" :error-text="$errors->first('current_password')" />

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('New Password')" name="password" id="password_alt" placeholder="Minimum 8 characters" :required="true" helperText="{{ __('Must be at least 8 characters long.') }}" :error-text="$errors->first('password')" />

                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('Confirm Password')" name="password_confirmation" id="password_confirmation_alt" placeholder="Repeat new password" :required="true" />

                                    <div class="d-flex justify-content-end mt-4">
                                        <x-ui.button type="submit" variant="primary" icon="feather-save">
                                            {{ __('Update Password') }}
                                        </x-ui.button>
                                    </div>
                                </form>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-lg-5">
                            <div class="bg-light p-4 rounded border">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-shield text-primary me-2"></i>{{ __('Session & Security Info') }}
                                </h6>
                                <div class="d-flex align-items-start gap-3 mb-4">
                                    <div class="p-2 rounded-circle bg-soft-success text-success">
                                        <i class="feather-monitor fs-20"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-14">{{ __('Current Session') }}</div>
                                        <div class="text-muted fs-12">IP: {{ request()->ip() }}</div>
                                        <div class="text-muted fs-11 mt-1 text-truncate" style="max-width: 260px;" title="{{ request()->userAgent() }}">
                                            {{ request()->userAgent() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-soft-primary border-0 fs-12 mb-0">
                                    <div class="fw-bold mb-1"><i class="feather-info me-1"></i>{{ __('Password Guidelines') }}</div>
                                    <ul class="mb-0 ps-3">
                                        <li>Use at least 8 characters.</li>
                                        <li>Mix uppercase, lowercase, numbers, and symbols.</li>
                                        <li>Avoid reusing passwords from other systems.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. ACTIVITY & SESSIONS TAB -->
                <div class="tab-pane fade {{ $activeTabName === 'activity' ? 'show active' : '' }}" id="tab-activity" role="tabpanel" aria-labelledby="tab-activity-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">{{ __('Recent Account Activity') }}</h5>
                    </div>

                    @if ($recentActivity->isNotEmpty())
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th style="width: 30%">{{ __('Action') }}</th>
                                        <th style="width: 40%">{{ __('Target / Scope') }}</th>
                                        <th style="width: 30%">{{ __('Timestamp') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentActivity as $log)
                                        <tr>
                                            <td>
                                                <span class="badge bg-soft-primary text-primary fw-bold">
                                                    {{ ucwords(str_replace(['.', '_'], ' ', $log->action)) }}
                                                </span>
                                            </td>
                                            <td class="fs-13 text-muted">
                                                {{ class_basename($log->subject_type ?: 'System') }}
                                                @if ($log->subject_id)
                                                    #{{ $log->subject_id }}
                                                @endif
                                            </td>
                                            <td class="fs-12 text-muted">
                                                {{ $log->created_at ? $log->created_at->format('d M, Y h:i A') : 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <div class="p-4 text-center border rounded bg-light text-muted fs-13">
                            <i class="feather-info me-2 text-primary"></i>{{ __('No recent audit activity found for your account.') }}
                        </div>
                    @endif
                </div>

            </div>

            <!-- Edit Profile Modal for Non-Employees -->
            <x-ui.modal id="editProfileModal" :title="__('Edit Profile')" :centered="true">
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" id="editProfileForm">
                    @csrf
                    @method('PUT')

                    <x-ui.odoo-form-ui type="sheet">
                        <x-ui.odoo-form-ui type="input" :label="__('Full Name')" name="name" id="edit_name" :value="old('name', $user->name)" :required="true" :error-text="$errors->first('name')" />

                        <x-ui.odoo-form-ui type="input" inputType="email" :label="__('Email Address')" name="email" id="edit_email" :value="old('email', $user->email)" :required="true" :error-text="$errors->first('email')" />

                        <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('Phone / Mobile')" name="phone" id="edit_phone" :value="old('phone', $user->effective_phone)" placeholder="+91 9876543210" :error-text="$errors->first('phone')" />

                        <x-ui.odoo-form-ui type="file" :label="__('Profile Photo')" name="avatar" id="edit_avatar" placeholder="Choose photo (JPG, PNG, WEBP, max 2MB)" :error-text="$errors->first('avatar')" />
                    </x-ui.odoo-form-ui>
                </form>

                <x-slot name="footer">
                    <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button type="button" variant="primary" icon="feather-save" onclick="document.getElementById('editProfileForm').submit();">
                        {{ __('Save Changes') }}
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
            <div class="modal fade" id="viewMyPendingRequestModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="modal-header bg-light border-bottom px-4 py-3">
                            <h5 class="modal-title fw-bold text-dark mb-0">
                                <i class="feather-clock me-2 text-warning"></i>Profile Edit Request Details
                            </h5>
                            <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="table-responsive border rounded">
                                <table class="table table-bordered align-middle mb-0 fs-13">
                                    <thead class="bg-light text-uppercase fs-11 text-muted">
                                        <tr>
                                            <th>Field</th>
                                            <th class="text-danger">Current Value</th>
                                            <th class="text-success">Requested New Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pChanges as $fKey => $fData)
                                            <tr>
                                                <td class="fw-bold text-dark">{{ $fData['label'] ?? ucwords(str_replace('_', ' ', $fKey)) }}</td>
                                                <td class="text-muted">
                                                    @if(!empty($fData['is_image']))
                                                        @if(!empty($fData['old']) && $fData['old'] !== '—')
                                                            <img src="{{ asset('storage/' . $fData['old']) }}" alt="Old Photo" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px;">
                                                        @else
                                                            <span class="text-muted">No photo</span>
                                                        @endif
                                                    @else
                                                        <span class="text-decoration-line-through text-danger">{{ $fData['old'] ?? '—' }}</span>
                                                    @endif
                                                </td>
                                                <td class="fw-bold text-success">
                                                    @if(!empty($fData['is_image']))
                                                        <img src="{{ asset('storage/' . $fData['new']) }}" alt="New Photo" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 2px solid #22c55e;">
                                                    @else
                                                        <x-ui.badge variant="success" soft class="fs-13 fw-bold">{{ $fData['new'] ?? '—' }}</x-ui.badge>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top px-4 py-3">
                            <x-ui.button type="button" variant="light" class="border" data-bs-dismiss="modal">
                                Close
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
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
                    const tabEl = document.querySelector(`#${activeTab}-tab`);
                    if (tabEl) {
                        const tab = new bootstrap.Tab(tabEl);
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
