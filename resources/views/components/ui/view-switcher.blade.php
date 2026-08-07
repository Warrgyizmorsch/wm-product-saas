@props([
    'listRoute' => null,
    'kanbanRoute' => null,
    'calendarRoute' => null,
    'showCalendar' => null,
])

@php
    $isDealContext = request()->routeIs('crm.deals.*') || request()->is('crm/deals*');
    $isLeadContext = request()->routeIs('crm.leads.*') || request()->is('crm/leads*');
    $isAccountContext = request()->routeIs('crm.accounts.*') || request()->is('crm/accounts*');

    if ($listRoute) {
        $finalListRoute = $listRoute;
    } elseif ($isDealContext) {
        $finalListRoute = route('crm.deals.index');
    } elseif ($isAccountContext) {
        $finalListRoute = route('crm.accounts.index');
    } else {
        $finalListRoute = route('crm.leads.index');
    }

    if ($kanbanRoute) {
        $finalKanbanRoute = $kanbanRoute;
    } elseif ($isDealContext) {
        $finalKanbanRoute = route('crm.deals.kanban');
    } else {
        $finalKanbanRoute = route('crm.leads.kanban');
    }

    $finalCalRoute = $calendarRoute ?? route('crm.activities.index');
    $shouldShowCalendar = $showCalendar ?? (!$isDealContext);

    $isListActive = request()->routeIs('crm.leads.index') || request()->routeIs('crm.deals.index') || request()->routeIs('crm.accounts.index');
    $isKanbanActive = request()->routeIs('crm.leads.kanban') || request()->routeIs('crm.deals.kanban');
    $isCalActive = request()->routeIs('crm.activities.index');
@endphp

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
    <a href="{{ $finalListRoute }}" class="action-dropdown-btn {{ $isListActive ? 'active' : '' }}" title="List View" data-bs-toggle="tooltip">
        <i class="feather-list"></i>
    </a>
    <a href="{{ $finalKanbanRoute }}" class="action-dropdown-btn {{ $isKanbanActive ? 'active' : '' }}" title="Pipeline Kanban" data-bs-toggle="tooltip">
        <i class="feather-grid"></i>
    </a>
    @if($shouldShowCalendar)
        <a href="{{ $finalCalRoute }}" class="action-dropdown-btn {{ $isCalActive ? 'active' : '' }}" title="Activity Calendar" data-bs-toggle="tooltip">
            <i class="feather-calendar"></i>
        </a>
    @endif
</div>
