@extends('layouts.duralux')

@section('title', __('purchase.purchase_requests') . ' | SaaS ERP')
@section('page-title', __('purchase.purchase_requests'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.purchase_requests'))

@section('page-actions')
    <x-ui.button href="{{ route('purchase.requisitions.create') }}" variant="primary" icon="feather-plus">
        {{ __('purchase.create_purchase_request') }}
    </x-ui.button>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        <!-- Toast Notifications -->

        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('purchase.purchase_requests_listing') }}</h5>

            <div class="d-flex gap-2 ms-auto align-items-center flex-wrap">
                <!-- Quick Search (HRMS Common Component Style) -->
                <form method="GET" action="{{ route('purchase.requisitions.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="{{ __('purchase.search_req_placeholder') }}" 
                        value="{{ request('search') }}"
                        style="box-shadow: none; height: 32px; width: 220px;"
                    >
                </form>

                <!-- Sort -->
                <x-ui.sort-dropdown :label="__('crm.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requisition_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_date_latest') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requisition_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_date_oldest') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requisition_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_req_asc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requisition_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requisition_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.sort_req_desc') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter -->
                <form method="GET" action="{{ route('purchase.requisitions.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_req_placeholder') }}" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('purchase.all_statuses') }}</option>
                                <option value="Draft"    @selected(request('status') === 'Draft')>{{ __('purchase.status_draft') }}</option>
                                <option value="Approved" @selected(request('status') === 'Approved')>{{ __('purchase.status_approved') }}</option>
                                <option value="Cancelled" @selected(request('status') === 'Cancelled')>{{ __('purchase.status_cancelled') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.source_type') }}</label>
                            <x-ui.odoo-form-ui type="select" name="source_type">
                                <option value="">{{ __('purchase.all_sources') }}</option>
                                <option value="direct"               @selected(request('source_type') === 'direct')>{{ __('purchase.source_direct') }}</option>
                                <option value="so"                   @selected(request('source_type') === 'so')>{{ __('purchase.source_so') }}</option>
                                <option value="mo"                   @selected(request('source_type') === 'mo')>{{ __('purchase.source_mo') }}</option>
                                <option value="material_request"     @selected(request('source_type') === 'material_request')>{{ __('purchase.source_material_request') }}</option>
                                <option value="material_requirement" @selected(request('source_type') === 'material_requirement')>{{ __('purchase.source_material_requirement') }}</option>
                                <option value="requisition_slip"     @selected(request('source_type') === 'requisition_slip')>{{ __('purchase.source_requisition_slip') }}</option>
                            </x-ui.odoo-form-ui>
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
                            <a href="{{ route('purchase.requisitions.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Requisitions List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="prTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input select-all">
                        </th>
                        <th style="width: 12%">{{ __('purchase.requisition_number') }}</th>
                        <th style="width: 13%">{{ __('purchase.requested_by') }}</th>
                        <th style="width: 11%">{{ __('purchase.requisition_date') }}</th>
                        <th style="width: 11%">{{ __('purchase.expected_date') }}</th>
                        <th style="width: 11%">{{ __('purchase.source_type') }}</th>
                        <th style="width: 12%">{{ __('purchase.source') }}</th>
                        <th style="width: 13%">Last Reminder</th>
                        <th style="width: 8%">{{ __('purchase.status') }}</th>
                        <th style="width: 9%" class="text-end">{{ __('purchase.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requisitions as $req)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-checkbox" value="{{ $req->id }}">
                            </td>
                            <td class="fw-bold">
                                <a href="{{ route('purchase.requisitions.show', $req->id) }}" class="text-primary text-decoration-none">
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
                                    if($req->source_type === 'mo') $sourceBadge = 'warning';
                                    elseif($req->source_type === 'material_request') $sourceBadge = 'info';
                                    elseif($req->source_type === 'material_requirement') $sourceBadge = 'success';
                                    elseif($req->source_type === 'so') $sourceBadge = 'danger';
                                @endphp
                                <x-ui.badge :soft="true" :variant="$sourceBadge" class="fs-10 text-uppercase">
                                    {{ __('purchase.source_' . $req->source_type) }}
                                </x-ui.badge>
                            </td>
                            <td>
                                @if($req->source_type === 'mo' && $req->sourceable)
                                    <a href="{{ route('production.orders.show', $req->source_id) }}" class="text-primary fw-medium">{{ $req->sourceable->order_number }}</a>
                                @elseif($req->source_type === 'material_request' && $req->sourceable)
                                    <a href="{{ route('sales.material-requests.show', $req->source_id) }}" class="text-primary fw-medium">{{ $req->sourceable->requisition_number }}</a>
                                @elseif($req->source_type === 'material_requirement' && $req->sourceable)
                                    <a href="{{ route('sales.material-requirements.show', $req->source_id) }}" class="text-primary fw-medium">{{ $req->sourceable->requirement_number }}</a>
                                @elseif($req->source_type === 'so' && $req->sourceable)
                                    <a href="{{ route('sales.orders.show', $req->source_id) }}" class="text-primary fw-medium">{{ $req->sourceable->sales_order_number }}</a>
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
                                        <i class="feather-bell"></i>Reminded ({{ $req->reminder_count }})
                                    </button>
                                    <div class="fs-11 text-muted font-monospace">
                                        {{ $req->last_reminded_at ? $req->last_reminded_at->format('d-m-Y H:i') : '' }}
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusClass = 'warning';
                                    if ($req->status === 'Approved') $statusClass = 'success';
                                    elseif ($req->status === 'Cancelled') $statusClass = 'danger';
                                @endphp
                                <x-ui.badge :soft="true" :variant="$statusClass">
                                    {{ __('purchase.status_' . strtolower($req->status)) }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                    @if($req->status === 'Draft')
                                        <button type="button"
                                                class="action-dropdown-btn text-warning flex-shrink-0"
                                                title="Send Quick Reminder"
                                                onclick="openRemindModal('{{ route('purchase.requisitions.remind', $req->id) }}', '{{ $req->requisition_number }}')">
                                            <i class="feather-bell"></i>
                                        </button>
                                    @endif
                                    <x-ui.action-dropdown :viewUrl="route('purchase.requisitions.show', $req->id)" id="reqActions-{{ $req->id }}">
                                    @if($req->status === 'Draft')
                                        <li>
                                            <button type="button" class="dropdown-item py-2 text-warning fw-semibold" onclick="openRemindModal('{{ route('purchase.requisitions.remind', $req->id) }}', '{{ $req->requisition_number }}')">
                                                <i class="feather-bell me-1.5 text-warning"></i> Send Quick Reminder
                                            </button>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2" href="{{ route('purchase.requisitions.edit', $req->id) }}">
                                                <i class="feather-edit me-1.5 text-muted"></i> {{ __('purchase.edit') }}
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('purchase.requisitions.destroy', $req->id) }}" method="POST" id="deletePrListForm_{{ $req->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction({ title: 'Delete Requisition', message: '{{ __('purchase.confirm_delete') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deletePrListForm_{{ $req->id }}').submit(); })">
                                                    <i class="feather-trash-2 me-1.5"></i> {{ __('purchase.delete') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </div>
                        </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted fs-14">
                                <i class="feather-truck fs-24 mb-1.5 d-block opacity-50"></i>
                                {{ __('purchase.no_purchase_requests') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Links -->
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$requisitions->currentPage()"
                :totalPages="$requisitions->lastPage()"
                :totalResults="$requisitions->total()"
                :perPage="$requisitions->perPage()" />
        </div>
    </div>
@endsection

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
        $(document).ready(function () {
            $('.select-all').on('change', function () {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
            });
        });

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
