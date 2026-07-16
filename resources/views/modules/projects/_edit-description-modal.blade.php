{{--
    Focused "Edit Description" modal — Description is the only Project field still edited
    through a full-form submit; every other field now uses inline editing (see
    resources/views/components/ui/inline-edit.blade.php). This still posts to the same
    UpdateProjectRequest/ProjectController::update() as before, so the other fields ride
    along as hidden inputs at their current value to satisfy that request's validation
    without re-exposing them for editing here.

    old()/$errors are global to the request, not scoped per-form, and this modal is rendered
    once per project (one per row on the index listing, plus one on the show page) — $useOld
    gates old()/errors to only the modal that actually submitted, same convention as the
    project quick-create modal.
--}}
@php
    $modalKey = 'editProjectModal-' . $project->id;
    $useOld = old('_modal') === $modalKey;
    $old = fn (string $key, $default = null) => $useOld ? old($key, $default) : $default;
@endphp
<x-ui.modal id="{{ $modalKey }}" title="{{ __('projects.edit_description') }} — {{ $project->project_code }}" size="md" :scrollable="true" :static="true" :showFooter="false">
    <form action="{{ route('projects.update', $project) }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="_modal" value="{{ $modalKey }}">

        <input type="hidden" name="name" value="{{ $old('name', $project->name) }}">
        <input type="hidden" name="customer_id" value="{{ $old('customer_id', $project->customer_id) }}">
        <input type="hidden" name="owner_id" value="{{ $old('owner_id', $project->owner_id) }}">
        <input type="hidden" name="manager_id" value="{{ $old('manager_id', $project->manager_id) }}">
        <input type="hidden" name="start_date" value="{{ $old('start_date', $project->start_date?->format('Y-m-d')) }}">
        <input type="hidden" name="end_date" value="{{ $old('end_date', $project->end_date?->format('Y-m-d')) }}">
        <input type="hidden" name="budget_type" value="{{ $old('budget_type', $project->budget_type) }}">
        <input type="hidden" name="budget_amount" value="{{ $old('budget_amount', $project->budget_amount) }}">
        <input type="hidden" name="budget_hours" value="{{ $old('budget_hours', $project->budget_hours) }}">
        <input type="hidden" name="billing_method" value="{{ $old('billing_method', $project->billing_method) }}">
        <input type="hidden" name="priority" value="{{ $old('priority', $project->priority) }}">
        <input type="hidden" name="status" value="{{ $old('status', $project->status) }}">

        <x-ui.odoo-form-ui type="textarea" label="{{ __('projects.description') }}" name="description" rows="5"
            :errorText="$useOld ? $errors->first('description') : null">{{ $old('description', $project->description) }}</x-ui.odoo-form-ui>

        <div class="d-flex gap-2 justify-content-end pt-3 border-top mt-4">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-primary px-4">
                <i class="feather-check-circle me-2"></i>{{ __('projects.save_changes') }}
            </button>
        </div>
    </form>
</x-ui.modal>
