@php
    $statuses = \App\Models\Tenant::statuses();
    $plans = \App\Models\Tenant::plans();
    $subscriptionStatuses = \App\Models\Tenant::subscriptionStatuses();
    $locales = collect(config('localization.supported', []))->map(fn ($meta) => $meta['name'] ?? $meta['native'] ?? '');
    $timezones = \DateTimeZone::listIdentifiers();
    $currencies = [
        'INR' => 'INR — Indian Rupee',
        'USD' => 'USD — US Dollar',
        'EUR' => 'EUR — Euro',
        'GBP' => 'GBP — British Pound',
        'AED' => 'AED — UAE Dirham',
        'SGD' => 'SGD — Singapore Dollar',
        'AUD' => 'AUD — Australian Dollar',
        'CAD' => 'CAD — Canadian Dollar',
    ];
    $suffix = $tenant->id ?? 'new';
@endphp

<div class="row g-3">
    <div class="col-12">
        <h6 class="fw-bold text-uppercase fs-11 text-muted mb-2">Company Details</h6>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Company Name" name="name" id="tenant_name_{{ $suffix }}" :value="old('name', $tenant->name)" :required="true" class="tenant-name-input @error('name') is-invalid @enderror" data-suffix="{{ $suffix }}" />
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Display Name" name="display_name" :value="old('display_name', $settings['display_name'] ?? $tenant->name)" placeholder="shown in header and sidebar" class="@error('display_name') is-invalid @enderror" />
        @error('display_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Slug" name="slug" id="tenant_slug_{{ $suffix }}" :value="old('slug', $tenant->slug)" placeholder="auto-created if empty" helperText="Identifies this tenant when there's no custom domain — used in the X-Tenant header and local testing." class="@error('slug') is-invalid @enderror" />
        @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div id="slug_preview_{{ $suffix }}" class="text-primary fs-11 mt-1"></div>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Domain" name="domain" :value="old('domain', $tenant->domain)" placeholder="company.example.com" helperText="Custom production domain, if this tenant is routed by hostname instead of slug." class="@error('domain') is-invalid @enderror" />
        @error('domain')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <h6 class="fw-bold text-uppercase fs-11 text-muted mb-2 border-top pt-3 mt-2">Plan &amp; Billing</h6>
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true" :searchable="false" class="@error('status') is-invalid @enderror">
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $tenant->status ?: \App\Models\Tenant::STATUS_TRIAL) === $value)>{{ $label }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="Plan" name="plan" :required="true" :searchable="false" class="@error('plan') is-invalid @enderror">
            @foreach ($plans as $value => $label)
                <option value="{{ $value }}" @selected(old('plan', $tenant->plan ?: \App\Models\Tenant::PLAN_STARTER) === $value)>{{ $label }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        @error('plan')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="Subscription" name="subscription_status" :required="true" :searchable="false" class="@error('subscription_status') is-invalid @enderror">
            @foreach ($subscriptionStatuses as $value => $label)
                <option value="{{ $value }}" @selected(old('subscription_status', $tenant->subscription_status ?: \App\Models\Tenant::SUBSCRIPTION_TRIAL) === $value)>{{ $label }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        @error('subscription_status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="email" label="Billing Email" name="billing_email" :value="old('billing_email', $tenant->billing_email)" placeholder="billing@company.com" class="@error('billing_email') is-invalid @enderror" />
        @error('billing_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Max Users" name="max_users" :value="old('max_users', $tenant->max_users)" min="1" class="@error('max_users') is-invalid @enderror" />
        @error('max_users')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Storage MB" name="max_storage_mb" :value="old('max_storage_mb', $tenant->max_storage_mb)" min="1" class="@error('max_storage_mb') is-invalid @enderror" />
        @error('max_storage_mb')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <h6 class="fw-bold text-uppercase fs-11 text-muted mb-2 border-top pt-3 mt-2">Localization &amp; Currency</h6>
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="Timezone" name="timezone" :required="true" class="@error('timezone') is-invalid @enderror">
            @php $currentTimezone = old('timezone', $tenant->timezone ?: 'UTC'); @endphp
            <option value="{{ $currentTimezone }}" selected>{{ $currentTimezone }}</option>
            @foreach ($timezones as $tz)
                @continue($tz === $currentTimezone)
                <option value="{{ $tz }}">{{ $tz }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        @error('timezone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="Locale" name="locale" :required="true" :searchable="false" class="@error('locale') is-invalid @enderror">
            @php $currentLocale = old('locale', $tenant->locale ?: 'en'); @endphp
            @foreach ($locales as $value => $label)
                <option value="{{ $value }}" @selected($currentLocale === $value)>{{ $label }} ({{ $value }})</option>
            @endforeach
            @if (! $locales->has($currentLocale))
                <option value="{{ $currentLocale }}" selected>{{ $currentLocale }}</option>
            @endif
        </x-ui.odoo-form-ui>
        @error('locale')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="Currency" name="currency" :searchable="false" class="@error('currency') is-invalid @enderror">
            @php $currentCurrency = old('currency', $settings['currency'] ?? 'INR'); @endphp
            @foreach ($currencies as $value => $label)
                <option value="{{ $value }}" @selected($currentCurrency === $value)>{{ $label }}</option>
            @endforeach
            @if (! isset($currencies[$currentCurrency]))
                <option value="{{ $currentCurrency }}" selected>{{ $currentCurrency }}</option>
            @endif
        </x-ui.odoo-form-ui>
        @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <h6 class="fw-bold text-uppercase fs-11 text-muted mb-2 border-top pt-3 mt-2">Branding</h6>
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
        <x-ui.odoo-form-ui type="input" inputType="file" label="Full Logo" name="logo_full" accept=".jpg,.jpeg,.png,.webp,.svg,image/*" class="tenant-logo-input @error('logo_full') is-invalid @enderror" data-suffix="{{ $suffix }}" data-logo-kind="full" />
        @error('logo_full')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="d-flex gap-2 mt-2">
            @if (!empty($settings['logo_full']))
                <div class="p-2 border rounded bg-light d-inline-flex align-items-center">
                    <img src="{{ tenant_branding_url($settings['logo_full'], 'assets/images/logo-full.png') }}" alt="{{ $settings['display_name'] ?? $tenant->name }} logo" style="max-height: 36px; max-width: 180px;">
                </div>
            @endif
            <div id="logo_preview_full_{{ $suffix }}" class="d-inline-flex align-items-center"></div>
        </div>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="file" label="Abbreviated Logo" name="logo_abbr" accept=".jpg,.jpeg,.png,.webp,.svg,image/*" class="tenant-logo-input @error('logo_abbr') is-invalid @enderror" data-suffix="{{ $suffix }}" data-logo-kind="abbr" />
        @error('logo_abbr')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="d-flex gap-2 mt-2">
            @if (!empty($settings['logo_abbr']))
                <div class="p-2 border rounded bg-light d-inline-flex align-items-center">
                    <img src="{{ tenant_branding_url($settings['logo_abbr'], 'assets/images/logo-abbr.png') }}" alt="{{ $settings['display_name'] ?? $tenant->name }} logo" style="max-height: 36px; max-width: 80px;">
                </div>
            @endif
            <div id="logo_preview_abbr_{{ $suffix }}" class="d-inline-flex align-items-center"></div>
        </div>
    </div>

    <div class="col-12">
        <button type="button" class="btn btn-sm btn-link text-decoration-none ps-0 advanced-options-toggle" data-bs-toggle="collapse" data-bs-target="#advancedOptions-{{ $suffix }}" aria-expanded="false" aria-controls="advancedOptions-{{ $suffix }}">
            <i class="feather-sliders me-1"></i> Advanced options (trial &amp; plan dates)
        </button>
        <div class="collapse" id="advancedOptions-{{ $suffix }}">
            <div class="row g-3 pt-2 border-top mt-1">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Trial Ends" name="trial_ends_at" :value="old('trial_ends_at', optional($tenant->trial_ends_at)->format('Y-m-d'))" class="@error('trial_ends_at') is-invalid @enderror" />
                    @error('trial_ends_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Plan Starts" name="plan_started_at" :value="old('plan_started_at', optional($tenant->plan_started_at)->format('Y-m-d'))" class="@error('plan_started_at') is-invalid @enderror" />
                    @error('plan_started_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Plan Expires" name="plan_expires_at" :value="old('plan_expires_at', optional($tenant->plan_expires_at)->format('Y-m-d'))" class="@error('plan_expires_at') is-invalid @enderror" />
                    @error('plan_expires_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    @if ($isCreate ?? false)
        <div class="col-12">
            <div class="border-top pt-3 mt-2">
                <h6 class="fw-bold mb-1">Tenant Owner</h6>
                <p class="text-muted fs-11 mb-3">Optional — leave these blank to create the tenant without an initial admin login. You can add users later with <code>php artisan rbac:create-user</code>.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Owner Name" name="owner_name" :value="old('owner_name')" placeholder="Primary admin" class="@error('owner_name') is-invalid @enderror" />
                        @error('owner_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="email" label="Owner Email" name="owner_email" :value="old('owner_email')" placeholder="admin@company.com" class="@error('owner_email') is-invalid @enderror" />
                        @error('owner_email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <div class="odoo-form-group">
                            <label class="odoo-form-label" for="owner_password_{{ $suffix }}">Password</label>
                            <div class="flex-grow-1 position-relative">
                                <input type="password" name="owner_password" id="owner_password_{{ $suffix }}" class="odoo-form-control pe-4 @error('owner_password') is-invalid @enderror">
                                <button type="button" class="password-toggle-btn btn btn-link btn-sm p-0 position-absolute end-0 top-0" data-target="owner_password_{{ $suffix }}" tabindex="-1" aria-label="Toggle password visibility">
                                    <i class="feather-eye fs-14 text-muted"></i>
                                </button>
                                <div class="text-muted fs-11 mt-1">Minimum 8 characters.</div>
                                @error('owner_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="odoo-form-group">
                            <label class="odoo-form-label" for="owner_password_confirmation_{{ $suffix }}">Confirm</label>
                            <div class="flex-grow-1 position-relative">
                                <input type="password" name="owner_password_confirmation" id="owner_password_confirmation_{{ $suffix }}" class="odoo-form-control pe-4">
                                <button type="button" class="password-toggle-btn btn btn-link btn-sm p-0 position-absolute end-0 top-0" data-target="owner_password_confirmation_{{ $suffix }}" tabindex="-1" aria-label="Toggle password visibility">
                                    <i class="feather-eye fs-14 text-muted"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function slugify(text) {
                    return text
                        .toString()
                        .trim()
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                }

                document.addEventListener('input', function (e) {
                    if (!e.target.classList || !e.target.classList.contains('tenant-name-input')) return;

                    var suffix = e.target.dataset.suffix;
                    var slugField = document.getElementById('tenant_slug_' + suffix);
                    var preview = document.getElementById('slug_preview_' + suffix);
                    if (!slugField || !preview) return;

                    if (slugField.value.trim() !== '') {
                        preview.textContent = '';
                        return;
                    }

                    var slug = slugify(e.target.value);
                    preview.textContent = slug ? 'Will be created as: ' + slug : '';
                });

                document.addEventListener('change', function (e) {
                    if (!e.target.classList || !e.target.classList.contains('tenant-logo-input')) return;

                    var suffix = e.target.dataset.suffix;
                    var kind = e.target.dataset.logoKind;
                    var preview = document.getElementById('logo_preview_' + kind + '_' + suffix);
                    if (!preview) return;

                    var file = e.target.files && e.target.files[0];
                    if (!file) {
                        preview.innerHTML = '';
                        return;
                    }

                    var reader = new FileReader();
                    reader.onload = function (ev) {
                        preview.innerHTML = '<img src="' + ev.target.result + '" alt="New logo preview" style="max-height:36px;max-width:180px;" class="border rounded p-1 bg-light">';
                    };
                    reader.readAsDataURL(file);
                });

                document.addEventListener('click', function (e) {
                    var toggle = e.target.closest('.password-toggle-btn');
                    if (!toggle) return;

                    var input = document.getElementById(toggle.dataset.target);
                    if (!input) return;

                    var isPassword = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPassword ? 'text' : 'password');

                    var icon = toggle.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('feather-eye', !isPassword);
                        icon.classList.toggle('feather-eye-off', isPassword);
                    }
                });

                document.addEventListener('show.bs.collapse', function (e) {
                    if (!e.target.id || e.target.id.indexOf('advancedOptions-') !== 0) return;
                    var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
                    if (btn) btn.setAttribute('aria-expanded', 'true');
                });

                document.addEventListener('hide.bs.collapse', function (e) {
                    if (!e.target.id || e.target.id.indexOf('advancedOptions-') !== 0) return;
                    var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                });
            })();
        </script>
    @endpush
@endonce
