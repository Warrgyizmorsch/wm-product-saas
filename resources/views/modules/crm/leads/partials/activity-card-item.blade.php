@php
    $isPending = ($item->status === 'Pending');
    $isHistory = ($category === 'history' || $category === 'today_done' || !$isPending);

    // Activity type icon & colors
    $typeIcon = 'feather-phone';
    $typeBadgeBg = '#16a34a'; // green
    if($item->type === 'Email') { $typeIcon = 'feather-mail'; $typeBadgeBg = '#eab308'; }
    elseif($item->type === 'Meeting') { $typeIcon = 'feather-users'; $typeBadgeBg = '#8b5cf6'; }
    elseif($item->type === 'Demo') { $typeIcon = 'feather-monitor'; $typeBadgeBg = '#ef4444'; }
    elseif($item->type === 'WhatsApp') { $typeIcon = 'feather-message-circle'; $typeBadgeBg = '#10b981'; }

    // Status / Outcome
    $statusBadgeClass = 'bg-primary text-white';
    $statusLabel = $item->status ?: 'Pending';
    if($item->status === 'Completed' || $item->status === 'Connected') { $statusBadgeClass = 'bg-success text-white'; $statusLabel = 'Connected'; }
    elseif($item->status === 'Not Connected') { $statusBadgeClass = 'bg-warning text-dark'; $statusLabel = 'Not Connected'; }
    elseif($item->status === 'Not Answering') { $statusBadgeClass = 'bg-secondary text-white'; $statusLabel = 'Not Answering'; }
    elseif($item->status === 'Cancelled') { $statusBadgeClass = 'bg-danger text-white'; $statusLabel = 'Cancelled'; }
    elseif($item->status === 'Rescheduled') { $statusBadgeClass = 'bg-purple text-white'; $statusLabel = 'Rescheduled'; }

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
            if (empty($line)) continue;

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

<div class="odoo-activity-item">
    <div class="d-flex align-items-start gap-3">

        <!-- User Avatar with Green Check Badge -->
        <div class="position-relative flex-shrink-0 pt-0.5" style="width: 36px; height: 36px;">
            <div class="d-flex align-items-center justify-content-center text-white fw-bold shadow-2xs" 
                 style="background-color: #1e1b4b; width: 34px; height: 34px; border-radius: 6px; font-size: 14px; font-family: 'Inter', sans-serif;">
                {{ $avatarInitial }}
            </div>
            <span class="position-absolute d-flex align-items-center justify-content-center bg-success text-white rounded-circle shadow-xs" 
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
                        <span class="text-danger fw-bold me-1">{{ rtrim(__('crm.overdue_in', ['time' => $item->followup_date->diffForHumans(null, true)]), ':') }}:</span>
                    @elseif($item->followup_date->isToday())
                        <span class="text-warning fw-bold me-1">{{ rtrim(__('crm.due_today'), ':') }}:</span>
                    @else
                        <span class="text-success fw-bold me-1">{{ rtrim(__('crm.due_in', ['time' => $item->followup_date->diffForHumans(null, true)]), ':') }}:</span>
                    @endif

                    <span class="fw-bold activity-title-text">{{ __('crm.activity_types.' . $item->type) ?: $item->type }}</span>

                    @if($lead->contact_person || $lead->company_name)
                        <span class="text-secondary ms-1">for {{ $lead->contact_person ?: $lead->company_name }}</span>
                    @endif

                    <!-- Info (i) Tooltip/Popover Icon -->
                    <i class="feather-info text-muted ms-1 odoo-info-icon cursor-pointer" 
                       data-bs-toggle="popover" 
                       data-bs-trigger="hover focus" 
                       data-bs-placement="top" 
                       data-bs-html="true"
                       data-bs-title="{{ __('crm.activity_details') }}"
                       data-bs-content="<div class='fs-11'><strong>{{ __('crm.activity_type_label') }}</strong> {{ $item->type }}<br><strong>{{ __('crm.created_on_label') }}</strong> {{ $item->created_at->format('d/m/Y h:i A') }}<br><strong>{{ __('crm.created_by_label') }}</strong> {{ $lead->owner?->name ?: 'System' }}<br><strong>{{ __('crm.assigned_to_label') }}</strong> {{ $assignedNames }}<br><strong>{{ __('crm.due_on_label') }}</strong> {{ $item->followup_date->format('d/m/Y h:i A') }}</div>"
                       style="font-size: 12px;"></i>
                @else
                    <strong class="activity-title-text">{{ $item->user?->name ?: ($lead->owner?->name ?: 'System') }}</strong>
                    <span class="text-muted fs-11 ms-1">{{ $item->updated_at->format('h:i A') }}</span>
                    <span class="badge rounded-pill {{ $statusBadgeClass }} px-2 py-0.5 fs-10 ms-1 fw-semibold">{{ __('crm.' . strtolower(str_replace(' ', '_', $statusLabel))) ?: $statusLabel }}</span>
                    <span class="text-secondary fs-12 ms-1">&bull; {{ __('crm.activity_types.' . $item->type) ?: $item->type }} {{ __('crm.activity_log') }}</span>
                @endif

                <!-- Google Calendar / Meet Indicators in Header -->
                @if($calEventLink)
                    <a href="{{ $calEventLink }}" target="_blank" class="badge bg-primary-subtle text-primary border border-primary-subtle px-1.5 py-0.5 fs-10 fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 hover-opacity ms-1" title="{{ __('crm.view_in_google_calendar') ?? __('crm.synced_google_calendar') }}">
                        <i class="feather-calendar fs-10"></i> {{ __('crm.google_calendar') }}
                    </a>
                @endif
                @if($meetLink && $item->is_google_meet)
                    <a href="{{ $meetLink }}" target="_blank" class="badge bg-success-subtle text-success border border-success-subtle px-1.5 py-0.5 fs-10 fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 hover-opacity ms-0.5" title="{{ __('crm.join_google_meet_conf') }}">
                        <i class="feather-video fs-10"></i> {{ __('crm.google_meet') }}
                    </a>
                @endif
            </div>

            <!-- Structured Activity Details (Activity, Schedule, Notes, Next Step, etc.) -->
            @if(count($parsedFields) > 0)
                <div class="activity-details-block mb-1.5">
                    @foreach($parsedFields as $field)
                        <div class="mb-0.5 fs-12 activity-detail-row">
                            @if($field['label'])
                                <span class="activity-detail-key">{{ $field['label'] }}:</span>
                            @endif
                            <span class="activity-detail-val">{{ $field['value'] }}</span>
                        </div>
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
                    <a href="{{ asset($item->recording_url) }}" download="call_recording_{{ $item->id }}.mp3" class="btn btn-xs btn-light border py-0.5 px-2 fs-10 rounded" title="{{ __('crm.download_recording') }}">
                        <i class="feather-download fs-10 text-primary"></i>
                    </a>
                </div>
            @endif

            <!-- Line 3: Direct Action Options (Connected, Not Connected, Reschedule, Cancel, Call) -->
            @if($isPending)
                <div class="d-flex align-items-center flex-wrap pt-1.5 fs-12" style="column-gap: 16px; row-gap: 6px;">
                    <!-- Connected -->
                    <a href="javascript:void(0)" class="text-success text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline" data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_Completed">
                        <i class="feather-check fs-12"></i> <span>Connected</span>
                    </a>

                    <!-- Not Connected -->
                    <a href="javascript:void(0)" class="text-warning text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline" data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_NotConnected">
                        <i class="feather-phone-off fs-12"></i> <span>Not Connected</span>
                    </a>

                    <!-- Reschedule -->
                    <a href="javascript:void(0)" class="text-info text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline" data-bs-toggle="modal" data-bs-target="#rescheduleModal_{{ $item->id }}">
                        <i class="feather-repeat fs-12"></i> <span>Reschedule</span>
                    </a>

                    <!-- Cancel -->
                    <a href="javascript:void(0)" class="text-danger text-decoration-none fw-semibold d-inline-flex align-items-center gap-1 hover-underline" data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_Cancelled">
                        <i class="feather-x fs-12"></i> <span>Cancel</span>
                    </a>

                    @if($phoneToCall)
                        <!-- Call Now Button -->
                        <a href="javascript:void(0)" 
                           class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 fs-10 fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 hover-opacity btn-crm-click-to-call ms-auto"
                           data-lead-id="{{ $lead->id }}"
                           data-lead-name="{{ $lead->contact_person ?: $lead->company_name }}"
                           data-lead-company="{{ $lead->company_name }}"
                           data-lead-phone="{{ $phoneToCall }}"
                           title="{{ __('crm.click_to_call_softphone') }}">
                            <i class="feather-phone-call fs-10"></i> <span>Call {{ $phoneToCall }}</span>
                        </a>
                    @endif
                </div>

                @php
                    $currentTaggedIds = $item->taggedUsers->pluck('id')->toArray();
                @endphp

                <!-- Status Modal: Connected / Completed -->
                <x-ui.modal
                    :id="'statusModal_' . $item->id . '_Completed'"
                    :title="__('crm.update_activity_mark_connected')"
                    size="md"
                    :centered="true"
                    :formAction="route('crm.followups.update', $item->id)"
                    formMethod="PUT"
                    :submitText="__('crm.save_mark_connected')"
                    :closeText="__('crm.cancel')"
                >
                    <input type="hidden" name="status" value="Completed">
                    <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.discussion_summary')" name="notes" rows="3" :placeholder="__('crm.enter_notes_outcome')">{{ $item->notes }}</x-ui.odoo-form-ui>
                </x-ui.modal>

                <!-- Status Modal: Not Connected -->
                <x-ui.modal
                    :id="'statusModal_' . $item->id . '_NotConnected'"
                    :title="__('crm.update_activity_mark_not_connected')"
                    size="md"
                    :centered="true"
                    :formAction="route('crm.followups.update', $item->id)"
                    formMethod="PUT"
                    :submitText="__('crm.save_status')"
                    :closeText="__('crm.cancel')"
                >
                    <input type="hidden" name="status" value="Not Connected">
                    <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.discussion_summary')" name="notes" rows="3" :placeholder="__('crm.enter_notes_outcome')">{{ $item->notes }}</x-ui.odoo-form-ui>
                </x-ui.modal>

                <!-- Status Modal: Cancelled -->
                <x-ui.modal
                    :id="'statusModal_' . $item->id . '_Cancelled'"
                    :title="__('crm.update_activity_cancel')"
                    size="md"
                    :centered="true"
                    :formAction="route('crm.followups.update', $item->id)"
                    formMethod="PUT"
                    :submitText="__('crm.confirm_cancel')"
                    :closeText="__('crm.cancel')"
                >
                    <input type="hidden" name="status" value="Cancelled">
                    <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.cancellation_note')" name="notes" rows="3" :placeholder="__('crm.reason_cancellation_placeholder')">{{ $item->notes }}</x-ui.odoo-form-ui>
                </x-ui.modal>

                <!-- Reschedule Modal -->
                <x-ui.modal
                    :id="'rescheduleModal_' . $item->id"
                    :title="__('crm.reschedule_activity')"
                    size="md"
                    :centered="true"
                    :formAction="route('crm.followups.update', $item->id)"
                    formMethod="PUT"
                    :submitText="__('crm.confirm_reschedule')"
                    :closeText="__('crm.cancel')"
                >
                    <input type="hidden" name="is_reschedule" value="1">
                    <p class="text-muted fs-11 mb-3">
                        <i class="{{ $typeIcon }} me-1"></i>
                        {{ __('crm.activity_types.' . $item->type) ?: $item->type }}
                        &bull; Current: <strong>{{ $item->followup_date->format('d M Y, h:i A') }}</strong>
                    </p>
                    <x-ui.odoo-form-ui type="input" inputType="text" :label="__('crm.pick_new_date_time')" name="followup_date" class="reschedule-datepicker" :required="true" autocomplete="off" :placeholder="__('crm.pick_new_date_time')" />
                    <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.reschedule_note_optional')" name="notes" rows="2" :placeholder="__('crm.reason_reschedule_placeholder')">{{ $item->notes }}</x-ui.odoo-form-ui>
                </x-ui.modal>
            @endif

        </div>
    </div>
</div>
