<x-ui.drawer id="taskListDrawer" title="{{ __('projects.tasklist_details') }}" position="end" style="width: 480px; max-width: 100%;">
    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.tasklist_name') }}</div>
        <div id="taskListDetailName" class="fw-semibold text-dark"></div>
    </div>

    <div class="mb-3" id="taskListDetailDescriptionWrap">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.description') }}</div>
        <div id="taskListDetailDescription" class="text-dark"></div>
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.milestone') }}</div>
        <div id="taskListDetailMilestone" class="text-dark"></div>
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.tasklist_owner') }}</div>
        <div id="taskListDetailOwner" class="text-dark"></div>
    </div>

    <form id="taskListDeleteForm" method="POST" action="" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <x-slot name="footer">
        <button type="button" class="btn btn-outline-danger" onclick="deleteCurrentTaskList()">
            <i class="feather feather-trash-2 me-1"></i>{{ __('projects.remove') }}
        </button>
        <button type="button" class="btn btn-light-brand" onclick="cloneCurrentTaskList()">
            <i class="feather feather-copy me-1"></i>{{ __('projects.clone') }}
        </button>
        <button type="button" class="btn btn-primary" onclick="editCurrentTaskList()">
            {{ __('projects.edit_tasklist') }}
        </button>
    </x-slot>
</x-ui.drawer>

<script>
    var currentTaskListData = null;

    function openTaskListDetailsDrawer(data) {
        data = data || {};
        currentTaskListData = data;

        document.getElementById('taskListDetailName').textContent = data.name || '—';

        var descriptionWrap = document.getElementById('taskListDetailDescriptionWrap');
        if (data.description) {
            descriptionWrap.classList.remove('d-none');
            document.getElementById('taskListDetailDescription').textContent = data.description;
        } else {
            descriptionWrap.classList.add('d-none');
        }

        document.getElementById('taskListDetailMilestone').textContent = data.milestoneName || '—';
        document.getElementById('taskListDetailOwner').textContent = data.ownerName || '—';

        var drawerEl = document.getElementById('taskListDrawer');
        if (drawerEl && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }
    }

    function hideTaskListDetailsDrawer() {
        var drawerEl = document.getElementById('taskListDrawer');
        if (drawerEl && window.bootstrap) {
            var instance = bootstrap.Offcanvas.getInstance(drawerEl);
            if (instance) instance.hide();
        }
    }

    function editCurrentTaskList() {
        if (!currentTaskListData) return;
        hideTaskListDetailsDrawer();
        openTaskListModal('edit', currentTaskListData);
    }

    function cloneCurrentTaskList() {
        if (!currentTaskListData) return;

        var cloneData = Object.assign({}, currentTaskListData, {
            name: currentTaskListData.name ? currentTaskListData.name + @js(' ' . __('projects.clone_suffix')) : '',
        });

        hideTaskListDetailsDrawer();
        openTaskListModal('add', cloneData);
    }

    function deleteCurrentTaskList() {
        if (!currentTaskListData || !currentTaskListData.deleteUrl) return;

        confirmAction(@js(__('projects.confirm_remove_tasklist')), function () {
            var form = document.getElementById('taskListDeleteForm');
            form.action = currentTaskListData.deleteUrl;
            form.submit();
        });
    }
</script>
