<x-ui.drawer id="milestoneDrawer" title="{{ __('projects.milestone_details') }}" position="end" style="width: 480px; max-width: 100%;">
    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.milestone_name') }}</div>
        <div id="milestoneDetailName" class="fw-semibold text-dark"></div>
    </div>

    <div class="mb-3" id="milestoneDetailDescriptionWrap">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.description') }}</div>
        <div id="milestoneDetailDescription" class="text-dark"></div>
    </div>

    <div class="mb-3">
        <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.milestone_owner') }}</div>
        <div id="milestoneDetailOwner" class="text-dark"></div>
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.start_date') }}</div>
            <div id="milestoneDetailStartDate" class="text-dark"></div>
        </div>
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.due_date') }}</div>
            <div id="milestoneDetailDueDate" class="text-dark"></div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.status') }}</div>
            <div id="milestoneDetailStatus"></div>
        </div>
        <div class="col-6">
            <div class="fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.completion_percentage') }}</div>
            <div id="milestoneDetailCompletion" class="text-dark"></div>
        </div>
    </div>

    <form id="milestoneDeleteForm" method="POST" action="" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <x-slot name="footer">
        <button type="button" class="btn btn-outline-danger" onclick="deleteCurrentMilestone()">
            <i class="feather feather-trash-2 me-1"></i>{{ __('projects.remove') }}
        </button>
        <button type="button" class="btn btn-light-brand" onclick="cloneCurrentMilestone()">
            <i class="feather feather-copy me-1"></i>{{ __('projects.clone') }}
        </button>
        <button type="button" class="btn btn-primary" onclick="editCurrentMilestone()">
            {{ __('projects.edit_milestone') }}
        </button>
    </x-slot>
</x-ui.drawer>

<script>
    var currentMilestoneData = null;

    var milestoneStatusVariants = {
        'Active': 'success',
        'On Hold': 'warning',
        'Completed': 'primary',
        'Closed': 'dark',
    };

    var milestoneStatusLabels = @js(collect(\App\Domains\Projects\Models\Milestone::STATUSES)->mapWithKeys(fn ($status) => [$status => __('projects.statuses.' . $status)]));

    function openMilestoneDetailsDrawer(data) {
        data = data || {};
        currentMilestoneData = data;

        document.getElementById('milestoneDetailName').textContent = data.name || '—';

        var descriptionWrap = document.getElementById('milestoneDetailDescriptionWrap');
        if (data.description) {
            descriptionWrap.classList.remove('d-none');
            document.getElementById('milestoneDetailDescription').textContent = data.description;
        } else {
            descriptionWrap.classList.add('d-none');
        }

        document.getElementById('milestoneDetailOwner').textContent = data.ownerName || '—';
        document.getElementById('milestoneDetailStartDate').textContent = data.startDateDisplay || '—';
        document.getElementById('milestoneDetailDueDate').textContent = data.dueDateDisplay || '—';
        document.getElementById('milestoneDetailCompletion').textContent = (data.completionPercentage ?? 0) + '%';

        var statusEl = document.getElementById('milestoneDetailStatus');
        if (data.status) {
            var variant = milestoneStatusVariants[data.status] || 'secondary';
            var label = milestoneStatusLabels[data.status] || data.status;
            statusEl.innerHTML = '<span class="badge bg-' + variant + '-soft text-' + variant + '">' + label + '</span>';
        } else {
            statusEl.textContent = '—';
        }

        var drawerEl = document.getElementById('milestoneDrawer');
        if (drawerEl && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }
    }

    function hideMilestoneDetailsDrawer() {
        var drawerEl = document.getElementById('milestoneDrawer');
        if (drawerEl && window.bootstrap) {
            var instance = bootstrap.Offcanvas.getInstance(drawerEl);
            if (instance) instance.hide();
        }
    }

    function editCurrentMilestone() {
        if (!currentMilestoneData) return;
        hideMilestoneDetailsDrawer();
        openMilestoneModal('edit', currentMilestoneData);
    }

    function cloneCurrentMilestone() {
        if (!currentMilestoneData) return;

        var cloneData = Object.assign({}, currentMilestoneData, {
            name: currentMilestoneData.name ? currentMilestoneData.name + @js(' ' . __('projects.clone_suffix')) : '',
        });

        hideMilestoneDetailsDrawer();
        openMilestoneModal('add', cloneData);
    }

    function deleteCurrentMilestone() {
        if (!currentMilestoneData || !currentMilestoneData.deleteUrl) return;

        confirmAction(@js(__('projects.confirm_remove_milestone')), function () {
            var form = document.getElementById('milestoneDeleteForm');
            form.action = currentMilestoneData.deleteUrl;
            form.submit();
        });
    }
</script>
