@extends('layouts.duralux')

@section('title', 'GST E-Invoice & E-Way Bill Settings | Platform Settings')
@section('page-title', 'GST E-Invoice & E-Way Bill Setup')
@section('breadcrumb', 'Platform / Settings / GST & E-Invoice')

@section('page-actions')
    <button type="button" class="btn btn-sm btn-primary fw-bold px-3 shadow-2xs" data-bs-toggle="modal" data-bs-target="#addGstConfigModal" onclick="resetGstForm()">
        <i class="feather-plus me-1.5"></i>ADD GST CONFIGURATION
    </button>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
    <!-- Header Title & Context Strip -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 pb-3 border-bottom">
        <div>
            <h5 class="fw-bold text-dark mb-1"><i class="feather-file-text text-primary me-2"></i>Multi-Tenant & Branch GST, E-Invoice & E-Way Bill Hub</h5>
            <p class="text-muted fs-12 mb-0">Manage GSP credentials (Setu.co, ClearTax, Masters India, Sandbox) and Seller GSTIN identities per Company & Branch.</p>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2 fs-12">
            <span class="fw-bold text-secondary me-1"><i class="feather-layers me-1"></i>Current Context:</span>
            <span class="badge erp-badge bg-primary text-white"><i class="feather-globe me-1"></i>Tenant: {{ tenant() ? tenant()->name : $tenantId }}</span>
            <span class="badge erp-badge bg-info text-dark"><i class="feather-briefcase me-1"></i>Company: {{ $companyId ?: 'All Companies' }}</span>
            <span class="badge erp-badge bg-secondary text-white"><i class="feather-git-branch me-1"></i>Branch: {{ $branchId ?: 'All Branches' }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
            <i class="feather-check-circle fs-18 me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
            <i class="feather-alert-circle fs-18 me-2"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <div class="fw-bold mb-1"><i class="feather-alert-triangle me-1"></i>Validation Errors:</div>
            <ul class="mb-0 ps-3 fs-13">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Provider Presets Banner -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="p-3 rounded border bg-light bg-opacity-50 h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar avatar-xs bg-soft-primary text-primary rounded"><i class="feather-zap fs-13"></i></span>
                    <strong class="fs-13 text-dark">Setu.co (Pine Labs)</strong>
                </div>
                <span class="fs-11 text-muted d-block mb-2">Instant Developer API Key / Bearer Token integration.</span>
                <span class="badge bg-soft-success text-success border fs-10">REST API Ready</span>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-3 rounded border bg-light bg-opacity-50 h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar avatar-xs bg-soft-info text-info rounded"><i class="feather-shield fs-13"></i></span>
                    <strong class="fs-13 text-dark">ClearTax Enterprise</strong>
                </div>
                <span class="fs-11 text-muted d-block mb-2">India #1 GSP with high-throughput OAuth2 & Client Secret.</span>
                <span class="badge bg-soft-info text-info border fs-10">OAuth2 Supported</span>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-3 rounded border bg-light bg-opacity-50 h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar avatar-xs bg-soft-warning text-warning rounded"><i class="feather-cpu fs-13"></i></span>
                    <strong class="fs-13 text-dark">Masters India / GSP</strong>
                </div>
                <span class="fs-11 text-muted d-block mb-2">AutoTax E-Invoice & E-Way Bill gateway credentials.</span>
                <span class="badge bg-soft-warning text-warning border fs-10">Low Latency</span>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-3 rounded border bg-light bg-opacity-50 h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar avatar-xs bg-soft-success text-success rounded"><i class="feather-download-cloud fs-13"></i></span>
                    <strong class="fs-13 text-dark">NIC GEPP JSON Export</strong>
                </div>
                <span class="fs-11 text-muted d-block mb-2">100% Free 1-click bulk JSON upload to Government Portal.</span>
                <span class="badge bg-soft-primary text-primary border fs-10">Standard 1.03 Schema</span>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="table-responsive flex-grow-1">
        <x-ui.odoo-form-ui type="table" id="gstConfigsTable" class="mb-0">
            <thead>
                <tr style="background-color: #e8ecf1 !important;">
                    <th style="background-color: #e8ecf1 !important;" class="ps-3">Seller GSTIN & Legal Name</th>
                    <th style="background-color: #e8ecf1 !important;">GSP Provider</th>
                    <th style="background-color: #e8ecf1 !important;">Scope & Location</th>
                    <th style="background-color: #e8ecf1 !important;">Environment</th>
                    <th style="background-color: #e8ecf1 !important;">Status</th>
                    <th style="background-color: #e8ecf1 !important;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($configurations as $config)
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar avatar-sm bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="feather-file-text"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <strong class="font-monospace text-dark fs-12">{{ $config->seller_gstin }}</strong>
                                        @if($config->is_default)
                                            <span class="badge bg-primary fs-10 px-1.5 py-0.5">DEFAULT</span>
                                        @endif
                                    </div>
                                    <span class="text-muted fs-11 d-block">{{ $config->legal_name }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark fs-12 text-capitalize">
                                {{ str_replace('_', ' ', $config->provider) }}
                            </div>
                            <span class="text-muted fs-11 font-monospace">{{ ucfirst($config->auth_type) }}</span>
                        </td>
                        <td>
                            <div class="fs-12 text-dark">
                                @if($config->company)
                                    <i class="feather-briefcase text-muted me-1 fs-11"></i>{{ $config->company->name }}
                                @else
                                    <span class="text-muted">All Companies</span>
                                @endif
                                @if($config->branch)
                                    <span class="text-muted">/ {{ $config->branch->name }}</span>
                                @endif
                            </div>
                            <span class="text-muted fs-11"><i class="feather-map-pin me-1 fs-10"></i>{{ $config->location }}, State Code: {{ $config->state_code }} ({{ $config->pincode }})</span>
                        </td>
                        <td>
                            @if($config->environment === 'production')
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 fs-11 fw-bold">
                                    <i class="feather-globe me-1"></i>Production (Live)
                                </span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0.5 fs-11 fw-bold">
                                    <i class="feather-tool me-1"></i>Sandbox (Test)
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($config->is_active)
                                <span class="badge bg-success px-2 py-0.5 fs-11">Active</span>
                            @else
                                <span class="badge bg-secondary px-2 py-0.5 fs-11">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <div class="hstack gap-1 justify-content-end">
                                <!-- Test Connection Ping Button -->
                                <button type="button" class="btn btn-2xs btn-outline-info fw-bold btn-test-connection" data-id="{{ $config->id }}" data-provider="{{ $config->provider }}" title="Test Connection / Validate Token">
                                    <i class="feather-activity me-1"></i>Test Ping
                                </button>
                                
                                <!-- Edit Button -->
                                <button type="button" class="btn btn-2xs btn-outline-primary fw-bold btn-edit-config" data-config='@json($config)' title="Edit Configuration">
                                    <i class="feather-edit-2"></i>
                                </button>

                                <!-- Delete Form -->
                                <form action="{{ route('platform.gstSettings.destroy', $config->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this GST configuration?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-2xs btn-outline-danger" title="Delete Configuration">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="avatar avatar-xl bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="feather-file-text fs-24"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-14">No Custom GST API Configurations Found</h6>
                            <p class="text-muted fs-12 mb-3">System is currently running on the built-in Sandbox Statutory Engine (NIC Schema 1.03).</p>
                            <button type="button" class="btn btn-sm btn-primary fw-bold px-3" data-bs-toggle="modal" data-bs-target="#addGstConfigModal" onclick="resetGstForm()">
                                <i class="feather-plus me-1"></i>Setup Custom GSP API Credentials
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </div>
</div>

<!-- ADD / EDIT GST CONFIGURATION MODAL -->
<x-ui.modal id="addGstConfigModal" title="<i class='feather-file-text text-primary me-1.5'></i>Configure GST E-Invoice & E-Way Bill API" size="xl" :centered="true" :showFooter="false">
    <form id="gstConfigForm" action="{{ route('platform.gstSettings.store') }}" method="POST">
        @csrf
        <input type="hidden" name="id" id="configId">

        <div class="row g-3">
            <!-- 1. Scope & Provider Selection -->
            <div class="col-12">
                <div class="p-3 bg-light rounded border">
                    <span class="fs-11 text-uppercase fw-bold text-muted d-block mb-2">1. Provider & Environment Details</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fs-12 fw-bold text-dark">GSP Provider <span class="text-danger">*</span></label>
                            <select name="provider" id="configProvider" class="form-select form-select-sm" required onchange="handleProviderChange()">
                                <option value="sandbox">Built-in Sandbox Engine (Free & Built-in)</option>
                                <option value="setu">Setu.co (Pine Labs - API Key / Token)</option>
                                <option value="cleartax">ClearTax Enterprise (Client ID & Secret)</option>
                                <option value="masters_india">Masters India (AutoTax GSP)</option>
                                <option value="nic_direct">NIC Direct Government Gateway</option>
                                <option value="custom">Custom GSP Gateway</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-12 fw-bold text-dark">Environment <span class="text-danger">*</span></label>
                            <select name="environment" id="configEnvironment" class="form-select form-select-sm" required>
                                <option value="sandbox">Sandbox / Testing Kit</option>
                                <option value="production">Production (Live Government Server)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-12 fw-bold text-dark">Authentication Method <span class="text-danger">*</span></label>
                            <select name="auth_type" id="configAuthType" class="form-select form-select-sm" required>
                                <option value="bearer_token">Bearer Token / API Key (Setu / Free Kit)</option>
                                <option value="api_key">Client ID + Client Secret (ClearTax / Masters)</option>
                                <option value="gsp_credentials">GST Portal Username & Password (Direct)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. API Credentials Box -->
            <div class="col-12" id="credentialsBox">
                <div class="p-3 bg-light rounded border">
                    <span class="fs-11 text-uppercase fw-bold text-muted d-block mb-2">2. API Credentials & Tokens</span>
                    <div class="row g-3">
                        <div class="col-md-6" id="fieldApiToken">
                            <label class="form-label fs-12 fw-bold text-dark">API Key / Bearer Token</label>
                            <input type="password" name="api_token" id="configApiToken" class="form-control form-control-sm font-monospace" placeholder="e.g. setu_live_token_... or x-api-key">
                            <span class="fs-10 text-muted">Paste your Setu or Provider API Key here.</span>
                        </div>
                        <div class="col-md-6" id="fieldApiBaseUrl">
                            <label class="form-label fs-12 fw-bold text-dark">API Base URL (Optional)</label>
                            <input type="url" name="api_base_url" id="configApiBaseUrl" class="form-control form-control-sm font-monospace" placeholder="Leave empty for auto-provider default">
                        </div>
                        <div class="col-md-6" id="fieldClientId">
                            <label class="form-label fs-12 fw-bold text-dark">Client ID</label>
                            <input type="text" name="client_id" id="configClientId" class="form-control form-control-sm font-monospace" placeholder="Provider Client ID">
                        </div>
                        <div class="col-md-6" id="fieldClientSecret">
                            <label class="form-label fs-12 fw-bold text-dark">Client Secret</label>
                            <input type="password" name="client_secret" id="configClientSecret" class="form-control form-control-sm font-monospace" placeholder="Provider Client Secret">
                        </div>
                        <div class="col-md-6" id="fieldGstUser">
                            <label class="form-label fs-12 fw-bold text-dark">GST Portal API Username</label>
                            <input type="text" name="gstin_username" id="configGstinUsername" class="form-control form-control-sm" placeholder="Created on einvoice1.gst.gov.in">
                        </div>
                        <div class="col-md-6" id="fieldGstPass">
                            <label class="form-label fs-12 fw-bold text-dark">GST Portal API Password</label>
                            <input type="password" name="gstin_password" id="configGstinPassword" class="form-control form-control-sm" placeholder="Portal API Password">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Seller GSTIN Identity & Scope -->
            <div class="col-12">
                <div class="p-3 bg-light rounded border">
                    <span class="fs-11 text-uppercase fw-bold text-muted d-block mb-2">3. Seller GSTIN & Business Unit Identity</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fs-12 fw-bold text-dark">Seller GSTIN <span class="text-danger">*</span></label>
                            <input type="text" name="seller_gstin" id="configSellerGstin" class="form-control form-control-sm font-monospace text-uppercase" placeholder="e.g. 08AAFCS1234E1Z0" maxlength="15" required value="08AAFCS1234E1Z0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-12 fw-bold text-dark">Legal Entity Name <span class="text-danger">*</span></label>
                            <input type="text" name="legal_name" id="configLegalName" class="form-control form-control-sm" placeholder="e.g. Acme Industries Ltd" required value="{{ tenant() ? tenant()->name : 'SaaS ERP Global' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-12 fw-bold text-dark">Trade / Brand Name</label>
                            <input type="text" name="trade_name" id="configTradeName" class="form-control form-control-sm" placeholder="e.g. Acme ERP">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-bold text-dark">Address Line 1 <span class="text-danger">*</span></label>
                            <input type="text" name="address_line1" id="configAddressLine1" class="form-control form-control-sm" placeholder="Floor, Building, Street" required value="H-1, Industrial Area, Sukher">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-bold text-dark">Address Line 2</label>
                            <input type="text" name="address_line2" id="configAddressLine2" class="form-control form-control-sm" placeholder="Area / Landmark">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-bold text-dark">City / Location <span class="text-danger">*</span></label>
                            <input type="text" name="location" id="configLocation" class="form-control form-control-sm" placeholder="City" required value="Udaipur">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-bold text-dark">Pincode <span class="text-danger">*</span></label>
                            <input type="text" name="pincode" id="configPincode" class="form-control form-control-sm font-monospace" placeholder="e.g. 313001" maxlength="6" required value="313001">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-bold text-dark">State Code (2 Digits) <span class="text-danger">*</span></label>
                            <input type="text" name="state_code" id="configStateCode" class="form-control form-control-sm font-monospace" placeholder="e.g. 08" maxlength="2" required value="08">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-bold text-dark">Billing Email</label>
                            <input type="email" name="contact_email" id="configContactEmail" class="form-control form-control-sm" placeholder="accounts@company.com" value="{{ tenant() ? tenant()->billing_email : 'billing@saaserp.com' }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-bold text-dark">Assign to Company (Optional)</label>
                            <select name="company_id" id="configCompanyId" class="form-select form-select-sm">
                                <option value="">Global (All Companies in Workspace)</option>
                                @foreach($companies as $comp)
                                    <option value="{{ $comp->id }}" {{ $companyId == $comp->id ? 'selected' : '' }}>{{ $comp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-bold text-dark">Assign to Branch (Optional)</label>
                            <select name="branch_id" id="configBranchId" class="form-select form-select-sm">
                                <option value="">All Branches</option>
                                @foreach($branches as $br)
                                    <option value="{{ $br->id }}" {{ $branchId == $br->id ? 'selected' : '' }}>{{ $br->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Flags & Preferences -->
            <div class="col-12">
                <div class="d-flex align-items-center gap-4 py-1">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_default" id="configIsDefault" value="1">
                        <label class="form-check-label fs-12 fw-bold text-dark" for="configIsDefault">Set as Default Configuration</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="auto_generate_on_post" id="configAutoGenerate" value="1">
                        <label class="form-check-label fs-12 fw-bold text-dark" for="configAutoGenerate">Auto-Generate IRN when Invoice is Posted</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="configIsActive" value="1" checked>
                        <label class="form-check-label fs-12 fw-bold text-dark" for="configIsActive">Active</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary fw-bold px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-primary fw-bold px-4">
                <i class="feather-save me-1.5"></i>Save Configuration
            </button>
        </div>
    </form>
</x-ui.modal>

<!-- TEST PING RESULT MODAL -->
<x-ui.modal id="testPingModal" title="<i class='feather-activity text-info me-1.5'></i>GSP Connection Test" size="md" :centered="true" :showFooter="false">
    <div class="p-3 text-center">
        <div id="pingStatusIcon" class="mb-2"></div>
        <h5 id="pingStatusTitle" class="fw-bold text-dark mb-2"></h5>
        <div id="pingStatusMsg" class="alert alert-light border fs-12 font-monospace p-2.5 mb-3 text-break"></div>
        <button type="button" class="btn btn-sm btn-primary fw-bold px-4" data-bs-dismiss="modal">Close</button>
    </div>
</x-ui.modal>

@push('scripts')
<script>
    function resetGstForm() {
        $('#gstConfigForm')[0].reset();
        $('#configId').val('');
        $('#configProvider').val('setu');
        $('#configEnvironment').val('sandbox');
        $('#configAuthType').val('bearer_token');
        $('#configIsActive').prop('checked', true);
        handleProviderChange();
    }

    function handleProviderChange() {
        const p = $('#configProvider').val();
        if (p === 'sandbox') {
            $('#credentialsBox').addClass('d-none');
        } else if (p === 'setu') {
            $('#credentialsBox').removeClass('d-none');
            $('#fieldApiToken, #fieldClientId, #fieldClientSecret').removeClass('d-none');
            $('#fieldGstUser, #fieldGstPass').addClass('d-none');
        } else if (p === 'cleartax' || p === 'masters_india') {
            $('#configAuthType').val('api_key');
            $('#credentialsBox').removeClass('d-none');
            $('#fieldApiToken').addClass('d-none');
            $('#fieldClientId, #fieldClientSecret, #fieldGstUser, #fieldGstPass').removeClass('d-none');
        } else {
            $('#credentialsBox').removeClass('d-none');
            $('#fieldApiToken, #fieldClientId, #fieldClientSecret, #fieldGstUser, #fieldGstPass').removeClass('d-none');
        }
    }

    $(document).on('click', '.btn-edit-config', function() {
        const c = $(this).data('config');
        $('#configId').val(c.id);
        $('#configProvider').val(c.provider);
        $('#configEnvironment').val(c.environment);
        $('#configAuthType').val(c.auth_type);
        $('#configApiBaseUrl').val(c.api_base_url || '');
        $('#configApiToken').val(c.api_token || '');
        $('#configClientId').val(c.client_id || '');
        $('#configClientSecret').val(c.client_secret || '');
        $('#configGstinUsername').val(c.gstin_username || '');
        $('#configGstinPassword').val(c.gstin_password || '');
        $('#configSellerGstin').val(c.seller_gstin);
        $('#configLegalName').val(c.legal_name);
        $('#configTradeName').val(c.trade_name || '');
        $('#configAddressLine1').val(c.address_line1);
        $('#configAddressLine2').val(c.address_line2 || '');
        $('#configLocation').val(c.location);
        $('#configPincode').val(c.pincode);
        $('#configStateCode').val(c.state_code);
        $('#configContactEmail').val(c.contact_email || '');
        $('#configCompanyId').val(c.company_id || '');
        $('#configBranchId').val(c.branch_id || '');
        $('#configIsDefault').prop('checked', !!c.is_default);
        $('#configAutoGenerate').prop('checked', !!c.auto_generate_on_post);
        $('#configIsActive').prop('checked', !!c.is_active);

        handleProviderChange();
        const modal = new bootstrap.Modal(document.getElementById('addGstConfigModal'));
        modal.show();
    });

    $(document).on('click', '.btn-test-connection', function() {
        const id = $(this).data('id');
        const btn = $(this);
        const origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="feather-loader spin"></i>');

        $.ajax({
            url: '/platform/gst-settings/' + id + '/test',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                btn.prop('disabled', false).html(origHtml);
                const isOnline = res.status === 'online';
                $('#pingStatusIcon').html(isOnline 
                    ? '<div class="avatar avatar-lg bg-soft-success text-success rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:50px;height:50px;"><i class="feather-check-circle fs-24"></i></div>'
                    : '<div class="avatar avatar-lg bg-soft-warning text-warning rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:50px;height:50px;"><i class="feather-alert-triangle fs-24"></i></div>'
                );
                $('#pingStatusTitle').text(isOnline ? 'Connection Successful!' : 'Gateway Response: ' + (res.status || 'Notice'));
                $('#pingStatusMsg').text(res.message);

                const modal = new bootstrap.Modal(document.getElementById('testPingModal'));
                modal.show();
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(origHtml);
                $('#pingStatusIcon').html('<div class="avatar avatar-lg bg-soft-danger text-danger rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:50px;height:50px;"><i class="feather-x-circle fs-24"></i></div>');
                $('#pingStatusTitle').text('Connection Failed');
                $('#pingStatusMsg').text(xhr.responseJSON ? xhr.responseJSON.message : 'Unable to connect to GSP provider.');

                const modal = new bootstrap.Modal(document.getElementById('testPingModal'));
                modal.show();
            }
        });
    });

    $(document).ready(function() {
        handleProviderChange();
    });
</script>
@endpush
@endsection
