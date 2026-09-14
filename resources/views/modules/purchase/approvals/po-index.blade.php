@extends('layouts.duralux')

@section('title', 'PO Approvals | SaaS ERP')
@section('page-title', 'PO Approvals')
@section('breadcrumb', 'Purchase / PO Approvals')

@section('content')
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp

    <div class="erp-single-panel">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-0">PO Approvals</h5>
                <p class="text-muted fs-12 mb-0">Purchase Orders pending your approval</p>
            </div>

            <div class="d-flex gap-2 ms-auto">
                <x-ui.sort-dropdown :label="__('purchase.sort') ?? 'Sort'">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.date_latest') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.date_oldest') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'grand_total', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'grand_total' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.amount_high_low') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'grand_total', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'grand_total' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.amount_low_high') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <form method="GET" action="{{ route('purchase.po-approvals.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filters</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="PO number or supplier..." value="{{ request('search') }}" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Has Reminders</label>
                            <x-ui.odoo-form-ui type="select" name="has_reminders">
                                <option value="">All</option>
                                <option value="1" @selected(request('has_reminders') === '1')>Yes (Reminded)</option>
                                <option value="0" @selected(request('has_reminders') === '0')>No</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Reminder Date From</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="reminder_date_from" value="{{ request('reminder_date_from') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Reminder Date To</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="reminder_date_to" value="{{ request('reminder_date_to') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.po-approvals.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Count badge --}}
        <div class="d-flex gap-2 mb-3">
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-12 px-3 py-2 rounded-pill">
                <i class="feather-clock me-1"></i>
                {{ $orders->total() }} Pending {{ Str::plural('Approval', $orders->total()) }}
            </span>
        </div>

        <!-- PO Approvals Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="poApprovalTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input select-all">
                        </th>
                        <th style="width: 12%">{{ __('purchase.po_no') }}</th>
                        <th style="width: 18%">{{ __('purchase.supplier_name') }}</th>
                        <th style="width: 12%">{{ __('purchase.ref_document') }}</th>
                        <th style="width: 15%">DATES</th>
                        <th style="width: 11%" class="text-end">{{ __('purchase.grand_total') }}</th>
                        <th style="width: 13%">Last Reminder</th>
                        <th style="width: 8%" class="text-center">Status</th>
                        <th style="width: 12%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-checkbox" value="{{ $order->id }}">
                            </td>
                            <td class="fw-bold">
                                <a href="javascript:void(0)"
                                   class="text-primary text-decoration-none po-view-btn"
                                   data-id="{{ $order->id }}">
                                    {{ $order->purchase_order_number }}
                                </a>
                            </td>
                            <td>{{ $order->vendor->name ?? '—' }}</td>
                            <td>
                                @if($order->requisition)
                                    <a href="{{ route('purchase.requisitions.show', $order->purchase_requisition_id) }}" class="text-primary fw-medium">
                                        {{ $order->requisition->requisition_number }}
                                    </a>
                                @else
                                    <span class="text-muted small">{{ __('purchase.direct_po') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold text-dark fs-12" title="PO Order Date">
                                    <i class="feather-calendar text-muted me-1 fs-11"></i>PO: {{ $order->date ? $order->date->format('d M Y') : '—' }}
                                </div>
                                @if($order->delivery_date)
                                    <div class="text-info fs-11 fw-medium mt-0.5" title="Expected Delivery Date">
                                        <i class="feather-clock me-1 fs-10"></i>Exp: {{ $order->delivery_date->format('d M Y') }}
                                    </div>
                                @endif
                                @if($order->completed_at)
                                    <div class="text-success fs-11 fw-bold mt-0.5" title="Completion Date">
                                        <i class="feather-check-circle me-1 fs-10"></i>Comp: {{ $order->completed_at->format('d M Y') }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end font-monospace fw-bold text-success">
                                ₹{{ number_format($order->grand_total, 2) }}
                            </td>
                            <td>
                                @if($order->reminder_count > 0)
                                    @php
                                        $remData = $order->reminders->map(fn($r) => [
                                            'user' => $r->user->name ?? 'User',
                                            'time' => $r->created_at->format('d M Y h:i A'),
                                            'note' => $r->note
                                        ]);
                                    @endphp
                                    <button type="button"
                                            class="btn btn-xs btn-soft-danger border border-danger-subtle font-monospace px-2 py-0.5 fs-10 fw-bold d-inline-flex align-items-center gap-1 mb-1"
                                            title="Click to view all reminders"
                                            onclick="showReminderHistoryModal('{{ $order->purchase_order_number }}', {{ json_encode($remData) }})">
                                        <i class="feather-bell"></i>Reminded ({{ $order->reminder_count }})
                                    </button>
                                    <div class="fs-11 text-muted font-monospace">
                                        {{ $order->last_reminded_at ? $order->last_reminded_at->format('d-m-Y H:i') : '' }}
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    $statusClass = 'warning';
                                    if ($order->status === 'Completed') $statusClass = 'success';
                                    elseif ($order->status === 'Approved') $statusClass = 'primary';
                                    elseif ($order->status === 'Partially Received') $statusClass = 'info';
                                    elseif (in_array($order->status, ['Cancelled', 'Rejected'])) $statusClass = 'danger';
                                @endphp
                                <x-ui.badge :soft="true" :variant="$statusClass">
                                    {{ $order->status }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end" style="white-space: nowrap;">
                                <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                    {{-- View → offcanvas --}}
                                    <button type="button"
                                            class="action-dropdown-btn po-view-btn flex-shrink-0"
                                            title="View Details"
                                            data-bs-toggle="tooltip"
                                            data-id="{{ $order->id }}">
                                        <i class="feather feather-eye"></i>
                                    </button>

                                    {{-- Approve --}}
                                    <form action="{{ route('purchase.orders.approve', $order->id) }}" method="POST" class="d-inline-flex flex-shrink-0" id="approvePoForm_{{ $order->id }}">
                                        @csrf
                                        <button type="button" class="action-dropdown-btn action-btn-approve" title="Approve" data-bs-toggle="tooltip" onclick="confirmAction({ title: 'Approve PO', message: '{{ __('purchase.confirm_approve_po') }}', variant: 'success', confirmText: 'Approve' }, function() { document.getElementById('approvePoForm_{{ $order->id }}').submit(); })">
                                            <i class="feather feather-check-circle"></i>
                                        </button>
                                    </form>

                                     {{-- Reject --}}
                                     <button type="button" class="action-dropdown-btn action-btn-reject flex-shrink-0" title="Reject" data-bs-toggle="tooltip" onclick="openRejectModal('{{ route('purchase.orders.reject', $order->id) }}', '{{ $order->purchase_order_number }}')">
                                         <i class="feather feather-x-circle"></i>
                                     </button>

                                    {{-- More --}}
                                    <div class="flex-shrink-0">
                                        <x-ui.action-dropdown id="poApprovalActions-{{ $order->id }}">
                                          
                                        </x-ui.action-dropdown>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted fs-14">
                                <i class="feather-check-circle fs-24 mb-2 d-block text-success opacity-75"></i>
                                <strong class="d-block text-dark mb-1">All caught up!</strong>
                                No Purchase Orders pending approval.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination -->
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$orders->currentPage()"
                :totalPages="$orders->lastPage()"
                :totalResults="$orders->total()"
                :perPage="$orders->perPage()" />
        </div>
    </div>

    {{-- ── PO Detail Offcanvas Drawer ── --}}
    <x-ui.drawer id="poApprovalDrawer" title="Purchase Order Details" position="end" style="width: 580px; max-width: 95vw;">
        <div id="poApprovalDrawerContent">
            <div id="poApprovalDrawerLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-2 fs-13">Loading details…</p>
            </div>
            <div id="poApprovalDrawerBody" style="display:none;"></div>
        </div>

        <x-slot:footer>
            <a id="poApprovalFullViewLink" href="#" class="btn btn-outline-primary btn-sm">
                <i class="feather-external-link me-1"></i> View Full Details
            </a>
            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="offcanvas">
                <i class="feather-x me-1"></i> Close
            </button>
        </x-slot:footer>
    </x-ui.drawer>
@endsection

@push('styles')
<style>
    .action-btn-approve {
        color: #15803d !important;
        border-color: #bbf7d0 !important;
        background-color: #f0fdf4 !important;
    }
    .action-btn-approve:hover {
        background-color: #dcfce7 !important;
        border-color: #86efac !important;
        color: #15803d !important;
    }
    .action-btn-reject {
        color: #dc2626 !important;
        border-color: #fecaca !important;
        background-color: #fff1f2 !important;
    }
    .action-btn-reject:hover {
        background-color: #fee2e2 !important;
        border-color: #fca5a5 !important;
        color: #dc2626 !important;
    }
</style>
@endpush

@push('scripts')
<script>
    window.openRejectModal = function(actionUrl, docNumber = '') {
        $('#rejectActionForm').attr('action', actionUrl);
        if (docNumber) {
            $('#rejectModalDocNumber').text('(' + docNumber + ')');
        } else {
            $('#rejectModalDocNumber').text('');
        }
        $('#rejectionReasonInput').val('');
        const modalEl = document.getElementById('rejectActionModal');
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }
        modal.show();
    };

    $(document).ready(function () {
        $('.select-all').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
        });

        var poDrawer = new bootstrap.Offcanvas(document.getElementById('poApprovalDrawer'));

        $(document).on('click', '.po-view-btn', function () {
            openPoDrawer($(this).data('id'));
        });

        function openPoDrawer(id) {
            $('#poApprovalDrawerLoading').show();
            $('#poApprovalDrawerBody').hide().html('');
            $('#poApprovalFullViewLink').attr('href', '#');
            poDrawer.show();

            $.get("{{ url('purchase/orders') }}/" + id + '/po-detail-partial', function (data) {
                $('#poApprovalDrawerLoading').hide();
                $('#poApprovalDrawerBody').html(data).show();
                $('#poApprovalFullViewLink').attr('href', "{{ url('purchase/orders') }}/" + id);
                $('[data-bs-toggle="tooltip"]', '#poApprovalDrawerBody').each(function () {
                    new bootstrap.Tooltip(this);
                });
            }).fail(function () {
                $('#poApprovalDrawerLoading').hide();
                $('#poApprovalDrawerBody').html(
                    '<div class="alert alert-danger m-3"><i class="feather-alert-circle me-2"></i>Failed to load. Please try again.</div>'
                ).show();
            });
        }
    });
</script>

<!-- Rejection Reason Modal -->
<div class="modal fade" id="rejectActionModal" tabindex="-1" aria-labelledby="rejectActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form id="rejectActionForm" method="POST" action="">
                @csrf
                <div class="modal-header bg-soft-danger text-danger border-bottom-0">
                    <h5 class="modal-title fw-bold" id="rejectActionModalLabel">
                        <i class="feather-x-circle me-2"></i>Reject Purchase Order <span id="rejectModalDocNumber" class="text-dark"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted fs-12 mb-3">Please specify the reason for rejecting this purchase order. This reason will be saved in audit history and displayed on details screen.</p>
                    
                    <div class="mb-3 text-start">
                        <label for="rejectionReasonInput" class="form-label fw-bold text-dark fs-12 mb-1">Rejection Reason / Remarks <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectionReasonInput" name="rejection_reason" rows="4" placeholder="Enter reason for rejection (e.g., Price mismatch, Terms unacceptable, Duplicate order, etc.)..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 px-4 py-3">
                    <button type="button" class="btn btn-light btn-sm border text-uppercase fs-11 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold text-uppercase fs-11" style="background-color: #ea580c; border-color: #ea580c;">
                        <i class="feather-x-circle me-1"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
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

<script>
    function openRejectModal(actionUrl, docNumber) {
        document.getElementById('rejectActionForm').action = actionUrl;
        document.getElementById('rejectModalDocNumber').innerText = docNumber ? '(' + docNumber + ')' : '';
        document.getElementById('rejectionReasonInput').value = '';
        var modal = new bootstrap.Modal(document.getElementById('rejectActionModal'));
        modal.show();
    }

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
