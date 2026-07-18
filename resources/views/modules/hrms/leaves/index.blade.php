@extends('layouts.duralux')

@section('title', 'Leave Applications | SaaS ERP')
@section('page-title', 'Leave Applications')
@section('breadcrumb', 'HRMS / Leave Applications')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#applyLeaveModal" class="fw-bold text-uppercase">
            Apply Leave
        </x-ui.button>
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
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script>
        var employeeDataMap = @json($employeeDataMap);

        $(document).ready(function() {
            // Append modals to body to prevent blur and backdrop overlay conflicts
            $('#applyLeaveModal').appendTo('body');
            $('#rejectLeaveModal').appendTo('body');

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
                
                $leaveTypeSelect.empty().append('<option value="">Select Leave Type</option>');

                if (empId && employeeDataMap[empId]) {
                    var types = employeeDataMap[empId];
                    types.forEach(function(t) {
                        var text = t.name + ' (Remaining: ' + t.remaining + ' / ' + t.quota + ' days)';
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
                    $leaveTypeSelect.empty().append('<option value="">Select Leave Type</option>');
                    var types = employeeDataMap[defaultEmpId];
                    types.forEach(function(t) {
                        var text = t.name + ' (Remaining: ' + t.remaining + ' / ' + t.quota + ' days)';
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
                    $('#policy_duration').html('<span class="policy-icon-wrapper"><i class="feather-clock"></i></span><span>Duration: <strong>' + minDur + ' to ' + maxDur + ' day(s)</strong> per application.</span>');

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

                        $('#policy_advance').html('<span class="policy-icon-wrapper"><i class="feather-alert-circle"></i></span><span>Notice required: Apply at least <strong>' + advanceDays + ' day(s)</strong> in advance.</span>').removeClass('d-none');
                    } else {
                        $('#start_date').removeAttr('min');
                        $('#end_date').removeAttr('min');
                        $('#policy_advance').addClass('d-none');
                    }

                    // Probation Rule
                    if (probRules.probation_rule && probRules.probation_rule !== 'allow') {
                        var probTxt = probRules.probation_rule === 'disallow' 
                            ? 'Cannot apply during probation.' 
                            : 'Allowed only after <strong>' + (probRules.probation_months || 3) + ' months</strong> of service.';
                        $('#policy_probation').html('<span class="policy-icon-wrapper"><i class="feather-user-check"></i></span><span>Probation: ' + probTxt + '</span>').removeClass('d-none');
                    } else {
                        $('#policy_probation').addClass('d-none');
                    }

                    // Notice Period Rule
                    if (noticeRules.notice_rule && noticeRules.notice_rule !== 'allow') {
                        var noticeTxt = noticeRules.notice_rule === 'disallow' 
                            ? 'Cannot apply during notice period.' 
                            : 'Requires special permission during notice period.';
                        $('#policy_notice').html('<span class="policy-icon-wrapper"><i class="feather-user-x"></i></span><span>Notice Period: ' + noticeTxt + '</span>').removeClass('d-none');
                    } else {
                        $('#policy_notice').addClass('d-none');
                    }

                    // Attachment Required
                    if (appRules.require_attachment) {
                        $('#policy_attachment').html('<span class="policy-icon-wrapper"><i class="feather-paperclip"></i></span><span>Attachment: Required for <strong>' + (appRules.attachment_days || 3) + ' day(s) or more</strong>.</span>').removeClass('d-none');
                    } else {
                        $('#policy_attachment').addClass('d-none');
                    }

                    // Approval levels
                    var approvalLevel = approvalRules.workflow_level || '1_level';
                    var approvalText = approvalLevel === 'auto' ? 'Auto-Approved' : (approvalLevel === '1_level' ? '1 Level Approval Required' : '2 Levels Approval Required');
                    $('#policy_approval').html('<span class="policy-icon-wrapper"><i class="feather-check-square"></i></span><span>Workflow: ' + approvalText + '</span>');

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
                            alert("An attachment is required for this leave type when the duration is " + attachmentDays + " day(s) or more. Please upload a supporting document before submitting.");
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
                    $('#calculated_duration_display').text('End date must be on or after start date');
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

                $('#calculated_duration_display').html('Estimated Duration: <strong>' + duration + ' day(s)</strong> (excluding Sundays)');

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
    </script>
@endpush

@section('content')
    <div class="row pt-4 px-4">
        <!-- Leave Balances Section (Visible to logged-in employee) -->
        @if($employee)
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white border-0 pt-4">
                        <h5 class="fw-bold text-dark mb-0">Your Leave Balances</h5>
                        <p class="text-muted fs-12 mb-0">Leave Plan: <span class="badge bg-secondary fw-semibold">{{ $employee->leavePlan ? $employee->leavePlan->name : 'No active plan assigned' }}</span></p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @forelse ($balances as $balance)
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="leave-balance-card p-4 text-center">
                                        <h6 class="text-muted text-uppercase fs-11 fw-bolder mb-2">{{ $balance->leaveType->name }}</h6>
                                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                                            <span class="fs-1 fw-bold text-primary">{{ $balance->remaining }}</span>
                                            <span class="text-muted fs-13">/ {{ $balance->allocated }} days</span>
                                        </div>
                                        <div class="progress progress-sm" style="height: 6px;">
                                            @php
                                                $pct = $balance->allocated > 0 ? ($balance->used / $balance->allocated) * 100 : 0;
                                            @endphp
                                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ 100 - $pct }}%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-3 fs-12 text-muted">
                                            <span>Used: <strong>{{ $balance->used }}</strong></span>
                                            <span>Type: <strong>{{ ucfirst($balance->leaveType->type) }}</strong></span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-center py-4">
                                    <p class="text-muted mb-0">No active leave type balances found for your assigned plan.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Leaves Applications List Table -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Leave Applications</h5>
                        <p class="text-muted fs-12 mb-0">Review applied leaves, approval cycles, and status updates.</p>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" action="javascript:void(0);" class="d-flex align-items-center gap-2 m-0" id="leavesFilterForm">
                            <!-- Registry Style Search Input -->
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" id="leaves_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search Employee..." style="box-shadow: none; height: 32px;">
                            </div>

                            <!-- Sort Dropdown with Checkmark Icons -->
                            <x-ui.sort-dropdown label="Sort">
                                <a class="dropdown-item py-2 d-flex align-items-center active" href="#" onclick="changeLeavesSort('date_desc', this); event.preventDefault();">
                                    <span>Newest Applied</span>
                                    <i class="feather-check text-dark ms-auto sort-check"></i>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="changeLeavesSort('date_asc', this); event.preventDefault();">
                                    <span>Oldest Applied</span>
                                    <i class="feather-check text-dark ms-auto sort-check d-none"></i>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="changeLeavesSort('duration_desc', this); event.preventDefault();">
                                    <span>Duration (High-Low)</span>
                                    <i class="feather-check text-dark ms-auto sort-check d-none"></i>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="changeLeavesSort('duration_asc', this); event.preventDefault();">
                                    <span>Duration (Low-High)</span>
                                    <i class="feather-check text-dark ms-auto sort-check d-none"></i>
                                </a>
                            </x-ui.sort-dropdown>

                            <!-- Filter Dropdown -->
                            <x-ui.filter label="Filter" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                
                                @if($isAdmin)
                                    <div class="mb-3" style="min-width: 250px;">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Employee</label>
                                        <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_employee_id">
                                            <option value="">All Employees</option>
                                            @foreach($allEmployees as $emp)
                                                <option value="{{ $emp->id }}">
                                                    {{ $emp->full_name }}
                                                </option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </div>
                                @endif

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                    <x-ui.odoo-form-ui type="select" name="status" id="filter_status">
                                        <option value="">All Statuses</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>

                                <div class="d-flex gap-2">
                                    <button type="button" id="btnApplyFilters" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                    <button type="button" id="btnResetFilters" class="btn btn-light btn-sm border flex-grow-1">Reset</button>
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
                                        <th>Employee</th>
                                    @endif
                                    <th>Leave Type</th>
                                    <th>Duration & Timeline</th>
                                    <th>Reason</th>
                                    <th>Workflow Level</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
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
                                                Balance: <strong>{{ $rowRemaining }}</strong> / {{ $rowAllocated }} days
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
                                                {{ $dateRange }} <span class="text-secondary fs-12 fw-normal">({{ floatval($req->duration) }} day(s))</span>
                                            </div>
                                            @if($req->start_date_type !== 'full_day' || $req->end_date_type !== 'full_day')
                                                <small class="text-muted fs-11">
                                                    Session: <span class="text-capitalize">{{ str_replace('_', ' ', $req->start_date_type) }}</span> 
                                                    @if(!$req->start_date->isSameDay($req->end_date))
                                                        to <span class="text-capitalize">{{ str_replace('_', ' ', $req->end_date_type) }}</span>
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
                                                      title="Application Reason" 
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
                                                     Approved
                                                 @elseif($req->status === 'rejected')
                                                     Rejected
                                                 @elseif($req->status === 'unauthorized' || $req->status === 'unpaid')
                                                     Processed
                                                 @else
                                                     Level {{ $req->current_level }}
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
                                                       title="Rejection Reason" 
                                                       data-bs-content="{{ $req->rejection_reason }}">
                                                     Rejected <i class="feather-help-circle fs-10"></i>
                                                 </span>
                                             @else
                                                 <span class="badge rounded-pill fw-semibold px-3 py-2 fs-11 badge-{{ $req->status }}">
                                                     {{ ucfirst($req->status) }}
                                                 </span>
                                             @endif
                                         </td>
                                         <td class="text-end">
                                             <div class="d-flex align-items-center justify-content-end gap-2">
                                                 @if($req->attachment_path)
                                                     <a href="{{ asset('storage/' . $req->attachment_path) }}" target="_blank" class="btn btn-light btn-sm" title="View Attachment">
                                                         <i class="feather-paperclip text-secondary"></i>
                                                     </a>
                                                 @endif
                                                 @if($isAdmin)
                                                     <form method="POST" action="{{ route('hrms.leaves.update-status', $req->id) }}" class="m-0 p-0">
                                                         @csrf
                                                         <select name="status" class="form-select form-select-sm status-dropdown fw-semibold fs-11" data-id="{{ $req->id }}" style="width: 130px; border-radius: 6px;">
                                                             <option value="pending" {{ $req->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                             <option value="approved" {{ $req->status === 'approved' ? 'selected' : '' }}>Approved</option>
                                                             <option value="rejected" {{ $req->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                                             <option value="unauthorized" {{ $req->status === 'unauthorized' ? 'selected' : '' }}>Unauthorized</option>
                                                             <option value="unpaid" {{ $req->status === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
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
                                            No leave applications submitted yet.
                                        </td>
                                    </tr>
                                @endforelse
                                <tr id="no_matching_leaves_row" class="d-none">
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="feather-folder fs-3 d-block mb-3 text-secondary"></i>
                                        No matching leave applications found.
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
                            Showing <span id="leaves_showing_start">0</span> to <span id="leaves_showing_end">0</span> of <strong id="leaves_total_count">0</strong> entries
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
                    <h5 class="modal-title fw-bold text-dark" id="applyLeaveModalLabel">Apply for Leave</h5>
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
                                    <x-ui.odoo-form-ui type="select" label="Employee" name="employee_id" id="employee_select" :required="true" class="odoo-select2-custom">
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
                                <x-ui.odoo-form-ui type="select" label="Leave Type" name="leave_type_id" id="leave_type_select" :required="true" class="odoo-select2-custom">
                                    <option value="">Select Leave Type</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" id="start_date" :required="true" class="odoo-underline-input" />
                            </div>
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="select" label="Start Session" name="start_date_type" id="start_date_type" :required="true" class="odoo-select2-custom">
                                    <option value="full_day">Full Day</option>
                                    <option value="first_half">First Half</option>
                                    <option value="second_half">Second Half</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" id="end_date" :required="true" class="odoo-underline-input" />
                            </div>
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="select" label="End Session" name="end_date_type" id="end_date_type" :required="true" class="odoo-select2-custom">
                                    <option value="full_day">Full Day</option>
                                    <option value="first_half">First Half</option>
                                    <option value="second_half">Second Half</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <div id="calculated_duration_display" class="alert alert-info py-2 fs-12 mb-0">
                                Estimated Duration: 0 day(s)
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" label="Reason for Leave" name="reason" :required="true" class="odoo-underline-input" placeholder="Please describe details of your leave request..."></x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label for="attachment" class="form-label fw-bold text-secondary fs-13">Upload Attachment (e.g. medical certificates or documents)</label>
                            <input type="file" name="attachment" id="attachment" class="form-control">
                            <small class="text-muted fs-11">Formats allowed: PDF, PNG, JPG, JPEG (Max size: 5MB)</small>
                            <div id="attachment_required_warning" class="text-danger fs-12 mt-1 d-none fw-semibold">
                                <i class="feather-alert-triangle"></i> Supporting document is required for this duration according to leave policy.
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Notify Members" name="notified_contacts[]" id="notified_contacts" :required="false" :multiple="true" class="odoo-select2-custom" placeholder="Select team members to keep informed">
                                @foreach ($allEmployees as $emp)
                                    @if (!$employee || $emp->id !== $employee->id)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endif
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-dark">Submit Request</button>
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
                    <h5 class="modal-title fw-bold text-dark" id="rejectLeaveModalLabel">Reject Leave Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rejectLeaveForm" method="POST">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label fw-bold text-secondary fs-13">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="4" placeholder="Please provide reason for rejecting this leave request..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger text-white">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
