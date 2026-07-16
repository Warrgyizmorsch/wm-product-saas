<div class="border rounded-3 p-3 mb-4">
    <h6 class="fw-bold text-dark mb-3"><i class="feather-align-left me-2 text-primary"></i>{{ __('projects.description') }}</h6>

    @if ($canManageTask)
        <div class="fs-13 text-dark">
            <x-ui.inline-edit field="description" :value="$task->description" :url="route('projects.tasks.field', [$project, $task])" type="textarea" :label="__('projects.description')" />
        </div>
    @else
        <p class="fs-13 text-dark mb-0">{{ $task->description ?: '—' }}</p>
    @endif
</div>
