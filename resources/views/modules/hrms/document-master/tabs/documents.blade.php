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
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">Document Registry</h5>
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
                        <input type="text" name="doc_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search code/name..." value="{{ request('doc_search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <div class="d-flex gap-2">
                        <x-ui.sort-dropdown label="Sort">
                            <a class="dropdown-item py-2 {{ request('doc_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('doc', 'name_asc', this); event.preventDefault();">Name (A-Z)</a>
                            <a class="dropdown-item py-2 {{ request('doc_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('doc', 'name_desc', this); event.preventDefault();">Name (Z-A)</a>
                            <a class="dropdown-item py-2 {{ request('doc_sort') == 'code_asc' ? 'active' : '' }}" href="#" onclick="changeSort('doc', 'code_asc', this); event.preventDefault();">Code (A-Z)</a>
                            <a class="dropdown-item py-2 {{ request('doc_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('doc', 'newest', this); event.preventDefault();">Newest</a>
                        </x-ui.sort-dropdown>

                        <x-ui.filter label="Filter" offset="0, 5" :reset-url="route('hrms.documents-master.index', ['active_tab' => 'documents'])">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            
                            <div class="mb-3" style="min-width: 250px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                                <x-ui.odoo-form-ui type="select" name="doc_category_id">
                                    <option value="">All Categories</option>
                                    @foreach($allCategories as $category)
                                        <option value="{{ $category->id }}" {{ request('doc_category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }} ({{ $category->company->company_name ?? 'All Companies' }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <x-ui.odoo-form-ui type="select" name="doc_status">
                                    <option value="">All Statuses</option>
                                    <option value="active" {{ request('doc_status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ request('doc_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
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
                        <th class="text-start px-4" style="width: 25%;">Document Details</th>
                        <th style="width: 15%;">Category</th>
                        <th style="width: 25%;">Configuration & Responsibility</th>
                        <th style="width: 15%;">Access Permissions</th>
                        <th style="width: 10%;">Status</th>
                        <th class="text-end px-4" style="width: 10%;">Actions</th>
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
                                <span class="fs-12 fw-bold text-secondary">{{ $doc->category->name ?? '-' }}</span>
                            </td>
                            <td>
                                <div class="d-flex flex-column align-items-center gap-1">
                                    <div class="fs-11 fw-bold text-dark">
                                        Upload Responsibility: 
                                        @if($doc->upload_responsibility === 'employee')
                                            <span class="text-primary text-uppercase">Employee</span>
                                        @elseif($doc->upload_responsibility === 'hr')
                                            <span class="text-warning text-uppercase">HR</span>
                                        @else
                                            <span class="text-violet text-uppercase" style="color: #8b5cf6;">HR & Employee</span>
                                        @endif
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-center gap-1 mt-0.5">
                                        @if($doc->is_required)
                                            <span class="badge bg-soft-danger text-danger fs-9 px-1.5 py-0.5">Mandatory Document</span>
                                        @endif
                                        @if($doc->approval_required)
                                            <span class="badge bg-soft-success text-success fs-9 px-1.5 py-0.5">Requires Approval</span>
                                        @endif
                                        @if($doc->expiry_applicable)
                                            <span class="badge bg-soft-warning text-warning fs-9 px-1.5 py-0.5" title="Reminder: {{ $doc->reminder_days_before }} days before expiry">Expiry ({{ $doc->reminder_days_before }}d)</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1 justify-content-center">
                                    @if($doc->employee_can_view)
                                        <span class="badge-access-yes fs-9" title="Employee Can View"><i class="feather-eye"></i> View</span>
                                    @else
                                        <span class="badge-access-no fs-9" title="Employee Cannot View"><i class="feather-eye-off"></i> View</span>
                                    @endif

                                    @if($doc->employee_can_download)
                                        <span class="badge-access-yes fs-9" title="Employee Can Download"><i class="feather-download"></i> Download</span>
                                    @else
                                        <span class="badge-access-no fs-9" title="Employee Cannot Download"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="vertical-align: middle; display: inline-block;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line><line x1="1" y1="1" x2="23" y2="23"></line></svg>Download</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="dropdown d-inline-block">
                                    <a href="javascript:void(0)" class="dropdown-toggle fw-bold text-uppercase fs-11 {{ $doc->status === 'active' ? 'text-success border-bottom border-success' : 'text-danger border-bottom border-danger' }} pb-0.5" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none; border-bottom-width: 1.5px !important; letter-spacing: 0.5px;">
                                        {{ $doc->status }}
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: auto !important; width: 110px !important; font-size: 12px; margin: 0; padding: 4px 0; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
                                        <li>
                                            <form action="{{ route('hrms.documents-master.documents.toggle-status', $doc->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2 fw-bold text-success d-flex align-items-center justify-content-between gap-2" {{ $doc->status === 'active' ? 'disabled' : '' }} style="background: transparent !important; color: var(--bs-success) !important; border: 0; width: 100%; box-shadow: none !important;">
                                                    <span>Active</span>
                                                    @if($doc->status === 'active')
                                                        <i class="feather-check text-success" style="font-size: 14px; font-weight: bold;"></i>
                                                    @endif
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="{{ route('hrms.documents-master.documents.toggle-status', $doc->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2 fw-bold text-danger d-flex align-items-center justify-content-between gap-2" {{ $doc->status === 'inactive' ? 'disabled' : '' }} style="background: transparent !important; color: var(--bs-danger) !important; border: 0; width: 100%; box-shadow: none !important;">
                                                    <span>Inactive</span>
                                                    @if($doc->status === 'inactive')
                                                        <i class="feather-check text-danger" style="font-size: 14px; font-weight: bold;"></i>
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
                                           data-expiry-applicable="{{ $doc->expiry_applicable ? 1 : 0 }}"
                                           data-reminder-days="{{ $doc->reminder_days_before }}"
                                           data-employee-can-view="{{ $doc->employee_can_view ? 1 : 0 }}"
                                           data-employee-can-download="{{ $doc->employee_can_download ? 1 : 0 }}"
                                           data-status="{{ $doc->status }}">
                                            <i class="feather-edit me-1.5 text-muted"></i> Edit
                                        </a>
                                    </li>
                                    <li>
                                        <form action="{{ route('hrms.documents-master.documents.destroy', $doc->id) }}" method="POST" id="deleteDocForm_{{ $doc->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item py-2 text-danger" onclick="return confirm('Are you sure you want to delete this document master?');">
                                                <i class="feather-trash-2 me-1.5"></i> Delete
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
                                    <i class="feather-info me-1 fs-16 text-primary"></i> No document masters found.
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
                    <i class="feather-file-text me-2 text-primary"></i>Create Document Master
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.documents-master.documents.store') }}" method="POST">
                @csrf
                <div class="modal-body text-start">
                    <!-- Basic Information -->
                    <div class="modal-card-box mb-4">
                        <h6 class="modal-section-title mb-3"><i class="feather-info text-primary me-1"></i> Basic Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Document Category" name="document_category_id" :required="true" select2-selector="default">
                                    <option value="">Choose category...</option>
                                    @foreach($allCategories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->company->company_name ?? 'All Companies' }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Document Name" name="name" placeholder="e.g. Aadhaar Card" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Document Code" name="code" placeholder="e.g. AADHAAR_CARD" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true" select2-selector="default">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Specify optional instructions or guidelines about this document..." />
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Grid -->
                    <div class="row g-4 mb-4">
                        <!-- Configuration -->
                        <div class="col-md-6">
                            <div class="modal-card-box h-100">
                                <h6 class="modal-section-title mb-3"><i class="feather-settings text-primary me-1"></i> Document Configuration</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="select" label="Upload Responsibility" name="upload_responsibility" :required="true" select2-selector="default">
                                            <option value="employee" selected>Employee</option>
                                            <option value="hr">HR</option>
                                            <option value="both">Both</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" label="Required" name="is_required">
                                            Is mandatory document
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" label="Approval Required" name="approval_required">
                                            Requires approval
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expiry Configuration -->
                        <div class="col-md-6">
                            <div class="modal-card-box h-100">
                                <h6 class="modal-section-title mb-3"><i class="feather-calendar text-primary me-1"></i> Expiry Configuration</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" class="expiry-applicable-toggle" label="Expiry Applicable" name="expiry_applicable" id="doc_expiry_applicable">
                                            Document is subject to expiration
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12 reminder-days-group" style="display: none;">
                                        <x-ui.odoo-form-ui type="input" label="Reminder Days" name="reminder_days_before" id="doc_reminder_days_before" inputType="number" placeholder="e.g. 30" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Access -->
                    <div class="modal-card-box">
                        <h6 class="modal-section-title mb-3"><i class="feather-eye text-primary me-1"></i> Employee Access Settings</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="checkbox" label="Employee Can View" name="employee_can_view">
                                    Allow employees to view uploaded copy
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="checkbox" label="Employee Can Download" name="employee_can_download">
                                    Allow employees to download document
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Create Document</button>
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
                    <i class="feather-file-text me-2 text-primary"></i>Edit Document Master
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body text-start">
                    <!-- Basic Information -->
                    <div class="modal-card-box mb-4">
                        <h6 class="modal-section-title mb-3"><i class="feather-info text-primary me-1"></i> Basic Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Document Category" name="document_category_id" id="edit_doc_category_id" :required="true" select2-selector="default">
                                    <option value="">Choose category...</option>
                                    @foreach($allCategories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->company->company_name ?? 'All Companies' }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Document Name" name="name" id="edit_doc_name" placeholder="e.g. Aadhaar Card" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Document Code" name="code" id="edit_doc_code" placeholder="e.g. AADHAAR_CARD" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Status" name="status" id="edit_doc_status" :required="true" select2-selector="default">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_doc_description" placeholder="Specify optional instructions or guidelines about this document..." />
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Grid -->
                    <div class="row g-4 mb-4">
                        <!-- Configuration -->
                        <div class="col-md-6">
                            <div class="modal-card-box h-100">
                                <h6 class="modal-section-title mb-3"><i class="feather-settings text-primary me-1"></i> Document Configuration</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="select" label="Upload Responsibility" name="upload_responsibility" id="edit_doc_upload_responsibility" :required="true" select2-selector="default">
                                            <option value="employee">Employee</option>
                                            <option value="hr">HR</option>
                                            <option value="both">Both</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" label="Required" name="is_required" id="edit_doc_is_required">
                                            Is mandatory document
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" label="Approval Required" name="approval_required" id="edit_doc_approval_required">
                                            Requires approval
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expiry Configuration -->
                        <div class="col-md-6">
                            <div class="modal-card-box h-100">
                                <h6 class="modal-section-title mb-3"><i class="feather-calendar text-primary me-1"></i> Expiry Configuration</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <x-ui.odoo-form-ui type="checkbox" class="expiry-applicable-toggle" label="Expiry Applicable" name="expiry_applicable" id="edit_doc_expiry_applicable">
                                            Document is subject to expiration
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-12 reminder-days-group" style="display: none;">
                                        <x-ui.odoo-form-ui type="input" label="Reminder Days" name="reminder_days_before" id="edit_doc_reminder_days_before" inputType="number" placeholder="e.g. 30" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Access -->
                    <div class="modal-card-box">
                        <h6 class="modal-section-title mb-3"><i class="feather-eye text-primary me-1"></i> Employee Access Settings</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="checkbox" label="Employee Can View" name="employee_can_view" id="edit_doc_employee_can_view">
                                    Allow employees to view uploaded copy
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="checkbox" label="Employee Can Download" name="employee_can_download" id="edit_doc_employee_can_download">
                                    Allow employees to download document
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
