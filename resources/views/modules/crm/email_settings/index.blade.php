@extends('layouts.duralux')

@section('title', 'SMTP Email Configurations | CRM Settings')
@section('page-title', 'Email Accounts & Database SMTP Setup')
@section('breadcrumb', 'CRM / Settings / Email Accounts')

@section('content')
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="feather-mail text-primary me-2"></i>Database Multi-Account SMTP & Email Setup</h4>
            <p class="text-muted fs-12 mb-0">Configure corporate SMTP credentials for sending quotations, client emails, and background sync stored in database.</p>
        </div>
        <button type="button" class="btn btn-primary fw-bold px-3 py-2 fs-12" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="feather-plus me-1.5"></i>Add New Email Account
        </button>
    </div>

    <!-- TENANT / COMPANY / BRANCH CONTEXT BADGES -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 8px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2 fs-12">
            <div>
                <span class="fw-bold text-secondary me-2"><i class="feather-layers me-1"></i>Current Context:</span>
                <span class="badge bg-primary me-1"><i class="feather-globe me-1"></i>Tenant ID: {{ $tenantId }}</span>
                <span class="badge bg-info text-dark me-1"><i class="feather-briefcase me-1"></i>Company ID: {{ $companyId ?: 'All Companies' }}</span>
                <span class="badge bg-secondary me-1"><i class="feather-git-branch me-1"></i>Branch ID: {{ $branchId ?: 'All Branches' }}</span>
            </div>
            <div>
                <span class="text-muted fs-11"><i class="feather-database me-1 text-success"></i>Strict Database Configuration Enabled</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm fs-13 mb-4">
            <i class="feather-check-circle me-1.5"></i>{{ session('success') }}
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4" style="border-radius: 8px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 fs-13">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Account Name</th>
                            <th>Email Address</th>
                            <th>Company & Branch</th>
                            <th>SMTP Host & Port</th>
                            <th>Encryption</th>
                            <th>Status / Default</th>
                            <th class="text-end pe-3">Testing & Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $acc)
                            <tr>
                                <td class="ps-3 fw-bold text-dark">
                                    {{ $acc->name }}
                                    @if($acc->is_default)
                                        <span class="badge bg-soft-primary text-primary ms-1 fs-10">Default</span>
                                    @endif
                                </td>
                                <td class="font-monospace text-primary fw-semibold">{{ $acc->email_address }}</td>
                                <td>
                                    <span class="badge bg-soft-info text-dark fs-11">{{ $acc->company?->name ?? 'All Companies' }}</span>
                                    <span class="badge bg-soft-secondary text-muted fs-11">{{ $acc->branch?->name ?? 'All Branches' }}</span>
                                </td>
                                <td>
                                    <span class="font-monospace text-dark">{{ $acc->host }}:{{ $acc->port }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border text-uppercase">{{ $acc->encryption }}</span>
                                </td>
                                <td>
                                    @if($acc->is_active)
                                        <span class="badge bg-soft-success text-success fw-bold"><i class="feather-check me-1"></i>Active</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-muted">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1.5">
                                        <button type="button" class="btn btn-xs btn-outline-primary fw-bold btn-test-connection" data-account-id="{{ $acc->id }}" data-account-name="{{ $acc->name }}">
                                            <i class="feather-activity me-1"></i>Test Connection
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-success fw-bold btn-open-test-mail-modal" data-account-id="{{ $acc->id }}" data-account-name="{{ $acc->name }}" data-email-address="{{ $acc->email_address }}">
                                            <i class="feather-send me-1"></i>Send Test Email
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="feather-mail fs-36 text-muted mb-2 d-block opacity-50"></i>
                                    No custom SMTP email accounts configured in Database for this context. Click "Add New Email Account" to setup.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ADD ACCOUNT MODAL -->
<x-ui.modal id="addAccountModal" title="<i class='feather-mail text-primary me-1.5'></i>Add SMTP Email Account" size="lg" :centered="true" :formAction="route('crm.emailSettings.store')" formMethod="POST" submitText="Save SMTP Account" closeText="Cancel">
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
                    <option value="{{ $comp->id }}" {{ $companyId == $comp->id ? 'selected' : '' }}>{{ $comp->name }}</option>
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

<!-- TEST RESULT NOTIFICATION MODAL (NO JS ALERT) -->
<x-ui.modal id="connectionResultModal" title="<i class='feather-info me-1.5 text-primary'></i>Status Result" size="md" :centered="true" :showFooter="false">
    <div class="p-3 text-center">
        <div id="connectionResultIcon" class="mb-3"></div>
        <h5 id="connectionResultTitle" class="fw-bold text-dark mb-2 fs-16"></h5>
        <div id="connectionResultMessage" class="alert alert-light border text-start fs-12 mb-4 font-monospace p-3 text-break"></div>
        <button type="button" class="btn btn-primary fw-bold px-4" data-bs-dismiss="modal">OK</button>
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
            <x-ui.modal-form-ui type="input" label="Subject" name="subject" id="testMailSubject" value="Test Email from CRM System" :required="true" />
        </div>
        <div class="mb-3">
            <x-ui.modal-form-ui type="textarea" label="Message Body" name="body_html" id="testMailBody" rows="4" :required="true">Hello! This is a test email sent from your CRM System to verify SMTP settings are working correctly.</x-ui.modal-form-ui>
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
                ? '<div class="avatar avatar-lg bg-soft-success text-success rounded-circle mx-auto mb-2" style="width: 50px; height: 50px; display: inline-flex; align-items: center; justify-content: center;"><i class="feather-check-circle fs-28"></i></div>'
                : '<div class="avatar avatar-lg bg-soft-danger text-danger rounded-circle mx-auto mb-2" style="width: 50px; height: 50px; display: inline-flex; align-items: center; justify-content: center;"><i class="feather-alert-triangle fs-28"></i></div>';
            
            $('#connectionResultIcon').html(iconHtml);
            $('#connectionResultTitle').text(title).attr('class', isSuccess ? 'fw-bold text-success mb-2 fs-16' : 'fw-bold text-danger mb-2 fs-16');
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
                url: "/crm/email-settings/" + accId + "/test",
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

            $('#sendTestMailForm').attr('action', '/crm/email-settings/' + accId + '/send-test-email');
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
