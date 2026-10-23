<!-- DOCUMENT MASTERS TAB -->
<style>
    .modal-section-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        font-weight: 700;
        color: #475569;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 6px;
    }
    .modal-card-box {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        background-color: #ffffff;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    }
</style>
<div class="tab-pane fade {{ request()->query('active_tab', 'documents') === 'documents' ? 'show active' : '' }}" id="documents-pane" role="tabpanel" aria-labelledby="documents-tab">
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.document_master.documents_title') }}</h5>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Search & Filters -->
                <form method="GET" action="{{ route('hrms.documents-master.index') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="active_tab" value="documents">
                    
                    @if(request()->filled('category_search'))
                        <input type="hidden" name="category_search" value="{{ request('category_search') }}">
                    @endif
                    @if(request()->filled('category_company_id'))
                        <input type="hidden" name="category_company_id" value="{{ request('category_company_id') }}">
                    @endif
                    @if(request()->filled('category_sort'))
                        <input type="hidden" name="category_sort" value="{{ request('category_sort') }}">
                    @endif
                    
                    <input type="hidden" name="doc_sort" id="doc_sort" value="{{ request('doc_sort', 'name_asc') }}">
                    
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="doc_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.document_master.search_documents') }}" value="{{ request('doc_search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <div class="d-flex gap-2">
                        <x-ui.sort-dropdown :label="__('hrms.document_master.sort')">
                            <a class="dropdown-item py-2 {{ request('doc_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('doc', 'name_asc', this); event.preventDefault();">{{ __('hrms.document_master.sort_name_asc') }}</a>
                            <a class="dropdown-item py-2 {{ request('doc_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('doc', 'name_desc', this); event.preventDefault();">{{ __('hrms.document_master.sort_name_desc') }}</a>
                            <a class="dropdown-item py-2 {{ request('doc_sort') == 'code_asc' ? 'active' : '' }}" href="#" onclick="changeSort('doc', 'code_asc', this); event.preventDefault();">{{ __('hrms.document_master.sort_code_asc') }}</a>
                            <a class="dropdown-item py-2 {{ request('doc_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('doc', 'newest', this); event.preventDefault();">{{ __('hrms.document_master.sort_newest') }}</a>
                        </x-ui.sort-dropdown>

                        <x-ui.filter :label="__('hrms.document_master.filter')" offset="0, 5" :reset-url="route('hrms.documents-master.index', ['active_tab' => 'documents'])">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.document_master.filter_options') }}</h6>
                            
                            <div class="mb-3" style="min-width: 250px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.document_master.category') }}</label>
                                <x-ui.odoo-form-ui type="select" name="doc_category_id">
                                    <option value="">{{ __('hrms.document_master.all_categories') }}</option>
                                    @foreach($allCategories as $category)
                                        <option value="{{ $category->id }}" {{ request('doc_category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }} ({{ $category->company->company_name ?? __('hrms.document_master.all_companies') }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.document_master.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="doc_status">
                                    <option value="">{{ __('hrms.document_master.all_statuses') }}</option>
                                    <option value="active" {{ request('doc_status') == 'active' ? 'selected' : '' }}>{{ __('hrms.document_master.active') }}</option>
                                    <option value="inactive" {{ request('doc_status') == 'inactive' ? 'selected' : '' }}>{{ __('hrms.document_master.inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </x-ui.filter>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                    <tr>
                        <th class="text-start px-4" style="width: 25%;">{{ __('hrms.document_master.document_details') }}</th>
                        <th style="width: 15%;">{{ __('hrms.document_master.category') }}</th>
                        <th style="width: 25%;">{{ __('hrms.document_master.config_and_responsibility') }}</th>
                        <th style="width: 15%;">{{ __('hrms.document_master.portal_visibility') }}</th>
                        <th style="width: 10%;">{{ __('hrms.document_master.status') }}</th>
                        <th class="text-end px-4" style="width: 10%;">{{ __('hrms.document_master.actions') }}</th>
                    </tr>
                </thead>
                <tbody id="documentsTableBody">
                    @forelse($documents as $doc)
                        <tr>
                            <td class="text-start px-4" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                <div class="fw-bold text-dark fs-13">{{ $doc->name }}</div>
                                <div class="text-muted fs-11 font-monospace mt-0.5">{{ $doc->code }}</div>
                                @if($doc->description)
                                    <div class="text-muted fs-11 mt-1 small text-truncate" title="{{ $doc->description }}">{{ $doc->description }}</div>
                                @endif
                            </td>
                            <td>
                                <x-ui.badge variant="info" soft>{{ $doc->category->name ?? '-' }}</x-ui.badge>
                            </td>
                            <td>
                                <div class="d-flex flex-column align-items-center gap-1.5">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="fs-11 text-muted fw-semibold">{{ __('hrms.document_master.upload_colon') }}</span>
                                        @if($doc->upload_responsibility === 'employee')
                                            <x-ui.badge variant="primary" soft>{{ __('hrms.document_master.resp_employee') }}</x-ui.badge>
                                        @elseif($doc->upload_responsibility === 'hr')
                                            <x-ui.badge variant="warning" soft>{{ __('hrms.document_master.resp_hr') }}</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="info" soft>{{ __('hrms.document_master.resp_both') }}</x-ui.badge>
                                        @endif
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-center gap-1 mt-0.5">
                                        @if($doc->is_required)
                                            <x-ui.badge variant="danger" soft>{{ __('hrms.document_master.mandatory_document') }}</x-ui.badge>
                                        @endif
                                        @if($doc->approval_required)
                                            <x-ui.badge variant="success" soft>{{ __('hrms.document_master.requires_approval') }}</x-ui.badge>
                                        @endif
                                        @if($doc->requires_signature)
                                            <x-ui.badge variant="info" soft><i class="feather-edit-3 me-0.5"></i> {{ __('hrms.document_master.signature_required_badge') }}</x-ui.badge>
                                        @endif
                                        @if($doc->expiry_applicable)
                                            <x-ui.badge variant="warning" soft>{{ __('hrms.document_master.expiry_badge', ['days' => $doc->reminder_days_before]) }}</x-ui.badge>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($doc->upload_responsibility !== 'hr')
                                    <x-ui.badge variant="success" soft><i class="feather-eye me-1"></i> {{ __('hrms.document_master.visible_in_portal') }}</x-ui.badge>
                                @elseif($doc->employee_can_view)
                                    <x-ui.badge variant="success" soft><i class="feather-eye me-1"></i> {{ __('hrms.document_master.visible_in_portal') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="secondary" soft><i class="feather-lock me-1"></i> {{ __('hrms.document_master.internal_only') }}</x-ui.badge>
                                @endif
                            </td>
                            <td>
                                <div class="dropdown d-inline-block">
                                    <button class="btn p-0 border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="box-shadow: none;">
                                        @if($doc->status === 'active')
                                            <x-ui.badge variant="success" soft>{{ __('hrms.document_master.active') }}</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="danger" soft>{{ __('hrms.document_master.inactive') }}</x-ui.badge>
                                        @endif
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1" style="min-width: 120px; font-size: 12px;">
                                        <li>
                                            <form action="{{ route('hrms.documents-master.documents.toggle-status', $doc->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-1.5 fw-semibold text-success d-flex align-items-center justify-content-between" {{ $doc->status === 'active' ? 'disabled' : '' }}>
                                                    <span>{{ __('hrms.document_master.active') }}</span>
                                                    @if($doc->status === 'active')
                                                        <i class="feather-check text-success fs-13"></i>
                                                    @endif
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="{{ route('hrms.documents-master.documents.toggle-status', $doc->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-1.5 fw-semibold text-danger d-flex align-items-center justify-content-between" {{ $doc->status === 'inactive' ? 'disabled' : '' }}>
                                                    <span>{{ __('hrms.document_master.inactive') }}</span>
                                                    @if($doc->status === 'inactive')
                                                        <i class="feather-check text-danger fs-13"></i>
                                                    @endif
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                            <td class="text-end px-4">
                                <x-ui.action-dropdown id="docActions-{{ $doc->id }}">
                                    <li>
                                        <a class="dropdown-item py-2" 
                                           href="javascript:void(0)"
                                           data-bs-toggle="modal" 
                                           data-bs-target="#editDocumentModal"
                                           data-id="{{ $doc->id }}"
                                           data-category-id="{{ $doc->document_category_id }}"
                                           data-name="{{ $doc->name }}"
                                           data-code="{{ $doc->code }}"
                                           data-description="{{ $doc->description }}"
                                           data-is-required="{{ $doc->is_required ? 1 : 0 }}"
                                           data-upload-responsibility="{{ $doc->upload_responsibility }}"
                                           data-approval-required="{{ $doc->approval_required ? 1 : 0 }}"
                                           data-requires-signature="{{ $doc->requires_signature ? 1 : 0 }}"
                                           data-expiry-applicable="{{ $doc->expiry_applicable ? 1 : 0 }}"
                                           data-reminder-days="{{ $doc->reminder_days_before }}"
                                           data-employee-can-view="{{ $doc->employee_can_view ? 1 : 0 }}"
                                           data-status="{{ $doc->status }}">
                                            <i class="feather-edit me-1.5 text-muted"></i> {{ __('hrms.document_master.edit') }}
                                        </a>
                                    </li>
                                    <li>
                                        <form action="{{ route('hrms.documents-master.documents.destroy', $doc->id) }}" method="POST" id="deleteDocForm_{{ $doc->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item py-2 text-danger" onclick="return confirm('{{ __('hrms.document_master.confirm_delete_document') }}');">
                                                <i class="feather-trash-2 me-1.5"></i> {{ __('hrms.document_master.delete') }}
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted fs-14">
                                    <i class="feather-info me-1 fs-16 text-primary"></i> {{ __('hrms.document_master.empty_documents') }}
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3" id="documentsPaginationWrapper">
            <x-ui.pagination 
                :currentPage="$documents->currentPage()" 
                :totalPages="$documents->lastPage()" 
                :totalResults="$documents->total()" 
                :perPage="$documents->perPage()" 
                pageParam="doc_page"
                tab="documents"
            />
        </div>
    </div>
</div>

<!-- MODAL: ADD DOCUMENT MASTER -->
<div class="modal fade" id="addDocumentModal" aria-labelledby="addDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="addDocumentModalLabel">
                    <i class="feather-file-text me-2 text-primary"></i>{{ __('hrms.document_master.create_document_title') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.documents-master.documents.store') }}" method="POST">
                @csrf
                <div class="modal-body text-start">
                    <!-- Basic Information -->
                    <div class="modal-card-box mb-4">
                        <h6 class="modal-section-title mb-3"><i class="feather-info text-primary me-1"></i> {{ __('hrms.document_master.basic_information') }}</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.document_category')" name="document_category_id" :required="true" select2-selector="default">
                                    <option value="">{{ __('hrms.document_master.choose_category') }}</option>
                                    @foreach($allCategories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->company->company_name ?? __('hrms.document_master.all_companies') }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.document_name')" name="name" :placeholder="__('hrms.document_master.doc_name_placeholder')" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.document_code')" name="code" :placeholder="__('hrms.document_master.doc_code_placeholder')" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.status')" name="status" :required="true" select2-selector="default">
                                    <option value="active" selected>{{ __('hrms.document_master.active') }}</option>
                                    <option value="inactive">{{ __('hrms.document_master.inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.document_master.description')" name="description" :placeholder="__('hrms.document_master.doc_desc_placeholder')" />
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Grid -->
                    <div class="row g-4 mb-4">
                        <!-- Configuration -->
                        <div class="col-md-6">
                            <div class="modal-card-box h-100">
                                <h6 class="modal-section-title mb-3"><i class="feather-settings text-primary me-1"></i> {{ __('hrms.document_master.document_configuration') }}</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.upload_responsibility')" name="upload_responsibility" :required="true" select2-selector="default">
                                            <option value="employee" selected>{{ __('hrms.document_master.resp_employee') }}</option>
                                            <option value="hr">{{ __('hrms.document_master.resp_hr_short') }}</option>
                                            <option value="both">{{ __('hrms.document_master.resp_both_short') }}</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.required')" name="is_required">
                                            {{ __('hrms.document_master.is_mandatory_doc') }}
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.approval_required')" name="approval_required">
                                            {{ __('hrms.document_master.requires_approval_desc') }}
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.signature_required')" name="requires_signature">
                                            {{ __('hrms.document_master.requires_signature_desc') }}
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expiry Configuration -->
                        <div class="col-md-6">
                            <div class="modal-card-box h-100">
                                <h6 class="modal-section-title mb-3"><i class="feather-calendar text-primary me-1"></i> {{ __('hrms.document_master.expiry_configuration') }}</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" class="expiry-applicable-toggle" :label="__('hrms.document_master.expiry_applicable')" name="expiry_applicable" id="doc_expiry_applicable">
                                            {{ __('hrms.document_master.doc_subject_to_expiration') }}
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12 reminder-days-group" style="display: none;">
                                        <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.reminder_days')" name="reminder_days_before" id="doc_reminder_days_before" inputType="number" :placeholder="__('hrms.document_master.reminder_days_placeholder')" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Access -->
                    <div class="modal-card-box">
                        <h6 class="modal-section-title mb-3"><i class="feather-eye text-primary me-1"></i> {{ __('hrms.document_master.portal_access_title') }}</h6>
                        <div class="row g-3">
                            <div class="col-12 portal-access-editable" style="display: none;">
                                <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.visible_to_employee')" name="employee_can_view" class="employee-can-view-checkbox" checked>
                                    {{ __('hrms.document_master.allow_emp_portal_access') }}
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12 portal-access-auto">
                                <div class="p-2.5 rounded-3 border bg-light d-flex align-items-center gap-2">
                                    <span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-bold"><i class="feather-check-circle me-1"></i> {{ __('hrms.document_master.always_visible') }}</span>
                                    <span class="text-muted fs-12">{{ __('hrms.document_master.auto_visible_desc') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.create_document_btn') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDIT DOCUMENT MASTER -->
<div class="modal fade" id="editDocumentModal" aria-labelledby="editDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="editDocumentModalLabel">
                    <i class="feather-file-text me-2 text-primary"></i>{{ __('hrms.document_master.edit_document_title') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body text-start">
                    <!-- Basic Information -->
                    <div class="modal-card-box mb-4">
                        <h6 class="modal-section-title mb-3"><i class="feather-info text-primary me-1"></i> {{ __('hrms.document_master.basic_information') }}</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.document_category')" name="document_category_id" id="edit_doc_category_id" :required="true" select2-selector="default">
                                    <option value="">{{ __('hrms.document_master.choose_category') }}</option>
                                    @foreach($allCategories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->company->company_name ?? __('hrms.document_master.all_companies') }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.document_name')" name="name" id="edit_doc_name" :placeholder="__('hrms.document_master.doc_name_placeholder')" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.document_code')" name="code" id="edit_doc_code" :placeholder="__('hrms.document_master.doc_code_placeholder')" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.status')" name="status" id="edit_doc_status" :required="true" select2-selector="default">
                                    <option value="active">{{ __('hrms.document_master.active') }}</option>
                                    <option value="inactive">{{ __('hrms.document_master.inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.document_master.description')" name="description" id="edit_doc_description" :placeholder="__('hrms.document_master.doc_desc_placeholder')" />
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Grid -->
                    <div class="row g-4 mb-4">
                        <!-- Configuration -->
                        <div class="col-md-6">
                            <div class="modal-card-box h-100">
                                <h6 class="modal-section-title mb-3"><i class="feather-settings text-primary me-1"></i> {{ __('hrms.document_master.document_configuration') }}</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.upload_responsibility')" name="upload_responsibility" id="edit_doc_upload_responsibility" :required="true" select2-selector="default">
                                            <option value="employee">{{ __('hrms.document_master.resp_employee') }}</option>
                                            <option value="hr">{{ __('hrms.document_master.resp_hr_short') }}</option>
                                            <option value="both">{{ __('hrms.document_master.resp_both_short') }}</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.required')" name="is_required" id="edit_doc_is_required">
                                            {{ __('hrms.document_master.is_mandatory_doc') }}
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.approval_required')" name="approval_required" id="edit_doc_approval_required">
                                            {{ __('hrms.document_master.requires_approval_desc') }}
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.signature_required')" name="requires_signature" id="edit_doc_requires_signature">
                                            {{ __('hrms.document_master.requires_signature_desc') }}
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expiry Configuration -->
                        <div class="col-md-6">
                            <div class="modal-card-box h-100">
                                <h6 class="modal-section-title mb-3"><i class="feather-calendar text-primary me-1"></i> {{ __('hrms.document_master.expiry_configuration') }}</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" class="expiry-applicable-toggle" :label="__('hrms.document_master.expiry_applicable')" name="expiry_applicable" id="edit_doc_expiry_applicable">
                                            {{ __('hrms.document_master.doc_subject_to_expiration') }}
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12 reminder-days-group" style="display: none;">
                                        <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.reminder_days')" name="reminder_days_before" id="edit_doc_reminder_days_before" inputType="number" :placeholder="__('hrms.document_master.reminder_days_placeholder')" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Access -->
                    <div class="modal-card-box">
                        <h6 class="modal-section-title mb-3"><i class="feather-eye text-primary me-1"></i> {{ __('hrms.document_master.portal_access_title') }}</h6>
                        <div class="row g-3">
                            <div class="col-12 portal-access-editable" style="display: none;">
                                <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.visible_to_employee')" name="employee_can_view" id="edit_doc_employee_can_view" class="employee-can-view-checkbox">
                                    {{ __('hrms.document_master.allow_emp_portal_access') }}
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12 portal-access-auto">
                                <div class="p-2.5 rounded-3 border bg-light d-flex align-items-center gap-2">
                                    <span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-bold"><i class="feather-check-circle me-1"></i> {{ __('hrms.document_master.always_visible') }}</span>
                                    <span class="text-muted fs-12">{{ __('hrms.document_master.auto_visible_desc') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.save_changes') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
