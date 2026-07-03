@extends('layouts.duralux')

@section('title', 'PENALIZATION POLICY SETTINGS | SaaS ERP')
@section('page-title', 'Penalization Policies')
@section('breadcrumb', 'HRMS / Penalization Policy Settings')

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
        }
        .policy-item.active {
            background-color: #f1f5f9 !important;
            border-left-color: var(--bs-primary) !important;
        }
        .policy-item:hover:not(.active) {
            background-color: #f8fafc !important;
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
                        <div class="col-md-4 col-12 border-end">
                            <div class="list-group list-group-flush rounded-0" style="min-height: 400px;">
                                @php
                                    $policyTypes = [
                                        'no_attendance' => ['Absence (No Attendance)', 'feather-user-x'],
                                        'late_arrival' => ['Late Arrival', 'feather-clock'],
                                        'under_hours' => ['Work Hours Deficit', 'feather-trending-down'],
                                        'missing_logs' => ['Missing Check-in/out Logs', 'feather-alert-triangle']
                                    ];
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
                                            <span class="fw-bold {{ $isActive ? 'text-primary' : 'text-dark' }}" style="font-size: 14px;">
                                                {{ $typeData[0] }}
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: SELECTED POLICY FORM DETAILED WORKSPACE -->
                        <div class="col-md-8 col-12">
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
                                                <span class="badge {{ $statusVal === '1' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }} px-2 py-1">
                                                    {{ $statusVal === '1' ? 'Active' : 'Inactive' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-4">
                                            <!-- Entity Scope -->
                                            <div class="col-md-6 col-12">
                                                <label class="form-label fw-bold">Company Scope</label>
                                                <select name="company_id" class="form-select">
                                                    <option value="">Apply Globally (All Entities)</option>
                                                    @foreach($companies as $company)
                                                        <option value="{{ $company->id }}" {{ ($rule && $rule->company_id === $company->id) ? 'selected' : '' }}>
                                                            {{ $company->company_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-6 col-12">
                                                <label class="form-label fw-bold">Policy Status <span class="text-danger">*</span></label>
                                                <select name="status" class="form-select" required>
                                                    <option value="1" {{ $statusVal === '1' ? 'selected' : '' }}>Active (Enforce Policy)</option>
                                                    <option value="0" {{ $statusVal === '0' ? 'selected' : '' }}>Inactive (Ignore Violations)</option>
                                                </select>
                                            </div>

                                            <!-- Type-Specific Parameters -->
                                            @if($typeKey === 'late_arrival')
                                                <!-- Grace Period -->
                                                <div class="col-md-6 col-12">
                                                    <label class="form-label fw-bold">Grace Period (Minutes) <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="number" name="grace_period_minutes" class="form-control" value="{{ $rule ? $rule->grace_period_minutes : 15 }}" min="0" required>
                                                        <span class="input-group-text bg-light">Minutes</span>
                                                    </div>
                                                    <small class="text-muted fs-11">Late arrival registered only after this duration.</small>
                                                </div>

                                                <!-- Occurrence Threshold -->
                                                <div class="col-md-6 col-12">
                                                    <label class="form-label fw-bold">Allowed Grace Counts (Per Month) <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="number" name="threshold_count" class="form-control" value="{{ $rule ? $rule->threshold_count : 3 }}" min="0" required>
                                                        <span class="input-group-text bg-light">Times</span>
                                                    </div>
                                                    <small class="text-muted fs-11">Count of allowed late arrivals before penalty starts.</small>
                                                </div>
                                            @endif

                                            @if($typeKey === 'under_hours')
                                                <!-- Shift Target Hours -->
                                                <div class="col-md-6 col-12">
                                                    <label class="form-label fw-bold">Required Shift Working Hours <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="number" name="grace_period_minutes" class="form-control" value="{{ $rule ? $rule->grace_period_minutes : 480 }}" min="0" required>
                                                        <span class="input-group-text bg-light">Minutes</span>
                                                    </div>
                                                    <small class="text-muted fs-11">Work hours target. e.g. 480 Minutes = 8 Hours.</small>
                                                </div>

                                                <!-- Occurrence Threshold -->
                                                <div class="col-md-6 col-12">
                                                    <label class="form-label fw-bold">Allowed Free Deficit Counts (Per Month) <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="number" name="threshold_count" class="form-control" value="{{ $rule ? $rule->threshold_count : 2 }}" min="0" required>
                                                        <span class="input-group-text bg-light">Times</span>
                                                    </div>
                                                    <small class="text-muted fs-11">Allowed times worker can log deficit hours before penalty.</small>
                                                </div>
                                            @endif

                                            <div class="col-12 border-top my-3 pt-3">
                                                <h6 class="fw-bold text-dark mb-3" style="font-size: 13.5px;"><i class="feather-dollar-sign text-primary me-2"></i>Deduction Details</h6>
                                            </div>

                                            <!-- Penalty Action Choice -->
                                            <div class="col-md-6 col-12">
                                                <label class="form-label fw-bold d-block">Penalty Settlement Method <span class="text-danger">*</span></label>
                                                <div class="d-flex gap-4 mt-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input action-toggle" type="radio" name="penalty_action" id="action_salary_{{ $typeKey }}" value="salary_deduction" {{ $action === 'salary_deduction' ? 'checked' : '' }} required>
                                                        <label class="form-check-label fw-semibold text-dark" for="action_salary_{{ $typeKey }}">
                                                            Loss of Pay (Salary)
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input action-toggle" type="radio" name="penalty_action" id="action_leave_{{ $typeKey }}" value="leave_deduction" {{ $action === 'leave_deduction' ? 'checked' : '' }} required>
                                                        <label class="form-check-label fw-semibold text-dark" for="action_leave_{{ $typeKey }}">
                                                            Deduct Leave Balance
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Penalty Amount / Rate -->
                                            <div class="col-md-6 col-12">
                                                <label class="form-label fw-bold">Deduction Value (Multiplier of Daily Rate) <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="number" step="0.1" name="penalty_value" class="form-control" value="{{ $val }}" min="0" required>
                                                    <span class="input-group-text bg-light">Day(s)</span>
                                                </div>
                                                <small class="text-muted fs-11">e.g., 0.5 to deduct half-day worth of leaves or salary per violation.</small>
                                            </div>

                                            <!-- Leave Type Dropdown Selector (Conditional) -->
                                            <div class="col-md-6 col-12 leave-select-container {{ $action === 'leave_deduction' ? '' : 'd-none' }}">
                                                <label class="form-label fw-bold">Deduct From Leave Type <span class="text-danger">*</span></label>
                                                <select name="leave_type_id" class="form-select leave-type-dropdown" {{ $action === 'leave_deduction' ? 'required' : '' }}>
                                                    <option value="">Select Leave Quota to Deduct</option>
                                                    @foreach($leaveTypes as $lType)
                                                        <option value="{{ $lType->id }}" {{ ($rule && $rule->leave_type_id === $lType->id) ? 'selected' : '' }}>
                                                            {{ $lType->name }} ({{ strtoupper($lType->code) }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted fs-11">Quota from which penalty days will be substracted.</small>
                                            </div>
                                        </div>

                                        <!-- Save Footer Button -->
                                        <div class="border-top pt-3 d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather-save me-2"></i> Save Policy Settings
                                            </button>
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
        });
    </script>
@endsection
