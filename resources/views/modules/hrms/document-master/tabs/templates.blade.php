<!-- DOCUMENT TEMPLATES TAB -->
<div class="tab-pane fade {{ request()->query('active_tab') === 'templates' ? 'show active' : '' }}" id="templates-pane" role="tabpanel" aria-labelledby="templates-tab">
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">Document Templates</h5>
                <p class="fs-12 text-muted mb-0">Design standard rich-text templates with custom fonts, colors, and dynamic employee placeholders.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Search & Filters -->
                <form method="GET" action="{{ route('hrms.documents-master.index') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="active_tab" value="templates">
                    <input type="hidden" name="template_sort" id="template_sort" value="{{ request('template_sort', 'name_asc') }}">
                    
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="template_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search templates..." value="{{ request('template_search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <div class="d-flex gap-2">
                        <x-ui.sort-dropdown label="Sort">
                            <a class="dropdown-item py-2 {{ request('template_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('template', 'name_asc', this); event.preventDefault();">Name (A-Z)</a>
                            <a class="dropdown-item py-2 {{ request('template_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('template', 'name_desc', this); event.preventDefault();">Name (Z-A)</a>
                            <a class="dropdown-item py-2 {{ request('template_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('template', 'newest', this); event.preventDefault();">Newest</a>
                        </x-ui.sort-dropdown>

                        <x-ui.filter label="Filter" offset="0, 5" :reset-url="route('hrms.documents-master.index', ['active_tab' => 'templates'])">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            <div class="mb-3" style="min-width: 200px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                                <x-ui.odoo-form-ui type="select" name="template_category_id">
                                    <option value="">All Categories</option>
                                    @foreach($allCategories as $cat)
                                        <option value="{{ $cat->id }}" {{ request('template_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3" style="min-width: 200px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <x-ui.odoo-form-ui type="select" name="template_status">
                                    <option value="">All Statuses</option>
                                    <option value="active" {{ request('template_status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ request('template_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
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
                        <th class="text-start px-4" style="width: 30%;">Template Name</th>
                        <th style="width: 20%;">Template Code</th>
                        <th style="width: 20%;">Category</th>
                        <th style="width: 12%;">Status</th>
                        <th class="text-end px-4" style="width: 18%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="templatesTableBody">
                    @forelse($templates as $tmpl)
                        <tr>
                            <td class="text-start px-4" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                <div class="fw-bold text-dark fs-13">{{ $tmpl->name }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1 font-monospace fs-11">{{ $tmpl->code }}</span>
                            </td>
                            <td>
                                <span class="badge bg-soft-info text-info px-2.5 py-1.5 fs-11">{{ $tmpl->category->name ?? 'Uncategorized' }}</span>
                            </td>
                            <td>
                                @if($tmpl->status === 'active')
                                    <span class="badge bg-soft-success text-success px-2.5 py-1 fs-11 rounded-pill">Active</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary px-2.5 py-1 fs-11 rounded-pill">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end px-4">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <button type="button" class="btn btn-xs btn-primary px-2.5 py-1 btn-generate-document"
                                            title="Generate Document for Employee"
                                            data-id="{{ $tmpl->id }}"
                                            data-name="{{ $tmpl->name }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#generateFromTemplateModal">
                                        <i class="feather-file-text me-1"></i> Generate
                                    </button>

                                    <x-ui.action-dropdown id="tmplActions-{{ $tmpl->id }}">
                                        <li>
                                            <a class="dropdown-item py-2 btn-preview-template" 
                                               href="javascript:void(0)"
                                               data-id="{{ $tmpl->id }}"
                                               data-name="{{ $tmpl->name }}"
                                               data-bs-toggle="modal"
                                               data-bs-target="#previewTemplateModal">
                                                <i class="feather-eye me-2 text-info"></i> Live Preview
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2 btn-edit-template" 
                                               href="javascript:void(0)"
                                               data-bs-toggle="modal" 
                                               data-bs-target="#editTemplateModal"
                                               data-id="{{ $tmpl->id }}"
                                               data-name="{{ $tmpl->name }}"
                                               data-code="{{ $tmpl->code }}"
                                               data-category-id="{{ $tmpl->document_category_id }}"
                                               data-body="{{ $tmpl->body_content }}"
                                               data-status="{{ $tmpl->status }}">
                                                <i class="feather-edit-2 me-2 text-primary"></i> Edit Template
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('hrms.documents-master.templates.toggle-status', $tmpl->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2">
                                                    @if($tmpl->status === 'active')
                                                        <i class="feather-slash me-2 text-warning"></i> Set Inactive
                                                    @else
                                                        <i class="feather-check-circle me-2 text-success"></i> Set Active
                                                    @endif
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('hrms.documents-master.templates.destroy', $tmpl->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this template?', { title: 'Delete Template', variant: 'danger', confirmButtonText: 'Delete' });" class="m-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item py-2 text-danger">
                                                    <i class="feather-trash-2 me-2"></i> Delete
                                                </button>
                                            </form>
                                        </li>
                                    </x-ui.action-dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-5 text-center text-muted">
                                <i class="feather-layout fs-24 mb-2 d-block"></i>
                                No document templates found. Click <strong>+ Add Document Template</strong> to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3" id="templatesPaginationWrapper">
            <x-ui.pagination 
                :currentPage="$templates->currentPage()" 
                :totalPages="$templates->lastPage()" 
                :totalResults="$templates->total()" 
                :perPage="$templates->perPage()" 
                pageParam="template_page"
                tab="templates"
            />
        </div>
    </div>
</div>

<!-- Add Document Template Modal -->
<div class="modal fade text-dark" id="addTemplateModal" tabindex="-1" aria-labelledby="addTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addTemplateModalLabel"><i class="feather-plus-circle text-primary me-1"></i> Add Document Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.documents-master.templates.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Template Name" name="name" placeholder="e.g. Standard Offer Letter, Experience Certificate..." :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Template Code" name="code" placeholder="e.g. TMPL_OFFER_01" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Document Category" name="document_category_id">
                                <option value="">Select Category (Optional)...</option>
                                @foreach($allCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Status" name="status" :searchable="false" :required="true">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" label="Import File" name="template_file" placeholder="Upload (.html, .txt, .docx)..." helperText="Uploading a file will extract its content into the editor." />
                        </div>

                        <!-- Main Rich Editor & Placeholders Sidebar -->
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Template Content & Design <span class="text-danger">*</span></label>
                                <div id="add_tmpl_quill_editor" style="min-height: 280px;" class="bg-white rounded border"></div>
                                <input type="hidden" name="body_content" id="add_tmpl_body_input" required>
                                <small class="text-muted mt-1 d-block"><i class="feather-info me-1"></i> Type your letter naturally like in MS Word. Click tags on the right to insert dynamic employee data or use the image button above for logos.</small>
                            </div>
                        </div>

                        <!-- Interactive Sidebar for Placeholders -->
                        <div class="col-md-4">
                            <div class="p-3 bg-soft-primary rounded border border-primary-subtle h-100">
                                <h6 class="fw-bold text-primary fs-12 mb-2"><i class="feather-tag me-1"></i> Available Placeholders</h6>
                                <p class="fs-11 text-muted mb-2">Click any tag to insert it into your editor:</p>
                                
                                <div class="placeholder-tags-wrapper d-flex flex-wrap gap-1 fs-11 max-h-300 overflow-auto">
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{employee_name}}')">@{{employee_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{employee_id}}')">@{{employee_id}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{designation}}')">@{{designation}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{department}}')">@{{department}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{joining_date}}')">@{{joining_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{last_working_day}}')">@{{last_working_day}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{email}}')">@{{email}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{phone}}')">@{{phone}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{company_name}}')">@{{company_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{company_logo}}')">@{{company_logo}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{current_date}}')">@{{current_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{reference_number}}')">@{{reference_number}}</button>
                                    
                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100">Dynamic Tables & Lists:</span>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('add', '@{{education_table}}')">@{{education_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('add', '@{{experience_table}}')">@{{experience_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('add', '@{{skills_list}}')">@{{skills_list}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('add', '@{{certifications_list}}')">@{{certifications_list}}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Document Template Modal -->
<div class="modal fade text-dark" id="editTemplateModal" tabindex="-1" aria-labelledby="editTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editTemplateModalLabel"><i class="feather-edit text-primary me-1"></i> Edit Document Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editTemplateForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Template Name" name="name" id="edit_tmpl_name" placeholder="e.g. Standard Offer Letter..." :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Template Code" name="code" id="edit_tmpl_code" placeholder="e.g. TMPL_OFFER_01" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Document Category" name="document_category_id" id="edit_tmpl_category_id">
                                <option value="">Select Category (Optional)...</option>
                                @foreach($allCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Status" name="status" id="edit_tmpl_status" :searchable="false" :required="true">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" label="Replace File" name="template_file" placeholder="Upload replacement (.html, .txt, .docx)..." />
                        </div>

                        <!-- Main Rich Editor & Placeholders Sidebar -->
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Template Content & Design <span class="text-danger">*</span></label>
                                <div id="edit_tmpl_quill_editor" style="min-height: 280px;" class="bg-white rounded border"></div>
                                <input type="hidden" name="body_content" id="edit_tmpl_body_input" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 bg-soft-primary rounded border border-primary-subtle h-100">
                                <h6 class="fw-bold text-primary fs-12 mb-2"><i class="feather-tag me-1"></i> Available Placeholders</h6>
                                <p class="fs-11 text-muted mb-2">Click to insert tag:</p>
                                <div class="placeholder-tags-wrapper d-flex flex-wrap gap-1 fs-11">
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{employee_name}}')">@{{employee_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{employee_id}}')">@{{employee_id}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{designation}}')">@{{designation}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{department}}')">@{{department}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{joining_date}}')">@{{joining_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{education_table}}')">@{{education_table}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{skills_list}}')">@{{skills_list}}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Direct Generate Document Modal -->
<div class="modal fade text-dark" id="generateFromTemplateModal" tabindex="-1" aria-labelledby="generateFromTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="generateFromTemplateModalLabel"><i class="feather-file-text text-primary me-1"></i> Generate Document for Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.documents.bulk-upload') }}" method="POST">
                @csrf
                <input type="hidden" name="upload_mode" value="generate_template">
                <input type="hidden" name="document_template_id" id="gen_modal_template_id">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Target Employee" name="employee_id" id="gen_modal_employee_id" :required="true">
                                <option value="">Select Employee...</option>
                                @foreach($allEmployees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name ?? $emp->display_name }} ({{ $emp->employee_id }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Document Title / Name" name="document_title" id="gen_modal_title" placeholder="e.g. Experience Certificate - John Doe" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Reference Number (Optional)" name="reference_number" placeholder="e.g. REF-2026-001" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" type="date" label="Issue Date" name="issue_date" value="{{ date('Y-m-d') }}" />
                        </div>

                        <!-- Instant Live Preview Box -->
                        <div class="col-12 mt-3">
                            <label class="form-label fw-bold text-dark fs-12 mb-1"><i class="feather-eye me-1 text-primary"></i> Live Rendered Preview</label>
                            <div id="genModalPreviewBox" class="border rounded p-3 bg-white min-h-200" style="max-height: 350px; overflow-y: auto;">
                                <div class="text-center py-4 text-muted fs-12">
                                    Select an employee above to load the populated document preview.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="feather-check-circle me-1"></i> Generate & Save to HR Documents</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Live Preview Template Modal -->
<div class="modal fade text-dark" id="previewTemplateModal" tabindex="-1" aria-labelledby="previewTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="previewTemplateModalLabel"><i class="feather-eye text-primary me-1"></i> Live Document Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="previewTemplateContainer" class="border rounded p-3 bg-white min-h-300">
                    <div class="text-center py-5 text-muted">
                        <i class="feather-loader spinner-border spinner-border-sm me-2"></i> Generating live document preview...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    var addQuillInstance = null;
    var editQuillInstance = null;

    function initTemplateQuillEditors() {
        var toolbarOptions = [
            [{ 'font': [] }, { 'size': ['small', false, 'large', 'huge'] }],
            [{ 'header': [1, 2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'align': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['link', 'image', 'clean']
        ];

        if ($('#add_tmpl_quill_editor').length && typeof Quill !== 'undefined' && !addQuillInstance) {
            addQuillInstance = new Quill('#add_tmpl_quill_editor', {
                theme: 'snow',
                modules: { toolbar: toolbarOptions }
            });
            addQuillInstance.on('text-change', function() {
                var html = addQuillInstance.root.innerHTML;
                $('#add_tmpl_body_input').val(html === '<p><br></p>' ? '' : html);
            });
        }

        if ($('#edit_tmpl_quill_editor').length && typeof Quill !== 'undefined' && !editQuillInstance) {
            editQuillInstance = new Quill('#edit_tmpl_quill_editor', {
                theme: 'snow',
                modules: { toolbar: toolbarOptions }
            });
            editQuillInstance.on('text-change', function() {
                var html = editQuillInstance.root.innerHTML;
                $('#edit_tmpl_body_input').val(html === '<p><br></p>' ? '' : html);
            });
        }
    }

    function insertTag(mode, tag) {
        var quill = (mode === 'add') ? addQuillInstance : editQuillInstance;
        var inputId = (mode === 'add') ? '#add_tmpl_body_input' : '#edit_tmpl_body_input';
        
        if (quill) {
            quill.focus();
            var range = quill.getSelection(true);
            var index = (range && typeof range.index !== 'undefined') ? range.index : quill.getLength();
            quill.insertText(index, tag);
            $(inputId).val(quill.root.innerHTML);
        }
    }

    $(document).ready(function() {
        // Move modals to body immediately to prevent CSS transform / overflow tab-pane z-index stacking issues
        $('#generateFromTemplateModal, #addTemplateModal, #editTemplateModal, #previewTemplateModal').appendTo('body');

        $(document).on('show.bs.modal', '.modal', function() {
            $(this).appendTo('body');
            $('.dropdown-menu').removeClass('show');
            $('.dropdown-toggle').removeClass('show').attr('aria-expanded', 'false');
        });

        initTemplateQuillEditors();

        $('#addTemplateModal, #editTemplateModal').on('shown.bs.modal', function () {
            initTemplateQuillEditors();
        });

        // Edit Template Modal Populator
        $(document).on('click', '.btn-edit-template', function() {
            var id = $(this).data('id');
            var form = $('#editTemplateForm');
            form.attr('action', '/hrms/documents-master/templates/' + id);

            $('#edit_tmpl_name').val($(this).data('name'));
            $('#edit_tmpl_code').val($(this).data('code'));
            $('#edit_tmpl_category_id').val($(this).data('category-id'));
            $('#edit_tmpl_status').val($(this).data('status'));

            var bodyVal = $(this).data('body') || '';
            $('#edit_tmpl_body_input').val(bodyVal);

            if (editQuillInstance) {
                editQuillInstance.root.innerHTML = bodyVal;
            } else {
                initTemplateQuillEditors();
                if (editQuillInstance) {
                    editQuillInstance.root.innerHTML = bodyVal;
                }
            }
        });

        // Direct Generate Document Modal Handler
        $(document).on('click', '.btn-generate-document', function() {
            var tmplId = $(this).data('id');
            var tmplName = $(this).data('name');
            $('#gen_modal_template_id').val(tmplId);
            $('#gen_modal_title').val(tmplName);
            $('#generateFromTemplateModalLabel').html('<i class="feather-file-text text-primary me-1"></i> Generate: ' + tmplName);
            $('#genModalPreviewBox').html('<div class="text-center py-4 text-muted fs-12">Select an employee above to view live document preview.</div>');
            
            // Trigger preview if employee is already selected
            if ($('#gen_modal_employee_id').val()) {
                fetchGenPreview(tmplId, $('#gen_modal_employee_id').val());
            }
        });

        $('#gen_modal_employee_id').on('change', function() {
            var tmplId = $('#gen_modal_template_id').val();
            var empId = $(this).val();
            if (tmplId && empId) {
                fetchGenPreview(tmplId, empId);
            }
        });

        function fetchGenPreview(tmplId, empId) {
            $('#genModalPreviewBox').html('<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>Rendering document preview...</div>');
            $.ajax({
                url: '/hrms/documents-master/templates/' + tmplId + '/preview?employee_id=' + empId,
                type: 'GET',
                success: function(res) {
                    if (res && res.html) {
                        $('#genModalPreviewBox').html(res.html);
                    } else {
                        $('#genModalPreviewBox').html('<div class="alert alert-warning m-0">Unable to generate preview for selected employee.</div>');
                    }
                },
                error: function(xhr) {
                    var errMsg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error rendering document preview.';
                    $('#genModalPreviewBox').html('<div class="alert alert-danger m-0">' + errMsg + '</div>');
                }
            });
        }

        // Live Template Preview Handler
        $(document).on('click', '.btn-preview-template', function() {
            var tmplId = $(this).data('id');
            var tmplName = $(this).data('name');
            $('#previewTemplateModalLabel').html('<i class="feather-eye text-primary me-1"></i> Preview: ' + tmplName);
            $('#previewTemplateContainer').html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>Loading live template preview...</div>');

            $.ajax({
                url: '/hrms/documents-master/templates/' + tmplId + '/preview',
                type: 'GET',
                success: function(res) {
                    if (res && res.html) {
                        $('#previewTemplateContainer').html(res.html);
                    } else {
                        $('#previewTemplateContainer').html('<div class="alert alert-warning">Unable to render preview for this template.</div>');
                    }
                },
                error: function() {
                    $('#previewTemplateContainer').html('<div class="alert alert-danger">Error loading template preview.</div>');
                }
            });
        });
    });
</script>
@endpush
