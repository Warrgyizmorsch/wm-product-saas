<x-ui.modal id="taskModal" title="{{ __('projects.add_task') }}" size="lg" :scrollable="true" :static="true" :showFooter="false">
    <form id="taskForm" method="POST" action="{{ isset($project) ? route('projects.tasks.store', $project) : '' }}">
        @csrf
        <input type="hidden" name="_method" id="taskMethodField" value="POST">
        @include('modules.projects.tasks._form')

        <div class="d-flex gap-2 justify-content-end pt-3 border-top mt-4">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" id="taskSubmitBtn" class="btn btn-primary px-4">{{ __('projects.create') }}</button>
        </div>
    </form>
</x-ui.modal>

<script>
    function openTaskModal(mode, data) {
        data = data || {};

        var form = document.getElementById('taskForm');
        var methodField = document.getElementById('taskMethodField');
        var modeField = document.getElementById('task_form_mode');
        var idField = document.getElementById('task_form_id');
        var titleEl = document.getElementById('taskModalLabel');
        var submitBtn = document.getElementById('taskSubmitBtn');

        if (mode === 'edit') {
            form.action = data.updateUrl;
            methodField.value = 'PUT';
            modeField.value = 'edit';
            idField.value = data.id;
            if (titleEl) titleEl.textContent = @js(__('projects.edit_task'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.save_changes'));
        } else {
            form.action = data.storeUrl || @js(isset($project) ? route('projects.tasks.store', $project) : '');
            methodField.value = 'POST';
            modeField.value = 'add';
            idField.value = '';
            if (titleEl) titleEl.textContent = @js(__('projects.add_task'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.create'));
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

        var modalEl = document.getElementById('taskModal');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
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
</script>
