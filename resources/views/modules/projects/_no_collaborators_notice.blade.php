{{--
    The in-page picker (data-action="open-project-collaborators") is only
    wired up by collaborators.js on the project show page, where the
    #projectCollaborators widget it opens actually lives. Everywhere else
    this partial is included (milestone/task list workspace pages) there is
    no such widget on the page, so we link to the project's Collaborators
    panel instead of rendering a button with no listener.
--}}
<div class="fs-12 text-muted d-flex align-items-center gap-2 flex-wrap">
    <span>{{ __('projects.no_collaborators_available') }}</span>
    @if (request()->routeIs('projects.show'))
        <button type="button" class="btn btn-sm btn-light-brand py-0 px-2" data-action="open-project-collaborators">
            {{ __('projects.add_collaborator') }}
        </button>
    @else
        <a href="{{ route('projects.show', $project) }}#projectCollaborators" class="btn btn-sm btn-light-brand py-0 px-2">
            {{ __('projects.manage_collaborators') }}
        </a>
    @endif
</div>
