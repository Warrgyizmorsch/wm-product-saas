@php
    $isEdit = $mode === 'edit';
    $prefix = $isEdit ? 'edit' : 'add';
@endphp

<div class="row g-4 align-items-stretch mb-2">
    <div class="col-lg-3">
        <div class="hrms-logo-panel">
            <label class="form-label fw-semibold mb-3">{{ __('hrms.org.tbl_logo') }}:</label>
            <div class="d-flex gap-3 align-items-center">
                <div class="wd-100 ht-100 position-relative overflow-hidden border border-gray-200 rounded bg-white">
                    <img src="{{ $isEdit ? '' : asset('assets/images/avatar/1.png') }}" class="{{ $prefix }}-upload-pic img-fluid rounded h-100 w-100" id="{{ $prefix }}_logo_preview" alt="">
                    <div class="position-absolute start-50 top-50 end-0 translate-middle h-100 w-100 hstack align-items-center justify-content-center c-pointer {{ $prefix }}-upload-button upload-button" style="background: rgba(0,0,0,0.3); color: white;">
                        <i class="feather feather-camera" aria-hidden="true"></i>
                    </div>
                    <input class="{{ $prefix }}-file-upload" type="file" name="logo" accept="image/*" style="display: none;">
                </div>
                <div class="d-flex flex-column gap-1">
                    <div class="fs-11 text-gray-500">{{ $isEdit ? __('hrms.org.upload_new_logo') : __('hrms.org.avatar_size_150') }}</div>
                    @if(!$isEdit)
                        <div class="fs-11 text-gray-500">{{ __('hrms.org.max_upload_2mb') }}</div>
                        <div class="fs-11 text-gray-500">{{ __('hrms.org.allowed_formats') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="hrms-section-title">{{ __('hrms.org.entity_identity') }}</div>
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.company_name') }}" name="company_name" id="{{ $prefix }}_company_name" :required="true" :errorText="$errors->first('company_name')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.legal_name') }}" name="legal_name" id="{{ $prefix }}_legal_name" :required="true" :errorText="$errors->first('legal_name')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.currency') }}" name="currency" id="{{ $prefix }}_currency" :required="true" placeholder="e.g. INR, USD" :errorText="$errors->first('currency')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.timezone') }}" name="time_zone" id="{{ $prefix }}_timezone" select2-selector="tzone" class="geo-timezone" :required="true" :errorText="$errors->first('time_zone')" data-initial-value="{{ $isEdit ? '' : old('time_zone', 'Asia/Kolkata') }}">
                    <option value="">{{ __('hrms.org.select_timezone') }}</option>
                </x-ui.odoo-form-ui>
            </div>
        </div>
    </div>
</div>

<div class="hrms-section-title">{{ __('hrms.org.registration_details') }}</div>
<div class="row g-3">
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.gst_number') }}" name="gst_number" id="{{ $prefix }}_gst_number" placeholder="{{ __('hrms.org.gst_number') }}" :errorText="$errors->first('gst_number')" />
    </div>
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.pan_number') }}" name="pan_number" id="{{ $prefix }}_pan_number" placeholder="{{ __('hrms.org.pan_number') }}" :errorText="$errors->first('pan_number')" />
    </div>
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.cin_number') }}" name="cin_number" id="{{ $prefix }}_cin_number" placeholder="{{ __('hrms.org.cin_number') }}" :errorText="$errors->first('cin_number')" />
    </div>
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.registration_number') }}" name="registration_number" id="{{ $prefix }}_registration_number" placeholder="{{ __('hrms.org.registration_number') }}" :errorText="$errors->first('registration_number')" />
    </div>
</div>

<div class="hrms-section-title">{{ __('hrms.org.contact_and_status') }}</div>
<div class="row g-3">
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.email') }}" name="email" id="{{ $prefix }}_email" inputType="email" :required="true" placeholder="{{ __('hrms.org.email') }}" :errorText="$errors->first('email')" />
    </div>
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.phone') }}" name="phone" id="{{ $prefix }}_phone" placeholder="{{ __('hrms.org.phone') }}" :errorText="$errors->first('phone')" />
    </div>
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.website') }}" name="website" id="{{ $prefix }}_website" placeholder="{{ __('hrms.org.website') }}" :errorText="$errors->first('website')" />
    </div>
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="{{ $prefix }}_status" select2-selector="default" :errorText="$errors->first('status')">
            <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
            <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
        </x-ui.odoo-form-ui>
    </div>
</div>

<div class="hrms-section-title">{{ __('hrms.org.location') }}</div>
<div class="row g-3">
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.country') }}" name="country" id="{{ $prefix }}_country" select2-selector="country" class="geo-country" :errorText="$errors->first('country')" data-initial-value="{{ $isEdit ? '' : old('country', 'United States') }}">
            <option value="">{{ __('hrms.org.select_country') }}</option>
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.state') }}" name="state" id="{{ $prefix }}_state" select2-selector="default" class="geo-state" :errorText="$errors->first('state')">
            <option value="">{{ __('hrms.org.select_state') }}</option>
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.city') }}" name="city" id="{{ $prefix }}_city" select2-selector="default" class="geo-city" :errorText="$errors->first('city')">
            <option value="">{{ __('hrms.org.select_city') }}</option>
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-lg-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.postal_code') }}" name="postal_code" id="{{ $prefix }}_postal_code" placeholder="{{ __('hrms.org.postal_code') }}" :errorText="$errors->first('postal_code')" />
    </div>
    <div class="col-12">
        <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.address') }}" name="address" id="{{ $prefix }}_address" rows="3" placeholder="{{ __('hrms.org.address') }}" :errorText="$errors->first('address')" />
    </div>
</div>
