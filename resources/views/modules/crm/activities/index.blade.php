@extends('layouts.duralux')

@section('title', 'Activity Calendar & Scheduler | CRM | SaaS ERP')
@section('page-title', 'Activity Calendar & Scheduler')
@section('breadcrumb', 'CRM > Activity Calendar')

@push('styles')
<style>
    .calendar-container {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background-color: #e2e8f0;
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
    }
    .calendar-day-cell {
        background-color: #ffffff;
        min-height: 125px;
        padding: 0.6rem;
        display: flex;
        flex-direction: column;
        transition: all 0.2s ease-in-out;
        position: relative;
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
        gap: 0.35rem;
        margin-top: 0.35rem;
        overflow-y: auto;
        max-height: 110px;
    }
    .activity-pill {
        font-size: 0.72rem;
        padding: 0.3rem 0.5rem;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none !important;
        font-weight: 600;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .activity-pill:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        opacity: 0.95;
    }
    .legend-indicator {
        width: 12px;
        height: 12px;
        border-radius: 3px;
        display: inline-block;
        margin-right: 6px;
    }
</style>
@endpush

@section('page-actions')
    <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#scheduleActivityModal">
        <i class="feather-plus me-1"></i>Log Activity / Follow-up
    </button>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4">
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif
    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

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
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold text-dark mb-0 me-2">Activity Calendar</h5>
            <!-- Icon View Switcher (Exact action-dropdown-btn style matching paperclip import/export button) -->
            <div class="d-flex align-items-center gap-2 me-3">
                <a href="{{ route('crm.leads.index') }}" class="action-dropdown-btn" title="List View" data-bs-toggle="tooltip">
                    <i class="feather-list"></i>
                </a>
                <a href="{{ route('crm.leads.kanban') }}" class="action-dropdown-btn" title="Pipeline Kanban" data-bs-toggle="tooltip">
                    <i class="feather-grid"></i>
                </a>
                <a href="{{ route('crm.activities.index') }}" class="action-dropdown-btn active" title="Activity Calendar" data-bs-toggle="tooltip">
                    <i class="feather-calendar"></i>
                </a>
            </div>

            <a href="{{ request()->fullUrlWithQuery(['start' => $prevStart->toDateString()]) }}" class="btn btn-sm btn-light border" title="Previous">
                <i class="feather-chevron-left"></i>
            </a>
            <h5 class="fw-bold text-dark mb-0 mx-2">
                @if($view === 'day')
                    {{ $startDate->format('l, d F Y') }}
                @elseif($view === 'week')
                    Week of {{ $startDate->copy()->startOfWeek()->format('d M') }} – {{ $startDate->copy()->endOfWeek()->format('d M Y') }}
                @else
                    {{ $startDate->format('F Y') }}
                @endif
            </h5>
            <a href="{{ request()->fullUrlWithQuery(['start' => $nextStart->toDateString()]) }}" class="btn btn-sm btn-light border" title="Next">
                <i class="feather-chevron-right"></i>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['start' => now()->toDateString()]) }}" class="btn btn-sm btn-outline-primary ms-2 fw-semibold">Today</a>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="btn-group" role="group">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'day']) }}" class="btn btn-sm {{ $view === 'day' ? 'btn-primary' : 'btn-outline-secondary' }}">Day</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'week']) }}" class="btn btn-sm {{ $view === 'week' ? 'btn-primary' : 'btn-outline-secondary' }}">Week</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'month']) }}" class="btn btn-sm {{ $view === 'month' ? 'btn-primary' : 'btn-outline-secondary' }}">Month</a>
            </div>
        </div>
    </div>

    <!-- 2. COLOR LEGEND BAR (रंगों की स्पष्ट जानकारी) -->
    <div class="card border-0 bg-light mb-4 shadow-none">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 fs-12">
                <span class="fw-bold text-dark d-flex align-items-center"><i class="feather-info me-1 text-primary"></i> Color Legend (गतिविधि रंग सूची):</span>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-danger"></span>
                        <strong class="text-dark me-1">Red:</strong> Overdue / Missed Call (छूटा हुआ फ़ॉलो-अप)
                    </span>
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-info"></span>
                        <strong class="text-dark me-1">Cyan/Blue:</strong> Scheduled Call (फोन कॉल)
                    </span>
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-primary"></span>
                        <strong class="text-dark me-1">Dark Blue:</strong> Client Meeting (मीटिंग)
                    </span>
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-teal"></span>
                        <strong class="text-dark me-1">Teal Green:</strong> Email / Proposal (ईमेल/प्रस्ताव)
                    </span>
                    <span class="d-flex align-items-center text-secondary">
                        <span class="legend-indicator bg-success"></span>
                        <strong class="text-dark me-1">Green:</strong> Completed Action (पूरा हुआ कार्य)
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
            <div class="calendar-day-header">Sun</div>
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header">Fri</div>
            <div class="calendar-day-header">Sat</div>

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

                <div class="calendar-day-cell {{ !$isCurrentMonth ? 'other-month' : '' }} {{ $isToday ? 'is-today' : '' }}">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="day-number-badge">{{ $currentDay->day }}</span>
                        @if($isToday)
                            <span class="badge bg-primary fs-10 px-1.5 py-0.5">Today</span>
                        @endif
                    </div>

                    <div class="activities-list flex-fill">
                        @foreach($dayFollowups as $f)
                            @php
                                $isOverdue = $f->followup_date->isPast() && $f->status !== 'Completed';

                                $badgeClass = match($f->type) {
                                    'Meeting' => 'bg-primary text-white',
                                    'Call' => 'bg-info text-white',
                                    'Email' => 'bg-teal text-white',
                                    default => 'bg-secondary text-white',
                                };
                                $iconClass = match($f->type) {
                                    'Meeting' => 'feather-users',
                                    'Call' => 'feather-phone-call',
                                    'Email' => 'feather-mail',
                                    default => 'feather-check-square',
                                };

                                if ($isOverdue) {
                                    $badgeClass = 'bg-danger text-white';
                                    $iconClass = 'feather-alert-triangle';
                                } elseif ($f->status === 'Completed') {
                                    $badgeClass = 'bg-success text-white';
                                    $iconClass = 'feather-check-circle';
                                }
                            @endphp
                            <a href="{{ route('crm.leads.show', $f->lead_id) }}" class="activity-pill {{ $badgeClass }}" title="{{ $f->type }}: {{ $f->lead?->company_name }} — {{ $f->notes ?: 'Scheduled Follow-up' }}">
                                <span class="d-flex align-items-center text-truncate">
                                    <i class="{{ $iconClass }} me-1 opacity-85"></i>
                                    <span class="text-truncate">{{ $f->lead?->company_name ?: 'Lead #'.$f->lead_id }}</span>
                                </span>
                                <span class="font-monospace fs-10 opacity-90 ms-1">{{ $f->followup_date->format('H:i') }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                @php $currentDay->addDay(); @endphp
            @endwhile
        </div>
    </div>
</div>

<!-- Schedule Activity Modal -->
<div class="modal fade" id="scheduleActivityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="#" method="POST" id="quickScheduleForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="feather-calendar me-2 text-primary"></i>Schedule Activity / Follow-up</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Select Lead <span class="text-danger">*</span></label>
                        <select name="lead_id" id="modal_lead_id" class="form-select" required>
                            <option value="">— Select Lead —</option>
                            @foreach($leads as $lead)
                                <option value="{{ $lead->id }}">{{ $lead->company_name }} ({{ $lead->contact_name ?: 'No Contact' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Activity Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="Call">📞 Phone Call</option>
                                <option value="Meeting">🤝 Client Meeting</option>
                                <option value="Email">✉️ Send Email / Proposal</option>
                                <option value="Task">📋 General Task</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-13">Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="followup_date" class="form-control" value="{{ now()->addDay()->format('Y-m-d\TH:i') }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-13">Activity Notes / Agenda</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Enter follow-up agenda or discussion notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Save & Schedule Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('quickScheduleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const leadId = document.getElementById('modal_lead_id').value;
        if (!leadId) {
            alert('Please select a lead first!');
            return;
        }

        this.action = `/crm/leads/${leadId}/followups`;
        this.submit();
    });
</script>
@endpush
