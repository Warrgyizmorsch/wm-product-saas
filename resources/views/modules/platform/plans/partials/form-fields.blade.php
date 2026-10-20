@php
    $suffix = $plan->id ?? 'new';
    $features = old('features', $plan->features ?? []);
    $availableFeatures = \App\Http\Middleware\EnsureTenantModuleAccess::GATED_MODULES;
    $featureCatalog = app(\App\Domains\Platform\Services\PlanFeatureGate::class)->catalog();
    $excludedFeatures = $plan->excluded_features ?? [];
    $includedFeatures = old('feature_picker')
        ? old('included_features', [])
        : array_values(array_diff(collect($featureCatalog)->flatMap(fn ($g) => array_keys($g['features']))->all(), $excludedFeatures));
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
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Per user / month — monthly billing" name="monthly_price_per_user" :value="old('monthly_price_per_user', $plan->monthly_price_per_user)" min="0" helperText="Whole rupees, excl. GST. Blank = sum of its modules' monthly prices." class="@error('monthly_price_per_user') is-invalid @enderror" />
        @error('monthly_price_per_user')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Per user / month — billed yearly" name="yearly_price_per_user" :value="old('yearly_price_per_user', $plan->yearly_price_per_user)" min="0" helperText="Per user PER MONTH, charged 12 months up front — not the yearly total. Blank = sum of its modules' yearly prices." class="@error('yearly_price_per_user') is-invalid @enderror" />
        @error('yearly_price_per_user')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Flat price (legacy)" name="price" :value="old('price', $plan->price ?? 0)" min="0" helperText="Used by the current one-time checkout until per-user billing goes live. 0 = free plan." class="@error('price') is-invalid @enderror" />
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
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Min Users" name="min_users" :value="old('min_users', $plan->min_users)" min="1" helperText="Fewest users it can be bought for. Blank = no minimum." class="@error('min_users') is-invalid @enderror" />
        @error('min_users')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Max Users" name="max_users" :value="old('max_users', $plan->max_users)" min="1" helperText="Blank = unlimited." class="@error('max_users') is-invalid @enderror" />
        @error('max_users')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Storage MB" name="max_storage_mb" :value="old('max_storage_mb', $plan->max_storage_mb)" min="1" helperText="Blank = unlimited." class="@error('max_storage_mb') is-invalid @enderror" />
        @error('max_storage_mb')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
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
                    <label class="form-check-label" for="feature_{{ $feature }}_{{ $suffix }}">{{ config("navigation.apps.$feature.label", ucfirst($feature)) }}</label>
                </div>
            @endforeach
        </div>
        @error('features')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="odoo-form-label d-block mb-1">Features</label>
        <p class="fs-12 text-muted mb-2">Untick what this plan leaves out. Left-out features stay <strong>view-only</strong> — tenants still see their data but can't create, change or post. New features are included by default.</p>
        <input type="hidden" name="feature_picker" value="1">
        @error('included_features')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="plan-feature-groups" data-suffix="{{ $suffix }}">
            @foreach ($featureCatalog as $module => $group)
                @php $groupIncluded = count(array_intersect(array_keys($group['features']), $includedFeatures)); @endphp
                <details class="plan-feature-group" data-module="{{ $module }}">
                    <summary>
                        <span class="fw-semibold">{{ $group['label'] }}</span>
                        <span class="plan-feature-count text-muted fs-12">{{ $groupIncluded }} / {{ count($group['features']) }} included</span>
                        <span class="plan-feature-off fs-11">Module not in plan</span>
                    </summary>
                    <div class="plan-feature-grid">
                        @foreach ($group['features'] as $key => $feature)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="included_features[]" value="{{ $key }}"
                                    id="pf_{{ str_replace('.', '_', $key) }}_{{ $suffix }}" @checked(in_array($key, $includedFeatures, true))>
                                <label class="form-check-label fs-13" for="pf_{{ str_replace('.', '_', $key) }}_{{ $suffix }}">{{ $feature['label'] }}</label>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
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
    @push('styles')
        <style>
            .plan-feature-groups { border: 1px solid var(--ax-border, #e2e8f0); border-radius: 10px; overflow: hidden; }
            .plan-feature-group + .plan-feature-group { border-top: 1px solid var(--ax-border, #e2e8f0); }
            .plan-feature-group summary { display: flex; align-items: center; gap: 10px; padding: 10px 14px; cursor: pointer; list-style: none; background: var(--ax-surface-muted, #f8fafc); }
            .plan-feature-group summary::-webkit-details-marker { display: none; }
            .plan-feature-group summary::before { content: '\203A'; font-size: 18px; line-height: 1; transition: transform .15s ease; color: var(--ax-text-muted, #64748b); }
            .plan-feature-group[open] summary::before { transform: rotate(90deg); }
            .plan-feature-count { margin-left: auto; }
            .plan-feature-off { display: none; padding: 1px 8px; border-radius: 6px; color: var(--ax-pending-strong, #b45309); background: var(--ax-pending-soft, #fffbeb); }
            .plan-feature-group.module-off .plan-feature-off { display: inline-block; }
            .plan-feature-group.module-off .plan-feature-grid { opacity: .5; }
            .plan-feature-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px 16px; padding: 12px 14px 14px; }
        </style>
    @endpush
    @push('scripts')
        <script>
            // Feature picker: live "n / m included" per group, and dim groups whose module is unticked.
            (function () {
                document.querySelectorAll('.plan-feature-groups').forEach(function (wrap) {
                    var form = wrap.closest('form');
                    function refresh() {
                        wrap.querySelectorAll('.plan-feature-group').forEach(function (group) {
                            var boxes = group.querySelectorAll('input[type=checkbox]');
                            var on = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length;
                            group.querySelector('.plan-feature-count').textContent = on + ' / ' + boxes.length + ' included';
                            var moduleBox = form && form.querySelector('input[name="features[]"][value="' + group.dataset.module + '"]');
                            group.classList.toggle('module-off', !!moduleBox && !moduleBox.checked);
                        });
                    }
                    (form || wrap).addEventListener('change', refresh);
                    refresh();
                });
            })();

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
