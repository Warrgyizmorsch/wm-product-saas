{{-- Smart Visual Column Mapping Import Modal (Using Common UI Components) --}}
<x-ui.modal 
    id="importProductsModal" 
    :title="'<i class=\'feather-upload-cloud me-2 text-primary\'></i>Smart Product Importer'"
    size="xl" 
    :centered="true" 
    :scrollable="true" 
    :static="true" 
    :showFooter="false">

<style>
    #importProductsModal .select2-container--default .select2-selection--single {
        height: 34px !important;
        padding: 3px 8px !important;
        font-size: 13px !important;
        border-color: #cbd5e1 !important;
        border-radius: 6px !important;
        display: flex;
        align-items: center;
    }
    #importProductsModal .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px !important;
        color: #1e293b !important;
        padding-left: 0 !important;
    }
    #importProductsModal .select2-container--default .select2-selection--single .select2-selection__arrow {
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
            Import products from <strong>Mewar ERP, Tally, Marg, Busy, Excel or any legacy software</strong> with automatic column matching & instant data validation.
        </p>
    </div>

    {{-- Wizard Stepper Indicator --}}
    <div class="d-flex align-items-center justify-content-center mb-4">
        <div class="d-flex align-items-center gap-2">
            <div class="step-indicator active d-flex align-items-center justify-content-center rounded-circle fw-bold fs-12" id="stepIndicator1" style="width: 28px; height: 28px; background: var(--bs-primary, #6366f1); color: #fff;">1</div>
            <span class="fs-13 fw-semibold text-dark" id="stepText1">Upload File</span>
        </div>
        <div class="mx-3" style="width: 50px; height: 2px; background: #e2e8f0;" id="stepLine1"></div>
        <div class="d-flex align-items-center gap-2">
            <div class="step-indicator d-flex align-items-center justify-content-center rounded-circle fw-bold fs-12" id="stepIndicator2" style="width: 28px; height: 28px; background: #e2e8f0; color: #64748b;">2</div>
            <span class="fs-13 fw-semibold text-muted" id="stepText2">Match Columns</span>
        </div>
        <div class="mx-3" style="width: 50px; height: 2px; background: #e2e8f0;" id="stepLine2"></div>
        <div class="d-flex align-items-center gap-2">
            <div class="step-indicator d-flex align-items-center justify-content-center rounded-circle fw-bold fs-12" id="stepIndicator3" style="width: 28px; height: 28px; background: #e2e8f0; color: #64748b;">3</div>
            <span class="fs-13 fw-semibold text-muted" id="stepText3">Import & Verify</span>
        </div>
    </div>

    {{-- Dynamic Alert Component Container --}}
    <div id="smartImportAlertWrapper" class="d-none mb-3">
        <x-ui.alert id="smartImportAlert" variant="danger" icon="feather-alert-circle" class="py-2 px-3 fs-13">
            <span id="smartImportAlertContent"></span>
        </x-ui.alert>
    </div>

    {{-- ======================================================== --}}
    {{-- STEP 1: FILE UPLOAD & CONFIGURATION                      --}}
    {{-- ======================================================== --}}
    <div id="smartImportStep1">
        <div class="border rounded-3 p-4 bg-white mb-4 text-center" id="dropZoneContainer" style="border: 2px dashed #cbd5e1 !important; transition: all 0.2s ease;">
            <input type="file" id="smartImportFileInput" class="d-none" accept=".xlsx,.xls,.csv">
            <div class="py-4 cursor-pointer" onclick="document.getElementById('smartImportFileInput').click()">
                <i class="feather-file-text text-primary mb-3" style="font-size: 42px;"></i>
                <h6 class="fw-bold text-dark mb-1">Click to browse or drag & drop your Excel / CSV file</h6>
                <p class="text-muted fs-12 mb-3">Supports .xlsx, .xls, .csv exported from Mewar ERP, Tally, Marg, Busy, SAP, etc.</p>
                <x-ui.button type="button" variant="soft-primary" size="sm" icon="feather-plus">
                    Select File
                </x-ui.button>
            </div>
            <div id="selectedFileInfo" class="d-none mt-3 p-2 bg-light rounded text-start d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-check-circle text-success fs-16"></i>
                    <span class="fw-semibold text-dark fs-13" id="selectedFileName"></span>
                    <span class="text-muted fs-12" id="selectedFileSize"></span>
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="resetSelectedFile()">Change File</button>
            </div>
        </div>

        {{-- Quick Import Options --}}
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <x-ui.odoo-form-ui 
                    type="select" 
                    name="default_material_type" 
                    id="importOptionDefaultType" 
                    label="Default Material Type" 
                    class="form-select-sm">
                    <option value="finished_good" selected>Finished Good (FG)</option>
                    <option value="raw_material">Raw Material (RM)</option>
                    <option value="semi_finished">Semi-Finished (SFG)</option>
                    <option value="component">Component / Sub-assembly</option>
                    <option value="service">Service Item</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch pt-4">
                    <input class="form-check-input" type="checkbox" id="importOptionAutoUom" checked>
                    <label class="form-check-label fs-13 fw-semibold text-dark" for="importOptionAutoUom">
                        Auto-create missing Units (UOM)
                    </label>
                    <div class="text-muted fs-11">Creates UOM if not in database (e.g. NOS, KGS, MTR)</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch pt-4">
                    <input class="form-check-input" type="checkbox" id="importOptionUpdateExisting" checked>
                    <label class="form-check-label fs-13 fw-semibold text-dark" for="importOptionUpdateExisting">
                        Update existing items (if SKU exists)
                    </label>
                    <div class="text-muted fs-11">Updates price/stock if product code already present</div>
                </div>
            </div>
        </div>

        {{-- Default Chart of Accounts (COA) --}}
        <div class="border rounded-3 p-3 bg-light mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="fw-bold text-dark fs-13 mb-0">
                    <i class="feather-book-open me-1 text-primary"></i> Default Chart of Accounts (COA)
                </h6>
                <span class="badge bg-soft-info text-info fs-11">Auto-applied if not mapped or missing in file</span>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-box me-1 text-success"></i> Inventory Asset Account
                    </label>
                    <select id="importDefaultInventoryAccount" class="form-select form-select-sm select2-import-account" style="width: 100%;">
                        @if(isset($inventoryAccounts) && count($inventoryAccounts) > 0)
                            @foreach($inventoryAccounts as $acc)
                                @php
                                    $isDefault = ($acc->code == '1200' || str_contains(strtolower($acc->name), 'inventory') || $loop->first);
                                @endphp
                                <option value="{{ $acc->id }}" {{ $isDefault ? 'selected' : '' }}>
                                    {{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}
                                </option>
                            @endforeach
                        @else
                            <option value="1200" selected>1200 - Inventory</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-shopping-cart me-1 text-warning"></i> Purchase / COGS Account
                    </label>
                    <select id="importDefaultPurchaseAccount" class="form-select form-select-sm select2-import-account" style="width: 100%;">
                        @if(isset($purchaseAccounts) && count($purchaseAccounts) > 0)
                            @foreach($purchaseAccounts as $acc)
                                @php
                                    $isDefault = ($acc->code == '5010' || str_contains(strtolower($acc->name), 'cost of goods sold') || str_contains(strtolower($acc->name), 'cogs') || $loop->first);
                                @endphp
                                <option value="{{ $acc->id }}" {{ $isDefault ? 'selected' : '' }}>
                                    {{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}
                                </option>
                            @endforeach
                        @else
                            <option value="5010" selected>5010 - Cost of Goods Sold</option>
                        @endif
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">
                        <i class="feather-dollar-sign me-1 text-primary"></i> Sales / Revenue Account
                    </label>
                    <select id="importDefaultSalesAccount" class="form-select form-select-sm select2-import-account" style="width: 100%;">
                        @if(isset($salesAccounts) && count($salesAccounts) > 0)
                            @foreach($salesAccounts as $acc)
                                @php
                                    $isDefault = ($acc->code == '4010' || str_contains(strtolower($acc->name), 'sales revenue') || str_contains(strtolower($acc->name), 'sales') || $loop->first);
                                @endphp
                                <option value="{{ $acc->id }}" {{ $isDefault ? 'selected' : '' }}>
                                    {{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}
                                </option>
                            @endforeach
                        @else
                            <option value="4010" selected>4010 - Sales Revenue</option>
                        @endif
                    </select>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <x-ui.button href="{{ route('inventory.products.downloadSample') }}" variant="outline-secondary" size="sm" icon="feather-download">
                Download Standard Template
            </x-ui.button>
            <x-ui.button type="button" variant="primary" id="btnAnalyzeFile" disabled onclick="analyzeUploadedFile()">
                <span class="spinner-border spinner-border-sm d-none me-1" id="analyzeSpinner"></span>
                Analyze & Map Columns <i class="feather-arrow-right ms-1"></i>
            </x-ui.button>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- STEP 2: VISUAL COLUMN MAPPER TABLE (LEFT vs RIGHT)       --}}
    {{-- ======================================================== --}}
    <div id="smartImportStep2" class="d-none">
        <div class="d-flex align-items-center justify-content-between bg-soft-primary p-3 rounded-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <i class="feather-check-circle text-primary fs-18"></i>
                <div>
                    <span class="fw-bold text-dark fs-13" id="step2FileSummary">File Ready</span>
                    <div class="text-muted fs-12">Columns have been auto-matched. Review and adjust any mappings below.</div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <x-ui.badge variant="secondary" :soft="true" id="step2TotalRowsBadge" class="fs-12 px-2 py-1">
                    0 Data Rows
                </x-ui.badge>
            </div>
        </div>

        {{-- Mapping Table --}}
        <div class="table-responsive border rounded-3" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0 fs-13">
                <thead class="table-light sticky-top" style="z-index: 2;">
                    <tr>
                        <th style="width: 32%;">ERP Field</th>
                        <th style="width: 35%;">Your File Column</th>
                        <th style="width: 33%;">Sample Preview (From File)</th>
                    </tr>
                </thead>
                <tbody id="mappingTableBody">
                    {{-- Populated dynamically via JS --}}
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
            <x-ui.button type="button" variant="outline-secondary" icon="feather-arrow-left" onclick="goToStep(1)">
                Back to Upload
            </x-ui.button>
            <div class="d-flex gap-2">
                <x-ui.button type="button" variant="outline-primary" id="btnTestImport" icon="feather-activity" onclick="executeImport(true)">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="testSpinner"></span>
                    Test Import (Dry Run)
                </x-ui.button>
                <x-ui.button type="button" variant="success" id="btnProcessImport" icon="feather-check" onclick="executeImport(false)">
                    <span class="spinner-border spinner-border-sm d-none me-1" id="importSpinner"></span>
                    Confirm & Import All Products
                </x-ui.button>
            </div>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- STEP 3: RESULT & REPORT SUMMARY                          --}}
    {{-- ======================================================== --}}
    <div id="smartImportStep3" class="d-none">
        <div class="text-center py-4">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-soft-success text-success mb-3" style="width: 64px; height: 64px;">
                <i class="feather-check-circle" style="font-size: 32px;"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1" id="resultTitle">Import Completed Successfully!</h4>
            <p class="text-muted fs-13 mb-4" id="resultSubtitle">Your products are now available in the inventory master.</p>

            {{-- Stats Cards using common cards --}}
            <div class="row g-3 justify-content-center mb-4 text-start">
                <div class="col-md-3">
                    <div class="card border-0 bg-soft-success p-3 rounded-3 text-center">
                        <span class="fs-12 text-muted fw-semibold text-uppercase">New Products Created</span>
                        <h3 class="fw-bold text-success mb-0 mt-1" id="statCreatedCount">0</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-soft-primary p-3 rounded-3 text-center">
                        <span class="fs-12 text-muted fw-semibold text-uppercase">Existing Updated</span>
                        <h3 class="fw-bold text-primary mb-0 mt-1" id="statUpdatedCount">0</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-soft-secondary p-3 rounded-3 text-center">
                        <span class="fs-12 text-muted fw-semibold text-uppercase">Skipped / Ignored</span>
                        <h3 class="fw-bold text-secondary mb-0 mt-1" id="statSkippedCount">0</h3>
                    </div>
                </div>
            </div>

            {{-- Errors / Warnings Collapsible --}}
            <div id="importErrorsContainer" class="d-none text-start mb-4">
                <h6 class="fw-bold text-danger fs-13 mb-2">
                    <i class="feather-alert-triangle me-1"></i>Issues / Skipped Rows:
                </h6>
                <div class="border rounded p-2 bg-light" style="max-height: 160px; overflow-y: auto;">
                    <ul class="list-unstyled mb-0 fs-12 text-muted" id="importErrorsList"></ul>
                </div>
            </div>

            <div class="d-flex justify-content-center gap-2">
                <x-ui.button type="button" variant="outline-secondary" icon="feather-rotate-ccw" onclick="resetSmartImport()">
                    Import Another File
                </x-ui.button>
                <x-ui.button type="button" variant="primary" icon="feather-arrow-right" onclick="window.location.reload()">
                    Done & View Products
                </x-ui.button>
            </div>
        </div>
    </div>

</x-ui.modal>

@push('scripts')
<script>
    let smartImportState = {
        file: null,
        fileToken: null,
        headers: [],
        sampleRows: [],
        fieldsSchema: {},
        suggestedMapping: {},
        totalRows: 0
    };

    // File selection listener
    document.getElementById('smartImportFileInput').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            handleFileSelect(e.target.files[0]);
        }
    });

    // Drag & Drop handlers
    const dropZone = document.getElementById('dropZoneContainer');
    if (dropZone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.borderColor = '#6366f1';
                dropZone.style.backgroundColor = '#f8fafc';
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.borderColor = '#cbd5e1';
                dropZone.style.backgroundColor = '#ffffff';
            }, false);
        });
        dropZone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });
    }

    function handleFileSelect(file) {
        smartImportState.file = file;
        document.getElementById('selectedFileName').textContent = file.name;
        document.getElementById('selectedFileSize').textContent = '(' + (file.size / 1024).toFixed(1) + ' KB)';
        document.getElementById('selectedFileInfo').classList.remove('d-none');
        document.getElementById('btnAnalyzeFile').removeAttribute('disabled');
        hideSmartAlert();
    }

    function resetSelectedFile() {
        smartImportState.file = null;
        document.getElementById('smartImportFileInput').value = '';
        document.getElementById('selectedFileInfo').classList.add('d-none');
        document.getElementById('btnAnalyzeFile').setAttribute('disabled', 'disabled');
    }

    function showSmartAlert(msg, type = 'danger') {
        const wrapper = document.getElementById('smartImportAlertWrapper');
        const alertEl = document.getElementById('smartImportAlert');
        const contentEl = document.getElementById('smartImportAlertContent');
        if (contentEl && wrapper) {
            contentEl.innerHTML = msg;
            wrapper.classList.remove('d-none');
        }
    }

    function hideSmartAlert() {
        const wrapper = document.getElementById('smartImportAlertWrapper');
        if (wrapper) wrapper.classList.add('d-none');
    }

    function goToStep(step) {
        document.getElementById('smartImportStep1').classList.toggle('d-none', step !== 1);
        document.getElementById('smartImportStep2').classList.toggle('d-none', step !== 2);
        document.getElementById('smartImportStep3').classList.toggle('d-none', step !== 3);

        // Update step indicators
        for (let i = 1; i <= 3; i++) {
            const ind = document.getElementById('stepIndicator' + i);
            const txt = document.getElementById('stepText' + i);
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
    function analyzeUploadedFile() {
        if (!smartImportState.file) return;

        const formData = new FormData();
        formData.append('file', smartImportState.file);
        formData.append('_token', '{{ csrf_token() }}');

        const btn = document.getElementById('btnAnalyzeFile');
        const spinner = document.getElementById('analyzeSpinner');
        btn.setAttribute('disabled', 'disabled');
        spinner.classList.remove('d-none');
        hideSmartAlert();

        fetch("{{ route('inventory.products.import.parse') }}", {
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
                showSmartAlert(res.message || 'Failed to parse file.', 'danger');
                return;
            }

            const data = res.data;
            smartImportState.fileToken = data.file_token;
            smartImportState.headers = data.headers || [];
            smartImportState.sampleRows = data.sample_rows || [];
            smartImportState.fieldsSchema = data.fields_schema || {};
            smartImportState.suggestedMapping = data.suggested_mapping || {};
            smartImportState.totalRows = data.total_rows || 0;

            buildMappingTable();

            document.getElementById('step2FileSummary').textContent = data.original_name;
            document.getElementById('step2TotalRowsBadge').textContent = data.total_rows + ' Data Rows';

            goToStep(2);
        })
        .catch(err => {
            btn.removeAttribute('disabled');
            spinner.classList.add('d-none');
            showSmartAlert('Network error while analyzing file: ' + err.message, 'danger');
        });
    }

    function buildMappingTable() {
        const tbody = document.getElementById('mappingTableBody');
        
        // Destroy existing select2 instances if any before rebuilding
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(tbody).find('.select2-mapping').each(function() {
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }
            });
        }

        tbody.innerHTML = '';

        const headers = smartImportState.headers;
        const sampleRows = smartImportState.sampleRows;
        const schema = smartImportState.fieldsSchema;
        const suggested = smartImportState.suggestedMapping;

        Object.keys(schema).forEach(fieldKey => {
            const meta = schema[fieldKey];
            const matchedHeader = suggested[fieldKey] || '';

            const tr = document.createElement('tr');

            // 1. ERP Field Column
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
                optionsHtml += `<option value="${escapeHtml(h)}" ${isSelected}>${escapeHtml(h)}</option>`;
            });

            let selectHtml = `
                <select class="form-select form-select-sm mapping-select select2-mapping" data-field="${fieldKey}" style="width: 100%;">
                    ${optionsHtml}
                </select>
            `;

            // 3. Sample Preview Column
            let previewHtml = `<div class="sample-preview-box text-muted fs-12 font-monospace" id="preview_${fieldKey}">—</div>`;

            tr.innerHTML = `
                <td>${fieldHtml}</td>
                <td>${selectHtml}</td>
                <td>${previewHtml}</td>
            `;

            tbody.appendChild(tr);

            // Trigger initial sample preview
            updateSamplePreview(fieldKey);
        });

        // Initialize Select2 on all mapping dropdowns
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('.select2-mapping').each(function() {
                const fieldKey = $(this).data('field');
                $(this).select2({
                    dropdownParent: $('#importProductsModal'),
                    width: '100%'
                }).on('change', function() {
                    updateSamplePreview(fieldKey);
                });
            });
        }
    }

    function updateSamplePreview(fieldKey) {
        const select = document.querySelector(`.mapping-select[data-field="${fieldKey}"]`);
        const previewBox = document.getElementById('preview_' + fieldKey);
        if (!select || !previewBox) return;

        const selectedHeader = select.value;
        if (!selectedHeader) {
            previewBox.innerHTML = '<span class="text-muted fst-italic fs-11">— Skipped —</span>';
            return;
        }

        const sampleRows = smartImportState.sampleRows;
        const samples = [];
        sampleRows.slice(0, 3).forEach(row => {
            if (row[selectedHeader] !== undefined && row[selectedHeader] !== '') {
                samples.push(row[selectedHeader]);
            }
        });

        if (samples.length > 0) {
            previewBox.innerHTML = samples.map(s => `<span class="badge bg-light text-dark border me-1">${escapeHtml(s)}</span>`).join('');
        } else {
            previewBox.innerHTML = '<span class="text-muted fs-11">No preview value</span>';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Step 2 -> Step 3: Execute Import (Dry Run or Real)
    function executeImport(isDryRun) {
        const mapping = {};
        let nameMapped = false;

        document.querySelectorAll('.mapping-select').forEach(sel => {
            const field = sel.getAttribute('data-field');
            const val = sel.value;
            if (val) {
                mapping[field] = val;
                if (field === 'name') nameMapped = true;
            }
        });

        if (!nameMapped) {
            showSmartAlert('<strong>Validation Error:</strong> You must map at least the <strong>Item / Product Name</strong> field.', 'danger');
            return;
        }

        const defaultInvAcc = document.getElementById('importDefaultInventoryAccount') ? document.getElementById('importDefaultInventoryAccount').value : null;
        const defaultPurchAcc = document.getElementById('importDefaultPurchaseAccount') ? document.getElementById('importDefaultPurchaseAccount').value : null;
        const defaultSalesAcc = document.getElementById('importDefaultSalesAccount') ? document.getElementById('importDefaultSalesAccount').value : null;

        const options = {
            auto_create_uom: document.getElementById('importOptionAutoUom').checked,
            update_existing: document.getElementById('importOptionUpdateExisting').checked,
            default_type: document.getElementById('importOptionDefaultType').value,
            default_inventory_account: defaultInvAcc,
            default_purchase_account: defaultPurchAcc,
            default_sales_account: defaultSalesAcc,
            dry_run: isDryRun
        };

        const payload = {
            file_token: smartImportState.fileToken,
            mapping: mapping,
            options: options,
            _token: '{{ csrf_token() }}'
        };

        const btn = isDryRun ? document.getElementById('btnTestImport') : document.getElementById('btnProcessImport');
        const spinner = isDryRun ? document.getElementById('testSpinner') : document.getElementById('importSpinner');
        btn.setAttribute('disabled', 'disabled');
        spinner.classList.remove('d-none');
        hideSmartAlert();

        fetch("{{ route('inventory.products.import.process') }}", {
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
                showSmartAlert(res.message || 'Import processing failed.', 'danger');
                return;
            }

            if (isDryRun) {
                showSmartAlert(`
                    <strong>Dry Run Validation Passed!</strong><br>
                    • New products to be created: <strong>${res.imported_count}</strong><br>
                    • Existing products to be updated: <strong>${res.updated_count}</strong><br>
                    • Skipped: <strong>${res.skipped_count}</strong>
                `, 'success');
            } else {
                // Real Import Finished -> Show Step 3
                document.getElementById('statCreatedCount').textContent = res.imported_count;
                document.getElementById('statUpdatedCount').textContent = res.updated_count;
                document.getElementById('statSkippedCount').textContent = res.skipped_count;

                if (res.errors && res.errors.length > 0) {
                    const list = document.getElementById('importErrorsList');
                    list.innerHTML = res.errors.map(err => `<li>• ${escapeHtml(err)}</li>`).join('');
                    document.getElementById('importErrorsContainer').classList.remove('d-none');
                } else {
                    document.getElementById('importErrorsContainer').classList.add('d-none');
                }

                goToStep(3);
            }
        })
        .catch(err => {
            btn.removeAttribute('disabled');
            spinner.classList.add('d-none');
            showSmartAlert('Network error during import: ' + err.message, 'danger');
        });
    }

    function resetSmartImport() {
        resetSelectedFile();
        smartImportState = {
            file: null,
            fileToken: null,
            headers: [],
            sampleRows: [],
            fieldsSchema: {},
            suggestedMapping: {},
            totalRows: 0
        };
        hideSmartAlert();
        goToStep(1);
    }

    // Initialize Select2 on modal shown
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof $ !== 'undefined') {
            $('#importProductsModal').on('shown.bs.modal', function() {
                $('.select2-import-account').each(function() {
                    if (!$(this).data('select2')) {
                        $(this).select2({
                            dropdownParent: $('#importProductsModal'),
                            width: '100%'
                        });
                    }
                });
            });
        }
    });
</script>
@endpush
