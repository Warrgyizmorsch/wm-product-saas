@php
    $collaborators = $members->where('is_active', true)->values();
    $visibleCollaborators = $collaborators->take(4);
    $overflowCount = max($collaborators->count() - $visibleCollaborators->count(), 0);
    $avatarVariants = ['primary', 'success', 'info', 'warning', 'danger'];
@endphp

<div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase">
        <i class="feather-users me-1 text-primary"></i>{{ __('projects.collaborators') }}
    </h6>
</div>

@if ($collaborators->isEmpty())
    <p class="text-muted fs-12 mb-0">{{ __('projects.no_collaborators') }}</p>
@else
    <div class="d-flex align-items-center">
        @foreach ($visibleCollaborators as $index => $collaborator)
            @php
                $name = $collaborator->user?->name ?: '?';
                $initials = strtoupper(collect(preg_split('/\s+/', trim($name)))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''));
                $variant = $avatarVariants[$index % count($avatarVariants)];
            @endphp
            <div class="avatar-text avatar-md bg-soft-{{ $variant }} text-{{ $variant }} border border-2 border-white"
                 style="{{ $index > 0 ? 'margin-left: -10px;' : '' }}"
                 title="{{ $name }}">
                {{ $initials ?: '?' }}
            </div>
        @endforeach

        @if ($overflowCount > 0)
            <div class="avatar-text avatar-md bg-soft-secondary text-secondary border border-2 border-white"
                 style="margin-left: -10px;"
                 title="+{{ $overflowCount }} {{ __('projects.collaborators') }}">
                +{{ $overflowCount }}
            </div>
        @endif
    </div>

    <div class="fs-11 text-muted mt-2">
        {{ __('projects.collaborators_active_count', ['count' => $collaborators->count()]) }}
    </div>
@endif
