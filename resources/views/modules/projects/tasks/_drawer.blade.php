<x-ui.drawer id="taskDrawer" title="{{ __('projects.task_details') }}" position="end" style="width: 480px; max-width: 100%;">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
        <div class="min-w-0">
            <h5 id="taskDetailTitle" class="fw-bold mb-1"></h5>
            <div id="taskDetailTaskListWrap" class="fs-12 text-muted"></div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
            <span id="taskDetailStatus"></span>
            <span id="taskDetailPriority"></span>
        </div>
    </div>

    <div class="mb-3" id="taskDetailDescriptionWrap">
        <div id="taskDetailDescription" class="text-muted fs-13"></div>
    </div>

    <div class="border rounded-3 p-3 mb-3 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold fs-13">{{ __('projects.progress') }}</span>
            <span id="taskProgressSummary" class="fs-12 text-muted"></span>
        </div>
        <div class="progress" style="height: 8px;">
            <div id="taskProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
        </div>
    </div>

    <form id="taskDeleteForm" method="POST" action="" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <div class="accordion mb-3" id="taskDetailAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#taskDetailsCollapse" aria-expanded="false">
                    {{ __('projects.details') }}
                </button>
            </h2>
            <div id="taskDetailsCollapse" class="accordion-collapse collapse" data-bs-parent="#taskDetailAccordion">
                <div class="accordion-body">
                    <div class="row row-cols-2 g-3">
                        <div class="col">
                            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_assignee') }}</div>
                            <div id="taskDetailAssignee" class="text-dark"></div>
                        </div>
                        <div class="col">
                            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.task_reviewer') }}</div>
                            <div id="taskDetailReviewer" class="text-dark"></div>
                        </div>
                        <div class="col">
                            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.estimated_hours') }}</div>
                            <div id="taskDetailEstimatedHours" class="text-dark"></div>
                        </div>
                        <div class="col">
                            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.start_date') }}</div>
                            <div id="taskDetailStartDate" class="text-dark"></div>
                        </div>
                        <div class="col">
                            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.due_date') }}</div>
                            <div id="taskDetailDueDate" class="text-dark"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="taskSubtasksSection" class="accordion-item d-none">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#taskSubtasksCollapse" aria-expanded="true">
                    <span class="d-flex align-items-center justify-content-between w-100 me-2">
                        <span class="fw-bold">{{ __('projects.subtasks') }}</span>
                        <span id="taskSubtasksProgress" class="badge bg-soft-secondary text-secondary fs-11 me-2"></span>
                    </span>
                </button>
            </h2>
            <div id="taskSubtasksCollapse" class="accordion-collapse collapse show" data-bs-parent="#taskDetailAccordion">
                <div class="accordion-body">
                    <div id="taskSubtasksList" class="mb-2"></div>
                    <form id="taskSubtaskAddForm" method="POST" action="" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('projects.subtask_title_placeholder') }}" required>
                        <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">{{ __('projects.add') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="taskDependenciesSection" class="accordion-item d-none">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#taskDependenciesCollapse" aria-expanded="false">
                    <span class="d-flex align-items-center justify-content-between w-100 me-2">
                        <span class="fw-bold">{{ __('projects.dependencies') }}</span>
                        <span id="taskDependenciesCount" class="badge bg-soft-secondary text-secondary fs-11 me-2"></span>
                    </span>
                </button>
            </h2>
            <div id="taskDependenciesCollapse" class="accordion-collapse collapse" data-bs-parent="#taskDetailAccordion">
                <div class="accordion-body">
                    <div id="taskDependenciesList" class="mb-2"></div>
                    <form id="taskDependencyAddForm" method="POST" action="" class="d-flex gap-2">
                        @csrf
                        <select name="depends_on_task_id" id="taskDependencyOptions" class="form-select form-select-sm" required>
                            <option value="">{{ __('projects.select_option') }}</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">{{ __('projects.add') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="footer">
        <button type="button" class="btn btn-outline-danger" onclick="deleteCurrentTask()">
            <i class="feather feather-trash-2 me-1"></i>{{ __('projects.remove') }}
        </button>
        <button type="button" class="btn btn-primary" onclick="editCurrentTask()">
            {{ __('projects.edit_task') }}
        </button>
    </x-slot>
</x-ui.drawer>

<script>
    var currentTaskData = null;

    // Single source of truth for drawer data, keyed by task id. The row's onclick
    // payload only seeds this store on a task's first open; every open after that
    // (and every successful AJAX mutation, via the shared object reference) reads
    // the latest in-memory state instead of the page-load snapshot.
    //
    // Stores the latest client-side task payload for tasks opened during this page session.
    // Any future AJAX mutation that updates task fields should also update this store.
    // Full-page form submissions naturally recreate it after reload.
    var taskDrawerStore = {};

    var taskStatusVariants = {
        'In Progress': 'info',
        'Review': 'warning',
        'On Hold': 'dark',
        'Completed': 'success',
        'Cancelled': 'danger',
    };

    var taskPriorityVariants = {
        'Low': 'secondary',
        'Medium': 'warning',
        'High': 'danger',
        'Critical': 'danger',
    };

    var taskStatusLabels = @js(collect(\App\Domains\Projects\Models\Task::STATUSES)->mapWithKeys(fn ($status) => [$status => __('projects.task_statuses.' . $status)]));
    var taskPriorityLabels = @js(collect(\App\Domains\Projects\Models\Task::PRIORITIES)->mapWithKeys(fn ($priority) => [$priority => __('projects.priorities.' . $priority)]));

    function openTaskDetailsDrawer(data) {
        data = data || {};

        if (data.id != null) {
            data = taskDrawerStore[data.id] || (taskDrawerStore[data.id] = data);
        }

        currentTaskData = data;

        document.getElementById('taskDetailTitle').textContent = data.title || '—';

        var taskListWrap = document.getElementById('taskDetailTaskListWrap');
        taskListWrap.textContent = data.taskListName || '';
        taskListWrap.classList.toggle('d-none', !data.taskListName);

        var descriptionWrap = document.getElementById('taskDetailDescriptionWrap');
        if (data.description) {
            descriptionWrap.classList.remove('d-none');
            document.getElementById('taskDetailDescription').textContent = data.description;
        } else {
            descriptionWrap.classList.add('d-none');
        }

        document.getElementById('taskDetailAssignee').textContent = data.assigneeName || '—';
        document.getElementById('taskDetailReviewer').textContent = data.reviewerName || '—';
        document.getElementById('taskDetailStartDate').textContent = data.startDateDisplay || '—';
        document.getElementById('taskDetailDueDate').textContent = data.dueDateDisplay || '—';
        document.getElementById('taskDetailEstimatedHours').textContent = data.estimatedHours || '—';

        var statusEl = document.getElementById('taskDetailStatus');
        if (data.status) {
            var statusVariant = taskStatusVariants[data.status] || 'secondary';
            var statusLabel = taskStatusLabels[data.status] || data.status;
            statusEl.innerHTML = '<span class="badge bg-' + statusVariant + '-soft text-' + statusVariant + '">' + statusLabel + '</span>';
        } else {
            statusEl.innerHTML = '';
        }

        var priorityEl = document.getElementById('taskDetailPriority');
        if (data.priority) {
            var priorityVariant = taskPriorityVariants[data.priority] || 'secondary';
            var priorityLabel = taskPriorityLabels[data.priority] || data.priority;
            priorityEl.innerHTML = '<span class="badge bg-' + priorityVariant + '-soft text-' + priorityVariant + '">' + priorityLabel + '</span>';
        } else {
            priorityEl.innerHTML = '';
        }

        renderTaskProgress(data);
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

    function renderTaskProgress(data) {
        var bar = document.getElementById('taskProgressBar');
        var summary = document.getElementById('taskProgressSummary');
        var subtasks = (data && data.subtasks) || [];
        var completed = subtasks.filter(function (s) { return s.isCompleted; }).length;
        var percent = subtasks.length ? Math.round((completed / subtasks.length) * 100) : 0;

        bar.style.width = percent + '%';
        summary.textContent = subtasks.length
            ? completed + ' / ' + subtasks.length + ' ' + @js(__('projects.completed')) + ' (' + percent + '%)'
            : '0%';
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
        progress.textContent = completed + '/' + subtasks.length;

        var scrollTop = list.scrollTop;
        list.innerHTML = '';
        subtasks.forEach(function (subtask) {
            var row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 border-bottom py-1';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input flex-shrink-0';
            checkbox.checked = !!subtask.isCompleted;
            checkbox.onchange = function () {
                checkbox.disabled = true;
                drawerRequest(subtask.toggleUrl, 'PATCH')
                    .then(function (result) {
                        applySubtasksResult(result);
                    })
                    .catch(function () {
                        checkbox.checked = !checkbox.checked;
                        checkbox.disabled = false;
                    });
            };

            var title = document.createElement('input');
            title.type = 'text';
            title.className = 'form-control form-control-sm border-0 bg-transparent flex-grow-1';
            title.value = subtask.title;
            if (subtask.isCompleted) title.style.textDecoration = 'line-through';
            title.onblur = function () {
                if (title.value && title.value !== subtask.title) {
                    title.disabled = true;
                    drawerRequest(subtask.updateUrl, 'PUT', { title: title.value })
                        .then(function (result) {
                            applySubtasksResult(result);
                        })
                        .catch(function () {
                            title.value = subtask.title;
                            title.disabled = false;
                        });
                }
            };

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-link text-danger btn-sm p-0 flex-shrink-0';
            remove.innerHTML = '<i class="feather-x"></i>';
            remove.onclick = function () {
                confirmAction(@js(__('projects.confirm_remove_subtask')), function () {
                    remove.disabled = true;
                    drawerRequest(subtask.deleteUrl, 'DELETE')
                        .then(function (result) {
                            applySubtasksResult(result);
                        })
                        .catch(function () {
                            remove.disabled = false;
                        });
                });
            };

            row.appendChild(checkbox);
            row.appendChild(title);
            row.appendChild(remove);
            list.appendChild(row);
        });
        list.scrollTop = scrollTop;

        addForm.action = data.subtaskStoreUrl || '';
    }

    function applySubtasksResult(result) {
        currentTaskData.subtasks = result.subtasks || [];
        renderTaskProgress(currentTaskData);
        renderTaskSubtasks(currentTaskData);
        showAppToast('success', result.message);
    }

    function renderTaskDependencies(data) {
        var section = document.getElementById('taskDependenciesSection');
        var list = document.getElementById('taskDependenciesList');
        var addForm = document.getElementById('taskDependencyAddForm');
        var select = document.getElementById('taskDependencyOptions');
        var count = document.getElementById('taskDependenciesCount');

        if (!data) {
            section.classList.add('d-none');
            return;
        }
        section.classList.remove('d-none');

        var dependencies = data.dependencies || [];
        count.textContent = dependencies.length;

        var scrollTop = list.scrollTop;
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
                confirmAction(@js(__('projects.confirm_remove_dependency')), function () {
                    remove.disabled = true;
                    drawerRequest(dependency.deleteUrl, 'DELETE')
                        .then(function (result) {
                            applyDependenciesResult(result);
                        })
                        .catch(function () {
                            remove.disabled = false;
                        });
                });
            };

            row.appendChild(label);
            row.appendChild(remove);
            list.appendChild(row);
        });
        list.scrollTop = scrollTop;

        select.innerHTML = '<option value="">' + @js(__('projects.select_option')) + '</option>';
        (data.otherTasks || []).forEach(function (option) {
            var opt = document.createElement('option');
            opt.value = option.id;
            opt.textContent = option.label;
            select.appendChild(opt);
        });

        addForm.action = data.dependencyStoreUrl || '';
    }

    function applyDependenciesResult(result) {
        currentTaskData.dependencies = result.dependencies || [];
        currentTaskData.otherTasks = result.otherTasks || [];
        renderTaskDependencies(currentTaskData);
        showAppToast('success', result.message);
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    var drawerRequestInFlight = false;

    // Single fetch helper for all subtask/dependency mutations: keeps the drawer
    // open, guards against duplicate submits, and surfaces errors as toasts so
    // callers only need to handle re-rendering their own section on success.
    function drawerRequest(url, method, body) {
        if (drawerRequestInFlight) {
            return Promise.reject(new Error('busy'));
        }
        drawerRequestInFlight = true;

        var options = {
            method: method,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        };

        if (body) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        return fetch(url, options)
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok) {
                        var errors = data.errors ? Object.values(data.errors) : [];
                        var message = (errors[0] && errors[0][0]) || data.message || @js(__('projects.something_went_wrong'));
                        showAppToast('error', message);
                        throw new Error(message);
                    }
                    return data;
                });
            })
            .finally(function () {
                drawerRequestInFlight = false;
            });
    }

    document.getElementById('taskSubtaskAddForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.target;
        var input = form.querySelector('input[name="title"]');
        var button = form.querySelector('button[type="submit"]');
        if (!input.value || !form.action) return;

        button.disabled = true;
        drawerRequest(form.action, 'POST', { title: input.value })
            .then(function (result) {
                applySubtasksResult(result);
                input.value = '';
            })
            .finally(function () {
                button.disabled = false;
            });
    });

    document.getElementById('taskDependencyAddForm').addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.target;
        var select = form.querySelector('select[name="depends_on_task_id"]');
        var button = form.querySelector('button[type="submit"]');
        if (!select.value || !form.action) return;

        button.disabled = true;
        drawerRequest(form.action, 'POST', { depends_on_task_id: select.value })
            .then(function (result) {
                applyDependenciesResult(result);
                select.value = '';
            })
            .finally(function () {
                button.disabled = false;
            });
    });

    function deleteCurrentTask() {
        if (!currentTaskData || !currentTaskData.deleteUrl) return;

        confirmAction(@js(__('projects.confirm_remove_task')), function () {
            var form = document.getElementById('taskDeleteForm');
            form.action = currentTaskData.deleteUrl;
            form.submit();
        });
    }
</script>
