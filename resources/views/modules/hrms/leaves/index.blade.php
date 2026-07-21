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

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
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
@endpush

@section('content')
    <div class="row pt-4 px-4">
        <!-- Leave Balances Section (Visible to logged-in employee) -->
        @if($employee)
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 pt-4">
                        <h5 class="fw-bold text-dark mb-0">{{ __('hrms.leave.app.your_leave_balances') }}</h5>
                        <p class="text-muted fs-12 mb-0">{{ __('hrms.leave.app.leave_plan') }} <span class="badge bg-secondary fw-semibold">{{ $employee->leavePlan ? $employee->leavePlan->name : __('hrms.leave.app.no_active_plan') }}</span></p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @forelse ($balances as $balance)
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="leave-balance-card p-4 text-center">
                                        <h6 class="text-muted text-uppercase fs-11 fw-bolder mb-2">{{ $balance->leaveType->name }}</h6>
                                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                                            <span class="fs-1 fw-bold text-primary">{{ $balance->remaining }}</span>
                                            <span class="text-muted fs-13">/ {{ $balance->allocated }} {{ __('hrms.leave.days') }}</span>
                                        </div>
                                        <div class="progress progress-sm" style="height: 6px;">
                                            @php
                                                $pct = $balance->allocated > 0 ? ($balance->used / $balance->allocated) * 100 : 0;
                                            @endphp
                                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ 100 - $pct }}%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-3 fs-12 text-muted">
                                            <span>{{ __('hrms.leave.app.used') }} <strong>{{ $balance->used }}</strong></span>
                                            <span>{{ __('hrms.leave.app.type') }} <strong>{{ $balance->leaveType->type === 'paid' ? __('hrms.leave.paid') : __('hrms.leave.unpaid') }}</strong></span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-center py-4">
                                    <p class="text-muted mb-0">{{ __('hrms.leave.app.no_balances_found') }}</p>
                                </div>
                            @endforelse
                        </div>
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
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            @if($isAdmin)
                                                <th>{{ __('hrms.employees.tbl_employee') ?? 'Employee' }}</th>
                                            @endif
                                            <th>{{ __('hrms.leave.leave_types') }}</th>
                                            <th>{{ __('hrms.leave.app.duration_timeline') }}</th>
                                            <th>{{ __('hrms.leave.app.reason') }}</th>
                                            <th>{{ __('hrms.leave.app.workflow_level') }}</th>
                                            <th>{{ __('ui.status') ?? 'Status' }}</th>
                                            <th class="text-end">{{ __('hrms.leave.app.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="leavesTableBody">
                                        @forelse($leaveRequests as $req)
                                            <tr class="leave-row" 
                                                data-employee="{{ strtolower($req->employee->full_name) }} {{ strtolower($req->employee->employee_id) }}"
                                                data-employee-id="{{ $req->employee_id }}"
                                                data-status="{{ $req->status }}"
                                                data-duration="{{ $req->duration }}"
                                                data-created-at="{{ $req->created_at->timestamp }}">
                                                @if($isAdmin)
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div>
                                                                <h6 class="fw-bold mb-0 fs-13 text-dark">{{ $req->employee->full_name }}</h6>
                                                                <small class="text-muted fs-11">{{ $req->employee->employee_id }}</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                @endif
                                                <td>
                                                    <span class="badge bg-light text-primary fw-semibold fs-12 mb-1">{{ $req->leaveType->name }}</span>
                                                    @php
                                                        $rowBalance = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $req->employee_id)
                                                            ->where('leave_type_id', $req->leave_type_id)
                                                            ->first();
                                                        $rowRemaining = $rowBalance ? floatval($rowBalance->remaining) : 0.0;
                                                        $rowAllocated = $rowBalance ? floatval($rowBalance->allocated) : floatval($req->leaveType->quota);
                                                    @endphp
                                                    <div class="text-muted fs-11" style="white-space: nowrap;">
                                                        {{ __('hrms.leave.app.remaining') }}: <strong>{{ $rowRemaining }}</strong> / {{ $rowAllocated }} {{ __('hrms.leave.days') }}
                                                    </div>
                                                </td>
                                                <td>
                                                    @php
                                                        $startFormat = $req->start_date->format('Y') === $req->end_date->format('Y') ? 'd M' : 'd M Y';
                                                        $startStr = $req->start_date->format($startFormat);
                                                        $endStr = $req->end_date->format('d M Y');
                                                        
                                                        if ($req->start_date->isSameDay($req->end_date)) {
                                                            $dateRange = $req->start_date->format('d M Y');
                                                        } else {
                                                            $dateRange = $startStr . ' - ' . $endStr;
                                                        }
                                                    @endphp
                                                    <div class="fs-13 text-dark fw-bold">
                                                        {{ $dateRange }} <span class="text-secondary fs-12 fw-normal">({{ __('hrms.leave.app.duration_days', ['duration' => floatval($req->duration)]) }})</span>
                                                    </div>
                                                    @if($req->start_date_type !== 'full_day' || $req->end_date_type !== 'full_day')
                                                        <small class="text-muted fs-11">
                                                            {{ __('hrms.leave.app.session') }} <span class="text-capitalize">{{ __('hrms.leave.app.' . $req->start_date_type) }}</span> 
                                                            @if(!$req->start_date->isSameDay($req->end_date))
                                                                {{ __('hrms.leave.app.to') }} <span class="text-capitalize">{{ __('hrms.leave.app.' . $req->end_date_type) }}</span>
                                                            @endif
                                                        </small>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if(strlen($req->reason) > 25)
                                                        <span class="text-secondary fs-13" 
                                                              style="cursor: pointer; border-bottom: 1px dashed #cbd5e1;" 
                                                              data-bs-toggle="popover" 
                                                              data-bs-trigger="click" 
                                                              data-bs-placement="top" 
                                                              title="{{ __('hrms.leave.app.application_reason') }}" 
                                                              data-bs-content="{{ $req->reason }}">
                                                            {{ Str::limit($req->reason, 25) }} <i class="feather-zoom-in fs-11 text-muted"></i>
                                                        </span>
                                                    @else
                                                        <span class="fs-13 text-secondary">{{ $req->reason }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                     <span class="text-secondary fs-12">
                                                         @if($req->status === 'approved')
                                                             {{ __('hrms.leave.app.status_approved') }}
                                                         @elseif($req->status === 'rejected')
                                                             {{ __('hrms.leave.app.status_rejected') }}
                                                         @elseif($req->status === 'unauthorized' || $req->status === 'unpaid')
                                                             {{ __('hrms.leave.app.processed') }}
                                                         @else
                                                             {{ __('hrms.leave.app.level_n', ['level' => $req->current_level]) }}
                                                         @endif
                                                     </span>
                                                 </td>
                                                 <td>
                                                     @if($req->status === 'rejected' && $req->rejection_reason)
                                                         <span class="badge rounded-pill fw-semibold px-3 py-2 fs-11 badge-rejected" 
                                                               style="cursor: pointer;" 
                                                               data-bs-toggle="popover" 
                                                               data-bs-trigger="click" 
                                                               data-bs-placement="top" 
                                                               title="{{ __('hrms.leave.app.rejection_reason') }}" 
                                                               data-bs-content="{{ $req->rejection_reason }}">
                                                             {{ __('hrms.leave.app.status_rejected') }} <i class="feather-help-circle fs-10"></i>
                                                         </span>
                                                     @else
                                                         <span class="badge rounded-pill fw-semibold px-3 py-2 fs-11 badge-{{ $req->status }}">
                                                             {{ __('hrms.leave.app.status_' . $req->status) }}
                                                         </span>
                                                     @endif
                                                 </td>
                                                 <td class="text-end">
                                                     <div class="d-flex align-items-center justify-content-end gap-2">
                                                         @if($req->attachment_path)
                                                             <a href="{{ asset('storage/' . $req->attachment_path) }}" target="_blank" class="btn btn-light btn-sm" title="{{ __('hrms.leave.app.view_attachment') }}">
                                                                 <i class="feather-paperclip text-secondary"></i>
                                                             </a>
                                                         @endif
                                                         @if($isAdmin)
                                                             <form method="POST" action="{{ route('hrms.leaves.update-status', $req->id) }}" class="m-0 p-0">
                                                                 @csrf
                                                                 <select name="status" class="form-select form-select-sm status-dropdown fw-semibold fs-11" data-id="{{ $req->id }}" style="width: 130px; border-radius: 6px;">
                                                                     <option value="pending" {{ $req->status === 'pending' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_pending') }}</option>
                                                                     <option value="approved" {{ $req->status === 'approved' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_approved') }}</option>
                                                                     <option value="rejected" {{ $req->status === 'rejected' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_rejected') }}</option>
                                                                     <option value="unauthorized" {{ $req->status === 'unauthorized' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_unauthorized') }}</option>
                                                                     <option value="unpaid" {{ $req->status === 'unpaid' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_unpaid') }}</option>
                                                                 </select>
                                                             </form>
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
                            <div class="erp-pagination-container">
                                <ul class="erp-pagination mb-2" id="leaves_pagination_ul">
                                    <!-- Dynamically generated pagination links -->
                                </ul>
                                <div class="erp-pagination-info">
                                    {!! __('hrms.leave.app.showing_entries', [
                                        'start' => '<span id="leaves_showing_start">0</span>',
                                        'end' => '<span id="leaves_showing_end">0</span>',
                                        'total' => '<strong id="leaves_total_count">0</strong>'
                                    ]) !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
                                                            <form method="POST" action="{{ route('hrms.leaves.encashment.destroy', $enc->id) }}" class="d-inline-block" onsubmit="return confirm('{{ __('hrms.leave.encashment_app.confirm_delete') }}');">
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
                            <label for="attachment" class="form-label fw-bold text-secondary fs-13">{{ __('hrms.leave.app.upload_attachment') }}</label>
                            <input type="file" name="attachment" id="attachment" class="form-control">
                            <small class="text-muted fs-11">{{ __('hrms.leave.app.formats_allowed') }}</small>
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
                <div class="modal-header bg-light px-4 py-3 border-bottom">
                    <h5 class="modal-title fw-bold text-dark" id="applyEncashmentModalLabel">{{ __('hrms.leave.encashment_app.apply_for_encashment') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('hrms.leaves.encashment.store') }}">
                    @csrf
                    <div class="modal-body p-4">
                        @if($isAdmin)
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark fs-13">{{ __('hrms.leave.encashment_app.select_employee') }} <span class="text-danger">*</span></label>
                                <select name="employee_id" id="encashment_employee_id" class="form-select odoo-select2-custom" required>
                                    <option value="">{{ __('hrms.leave.encashment_app.select_employee') }}...</option>
                                    @foreach($allEmployees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                        @endif

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark fs-13">{{ __('hrms.leave.encashment_app.select_leave_type') }} <span class="text-danger">*</span></label>
                            <select name="leave_type_id" id="encashment_leave_type_id" class="form-select odoo-select2-custom" required>
                                <option value="">{{ __('hrms.leave.encashment_app.select_leave_type') }}...</option>
                                @if($employee && isset($employeeDataMap[$employee->id]))
                                    @foreach($employeeDataMap[$employee->id] as $t)
                                        <option value="{{ $t['id'] }}">{{ $t['name'] }} ({{ __('hrms.leave.app.remaining') }}: {{ $t['remaining'] }} {{ __('hrms.leave.days') }})</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark fs-13">{{ __('hrms.leave.encashment_app.requested_days') }} <span class="text-danger">*</span></label>
                            <input type="number" name="requested_days" class="form-control" step="0.5" min="0.5" placeholder="e.g. 2.5" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark fs-13">{{ __('hrms.leave.encashment_app.reason') }}</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="{{ __('hrms.leave.encashment_app.reason_placeholder') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-4 py-3 border-top">
                        <button type="button" class="btn btn-light border fw-semibold" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary fw-semibold px-4"><i class="feather-check me-1"></i> {{ __('hrms.leave.encashment_app.submit_encashment') }}</button>
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
