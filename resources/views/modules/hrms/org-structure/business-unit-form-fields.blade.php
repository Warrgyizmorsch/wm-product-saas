@php
    $isEdit = $mode === 'edit';
    $prefix = $isEdit ? 'edit_bu' : 'add_bu';
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.business_unit_name') }}" name="name" id="{{ $prefix }}_name" :required="true" placeholder="{{ __('hrms.org.business_unit_name') }}" :errorText="$errors->first('name')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.business_unit_code') }}" name="code" id="{{ $prefix }}_code" :required="true" placeholder="{{ __('hrms.org.business_unit_code') }}" :errorText="$errors->first('code')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" id="{{ $prefix }}_company_id" :required="true" select2-selector="default" :errorText="$errors->first('company_id')">
            @if(!$isEdit)
                <option value="">{{ __('hrms.org.parent_company') }}</option>
            @endif
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->company_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.unit_head') }}" name="head_employee_id" id="{{ $prefix }}_head_employee_id" select2-selector="default" :errorText="$errors->first('head_employee_id')">
            <option value="">{{ __('hrms.org.unit_head') }}</option>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" data-company-id="{{ $employee->company_id }}" @if($isEdit) data-business-unit-id="{{ $employee->business_unit_id }}" @endif>{{ $employee->first_name }} {{ $employee->last_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="{{ $prefix }}_status" select2-selector="default" :errorText="$errors->first('status')">
            <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
            <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-12">
        <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" id="{{ $prefix }}_description" rows="3" placeholder="{{ __('hrms.org.description') }}" :errorText="$errors->first('description')" />
    </div>
</div>
