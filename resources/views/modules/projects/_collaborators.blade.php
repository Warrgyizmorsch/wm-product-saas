@php
    $collaborators = $members->where('is_active', true)->sortBy(fn ($member) => mb_strtolower($member->user?->name ?: ''))->values();
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
         data-remove-error-text="{{ __('projects.something_went_wrong') }}">
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
@endif
