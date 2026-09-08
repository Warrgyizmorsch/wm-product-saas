<style>
    .modal .odoo-form-label {
        width: 160px !important;
    }
    .modal .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding-right: 24px !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
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
    .modal select.form-select {
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        color: #1e293b !important;
        background-color: #ffffff !important;
        font-size: 13px !important;
    }
    .modal select.form-select:focus {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb, 59, 130, 246), 0.2) !important;
        outline: none !important;
    }
    .modal select.form-select option {
        padding: 8px 12px !important;
        background-color: #ffffff !important;
        color: #1e293b !important;
    }
</style>
<div class="tab-pane fade {{ $activeTabName === 'documents' ? 'show active' : '' }}" id="documents-pane" role="tabpanel" aria-labelledby="documents-tab">
    <div class="row">
        <div class="col-12">
            <div class="card-custom">
                <div class="card-custom-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h5 class="card-custom-title"><i class="feather-file-text text-primary"></i> {{ __('hrms.employees.lbl_doc_registry') }}</h5>
                        <small class="text-muted d-block mt-1">{{ __('hrms.employees.lbl_doc_registry_desc') }}</small>
                    </div>
                    <div class="documents-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                        <div class="documents-search d-flex align-items-center px-3 py-1">
                            <i class="feather-search text-muted me-2 fs-14"></i>
                            <input 
                                type="text" 
                                id="documentSearchInput" 
                                class="form-control border-0 bg-transparent p-0 fs-13" 
                                placeholder="{{ __('hrms.employees.lbl_search_docs') }}" 
                                autocomplete="off"
                                style="box-shadow: none; height: 32px;"
                            >
                        </div>

                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                            <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" data-sort="title_asc">
                                <span>{{ __('hrms.employees.lbl_doc_title_asc') }}</span>
                                <i class="feather-check ms-3"></i>
                            </a>
                            <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="title_desc">
                                <span>{{ __('hrms.employees.lbl_doc_title_desc') }}</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="expiry_asc">
                                <span>{{ __('hrms.employees.lbl_expiry_soonest') }}</span>
                            </a>
                            <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="expiry_desc">
                                <span>{{ __('hrms.employees.lbl_expiry_latest') }}</span>
                            </a>
                        </x-ui.sort-dropdown>

                        <x-ui.filter label="{{ __('hrms.common.filter') }}">
                            <div class="document-filter-panel">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                            <form id="documentFilterForm" onsubmit="return false;">
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="status">
                                        <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                        <option value="uploaded">{{ __('hrms.employees.lbl_uploaded') }}</option>
                                        <option value="requested">{{ __('hrms.employees.lbl_pending_upload') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.lbl_expiry_req') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="has_expiry">
                                        <option value="">{{ __('hrms.employees.tbl_actions') }} - {{ __('hrms.common.filter') }}</option>
                                        <option value="1">{{ __('hrms.employees.lbl_has_expiry') }}</option>
                                        <option value="0">{{ __('hrms.employees.lbl_no_expiry') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="dropdown-divider my-3"></div>
                                <div class="d-flex gap-2">
                                    <x-ui.button type="button" id="btnDocumentFilterApply" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.common.apply') }}</x-ui.button>
                                    <x-ui.button type="button" id="btnDocumentFilterReset" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
                                </div>
                            </form>
                            </div>
                        </x-ui.filter>

                        <x-ui.button type="button" variant="primary" class="fw-bold text-uppercase d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                            <i class="feather-upload-cloud"></i> {{ __('hrms.employees.btn_upload') }}
                        </x-ui.button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="overflow: visible;">
                        <table class="table table-hover align-middle mb-0 documents-table" id="documentsTable" style="table-layout: fixed; width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 32%;">{{ __('hrms.employees.tbl_doc_title') }}</th>
                                    <th style="width: 18%;">{{ __('hrms.employees.tbl_source_expiry') }}</th>
                                    <th style="width: 26%;">{{ __('hrms.employees.tbl_file') }}</th>
                                    <th style="width: 16%;">{{ __('hrms.employees.tbl_status') }}</th>
                                    <th class="text-end pe-3" style="width: 8%;">{{ __('hrms.employees.tbl_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documents as $doc)
                                    @php
                                         $isExpired = $doc->expiry_date && $doc->expiry_date->isPast();
                                         $reminderDays = $doc->documentMaster?->reminder_days_before ?? 30;
                                         $isExpiringSoon = $doc->expiry_date && !$isExpired && now()->greaterThanOrEqualTo($doc->expiry_date->copy()->subDays($reminderDays));
                                        
                                        $rowStatus = $isExpired ? 'requested' : $doc->status;
                                        $rowHasExpiry = $doc->expiry_date ? '1' : '0';

                                        $requiresApproval = true;
                                        if ($doc->documentMaster) {
                                            $requiresApproval = (bool) $doc->documentMaster->approval_required;
                                        }
                                        
                                        $displayStatus = $doc->status;
                                        if ($doc->file_path && $displayStatus === 'requested') {
                                            $displayStatus = 'uploaded';
                                        }
                                        if (!$requiresApproval && ($displayStatus === 'uploaded' || $displayStatus === 'requested')) {
                                            $displayStatus = 'approved';
                                        }
                                    @endphp
                                    <tr class="document-row" 
                                        data-title="{{ strtolower($doc->name) }}" 
                                        data-search="{{ strtolower($doc->name) }}" 
                                        data-status="{{ $rowStatus }}" 
                                        data-has-expiry="{{ $rowHasExpiry }}"
                                        data-expiry="{{ $doc->expiry_date ? $doc->expiry_date->timestamp : 9999999999 }}"
                                        data-title-raw="{{ $doc->name }}">
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark fs-14 mb-1" style="word-break: break-word; white-space: normal; line-height: 1.4;" title="{{ $doc->name }}">{{ $doc->name }}</div>
                                            @if($doc->description)
                                                <div class="doc-desc-wrapper" style="max-width: 100%;">
                                                    <div class="text-muted fs-12 mb-0 doc-desc-text" 
                                                         style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; white-space: normal; line-height: 1.3;"
                                                         title="{{ $doc->description }}">{{ $doc->description }}</div>
                                                    <a href="#" class="doc-toggle-text-btn fs-11 text-primary fw-semibold d-none mt-0.5" onclick="toggleDocText(this); return false;">See more</a>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge bg-light text-dark px-2 py-1 fs-11 d-inline-flex align-items-center gap-1 border">
                                                    <i class="feather-user fs-11"></i>
                                                    {{ $doc->requestedBy?->name ?? 'System' }}
                                                </span>
                                            </div>
                                            <div class="mt-1.5">
                                                @if($doc->expiry_date)
                                                    @if($isExpired)
                                                        <span class="badge bg-soft-danger text-danger px-2 py-1 fs-11 d-inline-flex align-items-center gap-1">
                                                            <i class="feather-alert-triangle fs-11"></i>
                                                            {{ __('hrms.employees.lbl_expired') }} ({{ $doc->expiry_date->format('d M Y') }})
                                                        </span>
                                                    @elseif($isExpiringSoon)
                                                        <span class="badge px-2 py-1 fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 193, 7, 0.1) !important; color: #ff9800 !important; border: 1px solid rgba(255, 152, 0, 0.2);">
                                                            <i class="feather-clock fs-11"></i>
                                                            {{ __('hrms.employees.lbl_near_expiry') ?? 'Near Expiry' }} ({{ $doc->expiry_date->format('d M Y') }})
                                                        </span>
                                                    @else
                                                        <span class="badge bg-soft-secondary text-dark px-2 py-1 fs-11 d-inline-flex align-items-center gap-1 border">
                                                            <i class="feather-clock fs-11"></i>
                                                            {{ __('hrms.employees.lbl_expiry') ?? 'Expiry' }} ({{ $doc->expiry_date->format('d M Y') }})
                                                        </span>
                                                    @endif
                                                @elseif($doc->has_expiry)
                                                    <span class="badge px-2 py-1 fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 193, 7, 0.1) !important; color: #ff9800 !important; border: 1px solid rgba(255, 152, 0, 0.2);">
                                                        <i class="feather-alert-circle fs-11"></i>
                                                        {{ __('hrms.employees.lbl_expiry_required') }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-soft-success text-success px-2 py-1 fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(40, 167, 69, 0.08) !important; color: #28a745 !important; border: 1px solid rgba(40, 167, 69, 0.15);">
                                                        <i class="feather-check-circle fs-11"></i>
                                                        {{ __('hrms.employees.lbl_no_expiry') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
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
                                                         @if(($doc->documentMaster?->employee_can_view ?? true) || $doc->file_path)
                                                             <a href="{{ $doc->is_signed ? route('hrms.employees.documents.view-signed', $doc->id) : asset('storage/' . $doc->file_path) }}" target="_blank" class="btn btn-xs btn-white border rounded-circle p-0 d-inline-flex align-items-center justify-content-center text-muted hover-primary" style="width: 24px; height: 24px; background: #ffffff;" title="{{ __('hrms.common.view') }}">
                                                                 <i class="feather-eye fs-11"></i>
                                                             </a>
                                                         @endif
                                                         @if($isExpired || $isExpiringSoon)
                                                             <a href="#" class="btn btn-xs btn-white border rounded-circle p-0 d-inline-flex align-items-center justify-content-center text-muted hover-primary" style="width: 24px; height: 24px; background: #ffffff;" title="{{ __('hrms.employees.lbl_reupload') ?? 'Reupload' }}" onclick="toggleInlineUploadForm('{{ $doc->id }}'); return false;">
                                                                 <i class="feather-refresh-cw fs-10"></i>
                                                             </a>
                                                         @endif
                                                         @if(($doc->documentMaster?->employee_can_download ?? true) || $doc->file_path)
                                                             <a href="{{ asset('storage/' . ($doc->signed_file_path ?: $doc->file_path)) }}" download class="btn btn-xs btn-white border rounded-circle p-0 d-inline-flex align-items-center justify-content-center text-muted hover-primary" style="width: 24px; height: 24px; background: #ffffff;" title="{{ __('hrms.employees.lbl_download_doc') ?? 'Download' }}">
                                                                 <i class="feather-download fs-11"></i>
                                                             </a>
                                                         @endif
                                                         @if($doc->status === 'pending_signature')
                                                             <button type="button" class="btn btn-xs btn-warning text-dark fw-bold px-2 py-0.5 d-inline-flex align-items-center gap-1 border-0 rounded-pill ms-1" 
                                                                     onclick="openSignModal('{{ $doc->id }}', '{{ route('hrms.employees.documents.sign', $doc->id) }}', '{{ e($doc->name) }}', '{{ asset('storage/' . $doc->file_path) }}', '{{ strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION)) }}')" 
                                                                     title="Sign Document Now">
                                                                 <i class="feather-edit-3 fs-10"></i> Sign
                                                             </button>
                                                         @endif
                                                    </div>
                                                </div>
                                                @if($isExpired)
                                                    <div class="text-danger fw-bold fs-11 mt-1.5"><i class="feather-alert-triangle me-1"></i>{{ __('hrms.employees.lbl_document_expired') }}</div>
                                                @endif
                                                @if($isExpired || $isExpiringSoon)
                                                    <div id="inline-upload-container-{{ $doc->id }}" class="d-none mt-2">
                                                        <form action="{{ route('hrms.employees.documents.upload', $employee->id) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-1.5 inline-upload-form" style="width: 280px; max-width: 100%; margin-left: 0 !important; margin-right: auto !important;" novalidate onsubmit="return validateInlineUploadForm(event, this);">
                                                            @csrf
                                                            <input type="hidden" name="document_id" value="{{ $doc->id }}">
                                                            <input type="hidden" name="name" value="{{ $doc->name }}">
                                                            
                                                            <div class="d-flex flex-column">
                                                                <div class="d-flex align-items-center gap-1.5">
                                                                    <div class="position-relative flex-grow-1" style="min-width: 0;">
                                                                        <input type="file" name="file" class="position-absolute opacity-0 inline-file-input" style="left: 0; top: 0; width: 100%; height: 100%; cursor: pointer; z-index: 2;" onchange="updateInlineFileName(this)" required>
                                                                        <div class="form-control d-flex align-items-center gap-1.5 fs-11 text-muted text-truncate px-2 file-upload-box" style="border: 1px dashed rgba(var(--bs-primary-rgb), 0.25); background-color: rgba(var(--bs-primary-rgb), 0.02); height: 32px; border-radius: 8px;">
                                                                            <i class="feather-upload-cloud fs-13 flex-shrink-0"></i>
                                                                            <span class="file-name-label text-truncate">{{ __('hrms.employees.lbl_choose_file') ?? 'Choose File' }}</span>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <button type="submit" class="btn btn-sm text-white fw-bold d-inline-flex align-items-center gap-1 px-2.5 flex-shrink-0" style="background-color: var(--bs-primary) !important; font-size: 10.5px; height: 32px; border-radius: 8px; border: none; white-space: nowrap;">
                                                                        <i class="feather-upload-cloud fs-11"></i> {{ __('hrms.employees.btn_upload') }}
                                                                    </button>
                                                                </div>
                                                                <div class="file-error-msg text-danger fs-10 mt-1 d-none" style="font-weight: 600;">Please select a file.</div>
                                                            </div>

                                                            @if($doc->has_expiry)
                                                                <div class="d-flex flex-column mt-1">
                                                                    <div class="d-flex align-items-center gap-1.5">
                                                                        <span class="text-muted fw-bold fs-9 text-uppercase" style="white-space: nowrap;">{{ __('hrms.employees.lbl_expiry') ?? 'EXPIRY' }}:</span>
                                                                        <input type="date" name="expiry_date" class="form-control py-0 px-2 fs-11 text-muted border inline-expiry-input" style="height: 26px; border-radius: 6px; width: 120px;" required>
                                                                    </div>
                                                                    <div class="expiry-error-msg text-danger fs-10 mt-1 d-none" style="font-weight: 600;">Expiry date is required.</div>
                                                                </div>
                                                            @endif
                                                        </form>
                                                    </div>
                                                @endif
                                            @else
                                                <form action="{{ route('hrms.employees.documents.upload', $employee->id) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-1.5 inline-upload-form" style="width: 280px; max-width: 100%; margin-left: 0 !important; margin-right: auto !important;" novalidate onsubmit="return validateInlineUploadForm(event, this);">
                                                    @csrf
                                                    <input type="hidden" name="document_id" value="{{ $doc->id }}">
                                                    <input type="hidden" name="name" value="{{ $doc->name }}">
                                                    
                                                    <div class="d-flex flex-column">
                                                        <div class="d-flex align-items-center gap-1.5">
                                                            <div class="position-relative flex-grow-1" style="min-width: 0;">
                                                                <input type="file" name="file" class="position-absolute opacity-0 inline-file-input" style="left: 0; top: 0; width: 100%; height: 100%; cursor: pointer; z-index: 2;" onchange="updateInlineFileName(this)" required>
                                                                <div class="form-control d-flex align-items-center gap-1.5 fs-11 text-muted text-truncate px-2 file-upload-box" style="border: 1px dashed rgba(var(--bs-primary-rgb), 0.25); background-color: rgba(var(--bs-primary-rgb), 0.02); height: 32px; border-radius: 8px;">
                                                                    <i class="feather-upload-cloud fs-13 flex-shrink-0"></i>
                                                                    <span class="file-name-label text-truncate">{{ __('hrms.employees.lbl_choose_file') ?? 'Choose File' }}</span>
                                                                </div>
                                                            </div>
                                                            
                                                            <button type="submit" class="btn btn-sm text-white fw-bold d-inline-flex align-items-center gap-1 px-2.5 flex-shrink-0" style="background-color: var(--bs-primary) !important; font-size: 10.5px; height: 32px; border-radius: 8px; border: none; white-space: nowrap;">
                                                                <i class="feather-upload-cloud fs-11"></i> {{ __('hrms.employees.btn_upload') }}
                                                            </button>
                                                        </div>
                                                        <div class="file-error-msg text-danger fs-10 mt-1 d-none" style="font-weight: 600;">Please select a file.</div>
                                                    </div>

                                                    @if($doc->has_expiry)
                                                        <div class="d-flex flex-column mt-1">
                                                            <div class="d-flex align-items-center gap-1.5">
                                                                <span class="text-muted fw-bold fs-9 text-uppercase" style="white-space: nowrap;">{{ __('hrms.employees.lbl_expiry') ?? 'EXPIRY' }}:</span>
                                                                <input type="date" name="expiry_date" class="form-control py-0 px-2 fs-11 text-muted border inline-expiry-input" style="height: 26px; border-radius: 6px; width: 120px;" required>
                                                            </div>
                                                            <div class="expiry-error-msg text-danger fs-10 mt-1 d-none" style="font-weight: 600;">Expiry date is required.</div>
                                                        </div>
                                                    @endif
                                                </form>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$doc->file_path || $isExpired)
                                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 193, 7, 0.08) !important; color: #ff9800 !important; border: 1px solid rgba(255, 193, 7, 0.15); font-weight: 500;">
                                                    <i class="feather-clock fs-11"></i>
                                                    {{ __('hrms.employees.lbl_pending_upload') }}
                                                </span>
                                            @elseif($doc->status === 'pending_signature')
                                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 193, 7, 0.1) !important; color: #d97706 !important; border: 1px solid rgba(245, 158, 11, 0.2); font-weight: 600;">
                                                    <i class="feather-edit-3 fs-11"></i> Pending Signature
                                                </span>
                                            @else
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
                                                @if($doc->is_signed)
                                                    <div class="mt-0.5">
                                                        <span class="badge bg-soft-success text-success px-1.5 py-0.5 rounded fs-9" title="Digitally Signed on {{ $doc->signed_at?->format('d M Y, H:i') }}">
                                                            <i class="feather-check-circle fs-9 me-0.5"></i> Digitally Signed
                                                        </span>
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex align-items-center justify-content-end gap-2">

                                                
                                                <form action="{{ route('hrms.employees.documents.destroy', $doc->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.employees.confirm_delete_document') }}', { title: '{{ __('hrms.employees.lbl_delete_document') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });" class="m-0 d-inline-flex" onclick="event.stopPropagation();">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-soft-danger border d-flex align-items-center justify-content-center p-0" style="border-radius: 8px; width: 32px; height: 32px; background: rgba(220, 53, 69, 0.05);" title="{{ __('hrms.common.delete') }}">
                                                        <i class="feather-trash-2 fs-13"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="documentEmptyStateRow">
                                        <td colspan="5" class="text-center py-5 text-muted fs-13">
                                            <i class="feather-file-text d-block fs-32 text-light-muted mb-2" style="font-size: 28px;"></i>
                                            No documents found.
                                        </td>
                                    </tr>
                                @endforelse
                                <tr id="documentNoResultsRow" class="d-none">
                                    <td colspan="5" class="text-center py-5 text-muted fs-13">
                                        <i class="feather-folder-minus d-block fs-32 text-light-muted mb-2"></i>
                                        {{ __('hrms.employees.lbl_no_docs_match') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    @if($documents->hasPages())
                        <div class="erp-pagination-container border-top py-3 px-3">
                            <x-ui.pagination 
                                :currentPage="$documents->currentPage()" 
                                :totalPages="$documents->lastPage()" 
                                :totalResults="$documents->total()" 
                                :perPage="$documents->perPage()" 
                                pageParam="doc_page" 
                                tab="documents" 
                            />
                        </div>
                    @endif
            </div>
        </div>
    </div>
</div>
</div>

    <!-- DIRECT UPLOAD DOCUMENT MODAL -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="uploadDocumentModalLabel">
                        <i class="feather-upload-cloud me-2 text-primary" style="font-size: 16px;"></i>{{ __('hrms.employees.mdl_upload_doc_title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.documents.upload', $employee->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="document_id" id="upload_doc_modal_document_id" value="">
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Selected Document Template (For new uploads) -->
                            <div class="col-12" id="upload_template_select_group">
                                <x-ui.odoo-form-ui type="select" label="Document Template" name="document_master_id" id="upload_document_master_id" :required="true" select2-selector="default">
                                    <option value="">-- Select Document Template --</option>
                                    @foreach($documentMasters as $master)
                                        <option value="{{ $master->id }}" data-expiry-applicable="{{ $master->expiry_applicable ? 1 : 0 }}">
                                            {{ $master->name }} ({{ $master->code }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            
                            <!-- Static Label for Document Name (For requested/existing uploads) -->
                            <div class="col-12" id="upload_document_name_label_group" style="display: none;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Document Name</label>
                                <div id="upload_document_name_label" class="fw-bold text-dark fs-14 py-1"></div>
                            </div>
                            
                            <!-- File Upload Input -->
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="file" label="{{ __('hrms.employees.mdl_select_file') }}" name="file" :required="true" placeholder="{{ __('hrms.employees.mdl_select_file_placeholder') }}" />
                            </div>
                            
                            <!-- Expiry Date Input (Dynamically shown/hidden) -->
                            <div class="col-12" id="upload_expiry_date_group" style="display: none;">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_expiry_date') }}" name="expiry_date" id="upload_expiry_date" inputType="date" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_upload_file') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
    function validateInlineUploadForm(event, form) {
        var $form = $(form);
        var isValid = true;
        
        // Reset previous errors
        $form.find('.file-error-msg').addClass('d-none');
        $form.find('.expiry-error-msg').addClass('d-none');
        $form.find('.file-upload-box').css('border-color', '');
        $form.find('.inline-expiry-input').removeClass('is-invalid');
        
        // Validate File Input
        var fileInput = $form.find('.inline-file-input');
        if (fileInput.length && (!fileInput[0].files || !fileInput[0].files.length)) {
            $form.find('.file-error-msg').removeClass('d-none');
            $form.find('.file-upload-box').css('border-color', '#dc3545');
            isValid = false;
        }
        
        // Validate Expiry Date
        var expiryInput = $form.find('.inline-expiry-input');
        if (expiryInput.length && expiryInput.prop('required') && !expiryInput.val()) {
            $form.find('.expiry-error-msg').removeClass('d-none');
            expiryInput.addClass('is-invalid');
            isValid = false;
        }
        
        if (!isValid) {
            event.preventDefault();
            return false;
        }
        return true;
    }

    // Reset validation styles on interaction
    $(document).on('change', '.inline-file-input', function() {
        var $form = $(this).closest('form');
        $form.find('.file-error-msg').addClass('d-none');
        $form.find('.file-upload-box').css('border-color', '');
    });

    $(document).on('input change', '.inline-expiry-input', function() {
        var $form = $(this).closest('form');
        $form.find('.expiry-error-msg').addClass('d-none');
        $(this).removeClass('is-invalid');
    });
</script>

<!-- SIGN DOCUMENT CANVAS MODAL (STANDARD FORMAT) -->
<div class="modal fade" id="signDocumentCanvasModal" tabindex="-1" aria-labelledby="signDocumentCanvasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2.5">
                <h5 class="modal-title fw-bold text-dark" id="signDocumentCanvasModalLabel" style="font-size: 15px;">
                    <i class="feather-edit-3 me-2 text-warning"></i>Sign Document
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="signDocumentForm" method="POST" action="">
                @csrf
                <input type="hidden" name="signature_image" id="modal_signature_image_input">
                <div class="modal-body p-3">
                    <div class="mb-2">
                        <span class="text-muted fs-12">Document Name:</span>
                        <span id="sign_modal_doc_title" class="fw-bold text-primary fs-14 ms-1"></span>
                    </div>

                    <!-- DOCUMENT PREVIEW (FULL SCROLLABLE PDF/IMAGE) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-12 text-dark mb-1"><i class="feather-eye text-primary me-1"></i> Document Preview:</label>
                        <div id="modal_doc_preview_wrapper" class="position-relative border rounded bg-light" style="height: 380px; border-color: #cbd5e1 !important;">
                            <div id="modal_doc_preview_container" class="h-100 w-100 overflow-auto">
                                <div class="d-flex align-items-center justify-content-center h-100 text-muted fs-13">
                                    <i class="feather-file me-1"></i> Loading preview...
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-bold fs-12 text-dark mb-1">Signature Placement Line <span class="text-danger">*</span></label>
                            <select name="signature_position" id="modal_signature_position_select" class="form-select fs-13 py-2 rounded-3">
                                <option value="bottom_left">Employee Signature Line (Bottom Left)</option>
                                <option value="bottom_right" selected>Authorized Signatory Line (Bottom Right)</option>
                                <option value="bottom_center">Bottom Center Signature Line</option>
                            </select>
                            <small class="text-muted fs-11 mt-1 d-block"><i class="feather-layout me-1"></i> Signature will be stamped on this line.</small>
                        </div>
                        <div class="col-md-7">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold fs-12 text-dark mb-0">Signature Input <span class="text-danger">*</span></label>
                                <ul class="nav nav-pills bg-light p-1 rounded-pill border gap-1" id="signatureInputTabs" role="tablist">
                                    <li class="nav-item">
                                        <button type="button" id="btn_sig_type_draw" class="nav-link btn-xs py-1 px-3 fs-11 fw-bold rounded-pill active border-0 btn-primary" style="background-color: var(--bs-primary) !important; color: #ffffff !important;" onclick="switchSigMode('draw')">
                                            <i class="feather-edit-2 me-1"></i> Draw Signature
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button type="button" id="btn_sig_type_upload" class="nav-link btn-xs py-1 px-3 fs-11 fw-bold text-secondary rounded-pill border-0" onclick="switchSigMode('upload')">
                                            <i class="feather-upload-cloud me-1"></i> Upload Image
                                        </button>
                                    </li>
                                </ul>
                            </div>

                            <!-- DRAW SIGNATURE TAB -->
                            <div id="sig_draw_container">
                                <div class="d-flex justify-content-end mb-1">
                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="clearSignatureCanvas()">
                                        <i class="feather-rotate-ccw me-1"></i>Clear
                                    </button>
                                </div>
                                <div class="border rounded bg-white p-1 text-center position-relative" style="border-color: #cbd5e1 !important;">
                                    <canvas id="signatureCanvas" width="400" height="110" style="touch-action: none; cursor: crosshair; background: #ffffff; width: 100%; height: 110px;"></canvas>
                                </div>
                                <small class="text-muted fs-11 mt-1 d-block"><i class="feather-info me-1"></i> Draw signature using mouse or touch.</small>
                            </div>

                            <!-- UPLOAD SIGNATURE IMAGE TAB (CUSTOM UI) -->
                            <div id="sig_upload_container" class="d-none">
                                <div class="position-relative w-100">
                                    <input type="file" id="sig_file_input" accept="image/png, image/jpeg, image/jpg, image/webp" class="position-absolute opacity-0 w-100 h-100" style="left:0; top:0; cursor:pointer; z-index:5;" onchange="handleSignatureFileUpload(this)">
                                    <div class="form-control d-flex flex-column align-items-center justify-content-center gap-1.5 p-3 text-center" style="border: 2px dashed rgba(var(--bs-primary-rgb, 59, 130, 246), 0.3); background-color: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.02); border-radius: 8px; min-height: 140px;">
                                        <div id="sig_upload_preview_box" class="d-flex flex-column align-items-center justify-content-center">
                                            <i class="feather-upload-cloud mb-1" style="font-size: 24px; color: var(--bs-primary);"></i>
                                            <span class="fw-bold text-dark fs-12 file-name-label">Click to browse or drop signature image here</span>
                                            <small class="text-muted fs-10 mt-0.5">Supports PNG, JPG, JPEG, WEBP image formats</small>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted fs-11 mt-1 d-block"><i class="feather-info me-1"></i> Upload pre-saved PNG/JPG image file of your signature.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                    <button type="button" class="btn btn-primary px-4 text-uppercase fw-bold" onclick="submitDigitalSignature()" style="font-size: 11px; background-color: var(--bs-primary) !important; border-color: var(--bs-primary) !important; border-radius: 6px;">Confirm & Sign Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var canvas = document.getElementById('signatureCanvas');
    var ctx = canvas ? canvas.getContext('2d') : null;
    var isDrawing = false;
    var hasSigned = false;
    var currentSigMode = 'draw';
    var uploadedSigDataUrl = null;

    if (canvas && ctx) {
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#0f172a';

        function getCanvasPos(e) {
            var rect = canvas.getBoundingClientRect();
            var clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
            var clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
            return {
                x: (clientX - rect.left) * (canvas.width / rect.width),
                y: (clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        canvas.addEventListener('mousedown', function(e) {
            isDrawing = true;
            hasSigned = true;
            var pos = getCanvasPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        });

        canvas.addEventListener('mousemove', function(e) {
            if (!isDrawing) return;
            var pos = getCanvasPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        });

        canvas.addEventListener('mouseup', function() { isDrawing = false; });
        canvas.addEventListener('mouseleave', function() { isDrawing = false; });

        canvas.addEventListener('touchstart', function(e) {
            isDrawing = true;
            hasSigned = true;
            var pos = getCanvasPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }, { passive: false });

        canvas.addEventListener('touchmove', function(e) {
            if (!isDrawing) return;
            var pos = getCanvasPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            e.preventDefault();
        }, { passive: false });

        canvas.addEventListener('touchend', function() { isDrawing = false; });
    }

    function clearSignatureCanvas() {
        if (ctx && canvas) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasSigned = false;
        }
    }

    function switchSigMode(mode) {
        currentSigMode = mode;
        if (mode === 'upload') {
            $('#btn_sig_type_upload').attr('style', 'background-color: var(--bs-primary) !important; color: #ffffff !important;').addClass('active btn-primary').removeClass('text-secondary');
            $('#btn_sig_type_draw').removeAttr('style').removeClass('active btn-primary').addClass('text-secondary');
            $('#sig_draw_container').addClass('d-none');
            $('#sig_upload_container').removeClass('d-none');
        } else {
            $('#btn_sig_type_draw').attr('style', 'background-color: var(--bs-primary) !important; color: #ffffff !important;').addClass('active btn-primary').removeClass('text-secondary');
            $('#btn_sig_type_upload').removeAttr('style').removeClass('active btn-primary').addClass('text-secondary');
            $('#sig_upload_container').addClass('d-none');
            $('#sig_draw_container').removeClass('d-none');
        }
    }

    function handleSignatureFileUpload(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                uploadedSigDataUrl = e.target.result;
                $('#sig_upload_preview_box').html(
                    '<div class="d-flex flex-column align-items-center gap-1">' +
                    '<img src="' + uploadedSigDataUrl + '" style="max-height: 65px; max-width: 100%; object-fit: contain;" class="rounded border p-1 bg-white" alt="Uploaded Signature Preview" />' +
                    '<span class="text-success fw-bold fs-11"><i class="feather-check-circle me-1"></i> ' + input.files[0].name + '</span>' +
                    '</div>'
                );
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function openSignModal(docId, actionUrl, docTitle, docFileUrl, docFileType) {
        $('#sign_modal_doc_title').text(docTitle);
        $('#signDocumentForm').attr('action', actionUrl);
        clearSignatureCanvas();
        uploadedSigDataUrl = null;
        $('#sig_file_input').val('');
        $('#sig_upload_preview_box').html(
            '<i class="feather-upload-cloud text-primary mb-1" style="font-size: 24px;"></i>' +
            '<span class="fw-bold text-dark fs-12 file-name-label">Click to browse or drop signature image here</span>' +
            '<small class="text-muted fs-10 mt-0.5">Supports PNG, JPG, JPEG, WEBP image formats</small>'
        );
        switchSigMode('draw');

        var previewContainer = $('#modal_doc_preview_container');
        previewContainer.empty();

        if (docFileUrl) {
            var ext = (docFileType || '').toLowerCase();
            if (['png', 'jpg', 'jpeg', 'webp', 'gif'].includes(ext)) {
                previewContainer.html(
                    '<div class="d-flex justify-content-center align-items-center h-100 p-2 bg-dark-subtle overflow-auto">' +
                    '<img src="' + docFileUrl + '" style="max-height: 370px; max-width: 100%; object-fit: contain;" class="rounded shadow-sm" />' +
                    '</div>'
                );
            } else {
                previewContainer.html(
                    '<iframe src="' + docFileUrl + '" style="width: 100%; height: 370px; border: none; pointer-events: auto; overflow: auto;"></iframe>'
                );
            }
        } else {
            previewContainer.html(
                '<div class="d-flex align-items-center justify-content-center h-100 text-muted fs-13"><i class="feather-file me-1"></i> Preview not available</div>'
            );
        }

        var modal = new bootstrap.Modal(document.getElementById('signDocumentCanvasModal'));
        modal.show();
    }

    function submitDigitalSignature() {
        var dataUrl = null;
        if (currentSigMode === 'upload') {
            if (!uploadedSigDataUrl) {
                alert('Please select a signature image file to upload.');
                return;
            }
            dataUrl = uploadedSigDataUrl;
        } else {
            if (!hasSigned) {
                alert('Please draw your signature inside the box before confirming.');
                return;
            }
            dataUrl = canvas.toDataURL('image/png');
        }

        $('#modal_signature_image_input').val(dataUrl);
        $('#signDocumentForm').submit();
    }
</script>

