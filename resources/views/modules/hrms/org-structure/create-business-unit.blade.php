@extends('layouts.duralux')

@section('title', 'ORG STRUCTURE | SaaS ERP')
@section('page-title', 'Create Business Unit')
@section('breadcrumb', 'HRMS / Org Structure / Business Units / Create')

@section('page-actions')
    <x-ui.button href="{{ route('hrms.org.index') }}" variant="light" icon="feather-arrow-left">
        Back to Org Structure
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
                        <form action="{{ route('hrms.business-unit.store') }}" method="POST">
                            @csrf
                            <div class="mb-4 d-flex align-items-center justify-content-between">
                                <h5 class="fw-bold mb-0 me-4">
                                    <span class="d-block mb-2">Business Unit Information:</span>
                                </h5>
                                <x-ui.button type="submit" variant="light-brand" size="lg">Add New</x-ui.button>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="Business Unit Name" name="name" id="name" placeholder="Enter Business Unit Name" :required="true" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="Business Unit Code" name="code" id="code" placeholder="Enter Business Unit Code" :required="true" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="Parent Company" name="company_id" id="company_id" :required="true">
                                        <option value="">Select Company</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="Unit Head" name="head_employee_id" id="head_employee_id">
                                        <option value="">Select Unit Head</option>
                                        @foreach($employees as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="Status" name="status" id="status" :required="true">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="description" placeholder="Enter description..." rows="4" />
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
