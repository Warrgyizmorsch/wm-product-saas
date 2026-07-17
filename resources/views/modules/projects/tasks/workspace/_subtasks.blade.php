<div class="border rounded-3 p-3 mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-dark mb-0"><i class="feather-check-square me-2 text-primary"></i>{{ __('projects.subtasks') }}</h6>
        <span id="wsSubtasksProgress" class="badge bg-soft-secondary text-secondary fs-11"></span>
    </div>

    <div id="wsSubtasksList" class="mb-2"></div>

    @if ($canManageTask)
        <form id="wsSubtaskAddForm" class="d-flex gap-2" action="{{ route('projects.tasks.subtasks.store', [$project, $task]) }}">
            @csrf
            <input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('projects.subtask_title_placeholder') }}" required>
            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">{{ __('projects.add') }}</button>
        </form>
    @endif
</div>

<script>
    (function () {
        var canManage = @js($canManageTask);
        var subtasks = @js($subtasksPayload);
        var list = document.getElementById('wsSubtasksList');
        var progress = document.getElementById('wsSubtasksProgress');

        function csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        }

        var requestInFlight = false;

        function wsRequest(url, method, body) {
            if (requestInFlight) {
                return Promise.reject(new Error('busy'));
            }
            requestInFlight = true;

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
                            window.showAppToast('error', message);
                            throw new Error(message);
                        }
                        return data;
                    });
                })
                .finally(function () { requestInFlight = false; });
        }

        function render() {
            var completed = subtasks.filter(function (s) { return s.isCompleted; }).length;
            progress.textContent = subtasks.length ? (completed + '/' + subtasks.length) : '';

            list.innerHTML = '';

            if (subtasks.length === 0) {
                var empty = document.createElement('p');
                empty.className = 'fs-12 text-muted mb-2';
                empty.textContent = @js(__('projects.no_subtasks'));
                list.appendChild(empty);
                return;
            }

            subtasks.forEach(function (subtask) {
                var row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2 border-bottom py-1';

                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'form-check-input flex-shrink-0';
                checkbox.checked = !!subtask.isCompleted;
                checkbox.disabled = !canManage;
                checkbox.onchange = function () {
                    checkbox.disabled = true;
                    wsRequest(subtask.toggleUrl, 'PATCH')
                        .then(function (result) { apply(result); })
                        .catch(function () {
                            checkbox.checked = !checkbox.checked;
                            checkbox.disabled = false;
                        });
                };

                var title = document.createElement('input');
                title.type = 'text';
                title.className = 'form-control form-control-sm border-0 bg-transparent flex-grow-1';
                title.value = subtask.title;
                title.readOnly = !canManage;
                if (subtask.isCompleted) title.style.textDecoration = 'line-through';
                title.onblur = function () {
                    if (canManage && title.value && title.value !== subtask.title) {
                        title.disabled = true;
                        wsRequest(subtask.updateUrl, 'PUT', { title: title.value })
                            .then(function (result) { apply(result); })
                            .catch(function () {
                                title.value = subtask.title;
                                title.disabled = false;
                            });
                    }
                };

                row.appendChild(checkbox);
                row.appendChild(title);

                if (canManage) {
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'btn btn-link text-danger btn-sm p-0 flex-shrink-0';
                    remove.innerHTML = '<i class="feather-x"></i>';
                    remove.onclick = function () {
                        confirmAction(@js(__('projects.confirm_remove_subtask')), function () {
                            remove.disabled = true;
                            wsRequest(subtask.deleteUrl, 'DELETE')
                                .then(function (result) { apply(result); })
                                .catch(function () { remove.disabled = false; });
                        });
                    };
                    row.appendChild(remove);
                }

                list.appendChild(row);
            });
        }

        function apply(result) {
            subtasks = result.subtasks || [];
            render();
            window.showAppToast('success', result.message);
        }

        render();

        var addForm = document.getElementById('wsSubtaskAddForm');
        if (addForm) {
            addForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var input = addForm.querySelector('input[name="title"]');
                var button = addForm.querySelector('button[type="submit"]');
                if (!input.value) return;

                button.disabled = true;
                wsRequest(addForm.action, 'POST', { title: input.value })
                    .then(function (result) {
                        apply(result);
                        input.value = '';
                    })
                    .finally(function () { button.disabled = false; });
            });
        }
    })();
</script>
