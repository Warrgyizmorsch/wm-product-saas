<x-ui.modal id="deleteMilestoneModal" title="{{ __('projects.remove_milestone') }}" size="md" :showFooter="false">
    <form id="deleteMilestoneForm" method="POST" action="">
        @csrf
        @method('DELETE')

        <div id="deleteMilestoneWithTasksWarning" class="d-none">
            <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
                <i class="feather-alert-triangle fs-4 text-warning flex-shrink-0 mt-1"></i>
                <div class="fs-13">
                    <span id="deleteMilestoneWarningText"></span>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold text-dark fs-12 mb-2">{{ __('projects.choose_task_action') }}</label>
                
                <div class="form-check p-3 border rounded-3 mb-2 bg-light">
                    <input class="form-check-input ms-0 me-2" type="radio" name="task_action" id="taskActionUnlink" value="unlink" checked>
                    <label class="form-check-label" for="taskActionUnlink">
                        <strong class="text-dark d-block fs-13">{{ __('projects.unlink_tasks_option') }}</strong>
                        <span class="text-muted fs-12">{{ __('projects.unlink_tasks_hint') }}</span>
                    </label>
                </div>

                <div class="form-check p-3 border rounded-3 mb-3 bg-light">
                    <input class="form-check-input ms-0 me-2" type="radio" name="task_action" id="taskActionDelete" value="delete">
                    <label class="form-check-label" for="taskActionDelete">
                        <strong class="text-danger d-block fs-13">{{ __('projects.delete_tasks_option') }}</strong>
                        <span class="text-muted fs-12">{{ __('projects.delete_tasks_hint') }}</span>
                    </label>
                </div>
            </div>
        </div>

        <div id="deleteMilestoneSimpleWarning" class="mb-3 fs-13 text-muted">
            {{ __('projects.confirm_remove_milestone') }}
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-danger px-3">{{ __('projects.confirm_remove') }}</button>
        </div>
    </form>
</x-ui.modal>

<script>
    function openDeleteMilestoneModal(deleteUrl, milestoneName, tasksCount) {
        var modalEl = document.getElementById('deleteMilestoneModal');
        var formEl = document.getElementById('deleteMilestoneForm');
        var tasksWarningEl = document.getElementById('deleteMilestoneWithTasksWarning');
        var simpleWarningEl = document.getElementById('deleteMilestoneSimpleWarning');
        var warningTextEl = document.getElementById('deleteMilestoneWarningText');

        if (!modalEl || !formEl) return;

        formEl.action = deleteUrl;

        if (tasksCount > 0) {
            tasksWarningEl.classList.remove('d-none');
            simpleWarningEl.classList.add('d-none');
            if (warningTextEl) {
                var template = @js(__('projects.milestone_has_tasks_warning'));
                warningTextEl.innerHTML = template.replace(':count', '<strong>' + tasksCount + '</strong>');
            }
            var unlinkRadio = document.getElementById('taskActionUnlink');
            if (unlinkRadio) unlinkRadio.checked = true;
        } else {
            tasksWarningEl.classList.add('d-none');
            simpleWarningEl.classList.remove('d-none');
        }

        if (window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
</script>
