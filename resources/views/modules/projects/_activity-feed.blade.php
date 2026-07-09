{{-- Activity feed panel; expects $activities (Collection of ActivityLog), optional $viewAllUrl --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0 fw-bold text-dark">
            <i class="feather-activity me-2 text-primary"></i>{{ __('projects.activity') }}
        </h5>
        @isset($viewAllUrl)
            <a href="{{ $viewAllUrl }}" class="fs-12 fw-semibold">{{ __('projects.view_all') }}</a>
        @endisset
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush fs-13">
            @forelse ($activities as $activity)
                <li class="list-group-item border-0 border-bottom py-3">
                    <div class="d-flex align-items-start">
                        <div class="avatar-text avatar-sm bg-soft-primary text-primary fs-11 fw-bold me-3">
                            <i class="feather-clock"></i>
                        </div>
                        <div>
                            <span class="fw-semibold text-dark d-block">{{ $activity->title }}</span>
                            @if ($activity->description)
                                <span class="text-muted d-block fs-12">{{ $activity->description }}</span>
                            @endif
                            <span class="text-muted fs-11">
                                {{ $activity->triggeredBy?->name ?? __('projects.system') }}
                                · {{ $activity->created_at?->format('d/m/Y H:i') }}
                            </span>
                        </div>
                    </div>
                </li>
            @empty
                <li class="list-group-item border-0 text-center py-5 text-muted">
                    <i class="feather-activity fs-1 mb-2 d-block"></i>
                    {{ __('projects.no_activity') }}
                </li>
            @endforelse
        </ul>
    </div>
</div>
