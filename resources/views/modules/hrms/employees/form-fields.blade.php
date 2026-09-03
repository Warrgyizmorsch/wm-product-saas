@php
    $isEdit = $mode === 'edit';
    $prefix = $isEdit ? 'edit' : 'create';
    $fieldValue = function (string $field, $default = '') use ($isEdit) {
        return old($field, $default);
    };
    // Prepared for future role-based lock: when $isEmployeeSelfService is true, HR fields become readonly/disabled
    $isEmployeeSelfService = $isEmployeeSelfService ?? false;
@endphp
@once
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
@endonce

<div class="employee-form-container">
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 1: OFFICIAL & COMPANY MANAGED FIELDS                            -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <div class="card border rounded-3 mb-4 shadow-none bg-white">
        <div class="card-header bg-light border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="d-flex align-items-center justify-content-center rounded-circle bg-soft-primary text-primary" style="width: 32px; height: 32px; min-width: 32px;">
                    <i class="feather-shield fs-14"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0 fs-13">1. Official & Company Configuration</h6>
                    <span class="text-muted fs-11">Organizational hierarchy, job mapping, system access, and official employment terms.</span>
                </div>
            </div>
            <x-ui.badge soft variant="primary" class="border fs-11 px-2.5 py-1 fw-bold">
                <i class="feather-lock me-1"></i>Company Controlled
            </x-ui.badge>
        </div>
        <div class="card-body p-4">
            <!-- 1.1 Organizational Mapping -->
            <div class="employee-modal-section-title mt-0">{{ __('hrms.employees.org_mapping') }}</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_company') }}" name="company_id" id="{{ $prefix }}_company_id" :required="true" select2-selector="default" :errorText="$errors->first('company_id')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_company') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) $fieldValue('company_id') === (string) $company->id)>
                                {{ $company->company_name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6" id="{{ $prefix }}_business_unit_wrapper">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_business_unit') }}" name="business_unit_id" id="{{ $prefix }}_business_unit_id" select2-selector="default" :errorText="$errors->first('business_unit_id')" data-selected-value="{{ $fieldValue('business_unit_id') }}" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_bu') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6" id="{{ $prefix }}_branch_wrapper">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_branch') }}" name="branch_id" id="{{ $prefix }}_branch_id" select2-selector="default" :errorText="$errors->first('branch_id')" data-selected-value="{{ $fieldValue('branch_id') }}" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_branch') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_department') }}" name="department_id" id="{{ $prefix }}_department_id" :required="true" select2-selector="default" :errorText="$errors->first('department_id')" data-selected-value="{{ $fieldValue('department_id') }}" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_dept') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_designation') }}" name="designation_id" id="{{ $prefix }}_designation_id" :required="true" select2-selector="default" :errorText="$errors->first('designation_id')" data-selected-value="{{ $fieldValue('designation_id') }}" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_desig') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_emp_code') }}" name="employee_id" id="{{ $prefix }}_employee_id" :required="false" :value="$fieldValue('employee_id')" placeholder="{{ __('hrms.employees.frm_emp_code_placeholder') }}" :errorText="$errors->first('employee_id')" :disabled="!$isEdit || $isEmployeeSelfService" data-field-group="hr_admin" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_manager') }}" name="reporting_manager_id" id="{{ $prefix }}_reporting_manager_id" select2-selector="default" :errorText="$errors->first('reporting_manager_id')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
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
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_work_shift') }}" name="shift_id" id="{{ $prefix }}_shift_id" select2-selector="default" :errorText="$errors->first('shift_id')" data-selected-value="{{ $fieldValue('shift_id') }}" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_shift') }}</option>
                        @foreach($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected((string) $fieldValue('shift_id') === (string) $shift->id)>
                                {{ $shift->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- 1.2 Master & Policy Assignments -->
            <div class="employee-modal-section-title">{{ __('hrms.employees.master_assignments') }}</div>
            <div class="row g-3 mb-4">
                <div class="col-md-6" id="{{ $prefix }}_pay_group_wrapper">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_pay_group') }}" name="pay_group_id" id="{{ $prefix }}_pay_group_id" select2-selector="default" :errorText="$errors->first('pay_group_id')" data-selected-value="{{ $fieldValue('pay_group_id') }}" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_pay_group') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6" id="{{ $prefix }}_leave_plan_wrapper">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_leave_structure') }}" name="leave_plan_id" id="{{ $prefix }}_leave_plan_id" select2-selector="default" :errorText="$errors->first('leave_plan_id')" data-selected-value="{{ $fieldValue('leave_plan_id') }}" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
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

            <!-- 1.3 Official Employment Terms & System Access -->
            <div class="employee-modal-section-title">Official Employment & System Access</div>
            <div class="row g-3">
                <div class="col-md-6">
                    @php
                        $usersList = $isEdit ? ($allTenantUsers ?? $unmappedUsers ?? collect()) : ($unmappedUsers ?? collect());
                    @endphp
                    <x-ui.odoo-form-ui type="select" label="User Account (Link to login)" name="user_id" id="{{ $prefix }}_user_id" :required="true" select2-selector="default" :errorText="$errors->first('user_id')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">Select User Account</option>
                        @foreach($usersList as $u)
                            <option value="{{ $u->id }}" data-user-name="{{ $u->name }}" data-user-email="{{ $u->email }}" data-user-role-id="{{ $u->role_id ?? $u->roles->first()?->id ?? '' }}" @selected((string) $fieldValue('user_id', $isEdit ? ($employee->user_id ?? '') : '') === (string) $u->id)>
                                {{ $u->name }} ({{ $u->email }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_full_name') }}" name="full_name" id="{{ $prefix }}_full_name" :required="true" :value="$fieldValue('full_name', $isEdit ? ($employee->full_name ?? '') : '')" placeholder="Enter Full Name" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="System Role" name="role_id" id="{{ $prefix }}_role_id" :required="true" select2-selector="default" :errorText="$errors->first('role_id')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">Select Role</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" @selected((string) old('role_id', ($isEdit && isset($employee) && $employee->user) ? $employee->user->role_id : '') === (string) $r->id)>
                                {{ $r->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_job_title') }}" name="job_title" id="{{ $prefix }}_job_title" :required="true" :value="$fieldValue('job_title')" placeholder="{{ __('hrms.employees.frm_job_title_placeholder') }}" :errorText="$errors->first('job_title')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_office_email') }}" name="office_email" id="{{ $prefix }}_office_email" inputType="email" :value="$fieldValue('office_email')" placeholder="{{ __('hrms.employees.frm_office_email_placeholder') }}" :errorText="$errors->first('office_email')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_gender') }}" name="gender" id="{{ $prefix }}_gender" :required="true" select2-selector="default" :errorText="$errors->first('gender')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_gender') }}</option>
                        @foreach($genders as $gender)
                            <option value="{{ $gender }}" @selected($fieldValue('gender') === $gender)>{{ $gender }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_stage') }}" name="employee_stage" id="{{ $prefix }}_employee_stage" select2-selector="default" :errorText="$errors->first('employee_stage')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_stage') }}</option>
                        @foreach($employeeStages as $stage)
                            <option value="{{ $stage }}" @selected($fieldValue('employee_stage') === $stage)>{{ $stage }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_emp_type') }}" name="employment_type" id="{{ $prefix }}_employment_type" select2-selector="default" :errorText="$errors->first('employment_type')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="">{{ __('hrms.employees.frm_select_type') }}</option>
                        @foreach($employmentTypes as $type)
                            <option value="{{ $type }}" @selected($fieldValue('employment_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_doj') }}" name="date_of_joining" id="{{ $prefix }}_date_of_joining" inputType="date" :required="true" :value="$fieldValue('date_of_joining', '')" :errorText="$errors->first('date_of_joining')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_probation_end') }}" name="probation_end_date" id="{{ $prefix }}_probation_end_date" inputType="date" :value="$fieldValue('probation_end_date', '')" :errorText="$errors->first('probation_end_date')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_confirmation') }}" name="confirmation_date" id="{{ $prefix }}_confirmation_date" inputType="date" :value="$fieldValue('confirmation_date', '')" :errorText="$errors->first('confirmation_date')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_exp') }}" name="experience" id="{{ $prefix }}_experience" inputType="number" step="0.01" :value="$fieldValue('experience', '0')" placeholder="0.00" :errorText="$errors->first('experience')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_salary') }}" name="current_salary" id="{{ $prefix }}_current_salary" inputType="number" step="0.01" :value="$fieldValue('current_salary', '0')" placeholder="0.00" :errorText="$errors->first('current_salary')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_status') }}" name="status" id="{{ $prefix }}_status" :required="true" select2-selector="default" :errorText="$errors->first('status')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="1" @selected((string) $fieldValue('status', '1') === '1')>{{ __('hrms.employees.frm_status_active') }}</option>
                        <option value="0" @selected((string) $fieldValue('status') === '0')>{{ __('hrms.employees.frm_status_inactive') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_location') }}" name="office" id="{{ $prefix }}_office" :errorText="$errors->first('office')" select2-selector="default" data-field-group="hr_admin" :disabled="$isEmployeeSelfService">
                        <option value="office" @selected($fieldValue('office') === 'office' || !$fieldValue('office'))>Office</option>
                        <option value="wfh" @selected($fieldValue('office') === 'wfh')>Work From Home (WFH)</option>
                        <option value="onsite" @selected($fieldValue('office') === 'onsite')>On-Site (Client/Project)</option>
                    </x-ui.odoo-form-ui>
                </div>
                <!-- WFH Coordinates -->
                <div class="col-12" id="{{ $prefix }}_wfh_coordinates_section" style="display: {{ $fieldValue('office') === 'wfh' ? 'block' : 'none' }};">
                    <div class="card bg-light border p-3 rounded-3 mb-1 shadow-sm">
                        <div class="fw-bold text-dark fs-12 mb-2"><i class="feather-map-pin me-1 text-primary"></i> WFH Geofence Coordinates</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="input" label="WFH Latitude" name="wfh_latitude" id="{{ $prefix }}_wfh_latitude" :value="$fieldValue('wfh_latitude', $isEdit && isset($employee) ? $employee->wfh_latitude : '')" placeholder="e.g. 28.6139" :errorText="$errors->first('wfh_latitude')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="input" label="WFH Longitude" name="wfh_longitude" id="{{ $prefix }}_wfh_longitude" :value="$fieldValue('wfh_longitude', $isEdit && isset($employee) ? $employee->wfh_longitude : '')" placeholder="e.g. 77.2090" :errorText="$errors->first('wfh_longitude')" data-field-group="hr_admin" :disabled="$isEmployeeSelfService" />
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="d-flex gap-2 w-100">
                                    <button type="button" class="btn btn-primary btn-sm flex-fill" id="{{ $prefix }}_btn_detect_wfh_loc" style="font-size: 11px;" @disabled($isEmployeeSelfService)>
                                        <i class="feather-crosshair me-1"></i>Detect
                                    </button>
                                    <button type="button" class="btn btn-light-brand btn-sm flex-fill" id="{{ $prefix }}_btn_toggle_wfh_map" style="font-size: 11px;" onclick="toggleWfhMapPicker_{{ $prefix }}()" @disabled($isEmployeeSelfService)>
                                        <i class="feather-map me-1"></i>Map
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="position-relative mt-3" id="{{ $prefix }}_wfh_map_wrap" style="display: none;">
                            <input type="text" id="{{ $prefix }}_wfh_map_search" class="form-control position-absolute" style="top: 10px; right: 10px; width: 240px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important; font-size: 11px; border: none !important; border-radius: 6px !important; padding: 6px 12px !important; height: 34px !important; background-color: #fff !important; outline: none !important;" placeholder="Search address or subarea (Press Enter)...">
                            <div id="{{ $prefix }}_wfh_map_picker" style="height: 180px; width: 100%; border-radius: 8px; border: 1px solid #ced4da; z-index: 1;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 2: PERSONAL & EMPLOYEE SELF-SERVICE PROFILE                     -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <div class="card border rounded-3 mb-0 shadow-none bg-white">
        <div class="card-header bg-light border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="d-flex align-items-center justify-content-center rounded-circle bg-soft-info text-info" style="width: 32px; height: 32px; min-width: 32px;">
                    <i class="feather-user fs-14"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0 fs-13">2. Personal & Employee Self-Service Information</h6>
                    <span class="text-muted fs-11">Personal details, identity compliance, addresses, and banking. Editable by employee during onboarding.</span>
                </div>
            </div>
            <x-ui.badge soft variant="info" class="border fs-11 px-2.5 py-1 fw-bold">
                <i class="feather-user-check me-1"></i>Employee Editable
            </x-ui.badge>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- Profile Photo Column -->
                <div class="col-xl-3 employee-photo-col">
                    <div class="employee-photo-panel">
                        <div class="employee-photo-preview" id="{{ $prefix }}_photo_preview">
                            {{ $isEdit ? 'EM' : strtoupper(substr((string) old('full_name', 'Employee'), 0, 2)) }}
                        </div>
                        <div class="fw-semibold text-dark mb-1">{{ __('hrms.employees.frm_profile_photo') }}</div>
                        <div class="text-muted fs-12 mb-3">{{ __('hrms.employees.frm_photo_help') }}</div>
                        <input type="file" class="form-control" name="photo" id="{{ $prefix }}_photo" accept=".png,.jpg,.jpeg,.webp" data-field-group="employee">
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="col-xl-9">
                    <div class="employee-modal-section-title mt-0">{{ __('hrms.employees.basic_details') }}</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_nick_name') }}" name="nick_name" id="{{ $prefix }}_nick_name" :value="$fieldValue('nick_name')" placeholder="{{ __('hrms.employees.frm_nick_name_placeholder') }}" :errorText="$errors->first('nick_name')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_dob') }}" name="date_of_birth" id="{{ $prefix }}_date_of_birth" inputType="date" :value="$fieldValue('date_of_birth', '')" :errorText="$errors->first('date_of_birth')" data-field-group="employee" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_marital_status') }}" name="marital_status" id="{{ $prefix }}_marital_status" select2-selector="default" :errorText="$errors->first('marital_status')" data-field-group="employee">
                                <option value="">{{ __('hrms.employees.frm_select_status') }}</option>
                                @foreach($maritalStatuses as $status)
                                    <option value="{{ $status }}" @selected($fieldValue('marital_status') === $status)>{{ $status }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-4" id="{{ $prefix }}_blood_group_wrapper">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_blood_group') }}" name="blood_group" id="{{ $prefix }}_blood_group" select2-selector="default" :errorText="$errors->first('blood_group')" data-field-group="employee">
                                <option value="">{{ __('hrms.employees.frm_select_blood') }}</option>
                                @foreach($bloodGroups as $bloodGroup)
                                    <option value="{{ $bloodGroup }}" @selected($fieldValue('blood_group') === $bloodGroup)>{{ $bloodGroup }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.frm_diet_pref') }}" name="diet_preference" id="{{ $prefix }}_diet_preference" select2-selector="default" :errorText="$errors->first('diet_preference')" data-field-group="employee">
                                <option value="">{{ __('hrms.employees.frm_select_diet') }}</option>
                                @foreach($dietPreferences as $preference)
                                    <option value="{{ $preference }}" @selected($fieldValue('diet_preference') === $preference)>{{ $preference }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- 2.2 Contact & Emergency Details -->
                    <div class="employee-modal-section-title">{{ __('hrms.employees.contact_compliance') }}</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_mobile') }}" name="personal_mobile_number" id="{{ $prefix }}_personal_mobile_number" :value="$fieldValue('personal_mobile_number')" placeholder="{{ __('hrms.employees.frm_mobile_placeholder') }}" :errorText="$errors->first('personal_mobile_number')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_email') }}" name="personal_email" id="{{ $prefix }}_personal_email" inputType="email" :value="$fieldValue('personal_email', $isEdit ? ($employee->personal_email ?? '') : '')" placeholder="{{ __('hrms.employees.frm_email_placeholder') }}" :errorText="$errors->first('personal_email')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_home_phone') }}" name="home_phone" id="{{ $prefix }}_home_phone" :value="$fieldValue('home_phone')" placeholder="{{ __('hrms.employees.frm_home_phone_placeholder') }}" :errorText="$errors->first('home_phone')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_emergency_name') }}" name="emergency_contact_name" id="{{ $prefix }}_emergency_contact_name" :value="$fieldValue('emergency_contact_name')" placeholder="{{ __('hrms.employees.frm_emergency_name_placeholder') }}" :errorText="$errors->first('emergency_contact_name')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_emergency_number') }}" name="emergency_contact_number" id="{{ $prefix }}_emergency_contact_number" :value="$fieldValue('emergency_contact_number')" placeholder="{{ __('hrms.employees.frm_emergency_number_placeholder') }}" :errorText="$errors->first('emergency_contact_number')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_emergency_relation') }}" name="emergency_contact_relation" id="{{ $prefix }}_emergency_contact_relation" :value="$fieldValue('emergency_contact_relation')" placeholder="{{ __('hrms.employees.frm_emergency_relation_placeholder') }}" :errorText="$errors->first('emergency_contact_relation')" data-field-group="employee" />
                        </div>
                    </div>

                    <!-- 2.3 Addresses -->
                    <div class="employee-modal-section-title">Address Information</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_city') }}" name="city" id="{{ $prefix }}_city" :value="$fieldValue('city')" placeholder="{{ __('hrms.employees.frm_city_placeholder') }}" :errorText="$errors->first('city')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_postal') }}" name="postal_code" id="{{ $prefix }}_postal_code" :value="$fieldValue('postal_code')" placeholder="{{ __('hrms.employees.frm_postal_placeholder') }}" :errorText="$errors->first('postal_code')" data-field-group="employee" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.frm_present_address') }}" name="present_address" id="{{ $prefix }}_present_address" rows="3" placeholder="{{ __('hrms.employees.frm_present_address_placeholder') }}" :errorText="$errors->first('present_address')" data-field-group="employee">{{ $fieldValue('present_address') }}</x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12 py-1">
                            <div class="odoo-form-group">
                                <div class="odoo-form-label"></div>
                                <div class="flex-grow-1">
                                    <div class="form-check m-0 d-flex align-items-center">
                                        <input class="form-check-input" type="checkbox" id="{{ $prefix }}_same_as_present" data-field-group="employee">
                                        <label class="form-check-label fw-bold text-dark ms-2" for="{{ $prefix }}_same_as_present">{{ __('hrms.employees.frm_same_address') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.frm_perm_address') }}" name="permanent_address" id="{{ $prefix }}_permanent_address" rows="3" placeholder="{{ __('hrms.employees.frm_perm_address_placeholder') }}" :errorText="$errors->first('permanent_address')" data-field-group="employee">{{ $fieldValue('permanent_address') }}</x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- 2.4 Statutory & Identity Compliance -->
                    <div class="employee-modal-section-title">Identity & Statutory Numbers</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_aadhaar') }}" name="aadhaar_card_number" id="{{ $prefix }}_aadhaar_card_number" :value="$fieldValue('aadhaar_card_number')" placeholder="{{ __('hrms.employees.frm_aadhaar_placeholder') }}" :errorText="$errors->first('aadhaar_card_number')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_pan') }}" name="pan_card_number" id="{{ $prefix }}_pan_card_number" :value="$fieldValue('pan_card_number')" placeholder="{{ __('hrms.employees.frm_pan_placeholder') }}" :errorText="$errors->first('pan_card_number')" data-field-group="employee" />
                        </div>
                    </div>

                    <!-- 2.5 Bank Account Details -->
                    <div class="employee-modal-section-title">{{ __('hrms.employees.bank_details') }}</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_bank_name') }}" name="bank_name" id="{{ $prefix }}_bank_name" :value="$fieldValue('bank_name')" placeholder="{{ __('hrms.employees.frm_bank_name_placeholder') }}" :errorText="$errors->first('bank_name')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_acc_number') }}" name="account_number" id="{{ $prefix }}_account_number" :value="$fieldValue('account_number')" placeholder="{{ __('hrms.employees.frm_acc_number_placeholder') }}" :errorText="$errors->first('account_number')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_ifsc') }}" name="ifsc_code" id="{{ $prefix }}_ifsc_code" :value="$fieldValue('ifsc_code')" placeholder="{{ __('hrms.employees.frm_ifsc_placeholder') }}" :errorText="$errors->first('ifsc_code')" data-field-group="employee" />
                        </div>
                    </div>

                    <!-- 2.6 Professional & Educational Qualifications -->
                    <div class="employee-modal-section-title">Qualifications & Skill Set</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_qualification') }}" name="qualification" id="{{ $prefix }}_qualification" :value="$fieldValue('qualification')" placeholder="{{ __('hrms.employees.frm_qualification_placeholder') }}" :errorText="$errors->first('qualification')" data-field-group="employee" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.frm_source_hire') }}" name="source_of_hire" id="{{ $prefix }}_source_of_hire" :value="$fieldValue('source_of_hire')" placeholder="{{ __('hrms.employees.frm_source_hire_placeholder') }}" :errorText="$errors->first('source_of_hire')" data-field-group="employee" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.frm_skill_set') }}" name="skill_set" id="{{ $prefix }}_skill_set" rows="3" placeholder="{{ __('hrms.employees.frm_skill_set_placeholder') }}" :errorText="$errors->first('skill_set')" data-field-group="employee">{{ $fieldValue('skill_set') }}</x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .pac-container {
        z-index: 9999 !important;
    }
</style>

<script>
const runEmployeeFormSetup = () => {
    // We use a self-executing or delayed block to ensure jQuery and DOM are ready
    const setupCoordinatesToggle = () => {
        if (typeof $ === 'undefined') {
            setTimeout(setupCoordinatesToggle, 100);
            return;
        }

        const prefix = "{{ $prefix }}";

        // Auto-fill Full Name and System Role from linked User Account selection
        $('#' + prefix + '_user_id').on('change', function() {
            const selectedOpt = $(this).find('option:selected');
            const userName = selectedOpt.attr('data-user-name') || selectedOpt.data('user-name') || '';
            const userRoleId = selectedOpt.attr('data-user-role-id') || selectedOpt.data('user-role-id') || '';
            if (userName) {
                $('#' + prefix + '_full_name').val(userName);
            }
            if (userRoleId) {
                $('#' + prefix + '_role_id').val(userRoleId).trigger('change');
            }
        });

        const officeSelect = $('#' + prefix + '_office');
        const coordSection = $('#' + prefix + '_wfh_coordinates_section');
        
        if (!officeSelect.length) return;

        // Toggle handler
        const toggleSection = () => {
            if (officeSelect.val() === 'wfh') {
                coordSection.slideDown(400, function() {
                    initWfhMapPicker();
                });
            } else {
                coordSection.slideUp();
                // Clear inputs if switching away from WFH to avoid stale coordinates
                $('#' + prefix + '_wfh_latitude').val('');
                $('#' + prefix + '_wfh_longitude').val('');
                $('#' + prefix + '_wfh_map_search').val('');
            }
        };

        officeSelect.on('change', toggleSection);

        // Initial setup
        if (officeSelect.val() === 'wfh') {
            coordSection.show();
        } else {
            coordSection.hide();
        }

        // Location Detection handler
        let wfhMap = null;
        let wfhMarker = null;

        const initWfhMapPicker = () => {
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                setTimeout(initWfhMapPicker, 100);
                return;
            }

            const mapContainerId = prefix + '_wfh_map_picker';
            const latInput = $('#' + prefix + '_wfh_latitude');
            const lngInput = $('#' + prefix + '_wfh_longitude');
            
            if (!document.getElementById(mapContainerId)) return;

            let initialLat = parseFloat(latInput.val()) || 28.6139; // Default to New Delhi or a sensible center
            let initialLng = parseFloat(lngInput.val()) || 77.2090;
            const initialPos = { lat: initialLat, lng: initialLng };
            
            if (wfhMap) {
                google.maps.event.trigger(wfhMap, 'resize');
                wfhMap.setCenter(initialPos);
                return;
            }

            wfhMap = new google.maps.Map(document.getElementById(mapContainerId), {
                center: initialPos,
                zoom: 13,
                mapTypeControl: false,
                fullscreenControl: false,
                streetViewControl: false
            });

            wfhMarker = new google.maps.Marker({
                position: initialPos,
                map: wfhMap,
                draggable: true
            });

            // Update inputs on marker drag
            wfhMarker.addListener('dragend', function() {
                const position = wfhMarker.getPosition();
                latInput.val(position.lat().toFixed(8));
                lngInput.val(position.lng().toFixed(8));
            });

            // Update marker and inputs on map click
            wfhMap.addListener('click', function(e) {
                wfhMarker.setPosition(e.latLng);
                latInput.val(e.latLng.lat().toFixed(8));
                lngInput.val(e.latLng.lng().toFixed(8));
            });

            // Update map when inputs change manually
            const updateMapFromInputs = () => {
                const lat = parseFloat(latInput.val());
                const lng = parseFloat(lngInput.val());
                if (!isNaN(lat) && !isNaN(lng)) {
                    const latlng = { lat: lat, lng: lng };
                    wfhMarker.setPosition(latlng);
                    wfhMap.setCenter(latlng);
                }
            };

            latInput.on('input', updateMapFromInputs);
            lngInput.on('input', updateMapFromInputs);

            // Bind Google Places Autocomplete search input
            const searchInputEl = document.getElementById(prefix + '_wfh_map_search');
            if (searchInputEl) {
                const autocomplete = new google.maps.places.Autocomplete(searchInputEl);
                autocomplete.bindTo('bounds', wfhMap);

                autocomplete.addListener('place_changed', function() {
                    const place = autocomplete.getPlace();
                    if (!place.geometry || !place.geometry.location) {
                        return;
                    }

                    wfhMap.setCenter(place.geometry.location);
                    wfhMap.setZoom(15);
                    wfhMarker.setPosition(place.geometry.location);

                    latInput.val(place.geometry.location.lat().toFixed(8));
                    lngInput.val(place.geometry.location.lng().toFixed(8));
                });

                // Prevent form submission when pressing enter on search box
                $(searchInputEl).on('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                    }
                });
            }
        };

        // Map toggle function (called inline from the Map button)
        window['toggleWfhMapPicker_' + prefix] = function() {
            const mapWrap = document.getElementById(prefix + '_wfh_map_wrap');
            const toggleBtn = document.getElementById(prefix + '_btn_toggle_wfh_map');
            if (!mapWrap) return;

            const isVisible = mapWrap.style.display !== 'none';
            if (isVisible) {
                mapWrap.style.display = 'none';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="feather-map me-1"></i>Map';
                    toggleBtn.classList.remove('btn-secondary');
                    toggleBtn.classList.add('btn-soft-secondary');
                }
            } else {
                mapWrap.style.display = 'block';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="feather-x me-1"></i>Hide Map';
                    toggleBtn.classList.remove('btn-soft-secondary');
                    toggleBtn.classList.add('btn-secondary');
                }
                // Lazy init map
                setTimeout(initWfhMapPicker, 150);
            }
        };

        // Location Detection handler
        $('#' + prefix + '_btn_detect_wfh_loc').on('click', function() {
            const btn = $(this);
            const originalHtml = btn.html();
            
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Det.');

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    $('#' + prefix + '_wfh_latitude').val(lat.toFixed(8));
                    $('#' + prefix + '_wfh_longitude').val(lng.toFixed(8));
                    
                    const latlng = { lat: lat, lng: lng };
                    if (wfhMap && wfhMarker) {
                        wfhMarker.setPosition(latlng);
                        wfhMap.setCenter(latlng);
                        wfhMap.setZoom(15);
                    } else {
                        // If map isn't open yet, auto-open it so user sees the detected location
                        const mapWrap = document.getElementById(prefix + '_wfh_map_wrap');
                        if (mapWrap && mapWrap.style.display === 'none') {
                            window['toggleWfhMapPicker_' + prefix]();
                            setTimeout(() => {
                                if (wfhMap && wfhMarker) {
                                    wfhMarker.setPosition(latlng);
                                    wfhMap.setCenter(latlng);
                                    wfhMap.setZoom(15);
                                }
                            }, 600);
                        }
                    }
                    
                    btn.prop('disabled', false).html(originalHtml);
                },
                function(error) {
                    let errorMsg = 'Unable to retrieve location.';
                    if (error.code === error.PERMISSION_DENIED) {
                        errorMsg = 'Permission denied. Please allow location access.';
                    }
                    alert(errorMsg);
                    btn.prop('disabled', false).html(originalHtml);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    };

    setupCoordinatesToggle();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runEmployeeFormSetup);
} else {
    runEmployeeFormSetup();
}
</script>
