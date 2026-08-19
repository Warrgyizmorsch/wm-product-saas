@extends('layouts.duralux')

@section('title', 'Deals Kanban Pipeline | CRM | SaaS ERP')
@section('page-title', 'Deals Pipeline Kanban')
@section('breadcrumb', 'CRM > Deals Kanban')

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
</style>
@endpush

@section('page-actions')
    <x-ui.button href="{{ route('crm.deals.create') }}" variant="primary" icon="feather-plus">
        New Deal
    </x-ui.button>
@endsection

@section('content')
<div class="erp-single-panel text-dark">

    <!-- Toolbar: View Switcher & Search (Matching Lead Kanban) -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h5 class="fw-bold text-dark mb-0 me-2">Deals Pipeline Kanban</h5>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <!-- Outside Search Box -->
            <form method="GET" action="{{ route('crm.deals.kanban') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                @foreach(request()->except(['search', 'page']) as $k => $v)
                    @if(is_scalar($v) && $v !== '')
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach
                <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search deals by title, account..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                @if(request('search'))
                    <a href="{{ route('crm.deals.kanban', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                        <i class="feather-x fs-12"></i>
                    </a>
                @endif
            </form>

            <!-- Icon View Switcher -->
            <x-ui.view-switcher />

            <!-- Common Filter Component -->
            <form method="GET" action="{{ route('crm.deals.kanban') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Deals</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="Search title or company..." value="{{ request('search') }}" />
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date From</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date To</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('crm.deals.kanban') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

    <!-- Kanban Board Container -->
    <div class="kanban-board-container">
        @php
            $stageHeaderConfigs = [
                'Qualification'  => ['color' => '#3b82f6', 'badge' => 'bg-soft-primary text-primary',   'title' => 'Qualification (10%)'],
                'Needs Analysis' => ['color' => '#06b6d4', 'badge' => 'bg-soft-info text-info',          'title' => 'Needs Analysis (30%)'],
                'Proposal'       => ['color' => '#f59e0b', 'badge' => 'bg-soft-warning text-warning',   'title' => 'Proposal (60%)'],
                'Negotiation'    => ['color' => '#8b5cf6', 'badge' => 'bg-soft-purple text-purple',     'title' => 'Negotiation (80%)'],
                'Won'            => ['color' => '#22c55e', 'badge' => 'bg-soft-success text-success',   'title' => 'Won (100%)'],
                'Lost'           => ['color' => '#ef4444', 'badge' => 'bg-soft-danger text-danger',     'title' => 'Lost (0%)'],
            ];
        @endphp

        @foreach($stages as $stageKey => $info)
            @php
                $config = $stageHeaderConfigs[$stageKey] ?? [
                    'color' => '#3b82f6',
                    'badge' => 'bg-soft-primary text-primary',
                    'title' => $info['label'] . ' (' . ($info['prob'] ?? 50) . '%)'
                ];
                $columnData = $kanbanData[$stageKey] ?? ['deals' => collect(), 'total' => 0];
                $deals = $columnData['deals'];
            @endphp
            <div class="kanban-column" data-stage="{{ $stageKey }}">
                <!-- Column Header -->
                <div class="kanban-column-header" style="border-top: 3.5px solid {{ $config['color'] }};">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="fw-bold fs-13 text-dark d-flex align-items-center gap-1.5">
                            {{ $config['title'] }}
                        </span>
                        <span class="badge {{ $config['badge'] }} rounded-pill px-2 py-1 fs-11 col-count">
                            {{ $deals->count() }}
                        </span>
                    </div>
                    <div class="fs-11 text-muted fw-semibold mt-1">
                        Total Value: <span class="text-dark font-monospace col-total">₹ {{ number_format($columnData['total'], 2) }}</span>
                    </div>
                </div>

                <!-- Cards Body (Droppable) -->
                <div class="kanban-cards-body" id="kanban_col_{{ Str::slug($stageKey) }}">
                    @forelse($deals as $deal)
                        @php
                            $dealValue = (float)($deal->actual_value ?: $deal->estimated_value);
                            $hasAcceptedQuote = $deal->quotations()->where('status', 'Accepted')->exists() ? '1' : '0';
                            $isWonOrLost = in_array($deal->stage, ['Won', 'Closed Won', 'Lost', 'Closed Lost']);
                        @endphp
                        <div class="kanban-card" 
                             draggable="true" 
                             data-deal-id="{{ $deal->id }}" 
                             data-deal-title="{{ addslashes($deal->title) }}" 
                             data-has-accepted-quote="{{ $hasAcceptedQuote }}" 
                             data-amount="{{ $dealValue }}">
                            
                            <!-- Deal Title & Amount -->
                            <div class="d-flex align-items-start justify-content-between mb-1.5">
                                <a href="{{ route('crm.deals.show', $deal->id) }}" class="fw-bold text-dark fs-13 hover-underline text-truncate me-2" style="max-width: 180px;" title="{{ $deal->title }}">
                                    {{ $deal->title }}
                                </a>
                                <span class="badge bg-soft-success text-success fs-10 font-monospace fw-bold">
                                    ₹ {{ number_format($dealValue, 0) }}
                                </span>
                            </div>

                            <!-- Subhead Info -->
                            <div class="fs-11 text-muted mb-2">
                                <div class="text-truncate mb-0.5">
                                    <span class="font-monospace text-primary fw-bold">{{ $deal->deal_number }}</span>
                                </div>
                                @if($deal->account)
                                    <div class="text-truncate mb-0.5">
                                        <i class="feather-briefcase me-1 text-secondary"></i><strong class="text-dark">{{ $deal->account->name }}</strong>
                                    </div>
                                @endif
                                @if($deal->contact)
                                    <div class="text-truncate"><i class="feather-user me-1 text-secondary"></i>{{ $deal->contact->name }}</div>
                                @endif
                            </div>

                            <!-- Footer Details & Actions -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top fs-11 text-muted">
                                <div>
                                    @if($deal->closing_date)
                                        <i class="feather-calendar me-1 text-secondary"></i>{{ \Illuminate\Support\Carbon::parse($deal->closing_date)->format('d M Y') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    @if(!$isWonOrLost)
                                        <a href="{{ route('crm.deals.edit', $deal->id) }}" class="text-secondary hover-primary px-1" title="Edit Deal">
                                            <i class="feather-edit fs-12"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('crm.deals.show', $deal->id) }}" class="text-secondary hover-primary px-1" title="View Profile">
                                        <i class="feather-external-link fs-12"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted fs-12 border border-dashed rounded-3 opacity-75 empty-col-msg">
                            No deals in {{ $info['label'] }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Modal: Mark Deal Closed Lost -->
<x-ui.modal id="closeReasonModal" title="Mark Deal as Lost" :centered="true" :showFooter="false">
    <input type="hidden" id="modalDealId">
    <input type="hidden" id="modalTargetStage">
    <div class="mb-3">
        <label class="form-label fw-semibold fs-12 text-dark">Select Reason / Notes for Lost Deal</label>
        <select id="modalCloseReasonSelect" class="form-select mb-2 fs-13">
            <option value="Lost to Competitor">Lost to Competitor</option>
            <option value="Price Too High">Price Too High</option>
            <option value="No Budget / Project Cancelled">No Budget / Project Cancelled</option>
            <option value="Unresponsive">No Response / Unresponsive</option>
        </select>
        <textarea id="modalCloseNotes" class="form-control fs-13" rows="2" placeholder="Additional notes or competitor details..."></textarea>
    </div>
    <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-3">
        <button type="button" class="btn btn-sm btn-light border" id="cancelCloseReasonBtn" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger px-4" id="saveCloseReasonBtn">Confirm & Change Stage</button>
    </div>
</x-ui.modal>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let draggedCard = null;
        let originalParent = null;
        let originalNextSibling = null;
        let pendingTargetStage = null;
        let pendingTargetColBody = null;

        // Attach HTML5 Drag & Drop Listeners
        document.querySelectorAll('.kanban-card').forEach(card => {
            card.addEventListener('dragstart', function (e) {
                draggedCard = this;
                originalParent = this.parentNode;
                originalNextSibling = this.nextSibling;
                this.style.opacity = '0.5';
                e.dataTransfer.setData('text/plain', this.getAttribute('data-deal-id'));
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
                const newStage = targetCol.getAttribute('data-stage');
                const dealId = draggedCard.getAttribute('data-deal-id');
                const dealTitle = draggedCard.getAttribute('data-deal-title') || ('Deal #' + dealId);
                const hasAcceptedQuote = draggedCard.getAttribute('data-has-accepted-quote') === '1';

                // Guard 1: Client-Side Instant Guard for Won Stage
                if (newStage === 'Won' && !hasAcceptedQuote) {
                    if (typeof confirmAction === 'function') {
                        confirmAction({
                            title: 'Action Blocked: Quotation Required',
                            message: 'Deal "' + dealTitle + '" cannot be marked as "Won" directly because no Quotation has been Accepted yet! A Quotation must be created and Accepted first.',
                            variant: 'warning',
                            confirmText: 'Got It'
                        });
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Action Blocked', 'Deal cannot be marked as Won directly. A Quotation must be created and Accepted first.', 'warning');
                    } else {
                        alert('Deal cannot be marked as Won directly. A Quotation must be created and Accepted first.');
                    }
                    return;
                }

                // Modal Check for Lost Stage
                if (newStage === 'Lost') {
                    pendingTargetStage = newStage;
                    pendingTargetColBody = this;
                    $('#modalDealId').val(dealId);
                    $('#modalTargetStage').val(newStage);
                    $('#closeReasonModal').modal('show');
                    return;
                }

                // Execute Stage Update without full page refresh
                processStageUpdate(draggedCard, this, dealId, newStage, null, originalParent, originalNextSibling);
            });
        });

        // Handle Lost Reason Modal Confirm
        $('#saveCloseReasonBtn').on('click', function() {
            const dealId = $('#modalDealId').val();
            const targetStage = $('#modalTargetStage').val();
            const reason = $('#modalCloseReasonSelect').val() + ($('#modalCloseNotes').val() ? ' - ' + $('#modalCloseNotes').val() : '');

            $('#closeReasonModal').modal('hide');

            if (draggedCard && pendingTargetColBody) {
                processStageUpdate(draggedCard, pendingTargetColBody, dealId, targetStage, reason, originalParent, originalNextSibling);
            }
        });

        // If Lost modal is cancelled, reset
        $('#closeReasonModal').on('hidden.bs.modal', function () {
            pendingTargetStage = null;
            pendingTargetColBody = null;
        });

        function processStageUpdate(card, targetCardsBody, dealId, newStage, closeReason, prevParent, prevNextSibling) {
            // Append card temporarily to target column
            const emptyMsg = targetCardsBody.querySelector('.empty-col-msg');
            if (emptyMsg) emptyMsg.remove();
            targetCardsBody.appendChild(card);
            recalculateTotals();

            // Send AJAX update
            fetch(`{{ url('crm/deals') }}/${dealId}/stage`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    stage: newStage,
                    close_reason: closeReason
                })
            })
            .then(async res => {
                const data = await res.json();

                if (!res.ok || data.success === false) {
                    // Revert card immediately to original column!
                    if (prevNextSibling) {
                        prevParent.insertBefore(card, prevNextSibling);
                    } else {
                        prevParent.appendChild(card);
                    }
                    recalculateTotals();

                    const errMsg = data.message || 'Unable to update deal stage. Action has been reverted.';

                    if (typeof confirmAction === 'function') {
                        confirmAction({
                            title: 'Stage Change Reverted',
                            message: errMsg,
                            variant: 'warning',
                            confirmText: 'Got It'
                        });
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Cannot Change Stage', errMsg, 'warning');
                    } else {
                        alert(errMsg);
                    }
                } else {
                    // Show success toast notification
                    if (typeof Swal !== 'undefined') {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                        Toast.fire({
                            icon: 'success',
                            title: data.message || 'Deal stage updated successfully.'
                        });
                    }
                }
            })
            .catch(err => {
                // Revert card immediately on network error
                if (prevNextSibling) {
                    prevParent.insertBefore(card, prevNextSibling);
                } else {
                    prevParent.appendChild(card);
                }
                recalculateTotals();

                if (typeof confirmAction === 'function') {
                    confirmAction({
                        title: 'Network Error',
                        message: 'Failed to communicate with server. Deal stage update was reverted.',
                        variant: 'danger',
                        confirmText: 'Got It'
                    });
                } else {
                    alert('Network Error. Deal stage update was reverted.');
                }
            });
        }

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
</script>
@endpush
