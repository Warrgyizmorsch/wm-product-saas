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
    #add_tmpl_quill_editor .ql-editor td p,
    #add_tmpl_quill_editor .ql-editor th p,
    #edit_tmpl_quill_editor .ql-editor td p,
    #edit_tmpl_quill_editor .ql-editor th p,
    .generated-doc-container td p,
    .generated-doc-container th p,
    #previewTemplateContainer td p,
    #previewTemplateContainer th p,
    #genModalPreviewBox td p,
    #genModalPreviewBox th p {
        margin-bottom: 0 !important;
    }
    /* Available Placeholders sidebar - fixed height matching editor with sleek custom scrollbar */
    .placeholder-sidebar-card {
        height: 412px !important;
        display: flex !important;
        flex-direction: column !important;
        background-color: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 12px 14px !important;
    }
    .placeholder-tags-scroll {
        flex: 1 1 auto !important;
        overflow-y: auto !important;
        padding-right: 4px !important;
    }
    .placeholder-tags-scroll::-webkit-scrollbar {
        width: 5px;
    }
    .placeholder-tags-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    .placeholder-tags-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .placeholder-tags-scroll::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .placeholder-tags-scroll .tag-btn {
        font-size: 11px !important;
        padding: 3px 7px !important;
        font-weight: 500 !important;
        border-radius: 4px !important;
        transition: all 0.15s ease-in-out;
    }
    .placeholder-tags-scroll .tag-btn:hover {
        background-color: #e2e8f0 !important;
        transform: translateY(-1px);
    }
    .placeholder-group-title {
        letter-spacing: 0.3px;
    }
    .placeholder-search-box {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .placeholder-search-box:focus-within {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb, 59, 130, 246), 0.12) !important;
    }
</style>

<!-- DOCUMENT TEMPLATES TAB -->
<div class="tab-pane fade {{ request()->query('active_tab') === 'templates' ? 'show active' : '' }}" id="templates-pane" role="tabpanel" aria-labelledby="templates-tab">
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.document_master.templates_title') }}</h5>
                <p class="fs-12 text-muted mb-0">{{ __('hrms.document_master.templates_subtitle') }}</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Search & Filters -->
                <form method="GET" action="{{ route('hrms.documents-master.index') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="active_tab" value="templates">
                    <input type="hidden" name="template_sort" id="template_sort" value="{{ request('template_sort', 'name_asc') }}">
                    
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="template_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.document_master.search_templates') }}" value="{{ request('template_search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <div class="d-flex gap-2">
                        <x-ui.sort-dropdown :label="__('hrms.document_master.sort')">
                            <a class="dropdown-item py-2 {{ request('template_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('template', 'name_asc', this); event.preventDefault();">{{ __('hrms.document_master.sort_name_asc') }}</a>
                            <a class="dropdown-item py-2 {{ request('template_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('template', 'name_desc', this); event.preventDefault();">{{ __('hrms.document_master.sort_name_desc') }}</a>
                            <a class="dropdown-item py-2 {{ request('template_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('template', 'newest', this); event.preventDefault();">{{ __('hrms.document_master.sort_newest') }}</a>
                        </x-ui.sort-dropdown>

                        <x-ui.filter :label="__('hrms.document_master.filter')" offset="0, 5" :reset-url="route('hrms.documents-master.index', ['active_tab' => 'templates'])">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.document_master.filter_options') }}</h6>
                            <div class="mb-3" style="min-width: 200px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.document_master.category') }}</label>
                                <x-ui.odoo-form-ui type="select" name="template_category_id">
                                    <option value="">{{ __('hrms.document_master.all_categories') }}</option>
                                    @foreach($allCategories as $cat)
                                        <option value="{{ $cat->id }}" {{ request('template_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3" style="min-width: 200px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.document_master.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="template_status">
                                    <option value="">{{ __('hrms.document_master.all_statuses') }}</option>
                                    <option value="active" {{ request('template_status') == 'active' ? 'selected' : '' }}>{{ __('hrms.document_master.active') }}</option>
                                    <option value="inactive" {{ request('template_status') == 'inactive' ? 'selected' : '' }}>{{ __('hrms.document_master.inactive') }}</option>
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
                        <th class="text-start px-4" style="width: 30%;">{{ __('hrms.document_master.template_name') }}</th>
                        <th style="width: 20%;">{{ __('hrms.document_master.template_code') }}</th>
                        <th style="width: 20%;">{{ __('hrms.document_master.category') }}</th>
                        <th style="width: 12%;">{{ __('hrms.document_master.status') }}</th>
                        <th class="text-end px-4" style="width: 18%;">{{ __('hrms.document_master.actions') }}</th>
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
                                    <x-ui.badge variant="success" soft class="mt-1 d-inline-block">
                                        <i class="feather-dollar-sign me-1"></i> {{ __('hrms.document_master.autolinked_payslip') }}
                                    </x-ui.badge>
                                @elseif(in_array($codeUpper, ['RELIEVING_LT', 'RELIEVING_LETTER', 'RELIEVING']) || str_contains($tmplNameLower, 'relieving'))
                                    <x-ui.badge variant="primary" soft class="mt-1 d-inline-block">
                                        <i class="feather-log-out me-1"></i> {{ __('hrms.document_master.autolinked_relieving') }}
                                    </x-ui.badge>
                                @elseif(in_array($codeUpper, ['EXPERIENCE_LT', 'EXPERIENCE_CERTIFICATE', 'EXPERIENCE']) || str_contains($tmplNameLower, 'experience'))
                                    <x-ui.badge variant="info" soft class="mt-1 d-inline-block">
                                        <i class="feather-award me-1"></i> {{ __('hrms.document_master.autolinked_experience') }}
                                    </x-ui.badge>
                                @elseif(in_array($codeUpper, ['NOC_LT', 'NOC_CERTIFICATE', 'NOC', 'NO_DUES']) || str_contains($tmplNameLower, 'no objection') || str_contains($tmplNameLower, 'noc'))
                                    <x-ui.badge variant="secondary" soft class="mt-1 d-inline-block">
                                        <i class="feather-check-circle me-1"></i> {{ __('hrms.document_master.autolinked_noc') }}
                                    </x-ui.badge>
                                @elseif(in_array($codeUpper, ['FNF_STMT', 'FNF_STATEMENT', 'FNF_SETTLEMENT']) || str_contains($tmplNameLower, 'settlement') || str_contains($tmplNameLower, 'fnf'))
                                    <x-ui.badge variant="warning" soft class="mt-1 d-inline-block">
                                        <i class="feather-file-text me-1"></i> {{ __('hrms.document_master.autolinked_fnf') }}
                                    </x-ui.badge>
                                @elseif(in_array($codeUpper, ['OFFERLT', 'OFFER_LETTER']) || str_contains($tmplNameLower, 'offer letter'))
                                    <x-ui.badge variant="primary" soft class="mt-1 d-inline-block">
                                        <i class="feather-user-check me-1"></i> {{ __('hrms.document_master.autolinked_offer') }}
                                    </x-ui.badge>
                                @elseif(in_array($codeUpper, ['SELECTLT', 'SELECTION_LETTER']) || str_contains($tmplNameLower, 'selection letter'))
                                    <x-ui.badge variant="info" soft class="mt-1 d-inline-block">
                                        <i class="feather-mail me-1"></i> {{ __('hrms.document_master.autolinked_selection') }}
                                    </x-ui.badge>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1 font-monospace fs-11">{{ $tmpl->code }}</span>
                            </td>
                            <td>
                                <x-ui.badge variant="info" soft>{{ $tmpl->category->name ?? __('hrms.document_master.uncategorized') }}</x-ui.badge>
                            </td>
                            <td>
                                @if($tmpl->status === 'active')
                                    <x-ui.badge variant="success" soft>{{ __('hrms.document_master.active') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="secondary" soft>{{ __('hrms.document_master.inactive') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end px-4">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <x-ui.button type="button" 
                                                 variant="primary" 
                                                 size="xs" 
                                                 icon="feather-file-text" 
                                                 class="btn-generate-document px-2.5 py-1"
                                                 title="{{ __('hrms.document_master.generate_doc_title') }}"
                                                 data-id="{{ $tmpl->id }}"
                                                 data-name="{{ $tmpl->name }}"
                                                 data-bs-toggle="modal"
                                                 data-bs-target="#generateFromTemplateModal">
                                        {{ __('hrms.document_master.generate_btn') }}
                                    </x-ui.button>

                                    <x-ui.action-dropdown id="tmplActions-{{ $tmpl->id }}">
                                        <li>
                                            <a class="dropdown-item py-2 btn-preview-template" 
                                               href="javascript:void(0)"
                                               data-id="{{ $tmpl->id }}"
                                               data-name="{{ $tmpl->name }}"
                                               data-bs-toggle="modal"
                                               data-bs-target="#previewTemplateModal">
                                                <i class="feather-eye me-2 text-info"></i> {{ __('hrms.document_master.live_preview') }}
                                            </a>
                                        </li>
                                        <li>
                                            <textarea id="tmpl_raw_body_{{ $tmpl->id }}" class="d-none">{{ $tmpl->body_content }}</textarea>
                                            <a class="dropdown-item py-2 btn-edit-template" 
                                               href="javascript:void(0)"
                                               data-bs-toggle="modal" 
                                               data-bs-target="#editTemplateModal"
                                               data-id="{{ $tmpl->id }}"
                                               data-name="{{ $tmpl->name }}"
                                               data-code="{{ $tmpl->code }}"
                                               data-category-id="{{ $tmpl->document_category_id }}"
                                               data-requires-signature="{{ $tmpl->requires_signature ? '1' : '0' }}"
                                               data-status="{{ $tmpl->status }}">
                                                <i class="feather-edit-2 me-2 text-primary"></i> {{ __('hrms.document_master.edit_template') }}
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('hrms.documents-master.templates.toggle-status', $tmpl->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2">
                                                    @if($tmpl->status === 'active')
                                                        <i class="feather-slash me-2 text-warning"></i> {{ __('hrms.document_master.set_inactive') }}
                                                    @else
                                                        <i class="feather-check-circle me-2 text-success"></i> {{ __('hrms.document_master.set_active') }}
                                                    @endif
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('hrms.documents-master.templates.destroy', $tmpl->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.document_master.confirm_delete_template') }}', { title: '{{ __('hrms.document_master.delete_template_title') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.document_master.delete') }}' });" class="m-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item py-2 text-danger">
                                                    <i class="feather-trash-2 me-2"></i> {{ __('hrms.document_master.delete') }}
                                                </button>
                                            </form>
                                        </li>
                                    </x-ui.action-dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-5 text-center text-muted">
                                <i class="feather-layout fs-24 mb-2 d-block"></i>
                                {!! __('hrms.document_master.empty_templates') !!}
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
                <h5 class="modal-title fw-bold" id="addTemplateModalLabel"><i class="feather-plus-circle text-primary me-1"></i> {{ __('hrms.document_master.add_template_modal_title') }}</h5>
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
                                    <div class="fw-bold text-dark fs-12 mb-0"><i class="feather-zap text-primary me-1"></i> {{ __('hrms.document_master.preset_banner_title') }}</div>
                                    <p class="fs-11 text-muted mb-0">{{ __('hrms.document_master.preset_banner_desc') }}</p>
                                </div>
                                <div style="min-width: 280px;">
                                    <select class="form-select form-select-sm fs-12" id="add_template_preset_select" onchange="applyTemplatePreset('add', this.value)">
                                        <option value="">{{ __('hrms.document_master.choose_preset') }}</option>
                                        <option value="payslip">{{ __('hrms.document_master.preset_payslip') }}</option>
                                        <option value="relieving">{{ __('hrms.document_master.preset_relieving') }}</option>
                                        <option value="experience">{{ __('hrms.document_master.preset_experience') }}</option>
                                        <option value="noc">{{ __('hrms.document_master.preset_noc') }}</option>
                                        <option value="fnf">{{ __('hrms.document_master.preset_fnf') }}</option>
                                        <option value="offer">{{ __('hrms.document_master.preset_offer') }}</option>
                                        <option value="selection">{{ __('hrms.document_master.preset_selection') }}</option>
                                        <option value="custom">{{ __('hrms.document_master.preset_custom') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.template_name')" name="name" id="add_tmpl_name" :placeholder="__('hrms.document_master.tmpl_name_placeholder')" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.template_code')" name="code" id="add_tmpl_code" :placeholder="__('hrms.document_master.tmpl_code_placeholder')" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.document_category')" name="document_category_id" id="add_tmpl_category_id">
                                <option value="">{{ __('hrms.document_master.select_category_optional') }}</option>
                                @foreach($allCategories as $cat)
                                    <option value="{{ $cat->id }}" data-name="{{ strtolower($cat->name) }}">{{ $cat->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.status')" name="status" id="add_tmpl_status" :searchable="false" :required="true">
                                <option value="active" selected>{{ __('hrms.document_master.active') }}</option>
                                <option value="inactive">{{ __('hrms.document_master.inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.requires_employee_signature')" name="requires_signature">
                                {{ __('hrms.document_master.requires_employee_signature_desc') }}
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" :label="__('hrms.document_master.import_file')" name="template_file" :placeholder="__('hrms.document_master.import_file_placeholder')" :helperText="__('hrms.document_master.import_file_helper')" />
                        </div>

                        <!-- Main Rich Editor & Placeholders Sidebar -->
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.document_master.template_content_and_design') }} <span class="text-danger">*</span></label>
                                <div id="add_tmpl_quill_editor" style="height: 380px;" class="bg-white rounded border"></div>
                                <input type="hidden" name="body_content" id="add_tmpl_body_input" required>
                                <small class="text-muted mt-1 d-block"><i class="feather-info me-1"></i> {{ __('hrms.document_master.template_editor_hint') }}</small>
                            </div>
                        </div>

                        <!-- Interactive Sidebar for Placeholders -->
                        <div class="col-md-4">
                            <div class="placeholder-sidebar-card shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold text-dark fs-12 mb-0"><i class="feather-tag text-primary me-1"></i> {{ __('hrms.document_master.placeholders') }}</h6>
                                    <span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 rounded-pill">{{ __('hrms.document_master.click_to_insert') }}</span>
                                </div>
                                
                                <!-- Instant Search Bar -->
                                <div class="mb-2.5">
                                    <div class="d-flex align-items-center placeholder-search-box px-2.5 py-1" style="height: 34px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                                        <input type="text" class="form-control border-0 bg-transparent p-0 fs-12 placeholder-search-input" 
                                               placeholder="{{ __('hrms.document_master.search_placeholders') }}" 
                                               style="box-shadow: none; height: 100%; outline: none;"
                                               oninput="filterPlaceholders(this, 'add')">
                                        <button type="button" class="btn btn-link text-muted p-0 ms-1 btn-clear-search d-none text-decoration-none" 
                                                style="font-size: 12px; line-height: 1;" onclick="clearPlaceholderSearch(this, 'add')">
                                            <i class="feather-x"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Scrollable Placeholders List -->
                                <div class="placeholder-tags-scroll" id="add_placeholder_tags_scroll">
                                    <!-- Employee Data -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-user me-1 text-primary"></i> {{ __('hrms.document_master.grp_employee_data') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{employee_name}}" data-keywords="employee name staff full name" onclick="insertTag('add', '@{{employee_name}}')">@{{employee_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{employee_id}}" data-keywords="employee id code staff id" onclick="insertTag('add', '@{{employee_id}}')">@{{employee_id}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{designation}}" data-keywords="designation position job title role" onclick="insertTag('add', '@{{designation}}')">@{{designation}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{department}}" data-keywords="department division team" onclick="insertTag('add', '@{{department}}')">@{{department}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{branch}}" data-keywords="branch office location" onclick="insertTag('add', '@{{branch}}')">@{{branch}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{reporting_manager}}" data-keywords="reporting manager supervisor boss" onclick="insertTag('add', '@{{reporting_manager}}')">@{{reporting_manager}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{joining_date}}" data-keywords="joining date start date doj" onclick="insertTag('add', '@{{joining_date}}')">@{{joining_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{probation_end_date}}" data-keywords="probation end date confirmation" onclick="insertTag('add', '@{{probation_end_date}}')">@{{probation_end_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{confirmation_date}}" data-keywords="confirmation date permanent" onclick="insertTag('add', '@{{confirmation_date}}')">@{{confirmation_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{last_working_day}}" data-keywords="last working day relieving date lwd" onclick="insertTag('add', '@{{last_working_day}}')">@{{last_working_day}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{employment_status}}" data-keywords="employment status type full time" onclick="insertTag('add', '@{{employment_status}}')">@{{employment_status}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{email}}" data-keywords="email mail id contact" onclick="insertTag('add', '@{{email}}')">@{{email}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{phone}}" data-keywords="phone mobile contact number" onclick="insertTag('add', '@{{phone}}')">@{{phone}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{dob}}" data-keywords="dob date of birth birthday" onclick="insertTag('add', '@{{dob}}')">@{{dob}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{gender}}" data-keywords="gender sex male female" onclick="insertTag('add', '@{{gender}}')">@{{gender}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{marital_status}}" data-keywords="marital status married single" onclick="insertTag('add', '@{{marital_status}}')">@{{marital_status}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{blood_group}}" data-keywords="blood group rh" onclick="insertTag('add', '@{{blood_group}}')">@{{blood_group}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{current_address}}" data-keywords="current present address location" onclick="insertTag('add', '@{{current_address}}')">@{{current_address}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{permanent_address}}" data-keywords="permanent address home" onclick="insertTag('add', '@{{permanent_address}}')">@{{permanent_address}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{emergency_contact_name}}" data-keywords="emergency contact name person kin" onclick="insertTag('add', '@{{emergency_contact_name}}')">@{{emergency_contact_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{emergency_contact_phone}}" data-keywords="emergency contact phone mobile number" onclick="insertTag('add', '@{{emergency_contact_phone}}')">@{{emergency_contact_phone}}</button>
                                        </div>
                                    </div>

                                    <!-- Salary & Compensation -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-dollar-sign me-1 text-success"></i> {{ __('hrms.document_master.grp_salary_compensation') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{annual_ctc}}" data-keywords="annual ctc salary package lpa compensation" onclick="insertTag('add', '@{{annual_ctc}}')">@{{annual_ctc}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{monthly_gross_salary}}" data-keywords="monthly gross salary compensation" onclick="insertTag('add', '@{{monthly_gross_salary}}')">@{{monthly_gross_salary}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{basic_salary}}" data-keywords="basic salary component" onclick="insertTag('add', '@{{basic_salary}}')">@{{basic_salary}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{salary_in_words}}" data-keywords="salary in words rupees" onclick="insertTag('add', '@{{salary_in_words}}')">@{{salary_in_words}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" data-tag="@{{salary_breakdown_table}}" data-keywords="salary breakdown table earnings deductions" onclick="insertTag('add', '@{{salary_breakdown_table}}')">@{{salary_breakdown_table}}</button>
                                        </div>
                                    </div>

                                    <!-- Bank & Statutory -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-credit-card me-1 text-primary"></i> {{ __('hrms.document_master.grp_bank_statutory') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{bank_name}}" data-keywords="bank name account" onclick="insertTag('add', '@{{bank_name}}')">@{{bank_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{bank_account_number}}" data-keywords="bank account number ac no" onclick="insertTag('add', '@{{bank_account_number}}')">@{{bank_account_number}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{ifsc_code}}" data-keywords="ifsc code bank branch" onclick="insertTag('add', '@{{ifsc_code}}')">@{{ifsc_code}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{pan_number}}" data-keywords="pan number card tax" onclick="insertTag('add', '@{{pan_number}}')">@{{pan_number}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{aadhaar_number}}" data-keywords="aadhaar number card uid" onclick="insertTag('add', '@{{aadhaar_number}}')">@{{aadhaar_number}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{uan_number}}" data-keywords="uan number pf epf universal" onclick="insertTag('add', '@{{uan_number}}')">@{{uan_number}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{pf_number}}" data-keywords="pf number provident fund" onclick="insertTag('add', '@{{pf_number}}')">@{{pf_number}}</button>
                                        </div>
                                    </div>

                                    <!-- Company Details -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-briefcase me-1 text-primary"></i> {{ __('hrms.document_master.grp_company_details') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_name}}" data-keywords="company name organization employer" onclick="insertTag('add', '@{{company_name}}')">@{{company_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_logo}}" data-keywords="company logo image brand" onclick="insertTag('add', '@{{company_logo}}')">@{{company_logo}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_address}}" data-keywords="company address office location headquarters" onclick="insertTag('add', '@{{company_address}}')">@{{company_address}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_email}}" data-keywords="company email contact mail" onclick="insertTag('add', '@{{company_email}}')">@{{company_email}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_phone}}" data-keywords="company phone mobile landline" onclick="insertTag('add', '@{{company_phone}}')">@{{company_phone}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_website}}" data-keywords="company website url domain" onclick="insertTag('add', '@{{company_website}}')">@{{company_website}}</button>
                                        </div>
                                    </div>

                                    <!-- Document & Signatures -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-shield me-1 text-warning"></i> {{ __('hrms.document_master.grp_document_signatures') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{current_date}}" data-keywords="current date today" onclick="insertTag('add', '@{{current_date}}')">@{{current_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{issue_date}}" data-keywords="issue date document date" onclick="insertTag('add', '@{{issue_date}}')">@{{issue_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{reference_number}}" data-keywords="reference number ref doc no" onclick="insertTag('add', '@{{reference_number}}')">@{{reference_number}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-warning border text-warning tag-btn" data-tag="@{{hr_signature}}" data-keywords="hr signature sign authorizer stamp" onclick="insertTag('add', '@{{hr_signature}}')">@{{hr_signature}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{hr_name}}" data-keywords="hr name signatory" onclick="insertTag('add', '@{{hr_name}}')">@{{hr_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{hr_designation}}" data-keywords="hr designation title role" onclick="insertTag('add', '@{{hr_designation}}')">@{{hr_designation}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{signature_date}}" data-keywords="signature date signed on" onclick="insertTag('add', '@{{signature_date}}')">@{{signature_date}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-warning border text-warning tag-btn" data-tag="@{{employee_signature}}" data-keywords="employee signature sign candidate" onclick="insertTag('add', '@{{employee_signature}}')">@{{employee_signature}}</button>
                                        </div>
                                    </div>

                                    <!-- Dynamic Tables & Lists -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-grid me-1 text-info"></i> {{ __('hrms.document_master.grp_dynamic_tables') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" data-tag="@{{education_table}}" data-keywords="education table qualification degree college" onclick="insertTag('add', '@{{education_table}}')">@{{education_table}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" data-tag="@{{experience_table}}" data-keywords="experience table past company career" onclick="insertTag('add', '@{{experience_table}}')">@{{experience_table}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" data-tag="@{{skills_list}}" data-keywords="skills list competencies" onclick="insertTag('add', '@{{skills_list}}')">@{{skills_list}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" data-tag="@{{certifications_list}}" data-keywords="certifications list course diploma" onclick="insertTag('add', '@{{certifications_list}}')">@{{certifications_list}}</button>
                                        </div>
                                    </div>

                                    <!-- Payslip & Payroll -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-dollar-sign me-1 text-success"></i> {{ __('hrms.document_master.grp_payslip_payroll') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{payslip_month}}" data-keywords="payslip month pay period" onclick="insertTag('add', '@{{payslip_month}}')">@{{payslip_month}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{working_days}}" data-keywords="working days total days" onclick="insertTag('add', '@{{working_days}}')">@{{working_days}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{paid_days}}" data-keywords="paid days attendance" onclick="insertTag('add', '@{{paid_days}}')">@{{paid_days}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{lop_days}}" data-keywords="lop days loss of pay unpaid leave" onclick="insertTag('add', '@{{lop_days}}')">@{{lop_days}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{gross_earnings}}" data-keywords="gross earnings total earnings" onclick="insertTag('add', '@{{gross_earnings}}')">@{{gross_earnings}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{total_deductions}}" data-keywords="total deductions tax pf" onclick="insertTag('add', '@{{total_deductions}}')">@{{total_deductions}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" data-tag="@{{net_pay}}" data-keywords="net pay take home in hand" onclick="insertTag('add', '@{{net_pay}}')">@{{net_pay}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{net_pay_in_words}}" data-keywords="net pay in words rupees" onclick="insertTag('add', '@{{net_pay_in_words}}')">@{{net_pay_in_words}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" data-tag="@{{earnings_table}}" data-keywords="earnings table allowance basic hra" onclick="insertTag('add', '@{{earnings_table}}')">@{{earnings_table}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" data-tag="@{{deductions_table}}" data-keywords="deductions table tds pf pt" onclick="insertTag('add', '@{{deductions_table}}')">@{{deductions_table}}</button>
                                        </div>
                                    </div>

                                    <!-- Exit & Separation -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-log-out me-1 text-danger"></i> {{ __('hrms.document_master.grp_exit_separation') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{separation_type}}" data-keywords="separation type resignation termination exit" onclick="insertTag('add', '@{{separation_type}}')">@{{separation_type}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{resignation_date}}" data-keywords="resignation date applied exit" onclick="insertTag('add', '@{{resignation_date}}')">@{{resignation_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{tenure_string}}" data-keywords="tenure duration service period years months" onclick="insertTag('add', '@{{tenure_string}}')">@{{tenure_string}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{conduct_statement}}" data-keywords="conduct statement character behavior" onclick="insertTag('add', '@{{conduct_statement}}')">@{{conduct_statement}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{clearance_status}}" data-keywords="clearance status nodues exit" onclick="insertTag('add', '@{{clearance_status}}')">@{{clearance_status}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-danger border text-danger tag-btn" data-tag="@{{fnf_settlement_table}}" data-keywords="fnf settlement table full final" onclick="insertTag('add', '@{{fnf_settlement_table}}')">@{{fnf_settlement_table}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{fnf_net_payable}}" data-keywords="fnf net payable amount settlement" onclick="insertTag('add', '@{{fnf_net_payable}}')">@{{fnf_net_payable}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{fnf_net_payable_words}}" data-keywords="fnf net payable in words" onclick="insertTag('add', '@{{fnf_net_payable_words}}')">@{{fnf_net_payable_words}}</button>
                                        </div>
                                    </div>

                                    <!-- Empty Search State -->
                                    <div class="placeholder-empty-state d-none text-center py-4 text-muted fs-11">
                                        <i class="feather-search text-muted mb-1 d-block fs-16"></i>
                                        <span>{{ __('hrms.document_master.no_placeholders_found') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.save_template_btn') }}</x-ui.button>
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
                <h5 class="modal-title fw-bold" id="editTemplateModalLabel"><i class="feather-edit text-primary me-1"></i> {{ __('hrms.document_master.edit_template_modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editTemplateForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.template_name')" name="name" id="edit_tmpl_name" :placeholder="__('hrms.document_master.tmpl_name_placeholder')" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.template_code')" name="code" id="edit_tmpl_code" :placeholder="__('hrms.document_master.tmpl_code_placeholder')" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.document_category')" name="document_category_id" id="edit_tmpl_category_id">
                                <option value="">{{ __('hrms.document_master.select_category_optional') }}</option>
                                @foreach($allCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.status')" name="status" id="edit_tmpl_status" :searchable="false" :required="true">
                                <option value="active">{{ __('hrms.document_master.active') }}</option>
                                <option value="inactive">{{ __('hrms.document_master.inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.document_master.requires_employee_signature')" name="requires_signature" id="edit_tmpl_requires_signature" value="1">
                                {{ __('hrms.document_master.requires_employee_signature_desc') }}
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" :label="__('hrms.document_master.replace_file')" name="template_file" :placeholder="__('hrms.document_master.replace_file_placeholder')" />
                        </div>

                        <!-- Main Rich Editor & Placeholders Sidebar -->
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('hrms.document_master.template_content_and_design') }} <span class="text-danger">*</span></label>
                                <div id="edit_tmpl_quill_editor" style="height: 380px;" class="bg-white rounded border"></div>
                                <input type="hidden" name="body_content" id="edit_tmpl_body_input" required>
                            </div>
                        </div>

                        <!-- Interactive Sidebar for Placeholders -->
                        <div class="col-md-4">
                            <div class="placeholder-sidebar-card shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold text-dark fs-12 mb-0"><i class="feather-tag text-primary me-1"></i> {{ __('hrms.document_master.placeholders') }}</h6>
                                    <span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 rounded-pill">{{ __('hrms.document_master.click_to_insert') }}</span>
                                </div>
                                
                                <!-- Instant Search Bar -->
                                <div class="mb-2.5">
                                    <div class="d-flex align-items-center placeholder-search-box px-2.5 py-1" style="height: 34px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                                        <input type="text" class="form-control border-0 bg-transparent p-0 fs-12 placeholder-search-input" 
                                               placeholder="{{ __('hrms.document_master.search_placeholders') }}" 
                                               style="box-shadow: none; height: 100%; outline: none;"
                                               oninput="filterPlaceholders(this, 'edit')">
                                        <button type="button" class="btn btn-link text-muted p-0 ms-1 btn-clear-search d-none text-decoration-none" 
                                                style="font-size: 12px; line-height: 1;" onclick="clearPlaceholderSearch(this, 'edit')">
                                            <i class="feather-x"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Scrollable Placeholders List -->
                                <div class="placeholder-tags-scroll" id="edit_placeholder_tags_scroll">
                                    <!-- Employee Data -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-user me-1 text-primary"></i> {{ __('hrms.document_master.grp_employee_data') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{employee_name}}" data-keywords="employee name staff full name" onclick="insertTag('edit', '@{{employee_name}}')">@{{employee_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{employee_id}}" data-keywords="employee id code staff id" onclick="insertTag('edit', '@{{employee_id}}')">@{{employee_id}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{designation}}" data-keywords="designation position job title role" onclick="insertTag('edit', '@{{designation}}')">@{{designation}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{department}}" data-keywords="department division team" onclick="insertTag('edit', '@{{department}}')">@{{department}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{branch}}" data-keywords="branch office location" onclick="insertTag('edit', '@{{branch}}')">@{{branch}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{reporting_manager}}" data-keywords="reporting manager supervisor boss" onclick="insertTag('edit', '@{{reporting_manager}}')">@{{reporting_manager}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{joining_date}}" data-keywords="joining date start date doj" onclick="insertTag('edit', '@{{joining_date}}')">@{{joining_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{probation_end_date}}" data-keywords="probation end date confirmation" onclick="insertTag('edit', '@{{probation_end_date}}')">@{{probation_end_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{confirmation_date}}" data-keywords="confirmation date permanent" onclick="insertTag('edit', '@{{confirmation_date}}')">@{{confirmation_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{last_working_day}}" data-keywords="last working day relieving date lwd" onclick="insertTag('edit', '@{{last_working_day}}')">@{{last_working_day}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{employment_status}}" data-keywords="employment status type full time" onclick="insertTag('edit', '@{{employment_status}}')">@{{employment_status}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{email}}" data-keywords="email mail id contact" onclick="insertTag('edit', '@{{email}}')">@{{email}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{phone}}" data-keywords="phone mobile contact number" onclick="insertTag('edit', '@{{phone}}')">@{{phone}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{dob}}" data-keywords="dob date of birth birthday" onclick="insertTag('edit', '@{{dob}}')">@{{dob}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{gender}}" data-keywords="gender sex male female" onclick="insertTag('edit', '@{{gender}}')">@{{gender}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{marital_status}}" data-keywords="marital status married single" onclick="insertTag('edit', '@{{marital_status}}')">@{{marital_status}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{blood_group}}" data-keywords="blood group rh" onclick="insertTag('edit', '@{{blood_group}}')">@{{blood_group}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{current_address}}" data-keywords="current present address location" onclick="insertTag('edit', '@{{current_address}}')">@{{current_address}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{permanent_address}}" data-keywords="permanent address home" onclick="insertTag('edit', '@{{permanent_address}}')">@{{permanent_address}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{emergency_contact_name}}" data-keywords="emergency contact name person kin" onclick="insertTag('edit', '@{{emergency_contact_name}}')">@{{emergency_contact_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{emergency_contact_phone}}" data-keywords="emergency contact phone mobile number" onclick="insertTag('edit', '@{{emergency_contact_phone}}')">@{{emergency_contact_phone}}</button>
                                        </div>
                                    </div>

                                    <!-- Salary & Compensation -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-dollar-sign me-1 text-success"></i> {{ __('hrms.document_master.grp_salary_compensation') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{annual_ctc}}" data-keywords="annual ctc salary package lpa compensation" onclick="insertTag('edit', '@{{annual_ctc}}')">@{{annual_ctc}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{monthly_gross_salary}}" data-keywords="monthly gross salary compensation" onclick="insertTag('edit', '@{{monthly_gross_salary}}')">@{{monthly_gross_salary}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{basic_salary}}" data-keywords="basic salary component" onclick="insertTag('edit', '@{{basic_salary}}')">@{{basic_salary}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{salary_in_words}}" data-keywords="salary in words rupees" onclick="insertTag('edit', '@{{salary_in_words}}')">@{{salary_in_words}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" data-tag="@{{salary_breakdown_table}}" data-keywords="salary breakdown table earnings deductions" onclick="insertTag('edit', '@{{salary_breakdown_table}}')">@{{salary_breakdown_table}}</button>
                                        </div>
                                    </div>

                                    <!-- Bank & Statutory -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-credit-card me-1 text-primary"></i> {{ __('hrms.document_master.grp_bank_statutory') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{bank_name}}" data-keywords="bank name account" onclick="insertTag('edit', '@{{bank_name}}')">@{{bank_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{bank_account_number}}" data-keywords="bank account number ac no" onclick="insertTag('edit', '@{{bank_account_number}}')">@{{bank_account_number}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{ifsc_code}}" data-keywords="ifsc code bank branch" onclick="insertTag('edit', '@{{ifsc_code}}')">@{{ifsc_code}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{pan_number}}" data-keywords="pan number card tax" onclick="insertTag('edit', '@{{pan_number}}')">@{{pan_number}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{aadhaar_number}}" data-keywords="aadhaar number card uid" onclick="insertTag('edit', '@{{aadhaar_number}}')">@{{aadhaar_number}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{uan_number}}" data-keywords="uan number pf epf universal" onclick="insertTag('edit', '@{{uan_number}}')">@{{uan_number}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{pf_number}}" data-keywords="pf number provident fund" onclick="insertTag('edit', '@{{pf_number}}')">@{{pf_number}}</button>
                                        </div>
                                    </div>

                                    <!-- Company Details -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-briefcase me-1 text-primary"></i> {{ __('hrms.document_master.grp_company_details') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_name}}" data-keywords="company name organization employer" onclick="insertTag('edit', '@{{company_name}}')">@{{company_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_logo}}" data-keywords="company logo image brand" onclick="insertTag('edit', '@{{company_logo}}')">@{{company_logo}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_address}}" data-keywords="company address office location headquarters" onclick="insertTag('edit', '@{{company_address}}')">@{{company_address}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_email}}" data-keywords="company email contact mail" onclick="insertTag('edit', '@{{company_email}}')">@{{company_email}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_phone}}" data-keywords="company phone mobile landline" onclick="insertTag('edit', '@{{company_phone}}')">@{{company_phone}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{company_website}}" data-keywords="company website url domain" onclick="insertTag('edit', '@{{company_website}}')">@{{company_website}}</button>
                                        </div>
                                    </div>

                                    <!-- Document & Signatures -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-shield me-1 text-warning"></i> {{ __('hrms.document_master.grp_document_signatures') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{current_date}}" data-keywords="current date today" onclick="insertTag('edit', '@{{current_date}}')">@{{current_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{issue_date}}" data-keywords="issue date document date" onclick="insertTag('edit', '@{{issue_date}}')">@{{issue_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{reference_number}}" data-keywords="reference number ref doc no" onclick="insertTag('edit', '@{{reference_number}}')">@{{reference_number}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-warning border text-warning tag-btn" data-tag="@{{hr_signature}}" data-keywords="hr signature sign authorizer stamp" onclick="insertTag('edit', '@{{hr_signature}}')">@{{hr_signature}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{hr_name}}" data-keywords="hr name signatory" onclick="insertTag('edit', '@{{hr_name}}')">@{{hr_name}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{hr_designation}}" data-keywords="hr designation title role" onclick="insertTag('edit', '@{{hr_designation}}')">@{{hr_designation}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{signature_date}}" data-keywords="signature date signed on" onclick="insertTag('edit', '@{{signature_date}}')">@{{signature_date}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-warning border text-warning tag-btn" data-tag="@{{employee_signature}}" data-keywords="employee signature sign candidate" onclick="insertTag('edit', '@{{employee_signature}}')">@{{employee_signature}}</button>
                                        </div>
                                    </div>

                                    <!-- Dynamic Tables & Lists -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-grid me-1 text-info"></i> {{ __('hrms.document_master.grp_dynamic_tables') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" data-tag="@{{education_table}}" data-keywords="education table qualification degree college" onclick="insertTag('edit', '@{{education_table}}')">@{{education_table}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" data-tag="@{{experience_table}}" data-keywords="experience table past company career" onclick="insertTag('edit', '@{{experience_table}}')">@{{experience_table}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" data-tag="@{{skills_list}}" data-keywords="skills list competencies" onclick="insertTag('edit', '@{{skills_list}}')">@{{skills_list}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-info border text-info tag-btn" data-tag="@{{certifications_list}}" data-keywords="certifications list course diploma" onclick="insertTag('edit', '@{{certifications_list}}')">@{{certifications_list}}</button>
                                        </div>
                                    </div>

                                    <!-- Payslip & Payroll -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-dollar-sign me-1 text-success"></i> {{ __('hrms.document_master.grp_payslip_payroll') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{payslip_month}}" data-keywords="payslip month pay period" onclick="insertTag('edit', '@{{payslip_month}}')">@{{payslip_month}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{working_days}}" data-keywords="working days total days" onclick="insertTag('edit', '@{{working_days}}')">@{{working_days}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{paid_days}}" data-keywords="paid days attendance" onclick="insertTag('edit', '@{{paid_days}}')">@{{paid_days}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{lop_days}}" data-keywords="lop days loss of pay unpaid leave" onclick="insertTag('edit', '@{{lop_days}}')">@{{lop_days}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{gross_earnings}}" data-keywords="gross earnings total earnings" onclick="insertTag('edit', '@{{gross_earnings}}')">@{{gross_earnings}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{total_deductions}}" data-keywords="total deductions tax pf" onclick="insertTag('edit', '@{{total_deductions}}')">@{{total_deductions}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" data-tag="@{{net_pay}}" data-keywords="net pay take home in hand" onclick="insertTag('edit', '@{{net_pay}}')">@{{net_pay}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{net_pay_in_words}}" data-keywords="net pay in words rupees" onclick="insertTag('edit', '@{{net_pay_in_words}}')">@{{net_pay_in_words}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" data-tag="@{{earnings_table}}" data-keywords="earnings table allowance basic hra" onclick="insertTag('edit', '@{{earnings_table}}')">@{{earnings_table}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-success border text-success tag-btn" data-tag="@{{deductions_table}}" data-keywords="deductions table tds pf pt" onclick="insertTag('edit', '@{{deductions_table}}')">@{{deductions_table}}</button>
                                        </div>
                                    </div>

                                    <!-- Exit & Separation -->
                                    <div class="placeholder-group mb-2">
                                        <span class="fw-bold text-dark fs-10 w-100 d-block mb-1 placeholder-group-title"><i class="feather-log-out me-1 text-danger"></i> {{ __('hrms.document_master.grp_exit_separation') }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{separation_type}}" data-keywords="separation type resignation termination exit" onclick="insertTag('edit', '@{{separation_type}}')">@{{separation_type}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{resignation_date}}" data-keywords="resignation date applied exit" onclick="insertTag('edit', '@{{resignation_date}}')">@{{resignation_date}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{tenure_string}}" data-keywords="tenure duration service period years months" onclick="insertTag('edit', '@{{tenure_string}}')">@{{tenure_string}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{conduct_statement}}" data-keywords="conduct statement character behavior" onclick="insertTag('edit', '@{{conduct_statement}}')">@{{conduct_statement}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{clearance_status}}" data-keywords="clearance status nodues exit" onclick="insertTag('edit', '@{{clearance_status}}')">@{{clearance_status}}</button>
                                            <button type="button" class="btn btn-xs btn-soft-danger border text-danger tag-btn" data-tag="@{{fnf_settlement_table}}" data-keywords="fnf settlement table full final" onclick="insertTag('edit', '@{{fnf_settlement_table}}')">@{{fnf_settlement_table}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{fnf_net_payable}}" data-keywords="fnf net payable amount settlement" onclick="insertTag('edit', '@{{fnf_net_payable}}')">@{{fnf_net_payable}}</button>
                                            <button type="button" class="btn btn-xs btn-white border text-dark tag-btn" data-tag="@{{fnf_net_payable_words}}" data-keywords="fnf net payable in words" onclick="insertTag('edit', '@{{fnf_net_payable_words}}')">@{{fnf_net_payable_words}}</button>
                                        </div>
                                    </div>

                                    <!-- Empty Search State -->
                                    <div class="placeholder-empty-state d-none text-center py-4 text-muted fs-11">
                                        <i class="feather-search text-muted mb-1 d-block fs-16"></i>
                                        <span>{{ __('hrms.document_master.no_placeholders_found') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.update_template_btn') }}</x-ui.button>
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
                <h5 class="modal-title fw-bold" id="generateFromTemplateModalLabel"><i class="feather-file-text text-primary me-1"></i> {{ __('hrms.document_master.generate_modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.documents.bulk-upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="upload_mode" value="generate_template">
                <input type="hidden" name="document_template_id" id="gen_modal_template_id">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.target_employee')" name="employee_id" id="gen_modal_employee_id" :required="true">
                                <option value="">{{ __('hrms.document_master.select_employee_placeholder') }}</option>
                                @foreach($allEmployees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name ?? $emp->display_name }} ({{ $emp->employee_id }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.doc_title_name')" name="document_title" id="gen_modal_title" :placeholder="__('hrms.document_master.doc_title_placeholder')" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.ref_no_optional')" name="reference_number" :placeholder="__('hrms.document_master.ref_no_placeholder')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="date" :label="__('hrms.document_master.issue_date_label')" name="issue_date" value="{{ date('Y-m-d') }}" />
                        </div>

                        <!-- HR DIGITAL SIGNATURE SECTION (WHEN GENERATING / ASSIGNING DOCUMENT) -->
                        <div class="col-12 mt-3 pt-3 border-top">
                            <h6 class="fw-bold text-dark fs-13 mb-2"><i class="feather-edit-3 text-warning me-1"></i> {{ __('hrms.document_master.hr_sig_section_title') }}</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.hr_signer_name')" name="hr_name" value="{{ auth()->user()->name }}" placeholder="{{ auth()->user()->name }}" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.hr_designation_title')" name="hr_designation" value="HR Manager" />
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-bold fs-12 text-dark mb-0">{{ __('hrms.document_master.hr_sig_image_label') }}</label>
                                        <ul class="nav nav-pills bg-light p-1 rounded-pill border gap-1" id="tmplHrSigInputTabs" role="tablist">
                                            <li class="nav-item">
                                                <button type="button" id="btn_tmpl_hr_sig_draw" class="nav-link btn-xs py-1 px-3 fs-11 fw-bold rounded-pill active border-0 btn-primary" style="background-color: var(--bs-primary) !important; color: #ffffff !important;" onclick="switchTmplHrSigMode('draw')">
                                                    <i class="feather-edit-2 me-1"></i> {{ __('hrms.document_master.draw_signature') }}
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button type="button" id="btn_tmpl_hr_sig_upload" class="nav-link btn-xs py-1 px-3 fs-11 fw-bold text-secondary rounded-pill border-0" onclick="switchTmplHrSigMode('upload')">
                                                    <i class="feather-upload-cloud me-1"></i> {{ __('hrms.document_master.upload_image') }}
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    <input type="hidden" name="hr_signature_data" id="tmpl_hr_sig_input_data">

                                    <!-- DRAW HR SIGNATURE TAB -->
                                    <div id="tmpl_hr_sig_draw_container">
                                        <div class="d-flex justify-content-end mb-1">
                                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="clearTmplHrSigCanvas()">
                                                <i class="feather-rotate-ccw me-1"></i> {{ __('hrms.document_master.clear_canvas') }}
                                            </button>
                                        </div>
                                        <div class="p-1 text-center position-relative shadow-sm" style="border: 2px dashed #94a3b8 !important; background-color: #f8fafc; border-radius: 8px;">
                                            <canvas id="tmplHrSignatureCanvas" width="400" height="100" style="touch-action: none; cursor: crosshair; background: #ffffff; width: 100%; height: 100px; border-radius: 6px;"></canvas>
                                        </div>
                                        <small class="text-muted fs-11 mt-1 d-block"><i class="feather-info me-1"></i> {{ __('hrms.document_master.draw_sig_helper') }}</small>
                                    </div>

                                    <!-- UPLOAD HR SIGNATURE IMAGE TAB -->
                                    <div id="tmpl_hr_sig_upload_container" class="d-none">
                                        <div class="position-relative w-100">
                                            <input type="file" name="hr_signature_file" id="tmpl_hr_sig_file_input" accept="image/png, image/jpeg, image/jpg, image/webp" class="position-absolute opacity-0 w-100 h-100" style="left:0; top:0; cursor:pointer; z-index:5;" onchange="handleTmplHrSigUpload(this)">
                                            <div class="form-control d-flex flex-column align-items-center justify-content-center gap-1.5 p-3 text-center" style="border: 2px dashed rgba(var(--bs-primary-rgb, 59, 130, 246), 0.3); background-color: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.02); border-radius: 8px; min-height: 110px;">
                                                <div id="tmpl_hr_sig_upload_preview_box" class="d-flex flex-column align-items-center justify-content-center">
                                                    <i class="feather-upload-cloud mb-1" style="font-size: 24px; color: var(--bs-primary);"></i>
                                                    <span class="fw-bold text-dark fs-12 file-name-label">{{ __('hrms.document_master.upload_sig_label') }}</span>
                                                    <small class="text-muted fs-10 mt-0.5">{{ __('hrms.document_master.upload_sig_helper') }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Instant Live Preview Box -->
                        <div class="col-12 mt-3">
                            <label class="form-label fw-bold text-dark fs-12 mb-1"><i class="feather-eye me-1 text-primary"></i> {{ __('hrms.document_master.live_rendered_preview') }}</label>
                            <div id="genModalPreviewBox" class="border rounded p-3 bg-white min-h-200" style="max-height: 350px; overflow-y: auto;">
                                <div class="text-center py-4 text-muted fs-12">
                                    {{ __('hrms.document_master.select_emp_for_preview') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="feather-check-circle" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.generate_and_save') }}</x-ui.button>
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
                <h5 class="modal-title fw-bold" id="previewTemplateModalLabel"><i class="feather-eye text-primary me-1"></i> {{ __('hrms.document_master.live_doc_preview_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="previewTemplateContainer" class="border rounded p-3 bg-white min-h-300">
                    <div class="text-center py-5 text-muted">
                        <i class="feather-loader spinner-border spinner-border-sm me-2"></i> {{ __('hrms.document_master.generating_preview') }}
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 gap-2">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.close') }}</x-ui.button>
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

    // Instant client-side search for placeholders (no page load, zero latency)
    function filterPlaceholders(inputEl, mode) {
        var query = $(inputEl).val().toLowerCase().trim();
        var container = (mode === 'add') ? $('#add_placeholder_tags_scroll') : $('#edit_placeholder_tags_scroll');
        var clearBtn = $(inputEl).siblings('.btn-clear-search');
        
        if (query.length > 0) {
            clearBtn.removeClass('d-none');
        } else {
            clearBtn.addClass('d-none');
        }

        var totalVisible = 0;

        container.find('.placeholder-group').each(function() {
            var group = $(this);
            var groupVisibleCount = 0;

            group.find('.tag-btn').each(function() {
                var btn = $(this);
                var tagText = (btn.attr('data-tag') || btn.text() || '').toLowerCase();
                var keywords = (btn.attr('data-keywords') || '').toLowerCase();

                if (!query || tagText.indexOf(query) !== -1 || keywords.indexOf(query) !== -1) {
                    btn.removeClass('d-none');
                    groupVisibleCount++;
                    totalVisible++;
                } else {
                    btn.addClass('d-none');
                }
            });

            if (groupVisibleCount > 0) {
                group.removeClass('d-none');
            } else {
                group.addClass('d-none');
            }
        });

        var emptyState = container.find('.placeholder-empty-state');
        if (totalVisible === 0) {
            emptyState.removeClass('d-none');
        } else {
            emptyState.addClass('d-none');
        }
    }

    function clearPlaceholderSearch(btnEl, mode) {
        var input = $(btnEl).siblings('.placeholder-search-input');
        input.val('');
        filterPlaceholders(input[0], mode);
        input.focus();
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

        // Sync Quill editor to hidden body_content inputs on form submit
        $('#addTemplateModal form').on('submit', function() {
            if (addQuillInstance) {
                var html = addQuillInstance.root.innerHTML;
                $('#add_tmpl_body_input').val(html === '<p><br></p>' ? '' : html);
            }
        });

        $('#editTemplateForm').on('submit', function() {
            if (editQuillInstance) {
                var html = editQuillInstance.root.innerHTML;
                $('#edit_tmpl_body_input').val(html === '<p><br></p>' ? '' : html);
            }
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
            form.attr('action', '{{ url("hrms/documents-master/templates") }}/' + id);

            $('#edit_tmpl_name').val($(this).data('name'));
            $('#edit_tmpl_code').val($(this).data('code'));
            $('#edit_tmpl_category_id').val($(this).data('category-id')).trigger('change');
            $('#edit_tmpl_status').val($(this).data('status')).trigger('change');

            var reqSig = $(this).data('requires-signature');
            $('#edit_tmpl_requires_signature').prop('checked', reqSig == 1 || reqSig === '1' || reqSig === true);

            var bodyVal = $('#tmpl_raw_body_' + id).val() || $(this).data('body') || '';
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
            $('#generateFromTemplateModalLabel').html('<i class="feather-file-text text-primary me-1"></i> {{ __('hrms.document_master.generate_btn') }}: ' + tmplName);
            $('#genModalPreviewBox').html('<div class="text-center py-4 text-muted fs-12">{{ __('hrms.document_master.select_emp_for_preview') }}</div>');
            
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
            $('#genModalPreviewBox').html('<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>{{ __('hrms.document_master.rendering_preview') }}</div>');
            $.ajax({
                url: '{{ url("hrms/documents-master/templates") }}/' + tmplId + '/preview?employee_id=' + empId,
                type: 'GET',
                success: function(res) {
                    if (res && res.html) {
                        $('#genModalPreviewBox').html(res.html);
                    } else {
                        $('#genModalPreviewBox').html('<div class="alert alert-warning m-0">{{ __('hrms.document_master.unable_gen_preview') }}</div>');
                    }
                },
                error: function(xhr) {
                    var errMsg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : '{{ __('hrms.document_master.error_rendering_preview') }}';
                    $('#genModalPreviewBox').html('<div class="alert alert-danger m-0">' + errMsg + '</div>');
                }
            });
        }

        // Live Template Preview Handler
        $(document).on('click', '.btn-preview-template', function() {
            var tmplId = $(this).data('id');
            var tmplName = $(this).data('name');
            $('#previewTemplateModalLabel').html('<i class="feather-eye text-primary me-1"></i> {{ __('hrms.document_master.live_preview') }}: ' + tmplName);
            $('#previewTemplateContainer').html('<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>{{ __('hrms.document_master.loading_preview') }}</div>');

            $.ajax({
                url: '{{ url("hrms/documents-master/templates") }}/' + tmplId + '/preview',
                type: 'GET',
                success: function(res) {
                    if (res && res.html) {
                        $('#previewTemplateContainer').html(res.html);
                    } else {
                        $('#previewTemplateContainer').html('<div class="alert alert-warning">{{ __('hrms.document_master.unable_render_template_preview') }}</div>');
                    }
                },
                error: function() {
                    $('#previewTemplateContainer').html('<div class="alert alert-danger">{{ __('hrms.document_master.error_loading_preview') }}</div>');
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
                            $('#add_tmpl_category_id').val($(this).val()).trigger('change');
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
