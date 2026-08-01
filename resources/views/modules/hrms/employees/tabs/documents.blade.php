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
                        <x-ui.button type="button" variant="soft-primary" class="fw-bold text-uppercase d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#requestDocumentModal">
                            <i class="feather-git-pull-request"></i> {{ __('hrms.employees.btn_request') }}
                        </x-ui.button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="overflow: visible;">
                        <table class="table table-hover align-middle mb-0 documents-table" id="documentsTable" style="table-layout: fixed; width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 30%;">{{ __('hrms.employees.tbl_doc_title') }}</th>
                                    <th style="width: 18%;">{{ __('hrms.employees.tbl_source_expiry') }}</th>
                                    <th style="width: 26%;">{{ __('hrms.employees.tbl_file') }}</th>
                                    <th style="width: 12%;">{{ __('hrms.employees.tbl_status') }}</th>
                                    <th class="text-end pe-3" style="width: 14%;">{{ __('hrms.employees.tbl_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documents as $doc)
                                    @php
                                        $isExpired = $doc->expiry_date && $doc->expiry_date->isPast();
                                        $isExpiringSoon = $doc->expiry_date && !$isExpired && now()->greaterThanOrEqualTo($doc->expiry_date->copy()->subDays(30));
                                        
                                        $rowStatus = ($isExpired || $isExpiringSoon) ? 'requested' : $doc->status;
                                        $rowHasExpiry = $doc->expiry_date ? '1' : '0';
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
                                                    {{ $doc->uploadedBy?->full_name ?? 'System' }}
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
                                        <td>
                                            @if($doc->file_path)
                                                <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-white" style="width: 280px; max-width: 100%; height: 50px; border-color: #e2e8f0 !important; background-color: #f8fafc !important;">
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
                                                        <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="btn btn-xs btn-white border rounded-circle p-0 d-inline-flex align-items-center justify-content-center text-muted hover-primary" style="width: 24px; height: 24px; background: #ffffff;" title="{{ __('hrms.common.view') }}">
                                                            <i class="feather-eye fs-11"></i>
                                                        </a>
                                                        @if($isExpired || $isExpiringSoon)
                                                            <a href="#" class="btn btn-xs btn-white border rounded-circle p-0 d-inline-flex align-items-center justify-content-center text-muted hover-primary" style="width: 24px; height: 24px; background: #ffffff;" title="{{ __('hrms.employees.lbl_reupload') ?? 'Reupload' }}" onclick="toggleInlineUploadForm('{{ $doc->id }}'); return false;">
                                                                <i class="feather-refresh-cw fs-10"></i>
                                                            </a>
                                                        @endif
                                                        <a href="{{ asset('storage/' . $doc->file_path) }}" download class="btn btn-xs btn-white border rounded-circle p-0 d-inline-flex align-items-center justify-content-center text-muted hover-primary" style="width: 24px; height: 24px; background: #ffffff;" title="{{ __('hrms.employees.lbl_download_doc') ?? 'Download' }}">
                                                            <i class="feather-download fs-11"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                                @if($isExpired)
                                                    <div class="text-danger fw-bold fs-11 mt-1.5"><i class="feather-alert-triangle me-1"></i>{{ __('hrms.employees.lbl_document_expired') }}</div>
                                                @endif
                                                @if($isExpired || $isExpiringSoon)
                                                    <div id="inline-upload-container-{{ $doc->id }}" class="d-none mt-2">
                                                        <form action="{{ route('hrms.employees.documents.upload', $employee->id) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-1.5" style="width: 280px; max-width: 100%;">
                                                            @csrf
                                                            <input type="hidden" name="document_id" value="{{ $doc->id }}">
                                                            <input type="hidden" name="name" value="{{ $doc->name }}">
                                                            
                                                            <div class="d-flex align-items-center gap-1.5">
                                                                <div class="position-relative flex-grow-1" style="min-width: 0;">
                                                                    <input type="file" name="file" class="position-absolute opacity-0" style="left: 0; top: 0; width: 100%; height: 100%; cursor: pointer; z-index: 2;" onchange="updateInlineFileName(this)" required>
                                                                    <div class="form-control d-flex align-items-center gap-1.5 fs-11 text-muted text-truncate px-2" style="border: 1px dashed rgba(var(--bs-primary-rgb), 0.25); background-color: rgba(var(--bs-primary-rgb), 0.02); height: 32px; border-radius: 8px;">
                                                                        <i class="feather-upload-cloud fs-13 flex-shrink-0"></i>
                                                                        <span class="file-name-label text-truncate">{{ __('hrms.employees.lbl_choose_file') ?? 'Choose File' }}</span>
                                                                    </div>
                                                                </div>
                                                                
                                                                <button type="submit" class="btn btn-sm text-white fw-bold d-inline-flex align-items-center gap-1 px-2.5 flex-shrink-0" style="background-color: var(--bs-primary) !important; font-size: 10.5px; height: 32px; border-radius: 8px; border: none; white-space: nowrap;">
                                                                    <i class="feather-upload-cloud fs-11"></i> {{ __('hrms.employees.btn_upload') }}
                                                                </button>
                                                            </div>

                                                            @if($doc->has_expiry)
                                                                <div class="d-flex align-items-center gap-1.5 mt-1">
                                                                    <span class="text-muted fw-bold fs-9 text-uppercase" style="white-space: nowrap;">{{ __('hrms.employees.lbl_expiry') ?? 'EXPIRY' }}:</span>
                                                                    <input type="date" name="expiry_date" class="form-control py-0 px-2 fs-11 text-muted border" style="height: 26px; border-radius: 6px; width: 120px;" required>
                                                                </div>
                                                            @endif
                                                        </form>
                                                    </div>
                                                @endif
                                            @else
                                                <form action="{{ route('hrms.employees.documents.upload', $employee->id) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-1.5" style="width: 280px; max-width: 100%;">
                                                    @csrf
                                                    <input type="hidden" name="document_id" value="{{ $doc->id }}">
                                                    <input type="hidden" name="name" value="{{ $doc->name }}">
                                                    
                                                    <div class="d-flex align-items-center gap-1.5">
                                                        <div class="position-relative flex-grow-1" style="min-width: 0;">
                                                            <input type="file" name="file" class="position-absolute opacity-0" style="left: 0; top: 0; width: 100%; height: 100%; cursor: pointer; z-index: 2;" onchange="updateInlineFileName(this)" required>
                                                            <div class="form-control d-flex align-items-center gap-1.5 fs-11 text-muted text-truncate px-2" style="border: 1px dashed rgba(var(--bs-primary-rgb), 0.25); background-color: rgba(var(--bs-primary-rgb), 0.02); height: 32px; border-radius: 8px;">
                                                                <i class="feather-upload-cloud fs-13 flex-shrink-0"></i>
                                                                <span class="file-name-label text-truncate">{{ __('hrms.employees.lbl_choose_file') ?? 'Choose File' }}</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <button type="submit" class="btn btn-sm text-white fw-bold d-inline-flex align-items-center gap-1 px-2.5 flex-shrink-0" style="background-color: var(--bs-primary) !important; font-size: 10.5px; height: 32px; border-radius: 8px; border: none; white-space: nowrap;">
                                                            <i class="feather-upload-cloud fs-11"></i> {{ __('hrms.employees.btn_upload') }}
                                                        </button>
                                                    </div>

                                                    @if($doc->has_expiry)
                                                        <div class="d-flex align-items-center gap-1.5 mt-1">
                                                            <span class="text-muted fw-bold fs-9 text-uppercase" style="white-space: nowrap;">{{ __('hrms.employees.lbl_expiry') ?? 'EXPIRY' }}:</span>
                                                            <input type="date" name="expiry_date" class="form-control py-0 px-2 fs-11 text-muted border" style="height: 26px; border-radius: 6px; width: 120px;" required>
                                                        </div>
                                                    @endif
                                                </form>
                                            @endif
                                        </td>
                                        <td>
                                            @if($isExpired)
                                                <span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(220, 53, 69, 0.08) !important; color: #dc3545 !important; border: 1px solid rgba(220, 53, 69, 0.15); font-weight: 500;">
                                                    <i class="feather-alert-triangle fs-11"></i>
                                                    {{ __('hrms.employees.lbl_expired') }}
                                                </span>
                                            @elseif($isExpiringSoon)
                                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 193, 7, 0.08) !important; color: #ff9800 !important; border: 1px solid rgba(255, 193, 7, 0.15); font-weight: 500;">
                                                    <i class="feather-clock fs-11"></i>
                                                    {{ __('hrms.employees.lbl_pending_upload') }}
                                                </span>
                                            @elseif($doc->status === 'approved')
                                                <span class="badge bg-soft-success text-success px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(40, 167, 69, 0.08) !important; color: #28a745 !important; border: 1px solid rgba(40, 167, 69, 0.15); font-weight: 500;">
                                                    <i class="feather-check-circle fs-11"></i>
                                                    {{ __('hrms.employees.status_approved') }}
                                                </span>
                                            @elseif($doc->status === 'rejected')
                                                <span class="badge bg-soft-danger text-danger px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(220, 53, 69, 0.08) !important; color: #dc3545 !important; border: 1px solid rgba(220, 53, 69, 0.15); font-weight: 500;">
                                                    <i class="feather-x-circle fs-11"></i>
                                                    {{ __('hrms.employees.status_rejected') }}
                                                </span>
                                            @elseif($doc->status === 'uploaded')
                                                <span class="badge px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(13, 202, 240, 0.08) !important; color: #00bcd4 !important; border: 1px solid rgba(13, 202, 240, 0.15); font-weight: 500;">
                                                    <i class="feather-clock fs-11"></i>
                                                    {{ __('hrms.employees.lbl_pending_verification') }}
                                                </span>
                                            @else
                                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded fs-11 d-inline-flex align-items-center gap-1" style="background-color: rgba(255, 193, 7, 0.08) !important; color: #ff9800 !important; border: 1px solid rgba(255, 193, 7, 0.15); font-weight: 500;">
                                                    <i class="feather-clock fs-11"></i>
                                                    {{ __('hrms.employees.lbl_pending_upload') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                @if($doc->status !== 'requested' && !$isExpired && !$isExpiringSoon)
                                                    <div class="dropdown {{ $loop->last ? 'dropup' : '' }} d-inline-block position-relative" onclick="event.stopPropagation();">
                                                        <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm" 
                                                                type="button" 
                                                                data-bs-toggle="dropdown" 
                                                                data-bs-boundary="viewport"
                                                                aria-expanded="false"
                                                                style="background-color: var(--bs-primary) !important; color: #ffffff !important; font-size: 11.5px; height: 32px; border-radius: 8px; min-width: 120px; border: none;"
                                                                title="{{ __('hrms.employees.lbl_update_status') ?? 'Change Status' }}">
                                                            <span>
                                                                @if($doc->status === 'approved')
                                                                    {{ __('hrms.employees.status_approved') }}
                                                                @elseif($doc->status === 'rejected')
                                                                    {{ __('hrms.employees.status_rejected') }}
                                                                @else
                                                                    {{ __('hrms.employees.lbl_select_status') }}
                                                                @endif
                                                            </span>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-1.5 mt-1 fs-12" style="border-radius: 8px; min-width: 120px; z-index: 1050; background: #ffffff;">
                                                            <li>
                                                                <a class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium {{ $doc->status === 'approved' ? 'bg-light text-primary fw-bold' : '' }}"
                                                                   href="#"
                                                                   onclick="submitDocumentStatusDirect('{{ route('hrms.employees.documents.status', $doc->id) }}', 'approved'); return false;"
                                                                   style="{{ $doc->status === 'approved' ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                    {{ __('hrms.employees.status_approved') }}
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium {{ $doc->status === 'rejected' ? 'bg-light text-primary fw-bold' : '' }}"
                                                                   href="#"
                                                                   onclick="submitDocumentStatusDirect('{{ route('hrms.employees.documents.status', $doc->id) }}', 'rejected'); return false;"
                                                                   style="{{ $doc->status === 'rejected' ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                    {{ __('hrms.employees.status_rejected') }}
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                @else
                                                    <button class="btn btn-sm btn-light border py-1 px-3 d-inline-flex align-items-center justify-content-center fw-semibold text-muted disabled" style="font-size: 11.5px; height: 32px; border-radius: 8px; min-width: 120px;" disabled>
                                                        {{ __('hrms.employees.lbl_pending_upload') }}
                                                    </button>
                                                @endif
                                                
                                                <form action="{{ route('hrms.employees.documents.destroy', [$employee->id, $doc->id]) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.employees.confirm_delete_document') }}', { title: '{{ __('hrms.employees.lbl_delete_document') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });" class="m-0 d-inline-flex" onclick="event.stopPropagation();">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-soft-danger border d-flex align-items-center justify-content-center p-0" style="border-radius: 8px; width: 32px; height: 32px; background: rgba(220, 53, 69, 0.05);" title="{{ __('hrms.common.delete') }}">
                                                        <i class="feather-trash-2 fs-13"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
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

    <div class="modal fade" id="requestDocumentModal" tabindex="-1" aria-labelledby="requestDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="requestDocumentModalLabel">
                        <i class="feather-git-pull-request me-2 text-primary" style="font-size: 16px;"></i>{{ __('hrms.employees.mdl_req_doc_title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.documents.request', $employee->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_doc_name') }}" name="name" :required="true" placeholder="{{ __('hrms.employees.mdl_doc_name_placeholder') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_instructions') }}" name="description" placeholder="{{ __('hrms.employees.mdl_instructions_placeholder') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="radio" label="{{ __('hrms.employees.mdl_requires_expiry') }}" name="has_expiry" :required="true">
                                    <div class="form-check">
                                        <input type="radio" id="has_expiry_yes" name="has_expiry" value="1" class="form-check-input">
                                        <label class="form-check-label fs-13" for="has_expiry_yes">{{ __('hrms.employees.mdl_yes') }}</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" id="has_expiry_no" name="has_expiry" value="0" class="form-check-input" checked>
                                        <label class="form-check-label fs-13" for="has_expiry_no">{{ __('hrms.employees.mdl_no') }}</label>
                                    </div>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_send_request') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_discard') }}</button>
                    </div>
                </form>
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
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_doc_title') }}" name="name" :required="true" placeholder="{{ __('hrms.employees.mdl_doc_title_placeholder') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="file" label="{{ __('hrms.employees.mdl_select_file') }}" name="file" :required="true" placeholder="{{ __('hrms.employees.mdl_select_file_placeholder') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_expiry_date') }}" name="expiry_date" inputType="date" />
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
</div>
