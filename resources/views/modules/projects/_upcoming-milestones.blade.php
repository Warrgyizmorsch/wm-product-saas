<div class="d-flex align-items-center justify-content-between mb-3">
    <h6 class="fw-bold text-uppercase text-muted fs-11 mb-0">
        <i class="feather-flag me-1 text-primary"></i>{{ __('projects.upcoming_milestones') }}
    </h6>
    <a href="{{ route('projects.show', $project) }}?tab=milestones#tab-milestones" class="fs-12 fw-semibold text-primary text-decoration-none">
        {{ __('projects.view_all') }}
    </a>
</div>

@forelse ($dashboard['milestones']['upcoming'] as $milestone)
    <div class="d-flex align-items-center justify-content-between {{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
        <div>
            <div class="fw-semibold text-dark fs-13">{{ $milestone->name }}</div>
            <div class="fs-11 text-muted">{{ $milestone->due_date?->format('d M Y') ?: '—' }}</div>
        </div>
        @php
            $milestoneStatusVariant = match ($milestone->status) {
                'Active' => 'success',
                'On Hold' => 'warning',
                'Completed' => 'primary',
                'Closed' => 'dark',
                default => 'secondary',
            };
        @endphp
        <x-ui.badge variant="{{ $milestoneStatusVariant }}" soft>
            {{ __('projects.statuses.' . $milestone->status) }}
        </x-ui.badge>
    </div>
@empty
    <div class="text-center text-muted fs-12 py-3">{{ __('projects.no_upcoming_milestones') }}</div>
@endforelse
