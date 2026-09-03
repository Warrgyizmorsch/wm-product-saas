<div class="tab-pane fade {{ $activeTabName === 'overview' ? 'show active' : '' }}" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
    <div class="row">
        <div class="col-md-6 col-12">
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-briefcase text-primary"></i> {{ __('hrms.employees.lbl_employment_details') }}</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.tbl_company') }}</div>
                            <div class="info-value">{{ $employee->company?->company_name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.org.business_units') }}</div>
                            <div class="info-value">{{ $employee->businessUnit?->name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.org.branches') }}</div>
                            <div class="info-value">{{ $employee->branch?->name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.tbl_department') }}</div>
                            <div class="info-value">{{ $employee->department?->name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.tbl_designation') }}</div>
                            <div class="info-value">{{ $employee->designation?->name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_doj') }}</div>
                            <div class="info-value">{{ $employee->date_of_joining ? $employee->date_of_joining->format('d M, Y') : 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_emp_type') }}</div>
                            <div class="info-value">{{ $employee->employment_type ?: 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_stage') }}</div>
                            <div class="info-value">
                                @php
                                    $stageVariant = match($employee->employee_stage) {
                                        'Probation'     => 'warning',
                                        'Confirmed'     => 'success',
                                        'Notice Period' => 'danger',
                                        'Exited'        => 'secondary',
                                        default         => 'light',
                                    };
                                @endphp
                                <x-ui.badge soft :variant="$stageVariant" class="fs-12 fw-semibold px-2.5 py-0.5">
                                    {{ $employee->employee_stage ?: 'Not Set' }}
                                </x-ui.badge>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_manager') }}</div>
                            <div class="info-value">{{ $employee->reportingManager?->full_name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.org.shifts') }}</div>
                            <div class="info-value">{{ $employee->shift?->name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_office_email') }}</div>
                            <div class="info-value">{{ $employee->office_email ?: 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_probation_end') }}</div>
                            <div class="info-value">{{ $employee->probation_end_date ? $employee->probation_end_date->format('d M, Y') : 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_confirmation_date') }}</div>
                            <div class="info-value">{{ $employee->confirmation_date ? $employee->confirmation_date->format('d M, Y') : 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-12">
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-user text-primary"></i> {{ __('hrms.employees.lbl_personal_details') }}</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_gender') }}</div>
                            <div class="info-value">{{ $employee->gender }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_marital_status') }}</div>
                            <div class="info-value">{{ $employee->marital_status ?: 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_blood_group') }}</div>
                            <div class="info-value">{{ $employee->blood_group ?: 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_diet_preference') }}</div>
                            <div class="info-value">{{ $employee->diet_preference ?: 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_aadhaar') }}</div>
                            <div class="info-value">{{ $employee->aadhaar_card_number ?: 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_pan') }}</div>
                            <div class="info-value">{{ $employee->pan_card_number ?: 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">{{ __('hrms.employees.lbl_dob') }}</div>
                            <div class="info-value">{{ $employee->date_of_birth ? $employee->date_of_birth->format('d M, Y') : 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-map-pin text-primary"></i> {{ __('hrms.employees.lbl_contact_address') }}</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_personal_mobile') }}</div>
                            <div class="info-value">{{ $employee->personal_mobile_number ?: 'N/A' }}</div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_home_phone') }}</div>
                            <div class="info-value">{{ $employee->home_phone ?: 'N/A' }}</div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_city_postal') }}</div>
                            <div class="info-value">{{ $employee->city ?: 'N/A' }} {{ $employee->postal_code ? '(' . $employee->postal_code . ')' : '' }}</div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_emergency_contact') }}</div>
                            <div class="info-value">{{ $employee->emergency_contact_name ?: 'N/A' }}</div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_emergency_number') }}</div>
                            <div class="info-value">{{ $employee->emergency_contact_number ?: 'N/A' }}</div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_emergency_relation') }}</div>
                            <div class="info-value">{{ $employee->emergency_contact_relation ?: 'N/A' }}</div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_present_address') }}</div>
                            <div class="info-value text-wrap" style="max-width: 100%;">{{ $employee->present_address ?: 'N/A' }}</div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_permanent_address') }}</div>
                            <div class="info-value text-wrap" style="max-width: 100%;">{{ $employee->permanent_address ?: 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 mt-4">
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-credit-card text-primary"></i> {{ __('hrms.employees.lbl_bank_details') }}</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_bank_name') }}</div>
                            <div class="info-value">{{ $employee->bank_name ?: 'N/A' }}</div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_account_number') }}</div>
                            <div class="info-value">{{ $employee->account_number ?: 'N/A' }}</div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="info-label">{{ __('hrms.employees.lbl_ifsc_code') }}</div>
                            <div class="info-value">{{ $employee->ifsc_code ?: 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
