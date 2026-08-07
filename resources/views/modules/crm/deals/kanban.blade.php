@extends('layouts.duralux')

@section('title', 'Deals Kanban Pipeline | SaaS ERP')
@section('page-title', 'Deals Pipeline (Kanban Board)')
@section('breadcrumb', 'Deals Kanban')

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('crm.deals.index') }}" class="btn btn-light">
            <i class="feather-list me-1"></i>List View
        </a>
        <a href="{{ route('crm.deals.create') }}" class="btn btn-primary">
            <i class="feather-plus me-1"></i>New Deal
        </a>
    </div>
@endsection

@section('content')
    <div class="card border-0 shadow-sm p-4 bg-white mb-4">
        <!-- Kanban Columns Container -->
        <div class="row flex-nowrap overflow-auto g-3 pb-3" style="min-height: 600px;">
            @foreach($kanbanData as $stageKey => $data)
                @php
                    $info = $data['info'];
                    $deals = $data['deals'];
                    $totalVal = $data['total'];
                @endphp
                <div class="col" style="min-width: 260px; max-width: 320px;">
                    <div class="bg-light rounded-3 p-3 h-100 border kanban-column" data-stage="{{ $stageKey }}" ondragover="allowDrop(event)" ondrop="dropDeal(event, '{{ $stageKey }}')">
                        <!-- Column Header -->
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-2 border-{{ $info['color'] }}">
                            <div>
                                <span class="fw-bold text-dark fs-14">{{ $info['label'] }}</span>
                                <span class="badge bg-soft-{{ $info['color'] }} text-{{ $info['color'] }} rounded-circle ms-1">{{ $deals->count() }}</span>
                            </div>
                            <span class="fs-12 fw-bold text-success">₹{{ number_format($totalVal, 0) }}</span>
                        </div>

                        <!-- Cards Body -->
                        <div class="d-flex flex-column gap-3 kanban-cards-container">
                            @forelse($deals as $deal)
                                <div class="card border shadow-2xs rounded-3 p-3 bg-white kanban-deal-card cursor-grab" 
                                     id="deal-card-{{ $deal->id }}" 
                                     draggable="true" 
                                     ondragstart="dragDeal(event, {{ $deal->id }})">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-monospace fs-11 text-muted">{{ $deal->deal_number }}</span>
                                        <span class="badge bg-light text-dark border fs-11">{{ $info['prob'] }}%</span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1 fs-13">
                                        <a href="{{ route('crm.deals.show', $deal) }}" class="text-dark text-decoration-none hover-primary">
                                            {{ $deal->title }}
                                        </a>
                                    </h6>
                                    @if($deal->account)
                                        <div class="text-muted fs-12 mb-2">
                                            <i class="feather-briefcase me-1"></i>{{ $deal->account->name }}
                                        </div>
                                    @endif
                                    <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                                        <span class="fw-bold text-success fs-13">₹{{ number_format($deal->actual_value, 2) }}</span>
                                        @if($deal->closing_date)
                                            <span class="text-muted fs-11"><i class="feather-calendar me-1"></i>{{ \Illuminate\Support\Carbon::parse($deal->closing_date)->format('d M') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted fs-12 border border-dashed rounded-3 opacity-75">
                                    No deals in {{ $info['label'] }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Modal: Mark Deal Closed Won / Closed Lost -->
    <x-ui.modal id="closeReasonModal" title="Mark Deal Outcome" :centered="true" :showFooter="false">
        <input type="hidden" id="modalDealId">
        <input type="hidden" id="modalTargetStage">
        <div class="mb-3">
            <label class="form-label fw-semibold">Select Reason / Notes</label>
            <select id="modalCloseReasonSelect" class="form-select mb-2">
                <!-- Populated dynamically -->
            </select>
            <textarea id="modalCloseNotes" class="form-control" rows="2" placeholder="Additional notes or competitor info..."></textarea>
        </div>
        <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary px-4" id="saveCloseReasonBtn">Save & Change Stage</button>
        </div>
    </x-ui.modal>
@endsection

@push('scripts')
    <script>
        let draggedDealId = null;

        function dragDeal(ev, dealId) {
            draggedDealId = dealId;
            ev.dataTransfer.setData("text", dealId);
        }

        function allowDrop(ev) {
            ev.preventDefault();
        }

        function dropDeal(ev, targetStage) {
            ev.preventDefault();
            if (!draggedDealId) return;

            if (targetStage === 'Lost' || targetStage === 'Closed Lost') {
                $('#modalDealId').val(draggedDealId);
                $('#modalTargetStage').val(targetStage);
                $('#closeReasonModalLabel, #closeReasonModalTitle').text('Mark Deal as Lost');
                
                const $select = $('#modalCloseReasonSelect').empty();
                $select.append('<option value="Lost to Competitor">Lost to Competitor</option>');
                $select.append('<option value="Price Too High">Price Too High</option>');
                $select.append('<option value="No Budget / Project Cancelled">No Budget / Project Cancelled</option>');
                $select.append('<option value="Unresponsive">No Response / Unresponsive</option>');
                
                $('#closeReasonModal').modal('show');
            } else {
                updateDealStage(draggedDealId, targetStage, null);
            }
        }

        $('#saveCloseReasonBtn').on('click', function() {
            const dealId = $('#modalDealId').val();
            const targetStage = $('#modalTargetStage').val();
            const reason = $('#modalCloseReasonSelect').val() + ($('#modalCloseNotes').val() ? ' - ' + $('#modalCloseNotes').val() : '');
            
            updateDealStage(dealId, targetStage, reason);
            $('#closeReasonModal').modal('hide');
        });

        function updateDealStage(dealId, stage, closeReason) {
            $.ajax({
                url: `/crm/deals/${dealId}/stage`,
                type: 'PATCH',
                data: {
                    _token: '{{ csrf_token() }}',
                    stage: stage,
                    close_reason: closeReason
                },
                success: function(res) {
                    if (res.success) {
                        window.location.reload();
                    }
                },
                error: function(err) {
                    const msg = err.responseJSON && err.responseJSON.message 
                        ? err.responseJSON.message 
                        : 'Cannot update deal stage. A Quotation must be Accepted before marking Deal as Won.';
                    alert('⚠️ ' + msg);
                    window.location.reload();
                }
            });
        }
    </script>
@endpush
