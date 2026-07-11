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

    <div id="taskSubtasksSection" class="d-none border-top pt-3 mt-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="fw-bold mb-0">{{ __('projects.subtasks') }}</h6>
            <span id="taskSubtasksProgress" class="badge bg-soft-secondary text-secondary fs-11"></span>
        </div>
        <div id="taskSubtasksList" class="mb-2"></div>
        <form id="taskSubtaskAddForm" method="POST" action="" class="d-flex gap-2">
            @csrf
            <input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('projects.subtask_title_placeholder') }}" required>
            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">{{ __('projects.add') }}</button>
        </form>
    </div>

    <div id="taskDependenciesSection" class="d-none border-top pt-3 mt-3">
        <h6 class="fw-bold mb-2">{{ __('projects.dependencies') }}</h6>
        <div id="taskDependenciesList" class="mb-2"></div>
        <form id="taskDependencyAddForm" method="POST" action="" class="d-flex gap-2">
            @csrf
            <select name="depends_on_task_id" id="taskDependencyOptions" class="form-select form-select-sm" required>
                <option value="">{{ __('projects.select_option') }}</option>
            </select>
            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">{{ __('projects.add') }}</button>
        </form>
    </div>

    <form id="taskSubtaskToggleForm" method="POST" action="" class="d-none">
        @csrf
        @method('PATCH')
    </form>
    <form id="taskSubtaskRenameForm" method="POST" action="" class="d-none">
        @csrf
        @method('PUT')
        <input type="hidden" name="title" id="taskSubtaskRenameTitle">
    </form>
    <form id="taskSubtaskDeleteForm" method="POST" action="" class="d-none">
        @csrf
        @method('DELETE')
    </form>
    <form id="taskDependencyDeleteForm" method="POST" action="" class="d-none">
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

        renderTaskSubtasks(mode === 'edit' ? data : null);
        renderTaskDependencies(mode === 'edit' ? data : null);

        var drawerEl = document.getElementById('taskDrawer');
        if (drawerEl && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }
    }

    function renderTaskSubtasks(data) {
        var section = document.getElementById('taskSubtasksSection');
        var list = document.getElementById('taskSubtasksList');
        var progress = document.getElementById('taskSubtasksProgress');
        var addForm = document.getElementById('taskSubtaskAddForm');

        if (!data) {
            section.classList.add('d-none');
            return;
        }
        section.classList.remove('d-none');

        var subtasks = data.subtasks || [];
        var completed = subtasks.filter(function (s) { return s.isCompleted; }).length;
        progress.textContent = subtasks.length
            ? completed + '/' + subtasks.length + ' (' + Math.round((completed / subtasks.length) * 100) + '%)'
            : '0%';

        list.innerHTML = '';
        subtasks.forEach(function (subtask) {
            var row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 border-bottom py-1';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input flex-shrink-0';
            checkbox.checked = !!subtask.isCompleted;
            checkbox.onchange = function () { submitHiddenForm('taskSubtaskToggleForm', subtask.toggleUrl); };

            var title = document.createElement('input');
            title.type = 'text';
            title.className = 'form-control form-control-sm border-0 bg-transparent flex-grow-1';
            title.value = subtask.title;
            if (subtask.isCompleted) title.style.textDecoration = 'line-through';
            title.onblur = function () {
                if (title.value && title.value !== subtask.title) {
                    document.getElementById('taskSubtaskRenameTitle').value = title.value;
                    submitHiddenForm('taskSubtaskRenameForm', subtask.updateUrl);
                }
            };

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-link text-danger btn-sm p-0 flex-shrink-0';
            remove.innerHTML = '<i class="feather-x"></i>';
            remove.onclick = function () {
                if (confirm(@js(__('projects.confirm_remove_subtask')))) {
                    submitHiddenForm('taskSubtaskDeleteForm', subtask.deleteUrl);
                }
            };

            row.appendChild(checkbox);
            row.appendChild(title);
            row.appendChild(remove);
            list.appendChild(row);
        });

        addForm.action = data.subtaskStoreUrl || '';
    }

    function renderTaskDependencies(data) {
        var section = document.getElementById('taskDependenciesSection');
        var list = document.getElementById('taskDependenciesList');
        var addForm = document.getElementById('taskDependencyAddForm');
        var select = document.getElementById('taskDependencyOptions');

        if (!data) {
            section.classList.add('d-none');
            return;
        }
        section.classList.remove('d-none');

        var dependencies = data.dependencies || [];
        list.innerHTML = '';
        dependencies.forEach(function (dependency) {
            var row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between gap-2 border-bottom py-1';

            var label = document.createElement('span');
            label.className = 'fs-12';
            label.textContent = dependency.label;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-link text-danger btn-sm p-0 flex-shrink-0';
            remove.innerHTML = '<i class="feather-x"></i>';
            remove.onclick = function () {
                if (confirm(@js(__('projects.confirm_remove_dependency')))) {
                    submitHiddenForm('taskDependencyDeleteForm', dependency.deleteUrl);
                }
            };

            row.appendChild(label);
            row.appendChild(remove);
            list.appendChild(row);
        });

        select.innerHTML = '<option value="">' + @js(__('projects.select_option')) + '</option>';
        (data.otherTasks || []).forEach(function (option) {
            var opt = document.createElement('option');
            opt.value = option.id;
            opt.textContent = option.label;
            select.appendChild(opt);
        });

        addForm.action = data.dependencyStoreUrl || '';
    }

    function submitHiddenForm(formId, action) {
        if (!action) return;
        var form = document.getElementById(formId);
        form.action = action;
        form.submit();
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
