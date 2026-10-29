@extends('layouts.duralux')

@section('title', __('crm.my_meetings_sidebar', ['default' => 'My Meetings']) . ' | CRM | SaaS ERP')
@section('page-title', __('crm.my_meetings_sidebar', ['default' => 'My Meetings']))
@section('breadcrumb', 'CRM / ' . __('crm.my_meetings_sidebar', ['default' => 'My Meetings']))

@section('page-badge')
    <span
        class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 fw-bold border border-primary-subtle d-inline-flex align-items-center gap-1">
        <i class="feather-user fs-10"></i> {{ $user->name ?? 'Executive' }}
    </span>
    @if(!empty($geoVerification['is_enabled']))
        <span class="badge bg-soft-info text-info fs-11 px-2 py-1 fw-bold border border-info-subtle"
            title="Geo-Verification is active for field meetings">
            <i class="feather-shield fs-10"></i> Field Geo-Verified
        </span>
    @endif
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('crm.meetings.index') }}" variant="light" size="sm" icon="feather-refresh-cw"
            title="Refresh list">
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <style>
        /* CRM Status Tabs (Exact Match to Lead & Deal Index) */
        .crm-status-tabs-wrapper {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            background-color: transparent;
            padding-left: 0;
            padding-right: 0;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .crm-status-tabs-wrapper::-webkit-scrollbar {
            display: none;
        }

        .crm-status-tab {
            display: inline-flex;
            align-items: center;
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #64748b;
            text-decoration: none;
            white-space: nowrap;
            border-top: none;
            border-left: none;
            border-right: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
            background: transparent;
            cursor: pointer;
        }

        .crm-status-tab:hover {
            color: var(--bs-primary);
            background-color: color-mix(in srgb, var(--bs-primary) 6%, transparent);
        }

        .crm-status-tab.active {
            color: var(--bs-primary);
            border-bottom-color: var(--bs-primary);
            background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent);
        }

        .crm-status-tab.tab-overdue:hover {
            color: #ef4444;
            background-color: rgba(239, 68, 68, 0.06);
        }

        .crm-status-tab.tab-overdue.active {
            color: #ef4444;
            border-bottom-color: #ef4444;
            background-color: rgba(239, 68, 68, 0.08);
        }

        /* Overdue animated badge pulse */
        .overdue-pulse {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #ef4444;
            animation: pulse-ring 1.5s infinite;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 5px rgba(239, 68, 68, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        /* Table styling aligned with Lead Index */
        .meeting-table-row:hover {
            background-color: #f8fafc;
        }

        .table-responsive {
            overflow-x: auto;
            scrollbar-width: thin;
        }

        /* Dark mode enhancements */
        [data-bs-theme="dark"] .crm-status-tabs-wrapper,
        html.app-skin-dark .crm-status-tabs-wrapper {
            border-bottom-color: #334155;
        }

        [data-bs-theme="dark"] .crm-status-tab,
        html.app-skin-dark .crm-status-tab {
            color: #94a3b8;
        }

        [data-bs-theme="dark"] .crm-status-tab:hover,
        html.app-skin-dark .crm-status-tab:hover {
            color: #60a5fa;
            background-color: rgba(96, 165, 250, 0.1);
        }

        [data-bs-theme="dark"] .crm-status-tab.active,
        html.app-skin-dark .crm-status-tab.active {
            color: #60a5fa;
            border-bottom-color: #60a5fa;
            background-color: rgba(96, 165, 250, 0.15);
        }

        [data-bs-theme="dark"] .meeting-table-row:hover,
        html.app-skin-dark .meeting-table-row:hover {
            background-color: #162038 !important;
        }
    </style>
@endpush

@section('content')

    {{-- Top Section: 4 KPI Cards using Common Component <x-ui.kpi-card> --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-ui.kpi-card 
                title="Today's Schedule" 
                :value="$counts['today'] ?? 0" 
                icon="feather-calendar" 
                tone="brand" 
                subtitle="Pending meetings for today"
                :href="request()->fullUrlWithQuery(['tab' => 'today', 'page' => null])"
                :class="$activeTab === 'today' ? 'border-primary shadow-xs' : ''" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-ui.kpi-card 
                title="Planned Meetings" 
                :value="$counts['planned'] ?? 0" 
                icon="feather-clock" 
                tone="info" 
                subtitle="Upcoming future meetings"
                :href="request()->fullUrlWithQuery(['tab' => 'planned', 'page' => null])"
                :class="$activeTab === 'planned' ? 'border-info shadow-xs' : ''" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-ui.kpi-card 
                title="Overdue Meetings" 
                :value="$counts['overdue'] ?? 0" 
                icon="feather-alert-triangle" 
                tone="negative" 
                valueTone="negative"
                subtitle="Pending past scheduled date"
                :href="request()->fullUrlWithQuery(['tab' => 'overdue', 'page' => null])"
                :class="$activeTab === 'overdue' ? 'border-danger shadow-xs' : ''" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-ui.kpi-card 
                title="Activity Logs" 
                :value="$counts['activity_logs'] ?? 0" 
                icon="feather-check-circle" 
                tone="positive" 
                subtitle="Completed & updated meetings"
                :href="request()->fullUrlWithQuery(['tab' => 'activity_logs', 'page' => null])"
                :class="$activeTab === 'activity_logs' ? 'border-success shadow-xs' : ''" />
        </div>
    </div>

    {{-- Main Container: Standard ERP Single Panel --}}
    <div class="erp-single-panel">

        {{-- 1. Panel Header: Title & Quick Search / Filters / Sorting Toolbar --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('crm.my_meetings_sidebar', ['default' => 'My Meetings']) }}</h5>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box (HRMS / CRM Lead Index Style) -->
                <form method="GET" action="{{ route('crm.meetings.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1"
                    style="height: 34px; min-width: 250px;">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    @if(request('sort_by'))<input type="hidden" name="sort_by" value="{{ request('sort_by') }}">@endif
                    @if(request('sort_order'))<input type="hidden" name="sort_order" value="{{ request('sort_order') }}">@endif
                    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                    @if(request('date_from'))<input type="hidden" name="date_from" value="{{ request('date_from') }}">@endif
                    @if(request('date_to'))<input type="hidden" name="date_to" value="{{ request('date_to') }}">@endif

                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" id="meetingLiveSearch"
                        class="form-control border-0 bg-transparent p-0 fs-12 text-dark"
                        placeholder="Search company, contact, title..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => null]) }}" id="clearMeetingSearch" class="text-muted p-0"
                            title="Clear Search" style="text-decoration: none;">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @else
                        <button type="button" id="clearMeetingSearch" class="btn btn-link text-muted p-0 d-none"
                            title="Clear Search" style="text-decoration: none;">
                            <i class="feather-x fs-12"></i>
                        </button>
                    @endif
                </form>

                <!-- Sort Dropdown (Common Component: x-ui.sort-dropdown) -->
                <x-ui.sort-dropdown :label="__('crm.sort') ?: 'Sort'">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'followup_date', 'sort_order' => 'asc', 'page' => null]) }}" class="dropdown-item {{ ($sortBy === 'followup_date' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <i class="feather-calendar me-2 fs-12"></i><span>Date (Earliest First)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'followup_date', 'sort_order' => 'desc', 'page' => null]) }}" class="dropdown-item {{ ($sortBy === 'followup_date' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <i class="feather-calendar me-2 fs-12"></i><span>Date (Latest First)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'company', 'sort_order' => 'asc', 'page' => null]) }}" class="dropdown-item {{ ($sortBy === 'company' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <i class="feather-type me-2 fs-12"></i><span>Company / Lead (A - Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'company', 'sort_order' => 'desc', 'page' => null]) }}" class="dropdown-item {{ ($sortBy === 'company' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <i class="feather-type me-2 fs-12"></i><span>Company / Lead (Z - A)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown (Common Component: x-ui.filter) -->
                <form method="GET" action="{{ route('crm.meetings.index') }}" class="d-inline">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if(request('sort_by'))<input type="hidden" name="sort_by" value="{{ request('sort_by') }}">@endif
                    @if(request('sort_order'))<input type="hidden" name="sort_order" value="{{ request('sort_order') }}">@endif

                    <x-ui.filter :label="__('crm.filter') ?: 'Filter'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Meetings</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                                <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Connected / Completed</option>
                                <option value="Not Connected" {{ request('status') === 'Not Connected' ? 'selected' : '' }}>Not Connected</option>
                                <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                                <option value="Rescheduled" {{ request('status') === 'Rescheduled' ? 'selected' : '' }}>Rescheduled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date From</label>
                            <x-ui.odoo-form-ui type="date" name="date_from" value="{{ request('date_from') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date To</label>
                            <x-ui.odoo-form-ui type="date" name="date_to" value="{{ request('date_to') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('crm.meetings.index', ['tab' => $activeTab]) }}" class="btn btn-sm btn-light border">{{ __('crm.reset') ?: 'Reset' }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') ?: 'Apply Filters' }}</button>
                        </div>
                    </x-ui.filter>
                </form>

                <x-ui.button href="{{ route('crm.meetings.index', ['tab' => $activeTab]) }}" variant="light" size="sm" icon="feather-refresh-cw"
                    title="Refresh list">
                </x-ui.button>
            </div>
        </div>

        {{-- 2. Status Filter Tabs Strip (Exact Match to Lead Index) --}}
        <div class="mb-2" style="border-bottom: 2px solid #e2e8f0;">
            <div class="crm-status-tabs-wrapper">
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'today', 'page' => null]) }}" 
                   class="crm-status-tab {{ $activeTab === 'today' ? 'active' : '' }}">
                    TODAY ({{ $counts['today'] ?? 0 }})
                </a>
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'planned', 'page' => null]) }}" 
                   class="crm-status-tab {{ $activeTab === 'planned' ? 'active' : '' }}">
                    PLANNED ({{ $counts['planned'] ?? 0 }})
                </a>
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'overdue', 'page' => null]) }}" 
                   class="crm-status-tab tab-overdue {{ ($counts['overdue'] ?? 0) > 0 ? 'text-danger' : '' }} {{ $activeTab === 'overdue' ? 'active' : '' }}">
                    @if(($counts['overdue'] ?? 0) > 0)<span class="overdue-pulse me-1"></span>@endif
                    OVERDUE ({{ $counts['overdue'] ?? 0 }})
                </a>
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'activity_logs', 'page' => null]) }}" 
                   class="crm-status-tab {{ $activeTab === 'activity_logs' ? 'active' : '' }}">
                    ACTIVITY LOGS ({{ $counts['activity_logs'] ?? 0 }})
                </a>
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => null]) }}" 
                   class="crm-status-tab {{ $activeTab === 'all' ? 'active' : '' }}">
                    ALL ({{ $counts['total'] ?? 0 }})
                </a>
            </div>
        </div>

        {{-- 3. Table Content with Pagination --}}
        <div class="pt-2">
            @include('modules.crm.meetings.partials.meetings-list', [
                'items' => $meetings, 
                'meetings' => $meetings,
                'emptyMessage' => match($activeTab) {
                    'today' => 'No meetings scheduled for today.',
                    'planned' => 'No upcoming future meetings found.',
                    'overdue' => 'Great job! You have zero overdue meetings.',
                    'activity_logs' => 'No completed activity logs recorded yet.',
                    default => 'No meetings found.',
                }, 
                'isActionable' => ($activeTab !== 'activity_logs'),
                'isOverdue' => ($activeTab === 'overdue')
            ])
        </div>

    </div>

    {{-- Global Selfie Viewer Modal --}}
    <div class="modal fade" id="meetingSelfieViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header py-2.5 px-3 border-bottom">
                    <h6 class="modal-title fw-bold text-dark fs-14">
                        <i class="feather-camera text-primary me-1.5"></i>Field Selfie Verification Photo
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-3 bg-light">
                    <img id="meetingSelfieViewImg" src="" alt="Selfie" class="img-fluid rounded border shadow-sm"
                        style="max-height: 380px; object-fit: contain;">
                    <div id="meetingSelfieErrorMsg" class="d-none py-4 text-muted">
                        <i class="feather-image text-secondary fs-32 d-block mb-2"></i>
                        <span class="fs-12">Unable to display selfie image</span>
                    </div>

                    <div id="meetingSelfieInfoBox" class="mt-2.5 p-2.5 bg-white rounded-3 border text-start shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold fs-11 text-dark d-flex align-items-center gap-1">
                                <i class="feather-map-pin text-primary fs-12"></i> Actual Verification Location:
                            </span>
                            <span id="meetingSelfieStatusBadge" class="badge bg-soft-success text-success fs-10 fw-semibold"></span>
                        </div>
                        <div id="meetingSelfieViewLocation" class="text-secondary fs-12 fw-medium mb-1"></div>
                        <div class="d-flex align-items-center justify-content-between text-muted fs-11 pt-1 border-top flex-wrap gap-1">
                            <span id="meetingSelfieViewGPS"></span>
                            <span id="meetingSelfieViewTime"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Initialize Bootstrap Tooltips for note and location popups
            document.addEventListener('DOMContentLoaded', function () {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                    new bootstrap.Tooltip(tooltipTriggerEl, { html: true });
                });
            });

            // Live Search Filter for Meeting Table Rows (Instant Client-side Filter + Server Submit on Enter)
            const searchInput = document.getElementById('meetingLiveSearch');
            const clearBtn = document.getElementById('clearMeetingSearch');

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const query = this.value.toLowerCase().trim();
                    if (clearBtn && clearBtn.tagName === 'BUTTON') {
                        clearBtn.classList.toggle('d-none', query === '');
                    }

                    const container = document.querySelector('.erp-single-panel');
                    if (!container) return;

                    const rows = container.querySelectorAll('.meeting-table-row');
                    let visibleCount = 0;

                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        if (text.includes(query)) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    const emptySearchState = container.querySelector('.empty-search-state');
                    if (emptySearchState) {
                        emptySearchState.style.display = (visibleCount === 0 && query !== '' && rows.length > 0) ? 'block' : 'none';
                    }
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (searchInput) {
                        searchInput.value = '';
                        searchInput.dispatchEvent(new Event('input'));
                        searchInput.focus();
                    }
                });
            }

            // View selfie photo in modal with actual status verification location
            window.crmOpenSelfieModal = function (url, location, gps, time, status) {
                if (url && typeof url === 'string') {
                    while (url.includes('/storage/storage/')) {
                        url = url.replace('/storage/storage/', '/storage/');
                    }
                }
                const img = document.getElementById('meetingSelfieViewImg');
                const loc = document.getElementById('meetingSelfieViewLocation');
                const errMsg = document.getElementById('meetingSelfieErrorMsg');
                const gpsEl = document.getElementById('meetingSelfieViewGPS');
                const timeEl = document.getElementById('meetingSelfieViewTime');
                const badgeEl = document.getElementById('meetingSelfieStatusBadge');
                const infoBox = document.getElementById('meetingSelfieInfoBox');

                if (errMsg) errMsg.classList.add('d-none');
                if (img) {
                    img.classList.remove('d-none');
                    img.onerror = function () {
                        this.classList.add('d-none');
                        if (errMsg) errMsg.classList.remove('d-none');
                    };
                    img.src = url || '';
                }

                if (loc) {
                    loc.textContent = location || (gps ? ('GPS: ' + gps) : 'Location captured during selfie verification');
                }
                if (gpsEl) {
                    if (gps) {
                        gpsEl.innerHTML = '<a href="https://maps.google.com/?q=' + encodeURIComponent(gps) + '" target="_blank" class="text-primary text-decoration-none hover-underline"><i class="feather-navigation me-1 fs-10"></i>' + gps + ' <i class="feather-external-link fs-9"></i></a>';
                    } else {
                        gpsEl.innerHTML = '';
                    }
                }
                if (timeEl) {
                    timeEl.innerHTML = time ? ('<i class="feather-clock text-muted me-1 fs-10"></i>' + time) : '';
                }
                if (badgeEl) {
                    if (status) {
                        badgeEl.textContent = status;
                        const isMismatch = status.toLowerCase().includes('mismatch');
                        badgeEl.className = 'badge ' + (isMismatch ? 'bg-soft-danger text-danger border border-danger-subtle' : 'bg-soft-success text-success border border-success-subtle') + ' fs-10 fw-semibold';
                        badgeEl.classList.remove('d-none');
                    } else {
                        badgeEl.classList.add('d-none');
                    }
                }

                const modal = new bootstrap.Modal(document.getElementById('meetingSelfieViewModal'));
                modal.show();
            };

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
                    if (locText) locText.textContent = 'Geolocation not supported by device.';
                    if (locError) {
                        locError.textContent = 'Geolocation is not supported by your browser.';
                        locError.classList.remove('d-none');
                    }
                    return;
                }

                if (locText) {
                    locText.textContent = isRetry ? 'Detecting network location...' : 'Fetching live location...';
                    locText.className = 'text-primary text-truncate fs-12';
                }
                if (statusDot) statusDot.className = 'rounded-circle d-inline-block bg-info';

                const options = isRetry
                    ? { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
                    : { enableHighAccuracy: true, timeout: 4000, maximumAge: 60000 };

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        const lat = pos.coords.latitude.toFixed(7);
                        const lng = pos.coords.longitude.toFixed(7);
                        if (latInput) latInput.value = lat;
                        if (lngInput) lngInput.value = lng;
                        if (statusDot) statusDot.className = 'rounded-circle d-inline-block bg-success';
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

                        // Reverse geocoding via OpenStreetMap Nominatim
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
                        }
                        if (statusDot) statusDot.className = 'rounded-circle d-inline-block bg-danger';
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
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
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
                        if (selfieErr) {
                            selfieErr.textContent = 'Camera access denied or device unavailable: ' + (err.message || 'Error');
                            selfieErr.classList.remove('d-none');
                        }
                    });
            };

            window.crmTakeSnapshot = function (itemId, suffix) {
                const key = itemId + '_' + suffix;
                const videoEl = document.getElementById('video_' + key);
                const canvasEl = document.getElementById('canvas_' + key);
                const previewImg = document.getElementById('preview_img_' + key);
                const activeEl = document.getElementById('camera_active_' + key);
                const previewEl = document.getElementById('camera_preview_' + key);
                const photoInput = document.getElementById('selfie_data_' + key);

                if (!videoEl || !canvasEl) return;

                canvasEl.width = videoEl.videoWidth || 640;
                canvasEl.height = videoEl.videoHeight || 480;
                const ctx = canvasEl.getContext('2d');
                ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

                const dataUrl = canvasEl.toDataURL('image/jpeg', 0.85);
                if (photoInput) photoInput.value = dataUrl;
                if (previewImg) previewImg.src = dataUrl;

                // Stop camera stream
                if (window.crmActiveStreams[key]) {
                    window.crmActiveStreams[key].getTracks().forEach(t => t.stop());
                    delete window.crmActiveStreams[key];
                }

                if (activeEl) activeEl.classList.add('d-none');
                if (previewEl) previewEl.classList.remove('d-none');
            };

            window.crmStopCamera = function (itemId, suffix) {
                const key = itemId + '_' + suffix;
                if (window.crmActiveStreams[key]) {
                    window.crmActiveStreams[key].getTracks().forEach(t => t.stop());
                    delete window.crmActiveStreams[key];
                }
                const idleEl = document.getElementById('camera_idle_' + key);
                const activeEl = document.getElementById('camera_active_' + key);
                const videoEl = document.getElementById('video_' + key);
                if (videoEl) videoEl.srcObject = null;
                if (activeEl) activeEl.classList.add('d-none');
                if (idleEl) idleEl.classList.remove('d-none');
            };

            window.crmSnapSelfie = window.crmTakeSnapshot;
            window.crmRetakeSelfie = function (itemId, suffix) {
                const key = itemId + '_' + suffix;
                const photoInput = document.getElementById('selfie_data_' + key);
                if (photoInput) photoInput.value = '';
                window.crmStartCamera(itemId, suffix);
            };
            window.crmRetakePhoto = window.crmRetakeSelfie;

            window.crmValidateMeetingSubmit = function (itemId, suffix, isGeoRequired) {
                if (!isGeoRequired) return true;

                const key = itemId + '_' + suffix;
                const lat = document.getElementById('lat_' + key)?.value;
                const selfie = document.getElementById('selfie_data_' + key)?.value;
                const fileInput = document.getElementById('selfie_file_' + key);
                const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

                let hasError = false;

                if (!lat) {
                    const locErr = document.getElementById('loc_error_' + key);
                    const locBox = document.getElementById('loc_box_' + key);
                    if (locErr) {
                        locErr.textContent = 'GPS location is strictly required to verify field presence.';
                        locErr.classList.remove('d-none');
                    }
                    if (locBox) locBox.classList.add('border-danger');
                    hasError = true;
                }

                if (!selfie && !hasFile) {
                    const selfieErr = document.getElementById('selfie_error_' + key);
                    const idleEl = document.getElementById('camera_idle_' + key);
                    if (selfieErr) {
                        selfieErr.textContent = 'Selfie photo proof is strictly required by management for field verification.';
                        selfieErr.classList.remove('d-none');
                    }
                    if (idleEl) idleEl.classList.add('border-danger');
                    hasError = true;
                }

                return !hasError;
            };
        </script>
    @endpush
@endsection