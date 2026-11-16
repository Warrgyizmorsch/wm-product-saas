<x-ui.modal id="milestoneModal" title="{{ __('projects.add_milestone') }}" size="lg" :scrollable="true" :static="true" :showFooter="false">
    <form id="milestoneForm" method="POST" action="{{ isset($project) ? route('projects.milestones.store', $project) : '' }}">
        @csrf
        <input type="hidden" name="_method" id="milestoneMethodField" value="POST">
        @include('modules.projects.milestones._form')

        <div class="d-flex gap-2 justify-content-end pt-3 border-top mt-4">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" id="milestoneSubmitBtn" class="btn btn-primary px-4">{{ __('projects.create') }}</button>
        </div>
    </form>
</x-ui.modal>

<script>
    function openMilestoneModal(mode, data) {
        data = data || {};

        var form = document.getElementById('milestoneForm');
        var methodField = document.getElementById('milestoneMethodField');
        var modeField = document.getElementById('milestone_form_mode');
        var idField = document.getElementById('milestone_form_id');
        var titleEl = document.getElementById('milestoneModalLabel');
        var submitBtn = document.getElementById('milestoneSubmitBtn');

        if (mode === 'edit') {
            form.action = data.updateUrl;
            methodField.value = 'PUT';
            modeField.value = 'edit';
            idField.value = data.id;
            if (titleEl) titleEl.textContent = @js(__('projects.edit_milestone'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.save_changes'));
        } else {
            form.action = data.storeUrl || @js(isset($project) ? route('projects.milestones.store', $project) : '');
            methodField.value = 'POST';
            modeField.value = 'add';
            idField.value = '';
            if (titleEl) titleEl.textContent = @js(__('projects.add_milestone'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.create'));
        }

        // A fresh "Add Milestone" click passes no data at all; cloning/editing
        // always passes explicit (possibly empty) date keys, so only a truly
        // fresh add defaults the dates to today.
        var isFreshAdd = mode !== 'edit' && !('startDate' in data) && !('dueDate' in data);
        var today = new Date().toISOString().slice(0, 10);

        setMilestoneFieldValue('milestone_name', data.name || '');
        setMilestoneFieldValue('milestone_description', data.description || '');
        setMilestoneFieldValue('milestone_start_date', isFreshAdd ? today : (data.startDate || ''));
        setMilestoneFieldValue('milestone_due_date', isFreshAdd ? today : (data.dueDate || ''));
        setMilestoneFieldValue('milestone_status', data.status || 'Draft');
        setMilestoneFieldValue('milestone_completion_percentage', mode === 'edit' ? (data.completionPercentage ?? 0) : 0);
        setMilestoneFieldValue('milestone_billing_amount', data.billingAmount !== undefined && data.billingAmount !== null ? data.billingAmount : '');
        syncMilestoneShareFromAmount();
        setMilestoneOwner(data.ownerId);

        var modalEl = document.getElementById('milestoneModal');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function syncMilestoneShareFromAmount() {
        var billingInput = document.getElementById('milestone_billing_amount');
        var shareInput = document.getElementById('milestone_billing_percentage') || document.getElementById('milestone_share_percentage');
        if (!billingInput || !shareInput) return;
        var budget = parseFloat(shareInput.getAttribute('data-budget') || '0');
        if (budget > 0 && billingInput.value !== '') {
            var val = parseFloat(billingInput.value) || 0;
            var pct = (val / budget) * 100;
            shareInput.value = pct > 0 ? (pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(1)) : '';
        } else {
            shareInput.value = '';
        }
        updateCalcDisplay();
    }

    function syncMilestoneAmountFromShare() {
        var billingInput = document.getElementById('milestone_billing_amount');
        var shareInput = document.getElementById('milestone_billing_percentage') || document.getElementById('milestone_share_percentage');
        if (!billingInput || !shareInput) return;
        var budget = parseFloat(shareInput.getAttribute('data-budget') || '0');
        if (budget > 0 && shareInput.value !== '') {
            var pct = parseFloat(shareInput.value) || 0;
            var amt = (pct / 100) * budget;
            billingInput.value = amt.toFixed(2);
        } else {
            billingInput.value = '0';
        }
        updateCalcDisplay();
    }

    function updateCalcDisplay() {
        var calcEl = document.getElementById('milestone_calc_display');
        var billingInput = document.getElementById('milestone_billing_amount');
        if (!calcEl || !billingInput) return;
        var amt = parseFloat(billingInput.value) || 0;
        var formatted = (window.AppCurrency && typeof window.AppCurrency.format === 'function')
            ? window.AppCurrency.format(amt)
            : '₹' + amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        calcEl.innerHTML = '<i class="feather-info me-1"></i>Amount: ' + formatted;
    }

    function setMilestoneFieldValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    (function () {
        var completionInput = document.getElementById('milestone_completion_percentage');
        if (completionInput) {
            completionInput.addEventListener('input', function () {
                if (completionInput.value === '') return;

                var clamped = Math.min(100, Math.max(0, Number(completionInput.value)));
                if (!Number.isNaN(clamped) && Number(completionInput.value) !== clamped) {
                    completionInput.value = clamped;
                }
            });
        }

        var statusSelect = document.getElementById('milestone_status');
        if (statusSelect && completionInput) {
            statusSelect.addEventListener('change', function () {
                if (statusSelect.value === 'Completed') {
                    completionInput.value = 100;
                }
            });
        }

        var billingInput = document.getElementById('milestone_billing_amount');
        var shareInput = document.getElementById('milestone_billing_percentage') || document.getElementById('milestone_share_percentage');
        if (shareInput) {
            shareInput.addEventListener('input', syncMilestoneAmountFromShare);
        }
        if (billingInput) {
            billingInput.addEventListener('input', syncMilestoneShareFromAmount);
        }

        var form = document.getElementById('milestoneForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                var action = form.getAttribute('action') || form.action;
                if (!action || action === '' || action === window.location.href) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    })();

    function setMilestoneOwner(value) {
        var el = document.getElementById('milestone_owner_id');
        if (!el) return;
        el.value = value || '';
        if (window.jQuery && jQuery(el).data('select2')) {
            jQuery(el).trigger('change');
        }
    }
</script>
