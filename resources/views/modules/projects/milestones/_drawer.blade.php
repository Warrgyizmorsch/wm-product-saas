<x-ui.drawer id="milestoneDrawer" title="{{ __('projects.add_milestone') }}" position="end" style="width: 480px; max-width: 100%;">
    <div id="milestoneActionsBar" class="d-none justify-content-end gap-2 mb-3">
        <button type="button" class="btn btn-light-brand btn-sm" onclick="cloneCurrentMilestone()">
            <i class="feather feather-copy me-1"></i>{{ __('projects.clone') }}
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteCurrentMilestone()">
            <i class="feather feather-trash-2 me-1"></i>{{ __('projects.remove') }}
        </button>
    </div>

    <form id="milestoneForm" method="POST" action="{{ isset($project) ? route('projects.milestones.store', $project) : '' }}">
        @csrf
        <input type="hidden" name="_method" id="milestoneMethodField" value="POST">
        @include('modules.projects.milestones._form')
    </form>

    <form id="milestoneDeleteForm" method="POST" action="" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <x-slot name="footer">
        <button type="button" class="btn btn-light-brand" data-bs-dismiss="offcanvas">{{ __('projects.cancel') }}</button>
        <button type="submit" form="milestoneForm" id="milestoneSubmitBtn" class="btn btn-primary">{{ __('projects.create') }}</button>
    </x-slot>
</x-ui.drawer>

<script>
    var currentMilestoneData = null;

    function openMilestoneDrawer(mode, data) {
        data = data || {};
        currentMilestoneData = mode === 'edit' ? data : null;

        var form = document.getElementById('milestoneForm');
        var methodField = document.getElementById('milestoneMethodField');
        var modeField = document.getElementById('milestone_form_mode');
        var idField = document.getElementById('milestone_form_id');
        var titleEl = document.getElementById('milestoneDrawerLabel');
        var submitBtn = document.getElementById('milestoneSubmitBtn');
        var actionsBar = document.getElementById('milestoneActionsBar');

        if (mode === 'edit') {
            form.action = data.updateUrl;
            methodField.value = 'PUT';
            modeField.value = 'edit';
            idField.value = data.id;
            if (titleEl) titleEl.textContent = @js(__('projects.edit_milestone'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.save_changes'));
            if (actionsBar) {
                actionsBar.classList.remove('d-none');
                actionsBar.classList.add('d-flex');
            }
        } else {
            form.action = data.storeUrl || @js(isset($project) ? route('projects.milestones.store', $project) : '');
            methodField.value = 'POST';
            modeField.value = 'add';
            idField.value = '';
            if (titleEl) titleEl.textContent = @js(__('projects.add_milestone'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.create'));
            if (actionsBar) {
                actionsBar.classList.remove('d-flex');
                actionsBar.classList.add('d-none');
            }
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
        setMilestoneOwner(data.ownerId);

        var drawerEl = document.getElementById('milestoneDrawer');
        if (drawerEl && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }
    }

    function setMilestoneFieldValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    (function () {
        var completionInput = document.getElementById('milestone_completion_percentage');
        if (!completionInput) return;

        completionInput.addEventListener('input', function () {
            if (completionInput.value === '') return;

            var clamped = Math.min(100, Math.max(0, Number(completionInput.value)));
            if (!Number.isNaN(clamped) && Number(completionInput.value) !== clamped) {
                completionInput.value = clamped;
            }
        });
    })();

    function setMilestoneOwner(value) {
        var el = document.getElementById('milestone_owner_id');
        if (!el) return;
        el.value = value || '';
        if (window.jQuery && jQuery(el).data('select2')) {
            jQuery(el).trigger('change');
        }
    }

    function cloneCurrentMilestone() {
        if (!currentMilestoneData) return;

        var cloneData = Object.assign({}, currentMilestoneData, {
            name: currentMilestoneData.name ? currentMilestoneData.name + @js(' ' . __('projects.clone_suffix')) : '',
        });

        openMilestoneDrawer('add', cloneData);
    }

    function deleteCurrentMilestone() {
        if (!currentMilestoneData || !currentMilestoneData.deleteUrl) return;
        if (!confirm(@js(__('projects.confirm_remove_milestone')))) return;

        var form = document.getElementById('milestoneDeleteForm');
        form.action = currentMilestoneData.deleteUrl;
        form.submit();
    }
</script>
