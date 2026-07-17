@extends('layouts.duralux')

@section('title', __('hrms.org_create.create_branch_title') . ' | SaaS ERP')
@section('page-title', __('hrms.org_create.create_branch_title'))
@section('breadcrumb', 'HRMS / Org Structure / Branches / ' . __('hrms.org_create.create_branch_title'))

@section('page-actions')
    <x-ui.button href="{{ route('hrms.org.index', ['tab' => 'branches']) }}" variant="light" icon="feather-arrow-left">
        {{ __('hrms.org_create.back_to_org') }}
    </x-ui.button>
@endsection

@push('scripts')
<script>
(function () {
    if (window.hrmsThemedValidationInstalled) return;
    window.hrmsThemedValidationInstalled = true;
    function fieldLabel(field) { return (field.closest('.odoo-form-group')?.querySelector('.odoo-form-label')?.textContent || 'This field').replace('*', '').trim().toLowerCase(); }
    function fieldAnchor(field) { return field.tagName === 'SELECT' && field.nextElementSibling?.classList.contains('select2-container') ? field.nextElementSibling : field; }
    function showError(field) {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        const anchor = fieldAnchor(field);
        let error = anchor.nextElementSibling;
        if (!error || !error.classList.contains('hrms-client-validation-error')) {
            error = document.createElement('div');
            error.className = 'invalid-feedback d-block fs-11 mt-1 hrms-client-validation-error';
            anchor.insertAdjacentElement('afterend', error);
        }
        error.textContent = field.validity.valueMissing ? (field.tagName === 'SELECT' ? `Please select ${fieldLabel(field)}.` : `Please enter ${fieldLabel(field)}.`) : (field.validationMessage || 'Please enter a valid value.');
    }
    function clearError(field) { field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid'); const error = fieldAnchor(field).nextElementSibling; if (error?.classList.contains('hrms-client-validation-error')) error.remove(); }
    function requiredFields(form) { return Array.from(form.querySelectorAll('[required]')).filter(field => !field.disabled && field.type !== 'hidden'); }
    function validate(field) { if (field.checkValidity()) { clearError(field); return true; } showError(field); return false; }
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.hrmsThemedValidation === '1' || !form.querySelector('[required]')) return;
            form.dataset.hrmsThemedValidation = '1';
            form.setAttribute('novalidate', 'novalidate');
            requiredFields(form).forEach(field => { field.addEventListener('input', () => validate(field)); field.addEventListener('change', () => validate(field)); });
            form.addEventListener('submit', function (event) {
                const invalid = requiredFields(form).find(field => !validate(field));
                if (!invalid) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                fieldAnchor(invalid).scrollIntoView({ behavior: 'smooth', block: 'center' });
                invalid.focus({ preventScroll: true });
            });
        });

        // Dynamic filtering of Branch Manager by Business Unit (or Company if no BU is selected)
        const buSelect = document.getElementById('business_unit_id');
        const companySelect = document.getElementById('company_id');
        const managerSelect = document.getElementById('manager_employee_id');

        if (buSelect && companySelect && managerSelect) {
            const $bu = $(buSelect);
            const $company = $(companySelect);
            const $manager = $(managerSelect);

            function filterBranchManagers() {
                const buId = $bu.val();
                const companyId = $company.val();
                
                let originalOptions = $manager.data('original-options');
                if (!originalOptions) {
                    originalOptions = $manager.find('option').clone();
                    $manager.data('original-options', originalOptions);
                }

                const currentSelected = $manager.val();
                $manager.empty();

                originalOptions.each(function () {
                    const opt = $(this);
                    const optVal = opt.val();
                    const optionBuId = opt.attr('data-business-unit-id');
                    const optionCompId = opt.attr('data-company-id');

                    if (!optVal) {
                        $manager.append(opt.clone());
                        return;
                    }

                    if (!buId && !companyId) {
                        $manager.append(opt.clone());
                    } else if (buId) {
                        if (String(optionBuId) === String(buId)) {
                            $manager.append(opt.clone());
                        }
                    } else if (companyId) {
                        if (String(optionCompId) === String(companyId)) {
                            $manager.append(opt.clone());
                        }
                    }
                });

                if ($manager.find(`option[value="${currentSelected}"]`).length) {
                    $manager.val(currentSelected);
                } else {
                    $manager.val('');
                }

                if ($manager.hasClass('select2-hidden-accessible')) {
                    $manager.trigger('change.select2');
                }
            }

            $bu.on('change', filterBranchManagers);
            $company.on('change', filterBranchManagers);

            // Trigger initially
            setTimeout(filterBranchManagers, 100);
        }
    });
})();
</script>
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="card border-top-0">
                <div>
                    <div class="card-body personal-info">
                        <form action="{{ route('hrms.branch.store') }}" method="POST">
                            @csrf
                            <div class="mb-4 d-flex align-items-center justify-content-between">
                                <h5 class="fw-bold mb-0 me-4">
                                    <span class="d-block mb-2">{{ __('hrms.org_create.branch_info') }}:</span>
                                </h5>
                                <x-ui.button type="submit" variant="light-brand" size="lg">{{ __('hrms.org_create.add_new') }}</x-ui.button>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.branch_name') }}" name="name" id="name" placeholder="{{ __('hrms.org.branch_name') }}" :required="true" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.branch_code') }}" name="code" id="code" placeholder="{{ __('hrms.org.branch_code') }}" :required="true" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_company') }}" name="company_id" id="company_id" :required="true">
                                        <option value="">{{ __('hrms.org.parent_company') }}</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_business_unit') }}" name="business_unit_id" id="business_unit_id">
                                        <option value="">{{ __('hrms.org.select_business_unit') }}</option>
                                        @foreach($businessUnits as $buUnit)
                                            <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.branch_manager') }}" name="manager_employee_id" id="manager_employee_id">
                                        <option value="">{{ __('hrms.org.select_manager') }}</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" data-company-id="{{ $employee->company_id }}" data-business-unit-id="{{ $employee->business_unit_id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.phone') }}" name="phone" id="phone" placeholder="{{ __('hrms.org.phone') }}" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.email') }}" name="email" id="email" inputType="email" placeholder="{{ __('hrms.org.email') }}" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.country') }}" name="country" id="country" select2-selector="country" class="geo-country" data-initial-value="{{ old('country', 'United States') }}">
                                        <option value="">{{ __('hrms.org.select_country') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.state') }}" name="state" id="state" select2-selector="default" class="geo-state">
                                        <option value="">{{ __('hrms.org.select_state') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.city') }}" name="city" id="city" select2-selector="default" class="geo-city">
                                        <option value="">{{ __('hrms.org.select_city') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.postal_code') }}" name="postal_code" id="postal_code" placeholder="{{ __('hrms.org.postal_code') }}" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="status" :required="true">
                                        <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                        <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.address') }}" name="address" id="address" placeholder="{{ __('hrms.org.address') }}" rows="3" />
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
