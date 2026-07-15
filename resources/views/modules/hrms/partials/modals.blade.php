@php
    $companies = $companiesList ?? $companies ?? \App\Domains\HRMS\Models\Company::all();
    $businessUnits = $businessUnitsList ?? $businessUnits ?? \App\Domains\HRMS\Models\BusinessUnit::all();
    $branches = $branchesList ?? $branches ?? \App\Domains\HRMS\Models\Branch::all();
    $departments = $departmentsList ?? $departments ?? \App\Domains\HRMS\Models\Department::all();
    $employees = $employeesList ?? $employees ?? \App\Domains\HRMS\Models\Employee::all();
    $salaryComponents = $salaryComponents ?? \App\Domains\HRMS\Models\SalaryComponent::all();
@endphp

<style>
    .hrms-entity-form .hrms-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 10px 0 14px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #64748b;
    }
    .hrms-entity-form .hrms-section-title::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #e5e7eb;
    }
    .hrms-entity-form .hrms-logo-panel {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #f8fafc;
        padding: 16px;
        height: 100%;
    }

    /* Override flex label width for cleaner alignment on long labels inside specific modals */
    #addBuModal .odoo-form-label,
    #editBuModal .odoo-form-label,
    #addDeptModal .odoo-form-label,
    #editDeptModal .odoo-form-label,
    #addDesigModal .odoo-form-label,
    #editDesigModal .odoo-form-label,
    #addBranchModal .odoo-form-label,
    #editBranchModal .odoo-form-label,
    #addSalaryStructureModal .odoo-form-label,
    #editSalaryStructureModal .odoo-form-label,
    #addShiftModal .odoo-form-label,
    #editShiftModal .odoo-form-label {
        width: 170px !important;
    }

    /* Stack labels vertically above inputs for address fields in Branch modals */
    .branch-address-field .odoo-form-group {
        flex-direction: column !important;
        align-items: flex-start !important;
    }
    .branch-address-field .odoo-form-label {
        width: 100% !important;
        margin-bottom: 5px !important;
    }
</style>

<!-- ============================================ -->
<!--            LEGAL ENTITIES MODALS             -->
<!-- ============================================ -->

<!-- View Company Modal -->
<div class="modal fade" id="viewCompanyModal" tabindex="-1" aria-labelledby="viewCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewCompanyModalLabel"><i class="feather-info me-2"></i>{{ __('hrms.org.legal_entity_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_logo_container" class="avatar-image avatar-xl rounded-3 border border-2 border-white shadow-sm overflow-hidden" style="width: 64px; height: 64px;">
                        <!-- Dynamically generated -->
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_company_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_legal_name"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">{{ __('hrms.org.registration_info') }}</span>
                            <div class="mb-2"><strong class="fs-12 text-muted">{{ __('hrms.org.gst') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_gst"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">{{ __('hrms.org.pan') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_pan"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">{{ __('hrms.org.cin') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_cin"></span></div>
                            <div class="mb-0"><strong class="fs-12 text-muted">{{ __('hrms.org.reg_no') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_reg"></span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">{{ __('hrms.org.contact_locale') }}</span>
                            <div class="mb-2"><strong class="fs-12 text-muted">{{ __('hrms.org.email') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_email"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">{{ __('hrms.org.phone') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_phone"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">{{ __('hrms.org.currency') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_currency"></span></div>
                            <div class="mb-0"><strong class="fs-12 text-muted">{{ __('hrms.org.timezone') }}:</strong> <span class="fs-13 text-dark fw-bold text-truncate d-inline-block" style="max-width: 200px;" id="modal_view_timezone"></span></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">{{ __('hrms.org.location_details') }}</span>
                            <div class="row g-2">
                                <div class="col-sm-3"><strong class="fs-12 text-muted">{{ __('hrms.org.country') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_country"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">{{ __('hrms.org.state') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_state"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">{{ __('hrms.org.city') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_city"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">{{ __('hrms.org.zip_code') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_zip"></span></div>
                                <div class="col-12 mt-2 pt-2 border-top"><strong class="fs-12 text-muted">{{ __('hrms.org.full_address') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_address"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                if (window.hrmsThemedValidationInstalled) {
                    return;
                }

                window.hrmsThemedValidationInstalled = true;

                function getFieldLabel(field) {
                    const group = field.closest('.odoo-form-group');
                    const label = group ? group.querySelector('.odoo-form-label') : null;
                    return label ? label.textContent.replace('*', '').trim() : 'This field';
                }

                function getValidationMessage(field) {
                    const label = getFieldLabel(field);

                    if (field.validity.valueMissing) {
                        if (field.tagName === 'SELECT') {
                            return `Please select ${label.toLowerCase()}.`;
                        }

                        return `Please enter ${label.toLowerCase()}.`;
                    }

                    return field.validationMessage || 'Please enter a valid value.';
                }

                function getErrorAnchor(field) {
                    if (field.tagName === 'SELECT' && field.nextElementSibling && field.nextElementSibling.classList.contains('select2-container')) {
                        return field.nextElementSibling;
                    }

                    const radioWrap = field.closest('.odoo-form-group')?.querySelector('.flex-grow-1');
                    if (field.type === 'radio' && radioWrap) {
                        return radioWrap;
                    }

                    return field;
                }

                function findErrorElement(field) {
                    const anchor = getErrorAnchor(field);
                    const next = anchor.nextElementSibling;

                    if (next && next.classList.contains('hrms-client-validation-error')) {
                        return next;
                    }

                    return null;
                }

                function showFieldError(field) {
                    field.classList.add('is-invalid');
                    field.setAttribute('aria-invalid', 'true');

                    const anchor = getErrorAnchor(field);
                    let error = findErrorElement(field);

                    if (!error) {
                        error = document.createElement('div');
                        error.className = 'invalid-feedback d-block fs-11 mt-1 hrms-client-validation-error';
                        anchor.insertAdjacentElement('afterend', error);
                    }

                    error.textContent = getValidationMessage(field);
                }

                function clearFieldError(field) {
                    field.classList.remove('is-invalid');
                    field.removeAttribute('aria-invalid');

                    const error = findErrorElement(field);
                    if (error) {
                        error.remove();
                    }
                }

                function getRequiredFields(form) {
                    return Array.from(form.querySelectorAll('[required]')).filter(function (field) {
                        return !field.disabled && field.type !== 'hidden';
                    });
                }

                function validateField(field) {
                    if (field.checkValidity()) {
                        clearFieldError(field);
                        return true;
                    }

                    showFieldError(field);
                    return false;
                }

                function focusField(field) {
                    if (field.tagName === 'SELECT' && field.nextElementSibling && field.nextElementSibling.classList.contains('select2-container')) {
                        field.nextElementSibling.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        field.nextElementSibling.querySelector('.select2-selection')?.focus();
                        return;
                    }

                    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    field.focus({ preventScroll: true });
                }

                function bindHrmsValidation(root) {
                    root.querySelectorAll('form').forEach(function (form) {
                        if (form.dataset.hrmsThemedValidation === '1' || !form.querySelector('[required]')) {
                            return;
                        }

                        form.dataset.hrmsThemedValidation = '1';
                        form.setAttribute('novalidate', 'novalidate');

                        getRequiredFields(form).forEach(function (field) {
                            field.addEventListener('input', function () {
                                validateField(field);
                            });
                            field.addEventListener('change', function () {
                                validateField(field);
                            });
                        });

                        form.addEventListener('submit', function (event) {
                            const invalidField = getRequiredFields(form).find(function (field) {
                                return !validateField(field);
                            });

                            if (invalidField) {
                                event.preventDefault();
                                event.stopImmediatePropagation();
                                focusField(invalidField);
                            }
                        });
                    });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    bindHrmsValidation(document);
                });

                document.addEventListener('shown.bs.modal', function (event) {
                    bindHrmsValidation(event.target);
                });
            })();
        </script>
    @endpush
@endonce

<!-- Add Company Modal -->
<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-labelledby="addCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addCompanyModalLabel"><i class="feather-plus me-2 text-primary"></i>{{ __('hrms.org.add_legal_entity') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.company.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="form_mode" value="add_company">
                <div class="modal-body p-4 hrms-entity-form">
                    <div class="row g-4 align-items-stretch mb-2">
                        <div class="col-lg-3">
                            <div class="hrms-logo-panel">
                                <label class="form-label fw-semibold mb-3">{{ __('hrms.org.tbl_logo') }}:</label>
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="wd-100 ht-100 position-relative overflow-hidden border border-gray-200 rounded bg-white">
                                        <img src="{{ asset('assets/images/avatar/1.png') }}" class="add-upload-pic img-fluid rounded h-100 w-100" id="add_logo_preview" alt="">
                                        <div class="position-absolute start-50 top-50 end-0 translate-middle h-100 w-100 hstack align-items-center justify-content-center c-pointer add-upload-button upload-button" style="background: rgba(0,0,0,0.3); color: white;">
                                            <i class="feather feather-camera" aria-hidden="true"></i>
                                        </div>
                                        <input class="add-file-upload" type="file" name="logo" accept="image/*" style="display: none;">
                                    </div>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="fs-11 text-gray-500">{{ __('hrms.org.avatar_size_150') }}</div>
                                        <div class="fs-11 text-gray-500">{{ __('hrms.org.max_upload_2mb') }}</div>
                                        <div class="fs-11 text-gray-500">{{ __('hrms.org.allowed_formats') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-9">
                            <div class="hrms-section-title">{{ __('hrms.org.entity_identity') }}</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.company_name') }}" name="company_name" :required="true" placeholder="{{ __('hrms.org.company_name') }}" :errorText="$errors->first('company_name')" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.legal_name') }}" name="legal_name" :required="true" placeholder="{{ __('hrms.org.legal_name') }}" :errorText="$errors->first('legal_name')" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.currency') }}" name="currency" :required="true" placeholder="e.g. INR, USD" :errorText="$errors->first('currency')" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.timezone') }}" name="time_zone" select2-selector="tzone" class="geo-timezone" :required="true" :errorText="$errors->first('time_zone')" data-initial-value="{{ old('time_zone', 'Asia/Kolkata') }}">
                                        <option value="">{{ __('hrms.org.select_timezone') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hrms-section-title">{{ __('hrms.org.registration_details') }}</div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.gst_number') }}" name="gst_number" placeholder="{{ __('hrms.org.gst_number') }}" :errorText="$errors->first('gst_number')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.pan_number') }}" name="pan_number" placeholder="{{ __('hrms.org.pan_number') }}" :errorText="$errors->first('pan_number')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.cin_number') }}" name="cin_number" placeholder="{{ __('hrms.org.cin_number') }}" :errorText="$errors->first('cin_number')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.registration_number') }}" name="registration_number" placeholder="{{ __('hrms.org.registration_number') }}" :errorText="$errors->first('registration_number')" />
                        </div>
                    </div>

                    <div class="hrms-section-title">{{ __('hrms.org.contact_and_status') }}</div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.email') }}" name="email" inputType="email" placeholder="{{ __('hrms.org.email') }}" :errorText="$errors->first('email')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.phone') }}" name="phone" placeholder="{{ __('hrms.org.phone') }}" :errorText="$errors->first('phone')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.website') }}" name="website" placeholder="{{ __('hrms.org.website') }}" :errorText="$errors->first('website')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="hrms-section-title">{{ __('hrms.org.location') }}</div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.country') }}" name="country" select2-selector="country" class="geo-country" :errorText="$errors->first('country')" data-initial-value="{{ old('country', 'United States') }}">
                                <option value="">{{ __('hrms.org.select_country') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.state') }}" name="state" select2-selector="default" class="geo-state" :errorText="$errors->first('state')">
                                <option value="">{{ __('hrms.org.select_state') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.city') }}" name="city" select2-selector="default" class="geo-city" :errorText="$errors->first('city')">
                                <option value="">{{ __('hrms.org.select_city') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.postal_code') }}" name="postal_code" placeholder="{{ __('hrms.org.postal_code') }}" :errorText="$errors->first('postal_code')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.address') }}" name="address" rows="3" placeholder="{{ __('hrms.org.address') }}" :errorText="$errors->first('address')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.save_entity') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-labelledby="editCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editCompanyModalLabel"><i class="feather-edit me-2 text-primary"></i>{{ __('hrms.org.edit_legal_entity') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="company_edit_form" method="POST" action="{{ old('form_mode') === 'edit_company' && old('edit_company_id') ? route('hrms.company.update', ['company' => old('edit_company_id')]) : '' }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="form_mode" value="edit_company">
                <input type="hidden" name="edit_company_id" id="edit_company_id" value="{{ old('edit_company_id') }}">
                <div class="modal-body p-4 hrms-entity-form">
                    <div class="row g-4 align-items-stretch mb-2">
                        <div class="col-lg-3">
                            <div class="hrms-logo-panel">
                                <label class="form-label fw-semibold mb-3">{{ __('hrms.org.tbl_logo') }}:</label>
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="wd-100 ht-100 position-relative overflow-hidden border border-gray-200 rounded bg-white">
                                        <img src="" class="edit-upload-pic img-fluid rounded h-100 w-100" id="edit_logo_preview" alt="">
                                        <div class="position-absolute start-50 top-50 end-0 translate-middle h-100 w-100 hstack align-items-center justify-content-center c-pointer edit-upload-button upload-button" style="background: rgba(0,0,0,0.3); color: white;">
                                            <i class="feather feather-camera" aria-hidden="true"></i>
                                        </div>
                                        <input class="edit-file-upload" type="file" name="logo" accept="image/*" style="display: none;">
                                    </div>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="fs-11 text-gray-500">{{ __('hrms.org.upload_new_logo') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-9">
                            <div class="hrms-section-title">{{ __('hrms.org.entity_identity') }}</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.company_name') }}" name="company_name" id="edit_company_name" :required="true" :errorText="$errors->first('company_name')" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.legal_name') }}" name="legal_name" id="edit_legal_name" :required="true" :errorText="$errors->first('legal_name')" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.currency') }}" name="currency" id="edit_currency" :required="true" :errorText="$errors->first('currency')" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.timezone') }}" name="time_zone" id="edit_timezone" select2-selector="tzone" class="geo-timezone" :required="true" :errorText="$errors->first('time_zone')">
                                        <option value="">{{ __('hrms.org.select_timezone') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hrms-section-title">{{ __('hrms.org.registration_details') }}</div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.gst_number') }}" name="gst_number" id="edit_gst_number" :errorText="$errors->first('gst_number')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.pan_number') }}" name="pan_number" id="edit_pan_number" :errorText="$errors->first('pan_number')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.cin_number') }}" name="cin_number" id="edit_cin_number" :errorText="$errors->first('cin_number')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.registration_number') }}" name="registration_number" id="edit_registration_number" :errorText="$errors->first('registration_number')" />
                        </div>
                    </div>

                    <div class="hrms-section-title">{{ __('hrms.org.contact_and_status') }}</div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.email') }}" name="email" id="edit_email" inputType="email" :errorText="$errors->first('email')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.phone') }}" name="phone" id="edit_phone" :errorText="$errors->first('phone')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.website') }}" name="website" id="edit_website" :errorText="$errors->first('website')" />
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="hrms-section-title">{{ __('hrms.org.location') }}</div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.country') }}" name="country" id="edit_country" select2-selector="country" class="geo-country" :errorText="$errors->first('country')">
                                <option value="">{{ __('hrms.org.select_country') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.state') }}" name="state" id="edit_state" select2-selector="default" class="geo-state" :errorText="$errors->first('state')">
                                <option value="">{{ __('hrms.org.select_state') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.city') }}" name="city" id="edit_city" select2-selector="default" class="geo-city" :errorText="$errors->first('city')">
                                <option value="">{{ __('hrms.org.select_city') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-lg-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.postal_code') }}" name="postal_code" id="edit_postal_code" :errorText="$errors->first('postal_code')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.address') }}" name="address" id="edit_address" rows="3" :errorText="$errors->first('address')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.update_entity') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================ -->
<!--            BUSINESS UNITS MODALS             -->
<!-- ============================================ -->

<!-- View BU Modal -->
<div class="modal fade" id="viewBuModal" tabindex="-1" aria-labelledby="viewBuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewBuModalLabel"><i class="feather-info me-2"></i>{{ __('hrms.org.business_unit_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_bu_avatar" class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center fw-bold fs-16" style="width: 54px; height: 54px;">
                        BU
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_bu_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_bu_company"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.business_unit_code') }}</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_bu_code"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.unit_head') }}</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_bu_head"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.status') }}</label>
                        <div id="modal_view_bu_status"></div>
                    </div>
                    <div class="col-12 mt-2">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.description') }}</label>
                        <p class="fs-13 fw-semibold text-dark mb-0" id="modal_view_bu_desc"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Add BU Modal -->
<div class="modal fade" id="addBuModal" tabindex="-1" aria-labelledby="addBuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addBuModalLabel"><i class="feather-plus me-2 text-primary"></i>{{ __('hrms.org.add_business_unit') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.business-unit.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.business_unit_name') }}" name="name" :required="true" placeholder="{{ __('hrms.org.business_unit_name') }}" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.business_unit_code') }}" name="code" :required="true" placeholder="{{ __('hrms.org.business_unit_code') }}" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" :required="true" select2-selector="default" :errorText="$errors->first('company_id')">
                                <option value="">{{ __('hrms.org.parent_company') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" rows="3" placeholder="{{ __('hrms.org.description') }}" :errorText="$errors->first('description')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.save_business_unit') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit BU Modal -->
<div class="modal fade" id="editBuModal" tabindex="-1" aria-labelledby="editBuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editBuModalLabel"><i class="feather-edit me-2 text-primary"></i>{{ __('hrms.assets.edit') }} {{ __('hrms.org.business_units') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bu_edit_form" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.business_unit_name') }}" name="name" id="edit_bu_name" :required="true" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.business_unit_code') }}" name="code" id="edit_bu_code" :required="true" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" id="edit_bu_company_id" :required="true" select2-selector="default" :errorText="$errors->first('company_id')">
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_bu_status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" id="edit_bu_description" rows="3" :errorText="$errors->first('description')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.update_business_unit') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================ -->
<!--               BRANCHES MODALS                -->
<!-- ============================================ -->

<!-- View Branch Modal -->
<div class="modal fade" id="viewBranchModal" tabindex="-1" aria-labelledby="viewBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewBranchModalLabel"><i class="feather-info me-2"></i>{{ __('hrms.org.branch_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_branch_avatar" class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center fw-bold fs-16" style="width: 54px; height: 54px;">
                        BR
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_branch_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_branch_bu"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">{{ __('hrms.org.overview') }}</span>
                            <div class="mb-2"><strong class="fs-12 text-muted">{{ __('hrms.org.branch_code') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_code"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">{{ __('hrms.org.manager') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_manager"></span></div>
                            <div class="mb-0"><strong class="fs-12 text-muted">{{ __('hrms.org.status') }}:</strong> <span id="modal_view_branch_status"></span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">{{ __('hrms.org.contact_info') }}</span>
                            <div class="mb-2"><strong class="fs-12 text-muted">{{ __('hrms.org.phone') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_phone"></span></div>
                            <div class="mb-0"><strong class="fs-12 text-muted">{{ __('hrms.org.email') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_email"></span></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">{{ __('hrms.org.location_details') }}</span>
                            <div class="row g-2">
                                <div class="col-sm-3"><strong class="fs-12 text-muted">{{ __('hrms.org.country') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_country"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">{{ __('hrms.org.state') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_state"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">{{ __('hrms.org.city') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_city"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">{{ __('hrms.org.zip_code') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_zip"></span></div>
                                <div class="col-12 mt-2 pt-2 border-top"><strong class="fs-12 text-muted">{{ __('hrms.org.full_address') }}:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_address"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addBranchModalLabel"><i class="feather-plus me-2 text-primary"></i>{{ __('hrms.org.add_branch') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.branch.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.branch_name') }}" name="name" :required="true" placeholder="{{ __('hrms.org.branch_name') }}" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.branch_code') }}" name="code" :required="true" placeholder="{{ __('hrms.org.branch_code') }}" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                <option value="">{{ __('hrms.org.select_company_required') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_business_unit') }}" name="business_unit_id" select2-selector="default" :errorText="$errors->first('business_unit_id')">
                                <option value="">{{ __('hrms.org.select_business_unit') }}</option>
                                @foreach($businessUnits as $buUnit)
                                    <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.branch_manager') }}" name="manager_employee_id" select2-selector="default" :errorText="$errors->first('manager_employee_id')">
                                <option value="">{{ __('hrms.org.select_manager') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.phone') }}" name="phone" placeholder="{{ __('hrms.org.phone') }}" :errorText="$errors->first('phone')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.email') }}" name="email" inputType="email" placeholder="{{ __('hrms.org.email') }}" :errorText="$errors->first('email')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.country') }}" name="country" select2-selector="country" class="geo-country" :errorText="$errors->first('country')" data-initial-value="{{ old('country', 'United States') }}">
                                <option value="">{{ __('hrms.org.select_country') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.state') }}" name="state" select2-selector="default" class="geo-state" :errorText="$errors->first('state')">
                                <option value="">{{ __('hrms.org.select_state') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.city') }}" name="city" select2-selector="default" class="geo-city" :errorText="$errors->first('city')">
                                <option value="">{{ __('hrms.org.select_city') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.postal_code') }}" name="postal_code" placeholder="{{ __('hrms.org.postal_code') }}" :errorText="$errors->first('postal_code')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.address') }}" name="address" rows="3" placeholder="{{ __('hrms.org.address') }}" :errorText="$errors->first('address')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.save_branch') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editBranchModalLabel"><i class="feather-edit me-2 text-primary"></i>{{ __('hrms.assets.edit') }} {{ __('hrms.org.branches') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="branch_edit_form" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.branch_name') }}" name="name" id="edit_branch_name" :required="true" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.branch_code') }}" name="code" id="edit_branch_code" :required="true" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" id="edit_branch_company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                <option value="">{{ __('hrms.org.select_company_required') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_business_unit') }}" name="business_unit_id" id="edit_branch_bu_id" select2-selector="default" :errorText="$errors->first('business_unit_id')">
                                <option value="">{{ __('hrms.org.select_business_unit') }}</option>
                                @foreach($businessUnits as $buUnit)
                                    <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.branch_manager') }}" name="manager_employee_id" id="edit_branch_manager_id" select2-selector="default" :errorText="$errors->first('manager_employee_id')">
                                <option value="">{{ __('hrms.org.select_manager') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.phone') }}" name="phone" id="edit_branch_phone" :errorText="$errors->first('phone')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.email') }}" name="email" id="edit_branch_email" inputType="email" :errorText="$errors->first('email')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_branch_status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.country') }}" name="country" id="edit_branch_country" select2-selector="country" class="geo-country" :errorText="$errors->first('country')">
                                <option value="">{{ __('hrms.org.select_country') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.state') }}" name="state" id="edit_branch_state" select2-selector="default" class="geo-state" :errorText="$errors->first('state')">
                                <option value="">{{ __('hrms.org.select_state') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.city') }}" name="city" id="edit_branch_city" select2-selector="default" class="geo-city" :errorText="$errors->first('city')">
                                <option value="">{{ __('hrms.org.select_city') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.postal_code') }}" name="postal_code" id="edit_branch_postal_code" :errorText="$errors->first('postal_code')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.address') }}" name="address" id="edit_branch_address" rows="3" :errorText="$errors->first('address')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.update_branch') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================ -->
<!--              DEPARTMENTS MODALS              -->
<!-- ============================================ -->

<!-- View Dept Modal -->
<div class="modal fade" id="viewDeptModal" tabindex="-1" aria-labelledby="viewDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewDeptModalLabel"><i class="feather-info me-2"></i>{{ __('hrms.org.department_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_dept_avatar" class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center fw-bold fs-16" style="width: 54px; height: 54px;">
                        DP
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_dept_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_dept_branch"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.department_code') }}</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_dept_code"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.department_head') }}</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_dept_head"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.status') }}</label>
                        <div id="modal_view_dept_status"></div>
                    </div>
                    <div class="col-12 mt-2">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.description') }}</label>
                        <p class="fs-13 fw-semibold text-dark mb-0" id="modal_view_dept_desc"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Dept Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-labelledby="addDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addDeptModalLabel"><i class="feather-plus me-2 text-primary"></i>{{ __('hrms.org.add_department') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.department.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.department_name') }}" name="name" :required="true" placeholder="{{ __('hrms.org.department_name') }}" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.department_code') }}" name="code" :required="true" placeholder="{{ __('hrms.org.department_code') }}" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                <option value="">{{ __('hrms.org.select_company_required_dept') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_business_unit') }}" name="business_unit_id" select2-selector="default" :errorText="$errors->first('business_unit_id')">
                                <option value="">{{ __('hrms.org.select_business_unit') }}</option>
                                @foreach($businessUnits as $buUnit)
                                    <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_branch') }}" name="branch_id" select2-selector="default" :errorText="$errors->first('branch_id')">
                                <option value="">{{ __('hrms.org.select_branch') }}</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.department_head') }}" name="head_employee_id" select2-selector="default" :errorText="$errors->first('head_employee_id')">
                                <option value="">{{ __('hrms.org.department_head') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" rows="3" placeholder="{{ __('hrms.org.description') }}" :errorText="$errors->first('description')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.save_department') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Dept Modal -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-labelledby="editDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editDeptModalLabel"><i class="feather-edit me-2 text-primary"></i>{{ __('hrms.assets.edit') }} {{ __('hrms.org.departments') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="dept_edit_form" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.department_name') }}" name="name" id="edit_dept_name" :required="true" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.department_code') }}" name="code" id="edit_dept_code" :required="true" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" id="edit_dept_company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                <option value="">{{ __('hrms.org.select_company_required_dept') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_business_unit') }}" name="business_unit_id" id="edit_dept_bu_id" select2-selector="default" :errorText="$errors->first('business_unit_id')">
                                <option value="">{{ __('hrms.org.select_business_unit') }}</option>
                                @foreach($businessUnits as $buUnit)
                                    <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_branch') }}" name="branch_id" id="edit_dept_branch_id" select2-selector="default" :errorText="$errors->first('branch_id')">
                                <option value="">{{ __('hrms.org.select_branch') }}</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.department_head') }}" name="head_employee_id" id="edit_dept_head_id" select2-selector="default" :errorText="$errors->first('head_employee_id')">
                                <option value="">{{ __('hrms.org.department_head') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_dept_status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" id="edit_dept_description" rows="3" :errorText="$errors->first('description')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.update_department') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================ -->
<!--              DESIGNATIONS MODALS             -->
<!-- ============================================ -->

<!-- View Desig Modal -->
<div class="modal fade" id="viewDesigModal" tabindex="-1" aria-labelledby="viewDesigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewDesigModalLabel"><i class="feather-info me-2"></i>{{ __('hrms.org.designation_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_desig_avatar" class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center fw-bold fs-16" style="width: 54px; height: 54px;">
                        DS
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_desig_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_desig_dept"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.grade_level') }}</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_desig_level"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.status') }}</label>
                        <div id="modal_view_desig_status"></div>
                    </div>
                    <div class="col-12 mt-2">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.description') }}</label>
                        <p class="fs-13 fw-semibold text-dark mb-0" id="modal_view_desig_desc"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Desig Modal -->
<div class="modal fade" id="addDesigModal" tabindex="-1" aria-labelledby="addDesigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addDesigModalLabel"><i class="feather-plus me-2 text-primary"></i>{{ __('hrms.org.add_designation') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.designation.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.designation_name') }}" name="name" :required="true" placeholder="{{ __('hrms.org.designation_name') }}" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.grade_level') }}" name="level" placeholder="{{ __('hrms.org.grade_level') }}" :errorText="$errors->first('level')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_department') }}" name="department_id" :required="true" select2-selector="default" :errorText="$errors->first('department_id')">
                                <option value="">{{ __('hrms.org.select_department') }}</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" rows="3" placeholder="{{ __('hrms.org.description') }}" :errorText="$errors->first('description')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.save_designation') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Desig Modal -->
<div class="modal fade" id="editDesigModal" tabindex="-1" aria-labelledby="editDesigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editDesigModalLabel"><i class="feather-edit me-2 text-primary"></i>{{ __('hrms.assets.edit') }} {{ __('hrms.org.designations') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="desig_edit_form" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.designation_name') }}" name="name" id="edit_desig_name" :required="true" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.grade_level') }}" name="level" id="edit_desig_level" :errorText="$errors->first('level')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_department') }}" name="department_id" id="edit_desig_dept_id" :required="true" select2-selector="default" :errorText="$errors->first('department_id')">
                                <option value="">{{ __('hrms.org.select_department') }}</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_desig_status" select2-selector="default" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" id="edit_desig_description" rows="3" :errorText="$errors->first('description')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.update_designation') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Salary Component Modal -->
<div class="modal fade" id="addSalaryComponentModal" tabindex="-1" aria-labelledby="addSalaryComponentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addSalaryComponentModalLabel"><i class="feather-plus me-2 text-primary"></i>{{ __('hrms.org.add_salary_component') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ request()->routeIs('hrms.salary-structure.index') ? route('hrms.salary-structure.store') : route('hrms.salary-component.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.component_name') }}" name="name" :required="true" placeholder="{{ __('hrms.org.component_name_placeholder') }}" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.tbl_code') }}" name="code" :required="true" placeholder="{{ __('hrms.org.code_placeholder') }}" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.type') }}" name="type" :required="true" select2-selector="default" :errorText="$errors->first('type')">
                                <option value="earning">{{ __('hrms.org.earning') }}</option>
                                <option value="deduction">{{ __('hrms.org.deduction') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <input type="hidden" name="calculation_type" value="fixed">
                        <input type="hidden" name="pay_group_id" id="add_component_pay_group_id" value="{{ isset($selectedPayGroup) && $selectedPayGroup ? $selectedPayGroup->id : '' }}">
                        <input type="hidden" name="is_adhoc" id="add_component_is_adhoc" value="0">
                        <input type="hidden" name="company_id" id="add_sc_company_id">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="status" :errorText="$errors->first('status')">
                                <option value="1" data-bg="bg-success" selected>{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0" data-bg="bg-danger">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" rows="3" placeholder="{{ __('hrms.org.description') }}" :errorText="$errors->first('description')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.save_component') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Salary Component Modal -->
<div class="modal fade" id="editSalaryComponentModal" tabindex="-1" aria-labelledby="editSalaryComponentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editSalaryComponentModalLabel"><i class="feather-edit me-2 text-primary"></i>{{ __('hrms.org.edit_salary_component') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="salary_component_edit_form" method="POST" data-update-route="{{ request()->routeIs('hrms.salary-structure.index') ? route('hrms.salary-structure.update', ['salaryComponent' => '__ID__']) : route('hrms.salary-component.update', ['salaryComponent' => '__ID__']) }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.component_name') }}" name="name" id="edit_sc_name" :required="true" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.tbl_code') }}" name="code" id="edit_sc_code" :required="true" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.type') }}" name="type" id="edit_sc_type" :required="true" select2-selector="default" :errorText="$errors->first('type')">
                                <option value="earning">{{ __('hrms.org.earning') }}</option>
                                <option value="deduction">{{ __('hrms.org.deduction') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <input type="hidden" name="calculation_type" id="edit_sc_calculation_type" value="fixed">
                        <input type="hidden" name="pay_group_id" id="edit_sc_pay_group_id">
                        <input type="hidden" name="is_adhoc" id="edit_sc_is_adhoc" value="0">
                        <input type="hidden" name="company_id" id="edit_sc_company_id">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_sc_status" select2-selector="status" :errorText="$errors->first('status')">
                                <option value="1" data-bg="bg-success">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0" data-bg="bg-danger">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" id="edit_sc_description" rows="3" :errorText="$errors->first('description')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.update_component') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!--              PAY GROUPS MODALS               -->
<!-- ============================================ -->

<!-- Add Pay Group Modal -->
<div class="modal fade" id="addPayGroupModal" tabindex="-1" aria-labelledby="addPayGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addPayGroupModalLabel"><i class="feather-plus me-2 text-primary"></i>{{ __('hrms.org.add_pay_group') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.salary-structure.pay-group.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.pay_group_name') }}" name="name" :required="true" placeholder="{{ __('hrms.org.pay_group_name_placeholder') }}" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" select2-selector="default">
                                <option value="">{{ __('hrms.org.apply_to_all_companies') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="status">
                                <option value="1" data-bg="bg-success" selected>{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0" data-bg="bg-danger">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" rows="3" placeholder="{{ __('hrms.org.pay_group_desc_placeholder') }}" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.save_pay_group') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Pay Group Modal -->
<div class="modal fade" id="editPayGroupModal" tabindex="-1" aria-labelledby="editPayGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editPayGroupModalLabel"><i class="feather-edit me-2 text-primary"></i>{{ __('hrms.org.edit_pay_group') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPayGroupForm" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.pay_group_name') }}" name="name" id="edit_pg_name" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" id="edit_pg_company_id" select2-selector="default">
                                <option value="">{{ __('hrms.org.apply_to_all_companies') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_pg_status" select2-selector="status">
                                <option value="1" data-bg="bg-success">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0" data-bg="bg-danger">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" id="edit_pg_description" rows="3" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.update_pay_group') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!--                 SHIFT MODALS                 -->
<!-- ============================================ -->

<!-- View Shift Modal -->
<div class="modal fade" id="viewShiftModal" tabindex="-1" aria-labelledby="viewShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewShiftModalLabel"><i class="feather-clock me-2"></i>{{ __('hrms.org.shift_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div class="avatar-text avatar-xl rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center fw-bold fs-20" style="width: 56px; height: 56px; min-width: 56px; min-height: 56px;">
                        <i class="feather-clock"></i>
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_shift_name"></h4>
                        <span class="fs-12 text-muted font-monospace" id="modal_view_shift_code"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Company</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_shift_company"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.start_time') }}</label>
                        <span class="fs-13 fw-bold text-dark font-monospace" id="modal_view_shift_start"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.end_time') }}</label>
                        <span class="fs-13 fw-bold text-dark font-monospace" id="modal_view_shift_end"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.break_minutes') }}</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_shift_break"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.overtime_allowed') }}</label>
                        <div id="modal_view_shift_overtime"></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">{{ __('hrms.org.status') }}</label>
                        <div id="modal_view_shift_status"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Shift Modal -->
<div class="modal fade" id="addShiftModal" tabindex="-1" aria-labelledby="addShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addShiftModalLabel"><i class="feather-plus me-2 text-primary"></i>{{ __('hrms.org.add_shift') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.shift.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Company (Legal Entity)" name="company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                <option value="">Shared (All Companies)</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.shift_name') }}" name="name" :required="true" placeholder="{{ __('hrms.org.shift_name_placeholder') }}" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.shift_code') }}" name="code" :required="true" placeholder="{{ __('hrms.org.shift_code_placeholder') }}" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.start_time') }}" name="start_time" :required="true" placeholder="{{ __('hrms.org.start_time_placeholder') }}" :errorText="$errors->first('start_time')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.end_time') }}" name="end_time" :required="true" placeholder="{{ __('hrms.org.end_time_placeholder') }}" :errorText="$errors->first('end_time')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.break_minutes') }}" name="break_minutes" inputType="number" :required="true" placeholder="{{ __('hrms.org.break_minutes_placeholder') }}" value="0" :errorText="$errors->first('break_minutes')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.overtime_allowed') }}" name="overtime_allowed" select2-selector="default" :errorText="$errors->first('overtime_allowed')">
                                <option value="1">{{ __('hrms.common.yes') }}</option>
                                <option value="0" selected>{{ __('hrms.common.no') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="active" select2-selector="default" :errorText="$errors->first('active')">
                                <option value="1" selected>{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.save_shift') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Shift Modal -->
<div class="modal fade" id="editShiftModal" tabindex="-1" aria-labelledby="editShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editShiftModalLabel"><i class="feather-edit me-2 text-primary"></i>{{ __('hrms.assets.edit') }} {{ __('hrms.org.shifts') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="shift_edit_form" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Company (Legal Entity)" name="company_id" id="edit_shift_company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                <option value="">Shared (All Companies)</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.shift_name') }}" name="name" id="edit_shift_name" :required="true" :errorText="$errors->first('name')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.shift_code') }}" name="code" id="edit_shift_code" :required="true" :errorText="$errors->first('code')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.start_time') }}" name="start_time" id="edit_shift_start" :required="true" :errorText="$errors->first('start_time')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.end_time') }}" name="end_time" id="edit_shift_end" :required="true" :errorText="$errors->first('end_time')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.break_minutes') }}" name="break_minutes" id="edit_shift_break" inputType="number" :required="true" :errorText="$errors->first('break_minutes')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.overtime_allowed') }}" name="overtime_allowed" id="edit_shift_overtime" select2-selector="default" :errorText="$errors->first('overtime_allowed')">
                                <option value="1">{{ __('hrms.common.yes') }}</option>
                                <option value="0">{{ __('hrms.common.no') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="active" id="edit_shift_active" select2-selector="default" :errorText="$errors->first('active')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('hrms.org.update_shift') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
