@php
    $eventType = $item->event_type;
    $rawNote = (string)$item->notes;
    $user = $item->user;
    $userName = $user?->name ?: 'System';
    $nameParts = preg_split('/\s+/', trim($userName));
    $userInitials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
    if (empty($userInitials)) $userInitials = 'SY';
    $timeStr = $item->created_at ? $item->created_at->format('h:i A') : '';

    // Defaults
    $themeColor = 'primary';
    $nodeClass = 'node-primary';
    $eventTitle = 'Activity Logged';
    $eventIcon = 'feather-clock';
    $activityTypeBadge = null;
    $scheduledDateTime = null;
    $interactionNote = null;
    $isInteraction = false;
    $statusOld = null;
    $statusNew = null;
    $isStatusChange = false;
    $assigneeName = null;

    if ($eventType === 'created') {
        $themeColor = 'success';
        $nodeClass = 'node-success';
        $eventTitle = 'Lead Created';
        $eventIcon = 'feather-plus-circle';
    } elseif ($eventType === 'assigned') {
        $themeColor = 'info';
        $nodeClass = 'node-info';
        $eventTitle = 'Lead Assigned';
        $eventIcon = 'feather-user-check';
        $assigneeName = $item->new_value ?: ($item->notes ? preg_replace('/^Assigned to\s*/i', '', $item->notes) : null);
    } elseif (in_array($eventType, ['status_updated', 'status_changed'])) {
        $themeColor = 'indigo';
        $nodeClass = 'node-indigo';
        $eventTitle = 'Status Updated';
        $eventIcon = 'feather-git-commit';
        $isStatusChange = true;
        $statusOld = $item->old_value;
        $statusNew = $item->new_value;
        if (!$statusOld && !$statusNew && preg_match('/from\s+(.+?)\s+to\s+(.+)$/i', $rawNote, $sm)) {
            $statusOld = trim($sm[1]);
            $statusNew = trim($sm[2]);
        }
    } elseif ($eventType === 'activity_scheduled') {
        $themeColor = 'purple';
        $nodeClass = 'node-purple';
        $activityType = $item->new_value ?: 'Activity';
        $activityTypeBadge = $activityType;
        $eventTitle = $activityType . ' Scheduled';

        if (strcasecmp($activityType, 'Call') === 0) {
            $eventIcon = 'feather-phone-call';
        } elseif (strcasecmp($activityType, 'Email') === 0) {
            $eventIcon = 'feather-mail';
        } elseif (strcasecmp($activityType, 'Meeting') === 0) {
            $eventIcon = 'feather-video';
        } else {
            $eventIcon = 'feather-calendar';
        }

        if (preg_match('/on\s+([0-9\/\-:\sAPMapm]+)$/i', $rawNote, $m)) {
            $scheduledDateTime = trim($m[1]);
        }
    } elseif ($eventType === 'activity_completed') {
        $activityType = $item->new_value ?: 'Activity';
        $activityTypeBadge = $activityType;

        if (stripos($rawNote, 'Logged a') === 0 && preg_match('/Logged a\s+(\w+)\s+interaction:\s*(.*)/is', $rawNote, $m)) {
            $isInteraction = true;
            $themeColor = 'teal';
            $nodeClass = 'node-teal';
            $activityTypeBadge = $m[1];
            $eventTitle = $m[1] . ' Interaction Logged';
            $interactionNote = trim($m[2]);

            if (strcasecmp($m[1], 'Call') === 0) $eventIcon = 'feather-phone';
            elseif (strcasecmp($m[1], 'Email') === 0) $eventIcon = 'feather-mail';
            elseif (strcasecmp($m[1], 'Meeting') === 0) $eventIcon = 'feather-users';
            else $eventIcon = 'feather-message-circle';
        } else {
            $themeColor = 'success';
            $nodeClass = 'node-success';
            $eventTitle = $activityType . ' Completed';
            $eventIcon = 'feather-check-circle';

            if (stripos($rawNote, 'Marked scheduled') === 0 && preg_match('/scheduled for\s+([^)]+)/i', $rawNote, $m)) {
                $scheduledDateTime = trim($m[1]);
            }
        }
    } elseif ($eventType === 'activity_rescheduled') {
        $themeColor = 'warning';
        $nodeClass = 'node-warning';
        $eventTitle = 'Activity Rescheduled';
        $eventIcon = 'feather-clock';
    } elseif ($eventType === 'activity_deleted') {
        $themeColor = 'danger';
        $nodeClass = 'node-danger';
        $eventTitle = 'Activity Deleted';
        $eventIcon = 'feather-trash-2';
    } elseif (str_contains($eventType, 'call_completed_ai')) {
        $themeColor = 'cyan';
        $nodeClass = 'node-cyan';
        $eventTitle = 'AI Voice Call Completed';
        $eventIcon = 'feather-mic';
    }
@endphp

<div class="history-timeline-item position-relative mb-2.5" data-event-type="{{ $eventType }}" data-search-term="{{ strtolower($eventTitle . ' ' . $rawNote . ' ' . $userName . ' ' . ($activityTypeBadge ?? '')) }}">
    
    <!-- Vertical Track Line (connecting nodes) -->
    <div class="history-timeline-track position-absolute"></div>

    <!-- Node Icon on the track -->
    <div class="history-timeline-node {{ $nodeClass }} shadow-2xs position-absolute d-flex align-items-center justify-content-center" title="{{ $eventTitle }}">
        <i class="{{ $eventIcon }}"></i>
    </div>

    <!-- Main History Card -->
    <div class="history-card rounded-3 border bg-white shadow-2xs p-2.5">
        
        <!-- Header: Title + Type Badge + Time -->
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-1">
            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                <span class="fs-12 fw-bold text-dark">{{ $eventTitle }}</span>
                @if($activityTypeBadge)
                    @php
                        $badgeBg = match(strtolower($activityTypeBadge)) {
                            'call' => 'bg-soft-teal text-teal border-teal-subtle',
                            'email' => 'bg-soft-purple text-purple border-purple-subtle',
                            'meeting' => 'bg-soft-warning text-warning border-warning-subtle',
                            default => 'bg-soft-primary text-primary border-primary-subtle'
                        };
                    @endphp
                    <span class="badge {{ $badgeBg }} border px-1.5 py-0.5 rounded-pill fs-10 fw-semibold">{{ $activityTypeBadge }}</span>
                @endif
            </div>
            <span class="text-muted fs-10 font-monospace d-inline-flex align-items-center gap-1 flex-shrink-0">
                <i class="feather-clock fs-9 text-muted opacity-75"></i>{{ $timeStr }}
            </span>
        </div>

        <!-- Body Details -->
        @if($isInteraction)
            <div class="history-bubble mt-1.5 p-2 rounded-2 border">
                <div class="d-flex align-items-center gap-1 text-muted fs-10 text-uppercase fw-bold mb-1">
                    <i class="feather-message-square text-teal fs-11"></i>
                    <span>Interaction Notes</span>
                </div>
                @if(!empty($interactionNote))
                    <div class="fs-12 text-dark text-break fw-normal lh-sm" style="white-space: pre-wrap;">{{ $interactionNote }}</div>
                @else
                    <div class="fs-11 text-muted fst-italic">Interaction logged with customer.</div>
                @endif
            </div>
        @elseif($scheduledDateTime)
            <div class="d-flex align-items-center gap-2 mt-1.5 p-1.5 rounded-2 bg-soft-purple border border-purple-subtle text-purple fs-11">
                <i class="feather-calendar fs-12 flex-shrink-0"></i>
                <div class="text-truncate">
                    <span class="text-muted">Target Schedule:</span>
                    <strong class="text-purple ms-1 font-monospace">{{ $scheduledDateTime }}</strong>
                </div>
            </div>
        @elseif($isStatusChange && ($statusOld || $statusNew))
            <div class="d-flex align-items-center gap-2 mt-1.5 p-1.5 rounded-2 bg-light border">
                @if($statusOld)
                    <span class="badge bg-white text-secondary border px-2 py-0.5 rounded fs-11 fw-semibold shadow-2xs">{{ $statusOld }}</span>
                    <i class="feather-arrow-right text-muted fs-11"></i>
                @endif
                <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-0.5 rounded fs-11 fw-bold shadow-2xs">{{ $statusNew }}</span>
            </div>
        @elseif($assigneeName)
            <div class="d-flex align-items-center gap-1.5 mt-1 text-muted fs-11">
                <span>Assigned to</span>
                <span class="badge bg-soft-info text-info border border-info-subtle px-2 py-0.5 rounded-pill fw-semibold">{{ $assigneeName }}</span>
            </div>
        @elseif(!empty($rawNote))
            <div class="fs-12 text-secondary mt-1 lh-sm">{{ $rawNote }}</div>
        @endif

        <!-- Footer: Performer User & Relative Timestamp -->
        <div class="d-flex align-items-center justify-content-between mt-2 pt-1.5 border-top border-light-subtle fs-11 text-muted">
            <div class="d-flex align-items-center gap-1.5">
                @php
                    $avatarSrc = $user?->avatar_url ?: asset('assets/images/avatar/default.png');
                @endphp
                <img src="{{ $avatarSrc }}" alt="{{ $userName }}" class="rounded-circle border flex-shrink-0 shadow-2xs" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; object-fit: cover; border-color: #cbd5e1 !important;" onerror="this.onerror=null; this.src='{{ asset('assets/images/avatar/default.png') }}';">
                <span>by <strong class="text-dark">{{ $userName }}</strong></span>
            </div>
            @if($item->created_at)
                <span class="text-muted fs-10" title="{{ $item->created_at->format('d M Y, h:i A') }}">
                    {{ $item->created_at->diffForHumans() }}
                </span>
            @endif
        </div>

    </div>
</div>
