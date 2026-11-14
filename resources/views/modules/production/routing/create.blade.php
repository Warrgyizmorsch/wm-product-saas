@extends('layouts.duralux')

@section('title', __('production.create_routing') . ' | SaaS ERP')
@section('page-title', __('production.create_routing'))
@section('breadcrumb', __('production.create_routing'))

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
    <script>window.fmt4 = function (v) { const n = Math.round((parseFloat(v) || 0) * 10000) / 10000; return Number.isInteger(n) ? String(n) : n.toFixed(4); }; // 4 decimals only when there are decimals
    </script>
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

        <form method="POST" action="{{ route('production.routing.store') }}" x-data="routingForm()">
            @csrf
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.new_routing') }}</h4>
                    <a href="{{ route('production.routing.index') }}" class="text-muted hover-danger fs-18" title="{{ __('production.cancel') }}">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Routing General Header -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" :label="__('production.routing_number')" name="routing_number" placeholder="e.g. RTG-2026-001 or AUTO" :value="old('routing_number', 'AUTO')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.routing_name')" name="name" placeholder="{{ __('production.routing_name_placeholder') }}" :value="old('name')" :required="true" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.version')" name="version" placeholder="e.g. 1.0.0" :value="old('version', '1.0.0')" :required="true" />

                        <div class="row g-2">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('production.start_date')" name="effective_from" inputType="date" :value="old('effective_from', date('Y-m-d'))" :required="true" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('production.end_date') ?? 'End Date'" name="effective_to" inputType="date" :value="old('effective_to')" />
                            </div>
                        </div>

                        <input type="hidden" name="is_default" value="1">
                    </div>



                    <div class="col-12 border-top pt-3">
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.description')" name="description" placeholder="{{ __('production.description_placeholder') }}" rows="3">{{ old('description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>



                <!-- Operations Dynamic Grid -->
                <div class="border-top pt-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
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
                    
                    {{-- RT-IMPL-10 (RT-12 / RT-17) --}}
                    <div class="alert alert-soft-info border border-info-subtle py-1.5 px-3 fs-12 mb-2 d-flex align-items-center gap-2 routing-one-unit-notice"><i class="feather-info text-info fs-13"></i><span>{{ __('production.routing_one_unit_notice') }}</span></div>
                    <div class="table-responsive mb-3">
                        <x-ui.odoo-form-ui type="table" style="min-width: 1700px;">
                            <thead>
                                <tr>
                                    <th style="width: 45px; min-width: 45px;" class="text-center">{{ __('production.seq') }}</th>
                                    <th style="min-width: 260px;">{{ __('production.operation_name_yield') }}</th>
                                    <th style="width: 175px; min-width: 175px;">{{ __('production.work_center') }}</th>
                                    <th style="width: 175px; min-width: 175px;">Section</th>
                                    <th style="width: 185px; min-width: 185px;">{{ __('production.machine') }}</th>
                                    <th style="width: 185px; min-width: 185px;">Manual Batch</th>
                                    <th style="width: 85px; min-width: 85px;" class="text-end" title="Setup per operation: once per production run (machine changeover, tool setting). Not part of Cost / Unit — shown as setup cost per run.">{{ __('production.setup') }} / run (m)</th>
                                    <th style="width: 85px; min-width: 85px;" class="text-end" title="Setup per piece: repeated for every unit (loading, clamping, positioning). Added to the run time in Cost / Unit.">{{ __('production.setup') }} / piece (m)</th>
                                    <th style="width: 85px; min-width: 85px;" class="text-end">{{ __('production.run') }} (m)</th>
                                    <th style="width: 85px; min-width: 85px;" class="text-end" title="Buffer / wait / cure / cool-down time (minutes)">Wait (m)</th>
                                    <th style="width: 115px; min-width: 115px;" class="text-end text-nowrap">
                                        Cost / Unit <i class="feather-info text-primary ms-0.5 fs-11" data-bs-toggle="tooltip" data-bs-placement="top" title="Cost / Unit (recurring, every piece) = (Setup / piece + Run time) × (Labor rate + Machine rate) × (100 ÷ Yield %). One-time setup per run is shown separately in Op Setup Cost."></i>
                                    </th>
                                    {{-- Short Full Review Point 1: one-time setup per run gets its own column --}}
                                    <th style="width: 125px; min-width: 125px;" class="text-end text-nowrap routing-op-setup-cost-head">
                                        Op Setup Cost <i class="feather-info text-primary ms-0.5 fs-11" data-bs-toggle="tooltip" data-bs-placement="top" title="Operation Setup Cost (per run): one-time setup cost incurred once per production run / batch for machine changeover or tool setting = Setup / run (m) × (Labor rate + Machine rate), yield excluded. Shown separately from Cost / Unit because batch size is decided at Production Order time."></i>
                                    </th>
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
                                            <input type="hidden" x-bind:name="'operations['+index+'][replaces_sequence]'" :value="operation.is_alternative ? (operation.replaces_sequence || '') : ''" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][priority]'" :value="operation.is_alternative ? (operation.priority || 1) : ''" />
                                            {{-- RT-IMPL-14: all figures come from computeFigures() (one calculator); the backend re-checks and saves them as sent --}}
                                            <input type="hidden" x-bind:name="'operations['+index+'][labor_cost_rate]'" :value="computeFigures(operation).labor_cost_rate.toFixed(4)" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][machine_cost_rate]'" :value="computeFigures(operation).machine_cost_rate.toFixed(4)" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][cost_per_unit]'" :value="calculateUnitCost(operation)" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][labor_cost_per_unit]'" :value="computeFigures(operation).labor_cost_per_unit.toFixed(4)" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][machine_cost_per_unit]'" :value="computeFigures(operation).machine_cost_per_unit.toFixed(4)" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][setup_cost_per_run]'" :value="calculateSetupCostPerRun(operation)" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][section_calculation_id]'" :value="operation.is_external ? '' : ((sectionMeta(operation) || {}).section_calculation_id || '')" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][section_snapshot_number]'" :value="operation.is_external ? '' : ((sectionMeta(operation) || {}).snapshot_number || '')" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][work_center_calculation_id]'" :value="operation.is_external ? '' : ((sectionMeta(operation) || {}).work_center_calculation_id || '')" />
                                            <input type="hidden" x-bind:name="'operations['+index+'][resource_snapshot]'" :value="JSON.stringify(getResourceSnapshot(operation))" />
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

                                            <!-- Predecessor Selector or Mirror Info for Alternatives -->
                                            <div class="mt-1.5" x-show="!operation.is_parallel && index > 0">
                                                <template x-if="operation.is_alternative">
                                                    <div class="d-flex align-items-center gap-1.5 text-muted fs-11">
                                                        <span class="fs-10 text-muted text-nowrap"><i class="feather-git-commit me-1"></i>Predecessor:</span>
                                                        <span class="badge bg-light text-secondary border fs-10 font-monospace py-1" x-text="getAlternativePredecessorLabel(operation)"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!operation.is_alternative">
                                                    <div class="d-flex align-items-center gap-1.5">
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
                                                </template>
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
                                            {{-- RT-IMPL-14 (RT-19 rule 7): figures come from the section's latest calculation snapshot --}}
                                            <template x-if="!operation.is_external && sectionMeta(operation) && sectionMeta(operation).not_calculated">
                                                <div class="mt-1.5 text-danger fs-10 routing-section-not-calculated"><i class="feather-alert-triangle me-1"></i><span x-text="sectionMeta(operation).message"></span></div>
                                            </template>
                                            <template x-if="!operation.is_external && sectionMeta(operation) && !sectionMeta(operation).not_calculated">
                                                <div class="mt-1 text-muted fs-10 font-monospace" :title="'Figures from section calculation snapshot #' + sectionMeta(operation).snapshot_number">Snapshot #<span x-text="sectionMeta(operation).snapshot_number"></span></div>
                                            </template>
                                            <template x-if="!operation.is_external && sectionMeta(operation) && !sectionMeta(operation).not_calculated && sectionMeta(operation).has_pending_changes">
                                                <div class="mt-1 text-warning fs-10"><i class="feather-clock me-1"></i>Section has pending changes — recalculate the Work Center for the latest figures.</div>
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
                                                        <span class="fw-bold text-dark font-monospace" x-text="formatDuration(getSelectedMachine(operation).speed_minutes_per_unit) + ' (' + fmt4(getSelectedMachine(operation).speed_minutes_per_unit) + ' m/u)'"></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary mb-1">
                                                        <span><i class="feather-cpu me-1 text-muted"></i>Machine Cost:</span>
                                                        <span class="fw-semibold text-dark font-monospace" x-text="'{{ active_currency_symbol() }}' + fmt4(getSelectedMachine(operation).machine_cost_per_unit)"></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary mb-1">
                                                        <span><i class="feather-user me-1 text-muted"></i>Operator Cost:</span>
                                                        <span class="fw-semibold text-dark font-monospace" x-text="'{{ active_currency_symbol() }}' + fmt4(getSelectedMachine(operation).labor_cost_per_unit)"></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary pt-1 border-top border-light-subtle">
                                                        <span class="fw-semibold text-dark"><i class="feather-dollar-sign me-0.5 text-muted"></i>Operation Cost:</span>
                                                        <span class="fw-bold text-primary font-monospace fs-11" x-text="'{{ active_currency_symbol() }}' + fmt4(getSelectedMachine(operation).cost_per_unit)"></span>
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
                                                        <span class="fw-bold text-dark font-monospace" x-text="formatDuration(getSelectedBatch(operation).speed_minutes_per_unit) + ' (' + fmt4(getSelectedBatch(operation).speed_minutes_per_unit) + ' m/u)'"></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center fs-10 text-secondary pt-1 border-top border-light-subtle">
                                                        <span><i class="feather-dollar-sign me-0.5 text-muted"></i>Labor Cost:</span>
                                                        <span class="fw-bold text-info font-monospace fs-11" x-text="'{{ active_currency_symbol() }}' + fmt4(getSelectedBatch(operation).cost_per_unit)"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </td>
                                        
                                        <!-- Setup Time -->
                                        <td class="align-middle text-end" style="width: 85px; min-width: 85px;">
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][setup_time_minutes]'" class="odoo-table-input text-end px-1" :class="operation.is_external ? 'bg-light text-muted' : ''" style="width: 100%;" :style="operation.is_external ? 'cursor: not-allowed;' : ''" x-model="operation.setup_time_minutes" :readonly="operation.is_external" min="0" placeholder="0" title="Setup per operation (once per run)" />
                                            <div class="mt-1" x-show="parseFloat(operation.setup_time_minutes || 0) > 0">
                                                <span class="badge bg-soft-secondary text-secondary fs-10 font-monospace py-0.5 px-1" x-text="formatDuration(operation.setup_time_minutes)"></span>
                                            </div>
                                        </td>

                                        <!-- Setup per Piece (RT-IMPL-13) -->
                                        <td class="align-middle text-end" style="width: 85px; min-width: 85px;">
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][setup_time_per_piece_minutes]'" class="odoo-table-input text-end px-1" :class="operation.is_external ? 'bg-light text-muted' : ''" style="width: 100%;" :style="operation.is_external ? 'cursor: not-allowed;' : ''" x-model="operation.setup_time_per_piece_minutes" :readonly="operation.is_external" min="0" placeholder="0" title="Setup per piece (every unit)" />
                                            <div class="mt-1" x-show="parseFloat(operation.setup_time_per_piece_minutes || 0) > 0">
                                                <span class="badge bg-soft-secondary text-secondary fs-10 font-monospace py-0.5 px-1" x-text="formatDuration(operation.setup_time_per_piece_minutes)"></span>
                                            </div>
                                        </td>
                                        
                                        <!-- Process Time (Run) - Derived from resource capacity, non-editable -->
                                        <td class="align-middle text-end" style="width: 85px; min-width: 85px;">
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][processing_time_minutes]'" class="odoo-table-input text-end px-1 fw-semibold bg-light text-muted" style="width: 100%; cursor: not-allowed;" :value="fmt4(computeFigures(operation).processing_time_minutes)" readonly tabindex="-1" min="0" required title="Run speed is automatically derived from Machine / Manual Batch capacity" />
                                            <div class="mt-1" x-show="computeFigures(operation).processing_time_minutes > 0">
                                                <span class="badge bg-soft-primary text-primary fs-10 font-monospace py-0.5 px-1" x-text="formatDuration(computeFigures(operation).processing_time_minutes)"></span>
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
                                        <td class="align-middle text-end font-monospace fw-semibold text-nowrap" style="width: 115px; min-width: 115px;">
                                            <span class="fs-12 text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Cost / Unit = (setup per piece + run time) × (labor + machine rate) × (100 ÷ yield)" x-text="'{{ active_currency_symbol() }}' + fmt4(calculateUnitCost(operation))"></span>
                                        </td>

                                        <!-- Op Setup Cost (per run) — Short Full Review Point 1 -->
                                        <td class="align-middle text-end font-monospace text-nowrap routing-op-setup-cost" style="width: 125px; min-width: 125px;" title="Setup cost per run = setup per operation × (labor + machine rate) — once per production run, no yield">
                                            <span class="fs-12 text-dark" x-show="parseFloat(calculateSetupCostPerRun(operation)) > 0" x-text="'{{ active_currency_symbol() }}' + fmt4(calculateSetupCostPerRun(operation))"></span>
                                            <span class="text-muted" x-show="!(parseFloat(calculateSetupCostPerRun(operation)) > 0)">—</span>
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
                                         <td colspan="15" class="p-2 border-top-0">
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
                                                     <x-ui.odoo-form-ui type="input" inputType="number" step="any" min="0" label="Cost / Unit" x-bind:name="'operations['+index+'][subcontract_cost_per_unit]'" class="form-control form-control-sm fs-11" x-model="operation.subcontract_cost_per_unit" placeholder="0.00" x-bind:disabled="!operation.is_external" />
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
                    <x-ui.button type="submit" variant="primary" class="px-4">{{ __('production.save_routing_draft') }}</x-ui.button>
                    <x-ui.button :href="route('production.routing.index')" variant="light" class="px-4">{{ __('production.cancel') }}</x-ui.button>
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

                    // Populate from old('operations') if returning with validation errors/warnings, otherwise add default initial row
                    const oldOps = @json(old('operations', []));
                    const rawOpsList = Array.isArray(oldOps) ? oldOps : Object.values(oldOps || {});
                    if (rawOpsList.length > 0) {
                        rawOpsList.forEach((op, opIndex) => {
                            const newOp = {
                                uid: 'row_' + (self.uidCounter++),
                                sequence: op.sequence ? parseInt(op.sequence) : (opIndex + 1) * 10,
                                name: op.name || '',
                                operation_type: op.operation_type || 'manufacturing',
                                is_alternative: op.is_alternative == 1 || op.is_alternative === '1' || op.is_alternative === true,
                                replaces_row_uid: op.replaces_row_uid || '',
                                replaces_operation_id: op.replaces_operation_id || '',
                                replaces_sequence: op.replaces_sequence || '',
                                priority: parseInt(op.priority || 1),
                                work_center_id: op.work_center_id || '',
                                section_id: op.section_id || '',
                                machine_id: op.machine_id || '',
                                section_manual_production_id: op.section_manual_production_id || '',
                                setup_time_minutes: op.setup_time_minutes !== undefined ? op.setup_time_minutes : '0.00',
                                setup_time_per_piece_minutes: op.setup_time_per_piece_minutes !== undefined ? op.setup_time_per_piece_minutes : '',
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
                        uid: 'row_' + (this.uidCounter++),
                        sequence: nextSeq,
                        name: '',
                        operation_type: 'manufacturing',
                        is_alternative: false,
                        replaces_row_uid: '',
                        replaces_operation_id: '',
                        replaces_sequence: '',
                        priority: 1,
                        work_center_id: '',
                        section_id: '',
                        machine_id: '',
                        section_manual_production_id: '',
                        setup_time_minutes: '0.00',
                        setup_time_per_piece_minutes: '',
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
                                operation.replaces_sequence = targetOp ? targetOp.sequence : '';
                                if (targetOp) {
                                    operation.predecessor_sequence = targetOp.predecessor_sequence || '';
                                }
                            }
                            self.refreshAllPredSelect2();
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
                                // If this operation has alternative operations, sync their predecessor too!
                                self.operations.forEach(other => {
                                    if (other.is_alternative && (String(other.replaces_row_uid) === String(operation.uid) || (other.replaces_sequence && String(other.replaces_sequence) === String(operation.sequence)))) {
                                        other.predecessor_sequence = val;
                                    }
                                });
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
                    if (mFloat * 60 < 1) return '< 1s'; // anything under 1 second
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

                getAutoPredecessorTarget(operation, index) {
                    if (index === undefined || index === null) {
                        index = this.operations.findIndex(o => String(o.uid) === String(operation.uid));
                    }
                    if (index <= 0) return null;
                    // Look backwards from index - 1 to find the first non-alternative operation
                    for (let p = index - 1; p >= 0; p--) {
                        const prev = this.operations[p];
                        const isAlt = prev.is_alternative == 1 || prev.is_alternative === '1' || prev.is_alternative === true;
                        if (!isAlt) {
                            return prev;
                        }
                    }
                    return null;
                },

                getAlternativePredecessorLabel(operation) {
                    const mainOp = this.operations.find(o => String(o.uid) === String(operation.replaces_row_uid) || (operation.replaces_sequence && String(o.sequence) === String(operation.replaces_sequence)));
                    if (!mainOp) {
                        return 'Select Primary Op first';
                    }
                    const mainPredSeq = mainOp.predecessor_sequence;
                    if (mainPredSeq === '-1') {
                        return `Mirrors Op ${mainOp.sequence} (Independent)`;
                    }
                    if (mainPredSeq && mainPredSeq !== '') {
                        const predOp = this.operations.find(o => String(o.sequence) === String(mainPredSeq));
                        return `Mirrors Op ${mainOp.sequence} (Predecessor: Op ${mainPredSeq}${predOp?.name ? ': ' + predOp.name : ''})`;
                    }
                    const autoTarget = this.getAutoPredecessorTarget(mainOp);
                    return `Mirrors Op ${mainOp.sequence} (Predecessor: ${autoTarget ? 'Op ' + autoTarget.sequence : 'Initial Op'})`;
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

                    const autoTarget = this.getAutoPredecessorTarget(operation, index);
                    const autoLabel = autoTarget 
                        ? `-- Auto (Op ${autoTarget.sequence}${autoTarget.name ? ': ' + autoTarget.name : ''}) --` 
                        : '-- Auto (Previous Op) --';

                    $predSelect.empty();
                    $predSelect.append(new Option(autoLabel, '', currentVal === '', currentVal === ''));
                    $predSelect.append(new Option('-- None (Independent / Starts Immediately) --', '-1', currentVal === '-1', currentVal === '-1'));

                    priorOps.forEach(prevOp => {
                        const isAlt = prevOp.is_alternative == 1 || prevOp.is_alternative === '1' || prevOp.is_alternative === true;
                        let label = 'Op ' + prevOp.sequence + (prevOp.name ? ': ' + prevOp.name : '');
                        const valStr = String(prevOp.sequence);

                        if (isAlt) {
                            const mainOp = this.operations.find(o => String(o.uid) === String(prevOp.replaces_row_uid) || (prevOp.replaces_sequence && String(o.sequence) === String(prevOp.replaces_sequence)));
                            const mainLabel = mainOp ? 'Op ' + mainOp.sequence : 'Primary Op';
                            label += ` (Disabled: Alternative of ${mainLabel} — cannot be predecessor)`;
                            const opt = new Option(label, valStr, false, false);
                            opt.disabled = true;
                            $predSelect.append(opt);
                        } else {
                            const isSelected = valStr === currentVal;
                            $predSelect.append(new Option(label, valStr, isSelected, isSelected));
                        }
                    });

                    // Guard: if current selected predecessor was an alternative, reset to auto
                    const currentTargetOp = priorOps.find(o => String(o.sequence) === currentVal);
                    if (currentTargetOp && (currentTargetOp.is_alternative == 1 || currentTargetOp.is_alternative === '1' || currentTargetOp.is_alternative === true)) {
                        operation.predecessor_sequence = '';
                        $predSelect.val('');
                    } else {
                        $predSelect.val(currentVal);
                    }

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
                        operation.replaces_sequence = '';
                        operation.priority = 1;
                    } else {
                        if (!operation.priority || operation.priority < 1) {
                            operation.priority = 1;
                        }
                        // Clear references pointing to this operation from other alternatives
                        this.operations.forEach(other => {
                            if (other.is_alternative && (String(other.replaces_row_uid) === String(operation.uid) || String(other.replaces_operation_id) === String(operation.uid))) {
                                other.replaces_row_uid = '';
                                other.replaces_operation_id = '';
                                other.replaces_sequence = '';
                            }
                        });
                    }
                    this.refreshAllPredSelect2();
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
                                    operation.replaces_sequence = targetOp ? targetOp.sequence : '';
                                    if (targetOp) {
                                        operation.predecessor_sequence = targetOp.predecessor_sequence || '';
                                    }
                                }
                                self.refreshAllPredSelect2();
                            });
                        }
                        this.refreshAllAltSelect2();
                    });
                },

                // RT-IMPL-14: which section / Work Center snapshot the figures came from
                snapshotSource(operation) {
                    const meta = this.sectionMeta(operation) || {};
                    return {
                        work_center_id: meta.work_center_id || operation.work_center_id || null,
                        work_center_code: meta.work_center_code || null,
                        section_id: meta.section_id || operation.section_id || null,
                        section_code: meta.section_code || null,
                        section_calculation_id: meta.section_calculation_id || null,
                        section_snapshot_number: meta.snapshot_number || null,
                        work_center_calculation_id: meta.work_center_calculation_id || null,
                        work_center_snapshot_number: meta.work_center_snapshot_number || null,
                        calculated_at: meta.calculated_at || null
                    };
                },

                getResourceSnapshot(operation) {
                    if (operation.is_external) {
                        return {
                            type: 'outsourced',
                            vendor_id: operation.vendor_id || null,
                            cost_per_unit: parseFloat(operation.subcontract_cost_per_unit || 0)
                        };
                    }
                    const machine = this.getSelectedMachine(operation);
                    if (machine) {
                        return {
                            type: 'machine',
                            machine_id: machine.id,
                            code: machine.code,
                            name: machine.name,
                            capacity: parseFloat(machine.capacity || 0),
                            speed_minutes_per_unit: parseFloat(machine.speed_minutes_per_unit || 0),
                            operating_cost_per_hour: parseFloat(machine.operating_cost_per_hour || 0),
                            operator_labor_cost_per_machine_per_hour: parseFloat(operation.operator_labor_cost_per_machine_per_hour || machine.operator_labor_cost_per_hour || 0),
                            non_batch_daily_labor_cost: parseFloat(machine.non_batch_daily_labor_cost || 0),
                            section_operating_hours: parseFloat(machine.section_operating_hours || 0),
                            active_machines_count: parseInt(machine.active_machines_count || 0, 10),
                            // run-only per-unit figures of the resource (not the operation's Cost / Unit — RT-IMPL-14)
                            run_machine_cost_per_unit: parseFloat(machine.machine_cost_per_unit || 0),
                            run_labor_cost_per_unit: parseFloat(machine.labor_cost_per_unit || 0),
                            run_cost_per_unit: parseFloat(machine.cost_per_unit || 0),
                            rate_source: 'operator labor = non-batch daily labor ÷ section operating hours ÷ active machines',
                            ...this.snapshotSource(operation)
                        };
                    }
                    const batch = this.getSelectedBatch(operation);
                    if (batch) {
                        return {
                            type: 'manual_batch',
                            batch_id: batch.id,
                            batch_name: batch.batch_name,
                            shift_name: batch.shift_name,
                            capacity: parseFloat(batch.capacity_per_hour || 0),
                            speed_minutes_per_unit: parseFloat(batch.speed_minutes_per_unit || 0),
                            labor_cost_per_hour: parseFloat(batch.labor_cost_per_hour || 0),
                            run_labor_cost_per_unit: parseFloat(batch.labor_cost_per_unit || 0),
                            run_cost_per_unit: parseFloat(batch.cost_per_unit || 0),
                            rate_source: 'batch labor per hour (batch workers)',
                            ...this.snapshotSource(operation)
                        };
                    }
                    return null;
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
                        this.clearResourceDerivedTimes(operation); // RT-IMPL-15
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
                    this.clearResourceDerivedTimes(operation); // RT-IMPL-15
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
                    this.clearResourceDerivedTimes(operation); // RT-IMPL-15
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
                                operation.processing_time_minutes = parseFloat(m.speed_minutes_per_unit).toFixed(4);
                            } else {
                                this.clearResourceDerivedTimes(operation); // RT-IMPL-15
                            }
                            operation.machine_cost_rate = (parseFloat(m.operating_cost_per_hour || 0) / 60.0).toFixed(4);
                            operation.labor_cost_rate = (parseFloat(operation.operator_labor_cost_per_machine_per_hour || 0) / 60.0).toFixed(4);
                        }
                    } else {
                        this.clearResourceDerivedTimes(operation); // RT-IMPL-15
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
                                operation.processing_time_minutes = parseFloat(b.speed_minutes_per_unit).toFixed(4);
                            } else {
                                this.clearResourceDerivedTimes(operation); // RT-IMPL-15
                            }
                            operation.machine_cost_rate = '0.0000';
                            operation.labor_cost_rate = (parseFloat(b.labor_cost_per_hour || 0) / 60.0).toFixed(4);
                        }
                    } else {
                        this.clearResourceDerivedTimes(operation); // RT-IMPL-15
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

                // RT-IMPL-14 (RT-19 rule 1): ONE calculator for every figure of an operation. Rates and run time come from
                // the selected machine / batch of the section's latest calculation snapshot; rounding = backend check
                // (RoutingOperationCostCalculator). Formulas: RT-IMPL-10 / 11 / 12 / 13.
                round4(v) {
                    const n = parseFloat(v || 0);
                    return isNaN(n) ? 0 : Math.round(n * 10000) / 10000;
                },

                sectionMeta(operation) {
                    if (!operation || !operation.section_id) return null;
                    return this.sectionResourcesCache[operation.section_id] || null;
                },

                computeFigures(operation) {
                    const r4 = (v) => this.round4(v);
                    const yieldPct = parseFloat(operation.expected_yield_percentage || 100);
                    const yf = yieldPct > 0 ? (100 / yieldPct) : 1.0;

                    if (operation.is_external) {
                        return {
                            processing_time_minutes: r4(operation.processing_time_minutes), labor_cost_rate: 0, machine_cost_rate: 0,
                            labor_cost_per_unit: 0, machine_cost_per_unit: 0,
                            cost_per_unit: r4(parseFloat(operation.subcontract_cost_per_unit || 0) * yf),
                            setup_cost_per_run: 0, cycle_time_per_piece: 0, found: true
                        };
                    }

                    const meta = this.sectionMeta(operation) || {};
                    const m = this.getSelectedMachine(operation);
                    const b = m ? null : this.getSelectedBatch(operation);
                    let speed = 0, laborRate = 0, machineRate = 0;
                    if (m) {
                        speed = parseFloat(m.speed_minutes_per_unit || 0);
                        machineRate = r4(parseFloat(m.operating_cost_per_hour || 0) / 60);
                        const opLabor = (m.operator_labor_cost_per_hour !== undefined && m.operator_labor_cost_per_hour !== null)
                            ? m.operator_labor_cost_per_hour : (meta.operator_labor_cost_per_machine_per_hour || 0);
                        laborRate = r4(parseFloat(opLabor || 0) / 60);
                    } else if (b) {
                        speed = parseFloat(b.speed_minutes_per_unit || 0);
                        laborRate = r4(parseFloat(b.labor_cost_per_hour || 0) / 60);
                    }

                    const run = r4(speed);
                    const cycle = parseFloat(operation.setup_time_per_piece_minutes || 0) + run;
                    const laborPerUnit = r4(cycle * laborRate * yf);
                    const machinePerUnit = r4(cycle * machineRate * yf);

                    return {
                        processing_time_minutes: run,
                        labor_cost_rate: laborRate,
                        machine_cost_rate: machineRate,
                        labor_cost_per_unit: laborPerUnit,
                        machine_cost_per_unit: machinePerUnit,
                        cost_per_unit: r4(laborPerUnit + machinePerUnit),
                        setup_cost_per_run: r4(parseFloat(operation.setup_time_minutes || 0) * (laborRate + machineRate)),
                        cycle_time_per_piece: r4(cycle),
                        found: !!(m || b)
                    };
                },

                calculateUnitCost(operation) {
                    return this.computeFigures(operation).cost_per_unit.toFixed(4);
                },

                // RT-IMPL-13: setup per operation × (labor + machine rate) — once per run, no yield
                calculateSetupCostPerRun(operation) {
                    return this.computeFigures(operation).setup_cost_per_run.toFixed(4);
                },

                // RT-IMPL-15: whenever the run time is cleared, both setup times are cleared too
                clearResourceDerivedTimes(operation) {
                    operation.processing_time_minutes = '0.00';
                    operation.setup_time_minutes = '0.00';
                    operation.setup_time_per_piece_minutes = '';
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
