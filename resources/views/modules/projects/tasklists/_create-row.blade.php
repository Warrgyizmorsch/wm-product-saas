@once
    @push('styles')
        <style>
            .tasklist-row-creating {
                background-color: rgba(var(--bs-primary-rgb), 0.04);
                border-left: 3px solid var(--bs-primary);
                animation: taskListRowSlideIn 150ms ease-out;
            }
            .tasklist-row-created-flash {
                animation: taskListRowFlash 900ms ease-out;
            }
            @keyframes taskListRowSlideIn {
                from { opacity: 0; transform: translateY(-6px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes taskListRowFlash {
                0% { background-color: rgba(var(--bs-success-rgb), 0.12); }
                100% { background-color: transparent; }
            }
        </style>
    @endpush
@endonce

<template id="taskListCreateRowTemplate"
          data-store-url="{{ route('projects.tasklists.store', $project) }}"
          data-milestone-id="{{ $milestone->id ?? '' }}">
    <div class="border rounded-3 task-list-card tasklist-row-creating mb-3 p-3">
        <div class="d-flex align-items-start gap-3">
            <div class="flex-grow-1" style="min-width: 0;">
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="flex-grow-1" style="min-width: 200px;">
                        <input type="text" class="form-control form-control-sm tasklist-create-name"
                               placeholder="{{ __('projects.tasklist_name') }}" maxlength="255">
                        <div class="invalid-feedback d-block fs-11 tasklist-create-error" data-field="name"></div>
                    </div>

                    <div class="d-flex align-items-center gap-1 flex-shrink-0 pt-1">
                        <button type="button" class="btn btn-sm btn-primary tasklist-create-submit" title="{{ __('projects.create') }}">
                            <i class="feather-check"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light-brand tasklist-create-cancel" title="{{ __('projects.cancel') }}">
                            <i class="feather-x"></i>
                        </button>
                    </div>
                </div>

                <div class="fs-11 text-danger tasklist-create-general-error mt-1 d-none"></div>
            </div>
        </div>
    </div>
</template>
