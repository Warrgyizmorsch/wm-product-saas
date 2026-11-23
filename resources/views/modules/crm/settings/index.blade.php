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
            <p class="text-muted fs-12 mb-0">Configure Lead Auto-Assignment, Round-Robin Distribution & Quotation Approvals.</p>
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
                'id' => 'tab-field-visit',
                'label' => 'Field Visits & Geo-Verification',
                'icon' => 'feather-map-pin',
                'active' => in_array($activeTab, ['field-visit', 'field_visit', 'tab-field-visit']),
            ],
            [
                'id' => 'tab-lead-sla-policy',
                'label' => 'Response SLA & Inactivity Policy',
                'icon' => 'feather-clock',
                'active' => in_array($activeTab, ['lead-sla-policy', 'lead_sla_policy', 'tab-lead-sla-policy']),
            ],
            [
                'id' => 'tab-contact-roles',
                'label' => 'Contact Roles',
                'icon' => 'feather-tag',
                'active' => in_array($activeTab, ['contact-roles', 'contact_roles', 'tab-contact-roles']),
            ],
            [
                'id' => 'tab-loss-reasons',
                'label' => 'Loss Reasons',
                'icon' => 'feather-x-circle',
                'active' => in_array($activeTab, ['loss-reasons', 'loss_reasons', 'tab-loss-reasons']),
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

                                <!-- FRS 2.5 step 3: routing rules -->
                                <div class="border rounded-3 p-3 mb-4">
                                    <h6 class="fw-bold text-dark fs-13 mb-1"><i class="feather-git-branch me-1 text-primary"></i>Routing Rules (territory, segment, product, source, workload)</h6>
                                    <p class="text-muted fs-12 mb-3">The first matching rule picks the owner from its pool. Rules run before the mode above; leave empty to use the mode only.</p>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="prefer_account_owner" id="prefer_account_owner" value="1" @checked(!empty($leadAssignmentConfig['prefer_account_owner']))>
                                        <label class="form-check-label fs-13 fw-semibold" for="prefer_account_owner">Route a lead from an existing account to that account's owner</label>
                                    </div>
                                    <div id="assignmentRules">
                                        @foreach((array) ($leadAssignmentConfig['rules'] ?? []) as $ri => $rule)
                                            <div class="assignment-rule border rounded p-2 mb-2 bg-light">
                                                <div class="row g-2">
                                                    <div class="col-md-3"><input class="form-control form-control-sm" name="rules[{{ $ri }}][name]" placeholder="Rule name" value="{{ $rule['name'] ?? '' }}"></div>
                                                    <div class="col-md-3"><input class="form-control form-control-sm" name="rules[{{ $ri }}][territory]" placeholder="Territory (state/city/country)" value="{{ $rule['territory'] ?? '' }}"></div>
                                                    <div class="col-md-2"><input class="form-control form-control-sm" name="rules[{{ $ri }}][segment]" placeholder="Segment" value="{{ $rule['segment'] ?? '' }}"></div>
                                                    <div class="col-md-2"><input class="form-control form-control-sm" name="rules[{{ $ri }}][source]" placeholder="Source" value="{{ $rule['source'] ?? '' }}"></div>
                                                    <div class="col-md-2"><input class="form-control form-control-sm" name="rules[{{ $ri }}][product]" placeholder="Product keyword" value="{{ $rule['product'] ?? '' }}"></div>
                                                    <div class="col-md-3"><select class="form-select form-select-sm" name="rules[{{ $ri }}][strategy]">
                                                        @foreach(['least_loaded' => 'Least open leads (workload)', 'round_robin' => 'Round-robin', 'first' => 'First in pool'] as $sv => $sl)
                                                            <option value="{{ $sv }}" @selected(($rule['strategy'] ?? 'least_loaded') === $sv)>{{ $sl }}</option>
                                                        @endforeach
                                                    </select></div>
                                                    <div class="col-md-7"><select class="form-select form-select-sm" name="rules[{{ $ri }}][owner_ids][]" multiple size="3">
                                                        @foreach($users as $u)
                                                            <option value="{{ $u->id }}" @selected(in_array($u->id, (array) ($rule['owner_ids'] ?? [])))>{{ $u->name }}</option>
                                                        @endforeach
                                                    </select></div>
                                                    <div class="col-md-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-rule">Remove</button></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addAssignmentRule">+ Add rule</button>
                                    <template id="assignmentRuleTpl">
                                        <div class="assignment-rule border rounded p-2 mb-2 bg-light">
                                            <div class="row g-2">
                                                <div class="col-md-3"><input class="form-control form-control-sm" name="rules[__I__][name]" placeholder="Rule name"></div>
                                                <div class="col-md-3"><input class="form-control form-control-sm" name="rules[__I__][territory]" placeholder="Territory (state/city/country)"></div>
                                                <div class="col-md-2"><input class="form-control form-control-sm" name="rules[__I__][segment]" placeholder="Segment"></div>
                                                <div class="col-md-2"><input class="form-control form-control-sm" name="rules[__I__][source]" placeholder="Source"></div>
                                                <div class="col-md-2"><input class="form-control form-control-sm" name="rules[__I__][product]" placeholder="Product keyword"></div>
                                                <div class="col-md-3"><select class="form-select form-select-sm" name="rules[__I__][strategy]"><option value="least_loaded">Least open leads (workload)</option><option value="round_robin">Round-robin</option><option value="first">First in pool</option></select></div>
                                                <div class="col-md-7"><select class="form-select form-select-sm" name="rules[__I__][owner_ids][]" multiple size="3">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                                                <div class="col-md-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-rule">Remove</button></div>
                                            </div>
                                        </div>
                                    </template>
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
        <!-- TAB 3: FIELD VISITS & GEO-VERIFICATION    -->
        <!-- ========================================== -->
        <div class="tab-pane fade @if(in_array($activeTab, ['field-visit', 'field_visit', 'tab-field-visit'])) show active @endif" id="tab-field-visit" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="feather-map-pin me-2 text-primary"></i>Meeting & Field Visit Geo-Location Verification
                                </h6>
                                <span class="text-muted fs-12">Control whether sales executives must verify physical location and take a selfie when completing meetings.</span>
                            </div>
                            <span class="badge @if($fieldVisitVerification) bg-soft-success text-success @else bg-soft-secondary text-secondary @endif fs-11 font-monospace">
                                {{ $fieldVisitVerification ? 'Active' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('crm.settings.update-field-visit-verification') }}" method="POST">
                                @csrf
                                <div class="p-3 border rounded-3 mb-4 @if($fieldVisitVerification) bg-soft-success-light border-success @else bg-light border-secondary-subtle @endif">
                                    <div class="form-check form-switch d-flex align-items-center gap-3 ps-0 mb-0">
                                        <input class="form-check-input ms-0 fs-4" type="checkbox" role="switch" id="fieldVisitVerificationSwitch" name="field_visit_verification" value="1" @checked($fieldVisitVerification) style="cursor: pointer;">
                                        <label class="form-check-label mb-0" for="fieldVisitVerificationSwitch" style="cursor: pointer;">
                                            <span class="fw-bold fs-14 text-dark d-block">Require Geo-Location & Live Photo for Meeting Outcome</span>
                                            <span class="text-muted fs-12">
                                                When enabled, sales executives will be prompted for real-time GPS location and must capture a live photo before marking meetings as <strong>Connected</strong> or <strong>Not Connected</strong>.
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 h-100 bg-white">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <div class="rounded-circle p-1.5 bg-soft-secondary text-secondary d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                    <i class="feather-x fs-13"></i>
                                                </div>
                                                <span class="fw-bold fs-13 text-dark">Disabled (Default Policy)</span>
                                            </div>
                                            <p class="text-muted fs-12 mb-0">
                                                Meetings can be marked as Connected or Not Connected instantly. No mandatory GPS coordinate or photo validation is enforced.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded-3 h-100 bg-white">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <div class="rounded-circle p-1.5 bg-soft-success text-success d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                    <i class="feather-shield fs-13"></i>
                                                </div>
                                                <span class="fw-bold fs-13 text-dark">Enabled (Field Verification)</span>
                                            </div>
                                            <p class="text-muted fs-12 mb-0">
                                                Enforces live GPS location coordinates and front-camera selfie capture. Guarantees physical presence and eliminates fake reporting.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mappls (MapmyIndia) Integration Configuration -->
                                <div class="p-3 border rounded-3 mb-4 bg-light">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <label class="form-label fw-bold fs-13 text-dark mb-0 d-flex align-items-center gap-1.5">
                                            <i class="feather-map-pin text-primary fs-14"></i> Mappls (MapmyIndia) API Key & Search Integration
                                        </label>
                                        <a href="https://about.mappls.com/api/" target="_blank" class="btn btn-xs btn-outline-primary fw-semibold px-2 py-0.5 d-inline-flex align-items-center gap-1">
                                            <i class="feather-external-link fs-10"></i> Get Free Mappls Key (No Card Required)
                                        </a>
                                    </div>
                                    <p class="text-muted fs-12 mb-3">
                                        Enter your tenant's free Mappls API key below. This enables pinpoint address autocomplete for Indian offices, complexes, landmarks (e.g. Ashoka Palace, Sobhagpura) and renders the interactive map with accurate coordinates.
                                    </p>
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label class="form-label fs-11 fw-semibold text-secondary mb-1">Mappls API Key / Vector SDK Key</label>
                                            <input type="text" name="mappls_api_key" class="form-control form-control-sm font-monospace fs-12 bg-white" placeholder="e.g. your_mappls_api_key_here" value="{{ $settings['mappls_api_key'] ?? '' }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fs-11 fw-semibold text-secondary mb-1">Allowed Geo-Fence Radius (Meters)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" name="geofence_radius_meters" class="form-control fs-12 bg-white" min="50" max="10000" placeholder="500" value="{{ $settings['geofence_radius_meters'] ?? 500 }}">
                                                <span class="input-group-text bg-white fs-11 text-muted">Meters</span>
                                            </div>
                                            <small class="text-muted fs-10 d-block mt-0.5">Permitted radius when verifying presence on Mark Connected.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                                        <i class="feather-save me-1"></i>Save Verification Policy
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="feather-info me-2 text-info"></i>How It Works</h6>
                            <ul class="text-muted fs-12 ps-3 mb-0">
                                <li class="mb-2"><strong>Database Non-Breaking:</strong> Location and photo fields in database are nullable, ensuring zero disruption to existing activities.</li>
                                <li class="mb-2"><strong>Controller Enforcement:</strong> When enabled, server rejects meeting submissions missing GPS coordinates or selfie.</li>
                                <li class="mb-2"><strong>Live Camera Capture:</strong> Mobile browser triggers the front-camera directly, blocking gallery photo uploads.</li>
                                <li><strong>Manager Verification:</strong> Check location coordinates with direct Google Maps link and photo evidence in Activity History.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: CONTACT ROLES (FRS 2.3 / C-17)      -->
        <!-- ========================================== -->
        <div class="tab-pane fade @if(in_array($activeTab, ['contact-roles', 'contact_roles', 'tab-contact-roles'])) show active @endif" id="tab-contact-roles" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-dark"><i class="feather-tag me-2 text-primary"></i>Customer &amp; Vendor Contact Roles</h6>
                            <span class="text-muted fs-12">Roles assigned to customer and vendor contacts (a single contact can carry multiple roles).</span>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 fs-13">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Role Name</th>
                                            <th>Type</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($contactRoles ?? []) as $role)
                                            <tr>
                                                <td class="fw-semibold">{{ $role->name }}</td>
                                                <td>
                                                    @if($role->is_system)
                                                        <span class="badge bg-soft-primary text-primary fs-11">System Default</span>
                                                    @else
                                                        <span class="badge bg-soft-success text-success fs-11">Custom</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    @if(! $role->is_system)
                                                        <form method="POST" action="{{ route('crm.settings.contact-roles.destroy', $role->id) }}" class="d-inline" onsubmit="return confirm('Delete custom role {{ addslashes($role->name) }}?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-xs btn-outline-danger"><i class="feather-trash-2"></i> Delete</button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted fs-11">Protected</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-center text-muted py-4">No contact roles configured.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-dark"><i class="feather-plus me-2 text-primary"></i>Add Custom Role</h6>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="{{ route('crm.settings.contact-roles.store') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold">Role Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control form-control-sm" maxlength="100" required placeholder="e.g. Facility Manager">
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary w-100">Add Contact Role</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ========================================== -->
        <!-- TAB 5: LEAD RESPONSE SLA & STALE POLICY   -->
        <!-- ========================================== -->
        <div class="tab-pane fade @if(in_array($activeTab, ['lead-sla-policy', 'lead_sla_policy', 'tab-lead-sla-policy'])) show active @endif" id="tab-lead-sla-policy" role="tabpanel">
            <form action="{{ route('crm.settings.update-lead-sla-policy') }}" method="POST">
                @csrf
                <div class="row g-4">
                    <div class="col-lg-8">
                        <!-- SLA Configuration Card -->
                        <div class="card border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">
                                        <i class="feather-clock me-2 text-primary"></i>First Response SLA Clock (FRS Ref 2.5 / C-01)
                                    </h6>
                                    <span class="text-muted fs-12">Tracks time between lead assignment and first customer interaction. Overdue touches escalate to the Sales Manager.</span>
                                </div>
                                <span class="badge bg-soft-info text-info font-monospace fs-11">SLA Engine</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fs-13 fw-semibold text-dark">
                                            First Response SLA Window (Minutes) <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" name="response_sla_minutes" class="form-control" value="{{ $leadSlaPolicy['response_sla_minutes'] ?? 60 }}" min="5" max="1440" required>
                                            <span class="input-group-text fs-12">Minutes</span>
                                        </div>
                                        <div class="form-text fs-11 text-muted">Default is 60 minutes. Once exceeded without an interaction, the lead is flagged as SLA Breached.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fs-13 fw-semibold text-dark">Default Loss Nurture Horizon (Days) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" name="default_nurture_days" class="form-control" value="{{ $leadSlaPolicy['default_nurture_days'] ?? 90 }}" min="7" max="365" required>
                                            <span class="input-group-text fs-12">Days</span>
                                        </div>
                                        <div class="form-text fs-11 text-muted">Auto-schedules a nurture outreach task when a lead is marked Closed Lost.</div>
                                    </div>
                                </div>

                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="sla_working_hours_only" id="sla_working_hours_only" value="1" @checked(!empty($leadSlaPolicy['sla_working_hours_only']))>
                                    <label class="form-check-label fw-semibold text-dark fs-13" for="sla_working_hours_only">
                                        Count SLA Only During Business Working Hours (9:00 AM - 7:00 PM)
                                    </label>
                                    <div class="form-text fs-11 text-muted">Pauses the SLA response clock overnight and on weekends.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Stale Lead & Inactivity Policy Card -->
                        <div class="card border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">
                                        <i class="feather-alert-circle me-2 text-warning"></i>Next-Action Mandate & Stale Lead Alerts (C-02)
                                    </h6>
                                    <span class="text-muted fs-12">Monitors open leads without an active scheduled next action or with overdue activity.</span>
                                </div>
                                <span class="badge bg-soft-warning text-warning font-monospace fs-11">Governance</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fs-13 fw-semibold text-dark">
                                            Stale Inactivity Window (Days) <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" name="stale_lead_days" class="form-control" value="{{ $leadSlaPolicy['stale_lead_days'] ?? 3 }}" min="1" max="90" required>
                                            <span class="input-group-text fs-12">Days</span>
                                        </div>
                                        <div class="form-text fs-11 text-muted">Leads with no scheduled next action or zero interactions for this duration alert the owner and manager.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fs-13 fw-semibold text-dark">
                                            Repeated Alert Cadence (Days) <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" name="stale_alert_cadence_days" class="form-control" value="{{ $leadSlaPolicy['stale_alert_cadence_days'] ?? 2 }}" min="1" max="30" required>
                                            <span class="input-group-text fs-12">Days</span>
                                        </div>
                                        <div class="form-text fs-11 text-muted">Repeat notification cadence while an open lead remains uncontacted/stale.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Inbound Enquiry Auto-Acknowledgment Card -->
                        <div class="card border-0 shadow-sm rounded-3 mb-4">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">
                                        <i class="feather-send me-2 text-success"></i>Inbound Enquiry Auto-Acknowledgment (C-05)
                                    </h6>
                                    <span class="text-muted fs-12">Immediate greeting and confirmation sent to prospective clients upon form submission.</span>
                                </div>
                                <span class="badge bg-soft-success text-success font-monospace fs-11">Inbound Channel</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="auto_ack_inbound" id="auto_ack_inbound" value="1" @checked(!empty($leadSlaPolicy['auto_ack_inbound']))>
                                    <label class="form-check-label fw-semibold text-dark fs-13" for="auto_ack_inbound">
                                        Enable Instant Acknowledgment on Web & Inbound Form Submissions
                                    </label>
                                    <div class="form-text fs-11 text-muted">Fires exclusively for real inbound inquiries. Suppressed for cold / AI-researched prospect imports.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold fs-13">Acknowledgement message template</label>
                                    <textarea name="ack_template" rows="3" class="form-control fs-13" maxlength="600" placeholder="Hi {name}, thank you for contacting {brand}! We have received your enquiry. {owner} will connect with you shortly.">{{ old('ack_template', $leadSlaPolicy['ack_template'] ?? '') }}</textarea>
                                    <div class="form-text fs-11 text-muted">Placeholders: {name}, {brand}, {owner}, {company}. Never sent to prospects flagged Do-not-contact, or to imported / researched / API leads.</div>
                                    <input type="text" name="ack_subject" class="form-control fs-13 mt-2" maxlength="150" placeholder="Email subject (optional)" value="{{ old('ack_subject', $leadSlaPolicy['ack_subject'] ?? '') }}">
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_ack_whatsapp" id="auto_ack_whatsapp" value="1" @checked(!empty($leadSlaPolicy['auto_ack_whatsapp']))>
                                    <label class="form-check-label fw-semibold text-dark fs-13" for="auto_ack_whatsapp">
                                        Send Instant WhatsApp Greeting When Customer Phone is Provided
                                    </label>
                                    <div class="form-text fs-11 text-muted">Uses tenant WhatsApp Business gateway to confirm receipt and provide executive details.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-end">
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                                <i class="feather-save me-1"></i>Save SLA & Inactivity Policy
                            </button>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-3 bg-light">
                            <div class="card-header bg-transparent py-3 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="feather-info me-2 text-primary"></i>FRS Ref 2.5 Compliance
                                </h6>
                            </div>
                            <div class="card-body p-3 fs-12 text-muted">
                                <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                                    <li><i class="feather-check text-success me-1"></i><strong>Response SLA:</strong> Clock starts on lead assignment; stops at first customer touch.</li>
                                    <li><i class="feather-check text-success me-1"></i><strong>Next Action Rule:</strong> Open leads require an accountable owner and due date.</li>
                                    <li><i class="feather-check text-success me-1"></i><strong>Stage Gating:</strong> Moving to Dealing requires verified BANT qualification.</li>
                                    <li><i class="feather-check text-success me-1"></i><strong>Loss Capture:</strong> Mandatory structured reason and future nurture reminder.</li>
                                    <li><i class="feather-check text-success me-1"></i><strong>Manufacturing Isolation (BASE-05):</strong> CRM Won never creates production demand.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ========================================== -->
        <!-- TAB 6: LOSS REASONS (FRS 2.5 / C-04)       -->
        <!-- ========================================== -->
        <div class="tab-pane fade @if(in_array($activeTab, ['loss-reasons', 'loss_reasons', 'tab-loss-reasons'])) show active @endif" id="tab-loss-reasons" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="feather-x-circle me-2 text-danger"></i>Structured Loss Reasons Master
                                </h6>
                                <span class="text-muted fs-12">Standardized reasons enforced when marking Leads or Deals as Closed Lost, with auto-nurture cadence.</span>
                            </div>
                            <span class="badge bg-soft-danger text-danger fs-11 font-monospace">FRS Ref 2.5 / C-04</span>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 fs-13">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Reason Name</th>
                                            <th>Competitor Required</th>
                                            <th>Nurture Cadence</th>
                                            <th>Scope</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($lossReasons ?? []) as $reason)
                                            <tr>
                                                <td class="fw-semibold">
                                                    <div class="text-dark">{{ $reason->name }}</div>
                                                    <span class="text-muted fs-11 font-monospace">{{ $reason->code ?: '—' }}</span>
                                                </td>
                                                <td>
                                                    @if($reason->requires_competitor)
                                                        <span class="badge bg-soft-warning text-warning fs-11"><i class="feather-alert-triangle me-1"></i>Yes</span>
                                                    @else
                                                        <span class="text-muted fs-12">No</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border fs-11">
                                                        <i class="feather-calendar me-1 text-primary"></i>{{ $reason->follow_up_days ?? 30 }} Days
                                                    </span>
                                                </td>
                                                <td>
                                                    @if(empty($reason->tenant_id))
                                                        <span class="badge bg-soft-primary text-primary fs-11">System Default</span>
                                                    @else
                                                        <span class="badge bg-soft-success text-success fs-11">Custom Tenant</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <form method="POST" action="{{ route('crm.settings.loss-reasons.toggle', $reason->id) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="badge border-0 cursor-pointer @if($reason->is_active) bg-soft-success text-success @else bg-soft-secondary text-muted @endif fs-11" title="Click to toggle status">
                                                            <i class="feather-{{ $reason->is_active ? 'check-circle' : 'slash' }} me-1"></i>{{ $reason->is_active ? 'Active' : 'Inactive' }}
                                                        </button>
                                                    </form>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-1">
                                                        <!-- Edit Button (Modal) -->
                                                        <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editLossReasonModal{{ $reason->id }}" title="Edit Reason">
                                                            <i class="feather-edit-2"></i>
                                                        </button>

                                                        @if(!empty($reason->tenant_id))
                                                            <form method="POST" action="{{ route('crm.settings.loss-reasons.destroy', $reason->id) }}" class="d-inline" onsubmit="return confirm('Delete loss reason {{ addslashes($reason->name) }}?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete Reason">
                                                                    <i class="feather-trash-2"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>

                                                    <!-- Edit Modal -->
                                                    <div class="modal fade" id="editLossReasonModal{{ $reason->id }}" tabindex="-1" aria-hidden="true" style="text-align: left;">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content border-0 shadow">
                                                                <form method="POST" action="{{ route('crm.settings.loss-reasons.update', $reason->id) }}">
                                                                    @csrf
                                                                    @method('PUT')
                                                                    <div class="modal-header border-bottom py-3">
                                                                        <h6 class="modal-title fw-bold text-dark"><i class="feather-edit me-2 text-primary"></i>Edit Loss Reason</h6>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body p-4 fs-13">
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-semibold text-dark">Reason Name <span class="text-danger">*</span></label>
                                                                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $reason->name }}" required maxlength="150">
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-semibold text-dark">Follow-up Days (Nurture)</label>
                                                                            <div class="input-group input-group-sm">
                                                                                <input type="number" name="follow_up_days" class="form-control" value="{{ $reason->follow_up_days ?? 30 }}" min="1" max="365">
                                                                                <span class="input-group-text">Days</span>
                                                                            </div>
                                                                            <div class="form-text fs-11 text-muted">Auto-schedules nurture task after this many days.</div>
                                                                        </div>
                                                                        <div class="form-check form-switch mb-3">
                                                                            <input class="form-check-input" type="checkbox" name="requires_competitor" id="edit_req_comp_{{ $reason->id }}" value="1" @checked($reason->requires_competitor)>
                                                                            <label class="form-check-label fw-semibold text-dark" for="edit_req_comp_{{ $reason->id }}">
                                                                                Mandatory Competitor Name
                                                                            </label>
                                                                        </div>
                                                                        <div class="form-check form-switch mb-2">
                                                                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active_{{ $reason->id }}" value="1" @checked($reason->is_active)>
                                                                            <label class="form-check-label fw-semibold text-dark" for="edit_is_active_{{ $reason->id }}">
                                                                                Active
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer border-top py-2 bg-light">
                                                                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No loss reasons configured.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Card: Add New Loss Reason -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-dark"><i class="feather-plus me-2 text-primary"></i>Add Loss Reason</h6>
                            <span class="text-muted fs-12">Create a new structured reason for Closed Lost deals.</span>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="{{ route('crm.settings.loss-reasons.store') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">Reason Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control form-control-sm" maxlength="150" required placeholder="e.g. Price Too High">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fs-12 fw-semibold text-dark">Follow-up Days (Nurture)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="follow_up_days" class="form-control" value="30" min="1" max="365">
                                        <span class="input-group-text fs-11">Days</span>
                                    </div>
                                    <div class="form-text fs-11 text-muted">Auto-schedules a nurture reminder after these many days.</div>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="requires_competitor" id="new_req_comp" value="1">
                                    <label class="form-check-label fs-12 fw-semibold text-dark" for="new_req_comp">
                                        Competitor Name Required
                                    </label>
                                    <div class="form-text fs-11 text-muted">Mandates entering competitor name when this reason is selected.</div>
                                </div>
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="new_is_active" value="1" checked>
                                    <label class="form-check-label fs-12 fw-semibold text-dark" for="new_is_active">
                                        Is Active
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                                    <i class="feather-save me-1"></i>Save Reason
                                </button>
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

    // FRS 2.5 routing rules editor
    (function () {
        const wrap = document.getElementById('assignmentRules');
        const tpl = document.getElementById('assignmentRuleTpl');
        const addBtn = document.getElementById('addAssignmentRule');
        if (!wrap || !tpl || !addBtn) return;
        let idx = wrap.querySelectorAll('.assignment-rule').length + 100;
        addBtn.addEventListener('click', () => {
            wrap.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__I__', String(idx++)));
        });
        wrap.addEventListener('click', e => {
            if (e.target.classList.contains('remove-rule')) e.target.closest('.assignment-rule').remove();
        });
    })();

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
