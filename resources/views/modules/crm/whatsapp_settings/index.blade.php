@extends('layouts.duralux')

@section('title', 'WhatsApp Web Setup & Device Linking | CRM Settings')
@section('page-title', 'WhatsApp Web Integration')
@section('breadcrumb', 'CRM / Settings / WhatsApp Setup')

@section('content')
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="feather-message-circle text-success me-2"></i>WhatsApp Web Linked Device Setup
            </h4>
            <p class="text-muted fs-12 mb-0">Connect your corporate WhatsApp account via Linked Devices QR scan to send instant Quotations, Invoices, and Client Documents.</p>
        </div>
        <div>
            <button type="button" id="btnRefreshWA" class="btn btn-outline-secondary fw-bold px-3 py-2 fs-12">
                <i class="feather-refresh-cw me-1.5"></i>Refresh Status
            </button>
            <button type="button" id="btnConnectWA" class="btn btn-success fw-bold px-3 py-2 fs-12 ms-2">
                <i class="feather-smartphone me-1.5"></i>Generate QR Code
            </button>
        </div>
    </div>

    <!-- TENANT / COMPANY / BRANCH CONTEXT BADGES -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 8px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2 fs-12">
            <div>
                <span class="fw-bold text-secondary me-2"><i class="feather-layers me-1"></i>Active Context:</span>
                <span class="badge bg-primary me-1"><i class="feather-globe me-1"></i>Tenant ID: {{ $config->tenant_id }}</span>
                <span class="badge bg-info text-dark me-1"><i class="feather-briefcase me-1"></i>Company ID: {{ $config->company_id ?: 'Default (All)' }}</span>
                <span class="badge bg-secondary me-1"><i class="feather-git-branch me-1"></i>Branch ID: {{ $config->branch_id ?: 'Default (All)' }}</span>
            </div>
            <div>
                <span class="text-muted font-monospace"><i class="feather-key me-1 text-warning"></i>DB Session Key: <strong>{{ $config->session_key }}</strong></span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- CONNECTION STATUS CARD -->
        <div class="col-lg-6 col-md-12">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 8px;">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="feather-shield text-primary me-2"></i>WhatsApp Connection State
                    </h6>
                    <span id="waStatusBadge" class="badge bg-secondary px-2.5 py-1 fs-11">Checking...</span>
                </div>
                <div class="card-body p-4 text-center">
                    <!-- Status Icon Display -->
                    <div id="waStatusIcon" class="mb-3">
                        <div class="avatar avatar-xl bg-soft-secondary text-secondary rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                            <i class="feather-message-square fs-32"></i>
                        </div>
                    </div>

                    <h5 id="waStatusTitle" class="fw-bold text-dark mb-2">Connecting to WhatsApp Bridge...</h5>
                    <p id="waStatusText" class="text-muted fs-13 mb-4">Initializing connection with local WhatsApp Baileys engine...</p>

                    <!-- QR CODE BOX -->
                    <div id="waQrBox" class="p-4 rounded border bg-light-subtle mb-4 d-none">
                        <div class="mb-3">
                            <span class="badge bg-soft-warning text-dark border border-warning px-3 py-1 fw-bold fs-12">
                                <i class="feather-camera me-1"></i>Scan QR Code with WhatsApp App
                            </span>
                        </div>
                        <div class="d-inline-block p-3 bg-white rounded border shadow-sm mb-3">
                            <img id="waQrImg" src="" alt="WhatsApp QR Code" class="img-fluid" style="width: 220px; height: 220px; object-fit: contain;">
                        </div>
                        <div class="text-start fs-12 text-muted max-w-sm mx-auto bg-white p-3 rounded border">
                            <strong class="text-dark d-block mb-1"><i class="feather-info text-primary me-1"></i>How to connect:</strong>
                            <ol class="mb-0 ps-3">
                                <li>Open <strong>WhatsApp</strong> on your phone.</li>
                                <li>Tap <strong>Menu (⋮)</strong> or <strong>Settings</strong> ⚙️.</li>
                                <li>Select <strong>Linked Devices</strong> and tap <strong>Link a Device</strong>.</li>
                                <li>Point your phone camera at this QR code.</li>
                            </ol>
                        </div>
                    </div>

                    <!-- CONNECTED DEVICE ACCOUNT INFO -->
                    <div id="waDeviceInfo" class="p-3 rounded border bg-soft-success text-start fs-13 mb-4 d-none">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar avatar-md bg-success text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 45px; height: 45px;">
                                <i class="feather-check-circle fs-20"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <strong id="waUserName" class="text-dark d-block fs-14 fw-bold">WhatsApp Account Active</strong>
                                <span id="waUserJid" class="text-muted font-monospace fs-11">JID: --</span>
                            </div>
                        </div>
                    </div>

                    <!-- DISCONNECT BUTTON -->
                    <button type="button" id="btnDisconnectWA" class="btn btn-outline-danger fw-bold px-4 fs-12 d-none">
                        <i class="feather-power me-1.5"></i>Disconnect WhatsApp Session
                    </button>
                </div>
            </div>
        </div>

        <!-- QUICK TEST SENDER CARD & DATABASE CONFIG -->
        <div class="col-lg-6 col-md-12">
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 8px;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="feather-send text-success me-2"></i>Send Quick Test WhatsApp
                    </h6>
                </div>
                <div class="card-body p-4">
                    <form id="sendQuickWaForm">
                        @csrf
                        <div class="mb-3">
                            <x-ui.modal-form-ui type="input" label="Recipient WhatsApp Mobile Number" name="mobile" id="quickWaMobile" placeholder="e.g. 9876543210 (Country code 91 auto-added)" :required="true" />
                        </div>
                        <div class="mb-3">
                            <x-ui.modal-form-ui type="textarea" label="Message / Caption Text" name="caption" id="quickWaCaption" rows="3" :required="true">Hello! This is a test message sent from Warrgyizmorsch SaaS ERP via WhatsApp Web Bridge.</x-ui.modal-form-ui>
                        </div>
                        <button type="submit" id="btnSubmitQuickWa" class="btn btn-success fw-bold px-4 py-2 fs-12">
                            <i class="feather-send me-1.5"></i>Send Test Message
                        </button>
                    </form>
                </div>
            </div>

            <!-- DATABASE CONFIGURATION CARD -->
            <div class="card border-0 shadow-sm" style="border-radius: 8px;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="feather-database text-warning me-2"></i>Database WhatsApp Bridge Credentials
                    </h6>
                </div>
                <div class="card-body p-4">
                    <form id="saveWaDbConfigForm">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <x-ui.modal-form-ui type="input" label="Bridge Node.js URL" name="bridge_url" id="dbBridgeUrl" :value="$config->bridge_url" placeholder="http://127.0.0.1:3210" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.modal-form-ui type="input" label="Bridge Secret Token" name="bridge_token" id="dbBridgeToken" :value="$config->bridge_token" placeholder="wm_erp_whatsapp_secret_token_2026" :required="true" />
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-11 text-muted"><i class="feather-info me-1"></i>Stored in Database table <code>whatsapp_configurations</code></span>
                            <button type="submit" id="btnSaveWaDbConfig" class="btn btn-primary fw-bold px-3 py-1.5 fs-12">
                                <i class="feather-save me-1"></i>Save DB Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- WHATSAPP MESSAGES LOG / INBOX TABLE CARD -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 8px;">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-1">
                            <i class="feather-inbox text-primary me-2"></i>WhatsApp Messages Log & Live Inbox
                        </h6>
                        <p class="text-muted fs-12 mb-0">Live view of incoming customer messages and outbound sent notifications.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="liveBadge" class="badge bg-soft-success text-success border border-success fs-11 px-2.5 py-1">
                            <i class="feather-radio me-1 spin"></i>Live Auto-Refresh ON
                        </span>
                        <button type="button" id="btnRefreshMessages" class="btn btn-sm btn-outline-primary fw-bold px-3 py-1.5 fs-12">
                            <i class="feather-refresh-cw me-1"></i>Refresh Messages
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="waMessagesTable">
                            <thead class="bg-light fs-12 text-uppercase text-muted">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 50px;">#</th>
                                    <th class="py-3">Type / Direction</th>
                                    <th class="py-3">Mobile Number</th>
                                    <th class="py-3">Sender Name</th>
                                    <th class="py-3" style="min-width: 250px;">Message Content</th>
                                    <th class="py-3">Status</th>
                                    <th class="pe-4 py-3 text-end">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody id="waMessagesTbody" class="fs-13">
                                @forelse($messages as $index => $msg)
                                <tr>
                                    <td class="ps-4 fw-semibold text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        @if($msg->direction === 'inbound')
                                            <span class="badge bg-soft-success text-success border border-success-subtle px-2.5 py-1 fs-11 fw-bold">
                                                <i class="feather-arrow-down-left me-1"></i>Incoming
                                            </span>
                                        @else
                                            <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2.5 py-1 fs-11 fw-bold">
                                                <i class="feather-arrow-up-right me-1"></i>Outbound
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong class="text-dark font-monospace">+{{ $msg->sender_number }}</strong>
                                    </td>
                                    <td>
                                        <span class="fw-medium text-dark">{{ $msg->sender_name ?: 'Unknown' }}</span>
                                    </td>
                                    <td>
                                        <div class="text-wrap" style="max-width: 380px; word-break: break-word;">
                                            @if($msg->message_type === 'document')
                                                <span class="badge bg-light text-dark border me-1"><i class="feather-file-text me-1 text-danger"></i>Document</span>
                                            @elseif($msg->message_type === 'image')
                                                <span class="badge bg-light text-dark border me-1"><i class="feather-image me-1 text-info"></i>Image</span>
                                            @endif
                                            <span class="text-secondary">{{ $msg->message_body }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if($msg->status === 'received')
                                            <span class="badge bg-success-subtle text-success px-2 py-0.5 fs-11">Received</span>
                                        @elseif($msg->status === 'sent' || $msg->status === 'delivered')
                                            <span class="badge bg-info-subtle text-info px-2 py-0.5 fs-11">Sent</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary px-2 py-0.5 fs-11">{{ ucfirst($msg->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="pe-4 text-end text-muted fs-12">
                                        {{ $msg->created_at ? $msg->created_at->format('d M Y, h:i A') : '--' }}
                                    </td>
                                </tr>
                                @empty
                                <tr id="emptyMsgRow">
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="feather-inbox fs-32 d-block mb-2 text-secondary"></i>
                                        <span>No WhatsApp messages logged yet. Incoming & Outbound messages will automatically appear here.</span>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TEST RESULT NOTIFICATION MODAL -->
<x-ui.modal id="waResultModal" title="<i class='feather-info me-1.5 text-primary'></i>WhatsApp Notification" size="lg" :centered="true" :showFooter="false">
    <div class="py-4 px-3 text-center">
        <div id="waResultIcon" class="mb-3 d-flex justify-content-center"></div>
        <h4 id="waResultTitle" class="fw-bold text-dark mb-3 fs-18"></h4>
        <div id="waResultMessage" class="alert alert-light border text-center fs-13 mb-4 font-monospace p-3.5 text-break shadow-2xs rounded-3 mx-auto" style="max-width: 520px; background-color: #f8fafc; border-color: #e2e8f0 !important; color: #334155; line-height: 1.6;"></div>
        <div class="d-flex justify-content-center mt-3">
            <button type="button" class="btn btn-primary fw-bold px-5 py-2 fs-13 shadow-2xs rounded-3" data-bs-dismiss="modal" style="min-width: 140px;">OK</button>
        </div>
    </div>
</x-ui.modal>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        let pollTimer = null;

        function showResultModal(isSuccess, title, message) {
            const iconHtml = isSuccess 
                ? '<div class="avatar avatar-xl bg-soft-success text-success rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-2xs" style="width: 64px; height: 64px; border: 2px solid rgba(34, 197, 94, 0.2);"><i class="feather-check-circle fs-32"></i></div>'
                : '<div class="avatar avatar-xl bg-soft-danger text-danger rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-2xs" style="width: 64px; height: 64px; border: 2px solid rgba(239, 68, 68, 0.2);"><i class="feather-alert-triangle fs-32"></i></div>';
            
            $('#waResultIcon').html(iconHtml);
            $('#waResultTitle').text(title).attr('class', isSuccess ? 'fw-bold text-success mb-2 fs-18' : 'fw-bold text-danger mb-2 fs-18');
            $('#waResultMessage').text(message);
            
            const modalEl = document.getElementById('waResultModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }

        function checkWhatsAppStatus() {
            $.ajax({
                url: "{{ route('crm.whatsapp.status') }}",
                method: "GET",
                success: function(res) {
                    if (res.status === 'connected') {
                        $('#waStatusBadge').attr('class', 'badge bg-success px-2.5 py-1 fs-11').html('<i class="feather-check me-1"></i>Connected');
                        $('#waStatusIcon').html('<div class="avatar avatar-xl bg-soft-success text-success rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;"><i class="feather-check-circle fs-36"></i></div>');
                        $('#waStatusTitle').text('WhatsApp Account Connected').attr('class', 'fw-bold text-success mb-2');
                        $('#waStatusText').text('Your device is linked and active. Quotations will be delivered instantly.');
                        
                        const userName = res.user ? (res.user.name || res.user.id || 'Linked Account') : 'Linked Account';
                        const userJid = res.user ? (res.user.id || 'N/A') : 'Active';
                        $('#waUserName').text(userName);
                        $('#waUserJid').text('JID: ' + userJid);

                        $('#waQrBox').addClass('d-none');
                        $('#waDeviceInfo').removeClass('d-none');
                        $('#btnDisconnectWA').removeClass('d-none');
                        $('#btnConnectWA').addClass('d-none');
                        $('#btnSubmitQuickWa').prop('disabled', false);

                    } else if (res.status === 'qr' && res.qr) {
                        $('#waStatusBadge').attr('class', 'badge bg-warning text-dark px-2.5 py-1 fs-11').html('<i class="feather-camera me-1"></i>Scan QR');
                        $('#waStatusIcon').html('<div class="avatar avatar-xl bg-soft-warning text-warning rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;"><i class="feather-qr-code fs-36"></i></div>');
                        $('#waStatusTitle').text('Scan QR Code to Connect').attr('class', 'fw-bold text-dark mb-2');
                        $('#waStatusText').text('Please open WhatsApp on your phone and scan the QR code below.');

                        $('#waQrImg').attr('src', res.qr);
                        $('#waQrBox').removeClass('d-none');
                        $('#waDeviceInfo').addClass('d-none');
                        $('#btnDisconnectWA').addClass('d-none');
                        $('#btnConnectWA').addClass('d-none');
                        $('#btnSubmitQuickWa').prop('disabled', true);

                        clearTimeout(pollTimer);
                        pollTimer = setTimeout(checkWhatsAppStatus, 2500);

                    } else if (res.status === 'connecting') {
                        $('#waStatusBadge').attr('class', 'badge bg-info text-dark px-2.5 py-1 fs-11').html('<i class="feather-loader spin me-1"></i>Connecting...');
                        $('#waStatusIcon').html('<div class="avatar avatar-xl bg-soft-info text-info rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;"><i class="feather-loader spin fs-36"></i></div>');
                        $('#waStatusTitle').text('Initializing Baileys Socket...').attr('class', 'fw-bold text-info mb-2');
                        $('#waStatusText').text('Starting WhatsApp engine and fetching QR code...');

                        $('#waQrBox').addClass('d-none');
                        $('#waDeviceInfo').addClass('d-none');
                        $('#btnDisconnectWA').addClass('d-none');
                        $('#btnConnectWA').addClass('d-none');

                        clearTimeout(pollTimer);
                        pollTimer = setTimeout(checkWhatsAppStatus, 2500);

                    } else {
                        $('#waStatusBadge').attr('class', 'badge bg-danger px-2.5 py-1 fs-11').html('<i class="feather-x me-1"></i>Disconnected');
                        $('#waStatusIcon').html('<div class="avatar avatar-xl bg-soft-danger text-danger rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;"><i class="feather-smartphone-off fs-36"></i></div>');
                        $('#waStatusTitle').text('WhatsApp Disconnected').attr('class', 'fw-bold text-danger mb-2');
                        $('#waStatusText').text(res.message || 'No WhatsApp account linked. Click "Generate QR Code" to connect.');

                        $('#waQrBox').addClass('d-none');
                        $('#waDeviceInfo').addClass('d-none');
                        $('#btnDisconnectWA').addClass('d-none');
                        $('#btnConnectWA').removeClass('d-none');
                        $('#btnSubmitQuickWa').prop('disabled', true);
                    }
                },
                error: function() {
                    $('#waStatusBadge').attr('class', 'badge bg-dark px-2.5 py-1 fs-11').html('<i class="feather-alert-triangle me-1"></i>Bridge Offline');
                    $('#waStatusIcon').html('<div class="avatar avatar-xl bg-soft-dark text-dark rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;"><i class="feather-server fs-36"></i></div>');
                    $('#waStatusTitle').text('WhatsApp Bridge Offline').attr('class', 'fw-bold text-dark mb-2');
                    $('#waStatusText').text('The Node.js WhatsApp bridge (services/whatsapp-bridge) is offline. Start the server using: npm start');

                    $('#waQrBox').addClass('d-none');
                    $('#waDeviceInfo').addClass('d-none');
                    $('#btnDisconnectWA').addClass('d-none');
                    $('#btnConnectWA').removeClass('d-none');
                    $('#btnSubmitQuickWa').prop('disabled', true);
                }
            });
        }

        // Initial Load
        checkWhatsAppStatus();

        $('#btnRefreshWA').on('click', function() {
            checkWhatsAppStatus();
        });

        $('#btnConnectWA').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Generating...');
            $.ajax({
                url: "{{ route('crm.whatsapp.connect') }}",
                method: "POST",
                data: { _token: "{{ csrf_token() }}" },
                success: function() {
                    btn.prop('disabled', false).html('<i class="feather-smartphone me-1.5"></i>Generate QR Code');
                    checkWhatsAppStatus();
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="feather-smartphone me-1.5"></i>Generate QR Code');
                    showResultModal(false, "Bridge Error", "WhatsApp Node.js bridge server is offline. Please start it using 'npm start' inside services/whatsapp-bridge.");
                }
            });
        });

        $('#btnDisconnectWA').on('click', function() {
            if (!confirm('Are you sure you want to disconnect this WhatsApp session? You will need to scan QR code again to reconnect.')) {
                return;
            }

            $.ajax({
                url: "{{ route('crm.whatsapp.disconnect') }}",
                method: "DELETE",
                data: { _token: "{{ csrf_token() }}" },
                success: function(res) {
                    showResultModal(true, "Session Disconnected", res.message || "WhatsApp session cleared.");
                    checkWhatsAppStatus();
                }
            });
        });

        $('#sendQuickWaForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const btn = $('#btnSubmitQuickWa');
            const origHtml = btn.html();
            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Sending Message...');

            $.ajax({
                url: "{{ route('crm.whatsapp.sendMessage') }}",
                method: "POST",
                data: form.serialize(),
                success: function(res) {
                    btn.prop('disabled', false).html(origHtml);
                    showResultModal(true, "Message Delivered!", res.message);
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(origHtml);
                    const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to send WhatsApp message.';
                    showResultModal(false, "Dispatch Failed", errMsg);
                }
            });
        });

        $('#saveWaDbConfigForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const btn = $('#btnSaveWaDbConfig');
            const origHtml = btn.html();
            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Saving...');

            $.ajax({
                url: "{{ route('crm.whatsapp.updateConfig') }}",
                method: "POST",
                data: form.serialize(),
                success: function(res) {
                    btn.prop('disabled', false).html(origHtml);
                    showResultModal(true, "Configuration Saved", res.message);
                    checkWhatsAppStatus();
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(origHtml);
                    const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to update Database WhatsApp configuration.';
                    showResultModal(false, "Save Failed", errMsg);
                }
            });
        });

        // WHATSAPP MESSAGES LOG FETCHING & AUTO-POLLING
        function fetchMessagesList() {
            $.ajax({
                url: "{{ route('crm.whatsapp.messages') }}",
                method: "GET",
                success: function(res) {
                    if (res.success && res.messages) {
                        renderMessagesTable(res.messages);
                    }
                }
            });
        }

        function renderMessagesTable(messages) {
            if (!messages || messages.length === 0) {
                $('#waMessagesTbody').html(`
                    <tr id="emptyMsgRow">
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="feather-inbox fs-32 d-block mb-2 text-secondary"></i>
                            <span>No WhatsApp messages logged yet. Incoming & Outbound messages will automatically appear here.</span>
                        </td>
                    </tr>
                `);
                return;
            }

            let rowsHtml = '';
            messages.forEach(function(msg, idx) {
                const isIncoming = msg.direction === 'inbound';
                const dirBadge = isIncoming
                    ? `<span class="badge bg-soft-success text-success border border-success-subtle px-2.5 py-1 fs-11 fw-bold"><i class="feather-arrow-down-left me-1"></i>Incoming</span>`
                    : `<span class="badge bg-soft-primary text-primary border border-primary-subtle px-2.5 py-1 fs-11 fw-bold"><i class="feather-arrow-up-right me-1"></i>Outbound</span>`;
                
                let typeBadge = '';
                if (msg.message_type === 'document') {
                    typeBadge = `<span class="badge bg-light text-dark border me-1"><i class="feather-file-text me-1 text-danger"></i>Document</span>`;
                } else if (msg.message_type === 'image') {
                    typeBadge = `<span class="badge bg-light text-dark border me-1"><i class="feather-image me-1 text-info"></i>Image</span>`;
                }

                const statusBadge = isIncoming
                    ? `<span class="badge bg-success-subtle text-success px-2 py-0.5 fs-11">Received</span>`
                    : `<span class="badge bg-info-subtle text-info px-2 py-0.5 fs-11">Sent</span>`;

                rowsHtml += `
                    <tr>
                        <td class="ps-4 fw-semibold text-muted">${idx + 1}</td>
                        <td>${dirBadge}</td>
                        <td><strong class="text-dark font-monospace">+${msg.sender_number}</strong></td>
                        <td><span class="fw-medium text-dark">${msg.sender_name}</span></td>
                        <td>
                            <div class="text-wrap" style="max-width: 380px; word-break: break-word;">
                                ${typeBadge}
                                <span class="text-secondary">${msg.message_body || ''}</span>
                            </div>
                        </td>
                        <td>${statusBadge}</td>
                        <td class="pe-4 text-end text-muted fs-12">${msg.time_formatted || '--'}</td>
                    </tr>
                `;
            });

            $('#waMessagesTbody').html(rowsHtml);
        }

        // Auto Poll Messages every 5 seconds
        setInterval(fetchMessagesList, 5000);

        $('#btnRefreshMessages').on('click', function() {
            const btn = $(this);
            btn.addClass('disabled').find('i').addClass('spin');
            fetchMessagesList();
            setTimeout(function() {
                btn.removeClass('disabled').find('i').removeClass('spin');
            }, 800);
        });
    });
</script>
@endpush
