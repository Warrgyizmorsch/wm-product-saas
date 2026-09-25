@extends('layouts.duralux')

@section('title', 'SMTP Email Configurations | Platform Settings')
@section('page-title', 'Email Accounts & Database SMTP Setup')
@section('breadcrumb', 'Platform / Settings / Email Accounts')

@section('page-actions')
    <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addAccountModal">
        ADD NEW EMAIL ACCOUNT
    </x-ui.button>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
    <!-- Header Title & Context Strip -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 pb-3 border-bottom">
        <div>
            <h5 class="fw-bold text-dark mb-1"><i class="feather-mail text-primary me-2"></i>Database Multi-Account SMTP & Email Setup</h5>
            <p class="text-muted fs-12 mb-0">Configure corporate SMTP credentials for sending quotations, client emails, and background sync stored in database.</p>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2 fs-12">
            <span class="fw-bold text-secondary me-1"><i class="feather-layers me-1"></i>Current Context:</span>
            <span class="badge erp-badge bg-primary text-white"><i class="feather-globe me-1"></i>Tenant ID: {{ $tenantId }}</span>
            <span class="badge erp-badge bg-info text-dark"><i class="feather-briefcase me-1"></i>Company: {{ $companyId ?: 'All Companies' }}</span>
            <span class="badge erp-badge bg-secondary text-white"><i class="feather-git-branch me-1"></i>Branch: {{ $branchId ?: 'All Branches' }}</span>
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
            <div class="fw-bold mb-1"><i class="feather-alert-triangle me-1"></i>Please fix the following validation errors:</div>
            <ul class="mb-0 ps-3 fs-13">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Table Section with Common Table Component -->
    <div class="table-responsive flex-grow-1">
        <x-ui.odoo-form-ui type="table" id="emailAccountsTable" class="mb-0">
            <thead>
                <tr style="background-color: #e8ecf1 !important;">
                    <th style="background-color: #e8ecf1 !important;" class="ps-3">Account Name</th>
                    <th style="background-color: #e8ecf1 !important;">Email Address</th>
                    <th style="background-color: #e8ecf1 !important;">Scope (Company & Branch)</th>
                    <th style="background-color: #e8ecf1 !important;">SMTP Host & Port</th>
                    <th style="background-color: #e8ecf1 !important;">Encryption</th>
                    <th style="background-color: #e8ecf1 !important;">Status</th>
                    <th style="background-color: #e8ecf1 !important;" class="text-end pe-3">Testing & Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $acc)
                    <tr>
                        <td class="ps-3 fw-bold text-dark">
                            {{ $acc->name }}
                            @if($acc->is_default)
                                <span class="badge bg-soft-primary text-primary ms-1 fs-10 font-monospace border">Default</span>
                            @endif
                        </td>
                        <td class="font-monospace text-primary fw-bold">{{ $acc->email_address }}</td>
                        <td>
                            <span class="badge bg-soft-info text-dark fs-11 border me-1">{{ $acc->company?->name ?? 'All Companies' }}</span>
                            <span class="badge bg-soft-secondary text-muted fs-11 border">{{ $acc->branch?->name ?? 'All Branches' }}</span>
                        </td>
                        <td>
                            <span class="font-monospace text-dark fw-medium">{{ $acc->host }}:{{ $acc->port }}</span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border text-uppercase font-monospace">{{ $acc->encryption }}</span>
                        </td>
                        <td>
                            @if($acc->is_active)
                                <x-ui.status-badge status="Active" type="success" />
                            @else
                                <x-ui.status-badge status="Inactive" type="secondary" />
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex justify-content-end align-items-center gap-2">
                                <button type="button" class="btn btn-xs btn-outline-primary fw-bold btn-test-connection px-2.5 py-1" data-account-id="{{ $acc->id }}" data-account-name="{{ $acc->name }}">
                                    <i class="feather-activity me-1"></i>Test Connection
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-success fw-bold btn-open-test-mail-modal px-2.5 py-1" data-account-id="{{ $acc->id }}" data-account-name="{{ $acc->name }}" data-email-address="{{ $acc->email_address }}">
                                    <i class="feather-send me-1"></i>Send Test Email
                                </button>
                                <form action="{{ route('platform.emailSettings.destroy', $acc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete SMTP account \'{{ $acc->name }}\' ({{ $acc->email_address }})?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger fw-bold px-2.5 py-1">
                                        <i class="feather-trash-2 me-1"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="feather-mail fs-36 text-muted mb-2 d-block opacity-50"></i>
                            No custom SMTP email accounts configured in Database for this context. Click "Add New Email Account" to setup.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </div>
</div>

<!-- ADD ACCOUNT MODAL -->
<x-ui.modal id="addAccountModal" title="<i class='feather-mail text-primary me-1.5'></i>Add SMTP Email Account" size="lg" :centered="true" :formAction="route('platform.emailSettings.store')" formMethod="POST" submitText="Save SMTP Account" closeText="Cancel">
    <div class="row g-3">
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" label="Display Account Name" name="name" placeholder="e.g. Sales Desk" :required="true" />
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" inputType="email" label="Email Address" name="email_address" placeholder="e.g. sales@yourdomain.com" :required="true" />
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="select" label="Scope to Company" name="company_id">
                <option value="">All Companies (Global Default)</option>
                @foreach($companies as $comp)
                    <option value="{{ $comp->id }}" {{ $companyId == $comp->id ? 'selected' : '' }}>{{ $comp->company_name ?: ($comp->name ?: $comp->legal_name) }}</option>
                @endforeach
            </x-ui.modal-form-ui>
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="select" label="Scope to Branch" name="branch_id">
                <option value="">All Branches (Company Default)</option>
                @foreach($branches as $br)
                    <option value="{{ $br->id }}" {{ $branchId == $br->id ? 'selected' : '' }}>{{ $br->name }}</option>
                @endforeach
            </x-ui.modal-form-ui>
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" label="From Name" name="from_name" placeholder="e.g. Warrgyizmorsch Sales" />
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" label="SMTP Host" name="host" placeholder="smtp.gmail.com" :required="true" />
        </div>
        <div class="col-md-4">
            <x-ui.modal-form-ui type="input" inputType="number" label="SMTP Port" name="port" value="587" :required="true" />
        </div>
        <div class="col-md-4">
            <x-ui.modal-form-ui type="select" label="Encryption" name="encryption" :required="true">
                <option value="tls">TLS (587)</option>
                <option value="ssl">SSL (465)</option>
                <option value="none">None</option>
            </x-ui.modal-form-ui>
        </div>
        <div class="col-md-4 d-flex align-items-center pt-3">
            <x-ui.modal-form-ui type="checkbox" label="Set as Default SMTP" name="is_default" value="1" />
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" label="SMTP Username / Email" name="username" placeholder="your_email@gmail.com" :required="true" />
        </div>
        <div class="col-md-6">
            <x-ui.modal-form-ui type="input" inputType="password" label="SMTP Password / App Password" name="password" placeholder="16-character App Password" :required="true" />
        </div>
    </div>
</x-ui.modal>

<!-- TEST RESULT NOTIFICATION MODAL -->
<x-ui.modal id="connectionResultModal" title="<i class='feather-info me-1.5 text-primary'></i>System Notification" size="lg" :centered="true" :showFooter="false">
    <div class="py-4 px-3 text-center">
        <div id="connectionResultIcon" class="mb-3 d-flex justify-content-center"></div>
        <h4 id="connectionResultTitle" class="fw-bold text-dark mb-3 fs-18"></h4>
        <div id="connectionResultMessage" class="alert alert-light border text-center fs-13 mb-4 font-monospace p-3.5 text-break shadow-2xs rounded-3 mx-auto" style="max-width: 520px; background-color: #f8fafc; border-color: #e2e8f0 !important; color: #334155; line-height: 1.6;"></div>
        <div class="d-flex justify-content-center mt-3">
            <button type="button" class="btn btn-primary fw-bold px-5 py-2 fs-13 shadow-2xs rounded-3" data-bs-dismiss="modal" style="min-width: 140px;">OK</button>
        </div>
    </div>
</x-ui.modal>

<!-- SEND TEST EMAIL MODAL -->
<x-ui.modal id="sendTestMailModal" title="<i class='feather-send text-success me-1.5'></i>Send Test Email" size="md" :centered="true" :showFooter="false">
    <form id="sendTestMailForm" action="" method="POST">
        @csrf
        <div class="mb-3">
            <x-ui.modal-form-ui type="input" label="Sending From SMTP Account" id="testMailFromAccount" readonly="true" />
        </div>
        <div class="mb-3">
            <x-ui.modal-form-ui type="input" inputType="email" label="Recipient Email Address (To)" name="to_email" id="testMailToEmail" placeholder="recipient@example.com" :required="true" />
        </div>
        <div class="mb-3">
            <x-ui.modal-form-ui type="input" label="Subject" name="subject" id="testMailSubject" value="Test Email from Platform System" :required="true" />
        </div>
        <div class="mb-3">
            <x-ui.modal-form-ui type="textarea" label="Message Body" name="body_html" id="testMailBody" rows="4" :required="true">Hello! This is a test email sent from your Platform System to verify SMTP settings are working correctly.</x-ui.modal-form-ui>
        </div>
        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border fw-bold" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" id="btnSubmitSendTestMail" class="btn btn-sm btn-success fw-bold px-4">
                <i class="feather-send me-1"></i>Send Test Email
            </button>
        </div>
    </form>
</x-ui.modal>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        function showNotificationModal(isSuccess, title, message) {
            const iconHtml = isSuccess 
                ? '<div class="avatar avatar-xl bg-soft-success text-success rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-2xs" style="width: 64px; height: 64px; border: 2px solid rgba(34, 197, 94, 0.2);"><i class="feather-check-circle fs-32"></i></div>'
                : '<div class="avatar avatar-xl bg-soft-danger text-danger rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-2xs" style="width: 64px; height: 64px; border: 2px solid rgba(239, 68, 68, 0.2);"><i class="feather-alert-triangle fs-32"></i></div>';
            
            $('#connectionResultIcon').html(iconHtml);
            $('#connectionResultTitle').text(title).attr('class', isSuccess ? 'fw-bold text-success mb-2 fs-18' : 'fw-bold text-danger mb-2 fs-18');
            $('#connectionResultMessage').text(message);
            
            const modalEl = document.getElementById('connectionResultModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }

        // 1-Click Connection Test
        $('.btn-test-connection').on('click', function() {
            const btn = $(this);
            const accId = btn.attr('data-account-id');
            const origHtml = btn.html();

            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Testing...');

            $.ajax({
                url: "{{ url('platform/email-settings') }}/" + accId + "/test",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function(res) {
                    btn.prop('disabled', false).html(origHtml);
                    showNotificationModal(true, "SMTP Connection Successful!", res.message);
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(origHtml);
                    const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Connection Test Failed.';
                    showNotificationModal(false, "Connection Failed", errMsg);
                }
            });
        });

        // Open Send Test Email Modal
        $('.btn-open-test-mail-modal').on('click', function() {
            const accId = $(this).attr('data-account-id');
            const accName = $(this).attr('data-account-name');
            const accEmail = $(this).attr('data-email-address');

            $('#sendTestMailForm').attr('action', '/platform/email-settings/' + accId + '/send-test-email');
            $('#testMailFromAccount').val(accName + ' (' + accEmail + ')');
            if(!$('#testMailToEmail').val()) {
                $('#testMailToEmail').val(accEmail);
            }

            const modalEl = document.getElementById('sendTestMailModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        });

        // Handle Send Test Email Form Submission
        $('#sendTestMailForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const btn = $('#btnSubmitSendTestMail');
            const origHtml = btn.html();

            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Sending Email...');

            $.ajax({
                url: form.attr('action'),
                method: "POST",
                data: form.serialize(),
                success: function(res) {
                    btn.prop('disabled', false).html(origHtml);
                    
                    const sendModalEl = document.getElementById('sendTestMailModal');
                    const sendModal = bootstrap.Modal.getInstance(sendModalEl);
                    if (sendModal) sendModal.hide();

                    showNotificationModal(true, "Email Dispatched Successfully!", res.message);
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(origHtml);
                    const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to send test email.';
                    showNotificationModal(false, "Email Dispatch Failed", errMsg);
                }
            });
        });
    });
</script>
@endpush
