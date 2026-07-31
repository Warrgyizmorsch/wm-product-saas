@php
    $isEdit = $mode === 'edit';
    $prefix = $isEdit ? 'edit_dept' : 'add_dept';
    $buId = $isEdit ? 'edit_dept_bu_id' : 'add_dept_business_unit_id';
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.department_name') }}" name="name" id="{{ $prefix }}_name" :required="true" placeholder="{{ __('hrms.org.department_name') }}" :errorText="$errors->first('name')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.department_code') }}" name="code" id="{{ $prefix }}_code" :required="true" placeholder="{{ __('hrms.org.department_code') }}" :errorText="$errors->first('code')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" id="{{ $prefix }}_company_id" select2-selector="default" :errorText="$errors->first('company_id')">
            <option value="">{{ __('hrms.org.select_company_required_dept') }}</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->company_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_business_unit') }}" name="business_unit_id" id="{{ $buId }}" select2-selector="default" :errorText="$errors->first('business_unit_id')">
            <option value="">{{ __('hrms.org.select_business_unit') }}</option>
            @foreach($businessUnits as $buUnit)
                <option value="{{ $buUnit->id }}" data-company-id="{{ $buUnit->company_id }}">{{ $buUnit->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_branch') }}" name="branch_id" id="{{ $prefix }}_branch_id" select2-selector="default" :errorText="$errors->first('branch_id')">
            <option value="">{{ __('hrms.org.select_branch') }}</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" data-company-id="{{ $branch->company_id }}" data-business-unit-id="{{ $branch->business_unit_id }}">{{ $branch->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.department_head') }}" name="head_employee_id" id="{{ $prefix }}_head_id" select2-selector="default" :errorText="$errors->first('head_employee_id')">
            <option value="">{{ __('hrms.org.department_head') }}</option>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" data-company-id="{{ $employee->company_id }}" data-business-unit-id="{{ $employee->business_unit_id }}" data-branch-id="{{ $employee->branch_id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
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
