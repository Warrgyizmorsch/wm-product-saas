@extends('layouts.duralux')

@section('title', 'Employee Documents Registry | SaaS ERP')
@section('page-title', 'Employee Documents')
@section('breadcrumb', 'HRMS / Employee Documents')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-upload-cloud" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal" class="fw-bold text-uppercase">
            Upload Document
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .table-responsive {
            position: relative;
        }
        .table-responsive:has(.dropdown-menu.show) {
            overflow: visible !important;
        }
        .doc-status-toggle {
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 4px;
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 700;
        }
        .doc-status-toggle::after {
            margin-left: 6px !important;
            vertical-align: middle !important;
        }
        .status-dropdown-menu {
            min-width: 110px !important;
            width: 110px !important;
            max-width: 110px !important;
            padding: 4px 0 !important;
            border: 1px solid #ced4da !important;
            border-radius: 4px !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
            background: #ffffff !important;
        }
        .status-dropdown-menu li {
            padding: 0 !important;
            margin: 0 !important;
        }
        .status-dropdown-menu .dropdown-item {
            font-size: 12px !important;
            padding: 6px 12px !important;
            font-weight: 600 !important;
            background: transparent !important;
            border: none !important;
            width: 100% !important;
            text-align: left !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            transition: all 0.15s ease-in-out !important;
            border-radius: 0 !important;
            margin: 0 !important;
        }
        .status-dropdown-menu .dropdown-item:hover,
        .status-dropdown-menu .dropdown-item:focus {
            background-color: #f1f5f9 !important;
        }
        .status-dropdown-menu .dropdown-item.text-success {
            color: #10b981 !important;
        }
        .status-dropdown-menu .dropdown-item.text-danger {
            color: #ef4444 !important;
        }
        .employee-avatar-wrapper {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            background-color: #f1f5f9;
            border: 1.5px solid #e2e8f0;
        }
        .odoo-form-label {
            white-space: nowrap !important;
        }
        #uploadDocumentModal .odoo-form-label {
            width: 190px !important;
        }
        .file-upload-box {
            transition: all 0.2s ease-in-out;
        }
        .file-upload-box:hover {
            border-color: var(--bs-primary) !important;
            background-color: rgba(var(--bs-primary-rgb), 0.05) !important;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="feather-check-circle me-1.5"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="feather-alert-triangle me-1.5"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h6 class="fw-bold mb-2"><i class="feather-alert-octagon me-1"></i> Please fix the errors:</h6>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Main Registry Card -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-3 bg-white">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark" style="font-size: 15px;">All Employee Documents</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <form method="GET" action="{{ route('hrms.documents.index') }}" class="d-flex align-items-center gap-2 m-0">
                            <!-- Search Bar -->
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search employee or document..." value="{{ request('search') }}" style="box-shadow: none; height: 32px;">
                            </div>

                            <!-- Filter Options -->
                            <x-ui.filter label="Filter" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                
                                <div class="mb-3" style="min-width: 200px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                    <select name="status" class="form-select fs-12">
                                        <option value="">All Statuses</option>
                                        <option value="uploaded" {{ request('status') === 'uploaded' ? 'selected' : '' }}>Pending Verification</option>
                                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                        <option value="requested" {{ request('status') === 'requested' ? 'selected' : '' }}>Pending Upload</option>
                                    </select>
                                </div>

                                <div class="d-flex gap-2 justify-content-end mt-4">
                                    <a href="{{ route('hrms.documents.index') }}" class="btn btn-sm btn-light border">Reset</a>
                                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                </div>
                            </x-ui.filter>

                            @if(request()->anyFilled(['search', 'status']))
                                <a href="{{ route('hrms.documents.index') }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="Clear Filters">
                                    <i class="feather-x"></i>
                                </a>
                            @endif
                        </form>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive" style="overflow: visible;">
                        <table class="table table-hover align-middle mb-0" style="table-layout: fixed; width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 25%;">Employee</th>
                                    <th style="width: 23%;">Document & Category</th>
                                    <th style="width: 26%;">File Copy</th>
                                    <th style="width: 16%;">Status & Expiry</th>
                                    <th class="text-end pe-3" style="width: 10%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documents as $doc)
                                    @php
                                        $employee = $doc->documentable;
                                        $isExpired = $doc->expiry_date && $doc->expiry_date->isPast();
                                        $reminderDays = $doc->documentMaster?->reminder_days_before ?? 30;
                                        $isExpiringSoon = $doc->expiry_date && !$isExpired && now()->greaterThanOrEqualTo($doc->expiry_date->copy()->subDays($reminderDays));
                                        
                                        $displayStatus = $doc->status;
                                        $requiresApproval = $doc->documentMaster ? (bool)$doc->documentMaster->approval_required : true;
                                        if (!$requiresApproval && $displayStatus === 'uploaded') {
                                            $displayStatus = 'approved';
                                        }
                                    @endphp
                                    <tr>
                                        <!-- Employee column -->
                                        <td class="ps-3">
                                            @if($employee)
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="employee-avatar-wrapper flex-shrink-0 d-flex align-items-center justify-content-center">
                                                        @if(!empty($employee->photo))
                                                            <img src="{{ asset('storage/' . $employee->photo) }}" alt="" class="w-100 h-100 object-fit-cover">
                                                        @else
                                                            <span class="fw-bold text-primary fs-12">{{ strtoupper(substr($employee->full_name ?? 'E', 0, 2)) }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-truncate" style="line-height: 1.2;">
                                                        <div class="fw-bold text-dark fs-12 text-truncate" title="{{ $employee->full_name }}">{{ $employee->full_name }}</div>
                                                        <small class="text-muted fs-10">{{ $employee->employee_id }}</small>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-muted fs-11">N/A</span>
                                            @endif
                                        </td>

                                        <!-- Document Template column -->
                                        <td>
                                            <div class="fw-bold text-dark fs-13 text-truncate" title="{{ $doc->name }}">{{ $doc->name }}</div>
                                            <div class="mt-1">
                                                <span class="badge bg-soft-secondary text-secondary px-1.5 py-0.5 rounded fs-10" style="background-color: rgba(108, 117, 125, 0.08) !important; color: #6c757d !important; border: 1px solid rgba(108, 117, 125, 0.15);">
                                                    {{ $doc->documentMaster?->category?->name ?? 'Uncategorized' }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- File Column -->
                                        <td class="text-start">
                                            @if($doc->file_path)
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-white" style="width: 280px; max-width: 100%; height: 50px; border-color: #e2e8f0 !important; background-color: #f8fafc !important; margin-left: 0 !important; margin-right: auto !important;">
                                                    <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                                        <div class="d-flex align-items-center justify-content-center bg-white border rounded text-secondary flex-shrink-0" style="width: 32px; height: 32px;">
                                                            <i class="feather-file fs-15"></i>
                                                        </div>
                                                        <div class="text-start" style="line-height: 1.2; min-width: 0;">
                                                            <div class="fw-semibold text-dark text-truncate fs-12" style="max-width: 150px;" title="{{ $doc->file_name ?? basename($doc->file_path) }}">
                                                                {{ $doc->file_name ?? basename($doc->file_path) }}
                                                            </div>
                                                            <small class="text-muted fs-10">
                                                                @if($doc->file_size)
                                                                    {{ number_format($doc->file_size / 1024, 1) }} KB
                                                                @else
                                                                    N/A
                                                                @endif
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-1 ms-1 flex-shrink-0">
                                                        <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="btn btn-xs btn-white border rounded-circle p-0 d-inline-flex align-items-center justify-content-center text-muted hover-primary" style="width: 24px; height: 24px; background: #ffffff;" title="View Copy">
                                                            <i class="feather-eye fs-11"></i>
                                                        </a>
                                                        <a href="{{ asset('storage/' . $doc->file_path) }}" download class="btn btn-xs btn-white border rounded-circle p-0 d-inline-flex align-items-center justify-content-center text-muted hover-primary" style="width: 24px; height: 24px; background: #ffffff;" title="Download Copy">
                                                            <i class="feather-download fs-11"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 193, 7, 0.08) !important; color: #ff9800 !important; border: 1px solid rgba(255, 193, 7, 0.15); font-weight: 500;">
                                                    <i class="feather-clock fs-11"></i>
                                                    Pending Upload
                                                </span>
                                            @endif
                                        </td>

                                        <!-- Status and Expiry Column -->
                                        <td>
                                            <!-- Status Dropdown or Badge -->
                                            <div>
                                                @if($isExpired || $isExpiringSoon)
                                                    <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 193, 7, 0.08) !important; color: #ff9800 !important; border: 1px solid rgba(255, 193, 7, 0.15); font-weight: 500;">
                                                        <i class="feather-clock fs-11"></i>
                                                        Pending Upload
                                                    </span>
                                                @elseif($doc->status !== 'requested')
                                                    @if($requiresApproval)
                                                        <div class="dropdown d-inline-block">
                                                            <span class="dropdown-toggle doc-status-toggle fw-bold" 
                                                                  id="docStatusDropdown_{{ $doc->id }}" 
                                                                  data-bs-toggle="dropdown" 
                                                                  aria-expanded="false" 
                                                                  style="color: {{ $displayStatus === 'approved' ? '#10b981' : ($displayStatus === 'rejected' ? '#ef4444' : '#00bcd4') }};">
                                                                @if($displayStatus === 'approved')
                                                                    Approved
                                                                @elseif($displayStatus === 'rejected')
                                                                    Rejected
                                                                @else
                                                                    Pending Verification
                                                                @endif
                                                            </span>
                                                            <ul class="dropdown-menu dropdown-menu-start shadow-sm status-dropdown-menu mt-1" aria-labelledby="docStatusDropdown_{{ $doc->id }}" style="z-index: 1050;">
                                                                <li>
                                                                    <button type="button" class="dropdown-item fw-bold text-success d-flex align-items-center justify-content-between gap-2" 
                                                                            onclick="submitDocumentStatusDirect('{{ route('hrms.employees.documents.status', $doc->id) }}', 'approved'); return false;"
                                                                            style="background: transparent; border: none; width: 100%;">
                                                                        Approved
                                                                        @if($displayStatus === 'approved')
                                                                            <i class="feather-check text-success fs-14"></i>
                                                                        @endif
                                                                    </button>
                                                                </li>
                                                                <li>
                                                                    <button type="button" class="dropdown-item fw-bold text-danger d-flex align-items-center justify-content-between gap-2" 
                                                                            onclick="submitDocumentStatusDirect('{{ route('hrms.employees.documents.status', $doc->id) }}', 'rejected'); return false;"
                                                                            style="background: transparent; border: none; width: 100%;">
                                                                        Rejected
                                                                        @if($displayStatus === 'rejected')
                                                                            <i class="feather-check text-danger fs-14"></i>
                                                                        @endif
                                                                    </button>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    @else
                                                        <span class="fw-bold fs-13" style="color: #10b981; font-weight: 700; text-transform: uppercase;">
                                                            Approved
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 193, 7, 0.08) !important; color: #ff9800 !important; border: 1px solid rgba(255, 193, 7, 0.15); font-weight: 500;">
                                                        <i class="feather-clock fs-11"></i>
                                                        Pending Upload
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Expiry text -->
                                            <div class="mt-1 text-muted fs-11">
                                                @if($doc->expiry_date)
                                                    @if($isExpired)
                                                        <span class="text-danger fw-bold"><i class="feather-alert-triangle me-1"></i>Expired ({{ $doc->expiry_date->format('d M Y') }})</span>
                                                    @elseif($isExpiringSoon)
                                                        <span class="text-warning fw-bold"><i class="feather-clock me-1"></i>Near Expiry ({{ $doc->expiry_date->format('d M Y') }})</span>
                                                    @else
                                                        <span><i class="feather-clock me-1"></i>Expiry: {{ $doc->expiry_date->format('d M Y') }}</span>
                                                    @endif
                                                @elseif($doc->has_expiry)
                                                    <span class="text-warning"><i class="feather-alert-circle me-1"></i>Expiry Required</span>
                                                @else
                                                    <span class="text-success"><i class="feather-check-circle me-1"></i>No Expiry</span>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Actions Column -->
                                        <td class="text-end pe-3">
                                            <div class="d-flex align-items-center justify-content-end gap-1.5">
                                                 @if($doc->file_path)
                                                     <!-- Delete document attachment record -->
                                                     <form action="{{ route('hrms.employees.documents.destroy', $doc->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.employees.confirm_delete_document') }}', { title: '{{ __('hrms.employees.lbl_delete_document') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });" class="m-0 d-inline-flex" onclick="event.stopPropagation();">
                                                         @csrf
                                                         @method('DELETE')
                                                         <button type="submit" class="btn btn-sm btn-soft-danger border d-flex align-items-center justify-content-center p-0" style="border-radius: 8px; width: 32px; height: 32px; background: rgba(220, 53, 69, 0.05);" title="{{ __('hrms.common.delete') }}">
                                                             <i class="feather-trash-2 fs-13"></i>
                                                         </button>
                                                     </form>
                                                 @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-5 text-center text-muted">
                                            <i class="feather-file-text fs-24 mb-2 d-block"></i>
                                            No documents found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($documents->hasPages())
                    @php
                        $currentPage = $documents->currentPage();
                        $totalPages = $documents->lastPage();
                        $totalResults = $documents->total();
                        $perPage = $documents->perPage();
                    @endphp
                    <div class="card-footer p-0">
                        <x-ui.pagination 
                            class="px-4 py-3 border-top"
                            :current-page="$currentPage"
                            :total-pages="$totalPages"
                            :total-results="$totalResults"
                            :per-page="$perPage"
                        />
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Hidden form for status submission -->
    <form id="directStatusForm" method="POST" style="display: none;">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" id="directStatusInput">
    </form>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark fs-15" id="uploadDocumentModalLabel">Upload Document File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.documents.bulk-upload') }}" method="POST" enctype="multipart/form-data" id="bulkUploadForm" novalidate onsubmit="return validateBulkUploadForm(event, this);">
                    @csrf
                    <div class="modal-body">
                        <!-- Select Employee (Dropdown) -->
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Select Employee" name="employee_id" id="employee_id" :required="true" select2-selector="default">
                                <option value="all" selected>All Employees</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                            <div class="error-msg text-danger fs-10 mt-1 d-none" style="font-weight: 600;">Please select an employee.</div>
                        </div>

                        <!-- Document Category Selection -->
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Document Category" name="document_category_id" id="document_category_id" :required="true" select2-selector="default" onchange="filterTemplatesByCategory(this)">
                                <option value="">Choose Category...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                            <div class="error-msg text-danger fs-10 mt-1 d-none" style="font-weight: 600;">Please choose a document category.</div>
                        </div>

                        <!-- Document Template Selection -->
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Document Template" name="document_master_id" id="document_master_id" :required="true" select2-selector="default" disabled="disabled">
                                <option value="">Choose Template...</option>
                                @foreach($templates as $tmpl)
                                    <option value="{{ $tmpl->id }}" data-category-id="{{ $tmpl->document_category_id }}" data-expiry="{{ $tmpl->expiry_applicable ? '1' : '0' }}">{{ $tmpl->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                            <div class="error-msg text-danger fs-10 mt-1 d-none" style="font-weight: 600;">Please choose a document template.</div>
                        </div>

                        <!-- File Upload -->
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="file" label="File Attachment" name="file" id="modal_upload_file" :required="true" />
                            <div class="error-msg text-danger fs-10 mt-1 d-none" style="font-weight: 600;">Please select a file.</div>
                        </div>

                        <!-- Expiry Date -->
                        <div class="mb-3 d-none" id="expiry-date-container">
                            <x-ui.odoo-form-ui type="input" label="Expiry Date" name="expiry_date" id="modal_expiry_date" inputType="date" />
                            <div class="error-msg text-danger fs-10 mt-1 d-none" style="font-weight: 600;">Expiry date is required for this template.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border fw-bold text-uppercase fs-11" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary fw-bold text-uppercase fs-11">Submit Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script>
        // Dropdown status submission handler
        function submitDocumentStatusDirect(url, status) {
            var form = document.getElementById('directStatusForm');
            var input = document.getElementById('directStatusInput');
            form.action = url;
            input.value = status;
            form.submit();
        }

        // Filter templates based on selected category
        function filterTemplatesByCategory(categorySelect) {
            var selectedCategoryId = $(categorySelect).val();
            var $templateSelect = $('#document_master_id');

            // Clear template options and add placeholder
            $templateSelect.empty().append('<option value="">Choose Template...</option>');

            // Reset validation/expiry state
            $templateSelect.removeClass('is-invalid');
            $templateSelect.closest('.mb-3').find('.error-msg').addClass('d-none');
            
            var $expiryInput = $('#modal_expiry_date');
            $expiryInput.removeClass('is-invalid');
            var $expiryContainer = $('#expiry-date-container');
            $expiryContainer.addClass('d-none');
            $expiryContainer.find('.error-msg').addClass('d-none');
            $expiryInput.val('');
            var $label = $expiryContainer.find('.odoo-form-label');
            $label.removeClass('text-danger').html('Expiry Date');
            $expiryInput.prop('required', false);

            if (!selectedCategoryId) {
                $templateSelect.prop('disabled', true);
            } else {
                $templateSelect.prop('disabled', false);
                // Filter and append matching templates
                if (window.allDocumentTemplates) {
                    window.allDocumentTemplates.forEach(function(tmpl) {
                        if (String(tmpl.categoryId) === String(selectedCategoryId)) {
                            var option = $('<option></option>')
                                .val(tmpl.id)
                                .text(tmpl.text)
                                .attr('data-category-id', tmpl.categoryId)
                                .attr('data-expiry', tmpl.expiry);
                            $templateSelect.append(option);
                        }
                    });
                }
            }

            // Refresh Select2 dropdown to reflect new option tags
            if ($.fn.select2) {
                $templateSelect.trigger('change');
            }
        }

        // Toggle required expiry color/text when template selection changes
        function checkExpiryRequirement(select) {
            var selectedOption = $(select).find('option:selected');
            var requiresExpiry = selectedOption.data('expiry') == '1';
            
            var $expiryInput = $('#modal_expiry_date');
            var $container = $('#expiry-date-container');
            var $label = $container.find('.odoo-form-label');

            // Reset errors
            $expiryInput.removeClass('is-invalid').removeClass('border-danger');
            $container.find('.error-msg').addClass('d-none');
            $expiryInput.val('');

            if (requiresExpiry) {
                $container.removeClass('d-none');
                $label.addClass('text-danger').html('Expiry Date <span class="text-danger">*</span>');
                $expiryInput.prop('required', true);
            } else {
                $container.addClass('d-none');
                $label.removeClass('text-danger').html('Expiry Date');
                $expiryInput.prop('required', false);
            }
        }

        // Modal client-side form validation handler
        function validateBulkUploadForm(e, form) {
            var isValid = true;
            var $form = $(form);

            // Clear previous errors
            $form.find('.error-msg').addClass('d-none');
            $form.find('input, select').removeClass('is-invalid').removeClass('border-danger');

            // 1. Validate employee_id choice
            var selectedEmployee = $('#employee_id').val();
            if (!selectedEmployee) {
                var $selectContainer = $('#employee_id').closest('.mb-3');
                $selectContainer.find('.error-msg').removeClass('d-none');
                $('#employee_id').addClass('is-invalid');
                isValid = false;
            }

            // 2. Validate category choice
            var categoryId = $('#document_category_id').val();
            if (!categoryId) {
                var $catContainer = $('#document_category_id').closest('.mb-3');
                $catContainer.find('.error-msg').removeClass('d-none');
                $('#document_category_id').addClass('is-invalid');
                isValid = false;
            }

            // 3. Validate document template choice
            var templateId = $('#document_master_id').val();
            if (!templateId) {
                var $tmplContainer = $('#document_master_id').closest('.mb-3');
                $tmplContainer.find('.error-msg').removeClass('d-none');
                $('#document_master_id').addClass('is-invalid');
                isValid = false;
            }

            // 4. Validate file choosing
            var fileInput = $form.find('input[type="file"]')[0];
            if (!fileInput.files || fileInput.files.length === 0) {
                var $fileContainer = $(fileInput).closest('.mb-3');
                $fileContainer.find('.error-msg').removeClass('d-none');
                $fileContainer.find('.form-control').addClass('is-invalid');
                isValid = false;
            }

            // 5. Validate dynamic expiry requirements
            var selectedTemplate = $('#document_master_id').find('option:selected');
            var requiresExpiry = selectedTemplate.data('expiry') == '1';
            var expiryDate = $('#modal_expiry_date').val();

            if (requiresExpiry && !expiryDate) {
                var $expiryContainer = $('#modal_expiry_date').closest('.mb-3');
                $expiryContainer.find('.error-msg').removeClass('d-none');
                $('#modal_expiry_date').addClass('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                return false;
            }
            return true;
        }

        $(document).ready(function() {
            // Append modal to body to bypass layout clipping
            $('#uploadDocumentModal').appendTo('body');

            // Cache all templates options dynamically on page load
            var allTemplates = [];
            $('#document_master_id option').each(function() {
                var val = $(this).val();
                if (val !== '') {
                    allTemplates.push({
                        id: val,
                        text: $(this).text(),
                        categoryId: $(this).data('category-id'),
                        expiry: $(this).data('expiry')
                    });
                }
            });
            window.allDocumentTemplates = allTemplates;

            // Handle clean clearing when inputs receive value
            $('#employee_id').on('change', function() {
                if ($(this).val()) {
                    var $container = $(this).closest('.mb-3');
                    $(this).removeClass('is-invalid');
                    $container.find('.error-msg').addClass('d-none');
                }
            });

            $('#document_category_id').on('change', function() {
                if ($(this).val()) {
                    var $container = $(this).closest('.mb-3');
                    $(this).removeClass('is-invalid');
                    $container.find('.error-msg').addClass('d-none');
                }
            });

            $('#document_master_id').on('change', function() {
                if ($(this).val()) {
                    var $container = $(this).closest('.mb-3');
                    $(this).removeClass('is-invalid');
                    $container.find('.error-msg').addClass('d-none');
                    checkExpiryRequirement(this);
                }
            });

            $('#modal_expiry_date').on('change input', function() {
                if ($(this).val()) {
                    var $container = $(this).closest('.mb-3');
                    $(this).removeClass('is-invalid');
                    $container.find('.error-msg').addClass('d-none');
                }
            });
        });
    </script>
@endpush
