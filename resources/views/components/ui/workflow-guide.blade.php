@props([
    'title'       => "What's Next?",
    'icon'        => 'feather-info',
    'dismissible' => true,
])

<div x-data="{ open: true }" x-show="open" x-transition.opacity.duration.200ms {{ $attributes->merge(['class' => 'erp-workflow-guide-box mb-2']) }}>
    <div class="p-1 rounded-1 shadow-xs d-flex align-items-start gap-3"
         style="background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent); border: 1px solid color-mix(in srgb, var(--bs-primary) 22%, transparent); border-radius: 10px;">
        <div class="avatar-text avatar-sm bg-white rounded-circle shadow-xs flex-shrink-0 mt-0.5"
             style="color: var(--bs-primary) !important; border: 1px solid color-mix(in srgb, var(--bs-primary) 30%, transparent);">
            <i class="{{ $icon }} fs-14"></i>
        </div>
        <div class="flex-grow-1 fs-13 text-dark">
            <div class="fw-bold mb-1" style="color: var(--bs-primary) !important; font-size: 13.5px;">{{ $title }}</div>
            <div class="lh-base fs-13" style="color: #4b5563;">
                {{ $slot }}
            </div>
        </div>
        @if($dismissible)
            <button type="button" @click="open = false" class="btn btn-link p-0 ms-3 text-secondary text-decoration-none fs-12 fw-medium flex-shrink-0 d-inline-flex align-items-center gap-1 mt-0.5" style="color: #6b7280;">
                <span>Dismiss</span> <i class="feather-x fs-14"></i>
            </button>
        @endif
    </div>
</div>
