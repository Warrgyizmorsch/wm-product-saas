@extends('layouts.duralux')

@section('title', __('crm.deal_activity_scheduler') . ' | CRM | SaaS ERP')
@section('page-title', __('crm.deal_activity_scheduler'))
@section('breadcrumb', 'CRM > ' . __('crm.deal_activity_calendar'))

@push('styles')
<style>
    .calendar-container {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        width: 100%;
        overflow-x: auto;
    }
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 1px;
        background-color: #e2e8f0;
        width: 100%;
        min-width: 700px;
        table-layout: fixed;
    }
    .calendar-day-header {
        background-color: #f8fafc;
        padding: 0.75rem 0.5rem;
        text-align: center;
        font-weight: 700;
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e2e8f0;
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .calendar-day-cell {
        background-color: #ffffff;
        min-height: 145px;
        padding: 0.6rem;
        display: flex;
        flex-direction: column;
        transition: all 0.2s ease-in-out;
        position: relative;
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
        overflow: hidden;
    }
    .calendar-day-cell:hover {
        background-color: #f8fafc;
    }
    .calendar-day-cell.other-month {
        background-color: #f8fafc;
        opacity: 0.55;
    }
    .calendar-day-cell.is-today {
        background-color: #f0f9ff !important;
        box-shadow: inset 0 0 0 2px #3b82f6;
    }
    .day-number-badge {
        font-weight: 700;
        font-size: 0.85rem;
        color: #334155;
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    .is-today .day-number-badge {
        background-color: #3b82f6;
        color: #ffffff;
    }
    .activities-list {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        margin-top: 0.35rem;
        overflow-y: auto;
        max-height: 180px;
        padding-right: 2px;
    }
    .activity-card-rich {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 0.45rem 0.55rem;
        text-decoration: none !important;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        display: block;
        cursor: pointer;
    }
    .activity-card-rich:hover {
        transform: translateY(-1.5px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        background-color: #fafbfc;
    }
    .fs-8 { font-size: 8px !important; }
    .fs-9 { font-size: 9px !important; }
    .status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .legend-indicator {
        width: 12px;
        height: 12px;
        border-radius: 3px;
        display: inline-block;
        margin-right: 6px;
    }
    .bg-indigo { background-color: #4f46e5 !important; }
    .bg-purple { background-color: #7c3aed !important; }
    .bg-pink   { background-color: #db2777 !important; }
    .bg-amber  { background-color: #d97706 !important; }

    /* ==========================================
       Dark Mode Support (html.app-skin-dark)
       ========================================== */
    html.app-skin-dark .erp-single-panel {
        background-color: transparent !important;
        color: #e2e8f0 !important;
    }
    html.app-skin-dark .erp-single-panel.bg-white {
        background-color: #0b1329 !important;
    }
    html.app-skin-dark .calendar-container {
        border-color: #1e293b !important;
        background-color: #0f172a !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
    }
    html.app-skin-dark .calendar-grid {
        background-color: #1e293b !important;
    }
    html.app-skin-dark .calendar-day-header {
        background-color: #162038 !important;
        color: #94a3b8 !important;
        border-bottom-color: #1e293b !important;
    }
    html.app-skin-dark .calendar-day-cell {
        background-color: #0f172a !important;
        color: #cbd5e1 !important;
    }
    html.app-skin-dark .calendar-day-cell:hover {
        background-color: #162038 !important;
    }
    html.app-skin-dark .calendar-day-cell.other-month {
        background-color: #090e1a !important;
        opacity: 0.45;
    }
    html.app-skin-dark .calendar-day-cell.is-today {
        background-color: rgba(59, 130, 246, 0.15) !important;
        box-shadow: inset 0 0 0 2px #3b82f6 !important;
    }
    html.app-skin-dark .day-number-badge {
        color: #f1f5f9 !important;
    }
    html.app-skin-dark .is-today .day-number-badge {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
    }
    html.app-skin-dark .btn-soft-primary {
        background-color: rgba(59, 130, 246, 0.2) !important;
        color: #60a5fa !important;
    }
    html.app-skin-dark .card.bg-light {
        background-color: #162038 !important;
        border: 1px solid #1e293b !important;
    }
    html.app-skin-dark .card.bg-light .text-dark {
        color: #f1f5f9 !important;
    }
    html.app-skin-dark .card.bg-light .text-secondary {
        color: #94a3b8 !important;
    }
    html.app-skin-dark .border-bottom,
    html.app-skin-dark .border-top {
        border-color: #1e293b !important;
    }
    html.app-skin-dark .text-dark {
        color: #f8fafc !important;
    }
</style>
@endpush

@section('page-actions')
    @if(!empty($isGoogleConnected))
        <div class="d-inline-flex align-items-center gap-1 me-2">
            <span class="badge bg-success-subtle text-success border border-success-subtle py-1.5 px-3 fs-12 fw-bold align-middle" title="Google Account is Synced & Linked">
                <i class="feather-check-circle me-1"></i>{{ __('crm.google_calendar_synced') }}
            </span>
            <a href="{{ route('crm.google-calendar.disconnect') }}" class="btn btn-outline-danger btn-sm py-1 px-2 fs-12 fw-semibold" title="Disconnect Google Account" onclick="return confirm('Disconnect Google Calendar account?')">
                <i class="feather-log-out me-1"></i>Disconnect
            </a>
        </div>
    @else
        <a href="{{ route('crm.google-calendar.connect') }}" target="_blank" class="btn btn-outline-danger btn-sm fw-semibold me-2">
            <i class="feather-calendar me-1"></i>{{ __('crm.connect_google_account') }}
        </a>
    @endif
    <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#scheduleDealActivityModal">
        <i class="feather-plus me-1"></i>{{ __('crm.log_deal_activity') }}
    </button>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4">

    @php
        $prevStart = match($view) {
            'day'   => $startDate->copy()->subDay(),
            'week'  => $startDate->copy()->subWeek(),
            default => $startDate->copy()->subMonth(),
        };
        $nextStart = match($view) {
            'day'   => $startDate->copy()->addDay(),
            'week'  => $startDate->copy()->addWeek(),
            default => $startDate->copy()->addMonth(),
        };
    @endphp

    <!-- 1. Calendar Header Controls & View Switcher -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-3 border-bottom">
        <!-- Left Section: Title & Date Navigation Controls -->
        <div class="d-flex align-items-center flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0 me-2">{{ __('crm.deal_activity_calendar') }}</h5>

            <!-- Date Prev / Next / Today Controls -->
            <div class="d-flex align-items-center gap-1 me-2">
                <a href="{{ request()->fullUrlWithQuery(['start' => $prevStart->toDateString()]) }}" class="btn btn-xs btn-light border py-1 px-2" title="Previous">
                    <i class="feather-chevron-left"></i>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['start' => $nextStart->toDateString()]) }}" class="btn btn-xs btn-light border py-1 px-2" title="Next">
                    <i class="feather-chevron-right"></i>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['start' => now()->toDateString()]) }}" class="btn btn-xs btn-outline-primary fw-bold py-1 px-2">{{ __('crm.today') }}</a>
            </div>

            <h5 class="fw-bold text-dark mb-0 fs-14 me-2">
                @if($view === 'day')
                    {{ $startDate->format('l, d F Y') }}
                @elseif($view === 'week')
                    Week of {{ $startDate->copy()->startOfWeek()->format('d M') }} – {{ $startDate->copy()->endOfWeek()->format('d M Y') }}
                @else
                    {{ $startDate->format('F Y') }}
                @endif
            </h5>
        </div>

        <!-- Right Section: Day/Week/Month Switcher, View Switcher (List/Kanban/Cal), Filter Drawer -->
        <div class="d-flex align-items-center flex-wrap gap-2">
            <!-- Day / Week / Month Selector -->
            <div class="d-flex align-items-center me-2" style="gap: 4px;">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'day']) }}" class="btn btn-xs {{ $view === 'day' ? 'btn-primary' : 'btn-light border text-dark' }} fw-medium px-2.5 py-1">{{ __('crm.day') }}</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'week']) }}" class="btn btn-xs {{ $view === 'week' ? 'btn-primary' : 'btn-light border text-dark' }} fw-medium px-2.5 py-1">{{ __('crm.week') }}</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'month']) }}" class="btn btn-xs {{ $view === 'month' ? 'btn-primary' : 'btn-light border text-dark' }} fw-medium px-2.5 py-1">{{ __('crm.month') }}</a>
            </div>

            <!-- Icon View Switcher -->
            <x-ui.view-switcher />

            <!-- Custom Filter Component -->
            <form method="GET" action="{{ route('crm.deals.activities') }}" class="d-inline">
                <x-ui.filter :label="__('crm.filter')" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="Search deal title, account..." value="{{ request('search') }}" />
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('crm.deals.activities') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

    <!-- 2. COLOR LEGEND BAR -->
    <div class="card border-0 bg-light mb-4 shadow-none">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 fs-12">
                <span class="fw-bold text-dark d-flex align-items-center"><i class="feather-info me-1 text-primary"></i> {{ __('crm.color_legend') }}:</span>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-danger"></span>
                        <strong class="text-dark me-1">{{ __('crm.legend_red_label') }}:</strong> {{ __('crm.legend_red_desc') }}
                    </span>
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-info"></span>
                        <strong class="text-dark me-1">{{ __('crm.legend_cyan_label') }}:</strong> {{ __('crm.legend_cyan_desc') }}
                    </span>
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-primary"></span>
                        <strong class="text-dark me-1">{{ __('crm.legend_blue_label') }}:</strong> {{ __('crm.legend_blue_desc') }}
                    </span>
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-teal"></span>
                        <strong class="text-dark me-1">{{ __('crm.legend_teal_label') }}:</strong> {{ __('crm.legend_teal_desc') }}
                    </span>
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-success"></span>
                        <strong class="text-dark me-1">{{ __('crm.legend_green_label') }}:</strong> {{ __('crm.legend_green_desc') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Calendar Grid -->
    @php
        $gridStart = $startDate->copy()->startOfMonth()->startOfWeek(Carbon\Carbon::SUNDAY);
        $gridEnd   = $startDate->copy()->endOfMonth()->endOfWeek(Carbon\Carbon::SATURDAY);
    @endphp

    <div class="calendar-container">
        <div class="calendar-grid">
            <!-- Weekday Headers -->
            <div class="calendar-day-header">{{ __('crm.weekdays.sun') }}</div>
            <div class="calendar-day-header">{{ __('crm.weekdays.mon') }}</div>
            <div class="calendar-day-header">{{ __('crm.weekdays.tue') }}</div>
            <div class="calendar-day-header">{{ __('crm.weekdays.wed') }}</div>
            <div class="calendar-day-header">{{ __('crm.weekdays.thu') }}</div>
            <div class="calendar-day-header">{{ __('crm.weekdays.fri') }}</div>
            <div class="calendar-day-header">{{ __('crm.weekdays.sat') }}</div>

            <!-- Date Cells -->
            @php
                $currentDay = $gridStart->copy();
            @endphp

            @while($currentDay->lte($gridEnd))
                @php
                    $dateStr = $currentDay->toDateString();
                    $isCurrentMonth = $currentDay->month === $startDate->month;
                    $isToday = $currentDay->isToday();

                    $dayFollowups = $followups->filter(function($f) use ($dateStr) {
                        return $f->followup_date && $f->followup_date->toDateString() === $dateStr;
                    });
                @endphp

                <div class="calendar-day-cell {{ !$isCurrentMonth ? 'other-month' : '' }} {{ $isToday ? 'is-today' : '' }}" 
                     onclick="openScheduleModalForDate('{{ $dateStr }}', event)" 
                     style="cursor: pointer;" 
                     title="Click date to schedule activity">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="day-number-badge">{{ $currentDay->day }}</span>
                        <div class="d-flex align-items-center gap-1">
                            @if($isToday)
                                <span class="badge bg-primary fs-10 px-1.5 py-0.5">{{ __('crm.today') }}</span>
                            @endif
                            <button type="button" class="btn btn-xs btn-soft-primary p-0 d-inline-flex align-items-center justify-content-center" 
                                     style="width: 20px; height: 20px; border-radius: 50%;" 
                                     title="Schedule activity on {{ $currentDay->format('d M Y') }}"
                                     onclick="openScheduleModalForDate('{{ $dateStr }}', event)">
                                <i class="feather-plus fs-10"></i>
                            </button>
                        </div>
                    </div>

                    <div class="activities-list flex-fill">
                        @foreach($dayFollowups as $f)
                            @php
                                $isOverdue = $f->followup_date->isPast() && !in_array($f->status, ['Completed', 'Not Connected', 'Cancelled']);

                                $iconClass = match($f->type) {
                                    'Meeting' => 'feather-users',
                                    'Call'    => 'feather-phone-call',
                                    'Email'   => 'feather-mail',
                                    'Demo'    => 'feather-monitor',
                                    default   => 'feather-check-square',
                                };

                                if ($f->status === 'Completed') {
                                    $cardBgClass = 'bg-success text-white';
                                    $statusText = 'Completed';
                                    $badgeBgClass = 'bg-white bg-opacity-25 text-white';
                                } elseif ($f->status === 'Not Connected') {
                                    $cardBgClass = 'bg-warning text-dark';
                                    $statusText = 'Not Connected';
                                    $badgeBgClass = 'bg-black bg-opacity-10 text-dark';
                                } elseif ($f->status === 'Cancelled') {
                                    $cardBgClass = 'bg-danger text-white';
                                    $statusText = 'Cancelled';
                                    $badgeBgClass = 'bg-white bg-opacity-25 text-white';
                                } elseif ($f->status === 'Rescheduled') {
                                    $cardBgClass = 'bg-purple text-white';
                                    $statusText = 'Rescheduled';
                                    $badgeBgClass = 'bg-white bg-opacity-25 text-white';
                                } elseif ($isOverdue) {
                                    $cardBgClass = 'bg-danger text-white';
                                    $statusText = 'Overdue';
                                    $badgeBgClass = 'bg-white bg-opacity-25 text-white';
                                } else {
                                    $cardBgClass = match($f->type) {
                                        'Meeting' => 'bg-indigo text-white',
                                        'Call'    => 'bg-primary text-white',
                                        'Email'   => 'bg-amber text-white',
                                        'Demo'    => 'bg-pink text-white',
                                        default   => 'bg-indigo text-white',
                                    };
                                    $statusText = 'Scheduled';
                                    $badgeBgClass = 'bg-white bg-opacity-25 text-white';
                                }

                                $dealUrl = $f->crm_deal_id 
                                    ? route('crm.deals.show', ['deal' => $f->crm_deal_id, 'tab' => 'interactions']) . '#subtab-interactions' 
                                    : ($f->lead_id ? route('crm.leads.show', ['lead' => $f->lead_id, 'tab' => 'interactions']) . '#subtab-interactions' : '#');
                                $dealLabel = $f->deal?->title ?: ($f->deal?->account?->name ?: ($f->crm_deal_id ? 'Deal #'.$f->crm_deal_id : 'Activity'));
                                $accountName = $f->deal?->account?->name ?: ($f->deal?->contact?->first_name ?: null);
                                $assigneeName = $f->tagged_users->pluck('name')->first() ?: ($f->deal?->owner?->name ?: null);
                                $pillStyle = ($f->status === 'Not Connected') 
                                    ? 'background-color: rgba(0,0,0,0.15); color: #1e293b !important;' 
                                    : 'background-color: rgba(255,255,255,0.22); color: #ffffff !important;';
                            @endphp
                            <a href="{{ $dealUrl }}" 
                               class="activity-card-rich {{ $cardBgClass }} p-2 rounded-2 border-0 d-block text-decoration-none shadow-xs position-relative" 
                               title="{{ $f->title ?: ($f->notes ?: $f->type) }} [Status: {{ $statusText }}]" 
                               onclick="event.stopPropagation(); if (window.localStorage && '{{ $f->crm_deal_id }}') { localStorage.setItem('deal_active_tab_{{ $f->crm_deal_id }}', 'timeline-tab'); localStorage.setItem('deal_active_subtab_{{ $f->crm_deal_id }}', 'subtab-interactions-tab'); }">
                                
                                <!-- Top Row: Time + Type & Meet Indicator -->
                                <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                    <span class="fs-11 fw-bolder text-white d-inline-flex align-items-center gap-1">
                                        <i class="{{ $iconClass }} fs-11 opacity-90"></i>
                                        <span>{{ $f->followup_date->format('h:i A') }}</span>
                                        <span style="{{ $pillStyle }} padding: 1px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; display: inline-block;">
                                            {{ $f->type ?: 'Meeting' }}
                                        </span>
                                    </span>
                                    @if(!empty($f->google_meet_link) || $f->is_google_meet)
                                        <span style="background-color: #ffffff; color: #dc2626 !important; padding: 1px 6px; border-radius: 12px; font-size: 9px; font-weight: 800; display: inline-flex; align-items: center; gap: 3px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);" title="Google Meet Video Link Active">
                                            <i class="feather-video fs-8"></i> Meet
                                        </span>
                                    @endif
                                </div>

                                <!-- Middle Row: Target Deal & Account -->
                                <div class="fs-11 text-white fw-bold text-truncate lh-sm mb-1">
                                    <span class="opacity-80 fw-normal fs-10">with</span> 
                                    <span class="text-white">{{ $dealLabel }}</span>
                                    @if($accountName)
                                        <span class="opacity-80 fw-normal fs-9">({{ $accountName }})</span>
                                    @endif
                                </div>

                                <!-- Bottom Row: Status & Assignee -->
                                <div class="d-flex align-items-center justify-content-between gap-1 fs-10 text-white opacity-90 border-top border-white border-opacity-25 pt-1 mt-1">
                                    <span style="{{ $pillStyle }} padding: 1px 6px; border-radius: 4px; font-size: 9px; font-weight: 600; display: inline-block;">
                                        {{ $statusText }}
                                    </span>
                                    @if($assigneeName)
                                        <span class="text-truncate text-white fw-medium" title="Assigned To: {{ $assigneeName }}">
                                            <i class="feather-user fs-9 me-0.5 opacity-80"></i>{{ Str::limit($assigneeName, 13) }}
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>

                @php $currentDay->addDay(); @endphp
            @endwhile
        </div>
    </div>
</div>

<!-- Schedule Deal Activity Modal -->
<x-ui.modal id="scheduleDealActivityModal" :title="__('crm.schedule_activity_modal_title')" size="lg" :showFooter="false">
    <form action="" method="POST" id="quickDealScheduleForm">
        @csrf
        <input type="hidden" name="action_mode" value="schedule">

        <!-- Google Calendar Integration Banner -->
        @if(!empty($isGoogleConnected))
            <div class="alert alert-success border-0 bg-success-subtle text-success-emphasis d-flex align-items-center justify-content-between flex-wrap gap-2 rounded-3 py-2 px-3 mb-3 fs-12 fw-medium">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-check-circle fs-15 text-success"></i> 
                    <span><strong>Google Calendar Connected:</strong> Events & Google Meet links automatically sync with live push notifications.</span>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <span class="badge bg-success text-white fw-bold px-2 py-1"><i class="feather-zap me-1"></i>Active Sync</span>
                    <a href="{{ route('crm.google-calendar.disconnect') }}" class="btn btn-xs btn-outline-danger fw-bold px-2 py-1" onclick="return confirm('Disconnect Google Calendar account?')">
                        <i class="feather-log-out me-1"></i>Disconnect
                    </a>
                </div>
            </div>
        @else
            <div class="alert alert-info border-0 bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-between flex-wrap gap-2 rounded-3 py-2 px-3 mb-3 fs-12 fw-medium">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-info fs-15 text-info"></i> 
                    <span><strong>Google Calendar Integration:</strong> Schedule Google events, calls, and meetings directly into Google Calendar & Deal activities.</span>
                </div>
                <a href="{{ route('crm.google-calendar.connect') }}" target="_blank" class="btn btn-xs btn-primary fw-bold px-2 py-1" title="Click to grant Google Calendar & Gmail permissions">
                    <i class="feather-external-link me-1"></i> {{ __('crm.connect_google_account') }}
                </a>
            </div>
        @endif

        <div class="row g-2">
            <div class="col-md-6">
                <x-ui.modal-form-ui 
                    type="select" 
                    :label="__('crm.select_crm_deal')" 
                    name="deal_id" 
                    id="modal_deal_id" 
                    :required="true" 
                    :searchable="true" 
                    :errorText="$errors->first('deal_id')"
                >
                    <option value="">— {{ __('crm.select_crm_deal') }} —</option>
                    @foreach($deals as $deal)
                        <option value="{{ $deal->id }}">{{ $deal->title }} — {{ $deal->account?->name ?: 'N/A' }} ({{ $deal->deal_number }})</option>
                    @endforeach
                </x-ui.modal-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.modal-form-ui 
                    type="input" 
                    :label="__('crm.event_meeting_title')" 
                    name="title" 
                    id="modal_event_title"
                    :required="true"
                    :placeholder="__('crm.event_title_placeholder')" 
                    value="Deal Followup Call" 
                />
            </div>

            <div class="col-md-4">
                <x-ui.modal-form-ui 
                    type="select" 
                    :label="__('crm.activity_type')" 
                    name="type" 
                    id="modal_activity_type"
                    :required="true"
                    :searchable="true"
                    :errorText="$errors->first('type')"
                >
                    <option value="Call">{{ __('crm.activity_types.Call') ?? 'Call' }}</option>
                    <option value="Meeting">{{ __('crm.activity_types.Meeting') ?? 'Meeting' }}</option>
                    <option value="Email">{{ __('crm.activity_types.Email') ?? 'Email' }}</option>
                    <option value="Demo">{{ __('crm.activity_types.Demo') ?? 'Demo' }}</option>
                </x-ui.modal-form-ui>
            </div>

            <div class="col-md-4">
                <x-ui.modal-form-ui 
                    type="input" 
                    inputType="datetime-local" 
                    :label="__('crm.meeting_date_time')" 
                    name="followup_date" 
                    :value="now()->addDay()->format('Y-m-d\TH:i')" 
                    :required="true"
                    :errorText="$errors->first('followup_date')"
                />
            </div>

            <div class="col-md-4">
                <x-ui.modal-form-ui 
                    type="select" 
                    :label="__('crm.duration_minutes')" 
                    name="duration_minutes" 
                    id="modal_duration"
                    :searchable="true"
                >
                    <option value="15">{{ __('crm.duration_options.15') ?? '15 Mins' }}</option>
                    <option value="30" selected>{{ __('crm.duration_options.30') ?? '30 Mins' }}</option>
                    <option value="45">{{ __('crm.duration_options.45') ?? '45 Mins' }}</option>
                    <option value="60">{{ __('crm.duration_options.60') ?? '60 Mins (1 Hr)' }}</option>
                    <option value="90">{{ __('crm.duration_options.90') ?? '90 Mins' }}</option>
                    <option value="120">{{ __('crm.duration_options.120') ?? '120 Mins' }}</option>
                </x-ui.modal-form-ui>
            </div>

            <div class="col-12">
                <div class="p-3 bg-light rounded-3 border mb-2">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <label class="fw-bold fs-12 text-dark mb-0 d-flex align-items-center gap-1 c-pointer" for="syncGoogleSwitch">
                                <i class="feather-calendar text-danger"></i> {{ __('crm.sync_to_google_calendar') }}
                            </label>
                            <input type="hidden" name="sync_google_calendar" value="0">
                            <x-ui.checkbox name="sync_google_calendar" id="syncGoogleSwitch" value="1" :checked="true" />
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="fw-bold fs-12 text-dark mb-0 d-flex align-items-center gap-1 c-pointer" for="createMeetSwitchUnified">
                                <i class="feather-video text-primary"></i> {{ __('crm.generate_google_meet_room_link') }}
                            </label>
                            <input type="hidden" name="create_meet_link" value="0">
                            <x-ui.checkbox name="create_meet_link" id="createMeetSwitchUnified" value="1" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <x-ui.modal-form-ui 
                    type="input" 
                    :label="__('crm.guest_attendee_email_addresses')" 
                    name="guest_emails" 
                    placeholder="e.g. client@company.com, rep@mycompany.com (comma separated)" 
                />
            </div>

            <div class="col-md-6">
                <x-ui.modal-form-ui 
                    type="select" 
                    :label="__('crm.tag_persons_internal_staff')" 
                    name="tagged_user_ids[]" 
                    :multiple="true" 
                    :searchable="true"
                >
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </x-ui.modal-form-ui>
            </div>

            <div class="col-12">
                <x-ui.modal-form-ui 
                    type="textarea" 
                    :label="__('crm.agenda_discussion_notes')" 
                    name="notes" 
                    placeholder="Enter meeting agenda or discussion points..." 
                    rows="3" 
                />
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('crm.cancel') }}</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold">{{ __('crm.schedule_activity_btn') }}</button>
        </div>
    </form>
</x-ui.modal>
@endsection

@push('scripts')
<script>
    let lockedCalendarDate = null;

    function openScheduleModalForDate(dateStr, event) {
        if (event) {
            event.stopPropagation();
        }
        lockedCalendarDate = dateStr;
        const dateInput = document.querySelector('#scheduleDealActivityModal input[name="followup_date"]');
        if (dateInput) {
            const currentTimeVal = dateInput.value && dateInput.value.includes('T') ? dateInput.value.split('T')[1] : '09:00';
            dateInput.value = dateStr + 'T' + currentTimeVal;
            dateInput.setAttribute('min', dateStr + 'T00:00');
            dateInput.setAttribute('max', dateStr + 'T23:59');
        }

        const modalEl = document.getElementById('scheduleDealActivityModal');
        if (modalEl) {
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('scheduleDealActivityModal');
        const dateInput = document.querySelector('#scheduleDealActivityModal input[name="followup_date"]');

        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function() {
                if (!lockedCalendarDate && dateInput) {
                    dateInput.removeAttribute('min');
                    dateInput.removeAttribute('max');
                }
            });

            modalEl.addEventListener('hidden.bs.modal', function() {
                lockedCalendarDate = null;
                if (dateInput) {
                    dateInput.removeAttribute('min');
                    dateInput.removeAttribute('max');
                }
            });
        }

        if (dateInput) {
            dateInput.addEventListener('change', function() {
                if (lockedCalendarDate && this.value) {
                    const currentVal = this.value;
                    const timePart = currentVal.includes('T') ? currentVal.split('T')[1] : '09:00';
                    const newDatePart = currentVal.split('T')[0];
                    if (newDatePart !== lockedCalendarDate) {
                        this.value = lockedCalendarDate + 'T' + timePart;
                    }
                }
            });
        }
    });

    document.getElementById('quickDealScheduleForm')?.addEventListener('submit', function(e) {
        const dealSelect = document.getElementById('modal_deal_id');
        const dealId = dealSelect ? dealSelect.value : '';

        if (!dealId) {
            e.preventDefault();
            dealSelect.classList.add('is-invalid');
            dealSelect.focus();
            return false;
        }

        dealSelect.classList.remove('is-invalid');
        this.action = `{{ url('crm/deals') }}/${dealId}/followups`;
    });

    document.getElementById('modal_deal_id')?.addEventListener('change', function() {
        if (this.value) {
            this.classList.remove('is-invalid');
        }
    });

    document.getElementById('modal_activity_type')?.addEventListener('change', function() {
        const meetSwitch = document.getElementById('createMeetSwitchUnified');
        const syncSwitch = document.getElementById('syncGoogleSwitch');
        if (meetSwitch && syncSwitch) {
            if (this.value === 'Meeting') {
                meetSwitch.checked = true;
                syncSwitch.checked = true;
            } else {
                meetSwitch.checked = false;
            }
        }
    });
</script>
@endpush
