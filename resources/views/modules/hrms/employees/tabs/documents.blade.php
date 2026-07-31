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
                                    <x-ui.button type="button" id="btnApplyDocumentFilter" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.common.apply') }}</x-ui.button>
                                    <x-ui.button type="button" id="btnResetDocumentFilter" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
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
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="documentsTable" style="table-layout: fixed; width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 25%;">{{ __('hrms.employees.tbl_doc_title') }}</th>
                                    <th style="width: 15%;">{{ __('hrms.employees.tbl_added_by') }}</th>
                                    <th style="width: 15%;">{{ __('hrms.employees.tbl_expiry_date') }}</th>
                                    <th style="width: 12%;">{{ __('hrms.employees.tbl_status') }}</th>
                                    <th style="width: 13%;">{{ __('hrms.employees.tbl_last_updated') }}</th>
                                    <th class="text-end pe-3" style="width: 20%;">{{ __('hrms.employees.tbl_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documents as $doc)
                                    @php
                                        $isExpired = $doc->expiry_date && $doc->expiry_date->isPast();
                                        $isExpiringSoon = $doc->expiry_date && !$isExpired && $doc->expiry_date->diffInDays(now()) <= 30;
                                        
                                        $rowStatus = $doc->status;
                                        $rowHasExpiry = $doc->expiry_date ? '1' : '0';
                                    @endphp
                                    <tr class="document-row" 
                                        data-title="{{ strtolower($doc->name) }}" 
                                        data-status="{{ $rowStatus }}" 
                                        data-has-expiry="{{ $rowHasExpiry }}"
                                        data-expiry-timestamp="{{ $doc->expiry_date ? $doc->expiry_date->timestamp : 9999999999 }}"
                                        data-title-raw="{{ $doc->name }}">
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px;">
                                                    <i class="feather-file fs-16"></i>
                                                </div>
                                                <div style="max-width: calc(100% - 50px);">
                                                    <div class="fw-bold text-dark text-truncate mb-0.5" title="{{ $doc->name }}">{{ $doc->name }}</div>
                                                    @if($doc->description)
                                                        <small class="text-muted text-truncate d-block" title="{{ $doc->description }}">{{ $doc->description }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark">{{ $doc->uploadedBy?->full_name ?? 'System' }}</div>
                                        </td>
                                        <td>
                                            @if($doc->expiry_date)
                                                <div class="fw-semibold {{ $isExpired ? 'text-danger' : ($isExpiringSoon ? 'text-warning' : 'text-dark') }}">
                                                    {{ $doc->expiry_date->format('d M, Y') }}
                                                </div>
                                                @if($isExpired)
                                                    <small class="badge bg-soft-danger text-danger fs-10 px-2 mt-0.5 rounded-pill">{{ __('hrms.employees.lbl_expired') }}</small>
                                                @elseif($isExpiringSoon)
                                                    <small class="badge bg-soft-warning text-dark fs-10 px-2 mt-0.5 rounded-pill">{{ __('hrms.employees.lbl_expiring_soon') }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted fs-13">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($doc->status === 'uploaded')
                                                <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-11">{{ __('hrms.employees.lbl_uploaded') }}</span>
                                            @else
                                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-11">{{ __('hrms.employees.lbl_pending_upload') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fs-12 text-dark">{{ $doc->updated_at->format('d M, Y') }}</div>
                                            <small class="text-muted fs-11">{{ $doc->updated_at->format('H:i') }}</small>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                @if($doc->status === 'uploaded' && $doc->file_path)
                                                    <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center p-0" style="border-radius: 8px; width: 32px; height: 32px;" title="{{ __('hrms.employees.lbl_download_doc') }}">
                                                        <i class="feather-download fs-13"></i>
                                                    </a>
                                                @endif
                                                
                                                <form action="{{ route('hrms.employees.documents.destroy', [$employee->id, $doc->id]) }}" method="POST" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this document record?', { title: 'Delete Document', variant: 'danger', confirmButtonText: 'Delete' });" class="m-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-soft-danger border d-flex align-items-center justify-content-center p-0" style="border-radius: 8px; width: 32px; height: 32px;" title="{{ __('hrms.common.delete') }}">
                                                        <i class="feather-trash-2 fs-13"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr id="noMatchingDocumentsRow" class="d-none">
                                    <td colspan="6" class="text-center py-5 text-muted fs-13">
                                        <i class="feather-folder-minus d-block fs-32 text-light-muted mb-2"></i>
                                        No matching documents found.
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
