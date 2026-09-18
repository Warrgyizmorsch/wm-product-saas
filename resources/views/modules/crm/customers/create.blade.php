@extends('layouts.duralux')

@section('title', __('crm.create_customer') . ' | SaaS ERP')
@section('page-title', __('crm.create_customer'))
@section('breadcrumb', __('crm.customers_sidebar') . ' / ' . __('crm.create_customer'))

@section('page-actions')
    <a href="{{ route('crm.customers.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>{{ __('crm.back_to_customers') }}
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-xxl-9 col-xl-10 mx-auto">
            <form action="{{ route('crm.customers.store') }}" method="POST">
                @csrf

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="feather-user-plus me-2 text-primary"></i>{{ __('crm.customer_details') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <!-- Left Column: Primary Details -->
                            <div class="col-md-6 border-end-md">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>{{ __('crm.primary_information') }}</h6>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-dark">{{ __('crm.customer_name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Acme Corp or Manish Patidar" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-dark">{{ __('crm.gstin_tax_id') }}</label>
                                    <input type="text" name="gstin" class="form-control @error('gstin') is-invalid @enderror" value="{{ old('gstin') }}" placeholder="e.g. 22AAAAA0000A1Z5">
                                    @error('gstin')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">{{ __('crm.email') }} <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="e.g. contact@acme.com" required>
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">{{ __('crm.phone') }}</label>
                                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="e.g. +91 9876543210">
                                        @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-dark">{{ __('crm.status') }} <span class="text-danger">*</span></label>
                                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                        <option value="active" @selected(old('status', 'active') === 'active')>{{ __('crm.active_account') }}</option>
                                        <option value="inactive" @selected(old('status') === 'inactive')>{{ __('crm.inactive_account') }}</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Right Column: Address Details -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold text-primary mb-0"><i class="feather-map-pin me-2"></i>{{ __('crm.address_information') }}</h6>
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fs-12 text-primary" onclick="var b = document.querySelector('[name=billing_address]'); var s = document.querySelector('[name=shipping_address]'); if(b && s) s.value = b.value;">
                                        <i class="feather-copy me-1"></i>{{ __('crm.copy_billing_shipping') }}
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-dark">{{ __('crm.billing_address') }}</label>
                                    <textarea name="billing_address" rows="3" class="form-control @error('billing_address') is-invalid @enderror" placeholder="Enter full billing address details...">{{ old('billing_address') }}</textarea>
                                    @error('billing_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-dark">{{ __('crm.shipping_address') }}</label>
                                    <textarea name="shipping_address" rows="3" class="form-control @error('shipping_address') is-invalid @enderror" placeholder="Enter full shipping address details...">{{ old('shipping_address') }}</textarea>
                                    @error('shipping_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent d-flex justify-content-end gap-2 py-3">
                        <a href="{{ route('crm.customers.index') }}" class="btn btn-light">{{ __('crm.discard') }}</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="feather-check-circle me-2"></i>{{ __('crm.save_customer') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

