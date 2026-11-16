@php
    $collaborators = $members->where('is_active', true)->sortBy(fn ($member) => mb_strtolower($member->user?->name ?: ''))->values();
    $currencySymbol = active_currency_symbol();
@endphp

<div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase">
        <i class="feather-users me-1 text-primary"></i>{{ __('projects.collaborators') }}
    </h6>
</div>

@if ($collaborators->isEmpty() && !$canManageMembers)
    <p class="text-muted fs-12 mb-0">{{ __('projects.no_collaborators') }}</p>
@else
    <div class="project-collaborators"
         id="projectCollaborators"
         data-search-url="{{ route('projects.collaborators.search', $project) }}"
         data-store-url="{{ route('projects.collaborators.store', $project) }}"
         data-remove-url-template="{{ route('projects.collaborators.destroy', [$project, '__MEMBER__']) }}"
         data-tasks-url-template="{{ route('projects.collaborators.active-tasks', [$project, '__MEMBER__']) }}"
         data-no-results-text="{{ __('projects.collaborator_no_results') }}"
         data-can-manage="{{ $canManageMembers ? '1' : '0' }}"
         data-remove-label="{{ __('projects.remove_collaborator') }}"
         data-confirm-remove-template="{{ __('projects.confirm_remove_collaborator', ['name' => '__NAME__']) }}"
         data-remove-success-text="{{ __('projects.member_removed') }}"
         data-remove-error-text="{{ __('projects.something_went_wrong') }}"
         data-quick-reassign-placeholder="{{ __('projects.select_member_or_unassigned') }}"
         data-none-option="{{ __('projects.none_option') }}"
         data-billing-method="{{ $project->billing_method }}"
         data-budget-type="{{ $project->budget_type }}"
         data-currency-symbol="{{ $currencySymbol }}"
         data-update-url-template="{{ route('projects.members.update', [$project, '__MEMBER__']) }}"
         data-edit-rate-label="{{ __('projects.edit_rate') }}"
         data-update-rate-label="{{ __('projects.update_rate') }}"
         data-update-rate-title="{{ __('projects.update_billing_rate') }}"
         data-add-collaborator-title="{{ __('projects.add_collaborator') }}"
         data-rate-label="{{ __('projects.rate_per_hour') }}">
        <div class="d-flex align-items-center flex-wrap" id="projectCollaboratorAvatars">
            @foreach ($collaborators as $index => $collaborator)
                @php
                    $collaboratorRole = ((int) $collaborator->user_id === (int) $project->owner_id)
                        ? __('projects.owner')
                        : (($project->manager_id !== null && (int) $project->manager_id === (int) $collaborator->user_id)
                            ? __('projects.role_manager')
                            : ($collaborator->project_role ?: __('projects.role_collaborator')));
                @endphp
                @include('modules.projects._collaborator_avatar', [
                    'name' => $collaborator->user?->name ?: '?',
                    'index' => $index,
                    'memberId' => $collaborator->id,
                    'role' => $collaboratorRole,
                    'rate' => $collaborator->rate_per_hour,
                ])
            @endforeach

            @if ($canManageMembers)
                @if ($project->isSetupComplete())
                    <button type="button"
                            class="avatar-text avatar-md bg-light text-muted border border-2 border-white collaborator-add-btn"
                            style="{{ $collaborators->isNotEmpty() ? 'margin-left: -10px;' : '' }}"
                            title="{{ __('projects.add_collaborator') }}"
                            id="projectCollaboratorAddBtn">
                        <i class="feather-plus"></i>
                    </button>

                    <div class="collaborator-search-wrapper flex-grow-1 position-relative ms-2 d-none" id="projectCollaboratorSearchWrapper">
                        <input type="text" class="form-control form-control-sm" id="projectCollaboratorSearchInput"
                               placeholder="{{ __('projects.collaborator_search_placeholder') }}" autocomplete="off">
                        <ul class="list-group position-absolute w-100 shadow-sm d-none"
                            id="projectCollaboratorResults"
                            style="z-index: 20; max-height: 240px; overflow-y: auto; overflow-x: hidden; top: 100%; left: 0; min-width: 240px;"></ul>
                    </div>
                @else
                    <div class="w-100 mt-2">
                        <div class="alert alert-warning py-2 px-2.5 fs-11 mb-0 rounded-2 border-0 d-flex align-items-center gap-2"
                             style="background-color: #fffbeb; border-left: 3px solid #f59e0b !important; color: #92400e;">
                            <i class="feather-alert-triangle fs-14 flex-shrink-0" style="color: #d97706;"></i>
                            <div>
                                <span class="fw-bold">{{ __('projects.setup_required_before_collaborators') }}:</span>
                                <span>{{ implode(', ', $project->missingSetupFields()) }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <div class="fs-11 text-muted mt-2" id="projectCollaboratorCount">
            {{ __('projects.collaborators_active_count', ['count' => $collaborators->count()]) }}
        </div>
    </div>

    @if ($canManageMembers)
        <div class="modal fade" id="modalAddCollaboratorRate" tabindex="-1" aria-labelledby="modalAddCollaboratorRateLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                <div class="modal-content shadow rounded-3 border-0 overflow-hidden">
                    {{-- Modal Header --}}
                    <div class="modal-header py-3 px-4 bg-light border-bottom align-items-center">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="feather-user-plus fs-16"></i>
                            </div>
                            <div>
                                <h6 class="modal-title fw-bold text-dark mb-0 fs-14" id="modalAddCollaboratorRateLabel">
                                    {{ __('projects.add_collaborator') }}
                                </h6>
                                <small class="text-muted fs-11">{{ __('projects.set_billing_rate_desc') }}</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="modal-body p-4">
                        {{-- Collaborator Summary Card --}}
                        <div class="p-3 rounded-3 border mb-3 d-flex align-items-center justify-content-between" style="background-color: #f8fafc;">
                            <div class="d-flex align-items-center gap-2.5 overflow-hidden me-2">
                                <div class="avatar-text avatar-md bg-primary text-white rounded-circle fw-bold fs-13 flex-shrink-0" id="modalCollaboratorAvatar">
                                    ?
                                </div>
                                <div class="overflow-hidden">
                                    <h6 class="fw-bold text-dark mb-0 fs-13 text-truncate" id="modalCollaboratorUserName">—</h6>
                                    <small class="text-muted fs-11 text-truncate d-block" id="modalCollaboratorUserEmail"></small>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0 d-none" id="modalCollaboratorCostWrap">
                                <span class="text-muted fs-10 text-uppercase fw-semibold d-block">Shift Cost</span>
                                <span class="badge bg-white text-dark border shadow-sm fw-bold fs-11 py-1 px-2" id="modalCollaboratorBaselineCostBadge">
                                    ₹0.00 / hr
                                </span>
                            </div>
                        </div>

                        {{-- Rate Input Field --}}
                        <div class="mb-3">
                            <label for="modalCollaboratorRateInput" class="form-label fs-12 fw-bold text-dark mb-1">
                                {{ __('projects.rate_per_hour') }} ({{ $currencySymbol }}) <span class="text-danger" id="modalCollaboratorRateRequiredAsterisk">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold px-3">{{ $currencySymbol }}</span>
                                <input type="number" step="0.01" min="0" max="99999999.99"
                                       class="form-control form-control-md fw-semibold fs-14"
                                       id="modalCollaboratorRateInput"
                                       placeholder="0.00">
                            </div>
                            <div class="text-danger fs-11 mt-1 d-none" id="modalCollaboratorRateError">
                                {{ __('projects.rate_required_for_user_billing') }}
                            </div>
                            <div class="form-text fs-11 text-muted mt-1.5" id="modalCollaboratorRateHelp">
                                {{ __('projects.user_billing_rate_help') }}
                            </div>
                        </div>

                        {{-- Margin Warning Alert --}}
                        <div class="alert alert-warning py-2.5 px-3 fs-12 mb-0 rounded-3 border-0 shadow-sm d-none"
                             id="modalCollaboratorCostWarning"
                             style="background-color: #fffbeb; border-left: 4px solid #f59e0b !important; color: #92400e;">
                            <div class="d-flex align-items-start gap-2">
                                <i class="feather-alert-triangle fs-16 flex-shrink-0 mt-0.5" style="color: #d97706;"></i>
                                <div class="lh-sm" id="modalCollaboratorCostWarningText"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="modal-footer py-2.5 px-4 bg-light border-top d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-sm btn-light border px-3 fw-medium" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
                        <button type="button" class="btn btn-sm btn-primary px-3 fw-semibold d-inline-flex align-items-center" id="modalCollaboratorSubmitBtn">
                            <i class="feather-check me-1.5"></i>{{ __('projects.add_collaborator') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal Reassign Tasks Before Removal --}}
        <div class="modal fade" id="modalReassignTasksBeforeRemoval" tabindex="-1" aria-labelledby="modalReassignTasksBeforeRemovalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content shadow rounded-3 border-0 overflow-hidden">
                    {{-- Modal Header --}}
                    <div class="modal-header py-3 px-4 bg-light border-bottom align-items-center">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="feather-alert-triangle fs-16 text-warning"></i>
                            </div>
                            <div>
                                <h6 class="modal-title fw-bold text-dark mb-0 fs-14" id="modalReassignTasksBeforeRemovalLabel">
                                    {{ __('projects.reassign_tasks_before_removal') }}
                                </h6>
                                <small class="text-muted fs-11" id="modalReassignTasksSubtitle">
                                    {{ __('projects.reassign_tasks_desc') }}
                                </small>
                            </div>
                        </div>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="modal-body p-4">
                        {{-- Quick Reassign All Shortcut --}}
                        <div class="p-3 rounded-3 border mb-3 bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="feather-fast-forward text-primary fs-14"></i>
                                <span class="fs-12 fw-semibold text-dark">{{ __('projects.quick_reassign_all_to') }}:</span>
                            </div>
                            <div style="min-width: 240px;">
                                <select class="form-select form-select-sm" id="quickReassignAllSelect">
                                    <option value="">{{ __('projects.select_member_or_unassigned') }}</option>
                                    <option value="__unassigned__">— {{ __('projects.none_option') }} —</option>
                                </select>
                            </div>
                        </div>

                        {{-- Granular Tasks Table --}}
                        <div class="table-responsive border rounded-3" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light fs-11 text-uppercase text-muted sticky-top">
                                    <tr>
                                        <th class="ps-3 py-2" style="width: 120px;">{{ __('projects.code') }}</th>
                                        <th class="py-2">{{ __('projects.name') }}</th>
                                        <th class="py-2" style="width: 110px;">{{ __('projects.status') }}</th>
                                        <th class="py-2 pe-3" style="width: 240px;">{{ __('projects.reassign_to') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="reassignTasksTableBody" class="fs-12">
                                    {{-- Rendered dynamically via JS --}}
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-danger py-2 px-3 fs-11 mt-3 mb-0 rounded-2 d-none" id="modalReassignTasksError"></div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="modal-footer py-2.5 px-4 bg-light border-top d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-sm btn-light border px-3 fw-medium" data-bs-dismiss="modal">
                            {{ __('projects.cancel') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-danger px-3 fw-semibold d-inline-flex align-items-center gap-1.5" id="btnConfirmReassignAndRemove">
                            <i class="feather-user-x"></i>
                            <span>{{ __('projects.reassign_and_remove') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
