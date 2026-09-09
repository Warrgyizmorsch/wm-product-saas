@extends('layouts.duralux')

@section('title', 'Company Broadcasts & Announcements | HRMS')
@section('page-title', 'Broadcasts & Company Announcements')
@section('breadcrumb', 'HRMS / Communications / Broadcasts')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createBroadcastModal" class="fw-bold text-uppercase">
            Create Broadcast
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    #broadcastTabs .nav-link {
        border: none !important;
        background-color: transparent !important;
        color: #64748b;
        font-weight: 500;
        padding: 12px 20px;
        border-bottom: 2px solid transparent !important;
        transition: all 0.2s ease-in-out;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    #broadcastTabs .nav-link:hover {
        color: var(--bs-primary);
    }
    #broadcastTabs .nav-link.active {
        color: var(--bs-primary) !important;
        border-bottom: 2px solid var(--bs-primary) !important;
        font-weight: 600;
    }
    #createBroadcastModal .modal-dialog {
        max-width: 860px !important;
    }
    #createBroadcastModal .odoo-form-label {
        width: auto !important;
        min-width: 120px !important;
        max-width: 215px !important;
        flex-shrink: 0 !important;
        white-space: nowrap !important;
        margin-right: 8px !important;
    }
    #createBroadcastModal .erp-custom-file-upload {
        display: flex !important;
        justify-content: flex-end !important;
        width: 100% !important;
    }
    #createBroadcastModal .erp-custom-file-upload .file-upload-label {
        max-width: 155px !important;
        padding: 5px 10px !important;
        font-size: 12px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- STATS SUMMARY ROW -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100 position-relative overflow-hidden" style="border-top: 3px solid var(--bs-primary) !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">Active Published</small>
                                <h3 class="fw-extrabold text-dark mb-0 mt-1.5 fs-22">{{ $totalActive }}</h3>
                            </div>
                            <div class="p-2.5 rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="feather-radio fs-18"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100 position-relative overflow-hidden" style="border-top: 3px solid #10b981 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">Published This Month</small>
                                <h3 class="fw-extrabold text-success mb-0 mt-1.5 fs-22">{{ $publishedThisMonth }}</h3>
                            </div>
                            <div class="p-2.5 rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="feather-check-circle fs-18"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100 position-relative overflow-hidden" style="border-top: 3px solid #f59e0b !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">Pending Acknowledgements</small>
                                <h3 class="fw-extrabold text-warning mb-0 mt-1.5 fs-22">{{ $pendingAckCount }}</h3>
                            </div>
                            <div class="p-2.5 rounded-3 bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="feather-alert-circle fs-18"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100 position-relative overflow-hidden" style="border-top: 3px solid #06b6d4 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">Scheduled / Drafts</small>
                                <h3 class="fw-extrabold text-info mb-0 mt-1.5 fs-22">{{ $scheduledCount }}</h3>
                            </div>
                            <div class="p-2.5 rounded-3 bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="feather-clock fs-18"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs & Right Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-2">
            <!-- Left Tabs -->
            <ul class="nav gap-1" id="broadcastTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'published' ? 'active' : '' }}" href="{{ route('hrms.broadcasts.index', ['active_tab' => 'published']) }}">
                        <i class="feather-radio"></i>
                        <span>Published Announcements</span>
                        <x-ui.badge soft variant="primary" class="ms-1">{{ $publishedBroadcasts->total() }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'scheduled' ? 'active' : '' }}" href="{{ route('hrms.broadcasts.index', ['active_tab' => 'scheduled']) }}">
                        <i class="feather-clock"></i>
                        <span>Scheduled & Drafts</span>
                        <x-ui.badge soft variant="warning" class="ms-1">{{ $scheduledBroadcasts->total() }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'archived' ? 'active' : '' }}" href="{{ route('hrms.broadcasts.index', ['active_tab' => 'archived']) }}">
                        <i class="feather-archive"></i>
                        <span>Archived / Expired</span>
                        <x-ui.badge soft variant="secondary" class="ms-1">{{ $archivedBroadcasts->total() }}</x-ui.badge>
                    </a>
                </li>
            </ul>

            <!-- Right Toolbar: Search, Sort, Filter aligned at Right Corner -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <!-- Search Input Form with Instant Auto-Submit -->
                <form method="GET" action="{{ route('hrms.broadcasts.index') }}" id="broadcastSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 36px !important;">
                    <input type="hidden" name="active_tab" value="{{ $activeTab }}">
                    <input type="hidden" name="priority" value="{{ request('priority') }}">
                    <input type="hidden" name="category" value="{{ request('category') }}">
                    <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" name="search" id="broadcastSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="Search broadcast #, title..." value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; outline: none;">
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="SORT">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                        <span>Newest First</span>
                        @if(request('sort', 'newest') === 'newest') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                        <span>Oldest First</span>
                        @if(request('sort') === 'oldest') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'title_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'title_asc']) }}">
                        <span>Title (A-Z)</span>
                        @if(request('sort') === 'title_asc') <i class="feather-check ms-3"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <x-ui.filter label="FILTER">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                    <form method="GET" action="{{ route('hrms.broadcasts.index') }}">
                        <input type="hidden" name="active_tab" value="{{ $activeTab }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Priority</label>
                            <x-ui.odoo-form-ui type="select" name="priority">
                                <option value="">All Priorities</option>
                                <option value="normal" @selected(request('priority') === 'normal')>Normal</option>
                                <option value="important" @selected(request('priority') === 'important')>Important</option>
                                <option value="urgent" @selected(request('priority') === 'urgent')>Urgent / Emergency</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                            <x-ui.odoo-form-ui type="select" name="category">
                                <option value="">All Categories</option>
                                <option value="announcement" @selected(request('category') === 'announcement')>General Announcement</option>
                                <option value="policy_update" @selected(request('category') === 'policy_update')>Policy Update</option>
                                <option value="event" @selected(request('category') === 'event')>Company Event</option>
                                <option value="emergency" @selected(request('category') === 'emergency')>Emergency Alert</option>
                                <option value="news" @selected(request('category') === 'news')>News & Newsletter</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4 pt-2 border-top">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">APPLY FILTERS</x-ui.button>
                            <x-ui.button href="{{ route('hrms.broadcasts.index') }}" variant="light" size="sm" class="border flex-grow-1">RESET</x-ui.button>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        <!-- TAB CONTENT SLOTS -->
        @if($activeTab === 'published')

            <!-- PUBLISHED BROADCASTS TABLE -->
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">Broadcast #</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Title & Category</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Target Audience</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Priority</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Read & Ack Rate</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Published At</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($publishedBroadcasts as $bc)
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    <a href="{{ route('hrms.broadcasts.show', $bc->id) }}" class="text-decoration-none text-primary">
                                        {{ $bc->broadcast_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-13">
                                        <a href="{{ route('hrms.broadcasts.show', $bc->id) }}" class="text-decoration-none text-dark">
                                            {{ $bc->title }}
                                        </a>
                                    </div>
                                    <small class="text-muted fs-11 text-capitalize">
                                        <i class="feather-tag me-1 text-primary"></i> {{ str_replace('_', ' ', $bc->category) }}
                                    </small>
                                </td>
                                <td>
                                    <x-ui.badge soft variant="secondary" class="text-capitalize">
                                        <i class="feather-users me-1"></i> {{ str_replace('_', ' ', $bc->target_type) }}
                                    </x-ui.badge>
                                </td>
                                <td>
                                    @php
                                        $prioBadge = match($bc->priority) {
                                            'urgent' => 'danger',
                                            'important' => 'warning',
                                            default => 'info'
                                        };
                                    @endphp
                                    <x-ui.badge soft variant="{{ $prioBadge }}" class="text-capitalize">
                                        {{ $bc->priority }}
                                    </x-ui.badge>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2" style="max-width: 140px;">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: {{ $bc->read_percentage }}%;"></div>
                                        </div>
                                        <span class="fs-11 fw-bold text-muted">{{ $bc->read_percentage }}%</span>
                                    </div>
                                    @if($bc->is_acknowledgement_required)
                                        <small class="text-primary fs-10 d-block mt-0.5"><i class="feather-check-square me-1"></i> Ack: {{ $bc->acknowledgement_percentage }}%</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="fs-12 text-secondary">{{ $bc->published_at ? $bc->published_at->format('M d, Y H:i') : 'N/A' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown id="bcActions{{ $bc->id }}">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hrms.broadcasts.show', $bc->id) }}">
                                                <i class="feather-eye me-2 text-primary"></i> View Announcement
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmAction('Are you sure you want to delete broadcast {{ $bc->broadcast_number }}? This will remove all delivery receipts and comments.', function() { document.getElementById('deleteBcForm{{ $bc->id }}').submit(); }, { title: 'Delete Broadcast', confirmText: 'Yes, Delete', variant: 'danger' });">
                                                <i class="feather-trash-2 me-2"></i> Delete Broadcast
                                            </a>
                                        </li>
                                    </x-ui.action-dropdown>
                                    <form id="deleteBcForm{{ $bc->id }}" action="{{ route('hrms.broadcasts.destroy', $bc->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-radio fs-34 d-block mb-2 text-secondary opacity-50"></i>
                                    No published announcements found. Click <strong>Create Broadcast</strong> to post a new announcement.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($publishedBroadcasts->total() > 0)
                <x-ui.pagination
                    class="mt-3"
                    :current-page="$publishedBroadcasts->currentPage()"
                    :total-pages="$publishedBroadcasts->lastPage()"
                    :total-results="$publishedBroadcasts->total()"
                    :per-page="$publishedBroadcasts->perPage()"
                    page-param="published_page"
                    tab="published"
                />
            @endif

        @elseif($activeTab === 'scheduled')

            <!-- SCHEDULED & DRAFTS TABLE -->
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">Broadcast #</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Title & Category</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Target Audience</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Priority</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Status</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Scheduled For</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($scheduledBroadcasts as $bc)
                            <tr>
                                <td class="ps-3 fw-bold text-primary">{{ $bc->broadcast_number }}</td>
                                <td>
                                    <div class="fw-bold text-dark fs-13">{{ $bc->title }}</div>
                                    <small class="text-muted fs-11 text-capitalize">{{ str_replace('_', ' ', $bc->category) }}</small>
                                </td>
                                <td><x-ui.badge soft variant="secondary" class="text-capitalize">{{ str_replace('_', ' ', $bc->target_type) }}</x-ui.badge></td>
                                <td><x-ui.badge soft variant="info" class="text-capitalize">{{ $bc->priority }}</x-ui.badge></td>
                                <td><x-ui.badge soft variant="warning" class="text-capitalize">{{ $bc->status }}</x-ui.badge></td>
                                <td>{{ $bc->scheduled_at ? $bc->scheduled_at->format('M d, Y H:i') : 'Immediate' }}</td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown id="bcSchedActions{{ $bc->id }}">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hrms.broadcasts.show', $bc->id) }}">
                                                <i class="feather-eye me-2 text-primary"></i> View Details
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmAction('Delete this scheduled broadcast?', function() { document.getElementById('deleteBcSchedForm{{ $bc->id }}').submit(); }, { title: 'Delete Scheduled Broadcast', confirmText: 'Yes, Delete', variant: 'danger' });">
                                                <i class="feather-trash-2 me-2"></i> Delete
                                            </a>
                                        </li>
                                    </x-ui.action-dropdown>
                                    <form id="deleteBcSchedForm{{ $bc->id }}" action="{{ route('hrms.broadcasts.destroy', $bc->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No scheduled or draft broadcasts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($scheduledBroadcasts->total() > 0)
                <x-ui.pagination
                    class="mt-3"
                    :current-page="$scheduledBroadcasts->currentPage()"
                    :total-pages="$scheduledBroadcasts->lastPage()"
                    :total-results="$scheduledBroadcasts->total()"
                    :per-page="$scheduledBroadcasts->perPage()"
                    page-param="scheduled_page"
                    tab="scheduled"
                />
            @endif

        @elseif($activeTab === 'archived')

            <!-- ARCHIVED TABLE -->
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">Broadcast #</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Title</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Category</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Status</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Expired / Archived At</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($archivedBroadcasts as $bc)
                            <tr>
                                <td class="ps-3 fw-bold text-muted">{{ $bc->broadcast_number }}</td>
                                <td>{{ $bc->title }}</td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', $bc->category) }}</td>
                                <td><x-ui.badge soft variant="secondary">{{ $bc->status }}</x-ui.badge></td>
                                <td>{{ $bc->expires_at ? $bc->expires_at->format('M d, Y') : $bc->updated_at->format('M d, Y') }}</td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown id="bcArchActions{{ $bc->id }}">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hrms.broadcasts.show', $bc->id) }}">
                                                <i class="feather-eye me-2 text-primary"></i> View Archived
                                            </a>
                                        </li>
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No archived broadcasts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($archivedBroadcasts->total() > 0)
                <x-ui.pagination
                    class="mt-3"
                    :current-page="$archivedBroadcasts->currentPage()"
                    :total-pages="$archivedBroadcasts->lastPage()"
                    :total-results="$archivedBroadcasts->total()"
                    :per-page="$archivedBroadcasts->perPage()"
                    page-param="archived_page"
                    tab="archived"
                />
            @endif

        @endif

    </div>
</div>

<!-- CREATE BROADCAST MODAL -->
<div class="modal fade" id="createBroadcastModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-15"><i class="feather-plus-circle me-1.5 text-primary"></i> Create Broadcast Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.broadcasts.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Announcement Title" name="title" placeholder="e.g. Annual Company Gala & Policy Update..." :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Category" name="category" :required="true">
                                <option value="announcement" selected>General Announcement</option>
                                <option value="policy_update">Policy Update</option>
                                <option value="event">Company Event</option>
                                <option value="emergency">Emergency Alert</option>
                                <option value="news">News & Newsletter</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Priority Level" name="priority" :required="true">
                                <option value="normal" selected>Normal Priority</option>
                                <option value="important">Important (High Visibility)</option>
                                <option value="urgent">Urgent / Emergency Alert</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Target Audience Scope" name="target_type" id="targetTypeSelect" :required="true">
                                <option value="all" selected>All Active Employees (Company-wide)</option>
                                <option value="department">By Department</option>
                                <option value="branch">By Office Branch</option>
                                <option value="designation">By Designation / Role</option>
                                <option value="specific_employees">Select Specific Employees</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Dynamic Target Selection Container -->
                        <div class="col-12 d-none" id="targetSelectionContainer">
                            <x-ui.odoo-form-ui type="select" label="Select Target Items" name="target_ids[]" id="targetIdsSelect" :multiple="true" :searchable="true">
                                <!-- JS Populated -->
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Announcement Content" name="content" rows="4" placeholder="Write announcement details..." :required="true" />
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="file" label="Attachment Document (PDF/Doc)" name="attachment" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="file" label="Header Graphic Banner (Image)" name="banner_image" />
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="datetime-local" label="Schedule Release (Optional)" name="scheduled_at" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Expiration Date (Optional)" name="expires_at" />
                        </div>

                        <div class="col-12 border-top pt-3 mt-2">
                            <h6 class="fw-bold text-dark fs-12 mb-2">Delivery Channels & Interactivity Flags</h6>
                            <div class="d-flex flex-wrap gap-4">
                                <x-ui.checkbox label="Require Read Acknowledgement" name="is_acknowledgement_required" :checked="false" />
                                <x-ui.checkbox label="Allow Employee Comments & Q&A" name="allow_comments" :checked="true" />
                                <x-ui.checkbox label="Show Dashboard Banner Alert" name="show_banner" :checked="true" />
                                <x-ui.checkbox label="Send Email Dispatch" name="send_email" :checked="false" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2.5">
                    <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">Publish Broadcast</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<x-ui.confirmation-modal />

@push('scripts')
<script>
    $(document).ready(function() {
        if ($('#createBroadcastModal').parent().get(0) !== document.body) {
            $('#createBroadcastModal').appendTo(document.body);
        }

        // Instant Auto Search without pressing Enter key
        let bcSearchDebounceTimer;
        $(document).on('input', '#broadcastSearchInput', function() {
            clearTimeout(bcSearchDebounceTimer);
            bcSearchDebounceTimer = setTimeout(function() {
                $('#broadcastSearchForm').submit();
            }, 450);
        });

        // Target audience selection toggle
        const departments = @json($departments);
        const branches = @json($branches);
        const designations = @json($designations);
        const employees = @json($employees);

        function updateTargetSelection() {
            const val = $('#targetTypeSelect').val();
            const container = $('#targetSelectionContainer');
            const select = $('#targetIdsSelect');
            const labelEl = container.find('.odoo-form-label');

            select.empty();

            if (val === 'all') {
                container.addClass('d-none');
            } else {
                container.removeClass('d-none');
                let labelText = 'Select Target Items';
                if (val === 'department') {
                    labelText = 'Select Departments';
                    departments.forEach(d => select.append(`<option value="${d.id}">${d.name}</option>`));
                } else if (val === 'branch') {
                    labelText = 'Select Office Branches';
                    branches.forEach(b => select.append(`<option value="${b.id}">${b.name}</option>`));
                } else if (val === 'designation') {
                    labelText = 'Select Designations';
                    designations.forEach(ds => select.append(`<option value="${ds.id}">${ds.name}</option>`));
                } else if (val === 'specific_employees') {
                    labelText = 'Select Targeted Employees';
                    employees.forEach(e => select.append(`<option value="${e.id}">${e.full_name} (${e.employee_id})</option>`));
                }

                if (labelEl.length) {
                    labelEl.html(labelText + ' <span class="text-danger">*</span>');
                }

                if ($.fn.select2) {
                    if (!select.hasClass('select2-hidden-accessible')) {
                        select.select2({
                            theme: "bootstrap-5",
                            width: "100%",
                            dropdownParent: $('#createBroadcastModal')
                        });
                    } else {
                        select.trigger('change.select2');
                    }
                }
            }
        }

        $(document).on('change', '#targetTypeSelect', updateTargetSelection);
    });

    $(document).on('show.bs.modal', '.modal', function () {
        if ($(this).parent().get(0) !== document.body) {
            $(this).appendTo(document.body);
        }
    });
</script>
@endpush
@endsection
