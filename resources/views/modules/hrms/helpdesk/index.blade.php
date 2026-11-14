@extends('layouts.duralux')

@section('title', __('hrms.helpdesk.title_index'))
@section('page-title', __('hrms.helpdesk.page_title_index'))
@section('breadcrumb', __('hrms.helpdesk.breadcrumb_index'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-book-open" href="{{ route('hrms.helpdesk.kb.index') }}">
            {{ __('hrms.helpdesk.btn_knowledge_base') }}
        </x-ui.button>

        @if($canManage)
            <x-ui.button variant="outline-secondary" icon="feather-settings" href="{{ route('hrms.helpdesk.categories.index') }}">
                {{ __('hrms.helpdesk.btn_category_sla_rules') }}
            </x-ui.button>
        @endif

        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTicketModal" class="fw-bold">
            {{ __('hrms.helpdesk.btn_submit_ticket') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    .avatar-initials {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
    }

    /* Helpdesk Tickets Table: Prevent horizontal scrollbar & wrap long content to next line */
    .helpdesk-table {
        width: 100% !important;
        table-layout: fixed !important;
    }
    .helpdesk-table th,
    .helpdesk-table td {
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
        white-space: normal !important;
    }
</style>
@endpush

@section('content')
@php
    $helpdeskTabs = [
        [
            'id'     => 'tab-all',
            'label'  => __('hrms.helpdesk.tab_all_tickets'),
            'icon'   => 'feather-inbox',
            'url'    => route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'all'])),
            'active' => $currentTab === 'all',
        ],
        [
            'id'     => 'tab-my-tickets',
            'label'  => __('hrms.helpdesk.tab_my_tickets'),
            'icon'   => 'feather-user',
            'url'    => route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'my_tickets'])),
            'active' => $currentTab === 'my_tickets',
        ],
    ];

    if ($canManage) {
        $helpdeskTabs[] = [
            'id'     => 'tab-assigned-to-me',
            'label'  => __('hrms.helpdesk.tab_assigned_to_me'),
            'icon'   => 'feather-user-check',
            'url'    => route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'assigned_to_me'])),
            'active' => $currentTab === 'assigned_to_me',
        ];
        $helpdeskTabs[] = [
            'id'     => 'tab-unassigned',
            'label'  => __('hrms.helpdesk.tab_unassigned'),
            'icon'   => 'feather-help-circle',
            'url'    => route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'unassigned'])),
            'active' => $currentTab === 'unassigned',
        ];
    }

    $helpdeskTabs[] = [
        'id'     => 'tab-overdue',
        'label'  => __('hrms.helpdesk.tab_overdue_sla'),
        'icon'   => 'feather-clock',
        'url'    => route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'overdue'])),
        'active' => $currentTab === 'overdue',
    ];

    $helpdeskTabs[] = [
        'id'     => 'tab-resolved',
        'label'  => __('hrms.helpdesk.tab_resolved'),
        'icon'   => 'feather-check-square',
        'url'    => route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'resolved'])),
        'active' => $currentTab === 'resolved',
    ];
@endphp

<div class="container-fluid">
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

    <!-- ERP Single Panel Container -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
            <!-- Left Common UI Horizontal Tabs -->
            <div class="flex-grow-1">
                <x-ui.horizontal-tabs id="helpdeskTabs" :tabs="$helpdeskTabs" class="mb-0 border-bottom-0 pb-0" />
            </div>

            <!-- Common Toolbar: Search, Sort & Filter Dropdown (Standard UI Component Pattern) -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <!-- Search Input Form with Instant Auto-Submit -->
                <form method="GET" action="{{ route('hrms.helpdesk.tickets.index') }}" id="helpdeskSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 36px !important; box-sizing: border-box !important;">
                    <input type="hidden" name="tab" value="{{ $currentTab }}">
                    <input type="hidden" name="category_id" value="{{ request('category_id') }}">
                    <input type="hidden" name="priority" value="{{ request('priority') }}">
                    <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" name="search" id="helpdeskSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.helpdesk.search_placeholder') }}" value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                </form>

                <!-- Custom Sort Dropdown Component -->
                <x-ui.sort-dropdown :label="__('hrms.helpdesk.sort_label')">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                        <span>{{ __('hrms.helpdesk.sort_recently_updated') }}</span>
                        @if(request('sort', 'newest') === 'newest') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                        <span>{{ __('hrms.helpdesk.sort_oldest_created') }}</span>
                        @if(request('sort') === 'oldest') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'priority_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'priority_desc']) }}">
                        <span>{{ __('hrms.helpdesk.sort_priority_desc') }}</span>
                        @if(request('sort') === 'priority_desc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'due_soon' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'due_soon']) }}">
                        <span>{{ __('hrms.helpdesk.sort_due_soon') }}</span>
                        @if(request('sort') === 'due_soon') <i class="feather-check ms-3"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Dropdown Component -->
                <x-ui.filter :label="__('hrms.helpdesk.filter_label')">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.helpdesk.filter_options') }}</h6>
                    <form method="GET" action="{{ route('hrms.helpdesk.tickets.index') }}">
                        <input type="hidden" name="tab" value="{{ $currentTab }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="sort" value="{{ request('sort') }}">

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.helpdesk.field_category') }}</label>
                            <x-ui.odoo-form-ui type="select" name="category_id">
                                <option value="">{{ __('hrms.helpdesk.all_categories') }}</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.helpdesk.field_priority') }}</label>
                            <x-ui.odoo-form-ui type="select" name="priority">
                                <option value="">{{ __('hrms.helpdesk.all_priorities') }}</option>
                                <option value="low" @selected(request('priority') === 'low')>{{ __('hrms.helpdesk.priority_low') }}</option>
                                <option value="medium" @selected(request('priority') === 'medium')>{{ __('hrms.helpdesk.priority_medium') }}</option>
                                <option value="high" @selected(request('priority') === 'high')>{{ __('hrms.helpdesk.priority_high') }}</option>
                                <option value="urgent" @selected(request('priority') === 'urgent')>{{ __('hrms.helpdesk.priority_urgent') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4 pt-2 border-top">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.helpdesk.btn_apply_filters') }}</x-ui.button>
                            <x-ui.button href="{{ route('hrms.helpdesk.tickets.index', ['tab' => $currentTab]) }}" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.helpdesk.btn_reset') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

            <!-- Datatable -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 helpdesk-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 12%;">{{ __('hrms.helpdesk.th_ticket_num') }}</th>
                            <th style="width: 22%;">{{ __('hrms.helpdesk.th_subject_category') }}</th>
                            <th style="width: 16%;">{{ __('hrms.helpdesk.th_requester') }}</th>
                            <th style="width: 15%;">{{ __('hrms.helpdesk.th_assigned_to') }}</th>
                            <th class="text-center text-nowrap" style="width: 8%;">{{ __('hrms.helpdesk.th_priority') }}</th>
                            <th class="text-center text-nowrap" style="width: 10%;">{{ __('hrms.helpdesk.th_status') }}</th>
                            <th class="text-center text-nowrap" style="width: 10%;">{{ __('hrms.helpdesk.th_sla_due') }}</th>
                            <th class="text-end pe-3 text-nowrap" style="width: 7%;">{{ __('hrms.helpdesk.th_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            <tr>
                                <td class="fw-bold ps-3">
                                    <a href="{{ route('hrms.helpdesk.tickets.show', $ticket->id) }}" class="text-decoration-none text-primary">
                                        {{ $ticket->ticket_number }}
                                    </a>
                                    @if($ticket->is_confidential)
                                        <x-ui.badge variant="danger" soft class="ms-1" :title="__('hrms.helpdesk.badge_confidential_ticket')">
                                            <i class="feather-lock"></i>
                                        </x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-13">{{ $ticket->subject }}</div>
                                    <small class="text-muted"><i class="feather-tag me-1"></i>{{ $ticket->category ? $ticket->category->name : __('hrms.helpdesk.badge_general') }}</small>
                                </td>
                                <td>
                                     <div class="d-flex align-items-center gap-2">
                                         <div class="avatar-initials flex-shrink-0">
                                             {{ strtoupper(substr($ticket->employee->first_name ?? 'E', 0, 1) . substr($ticket->employee->last_name ?? '', 0, 1)) }}
                                         </div>
                                         <div>
                                             <div class="fw-bold text-dark fs-13 lh-sm">{{ $ticket->employee ? ($ticket->employee->full_name ?: $ticket->employee->first_name . ' ' . $ticket->employee->last_name) : __('hrms.common.na') }}</div>
                                             @if($ticket->employee && ($ticket->employee->employee_id || $ticket->employee->employee_code))
                                                 <div class="text-muted fs-11 lh-xs">{{ $ticket->employee->employee_id ?: $ticket->employee->employee_code }}</div>
                                             @endif
                                         </div>
                                     </div>
                                 </td>
                                 <td>
                                     @if($ticket->assignedAgent)
                                         <div class="d-flex align-items-center gap-2">
                                             <div class="avatar-initials flex-shrink-0 bg-light text-primary" style="width: 28px; height: 28px; font-size: 11px;">
                                                 {{ strtoupper(substr($ticket->assignedAgent->first_name ?? 'A', 0, 1) . substr($ticket->assignedAgent->last_name ?? '', 0, 1)) }}
                                             </div>
                                             <span class="fw-semibold text-dark fs-13">
                                                 {{ $ticket->assignedAgent->full_name ?: ($ticket->assignedAgent->first_name . ' ' . $ticket->assignedAgent->last_name) }}
                                             </span>
                                         </div>
                                     @else
                                         <x-ui.badge variant="secondary" soft class="fs-12">{{ __('hrms.helpdesk.badge_unassigned') }}</x-ui.badge>
                                     @endif
                                 </td>
                                <td class="text-center">
                                    @if($ticket->priority === 'urgent')
                                        <x-ui.badge variant="danger" soft class="fw-bold text-uppercase">{{ __('hrms.helpdesk.priority_urgent') }}</x-ui.badge>
                                    @elseif($ticket->priority === 'high')
                                        <x-ui.badge variant="warning" soft class="fw-bold text-uppercase">{{ __('hrms.helpdesk.priority_high') }}</x-ui.badge>
                                    @elseif($ticket->priority === 'medium')
                                        <x-ui.badge variant="info" soft class="fw-bold text-uppercase">{{ __('hrms.helpdesk.priority_medium') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft class="fw-bold text-uppercase">{{ __('hrms.helpdesk.priority_low') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($ticket->status === 'open')
                                        <x-ui.badge variant="primary" soft class="fw-bold">{{ __('hrms.helpdesk.status_open') }}</x-ui.badge>
                                    @elseif($ticket->status === 'in_progress')
                                        <x-ui.badge variant="warning" soft class="fw-bold">{{ __('hrms.helpdesk.status_in_progress') }}</x-ui.badge>
                                    @elseif($ticket->status === 'pending_employee')
                                        <x-ui.badge variant="info" soft class="fw-bold">{{ __('hrms.helpdesk.status_pending_employee') }}</x-ui.badge>
                                    @elseif($ticket->status === 'resolved')
                                        <x-ui.badge variant="success" soft class="fw-bold">{{ __('hrms.helpdesk.status_resolved') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft class="fw-bold">{{ __('hrms.helpdesk.status_closed') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(in_array($ticket->status, ['resolved', 'closed']))
                                        <span class="text-success small fw-semibold text-nowrap"><i class="feather-check me-1"></i> {{ __('hrms.helpdesk.badge_completed') }}</span>
                                    @elseif($ticket->isOverdue())
                                        <x-ui.badge variant="danger" :title="__('hrms.helpdesk.overdue_target_title', ['target' => $ticket->due_at ? $ticket->due_at->format('M d, H:i') : ''])">
                                            <i class="feather-alert-circle me-1"></i> {{ __('hrms.helpdesk.badge_overdue') }}
                                        </x-ui.badge>
                                    @else
                                        <small class="text-muted text-nowrap">{{ $ticket->due_at ? $ticket->due_at->diffForHumans() : __('hrms.common.na') }}</small>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown :viewUrl="route('hrms.helpdesk.tickets.show', $ticket->id)" align="end" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="feather-inbox fs-1 d-block mb-2 text-secondary"></i>
                                        <p class="mb-1 fw-bold">{{ __('hrms.helpdesk.no_tickets_found') }}</p>
                                        <small>{{ __('hrms.helpdesk.no_tickets_found_hint') }}</small>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($tickets->total() > 0)
                <x-ui.pagination
                    class="mt-3"
                    :paginator="$tickets"
                    tab="{{ $currentTab }}"
                />
            @endif
    </div>
</div>

<!-- Modal: Submit Ticket -->
<x-ui.modal id="createTicketModal" :title="'<i class=\'feather-life-buoy me-2\'></i> ' . __('hrms.helpdesk.modal_submit_ticket_title')" size="lg" formAction="{{ route('hrms.helpdesk.tickets.store') }}" :submitText="__('hrms.helpdesk.btn_submit_ticket')" centered>
    <div class="row g-3">
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="select" :label="__('hrms.helpdesk.field_category')" name="category_id" :required="true">
                <option value="">{{ __('hrms.helpdesk.select_category_placeholder') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }} ({{ __('hrms.helpdesk.sla_hours_prefix', ['hours' => $cat->default_sla_hours]) }})</option>
                @endforeach
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="select" :label="__('hrms.helpdesk.field_priority')" name="priority" :required="true">
                <option value="low">{{ __('hrms.helpdesk.priority_low') }}</option>
                <option value="medium" selected>{{ __('hrms.helpdesk.priority_medium') }}</option>
                <option value="high">{{ __('hrms.helpdesk.priority_high') }}</option>
                <option value="urgent">{{ __('hrms.helpdesk.priority_urgent') }}</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-12">
            <x-ui.odoo-form-ui type="input" :label="__('hrms.helpdesk.field_subject_summary')" id="ticketSubjectInput" name="subject" :placeholder="__('hrms.helpdesk.placeholder_subject')" :required="true" />

            <!-- Live Ticket Deflection Container -->
            <div id="deflectionContainer" class="mt-2 d-none">
                <div class="card bg-light border-info">
                    <div class="card-body p-3">
                        <div class="fw-bold text-info small mb-1"><i class="feather-help-circle me-1"></i> {{ __('hrms.helpdesk.deflection_heading') }}</div>
                        <ul id="deflectionList" class="mb-0 ps-3 small text-dark"></ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.helpdesk.field_detailed_description')" name="description" rows="5" :placeholder="__('hrms.helpdesk.placeholder_description')" :required="true" />
        </div>
        <div class="col-12">
            <x-ui.odoo-form-ui type="file" :label="__('hrms.helpdesk.field_attachments_optional')" name="attachments[]" :required="false" />
            <small class="text-muted d-block mt-1">{{ __('hrms.helpdesk.hint_attachment_size') }}</small>
        </div>
    </div>
</x-ui.modal>

@push('scripts')
<script>
    // Live Ticket Deflection & Instant Search
    document.addEventListener('DOMContentLoaded', function() {
        const subjectInput = document.getElementById('ticketSubjectInput');
        const deflectionContainer = document.getElementById('deflectionContainer');
        const deflectionList = document.getElementById('deflectionList');

        let debounceTimer;

        if (subjectInput) {
            subjectInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                const query = this.value.trim();

                if (query.length < 3) {
                    deflectionContainer.classList.add('d-none');
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(`{{ route('hrms.helpdesk.kb.suggest') }}?q=${encodeURIComponent(query)}`)
                        .then(res => res.json())
                        .then(articles => {
                            if (articles.length > 0) {
                                deflectionList.innerHTML = articles.map(art => `
                                    <li class="mb-1">
                                        <a href="{{ url('hrms/helpdesk/kb') }}/${art.slug}" target="_blank" class="fw-semibold text-primary text-decoration-none">${art.title}</a>
                                    </li>
                                `).join('');
                                deflectionContainer.classList.remove('d-none');
                            } else {
                                deflectionContainer.classList.add('d-none');
                            }
                        })
                        .catch(() => deflectionContainer.classList.add('d-none'));
                }, 300);
            });
        }

        // Auto-submit search input on typing after delay (matching Knowledge Base exact behavior)
        const searchInput = document.getElementById('helpdeskSearchInput');
        const searchForm = document.getElementById('helpdeskSearchForm');
        let searchTimer;

        if (searchInput && searchForm) {
            // Auto-focus search input if search query is present
            if (searchInput.value) {
                searchInput.focus();
                const len = searchInput.value.length;
                searchInput.setSelectionRange(len, len);
            }

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    searchForm.submit();
                }, 500);
            });
        }
    });
</script>
@endpush
@endsection
