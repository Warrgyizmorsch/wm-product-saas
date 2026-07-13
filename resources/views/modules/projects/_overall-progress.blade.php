<h6 class="fw-bold text-uppercase text-muted fs-11 mb-3">
    <i class="feather-trending-up me-1 text-primary"></i>{{ __('projects.overall_progress') }}
</h6>

<div class="d-flex align-items-center justify-content-between mb-2">
    <span class="fs-12 text-muted text-uppercase fw-semibold">{{ __('projects.tasks') }}</span>
    <span class="fs-12 fw-bold text-dark">{{ $dashboard['tasks']['done'] }} / {{ $dashboard['tasks']['total'] }}</span>
</div>
<div class="progress ht-8 mb-3">
    <div class="progress-bar bg-success" style="width: {{ $dashboard['tasks']['percent'] }}%"></div>
</div>

<div class="d-flex align-items-center justify-content-between mb-2">
    <span class="fs-12 text-muted text-uppercase fw-semibold">{{ __('projects.milestones') }}</span>
    <span class="fs-12 fw-bold text-dark">{{ $dashboard['milestones']['completed'] }} / {{ $dashboard['milestones']['total'] }}</span>
</div>
<div class="progress ht-8 {{ $dashboard['hours']['budget'] > 0 ? 'mb-3' : '' }}">
    <div class="progress-bar bg-primary" style="width: {{ $dashboard['milestones']['percent'] }}%"></div>
</div>

@if ($dashboard['hours']['budget'] > 0)
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="fs-12 text-muted text-uppercase fw-semibold">{{ __('projects.hours_tracked') }}</span>
        <span class="fs-12 fw-bold text-dark">{{ number_format($dashboard['hours']['tracked'], 1) }} / {{ number_format($dashboard['hours']['budget'], 1) }}</span>
    </div>
    <div class="progress ht-8">
        <div class="progress-bar bg-warning" style="width: {{ $dashboard['hours']['percent'] }}%"></div>
    </div>
@endif
