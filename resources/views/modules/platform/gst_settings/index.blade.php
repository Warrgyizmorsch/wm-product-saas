@extends('layouts.duralux')

@section('title', 'GST E-Invoice & E-Way Bill Settings | Platform Settings')
@section('page-title', 'GST E-Invoice & E-Way Bill Setup')
@section('breadcrumb', 'Platform / Settings / GST & E-Invoice')

@section('page-actions')
    <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addGstConfigModal" onclick="resetGstForm()">
        ADD GST CONFIGURATION
    </x-ui.button>
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
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <x-ui.card class="border shadow-none bg-light bg-opacity-50 h-100 mb-0" bodyClass="p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar avatar-xs bg-soft-primary text-primary rounded"><i class="feather-zap fs-13"></i></span>
                    <strong class="fs-13 text-dark">Setu.co (Pine Labs)</strong>
                </div>
                <span class="fs-11 text-muted d-block mb-2">Instant Developer API Key / Bearer Token integration.</span>
                <x-ui.badge variant="success" :soft="true" class="fs-10">REST API Ready</x-ui.badge>
            </x-ui.card>
        </div>
        <div class="col-md-3 col-6">
            <x-ui.card class="border shadow-none bg-light bg-opacity-50 h-100 mb-0" bodyClass="p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar avatar-xs bg-soft-info text-info rounded"><i class="feather-shield fs-13"></i></span>
                    <strong class="fs-13 text-dark">ClearTax Enterprise</strong>
                </div>
                <span class="fs-11 text-muted d-block mb-2">India #1 GSP with high-throughput OAuth2 & Client Secret.</span>
                <x-ui.badge variant="info" :soft="true" class="fs-10">OAuth2 Supported</x-ui.badge>
            </x-ui.card>
        </div>
        <div class="col-md-3 col-6">
            <x-ui.card class="border shadow-none bg-light bg-opacity-50 h-100 mb-0" bodyClass="p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar avatar-xs bg-soft-warning text-warning rounded"><i class="feather-cpu fs-13"></i></span>
                    <strong class="fs-13 text-dark">Masters India / GSP</strong>
                </div>
                <span class="fs-11 text-muted d-block mb-2">AutoTax E-Invoice & E-Way Bill gateway credentials.</span>
                <x-ui.badge variant="warning" :soft="true" class="fs-10">Low Latency</x-ui.badge>
            </x-ui.card>
        </div>
        <div class="col-md-3 col-6">
            <x-ui.card class="border shadow-none bg-light bg-opacity-50 h-100 mb-0" bodyClass="p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="avatar avatar-xs bg-soft-success text-success rounded"><i class="feather-download-cloud fs-13"></i></span>
                    <strong class="fs-13 text-dark">NIC GEPP JSON Export</strong>
                </div>
                <span class="fs-11 text-muted d-block mb-2">100% Free 1-click bulk JSON upload to Government Portal.</span>
                <x-ui.badge variant="primary" :soft="true" class="fs-10">Standard 1.03 Schema</x-ui.badge>
            </x-ui.card>
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
                            <div class="d-flex align-items-center gap-2.5">
                                <div class="avatar avatar-sm bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                                    <i class="feather-file-text fs-14"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <strong class="font-monospace text-dark fs-12">{{ $config->seller_gstin }}</strong>
                                        @if($config->is_default)
                                            <x-ui.badge variant="primary" :soft="true" class="fs-10 px-2 py-0.5 ms-1">Default</x-ui.badge>
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
                                    <i class="feather-briefcase text-muted me-1 fs-11"></i>{{ $config->company->company_name ?: ($config->company->name ?: $config->company->legal_name) }}
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
                                <x-ui.status-badge status="active" label="Production (Live)" :dot="true" size="sm" />
                            @else
                                <x-ui.status-badge status="on_hold" label="Sandbox (Test)" :dot="true" size="sm" />
                            @endif
                        </td>
                        <td>
                            <x-ui.status-badge :status="$config->is_active ? 'active' : 'inactive'" :dot="true" size="sm" />
                        </td>
                        <td class="text-end pe-3">
                            <x-ui.action-dropdown align="end" id="gstActions_{{ $config->id }}">
                                <x-slot:extraActions>
                                    <!-- Test Connection Ping Button -->
                                    <button type="button" 
                                            class="action-dropdown-btn btn-test-connection" 
                                            data-id="{{ $config->id }}" 
                                            data-provider="{{ $config->provider }}" 
                                            title="Test Ping" 
                                            data-bs-toggle="tooltip">
                                        <i class="feather-activity text-info"></i>
                                    </button>
                                    
                                    <!-- Edit Button -->
                                    <button type="button" 
                                            class="action-dropdown-btn btn-edit-config" 
                                            data-config='@json($config)' 
                                            title="Edit Configuration" 
                                            data-bs-toggle="tooltip">
                                        <i class="feather-edit-2 text-primary"></i>
                                    </button>

                                    <!-- Delete Form -->
                                    <form action="{{ route('platform.gstSettings.destroy', $config->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this GST configuration?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-dropdown-btn" title="Delete Configuration" data-bs-toggle="tooltip">
                                            <i class="feather-trash-2 text-danger"></i>
                                        </button>
                                    </form>
                                </x-slot:extraActions>
                            </x-ui.action-dropdown>
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
                            <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addGstConfigModal" onclick="resetGstForm()">
                                Setup Custom GSP API Credentials
                            </x-ui.button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </div>

    <!-- Pagination -->
    <x-ui.pagination 
        :currentPage="$configurations->currentPage()" 
        :totalPages="$configurations->lastPage()" 
        :totalResults="$configurations->total()" 
        :perPage="$configurations->perPage()" 
    />
</div>

<!-- ADD / EDIT GST CONFIGURATION MODAL -->
<x-ui.modal id="addGstConfigModal" title="<i class='feather-file-text text-primary me-1.5'></i>Configure GST E-Invoice & E-Way Bill API" size="xl" :centered="true" :showFooter="false">
    <form id="gstConfigForm" action="{{ route('platform.gstSettings.store') }}" method="POST">
        @csrf
        <input type="hidden" name="id" id="configId">

        <div class="row g-3">
            <!-- 1. Scope & Provider Selection -->
            <div class="col-12">
                <div class="p-3 rounded border">
                    <span class="fs-11 text-uppercase fw-bold text-muted d-block mb-2">1. Provider & Environment Details</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <x-ui.modal-form-ui type="select" label="GSP Provider" name="provider" id="configProvider" :required="true" :searchable="true" onchange="handleProviderChange()">
                                <option value="sandbox">Built-in Sandbox Engine (Free & Built-in)</option>
                                <option value="setu">Setu.co (Pine Labs - API Key / Token)</option>
                                <option value="cleartax">ClearTax Enterprise (Client ID & Secret)</option>
                                <option value="masters_india">Masters India (AutoTax GSP)</option>
                                <option value="nic_direct">NIC Direct Government Gateway</option>
                                <option value="custom">Custom GSP Gateway</option>
                            </x-ui.modal-form-ui>
                        </div>
                        <div class="col-md-4">
                            <x-ui.modal-form-ui type="select" label="Environment" name="environment" id="configEnvironment" :required="true" :searchable="true">
                                <option value="sandbox">Sandbox / Testing Kit</option>
                                <option value="production">Production (Live Government Server)</option>
                            </x-ui.modal-form-ui>
                        </div>
                        <div class="col-md-4">
                            <x-ui.modal-form-ui type="select" label="Authentication Method" name="auth_type" id="configAuthType" :required="true" :searchable="true">
                                <option value="bearer_token">Bearer Token / API Key (Setu / Free Kit)</option>
                                <option value="api_key">Client ID + Client Secret (ClearTax / Masters)</option>
                                <option value="gsp_credentials">GST Portal Username & Password (Direct)</option>
                            </x-ui.modal-form-ui>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. API Credentials Box -->
            <div class="col-12" id="credentialsBox">
                <div class="p-3 rounded border">
                    <span class="fs-11 text-uppercase fw-bold text-muted d-block mb-2">2. API Credentials & Tokens</span>
                    <div class="row g-3">
                        <div class="col-md-6" id="fieldApiToken">
                            <x-ui.modal-form-ui type="input" inputType="password" label="API Key / Bearer Token" name="api_token" id="configApiToken" placeholder="e.g. setu_live_token_... or x-api-key" helperText="Paste your Setu or Provider API Key here." class="font-monospace" />
                        </div>
                        <div class="col-md-6" id="fieldApiBaseUrl">
                            <x-ui.modal-form-ui type="input" inputType="url" label="API Base URL (Optional)" name="api_base_url" id="configApiBaseUrl" placeholder="Leave empty for auto-provider default" class="font-monospace" />
                        </div>
                        <div class="col-md-6" id="fieldClientId">
                            <x-ui.modal-form-ui type="input" label="Client ID" name="client_id" id="configClientId" placeholder="Provider Client ID" class="font-monospace" />
                        </div>
                        <div class="col-md-6" id="fieldClientSecret">
                            <x-ui.modal-form-ui type="input" inputType="password" label="Client Secret" name="client_secret" id="configClientSecret" placeholder="Provider Client Secret" class="font-monospace" />
                        </div>
                        <div class="col-md-6" id="fieldGstUser">
                            <x-ui.modal-form-ui type="input" label="GST Portal API Username" name="gstin_username" id="configGstinUsername" placeholder="Created on einvoice1.gst.gov.in" />
                        </div>
                        <div class="col-md-6" id="fieldGstPass">
                            <x-ui.modal-form-ui type="input" inputType="password" label="GST Portal API Password" name="gstin_password" id="configGstinPassword" placeholder="Portal API Password" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Seller GSTIN Identity & Scope -->
            <div class="col-12">
                <div class="p-3 rounded border">
                    <span class="fs-11 text-uppercase fw-bold text-muted d-block mb-2">3. Seller GSTIN & Business Unit Identity</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <x-ui.modal-form-ui type="input" label="Seller GSTIN" name="seller_gstin" id="configSellerGstin" placeholder="e.g. 08AAFCS1234E1Z0" maxlength="15" :required="true" value="08AAFCS1234E1Z0" class="font-monospace text-uppercase" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.modal-form-ui type="input" label="Legal Entity Name" name="legal_name" id="configLegalName" placeholder="e.g. Acme Industries Ltd" :required="true" :value="tenant() ? tenant()->name : 'SaaS ERP Global'" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.modal-form-ui type="input" label="Trade / Brand Name" name="trade_name" id="configTradeName" placeholder="e.g. Acme ERP" />
                        </div>

                        <div class="col-md-6">
                            <x-ui.modal-form-ui type="input" label="Address Line 1" name="address_line1" id="configAddressLine1" placeholder="Floor, Building, Street" :required="true" value="H-1, Industrial Area, Sukher" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.modal-form-ui type="input" label="Address Line 2" name="address_line2" id="configAddressLine2" placeholder="Area / Landmark" />
                        </div>

                        <div class="col-md-3">
                            <x-ui.modal-form-ui type="input" label="City / Location" name="location" id="configLocation" placeholder="City" :required="true" value="Udaipur" />
                        </div>
                        <div class="col-md-3">
                            <x-ui.modal-form-ui type="input" label="Pincode" name="pincode" id="configPincode" placeholder="e.g. 313001" maxlength="6" :required="true" value="313001" class="font-monospace" />
                        </div>
                        <div class="col-md-3">
                            <x-ui.modal-form-ui type="input" label="State Code (2 Digits)" name="state_code" id="configStateCode" placeholder="e.g. 08" maxlength="2" :required="true" value="08" class="font-monospace" />
                        </div>
                        <div class="col-md-3">
                            <x-ui.modal-form-ui type="input" inputType="email" label="Billing Email" name="contact_email" id="configContactEmail" placeholder="accounts@company.com" :value="tenant() ? tenant()->billing_email : 'billing@saaserp.com'" />
                        </div>

                        <div class="col-md-6">
                            <x-ui.modal-form-ui type="select" label="Assign to Company (Optional)" name="company_id" id="configCompanyId" :searchable="true">
                                <option value="">Global (All Companies in Workspace)</option>
                                @foreach($companies as $comp)
                                    <option value="{{ $comp->id }}" {{ $companyId == $comp->id ? 'selected' : '' }}>{{ $comp->company_name ?: ($comp->name ?: $comp->legal_name) }}</option>
                                @endforeach
                            </x-ui.modal-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.modal-form-ui type="select" label="Assign to Branch (Optional)" name="branch_id" id="configBranchId" :searchable="true">
                                <option value="">All Branches</option>
                                @foreach($branches as $br)
                                    <option value="{{ $br->id }}" {{ $branchId == $br->id ? 'selected' : '' }}>{{ $br->name }}</option>
                                @endforeach
                            </x-ui.modal-form-ui>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Flags & Preferences -->
            <div class="col-12">
                <div class="d-flex align-items-center flex-wrap gap-4 py-1">
                    <x-ui.modal-form-ui type="switch" label="Set as Default Configuration" name="is_default" id="configIsDefault" value="1" />
                    <x-ui.modal-form-ui type="switch" label="Auto-Generate IRN when Invoice is Posted" name="auto_generate_on_post" id="configAutoGenerate" value="1" />
                    <x-ui.modal-form-ui type="switch" label="Active" name="is_active" id="configIsActive" value="1" :checked="true" />
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-3">
            <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button variant="primary" type="submit" icon="feather-save">Save Configuration</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- TEST PING RESULT MODAL -->
<x-ui.modal id="testPingModal" title="<i class='feather-activity text-info me-1.5'></i>GSP Connection Test" size="md" :centered="true" :showFooter="false">
    <div class="p-3 text-center">
        <div id="pingStatusIcon" class="mb-2"></div>
        <h5 id="pingStatusTitle" class="fw-bold text-dark mb-2"></h5>
        <div id="pingStatusMsg" class="alert alert-light border fs-12 font-monospace p-2.5 mb-3 text-break"></div>
        <x-ui.button variant="primary" data-bs-dismiss="modal">Close</x-ui.button>
    </div>
</x-ui.modal>

@push('scripts')
<script>
    function resetGstForm() {
        $('#gstConfigForm')[0].reset();
        $('#configId').val('');
        $('#configProvider').val('setu').trigger('change');
        $('#configEnvironment').val('sandbox').trigger('change');
        $('#configAuthType').val('bearer_token').trigger('change');
        $('#configCompanyId').val('').trigger('change');
        $('#configBranchId').val('').trigger('change');
        $('#configIsActive').prop('checked', true);
        $('#configIsDefault').prop('checked', false);
        $('#configAutoGenerate').prop('checked', false);
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
            $('#configAuthType').val('api_key').trigger('change');
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
        $('#configProvider').val(c.provider).trigger('change');
        $('#configEnvironment').val(c.environment).trigger('change');
        $('#configAuthType').val(c.auth_type).trigger('change');
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
        $('#configCompanyId').val(c.company_id || '').trigger('change');
        $('#configBranchId').val(c.branch_id || '').trigger('change');
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
            url: '{{ url("platform/gst-settings") }}/' + id + '/test',
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
