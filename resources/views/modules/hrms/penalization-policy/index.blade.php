@extends('layouts.duralux')

@section('title', 'PENALIZATION POLICY SETTINGS | SaaS ERP')
@section('page-title', 'Penalization Policies')
@section('breadcrumb', 'HRMS / Penalization Policy Settings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <style>
        /* Sidebar and Workspace layout alignment */
        @media (min-width: 992px) {
            .nxl-content {
                padding: 0 !important;
            }
            .page-header {
                padding: 24px 24px 16px 24px !important;
                margin-bottom: 0 !important;
                border-bottom: 1px solid #e5e7eb;
                background-color: #fff;
            }
            .main-content {
                padding: 0 !important;
            }
            .settings-container {
                display: flex;
                min-height: calc(100vh - 120px);
                background-color: #f8fafc;
            }
            .settings-sidebar-col {
                width: 280px;
                min-width: 280px;
                background-color: #fff;
                border-right: 1px solid #e5e7eb;
                display: flex;
                flex-direction: column;
            }
            .settings-content-col {
                flex-grow: 1;
                padding: 24px 30px;
                background-color: #f8fafc;
                min-width: 0;
            }
        }

        @media (max-width: 991.98px) {
            .settings-sidebar-col {
                width: 100%;
                background-color: #fff;
                border-bottom: 1px solid #e5e7eb;
                margin-bottom: 20px;
                padding: 10px;
            }
            .settings-content-col {
                width: 100%;
                padding: 0 15px;
            }
        }

        /* Sidebar pills overrides */
        #settingsSubSidebar .nav-link {
            background-color: transparent;
            transition: all 0.2s ease-in-out;
            border-radius: 6px !important;
            font-size: 14px;
            font-weight: 500;
            color: #475569 !important;
            padding: 12px 16px !important;
            border: 0 !important;
        }
        #settingsSubSidebar .nav-link:hover {
            background-color: #f1f5f9;
            color: var(--bs-primary) !important;
        }
        #settingsSubSidebar .nav-link.active {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
            font-weight: 600;
        }

        /* List items styling */
        .policy-item {
            border-left: 4px solid transparent !important;
            transition: all 0.15s ease-in-out;
            border-bottom: 1px solid #f1f5f9 !important;
            padding: 16px 20px !important;
        }
        .policy-item.active {
            background-color: rgba(74, 108, 247, 0.05) !important;
            border-left-color: var(--bs-primary) !important;
        }
        .policy-item.active span {
            color: var(--bs-primary) !important;
            font-weight: 600 !important;
        }
        .policy-item.active i {
            color: var(--bs-primary) !important;
        }
        .policy-item:hover:not(.active) {
            background-color: #f8fafc !important;
            transform: translateX(2px);
        }

        /* HRMS theme form controls */
        .form-label {
            font-size: 12.5px !important;
            color: #334155 !important;
            margin-bottom: 6px !important;
        }
        .form-label .text-danger {
            display: inline !important;
            white-space: nowrap !important;
        }
        .settings-content-col .form-control,
        .settings-content-col .form-select,
        .settings-content-col .odoo-table-input,
        .settings-content-col .odoo-table-select {
            border: none !important;
            border-bottom: 1px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            font-size: 13px !important;
            color: #334155 !important;
            padding: 6px 2px !important;
            transition: border-bottom-color 0.15s ease-in-out !important;
        }
        .settings-content-col .form-control:hover,
        .settings-content-col .form-select:hover,
        .settings-content-col .odoo-table-input:hover,
        .settings-content-col .odoo-table-select:hover {
            border-bottom-color: #94a3b8 !important;
        }
        .settings-content-col .form-control:focus,
        .settings-content-col .form-select:focus,
        .settings-content-col .odoo-table-input:focus,
        .settings-content-col .odoo-table-select:focus {
            border-bottom-color: var(--bs-primary) !important;
            box-shadow: none !important;
        }
        .form-control-sm, .form-select-sm {
            height: 32px !important;
            font-size: 12px !important;
            padding: 4px 2px !important;
        }
        .input-group-text {
            border: none !important;
            border-bottom: 1px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background: transparent !important;
            font-size: 12.5px !important;
            color: #475569 !important;
        }

        /* Table enhancements */
        .table-responsive {
            border-color: #e2e8f0 !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03) !important;
        }
        .table thead th {
            font-size: 10.5px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            color: #475569 !important;
            background-color: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 10px 14px !important;
        }
        .table tbody td {
            padding: 8px 12px !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        .settings-content-col .select2-container--bootstrap-5 {
            width: 100% !important;
        }
        .settings-content-col .select2-container--bootstrap-5 .select2-selection--single,
        .settings-content-col .select2-container--bootstrap-5 .select2-selection--multiple {
            min-height: 32px !important;
            height: auto !important;
            border: none !important;
            border-bottom: 1px solid #cbd5e1 !important;
            border-radius: 0 !important;
            padding: 4px 2rem 4px 2px !important;
            display: flex !important;
            align-items: center !important;
            background-color: transparent !important;
            box-shadow: none !important;
            transition: border-bottom-color 0.15s ease-in-out !important;
        }
        .settings-content-col .select2-container--bootstrap-5 .select2-selection--single:hover,
        .settings-content-col .select2-container--bootstrap-5 .select2-selection--multiple:hover {
            border-bottom-color: #94a3b8 !important;
        }
        .settings-content-col .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        .settings-content-col .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
            padding: 0 !important;
            color: #334155 !important;
            line-height: 1.4 !important;
        }
        .settings-content-col .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            right: 2px !important;
        }
        .table tbody td .select2-container--bootstrap-5 .select2-selection--single,
        .table tbody td .select2-container--bootstrap-5 .select2-selection--multiple {
            min-height: 30px !important;
            padding: 4px 1.75rem 4px 2px !important;
        }
        .table tbody td .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        .table tbody td .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
            font-size: 12.5px !important;
            line-height: 1.3 !important;
        }
        .table tbody td .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            right: 0 !important;
        }
        .table tbody td .select2-container--bootstrap-5.select2-container--focus .select2-selection--single,
        .table tbody td .select2-container--bootstrap-5.select2-container--focus .select2-selection--multiple,
        .settings-content-col .select2-container--bootstrap-5.select2-container--focus .select2-selection--single,
        .settings-content-col .select2-container--bootstrap-5.select2-container--focus .select2-selection--multiple {
            border-bottom-color: var(--bs-primary) !important;
            box-shadow: none !important;
        }
        .table tbody td input[readonly] {
            background-color: transparent !important;
            border-bottom-color: #e2e8f0 !important;
            color: #64748b !important;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(248, 250, 252, 0.75) !important;
        }
        .btn-soft-danger {
            background-color: #fef2f2 !important;
            color: #ef4444 !important;
            border: 1px solid #fee2e2 !important;
            transition: all 0.15s ease;
        }
        .btn-soft-danger:hover {
            background-color: #ef4444 !important;
            color: #ffffff !important;
        }
        .btn-soft-primary {
            background-color: rgba(74, 108, 247, 0.08) !important;
            color: var(--bs-primary) !important;
            border: 1px dashed rgba(74, 108, 247, 0.25) !important;
            transition: all 0.15s ease;
            font-weight: 500 !important;
        }
        .btn-soft-primary:hover {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
            border-style: solid !important;
        }
    </style>

    <div class="settings-container">
        <!-- Sidebar Column -->
        <div class="settings-sidebar-col">
            @include('modules.hrms.partials.settings-sidebar')
        </div>

        <!-- Content Column -->
        <div class="settings-content-col">
            @if(session('success'))
                <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                    {{ session('success') }}
                </x-ui.alert>
            @endif
            @if(session('error'))
                <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                    {{ session('error') }}
                </x-ui.alert>
            @endif

            <!-- Single Outer Card spanning full width -->
            <div class="col-12">
                <x-ui.card title="Penalization Policies" subtitle="Configure thresholds, grace periods, and deductions for attendance infractions" bodyClass="p-0" stretch>
                    <div class="row g-0">
                        <!-- LEFT COLUMN: RULES CATEGORIES ONLY -->
                        <div class="col-md-3 col-12 border-end">
                            <div class="list-group list-group-flush rounded-0" style="min-height: 400px;">
                                @php
                                    $policyTypes = [
                                        'late_arrival' => ['Late Arrival', 'feather-clock'],
                                        'under_hours' => ['Work Hours Deficit', 'feather-trending-down'],
                                        'missing_logs' => ['Missing Logs', 'feather-alert-triangle']
                                    ];
                                    $lateArrivalRule = $rules->get('late_arrival');
                                    $savedLateTiers = ($lateArrivalRule && $lateArrivalRule->penalty_tiers) ? $lateArrivalRule->penalty_tiers : null;
                                    $underHoursRule = $rules->get('under_hours');
                                    $savedDeficitTiers = ($underHoursRule && $underHoursRule->penalty_tiers) ? $underHoursRule->penalty_tiers : null;
                                    $missingLogsRule = $rules->get('missing_logs');
                                    $savedMissingTiers = ($missingLogsRule && $missingLogsRule->penalty_tiers) ? $missingLogsRule->penalty_tiers : null;
                                @endphp
                                @foreach($policyTypes as $typeKey => $typeData)
                                    @php
                                        $isActive = ($selectedType === $typeKey);
                                    @endphp
                                    <a href="javascript:void(0);" 
                                       class="list-group-item list-group-item-action py-3.5 px-4 policy-item policy-switch-btn {{ $isActive ? 'active' : '' }}"
                                       data-target="#policy-details-{{ $typeKey }}"
                                       data-policy-type="{{ $typeKey }}">
                                        <div class="d-flex align-items-center">
                                            <i class="{{ $typeData[1] }} me-3 fs-16 {{ $isActive ? 'text-primary' : 'text-secondary' }}"></i>
                                            <span class="fw-bold {{ $isActive ? 'text-primary' : 'text-dark' }}" style="font-size: 13px;">
                                                {{ $typeData[0] }}
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: SELECTED POLICY FORM DETAILED WORKSPACE -->
                        <div class="col-md-9 col-12">
                            @foreach($policyTypes as $typeKey => $typeData)
                                @php
                                    $isPaneActive = ($selectedType === $typeKey);
                                    $rule = $rules->get($typeKey);
                                    $action = $rule ? $rule->penalty_action : 'salary_deduction';
                                    $val = $rule ? floatval($rule->penalty_value) : 0.5;
                                    $statusVal = $rule ? ($rule->status ? '1' : '0') : '1';
                                @endphp
                                <div class="policy-details-pane {{ $isPaneActive ? '' : 'd-none' }}" id="policy-details-{{ $typeKey }}">
                                    <form action="{{ route('hrms.penalization-policy.store') }}" method="POST" class="p-4">
                                        @csrf
                                        <input type="hidden" name="rule_type" value="{{ $typeKey }}">

                                        <!-- Panel Header Details -->
                                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                                            <div>
                                                <h5 class="fw-bold text-dark mb-1" style="font-size: 16px;">
                                                    <i class="{{ $typeData[1] }} text-primary me-2 fs-18"></i>Configure {{ $typeData[0] }} Rules
                                                </h5>
                                                <span class="text-muted fs-12">Set thresholds, deductions, and active policy scope</span>
                                            </div>
                                            <div>
                                                <x-ui.badge variant="{{ $statusVal === '1' ? 'success' : 'danger' }}" soft class="px-2 py-1">
                                                    {{ $statusVal === '1' ? 'Active' : 'Inactive' }}
                                                </x-ui.badge>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-4">
                                            <!-- Entity Scope -->
                                            <div class="col-md-6 col-12">
                                                <x-ui.odoo-form-ui type="select" label="Company Scope" name="company_id" select2-selector="default">
                                                    <option value="">Apply Globally (All Entities)</option>
                                                    @foreach($companies as $company)
                                                        <option value="{{ $company->id }}" {{ ($rule && $rule->company_id === $company->id) ? 'selected' : '' }}>
                                                            {{ $company->company_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-6 col-12">
                                                <x-ui.odoo-form-ui type="select" label="Policy Status" name="status" select2-selector="default" :required="true">
                                                    <option value="1" {{ $statusVal === '1' ? 'selected' : '' }}>Active (Enforce Policy)</option>
                                                    <option value="0" {{ $statusVal === '0' ? 'selected' : '' }}>Inactive (Ignore Violations)</option>
                                                </x-ui.odoo-form-ui>
                                            </div>

                                             <!-- Type-Specific Parameters -->
                                             @if($typeKey === 'late_arrival')
                                                 <!-- Grace Period -->
                                                 <div class="col-12">
                                                     <div class="alert bg-light border-0 d-flex align-items-center gap-2 p-3 m-0 rounded-3 text-dark fs-13 flex-nowrap">
                                                         <i class="feather-info text-primary fs-16"></i>
                                                         <span>Late arrival grace period is</span>
                                                         <input type="number" name="grace_period_minutes" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="{{ $rule ? $rule->grace_period_minutes : 15 }}" min="0" style="width: 70px; height: 32px; font-weight: 600;" required>
                                                         <span>minutes relative to shift start time.</span>
                                                     </div>
                                                 </div>

                                                 <div class="col-12 border-top my-4 pt-4">
                                                     <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 14px; letter-spacing: 0.25px;">
                                                         <i class="feather-grid text-primary fs-16"></i> Configure Penalty Tiers
                                                     </h6>
                                                     <span class="text-muted fs-11 d-block mb-3">Define occurrence boundaries and corresponding penalty actions</span>
                                                 </div>

                                                 <div class="col-12">
                                                     <div class="table-responsive border rounded bg-white">
                                                         <table class="table table-sm table-hover align-middle mb-0 erp-thin-table" id="late-arrival-tiers-table" style="font-size: 13px;">
                                                             <thead class="table-light">
                                                                 <tr>
                                                                     <th style="width: 10%; min-width: 95px;">Min Occurrences</th>
                                                                     <th style="width: 10%; min-width: 105px;">Max Occurrences</th>
                                                                     <th style="width: 30%; min-width: 240px;">Penalty Settlement Method</th>
                                                                     <th style="width: 12%; min-width: 115px;">Deduction Value (Days)</th>
                                                                     <th style="width: 28%; min-width: 210px;">Deduct From Leave Type</th>
                                                                     <th style="width: 10%; min-width: 60px;" class="text-center">Action</th>
                                                                 </tr>
                                                             </thead>
                                                             <tbody id="late-arrival-tiers-tbody">
                                                                 <!-- Tiers will be dynamically rendered here -->
                                                             </tbody>
                                                         </table>
                                                     </div>
                                                     <div class="mt-3">
                                                         <x-ui.button type="button" variant="soft-primary" size="sm" id="btn-add-tier" icon="feather-plus">
                                                             Add Penalty Tier
                                                         </x-ui.button>
                                                     </div>
                                                 </div>
                                             @endif

                                             @if($typeKey === 'under_hours')
                                                 <!-- Shift Deficit Parameters -->
                                                 <div class="col-12 d-flex flex-column gap-2">
                                                     <div class="alert bg-light border-0 d-flex align-items-center gap-2 p-2.5 m-0 rounded-3 text-dark fs-13 flex-nowrap">
                                                         <i class="feather-info text-primary fs-16"></i>
                                                         <span>Shift working hours target is</span>
                                                         <input type="number" name="grace_period_hours" step="0.5" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="{{ $rule ? ($rule->grace_period_minutes / 60) : 8 }}" min="0" style="width: 70px; height: 32px; font-weight: 600;" required>
                                                         <span>hours.</span>
                                                     </div>
                                                     <div class="alert bg-light border-0 d-flex align-items-center gap-2 p-2.5 m-0 rounded-3 text-dark fs-13 flex-nowrap">
                                                         <i class="feather-calendar text-primary fs-16"></i>
                                                         <span>Allowed monthly grace of</span>
                                                         <input type="number" name="threshold_count" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="{{ $rule ? $rule->threshold_count : 2 }}" min="0" style="width: 60px; height: 32px; font-weight: 600;" required>
                                                         <span>deficit occurrences before penalties trigger.</span>
                                                     </div>
                                                 </div>

                                                 <div class="col-12 border-top my-4 pt-4">
                                                     <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 14px; letter-spacing: 0.25px;">
                                                         <i class="feather-grid text-primary fs-16"></i> Configure Penalty Tiers
                                                     </h6>
                                                     <span class="text-muted fs-11 d-block mb-3">Define occurrence boundaries and corresponding penalty actions</span>
                                                 </div>

                                                 <div class="col-12">
                                                     <div class="table-responsive border rounded bg-white">
                                                         <table class="table table-sm table-hover align-middle mb-0 erp-thin-table" id="under-hours-tiers-table" style="font-size: 13px;">
                                                             <thead class="table-light">
                                                                 <tr>
                                                                     <th style="width: 18%; min-width: 150px;">If Shift Hours Less Than</th>
                                                                     <th style="width: 32%; min-width: 240px;">Penalty Settlement Method</th>
                                                                     <th style="width: 12%; min-width: 115px;">Deduction Value (Days)</th>
                                                                     <th style="width: 28%; min-width: 210px;">Deduct From Leave Type</th>
                                                                     <th style="width: 10%; min-width: 60px;" class="text-center">Action</th>
                                                                 </tr>
                                                             </thead>
                                                             <tbody id="under-hours-tiers-tbody">
                                                                 <!-- Tiers will be dynamically rendered here -->
                                                             </tbody>
                                                         </table>
                                                     </div>
                                                     <div class="mt-3">
                                                         <x-ui.button type="button" variant="soft-primary" size="sm" id="btn-add-deficit-tier" icon="feather-plus">
                                                             Add Penalty Tier
                                                         </x-ui.button>
                                                     </div>
                                                 </div>
                                             @endif

                                             @if($typeKey === 'missing_logs')
                                                 <!-- Allowed Free Missing Log Counts (Per Month) -->
                                                 <div class="col-12">
                                                     <div class="alert bg-light border-0 d-flex align-items-center gap-2 p-3 m-0 rounded-3 text-dark fs-13 flex-nowrap">
                                                         <i class="feather-info text-primary fs-16"></i>
                                                         <span>Employees are allowed a monthly grace of</span>
                                                         <input type="number" name="threshold_count" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="{{ $rule ? $rule->threshold_count : 2 }}" min="0" style="width: 60px; height: 32px; font-weight: 600;" required>
                                                         <span>missing logs before penalties trigger.</span>
                                                     </div>
                                                 </div>

                                                 <div class="col-12 border-top my-4 pt-4">
                                                     <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 14px; letter-spacing: 0.25px;">
                                                         <i class="feather-grid text-primary fs-16"></i> Configure Penalty Tiers
                                                     </h6>
                                                     <span class="text-muted fs-11 d-block mb-3">Define occurrence boundaries and corresponding penalty actions</span>
                                                 </div>

                                                 <div class="col-12">
                                                     <div class="table-responsive border rounded bg-white">
                                                         <table class="table table-sm table-hover align-middle mb-0 erp-thin-table" id="missing-logs-tiers-table" style="font-size: 13px;">
                                                             <thead class="table-light">
                                                                 <tr>
                                                                     <th style="width: 11%; min-width: 95px;">Min Occurrences</th>
                                                                     <th style="width: 12%; min-width: 105px;">Max Occurrences</th>
                                                                     <th style="width: 25%; min-width: 195px;">Penalty Settlement Method</th>
                                                                     <th style="width: 15%; min-width: 115px;">Deduction Value (Days)</th>
                                                                     <th style="width: 27%; min-width: 175px;">Deduct From Leave Type</th>
                                                                     <th style="width: 10%; min-width: 60px;" class="text-center">Action</th>
                                                                 </tr>
                                                             </thead>
                                                             <tbody id="missing-logs-tiers-tbody">
                                                                 <!-- Tiers will be dynamically rendered here -->
                                                             </tbody>
                                                         </table>
                                                     </div>
                                                     <div class="mt-3">
                                                         <x-ui.button type="button" variant="soft-primary" size="sm" id="btn-add-missing-tier" icon="feather-plus">
                                                             Add Penalty Tier
                                                         </x-ui.button>
                                                     </div>
                                                 </div>
                                             @endif
                                         </div>

                                        <!-- Save Footer Button -->
                                        <div class="border-top pt-3 d-flex justify-content-end">
                                            <x-ui.button type="submit" variant="primary" icon="feather-save">
                                                Save Policy Settings
                                            </x-ui.button>
                                        </div>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            function buildPolicySelect2Options($select, $pane) {
                let selectorType = $select.attr('data-select2-selector') || 'default';
                let options = {
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: $pane
                };

                if (selectorType === 'status' && typeof bgformat === 'function') {
                    options.templateResult = bgformat;
                    options.templateSelection = bgformat;
                    options.minimumResultsForSearch = Infinity;
                } else if (selectorType === 'currency' && typeof currencyformat === 'function') {
                    options.templateResult = currencyformat;
                    options.templateSelection = currencyformat;
                } else if (selectorType === 'country' && typeof countryformat === 'function') {
                    options.templateResult = countryformat;
                    options.templateSelection = countryformat;
                } else if (selectorType === 'tzone' && typeof tzoneformat === 'function') {
                    options.templateResult = tzoneformat;
                    options.templateSelection = tzoneformat;
                }

                return options;
            }

            function initPolicyPaneSelects(paneSelector) {
                let $pane = $(paneSelector);
                if (!$pane.length) return;

                $pane.find('select[data-select2-selector]').each(function() {
                    let $select = $(this);
                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }
                    $select.select2(buildPolicySelect2Options($select, $pane));
                });
            }

            // Client-side Policy Pane Switching (Zero reloads)
            $(document).on('click', '.policy-switch-btn', function(e) {
                e.preventDefault();
                let clicked = $(this);
                let targetPaneId = clicked.attr('data-target');
                let policyType = clicked.attr('data-policy-type');

                // Switch active highlights in category selector lists
                $('.policy-switch-btn').removeClass('active');
                $('.policy-switch-btn i').removeClass('text-primary').addClass('text-secondary');
                $('.policy-switch-btn span').removeClass('text-primary').addClass('text-dark');
                
                clicked.addClass('active');
                clicked.find('i').removeClass('text-secondary').addClass('text-primary');
                clicked.find('span').removeClass('text-dark').addClass('text-primary');

                // Toggle visibility of target details pane
                $('.policy-details-pane').addClass('d-none');
                $(targetPaneId).removeClass('d-none');
                initPolicyPaneSelects(targetPaneId);

                // Update URL history parameters to persist select focus on reload
                if (history.pushState) {
                    let newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?policy_type=' + policyType;
                    window.history.pushState({path:newurl}, '', newurl);
                }
            });

            // Dynamically show/hide Leave Type select dropdown based on radio check
            $(document).on('change', '.action-toggle', function() {
                let radio = $(this);
                let pane = radio.closest('.policy-details-pane');
                let leaveContainer = pane.find('.leave-select-container');
                let dropdown = pane.find('.leave-type-dropdown');

                if (radio.val() === 'leave_deduction') {
                    leaveContainer.removeClass('d-none');
                    dropdown.prop('required', true);
                } else {
                    leaveContainer.addClass('d-none');
                    dropdown.prop('required', false);
                    dropdown.val('');
                }
            });

            // Late Arrival Tiers Builder Logic
            let tierIndex = 0;

            function addTierRow(min_occ = '', max_occ = '', penalty_action = 'no_deduction', penalty_value = 0, leave_type_id = '') {
                let tbody = $('#late-arrival-tiers-tbody');
                
                let leaveOptions = `<option value="">Select Leave Quota</option>`;
                @foreach($leaveTypes as $lType)
                    leaveOptions += `<option value="{{ $lType->id }}" ${leave_type_id == "{{ $lType->id }}" ? 'selected' : ''}>{{ $lType->name }} ({{ strtoupper($lType->code) }})</option>`;
                @endforeach

                let isLeaveRequired = (penalty_action === 'leave_deduction' || penalty_action === 'both_deductions');
                let leaveStyles = isLeaveRequired ? '' : 'style="display:none;"';
                let leaveDisabled = isLeaveRequired ? '' : 'disabled';
                let valueReadonly = (penalty_action === 'no_deduction') ? 'readonly' : '';

                let rowHtml = `
                    <tr class="tier-row" data-index="${tierIndex}">
                        <td>
                            <input type="number" name="penalty_tiers[${tierIndex}][min_occurrence]" class="odoo-table-input min-occ" min="1" value="${min_occ}" required>
                        </td>
                        <td>
                            <input type="number" name="penalty_tiers[${tierIndex}][max_occurrence]" class="odoo-table-input max-occ" min="1" value="${max_occ !== null ? max_occ : ''}" placeholder="Unlimited">
                        </td>
                        <td>
                            <select name="penalty_tiers[${tierIndex}][penalty_action]" class="odoo-table-select tier-action-select" required>
                                <option value="no_deduction" ${penalty_action === 'no_deduction' ? 'selected' : ''}>No Deduction (Free)</option>
                                <option value="salary_deduction" ${penalty_action === 'salary_deduction' ? 'selected' : ''}>Loss of Pay (Salary)</option>
                                <option value="leave_deduction" ${penalty_action === 'leave_deduction' ? 'selected' : ''}>Deduct Leave Balance</option>
                                <option value="both_deductions" ${penalty_action === 'both_deductions' ? 'selected' : ''}>Both (Salary & Leave)</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" step="0.1" name="penalty_tiers[${tierIndex}][penalty_value]" class="odoo-table-input tier-value-input" min="0" value="${penalty_value}" ${valueReadonly} required>
                        </td>
                        <td>
                            <select name="penalty_tiers[${tierIndex}][leave_type_id]" class="odoo-table-select tier-leave-select" ${leaveDisabled} ${leaveStyles}>
                                ${leaveOptions}
                            </select>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-soft-danger btn-remove-tier"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>
                `;

                let row = $(rowHtml);
                tbody.append(row);
                
                row.find('.tier-action-select, .tier-leave-select').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#late-arrival-tiers-table')
                });

                tierIndex++;
                updateLeaveColumnVisibility('late-arrival-tiers-table');
            }

            // Pre-populate late arrival tiers
            let savedLateTiers = @json($savedLateTiers);
            if (savedLateTiers && Array.isArray(savedLateTiers) && savedLateTiers.length > 0) {
                savedLateTiers.forEach(t => {
                    addTierRow(t.min_occurrence, t.max_occurrence, t.penalty_action, t.penalty_value, t.leave_type_id);
                });
            } else {
                // Seed standard defaults: 1-3 Free, 4-5 Salary Deduct 1 day, 6+ Salary Deduct 2 days
                addTierRow(1, 3, 'no_deduction', 0, '');
                addTierRow(4, 5, 'salary_deduction', 1, '');
                addTierRow(6, '', 'salary_deduction', 2, '');
            }

            // Add Tier Button Click handler
            $(document).on('click', '#btn-add-tier', function() {
                // Try to find the max value of existing rows to guess next min occurrence
                let lastMax = 0;
                $('.max-occ').each(function() {
                    let val = parseInt($(this).val());
                    if (!isNaN(val) && val > lastMax) {
                        lastMax = val;
                    }
                });

                let nextMin = lastMax ? lastMax + 1 : 1;
                addTierRow(nextMin, '', 'salary_deduction', 1, '');
            });

            // Remove Tier Row handler
            $(document).on('click', '.btn-remove-tier', function() {
                let row = $(this).closest('tr');
                row.remove();
                
                // If all rows removed, insert a default row
                if ($('#late-arrival-tiers-tbody tr').length === 0) {
                    addTierRow(1, '', 'no_deduction', 0, '');
                }
                updateLeaveColumnVisibility('late-arrival-tiers-table');
            });

            // Work Hours Deficit Tiers Builder Logic
            let deficitTierIndex = 0;

            function addDeficitTierRow(hours_threshold = '', penalty_action = 'no_deduction', penalty_value = 0, leave_type_id = '') {
                let tbody = $('#under-hours-tiers-tbody');
                
                let leaveOptions = `<option value="">Select Leave Quota</option>`;
                @foreach($leaveTypes as $lType)
                    leaveOptions += `<option value="{{ $lType->id }}" ${leave_type_id == "{{ $lType->id }}" ? 'selected' : ''}>{{ $lType->name }} ({{ strtoupper($lType->code) }})</option>`;
                @endforeach

                let isLeaveRequired = (penalty_action === 'leave_deduction' || penalty_action === 'both_deductions');
                let leaveStyles = isLeaveRequired ? '' : 'style="display:none;"';
                let leaveDisabled = isLeaveRequired ? '' : 'disabled';
                let valueReadonly = (penalty_action === 'no_deduction') ? 'readonly' : '';

                let rowHtml = `
                    <tr class="deficit-tier-row" data-index="${deficitTierIndex}">
                        <td>
                            <input type="number" step="0.1" name="penalty_tiers[${deficitTierIndex}][hours_threshold]" class="odoo-table-input hours-threshold" min="0" max="24" value="${hours_threshold}" required>
                        </td>
                        <td>
                            <select name="penalty_tiers[${deficitTierIndex}][penalty_action]" class="odoo-table-select tier-action-select" required>
                                <option value="no_deduction" ${penalty_action === 'no_deduction' ? 'selected' : ''}>No Deduction (Free)</option>
                                <option value="salary_deduction" ${penalty_action === 'salary_deduction' ? 'selected' : ''}>Loss of Pay (Salary)</option>
                                <option value="leave_deduction" ${penalty_action === 'leave_deduction' ? 'selected' : ''}>Deduct Leave Balance</option>
                                <option value="both_deductions" ${penalty_action === 'both_deductions' ? 'selected' : ''}>Both (Salary & Leave)</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" step="0.1" name="penalty_tiers[${deficitTierIndex}][penalty_value]" class="odoo-table-input tier-value-input" min="0" value="${penalty_value}" ${valueReadonly} required>
                        </td>
                        <td>
                            <select name="penalty_tiers[${deficitTierIndex}][leave_type_id]" class="odoo-table-select tier-leave-select" ${leaveDisabled} ${leaveStyles}>
                                ${leaveOptions}
                            </select>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-soft-danger btn-remove-deficit-tier"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>
                `;

                let row = $(rowHtml);
                tbody.append(row);
                
                row.find('.tier-action-select, .tier-leave-select').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#under-hours-tiers-table')
                });

                deficitTierIndex++;
                updateLeaveColumnVisibility('under-hours-tiers-table');
            }

            // Pre-populate deficit tiers
            let savedDeficitTiers = @json($savedDeficitTiers);
            if (savedDeficitTiers && Array.isArray(savedDeficitTiers) && savedDeficitTiers.length > 0) {
                savedDeficitTiers.forEach(t => {
                    addDeficitTierRow(t.hours_threshold, t.penalty_action, t.penalty_value, t.leave_type_id);
                });
            } else {
                // Seed standard defaults: < 6 Hours -> Loss of Pay (1 Day), < 4 Hours -> Loss of Pay (2 Days)
                addDeficitTierRow(6, 'salary_deduction', 1, '');
                addDeficitTierRow(4, 'salary_deduction', 2, '');
            }

            // Add Deficit Tier Button Click handler
            $(document).on('click', '#btn-add-deficit-tier', function() {
                addDeficitTierRow('', 'salary_deduction', 1, '');
            });

            // Remove Deficit Tier Row handler
            $(document).on('click', '.btn-remove-deficit-tier', function() {
                let row = $(this).closest('tr');
                row.remove();
                
                // If all rows removed, insert a default row
                if ($('#under-hours-tiers-tbody tr').length === 0) {
                    addDeficitTierRow(6, 'salary_deduction', 1, '');
                }
                updateLeaveColumnVisibility('under-hours-tiers-table');
            });

            // Missing Logs Tiers Builder Logic
            let missingTierIndex = 0;

            function addMissingTierRow(min_occ = '', max_occ = '', penalty_action = 'no_deduction', penalty_value = 0, leave_type_id = '') {
                let tbody = $('#missing-logs-tiers-tbody');
                
                let leaveOptions = `<option value="">Select Leave Quota</option>`;
                @foreach($leaveTypes as $lType)
                    leaveOptions += `<option value="{{ $lType->id }}" ${leave_type_id == "{{ $lType->id }}" ? 'selected' : ''}>{{ $lType->name }} ({{ strtoupper($lType->code) }})</option>`;
                @endforeach

                let isLeaveRequired = (penalty_action === 'leave_deduction' || penalty_action === 'both_deductions');
                let leaveStyles = isLeaveRequired ? '' : 'style="display:none;"';
                let leaveDisabled = isLeaveRequired ? '' : 'disabled';
                let valueReadonly = (penalty_action === 'no_deduction') ? 'readonly' : '';

                let rowHtml = `
                    <tr class="missing-tier-row" data-index="${missingTierIndex}">
                        <td>
                            <input type="number" name="penalty_tiers[${missingTierIndex}][min_occurrence]" class="odoo-table-input missing-min-occ" min="1" value="${min_occ}" required>
                        </td>
                        <td>
                            <input type="number" name="penalty_tiers[${missingTierIndex}][max_occurrence]" class="odoo-table-input missing-max-occ" min="1" value="${max_occ !== null ? max_occ : ''}" placeholder="Unlimited">
                        </td>
                        <td>
                            <select name="penalty_tiers[${missingTierIndex}][penalty_action]" class="odoo-table-select tier-action-select" required>
                                <option value="no_deduction" ${penalty_action === 'no_deduction' ? 'selected' : ''}>No Deduction (Free)</option>
                                <option value="salary_deduction" ${penalty_action === 'salary_deduction' ? 'selected' : ''}>Loss of Pay (Salary)</option>
                                <option value="leave_deduction" ${penalty_action === 'leave_deduction' ? 'selected' : ''}>Deduct Leave Balance</option>
                                <option value="both_deductions" ${penalty_action === 'both_deductions' ? 'selected' : ''}>Both (Salary & Leave)</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" step="0.1" name="penalty_tiers[${missingTierIndex}][penalty_value]" class="odoo-table-input tier-value-input" min="0" value="${penalty_value}" ${valueReadonly} required>
                        </td>
                        <td>
                            <select name="penalty_tiers[${missingTierIndex}][leave_type_id]" class="odoo-table-select tier-leave-select" ${leaveDisabled} ${leaveStyles}>
                                ${leaveOptions}
                            </select>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-soft-danger btn-remove-missing-tier"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>
                `;

                let row = $(rowHtml);
                tbody.append(row);
                
                row.find('.tier-action-select, .tier-leave-select').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#missing-logs-tiers-table')
                });

                missingTierIndex++;
                updateLeaveColumnVisibility('missing-logs-tiers-table');
            }

            // Pre-populate missing logs tiers
            let savedMissingTiers = @json($savedMissingTiers);
            if (savedMissingTiers && Array.isArray(savedMissingTiers) && savedMissingTiers.length > 0) {
                savedMissingTiers.forEach(t => {
                    addMissingTierRow(t.min_occurrence, t.max_occurrence, t.penalty_action, t.penalty_value, t.leave_type_id);
                });
            } else {
                // Seed standard defaults: 1-3 Free, 4-5 Salary Deduct 0.5 day, 6+ Salary Deduct 1 day
                addMissingTierRow(1, 3, 'no_deduction', 0, '');
                addMissingTierRow(4, 5, 'salary_deduction', 0.5, '');
                addMissingTierRow(6, '', 'salary_deduction', 1, '');
            }

            // Add Missing Tier Button Click handler
            $(document).on('click', '#btn-add-missing-tier', function() {
                // Try to find the max value of existing rows to guess next min occurrence
                let lastMax = 0;
                $('.missing-max-occ').each(function() {
                    let val = parseInt($(this).val());
                    if (!isNaN(val) && val > lastMax) {
                        lastMax = val;
                    }
                });

                let nextMin = lastMax ? lastMax + 1 : 1;
                addMissingTierRow(nextMin, '', 'salary_deduction', 0.5, '');
            });

            // Remove Missing Tier Row handler
            $(document).on('click', '.btn-remove-missing-tier', function() {
                let row = $(this).closest('tr');
                row.remove();
                
                // If all rows removed, insert a default row
                if ($('#missing-logs-tiers-tbody tr').length === 0) {
                    addMissingTierRow(1, '', 'no_deduction', 0, '');
                }
                updateLeaveColumnVisibility('missing-logs-tiers-table');
            });

            // Helper to dynamically show/hide the Deduct Leave column if no rows require it
            function updateLeaveColumnVisibility(tableId) {
                let table = $('#' + tableId);
                if (table.length === 0) return;
                
                let hasLeaveAction = false;
                table.find('.tier-action-select').each(function() {
                    let val = $(this).val();
                    if (val === 'leave_deduction' || val === 'both_deductions') {
                        hasLeaveAction = true;
                    }
                });

                // Find index of column header that contains "Leave Type"
                let leaveColIdx = -1;
                table.find('thead th').each(function(index) {
                    let text = $(this).text().toUpperCase();
                    if (text.indexOf('LEAVE TYPE') !== -1) {
                        leaveColIdx = index;
                    }
                });

                if (leaveColIdx !== -1) {
                    if (hasLeaveAction) {
                        table.find('thead th').eq(leaveColIdx).show();
                        table.find('tbody tr').each(function() {
                            $(this).find('td').eq(leaveColIdx).show();
                        });
                    } else {
                        table.find('thead th').eq(leaveColIdx).hide();
                        table.find('tbody tr').each(function() {
                            $(this).find('td').eq(leaveColIdx).hide();
                        });
                    }
                }
            }

            // Handle action select changes dynamically
            $(document).on('change', '.tier-action-select', function() {
                let select = $(this);
                let row = select.closest('tr');
                let valueInput = row.find('.tier-value-input');
                let leaveSelect = row.find('.tier-leave-select');
                let select2Container = leaveSelect.next('.select2-container');

                if (select.val() === 'no_deduction') {
                    valueInput.val(0).prop('readonly', true);
                    leaveSelect.val('').prop('required', false).prop('disabled', true).trigger('change.select2');
                    if (select2Container.length) select2Container.hide();
                    else leaveSelect.hide();
                } else {
                    valueInput.prop('readonly', false);
                    if (select.val() === 'leave_deduction' || select.val() === 'both_deductions') {
                        leaveSelect.prop('required', true).prop('disabled', false);
                        if (select2Container.length) select2Container.show();
                        else leaveSelect.show();
                    } else {
                        // salary_deduction
                        leaveSelect.val('').prop('required', false).prop('disabled', true).trigger('change.select2');
                        if (select2Container.length) select2Container.hide();
                        else leaveSelect.hide();
                    }
                }

                let tableId = select.closest('table').attr('id');
                updateLeaveColumnVisibility(tableId);
            });

            // Initial load visibility checks
            initPolicyPaneSelects('.policy-details-pane:not(.d-none)');
            updateLeaveColumnVisibility('late-arrival-tiers-table');
            updateLeaveColumnVisibility('under-hours-tiers-table');
            updateLeaveColumnVisibility('missing-logs-tiers-table');
        });
    </script>
    <script>
        (function () {
            if (window.hrmsThemedValidationInstalled) {
                return;
            }

            window.hrmsThemedValidationInstalled = true;

            function getFieldLabel(field) {
                const group = field.closest('.odoo-form-group');
                const label = group ? group.querySelector('.odoo-form-label') : null;
                return label ? label.textContent.replace('*', '').trim() : 'This field';
            }

            function getValidationMessage(field) {
                const label = getFieldLabel(field).toLowerCase();

                if (field.validity.valueMissing) {
                    return field.tagName === 'SELECT' ? `Please select ${label}.` : `Please enter ${label}.`;
                }

                return field.validationMessage || 'Please enter a valid value.';
            }

            function getErrorAnchor(field) {
                if (field.tagName === 'SELECT' && field.nextElementSibling && field.nextElementSibling.classList.contains('select2-container')) {
                    return field.nextElementSibling;
                }

                if (field.type === 'radio') {
                    return field.closest('.odoo-form-group')?.querySelector('.flex-grow-1') || field;
                }

                return field;
            }

            function showFieldError(field) {
                field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');

                const anchor = getErrorAnchor(field);
                let error = anchor.nextElementSibling;

                if (!error || !error.classList.contains('hrms-client-validation-error')) {
                    error = document.createElement('div');
                    error.className = 'invalid-feedback d-block fs-11 mt-1 hrms-client-validation-error';
                    anchor.insertAdjacentElement('afterend', error);
                }

                error.textContent = getValidationMessage(field);
            }

            function clearFieldError(field) {
                field.classList.remove('is-invalid');
                field.removeAttribute('aria-invalid');

                const error = getErrorAnchor(field).nextElementSibling;
                if (error && error.classList.contains('hrms-client-validation-error')) {
                    error.remove();
                }
            }

            function getRequiredFields(form) {
                return Array.from(form.querySelectorAll('[required]')).filter(field => !field.disabled && field.type !== 'hidden');
            }

            function validateField(field) {
                if (field.checkValidity()) {
                    clearFieldError(field);
                    return true;
                }

                showFieldError(field);
                return false;
            }

            function focusField(field) {
                const select2 = field.tagName === 'SELECT' && field.nextElementSibling?.classList.contains('select2-container') ? field.nextElementSibling : null;
                const target = select2 || field;
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                (select2?.querySelector('.select2-selection') || field).focus({ preventScroll: true });
            }

            function bindHrmsValidation(root) {
                root.querySelectorAll('form').forEach(function (form) {
                    if (form.dataset.hrmsThemedValidation === '1' || !form.querySelector('[required]')) {
                        return;
                    }

                    form.dataset.hrmsThemedValidation = '1';
                    form.setAttribute('novalidate', 'novalidate');

                    getRequiredFields(form).forEach(function (field) {
                        field.addEventListener('input', () => validateField(field));
                        field.addEventListener('change', () => validateField(field));
                    });

                    form.addEventListener('submit', function (event) {
                        const invalidField = getRequiredFields(form).find(field => !validateField(field));

                        if (invalidField) {
                            event.preventDefault();
                            event.stopImmediatePropagation();
                            focusField(invalidField);
                        }
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', () => bindHrmsValidation(document));
            document.addEventListener('shown.bs.modal', event => bindHrmsValidation(event.target));
        })();
    </script>
@endsection
