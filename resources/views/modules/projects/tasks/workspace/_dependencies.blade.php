<div class="border rounded-3 p-3 mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-dark mb-0"><i class="feather-link me-2 text-primary"></i>{{ __('projects.dependencies') }}</h6>
        <span id="wsDependenciesCount" class="badge bg-soft-secondary text-secondary fs-11"></span>
    </div>

    <div id="wsDependenciesList" class="mb-2"></div>

    @if ($canManageTask)
        <form id="wsDependencyAddForm" class="d-flex gap-2 mb-3" action="{{ route('projects.tasks.dependencies.store', [$project, $task]) }}">
            @csrf
            <select name="depends_on_task_id" class="form-select form-select-sm" required>
                <option value="">{{ __('projects.select_option') }}</option>
                @foreach ($otherTasks as $otherTask)
                    <option value="{{ $otherTask['id'] }}">{{ $otherTask['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">{{ __('projects.add') }}</button>
        </form>
    @endif

    @if ($task->dependents->isNotEmpty())
        <div class="pt-2 border-top">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-2">{{ __('projects.workspace_blocks') }}</div>
            @foreach ($task->dependents as $dependent)
                <div class="fs-12 text-dark py-1">{{ $dependent->task?->task_code }} — {{ $dependent->task?->title }}</div>
            @endforeach
        </div>
    @endif
</div>

<script>
    (function () {
        var canManage = @js($canManageTask);
        var dependencies = @js($dependenciesPayload);
        var list = document.getElementById('wsDependenciesList');
        var count = document.getElementById('wsDependenciesCount');

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
            count.textContent = dependencies.length || '';
            list.innerHTML = '';

            if (dependencies.length === 0) {
                var empty = document.createElement('p');
                empty.className = 'fs-12 text-muted mb-2';
                empty.textContent = @js(__('projects.no_dependencies'));
                list.appendChild(empty);
                return;
            }

            dependencies.forEach(function (dependency) {
                var row = document.createElement('div');
                row.className = 'd-flex align-items-center justify-content-between gap-2 border-bottom py-1';

                var label = document.createElement('span');
                label.className = 'fs-12';
                label.textContent = dependency.label;
                row.appendChild(label);

                if (canManage) {
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'btn btn-link text-danger btn-sm p-0 flex-shrink-0';
                    remove.innerHTML = '<i class="feather-x"></i>';
                    remove.onclick = function () {
                        confirmAction(@js(__('projects.confirm_remove_dependency')), function () {
                            remove.disabled = true;
                            wsRequest(dependency.deleteUrl, 'DELETE')
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
            dependencies = result.dependencies || [];
            render();
            window.showAppToast('success', result.message);
        }

        render();

        var addForm = document.getElementById('wsDependencyAddForm');
        if (addForm) {
            addForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var select = addForm.querySelector('select[name="depends_on_task_id"]');
                var button = addForm.querySelector('button[type="submit"]');
                if (!select.value) return;

                button.disabled = true;
                wsRequest(addForm.action, 'POST', { depends_on_task_id: select.value })
                    .then(function (result) {
                        apply(result);
                        select.value = '';
                    })
                    .finally(function () { button.disabled = false; });
            });
        }
    })();
</script>
