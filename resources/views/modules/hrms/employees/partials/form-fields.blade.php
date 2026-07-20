@php
    $isEdit = $mode === 'edit';
    $prefix = $isEdit ? 'edit' : 'create';
    $fieldValue = function (string $field, $default = '') use ($isEdit) {
        return old($field, $default);
    };
@endphp

<div class="row g-4">
    <div class="col-xl-3">
        <div class="employee-photo-panel">
            <div class="employee-photo-preview" id="{{ $prefix }}_photo_preview">
                {{ $isEdit ? 'EM' : strtoupper(substr((string) old('full_name', 'Employee'), 0, 2)) }}
            </div>
            <div class="fw-semibold text-dark mb-1">{{ __('hrms.employees.frm_profile_photo') }}</div>
            <div class="text-muted fs-12 mb-3">{{ __('hrms.employees.frm_photo_help') }}</div>
            <input type="file" class="form-control" name="photo" id="{{ $prefix }}_photo" accept=".png,.jpg,.jpeg,.webp">
        </div>
    </div>
    <div class="col-xl-9">
        <div class="employee-modal-section-title">{{ __('hrms.employees.org_mapping') }}</div>
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_company') }}" name="company_id" id="{{ $prefix }}_company_id" :required="true" select2-selector="default" :errorText="$errors->first('company_id')">
                    <option value="">{{ __('hrms.employees.frm_select_company') }}</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) $fieldValue('company_id') === (string) $company->id)>
                            {{ $company->company_name }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6" id="{{ $prefix }}_business_unit_wrapper">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_business_unit') }}" name="business_unit_id" id="{{ $prefix }}_business_unit_id" select2-selector="default" :errorText="$errors->first('business_unit_id')" data-selected-value="{{ $fieldValue('business_unit_id') }}">
                    <option value="">{{ __('hrms.employees.frm_select_bu') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6" id="{{ $prefix }}_branch_wrapper">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_branch') }}" name="branch_id" id="{{ $prefix }}_branch_id" select2-selector="default" :errorText="$errors->first('branch_id')" data-selected-value="{{ $fieldValue('branch_id') }}">
                    <option value="">{{ __('hrms.employees.frm_select_branch') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_department') }}" name="department_id" id="{{ $prefix }}_department_id" :required="true" select2-selector="default" :errorText="$errors->first('department_id')" data-selected-value="{{ $fieldValue('department_id') }}">
                    <option value="">{{ __('hrms.employees.frm_select_dept') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_designation') }}" name="designation_id" id="{{ $prefix }}_designation_id" :required="true" select2-selector="default" :errorText="$errors->first('designation_id')" data-selected-value="{{ $fieldValue('designation_id') }}">
                    <option value="">{{ __('hrms.employees.frm_select_desig') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_emp_code') }}" name="employee_id" id="{{ $prefix }}_employee_id" :required="false" :value="$fieldValue('employee_id')" placeholder="{{ __('hrms.employees.frm_emp_code_placeholder') }}" :errorText="$errors->first('employee_id')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_manager') }}" name="reporting_manager_id" id="{{ $prefix }}_reporting_manager_id" select2-selector="default" :errorText="$errors->first('reporting_manager_id')">
                    <option value="">{{ __('hrms.employees.frm_select_manager') }}</option>
                    @foreach($reportingManagers as $manager)
                        @if(!$isEdit || (int) $fieldValue('id') !== (int) $manager->id)
                            <option value="{{ $manager->id }}" @selected((string) $fieldValue('reporting_manager_id') === (string) $manager->id)>
                                {{ $manager->full_name }} ({{ $manager->employee_id }})
                            </option>
                        @endif
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_work_shift') }}" name="shift_id" id="{{ $prefix }}_shift_id" select2-selector="default" :errorText="$errors->first('shift_id')" data-selected-value="{{ $fieldValue('shift_id') }}">
                    <option value="">{{ __('hrms.employees.frm_select_shift') }}</option>
                    @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) $fieldValue('shift_id') === (string) $shift->id)>
                            {{ $shift->name }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="employee-modal-section-title">{{ __('hrms.employees.master_assignments') }}</div>
        <div class="row g-3">
            <div class="col-md-6" id="{{ $prefix }}_pay_group_wrapper">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_pay_group') }}" name="pay_group_id" id="{{ $prefix }}_pay_group_id" select2-selector="default" :errorText="$errors->first('pay_group_id')" data-selected-value="{{ $fieldValue('pay_group_id') }}">
                    <option value="">{{ __('hrms.employees.frm_select_pay_group') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6" id="{{ $prefix }}_leave_plan_wrapper">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_leave_structure') }}" name="leave_plan_id" id="{{ $prefix }}_leave_plan_id" select2-selector="default" :errorText="$errors->first('leave_plan_id')" data-selected-value="{{ $fieldValue('leave_plan_id') }}">
                    <option value="">{{ __('hrms.employees.frm_select_leave_plan') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            @if($prefix === 'edit')
            <div class="col-12 d-none border rounded p-3 bg-light mt-2 mb-2" id="edit_leave_transition_options" style="border-color: #cbd5e1 !important;">
                <div class="fw-bold text-dark fs-13 mb-1"><i class="feather-shuffle me-1 text-primary"></i> Leave Plan Transition Options</div>
                <p class="text-muted fs-11 mb-3">You are changing the employee's Leave Plan. Please choose how to handle their current leave balances:</p>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fs-12 text-dark mb-1">Transition Method</label>
                        <select class="form-select form-select-sm" name="leave_transition_action" style="font-size: 13px; height: 36px; padding: 6px 12px;">
                            <option value="transfer" selected>Transfer & Carry Forward (Full Quota)</option>
                            <option value="prorate">Prorate & Reset (Pro-rata Quota)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fs-12 text-dark mb-1">Unused Leaves Action</label>
                        <select class="form-select form-select-sm" name="leave_transition_unused" style="font-size: 13px; height: 36px; padding: 6px 12px;">
                            <option value="carry" selected>Carry Forward Unused</option>
                            <option value="lapse">Lapse Unused</option>
                        </select>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="employee-modal-section-title">{{ __('hrms.employees.basic_details') }}</div>
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_full_name') }}" name="full_name" id="{{ $prefix }}_full_name" :required="true" :value="$fieldValue('full_name')" placeholder="{{ __('hrms.employees.frm_full_name_placeholder') }}" :errorText="$errors->first('full_name')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_nick_name') }}" name="nick_name" id="{{ $prefix }}_nick_name" :value="$fieldValue('nick_name')" placeholder="{{ __('hrms.employees.frm_nick_name_placeholder') }}" :errorText="$errors->first('nick_name')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_job_title') }}" name="job_title" id="{{ $prefix }}_job_title" :required="true" :value="$fieldValue('job_title')" placeholder="{{ __('hrms.employees.frm_job_title_placeholder') }}" :errorText="$errors->first('job_title')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_role') }}" name="role" id="{{ $prefix }}_role" :value="$fieldValue('role')" placeholder="{{ __('hrms.employees.frm_role_placeholder') }}" :errorText="$errors->first('role')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_stage') }}" name="employee_stage" id="{{ $prefix }}_employee_stage" select2-selector="default" :errorText="$errors->first('employee_stage')">
                    <option value="">{{ __('hrms.employees.frm_select_stage') }}</option>
                    @foreach($employeeStages as $stage)
                        <option value="{{ $stage }}" @selected($fieldValue('employee_stage') === $stage)>{{ $stage }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_emp_type') }}" name="employment_type" id="{{ $prefix }}_employment_type" select2-selector="default" :errorText="$errors->first('employment_type')">
                    <option value="">{{ __('hrms.employees.frm_select_type') }}</option>
                    @foreach($employmentTypes as $type)
                        <option value="{{ $type }}" @selected($fieldValue('employment_type') === $type)>{{ $type }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_doj') }}" name="date_of_joining" id="{{ $prefix }}_date_of_joining" inputType="date" :required="true" :value="$fieldValue('date_of_joining', '')" :errorText="$errors->first('date_of_joining')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_dob') }}" name="date_of_birth" id="{{ $prefix }}_date_of_birth" inputType="date" :value="$fieldValue('date_of_birth', '')" :errorText="$errors->first('date_of_birth')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_probation_end') }}" name="probation_end_date" id="{{ $prefix }}_probation_end_date" inputType="date" :value="$fieldValue('probation_end_date', '')" :errorText="$errors->first('probation_end_date')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_confirmation') }}" name="confirmation_date" id="{{ $prefix }}_confirmation_date" inputType="date" :value="$fieldValue('confirmation_date', '')" :errorText="$errors->first('confirmation_date')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_gender') }}" name="gender" id="{{ $prefix }}_gender" :required="true" select2-selector="default" :errorText="$errors->first('gender')">
                    <option value="">{{ __('hrms.employees.frm_select_gender') }}</option>
                    @foreach($genders as $gender)
                        <option value="{{ $gender }}" @selected($fieldValue('gender') === $gender)>{{ $gender }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_marital_status') }}" name="marital_status" id="{{ $prefix }}_marital_status" select2-selector="default" :errorText="$errors->first('marital_status')">
                    <option value="">{{ __('hrms.employees.frm_select_status') }}</option>
                    @foreach($maritalStatuses as $status)
                        <option value="{{ $status }}" @selected($fieldValue('marital_status') === $status)>{{ $status }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4" id="{{ $prefix }}_blood_group_wrapper">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_blood_group') }}" name="blood_group" id="{{ $prefix }}_blood_group" select2-selector="default" :errorText="$errors->first('blood_group')">
                    <option value="">{{ __('hrms.employees.frm_select_blood') }}</option>
                    @foreach($bloodGroups as $bloodGroup)
                        <option value="{{ $bloodGroup }}" @selected($fieldValue('blood_group') === $bloodGroup)>{{ $bloodGroup }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_diet_pref') }}" name="diet_preference" id="{{ $prefix }}_diet_preference" select2-selector="default" :errorText="$errors->first('diet_preference')">
                    <option value="">{{ __('hrms.employees.frm_select_diet') }}</option>
                    @foreach($dietPreferences as $preference)
                        <option value="{{ $preference }}" @selected($fieldValue('diet_preference') === $preference)>{{ $preference }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_location') }}" name="office" id="{{ $prefix }}_office" :value="$fieldValue('office')" placeholder="{{ __('hrms.employees.frm_location_placeholder') }}" :errorText="$errors->first('office')" />
            </div>
        </div>

        <div class="employee-modal-section-title">{{ __('hrms.employees.contact_compliance') }}</div>
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_mobile') }}" name="personal_mobile_number" id="{{ $prefix }}_personal_mobile_number" :value="$fieldValue('personal_mobile_number')" placeholder="{{ __('hrms.employees.frm_mobile_placeholder') }}" :errorText="$errors->first('personal_mobile_number')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_email') }}" name="personal_email" id="{{ $prefix }}_personal_email" inputType="email" :value="$fieldValue('personal_email')" placeholder="{{ __('hrms.employees.frm_email_placeholder') }}" :errorText="$errors->first('personal_email')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_office_email') }}" name="office_email" id="{{ $prefix }}_office_email" inputType="email" :value="$fieldValue('office_email')" placeholder="{{ __('hrms.employees.frm_office_email_placeholder') }}" :errorText="$errors->first('office_email')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_home_phone') }}" name="home_phone" id="{{ $prefix }}_home_phone" :value="$fieldValue('home_phone')" placeholder="{{ __('hrms.employees.frm_home_phone_placeholder') }}" :errorText="$errors->first('home_phone')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_city') }}" name="city" id="{{ $prefix }}_city" :value="$fieldValue('city')" placeholder="{{ __('hrms.employees.frm_city_placeholder') }}" :errorText="$errors->first('city')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_postal') }}" name="postal_code" id="{{ $prefix }}_postal_code" :value="$fieldValue('postal_code')" placeholder="{{ __('hrms.employees.frm_postal_placeholder') }}" :errorText="$errors->first('postal_code')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_aadhaar') }}" name="aadhaar_card_number" id="{{ $prefix }}_aadhaar_card_number" :value="$fieldValue('aadhaar_card_number')" placeholder="{{ __('hrms.employees.frm_aadhaar_placeholder') }}" :errorText="$errors->first('aadhaar_card_number')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_pan') }}" name="pan_card_number" id="{{ $prefix }}_pan_card_number" :value="$fieldValue('pan_card_number')" placeholder="{{ __('hrms.employees.frm_pan_placeholder') }}" :errorText="$errors->first('pan_card_number')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_emergency_name') }}" name="emergency_contact_name" id="{{ $prefix }}_emergency_contact_name" :value="$fieldValue('emergency_contact_name')" placeholder="{{ __('hrms.employees.frm_emergency_name_placeholder') }}" :errorText="$errors->first('emergency_contact_name')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_emergency_number') }}" name="emergency_contact_number" id="{{ $prefix }}_emergency_contact_number" :value="$fieldValue('emergency_contact_number')" placeholder="{{ __('hrms.employees.frm_emergency_number_placeholder') }}" :errorText="$errors->first('emergency_contact_number')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_emergency_relation') }}" name="emergency_contact_relation" id="{{ $prefix }}_emergency_contact_relation" :value="$fieldValue('emergency_contact_relation')" placeholder="{{ __('hrms.employees.frm_emergency_relation_placeholder') }}" :errorText="$errors->first('emergency_contact_relation')" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.frm_present_address') }}" name="present_address" id="{{ $prefix }}_present_address" rows="3" placeholder="{{ __('hrms.employees.frm_present_address_placeholder') }}" :errorText="$errors->first('present_address')">{{ $fieldValue('present_address') }}</x-ui.odoo-form-ui>
            </div>
            <div class="col-12 py-1">
                <div class="odoo-form-group">
                    <div class="odoo-form-label"></div>
                    <div class="flex-grow-1">
                        <div class="form-check m-0 d-flex align-items-center">
                            <input class="form-check-input" type="checkbox" id="{{ $prefix }}_same_as_present">
                            <label class="form-check-label fw-bold text-dark ms-2" for="{{ $prefix }}_same_as_present">{{ __('hrms.employees.frm_same_address') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.frm_perm_address') }}" name="permanent_address" id="{{ $prefix }}_permanent_address" rows="3" placeholder="{{ __('hrms.employees.frm_perm_address_placeholder') }}" :errorText="$errors->first('permanent_address')">{{ $fieldValue('permanent_address') }}</x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="employee-modal-section-title">{{ __('hrms.employees.bank_details') }}</div>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_bank_name') }}" name="bank_name" id="{{ $prefix }}_bank_name" :value="$fieldValue('bank_name')" placeholder="{{ __('hrms.employees.frm_bank_name_placeholder') }}" :errorText="$errors->first('bank_name')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_acc_number') }}" name="account_number" id="{{ $prefix }}_account_number" :value="$fieldValue('account_number')" placeholder="{{ __('hrms.employees.frm_acc_number_placeholder') }}" :errorText="$errors->first('account_number')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_ifsc') }}" name="ifsc_code" id="{{ $prefix }}_ifsc_code" :value="$fieldValue('ifsc_code')" placeholder="{{ __('hrms.employees.frm_ifsc_placeholder') }}" :errorText="$errors->first('ifsc_code')" />
            </div>
        </div>

        <div class="employee-modal-section-title">{{ __('hrms.employees.professional_details') }}</div>
        <div class="row g-3">
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_exp') }}" name="experience" id="{{ $prefix }}_experience" inputType="number" step="0.01" :value="$fieldValue('experience', '0')" placeholder="0.00" :errorText="$errors->first('experience')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_salary') }}" name="current_salary" id="{{ $prefix }}_current_salary" inputType="number" step="0.01" :value="$fieldValue('current_salary', '0')" placeholder="0.00" :errorText="$errors->first('current_salary')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_status') }}" name="status" id="{{ $prefix }}_status" :required="true" select2-selector="default" :errorText="$errors->first('status')">
                    <option value="1" @selected((string) $fieldValue('status', '1') === '1')>{{ __('hrms.employees.frm_status_active') }}</option>
                    <option value="0" @selected((string) $fieldValue('status') === '0')>{{ __('hrms.employees.frm_status_inactive') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_qualification') }}" name="qualification" id="{{ $prefix }}_qualification" :value="$fieldValue('qualification')" placeholder="{{ __('hrms.employees.frm_qualification_placeholder') }}" :errorText="$errors->first('qualification')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_source_hire') }}" name="source_of_hire" id="{{ $prefix }}_source_of_hire" :value="$fieldValue('source_of_hire')" placeholder="{{ __('hrms.employees.frm_source_hire_placeholder') }}" :errorText="$errors->first('source_of_hire')" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.frm_skill_set') }}" name="skill_set" id="{{ $prefix }}_skill_set" rows="3" placeholder="{{ __('hrms.employees.frm_skill_set_placeholder') }}" :errorText="$errors->first('skill_set')">{{ $fieldValue('skill_set') }}</x-ui.odoo-form-ui>
            </div>
        </div>
    </div>
</div>
