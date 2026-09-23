@extends('layouts.duralux')

@section('title', 'HRMS Executive & Role Dashboard | HRMS')
@section('page-title', 'HRMS Dashboard')
@section('breadcrumb', 'HRMS / Dashboard')

@php
    $rawRole = auth()->user()?->role;
    $roleDisplayName = is_object($rawRole) ? ($rawRole->name ?? 'Company Admin') : ($rawRole ?? 'Company Admin');
    $roleDisplayName = ucwords(str_replace(['_', '-'], ' ', (string) $roleDisplayName));
@endphp

@push('styles')
<style>
    .avatar-initials-dash {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 15px;
    }
    .action-inbox-pills {
        background: var(--bs-tertiary-bg, #f1f5f9);
        padding: 3px;
        border-radius: 8px;
    }
    .action-inbox-pills .nav-link {
        border-radius: 6px;
        color: var(--bs-secondary-color, #64748b);
        font-weight: 600;
        font-size: 12px;
        padding: 5px 12px;
        background: transparent;
        border: none;
        transition: all 0.15s ease-in-out;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .action-inbox-pills .nav-link:hover {
        color: var(--bs-body-color, #1e293b);
        background: var(--bs-secondary-bg, rgba(255, 255, 255, 0.6));
    }
    .action-inbox-pills .nav-link.active {
        background: var(--bs-card-bg, #ffffff) !important;
        color: var(--bs-primary, #1c3faa) !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }
    .action-inbox-pills .nav-link .tab-count-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 10px;
        line-height: 1.2;
    }
    .day-strip-box {
        flex: 1;
        border-radius: 8px;
        padding: 8px 4px;
        text-align: center;
        background: var(--bs-tertiary-bg, #f8fafc);
        border: 1px solid var(--bs-border-color, #e2e8f0);
        transition: all 0.15s ease-in-out;
    }
    .day-strip-box:hover {
        background: var(--bs-secondary-bg, #f1f5f9);
    }
    .day-strip-box.is-today {
        background: var(--bs-primary-bg-subtle, #eff6ff);
        border-color: var(--bs-primary, #93c5fd);
    }
    .dash-card-icon-avatar {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        flex-shrink: 0;
    }
</style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        @if($isHrOrAdmin ?? false)
            <a href="{{ route('hrms.employees.index') }}" class="btn btn-sm btn-primary fw-semibold shadow-sm d-inline-flex align-items-center gap-1.5">
                <i class="feather-user-plus fs-14"></i> Add Employee
            </a>
        @endif
        @include('partials.dashboard.actions')
    </div>
@endsection

@section('content')

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4 text-dark" role="alert">
            <i class="feather-check-circle me-2 text-success"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4 text-dark" role="alert">
            <i class="feather-alert-circle me-2 text-danger"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- 1. Top Executive Welcome Card -->
    <div class="card border-0 shadow-sm mb-3.5">
        <div class="card-body p-4 px-4">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <div class="d-flex align-items-center gap-4">
                        <div class="avatar-initials-dash bg-soft-primary text-primary shadow-sm border border-primary border-opacity-10 fw-bolder fs-16 flex-shrink-0" style="width: 56px; height: 56px; border-radius: 50%;">
                            {{ strtoupper(substr(auth()->user()?->name ?? ($currentEmployee?->full_name ?? 'AD'), 0, 2)) }}
                        </div>
                        <div>
                            @php
                                $hour = date('H');
                                $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
                            @endphp
                            <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                                <h5 class="fw-bold mb-0 text-dark fs-18" style="line-height: 1.3;">{{ $greeting }}, {{ auth()->user()?->name ?? ($currentEmployee?->full_name ?? 'Rahul Sharma') }}! 👋</h5>
                                <span class="badge bg-soft-primary text-primary px-2.5 py-1 fs-11 fw-bold rounded-pill border border-primary border-opacity-20 d-inline-flex align-items-center gap-1.5">
                                    <i class="feather-shield text-primary"></i> Role: {{ $roleDisplayName }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap fs-12 text-muted">
                                <span class="fw-semibold text-secondary d-inline-flex align-items-center gap-1.5">
                                    <i class="feather-briefcase text-muted"></i>{{ $currentEmployee ? ($currentEmployee->designation->name ?? 'Staff') : 'Engineering' }}
                                </span>
                                <span class="text-muted opacity-50">&bull;</span>
                                <span class="d-inline-flex align-items-center gap-1.5">
                                    <i class="feather-layers text-muted"></i>{{ $currentEmployee ? ($currentEmployee->department->name ?? 'Engineering Department') : 'Operations' }}
                                </span>
                                <span class="text-muted opacity-50">&bull;</span>
                                <span class="text-dark d-inline-flex align-items-center gap-1.5">
                                    <i class="feather-calendar text-primary"></i>{{ date('l, d F Y') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                    @php
                        $myProfileUrl = $currentEmployee ? route('hrms.employees.show', $currentEmployee->id) : route('hrms.employees.index');
                    @endphp
                    <div class="d-inline-flex align-items-center justify-content-end gap-3.5 text-start">
                        <div>
                            <span class="fs-10 text-uppercase fw-bold text-muted d-block tracking-wide mb-0.5">Profile & KYC</span>
                            <span class="fw-bolder text-dark fs-15 d-block mb-1" style="line-height: 1.2;">{{ $profileCompletion }}% Completed</span>
                            <a href="{{ $myProfileUrl }}" class="fs-11 text-primary fw-bold text-decoration-none d-inline-flex align-items-center gap-1">
                                Update Details <i class="feather-arrow-right fs-11"></i>
                            </a>
                        </div>
                        <a href="{{ $myProfileUrl }}" class="position-relative d-flex align-items-center justify-content-center flex-shrink-0" style="width: 58px; height: 58px;" title="Click to view & update Profile / KYC">
                            <svg width="58" height="58" viewBox="0 0 58 58" style="transform: rotate(-90deg);">
                                <circle cx="29" cy="29" r="23" fill="none" stroke="var(--bs-border-color, #e2e8f0)" stroke-width="4.5"></circle>
                                <circle cx="29" cy="29" r="23" fill="none" stroke="var(--bs-primary, #1c3faa)" stroke-width="4.5" stroke-dasharray="144.5" stroke-dashoffset="{{ round(144.5 - (144.5 * $profileCompletion / 100)) }}" stroke-linecap="round"></circle>
                            </svg>
                            <div class="position-absolute text-center">
                                <i class="feather-user-check text-primary fs-5"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Company Broadcasts & Announcements Section (Exact 1:1 match with user screenshot) -->
    @if(!empty($latestBroadcasts) && count($latestBroadcasts) > 0)
        <div class="card border shadow-sm mb-3.5 rounded-3 overflow-hidden" style="border-color: #e2e8f0 !important;">
            <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-initials-dash bg-soft-primary text-primary flex-shrink-0" style="width: 42px; height: 42px; border-radius: 10px;">
                        <i class="feather-radio fs-18"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold mb-0 text-dark fs-15">Company Broadcasts & Announcements</h6>
                            <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-1 fs-11 fw-bold border border-primary border-opacity-20">
                                {{ $totalBroadcastsCount ?? count($latestBroadcasts) }} Active
                            </span>
                        </div>
                        <span class="fs-12 text-muted d-block mt-0.5">Stay updated with company notifications, policy updates and official notices.</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-light rounded-2 border shadow-2xs" data-bs-toggle="collapse" data-bs-target="#broadcastsCollapse" aria-expanded="true">
                    <i class="feather-chevron-up"></i>
                </button>
            </div>

            <div class="collapse show" id="broadcastsCollapse">
                <div class="card-body p-4 bg-white">
                    <div class="d-flex flex-column gap-3">
                        @foreach($latestBroadcasts as $bc)
                            @php
                                $badgeTone = match($bc->priority ?? 'normal') {
                                    'urgent' => 'danger',
                                    'important' => 'warning',
                                    default => 'info'
                                };
                                $empRec = ($currentEmployee ?? null) && $bc->receipts ? $bc->receipts->where('employee_id', $currentEmployee->id)->first() : null;
                                $isAcked = $empRec && $empRec->acknowledged_at;
                            @endphp
                            <div class="p-4 border rounded-3 bg-white position-relative" style="border-color: #e2e8f0 !important; border-radius: 10px;">
                                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-soft-{{ $badgeTone }} text-{{ $badgeTone }} border border-{{ $badgeTone }} border-opacity-30 text-uppercase fs-10 fw-bold px-2.5 py-1 rounded-pill">
                                            <i class="feather-info me-1"></i>{{ strtoupper($bc->priority ?? 'NORMAL') }} PRIORITY
                                        </span>
                                        <span class="fs-11 text-muted fw-medium d-inline-flex align-items-center gap-1 ms-1">
                                            <i class="feather-clock fs-11"></i> {{ $bc->published_at ? \Carbon\Carbon::parse($bc->published_at)->diffForHumans() : 'Recently' }}
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if(($bc->is_acknowledgement_required ?? false) && !$isAcked)
                                            <form action="{{ \Illuminate\Support\Facades\Route::has('hrms.broadcasts.acknowledge') ? route('hrms.broadcasts.acknowledge', $bc->id) : '#' }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn text-white fw-bold text-uppercase fs-11 px-3 py-1.5 rounded-2 d-inline-flex align-items-center gap-1.5 shadow-2xs" style="background: #4a3b32; border: none;">
                                                    <i class="feather-check-circle fs-13"></i> ACKNOWLEDGE NOTICE
                                                </button>
                                            </form>
                                        @elseif(($bc->is_acknowledgement_required ?? false) && $isAcked)
                                            <span class="badge bg-soft-success text-success fs-11 fw-bold px-2.5 py-1 rounded-2 border border-success border-opacity-20 d-inline-flex align-items-center gap-1">
                                                <i class="feather-check-circle fs-12"></i> Acknowledged
                                            </span>
                                        @endif
                                        <button type="button" class="btn text-white fw-bold text-uppercase fs-11 px-3 py-1.5 rounded-2 d-inline-flex align-items-center gap-1.5 shadow-2xs" style="background: #4a3b32; border: none;">
                                            <i class="feather-message-square fs-13"></i> ADD COMMENT
                                        </button>
                                    </div>
                                </div>
                                <h6 class="fw-bold text-dark fs-15 mb-1.5" style="color: #0f172a !important;">{{ $bc->title }}</h6>
                                <p class="fs-12 text-secondary mb-0" style="line-height: 1.5; color: #64748b !important;">{{ strip_tags($bc->content ?? '') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card-footer bg-light py-2.5 px-4 d-flex align-items-center justify-content-between border-top" style="background: #f8fafc !important;">
                    <span class="fs-12 text-muted fw-medium">Showing {{ count($latestBroadcasts) }} of {{ $totalBroadcastsCount ?? count($latestBroadcasts) }} active announcements</span>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.broadcasts.index') ? route('hrms.broadcasts.index') : '#' }}" class="btn btn-sm btn-soft-primary text-primary fw-bold fs-11 rounded-2 text-uppercase d-inline-flex align-items-center gap-1.5">
                        <i class="feather-grid fs-12"></i> VIEW ALL ANNOUNCEMENTS &rarr;
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Customizable GridStack widget container shared with Accounting & Executive dashboards --}}
    @include('partials.dashboard.grid')

@endsection
