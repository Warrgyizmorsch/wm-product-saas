@extends('layouts.duralux')

@section('title', 'Lead Kanban Pipeline | CRM | SaaS ERP')
@section('page-title', 'Lead Kanban Pipeline')
@section('breadcrumb', 'CRM > Pipeline Kanban')

@push('styles')
<style>
    .kanban-board-container {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: 1.5rem;
        min-height: calc(100vh - 220px);
    }
    .kanban-column {
        flex: 0 0 320px;
        min-width: 320px;
        background-color: #f8fafc;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 220px);
    }
    .kanban-column-header {
        padding: 0.85rem 1rem;
        background: #ffffff;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .kanban-cards-body {
        padding: 0.75rem;
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        min-height: 150px;
    }
    .kanban-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.85rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        cursor: grab;
        transition: all 0.2s ease-in-out;
    }
    .kanban-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-color: #cbd5e1;
        transform: translateY(-2px);
    }
    .kanban-card:active {
        cursor: grabbing;
    }
    .drag-over {
        background-color: #f1f5f9 !important;
        border: 2px dashed #3b82f6 !important;
    }

    /* Star Rating Widget — Kanban Cards */
    .star-rating-widget {
        cursor: pointer;
        user-select: none;
    }
    .star-rating-widget .star-icon {
        font-size: 13px;
        color: #cbd5e1;
        fill: transparent;
        transition: transform 0.15s ease, color 0.15s ease, fill 0.15s ease;
    }
    .star-rating-widget .star-icon.active-star {
        color: #f59e0b;
        fill: #f59e0b;
    }
    .star-rating-widget .star-icon.hovered-star {
        color: #f59e0b !important;
        fill: #f59e0b !important;
        transform: scale(1.25);
    }
</style>
@endpush

@section('page-actions')
    <x-ui.button href="{{ route('crm.leads.create') }}" variant="primary" icon="feather-plus">
        {{ __('crm.add_new_call_lead') }}
    </x-ui.button>
@endsection

@section('content')
<div class="erp-single-panel text-dark">

    <!-- Toolbar: View Switcher & Custom Filter Component (100% Mobile Responsive) -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h5 class="fw-bold text-dark mb-0 me-2">Pipeline Kanban</h5>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <!-- Icon View Switcher (Common System Component) -->
            <x-ui.view-switcher />

            <!-- Common Filter Component (Identical to Lead Listing) -->
            <form method="GET" action="{{ route('crm.leads.kanban') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keywords') }}</label>
                        <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('crm.search_placeholder_leads')" value="{{ request('search') }}" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.priority') }}</label>
                        <x-ui.odoo-form-ui type="select" name="priority">
                            <option value="">{{ __('crm.all_priorities') }}</option>
                            <option value="Low" {{ request('priority') === 'Low' ? 'selected' : '' }}>{{ __('crm.priorities.Low') }}</option>
                            <option value="Medium" {{ request('priority') === 'Medium' ? 'selected' : '' }}>{{ __('crm.priorities.Medium') }}</option>
                            <option value="High" {{ request('priority') === 'High' ? 'selected' : '' }}>{{ __('crm.priorities.High') }}</option>
                            <option value="Urgent" {{ request('priority') === 'Urgent' ? 'selected' : '' }}>{{ __('crm.priorities.Urgent') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.segment') }}</label>
                        <x-ui.odoo-form-ui type="select" name="segment">
                            <option value="">{{ __('crm.all_segments') }}</option>
                            <option value="SME" {{ request('segment') === 'SME' ? 'selected' : '' }}>{{ __('crm.segments.SME') }}</option>
                            <option value="Mid-Market" {{ request('segment') === 'Mid-Market' ? 'selected' : '' }}>{{ __('crm.segments.Mid-Market') }}</option>
                            <option value="Enterprise" {{ request('segment') === 'Enterprise' ? 'selected' : '' }}>{{ __('crm.segments.Enterprise') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date From</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') ?? request('start_date') }}" />
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date To</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') ?? request('end_date') }}" />
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('crm.leads.kanban') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') }}</a>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') }}</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

    <!-- Kanban Board Container -->
    <div class="kanban-board-container">
        @php
            $columnConfigs = [
                'Lost' => ['color' => '#ef4444', 'badge' => 'bg-soft-danger text-danger', 'title' => 'Lost'],
                'New' => ['color' => '#3b82f6', 'badge' => 'bg-soft-primary text-primary', 'title' => 'New Leads'],
                'Qualified' => ['color' => '#14b8a6', 'badge' => 'bg-soft-teal text-teal', 'title' => 'Qualified'],
                'Won' => ['color' => '#22c55e', 'badge' => 'bg-soft-success text-success', 'title' => 'Won'],
            ];
        @endphp

        @foreach($statuses as $status)
            @php
                $config = $columnConfigs[$status] ?? ['color' => '#64748b', 'badge' => 'bg-soft-secondary text-secondary', 'title' => $status];
                $columnData = $kanbanData[$status] ?? ['leads' => collect(), 'count' => 0, 'total_amount' => 0];
                $leads = $columnData['leads'];
            @endphp
            <div class="kanban-column" data-status="{{ $status }}">
                <!-- Column Header -->
                <div class="kanban-column-header" style="border-top: 3.5px solid {{ $config['color'] }};">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="fw-bold fs-13 text-dark d-flex align-items-center gap-1.5">
                            {{ $config['title'] }}
                        </span>
                        <span class="badge {{ $config['badge'] }} rounded-pill px-2 py-1 fs-11 col-count">
                            {{ $columnData['count'] }}
                        </span>
                    </div>
                    <div class="fs-11 text-muted fw-semibold mt-1">
                        Total Value: <span class="text-dark font-monospace col-total">₹ {{ number_format($columnData['total_amount'], 2) }}</span>
                    </div>
                </div>

                <!-- Cards Body (Droppable) -->
                <div class="kanban-cards-body" id="kanban_col_{{ Str::slug($status) }}">
                    @forelse($leads as $lead)
                        @php
                            $expAmt = (float)($lead->expected_amount ?: ($lead->quotations->last()?->total_amount ?: 0));
                            $kStarsMap = ['Low' => 1, 'Medium' => 2, 'High' => 3, 'Urgent' => 4];
                            $kStarsCount = $kStarsMap[$lead->priority] ?? 0;
                            $kBadgeClass = match($lead->priority) {
                                'Low'    => 'bg-soft-success text-success',
                                'Medium' => 'bg-soft-warning text-warning',
                                'High'   => 'bg-soft-danger text-danger',
                                'Urgent' => 'bg-danger text-white',
                                default  => 'bg-soft-secondary text-secondary',
                            };
                            $kStarLabels = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
                        @endphp
                        <div class="kanban-card" draggable="true" data-lead-id="{{ $lead->id }}" data-company-name="{{ addslashes($lead->company_name) }}" data-is-qualified="{{ $lead->is_qualified ? '1' : '0' }}" data-amount="{{ $expAmt }}">
                            <!-- Lead Title & Value -->
                            <div class="d-flex align-items-start justify-content-between mb-1.5">
                                <a href="{{ route('crm.leads.show', $lead->id) }}" class="fw-bold text-dark fs-13 hover-underline text-truncate me-2" style="max-width: 190px;" title="{{ $lead->company_name }}">
                                    {{ $lead->company_name }}
                                </a>
                                <span class="badge bg-soft-success text-success fs-10 font-monospace fw-bold">
                                    ₹ {{ number_format($expAmt, 0) }}
                                </span>
                            </div>

                            <!-- Contact Info -->
                            <div class="fs-11 text-muted mb-2">
                                <div class="text-truncate mb-0.5"><i class="feather-user me-1 text-secondary"></i>{{ $lead->contact_name ?: 'No Contact Name' }}</div>
                                @if($lead->phone)
                                    <div><i class="feather-phone me-1 text-secondary"></i><a href="tel:{{ $lead->phone }}" class="text-muted">{{ $lead->phone }}</a></div>
                                @endif
                            </div>

                            <!-- Priority Stars & Actions Footer -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <!-- Star Rating Widget -->
                                <div class="d-flex align-items-center gap-1">
                                    <div class="star-rating-widget d-inline-flex align-items-center gap-1" id="starRating_{{ $lead->id }}" data-current-stars="{{ $kStarsCount }}" data-current-priority="{{ $lead->priority }}">
                                        @for($i = 1; $i <= 4; $i++)
                                            @php $targetPriority = $kStarLabels[$i]; @endphp
                                            <i class="feather-star star-icon {{ $i <= $kStarsCount ? 'active-star' : 'inactive-star' }}"
                                               data-star="{{ $i }}"
                                               data-priority="{{ $targetPriority }}"
                                               data-lead-id="{{ $lead->id }}"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top"
                                               title="{{ $targetPriority }} ({{ $i }}★)"
                                               onclick="updateLeadPriority({{ $lead->id }}, '{{ $targetPriority }}', this)"></i>
                                        @endfor
                                    </div>
                                    <span class="badge fs-10 ms-1 priority-badge-{{ $lead->id }} {{ $kBadgeClass }}">
                                        {{ $lead->priority ?: 'Unset' }}
                                    </span>
                                </div>

                                <div class="d-flex align-items-center gap-1">
                                    <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}" class="btn btn-xs btn-link text-muted p-0 border-0 me-1" title="Edit Lead">
                                        <i class="feather-edit fs-12"></i>
                                    </a>
                                    <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-xs btn-outline-primary py-0 px-1.5 fs-10 fw-semibold">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted fs-12 empty-col-msg opacity-75">
                            <i class="feather-inbox fs-3 d-block mb-1"></i>No leads in {{ $status }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let draggedCard = null;
        let originalParent = null;
        let originalNextSibling = null;

        // Drag Start & End
        document.querySelectorAll('.kanban-card').forEach(card => {
            card.addEventListener('dragstart', function (e) {
                draggedCard = this;
                originalParent = this.parentElement;
                originalNextSibling = this.nextElementSibling;
                this.style.opacity = '0.5';
                e.dataTransfer.effectAllowed = 'move';
            });

            card.addEventListener('dragend', function () {
                this.style.opacity = '1';
                draggedCard = null;
                document.querySelectorAll('.kanban-cards-body').forEach(col => col.classList.remove('drag-over'));
            });
        });

        // Column Drop Listeners
        document.querySelectorAll('.kanban-cards-body').forEach(cardsBody => {
            cardsBody.addEventListener('dragover', function (e) {
                e.preventDefault();
                this.classList.add('drag-over');
            });

            cardsBody.addEventListener('dragleave', function () {
                this.classList.remove('drag-over');
            });

            cardsBody.addEventListener('drop', function (e) {
                e.preventDefault();
                this.classList.remove('drag-over');

                if (!draggedCard) return;

                const targetCol = this.closest('.kanban-column');
                const newStatus = targetCol.getAttribute('data-status');
                const leadId = draggedCard.getAttribute('data-lead-id');
                const companyName = draggedCard.getAttribute('data-company-name') || ('Lead #' + leadId);
                const isQualified = draggedCard.getAttribute('data-is-qualified') === '1';

                // Guard 1: Client-Side Instant Qualification Guard for Direct Conversion
                if (newStatus === 'Won' && !isQualified) {
                    confirmAction({
                        title: 'Action Blocked: Qualification Required',
                        message: 'Lead "' + companyName + '" (# ' + leadId + ') cannot be marked directly as "Won" because it is not Qualified yet! Please qualify the lead first.',
                        variant: 'warning',
                        confirmText: 'Got It'
                    });
                    return;
                }

                // Append card temporarily to new column
                const emptyMsg = this.querySelector('.empty-col-msg');
                if (emptyMsg) emptyMsg.remove();
                this.appendChild(draggedCard);
                recalculateTotals();

                // AJAX Update Status in DB
                fetch(`/crm/leads/${leadId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                .then(async res => {
                    const data = await res.json();

                    if (!res.ok || data.success === false) {
                        // Revert card immediately to original column!
                        if (originalNextSibling) {
                            originalParent.insertBefore(draggedCard, originalNextSibling);
                        } else {
                            originalParent.appendChild(draggedCard);
                        }
                        recalculateTotals();

                        // Display instant warning modal
                        confirmAction({
                            title: 'Status Change Reverted',
                            message: data.message || 'Unable to update lead status. Action has been reverted.',
                            variant: 'danger',
                            confirmText: 'Got It'
                        });
                    }
                })
                .catch(err => {
                    // Revert card immediately on network error
                    if (originalNextSibling) {
                        originalParent.insertBefore(draggedCard, originalNextSibling);
                    } else {
                        originalParent.appendChild(draggedCard);
                    }
                    recalculateTotals();

                    confirmAction({
                        title: 'Network Error',
                        message: 'Failed to communicate with server. Lead status update was reverted.',
                        variant: 'danger',
                        confirmText: 'Got It'
                    });
                });
            });
        });

        function recalculateTotals() {
            document.querySelectorAll('.kanban-column').forEach(col => {
                const cards = col.querySelectorAll('.kanban-card');
                const countBadge = col.querySelector('.col-count');
                const totalSpan = col.querySelector('.col-total');

                let totalVal = 0;
                cards.forEach(card => {
                    totalVal += parseFloat(card.getAttribute('data-amount')) || 0;
                });

                if (countBadge) countBadge.textContent = cards.length;
                if (totalSpan) totalSpan.textContent = '₹ ' + totalVal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
        }
    });

    // ── Star Rating: Update Priority via AJAX ──
    window.updateLeadPriority = function(leadId, priority, el) {
        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        fetch('/crm/leads/' + leadId + '/priority', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ priority: priority })
        })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                var widget = document.getElementById('starRating_' + leadId);
                var starsCount = priority === 'Low' ? 1 : (priority === 'Medium' ? 2 : (priority === 'High' ? 3 : 4));
                widget.setAttribute('data-current-stars', starsCount);
                widget.setAttribute('data-current-priority', priority);

                widget.querySelectorAll('.star-icon').forEach(function(icon) {
                    var s = parseInt(icon.getAttribute('data-star'));
                    if (s <= starsCount) {
                        icon.classList.add('active-star'); icon.classList.remove('inactive-star');
                    } else {
                        icon.classList.add('inactive-star'); icon.classList.remove('active-star');
                    }
                });

                var badge = document.querySelector('.priority-badge-' + leadId);
                if (badge) {
                    badge.textContent = priority;
                    badge.className = 'badge fs-10 ms-1 priority-badge-' + leadId;
                    if (priority === 'Low')         badge.classList.add('bg-soft-success', 'text-success');
                    else if (priority === 'Medium') badge.classList.add('bg-soft-warning', 'text-warning');
                    else if (priority === 'High')   badge.classList.add('bg-soft-danger',  'text-danger');
                    else if (priority === 'Urgent') badge.classList.add('bg-danger',        'text-white');
                }
            }
        })
        .catch(function(err) { console.error('Priority update failed:', err); });
    };

    // ── Star Hover Effect ──
    document.addEventListener('mouseover', function(e) {
        if (!e.target.classList.contains('star-icon')) return;
        var hoveredStar = parseInt(e.target.getAttribute('data-star'));
        var widget = e.target.closest('.star-rating-widget');
        if (!widget) return;
        widget.querySelectorAll('.star-icon').forEach(function(icon) {
            var s = parseInt(icon.getAttribute('data-star'));
            if (s <= hoveredStar) icon.classList.add('hovered-star');
            else icon.classList.remove('hovered-star');
        });
    });
    document.addEventListener('mouseout', function(e) {
        if (!e.target.classList.contains('star-icon')) return;
        var widget = e.target.closest('.star-rating-widget');
        if (widget) widget.querySelectorAll('.star-icon').forEach(i => i.classList.remove('hovered-star'));
    });

    // Tooltip init for kanban stars
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            new bootstrap.Tooltip(el);
        });
    });
</script>
@endpush
