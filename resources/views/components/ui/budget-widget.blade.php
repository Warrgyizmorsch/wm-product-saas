@props([
    'budget' => '$0',
    'spent' => '$0',
    'remaining' => '$0',
    'burnPercentage' => 0, // precomputed percentage e.g. 85 or 115
    'currency' => '$',
    'status' => 'normal', // normal, warning, over_budget
])

@php
    $statusMap = [
        'normal' => ['bg' => 'bg-soft-success', 'text' => 'text-success', 'label' => 'Healthy Budget', 'icon' => 'feather-check-circle', 'bar_color' => 'success'],
        'warning' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'label' => 'Near Threshold', 'icon' => 'feather-alert-triangle', 'bar_color' => 'warning'],
        'over_budget' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'label' => 'Over Budget Alert', 'icon' => 'feather-alert-octagon', 'bar_color' => 'danger'],
    ];

    $config = $statusMap[strtolower($status)] ?? $statusMap['normal'];
@endphp

<div {{ $attributes->merge(['class' => 'card stretch stretch-full border-0 shadow-sm mb-4']) }}>
    <div class="card-body p-4">
        <!-- Header Row -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center">
                    <i class="feather-dollar-sign fs-18"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0 fs-15">{{ __('Project Financial Budget') }}</h6>
                    <span class="fs-12 text-muted">{{ __('Financial burn rate & allocation') }}</span>
                </div>
            </div>

            <span class="badge {{ $config['bg'] }} {{ $config['text'] }} px-2.5 py-1.5 fs-12 fw-bold d-inline-flex align-items-center gap-1">
                <i class="{{ $config['icon'] }} fs-11"></i>
                <span>{{ __($config['label']) }}</span>
            </span>
        </div>

        <!-- Main Metrics Grid -->
        <div class="row g-3 mb-3 p-3 bg-light rounded-3 text-center">
            <div class="col-4 border-end">
                <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('Total Budget') }}</span>
                <h5 class="fw-bold text-dark mb-0 fs-16">{{ is_numeric($budget) ? $currency . number_format($budget, 2) : $budget }}</h5>
            </div>
            <div class="col-4 border-end">
                <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('Actual Spent') }}</span>
                <h5 class="fw-bold text-primary mb-0 fs-16">{{ is_numeric($spent) ? $currency . number_format($spent, 2) : $spent }}</h5>
            </div>
            <div class="col-4">
                <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('Remaining') }}</span>
                <h5 class="fw-bold {{ $status === 'over_budget' ? 'text-danger' : 'text-success' }} mb-0 fs-16">
                    {{ is_numeric($remaining) ? $currency . number_format($remaining, 2) : $remaining }}
                </h5>
            </div>
        </div>

        <!-- Progress Bar (Supplied Burn Percentage) -->
        <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1 fs-12 text-muted fw-semibold">
                <span>{{ __('Burn Rate') }}</span>
                <span class="fw-bold {{ $config['text'] }}">{{ $burnPercentage }}%</span>
            </div>
            <x-ui.progress-bar :value="min(100, $burnPercentage)" :color="$config['bar_color']" height="8px" />
        </div>

        @if (isset($footer))
            <div class="pt-3 mt-3 border-top fs-12 text-muted">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
