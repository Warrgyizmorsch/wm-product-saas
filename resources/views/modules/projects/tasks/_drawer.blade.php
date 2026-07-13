<x-ui.drawer id="taskDrawer" title="{{ __('projects.task_details') }}" position="end" style="width: 480px; max-width: 100%;">
    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_title') }}</div>
        <div id="taskDetailTitle" class="fw-semibold text-dark"></div>
    </div>

    <div class="mb-3" id="taskDetailDescriptionWrap">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.description') }}</div>
        <div id="taskDetailDescription" class="text-dark"></div>
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_list') }}</div>
        <div id="taskDetailTaskList" class="text-dark"></div>
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_assignee') }}</div>
            <div id="taskDetailAssignee" class="text-dark"></div>
        </div>
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_reviewer') }}</div>
            <div id="taskDetailReviewer" class="text-dark"></div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.priority') }}</div>
            <div id="taskDetailPriority" class="text-dark"></div>
        </div>
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.status') }}</div>
            <div id="taskDetailStatus"></div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.start_date') }}</div>
            <div id="taskDetailStartDate" class="text-dark"></div>
        </div>
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.due_date') }}</div>
            <div id="taskDetailDueDate" class="text-dark"></div>
        </div>
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.estimated_hours') }}</div>
        <div id="taskDetailEstimatedHours" class="text-dark"></div>
    </div>

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
        <button type="button" class="btn btn-outline-danger" onclick="deleteCurrentTask()">
            <i class="feather feather-trash-2 me-1"></i>{{ __('projects.remove') }}
        </button>
        <button type="button" class="btn btn-light-brand" onclick="cloneCurrentTask()">
            <i class="feather feather-copy me-1"></i>{{ __('projects.clone') }}
        </button>
        <button type="button" class="btn btn-primary" onclick="editCurrentTask()">
            {{ __('projects.edit_task') }}
        </button>
    </x-slot>
</x-ui.drawer>

<script>
    var currentTaskData = null;

    var taskStatusVariants = {
        'In Progress': 'info',
        'Review': 'warning',
        'On Hold': 'dark',
        'Completed': 'success',
        'Cancelled': 'danger',
    };

    var taskStatusLabels = @js(collect(\App\Domains\Projects\Models\Task::STATUSES)->mapWithKeys(fn ($status) => [$status => __('projects.task_statuses.' . $status)]));
    var taskPriorityLabels = @js(collect(\App\Domains\Projects\Models\Task::PRIORITIES)->mapWithKeys(fn ($priority) => [$priority => __('projects.priorities.' . $priority)]));

    function openTaskDetailsDrawer(data) {
        data = data || {};
        currentTaskData = data;

        document.getElementById('taskDetailTitle').textContent = data.title || '—';

        var descriptionWrap = document.getElementById('taskDetailDescriptionWrap');
        if (data.description) {
            descriptionWrap.classList.remove('d-none');
            document.getElementById('taskDetailDescription').textContent = data.description;
        } else {
            descriptionWrap.classList.add('d-none');
        }

        document.getElementById('taskDetailTaskList').textContent = data.taskListName || '—';
        document.getElementById('taskDetailAssignee').textContent = data.assigneeName || '—';
        document.getElementById('taskDetailReviewer').textContent = data.reviewerName || '—';
        document.getElementById('taskDetailPriority').textContent = (data.priority && taskPriorityLabels[data.priority]) || data.priority || '—';
        document.getElementById('taskDetailStartDate').textContent = data.startDateDisplay || '—';
        document.getElementById('taskDetailDueDate').textContent = data.dueDateDisplay || '—';
        document.getElementById('taskDetailEstimatedHours').textContent = data.estimatedHours || '—';

        var statusEl = document.getElementById('taskDetailStatus');
        if (data.status) {
            var variant = taskStatusVariants[data.status] || 'secondary';
            var label = taskStatusLabels[data.status] || data.status;
            statusEl.innerHTML = '<span class="badge bg-' + variant + '-soft text-' + variant + '">' + label + '</span>';
        } else {
            statusEl.textContent = '—';
        }

        renderTaskSubtasks(data);
        renderTaskDependencies(data);

        var drawerEl = document.getElementById('taskDrawer');
        if (drawerEl && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }
    }

    function hideTaskDetailsDrawer() {
        var drawerEl = document.getElementById('taskDrawer');
        if (drawerEl && window.bootstrap) {
            var instance = bootstrap.Offcanvas.getInstance(drawerEl);
            if (instance) instance.hide();
        }
    }

    function editCurrentTask() {
        if (!currentTaskData) return;
        hideTaskDetailsDrawer();
        openTaskModal('edit', currentTaskData);
    }

    function cloneCurrentTask() {
        if (!currentTaskData) return;

        var cloneData = Object.assign({}, currentTaskData, {
            title: currentTaskData.title ? currentTaskData.title + @js(' ' . __('projects.clone_suffix')) : '',
        });

        hideTaskDetailsDrawer();
        openTaskModal('add', cloneData);
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

    function deleteCurrentTask() {
        if (!currentTaskData || !currentTaskData.deleteUrl) return;
        if (!confirm(@js(__('projects.confirm_remove_task')))) return;

        var form = document.getElementById('taskDeleteForm');
        form.action = currentTaskData.deleteUrl;
        form.submit();
    }
</script>
