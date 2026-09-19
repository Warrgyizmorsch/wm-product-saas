<!-- Dynamic System-Wide Header Notifications Dropdown -->
<div class="dropdown nxl-h-item">
    <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside" id="notificationBellDropdown" aria-expanded="false" title="Notifications">
        <i class="feather-bell"></i>
        <span class="badge bg-danger nxl-h-badge" id="notificationBadgeCount" style="display: none;">
            0
        </span>
    </a>
    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
        <div class="d-flex justify-content-between align-items-center notifications-head">
            <h6 class="fw-bold text-dark mb-0">{{ __('ui.notifications') }}</h6>
            <a href="javascript:void(0);" id="markAllNotificationsReadBtn" class="fs-11 text-success text-end ms-auto">
                <i class="feather-check"></i>
                <span>{{ __('ui.mark_as_read') }}</span>
            </a>
        </div>

        <div id="notificationListContainer" style="max-height: 360px; overflow-y: auto;">
            <div class="text-center py-4 text-muted" id="notificationLoadingSpinner">
                <i class="feather-loader spin me-1"></i> Loading...
            </div>
        </div>

        <div class="text-center notifications-footer">
            <a href="{{ route('notifications.index') }}" class="fs-13 fw-semibold text-dark">{{ __('ui.all_notifications') }}</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        function fetchSystemNotifications() {
            $.ajax({
                url: "{{ route('notifications.unread') }}",
                type: "GET",
                dataType: "json",
                success: function (res) {
                    let count = res.unread_count || 0;
                    let badge = $('#notificationBadgeCount');
                    
                    if (count > 0) {
                        badge.text(count > 99 ? '99+' : count).show();
                    } else {
                        badge.hide();
                    }

                    let container = $('#notificationListContainer');
                    container.empty();

                    if (!res.notifications || res.notifications.length === 0) {
                        container.html(`
                            <div class="text-center py-4 text-muted">
                                <i class="feather-bell-off fs-20 d-block mb-1 text-secondary"></i>
                                <span class="fs-12">No notifications found</span>
                            </div>
                        `);
                        return;
                    }

                    let html = '';
                    res.notifications.forEach(function (n) {
                        let isUnreadBg = !n.is_read ? 'bg-soft-primary' : '';
                        let typeIcon = n.icon_class || 'feather-bell';
                        let moduleBadgeClass = n.module_badge_class || 'bg-soft-primary text-primary';
                        let moduleLabel = n.module_label || 'SYSTEM';

                        html += `
                            <div class="notifications-item ${isUnreadBg}" data-id="${n.id}">
                                <div class="avatar-text rounded me-3 bg-soft-primary text-primary flex-shrink-0" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                    <i class="${typeIcon}"></i>
                                </div>
                                <div class="notifications-desc flex-grow-1" style="min-width: 0;">
                                    <a href="${n.action_url}" onclick="markNotificationRead(${n.id})" class="font-body text-truncate-2-line text-decoration-none" title="${n.title}">
                                        <div class="d-flex align-items-center gap-1.5 mb-0.5">
                                            <span class="badge ${moduleBadgeClass} fs-10 px-1.5 py-0.5 rounded fw-semibold">${moduleLabel}</span>
                                            <span class="fw-semibold text-dark d-inline-block text-truncate" style="max-width: 180px;">${n.title}</span>
                                        </div>
                                        <span class="text-muted fs-12">${n.message}</span>
                                    </a>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <div class="notifications-date text-muted border-bottom border-bottom-dashed fs-11">${n.time_ago}</div>
                                        <a href="javascript:void(0);" onclick="deleteNotificationItem(${n.id}, event)" class="text-danger ms-2" title="Dismiss">
                                            <i class="feather-x fs-12"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    container.html(html);
                }
            });
        }

        window.markNotificationRead = function(id) {
            $.ajax({
                url: "/notifications/" + id + "/read",
                type: "POST",
                data: { _token: "{{ csrf_token() }}" },
                success: function() {
                    fetchSystemNotifications();
                }
            });
        };

        window.markHrmsNotificationRead = window.markNotificationRead;

        window.deleteNotificationItem = function(id, event) {
            if (event) event.stopPropagation();
            $.ajax({
                url: "/notifications/" + id,
                type: "DELETE",
                data: { _token: "{{ csrf_token() }}" },
                success: function() {
                    fetchSystemNotifications();
                }
            });
        };

        window.deleteHrmsNotification = window.deleteNotificationItem;

        $('#markAllNotificationsReadBtn').on('click', function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('notifications.read-all') }}",
                type: "POST",
                data: { _token: "{{ csrf_token() }}" },
                success: function() {
                    fetchSystemNotifications();
                }
            });
        });

        fetchSystemNotifications();
        setInterval(fetchSystemNotifications, 45000);
    });
</script>
