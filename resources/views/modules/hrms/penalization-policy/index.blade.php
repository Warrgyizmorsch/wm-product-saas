@extends('layouts.duralux')

@section('title', __('hrms.penalization.title') . ' | SaaS ERP')
@section('page-title', __('hrms.penalization.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.penalization.title'))

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

            <!-- Single Outer Card spanning full width -->
            <div class="col-12">
                <x-ui.card title="{{ __('hrms.penalization.title') }}" subtitle="{{ __('hrms.penalization.subtitle') }}" bodyClass="p-0" stretch>
                    <div class="row g-0">
                        <!-- LEFT COLUMN: RULES CATEGORIES ONLY -->
                        <div class="col-md-3 col-12 border-end">
                            <div class="list-group list-group-flush rounded-0" style="min-height: 400px;">
                                @php
                                    $policyTypes = [
                                        'late_arrival' => [__('hrms.penalization.late_arrival'), 'feather-clock'],
                                        'under_hours' => [__('hrms.penalization.under_hours'), 'feather-trending-down'],
                                        'missing_logs' => [__('hrms.penalization.missing_logs'), 'feather-alert-triangle'],
                                        'attendance_rules' => [__('hrms.penalization.attendance_rules'), 'feather-check-square']
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
                                    <form action="{{ $typeKey === 'attendance_rules' ? route('hrms.attendance-rules.save') : route('hrms.penalization-policy.store') }}" method="POST" class="p-4">
                                        @csrf
                                        <input type="hidden" name="rule_type" value="{{ $typeKey }}">

                                        <!-- Panel Header Details -->
                                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                                            <div>
                                                <h5 class="fw-bold text-dark mb-1" style="font-size: 16px;">
                                                    <i class="{{ $typeData[1] }} text-primary me-2 fs-18"></i>
                                                    @if($typeKey === 'attendance_rules')
                                                        Configure Attendance Rules
                                                    @else
                                                        {{ __('hrms.penalization.configure_rules', ['type' => $typeData[0]]) }}
                                                    @endif
                                                </h5>
                                                <span class="text-muted fs-12">{{ __('hrms.penalization.set_thresholds_desc') }}</span>
                                            </div>
                                            <div>
                                                <x-ui.badge variant="{{ $statusVal === '1' ? 'success' : 'danger' }}" soft class="px-2 py-1">
                                                    {{ $statusVal === '1' ? __('hrms.employees.frm_status_active') : __('hrms.employees.frm_status_inactive') }}
                                                </x-ui.badge>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-4">
                                            <!-- Entity Scope -->
                                            <div class="col-md-6 col-12">
                                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.penalization.company_scope') }}" name="company_id" select2-selector="default" id="{{ $typeKey === 'attendance_rules' ? 'sel_company_id' : 'company_id_' . $typeKey }}">
                                                    @if($typeKey === 'attendance_rules')
                                                        <option value="" disabled selected>-- Select a Company (Required) --</option>
                                                    @else
                                                        <option value="">{{ __('hrms.penalization.apply_globally') }}</option>
                                                    @endif
                                                    @foreach($companies as $company)
                                                        <option value="{{ $company->id }}" {{ (($typeKey === 'attendance_rules' ? request('company_id') : ($rule ? $rule->company_id : null)) == $company->id) ? 'selected' : '' }}>
                                                            {{ $company->company_name }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-6 col-12">
                                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.penalization.policy_status') }}" name="status" select2-selector="default" :required="true" id="{{ $typeKey === 'attendance_rules' ? 'sel_status' : 'status_' . $typeKey }}">
                                                    <option value="1" {{ $statusVal === '1' ? 'selected' : '' }}>{{ __('hrms.penalization.active_enforce') }}</option>
                                                    <option value="0" {{ $statusVal === '0' ? 'selected' : '' }}>{{ __('hrms.penalization.inactive_ignore') }}</option>
                                                </x-ui.odoo-form-ui>
                                            </div>

                                             <!-- Type-Specific Parameters -->
                                             @if($typeKey === 'late_arrival')
                                                  <!-- Grace Period -->
                                                  <div class="col-12 d-flex flex-column gap-2">
                                                      <div class="alert bg-light border-0 p-3 m-0 rounded-3 text-dark fs-13">
                                                          <div class="d-flex align-items-start gap-2">
                                                              <i class="feather-info text-primary fs-16 mt-0.5"></i>
                                                              <div>
                                                                  <p class="mb-0 text-dark" style="line-height: 1.6;">
                                                                      {{ __('hrms.penalization.grace_period_set_to') }} 
                                                                      <input type="number" name="grace_period_minutes" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="{{ $rule ? $rule->grace_period_minutes : 15 }}" min="0" style="width: 60px; height: 24px; font-weight: 600; vertical-align: middle; border-bottom: 1px solid #cbd5e1 !important;" required>
                                                                      {{ __('hrms.penalization.minutes_relative_to_shift') }}
                                                                  </p>
                                                              </div>
                                                          </div>
                                                      </div>
                                                      <div class="alert bg-light border-0 p-3 m-0 rounded-3 text-dark fs-13">
                                                          <div class="d-flex align-items-start gap-2">
                                                              <i class="feather-calendar text-primary fs-16 mt-0.5"></i>
                                                              <div>
                                                                  <p class="mb-0 text-dark" style="line-height: 1.6;">
                                                                      {{ __('hrms.penalization.employee_allowed_up_to') }} 
                                                                      <input type="number" name="threshold_count" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="{{ $rule ? $rule->threshold_count : 2 }}" min="0" style="width: 60px; height: 24px; font-weight: 600; vertical-align: middle; border-bottom: 1px solid #cbd5e1 !important;" required>
                                                                      {{ __('hrms.penalization.late_occurrences_without_penalty') }}
                                                                  </p>
                                                              </div>
                                                          </div>
                                                      </div>
                                                  </div>

                                                 <div class="col-12 border-top my-4 pt-4">
                                                     <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 14px; letter-spacing: 0.25px;">
                                                         <i class="feather-grid text-primary fs-16"></i> {{ __('hrms.penalization.configure_tiers') }}
                                                     </h6>
                                                     <span class="text-muted fs-11 d-block mb-3">{{ __('hrms.penalization.define_boundaries') }}</span>
                                                 </div>

                                                 <div class="col-12">
                                                     <div class="table-responsive border rounded bg-white">
                                                         <table class="table table-sm table-hover align-middle mb-0 erp-thin-table" id="late-arrival-tiers-table" style="font-size: 13px;">
                                                              <thead class="table-light">
                                                                  <tr>
                                                                      <th style="width: 15%;">{{ __('hrms.penalization.min_occurrences') }}</th>
                                                                      <th style="width: 15%;">{{ __('hrms.penalization.max_occurrences') }}</th>
                                                                      <th style="width: 45%;">{{ __('hrms.penalization.settlement_method') }}</th>
                                                                      <th style="width: 15%;">Deduction Value (Days)</th>
                                                                      <th style="width: 10%;" class="text-center">{{ __('hrms.penalization.action') }}</th>
                                                                  </tr>
                                                              </thead>
                                                             <tbody id="late-arrival-tiers-tbody">
                                                                 <!-- Tiers will be dynamically rendered here -->
                                                             </tbody>
                                                         </table>
                                                     </div>
                                                     <div class="mt-3">
                                                         <x-ui.button type="button" variant="soft-primary" size="sm" id="btn-add-tier" icon="feather-plus">
                                                             {{ __('hrms.penalization.add_tier') }}
                                                         </x-ui.button>
                                                     </div>
                                                 </div>
                                             @endif

                                              @if($typeKey === 'under_hours')
                                                  <!-- Shift Deficit Parameters -->
                                                  <div class="col-12 d-flex flex-column gap-2">
                                                      <div class="alert bg-light border-0 p-3 m-0 rounded-3 text-dark fs-13">
                                                          <div class="d-flex align-items-start gap-2">
                                                              <i class="feather-info text-primary fs-16 mt-0.5"></i>
                                                              <div>
                                                                  <p class="mb-0 text-dark" style="line-height: 1.6;">
                                                                      {{ __('hrms.penalization.shift_target_hours') }}
                                                                      <input type="number" name="grace_period_hours" step="0.5" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="{{ $rule ? ($rule->grace_period_minutes / 60) : 8 }}" min="0" style="width: 60px; height: 24px; font-weight: 600; vertical-align: middle; border-bottom: 1px solid #cbd5e1 !important;" required>
                                                                      {{ __('hrms.penalization.hours') }}
                                                                  </p>
                                                              </div>
                                                          </div>
                                                      </div>
                                                      <div class="alert bg-light border-0 p-3 m-0 rounded-3 text-dark fs-13">
                                                          <div class="d-flex align-items-start gap-2">
                                                              <i class="feather-calendar text-primary fs-16 mt-0.5"></i>
                                                              <div>
                                                                  <p class="mb-0 text-dark" style="line-height: 1.6;">
                                                                      {{ __('hrms.penalization.allowed_monthly_grace') }}
                                                                      <input type="number" name="threshold_count" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="{{ $rule ? $rule->threshold_count : 2 }}" min="0" style="width: 60px; height: 24px; font-weight: 600; vertical-align: middle; border-bottom: 1px solid #cbd5e1 !important;" required>
                                                                      {{ __('hrms.penalization.deficit_before_trigger') }}
                                                                  </p>
                                                              </div>
                                                          </div>
                                                      </div>
                                                  </div>

                                                 <div class="col-12 border-top my-4 pt-4">
                                                     <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 14px; letter-spacing: 0.25px;">
                                                         <i class="feather-grid text-primary fs-16"></i> {{ __('hrms.penalization.configure_tiers') }}
                                                     </h6>
                                                     <span class="text-muted fs-11 d-block mb-3">{{ __('hrms.penalization.define_boundaries') }}</span>
                                                 </div>

                                                 <div class="col-12">
                                                     <div class="table-responsive border rounded bg-white">
                                                         <table class="table table-sm table-hover align-middle mb-0 erp-thin-table" id="under-hours-tiers-table" style="font-size: 13px;">
                                                             <thead class="table-light">
                                                                 <tr>
                                                                     <th style="width: 25%;">{{ __('hrms.penalization.if_hours_less') }}</th>
                                                                     <th style="width: 50%;">{{ __('hrms.penalization.settlement_method') }}</th>
                                                                     <th style="width: 15%;">Deduction Value (Days)</th>
                                                                     <th style="width: 10%;" class="text-center">{{ __('hrms.penalization.action') }}</th>
                                                                 </tr>
                                                             </thead>
                                                             <tbody id="under-hours-tiers-tbody">
                                                                 <!-- Tiers will be dynamically rendered here -->
                                                             </tbody>
                                                         </table>
                                                     </div>
                                                     <div class="mt-3">
                                                         <x-ui.button type="button" variant="soft-primary" size="sm" id="btn-add-deficit-tier" icon="feather-plus">
                                                             {{ __('hrms.penalization.add_tier') }}
                                                         </x-ui.button>
                                                     </div>
                                                 </div>
                                             @endif

                                              @if($typeKey === 'missing_logs')
                                                  <!-- Allowed Free Missing Log Counts (Per Month) -->
                                                  <div class="col-12">
                                                      <div class="alert bg-light border-0 p-3 m-0 rounded-3 text-dark fs-13">
                                                          <div class="d-flex align-items-start gap-2">
                                                              <i class="feather-info text-primary fs-16 mt-0.5"></i>
                                                              <div>
                                                                  <p class="mb-0 text-dark" style="line-height: 1.6;">
                                                                      {{ __('hrms.penalization.employees_allowed_grace') }}
                                                                      <input type="number" name="threshold_count" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="{{ $rule ? $rule->threshold_count : 2 }}" min="0" style="width: 60px; height: 24px; font-weight: 600; vertical-align: middle; border-bottom: 1px solid #cbd5e1 !important;" required>
                                                                      {{ __('hrms.penalization.missing_before_trigger') }}
                                                                  </p>
                                                              </div>
                                                          </div>
                                                      </div>
                                                  </div>

                                                 <div class="col-12 border-top my-4 pt-4">
                                                     <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 14px; letter-spacing: 0.25px;">
                                                         <i class="feather-grid text-primary fs-16"></i> {{ __('hrms.penalization.configure_tiers') }}
                                                     </h6>
                                                     <span class="text-muted fs-11 d-block mb-3">{{ __('hrms.penalization.define_boundaries') }}</span>
                                                 </div>

                                                 <div class="col-12">
                                                     <div class="table-responsive border rounded bg-white">
                                                         <table class="table table-sm table-hover align-middle mb-0 erp-thin-table" id="missing-logs-tiers-table" style="font-size: 13px;">
                                                              <thead class="table-light">
                                                                  <tr>
                                                                      <th style="width: 15%;">{{ __('hrms.penalization.min_occurrences') }}</th>
                                                                      <th style="width: 15%;">{{ __('hrms.penalization.max_occurrences') }}</th>
                                                                      <th style="width: 45%;">{{ __('hrms.penalization.settlement_method') }}</th>
                                                                      <th style="width: 15%;">Deduction Value (Days)</th>
                                                                      <th style="width: 10%;" class="text-center">{{ __('hrms.penalization.action') }}</th>
                                                                  </tr>
                                                              </thead>
                                                             <tbody id="missing-logs-tiers-tbody">
                                                                 <!-- Tiers will be dynamically rendered here -->
                                                             </tbody>
                                                         </table>
                                                     </div>
                                                     <div class="mt-3">
                                                         <x-ui.button type="button" variant="soft-primary" size="sm" id="btn-add-missing-tier" icon="feather-plus">
                                                             {{ __('hrms.penalization.add_tier') }}
                                                         </x-ui.button>
                                                     </div>
                                                 </div>
                                              @endif

                                              @if($typeKey === 'attendance_rules')
                                                  <!-- Scope Selection: Business Unit & Branch -->
                                                  <div class="row g-3 mb-4 border-bottom pb-4">
                                                      <!-- Business Unit -->
                                                      <div class="col-md-6 col-12" id="div_business_unit">
                                                          <x-ui.odoo-form-ui type="select" label="Business Unit (Optional)" name="business_unit_id" id="sel_business_unit_id" onchange="loadAttendanceRulesForScope()">
                                                              <option value="">All Business Units</option>
                                                              @foreach($businessUnits as $bu)
                                                                  <option value="{{ $bu->id }}" data-company="{{ $bu->company_id }}" {{ request('business_unit_id') == $bu->id ? 'selected' : '' }}>{{ $bu->name }}</option>
                                                              @endforeach
                                                          </x-ui.odoo-form-ui>
                                                      </div>
                                                      <!-- Branch -->
                                                      <div class="col-md-6 col-12" id="div_branch">
                                                          <x-ui.odoo-form-ui type="select" label="Branch (Optional)" name="branch_id" id="sel_branch_id" onchange="loadAttendanceRulesForScope()">
                                                              <option value="">All Branches</option>
                                                              @foreach($branches as $branch)
                                                                  <option value="{{ $branch->id }}" data-company="{{ $branch->company_id }}" data-bu="{{ $branch->business_unit_id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                                              @endforeach
                                                          </x-ui.odoo-form-ui>
                                                      </div>
                                                  </div>

                                                  <!-- Office Rules -->
                                                  <div class="col-12 border-bottom pb-4 mb-4">
                                                      <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 14px; letter-spacing: 0.25px;">
                                                          <i class="feather-home text-primary fs-16"></i> Office Settings
                                                      </h6>
                                                      <span class="text-muted fs-11 d-block mb-3">Define how employees working in the office can check in and out. Note: Checks will lookup employee's Legal Entity (Company), and optional Business Unit and Branch.</span>
                                                      
                                                      <div class="d-flex flex-column gap-3 px-2">
                                                          <div>
                                                              <x-ui.checkbox name="office_biometric" id="office_biometric" label="Enable Biometric Device Check-In" />
                                                              <span class="text-muted fs-11 d-block ms-4 ps-1">Allow check-in and check-out logs to sync from biometric devices.</span>
                                                          </div>
                                                          <div>
                                                              <x-ui.checkbox name="office_web" id="office_web" label="Enable Web/Mobile App Check-In" onchange="toggleOfficeGeofenceFields()" />
                                                              <span class="text-muted fs-11 d-block ms-4 ps-1">Allow employees to check in/out via the web portal or mobile app.</span>

                                                              {{-- Sub-options shown when office_web is enabled --}}
                                                              <div class="ms-4 ps-1 mt-3 d-none d-flex flex-column gap-3" id="office_geofence_fields">

                                                                  {{-- Geofence toggle --}}
                                                                  <div>
                                                                      <x-ui.checkbox name="office_geofence" id="office_geofence" label="Require Location Coordinate Capture" onchange="toggleOfficeCoordinateFields()" />
                                                                      <span class="text-muted fs-11 d-block ms-4 ps-1">Only allow check-in when employee is within the office geofence radius.</span>

                                                                      {{-- Lat/Lng/Radius shown when office_geofence is enabled --}}
                                                                      <div class="row g-3 mt-1 align-items-end d-none" id="office_coordinate_fields">
                                                                          <div class="col-md-3">
                                                                              <label class="form-label fs-12 text-muted mb-1">Office Latitude</label>
                                                                              <input type="text" name="office_latitude" id="office_latitude" class="form-control fs-12" placeholder="e.g. 37.7749">
                                                                          </div>
                                                                          <div class="col-md-3">
                                                                              <label class="form-label fs-12 text-muted mb-1">Office Longitude</label>
                                                                              <input type="text" name="office_longitude" id="office_longitude" class="form-control fs-12" placeholder="e.g. -122.4194">
                                                                          </div>
                                                                          <div class="col-md-3">
                                                                              <label class="form-label fs-12 text-muted mb-1">Allowed Radius (Meters)</label>
                                                                              <input type="number" name="office_radius" id="office_radius" class="form-control fs-12" value="100" min="1">
                                                                          </div>
                                                                          <div class="col-md-3">
                                                                              <button type="button" class="btn btn-sm btn-soft-primary w-100 d-flex align-items-center justify-content-center gap-1" style="height: 38px; border-radius: 4px;" onclick="detectCurrentCoordinates(event)">
                                                                                  <i class="feather-map-pin"></i> Detect Location
                                                                              </button>
                                                                          </div>
                                                                      </div>
                                                                  </div>

                                                                  {{-- Live tracking toggle --}}
                                                                  <div>
                                                                      <x-ui.checkbox name="office_tracking" id="office_tracking" label="Enable Live Location Tracking During Shift" onchange="toggleOfficeTrackingMinutes()" />
                                                                      <span class="text-muted fs-11 d-block ms-4 ps-1">Periodically log employee GPS coordinates while checked in at the office.</span>
                                                                      <div class="ms-4 ps-1 mt-2 d-none" id="office_tracking_minutes_wrap">
                                                                          <label class="form-label fs-12 text-muted mb-1">Tracking Interval (Minutes)</label>
                                                                          <input type="number" name="office_tracking_minutes" id="office_tracking_minutes" class="form-control fs-12" style="max-width: 160px;" value="15" min="1" max="120" placeholder="e.g. 15">
                                                                          <span class="text-muted fs-11 d-block mt-1">Location will be recorded every N minutes during an active shift.</span>
                                                                      </div>
                                                                  </div>

                                                              </div>
                                                          </div>
                                                      </div>
                                                 </div>

                                                  <!-- WFH Rules -->
                                                  <div class="col-12 border-bottom pb-4 mb-4">
                                                      <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 14px; letter-spacing: 0.25px;">
                                                          <i class="feather-rss text-primary fs-16"></i> WFH Settings
                                                      </h6>
                                                      <span class="text-muted fs-11 d-block mb-3">Configure check-in methods, validations, and location tracking for remote employees. Note: Validation methods are uniform on check-in and check-out.</span>
                                                      
                                                      <div class="d-flex flex-column gap-3 px-2">
                                                          <div>
                                                              <x-ui.checkbox name="wfh_location" id="wfh_location" label="Require Location Coordinate Capture" />
                                                              <span class="text-muted fs-11 d-block ms-4 ps-1">Record GPS coordinates when checking in or out.</span>
                                                          </div>
                                                          <div>
                                                              <x-ui.checkbox name="wfh_selfie" id="wfh_selfie" label="Require Selfie Capture" />
                                                              <span class="text-muted fs-11 d-block ms-4 ps-1">Mandate employee to snap a selfie photo on check-in and check-out.</span>
                                                          </div>
                                                          <div>
                                                              <x-ui.checkbox name="wfh_geofence" id="wfh_geofence" label="Enforce Home Location Geofence (Strict)" />
                                                              <span class="text-muted fs-11 d-block ms-4 ps-1">If checked, they must check-in from their home location (tracking anchor is assigned location). If unchecked, home location is optional (tracking anchor is first check-in location).</span>
                                                          </div>
                                                          <div>
                                                              <x-ui.checkbox name="wfh_tracking" id="wfh_tracking" label="Enable Live Location Tracking during Shift" onchange="toggleTrackingThreshold('wfh')" />
                                                              <span class="text-muted fs-11 d-block ms-4 ps-1">Track and record location updates in the background. Note: Live movement tracking requires this to be enabled.</span>
                                                          </div>
                                                          <div class="alert bg-light border-0 p-3 m-0 rounded-3 text-dark fs-13 d-none" id="wfh_tracking_meters_container">
                                                              <div class="d-flex align-items-center gap-2">
                                                                  <i class="feather-map-pin text-primary fs-16"></i>
                                                                  <p class="mb-0 text-dark" style="line-height: 1.6;">
                                                                      Track new coordinates if the employee moves more than 
                                                                      <input type="number" name="wfh_tracking_meters" id="wfh_tracking_meters" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="50" min="1" style="width: 60px; height: 24px; font-weight: 600; vertical-align: middle; border-bottom: 1px solid #cbd5e1 !important;">
                                                                      meters from the last/first check-in coordinates, and fetch every
                                                                      <input type="number" name="wfh_tracking_minutes" id="wfh_tracking_minutes" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="15" min="1" max="120" style="width: 55px; height: 24px; font-weight: 600; vertical-align: middle; border-bottom: 1px solid #cbd5e1 !important;">
                                                                      minutes.
                                                                  </p>
                                                              </div>
                                                          </div>
                                                      </div>
                                                  </div>

                                                  <!-- On-Site Rules -->
                                                  <div class="col-12 mb-2">
                                                      <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 14px; letter-spacing: 0.25px;">
                                                          <i class="feather-map text-primary fs-16"></i> On-Site Settings
                                                      </h6>
                                                      <span class="text-muted fs-11 d-block mb-3">Configure check-in methods, validations, and location tracking for client-site deployments. Note: Validation methods are uniform on check-in and check-out.</span>
                                                      
                                                      <div class="d-flex flex-column gap-3 px-2">
                                                          <div>
                                                              <x-ui.checkbox name="site_location" id="site_location" label="Require Location Coordinate Capture" />
                                                              <span class="text-muted fs-11 d-block ms-4 ps-1">Record GPS coordinates when checking in or out.</span>
                                                          </div>
                                                          <div>
                                                              <x-ui.checkbox name="site_selfie" id="site_selfie" label="Require Selfie Capture" />
                                                              <span class="text-muted fs-11 d-block ms-4 ps-1">Mandate employee to snap a selfie photo on check-in and check-out.</span>
                                                          </div>
                                                          <div>
                                                              <x-ui.checkbox name="site_tracking" id="site_tracking" label="Enable Live Location Tracking during Shift" onchange="toggleTrackingThreshold('site')" />
                                                              <span class="text-muted fs-11 d-block ms-4 ps-1">Track and record location updates in the background. Note: Live movement tracking requires this to be enabled.</span>
                                                          </div>
                                                          <div class="alert bg-light border-0 p-3 m-0 rounded-3 text-dark fs-13 d-none" id="site_tracking_meters_container">
                                                              <div class="d-flex align-items-center gap-2">
                                                                  <i class="feather-map-pin text-primary fs-16"></i>
                                                                  <p class="mb-0 text-dark" style="line-height: 1.6;">
                                                                      Track new coordinates if the employee moves more than 
                                                                      <input type="number" name="site_tracking_meters" id="site_tracking_meters" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="50" min="1" style="width: 60px; height: 24px; font-weight: 600; vertical-align: middle; border-bottom: 1px solid #cbd5e1 !important;">
                                                                      meters from the last/first check-in coordinates, and fetch every
                                                                      <input type="number" name="site_tracking_minutes" id="site_tracking_minutes" class="odoo-table-input d-inline-block text-center px-1 mx-1" value="15" min="1" max="120" style="width: 55px; height: 24px; font-weight: 600; vertical-align: middle; border-bottom: 1px solid #cbd5e1 !important;">
                                                                      minutes.
                                                                  </p>
                                                              </div>
                                                          </div>
                                                      </div>
                                                  </div>
                                              @endif
                                          </div>

                                         <!-- Save Footer Button -->
                                         <div class="border-top pt-3 d-flex justify-content-end">
                                             <x-ui.button type="submit" variant="primary" icon="feather-save">
                                                 {{ $typeKey === 'attendance_rules' ? 'Save Rules' : __('hrms.penalization.save_policy') }}
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

    @push('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Close Select2 dropdowns when any parent scrollable container scrolls
            document.addEventListener('scroll', function(e) {
                if (e.target && e.target.classList && e.target.classList.contains('table-responsive')) {
                    $('.tier-action-select, .tier-leave-select').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('close');
                        }
                    });
                }
            }, true); // Use capture phase because scroll events do not bubble

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
                let valueReadonly = (penalty_action === 'no_deduction') ? 'readonly' : '';

                let rowHtml = `
                    <tr class="tier-row" data-index="${tierIndex}">
                        <td>
                            <input type="number" name="penalty_tiers[${tierIndex}][min_occurrence]" class="odoo-table-input min-occ" min="1" value="${min_occ}" required>
                        </td>
                        <td>
                            <input type="number" name="penalty_tiers[${tierIndex}][max_occurrence]" class="odoo-table-input max-occ" min="1" value="${max_occ !== null ? max_occ : ''}" placeholder="{{ __('hrms.penalization.unlimited') }}">
                        </td>
                        <td>
                            <select name="penalty_tiers[${tierIndex}][penalty_action]" class="odoo-table-select tier-action-select" required>
                                <option value="no_deduction" ${penalty_action === 'no_deduction' ? 'selected' : ''}>No Deduction</option>
                                <option value="salary_deduction" ${penalty_action === 'salary_deduction' ? 'selected' : ''}>Deduct Salary (Loss of Pay)</option>
                                <option value="working_hour_deduction" ${penalty_action === 'working_hour_deduction' ? 'selected' : ''}>Deduct Working Day</option>
                                <option value="both_deductions" ${penalty_action === 'both_deductions' ? 'selected' : ''}>Both (Salary & Working Day)</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" step="any" name="penalty_tiers[${tierIndex}][penalty_value]" class="odoo-table-input tier-value-input" min="0" value="${penalty_value}" ${valueReadonly} required>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-soft-danger btn-remove-tier"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>
                `;

                let row = $(rowHtml);
                tbody.append(row);
                
                row.find('.tier-action-select').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $(document.body)
                });

                tierIndex++;
            }

            // Pre-populate late arrival tiers
            let savedLateTiers = @json($savedLateTiers);
            if (savedLateTiers && Array.isArray(savedLateTiers) && savedLateTiers.length > 0) {
                savedLateTiers.forEach(t => {
                    addTierRow(t.min_occurrence, t.max_occurrence, t.penalty_action, t.penalty_value, t.leave_type_id);
                });
            } else {
                addTierRow(1, 3, 'no_deduction', 0, '');
                addTierRow(4, 5, 'salary_deduction', 1, '');
                addTierRow(6, '', 'salary_deduction', 2, '');
            }

            // Add Tier Button Click handler
            $(document).on('click', '#btn-add-tier', function() {
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
                if ($('#late-arrival-tiers-tbody tr').length === 0) {
                    addTierRow(1, '', 'no_deduction', 0, '');
                }
            });

            // Work Hours Deficit Tiers Builder Logic
            let deficitTierIndex = 0;

            function addDeficitTierRow(hours_threshold = '', penalty_action = 'no_deduction', penalty_value = 0, leave_type_id = '') {
                let tbody = $('#under-hours-tiers-tbody');
                let valueReadonly = (penalty_action === 'no_deduction') ? 'readonly' : '';

                let rowHtml = `
                    <tr class="deficit-tier-row" data-index="${deficitTierIndex}">
                        <td>
                            <input type="number" step="0.1" name="penalty_tiers[${deficitTierIndex}][hours_threshold]" class="odoo-table-input hours-threshold" min="0" max="24" value="${hours_threshold}" required>
                        </td>
                        <td>
                            <select name="penalty_tiers[${deficitTierIndex}][penalty_action]" class="odoo-table-select tier-action-select" required>
                                <option value="no_deduction" ${penalty_action === 'no_deduction' ? 'selected' : ''}>No Deduction</option>
                                <option value="salary_deduction" ${penalty_action === 'salary_deduction' ? 'selected' : ''}>Deduct Salary (Loss of Pay)</option>
                                <option value="working_hour_deduction" ${penalty_action === 'working_hour_deduction' ? 'selected' : ''}>Deduct Working Day</option>
                                <option value="both_deductions" ${penalty_action === 'both_deductions' ? 'selected' : ''}>Both (Salary & Working Day)</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" step="any" name="penalty_tiers[${deficitTierIndex}][penalty_value]" class="odoo-table-input tier-value-input" min="0" value="${penalty_value}" ${valueReadonly} required>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-soft-danger btn-remove-deficit-tier"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>
                `;

                let row = $(rowHtml);
                tbody.append(row);
                
                row.find('.tier-action-select').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $(document.body)
                });

                deficitTierIndex++;
            }

            // Pre-populate deficit tiers
            let savedDeficitTiers = @json($savedDeficitTiers);
            if (savedDeficitTiers && Array.isArray(savedDeficitTiers) && savedDeficitTiers.length > 0) {
                savedDeficitTiers.forEach(t => {
                    addDeficitTierRow(t.hours_threshold, t.penalty_action, t.penalty_value, t.leave_type_id);
                });
            } else {
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
                if ($('#under-hours-tiers-tbody tr').length === 0) {
                    addDeficitTierRow(6, 'salary_deduction', 1, '');
                }
            });

            // Missing Logs Tiers Builder Logic
            let missingTierIndex = 0;

            function addMissingTierRow(min_occ = '', max_occ = '', penalty_action = 'no_deduction', penalty_value = 0, leave_type_id = '') {
                let tbody = $('#missing-logs-tiers-tbody');
                let valueReadonly = (penalty_action === 'no_deduction') ? 'readonly' : '';

                let rowHtml = `
                    <tr class="missing-tier-row" data-index="${missingTierIndex}">
                        <td>
                            <input type="number" name="penalty_tiers[${missingTierIndex}][min_occurrence]" class="odoo-table-input missing-min-occ" min="1" value="${min_occ}" required>
                        </td>
                        <td>
                            <input type="number" name="penalty_tiers[${missingTierIndex}][max_occurrence]" class="odoo-table-input missing-max-occ" min="1" value="${max_occ !== null ? max_occ : ''}" placeholder="{{ __('hrms.penalization.unlimited') }}">
                        </td>
                        <td>
                            <select name="penalty_tiers[${missingTierIndex}][penalty_action]" class="odoo-table-select tier-action-select" required>
                                <option value="no_deduction" ${penalty_action === 'no_deduction' ? 'selected' : ''}>No Deduction</option>
                                <option value="salary_deduction" ${penalty_action === 'salary_deduction' ? 'selected' : ''}>Deduct Salary (Loss of Pay)</option>
                                <option value="working_hour_deduction" ${penalty_action === 'working_hour_deduction' ? 'selected' : ''}>Deduct Working Day</option>
                                <option value="both_deductions" ${penalty_action === 'both_deductions' ? 'selected' : ''}>Both (Salary & Working Day)</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" step="any" name="penalty_tiers[${missingTierIndex}][penalty_value]" class="odoo-table-input tier-value-input" min="0" value="${penalty_value}" ${valueReadonly} required>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-soft-danger btn-remove-missing-tier"><i class="feather-trash-2"></i></button>
                        </td>
                    </tr>
                `;

                let row = $(rowHtml);
                tbody.append(row);
                
                row.find('.tier-action-select').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $(document.body)
                });

                missingTierIndex++;
            }

            // Pre-populate missing logs tiers
            let savedMissingTiers = @json($savedMissingTiers);
            if (savedMissingTiers && Array.isArray(savedMissingTiers) && savedMissingTiers.length > 0) {
                savedMissingTiers.forEach(t => {
                    addMissingTierRow(t.min_occurrence, t.max_occurrence, t.penalty_action, t.penalty_value, t.leave_type_id);
                });
            } else {
                addMissingTierRow(1, 3, 'no_deduction', 0, '');
                addMissingTierRow(4, 5, 'salary_deduction', 0.5, '');
                addMissingTierRow(6, '', 'salary_deduction', 1, '');
            }

            // Add Missing Tier Button Click handler
            $(document).on('click', '#btn-add-missing-tier', function() {
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
                if ($('#missing-logs-tiers-tbody tr').length === 0) {
                    addMissingTierRow(1, '', 'no_deduction', 0, '');
                }
            });

            // Handle action select changes dynamically
            $(document).on('change', '.tier-action-select', function() {
                let select = $(this);
                let row = select.closest('tr');
                let valueInput = row.find('.tier-value-input');

                if (select.val() === 'no_deduction') {
                    valueInput.val(0).prop('readonly', true);
                } else {
                    valueInput.prop('readonly', false);
                }
            });

            // Initial load visibility checks
            initPolicyPaneSelects('.policy-details-pane:not(.d-none)');
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

        window.toggleTrackingThreshold = function(mode) {
            var checkbox = document.getElementById(mode + '_tracking');
            var container = document.getElementById(mode + '_tracking_meters_container');
            if (checkbox && container) {
                if (checkbox.checked) {
                    container.classList.remove('d-none');
                } else {
                    container.classList.add('d-none');
                }
            }
        };

        window.toggleOfficeGeofenceFields = function() {
            var checkbox = document.getElementById('office_web');
            var container = document.getElementById('office_geofence_fields');
            if (checkbox && container) {
                if (checkbox.checked) {
                    container.classList.remove('d-none');
                } else {
                    container.classList.add('d-none');
                }
            }
        };

        window.loadAttendanceRulesForScope = function() {
            if (typeof isUpdatingScope !== 'undefined' && isUpdatingScope) return;
            var companyId = document.getElementById('sel_company_id')?.value;
            var buId = document.getElementById('sel_business_unit_id')?.value;
            var branchId = document.getElementById('sel_branch_id')?.value;

            if (!companyId) return;

            $.ajax({
                url: "{{ route('hrms.attendance-rules.query') }}",
                type: 'GET',
                data: {
                    company_id: companyId,
                    business_unit_id: buId,
                    branch_id: branchId
                },
                success: function(rule) {
                    if (rule) {
                        document.getElementById('office_biometric').checked = !!rule.office_biometric;
                        document.getElementById('office_web').checked = !!rule.office_web;
                        document.getElementById('office_geofence').checked = !!rule.office_geofence;
                        document.getElementById('office_latitude').value = rule.office_latitude || '';
                        document.getElementById('office_longitude').value = rule.office_longitude || '';
                        document.getElementById('office_radius').value = rule.office_radius || 100;
                        document.getElementById('office_tracking').checked = !!rule.office_tracking;
                        document.getElementById('office_tracking_minutes').value = rule.office_tracking_minutes || 15;

                        document.getElementById('wfh_location').checked = !!rule.wfh_location;
                        document.getElementById('wfh_selfie').checked = !!rule.wfh_selfie;
                        document.getElementById('wfh_geofence').checked = !!rule.wfh_geofence;
                        document.getElementById('wfh_tracking').checked = !!rule.wfh_tracking;
                        document.getElementById('wfh_tracking_meters').value = rule.wfh_tracking_meters || 50;
                        document.getElementById('wfh_tracking_minutes').value = rule.wfh_tracking_minutes || 15;

                        document.getElementById('site_location').checked = !!rule.site_location;
                        document.getElementById('site_selfie').checked = !!rule.site_selfie;
                        document.getElementById('site_geofence').checked = !!rule.site_geofence;
                        document.getElementById('site_tracking').checked = !!rule.site_tracking;
                        document.getElementById('site_tracking_meters').value = rule.site_tracking_meters || 50;
                        document.getElementById('site_tracking_minutes').value = rule.site_tracking_minutes || 15;

                        if ($('#sel_status').length) {
                            var statusVal = rule.status ? '1' : '0';
                            $('#sel_status').val(statusVal).trigger('change.select2');
                        }
                    } else {
                        // Reset to defaults
                        document.getElementById('office_biometric').checked = false;
                        document.getElementById('office_web').checked = false;
                        document.getElementById('office_geofence').checked = false;
                        document.getElementById('office_latitude').value = '';
                        document.getElementById('office_longitude').value = '';
                        document.getElementById('office_radius').value = 100;
                        document.getElementById('office_tracking').checked = false;
                        document.getElementById('office_tracking_minutes').value = 15;

                        document.getElementById('wfh_location').checked = false;
                        document.getElementById('wfh_selfie').checked = false;
                        document.getElementById('wfh_geofence').checked = false;
                        document.getElementById('wfh_tracking').checked = false;
                        document.getElementById('wfh_tracking_meters').value = 50;
                        document.getElementById('wfh_tracking_minutes').value = 15;

                        document.getElementById('site_location').checked = false;
                        document.getElementById('site_selfie').checked = false;
                        document.getElementById('site_geofence').checked = false;
                        document.getElementById('site_tracking').checked = false;
                        document.getElementById('site_tracking_meters').value = 50;
                        document.getElementById('site_tracking_minutes').value = 15;

                        if ($('#sel_status').length) {
                            $('#sel_status').val('1').trigger('change.select2');
                        }
                    }
                    toggleTrackingThreshold('wfh');
                    toggleTrackingThreshold('site');
                    toggleOfficeGeofenceFields();
                    toggleOfficeCoordinateFields();
                    toggleOfficeTrackingMinutes();
                }
            });
        };

        // Toggle the tracking minutes input for Office location tracking
        window.toggleOfficeTrackingMinutes = function() {
            const enabled = document.getElementById('office_tracking').checked;
            const wrap = document.getElementById('office_tracking_minutes_wrap');
            if (wrap) wrap.classList.toggle('d-none', !enabled);
        };

        // Toggle the coordinate fields when office_geofence checkbox changes
        window.toggleOfficeCoordinateFields = function() {
            const enabled = document.getElementById('office_geofence') && document.getElementById('office_geofence').checked;
            const fields = document.getElementById('office_coordinate_fields');
            if (fields) fields.classList.toggle('d-none', !enabled);
        };

        // Toggle sub-options (geofence + tracking) when office_web checkbox changes
        window.toggleOfficeGeofenceFields = function() {
            const enabled = document.getElementById('office_web').checked;
            const panel = document.getElementById('office_geofence_fields');
            if (panel) {
                panel.classList.toggle('d-none', !enabled);
                // If web check-in gets disabled, reset sub-options
                if (!enabled) {
                    const geofence = document.getElementById('office_geofence');
                    if (geofence) geofence.checked = false;
                    toggleOfficeCoordinateFields();
                    const tracking = document.getElementById('office_tracking');
                    if (tracking) tracking.checked = false;
                    toggleOfficeTrackingMinutes();
                }
            }
        };

        // Geolocation Coordinates Detection Logic
        window.detectCurrentCoordinates = function(event) {
            if (!navigator.geolocation) {
                showAppToast('error', 'Geolocation is not supported by your browser.');
                return;
            }
            
            var $btn = $(event.currentTarget || 'button[onclick^="detectCurrentCoordinates"]');
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="feather-loader animate-spin me-1"></i> Detecting...');

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    $('#office_latitude').val(position.coords.latitude.toFixed(6));
                    $('#office_longitude').val(position.coords.longitude.toFixed(6));
                    $btn.prop('disabled', false).html(originalText);
                    showAppToast('success', 'Location coordinates detected successfully!');
                },
                function(error) {
                    $btn.prop('disabled', false).html(originalText);
                    var msg = 'Unable to retrieve location.';
                    if (error.code === error.PERMISSION_DENIED) {
                        msg = 'Permission denied. Please allow location access in your browser settings.';
                    } else if (error.code === error.POSITION_UNAVAILABLE) {
                        msg = 'Location position unavailable.';
                    } else if (error.code === error.TIMEOUT) {
                        msg = 'Location detection request timed out.';
                    }
                    showAppToast('error', msg);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 8000,
                    maximumAge: 0
                }
            );
        };

        // Dynamic Scope Dropdown Filtering Logic
        var originalBus = [];
        var originalBranches = [];
        var isUpdatingScope = false;

        function updateScopeSelectors() {
            if (isUpdatingScope) return;
            isUpdatingScope = true;

            var companyId = $('#sel_company_id').val();
            var $buSelect = $('#sel_business_unit_id');
            var $branchSelect = $('#sel_branch_id');

            // 1. If Company is not selected ("All Companies"), hide both fields and reset their values
            if (!companyId) {
                $('#div_business_unit').addClass('d-none');
                $('#div_branch').addClass('d-none');
                $buSelect.val('').trigger('change.select2');
                $branchSelect.val('').trigger('change.select2');
                isUpdatingScope = false;
                return;
            }

            // 2. Determine which Business Units belong to this Company
            var filteredBUs = originalBus.filter(function(bu) {
                return bu.company == companyId;
            });
            var hasBUs = filteredBUs.length > 0;

            // 3. Determine which Branches belong to this Company
            var filteredBranchesForCompany = originalBranches.filter(function(b) {
                return b.company == companyId;
            });
            var hasBranchesForCompany = filteredBranchesForCompany.length > 0;

            // Save current values to restore if still valid
            var currentBu = $buSelect.val();
            var currentBranch = $branchSelect.val();

            if (hasBUs) {
                // Show Business Unit field
                $('#div_business_unit').removeClass('d-none');

                // Rebuild Business Unit select options
                $buSelect.empty().append('<option value="">All Business Units</option>');
                filteredBUs.forEach(function(bu) {
                    var selectedAttr = (currentBu == bu.id) ? ' selected' : '';
                    $buSelect.append('<option value="' + bu.id + '" data-company="' + bu.company + '"' + selectedAttr + '>' + bu.text + '</option>');
                });
                
                // Re-initialize select2
                if ($buSelect.hasClass('select2-hidden-accessible')) {
                    $buSelect.select2('destroy');
                }
                $buSelect.select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });

                // Get resolved Business Unit
                var activeBu = $buSelect.val();

                if (activeBu) {
                    // Filter branches for this particular Business Unit
                    var filteredBranchesForBu = originalBranches.filter(function(b) {
                        return b.company == companyId && b.bu == activeBu;
                    });
                    var hasBranchesForBu = filteredBranchesForBu.length > 0;

                    if (hasBranchesForBu) {
                        $('#div_branch').removeClass('d-none');
                        $branchSelect.empty().append('<option value="">All Branches</option>');
                        filteredBranchesForBu.forEach(function(b) {
                            var selectedAttr = (currentBranch == b.id) ? ' selected' : '';
                            $branchSelect.append('<option value="' + b.id + '" data-company="' + b.company + '" data-bu="' + b.bu + '"' + selectedAttr + '>' + b.text + '</option>');
                        });

                        if ($branchSelect.hasClass('select2-hidden-accessible')) {
                            $branchSelect.select2('destroy');
                        }
                        $branchSelect.select2({
                            theme: 'bootstrap-5',
                            width: '100%'
                        });
                    } else {
                        $('#div_branch').addClass('d-none');
                        $branchSelect.val('').trigger('change.select2');
                    }
                } else {
                    // No BU selected -> hide branch
                    $('#div_branch').addClass('d-none');
                    $branchSelect.val('').trigger('change.select2');
                }
            } else {
                // No Business Units exist for this Company.
                $('#div_business_unit').addClass('d-none');
                $buSelect.val('').trigger('change.select2');

                // Check if there are branches directly belonging to this company
                if (hasBranchesForCompany) {
                    // Show Branch selector directly, skipping Business Unit
                    $('#div_branch').removeClass('d-none');
                    $branchSelect.empty().append('<option value="">All Branches</option>');
                    filteredBranchesForCompany.forEach(function(b) {
                        var selectedAttr = (currentBranch == b.id) ? ' selected' : '';
                        $branchSelect.append('<option value="' + b.id + '" data-company="' + b.company + '" data-bu="' + b.bu + '"' + selectedAttr + '>' + b.text + '</option>');
                    });

                    if ($branchSelect.hasClass('select2-hidden-accessible')) {
                        $branchSelect.select2('destroy');
                    }
                    $branchSelect.select2({
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                } else {
                    // No business units AND no branches
                    $('#div_branch').addClass('d-none');
                    $branchSelect.val('').trigger('change.select2');
                }
            }

            isUpdatingScope = false;
        }

        // Trigger loading when entering tab
        $(document).on('click', '.policy-switch-btn', function() {
            var policyType = $(this).attr('data-policy-type');
            if (policyType === 'attendance_rules') {
                setTimeout(function() {
                    updateScopeSelectors();
                    loadAttendanceRulesForScope();
                }, 100);
            }
        });

        // Initialize on DOM load if active
        document.addEventListener('DOMContentLoaded', function() {
            // Store original options on load
            $('#sel_business_unit_id option').each(function() {
                var $opt = $(this);
                if ($opt.val()) {
                    originalBus.push({
                        id: $opt.val(),
                        text: $opt.text(),
                        company: $opt.data('company')
                    });
                }
            });

            $('#sel_branch_id option').each(function() {
                var $opt = $(this);
                if ($opt.val()) {
                    originalBranches.push({
                        id: $opt.val(),
                        text: $opt.text(),
                        company: $opt.data('company'),
                        bu: $opt.data('bu')
                    });
                }
            });

            // Perform initial update of selectors
            updateScopeSelectors();

            var activeBtn = $('.policy-switch-btn.active');
            if (activeBtn.attr('data-policy-type') === 'attendance_rules') {
                loadAttendanceRulesForScope();
            }

            $(document).on('change', '#sel_company_id', function() {
                if (typeof isUpdatingScope !== 'undefined' && isUpdatingScope) return;
                updateScopeSelectors();
                loadAttendanceRulesForScope();
            });

            $(document).on('change', '#sel_business_unit_id', function() {
                if (typeof isUpdatingScope !== 'undefined' && isUpdatingScope) return;
                updateScopeSelectors();
                loadAttendanceRulesForScope();
            });

            // Redirect and reload when changing company scope for non-ajax tabs
            $(document).on('change', 'select[id^="company_id_"]', function() {
                var companyId = $(this).val() || '';
                var policyType = $(this).attr('id').replace('company_id_', '');
                window.location.href = "{{ route('hrms.penalization-policy.index') }}?policy_type=" + policyType + "&company_id=" + companyId;
            });
        });
    </script>
    @endpush
@endsection
