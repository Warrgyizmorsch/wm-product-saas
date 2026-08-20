@extends('layouts.duralux')

@section('title', __('purchase.purchase_orders') . ' | SaaS ERP')
@section('page-title', __('purchase.purchase_orders'))
@section('breadcrumb')
    {{ __('ui.purchase') }} / {{ __('purchase.purchase_orders') }}
@endsection

@section('page-actions')
    <x-ui.button href="{{ route('purchase.orders.create') }}" variant="primary" icon="feather-plus" style="background-color: #714B67; border-color: #714B67;">
        {{ __('purchase.create_purchase_order') }}
    </x-ui.button>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Toast Notifications -->

        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('purchase.purchase_orders_listing') }}</h5>
            
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Dropdown -->
                <x-ui.sort-dropdown :label="__('purchase.sort') ?? 'Sort'">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.date_latest') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.date_oldest') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'purchase_order_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'purchase_order_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.po_number_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'purchase_order_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'purchase_order_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.po_number_za') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'grand_total', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'grand_total' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('purchase.amount_high_low') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'grand_total', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'grand_total' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('purchase.amount_low_high') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Panel -->
                <form method="GET" action="{{ route('purchase.orders.index') }}" class="d-inline">
                    <x-ui.filter :label="__('purchase.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_po_placeholder') }}" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Purchase Type</label>
                            <x-ui.odoo-form-ui type="select" name="is_subcontract">
                                <option value="">All Types</option>
                                <option value="0" @selected(request('is_subcontract') === '0')>Standard PO</option>
                                <option value="1" @selected(request('is_subcontract') === '1')>Subcontract PO</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('purchase.all_statuses') }}</option>
                                <option value="Draft" @selected(request('status') === 'Draft')>{{ __('purchase.status_draft') }}</option>
                                <option value="Approved" @selected(request('status') === 'Approved')>{{ __('purchase.status_approved') }}</option>
                                <option value="Cancelled" @selected(request('status') === 'Cancelled')>{{ __('purchase.status_cancelled') }}</option>
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
                            <a href="{{ route('purchase.orders.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Listing Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="poTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input select-all">
                        </th>
                        <th style="width: 11%">{{ __('purchase.po_no') }}</th>
                        <th style="width: 16%">{{ __('purchase.supplier_name') }}</th>
                        <th style="width: 11%">{{ __('purchase.ref_document') }}</th>
                        <th style="width: 14%">DATES</th>
                        <th style="width: 8%" class="text-end">{{ __('purchase.subtotal') }}</th>
                        <th style="width: 7%" class="text-end">{{ __('purchase.total_tax') }}</th>
                        <th style="width: 9%" class="text-end">{{ __('purchase.grand_total') }}</th>
                        <th style="width: 12%">Last Reminder</th>
                        <th style="width: 8%" class="text-center">{{ __('purchase.status') }}</th>
                        <th style="width: 9%" class="text-end">{{ __('purchase.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-checkbox" value="{{ $order->id }}">
                            </td>
                            <td class="fw-bold">
                                <a href="{{ route('purchase.orders.show', $order->id) }}" class="text-primary text-decoration-none">
                                    {{ $order->purchase_order_number }}
                                </a>
                                @if($order->is_subcontract)
                                    <span class="badge bg-soft-warning text-dark border border-warning px-1.5 py-0.5 fs-10 fw-bold d-block mt-1">
                                        <i class="feather-truck me-1"></i>Subcontract PO
                                    </span>
                                @endif
                            </td>
                            <td>{{ $order->vendor->name ?? '—' }}</td>
                            <td>
                                @if($order->is_subcontract && $order->production_order_id)
                                    <a href="{{ route('production.orders.show', $order->production_order_id) }}" class="text-primary fw-bold">
                                        <i class="feather-cpu me-1"></i>MO #{{ $order->production_order_id }}
                                    </a>
                                @elseif($order->requisition)
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
                            <td class="text-end font-monospace">{{ number_format($order->subtotal, 2) }}</td>
                            <td class="text-end font-monospace text-muted">{{ number_format($order->tax_amount, 2) }}</td>
                            <td class="text-end font-monospace fw-bold text-success">{{ number_format($order->grand_total, 2) }}</td>
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

                                    $statusText = match($order->status) {
                                        'Draft' => __('purchase.status_draft'),
                                        'Approved' => __('purchase.status_approved'),
                                        'Completed' => 'Completed',
                                        'Partially Received' => 'Partially Received',
                                        'Cancelled' => __('purchase.status_cancelled'),
                                        default => $order->status,
                                    };
                                @endphp
                                <x-ui.badge :soft="true" :variant="$statusClass">
                                    {{ $statusText }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                    @if($order->status === 'Draft')
                                        <button type="button"
                                                class="action-dropdown-btn text-warning flex-shrink-0"
                                                title="Send Quick Reminder"
                                                onclick="openRemindModal('{{ route('purchase.orders.remind', $order->id) }}', '{{ $order->purchase_order_number }}')">
                                            <i class="feather-bell"></i>
                                        </button>
                                    @endif
                                    <x-ui.action-dropdown :viewUrl="route('purchase.orders.show', $order->id)" id="poActions-{{ $order->id }}">
                                        @if($order->status === 'Draft')
                                            <li>
                                                <button type="button" class="dropdown-item py-2 text-warning fw-semibold" onclick="openRemindModal('{{ route('purchase.orders.remind', $order->id) }}', '{{ $order->purchase_order_number }}')">
                                                    <i class="feather-bell me-1.5 text-warning"></i> Send Quick Reminder
                                                </button>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2" href="{{ route('purchase.orders.edit', $order->id) }}">
                                                    <i class="feather-edit me-1.5 text-muted"></i> {{ __('purchase.edit_draft') }}
                                                </a>
                                            </li>
                                             <li>
                                                 <form action="{{ route('purchase.orders.destroy', $order->id) }}" method="POST" id="deletePoListForm_{{ $order->id }}">
                                                     @csrf
                                                     @method('DELETE')
                                                     <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction({ title: 'Delete PO', message: '{{ __('purchase.confirm_delete_po') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deletePoListForm_{{ $order->id }}').submit(); })">
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
                            <td colspan="10" class="text-center py-5 text-muted fs-14">
                                <i class="feather-truck fs-24 mb-1.5 d-block opacity-50"></i>
                                {{ __('purchase.no_pos_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Links -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$orders->currentPage()" 
                :totalPages="$orders->lastPage()" 
                :totalResults="$orders->total()" 
                :perPage="$orders->perPage()" />
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
        $(document).ready(function() {
            $('.select-all').on('change', function() {
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
