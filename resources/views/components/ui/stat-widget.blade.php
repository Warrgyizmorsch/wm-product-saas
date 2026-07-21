@props([
    'title' => '',
    'value' => '0',
    'subtitle' => null,
    'trend' => null,
    'trendDirection' => 'up', // up, down, neutral
    'icon' => 'feather-activity',
    'color' => 'primary', // primary, success, warning, danger, info, teal
    'variant' => 'standard', // standard, compact
])

@php
    $trendClass = match($trendDirection) {
        'up' => 'text-success',
        'down' => 'text-danger',
        default => 'text-muted',
    };
    $trendIcon = match($trendDirection) {
        'up' => 'feather-arrow-up-right',
        'down' => 'feather-arrow-down-right',
        default => 'feather-minus',
    };
@endphp

<div {{ $attributes->merge(['class' => 'card stretch stretch-full border-0 shadow-sm mb-3']) }}>
    <div class="card-body {{ $variant === 'compact' ? 'p-3' : 'p-4' }}">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-text avatar-lg bg-soft-{{ $color }} text-{{ $color }} rounded-3 d-flex align-items-center justify-content-center">
                    <i class="{{ $icon }} fs-4"></i>
                </div>
                <div>
                    <span class="fs-12 text-uppercase text-muted fw-semibold d-block mb-1">{{ $title }}</span>
                    <h3 class="fw-bold mb-0 text-dark">{{ $value }}</h3>
                    @if ($subtitle)
                        <span class="fs-12 text-muted d-block mt-1">{{ $subtitle }}</span>
                    @endif
                </div>
            </div>

            @if ($trend)
                <div class="text-end">
                    <span class="badge bg-soft-{{ $trendDirection === 'up' ? 'success' : ($trendDirection === 'down' ? 'danger' : 'secondary') }} {{ $trendClass }} fw-bold px-2 py-1 fs-12">
                        <i class="{{ $trendIcon }} me-1"></i>{{ $trend }}
                    </span>
                </div>
            @endif
        </div>

        @if (isset($chart))
            <div class="mt-3">
                {{ $chart }}
            </div>
        @endif

        @if (isset($footer))
            <div class="pt-3 mt-3 border-top fs-12 text-muted">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
