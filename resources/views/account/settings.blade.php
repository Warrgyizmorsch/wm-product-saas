@extends('layouts.duralux')

@section('title', __('Account Settings') . ' | SaaS ERP')
@section('page-title', __('Account Settings'))
@section('breadcrumb', 'Account / ' . __('Account Settings'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('profile.show') }}" variant="secondary" icon="feather-user">
            {{ __('View Profile') }}
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

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" :title="session('error')" />
            <x-ui.alert variant="danger" icon="feather-alert-triangle" class="mb-4" dismissible>
                {{ session('error') }}
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

        <!-- Settings Header Grid (BOM style) -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1">{{ __('Account Settings') }}</h4>
                <p class="text-muted fs-13 mb-0">{{ __('Manage your credentials, notification channels, integrations, and workspace parameters.') }}</p>
            </div>
            <div>
                <span class="erp-badge-active">{{ ucfirst($tenant?->subscription_status ?? 'Active') }}</span>
            </div>
        </div>

        @php
            $activeTab = request('tab', 'profile');
        @endphp

        <div class="row g-4">
            {{-- Sidebar Navigation Tabs using Vertical Tabs Component --}}
            <div class="col-lg-3 col-md-4 border-end pe-lg-4">
                <x-ui.vertical-tabs id="settingsTabs" :tabs="[
                    ['id' => 'profile-section', 'label' => __('Profile & Credentials'), 'active' => ($activeTab === 'profile'), 'icon' => 'feather-user'],
                    ['id' => 'notifications-section', 'label' => __('ui.notifications'), 'active' => ($activeTab === 'notifications'), 'icon' => 'feather-bell'],
                    ['id' => 'integrations-section', 'label' => __('Integrations'), 'active' => ($activeTab === 'integrations'), 'icon' => 'feather-link-2'],
                    ['id' => 'subscription-section', 'label' => __('Subscription & Quota'), 'active' => ($activeTab === 'subscription'), 'icon' => 'feather-credit-card'],
                    ['id' => 'danger-section', 'label' => __('Danger Zone'), 'active' => ($activeTab === 'danger'), 'icon' => 'feather-alert-octagon']
                ]" />
            </div>

            <!-- Content Area -->
            <div class="col-lg-9 col-md-8 ps-lg-4">
                <div class="tab-content" id="settingsTabsContent">

                    <!-- 1. PROFILE & CREDENTIALS SECTION -->
                    <div class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}" id="profile-section" role="tabpanel" aria-labelledby="profile-section-tab">
                        <x-ui.odoo-form-ui type="sheet">
                            <!-- Account Details Form -->
                            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                <i class="feather-user text-primary me-2"></i>{{ __('Account Information') }}
                            </h5>
                            <form action="{{ route('account.settings.profile') }}" method="POST">
                                @csrf
                                @method('PUT')

                                <x-ui.odoo-form-ui type="input" :label="__('Full Name')" name="name" id="profile_name" :value="old('name', $user->name)" :required="true" :error-text="$errors->first('name')" />

                                <x-ui.odoo-form-ui type="input" inputType="email" :label="__('Email Address')" name="email" id="profile_email" :value="old('email', $user->email)" :required="true" :error-text="$errors->first('email')" />

                                <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('Phone Number')" name="phone" id="profile_phone" :value="old('phone', $user->effective_phone)" placeholder="+91 9876543210" :error-text="$errors->first('phone')" />

                                <x-ui.odoo-form-ui type="input" :label="__('Role / Access')" name="role_display" :value="$user->primaryRole?->name ?? ucfirst($user->role ?: 'User')" :readonly="true" :disabled="true" />

                                <div class="d-flex justify-content-end mt-4">
                                    <x-ui.button type="submit" variant="primary" icon="feather-save">
                                        {{ __('Save Account Details') }}
                                    </x-ui.button>
                                </div>
                            </form>

                            <hr class="my-4">

                            <!-- Password Update Form -->
                            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                <i class="feather-key text-primary me-2"></i>{{ __('Change Password') }}
                            </h5>
                            <form action="{{ route('account.settings.password') }}" method="POST">
                                @csrf
                                @method('PUT')

                                <x-ui.odoo-form-ui type="input" inputType="password" :label="__('Current Password')" name="current_password" id="acc_current_password" placeholder="Enter current password" :required="true" :error-text="$errors->first('current_password')" />

                                <x-ui.odoo-form-ui type="input" inputType="password" :label="__('New Password')" name="password" id="acc_password" placeholder="Minimum 8 characters" :required="true" helperText="{{ __('Must be at least 8 characters long.') }}" :error-text="$errors->first('password')" />

                                <x-ui.odoo-form-ui type="input" inputType="password" :label="__('Confirm Password')" name="password_confirmation" id="acc_password_confirmation" placeholder="Repeat new password" :required="true" />

                                <div class="d-flex justify-content-end mt-4">
                                    <x-ui.button type="submit" variant="primary" icon="feather-save">
                                        {{ __('Update Password') }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- 2. NOTIFICATIONS SECTION -->
                    <div class="tab-pane fade {{ $activeTab === 'notifications' ? 'show active' : '' }}" id="notifications-section" role="tabpanel" aria-labelledby="notifications-section-tab">
                        <x-ui.odoo-form-ui type="sheet">
                            <h5 class="fw-bold text-dark mb-2 pb-2 border-bottom">
                                <i class="feather-bell text-primary me-2"></i>{{ __('Notification Preferences') }}
                            </h5>
                            <p class="text-muted fs-13 mb-4">
                                {{ __('Configure how and when you receive system alerts, order updates, and workflow notifications. Channels require active integration setups to deliver messages.') }}
                            </p>

                            <form action="{{ route('account.settings.notifications') }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="border rounded p-3 mb-3 bg-light-soft">
                                    <div class="fw-bold text-dark fs-14 mb-1">{{ __('In-App Header Bell Alerts') }}</div>
                                    <x-ui.odoo-form-ui type="checkbox" name="in_app" value="1" :checked="!empty($notificationPrefs['in_app']) ? true : null">
                                        {{ __('Show real-time notifications in the topbar bell dropdown.') }}
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="border rounded p-3 mb-3 bg-light-soft">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark fs-14">{{ __('Email Notifications') }}</span>
                                        @if ($emailConfigured)
                                            <span class="badge bg-soft-success text-success"><i class="feather-check-circle me-1"></i>SMTP Active</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning"><i class="feather-alert-circle me-1"></i>SMTP Not Configured</span>
                                        @endif
                                    </div>
                                    <x-ui.odoo-form-ui type="checkbox" name="email" value="1" :checked="!empty($notificationPrefs['email']) ? true : null">
                                        {{ __('Receive essential updates and approval requests via email.') }}
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="border rounded p-3 mb-4 bg-light-soft">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark fs-14">{{ __('WhatsApp Notifications') }}</span>
                                        @if ($whatsappConnected)
                                            <span class="badge bg-soft-success text-success"><i class="feather-check-circle me-1"></i>Bridge Connected</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary"><i class="feather-alert-circle me-1"></i>Bridge Disconnected</span>
                                        @endif
                                    </div>
                                    <x-ui.odoo-form-ui type="checkbox" name="whatsapp" value="1" :checked="!empty($notificationPrefs['whatsapp']) ? true : null">
                                        {{ __('Receive urgent transactional alerts on your verified WhatsApp number.') }}
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <x-ui.button type="submit" variant="primary" icon="feather-save">
                                        {{ __('Save Notification Preferences') }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- 3. INTEGRATIONS SECTION -->
                    <div class="tab-pane fade {{ $activeTab === 'integrations' ? 'show active' : '' }}" id="integrations-section" role="tabpanel" aria-labelledby="integrations-section-tab">
                        <x-ui.odoo-form-ui type="sheet">
                            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                <i class="feather-link-2 text-primary me-2"></i>{{ __('Connected Gateways & Bridges') }}
                            </h5>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 bg-light-soft d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-bold text-dark mb-0">
                                                <i class="feather-mail text-info me-1"></i>{{ __('Email & SMTP Gateway') }}
                                            </h6>
                                            @if ($emailConfigured)
                                                <span class="badge bg-soft-success text-success"><i class="feather-check-circle me-1"></i>Configured</span>
                                            @else
                                                <span class="badge bg-soft-warning text-warning">Inactive</span>
                                            @endif
                                        </div>
                                        <p class="text-muted fs-13 mb-3">
                                            {{ __('Handles system notifications, invoice dispatches, RFQs, purchase orders, and lead auto-responders.') }}
                                        </p>
                                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="fs-12 text-muted">{{ $emailAccountCount }} {{ __('account(s) found') }}</span>
                                            @if ($canManagePlatformSettings && Route::has('platform.emailSettings.index'))
                                                <x-ui.button href="{{ route('platform.emailSettings.index') }}" variant="secondary" size="sm" icon="feather-external-link">
                                                    {{ __('Manage SMTP') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 bg-light-soft d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-bold text-dark mb-0">
                                                <i class="feather-message-circle text-success me-1"></i>{{ __('WhatsApp Bridge') }}
                                            </h6>
                                            @if ($whatsappConnected)
                                                <span class="badge bg-soft-success text-success"><i class="feather-check-circle me-1"></i>Connected</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($whatsappStatus) }}</span>
                                            @endif
                                        </div>
                                        <p class="text-muted fs-13 mb-3">
                                            {{ __('Connects WhatsApp web bridge for instant dispatch notifications, CRM updates, and payment alerts.') }}
                                        </p>
                                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="fs-12 text-muted">{{ $whatsappNumber ?: __('No phone attached') }}</span>
                                            @if ($canManagePlatformSettings && Route::has('platform.whatsappSettings.index'))
                                                <x-ui.button href="{{ route('platform.whatsappSettings.index') }}" variant="secondary" size="sm" icon="feather-external-link">
                                                    {{ __('Manage WhatsApp') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if (Route::has('platform.notification-rules.index') && $canManagePlatformSettings)
                                <div class="p-3 border rounded bg-light d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1">
                                            <i class="feather-sliders text-primary me-2"></i>{{ __('Rule-Based Automated Triggers') }}
                                        </h6>
                                        <p class="text-muted fs-13 mb-0">{{ __('Manage automated triggers for invoices, leads, production orders, and approvals.') }}</p>
                                    </div>
                                    <x-ui.button href="{{ route('platform.notification-rules.index') }}" variant="secondary" icon="feather-settings">
                                        {{ __('Configure Rules') }}
                                    </x-ui.button>
                                </div>
                            @endif
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- 4. SUBSCRIPTION & QUOTA SECTION -->
                    <div class="tab-pane fade {{ $activeTab === 'subscription' ? 'show active' : '' }}" id="subscription-section" role="tabpanel" aria-labelledby="subscription-section-tab">
                        <x-ui.odoo-form-ui type="sheet">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <h5 class="fw-bold text-dark mb-0">
                                    <i class="feather-credit-card text-primary me-2"></i>{{ __('Subscription & Usage Limits') }}
                                </h5>
                                @if (Route::has('platform.subscription.index') && $canManagePlatformSettings)
                                    <x-ui.button href="{{ route('platform.subscription.index') }}" variant="primary" size="sm" icon="feather-arrow-up-right">
                                        {{ __('Manage Plan') }}
                                    </x-ui.button>
                                @endif
                            </div>

                            <div class="row g-4 mb-4">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded text-center border">
                                        <div class="text-muted fs-12 text-uppercase fw-bold mb-1">{{ __('Current Plan') }}</div>
                                        <h4 class="fw-bold text-dark mb-0">{{ $tenant?->planCatalog?->name ?? ucfirst($tenant?->plan ?? 'Enterprise') }}</h4>
                                        <span class="badge bg-soft-success text-success mt-1">
                                            {{ ucfirst($tenant?->subscription_status ?? 'Active') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded text-center border">
                                        <div class="text-muted fs-12 text-uppercase fw-bold mb-1">{{ __('User Slots') }}</div>
                                        <h4 class="fw-bold text-dark mb-0">
                                            {{ $currentUserCount }} / {{ $maxUsers ?? '∞' }}
                                        </h4>
                                        <span class="fs-12 text-muted">
                                            {{ $remainingUserSlots !== null ? $remainingUserSlots . ' slots remaining' : 'Unlimited' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded text-center border">
                                        <div class="text-muted fs-12 text-uppercase fw-bold mb-1">{{ __('Allocated Storage') }}</div>
                                        <h4 class="fw-bold text-dark mb-0">
                                            {{ number_format(($maxStorageMb ?: 10240) / 1024, 1) }} GB
                                        </h4>
                                        <span class="fs-12 text-muted">{{ __('Document storage limit') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 border rounded bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fs-13 fw-semibold text-dark">{{ __('User Seats Utilization') }}</span>
                                    @php
                                        $percent = $maxUsers ? min(100, round(($currentUserCount / $maxUsers) * 100)) : 10;
                                    @endphp
                                    <span class="fs-12 fw-bold text-primary">{{ $percent }}%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- 5. DANGER ZONE SECTION -->
                    <div class="tab-pane fade {{ $activeTab === 'danger' ? 'show active' : '' }}" id="danger-section" role="tabpanel" aria-labelledby="danger-section-tab">
                        <x-ui.odoo-form-ui type="sheet">
                            <div class="p-4 border border-danger rounded bg-soft-danger">
                                <h5 class="fw-bold text-danger mb-2">
                                    <i class="feather-alert-triangle me-2"></i>{{ __('Danger Zone') }}
                                </h5>
                                <h6 class="fw-bold text-dark mt-3 mb-1">{{ __('Deactivate Account') }}</h6>
                                <p class="text-muted fs-13 mb-4">
                                    {{ __('Deactivating your user account will immediately log you out, revoke all active sessions and access tokens. Your historical contributions (audit logs, documents, approvals) will be preserved for compliance and operational continuity.') }}
                                </p>
                                <div>
                                    <x-ui.button type="button" variant="danger" icon="feather-user-x" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                        {{ __('Deactivate My Account') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        </x-ui.odoo-form-ui>
                    </div>

                </div>
            </div>
        </div>

        <!-- Deactivate Modal (Common Modal Component) -->
        <x-ui.modal id="deactivateModal" :title="__('Confirm Account Deactivation')" :centered="true">
            <form action="{{ route('account.settings.delete') }}" method="POST" id="deactivateForm" autocomplete="off">
                @csrf
                @method('DELETE')

                {{-- Decoy fields to trap browser password autofill --}}
                <input type="text" name="_decoy_username" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
                <input type="password" name="_decoy_password" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">

                <p class="fs-13 text-muted mb-3">
                    {{ __('Are you sure you want to deactivate your account? To confirm, please type') }}
                    <strong class="text-danger">DEACTIVATE</strong> {{ __('below and enter your current password.') }}
                </p>

                <x-ui.odoo-form-ui type="sheet">
                    <x-ui.odoo-form-ui type="input" :label="__('Type Confirmation')" name="confirmation" id="confirmation" placeholder="DEACTIVATE" :value="old('confirmation')" :required="true" autocomplete="one-time-code" autocapitalize="characters" autocorrect="off" data-lpignore="true" :error-text="$errors->first('confirmation')" />
                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('Your Current Password')" name="current_password" id="deactivate_password" placeholder="Enter current password" :required="true" autocomplete="new-password" data-lpignore="true" :error-text="$errors->first('current_password')" />
                </x-ui.odoo-form-ui>
            </form>

            <x-slot name="footer">
                <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button type="button" variant="danger" icon="feather-user-x" onclick="document.getElementById('deactivateForm').submit();">
                    {{ __('Permanently Deactivate') }}
                </x-ui.button>
            </x-slot>
        </x-ui.modal>

    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('deactivateModal');
            if (modalEl) {
                // Prevent browser password managers from prefilling confirmation with user email
                modalEl.addEventListener('shown.bs.modal', function () {
                    var confInput = document.getElementById('confirmation');
                    var passInput = document.getElementById('deactivate_password');
                    @if (!$errors->has('confirmation') && !$errors->has('current_password'))
                        if (confInput) confInput.value = '';
                        if (passInput) passInput.value = '';
                    @endif
                });

                @if ($errors->has('confirmation') || ($errors->has('current_password') && old('confirmation')))
                    if (typeof bootstrap !== 'undefined') {
                        var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                        modal.show();
                    }
                @endif
            }
        });
    </script>
    @endpush
@endsection
