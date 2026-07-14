{{-- Shared "Edit Project" modal; expects $project, $customers, $users --}}
<x-ui.modal id="editProjectModal-{{ $project->id }}" title="{{ __('projects.edit_code', ['code' => $project->project_code]) }}" size="lg" :scrollable="true" :static="true" :showFooter="false">
    <form action="{{ route('projects.update', $project) }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="_modal" value="editProjectModal-{{ $project->id }}">

        @include('modules.projects._form-fields', [
            'project' => $project,
            'customers' => $customers,
            'users' => $users,
            'statusOptions' => \App\Domains\Projects\Models\Project::EDITABLE_STATUSES,
        ])

        <div class="d-flex gap-2 justify-content-end pt-3 border-top mt-4">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-primary px-4">
                <i class="feather-check-circle me-2"></i>{{ __('projects.update_project') }}
            </button>
        </div>
    </form>
</x-ui.modal>
