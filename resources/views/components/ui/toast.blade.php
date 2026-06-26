@props([
    'id',
    'title' => 'Notification',
    'subtitle' => 'just now',
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
    
    $borderColors = [
        'primary' => '#e07a5f',
        'success' => '#81b29a',
        'danger' => '#e76f51',
        'warning' => '#f2cc8f',
        'info' => '#5f9ea0'
    ];
    $borderColor = $borderColors[$type] ?? '#e07a5f';
@endphp

<div class="toast fade border-0 shadow-lg erp-premium-toast" id="{{ $id }}" role="alert" aria-live="assertive" aria-atomic="true" 
     data-bs-autohide="{{ $autohide ? 'true' : 'false' }}" data-bs-delay="{{ $delay }}"
     style="border-left: 4px solid {{ $borderColor }} !important;" {{ $attributes }}>
    <div class="d-flex p-3 bg-white rounded-end align-items-start">
        <div class="fs-20 me-3 mt-1">
            <i class="{{ $iconClass }}"></i>
        </div>
        <div class="flex-grow-1 me-2">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="fw-bold text-dark fs-13">{{ $title }}</span>
                <small class="text-muted fs-11 ms-2">{{ $subtitle }}</small>
            </div>
            <div class="fs-12 text-secondary" style="line-height: 1.4;">
                {{ $slot }}
            </div>
        </div>
        <button type="button" class="btn-close ms-auto mt-1" data-bs-dismiss="toast" aria-label="Close" style="font-size: 10px;"></button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var toastEl = document.getElementById('{{ $id }}');
        if (toastEl && typeof bootstrap !== 'undefined') {
            var toastInstance = new bootstrap.Toast(toastEl);
            if (!window.erpToasts) window.erpToasts = {};
            window.erpToasts['{{ $id }}'] = toastInstance;
        }
    });
</script>
