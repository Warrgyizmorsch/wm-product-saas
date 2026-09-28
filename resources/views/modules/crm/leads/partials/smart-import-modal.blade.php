{{-- Smart Visual Column Mapping Import Modal for CRM Leads --}}
<x-ui.modal 
    id="importLeadsModal" 
    :title="'<i class=\'feather-upload-cloud me-2 text-primary\'></i>Smart Lead Importer'" 
    size="xl" 
    :centered="true" 
    :scrollable="true" 
    :static="true" 
    :showFooter="false">

<style>
    #importLeadsModal .select2-container--default .select2-selection--single {
        height: 34px !important;
        padding: 3px 8px !important;
        font-size: 13px !important;
        border-color: #cbd5e1 !important;
        border-radius: 6px !important;
        display: flex;
        align-items: center;
    }
    #importLeadsModal .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px !important;
        color: #1e293b !important;
        padding-left: 0 !important;
    }
    #importLeadsModal .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 32px !important;
        right: 6px !important;
    }
    .select2-dropdown {
        border-color: #cbd5e1 !important;
        border-radius: 6px !important;
        font-size: 13px !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        z-index: 1065 !important;
    }
    .select2-search--dropdown .select2-search__field {
        border: 1px solid #cbd5e1 !important;
        border-radius: 4px !important;
        padding: 5px 8px !important;
        font-size: 12px !important;
    }
</style>

    {{-- Subtitle header description --}}
    <div class="mb-4 pb-2 border-bottom">
        <p class="text-muted fs-12 mb-0">
            Import leads from <strong>IndiaMart, TradeIndia, Meta/Google Ads, Excel, CSV or any legacy CRM</strong> with automatic column matching & instant data validation.
        </p>
    </div>

    {{-- Wizard Stepper Indicator --}}
    <div class="d-flex align-items-center justify-content-center mb-4">
        <div class="d-flex align-items-center gap-2">
            <div class="step-indicator active d-flex align-items-center justify-content-center rounded-circle fw-bold fs-12" id="leadStepIndicator1" style="width: 28px; height: 28px; background: var(--bs-primary, #6366f1); color: #fff;">1</div>
            <span class="fs-13 fw-semibold text-dark" id="leadStepText1">Upload File</span>
        </div>
        <div class="mx-3" style="width: 50px; height: 2px; background: #e2e8f0;" id="leadStepLine1"></div>
        <div class="d-flex align-items-center gap-2">
            <div class="step-indicator d-flex align-items-center justify-content-center rounded-circle fw-bold fs-12" id="leadStepIndicator2" style="width: 28px; height: 28px; background: #e2e8f0; color: #64748b;">2</div>
            <span class="fs-13 fw-semibold text-muted" id="leadStepText2">Match Columns</span>
        </div>
        <div class="mx-3" style="width: 50px; height: 2px; background: #e2e8f0;" id="leadStepLine2"></div>
        <div class="d-flex align-items-center gap-2">
            <div class="step-indicator d-flex align-items-center justify-content-center rounded-circle fw-bold fs-12" id="leadStepIndicator3" style="width: 28px; height: 28px; background: #e2e8f0; color: #64748b;">3</div>
            <span class="fs-13 fw-semibold text-muted" id="leadStepText3">Import & Verify</span>
        </div>
    </div>

    {{-- Dynamic Alert Component Container --}}
    <div id="smartLeadImportAlertWrapper" class="d-none mb-3">
        <x-ui.alert id="smartLeadImportAlert" variant="danger" icon="feather-alert-circle" class="py-2 px-3 fs-13">
            <span id="smartLeadImportAlertContent"></span>
        </x-ui.alert>
    </div>

    {{-- ======================================================== --}}
    {{-- STEP 1: FILE UPLOAD & CONFIGURATION                      --}}
    {{-- ======================================================== --}}
    <div id="smartLeadImportStep1">
        <div class="border rounded-3 p-4 bg-white mb-4 text-center" id="leadDropZoneContainer" style="border: 2px dashed #cbd5e1 !important; transition: all 0.2s ease;">
            <input type="file" id="smartLeadImportFileInput" class="d-none" accept=".xlsx,.xls,.csv">
            <div class="py-4 cursor-pointer" onclick="document.getElementById('smartLeadImportFileInput').click()">
                <i class="feather-users text-primary mb-3" style="font-size: 42px;"></i>
                <h6 class="fw-bold text-dark mb-1">Click to browse or drag & drop your Excel / CSV file</h6>
                <p class="text-muted fs-12 mb-3">Supports .xlsx, .xls, .csv exported from IndiaMart, TradeIndia, Facebook Leads, Zoho, HubSpot, etc.</p>
                <x-ui.button type="button" variant="soft-primary" size="sm" icon="feather-plus">
                    Select File
                </x-ui.button>
            </div>
            <div id="selectedLeadFileInfo" class="d-none mt-3 p-2 bg-light rounded text-start d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-check-circle text-success fs-16"></i>
                    <span class="fw-semibold text-dark fs-13" id="selectedLeadFileName"></span>
                    <span class="text-muted fs-12" id="selectedLeadFileSize"></span>
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="resetSelectedLeadFile()">Change File</button>
            </div>
        </div>

        {{-- Quick Import Options --}}
        <div class="border rounded-3 p-3 bg-light mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark fs-13 mb-0">
                    <i class="feather-sliders me-1 text-primary"></i> Default Lead Settings & Assignee
                </h6>
                <span class="badge bg-soft-info text-info fs-11">Applied automatically when not present in file</span>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-flag me-1 text-primary"></i> Default Status
                    </label>
                    <select id="importLeadDefaultStatus" class="form-select form-select-sm select2-lead-opt" style="width: 100%;">
                        @if(isset($leadStatuses) && count($leadStatuses) > 0)
                            @foreach($leadStatuses as $statusObj)
                                <option value="{{ $statusObj->name }}" {{ strtolower($statusObj->name) === 'new' ? 'selected' : '' }}>
                                    {{ $statusObj->name }}
                                </option>
                            @endforeach
                        @else
                            <option value="New" selected>New</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Qualified">Qualified</option>
                            <option value="In Progress">In Progress</option>
                        @endif
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-globe me-1 text-success"></i> Default Source
                    </label>
                    <select id="importLeadDefaultSource" class="form-select form-select-sm select2-lead-opt" style="width: 100%;">
                        <option value="Direct" selected>Direct</option>
                        <option value="Website Form">Website Form</option>
                        <option value="WhatsApp Bot">WhatsApp Bot</option>
                        <option value="IndiaMart">IndiaMart</option>
                        <option value="TradeIndia">TradeIndia</option>
                        <option value="Meta Ads">Meta / Facebook Ads</option>
                        <option value="Google Ads">Google Ads</option>
                        <option value="LinkedIn">LinkedIn</option>
                        <option value="Referral">Referral</option>
                        <option value="Cold Call">Cold Call</option>
                        <option value="Walk-in">Walk-in</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-user-check me-1 text-warning"></i> Default Salesperson (Owner)
                    </label>
                    <select id="importLeadDefaultOwner" class="form-select form-select-sm select2-lead-opt" style="width: 100%;">
                        <option value="">— Unassigned —</option>
                        @if(isset($users) && count($users) > 0)
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ auth()->id() == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }} ({{ $u->email }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-briefcase me-1 text-secondary"></i> Default Lead Type
                    </label>
                    <select id="importLeadDefaultType" class="form-select form-select-sm select2-lead-opt" style="width: 100%;">
                        <option value="b2b" selected>B2B (Business / Corporate)</option>
                        <option value="b2c">B2C (Individual / Retail)</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <div class="form-check form-switch pt-2">
                        <input class="form-check-input" type="checkbox" id="importLeadUpdateExisting" checked>
                        <label class="form-check-label fs-13 fw-semibold text-dark" for="importLeadUpdateExisting">
                            Update existing leads if matching Phone or Email is found
                        </label>
                        <div class="text-muted fs-11">Prevents duplicate lead records while keeping existing data up-to-date</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <x-ui.button href="{{ route('crm.leads.downloadSample') }}" variant="outline-secondary" size="sm" icon="feather-download">
                Download Standard Template
            </x-ui.button>
            <x-ui.button type="button" variant="primary" id="btnAnalyzeLeadFile" disabled onclick="analyzeUploadedLeadFile()">
                <span class="spinner-border spinner-border-sm d-none me-1" id="analyzeLeadSpinner"></span>
                Analyze & Map Columns <i class="feather-arrow-right ms-1"></i>
            </x-ui.button>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- STEP 2: VISUAL COLUMN MAPPER TABLE (LEFT vs RIGHT)       --}}
    {{-- ======================================================== --}}
    <div id="smartLeadImportStep2" class="d-none">
        <div class="d-flex align-items-center justify-content-between bg-soft-primary p-3 rounded-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <i class="feather-check-circle text-primary fs-18"></i>
                <div>
                    <span class="fw-bold text-dark fs-13" id="step2LeadFileSummary">File Ready</span>
                    <div class="text-muted fs-12">Columns have been automatically matched. Review and adjust any mappings below.</div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <x-ui.badge variant="secondary" :soft="true" id="step2LeadTotalRowsBadge" class="fs-12 px-2 py-1">
                    0 Data Rows
                </x-ui.badge>
            </div>
        </div>

        {{-- Mapping Table --}}
        <div class="table-responsive border rounded-3" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0 fs-13">
                <thead class="table-light sticky-top" style="z-index: 2;">
                    <tr>
                        <th style="width: 32%;">CRM Lead Field</th>
                        <th style="width: 35%;">Your File Column</th>
                        <th style="width: 33%;">Sample Preview (From File)</th>
                    </tr>
                </thead>
                <tbody id="leadMappingTableBody">
                    {{-- Populated dynamically via JS --}}
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
            <x-ui.button type="button" variant="outline-secondary" icon="feather-arrow-left" onclick="goToLeadStep(1)">
                Back to Upload
            </x-ui.button>
            <div class="d-flex gap-2">
                <x-ui.button type="button" variant="outline-primary" id="btnTestLeadImport" icon="feather-activity" onclick="executeLeadImport(true)">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="testLeadSpinner"></span>
                    Test Import (Dry Run)
                </x-ui.button>
                <x-ui.button type="button" variant="success" id="btnProcessLeadImport" icon="feather-check" onclick="executeLeadImport(false)">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="importLeadSpinner"></span>
                    Confirm & Import All Leads
                </x-ui.button>
            </div>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- STEP 3: RESULT & REPORT SUMMARY                          --}}
    {{-- ======================================================== --}}
    <div id="smartLeadImportStep3" class="d-none">
        <div class="text-center py-4">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-soft-success text-success mb-3" style="width: 64px; height: 64px;">
                <i class="feather-check-circle" style="font-size: 32px;"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1" id="leadResultTitle">Import Completed Successfully!</h4>
            <p class="text-muted fs-13 mb-4" id="leadResultSubtitle">Your leads have been imported and are now available in your CRM pipeline.</p>

            {{-- Stats Cards --}}
            <div class="row g-3 justify-content-center mb-4 text-start">
                <div class="col-md-3">
                    <div class="card border-0 bg-soft-success p-3 rounded-3 text-center">
                        <span class="fs-12 text-muted fw-semibold text-uppercase">New Leads Created</span>
                        <h3 class="fw-bold text-success mb-0 mt-1" id="statLeadCreatedCount">0</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-soft-primary p-3 rounded-3 text-center">
                        <span class="fs-12 text-muted fw-semibold text-uppercase">Existing Updated</span>
                        <h3 class="fw-bold text-primary mb-0 mt-1" id="statLeadUpdatedCount">0</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-soft-secondary p-3 rounded-3 text-center">
                        <span class="fs-12 text-muted fw-semibold text-uppercase">Skipped / Ignored</span>
                        <h3 class="fw-bold text-secondary mb-0 mt-1" id="statLeadSkippedCount">0</h3>
                    </div>
                </div>
            </div>

            {{-- Errors / Warnings Collapsible --}}
            <div id="importLeadErrorsContainer" class="d-none text-start mb-4">
                <h6 class="fw-bold text-danger fs-13 mb-2">
                    <i class="feather-alert-triangle me-1"></i>Issues / Skipped Rows:
                </h6>
                <div class="border rounded p-2 bg-light" style="max-height: 160px; overflow-y: auto;">
                    <ul class="list-unstyled mb-0 fs-12 text-muted" id="importLeadErrorsList"></ul>
                </div>
            </div>

            <div class="d-flex justify-content-center gap-2">
                <x-ui.button type="button" variant="outline-secondary" icon="feather-rotate-ccw" onclick="resetSmartLeadImport()">
                    Import Another File
                </x-ui.button>
                <x-ui.button type="button" variant="primary" icon="feather-arrow-right" onclick="window.location.reload()">
                    Done & View Leads
                </x-ui.button>
            </div>
        </div>
    </div>

</x-ui.modal>

@push('scripts')
<script>
    let smartLeadImportState = {
        file: null,
        fileToken: null,
        headers: [],
        sampleRows: [],
        fieldsSchema: {},
        suggestedMapping: {},
        totalRows: 0
    };

    // File selection listener
    document.getElementById('smartLeadImportFileInput').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            handleLeadFileSelect(e.target.files[0]);
        }
    });

    // Drag & Drop handlers
    const leadDropZone = document.getElementById('leadDropZoneContainer');
    if (leadDropZone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            leadDropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                leadDropZone.style.borderColor = '#6366f1';
                leadDropZone.style.backgroundColor = '#f8fafc';
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            leadDropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                leadDropZone.style.borderColor = '#cbd5e1';
                leadDropZone.style.backgroundColor = '#ffffff';
            }, false);
        });
        leadDropZone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                handleLeadFileSelect(e.dataTransfer.files[0]);
            }
        });
    }

    function handleLeadFileSelect(file) {
        smartLeadImportState.file = file;
        document.getElementById('selectedLeadFileName').textContent = file.name;
        document.getElementById('selectedLeadFileSize').textContent = '(' + (file.size / 1024).toFixed(1) + ' KB)';
        document.getElementById('selectedLeadFileInfo').classList.remove('d-none');
        document.getElementById('btnAnalyzeLeadFile').removeAttribute('disabled');
        hideSmartLeadAlert();
    }

    function resetSelectedLeadFile() {
        smartLeadImportState.file = null;
        document.getElementById('smartLeadImportFileInput').value = '';
        document.getElementById('selectedLeadFileInfo').classList.add('d-none');
        document.getElementById('btnAnalyzeLeadFile').setAttribute('disabled', 'disabled');
    }

    function showSmartLeadAlert(msg, type = 'danger') {
        const wrapper = document.getElementById('smartLeadImportAlertWrapper');
        const contentEl = document.getElementById('smartLeadImportAlertContent');
        if (contentEl && wrapper) {
            contentEl.innerHTML = msg;
            wrapper.classList.remove('d-none');
        }
    }

    function hideSmartLeadAlert() {
        const wrapper = document.getElementById('smartLeadImportAlertWrapper');
        if (wrapper) wrapper.classList.add('d-none');
    }

    function goToLeadStep(step) {
        document.getElementById('smartLeadImportStep1').classList.toggle('d-none', step !== 1);
        document.getElementById('smartLeadImportStep2').classList.toggle('d-none', step !== 2);
        document.getElementById('smartLeadImportStep3').classList.toggle('d-none', step !== 3);

        // Update step indicators
        for (let i = 1; i <= 3; i++) {
            const ind = document.getElementById('leadStepIndicator' + i);
            const txt = document.getElementById('leadStepText' + i);
            if (i < step) {
                ind.style.background = '#10b981';
                ind.style.color = '#fff';
                ind.innerHTML = '<i class="feather-check"></i>';
                txt.className = 'fs-13 fw-semibold text-dark';
            } else if (i === step) {
                ind.style.background = '#6366f1';
                ind.style.color = '#fff';
                ind.innerHTML = i;
                txt.className = 'fs-13 fw-semibold text-dark';
            } else {
                ind.style.background = '#e2e8f0';
                ind.style.color = '#64748b';
                ind.innerHTML = i;
                txt.className = 'fs-13 fw-semibold text-muted';
            }
        }
    }

    // Step 1 -> Step 2: Upload file & Auto-detect columns
    function analyzeUploadedLeadFile() {
        if (!smartLeadImportState.file) return;

        const formData = new FormData();
        formData.append('file', smartLeadImportState.file);
        formData.append('_token', '{{ csrf_token() }}');

        const btn = document.getElementById('btnAnalyzeLeadFile');
        const spinner = document.getElementById('analyzeLeadSpinner');
        btn.setAttribute('disabled', 'disabled');
        spinner.classList.remove('d-none');
        hideSmartLeadAlert();

        fetch("{{ route('crm.leads.import.parse') }}", {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(res => {
            btn.removeAttribute('disabled');
            spinner.classList.add('d-none');

            if (!res.success) {
                showSmartLeadAlert(res.message || 'Failed to parse file.', 'danger');
                return;
            }

            const data = res.data;
            smartLeadImportState.fileToken = data.file_token;
            smartLeadImportState.headers = data.headers || [];
            smartLeadImportState.sampleRows = data.sample_rows || [];
            smartLeadImportState.fieldsSchema = data.fields_schema || {};
            smartLeadImportState.suggestedMapping = data.suggested_mapping || {};
            smartLeadImportState.totalRows = data.total_rows || 0;

            buildLeadMappingTable();

            document.getElementById('step2LeadFileSummary').textContent = data.original_name;
            document.getElementById('step2LeadTotalRowsBadge').textContent = data.total_rows + ' Data Rows';

            goToLeadStep(2);
        })
        .catch(err => {
            btn.removeAttribute('disabled');
            spinner.classList.add('d-none');
            showSmartLeadAlert('Network error while analyzing file: ' + err.message, 'danger');
        });
    }

    function buildLeadMappingTable() {
        const tbody = document.getElementById('leadMappingTableBody');
        
        // Destroy existing select2 instances if any before rebuilding
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(tbody).find('.select2-lead-mapping').each(function() {
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }
            });
        }

        tbody.innerHTML = '';

        const headers = smartLeadImportState.headers;
        const schema = smartLeadImportState.fieldsSchema;
        const suggested = smartLeadImportState.suggestedMapping;

        Object.keys(schema).forEach(fieldKey => {
            const meta = schema[fieldKey];
            const matchedHeader = suggested[fieldKey] || '';

            const tr = document.createElement('tr');

            // 1. CRM Lead Field Column
            let reqBadge = meta.required ? '<span class="badge bg-soft-danger text-danger ms-1 fs-10">Required</span>' : '<span class="badge bg-soft-secondary text-secondary ms-1 fs-10">Optional</span>';
            let fieldHtml = `
                <div>
                    <span class="fw-bold text-dark">${meta.label}</span> ${reqBadge}
                    <div class="text-muted fs-11">${meta.description || ''}</div>
                </div>
            `;

            // 2. Dropdown column
            let optionsHtml = `<option value="">— Do Not Import (Skip) —</option>`;
            headers.forEach(h => {
                let isSelected = (h === matchedHeader) ? 'selected' : '';
                optionsHtml += `<option value="${escapeLeadHtml(h)}" ${isSelected}>${escapeLeadHtml(h)}</option>`;
            });

            let selectHtml = `
                <select class="form-select form-select-sm lead-mapping-select select2-lead-mapping" data-field="${fieldKey}" style="width: 100%;">
                    ${optionsHtml}
                </select>
            `;

            // 3. Sample Preview Column
            let previewHtml = `<div class="sample-preview-box text-muted fs-12 font-monospace" id="preview_lead_${fieldKey}">—</div>`;

            tr.innerHTML = `
                <td>${fieldHtml}</td>
                <td>${selectHtml}</td>
                <td>${previewHtml}</td>
            `;

            tbody.appendChild(tr);

            // Trigger initial sample preview
            updateLeadSamplePreview(fieldKey);
        });

        // Initialize Select2 on all mapping dropdowns
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('.select2-lead-mapping').each(function() {
                const fieldKey = $(this).data('field');
                $(this).select2({
                    dropdownParent: $('#importLeadsModal'),
                    width: '100%'
                }).on('change', function() {
                    updateLeadSamplePreview(fieldKey);
                });
            });
        }
    }

    function updateLeadSamplePreview(fieldKey) {
        const select = document.querySelector(`.lead-mapping-select[data-field="${fieldKey}"]`);
        const previewBox = document.getElementById('preview_lead_' + fieldKey);
        if (!select || !previewBox) return;

        const selectedHeader = select.value;
        if (!selectedHeader) {
            previewBox.innerHTML = '<span class="text-muted fst-italic fs-11">— Skipped —</span>';
            return;
        }

        const sampleRows = smartLeadImportState.sampleRows;
        const samples = [];
        sampleRows.slice(0, 3).forEach(row => {
            if (row[selectedHeader] !== undefined && row[selectedHeader] !== '') {
                samples.push(row[selectedHeader]);
            }
        });

        if (samples.length > 0) {
            previewBox.innerHTML = samples.map(s => `<span class="badge bg-light text-dark border me-1">${escapeLeadHtml(s)}</span>`).join('');
        } else {
            previewBox.innerHTML = '<span class="text-muted fs-11">No preview value</span>';
        }
    }

    function escapeLeadHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Step 2 -> Step 3: Execute Import (Dry Run or Real)
    function executeLeadImport(isDryRun) {
        const mapping = {};
        let keyFieldMapped = false;

        document.querySelectorAll('.lead-mapping-select').forEach(sel => {
            const field = sel.getAttribute('data-field');
            const val = sel.value;
            if (val) {
                mapping[field] = val;
                if (['contact_person', 'company_name', 'phone', 'email'].includes(field)) {
                    keyFieldMapped = true;
                }
            }
        });

        if (!keyFieldMapped) {
            showSmartLeadAlert('<strong>Validation Error:</strong> You must map at least one primary identifier: <strong>Contact Person, Company Name, Phone, or Email</strong>.', 'danger');
            return;
        }

        const defaultStatus = document.getElementById('importLeadDefaultStatus') ? document.getElementById('importLeadDefaultStatus').value : 'New';
        const defaultSource = document.getElementById('importLeadDefaultSource') ? document.getElementById('importLeadDefaultSource').value : 'Direct';
        const defaultOwner = document.getElementById('importLeadDefaultOwner') ? document.getElementById('importLeadDefaultOwner').value : null;
        const defaultType = document.getElementById('importLeadDefaultType') ? document.getElementById('importLeadDefaultType').value : 'b2b';

        const options = {
            default_status: defaultStatus,
            default_source: defaultSource,
            default_owner_id: defaultOwner,
            default_type: defaultType,
            update_existing: document.getElementById('importLeadUpdateExisting').checked,
            dry_run: isDryRun
        };

        const payload = {
            file_token: smartLeadImportState.fileToken,
            mapping: mapping,
            options: options,
            _token: '{{ csrf_token() }}'
        };

        const btn = isDryRun ? document.getElementById('btnTestLeadImport') : document.getElementById('btnProcessLeadImport');
        const spinner = isDryRun ? document.getElementById('testLeadSpinner') : document.getElementById('importLeadSpinner');
        btn.setAttribute('disabled', 'disabled');
        spinner.classList.remove('d-none');
        hideSmartLeadAlert();

        fetch("{{ route('crm.leads.import.process') }}", {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(res => {
            btn.removeAttribute('disabled');
            spinner.classList.add('d-none');

            if (!res.success) {
                showSmartLeadAlert(res.message || 'Import processing failed.', 'danger');
                return;
            }

            if (isDryRun) {
                showSmartLeadAlert(`
                    <strong>Dry Run Validation Passed!</strong><br>
                    • New leads to be created: <strong>${res.imported_count}</strong><br>
                    • Existing leads to be updated: <strong>${res.updated_count}</strong><br>
                    • Skipped / Ignored: <strong>${res.skipped_count}</strong>
                `, 'success');
            } else {
                // Real Import Finished -> Show Step 3
                document.getElementById('statLeadCreatedCount').textContent = res.imported_count;
                document.getElementById('statLeadUpdatedCount').textContent = res.updated_count;
                document.getElementById('statLeadSkippedCount').textContent = res.skipped_count;

                if (res.errors && res.errors.length > 0) {
                    const list = document.getElementById('importLeadErrorsList');
                    list.innerHTML = res.errors.map(err => `<li>• ${escapeLeadHtml(err)}</li>`).join('');
                    document.getElementById('importLeadErrorsContainer').classList.remove('d-none');
                } else {
                    document.getElementById('importLeadErrorsContainer').classList.add('d-none');
                }

                goToLeadStep(3);
            }
        })
        .catch(err => {
            btn.removeAttribute('disabled');
            spinner.classList.add('d-none');
            showSmartLeadAlert('Network error during import: ' + err.message, 'danger');
        });
    }

    function resetSmartLeadImport() {
        resetSelectedLeadFile();
        smartLeadImportState = {
            file: null,
            fileToken: null,
            headers: [],
            sampleRows: [],
            fieldsSchema: {},
            suggestedMapping: {},
            totalRows: 0
        };
        hideSmartLeadAlert();
        goToLeadStep(1);
    }

    // Initialize Select2 on modal shown
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof $ !== 'undefined') {
            $('#importLeadsModal').on('shown.bs.modal', function() {
                $('.select2-lead-opt').each(function() {
                    if (!$(this).data('select2')) {
                        $(this).select2({
                            dropdownParent: $('#importLeadsModal'),
                            width: '100%'
                        });
                    }
                });
            });
        }
    });
</script>
@endpush
