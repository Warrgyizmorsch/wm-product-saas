@extends('layouts.duralux')

@section('title', __('meta.page_meta_title'))
@section('page-title', __('meta.page_title'))
@section('breadcrumb', __('meta.breadcrumb'))

@section('page-actions')
    <x-ui.button href="https://adsmanager.facebook.com" target="_blank" variant="outline-primary" icon="feather-external-link">
        {{ __('meta.open_ads_manager') }}
    </x-ui.button>
    <x-ui.button href="https://developers.facebook.com/tools/lead-ads-testing" target="_blank" variant="outline-success" icon="feather-check-circle">
        {{ __('meta.lead_testing_tool') }}
    </x-ui.button>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
    <!-- Header Context Strip -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 pb-3 border-bottom">
        <div>
            <h5 class="fw-bold text-dark mb-1">
                <i class="feather-globe text-primary me-2"></i>{{ __('meta.console_title') }}
            </h5>
            <p class="text-muted fs-12 mb-0">{{ __('meta.console_subtitle') }}</p>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2 fs-12">
            <span class="fw-bold text-secondary me-1"><i class="feather-layers me-1"></i>{{ __('meta.current_context') }}:</span>
            <span class="badge erp-badge bg-primary text-white"><i class="feather-globe me-1"></i>{{ __('meta.tenant_id') }}: {{ $tenantId }}</span>
            <span class="badge erp-badge bg-info text-dark"><i class="feather-briefcase me-1"></i>{{ __('meta.company') }}: {{ $companyId ?: __('meta.all_companies') }}</span>
            <span class="badge erp-badge bg-secondary text-white"><i class="feather-git-branch me-1"></i>{{ __('meta.branch') }}: {{ $branchId ?: __('meta.all_branches') }}</span>
        </div>
    </div>

    @if(session('success'))
        <x-ui.toast :auto="true" title="{{ session('success') }}" type="success" delay="5000" />
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
            <div class="fw-bold mb-1"><i class="feather-alert-triangle me-1"></i>{{ __('meta.please_fix_errors') }}</div>
            <ul class="mb-0 ps-3 fs-13">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $metaTabs = [
            [
                'id' => 'tab-settings',
                'label' => __('meta.tab_credentials'),
                'icon' => 'feather-sliders',
                'active' => true,
            ],
            [
                'id' => 'tab-structure',
                'label' => __('meta.tab_structure'),
                'icon' => 'feather-target',
                'active' => false,
            ],
            [
                'id' => 'tab-token-guide',
                'label' => __('meta.tab_token_guide'),
                'icon' => 'feather-key',
                'active' => false,
            ],
            [
                'id' => 'tab-testing',
                'label' => __('meta.tab_testing'),
                'icon' => 'feather-check-square',
                'active' => false,
            ],
            [
                'id' => 'tab-logs',
                'label' => __('meta.tab_logs'),
                'icon' => 'feather-activity',
                'active' => false,
                'count' => count($recentLogs) ?: null,
            ],
        ];
    @endphp

    <!-- Common Horizontal Tabs Component -->
    <x-ui.horizontal-tabs id="metaTabs" :tabs="$metaTabs" class="mb-4" />

    <div class="tab-content" id="metaTabsContent">
        <!-- TAB 1: SETTINGS & CONFIGURATION -->
        <div class="tab-pane fade show active" id="tab-settings" role="tabpanel">
            <form action="{{ route('platform.metaSettings.store') }}" method="POST">
                @csrf
                @if($metaConfig)
                    <input type="hidden" name="id" value="{{ $metaConfig->id }}">
                @endif

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border rounded-3 p-4 shadow-sm mb-4 bg-white">
                            <div class="d-flex align-items-center gap-2 pb-3 mb-4 border-bottom">
                                <span class="d-inline-flex align-items-center justify-content-center bg-soft-primary text-primary rounded-circle" style="width: 32px; height: 32px;">
                                    <i class="feather-shield fs-16"></i>
                                </span>
                                <h6 class="fw-bold text-dark mb-0 fs-15">{{ __('meta.api_credentials_title') }}</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('meta.account_name')" name="name" :value="old('name', $metaConfig->name ?? __('meta.account_name_default'))" :required="true" :placeholder="__('meta.account_name_default')" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('meta.meta_app_id')" name="app_id" :value="old('app_id', $metaConfig->app_id ?? '')" placeholder="e.g. 109283746592019" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" inputType="password" :label="__('meta.meta_app_secret')" name="app_secret" :value="old('app_secret', $metaConfig->app_secret ?? '')" placeholder="••••••••••••••••" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('meta.ad_account_id')" name="ad_account_id" :value="old('ad_account_id', str_replace('act_', '', $metaConfig->ad_account_id ?? ''))" placeholder="1234567890" :helperText="__('meta.ad_account_helper')" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="textarea" :label="__('meta.access_token')" name="access_token" rows="3" :placeholder="__('meta.access_token_placeholder')" :helperText="__('meta.access_token_helper')">{{ old('access_token', $metaConfig->access_token ?? '') }}</x-ui.odoo-form-ui>
                                </div>
                            </div>
                        </div>

                        <div class="card border rounded-3 p-4 shadow-sm mb-4 bg-white">
                            <div class="d-flex align-items-center gap-2 pb-3 mb-4 border-bottom">
                                <span class="d-inline-flex align-items-center justify-content-center bg-soft-success text-success rounded-circle" style="width: 32px; height: 32px;">
                                    <i class="feather-user-check fs-16"></i>
                                </span>
                                <h6 class="fw-bold text-dark mb-0 fs-15">{{ __('meta.routing_title') }}</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('meta.page_id')" name="page_id" :value="old('page_id', $metaConfig->page_id ?? '')" placeholder="e.g. 100982348572910" :helperText="__('meta.page_id_helper')" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" :label="__('meta.lead_owner')" name="default_lead_owner_id">
                                        <option value="">{{ __('meta.select_option') }}</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" @selected(old('default_lead_owner_id', $metaConfig->default_lead_owner_id ?? '') == $u->id)>
                                                {{ $u->name }} ({{ $u->email }})
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" :label="__('meta.source')" name="default_source">
                                        @php
                                            $crmSources = ['Meta Ads', 'Facebook / Instagram', 'Direct Inquiry', 'Website Form', 'Web Search', 'IndiaMART', 'TradeIndia', 'Justdial', 'Cold Call', 'Referral', 'Employee Referral', 'Partner', 'Advertisement', 'Trade Show', 'WhatsApp Bot', 'WhatsApp', 'Email', 'Phone Call', 'Walk In', 'LinkedIn', 'Google Ads', 'Other'];
                                        @endphp
                                        @foreach ($crmSources as $srcOption)
                                            <option value="{{ $srcOption }}" @selected(old('default_source', $metaConfig->default_source ?? 'Meta Ads') === $srcOption)>
                                                {{ \Illuminate\Support\Facades\Lang::has('crm.sources.' . $srcOption) ? __('crm.sources.' . $srcOption) : $srcOption }}
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" :label="__('meta.priority')" name="default_priority">
                                        <option value="Urgent" @selected(old('default_priority', $metaConfig->default_priority ?? '') === 'Urgent')>{{ __('crm.priorities.Urgent') }}</option>
                                        <option value="High" @selected(old('default_priority', $metaConfig->default_priority ?? '') === 'High')>{{ __('crm.priorities.High') }}</option>
                                        <option value="Medium" @selected(old('default_priority', $metaConfig->default_priority ?? 'Medium') === 'Medium')>{{ __('crm.priorities.Medium') }}</option>
                                        <option value="Low" @selected(old('default_priority', $metaConfig->default_priority ?? '') === 'Low')>{{ __('crm.priorities.Low') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="col-12">
                                    <div class="odoo-form-group mb-0">
                                        <label class="odoo-form-label">{{ __('meta.integration_status') }}</label>
                                        <div class="d-flex align-items-center gap-4 pt-1">
                                            <x-ui.radio name="is_active" value="1" :checked="old('is_active', $metaConfig->is_active ?? true) == 1" :label="__('meta.active')" />
                                            <x-ui.radio name="is_active" value="0" :checked="old('is_active', $metaConfig->is_active ?? true) == 0" :label="__('meta.inactive')" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <x-ui.button type="button" id="btnTestMetaToken" variant="outline-primary" icon="feather-activity">
                                {{ __('meta.test_token_btn') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" icon="feather-save">
                                {{ __('meta.save_config_btn') }}
                            </x-ui.button>
                        </div>
                    </div>

                    <!-- Right Column Quick Info & Live Webhook Status -->
                    <div class="col-lg-4">
                        <div class="card border rounded-3 bg-light-subtle p-4 shadow-sm mb-4">
                            <div class="d-flex align-items-center gap-2 pb-3 mb-3 border-bottom">
                                <span class="d-inline-flex align-items-center justify-content-center bg-soft-success text-success rounded-circle" style="width: 30px; height: 30px;">
                                    <i class="feather-radio fs-15"></i>
                                </span>
                                <h6 class="fw-bold text-dark mb-0 fs-14">{{ __('meta.webhook_ready_title') }}</h6>
                            </div>
                            <p class="fs-12 text-muted mb-3">{{ __('meta.webhook_help') }}</p>
                            <div class="mb-3">
                                <label class="fw-bold fs-12 text-secondary mb-1 d-block">{{ __('meta.callback_url') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-sm font-monospace fs-11 bg-white" id="webhookUrlInput" value="{{ url('api/webhooks/meta-leadgen') }}" readonly>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ url('api/webhooks/meta-leadgen') }}'); alert('{{ __('meta.webhook_url_copied') }}');">
                                        <i class="feather-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="fw-bold fs-12 text-secondary mb-1 d-block">{{ __('meta.verify_token') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-sm font-monospace fs-11 bg-white" value="{{ $metaConfig->verify_token ?? 'meta_erp_token' }}" readonly>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ $metaConfig->verify_token ?? 'meta_erp_token' }}'); alert('{{ __('meta.verify_token_copied') }}');">
                                        <i class="feather-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card border rounded-3 p-4 shadow-sm mb-4 bg-white">
                            <div class="d-flex align-items-center gap-2 pb-3 mb-3 border-bottom">
                                <span class="d-inline-flex align-items-center justify-content-center bg-soft-primary text-primary rounded-circle" style="width: 30px; height: 30px;">
                                    <i class="feather-layers fs-15"></i>
                                </span>
                                <h6 class="fw-bold text-dark mb-0 fs-14">{{ __('meta.account_scope_title') }}</h6>
                            </div>
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('meta.company_scope')" name="company_id">
                                    <option value="">{{ __('meta.all_companies_global') }}</option>
                                    @foreach($companies as $c)
                                        <option value="{{ $c->id }}" @selected(($metaConfig->company_id ?? $companyId) == $c->id)>{{ $c->company_name ?: ($c->name ?: $c->legal_name) }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div>
                                <x-ui.odoo-form-ui type="select" :label="__('meta.branch_scope')" name="branch_id">
                                    <option value="">{{ __('meta.all_branches_global') }}</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}" @selected(($metaConfig->branch_id ?? $branchId) == $b->id)>{{ $b->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- TAB 2: META ADS STRUCTURE & CAMPAIGN CREATION GUIDE -->
        <div class="tab-pane fade" id="tab-structure" role="tabpanel">
            <div class="row g-4">
                <div class="col-12">
                    <div class="alert alert-primary d-flex align-items-center mb-4">
                        <i class="feather-compass fs-24 me-3"></i>
                        <div>
                            <h6 class="fw-bold mb-1">{{ __('meta.hierarchy_title') }}</h6>
                            <span class="fs-13">{{ __('meta.hierarchy_desc') }}</span>
                        </div>
                    </div>
                </div>

                <!-- 1. CAMPAIGN LEVEL -->
                <div class="col-md-6 col-lg-3">
                    <div class="card border rounded-3 h-100 shadow-none border-primary">
                        <div class="card-header bg-soft-primary border-bottom py-2.5">
                            <span class="badge bg-primary me-1">{{ __('meta.level_1_badge') }}</span>
                            <strong class="text-primary fs-14">{{ __('meta.level_1_title') }}</strong>
                        </div>
                        <div class="card-body fs-13">
                            <p class="text-muted mb-2">{{ __('meta.level_1_desc') }}</p>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><i class="feather-check-circle text-success me-1"></i> {{ __('meta.level_1_point1') }}</li>
                                <li class="mb-2"><i class="feather-check-circle text-success me-1"></i> {{ __('meta.level_1_point2') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 2. AD SET LEVEL -->
                <div class="col-md-6 col-lg-3">
                    <div class="card border rounded-3 h-100 shadow-none border-info">
                        <div class="card-header bg-soft-info border-bottom py-2.5">
                            <span class="badge bg-info text-dark me-1">{{ __('meta.level_2_badge') }}</span>
                            <strong class="text-dark fs-14">{{ __('meta.level_2_title') }}</strong>
                        </div>
                        <div class="card-body fs-13">
                            <p class="text-muted mb-2">{{ __('meta.level_2_desc') }}</p>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><i class="feather-check-circle text-info me-1"></i> {{ __('meta.level_2_point1') }}</li>
                                <li class="mb-2"><i class="feather-check-circle text-info me-1"></i> {{ __('meta.level_2_point2') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 3. AD LEVEL -->
                <div class="col-md-6 col-lg-3">
                    <div class="card border rounded-3 h-100 shadow-none border-warning">
                        <div class="card-header bg-soft-warning border-bottom py-2.5">
                            <span class="badge bg-warning text-dark me-1">{{ __('meta.level_3_badge') }}</span>
                            <strong class="text-dark fs-14">{{ __('meta.level_3_title') }}</strong>
                        </div>
                        <div class="card-body fs-13">
                            <p class="text-muted mb-2">{{ __('meta.level_3_desc') }}</p>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><i class="feather-check-circle text-warning me-1"></i> {{ __('meta.level_3_point1') }}</li>
                                <li class="mb-2"><i class="feather-check-circle text-warning me-1"></i> {{ __('meta.level_3_point2') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 4. INSTANT FORM -->
                <div class="col-md-6 col-lg-3">
                    <div class="card border rounded-3 h-100 shadow-none border-success">
                        <div class="card-header bg-soft-success border-bottom py-2.5">
                            <span class="badge bg-success me-1">{{ __('meta.level_4_badge') }}</span>
                            <strong class="text-success fs-14">{{ __('meta.level_4_title') }}</strong>
                        </div>
                        <div class="card-body fs-13">
                            <p class="text-muted mb-2">{{ __('meta.level_4_desc') }}</p>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><i class="feather-check-circle text-success me-1"></i> {{ __('meta.level_4_point1') }}</li>
                                <li class="mb-2"><i class="feather-check-circle text-success me-1"></i> {{ __('meta.level_4_point2') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: DEVELOPER TOKEN & APP SETUP GUIDE -->
        <div class="tab-pane fade" id="tab-token-guide" role="tabpanel">
            <div class="card border rounded-3 p-4 shadow-none bg-white">
                <h6 class="fw-bold text-dark border-bottom pb-3 mb-3">
                    <i class="feather-key text-primary me-2"></i>{{ __('meta.token_guide_title') }}
                </h6>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h6 class="fw-bold text-primary mb-2">{{ __('meta.step_a_title') }}</h6>
                            <ol class="fs-13 ps-3 text-secondary mb-0">
                                <li class="mb-2">{{ __('meta.step_a_1') }}</li>
                                <li class="mb-2">{{ __('meta.step_a_2') }}</li>
                                <li class="mb-2">{{ __('meta.step_a_3') }}</li>
                            </ol>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <h6 class="fw-bold text-success mb-2">{{ __('meta.step_b_title') }}</h6>
                            <ol class="fs-13 ps-3 text-secondary mb-0">
                                <li class="mb-2">{{ __('meta.step_b_1') }}</li>
                                <li class="mb-2">{{ __('meta.step_b_2') }}</li>
                                <li class="mb-2">{{ __('meta.step_b_3') }}
                                    <div class="badge bg-soft-primary text-primary mt-1">leads_retrieval</div>
                                    <div class="badge bg-soft-primary text-primary mt-1">pages_show_list</div>
                                    <div class="badge bg-soft-primary text-primary mt-1">pages_read_engagement</div>
                                    <div class="badge bg-soft-primary text-primary mt-1">pages_manage_ads</div>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: FREE LEAD TESTING SIMULATOR -->
        <div class="tab-pane fade" id="tab-testing" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border rounded-3 p-4 shadow-sm bg-white mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-3 mb-3">
                            <i class="feather-play text-success me-2"></i>{{ __('meta.simulator_title') }}
                        </h6>
                        <p class="text-muted fs-13 mb-3">{{ __('meta.simulator_desc') }}</p>

                        <form id="simulateLeadForm">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('meta.customer_name')" name="contact_person" :required="true" value="Rahul Sharma (Test Lead)" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('meta.phone_number')" name="phone" :required="true" value="+91 98765 43210" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" inputType="email" :label="__('meta.email_address')" name="email" value="rahul.test@example.com" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('meta.company_name')" name="company_name" value="Sharma Enterprises Pvt Ltd" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('meta.instant_form_name')" name="form_name" value="ERP Demo Inquiry Form 2026" />
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" :label="__('meta.product_requirement')" name="requirement" value="Looking for ERP Software demo & quotation" />
                                </div>
                                <div class="col-12 pt-2">
                                    <x-ui.button type="submit" id="btnSubmitSimulateLead" variant="success" icon="feather-send">
                                        {{ __('meta.simulate_btn') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card border rounded-3 p-4 bg-light shadow-none h-100">
                        <h6 class="fw-bold text-dark mb-2"><i class="feather-external-link text-primary me-2"></i>{{ __('meta.official_testing_title') }}</h6>
                        <p class="fs-13 text-secondary mb-3">{{ __('meta.official_testing_desc') }}</p>
                        
                        <div class="p-3 bg-white border rounded-3 mb-3">
                            <strong class="text-dark d-block mb-1 fs-13">{{ __('meta.testing_box_title') }}</strong>
                            <p class="fs-12 text-muted mb-2">{{ __('meta.testing_box_desc') }}</p>
                            <x-ui.button href="https://developers.facebook.com/tools/lead-ads-testing" target="_blank" variant="primary" size="sm" icon="feather-external-link">
                                {{ __('meta.open_lead_tool_btn') }}
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 5: INGESTED LEADS & WEBHOOK LOGS -->
        <div class="tab-pane fade" id="tab-logs" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="feather-database text-primary me-2"></i>{{ __('meta.recent_logs_title') }}</h6>
                <x-ui.button type="button" variant="outline-secondary" size="sm" icon="feather-refresh-cw" onclick="window.location.reload();">
                    {{ __('meta.refresh_logs_btn') }}
                </x-ui.button>
            </div>

            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" class="mb-0">
                    <thead>
                        <tr style="background-color: #e8ecf1 !important;">
                            <th style="background-color: #e8ecf1 !important;" class="ps-3">{{ __('meta.col_date_time') }}</th>
                            <th style="background-color: #e8ecf1 !important;">{{ __('meta.col_lead_name_contact') }}</th>
                            <th style="background-color: #e8ecf1 !important;">{{ __('meta.col_meta_leadgen_form') }}</th>
                            <th style="background-color: #e8ecf1 !important;">{{ __('meta.col_linked_crm_lead') }}</th>
                            <th style="background-color: #e8ecf1 !important;">{{ __('meta.col_status') }}</th>
                            <th style="background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('meta.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        @forelse($recentLogs as $log)
                            <tr>
                                <td class="ps-3 fs-12 font-monospace text-muted">
                                    {{ $log->created_at->format('d M Y, h:i A') }}
                                </td>
                                <td>
                                    <strong class="text-dark d-block">{{ $log->full_name ?: __('meta.unknown_lead') }}</strong>
                                    <span class="fs-12 text-muted font-monospace">{{ $log->phone_number ?: '—' }} | {{ $log->email ?: '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace fs-11">{{ $log->leadgen_id }}</span>
                                    <div class="fs-11 text-muted mt-0.5">Form: {{ $log->extracted_data['form_name'] ?? ($log->form_id ?: 'Default Form') }}</div>
                                </td>
                                <td>
                                    @if($log->crm_lead_id && $log->crmLead)
                                        <a href="{{ route('crm.leads.show', $log->crm_lead_id) }}" target="_blank" class="badge bg-soft-success text-success text-decoration-none fw-bold fs-12 p-1.5 border">
                                            <i class="feather-user me-1"></i>{{ $log->crmLead->lead_number ?: ('#Lead-' . $log->crm_lead_id) }}
                                        </a>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->status === 'processed')
                                        <x-ui.status-badge status="Processed" type="success" />
                                    @elseif($log->status === 'failed')
                                        <x-ui.status-badge status="Failed" type="danger" />
                                    @elseif($log->status === 'duplicate')
                                        <x-ui.status-badge status="Duplicate" type="warning" />
                                    @else
                                        <x-ui.status-badge status="Pending" type="secondary" />
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if($log->crm_lead_id)
                                        <x-ui.button href="{{ route('crm.leads.show', $log->crm_lead_id) }}" target="_blank" variant="outline-primary" size="xs" icon="feather-eye">
                                            {{ __('meta.view_in_crm') }}
                                        </x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="feather-inbox fs-36 text-muted mb-2 d-block opacity-50"></i>
                                    {{ __('meta.no_logs_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>
    </div>
</div>

<!-- TEST NOTIFICATION MODAL -->
<x-ui.modal id="metaTestResultModal" title="<i class='feather-activity me-1.5 text-primary'></i>{{ __('meta.meta_api_test_modal_title') }}" size="lg" :centered="true" :showFooter="false">
    <div class="py-4 px-3 text-center">
        <div id="metaResultIcon" class="mb-3 d-flex justify-content-center"></div>
        <h4 id="metaResultTitle" class="fw-bold text-dark mb-2 fs-18"></h4>
        <div id="metaResultMessage" class="alert alert-light border text-center fs-13 mb-3 font-monospace p-3 shadow-2xs rounded-3 mx-auto" style="max-width: 520px; background-color: #f8fafc; border-color: #e2e8f0 !important; color: #334155;"></div>
        <div id="metaResultDetails" class="text-start mx-auto p-3 bg-light rounded border fs-12 mb-3" style="max-width: 520px; display: none;"></div>
        <div class="d-flex justify-content-center mt-3">
            <button type="button" class="btn btn-primary fw-bold px-5 py-2 fs-13" data-bs-dismiss="modal">{{ __('meta.modal_ok') }}</button>
        </div>
    </div>
</x-ui.modal>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        const trans = {
            verifyingToken: "{{ __('meta.verifying_token') }}",
            connectionSuccess: "{{ __('meta.connection_successful') }}",
            connectionFailed: "{{ __('meta.connection_failed') }}",
            authDetails: "{{ __('meta.authenticated_account_details') }}",
            creatingLead: "{{ __('meta.creating_lead_in_crm') }}",
            ingestionSuccess: "{{ __('meta.lead_ingestion_successful') }}",
            simulationFailed: "{{ __('meta.lead_simulation_failed') }}",
            openLead: "{{ __('meta.open_lead_in_crm') }}"
        };

        // Test Meta Token
        $('#btnTestMetaToken').on('click', function() {
            const btn = $(this);
            const origHtml = btn.html();
            const token = $('textarea[name="access_token"]').val();
            const adAccountId = $('input[name="ad_account_id"]').val();

            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>' + trans.verifyingToken);

            $.ajax({
                url: "{{ route('platform.metaSettings.testToken') }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    access_token: token,
                    ad_account_id: adAccountId
                },
                success: function(res) {
                    btn.prop('disabled', false).html(origHtml);
                    
                    $('#metaResultIcon').html('<div class="avatar avatar-xl bg-soft-success text-success rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border: 2px solid rgba(34, 197, 94, 0.2);"><i class="feather-check-circle fs-32"></i></div>');
                    $('#metaResultTitle').text(trans.connectionSuccess).attr('class', 'fw-bold text-success mb-2 fs-18');
                    $('#metaResultMessage').text(res.message);

                    if (res.details) {
                        let detailsHtml = '<strong class="text-dark d-block mb-1">' + trans.authDetails + '</strong><ul class="mb-0 ps-3">';
                        $.each(res.details, function(k, v) {
                            detailsHtml += '<li><strong>' + k + ':</strong> ' + v + '</li>';
                        });
                        detailsHtml += '</ul>';
                        $('#metaResultDetails').html(detailsHtml).show();
                    } else {
                        $('#metaResultDetails').hide();
                    }

                    const modalEl = document.getElementById('metaTestResultModal');
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(origHtml);
                    const errMsg = xhr.responseJSON ? xhr.responseJSON.message : trans.connectionFailed;
                    
                    $('#metaResultIcon').html('<div class="avatar avatar-xl bg-soft-danger text-danger rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border: 2px solid rgba(239, 68, 68, 0.2);"><i class="feather-alert-triangle fs-32"></i></div>');
                    $('#metaResultTitle').text(trans.connectionFailed).attr('class', 'fw-bold text-danger mb-2 fs-18');
                    $('#metaResultMessage').text(errMsg);
                    $('#metaResultDetails').hide();

                    const modalEl = document.getElementById('metaTestResultModal');
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        });

        // Simulate Test Lead
        $('#simulateLeadForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const btn = $('#btnSubmitSimulateLead');
            const origHtml = btn.html();

            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>' + trans.creatingLead);

            $.ajax({
                url: "{{ route('platform.metaSettings.simulateTestLead') }}",
                method: "POST",
                data: form.serialize(),
                success: function(res) {
                    btn.prop('disabled', false).html(origHtml);

                    $('#metaResultIcon').html('<div class="avatar avatar-xl bg-soft-success text-success rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border: 2px solid rgba(34, 197, 94, 0.2);"><i class="feather-user-check fs-32"></i></div>');
                    $('#metaResultTitle').text(trans.ingestionSuccess).attr('class', 'fw-bold text-success mb-2 fs-18');
                    $('#metaResultMessage').html(res.message + '<br><a href="' + res.view_url + '" target="_blank" class="btn btn-sm btn-primary fw-bold mt-2"><i class="feather-eye me-1"></i>' + trans.openLead + '</a>');
                    $('#metaResultDetails').hide();

                    const modalEl = document.getElementById('metaTestResultModal');
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(origHtml);
                    const errMsg = xhr.responseJSON ? xhr.responseJSON.message : trans.simulationFailed;

                    $('#metaResultIcon').html('<div class="avatar avatar-xl bg-soft-danger text-danger rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border: 2px solid rgba(239, 68, 68, 0.2);"><i class="feather-alert-triangle fs-32"></i></div>');
                    $('#metaResultTitle').text(trans.simulationFailed).attr('class', 'fw-bold text-danger mb-2 fs-18');
                    $('#metaResultMessage').text(errMsg);
                    $('#metaResultDetails').hide();

                    const modalEl = document.getElementById('metaTestResultModal');
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        });
    });
</script>
@endpush
