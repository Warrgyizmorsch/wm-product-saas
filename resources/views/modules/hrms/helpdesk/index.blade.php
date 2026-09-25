@extends('layouts.duralux')

@section('title', 'Helpdesk & Support | HRMS')
@section('page-title', 'Helpdesk & Employee Support')
@section('breadcrumb', 'HRMS / Support & Helpdesk')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-book-open" href="{{ route('hrms.helpdesk.kb.index') }}">
            Knowledge Base
        </x-ui.button>

        @if($canManage)
            <x-ui.button variant="outline-secondary" icon="feather-settings" href="{{ route('hrms.helpdesk.categories.index') }}">
                Category & SLA Rules
            </x-ui.button>
        @endif

        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTicketModal" class="fw-bold">
            Submit Ticket
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    #helpdeskTabs .nav-link {
        border: none !important;
        background-color: transparent !important;
        color: #64748b;
        font-weight: 500;
        padding: 12px 18px;
        border-bottom: 2px solid transparent !important;
        transition: all 0.2s ease-in-out;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    #helpdeskTabs .nav-link:hover {
        color: var(--bs-primary);
    }
    #helpdeskTabs .nav-link.active {
        color: var(--bs-primary) !important;
        border-bottom: 2px solid var(--bs-primary) !important;
        font-weight: 600;
    }

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
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="feather-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif



    <!-- ERP Single Panel Container -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs border-0" id="helpdeskTabs">
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'all' ? 'active' : '' }}" href="{{ route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'all'])) }}">
                        <i class="feather-inbox"></i> All Tickets
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'my_tickets' ? 'active' : '' }}" href="{{ route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'my_tickets'])) }}">
                        <i class="feather-user"></i> My Tickets
                    </a>
                </li>
                @if($canManage)
                    <li class="nav-item">
                        <a class="nav-link {{ $currentTab === 'assigned_to_me' ? 'active' : '' }}" href="{{ route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'assigned_to_me'])) }}">
                            <i class="feather-user-check"></i> Assigned to Me
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $currentTab === 'unassigned' ? 'active' : '' }}" href="{{ route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'unassigned'])) }}">
                            <i class="feather-help-circle"></i> Unassigned
                        </a>
                    </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'overdue' ? 'active' : '' }}" href="{{ route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'overdue'])) }}">
                        <i class="feather-clock text-danger"></i> Overdue SLA
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $currentTab === 'resolved' ? 'active' : '' }}" href="{{ route('hrms.helpdesk.tickets.index', array_merge(request()->query(), ['tab' => 'resolved'])) }}">
                        <i class="feather-check-square text-success"></i> Resolved
                    </a>
                </li>
            </ul>

            <!-- Common Toolbar: Search, Sort & Filter Dropdown (Standard UI Component Pattern) -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap pb-3">
                <!-- Search Input Form with Instant Auto-Submit -->
                <form method="GET" action="{{ route('hrms.helpdesk.tickets.index') }}" id="helpdeskSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 36px !important; box-sizing: border-box !important;">
                    <input type="hidden" name="tab" value="{{ $currentTab }}">
                    <input type="hidden" name="category_id" value="{{ request('category_id') }}">
                    <input type="hidden" name="priority" value="{{ request('priority') }}">
                    <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" name="search" id="helpdeskSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="Search ticket #, subject, requester..." value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                </form>

                <!-- Custom Sort Dropdown Component -->
                <x-ui.sort-dropdown label="SORT">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                        <span>Recently Updated</span>
                        @if(request('sort', 'newest') === 'newest') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                        <span>Oldest Created</span>
                        @if(request('sort') === 'oldest') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'priority_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'priority_desc']) }}">
                        <span>Priority (Highest First)</span>
                        @if(request('sort') === 'priority_desc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'due_soon' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'due_soon']) }}">
                        <span>SLA Due Soonest</span>
                        @if(request('sort') === 'due_soon') <i class="feather-check ms-3"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Dropdown Component -->
                <x-ui.filter label="FILTER">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                    <form method="GET" action="{{ route('hrms.helpdesk.tickets.index') }}">
                        <input type="hidden" name="tab" value="{{ $currentTab }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="sort" value="{{ request('sort') }}">

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                            <x-ui.odoo-form-ui type="select" name="category_id">
                                <option value="">All Categories</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Priority</label>
                            <x-ui.odoo-form-ui type="select" name="priority">
                                <option value="">All Priorities</option>
                                <option value="low" @selected(request('priority') === 'low')>Low</option>
                                <option value="medium" @selected(request('priority') === 'medium')>Medium</option>
                                <option value="high" @selected(request('priority') === 'high')>High</option>
                                <option value="urgent" @selected(request('priority') === 'urgent')>Urgent</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4 pt-2 border-top">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">APPLY FILTERS</x-ui.button>
                            <x-ui.button href="{{ route('hrms.helpdesk.tickets.index', ['tab' => $currentTab]) }}" variant="light" size="sm" class="border flex-grow-1">RESET</x-ui.button>
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
                            <th class="ps-3" style="width: 12%;">Ticket #</th>
                            <th style="width: 22%;">Subject & Category</th>
                            <th style="width: 16%;">Requester</th>
                            <th style="width: 15%;">Assigned To</th>
                            <th class="text-center text-nowrap" style="width: 8%;">Priority</th>
                            <th class="text-center text-nowrap" style="width: 10%;">Status</th>
                            <th class="text-center text-nowrap" style="width: 10%;">SLA Due</th>
                            <th class="text-end pe-3 text-nowrap" style="width: 7%;">Action</th>
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
                                        <x-ui.badge variant="danger" soft class="ms-1" title="Confidential Ticket">
                                            <i class="feather-lock"></i>
                                        </x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-bold text-dark fs-13">{{ $ticket->subject }}</div>
                                    <small class="text-muted"><i class="feather-tag me-1"></i>{{ $ticket->category ? $ticket->category->name : 'General' }}</small>
                                </td>
                                <td>
                                     <div class="d-flex align-items-center gap-2">
                                         <div class="avatar-initials flex-shrink-0">
                                             {{ strtoupper(substr($ticket->employee->first_name ?? 'E', 0, 1) . substr($ticket->employee->last_name ?? '', 0, 1)) }}
                                         </div>
                                         <div>
                                             <div class="fw-bold text-dark fs-13 lh-sm">{{ $ticket->employee ? ($ticket->employee->full_name ?: $ticket->employee->first_name . ' ' . $ticket->employee->last_name) : 'N/A' }}</div>
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
                                         <span class="badge bg-secondary-subtle text-secondary fs-12">Unassigned</span>
                                     @endif
                                 </td>
                                <td class="text-center">
                                    @if($ticket->priority === 'urgent')
                                        <x-ui.badge variant="danger" soft class="fw-bold text-uppercase">{{ $ticket->priority }}</x-ui.badge>
                                    @elseif($ticket->priority === 'high')
                                        <x-ui.badge variant="warning" soft class="fw-bold text-uppercase">{{ $ticket->priority }}</x-ui.badge>
                                    @elseif($ticket->priority === 'medium')
                                        <x-ui.badge variant="info" soft class="fw-bold text-uppercase">{{ $ticket->priority }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft class="fw-bold text-uppercase">{{ $ticket->priority }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($ticket->status === 'open')
                                        <x-ui.badge variant="primary" soft class="fw-bold">{{ strtoupper($ticket->status) }}</x-ui.badge>
                                    @elseif($ticket->status === 'in_progress')
                                        <x-ui.badge variant="warning" soft class="fw-bold">IN PROGRESS</x-ui.badge>
                                    @elseif($ticket->status === 'pending_employee')
                                        <x-ui.badge variant="info" soft class="fw-bold">PENDING EMPLOYEE</x-ui.badge>
                                    @elseif($ticket->status === 'resolved')
                                        <x-ui.badge variant="success" soft class="fw-bold">RESOLVED</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft class="fw-bold">CLOSED</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(in_array($ticket->status, ['resolved', 'closed']))
                                        <span class="text-success small fw-semibold text-nowrap"><i class="feather-check me-1"></i> Completed</span>
                                    @elseif($ticket->isOverdue())
                                        <x-ui.badge variant="danger" title="Overdue Target: {{ $ticket->due_at ? $ticket->due_at->format('M d, H:i') : '' }}">
                                            <i class="feather-alert-circle me-1"></i> Overdue
                                        </x-ui.badge>
                                    @else
                                        <small class="text-muted text-nowrap">{{ $ticket->due_at ? $ticket->due_at->diffForHumans() : 'N/A' }}</small>
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
                                        <p class="mb-1 fw-bold">No helpdesk tickets found</p>
                                        <small>Submit a ticket or change your filter selection above.</small>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-end mt-4">
                {{ $tickets->withQueryString()->links() }}
            </div>
    </div>
</div>

<!-- Modal: Submit Ticket -->
<x-ui.modal id="createTicketModal" title="<i class='feather-life-buoy me-2'></i> Submit Helpdesk Ticket" size="lg" formAction="{{ route('hrms.helpdesk.tickets.store') }}" submitText="Submit Ticket" centered>
    <div class="row g-3">
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="select" label="Category" name="category_id" :required="true">
                <option value="">-- Select Category --</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }} (SLA: {{ $cat->default_sla_hours }}h)</option>
                @endforeach
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="select" label="Priority" name="priority" :required="true">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-12">
            <x-ui.odoo-form-ui type="input" label="Subject / Short Summary" id="ticketSubjectInput" name="subject" placeholder="e.g. Discrepancy in August Payslip Tax Calculation" :required="true" />

            <!-- Live Ticket Deflection Container -->
            <div id="deflectionContainer" class="mt-2 d-none">
                <div class="card bg-light border-info">
                    <div class="card-body p-3">
                        <div class="fw-bold text-info small mb-1"><i class="feather-help-circle me-1"></i> Frequently Asked Articles (Click to resolve query without ticket):</div>
                        <ul id="deflectionList" class="mb-0 ps-3 small text-dark"></ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Detailed Description <span class="text-danger">*</span></label>
            <textarea name="description" class="form-control" rows="5" placeholder="Please describe your issue or query clearly with any relevant context..." required></textarea>
        </div>
        <div class="col-12">
            <x-ui.odoo-form-ui type="file" label="Attachments (Optional)" name="attachments[]" :required="false" />
            <small class="text-muted d-block mt-1">Max file size: 10MB per attachment (PDF, Images, DOCX, ZIP).</small>
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
                                        <a href="/hrms/helpdesk/kb/${art.slug}" target="_blank" class="fw-semibold text-primary text-decoration-none">${art.title}</a>
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
