(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var optionsHolder = document.getElementById('contractAmendOptions');
        var modalEl = document.getElementById('modalAmendContractSetting');
        if (!optionsHolder || !modalEl || typeof bootstrap === 'undefined') return;

        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var amendUrl = optionsHolder.dataset.amendUrl;
        var billingMethods = JSON.parse(optionsHolder.dataset.billingMethods || '{}');
        var budgetTypes = JSON.parse(optionsHolder.dataset.budgetTypes || '{}');
        var clients = JSON.parse(optionsHolder.dataset.clients || '{}');
        var noneLabel = optionsHolder.dataset.noneOption || '— None —';

        var fieldLabelEl = document.getElementById('modalAmendFieldLabel');
        var currentBadgeEl = document.getElementById('modalAmendCurrentValueBadge');
        var newValueSelect = document.getElementById('modalAmendNewValueSelect');
        var noticeTextEl = document.getElementById('modalAmendNoticeText');
        var reasonInput = document.getElementById('modalAmendReasonInput');
        var reasonError = document.getElementById('modalAmendReasonError');
        var confirmCheck = document.getElementById('modalAmendConfirmCheck');
        var serverError = document.getElementById('modalAmendServerError');
        var submitBtn = document.getElementById('btnConfirmAmendContract');

        var currentField = null;
        var currentValue = null;
        var isSubmitting = false;

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.contract-amend-btn');
            if (!btn) return;
            e.preventDefault();

            var tt = bootstrap.Tooltip.getInstance(btn);
            if (tt) tt.hide();

            currentField = btn.dataset.field;
            currentValue = btn.dataset.currentValue || '';
            var fieldLabel = btn.dataset.label || 'Setting';
            var currentLabel = btn.dataset.currentLabel || noneLabel;

            fieldLabelEl.textContent = fieldLabel;
            currentBadgeEl.textContent = currentLabel;
            currentBadgeEl.title = currentLabel;

            // Notice text
            if (currentField === 'billing_method') {
                noticeTextEl.textContent = optionsHolder.dataset.noticeBillingMethod;
            } else if (currentField === 'budget_type') {
                noticeTextEl.textContent = optionsHolder.dataset.noticeBudgetType;
            } else if (currentField === 'customer_id') {
                noticeTextEl.textContent = optionsHolder.dataset.noticeCustomer;
            } else {
                noticeTextEl.textContent = optionsHolder.dataset.noticeGeneral;
            }

            // Populate options
            newValueSelect.innerHTML = '';
            var optMap = {};
            if (currentField === 'billing_method') {
                optMap = billingMethods;
            } else if (currentField === 'budget_type') {
                optMap = budgetTypes;
            } else if (currentField === 'customer_id') {
                optMap = clients;
            }

            var defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = '— Select New ' + fieldLabel + ' —';
            defaultOpt.disabled = true;
            defaultOpt.selected = true;
            newValueSelect.appendChild(defaultOpt);

            for (var key in optMap) {
                if (optMap.hasOwnProperty(key)) {
                    var opt = document.createElement('option');
                    opt.value = key;
                    opt.textContent = optMap[key];
                    if (String(key) === String(currentValue)) {
                        opt.disabled = true;
                        opt.textContent += ' (Current)';
                    }
                    newValueSelect.appendChild(opt);
                }
            }

            // Reset inputs
            newValueSelect.value = '';
            reasonInput.value = '';
            confirmCheck.checked = false;
            reasonError.classList.add('d-none');
            serverError.classList.add('d-none');
            submitBtn.disabled = true;

            modal.show();
        });

        function validateForm() {
            var val = newValueSelect.value;
            var reason = reasonInput.value.trim();
            var isChecked = confirmCheck.checked;

            var isValid = (val !== '' && String(val) !== String(currentValue))
                && reason.length >= 5
                && isChecked;

            submitBtn.disabled = !isValid || isSubmitting;
        }

        newValueSelect.addEventListener('change', validateForm);
        reasonInput.addEventListener('input', function () {
            if (reasonInput.value.trim().length > 0 && reasonInput.value.trim().length < 5) {
                reasonError.classList.remove('d-none');
            } else {
                reasonError.classList.add('d-none');
            }
            validateForm();
        });
        confirmCheck.addEventListener('change', validateForm);

        submitBtn.addEventListener('click', function () {
            if (submitBtn.disabled || isSubmitting) return;

            var val = newValueSelect.value;
            var reason = reasonInput.value.trim();

            if (!val || String(val) === String(currentValue) || reason.length < 5 || !confirmCheck.checked) {
                return;
            }

            isSubmitting = true;
            submitBtn.disabled = true;
            var origHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
            serverError.classList.add('d-none');

            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            csrfToken = csrfToken ? csrfToken.content : '';

            fetch(amendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    field: currentField,
                    value: val,
                    reason: reason,
                }),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, status: response.status, data: data };
                    });
                })
                .then(function (res) {
                    isSubmitting = false;
                    submitBtn.innerHTML = origHtml;

                    if (!res.ok) {
                        var msg = (res.data && res.data.message) || 'Failed to amend contract setting.';
                        serverError.textContent = msg;
                        serverError.classList.remove('d-none');
                        submitBtn.disabled = false;
                        return;
                    }

                    modal.hide();
                    if (window.showAppToast) {
                        window.showAppToast('success', res.data.message || 'Contract setting successfully amended.');
                    }

                    if (res.data && res.data.should_reload) {
                        setTimeout(function () {
                            window.location.reload();
                        }, 400);
                    }
                })
                .catch(function () {
                    isSubmitting = false;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                    serverError.textContent = 'Network or server error occurred. Please try again.';
                    serverError.classList.remove('d-none');
                });
        });
    });
})();

