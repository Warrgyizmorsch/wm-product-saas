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

                // Show validation errors under inputs in the theme's style
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
