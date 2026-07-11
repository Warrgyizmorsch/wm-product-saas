<x-ui.drawer id="taskListDrawer" title="{{ __('projects.add_tasklist') }}" position="end" style="width: 480px; max-width: 100%;">
    <div id="taskListActionsBar" class="d-none justify-content-end gap-2 mb-3">
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteCurrentTaskList()">
            <i class="feather feather-trash-2 me-1"></i>{{ __('projects.remove') }}
        </button>
    </div>

    <form id="taskListForm" method="POST" action="{{ isset($project) ? route('projects.tasklists.store', $project) : '' }}">
        @csrf
        <input type="hidden" name="_method" id="taskListMethodField" value="POST">
        @include('modules.projects.tasklists._form')
    </form>

    <form id="taskListDeleteForm" method="POST" action="" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <x-slot name="footer">
        <button type="button" class="btn btn-light-brand" data-bs-dismiss="offcanvas">{{ __('projects.cancel') }}</button>
        <button type="submit" form="taskListForm" id="taskListSubmitBtn" class="btn btn-primary">{{ __('projects.create') }}</button>
    </x-slot>
</x-ui.drawer>

<script>
    var currentTaskListData = null;

    function openTaskListDrawer(mode, data) {
        data = data || {};
        currentTaskListData = mode === 'edit' ? data : null;

        var form = document.getElementById('taskListForm');
        var methodField = document.getElementById('taskListMethodField');
        var modeField = document.getElementById('tasklist_form_mode');
        var idField = document.getElementById('tasklist_form_id');
        var titleEl = document.getElementById('taskListDrawerLabel');
        var submitBtn = document.getElementById('taskListSubmitBtn');
        var actionsBar = document.getElementById('taskListActionsBar');

        if (mode === 'edit') {
            form.action = data.updateUrl;
            methodField.value = 'PUT';
            modeField.value = 'edit';
            idField.value = data.id;
            if (titleEl) titleEl.textContent = @js(__('projects.edit_tasklist'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.save_changes'));
            if (actionsBar) {
                actionsBar.classList.remove('d-none');
                actionsBar.classList.add('d-flex');
            }
        } else {
            form.action = data.storeUrl || @js(isset($project) ? route('projects.tasklists.store', $project) : '');
            methodField.value = 'POST';
            modeField.value = 'add';
            idField.value = '';
            if (titleEl) titleEl.textContent = @js(__('projects.add_tasklist'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.create'));
            if (actionsBar) {
                actionsBar.classList.remove('d-flex');
                actionsBar.classList.add('d-none');
            }
        }

        setTaskListFieldValue('tasklist_name', data.name || '');
        setTaskListFieldValue('tasklist_description', data.description || '');
        setTaskListMilestone(data.milestoneId);
        setTaskListOwner(data.ownerId);

        var drawerEl = document.getElementById('taskListDrawer');
        if (drawerEl && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }
    }

    function setTaskListFieldValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function setTaskListMilestone(value) {
        var el = document.getElementById('tasklist_milestone_id');
        if (!el) return;
        el.value = value || '';
        if (window.jQuery && jQuery(el).data('select2')) {
            jQuery(el).trigger('change');
        }
    }

    function setTaskListOwner(value) {
        var el = document.getElementById('tasklist_owner_id');
        if (!el) return;
        el.value = value || '';
        if (window.jQuery && jQuery(el).data('select2')) {
            jQuery(el).trigger('change');
        }
    }

    function deleteCurrentTaskList() {
        if (!currentTaskListData || !currentTaskListData.deleteUrl) return;
        if (!confirm(@js(__('projects.confirm_remove_tasklist')))) return;

        var form = document.getElementById('taskListDeleteForm');
        form.action = currentTaskListData.deleteUrl;
        form.submit();
    }
</script>
