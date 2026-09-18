@extends('layouts.duralux')

@section('title', __('crm.edit_account') . ' | SaaS ERP')
@section('page-title', __('crm.edit_account') . ': ' . $account->name)
@section('breadcrumb', __('crm.edit_account'))

@section('page-actions')
    <a href="{{ route('crm.accounts.show', $account) }}" class="btn btn-light">
        <i class="feather-arrow-left me-1"></i>{{ __('crm.back_to_deal') }}
    </a>
@endsection

@section('content')
    <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4">
        <form action="{{ route('crm.accounts.update', $account) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Company Master Details -->
            <h6 class="fw-bold text-primary mb-3"><i class="feather-briefcase me-2"></i>{{ __('crm.company_master_details') }}</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" :label="__('crm.company_name')" name="name" :value="old('name', $account->name)" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" :label="__('crm.gstin')" name="gstin" :value="old('gstin', $account->gstin)" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.contact_email')" name="email" :value="old('email', $account->email)" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" :label="__('crm.contact_phone')" name="phone" :value="old('phone', $account->phone)" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="Website URL" name="website" :value="old('website', $account->website)" />
                </div>
                <div class="col-md-3">
                    <x-ui.odoo-form-ui type="input" label="Industry Type" name="industry_type" :value="old('industry_type', $account->industry_type)" />
                </div>
                <div class="col-md-3">
                    <x-ui.odoo-form-ui type="input" inputType="number" :label="__('crm.credit_limit') . ' (' . active_currency_symbol() . ')'" name="credit_limit" :value="old('credit_limit', $account->credit_limit)" step="0.01" />
                </div>
                <div class="col-md-3">
                    <x-ui.odoo-form-ui type="select" :label="__('crm.account_manager_owner')" name="owner_id">
                        <option value="">Select Account Manager...</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('owner_id', $account->owner_id) == $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-3">
                    <x-ui.odoo-form-ui type="select" label="Account Status" name="status">
                        <option value="active" {{ $account->status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $account->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Address Details -->
            <div class="border-top pt-4 mb-4">
                <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>{{ __('crm.address_location') }}</h6>
                <div class="row g-3">
                    <div class="col-md-12">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.street_address')" name="street" :value="old('street', $account->street)" />
                    </div>
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" label="City" name="city" :value="old('city', $account->city)" />
                    </div>
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" label="State" name="state" :value="old('state', $account->state)" />
                    </div>
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" label="Country" name="country" :value="old('country', $account->country)" />
                    </div>
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" label="Zip / Postal Code" name="zip_code" :value="old('zip_code', $account->zip_code)" />
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end border-top pt-4">
                <a href="{{ route('crm.accounts.show', $account) }}" class="btn btn-light">{{ __('crm.cancel') }}</a>
                <button type="submit" class="btn btn-primary px-4"><i class="feather-check me-1"></i>{{ __('crm.update_account') }}</button>
            </div>
        </form>
    </div>
@endsection
