@php
    $settings = $tenant->settings ?? [];
@endphp

<div class="row">
    <div class="col-xxl-8 col-xl-9 mx-auto">
        <form action="{{ $action }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="feather-grid me-2 text-primary"></i>Tenant Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $tenant->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Display Name</label>
                            <input type="text" name="display_name" class="form-control @error('display_name') is-invalid @enderror" value="{{ old('display_name', $settings['display_name'] ?? $tenant->name) }}" placeholder="shown in header and sidebar">
                            @error('display_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Slug</label>
                            <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $tenant->slug) }}" placeholder="auto-created if empty">
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Domain</label>
                            <input type="text" name="domain" class="form-control @error('domain') is-invalid @enderror" value="{{ old('domain', $tenant->domain) }}" placeholder="company.example.com">
                            @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-dark">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $tenant->status ?: 'active') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-dark">Plan <span class="text-danger">*</span></label>
                            <select name="plan" class="form-select @error('plan') is-invalid @enderror" required>
                                @foreach (['starter' => 'Starter', 'pro' => 'Pro', 'enterprise' => 'Enterprise'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('plan', $tenant->plan ?: 'starter') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('plan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark">Timezone <span class="text-danger">*</span></label>
                            <input type="text" name="timezone" class="form-control @error('timezone') is-invalid @enderror" value="{{ old('timezone', $tenant->timezone ?: 'UTC') }}" required>
                            @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark">Locale <span class="text-danger">*</span></label>
                            <input type="text" name="locale" class="form-control @error('locale') is-invalid @enderror" value="{{ old('locale', $tenant->locale ?: 'en') }}" required>
                            @error('locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark">Currency</label>
                            <input type="text" name="currency" class="form-control @error('currency') is-invalid @enderror" value="{{ old('currency', $settings['currency'] ?? 'INR') }}">
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Branch</label>
                            <input type="text" name="branch" class="form-control @error('branch') is-invalid @enderror" value="{{ old('branch', $settings['branch'] ?? 'Main Office') }}">
                            @error('branch')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Financial Year</label>
                            <input type="text" name="financial_year" class="form-control @error('financial_year') is-invalid @enderror" value="{{ old('financial_year', $settings['financial_year'] ?? 'FY '.now()->format('Y')) }}">
                            @error('financial_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Full Logo</label>
                            <input type="file" name="logo_full" class="form-control @error('logo_full') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.svg,image/*">
                            @error('logo_full')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if (!empty($settings['logo_full']))
                                <div class="mt-2 p-2 border rounded bg-light d-inline-flex align-items-center">
                                    <img src="{{ tenant_branding_url($settings['logo_full'], 'assets/images/logo-full.png') }}" alt="{{ $settings['display_name'] ?? $tenant->name }} logo" style="max-height: 36px; max-width: 180px;">
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Abbreviated Logo</label>
                            <input type="file" name="logo_abbr" class="form-control @error('logo_abbr') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.svg,image/*">
                            @error('logo_abbr')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if (!empty($settings['logo_abbr']))
                                <div class="mt-2 p-2 border rounded bg-light d-inline-flex align-items-center">
                                    <img src="{{ tenant_branding_url($settings['logo_abbr'], 'assets/images/logo-abbr.png') }}" alt="{{ $settings['display_name'] ?? $tenant->name }} logo" style="max-height: 36px; max-width: 80px;">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
                    <a href="{{ route('platform.tenants.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-check-circle me-2"></i>{{ $submitLabel }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
