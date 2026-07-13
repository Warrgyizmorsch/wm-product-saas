<x-ui.modal id="taskListModal" title="{{ __('projects.add_tasklist') }}" size="lg" :scrollable="true" :static="true" :showFooter="false">
    <form id="taskListForm" method="POST" action="{{ isset($project) ? route('projects.tasklists.store', $project) : '' }}">
        @csrf
        <input type="hidden" name="_method" id="taskListMethodField" value="POST">
        @include('modules.projects.tasklists._form')

        <div class="d-flex gap-2 justify-content-end pt-3 border-top mt-4">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" id="taskListSubmitBtn" class="btn btn-primary px-4">{{ __('projects.create') }}</button>
        </div>
    </form>
</x-ui.modal>

<script>
    function openTaskListModal(mode, data) {
        data = data || {};

        var form = document.getElementById('taskListForm');
        var methodField = document.getElementById('taskListMethodField');
        var modeField = document.getElementById('tasklist_form_mode');
        var idField = document.getElementById('tasklist_form_id');
        var titleEl = document.getElementById('taskListModalLabel');
        var submitBtn = document.getElementById('taskListSubmitBtn');

        if (mode === 'edit') {
            form.action = data.updateUrl;
            methodField.value = 'PUT';
            modeField.value = 'edit';
            idField.value = data.id;
            if (titleEl) titleEl.textContent = @js(__('projects.edit_tasklist'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.save_changes'));
        } else {
            form.action = data.storeUrl || @js(isset($project) ? route('projects.tasklists.store', $project) : '');
            methodField.value = 'POST';
            modeField.value = 'add';
            idField.value = '';
            if (titleEl) titleEl.textContent = @js(__('projects.add_tasklist'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.create'));
        }

        setTaskListFieldValue('tasklist_name', data.name || '');
        setTaskListFieldValue('tasklist_description', data.description || '');
        setTaskListMilestone(data.milestoneId);
        setTaskListOwner(data.ownerId);

        var modalEl = document.getElementById('taskListModal');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
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
</script>
