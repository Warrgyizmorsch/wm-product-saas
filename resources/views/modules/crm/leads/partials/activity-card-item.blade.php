@php
    $isPending = ($item->status === 'Pending');
    $isHistory = ($category === 'history' || $category === 'today_done' || !$isPending);

    // Activity type icon & colors
    $typeIcon = 'feather-phone';
    $typeBadgeBg = '#16a34a'; // green
    if ($item->type === 'Email') {
        $typeIcon = 'feather-mail';
        $typeBadgeBg = '#eab308';
    } elseif ($item->type === 'Meeting') {
        $typeIcon = 'feather-users';
        $typeBadgeBg = '#8b5cf6';
    } elseif ($item->type === 'Demo') {
        $typeIcon = 'feather-monitor';
        $typeBadgeBg = '#ef4444';
    } elseif ($item->type === 'WhatsApp') {
        $typeIcon = 'feather-message-circle';
        $typeBadgeBg = '#10b981';
    }

    // Status / Outcome
    $statusBadgeClass = 'bg-primary text-white';
    $statusLabel = $item->status ?: 'Pending';
    if ($item->status === 'Completed' || $item->status === 'Connected') {
        $statusBadgeClass = 'bg-success text-white';
        $statusLabel = 'Connected';
    } elseif ($item->status === 'Not Connected') {
        $statusBadgeClass = 'bg-warning text-dark';
        $statusLabel = 'Not Connected';
    } elseif ($item->status === 'Not Answering') {
        $statusBadgeClass = 'bg-secondary text-white';
        $statusLabel = 'Not Answering';
    } elseif ($item->status === 'Cancelled') {
        $statusBadgeClass = 'bg-danger text-white';
        $statusLabel = 'Cancelled';
    } elseif ($item->status === 'Rescheduled') {
        $statusBadgeClass = 'bg-purple text-white';
        $statusLabel = 'Rescheduled';
    }

    // Avatar Initial
    $avatarInitial = strtoupper(substr($lead->owner?->name ?: ($lead->contact_person ?: 'M'), 0, 1));
    $avatarColors = ['#b45309', '#0369a1', '#4338ca', '#047857', '#be185d', '#c2410c'];
    $avatarBg = $avatarColors[abs(crc32($lead->owner?->name ?: 'Lead')) % count($avatarColors)];

    // Parse Google Meet & notes
    $rawNotes = $item->notes ?? '';
    $meetUrlFromNotes = null;
    $cleanNotes = $rawNotes;
    if (preg_match('/(Google Meet:\s*)(https?:\/\/\S+)/i', $rawNotes, $m)) {
        $meetUrlFromNotes = $m[2];
        $cleanNotes = trim(preg_replace('/\n?Google Meet:\s*https?:\/\/\S+/i', '', $rawNotes));
    }
    $meetLink = $item->google_meet_link ?? $meetUrlFromNotes;

    // Google Calendar Link
    $calEventLink = null;
    if (!empty($item->google_event_id) || $item->is_google_meet || $meetLink) {
        $calEventLink = 'https://calendar.google.com/calendar/r';
    }

    // Assigned / Tagged user string
    $assignedNames = $item->taggedUsers->pluck('name')->join(', ') ?: ($lead->owner?->name ?: 'Unassigned');

    // Phone
    $phoneToCall = $lead->phone ?: ($lead->mobile ?: '');

    // Parse structured details (Activity, Schedule, Notes, Next Step, etc.)
    $parsedFields = [];
    $hasActivityKey = false;
    $hasScheduleKey = false;
    $hasNotesKey = false;

    if (!empty($cleanNotes)) {
        $rawLines = preg_split('/\r\n|\r|\n/', trim($cleanNotes));
        foreach ($rawLines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $label = trim($matches[1]);
                $val = trim($matches[2]);

                // Normalize common internal labels
                if (strcasecmp($label, 'Scheduled action') === 0) {
                    $label = 'Activity';
                    $hasActivityKey = true;
                } elseif (strcasecmp($label, 'Context from call') === 0) {
                    $label = 'Notes';
                    $hasNotesKey = true;
                } elseif (strcasecmp($label, 'Activity') === 0) {
                    $hasActivityKey = true;
                } elseif (strcasecmp($label, 'Schedule') === 0) {
                    $hasScheduleKey = true;
                } elseif (strcasecmp($label, 'Notes') === 0 || strcasecmp($label, 'Note') === 0) {
                    $hasNotesKey = true;
                }

                $parsedFields[] = ['label' => $label, 'value' => $val];
            } else {
                $parsedFields[] = ['label' => null, 'value' => $line];
            }
        }
    }

    // Default Activity row if none found and title exists
    if (!$hasActivityKey && !empty($item->title)) {
        array_unshift($parsedFields, ['label' => 'Activity', 'value' => $item->title]);
        $hasActivityKey = true;
    }

    // Default Schedule row if none found in notes
    if (!$hasScheduleKey && $item->followup_date) {
        $typeLabel = __('crm.activity_types.' . $item->type) ?: $item->type;
        $scheduleText = "{$typeLabel} scheduled for " . $item->followup_date->format('d F Y \a\t h:i A') . ".";

        $inserted = false;
        for ($i = 0; $i < count($parsedFields); $i++) {
            if ($parsedFields[$i]['label'] === 'Activity') {
                array_splice($parsedFields, $i + 1, 0, [['label' => 'Schedule', 'value' => $scheduleText]]);
                $inserted = true;
                break;
            }
        }
        if (!$inserted) {
            array_unshift($parsedFields, ['label' => 'Schedule', 'value' => $scheduleText]);
        }
    }
@endphp

<div class="odoo-activity-item" id="lead-activity-{{ $item->id }}" data-activity-id="{{ $item->id }}">
    <div class="d-flex align-items-start gap-3">

        <!-- User Avatar with Green Check Badge -->
        <div class="position-relative flex-shrink-0 pt-0.5" style="width: 36px; height: 36px;">
            <div class="d-flex align-items-center justify-content-center text-white fw-bold shadow-2xs"
                style="background-color: #1e1b4b; width: 34px; height: 34px; border-radius: 6px; font-size: 14px; font-family: 'Inter', sans-serif;">
                {{ $avatarInitial }}
            </div>
            <span
                class="position-absolute d-flex align-items-center justify-content-center bg-success text-white rounded-circle shadow-xs"
                style="bottom: -1px; right: -1px; width: 16px; height: 16px; border: 2px solid #ffffff; font-size: 8px;">
                <i class="feather-check" style="stroke-width: 3.5;"></i>
            </span>
        </div>

        <!-- Main Content Area -->
        <div class="flex-grow-1 overflow-hidden">

            <!-- Line 1: Header Row (Left: Due Badge + Title + for Lead + Popover) -->
            <div class="d-flex align-items-baseline flex-wrap gap-1.5 fs-13 mb-1">
                @if(!$isHistory)
                    @if($category === 'overdue')
                        <span
                            class="text-danger fw-bold me-1">{{ rtrim(__('crm.overdue_in', ['time' => $item->followup_date->diffForHumans(null, true)]), ':') }}:</span>
                    @elseif($item->followup_date->isToday())
                        <span class="text-warning fw-bold me-1">{{ rtrim(__('crm.due_today'), ':') }}:</span>
                    @else
                        <span
                            class="text-success fw-bold me-1">{{ rtrim(__('crm.due_in', ['time' => $item->followup_date->diffForHumans(null, true)]), ':') }}:</span>
                    @endif

                    <span
                        class="fw-bold activity-title-text">{{ __('crm.activity_types.' . $item->type) ?: $item->type }}</span>

                    @if($lead->contact_person || $lead->company_name)
                        <span class="text-secondary ms-1">for {{ $lead->contact_person ?: $lead->company_name }}</span>
                    @endif

                    @php
                        $assigneeUser = $item->taggedUser ?: ($item->taggedUsers ? $item->taggedUsers->first() : null);
                        $leadOwnerUser = $lead->owner;
                        $currentAuthId = auth()->id();
                    @endphp

                    @if($assigneeUser)
                        @if($assigneeUser->id == $currentAuthId)
                            <span class="badge px-2 py-0.5 fs-10 d-inline-flex align-items-center gap-1 ms-1 shadow-2xs"
                                style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc !important;"
                                title="Assigned by {{ $leadOwnerUser?->name ?: 'Lead Owner' }}">
                                <i class="feather-user-check fs-10" style="color: #0284c7;"></i>
                                <span style="color: #075985;">Assigned by:</span>
                                <strong style="color: #0c4a6e;">{{ $leadOwnerUser?->name ?: 'Lead Owner' }}</strong>
                            </span>
                        @else
                            <span class="badge px-2 py-0.5 fs-10 d-inline-flex align-items-center gap-1 ms-1 shadow-2xs"
                                style="background-color: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe !important;"
                                title="Assigned to {{ $assigneeUser->name }}">
                                <i class="feather-share-2 fs-10" style="color: #9333ea;"></i>
                                <span style="color: #6b21a8;">Assigned to:</span>
                                <strong style="color: #3b0764;">{{ $assigneeUser->name }}</strong>
                            </span>
                        @endif
                    @endif

                    <!-- Info (i) Tooltip/Popover Icon -->
                    <i class="feather-info text-muted ms-1 odoo-info-icon cursor-pointer" data-bs-toggle="popover"
                        data-bs-trigger="hover focus" data-bs-placement="top" data-bs-html="true"
                        data-bs-title="{{ __('crm.activity_details') }}"
                        data-bs-content="<div class='fs-11'><strong>{{ __('crm.activity_type_label') }}</strong> {{ $item->type }}<br><strong>{{ __('crm.created_on_label') }}</strong> {{ $item->created_at->format('d/m/Y h:i A') }}<br><strong>{{ __('crm.created_by_label') }}</strong> {{ $lead->owner?->name ?: 'System' }}<br><strong>{{ __('crm.assigned_to_label') }}</strong> {{ $assignedNames }}<br><strong>{{ __('crm.due_on_label') }}</strong> {{ $item->followup_date->format('d/m/Y h:i A') }}</div>"
                        style="font-size: 12px;"></i>
                @else
                    <strong
                        class="activity-title-text">{{ $item->user?->name ?: ($lead->owner?->name ?: 'System') }}</strong>
                    <span class="text-muted fs-11 ms-1">{{ $item->updated_at->format('h:i A') }}</span>
                    <span
                        class="badge rounded-pill {{ $statusBadgeClass }} px-2 py-0.5 fs-10 ms-1 fw-semibold">{{ __('crm.' . strtolower(str_replace(' ', '_', $statusLabel))) ?: $statusLabel }}</span>
                    <span class="text-secondary fs-12 ms-1">&bull;
                        {{ __('crm.activity_types.' . $item->type) ?: $item->type }} {{ __('crm.activity_log') }}</span>
                @endif

                <!-- Google Calendar / Meet Indicators in Header -->
                @if($calEventLink)
                    <a href="{{ $calEventLink }}" target="_blank"
                        class="badge bg-primary-subtle text-primary border border-primary-subtle px-1.5 py-0.5 fs-10 fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 hover-opacity ms-1"
                        title="{{ __('crm.view_in_google_calendar') ?? __('crm.synced_google_calendar') }}">
                        <i class="feather-calendar fs-10"></i> {{ __('crm.google_calendar') }}
                    </a>
                @endif
                @if($meetLink && $item->is_google_meet)
                    <a href="{{ $meetLink }}" target="_blank"
                        class="badge bg-success-subtle text-success border border-success-subtle px-1.5 py-0.5 fs-10 fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 hover-opacity ms-0.5"
                        title="{{ __('crm.join_google_meet_conf') }}">
                        <i class="feather-video fs-10"></i> {{ __('crm.google_meet') }}
                    </a>
                @endif
            </div>

            <!-- Structured Activity Details (Activity, Schedule, Notes, Next Step, etc.) -->
            @if(count($parsedFields) > 0)
                <div class="activity-details-block mb-1.5">
                    @foreach($parsedFields as $field)
                        @php
                            $isDelegationRow = str_contains(strtolower($field['label'] ?? ''), 'assigned') || str_contains(strtolower($field['label'] ?? ''), 'delegated');
                        @endphp
                        @if($isDelegationRow)
                            <div
                                class="mb-1 p-1.5 rounded-2 bg-purple-subtle border border-purple-subtle fs-12 activity-detail-row">
                                <span class="fw-bold text-purple"><i
                                        class="feather-user-check me-1 fs-11"></i>{{ trim($field['label'], '[]') }}:</span>
                                <span class="text-dark fw-semibold ms-1">{{ $field['value'] }}</span>
                            </div>
                        @else
                            <div class="mb-0.5 fs-12 activity-detail-row">
                                @if($field['label'])
                                    <span class="activity-detail-key">{{ $field['label'] }}:</span>
                                @endif
                                <span class="activity-detail-val">{{ $field['value'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <!-- Call Recording Player (if present) -->
            @if(!empty($item->recording_url))
                <div class="d-flex align-items-center gap-2 my-2" style="max-width: 340px;">
                    <audio controls preload="none" style="height: 26px; width: 220px; outline: none;">
                        <source src="{{ asset($item->recording_url) }}" type="audio/mpeg">
                        <source src="{{ asset($item->recording_url) }}" type="audio/mp3">
                    </audio>
                    <a href="{{ asset($item->recording_url) }}" download="call_recording_{{ $item->id }}.mp3"
                        class="btn btn-xs btn-light border py-0.5 px-2 fs-10 rounded"
                        title="{{ __('crm.download_recording') }}">
                        <i class="feather-download fs-10 text-primary"></i>
                    </a>
                </div>
            @endif

            <!-- 1. Scheduled Meeting Venue (Planned) -->
            @if(!empty($item->location_address) || (!empty($item->latitude) && !empty($item->longitude)))
                <div class="d-flex align-items-center gap-2 my-1.5 p-1.5 px-2 rounded-2 bg-light border fs-11" style="max-width: 480px;">
                    <i class="feather-map-pin text-primary fs-12 flex-shrink-0"></i>
                    <div class="text-truncate">
                        <span class="text-muted fw-semibold">Scheduled Venue:</span>
                        @if(!empty($item->latitude) && !empty($item->longitude))
                            <a href="https://www.google.com/maps?q={{ $item->latitude }},{{ $item->longitude }}" target="_blank"
                                class="text-primary text-decoration-none fw-semibold hover-underline" title="View scheduled location on Google Maps">
                                {{ $item->location_address ?: ($item->latitude . ', ' . $item->longitude) }}
                                <i class="feather-external-link fs-10 ms-0.5"></i>
                            </a>
                        @else
                            <span class="text-dark">{{ $item->location_address }}</span>
                        @endif
                    </div>
                </div>
            @endif

            <!-- 2. Actual Field Visit Check-in Verification (Captured on Status Connected/Not Connected) -->
            @if(!empty($item->checkin_latitude) || !empty($item->checkin_at) || (!empty($item->selfie_path) && !$isPending))
                @php
                    $isMismatch = str_contains($item->checkin_status ?? '', 'Mismatch');
                @endphp
                <div class="d-flex align-items-start gap-2 my-2 p-2.5 rounded-3 border {{ $isMismatch ? 'bg-danger-subtle border-danger-subtle' : 'bg-success-subtle border-success-subtle' }}" style="max-width: 480px;">
                    @if(!empty($item->selfie_path))
                        <a href="{{ $item->selfie_url ?? asset($item->selfie_path) }}" target="_blank"
                            class="flex-shrink-0 d-block me-1.5"
                            title="View Verified Meeting Selfie">
                            <img src="{{ $item->selfie_url ?? asset($item->selfie_path) }}" class="rounded-2 border shadow-xs"
                                style="width: 48px; height: 48px; object-fit: cover;" alt="Selfie">
                        </a>
                    @endif
                    <div class="fs-11 text-secondary flex-grow-1" style="min-width: 0;">
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-1">
                            <span class="fw-semibold {{ $isMismatch ? 'text-danger' : 'text-success' }} d-inline-flex align-items-center gap-1">
                                <i class="feather-shield fs-12"></i> Field Visit Verification
                                @if($item->checkin_at)
                                    <span class="text-muted fw-normal">({{ \Carbon\Carbon::parse($item->checkin_at)->format('d M, h:i A') }})</span>
                                @elseif($item->location_verified_at)
                                    <span class="text-muted fw-normal">({{ $item->location_verified_at->format('d M, h:i A') }})</span>
                                @endif
                            </span>
                            @if(!empty($item->checkin_status))
                                <span class="badge {{ $isMismatch ? 'bg-danger text-white' : 'bg-success text-white' }} px-2 py-0.5 fs-10 fw-semibold">
                                    {{ $item->checkin_status }}
                                </span>
                            @endif
                        </div>

                        <!-- Actual Meeting Live Location -->
                        @if(!empty($item->checkin_latitude) && !empty($item->checkin_longitude))
                            <div class="d-flex align-items-center gap-1 mt-1 text-truncate">
                                <a href="https://www.google.com/maps?q={{ $item->checkin_latitude }},{{ $item->checkin_longitude }}"
                                    target="_blank"
                                    class="text-dark text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline"
                                    title="View meeting location on Google Maps">
                                    <i class="feather-crosshair text-danger fs-11"></i>
                                    <span class="text-truncate" style="max-width: 250px;">
                                        {{ $item->checkin_address ?: ($item->checkin_latitude . ', ' . $item->checkin_longitude) }}
                                    </span>
                                    <i class="feather-external-link fs-10 text-primary"></i>
                                </a>
                            </div>
                        @elseif(!empty($item->checkin_address))
                            <div class="text-dark mt-1 text-truncate" style="max-width: 300px;">
                                <i class="feather-crosshair text-danger fs-11 me-0.5"></i>{{ $item->checkin_address }}
                            </div>
                        @elseif(!empty($item->latitude) && !empty($item->longitude) && !$isPending)
                            <div class="d-flex align-items-center gap-1 mt-1">
                                <a href="https://www.google.com/maps?q={{ $item->latitude }},{{ $item->longitude }}"
                                    target="_blank"
                                    class="text-dark text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline">
                                    <i class="feather-crosshair text-danger fs-11"></i>
                                    <span class="text-truncate" style="max-width: 250px;">
                                        {{ $item->location_address ?: ($item->latitude . ', ' . $item->longitude) }}
                                    </span>
                                    <i class="feather-external-link fs-10 text-primary"></i>
                                </a>
                            </div>
                        @endif

                        @if(!empty($item->checkin_distance_meters))
                            <div class="text-muted fs-10 mt-1">
                                <i class="feather-navigation me-0.5 text-primary"></i>Distance from Scheduled Venue: <strong>{{ $item->checkin_distance_meters }}m</strong>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Line 3: Direct Action Options (Connected, Not Connected, Reschedule, Cancel, Call) -->
            @if($isPending)
                <div class="d-flex align-items-center flex-wrap pt-1.5 fs-12" style="column-gap: 16px; row-gap: 6px;">
                    <!-- Connected -->
                    <a href="javascript:void(0)"
                        class="text-success text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline"
                        data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_Completed"
                        onclick="crmInitVisitModal('{{ $item->id }}', 'Completed')">
                        <i class="feather-check fs-12"></i> <span>{{ __('crm.connected') }}</span>
                    </a>

                    <!-- Not Connected -->
                    <a href="javascript:void(0)"
                        class="text-warning text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline"
                        data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_NotConnected"
                        onclick="crmInitVisitModal('{{ $item->id }}', 'NotConnected')">
                        <i class="feather-phone-off fs-12"></i> <span>{{ __('crm.not_connected') }}</span>
                    </a>

                    <!-- Reschedule -->
                    <a href="javascript:void(0)"
                        class="text-info text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline"
                        data-bs-toggle="modal" data-bs-target="#rescheduleModal_{{ $item->id }}">
                        <i class="feather-repeat fs-12"></i> <span>{{ __('crm.reschedule') }}</span>
                    </a>

                    <!-- Cancel -->
                    <a href="javascript:void(0)"
                        class="text-danger text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline"
                        data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_Cancelled">
                        <i class="feather-x fs-12"></i> <span>{{ __('crm.cancel') }}</span>
                    </a>

                    <!-- Assign / Delegate -->
                    <a href="javascript:void(0)"
                        class="text-primary text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline"
                        data-bs-toggle="modal" data-bs-target="#assignModal_{{ $item->id }}"
                        title="Assign to another sales person">
                        <i class="feather-user-plus fs-12"></i> <span>Assign / Delegate</span>
                    </a>

                    @if($phoneToCall)
                        <!-- Call Now Button -->
                        <a href="javascript:void(0)"
                            class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 fs-10 fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 hover-opacity btn-crm-click-to-call ms-auto"
                            data-lead-id="{{ $lead->id }}" data-lead-name="{{ $lead->contact_person ?: $lead->company_name }}"
                            data-lead-company="{{ $lead->company_name }}" data-lead-phone="{{ $phoneToCall }}"
                            title="{{ __('crm.click_to_call_softphone') }}">
                            <i class="feather-phone-call fs-10"></i> <span>{{ __('crm.call') }} {{ $phoneToCall }}</span>
                        </a>
                    @endif
                </div>

                @php
                    $currentTaggedIds = $item->taggedUsers->pluck('id')->toArray();
                    $isGeoVerificationEnabled = !empty(tenant()?->settings['field_visit_verification']);
                    $isMeeting = ($item->type === 'Meeting');
                @endphp

                <!-- Status Modal: Connected / Completed -->
                <x-ui.modal :id="'statusModal_' . $item->id . '_Completed'"
                    :title="__('crm.update_activity_mark_connected')" size="md" :centered="true"
                    :formAction="route('crm.followups.update', $item->id)" formMethod="PUT"
                    formEnctype="multipart/form-data" :submitText="__('crm.save_mark_connected')"
                    :closeText="__('crm.cancel')">
                    <input type="hidden" name="status" value="Completed">
                    <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]"
                        :multiple="true" :searchable="true">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }}
                                ({{ $u->email }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    @if($isGeoVerificationEnabled && $isMeeting)
                        <div class="border rounded-3 p-3 bg-light mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                <span class="fs-12 fw-bold text-dark d-flex align-items-center gap-1">
                                    <i class="feather-shield text-primary fs-13"></i> Field Visit Verification
                                </span>
                                <span
                                    class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10 font-monospace">Required</span>
                            </div>

                            <!-- Scheduled Venue Reference Box -->
                            @if(!empty($item->location_address) || !empty($item->latitude))
                                <div class="p-2 mb-2 bg-white rounded border border-light-subtle fs-11">
                                    <div class="text-muted fs-10 text-uppercase fw-semibold mb-0.5">
                                        <i class="feather-map-pin text-primary me-1"></i>Scheduled Venue:
                                    </div>
                                    <div class="fw-semibold text-dark text-truncate" title="{{ $item->location_address ?: ($item->latitude . ', ' . $item->longitude) }}">
                                        {{ $item->location_address ?: ($item->latitude . ', ' . $item->longitude) }}
                                    </div>
                                </div>
                            @endif

                            <!-- Check-in GPS hidden inputs -->
                            <input type="hidden" name="checkin_latitude" id="lat_{{ $item->id }}_Completed" value="">
                            <input type="hidden" name="checkin_longitude" id="lng_{{ $item->id }}_Completed" value="">
                            <input type="hidden" name="checkin_address" id="addr_{{ $item->id }}_Completed" value="">

                            <!-- 1. GPS Location Section -->
                            <div class="mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label fs-11 fw-semibold text-secondary mb-0">
                                        <i class="feather-map-pin me-1 text-primary"></i>Live Check-in GPS <span
                                            class="text-danger">*</span>
                                    </label>
                                    <span id="loc_badge_{{ $item->id }}_Completed"
                                        class="badge bg-secondary-subtle text-muted fs-10">
                                        Required
                                    </span>
                                </div>
                                <div id="loc_box_{{ $item->id }}_Completed"
                                    class="d-flex align-items-center justify-content-between bg-white border rounded-2 p-2 fs-12">
                                    <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                        <span id="loc_status_dot_{{ $item->id }}_Completed"
                                            class="rounded-circle d-inline-block bg-warning"
                                            style="width: 8px; height: 8px; flex-shrink: 0;"></span>
                                        <span id="loc_text_{{ $item->id }}_Completed" class="text-muted text-truncate fs-12">
                                            Click Fetch GPS to capture live location
                                        </span>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-primary flex-shrink-0"
                                        onclick="crmCaptureLocation('{{ $item->id }}', 'Completed')">
                                        <i class="feather-crosshair me-1"></i>Fetch GPS
                                    </button>
                                </div>
                                <div id="loc_error_{{ $item->id }}_Completed" class="text-danger fs-11 mt-1 d-none">
                                    <i class="feather-alert-circle me-1"></i>Live location verification is required. Please click
                                    "Fetch GPS" to capture live coordinates.
                                </div>
                            </div>

                            <!-- 2. Front Camera Live Selfie Section (NO file upload!) -->
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label fs-11 fw-semibold text-secondary mb-0">
                                        <i class="feather-camera me-1 text-primary"></i>Live Front Camera Selfie <span
                                            class="text-danger">*</span>
                                    </label>
                                    <span id="selfie_badge_{{ $item->id }}_Completed"
                                        class="badge {{ $item->selfie_path ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-muted' }} fs-10">
                                        {{ $item->selfie_path ? 'Captured' : 'Required' }}
                                    </span>
                                </div>

                                <input type="hidden" name="selfie_base64" id="selfie_base64_{{ $item->id }}_Completed" value="">
                                <input type="hidden" id="has_existing_selfie_{{ $item->id }}_Completed"
                                    value="{{ $item->selfie_path ? '1' : '0' }}">
                                <canvas id="canvas_{{ $item->id }}_Completed" style="display:none;"></canvas>

                                <!-- State A: Idle / Click to Open Front Camera -->
                                <div id="camera_idle_{{ $item->id }}_Completed"
                                    class="{{ $item->selfie_path ? 'd-none' : '' }} bg-white border border-dashed rounded-2 p-3 text-center cursor-pointer hover-bg-light"
                                    onclick="crmStartCamera('{{ $item->id }}', 'Completed')">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-1.5"
                                        style="width: 38px; height: 38px;">
                                        <i class="feather-camera fs-18"></i>
                                    </div>
                                    <div class="fs-12 fw-semibold text-dark">Open Front Camera & Take Selfie</div>
                                    <div class="fs-10 text-muted mt-0.5">Click here to launch selfie camera directly</div>
                                </div>

                                <!-- State B: Active Live Camera Stream -->
                                <div id="camera_active_{{ $item->id }}_Completed"
                                    class="d-none bg-dark rounded-2 overflow-hidden position-relative shadow-sm text-center">
                                    <video id="video_{{ $item->id }}_Completed" autoplay playsinline muted
                                        style="width: 100%; max-height: 220px; object-fit: cover; transform: scaleX(-1); border-radius: 6px;"></video>
                                    <div
                                        class="p-2 bg-dark bg-opacity-75 d-flex align-items-center justify-content-center gap-2">
                                        <button type="button" class="btn btn-sm btn-success px-3 fw-semibold shadow-xs"
                                            onclick="crmSnapSelfie('{{ $item->id }}', 'Completed')">
                                            <i class="feather-camera me-1"></i>Snap Selfie
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-light"
                                            onclick="crmStopCamera('{{ $item->id }}', 'Completed')">
                                            <i class="feather-x me-1"></i>Cancel
                                        </button>
                                    </div>
                                </div>

                                <!-- State C: Captured Preview -->
                                <div id="camera_preview_{{ $item->id }}_Completed"
                                    class="{{ $item->selfie_path ? '' : 'd-none' }} bg-white border rounded-2 p-2 text-center">
                                    <div class="position-relative d-inline-block">
                                        <img id="selfie_img_{{ $item->id }}_Completed"
                                            src="{{ $item->selfie_url ?? ($item->selfie_path ? asset($item->selfie_path) : '') }}"
                                            class="rounded border shadow-xs"
                                            style="max-height: 160px; max-width: 100%; object-fit: cover;" alt="Selfie Preview">
                                        <span class="position-absolute top-0 end-0 badge bg-success m-1 shadow-xs fs-10">
                                            <i class="feather-check"></i> Captured
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-secondary"
                                            onclick="crmRetakeSelfie('{{ $item->id }}', 'Completed')">
                                            <i class="feather-refresh-cw me-1"></i>Retake Selfie
                                        </button>
                                    </div>
                                </div>

                                <!-- Validation error text for Selfie -->
                                <div id="selfie_error_{{ $item->id }}_Completed" class="text-danger fs-11 mt-1 d-none">
                                    <i class="feather-alert-circle me-1"></i>Front camera selfie is required. Please take a
                                    selfie before saving.
                                </div>
                            </div>
                        </div>
                    @endif

                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.discussion_summary')" name="notes" rows="3"
                        :placeholder="__('crm.enter_notes_outcome')">{{ $item->notes }}</x-ui.odoo-form-ui>
                </x-ui.modal>

                <!-- Status Modal: Not Connected -->
                <x-ui.modal :id="'statusModal_' . $item->id . '_NotConnected'"
                    :title="__('crm.update_activity_mark_not_connected')" size="md" :centered="true"
                    :formAction="route('crm.followups.update', $item->id)" formMethod="PUT"
                    formEnctype="multipart/form-data" :submitText="__('crm.save_status')" :closeText="__('crm.cancel')">
                    <input type="hidden" name="status" value="Not Connected">
                    <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]"
                        :multiple="true" :searchable="true">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }}
                                ({{ $u->email }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    @if($isGeoVerificationEnabled && $isMeeting)
                        <div class="border rounded-3 p-3 bg-light mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                <span class="fs-12 fw-bold text-dark d-flex align-items-center gap-1">
                                    <i class="feather-shield text-primary fs-13"></i> Field Visit Verification
                                </span>
                                <span
                                    class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10 font-monospace">Required</span>
                            </div>

                            <!-- Scheduled Venue Reference Box -->
                            @if(!empty($item->location_address) || !empty($item->latitude))
                                <div class="p-2 mb-2 bg-white rounded border border-light-subtle fs-11">
                                    <div class="text-muted fs-10 text-uppercase fw-semibold mb-0.5">
                                        <i class="feather-map-pin text-primary me-1"></i>Scheduled Venue:
                                    </div>
                                    <div class="fw-semibold text-dark text-truncate" title="{{ $item->location_address ?: ($item->latitude . ', ' . $item->longitude) }}">
                                        {{ $item->location_address ?: ($item->latitude . ', ' . $item->longitude) }}
                                    </div>
                                </div>
                            @endif

                            <!-- Check-in GPS hidden inputs -->
                            <input type="hidden" name="checkin_latitude" id="lat_{{ $item->id }}_NotConnected" value="">
                            <input type="hidden" name="checkin_longitude" id="lng_{{ $item->id }}_NotConnected" value="">
                            <input type="hidden" name="checkin_address" id="addr_{{ $item->id }}_NotConnected" value="">

                            <!-- 1. GPS Location Section -->
                            <div class="mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label fs-11 fw-semibold text-secondary mb-0">
                                        <i class="feather-map-pin me-1 text-primary"></i>Live Check-in GPS <span
                                            class="text-danger">*</span>
                                    </label>
                                    <span id="loc_badge_{{ $item->id }}_NotConnected"
                                        class="badge bg-secondary-subtle text-muted fs-10">
                                        Required
                                    </span>
                                </div>
                                <div id="loc_box_{{ $item->id }}_NotConnected"
                                    class="d-flex align-items-center justify-content-between bg-white border rounded-2 p-2 fs-12">
                                    <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                        <span id="loc_status_dot_{{ $item->id }}_NotConnected"
                                            class="rounded-circle d-inline-block bg-warning"
                                            style="width: 8px; height: 8px; flex-shrink: 0;"></span>
                                        <span id="loc_text_{{ $item->id }}_NotConnected" class="text-muted text-truncate fs-12">
                                            Click Fetch GPS to capture live location
                                        </span>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-primary flex-shrink-0"
                                        onclick="crmCaptureLocation('{{ $item->id }}', 'NotConnected')">
                                        <i class="feather-crosshair me-1"></i>Fetch GPS
                                    </button>
                                </div>
                                <div id="loc_error_{{ $item->id }}_NotConnected" class="text-danger fs-11 mt-1 d-none">
                                    <i class="feather-alert-circle me-1"></i>Live location verification is required. Please click
                                    "Fetch GPS" to capture live coordinates.
                                </div>
                            </div>

                            <!-- 2. Front Camera Live Selfie Section (NO file upload!) -->
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label fs-11 fw-semibold text-secondary mb-0">
                                        <i class="feather-camera me-1 text-primary"></i>Live Front Camera Selfie <span
                                            class="text-danger">*</span>
                                    </label>
                                    <span id="selfie_badge_{{ $item->id }}_NotConnected"
                                        class="badge {{ $item->selfie_path ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-muted' }} fs-10">
                                        {{ $item->selfie_path ? 'Captured' : 'Required' }}
                                    </span>
                                </div>

                                <input type="hidden" name="selfie_base64" id="selfie_base64_{{ $item->id }}_NotConnected"
                                    value="">
                                <input type="hidden" id="has_existing_selfie_{{ $item->id }}_NotConnected"
                                    value="{{ $item->selfie_path ? '1' : '0' }}">
                                <canvas id="canvas_{{ $item->id }}_NotConnected" style="display:none;"></canvas>

                                <!-- State A: Idle / Click to Open Front Camera -->
                                <div id="camera_idle_{{ $item->id }}_NotConnected"
                                    class="{{ $item->selfie_path ? 'd-none' : '' }} bg-white border border-dashed rounded-2 p-3 text-center cursor-pointer hover-bg-light"
                                    onclick="crmStartCamera('{{ $item->id }}', 'NotConnected')">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-1.5"
                                        style="width: 38px; height: 38px;">
                                        <i class="feather-camera fs-18"></i>
                                    </div>
                                    <div class="fs-12 fw-semibold text-dark">Open Front Camera & Take Selfie</div>
                                    <div class="fs-10 text-muted mt-0.5">Click here to launch selfie camera directly</div>
                                </div>

                                <!-- State B: Active Live Camera Stream -->
                                <div id="camera_active_{{ $item->id }}_NotConnected"
                                    class="d-none bg-dark rounded-2 overflow-hidden position-relative shadow-sm text-center">
                                    <video id="video_{{ $item->id }}_NotConnected" autoplay playsinline muted
                                        style="width: 100%; max-height: 220px; object-fit: cover; transform: scaleX(-1); border-radius: 6px;"></video>
                                    <div
                                        class="p-2 bg-dark bg-opacity-75 d-flex align-items-center justify-content-center gap-2">
                                        <button type="button" class="btn btn-sm btn-success px-3 fw-semibold shadow-xs"
                                            onclick="crmSnapSelfie('{{ $item->id }}', 'NotConnected')">
                                            <i class="feather-camera me-1"></i>Snap Selfie
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-light"
                                            onclick="crmStopCamera('{{ $item->id }}', 'NotConnected')">
                                            <i class="feather-x me-1"></i>Cancel
                                        </button>
                                    </div>
                                </div>

                                <!-- State C: Captured Preview -->
                                <div id="camera_preview_{{ $item->id }}_NotConnected"
                                    class="{{ $item->selfie_path ? '' : 'd-none' }} bg-white border rounded-2 p-2 text-center">
                                    <div class="position-relative d-inline-block">
                                        <img id="selfie_img_{{ $item->id }}_NotConnected"
                                            src="{{ $item->selfie_url ?? ($item->selfie_path ? asset($item->selfie_path) : '') }}"
                                            class="rounded border shadow-xs"
                                            style="max-height: 160px; max-width: 100%; object-fit: cover;" alt="Selfie Preview">
                                        <span class="position-absolute top-0 end-0 badge bg-success m-1 shadow-xs fs-10">
                                            <i class="feather-check"></i> Captured
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-secondary"
                                            onclick="crmRetakeSelfie('{{ $item->id }}', 'NotConnected')">
                                            <i class="feather-refresh-cw me-1"></i>Retake Selfie
                                        </button>
                                    </div>
                                </div>

                                <!-- Validation error text for Selfie -->
                                <div id="selfie_error_{{ $item->id }}_NotConnected" class="text-danger fs-11 mt-1 d-none">
                                    <i class="feather-alert-circle me-1"></i>Front camera selfie is required. Please take a
                                    selfie before saving.
                                </div>
                            </div>
                        </div>
                    @endif

                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.discussion_summary')" name="notes" rows="3"
                        :placeholder="__('crm.enter_notes_outcome')">{{ $item->notes }}</x-ui.odoo-form-ui>
                </x-ui.modal>

                <!-- Status Modal: Cancelled -->
                <x-ui.modal :id="'statusModal_' . $item->id . '_Cancelled'" :title="__('crm.update_activity_cancel')"
                    size="md" :centered="true" :formAction="route('crm.followups.update', $item->id)" formMethod="PUT"
                    :submitText="__('crm.confirm_cancel')" :closeText="__('crm.cancel')">
                    <input type="hidden" name="status" value="Cancelled">
                    <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]"
                        :multiple="true" :searchable="true">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }}
                                ({{ $u->email }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.cancellation_note')" name="notes" rows="3"
                        :placeholder="__('crm.reason_cancellation_placeholder')">{{ $item->notes }}</x-ui.odoo-form-ui>
                </x-ui.modal>

                <!-- Reschedule Modal -->
                <x-ui.modal :id="'rescheduleModal_' . $item->id" :title="__('crm.reschedule_activity')" size="md"
                    :centered="true" :formAction="route('crm.followups.update', $item->id)" formMethod="PUT"
                    :submitText="__('crm.confirm_reschedule')" :closeText="__('crm.cancel')">
                    <input type="hidden" name="is_reschedule" value="1">
                    <p class="text-muted fs-11 mb-3">
                        <i class="{{ $typeIcon }} me-1"></i>
                        {{ __('crm.activity_types.' . $item->type) ?: $item->type }}
                        &bull; Current: <strong>{{ $item->followup_date->format('d M Y, h:i A') }}</strong>
                    </p>
                    <x-ui.odoo-form-ui type="input" inputType="text" :label="__('crm.pick_new_date_time')"
                        name="followup_date" class="reschedule-datepicker" :required="true" autocomplete="off"
                        :placeholder="__('crm.pick_new_date_time')" />
                    <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]"
                        :multiple="true" :searchable="true">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }}
                                ({{ $u->email }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.reschedule_note_optional')" name="notes" rows="2"
                        :placeholder="__('crm.reason_reschedule_placeholder')">{{ $item->notes }}</x-ui.odoo-form-ui>
                </x-ui.modal>

                <!-- Assign / Delegate Modal -->
                <x-ui.modal :id="'assignModal_' . $item->id" title="Assign / Delegate Meeting" size="md" :centered="true"
                    :formAction="route('crm.meetings.assign', $item->id)" formMethod="POST" submitText="Confirm Assignment"
                    :closeText="__('crm.cancel')">
                    <p class="text-muted fs-11 mb-3">
                        <i class="feather-user-check me-1 text-primary"></i>
                        Select another sales person / team member to attend or take responsibility for this
                        {{ strtolower($item->type ?: 'meeting') }}.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semibold text-secondary mb-1">Select Sales Person <span
                                class="text-danger">*</span></label>
                        <select name="tagged_user_id" class="form-select form-select-sm" required>
                            <option value="">-- Choose Sales Person --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" @selected($u->id == ($item->tagged_user_id ?: ($currentTaggedIds[0] ?? null)))>
                                    {{ $u->name }} ({{ $u->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semibold text-secondary mb-1">Handover Note / Instructions
                            (Optional)</label>
                        <textarea name="notes" class="form-control form-control-sm fs-12" rows="2"
                            placeholder="e.g. Please visit client site today, discuss requirements..."></textarea>
                    </div>
                </x-ui.modal>
            @endif

        </div>
    </div>
</div>

@once
    <script>
        window.crmActiveStreams = window.crmActiveStreams || {};

        window.crmCaptureLocation = function (itemId, suffix, isRetry) {
            const key = itemId + '_' + suffix;
            const statusDot = document.getElementById('loc_status_dot_' + key);
            const locText = document.getElementById('loc_text_' + key);
            const locBox = document.getElementById('loc_box_' + key);
            const locError = document.getElementById('loc_error_' + key);
            const locBadge = document.getElementById('loc_badge_' + key);
            const latInput = document.getElementById('lat_' + key);
            const lngInput = document.getElementById('lng_' + key);
            const addrInput = document.getElementById('addr_' + key);

            if (!navigator.geolocation) {
                if (locText) {
                    locText.textContent = 'Geolocation not supported by this device.';
                    locText.className = 'text-danger text-truncate fs-12';
                }
                if (locError) {
                    locError.textContent = 'Geolocation is not supported by your browser or device.';
                    locError.classList.remove('d-none');
                }
                if (locBox) locBox.classList.add('border-danger');
                return;
            }

            if (locText) {
                locText.textContent = isRetry ? 'Detecting network location...' : 'Fetching live location...';
                locText.className = 'text-primary text-truncate fs-12';
            }
            if (statusDot) {
                statusDot.className = 'rounded-circle d-inline-block bg-info';
            }

            const options = isRetry
                ? { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
                : { enableHighAccuracy: true, timeout: 4000, maximumAge: 60000 };

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const lat = pos.coords.latitude.toFixed(7);
                    const lng = pos.coords.longitude.toFixed(7);
                    if (latInput) latInput.value = lat;
                    if (lngInput) lngInput.value = lng;
                    if (statusDot) {
                        statusDot.className = 'rounded-circle d-inline-block bg-success';
                    }
                    if (locText) {
                        locText.textContent = 'GPS: ' + lat + ', ' + lng;
                        locText.className = 'text-success fw-semibold text-truncate fs-12';
                    }
                    if (locBadge) {
                        locBadge.textContent = 'Verified';
                        locBadge.className = 'badge bg-success-subtle text-success border border-success-subtle fs-10';
                    }
                    if (locError) locError.classList.add('d-none');
                    if (locBox) locBox.classList.remove('border-danger');

                    // Silent Reverse Geocoding via Nominatim
                    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.display_name) {
                                if (addrInput) addrInput.value = data.display_name;
                                const parts = data.display_name.split(',').map(s => s.trim());
                                const shortAddr = parts.slice(0, 3).join(', ');
                                if (locText) locText.textContent = shortAddr || ('GPS: ' + lat + ', ' + lng);
                            }
                        })
                        .catch(() => { });
                },
                function (err) {
                    if (!isRetry && (err.code === err.POSITION_UNAVAILABLE || err.code === err.TIMEOUT)) {
                        return window.crmCaptureLocation(itemId, suffix, true);
                    }

                    let msg = 'Location unavailable. Click Fetch GPS to retry.';
                    if (err.code === err.PERMISSION_DENIED) {
                        msg = 'GPS Permission Blocked. Please allow location access in browser.';
                    } else if (err.code === err.TIMEOUT) {
                        msg = 'Location timed out. Click Fetch GPS to retry.';
                    }

                    if (statusDot) {
                        statusDot.className = 'rounded-circle d-inline-block bg-danger';
                    }
                    if (locText) {
                        locText.textContent = msg;
                        locText.className = 'text-danger text-truncate fs-12';
                    }
                    if (locError) {
                        locError.textContent = msg;
                        locError.classList.remove('d-none');
                    }
                    if (locBox) locBox.classList.add('border-danger');
                },
                options
            );
        };

        window.crmInitVisitModal = function (itemId, suffix) {
            setTimeout(function () {
                const latInput = document.getElementById('lat_' + itemId + '_' + suffix);
                if (latInput && !latInput.value) {
                    window.crmCaptureLocation(itemId, suffix, false);
                }
            }, 200);
        };

        window.crmStartCamera = function (itemId, suffix) {
            const key = itemId + '_' + suffix;
            const idleEl = document.getElementById('camera_idle_' + key);
            const activeEl = document.getElementById('camera_active_' + key);
            const previewEl = document.getElementById('camera_preview_' + key);
            const videoEl = document.getElementById('video_' + key);
            const selfieErr = document.getElementById('selfie_error_' + key);

            if (selfieErr) selfieErr.classList.add('d-none');
            if (idleEl) idleEl.classList.remove('border-danger');

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                if (selfieErr) {
                    selfieErr.textContent = 'Camera is not supported on this browser or insecure origin (requires HTTPS or localhost).';
                    selfieErr.classList.remove('d-none');
                }
                return;
            }

            navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                },
                audio: false
            })
                .then(function (stream) {
                    window.crmActiveStreams[key] = stream;
                    if (videoEl) {
                        videoEl.srcObject = stream;
                        videoEl.play();
                    }
                    if (idleEl) idleEl.classList.add('d-none');
                    if (previewEl) previewEl.classList.add('d-none');
                    if (activeEl) activeEl.classList.remove('d-none');
                })
                .catch(function (err) {
                    console.error('Camera error:', err);
                    let msg = 'Unable to access front camera. Please check camera permissions.';
                    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                        msg = 'Camera permission denied. Please allow camera access in browser.';
                    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                        msg = 'No camera found on this device.';
                    }
                    if (selfieErr) {
                        selfieErr.textContent = msg;
                        selfieErr.classList.remove('d-none');
                    }
                });
        };

        window.crmStopCamera = function (itemId, suffix) {
            const key = itemId + '_' + suffix;
            const stream = window.crmActiveStreams[key];
            if (stream) {
                stream.getTracks().forEach(function (track) {
                    track.stop();
                });
                delete window.crmActiveStreams[key];
            }
            const videoEl = document.getElementById('video_' + key);
            if (videoEl) {
                videoEl.srcObject = null;
            }
            const activeEl = document.getElementById('camera_active_' + key);
            const previewEl = document.getElementById('camera_preview_' + key);
            const idleEl = document.getElementById('camera_idle_' + key);
            const selfieInput = document.getElementById('selfie_base64_' + key);
            const existingSelfie = document.getElementById('has_existing_selfie_' + key);

            if (activeEl) activeEl.classList.add('d-none');
            if ((selfieInput && selfieInput.value) || (existingSelfie && existingSelfie.value === '1')) {
                if (previewEl) previewEl.classList.remove('d-none');
            } else {
                if (idleEl) idleEl.classList.remove('d-none');
            }
        };

        window.crmSnapSelfie = function (itemId, suffix) {
            const key = itemId + '_' + suffix;
            const videoEl = document.getElementById('video_' + key);
            const canvasEl = document.getElementById('canvas_' + key);
            const selfieInput = document.getElementById('selfie_base64_' + key);
            const previewImg = document.getElementById('selfie_img_' + key);
            const activeEl = document.getElementById('camera_active_' + key);
            const previewEl = document.getElementById('camera_preview_' + key);
            const idleEl = document.getElementById('camera_idle_' + key);
            const selfieErr = document.getElementById('selfie_error_' + key);
            const selfieBadge = document.getElementById('selfie_badge_' + key);

            if (!videoEl || !canvasEl) return;

            const width = videoEl.videoWidth || 640;
            const height = videoEl.videoHeight || 480;
            canvasEl.width = width;
            canvasEl.height = height;

            const ctx = canvasEl.getContext('2d');
            // Mirror horizontally so the snapped image looks natural as seen in front camera
            ctx.translate(width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(videoEl, 0, 0, width, height);

            const dataUrl = canvasEl.toDataURL('image/jpeg', 0.85);
            if (selfieInput) selfieInput.value = dataUrl;
            if (previewImg) {
                previewImg.src = dataUrl;
            }

            if (selfieBadge) {
                selfieBadge.textContent = 'Captured';
                selfieBadge.className = 'badge bg-success-subtle text-success border border-success-subtle fs-10';
            }
            if (selfieErr) selfieErr.classList.add('d-none');
            if (idleEl) idleEl.classList.remove('border-danger');

            // Stop video stream
            const stream = window.crmActiveStreams[key];
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                delete window.crmActiveStreams[key];
            }
            videoEl.srcObject = null;

            if (activeEl) activeEl.classList.add('d-none');
            if (previewEl) previewEl.classList.remove('d-none');
        };

        window.crmRetakeSelfie = function (itemId, suffix) {
            const key = itemId + '_' + suffix;
            const selfieInput = document.getElementById('selfie_base64_' + key);
            const existingSelfie = document.getElementById('has_existing_selfie_' + key);
            const selfieBadge = document.getElementById('selfie_badge_' + key);

            if (selfieInput) selfieInput.value = '';
            if (existingSelfie) existingSelfie.value = '0';
            if (selfieBadge) {
                selfieBadge.textContent = 'Required';
                selfieBadge.className = 'badge bg-secondary-subtle text-muted fs-10';
            }
            window.crmStartCamera(itemId, suffix);
        };

        window.crmValidateVisitForm = function (e, itemId, suffix) {
            const key = itemId + '_' + suffix;
            const latInput = document.getElementById('lat_' + key);
            const selfieInput = document.getElementById('selfie_base64_' + key);
            const locBox = document.getElementById('loc_box_' + key);
            const locError = document.getElementById('loc_error_' + key);
            const idleEl = document.getElementById('camera_idle_' + key);
            const selfieErr = document.getElementById('selfie_error_' + key);
            const existingSelfie = document.getElementById('has_existing_selfie_' + key);

            let isValid = true;

            // 1. Check GPS Location
            if (!latInput || !latInput.value || latInput.value.trim() === '') {
                isValid = false;
                if (locError) {
                    locError.textContent = 'Location verification is required. Please click "Fetch GPS" to capture live coordinates.';
                    locError.classList.remove('d-none');
                }
                if (locBox) {
                    locBox.classList.add('border-danger');
                }
            } else {
                if (locError) locError.classList.add('d-none');
                if (locBox) locBox.classList.remove('border-danger');
            }

            // 2. Check Selfie
            const hasSelfie = (selfieInput && selfieInput.value && selfieInput.value.trim() !== '') || (existingSelfie && existingSelfie.value === '1');
            if (!hasSelfie) {
                isValid = false;
                if (selfieErr) {
                    selfieErr.textContent = 'Front camera selfie is required. Please take a selfie before saving.';
                    selfieErr.classList.remove('d-none');
                }
                if (idleEl) {
                    idleEl.classList.add('border-danger');
                }
            } else {
                if (selfieErr) selfieErr.classList.add('d-none');
                if (idleEl) idleEl.classList.remove('border-danger');
            }

            return isValid;
        };

        // Global capturing submit listener to block submission if GPS or Selfie is missing
        if (!window.crmVisitSubmitListenerRegistered) {
            window.crmVisitSubmitListenerRegistered = true;
            document.addEventListener('submit', function (e) {
                const form = e.target;
                const modal = form.closest ? form.closest('.modal') : null;
                if (!modal || !modal.id) return;

                const match = modal.id.match(/^statusModal_(\d+)_(Completed|NotConnected)$/);
                if (!match) return;

                const itemId = match[1];
                const suffix = match[2];

                const latInput = document.getElementById('lat_' + itemId + '_' + suffix);
                const selfieInput = document.getElementById('selfie_base64_' + itemId + '_' + suffix);

                // Only validate if geo-verification inputs exist in this modal
                if (!latInput || !selfieInput) return;

                const isValid = window.crmValidateVisitForm(e, itemId, suffix);
                if (!isValid) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, true);

            // Turn off camera when modal is closed
            document.addEventListener('hidden.bs.modal', function (e) {
                if (e.target && e.target.id && e.target.id.startsWith('statusModal_')) {
                    const parts = e.target.id.replace('statusModal_', '').split('_');
                    if (parts.length >= 2) {
                        window.crmStopCamera(parts[0], parts[1]);
                    }
                }
            });
        }
    </script>
@endonce