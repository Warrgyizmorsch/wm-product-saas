@extends('layouts.duralux')

@section('title', 'Twilio Telephony & Call Recording Setup | SaaS ERP')
@section('page-title', 'Twilio Cloud Telephony Setup')
@section('breadcrumb', 'Platform / Integrations / Twilio')

@section('page-actions')
    <x-ui.button href="https://console.twilio.com" target="_blank" variant="outline-primary" icon="feather-external-link">
        Open Twilio Console
    </x-ui.button>
    <x-ui.button href="{{ route('crm.dashboard') }}" variant="light-brand" icon="feather-grid">
        CRM Dashboard
    </x-ui.button>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
    <!-- Header Context Strip -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 pb-3 border-bottom">
        <div>
            <h5 class="fw-bold text-dark mb-1">
                <i class="feather-phone-call text-purple me-2"></i>Twilio Cloud Telephony & Call Recording Setup
            </h5>
            <p class="text-muted fs-12 mb-0">Configure tenant-level Twilio credentials for automatic calling, audio recording capture, and AI voice notes in CRM.</p>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2 fs-12">
            <span class="fw-bold text-secondary me-1"><i class="feather-layers me-1"></i>Current Context:</span>
            <span class="badge erp-badge bg-primary text-white"><i class="feather-globe me-1"></i>Tenant #{{ $tenantId }}</span>
            <span class="badge erp-badge bg-info text-dark"><i class="feather-briefcase me-1"></i>Company: {{ $companyId ?: 'All Companies' }}</span>
            @if ($twilioConfig?->is_active)
                <span class="badge bg-soft-success text-success"><i class="feather-check-circle me-1"></i>Twilio Active</span>
            @else
                <span class="badge bg-soft-secondary text-muted"><i class="feather-slash me-1"></i>Inactive / Not Configured</span>
            @endif
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

    @php
        $twilioTabs = [
            [
                'id' => 'tab-credentials',
                'label' => 'API Credentials & Setup',
                'icon' => 'feather-key',
                'active' => true,
            ],
            [
                'id' => 'tab-webhooks',
                'label' => 'Webhooks & Endpoints',
                'icon' => 'feather-share-2',
                'active' => false,
            ],
            [
                'id' => 'tab-testing',
                'label' => 'Connection Test & Simulator',
                'icon' => 'feather-play-circle',
                'active' => false,
            ],
            [
                'id' => 'tab-guide',
                'label' => 'Configuration Guide',
                'icon' => 'feather-help-circle',
                'active' => false,
            ],
        ];
    @endphp

    <!-- Horizontal Tabs -->
    <x-ui.horizontal-tabs id="twilioTabs" :tabs="$twilioTabs" class="mb-4" />

    <div class="tab-content" id="twilioTabsContent">
        <!-- ========================================================= -->
        <!-- TAB 1: API CREDENTIALS & ACCOUNT SETUP -->
        <!-- ========================================================= -->
        <div class="tab-pane fade show active" id="tab-credentials" role="tabpanel">
            <form action="{{ route('platform.twilioSettings.store') }}" method="POST">
                @csrf
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border rounded-3 p-4 mb-4 shadow-none">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                <i class="feather-shield text-primary me-2"></i>Twilio API Account Credentials
                            </h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark fs-13" for="account_name">Configuration Name</label>
                                    <input type="text" name="name" id="account_name" class="form-control form-control-sm" 
                                           value="{{ old('name', $twilioConfig?->name ?? 'Primary Twilio Account') }}" placeholder="e.g. Sales Twilio Trunk">
                                    <span class="text-muted fs-11">A descriptive label for this telephony configuration.</span>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark fs-13" for="phone_number">
                                        Twilio Virtual Phone Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="phone_number" id="phone_number" class="form-control form-control-sm font-monospace" 
                                           value="{{ old('phone_number', $twilioConfig?->phone_number) }}" placeholder="+1234567890">
                                    <span class="text-muted fs-11">Include country code (e.g. +14155552671 or +919876543210).</span>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark fs-13" for="account_sid">
                                        Twilio Account SID <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="account_sid" id="account_sid" class="form-control form-control-sm font-monospace" 
                                           value="{{ old('account_sid', $twilioConfig?->account_sid) }}" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                                    <span class="text-muted fs-11">Found on your Twilio Console main dashboard.</span>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark fs-13" for="auth_token">
                                        Twilio Auth Token <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" name="auth_token" id="auth_token" class="form-control font-monospace" 
                                               value="{{ old('auth_token', $twilioConfig?->auth_token) }}" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('auth_token')">
                                            <i class="feather-eye" id="auth_token_icon"></i>
                                        </button>
                                    </div>
                                    <span class="text-muted fs-11">Secret auth token used for API requests & Webhook verification.</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark fs-13" for="twiml_app_sid">
                                    TwiML App SID <span class="badge bg-soft-secondary text-secondary ms-1">Optional</span>
                                </label>
                                <input type="text" name="twiml_app_sid" id="twiml_app_sid" class="form-control form-control-sm font-monospace" 
                                       value="{{ old('twiml_app_sid', $twilioConfig?->twiml_app_sid) }}" placeholder="APxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                <span class="text-muted fs-11">Required only if using Browser WebRTC softphone calling.</span>
                            </div>
                        </div>

                        <!-- AI Voice Notes & Transcription Settings -->
                        <div class="card border rounded-3 p-4 mb-4 shadow-none bg-soft-primary bg-opacity-10 border-primary border-opacity-25">
                            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                                <h6 class="fw-bold text-primary mb-0">
                                    <i class="feather-cpu text-primary me-2"></i>AI Call Recording & Voice-to-CRM Summary
                                </h6>
                                <span class="badge bg-primary text-white">Next-Gen AI</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="record_calls" id="record_calls" value="1" 
                                               @checked(old('record_calls', $twilioConfig?->record_calls ?? true))>
                                        <label class="form-check-label fw-semibold text-dark fs-13" for="record_calls">
                                            Enable Automatic Call Recording
                                        </label>
                                    </div>
                                    <p class="text-muted fs-12 mb-0">Twilio records audio and attaches a playable mp3 player in the Lead Timeline.</p>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="auto_summarize_ai" id="auto_summarize_ai" value="1" 
                                               @checked(old('auto_summarize_ai', $twilioConfig?->auto_summarize_ai ?? true))>
                                        <label class="form-check-label fw-semibold text-dark fs-13" for="auto_summarize_ai">
                                            Enable AI Call Notes & Follow-up Extraction
                                        </label>
                                    </div>
                                    <p class="text-muted fs-12 mb-0">AI automatically transcribes audio, summarizes requirements, and schedules next follow-ups.</p>
                                </div>

                                <div class="col-12 mt-2">
                                    <label class="form-label fw-bold text-dark fs-13" for="gemini_api_key">
                                        Google Gemini API Key <span class="badge bg-soft-success text-success ms-1">Free Tier Supported</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-control font-monospace" 
                                               value="{{ old('gemini_api_key', $twilioConfig?->gemini_api_key) }}" placeholder="AIzaSyxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('gemini_api_key')">
                                            <i class="feather-eye" id="gemini_api_key_icon"></i>
                                        </button>
                                    </div>
                                    <span class="text-muted fs-11">Leave blank to use global system AI, or provide tenant's custom Gemini API key.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Lead Auto-Creation Defaults -->
                        <div class="card border rounded-3 p-4 mb-4 shadow-none">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                                <i class="feather-user-check text-success me-2"></i>Inbound Call Lead Defaults
                            </h6>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark fs-13" for="default_lead_owner_id">Default Lead Assignee</label>
                                    <select name="default_lead_owner_id" id="default_lead_owner_id" class="form-select form-select-sm">
                                        <option value="">-- Active Logged-in User / Round-Robin --</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" @selected(old('default_lead_owner_id', $twilioConfig?->default_lead_owner_id) == $user->id)>
                                                {{ $user->name }} ({{ $user->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark fs-13" for="default_source">Default Source</label>
                                    <input type="text" name="default_source" id="default_source" class="form-control form-control-sm" 
                                           value="{{ old('default_source', $twilioConfig?->default_source ?? 'Twilio Call') }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark fs-13" for="default_priority">Default Priority</label>
                                    <select name="default_priority" id="default_priority" class="form-select form-select-sm">
                                        <option value="Low" @selected(old('default_priority', $twilioConfig?->default_priority) === 'Low')>Low</option>
                                        <option value="Medium" @selected(old('default_priority', $twilioConfig?->default_priority ?? 'Medium') === 'Medium')>Medium</option>
                                        <option value="High" @selected(old('default_priority', $twilioConfig?->default_priority) === 'High')>High</option>
                                        <option value="Urgent" @selected(old('default_priority', $twilioConfig?->default_priority) === 'Urgent')>Urgent</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Action Buttons -->
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 border">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" 
                                       @checked(old('is_active', $twilioConfig?->is_active ?? true))>
                                <label class="form-check-label fw-bold text-dark fs-13" for="is_active">
                                    Enable Twilio Telephony Integration for this Tenant
                                </label>
                            </div>

                            <x-ui.button type="submit" variant="primary" icon="feather-save">
                                Save Configuration
                            </x-ui.button>
                        </div>
                    </div>

                    <!-- Right Quick Status Panel -->
                    <div class="col-lg-4">
                        <div class="card border rounded-3 p-4 mb-4 shadow-none bg-light">
                            <h6 class="fw-bold text-dark mb-3"><i class="feather-info text-primary me-2"></i>Telephony Status</h6>
                            <ul class="list-unstyled mb-0 d-flex flex-column gap-2 fs-13 text-muted">
                                <li class="d-flex justify-content-between">
                                    <span>Status:</span>
                                    @if($twilioConfig?->is_active && $twilioConfig?->account_sid)
                                        <span class="badge bg-success text-white">Configured & Active</span>
                                    @else
                                        <span class="badge bg-secondary text-white">Not Connected</span>
                                    @endif
                                </li>
                                <li class="d-flex justify-content-between">
                                    <span>Twilio Number:</span>
                                    <strong class="text-dark font-monospace">{{ $twilioConfig?->phone_number ?: 'Not specified' }}</strong>
                                </li>
                                <li class="d-flex justify-content-between">
                                    <span>Call Recording:</span>
                                    <strong class="{{ $twilioConfig?->record_calls ? 'text-success' : 'text-danger' }}">
                                        {{ $twilioConfig?->record_calls ? 'Enabled' : 'Disabled' }}
                                    </strong>
                                </li>
                                <li class="d-flex justify-content-between">
                                    <span>AI Voice Notes:</span>
                                    <strong class="{{ $twilioConfig?->auto_summarize_ai ? 'text-success' : 'text-muted' }}">
                                        {{ $twilioConfig?->auto_summarize_ai ? 'Gemini AI Active' : 'Off' }}
                                    </strong>
                                </li>
                            </ul>

                            <hr class="my-3">

                            <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-2" onclick="testTwilioConnection()">
                                <i class="feather-activity me-1"></i>Test Credentials Connection
                            </button>
                            <div id="quick-test-result" class="fs-12 mt-2 d-none"></div>
                        </div>

                        <!-- Info Box -->
                        <div class="card border rounded-3 p-3 shadow-none border-info border-opacity-25 bg-soft-info bg-opacity-10">
                            <h6 class="fw-bold text-info fs-13 mb-1"><i class="feather-zap me-1"></i>Zero Manual Data Entry</h6>
                            <p class="fs-12 text-muted mb-0">
                                Jab bhi sales rep call karega, Twilio automatically audio record karega aur call khatam hone par recording ka link CRM lead timeline mein player ke sath save ho jayega.
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ========================================================= -->
        <!-- TAB 2: WEBHOOKS & ENDPOINTS -->
        <!-- ========================================================= -->
        <div class="tab-pane fade" id="tab-webhooks" role="tabpanel">
            <div class="card border rounded-3 p-4 mb-4 shadow-none">
                <h6 class="fw-bold text-dark mb-2">
                    <i class="feather-share-2 text-primary me-2"></i>Twilio Webhook Endpoints
                </h6>
                <p class="text-muted fs-13 mb-4">
                    Copy these webhook URLs into your <a href="https://console.twilio.com" target="_blank" class="fw-bold text-primary">Twilio Console &rarr; Phone Numbers &rarr; Manage &rarr; Active Numbers</a> settings.
                </p>

                <div class="mb-4">
                    <label class="form-label fw-bold text-dark fs-13">1. Voice / Inbound Call Webhook (HTTP POST)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace bg-light" id="voiceWebhookInput" value="{{ $voiceWebhookUrl }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('voiceWebhookInput')">
                            <i class="feather-copy me-1"></i>Copy URL
                        </button>
                    </div>
                    <span class="text-muted fs-11">Paste in Twilio Console under <strong>"A Call Comes In" &rarr; Webhook (HTTP POST)</strong>.</span>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-dark fs-13">2. Recording Status Callback URL (HTTP POST)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace bg-light" id="recordingWebhookInput" value="{{ $recordingWebhookUrl }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('recordingWebhookInput')">
                            <i class="feather-copy me-1"></i>Copy URL
                        </button>
                    </div>
                    <span class="text-muted fs-11">Triggered when call recording (.mp3) is ready. ERP downloads/logs the audio and triggers AI summarization.</span>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-dark fs-13">3. Status Callback URL (Call Completed / Duration)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace bg-light" id="statusWebhookInput" value="{{ $statusWebhookUrl }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('statusWebhookInput')">
                            <i class="feather-copy me-1"></i>Copy URL
                        </button>
                    </div>
                    <span class="text-muted fs-11">Logs call duration, answered status, busy/missed call alerts in real time.</span>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- TAB 3: CONNECTION TEST & SIMULATOR -->
        <!-- ========================================================= -->
        <div class="tab-pane fade" id="tab-testing" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border rounded-3 p-4 h-100 shadow-none">
                        <h6 class="fw-bold text-dark mb-2">
                            <i class="feather-activity text-success me-2"></i>Live Twilio API Credentials Check
                        </h6>
                        <p class="text-muted fs-13 mb-3">
                            Verifies whether your Account SID and Auth Token can successfully authenticate with Twilio Cloud Servers.
                        </p>

                        <button type="button" class="btn btn-primary btn-sm mb-3" onclick="testTwilioConnection()">
                            <i class="feather-check-circle me-1"></i>Run Live Twilio API Check
                        </button>

                        <div id="full-test-result" class="p-3 bg-light rounded-3 border d-none">
                            <div class="fw-bold text-dark fs-13 mb-1" id="test-status-heading"></div>
                            <div class="text-muted fs-12" id="test-status-body"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border rounded-3 p-4 h-100 shadow-none">
                        <h6 class="fw-bold text-dark mb-2">
                            <i class="feather-phone-incoming text-primary me-2"></i>Test Call & Recording Simulator
                        </h6>
                        <p class="text-muted fs-13 mb-3">
                            Simulates an inbound/outbound call event with audio log in CRM to verify that the lead timeline and activities display correctly.
                        </p>

                        <form id="simulateCallForm" onsubmit="handleSimulateCall(event)">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label fs-12 fw-bold text-dark mb-1">Customer / Caller Name</label>
                                    <input type="text" id="sim_caller_name" class="form-control form-control-sm" value="Rajesh Sharma" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fs-12 fw-bold text-dark mb-1">Phone Number</label>
                                    <input type="text" id="sim_phone" class="form-control form-control-sm font-monospace" value="+919876543210" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fs-12 fw-bold text-dark mb-1">Sample Call Notes / Inquiry</label>
                                <textarea id="sim_notes" class="form-control form-control-sm" rows="2">Client requested urgent quotation for 100 units with 15-day delivery to Mumbai.</textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-success btn-sm w-100" id="simSubmitBtn">
                                <i class="feather-play me-1"></i>Generate Test Call Activity in CRM
                            </button>
                        </form>
                        <div id="sim-result" class="mt-2 fs-12 d-none"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- TAB 4: CONFIGURATION GUIDE -->
        <!-- ========================================================= -->
        <div class="tab-pane fade" id="tab-guide" role="tabpanel">
            <div class="card border rounded-3 p-4 shadow-none">
                <h6 class="fw-bold text-dark mb-3"><i class="feather-book-open text-primary me-2"></i>How to Setup Twilio Step-by-Step</h6>
                <div class="d-flex flex-column gap-3 fs-13 text-muted">
                    <div class="p-3 bg-light rounded-3 border">
                        <strong class="text-dark d-block mb-1">Step 1: Get Twilio Account SID & Auth Token</strong>
                        Log into <a href="https://console.twilio.com" target="_blank" class="fw-bold text-primary">console.twilio.com</a>. On your dashboard homepage, copy the <strong>Account SID</strong> and <strong>Auth Token</strong>, then paste them in Tab 1 of this page.
                    </div>
                    <div class="p-3 bg-light rounded-3 border">
                        <strong class="text-dark d-block mb-1">Step 2: Buy or Select a Twilio Phone Number</strong>
                        Navigate to <strong>Phone Numbers &rarr; Manage &rarr; Buy a Number</strong>. Choose a number capable of Voice calling and copy it to the Phone Number field.
                    </div>
                    <div class="p-3 bg-light rounded-3 border">
                        <strong class="text-dark d-block mb-1">Step 3: Setup Webhook URLs</strong>
                        In Twilio Console, click on your Active Number, scroll to <strong>Voice Configuration</strong>, and paste the <strong>Voice Webhook URL</strong> and <strong>Recording Status Callback URL</strong> from Tab 2.
                    </div>
                    <div class="p-3 bg-light rounded-3 border">
                        <strong class="text-dark d-block mb-1">Step 4: AI Voice Notes & Gemini Integration</strong>
                        Enable the "AI Call Recording & Voice-to-CRM Summary" toggle. When a call completes, Twilio sends the audio recording URL to our system, which uses Gemini AI to extract requirements, sentiment, and automatically schedules the next follow-up.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + '_icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('feather-eye');
        icon.classList.add('feather-eye-off');
    } else {
        input.type = 'password';
        icon.classList.remove('feather-eye-off');
        icon.classList.add('feather-eye');
    }
}

function copyToClipboard(inputId) {
    const copyText = document.getElementById(inputId);
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value).then(() => {
        alert('Webhook URL copied to clipboard: ' + copyText.value);
    });
}

function testTwilioConnection() {
    const accountSid = document.getElementById('account_sid').value;
    const authToken = document.getElementById('auth_token').value;
    const quickResult = document.getElementById('quick-test-result');
    const fullResult = document.getElementById('full-test-result');
    const statusHeading = document.getElementById('test-status-heading');
    const statusBody = document.getElementById('test-status-body');

    quickResult.className = 'fs-12 mt-2 alert alert-info py-1 px-2 d-block';
    quickResult.innerHTML = '<i class="feather-loader me-1"></i> Testing Twilio connection...';

    fetch("{{ route('platform.twilioSettings.testConnection') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ account_sid: accountSid, auth_token: authToken })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            quickResult.className = 'fs-12 mt-2 alert alert-success py-1 px-2 d-block';
            quickResult.innerHTML = '<i class="feather-check-circle me-1"></i> ' + data.message;
            if (fullResult) {
                fullResult.classList.remove('d-none');
                statusHeading.innerHTML = '<i class="feather-check-circle text-success me-1"></i> ' + data.account_name + ' (Status: ' + data.status + ')';
                statusBody.innerHTML = data.message + ' Account Type: ' + data.type;
            }
        } else {
            quickResult.className = 'fs-12 mt-2 alert alert-danger py-1 px-2 d-block';
            quickResult.innerHTML = '<i class="feather-alert-triangle me-1"></i> ' + data.message;
            if (fullResult) {
                fullResult.classList.remove('d-none');
                statusHeading.innerHTML = '<i class="feather-alert-triangle text-danger me-1"></i> Connection Failed';
                statusBody.innerHTML = data.message;
            }
        }
    })
    .catch(err => {
        quickResult.className = 'fs-12 mt-2 alert alert-danger py-1 px-2 d-block';
        quickResult.innerHTML = '<i class="feather-alert-triangle me-1"></i> Request failed: ' + err.message;
    });
}

function handleSimulateCall(e) {
    e.preventDefault();
    const btn = document.getElementById('simSubmitBtn');
    const simResult = document.getElementById('sim-result');
    btn.disabled = true;
    btn.innerHTML = '<i class="feather-loader me-1"></i> Generating...';

    const payload = {
        caller_name: document.getElementById('sim_caller_name').value,
        phone: document.getElementById('sim_phone').value,
        sample_notes: document.getElementById('sim_notes').value,
        call_duration: 120
    };

    fetch("{{ route('platform.twilioSettings.simulateCallLog') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="feather-play me-1"></i> Generate Test Call Activity in CRM';
        if (data.success) {
            simResult.className = 'mt-2 fs-12 alert alert-success py-1 px-2 d-block';
            simResult.innerHTML = `<i class="feather-check-circle me-1"></i> ${data.message} <a href="${data.lead_url}" target="_blank" class="fw-bold ms-1 text-primary">View Lead &rarr;</a>`;
        } else {
            simResult.className = 'mt-2 fs-12 alert alert-danger py-1 px-2 d-block';
            simResult.innerHTML = '<i class="feather-alert-triangle me-1"></i> ' + data.message;
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="feather-play me-1"></i> Generate Test Call Activity in CRM';
        simResult.className = 'mt-2 fs-12 alert alert-danger py-1 px-2 d-block';
        simResult.innerHTML = '<i class="feather-alert-triangle me-1"></i> ' + err.message;
    });
}
</script>
@endpush
