@extends('layouts.duralux')

@section('title', 'Quotation Approvals | SaaS ERP')
@section('page-title', 'Quotation Approvals')
@section('breadcrumb', 'CRM / Approvals / Quotations')

@section('content')

    @php
        $sortBy = request('sort_by', 'quotation_date');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if ($errors->any())
            <div class="alert alert-danger mb-3 alert-dismissible fade show fs-12 py-2" role="alert">
                <ul class="mb-0 ps-3 text-start">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem 1rem;"></button>
            </div>
        @endif
        
        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Quotation Approvals Listing</h5>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'quotation_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'quotation_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Quotation Date (Latest first)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'quotation_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'quotation_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Quotation Date (Oldest first)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'quotation_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'quotation_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Quotation Number (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'quotation_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'quotation_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Quotation Number (Z-A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'total_amount', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'total_amount' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Total Amount (High to Low)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'total_amount', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'total_amount' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Total Amount (Low to High)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'customer_name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'customer_name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Customer Name (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'customer_name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'customer_name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Customer Name (Z-A)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('crm.approvals.quotations.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search quotation number, customer..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                @foreach(['Draft', 'Pending Approval', 'Approved', 'Sent', 'Quotation Sent', 'Accepted', 'Rejected', 'Quotation Rework'] as $statusOption)
                                    <option value="{{ $statusOption }}" {{ request('status') === $statusOption || (!request()->has('status') && !request()->has('search') && $statusOption === 'Pending Approval') ? 'selected' : '' }}>{{ $statusOption }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('crm.approvals.quotations.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Quotations List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="quotationsTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>Quotation #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Expiry Date</th>
                        <th class="text-end">Total Amount</th>
                        <th class="ps-4">Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @forelse ($quotations as $quotation)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td class="fw-bold text-primary">
                                <a href="{{ route('crm.quotations.show', $quotation->id) }}">{{ $quotation->quotation_number }}</a>
                            </td>
                            <td>
                                <span class="fw-bold text-dark">{{ $quotation->customer?->name ?? ($quotation->lead?->company_name ?: ($quotation->lead?->contact_person ?? '—')) }}</span>
                            </td>
                            <td>{{ $quotation->quotation_date ? $quotation->quotation_date->format('d/m/Y') : '—' }}</td>
                            <td>{{ $quotation->expiry_date ? $quotation->expiry_date->format('d/m/Y') : '—' }}</td>
                            <td class="text-end fw-bold text-dark">₹{{ number_format($quotation->total_amount, 2) }}</td>
                            <td class="ps-4">
                                @php
                                    $badgeClass = 'bg-soft-secondary text-secondary';
                                    if ($quotation->status === 'Quotation Sent' || $quotation->status === 'Sent') $badgeClass = 'bg-soft-info text-info';
                                    elseif ($quotation->status === 'Accepted' || $quotation->status === 'Approved') $badgeClass = 'bg-soft-success text-success';
                                    elseif ($quotation->status === 'Rejected') $badgeClass = 'bg-soft-danger text-danger';
                                    elseif ($quotation->status === 'Pending Approval') $badgeClass = 'bg-soft-warning text-warning';
                                    elseif ($quotation->status === 'Quotation Rework') $badgeClass = 'bg-soft-warning text-warning';
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $quotation->status }}</span>
                                @if ($quotation->status === 'Rejected' && $quotation->rejection_reason)
                                    <small class="d-block text-danger fs-11 mt-1 text-truncate" style="max-width: 200px;" title="Reason: {{ $quotation->rejection_reason }}" data-bs-toggle="tooltip">
                                        <i class="feather-alert-circle me-1"></i>{{ $quotation->rejection_reason }}
                                    </small>
                                @endif
                            </td>
                            <td class="text-end pe-4" style="white-space: nowrap;">
                                <div class="d-inline-flex gap-1 align-items-center justify-content-end">
                                    <button type="button" class="action-dropdown-btn view-detail-btn flex-shrink-0" 
                                       data-url="{{ route('crm.quotations.detail-partial', $quotation->id) }}"
                                       title="View Details" data-bs-toggle="tooltip">
                                        <i class="feather-eye"></i>
                                    </button>

                                    @if ($quotation->status === 'Pending Approval')
                                        <form action="{{ route('crm.quotations.approve', $quotation->id) }}" method="POST" class="d-inline-flex flex-shrink-0 m-0" id="approveQuoForm_{{ $quotation->id }}">
                                            @csrf
                                            <button type="button" class="action-dropdown-btn action-btn-approve" title="Approve" data-bs-toggle="tooltip" onclick="confirmAction({ title: 'Approve Quotation', message: 'Approve this quotation?', variant: 'success', confirmText: 'Approve' }, function() { document.getElementById('approveQuoForm_{{ $quotation->id }}').submit(); })">
                                                <i class="feather-check-circle"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="action-dropdown-btn action-btn-reject flex-shrink-0" title="Reject" data-bs-toggle="tooltip" onclick="openRejectModal('{{ route('crm.quotations.reject', $quotation->id) }}', '{{ $quotation->quotation_number }}')">
                                            <i class="feather-x-circle"></i>
                                        </button>
                                    @endif

                                    @if ($quotation->status === 'Approved')
                                        <form action="{{ route('crm.quotations.updateStatus', $quotation->id) }}" method="POST" class="d-inline-flex flex-shrink-0 m-0" id="markSentQuoForm_{{ $quotation->id }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="Sent">
                                            <button type="button" class="action-dropdown-btn" title="Mark as Sent" data-bs-toggle="tooltip" style="color: #0284c7; background-color: #e0f2fe; border-color: #bae6fd;" onclick="confirmAction({ title: 'Mark as Sent', message: 'Mark this quotation as Sent to customer?', variant: 'info', confirmText: 'Mark Sent' }, function() { document.getElementById('markSentQuoForm_{{ $quotation->id }}').submit(); })">
                                                <i class="feather-send"></i>
                                            </button>
                                        </form>
                                    @elseif (in_array($quotation->status, ['Sent', 'Quotation Sent']))
                                        <form action="{{ route('crm.quotations.updateStatus', $quotation->id) }}" method="POST" class="d-inline-flex flex-shrink-0 m-0" id="acceptQuoForm_{{ $quotation->id }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="Accepted">
                                            <button type="button" class="action-dropdown-btn action-btn-approve" title="Accept" data-bs-toggle="tooltip" onclick="confirmAction({ title: 'Accept Quotation', message: 'Mark this quotation as Accepted by customer?', variant: 'success', confirmText: 'Accept' }, function() { document.getElementById('acceptQuoForm_{{ $quotation->id }}').submit(); })">
                                                <i class="feather-check-circle"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="action-dropdown-btn action-btn-reject flex-shrink-0" title="Reject" data-bs-toggle="tooltip" onclick="openRejectModal('{{ route('crm.quotations.reject', $quotation->id) }}', '{{ $quotation->quotation_number }}')">
                                            <i class="feather-x-circle"></i>
                                        </button>
                                    @elseif ($quotation->status === 'Accepted' && !$quotation->salesOrder)
                                        <a href="{{ route('sales.orders.create', ['quotation_id' => $quotation->id]) }}" class="action-dropdown-btn" title="Convert to Sales Order" data-bs-toggle="tooltip" style="color: #6366f1; border-color: #c7d2fe; background-color: #e0e7ff; text-decoration: none;">
                                            <i class="feather-shopping-cart"></i>
                                        </a>
                                    @endif

                                    <div class="flex-shrink-0">
                                        <x-ui.action-dropdown id="quotationActions-{{ $quotation->id }}">
                                            @if ($quotation->lead_id)
                                                <li>
                                                    <a href="{{ route('crm.leads.show', ['lead' => $quotation->lead_id, 'edit_quotation' => 1, 'active_quotation_id' => $quotation->id]) }}" class="dropdown-item">
                                                        <i class="feather-edit me-2 text-muted fs-12"></i>Edit Quotation
                                                    </a>
                                                </li>
                                            @endif

                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('crm.quotations.destroy', $quotation->id) }}" method="POST" id="deleteQuoForm_{{ $quotation->id }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger" onclick="confirmAction({ title: 'Delete Quotation', message: 'Are you sure you want to delete this quotation?', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deleteQuoForm_{{ $quotation->id }}').submit(); })">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete Quotation
                                                    </button>
                                                </form>
                                            </li>
                                        </x-ui.action-dropdown>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-file-text fs-1 mb-2 d-block"></i>
                                No quotations found in this tenant workspace.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$quotations->currentPage()" 
                :totalPages="$quotations->lastPage()" 
                :totalResults="$quotations->total()" 
                :perPage="$quotations->perPage()" />
        </div>
    </div>

    {{-- ── Quotation Detail Offcanvas Drawer ── --}}
    <x-ui.drawer id="quotationDetailOffcanvas" title="Quotation Details" position="end" style="width: 580px; max-width: 95vw;">
        <div id="quotationDetailContent">
            <div id="quotationDetailLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-2 fs-13">Loading details…</p>
            </div>
            <div id="quotationDetailBody" style="display:none;"></div>
        </div>

        <x-slot:footer>
            <a id="quotationFullViewLink" href="#" class="btn btn-outline-primary btn-sm">
                <i class="feather-external-link me-1"></i> View Full Details
            </a>
            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="offcanvas">
                <i class="feather-x me-1"></i> Close
            </button>
        </x-slot:footer>
    </x-ui.drawer>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        var quotationDrawer = new bootstrap.Offcanvas(document.getElementById('quotationDetailOffcanvas'));

        $(document).on('click', '.view-detail-btn', function (e) {
            e.preventDefault();
            var url = $(this).data('url');
            var showUrl = url.replace('/detail-partial', ''); // Infer the show URL

            $('#quotationDetailLoading').show();
            $('#quotationDetailBody').hide().html('');
            $('#quotationFullViewLink').attr('href', '#');
            quotationDrawer.show();

            $.get(url, function (data) {
                $('#quotationDetailLoading').hide();
                $('#quotationDetailBody').html(data).show();
                $('#quotationFullViewLink').attr('href', showUrl);
                $('[data-bs-toggle="tooltip"]', '#quotationDetailBody').each(function () {
                    new bootstrap.Tooltip(this);
                });
            }).fail(function () {
                $('#quotationDetailLoading').hide();
                $('#quotationDetailBody').html(
                    '<div class="alert alert-danger m-3"><i class="feather-alert-circle me-2"></i>Failed to load. Please try again.</div>'
                ).show();
            });
        });
    });
</script>
@endpush

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



@push('styles')
    <!-- Select2 Theme Styles -->
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Make select2 container compact for table layout */
        .select2-container--bootstrap-5 .select2-selection--single {
            padding: 2px 8px;
            height: auto;
            font-size: 11px;
            font-weight: 600;
        }
        /* Ensure status dropdown inside table has a fixed minimum width */
        .status-select + .select2-container {
            min-width: 160px !important;
            width: 160px !important;
        }
    </style>
@endpush

@push('scripts')
    <!-- Select2 Scripts -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(function () {
            // Auto submit status forms when changed in Select2
            $('.status-select').on('change', function() {
                $(this).closest('form').submit();
            });
        });

        function openRejectModal(actionUrl, quotationNumber = '') {
            $('#rejectQuotationForm').attr('action', actionUrl);
            if (quotationNumber) {
                $('#rejectModalQuotationNumber').text('(' + quotationNumber + ')');
            } else {
                $('#rejectModalQuotationNumber').text('');
            }
            $('#rejectionReasonInput').val('');
            const modalEl = document.getElementById('rejectQuotationModal');
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.show();
        }
    </script>

    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="rejectQuotationModal" tabindex="-1" aria-labelledby="rejectQuotationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <form id="rejectQuotationForm" method="POST" action="">
                    @csrf
                    <div class="modal-header bg-soft-danger text-danger border-bottom-0">
                        <h5 class="modal-title fw-bold" id="rejectQuotationModalLabel">
                            <i class="feather-x-circle me-2"></i>Reject Quotation <span id="rejectModalQuotationNumber" class="text-dark"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted fs-12 mb-3">Please specify the reason for rejecting this quotation. This reason will be saved in audit history and displayed on the quotation detail screen.</p>
                        
                        <div class="mb-3 text-start">
                            <label for="rejectionReasonInput" class="form-label fw-bold text-dark fs-12 mb-1">Rejection Reason / Remarks <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejectionReasonInput" name="rejection_reason" rows="4" placeholder="Enter reason for rejection (e.g., Price too high, Scope changed, Customer declined, etc.)..." required></textarea>
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
@endpush
