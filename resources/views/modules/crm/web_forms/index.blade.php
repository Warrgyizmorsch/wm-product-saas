@extends('layouts.duralux')

@section('title', 'Web-to-Lead Dynamic Form Builder | SaaS ERP')
@section('page-title', 'Web-to-Lead Dynamic Form Builder')
@section('breadcrumb', 'Revenue Cycle / CRM / Web-to-Lead Forms')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="feather-layout me-2 text-primary"></i>Web-to-Lead Dynamic Form Builder
            </h4>
            <p class="text-muted fs-12 mb-0">
                Design custom lead forms with dynamic fields (Dropdown, Radio, Date, Number, etc.), embed them on any website, and capture leads directly into your CRM.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div id="autoSaveIndicator" class="d-flex align-items-center me-2">
                <i class="feather-check-circle text-success me-1"></i><span class="text-success fs-11 fw-semibold">Auto-saved</span>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetDefaultFields()">
                <i class="feather-rotate-ccw me-1 fs-12"></i>Reset Defaults
            </button>
            <button type="button" class="btn btn-success btn-sm d-flex align-items-center gap-1.5" id="btnSaveSchema" onclick="saveFormLayout()">
                <i class="feather-save fs-13"></i>
                <span>Save Layout</span>
            </button>
            <a href="{{ $baseUrl }}/forms/lead-capture/{{ $tenantIdentifier }}" target="_blank" class="btn btn-primary btn-sm d-flex align-items-center gap-1.5">
                <i class="feather-external-link fs-13"></i>
                <span>Open Live Form</span>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Form Builder & Customizer -->
        <div class="col-xl-6 col-lg-6">
            <!-- Form General Settings Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white py-2.5 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark fs-13">
                        <i class="feather-sliders me-2 text-primary"></i>1. Branding & Display Settings
                    </h6>
                    <span class="badge bg-soft-primary text-primary font-monospace fs-11">Tenant: #{{ $tenant->id ?? 1 }}</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark fs-11 mb-1">Form Heading</label>
                            <input type="text" id="inputTitle" class="form-control form-control-sm" value="{{ $savedConfig['title'] ?? 'Get in Touch with Us' }}" oninput="updatePreview()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark fs-11 mb-1">Button Label</label>
                            <input type="text" id="inputBtnText" class="form-control form-control-sm" value="{{ $savedConfig['btn_text'] ?? 'Submit Enquiry' }}" oninput="updatePreview()">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark fs-11 mb-1">Sub-heading</label>
                            <input type="text" id="inputSubtitle" class="form-control form-control-sm" value="{{ $savedConfig['subtitle'] ?? 'Fill out the form below and our team will reach out promptly.' }}" oninput="updatePreview()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark fs-11 mb-1">Brand Accent Color</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" id="inputColor" class="form-control form-control-color border" value="{{ $savedConfig['color'] ?? '#4f46e5' }}" onchange="updatePreview()">
                                <input type="text" id="inputColorHex" class="form-control form-control-sm font-monospace text-uppercase" value="{{ $savedConfig['color'] ?? '#4F46E5' }}" style="max-width: 100px;" oninput="syncColorHex(this.value)">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark fs-11 mb-1">Floating Widget Position</label>
                            <select id="inputPosition" class="form-select form-select-sm" onchange="updatePreview()">
                                <option value="bottom-right" {{ ($savedConfig['position'] ?? 'bottom-right') === 'bottom-right' ? 'selected' : '' }}>Bottom Right (Default)</option>
                                <option value="bottom-left" {{ ($savedConfig['position'] ?? '') === 'bottom-left' ? 'selected' : '' }}>Bottom Left</option>
                                <option value="center-right" {{ ($savedConfig['position'] ?? '') === 'center-right' ? 'selected' : '' }}>Middle Right (Side Tab)</option>
                                <option value="center-left" {{ ($savedConfig['position'] ?? '') === 'center-left' ? 'selected' : '' }}>Middle Left (Side Tab)</option>
                                <option value="top-right" {{ ($savedConfig['position'] ?? '') === 'top-right' ? 'selected' : '' }}>Top Right</option>
                                <option value="top-left" {{ ($savedConfig['position'] ?? '') === 'top-left' ? 'selected' : '' }}>Top Left</option>
                                <option value="bottom-center" {{ ($savedConfig['position'] ?? '') === 'bottom-center' ? 'selected' : '' }}>Bottom Center</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark fs-11 mb-1">Theme Mode</label>
                            <div class="d-flex gap-3 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="formTheme" id="themeLight" value="light" {{ ($savedConfig['theme'] ?? 'light') === 'light' ? 'checked' : '' }} onchange="updatePreview()">
                                    <label class="form-check-label fs-12" for="themeLight">Light</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="formTheme" id="themeDark" value="dark" {{ ($savedConfig['theme'] ?? 'light') === 'dark' ? 'checked' : '' }} onchange="updatePreview()">
                                    <label class="form-check-label fs-12" for="themeDark">Dark</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between p-2 rounded-2 bg-light border gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-soft-primary text-primary fs-11">
                                        <i class="feather-briefcase me-1"></i>Company: <strong>{{ $activeCompany->company_name ?? 'Default Company' }}</strong>
                                    </span>
                                    <span class="badge bg-soft-info text-info fs-11">
                                        <i class="feather-map-pin me-1"></i>Branch: <strong>{{ $activeBranch->name ?? 'Default Branch' }}</strong>
                                    </span>
                                </div>
                                <span class="text-muted fs-11">
                                    <i class="feather-check-circle me-1 text-success"></i>Auto-assigned from active session
                                </span>
                            </div>
                            <input type="hidden" id="inputCompany" value="{{ $activeCompanyId }}">
                            <input type="hidden" id="inputBranch" value="{{ $activeBranchId }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark fs-11 mb-1">Custom Redirect / Thank You URL <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="url" id="inputRedirect" class="form-control form-control-sm" placeholder="https://yourwebsite.com/thank-you" value="{{ $savedConfig['redirect_url'] ?? '' }}" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Form Fields Builder Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-2.5 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark fs-13">
                            <i class="feather-list me-2 text-primary"></i>2. Form Fields (Drag & Custom Fields)
                        </h6>
                        <span class="text-muted fs-11">Reorder, add custom inputs, dropdowns, and radio choices.</span>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm py-1 px-2.5 fs-12" onclick="openAddFieldModal()">
                        <i class="feather-plus me-1"></i>Add Field
                    </button>
                </div>
                <div class="card-body p-3">
                    <!-- Fields List Container (Draggable / Sortable) -->
                    <div id="fieldsListContainer" class="d-flex flex-column gap-2">
                        <!-- Dynamically populated via JS -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Live Interactive Preview -->
        <div class="col-xl-6 col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 mb-4 sticky-top" style="top: 20px;">
                <div class="card-header bg-white py-2.5 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-soft-success text-success fs-11"><i class="feather-check-circle me-1"></i>Live Preview</span>
                        <span class="text-muted fs-12">Real-time preview of your form</span>
                    </div>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-light active" id="btnViewDesktop" onclick="setDeviceView('desktop')">
                            <i class="feather-monitor me-1"></i>Desktop
                        </button>
                        <button type="button" class="btn btn-light" id="btnViewMobile" onclick="setDeviceView('mobile')">
                            <i class="feather-smartphone me-1"></i>Mobile
                        </button>
                    </div>
                </div>
                <div class="card-body p-3 text-center bg-light rounded-bottom-3" style="min-height: 590px; display: flex; align-items: center; justify-content: center;">
                    <div id="previewWrapper" style="width: 100%; max-width: 540px; transition: max-width 0.3s ease;">
                        <iframe id="previewIframe" src="{{ $baseUrl }}/forms/lead-capture/{{ $tenantIdentifier }}" style="width: 100%; height: 585px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06);" title="Form Preview"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Embed Code Snippets Section -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="feather-code me-2 text-primary"></i>Ready-to-Use Embed & Integration Snippets
            </h6>
            <span class="badge bg-soft-info text-info fs-11">Instant Setup on any Website</span>
        </div>
        <div class="card-body p-4">
            <ul class="nav nav-pills mb-3 gap-2" id="embedTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-2 px-3 fs-12 fw-semibold rounded-2" id="tab-floating-btn" data-bs-toggle="pill" data-bs-target="#pill-floating" type="button" role="tab">
                        <i class="feather-message-square me-1.5"></i>Floating Popup Widget (Recommended)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2 px-3 fs-12 fw-semibold rounded-2" id="tab-iframe-btn" data-bs-toggle="pill" data-bs-target="#pill-iframe" type="button" role="tab">
                        <i class="feather-layout me-1.5"></i>Iframe Embed
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2 px-3 fs-12 fw-semibold rounded-2" id="tab-direct-btn" data-bs-toggle="pill" data-bs-target="#pill-direct" type="button" role="tab">
                        <i class="feather-link me-1.5"></i>Direct Public Link
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2 px-3 fs-12 fw-semibold rounded-2" id="tab-api-btn" data-bs-toggle="pill" data-bs-target="#pill-api" type="button" role="tab">
                        <i class="feather-terminal me-1.5"></i>Direct REST API / Webhook
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="embedTabsContent">
                <!-- 1. Floating Widget -->
                <div class="tab-pane fade show active" id="pill-floating" role="tabpanel">
                    <p class="text-muted fs-13 mb-2">
                        Add this single line of code right before the closing <code>&lt;/body&gt;</code> tag on any website (Next.js, WordPress, Shopify, PHP). It adds a sleek floating button that opens this custom form in a slide-out modal.
                    </p>
                    <div class="position-relative">
                        <pre class="bg-dark text-light p-3 rounded-3 font-monospace fs-12 mb-0" id="codeFloating"><code>&lt;script src="{{ $baseUrl }}/embed/lead-widget.js" data-tenant="{{ $tenantIdentifier }}" data-color="{{ $savedConfig['color'] ?? '#28a745' }}" data-theme="{{ $savedConfig['theme'] ?? 'light' }}" data-position="{{ $savedConfig['position'] ?? 'bottom-right' }}" data-button-text="{{ $savedConfig['btn_text'] ?? 'Submit Enquiry' }}" data-company="{{ $savedConfig['company_id'] ?? '' }}" data-branch="{{ $savedConfig['branch_id'] ?? '' }}"&gt;&lt;/script&gt;</code></pre>
                        <button type="button" class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2" onclick="copyCode('codeFloating', this)">
                            <i class="feather-copy me-1"></i>Copy Code
                        </button>
                    </div>
                </div>

                <!-- 2. Iframe Embed -->
                <div class="tab-pane fade" id="pill-iframe" role="tabpanel">
                    <p class="text-muted fs-13 mb-2">
                        Paste this HTML code anywhere on your Contact Us or Landing page to embed the dynamic form directly into the page.
                    </p>
                    <div class="position-relative">
                        <pre class="bg-dark text-light p-3 rounded-3 font-monospace fs-12 mb-0" id="codeIframe"><code>&lt;iframe src="{{ $baseUrl }}/forms/lead-capture/{{ $tenantIdentifier }}" width="100%" height="585" frameborder="0" style="border:none; border-radius:16px; overflow:hidden;"&gt;&lt;/iframe&gt;</code></pre>
                        <button type="button" class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2" onclick="copyCode('codeIframe', this)">
                            <i class="feather-copy me-1"></i>Copy Code
                        </button>
                    </div>
                </div>

                <!-- 3. Direct Public Link -->
                <div class="tab-pane fade" id="pill-direct" role="tabpanel">
                    <p class="text-muted fs-13 mb-2">
                        Share this direct URL with your clients via WhatsApp, Instagram Bio, Email, or SMS campaigns.
                    </p>
                    <div class="input-group mb-2">
                        <input type="text" id="directFormUrl" class="form-control font-monospace fs-13" value="{{ $baseUrl }}/forms/lead-capture/{{ $tenantIdentifier }}" readonly>
                        <button class="btn btn-primary" type="button" onclick="copyDirectUrl(this)">
                            <i class="feather-copy me-1"></i>Copy Link
                        </button>
                        <a href="{{ $baseUrl }}/forms/lead-capture/{{ $tenantIdentifier }}" target="_blank" class="btn btn-outline-secondary">
                            <i class="feather-external-link me-1"></i>Open
                        </a>
                    </div>
                </div>

                <!-- 4. Direct REST API -->
                <div class="tab-pane fade" id="pill-api" role="tabpanel">
                    <p class="text-muted fs-13 mb-2">
                        Submit leads from custom forms (Elementor, Webflow, React, Vue, Python, Zapier) to this open CORS endpoint:
                    </p>
                    <div class="position-relative">
                        <pre class="bg-dark text-light p-3 rounded-3 font-monospace fs-12 mb-0" id="codeApi"><code>curl -X POST "{{ $baseUrl }}/api/crm/public/lead-capture" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "tenant_id": {{ $tenant->id ?? 1 }},
    "company_id": {{ $savedConfig['company_id'] ?? 1 }},
    "branch_id": {{ $savedConfig['branch_id'] ?? 1 }},
    "contact_person": "John Doe",
    "company_name": "Acme Industries",
    "email": "john@example.com",
    "phone": "+91 98765 43210",
    "city": "Mumbai",
    "requirement": "Interested in ERP SaaS Plan",
    "custom_fields": {
      "budget": "₹1 - ₹5 Lakhs",
      "customer_type": "Architect"
    }
  }'</code></pre>
                        <button type="button" class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2" onclick="copyCode('codeApi', this)">
                            <i class="feather-copy me-1"></i>Copy cURL
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Common UI Component Modal: Add / Edit Custom Field -->
<x-ui.modal id="fieldModal" title="<i class='feather-plus-circle me-1 text-primary'></i><span id='modalActionTitle'>Add Custom Field</span>" :centered="true">
    <input type="hidden" id="modalFieldId">
    <input type="hidden" id="modalFieldIsCore" value="false">

    <!-- Field Type -->
    <div class="mb-3">
        <label class="form-label fw-bold text-dark fs-12 mb-1">Field Type <span class="text-danger">*</span></label>
        <select id="modalFieldType" class="form-select form-select-sm" onchange="toggleFieldOptionsSection()">
            <option value="text">Single Line Text (e.g. Full Name, Designation)</option>
            <option value="select">Dropdown Select (e.g. Budget, Product Interest)</option>
            <option value="radio">Radio Buttons (Single Choice)</option>
            <option value="checkbox">Checkboxes (Multi Choice)</option>
            <option value="number">Number / Currency (e.g. Quantity, Area)</option>
            <option value="date">Date Picker (e.g. Site Visit Date)</option>
            <option value="tel">Phone / Mobile Number</option>
            <option value="email">Email Address</option>
            <option value="textarea">Multi-line Textarea / Message</option>
        </select>
    </div>

    <!-- Field Label -->
    <div class="mb-3">
        <label class="form-label fw-bold text-dark fs-12 mb-1">Field Label <span class="text-danger">*</span></label>
        <input type="text" id="modalFieldLabel" class="form-control form-control-sm" placeholder="e.g. Expected Budget">
    </div>

    <!-- Field Placeholder (Hidden for Radio, Checkbox, Date) -->
    <div class="mb-3" id="modalPlaceholderGroup">
        <label class="form-label fw-bold text-dark fs-12 mb-1" id="modalPlaceholderLabel">Placeholder Text</label>
        <input type="text" id="modalFieldPlaceholder" class="form-control form-control-sm" placeholder="e.g. Select your budget range">
    </div>

    <!-- Options List (For Select, Radio, Checkbox) -->
    <div class="mb-3" id="modalOptionsGroup" style="display: none;">
        <label class="form-label fw-bold text-dark fs-12 mb-1">Choices / Options <span class="text-danger">*</span> <span class="text-muted fw-normal">(Comma-separated)</span></label>
        <textarea id="modalFieldOptions" class="form-control form-control-sm" rows="3" placeholder="Option 1, Option 2, Option 3"></textarea>
        <span class="fs-11 text-muted">Enter options separated by comma (e.g. <code>Architect, Builder, Dealer, Home Owner</code>)</span>
    </div>

    <!-- Field Width & Required -->
    <div class="row g-2">
        <div class="col-6">
            <label class="form-label fw-bold text-dark fs-12 mb-1">Layout Width</label>
            <select id="modalFieldWidth" class="form-select form-select-sm">
                <option value="half">Half Width (50% Column)</option>
                <option value="full">Full Width (100% Row)</option>
            </select>
        </div>
        <div class="col-6 d-flex align-items-end">
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" id="modalFieldRequired">
                <label class="form-check-label fs-12 fw-semibold" for="modalFieldRequired">
                    Mandatory / Required (*)
                </label>
            </div>
        </div>
    </div>

    <x-slot:footer>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="saveModalField()">Save Field</button>
    </x-slot:footer>
</x-ui.modal>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11000">
    <div id="copyToast" class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="feather-check-circle text-success me-2"></i><span id="toastMsg">Changes saved!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
    const baseUrl = window.location.origin;
    const tenantIdentifier = @json($tenantIdentifier);
    let formFields = @json($savedSchema);
    let draggedIndex = null;

    // Render Form Fields List with Checkboxes & Drag & Drop
    function renderFieldsList() {
        const container = document.getElementById('fieldsListContainer');
        container.innerHTML = '';

        formFields.forEach((field, index) => {
            const isCore = !!field.is_core;
            const isActive = field.is_active !== false;
            const typeBadge = getTypeBadge(field.type);
            const widthBadge = field.width === 'full' ? '100% Width' : '50% Width';

            const card = document.createElement('div');
            card.className = `border rounded-2 p-2.5 d-flex align-items-center justify-content-between gap-2 shadow-xs transition ${isActive ? 'bg-white' : 'bg-light opacity-75'}`;
            card.setAttribute('draggable', 'true');
            card.setAttribute('data-index', index);
            card.style.cursor = 'grab';
            card.id = `field_item_${index}`;

            card.innerHTML = `
                <div class="d-flex align-items-center gap-2.5 flex-grow-1 overflow-hidden">
                    <span class="text-muted d-flex align-items-center user-select-none" style="cursor: grab;" title="Drag up/down to reorder">
                        <i class="feather-grid fs-13"></i>
                    </span>
                    
                    <!-- Checkbox to Enable / Disable Field in Form -->
                    <div class="form-check form-switch mb-0" title="Toggle Show/Hide in Form">
                        <input class="form-check-input cursor-pointer" type="checkbox" role="switch" id="toggle_active_${index}" ${isActive ? 'checked' : ''} onchange="toggleFieldActive(${index}, this.checked)">
                    </div>

                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <label for="toggle_active_${index}" class="fw-bold text-dark fs-12 text-truncate mb-0 cursor-pointer ${isActive ? '' : 'text-decoration-line-through text-muted'}" style="max-width: 170px;">
                            ${escapeHtml(field.label)}
                        </label>
                        ${field.required ? '<span class="text-danger fs-12 fw-bold" title="Required">*</span>' : ''}
                        ${typeBadge}
                        <span class="badge bg-light text-muted border fs-10">${widthBadge}</span>
                        ${!isActive ? '<span class="badge bg-secondary text-white fs-10">Hidden</span>' : ''}
                    </div>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-light py-0.5 px-1.5 fs-11" onclick="moveField(${index}, -1)" ${index === 0 ? 'disabled' : ''} title="Move Up">
                        <i class="feather-chevron-up"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-light py-0.5 px-1.5 fs-11" onclick="moveField(${index}, 1)" ${index === formFields.length - 1 ? 'disabled' : ''} title="Move Down">
                        <i class="feather-chevron-down"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-light py-0.5 px-1.5 fs-11 text-primary" onclick="editField(${index})" title="Edit Field">
                        <i class="feather-edit-2"></i>
                    </button>
                    ${!isCore ? `
                        <button type="button" class="btn btn-sm btn-light py-0.5 px-1.5 fs-11 text-danger" onclick="deleteField(${index})" title="Delete Field">
                            <i class="feather-trash-2"></i>
                        </button>
                    ` : ''}
                </div>
            `;

            // HTML5 Drag & Drop Listeners
            card.addEventListener('dragstart', (e) => {
                draggedIndex = index;
                card.style.opacity = '0.4';
                e.dataTransfer.effectAllowed = 'move';
            });

            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                card.style.border = '2px dashed #4f46e5';
                card.style.backgroundColor = '#f5f3ff';
            });

            card.addEventListener('dragleave', () => {
                card.style.border = '';
                card.style.backgroundColor = '';
            });

            card.addEventListener('drop', (e) => {
                e.preventDefault();
                card.style.border = '';
                card.style.backgroundColor = '';
                if (draggedIndex !== null && draggedIndex !== index) {
                    const movedItem = formFields.splice(draggedIndex, 1)[0];
                    formFields.splice(index, 0, movedItem);
                    renderFieldsList();
                    updatePreview();
                    autoSaveFormLayout();
                }
            });

            card.addEventListener('dragend', () => {
                card.style.opacity = '';
                card.style.border = '';
                card.style.backgroundColor = '';
                draggedIndex = null;
            });

            container.appendChild(card);
        });
    }

    function toggleFieldActive(index, isActive) {
        formFields[index].is_active = isActive;
        renderFieldsList();
        updatePreview();
        autoSaveFormLayout();
    }

    function getTypeBadge(type) {
        switch(type) {
            case 'select': return '<span class="badge bg-soft-info text-info fs-10">Dropdown</span>';
            case 'radio': return '<span class="badge bg-soft-warning text-warning fs-10">Radio</span>';
            case 'checkbox': return '<span class="badge bg-soft-success text-success fs-10">Checkboxes</span>';
            case 'date': return '<span class="badge bg-soft-primary text-primary fs-10">Date</span>';
            case 'number': return '<span class="badge bg-soft-dark text-dark fs-10">Number</span>';
            case 'textarea': return '<span class="badge bg-soft-secondary text-secondary fs-10">Textarea</span>';
            case 'tel': return '<span class="badge bg-soft-primary text-primary fs-10">Phone</span>';
            case 'email': return '<span class="badge bg-soft-primary text-primary fs-10">Email</span>';
            default: return '<span class="badge bg-light text-dark fs-10">Text</span>';
        }
    }

    function toggleFieldOptionsSection() {
        const type = document.getElementById('modalFieldType').value;
        const optGroup = document.getElementById('modalOptionsGroup');
        const plGroup = document.getElementById('modalPlaceholderGroup');
        const plLabel = document.getElementById('modalPlaceholderLabel');

        if (type === 'radio' || type === 'checkbox') {
            optGroup.style.display = 'block';
            plGroup.style.display = 'none';
        } else if (type === 'select') {
            optGroup.style.display = 'block';
            plGroup.style.display = 'block';
            plLabel.textContent = 'Default Select Label (e.g. Select an option)';
        } else if (type === 'date') {
            optGroup.style.display = 'none';
            plGroup.style.display = 'none';
        } else {
            optGroup.style.display = 'none';
            plGroup.style.display = 'block';
            plLabel.textContent = 'Placeholder Text';
        }
    }

    function openAddFieldModal() {
        document.getElementById('modalActionTitle').textContent = 'Add Custom Field';
        document.getElementById('modalFieldId').value = '';
        document.getElementById('modalFieldIsCore').value = 'false';
        document.getElementById('modalFieldType').value = 'select';
        document.getElementById('modalFieldType').disabled = false;
        document.getElementById('modalFieldLabel').value = '';
        document.getElementById('modalFieldPlaceholder').value = '';
        document.getElementById('modalFieldOptions').value = 'Option 1, Option 2, Option 3';
        document.getElementById('modalFieldWidth').value = 'half';
        document.getElementById('modalFieldRequired').checked = false;

        toggleFieldOptionsSection();
        const modalEl = document.getElementById('fieldModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function editField(index) {
        const field = formFields[index];
        if (!field) return;

        document.getElementById('modalActionTitle').textContent = 'Edit Field: ' + field.label;
        document.getElementById('modalFieldId').value = index;
        document.getElementById('modalFieldIsCore').value = field.is_core ? 'true' : 'false';
        document.getElementById('modalFieldType').value = field.type;
        document.getElementById('modalFieldType').disabled = !!field.is_core;
        document.getElementById('modalFieldLabel').value = field.label;
        document.getElementById('modalFieldPlaceholder').value = field.placeholder || '';
        
        let opts = '';
        if (Array.isArray(field.options)) {
            opts = field.options.join(', ');
        } else if (typeof field.options === 'string') {
            opts = field.options;
        }
        document.getElementById('modalFieldOptions').value = opts;
        document.getElementById('modalFieldWidth').value = field.width || 'half';
        document.getElementById('modalFieldRequired').checked = !!field.required;

        toggleFieldOptionsSection();
        const modalEl = document.getElementById('fieldModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function saveModalField() {
        const label = document.getElementById('modalFieldLabel').value.trim();
        if (!label) {
            alert('Please provide a field label.');
            return;
        }

        const type = document.getElementById('modalFieldType').value;
        const placeholder = document.getElementById('modalFieldPlaceholder').value.trim();
        const width = document.getElementById('modalFieldWidth').value;
        const required = document.getElementById('modalFieldRequired').checked;
        const isCore = document.getElementById('modalFieldIsCore').value === 'true';
        const modalId = document.getElementById('modalFieldId').value;

        let options = [];
        if (['select', 'radio', 'checkbox'].includes(type)) {
            const rawOpts = document.getElementById('modalFieldOptions').value.trim();
            if (!rawOpts) {
                alert('Please provide choices/options separated by comma.');
                return;
            }
            options = rawOpts.split(',').map(s => s.trim()).filter(Boolean);
        }

        const slugName = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');

        if (modalId !== '') {
            // Update existing
            const idx = parseInt(modalId, 10);
            formFields[idx].label = label;
            formFields[idx].placeholder = placeholder;
            formFields[idx].width = width;
            formFields[idx].required = required;
            if (!isCore) {
                formFields[idx].type = type;
                formFields[idx].options = options;
            }
        } else {
            // Add new custom field
            const newField = {
                id: 'custom_' + Date.now(),
                name: slugName || 'custom_field_' + Date.now(),
                label: label,
                type: type,
                placeholder: placeholder,
                options: options,
                required: required,
                is_core: false,
                is_active: true,
                width: width
            };
            formFields.push(newField);
        }

        const modalEl = document.getElementById('fieldModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        renderFieldsList();
        updatePreview();
        autoSaveFormLayout();
    }

    function deleteField(index) {
        if (confirm(`Are you sure you want to remove "${formFields[index].label}"?`)) {
            formFields.splice(index, 1);
            renderFieldsList();
            updatePreview();
            autoSaveFormLayout();
        }
    }

    function moveField(index, dir) {
        const newIndex = index + dir;
        if (newIndex < 0 || newIndex >= formFields.length) return;
        const temp = formFields[index];
        formFields[index] = formFields[newIndex];
        formFields[newIndex] = temp;
        renderFieldsList();
        updatePreview();
        autoSaveFormLayout();
    }

    function resetDefaultFields() {
        if (confirm('Reset form fields to default? Any unsaved custom fields will be removed.')) {
            formFields = [
                { id: 'contact_person', name: 'contact_person', label: 'Full Name', type: 'text', placeholder: 'e.g. John Doe', required: true, is_core: true, is_active: true, width: 'half' },
                { id: 'company_name', name: 'company_name', label: 'Company / Business', type: 'text', placeholder: 'e.g. Acme Corp', required: false, is_core: true, is_active: true, width: 'half' },
                { id: 'phone', name: 'phone', label: 'Phone / Mobile', type: 'tel', placeholder: '+91 98765 43210', required: true, is_core: true, is_active: true, width: 'half' },
                { id: 'email', name: 'email', label: 'Email Address', type: 'email', placeholder: 'john@example.com', required: true, is_core: true, is_active: true, width: 'half' },
                { id: 'city', name: 'city', label: 'City / Location', type: 'text', placeholder: 'e.g. Mumbai, Delhi, Bangalore', required: false, is_core: true, is_active: true, width: 'full' },
                { id: 'requirement', name: 'requirement', label: 'Requirement Details / Message', type: 'textarea', placeholder: 'Briefly describe what you are looking for...', required: false, is_core: true, is_active: true, width: 'full' }
            ];
            renderFieldsList();
            updatePreview();
            autoSaveFormLayout();
        }
    }

    let autoSaveTimer = null;
    function autoSaveFormLayout() {
        clearTimeout(autoSaveTimer);
        const saveIndicator = document.getElementById('autoSaveIndicator');
        if (saveIndicator) {
            saveIndicator.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-primary"></span><span class="text-muted fs-11">Saving...</span>';
        }

        autoSaveTimer = setTimeout(async () => {
            const config = {
                title: document.getElementById('inputTitle').value.trim(),
                subtitle: document.getElementById('inputSubtitle').value.trim(),
                btn_text: document.getElementById('inputBtnText').value.trim(),
                color: document.getElementById('inputColor').value,
                theme: document.querySelector('input[name="formTheme"]:checked')?.value || 'light',
                position: document.getElementById('inputPosition')?.value || 'bottom-right',
                redirect_url: document.getElementById('inputRedirect').value.trim(),
                company_id: document.getElementById('inputCompany')?.value || null,
                branch_id: document.getElementById('inputBranch')?.value || null
            };

            try {
                const response = await fetch('{{ route('crm.web-forms.save-schema') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        fields: formFields,
                        config: config
                    })
                });

                if (response.ok) {
                    if (saveIndicator) {
                        saveIndicator.innerHTML = '<i class="feather-check-circle text-success me-1"></i><span class="text-success fs-11 fw-semibold">Auto-saved</span>';
                    }
                }
            } catch(e) {
                if (saveIndicator) {
                    saveIndicator.innerHTML = '<span class="text-danger fs-11">Auto-save failed</span>';
                }
            }
        }, 400);
    }

    async function saveFormLayout() {
        const btn = document.getElementById('btnSaveSchema');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

        const config = {
            title: document.getElementById('inputTitle').value.trim(),
            subtitle: document.getElementById('inputSubtitle').value.trim(),
            btn_text: document.getElementById('inputBtnText').value.trim(),
            color: document.getElementById('inputColor').value,
            theme: document.querySelector('input[name="formTheme"]:checked')?.value || 'light',
            position: document.getElementById('inputPosition')?.value || 'bottom-right',
            redirect_url: document.getElementById('inputRedirect').value.trim(),
            company_id: document.getElementById('inputCompany')?.value || null,
            branch_id: document.getElementById('inputBranch')?.value || null
        };

        try {
            const response = await fetch('{{ route('crm.web-forms.save-schema') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    fields: formFields,
                    config: config
                })
            });

            const data = await response.json();
            if (response.ok && data.success) {
                showToast('Form Builder layout saved successfully!');
                updatePreview();
            } else {
                alert(data.message || 'Failed to save form layout.');
            }
        } catch (e) {
            alert('Error saving layout: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    }

    let debounceTimer;
    function updatePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const title = document.getElementById('inputTitle').value.trim();
            const subtitle = document.getElementById('inputSubtitle').value.trim();
            const btnText = document.getElementById('inputBtnText').value.trim();
            const color = document.getElementById('inputColor').value;
            const theme = document.querySelector('input[name="formTheme"]:checked')?.value || 'light';
            const position = document.getElementById('inputPosition')?.value || 'bottom-right';
            const redirect = document.getElementById('inputRedirect').value.trim();
            const companyId = document.getElementById('inputCompany')?.value || '';
            const branchId = document.getElementById('inputBranch')?.value || '';

            const config = {
                title: title,
                subtitle: subtitle,
                btn_text: btnText,
                color: color,
                theme: theme,
                position: position,
                redirect_url: redirect,
                company_id: companyId,
                branch_id: branchId
            };

            // Only pass ACTIVE (enabled) fields to live preview!
            const activeFields = formFields.filter(f => f.is_active !== false);

            // 1. Instantly postMessage to the preview iframe (Live 0ms sync)
            const iframe = document.getElementById('previewIframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    type: 'CRM_UPDATE_PREVIEW',
                    fields: activeFields,
                    config: config
                }, '*');
            }

            // 2. Update Direct URL and Embed Code Snippets
            let extraParams = '';
            if (redirect) extraParams += '&redirect_url=' + encodeURIComponent(redirect);
            if (companyId) extraParams += '&company_id=' + encodeURIComponent(companyId);
            if (branchId) extraParams += '&branch_id=' + encodeURIComponent(branchId);

            const fullUrl = `${baseUrl}/forms/lead-capture/${tenantIdentifier}?title=${encodeURIComponent(title)}&subtitle=${encodeURIComponent(subtitle)}&btn_text=${encodeURIComponent(btnText)}&color=${encodeURIComponent(color)}&theme=${theme}${extraParams}`;
            document.getElementById('directFormUrl').value = fullUrl;

            const iframeCode = `<iframe src="${fullUrl}" width="100%" height="585" frameborder="0" style="border:none; border-radius:16px; overflow:hidden;"></iframe>`;
            document.getElementById('codeIframe').querySelector('code').textContent = iframeCode;

            let dataAttrs = `data-tenant="${tenantIdentifier}" data-color="${color}" data-theme="${theme}" data-position="${position}" data-button-text="${btnText}"`;
            if (companyId) dataAttrs += ` data-company="${companyId}"`;
            if (branchId) dataAttrs += ` data-branch="${branchId}"`;

            const floatingCode = `<script src="${baseUrl}/embed/lead-widget.js" ${dataAttrs}><\/script>`;
            document.getElementById('codeFloating').querySelector('code').textContent = floatingCode;
        }, 100);
    }

    function syncColorHex(val) {
        if (/^#[0-9A-F]{6}$/i.test(val)) {
            document.getElementById('inputColor').value = val;
            updatePreview();
        }
    }

    function setDeviceView(mode) {
        const wrapper = document.getElementById('previewWrapper');
        const btnDesk = document.getElementById('btnViewDesktop');
        const btnMob = document.getElementById('btnViewMobile');

        if (mode === 'mobile') {
            wrapper.style.maxWidth = '375px';
            btnMob.classList.add('active', 'btn-primary');
            btnMob.classList.remove('btn-light');
            btnDesk.classList.remove('active', 'btn-primary');
            btnDesk.classList.add('btn-light');
        } else {
            wrapper.style.maxWidth = '540px';
            btnDesk.classList.add('active', 'btn-primary');
            btnDesk.classList.remove('btn-light');
            btnMob.classList.remove('active', 'btn-primary');
            btnMob.classList.add('btn-light');
        }
    }

    function copyCode(elementId, btn) {
        const text = document.getElementById(elementId).innerText.trim();
        navigator.clipboard.writeText(text).then(() => {
            showToast('Code copied to clipboard!');
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="feather-check me-1"></i>Copied!';
            setTimeout(() => { btn.innerHTML = origHtml; }, 2000);
        });
    }

    function copyDirectUrl(btn) {
        const text = document.getElementById('directFormUrl').value.trim();
        navigator.clipboard.writeText(text).then(() => {
            showToast('Direct Form Link copied to clipboard!');
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="feather-check me-1"></i>Copied!';
            setTimeout(() => { btn.innerHTML = origHtml; }, 2000);
        });
    }

    function showToast(msg) {
        document.getElementById('toastMsg').textContent = msg;
        const toastEl = document.getElementById('copyToast');
        const toast = new bootstrap.Toast(toastEl, { delay: 2500 });
        toast.show();
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Initialize list & preview on load
    document.addEventListener('DOMContentLoaded', function() {
        renderFieldsList();
        updatePreview();
    });
</script>
@endsection
