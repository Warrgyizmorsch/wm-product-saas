{{-- Smart Visual Column Mapping Import Modal for Visitor Passes --}}
<x-ui.modal 
    id="importVisitorModal" 
    :title="'<i class=\'feather-upload-cloud me-2 text-primary\'></i>Smart Visitor Pass Importer'" 
    size="xl" 
    :centered="true" 
    :scrollable="true" 
    :static="true" 
    :showFooter="false">

<style>
    #importVisitorModal .select2-container--default .select2-selection--single {
        height: 34px !important;
        padding: 3px 8px !important;
        font-size: 13px !important;
        border-color: #cbd5e1 !important;
        border-radius: 6px !important;
        display: flex;
        align-items: center;
    }
    #importVisitorModal .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px !important;
        color: #1e293b !important;
        padding-left: 0 !important;
    }
    #importVisitorModal .select2-container--default .select2-selection--single .select2-selection__arrow {
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
</style>

    {{-- Subtitle header description --}}
    <div class="mb-4 pb-2 border-bottom">
        <p class="text-muted fs-12 mb-0">
            Bulk pre-register visitors from <strong>Events, Conferences, Contractor Rosters, Excel or CSV spreadsheets</strong> with automatic column matching & instant data validation.
        </p>
    </div>

    {{-- Wizard Stepper Indicator --}}
    <div class="d-flex align-items-center justify-content-center mb-4">
        <div class="d-flex align-items-center gap-2">
            <div class="step-indicator active d-flex align-items-center justify-content-center rounded-circle fw-bold fs-12" id="visitorStepIndicator1" style="width: 28px; height: 28px; background: var(--bs-primary, #6366f1); color: #fff;">1</div>
            <span class="fs-13 fw-semibold text-dark" id="visitorStepText1">Upload File</span>
        </div>
        <div class="mx-3" style="width: 50px; height: 2px; background: #e2e8f0;" id="visitorStepLine1"></div>
        <div class="d-flex align-items-center gap-2">
            <div class="step-indicator d-flex align-items-center justify-content-center rounded-circle fw-bold fs-12" id="visitorStepIndicator2" style="width: 28px; height: 28px; background: #e2e8f0; color: #64748b;">2</div>
            <span class="fs-13 fw-semibold text-muted" id="visitorStepText2">Match Columns</span>
        </div>
        <div class="mx-3" style="width: 50px; height: 2px; background: #e2e8f0;" id="visitorStepLine2"></div>
        <div class="d-flex align-items-center gap-2">
            <div class="step-indicator d-flex align-items-center justify-content-center rounded-circle fw-bold fs-12" id="visitorStepIndicator3" style="width: 28px; height: 28px; background: #e2e8f0; color: #64748b;">3</div>
            <span class="fs-13 fw-semibold text-muted" id="visitorStepText3">Import & Summary</span>
        </div>
    </div>

    {{-- Dynamic Alert Container --}}
    <div id="smartVisitorImportAlertWrapper" class="d-none mb-3">
        <div class="alert alert-danger py-2 px-3 fs-13 d-flex align-items-center" role="alert">
            <i class="feather-alert-circle me-2 fs-16 text-danger"></i>
            <span id="smartVisitorImportAlertContent"></span>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- STEP 1: FILE UPLOAD & CONFIGURATION                      --}}
    {{-- ======================================================== --}}
    <div id="smartVisitorImportStep1">
        <div class="border rounded-3 p-4 bg-white mb-4 text-center" id="visitorDropZoneContainer" style="border: 2px dashed #cbd5e1 !important; transition: all 0.2s ease;">
            <input type="file" id="smartVisitorImportFileInput" class="d-none" accept=".xlsx,.xls,.csv">
            <div class="py-4 cursor-pointer" onclick="document.getElementById('smartVisitorImportFileInput').click()">
                <i class="feather-user-check text-primary mb-3" style="font-size: 42px;"></i>
                <h6 class="fw-bold text-dark mb-1">Click to browse or drag & drop your Excel / CSV file</h6>
                <p class="text-muted fs-12 mb-3">Supports .xlsx, .xls, .csv files exported from Excel, Google Sheets, Event Portals, etc.</p>
                <button type="button" class="btn btn-sm btn-outline-primary px-3 fw-semibold">
                    <i class="feather-plus me-1"></i> Select File
                </button>
            </div>
            <div id="selectedVisitorFileInfo" class="d-none mt-3 p-2 bg-light rounded text-start d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-check-circle text-success fs-16"></i>
                    <span class="fw-semibold text-dark fs-13" id="selectedVisitorFileName"></span>
                    <span class="text-muted fs-12" id="selectedVisitorFileSize"></span>
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="resetSelectedVisitorFile()">Change File</button>
            </div>
        </div>

        {{-- Quick Import Options --}}
        <div class="border rounded-3 p-3 bg-light mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark fs-13 mb-0">
                    <i class="feather-sliders me-1 text-primary"></i> Default Pass Settings & Host Assignment
                </h6>
                <span class="badge bg-primary-subtle text-primary fs-11 fw-semibold">Applied automatically when not in file</span>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-user me-1 text-primary"></i> Default Host Employee
                    </label>
                    <select id="importVisitorDefaultHost" class="form-select form-select-sm select2-visitor-opt" style="width: 100%;">
                        <option value="">— Direct Reception / None —</option>
                        @if(isset($hosts) && count($hosts) > 0)
                            @foreach($hosts as $h)
                                <option value="{{ $h->id }}">
                                    {{ $h->name }} ({{ $h->email }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-tag me-1 text-success"></i> Default Purpose
                    </label>
                    <select id="importVisitorDefaultPurpose" class="form-select form-select-sm select2-visitor-opt" style="width: 100%;">
                        <option value="Meeting" selected>Meeting / Discussion</option>
                        <option value="Interview">Job Interview</option>
                        <option value="Vendor">Vendor / Supplier Visit</option>
                        <option value="Delivery">Material / Courier Delivery</option>
                        <option value="Audit">Official Audit / Inspection</option>
                        <option value="Personal">Personal Visit</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-map-pin me-1 text-warning"></i> Default Gate / Entry Point
                    </label>
                    <select id="importVisitorDefaultGate" class="form-select form-select-sm select2-visitor-opt" style="width: 100%;">
                        <option value="Main Gate 1" selected>Main Gate 1</option>
                        <option value="Gate 2">Gate 2 (Cargo / Logistics)</option>
                        <option value="Tower Reception">Tower Reception Desk</option>
                        <option value="VIP Gate">VIP / Executive Gate</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-shield me-1 text-secondary"></i> Default Entry Type
                    </label>
                    <select id="importVisitorDefaultEntryType" class="form-select form-select-sm select2-visitor-opt" style="width: 100%;">
                        <option value="Walk-in" selected>Walk-in</option>
                        <option value="Pre-Invite">Pre-Invite / Scheduled</option>
                        <option value="Kiosk">Self-Service Kiosk</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <div class="form-check form-switch pt-2">
                        <input class="form-check-input" type="checkbox" id="importVisitorUpdateExisting" checked>
                        <label class="form-check-label fs-13 fw-semibold text-dark" for="importVisitorUpdateExisting">
                            Update profile details for returning visitors (matched by Phone Number)
                        </label>
                        <div class="text-muted fs-11">Keeps email, company, and designation up to date while logging new visitor passes.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <a href="{{ route('visitor.sample-template') }}" class="btn btn-sm btn-outline-secondary">
                <i class="feather-download me-1"></i> Download Standard Sample CSV
            </a>
            <button type="button" class="btn btn-primary btn-sm fw-bold px-4" id="btnAnalyzeVisitorFile" disabled onclick="analyzeUploadedVisitorFile()">
                <span class="spinner-border spinner-border-sm d-none me-1" id="analyzeVisitorSpinner"></span>
                Analyze & Map Columns <i class="feather-arrow-right ms-1"></i>
            </button>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- STEP 2: VISUAL COLUMN MAPPER TABLE                       --}}
    {{-- ======================================================== --}}
    <div id="smartVisitorImportStep2" class="d-none">
        <div class="d-flex align-items-center justify-content-between bg-primary-subtle text-primary p-3 rounded-3 mb-3 border border-primary-subtle">
            <div class="d-flex align-items-center gap-2">
                <i class="feather-check-circle text-primary fs-18"></i>
                <div>
                    <span class="fw-bold text-dark fs-13" id="step2VisitorFileSummary">File Ready</span>
                    <div class="text-muted fs-12">Columns have been automatically matched. Review and adjust mappings below.</div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-primary text-white fs-12 px-2.5 py-1" id="step2VisitorTotalRowsBadge">
                    0 Data Rows
                </span>
            </div>
        </div>

        {{-- Mapping Table --}}
        <div class="table-responsive border rounded-3" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0 fs-13">
                <thead class="table-light sticky-top" style="z-index: 2;">
                    <tr>
                        <th style="width: 32%;">ERP Visitor Field</th>
                        <th style="width: 35%;">Your File Column</th>
                        <th style="width: 33%;">Sample Preview (Row 1)</th>
                    </tr>
                </thead>
                <tbody id="visitorMappingTableBody">
                    {{-- Populated dynamically via JS --}}
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="goToVisitorStep(1)">
                <i class="feather-arrow-left me-1"></i> Back to Upload
            </button>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btnTestVisitorImport" onclick="executeVisitorImport(true)">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="testVisitorSpinner"></span>
                    <i class="feather-activity me-1"></i> Test Import (Dry Run)
                </button>
                <button type="button" class="btn btn-sm btn-success fw-bold px-4" id="btnProcessVisitorImport" onclick="executeVisitorImport(false)">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="importVisitorSpinner"></span>
                    <i class="feather-check me-1"></i> Confirm & Import All Passes
                </button>
            </div>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- STEP 3: SUMMARY & CONFIRMATION RESULTS                   --}}
    {{-- ======================================================== --}}
    <div id="smartVisitorImportStep3" class="d-none">
        <div class="text-center py-4">
            <div class="avatar-lg bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                <i class="feather-check fs-1"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1" id="importResultsHeading">Import Completed Successfully!</h5>
            <p class="text-muted fs-13 mb-4" id="importResultsSubHeading">Your visitor passes have been created and logged into the gate registry.</p>

            <div class="row justify-content-center mb-4 g-3">
                <div class="col-md-3">
                    <div class="border rounded-3 p-3 bg-light">
                        <div class="fs-11 text-muted text-uppercase fw-bold">New Passes Created</div>
                        <h3 class="fw-bold text-success mb-0 mt-1" id="statCreatedVisitorCount">0</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded-3 p-3 bg-light">
                        <div class="fs-11 text-muted text-uppercase fw-bold">Returning Visitors Updated</div>
                        <h3 class="fw-bold text-primary mb-0 mt-1" id="statUpdatedVisitorCount">0</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded-3 p-3 bg-light">
                        <div class="fs-11 text-muted text-uppercase fw-bold">Rows Skipped / Failed</div>
                        <h3 class="fw-bold text-danger mb-0 mt-1" id="statFailedVisitorCount">0</h3>
                    </div>
                </div>
            </div>

            {{-- Error rows list if any --}}
            <div id="importVisitorErrorsContainer" class="d-none text-start mb-4">
                <div class="border border-danger-subtle bg-danger-subtle p-3 rounded-3">
                    <h6 class="fw-bold text-danger fs-13 mb-2">
                        <i class="feather-alert-triangle me-1"></i> Skipped Rows / Validation Warnings
                    </h6>
                    <ul class="list-unstyled mb-0 fs-12 text-muted" id="importVisitorErrorsList"></ul>
                </div>
            </div>

            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetVisitorImportWizard()">
                    <i class="feather-plus me-1"></i> Import Another File
                </button>
                <button type="button" class="btn btn-sm btn-primary fw-bold px-4" data-bs-dismiss="modal" onclick="window.location.reload()">
                    <i class="feather-check-circle me-1"></i> Done & View Gate Desk
                </button>
            </div>
        </div>
    </div>
</x-ui.modal>

@push('scripts')
<script>
    let currentVisitorParsedData = null;

    document.addEventListener('DOMContentLoaded', function() {
        const dropZone = document.getElementById('visitorDropZoneContainer');
        const fileInput = document.getElementById('smartVisitorImportFileInput');

        if (dropZone && fileInput) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.style.borderColor = 'var(--bs-primary)';
                    dropZone.style.background = '#f8fafc';
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.style.borderColor = '#cbd5e1';
                    dropZone.style.background = '#ffffff';
                }, false);
            });

            dropZone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length) {
                    fileInput.files = files;
                    handleVisitorFileSelected(files[0]);
                }
            }, false);

            fileInput.addEventListener('change', function() {
                if (this.files.length) {
                    handleVisitorFileSelected(this.files[0]);
                }
            });
        }
    });

    function handleVisitorFileSelected(file) {
        document.getElementById('selectedVisitorFileName').textContent = file.name;
        document.getElementById('selectedVisitorFileSize').textContent = `(${(file.size / 1024).toFixed(1)} KB)`;
        document.getElementById('selectedVisitorFileInfo').classList.remove('d-none');
        document.getElementById('btnAnalyzeVisitorFile').disabled = false;
        hideVisitorImportAlert();
    }

    function resetSelectedVisitorFile() {
        const fileInput = document.getElementById('smartVisitorImportFileInput');
        if (fileInput) fileInput.value = '';
        document.getElementById('selectedVisitorFileInfo').classList.add('d-none');
        document.getElementById('btnAnalyzeVisitorFile').disabled = true;
        hideVisitorImportAlert();
    }

    function showVisitorImportAlert(msg) {
        const wrapper = document.getElementById('smartVisitorImportAlertWrapper');
        const content = document.getElementById('smartVisitorImportAlertContent');
        if (wrapper && content) {
            content.textContent = msg;
            wrapper.classList.remove('d-none');
        }
    }

    function hideVisitorImportAlert() {
        const wrapper = document.getElementById('smartVisitorImportAlertWrapper');
        if (wrapper) wrapper.classList.add('d-none');
    }

    function goToVisitorStep(stepNum) {
        hideVisitorImportAlert();
        [1, 2, 3].forEach(n => {
            const stepEl = document.getElementById(`smartVisitorImportStep${n}`);
            const indEl = document.getElementById(`visitorStepIndicator${n}`);
            const textEl = document.getElementById(`visitorStepText${n}`);

            if (stepEl) stepEl.classList.toggle('d-none', n !== stepNum);
            if (indEl) {
                if (n === stepNum) {
                    indEl.style.background = 'var(--bs-primary, #6366f1)';
                    indEl.style.color = '#ffffff';
                } else if (n < stepNum) {
                    indEl.style.background = '#10b981';
                    indEl.style.color = '#ffffff';
                } else {
                    indEl.style.background = '#e2e8f0';
                    indEl.style.color = '#64748b';
                }
            }
            if (textEl) {
                textEl.classList.toggle('text-dark', n <= stepNum);
                textEl.classList.toggle('text-muted', n > stepNum);
            }
        });
    }

    function analyzeUploadedVisitorFile() {
        const fileInput = document.getElementById('smartVisitorImportFileInput');
        if (!fileInput || !fileInput.files.length) {
            showVisitorImportAlert('Please select an Excel or CSV file to analyze.');
            return;
        }

        const spinner = document.getElementById('analyzeVisitorSpinner');
        const btn = document.getElementById('btnAnalyzeVisitorFile');
        if (spinner) spinner.classList.remove('d-none');
        if (btn) btn.disabled = true;
        hideVisitorImportAlert();

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('_token', '{{ csrf_token() }}');

        fetch("{{ route('visitor.import.parse') }}", {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(res => res.json().then(data => ({ status: res.status, ok: res.ok, body: data })))
        .then(({ ok, body }) => {
            if (spinner) spinner.classList.add('d-none');
            if (btn) btn.disabled = false;

            if (!ok || !body.success) {
                showVisitorImportAlert(body.message || 'Failed to parse file. Please verify format.');
                return;
            }

            currentVisitorParsedData = body.data;
            renderVisitorMappingTable(body.data);
            goToVisitorStep(2);
        })
        .catch(err => {
            if (spinner) spinner.classList.add('d-none');
            if (btn) btn.disabled = false;
            showVisitorImportAlert('Network error occurred during analysis: ' + err.message);
        });
    }

    function renderVisitorMappingTable(data) {
        document.getElementById('step2VisitorFileSummary').textContent = `${data.file_name} (${(data.file_size / 1024).toFixed(1)} KB)`;
        document.getElementById('step2VisitorTotalRowsBadge').textContent = `${data.total_rows} Data Rows`;

        const tbody = document.getElementById('visitorMappingTableBody');
        tbody.innerHTML = '';

        const headers = data.file_headers; // { "A": "Visitor Name", "B": "Phone" }
        const autoMap = data.auto_mapping; // { "full_name": "A", "phone": "B" }
        const erpFields = data.erp_fields;
        const sampleRow = (data.preview_rows && data.preview_rows[0]) ? data.preview_rows[0] : {};

        Object.keys(erpFields).forEach(fieldKey => {
            const fDef = erpFields[fieldKey];
            const isReq = fDef.required;
            const currentMappedCol = autoMap[fieldKey] || '';
            const sampleVal = currentMappedCol && sampleRow[currentMappedCol] ? sampleRow[currentMappedCol] : '';

            let selectOptionsHtml = '<option value="">— Do Not Import / Skip —</option>';
            Object.keys(headers).forEach(colLetter => {
                const hName = headers[colLetter];
                const selected = colLetter === currentMappedCol ? 'selected' : '';
                selectOptionsHtml += `<option value="${colLetter}" ${selected}>Column ${colLetter}: ${hName}</option>`;
            });

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="fw-bold ${isReq ? 'text-dark' : 'text-secondary'} fs-13">
                        ${fDef.label}
                    </div>
                    <div class="text-muted fs-11">${fDef.description || ''}</div>
                </td>
                <td>
                    <select class="form-select form-select-sm visitor-map-select" data-field="${fieldKey}" onchange="updateVisitorSamplePreview('${fieldKey}', this.value)">
                        ${selectOptionsHtml}
                    </select>
                </td>
                <td id="visitorPreview_${fieldKey}" class="text-truncate font-monospace fs-12 text-muted" style="max-width: 200px;">
                    ${sampleVal ? `<span class="badge bg-light text-dark border">${escapeHtml(sampleVal)}</span>` : '<span class="text-muted fst-italic">— empty —</span>'}
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function updateVisitorSamplePreview(fieldKey, colLetter) {
        const previewCell = document.getElementById(`visitorPreview_${fieldKey}`);
        if (!previewCell || !currentVisitorParsedData) return;

        const sampleRow = (currentVisitorParsedData.preview_rows && currentVisitorParsedData.preview_rows[0]) ? currentVisitorParsedData.preview_rows[0] : {};
        const val = colLetter && sampleRow[colLetter] ? sampleRow[colLetter] : '';

        if (val) {
            previewCell.innerHTML = `<span class="badge bg-light text-dark border">${escapeHtml(val)}</span>`;
        } else {
            previewCell.innerHTML = '<span class="text-muted fst-italic">— empty —</span>';
        }
    }

    function executeVisitorImport(isDryRun) {
        if (!currentVisitorParsedData) return;

        // Build mapping object
        const mapping = {};
        const selects = document.querySelectorAll('.visitor-map-select');
        selects.forEach(sel => {
            const f = sel.getAttribute('data-field');
            const v = sel.value;
            if (v) mapping[f] = v;
        });

        // Validate required fields
        if (!mapping['full_name']) {
            showVisitorImportAlert('Please map the required field: Visitor Full Name *');
            return;
        }
        if (!mapping['phone']) {
            showVisitorImportAlert('Please map the required field: Phone / Mobile Number *');
            return;
        }

        const options = {
            header_row_index: currentVisitorParsedData.header_row_index,
            default_host_id: document.getElementById('importVisitorDefaultHost').value,
            default_purpose: document.getElementById('importVisitorDefaultPurpose').value,
            default_gate: document.getElementById('importVisitorDefaultGate').value,
            default_entry_type: document.getElementById('importVisitorDefaultEntryType').value,
            update_existing: document.getElementById('importVisitorUpdateExisting').checked,
            is_dry_run: isDryRun ? 1 : 0,
        };

        const spinner = isDryRun ? document.getElementById('testVisitorSpinner') : document.getElementById('importVisitorSpinner');
        const btn = isDryRun ? document.getElementById('btnTestVisitorImport') : document.getElementById('btnProcessVisitorImport');

        if (spinner) spinner.classList.remove('d-none');
        if (btn) btn.disabled = true;
        hideVisitorImportAlert();

        fetch("{{ route('visitor.import.process') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                file_token: currentVisitorParsedData.file_token,
                mapping: mapping,
                options: options,
            })
        })
        .then(res => res.json().then(data => ({ status: res.status, ok: res.ok, body: data })))
        .then(({ ok, body }) => {
            if (spinner) spinner.classList.add('d-none');
            if (btn) btn.disabled = false;

            if (!ok || !body.success) {
                showVisitorImportAlert(body.message || 'Import processing failed.');
                return;
            }

            if (isDryRun) {
                alert(`✅ Test Run Successful!\n\n• Valid rows to create: ${body.imported_count}\n• Warnings/Errors: ${body.errors_count}`);
                return;
            }

            // Step 3: Show Success Results
            document.getElementById('statCreatedVisitorCount').textContent = body.imported_count || 0;
            document.getElementById('statUpdatedVisitorCount').textContent = body.updated_count || 0;
            document.getElementById('statFailedVisitorCount').textContent = body.errors_count || 0;

            const errContainer = document.getElementById('importVisitorErrorsContainer');
            const errList = document.getElementById('importVisitorErrorsList');
            if (body.errors && body.errors.length > 0) {
                errList.innerHTML = body.errors.map(e => `<li>• ${escapeHtml(e)}</li>`).join('');
                errContainer.classList.remove('d-none');
            } else {
                errContainer.classList.add('d-none');
            }

            goToVisitorStep(3);
        })
        .catch(err => {
            if (spinner) spinner.classList.add('d-none');
            if (btn) btn.disabled = false;
            showVisitorImportAlert('Network error during import: ' + err.message);
        });
    }

    function resetVisitorImportWizard() {
        resetSelectedVisitorFile();
        currentVisitorParsedData = null;
        goToVisitorStep(1);
    }

    function escapeHtml(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>
@endpush
