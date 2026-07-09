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
            <div class="fw-semibold text-dark mb-1">Profile Photo</div>
            <div class="text-muted fs-12 mb-3">PNG, JPG, or WEBP up to 2 MB.</div>
            <input type="file" class="form-control" name="photo" id="{{ $prefix }}_photo" accept=".png,.jpg,.jpeg,.webp">
        </div>
    </div>
    <div class="col-xl-9">
        <div class="employee-modal-section-title">Organization Mapping</div>
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Company" name="company_id" id="{{ $prefix }}_company_id" :required="true" select2-selector="default" :errorText="$errors->first('company_id')">
                    <option value="">Select Company</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) $fieldValue('company_id') === (string) $company->id)>
                            {{ $company->company_name }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6" id="{{ $prefix }}_business_unit_wrapper">
                <x-ui.odoo-form-ui type="select" label="Business Unit" name="business_unit_id" id="{{ $prefix }}_business_unit_id" select2-selector="default" :errorText="$errors->first('business_unit_id')" data-selected-value="{{ $fieldValue('business_unit_id') }}">
                    <option value="">Select Business Unit</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6" id="{{ $prefix }}_branch_wrapper">
                <x-ui.odoo-form-ui type="select" label="Branch" name="branch_id" id="{{ $prefix }}_branch_id" select2-selector="default" :errorText="$errors->first('branch_id')" data-selected-value="{{ $fieldValue('branch_id') }}">
                    <option value="">Select Branch</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Department" name="department_id" id="{{ $prefix }}_department_id" :required="true" select2-selector="default" :errorText="$errors->first('department_id')" data-selected-value="{{ $fieldValue('department_id') }}">
                    <option value="">Select Department</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Designation" name="designation_id" id="{{ $prefix }}_designation_id" :required="true" select2-selector="default" :errorText="$errors->first('designation_id')" data-selected-value="{{ $fieldValue('designation_id') }}">
                    <option value="">Select Designation</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Employee Code" name="employee_id" id="{{ $prefix }}_employee_id" :required="true" :value="$fieldValue('employee_id')" placeholder="EMP-0001" :errorText="$errors->first('employee_id')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Reporting Manager" name="reporting_manager_id" id="{{ $prefix }}_reporting_manager_id" select2-selector="default" :errorText="$errors->first('reporting_manager_id')">
                    <option value="">Select Reporting Manager</option>
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
                <x-ui.odoo-form-ui type="select" label="Work Shift" name="shift_id" id="{{ $prefix }}_shift_id" select2-selector="default" :errorText="$errors->first('shift_id')">
                    <option value="">Select Work Shift</option>
                    @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}" @selected((string) $fieldValue('shift_id') === (string) $shift->id)>
                            {{ $shift->name }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="employee-modal-section-title">Master Assignments</div>
        <div class="row g-3">
            <div class="col-md-6" id="{{ $prefix }}_pay_group_wrapper">
                <x-ui.odoo-form-ui type="select" label="Pay Group" name="pay_group_id" id="{{ $prefix }}_pay_group_id" select2-selector="default" :errorText="$errors->first('pay_group_id')" data-selected-value="{{ $fieldValue('pay_group_id') }}">
                    <option value="">Select Pay Group</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6" id="{{ $prefix }}_leave_plan_wrapper">
                <x-ui.odoo-form-ui type="select" label="Leave Structure" name="leave_plan_id" id="{{ $prefix }}_leave_plan_id" select2-selector="default" :errorText="$errors->first('leave_plan_id')" data-selected-value="{{ $fieldValue('leave_plan_id') }}">
                    <option value="">Select Leave Structure</option>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="employee-modal-section-title">Basic Details</div>
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Full Name" name="full_name" id="{{ $prefix }}_full_name" :required="true" :value="$fieldValue('full_name')" placeholder="Enter full name" :errorText="$errors->first('full_name')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Nick Name" name="nick_name" id="{{ $prefix }}_nick_name" :value="$fieldValue('nick_name')" placeholder="Optional short name" :errorText="$errors->first('nick_name')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Job Title" name="job_title" id="{{ $prefix }}_job_title" :value="$fieldValue('job_title')" placeholder="HR Executive, Developer, etc." :errorText="$errors->first('job_title')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Role / Function" name="role" id="{{ $prefix }}_role" :value="$fieldValue('role')" placeholder="Role inside the organization" :errorText="$errors->first('role')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="Employee Stage" name="employee_stage" id="{{ $prefix }}_employee_stage" select2-selector="default" :errorText="$errors->first('employee_stage')">
                    <option value="">Select Stage</option>
                    @foreach($employeeStages as $stage)
                        <option value="{{ $stage }}" @selected($fieldValue('employee_stage') === $stage)>{{ $stage }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="Employment Type" name="employment_type" id="{{ $prefix }}_employment_type" select2-selector="default" :errorText="$errors->first('employment_type')">
                    <option value="">Select Type</option>
                    @foreach($employmentTypes as $type)
                        <option value="{{ $type }}" @selected($fieldValue('employment_type') === $type)>{{ $type }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Date Of Joining" name="date_of_joining" id="{{ $prefix }}_date_of_joining" inputType="date" :required="true" :value="$fieldValue('date_of_joining', '')" :errorText="$errors->first('date_of_joining')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Date Of Birth" name="date_of_birth" id="{{ $prefix }}_date_of_birth" inputType="date" :value="$fieldValue('date_of_birth', '')" :errorText="$errors->first('date_of_birth')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Probation End Date" name="probation_end_date" id="{{ $prefix }}_probation_end_date" inputType="date" :value="$fieldValue('probation_end_date', '')" :errorText="$errors->first('probation_end_date')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Confirmation Date" name="confirmation_date" id="{{ $prefix }}_confirmation_date" inputType="date" :value="$fieldValue('confirmation_date', '')" :errorText="$errors->first('confirmation_date')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="Gender" name="gender" id="{{ $prefix }}_gender" :required="true" select2-selector="default" :errorText="$errors->first('gender')">
                    <option value="">Select Gender</option>
                    @foreach($genders as $gender)
                        <option value="{{ $gender }}" @selected($fieldValue('gender') === $gender)>{{ $gender }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="Marital Status" name="marital_status" id="{{ $prefix }}_marital_status" select2-selector="default" :errorText="$errors->first('marital_status')">
                    <option value="">Select Status</option>
                    @foreach($maritalStatuses as $status)
                        <option value="{{ $status }}" @selected($fieldValue('marital_status') === $status)>{{ $status }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="Blood Group" name="blood_group" id="{{ $prefix }}_blood_group" select2-selector="default" :errorText="$errors->first('blood_group')">
                    <option value="">Select Blood Group</option>
                    @foreach($bloodGroups as $bloodGroup)
                        <option value="{{ $bloodGroup }}" @selected($fieldValue('blood_group') === $bloodGroup)>{{ $bloodGroup }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Diet Preference" name="diet_preference" id="{{ $prefix }}_diet_preference" select2-selector="default" :errorText="$errors->first('diet_preference')">
                    <option value="">Select Preference</option>
                    @foreach($dietPreferences as $preference)
                        <option value="{{ $preference }}" @selected($fieldValue('diet_preference') === $preference)>{{ $preference }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Office / Work Location" name="office" id="{{ $prefix }}_office" :value="$fieldValue('office')" placeholder="Head Office, Plant 1, etc." :errorText="$errors->first('office')" />
            </div>
        </div>

        <div class="employee-modal-section-title">Contact And Compliance</div>
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Personal Mobile" name="personal_mobile_number" id="{{ $prefix }}_personal_mobile_number" :value="$fieldValue('personal_mobile_number')" placeholder="Enter mobile number" :errorText="$errors->first('personal_mobile_number')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Personal Email" name="personal_email" id="{{ $prefix }}_personal_email" inputType="email" :value="$fieldValue('personal_email')" placeholder="Enter email address" :errorText="$errors->first('personal_email')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Office Email" name="office_email" id="{{ $prefix }}_office_email" inputType="email" :value="$fieldValue('office_email')" placeholder="Enter office email address" :errorText="$errors->first('office_email')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Home Phone" name="home_phone" id="{{ $prefix }}_home_phone" :value="$fieldValue('home_phone')" placeholder="Optional home phone" :errorText="$errors->first('home_phone')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="City" name="city" id="{{ $prefix }}_city" :value="$fieldValue('city')" placeholder="Enter city" :errorText="$errors->first('city')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Postal Code" name="postal_code" id="{{ $prefix }}_postal_code" :value="$fieldValue('postal_code')" placeholder="Enter postal code" :errorText="$errors->first('postal_code')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Aadhaar Number" name="aadhaar_card_number" id="{{ $prefix }}_aadhaar_card_number" :value="$fieldValue('aadhaar_card_number')" placeholder="Optional Aadhaar number" :errorText="$errors->first('aadhaar_card_number')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="PAN Number" name="pan_card_number" id="{{ $prefix }}_pan_card_number" :value="$fieldValue('pan_card_number')" placeholder="Optional PAN number" :errorText="$errors->first('pan_card_number')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Emergency Contact Name" name="emergency_contact_name" id="{{ $prefix }}_emergency_contact_name" :value="$fieldValue('emergency_contact_name')" placeholder="Contact person name" :errorText="$errors->first('emergency_contact_name')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Emergency Contact Number" name="emergency_contact_number" id="{{ $prefix }}_emergency_contact_number" :value="$fieldValue('emergency_contact_number')" placeholder="Contact person phone" :errorText="$errors->first('emergency_contact_number')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Emergency Contact Relation" name="emergency_contact_relation" id="{{ $prefix }}_emergency_contact_relation" :value="$fieldValue('emergency_contact_relation')" placeholder="e.g. Spouse, Father..." :errorText="$errors->first('emergency_contact_relation')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="textarea" label="Present Address" name="present_address" id="{{ $prefix }}_present_address" rows="3" placeholder="Enter present address" :errorText="$errors->first('present_address')">{{ $fieldValue('present_address') }}</x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="textarea" label="Permanent Address" name="permanent_address" id="{{ $prefix }}_permanent_address" rows="3" placeholder="Enter permanent address" :errorText="$errors->first('permanent_address')">{{ $fieldValue('permanent_address') }}</x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="employee-modal-section-title">Bank Account Details</div>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Bank Name" name="bank_name" id="{{ $prefix }}_bank_name" :value="$fieldValue('bank_name')" placeholder="Enter bank name" :errorText="$errors->first('bank_name')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Account Number" name="account_number" id="{{ $prefix }}_account_number" :value="$fieldValue('account_number')" placeholder="Enter account number" :errorText="$errors->first('account_number')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="IFSC Code" name="ifsc_code" id="{{ $prefix }}_ifsc_code" :value="$fieldValue('ifsc_code')" placeholder="Enter bank IFSC code" :errorText="$errors->first('ifsc_code')" />
            </div>
        </div>

        <div class="employee-modal-section-title">Professional Details</div>
        <div class="row g-3">
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Experience (Years)" name="experience" id="{{ $prefix }}_experience" inputType="number" step="0.01" :value="$fieldValue('experience', '0')" placeholder="0.00" :errorText="$errors->first('experience')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" label="Current Salary" name="current_salary" id="{{ $prefix }}_current_salary" inputType="number" step="0.01" :value="$fieldValue('current_salary', '0')" placeholder="0.00" :errorText="$errors->first('current_salary')" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="select" label="Status" name="status" id="{{ $prefix }}_status" :required="true" select2-selector="default" :errorText="$errors->first('status')">
                    <option value="1" @selected((string) $fieldValue('status', '1') === '1')>Active</option>
                    <option value="0" @selected((string) $fieldValue('status') === '0')>Inactive</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Qualification" name="qualification" id="{{ $prefix }}_qualification" :value="$fieldValue('qualification')" placeholder="B.Tech, MBA, etc." :errorText="$errors->first('qualification')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" label="Source Of Hire" name="source_of_hire" id="{{ $prefix }}_source_of_hire" :value="$fieldValue('source_of_hire')" placeholder="Referral, Portal, Campus, etc." :errorText="$errors->first('source_of_hire')" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="Skill Set" name="skill_set" id="{{ $prefix }}_skill_set" rows="3" placeholder="Key skills, tools, certifications" :errorText="$errors->first('skill_set')">{{ $fieldValue('skill_set') }}</x-ui.odoo-form-ui>
            </div>
        </div>
    </div>
</div>
