@once
    @push('styles')
        <style>
            .milestone-row-creating {
                background-color: rgba(var(--bs-primary-rgb), 0.04);
                border-left: 3px solid var(--bs-primary);
                animation: milestoneRowSlideIn 150ms ease-out;
            }
            .milestone-row-created-flash {
                animation: milestoneRowFlash 900ms ease-out;
            }
            @keyframes milestoneRowSlideIn {
                from { opacity: 0; transform: translateY(-6px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes milestoneRowFlash {
                0% { background-color: rgba(var(--bs-success-rgb), 0.12); }
                100% { background-color: transparent; }
            }
        </style>
    @endpush
@endonce

<template id="milestoneCreateRowTemplate" data-store-url="{{ route('projects.milestones.store', $project) }}">
    <div class="milestone-row milestone-row-creating border-bottom py-3 px-2 px-md-3">
        <div class="d-flex align-items-start gap-3">
            <div class="avatar-text avatar-md rounded-circle bg-soft-primary text-primary flex-shrink-0 mt-1">
                <i class="feather-flag"></i>
            </div>

            <div class="flex-grow-1" style="min-width: 0;">
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="flex-grow-1" style="min-width: 200px;">
                        <input type="text" class="form-control form-control-sm milestone-create-name"
                               placeholder="{{ __('projects.milestone_name') }}" maxlength="255">
                        <div class="invalid-feedback d-block fs-11 milestone-create-error" data-field="name"></div>
                    </div>

                    <div style="min-width: 180px; width: 200px;">
                        <select class="form-select form-select-sm milestone-create-owner" data-select2-selector="user">
                            <option value="">{{ __('projects.select_user') }}</option>
                            @foreach ($activeMemberOptions as $memberOption)
                                <option value="{{ $memberOption->id }}">{{ $memberOption->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block fs-11 milestone-create-error" data-field="owner_id"></div>
                    </div>

                    <div style="min-width: 150px; width: 160px;">
                        <input type="date" class="form-control form-control-sm milestone-create-due-date">
                        <div class="invalid-feedback d-block fs-11 milestone-create-error" data-field="due_date"></div>
                    </div>

                    <div class="d-flex align-items-center gap-1 flex-shrink-0 pt-1">
                        <button type="button" class="btn btn-sm btn-primary milestone-create-submit" title="{{ __('projects.create') }}">
                            <i class="feather-check"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light-brand milestone-create-cancel" title="{{ __('projects.cancel') }}">
                            <i class="feather-x"></i>
                        </button>
                    </div>
                </div>

                <div class="fs-11 text-danger milestone-create-general-error mt-1 d-none"></div>
            </div>
        </div>
    </div>
</template>
