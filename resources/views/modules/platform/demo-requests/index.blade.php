@extends('layouts.admin')

@section('title', 'Demo Requests | Platform Console')
@section('page-title', 'Demo Requests')
@section('breadcrumb', 'Demo Requests')

@push('styles')
    <style>
        /* Odoo / Leads Status Tabs */
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
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
            transition: all 0.2s ease;
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

        /* Dark mode support */
        [data-bs-theme="dark"] .crm-status-tab,
        html.app-skin-dark .crm-status-tab {
            color: #94a3b8;
        }
        [data-bs-theme="dark"] .crm-status-tab:hover,
        html.app-skin-dark .crm-status-tab:hover,
        [data-bs-theme="dark"] .crm-status-tab.active,
        html.app-skin-dark .crm-status-tab.active {
            color: #60a5fa;
            background-color: rgba(96, 165, 250, 0.1);
            border-bottom-color: #60a5fa;
        }

        /* Balanced Offcanvas Drawer Width (560px) */
        body .offcanvas.demo-request-drawer,
        .offcanvas.demo-request-drawer,
        div#viewDemoDrawer,
        div[id^="viewDemoDrawer"] {
            --bs-offcanvas-width: 560px !important;
            width: 560px !important;
            max-width: 92vw !important;
        }

        /* Highlighted Slot Banner */
        .demo-slot-card {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.06) 0%, rgba(59, 130, 246, 0.12) 100%);
            border: 1px solid rgba(59, 130, 246, 0.25);
            border-radius: 12px;
        }

        /* Detail field blocks */
        .drawer-info-tile {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
        }
        [data-bs-theme="dark"] .drawer-info-tile,
        html.app-skin-dark .drawer-info-tile {
            background: #1e293b;
            border-color: #334155;
        }
    </style>
@endpush

@section('content')

    {{-- Top Summary Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Total Requests</span>
                        <h3 class="mb-0 mt-2 fw-bold text-dark">{{ number_format($summary['total']) }}</h3>
                    </div>
                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle">
                        <i class="feather-calendar fs-18"></i>
                    </div>
                </div>
            </x-ui.card>
        </div>
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold">New / Uncontacted</span>
                        <h3 class="mb-0 mt-2 fw-bold text-primary">{{ number_format($summary['new']) }}</h3>
                    </div>
                    <div class="avatar-text avatar-md bg-soft-info text-info rounded-circle">
                        <i class="feather-bell fs-18"></i>
                    </div>
                </div>
            </x-ui.card>
        </div>
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold">In Follow-up / Scheduled</span>
                        <h3 class="mb-0 mt-2 fw-bold text-warning">{{ number_format($summary['contacted'] + $summary['scheduled']) }}</h3>
                    </div>
                    <div class="avatar-text avatar-md bg-soft-warning text-warning rounded-circle">
                        <i class="feather-clock fs-18"></i>
                    </div>
                </div>
            </x-ui.card>
        </div>
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Completed / Won</span>
                        <h3 class="mb-0 mt-2 fw-bold text-success">{{ number_format($summary['completed']) }}</h3>
                    </div>
                    <div class="avatar-text avatar-md bg-soft-success text-success rounded-circle">
                        <i class="feather-check-circle fs-18"></i>
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4" dismissible>
            {{ session('success') }}
        </x-ui.alert>
    @endif

    {{-- Single ERP Panel (Lead Table Style) --}}
    <div class="erp-single-panel bg-white p-3 p-md-4 rounded shadow-sm border">

        {{-- 1. Header Toolbar (Title, Search, Sort, Filter) --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">Website Demo Requests</h5>

            <div class="d-flex align-items-center flex-wrap gap-2">
                {{-- Outside Search Box --}}
                <form method="GET" action="{{ route('platform.demo-requests.index') }}"
                    class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1"
                    style="height: 34px; min-width: 250px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark"
                        placeholder="Search name, phone, email, company..." value="{{ request('search') }}"
                        style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('platform.demo-requests.index', request()->except(['search', 'page'])) }}"
                            class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                {{-- Sort Dropdown Component --}}
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}"
                        class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Latest Submissions First</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}"
                        class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest Submissions First</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'preferred_demo_at', 'sort_order' => 'asc']) }}"
                        class="dropdown-item {{ $sortBy === 'preferred_demo_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span class="text-primary fw-semibold"><i class="feather-calendar me-1"></i>Demo Slot: Earliest First</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'preferred_demo_at', 'sort_order' => 'desc']) }}"
                        class="dropdown-item {{ $sortBy === 'preferred_demo_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span class="text-primary fw-semibold"><i class="feather-calendar me-1"></i>Demo Slot: Latest First</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'full_name', 'sort_order' => 'asc']) }}"
                        class="dropdown-item {{ $sortBy === 'full_name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Name: A to Z</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'company_name', 'sort_order' => 'asc']) }}"
                        class="dropdown-item {{ $sortBy === 'company_name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Company: A to Z</span>
                    </a>
                </x-ui.sort-dropdown>

                {{-- Filter Component --}}
                <form method="GET" action="{{ route('platform.demo-requests.index') }}" class="d-inline">
                    @if(request('sort_by')) <input type="hidden" name="sort_by" value="{{ request('sort_by') }}"> @endif
                    @if(request('sort_order')) <input type="hidden" name="sort_order" value="{{ request('sort_order') }}"> @endif

                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3">
                            <i class="feather-sliders me-1 text-primary"></i> Filter Options
                        </h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search"
                                placeholder="Search prospect, phone, email..." value="{{ request('search') }}" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                @foreach($allStatuses as $key => $meta)
                                    <option value="{{ $key }}" @selected($currentStatus === $key)>
                                        {{ $meta['label'] }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Team Size</label>
                            <x-ui.odoo-form-ui type="select" name="team_size">
                                <option value="">All Team Sizes</option>
                                @foreach(['1-10 employees', '11-50 employees', '51-200 employees', '201-500 employees', '501+ employees'] as $ts)
                                    <option value="{{ $ts }}" @selected($teamSize === $ts)>{{ $ts }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('platform.demo-requests.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- 2. Status Tabs Strip --}}
        @php
            $isAll = !$currentStatus;
        @endphp
        <div class="mb-2" style="border-bottom: 2px solid #e2e8f0;">
            <div class="d-flex align-items-center gap-1 overflow-x-auto" style="scrollbar-width: thin;">
                <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}"
                    class="crm-status-tab {{ $isAll ? 'active' : '' }}">
                    ALL ({{ $summary['total'] }})
                </a>
                @foreach($allStatuses as $key => $meta)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}"
                        class="crm-status-tab {{ $currentStatus === $key ? 'active' : '' }}">
                        {{ strtoupper($meta['label']) }} ({{ $statusCounts[$key] ?? 0 }})
                    </a>
                @endforeach
            </div>
        </div>

        {{-- 3. Odoo Style Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="demoRequestsTable" class="mb-0">
                <thead>
                    <tr>
                        <th style="width: 13%;">Submission</th>
                        <th style="width: 21%;">Prospect & Company</th>
                        <th style="width: 18%;">Preferred Slot</th>
                        <th style="width: 17%;">Phone & Email</th>
                        <th style="width: 15%;">Interested Modules</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 6%;" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($demoRequests as $demo)
                        <tr id="demoRow_{{ $demo->id }}">
                            {{-- Submission Date & Time --}}
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-text avatar-xs bg-soft-primary text-primary me-2 rounded-circle shadow-2xs d-flex align-items-center justify-content-center"
                                        style="width: 24px; height: 24px; font-size: 11px;">
                                        <i class="feather-calendar"></i>
                                    </div>
                                    <div>
                                        <span class="d-block fw-semibold text-dark fs-12 leading-tight">
                                            {{ $demo->created_at->format('d/m/Y') }}
                                        </span>
                                        <span class="text-muted fs-11">
                                            {{ $demo->created_at->format('h:i A') }} ({{ $demo->created_at->diffForHumans() }})
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- Prospect & Company --}}
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar-text avatar-sm bg-soft-primary text-primary fw-bold rounded-circle">
                                        {{ strtoupper(substr($demo->full_name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <a href="javascript:void(0)" class="fw-bold text-dark fs-13 text-decoration-none hover-primary"
                                            data-bs-toggle="offcanvas" data-bs-target="#viewDemoDrawer{{ $demo->id }}">
                                            {{ $demo->full_name }}
                                        </a>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <span class="fs-12 text-muted">{{ $demo->company_name ?: 'No company specified' }}</span>
                                            @if ($demo->team_size)
                                                <span class="badge bg-light text-dark border fs-10 px-1.5 py-0.5">{{ $demo->team_size }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if ($demo->notes)
                                    <div class="text-muted fs-11 mt-1 text-truncate" style="max-width: 240px;" title="{{ $demo->notes }}">
                                        <i class="feather-message-square me-1 text-primary fs-10"></i>{{ $demo->notes }}
                                    </div>
                                @endif
                            </td>

                            {{-- Preferred Demo Slot (Tarika A: Displayed as AM/PM format) --}}
                            <td>
                                @if ($demo->preferred_demo_at)
                                    <div class="d-inline-flex flex-column gap-0.5">
                                        <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-11 px-2 py-1 text-start">
                                            <i class="feather-calendar me-1"></i>{{ $demo->preferred_demo_at->format('d M Y') }}
                                            <span class="mx-1">•</span>
                                            <i class="feather-clock me-1"></i>{{ $demo->preferred_demo_at->format('h:i A') }}
                                        </span>
                                        <span class="fs-10 text-muted ms-1">
                                            {{ $demo->preferred_demo_at->isPast() ? 'Slot passed' : $demo->preferred_demo_at->diffForHumans() }}
                                        </span>
                                    </div>
                                @elseif ($demo->preferred_demo_date)
                                    <span class="badge bg-soft-secondary text-secondary fs-11 px-2 py-1">
                                        <i class="feather-calendar me-1"></i>{{ $demo->preferred_demo_date->format('d M Y') }}
                                        @if ($demo->preferred_demo_time)
                                            <span class="mx-1">•</span>
                                            <i class="feather-clock me-1"></i>{{ $demo->preferred_demo_time }}
                                        @endif
                                    </span>
                                @else
                                    <span class="text-muted fs-11 fst-italic">Immediate / Any time</span>
                                @endif
                            </td>

                            {{-- Phone & Email (Clean text links) --}}
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <div class="fs-12 fw-semibold text-dark">
                                        <i class="feather-phone me-1 text-muted fs-11"></i>{{ $demo->phone }}
                                    </div>
                                    <div>
                                        <a href="mailto:{{ $demo->email }}" class="text-muted fs-11 text-decoration-none hover-primary">
                                            <i class="feather-mail me-1 text-muted fs-11"></i>{{ $demo->email }}
                                        </a>
                                    </div>
                                </div>
                            </td>

                            {{-- Interested Modules --}}
                            <td>
                                @if (!empty($demo->modules) && is_array($demo->modules))
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach (array_slice($demo->modules, 0, 2) as $mod)
                                            <x-ui.badge variant="info" soft class="fs-10 py-0.5 px-1.5">{{ $mod }}</x-ui.badge>
                                        @endforeach
                                        @if (count($demo->modules) > 2)
                                            <x-ui.badge variant="secondary" soft class="fs-10 py-0.5 px-1.5" title="{{ implode(', ', array_slice($demo->modules, 2)) }}">
                                                +{{ count($demo->modules) - 2 }} more
                                            </x-ui.badge>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted fs-11">All Modules / General</span>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td>
                                @php
                                    $statusMeta = $allStatuses[$demo->status] ?? ['label' => ucfirst($demo->status), 'badge' => 'secondary'];
                                @endphp
                                <x-ui.badge variant="{{ $statusMeta['badge'] }}" soft class="fs-11">
                                    {{ $statusMeta['label'] }}
                                </x-ui.badge>
                            </td>

                            {{-- Actions --}}
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown>
                                    <x-slot:extraActions>
                                        <button type="button" class="action-dropdown-btn text-primary"
                                            title="View Details in Drawer" data-bs-toggle="offcanvas" data-bs-target="#viewDemoDrawer{{ $demo->id }}">
                                            <i class="feather-eye"></i>
                                        </button>
                                    </x-slot:extraActions>

                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item" data-bs-toggle="offcanvas" data-bs-target="#viewDemoDrawer{{ $demo->id }}">
                                            <i class="feather-sidebar me-2 text-primary fs-12"></i> Open Details Drawer
                                        </a>
                                    </li>
                                    <li>
                                        <a href="mailto:{{ $demo->email }}" class="dropdown-item text-info">
                                            <i class="feather-mail me-2 text-info fs-12"></i> Send Email
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('platform.demo-requests.destroy', $demo) }}" method="POST"
                                            id="deleteDemoForm_{{ $demo->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="dropdown-item text-danger fw-semibold"
                                                onclick="if(confirm('Are you sure you want to delete this demo request?')) { document.getElementById('deleteDemoForm_{{ $demo->id }}').submit(); }">
                                                <i class="feather-trash-2 me-2 text-danger fs-12"></i> Delete Request
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="feather-inbox fs-1 d-block mb-3 text-light"></i>
                                No demo requests found matching the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        {{-- 4. Pagination --}}
        <div class="pt-3">
            <x-ui.pagination :currentPage="$demoRequests->currentPage()" :totalPages="$demoRequests->lastPage()"
                :totalResults="$demoRequests->total()" :perPage="$demoRequests->perPage()" />
        </div>
    </div>

    {{-- Offcanvas Drawers with 860px Width & Rich Information Layout --}}
    @foreach ($demoRequests as $demo)
        <x-ui.drawer
            id="viewDemoDrawer{{ $demo->id }}"
            title="<i class='feather-file-text me-2 text-primary'></i>Demo Request #{{ $demo->id }} — {{ e($demo->full_name) }}"
            position="end"
            class="demo-request-drawer"
            style="--bs-offcanvas-width: 560px !important; width: 560px !important; max-width: 92vw !important;"
        >
            <form action="{{ route('platform.demo-requests.status', $demo) }}" method="POST" class="d-flex flex-column h-100">
                @csrf
                @method('PATCH')

                {{-- 1. Prospect Header Banner --}}
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2.5">
                            <span class="avatar-text avatar-lg bg-soft-primary text-primary fw-bold rounded-circle fs-16">
                                {{ strtoupper(substr($demo->full_name, 0, 1)) }}
                            </span>
                            <div>
                                <h5 class="fw-bold text-dark mb-0 fs-16">{{ $demo->full_name }}</h5>
                                <div class="d-flex align-items-center gap-2 mt-0.5 flex-wrap">
                                    <span class="fs-12 text-muted fw-medium">
                                        <i class="feather-briefcase me-1 text-primary"></i>{{ $demo->company_name ?: 'Individual Prospect' }}
                                    </span>
                                    @if ($demo->team_size)
                                        <span class="badge bg-white text-dark border fs-10 px-1.5 py-0.5">
                                            <i class="feather-users me-1 text-info"></i>{{ $demo->team_size }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div>
                            @php
                                $statusMeta = $allStatuses[$demo->status] ?? ['label' => ucfirst($demo->status), 'badge' => 'secondary'];
                            @endphp
                            <x-ui.badge variant="{{ $statusMeta['badge'] }}" soft class="fs-11 px-2.5 py-1 fw-bold">
                                {{ $statusMeta['label'] }}
                            </x-ui.badge>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-2 border-top fs-11 text-muted">
                        <span><i class="feather-calendar me-1 text-primary"></i>Submitted: <strong class="text-dark">{{ $demo->created_at->format('d M Y, h:i A') }}</strong></span>
                        <span class="badge bg-soft-secondary text-secondary fs-10">{{ $demo->created_at->diffForHumans() }}</span>
                    </div>
                </div>

                {{-- 2. Requested Demo Slot Card --}}
                <div class="demo-slot-card p-3 mb-3">
                    <div class="d-flex align-items-start gap-2.5">
                        <div class="avatar-text avatar-md bg-primary text-white rounded-circle flex-shrink-0 shadow-sm" style="width: 40px; height: 40px; font-size: 16px;">
                            <i class="feather-calendar"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="text-uppercase fs-10 fw-bold text-primary tracking-wide d-block mb-1">PROSPECT'S REQUESTED DEMO SCHEDULE</span>
                            @if ($demo->preferred_demo_at)
                                <h6 class="fw-bold text-dark mb-1 fs-15">
                                    {{ $demo->preferred_demo_at->format('l, d F Y') }}
                                </h6>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge bg-primary text-white fs-11 px-2 py-0.5 shadow-2xs">
                                        <i class="feather-clock me-1"></i>{{ $demo->preferred_demo_at->format('h:i A') }} (IST)
                                    </span>
                                    <span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 border border-primary-subtle">
                                        {{ $demo->preferred_demo_at->isPast() ? 'Slot has passed' : $demo->preferred_demo_at->diffForHumans() }}
                                    </span>
                                </div>
                            @elseif ($demo->preferred_demo_date)
                                <h6 class="fw-bold text-dark mb-0 fs-15">
                                    {{ $demo->preferred_demo_date->format('l, d F Y') }}
                                    @if ($demo->preferred_demo_time)
                                        <span class="badge bg-primary text-white fs-11 ms-2 px-2 py-0.5">{{ $demo->preferred_demo_time }}</span>
                                    @endif
                                </h6>
                            @else
                                <div class="text-muted fs-12 mt-0.5 fst-italic">
                                    Prospect did not specify a date. Requested earliest availability.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- 3. Contact & Company Info Grid (2 Columns) --}}
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <div class="drawer-info-tile h-100">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                <i class="feather-phone me-1 text-primary"></i>Mobile / Phone
                            </span>
                            <span class="text-dark fw-bold fs-13 select-all">{{ $demo->phone }}</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="drawer-info-tile h-100">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                <i class="feather-mail me-1 text-primary"></i>Email Address
                            </span>
                            <a href="mailto:{{ $demo->email }}" class="text-primary fw-bold fs-13 text-decoration-none text-truncate d-block" title="{{ $demo->email }}">
                                {{ $demo->email }}
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="drawer-info-tile h-100">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                <i class="feather-briefcase me-1 text-primary"></i>Company Name
                            </span>
                            <span class="text-dark fw-semibold fs-13">{{ $demo->company_name ?: 'Not Provided' }}</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="drawer-info-tile h-100">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">
                                <i class="feather-users me-1 text-primary"></i>Team Size
                            </span>
                            <span class="text-dark fw-semibold fs-13">{{ $demo->team_size ?: 'Not Provided' }}</span>
                        </div>
                    </div>
                </div>

                {{-- 4. Requested ERP Modules --}}
                <div class="drawer-info-tile mb-3">
                    <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-2">
                        <i class="feather-layers me-1.5 text-primary"></i>Requested ERP Modules ({{ !empty($demo->modules) ? count($demo->modules) : 0 }})
                    </span>
                    <div class="d-flex flex-wrap gap-2">
                        @if (!empty($demo->modules) && is_array($demo->modules))
                            @foreach ($demo->modules as $mod)
                                <span class="badge bg-white text-dark border fs-12 py-1.5 px-3 shadow-2xs rounded-pill">
                                    <i class="feather-check-circle me-1.5 text-success"></i>{{ $mod }}
                                </span>
                            @endforeach
                        @else
                            <span class="text-muted fs-12">All ERP Modules / Complete Suite Demo</span>
                        @endif
                    </div>
                </div>

                {{-- 5. Prospect Message / Requirements --}}
                @if ($demo->notes)
                    <div class="drawer-info-tile mb-3">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1.5">
                            <i class="feather-message-square me-1.5 text-primary"></i>Prospect Message / Specific Requirements
                        </span>
                        <div class="p-3 bg-white rounded border-start border-3 border-primary text-dark fs-13 shadow-2xs" style="line-height: 1.6; white-space: pre-wrap;">{{ $demo->notes }}</div>
                    </div>
                @endif

                {{-- 6. Lead Status & Admin Notes (Management Section) --}}
                <div class="p-3.5 bg-light rounded-3 mb-3 border">
                    <h6 class="fw-bold text-dark fs-14 mb-3 d-flex align-items-center">
                        <i class="feather-edit-3 me-2 text-primary"></i>Update Lead Status & Follow-up
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold fs-12 text-dark mb-1">Lead Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select form-select-sm fs-12" required>
                                @foreach ($allStatuses as $key => $meta)
                                    <option value="{{ $key }}" @selected($demo->status === $key)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                            <span class="fs-11 text-muted d-block mt-1">Change status as you progress with the demo.</span>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label fw-semibold fs-12 text-dark mb-1">Internal Follow-up Remarks</label>
                            <textarea name="admin_notes" rows="3" class="form-control form-control-sm fs-12"
                                placeholder="Add salesperson assigned, meeting link, demo discussion summary...">{{ old('admin_notes', $demo->admin_notes) }}</textarea>
                        </div>
                    </div>
                </div>

                @if ($demo->ip_address)
                    <div class="text-muted fs-11 px-1 mb-3">
                        <span><i class="feather-globe me-1"></i>Submission IP: {{ $demo->ip_address }}</span>
                    </div>
                @endif

                {{-- Drawer Footer Actions --}}
                <div class="mt-auto pt-3 border-top d-flex gap-2 justify-content-end bg-white">
                    <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="offcanvas">Close</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3">
                        <i class="feather-save me-1"></i> Update Lead Status
                    </button>
                </div>
            </form>
        </x-ui.drawer>
    @endforeach

@endsection
