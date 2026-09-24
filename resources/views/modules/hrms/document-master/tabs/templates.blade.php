<style>
    /* Fixed text editor height with scrollbar inside editor for long text */
    #add_tmpl_quill_editor, #edit_tmpl_quill_editor {
        height: 380px !important;
        display: flex !important;
        flex-direction: column !important;
    }
    #add_tmpl_quill_editor .ql-container, #edit_tmpl_quill_editor .ql-container {
        flex: 1 1 auto !important;
        height: calc(380px - 42px) !important;
        overflow-y: auto !important;
    }
    #add_tmpl_quill_editor .ql-editor, #edit_tmpl_quill_editor .ql-editor {
        font-family: Arial, Helvetica, sans-serif !important;
        font-size: 14px !important;
        line-height: 1.5 !important;
        color: #1e293b !important;
        min-height: 100% !important;
        padding: 12px 15px !important;
    }
    /* Tight line spacing: exact match between text editor and generated document */
    #add_tmpl_quill_editor .ql-editor p,
    #edit_tmpl_quill_editor .ql-editor p,
    .generated-doc-container p,
    #previewTemplateContainer p,
    #genModalPreviewBox p,
    .doc-body p {
        margin-top: 0 !important;
        margin-bottom: 0.35em !important;
        line-height: 1.5 !important;
    }
    /* Available Placeholders sidebar - clean view without scrollbar */
    .placeholder-tags-wrapper {
        overflow: visible !important;
        max-height: none !important;
    }
    .placeholder-tags-wrapper .tag-btn {
        font-size: 11px !important;
        padding: 3px 7px !important;
        font-weight: 500 !important;
        border-radius: 4px !important;
        transition: all 0.15s ease-in-out;
    }
    .placeholder-tags-wrapper .tag-btn:hover {
        background-color: #e2e8f0 !important;
        transform: translateY(-1px);
    }
</style>

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
                                @php
                                    $codeUpper = strtoupper($tmpl->code);
                                    $catName = strtolower($tmpl->category->name ?? '');
                                    $tmplNameLower = strtolower($tmpl->name);
                                @endphp
                                @if(in_array($codeUpper, ['PAYSLIP', 'SALARY_SLIP', 'SALARYSLIP']) || str_contains($catName, 'payroll') || str_contains($tmplNameLower, 'payslip') || str_contains($tmplNameLower, 'salary slip'))
                                    <span class="badge bg-soft-success text-success border border-success-subtle fs-10 px-2 py-0.5 mt-1 d-inline-block">
                                        <i class="feather-dollar-sign me-1"></i> Auto-linked: Monthly Payslip
                                    </span>
                                @elseif(in_array($codeUpper, ['RELIEVING_LT', 'RELIEVING_LETTER', 'RELIEVING']) || str_contains($tmplNameLower, 'relieving'))
                                    <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-10 px-2 py-0.5 mt-1 d-inline-block">
                                        <i class="feather-log-out me-1"></i> Auto-linked: Relieving Letter
                                    </span>
                                @elseif(in_array($codeUpper, ['EXPERIENCE_LT', 'EXPERIENCE_CERTIFICATE', 'EXPERIENCE']) || str_contains($tmplNameLower, 'experience'))
                                    <span class="badge bg-soft-info text-info border border-info-subtle fs-10 px-2 py-0.5 mt-1 d-inline-block">
                                        <i class="feather-award me-1"></i> Auto-linked: Experience Certificate
                                    </span>
                                @elseif(in_array($codeUpper, ['NOC_LT', 'NOC_CERTIFICATE', 'NOC', 'NO_DUES']) || str_contains($tmplNameLower, 'no objection') || str_contains($tmplNameLower, 'noc'))
                                    <span class="badge bg-soft-secondary text-secondary border border-secondary-subtle fs-10 px-2 py-0.5 mt-1 d-inline-block">
                                        <i class="feather-check-circle me-1"></i> Auto-linked: NOC Certificate
                                    </span>
                                @elseif(in_array($codeUpper, ['FNF_STMT', 'FNF_STATEMENT', 'FNF_SETTLEMENT']) || str_contains($tmplNameLower, 'settlement') || str_contains($tmplNameLower, 'fnf'))
                                    <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 px-2 py-0.5 mt-1 d-inline-block">
                                        <i class="feather-file-text me-1"></i> Auto-linked: F&F Settlement
                                    </span>
                                @elseif(in_array($codeUpper, ['OFFERLT', 'OFFER_LETTER']) || str_contains($tmplNameLower, 'offer letter'))
                                    <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-10 px-2 py-0.5 mt-1 d-inline-block">
                                        <i class="feather-user-check me-1"></i> Auto-linked: Offer Letter
                                    </span>
                                @elseif(in_array($codeUpper, ['SELECTLT', 'SELECTION_LETTER']) || str_contains($tmplNameLower, 'selection letter'))
                                    <span class="badge bg-soft-info text-info border border-info-subtle fs-10 px-2 py-0.5 mt-1 d-inline-block">
                                        <i class="feather-mail me-1"></i> Auto-linked: Selection Letter
                                    </span>
                                @endif
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
                                               data-requires-signature="{{ $tmpl->requires_signature ? '1' : '0' }}"
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
                        <!-- Quick Preset Selector Banner -->
                        <div class="col-12">
                            <div class="p-3 bg-soft-primary rounded border border-primary-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold text-dark fs-12 mb-0"><i class="feather-zap text-primary me-1"></i> Template System Purpose / Quick Preset</div>
                                    <p class="fs-11 text-muted mb-0">Select a purpose to auto-link to system modules (Payroll, Exits, Recruitment) and load starter layouts.</p>
                                </div>
                                <div style="min-width: 280px;">
                                    <select class="form-select form-select-sm fs-12" id="add_template_preset_select" onchange="applyTemplatePreset('add', this.value)">
                                        <option value="">-- Choose System Purpose / Preset --</option>
                                        <option value="payslip">💰 Monthly Payslip / Salary Slip (Auto-links to Payroll)</option>
                                        <option value="relieving">🚪 Relieving Letter (Auto-links to Exits)</option>
                                        <option value="experience">📜 Experience Certificate (Auto-links to Exits)</option>
                                        <option value="noc">🛡️ No Objection / No Dues Certificate (NOC)</option>
                                        <option value="fnf">📊 Full & Final (F&F) Settlement Statement</option>
                                        <option value="offer">💼 Job Offer Letter (Recruitment)</option>
                                        <option value="selection">✉️ Selection Letter (Recruitment)</option>
                                        <option value="custom">✏️ Custom / General Employee Letter</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Template Name" name="name" id="add_tmpl_name" placeholder="e.g. Standard Offer Letter, Experience Certificate..." :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Template Code" name="code" id="add_tmpl_code" placeholder="e.g. TMPL_OFFER_01" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Document Category" name="document_category_id" id="add_tmpl_category_id">
                                <option value="">Select Category (Optional)...</option>
                                @foreach($allCategories as $cat)
                                    <option value="{{ $cat->id }}" data-name="{{ strtolower($cat->name) }}">{{ $cat->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Status" name="status" id="add_tmpl_status" :searchable="false" :required="true">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="checkbox" label="Requires Employee Signature" name="requires_signature">
                                Generated document requires Employee Signature
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" label="Import File" name="template_file" placeholder="Upload (.html, .txt, .docx)..." helperText="Uploading a file will extract its content into the editor." />
                        </div>

                        <!-- Main Rich Editor & Placeholders Sidebar -->
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Template Content & Design <span class="text-danger">*</span></label>
                                <div id="add_tmpl_quill_editor" style="height: 380px;" class="bg-white rounded border"></div>
                                <input type="hidden" name="body_content" id="add_tmpl_body_input" required>
                                <small class="text-muted mt-1 d-block"><i class="feather-info me-1"></i> Type your letter naturally like in MS Word. Click tags on the right to insert dynamic employee data or use the image button above for logos.</small>
                            </div>
                        </div>

                        <!-- Interactive Sidebar for Placeholders -->
                        <div class="col-md-4">
                            <div class="p-3 bg-soft-primary rounded border border-primary-subtle">
                                <h6 class="fw-bold text-primary fs-12 mb-2"><i class="feather-tag me-1"></i> Available Placeholders</h6>
                                <p class="fs-11 text-muted mb-2">Click any tag to insert it into your editor:</p>
                                
                                <div class="placeholder-tags-wrapper d-flex flex-wrap gap-1 fs-11">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-user me-1 text-primary"></i> Employee Data:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{employee_name}}')">@{{employee_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{employee_id}}')">@{{employee_id}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{designation}}')">@{{designation}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{department}}')">@{{department}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{branch}}')">@{{branch}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{reporting_manager}}')">@{{reporting_manager}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{joining_date}}')">@{{joining_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{last_working_day}}')">@{{last_working_day}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{employment_status}}')">@{{employment_status}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{email}}')">@{{email}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{phone}}')">@{{phone}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{dob}}')">@{{dob}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{gender}}')">@{{gender}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{marital_status}}')">@{{marital_status}}</button>

                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-briefcase me-1 text-primary"></i> Company Details:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{company_name}}')">@{{company_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{company_logo}}')">@{{company_logo}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{company_address}}')">@{{company_address}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{company_email}}')">@{{company_email}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{company_phone}}')">@{{company_phone}}</button>

                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-shield me-1 text-warning"></i> Document & Signatures:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{current_date}}')">@{{current_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{issue_date}}')">@{{issue_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{reference_number}}')">@{{reference_number}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-warning border text-warning tag-btn" onclick="insertTag('add', '@{{hr_signature}}')">@{{hr_signature}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{hr_name}}')">@{{hr_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{hr_designation}}')">@{{hr_designation}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{signature_date}}')">@{{signature_date}}</button>
                                    
                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-grid me-1 text-info"></i> Dynamic Tables & Lists:</span>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('add', '@{{education_table}}')">@{{education_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('add', '@{{experience_table}}')">@{{experience_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('add', '@{{skills_list}}')">@{{skills_list}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('add', '@{{certifications_list}}')">@{{certifications_list}}</button>

                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-dollar-sign me-1 text-success"></i> Payroll & Salary Data:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{payslip_month}}')">@{{payslip_month}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{working_days}}')">@{{working_days}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{paid_days}}')">@{{paid_days}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{lop_days}}')">@{{lop_days}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{gross_earnings}}')">@{{gross_earnings}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{total_deductions}}')">@{{total_deductions}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" onclick="insertTag('add', '@{{net_pay}}')">@{{net_pay}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{net_pay_in_words}}')">@{{net_pay_in_words}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{bank_name}}')">@{{bank_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{bank_account_number}}')">@{{bank_account_number}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{ifsc_code}}')">@{{ifsc_code}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{pan_number}}')">@{{pan_number}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{uan_number}}')">@{{uan_number}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" onclick="insertTag('add', '@{{salary_breakdown_table}}')">@{{salary_breakdown_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" onclick="insertTag('add', '@{{earnings_table}}')">@{{earnings_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" onclick="insertTag('add', '@{{deductions_table}}')">@{{deductions_table}}</button>

                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-log-out me-1 text-danger"></i> Exit & Separation:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{separation_type}}')">@{{separation_type}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{resignation_date}}')">@{{resignation_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{tenure_string}}')">@{{tenure_string}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{conduct_statement}}')">@{{conduct_statement}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{clearance_status}}')">@{{clearance_status}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-danger border text-danger tag-btn" onclick="insertTag('add', '@{{fnf_settlement_table}}')">@{{fnf_settlement_table}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{fnf_net_payable}}')">@{{fnf_net_payable}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('add', '@{{fnf_net_payable_words}}')">@{{fnf_net_payable_words}}</button>
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
                            <x-ui.odoo-form-ui type="checkbox" label="Requires Employee Signature" name="requires_signature" id="edit_tmpl_requires_signature" value="1">
                                Generated document requires Employee Signature
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" label="Replace File" name="template_file" placeholder="Upload replacement (.html, .txt, .docx)..." />
                        </div>

                        <!-- Main Rich Editor & Placeholders Sidebar -->
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Template Content & Design <span class="text-danger">*</span></label>
                                <div id="edit_tmpl_quill_editor" style="height: 380px;" class="bg-white rounded border"></div>
                                <input type="hidden" name="body_content" id="edit_tmpl_body_input" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 bg-soft-primary rounded border border-primary-subtle">
                                <h6 class="fw-bold text-primary fs-12 mb-2"><i class="feather-tag me-1"></i> Available Placeholders</h6>
                                <p class="fs-11 text-muted mb-2">Click to insert tag:</p>
                                <div class="placeholder-tags-wrapper d-flex flex-wrap gap-1 fs-11">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-user me-1 text-primary"></i> Employee Data:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{employee_name}}')">@{{employee_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{employee_id}}')">@{{employee_id}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{designation}}')">@{{designation}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{department}}')">@{{department}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{branch}}')">@{{branch}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{reporting_manager}}')">@{{reporting_manager}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{joining_date}}')">@{{joining_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{last_working_day}}')">@{{last_working_day}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{employment_status}}')">@{{employment_status}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{email}}')">@{{email}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{phone}}')">@{{phone}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{dob}}')">@{{dob}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{gender}}')">@{{gender}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{marital_status}}')">@{{marital_status}}</button>

                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-briefcase me-1 text-primary"></i> Company Details:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{company_name}}')">@{{company_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{company_logo}}')">@{{company_logo}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{company_address}}')">@{{company_address}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{company_email}}')">@{{company_email}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{company_phone}}')">@{{company_phone}}</button>

                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-shield me-1 text-warning"></i> Document & Signatures:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{current_date}}')">@{{current_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{issue_date}}')">@{{issue_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{reference_number}}')">@{{reference_number}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-warning border text-warning tag-btn" onclick="insertTag('edit', '@{{hr_signature}}')">@{{hr_signature}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{hr_name}}')">@{{hr_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{hr_designation}}')">@{{hr_designation}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{signature_date}}')">@{{signature_date}}</button>
                                    
                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-grid me-1 text-info"></i> Dynamic Tables & Lists:</span>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('edit', '@{{education_table}}')">@{{education_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('edit', '@{{experience_table}}')">@{{experience_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('edit', '@{{skills_list}}')">@{{skills_list}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" onclick="insertTag('edit', '@{{certifications_list}}')">@{{certifications_list}}</button>

                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-dollar-sign me-1 text-success"></i> Payroll & Salary Data:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{payslip_month}}')">@{{payslip_month}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{working_days}}')">@{{working_days}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{paid_days}}')">@{{paid_days}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{lop_days}}')">@{{lop_days}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{gross_earnings}}')">@{{gross_earnings}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{total_deductions}}')">@{{total_deductions}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" onclick="insertTag('edit', '@{{net_pay}}')">@{{net_pay}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{net_pay_in_words}}')">@{{net_pay_in_words}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{bank_name}}')">@{{bank_name}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{bank_account_number}}')">@{{bank_account_number}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{ifsc_code}}')">@{{ifsc_code}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{pan_number}}')">@{{pan_number}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{uan_number}}')">@{{uan_number}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" onclick="insertTag('edit', '@{{salary_breakdown_table}}')">@{{salary_breakdown_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" onclick="insertTag('edit', '@{{earnings_table}}')">@{{earnings_table}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" onclick="insertTag('edit', '@{{deductions_table}}')">@{{deductions_table}}</button>

                                    <hr class="w-100 my-1">
                                    <span class="fw-bold text-dark fs-10 w-100 mb-1"><i class="feather-log-out me-1 text-danger"></i> Exit & Separation:</span>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{separation_type}}')">@{{separation_type}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{resignation_date}}')">@{{resignation_date}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{tenure_string}}')">@{{tenure_string}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{conduct_statement}}')">@{{conduct_statement}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{clearance_status}}')">@{{clearance_status}}</button>
                                    <button type="button" class="btn btn-xs btn-soft-danger border text-danger tag-btn" onclick="insertTag('edit', '@{{fnf_settlement_table}}')">@{{fnf_settlement_table}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{fnf_net_payable}}')">@{{fnf_net_payable}}</button>
                                    <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" onclick="insertTag('edit', '@{{fnf_net_payable_words}}')">@{{fnf_net_payable_words}}</button>
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

                        <!-- HR DIGITAL SIGNATURE SECTION (WHEN GENERATING / ASSIGNING DOCUMENT) -->
                        <div class="col-12 mt-3 pt-3 border-top">
                            <h6 class="fw-bold text-dark fs-13 mb-2"><i class="feather-edit-3 text-warning me-1"></i> HR / Authoriser Signature Stamp</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="HR Signer Name" name="hr_name" value="{{ auth()->user()->name }}" placeholder="e.g. {{ auth()->user()->name }}" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="HR Designation / Title" name="hr_designation" value="HR Manager" placeholder="e.g. HR Manager / Director" />
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-bold fs-12 text-dark mb-0">HR Signature Image (Draw or Upload)</label>
                                        <ul class="nav nav-pills bg-light p-1 rounded-pill border gap-1" id="tmplHrSigInputTabs" role="tablist">
                                            <li class="nav-item">
                                                <button type="button" id="btn_tmpl_hr_sig_draw" class="nav-link btn-xs py-1 px-3 fs-11 fw-bold rounded-pill active border-0 btn-primary" style="background-color: var(--bs-primary) !important; color: #ffffff !important;" onclick="switchTmplHrSigMode('draw')">
                                                    <i class="feather-edit-2 me-1"></i> Draw Signature
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button type="button" id="btn_tmpl_hr_sig_upload" class="nav-link btn-xs py-1 px-3 fs-11 fw-bold text-secondary rounded-pill border-0" onclick="switchTmplHrSigMode('upload')">
                                                    <i class="feather-upload-cloud me-1"></i> Upload Image
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    <input type="hidden" name="hr_signature_data" id="tmpl_hr_sig_input_data">

                                    <!-- DRAW HR SIGNATURE TAB -->
                                    <div id="tmpl_hr_sig_draw_container">
                                        <div class="d-flex justify-content-end mb-1">
                                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="clearTmplHrSigCanvas()">
                                                <i class="feather-rotate-ccw me-1"></i>Clear Canvas
                                            </button>
                                        </div>
                                        <div class="p-1 text-center position-relative shadow-sm" style="border: 2px dashed #94a3b8 !important; background-color: #f8fafc; border-radius: 8px;">
                                            <canvas id="tmplHrSignatureCanvas" width="400" height="100" style="touch-action: none; cursor: crosshair; background: #ffffff; width: 100%; height: 100px; border-radius: 6px;"></canvas>
                                        </div>
                                        <small class="text-muted fs-11 mt-1 d-block"><i class="feather-info me-1"></i> Draw HR signature using mouse or touch.</small>
                                    </div>

                                    <!-- UPLOAD HR SIGNATURE IMAGE TAB -->
                                    <div id="tmpl_hr_sig_upload_container" class="d-none">
                                        <div class="position-relative w-100">
                                            <input type="file" name="hr_signature_file" id="tmpl_hr_sig_file_input" accept="image/png, image/jpeg, image/jpg, image/webp" class="position-absolute opacity-0 w-100 h-100" style="left:0; top:0; cursor:pointer; z-index:5;" onchange="handleTmplHrSigUpload(this)">
                                            <div class="form-control d-flex flex-column align-items-center justify-content-center gap-1.5 p-3 text-center" style="border: 2px dashed rgba(var(--bs-primary-rgb, 59, 130, 246), 0.3); background-color: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.02); border-radius: 8px; min-height: 110px;">
                                                <div id="tmpl_hr_sig_upload_preview_box" class="d-flex flex-column align-items-center justify-content-center">
                                                    <i class="feather-upload-cloud mb-1" style="font-size: 24px; color: var(--bs-primary);"></i>
                                                    <span class="fw-bold text-dark fs-12 file-name-label">Click to browse or drop HR signature image</span>
                                                    <small class="text-muted fs-10 mt-0.5">PNG or JPG transparent signature images recommended</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

        // Auto-extract content from uploaded template file into Quill editor
        $(document).on('change', 'input[name="template_file"]', function() {
            var fileInput = this;
            if (!fileInput.files || !fileInput.files[0]) return;

            var file = fileInput.files[0];
            var isEdit = $(fileInput).closest('.modal').attr('id') === 'editTemplateModal';
            var quill = isEdit ? editQuillInstance : addQuillInstance;
            var hiddenInputId = isEdit ? '#edit_tmpl_body_input' : '#add_tmpl_body_input';
            var ext = file.name.split('.').pop().toLowerCase();

            if (ext === 'html' || ext === 'htm' || ext === 'txt') {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var content = e.target.result;
                    if (quill) {
                        if (ext === 'txt') {
                            quill.setText(content);
                        } else {
                            quill.root.innerHTML = content;
                        }
                        $(hiddenInputId).val(quill.root.innerHTML);
                    }
                };
                reader.readAsText(file);
            } else if (ext === 'docx') {
                var formData = new FormData();
                formData.append('template_file', file);
                formData.append('_token', '{{ csrf_token() }}');

                $.ajax({
                    url: '{{ route("hrms.documents-master.templates.parse-file") }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res && res.success && res.content) {
                            if (quill) {
                                quill.root.innerHTML = res.content;
                                $(hiddenInputId).val(quill.root.innerHTML);
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error('File parse error:', xhr);
                    }
                });
            }
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

            var reqSig = $(this).data('requires-signature');
            $('#edit_tmpl_requires_signature').prop('checked', reqSig == 1 || reqSig === '1' || reqSig === true);

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

    // HR Signature Canvas & Upload Handlers for Template Modal
    var tmplHrCanvas = document.getElementById('tmplHrSignatureCanvas');
    var tmplHrCtx = tmplHrCanvas ? tmplHrCanvas.getContext('2d') : null;
    var tmplHrIsDrawing = false;

    if (tmplHrCanvas && tmplHrCtx) {
        tmplHrCtx.lineWidth = 2.5;
        tmplHrCtx.lineCap = 'round';
        tmplHrCtx.strokeStyle = '#0f172a';

        function getTmplHrCanvasPos(e) {
            var rect = tmplHrCanvas.getBoundingClientRect();
            var clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
            var clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
            return {
                x: (clientX - rect.left) * (tmplHrCanvas.width / rect.width),
                y: (clientY - rect.top) * (tmplHrCanvas.height / rect.height)
            };
        }

        tmplHrCanvas.addEventListener('mousedown', function(e) {
            tmplHrIsDrawing = true;
            var pos = getTmplHrCanvasPos(e);
            tmplHrCtx.beginPath();
            tmplHrCtx.moveTo(pos.x, pos.y);
        });

        tmplHrCanvas.addEventListener('mousemove', function(e) {
            if (!tmplHrIsDrawing) return;
            var pos = getTmplHrCanvasPos(e);
            tmplHrCtx.lineTo(pos.x, pos.y);
            tmplHrCtx.stroke();
            $('#tmpl_hr_sig_input_data').val(tmplHrCanvas.toDataURL('image/png'));
        });

        tmplHrCanvas.addEventListener('mouseup', function() { tmplHrIsDrawing = false; });
        tmplHrCanvas.addEventListener('mouseleave', function() { tmplHrIsDrawing = false; });

        tmplHrCanvas.addEventListener('touchstart', function(e) {
            tmplHrIsDrawing = true;
            var pos = getTmplHrCanvasPos(e);
            tmplHrCtx.beginPath();
            tmplHrCtx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }, { passive: false });

        tmplHrCanvas.addEventListener('touchmove', function(e) {
            if (!tmplHrIsDrawing) return;
            var pos = getTmplHrCanvasPos(e);
            tmplHrCtx.lineTo(pos.x, pos.y);
            tmplHrCtx.stroke();
            $('#tmpl_hr_sig_input_data').val(tmplHrCanvas.toDataURL('image/png'));
            e.preventDefault();
        }, { passive: false });

        tmplHrCanvas.addEventListener('touchend', function() { tmplHrIsDrawing = false; });
    }

    function clearTmplHrSigCanvas() {
        if (tmplHrCtx && tmplHrCanvas) {
            tmplHrCtx.clearRect(0, 0, tmplHrCanvas.width, tmplHrCanvas.height);
            $('#tmpl_hr_sig_input_data').val('');
        }
    }

    function switchTmplHrSigMode(mode) {
        if (mode === 'upload') {
            $('#btn_tmpl_hr_sig_upload').attr('style', 'background-color: var(--bs-primary) !important; color: #ffffff !important;').addClass('active btn-primary').removeClass('text-secondary');
            $('#btn_tmpl_hr_sig_draw').removeAttr('style').removeClass('active btn-primary').addClass('text-secondary');
            $('#tmpl_hr_sig_draw_container').addClass('d-none');
            $('#tmpl_hr_sig_upload_container').removeClass('d-none');
            $('#tmpl_hr_sig_input_data').val('');
        } else {
            $('#btn_tmpl_hr_sig_draw').attr('style', 'background-color: var(--bs-primary) !important; color: #ffffff !important;').addClass('active btn-primary').removeClass('text-secondary');
            $('#btn_tmpl_hr_sig_upload').removeAttr('style').removeClass('active btn-primary').addClass('text-secondary');
            $('#tmpl_hr_sig_upload_container').addClass('d-none');
            $('#tmpl_hr_sig_draw_container').removeClass('d-none');
        }
    }

    function handleTmplHrSigUpload(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var dataUrl = e.target.result;
                $('#tmpl_hr_sig_input_data').val(dataUrl);
                $('#tmpl_hr_sig_upload_preview_box').html(
                    '<div class="d-flex flex-column align-items-center gap-1">' +
                    '<img src="' + dataUrl + '" style="max-height: 55px; max-width: 100%; object-fit: contain;" class="rounded border p-1 bg-white" alt="HR Signature Preview" />' +
                    '<span class="text-success fw-bold fs-11"><i class="feather-check-circle me-1"></i> ' + input.files[0].name + '</span>' +
                    '</div>'
                );
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // System Preset Templates Dictionary
    const TEMPLATE_PRESETS = {
        payslip: {
            name: 'Standard Salary Slip',
            code: 'PAYSLIP',
            categoryKeywords: ['payroll', 'salary'],
            content: '<p><strong>@{{employee_name}}</strong> (@{{employee_id}}) - @{{designation}} | @{{department}}</p><p>Month: <strong>@{{payslip_month}}</strong> | Paid Days: @{{paid_days}} / @{{working_days}}</p><p>@{{salary_breakdown_table}}</p><p><strong>Net Salary:</strong> @{{net_pay}} (@{{net_pay_in_words}})</p>'
        },
        relieving: {
            name: 'Relieving Letter',
            code: 'RELIEVING_LT',
            categoryKeywords: ['exit', 'separation', 'offboarding'],
            content: '<p>To,<br><strong>@{{employee_name}}</strong> (@{{employee_id}})<br>@{{designation}} - @{{department}}</p><p><strong>Subject: Formal Relieving Letter & Acceptance of Resignation</strong></p><p>Dear @{{employee_name}},</p><p>With reference to your resignation, we hereby confirm that your resignation from <strong>@{{company_name}}</strong> has been accepted. You are officially relieved from your duties with effect from the close of business hours on <strong>@{{last_working_day}}</strong>.</p><p>We confirm that you served the organization from <strong>@{{joining_date}}</strong> to <strong>@{{last_working_day}}</strong> and completed all exit formalities and clearances.</p><p>We thank you for your service and wish you success in all future endeavors.</p><br><p>@{{hr_signature}}<br><strong>@{{hr_name}}</strong><br>@{{hr_designation}}<br>@{{company_name}}</p>'
        },
        experience: {
            name: 'Experience Certificate',
            code: 'EXPERIENCE_LT',
            categoryKeywords: ['exit', 'separation', 'offboarding'],
            content: '<div style="text-align: center; margin-bottom: 20px;"><h3 style="text-decoration: underline; letter-spacing: 1px;">TO WHOMSOEVER IT MAY CONCERN</h3></div><p>This is to certify that <strong>@{{employee_name}}</strong> (Employee ID: <strong>@{{employee_id}}</strong>) was employed with <strong>@{{company_name}}</strong> from <strong>@{{joining_date}}</strong> to <strong>@{{last_working_day}}</strong>.</p><p>During their tenure of <strong>@{{tenure_string}}</strong>, they served as <strong>@{{designation}}</strong> in the <strong>@{{department}}</strong> department.</p><p>@{{conduct_statement}}</p><p>We appreciate their contributions and wish them every success in their career.</p><br><p>@{{hr_signature}}<br><strong>@{{hr_name}}</strong><br>@{{hr_designation}}<br>@{{company_name}}</p>'
        },
        noc: {
            name: 'No Objection & No Dues Certificate',
            code: 'NOC_LT',
            categoryKeywords: ['exit', 'separation', 'offboarding'],
            content: '<div style="text-align: center; margin-bottom: 20px;"><h3 style="text-decoration: underline; letter-spacing: 1px;">NO OBJECTION & NO DUES CERTIFICATE</h3></div><p>This is to certify that <strong>@{{employee_name}}</strong> (Employee ID: <strong>@{{employee_id}}</strong>), formerly designated as <strong>@{{designation}}</strong> in <strong>@{{department}}</strong>, has completed their employment tenure ending on <strong>@{{last_working_day}}</strong>.</p><p>@{{clearance_status}}</p><p><strong>@{{company_name}}</strong> has no outstanding dues or claims against the employee and has no objection to their seeking employment elsewhere.</p><br><p>@{{hr_signature}}<br><strong>@{{hr_name}}</strong><br>@{{hr_designation}}<br>@{{company_name}}</p>'
        },
        fnf: {
            name: 'Full & Final Settlement Statement',
            code: 'FNF_STMT',
            categoryKeywords: ['exit', 'separation', 'offboarding'],
            content: '<div style="text-align: center; margin-bottom: 15px;"><h3>FULL & FINAL SETTLEMENT STATEMENT</h3></div><p><strong>Employee:</strong> @{{employee_name}} (@{{employee_id}}) | <strong>Designation:</strong> @{{designation}}</p><p><strong>Tenure:</strong> @{{joining_date}} to @{{last_working_day}} (@{{tenure_string}})</p><p>@{{fnf_settlement_table}}</p><p><strong>Net Payable Amount:</strong> @{{fnf_net_payable}} (@{{fnf_net_payable_words}})</p><br><p>Employee Acceptance Signature: ________________________ &nbsp;&nbsp; Date: @{{signature_date}}</p>'
        },
        offer: {
            name: 'Offer Letter',
            code: 'OFFERLT',
            categoryKeywords: ['letters', 'offer', 'recruitment'],
            content: '<p>Dear @{{employee_name}},</p><p>We are pleased to offer you the position of <strong>@{{designation}}</strong> in the <strong>@{{department}}</strong> department at @{{company_name}}, @{{branch}}.</p><p>Your date of joining will be <strong>@{{joining_date}}</strong>. Your terms of employment will be governed by company policy.</p><p>We look forward to welcoming you to the @{{company_name}} team.</p><br><p>@{{hr_signature}}<br><strong>@{{hr_name}}</strong><br>@{{hr_designation}}<br>@{{company_name}}</p>'
        },
        selection: {
            name: 'Selection Letter',
            code: 'SELECTLT',
            categoryKeywords: ['letters', 'selection', 'recruitment'],
            content: '<p>Dear @{{employee_name}},</p><p>Congratulations! Following your interview with @{{company_name}}, we are pleased to inform you that you have been <strong>selected</strong> for the role of <strong>@{{designation}}</strong> in the <strong>@{{department}}</strong> department, reporting to @{{reporting_manager}}.</p><p>A formal offer letter will follow shortly.</p><br><p>@{{hr_signature}}<br><strong>@{{hr_name}}</strong><br>@{{hr_designation}}<br>@{{company_name}}</p>'
        }
    };

    function applyTemplatePreset(modalType, presetKey) {
        if (!presetKey || presetKey === 'custom') return;
        var preset = TEMPLATE_PRESETS[presetKey];
        if (!preset) return;

        if (modalType === 'add') {
            $('#add_tmpl_name').val(preset.name);
            $('#add_tmpl_code').val(preset.code);

            // Auto-select category if matching
            if (preset.categoryKeywords && preset.categoryKeywords.length) {
                $('#add_tmpl_category_id option').each(function() {
                    var catName = ($(this).data('name') || $(this).text()).toLowerCase();
                    for (var i = 0; i < preset.categoryKeywords.length; i++) {
                        if (catName.indexOf(preset.categoryKeywords[i]) !== -1) {
                            $('#add_tmpl_category_id').val($(this).val());
                            return false;
                        }
                    }
                });
            }

            // Set content in Quill
            if (addQuillInstance) {
                addQuillInstance.root.innerHTML = preset.content;
                $('#add_tmpl_body_input').val(preset.content);
            }
        }
    }
</script>
@endpush
