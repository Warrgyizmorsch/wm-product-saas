@php
    $statuses = \App\Models\Tenant::statuses();
    $plans = \App\Models\Tenant::plans();
    $subscriptionStatuses = \App\Models\Tenant::subscriptionStatuses();
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Company Name" name="name" :value="old('name', $tenant->name)" :required="true" class="@error('name') is-invalid @enderror" />
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Display Name" name="display_name" :value="old('display_name', $settings['display_name'] ?? $tenant->name)" placeholder="shown in header and sidebar" class="@error('display_name') is-invalid @enderror" />
        @error('display_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Slug" name="slug" :value="old('slug', $tenant->slug)" placeholder="auto-created if empty" class="@error('slug') is-invalid @enderror" />
        @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Domain" name="domain" :value="old('domain', $tenant->domain)" placeholder="company.example.com" class="@error('domain') is-invalid @enderror" />
        @error('domain')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="email" label="Billing Email" name="billing_email" :value="old('billing_email', $tenant->billing_email)" placeholder="billing@company.com" class="@error('billing_email') is-invalid @enderror" />
        @error('billing_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true" :searchable="false" class="@error('status') is-invalid @enderror">
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $tenant->status ?: \App\Models\Tenant::STATUS_TRIAL) === $value)>{{ $label }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="select" label="Plan" name="plan" :required="true" :searchable="false" class="@error('plan') is-invalid @enderror">
            @foreach ($plans as $value => $label)
                <option value="{{ $value }}" @selected(old('plan', $tenant->plan ?: \App\Models\Tenant::PLAN_STARTER) === $value)>{{ $label }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        @error('plan')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="select" label="Subscription" name="subscription_status" :required="true" :searchable="false" class="@error('subscription_status') is-invalid @enderror">
            @foreach ($subscriptionStatuses as $value => $label)
                <option value="{{ $value }}" @selected(old('subscription_status', $tenant->subscription_status ?: \App\Models\Tenant::SUBSCRIPTION_TRIAL) === $value)>{{ $label }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        @error('subscription_status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Max Users" name="max_users" :value="old('max_users', $tenant->max_users)" min="1" class="@error('max_users') is-invalid @enderror" />
        @error('max_users')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Storage MB" name="max_storage_mb" :value="old('max_storage_mb', $tenant->max_storage_mb)" min="1" class="@error('max_storage_mb') is-invalid @enderror" />
        @error('max_storage_mb')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="date" label="Trial Ends" name="trial_ends_at" :value="old('trial_ends_at', optional($tenant->trial_ends_at)->format('Y-m-d'))" class="@error('trial_ends_at') is-invalid @enderror" />
        @error('trial_ends_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="date" label="Plan Starts" name="plan_started_at" :value="old('plan_started_at', optional($tenant->plan_started_at)->format('Y-m-d'))" class="@error('plan_started_at') is-invalid @enderror" />
        @error('plan_started_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="date" label="Plan Expires" name="plan_expires_at" :value="old('plan_expires_at', optional($tenant->plan_expires_at)->format('Y-m-d'))" class="@error('plan_expires_at') is-invalid @enderror" />
        @error('plan_expires_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" label="Timezone" name="timezone" :value="old('timezone', $tenant->timezone ?: 'UTC')" :required="true" class="@error('timezone') is-invalid @enderror" />
        @error('timezone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" label="Locale" name="locale" :value="old('locale', $tenant->locale ?: 'en')" :required="true" class="@error('locale') is-invalid @enderror" />
        @error('locale')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" label="Currency" name="currency" :value="old('currency', $settings['currency'] ?? 'INR')" class="@error('currency') is-invalid @enderror" />
        @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Branch" name="branch" :value="old('branch', $settings['branch'] ?? 'Main Office')" class="@error('branch') is-invalid @enderror" />
        @error('branch')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Financial Year" name="financial_year" :value="old('financial_year', $settings['financial_year'] ?? 'FY '.now()->format('Y'))" class="@error('financial_year') is-invalid @enderror" />
        @error('financial_year')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="file" label="Full Logo" name="logo_full" accept=".jpg,.jpeg,.png,.webp,.svg,image/*" class="@error('logo_full') is-invalid @enderror" />
        @error('logo_full')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @if (!empty($settings['logo_full']))
            <div class="mt-2 p-2 border rounded bg-light d-inline-flex align-items-center">
                <img src="{{ tenant_branding_url($settings['logo_full'], 'assets/images/logo-full.png') }}" alt="{{ $settings['display_name'] ?? $tenant->name }} logo" style="max-height: 36px; max-width: 180px;">
            </div>
        @endif
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="file" label="Abbreviated Logo" name="logo_abbr" accept=".jpg,.jpeg,.png,.webp,.svg,image/*" class="@error('logo_abbr') is-invalid @enderror" />
        @error('logo_abbr')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @if (!empty($settings['logo_abbr']))
            <div class="mt-2 p-2 border rounded bg-light d-inline-flex align-items-center">
                <img src="{{ tenant_branding_url($settings['logo_abbr'], 'assets/images/logo-abbr.png') }}" alt="{{ $settings['display_name'] ?? $tenant->name }} logo" style="max-height: 36px; max-width: 80px;">
            </div>
        @endif
    </div>
    @if ($isCreate ?? false)
        <div class="col-12">
            <div class="border-top pt-3 mt-2">
                <h6 class="fw-bold mb-3">Tenant Owner</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" label="Owner Name" name="owner_name" :value="old('owner_name')" placeholder="Primary admin" class="@error('owner_name') is-invalid @enderror" />
                        @error('owner_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" inputType="email" label="Owner Email" name="owner_email" :value="old('owner_email')" placeholder="admin@company.com" class="@error('owner_email') is-invalid @enderror" />
                        @error('owner_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <x-ui.odoo-form-ui type="input" inputType="password" label="Password" name="owner_password" class="@error('owner_password') is-invalid @enderror" />
                        @error('owner_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <x-ui.odoo-form-ui type="input" inputType="password" label="Confirm" name="owner_password_confirmation" />
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
