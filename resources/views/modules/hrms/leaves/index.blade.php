@extends('layouts.duralux')

@section('title', __('hrms.leave.app.title') . ' | SaaS ERP')
@section('page-title', __('hrms.leave.app.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.leave.app.title'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('hrms.leaves.export') }}"
           id="btnExportHeader"
           class="btn btn-light border fw-bold text-uppercase d-flex align-items-center gap-1"
           style="height: 38px; color: #475569; border-color: #cbd5e1 !important;">
            <i class="feather-download"></i> {{ __('hrms.common.export_excel') }}
        </a>
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

        /* ── Table Responsive Dropdown Visibility Fix ── */
        .table-responsive {
            position: relative;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }

        /* ── Custom Sort Dropdown Chevrons ── */
        .erp-sort-dropdown .dropdown-item.active:not(:has(i))::after {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='18 15 12 9 6 15'%3E%3C/polyline%3E%3C/svg%3E") !important;
            margin-left: 12px;
        }
        .erp-sort-dropdown .dropdown-item.active[data-sort*="desc"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[data-sort*="high"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[data-sort*="oldest"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[onclick*="desc"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[onclick*="high"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[onclick*="oldest"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[href*="desc"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[href*="high"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[href*="oldest"]:not(:has(i))::after {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script>
        var employeeDataMap = @json($employeeDataMap);

        $(document).ready(function() {
            // Append modals to document.body to completely prevent blur/backdrop z-index issues
            ['applyLeaveModal', 'rejectLeaveModal', 'applyEncashmentModal', 'leaveCancellationModal'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el && el.parentNode !== document.body) {
                    document.body.appendChild(el);
                }
            });
            $('[id^="leaveRulesModal"]').appendTo('body');

            var leavesExportUrl = "{{ route('hrms.leaves.export') }}";
            var encashmentExportUrl = "{{ route('hrms.leaves.encashment.export') }}";

            $('button[data-bs-toggle="tab"]').on('shown.bs.tab click', function (e) {
                var target = $(this).attr('data-bs-target') || $(this).attr('id');
                var isEncashment = target === '#pane-leave-encashments' || target === 'tab-encashments';

                if (isEncashment) {
                    $('#btnApplyLeaveHeader').addClass('d-none');
                    $('#btnApplyEncashmentHeader').removeClass('d-none');
                    $('#btnExportHeader').attr('href', encashmentExportUrl);
                } else {
                    $('#btnApplyEncashmentHeader').addClass('d-none');
                    $('#btnApplyLeaveHeader').removeClass('d-none');
                    $('#btnExportHeader').attr('href', leavesExportUrl);
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
                    $('#btnExportHeader').attr('href', encashmentExportUrl);
                }
            } else {
                $('#btnApplyEncashmentHeader').addClass('d-none');
                $('#btnApplyLeaveHeader').removeClass('d-none');
                $('#btnExportHeader').attr('href', leavesExportUrl);
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
                var defaultEmpId = "{{ (isset($employee) && $employee) ? $employee->id : '' }}";
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

                var startParts = startDateStr.split('-');
                var endParts = endDateStr.split('-');
                var start = new Date(parseInt(startParts[0], 10), parseInt(startParts[1], 10) - 1, parseInt(startParts[2], 10));
                var end = new Date(parseInt(endParts[0], 10), parseInt(endParts[1], 10) - 1, parseInt(endParts[2], 10));

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

            function loadLeaveApplications(page = 1) {
                var search = $('#leaves_search').val() || '';
                var empId = $('#filter_employee_id').val() || '';
                var status = $('#filter_status').val() || '';
                var sort = $('#leaves_sort_value').val() || 'date_desc';
                var tab = 'applications';

                var url = '{{ route("hrms.leaves.index") }}?tab=' + tab +
                          '&leaves_search=' + encodeURIComponent(search) +
                          '&leaves_employee_id=' + encodeURIComponent(empId) +
                          '&leaves_status=' + encodeURIComponent(status) +
                          '&leaves_sort=' + encodeURIComponent(sort) +
                          '&leaves_page=' + page;

                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        var oldBody = $('#leavesTableBody');
                        var newBody = $(doc).find('#leavesTableBody');
                        if (oldBody.length && newBody.length) {
                            oldBody.html(newBody.html());
                        }
                        
                        var oldPagination = $('#leaves_pagination_container');
                        var newPagination = $(doc).find('#leaves_pagination_container');
                        if (oldPagination.length && newPagination.length) {
                            oldPagination.replaceWith(newPagination);
                        }

                        $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                        $('.erp-filter-dropdown.show').removeClass('show');

                        history.pushState(null, '', url);
                    }
                });
            }

            var leavesSearchTimeout;
            $(document).on('input', '#leaves_search', function() {
                clearTimeout(leavesSearchTimeout);
                leavesSearchTimeout = setTimeout(function() {
                    loadLeaveApplications(1);
                }, 300);
            });

            $(document).on('click', '#btnApplyFilters', function(e) {
                e.preventDefault();
                loadLeaveApplications(1);
            });

            $(document).on('click', '#btnResetFilters', function(e) {
                e.preventDefault();
                $('#leaves_search').val('');
                $('#filter_employee_id').val('').trigger('change');
                $('#filter_status').val('').trigger('change');
                $('#leaves_sort_value').val('date_desc');

                var sortDropdown = $('#pane-leave-applications .erp-sort-dropdown');
                if (sortDropdown.length) {
                    sortDropdown.find('.dropdown-item').removeClass('active');
                    sortDropdown.find('.dropdown-item:first').addClass('active');
                }

                loadLeaveApplications(1);
            });

            $(document).on('click', '#leaves_pagination_container a', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                if (!url) return;
                var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
                var page = urlParams.get('leaves_page') || 1;
                loadLeaveApplications(page);
            });

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

            function loadLeaveEncashments(page = 1) {
                var search = $('#encashments_search').val() || '';
                var empId = $('#filter_encashment_employee_id').val() || '';
                var status = $('#filter_encashment_status').val() || '';
                var sort = $('#encashments_sort_value').val() || 'date_desc';
                var tab = 'encashments';

                var url = '{{ route("hrms.leaves.index") }}?tab=' + tab +
                          '&encashments_search=' + encodeURIComponent(search) +
                          '&encashments_employee_id=' + encodeURIComponent(empId) +
                          '&encashments_status=' + encodeURIComponent(status) +
                          '&encashments_sort=' + encodeURIComponent(sort) +
                          '&encash_page=' + page;

                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        var oldBody = $('#encashmentsTableBody');
                        var newBody = $(doc).find('#encashmentsTableBody');
                        if (oldBody.length && newBody.length) {
                            oldBody.html(newBody.html());
                        }
                        
                        var oldPagination = $('#encashments_pagination_container');
                        var newPagination = $(doc).find('#encashments_pagination_container');
                        if (oldPagination.length && newPagination.length) {
                            oldPagination.replaceWith(newPagination);
                        }

                        $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                        $('.erp-filter-dropdown.show').removeClass('show');

                        history.pushState(null, '', url);
                    }
                });
            }

            var encashmentsSearchTimeout;
            $(document).on('input', '#encashments_search', function() {
                clearTimeout(encashmentsSearchTimeout);
                encashmentsSearchTimeout = setTimeout(function() {
                    loadLeaveEncashments(1);
                }, 300);
            });

            $(document).on('click', '#btnApplyEncashmentFilters', function(e) {
                e.preventDefault();
                loadLeaveEncashments(1);
            });

            $(document).on('click', '#btnResetEncashmentFilters', function(e) {
                e.preventDefault();
                $('#encashments_search').val('');
                $('#filter_encashment_employee_id').val('').trigger('change');
                $('#filter_encashment_status').val('').trigger('change');
                $('#encashments_sort_value').val('date_desc');

                var sortDropdown = $('#pane-leave-encashments .erp-sort-dropdown');
                if (sortDropdown.length) {
                    sortDropdown.find('.dropdown-item').removeClass('active');
                    sortDropdown.find('.dropdown-item').find('.encash-sort-check').addClass('d-none');
                    sortDropdown.find('.dropdown-item:first').addClass('active').find('.encash-sort-check').removeClass('d-none');
                }

                loadLeaveEncashments(1);
            });

            $(document).on('click', '#encashments_pagination_container a', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                if (!url) return;
                var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
                var page = urlParams.get('encash_page') || 1;
                loadLeaveEncashments(page);
            });

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

            window.loadLeaveApplications = loadLeaveApplications;
            window.loadLeaveEncashments = loadLeaveEncashments;
        });

        // Sort selection handler for leave applications
        function changeLeavesSort(criteria, element) {
            var input = document.getElementById('leaves_sort_value');
            if (input) {
                input.value = criteria;
            }
            if (element) {
                var menu = element.closest('.dropdown-menu');
                if (menu) {
                    menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                        el.classList.remove('active');
                    });
                }
                element.classList.add('active');
            }
            if (typeof window.loadLeaveApplications === 'function') {
                window.loadLeaveApplications(1);
            }
        }

        // Sort selection handler for encashment requests
        function changeEncashmentsSort(criteria, element) {
            var input = document.getElementById('encashments_sort_value');
            if (input) {
                input.value = criteria;
            }
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
            if (typeof window.loadLeaveEncashments === 'function') {
                window.loadLeaveEncashments(1);
            }
        }

        window.changeLeavesSort = changeLeavesSort;
        window.changeEncashmentsSort = changeEncashmentsSort;
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
            $('#lid-balance-inline').text("{{ __('hrms.leave.app.remaining') }}: " + (d.remaining !== undefined ? d.remaining : '0') + ' / ' + (d.allocated !== undefined ? d.allocated : '0') + ' ' + "{{ __('hrms.leave.days') }}");

            // Status badge
            $('#lid-status-badge')
                .attr('class', 'badge rounded-pill px-2 py-1 fs-11 flex-shrink-0 ' + (d.statusCls || ''))
                .html('<i class="' + (d.statusIcon || '') + ' me-1"></i>' + (d.statusLabel || ''));

            // Period
            $('#lid-date-range').text(d.dateRange || '—');
            $('#lid-session-info').text(d.sessionInfo || '');

            // Duration
            var dur = parseFloat(d.duration) || 0;
            $('#lid-duration').text(dur + ' ' + (dur == 1 ? "{{ __('hrms.leave.day') }}" : "{{ __('hrms.leave.days') }}"));

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

            // Cancellation
            if (d.cancellation) {
                $('#lid-cancellation-wrap').removeClass('d-none');
                $('#lid-cancellation').text(d.cancellation);
            } else {
                $('#lid-cancellation-wrap').addClass('d-none');
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

            // Status form action & data urls
            var form = $('#lid-status-form');
            form.data('update-url', d.updateUrl || '');
            form.data('approve-cancel-url', d.approveCancelUrl || '');
            form.data('deny-cancel-url', d.denyCancelUrl || '');

            // Hide status change panel completely if status is cancelled
            if (d.status === 'cancelled') {
                $('#lid-status-change-wrap').addClass('d-none');
                $('#lid-status-hr').addClass('d-none');
            } else {
                $('#lid-status-change-wrap').removeClass('d-none');
                $('#lid-status-hr').removeClass('d-none');
            }

            // Dynamically populate options based on status
            var $select = $('#lid-status-select');
            $select.empty();

            if (d.status === 'cancellation_requested') {
                $select.append('<option value="approve_cancellation">Approve Cancellation</option>');
                $select.append('<option value="deny_cancellation">Deny Cancellation</option>');
                $select.val('approve_cancellation').trigger('change');
            } else {
                $select.append('<option value="approved">Approve</option>');
                $select.append('<option value="rejected">Reject</option>');
                $select.append('<option value="pending">Pending</option>');
                $select.append('<option value="unauthorized">Unauthorized</option>');
                $select.append('<option value="unpaid">Unpaid</option>');
                $select.val(d.status).trigger('change');
            }

            if (d.status === 'rejected') {
                $('#lid-rejection-input-wrap').removeClass('d-none');
                $('#lid-rejection-reason-input').val(d.rejection || '');
            } else {
                $('#lid-rejection-input-wrap').addClass('d-none');
                $('#lid-rejection-reason-input').val('');
            }
        });

        $(document).on('change', '#lid-status-select', function() {
            var selectedVal = $(this).val();
            var form = $('#lid-status-form');
            var baseUpdateUrl = form.data('update-url');
            var approveCancelUrl = form.data('approve-cancel-url');
            var denyCancelUrl = form.data('deny-cancel-url');

            if (selectedVal === 'approve_cancellation') {
                form.attr('action', approveCancelUrl);
            } else if (selectedVal === 'deny_cancellation') {
                form.attr('action', denyCancelUrl);
            } else {
                form.attr('action', baseUpdateUrl);
            }

            if (selectedVal === 'rejected') {
                $('#lid-rejection-input-wrap').removeClass('d-none');
            } else {
                $('#lid-rejection-input-wrap').addClass('d-none');
            }
        });

        // ── Leave Cancellation Modal ───────────────────────────────────────────
        function openLeaveCancellationModal(leaveId, actionUrl) {
            var form = document.getElementById('leaveCancellationForm');
            if (form) {
                form.action = actionUrl;
            }
            document.getElementById('leave_cancellation_reason').value = '';
            var modal = new bootstrap.Modal(document.getElementById('leaveCancellationModal'));
            modal.show();
        }

        function toggleLeaveCancelReasonText(btn) {
            var textEl = btn.previousElementSibling;
            if (textEl.style.display === 'block') {
                textEl.style.display = '-webkit-box';
                textEl.style.webkitLineClamp = '2';
                btn.textContent = 'See more';
            } else {
                textEl.style.display = 'block';
                textEl.style.webkitLineClamp = 'none';
                btn.textContent = 'See less';
            }
        }
    </script>
@endpush

@section('content')
    <div class="row pt-4 px-4">


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
                                    <input type="hidden" id="leaves_sort_value" value="{{ $leavesSort ?? 'date_desc' }}">
                                    <!-- Registry Style Search Input -->
                                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                        <input type="text" id="leaves_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.leave.app.search_employee') }}" style="box-shadow: none; height: 32px;" value="{{ $leavesSearch ?? '' }}">
                                    </div>

                                    <!-- Sort Dropdown with Checkmark Icons -->
                                    <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($leavesSort ?? 'date_desc') === 'date_desc' ? 'active' : '' }}" href="#" onclick="changeLeavesSort('date_desc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.app.sort_newest') }}</span>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($leavesSort ?? '') === 'date_asc' ? 'active' : '' }}" href="#" onclick="changeLeavesSort('date_asc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.app.sort_oldest') }}</span>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($leavesSort ?? '') === 'duration_desc' ? 'active' : '' }}" href="#" onclick="changeLeavesSort('duration_desc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.app.sort_duration_high_low') }}</span>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($leavesSort ?? '') === 'duration_asc' ? 'active' : '' }}" href="#" onclick="changeLeavesSort('duration_asc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.app.sort_duration_low_high') }}</span>
                                        </a>
                                    </x-ui.sort-dropdown>

                                    <!-- Filter Dropdown -->
                                    <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                        
                                        @if($isAdmin)
                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_employee') ?? 'Employee' }}</label>
                                                <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_employee_id">
                                                    <option value="" {{ ($leavesEmployeeId ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.common.all_employees') ?? 'All Employees' }}</option>
                                                    @foreach(($allEmployees ?? $employees ?? []) as $emp)
                                                        <option value="{{ $emp->id }}" {{ ($leavesEmployeeId ?? '') == $emp->id ? 'selected' : '' }}>
                                                            {{ $emp->full_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        @endif

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('ui.status') ?? 'Status' }}</label>
                                            <x-ui.odoo-form-ui type="select" name="status" id="filter_status">
                                                <option value="" {{ ($leavesStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.common.all_statuses') }}</option>
                                                <option value="pending" {{ ($leavesStatus ?? '') === 'pending' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_pending') }}</option>
                                                <option value="approved" {{ ($leavesStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_approved') }}</option>
                                                <option value="rejected" {{ ($leavesStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_rejected') }}</option>
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
                                                <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="width: 18%;">{{ __('hrms.employees.tbl_employee') ?? 'Employee' }}</th>
                                            @endif
                                            <th class="fs-12 text-uppercase text-muted fw-semibold {{ !$isAdmin ? 'ps-3' : '' }}" style="width: 18%;">{{ __('hrms.leave.leave_types') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 16%;">{{ __('hrms.leave.app.duration_timeline') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 8%;">{{ __('hrms.leave.days') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 14%;">{{ __('ui.status') ?? 'Status' }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 8%;">File</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="width: 18%;">{{ __('hrms.common.action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="leavesTableBody">
                                        @forelse(($leaveRequests ?? $requests ?? []) as $req)
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
                                                    'approved'                => ['cls' => 'bg-soft-success text-success',    'icon' => 'feather-check-circle',  'lbl' => __('hrms.leave.app.status_approved')],
                                                    'pending'                 => ['cls' => 'bg-soft-warning text-warning',    'icon' => 'feather-clock',          'lbl' => __('hrms.leave.app.status_pending')],
                                                    'rejected'                => ['cls' => 'bg-soft-danger text-danger',      'icon' => 'feather-x-circle',       'lbl' => __('hrms.leave.app.status_rejected')],
                                                    'cancellation_requested'  => ['cls' => 'bg-soft-info text-info',          'icon' => 'feather-rotate-ccw',     'lbl' => 'Cancellation Requested'],
                                                    'cancelled'               => ['cls' => 'bg-soft-secondary text-secondary','icon' => 'feather-slash',          'lbl' => 'Cancelled'],
                                                    'unauthorized'            => ['cls' => 'bg-soft-secondary text-secondary','icon' => 'feather-slash',          'lbl' => __('hrms.leave.app.status_unauthorized')],
                                                    'unpaid'                  => ['cls' => 'bg-soft-info text-info',          'icon' => 'feather-alert-circle',   'lbl' => __('hrms.leave.app.status_unpaid')],
                                                    default                   => ['cls' => 'bg-light text-secondary',         'icon' => 'feather-circle',         'lbl' => ucfirst($req->status)],
                                                };

                                                $sessionInfo = '';
                                                if ($req->start_date_type !== 'full_day' || $req->end_date_type !== 'full_day') {
                                                    $sessionInfo = ucwords(str_replace('_', ' ', $req->start_date_type));
                                                    if (!$req->start_date->isSameDay($req->end_date) && $req->end_date_type !== 'full_day') {
                                                        $sessionInfo .= ' → ' . ucwords(str_replace('_', ' ', $req->end_date_type));
                                                    }
                                                }
                                                $isLongCancelReason = (mb_strlen($req->cancellation_reason ?? '') > 70) || (substr_count($req->cancellation_reason ?? '', "\n") > 1);
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
                                                data-approve-cancel-url="{{ route('hrms.leaves.approve-cancellation', $req->id) }}"
                                                data-deny-cancel-url="{{ route('hrms.leaves.deny-cancellation', $req->id) }}"
                                                data-cancellation="{{ addslashes($req->cancellation_reason ?? '') }}"
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
                                                    @if(in_array($req->status, ['cancellation_requested', 'cancelled']) && !empty($req->cancellation_reason))
                                                        <div class="text-warning fs-11 mt-2" style="max-width: 250px;">
                                                            <span class="fw-semibold"><i class="feather-rotate-ccw me-1"></i>Cancellation:</span>
                                                            <div class="leave-cancel-reason-text mb-0 text-muted fs-11" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; white-space: normal; line-height: 1.4; color: inherit;">
                                                                {{ $req->cancellation_reason }}
                                                            </div>
                                                            @if($isLongCancelReason)
                                                                <a href="#" class="leave-toggle-cancel-reason-btn fs-10 text-primary fw-semibold d-inline-block mt-0.5" onclick="toggleLeaveCancelReasonText(this); return false;">See more</a>
                                                            @endif
                                                        </div>
                                                    @endif
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
                                                <td class="text-end pe-3" style="white-space: nowrap;">
                                                     <div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap">
                                                         {{-- Eye / detail button --}}
                                                         <button type="button"
                                                             class="btn btn-sm btn-soft-primary open-leave-detail-idx"
                                                             title="View Details"
                                                             data-bs-toggle="offcanvas"
                                                             data-bs-target="#leaveDetailDrawerIdx"
                                                             style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                             <i class="feather-eye fs-14"></i>
                                                         </button>

                                                         @if($isAdmin && $req->status !== 'cancelled')
                                                             {{-- Status Dropdown --}}
                                                             <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                                                 <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="background-color: var(--bs-primary) !important; color: #ffffff !important; font-size: 11.5px; height: 32px; border-radius: 8px; min-width: 120px; border: none;" title="Change Status">
                                                                     <span>{{ $statusBadge['lbl'] }}</span>
                                                                 </button>
                                                                 <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-1.5 mt-1 fs-12" style="min-width: 130px; border-radius: 8px; left: auto; right: 0; background: #ffffff; z-index: 1050;">
                                                                     @php
                                                                         $actionsList = [
                                                                             'pending' => __('hrms.leave.app.status_pending'),
                                                                             'approved' => __('hrms.leave.app.status_approved'),
                                                                             'rejected' => __('hrms.leave.app.status_rejected'),
                                                                             'unauthorized' => __('hrms.leave.app.status_unauthorized'),
                                                                             'unpaid' => __('hrms.leave.app.status_unpaid')
                                                                         ];
                                                                     @endphp
                                                                     @foreach($actionsList as $actionKey => $actionLabel)
                                                                         <li>
                                                                             <form action="{{ route('hrms.leaves.update-status', $req->id) }}" method="POST">
                                                                                 @csrf
                                                                                 <input type="hidden" name="action" value="{{ $actionKey }}">
                                                                                 <button type="submit" class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $req->status === $actionKey ? 'bg-light text-primary fw-bold' : '' }}" style="{{ $req->status === $actionKey ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                                     <span>{{ $actionLabel }}</span>
                                                                                 </button>
                                                                             </form>
                                                                         </li>
                                                                     @endforeach
                                                                 </ul>
                                                             </div>
                                                         @endif

                                                         {{-- Unified Withdraw / Cancellation Delete button --}}
                                                         @if($req->canWithdraw())
                                                             <form method="POST" action="{{ route('hrms.leaves.withdraw', $req->id) }}" onsubmit="return confirmFormSubmit(event, 'Withdraw this leave application?', { title: 'Withdraw Leave Application', variant: 'warning', confirmButtonText: 'Withdraw' })" class="d-inline">
                                                                 @csrf
                                                                 <button type="submit" class="btn btn-sm btn-soft-danger border" 
                                                                         title="Withdraw Application"
                                                                         style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                                     <i class="feather-trash-2 fs-14"></i>
                                                                 </button>
                                                             </form>
                                                         @elseif($req->canRequestCancellation())
                                                             <button type="button" class="btn btn-sm btn-soft-danger border" 
                                                                     title="Request Cancellation"
                                                                     onclick="openLeaveCancellationModal({{ $req->id }}, '{{ route('hrms.leaves.request-cancellation', $req->id) }}')"
                                                                     style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                                 <i class="feather-trash-2 fs-14"></i>
                                                             </button>
                                                         @else
                                                             <button type="button" class="btn btn-sm btn-light border disabled" 
                                                                     style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" disabled>
                                                                 <i class="feather-trash-2 fs-14"></i>
                                                             </button>
                                                         @endif
                                                     </div>
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
                            <div id="leaves_pagination_container">
                                @if($leaveRequests instanceof \Illuminate\Pagination\LengthAwarePaginator && $leaveRequests->hasPages())
                                    <x-ui.pagination
                                        class="px-0 py-0"
                                        :current-page="$leaveRequests->currentPage()"
                                        :total-pages="$leaveRequests->lastPage()"
                                        :total-results="$leaveRequests->total()"
                                        :per-page="$leaveRequests->perPage()"
                                        page-param="leaves_page"
                                    />
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Leave Detail Offcanvas Drawer (for Leave Applications index) --}}
                <x-ui.drawer id="leaveDetailDrawerIdx" :title="__('hrms.employees.lbl_leave_app_detail')" style="width:440px;max-width:100%;">
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
                                <div class="fs-11 text-muted mt-1">{{ __('hrms.leave.app.applied_on') }} <span class="fw-semibold text-dark" id="lid-applied">—</span></div>
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
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.duration') }}</div>
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

                    {{-- Cancellation Reason --}}
                    <div class="mb-3 d-none" id="lid-cancellation-wrap">
                        <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Cancellation Reason</div>
                        <div class="alert alert-soft-warning py-2 px-3 fs-13 mb-0" id="lid-cancellation" style="word-break: break-word !important; overflow-wrap: anywhere !important;"></div>
                    </div>

                    {{-- Notified Members --}}
                    <div class="mb-3 d-none" id="lid-notified-wrap">
                        <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.notify_members') }}</div>
                        <div class="fs-13 text-dark" id="lid-notified-names">—</div>
                    </div>

                    {{-- Status Change --}}
                    @if($isAdmin)
                        <hr class="my-3" id="lid-status-hr">
                        <div id="lid-status-change-wrap">
                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-2" style="letter-spacing:.5px;">{{ __('hrms.employees.lbl_update_status') }}</div>
                            <form method="POST" id="lid-status-form" action="">
                                @csrf
                                <div class="d-flex gap-2 align-items-center">
                                    <div class="flex-grow-1" style="margin-bottom: -1rem;">
                                        <x-ui.select name="status" id="lid-status-select" class="odoo-select2">
                                            <option value="pending">{{ __('hrms.leave.app.status_pending') }}</option>
                                            <option value="approved">{{ __('hrms.leave.app.status_approved') }}</option>
                                            <option value="rejected">{{ __('hrms.leave.app.status_rejected') }}</option>
                                            <option value="unauthorized">{{ __('hrms.leave.app.status_unauthorized') }}</option>
                                            <option value="unpaid">{{ __('hrms.leave.app.status_unpaid') }}</option>
                                        </x-ui.select>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-3 d-flex align-items-center gap-1" style="height: 38px; border-radius: 6px;">
                                        <i class="feather-check fs-12"></i> {{ __('hrms.common.apply') }}
                                    </button>
                                </div>
                                <div class="mt-2 d-none" id="lid-rejection-input-wrap">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-2 mt-2" style="letter-spacing:.5px;">{{ __('hrms.leave.app.rejection_reason') }}</div>
                                    <x-ui.textarea name="rejection_reason" id="lid-rejection-reason-input" rows="2" placeholder="{{ __('hrms.leave.app.rejection_reason_placeholder') }}" />
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
                                    <input type="hidden" id="encashments_sort_value" value="{{ $encashmentsSort ?? 'date_desc' }}">
                                    <!-- Registry Style Search Input -->
                                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                        <input type="text" id="encashments_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.leave.app.search_employee') }}" style="box-shadow: none; height: 32px;" value="{{ $encashmentsSearch ?? '' }}">
                                    </div>

                                    <!-- Sort Dropdown -->
                                    <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($encashmentsSort ?? 'date_desc') === 'date_desc' ? 'active' : '' }}" href="#" onclick="changeEncashmentsSort('date_desc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.encashment_app.sort_newest') }}</span>
                                            <i class="feather-check text-dark ms-auto encash-sort-check {{ ($encashmentsSort ?? 'date_desc') === 'date_desc' ? '' : 'd-none' }}"></i>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($encashmentsSort ?? '') === 'date_asc' ? 'active' : '' }}" href="#" onclick="changeEncashmentsSort('date_asc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.encashment_app.sort_oldest') }}</span>
                                            <i class="feather-check text-dark ms-auto encash-sort-check {{ ($encashmentsSort ?? '') === 'date_asc' ? '' : 'd-none' }}"></i>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($encashmentsSort ?? '') === 'days_desc' ? 'active' : '' }}" href="#" onclick="changeEncashmentsSort('days_desc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.encashment_app.sort_days_high_low') }}</span>
                                            <i class="feather-check text-dark ms-auto encash-sort-check {{ ($encashmentsSort ?? '') === 'days_desc' ? '' : 'd-none' }}"></i>
                                        </a>
                                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($encashmentsSort ?? '') === 'days_asc' ? 'active' : '' }}" href="#" onclick="changeEncashmentsSort('days_asc', this); event.preventDefault();">
                                            <span>{{ __('hrms.leave.encashment_app.sort_days_low_high') }}</span>
                                            <i class="feather-check text-dark ms-auto encash-sort-check {{ ($encashmentsSort ?? '') === 'days_asc' ? '' : 'd-none' }}"></i>
                                        </a>
                                    </x-ui.sort-dropdown>

                                    <!-- Filter Dropdown -->
                                    <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                        
                                        @if($isAdmin)
                                            <div class="mb-3" style="min-width: 250px;">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.leave.encashment_app.employee') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_encashment_employee_id">
                                                    <option value="" {{ ($encashmentsEmployeeId ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.common.all_employees') }}</option>
                                                    @foreach(($allEmployees ?? $employees ?? []) as $emp)
                                                        <option value="{{ $emp->id }}" {{ ($encashmentsEmployeeId ?? '') == $emp->id ? 'selected' : '' }}>
                                                            {{ $emp->full_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        @endif

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('ui.status') ?? 'Status' }}</label>
                                            <x-ui.odoo-form-ui type="select" name="status" id="filter_encashment_status">
                                                <option value="" {{ ($encashmentsStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.leave.encashment_app.all_statuses') }}</option>
                                                <option value="pending" {{ ($encashmentsStatus ?? '') === 'pending' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_pending') }}</option>
                                                <option value="approved" {{ ($encashmentsStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_approved') }}</option>
                                                <option value="rejected" {{ ($encashmentsStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_rejected') }}</option>
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
                                        @forelse(($leaveEncashments ?? $encashments ?? []) as $enc)
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
                                                    <td class="text-end pe-3" style="white-space: nowrap;">
                                                        <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                                            <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="background-color: var(--bs-primary) !important; color: #ffffff !important; font-size: 11.5px; height: 32px; border-radius: 8px; min-width: 130px; border: none;" title="Change Status">
                                                                <span>{{ $enc->status === 'approved' ? __('hrms.leave.app.status_approved') : ($enc->status === 'rejected' ? __('hrms.leave.app.status_rejected') : __('hrms.leave.app.status_pending')) }}</span>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-start shadow border-0 p-1.5 mt-1 fs-12" style="min-width: 100%; width: 100%; border-radius: 8px; left: 0; background: #ffffff;">
                                                                <li>
                                                                    <form action="{{ route('hrms.leaves.encashment.approve', $enc->id) }}" method="POST">
                                                                        @csrf
                                                                        <button type="submit" class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $enc->status === 'approved' ? 'bg-light text-primary fw-bold' : '' }}" style="{{ $enc->status === 'approved' ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                            <span>{{ __('hrms.leave.app.status_approved') }}</span>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <li>
                                                                    <form action="{{ route('hrms.leaves.encashment.reject', $enc->id) }}" method="POST">
                                                                        @csrf
                                                                        <button type="submit" class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $enc->status === 'rejected' ? 'bg-light text-primary fw-bold' : '' }}" style="{{ $enc->status === 'rejected' ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                            <span>{{ __('hrms.leave.app.status_rejected') }}</span>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            </ul>
                                                        </div>
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
                            <div id="encashments_pagination_container">
                                @if($leaveEncashments instanceof \Illuminate\Pagination\LengthAwarePaginator && $leaveEncashments->hasPages())
                                    <x-ui.pagination
                                        class="px-0 py-0"
                                        :current-page="$leaveEncashments->currentPage()"
                                        :total-pages="$leaveEncashments->lastPage()"
                                        :total-results="$leaveEncashments->total()"
                                        :per-page="$leaveEncashments->perPage()"
                                        page-param="encash_page"
                                    />
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Cancellation Request Modal -->
    <div class="modal fade" id="leaveCancellationModal" tabindex="-1" aria-labelledby="leaveCancellationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="leaveCancellationModalLabel">
                        <i class="feather-x-circle text-warning me-2"></i>Request Leave Cancellation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="leaveCancellationForm" method="POST" action="">
                    @csrf
                    <div class="modal-body p-4">
                        <p class="text-muted fs-13 mb-3">
                            Please provide a reason for requesting cancellation of this approved leave. The admin will review and approve or deny your request.
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark fs-13">Cancellation Reason <span class="text-danger">*</span></label>
                            <textarea name="cancellation_reason" id="leave_cancellation_reason" class="form-control fs-13" rows="3" placeholder="Explain why you want to cancel this leave..." required maxlength="1000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark fw-semibold">
                            <i class="feather-send me-1"></i>Submit Request
                        </button>
                    </div>
                </form>
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
                                        @foreach (($allEmployees ?? $employees ?? []) as $emp)
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
                                @foreach (($allEmployees ?? $employees ?? []) as $emp)
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
                                    @foreach(($allEmployees ?? $employees ?? []) as $emp)
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
        </div>
    </div>
@endsection
