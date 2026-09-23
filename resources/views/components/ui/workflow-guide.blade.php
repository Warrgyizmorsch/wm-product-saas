@props([
    'title'       => null,
    'icon'        => 'feather-info',
    'dismissible' => true,
])

@once
    @push('styles')
        <style>
            .erp-workflow-guide-box .erp-workflow-guide-inner {
                background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent);
                border: 1px solid color-mix(in srgb, var(--bs-primary) 22%, transparent);
                border-radius: 10px;
                padding: 10px 14px;
            }
            .erp-workflow-guide-box .erp-workflow-guide-avatar {
                background-color: #ffffff;
                color: var(--bs-primary) !important;
                border: 1px solid color-mix(in srgb, var(--bs-primary) 30%, transparent);
            }
            .erp-workflow-guide-box .erp-workflow-guide-title {
                color: var(--bs-primary) !important;
                font-size: 13.5px;
            }
            .erp-workflow-guide-box .erp-workflow-guide-body {
                color: #374151;
            }
            .erp-workflow-guide-box .erp-workflow-guide-body a {
                color: var(--bs-primary);
                text-decoration: underline;
                font-weight: 600;
            }
            .erp-workflow-guide-box .erp-workflow-guide-dismiss {
                color: #6b7280;
                transition: color 0.2s ease;
            }
            .erp-workflow-guide-box .erp-workflow-guide-dismiss:hover {
                color: #111827;
            }

            /* Dark Mode Support for Workflow Guide Component */
            html.app-skin-dark .erp-workflow-guide-box .erp-workflow-guide-inner {
                background-color: #131c31 !important;
                border: 1px solid color-mix(in srgb, var(--bs-primary) 35%, transparent) !important;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important;
            }
            html.app-skin-dark .erp-workflow-guide-box .erp-workflow-guide-avatar {
                background-color: color-mix(in srgb, var(--bs-primary) 25%, transparent) !important;
                color: color-mix(in srgb, var(--bs-primary) 70%, #ffffff) !important;
                border: 1px solid color-mix(in srgb, var(--bs-primary) 45%, transparent) !important;
            }
            html.app-skin-dark .erp-workflow-guide-box .erp-workflow-guide-title {
                color: color-mix(in srgb, var(--bs-primary) 70%, #ffffff) !important;
            }
            html.app-skin-dark .erp-workflow-guide-box .erp-workflow-guide-body {
                color: #cbd5e1 !important;
            }
            html.app-skin-dark .erp-workflow-guide-box .erp-workflow-guide-body a {
                color: color-mix(in srgb, var(--bs-primary) 75%, #ffffff) !important;
                text-decoration: underline !important;
            }
            html.app-skin-dark .erp-workflow-guide-box .erp-workflow-guide-dismiss {
                color: #94a3b8 !important;
            }
            html.app-skin-dark .erp-workflow-guide-box .erp-workflow-guide-dismiss:hover {
                color: #ffffff !important;
            }
        </style>
    @endpush
@endonce

<div x-data="{ open: true }" x-show="open" x-transition.opacity.duration.200ms {{ $attributes->merge(['class' => 'erp-workflow-guide-box mb-2']) }}>
    <div class="erp-workflow-guide-inner shadow-xs d-flex align-items-start gap-3">
        <div class="erp-workflow-guide-avatar avatar-text avatar-sm rounded-circle shadow-xs flex-shrink-0 mt-0.5">
            <i class="{{ $icon }} fs-14"></i>
        </div>
        <div class="flex-grow-1 fs-13">
            <div class="erp-workflow-guide-title fw-bold mb-1">{{ $title ?? __('production.whats_next') }}</div>
            <div class="erp-workflow-guide-body lh-base fs-13">
                {{ $slot }}
            </div>
        </div>
        @if($dismissible)
            <button type="button" @click="open = false" class="erp-workflow-guide-dismiss btn btn-link p-0 ms-3 text-decoration-none fs-12 fw-medium flex-shrink-0 d-inline-flex align-items-center gap-1 mt-0.5">
                <span>{{ __('production.dismiss') }}</span> <i class="feather-x fs-14"></i>
            </button>
        @endif
    </div>
</div>
