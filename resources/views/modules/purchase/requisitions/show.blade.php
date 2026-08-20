@extends('layouts.duralux')

@section('title', __('purchase.req_details') . ' | SaaS ERP')
@section('page-title', __('purchase.req_details'))
@section('breadcrumb')
    <a href="{{ route('purchase.requisitions.index') }}">{{ __('purchase.purchase_requests') }}</a> &gt; {{ __('purchase.view') }}
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('purchase.requisitions.index') }}"
           class="action-dropdown-btn"
           title="{{ __('purchase.back') }}"
           data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>

        @if($requisition->status === 'Draft')
            <x-ui.action-dropdown id="reqDetailsActions-{{ $requisition->id }}">
                @if($requisition->status === 'Draft')
                    <li>
                        <button type="button" class="dropdown-item py-2 text-warning fw-semibold" onclick="openRemindModal('{{ route('purchase.requisitions.remind', $requisition->id) }}', '{{ $requisition->requisition_number }}')">
                            <i class="feather-bell me-1.5 text-warning"></i> Send Quick Reminder
                        </button>
                    </li>
                @endif
                <li>
                    <a class="dropdown-item py-2" href="{{ route('purchase.requisitions.edit', $requisition->id) }}">
                        <i class="feather-edit me-1.5 text-muted"></i> {{ __('purchase.edit') }}
                    </a>
                </li>
                <li>
                    <form action="{{ route('purchase.requisitions.destroy', $requisition->id) }}" method="POST" class="d-inline" id="deletePrShowForm">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction({ title: 'Delete Requisition', message: '{{ __('purchase.confirm_delete') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deletePrShowForm').submit(); })">
                            <i class="feather-trash-2 me-1.5"></i> {{ __('purchase.delete') }}
                        </button>
                    </form>
                </li>
            </x-ui.action-dropdown>
        @endif
    </div>
@endsection

@section('content')
    <div class="erp-single-panel">
        <!-- Toast Notifications -->

        <x-ui.odoo-form-ui type="sheet" class="p-0">
            <!-- Header bar -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 px-4 pt-4 pb-3 border-bottom">
                <div>
                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">{{ __('purchase.purchase_requests') }}</span>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0">{{ $requisition->requisition_number }}</h4>
                        @php
                            $statusClass = 'warning';
                            if ($requisition->status === 'Approved') $statusClass = 'success';
                            elseif ($requisition->status === 'Cancelled') $statusClass = 'danger';
                        @endphp
                        <x-ui.badge :soft="true" :variant="$statusClass" class="px-2.5 py-1 fs-11 fw-bold">
                            {{ __('purchase.status_' . strtolower($requisition->status)) }}
                        </x-ui.badge>
                        @if($requisition->reminder_count > 0)
                            @php
                                $remData = $requisition->reminders->map(fn($r) => [
                                    'user' => $r->user->name ?? 'User',
                                    'time' => $r->created_at->format('d M Y h:i A'),
                                    'note' => $r->note
                                ]);
                            @endphp
                            <button type="button" class="btn btn-xs btn-soft-danger border border-danger-subtle px-2.5 py-1 fs-11 fw-bold"
                                    onclick="showReminderHistoryModal('{{ $requisition->requisition_number }}', {{ json_encode($remData) }})">
                                <i class="feather-bell me-1"></i>Reminded ({{ $requisition->reminder_count }})
                            </button>
                        @endif
                    </div>
                    <span class="fs-13 text-muted">
                        {{ __('purchase.requested_by') }}:&nbsp;<strong class="text-dark">{{ $requisition->requester->name ?? __('purchase.system') }}</strong>
                        &nbsp;·&nbsp;Requisition Date:&nbsp;<strong class="text-dark">{{ $requisition->requisition_date ? $requisition->requisition_date->format('d-m-Y') : '—' }}</strong>
                        @if($requisition->expected_date)
                            &nbsp;·&nbsp;Expected Date:&nbsp;<strong class="text-primary">{{ $requisition->expected_date->format('d-m-Y') }}</strong>
                        @endif
                    </span>
                </div>
            </div>

            @php
                $isSubcontractPr = in_array($requisition->source_type, ['mo', 'ProductionOrder']) 
                    && (str_contains($requisition->notes ?? '', 'Subcontract') 
                        || $requisition->items->contains(fn($i) => $i->product?->product_type === 'service')
                        || ($requisition->sourceable && method_exists($requisition->sourceable, 'operations') && $requisition->sourceable->operations->contains('is_external', true)));
            @endphp

            @if ($isSubcontractPr)
                <div class="alert alert-info border-0 border-start border-4 border-info m-4 mb-0 rounded-3 shadow-sm bg-soft-info">
                    <div class="d-flex align-items-top">
                        <i class="feather-info fs-18 text-info me-3 mt-0.5"></i>
                        <div>
                            <h6 class="fw-bold text-info mb-1">Subcontract Service Requisition</h6>
                            <p class="fs-12 text-dark mb-0">
                                This requisition purchases an external processing service. Material or WIP dispatch to the vendor is handled separately through <strong>Inventory / Stock Transfer</strong>.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($requisition->status === 'Cancelled' && !empty($requisition->rejection_reason))
                <div class="alert alert-danger border-0 border-start border-4 border-danger m-4 mb-0 rounded-3 shadow-sm bg-soft-danger">
                    <div class="d-flex align-items-top">
                        <i class="feather-x-circle fs-18 text-danger me-3 mt-0.5"></i>
                        <div>
                            <h6 class="fw-bold text-danger mb-1">Requisition Rejected / Cancelled</h6>
                            <p class="fs-13 text-dark mb-0"><strong>Reason:</strong> {{ $requisition->rejection_reason }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Summary Block and Links -->
            <div class="px-4 py-4 border-bottom bg-light-50">
                <div class="row g-4 fs-13">
                    <div class="col-md-6 border-end-md">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">{{ __('purchase.traceability') }}</h6>
                        <table class="table table-borderless table-sm mb-0 text-dark">
                            <tr>
                                <td class="text-muted ps-0" style="width: 35%">Document Purpose:</td>
                                <td>
                                    @if($isSubcontractPr)
                                        <x-ui.badge :soft="true" variant="warning" class="fs-11 text-uppercase fw-bold">
                                            <i class="feather-truck me-1"></i>Subcontract Service
                                        </x-ui.badge>
                                    @else
                                        <span class="fw-semibold text-uppercase">{{ __('purchase.source_' . $requisition->source_type) }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">{{ __('purchase.origin_document') }}:</td>
                                <td class="fw-bold">
                                    @if($requisition->source_type === 'mo' && $requisition->sourceable)
                                        <a href="{{ route('production.orders.show', $requisition->source_id) }}" class="text-primary hover-underline">
                                            <i class="feather-cpu me-1"></i>{{ $requisition->sourceable->order_number }}
                                        </a>
                                    @elseif($requisition->source_type === 'material_request' && $requisition->sourceable)
                                        <a href="{{ route('sales.material-requests.show', $requisition->source_id) }}" class="text-primary hover-underline">
                                            <i class="feather-file-text me-1"></i>{{ $requisition->sourceable->requisition_number }}
                                        </a>
                                    @elseif($requisition->source_type === 'material_requirement' && $requisition->sourceable)
                                        <a href="{{ route('sales.material-requirements.show', $requisition->source_id) }}" class="text-primary hover-underline">
                                            <i class="feather-archive me-1"></i>{{ $requisition->sourceable->requirement_number }}
                                        </a>
                                    @elseif($requisition->source_type === 'so' && $requisition->sourceable)
                                        <a href="{{ route('sales.orders.show', $requisition->source_id) }}" class="text-primary hover-underline">
                                            <i class="feather-shopping-cart me-1"></i>{{ $requisition->sourceable->sales_order_number }}
                                        </a>
                                    @elseif($requisition->source_type === 'requisition_slip')
                                        <span class="text-dark font-monospace">{{ $requisition->requisition_slip_number ?: '—' }}</span>
                                    @else
                                        <span class="text-muted">{{ __('purchase.direct_creation') }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">{{ __('purchase.notes') }}</h6>
                        <div class="text-muted bg-white p-3 border rounded" style="min-height: 80px;">
                            {!! nl2br(e($requisition->notes ?: __('purchase.no_notes'))) !!}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="px-4 py-4">
                <h5 class="fw-bold text-dark mb-3"><i class="feather-list text-primary me-2"></i>{{ __('purchase.requisition_line_items') }}</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-dark mb-0 fs-13">
                        <thead class="bg-soft-light text-uppercase fs-11 fw-semibold text-muted">
                            <tr>
                                <th style="width: 45%">{{ __('purchase.product') }}</th>
                                <th style="width: 25%">{{ __('purchase.target_warehouse') }}</th>
                                <th class="text-end" style="width: 15%">{{ __('purchase.quantity') }}</th>
                                <th class="text-end" style="width: 15%">{{ __('purchase.estimated_cost') }}</th>
                                <th class="text-end" style="width: 15%">{{ __('purchase.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $grandTotal = 0.00; @endphp
                            @foreach($requisition->items as $item)
                                @php
                                    $lineTotal = $item->quantity * $item->estimated_cost;
                                    $grandTotal += $lineTotal;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $item->product->name }}</div>
                                        <div class="text-muted fs-11">{{ __('purchase.sku') }}: {{ $item->product->sku ?: '—' }}</div>
                                    </td>
                                    <td>{{ $item->warehouse->name ?? '—' }}</td>
                                    <td class="text-end fw-semibold">{{ (float)$item->quantity }}</td>
                                    <td class="text-end">₹{{ number_format($item->estimated_cost, 2) }}</td>
                                    <td class="text-end fw-bold">₹{{ number_format($lineTotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-soft-light fw-bold text-dark">
                            <tr>
                                <td colspan="4" class="text-end text-uppercase fs-11 letter-spacing-1 text-muted">{{ __('purchase.estimated_requisition_total') }}</td>
                                <td class="text-end fs-15 text-primary">₹{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </x-ui.odoo-form-ui>
    </div>

    <!-- Send Quick Reminder Modal -->
    <div class="modal fade" id="remindModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-soft-warning border-bottom py-3">
                    <h6 class="modal-title fw-bold text-dark fs-14">
                        <i class="feather-bell text-warning me-1.5 fs-15"></i> Send Quick Approval Reminder
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="remindForm" method="POST" action="">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="alert alert-warning border border-warning-subtle py-2 px-3 fs-12 mb-3">
                            <i class="feather-info me-1"></i>
                            Sending an in-app reminder for document <strong id="remindDocNumberText" class="text-dark"></strong>.
                        </div>
                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold text-dark fs-12 mb-1">Optional Note / Message for Approver</label>
                            <textarea name="note" class="form-control form-control-sm shadow-2xs" rows="3" placeholder="e.g. Urgent stock required for client delivery..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 px-3 border-top">
                        <button type="button" class="btn btn-sm btn-light border fw-semibold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-warning fw-bold px-3 shadow-2xs text-white" style="background-color: #f59e0b; border-color: #d97706;">
                            <i class="feather-send me-1"></i> Send Reminder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approval Reminders Offcanvas Drawer -->
    <div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="reminderHistoryOffcanvas" style="width: 420px; z-index: 1060;">
        <div class="offcanvas-header bg-soft-warning border-bottom py-3">
            <h6 class="offcanvas-title fw-bold text-dark fs-14">
                <i class="feather-bell text-warning me-1.5 fs-15"></i> Approval Reminders Log — <span id="reminderOffcanvasDocNumber" class="text-primary font-monospace"></span>
            </h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-3" style="overflow-y: auto;">
            <div id="reminderOffcanvasList" class="d-flex flex-column gap-2.5">
                <!-- Populated dynamically via JS -->
            </div>
        </div>
        <div class="offcanvas-footer bg-light p-3 border-top text-end">
            <button type="button" class="btn btn-sm btn-secondary fw-semibold px-4" data-bs-dismiss="offcanvas">Close</button>
        </div>
    </div>

    @push('scripts')
    <script>
        function openRemindModal(actionUrl, docNumber) {
            document.getElementById('remindForm').action = actionUrl;
            document.getElementById('remindDocNumberText').innerText = docNumber || '';
            var modal = new bootstrap.Modal(document.getElementById('remindModal'));
            modal.show();
        }

        function showReminderHistoryModal(docNumber, reminders) {
            document.getElementById('reminderOffcanvasDocNumber').innerText = docNumber || '';
            const container = document.getElementById('reminderOffcanvasList');
            container.innerHTML = '';

            if (!reminders || reminders.length === 0) {
                container.innerHTML = '<div class="text-muted fs-12 text-center py-4"><i class="feather-info me-1"></i>No reminder messages recorded.</div>';
            } else {
                reminders.forEach((r, idx) => {
                    const item = document.createElement('div');
                    item.className = 'border rounded-3 p-3 bg-white shadow-2xs position-relative';
                    item.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="fw-bold text-dark fs-12"><i class="feather-user text-primary me-1"></i>${r.user}</span>
                            <span class="badge bg-soft-secondary text-muted font-monospace fs-10">${r.time}</span>
                        </div>
                        ${r.note ? `<div class="text-dark fst-italic fs-12 bg-light p-2 rounded border border-warning-subtle mt-1.5"><i class="feather-message-square me-1 text-warning"></i>"${r.note}"</div>` : '<div class="text-muted fs-11 fst-italic mt-1">(No note provided)</div>'}
                    `;
                    container.appendChild(item);
                });
            }

            var offcanvasEl = document.getElementById('reminderHistoryOffcanvas');
            var offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl) || new bootstrap.Offcanvas(offcanvasEl);
            offcanvas.show();
        }
    </script>
    @endpush
@endsection

