@extends('layouts.duralux')

@section('title', __('hrms.sidebar.shift_roster') . ' | SaaS ERP')
@section('page-title', __('hrms.sidebar.shift_roster'))
@section('breadcrumb', 'HRMS / ' . __('hrms.sidebar.shift_roster'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <button type="button" id="btnApplyShift" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#applyShiftChangeModal" style="height: 38px;">
            <i class="feather-plus"></i> {{ __('hrms.shift_change.apply_shift_change') }}
        </button>
        <button type="button" id="btnApplyOvertime" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1 d-none" data-bs-toggle="modal" data-bs-target="#applyOvertimeModal" style="height: 38px;">
            <i class="feather-plus"></i> {{ __('hrms.overtime.apply_overtime') }}
        </button>
    </div>
@endsection

@push('styles')
    <style>
        /* Underlined Horizontal Tabs (matching Leave module) */
        #shiftOvertimeTabs .nav-link {
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
        #shiftOvertimeTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #shiftOvertimeTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
            font-weight: 600;
        }

        /* High-specificity overrides to force Select2 options and selection text to dark grey, not blue */
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option,
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option *,
        body .select2-container .select2-results__option,
        body .select2-container .select2-results__option *,
        body .select2-results__option,
        body .select2-results__option *,
        .select2-results__option,
        .select2-results__option * {
            color: #1e293b !important;
        }

        body .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #1e293b !important;
        }

        /* High-specificity overrides for highlighted/hovered items */
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option--highlighted,
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option--highlighted *,
        body .select2-container .select2-results__option--highlighted,
        body .select2-container .select2-results__option--highlighted *,
        body .select2-results__option--highlighted,
        body .select2-results__option--highlighted *,
        .select2-results__option--highlighted,
        .select2-results__option--highlighted * {
            color: #ffffff !important;
            background-color: var(--bs-primary) !important;
        }

        /* Specific label width override for Overtime Policy Configuration modal to fit text on a single line */
        #overtimeSettingsModal .odoo-form-label {
            width: 180px !important;
        }

        /* Action Status Dropdown Styling */
        .btn-status-dropdown {
            background-color: #7c6f6c !important;
            color: #ffffff !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            height: 36px !important;
            border-radius: 8px !important;
            width: 120px !important;
            border: none !important;
            padding: 0 12px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .btn-status-dropdown:hover,
        .btn-status-dropdown:focus,
        .btn-status-dropdown:active {
            background-color: #6a5e5a !important;
            color: #ffffff !important;
        }
        .btn-status-dropdown::after {
            display: inline-block;
            margin-left: 8px;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
            color: #ffffff !important;
        }

        .status-dropdown-menu {
            min-width: 120px !important;
            width: 120px !important;
            border-radius: 8px !important;
            border: none !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
            padding: 6px !important;
            background: #ffffff !important;
        }
        .status-dropdown-menu .dropdown-item {
            text-align: center !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            color: #1e293b !important;
            background: transparent !important;
            transition: all 0.2s ease;
        }
        .status-dropdown-menu .dropdown-item:hover {
            background-color: #f8fafc !important;
            color: #1e293b !important;
        }
        .status-dropdown-menu .dropdown-item.active-status {
            background-color: #f1f5f9 !important;
            color: #1e293b !important;
            font-weight: 700 !important;
        }

        /* ERP Pagination styles matching WFH/Leaves theme */
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

@section('content')

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="feather-alert-triangle me-2"></i>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row pt-4 px-4">
        {{-- Tabs Header (matching Leave Module styling) --}}
        <div class="col-12 mb-2">
            <ul class="nav gap-2 border-bottom pb-2" id="shiftOvertimeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($activeTab === 'shift') active @endif" id="tab-shift" data-bs-toggle="tab" data-bs-target="#shift-pane" type="button" role="tab" aria-controls="shift-pane" aria-selected="@if($activeTab === 'shift') true @else false @endif">
                        <i class="feather-git-pull-request me-1"></i> {{ __('hrms.shift_change.title') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($activeTab === 'overtime') active @endif" id="tab-overtime" data-bs-toggle="tab" data-bs-target="#overtime-pane" type="button" role="tab" aria-controls="overtime-pane" aria-selected="@if($activeTab === 'overtime') true @else false @endif">
                        <i class="feather-clock me-1"></i> {{ __('hrms.overtime.title') }}
                    </button>
                </li>
            </ul>
        </div>

        {{-- Tab Content --}}
        <div class="col-12">
            <div class="tab-content" id="shiftOvertimeTabContent">
                
                @include('modules.hrms.shift-overtime.tabs.shift')
                @include('modules.hrms.shift-overtime.tabs.overtime')
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        var originalShiftOptions = '';

        $(document).ready(function() {
            // Append modals to body root to prevent Bootstrap z-index/backdrop issues inside tab panes
            $('#applyShiftChangeModal').appendTo('body');
            $('#applyOvertimeModal').appendTo('body');
            $('#overtimeSettingsModal').appendTo('body');
            $('#approveOvertimeTabModal').appendTo('body');
            $('#rejectOvertimeTabModal').appendTo('body');

            // Save the original list of shift options for dynamic rebuilds
            originalShiftOptions = $('#shift_requested_shift_id').html();

            // Initialize select2 inside modals with dropdownParent to fix Bootstrap focus/typing issue
            $('#applyShiftChangeModal select.odoo-select2, #applyOvertimeModal select.odoo-select2').each(function() {
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

            // Set initial button visibility depending on active tab
            syncPageActionButtons('{{ $activeTab }}');

            // Trigger employee change logic on load if pre-selected
            $('#shift_employee_id').trigger('change');

            // Trigger pagination display on load
            updateShiftPagination();
            updateOvertimePagination();
        });

        // Sync apply button state with the selected tab
        function syncPageActionButtons(activeTab) {
            if (activeTab === 'shift' || activeTab === 'shift-pane') {
                $('#btnApplyShift').removeClass('d-none');
                $('#btnApplyOvertime').addClass('d-none');
            } else {
                $('#btnApplyShift').addClass('d-none');
                $('#btnApplyOvertime').removeClass('d-none');
            }
        }

        // Toggle Shift Change options in Apply modal using jQuery (Select2 compatible)
        $(document).on('change', '#shift_change_type', function() {
            const val = $(this).val();
            const $endDateContainer = $('#end_date_container');
            const $recurringContainer = $('#recurring_days_container');

            if (val === 'temporary') {
                $endDateContainer.removeClass('d-none');
                $recurringContainer.addClass('d-none');
            } else if (val === 'permanent') {
                $endDateContainer.addClass('d-none');
                $recurringContainer.addClass('d-none');
            } else if (val === 'recurring') {
                $endDateContainer.addClass('d-none');
                $recurringContainer.removeClass('d-none');
            }
        });

        // Auto-fill end date when start date is selected (matching Leave and WFH)
        $('#shift_start_date').on('change', function() {
            var startDate = $(this).val();
            if (startDate) {
                $('#shift_end_date').val(startDate);
            }
        });

        // Dynamic requested shift filter logic to completely remove selected employee's current shift
        $('#shift_employee_id').on('change', function() {
            var currentShiftId = $(this).find('option:selected').attr('data-shift-id');
            var $reqShiftSelect = $('#shift_requested_shift_id');
            
            // Restore all original options from backup
            if (originalShiftOptions) {
                $reqShiftSelect.html(originalShiftOptions);
            }
            
            if (currentShiftId) {
                // Completely remove option matching the employee's current active shift
                $reqShiftSelect.find('option[value="' + currentShiftId + '"]').remove();
                
                // If it was selected, reset select to default empty selection
                if ($reqShiftSelect.val() === currentShiftId) {
                    $reqShiftSelect.val('');
                }
            }
            
            // Refresh select2 dropdown display state
            $reqShiftSelect.trigger('change.select2');
        });

        // Client-side Shift Change filtering, sorting and pagination
        var currentShiftPage = 1;
        var shiftItemsPerPage = 10;
        var currentShiftSort = 'newest';

        function updateShiftPagination() {
            var searchVal = $('#shift_search').val() ? $('#shift_search').val().toLowerCase().trim() : '';
            var empId = $('#filter_shift_employee_id').val();
            var status = $('#filter_shift_status').val();

            var $visibleRows = $('.shift-row').filter(function() {
                var $row = $(this);
                var rowEmp = $row.attr('data-employee') || '';
                var rowEmpId = $row.attr('data-employee-id') || '';
                var rowStatus = $row.attr('data-status') || '';

                var matchesSearch = !searchVal || rowEmp.indexOf(searchVal) !== -1;
                var matchesEmp = !empId || rowEmpId === empId;
                var matchesStatus = !status || rowStatus === status;

                return matchesSearch && matchesEmp && matchesStatus;
            });

            // Sort logic
            var rowsArray = $visibleRows.get();
            rowsArray.sort(function(a, b) {
                var keyA = parseInt($(a).attr('data-created-at') || 0);
                var keyB = parseInt($(b).attr('data-created-at') || 0);
                return currentShiftSort === 'newest' ? keyB - keyA : keyA - keyB;
            });

            var $tbody = $('#shiftTableBody');
            $.each(rowsArray, function(index, row) {
                $tbody.append(row);
            });

            var totalItems = $visibleRows.length;
            var totalPages = Math.ceil(totalItems / shiftItemsPerPage) || 1;

            if (currentShiftPage > totalPages) {
                currentShiftPage = totalPages;
            }
            if (currentShiftPage < 1) {
                currentShiftPage = 1;
            }

            var startIndex = (currentShiftPage - 1) * shiftItemsPerPage;
            var endIndex = Math.min(startIndex + shiftItemsPerPage, totalItems);

            $('.shift-row').hide();
            $visibleRows.slice(startIndex, endIndex).show();

            // Hide empty initial row if dynamic results are evaluated
            $('#empty_initial_shift_row').hide();

            if (totalPages > 1) {
                $('#shift_pagination_container').show();
            } else {
                $('#shift_pagination_container').hide();
            }

            if (totalItems === 0) {
                $('#no_matching_shift_row').removeClass('d-none');
            } else {
                $('#no_matching_shift_row').addClass('d-none');
            }

            $('#shift_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
            $('#shift_showing_end').text(endIndex);
            $('#shift_total_count').text(totalItems);

            var paginationHtml = '';
            paginationHtml += `
                <li class="page-item ${currentShiftPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentShiftPage - 1}" aria-label="Previous">
                        <i class="feather-chevron-left"></i>
                    </a>
                </li>
            `;
            for (var i = 1; i <= totalPages; i++) {
                paginationHtml += `
                    <li class="page-item ${currentShiftPage === i ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            }
            paginationHtml += `
                <li class="page-item ${currentShiftPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentShiftPage + 1}" aria-label="Next">
                        <i class="feather-chevron-right"></i>
                    </a>
                </li>
            `;
            $('#shift_pagination_ul').html(paginationHtml);
        }

        $(document).on('click', '#shift_pagination_ul .page-link', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            if (page && page !== currentShiftPage) {
                currentShiftPage = page;
                updateShiftPagination();
            }
        });

        $('#shift_search').on('input', function() {
            currentShiftPage = 1;
            updateShiftPagination();
        });

        function closeAllFilterDropdowns() {
            $('.erp-filter-dropdown').removeClass('show');
            $('.erp-filter-dropdown .dropdown-menu').removeClass('show');
        }

        $('#shiftFilterForm').on('submit', function(e) {
            e.preventDefault();
            currentShiftPage = 1;
            updateShiftPagination();
            closeAllFilterDropdowns();
        });

        function setShiftSort(value, element) {
            currentShiftSort = value;
            if (element) {
                var menu = element.closest('.dropdown-menu');
                if (menu) {
                    menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                        el.classList.remove('active');
                    });
                }
                element.classList.add('active');
            }
            currentShiftPage = 1;
            updateShiftPagination();
        }

        function resetShiftFilters() {
            $('#shift_search').val('');
            $('#filter_shift_employee_id').val('').trigger('change');
            $('#filter_shift_status').val('').trigger('change');
            currentShiftSort = 'newest';
            $('#shiftFilterForm').find('.dropdown-menu .dropdown-item').removeClass('active').first().addClass('active');
            currentShiftPage = 1;
            updateShiftPagination();
            closeAllFilterDropdowns();
        }

        // Client-side Overtime filtering, sorting and pagination
        var currentOvertimePage = 1;
        var overtimeItemsPerPage = 10;
        var currentOvertimeSort = 'newest';

        function updateOvertimePagination() {
            var searchVal = $('#overtime_search').val() ? $('#overtime_search').val().toLowerCase().trim() : '';
            var empId = $('#filter_overtime_employee_id').val();
            var status = $('#filter_overtime_status').val();

            var $visibleRows = $('.overtime-row').filter(function() {
                var $row = $(this);
                var rowEmp = $row.attr('data-employee') || '';
                var rowEmpId = $row.attr('data-employee-id') || '';
                var rowStatus = $row.attr('data-status') || '';

                var matchesSearch = !searchVal || rowEmp.indexOf(searchVal) !== -1;
                var matchesEmp = !empId || rowEmpId === empId;
                var matchesStatus = !status || rowStatus === status;

                return matchesSearch && matchesEmp && matchesStatus;
            });

            // Sort logic
            var rowsArray = $visibleRows.get();
            rowsArray.sort(function(a, b) {
                var keyA, keyB;
                if (currentOvertimeSort === 'newest' || currentOvertimeSort === 'oldest') {
                    keyA = parseInt($(a).attr('data-created-at') || 0);
                    keyB = parseInt($(b).attr('data-created-at') || 0);
                    return currentOvertimeSort === 'newest' ? keyB - keyA : keyA - keyB;
                } else if (currentOvertimeSort === 'duration_high' || currentOvertimeSort === 'duration_low') {
                    keyA = parseFloat($(a).attr('data-duration') || 0);
                    keyB = parseFloat($(b).attr('data-duration') || 0);
                    return currentOvertimeSort === 'duration_high' ? keyB - keyA : keyA - keyB;
                }
                return 0;
            });

            var $tbody = $('#overtimeTableBody');
            $.each(rowsArray, function(index, row) {
                $tbody.append(row);
            });

            var totalItems = $visibleRows.length;
            var totalPages = Math.ceil(totalItems / overtimeItemsPerPage) || 1;

            if (currentOvertimePage > totalPages) {
                currentOvertimePage = totalPages;
            }
            if (currentOvertimePage < 1) {
                currentOvertimePage = 1;
            }

            var startIndex = (currentOvertimePage - 1) * overtimeItemsPerPage;
            var endIndex = Math.min(startIndex + overtimeItemsPerPage, totalItems);

            $('.overtime-row').hide();
            $visibleRows.slice(startIndex, endIndex).show();

            $('#empty_initial_overtime_row').hide();

            if (totalPages > 1) {
                $('#overtime_pagination_container').show();
            } else {
                $('#overtime_pagination_container').hide();
            }

            if (totalItems === 0) {
                $('#no_matching_overtime_row').removeClass('d-none');
            } else {
                $('#no_matching_overtime_row').addClass('d-none');
            }

            $('#overtime_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
            $('#overtime_showing_end').text(endIndex);
            $('#overtime_total_count').text(totalItems);

            var paginationHtml = '';
            paginationHtml += `
                <li class="page-item ${currentOvertimePage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentOvertimePage - 1}" aria-label="Previous">
                        <i class="feather-chevron-left"></i>
                    </a>
                </li>
            `;
            for (var i = 1; i <= totalPages; i++) {
                paginationHtml += `
                    <li class="page-item ${currentOvertimePage === i ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            }
            paginationHtml += `
                <li class="page-item ${currentOvertimePage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentOvertimePage + 1}" aria-label="Next">
                        <i class="feather-chevron-right"></i>
                    </a>
                </li>
            `;
            $('#overtime_pagination_ul').html(paginationHtml);
        }

        $(document).on('click', '#overtime_pagination_ul .page-link', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            if (page && page !== currentOvertimePage) {
                currentOvertimePage = page;
                updateOvertimePagination();
            }
        });

        $('#overtime_search').on('input', function() {
            currentOvertimePage = 1;
            updateOvertimePagination();
        });

        $('#overtimeFilterForm').on('submit', function(e) {
            e.preventDefault();
            currentOvertimePage = 1;
            updateOvertimePagination();
            closeAllFilterDropdowns();
        });

        function setOvertimeSort(value, element) {
            currentOvertimeSort = value;
            if (element) {
                var menu = element.closest('.dropdown-menu');
                if (menu) {
                    menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                        el.classList.remove('active');
                    });
                }
                element.classList.add('active');
            }
            currentOvertimePage = 1;
            updateOvertimePagination();
        }

        function resetOvertimeFilters() {
            $('#overtime_search').val('');
            $('#filter_overtime_employee_id').val('').trigger('change');
            $('#filter_overtime_status').val('').trigger('change');
            currentOvertimeSort = 'newest';
            $('#overtimeFilterForm').find('.dropdown-menu .dropdown-item').removeClass('active').first().addClass('active');
            currentOvertimePage = 1;
            updateOvertimePagination();
            closeAllFilterDropdowns();
        }

        // Handle Shift Approval/Rejection
        function handleShiftDecision(action, requestId) {
            const form = document.getElementById('shiftDecisionForm');
            form.action = `{{ url('hrms/shift-change') }}/${requestId}/update-status`;
            document.getElementById('shiftDecisionAction').value = action === 'approve' ? 'approved' : 'rejected';

            if (action === 'reject') {
                const reason = prompt('Please enter a rejection reason:');
                if (reason === null) return;
                document.getElementById('shiftDecisionReason').value = reason;
            } else {
                document.getElementById('shiftDecisionReason').value = '';
            }

            form.submit();
        }

        // Handle Overtime Approval/Rejection
        var _pendingOvertimeDecisionId = null;

        function handleOvertimeDecision(action, requestId, requestedHours) {
            _pendingOvertimeDecisionId = requestId;
            const form = document.getElementById('overtimeDecisionForm');
            form.action = `{{ url('hrms/overtime') }}/${requestId}/update-status`;

            if (action === 'approve') {
                document.getElementById('overtimeTabApproveHoursInput').value = requestedHours;
                var modal = new bootstrap.Modal(document.getElementById('approveOvertimeTabModal'));
                modal.show();
            } else if (action === 'reject') {
                document.getElementById('overtimeTabRejectReasonInput').value = '';
                var modal = new bootstrap.Modal(document.getElementById('rejectOvertimeTabModal'));
                modal.show();
            } else if (action === 'pending') {
                document.getElementById('overtimeDecisionAction').value = 'pending';
                document.getElementById('overtimeDecisionReason').value = '';
                document.getElementById('overtimeDecisionApprovedHours').value = '';
                form.submit();
            }
        }

        document.getElementById('confirmOvertimeTabApproveBtn').addEventListener('click', function () {
            const hoursVal = parseFloat(document.getElementById('overtimeTabApproveHoursInput').value);
            if (isNaN(hoursVal) || hoursVal <= 0) {
                alert('Please enter a valid positive number for hours.');
                return;
            }
            const form = document.getElementById('overtimeDecisionForm');
            form.action = `{{ url('hrms/overtime') }}/${_pendingOvertimeDecisionId}/update-status`;
            document.getElementById('overtimeDecisionAction').value = 'approved';
            document.getElementById('overtimeDecisionApprovedHours').value = hoursVal;
            document.getElementById('overtimeDecisionReason').value = '';
            bootstrap.Modal.getInstance(document.getElementById('approveOvertimeTabModal')).hide();
            form.submit();
        });

        document.getElementById('confirmOvertimeTabRejectBtn').addEventListener('click', function () {
            const reason = document.getElementById('overtimeTabRejectReasonInput').value.trim();
            const form = document.getElementById('overtimeDecisionForm');
            form.action = `{{ url('hrms/overtime') }}/${_pendingOvertimeDecisionId}/update-status`;
            document.getElementById('overtimeDecisionAction').value = 'rejected';
            document.getElementById('overtimeDecisionReason').value = reason;
            document.getElementById('overtimeDecisionApprovedHours').value = '';
            bootstrap.Modal.getInstance(document.getElementById('rejectOvertimeTabModal')).hide();
            form.submit();
        });

        // Maintain active tab in query parameters upon tab click and toggle button views
        const tabElList = [].slice.call(document.querySelectorAll('#shiftOvertimeTabs button[data-bs-toggle="tab"]'));
        tabElList.forEach(function (tabEl) {
            tabEl.addEventListener('shown.bs.tab', function (event) {
                const targetId = event.target.getAttribute('data-bs-target');
                const tabName = targetId.replace('#', '').replace('-pane', '');
                
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', url.toString());

                // Sync the buttons visible in the page header
                syncPageActionButtons(tabName);
            });
        });
    </script>
@endpush
