@extends('layouts.duralux')

@section('title', __('My Profile') . ' | SaaS ERP')
@section('page-title', __('My Profile'))
@section('breadcrumb', 'Account / ' . __('My Profile'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('account.settings') }}" variant="secondary" icon="feather-settings">
            {{ __('Account Settings') }}
        </x-ui.button>
        <x-ui.button type="button" variant="primary" icon="feather-edit-3" data-bs-toggle="modal" data-bs-target="#editProfileModal">
            {{ __('Edit Profile') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
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

        <!-- Profile Details Header Grid (BOM style) -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="profile-avatar-container flex-shrink-0" style="width: 72px; height: 72px;">
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="rounded-circle shadow-sm" style="width: 72px; height: 72px; min-width: 72px; min-height: 72px; max-width: 72px; max-height: 72px; object-fit: cover; border: 3px solid #e2e8f0; display: block;" onerror="this.onerror=null; this.src='/assets/images/avatar/default.png';">
                </div>
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0">{{ $user->name }}</h4>
                        <span class="erp-badge-active">{{ __('Active') }}</span>
                        <span class="badge bg-soft-primary text-primary">{{ $user->primaryRole?->name ?? ucfirst($user->role ?: 'User') }}</span>
                        @if ($tenant)
                            <span class="badge bg-soft-info text-info border border-info-subtle">
                                <i class="feather-briefcase me-1"></i>{{ $tenant->name }}
                            </span>
                        @endif
                    </div>
                    <p class="text-muted fs-13 mb-0">
                        <span><i class="feather-mail me-1"></i>{{ $user->email }}</span>
                        @if ($user->effective_phone)
                            <span class="ms-2">&bull; <i class="feather-phone me-1"></i>{{ $user->effective_phone }}</span>
                        @endif
                        @if ($user->department)
                            <span class="ms-2">&bull; <i class="feather-layers me-1"></i>{{ $user->department->name }}</span>
                        @elseif ($employee && $employee->department)
                            <span class="ms-2">&bull; <i class="feather-layers me-1"></i>{{ $employee->department->name }}</span>
                        @endif
                        <span class="ms-2">&bull; <i class="feather-hash me-1"></i>User ID: #{{ $user->id }}</span>
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <x-ui.button type="button" variant="light-brand" icon="feather-edit" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    {{ __('Edit Profile') }}
                </x-ui.button>
            </div>
        </div>

        @php
            $activeTab = request('tab', 'overview');
        @endphp

        <!-- TAB NAVIGATION (Horizontal Tabs Component) -->
        <x-ui.horizontal-tabs id="profileTabs" :tabs="[
            ['id' => 'tab-overview', 'label' => __('Overview'), 'active' => ($activeTab === 'overview'), 'icon' => 'feather-user'],
            ['id' => 'tab-security', 'label' => __('Security & Password'), 'active' => ($activeTab === 'security'), 'icon' => 'feather-lock'],
            ['id' => 'tab-activity', 'label' => __('Activity & Sessions'), 'active' => ($activeTab === 'activity'), 'icon' => 'feather-activity']
        ]" />

        <!-- TAB CONTENT CONTAINER -->
        <div class="tab-content mt-5">

            <!-- 1. OVERVIEW TAB -->
            <div class="tab-pane fade {{ $activeTab === 'overview' ? 'show active' : '' }}" id="tab-overview" role="tabpanel" aria-labelledby="tab-overview-tab">
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
                            <x-ui.odoo-form-ui type="input" :label="__('Company')" name="view_company" :value="$user->company?->company_name ?? ($employee?->company?->company_name ?? __('Default Company'))" :readonly="true" />
                            <x-ui.odoo-form-ui type="input" :label="__('Branch')" name="view_branch" :value="$user->branch?->branch_name ?? ($employee?->branch?->branch_name ?? __('Default Branch'))" :readonly="true" />
                            <x-ui.odoo-form-ui type="input" :label="__('Department')" name="view_department" :value="$user->department?->name ?? ($employee?->department?->name ?? __('Not Assigned'))" :readonly="true" />
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
            <div class="tab-pane fade {{ $activeTab === 'security' ? 'show active' : '' }}" id="tab-security" role="tabpanel" aria-labelledby="tab-security-tab">
                <div class="row g-4">
                    <!-- Change Password Form -->
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

                    <!-- Security & Session Tips -->
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
            <div class="tab-pane fade {{ $activeTab === 'activity' ? 'show active' : '' }}" id="tab-activity" role="tabpanel" aria-labelledby="tab-activity-tab">
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

        <!-- Edit Profile Modal (Common Modal Component) -->
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

    </div>
@endsection
