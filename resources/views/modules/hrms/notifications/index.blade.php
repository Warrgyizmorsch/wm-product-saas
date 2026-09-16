@extends('layouts.duralux')

@section('title', 'Notifications Center | HRMS')
@section('page-title', 'Notifications Center')
@section('breadcrumb', 'HRMS / Notifications')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-primary" size="sm" icon="feather-check-circle" onclick="markAllNotificationsReadPage()">
            Mark All as Read
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    #notificationSubTabs .nav-link {
        border: none !important;
        background-color: transparent !important;
        color: #64748b;
        font-weight: 500;
        padding: 10px 16px;
        border-bottom: 2px solid transparent !important;
        transition: all 0.2s ease-in-out;
    }
    #notificationSubTabs .nav-link:hover {
        color: var(--bs-primary);
    }
    #notificationSubTabs .nav-link.active {
        color: var(--bs-primary) !important;
        border-bottom: 2px solid var(--bs-primary) !important;
        font-weight: 600;
    }
    .notification-panel-item {
        transition: background 0.15s ease-in-out;
        border-bottom: 1px solid #f1f5f9;
    }
    .notification-panel-item:last-child {
        border-bottom: none;
    }
    .notification-panel-item:hover {
        background-color: #f8fafc;
    }
    .notification-unread-bg {
        background-color: rgba(var(--bs-primary-rgb), 0.04);
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <!-- ERP Single Panel Container -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Panel Header: Navigation Sub-Tabs & Actions -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-2 mb-3">
            <ul class="nav nav-tabs border-0" id="notificationSubTabs">
                <li class="nav-item">
                    <a class="nav-link {{ !request('status') ? 'active' : '' }}" href="{{ route('hrms.notifications.index') }}">
                        <i class="feather-bell me-1"></i> All Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request('status') === 'unread' ? 'active' : '' }}" href="{{ route('hrms.notifications.index', ['status' => 'unread']) }}">
                        <i class="feather-mail me-1"></i> Unread Only
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request('status') === 'read' ? 'active' : '' }}" href="{{ route('hrms.notifications.index', ['status' => 'read']) }}">
                        <i class="feather-check-circle me-1"></i> Read Only
                    </a>
                </li>
            </ul>

            <div class="text-muted fs-12">
                Showing {{ $notifications->firstItem() ?? 0 }} - {{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} notifications
            </div>
        </div>

        <!-- Notifications Items Table List -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="table-layout: fixed; width: 100%;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;" class="text-center">Type</th>
                        <th style="width: 55%;">Notification Details</th>
                        <th style="width: 150px;">Time</th>
                        <th style="width: 120px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $n)
                        <tr id="notification-row-{{ $n->id }}" class="{{ !$n->read_at ? 'table-primary-subtle' : '' }}">
                            <td class="text-center align-middle">
                                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                    <i class="{{ $n->icon_class ?? 'feather-bell' }} fs-15"></i>
                                </div>
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
                                <p class="text-muted fs-12 mb-0">You're all caught up! There are no notifications to display.</p>
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
            url: "/hrms/notifications/" + id + "/read",
            type: "POST",
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                location.reload();
            }
        });
    }

    function markAllNotificationsReadPage() {
        $.ajax({
            url: "{{ route('hrms.notifications.read-all') }}",
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
            url: "/hrms/notifications/" + id,
            type: "DELETE",
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                $('#notification-row-' + id).fadeOut(300, function() { $(this).remove(); });
            }
        });
    }
</script>
@endsection
