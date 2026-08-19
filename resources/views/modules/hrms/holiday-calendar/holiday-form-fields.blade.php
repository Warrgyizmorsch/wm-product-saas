@php
    $isEdit = $mode === 'edit';
    $prefix = $isEdit ? 'edit_holiday' : 'add_holiday';
    $buId = $isEdit ? 'edit_holiday_bu_id' : 'add_holiday_business_unit_id';
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.holiday.holiday_name') }}" name="name" id="{{ $prefix }}_name" :required="true" placeholder="{{ __('hrms.holiday.holiday_name') }}" :errorText="$errors->first('name')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.holiday.holiday_date') }}" name="holiday_date" id="{{ $prefix }}_holiday_date" inputType="date" :required="true" :errorText="$errors->first('holiday_date')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.holiday.scope_company') }}" name="company_id" id="{{ $prefix }}_company_id" select2-selector="default" :errorText="$errors->first('company_id')">
            <option value="">{{ __('hrms.holiday.all_companies') }}</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->company_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.holiday.scope_bu') }}" name="business_unit_id" id="{{ $buId }}" select2-selector="default" :errorText="$errors->first('business_unit_id')">
            <option value="">{{ __('hrms.holiday.all_business_units') }}</option>
            @foreach($businessUnits as $buUnit)
                <option value="{{ $buUnit->id }}" data-company-id="{{ $buUnit->company_id }}">{{ $buUnit->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.holiday.scope_branch') }}" name="branch_id" id="{{ $prefix }}_branch_id" select2-selector="default" :errorText="$errors->first('branch_id')">
            <option value="">{{ __('hrms.holiday.all_branches') }}</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" data-company-id="{{ $branch->company_id }}" data-business-unit-id="{{ $branch->business_unit_id }}">{{ $branch->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.holiday.status') }}" name="status" id="{{ $prefix }}_status" select2-selector="default" :errorText="$errors->first('status')">
            <option value="1">{{ __('hrms.holiday.active') }}</option>
            <option value="0">{{ __('hrms.holiday.inactive') }}</option>
        </x-ui.odoo-form-ui>
    </div>
    <div class="col-12">
        <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" id="{{ $prefix }}_description" rows="3" placeholder="{{ __('hrms.org.description') }}" :errorText="$errors->first('description')" />
    </div>
</div>
