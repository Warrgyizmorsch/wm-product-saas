@extends('layouts.duralux')

@section('title', __('hrms.leave.app.title') . ' | SaaS ERP')
@section('page-title', __('hrms.leave.app.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.leave.app.title'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <button type="button" id="btnApplyEncashmentHeader" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1 d-none" data-bs-toggle="modal" data-bs-target="#applyEncashmentModal" style="height: 38px;">
            <i class="feather-dollar-sign"></i> {{ __('hrms.leave.encashment_app.apply_encashment') }}
        </button>
        <button type="button" id="btnApplyLeaveHeader" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#applyLeaveModal" style="height: 38px;">
            <i class="feather-plus"></i> {{ __('hrms.leave.app.apply_leave') }}
        </button>
    </div>
@endsection

@php
    $formatLeaveRulePoints = static function (?array $rules): array {
        if (empty($rules)) {
            return [];
        }

        $humanize = static fn ($value) => strtolower(str_replace('_', ' ', (string) $value));
        $formatNumber = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        $sections = [];

        if (!empty($rules['application'])) {
            $application = $rules['application'];
            $points = [];

            if (!empty($application['apply_in_advance'])) {
                $points[] = 'You have to apply for this leave at least ' . ($application['advance_days'] ?? 0) . ' day(s) in advance.';
            } else {
                $points[] = 'You can apply for this leave without an advance-day restriction.';
            }

            $points[] = 'One request can be from ' . ($application['min_duration'] ?? 1) . ' to ' . ($application['max_duration'] ?? 10) . ' day(s).';

            if (!empty($application['require_attachment'])) {
                $points[] = 'You must attach supporting documents when the leave duration is more than ' . ($application['attachment_days'] ?? 0) . ' day(s).';
            }

            $sections[] = ['title' => 'Application Rules', 'icon' => 'feather-file-text', 'points' => $points];
        }

        if (!empty($rules['approval'])) {
            $approval = $rules['approval'];
            $points = [];

            if (($approval['workflow_level'] ?? null) === 'auto') {
                $points[] = 'This leave is approved automatically after submission.';
            } elseif (($approval['workflow_level'] ?? null) === '2_level') {
                $points[] = 'This leave needs two approvals: first by ' . $humanize($approval['first_approver'] ?? 'reporting_manager') . ', then by ' . $humanize($approval['second_approver'] ?? 'hr_manager') . '.';
            } else {
                $points[] = 'This leave needs approval from ' . $humanize($approval['first_approver'] ?? 'reporting_manager') . '.';
            }

            $sections[] = ['title' => 'Approval Rules', 'icon' => 'feather-check-square', 'points' => $points];
        }

        if (!empty($rules['accrual'])) {
            $accrual = $rules['accrual'];
            $points = [];
            $unit = $humanize($accrual['calculate_in'] ?? 'days');

            if (($accrual['quota_type'] ?? 'fixed') === 'unlimited') {
                $points[] = 'This leave has unlimited quota.';
            } else {
                $points[] = 'You get ' . $formatNumber($accrual['quota_value'] ?? 0) . ' ' . $unit . ' of this leave.';
            }

            $rate = $accrual['rate'] ?? 'immediate';
            if ($rate === 'attendance') {
                $points[] = 'Leave is earned based on attendance: ' . ($accrual['attendance_earn'] ?? 1) . ' day for every ' . ($accrual['attendance_period'] ?? 20) . ' present day(s).';
            } elseif ($rate === 'periodic') {
                $points[] = 'Leave is credited periodically as configured in the leave policy.';
            } else {
                $points[] = 'Leave is credited immediately.';
            }

            if (!empty($accrual['limit_carry'])) {
                $points[] = 'Maximum accumulated balance is limited to ' . ($accrual['max_accum'] ?? 0) . ' day(s).';
            }

            $sections[] = ['title' => 'Accrual Rules', 'icon' => 'feather-calendar', 'points' => $points];
        }

        if (!empty($rules['yearend'])) {
            $yearend = $rules['yearend'];
            $points = [];

            if (($yearend['action'] ?? 'lapse') === 'carry_forward') {
                $points[] = 'Unused leave can be carried forward at year end.';
                $points[] = 'Maximum carry-forward limit is ' . ($yearend['max_carry'] ?? 0) . ' day(s).';
            } elseif (($yearend['action'] ?? null) === 'encash') {
                $points[] = 'Unused leave can be encashed at year end.';
                $points[] = 'Maximum encashment limit is ' . ($yearend['max_encash'] ?? 0) . ' day(s).';
            } else {
                $points[] = 'Unused leave lapses at year end.';
            }

            $sections[] = ['title' => 'Year-End Rules', 'icon' => 'feather-refresh-cw', 'points' => $points];
        }

        if (!empty($rules['probation'])) {
            $probation = $rules['probation'];
            $point = match ($probation['rule'] ?? 'allow') {
                'disallow' => 'You cannot apply for this leave during probation.',
                'allow_after_months' => 'You can apply for this leave after completing ' . ($probation['months'] ?? 0) . ' month(s) from joining.',
                default => 'You can apply for this leave during the probation.',
            };
            $sections[] = ['title' => 'Probation Rules', 'icon' => 'feather-shield', 'points' => [$point]];
        }

        if (!empty($rules['notice'])) {
            $notice = $rules['notice'];
            $point = match ($notice['rule'] ?? 'allow') {
                'disallow' => 'You cannot apply for this leave during the notice period.',
                'special_approval' => 'You need special HR approval to apply during the notice period.',
                default => 'You can apply for this leave during the notice period.',
            };
            $sections[] = ['title' => 'Notice Period Rules', 'icon' => 'feather-alert-triangle', 'points' => [$point]];
        }

        return $sections;
    };
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .text-primary {
            color: var(--bs-primary) !important;
        }
        .leave-rules-icon-btn {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid rgba(var(--bs-primary-rgb), 0.18) !important;
            background-color: rgba(var(--bs-primary-rgb), 0.08) !important;
            color: var(--bs-primary) !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .leave-rules-icon-btn:hover {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
            box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), 0.22) !important;
        }
        .leave-rule-detail-section {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            background: #fff;
            height: 100%;
        }
        .leave-rule-detail-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .leave-rule-points {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }
        .leave-rule-point {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #475569;
            font-size: 13px;
            line-height: 1.55;
        }
        .leave-rule-point::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background-color: var(--bs-primary);
            box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.1);
            margin-top: 7px;
            flex: 0 0 auto;
        }
        .leave-balance-card {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease-in-out;
        }
        .leave-balance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        }
        .policy-info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .policy-info-item {
            font-size: 12.5px;
            margin-bottom: 10px;
            color: #475569;
            display: flex;
            align-items: center;
        }
        .policy-info-item:last-child {
            margin-bottom: 0;
        }
        .policy-icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #ffffff;
            color: #3b82f6;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 10px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            flex-shrink: 0;
        }
        .policy-icon-wrapper i {
            font-size: 12px;
            color: #3b82f6;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #d97706;
        }
        .badge-approved {
            background-color: #d1fae5;
            color: #059669;
        }
        .badge-rejected {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .badge-unauthorized {
            background-color: #f3e8ff;
            color: #7c3aed;
        }
        .badge-unpaid {
            background-color: #f1f5f9;
            color: #475569;
        }
        .erp-pagination-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: auto !important;
            padding: 20px 15px 15px 15px !important;
            border-top: 1px solid #f1f5f9;
        }
        .erp-pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0;
            padding-left: 0;
            list-style: none;
        }
        .erp-pagination .page-item {
            display: inline-block;
        }
        .erp-pagination .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50% !important;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            cursor: pointer;
        }
        .erp-pagination .page-link:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.08);
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }
        .erp-pagination .page-item.active .page-link {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 10px rgba(var(--bs-primary-rgb), 0.2);
        }
        .erp-pagination .page-item.disabled .page-link {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }
        .erp-pagination-info {
            font-size: 12px;
            color: #64748b;
        }
        .odoo-underline-input {
            border: none !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            background-color: transparent !important;
            box-shadow: none !important;
            transition: border-color 0.2s ease-in-out;
        }
        .odoo-underline-input:focus {
            border-bottom-color: var(--bs-primary) !important;
        }
        .select2-container--default .select2-selection--single {
            border: none !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            height: auto !important;
            padding-top: 4px;
            padding-bottom: 4px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-right: 28px !important;
            padding-left: 0 !important;
            font-size: 13px !important;
            color: #212529 !important;
            white-space: nowrap !important;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 24px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 32px !important;
            right: 0 !important;
        }
        .select2-container .select2-dropdown {
            border: 1px solid #cbd5e1 !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05) !important;
        }
        .select2-results__option {
            font-size: 13px !important;
            padding: 8px 12px !important;
            white-space: nowrap !important;
        }
        /* Underlined Horizontal Tabs (matching Org Structure theme) */
        #leavesModuleTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #leavesModuleTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #leavesModuleTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
            font-weight: 600;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script>
        var employeeDataMap = @json($employeeDataMap);

        $(document).ready(function() {
            // Append modals to document.body to completely prevent blur/backdrop z-index issues
            ['applyLeaveModal', 'rejectLeaveModal', 'applyEncashmentModal'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el && el.parentNode !== document.body) {
                    document.body.appendChild(el);
                }
            });
            $('[id^="leaveRulesModal"]').appendTo('body');

            $('button[data-bs-toggle="tab"]').on('shown.bs.tab click', function (e) {
                var target = $(this).attr('data-bs-target') || $(this).attr('id');
                var isEncashment = target === '#pane-leave-encashments' || target === 'tab-encashments';

                if (isEncashment) {
                    $('#btnApplyLeaveHeader').addClass('d-none');
                    $('#btnApplyEncashmentHeader').removeClass('d-none');
                } else {
                    $('#btnApplyEncashmentHeader').addClass('d-none');
                    $('#btnApplyLeaveHeader').removeClass('d-none');
                }

                var tabName = isEncashment ? 'encashments' : 'applications';
                var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tabName;
                window.history.pushState({ path: newUrl }, '', newUrl);
            });

            var activeTabParam = new URLSearchParams(window.location.search).get('tab');
            if (activeTabParam === 'encashments') {
                var encashTab = document.querySelector('#tab-encashments');
                if (encashTab) {
                    var tabObj = new bootstrap.Tab(encashTab);
                    tabObj.show();
                    $('#btnApplyLeaveHeader').addClass('d-none');
                    $('#btnApplyEncashmentHeader').removeClass('d-none');
                }
            } else {
                $('#btnApplyEncashmentHeader').addClass('d-none');
                $('#btnApplyLeaveHeader').removeClass('d-none');
            }

            // Initialize custom Select2 dropdowns parented inside modal-content
            function initModalSelects() {
                $('.odoo-select2-custom').each(function() {
                    var $select = $(this);
                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }
                    $select.select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $select.closest('.modal-content'),
                        width: '100%'
                    });
                });
            }

            initModalSelects();

            // Initialize Bootstrap Popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl)
            });

            // Dynamic Employee On-Behalf selection change handler
            $('#employee_select').on('change', function() {
                var empId = $(this).val();
                var $leaveTypeSelect = $('#leave_type_select');
                
                $leaveTypeSelect.empty().append('<option value="">{{ __('hrms.leave.app.select_leave_type') }}</option>');

                if (empId && employeeDataMap[empId]) {
                    var types = employeeDataMap[empId];
                    types.forEach(function(t) {
                        var text = t.name + ' (' + '{{ __('hrms.leave.app.remaining') }}' + ': ' + t.remaining + ' / ' + t.quota + ' ' + '{{ __('hrms.leave.days') }}' + ')';
                        var option = $('<option>', {
                            value: t.id,
                            text: text
                        });
                        option.attr('data-rules', JSON.stringify(t.rules));
                        option.attr('data-type', t.type);
                        $leaveTypeSelect.append(option);
                    });
                }
                
                $leaveTypeSelect.trigger('change');
            });

            // Trigger employee_select change on load to initialize first select list
            if ($('#employee_select').length) {
                $('#employee_select').trigger('change');
            } else {
                // If normal employee (who doesn't have the employee select dropdown), populate leave types directly using their own ID
                var defaultEmpId = "{{ $employee ? $employee->id : '' }}";
                if (defaultEmpId && employeeDataMap[defaultEmpId]) {
                    var $leaveTypeSelect = $('#leave_type_select');
                    $leaveTypeSelect.empty().append('<option value="">{{ __('hrms.leave.app.select_leave_type') }}</option>');
                    var types = employeeDataMap[defaultEmpId];
                    types.forEach(function(t) {
                        var text = t.name + ' (' + '{{ __('hrms.leave.app.remaining') }}' + ': ' + t.remaining + ' / ' + t.quota + ' ' + '{{ __('hrms.leave.days') }}' + ')';
                        var option = $('<option>', {
                            value: t.id,
                            text: text
                        });
                        option.attr('data-rules', JSON.stringify(t.rules));
                        option.attr('data-type', t.type);
                        $leaveTypeSelect.append(option);
                    });
                    $leaveTypeSelect.trigger('change');
                }
            }

            // Dynamic Policy Display & Dates logic
            $('#leave_type_select').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var rulesStr = selectedOption.attr('data-rules');
                var leaveType = selectedOption.attr('data-type') || 'paid';
                
                if (!rulesStr) {
                    $('#policy_display').addClass('d-none');
                    return;
                }

                try {
                    var rules = JSON.parse(rulesStr);
                    $('#policy_display').removeClass('d-none');
                    
                    var appRules = rules.application || {};
                    var probRules = rules.probation || {};
                    var noticeRules = rules.notice || {};
                    var approvalRules = rules.approval || {};
                    var accrualRules = rules.accrual || {};

                    // Type label
                    $('#policy_type').text(leaveType.toUpperCase());

                    // Minimum & Maximum Duration
                    var minDur = appRules.min_duration || 1;
                    var maxDur = appRules.max_duration || 10;
                    var durationTpl = "{{ __('hrms.leave.app.duration_range', ['min' => '__min__', 'max' => '__max__']) }}";
                    $('#policy_duration').html('<span class="policy-icon-wrapper"><i class="feather-clock"></i></span><span>' + durationTpl.replace('__min__', '<strong>' + minDur + '</strong>').replace('__max__', '<strong>' + maxDur + '</strong>') + '</span>');

                    // Apply in Advance & Disable invalid dates
                    if (appRules.apply_in_advance) {
                        var advanceDays = parseInt(appRules.advance_days || 3);
                        var minDate = new Date();
                        minDate.setDate(minDate.getDate() + advanceDays);
                        var yyyy = minDate.getFullYear();
                        var mm = String(minDate.getMonth() + 1).padStart(2, '0');
                        var dd = String(minDate.getDate()).padStart(2, '0');
                        var minDateStr = yyyy + '-' + mm + '-' + dd;

                        $('#start_date').attr('min', minDateStr);
                        $('#end_date').attr('min', minDateStr);

                        // Clear previously selected dates if they violate the rule now
                        var curStart = $('#start_date').val();
                        if (curStart && curStart < minDateStr) {
                            $('#start_date').val('');
                        }
                        var curEnd = $('#end_date').val();
                        if (curEnd && curEnd < minDateStr) {
                            $('#end_date').val('');
                        }

                        var advanceTpl = "{{ __('hrms.leave.app.notice_required_days', ['days' => '__days__']) }}";
                        $('#policy_advance').html('<span class="policy-icon-wrapper"><i class="feather-alert-circle"></i></span><span>' + advanceTpl.replace('__days__', '<strong>' + advanceDays + '</strong>') + '</span>').removeClass('d-none');
                    } else {
                        $('#start_date').removeAttr('min');
                        $('#end_date').removeAttr('min');
                        $('#policy_advance').addClass('d-none');
                    }

                    // Probation Rule
                    if (probRules.probation_rule && probRules.probation_rule !== 'allow') {
                        var cannotProbTxt = "{{ __('hrms.leave.app.cannot_apply_probation') }}";
                        var allowedProbTpl = "{{ __('hrms.leave.app.allowed_after_months', ['months' => '__months__']) }}";
                        var probTxt = probRules.probation_rule === 'disallow' 
                            ? cannotProbTxt 
                            : allowedProbTpl.replace('__months__', '<strong>' + (probRules.probation_months || 3) + '</strong>');
                        $('#policy_probation').html('<span class="policy-icon-wrapper"><i class="feather-user-check"></i></span><span>' + '{{ __('hrms.leave.probation') }}' + ': ' + probTxt + '</span>').removeClass('d-none');
                    } else {
                        $('#policy_probation').addClass('d-none');
                    }

                    // Notice Period Rule
                    if (noticeRules.notice_rule && noticeRules.notice_rule !== 'allow') {
                        var cannotNoticeTxt = "{{ __('hrms.leave.app.cannot_apply_notice') }}";
                        var specialNoticeTxt = "{{ __('hrms.leave.app.special_permission_notice') }}";
                        var noticeTxt = noticeRules.notice_rule === 'disallow' 
                            ? cannotNoticeTxt 
                            : specialNoticeTxt;
                        $('#policy_notice').html('<span class="policy-icon-wrapper"><i class="feather-user-x"></i></span><span>' + '{{ __('hrms.leave.notice') }}' + ': ' + noticeTxt + '</span>').removeClass('d-none');
                    } else {
                        $('#policy_notice').addClass('d-none');
                    }

                    // Attachment Required
                    if (appRules.require_attachment) {
                        var attachmentTpl = "{{ __('hrms.leave.app.attachment_required_for', ['days' => '__days__']) }}";
                        $('#policy_attachment').html('<span class="policy-icon-wrapper"><i class="feather-paperclip"></i></span><span>' + attachmentTpl.replace('__days__', '<strong>' + (appRules.attachment_days || 3) + '</strong>') + '</span>').removeClass('d-none');
                    } else {
                        $('#policy_attachment').addClass('d-none');
                    }

                    // Approval levels
                    var approvalLevel = approvalRules.workflow_level || '1_level';
                    var autoTxt = "{{ __('hrms.leave.app.auto_approved') }}";
                    var oneLvlTxt = "{{ __('hrms.leave.app.one_level_req') }}";
                    var twoLvlTxt = "{{ __('hrms.leave.app.two_level_req') }}";
                    var approvalText = approvalLevel === 'auto' ? autoTxt : (approvalLevel === '1_level' ? oneLvlTxt : twoLvlTxt);
                    var workflowTpl = "{{ __('hrms.leave.app.workflow_label', ['type' => '__type__']) }}";
                    $('#policy_approval').html('<span class="policy-icon-wrapper"><i class="feather-check-square"></i></span><span>' + workflowTpl.replace('__type__', approvalText) + '</span>');

                    // Re-calculate expected duration and attachment requirement immediately
                    calculateExpectedDuration();

                } catch (e) {
                    console.error("Error parsing leave rules", e);
                }
            });

            // Block form submission if dynamic attachment requirement is violated
            $('#applyLeaveModal form').on('submit', function(e) {
                var selectedOption = $('#leave_type_select').find('option:selected');
                var rulesStr = selectedOption.attr('data-rules');
                if (!rulesStr) return;

                try {
                    var rules = JSON.parse(rulesStr);
                    var appRules = rules.application || {};
                    if (appRules.require_attachment) {
                        var attachmentDays = parseInt(appRules.attachment_days || 3);
                        var duration = calculateExpectedDuration();
                        var hasFile = $('#attachment').val();

                        if (duration >= attachmentDays && !hasFile) {
                            e.preventDefault();
                            var alertMsg = "{{ __('hrms.leave.app.attachment_required_alert', ['days' => '__days__']) }}";
                            alert(alertMsg.replace('__days__', attachmentDays));
                            return false;
                        }
                    }
                } catch (err) {
                    console.error("Error running form submit validation", err);
                }
            });

            // Handle date range select types
            $('#start_date_type, #end_date_type').on('change', function() {
                calculateExpectedDuration();
            });

            $('#start_date, #end_date').on('change', function() {
                // If single day is selected, force types and match dates
                var startDateVal = $('#start_date').val();
                var endDateVal = $('#end_date').val();

                if (startDateVal && !endDateVal) {
                    $('#end_date').val(startDateVal);
                }

                calculateExpectedDuration();
            });

            function calculateExpectedDuration() {
                var startDateStr = $('#start_date').val();
                var endDateStr = $('#end_date').val();
                var startType = $('#start_date_type').val() || 'full_day';
                var endType = $('#end_date_type').val() || 'full_day';

                if (!startDateStr || !endDateStr) return 0;

                var start = new Date(startDateStr);
                var end = new Date(endDateStr);

                if (end < start) {
                    $('#calculated_duration_display').text("{{ __('hrms.leave.app.date_validation_error') }}");
                    return 0;
                }

                var duration = 0;
                var current = new Date(start);

                if (start.getTime() === end.getTime()) {
                    // Single day
                    if (start.getDay() !== 0) { // Exclude Sunday (0)
                        duration = (startType === 'full_day') ? 1.0 : 0.5;
                    }
                } else {
                    while (current <= end) {
                        if (current.getDay() !== 0) { // Exclude Sunday
                            var isStart = current.getTime() === start.getTime();
                            var isEnd = current.getTime() === end.getTime();

                            if (isStart) {
                                duration += (startType === 'full_day') ? 1.0 : 0.5;
                            } else if (isEnd) {
                                duration += (endType === 'full_day') ? 1.0 : 0.5;
                            } else {
                                duration += 1.0;
                            }
                        }
                        current.setDate(current.getDate() + 1);
                    }
                }

                var estTpl = "{{ __('hrms.leave.app.estimated_duration', ['duration' => '__duration__']) }}";
                $('#calculated_duration_display').html(estTpl.replace('__duration__', '<strong>' + duration + '</strong>'));

                // Real-time dynamic attachment warning constraint
                var selectedOption = $('#leave_type_select').find('option:selected');
                var rulesStr = selectedOption.attr('data-rules');
                if (rulesStr) {
                    try {
                        var rules = JSON.parse(rulesStr);
                        var appRules = rules.application || {};
                        if (appRules.require_attachment) {
                            var attachmentDays = parseInt(appRules.attachment_days || 3);
                            if (duration >= attachmentDays) {
                                $('#attachment_required_warning').removeClass('d-none');
                                $('#attachment').prop('required', true);
                            } else {
                                $('#attachment_required_warning').addClass('d-none');
                                $('#attachment').prop('required', false);
                            }
                        } else {
                            $('#attachment_required_warning').addClass('d-none');
                            $('#attachment').prop('required', false);
                        }
                    } catch (e) {
                        console.error("Error evaluating real-time attachment rules", e);
                    }
                }

                return duration;
            }

            var currentPage = 1;
            var itemsPerPage = 10;

            function updatePagination() {
                var searchVal = $('#leaves_search').val().toLowerCase().trim();
                var empId = $('#filter_employee_id').val();
                var status = $('#filter_status').val();

                var $visibleRows = $('.leave-row').filter(function() {
                    var $row = $(this);
                    var rowEmp = $row.attr('data-employee') || '';
                    var rowEmpId = $row.attr('data-employee-id') || '';
                    var rowStatus = $row.attr('data-status') || '';

                    var matchesSearch = !searchVal || rowEmp.indexOf(searchVal) !== -1;
                    var matchesEmp = !empId || rowEmpId === empId;
                    var matchesStatus = !status || rowStatus === status;

                    return matchesSearch && matchesEmp && matchesStatus;
                });

                var totalItems = $visibleRows.length;
                var totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

                if (currentPage > totalPages) {
                    currentPage = totalPages;
                }
                if (currentPage < 1) {
                    currentPage = 1;
                }

                var startIndex = (currentPage - 1) * itemsPerPage;
                var endIndex = Math.min(startIndex + itemsPerPage, totalItems);

                $('.leave-row').hide();
                $visibleRows.slice(startIndex, endIndex).show();

                if (totalPages > 1) {
                    $('.erp-pagination-container').show();
                } else {
                    $('.erp-pagination-container').hide();
                }

                if (totalItems === 0) {
                    $('#no_matching_leaves_row').removeClass('d-none');
                } else {
                    $('#no_matching_leaves_row').addClass('d-none');
                }

                $('#leaves_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
                $('#leaves_showing_end').text(endIndex);
                $('#leaves_total_count').text(totalItems);

                var paginationHtml = '';

                // Previous button
                paginationHtml += `
                    <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
                            <i class="feather-chevron-left"></i>
                        </a>
                    </li>
                `;

                // Page numbers
                for (var i = 1; i <= totalPages; i++) {
                    paginationHtml += `
                        <li class="page-item ${currentPage === i ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>
                    `;
                }

                // Next button
                paginationHtml += `
                    <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
                            <i class="feather-chevron-right"></i>
                        </a>
                    </li>
                `;

                $('#leaves_pagination_ul').html(paginationHtml);
            }

            // Client-side search trigger
            $('#leaves_search').on('input', function() {
                currentPage = 1;
                updatePagination();
            });

            // Client-side filter Apply trigger
            $('#btnApplyFilters').on('click', function(e) {
                e.preventDefault();
                currentPage = 1;
                updatePagination();
                $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
            });

            // Client-side filter Reset trigger
            $('#btnResetFilters').on('click', function(e) {
                e.preventDefault();
                $('#leaves_search').val('');
                $('#filter_employee_id').val('').trigger('change');
                $('#filter_status').val('');
                currentPage = 1;
                updatePagination();
                $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
            });

            // Pagination click handler
            $(document).on('click', '#leaves_pagination_ul .page-link', function(e) {
                e.preventDefault();
                var page = $(this).data('page');
                if (page && !$(this).parent().hasClass('disabled')) {
                    currentPage = parseInt(page);
                    updatePagination();
                }
            });

            // Initial pagination display
            updatePagination();

            // Reject Leave Trigger Modal
            $('.reject-leave-btn').on('click', function() {
                var id = $(this).attr('data-id');
                var actionUrl = "{{ route('hrms.leaves.reject', ':id') }}".replace(':id', id);
                $('#rejectLeaveForm').attr('action', actionUrl);
                $('#rejectLeaveModal').modal('show');
            });

            // Status Dropdown Change Handler
            $(document).on('change', '.status-dropdown', function() {
                var $select = $(this);
                var id = $select.attr('data-id');
                var newStatus = $select.val();
                
                if (newStatus === 'rejected') {
                    // Show rejection reason modal
                    var actionUrl = "{{ route('hrms.leaves.reject', ':id') }}".replace(':id', id);
                    $('#rejectLeaveForm').attr('action', actionUrl);
                    $('#rejectLeaveModal').modal('show');
                    
                    // Revert selection if modal is dismissed without submitting
                    $('#rejectLeaveModal').one('hidden.bs.modal', function () {
                        var originalStatus = $select.closest('tr').attr('data-status');
                        $select.val(originalStatus);
                    });
                } else {
                    // Submit status update form directly
                    $select.closest('form').submit();
                }
            });

            // Client-side Encashments Pagination & Filtering
            var encashCurrentPage = 1;
            var encashRowsPerPage = 10;

            function updateEncashmentsPagination() {
                var searchTerm = ($('#encashments_search').val() || '').toLowerCase().trim();
                var empFilter = $('#filter_encashment_employee_id').val();
                var statusFilter = $('#filter_encashment_status').val();

                var $allRows = $('#encashmentsTableBody .encashment-row');
                var $visibleRows = $allRows.filter(function() {
                    var text = ($(this).attr('data-employee') || '').toLowerCase();
                    var empId = $(this).attr('data-employee-id') || '';
                    var status = $(this).attr('data-status') || '';

                    var matchesSearch = !searchTerm || text.indexOf(searchTerm) !== -1;
                    var matchesEmp = !empFilter || empId === empFilter;
                    var matchesStatus = !statusFilter || status === statusFilter;

                    return matchesSearch && matchesEmp && matchesStatus;
                });

                $allRows.addClass('d-none');

                var totalVisible = $visibleRows.length;
                var totalPages = Math.ceil(totalVisible / encashRowsPerPage) || 1;

                if (encashCurrentPage > totalPages) encashCurrentPage = totalPages;
                if (encashCurrentPage < 1) encashCurrentPage = 1;

                var startIndex = (encashCurrentPage - 1) * encashRowsPerPage;
                var endIndex = startIndex + encashRowsPerPage;

                $visibleRows.slice(startIndex, endIndex).removeClass('d-none');

                if ($allRows.length > 0 && totalVisible === 0) {
                    $('#no_matching_encashments_row').removeClass('d-none');
                } else {
                    $('#no_matching_encashments_row').addClass('d-none');
                }

                $('#encashments_showing_start').text(totalVisible > 0 ? startIndex + 1 : 0);
                $('#encashments_showing_end').text(Math.min(endIndex, totalVisible));
                $('#encashments_total_count').text(totalVisible);

                if (totalPages > 1) {
                    $('#pane-leave-encashments .erp-pagination-container').show();
                } else {
                    $('#pane-leave-encashments .erp-pagination-container').hide();
                }

                var paginationHtml = '';
                paginationHtml += `
                    <li class="page-item ${encashCurrentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${encashCurrentPage - 1}" aria-label="Previous">
                            <i class="feather-chevron-left"></i>
                        </a>
                    </li>
                `;

                for (var i = 1; i <= totalPages; i++) {
                    if (i === 1 || i === totalPages || (i >= encashCurrentPage - 1 && i <= encashCurrentPage + 1)) {
                        paginationHtml += `
                            <li class="page-item ${i === encashCurrentPage ? 'active' : ''}">
                                <a class="page-link" href="#" data-page="${i}">${i}</a>
                            </li>
                        `;
                    } else if (i === encashCurrentPage - 2 || i === encashCurrentPage + 2) {
                        paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    }
                }

                paginationHtml += `
                    <li class="page-item ${encashCurrentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${encashCurrentPage + 1}" aria-label="Next">
                            <i class="feather-chevron-right"></i>
                        </a>
                    </li>
                `;

                $('#encashments_pagination_ul').html(paginationHtml);
            }

            $('#encashments_search').on('input', function() {
                encashCurrentPage = 1;
                updateEncashmentsPagination();
            });

            $('#btnApplyEncashmentFilters').on('click', function(e) {
                e.preventDefault();
                encashCurrentPage = 1;
                updateEncashmentsPagination();
                $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
            });

            $('#btnResetEncashmentFilters').on('click', function(e) {
                e.preventDefault();
                $('#encashments_search').val('');
                $('#filter_encashment_employee_id').val('').trigger('change');
                $('#filter_encashment_status').val('');
                encashCurrentPage = 1;
                updateEncashmentsPagination();
                $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
            });

            $(document).on('click', '#encashments_pagination_ul .page-link', function(e) {
                e.preventDefault();
                var page = $(this).data('page');
                if (page && !$(this).parent().hasClass('disabled')) {
                    encashCurrentPage = parseInt(page);
                    updateEncashmentsPagination();
                }
            });

            updateEncashmentsPagination();

            // Dynamic Encashment Leave Type Population
            function updateEncashmentLeaveTypes(empId) {
                var $select = $('#encashment_leave_type_id');
                $select.empty().append('<option value="">' + "{{ __('hrms.leave.encashment_app.select_leave_type') }}" + '</option>');

                if (empId && employeeDataMap[empId]) {
                    var types = employeeDataMap[empId];
                    types.forEach(function(t) {
                        var encashRules = (t.rules && t.rules.encashment) ? t.rules.encashment : {};
                        var isEnabled = encashRules.enabled === true || encashRules.enabled === '1' || encashRules.enabled === 'true';
                        
                        if (isEnabled) {
                            var text = t.name + ' (' + '{{ __('hrms.leave.app.remaining') }}' + ': ' + t.remaining + ' / ' + t.quota + ' ' + '{{ __('hrms.leave.days') }}' + ')';
                            var option = $('<option>', {
                                value: t.id,
                                text: text
                            });
                            $select.append(option);
                        }
                    });
                }
                $select.trigger('change');
            }

            $('#encashment_employee_id').on('change', function() {
                updateEncashmentLeaveTypes($(this).val());
            });

            $('#applyEncashmentModal').on('show.bs.modal', function() {
                var empId = $('#encashment_employee_id').length ? $('#encashment_employee_id').val() : "{{ $employee ? $employee->id : '' }}";
                updateEncashmentLeaveTypes(empId);
            });

            var initialEncashEmpId = $('#encashment_employee_id').length ? $('#encashment_employee_id').val() : "{{ $employee ? $employee->id : '' }}";
            if (initialEncashEmpId) {
                updateEncashmentLeaveTypes(initialEncashEmpId);
            }
        });

        // Client-side sort selection handler for leave applications
        function changeLeavesSort(criteria, element) {
            $('.sort-check').addClass('d-none');
            if (element) {
                $(element).find('.sort-check').removeClass('d-none');
                var menu = element.closest('.dropdown-menu');
                if (menu) {
                    menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                        el.classList.remove('active');
                    });
                }
                element.classList.add('active');
            }

            var $tbody = $('#leavesTableBody');
            var $rows = $tbody.find('.leave-row').get();

            $rows.sort(function(a, b) {
                var keyA, keyB;

                if (criteria === 'date_desc' || criteria === 'date_asc') {
                    keyA = parseInt($(a).attr('data-created-at') || 0);
                    keyB = parseInt($(b).attr('data-created-at') || 0);
                    return criteria === 'date_desc' ? keyB - keyA : keyA - keyB;
                } else if (criteria === 'duration_desc' || criteria === 'duration_asc') {
                    keyA = parseFloat($(a).attr('data-duration') || 0);
                    keyB = parseFloat($(b).attr('data-duration') || 0);
                    return criteria === 'duration_desc' ? keyB - keyA : keyA - keyB;
                }
                return 0;
            });

            $.each($rows, function(index, row) {
                $tbody.append(row);
            });
            updatePagination();
        }

        // Client-side sort selection handler for encashment requests
        function changeEncashmentsSort(criteria, element) {
            $('.encash-sort-check').addClass('d-none');
            if (element) {
                $(element).find('.encash-sort-check').removeClass('d-none');
                var menu = element.closest('.dropdown-menu');
                if (menu) {
                    menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                        el.classList.remove('active');
                    });
                }
                element.classList.add('active');
            }

            var $tbody = $('#encashmentsTableBody');
            var $rows = $tbody.find('.encashment-row').get();

            $rows.sort(function(a, b) {
                var keyA, keyB;

                if (criteria === 'date_desc' || criteria === 'date_asc') {
                    keyA = parseInt($(a).attr('data-created-at') || 0);
                    keyB = parseInt($(b).attr('data-created-at') || 0);
                    return criteria === 'date_desc' ? keyB - keyA : keyA - keyB;
                } else if (criteria === 'days_desc' || criteria === 'days_asc') {
                    keyA = parseFloat($(a).attr('data-days') || 0);
                    keyB = parseFloat($(b).attr('data-days') || 0);
                    return criteria === 'days_desc' ? keyB - keyA : keyA - keyB;
                }
                return 0;
            });

            $.each($rows, function(index, row) {
                $tbody.append(row);
            });
            updateEncashmentsPagination();
        }
    </script>

    <script>
        // ── Leave Detail Offcanvas (index page) ─────────────────────────────────
        $(document).on('click', '.open-leave-detail-idx', function () {
            var $row = $(this).closest('tr.leave-row');
            var d    = $row.data();

            // Employee (admin view)
            if ($('#lid-emp-name').length) {
                var empName = d.employeeName || '';
                $('#lid-emp-name').text(empName);
                $('#lid-emp-code').text(d.employeeCode || '');
                $('#lid-emp-avatar').text(empName.charAt(0).toUpperCase() || 'E');
            }

            // Leave type banner
            $('#lid-color-dot').css('background', d.leaveColor || '#3b82f6');
            $('#lid-leave-type').text(d.leaveType || '—');
            $('#lid-balance-inline').text('Remaining: ' + (d.remaining !== undefined ? d.remaining : '0') + ' / ' + (d.allocated !== undefined ? d.allocated : '0') + ' Days');

            // Status badge
            $('#lid-status-badge')
                .attr('class', 'badge rounded-pill px-2 py-1 fs-11 flex-shrink-0 ' + (d.statusCls || ''))
                .html('<i class="' + (d.statusIcon || '') + ' me-1"></i>' + (d.statusLabel || ''));

            // Period
            $('#lid-date-range').text(d.dateRange || '—');
            $('#lid-session-info').text(d.sessionInfo || '');

            // Duration
            var dur = parseFloat(d.duration) || 0;
            $('#lid-duration').text(dur + (dur === 1 ? ' Day' : ' Days'));

            // Reason / Workflow / Applied
            $('#lid-reason').text(d.reason || '—');
            $('#lid-workflow').text(d.workflow || '—');
            $('#lid-applied').text(d.applied || '—');

            // Rejection
            if (d.rejection) {
                $('#lid-rejection-wrap').removeClass('d-none');
                $('#lid-rejection').text(d.rejection);
            } else {
                $('#lid-rejection-wrap').addClass('d-none');
            }

            // Attachment
            if (d.attachment) {
                $('#lid-attach-wrap').removeClass('d-none');
                $('#lid-attach-link').attr('href', d.attachment);
            } else {
                $('#lid-attach-wrap').addClass('d-none');
            }

            // Notified contacts
            if (d.notifiedNames) {
                $('#lid-notified-wrap').removeClass('d-none');
                $('#lid-notified-names').text(d.notifiedNames);
            } else {
                $('#lid-notified-wrap').addClass('d-none');
            }

            // Status form action
            $('#lid-status-form').attr('action', d.updateUrl || '');
            $('#lid-status-select').val(d.status).trigger('change');

            if (d.status === 'rejected') {
                $('#lid-rejection-input-wrap').removeClass('d-none');
                $('#lid-rejection-reason-input').val(d.rejection || '');
            } else {
                $('#lid-rejection-input-wrap').addClass('d-none');
                $('#lid-rejection-reason-input').val('');
            }
        });

        $(document).on('change', '#lid-status-select', function() {
            if ($(this).val() === 'rejected') {
                $('#lid-rejection-input-wrap').removeClass('d-none');
            } else {
                $('#lid-rejection-input-wrap').addClass('d-none');
            }
        });
    </script>
@endpush

@section('content')
    <div class="row pt-4 px-4">
        <!-- Leave Balances Section (Visible to logged-in employee when plan exists) -->
        @if($employee && $employee->leavePlan && !$employee->leavePlan->types->isEmpty())
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 pt-4 d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><i class="feather-info text-primary me-1.5"></i> {{ __('hrms.employees.lbl_assigned_leave_plan') ?? 'Assigned Leave Plan' }}</h5>
                            <p class="text-muted fs-12 mb-0">{{ __('hrms.leave.app.leave_plan') }} <span class="badge bg-secondary fw-semibold">{{ $employee->leavePlan ? $employee->leavePlan->name : __('hrms.leave.app.no_active_plan') }}</span></p>
                        </div>
                        @if($employee->leavePlan)
                            @if(!$employee->leavePlan->status)
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10 px-2 py-0.5 rounded">Inactive</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle fs-10 px-2 py-0.5 rounded">Active</span>
                            @endif
                        @endif
                    </div>
                    <div class="card-body">
                        @if($employee->leavePlan)
                            @if(!$employee->leavePlan->status)
                                <div class="alert alert-warning py-1.5 px-3 mb-3 fs-12 d-flex align-items-center gap-2 border-0" style="background-color: #fef3c7; color: #92400e;">
                                    <i class="feather-alert-triangle"></i>
                                    <span>This leave plan is currently inactive.</span>
                                </div>
                            @endif
                            <div class="mb-3">
                                <p class="text-muted fs-13 mb-0">{{ $employee->leavePlan->description ?: 'No description provided.' }}</p>
                                <small class="text-muted fs-11 text-uppercase fw-semibold d-block mt-2">{{ __('hrms.employees.lbl_effective_from') ?? 'Effective From' }}: <strong class="text-dark">{{ $employee->leavePlan->effective_from ? $employee->leavePlan->effective_from->format('d M, Y') : 'N/A' }}</strong></small>
                            </div>

                            @if(!$employee->leavePlan->types->isEmpty())
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 fs-12 border">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="py-2 ps-3">TYPE NAME</th>
                                                <th class="text-center py-2">BALANCE</th>
                                                <th class="text-end py-2 pe-3">RULES</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($employee->leavePlan->types as $ltype)
                                                @php
                                                    $balance = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $employee->id)
                                                        ->where('leave_type_id', $ltype->id)
                                                        ->first();
                                                    $balanceVal = $balance ? floatval($balance->remaining) : 0.0;
                                                    $allocatedVal = $balance ? floatval($balance->allocated) : floatval($ltype->quota);
                                                @endphp
                                                <tr>
                                                    <td class="py-2 ps-3">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="d-inline-block rounded-circle flex-shrink-0 me-1" style="width: 8px; height: 8px; background-color: {{ $ltype->color ?: '#3b82f6' }};"></span>
                                                            <span class="fw-bold text-dark fs-12">{{ $ltype->name }}</span>
                                                            <span class="text-muted fs-10 text-uppercase ms-1" style="font-size: 10px;">{{ $ltype->code }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="text-center py-2" style="white-space: nowrap;">
                                                        <span class="fw-bold text-dark fs-12">{{ $balanceVal }}</span>
                                                        <span class="text-muted fs-10">/ {{ $allocatedVal }}</span>
                                                    </td>
                                                    <td class="text-end py-2 pe-3">
                                                        <button
                                                            type="button"
                                                            class="leave-rules-icon-btn btn btn-light border d-inline-flex align-items-center justify-content-center p-0 rounded"
                                                            style="width: 22px; height: 22px;"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#leaveRulesModal{{ $ltype->id }}"
                                                            title="View leave rules"
                                                            aria-label="View rules for {{ $ltype->name }}"
                                                        >
                                                            <i class="feather-sliders text-primary" style="font-size: 10px;"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted fs-13">
                                <i class="feather-alert-circle d-block fs-24 mb-2 text-warning"></i>
                                {{ __('hrms.employees.lbl_no_leave_plan') ?? 'No Leave Plan assigned.' }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Main Module Navigation Tabs -->
        <div class="col-12 mb-2">
            <ul class="nav gap-2 border-bottom pb-2" id="leavesModuleTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-applications" data-bs-toggle="tab" data-bs-target="#pane-leave-applications" type="button" role="tab">
                        <i class="feather-file-text me-1"></i> {{ __('hrms.leave.app.title') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-encashments" data-bs-toggle="tab" data-bs-target="#pane-leave-encashments" type="button" role="tab">
                        <i class="feather-dollar-sign me-1"></i> {{ __('hrms.leave.encashment_app.title') }}
                        @if(isset($leaveEncashments) && $leaveEncashments->where('status', 'pending')->count() > 0)
                            <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5 fs-11">
                                {{ $leaveEncashments->where('status', 'pending')->count() }}
                            </span>
                        @endif
                    </button>
                </li>
            </ul>
        </div>

        <div class="col-12">
            <div class="tab-content" id="leavesModuleTabContent">
                <!-- Pane 1: Leave Applications -->
                <div class="tab-pane fade show active" id="pane-leave-applications" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-bold text-dark mb-0">{{ __('hrms.leave.app.title') }}</h5>
                                <p class="text-muted fs-12 mb-0">{{ __('hrms.leave.app.review_applications_desc') }}</p>
                            </div>
                            
                            <div class="d-flex align-items-center gap-2">
                                <form method="GET" action="javascript:void(0);" class="d-flex align-items-center gap-2 m-0" id="leavesFilterForm">
                                    <!-- Registry Style Search Input -->
                                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                        <input type="text" id="leaves_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.leave.app.search_employee') }}" style="box-shadow: none; height: 32px;">
                                    </div>

                                    <!-- Sort Dropdown with Checkmark Icons -->
                                    <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                                        <a class="dropdown-item py-2 d-flex align-items-center active" href="#" onclick="changeLeavesSort('date_desc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.app.sort_newest') }}</span>
                                            <i class="feather-check text-dark ms-auto sort-check"></i>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="changeLeavesSort('date_asc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.app.sort_oldest') }}</span>
                                            <i class="feather-check text-dark ms-auto sort-check d-none"></i>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="changeLeavesSort('duration_desc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.app.sort_duration_high_low') }}</span>
                                            <i class="feather-check text-dark ms-auto sort-check d-none"></i>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="changeLeavesSort('duration_asc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.app.sort_duration_low_high') }}</span>
                                            <i class="feather-check text-dark ms-auto sort-check d-none"></i>
                                        </a>
                                    </x-ui.sort-dropdown>

                                    <!-- Filter Dropdown -->
                                    <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                        
                                        @if($isAdmin)
                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_employee') ?? 'Employee' }}</label>
                                                <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_employee_id">
                                                    <option value="">{{ __('hrms.common.all_employees') ?? 'All Employees' }}</option>
                                                    @foreach($allEmployees as $emp)
                                                        <option value="{{ $emp->id }}">
                                                            {{ $emp->full_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        @endif

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('ui.status') ?? 'Status' }}</label>
                                            <x-ui.odoo-form-ui type="select" name="status" id="filter_status">
                                                <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                                <option value="pending">{{ __('hrms.leave.app.status_pending') }}</option>
                                                <option value="approved">{{ __('hrms.leave.app.status_approved') }}</option>
                                                <option value="rejected">{{ __('hrms.leave.app.status_rejected') }}</option>
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="dropdown-divider my-3"></div>

                                        <div class="d-flex gap-2">
                                            <button type="button" id="btnApplyFilters" class="btn btn-primary btn-sm flex-grow-1">{{ __('hrms.common.apply') }}</button>
                                            <button type="button" id="btnResetFilters" class="btn btn-light btn-sm border flex-grow-1">{{ __('hrms.common.reset') }}</button>
                                        </div>
                                    </x-ui.filter>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="leavesTable">
                                    <thead class="table-light">
                                        <tr>
                                            @if($isAdmin)
                                                <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="min-width:150px;">{{ __('hrms.employees.tbl_employee') ?? 'Employee' }}</th>
                                            @endif
                                            <th class="fs-12 text-uppercase text-muted fw-semibold {{ !$isAdmin ? 'ps-3' : '' }}" style="min-width:130px;">{{ __('hrms.leave.leave_types') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width:160px;">{{ __('hrms.leave.app.duration_timeline') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width:75px;">{{ __('hrms.leave.days') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width:95px;">{{ __('ui.status') ?? 'Status' }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width:65px;">File</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="width:70px;">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody id="leavesTableBody">
                                        @forelse($leaveRequests as $req)
                                            @php
                                                $sameYear = $req->start_date->format('Y') === $req->end_date->format('Y');
                                                $startStr = $req->start_date->format($sameYear ? 'd M' : 'd M Y');
                                                $endStr   = $req->end_date->format('d M Y');
                                                $dateRange = $req->start_date->isSameDay($req->end_date)
                                                    ? $req->start_date->format('d M Y')
                                                    : $startStr . ' – ' . $endStr;

                                                $rowBalance   = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $req->employee_id)->where('leave_type_id', $req->leave_type_id)->first();
                                                $rowRemaining = $rowBalance ? floatval($rowBalance->remaining) : 0.0;
                                                $rowAllocated = $rowBalance ? floatval($rowBalance->allocated) : floatval($req->leaveType->quota);

                                                $notifiedNames = '';
                                                if (!empty($req->notified_contacts)) {
                                                    $contacts = \App\Domains\HRMS\Models\Employee::whereIn('id', $req->notified_contacts)->pluck('full_name')->toArray();
                                                    $notifiedNames = implode(', ', $contacts);
                                                }

                                                $statusBadge = match($req->status) {
                                                    'approved'     => ['cls' => 'bg-soft-success text-success',    'icon' => 'feather-check-circle', 'lbl' => __('hrms.leave.app.status_approved')],
                                                    'pending'      => ['cls' => 'bg-soft-warning text-warning',    'icon' => 'feather-clock',         'lbl' => __('hrms.leave.app.status_pending')],
                                                    'rejected'     => ['cls' => 'bg-soft-danger text-danger',      'icon' => 'feather-x-circle',      'lbl' => __('hrms.leave.app.status_rejected')],
                                                    'unauthorized' => ['cls' => 'bg-soft-secondary text-secondary','icon' => 'feather-slash',         'lbl' => __('hrms.leave.app.status_unauthorized')],
                                                    'unpaid'       => ['cls' => 'bg-soft-info text-info',          'icon' => 'feather-alert-circle',  'lbl' => __('hrms.leave.app.status_unpaid')],
                                                    default        => ['cls' => 'bg-light text-secondary',         'icon' => 'feather-circle',        'lbl' => ucfirst($req->status)],
                                                };

                                                $sessionInfo = '';
                                                if ($req->start_date_type !== 'full_day' || $req->end_date_type !== 'full_day') {
                                                    $sessionInfo = ucwords(str_replace('_', ' ', $req->start_date_type));
                                                    if (!$req->start_date->isSameDay($req->end_date) && $req->end_date_type !== 'full_day') {
                                                        $sessionInfo .= ' → ' . ucwords(str_replace('_', ' ', $req->end_date_type));
                                                    }
                                                }
                                            @endphp
                                            <tr class="leave-row"
                                                data-employee="{{ strtolower($req->employee->full_name) }} {{ strtolower($req->employee->employee_id) }}"
                                                data-employee-id="{{ $req->employee_id }}"
                                                data-status="{{ $req->status }}"
                                                data-duration="{{ $req->duration }}"
                                                data-created-at="{{ $req->created_at->timestamp }}"
                                                data-leave-type="{{ $req->leaveType->name }}"
                                                data-leave-code="{{ $req->leaveType->code }}"
                                                data-leave-color="{{ $req->leaveType->color ?: '#3b82f6' }}"
                                                data-date-range="{{ $dateRange }}"
                                                data-session-info="{{ $sessionInfo }}"
                                                data-remaining="{{ $rowRemaining }}"
                                                data-allocated="{{ $rowAllocated }}"
                                                data-reason="{{ addslashes($req->reason) }}"
                                                data-status-cls="{{ $statusBadge['cls'] }}"
                                                data-status-icon="{{ $statusBadge['icon'] }}"
                                                data-status-label="{{ $statusBadge['lbl'] }}"
                                                data-workflow="{{ $req->status === 'approved' ? __('hrms.leave.app.status_approved') : ($req->status === 'rejected' ? __('hrms.leave.app.status_rejected') : (in_array($req->status,['unauthorized','unpaid']) ? __('hrms.leave.app.processed') : __('hrms.leave.app.level_n', ['level' => $req->current_level]))) }}"
                                                data-applied="{{ $req->created_at->format('d M Y, h:i A') }}"
                                                data-rejection="{{ addslashes($req->rejection_reason ?? '') }}"
                                                data-attachment="{{ $req->attachment_path ? asset('storage/'.$req->attachment_path) : '' }}"
                                                data-update-url="{{ route('hrms.leaves.update-status', $req->id) }}"
                                                data-notified-names="{{ $notifiedNames }}"
                                                data-employee-name="{{ $req->employee->full_name }}"
                                                data-employee-code="{{ $req->employee->employee_id }}"
                                            >
                                                @if($isAdmin)
                                                    <td class="ps-3">
                                                        <div class="fw-semibold text-dark fs-13">{{ $req->employee->full_name }}</div>
                                                        <div class="text-muted fs-11">{{ $req->employee->employee_id }}</div>
                                                    </td>
                                                @endif
                                                <td class="{{ !$isAdmin ? 'ps-3' : '' }}">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="flex-shrink-0 rounded-circle" style="width:9px;height:9px;background:{{ $req->leaveType->color ?: '#3b82f6' }};display:inline-block;"></span>
                                                        <div>
                                                            <div class="fw-semibold text-dark fs-13">{{ $req->leaveType->name }}</div>
                                                            <div class="text-muted fs-11">Rem: {{ $rowRemaining }} / {{ $rowAllocated }} {{ __('hrms.leave.days') }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fs-13 text-dark">{{ $dateRange }}</div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold fs-13 text-dark">{{ floatval($req->duration) }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge {{ $statusBadge['cls'] }} rounded-pill px-2 py-1 fs-11">
                                                        <i class="{{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['lbl'] }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    @if($req->attachment_path)
                                                        <i class="feather-paperclip text-primary fs-13" title="{{ __('hrms.leave.app.view_attachment') }}" data-bs-toggle="tooltip"></i>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-end pe-3">
                                                    <button type="button"
                                                        class="btn btn-sm btn-light border open-leave-detail-idx px-2 py-1"
                                                        title="View Details"
                                                        data-bs-toggle="offcanvas"
                                                        data-bs-target="#leaveDetailDrawerIdx">
                                                        <i class="feather-eye fs-12 text-primary"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="10" class="text-center py-5 text-muted">
                                                    <i class="feather-folder fs-3 d-block mb-3 text-secondary"></i>
                                                    {{ __('hrms.leave.app.no_applications_submitted') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                        <tr id="no_matching_leaves_row" class="d-none">
                                            <td colspan="10" class="text-center py-5 text-muted">
                                                <i class="feather-folder fs-3 d-block mb-3 text-secondary"></i>
                                                {{ __('hrms.leave.app.no_matching_applications') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="erp-pagination-container">
                                <ul class="erp-pagination mb-2" id="leaves_pagination_ul">
                                    <!-- Dynamically generated pagination links -->
                                </ul>
                                <div class="erp-pagination-info">
                                    {!! __('hrms.leave.app.showing_entries', [
                                        'start' => '<span id="leaves_showing_start">0</span>',
                                        'end'   => '<span id="leaves_showing_end">0</span>',
                                        'total' => '<strong id="leaves_total_count">0</strong>'
                                    ]) !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Leave Detail Offcanvas Drawer (for Leave Applications index) --}}
                <x-ui.drawer id="leaveDetailDrawerIdx" title="{{ __('hrms.leave.app.title') }} Detail" style="width:440px;max-width:100%;">
                    {{-- Merged Employee & Leave Type Card --}}
                    <div class="mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        @if($isAdmin)
                            <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom" style="border-color: #e2e8f0 !important;">
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold" style="width:34px;height:34px;font-size:13px;" id="lid-emp-avatar">E</div>
                                <div>
                                    <div class="fw-bold fs-13 text-dark" id="lid-emp-name">—</div>
                                    <div class="fs-11 text-muted" id="lid-emp-code"></div>
                                </div>
                            </div>
                        @endif

                        <div class="d-flex align-items-start gap-3">
                            <span id="lid-color-dot" class="rounded-circle flex-shrink-0 mt-1" style="width:12px;height:12px;display:inline-block;"></span>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-14 text-dark" id="lid-leave-type">—</div>
                                <div class="fs-12 text-muted mt-1" id="lid-balance-inline"></div>
                                <div class="fs-11 text-muted mt-1">Applied On: <span class="fw-semibold text-dark" id="lid-applied">—</span></div>
                            </div>
                            <span class="badge rounded-pill px-2 py-1 fs-11 flex-shrink-0" id="lid-status-badge"></span>
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- Period & Duration --}}
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.duration_timeline') }}</div>
                            <div class="fw-semibold text-dark fs-13" id="lid-date-range">—</div>
                            <div class="text-muted fs-12 mt-1" id="lid-session-info"></div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Duration</div>
                            <div class="fw-bold fs-22 text-primary" id="lid-duration">—</div>
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- Reason --}}
                    <div class="mb-3">
                        <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.reason') }}</div>
                        <div class="fs-13 text-dark" id="lid-reason" style="white-space:pre-line;">—</div>
                    </div>

                    {{-- Workflow Level & Attachment --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.workflow_level') }}</div>
                            <div class="fs-13 text-dark" id="lid-workflow">—</div>
                        </div>
                        <div class="d-none text-end" id="lid-attach-wrap">
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.view_attachment') }}</div>
                            <a id="lid-attach-link" href="#" target="_blank" class="btn btn-sm btn-soft-primary d-inline-flex align-items-center gap-1">
                                <i class="feather-paperclip fs-12"></i> {{ __('hrms.leave.app.view_attachment') }}
                            </a>
                        </div>
                    </div>

                    {{-- Rejection Reason --}}
                    <div class="mb-3 d-none" id="lid-rejection-wrap">
                        <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.rejection_reason') }}</div>
                        <div class="alert alert-soft-danger py-2 px-3 fs-13 mb-0" id="lid-rejection"></div>
                    </div>

                    {{-- Notified Members --}}
                    <div class="mb-3 d-none" id="lid-notified-wrap">
                        <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.notify_members') ?? 'Notified Members' }}</div>
                        <div class="fs-13 text-dark" id="lid-notified-names">—</div>
                    </div>

                    {{-- Status Change --}}
                    @if($isAdmin)
                        <hr class="my-3">
                        <div>
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-2" style="letter-spacing:.5px;">Update Status</div>
                            <form method="POST" id="lid-status-form" action="">
                                @csrf
                                <div class="d-flex gap-2 align-items-center">
                                    <div class="flex-grow-1" style="margin-bottom: -1rem;">
                                        <x-ui.select name="status" id="lid-status-select" class="odoo-select2">
                                            <option value="pending">Pending</option>
                                            <option value="approved">Approved</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="unauthorized">Unauthorized</option>
                                            <option value="unpaid">Unpaid</option>
                                        </x-ui.select>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-3 d-flex align-items-center gap-1" style="height: 38px; border-radius: 6px;">
                                        <i class="feather-check fs-12"></i> Apply
                                    </button>
                                </div>
                                <div class="mt-2 d-none" id="lid-rejection-input-wrap">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-2 mt-2" style="letter-spacing:.5px;">Rejection Reason</div>
                                    <x-ui.textarea name="rejection_reason" id="lid-rejection-reason-input" rows="2" placeholder="Enter reason for rejection..." />
                                </div>
                            </form>
                        </div>
                    @endif

                    <x-slot:footer>
                        <button type="button" class="btn btn-light border fw-semibold text-uppercase" data-bs-dismiss="offcanvas">CLOSE PANEL</button>
                    </x-slot:footer>
                </x-ui.drawer>

                <!-- Pane 2: Leave Encashments -->
                <div class="tab-pane fade" id="pane-leave-encashments" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-bold text-dark mb-0">{{ __('hrms.leave.encashment_app.title') }}</h5>
                                <p class="text-muted fs-12 mb-0">{{ __('hrms.leave.app.review_applications_desc') }}</p>
                            </div>
                            
                            <div class="d-flex align-items-center gap-2">
                                <form method="GET" action="javascript:void(0);" class="d-flex align-items-center gap-2 m-0" id="encashmentFilterForm">
                                    <!-- Registry Style Search Input -->
                                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                        <input type="text" id="encashments_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.leave.app.search_employee') }}" style="box-shadow: none; height: 32px;">
                                    </div>

                                    <!-- Sort Dropdown -->
                                    <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                                        <a class="dropdown-item py-2 d-flex align-items-center active" href="#" onclick="changeEncashmentsSort('date_desc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.encashment_app.sort_newest') }}</span>
                                            <i class="feather-check text-dark ms-auto encash-sort-check"></i>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="changeEncashmentsSort('date_asc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.encashment_app.sort_oldest') }}</span>
                                            <i class="feather-check text-dark ms-auto encash-sort-check d-none"></i>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="changeEncashmentsSort('days_desc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.encashment_app.sort_days_high_low') }}</span>
                                            <i class="feather-check text-dark ms-auto encash-sort-check d-none"></i>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="changeEncashmentsSort('days_asc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.encashment_app.sort_days_low_high') }}</span>
                                            <i class="feather-check text-dark ms-auto encash-sort-check d-none"></i>
                                        </a>
                                    </x-ui.sort-dropdown>

                                    <!-- Filter Dropdown -->
                                    <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                        
                                        @if($isAdmin)
                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.leave.encashment_app.employee') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_encashment_employee_id">
                                                    <option value="">{{ __('hrms.common.all_employees') }}</option>
                                                    @foreach($allEmployees as $emp)
                                                        <option value="{{ $emp->id }}">
                                                            {{ $emp->full_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        @endif

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('ui.status') ?? 'Status' }}</label>
                                            <x-ui.odoo-form-ui type="select" name="status" id="filter_encashment_status">
                                                <option value="">{{ __('hrms.leave.encashment_app.all_statuses') }}</option>
                                                <option value="pending">{{ __('hrms.leave.app.status_pending') }}</option>
                                                <option value="approved">{{ __('hrms.leave.app.status_approved') }}</option>
                                                <option value="rejected">{{ __('hrms.leave.app.status_rejected') }}</option>
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="dropdown-divider my-3"></div>

                                        <div class="d-flex gap-2">
                                            <button type="button" id="btnApplyEncashmentFilters" class="btn btn-primary btn-sm flex-grow-1">{{ __('hrms.common.apply') }}</button>
                                            <button type="button" id="btnResetEncashmentFilters" class="btn btn-light btn-sm border flex-grow-1">{{ __('hrms.common.reset') }}</button>
                                        </div>
                                    </x-ui.filter>
                                </form>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            @if($isAdmin)
                                                <th>{{ __('hrms.leave.encashment_app.employee') }}</th>
                                            @endif
                                            <th>{{ __('hrms.leave.encashment_app.leave_type') }}</th>
                                            <th>{{ __('hrms.leave.encashment_app.requested_days') }}</th>
                                            <th>{{ __('hrms.leave.encashment_app.reason') }}</th>
                                            <th>{{ __('hrms.leave.encashment_app.submitted_date') }}</th>
                                            <th>{{ __('ui.status') ?? 'Status' }}</th>
                                            @if($isAdmin)
                                                <th class="text-end">{{ __('hrms.leave.app.actions') }}</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody id="encashmentsTableBody">
                                        @forelse($leaveEncashments as $enc)
                                            <tr class="encashment-row"
                                                data-employee="{{ strtolower($enc->employee->full_name) }} {{ strtolower($enc->employee->employee_id) }}"
                                                data-employee-id="{{ $enc->employee_id }}"
                                                data-status="{{ $enc->status }}"
                                                data-days="{{ floatval($enc->requested_days) }}"
                                                data-created-at="{{ $enc->created_at->timestamp }}">
                                                @if($isAdmin)
                                                    <td>
                                                        <h6 class="fw-bold mb-0 fs-13 text-dark">{{ $enc->employee->full_name }}</h6>
                                                        <small class="text-muted fs-11">{{ $enc->employee->employee_id }}</small>
                                                    </td>
                                                @endif
                                                <td>
                                                    <span class="badge bg-light text-primary fw-semibold fs-12 mb-1">{{ $enc->leaveType->name }}</span>
                                                </td>
                                                <td>
                                                    <span class="fs-13 fw-bold text-dark">{{ floatval($enc->requested_days) }} {{ __('hrms.leave.days') }}</span>
                                                </td>
                                                <td>
                                                    <span class="fs-12 text-muted">{{ $enc->reason ?: __('hrms.leave.app.no_reason_provided') }}</span>
                                                </td>
                                                <td>
                                                    <span class="fs-12 text-dark">{{ $enc->created_at->format('d M Y') }}</span>
                                                </td>
                                                <td>
                                                    @if($enc->status === 'pending')
                                                        <span class="badge badge-pending px-2.5 py-1.5 rounded-pill fs-11 fw-semibold"><i class="feather-clock me-1"></i> {{ __('hrms.leave.app.status_pending') }}</span>
                                                    @elseif($enc->status === 'approved')
                                                        <span class="badge badge-approved px-2.5 py-1.5 rounded-pill fs-11 fw-semibold"><i class="feather-check-circle me-1"></i> {{ __('hrms.leave.app.status_approved') }}</span>
                                                    @else
                                                        <span class="badge badge-rejected px-2.5 py-1.5 rounded-pill fs-11 fw-semibold"><i class="feather-x-circle me-1"></i> {{ __('hrms.leave.app.status_rejected') }}</span>
                                                    @endif
                                                </td>
                                                @if($isAdmin)
                                                    <td class="text-end">
                                                        @if($enc->status === 'pending')
                                                            <form method="POST" action="{{ route('hrms.leaves.encashment.approve', $enc->id) }}" class="d-inline-block me-1">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-success px-2 py-1 fs-12 fw-semibold" title="{{ __('hrms.leave.encashment_app.approve') }}"><i class="feather-check me-1"></i> {{ __('hrms.leave.encashment_app.approve') }}</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('hrms.leaves.encashment.reject', $enc->id) }}" class="d-inline-block">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1 fs-12 fw-semibold" title="{{ __('hrms.leave.encashment_app.reject') }}"><i class="feather-x me-1"></i> {{ __('hrms.leave.encashment_app.reject') }}</button>
                                                            </form>
                                                        @else
                                                            <form method="POST" action="{{ route('hrms.leaves.encashment.destroy', $enc->id) }}" class="d-inline-block" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.leave.encashment_app.confirm_delete') }}', { title: 'Delete Encashment Application', variant: 'danger', confirmButtonText: 'Delete' });">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-light border text-danger px-2 py-1 fs-12" title="{{ __('hrms.common.delete') }}"><i class="feather-trash-2"></i></button>
                                                            </form>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ $isAdmin ? 7 : 5 }}" class="text-center py-5 text-muted fs-13">
                                                    <i class="feather-dollar-sign fs-3 d-block mb-2 text-secondary"></i>
                                                    {{ __('hrms.leave.encashment_app.no_encashments') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                        <tr id="no_matching_encashments_row" class="d-none">
                                            <td colspan="{{ $isAdmin ? 7 : 5 }}" class="text-center py-5 text-muted fs-13">
                                                <i class="feather-folder fs-3 d-block mb-2 text-secondary"></i>
                                                {{ __('hrms.leave.encashment_app.no_matching_encashments') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="erp-pagination-container">
                                <ul class="erp-pagination mb-2" id="encashments_pagination_ul">
                                    <!-- Encashments Pagination -->
                                </ul>
                                <div class="erp-pagination-info">
                                    {!! __('hrms.leave.app.showing_entries', [
                                        'start' => '<span id="encashments_showing_start">0</span>',
                                        'end' => '<span id="encashments_showing_end">0</span>',
                                        'total' => '<strong id="encashments_total_count">0</strong>'
                                    ]) !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Apply Leave Modal -->
    <div class="modal fade" id="applyLeaveModal" aria-labelledby="applyLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="applyLeaveModalLabel">{{ __('hrms.leave.app.apply_for_leave') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.leaves.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body p-4">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($isAdmin)
                            <div class="row mb-3">
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.employees.tbl_employee') ?? 'Employee'" name="employee_id" id="employee_select" :required="true" class="odoo-select2-custom">
                                        @foreach ($allEmployees as $emp)
                                            <option value="{{ $emp->id }}" {{ ($employee && $employee->id == $emp->id) ? 'selected' : '' }}>
                                                {{ $emp->full_name }} ({{ $emp->employee_id }})
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
                        @else
                            @if ($employee)
                                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                            @endif
                        @endif

                        <div class="row">
                            <div class="col-12 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.leave_types')" name="leave_type_id" id="leave_type_select" :required="true" class="odoo-select2-custom">
                                    <option value="">{{ __('hrms.leave.app.select_leave_type') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.leave.app.start_date')" name="start_date" id="start_date" :required="true" class="odoo-underline-input" />
                            </div>
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.start_session')" name="start_date_type" id="start_date_type" :required="true" class="odoo-select2-custom">
                                    <option value="full_day">{{ __('hrms.leave.app.full_day') }}</option>
                                    <option value="first_half">{{ __('hrms.leave.app.first_half') }}</option>
                                    <option value="second_half">{{ __('hrms.leave.app.second_half') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.leave.app.end_date')" name="end_date" id="end_date" :required="true" class="odoo-underline-input" />
                            </div>
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.end_session')" name="end_date_type" id="end_date_type" :required="true" class="odoo-select2-custom">
                                    <option value="full_day">{{ __('hrms.leave.app.full_day') }}</option>
                                    <option value="first_half">{{ __('hrms.leave.app.first_half') }}</option>
                                    <option value="second_half">{{ __('hrms.leave.app.second_half') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <div id="calculated_duration_display" class="alert alert-info py-2 fs-12 mb-0">
                                {{ __('hrms.leave.app.estimated_duration_simple', ['duration' => 0]) }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.leave.app.reason_for_leave')" name="reason" :required="true" class="odoo-underline-input" :placeholder="__('hrms.leave.app.reason_placeholder')"></x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="file" :label="__('hrms.leave.app.upload_attachment')" name="attachment" id="attachment" :required="false" helperText="{{ __('hrms.leave.app.formats_allowed') }}" />
                            <div id="attachment_required_warning" class="text-danger fs-12 mt-1 d-none fw-semibold">
                                <i class="feather-alert-triangle"></i> {{ __('hrms.leave.app.attachment_required_warning') }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.notify_members')" name="notified_contacts[]" id="notified_contacts" :required="false" :multiple="true" class="odoo-select2-custom" :placeholder="__('hrms.leave.app.notify_placeholder')">
                                @foreach ($allEmployees as $emp)
                                    @if (!$employee || $emp->id !== $employee->id)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endif
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                    <div class="modal-header border-top py-3 d-flex justify-content-end gap-2" style="border-bottom: none;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary text-dark">{{ __('hrms.leave.app.submit_request') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Apply Encashment Modal -->
    <div class="modal fade" id="applyEncashmentModal" tabindex="-1" aria-labelledby="applyEncashmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="applyEncashmentModalLabel">{{ __('hrms.leave.encashment_app.apply_for_encashment') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('hrms.leaves.encashment.store') }}">
                    @csrf
                    <div class="modal-body p-4">
                        @if($isAdmin)
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.encashment_app.select_employee')" name="employee_id" id="encashment_employee_id" :required="true" class="odoo-select2-custom">
                                    <option value="">{{ __('hrms.leave.encashment_app.select_employee') }}...</option>
                                    @foreach($allEmployees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        @else
                            <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                        @endif

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.encashment_app.select_leave_type')" name="leave_type_id" id="encashment_leave_type_id" :required="true" class="odoo-select2-custom">
                                <option value="">{{ __('hrms.leave.encashment_app.select_leave_type') }}...</option>
                                @if($employee && isset($employeeDataMap[$employee->id]))
                                    @foreach($employeeDataMap[$employee->id] as $t)
                                        <option value="{{ $t['id'] }}">{{ $t['name'] }} ({{ __('hrms.leave.app.remaining') }}: {{ $t['remaining'] }} {{ __('hrms.leave.days') }})</option>
                                    @endforeach
                                @endif
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.leave.encashment_app.requested_days')" name="requested_days" id="encashment_requested_days" :required="true" class="odoo-underline-input" step="0.5" min="0.5" placeholder="e.g. 2.5" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.leave.encashment_app.reason')" name="reason" id="encashment_reason" :required="false" class="odoo-underline-input" :placeholder="__('hrms.leave.encashment_app.reason_placeholder')" />
                        </div>
                    </div>
                    <div class="modal-header border-top py-3 d-flex justify-content-end gap-2" style="border-bottom: none;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary text-dark"><i class="feather-check me-1"></i> {{ __('hrms.leave.encashment_app.submit_encashment') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Leave Modal -->
    <div class="modal fade" id="rejectLeaveModal" aria-labelledby="rejectLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="rejectLeaveModalLabel">{{ __('hrms.leave.app.reject_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rejectLeaveForm" method="POST">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label fw-bold text-secondary fs-13">{{ __('hrms.leave.app.rejection_reason') }} <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="4" :placeholder="__('hrms.leave.app.rejection_reason_placeholder')" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</button>
                        <button type="submit" class="btn btn-danger text-white">{{ __('hrms.leave.app.confirm_rejection') }}</button>
                    </div>
                </form>
            </div>
    @if($employee && $employee->leavePlan && $employee->leavePlan->types->isNotEmpty())
        @foreach($employee->leavePlan->types as $ltype)
            @php($rulePoints = $formatLeaveRulePoints($ltype->rules ?? []))
            <div class="modal fade" id="leaveRulesModal{{ $ltype->id }}" tabindex="-1" aria-labelledby="leaveRulesModalLabel{{ $ltype->id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title fw-bold" id="leaveRulesModalLabel{{ $ltype->id }}">
                                    <i class="feather-sliders text-primary me-2"></i>{{ $ltype->name }} {{ __('hrms.employees.mdl_leave_rules_title') ?? 'Leave Rules' }}
                                </h5>
                                <div class="text-muted fs-12 mt-1">
                                    {{ $ltype->code }} · {{ ucfirst($ltype->type) }} · {{ floatval($ltype->quota) }} {{ __('hrms.employees.mdl_yearly_quota') ?? 'Yearly Quota' }}
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body bg-light">
                            @if(empty($rulePoints))
                                <div class="text-center py-5 text-muted">
                                    <i class="feather-check-circle d-block fs-32 mb-3 text-success"></i>
                                    <div class="fw-bold text-dark mb-1">{{ __('hrms.employees.mdl_std_rules') ?? 'Standard Rules' }}</div>
                                    <div>{{ __('hrms.employees.mdl_no_custom_rules') ?? 'No custom rules defined.' }}</div>
                                </div>
                            @else
                                <div class="row g-3">
                                    @foreach($rulePoints as $section)
                                        <div class="col-md-6 col-12">
                                            <div class="leave-rule-detail-section">
                                                <div class="leave-rule-detail-title">
                                                    <i class="{{ $section['icon'] }} text-primary"></i>
                                                    <span>{{ $section['title'] }}</span>
                                                </div>
                                                <ul class="leave-rule-points">
                                                    @foreach($section['points'] as $point)
                                                        <li class="leave-rule-point">{{ $point }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer bg-white">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.employees.mdl_btn_close') ?? 'Close' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
