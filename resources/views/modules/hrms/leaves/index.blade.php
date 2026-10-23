@extends('layouts.duralux')

@section('title', __('hrms.leave.app.title') . ' | SaaS ERP')
@section('page-title', __('hrms.leave.app.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.leave.app.title'))

@php
    $currentTab = request()->query('tab', 'applications');
    $isEncashActive = ($currentTab === 'encashments' || request()->query('action') === 'encash');
@endphp

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ $isEncashActive ? route('hrms.leaves.encashment.export') : route('hrms.leaves.export') }}"
                     id="btnExportHeader"
                     variant="light"
                     icon="feather-download"
                     class="border fw-bold text-uppercase"
                     style="height: 38px; color: #475569; border-color: #cbd5e1 !important;">
            {{ __('hrms.common.export_excel') }}
        </x-ui.button>
        <x-ui.button type="button"
                     id="btnApplyEncashmentHeader"
                     variant="primary"
                     icon="feather-dollar-sign"
                     class="fw-bold text-uppercase {{ $isEncashActive ? '' : 'd-none' }}"
                     data-bs-toggle="modal"
                     data-bs-target="#applyEncashmentModal"
                     style="height: 38px;">
            {{ __('hrms.leave.encashment_app.apply_encashment') }}
        </x-ui.button>
        <x-ui.button type="button"
                     id="btnApplyLeaveHeader"
                     variant="primary"
                     icon="feather-plus"
                     class="fw-bold text-uppercase {{ $isEncashActive ? 'd-none' : '' }}"
                     data-bs-toggle="modal"
                     data-bs-target="#applyLeaveModal"
                     style="height: 38px;">
            {{ __('hrms.leave.app.apply_leave') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <style>
        /* Action Status Dropdown Styling (matching Shift & Overtime module) */
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
    </style>
@endpush

@php
    $formatLeaveRulePoints = static function (?array $rules): array {
        if (empty($rules)) {
            return [];
        }

        $humanize = static function ($value) {
            $v = (string) $value;
            if (\Illuminate\Support\Facades\Lang::has('hrms.leave.' . $v)) {
                return __('hrms.leave.' . $v);
            }
            return strtolower(str_replace('_', ' ', $v));
        };
        $formatNumber = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        $sections = [];

        if (!empty($rules['application'])) {
            $application = $rules['application'];
            $points = [];

            if (!empty($application['apply_in_advance'])) {
                $points[] = __('hrms.leave.app.rule_apply_advance', ['days' => $application['advance_days'] ?? 0]);
            } else {
                $points[] = __('hrms.leave.app.rule_no_advance');
            }

            $points[] = __('hrms.leave.app.rule_request_range', ['min' => $application['min_duration'] ?? 1, 'max' => $application['max_duration'] ?? 10]);

            if (!empty($application['require_attachment'])) {
                $points[] = __('hrms.leave.app.rule_attach_required', ['days' => $application['attachment_days'] ?? 0]);
            }

            if (!empty($application['sandwich_rule'])) {
                $points[] = __('hrms.leave.app.rule_sandwich_active');
            }

            $sections[] = ['title' => __('hrms.leave.app.rule_sec_application'), 'icon' => 'feather-file-text', 'points' => $points];
        }

        if (!empty($rules['approval'])) {
            $approval = $rules['approval'];
            $points = [];

            if (($approval['workflow_level'] ?? null) === 'auto') {
                $points[] = __('hrms.leave.app.rule_auto_approved');
            } elseif (($approval['workflow_level'] ?? null) === '2_level') {
                $points[] = __('hrms.leave.app.rule_two_level', [
                    'first' => $humanize($approval['first_approver'] ?? 'reporting_manager'),
                    'second' => $humanize($approval['second_approver'] ?? 'hr_manager')
                ]);
            } else {
                $points[] = __('hrms.leave.app.rule_one_level', [
                    'approver' => $humanize($approval['first_approver'] ?? 'reporting_manager')
                ]);
            }

            $sections[] = ['title' => __('hrms.leave.app.rule_sec_approval'), 'icon' => 'feather-check-square', 'points' => $points];
        }

        if (!empty($rules['accrual'])) {
            $accrual = $rules['accrual'];
            $points = [];
            $unit = ($accrual['calculate_in'] ?? 'days') === 'hours' ? __('hrms.leave.app.hours') : __('hrms.leave.days');

            if (($accrual['quota_type'] ?? 'fixed') === 'unlimited') {
                $points[] = __('hrms.leave.app.rule_unlimited_quota');
            } else {
                $points[] = __('hrms.leave.app.rule_quota_value', [
                    'quota' => $formatNumber($accrual['quota_value'] ?? 0),
                    'unit' => $unit
                ]);
            }

            $rate = $accrual['rate'] ?? 'immediate';
            if ($rate === 'attendance') {
                $points[] = __('hrms.leave.app.rule_attendance_earn', [
                    'earn' => $accrual['attendance_earn'] ?? 1,
                    'period' => $accrual['attendance_period'] ?? 20
                ]);
            } elseif ($rate === 'periodic') {
                $points[] = __('hrms.leave.app.rule_periodic_credit');
            } else {
                $points[] = __('hrms.leave.app.rule_immediate_credit');
            }

            if (!empty($accrual['limit_carry'])) {
                $points[] = __('hrms.leave.app.rule_max_accum', ['days' => $accrual['max_accum'] ?? 0]);
            }

            if (!empty($accrual['allow_negative'])) {
                $maxNeg = $formatNumber($accrual['max_negative'] ?? 0);
                $points[] = __('hrms.leave.app.rule_negative_allowed', ['days' => $maxNeg]);
            }

            $sections[] = ['title' => __('hrms.leave.app.rule_sec_accrual'), 'icon' => 'feather-calendar', 'points' => $points];
        }

        if (!empty($rules['yearend'])) {
            $yearend = $rules['yearend'];
            $points = [];

            if (($yearend['action'] ?? 'lapse') === 'carry_forward') {
                $points[] = __('hrms.leave.app.rule_carry_forward');
                $points[] = __('hrms.leave.app.rule_max_carry', ['days' => $yearend['max_carry'] ?? 0]);
            } elseif (($yearend['action'] ?? null) === 'encash') {
                $points[] = __('hrms.leave.app.rule_encash_allowed');
                $points[] = __('hrms.leave.app.rule_max_encash', ['days' => $yearend['max_encash'] ?? 0]);
            } else {
                $points[] = __('hrms.leave.app.rule_lapse');
            }

            $sections[] = ['title' => __('hrms.leave.app.rule_sec_yearend'), 'icon' => 'feather-refresh-cw', 'points' => $points];
        }

        if (!empty($rules['probation'])) {
            $probation = $rules['probation'];
            $point = match ($probation['rule'] ?? 'allow') {
                'disallow' => __('hrms.leave.app.rule_probation_disallow'),
                'allow_after_months' => __('hrms.leave.app.rule_probation_after', ['months' => $probation['months'] ?? 0]),
                default => __('hrms.leave.app.rule_probation_allow'),
            };
            $sections[] = ['title' => __('hrms.leave.app.rule_sec_probation'), 'icon' => 'feather-shield', 'points' => [$point]];
        }

        if (!empty($rules['notice'])) {
            $notice = $rules['notice'];
            $point = match ($notice['rule'] ?? 'allow') {
                'disallow' => __('hrms.leave.app.rule_notice_disallow'),
                'special_approval' => __('hrms.leave.app.rule_notice_special'),
                default => __('hrms.leave.app.rule_notice_allow'),
            };
            $sections[] = ['title' => __('hrms.leave.app.rule_sec_notice'), 'icon' => 'feather-alert-triangle', 'points' => [$point]];
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
        .leave-balances-container {
            margin-bottom: 1.5rem;
        }
        .leave-balances-track {
            display: flex;
            gap: 16px;
            padding-bottom: 6px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
            flex-wrap: nowrap;
            overflow-x: auto;
            scroll-behavior: smooth;
        }
        .leave-balances-track::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        .leave-balances-track .leave-balance-card-item {
            flex: 0 0 calc(25% - 12px);
            min-width: 250px;
        }
        @media (max-width: 1200px) {
            .leave-balances-track .leave-balance-card-item {
                flex: 0 0 calc(33.333% - 11px);
            }
        }
        @media (max-width: 992px) {
            .leave-balances-track .leave-balance-card-item {
                flex: 0 0 calc(50% - 8px);
            }
        }
        @media (max-width: 576px) {
            .leave-balances-track .leave-balance-card-item {
                flex: 0 0 85%;
            }
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
            $('#applyLeaveModal form').attr('enctype', 'multipart/form-data');
            $('#leaveCancellationModal form').attr('id', 'leaveCancellationForm');
            $('#rejectLeaveModal form').attr('id', 'rejectLeaveForm');
            $('#leavesTableBody').closest('table').attr('id', 'leavesTable');
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

            function syncEncashmentExportUrl() {
                var params = new URLSearchParams();
                var search = $('#encashments_search').val() || '';
                var employeeId = $('#filter_encashment_employee_id').val() || '';
                var status = $('#filter_encashment_status').val() || '';
                if (search) params.set('search', search);
                if (employeeId) params.set('employee_id', employeeId);
                if (status) params.set('status', status);
                $('#btnExportHeader').attr('href', encashmentExportUrl + (params.toString() ? '?' + params.toString() : ''));
            }
            $(document).on('input change', '#encashments_search, #filter_encashment_employee_id, #filter_encashment_status', syncEncashmentExportUrl);

            $('button[data-bs-toggle="tab"]').on('shown.bs.tab click', function (e) {
                var target = $(this).attr('data-bs-target') || $(this).attr('id');
                var isEncashment = target === '#pane-leave-encashments' || target === 'tab-encashments' || target === 'pane-leave-encashments-tab' || target === 'pane-leave-encashments';

                if (isEncashment) {
                    $('#btnApplyLeaveHeader').addClass('d-none');
                    $('#btnApplyEncashmentHeader').removeClass('d-none');
                    syncEncashmentExportUrl();
                } else {
                    $('#btnApplyEncashmentHeader').addClass('d-none');
                    $('#btnApplyLeaveHeader').removeClass('d-none');
                    $('#btnExportHeader').attr('href', leavesExportUrl);
                }

                var tabName = isEncashment ? 'encashments' : 'applications';
                var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tabName;
                window.history.pushState({ path: newUrl }, '', newUrl);
            });

            var urlParams = new URLSearchParams(window.location.search);
            var activeTabParam = urlParams.get('tab');
            var actionParam = urlParams.get('action');

            if (activeTabParam === 'encashments' || actionParam === 'encash') {
                var encashTab = document.querySelector('#pane-leave-encashments-tab') || document.querySelector('#tab-encashments');
                if (encashTab) {
                    var tabObj = new bootstrap.Tab(encashTab);
                    tabObj.show();
                    $('#btnApplyLeaveHeader').addClass('d-none');
                    $('#btnApplyEncashmentHeader').removeClass('d-none');
                    syncEncashmentExportUrl();
                }
            } else {
                $('#btnApplyEncashmentHeader').addClass('d-none');
                $('#btnApplyLeaveHeader').removeClass('d-none');
                $('#btnExportHeader').attr('href', leavesExportUrl);
            }

            if (actionParam === 'apply') {
                setTimeout(function() {
                    var applyModalEl = document.getElementById('applyLeaveModal');
                    if (applyModalEl) {
                        var applyModal = new bootstrap.Modal(applyModalEl);
                        applyModal.show();
                    }
                }, 150);
            } else if (actionParam === 'encash') {
                setTimeout(function() {
                    var encashModalEl = document.getElementById('applyEncashmentModal');
                    if (encashModalEl) {
                        var encashModal = new bootstrap.Modal(encashModalEl);
                        encashModal.show();
                    }
                }, 150);
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

            // Leave Balances Horizontal Smooth Scroll with Mouse Wheel / Trackpad
            var balancesTrackEl = document.getElementById('leaveBalancesTrack');
            if (balancesTrackEl) {
                balancesTrackEl.addEventListener('wheel', function(e) {
                    if (e.deltaY !== 0 && balancesTrackEl.scrollWidth > balancesTrackEl.clientWidth) {
                        e.preventDefault();
                        balancesTrackEl.scrollLeft += e.deltaY;
                    }
                }, { passive: false });
            }

            // Dynamic Employee On-Behalf selection change handler
            $('#employee_select').on('change', function() {
                var empId = $(this).val();
                var $leaveTypeSelect = $('#leave_type_select');
                
                $leaveTypeSelect.empty().append('<option value="">{{ __('hrms.leave.app.select_leave_type') }}</option>');

                if (empId && employeeDataMap[empId]) {
                    var types = employeeDataMap[empId];
                    types.forEach(function(t) {
                        var isHourlyType = t.calculate_in === 'hours' || (t.rules && t.rules.accrual && t.rules.accrual.calculate_in === 'hours');
                        var unitStr = isHourlyType ? '{{ __('hrms.leave.app.hours') }}' : '{{ __('hrms.leave.days') }}';
                        var text = t.name + ' (' + '{{ __('hrms.leave.app.remaining') }}' + ': ' + t.remaining + ' / ' + t.quota + ' ' + unitStr + ')';
                        var option = $('<option>', {
                            value: t.id,
                            text: text
                        });
                        option.attr('data-rules', JSON.stringify(t.rules));
                        option.attr('data-type', t.type);
                        option.attr('data-calculate-in', isHourlyType ? 'hours' : 'days');
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
                        var isHourlyType = t.calculate_in === 'hours' || (t.rules && t.rules.accrual && t.rules.accrual.calculate_in === 'hours');
                        var unitStr = isHourlyType ? '{{ __('hrms.leave.app.hours') }}' : '{{ __('hrms.leave.days') }}';
                        var text = t.name + ' (' + '{{ __('hrms.leave.app.remaining') }}' + ': ' + t.remaining + ' / ' + t.quota + ' ' + unitStr + ')';
                        var option = $('<option>', {
                            value: t.id,
                            text: text
                        });
                        option.attr('data-rules', JSON.stringify(t.rules));
                        option.attr('data-type', t.type);
                        option.attr('data-calculate-in', isHourlyType ? 'hours' : 'days');
                        $leaveTypeSelect.append(option);
                    });
                    $leaveTypeSelect.trigger('change');
                }
            }

            // Dynamic Leave Type Selection & Hourly UI logic
            $('#leave_type_select').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var rulesStr = selectedOption.attr('data-rules');
                
                if (!rulesStr) {
                    $('#hourly_leave_container').addClass('d-none');
                    $('#start_date_col').removeClass('col-md-12').addClass('col-md-6');
                    $('#start_session_col').removeClass('d-none');
                    $('#end_date_row').removeClass('d-none');
                    $('#start_date_col .odoo-form-label').text("{{ __('hrms.leave.app.start_date') }}");
                    calculateExpectedDuration();
                    return;
                }

                try {
                    var rules = JSON.parse(rulesStr);
                    var appRules = rules.application || {};
                    var accrualRules = rules.accrual || {};
                    var isHourly = (accrualRules.calculate_in === 'hours') || (selectedOption.attr('data-calculate-in') === 'hours');

                    // Toggle Hourly vs Standard Session Mode
                    if (isHourly) {
                        $('#hourly_leave_container').removeClass('d-none');
                        $('#start_date_col').removeClass('col-md-6').addClass('col-md-12');
                        $('#start_session_col').addClass('d-none');
                        $('#end_date_row').addClass('d-none');
                        $('#start_date_col .odoo-form-label').text("{{ __('hrms.leave.app.date') }}");
                        $('#end_date').prop('required', false);
                        if ($('#start_date').val()) {
                            $('#end_date').val($('#start_date').val());
                        }
                    } else {
                        $('#hourly_leave_container').addClass('d-none');
                        $('#start_date_col').removeClass('col-md-12').addClass('col-md-6');
                        $('#start_session_col').removeClass('d-none');
                        $('#end_date_row').removeClass('d-none');
                        $('#start_date_col .odoo-form-label').text("{{ __('hrms.leave.app.start_date') }}");
                        $('#end_date').prop('required', true);
                    }

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
                    } else {
                        $('#start_date').removeAttr('min');
                        $('#end_date').removeAttr('min');
                    }

                    // Re-calculate expected duration immediately
                    calculateExpectedDuration();

                } catch (e) {
                    console.error("Error parsing leave rules", e);
                }
            });

            // Block form submission if dynamic rule requirements are violated
            $('#applyLeaveModal form').on('submit', function(e) {
                var selectedOption = $('#leave_type_select').find('option:selected');
                var rulesStr = selectedOption.attr('data-rules');
                if (!rulesStr) return;

                try {
                    var rules = JSON.parse(rulesStr);
                    var appRules = rules.application || {};
                    var isHourly = rules.accrual && rules.accrual.calculate_in === 'hours';
                    var duration = lastCalculatedDuration || 0;

                    if (isHourly) {
                        if (!$('#end_date').val() && $('#start_date').val()) {
                            $('#end_date').val($('#start_date').val());
                        }
                        var sTime = $('#leave_start_time').val();
                        var eTime = $('#leave_end_time').val();
                        if (sTime && eTime) {
                            var sParts = sTime.split(':');
                            var eParts = eTime.split(':');
                            var sMins = parseInt(sParts[0], 10) * 60 + parseInt(sParts[1], 10);
                            var eMins = parseInt(eParts[0], 10) * 60 + parseInt(eParts[1], 10);
                            if (eMins <= sMins) {
                                e.preventDefault();
                                alert("{{ __('hrms.leave.app.time_validation_error') }}");
                                $('#leave_end_time').focus();
                                return false;
                            }
                        }

                        if (duration < 0.5) {
                            e.preventDefault();
                            alert("{{ __('hrms.leave.app.min_hourly_error') }}");
                            return false;
                        }
                        var minDur = parseFloat(appRules.min_duration || 0);
                        var maxDur = parseFloat(appRules.max_duration || 0);
                        if (minDur > 0 && duration < minDur) {
                            e.preventDefault();
                            var minHourlyAlert = "{{ __('hrms.leave.app.duration_min_error', ['duration' => '__dur__', 'min' => '__min__', 'unit' => '__unit__']) }}";
                            alert(minHourlyAlert.replace('__dur__', duration).replace('__min__', minDur).replace('__unit__', "{{ __('hrms.leave.app.hours') }}"));
                            return false;
                        }
                        if (maxDur > 0 && duration > maxDur) {
                            e.preventDefault();
                            var maxHourlyAlert = "{{ __('hrms.leave.app.duration_max_error', ['duration' => '__dur__', 'max' => '__max__', 'unit' => '__unit__']) }}";
                            alert(maxHourlyAlert.replace('__dur__', duration).replace('__max__', maxDur).replace('__unit__', "{{ __('hrms.leave.app.hours') }}"));
                            return false;
                        }
                        return true;
                    }

                    if (duration < 0.5) {
                        e.preventDefault();
                        alert("{{ __('hrms.leave.app.duration_zero_error') }}");
                        return false;
                    }

                    var minDur = parseFloat(appRules.min_duration || 0);
                    var maxDur = parseFloat(appRules.max_duration || 0);
                    if (minDur > 0 && duration < minDur) {
                        e.preventDefault();
                        var minDayAlert = "{{ __('hrms.leave.app.duration_min_error', ['duration' => '__dur__', 'min' => '__min__', 'unit' => '__unit__']) }}";
                        alert(minDayAlert.replace('__dur__', duration).replace('__min__', minDur).replace('__unit__', "{{ __('hrms.leave.days') }}"));
                        return false;
                    }
                    if (maxDur > 0 && duration > maxDur) {
                        e.preventDefault();
                        var maxDayAlert = "{{ __('hrms.leave.app.duration_max_error', ['duration' => '__dur__', 'max' => '__max__', 'unit' => '__unit__']) }}";
                        alert(maxDayAlert.replace('__dur__', duration).replace('__max__', maxDur).replace('__unit__', "{{ __('hrms.leave.days') }}"));
                        return false;
                    }

                    if (appRules.require_attachment) {
                        var attachmentDays = parseInt(appRules.attachment_days || 3);
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

            // Handle date range and session sync
            $('#start_date, #end_date').on('change', function() {
                var selectedOption = $('#leave_type_select').find('option:selected');
                var rulesStr = selectedOption.attr('data-rules');
                var isHourly = false;
                if (rulesStr) {
                    try {
                        var rules = JSON.parse(rulesStr);
                        isHourly = (rules.accrual && rules.accrual.calculate_in === 'hours') || (selectedOption.attr('data-calculate-in') === 'hours');
                    } catch(e) {}
                }

                var startDateVal = $('#start_date').val();

                if (isHourly) {
                    if (startDateVal) {
                        $('#end_date').val(startDateVal);
                    }
                    calculateExpectedDuration();
                    return;
                }

                var endDateVal = $('#end_date').val();

                if (startDateVal && !endDateVal) {
                    $('#end_date').val(startDateVal);
                    endDateVal = startDateVal;
                }

                if (startDateVal && endDateVal && startDateVal === endDateVal) {
                    $('#end_date_type').val($('#start_date_type').val()).trigger('change.select2');
                }

                calculateExpectedDuration();
            });

            function formatTime12h(timeStr) {
                if (!timeStr) return '';
                var parts = timeStr.split(':');
                var h = parseInt(parts[0], 10);
                var m = parts[1] || '00';
                var ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12;
                h = h ? h : 12;
                return (h < 10 ? '0' + h : h) + ':' + m + ' ' + ampm;
            }

            // Sync hours when Start Time or End Time changes
            $('#leave_start_time, #leave_end_time').on('input change', function() {
                var sTime = $('#leave_start_time').val();
                var eTime = $('#leave_end_time').val();
                if (sTime && eTime) {
                    var sParts = sTime.split(':');
                    var eParts = eTime.split(':');
                    var sMins = parseInt(sParts[0], 10) * 60 + parseInt(sParts[1], 10);
                    var eMins = parseInt(eParts[0], 10) * 60 + parseInt(eParts[1], 10);
                    if (eMins > sMins) {
                        var diffHours = (eMins - sMins) / 60;
                        var roundedHours = Math.round(diffHours * 2) / 2;
                        if (roundedHours >= 0.5) {
                            $('#leave_hours').val(roundedHours);
                        }
                    } else if (this.id === 'leave_start_time') {
                        var curHours = parseFloat($('#leave_hours').val()) || 1.0;
                        var newEMins = sMins + Math.round(curHours * 60);
                        if (newEMins < 24 * 60) {
                            var endH = String(Math.floor(newEMins / 60)).padStart(2, '0');
                            var endM = String(newEMins % 60).padStart(2, '0');
                            $('#leave_end_time').val(endH + ':' + endM);
                        }
                    }
                }
                calculateExpectedDuration();
            });

            // When user adjusts #leave_hours directly, auto-advance end_time if start_time is set
            $('#leave_hours').on('input change', function() {
                var hours = parseFloat($(this).val()) || 0;
                var sTime = $('#leave_start_time').val();
                if (hours >= 0.5 && sTime) {
                    var sParts = sTime.split(':');
                    var sMins = parseInt(sParts[0], 10) * 60 + parseInt(sParts[1], 10);
                    var eMins = sMins + Math.round(hours * 60);
                    if (eMins < 24 * 60) {
                        var endH = String(Math.floor(eMins / 60)).padStart(2, '0');
                        var endM = String(eMins % 60).padStart(2, '0');
                        $('#leave_end_time').val(endH + ':' + endM);
                    }
                }
                calculateExpectedDuration();
            });

            $('#start_date_type').on('change', function() {
                var startDateVal = $('#start_date').val();
                var endDateVal = $('#end_date').val();
                if (startDateVal && endDateVal && startDateVal === endDateVal) {
                    $('#end_date_type').val($(this).val()).trigger('change.select2');
                }
                calculateExpectedDuration();
            });

            $('#end_date_type').on('change', function() {
                calculateExpectedDuration();
            });

            // Dynamic Encashment Employee selection change handler
            $(document).on('change', '#encashment_employee_select, #encashment_employee_id', function() {
                if (typeof updateEncashmentLeaveTypes === 'function') {
                    updateEncashmentLeaveTypes($(this).val());
                }
            });

            function getSelectedEmployeeId() {
                var select = document.getElementById('employee_select');
                if (select && select.value) {
                    return select.value;
                }
                return "{{ (isset($employee) && $employee) ? $employee->id : '' }}";
            }

            var lastCalculatedDuration = 0;
            var durationXhr = null;

            function calculateExpectedDuration() {
                var selectedOption = $('#leave_type_select').find('option:selected');
                var rulesStr = selectedOption.attr('data-rules');
                var isHourly = false;
                if (rulesStr) {
                    try {
                        var rules = JSON.parse(rulesStr);
                        isHourly = (rules.accrual && rules.accrual.calculate_in === 'hours') || selectedOption.attr('data-calculate-in') === 'hours';
                    } catch(e) {}
                }

                var startDateStr = $('#start_date').val();
                var empId = getSelectedEmployeeId();

                if (isHourly) {
                    var hoursInput = parseFloat($('#leave_hours').val());
                    var hoursVal = (!isNaN(hoursInput) && hoursInput > 0) ? Math.round(hoursInput * 2) / 2 : 1.0;

                    var sTime = $('#leave_start_time').val();
                    var eTime = $('#leave_end_time').val();

                    if ((isNaN(hoursInput) || hoursInput <= 0) && sTime && eTime) {
                        var sParts = sTime.split(':');
                        var eParts = eTime.split(':');
                        var sMins = parseInt(sParts[0], 10) * 60 + parseInt(sParts[1], 10);
                        var eMins = parseInt(eParts[0], 10) * 60 + parseInt(eParts[1], 10);
                        if (eMins > sMins) {
                            hoursVal = Math.round(((eMins - sMins) / 60) * 2) / 2;
                            $('#leave_hours').val(hoursVal);
                        }
                    }

                    hoursVal = Math.max(0.5, hoursVal);
                    lastCalculatedDuration = hoursVal;

                    if (startDateStr) {
                        $('#end_date').val(startDateStr);
                    }

                    var hasTimeError = false;
                    if (sTime && eTime) {
                        var sParts = sTime.split(':');
                        var eParts = eTime.split(':');
                        var sMins = parseInt(sParts[0], 10) * 60 + parseInt(sParts[1], 10);
                        var eMins = parseInt(eParts[0], 10) * 60 + parseInt(eParts[1], 10);
                        if (eMins <= sMins) {
                            hasTimeError = true;
                        }
                    }

                    var timeRangeStr = (sTime && eTime) ? ' <span class="badge bg-soft-primary text-primary ms-1 fs-11" style="padding: 4px 8px;"><i class="feather-clock me-1"></i>' + formatTime12h(sTime) + ' – ' + formatTime12h(eTime) + '</span>' : '';

                    var estTpl = "{{ __('hrms.leave.app.estimated_duration_simple', ['duration' => '__duration__']) }}";
                    var hoursLabel = "{{ __('hrms.leave.app.hours') }}";
                    var displayText = estTpl.replace('__duration__', '<strong>' + hoursVal + '</strong>')
                                             .replace('day(s)', hoursLabel)
                                             .replace('Day(s)', hoursLabel)
                                             .replace('दिन', hoursLabel)
                                             .replace('дни', hoursLabel);
                    if (displayText.indexOf(hoursLabel) === -1) {
                        displayText = estTpl.replace('__duration__', '<strong>' + hoursVal + '</strong> ' + hoursLabel);
                    }
                    displayText += timeRangeStr;

                    if (hasTimeError) {
                        displayText += ' <div class="text-danger mt-1 fs-11 fw-bold"><i class="feather-alert-triangle me-1"></i>{{ __("hrms.leave.app.time_validation_error") }}</div>';
                    }

                    if (rulesStr) {
                        try {
                            var parsedRules = JSON.parse(rulesStr);
                            var appRules = parsedRules.application || {};
                            var minDur = parseFloat(appRules.min_duration || 0);
                            var maxDur = parseFloat(appRules.max_duration || 0);
                            if (minDur > 0 && hoursVal < minDur) {
                                var minErrMsg = "{{ __('hrms.leave.app.duration_min_error', ['duration' => '__dur__', 'min' => '__min__', 'unit' => '__unit__']) }}"
                                    .replace('__dur__', hoursVal)
                                    .replace('__min__', minDur)
                                    .replace(/__unit__/g, "{{ __('hrms.leave.app.hours') }}");
                                displayText += ' <div class="text-danger mt-1 fs-11 fw-bold"><i class="feather-alert-triangle me-1"></i>' + minErrMsg + '</div>';
                            }
                            if (maxDur > 0 && hoursVal > maxDur) {
                                var maxErrMsg = "{{ __('hrms.leave.app.duration_max_error', ['duration' => '__dur__', 'max' => '__max__', 'unit' => '__unit__']) }}"
                                    .replace('__dur__', hoursVal)
                                    .replace('__max__', maxDur)
                                    .replace(/__unit__/g, "{{ __('hrms.leave.app.hours') }}");
                                displayText += ' <div class="text-danger mt-1 fs-11 fw-bold"><i class="feather-alert-triangle me-1"></i>' + maxErrMsg + '</div>';
                            }
                        } catch (e) {}
                    }

                    $('#calculated_duration_display').html(displayText);
                    $('#applyLeaveModal form button[type="submit"]').prop('disabled', hasTimeError);
                    return hoursVal;
                }

                var endDateStr = $('#end_date').val();
                var startType = $('#start_date_type').val() || 'full_day';
                var endType = $('#end_date_type').val() || 'full_day';

                if (!startDateStr || !endDateStr || !empId) {
                    var estZeroTpl = "{{ __('hrms.leave.app.estimated_duration_simple', ['duration' => 0]) }}";
                    $('#calculated_duration_display').html(estZeroTpl);
                    return 0;
                }

                var startParts = startDateStr.split('-');
                var endParts = endDateStr.split('-');
                var start = new Date(parseInt(startParts[0], 10), parseInt(startParts[1], 10) - 1, parseInt(startParts[2], 10));
                var end = new Date(parseInt(endParts[0], 10), parseInt(endParts[1], 10) - 1, parseInt(endParts[2], 10));

                if (end < start) {
                    $('#calculated_duration_display').text("{{ __('hrms.leave.app.date_validation_error') }}");
                    return 0;
                }

                // Show calculation indicator and disable submit
                $('#calculated_duration_display').html('<span class="spinner-border spinner-border-sm text-secondary me-2" role="status"></span>{{ __("hrms.leave.app.calculating_duration") }}');
                $('#applyLeaveModal form button[type="submit"]').prop('disabled', true);

                if (durationXhr) {
                    durationXhr.abort();
                }

                durationXhr = $.ajax({
                    url: '{{ route("hrms.leaves.calculate-duration") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        employee_id: empId,
                        leave_type_id: $('#leave_type_select').val(),
                        start_date: startDateStr,
                        end_date: endDateStr,
                        start_date_type: startType,
                        end_date_type: endType
                    },
                    success: function(response) {
                        if (response.success) {
                            var duration = response.duration;
                            lastCalculatedDuration = duration;

                            var estTpl = "{{ __('hrms.leave.app.estimated_duration_simple', ['duration' => '__duration__']) }}";
                            var displayText = estTpl.replace('__duration__', '<strong>' + duration + '</strong>');

                            if (response.sandwich_rule && response.sandwich_days && response.sandwich_days.length > 0) {
                                displayText += ' <span class="badge bg-soft-warning text-warning ms-1" style="font-size: 10px; padding: 4px 6px;" title="' + response.sandwich_days.join(', ') + '"><i class="feather-layers me-1"></i>' + '{{ __("hrms.leave.sandwich_rule_applied") }}' + ' (' + response.sandwich_days.length + ' {{ __("hrms.leave.days") }})</span>';
                            } else {
                                if (response.holidays && response.holidays.length > 0) {
                                    var holTxt = "{{ __('hrms.leave.app.excludes_holidays', ['count' => '__count__']) }}".replace('__count__', response.holidays.length);
                                    displayText += ' <span class="badge bg-soft-success text-success ms-1" style="font-size: 10px; padding: 4px 6px;" title="' + response.holidays.join(', ') + '"><i class="feather-calendar me-1"></i>' + holTxt + '</span>';
                                }
                                if (response.rest_days && response.rest_days.length > 0) {
                                    var restTxt = "{{ __('hrms.leave.app.excludes_rest_days', ['count' => '__count__']) }}".replace('__count__', response.rest_days.length);
                                    displayText += ' <span class="badge bg-soft-secondary text-secondary ms-1" style="font-size: 10px; padding: 4px 6px;" title="' + response.rest_days.join(', ') + '"><i class="feather-coffee me-1"></i>' + restTxt + '</span>';
                                }
                            }

                            // Real-time min/max duration policy check
                            var selectedOption = $('#leave_type_select').find('option:selected');
                            var rulesStr = selectedOption.attr('data-rules');
                            if (rulesStr) {
                                try {
                                    var parsedRules = JSON.parse(rulesStr);
                                    var appRules = parsedRules.application || {};
                                    var minDur = parseFloat(appRules.min_duration || 0);
                                    var maxDur = parseFloat(appRules.max_duration || 0);
                                    if (minDur > 0 && duration < minDur) {
                                        var minDayErrMsg = "{{ __('hrms.leave.app.duration_min_error', ['duration' => '__dur__', 'min' => '__min__', 'unit' => '__unit__']) }}"
                                            .replace('__dur__', duration)
                                            .replace('__min__', minDur)
                                            .replace(/__unit__/g, "{{ __('hrms.leave.days') }}");
                                        displayText += ' <div class="text-danger mt-1 fs-11 fw-bold"><i class="feather-alert-triangle me-1"></i>' + minDayErrMsg + '</div>';
                                    }
                                    if (maxDur > 0 && duration > maxDur) {
                                        var maxDayErrMsg = "{{ __('hrms.leave.app.duration_max_error', ['duration' => '__dur__', 'max' => '__max__', 'unit' => '__unit__']) }}"
                                            .replace('__dur__', duration)
                                            .replace('__max__', maxDur)
                                            .replace(/__unit__/g, "{{ __('hrms.leave.days') }}");
                                        displayText += ' <div class="text-danger mt-1 fs-11 fw-bold"><i class="feather-alert-triangle me-1"></i>' + maxDayErrMsg + '</div>';
                                    }
                                } catch (e) {}
                            }

                            $('#calculated_duration_display').html(displayText);

                            evaluateAttachmentRules(duration);
                        } else {
                            $('#calculated_duration_display').text("{{ __('hrms.leave.app.error_calculating_duration') }}");
                        }
                    },
                    error: function(xhr, status, error) {
                        if (status !== 'abort') {
                            $('#calculated_duration_display').text("{{ __('hrms.leave.app.error_calculating_duration') }}");
                        }
                    },
                    complete: function() {
                        $('#applyLeaveModal form button[type="submit"]').prop('disabled', false);
                    }
                });

                return lastCalculatedDuration;
            }

            function evaluateAttachmentRules(duration) {
                var selectedOption = $('#leave_type_select').find('option:selected');
                var rulesStr = selectedOption.attr('data-rules');
                if (rulesStr) {
                    try {
                        var rules = JSON.parse(rulesStr);
                        var isHourly = (rules.accrual && rules.accrual.calculate_in === 'hours') || (selectedOption.attr('data-calculate-in') === 'hours');
                        if (isHourly) {
                            $('#attachment_required_warning').addClass('d-none');
                            $('#attachment').prop('required', false);
                            return;
                        }
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

            $(document).on('click', '#btnExportHeader', function() {
                var search = $('#leaves_search').val() || '';
                var empId = $('#filter_employee_id').val() || '';
                var status = $('#filter_status').val() || '';
                var exportUrl = '{{ route("hrms.leaves.export") }}?leaves_search=' + encodeURIComponent(search) +
                                '&leaves_employee_id=' + encodeURIComponent(empId) +
                                '&leaves_status=' + encodeURIComponent(status);
                $(this).attr('href', exportUrl);
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
            $(document).on('click', '.reject-leave-btn', function(e) {
                e.preventDefault();
                var id = $(this).attr('data-id');
                var actionUrl = $(this).attr('data-action-url') || "{{ route('hrms.leaves.reject', ':id') }}".replace(':id', id);
                $('#rejection_reason').val('');
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
                syncEncashmentExportUrl();
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

            $('#applyEncashmentModal').on('show.bs.modal', function() {
                var empId = $('#encashment_employee_select').length
                    ? ($('#encashment_employee_select').val() || "{{ $employee ? $employee->id : '' }}")
                    : ($('#encashment_employee_id').val() || "{{ $employee ? $employee->id : '' }}");
                updateEncashmentLeaveTypes(empId);
            });

            $('#applyLeaveModal').on('show.bs.modal', function() {
                var empId = getSelectedEmployeeId();
                if (empId) {
                    $('#employee_select').val(empId).trigger('change');
                }
                var todayStr = new Date().toISOString().split('T')[0];
                $('#start_date').val(todayStr);
                $('#end_date').val(todayStr);
                $('#leave_hours').val('1.0');
                $('#leave_start_time').val('09:00');
                $('#leave_end_time').val('10:00');
                $('#attachment').val('');
                $('#attachment_required_warning').addClass('d-none');
                $('#applyLeaveModal form textarea[name="reason"]').val('');
                var estZeroTpl = "{{ __('hrms.leave.app.estimated_duration_simple', ['duration' => 0]) }}";
                $('#calculated_duration_display').html(estZeroTpl);
                $('#applyLeaveModal form button[type="submit"]').prop('disabled', false);
            });

            var initialEncashEmpId = $('#encashment_employee_select').length
                ? ($('#encashment_employee_select').val() || "{{ $employee ? $employee->id : '' }}")
                : ($('#encashment_employee_id').val() || "{{ $employee ? $employee->id : '' }}");
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
            var isHourUnit = (d.calculateIn === 'hours' || d.sessionInfo === 'Hourly');
            var unitWord = isHourUnit ? "{{ __('hrms.leave.app.hours') }}" : "{{ __('hrms.leave.days') }}";
            $('#lid-balance-inline').text("{{ __('hrms.leave.app.remaining') }}: " + (d.remaining !== undefined ? d.remaining : '0') + ' / ' + (d.allocated !== undefined ? d.allocated : '0') + ' ' + unitWord);

            // Status badge
            $('#lid-status-badge')
                .attr('class', 'badge rounded-pill px-2 py-1 fs-11 flex-shrink-0 ' + (d.statusCls || ''))
                .html('<i class="' + (d.statusIcon || '') + ' me-1"></i>' + (d.statusLabel || ''));

            // Period
            $('#lid-date-range').text(d.dateRange || '—');
            $('#lid-session-info').text(d.sessionInfo || '');

            // Duration
            var dur = parseFloat(d.duration) || 0;
            var durUnitWord = isHourUnit ? (dur == 1 ? "{{ __('hrms.leave.app.hour') }}" : "{{ __('hrms.leave.app.hours') }}") : (dur == 1 ? "{{ __('hrms.leave.day') }}" : "{{ __('hrms.leave.days') }}");
            $('#lid-duration').text(dur + ' ' + durUnitWord);

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
                $select.append('<option value="approve_cancellation">{{ __("hrms.leave.app.approve_cancellation") }}</option>');
                $select.append('<option value="deny_cancellation">{{ __("hrms.leave.app.deny_cancellation") }}</option>');
                $select.val('approve_cancellation').trigger('change');
            } else {
                $select.append('<option value="approved">{{ __("hrms.leave.app.status_approved") }}</option>');
                $select.append('<option value="rejected">{{ __("hrms.leave.app.status_rejected") }}</option>');
                $select.append('<option value="pending">{{ __("hrms.leave.app.status_pending") }}</option>');
                $select.append('<option value="unauthorized">{{ __("hrms.leave.app.status_unauthorized") }}</option>');
                $select.append('<option value="unpaid">{{ __("hrms.leave.app.status_unpaid") }}</option>');
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

        $(document).on('submit', '#lid-status-form', function(e) {
            var selectedVal = $('#lid-status-select').val();
            if (selectedVal === 'rejected') {
                var reason = $.trim($('#lid-rejection-reason-input').val());
                if (!reason) {
                    e.preventDefault();
                    alert("{{ __('hrms.leave.app.rejection_reason_alert') }}");
                    $('#lid-rejection-reason-input').focus();
                    return false;
                }
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
                btn.textContent = "{{ __('hrms.leave.app.see_more') }}";
            } else {
                textEl.style.display = 'block';
                textEl.style.webkitLineClamp = 'none';
                btn.textContent = "{{ __('hrms.leave.app.see_less') }}";
            }
        }
    </script>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="row">


        <!-- Main Module Navigation Tabs -->
        @php
            $currentTab = request()->query('tab', 'applications');
            $isEncashActive = ($currentTab === 'encashments' || request()->query('action') === 'encash');
            $leavesTabs = [
                [
                    'id' => 'pane-leave-applications',
                    'label' => __('hrms.leave.app.title'),
                    'active' => !$isEncashActive,
                    'icon' => 'feather-file-text'
                ],
                [
                    'id' => 'pane-leave-encashments',
                    'label' => __('hrms.leave.encashment_app.title'),
                    'active' => $isEncashActive,
                    'icon' => 'feather-dollar-sign',
                    'badge' => (isset($leaveEncashments) && $leaveEncashments->where('status', 'pending')->count() > 0) ? $leaveEncashments->where('status', 'pending')->count() : null,
                    'badgeClass' => 'bg-warning text-dark'
                ]
            ];
        @endphp

        <div class="col-12 mb-2">
            <x-ui.horizontal-tabs id="leavesModuleTabs" :tabs="$leavesTabs" />
        </div>

        <div class="col-12">
            <div class="tab-content" id="leavesModuleTabContent">
                <!-- Pane 1: Leave Applications -->
                <div class="tab-pane fade {{ !$isEncashActive ? 'show active' : '' }}" id="pane-leave-applications" role="tabpanel">
                    <div>
                        {{-- Leave Balances Summary Cards with Dynamic Multi-Type Carousel / Grid View --}}
                        @if(isset($balances) && $balances->isNotEmpty())
                            @php
                                $totalBalances = $balances->count();
                            @endphp
                            <div class="leave-balances-container mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fs-12 fw-bold text-uppercase text-muted" style="letter-spacing: .5px;">
                                            <i class="feather-pie-chart me-1 text-primary"></i> {{ __('hrms.leave.balances') }}
                                        </span>
                                        <x-ui.badge variant="primary" soft class="rounded-pill px-2 py-0.5 fs-11">
                                            {{ $totalBalances }} {{ __('hrms.leave.types') }}
                                        </x-ui.badge>
                                    </div>
                                </div>

                                <div class="leave-balances-track" id="leaveBalancesTrack">
                                    @foreach($balances as $bal)
                                        @php
                                            $type = $bal->leaveType;
                                            if (!$type) continue;
                                            $color = $type->color ?: '#3b82f6';
                                            $allocated = floatval($bal->allocated);
                                            $remaining = floatval($bal->remaining);
                                            $used = floatval($bal->used);
                                            $encashed = floatval($bal->encashed ?? 0);
                                            $pct = $allocated > 0 ? min(100, max(0, round(($used / $allocated) * 100))) : 0;
                                            $ruleSections = isset($formatLeaveRulePoints) ? $formatLeaveRulePoints($type->rules) : [];
                                        @endphp
                                        <div class="leave-balance-card-item">
                                            <div class="leave-balance-card p-3 h-100 position-relative">
                                                <div class="d-flex align-items-center justify-content-between mb-2">
                                                    <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                                        <span style="width: 10px; height: 10px; border-radius: 50%; background-color: {{ $color }}; display: inline-block; flex-shrink: 0;"></span>
                                                        <h6 class="fw-bold fs-13 text-dark mb-0 text-truncate" title="{{ $type->name }}">{{ $type->name }}</h6>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-1 flex-shrink-0 ms-2">
                                                        <x-ui.badge variant="{{ strtolower($type->type) === 'paid' ? 'success' : 'secondary' }}" soft class="fs-10 text-uppercase py-1 px-2">
                                                            {{ strtolower($type->type) === 'paid' ? __('hrms.leave.paid') : __('hrms.leave.unpaid') }}
                                                        </x-ui.badge>
                                                        @if(!empty($ruleSections))
                                                            <button type="button" class="leave-rules-icon-btn ms-1" data-bs-toggle="modal" data-bs-target="#leaveRulesModal_{{ $type->id }}" title="{{ __('hrms.leave.view_policy') }}">
                                                                <i class="feather-info fs-13"></i>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>

                                                @php
                                                    $balUnit = ($type->rules['accrual']['calculate_in'] ?? 'days') === 'hours' ? __('hrms.leave.app.hours') : __('hrms.leave.days');
                                                @endphp
                                                <div class="d-flex align-items-baseline gap-1 my-2">
                                                    <span class="fw-bold fs-22 text-dark">{{ $remaining }}</span>
                                                    <span class="fs-12 text-muted">/ {{ $allocated }} {{ $balUnit }}</span>
                                                </div>

                                                <div class="progress mb-2" style="height: 6px; background-color: #f1f5f9; border-radius: 4px;">
                                                    <div class="progress-bar" role="progressbar" style="width: {{ $pct }}%; background-color: {{ $color }}; border-radius: 4px;" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center fs-11 text-muted">
                                                    <span>{{ __('hrms.leave.app.used') }}: <strong class="text-dark">{{ $used }} {{ $balUnit }}</strong></span>
                                                    <span>{{ __('hrms.leave.app.remaining') }}: <strong class="text-primary">{{ $remaining }} {{ $balUnit }}</strong></span>
                                                </div>
                                            </div>
                                        </div>

                                        @if(!empty($ruleSections))
                                            <!-- Modal for Leave Rules for {{ $type->name }} -->
                                            <div class="modal fade" id="leaveRulesModal_{{ $type->id }}" tabindex="-1" aria-labelledby="leaveRulesModalLabel_{{ $type->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom py-3">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span style="width: 12px; height: 12px; border-radius: 50%; background-color: {{ $color }}; display: inline-block;"></span>
                                                                <h5 class="modal-title fw-bold text-dark" id="leaveRulesModalLabel_{{ $type->id }}">
                                                                    {{ $type->name }} – {{ __('hrms.leave.rules') }}
                                                                </h5>
                                                            </div>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="row g-3">
                                                                @foreach($ruleSections as $sec)
                                                                    <div class="col-md-6">
                                                                        <div class="leave-rule-detail-section">
                                                                            <div class="leave-rule-detail-title">
                                                                                <i class="{{ $sec['icon'] }} text-primary"></i>
                                                                                <span>{{ $sec['title'] }}</span>
                                                                            </div>
                                                                            <ul class="leave-rule-points">
                                                                                @foreach($sec['points'] as $pt)
                                                                                    <li class="leave-rule-point">{{ $pt }}</li>
                                                                                @endforeach
                                                                            </ul>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-top py-2">
                                                            <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="fw-bold text-dark mb-0">{{ __('hrms.leave.app.title') }}</h5>
                                <p class="text-muted fs-12 mb-0">{{ __('hrms.leave.app.review_applications_desc') }}</p>
                            </div>
                            
                            <div class="d-flex align-items-center gap-2">
                                <form method="GET" action="javascript:void(0);" class="d-flex align-items-center gap-2 m-0" id="leavesFilterForm">
                                    <input type="hidden" id="leaves_sort_value" value="{{ $leavesSort ?? 'date_desc' }}">
                                    <!-- Registry Style Search Input -->
                                    <div style="min-width: 220px; max-width: 280px;">
                                        <x-ui.input type="search" name="leaves_search" id="leaves_search" :placeholder="__('hrms.leave.app.search_employee')" :value="$leavesSearch ?? ''" class="form-control-sm" />
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
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_employee') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_employee_id">
                                                    <option value="" {{ ($leavesEmployeeId ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.common.all_employees') }}</option>
                                                    @foreach(($allEmployees ?? $employees ?? []) as $emp)
                                                        <option value="{{ $emp->id }}" {{ ($leavesEmployeeId ?? '') == $emp->id ? 'selected' : '' }}>
                                                            {{ $emp->full_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        @endif

                                        <div class="mb-3" style="min-width: 250px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('ui.status') }}</label>
                                            <x-ui.odoo-form-ui type="select" name="status" id="filter_status">
                                                <option value="" {{ ($leavesStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.common.all_statuses') }}</option>
                                                <option value="pending" {{ ($leavesStatus ?? '') === 'pending' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_pending') }}</option>
                                                <option value="approved" {{ ($leavesStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_approved') }}</option>
                                                <option value="rejected" {{ ($leavesStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_rejected') }}</option>
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="dropdown-divider my-3"></div>

                                        <div class="d-flex gap-2">
                                            <x-ui.button type="button" id="btnApplyFilters" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.common.apply') }}</x-ui.button>
                                            <x-ui.button type="button" id="btnResetFilters" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
                                        </div>
                                    </x-ui.filter>
                                </form>
                            </div>
                        </div>
                        
                        <div>
                            <x-ui.table class="p-0">
                                    <thead class="table-light">
                                        <tr>
                                            @if($isAdmin)
                                                <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="width: 18%;">{{ __('hrms.employees.tbl_employee') }}</th>
                                            @endif
                                            <th class="fs-12 text-uppercase text-muted fw-semibold {{ !$isAdmin ? 'ps-3' : '' }}" style="width: 18%;">{{ __('hrms.leave.leave_types') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 16%;">{{ __('hrms.leave.app.duration_timeline') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 8%;">{{ __('hrms.leave.app.duration') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 14%;">{{ __('ui.status') }}</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 8%;">{{ __('hrms.leave.app.file') }}</th>
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

                                                $isHourlyReq  = ($req->start_date_type === 'hourly') || ($req->session === 'hourly') || ($req->leaveType && ($req->leaveType->rules['accrual']['calculate_in'] ?? 'days') === 'hours');
                                                $rowUnit      = $isHourlyReq ? __('hrms.leave.app.hours') : __('hrms.leave.days');

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
                                                    'pending'                 => ['cls' => 'bg-soft-warning text-warning',    'icon' => 'feather-clock',          'lbl' => ((string)$req->current_level === '2') ? __('hrms.leave.app.l1_approved_l2_pending') : __('hrms.leave.app.status_pending')],
                                                    'rejected'                => ['cls' => 'bg-soft-danger text-danger',      'icon' => 'feather-x-circle',       'lbl' => __('hrms.leave.app.status_rejected')],
                                                    'cancellation_requested'  => ['cls' => 'bg-soft-info text-info',          'icon' => 'feather-rotate-ccw',     'lbl' => __('hrms.leave.app.status_cancellation_requested')],
                                                    'cancelled'               => ['cls' => 'bg-soft-secondary text-secondary','icon' => 'feather-slash',          'lbl' => __('hrms.leave.app.status_cancelled')],
                                                    'unauthorized'            => ['cls' => 'bg-soft-secondary text-secondary','icon' => 'feather-slash',          'lbl' => __('hrms.leave.app.status_unauthorized')],
                                                    'unpaid'                  => ['cls' => 'bg-soft-info text-info',          'icon' => 'feather-alert-circle',   'lbl' => __('hrms.leave.app.status_unpaid')],
                                                    default                   => ['cls' => 'bg-light text-secondary',         'icon' => 'feather-circle',         'lbl' => ucfirst($req->status)],
                                                };

                                                $timeRange = '';
                                                if ($isHourlyReq && !empty($req->start_time) && !empty($req->end_time)) {
                                                    try {
                                                        $timeRange = \Carbon\Carbon::parse($req->start_time)->format('h:i A') . ' – ' . \Carbon\Carbon::parse($req->end_time)->format('h:i A');
                                                    } catch (\Throwable $e) {
                                                        $timeRange = $req->start_time . ' – ' . $req->end_time;
                                                    }
                                                }

                                                $sessionLabels = [
                                                    'full_day' => __('hrms.leave.app.full_day'),
                                                    'first_half' => __('hrms.leave.app.first_half'),
                                                    'second_half' => __('hrms.leave.app.second_half'),
                                                    'hourly' => __('hrms.leave.app.hourly'),
                                                ];
                                                $sessionInfo = '';
                                                if ($req->session === 'hourly' || $req->start_date_type === 'hourly') {
                                                    $sessionInfo = $timeRange ?: __('hrms.leave.app.hourly');
                                                } elseif ($req->start_date_type !== 'full_day' || $req->end_date_type !== 'full_day') {
                                                    $startSessionLabel = $sessionLabels[$req->start_date_type] ?? ucwords(str_replace('_', ' ', $req->start_date_type));
                                                    $sessionInfo = $startSessionLabel;
                                                    if (!$req->start_date->isSameDay($req->end_date) && $req->end_date_type !== 'full_day') {
                                                        $endSessionLabel = $sessionLabels[$req->end_date_type] ?? ucwords(str_replace('_', ' ', $req->end_date_type));
                                                        $sessionInfo .= ' → ' . $endSessionLabel;
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
                                                data-calculate-in="{{ $isHourlyReq ? 'hours' : 'days' }}"
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
                                                            <div class="text-muted fs-11">{{ __('hrms.leave.app.remaining') }}: {{ $rowRemaining }} / {{ $rowAllocated }} {{ $rowUnit }}</div>
                                                        </div>
                                                    </div>
                                                    @if(in_array($req->status, ['cancellation_requested', 'cancelled']) && !empty($req->cancellation_reason))
                                                        <div class="text-warning fs-11 mt-2" style="max-width: 250px;">
                                                            <span class="fw-semibold"><i class="feather-rotate-ccw me-1"></i>{{ __('hrms.leave.app.cancellation_prefix') }}</span>
                                                            <div class="leave-cancel-reason-text mb-0 text-muted fs-11" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; white-space: normal; line-height: 1.4; color: inherit;">
                                                                {{ $req->cancellation_reason }}
                                                            </div>
                                                            @if($isLongCancelReason)
                                                                <a href="#" class="leave-toggle-cancel-reason-btn fs-10 text-primary fw-semibold d-inline-block mt-0.5" onclick="toggleLeaveCancelReasonText(this); return false;">{{ __('hrms.leave.app.see_more') }}</a>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="fs-13 text-dark">{{ $dateRange }}</div>
                                                    @if(!empty($timeRange))
                                                        <div class="fs-11 text-muted mt-0.5"><i class="feather-clock me-1 text-primary"></i>{{ $timeRange }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold fs-13 text-dark">{{ floatval($req->duration) }}</span>
                                                    <div class="fs-10 text-muted">{{ $rowUnit }}</div>
                                                </td>
                                                <td>
                                                    <x-ui.badge variant="{{ $req->status === 'approved' ? 'success' : ($req->status === 'pending' || $req->status === 'cancellation_requested' ? 'warning' : ($req->status === 'rejected' ? 'danger' : 'secondary')) }}" soft class="rounded-pill px-2 py-1 fs-11">
                                                        <i class="{{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['lbl'] }}
                                                    </x-ui.badge>
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
                                                         <x-ui.icon-btn type="button"
                                                             variant="soft-primary"
                                                             size="sm"
                                                             icon="feather-eye"
                                                             class="open-leave-detail-idx"
                                                             title="{{ __('hrms.leave.app.view_details') }}"
                                                             data-bs-toggle="offcanvas"
                                                             data-bs-target="#leaveDetailDrawerIdx"
                                                             style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" />

                                                         @if($isAdmin && $req->status !== 'cancelled')
                                                             {{-- Status Dropdown --}}
                                                              <x-ui.action-dropdown align="end" :id="'leave-actions-' . $req->id">
                                                                     @if($req->status === 'cancellation_requested')
                                                                         <li>
                                                                             <form action="{{ route('hrms.leaves.approve-cancellation', $req->id) }}" method="POST">
                                                                                 @csrf
                                                                                 <button type="submit" class="dropdown-item text-success fw-medium">
                                                                                     {{ __('hrms.leave.app.status_approved') }}
                                                                                 </button>
                                                                             </form>
                                                                         </li>
                                                                         <li>
                                                                             <form action="{{ route('hrms.leaves.deny-cancellation', $req->id) }}" method="POST">
                                                                                 @csrf
                                                                                 <button type="submit" class="dropdown-item text-danger fw-medium">
                                                                                     {{ __('hrms.leave.app.status_rejected') }}
                                                                                 </button>
                                                                             </form>
                                                                         </li>
                                                                     @else
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
                                                                                 @if($actionKey === 'rejected')
                                                                                    <button type="button" class="dropdown-item reject-leave-btn text-danger fw-medium {{ $req->status === 'rejected' ? 'active-status' : '' }}" data-id="{{ $req->id }}" data-action-url="{{ route('hrms.leaves.reject', $req->id) }}">
                                                                                        {{ $actionLabel }}
                                                                                    </button>
                                                                                @else
                                                                                <form action="{{ route('hrms.leaves.update-status', $req->id) }}" method="POST">
                                                                                     @csrf
                                                                                     <input type="hidden" name="action" value="{{ $actionKey }}">
                                                                                     <button type="submit" class="dropdown-item {{ $req->status === $actionKey ? 'active-status' : '' }}">
                                                                                         {{ $actionLabel }}
                                                                                     </button>
                                                                                 </form>
                                                                                @endif
                                                                             </li>
                                                                         @endforeach
                                                                     @endif
                                                              </x-ui.action-dropdown>
                                                         @endif

                                                         {{-- Unified Withdraw / Cancellation Delete button --}}
                                                         @if($isAdmin)
                                                             <form method="POST" action="{{ route('hrms.leaves.destroy', $req->id) }}" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.leave.app.confirm_delete_application') }}', { title: '{{ __('hrms.leave.app.delete_application_title') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.leave.app.delete') }}' })" class="d-inline">
                                                                 @csrf
                                                                 @method('DELETE')
                                                              <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="{{ __('hrms.leave.app.delete_application_title') }}" />
                                                             </form>
                                                         @elseif($req->canWithdraw())
                                                             <form method="POST" action="{{ route('hrms.leaves.withdraw', $req->id) }}" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.leave.app.confirm_withdraw') }}', { title: '{{ __('hrms.leave.app.withdraw_title') }}', variant: 'warning', confirmButtonText: '{{ __('hrms.leave.app.withdraw') }}' })" class="d-inline">
                                                                 @csrf
                                                              <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="{{ __('hrms.leave.app.withdraw_title') }}" />
                                                             </form>
                                                         @elseif($req->canRequestCancellation())
                                                              <x-ui.icon-btn type="button" variant="soft-danger" size="sm" icon="feather-trash-2" title="{{ __('hrms.leave.app.request_cancellation') }}" onclick="openLeaveCancellationModal({{ $req->id }}, '{{ route('hrms.leaves.request-cancellation', $req->id) }}')" />
                                                         @else
                                                              <x-ui.icon-btn type="button" variant="light-brand" size="sm" icon="feather-trash-2" disabled />
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
                            </x-ui.table>
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
                        <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.cancellation_reason') }}</div>
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
                                    <x-ui.button type="submit" variant="primary" size="sm" icon="feather-check" class="fw-bold px-3" style="height: 38px; border-radius: 6px;">
                                        {{ __('hrms.common.apply') }}
                                    </x-ui.button>
                                </div>
                                <div class="mt-2 d-none" id="lid-rejection-input-wrap">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-2 mt-2" style="letter-spacing:.5px;">{{ __('hrms.leave.app.rejection_reason') }}</div>
                                    <x-ui.textarea name="rejection_reason" id="lid-rejection-reason-input" rows="2" placeholder="{{ __('hrms.leave.app.rejection_reason_placeholder') }}" />
                                </div>
                            </form>
                        </div>
                    @endif

                    <x-slot:footer>
                        <x-ui.button variant="light-brand" data-bs-dismiss="offcanvas">{{ __('hrms.leave.app.close_panel') }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.drawer>

                <!-- Pane 2: Leave Encashments -->
                <div class="tab-pane fade {{ $isEncashActive ? 'show active' : '' }}" id="pane-leave-encashments" role="tabpanel">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="fw-bold text-dark mb-0">{{ __('hrms.leave.encashment_app.title') }}</h5>
                                <p class="text-muted fs-12 mb-0">{{ __('hrms.leave.app.review_applications_desc') }}</p>
                            </div>
                            
                            <div class="d-flex align-items-center gap-2">
                                <form method="GET" action="javascript:void(0);" class="d-flex align-items-center gap-2 m-0" id="encashmentFilterForm">
                                    <input type="hidden" id="encashments_sort_value" value="{{ $encashmentsSort ?? 'date_desc' }}">
                                    <!-- Registry Style Search Input -->
                                    <div style="min-width: 220px; max-width: 280px;">
                                        <x-ui.input type="search" name="encashments_search" id="encashments_search" :placeholder="__('hrms.leave.app.search_employee')" :value="$encashmentsSearch ?? ''" class="form-control-sm" />
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
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('ui.status') }}</label>
                                            <x-ui.odoo-form-ui type="select" name="status" id="filter_encashment_status">
                                                <option value="" {{ ($encashmentsStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.leave.encashment_app.all_statuses') }}</option>
                                                <option value="pending" {{ ($encashmentsStatus ?? '') === 'pending' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_pending') }}</option>
                                                <option value="approved" {{ ($encashmentsStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_approved') }}</option>
                                                <option value="rejected" {{ ($encashmentsStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.leave.app.status_rejected') }}</option>
                                            </x-ui.odoo-form-ui>
                                        </div>

                                        <div class="dropdown-divider my-3"></div>

                                        <div class="d-flex gap-2">
                                            <x-ui.button type="button" id="btnApplyEncashmentFilters" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.common.apply') }}</x-ui.button>
                                            <x-ui.button type="button" id="btnResetEncashmentFilters" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') }}</x-ui.button>
                                        </div>
                                    </x-ui.filter>
                                </form>
                            </div>
                        </div>

                        <div>
                            <x-ui.table class="p-0">
                                    <thead class="table-light">
                                        <tr>
                                            @if($isAdmin)
                                                <th>{{ __('hrms.leave.encashment_app.employee') }}</th>
                                            @endif
                                            <th>{{ __('hrms.leave.encashment_app.leave_type') }}</th>
                                            <th>{{ __('hrms.leave.encashment_app.requested_days') }}</th>
                                            <th>{{ __('hrms.leave.encashment_app.reason') }}</th>
                                            <th>{{ __('hrms.leave.encashment_app.submitted_date') }}</th>
                                            <th>{{ __('ui.status') }}</th>
                                            @if($canManageEncashments ?? $isAdmin)
                                                <th class="text-end">{{ __('hrms.leave.app.actions') }}</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody id="encashmentsTableBody">
                                        @forelse(($leaveEncashments ?? $encashments ?? []) as $enc)
                                            <tr class="encashment-row"
                                                data-employee="{{ strtolower($enc->employee?->full_name ?? '') }} {{ strtolower($enc->employee?->employee_id ?? '') }}"
                                                data-employee-id="{{ $enc->employee_id }}"
                                                data-status="{{ $enc->status }}"
                                                data-days="{{ floatval($enc->requested_days) }}"
                                                data-created-at="{{ $enc->created_at->timestamp }}">
                                                @if($isAdmin)
                                                    <td>
                                                        <h6 class="fw-bold mb-0 fs-13 text-dark">{{ $enc->employee?->full_name ?? __('hrms.employees.tbl_employee') }}</h6>
                                                        <small class="text-muted fs-11">{{ $enc->employee?->employee_id ?? '—' }}</small>
                                                    </td>
                                                @endif
                                                <td>
                                            <x-ui.badge variant="primary" soft class="fw-semibold fs-12 mb-1">{{ $enc->leaveType?->name ?? __('hrms.leave.encashment_app.select_leave_type') }}</x-ui.badge>
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
                                                        <x-ui.badge variant="warning" soft class="px-2.5 py-1.5 rounded-pill fs-11 fw-semibold"><i class="feather-clock me-1"></i> {{ __('hrms.leave.app.status_pending') }}</x-ui.badge>
                                                    @elseif($enc->status === 'approved')
                                                        <x-ui.badge variant="success" soft class="px-2.5 py-1.5 rounded-pill fs-11 fw-semibold"><i class="feather-check-circle me-1"></i> {{ __('hrms.leave.app.status_approved') }}</x-ui.badge>
                                                    @else
                                                        <x-ui.badge variant="danger" soft class="px-2.5 py-1.5 rounded-pill fs-11 fw-semibold"><i class="feather-x-circle me-1"></i> {{ __('hrms.leave.app.status_rejected') }}</x-ui.badge>
                                                    @endif
                                                </td>
                                                @if($canManageEncashments ?? $isAdmin)
                                                <td class="text-end pe-3" style="white-space: nowrap;">
                                                    @if($enc->status === 'pending')
                                                        <x-ui.action-dropdown align="end" :id="'encashment-actions-' . $enc->id">
                                                            <li><form action="{{ route('hrms.leaves.encashment.approve', $enc->id) }}" method="POST">@csrf<button type="submit" class="dropdown-item">{{ __('hrms.leave.app.status_approved') }}</button></form></li>
                                                            <li><form action="{{ route('hrms.leaves.encashment.reject', $enc->id) }}" method="POST">@csrf<button type="submit" class="dropdown-item text-danger">{{ __('hrms.leave.app.status_rejected') }}</button></form></li>
                                                        </x-ui.action-dropdown>
                                                    @else
                                                        <span class="text-muted fs-12">—</span>
                                                    @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ ($isAdmin ? 6 : 5) + (($canManageEncashments ?? $isAdmin) ? 1 : 0) }}" class="text-center py-5 text-muted fs-13">
                                                    <i class="feather-dollar-sign fs-3 d-block mb-2 text-secondary"></i>
                                                    {{ __('hrms.leave.encashment_app.no_encashments') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                        <tr id="no_matching_encashments_row" class="d-none">
                                            <td colspan="{{ ($isAdmin ? 6 : 5) + (($canManageEncashments ?? $isAdmin) ? 1 : 0) }}" class="text-center py-5 text-muted fs-13">
                                                <i class="feather-folder fs-3 d-block mb-2 text-secondary"></i>
                                                {{ __('hrms.leave.encashment_app.no_matching_encashments') }}
                                            </td>
                                        </tr>
                                    </tbody>
                            </x-ui.table>
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
    </div>

    <!-- Leave Cancellation Request Modal -->
    <x-ui.modal id="leaveCancellationModal" :title="__('hrms.leave.app.request_cancellation')" formAction="#" :showFooter="true" :scrollable="true">
                    <p class="text-muted fs-13 mb-3">{{ __('hrms.leave.app.cancellation_reason_desc') }}</p>
                    <x-ui.odoo-form-ui type="textarea" :label="__('hrms.leave.app.cancellation_reason')" name="cancellation_reason" id="leave_cancellation_reason" :required="true" :placeholder="__('hrms.leave.app.cancellation_placeholder')" rows="3" maxlength="1000" />
                    <x-slot:footer>
                        <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                        <x-ui.button variant="warning" type="submit" icon="feather-send" class="text-dark fw-semibold">{{ __('hrms.leave.app.submit_request') }}</x-ui.button>
                    </x-slot:footer>
    </x-ui.modal>

    <!-- Apply Leave Modal -->
    <x-ui.modal id="applyLeaveModal" :title="__('hrms.leave.app.apply_for_leave')" size="lg" :formAction="route('hrms.leaves.store')" :showFooter="true" :scrollable="true">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($isAdmin)
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.employees.tbl_employee')" name="employee_id" id="employee_select" :required="true" class="odoo-select2-custom">
                                        <option value="">{{ __('hrms.leave.app.select_employee') }}</option>
                                        @foreach (($allEmployees ?? $employees ?? []) as $emp)
                                            <option value="{{ $emp->id }}" {{ ($employee && $employee->id === $emp->id) ? 'selected' : '' }}>
                                                {{ $emp->full_name }} ({{ $emp->employee_id }})
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
                        @else
                            <input type="hidden" name="employee_id" id="employee_select" value="{{ $employee ? $employee->id : '' }}">
                        @endif

                        <div class="row">
                            <div class="col-12 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.leave_types')" name="leave_type_id" id="leave_type_select" :required="true" class="odoo-select2-custom">
                                    <option value="">{{ __('hrms.leave.app.select_leave_type') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="row" id="leave_date_row">
                            <div class="col-md-6 mb-3" id="start_date_col">
                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.leave.app.start_date')" name="start_date" id="start_date" :required="true" class="odoo-underline-input" :value="date('Y-m-d')" />
                            </div>
                            <div class="col-md-6 mb-3" id="start_session_col">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.start_session')" name="start_date_type" id="start_date_type" :required="true" class="odoo-select2-custom">
                                    <option value="full_day">{{ __('hrms.leave.app.full_day') }}</option>
                                    <option value="first_half">{{ __('hrms.leave.app.first_half') }}</option>
                                    <option value="second_half">{{ __('hrms.leave.app.second_half') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        {{-- Hourly / Permission Leave Time & Duration Container --}}
                        <div class="row d-none" id="hourly_leave_container">
                            <div class="col-md-4 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="time" :label="__('hrms.leave.app.from_time')" name="start_time" id="leave_start_time" :required="false" class="odoo-underline-input" value="09:00" />
                            </div>
                            <div class="col-md-4 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="time" :label="__('hrms.leave.app.to_time')" name="end_time" id="leave_end_time" :required="false" class="odoo-underline-input" value="10:00" />
                            </div>
                            <div class="col-md-4 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.leave.app.leave_hours')" name="hours" id="leave_hours" :required="false" class="odoo-underline-input" step="0.5" min="0.5" max="24" value="1.0" placeholder="e.g. 1.0, 1.5, 2.0" />
                            </div>
                        </div>

                        <div class="row" id="end_date_row">
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.leave.app.end_date')" name="end_date" id="end_date" :required="true" class="odoo-underline-input" :value="date('Y-m-d')" />
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
                    <x-slot:footer>
                        <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                        <x-ui.button variant="primary" type="submit">{{ __('hrms.leave.app.submit_request') }}</x-ui.button>
                    </x-slot:footer>
    </x-ui.modal>

    <!-- Apply Encashment Modal -->
    <x-ui.modal id="applyEncashmentModal" :title="__('hrms.leave.encashment_app.apply_for_encashment')" :formAction="route('hrms.leaves.encashment.store')" :showFooter="true" :scrollable="true">
                    <div class="row g-3">
                        @if($isAdmin)
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.employees.tbl_employee')" name="employee_id" id="encashment_employee_select" :required="true" class="odoo-select2-custom">
                                    <option value="">{{ __('hrms.leave.app.select_employee') }}</option>
                                    @foreach (($allEmployees ?? $employees ?? []) as $emp)
                                        <option value="{{ $emp->id }}" {{ ($employee && $employee->id === $emp->id) ? 'selected' : '' }}>
                                            {{ $emp->full_name }} ({{ $emp->employee_id }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        @else
                            <input type="hidden" name="employee_id" id="encashment_employee_id" value="{{ $employee ? $employee->id : '' }}">
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
                    <x-slot:footer>
                        <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                        <x-ui.button variant="primary" type="submit" icon="feather-check">{{ __('hrms.leave.encashment_app.submit_encashment') }}</x-ui.button>
                    </x-slot:footer>
    </x-ui.modal>

    <!-- Reject Leave Modal -->
    <x-ui.modal id="rejectLeaveModal" :title="__('hrms.leave.app.reject_title')" formAction="#" :showFooter="true" :scrollable="true">
        <x-ui.odoo-form-ui type="textarea" :label="__('hrms.leave.app.rejection_reason')" name="rejection_reason" id="rejection_reason" :required="true" :placeholder="__('hrms.leave.app.rejection_reason_placeholder')" rows="4" />
        <x-slot:footer>
            <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button variant="danger" type="submit">{{ __('hrms.leave.app.confirm_rejection') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endsection
