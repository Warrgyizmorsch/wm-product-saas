@extends('layouts.duralux')

@section('title', __('hrms.broadcasts.page_title') . ' | HRMS')
@section('page-title', __('hrms.broadcasts.page_title'))
@section('breadcrumb', __('hrms.broadcasts.breadcrumb'))

@section('page-actions')
    @if($isHrAdmin)
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createBroadcastModal" class="fw-bold text-uppercase">
            {{ __('hrms.broadcasts.btn_create_broadcast') }}
        </x-ui.button>
    </div>
    @endif
@endsection

@push('styles')
<style>
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
@php
    $categoryLabels = [
        'announcement'  => __('hrms.broadcasts.category_announcement'),
        'policy_update' => __('hrms.broadcasts.category_policy_update'),
        'event'         => __('hrms.broadcasts.category_event'),
        'emergency'     => __('hrms.broadcasts.category_emergency'),
        'news'          => __('hrms.broadcasts.category_news'),
    ];
    $priorityLabels = [
        'normal'    => __('hrms.broadcasts.priority_normal'),
        'important' => __('hrms.broadcasts.priority_important'),
        'urgent'    => __('hrms.broadcasts.priority_urgent'),
    ];
    $audienceLabels = [
        'all'                => __('hrms.broadcasts.audience_all'),
        'department'         => __('hrms.broadcasts.audience_department'),
        'branch'             => __('hrms.broadcasts.audience_branch'),
        'designation'        => __('hrms.broadcasts.audience_designation'),
        'specific_employees' => __('hrms.broadcasts.audience_specific_employees'),
    ];
    $statusLabels = [
        'published' => __('hrms.broadcasts.status_published'),
        'scheduled' => __('hrms.broadcasts.status_scheduled'),
        'draft'     => __('hrms.broadcasts.status_draft'),
        'archived'  => __('hrms.broadcasts.status_archived'),
    ];

    $broadcastTabs = [
        [
            'id'     => 'tab-published',
            'label'  => __('hrms.broadcasts.tab_published'),
            'icon'   => 'feather-radio',
            'url'    => route('hrms.broadcasts.index', ['active_tab' => 'published']),
            'active' => $activeTab === 'published',
            'badge'  => $publishedBroadcasts->total(),
        ],
    ];
    if ($isHrAdmin) {
        $broadcastTabs[] = [
            'id'     => 'tab-scheduled',
            'label'  => __('hrms.broadcasts.tab_scheduled'),
            'icon'   => 'feather-clock',
            'url'    => route('hrms.broadcasts.index', ['active_tab' => 'scheduled']),
            'active' => $activeTab === 'scheduled',
            'badge'  => $scheduledBroadcasts->total(),
        ];
    }
    $broadcastTabs[] = [
        'id'     => 'tab-archived',
        'label'  => __('hrms.broadcasts.tab_archived'),
        'icon'   => 'feather-archive',
        'url'    => route('hrms.broadcasts.index', ['active_tab' => 'archived']),
        'active' => $activeTab === 'archived',
        'badge'  => $archivedBroadcasts->total(),
    ];
@endphp

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
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">{{ __('hrms.broadcasts.stat_active_published') }}</small>
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
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">{{ __('hrms.broadcasts.stat_published_month') }}</small>
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
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">{{ __('hrms.broadcasts.stat_pending_ack') }}</small>
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
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">{{ __('hrms.broadcasts.stat_scheduled_drafts') }}</small>
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
            <!-- Left Common UI Horizontal Tabs -->
            <div class="flex-grow-1">
                <x-ui.horizontal-tabs id="broadcastTabs" :tabs="$broadcastTabs" class="mb-0 border-bottom-0 pb-0" />
            </div>

            <!-- Right Toolbar: Search, Sort, Filter aligned at Right Corner -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <!-- Search Input Form with Instant Auto-Submit -->
                <form method="GET" action="{{ route('hrms.broadcasts.index') }}" id="broadcastSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 36px !important;">
                    <input type="hidden" name="active_tab" value="{{ $activeTab }}">
                    <input type="hidden" name="priority" value="{{ request('priority') }}">
                    <input type="hidden" name="category" value="{{ request('category') }}">
                    <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" name="search" id="broadcastSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.broadcasts.search_placeholder') }}" value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; outline: none;">
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown :label="__('hrms.broadcasts.sort_label')">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                        <span>{{ __('hrms.broadcasts.sort_newest') }}</span>
                        @if(request('sort', 'newest') === 'newest') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                        <span>{{ __('hrms.broadcasts.sort_oldest') }}</span>
                        @if(request('sort') === 'oldest') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'title_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'title_asc']) }}">
                        <span>{{ __('hrms.broadcasts.sort_title_asc') }}</span>
                        @if(request('sort') === 'title_asc') <i class="feather-check ms-3"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <x-ui.filter :label="__('hrms.broadcasts.filter_label')">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.broadcasts.filter_options') }}</h6>
                    <form method="GET" action="{{ route('hrms.broadcasts.index') }}">
                        <input type="hidden" name="active_tab" value="{{ $activeTab }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.broadcasts.field_priority') }}</label>
                            <x-ui.odoo-form-ui type="select" name="priority">
                                <option value="">{{ __('hrms.broadcasts.all_priorities') }}</option>
                                <option value="normal" @selected(request('priority') === 'normal')>{{ __('hrms.broadcasts.priority_normal') }}</option>
                                <option value="important" @selected(request('priority') === 'important')>{{ __('hrms.broadcasts.priority_important') }}</option>
                                <option value="urgent" @selected(request('priority') === 'urgent')>{{ __('hrms.broadcasts.priority_urgent') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.broadcasts.field_category') }}</label>
                            <x-ui.odoo-form-ui type="select" name="category">
                                <option value="">{{ __('hrms.broadcasts.all_categories') }}</option>
                                <option value="announcement" @selected(request('category') === 'announcement')>{{ __('hrms.broadcasts.category_announcement') }}</option>
                                <option value="policy_update" @selected(request('category') === 'policy_update')>{{ __('hrms.broadcasts.category_policy_update') }}</option>
                                <option value="event" @selected(request('category') === 'event')>{{ __('hrms.broadcasts.category_event') }}</option>
                                <option value="emergency" @selected(request('category') === 'emergency')>{{ __('hrms.broadcasts.category_emergency') }}</option>
                                <option value="news" @selected(request('category') === 'news')>{{ __('hrms.broadcasts.category_news') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4 pt-2 border-top">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.broadcasts.btn_apply_filters') }}</x-ui.button>
                            <x-ui.button href="{{ route('hrms.broadcasts.index') }}" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.broadcasts.btn_reset') }}</x-ui.button>
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
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_broadcast_num') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_title_category') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_target_audience') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_priority') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_read_ack_rate') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_published_at') }}</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_actions') }}</th>
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
                                    <small class="text-muted fs-11">
                                        <i class="feather-tag me-1 text-primary"></i> {{ $categoryLabels[$bc->category] ?? str_replace('_', ' ', $bc->category) }}
                                    </small>
                                </td>
                                <td>
                                    <x-ui.badge soft variant="secondary">
                                        <i class="feather-users me-1"></i> {{ $audienceLabels[$bc->target_type] ?? str_replace('_', ' ', $bc->target_type) }}
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
                                    <x-ui.badge soft variant="{{ $prioBadge }}">
                                        {{ $priorityLabels[$bc->priority] ?? $bc->priority }}
                                    </x-ui.badge>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2" style="max-width: 140px;">
                                        <div class="flex-grow-1">
                                            <x-ui.progress-bar :value="$bc->read_percentage" color="success" height="6px" />
                                        </div>
                                        <span class="fs-11 fw-bold text-muted">{{ $bc->read_percentage }}%</span>
                                    </div>
                                    @if($bc->is_acknowledgement_required)
                                        <small class="text-primary fs-10 d-block mt-0.5"><i class="feather-check-square me-1"></i> {{ __('hrms.broadcasts.ack_rate_label', ['rate' => $bc->acknowledgement_percentage]) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="fs-12 text-secondary">{{ $bc->published_at ? $bc->published_at->format('M d, Y H:i') : 'N/A' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown id="bcActions{{ $bc->id }}">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hrms.broadcasts.show', $bc->id) }}">
                                                <i class="feather-eye me-2 text-primary"></i> {{ __('hrms.broadcasts.btn_view_announcement') }}
                                            </a>
                                        </li>
                                        @if($isHrAdmin)
                                        <li>
                                            <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmAction(@json(__('hrms.broadcasts.confirm_delete_broadcast', ['code' => $bc->broadcast_number])), function() { document.getElementById('deleteBcForm{{ $bc->id }}').submit(); }, { title: @json(__('hrms.broadcasts.btn_delete_broadcast')), confirmText: @json(__('hrms.broadcasts.btn_confirm_delete')), variant: 'danger' });">
                                                <i class="feather-trash-2 me-2"></i> {{ __('hrms.broadcasts.btn_delete_broadcast') }}
                                            </a>
                                        </li>
                                        @endif
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
                                    {!! __('hrms.broadcasts.no_published_found') !!}
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
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_broadcast_num') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_title_category') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_target_audience') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_priority') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_status') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_scheduled_for') }}</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($scheduledBroadcasts as $bc)
                            <tr>
                                <td class="ps-3 fw-bold text-primary">{{ $bc->broadcast_number }}</td>
                                <td>
                                    <div class="fw-bold text-dark fs-13">{{ $bc->title }}</div>
                                    <small class="text-muted fs-11">{{ $categoryLabels[$bc->category] ?? str_replace('_', ' ', $bc->category) }}</small>
                                </td>
                                <td><x-ui.badge soft variant="secondary">{{ $audienceLabels[$bc->target_type] ?? str_replace('_', ' ', $bc->target_type) }}</x-ui.badge></td>
                                <td><x-ui.badge soft variant="info">{{ $priorityLabels[$bc->priority] ?? $bc->priority }}</x-ui.badge></td>
                                <td><x-ui.badge soft variant="warning">{{ $statusLabels[$bc->status] ?? $bc->status }}</x-ui.badge></td>
                                <td>{{ $bc->scheduled_at ? $bc->scheduled_at->format('M d, Y H:i') : __('hrms.broadcasts.immediate') }}</td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown id="bcSchedActions{{ $bc->id }}">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hrms.broadcasts.show', $bc->id) }}">
                                                <i class="feather-eye me-2 text-primary"></i> {{ __('hrms.broadcasts.btn_view_details') }}
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmAction(@json(__('hrms.broadcasts.confirm_delete_broadcast', ['code' => $bc->broadcast_number])), function() { document.getElementById('deleteBcSchedForm{{ $bc->id }}').submit(); }, { title: @json(__('hrms.broadcasts.btn_delete_broadcast')), confirmText: @json(__('hrms.broadcasts.btn_confirm_delete')), variant: 'danger' });">
                                                <i class="feather-trash-2 me-2"></i> {{ __('hrms.broadcasts.btn_delete_broadcast') }}
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
                                <td colspan="7" class="text-center py-4 text-muted">{{ __('hrms.broadcasts.no_scheduled_found') }}</td>
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
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_broadcast_num') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_title') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_category') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_status') }}</th>
                            <th class="py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_expired_at') }}</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">{{ __('hrms.broadcasts.th_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($archivedBroadcasts as $bc)
                            <tr>
                                <td class="ps-3 fw-bold text-muted">{{ $bc->broadcast_number }}</td>
                                <td>{{ $bc->title }}</td>
                                <td>{{ $categoryLabels[$bc->category] ?? str_replace('_', ' ', $bc->category) }}</td>
                                <td><x-ui.badge soft variant="secondary">{{ $statusLabels[$bc->status] ?? $bc->status }}</x-ui.badge></td>
                                <td>{{ $bc->expires_at ? $bc->expires_at->format('M d, Y') : $bc->updated_at->format('M d, Y') }}</td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown id="bcArchActions{{ $bc->id }}">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hrms.broadcasts.show', $bc->id) }}">
                                                <i class="feather-eye me-2 text-primary"></i> {{ __('hrms.broadcasts.btn_view_archived') }}
                                            </a>
                                        </li>
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">{{ __('hrms.broadcasts.no_archived_found') }}</td>
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

@if($isHrAdmin)
<!-- CREATE BROADCAST MODAL -->
<x-ui.modal
    id="createBroadcastModal"
    :title="'<i class=\'feather-plus-circle me-1.5 text-primary\'></i> ' . __('hrms.broadcasts.modal_create_title')"
    size="lg"
    :centered="true"
    :showFooter="false"
>
    <form action="{{ route('hrms.broadcasts.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.broadcasts.field_title')" name="title" :placeholder="__('hrms.broadcasts.placeholder_title')" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.broadcasts.field_category')" name="category" :required="true">
                    <option value="announcement" selected>{{ __('hrms.broadcasts.category_announcement') }}</option>
                    <option value="policy_update">{{ __('hrms.broadcasts.category_policy_update') }}</option>
                    <option value="event">{{ __('hrms.broadcasts.category_event') }}</option>
                    <option value="emergency">{{ __('hrms.broadcasts.category_emergency') }}</option>
                    <option value="news">{{ __('hrms.broadcasts.category_news') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.broadcasts.field_priority')" name="priority" :required="true">
                    <option value="normal" selected>{{ __('hrms.broadcasts.priority_normal') }}</option>
                    <option value="important">{{ __('hrms.broadcasts.priority_important') }}</option>
                    <option value="urgent">{{ __('hrms.broadcasts.priority_urgent') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.broadcasts.field_target_scope')" name="target_type" id="targetTypeSelect" :required="true">
                    <option value="all" selected>{{ __('hrms.broadcasts.audience_all') }}</option>
                    <option value="department">{{ __('hrms.broadcasts.audience_department') }}</option>
                    <option value="branch">{{ __('hrms.broadcasts.audience_branch') }}</option>
                    <option value="designation">{{ __('hrms.broadcasts.audience_designation') }}</option>
                    <option value="specific_employees">{{ __('hrms.broadcasts.audience_specific_employees') }}</option>
                </x-ui.odoo-form-ui>
            </div>

            <!-- Dynamic Target Selection Container -->
            <div class="col-12 d-none" id="targetSelectionContainer">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.broadcasts.field_target_items')" name="target_ids[]" id="targetIdsSelect" :multiple="true" :searchable="true">
                    <!-- JS Populated -->
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.broadcasts.field_content')" name="content" rows="4" :placeholder="__('hrms.broadcasts.placeholder_content')" :required="true" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="file" :label="__('hrms.broadcasts.field_attachment')" name="attachment" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="file" :label="__('hrms.broadcasts.field_banner')" name="banner_image" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="datetime-local" :label="__('hrms.broadcasts.field_scheduled_at')" name="scheduled_at" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.broadcasts.field_expires_at')" name="expires_at" />
            </div>

            <div class="col-12 border-top pt-3 mt-2">
                <h6 class="fw-bold text-dark fs-12 mb-2">{{ __('hrms.broadcasts.section_delivery_flags') }}</h6>
                <div class="d-flex flex-wrap gap-4">
                    <x-ui.checkbox :label="__('hrms.broadcasts.flag_require_ack')" name="is_acknowledgement_required" :checked="false" />
                    <x-ui.checkbox :label="__('hrms.broadcasts.flag_allow_comments')" name="allow_comments" :checked="true" />
                    <x-ui.checkbox :label="__('hrms.broadcasts.flag_show_banner')" name="show_banner" :checked="true" />
                    <x-ui.checkbox :label="__('hrms.broadcasts.flag_send_email')" name="send_email" :checked="false" />
                </div>
            </div>
        </div>

        <div class="modal-footer border-top bg-light py-2.5 px-0 mt-4 mb-n3 mx-n3">
            <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.broadcasts.btn_cancel') }}</x-ui.button>
            <x-ui.button type="submit" name="status" value="draft" variant="light" size="sm" class="fw-bold border text-dark">{{ __('hrms.broadcasts.btn_save_draft') }}</x-ui.button>
            <x-ui.button type="submit" name="status" value="published" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.broadcasts.btn_publish_broadcast') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endif

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
                let labelText = @json(__('hrms.broadcasts.field_target_items'));
                if (val === 'department') {
                    labelText = @json(__('hrms.broadcasts.target_departments'));
                    departments.forEach(d => select.append(`<option value="${d.id}">${d.name}</option>`));
                } else if (val === 'branch') {
                    labelText = @json(__('hrms.broadcasts.target_branches'));
                    branches.forEach(b => select.append(`<option value="${b.id}">${b.name}</option>`));
                } else if (val === 'designation') {
                    labelText = @json(__('hrms.broadcasts.target_designations'));
                    designations.forEach(ds => select.append(`<option value="${ds.id}">${ds.name}</option>`));
                } else if (val === 'specific_employees') {
                    labelText = @json(__('hrms.broadcasts.target_employees'));
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
