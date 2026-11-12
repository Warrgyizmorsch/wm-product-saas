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
         data-no-results-text="{{ __('projects.collaborator_no_results') }}"
         data-can-manage="{{ $canManageMembers ? '1' : '0' }}"
         data-remove-label="{{ __('projects.remove_collaborator') }}"
         data-confirm-remove-template="{{ __('projects.confirm_remove_collaborator', ['name' => '__NAME__']) }}"
         data-remove-success-text="{{ __('projects.member_removed') }}"
         data-remove-error-text="{{ __('projects.something_went_wrong') }}"
         data-billing-method="{{ $project->billing_method }}"
         data-currency-symbol="{{ $currencySymbol }}">
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
                        style="z-index: 20; max-height: 220px; overflow-y: auto; top: 100%; left: 0;"></ul>
                </div>
            @endif
        </div>

        <div class="fs-11 text-muted mt-2" id="projectCollaboratorCount">
            {{ __('projects.collaborators_active_count', ['count' => $collaborators->count()]) }}
        </div>
    </div>

    @if ($canManageMembers)
        <div class="modal fade" id="modalAddCollaboratorRate" tabindex="-1" aria-labelledby="modalAddCollaboratorRateLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content shadow-sm border">
                    <div class="modal-header py-2 px-3 border-bottom">
                        <h6 class="modal-title fs-13 fw-bold" id="modalAddCollaboratorRateLabel">
                            <i class="feather-user-plus me-1 text-primary"></i>{{ __('projects.add_collaborator') }}
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3 px-3">
                        <div class="mb-3">
                            <label class="form-label fs-11 text-uppercase text-muted fw-bold mb-1">{{ __('projects.collaborator') ?? 'Collaborator' }}</label>
                            <div class="fs-13 fw-semibold text-dark" id="modalCollaboratorUserName">—</div>
                        </div>
                        <div class="mb-2">
                            <label for="modalCollaboratorRateInput" class="form-label fs-12 fw-semibold mb-1">
                                {{ __('projects.rate_per_hour') ?? 'Rate / Hour' }} ({{ $currencySymbol }}) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ $currencySymbol }}</span>
                                <input type="number" step="0.01" min="0" max="99999999.99"
                                       class="form-control form-control-sm"
                                       id="modalCollaboratorRateInput"
                                       placeholder="0.00" required>
                            </div>
                            <div class="text-danger fs-11 mt-1 d-none" id="modalCollaboratorRateError">
                                {{ __('projects.rate_required_for_user_billing') }}
                            </div>
                            <div class="form-text fs-11 text-muted mt-1">
                                {{ __('projects.user_billing_rate_help') }}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer py-2 px-3 bg-light border-top">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
                        <button type="button" class="btn btn-sm btn-primary" id="modalCollaboratorSubmitBtn">
                            <i class="feather-check me-1"></i>{{ __('projects.add') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
