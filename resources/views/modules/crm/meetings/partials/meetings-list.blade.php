@php
    $items = isset($meetings) ? $meetings : ($items ?? []);
@endphp

@if(empty($items) || count($items) === 0)
    <div class="text-center py-5">
        <div class="avatar-text avatar-lg bg-soft-secondary text-muted rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center"
            style="width: 52px; height: 52px;">
            <i class="feather-calendar fs-22"></i>
        </div>
        <h6 class="fw-bold text-dark mb-1">{{ $emptyMessage ?? __('crm.meetings_empty_default') }}</h6>
        <p class="text-muted fs-12 mb-0">{{ __('crm.meetings_empty_desc') }}</p>
    </div>
@else
    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table" class="mb-0">
            <thead>
                <tr>
                    <th style="width: 12%;">{{ __('crm.meetings_th_time') }}</th>
                    <th style="width: 20%;">{{ __('crm.meetings_th_lead_client') }}</th>
                    <th style="width: 26%;">{{ __('crm.meetings_th_meeting_remarks') }}</th>
                    <th style="width: 24%;">{{ __('crm.meetings_th_location') }}</th>
                    <th style="width: 11%;">{{ __('crm.meetings_th_status') }}</th>
                    <th style="width: 7%;" class="text-end pe-4">{{ __('crm.meetings_th_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    @php
                        $status = $item['status'] ?? 'Pending';
                        $isPending = ($status === 'Pending');
                        $isCompleted = ($status === 'Completed' || $status === 'Connected');
                        $isNotConnected = ($status === 'Not Connected');
                        $isCancelled = ($status === 'Cancelled');
                        $isRescheduled = ($status === 'Rescheduled');

                        $dueDate = !empty($item['followup_date']) ? \Illuminate\Support\Carbon::parse($item['followup_date']) : null;
                        $lead = $item['lead'] ?? null;
                        $deal = $item['deal'] ?? null;
                        $clientCompany = $deal['customer_name'] ?? ($lead['company_name'] ?? 'Prospect');
                        $contactPerson = $lead['contact_person'] ?? ($deal['contact_name'] ?? $clientCompany);
                        $clientPhone = $lead['phone'] ?? null;
                        $clientEmail = $lead['email'] ?? null;
                        $isGeoRequired = !empty($item['is_geo_verification_required']);
                        $hasScheduledLoc = !empty($item['location_address']) || (!empty($item['latitude']) && !empty($item['longitude']));
                        $hasVisitedLoc = !empty($item['checkin_latitude']) || !empty($item['checkin_address']);
                    @endphp

                    <tr class="meeting-table-row {{ !empty($isOverdue) ? 'table-danger-subtle' : '' }}" style="vertical-align: middle;">
                        {{-- 1. TIME --}}
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width: 26px; height: 26px; font-size: 11px;">
                                    <i class="feather-calendar"></i>
                                </div>
                                <div class="lh-sm">
                                    <span class="d-block fw-bold text-dark fs-12">
                                        {{ $dueDate ? $dueDate->format('d/m/Y') : 'N/A' }}
                                    </span>
                                    <span class="text-muted fs-11">
                                        {{ $dueDate ? $dueDate->format('h:i A') : '' }}
                                    </span>
                                    @if(!empty($isOverdue))
                                        <div class="mt-0.5">
                                            <span class="badge bg-danger text-white fs-9 px-1.5 py-0.2 rounded-pill">{{ __('crm.meetings_overdue_badge') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- 2. LEAD / CLIENT & CONTACT --}}
                        <td>
                            <div class="d-flex flex-column gap-0.5" style="max-width: 230px;">
                                <div class="d-flex align-items-center gap-1 text-truncate">
                                    <a href="javascript:void(0)" data-bs-toggle="offcanvas"
                                        data-bs-target="#meetingEntityOffcanvas_{{ $item['id'] }}"
                                        class="fw-bold text-dark text-decoration-none hover-primary text-truncate fs-12" title="{{ $clientCompany }}">
                                        {{ $clientCompany }}
                                    </a>
                                    @if($deal && !empty($deal['id']))
                                        <span class="badge bg-light text-success border font-monospace px-1 py-0 fs-10 flex-shrink-0">
                                            {{ $deal['deal_number'] ?: ('DL-' . $deal['id']) }}
                                        </span>
                                    @elseif($lead && !empty($lead['id']))
                                        <span class="badge bg-light text-primary border font-monospace px-1 py-0 fs-10 flex-shrink-0">
                                            {{ $lead['lead_number'] ?: ('LD-' . $lead['id']) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="text-muted fs-11 text-truncate">
                                    <span><i class="feather-user me-0.5 fs-10 text-muted"></i>{{ $contactPerson ?: 'N/A' }}</span>
                                    @if($clientPhone)
                                        <span class="mx-0.5 opacity-50">&bull;</span>
                                        <a href="tel:{{ $clientPhone }}" class="text-muted text-decoration-none hover-primary">
                                            {{ $clientPhone }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- 3. MEETING & REMARKS --}}
                        <td>
                            <div class="d-flex flex-column gap-1" style="max-width: 280px;">
                                {{-- Meeting Title & Duration --}}
                                <div class="d-flex align-items-center gap-1.5 text-truncate">
                                    <span class="fw-bold text-dark fs-12 text-truncate"
                                        title="{{ $item['title'] ?: 'Scheduled Field Meeting' }}">
                                        {{ $item['title'] ?: 'Scheduled Field Meeting' }}
                                    </span>
                                    <span class="badge bg-light text-secondary border px-1.5 py-0 fs-10 fw-medium flex-shrink-0">
                                        <i class="feather-clock fs-9 text-muted me-0.5"></i>{{ $item['duration_minutes'] ?? 30 }}m
                                    </span>
                                    @if(!empty($item['google_meet_link']))
                                        <a href="{{ $item['google_meet_link'] }}" target="_blank" rel="noopener noreferrer"
                                            class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1 fs-10 text-decoration-none flex-shrink-0">
                                            <i class="feather-video fs-9"></i> Meet
                                        </a>
                                    @endif
                                </div>

                                {{-- Remarks / Notes Single Line Preview with Tooltip & Modal --}}
                                @if(!empty($item['notes']))
                                    @php
                                        $rawNotes = trim($item['notes']);
                                        $cleanNotes = preg_replace('/\[Geo-Fence[^\]]*\]/i', '', $rawNotes);
                                        $cleanNotes = preg_replace('/\[\d{4}-\d{2}-\d{2}[^\]]*Status:[^\]]*\]/i', '', $cleanNotes);
                                        $cleanNotes = trim(preg_replace('/\s+/', ' ', $cleanNotes));

                                        $geoFenceTag = null;
                                        if (preg_match('/\[Geo-Fence[^\]]*\]/i', $rawNotes, $geoMatches)) {
                                            $geoFenceTag = trim($geoMatches[0], '[]');
                                        }

                                        $displayNoteText = !empty($cleanNotes) ? $cleanNotes : (!empty($geoFenceTag) ? $geoFenceTag : $rawNotes);
                                        $isLong = mb_strlen($rawNotes) > 40 || str_contains($rawNotes, "\n");
                                        $shortPreview = \Illuminate\Support\Str::limit($displayNoteText, 40, '...');
                                    @endphp
                                    <div class="d-flex align-items-center gap-1 text-truncate text-secondary fs-11"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $rawNotes }}">
                                        <i class="feather-message-square text-primary fs-10 flex-shrink-0"></i>
                                        <span class="text-truncate" style="min-width: 0;">{{ $shortPreview }}</span>
                                        @if($isLong)
                                            <a href="javascript:void(0)" class="text-primary fw-semibold fs-10 text-decoration-none hover-underline flex-shrink-0 ms-0.5"
                                                data-bs-toggle="modal" data-bs-target="#noteModal_{{ $item['id'] }}"
                                                onclick="event.stopPropagation();">
                                                {{ __('crm.meetings_more_link') }}
                                            </a>
                                        @endif
                                    </div>

                                    @if($isLong)
                                        {{-- Note View Modal --}}
                                        <div class="modal fade" id="noteModal_{{ $item['id'] }}" tabindex="-1" aria-hidden="true" onclick="event.stopPropagation();">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow-lg">
                                                    <div class="modal-header py-2.5 px-3 border-bottom bg-light">
                                                        <h6 class="modal-title fs-13 fw-bold text-dark d-flex align-items-center gap-1.5 mb-0">
                                                            <i class="feather-file-text text-primary"></i> {{ __('crm.meetings_notes_modal_title') }}
                                                        </h6>
                                                        <button type="button" class="btn-close fs-10" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-3">
                                                        <div class="mb-3 p-2.5 rounded-2 bg-light border">
                                                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                                                <span class="fw-bold text-dark fs-13">{{ $item['title'] ?: 'Field Meeting' }}</span>
                                                                <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-10">
                                                                    {{ $item['duration_minutes'] ?? 30 }} {{ __('crm.meetings_mins') }}
                                                                </span>
                                                            </div>
                                                            <div class="fs-11 text-muted mb-1">
                                                                <strong>Client:</strong> <span class="text-dark fw-semibold">{{ $clientCompany }}</span> ({{ $contactPerson ?: 'N/A' }})
                                                            </div>
                                                            @if($hasScheduledLoc)
                                                                <div class="fs-11 text-muted d-flex align-items-start gap-1 mb-1">
                                                                    <i class="feather-map-pin text-primary mt-0.5 flex-shrink-0 fs-11"></i>
                                                                    <div>
                                                                        <strong class="text-dark">{{ __('crm.meetings_scheduled_venue') }}</strong>
                                                                        <span>{{ $item['location_address'] ?: ($item['latitude'] . ', ' . $item['longitude']) }}</span>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            @if($hasVisitedLoc)
                                                                <div class="fs-11 text-muted d-flex align-items-start gap-1">
                                                                    <i class="feather-navigation text-success mt-0.5 flex-shrink-0 fs-11"></i>
                                                                    <div>
                                                                        <strong class="text-dark">{{ __('crm.meetings_visited_at') }}</strong>
                                                                        <span>{{ $item['checkin_address'] ?: ($item['checkin_latitude'] . ', ' . $item['checkin_longitude']) }}</span>
                                                                        @if(!empty($item['checkin_distance_meters']))
                                                                            <span class="badge bg-soft-info text-info border border-info-subtle ms-1 fs-10">{{ $item['checkin_distance_meters'] }}m {{ __('crm.meetings_distance_label') }}</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="fs-11 fw-bold text-muted text-uppercase mb-1 d-block" style="letter-spacing: 0.3px;">{{ __('crm.meetings_notes_and_remarks') }}</label>
                                                            <div class="p-3 rounded-2 border-start border-3 border-primary bg-light-subtle border-top border-end border-bottom fs-12 text-dark lh-base" style="white-space: pre-wrap; word-break: break-word;">
                                                                {{ $displayNoteText }}
                                                            </div>
                                                        </div>

                                                        @if(!empty($geoFenceTag))
                                                            <div class="mt-2.5 p-2 rounded-2 fs-11 d-flex align-items-center gap-1.5 {{ str_contains($geoFenceTag, 'Mismatch') ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle' }}">
                                                                <i class="feather-shield flex-shrink-0 fs-12"></i>
                                                                <div>
                                                                    <strong>{{ __('crm.meetings_geo_audit_log') }}</strong> {{ $geoFenceTag }}
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="modal-footer py-2 px-3 bg-light border-top">
                                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{{ __('crm.close') ?: 'Close' }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                {{-- Delegation / Assignment Badge if applicable --}}
                                @php
                                    $assignedTo = $item['assigned_to'] ?? $item['tagged_user'] ?? null;
                                    if (!$assignedTo && !empty($item['tagged_users'])) {
                                        $tUsers = $item['tagged_users'];
                                        $assignedTo = is_array($tUsers) ? ($tUsers[0] ?? null) : (is_object($tUsers) && method_exists($tUsers, 'first') ? $tUsers->first() : null);
                                    }
                                    $assignedBy = $item['assigned_by'] ?? ($deal['deal_owner'] ?? ($lead['lead_owner'] ?? null));
                                    $currentUserId = auth()->id();
                                    $isAssignedToMe = $assignedTo && (($assignedTo['id'] ?? null) == $currentUserId);
                                @endphp

                                @if(!empty($assignedTo))
                                    <div class="text-truncate">
                                        <span class="badge bg-light text-secondary border px-1.5 py-0 fs-10 fw-normal">
                                            <i class="feather-user me-0.5 fs-9 text-muted"></i>{{ $isAssignedToMe ? 'By: ' . ($assignedBy['name'] ?? 'Owner') : 'To: ' . $assignedTo['name'] }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </td>

                        {{-- 4. LOCATION (PLAN & VISIT) - Smooth Compact Format --}}
                        <td>
                            <div class="d-flex flex-column gap-1" style="max-width: 280px;">
                                {{-- 1. Scheduled Venue --}}
                                @if($hasScheduledLoc)
                                    <div class="d-flex align-items-center gap-1 text-truncate"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Scheduled Venue: {{ $item['location_address'] ?: ($item['latitude'] . ', ' . $item['longitude']) }}">
                                        <i class="feather-map-pin text-primary fs-11 flex-shrink-0"></i>
                                        <span class="text-muted fs-11 flex-shrink-0">{{ __('crm.meetings_plan_label') }}</span>
                                        <a href="https://maps.google.com/?q={{ $item['latitude'] ?? '' }},{{ $item['longitude'] ?? '' }}" target="_blank" rel="noopener noreferrer"
                                            class="text-dark fw-medium fs-11 text-truncate hover-underline text-decoration-none">
                                            {{ $item['location_address'] ?: ($item['latitude'] . ', ' . $item['longitude']) }}
                                        </a>
                                    </div>
                                @else
                                    <div class="text-muted fs-11 d-flex align-items-center gap-1">
                                        <i class="feather-map-pin text-muted fs-10 flex-shrink-0"></i>
                                        <span class="text-muted fs-11">{{ __('crm.meetings_no_venue_scheduled') }}</span>
                                    </div>
                                @endif

                                {{-- 2. Actual Visited / Check-in Location --}}
                                @if($hasVisitedLoc)
                                    @php
                                        $isMismatch = str_contains($item['checkin_status'] ?? '', 'Mismatch');
                                        $distMeters = $item['checkin_distance_meters'] ?? null;
                                        $distLabel = $distMeters !== null ? ($distMeters < 1000 ? $distMeters . 'm' : round($distMeters / 1000, 1) . 'km') : null;
                                    @endphp
                                    <div class="d-flex align-items-center gap-1 text-truncate"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="Actual Visit: {{ $item['checkin_address'] ?: ($item['checkin_latitude'] . ', ' . $item['checkin_longitude']) }} ({{ $item['checkin_status'] }})">
                                        <i class="feather-navigation {{ $isMismatch ? 'text-danger' : 'text-success' }} fs-11 flex-shrink-0"></i>
                                        <span class="{{ $isMismatch ? 'text-danger' : 'text-success' }} fs-11 fw-semibold flex-shrink-0">{{ __('crm.meetings_visit_label') }}</span>
                                        <a href="https://maps.google.com/?q={{ $item['checkin_latitude'] ?? '' }},{{ $item['checkin_longitude'] ?? '' }}" target="_blank" rel="noopener noreferrer"
                                            class="text-dark fw-medium fs-11 text-truncate hover-underline text-decoration-none flex-grow-1" style="min-width: 0;">
                                            {{ $item['checkin_address'] ?: ($item['checkin_latitude'] . ', ' . $item['checkin_longitude']) }}
                                        </a>
                                        @if($distLabel)
                                            <span class="badge {{ $isMismatch ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle' }} px-1 py-0 fs-10 fw-semibold rounded flex-shrink-0">
                                                {{ $distLabel }}
                                            </span>
                                        @endif
                                    </div>
                                @elseif($isPending && $isGeoRequired)
                                    <div class="text-muted fs-11 d-flex align-items-center gap-1">
                                        <i class="feather-shield text-info fs-10 flex-shrink-0"></i>
                                        <span class="text-muted fs-10">{{ __('crm.meetings_geo_checkin_required') }}</span>
                                    </div>
                                @endif
                            </div>
                        </td>

                        {{-- 5. STATUS --}}
                        <td>
                            <div class="d-flex flex-column gap-1 align-items-start">
                                <div>
                                    @if($isCompleted)
                                        <span
                                            class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1 rounded">
                                            <span class="rounded-circle bg-success d-inline-block"
                                                style="width: 6px; height: 6px;"></span> {{ __('crm.meetings_status_connected') }}
                                        </span>
                                    @elseif($isNotConnected)
                                        <span
                                            class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1 rounded">
                                            <span class="rounded-circle bg-warning d-inline-block"
                                                style="width: 6px; height: 6px;"></span> {{ __('crm.meetings_status_not_connected') }}
                                        </span>
                                    @elseif($isCancelled)
                                        <span
                                            class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1 rounded">
                                            <span class="rounded-circle bg-danger d-inline-block"
                                                style="width: 6px; height: 6px;"></span> {{ __('crm.meetings_status_cancelled') }}
                                        </span>
                                    @elseif($isRescheduled)
                                        <span
                                            class="badge bg-soft-info text-info border border-info-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1 rounded">
                                            <span class="rounded-circle bg-info d-inline-block"
                                                style="width: 6px; height: 6px;"></span> {{ __('crm.meetings_status_rescheduled') }}
                                        </span>
                                    @else
                                        <span
                                            class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1 rounded">
                                            <span class="rounded-circle bg-primary d-inline-block"
                                                style="width: 6px; height: 6px;"></span> {{ __('crm.meetings_status_pending') }}
                                        </span>
                                    @endif
                                </div>

                                @if(!empty($item['is_geo_verified']))
                                    @php
                                        $actualLoc = !empty($item['checkin_address']) 
                                             ? $item['checkin_address'] 
                                             : ((!empty($item['checkin_latitude']) && !empty($item['checkin_longitude'])) 
                                                 ? ('GPS: ' . round($item['checkin_latitude'], 5) . ', ' . round($item['checkin_longitude'], 5)) 
                                                 : '');
                                        $actualGps = (!empty($item['checkin_latitude']) && !empty($item['checkin_longitude'])) 
                                             ? (round($item['checkin_latitude'], 5) . ',' . round($item['checkin_longitude'], 5)) 
                                             : '';
                                        $actualTime = !empty($item['checkin_at']) 
                                             ? \Illuminate\Support\Carbon::parse($item['checkin_at'])->format('d M, Y - h:i A') 
                                             : '';
                                        $actualStatus = $item['checkin_status'] ?? 'Verified';
                                        $isMismatch = str_contains($actualStatus, 'Mismatch');
                                    @endphp
                                    <div>
                                        @if(!empty($item['checkin_status']))
                                            <span
                                                class="badge {{ $isMismatch ? 'bg-soft-danger text-danger border border-danger-subtle' : 'bg-soft-success text-success border border-success-subtle' }} fs-10 fw-semibold px-2 py-0.5 cursor-pointer d-inline-flex align-items-center gap-1 rounded"
                                                onclick="crmOpenSelfieModal('{{ $item['selfie_url'] }}', '{{ addslashes($actualLoc) }}', '{{ addslashes($actualGps) }}', '{{ addslashes($actualTime) }}', '{{ addslashes($actualStatus) }}')"
                                                title="Click to view field selfie proof ({{ $item['checkin_status'] }})">
                                                <i class="feather-shield fs-9"></i> {{ $item['checkin_status'] }}
                                                @if(!empty($item['selfie_url']))
                                                    <i class="feather-camera fs-9 ms-0.5 opacity-75"></i>
                                                @endif
                                            </span>
                                        @else
                                            <span
                                                class="badge bg-soft-success text-success border border-success-subtle fs-10 fw-semibold px-2 py-0.5 cursor-pointer d-inline-flex align-items-center gap-1 rounded"
                                                onclick="crmOpenSelfieModal('{{ $item['selfie_url'] }}', '{{ addslashes($actualLoc) }}', '{{ addslashes($actualGps) }}', '{{ addslashes($actualTime) }}', '{{ addslashes($actualStatus) }}')"
                                                title="Click to view field selfie proof">
                                                <i class="feather-shield fs-9"></i> {{ __('crm.meetings_verified_badge') }}
                                                @if(!empty($item['selfie_url']))
                                                    <i class="feather-camera fs-9 ms-0.5 opacity-75"></i>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                @elseif($isPending && $isGeoRequired)
                                    <div>
                                        <span
                                            class="badge bg-light text-muted border fs-10 px-2 py-0.5 d-inline-flex align-items-center gap-1 rounded"
                                            title="Field Geo-Verification Required">
                                            <i class="feather-map-pin text-info fs-9"></i> {{ __('crm.meetings_geo_req_badge') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </td>

                        {{-- 6. ACTIONS (Matching Lead Index Action-Dropdown Component) --}}
                        <td class="text-end pe-4">
                            <x-ui.action-dropdown :align="'end'">
                                <x-slot:extraActions>
                                    @if($isPending)
                                        {{-- Connected Icon Button --}}
                                        <button type="button" class="action-dropdown-btn text-success" title="{{ __('crm.meetings_mark_connected') }}"
                                            data-bs-toggle="modal" data-bs-target="#meetingModal_{{ $item['id'] }}_Connected"
                                            onclick="crmInitVisitModal('{{ $item['id'] }}', 'Connected')">
                                            <i class="feather-check text-success"></i>
                                        </button>

                                        {{-- Not Connected Icon Button --}}
                                        <button type="button" class="action-dropdown-btn text-warning" title="{{ __('crm.meetings_mark_not_connected') }}"
                                            data-bs-toggle="modal" data-bs-target="#meetingModal_{{ $item['id'] }}_NotConnected"
                                            onclick="crmInitVisitModal('{{ $item['id'] }}', 'NotConnected')">
                                            <i class="feather-phone-off text-warning"></i>
                                        </button>

                                        {{-- Reschedule Icon Button --}}
                                        <button type="button" class="action-dropdown-btn text-info" title="{{ __('crm.meetings_reschedule') }}"
                                            data-bs-toggle="modal" data-bs-target="#meetingRescheduleModal_{{ $item['id'] }}">
                                            <i class="feather-calendar text-info"></i>
                                        </button>
                                    @else
                                        @if(!empty($item['selfie_url']))
                                            @php
                                                $actionActualLoc = !empty($item['checkin_address']) 
                                                    ? $item['checkin_address'] 
                                                    : ((!empty($item['checkin_latitude']) && !empty($item['checkin_longitude'])) 
                                                        ? ('GPS: ' . round($item['checkin_latitude'], 5) . ', ' . round($item['checkin_longitude'], 5)) 
                                                        : '');
                                                $actionActualGps = (!empty($item['checkin_latitude']) && !empty($item['checkin_longitude'])) 
                                                    ? (round($item['checkin_latitude'], 5) . ',' . round($item['checkin_longitude'], 5)) 
                                                    : '';
                                                $actionActualTime = !empty($item['checkin_at']) 
                                                    ? \Illuminate\Support\Carbon::parse($item['checkin_at'])->format('d M, Y - h:i A') 
                                                    : '';
                                                $actionActualStatus = $item['checkin_status'] ?? 'Verified';
                                            @endphp
                                            <button type="button" class="action-dropdown-btn text-success"
                                                title="{{ __('crm.meetings_view_selfie') }}"
                                                onclick="crmOpenSelfieModal('{{ $item['selfie_url'] }}', '{{ addslashes($actionActualLoc) }}', '{{ addslashes($actionActualGps) }}', '{{ addslashes($actionActualTime) }}', '{{ addslashes($actionActualStatus) }}')">
                                                <i class="feather-image text-success"></i>
                                            </button>
                                        @endif
                                    @endif
                                </x-slot:extraActions>

                                {{-- More Actions Dropdown Slot --}}
                                @if($isPending)
                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item py-1.5 text-primary"
                                            data-bs-toggle="modal" data-bs-target="#meetingAssignModal_{{ $item['id'] }}">
                                            <i class="feather-user-plus me-2 text-primary fs-12"></i>{{ __('crm.meetings_assign_to_colleague') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item py-1.5 text-danger" data-bs-toggle="modal"
                                            data-bs-target="#meetingCancelModal_{{ $item['id'] }}">
                                            <i class="feather-x-circle me-2 text-danger fs-12"></i>{{ __('crm.meetings_cancel_meeting') }}
                                        </a>
                                    </li>
                                    <div class="dropdown-divider my-1"></div>
                                @endif

                                @if($deal && !empty($deal['id']))
                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item py-1.5 text-success"
                                            data-bs-toggle="offcanvas" data-bs-target="#meetingEntityOffcanvas_{{ $item['id'] }}">
                                            <i class="feather-briefcase me-2 text-success fs-12"></i>{{ __('crm.meetings_view_deal') }}
                                        </a>
                                    </li>
                                @elseif($lead && !empty($lead['id']))
                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item py-1.5 text-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#meetingEntityOffcanvas_{{ $item['id'] }}">
                                            <i class="feather-user me-2 text-primary fs-12"></i>{{ __('crm.meetings_view_lead') }}
                                        </a>
                                    </li>
                                @endif
                            </x-ui.action-dropdown>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.odoo-form-ui>
    </div>

    @if(isset($meetings) && $meetings instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="mt-auto pt-3">
            <x-ui.pagination :paginator="$meetings" />
        </div>
    @endif

    {{-- Clean Modals & Offcanvas Drawers Rendered Outside Table Rows --}}
    @foreach($items as $item)
        @php
            $lead = $item['lead'] ?? null;
            $deal = $item['deal'] ?? null;
            $clientCompany = $deal['customer_name'] ?? ($lead['company_name'] ?? 'Prospect');
            $contactPerson = $lead['contact_person'] ?? ($deal['contact_name'] ?? $clientCompany);
            $clientPhone = $lead['phone'] ?? ($deal['contact_phone'] ?? null);
            $clientEmail = $lead['email'] ?? ($deal['contact_email'] ?? null);
            $dueDate = !empty($item['followup_date']) ? \Illuminate\Support\Carbon::parse($item['followup_date']) : null;
            $status = $item['status'] ?? 'Pending';
            $isPending = ($status === 'Pending');
            $isGeoRequired = !empty($item['is_geo_verification_required']);
        @endphp

        {{-- Offcanvas Drawer: Quick View Lead / Deal Details --}}
        <div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="meetingEntityOffcanvas_{{ $item['id'] }}"
            aria-labelledby="meetingEntityOffcanvasLabel_{{ $item['id'] }}"
            style="width: 490px; max-width: 92vw; z-index: 1060;">
            <div class="offcanvas-header bg-light border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="avatar-text avatar-md {{ $deal ? 'bg-soft-success text-success border border-success-subtle' : 'bg-soft-primary text-primary border border-primary-subtle' }} rounded-circle d-flex align-items-center justify-content-center"
                        style="width: 38px; height: 38px;">
                        <i class="{{ $deal ? 'feather-briefcase' : 'feather-user' }} fs-17"></i>
                    </div>
                    <div>
                        <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0"
                            id="meetingEntityOffcanvasLabel_{{ $item['id'] }}">
                            {{ $deal ? __('crm.meetings_deal_details') : __('crm.meetings_lead_details') }}
                        </h5>
                        <span class="badge bg-white text-muted border font-monospace fs-10 px-1.5 py-0.5 mt-0.5">
                            {{ $deal ? ($deal['deal_number'] ?? ('DL-' . $deal['id'])) : ($lead['lead_number'] ?? ('LD-' . $lead['id'])) }}
                        </span>
                    </div>
                </div>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>

            <div class="offcanvas-body p-4 bg-white" style="overflow-y: auto;">
                {{-- 1. Entity Overview Card --}}
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <div>
                            <h5 class="fw-bold text-dark mb-1 fs-15">{{ $clientCompany }}</h5>
                            @if($contactPerson && $contactPerson !== $clientCompany)
                                <div class="text-muted fs-12 d-flex align-items-center gap-1">
                                    <i class="feather-user fs-11 text-primary"></i>
                                    <span class="fw-semibold text-dark">{{ $contactPerson }}</span>
                                    @if(!empty($lead['designation']))
                                        <span class="text-muted">({{ $lead['designation'] }})</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="text-end">
                            @if(!empty($lead['status']) || !empty($deal['stage']))
                                <span
                                    class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-1 fs-11 fw-semibold">
                                    {{ $deal ? $deal['stage'] : $lead['status'] }}
                                </span>
                            @endif
                            @if(!empty($lead['priority']))
                                <div class="mt-1">
                                    <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 fs-10">
                                        {{ $lead['priority'] }} Priority
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Direct Contact Buttons --}}
                    <div class="d-flex gap-2 mt-3 pt-2 border-top">
                        @if($clientPhone)
                            <a href="tel:{{ $clientPhone }}"
                                class="btn btn-xs btn-outline-success flex-fill d-inline-flex align-items-center justify-content-center gap-1 py-1.5 fw-semibold fs-11">
                                <i class="feather-phone fs-11"></i> Call: {{ $clientPhone }}
                            </a>
                        @endif
                        @if($clientEmail)
                            <a href="mailto:{{ $clientEmail }}"
                                class="btn btn-xs btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 py-1.5 fw-semibold fs-11">
                                <i class="feather-mail fs-11"></i> Send Email
                            </a>
                        @endif
                    </div>
                </div>

                {{-- 2. Basic Information List --}}
                <div class="border rounded-3 p-3 mb-3">
                    <h6 class="fs-11 text-uppercase fw-bold text-muted mb-2.5 d-flex align-items-center gap-1">
                        <i class="feather-file-text text-primary"></i> {{ __('crm.meetings_basic_information') }}
                    </h6>
                    <div class="row g-2 fs-12">
                        @if($deal)
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Estimated Value</span>
                                <span class="fw-bold text-dark fs-13">₹ {{ number_format($deal['estimated_value'] ?? 0, 2) }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Probability</span>
                                <span class="fw-semibold text-dark">{{ $deal['probability'] ?? '—' }}%</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Target Close Date</span>
                                <span class="fw-semibold text-dark">{{ $deal['closing_date'] ?? '—' }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Deal Owner</span>
                                <span class="fw-semibold text-dark">{{ $deal['deal_owner']['name'] ?? '—' }}</span>
                            </div>
                        @else
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Expected Amount</span>
                                <span class="fw-bold text-dark fs-13">₹ {{ number_format($lead['expected_amount'] ?? 0, 2) }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Lead Owner</span>
                                <span class="fw-semibold text-dark">{{ $lead['lead_owner']['name'] ?? '—' }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Segment</span>
                                <span class="fw-semibold text-dark">{{ $lead['segment'] ?: '—' }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Industry</span>
                                <span class="fw-semibold text-dark">{{ $lead['industry_type'] ?: '—' }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Lead Source</span>
                                <span class="fw-semibold text-dark">{{ $lead['source'] ?: '—' }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">Location</span>
                                <span
                                    class="fw-semibold text-dark">{{ implode(', ', array_filter([$lead['city'] ?? null, $lead['state'] ?? null])) ?: '—' }}</span>
                            </div>
                        @endif
                    </div>

                    @if(!empty($lead['address']))
                        <div class="mt-2.5 pt-2 border-top fs-12">
                            <span class="text-muted d-block fs-11">Full Address:</span>
                            <span class="text-dark">{{ $lead['address'] }}</span>
                        </div>
                    @endif

                    @if(!empty($lead['requirement']))
                        <div class="mt-2.5 pt-2 border-top fs-12">
                            <span class="text-muted d-block fs-11">Requirement / Notes:</span>
                            <span class="text-dark">{{ $lead['requirement'] }}</span>
                        </div>
                    @endif
                </div>

                {{-- 3. Associated Meeting Details --}}
                <div class="border rounded-3 p-3 bg-light-subtle">
                    <h6 class="fs-11 text-uppercase fw-bold text-muted mb-2.5 d-flex align-items-center gap-1">
                        <i class="feather-calendar text-primary"></i> {{ __('crm.meetings_current_meeting_info') }}
                    </h6>
                    <div class="d-flex flex-column gap-2 fs-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-11">Meeting Title</span>
                            <span class="fw-semibold text-dark">{{ $item['title'] ?: 'Scheduled Field Meeting' }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-11">Scheduled Time</span>
                            <span
                                class="fw-semibold text-dark">{{ $dueDate ? $dueDate->format('d M, Y - h:i A') : 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-11">Duration</span>
                            <span class="text-dark">{{ $item['duration_minutes'] ?? 30 }} minutes</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-11">Status</span>
                            <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-11">{{ $status }}</span>
                        </div>

                        @if(!empty($item['location_address']) || (!empty($item['latitude']) && !empty($item['longitude'])))
                            <div class="p-2.5 rounded-2 bg-white border border-light-subtle">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="text-muted fs-11 flex-shrink-0">
                                        <i class="feather-map-pin text-primary me-1"></i>Scheduled Venue:
                                    </span>
                                    <a href="https://maps.google.com/?q={{ $item['latitude'] ?? '' }},{{ $item['longitude'] ?? '' }}" target="_blank" rel="noopener noreferrer"
                                        class="text-primary text-decoration-none fw-semibold text-end fs-11 hover-underline">
                                        {{ $item['location_address'] ?: ($item['latitude'] . ', ' . $item['longitude']) }}
                                        <i class="feather-external-link fs-10 ms-0.5"></i>
                                    </a>
                                </div>
                                @if(!empty($item['latitude']))
                                    <div class="text-muted fs-10 mt-0.5 font-monospace">
                                        GPS: {{ $item['latitude'] }}, {{ $item['longitude'] }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if(!empty($item['checkin_latitude']) || !empty($item['checkin_at']))
                            <div class="p-2.5 rounded-2 border {{ str_contains($item['checkin_status'] ?? '', 'Mismatch') ? 'bg-danger-subtle border-danger-subtle' : 'bg-success-subtle border-success-subtle' }}">
                                <div class="d-flex justify-content-between align-items-center mb-1.5">
                                    <span class="fs-11 fw-bold {{ str_contains($item['checkin_status'] ?? '', 'Mismatch') ? 'text-danger' : 'text-success' }}">
                                        <i class="feather-shield me-1"></i>Field Visit Verification
                                    </span>
                                    @if(!empty($item['checkin_status']))
                                        <span class="badge {{ str_contains($item['checkin_status'] ?? '', 'Mismatch') ? 'bg-danger text-white' : 'bg-success text-white' }} fs-10">
                                            {{ $item['checkin_status'] }}
                                        </span>
                                    @endif
                                </div>

                                @if(!empty($item['checkin_address']) || !empty($item['checkin_latitude']))
                                    <div class="d-flex justify-content-between align-items-start gap-2 fs-11">
                                        <span class="text-muted flex-shrink-0">Actual Meeting Location:</span>
                                        <a href="https://maps.google.com/?q={{ $item['checkin_latitude'] ?? '' }},{{ $item['checkin_longitude'] ?? '' }}" target="_blank" rel="noopener noreferrer"
                                            class="text-dark text-decoration-none fw-semibold text-end hover-underline">
                                            {{ $item['checkin_address'] ?: ($item['checkin_latitude'] . ', ' . $item['checkin_longitude']) }}
                                            <i class="feather-external-link fs-10 ms-0.5 text-primary"></i>
                                        </a>
                                    </div>
                                    <div class="text-muted fs-10 font-monospace">
                                        Meeting GPS: {{ $item['checkin_latitude'] }}, {{ $item['checkin_longitude'] }}
                                    </div>
                                @endif

                                @if(!empty($item['checkin_at']))
                                    <div class="text-muted fs-11 mt-1">
                                        <i class="feather-clock me-1"></i>Verified At: <strong>{{ \Carbon\Carbon::parse($item['checkin_at'])->format('d M, Y - h:i A') }}</strong>
                                    </div>
                                @endif

                                @if(!empty($item['checkin_distance_meters']))
                                    <div class="text-muted fs-11 mt-0.5">
                                        <i class="feather-navigation me-1"></i>Distance from Scheduled Venue: <strong>{{ $item['checkin_distance_meters'] }} meters</strong>
                                    </div>
                                @endif

                                @if(!empty($item['selfie_url']))
                                    @php
                                        $offcanvasActualLoc = !empty($item['checkin_address']) 
                                            ? $item['checkin_address'] 
                                            : ((!empty($item['checkin_latitude']) && !empty($item['checkin_longitude'])) 
                                                ? ('GPS: ' . round($item['checkin_latitude'], 5) . ', ' . round($item['checkin_longitude'], 5)) 
                                                : '');
                                        $offcanvasActualGps = (!empty($item['checkin_latitude']) && !empty($item['checkin_longitude'])) 
                                            ? (round($item['checkin_latitude'], 5) . ',' . round($item['checkin_longitude'], 5)) 
                                            : '';
                                        $offcanvasActualTime = !empty($item['checkin_at']) 
                                            ? \Illuminate\Support\Carbon::parse($item['checkin_at'])->format('d M, Y - h:i A') 
                                            : '';
                                        $offcanvasActualStatus = $item['checkin_status'] ?? 'Verified';
                                    @endphp
                                    <div class="mt-2 pt-1 border-top border-white-50 d-flex align-items-center gap-2">
                                        <img src="{{ $item['selfie_url'] }}" alt="Meeting Verification Photo" class="rounded border shadow-sm" style="width: 44px; height: 44px; object-fit: cover; cursor: pointer;"
                                            onclick="crmOpenSelfieModal('{{ $item['selfie_url'] }}', '{{ addslashes($offcanvasActualLoc) }}', '{{ addslashes($offcanvasActualGps) }}', '{{ addslashes($offcanvasActualTime) }}', '{{ addslashes($offcanvasActualStatus) }}')">
                                        <span class="fs-11 text-muted">Click photo to view selfie proof</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if(!empty($item['google_meet_link']))
                            <div class="mt-1">
                                <a href="{{ $item['google_meet_link'] }}" target="_blank"
                                    class="btn btn-xs btn-success d-inline-flex align-items-center gap-1">
                                    <i class="feather-video fs-11"></i> Join Google Meet
                                </a>
                            </div>
                        @endif

                        @if(!empty($item['notes']))
                            @php
                                $rawNotes = trim($item['notes']);
                                $cleanNotes = preg_replace('/\[Geo-Fence[^\]]*\]/i', '', $rawNotes);
                                $cleanNotes = preg_replace('/\[\d{4}-\d{2}-\d{2}[^\]]*Status:[^\]]*\]/i', '', $cleanNotes);
                                $cleanNotes = trim(preg_replace('/\s+/', ' ', $cleanNotes));

                                $geoFenceTag = null;
                                if (preg_match('/\[Geo-Fence[^\]]*\]/i', $rawNotes, $geoMatches)) {
                                    $geoFenceTag = trim($geoMatches[0], '[]');
                                }
                                $displayNotes = !empty($cleanNotes) ? $cleanNotes : $rawNotes;
                            @endphp
                            <div class="mt-2 pt-2 border-top">
                                <span class="text-muted d-block fs-11 fw-semibold mb-1">Meeting Notes & Remarks:</span>
                                <div class="p-2.5 bg-light-subtle rounded border fs-12 text-dark" style="white-space: pre-wrap; word-break: break-word;">
                                    {{ $displayNotes }}
                                </div>
                                @if(!empty($geoFenceTag))
                                    <div class="mt-1.5 p-1.5 px-2 rounded fs-11 {{ str_contains($geoFenceTag, 'Mismatch') ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }} d-flex align-items-center gap-1">
                                        <i class="feather-shield fs-11 flex-shrink-0"></i>
                                        <span><strong>{{ __('crm.meetings_geo_verification') }}</strong> {{ $geoFenceTag }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="offcanvas-footer border-top bg-light p-3 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="offcanvas">
                    {{ __('crm.close') ?? 'Close' }}
                </button>
                @if($isPending)
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-info text-white" data-bs-dismiss="offcanvas"
                            data-bs-toggle="modal" data-bs-target="#meetingRescheduleModal_{{ $item['id'] }}">
                            <i class="feather-calendar me-1"></i> {{ __('crm.meetings_reschedule') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-success" data-bs-dismiss="offcanvas" data-bs-toggle="modal"
                            data-bs-target="#meetingModal_{{ $item['id'] }}_Connected"
                            onclick="crmInitVisitModal('{{ $item['id'] }}', 'Connected')">
                            <i class="feather-check me-1"></i> {{ __('crm.meetings_mark_connected') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>

        @if($isPending)
            {{-- 1. Connected Modal --}}
            <div class="modal fade" id="meetingModal_{{ $item['id'] }}_Connected" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <form action="{{ route('crm.meetings.status', $item['id']) }}" method="POST" enctype="multipart/form-data"
                            onsubmit="return crmValidateMeetingSubmit('{{ $item['id'] }}', 'Connected', {{ $isGeoRequired ? 'true' : 'false' }})">
                            @csrf
                            <input type="hidden" name="status" value="Connected">
                            <input type="hidden" name="outcome" value="Connected">

                            <div class="modal-header py-2.5 px-3 border-bottom bg-soft-success">
                                <h6 class="modal-title fw-bold text-success fs-14">
                                    <i class="feather-check-circle me-1.5"></i>{{ __('crm.meetings_modal_connected_title') }}
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                                    onclick="crmStopCamera('{{ $item['id'] }}', 'Connected')"></button>
                            </div>

                            <div class="modal-body p-3">
                                <p class="text-muted fs-12 mb-3">
                                    {!! __('crm.meetings_modal_connected_desc', ['client' => '<strong>' . e($clientCompany) . '</strong>']) !!}
                                </p>

                                @if($isGeoRequired)
                                    <div class="border rounded-3 p-3 bg-light mb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                            <span class="fs-12 fw-bold text-dark d-flex align-items-center gap-1">
                                                <i class="feather-shield text-primary fs-13"></i> Field Visit Verification
                                            </span>
                                            <span
                                                class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10 font-monospace">Required</span>
                                        </div>

                                        <!-- Scheduled Venue Reference Box -->
                                        @if(!empty($item['location_address']) || !empty($item['latitude']))
                                            <div class="p-2 mb-2 bg-white rounded border border-light-subtle fs-11">
                                                <div class="text-muted fs-10 text-uppercase fw-semibold mb-0.5">
                                                    <i class="feather-map-pin text-primary me-1"></i>{{ __('crm.meetings_scheduled_venue') }}
                                                </div>
                                                <div class="fw-semibold text-dark text-truncate" title="{{ $item['location_address'] ?: ($item['latitude'] . ', ' . $item['longitude']) }}">
                                                    {{ $item['location_address'] ?: ($item['latitude'] . ', ' . $item['longitude']) }}
                                                </div>
                                            </div>
                                        @endif

                                        <input type="hidden" name="checkin_latitude" id="lat_{{ $item['id'] }}_Connected" value="">
                                        <input type="hidden" name="checkin_longitude" id="lng_{{ $item['id'] }}_Connected" value="">
                                        <input type="hidden" name="checkin_address" id="addr_{{ $item['id'] }}_Connected" value="">

                                        <!-- GPS Location Box -->
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label fs-11 fw-semibold text-secondary mb-0">{{ __('crm.meetings_live_location_gps') }} <span
                                                        class="text-danger">*</span></label>
                                                <span id="loc_badge_{{ $item['id'] }}_Connected"
                                                    class="badge bg-secondary-subtle text-muted fs-10">
                                                    Required
                                                </span>
                                            </div>
                                            <div id="loc_box_{{ $item['id'] }}_Connected"
                                                class="d-flex align-items-center justify-content-between bg-white border rounded-2 p-2 fs-12">
                                                <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                                    <span id="loc_status_dot_{{ $item['id'] }}_Connected"
                                                        class="rounded-circle d-inline-block bg-warning"
                                                        style="width: 8px; height: 8px; flex-shrink: 0;"></span>
                                                    <span id="loc_text_{{ $item['id'] }}_Connected"
                                                        class="text-muted text-truncate fs-12">
                                                        {{ __('crm.meetings_click_fetch_gps') }}
                                                    </span>
                                                </div>
                                                <button type="button" class="btn btn-xs btn-outline-primary flex-shrink-0"
                                                    onclick="crmCaptureLocation('{{ $item['id'] }}', 'Connected')">
                                                    <i class="feather-crosshair me-1"></i>{{ __('crm.meetings_fetch_gps_btn') }}
                                                </button>
                                            </div>
                                            <div id="loc_error_{{ $item['id'] }}_Connected" class="text-danger fs-11 mt-1 d-none">
                                                Live location verification is required.
                                            </div>
                                        </div>

                                        <!-- Live Selfie Box -->
                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label fs-11 fw-semibold text-secondary mb-0">{{ __('crm.meetings_live_selfie_label') }}
                                                    <span class="text-danger">*</span></label>
                                                <span id="selfie_badge_{{ $item['id'] }}_Connected"
                                                    class="badge {{ !empty($item['selfie_url']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-muted' }} fs-10">
                                                    {{ !empty($item['selfie_url']) ? 'Captured' : 'Required' }}
                                                </span>
                                            </div>
                                            <input type="hidden" name="selfie_base64" id="selfie_data_{{ $item['id'] }}_Connected"
                                                value="">

                                            <div id="camera_idle_{{ $item['id'] }}_Connected"
                                                class="border rounded-2 p-3 text-center bg-white">
                                                <x-ui.button type="button" variant="primary" size="sm" icon="feather-camera"
                                                    onclick="crmStartCamera('{{ $item['id'] }}', 'Connected')">
                                                    {{ __('crm.meetings_open_camera_btn') }}
                                                </x-ui.button>
                                            </div>

                                            <div id="camera_active_{{ $item['id'] }}_Connected"
                                                class="d-none border rounded-2 p-2 bg-dark text-center">
                                                <video id="video_{{ $item['id'] }}_Connected" autoplay playsinline class="w-100 rounded"
                                                    style="max-height: 220px; object-fit: cover; transform: scaleX(-1);"></video>
                                                <canvas id="canvas_{{ $item['id'] }}_Connected" class="d-none"></canvas>
                                                <div class="mt-2 d-flex justify-content-center gap-2">
                                                    <x-ui.button type="button" variant="success" size="sm" icon="feather-camera"
                                                        onclick="crmTakeSnapshot('{{ $item['id'] }}', 'Connected')">
                                                        {{ __('crm.meetings_capture_photo_btn') }}
                                                    </x-ui.button>
                                                    <x-ui.button type="button" variant="light" size="sm"
                                                        onclick="crmStopCamera('{{ $item['id'] }}', 'Connected')">
                                                        {{ __('crm.cancel') ?: 'Cancel' }}
                                                    </x-ui.button>
                                                </div>
                                            </div>

                                            <div id="camera_preview_{{ $item['id'] }}_Connected"
                                                class="d-none border rounded-2 p-2 bg-white text-center">
                                                <img id="preview_img_{{ $item['id'] }}_Connected" src="" alt="Captured Selfie"
                                                    class="rounded border shadow-sm"
                                                    style="max-height: 180px; width: auto; object-fit: cover;">
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary"
                                                        onclick="crmRetakePhoto('{{ $item['id'] }}', 'Connected')">
                                                        <i class="feather-refresh-cw me-1"></i>{{ __('crm.meetings_retake_photo_btn') }}
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="selfie_error_{{ $item['id'] }}_Connected" class="text-danger fs-11 mt-1 d-none">
                                                Selfie is required before completing this meeting.
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">{{ __('crm.meetings_discussion_summary') }}</label>
                                    <textarea name="notes" class="form-control fs-12" rows="3"
                                        placeholder="{{ __('crm.meetings_discussion_placeholder') }}">{{ $item['notes'] ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer py-2 px-3 border-top bg-light">
                                <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal"
                                    onclick="crmStopCamera('{{ $item['id'] }}', 'Connected')">
                                    {{ __('crm.cancel') ?: 'Cancel' }}
                                </x-ui.button>
                                <x-ui.button type="submit" variant="success" size="sm" icon="feather-check">
                                    {{ __('crm.meetings_mark_connected') }}
                                </x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- 2. Not Connected Modal --}}
            <div class="modal fade" id="meetingModal_{{ $item['id'] }}_NotConnected" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <form action="{{ route('crm.meetings.status', $item['id']) }}" method="POST" enctype="multipart/form-data"
                            onsubmit="return crmValidateMeetingSubmit('{{ $item['id'] }}', 'NotConnected', {{ $isGeoRequired ? 'true' : 'false' }})">
                            @csrf
                            <input type="hidden" name="status" value="Not Connected">
                            <input type="hidden" name="outcome" value="Not Connected">

                            <div class="modal-header py-2.5 px-3 border-bottom bg-soft-warning">
                                <h6 class="modal-title fw-bold text-dark fs-14">
                                    <i class="feather-phone-off text-warning me-1.5"></i>{{ __('crm.meetings_modal_not_connected_title') }}
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                                    onclick="crmStopCamera('{{ $item['id'] }}', 'NotConnected')"></button>
                            </div>

                            <div class="modal-body p-3">
                                <p class="text-muted fs-12 mb-3">
                                    {{ __('crm.meetings_modal_not_connected_desc') }}
                                </p>

                                @if($isGeoRequired)
                                    <div class="border rounded-3 p-3 bg-light mb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                            <span class="fs-12 fw-bold text-dark d-flex align-items-center gap-1">
                                                <i class="feather-shield text-primary fs-13"></i> Field Visit Verification
                                            </span>
                                            <span
                                                class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10 font-monospace">Required</span>
                                        </div>

                                        <!-- Scheduled Venue Reference Box -->
                                        @if(!empty($item['location_address']) || !empty($item['latitude']))
                                            <div class="p-2 mb-2 bg-white rounded border border-light-subtle fs-11">
                                                <div class="text-muted fs-10 text-uppercase fw-semibold mb-0.5">
                                                    <i class="feather-map-pin text-primary me-1"></i>{{ __('crm.meetings_scheduled_venue') }}
                                                </div>
                                                <div class="fw-semibold text-dark text-truncate" title="{{ $item['location_address'] ?: ($item['latitude'] . ', ' . $item['longitude']) }}">
                                                    {{ $item['location_address'] ?: ($item['latitude'] . ', ' . $item['longitude']) }}
                                                </div>
                                            </div>
                                        @endif

                                        <input type="hidden" name="checkin_latitude" id="lat_{{ $item['id'] }}_NotConnected" value="">
                                        <input type="hidden" name="checkin_longitude" id="lng_{{ $item['id'] }}_NotConnected" value="">
                                        <input type="hidden" name="checkin_address" id="addr_{{ $item['id'] }}_NotConnected" value="">

                                        <div class="mb-3">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label fs-11 fw-semibold text-secondary mb-0">{{ __('crm.meetings_live_location_gps') }} <span
                                                        class="text-danger">*</span></label>
                                                <span id="loc_badge_{{ $item['id'] }}_NotConnected"
                                                    class="badge bg-secondary-subtle text-muted fs-10">
                                                    Required
                                                </span>
                                            </div>
                                            <div id="loc_box_{{ $item['id'] }}_NotConnected"
                                                class="d-flex align-items-center justify-content-between bg-white border rounded-2 p-2 fs-12">
                                                <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                                    <span id="loc_status_dot_{{ $item['id'] }}_NotConnected"
                                                        class="rounded-circle d-inline-block bg-warning"
                                                        style="width: 8px; height: 8px; flex-shrink: 0;"></span>
                                                    <span id="loc_text_{{ $item['id'] }}_NotConnected"
                                                        class="text-muted text-truncate fs-12">
                                                        {{ __('crm.meetings_click_fetch_gps') }}
                                                    </span>
                                                </div>
                                                <button type="button" class="btn btn-xs btn-outline-primary flex-shrink-0"
                                                    onclick="crmCaptureLocation('{{ $item['id'] }}', 'NotConnected')">
                                                    <i class="feather-crosshair me-1"></i>{{ __('crm.meetings_fetch_gps_btn') }}
                                                </button>
                                            </div>
                                            <div id="loc_error_{{ $item['id'] }}_NotConnected" class="text-danger fs-11 mt-1 d-none">
                                                Live location verification is required.
                                            </div>
                                        </div>

                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label fs-11 fw-semibold text-secondary mb-0">{{ __('crm.meetings_live_selfie_label') }}
                                                    <span class="text-danger">*</span></label>
                                                <span id="selfie_badge_{{ $item['id'] }}_NotConnected"
                                                    class="badge {{ !empty($item['selfie_url']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-muted' }} fs-10">
                                                    {{ !empty($item['selfie_url']) ? 'Captured' : 'Required' }}
                                                </span>
                                            </div>
                                            <input type="hidden" name="selfie_base64" id="selfie_data_{{ $item['id'] }}_NotConnected"
                                                value="">

                                            <div id="camera_idle_{{ $item['id'] }}_NotConnected"
                                                class="border rounded-2 p-3 text-center bg-white">
                                                <x-ui.button type="button" variant="primary" size="sm" icon="feather-camera"
                                                    onclick="crmStartCamera('{{ $item['id'] }}', 'NotConnected')">
                                                    {{ __('crm.meetings_open_camera_btn') }}
                                                </x-ui.button>
                                            </div>

                                            <div id="camera_active_{{ $item['id'] }}_NotConnected"
                                                class="d-none border rounded-2 p-2 bg-dark text-center">
                                                <video id="video_{{ $item['id'] }}_NotConnected" autoplay playsinline
                                                    class="w-100 rounded"
                                                    style="max-height: 220px; object-fit: cover; transform: scaleX(-1);"></video>
                                                <canvas id="canvas_{{ $item['id'] }}_NotConnected" class="d-none"></canvas>
                                                <div class="mt-2 d-flex justify-content-center gap-2">
                                                    <x-ui.button type="button" variant="warning" size="sm" icon="feather-camera"
                                                        onclick="crmTakeSnapshot('{{ $item['id'] }}', 'NotConnected')">
                                                        {{ __('crm.meetings_capture_photo_btn') }}
                                                    </x-ui.button>
                                                    <x-ui.button type="button" variant="light" size="sm"
                                                        onclick="crmStopCamera('{{ $item['id'] }}', 'NotConnected')">
                                                        {{ __('crm.cancel') ?: 'Cancel' }}
                                                    </x-ui.button>
                                                </div>
                                            </div>

                                            <div id="camera_preview_{{ $item['id'] }}_NotConnected"
                                                class="d-none border rounded-2 p-2 bg-white text-center">
                                                <img id="preview_img_{{ $item['id'] }}_NotConnected" src="" alt="Captured Selfie"
                                                    class="rounded border shadow-sm"
                                                    style="max-height: 180px; width: auto; object-fit: cover;">
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary"
                                                        onclick="crmRetakePhoto('{{ $item['id'] }}', 'NotConnected')">
                                                        <i class="feather-refresh-cw me-1"></i>{{ __('crm.meetings_retake_photo_btn') }}
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="selfie_error_{{ $item['id'] }}_NotConnected" class="text-danger fs-11 mt-1 d-none">
                                                Selfie is required before completing this meeting.
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">{{ __('crm.meetings_notes_and_remarks') }}</label>
                                    <textarea name="notes" class="form-control fs-12" rows="3"
                                        placeholder="{{ __('crm.meetings_not_connected_placeholder') }}">{{ $item['notes'] ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer py-2 px-3 border-top bg-light">
                                <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal"
                                    onclick="crmStopCamera('{{ $item['id'] }}', 'NotConnected')">
                                    {{ __('crm.cancel') ?: 'Cancel' }}
                                </x-ui.button>
                                <x-ui.button type="submit" variant="warning" size="sm" icon="feather-check">
                                    {{ __('crm.meetings_mark_not_connected') }}
                                </x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- 3. Reschedule Modal --}}
            <div class="modal fade" id="meetingRescheduleModal_{{ $item['id'] }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <form action="{{ route('crm.meetings.reschedule', $item['id']) }}" method="POST">
                            @csrf
                            <div class="modal-header py-2.5 px-3 border-bottom bg-soft-info">
                                <h6 class="modal-title fw-bold text-info fs-14">
                                    <i class="feather-repeat me-1.5"></i>{{ __('crm.meetings_reschedule_modal_title') }}
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body p-3">
                                <p class="text-muted fs-12 mb-3">
                                    {{ __('crm.meetings_current_scheduled_time') }}
                                    <strong>{{ $dueDate ? $dueDate->format('d M Y, h:i A') : 'N/A' }}</strong>
                                </p>

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">{{ __('crm.meetings_new_date_time') }} <span
                                            class="text-danger">*</span></label>
                                    <input type="datetime-local" name="followup_date" class="form-control fs-12" required
                                        value="{{ date('Y-m-d\TH:i', strtotime('+1 day 10:00')) }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">{{ __('crm.meetings_reschedule_reason') }}</label>
                                    <textarea name="notes" class="form-control fs-12" rows="2"
                                        placeholder="{{ __('crm.meetings_reschedule_placeholder') }}">{{ $item['notes'] ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer py-2 px-3 border-top bg-light">
                                <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal">
                                    {{ __('crm.cancel') ?: 'Cancel' }}
                                </x-ui.button>
                                <x-ui.button type="submit" variant="info" size="sm" icon="feather-check">
                                    {{ __('crm.meetings_confirm_reschedule_btn') }}
                                </x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- 4. Cancel Modal --}}
            <div class="modal fade" id="meetingCancelModal_{{ $item['id'] }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <form action="{{ route('crm.meetings.status', $item['id']) }}" method="POST">
                            @csrf
                            <input type="hidden" name="status" value="Cancelled">
                            <input type="hidden" name="outcome" value="Cancelled">

                            <div class="modal-header py-2.5 px-3 border-bottom bg-soft-danger">
                                <h6 class="modal-title fw-bold text-danger fs-14">
                                    <i class="feather-x-circle me-1.5"></i>{{ __('crm.meetings_cancel_modal_title') }}
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body p-3">
                                <p class="text-muted fs-12 mb-3">
                                    {!! __('crm.meetings_cancel_confirm_question', ['client' => '<strong>' . e($clientCompany) . '</strong>']) !!}
                                </p>

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">{{ __('crm.meetings_cancellation_reason') }}</label>
                                    <textarea name="notes" class="form-control fs-12" rows="2"
                                        placeholder="{{ __('crm.meetings_cancellation_placeholder') }}">{{ $item['notes'] ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer py-2 px-3 border-top bg-light">
                                <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal">
                                    {{ __('crm.close') }}
                                </x-ui.button>
                                <x-ui.button type="submit" variant="danger" size="sm" icon="feather-trash-2">
                                    {{ __('crm.meetings_confirm_cancel_btn') }}
                                </x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- 5. Assign / Delegate Modal --}}
            <div class="modal fade" id="meetingAssignModal_{{ $item['id'] }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <form action="{{ route('crm.meetings.assign', $item['id']) }}" method="POST">
                            @csrf
                            <div class="modal-header py-2.5 px-3 border-bottom bg-soft-primary">
                                <h6 class="modal-title fw-bold text-primary fs-14">
                                    <i class="feather-user-plus me-1.5"></i>{{ __('crm.meetings_assign_modal_title') }}
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body p-3">
                                <p class="text-muted fs-12 mb-3">
                                    {!! __('crm.meetings_assign_desc', [
                                        'client' => '<strong>' . e($clientCompany) . '</strong>',
                                        'time' => $dueDate ? $dueDate->format('d M Y, h:i A') : ''
                                    ]) !!}
                                </p>

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">{{ __('crm.meetings_assign_to_sales_person') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="tagged_user_id" class="form-select fs-12" required>
                                        <option value="">{{ __('crm.meetings_choose_sales_person') }}</option>
                                        @php
                                            $selectedAssigneeId = $item['tagged_user']['id'] ?? ($item['assigned_to']['id'] ?? null);
                                            if (!$selectedAssigneeId && !empty($item['tagged_users'])) {
                                                $tUsers = $item['tagged_users'];
                                                $firstUser = is_array($tUsers) ? ($tUsers[0] ?? null) : (is_object($tUsers) && method_exists($tUsers, 'first') ? $tUsers->first() : null);
                                                $selectedAssigneeId = $firstUser['id'] ?? null;
                                            }
                                        @endphp
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" @selected($selectedAssigneeId == $u->id)>
                                                {{ $u->name }} ({{ $u->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">{{ __('crm.meetings_handover_notes') }}</label>
                                    <textarea name="notes" class="form-control fs-12" rows="2"
                                        placeholder="{{ __('crm.meetings_handover_placeholder') }}"></textarea>
                                </div>
                            </div>

                            <div class="modal-footer py-2 px-3 border-top bg-light">
                                <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal">
                                    {{ __('crm.cancel') }}
                                </x-ui.button>
                                <x-ui.button type="submit" variant="primary" size="sm" icon="feather-check">
                                    {{ __('crm.meetings_confirm_assignment_btn') }}
                                </x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endif
<div class="empty-search-state text-center py-5" style="display: none;">
    <div class="avatar-text avatar-lg bg-soft-warning text-warning rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
        style="width: 48px; height: 48px;">
        <i class="feather-search fs-20"></i>
    </div>
    <h6 class="fw-bold text-dark mb-1">{{ __('crm.meetings_no_matching_found') }}</h6>
    <p class="text-muted fs-12 mb-0">{{ __('crm.meetings_try_different_term') }}</p>
</div>