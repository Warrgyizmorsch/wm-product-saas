@extends('layouts.duralux')

@section('title', __('production.edit_routing') . ' | SaaS ERP')
@section('page-title', __('production.edit_routing'))
@section('breadcrumb', __('production.edit_routing'))

    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
        <style>
            .erp-thin-table td .select2-container--bootstrap-5 .select2-selection {
                min-height: 34px !important;
                height: 34px !important;
                padding: 3px 8px !important;
                font-size: 12px !important;
            }
            .routing-select2-dropdown {
                z-index: 2055;
            }
            .routing-select2-dropdown .select2-results__options {
                max-height: 260px;
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
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.item_to_produce')" name="product_id" id="product_id" :required="true">
                            <option value="">{{ __('production.select_product') }}</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id', $routing->product_id) == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" :label="__('production.bom') ?? 'Bill of Materials (BOM)'" name="bom_id" id="bom_id" :required="true" :searchable="false">
                            <option value="">-- {{ __('production.select_bom') ?? 'Select BOM (Active / Draft)' }} --</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.version')" name="version" :value="old('version', $routing->version)" :required="true" />

                        <x-ui.odoo-form-ui type="input" :label="__('production.start_date')" name="effective_from" inputType="date" :value="old('effective_from', $routing->effective_from?->format('Y-m-d'))" min="{{ date('Y-m-d') }}" :required="true" />

                        <input type="hidden" name="is_default" value="{{ $routing->is_default ? '1' : '0' }}">
                    </div>

                    <!-- Material Quantity Entry Basis (Header Level) -->
                    <div class="col-12 border-top pt-3">
                        <label class="form-label fw-bold text-dark fs-13 d-flex align-items-center gap-2 mb-2">
                            <i class="feather-sliders text-primary"></i>
                            <span>Material Quantity Entry Basis</span>
                            <span class="text-danger">*</span>
                            <span class="badge bg-soft-info text-info fs-11 fw-normal" x-show="selectedBom" x-cloak>
                                Linked BOM: <span class="fw-bold font-monospace" x-text="selectedBom ? selectedBom.bom_number : ''"></span> 
                                (Base Qty: <span class="fw-bold" x-text="selectedBom ? selectedBom.base_quantity : 1"></span> <span x-text="selectedBomProductUom"></span>)
                            </span>
                        </label>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded border h-100 c-pointer transition-all"
                                     :class="material_quantity_mode === 'per_unit' ? 'border-primary bg-primary-subtle shadow-sm' : 'border-light bg-light hover-bg-light'"
                                     @click="changeMode('per_unit')">
                                    <div class="form-check m-0 d-flex align-items-start gap-2">
                                        <input class="form-check-input mt-1" type="radio" name="material_quantity_mode" id="edit_mode_per_unit" value="per_unit" x-model="material_quantity_mode" @change="onModeChange('per_unit')">
                                        <div>
                                            <label class="form-check-label fw-bold text-dark fs-13 c-pointer d-block" for="edit_mode_per_unit">
                                                Per 1 Finished Product Unit
                                            </label>
                                            <p class="fs-12 text-muted mb-0 mt-1">
                                                Enter the amount of this component consumed by this operation to produce <strong>1 unit</strong> of the routing's finished product.
                                            </p>
                                            <div class="mt-2 text-primary fs-11 fw-semibold">
                                                <i class="feather-info me-1"></i>Example: 20 fasteners per table, 2 legs per table.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 rounded border h-100 c-pointer transition-all"
                                     :class="material_quantity_mode === 'per_bom_base_quantity' ? 'border-primary bg-primary-subtle shadow-sm' : 'border-light bg-light hover-bg-light'"
                                     @click="changeMode('per_bom_base_quantity')">
                                    <div class="form-check m-0 d-flex align-items-start gap-2">
                                        <input class="form-check-input mt-1" type="radio" name="material_quantity_mode" id="edit_mode_per_bom_base_quantity" value="per_bom_base_quantity" x-model="material_quantity_mode" @change="onModeChange('per_bom_base_quantity')">
                                        <div>
                                            <label class="form-check-label fw-bold text-dark fs-13 c-pointer d-block" for="edit_mode_per_bom_base_quantity">
                                                Per BOM Base Quantity
                                                <span class="badge bg-primary text-white ms-1 fs-10" x-show="selectedBom" x-text="(selectedBom ? selectedBom.base_quantity : '1') + ' ' + selectedBomProductUom"></span>
                                            </label>
                                            <p class="fs-12 text-muted mb-0 mt-1">
                                                Enter the amount of this component consumed by this operation for the quantity specified as the <strong>BOM's Base Quantity</strong> (<span class="fw-semibold" x-text="(selectedBom ? selectedBom.base_quantity : '1') + ' ' + selectedBomProductUom"></span>).
                                            </p>
                                            <div class="mt-2 text-primary fs-11 fw-semibold">
                                                <i class="feather-info me-1"></i>Example: 200 fasteners for 10 tables (BOM Base Qty).
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-text fs-11 text-muted mt-2">
                            <i class="feather-help-circle me-1"></i>
                            <span>During production planning, operation material requirements will be scaled automatically to the Production Order quantity based on this chosen entry basis.</span>
                        </div>
                    </div>

                    <div class="col-12 border-top pt-3">
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.description')" name="description" placeholder="{{ __('production.description_placeholder') }}" rows="3">{{ old('description', $routing->description) }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Live BOM Component Allocation Summary Card -->
                <div class="border rounded p-3 mb-4 bg-white shadow-sm" x-show="selectedBom" x-cloak>
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 pb-2 border-bottom gap-2">
                        <div>
                            <h5 class="fw-bold text-dark mb-0 fs-14 d-flex align-items-center gap-2">
                                <i class="feather-pie-chart text-primary"></i>
                                BOM Component Allocation Summary
                            </h5>
                            <span class="fs-11 text-muted">
                                Live tracking of BOM requirements vs. operation allocations. Every BOM component must be assigned to at least 1 operation.
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-soft-primary text-primary fs-11">
                                BOM: <span class="fw-bold" x-text="selectedBom ? selectedBom.bom_number : ''"></span>
                            </span>
                            <span class="badge bg-soft-secondary text-secondary fs-11">
                                BOM Base Qty: <span class="fw-bold" x-text="selectedBom ? selectedBom.base_quantity : 1"></span> <span x-text="selectedBomProductUom"></span>
                            </span>
                            <span class="badge fs-11" :class="material_quantity_mode === 'per_unit' ? 'bg-soft-info text-info' : 'bg-soft-dark text-dark'">
                                Basis: <span class="fw-bold" x-text="material_quantity_mode === 'per_unit' ? 'Per 1 Finished Unit' : 'Per BOM Base Qty (' + (selectedBom ? selectedBom.base_quantity : 1) + ' ' + selectedBomProductUom + ')'"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Summary Warnings / Status Banner -->
                    <template x-if="summaryErrors.length > 0">
                        <div class="alert alert-danger py-2 px-3 fs-12 mb-2 d-flex align-items-center gap-2" x-cloak>
                            <i class="feather-alert-octagon fs-16 flex-shrink-0"></i>
                            <div>
                                <strong>Validation Warning:</strong>
                                <span x-text="summaryErrors.join(' | ')"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="summaryWarnings.length > 0 && summaryErrors.length === 0">
                        <div class="alert alert-warning py-2 px-3 fs-12 mb-2 d-flex align-items-center gap-2" x-cloak>
                            <i class="feather-alert-triangle fs-16 flex-shrink-0"></i>
                            <div>
                                <strong>Partial Allocation Notice:</strong>
                                <span x-text="summaryWarnings.join(' | ')"></span>
                            </div>
                        </div>
                    </template>

                    <div class="table-responsive">
                        <table class="erp-thin-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="min-width: 200px;">BOM Component</th>
                                    <th style="width: 75px;" class="text-center">UOM</th>
                                    <th style="min-width: 140px;" class="text-end">
                                        BOM Req
                                        <small class="d-block text-muted fs-10" x-text="'(Base: ' + (selectedBom ? selectedBom.base_quantity : 1) + ' ' + selectedBomProductUom + ')'"></small>
                                    </th>
                                    <th style="min-width: 130px;" class="text-end">
                                        Per Unit Req
                                        <small class="d-block text-muted fs-10">(Normalized)</small>
                                    </th>
                                    <th style="min-width: 150px;" class="text-end">
                                        Total Allocated
                                        <small class="d-block text-muted fs-10" x-text="'(In ' + (material_quantity_mode === 'per_unit' ? 'Per Unit' : 'BOM Base') + ')'"></small>
                                    </th>
                                    <th style="min-width: 150px;" class="text-end">
                                        Remaining
                                        <small class="d-block text-muted fs-10" x-text="'(In ' + (material_quantity_mode === 'per_unit' ? 'Per Unit' : 'BOM Base') + ')'"></small>
                                    </th>
                                    <th style="width: 130px;" class="text-center">Status</th>
                                    <th style="min-width: 180px;">Assigned In Operations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in allocationSummary" :key="item.material_id">
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark fs-12" x-text="item.material_name"></span>
                                            <small class="text-muted d-block font-monospace fs-10" x-text="item.material_sku"></small>
                                        </td>
                                        <td class="text-center font-monospace fs-11 text-secondary" x-text="item.uom_code || item.uom_name"></td>
                                        <td class="text-end font-monospace fs-12">
                                            <span class="fw-semibold text-dark" x-text="formatQty(item.bom_qty) + ' ' + (item.uom_code || item.uom_name)"></span>
                                            <small class="text-muted d-block fs-10">per <span x-text="(selectedBom ? selectedBom.base_quantity : 1) + ' ' + selectedBomProductUom"></span></small>
                                        </td>
                                        <td class="text-end font-monospace fs-12">
                                            <span class="text-secondary" x-text="formatQty(item.normalized_bom_qty) + ' ' + (item.uom_code || item.uom_name)"></span>
                                            <small class="text-muted d-block fs-10">per 1 unit</small>
                                        </td>
                                        <td class="text-end font-monospace fs-12">
                                            <span :class="item.is_over ? 'text-danger fw-bold' : (item.is_assigned ? 'text-primary fw-semibold' : 'text-muted')" x-text="formatQty(item.allocated_in_mode) + ' ' + (item.uom_code || item.uom_name)"></span>
                                            <small class="text-muted d-block fs-10" x-show="material_quantity_mode === 'per_bom_base_quantity'" x-text="'(' + formatQty(item.allocated_normalized) + ' / unit)'"></small>
                                        </td>
                                        <td class="text-end font-monospace fs-12">
                                            <span :class="item.is_over ? 'text-danger fw-bold' : (item.remaining_in_mode > 0.0001 ? 'text-warning fw-semibold' : 'text-success fw-bold')" 
                                                  x-text="(item.is_over ? '-' + formatQty(Math.abs(item.remaining_in_mode)) : formatQty(item.remaining_in_mode)) + ' ' + (item.uom_code || item.uom_name)">
                                            </span>
                                            <small class="text-muted d-block fs-10" x-show="material_quantity_mode === 'per_bom_base_quantity'" x-text="'(' + formatQty(item.remaining_normalized) + ' / unit)'"></small>
                                        </td>
                                        <td class="text-center">
                                            <template x-if="!item.is_assigned">
                                                <span class="badge bg-soft-danger text-danger fs-10 text-uppercase fw-bold">
                                                    <i class="feather-x-circle me-1"></i>Unassigned
                                                </span>
                                            </template>
                                            <template x-if="item.is_assigned && item.is_over">
                                                <span class="badge bg-danger text-white fs-10 text-uppercase fw-bold" :title="'Exceeds BOM by ' + formatQty(Math.abs(item.remaining_in_mode))">
                                                    <i class="feather-alert-octagon me-1"></i>Over-Allocated
                                                </span>
                                            </template>
                                            <template x-if="item.is_assigned && !item.is_over && item.remaining_in_mode > 0.0001">
                                                <span class="badge bg-soft-warning text-warning fs-10 text-uppercase fw-semibold" :title="'Remaining: ' + formatQty(item.remaining_in_mode)">
                                                    <i class="feather-clock me-1"></i>Partial (OK)
                                                </span>
                                            </template>
                                            <template x-if="item.is_assigned && !item.is_over && Math.abs(item.remaining_in_mode) <= 0.0001">
                                                <span class="badge bg-soft-success text-success fs-10 text-uppercase fw-bold">
                                                    <i class="feather-check-circle me-1"></i>100% Allocated
                                                </span>
                                            </template>
                                        </td>
                                        <td>
                                            <template x-if="item.assigned_operations.length === 0">
                                                <span class="text-danger fs-11 fst-italic">None (Must assign to &ge;1 op)</span>
                                            </template>
                                            <template x-if="item.assigned_operations.length > 0">
                                                <div class="d-flex flex-wrap gap-1">
                                                    <template x-for="op in item.assigned_operations" :key="op.sequence">
                                                        <span class="badge bg-light border text-dark fs-10 font-monospace">
                                                            Op <span class="fw-bold" x-text="op.sequence"></span>: <span class="text-primary fw-semibold" x-text="formatQty(op.quantity)"></span> <span x-text="item.uom_code || item.uom_name"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Operations Dynamic Grid -->
                <div class="border-top pt-4 mb-4">
                    <h5 class="fw-bold text-dark mb-3 fs-14">{{ __('production.routing_operations_sequence') }}</h5>
                    
                    <div class="table-responsive mb-3">
                        <x-ui.odoo-form-ui type="table">
                            <thead>
                                <tr>
                                    <th style="width: 45px;" class="text-center">{{ __('production.seq') }}</th>
                                    <th style="min-width: 360px;">{{ __('production.operation_name_yield') }}</th>
                                    <th style="width: 160px;">{{ __('production.work_center') }}</th>
                                    <th style="width: 160px;">{{ __('production.machine') }}</th>
                                    <th style="width: 100px; min-width: 100px;" class="text-end">{{ __('production.setup') }} (m)</th>
                                    <th style="width: 100px; min-width: 100px;" class="text-end">{{ __('production.run') }} (m)</th>
                                    <th style="width: 100px; min-width: 100px;" class="text-end" title="Buffer / wait / cure / cool-down time (minutes) before downstream transfer">Wait (m)</th>
                                    <th style="width: 110px; min-width: 110px;" class="text-end" title="Cost per minute. Leave 0 to use Work Center rate">Labor ({{ active_currency_symbol() }}/m)</th>
                                    <th style="width: 40px;" class="text-center">{{ __('production.qc_gate') }}</th>
                                    <th style="width: 110px;" class="text-end">{{ __('production.actions') }}</th>
                                </tr>
                            </thead>
                            <template x-for="(operation, index) in operations" :key="operation.uid">
                                <tbody class="border-bottom">
                                      <tr class="routing-operation-row" x-init="$nextTick(() => initOperationSelects($el, operation))">
                                          <!-- Sequence -->
                                          <td class="align-middle text-center">
                                              <input type="number" x-bind:name="'operations['+index+'][sequence]'" class="odoo-table-input text-center font-monospace" x-model="operation.sequence" x-bind:readonly="autoSequence" required min="1" />
                                              <input type="hidden" x-bind:name="'operations['+index+'][operation_type]'" :value="operation.is_external ? 'outsourcing' : (operation.quality_required ? 'inspection' : 'manufacturing')" />
                                          </td>
                                          
                                          <!-- Operation Name & Details -->
                                          <td class="align-middle">
                                              <input type="text" x-bind:name="'operations['+index+'][name]'" class="odoo-table-input fw-semibold" placeholder="{{ __('production.operation_placeholder') }}" x-model="operation.name" required />
                                              
                                              <div class="d-flex align-items-center gap-2 mt-1 fs-11 text-muted flex-wrap">
                                                  <div class="form-check m-0 p-0 d-inline-flex align-items-center me-1">
                                                      <input type="checkbox" class="form-check-input mt-0 me-1 ms-0" x-model="operation.is_external" @change="if (operation.is_external) { operation.work_center_id = ''; operation.machine_id = ''; }" x-bind:name="'operations['+index+'][is_external]'" x-bind:id="'ext_edit_' + operation.uid" value="1">
                                                      <label class="form-check-label fs-11 text-secondary c-pointer mb-0" x-bind:for="'ext_edit_' + operation.uid">{{ __('production.outsourced') }}</label>
                                                  </div>

                                                  <span class="text-black-50 me-1">|</span>

                                                  <div class="d-inline-flex align-items-center gap-1 me-1">
                                                      <span class="fs-11 fw-semibold text-secondary">{{ __('production.yield') }}%:</span>
                                                      <input type="number" step="any" x-bind:name="'operations['+index+'][expected_yield_percentage]'" class="routing-sub-input text-center" style="width: 68px; min-width: 68px;" x-model="operation.expected_yield_percentage" min="0.01" max="100.00" required />
                                                  </div>

                                                  <span class="text-black-50 me-1">|</span>

                                                  <div class="form-check m-0 p-0 d-inline-flex align-items-center me-1">
                                                      <input type="checkbox" class="form-check-input mt-0 me-1 ms-0" x-model="operation.queue_threshold_enabled" x-bind:name="'operations['+index+'][queue_threshold_enabled]'" x-bind:id="'ovl_edit_' + operation.uid" value="1">
                                                      <label class="form-check-label fs-11 text-info fw-semibold c-pointer mb-0" x-bind:for="'ovl_edit_' + operation.uid" title="Next machine starts when queue threshold is hit">Queue Threshold</label>
                                                  </div>

                                                  <div class="align-items-center gap-1"
                                                       :class="(operation.queue_threshold_enabled == 1 || operation.queue_threshold_enabled === true || operation.overlap_enabled == 1 || operation.overlap_enabled === true) ? 'd-inline-flex' : 'd-none'"
                                                       x-show="operation.queue_threshold_enabled == 1 || operation.queue_threshold_enabled === true || operation.overlap_enabled == 1 || operation.overlap_enabled === true"
                                                       x-transition:enter="transition ease-out duration-300"
                                                       x-transition:enter-start="opacity-0 transform -translate-x-2"
                                                       x-transition:enter-end="opacity-100 transform translate-x-0"
                                                       x-transition:leave="transition ease-in duration-200"
                                                       x-transition:leave-start="opacity-100 transform translate-x-0"
                                                       x-transition:leave-end="opacity-0 transform -translate-x-2">
                                                      <span class="fs-11 fw-semibold text-secondary">Qty:</span>
                                                      <input type="number" step="any" x-bind:name="'operations['+index+'][transfer_batch_quantity]'" class="routing-sub-input text-center" style="width: 72px; min-width: 72px;" x-model="operation.transfer_batch_quantity" :min="(operation.queue_threshold_enabled == 1 || operation.queue_threshold_enabled === true || operation.overlap_enabled == 1 || operation.overlap_enabled === true) ? '0.0001' : '0'" :disabled="!operation.queue_threshold_enabled && !operation.overlap_enabled" />
                                                      <span class="fs-11 fw-semibold text-secondary ms-1" title="Transit / handling delay before the first transfer batch reaches the downstream operation.">Lag (m):</span>
                                                      <input type="number" step="1" x-bind:name="'operations['+index+'][transfer_lag_minutes]'" class="routing-sub-input text-center" style="width: 58px; min-width: 58px;" x-model="operation.transfer_lag_minutes" min="0" placeholder="0" title="Transit / handling delay before the first transfer batch reaches the downstream operation." :disabled="!operation.queue_threshold_enabled && !operation.overlap_enabled" />
                                                  </div>

                                                  <span class="text-black-50 me-1">|</span>

                                                  <div class="form-check m-0 p-0 d-inline-flex align-items-center me-1">
                                                      <input type="checkbox" class="form-check-input mt-0 me-1 ms-0" x-model="operation.is_parallel" x-bind:name="'operations['+index+'][is_parallel]'" x-bind:id="'par_edit_' + operation.uid" value="1">
                                                      <label class="form-check-label fs-11 text-primary fw-semibold c-pointer mb-0" x-bind:for="'par_edit_' + operation.uid" title="Independent / Parallel operation — can start immediately without waiting for preceding operations">Parallel</label>
                                                  </div>

                                                  <div class="align-items-center gap-1"
                                                       :class="(operation.is_parallel == 1 || operation.is_parallel === true) ? 'd-inline-flex' : 'd-none'"
                                                       x-show="operation.is_parallel == 1 || operation.is_parallel === true">
                                                      <span class="fs-11 fw-semibold text-secondary">Grp:</span>
                                                      <input type="text" x-bind:name="'operations['+index+'][parallel_group]'" class="routing-sub-input text-center" style="width: 70px; min-width: 70px;" x-model="operation.parallel_group" placeholder="e.g. A" title="Optional Parallel Group Name" :disabled="!operation.is_parallel" />
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

                                              <div class="mt-1 d-flex align-items-center gap-1" x-show="!operation.is_parallel && index > 0">
                                                   <span class="fs-10 text-muted text-nowrap"><i class="feather-git-commit me-1"></i>Predecessor:</span>
                                                   <div class="flex-grow-1">
                                                       <x-ui.odoo-form-ui type="select" 
                                                           x-bind:name="'operations['+index+'][predecessor_sequence]'" 
                                                           class="odoo-table-select text-muted" 
                                                           x-model="operation.predecessor_sequence" 
                                                           select2Selector="default">
                                                           <option value="">-- Auto (Previous Op) --</option>
                                                           <option value="-1">-- None (Independent / Starts Immediately) --</option>
                                                           <template x-for="(prevOp, pIdx) in getPriorOperations(index)" :key="prevOp.uid">
                                                               <option :value="prevOp.sequence" x-text="'Op ' + prevOp.sequence + (prevOp.name ? ': ' + prevOp.name : '')" :selected="operation.predecessor_sequence == prevOp.sequence"></option>
                                                           </template>
                                                       </x-ui.odoo-form-ui>
                                                                                                       </div>
                                                </div>
                                            </td>
                                          
                                          <!-- Work Center -->
                                          <td class="align-middle">
                                              <x-ui.odoo-form-ui type="select" x-bind:name="'operations['+index+'][work_center_id]'" class="odoo-table-select" x-model="operation.work_center_id" x-bind:required="!operation.is_external" x-bind:disabled="operation.is_external" select2Selector="default">
                                                  <option value="">{{ __('production.select_work_center') }}</option>
                                                  @foreach ($workCenters as $wc)
                                                      <option value="{{ $wc->id }}">{{ $wc->name }} ({{ $wc->code }})</option>
                                                  @endforeach
                                              </x-ui.odoo-form-ui>
                                          </td>
                                          
                                          <!-- Machine -->
                                          <td class="align-middle">
                                              <x-ui.odoo-form-ui type="select" x-bind:name="'operations['+index+'][machine_id]'" class="odoo-table-select" x-model="operation.machine_id" x-bind:disabled="!operation.work_center_id || operation.is_external" select2Selector="default">
                                                  <option value="">{{ __('production.no_specific_machine') }}</option>
                                                  <template x-for="m in operation.availableMachines" :key="m.id">
                                                      <option :value="m.id" x-text="m.label" :selected="m.id == operation.machine_id"></option>
                                                  </template>
                                              </x-ui.odoo-form-ui>
                                          </td>
                                          
                                          <!-- Setup Time -->
                                          <td class="align-middle" style="width: 100px; min-width: 100px;">
                                              <input type="number" step="any" x-bind:name="'operations['+index+'][setup_time_minutes]'" class="odoo-table-input text-end px-2" style="width: 100%; min-width: 85px;" x-model="operation.setup_time_minutes" min="0" required />
                                          </td>
                                          
                                          <!-- Process Time -->
                                          <td class="align-middle" style="width: 100px; min-width: 100px;">
                                              <input type="number" step="any" x-bind:name="'operations['+index+'][processing_time_minutes]'" class="odoo-table-input text-end px-2" style="width: 100%; min-width: 85px;" x-model="operation.processing_time_minutes" min="0" required />
                                          </td>
                                          
                                          <!-- Wait Time -->
                                          <td class="align-middle" style="width: 100px; min-width: 100px;">
                                              <input type="number" step="any" x-bind:name="'operations['+index+'][wait_time_minutes]'" class="odoo-table-input text-end px-2" style="width: 100%; min-width: 85px;" x-model="operation.wait_time_minutes" min="0" placeholder="0.0" title="Buffer / wait / cure / cool-down time (minutes) before downstream transfer" />
                                          </td>

                                          <!-- Labor Cost Rate ($/min) -->
                                          <td class="align-middle" style="width: 110px; min-width: 110px;">
                                              <input type="number" step="0.0001" x-bind:name="'operations['+index+'][labor_cost_rate]'" class="odoo-table-input text-end px-2" style="width: 100%; min-width: 95px;" x-model="operation.labor_cost_rate" min="0" placeholder="0.00" title="Cost per minute (Leave 0 to use Work Center hourly rate / 60)" />
                                          </td>
                                          
                                          <!-- Quality Required -->
                                          <td class="text-center align-middle">
                                              <input type="checkbox" class="form-check-input ms-0 mt-0" x-model="operation.quality_required" x-bind:name="'operations['+index+'][quality_required]'" value="1" />
                                          </td>
                                          
                                          <!-- Actions -->
                                          <td class="text-end align-middle">
                                              <div class="d-inline-flex align-items-center gap-2">
                                                  <button type="button" class="btn btn-link p-0 text-secondary border-0 bg-transparent text-decoration-none" x-on:click="moveUp(index)" x-bind:disabled="index === 0" title="{{ __('production.move_up') }}">
                                                      <i class="feather-arrow-up fs-15 fw-bold"></i>
                                                  </button>
                                                  <button type="button" class="btn btn-link p-0 text-secondary border-0 bg-transparent text-decoration-none" x-on:click="moveDown(index)" x-bind:disabled="index === operations.length - 1" title="{{ __('production.move_down') }}">
                                                      <i class="feather-arrow-down fs-15 fw-bold"></i>
                                                  </button>
                                                  <button type="button" class="btn btn-link p-0 text-primary border-0 bg-transparent text-decoration-none" x-on:click="duplicateOperation(index)" title="{{ __('production.duplicate_operation') }}">
                                                      <i class="feather-copy fs-15"></i>
                                                  </button>
                                                  <button type="button" class="btn btn-link p-0 text-danger border-0 bg-transparent text-decoration-none" x-on:click="removeOperation(index)" title="{{ __('production.remove_operation') }}">
                                                      <i class="feather-x fs-18 fw-bold"></i>
                                                  </button>
                                              </div>
                                          </td>
                                      </tr>

                                    <!-- Consumed Component Materials Row (Dedicated 2nd <tr>) -->
                                    <tr class="routing-materials-row bg-light-subtle">
                                        <td colspan="10" class="px-3 py-2 border-top-0">
                                            <div class="p-2 bg-white rounded border" style="width: 75%; max-width: 75%;">
                                                <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="fs-11 fw-bold text-dark text-uppercase">
                                                            <i class="feather-layers text-primary me-1"></i>Consumed Component Materials
                                                        </span>
                                                        <span class="badge bg-soft-primary text-primary fs-10" x-text="'Op ' + operation.sequence"></span>
                                                        <span class="badge bg-soft-info text-info fs-10" x-show="operation.materials && operation.materials.length > 0" x-text="operation.materials.length + ' component' + (operation.materials.length > 1 ? 's' : '')"></span>
                                                    </div>
                                                    <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 fs-10" @click="addMaterialToOperation(operation)" :disabled="!selectedBom" title="Add a BOM component consumed in this operation">
                                                        <i class="feather-plus me-1"></i>Add Material
                                                    </button>
                                                </div>

                                                <!-- If no BOM is selected -->
                                                <template x-if="!selectedBom">
                                                    <div class="text-muted fs-11 fst-italic py-1 px-1">
                                                        <i class="feather-info me-1 text-secondary"></i>Select a BOM above to assign component materials to this operation.
                                                    </div>
                                                </template>

                                                <!-- If BOM selected but no materials assigned yet -->
                                                <template x-if="selectedBom && (!operation.materials || operation.materials.length === 0)">
                                                    <div class="text-muted fs-11 fst-italic py-1 px-1">
                                                        No component materials assigned to this operation. Click <span class="fw-semibold text-primary">"+ Add Material"</span> above to allocate a BOM component.
                                                    </div>
                                                </template>

                                                <!-- Materials Repeater List -->
                                                <template x-if="selectedBom && operation.materials && operation.materials.length > 0">
                                                    <div class="d-flex flex-column gap-1.5 mt-1">
                                                        <template x-for="(mat, mIdx) in operation.materials" :key="mat.uid">
                                                            <div class="d-flex align-items-center gap-2 bg-light-subtle p-1.5 rounded border">
                                                                <!-- Material Dropdown (Restricted to Selected BOM Components) -->
                                                                <div class="flex-grow-1" style="max-width: 420px;">
                                                                    <select class="form-select form-select-sm fs-11 text-dark fw-semibold py-1" 
                                                                            style="height: 30px;"
                                                                            x-bind:name="'operations['+index+'][materials]['+mIdx+'][material_id]'" 
                                                                            x-model="mat.material_id"
                                                                            @change="onMaterialSelected(mat)"
                                                                            required>
                                                                        <option value="">-- Select BOM Component --</option>
                                                                        <template x-for="comp in selectedBomComponents" :key="comp.material_id">
                                                                            <option :value="comp.material_id" 
                                                                                    x-text="comp.material_name + ' (' + comp.material_sku + ')'" 
                                                                                    :selected="mat.material_id == comp.material_id"></option>
                                                                        </template>
                                                                    </select>
                                                                </div>

                                                                <!-- Read-only UOM Badge -->
                                                                <div style="width: 80px; min-width: 80px;" class="text-center">
                                                                    <span class="badge bg-white border text-secondary font-monospace fs-11 w-100 py-1" 
                                                                          x-text="mat.uom_code || mat.uom_name || 'UOM'"
                                                                          title="Component UOM (from BOM)"></span>
                                                                    <input type="hidden" x-bind:name="'operations['+index+'][materials]['+mIdx+'][uom_id]'" :value="mat.uom_id">
                                                                </div>

                                                                <!-- Quantity Input with Basis Addon -->
                                                                <div class="d-flex align-items-center gap-1.5" style="width: 180px; min-width: 180px;">
                                                                    <input type="number" 
                                                                           step="any" 
                                                                           min="0.0001" 
                                                                           x-bind:name="'operations['+index+'][materials]['+mIdx+'][quantity]'" 
                                                                           class="routing-sub-input text-end font-monospace" 
                                                                           style="width: 85px; min-width: 85px;" 
                                                                           x-model="mat.quantity" 
                                                                           required 
                                                                           placeholder="0.00">
                                                                    <span class="fs-10 text-muted font-monospace text-truncate" 
                                                                          x-text="material_quantity_mode === 'per_unit' ? '/ unit' : ('/ ' + (selectedBom ? selectedBom.base_quantity : 1) + ' ' + selectedBomProductUom)"
                                                                          :title="material_quantity_mode === 'per_unit' ? 'Quantity per 1 finished product unit' : ('Quantity per BOM Base Quantity (' + (selectedBom ? selectedBom.base_quantity : 1) + ' ' + selectedBomProductUom + ')')"></span>
                                                                </div>

                                                                <!-- Remove Material Button -->
                                                                <button type="button" 
                                                                        class="btn btn-xs btn-outline-danger d-inline-flex align-items-center gap-1 py-1 px-2 fs-11 ms-auto" 
                                                                        @click="removeMaterialFromOperation(operation, mIdx)"
                                                                        title="Remove component material">
                                                                    <i class="feather-trash-2 fs-12"></i>
                                                                    <span>Delete</span>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>

                                      <!-- Subcontract Details Expanded Panel -->
                                     <tr x-show="operation.is_external" class="bg-light-subtle">
                                         <td colspan="10" class="p-2 border-top-0">
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
            const allBoms = @json($boms ?? []);

        function updateBomOptions(productId, selectedBomId) {
            const $bomSelect = $('#bom_id');
            const currentVal = (selectedBomId !== undefined && selectedBomId !== null && selectedBomId !== '') 
                ? String(selectedBomId) 
                : String($bomSelect.val() || '');
            
            $bomSelect.empty();
            $bomSelect.append($('<option>', {
                value: '',
                text: '-- {{ __('production.select_bom') ?? 'Select BOM (Active / Draft)' }} --'
            }));

            if (!productId) {
                $bomSelect.val('').trigger('change');
                return;
            }

            // Only active, approved, or draft BOMs for that product - never cancelled
            const filtered = allBoms.filter(b => String(b.product_id) === String(productId) && ['approved', 'active', 'draft'].includes(b.status));
            
            let matchedId = null;
            if (currentVal && filtered.some(b => String(b.id) === currentVal)) {
                matchedId = currentVal;
            } else {
                // If old routing had a cancelled/missing BOM, auto-select active/approved BOM, otherwise draft
                const activeBom = filtered.find(b => b.status === 'active' || b.status === 'approved') || filtered.find(b => b.status === 'draft');
                if (activeBom) {
                    matchedId = String(activeBom.id);
                }
            }

            filtered.forEach(b => {
                const statusLabel = b.status.charAt(0).toUpperCase() + b.status.slice(1);
                const isSelected = String(b.id) === matchedId;
                $bomSelect.append($('<option>', {
                    value: b.id,
                    text: `${b.bom_number} - ${b.bom_name} (v${b.version} - ${statusLabel})`,
                    selected: isSelected
                }));
            });

            if (matchedId) {
                $bomSelect.val(matchedId).trigger('change');
            } else {
                $bomSelect.val('').trigger('change');
            }
        }

        function confirmBomChange(onConfirm, onCancel) {
            if (typeof window.confirmAction === 'function') {
                let confirmed = false;
                const modalEl = document.getElementById('globalConfirmModal');
                const onHidden = function() {
                    if (modalEl) modalEl.removeEventListener('hidden.bs.modal', onHidden);
                    if (!confirmed && typeof onCancel === 'function') {
                        onCancel();
                    }
                };
                if (modalEl) {
                    modalEl.addEventListener('hidden.bs.modal', onHidden);
                }
                window.confirmAction({
                    title: 'Change Bill of Materials (BOM)?',
                    message: 'Changing the BOM will clear all existing operation material assignments, as they belong to the previously selected BOM. Operation sequence, workstations, and timings will be preserved. Do you want to proceed?',
                    variant: 'warning',
                    confirmText: 'Yes, Change BOM',
                    cancelButtonText: 'Cancel'
                }, function() {
                    confirmed = true;
                    if (typeof onConfirm === 'function') {
                        onConfirm();
                    }
                });
            } else {
                if (confirm('Changing the BOM will clear all existing operation material assignments. Do you want to proceed?')) {
                    if (typeof onConfirm === 'function') onConfirm();
                } else {
                    if (typeof onCancel === 'function') onCancel();
                }
            }
        }

        function routingForm() {
            return {
                operations: [],
                autoSequence: true,
                uidCounter: 1,
                matUidCounter: 1,
                material_quantity_mode: @js(old('material_quantity_mode', $routing->material_quantity_mode ?? 'per_unit')),
                selectedBomId: @js(old('bom_id', $routing->bom_id ?? '')),
                previousBomId: @js(old('bom_id', $routing->bom_id ?? '')),
                selectedProductId: @js(old('product_id', $routing->product_id ?? '')),
                previousProductId: @js(old('product_id', $routing->product_id ?? '')),

                get selectedBom() {
                    if (!this.selectedBomId) return null;
                    return allBoms.find(b => String(b.id) === String(this.selectedBomId)) || null;
                },

                get selectedBomProductUom() {
                    if (!this.selectedBom) return 'Units';
                    return this.selectedBom.base_uom?.name || this.selectedBom.product?.uom?.name || 'Units';
                },

                get selectedBomComponents() {
                    if (!this.selectedBom || !this.selectedBom.items) return [];
                    return this.selectedBom.items
                        .filter(item => !item.is_alternative)
                        .map(item => ({
                            material_id: item.material_id,
                            material_name: item.material ? item.material.name : ('Material #' + item.material_id),
                            material_sku: item.material ? item.material.sku : '',
                            uom_id: item.uom_id,
                            uom_name: item.uom ? item.uom.name : '',
                            uom_code: item.uom ? item.uom.code : '',
                            bom_quantity: parseFloat(item.quantity) || 0
                        }));
                },

                get allocationSummary() {
                    const bom = this.selectedBom;
                    if (!bom || !this.selectedBomComponents.length) return [];

                    const baseQty = parseFloat(bom.base_quantity) || 1.0;
                    const isPerUnit = this.material_quantity_mode === 'per_unit';

                    return this.selectedBomComponents.map(comp => {
                        const bomQty = parseFloat(comp.bom_quantity) || 0;
                        const normBomQty = baseQty > 0 ? (bomQty / baseQty) : 0;

                        // Find all allocations for this component across all operations
                        const assignedOps = [];
                        let totalAllocInMode = 0;

                        this.operations.forEach(op => {
                            if (op.materials && Array.isArray(op.materials)) {
                                op.materials.forEach(m => {
                                    if (String(m.material_id) === String(comp.material_id)) {
                                        const qty = parseFloat(m.quantity) || 0;
                                        totalAllocInMode += qty;
                                        assignedOps.push({
                                            sequence: op.sequence,
                                            operation_name: op.name,
                                            quantity: qty
                                        });
                                    }
                                });
                            }
                        });

                        const allocatedNormalized = isPerUnit ? totalAllocInMode : (baseQty > 0 ? (totalAllocInMode / baseQty) : 0);
                        const targetInMode = isPerUnit ? normBomQty : bomQty;
                        const remainingInMode = targetInMode - totalAllocInMode;
                        const remainingNormalized = normBomQty - allocatedNormalized;
                        const isOver = allocatedNormalized > (normBomQty + 0.000001);
                        const isAssigned = assignedOps.length > 0;

                        return {
                            material_id: comp.material_id,
                            material_name: comp.material_name,
                            material_sku: comp.material_sku,
                            uom_name: comp.uom_name,
                            uom_code: comp.uom_code,
                            bom_qty: bomQty,
                            normalized_bom_qty: normBomQty,
                            allocated_in_mode: totalAllocInMode,
                            allocated_normalized: allocatedNormalized,
                            remaining_in_mode: remainingInMode,
                            remaining_normalized: remainingNormalized,
                            is_assigned: isAssigned,
                            is_over: isOver,
                            assigned_operations: assignedOps
                        };
                    });
                },

                get summaryErrors() {
                    const errors = [];
                    this.allocationSummary.forEach(item => {
                        if (!item.is_assigned) {
                            errors.push(`'${item.material_name}' is not assigned to any operation`);
                        }
                        if (item.is_over) {
                            const excess = Math.abs(item.remaining_in_mode);
                            errors.push(`'${item.material_name}' is over-allocated by ${this.formatQty(excess)} ${item.uom_code || item.uom_name}`);
                        }
                    });
                    return errors;
                },

                get summaryWarnings() {
                    const warnings = [];
                    this.allocationSummary.forEach(item => {
                        if (item.is_assigned && !item.is_over && item.remaining_in_mode > 0.0001) {
                            warnings.push(`'${item.material_name}' has ${this.formatQty(item.remaining_in_mode)} ${item.uom_code || item.uom_name} unallocated`);
                        }
                    });
                    return warnings;
                },

                formatQty(val) {
                    const num = parseFloat(val);
                    if (isNaN(num)) return '0.00';
                    return (Math.round(num * 10000) / 10000).toString();
                },

                changeMode(newMode) {
                    if (this.material_quantity_mode === newMode) return;
                    this.onModeChange(newMode);
                },

                onModeChange(newMode) {
                    if (this.material_quantity_mode === newMode) return;

                    const oldMode = this.material_quantity_mode;
                    const baseQty = parseFloat(this.selectedBom?.base_quantity) || 1.0;

                    let hasEnteredQuantities = false;
                    this.operations.forEach(op => {
                        if (op.materials) {
                            op.materials.forEach(m => {
                                if (parseFloat(m.quantity) > 0) hasEnteredQuantities = true;
                            });
                        }
                    });

                    if (hasEnteredQuantities && baseQty > 0) {
                        this.operations.forEach(op => {
                            if (op.materials) {
                                op.materials.forEach(m => {
                                    const qty = parseFloat(m.quantity);
                                    if (!isNaN(qty) && qty > 0) {
                                        if (oldMode === 'per_unit' && newMode === 'per_bom_base_quantity') {
                                            m.quantity = (qty * baseQty).toFixed(4);
                                        } else if (oldMode === 'per_bom_base_quantity' && newMode === 'per_unit') {
                                            m.quantity = (qty / baseQty).toFixed(4);
                                        }
                                    }
                                });
                            }
                        });
                    }

                    this.material_quantity_mode = newMode;
                },

                addMaterialToOperation(operation) {
                    if (!operation.materials) {
                        operation.materials = [];
                    }
                    const defaultComp = this.selectedBomComponents.length > 0 ? this.selectedBomComponents[0] : null;
                    operation.materials.push({
                        uid: this.matUidCounter++,
                        material_id: defaultComp ? defaultComp.material_id : '',
                        quantity: '',
                        uom_id: defaultComp ? defaultComp.uom_id : '',
                        uom_name: defaultComp ? defaultComp.uom_name : '',
                        uom_code: defaultComp ? defaultComp.uom_code : ''
                    });
                },

                removeMaterialFromOperation(operation, mIdx) {
                    if (operation.materials) {
                        operation.materials.splice(mIdx, 1);
                    }
                },

                onMaterialSelected(mat) {
                    const comp = this.selectedBomComponents.find(c => String(c.material_id) === String(mat.material_id));
                    if (comp) {
                        mat.uom_id = comp.uom_id;
                        mat.uom_name = comp.uom_name;
                        mat.uom_code = comp.uom_code;
                    }
                },

                init() {
                    const self = this;

                    // Listen for BOM changes with confirmation if material assignments exist
                    $('#bom_id').on('change', function() {
                        const newBomId = $(this).val();
                        if (String(newBomId) === String(self.selectedBomId)) return;

                        const hasAssignments = self.operations.some(op => op.materials && op.materials.length > 0 && op.materials.some(m => m.material_id));
                        if (hasAssignments) {
                            confirmBomChange(
                                function onConfirm() {
                                    self.previousBomId = self.selectedBomId;
                                    self.selectedBomId = newBomId;
                                    self.previousProductId = self.selectedProductId;
                                    // Clear material assignments from all operations
                                    self.operations.forEach(op => {
                                        op.materials = [];
                                    });
                                },
                                function onCancel() {
                                    $('#bom_id').val(self.selectedBomId);
                                    if (self.previousProductId) {
                                        self.selectedProductId = self.previousProductId;
                                        $('#product_id').val(self.previousProductId).trigger('change.select2');
                                    }
                                }
                            );
                        } else {
                            self.previousBomId = self.selectedBomId;
                            self.selectedBomId = newBomId;
                            self.previousProductId = self.selectedProductId;
                        }
                    });

                    // Initialize dynamic BOM options
                    const initProdId = $('#product_id').val();
                    const initBomId = @js(old('bom_id', $routing->bom_id ?? ''));
                    this.selectedProductId = String(initProdId || '');
                    this.previousProductId = this.selectedProductId;
                    if (initBomId) {
                        this.selectedBomId = String(initBomId);
                        this.previousBomId = String(initBomId);
                    }

                    updateBomOptions(initProdId, initBomId);

                    const currentBomVal = $('#bom_id').val();
                    if (currentBomVal) {
                        this.selectedBomId = String(currentBomVal);
                        this.previousBomId = String(currentBomVal);
                    }

                    $('#product_id').on('change.select2 change', function() {
                        const newProd = $(this).val();
                        if (String(newProd) === String(self.selectedProductId)) return;
                        self.previousProductId = self.selectedProductId;
                        self.selectedProductId = String(newProd || '');
                        updateBomOptions(newProd, null);
                    });

                    // Populate from old('operations') if returning with validation errors/warnings, otherwise from database
                    const oldOps = @json(old('operations', []));
                    const rawOpsList = Array.isArray(oldOps) ? oldOps : Object.values(oldOps || {});
                    if (rawOpsList.length > 0) {
                        rawOpsList.forEach((op, opIndex) => {
                            let mats = [];
                            if (op.materials) {
                                const rawMats = Array.isArray(op.materials) ? op.materials : Object.values(op.materials);
                                mats = rawMats.map(m => {
                                    const comp = self.selectedBomComponents.find(c => String(c.material_id) === String(m.material_id));
                                    return {
                                        uid: self.matUidCounter++,
                                        material_id: m.material_id || '',
                                        quantity: m.quantity || '1',
                                        uom_id: m.uom_id || (comp ? comp.uom_id : ''),
                                        uom_name: comp ? comp.uom_name : (m.uom_name || ''),
                                        uom_code: comp ? comp.uom_code : (m.uom_code || '')
                                    };
                                });
                            }
                            self.operations.push({
                                uid: self.uidCounter++,
                                sequence: op.sequence ? parseInt(op.sequence) : (opIndex + 1) * 10,
                                name: op.name || '',
                                operation_type: op.operation_type || 'manufacturing',
                                work_center_id: op.work_center_id || '',
                                machine_id: op.machine_id || '',
                                setup_time_minutes: op.setup_time_minutes !== undefined ? op.setup_time_minutes : '0.00',
                                processing_time_minutes: op.processing_time_minutes !== undefined ? op.processing_time_minutes : '0.00',
                                wait_time_minutes: op.wait_time_minutes !== undefined ? op.wait_time_minutes : '0.00',
                                labor_cost_rate: op.labor_cost_rate !== undefined ? op.labor_cost_rate : '0.0000',
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
                                materials: mats,
                                is_parallel: op.is_parallel == 1 || op.is_parallel === '1' || op.is_parallel === true,
                                parallel_group: op.parallel_group || '',
                                predecessor_sequence: op.predecessor_sequence || '',
                                availableMachines: []
                            });
                            if (op.work_center_id) {
                                self.loadMachinesForOperation(opIndex, op.machine_id);
                            }
                        });
                    } else {
                        // Populate from existing operations database collection
                        @foreach ($routing->operations as $op)
                            this.operations.push({
                                uid: this.uidCounter++,
                                sequence: {{ $op->sequence }},
                                name: "{{ addslashes($op->name) }}",
                                operation_type: "{{ $op->operation_type }}",
                                work_center_id: "{{ $op->work_center_id }}",
                                machine_id: "{{ $op->machine_id ?? '' }}",
                                setup_time_minutes: "{{ number_format($op->setup_time_minutes, 2, '.', '') }}",
                                processing_time_minutes: "{{ number_format($op->processing_time_minutes, 2, '.', '') }}",
                                wait_time_minutes: "{{ number_format($op->wait_time_minutes ?? 0, 2, '.', '') }}",
                                labor_cost_rate: "{{ number_format(convert_from_base($op->labor_cost_rate ?? 0), 4, '.', '') }}",
                                description: "{{ addslashes($op->description ?? '') }}",
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
                                parallel_group: "{{ $op->parallel_group ?? '' }}",
                                predecessor_sequence: "{{ $op->previousOperation?->sequence ?? ($op->previous_operation_id === null && $loop->index > 0 && !$op->is_parallel ? '-1' : '') }}",
                                materials: [
                                    @foreach ($op->materials as $opMat)
                                    {
                                        uid: this.matUidCounter++,
                                        material_id: "{{ $opMat->material_id }}",
                                        quantity: "{{ number_format($opMat->quantity, 4, '.', '') }}",
                                        uom_id: "{{ $opMat->uom_id }}",
                                        uom_name: "{{ addslashes($opMat->uom?->name ?? '') }}",
                                        uom_code: "{{ addslashes($opMat->uom?->code ?? '') }}"
                                    },
                                    @endforeach
                                ],
                                availableMachines: []
                            });
                            // Fetch machines for the preloaded work center
                            this.loadMachinesForOperation(this.operations.length - 1, "{{ $op->machine_id ?? '' }}");
                        @endforeach
                    }

                    if (this.operations.length === 0) {
                        this.addOperation();
                    }

                    this.$watch('autoSequence', value => {
                        if (value) {
                            this.recalculateSequences();
                        }
                    });
                    this.$nextTick(() => this.initAllOperationSelects());
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
                        work_center_id: '',
                        machine_id: '',
                        setup_time_minutes: '0.00',
                        processing_time_minutes: '0.00',
                        wait_time_minutes: '0.00',
                        labor_cost_rate: '0.0000',
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
                        materials: [],
                        is_parallel: false,
                        parallel_group: '',
                        predecessor_sequence: '',
                        availableMachines: []
                    });
                    this.$nextTick(() => this.initAllOperationSelects());
                },

                getPriorOperations(currentIndex) {
                    return this.operations.filter((op, idx) => idx < currentIndex);
                },

                removeOperation(index) {
                    if (this.operations.length > 1) {
                        this.operations.splice(index, 1);
                        this.recalculateSequences();
                        this.$nextTick(() => this.initAllOperationSelects());
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
                    if (clone.materials && Array.isArray(clone.materials)) {
                        clone.materials.forEach(m => {
                            m.uid = this.matUidCounter++;
                        });
                    }

                    let newSeq = parseInt(original.sequence) + 5;
                    const existingSeqs = this.operations.map(o => parseInt(o.sequence));
                    while (existingSeqs.includes(newSeq)) {
                        newSeq += 5;
                    }
                    clone.sequence = newSeq;

                    this.operations.splice(index + 1, 0, clone);
                    this.recalculateSequences();

                    if (clone.work_center_id) {
                        this.loadMachinesForOperation(index + 1);
                    }
                    this.$nextTick(() => this.initAllOperationSelects());
                },

                moveUp(index) {
                    if (index > 0) {
                        const temp = this.operations[index];
                        this.operations[index] = this.operations[index - 1];
                        this.operations[index - 1] = temp;
                        this.recalculateSequences();
                        this.$nextTick(() => this.initAllOperationSelects());
                    }
                },

                moveDown(index) {
                    if (index < this.operations.length - 1) {
                        const temp = this.operations[index];
                        this.operations[index] = this.operations[index + 1];
                        this.operations[index + 1] = temp;
                        this.recalculateSequences();
                        this.$nextTick(() => this.initAllOperationSelects());
                    }
                },

                recalculateSequences() {
                    if (this.autoSequence) {
                        this.operations.forEach((op, index) => {
                            op.sequence = (index + 1) * 10;
                        });
                    }
                },

                workCenterChanged(index) {
                    const op = this.operations[index];
                    op.machine_id = '';
                    op.availableMachines = [];

                    if (op.work_center_id) {
                        this.loadMachinesForOperation(index);
                    }
                    this.$nextTick(() => this.initAllOperationSelects());
                },

                loadMachinesForOperation(index, selectedMachineId = null) {
                    const op = this.operations[index];
                    if (!op || !op.work_center_id) return;
                    fetch(`{{ url('production/machines/by-work-center') }}/${op.work_center_id}`)
                        .then(res => res.json())
                        .then(data => {
                            op.availableMachines = data;
                            if (selectedMachineId) {
                                op.machine_id = selectedMachineId;
                            }
                            this.$nextTick(() => this.initAllOperationSelects());
                        })
                        .catch(err => console.error('Failed to load machines:', err));
                },

                initOperationSelects(rowEl, operation) {
                    var self = this;
                    $(rowEl).find('[data-select2-selector="default"]').each(function() {
                        var $select = $(this);
                        var nameAttr = $select.attr('name') || '';
                        var isDynamic = nameAttr.indexOf('machine_id') !== -1 || nameAttr.indexOf('predecessor_sequence') !== -1;

                        if ($select.data('select2-initialized') && $select.hasClass('select2-hidden-accessible')) {
                            if (isDynamic) {
                                $select.select2('destroy');
                            } else {
                                $select.trigger('change.select2');
                                return;
                            }
                        }

                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }

                        $select.data('select2-initialized', true);
                        $select.select2(self.select2RowOptions());

                        // Sync values for select2
                        if (nameAttr.indexOf('operation_type') !== -1) {
                            if (operation.operation_type) $select.val(operation.operation_type).trigger('change.select2');
                        } else if (nameAttr.indexOf('work_center_id') !== -1) {
                            if (operation.work_center_id) $select.val(operation.work_center_id).trigger('change.select2');
                        } else if (nameAttr.indexOf('predecessor_sequence') !== -1) {
                            if (operation.predecessor_sequence !== undefined && operation.predecessor_sequence !== '') {
                                $select.val(operation.predecessor_sequence).trigger('change.select2');
                            }
                        } else if (nameAttr.indexOf('machine_id') !== -1) {
                            if (operation.machine_id) {
                                $select.val(operation.machine_id).trigger('change.select2');
                            }
                        }

                        $select.off('change.routing-operation-select').on('change.routing-operation-select', function() {
                            var val = $select.val();
                            var nameAttr = $select.attr('name') || '';

                            if (nameAttr.indexOf('operation_type') !== -1) {
                                operation.operation_type = val;
                            } else if (nameAttr.indexOf('work_center_id') !== -1) {
                                operation.work_center_id = val;
                                self.workCenterChanged(self.operations.indexOf(operation));
                            } else if (nameAttr.indexOf('machine_id') !== -1) {
                                operation.machine_id = val;
                            } else if (nameAttr.indexOf('predecessor_sequence') !== -1) {
                                operation.predecessor_sequence = val;
                            }

                            this.dispatchEvent(new Event('input', { bubbles: true }));
                        });
                    });
                },

                initAllOperationSelects() {
                    var self = this;
                    $('.routing-operation-row').each(function(index) {
                        if (self.operations[index]) {
                            self.initOperationSelects(this, self.operations[index]);
                        }
                    });
                },

                select2RowOptions() {
                    return {
                        theme: "bootstrap-5",
                        width: '100%',
                        dropdownParent: $('body'),
                        dropdownCssClass: 'routing-select2-dropdown'
                    };
                }
            }
        }
        </script>
@endsection
