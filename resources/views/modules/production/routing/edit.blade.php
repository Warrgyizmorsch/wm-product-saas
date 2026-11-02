@extends('layouts.duralux')

@section('title', __('production.edit_routing') . ' | SaaS ERP')
@section('page-title', __('production.edit_routing'))
@section('breadcrumb', __('production.edit_routing'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .erp-thin-table td .select2-container--bootstrap-5 .select2-selection {
            min-height: 31px !important;
            height: 31px !important;
            padding: 2px 8px !important;
            font-size: 11px !important;
        }
        .routing-select2-dropdown {
            z-index: 2055 !important;
            font-size: 11px !important;
        }
        .routing-select2-dropdown .select2-results__options {
            max-height: 260px;
        }
        .routing-select2-dropdown .select2-search__field {
            font-size: 11px !important;
            padding: 4px 6px !important;
        }
        .routing-select2-dropdown .select2-results__option {
            padding: 5px 8px !important;
            font-size: 11px !important;
        }
        /* Select2 inside routing operation row */
        .routing-operation-row .select2-container--bootstrap-5 {
            width: 100% !important;
            display: block;
        }
        .routing-operation-row .select2-container--bootstrap-5 .select2-selection {
            min-height: 31px !important;
            height: 31px !important;
            padding: 2px 8px !important;
            font-size: 11px !important;
            border-radius: 4px !important;
            border: 1px solid #ced4da !important;
            background-color: #ffffff;
            display: flex;
            align-items: center;
        }
        .routing-operation-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 25px !important;
            font-size: 11px !important;
            padding-left: 0 !important;
            padding-right: 18px !important;
            color: #1e293b !important;
            font-weight: 500;
        }
        .routing-operation-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8 !important;
            font-size: 11px !important;
        }
        .routing-operation-row .select2-container--bootstrap-5.select2-container--disabled .select2-selection {
            background-color: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed !important;
        }
        .routing-operation-row .select2-container--bootstrap-5.select2-container--disabled .select2-selection__rendered,
        .routing-operation-row .select2-container--bootstrap-5.select2-container--disabled .select2-selection__placeholder {
            color: #94a3b8 !important;
            cursor: not-allowed !important;
        }
        /* Hide number input spinners and ensure clear visibility */
        .routing-operation-row input[type="number"]::-webkit-outer-spin-button,
        .routing-operation-row input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none !important;
            margin: 0 !important;
        }
        .routing-operation-row input[type="number"] {
            -moz-appearance: textfield !important;
            appearance: textfield !important;
        }
        .routing-operation-row td .odoo-table-input {
            padding-left: 6px !important;
            padding-right: 6px !important;
        }
        /* Inline sub-inputs for Yield, Queue Threshold, and Parallel */
        .routing-sub-input {
            height: 25px !important;
            min-height: 25px !important;
            background-color: #ffffff !important;
            border: 1px solid #ced4da !important;
            border-radius: 4px !important;
            font-size: 11px !important;
            font-weight: 500 !important;
            color: #1e293b !important;
            padding: 1px 4px !important;
            text-align: center !important;
            box-sizing: border-box !important;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
        }
        .routing-sub-input:focus {
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--bs-primary) 20%, transparent) !important;
            outline: none !important;
        }
        .routing-sub-input:disabled {
            background-color: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            color: #94a3b8 !important;
            cursor: not-allowed !important;
        }
        [x-cloak] {
            display: none !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <!-- Load Alpine.js for dynamic operations grid management -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')
    <div class="erp-single-panel">
        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="{{ __('production.validation_failed') }}: {{ $errors->first() }}" />
        @endif

        <form method="POST" action="{{ route('production.routing.update', $routing->id) }}" x-data="routingForm()">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.edit_routing') }}: {{ $routing->routing_number }}</h4>
                    <a href="{{ route('production.routing.index') }}" class="text-muted hover-danger fs-18" title="{{ __('production.cancel') }}">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Routing General Header -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" :label="__('production.routing_number')" name="routing_number" :value="old('routing_number', $routing->routing_number)" :readonly="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.routing_name')" name="name" :value="old('name', $routing->name)" :required="true" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.version')" name="version" :value="old('version', $routing->version)" :required="true" />

                        <div class="row g-2">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('production.start_date')" name="effective_from" inputType="date" :value="old('effective_from', $routing->effective_from?->format('Y-m-d'))" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('production.end_date') ?? 'End Date'" name="effective_to" inputType="date" :value="old('effective_to', $routing->effective_to?->format('Y-m-d'))" />
                            </div>
                        </div>

                        <input type="hidden" name="is_default" value="{{ $routing->is_default ? '1' : '0' }}">
                    </div>

                    <div class="col-12 border-top pt-3">
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.description')" name="description" placeholder="{{ __('production.description_placeholder') }}" rows="3">{{ old('description', $routing->description) }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Operations Dynamic Grid -->
                <div class="border-top pt-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold text-dark mb-0 fs-14">{{ __('production.routing_operations_sequence') }}</h5>
                        <div class="text-muted fs-11">
                            <span class="badge bg-soft-info text-info border border-info-subtle"><i class="feather-info me-1"></i>Resource Rule: Machine XOR Manual Batch</span>
                        </div>
                    </div>

                    <!-- Inline Warning Notice for Soft-Switching Resources -->
                    <template x-if="noticeMessage">
                        <div x-transition.duration.300ms class="alert alert-warning py-1.5 px-3 fs-12 mb-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <i class="feather-alert-triangle text-warning fs-14"></i>
                                <span x-text="noticeMessage" class="fw-medium text-dark"></span>
                            </div>
                            <button type="button" class="btn-close fs-10" @click="noticeMessage = ''"></button>
                        </div>
                    </template>
                    
                    <div class="table-responsive mb-3">
                        <x-ui.odoo-form-ui type="table" style="min-width: 1560px;">
                            <thead>
                                <tr>
                                    <th style="width: 45px; min-width: 45px;" class="text-center">{{ __('production.seq') }}</th>
                                    <th style="min-width: 260px;">{{ __('production.operation_name_yield') }}</th>
                                    <th style="width: 175px; min-width: 175px;">{{ __('production.work_center') }}</th>
                                    <th style="width: 175px; min-width: 175px;">Section</th>
                                    <th style="width: 185px; min-width: 185px;">{{ __('production.machine') }}</th>
                                    <th style="width: 185px; min-width: 185px;">Manual Batch</th>
                                    <th style="width: 85px; min-width: 85px;" class="text-end">{{ __('production.setup') }} (m)</th>
                                    <th style="width: 85px; min-width: 85px;" class="text-end">{{ __('production.run') }} (m)</th>
                                    <th style="width: 85px; min-width: 85px;" class="text-end" title="Buffer / wait / cure / cool-down time (minutes)">Wait (m)</th>
                                    <th style="width: 95px; min-width: 95px;" class="text-end" title="Calculated Cost Per Unit (Labor + Machine or Subcontract)">Cost / Unit</th>
                                    <th style="width: 45px; min-width: 45px;" class="text-center">{{ __('production.qc_gate') }}</th>
                                    <th style="width: 170px; min-width: 170px;">Type / Alt</th>
                                    <th style="width: 120px; min-width: 120px;" class="text-end">{{ __('production.actions') }}</th>
                                </tr>
                            </thead>
                            <template x-for="(operation, index) in operations" :key="operation.uid">
                                <tbody class="border-bottom">
                                    <tr class="routing-operation-row" :data-row-uid="operation.uid">
                                        <!-- Sequence -->
                                        <td class="align-middle text-center">
                                            <input type="number" x-bind:name="'operations['+index+'][sequence]'" class="odoo-table-input text-center font-monospace" x-model="operation.sequence" x-bind:readonly="autoSequence" required min="1" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][operation_type]'" :value="operation.is_external ? 'outsourcing' : (operation.quality_required ? 'inspection' : 'manufacturing')" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][row_uid]'" :value="operation.uid" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][is_alternative]'" :value="operation.is_alternative ? 1 : 0" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][replaces_row_uid]'" :value="operation.is_alternative ? (operation.replaces_row_uid || '') : ''" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][replaces_operation_id]'" :value="operation.is_alternative ? (operation.replaces_operation_id || '') : ''" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][priority]'" :value="operation.is_alternative ? (operation.priority || 1) : ''" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][labor_cost_rate]'" :value="operation.labor_cost_rate" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][machine_cost_rate]'" :value="operation.machine_cost_rate" />
                                        </td>
                                        
                                        <!-- Operation Name & Details -->
                                        <td class="align-middle">
                                            <input type="text" x-bind:name="'operations['+index+'][name]'" class="odoo-table-input fw-semibold" placeholder="{{ __('production.operation_placeholder') }}" x-model="operation.name" @input.debounce.300ms="refreshAllAltAndPredSelect2()" required />
                                            
                                            <div class="d-flex align-items-center gap-2 mt-1 fs-11 text-muted flex-wrap">
                                                <div class="form-check m-0 p-0 d-inline-flex align-items-center me-1">
                                                    <input type="checkbox" class="form-check-input mt-0 me-1 ms-0" x-model="operation.is_external" @change="onExternalToggle(operation)" x-bind:name="'operations['+index+'][is_external]'" x-bind:id="'ext_' + operation.uid" value="1">
                                                    <label class="form-check-label fs-11 text-secondary c-pointer mb-0" x-bind:for="'ext_' + operation.uid">{{ __('production.outsourced') }}</label>
                                                </div>

                                                <span class="text-black-50 me-1">|</span>

                                                <div class="d-inline-flex align-items-center gap-1 me-1">
                                                    <span class="fs-11 fw-semibold text-secondary">{{ __('production.yield') }}%:</span>
                                                    <input type="number" step="any" x-bind:name="'operations['+index+'][expected_yield_percentage]'" class="routing-sub-input text-center" style="width: 60px; min-width: 60px;" x-model="operation.expected_yield_percentage" min="0.01" max="100.00" required />
                                                </div>

                                                <span class="text-black-50 me-1">|</span>

                                                <div class="form-check m-0 p-0 d-inline-flex align-items-center me-1">
                                                    <input type="checkbox" class="form-check-input mt-0 me-1 ms-0" x-model="operation.queue_threshold_enabled" x-bind:name="'operations['+index+'][queue_threshold_enabled]'" x-bind:id="'ovl_' + operation.uid" value="1">
                                                    <label class="form-check-label fs-11 text-info fw-semibold c-pointer mb-0" x-bind:for="'ovl_' + operation.uid" title="Next machine starts when queue threshold is hit">Threshold</label>
                                                </div>

                                                <div class="align-items-center gap-1"
                                                         :class="(operation.queue_threshold_enabled == 1 || operation.queue_threshold_enabled === true || operation.overlap_enabled == 1 || operation.overlap_enabled === true) ? 'd-inline-flex' : 'd-none'"
                                                         x-show="operation.queue_threshold_enabled == 1 || operation.queue_threshold_enabled === true || operation.overlap_enabled == 1 || operation.overlap_enabled === true">
                                                    <span class="fs-11 fw-semibold text-secondary">Qty:</span>
                                                    <input type="number" step="any" x-bind:name="'operations['+index+'][transfer_batch_quantity]'" class="routing-sub-input text-center" style="width: 60px; min-width: 60px;" x-model="operation.transfer_batch_quantity" :min="(operation.queue_threshold_enabled == 1 || operation.queue_threshold_enabled === true || operation.overlap_enabled == 1 || operation.overlap_enabled === true) ? '0.0001' : '0'" :disabled="!operation.queue_threshold_enabled && !operation.overlap_enabled" />
                                                    <span class="fs-11 fw-semibold text-secondary ms-1" title="Transit / handling delay before the first transfer batch reaches the downstream operation.">Lag:</span>
                                                    <input type="number" step="1" x-bind:name="'operations['+index+'][transfer_lag_minutes]'" class="routing-sub-input text-center" style="width: 50px; min-width: 50px;" x-model="operation.transfer_lag_minutes" min="0" placeholder="0" title="Transit / handling delay in minutes" :disabled="!operation.queue_threshold_enabled && !operation.overlap_enabled" />
                                                </div>

                                                <span class="text-black-50 me-1">|</span>

                                                <div class="form-check m-0 p-0 d-inline-flex align-items-center me-1">
                                                    <input type="checkbox" class="form-check-input mt-0 me-1 ms-0" x-model="operation.is_parallel" x-bind:name="'operations['+index+'][is_parallel]'" x-bind:id="'par_' + operation.uid" value="1">
                                                    <label class="form-check-label fs-11 text-primary fw-semibold c-pointer mb-0" x-bind:for="'par_' + operation.uid" title="Independent / Parallel operation">Parallel</label>
                                                </div>

                                                <div class="align-items-center gap-1"
                                                         :class="(operation.is_parallel == 1 || operation.is_parallel === true) ? 'd-inline-flex' : 'd-none'"
                                                         x-show="operation.is_parallel == 1 || operation.is_parallel === true">
                                                    <span class="fs-11 fw-semibold text-secondary">Grp:</span>
                                                    <input type="text" x-bind:name="'operations['+index+'][parallel_group]'" class="routing-sub-input text-center" style="width: 55px; min-width: 55px;" x-model="operation.parallel_group" placeholder="A" title="Parallel Group Name" :disabled="!operation.is_parallel" />
                                                </div>
                                            </div>

                                            <div class="mt-1 d-flex align-items-center gap-2">
                                                <input type="text" x-bind:name="'operations['+index+'][description]'" class="odoo-table-input fs-11 text-muted flex-grow-1" placeholder="Brief description..." x-model="operation.description" />
                                                <button type="button" class="btn btn-xs py-0 px-1 fs-10 text-nowrap" :class="operation.instructions ? 'btn-soft-primary' : 'btn-outline-secondary'" @click="operation.showSop = !operation.showSop" title="Toggle Operation Instructions (SOP)">
                                                    <i class="feather-file-text me-1"></i>SOP
                                                </button>
                                            </div>
                                            <div x-show="operation.showSop" class="mt-1.5 p-2 bg-light rounded border" x-cloak>
                                                <label class="form-label fs-10 fw-bold text-dark text-uppercase mb-1"><i class="feather-file-text me-1 text-primary"></i>Operation Instructions (SOP)</label>
                                                <textarea x-bind:name="'operations['+index+'][instructions]'" class="odoo-table-input fs-11 text-muted bg-white" rows="2" placeholder="Detailed shop-floor SOP instructions for operators..." x-model="operation.instructions"></textarea>
                                            </div>

                                            <div class="mt-1.5 d-flex align-items-center gap-1.5" x-show="!operation.is_parallel && index > 0">
                                                <span class="fs-10 text-muted text-nowrap"><i class="feather-git-commit me-1"></i>Predecessor:</span>
                                                <div class="flex-grow-1" style="min-width: 170px;">
                                                    <select x-bind:name="'operations['+index+'][predecessor_sequence]'"
                                                        class="form-select form-select-sm fs-11 routing-pred-select"
                                                        :data-row-uid="operation.uid">
                                                        <option value="">-- Auto (Previous Op) --</option>
                                                        <option value="-1">-- None (Independent / Starts Immediately) --</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Work Center -->
                                        <td class="align-top" style="width: 175px; min-width: 175px;">
                                            <select x-bind:name="'operations['+index+'][work_center_id]'"
                                                class="form-select form-select-sm fs-11 routing-wc-select"
                                                :data-row-uid="operation.uid"
                                                x-bind:required="!operation.is_external"
                                                x-bind:disabled="operation.is_external">
                                                <option value="">-- Select WC --</option>
                                                @foreach ($workCenters as $wc)
                                                    <option value="{{ $wc->id }}" :selected="operation.work_center_id == {{ $wc->id }}">{{ $wc->name }} ({{ $wc->code }})</option>
                                                @endforeach
                                            </select>
                                            <template x-if="operation.is_external">
                                                <span class="badge bg-soft-secondary text-muted fs-10 mt-1.5 d-inline-block"><i class="feather-slash me-1"></i>N/A (Outsourced)</span>
                                            </template>
                                        </td>

                                        <!-- Section -->
                                        <td class="align-top" style="width: 175px; min-width: 175px;">
                                            <select x-bind:name="'operations['+index+'][section_id]'"
                                                class="form-select form-select-sm fs-11 routing-section-select"
                                                :data-row-uid="operation.uid"
                                                x-bind:required="!operation.is_external"
                                                x-bind:disabled="!operation.work_center_id || operation.is_external">
                                                <option value="">-- Select Section --</option>
                                            </select>
                                            <!-- Disabled reason helpers -->
                                            <template x-if="operation.is_external">
                                                <span class="badge bg-soft-secondary text-muted fs-10 mt-1.5 d-inline-block"><i class="feather-slash me-1"></i>N/A (Outsourced)</span>
                                            </template>
                                            <template x-if="!operation.work_center_id && !operation.is_external">
                                                <div class="mt-1.5 d-flex align-items-center text-muted fs-10 fst-italic">
                                                    <i class="feather-arrow-left me-1 text-secondary"></i>Select Work Center first
                                                </div>
                                            </template>
                                            <template x-if="operation.work_center_id && (!operation.availableSections || operation.availableSections.length === 0) && !operation.is_external">
                                                <div class="mt-1.5 d-flex align-items-center text-warning fs-10 fst-italic">
                                                    <i class="feather-alert-circle me-1"></i>No sections in this WC
                                                </div>
                                            </template>
                                        </td>
                                        
                                        <!-- Machine -->
                                        <td class="align-top" style="width: 185px; min-width: 185px;">
                                            <!-- Inline row warning if cleared because manual batch was selected -->
                                            <template x-if="operation.noticeMessage && operation.section_manual_production_id">
                                                <div class="alert alert-warning py-1 px-1.5 fs-10 mb-2 d-flex align-items-start justify-content-between shadow-xs">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="feather-alert-triangle text-warning flex-shrink-0"></i>
                                                        <span x-text="operation.noticeMessage" class="fw-medium text-dark lh-sm"></span>
                                                    </div>
                                                    <button type="button" class="btn-close fs-8 p-0 ms-1 flex-shrink-0" @click="operation.noticeMessage = ''"></button>
                                                </div>
                                            </template>

                                            <select x-bind:name="'operations['+index+'][machine_id]'"
                                                class="form-select form-select-sm fs-11 routing-machine-select"
                                                :data-row-uid="operation.uid"
                                                x-bind:disabled="!operation.section_id || operation.is_external || (operation.section_id && (!operation.availableMachines || operation.availableMachines.length === 0))">
                                                <option value="">-- None / Select --</option>
                                            </select>

                                            <!-- Contextual Guidance / Status / Helpers with vertical gap and padding -->
                                            <div class="mt-2 d-flex flex-column gap-2">
                                                <template x-if="operation.is_external">
                                                    <span class="badge bg-soft-secondary text-muted fs-10 py-1 px-1.5"><i class="feather-slash me-1"></i>N/A (Outsourced)</span>
                                                </template>
                                                <template x-if="!operation.work_center_id && !operation.is_external">
                                                    <div class="d-flex align-items-center text-muted fs-10 fst-italic py-0.5">
                                                        <i class="feather-arrow-left me-1 text-secondary"></i>Select Work Center first
                                                    </div>
                                                </template>
                                                <template x-if="operation.work_center_id && !operation.section_id && !operation.is_external">
                                                    <div class="d-flex align-items-center text-muted fs-10 fst-italic py-0.5">
                                                        <i class="feather-arrow-left me-1 text-secondary"></i>Select Section first
                                                    </div>
                                                </template>
                                                <template x-if="operation.section_id && (!operation.availableMachines || operation.availableMachines.length === 0) && !operation.is_external">
                                                    <div class="d-flex align-items-center text-muted fs-10 fst-italic py-0.5">
                                                        <i class="feather-info me-1 text-secondary"></i>No active machines in section
                                                    </div>
                                                </template>
                                                <template x-if="operation.section_manual_production_id && !operation.machine_id && !operation.is_external">
                                                    <div class="p-2 rounded bg-light border border-secondary-subtle shadow-xs">
                                                        <div class="d-flex align-items-center gap-1.5 text-secondary fs-10 fw-semibold">
                                                            <i class="feather-users text-info fs-12"></i><span>Manual batch active</span>
                                                        </div>
                                                        <div class="fs-9 text-muted mt-1 lh-sm">Selecting machine will switch resource</div>
                                                    </div>
                                                </template>
                                            </div>

                                            <!-- Mini-Spec Card for Machine -->
                                            <template x-if="getSelectedMachine(operation) && !operation.is_external">
                                                <div class="mt-2 p-2 rounded bg-white border shadow-xs" style="border-color: #e2e8f0 !important;">
                                                    <div class="d-flex justify-content-between align-items-center pb-1 mb-1 border-bottom border-light-subtle">
                                                        <span class="badge bg-soft-primary text-primary fs-10 px-1 py-0.5 font-monospace fw-semibold" x-text="getSelectedMachine(operation).code"></span>
                                                        <span class="fs-9 text-muted fw-bold text-uppercase ls-1">Machine Spec</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary mb-1">
                                                        <span><i class="feather-activity me-1 text-muted"></i>Capacity:</span>
                                                        <span class="fw-bold text-dark font-monospace" x-text="getSelectedMachine(operation).capacity + ' u/hr'"></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary mb-1">
                                                        <span><i class="feather-clock me-1 text-muted"></i>Run Speed:</span>
                                                        <span class="fw-bold text-dark font-monospace" x-text="formatDuration(getSelectedMachine(operation).speed_minutes_per_unit) + ' (' + parseFloat(getSelectedMachine(operation).speed_minutes_per_unit || 0).toFixed(2) + ' m/u)'"></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary pt-1 border-top border-light-subtle">
                                                        <span><i class="feather-dollar-sign me-0.5 text-muted"></i>Unit Cost:</span>
                                                        <span class="fw-bold text-primary font-monospace fs-11" x-text="'{{ active_currency_symbol() }}' + parseFloat(getSelectedMachine(operation).cost_per_unit || 0).toFixed(2)"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </td>

                                        <!-- Manual Batch -->
                                        <td class="align-top" style="width: 185px; min-width: 185px;">
                                            <!-- Inline row warning if cleared because machine was selected -->
                                            <template x-if="operation.noticeMessage && operation.machine_id">
                                                <div class="alert alert-warning py-1 px-1.5 fs-10 mb-2 d-flex align-items-start justify-content-between shadow-xs">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="feather-alert-triangle text-warning flex-shrink-0"></i>
                                                        <span x-text="operation.noticeMessage" class="fw-medium text-dark lh-sm"></span>
                                                    </div>
                                                    <button type="button" class="btn-close fs-8 p-0 ms-1 flex-shrink-0" @click="operation.noticeMessage = ''"></button>
                                                </div>
                                            </template>

                                            <select x-bind:name="'operations['+index+'][section_manual_production_id]'"
                                                class="form-select form-select-sm fs-11 routing-batch-select"
                                                :data-row-uid="operation.uid"
                                                x-bind:disabled="!operation.section_id || operation.is_external || (operation.section_id && (!operation.availableBatches || operation.availableBatches.length === 0))">
                                                <option value="">-- None / Select --</option>
                                            </select>

                                            <!-- Contextual Guidance / Status / Helpers with vertical gap and padding -->
                                            <div class="mt-2 d-flex flex-column gap-2">
                                                <template x-if="operation.is_external">
                                                    <span class="badge bg-soft-secondary text-muted fs-10 py-1 px-1.5"><i class="feather-slash me-1"></i>N/A (Outsourced)</span>
                                                </template>
                                                <template x-if="!operation.work_center_id && !operation.is_external">
                                                    <div class="d-flex align-items-center text-muted fs-10 fst-italic py-0.5">
                                                        <i class="feather-arrow-left me-1 text-secondary"></i>Select Work Center first
                                                    </div>
                                                </template>
                                                <template x-if="operation.work_center_id && !operation.section_id && !operation.is_external">
                                                    <div class="d-flex align-items-center text-muted fs-10 fst-italic py-0.5">
                                                        <i class="feather-arrow-left me-1 text-secondary"></i>Select Section first
                                                    </div>
                                                </template>
                                                <template x-if="operation.section_id && (!operation.availableBatches || operation.availableBatches.length === 0) && !operation.is_external">
                                                    <div class="d-flex align-items-center text-muted fs-10 fst-italic py-0.5">
                                                        <i class="feather-info me-1 text-secondary"></i>No manual batches in section
                                                    </div>
                                                </template>
                                                <template x-if="operation.machine_id && !operation.section_manual_production_id && !operation.is_external">
                                                    <div class="p-2 rounded bg-light border border-secondary-subtle shadow-xs">
                                                        <div class="d-flex align-items-center gap-1.5 text-secondary fs-10 fw-semibold">
                                                            <i class="feather-cpu text-primary fs-12"></i><span>Machine active</span>
                                                        </div>
                                                        <div class="fs-9 text-muted mt-1 lh-sm">Selecting batch will switch resource</div>
                                                    </div>
                                                </template>
                                            </div>

                                            <!-- Mini-Spec Card for Manual Batch -->
                                            <template x-if="getSelectedBatch(operation) && !operation.is_external">
                                                <div class="mt-2 p-2 rounded bg-white border shadow-xs" style="border-color: #e2e8f0 !important;">
                                                    <div class="d-flex justify-content-between align-items-center pb-1 mb-1 border-bottom border-light-subtle">
                                                        <span class="badge bg-soft-info text-info fs-10 px-1 py-0.5 fw-semibold text-truncate" style="max-width: 95px;" x-text="getSelectedBatch(operation).shift_name || 'Manual Batch'"></span>
                                                        <span class="fs-9 text-muted fw-bold text-uppercase ls-1">Batch Spec</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary mb-1">
                                                        <span><i class="feather-activity me-1 text-muted"></i>Capacity:</span>
                                                        <span class="fw-bold text-dark font-monospace" x-text="getSelectedBatch(operation).capacity_per_hour + ' u/hr'"></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary mb-1">
                                                        <span><i class="feather-clock me-1 text-muted"></i>Run Speed:</span>
                                                        <span class="fw-bold text-dark font-monospace" x-text="formatDuration(getSelectedBatch(operation).speed_minutes_per_unit) + ' (' + parseFloat(getSelectedBatch(operation).speed_minutes_per_unit || 0).toFixed(2) + ' m/u)'"></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary pt-1 border-top border-light-subtle">
                                                        <span><i class="feather-dollar-sign me-0.5 text-muted"></i>Labor Cost:</span>
                                                        <span class="fw-bold text-info font-monospace fs-11" x-text="'{{ active_currency_symbol() }}' + parseFloat(getSelectedBatch(operation).cost_per_unit || 0).toFixed(2)"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </td>
                                        
                                        <!-- Setup Time -->
                                        <td class="align-middle text-end" style="width: 85px; min-width: 85px;">
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][setup_time_minutes]'" class="odoo-table-input text-end px-1" :class="operation.is_external ? 'bg-light text-muted' : ''" style="width: 100%;" :style="operation.is_external ? 'cursor: not-allowed;' : ''" x-model="operation.setup_time_minutes" :readonly="operation.is_external" min="0" required />
                                            <div class="mt-1" x-show="parseFloat(operation.setup_time_minutes || 0) > 0">
                                                <span class="badge bg-soft-secondary text-secondary fs-10 font-monospace py-0.5 px-1" x-text="formatDuration(operation.setup_time_minutes)"></span>
                                            </div>
                                        </td>
                                        
                                        <!-- Process Time (Run) - Derived from capacity, non-editable -->
                                        <td class="align-middle text-end" style="width: 85px; min-width: 85px;">
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][processing_time_minutes]'" class="odoo-table-input text-end px-1 fw-semibold bg-light text-muted" style="width: 100%; cursor: not-allowed;" x-model="operation.processing_time_minutes" readonly tabindex="-1" min="0" required title="Run speed is automatically derived from Machine / Manual Batch capacity" />
                                            <div class="mt-1" x-show="parseFloat(operation.processing_time_minutes || 0) > 0">
                                                <span class="badge bg-soft-primary text-primary fs-10 font-monospace py-0.5 px-1" x-text="formatDuration(operation.processing_time_minutes)"></span>
                                            </div>
                                        </td>

                                        <!-- Wait Time -->
                                        <td class="align-middle text-end" style="width: 85px; min-width: 85px;">
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][wait_time_minutes]'" class="odoo-table-input text-end px-1" :class="operation.is_external ? 'bg-light text-muted' : ''" style="width: 100%;" :style="operation.is_external ? 'cursor: not-allowed;' : ''" x-model="operation.wait_time_minutes" :readonly="operation.is_external" min="0" placeholder="0.0" title="Buffer / wait / cure / cool-down time (minutes)" />
                                            <div class="mt-1" x-show="parseFloat(operation.wait_time_minutes || 0) > 0">
                                                <span class="badge bg-soft-secondary text-secondary fs-10 font-monospace py-0.5 px-1" x-text="formatDuration(operation.wait_time_minutes)"></span>
                                            </div>
                                        </td>
                                        
                                        <!-- Cost / Unit -->
                                        <td class="align-middle text-end font-monospace fw-semibold" style="width: 95px; min-width: 95px;">
                                            <span class="fs-12 text-primary" x-text="'{{ active_currency_symbol() }}' + calculateUnitCost(operation)"></span>
                                        </td>
                                        
                                        <!-- Quality Required -->
                                        <td class="text-center align-middle" style="width: 45px; min-width: 45px;">
                                            <input type="checkbox" class="form-check-input ms-0 mt-0" x-model="operation.quality_required" x-bind:name="'operations['+index+'][quality_required]'" value="1" />
                                        </td>

                                        <!-- Type / Alternative -->
                                        <td class="align-middle" style="width: 170px; min-width: 170px;">
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input" type="checkbox"
                                                    x-model="operation.is_alternative"
                                                    @change="onAlternativeToggle(operation)"
                                                    x-bind:id="'is_alt_' + operation.uid">
                                                <label class="form-check-label fs-11 fw-semibold c-pointer" x-bind:for="'is_alt_' + operation.uid"
                                                    x-text="operation.is_alternative ? 'Alternative' : 'Primary'">
                                                </label>
                                            </div>
                                            <div x-show="operation.is_alternative" class="mt-2" x-cloak>
                                                <label class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">
                                                    Alt Of <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-select form-select-sm fs-11 routing-alt-select"
                                                    :data-row-uid="operation.uid"
                                                    :required="operation.is_alternative">
                                                    <option value="">-- Primary Op --</option>
                                                </select>
                                                <div class="mt-2 d-flex align-items-center gap-1.5">
                                                    <span class="fs-10 text-muted fw-bold text-uppercase">Priority:</span>
                                                    <input type="number" min="1" step="1"
                                                        class="routing-sub-input text-center" style="width: 55px;"
                                                        x-model="operation.priority"
                                                        placeholder="1"
                                                        :required="operation.is_alternative" />
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="text-end align-middle" style="width: 120px; min-width: 120px;">
                                            <div class="d-inline-flex align-items-center justify-content-end gap-2">
                                                <button type="button" class="btn btn-xs btn-outline-secondary p-0 rounded d-inline-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" x-on:click="moveUp(index)" x-bind:disabled="index === 0" title="{{ __('production.move_up') }}">
                                                    <i class="feather-arrow-up fs-12"></i>
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-secondary p-0 rounded d-inline-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" x-on:click="moveDown(index)" x-bind:disabled="index === operations.length - 1" title="{{ __('production.move_down') }}">
                                                    <i class="feather-arrow-down fs-12"></i>
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-primary p-0 rounded d-inline-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" x-on:click="duplicateOperation(index)" title="{{ __('production.duplicate_operation') }}">
                                                    <i class="feather-copy fs-12"></i>
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-danger p-0 rounded d-inline-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" x-on:click="removeOperation(index)" title="{{ __('production.remove_operation') }}">
                                                    <i class="feather-x fs-13"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                     <!-- Subcontract Details Expanded Panel -->
                                     <tr x-show="operation.is_external" class="bg-light-subtle">
                                         <td colspan="13" class="p-2 border-top-0">
                                             <div class="row g-2 align-items-center fs-12 px-2 py-2 bg-white rounded border">
                                                 <div class="col-md-3">
                                                     <x-ui.odoo-form-ui type="select" label="Vendor *" x-bind:name="'operations['+index+'][vendor_id]'" class="form-select form-select-sm fs-11" x-model="operation.vendor_id" x-bind:required="operation.is_external" x-bind:disabled="!operation.is_external">
                                                         <option value="">Select Vendor</option>
                                                         @foreach($vendors as $v)
                                                             <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->code }})</option>
                                                         @endforeach
                                                     </x-ui.odoo-form-ui>
                                                 </div>
                                                 <div class="col-md-3" x-show="operation.subcontract_input_type !== 'previous_operation_wip'">
                                                     <x-ui.odoo-form-ui type="select" label="Supply Type" x-bind:name="'operations['+index+'][material_supply_type]'" class="form-select form-select-sm fs-11" x-model="operation.material_supply_type" x-bind:disabled="!operation.is_external">
                                                         <option value="company_supplied">Company Supplied</option>
                                                         <option value="vendor_supplied">Vendor Supplied</option>
                                                     </x-ui.odoo-form-ui>
                                                 </div>
                                                 <div class="col-md-3" x-show="operation.subcontract_input_type === 'previous_operation_wip'">
                                                     <div class="odoo-form-group mb-0">
                                                         <label class="odoo-form-label fs-11 text-muted">Supply Type</label>
                                                         <div class="flex-grow-1">
                                                             <div class="form-control form-control-sm fs-11 bg-light text-primary fw-semibold d-flex align-items-center" style="height: 31px;">
                                                                 <i class="feather-layers me-1"></i>Previous Op WIP
                                                             </div>
                                                         </div>
                                                     </div>
                                                 </div>
                                                 <div class="col-md-3">
                                                     <x-ui.odoo-form-ui type="select" label="Subcontract Processing Mode" x-bind:name="'operations['+index+'][subcontract_input_type]'" class="form-select form-select-sm fs-11" x-model="operation.subcontract_input_type" x-bind:disabled="!operation.is_external">
                                                         <option value="bom_raw_materials">BOM Raw Materials (From Stock)</option>
                                                         <option value="previous_operation_wip">Previous Operation WIP (Job Work)</option>
                                                     </x-ui.odoo-form-ui>
                                                 </div>
                                                 <div class="col-md-3">
                                                     <x-ui.odoo-form-ui type="input" inputType="number" label="Lead Time (Days)" min="0" x-bind:name="'operations['+index+'][subcontract_lead_time_days]'" class="form-control form-control-sm fs-11" x-model="operation.subcontract_lead_time_days" placeholder="0" x-bind:disabled="!operation.is_external" />
                                                 </div>
                                                 <div class="col-md-3">
                                                     <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" min="0" label="Cost / Unit" x-bind:name="'operations['+index+'][subcontract_cost_per_unit]'" class="form-control form-control-sm fs-11" x-model="operation.subcontract_cost_per_unit" placeholder="0.00" x-bind:disabled="!operation.is_external" />
                                                 </div>
                                                 <div class="col-md-3">
                                                     <x-ui.odoo-form-ui type="select" label="Subcontract Service Product" x-bind:name="'operations['+index+'][subcontract_service_product_id]'" class="form-select form-select-sm fs-11" x-model="operation.subcontract_service_product_id" x-bind:disabled="!operation.is_external">
                                                         <option value="">Select Service Product</option>
                                                         @foreach($serviceProducts as $sp)
                                                             <option value="{{ $sp->id }}">{{ $sp->name }} ({{ $sp->sku }})</option>
                                                         @endforeach
                                                     </x-ui.odoo-form-ui>
                                                 </div>
                                                 <div class="col-md-3">
                                                     <x-ui.odoo-form-ui type="input" inputType="number" min="0" label="Dispatch Buffer (Days)" x-bind:name="'operations['+index+'][dispatch_buffer_days]'" class="form-control form-control-sm fs-11" x-model="operation.dispatch_buffer_days" placeholder="0" x-bind:disabled="!operation.is_external" />
                                                 </div>
                                                 <div class="col-md-3">
                                                     <x-ui.odoo-form-ui type="input" inputType="number" min="0" label="Return Buffer (Days)" x-bind:name="'operations['+index+'][return_buffer_days]'" class="form-control form-control-sm fs-11" x-model="operation.return_buffer_days" placeholder="0" x-bind:disabled="!operation.is_external" />
                                                 </div>
                                             </div>
                                         </td>
                                     </tr>
                                </tbody>
                            </template>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <button type="button" class="btn btn-link p-0 text-primary fw-semibold text-decoration-none shadow-none border-0 bg-transparent" x-on:click="addOperation()">
                            <i class="feather-plus me-1 fs-14"></i>{{ __('production.add_operation_stage') }}
                        </button>
                        
                        <div class="text-muted fs-12 fw-medium">
                            {{ __('production.total_operations') }} <span class="fw-bold text-dark" x-text="operations.length"></span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <x-ui.button type="submit" variant="primary" class="px-4">{{ __('production.update_routing') }}</x-ui.button>
                    <x-ui.button :href="route('production.routing.show', $routing->id)" variant="light" class="px-4">{{ __('production.cancel') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>

        <x-ui.confirmation-modal />
    </div>

    <script>
        function routingForm() {
            return {
                operations: [],
                autoSequence: true,
                uidCounter: 1,
                noticeMessage: '',
                noticeTimeout: null,
                sectionResourcesCache: {},
                workCenterSectionsMap: {},

                init() {
                    const self = this;

                    // Build workCenterSectionsMap from server data
                    const workCentersData = @json($workCenters);
                    (workCentersData || []).forEach(wc => {
                        self.workCenterSectionsMap[wc.id] = (wc.sections || []).map(s => ({
                            id: s.id,
                            name: s.name,
                            code: s.code,
                            label: s.name + ' (' + s.code + ')'
                        }));
                    });

                    // Populate from old('operations') if returning with validation errors/warnings, otherwise from database
                    const oldOps = @json(old('operations', []));
                    const rawOpsList = Array.isArray(oldOps) ? oldOps : Object.values(oldOps || {});
                    if (rawOpsList.length > 0) {
                        rawOpsList.forEach((op, opIndex) => {
                            const newOp = {
                                uid: self.uidCounter++,
                                sequence: op.sequence ? parseInt(op.sequence) : (opIndex + 1) * 10,
                                name: op.name || '',
                                operation_type: op.operation_type || 'manufacturing',
                                is_alternative: op.is_alternative == 1 || op.is_alternative === '1' || op.is_alternative === true,
                                replaces_row_uid: op.replaces_row_uid || '',
                                replaces_operation_id: op.replaces_operation_id || '',
                                priority: parseInt(op.priority || 1),
                                work_center_id: op.work_center_id || '',
                                section_id: op.section_id || '',
                                machine_id: op.machine_id || '',
                                section_manual_production_id: op.section_manual_production_id || '',
                                setup_time_minutes: op.setup_time_minutes !== undefined ? op.setup_time_minutes : '0.00',
                                processing_time_minutes: op.processing_time_minutes !== undefined ? op.processing_time_minutes : '0.00',
                                wait_time_minutes: op.wait_time_minutes !== undefined ? op.wait_time_minutes : '0.00',
                                labor_cost_rate: op.labor_cost_rate !== undefined ? op.labor_cost_rate : '0.0000',
                                machine_cost_rate: op.machine_cost_rate !== undefined ? op.machine_cost_rate : '0.0000',
                                description: op.description || '',
                                instructions: op.instructions || '',
                                showSop: !!(op.instructions),
                                expected_yield_percentage: op.expected_yield_percentage || '100.00',
                                quality_required: op.quality_required == 1 || op.quality_required === '1' || op.quality_required === true,
                                is_external: op.is_external == 1 || op.is_external === '1' || op.is_external === true,
                                vendor_id: op.vendor_id || '',
                                material_supply_type: op.material_supply_type || 'company_supplied',
                                subcontract_input_type: op.subcontract_input_type || 'bom_raw_materials',
                                subcontract_lead_time_days: parseInt(op.subcontract_lead_time_days || 0),
                                subcontract_cost_per_unit: op.subcontract_cost_per_unit || '0.00',
                                subcontract_service_product_id: op.subcontract_service_product_id || '',
                                dispatch_buffer_days: parseInt(op.dispatch_buffer_days || 0),
                                return_buffer_days: parseInt(op.return_buffer_days || 0),
                                queue_threshold_enabled: op.queue_threshold_enabled == 1 || op.queue_threshold_enabled === '1' || op.queue_threshold_enabled === true,
                                overlap_enabled: op.overlap_enabled == 1 || op.overlap_enabled === '1' || op.overlap_enabled === true,
                                transfer_batch_quantity: op.transfer_batch_quantity || '0.00',
                                transfer_lag_minutes: parseInt(op.transfer_lag_minutes || 0),
                                is_parallel: op.is_parallel == 1 || op.is_parallel === '1' || op.is_parallel === true,
                                parallel_group: op.parallel_group || '',
                                predecessor_sequence: op.predecessor_sequence || '',
                                availableSections: op.work_center_id ? (self.workCenterSectionsMap[op.work_center_id] || []) : [],
                                availableMachines: [],
                                availableBatches: [],
                                operator_labor_cost_per_machine_per_hour: 0,
                                noticeMessage: '',
                                noticeTimeout: null
                            };
                            self.operations.push(newOp);
                            if (newOp.section_id) {
                                self.fetchSectionResources(newOp.section_id, (res) => {
                                    newOp.availableMachines = res.machines || [];
                                    newOp.availableBatches = res.manual_batches || [];
                                    newOp.operator_labor_cost_per_machine_per_hour = res.operator_labor_cost_per_machine_per_hour || 0;
                                    const $row = $('tr[data-row-uid="' + newOp.uid + '"]');
                                    self.refreshMachineSelect2($row, newOp);
                                    self.refreshBatchSelect2($row, newOp);
                                });
                            }
                        });
                    } else {
                        // Populate from existing operations database collection
                        @foreach ($routing->operations as $op)
                            const op{{ $loop->index }} = {
                                uid: self.uidCounter++,
                                db_id: {{ $op->id }},
                                sequence: {{ $op->sequence }},
                                name: @js($op->name),
                                operation_type: "{{ $op->operation_type }}",
                                is_alternative: {{ $op->is_alternative ? 'true' : 'false' }},
                                replaces_row_uid: '',
                                replaces_operation_id: "{{ $op->replaces_operation_id ?? '' }}",
                                priority: {{ (int) ($op->priority ?? 1) }},
                                work_center_id: "{{ $op->work_center_id ?? '' }}",
                                section_id: "{{ $op->section_id ?? '' }}",
                                machine_id: "{{ $op->machine_id ?? '' }}",
                                section_manual_production_id: "{{ $op->section_manual_production_id ?? '' }}",
                                setup_time_minutes: "{{ number_format($op->setup_time_minutes, 2, '.', '') }}",
                                processing_time_minutes: "{{ number_format($op->processing_time_minutes, 2, '.', '') }}",
                                wait_time_minutes: "{{ number_format($op->wait_time_minutes ?? 0, 2, '.', '') }}",
                                labor_cost_rate: "{{ number_format(convert_from_base($op->labor_cost_rate ?? 0), 4, '.', '') }}",
                                machine_cost_rate: "{{ number_format(convert_from_base($op->machine_cost_rate ?? 0), 4, '.', '') }}",
                                description: @js($op->description ?? ''),
                                instructions: @js($op->instructions ?? ''),
                                showSop: {{ !empty($op->instructions) ? 'true' : 'false' }},
                                expected_yield_percentage: "{{ number_format($op->expected_yield_percentage, 2, '.', '') }}",
                                quality_required: {{ $op->quality_required ? 'true' : 'false' }},
                                is_external: {{ $op->is_external ? 'true' : 'false' }},
                                vendor_id: "{{ $op->vendor_id ?? '' }}",
                                material_supply_type: "{{ $op->material_supply_type ?? 'company_supplied' }}",
                                subcontract_input_type: "{{ $op->subcontract_input_type ?? 'bom_raw_materials' }}",
                                subcontract_lead_time_days: {{ (int) ($op->subcontract_lead_time_days ?? 0) }},
                                subcontract_cost_per_unit: "{{ number_format(convert_from_base($op->subcontract_cost_per_unit ?? 0), 2, '.', '') }}",
                                subcontract_service_product_id: "{{ $op->subcontract_service_product_id ?? '' }}",
                                dispatch_buffer_days: {{ (int) ($op->dispatch_buffer_days ?? 0) }},
                                return_buffer_days: {{ (int) ($op->return_buffer_days ?? 0) }},
                                queue_threshold_enabled: {{ ($op->queue_threshold_enabled ?? $op->overlap_enabled) ? 'true' : 'false' }},
                                overlap_enabled: {{ ($op->queue_threshold_enabled ?? $op->overlap_enabled) ? 'true' : 'false' }},
                                transfer_batch_quantity: "{{ number_format($op->transfer_batch_quantity ?? 0, 2, '.', '') }}",
                                transfer_lag_minutes: {{ (int) ($op->transfer_lag_minutes ?? 0) }},
                                is_parallel: {{ $op->is_parallel ? 'true' : 'false' }},
                                parallel_group: @js($op->parallel_group ?? ''),
                                predecessor_sequence: "{{ $op->previousOperation?->sequence ?? ($op->previous_operation_id === null && $loop->index > 0 && !$op->is_parallel ? '-1' : '') }}",
                                availableSections: "{{ $op->work_center_id }}" ? (self.workCenterSectionsMap["{{ $op->work_center_id }}"] || []) : [],
                                availableMachines: [],
                                availableBatches: [],
                                operator_labor_cost_per_machine_per_hour: 0,
                                noticeMessage: '',
                                noticeTimeout: null
                            };
                            self.operations.push(op{{ $loop->index }});
                        @endforeach

                        // Resolve replaces_row_uid from database replaces_operation_id
                        const dbIdToUidMap = {};
                        self.operations.forEach(op => {
                            if (op.db_id) {
                                dbIdToUidMap[op.db_id] = op.uid;
                            }
                        });
                        self.operations.forEach(op => {
                            if (op.is_alternative && op.replaces_operation_id && dbIdToUidMap[op.replaces_operation_id]) {
                                op.replaces_row_uid = dbIdToUidMap[op.replaces_operation_id];
                            }
                        });

                        // Fetch section resources for all loaded operations
                        self.operations.forEach(loadedOp => {
                            if (loadedOp.section_id) {
                                self.fetchSectionResources(loadedOp.section_id, (res) => {
                                    loadedOp.availableMachines = res.machines || [];
                                    loadedOp.availableBatches = res.manual_batches || [];
                                    loadedOp.operator_labor_cost_per_machine_per_hour = res.operator_labor_cost_per_machine_per_hour || 0;
                                    const $row = $('tr[data-row-uid="' + loadedOp.uid + '"]');
                                    self.refreshMachineSelect2($row, loadedOp);
                                    self.refreshBatchSelect2($row, loadedOp);
                                });
                            }
                        });
                    }

                    if (this.operations.length === 0) {
                        this.addOperation();
                    }

                    // Watch autoSequence changes to keep sequences updated
                    this.$watch('autoSequence', value => {
                        if (value) {
                            this.recalculateSequences();
                        }
                    });

                    // Initialize Select2 on all dynamic rows
                    this.$nextTick(() => {
                        this.initAllRowSelect2();
                    });
                },

                addOperation() {
                    const nextSeq = this.operations.length > 0 
                        ? parseInt(this.operations[this.operations.length - 1].sequence) + 10 
                        : 10;

                    this.operations.push({
                        uid: this.uidCounter++,
                        sequence: nextSeq,
                        name: '',
                        operation_type: 'manufacturing',
                        is_alternative: false,
                        replaces_row_uid: '',
                        replaces_operation_id: '',
                        priority: 1,
                        work_center_id: '',
                        section_id: '',
                        machine_id: '',
                        section_manual_production_id: '',
                        setup_time_minutes: '0.00',
                        processing_time_minutes: '0.00',
                        wait_time_minutes: '0.00',
                        labor_cost_rate: '0.0000',
                        machine_cost_rate: '0.0000',
                        description: '',
                        instructions: '',
                        showSop: false,
                        expected_yield_percentage: '100.00',
                        quality_required: false,
                        is_external: false,
                        vendor_id: '',
                        material_supply_type: 'company_supplied',
                        subcontract_input_type: 'bom_raw_materials',
                        subcontract_lead_time_days: 0,
                        subcontract_cost_per_unit: '0.00',
                        subcontract_service_product_id: '',
                        dispatch_buffer_days: 0,
                        return_buffer_days: 0,
                        queue_threshold_enabled: false,
                        overlap_enabled: false,
                        transfer_batch_quantity: '0.00',
                        transfer_lag_minutes: 0,
                        is_parallel: false,
                        parallel_group: '',
                        predecessor_sequence: '',
                        availableSections: [],
                        availableMachines: [],
                        availableBatches: [],
                        operator_labor_cost_per_machine_per_hour: 0,
                        noticeMessage: '',
                        noticeTimeout: null
                    });

                    this.$nextTick(() => {
                        this.initAllRowSelect2();
                    });
                },

                getSelectedMachine(operation) {
                    if (!operation.machine_id || !operation.availableMachines) return null;
                    return operation.availableMachines.find(item => String(item.id) === String(operation.machine_id)) || null;
                },

                getSelectedBatch(operation) {
                    if (!operation.section_manual_production_id || !operation.availableBatches) return null;
                    return operation.availableBatches.find(item => String(item.id) === String(operation.section_manual_production_id)) || null;
                },

                select2RowOptions(placeholderText, allowClear = true) {
                    return {
                        theme: "bootstrap-5",
                        width: '100%',
                        dropdownParent: $('body'),
                        dropdownCssClass: 'routing-select2-dropdown',
                        placeholder: placeholderText || '-- Select --',
                        allowClear: allowClear
                    };
                },

                initRowSelect2(rowEl, operation) {
                    const self = this;
                    const $row = $(rowEl);

                    // 1. Work Center
                    const $wcSelect = $row.find('.routing-wc-select');
                    if ($wcSelect.length && !$wcSelect.hasClass('select2-hidden-accessible')) {
                        $wcSelect.select2(self.select2RowOptions('-- Select WC --'));
                        if (operation.work_center_id) {
                            $wcSelect.val(operation.work_center_id).trigger('change.select2');
                        }
                        $wcSelect.off('change.routing-wc select2:select.routing-wc select2:clear.routing-wc')
                                 .on('change.routing-wc select2:select.routing-wc select2:clear.routing-wc', function() {
                            const val = $(this).val() || '';
                            if (String(operation.work_center_id) !== String(val)) {
                                operation.work_center_id = val;
                                self.workCenterChanged(operation, $row);
                            }
                        });
                    }

                    // 2. Section
                    const $secSelect = $row.find('.routing-section-select');
                    if ($secSelect.length && !$secSelect.hasClass('select2-hidden-accessible')) {
                        $secSelect.select2(self.select2RowOptions(
                            operation.work_center_id ? '-- Select Section --' : '-- Select WC First --'
                        ));
                        self.refreshSectionSelect2($row, operation);
                        $secSelect.off('change.routing-sec select2:select.routing-sec select2:clear.routing-sec')
                                  .on('change.routing-sec select2:select.routing-sec select2:clear.routing-sec', function() {
                            const val = $(this).val() || '';
                            if (String(operation.section_id) !== String(val)) {
                                operation.section_id = val;
                                self.sectionChanged(operation, $row);
                            }
                        });
                    }

                    // 3. Machine
                    const $macSelect = $row.find('.routing-machine-select');
                    if ($macSelect.length && !$macSelect.hasClass('select2-hidden-accessible')) {
                        $macSelect.select2(self.select2RowOptions(
                            operation.section_id ? '-- None / Select Machine --' : '-- Select Section First --'
                        ));
                        self.refreshMachineSelect2($row, operation);
                        $macSelect.off('change.routing-mac select2:select.routing-mac select2:clear.routing-mac')
                                  .on('change.routing-mac select2:select.routing-mac select2:clear.routing-mac', function() {
                            const val = $(this).val() || '';
                            if (String(operation.machine_id) !== String(val)) {
                                operation.machine_id = val;
                                self.onMachineSelect(operation, $row);
                            }
                        });
                    }

                    // 4. Batch
                    const $batSelect = $row.find('.routing-batch-select');
                    if ($batSelect.length && !$batSelect.hasClass('select2-hidden-accessible')) {
                        $batSelect.select2(self.select2RowOptions(
                            operation.section_id ? '-- None / Select Batch --' : '-- Select Section First --'
                        ));
                        self.refreshBatchSelect2($row, operation);
                        $batSelect.off('change.routing-bat select2:select.routing-bat select2:clear.routing-bat')
                                  .on('change.routing-bat select2:select.routing-bat select2:clear.routing-bat', function() {
                            const val = $(this).val() || '';
                            if (String(operation.section_manual_production_id) !== String(val)) {
                                operation.section_manual_production_id = val;
                                self.onManualBatchSelect(operation, $row);
                            }
                        });
                    }

                    // 5. Alt Of
                    const $altSelect = $row.find('.routing-alt-select');
                    if ($altSelect.length) {
                        if (!$altSelect.hasClass('select2-hidden-accessible')) {
                            $altSelect.select2(self.select2RowOptions('-- Primary Op --'));
                        }
                        self.refreshAltSelect2($row, operation);
                        $altSelect.off('change.routing-alt select2:select.routing-alt select2:clear.routing-alt')
                                  .on('change.routing-alt select2:select.routing-alt select2:clear.routing-alt', function() {
                            const val = $(this).val() || '';
                            if (String(operation.replaces_row_uid || '') !== String(val)) {
                                operation.replaces_row_uid = val;
                                const targetOp = self.operations.find(o => String(o.uid) === String(val));
                                operation.replaces_operation_id = targetOp ? (targetOp.db_id || '') : '';
                            }
                        });
                    }

                    // 6. Predecessor
                    const $predSelect = $row.find('.routing-pred-select');
                    if ($predSelect.length) {
                        if (!$predSelect.hasClass('select2-hidden-accessible')) {
                            $predSelect.select2(self.select2RowOptions('-- Auto (Previous Op) --', false));
                        }
                        const opIdx = self.operations.findIndex(o => String(o.uid) === String(operation.uid));
                        self.refreshPredSelect2($row, operation, opIdx);
                        $predSelect.off('change.routing-pred select2:select.routing-pred select2:clear.routing-pred')
                                   .on('change.routing-pred select2:select.routing-pred select2:clear.routing-pred', function() {
                            const val = $(this).val() || '';
                            if (String(operation.predecessor_sequence || '') !== String(val)) {
                                operation.predecessor_sequence = val;
                            }
                        });
                    }
                },

                initAllRowSelect2() {
                    const self = this;
                    $('.routing-operation-row').each(function() {
                        const uid = $(this).attr('data-row-uid');
                        const op = self.operations.find(o => String(o.uid) === String(uid));
                        if (op) {
                            self.initRowSelect2(this, op);
                        }
                    });
                    this.refreshAllAltSelect2();
                    this.refreshAllPredSelect2();
                },

                formatDuration(minutes) {
                    const mFloat = parseFloat(minutes || 0);
                    if (isNaN(mFloat) || mFloat <= 0) return '0s';
                    const totalSeconds = Math.round(mFloat * 60);
                    const hours = Math.floor(totalSeconds / 3600);
                    const remainingSecs = totalSeconds % 3600;
                    const mins = Math.floor(remainingSecs / 60);
                    const secs = remainingSecs % 60;

                    if (hours > 0) {
                        if (mins > 0 && secs > 0) return `${hours}h ${mins}m ${secs}s`;
                        if (mins > 0) return `${hours}h ${mins}m`;
                        if (secs > 0) return `${hours}h ${secs}s`;
                        return `${hours}h`;
                    }
                    if (mins > 0) {
                        return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`;
                    }
                    return `${secs}s`;
                },

                refreshSectionSelect2($row, operation) {
                    if (!$row || !$row.length) return;
                    const $secSelect = $row.find('.routing-section-select');
                    if (!$secSelect.length) return;

                    const sections = operation.work_center_id ? (this.workCenterSectionsMap[operation.work_center_id] || []) : [];
                    const isDisabled = !operation.work_center_id || operation.is_external;
                    const placeholderText = operation.is_external
                        ? '-- N/A (Outsourced) --'
                        : (!operation.work_center_id ? '-- Select WC First --' : '-- Select Section --');

                    $secSelect.empty();
                    $secSelect.append(new Option(placeholderText, '', false, false));
                    sections.forEach(s => {
                        const isSelected = String(s.id) === String(operation.section_id);
                        $secSelect.append(new Option(s.label, s.id, isSelected, isSelected));
                    });

                    $secSelect.prop('disabled', isDisabled);
                    $secSelect.val(operation.section_id || '');
                    if ($secSelect.data('select2')) {
                        $secSelect.trigger('change.select2');
                    }
                },

                refreshMachineSelect2($row, operation) {
                    if (!$row || !$row.length) return;
                    const $macSelect = $row.find('.routing-machine-select');
                    if (!$macSelect.length) return;

                    const machines = operation.availableMachines || [];
                    const isDisabled = !operation.section_id || operation.is_external || machines.length === 0;
                    let placeholderText = '-- None / Select Machine --';
                    if (operation.is_external) {
                        placeholderText = '-- N/A (Outsourced) --';
                    } else if (!operation.work_center_id) {
                        placeholderText = '-- Select WC First --';
                    } else if (!operation.section_id) {
                        placeholderText = '-- Select Section First --';
                    } else if (machines.length === 0) {
                        placeholderText = '-- No Active Machines --';
                    }

                    $macSelect.empty();
                    $macSelect.append(new Option(placeholderText, '', false, false));
                    machines.forEach(m => {
                        const label = m.name + ' (' + m.code + ')';
                        const isSelected = String(m.id) === String(operation.machine_id);
                        $macSelect.append(new Option(label, m.id, isSelected, isSelected));
                    });

                    $macSelect.prop('disabled', isDisabled);
                    $macSelect.val(operation.machine_id || '');
                    if ($macSelect.data('select2')) {
                        $macSelect.data('select2').options.set('placeholder', placeholderText);
                        $macSelect.trigger('change.select2');
                    }
                },

                refreshBatchSelect2($row, operation) {
                    if (!$row || !$row.length) return;
                    const $batSelect = $row.find('.routing-batch-select');
                    if (!$batSelect.length) return;

                    const batches = operation.availableBatches || [];
                    const isDisabled = !operation.section_id || operation.is_external || batches.length === 0;
                    let placeholderText = '-- None / Select Batch --';
                    if (operation.is_external) {
                        placeholderText = '-- N/A (Outsourced) --';
                    } else if (!operation.work_center_id) {
                        placeholderText = '-- Select WC First --';
                    } else if (!operation.section_id) {
                        placeholderText = '-- Select Section First --';
                    } else if (batches.length === 0) {
                        placeholderText = '-- No Manual Batches --';
                    }

                    $batSelect.empty();
                    $batSelect.append(new Option(placeholderText, '', false, false));
                    batches.forEach(b => {
                        const label = b.batch_name + (b.shift_name ? ' [' + b.shift_name + ']' : '');
                        const isSelected = String(b.id) === String(operation.section_manual_production_id);
                        $batSelect.append(new Option(label, b.id, isSelected, isSelected));
                    });

                    $batSelect.prop('disabled', isDisabled);
                    $batSelect.val(operation.section_manual_production_id || '');
                    if ($batSelect.data('select2')) {
                        $batSelect.data('select2').options.set('placeholder', placeholderText);
                        $batSelect.trigger('change.select2');
                    }
                },

                refreshAltSelect2($row, operation) {
                    if (!$row || !$row.length) return;
                    const $altSelect = $row.find('.routing-alt-select');
                    if (!$altSelect.length) return;

                    const eligibleOps = this.getEligiblePrimaryOperations(operation.uid);
                    let currentVal = operation.replaces_row_uid !== undefined && operation.replaces_row_uid !== null ? String(operation.replaces_row_uid) : '';

                    if (currentVal && !eligibleOps.some(target => String(target.uid) === currentVal)) {
                        operation.replaces_row_uid = '';
                        operation.replaces_operation_id = '';
                        currentVal = '';
                    }

                    $altSelect.empty();
                    $altSelect.append(new Option('-- Primary Op --', '', false, false));
                    eligibleOps.forEach(target => {
                        const label = 'Op ' + target.sequence + (target.name ? ': ' + target.name : '');
                        const isSelected = String(target.uid) === currentVal;
                        $altSelect.append(new Option(label, target.uid, isSelected, isSelected));
                    });

                    $altSelect.val(currentVal);
                    if ($altSelect.data('select2')) {
                        $altSelect.trigger('change.select2');
                    }
                },

                refreshAllAltSelect2() {
                    const self = this;
                    $('.routing-operation-row').each(function() {
                        const uid = $(this).attr('data-row-uid');
                        const op = self.operations.find(o => String(o.uid) === String(uid));
                        if (op) {
                            self.refreshAltSelect2($(this), op);
                        }
                    });
                },

                refreshPredSelect2($row, operation, index) {
                    if (!$row || !$row.length) return;
                    const $predSelect = $row.find('.routing-pred-select');
                    if (!$predSelect.length) return;

                    if (typeof index === 'undefined') {
                        index = this.operations.findIndex(o => String(o.uid) === String(operation.uid));
                    }
                    const priorOps = this.getPriorOperations(index);
                    const currentVal = operation.predecessor_sequence !== undefined && operation.predecessor_sequence !== null && operation.predecessor_sequence !== ''
                        ? String(operation.predecessor_sequence)
                        : '';

                    $predSelect.empty();
                    $predSelect.append(new Option('-- Auto (Previous Op) --', '', currentVal === '', currentVal === ''));
                    $predSelect.append(new Option('-- None (Independent / Starts Immediately) --', '-1', currentVal === '-1', currentVal === '-1'));

                    priorOps.forEach(prevOp => {
                        const label = 'Op ' + prevOp.sequence + (prevOp.name ? ': ' + prevOp.name : '');
                        const valStr = String(prevOp.sequence);
                        const isSelected = valStr === currentVal;
                        $predSelect.append(new Option(label, valStr, isSelected, isSelected));
                    });

                    $predSelect.val(currentVal);
                    if ($predSelect.data('select2')) {
                        $predSelect.trigger('change.select2');
                    }
                },

                refreshAllPredSelect2() {
                    const self = this;
                    $('.routing-operation-row').each(function() {
                        const uid = $(this).attr('data-row-uid');
                        const idx = self.operations.findIndex(o => String(o.uid) === String(uid));
                        if (idx !== -1) {
                            self.refreshPredSelect2($(this), self.operations[idx], idx);
                        }
                    });
                },

                refreshAllAltAndPredSelect2() {
                    this.refreshAllAltSelect2();
                    this.refreshAllPredSelect2();
                },

                triggerRowNotice(operation, msg) {
                    operation.noticeMessage = msg;
                    this.noticeMessage = `Op ${operation.sequence}: ${msg}`;

                    if (window.toastr) {
                        toastr.info(`Op ${operation.sequence}: ${msg}`, 'Resource Selection');
                    }

                    if (operation.noticeTimeout) clearTimeout(operation.noticeTimeout);
                    operation.noticeTimeout = setTimeout(() => {
                        operation.noticeMessage = '';
                    }, 7000);

                    if (this.noticeTimeout) clearTimeout(this.noticeTimeout);
                    this.noticeTimeout = setTimeout(() => {
                        this.noticeMessage = '';
                    }, 7000);
                },

                getPriorOperations(currentIndex) {
                    return this.operations.filter((op, idx) => idx < currentIndex);
                },

                getEligiblePrimaryOperations(identifier) {
                    let targetUid = null;
                    if (identifier && typeof identifier === 'object' && identifier.uid !== undefined) {
                        targetUid = identifier.uid;
                    } else if (this.operations.some(op => String(op.uid) === String(identifier))) {
                        // identifier is already an existing operation uid
                        targetUid = identifier;
                    } else if (typeof identifier === 'number' && this.operations[identifier]) {
                        // identifier was passed as an array index
                        targetUid = this.operations[identifier].uid;
                    } else {
                        targetUid = identifier;
                    }

                    return this.operations.filter((op) => {
                        if (String(op.uid) === String(targetUid)) return false;
                        const isAlt = op.is_alternative == 1 || op.is_alternative === '1' || op.is_alternative === true;
                        if (isAlt) return false;
                        return true;
                    });
                },

                onAlternativeToggle(operation) {
                    if (!operation.is_alternative) {
                        operation.replaces_row_uid = '';
                        operation.replaces_operation_id = '';
                        operation.priority = 1;
                    } else {
                        if (!operation.priority || operation.priority < 1) {
                            operation.priority = 1;
                        }
                        // Clear references pointing to this operation from other alternatives
                        this.operations.forEach(other => {
                            if (other.is_alternative && (String(other.replaces_row_uid) === String(operation.uid) || String(other.replaces_operation_id) === String(operation.db_id || operation.uid))) {
                                other.replaces_row_uid = '';
                                other.replaces_operation_id = '';
                            }
                        });
                    }
                    this.$nextTick(() => {
                        const $row = $('tr[data-row-uid="' + operation.uid + '"]');
                        const $altSelect = $row.find('.routing-alt-select');
                        if ($altSelect.length) {
                            if ($altSelect.hasClass('select2-hidden-accessible')) {
                                $altSelect.select2('destroy');
                            }
                            $altSelect.select2(this.select2RowOptions('-- Primary Op --'));
                            const self = this;
                            $altSelect.off('change.routing-alt select2:select.routing-alt select2:clear.routing-alt')
                                      .on('change.routing-alt select2:select.routing-alt select2:clear.routing-alt', function() {
                                const val = $(this).val() || '';
                                if (String(operation.replaces_row_uid || '') !== String(val)) {
                                    operation.replaces_row_uid = val;
                                    const targetOp = self.operations.find(o => String(o.uid) === String(val));
                                    operation.replaces_operation_id = targetOp ? (targetOp.db_id || '') : '';
                                }
                            });
                        }
                        this.refreshAllAltSelect2();
                    });
                },

                onExternalToggle(operation) {
                    const $row = $('tr[data-row-uid="' + operation.uid + '"]');
                    if (operation.is_external) {
                        operation.work_center_id = '';
                        operation.section_id = '';
                        operation.machine_id = '';
                        operation.section_manual_production_id = '';
                        operation.availableSections = [];
                        operation.availableMachines = [];
                        operation.availableBatches = [];
                        operation.operator_labor_cost_per_machine_per_hour = 0;
                        operation.labor_cost_rate = '0.0000';
                        operation.machine_cost_rate = '0.0000';
                        operation.setup_time_minutes = '0.00';
                        operation.processing_time_minutes = '0.00';
                        operation.wait_time_minutes = '0.00';

                        if ($row.length) {
                            $row.find('.routing-wc-select').val('').prop('disabled', true).trigger('change.select2');
                            this.refreshSectionSelect2($row, operation);
                            this.refreshMachineSelect2($row, operation);
                            this.refreshBatchSelect2($row, operation);
                        }
                    } else {
                        operation.vendor_id = '';
                        operation.subcontract_cost_per_unit = '0.00';
                        operation.subcontract_lead_time_days = 0;
                        operation.subcontract_service_product_id = '';
                        operation.dispatch_buffer_days = 0;
                        operation.return_buffer_days = 0;

                        if ($row.length) {
                            $row.find('.routing-wc-select').prop('disabled', false).trigger('change.select2');
                            this.refreshSectionSelect2($row, operation);
                            this.refreshMachineSelect2($row, operation);
                            this.refreshBatchSelect2($row, operation);
                        }
                    }
                },

                workCenterChanged(operation, $row) {
                    operation.section_id = '';
                    operation.machine_id = '';
                    operation.section_manual_production_id = '';
                    operation.availableMachines = [];
                    operation.availableBatches = [];
                    operation.operator_labor_cost_per_machine_per_hour = 0;
                    operation.labor_cost_rate = '0.0000';
                    operation.machine_cost_rate = '0.0000';
                    operation.processing_time_minutes = '0.00';

                    if (operation.work_center_id) {
                        operation.availableSections = this.workCenterSectionsMap[operation.work_center_id] || [];
                    } else {
                        operation.availableSections = [];
                    }

                    if (!$row || !$row.length) {
                        $row = $('tr[data-row-uid="' + operation.uid + '"]');
                    }
                    this.refreshSectionSelect2($row, operation);
                    this.refreshMachineSelect2($row, operation);
                    this.refreshBatchSelect2($row, operation);
                },

                sectionChanged(operation, $row) {
                    operation.machine_id = '';
                    operation.section_manual_production_id = '';
                    operation.availableMachines = [];
                    operation.availableBatches = [];
                    operation.operator_labor_cost_per_machine_per_hour = 0;
                    operation.labor_cost_rate = '0.0000';
                    operation.machine_cost_rate = '0.0000';
                    operation.processing_time_minutes = '0.00';

                    if (!$row || !$row.length) {
                        $row = $('tr[data-row-uid="' + operation.uid + '"]');
                    }
                    this.refreshMachineSelect2($row, operation);
                    this.refreshBatchSelect2($row, operation);

                    if (operation.section_id) {
                        this.fetchSectionResources(operation.section_id, (res) => {
                            operation.availableMachines = res.machines || [];
                            operation.availableBatches = res.manual_batches || [];
                            operation.operator_labor_cost_per_machine_per_hour = res.operator_labor_cost_per_machine_per_hour || 0;

                            const $currentRow = $('tr[data-row-uid="' + operation.uid + '"]');
                            this.refreshMachineSelect2($currentRow, operation);
                            this.refreshBatchSelect2($currentRow, operation);
                        });
                    }
                },

                onMachineSelect(operation, $row) {
                    if (operation.machine_id) {
                        if (operation.section_manual_production_id) {
                            operation.section_manual_production_id = '';
                            const $bSelect = $row && $row.length ? $row.find('.routing-batch-select') : $('tr[data-row-uid="' + operation.uid + '"] .routing-batch-select');
                            $bSelect.val('').trigger('change.select2');
                            this.triggerRowNotice(operation, 'Machine selected: Cleared manual batch selection (Mutual Exclusivity).');
                        }
                        const m = (operation.availableMachines || []).find(item => String(item.id) === String(operation.machine_id));
                        if (m) {
                            if (m.speed_minutes_per_unit > 0) {
                                operation.processing_time_minutes = parseFloat(m.speed_minutes_per_unit).toFixed(2);
                            } else {
                                operation.processing_time_minutes = '0.00';
                            }
                            operation.machine_cost_rate = (parseFloat(m.operating_cost_per_hour || 0) / 60.0).toFixed(4);
                            operation.labor_cost_rate = (parseFloat(operation.operator_labor_cost_per_machine_per_hour || 0) / 60.0).toFixed(4);
                        }
                    } else {
                        operation.processing_time_minutes = '0.00';
                        operation.machine_cost_rate = '0.0000';
                        operation.labor_cost_rate = '0.0000';
                    }
                },

                onManualBatchSelect(operation, $row) {
                    if (operation.section_manual_production_id) {
                        if (operation.machine_id) {
                            operation.machine_id = '';
                            const $mSelect = $row && $row.length ? $row.find('.routing-machine-select') : $('tr[data-row-uid="' + operation.uid + '"] .routing-machine-select');
                            $mSelect.val('').trigger('change.select2');
                            this.triggerRowNotice(operation, 'Manual batch selected: Cleared machine selection (Mutual Exclusivity).');
                        }
                        const b = (operation.availableBatches || []).find(item => String(item.id) === String(operation.section_manual_production_id));
                        if (b) {
                            if (b.speed_minutes_per_unit > 0) {
                                operation.processing_time_minutes = parseFloat(b.speed_minutes_per_unit).toFixed(2);
                            } else {
                                operation.processing_time_minutes = '0.00';
                            }
                            operation.machine_cost_rate = '0.0000';
                            operation.labor_cost_rate = (parseFloat(b.labor_cost_per_hour || 0) / 60.0).toFixed(4);
                        }
                    } else {
                        operation.processing_time_minutes = '0.00';
                        operation.machine_cost_rate = '0.0000';
                        operation.labor_cost_rate = '0.0000';
                    }
                },

                fetchSectionResources(sectionId, callback) {
                    if (!sectionId) return;
                    if (this.sectionResourcesCache[sectionId]) {
                        if (callback) callback(this.sectionResourcesCache[sectionId]);
                        return;
                    }
                    fetch(`{{ url('production/sections') }}/${sectionId}/resources`)
                        .then(res => res.json())
                        .then(data => {
                            this.sectionResourcesCache[sectionId] = data;
                            if (callback) callback(data);
                        })
                        .catch(err => console.error('Failed to load section resources:', err));
                },

                calculateUnitCost(operation) {
                    if (operation.is_external) {
                        const unitSub = parseFloat(operation.subcontract_cost_per_unit || 0);
                        const yieldPct = parseFloat(operation.expected_yield_percentage || 100);
                        const yieldFactor = yieldPct > 0 ? (100 / yieldPct) : 1.0;
                        return (unitSub * yieldFactor).toFixed(2);
                    }
                    const activeMinutes = parseFloat(operation.setup_time_minutes || 0) + parseFloat(operation.processing_time_minutes || 0);
                    const laborRate = parseFloat(operation.labor_cost_rate || 0);
                    const machineRate = parseFloat(operation.machine_cost_rate || 0);
                    const yieldPct = parseFloat(operation.expected_yield_percentage || 100);
                    const yieldFactor = yieldPct > 0 ? (100 / yieldPct) : 1.0;

                    const unitCost = activeMinutes * (laborRate + machineRate) * yieldFactor;
                    return (unitCost || 0).toFixed(2);
                },

                removeOperation(index) {
                    if (this.operations.length > 1) {
                        const removed = this.operations[index];
                        $('tr[data-row-uid="' + removed.uid + '"] .select2-hidden-accessible').select2('destroy');
                        this.operations.forEach(op => {
                            if (op.is_alternative && (String(op.replaces_row_uid) === String(removed.uid) || String(op.replaces_operation_id) === String(removed.db_id || removed.uid))) {
                                op.replaces_row_uid = '';
                                op.replaces_operation_id = '';
                            }
                        });
                        this.operations.splice(index, 1);
                        this.recalculateSequences();
                    } else {
                        if (typeof confirmAction === 'function') {
                            confirmAction({
                                title: @js(__('production.validation_failed') ?? 'Validation Warning'),
                                message: @js(__('production.requires_at_least_one_operation')),
                                variant: 'warning',
                                confirmText: 'OK'
                            });
                        } else {
                            alert(@js(__('production.requires_at_least_one_operation')));
                        }
                    }
                },

                duplicateOperation(index) {
                    const original = this.operations[index];
                    const clone = JSON.parse(JSON.stringify(original));
                    
                    clone.uid = this.uidCounter++;
                    clone.noticeMessage = '';
                    clone.noticeTimeout = null;
                    let newSeq = parseInt(original.sequence) + 5;
                    const existingSeqs = this.operations.map(o => parseInt(o.sequence));
                    while (existingSeqs.includes(newSeq)) {
                        newSeq += 5;
                    }
                    clone.sequence = newSeq;
                    
                    this.operations.splice(index + 1, 0, clone);
                    this.recalculateSequences();
                    this.$nextTick(() => {
                        this.initAllRowSelect2();
                    });
                },

                moveUp(index) {
                    if (index > 0) {
                        const temp = this.operations[index];
                        this.operations[index] = this.operations[index - 1];
                        this.operations[index - 1] = temp;
                        this.recalculateSequences();
                        this.$nextTick(() => {
                            this.initAllRowSelect2();
                        });
                    }
                },

                moveDown(index) {
                    if (index < this.operations.length - 1) {
                        const temp = this.operations[index];
                        this.operations[index] = this.operations[index + 1];
                        this.operations[index + 1] = temp;
                        this.recalculateSequences();
                        this.$nextTick(() => {
                            this.initAllRowSelect2();
                        });
                    }
                },

                recalculateSequences() {
                    if (this.autoSequence) {
                        this.operations.forEach((op, index) => {
                            op.sequence = (index + 1) * 10;
                        });
                    }
                    this.$nextTick(() => {
                        this.refreshAllAltSelect2();
                        this.refreshAllPredSelect2();
                    });
                }
            }
        }

        $(document).ready(function() {
            $('.table-responsive').on('scroll', function() {
                $('.select2-hidden-accessible').select2('close');
            });
        });
    </script>
@endsection
