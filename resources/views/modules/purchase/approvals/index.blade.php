@extends('layouts.duralux')

@section('title', __('purchase.pr_approvals') . ' | SaaS ERP')
@section('page-title', __('purchase.pr_approvals'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.pr_approvals'))

@section('content')
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        <!-- Toast Notifications -->

        <div class="d-flex align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-0">{{ __('purchase.pr_approvals') }}</h5>
                <p class="text-muted fs-12 mb-0">{{ __('purchase.pr_approvals_help') }}</p>
            </div>

            <div class="d-flex gap-2 ms-auto">
                <!-- Sort -->
                <x-ui.sort-dropdown :label="__('crm.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_date', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'requisition_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_date_latest') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_date', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'requisition_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_date_oldest') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_number', 'sort_order' => 'asc']) }}"
                       class="dropdown-item {{ $sortBy === 'requisition_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_req_asc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_number', 'sort_order' => 'desc']) }}"
                       class="dropdown-item {{ $sortBy === 'requisition_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_req_desc') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter -->
                <form method="GET" action="{{ route('purchase.pr-approvals.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3">
                            <i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}
                        </h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search"
                                placeholder="{{ __('purchase.search_req_placeholder') }}"
                                value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.source_type') }}</label>
                            <x-ui.odoo-form-ui type="select" name="source_type">
                                <option value="">{{ __('purchase.all_sources') }}</option>
                                <option value="direct"       @selected(request('source_type') === 'direct')>{{ __('purchase.source_direct') }}</option>
                                <option value="so"           @selected(request('source_type') === 'so')>{{ __('purchase.source_so') }}</option>
                                <option value="mo"           @selected(request('source_type') === 'mo')>{{ __('purchase.source_mo') }}</option>
                                <option value="material_request" @selected(request('source_type') === 'material_request')>{{ __('purchase.source_material_request') }}</option>
                                <option value="material_requirement" @selected(request('source_type') === 'material_requirement')>{{ __('purchase.source_material_requirement') }}</option>
                                <option value="requisition_slip" @selected(request('source_type') === 'requisition_slip')>{{ __('purchase.source_requisition_slip') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.has_reminders') }}</label>
                            <x-ui.odoo-form-ui type="select" name="has_reminders">
                                <option value="">{{ __('purchase.all') }}</option>
                                <option value="1" @selected(request('has_reminders') === '1')>{{ __('purchase.yes_reminded') }}</option>
                                <option value="0" @selected(request('has_reminders') === '0')>{{ __('purchase.no') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.reminder_date_from') }}</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="reminder_date_from" value="{{ request('reminder_date_from') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.reminder_date_to') }}</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="reminder_date_to" value="{{ request('reminder_date_to') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.pr-approvals.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Summary badges --}}
        <div class="d-flex gap-2 mb-3">
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-12 px-3 py-2 rounded-pill">
                <i class="feather-clock me-1"></i>
                {{ $requisitions->total() }} {{ $requisitions->total() == 1 ? __('purchase.pending_approval_badge') : __('purchase.pending_approvals_badge') }}
            </span>
        </div>

        <!-- Approvals Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="prApprovalTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input select-all">
                        </th>
                        <th style="width: 11%">{{ __('purchase.requisition_number') }}</th>
                        <th style="width: 12%">{{ __('purchase.requested_by') }}</th>
                        <th style="width: 11%">{{ __('purchase.requisition_date') }}</th>
                        <th style="width: 11%">{{ __('purchase.expected_date') }}</th>
                        <th style="width: 11%">{{ __('purchase.source_type') }}</th>
                        <th style="width: 11%">{{ __('purchase.source') }}</th>
                        <th style="width: 14%">{{ __('purchase.last_reminder') }}</th>
                        <th style="width: 8%">{{ __('purchase.status') }}</th>
                        <th style="width: 14%" class="text-end">{{ __('purchase.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requisitions as $req)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-checkbox" value="{{ $req->id }}">
                            </td>
                            <td class="fw-bold">
                                <a href="javascript:void(0)"
                                   class="text-primary text-decoration-none pr-view-btn"
                                   data-id="{{ $req->id }}">
                                    {{ $req->requisition_number }}
                                </a>
                            </td>
                            <td>{{ $req->requester->name ?? __('purchase.system') }}</td>
                            <td>{{ $req->requisition_date ? $req->requisition_date->format('d-m-Y') : '—' }}</td>
                            <td>
                                @if($req->expected_date)
                                    <span class="badge bg-soft-info text-info border border-info-subtle font-monospace px-2 py-0.5 fs-11">
                                        {{ $req->expected_date->format('d-m-Y') }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $sourceBadge = 'secondary';
                                    if ($req->source_type === 'mo') $sourceBadge = 'warning';
                                    elseif ($req->source_type === 'material_request') $sourceBadge = 'info';
                                    elseif ($req->source_type === 'material_requirement') $sourceBadge = 'success';
                                    elseif ($req->source_type === 'so') $sourceBadge = 'danger';
                                @endphp
                                <x-ui.badge :soft="true" :variant="$sourceBadge" class="fs-10 text-uppercase">
                                    {{ __('purchase.source_' . $req->source_type) }}
                                </x-ui.badge>
                            </td>
                            <td>
                                @if($req->source_type === 'mo' && $req->sourceable)
                                    <a href="{{ route('production.orders.show', $req->source_id) }}" class="text-primary fw-medium">
                                        {{ $req->sourceable->order_number }}
                                    </a>
                                @elseif($req->source_type === 'material_request' && $req->sourceable)
                                    <a href="{{ route('sales.material-requests.show', $req->source_id) }}" class="text-primary fw-medium">
                                        {{ $req->sourceable->requisition_number }}
                                    </a>
                                @elseif($req->source_type === 'material_requirement' && $req->sourceable)
                                    <a href="{{ route('inventory.material-requirements.show', $req->source_id) }}" class="text-primary fw-medium">
                                        {{ $req->sourceable->requirement_number }}
                                    </a>
                                @elseif($req->source_type === 'so' && $req->sourceable)
                                    <a href="{{ route('sales.orders.show', $req->source_id) }}" class="text-primary fw-medium">
                                        {{ $req->sourceable->sales_order_number }}
                                    </a>
                                @elseif($req->source_type === 'requisition_slip')
                                    <span class="text-muted font-monospace">{{ $req->requisition_slip_number ?: '—' }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($req->reminder_count > 0)
                                    @php
                                        $remData = $req->reminders->map(fn($r) => [
                                            'user' => $r->user->name ?? 'User',
                                            'time' => $r->created_at->format('d M Y h:i A'),
                                            'note' => $r->note
                                        ]);
                                    @endphp
                                    <button type="button"
                                            class="btn btn-xs btn-soft-danger border border-danger-subtle font-monospace px-2 py-0.5 fs-10 fw-bold d-inline-flex align-items-center gap-1 mb-1"
                                            title="Click to view all reminders"
                                            onclick="showReminderHistoryModal('{{ $req->requisition_number }}', {{ json_encode($remData) }})">
                                        <i class="feather-bell"></i>{{ __('purchase.reminded') }} ({{ $req->reminder_count }})
                                    </button>
                                    <div class="fs-11 text-muted font-monospace">
                                        {{ $req->last_reminded_at ? $req->last_reminded_at->format('d-m-Y H:i') : '' }}
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <x-ui.badge :soft="true" variant="warning">{{ __('purchase.status_draft') }}</x-ui.badge>
                            </td>
                            <td class="text-end" style="white-space: nowrap;">
                                <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                    {{-- View (offcanvas) --}}
                                    <button type="button"
                                            class="action-dropdown-btn pr-view-btn flex-shrink-0"
                                            title="{{ __('purchase.view_details') }}"
                                            data-bs-toggle="tooltip"
                                            data-id="{{ $req->id }}">
                                        <i class="feather feather-eye"></i>
                                    </button>

                                    {{-- Approve --}}
                                     <form action="{{ route('purchase.requisitions.approve', $req->id) }}" method="POST" class="d-inline-flex flex-shrink-0" id="approvePrForm_{{ $req->id }}">
                                         @csrf
                                         <button type="button" class="action-dropdown-btn action-btn-approve" title="{{ __('purchase.approve') }}" data-bs-toggle="tooltip" onclick="confirmAction({ title: '{{ __('purchase.approve_pr') }}', message: '{{ __('purchase.confirm_approve') }}', variant: 'success', confirmText: '{{ __('purchase.approve') }}' }, function() { document.getElementById('approvePrForm_{{ $req->id }}').submit(); })">
                                             <i class="feather feather-check-circle"></i>
                                         </button>
                                     </form>

                                     {{-- Reject --}}
                                     <button type="button" class="action-dropdown-btn action-btn-reject flex-shrink-0" title="{{ __('purchase.reject') }}" data-bs-toggle="tooltip" onclick="openRejectModal('{{ route('purchase.requisitions.reject', $req->id) }}', '{{ $req->requisition_number }}')">
                                         <i class="feather feather-x-circle"></i>
                                     </button>

                                     {{-- More (Edit / Delete) --}}
                                     <div class="flex-shrink-0">
                                         <x-ui.action-dropdown id="prApprovalActions-{{ $req->id }}">
                                         </x-ui.action-dropdown>
                                     </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted fs-14">
                                <i class="feather-check-circle fs-24 mb-2 d-block text-success opacity-75"></i>
                                <strong class="d-block text-dark mb-1">{{ __('purchase.all_caught_up') }}</strong>
                                {{ __('purchase.no_pr_pending_approval') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination -->
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$requisitions->currentPage()"
                :totalPages="$requisitions->lastPage()"
                :totalResults="$requisitions->total()"
                :perPage="$requisitions->perPage()" />
        </div>
    </div>

    {{-- ── Offcanvas Drawer (reuses same partial as PR index) ── --}}
    <x-ui.drawer id="prApprovalDrawer" :title="__('purchase.purchase_requisition_details')" position="end" style="width: 580px; max-width: 95vw;">
        <div id="prApprovalDrawerContent">
            <div id="prApprovalDrawerLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">{{ __('purchase.loading') ?? 'Loading...' }}</span>
                </div>
                <p class="text-muted mt-2 fs-13">{{ __('purchase.loading_details') }}</p>
            </div>
            <div id="prApprovalDrawerBody" style="display:none;"></div>
        </div>

        <x-slot:footer>
            <a id="prApprovalFullViewLink" href="#" class="btn btn-outline-primary btn-sm">
                <i class="feather-external-link me-1"></i> {{ __('purchase.view_full_details') }}
            </a>
            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="offcanvas">
                <i class="feather-x me-1"></i> {{ __('purchase.close') }}
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
        // Select All
        $('.select-all').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Offcanvas
        var prDrawer = new bootstrap.Offcanvas(document.getElementById('prApprovalDrawer'));

        $(document).on('click', '.pr-view-btn', function () {
            openDrawer($(this).data('id'));
        });

        function openDrawer(id) {
            $('#prApprovalDrawerLoading').show();
            $('#prApprovalDrawerBody').hide().html('');
            $('#prApprovalFullViewLink').attr('href', '#');
            prDrawer.show();

            $.get("{{ url('purchase/requisitions') }}/" + id + '/detail-partial', function (data) {
                $('#prApprovalDrawerLoading').hide();
                $('#prApprovalDrawerBody').html(data).show();
                $('#prApprovalFullViewLink').attr('href', "{{ url('purchase/requisitions') }}/" + id);
                $('[data-bs-toggle="tooltip"]', '#prApprovalDrawerBody').each(function () {
                    new bootstrap.Tooltip(this);
                });
            }).fail(function () {
                $('#prApprovalDrawerLoading').hide();
                $('#prApprovalDrawerBody').html(
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
                        <i class="feather-x-circle me-2"></i>{{ __('purchase.reject_requisition') }} <span id="rejectModalDocNumber" class="text-dark"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted fs-12 mb-3">{{ __('purchase.reject_pr_help_text') }}</p>
                    
                    <div class="mb-3 text-start">
                        <label for="rejectionReasonInput" class="form-label fw-bold text-dark fs-12 mb-1">{{ __('purchase.rejection_reason_remarks') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectionReasonInput" name="rejection_reason" rows="4" placeholder="{{ __('purchase.reject_pr_reason_placeholder') }}" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 px-4 py-3">
                    <button type="button" class="btn btn-light btn-sm border text-uppercase fs-11 fw-bold" data-bs-dismiss="modal">{{ __('purchase.cancel') }}</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold text-uppercase fs-11" style="background-color: #ea580c; border-color: #ea580c;">
                        <i class="feather-x-circle me-1"></i> {{ __('purchase.confirm_rejection') }}
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
                    <i class="feather-bell text-warning me-1.5 fs-15"></i> {{ __('purchase.send_quick_approval_reminder') }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="remindForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-warning border border-warning-subtle py-2 px-3 fs-12 mb-3">
                        <i class="feather-info me-1"></i>
                        {{ __('purchase.sending_reminder_for_doc') }} <strong id="remindDocNumberText" class="text-dark"></strong>.
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('purchase.optional_note_for_approver') }}</label>
                        <textarea name="note" class="form-control form-control-sm shadow-2xs" rows="3" placeholder="{{ __('purchase.reminder_note_placeholder') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3 border-top">
                    <button type="button" class="btn btn-sm btn-light border fw-semibold" data-bs-dismiss="modal">{{ __('purchase.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-warning fw-bold px-3 shadow-2xs text-white" style="background-color: #f59e0b; border-color: #d97706;">
                        <i class="feather-send me-1"></i> {{ __('purchase.send_reminder') }}
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
            <i class="feather-bell text-warning me-1.5 fs-15"></i> {{ __('purchase.approval_reminders_log') }} — <span id="reminderOffcanvasDocNumber" class="text-primary font-monospace"></span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3" style="overflow-y: auto;">
        <div id="reminderOffcanvasList" class="d-flex flex-column gap-2.5">
            <!-- Populated dynamically via JS -->
        </div>
    </div>
    <div class="offcanvas-footer bg-light p-3 border-top text-end">
        <button type="button" class="btn btn-sm btn-secondary fw-semibold px-4" data-bs-dismiss="offcanvas">{{ __('purchase.close') }}</button>
    </div>
</div>

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
            container.innerHTML = '<div class="text-muted fs-12 text-center py-4"><i class="feather-info me-1"></i>{{ __('purchase.no_reminder_messages') }}</div>';
        } else {
            reminders.forEach((r, idx) => {
                const item = document.createElement('div');
                item.className = 'border rounded-3 p-3 bg-white shadow-2xs position-relative';
                item.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <span class="fw-bold text-dark fs-12"><i class="feather-user text-primary me-1"></i>${r.user}</span>
                        <span class="badge bg-soft-secondary text-muted font-monospace fs-10">${r.time}</span>
                    </div>
                    ${r.note ? `<div class="text-dark fst-italic fs-12 bg-light p-2 rounded border border-warning-subtle mt-1.5"><i class="feather-message-square me-1 text-warning"></i>"${r.note}"</div>` : '<div class="text-muted fs-11 fst-italic mt-1">{{ __('purchase.no_note_provided') }}</div>'}
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
