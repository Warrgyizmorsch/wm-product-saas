@extends('layouts.duralux')

@section('title', __('production.create_bom') . ' | SaaS ERP')
@section('page-title', __('production.create_bom'))
@section('breadcrumb', __('production.create_bom'))

@section('content')
    <div class="erp-single-panel bg-white" x-data="bomForm">        

        <!-- Validation Errors & Warning Banners (Rendered via Toast Component) -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="{{ __('production.validation_failed') ?? 'Validation Failed' }}: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <form method="POST" action="{{ route('production.boms.store') }}">
            @csrf
            @if(request()->has('parent_product_id'))
                <input type="hidden" name="parent_product_id" value="{{ request('parent_product_id') }}">
            @endif

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.new_bill_of_materials') }}</h4>
                    <a href="{{ route('production.boms.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- BOM Header Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.bom_number')" name="bom_number" placeholder="{{ __('production.bom_number_placeholder') }}" :value="old('bom_number', 'AUTO')" :required="true" :error-text="$errors->first('bom_number')" alpineError="errors.bom_number" />
                        <x-ui.odoo-form-ui type="select" :label="__('production.item_to_produce')" name="product_id" id="product_id" :required="true" data-master="product" :error-text="$errors->first('product_id')" alpineError="errors.product_id">
                            <option value="">{{ __('production.select_product') }}</option>
                            <option value="__ADD_NEW__" class="fw-bold text-primary">{{ __('production.add_new_product') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id', $selectedProductId ?? '') == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" :label="__('production.bom_name')" name="bom_name" placeholder="{{ __('production.bom_name_placeholder') }}" :value="old('bom_name')" :required="true" :error-text="$errors->first('bom_name')" alpineError="errors.bom_name" />

                        <x-ui.odoo-form-ui type="select" :label="__('production.bom_type')" name="bom_type" :required="true" :error-text="$errors->first('bom_type')" alpineError="errors.bom_type">
                            <option value="manufacturing" @selected(old('bom_type', 'manufacturing') === 'manufacturing')>{{ __('production.bom_type_manufacturing') }}</option>
                            <option value="engineering" @selected(old('bom_type') === 'engineering')>{{ __('production.bom_type_engineering') }}</option>
                            <option value="sales" @selected(old('bom_type') === 'sales')>{{ __('production.bom_type_sales') }}</option>
                            <option value="phantom" @selected(old('bom_type') === 'phantom')>{{ __('production.bom_type_phantom') }}</option>
                            <option value="subcontracting" @selected(old('bom_type') === 'subcontracting')>{{ __('production.bom_type_subcontracting') }}</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" :label="__('production.usage_context')" name="usage_context" :required="true" :error-text="$errors->first('usage_context')" alpineError="errors.usage_context">
                            <option value="manufacturing" @selected(old('usage_context', 'manufacturing') === 'manufacturing')>{{ __('production.usage_context_manufacturing') }}</option>
                            <option value="engineering" @selected(old('usage_context') === 'engineering')>{{ __('production.usage_context_engineering') }}</option>
                            <option value="prototype" @selected(old('usage_context') === 'prototype')>{{ __('production.usage_context_prototype') }}</option>
                            <option value="costing" @selected(old('usage_context') === 'costing')>{{ __('production.usage_context_costing') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.base_quantity')" name="base_quantity" inputType="number" step="any" placeholder="1.0" :value="old('base_quantity', '1.00')" :required="true" :error-text="$errors->first('base_quantity')" alpineError="errors.base_quantity" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.base_uom')" name="base_uom_id" :required="true" data-master="uom" :error-text="$errors->first('base_uom_id')" alpineError="errors.base_uom_id">
                            <option value="">{{ __('production.select_uom') }}</option>
                            <option value="__ADD_NEW__" class="fw-bold text-primary">{{ __('production.add_new_uom') }}</option>
                            @foreach ($uoms as $uom)
                                <option value="{{ $uom->id }}" @selected(old('base_uom_id') == $uom->id)>{{ $uom->name }} ({{ $uom->code }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>                        
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.revision_notes')" name="notes" placeholder="{{ __('production.notes_placeholder') }}" rows="3" :error-text="$errors->first('notes')" alpineError="errors.notes">{{ old('notes') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Advanced Configuration Collapsible settings using Alpine.js -->
                <div class="border rounded mb-4">
                    <div class="bg-light py-2 px-3 fs-13 fw-semibold text-dark d-flex justify-content-between align-items-center c-pointer" @click="showAdvanced = !showAdvanced">
                        <span><i class="feather-settings me-2"></i>{{ __('production.advanced_configuration') ?? 'Advanced Configuration & Lifecycle Dates' }}</span>
                        <i class="feather-chevron-down transition-all" style="transition: transform 0.2s;" :style="showAdvanced ? 'transform: rotate(180deg);' : ''"></i>
                    </div>
                    <div class="bg-white" 
                         style="transition: max-height 0.25s ease-out, opacity 0.25s ease-out, padding 0.25s ease-out; overflow: hidden;"
                         :style="showAdvanced ? 'max-height: 1000px; padding: 1rem; opacity: 1; border-top: 1px solid #dee2e6;' : 'max-height: 0px; padding: 0 1rem; opacity: 0; border-top: none;'">
                        <div class="row g-3 fs-13 text-dark">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('production.version')" name="version" placeholder="{{ __('production.version_placeholder') }}" :value="old('version', '1.0.0')" :required="true" :error-text="$errors->first('version')" alpineError="errors.version" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('production.routing_reference')" name="routing_id" id="routing_id" x-model="selectedRoutingId" :error-text="$errors->first('routing_id')" alpineError="errors.routing_id">
                                    <option value="">{{ __('production.no_routing') }}</option>
                                    @foreach($routings as $rt)
                                        <option value="{{ $rt->id }}" data-product-id="{{ $rt->product_id }}">{{ $rt->routing_number }} - {{ $rt->name }} (v{{ $rt->version }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('production.start_date') ?? 'Start Date'" name="effective_date" inputType="date" :value="old('effective_date', date('Y-m-d'))" :required="true" :error-text="$errors->first('effective_date')" alpineError="errors.effective_date" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('production.expiry_date') ?? 'Expiry Date'" name="expiry_date" inputType="date" :value="old('expiry_date')" :error-text="$errors->first('expiry_date')" alpineError="errors.expiry_date" />
                            </div>
                            <div class="col-md-12">
                                <x-ui.odoo-form-ui type="input" :label="__('production.revision_reason') ?? 'Revision Reason'" name="revision_reason" placeholder="{{ __('production.revision_reason_placeholder') }}" :value="old('revision_reason')" :error-text="$errors->first('revision_reason')" alpineError="errors.revision_reason" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Headers -->
                <x-ui.horizontal-tabs id="bomFormTabs" :tabs="[
                    ['id' => 'tab-components', 'label' => __('production.components'), 'active' => true, 'icon' => 'feather-list'],
                    ['id' => 'tab-operations', 'label' => __('production.operations'), 'icon' => 'feather-sliders']
                ]" />

                <div class="tab-content mt-3">
                    <!-- Tab 1: Components Panel -->
                    <div class="tab-pane fade show active" id="tab-components" role="tabpanel" aria-labelledby="tab-components-tab">
                        <h5 class="fw-bold text-dark mb-3">{{ __('production.add_material_item') }}*</h5>
                        <div class="table-responsive mb-4">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%" class="text-center">{{ __('production.seq') }}</th>
                                        <th style="width: 25%">{{ __('production.component_product') }}</th>
                                        <th style="width: 10%">{{ __('production.quantity') }}</th>
                                        <th style="width: 12%">{{ __('production.uom') }}</th>
                                        <th style="width: 10%">{{ __('production.scrap_percent') }}</th>
                                        <th style="width: 8%">{{ __('production.priority') }}</th>
                                        <th style="width: 15%">{{ __('production.validity') }}</th>
                                        <th style="width: 12%">{{ __('production.alternative') }}</th>
                                        <th style="width: 5%" class="text-center">{{ __('production.action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in items" :key="item.uid">
                                        <tr class="bom-component-row" x-init="$nextTick(() => initRowSelects($el, item))">
                                            <!-- Sequence -->
                                            <td class="fw-bold text-center align-middle" x-text="index + 1"></td>
                                            
                                            <!-- Material Selection -->
                                            <td class="align-middle">
                                                <x-ui.odoo-form-ui type="select" name="items[][material_id]" x-bind:name="'items['+index+'][material_id]'" class="odoo-table-select" x-model="item.material_id" required select2Selector="default" data-master="product" alpineError="errors['items.' + index + '.material_id']">
                                                    <option value="">{{ __('production.select_component') }}</option>
                                                    <option value="__ADD_NEW__" class="fw-bold text-primary">{{ __('production.add_new_product') }}</option>
                                                    @foreach($materials as $material)
                                                        <option value="{{ $material->id }}" data-type="{{ $material->type }}" data-sku="{{ $material->sku }}">{{ $material->name }} ({{ $material->sku }})</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                                <input type="hidden" x-bind:name="'items['+index+'][child_bom_id]'" x-model="item.child_bom_id">
                                                <template x-if="item.child_bom_loading">
                                                    <div class="mt-2 text-muted fs-11">
                                                        <span class="spinner-border spinner-border-sm text-secondary me-1" role="status" style="width: 12px; height: 12px;"></span>{{ __('production.checking_child_bom') }}
                                                    </div>
                                                </template>
                                                <template x-if="!item.child_bom_loading && item.child_bom_versions && item.child_bom_versions.length > 0">
                                                    <div class="mt-2 p-2 border rounded bg-light" style="max-width: 250px;">
                                                        <label class="fs-10 fw-bold text-muted mb-1 d-block">{{ __('production.link_specific_bom') }}</label>
                                                        <select class="form-select form-select-xs py-0.5 fs-11" x-model="item.child_bom_id">
                                                            <option value="">{{ __('production.explicit_selection') }}</option>
                                                            <template x-for="v in item.child_bom_versions" :key="v.id">
                                                                <option :value="v.id" x-text="'v' + v.version + ' (' + v.status + ')'"></option>
                                                            </template>
                                                        </select>
                                                        <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-1">
                                                            <div class="d-flex gap-1 align-items-center">
                                                                <template x-if="item.child_bom_id">
                                                                    <a :href="'/production/boms/' + item.child_bom_id" target="_blank" class="btn btn-xs btn-soft-info py-0.5 px-1 fs-9 text-nowrap">
                                                                        <i class="feather-eye"></i> {{ __('production.view') }}
                                                                    </a>
                                                                </template>
                                                                <template x-if="item.child_bom_id && item.child_bom_versions.find(x => x.id == item.child_bom_id)?.status !== 'approved'">
                                                                    <span class="badge bg-soft-danger text-danger fs-9" title="Draft / non-approved versions warn user"><i class="feather-alert-triangle"></i> {{ __('production.non_approved') }}</span>
                                                                </template>
                                                            </div>
                                                            <div class="d-flex gap-1">
                                                                <template x-if="item.child_bom_id">
                                                                    <form :action="'/production/boms/' + item.child_bom_id + '/create-revision'" method="POST" target="_blank" class="d-inline">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-xs btn-soft-warning py-0.5 px-1 fs-9 text-nowrap">
                                                                            <i class="feather-copy"></i> {{ __('production.revise') }}
                                                                         </button>
                                                                    </form>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="!item.child_bom_loading && (!item.child_bom_versions || item.child_bom_versions.length === 0) && (getMaterialType(item.material_id) === 'semi_finished' || getMaterialType(item.material_id) === 'finished_good')">
                                                    <div class="erp-child-bom-cta mt-2">
                                                        <a x-bind:href="'{{ route('production.boms.create') }}?product_id=' + item.material_id + '&parent_product_id=' + (document.getElementById('product_id')?.value || '')" target="_blank" class="btn btn-xs btn-soft-primary py-0.5 px-1.5 fs-9 text-nowrap">
                                                            <i class="feather-plus me-0.5"></i>{{ __('production.configure_sub_bom') }}
                                                        </a>
                                                    </div>
                                                </template>
                                            </td>
                                            
                                            <!-- Quantity -->
                                            <td class="align-middle">
                                                <x-ui.odoo-form-ui type="input" inputType="number" step="any" name="items[][quantity]" x-bind:name="'items['+index+'][quantity]'" class="odoo-table-input text-end" x-model="item.quantity" placeholder="0.00" required min="0.0001" alpineError="errors['items.' + index + '.quantity']" />
                                            </td>
                                            
                                            <!-- UOM -->
                                            <td class="align-middle">
                                                <x-ui.odoo-form-ui type="select" name="items[][uom_id]" x-bind:name="'items['+index+'][uom_id]'" class="odoo-table-select" x-model="item.uom_id" required select2Selector="default" data-master="uom" alpineError="errors['items.' + index + '.uom_id']">
                                                    <option value="">{{ __('production.select_uom') }}</option>
                                                    <option value="__ADD_NEW__" class="fw-bold text-primary">{{ __('production.add_new_uom') }}</option>
                                                    @foreach($uoms as $uom)
                                                        <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->code }})</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </td>
                                            
                                            <!-- Material Scrap Percentage -->
                                            <td class="align-middle">
                                                <x-ui.odoo-form-ui type="input" inputType="number" step="any" name="items[][material_scrap_percentage]" x-bind:name="'items['+index+'][material_scrap_percentage]'" class="odoo-table-input text-end text-danger" x-model="item.material_scrap_percentage" placeholder="0.00" min="0" max="100" alpineError="errors['items.' + index + '.material_scrap_percentage']" />
                                            </td>
                                            
                                            <!-- Priority -->
                                            <td class="align-middle">
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="items[][priority]" x-bind:name="'items['+index+'][priority]'" class="odoo-table-input text-end" x-model="item.priority" placeholder="1" min="1" alpineError="errors['items.' + index + '.priority']" />
                                            </td>

                                            <!-- Validity limits -->
                                            <td class="align-middle">
                                                <div class="d-flex flex-column gap-1">
                                                    <x-ui.odoo-form-ui type="input" inputType="date" name="items[][effective_from]" x-bind:name="'items['+index+'][effective_from]'" class="fs-11" x-model="item.effective_from" alpineError="errors['items.' + index + '.effective_from']" />
                                                    <x-ui.odoo-form-ui type="input" inputType="date" name="items[][effective_to]" x-bind:name="'items['+index+'][effective_to]'" class="fs-11" x-model="item.effective_to" alpineError="errors['items.' + index + '.effective_to']" />
                                                </div>
                                            </td>
                                            
                                            <!-- Alternative material options -->
                                            <td class="align-middle">
                                                <div class="d-flex flex-column gap-2">
                                                    <div>
                                                        <input type="hidden" x-bind:name="'items['+index+'][is_alternative]'" :value="item.is_alternative ? 1 : 0">
                                                        <x-ui.odoo-form-ui type="checkbox" name="items[][is_alternative]" x-model="item.is_alternative" x-bind:id="'is_alternative_create_' + index">
                                                            {{ __('production.is_alternative') }}
                                                        </x-ui.odoo-form-ui>
                                                    </div>
                                                    <div x-show="item.is_alternative">
                                                        <x-ui.odoo-form-ui type="input" name="items[][alternative_group]" x-bind:name="'items['+index+'][alternative_group]'" class="fs-11" placeholder="Alt Group Code..." x-model="item.alternative_group" alpineError="errors['items.' + index + '.alternative_group']" />
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- Remove Row Action -->
                                            <td class="text-center align-middle">
                                                <button type="button" class="erp-btn-delete" @click="removeItem(index)">
                                                    <i class="feather-x"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Add Row Action -->
                        <div class="mb-4">
                            <button type="button" class="btn btn-light-brand" @click="addItem()">
                                <i class="feather-plus me-2"></i>{{ __('production.add_material_line') }}
                            </button>
                        </div>
                    </div>

                    <!-- Tab 2: Operations Panel (Linked to Routing reference dropdown) -->
                    <div class="tab-pane fade" id="tab-operations" role="tabpanel" aria-labelledby="tab-operations-tab">
                        <h5 class="fw-bold text-dark mb-3">{{ __('production.workflow_routing') }}</h5>
                        
                        <template x-if="!selectedRoutingId">
                            <div class="p-4 text-center border rounded bg-light text-muted">
                                <i class="feather-info me-2"></i>{{ __('production.select_routing_see_ops') }}
                            </div>
                        </template>
                        
                        <template x-if="selectedRoutingId">
                            <div>
                                <template x-if="loadingOperations">
                                    <div class="p-4 text-center">
                                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>{{ __('production.loading_operations') }}
                                    </div>
                                </template>
                                
                                <template x-if="!loadingOperations && operations.length === 0">
                                    <div class="p-4 text-center border rounded bg-light text-muted">
                                        {{ __('production.no_operations_defined') }}
                                    </div>
                                </template>
                                
                                <template x-if="!loadingOperations && operations.length > 0">
                                    <div class="table-responsive">
                                        <x-ui.odoo-form-ui type="table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 10%" class="text-center">{{ __('production.seq') }}</th>
                                                    <th style="width: 25%">{{ __('production.operation_stage') }}</th>
                                                    <th style="width: 15%">{{ __('production.type') }}</th>
                                                    <th style="width: 20%">{{ __('production.work_center') }}</th>
                                                    <th style="width: 15%">{{ __('production.machine') }}</th>
                                                    <th style="width: 15%" class="text-end">{{ __('production.times_yield') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="op in operations" :key="op.sequence">
                                                    <tr>
                                                        <td class="fw-semibold text-muted text-center align-middle" x-text="op.sequence"></td>
                                                        <td class="fw-bold text-dark align-middle" x-text="op.name"></td>
                                                        <td class="text-capitalize align-middle" x-text="op.operation_type"></td>
                                                        <td class="align-middle" x-text="op.work_center_name"></td>
                                                        <td class="align-middle" x-text="op.machine_name"></td>
                                                        <td class="text-end align-middle">
                                                            <div class="fs-11 text-muted">{{ __('production.setup') }}: <span class="fw-semibold text-dark" x-text="op.setup_time_minutes + 'm'"></span></div>
                                                            <div class="fs-11 text-muted">{{ __('production.run') }}: <span class="fw-semibold text-dark" x-text="op.processing_time_minutes + 'm'"></span></div>
                                                            <div class="fs-11 text-muted">{{ __('production.yield') }}: <span class="fw-semibold text-dark" x-text="op.expected_yield_percentage + '%'"></span></div>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Footer Save and Cancel buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">{{ __('production.save_bom') }}</button>
                    <a href="{{ route('production.boms.index') }}" class="btn btn-secondary px-4">{{ __('production.cancel') }}</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>

    @php
        $oldItems = old('items', [
            ['material_id' => '', 'quantity' => '', 'uom_id' => '', 'material_scrap_percentage' => 0.00, 'is_alternative' => false, 'alternative_group' => '', 'priority' => 1, 'effective_from' => '', 'effective_to' => '']
        ]);
    @endphp

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bomForm', () => ({
                items: @json($oldItems),
                errors: @json($errors->toArray()),
                materials: @json($materials),
                selectedRoutingId: '{{ old("routing_id", "") }}',
                operations: [],
                loadingOperations: false,
                activeTab: 'components',
                showAdvanced: true,

                getMaterialType(materialId) {
                    if (!materialId) return '';
                    var mat = this.materials.find(m => m.id == materialId);
                    return mat ? mat.type : '';
                },

                fetchChildBomStatus(item) {
                    if (!item.material_id) {
                        item.child_bom_status = 'none';
                        item.child_bom_versions = [];
                        return;
                    }
                    item.child_bom_loading = true;
                    fetch('/production/boms/check-child/' + item.material_id)
                        .then(res => res.json())
                        .then(data => {
                            item.child_bom_status = data.status;
                            item.child_bom_id = item.child_bom_id || null;
                            item.child_bom_number = data.bom_number;
                            item.child_bom_version = data.version;
                            item.child_bom_name = data.bom_name;
                            item.child_bom_versions = data.versions || [];
                            item.child_bom_loading = false;
                        })
                        .catch(err => {
                            console.error(err);
                            item.child_bom_status = 'none';
                            item.child_bom_versions = [];
                            item.child_bom_loading = false;
                        });
                },

                refreshChildBomStatusForProduct(productId) {
                    this.items.forEach(item => {
                        if (item.material_id == productId) {
                            this.fetchChildBomStatus(item);
                        }
                    });
                },

                init() {
                    window.bomAlpineInstance = this;
                    this.items = this.items.map((item, idx) => {
                        const newItem = {
                            uid: item.uid || 'row_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9) + '_' + idx,
                            material_id: item.material_id || '',
                            child_bom_id: item.child_bom_id || null,
                            quantity: item.quantity || '',
                            uom_id: item.uom_id || '',
                            material_scrap_percentage: item.material_scrap_percentage || item.wastage_percentage || 0,
                            is_alternative: !!parseInt(item.is_alternative) || item.is_alternative === true,
                            alternative_group: item.alternative_group || '',
                            priority: item.priority || 1,
                            effective_from: item.effective_from || '',
                            effective_to: item.effective_to || '',
                            child_bom_status: 'none',
                            child_bom_versions: [],
                            child_bom_loading: false
                        };
                        if (newItem.material_id) {
                            this.fetchChildBomStatus(newItem);
                        }
                        return newItem;
                    });
                    if(this.items.length === 0) {
                        this.addItem();
                    }

                    // Store all routing options in memory on init
                    const allRoutings = [];
                    $('#routing_id option').each(function() {
                        const val = $(this).val();
                        if (val) {
                            allRoutings.push({
                                id: val,
                                text: $(this).text(),
                                productId: $(this).attr('data-product-id')
                            });
                        }
                    });

                    // Function to filter routing options based on selected product (visible only)
                    const filterRoutings = (productId) => {
                        const $routingSelect = $('#routing_id');
                        const currentVal = $routingSelect.val();

                        $routingSelect.empty();
                        $routingSelect.append($('<option value="">No Routing Reference</option>'));

                        let selectedValStillValid = false;

                        allRoutings.forEach(rt => {
                            if (productId && rt.productId == productId) {
                                const $opt = $('<option></option>')
                                    .val(rt.id)
                                    .text(rt.text)
                                    .attr('data-product-id', rt.productId);
                                $routingSelect.append($opt);
                                if (rt.id == currentVal) {
                                    selectedValStillValid = true;
                                }
                            }
                        });

                        if (selectedValStillValid && currentVal) {
                            $routingSelect.val(currentVal);
                        } else {
                            $routingSelect.val('');
                        }

                        if ($routingSelect.data('select2')) {
                            $routingSelect.trigger('change');
                        }
                    };

                    // Initial filter on load
                    const initialProductId = $('#product_id').val();
                    filterRoutings(initialProductId);

                    // Hook select2 change event for routing reference
                    $('#routing_id').on('change.select2', (e) => {
                        this.selectedRoutingId = e.target.value;
                        this.loadOperations();
                    });

                    // Hook select2 change event for parent product_id to disable it in row selections and filter routings
                    $('#product_id').on('change.select2 change', (e) => {
                        var parentProductId = e.target.value;
                        filterRoutings(parentProductId);

                        $('.odoo-table-select[name*="[material_id]"]').each(function() {
                            var $select = $(this);
                            $select.find('option').each(function() {
                                if ($(this).val() === parentProductId && parentProductId !== "") {
                                    $(this).prop('disabled', true);
                                } else {
                                    $(this).prop('disabled', false);
                                }
                            });
                            if ($select.data('select2')) {
                                $select.trigger('change.select2');
                            }
                        });
                    });

                    // Trigger initial load
                    this.loadOperations();
                    this.$nextTick(() => this.initAllRowSelects());
                },

                loadOperations() {
                    // Pull routing ID directly from DOM select input to guarantee sync
                    var routingEl = document.getElementById('routing_id');
                    if (routingEl) {
                        this.selectedRoutingId = routingEl.value;
                    }

                    if (!this.selectedRoutingId) {
                        this.operations = [];
                        return;
                    }
                    this.loadingOperations = true;
                    fetch('/production/routing/' + this.selectedRoutingId + '/operations')
                        .then(res => res.json())
                        .then(data => {
                            this.operations = data;
                            this.loadingOperations = false;
                        })
                        .catch(err => {
                            console.error(err);
                            this.operations = [];
                            this.loadingOperations = false;
                        });
                },

                addItem() {
                    this.items.push({
                        uid: 'row_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                        material_id: '',
                        child_bom_id: null,
                        quantity: '',
                        uom_id: '',
                        material_scrap_percentage: 0,
                        is_alternative: false,
                        alternative_group: '',
                        priority: 1,
                        effective_from: '',
                        effective_to: '',
                        child_bom_status: 'none',
                        child_bom_versions: [],
                        child_bom_loading: false
                    });
                    this.$nextTick(() => this.initAllRowSelects());
                },

                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    } else {
                        alert(@js(__('production.must_contain_material_row')));
                    }
                },

                initRowSelects(rowEl, item) {
                    var self = this;
                    $(rowEl).find('[data-select2-selector="default"]').each(function() {
                        var $select = $(this);
                        
                        if ($select.data('select2-initialized') && $select.hasClass('select2-hidden-accessible')) {
                            return;
                        }
                        
                        // Disable parent product if selected
                        var parentProductId = $('#product_id').val();
                        if ($select.attr('name') && $select.attr('name').indexOf('material_id') !== -1) {
                            $select.find('option').each(function() {
                                if ($(this).val() === parentProductId && parentProductId !== "") {
                                    $(this).prop('disabled', true);
                                } else {
                                    $(this).prop('disabled', false);
                                }
                            });
                        }

                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }

                        $select.data('select2-initialized', true);
                        $select.select2(self.select2RowOptions());
                        
                        // Capture type on init
                        var nameAttr = $select.attr('name') || '';
                        if (nameAttr.indexOf('material_id') !== -1) {
                            var val = $select.val();
                            var selectedOption = $select.find('option[value="' + val + '"]');
                            item.material_type = selectedOption.attr('data-type') || '';
                        }
                        
                        // Sync select2 changes to Alpine.js
                        $select.off('change.bom-row-select').on('change.bom-row-select', function () {
                            var val = $select.val();
                            var nameAttr = $select.attr('name') || '';
                            
                            if (nameAttr.indexOf('material_id') !== -1) {
                                if (item.material_id !== val) {
                                    item.material_id = val;
                                    var selectedOption = $select.find('option[value="' + val + '"]');
                                    item.material_type = selectedOption.attr('data-type') || '';
                                    self.fetchChildBomStatus(item);
                                }
                            } else if (nameAttr.indexOf('uom_id') !== -1) {
                                item.uom_id = val;
                            }
                            
                            // Trigger native events to propagate up to Alpine
                            this.dispatchEvent(new Event('input', { bubbles: true }));
                        });
                    });
                },

                initAllRowSelects() {
                    var self = this;
                    $('.bom-component-row').each(function(index) {
                        if (self.items[index]) {
                            self.initRowSelects(this, self.items[index]);
                        }
                    });
                },

                select2RowOptions() {
                    return {
                        theme: "bootstrap-5",
                        width: '100%',
                        dropdownParent: $('body'),
                        dropdownCssClass: 'bom-select2-dropdown'
                    };
                },

            }));

            // Hook bootstrap horizontal tabs change to load operations on Alpine
            $(document).on('shown.bs.tab', '#bomFormTabs button', function (e) {
                var targetId = $(e.target).attr('aria-controls');
                if (targetId === 'tab-operations') {
                    if (window.bomAlpineInstance) {
                        window.bomAlpineInstance.loadOperations();
                    }
                }
            });

            window.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'CHILD_BOM_CREATED') {
                    if (window.bomAlpineInstance) {
                        window.bomAlpineInstance.refreshChildBomStatusForProduct(event.data.product_id);
                    }
                }
            });
        });
    </script>

    {{-- Global master quick-create modals --}}
    <x-ui.master-modals :masters="['product', 'uom']" />
@endsection
