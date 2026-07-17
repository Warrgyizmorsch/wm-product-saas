@extends('layouts.duralux')

@section('title', __('hrms.org_create.create_dept_title') . ' | SaaS ERP')
@section('page-title', __('hrms.org_create.create_dept_title'))
@section('breadcrumb', 'HRMS / Org Structure / Departments / ' . __('hrms.org_create.create_dept_title'))

@section('page-actions')
    <x-ui.button href="{{ route('hrms.org.index', ['tab' => 'departments']) }}" variant="light" icon="feather-arrow-left">
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

        // Dynamic filtering of Department Head by branch/business unit/company
        const companySelect = document.getElementById('company_id');
        const buSelect = document.getElementById('business_unit_id');
        const branchSelect = document.getElementById('branch_id');
        const headSelect = document.getElementById('head_employee_id');

        if (companySelect && buSelect && branchSelect && headSelect) {
            const $company = $(companySelect);
            const $bu = $(buSelect);
            const $branch = $(branchSelect);
            const $head = $(headSelect);

            function filterDepartmentHead() {
                const companyId = $company.val();
                const buId = $bu.val();
                const branchId = $branch.val();

                let originalOptions = $head.data('original-options');
                if (!originalOptions) {
                    originalOptions = $head.find('option').clone();
                    $head.data('original-options', originalOptions);
                }

                const currentSelected = $head.val();
                $head.empty();

                originalOptions.each(function () {
                    const opt = $(this);
                    const optVal = opt.val();
                    const optionBranchId = opt.attr('data-branch-id');
                    const optionBuId = opt.attr('data-business-unit-id');
                    const optionCompId = opt.attr('data-company-id');

                    if (!optVal) {
                        $head.append(opt.clone());
                        return;
                    }

                    if (branchId) {
                        if (String(optionBranchId) === String(branchId)) {
                            $head.append(opt.clone());
                        }
                    } else if (buId) {
                        if (String(optionBuId) === String(buId)) {
                            $head.append(opt.clone());
                        }
                    } else if (companyId) {
                        if (String(optionCompId) === String(companyId)) {
                            $head.append(opt.clone());
                        }
                    } else {
                        // All unselected -> show all
                        $head.append(opt.clone());
                    }
                });

                if ($head.find(`option[value="${currentSelected}"]`).length) {
                    $head.val(currentSelected);
                } else {
                    $head.val('');
                }

                if ($head.hasClass('select2-hidden-accessible')) {
                    $head.trigger('change.select2');
                }
            }

            // Hierarchical filters for BU and branch selects
            function filterOrgDropdowns() {
                const companyId = $company.val();
                const buId = $bu.val();

                // Filter business units by company
                let originalBUs = $bu.data('original-options');
                if (!originalBUs) {
                    originalBUs = $bu.find('option').clone();
                    $bu.data('original-options', originalBUs);
                }
                const currentBU = $bu.val();
                $bu.empty();
                originalBUs.each(function () {
                    const opt = $(this);
                    if (!opt.val() || !companyId || String(opt.attr('data-company-id')) === String(companyId)) {
                        $bu.append(opt.clone());
                    }
                });
                if ($bu.find(`option[value="${currentBU}"]`).length) {
                    $bu.val(currentBU);
                } else {
                    $bu.val('');
                }
                if ($bu.hasClass('select2-hidden-accessible')) {
                    $bu.trigger('change.select2');
                }

                // Filter branches by business unit or company
                let originalBranches = $branch.data('original-options');
                if (!originalBranches) {
                    originalBranches = $branch.find('option').clone();
                    $branch.data('original-options', originalBranches);
                }
                const currentBranch = $branch.val();
                $branch.empty();
                originalBranches.each(function () {
                    const opt = $(this);
                    if (!opt.val()) {
                        $branch.append(opt.clone());
                        return;
                    }
                    if (buId) {
                        if (String(opt.attr('data-business-unit-id')) === String(buId)) {
                            $branch.append(opt.clone());
                        }
                    } else if (companyId) {
                        if (String(opt.attr('data-company-id')) === String(companyId)) {
                            $branch.append(opt.clone());
                        }
                    } else {
                        $branch.append(opt.clone());
                    }
                });
                if ($branch.find(`option[value="${currentBranch}"]`).length) {
                    $branch.val(currentBranch);
                } else {
                    $branch.val('');
                }
                if ($branch.hasClass('select2-hidden-accessible')) {
                    $branch.trigger('change.select2');
                }
            }

            $company.on('change', function () {
                filterOrgDropdowns();
                filterDepartmentHead();
            });
            $bu.on('change', function () {
                filterOrgDropdowns();
                filterDepartmentHead();
            });
            $branch.on('change', filterDepartmentHead);

            // Trigger initially
            setTimeout(function () {
                filterOrgDropdowns();
                filterDepartmentHead();
            }, 100);
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
                        <form action="{{ route('hrms.department.store') }}" method="POST">
                            @csrf
                            <div class="mb-4 d-flex align-items-center justify-content-between">
                                <h5 class="fw-bold mb-0 me-4">
                                    <span class="d-block mb-2">{{ __('hrms.org_create.dept_info') }}:</span>
                                </h5>
                                <x-ui.button type="submit" variant="light-brand" size="lg">{{ __('hrms.org_create.add_new') }}</x-ui.button>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.department_name') }}" name="name" id="name" placeholder="{{ __('hrms.org.department_name') }}" :required="true" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.org.department_code') }}" name="code" id="code" placeholder="{{ __('hrms.org.department_code') }}" :required="true" />
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
                                            <option value="{{ $buUnit->id }}" data-company-id="{{ $buUnit->company_id }}">{{ $buUnit->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.parent_branch') }}" name="branch_id" id="branch_id">
                                        <option value="">{{ __('hrms.org.select_branch') }}</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" data-company-id="{{ $branch->company_id }}" data-business-unit-id="{{ $branch->business_unit_id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.department_head') }}" name="head_employee_id" id="head_employee_id">
                                        <option value="">{{ __('hrms.org.department_head') }}</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}" data-company-id="{{ $employee->company_id }}" data-business-unit-id="{{ $employee->business_unit_id }}" data-branch-id="{{ $employee->branch_id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="status" :required="true">
                                        <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                        <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.org.description') }}" name="description" id="description" placeholder="{{ __('hrms.org.description') }}" rows="4" />
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
