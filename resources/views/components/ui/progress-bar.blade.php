@props([
    'value' => 0,
    'max' => 100,
    'color' => 'auto', // auto, primary, success, warning, danger, info, teal
    'height' => '6px',
    'showLabel' => false,
    'striped' => false,
])

@php
    $percentage = $max > 0 ? min(100, max(0, round(($value / $max) * 100))) : 0;

    $colorClass = $color;
    if ($color === 'auto') {
        if ($percentage >= 80) {
            $colorClass = 'success';
        } elseif ($percentage >= 40) {
            $colorClass = 'primary';
        } elseif ($percentage >= 20) {
            $colorClass = 'warning';
        } else {
            $colorClass = 'danger';
        }
    }
@endphp

<div class="w-100">
    @if ($showLabel)
        <div class="d-flex justify-content-between align-items-center mb-1 fs-12 text-muted fw-semibold">
            <span>{{ __('Progress') }}</span>
            <span>{{ $percentage }}%</span>
        </div>
    @endif
    <div {{ $attributes->merge(['class' => 'progress rounded-pill bg-light']) }} style="height: {{ $height }};">
        <div class="progress-bar bg-{{ $colorClass }} {{ $striped ? 'progress-bar-striped progress-bar-animated' : '' }} rounded-pill"
             role="progressbar"
             style="width: {{ $percentage }}%;"
             aria-valuenow="{{ $value }}"
             aria-valuemin="0"
             aria-valuemax="{{ $max }}">
        </div>
    </div>
</div>
