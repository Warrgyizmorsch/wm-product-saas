@props([
    'id',
    'title' => 'Notification',
    'subtitle' => null,
    'type' => 'primary', // primary, success, danger, warning, info
    'autohide' => true,
    'delay' => 5000
])

@php
    $icons = [
        'primary' => 'feather-bell text-primary',
        'success' => 'feather-check-circle text-success',
        'danger' => 'feather-alert-circle text-danger',
        'warning' => 'feather-alert-triangle text-warning',
        'info' => 'feather-info text-info'
    ];
    $iconClass = $icons[$type] ?? 'feather-bell text-primary';
    
    // Border dynamic colors matching the theme status
    $borderColors = [
        'primary' => 'var(--bs-primary)',
        'success' => '#10b981',
        'danger' => '#ef4444',
        'warning' => '#f59e0b',
        'info' => '#3b82f6'
    ];
    $borderColor = $borderColors[$type] ?? 'var(--bs-primary)';
@endphp

<div class="toast fade" id="{{ $id }}" role="alert" aria-live="assertive" aria-atomic="true" 
     data-bs-autohide="{{ $autohide ? 'true' : 'false' }}" data-bs-delay="{{ $delay }}"
     style="border-left: 4px solid {{ $borderColor }} !important;"
     {{ $attributes }}>
    <div class="toast-header">
        <i class="{{ $iconClass }} me-2 fs-15"></i>
        <strong class="me-auto text-dark">{{ $title }}</strong>
        @if($subtitle)
            <small class="text-muted">{{ $subtitle }}</small>
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body fs-12 text-secondary">
        {{ $slot }}
    </div>
</div>

@once
    @push('scripts')
        <script>
            $(function () {
                var toastEl = document.getElementById('{{ $id }}');
                if (toastEl && typeof bootstrap !== 'undefined') {
                    var toastInstance = new bootstrap.Toast(toastEl);
                    if (!window.erpToasts) window.erpToasts = {};
                    window.erpToasts['{{ $id }}'] = toastInstance;
                }
            });
        </script>
    @endpush
@endonce
