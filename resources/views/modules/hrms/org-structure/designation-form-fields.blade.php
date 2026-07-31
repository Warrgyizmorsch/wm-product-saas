@php
    $isEdit = $mode === 'edit';
    $prefix = $isEdit ? 'edit_desig' : 'add_desig';
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.designation_name') }}" name="name" id="{{ $prefix }}_name" :required="true" placeholder="{{ __('hrms.org.designation_name') }}" :errorText="$errors->first('name')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.grade_level') }}" name="level" id="{{ $prefix }}_level" placeholder="{{ __('hrms.org.grade_level') }}" :errorText="$errors->first('level')" />
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_department') }}" name="department_id" id="{{ $prefix }}_dept_id" :required="true" select2-selector="default" :errorText="$errors->first('department_id')">
            <option value="">{{ __('hrms.org.select_department') }}</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
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
