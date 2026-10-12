@extends('layouts.duralux')

@section('title', __('crm.crm_settings') . ' | SaaS ERP')
@section('page-title', __('crm.crm_settings'))
@section('breadcrumb', __('crm.revenue_cycle') . ' / CRM / ' . __('crm.settings'))

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-settings me-2 text-primary"></i>{{ __('crm.crm_settings') }}</h4>
            <p class="text-muted fs-12 mb-0">Configure Lead Auto-Assignment, Round-Robin Distribution, Quotation Approvals & CRM Policies.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $activeTab = request()->query('tab', 'lead-assignment');
        $assignmentMode = $leadAssignmentConfig['mode'] ?? 'manual_creator';
        $selectedRrUsers = (array)($leadAssignmentConfig['round_robin_users'] ?? []);

        $settingsTabs = [
            [
                'id' => 'tab-lead-assignment',
                'label' => 'Lead Auto-Assignment & Round-Robin',
                'icon' => 'feather-user-check',
                'active' => in_array($activeTab, ['lead-assignment', 'lead_assignment', 'tab-lead-assignment']),
            ],
            [
                'id' => 'tab-quotation-approval',
                'label' => 'Quotation Approval Policy',
                'icon' => 'feather-check-square',
                'active' => in_array($activeTab, ['quotation-approval', 'approvals', 'tab-quotation-approval']),
            ],
            [
                'id' => 'tab-invoicing-policy',
                'label' => 'Invoicing & Sales Policy',
                'icon' => 'feather-file-text',
                'active' => in_array($activeTab, ['invoicing-policy', 'general', 'tab-invoicing-policy']),
            ],
        ];
    @endphp

    <!-- Standard ERP Common Component: Horizontal Tabs -->
    <x-ui.horizontal-tabs id="crmSettingsTabs" :tabs="$settingsTabs" syncUrl="true" syncParam="tab" class="mb-4 bg-white px-3 py-1 rounded-3 shadow-sm border" />

    <!-- Tab Contents -->
    <div class="tab-content" id="crmSettingsTabContent">
        <!-- ========================================== -->
        <!-- TAB 1: LEAD AUTO-ASSIGNMENT & ROUND-ROBIN -->
        <!-- ========================================== -->
        <div class="tab-pane fade @if(in_array($activeTab, ['lead-assignment', 'lead_assignment', 'tab-lead-assignment'])) show active @endif" id="tab-lead-assignment" role="tabpanel">
            <form action="{{ route('crm.settings.update-lead-assignment') }}" method="POST" id="leadAssignmentForm">
                @csrf
                <div class="row g-4">
                    <div class="col-lg-8">
                        <!-- Card 1: Assignment Mode -->
                        <div class="card border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">
                                        <i class="feather-users me-2 text-primary"></i>Lead Assignment & Distribution Mode
                                    </h6>
                                    <span class="text-muted fs-12">Define how new leads from Websites, Meta Ads, REST APIs, or Manual entry are assigned.</span>
                                </div>
                                <span class="badge bg-soft-primary text-primary fs-11 font-monospace">Automated Engine</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3 mb-4">
                                    <!-- Mode 1: Manual / Creator -->
                                    <div class="col-md-4">
                                        <div class="form-check custom-option-card border rounded-3 p-3 h-100 @if($assignmentMode === 'manual_creator') border-primary bg-soft-primary-light active-card @endif" data-mode="manual_creator">
                                            <input class="form-check-input mt-1" type="radio" name="mode" id="mode_manual_creator" value="manual_creator" @checked($assignmentMode === 'manual_creator')>
                                            <label class="form-check-label ms-2 cursor-pointer w-100" for="mode_manual_creator">
                                                <div class="fw-bold text-dark fs-14 mb-1">
                                                    <i class="feather-user me-1 text-primary"></i>Creator / Default User
                                                </div>
                                                <div class="text-muted fs-12">
                                                    Logged-in user becomes owner for manual entries. External leads use default fallback owner.
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Mode 2: Unassigned Pool -->
                                    <div class="col-md-4">
                                        <div class="form-check custom-option-card border rounded-3 p-3 h-100 @if($assignmentMode === 'unassigned') border-warning bg-soft-warning-light active-card @endif" data-mode="unassigned">
                                            <input class="form-check-input mt-1" type="radio" name="mode" id="mode_unassigned" value="unassigned" @checked($assignmentMode === 'unassigned')>
                                            <label class="form-check-label ms-2 cursor-pointer w-100" for="mode_unassigned">
                                                <div class="fw-bold text-dark fs-14 mb-1">
                                                    <i class="feather-inbox me-1 text-warning"></i>Unassigned Pool (Open Claim)
                                                </div>
                                                <div class="text-muted fs-12">
                                                    All incoming leads remain Unassigned. Sales reps can view and claim leads from the shared pool.
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Mode 3: Global Round-Robin -->
                                    <div class="col-md-4">
                                        <div class="form-check custom-option-card border rounded-3 p-3 h-100 @if($assignmentMode === 'round_robin') border-success bg-soft-success-light active-card @endif" data-mode="round_robin">
                                            <input class="form-check-input mt-1" type="radio" name="mode" id="mode_round_robin" value="round_robin" @checked($assignmentMode === 'round_robin')>
                                            <label class="form-check-label ms-2 cursor-pointer w-100" for="mode_round_robin">
                                                <div class="fw-bold text-dark fs-14 mb-1">
                                                    <i class="feather-rotate-cw me-1 text-success"></i>Round-Robin (Rotation)
                                                </div>
                                                <div class="text-muted fs-12">
                                                    Sequentially rotates leads one-by-one equally across all selected sales team members.
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dynamic Section: Round-Robin User Pool -->
                                <div id="section-round-robin" class="border rounded-3 p-3 mb-4 bg-light bg-opacity-50 @if($assignmentMode !== 'round_robin') d-none @endif">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-users me-1.5 text-success"></i>Round-Robin Sales Team Rotation Pool</h6>
                                            <span class="text-muted fs-11">Select which active team members should receive auto-distributed leads.</span>
                                        </div>
                                        <x-ui.button type="button" variant="outline-secondary" size="xs" id="selectAllRrUsersBtn">
                                            Select All
                                        </x-ui.button>
                                    </div>

                                    <div class="row g-2">
                                        @forelse($users as $u)
                                            <div class="col-md-6">
                                                <div class="form-check user-select-card d-flex align-items-center gap-2 p-2 border rounded bg-white">
                                                    <input class="form-check-input rr-user-checkbox ms-1" type="checkbox" name="round_robin_users[]" value="{{ $u->id }}" id="rr_user_{{ $u->id }}" @checked(in_array($u->id, $selectedRrUsers))>
                                                    <label class="form-check-label d-flex align-items-center gap-2 w-100 cursor-pointer mb-0" for="rr_user_{{ $u->id }}">
                                                        <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}" class="rounded-circle" style="width: 26px; height: 26px; object-fit: cover;">
                                                        <div class="text-truncate">
                                                            <div class="fw-semibold text-dark fs-12 text-truncate">{{ $u->name }}</div>
                                                            <div class="text-muted fs-10 text-truncate">{{ $u->email }}</div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="col-12 text-muted fs-12 py-2">No users found.</div>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- Default Fallback Owner -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark fs-13">Default Fallback Lead Owner</label>
                                    <p class="text-muted fs-12 mb-2">Used as the fallback recipient for unassigned external leads or when round-robin pool is empty.</p>
                                    <select name="default_owner_id" class="form-select fs-13" style="max-width: 380px;">
                                        <option value="">-- No Default Owner (Leave Unassigned) --</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" @selected(($leadAssignmentConfig['default_owner_id'] ?? null) == $u->id)>
                                                {{ $u->name }} ({{ $u->email }}) - {{ ucfirst($u->role ?? 'User') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Multi-Channel Execution Triggers -->
                                <div class="border-top pt-3">
                                    <label class="form-label fw-bold text-dark fs-13 mb-2">Auto-Assignment Channel Triggers</label>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="apply_on_web_forms" id="trigger_web_forms" value="1" @checked(!empty($leadAssignmentConfig['apply_on_web_forms']))>
                                            <label class="form-check-label fs-13 fw-semibold text-dark" for="trigger_web_forms">
                                                Apply on Web-to-Lead Forms & Floating Website Widget
                                            </label>
                                            <div class="text-muted fs-11">Automatically assign owner when visitors submit enquiry forms on your website.</div>
                                        </div>

                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="apply_on_api_imports" id="trigger_api_imports" value="1" @checked(!empty($leadAssignmentConfig['apply_on_api_imports']))>
                                            <label class="form-check-label fs-13 fw-semibold text-dark" for="trigger_api_imports">
                                                Apply on REST APIs, Meta/WhatsApp Webhooks & CSV Imports
                                            </label>
                                            <div class="text-muted fs-11">Automatically distribute leads arriving via third-party integrations and batch file imports.</div>
                                        </div>

                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="apply_on_manual_create" id="trigger_manual_create" value="1" @checked(!empty($leadAssignmentConfig['apply_on_manual_create']))>
                                            <label class="form-check-label fs-13 fw-semibold text-dark" for="trigger_manual_create">
                                                Apply on Manual Dashboard Creation (When Owner is Left Empty)
                                            </label>
                                            <div class="text-muted fs-11">If an agent creates a lead without picking an owner, apply the round-robin rules.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                                <x-ui.button type="submit" variant="primary" icon="feather-save">
                                    Save Lead Assignment Policy
                                </x-ui.button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Sidebar Info -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="feather-help-circle me-2 text-primary"></i>Lead Assignment Guide</h6>
                                <div class="d-flex flex-column gap-3 fs-12 text-muted">
                                    <div>
                                        <strong class="text-dark d-block mb-1"><i class="feather-rotate-cw me-1 text-success"></i> Round-Robin Mode:</strong>
                                        Leads are distributed fairly in turns (User A &rarr; User B &rarr; User C &rarr; User A). Perfect for sales teams handling inbound website traffic and Meta ads.
                                    </div>
                                    <div>
                                        <strong class="text-dark d-block mb-1"><i class="feather-inbox me-1 text-warning"></i> Open Pool (Unassigned):</strong>
                                        Leads enter a central queue where any available sales executive can claim or assign them manually.
                                    </div>
                                    <div>
                                        <strong class="text-dark d-block mb-1"><i class="feather-user me-1 text-primary"></i> Creator / Default User:</strong>
                                        For manual entries, the logged-in user is set as owner. External leads go to the configured default owner.
                                    </div>
                                    <div>
                                        <strong class="text-dark d-block mb-1"><i class="feather-clock me-1 text-primary"></i> Audit Trail:</strong>
                                        Every auto-assignment is timestamped and recorded in the lead's history timeline with the exact rotation reason.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Rules Preview Widget -->
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-dark mb-2 fs-13"><i class="feather-shield me-2 text-success"></i>Distribution Status</h6>
                                <div class="p-3 bg-light rounded-3 border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fs-12 text-muted">Current Mode:</span>
                                        <span class="badge bg-primary fs-11 text-uppercase">{{ str_replace('_', ' ', $assignmentMode) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fs-12 text-muted">Round-Robin Pool:</span>
                                        <span class="fw-semibold text-dark fs-12">{{ count($selectedRrUsers) }} Active Members</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: QUOTATION APPROVAL POLICY -->
        <!-- ========================================== -->
        <div class="tab-pane fade @if(in_array($activeTab, ['quotation-approval', 'approvals', 'tab-quotation-approval'])) show active @endif" id="tab-quotation-approval" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="feather-check-square me-2 text-primary"></i>{{ __('crm.quotation_approval_policy') }}
                            </h6>
                            <span class="badge bg-soft-info text-info font-monospace fs-11">{{ __('crm.approval_automation') }}</span>
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('crm.settings.update-quotation-approval-policy') }}" method="POST">
                                @csrf
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark fs-13">{{ __('crm.select_quotation_approval_mode') }}</label>
                                    <p class="text-muted fs-12 mb-3">
                                        {{ __('crm.controls_quotation_approval_desc') }}
                                    </p>

                                    <div class="row g-3">
                                        <!-- Option 1: Approval Required -->
                                        <div class="col-md-12">
                                            <div class="form-check custom-option-card border rounded-3 p-3 @if(($quotationApprovalPolicy ?? 'approval_required') === 'approval_required') border-primary bg-soft-primary-light @endif">
                                                <input class="form-check-input mt-1" type="radio" name="quotation_approval_policy" id="policy_approval_req" value="approval_required" @checked(($quotationApprovalPolicy ?? 'approval_required') === 'approval_required')>
                                                <label class="form-check-label ms-2 cursor-pointer w-100" for="policy_approval_req">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <span class="fw-bold text-dark fs-14">
                                                            <i class="feather-shield me-1.5 text-primary"></i>{{ __('crm.require_approval_standard') }}
                                                        </span>
                                                        <span class="badge bg-soft-primary text-primary fs-11">{{ __('crm.multi_stage_approval') }}</span>
                                                    </div>
                                                    <div class="text-muted fs-12 mt-1">
                                                        {{ __('crm.require_approval_desc') }}
                                                    </div>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Option 2: Auto-Approve -->
                                        <div class="col-md-12">
                                            <div class="form-check custom-option-card border rounded-3 p-3 @if(($quotationApprovalPolicy ?? 'approval_required') === 'auto_approve') border-success bg-soft-success-light @endif">
                                                <input class="form-check-input mt-1" type="radio" name="quotation_approval_policy" id="policy_auto_approve" value="auto_approve" @checked(($quotationApprovalPolicy ?? 'approval_required') === 'auto_approve')>
                                                <label class="form-check-label ms-2 cursor-pointer w-100" for="policy_auto_approve">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <span class="fw-bold text-dark fs-14">
                                                            <i class="feather-check-circle me-1.5 text-success"></i>{{ __('crm.auto_approve_direct') }}
                                                        </span>
                                                        <span class="badge bg-soft-success text-success fs-11">{{ __('crm.fast_track_mode') }}</span>
                                                    </div>
                                                    <div class="text-muted fs-12 mt-1">
                                                        {{ __('crm.auto_approve_desc') }}
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end pt-3 border-top">
                                    <x-ui.button type="submit" variant="primary" icon="feather-save">
                                        {{ __('crm.save_approval_policy') }}
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="feather-help-circle me-2 text-info"></i>{{ __('crm.approval_policy_summary') }}</h6>
                            <p class="text-muted fs-12 leading-relaxed mb-3">
                                {{ __('crm.configure_approval_intro') }}
                            </p>
                            <ul class="text-muted fs-12 ps-3 mb-0">
                                <li class="mb-2"><strong>{{ __('crm.standard_approval_colon') }}</strong> {{ __('crm.standard_approval_summary_desc') }}</li>
                                <li><strong>{{ __('crm.auto_approve_colon') }}</strong> {{ __('crm.auto_approve_summary_desc') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: INVOICING & SALES POLICY -->
        <!-- ========================================== -->
        <div class="tab-pane fade @if(in_array($activeTab, ['invoicing-policy', 'general', 'tab-invoicing-policy'])) show active @endif" id="tab-invoicing-policy" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="feather-file-text me-2 text-primary"></i>Sales Invoicing Trigger Policy
                            </h6>
                            <span class="badge bg-soft-primary text-primary font-monospace fs-11">Billing Pipeline</span>
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('crm.settings.update-invoicing-policy') }}" method="POST">
                                @csrf
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark fs-13">Invoice Creation Point</label>
                                    <p class="text-muted fs-12 mb-3">Choose the ERP milestone at which sales invoices can be generated.</p>

                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <div class="form-check custom-option-card border rounded-3 p-3 @if(($invoicingPolicy ?? 'sales_order') === 'sales_order') border-primary bg-soft-primary-light @endif">
                                                <input class="form-check-input mt-1" type="radio" name="invoicing_policy" id="inv_policy_so" value="sales_order" @checked(($invoicingPolicy ?? 'sales_order') === 'sales_order')>
                                                <label class="form-check-label ms-2 cursor-pointer w-100" for="inv_policy_so">
                                                    <div class="fw-bold text-dark fs-14">From Confirmed Sales Order</div>
                                                    <div class="text-muted fs-12 mt-1">Invoices are created directly upon confirming customer sales orders.</div>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-check custom-option-card border rounded-3 p-3 @if(($invoicingPolicy ?? 'sales_order') === 'dispatch_order') border-primary bg-soft-primary-light @endif">
                                                <input class="form-check-input mt-1" type="radio" name="invoicing_policy" id="inv_policy_dispatch" value="dispatch_order" @checked(($invoicingPolicy ?? 'sales_order') === 'dispatch_order')>
                                                <label class="form-check-label ms-2 cursor-pointer w-100" for="inv_policy_dispatch">
                                                    <div class="fw-bold text-dark fs-14">From Dispatch Order (Post-Fulfillment)</div>
                                                    <div class="text-muted fs-12 mt-1">Invoices are only generated after dispatch/delivery challan is completed.</div>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-check custom-option-card border rounded-3 p-3 @if(($invoicingPolicy ?? 'sales_order') === 'both') border-primary bg-soft-primary-light @endif">
                                                <input class="form-check-input mt-1" type="radio" name="invoicing_policy" id="inv_policy_both" value="both" @checked(($invoicingPolicy ?? 'sales_order') === 'both')>
                                                <label class="form-check-label ms-2 cursor-pointer w-100" for="inv_policy_both">
                                                    <div class="fw-bold text-dark fs-14">Flexible (Both Modes Supported)</div>
                                                    <div class="text-muted fs-12 mt-1">Allow generating invoice from both Sales Orders and Delivery Dispatches.</div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end pt-3 border-top">
                                    <x-ui.button type="submit" variant="primary" icon="feather-save">
                                        Save Invoicing Policy
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.custom-option-card {
    transition: all 0.2s ease-in-out;
    cursor: pointer;
}
.custom-option-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transform: translateY(-1px);
}
.bg-soft-primary-light {
    background-color: rgba(37, 99, 235, 0.04);
}
.bg-soft-success-light {
    background-color: rgba(22, 163, 74, 0.04);
}
.bg-soft-warning-light {
    background-color: rgba(217, 119, 6, 0.04);
}
.user-select-card {
    transition: border-color 0.2s, background-color 0.2s;
}
.user-select-card:hover {
    border-color: #cbd5e1 !important;
    background-color: #f8fafc !important;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Mode Switcher for Lead Auto-Assignment
    const modeRadios = document.querySelectorAll('input[name="mode"]');
    const rrSection = document.getElementById('section-round-robin');

    function updateModeVisibility() {
        const selected = document.querySelector('input[name="mode"]:checked')?.value;
        
        // Highlight active cards
        document.querySelectorAll('.custom-option-card[data-mode]').forEach(card => {
            card.classList.remove('border-primary', 'border-success', 'border-warning', 'bg-soft-primary-light', 'bg-soft-success-light', 'bg-soft-warning-light');
            if (card.dataset.mode === selected) {
                if (selected === 'manual_creator') card.classList.add('border-primary', 'bg-soft-primary-light');
                if (selected === 'round_robin') card.classList.add('border-success', 'bg-soft-success-light');
                if (selected === 'unassigned') card.classList.add('border-warning', 'bg-soft-warning-light');
            }
        });

        if (rrSection) {
            if (selected === 'round_robin') {
                rrSection.classList.remove('d-none');
            } else {
                rrSection.classList.add('d-none');
            }
        }
    }

    modeRadios.forEach(r => r.addEventListener('change', updateModeVisibility));

    // Custom card click
    document.querySelectorAll('.custom-option-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT') {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            }
        });
    });

    // Select all round-robin users
    const selectAllBtn = document.getElementById('selectAllRrUsersBtn');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.rr-user-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            selectAllBtn.textContent = allChecked ? 'Select All' : 'Deselect All';
        });
    }
});
</script>
@endpush
