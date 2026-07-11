<x-ui.drawer id="taskDrawer" title="{{ __('projects.add_task') }}" position="end" style="width: 480px; max-width: 100%;">
    <div id="taskActionsBar" class="d-none justify-content-end gap-2 mb-3">
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteCurrentTask()">
            <i class="feather feather-trash-2 me-1"></i>{{ __('projects.remove') }}
        </button>
    </div>

    <form id="taskForm" method="POST" action="{{ isset($project) ? route('projects.tasks.store', $project) : '' }}">
        @csrf
        <input type="hidden" name="_method" id="taskMethodField" value="POST">
        @include('modules.projects.tasks._form')
    </form>

    <form id="taskDeleteForm" method="POST" action="" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <x-slot name="footer">
        <button type="button" class="btn btn-light-brand" data-bs-dismiss="offcanvas">{{ __('projects.cancel') }}</button>
        <button type="submit" form="taskForm" id="taskSubmitBtn" class="btn btn-primary">{{ __('projects.create') }}</button>
    </x-slot>
</x-ui.drawer>

<script>
    var currentTaskData = null;

    function openTaskDrawer(mode, data) {
        data = data || {};
        currentTaskData = mode === 'edit' ? data : null;

        var form = document.getElementById('taskForm');
        var methodField = document.getElementById('taskMethodField');
        var modeField = document.getElementById('task_form_mode');
        var idField = document.getElementById('task_form_id');
        var titleEl = document.getElementById('taskDrawerLabel');
        var submitBtn = document.getElementById('taskSubmitBtn');
        var actionsBar = document.getElementById('taskActionsBar');

        if (mode === 'edit') {
            form.action = data.updateUrl;
            methodField.value = 'PUT';
            modeField.value = 'edit';
            idField.value = data.id;
            if (titleEl) titleEl.textContent = @js(__('projects.edit_task'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.save_changes'));
            if (actionsBar) {
                actionsBar.classList.remove('d-none');
                actionsBar.classList.add('d-flex');
            }
        } else {
            form.action = data.storeUrl || @js(isset($project) ? route('projects.tasks.store', $project) : '');
            methodField.value = 'POST';
            modeField.value = 'add';
            idField.value = '';
            if (titleEl) titleEl.textContent = @js(__('projects.add_task'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.create'));
            if (actionsBar) {
                actionsBar.classList.remove('d-flex');
                actionsBar.classList.add('d-none');
            }
        }

        setTaskFieldValue('task_title', data.title || '');
        setTaskFieldValue('task_description', data.description || '');
        setTaskFieldValue('task_start_date', data.startDate || '');
        setTaskFieldValue('task_due_date', data.dueDate || '');
        setTaskFieldValue('task_estimated_hours', data.estimatedHours || '');
        setTaskFieldValue('task_priority', data.priority || 'Medium');
        setTaskSelect('task_task_list_id', data.taskListId);
        setTaskSelect('task_assignee_id', data.assigneeId);
        setTaskSelect('task_reviewer_id', data.reviewerId);

        var drawerEl = document.getElementById('taskDrawer');
        if (drawerEl && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }
    }

    function setTaskFieldValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function setTaskSelect(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        el.value = value || '';
        if (window.jQuery && jQuery(el).data('select2')) {
            jQuery(el).trigger('change');
        }
    }

    function deleteCurrentTask() {
        if (!currentTaskData || !currentTaskData.deleteUrl) return;
        if (!confirm(@js(__('projects.confirm_remove_task')))) return;

        var form = document.getElementById('taskDeleteForm');
        form.action = currentTaskData.deleteUrl;
        form.submit();
    }
</script>
