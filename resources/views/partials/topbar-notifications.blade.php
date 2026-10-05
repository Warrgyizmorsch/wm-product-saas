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
                                        <div class="d-flex align-items-center gap-1 ms-auto">
                                            ${!n.is_read ? `
                                                <a href="javascript:void(0);" onclick="markNotificationRead(${n.id}, event)" class="text-success px-1 py-0.5 rounded" title="Mark as Read">
                                                    <i class="feather-check fs-13 fw-bold"></i>
                                                </a>
                                            ` : ''}
                                            <a href="javascript:void(0);" onclick="deleteNotificationItem(${n.id}, event)" class="text-danger px-1 py-0.5 rounded" title="Dismiss">
                                                <i class="feather-x fs-13"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    container.html(html);
                }
            });
        }

        window.markNotificationRead = function(id, event) {
            if (event) event.stopPropagation();
            $.ajax({
                url: "{{ url('notifications') }}/" + id + "/read",
                type: "POST",
                data: { _token: "{{ csrf_token() }}" },
                success: function() {
                    fetchSystemNotifications();
                    if (typeof markSingleReadPage === 'function') {
                        let row = document.getElementById('notification-row-' + id);
                        if (row) row.classList.remove('table-primary-subtle');
                    }
                }
            });
        };

        window.markHrmsNotificationRead = window.markNotificationRead;

        window.deleteNotificationItem = function(id, event) {
            if (event) event.stopPropagation();
            $.ajax({
                url: "{{ url('notifications') }}/" + id,
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

<!-- Real-time Pusher & Push Notification System -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
(function() {
    @php
        $pusherConfig = \App\Services\Pusher\PusherBroadcastService::getClientConfig();
        $currentUserId = auth()->id() ?? 0;
        $currentTenantId = function_exists('tenant_id') ? tenant_id() : (auth()->user()->tenant_id ?? 0);
    @endphp

    const pusherKey = "{{ $pusherConfig['key'] }}";
    const pusherCluster = "{{ $pusherConfig['cluster'] }}";
    const currentUserId = {{ $currentUserId }};
    const currentTenantId = {{ $currentTenantId }};

    // Synthesized Audio Chime using Web Audio API (Zero external mp3 files needed)
    function playNotificationSound(type) {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();

            const playTone = (freq, start, duration, gainVal) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, ctx.currentTime + start);
                gain.gain.setValueAtTime(gainVal, ctx.currentTime + start);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + start + duration);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + start);
                osc.stop(ctx.currentTime + start + duration);
            };

            if (type === 'approval_request') {
                playTone(587.33, 0.0, 0.15, 0.18); // D5
                playTone(880.00, 0.12, 0.25, 0.22); // A5
                playTone(1174.66, 0.24, 0.40, 0.25); // D6
            } else if (type === 'approved') {
                playTone(523.25, 0.0, 0.15, 0.18); // C5
                playTone(659.25, 0.12, 0.18, 0.20); // E5
                playTone(1046.50, 0.24, 0.45, 0.25); // C6
            } else if (type === 'rejected') {
                playTone(659.25, 0.0, 0.18, 0.20); // E5
                playTone(493.88, 0.14, 0.35, 0.20); // B4
            } else {
                playTone(880.00, 0.0, 0.18, 0.18);
                playTone(1318.51, 0.12, 0.35, 0.20);
            }
        } catch (e) {
            console.warn('AudioContext chime error:', e);
        }
    }

    let originalTitle = document.title;
    let titleInterval = null;

    // Flash tab title when user is on another tab/app
    function flashTabTitle(alertText) {
        if (titleInterval) clearInterval(titleInterval);
        let isOriginal = false;
        titleInterval = setInterval(() => {
            document.title = isOriginal ? originalTitle : '🔔 ' + alertText;
            isOriginal = !isOriginal;
        }, 1000);

        const stopFlashing = () => {
            if (titleInterval) {
                clearInterval(titleInterval);
                titleInterval = null;
                document.title = originalTitle;
            }
            window.removeEventListener('focus', stopFlashing);
            document.removeEventListener('mousemove', stopFlashing);
        };

        window.addEventListener('focus', stopFlashing);
        document.addEventListener('mousemove', stopFlashing);
    }

    function requestDesktopPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    // Show Native Desktop / Browser Push Notification
    function showBrowserDesktopNotification(title, body, url, icon, tag) {
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const notifTag = tag || ('visitor-alert-' + (title || '').toLowerCase().replace(/[^a-z0-9]/g, ''));
                const n = new Notification(title, {
                    body: body,
                    icon: icon || '/assets/images/favicon.ico',
                    tag: notifTag,
                    requireInteraction: true
                });
                n.onclick = function() {
                    window.focus();
                    if (url) window.location.href = url;
                    n.close();
                };
            } catch (err) {
                console.warn('Desktop Notification error:', err);
            }
        }
    }

    // Floating Push Toast on UI
    function showFloatingPushToast(data) {
        let toastContainer = document.getElementById('floatingPushToastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'floatingPushToastContainer';
            toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; max-width: 380px; width: calc(100% - 40px); pointer-events: none;';
            document.body.appendChild(toastContainer);
        }

        const isSuccess = data.type === 'approved';
        const isDanger = data.type === 'rejected';
        const isRequest = data.type === 'approval_request';

        const borderColor = isSuccess ? '#10b981' : (isDanger ? '#ef4444' : '#3b82f6');
        const badgeBg = isSuccess ? '#d1fae5' : (isDanger ? '#fee2e2' : '#dbeafe');
        const badgeColor = isSuccess ? '#065f46' : (isDanger ? '#991b1b' : '#1e40af');
        const iconClass = isSuccess ? 'feather-check-circle' : (isDanger ? 'feather-x-circle' : 'feather-user-check');

        const toast = document.createElement('div');
        toast.style.cssText = `
            background: #ffffff;
            border-left: 4px solid ${borderColor};
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15), 0 2px 6px rgba(0,0,0,0.08);
            padding: 14px 16px;
            pointer-events: auto;
            transform: translateX(120%);
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
            font-family: inherit;
        `;

        toast.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: ${badgeBg}; color: ${badgeColor}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                    <i class="${iconClass}"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px;">
                        <span style="font-size: 13px; font-weight: 700; color: #1e293b;">${data.title || 'Visitor Update'}</span>
                        <span style="font-size: 10px; font-weight: 600; text-transform: uppercase; background: ${badgeBg}; color: ${badgeColor}; padding: 2px 6px; border-radius: 4px;">Real-Time</span>
                    </div>
                    <div style="font-size: 12px; color: #475569; line-height: 1.4; margin-bottom: 8px;">${data.message || ''}</div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        ${data.action_url ? `<a href="${data.action_url}" style="font-size: 11px; font-weight: 600; color: ${borderColor}; text-decoration: underline;">View Pass Details &rarr;</a>` : '<span></span>'}
                        <button type="button" class="btn-close-toast" style="background: none; border: none; font-size: 11px; color: #94a3b8; cursor: pointer; padding: 2px 6px;">Dismiss</button>
                    </div>
                </div>
            </div>
        `;

        toastContainer.appendChild(toast);

        // Slide in animation
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
        });

        const closeBtn = toast.querySelector('.btn-close-toast');
        const dismiss = () => {
            toast.style.transform = 'translateX(120%)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 350);
        };

        if (closeBtn) closeBtn.addEventListener('click', dismiss);
        setTimeout(dismiss, 9000);
    }

    // Handle Incoming Live Event
    function handleLiveNotificationEvent(eventName, payload) {
        console.log('[Pusher Live Event]', eventName, payload);

        const notifUniqueId = payload.pass_id || payload.pass_number || 'default';
        const notifTag = 'visitor_pass_' + notifUniqueId;
        const dedupKey = 'notif_dedup_' + eventName + '_' + notifUniqueId;
        
        // Multi-Tab Shared Deduplication using localStorage
        const lastHandled = window.localStorage.getItem(dedupKey);
        const now = Date.now();
        const isDuplicateAcrossTabs = lastHandled && (now - parseInt(lastHandled)) < 4000;

        // Update header bell counter on all tabs
        if (typeof fetchSystemNotifications === 'function') {
            fetchSystemNotifications();
        }

        // If another tab already showed the desktop alert and audio chime within 4s, stop here
        if (isDuplicateAcrossTabs) {
            console.log('[Pusher] Cross-tab duplicate debounced:', eventName);
            return;
        }
        try { window.localStorage.setItem(dedupKey, now.toString()); } catch (e) {}

        let type = 'general';
        if (eventName === 'visitor.approval_request') type = 'approval_request';
        if (eventName === 'visitor.approved') type = 'approved';
        if (eventName === 'visitor.rejected') type = 'rejected';

        // 1. Play Audio Chime (Only once across all tabs)
        playNotificationSound(type);

        // 2. Flash Tab Title when outside the tab
        flashTabTitle(payload.title || 'Visitor Alert');

        // 3. Show Floating On-Screen Toast
        showFloatingPushToast({
            type: type,
            title: payload.title || 'Visitor Management Alert',
            message: payload.message || 'You have a new visitor update.',
            action_url: payload.action_url || payload.url || null
        });

        // 4. Trigger Native OS / Desktop Notification (Coalesced via unique tag)
        showBrowserDesktopNotification(
            payload.title || 'Visitor Management',
            payload.message || 'You have a new visitor notification.',
            payload.action_url || payload.url || null,
            null,
            notifTag
        );

        // 5. If user is currently on approvals or gate desk page, trigger auto-refresh if function exists
        if (window.location.pathname.includes('/visitor/approvals') || window.location.pathname.includes('/visitor')) {
            if (typeof refreshVisitorTable === 'function') {
                refreshVisitorTable();
            }
        }
    }

    // Initialize Pusher if key is available
    if (pusherKey && typeof Pusher !== 'undefined') {
        try {
            const pusher = new Pusher(pusherKey, {
                cluster: pusherCluster || 'ap2',
                forceTLS: true
            });

            const visitorEvents = [
                'visitor.approval_request',
                'visitor.approved',
                'visitor.rejected',
                'visitor.checked_in',
                'visitor.arrived',
                'visitor.pre_registered',
                'visitor.meeting_started',
                'visitor.checked_out',
                'visitor.visit_extended',
                'visitor.incident_reported',
                'visitor.host_ping'
            ];

            // 1. User Channel (Host specific alerts)
            if (currentUserId > 0) {
                const userChannelName = 'user-' + currentUserId;
                const userChannel = pusher.subscribe(userChannelName);

                visitorEvents.forEach(ev => {
                    userChannel.bind(ev, function(data) {
                        handleLiveNotificationEvent(ev, data);
                    });
                });
            }

            // 2. Tenant Visitor Channel (Gate desk & general security alerts)
            if (currentTenantId > 0) {
                const tenantChannelName = 'visitor-tenant-' + currentTenantId;
                const tenantChannel = pusher.subscribe(tenantChannelName);

                visitorEvents.forEach(ev => {
                    tenantChannel.bind(ev, function(data) {
                        handleLiveNotificationEvent(ev, data);
                    });
                });
            }

            console.log('[Pusher] Initialized successfully on cluster:', pusherCluster);
        } catch (e) {
            console.error('[Pusher] Initialization error:', e);
        }
    }

    // Request desktop permission on first interaction
    document.addEventListener('click', requestDesktopPermission, { once: true });
})();
</script>

