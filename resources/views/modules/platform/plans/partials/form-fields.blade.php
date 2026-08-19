@php
    $suffix = $plan->id ?? 'new';
    $features = old('features', $plan->features ?? []);
    $availableFeatures = ['crm', 'inventory', 'sales', 'purchase', 'production', 'hrms', 'accounting', 'projects'];
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Name" name="name" id="plan_name_{{ $suffix }}" :value="old('name', $plan->name)" :required="true" class="plan-name-input @error('name') is-invalid @enderror" data-suffix="{{ $suffix }}" />
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Slug" name="slug" id="plan_slug_{{ $suffix }}" :value="old('slug', $plan->slug)" placeholder="auto-created if empty" class="@error('slug') is-invalid @enderror" />
        @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div id="plan_slug_preview_{{ $suffix }}" class="text-primary fs-11 mt-1"></div>
    </div>
    <div class="col-12">
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="2" :value="old('description', $plan->description)" class="@error('description') is-invalid @enderror" />
        @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <h6 class="fw-bold text-uppercase fs-11 text-muted mb-2 border-top pt-3 mt-2">Pricing</h6>
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Price" name="price" :value="old('price', $plan->price ?? 0)" min="0" helperText="0 = free plan." class="@error('price') is-invalid @enderror" />
        @error('price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" label="Currency" name="currency" :value="old('currency', $plan->currency ?? 'INR')" class="@error('currency') is-invalid @enderror" />
        @error('currency')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="select" label="Billing Cycle" name="billing_cycle" :searchable="false" class="@error('billing_cycle') is-invalid @enderror">
            @foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label)
                <option value="{{ $value }}" @selected(old('billing_cycle', $plan->billing_cycle ?: 'monthly') === $value)>{{ $label }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        @error('billing_cycle')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <h6 class="fw-bold text-uppercase fs-11 text-muted mb-2 border-top pt-3 mt-2">Limits</h6>
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Max Users" name="max_users" :value="old('max_users', $plan->max_users)" min="1" helperText="Blank = unlimited." class="@error('max_users') is-invalid @enderror" />
        @error('max_users')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Storage MB" name="max_storage_mb" :value="old('max_storage_mb', $plan->max_storage_mb)" min="1" helperText="Blank = unlimited." class="@error('max_storage_mb') is-invalid @enderror" />
        @error('max_storage_mb')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Trial Days" name="trial_days" :value="old('trial_days', $plan->trial_days)" min="1" helperText="Blank = no trial." class="@error('trial_days') is-invalid @enderror" />
        @error('trial_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <h6 class="fw-bold text-uppercase fs-11 text-muted mb-2 border-top pt-3 mt-2">Features &amp; Status</h6>
    </div>
    <div class="col-12">
        <label class="odoo-form-label d-block mb-2">Modules Included</label>
        <div class="d-flex flex-wrap gap-3">
            @foreach ($availableFeatures as $feature)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="features[]" value="{{ $feature }}" id="feature_{{ $feature }}_{{ $suffix }}" @checked(in_array($feature, $features ?? [], true))>
                    <label class="form-check-label text-capitalize" for="feature_{{ $feature }}_{{ $suffix }}">{{ $feature }}</label>
                </div>
            @endforeach
        </div>
        @error('features')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Sort Order" name="sort_order" :value="old('sort_order', $plan->sort_order ?? 0)" min="0" class="@error('sort_order') is-invalid @enderror" />
        @error('sort_order')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <div class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" role="switch" name="is_demo" value="1" id="is_demo_{{ $suffix }}" @checked(old('is_demo', $plan->is_demo ?? false))>
            <label class="form-check-label" for="is_demo_{{ $suffix }}">Demo plan</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active_{{ $suffix }}" @checked(old('is_active', $plan->exists ? $plan->is_active : true))>
            <label class="form-check-label" for="is_active_{{ $suffix }}">Active</label>
        </div>
    </div>
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
                    if (!e.target.classList || !e.target.classList.contains('plan-name-input')) return;

                    var suffix = e.target.dataset.suffix;
                    var slugField = document.getElementById('plan_slug_' + suffix);
                    var preview = document.getElementById('plan_slug_preview_' + suffix);
                    if (!slugField || !preview) return;

                    if (slugField.value.trim() !== '') {
                        preview.textContent = '';
                        return;
                    }

                    var slug = slugify(e.target.value);
                    preview.textContent = slug ? 'Will be created as: ' + slug : '';
                });
            })();
        </script>
    @endpush
@endonce
