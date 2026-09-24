@extends('layouts.duralux')

@section('title', 'Notifications Center | Enterprise ERP')
@section('page-title', 'Notifications Center')
@section('breadcrumb', 'System / Notifications')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-primary" size="sm" icon="feather-check-circle" onclick="markAllNotificationsReadPage()">
            Mark All as Read
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    #notificationSubTabs .nav-link, #notificationModuleTabs .nav-link {
        border: none !important;
        background-color: transparent !important;
        color: #64748b;
        font-weight: 500;
        padding: 8px 14px;
        border-bottom: 2px solid transparent !important;
        transition: all 0.2s ease-in-out;
    }
    #notificationSubTabs .nav-link:hover, #notificationModuleTabs .nav-link:hover {
        color: var(--bs-primary);
    }
    #notificationSubTabs .nav-link.active, #notificationModuleTabs .nav-link.active {
        color: var(--bs-primary) !important;
        border-bottom: 2px solid var(--bs-primary) !important;
        font-weight: 600;
    }
    .hover-primary:hover {
        color: var(--bs-primary) !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <!-- ERP Single Panel Container -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- Module Filter Tabs -->
        <div class="border-bottom pb-2 mb-3">
            <ul class="nav nav-tabs border-0 flex-nowrap overflow-auto" id="notificationModuleTabs">
                @php
                    $currentModule = request('module', 'all');
                    $modules = [
                        'all' => ['label' => 'All Modules', 'icon' => 'feather-grid'],
                        'hrms' => ['label' => 'HRMS', 'icon' => 'feather-users'],
                        'purchase' => ['label' => 'Purchase', 'icon' => 'feather-shopping-bag'],
                        'production' => ['label' => 'Production', 'icon' => 'feather-layers'],
                        'sales' => ['label' => 'Sales', 'icon' => 'feather-trending-up'],
                        'crm' => ['label' => 'CRM', 'icon' => 'feather-target'],
                        'inventory' => ['label' => 'Inventory', 'icon' => 'feather-package'],
                        'accounting' => ['label' => 'Accounting', 'icon' => 'feather-dollar-sign'],
                        'system' => ['label' => 'System', 'icon' => 'feather-sliders'],
                    ];
                @endphp
                @foreach($modules as $modKey => $modInfo)
                    <li class="nav-item">
                        <a class="nav-link {{ $currentModule === $modKey ? 'active' : '' }}" href="{{ route('notifications.index', array_merge(request()->query(), ['module' => $modKey])) }}">
                            <i class="{{ $modInfo['icon'] }} me-1 fs-12"></i> {{ $modInfo['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- Status Filter Sub-Tabs & Info -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-2 mb-3">
            <ul class="nav nav-tabs border-0" id="notificationSubTabs">
                <li class="nav-item">
                    <a class="nav-link {{ !request('status') ? 'active' : '' }}" href="{{ route('notifications.index', array_merge(request()->query(), ['status' => null])) }}">
                        <i class="feather-bell me-1"></i> All Status
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request('status') === 'unread' ? 'active' : '' }}" href="{{ route('notifications.index', array_merge(request()->query(), ['status' => 'unread'])) }}">
                        <i class="feather-mail me-1"></i> Unread Only
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request('status') === 'read' ? 'active' : '' }}" href="{{ route('notifications.index', array_merge(request()->query(), ['status' => 'read'])) }}">
                        <i class="feather-check-circle me-1"></i> Read Only
                    </a>
                </li>
            </ul>

            <div class="text-muted fs-12">
                Showing {{ $notifications->firstItem() ?? 0 }} - {{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} notifications
            </div>
        </div>

        <!-- Notifications Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="table-layout: fixed; width: 100%;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 100px;" class="text-center">Module</th>
                        <th style="width: 55%;">Notification Details</th>
                        <th style="width: 150px;">Time</th>
                        <th style="width: 120px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $n)
                        @php
                            $mod = strtolower($n->module ?? 'system');
                            $badgeClass = match ($mod) {
                                'hrms' => 'bg-soft-primary text-primary',
                                'purchase' => 'bg-soft-info text-info',
                                'production' => 'bg-soft-warning text-warning',
                                'sales' => 'bg-soft-success text-success',
                                'crm' => 'bg-soft-danger text-danger',
                                'inventory' => 'bg-soft-purple text-purple',
                                'accounting' => 'bg-soft-dark text-dark',
                                'projects' => 'bg-soft-secondary text-secondary',
                                default => 'bg-soft-secondary text-dark',
                            };
                        @endphp
                        <tr id="notification-row-{{ $n->id }}" class="{{ !$n->read_at ? 'table-primary-subtle' : '' }}">
                            <td class="text-center align-middle">
                                <span class="badge {{ $badgeClass }} fs-11 px-2 py-1 rounded fw-bold d-inline-block text-uppercase">
                                    {{ $mod }}
                                </span>
                            </td>
                            <td>
                                <div class="py-1">
                                    <a href="{{ $n->action_url ?? '#' }}" onclick="markSingleReadPage({{ $n->id }})" class="fw-bold text-dark fs-13 text-decoration-none hover-primary">
                                        {{ $n->title }}
                                        @if($n->action_url)
                                            <i class="feather-external-link text-primary ms-1 fs-11" title="Open Details"></i>
                                        @endif
                                    </a>
                                    <p class="text-muted fs-12 mb-0 mt-0.5" style="line-height: 1.4;">{{ $n->message }}</p>
                                </div>
                            </td>
                            <td class="align-middle text-muted fs-12">
                                <span>{{ $n->created_at ? $n->created_at->diffForHumans() : '' }}</span>
                            </td>
                            <td class="align-middle text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    @if(!$n->read_at)
                                        <button type="button" onclick="markSingleReadPage({{ $n->id }})" class="btn btn-sm btn-icon btn-light border text-success" title="Mark as Read">
                                            <i class="feather-check"></i>
                                        </button>
                                    @endif
                                    <button type="button" onclick="deleteNotificationPage({{ $n->id }})" class="btn btn-sm btn-icon btn-light border text-danger" title="Delete Notification">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <i class="feather-bell-off text-muted fs-36 d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">No Notifications Found</h6>
                                <p class="text-muted fs-12 mb-0">You're all caught up! There are no notifications to display in this view.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($notifications->hasPages())
            <div class="pt-3 border-top mt-3">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>

<script>
    function markSingleReadPage(id) {
        $.ajax({
            url: "{{ url('notifications') }}/" + id + "/read",
            type: "POST",
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                location.reload();
            }
        });
    }

    function markAllNotificationsReadPage() {
        $.ajax({
            url: "{{ route('notifications.read-all') }}",
            type: "POST",
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                location.reload();
            }
        });
    }

    function deleteNotificationPage(id) {
        if (!confirm('Are you sure you want to delete this notification?')) return;
        $.ajax({
            url: "{{ url('notifications') }}/" + id,
            type: "DELETE",
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                $('#notification-row-' + id).fadeOut(200, function() { $(this).remove(); });
            }
        });
    }
</script>
@endsection
