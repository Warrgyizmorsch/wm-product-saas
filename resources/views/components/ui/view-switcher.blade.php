@once
    @push('styles')
        <style>
            .action-dropdown-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 32px !important;
                height: 32px !important;
                border-radius: 8px !important;
                border: 1.5px solid #cbd5e1 !important;
                background-color: #ffffff !important;
                color: #475569 !important;
                transition: all 0.28s ease !important;
                text-decoration: none !important;
                cursor: pointer !important;
            }
            .action-dropdown-btn:hover,
            .action-dropdown-btn.active {
                background-color: color-mix(in srgb, var(--bs-primary) 12%, transparent) !important;
                border-color: var(--bs-primary) !important;
                color: var(--bs-primary) !important;
            }
        </style>
    @endpush
@endonce

<div class="d-flex align-items-center me-2" style="gap: 6px;">
    <a href="{{ route('crm.leads.index') }}" class="action-dropdown-btn {{ request()->routeIs('crm.leads.index') ? 'active' : '' }}" title="List View" data-bs-toggle="tooltip">
        <i class="feather-list"></i>
    </a>
    <a href="{{ route('crm.leads.kanban') }}" class="action-dropdown-btn {{ request()->routeIs('crm.leads.kanban') ? 'active' : '' }}" title="Pipeline Kanban" data-bs-toggle="tooltip">
        <i class="feather-grid"></i>
    </a>
    <a href="{{ route('crm.activities.index') }}" class="action-dropdown-btn {{ request()->routeIs('crm.activities.index') ? 'active' : '' }}" title="Activity Calendar" data-bs-toggle="tooltip">
        <i class="feather-calendar"></i>
    </a>
</div>
