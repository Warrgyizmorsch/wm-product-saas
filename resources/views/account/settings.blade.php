@extends('layouts.duralux')

@section('title', __('account.account_settings') . ' | SaaS ERP')
@section('page-title', __('account.account_settings'))
@section('breadcrumb', __('account.account') . ' / ' . __('account.account_settings'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('profile.show') }}" variant="secondary" icon="feather-user">
            {{ __('account.view_profile') }}
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
                <h4 class="fw-bold text-dark mb-1">{{ __('account.account_settings') }}</h4>
                <p class="text-muted fs-13 mb-0">{{ __('account.settings_desc') }}</p>
            </div>
            <div>
                <span class="erp-badge-active">{{ ($tenant?->subscription_status && strtolower($tenant->subscription_status) === 'active') ? __('account.active') : ucfirst($tenant?->subscription_status ?? __('account.active')) }}</span>
            </div>
        </div>

        @php
            $activeTab = request('tab', 'profile');
        @endphp

        <div class="row g-4">
            {{-- Sidebar Navigation Tabs using Vertical Tabs Component --}}
            <div class="col-lg-3 col-md-4 border-end pe-lg-4">
                <x-ui.vertical-tabs id="settingsTabs" :tabs="[
                    ['id' => 'profile-section', 'label' => __('account.tab_profile_credentials'), 'active' => ($activeTab === 'profile'), 'icon' => 'feather-user'],
                    ['id' => 'notifications-section', 'label' => __('account.tab_notifications'), 'active' => ($activeTab === 'notifications'), 'icon' => 'feather-bell'],
                    ['id' => 'integrations-section', 'label' => __('account.tab_integrations'), 'active' => ($activeTab === 'integrations'), 'icon' => 'feather-link-2'],
                    ['id' => 'subscription-section', 'label' => __('account.tab_subscription_quota'), 'active' => ($activeTab === 'subscription'), 'icon' => 'feather-credit-card'],
                    ['id' => 'danger-section', 'label' => __('account.tab_danger_zone'), 'active' => ($activeTab === 'danger'), 'icon' => 'feather-alert-octagon']
                ]" />
            </div>

            <!-- Content Area -->
            <div class="col-lg-9 col-md-8 ps-lg-4">
                <div class="tab-content" id="settingsTabsContent">

                    <!-- 1. PROFILE & CREDENTIALS SECTION -->
                    <div class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}" id="profile-section" role="tabpanel" aria-labelledby="profile-section-tab">
                        <x-ui.odoo-form-ui type="sheet">
                            <!-- Account Details Form -->
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <h5 class="fw-bold text-dark mb-0">
                                    <i class="feather-user text-primary me-2"></i>{{ __('account.account_information') }}
                                </h5>
                                @if($employee)
                                    <span class="badge bg-soft-info text-info border border-info px-2 py-1 fs-12">
                                        <i class="feather-shield me-1"></i>{{ __('account.hrms_linked_employee') }}
                                    </span>
                                @endif
                            </div>

                            @if($employee)
                                <div class="alert alert-info d-flex align-items-center mb-3 fs-13 py-2 px-3 border border-info rounded">
                                    <i class="feather-info me-2 fs-16 text-info"></i>
                                    <div>
                                        {{ __('account.official_hrms_notice') }}
                                        <a href="{{ route('profile.show', ['tab' => 'overview']) }}" class="fw-bold text-decoration-underline ms-1">
                                            {{ __('account.submit_profile_edit_request') }} &rarr;
                                        </a>
                                    </div>
                                </div>
                            @endif

                            <form action="{{ route('account.settings.profile') }}" method="POST">
                                @csrf
                                @method('PUT')

                                @if($employee)
                                    <x-ui.odoo-form-ui type="input" :label="__('account.full_name')" name="name_locked" id="profile_name" :value="$user->name" :readonly="true" :disabled="true" helperText="{{ __('account.name_locked_helper') }}" />

                                    <x-ui.odoo-form-ui type="input" inputType="email" :label="__('account.email_address')" name="email_locked" id="profile_email" :value="$user->email" :readonly="true" :disabled="true" helperText="{{ __('account.email_locked_helper') }}" />
                                @else
                                    <x-ui.odoo-form-ui type="input" :label="__('account.full_name')" name="name" id="profile_name" :value="old('name', $user->name)" :required="true" :error-text="$errors->first('name')" />

                                    <x-ui.odoo-form-ui type="input" inputType="email" :label="__('account.email_address')" name="email" id="profile_email" :value="old('email', $user->email)" :required="true" :error-text="$errors->first('email')" />
                                @endif

                                <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('account.phone_number')" name="phone" id="profile_phone" :value="old('phone', $user->effective_phone)" placeholder="+91 9876543210" :error-text="$errors->first('phone')" />

                                <x-ui.odoo-form-ui type="input" :label="__('account.role_access')" name="role_display" :value="__($user->primaryRole?->name ?? ucfirst($user->role ?: 'User'))" :readonly="true" :disabled="true" />

                                <div class="d-flex justify-content-end mt-4">
                                    <x-ui.button type="submit" variant="primary" icon="feather-save">
                                        {{ __('account.save_account_details') }}
                                    </x-ui.button>
                                </div>
                            </form>

                            <hr class="my-4">

                            <!-- Password Update Form -->
                            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                <i class="feather-key text-primary me-2"></i>{{ __('account.change_password') }}
                            </h5>
                            <form action="{{ route('account.settings.password') }}" method="POST">
                                @csrf
                                @method('PUT')

                                <x-ui.odoo-form-ui type="input" inputType="password" :label="__('account.current_password')" name="current_password" id="acc_current_password" :placeholder="__('account.enter_current_password')" :required="true" :error-text="$errors->first('current_password')" />

                                <x-ui.odoo-form-ui type="input" inputType="password" :label="__('account.new_password')" name="password" id="acc_password" :placeholder="__('account.minimum_8_chars')" :required="true" helperText="{{ __('account.password_min_length') }}" :error-text="$errors->first('password')" />

                                <x-ui.odoo-form-ui type="input" inputType="password" :label="__('account.confirm_password')" name="password_confirmation" id="acc_password_confirmation" :placeholder="__('account.repeat_new_password')" :required="true" />

                                <div class="d-flex justify-content-end mt-4">
                                    <x-ui.button type="submit" variant="primary" icon="feather-save">
                                        {{ __('account.update_password') }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- 2. NOTIFICATIONS SECTION -->
                    <div class="tab-pane fade {{ $activeTab === 'notifications' ? 'show active' : '' }}" id="notifications-section" role="tabpanel" aria-labelledby="notifications-section-tab">
                        <x-ui.odoo-form-ui type="sheet">
                            <h5 class="fw-bold text-dark mb-2 pb-2 border-bottom">
                                <i class="feather-bell text-primary me-2"></i>{{ __('account.notification_preferences') }}
                            </h5>
                            <p class="text-muted fs-13 mb-4">
                                {{ __('account.notification_preferences_desc') }}
                            </p>

                            <form action="{{ route('account.settings.notifications') }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="border rounded p-3 mb-3 bg-light-soft">
                                    <div class="fw-bold text-dark fs-14 mb-1">{{ __('account.in_app_alerts') }}</div>
                                    <x-ui.odoo-form-ui type="checkbox" name="in_app" value="1" :checked="!empty($notificationPrefs['in_app']) ? true : null">
                                        {{ __('account.in_app_alerts_desc') }}
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="border rounded p-3 mb-3 bg-light-soft">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark fs-14">{{ __('account.email_notifications') }}</span>
                                        @if ($emailConfigured)
                                            <span class="badge bg-soft-success text-success"><i class="feather-check-circle me-1"></i>{{ __('account.smtp_active') }}</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning"><i class="feather-alert-circle me-1"></i>{{ __('account.smtp_not_configured') }}</span>
                                        @endif
                                    </div>
                                    <x-ui.odoo-form-ui type="checkbox" name="email" value="1" :checked="!empty($notificationPrefs['email']) ? true : null">
                                        {{ __('account.email_notifications_desc') }}
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="border rounded p-3 mb-4 bg-light-soft">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark fs-14">{{ __('account.whatsapp_notifications') }}</span>
                                        @if ($whatsappConnected)
                                            <span class="badge bg-soft-success text-success"><i class="feather-check-circle me-1"></i>{{ __('account.bridge_connected') }}</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary"><i class="feather-alert-circle me-1"></i>{{ __('account.bridge_disconnected') }}</span>
                                        @endif
                                    </div>
                                    <x-ui.odoo-form-ui type="checkbox" name="whatsapp" value="1" :checked="!empty($notificationPrefs['whatsapp']) ? true : null">
                                        {{ __('account.whatsapp_notifications_desc') }}
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <x-ui.button type="submit" variant="primary" icon="feather-save">
                                        {{ __('account.save_notification_preferences') }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- 3. INTEGRATIONS SECTION -->
                    <div class="tab-pane fade {{ $activeTab === 'integrations' ? 'show active' : '' }}" id="integrations-section" role="tabpanel" aria-labelledby="integrations-section-tab">
                        <x-ui.odoo-form-ui type="sheet">
                            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                <i class="feather-link-2 text-primary me-2"></i>{{ __('account.connected_gateways') }}
                            </h5>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 bg-light-soft d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-bold text-dark mb-0">
                                                <i class="feather-mail text-info me-1"></i>{{ __('account.email_smtp_gateway') }}
                                            </h6>
                                            @if ($emailConfigured)
                                                <span class="badge bg-soft-success text-success"><i class="feather-check-circle me-1"></i>{{ __('account.configured') }}</span>
                                            @else
                                                <span class="badge bg-soft-warning text-warning">{{ __('account.inactive') }}</span>
                                            @endif
                                        </div>
                                        <p class="text-muted fs-13 mb-3">
                                            {{ __('account.email_gateway_desc') }}
                                        </p>
                                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="fs-12 text-muted">{{ __('account.accounts_found', ['count' => $emailAccountCount]) }}</span>
                                            @if ($canManagePlatformSettings && Route::has('platform.emailSettings.index'))
                                                <x-ui.button href="{{ route('platform.emailSettings.index') }}" variant="secondary" size="sm" icon="feather-external-link">
                                                    {{ __('account.manage_smtp') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 bg-light-soft d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-bold text-dark mb-0">
                                                <i class="feather-message-circle text-success me-1"></i>{{ __('account.whatsapp_bridge') }}
                                            </h6>
                                            @if ($whatsappConnected)
                                                <span class="badge bg-soft-success text-success"><i class="feather-check-circle me-1"></i>{{ __('account.connected') }}</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">{{ __(ucfirst($whatsappStatus)) }}</span>
                                            @endif
                                        </div>
                                        <p class="text-muted fs-13 mb-3">
                                            {{ __('account.whatsapp_bridge_desc') }}
                                        </p>
                                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="fs-12 text-muted">{{ $whatsappNumber ?: __('account.no_phone_attached') }}</span>
                                            @if ($canManagePlatformSettings && Route::has('platform.whatsappSettings.index'))
                                                <x-ui.button href="{{ route('platform.whatsappSettings.index') }}" variant="secondary" size="sm" icon="feather-external-link">
                                                    {{ __('account.manage_whatsapp') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 bg-light-soft d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-bold text-dark mb-0">
                                                <i class="feather-globe text-primary me-1"></i>{{ __('account.meta_leads_setup') }}
                                            </h6>
                                            <span class="badge bg-soft-primary text-primary">{{ __('account.marketing_and_ads') }}</span>
                                        </div>
                                        <p class="text-muted fs-13 mb-3">
                                            {{ __('account.meta_leads_desc') }}
                                        </p>
                                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="fs-12 text-muted">{{ __('account.campaigns_and_instant_forms') }}</span>
                                            @if ($canManagePlatformSettings && Route::has('platform.metaSettings.index'))
                                                <x-ui.button href="{{ route('platform.metaSettings.index') }}" variant="secondary" size="sm" icon="feather-external-link">
                                                    {{ __('account.manage_meta_setup') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 bg-light-soft d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-bold text-dark mb-0">
                                                <i class="feather-phone-call text-purple me-1"></i>{{ __('account.twilio_telephony') }}
                                            </h6>
                                            <span class="badge bg-soft-purple text-purple">{{ __('account.cloud_calling') }}</span>
                                        </div>
                                        <p class="text-muted fs-13 mb-3">
                                            {{ __('account.twilio_desc') }}
                                        </p>
                                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                            <span class="fs-12 text-muted">{{ __('account.inbound_outbound_ai_notes') }}</span>
                                            @if ($canManagePlatformSettings && Route::has('platform.twilioSettings.index'))
                                                <x-ui.button href="{{ route('platform.twilioSettings.index') }}" variant="secondary" size="sm" icon="feather-external-link">
                                                    {{ __('account.manage_twilio') }}
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
                                            <i class="feather-sliders text-primary me-2"></i>{{ __('account.rule_based_triggers') }}
                                        </h6>
                                        <p class="text-muted fs-13 mb-0">{{ __('account.rule_based_triggers_desc') }}</p>
                                    </div>
                                    <x-ui.button href="{{ route('platform.notification-rules.index') }}" variant="secondary" icon="feather-settings">
                                        {{ __('account.configure_rules') }}
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
                                    <i class="feather-credit-card text-primary me-2"></i>{{ __('account.subscription_usage_limits') }}
                                </h5>
                                @if (Route::has('platform.subscription.index') && $canManagePlatformSettings)
                                    <x-ui.button href="{{ route('platform.subscription.index') }}" variant="primary" size="sm" icon="feather-arrow-up-right">
                                        {{ __('account.manage_plan') }}
                                    </x-ui.button>
                                @endif
                            </div>

                            <div class="row g-4 mb-4">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded text-center border">
                                        <div class="text-muted fs-12 text-uppercase fw-bold mb-1">{{ __('account.current_plan') }}</div>
                                        <h4 class="fw-bold text-dark mb-0">{{ $tenant?->planCatalog?->name ?? ucfirst($tenant?->plan ?? 'Enterprise') }}</h4>
                                        <span class="badge bg-soft-success text-success mt-1">
                                            {{ ($tenant?->subscription_status && strtolower($tenant->subscription_status) === 'active') ? __('account.active') : ucfirst($tenant?->subscription_status ?? __('account.active')) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded text-center border">
                                        <div class="text-muted fs-12 text-uppercase fw-bold mb-1">{{ __('account.user_slots') }}</div>
                                        <h4 class="fw-bold text-dark mb-0">
                                            {{ $currentUserCount }} / {{ $maxUsers ?? '∞' }}
                                        </h4>
                                        <span class="fs-12 text-muted">
                                            {{ $remainingUserSlots !== null ? __('account.slots_remaining', ['count' => $remainingUserSlots]) : __('account.unlimited') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded text-center border">
                                        <div class="text-muted fs-12 text-uppercase fw-bold mb-1">{{ __('account.allocated_storage') }}</div>
                                        <h4 class="fw-bold text-dark mb-0">
                                            {{ number_format(($maxStorageMb ?: 10240) / 1024, 1) }} GB
                                        </h4>
                                        <span class="fs-12 text-muted">{{ __('account.doc_storage_limit') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 border rounded bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fs-13 fw-semibold text-dark">{{ __('account.user_seats_utilization') }}</span>
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
                                    <i class="feather-alert-triangle me-2"></i>{{ __('account.danger_zone') }}
                                </h5>
                                <h6 class="fw-bold text-dark mt-3 mb-1">{{ __('account.deactivate_account') }}</h6>
                                <p class="text-muted fs-13 mb-4">
                                    {{ __('account.deactivate_account_desc') }}
                                </p>
                                <div>
                                    <x-ui.button type="button" variant="danger" icon="feather-user-x" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                        {{ __('account.deactivate_my_account') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        </x-ui.odoo-form-ui>
                    </div>

                </div>
            </div>
        </div>

        <!-- Deactivate Modal (Common Modal Component) -->
        <x-ui.modal id="deactivateModal" :title="__('account.confirm_account_deactivation')" :centered="true">
            <form action="{{ route('account.settings.delete') }}" method="POST" id="deactivateForm" autocomplete="off">
                @csrf
                @method('DELETE')

                {{-- Decoy fields to trap browser password autofill --}}
                <input type="text" name="_decoy_username" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
                <input type="password" name="_decoy_password" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">

                <p class="fs-13 text-muted mb-3">
                    {{ __('account.deactivate_warning_prefix') }}
                    <strong class="text-danger">DEACTIVATE</strong> {{ __('account.deactivate_warning_suffix') }}
                </p>

                <x-ui.odoo-form-ui type="sheet">
                    <x-ui.odoo-form-ui type="input" :label="__('account.type_confirmation')" name="confirmation" id="confirmation" placeholder="DEACTIVATE" :value="old('confirmation')" :required="true" autocomplete="one-time-code" autocapitalize="characters" autocorrect="off" data-lpignore="true" :error-text="$errors->first('confirmation')" />
                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('account.your_current_password')" name="current_password" id="deactivate_password" :placeholder="__('account.enter_current_password')" :required="true" autocomplete="new-password" data-lpignore="true" :error-text="$errors->first('current_password')" />
                </x-ui.odoo-form-ui>
            </form>

            <x-slot name="footer">
                <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">
                    {{ __('account.cancel') }}
                </x-ui.button>
                <x-ui.button type="button" variant="danger" icon="feather-user-x" onclick="document.getElementById('deactivateForm').submit();">
                    {{ __('account.permanently_deactivate') }}
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
