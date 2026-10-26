@php
    $items = isset($meetings) ? $meetings : ($items ?? []);
@endphp

@if(empty($items) || count($items) === 0)
    <div class="text-center py-5">
        <div class="avatar-text avatar-lg bg-soft-secondary text-muted rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
            <i class="feather-calendar fs-22"></i>
        </div>
        <h6 class="fw-bold text-dark mb-1">{{ $emptyMessage ?? 'No meetings found in this view.' }}</h6>
        <p class="text-muted fs-12 mb-0">Scheduled meetings for leads and deals assigned to you will appear here.</p>
    </div>
@else
    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table" class="mb-0">
            <thead>
                <tr>
                    <th style="width: 14%;">TIME</th>
                    <th style="width: 20%;">LEAD / COMPANY</th>
                    <th style="width: 18%;">PHONE / EMAIL</th>
                    <th style="width: 22%;">MEETING DETAILS</th>
                    <th style="width: 12%;">STATUS</th>
                    <th style="width: 14%;" class="text-end pe-4">ACTIONS</th>
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
                    @endphp

                    <tr class="meeting-table-row {{ !empty($isOverdue) ? 'table-danger-subtle' : '' }}">
                        {{-- 1. TIME (Lead Index Calendar Avatar Style) --}}
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-text avatar-xs bg-soft-primary text-primary me-2 rounded-circle shadow-2xs d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 11px;">
                                    <i class="feather-calendar"></i>
                                </div>
                                <div>
                                    <span class="d-block fw-semibold text-dark fs-12 leading-tight">
                                        {{ $dueDate ? $dueDate->format('d/m/Y') : 'N/A' }}
                                    </span>
                                    <span class="text-muted fs-10">
                                        {{ $dueDate ? $dueDate->format('h:i A') : '' }}
                                    </span>
                                    @if(!empty($isOverdue))
                                        <div class="mt-0.5">
                                            <span class="badge bg-danger text-white fs-9 px-1.5 py-0.5 rounded-pill">Overdue</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- 2. LEAD / COMPANY (Matching Lead Index Lead Row) --}}
                        <td>
                            <div class="d-flex align-items-center flex-wrap gap-1">
                                <a href="javascript:void(0)" data-bs-toggle="offcanvas" data-bs-target="#meetingEntityOffcanvas_{{ $item['id'] }}" class="fw-bold text-dark text-decoration-none" title="Quick View Info">
                                    {{ $clientCompany }}
                                </a>
                                @if($deal && !empty($deal['id']))
                                    <a href="javascript:void(0)" data-bs-toggle="offcanvas" data-bs-target="#meetingEntityOffcanvas_{{ $item['id'] }}" class="badge bg-light text-success border font-monospace px-1.5 py-0.5 fs-11 text-decoration-none" title="Quick View Deal">
                                        {{ $deal['deal_number'] ?: ('DL-' . $deal['id']) }}
                                    </a>
                                @elseif($lead && !empty($lead['id']))
                                    <a href="javascript:void(0)" data-bs-toggle="offcanvas" data-bs-target="#meetingEntityOffcanvas_{{ $item['id'] }}" class="badge bg-light text-primary border font-monospace px-1.5 py-0.5 fs-11 text-decoration-none" title="Quick View Lead">
                                        {{ $lead['lead_number'] ?: ('LD-' . $lead['id']) }}
                                    </a>
                                @endif
                            </div>
                            <span class="text-muted fs-11 d-block mt-0.5">
                                <i class="feather-user me-1 fs-10 text-primary"></i>{{ $contactPerson ?: 'N/A' }}
                            </span>
                        </td>

                        {{-- 3. PHONE / EMAIL (Lead Index Phone & Email Column Style) --}}
                        <td>
                            @if($clientPhone)
                                <span class="text-muted fs-11 d-block">
                                    <a href="tel:{{ $clientPhone }}" class="text-muted text-decoration-none hover-primary">
                                        <i class="feather-phone fs-11 me-1 text-muted"></i>{{ $clientPhone }}
                                    </a>
                                </span>
                            @else
                                <span class="text-muted fs-11 d-block"><i class="feather-phone fs-11 me-1 text-muted"></i>—</span>
                            @endif

                            @if($clientEmail)
                                <span class="text-muted fs-11 d-block text-truncate" style="max-width: 180px;">
                                    <i class="feather-mail fs-11 me-1 text-muted"></i>{{ $clientEmail }}
                                </span>
                            @endif
                        </td>

                        {{-- 4. MEETING DETAILS --}}
                        <td>
                            <div class="d-flex flex-column gap-0.5">
                                <span class="fw-semibold text-dark fs-12 leading-tight text-truncate" style="max-width: 220px;" title="{{ $item['title'] ?: 'Scheduled Meeting' }}">
                                    {{ $item['title'] ?: 'Scheduled Field Meeting' }}
                                </span>
                                <div class="d-flex align-items-center gap-1.5 flex-wrap mt-0.5">
                                    <span class="text-muted fs-11"><i class="feather-clock me-1 fs-10"></i>{{ $item['duration_minutes'] ?? 30 }} mins</span>
                                    @if(!empty($item['google_meet_link']))
                                        <a href="{{ $item['google_meet_link'] }}" target="_blank" rel="noopener noreferrer" class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-1.5 fs-10 text-decoration-none d-inline-flex align-items-center gap-0.5">
                                            <i class="feather-video fs-9"></i> Meet
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- 5. STATUS (Lead Index Dot Badge Standard) --}}
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <div>
                                    @if($isCompleted)
                                        <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1">
                                            <span class="rounded-circle bg-success d-inline-block" style="width: 6px; height: 6px;"></span> Connected
                                        </span>
                                    @elseif($isNotConnected)
                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1">
                                            <span class="rounded-circle bg-warning d-inline-block" style="width: 6px; height: 6px;"></span> Not Connected
                                        </span>
                                    @elseif($isCancelled)
                                        <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1">
                                            <span class="rounded-circle bg-danger d-inline-block" style="width: 6px; height: 6px;"></span> Cancelled
                                        </span>
                                    @elseif($isRescheduled)
                                        <span class="badge bg-soft-info text-info border border-info-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1">
                                            <span class="rounded-circle bg-info d-inline-block" style="width: 6px; height: 6px;"></span> Rescheduled
                                        </span>
                                    @else
                                        <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1">
                                            <span class="rounded-circle bg-primary d-inline-block" style="width: 6px; height: 6px;"></span> Pending
                                        </span>
                                    @endif
                                </div>

                                @if(!empty($item['is_geo_verified']))
                                    <div>
                                        <span class="badge bg-soft-success text-success border border-success-subtle fs-10 fw-bold cursor-pointer" onclick="crmOpenSelfieModal('{{ $item['selfie_url'] }}', '{{ addslashes($item['location_address'] ?? '') }}')" title="{{ $item['location_address'] ?? 'Verified' }}">
                                            <i class="feather-shield fs-9"></i> Verified
                                        </span>
                                    </div>
                                @elseif($isPending && $isGeoRequired)
                                    <div>
                                        <span class="badge bg-light text-muted border fs-10 d-inline-flex align-items-center gap-0.5" title="Field Geo-Verification Required">
                                            <i class="feather-map-pin text-info fs-9"></i> Geo-Req
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
                                        <button type="button" 
                                                class="action-dropdown-btn text-success" 
                                                title="Mark as Connected"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#meetingModal_{{ $item['id'] }}_Connected" 
                                                onclick="crmInitVisitModal('{{ $item['id'] }}', 'Connected')">
                                            <i class="feather-check text-success"></i>
                                        </button>

                                        {{-- Not Connected Icon Button --}}
                                        <button type="button" 
                                                class="action-dropdown-btn text-warning" 
                                                title="Mark as Not Connected"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#meetingModal_{{ $item['id'] }}_NotConnected" 
                                                onclick="crmInitVisitModal('{{ $item['id'] }}', 'NotConnected')">
                                            <i class="feather-phone-off text-warning"></i>
                                        </button>

                                        {{-- Reschedule Icon Button --}}
                                        <button type="button" 
                                                class="action-dropdown-btn text-info" 
                                                title="Reschedule Meeting"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#meetingRescheduleModal_{{ $item['id'] }}">
                                            <i class="feather-calendar text-info"></i>
                                        </button>
                                    @else
                                        @if(!empty($item['selfie_url']))
                                            <button type="button" 
                                                    class="action-dropdown-btn text-success" 
                                                    title="View Verification Selfie"
                                                    onclick="crmOpenSelfieModal('{{ $item['selfie_url'] }}', '{{ addslashes($item['location_address'] ?? '') }}')">
                                                <i class="feather-image text-success"></i>
                                            </button>
                                        @endif
                                    @endif
                                </x-slot:extraActions>

                                {{-- More Actions Dropdown Slot --}}
                                @if($isPending)
                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item py-1.5 text-danger" data-bs-toggle="modal" data-bs-target="#meetingCancelModal_{{ $item['id'] }}">
                                            <i class="feather-x-circle me-2 text-danger fs-12"></i>Cancel Meeting
                                        </a>
                                    </li>
                                    <div class="dropdown-divider my-1"></div>
                                @endif

                                @if($deal && !empty($deal['id']))
                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item py-1.5 text-success" data-bs-toggle="offcanvas" data-bs-target="#meetingEntityOffcanvas_{{ $item['id'] }}">
                                            <i class="feather-briefcase me-2 text-success fs-12"></i>View Deal
                                        </a>
                                    </li>
                                @elseif($lead && !empty($lead['id']))
                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item py-1.5 text-primary" data-bs-toggle="offcanvas" data-bs-target="#meetingEntityOffcanvas_{{ $item['id'] }}">
                                            <i class="feather-user me-2 text-primary fs-12"></i>View Lead
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
        <div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="meetingEntityOffcanvas_{{ $item['id'] }}" aria-labelledby="meetingEntityOffcanvasLabel_{{ $item['id'] }}" style="width: 490px; max-width: 92vw; z-index: 1060;">
            <div class="offcanvas-header bg-light border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="avatar-text avatar-md {{ $deal ? 'bg-soft-success text-success border border-success-subtle' : 'bg-soft-primary text-primary border border-primary-subtle' }} rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="{{ $deal ? 'feather-briefcase' : 'feather-user' }} fs-17"></i>
                    </div>
                    <div>
                        <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="meetingEntityOffcanvasLabel_{{ $item['id'] }}">
                            {{ $deal ? 'Deal Details' : 'Lead Details' }}
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
                                <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-1 fs-11 fw-semibold">
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
                            <a href="tel:{{ $clientPhone }}" class="btn btn-xs btn-outline-success flex-fill d-inline-flex align-items-center justify-content-center gap-1 py-1.5 fw-semibold fs-11">
                                <i class="feather-phone fs-11"></i> Call: {{ $clientPhone }}
                            </a>
                        @endif
                        @if($clientEmail)
                            <a href="mailto:{{ $clientEmail }}" class="btn btn-xs btn-outline-primary flex-fill d-inline-flex align-items-center justify-content-center gap-1 py-1.5 fw-semibold fs-11">
                                <i class="feather-mail fs-11"></i> Send Email
                            </a>
                        @endif
                    </div>
                </div>

                {{-- 2. Basic Information List --}}
                <div class="border rounded-3 p-3 mb-3">
                    <h6 class="fs-11 text-uppercase fw-bold text-muted mb-2.5 d-flex align-items-center gap-1">
                        <i class="feather-file-text text-primary"></i> Basic Information
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
                                <span class="fw-semibold text-dark">{{ implode(', ', array_filter([$lead['city'] ?? null, $lead['state'] ?? null])) ?: '—' }}</span>
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
                        <i class="feather-calendar text-primary"></i> Current Meeting Info
                    </h6>
                    <div class="d-flex flex-column gap-2 fs-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-11">Meeting Title</span>
                            <span class="fw-semibold text-dark">{{ $item['title'] ?: 'Scheduled Field Meeting' }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-11">Scheduled Time</span>
                            <span class="fw-semibold text-dark">{{ $dueDate ? $dueDate->format('d M, Y - h:i A') : 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-11">Duration</span>
                            <span class="text-dark">{{ $item['duration_minutes'] ?? 30 }} minutes</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fs-11">Status</span>
                            <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-11">{{ $status }}</span>
                        </div>

                        @if(!empty($item['google_meet_link']))
                            <div class="mt-1">
                                <a href="{{ $item['google_meet_link'] }}" target="_blank" class="btn btn-xs btn-success d-inline-flex align-items-center gap-1">
                                    <i class="feather-video fs-11"></i> Join Google Meet
                                </a>
                            </div>
                        @endif

                        @if(!empty($item['notes']))
                            <div class="mt-1.5 pt-1.5 border-top">
                                <span class="text-muted d-block fs-11 mb-0.5">Meeting Agenda / Notes:</span>
                                <div class="p-2 bg-white rounded border fs-11 text-dark">
                                    {{ $item['notes'] }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="offcanvas-footer border-top bg-light p-3 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="offcanvas">
                    Close
                </button>
                @if($isPending)
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-info text-white" data-bs-dismiss="offcanvas" data-bs-toggle="modal" data-bs-target="#meetingRescheduleModal_{{ $item['id'] }}">
                            <i class="feather-calendar me-1"></i> Reschedule
                        </button>
                        <button type="button" class="btn btn-sm btn-success" data-bs-dismiss="offcanvas" data-bs-toggle="modal" data-bs-target="#meetingModal_{{ $item['id'] }}_Connected" onclick="crmInitVisitModal('{{ $item['id'] }}', 'Connected')">
                            <i class="feather-check me-1"></i> Mark Connected
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
                        <form action="{{ route('crm.meetings.status', $item['id']) }}" method="POST" enctype="multipart/form-data" onsubmit="return crmValidateMeetingSubmit('{{ $item['id'] }}', 'Connected', {{ $isGeoRequired ? 'true' : 'false' }})">
                            @csrf
                            <input type="hidden" name="status" value="Connected">
                            <input type="hidden" name="outcome" value="Connected">

                            <div class="modal-header py-2.5 px-3 border-bottom bg-soft-success">
                                <h6 class="modal-title fw-bold text-success fs-14">
                                    <i class="feather-check-circle me-1.5"></i>Mark Meeting as Connected
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="crmStopCamera('{{ $item['id'] }}', 'Connected')"></button>
                            </div>

                            <div class="modal-body p-3">
                                <p class="text-muted fs-12 mb-3">
                                    Confirming that you connected/conducted the meeting with <strong>{{ $clientCompany }}</strong>.
                                </p>

                                @if($isGeoRequired)
                                    <div class="border rounded-3 p-3 bg-light mb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                            <span class="fs-12 fw-bold text-dark d-flex align-items-center gap-1">
                                                <i class="feather-shield text-primary fs-13"></i> Field Visit Verification
                                            </span>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10 font-monospace">Required</span>
                                        </div>

                                        <input type="hidden" name="latitude" id="lat_{{ $item['id'] }}_Connected" value="{{ $item['latitude'] ?? '' }}">
                                        <input type="hidden" name="longitude" id="lng_{{ $item['id'] }}_Connected" value="{{ $item['longitude'] ?? '' }}">
                                        <input type="hidden" name="location_address" id="addr_{{ $item['id'] }}_Connected" value="{{ $item['location_address'] ?? '' }}">

                                        <!-- GPS Location Box -->
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label fs-11 fw-semibold text-secondary mb-0">Live GPS Location <span class="text-danger">*</span></label>
                                                <span id="loc_badge_{{ $item['id'] }}_Connected" class="badge {{ !empty($item['latitude']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-muted' }} fs-10">
                                                    {{ !empty($item['latitude']) ? 'Verified' : 'Required' }}
                                                </span>
                                            </div>
                                            <div id="loc_box_{{ $item['id'] }}_Connected" class="d-flex align-items-center justify-content-between bg-white border rounded-2 p-2 fs-12">
                                                <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                                    <span id="loc_status_dot_{{ $item['id'] }}_Connected" class="rounded-circle d-inline-block {{ !empty($item['latitude']) ? 'bg-success' : 'bg-warning' }}" style="width: 8px; height: 8px; flex-shrink: 0;"></span>
                                                    <span id="loc_text_{{ $item['id'] }}_Connected" class="text-muted text-truncate fs-12">
                                                        {{ !empty($item['latitude']) ? "GPS: {$item['latitude']}, {$item['longitude']}" : 'Click Fetch GPS to capture location' }}
                                                    </span>
                                                </div>
                                                <button type="button" class="btn btn-xs btn-outline-primary flex-shrink-0" onclick="crmCaptureLocation('{{ $item['id'] }}', 'Connected')">
                                                    <i class="feather-crosshair me-1"></i>Fetch GPS
                                                </button>
                                            </div>
                                            <div id="loc_error_{{ $item['id'] }}_Connected" class="text-danger fs-11 mt-1 d-none">
                                                Location verification is required.
                                            </div>
                                        </div>

                                        <!-- Live Selfie Box -->
                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label fs-11 fw-semibold text-secondary mb-0">Live Front Camera Selfie <span class="text-danger">*</span></label>
                                                <span id="selfie_badge_{{ $item['id'] }}_Connected" class="badge {{ !empty($item['selfie_url']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-muted' }} fs-10">
                                                    {{ !empty($item['selfie_url']) ? 'Captured' : 'Required' }}
                                                </span>
                                            </div>
                                            <input type="hidden" name="selfie_base64" id="selfie_data_{{ $item['id'] }}_Connected" value="">

                                            <div id="camera_idle_{{ $item['id'] }}_Connected" class="border rounded-2 p-3 text-center bg-white">
                                                <x-ui.button type="button" variant="primary" size="sm" icon="feather-camera" onclick="crmStartCamera('{{ $item['id'] }}', 'Connected')">
                                                    Open Camera to Take Selfie
                                                </x-ui.button>
                                            </div>

                                            <div id="camera_active_{{ $item['id'] }}_Connected" class="d-none border rounded-2 p-2 bg-dark text-center">
                                                <video id="video_{{ $item['id'] }}_Connected" autoplay playsinline class="w-100 rounded" style="max-height: 220px; object-fit: cover; transform: scaleX(-1);"></video>
                                                <canvas id="canvas_{{ $item['id'] }}_Connected" class="d-none"></canvas>
                                                <div class="mt-2 d-flex justify-content-center gap-2">
                                                    <x-ui.button type="button" variant="success" size="sm" icon="feather-camera" onclick="crmTakeSnapshot('{{ $item['id'] }}', 'Connected')">
                                                        Capture Photo
                                                    </x-ui.button>
                                                    <x-ui.button type="button" variant="light" size="sm" onclick="crmStopCamera('{{ $item['id'] }}', 'Connected')">
                                                        Cancel
                                                    </x-ui.button>
                                                </div>
                                            </div>

                                            <div id="camera_preview_{{ $item['id'] }}_Connected" class="d-none border rounded-2 p-2 bg-white text-center">
                                                <img id="preview_img_{{ $item['id'] }}_Connected" src="" alt="Captured Selfie" class="rounded border shadow-sm" style="max-height: 180px; width: auto; object-fit: cover;">
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="crmRetakePhoto('{{ $item['id'] }}', 'Connected')">
                                                        <i class="feather-refresh-cw me-1"></i>Retake Photo
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
                                    <label class="form-label fs-12 fw-semibold text-dark">Discussion Summary / Meeting Notes</label>
                                    <textarea name="notes" class="form-control fs-12" rows="3" placeholder="Enter meeting outcomes, decisions, or action items...">{{ $item['notes'] ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer py-2 px-3 border-top bg-light">
                                <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal" onclick="crmStopCamera('{{ $item['id'] }}', 'Connected')">
                                    Cancel
                                </x-ui.button>
                                <x-ui.button type="submit" variant="success" size="sm" icon="feather-check">
                                    Mark as Connected
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
                        <form action="{{ route('crm.meetings.status', $item['id']) }}" method="POST" enctype="multipart/form-data" onsubmit="return crmValidateMeetingSubmit('{{ $item['id'] }}', 'NotConnected', {{ $isGeoRequired ? 'true' : 'false' }})">
                            @csrf
                            <input type="hidden" name="status" value="Not Connected">
                            <input type="hidden" name="outcome" value="Not Connected">

                            <div class="modal-header py-2.5 px-3 border-bottom bg-soft-warning">
                                <h6 class="modal-title fw-bold text-dark fs-14">
                                    <i class="feather-phone-off text-warning me-1.5"></i>Mark as Not Connected
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="crmStopCamera('{{ $item['id'] }}', 'NotConnected')"></button>
                            </div>

                            <div class="modal-body p-3">
                                <p class="text-muted fs-12 mb-3">
                                    Meeting could not take place (e.g. client unavailable, premise locked, etc.).
                                </p>

                                @if($isGeoRequired)
                                    <div class="border rounded-3 p-3 bg-light mb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                            <span class="fs-12 fw-bold text-dark d-flex align-items-center gap-1">
                                                <i class="feather-shield text-primary fs-13"></i> Field Visit Verification
                                            </span>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10 font-monospace">Required</span>
                                        </div>

                                        <input type="hidden" name="latitude" id="lat_{{ $item['id'] }}_NotConnected" value="{{ $item['latitude'] ?? '' }}">
                                        <input type="hidden" name="longitude" id="lng_{{ $item['id'] }}_NotConnected" value="{{ $item['longitude'] ?? '' }}">
                                        <input type="hidden" name="location_address" id="addr_{{ $item['id'] }}_NotConnected" value="{{ $item['location_address'] ?? '' }}">

                                        <div class="mb-3">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label fs-11 fw-semibold text-secondary mb-0">Live GPS Location <span class="text-danger">*</span></label>
                                                <span id="loc_badge_{{ $item['id'] }}_NotConnected" class="badge {{ !empty($item['latitude']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-muted' }} fs-10">
                                                    {{ !empty($item['latitude']) ? 'Verified' : 'Required' }}
                                                </span>
                                            </div>
                                            <div id="loc_box_{{ $item['id'] }}_NotConnected" class="d-flex align-items-center justify-content-between bg-white border rounded-2 p-2 fs-12">
                                                <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                                    <span id="loc_status_dot_{{ $item['id'] }}_NotConnected" class="rounded-circle d-inline-block {{ !empty($item['latitude']) ? 'bg-success' : 'bg-warning' }}" style="width: 8px; height: 8px; flex-shrink: 0;"></span>
                                                    <span id="loc_text_{{ $item['id'] }}_NotConnected" class="text-muted text-truncate fs-12">
                                                        {{ !empty($item['latitude']) ? "GPS: {$item['latitude']}, {$item['longitude']}" : 'Click Fetch GPS to capture location' }}
                                                    </span>
                                                </div>
                                                <button type="button" class="btn btn-xs btn-outline-primary flex-shrink-0" onclick="crmCaptureLocation('{{ $item['id'] }}', 'NotConnected')">
                                                    <i class="feather-crosshair me-1"></i>Fetch GPS
                                                </button>
                                            </div>
                                            <div id="loc_error_{{ $item['id'] }}_NotConnected" class="text-danger fs-11 mt-1 d-none">
                                                Location verification is required.
                                            </div>
                                        </div>

                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <label class="form-label fs-11 fw-semibold text-secondary mb-0">Live Front Camera Selfie <span class="text-danger">*</span></label>
                                                <span id="selfie_badge_{{ $item['id'] }}_NotConnected" class="badge {{ !empty($item['selfie_url']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-muted' }} fs-10">
                                                    {{ !empty($item['selfie_url']) ? 'Captured' : 'Required' }}
                                                </span>
                                            </div>
                                            <input type="hidden" name="selfie_base64" id="selfie_data_{{ $item['id'] }}_NotConnected" value="">

                                            <div id="camera_idle_{{ $item['id'] }}_NotConnected" class="border rounded-2 p-3 text-center bg-white">
                                                <x-ui.button type="button" variant="primary" size="sm" icon="feather-camera" onclick="crmStartCamera('{{ $item['id'] }}', 'NotConnected')">
                                                    Open Camera to Take Selfie
                                                </x-ui.button>
                                            </div>

                                            <div id="camera_active_{{ $item['id'] }}_NotConnected" class="d-none border rounded-2 p-2 bg-dark text-center">
                                                <video id="video_{{ $item['id'] }}_NotConnected" autoplay playsinline class="w-100 rounded" style="max-height: 220px; object-fit: cover; transform: scaleX(-1);"></video>
                                                <canvas id="canvas_{{ $item['id'] }}_NotConnected" class="d-none"></canvas>
                                                <div class="mt-2 d-flex justify-content-center gap-2">
                                                    <x-ui.button type="button" variant="warning" size="sm" icon="feather-camera" onclick="crmTakeSnapshot('{{ $item['id'] }}', 'NotConnected')">
                                                        Capture Photo
                                                    </x-ui.button>
                                                    <x-ui.button type="button" variant="light" size="sm" onclick="crmStopCamera('{{ $item['id'] }}', 'NotConnected')">
                                                        Cancel
                                                    </x-ui.button>
                                                </div>
                                            </div>

                                            <div id="camera_preview_{{ $item['id'] }}_NotConnected" class="d-none border rounded-2 p-2 bg-white text-center">
                                                <img id="preview_img_{{ $item['id'] }}_NotConnected" src="" alt="Captured Selfie" class="rounded border shadow-sm" style="max-height: 180px; width: auto; object-fit: cover;">
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="crmRetakePhoto('{{ $item['id'] }}', 'NotConnected')">
                                                        <i class="feather-refresh-cw me-1"></i>Retake Photo
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
                                    <label class="form-label fs-12 fw-semibold text-dark">Reason / Notes</label>
                                    <textarea name="notes" class="form-control fs-12" rows="3" placeholder="e.g. Visited client office, contact person was on leave...">{{ $item['notes'] ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer py-2 px-3 border-top bg-light">
                                <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal" onclick="crmStopCamera('{{ $item['id'] }}', 'NotConnected')">
                                    Cancel
                                </x-ui.button>
                                <x-ui.button type="submit" variant="warning" size="sm" icon="feather-check">
                                    Mark as Not Connected
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
                                    <i class="feather-repeat me-1.5"></i>Reschedule Meeting
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body p-3">
                                <p class="text-muted fs-12 mb-3">
                                    Current scheduled time: <strong>{{ $dueDate ? $dueDate->format('d M Y, h:i A') : 'N/A' }}</strong>
                                </p>

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">New Date & Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="followup_date" class="form-control fs-12" required value="{{ date('Y-m-d\TH:i', strtotime('+1 day 10:00')) }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">Reschedule Reason / Notes</label>
                                    <textarea name="notes" class="form-control fs-12" rows="2" placeholder="e.g. Rescheduled as per client request...">{{ $item['notes'] ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer py-2 px-3 border-top bg-light">
                                <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal">
                                    Cancel
                                </x-ui.button>
                                <x-ui.button type="submit" variant="info" size="sm" icon="feather-check">
                                    Confirm Reschedule
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
                                    <i class="feather-x-circle me-1.5"></i>Cancel Scheduled Meeting
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body p-3">
                                <p class="text-muted fs-12 mb-3">
                                    Are you sure you want to cancel this meeting with <strong>{{ $clientCompany }}</strong>?
                                </p>

                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">Cancellation Reason</label>
                                    <textarea name="notes" class="form-control fs-12" rows="2" placeholder="e.g. Deal dropped, client requested cancellation...">{{ $item['notes'] ?? '' }}</textarea>
                                </div>
                            </div>

                            <div class="modal-footer py-2 px-3 border-top bg-light">
                                <x-ui.button type="button" variant="light" size="sm" data-bs-dismiss="modal">
                                    Close
                                </x-ui.button>
                                <x-ui.button type="submit" variant="danger" size="sm" icon="feather-trash-2">
                                    Confirm Cancel
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
    <div class="avatar-text avatar-lg bg-soft-warning text-warning rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
        <i class="feather-search fs-20"></i>
    </div>
    <h6 class="fw-bold text-dark mb-1">No matching meetings found</h6>
    <p class="text-muted fs-12 mb-0">Try searching with a different term.</p>
</div>
