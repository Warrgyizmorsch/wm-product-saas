@extends('layouts.duralux')

@section('title', __('crm.edit_account') . ' | SaaS ERP')
@section('page-title', __('crm.edit_account') . ': ' . $account->name)
@section('breadcrumb', __('crm.edit_account'))

@section('page-actions')
    <a href="{{ route('crm.accounts.show', $account) }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.back_to_deal') }}">
        <i class="feather-arrow-left fs-16"></i>
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 p-md-5 rounded-3 border shadow-sm">
        <form action="{{ route('crm.accounts.update', $account) }}" method="POST" id="accountForm" class="odoo-sheet">
            @csrf
            @method('PUT')

            {{-- Header Top Bar (Matches Create Form 100%) --}}
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3 gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3 fs-18">
                        <i class="feather-briefcase"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-0">{{ __('crm.edit_account') }}: {{ $account->name }}</h4>
                        <span class="text-muted fs-12">{{ $account->account_number ? '#' . $account->account_number . ' · ' : '' }}Update company master details, primary contact and location</span>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4 fs-13 text-dark">
                {{-- Left Column: Company Master Details --}}
                <div class="col-lg-6 border-end pe-lg-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-briefcase me-2"></i>{{ __('crm.company_master_details') }}</h6>
                    
                    <x-ui.odoo-form-ui type="input" :label="__('crm.company_name')" name="name" :value="old('name', $account->name)" :required="true" placeholder="e.g. ABC Builders Pvt Ltd" :errorText="$errors->first('name')" />

                    <x-ui.odoo-form-ui type="input" :label="__('crm.gstin')" name="gstin" :value="old('gstin', $account->gstin)" placeholder="e.g. 27AAAAA0000A1Z5" :errorText="$errors->first('gstin')" />

                    <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.contact_email')" name="email" :value="old('email', $account->email)" :required="true" placeholder="info@company.com" :errorText="$errors->first('email')" />

                    <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('crm.contact_phone')" name="phone" id="phone_input" :value="old('phone', $account->phone)" placeholder="02240001122" oninput="this.value = this.value.replace(/[^0-9]/g, '')" :errorText="$errors->first('phone')" />

                    <x-ui.odoo-form-ui type="input" label="Website URL" name="website" :value="old('website', $account->website)" placeholder="https://company.com" :errorText="$errors->first('website')" />

                    <x-ui.odoo-form-ui type="input" label="Industry Type" name="industry_type" :value="old('industry_type', $account->industry_type)" placeholder="e.g. Construction / Infrastructure" :errorText="$errors->first('industry_type')" />

                    <x-ui.odoo-form-ui type="input" inputType="number" :label="__('crm.credit_limit') . ' (' . active_currency_symbol() . ')'" name="credit_limit" :value="old('credit_limit', $account->credit_limit ? round(convert_from_base((float)$account->credit_limit), 2) : '0.00')" step="0.01" placeholder="0.00" :errorText="$errors->first('credit_limit')" />

                    <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="Opening Balance ({{ active_currency_symbol() }})" name="opening_balance" id="opening_balance" :value="old('opening_balance', $account->customer?->opening_balance ? number_format(convert_from_base((float)$account->customer->opening_balance), 2, '.', '') : '0.00')" placeholder="0.00" :error-text="$errors->first('opening_balance')" />

                    <x-ui.odoo-form-ui type="select" :label="__('crm.account_manager_owner')" name="owner_id" :errorText="$errors->first('owner_id')">
                        <option value="">Select Account Manager...</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('owner_id', $account->owner_id) == $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="select" label="Account Status" name="status" :errorText="$errors->first('status')">
                        <option value="active" {{ old('status', $account->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $account->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </x-ui.odoo-form-ui>
                </div>

                {{-- Right Column: Primary Contact & Address Details --}}
                <div class="col-lg-6 ps-lg-4">
                    @php
                        $contact = $account->primaryContact;
                    @endphp
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-2"></i>{{ __('crm.primary_contact_person') }}</h6>
                    
                    <x-ui.odoo-form-ui type="input" :label="__('crm.primary_contact')" name="contact_name" :value="old('contact_name', $contact?->name)" placeholder="e.g. Rahul Sharma" :errorText="$errors->first('contact_name')" />

                    <x-ui.odoo-form-ui type="input" label="Designation / Title" name="designation" :value="old('designation', $contact?->designation)" placeholder="e.g. Purchase Head" :errorText="$errors->first('designation')" />

                    <x-ui.odoo-form-ui type="select" :label="__('crm.buying_center_role')" name="role" :errorText="$errors->first('role')">
                        @php $selectedRole = old('role', $contact?->role ?: 'Purchase Decision Maker'); @endphp
                        <option value="Purchase Decision Maker" @selected($selectedRole === 'Purchase Decision Maker')>Purchase Decision Maker</option>
                        <option value="Technical Evaluator" @selected($selectedRole === 'Technical Evaluator')>Technical Evaluator</option>
                        <option value="Finance" @selected($selectedRole === 'Finance')>Finance / Accounts</option>
                        <option value="Influencer" @selected($selectedRole === 'Influencer')>Influencer</option>
                        <option value="End User" @selected($selectedRole === 'End User')>End User</option>
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.contact_email')" name="contact_email" :value="old('contact_email', $contact?->email)" placeholder="rahul@company.com" :errorText="$errors->first('contact_email')" />

                    <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('crm.contact_phone')" name="contact_phone" id="contact_phone_input" :value="old('contact_phone', $contact?->phone ?: $contact?->mobile)" placeholder="9876543210" oninput="this.value = this.value.replace(/[^0-9]/g, '')" :errorText="$errors->first('contact_phone')" />

                    <div class="border-top pt-4 mt-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>{{ __('crm.address_location') }}</h6>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('crm.street_address')" name="street" :value="old('street', $account->street)" placeholder="Building, Street, Landmark" :errorText="$errors->first('street')" />

                        <x-ui.odoo-form-ui type="input" label="City" name="city" :value="old('city', $account->city)" placeholder="Mumbai" :errorText="$errors->first('city')" />

                        <x-ui.odoo-form-ui type="input" label="State" name="state" :value="old('state', $account->state)" placeholder="Maharashtra" :errorText="$errors->first('state')" />

                        <x-ui.odoo-form-ui type="input" label="Country" name="country" :value="old('country', $account->country ?: 'India')" placeholder="India" :errorText="$errors->first('country')" />

                        <x-ui.odoo-form-ui type="input" inputType="text" label="Zip / Postal Code" name="zip_code" id="zip_code_input" :value="old('zip_code', $account->zip_code)" placeholder="400001" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="10" :errorText="$errors->first('zip_code')" />
                    </div>
                </div>
            </div>

            {{-- Bottom Action Bar --}}
            <div class="d-flex gap-2 justify-content-end border-top pt-3">
                <a href="{{ route('crm.accounts.show', $account) }}" class="btn btn-light border px-4 py-2 fs-13">{{ __('crm.cancel_caps') }}</a>
                <button type="submit" form="accountForm" class="btn btn-primary px-4 py-2 fs-13 fw-bold shadow-sm">
                    <i class="feather-check-circle me-1.5"></i>{{ __('crm.update_account') }}
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const numericInputs = document.querySelectorAll('input[name="phone"], input[name="contact_phone"], input[name="zip_code"]');
        numericInputs.forEach(function (inp) {
            inp.addEventListener('keypress', function (e) {
                // Allow control keys like backspace, tab, enter, arrow keys
                if (e.isComposing || e.ctrlKey || e.altKey || e.metaKey) return;
                if (!/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'Tab') {
                    e.preventDefault();
                }
            });
            inp.addEventListener('paste', function (e) {
                setTimeout(function () {
                    inp.value = inp.value.replace(/[^0-9]/g, '');
                }, 10);
            });
        });
    });
</script>
@endpush
