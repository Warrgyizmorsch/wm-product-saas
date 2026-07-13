<x-ui.drawer id="memberDrawer" title="{{ __('projects.add_member') }}" position="end" style="width: 480px; max-width: 100%;">
    <div id="memberActionsBar" class="d-none justify-content-end gap-2 mb-3">
        <button type="button" class="btn btn-light-brand btn-sm" onclick="toggleCurrentMemberActive()">
            <i class="feather feather-slash me-1"></i>{{ __('projects.deactivate') }}
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteCurrentMember()">
            <i class="feather feather-trash-2 me-1"></i>{{ __('projects.remove') }}
        </button>
    </div>

    <form id="memberForm" method="POST" action="{{ route('projects.members.store', $project) }}">
        @csrf
        <input type="hidden" name="_method" id="memberMethodField" value="POST">
        @include('modules.projects.members._form')
    </form>

    <form id="memberDeleteForm" method="POST" action="" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <form id="memberToggleActiveForm" method="POST" action="" class="d-none">
        @csrf
        @method('PATCH')
    </form>

    <x-slot name="footer">
        <button type="button" class="btn btn-light-brand" data-bs-dismiss="offcanvas">{{ __('projects.cancel') }}</button>
        <button type="submit" form="memberForm" id="memberSubmitBtn" class="btn btn-primary">{{ __('projects.add_member') }}</button>
    </x-slot>
</x-ui.drawer>

<script>
    var currentMemberData = null;

    function openMemberDrawer(mode, data) {
        data = data || {};
        currentMemberData = mode === 'edit' ? data : null;

        var form = document.getElementById('memberForm');
        var methodField = document.getElementById('memberMethodField');
        var modeField = document.getElementById('member_form_mode');
        var idField = document.getElementById('member_form_id');
        var titleEl = document.getElementById('memberDrawerLabel');
        var submitBtn = document.getElementById('memberSubmitBtn');
        var actionsBar = document.getElementById('memberActionsBar');
        var actionsBtn = actionsBar ? actionsBar.querySelector('button') : null;

        if (mode === 'edit') {
            form.action = data.updateUrl;
            methodField.value = 'PUT';
            modeField.value = 'edit';
            idField.value = data.id;
            if (titleEl) titleEl.textContent = @js(__('projects.edit_member'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.edit_member'));
            if (actionsBar) {
                actionsBar.classList.remove('d-none');
                actionsBar.classList.add('d-flex');
            }
            if (actionsBtn) {
                actionsBtn.innerHTML = data.isActive
                    ? '<i class="feather feather-slash me-1"></i>' + @js(__('projects.deactivate'))
                    : '<i class="feather feather-check me-1"></i>' + @js(__('projects.activate'));
            }
        } else {
            form.action = @js(route('projects.members.store', $project));
            methodField.value = 'POST';
            modeField.value = 'add';
            idField.value = '';
            if (titleEl) titleEl.textContent = @js(__('projects.add_member'));
            if (submitBtn) submitBtn.textContent = @js(__('projects.add_member'));
            if (actionsBar) {
                actionsBar.classList.remove('d-flex');
                actionsBar.classList.add('d-none');
            }
        }

        setMemberFieldValue('member_project_role', data.projectRole || '');
        setMemberFieldValue('member_rate_per_hour', data.ratePerHour ?? '');
        setMemberFieldValue('member_cost_per_hour', data.costPerHour ?? '');
        setMemberFieldValue('member_budget_hours', data.budgetHours ?? '');
        setMemberUser(data.userId);

        var drawerEl = document.getElementById('memberDrawer');
        if (drawerEl && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }
    }

    function setMemberFieldValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function setMemberUser(value) {
        var el = document.getElementById('member_user_id');
        if (!el) return;
        el.value = value || '';
        if (window.jQuery && jQuery(el).data('select2')) {
            jQuery(el).trigger('change');
        }
    }

    function toggleCurrentMemberActive() {
        if (!currentMemberData || !currentMemberData.toggleActiveUrl) return;

        confirmAction(@js(__('projects.confirm_toggle_active')), function () {
            var form = document.getElementById('memberToggleActiveForm');
            form.action = currentMemberData.toggleActiveUrl;
            form.submit();
        });
    }

    function deleteCurrentMember() {
        if (!currentMemberData || !currentMemberData.deleteUrl) return;

        confirmAction(@js(__('projects.confirm_remove_member')), function () {
            var form = document.getElementById('memberDeleteForm');
            form.action = currentMemberData.deleteUrl;
            form.submit();
        });
    }
</script>
