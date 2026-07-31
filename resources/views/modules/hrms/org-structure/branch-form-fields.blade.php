@php
    $isEdit = $mode === 'edit';
    $prefix = $isEdit ? 'edit_branch' : 'add_branch';
    // Note: edit uses 'edit_branch_bu_id' for business_unit_id, while add uses 'add_branch_business_unit_id'
    $buId = $isEdit ? 'edit_branch_bu_id' : 'add_branch_business_unit_id';
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.branch_name') }}" name="name" id="{{ $prefix }}_name" :required="true" placeholder="{{ __('hrms.org.branch_name') }}" :errorText="$errors->first('name')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.branch_code') }}" name="code" id="{{ $prefix }}_code" :required="true" placeholder="{{ __('hrms.org.branch_code') }}" :errorText="$errors->first('code')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" id="{{ $prefix }}_company_id" select2-selector="default" :errorText="$errors->first('company_id')">
            <option value="">{{ __('hrms.org.select_company_required') }}</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->company_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_business_unit') }}" name="business_unit_id" id="{{ $buId }}" select2-selector="default" :errorText="$errors->first('business_unit_id')">
            <option value="">{{ __('hrms.org.select_business_unit') }}</option>
            @foreach($businessUnits as $buUnit)
                <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.branch_manager') }}" name="manager_employee_id" id="{{ $prefix }}_manager_id" select2-selector="default" :errorText="$errors->first('manager_employee_id')">
            <option value="">{{ __('hrms.org.select_manager') }}</option>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" data-company-id="{{ $employee->company_id }}" data-business-unit-id="{{ $employee->business_unit_id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.phone') }}" name="phone" id="{{ $prefix }}_phone" placeholder="{{ __('hrms.org.phone') }}" :errorText="$errors->first('phone')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.email') }}" name="email" id="{{ $prefix }}_email" inputType="email" placeholder="{{ __('hrms.org.email') }}" :errorText="$errors->first('email')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="{{ $prefix }}_status" select2-selector="default" :errorText="$errors->first('status')">
            <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
            <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.country') }}" name="country" id="{{ $prefix }}_country" select2-selector="country" class="geo-country" :errorText="$errors->first('country')" data-initial-value="{{ $isEdit ? '' : old('country', 'United States') }}">
            <option value="">{{ __('hrms.org.select_country') }}</option>
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.state') }}" name="state" id="{{ $prefix }}_state" select2-selector="default" class="geo-state" :errorText="$errors->first('state')">
            <option value="">{{ __('hrms.org.select_state') }}</option>
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.city') }}" name="city" id="{{ $prefix }}_city" select2-selector="default" class="geo-city" :errorText="$errors->first('city')">
            <option value="">{{ __('hrms.org.select_city') }}</option>
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.postal_code') }}" name="postal_code" id="{{ $prefix }}_postal_code" placeholder="{{ __('hrms.org.postal_code') }}" :errorText="$errors->first('postal_code')" />
    </div>
    <div class="col-12">
        <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.address') }}" name="address" id="{{ $prefix }}_address" rows="3" placeholder="{{ __('hrms.org.address') }}" :errorText="$errors->first('address')" />
    </div>
</div>
