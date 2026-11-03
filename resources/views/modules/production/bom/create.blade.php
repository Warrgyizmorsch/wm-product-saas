@extends('layouts.duralux')

@section('title', __('production.create_bom') . ' | SaaS ERP')
@section('page-title', __('production.create_bom'))
@section('breadcrumb', __('production.create_bom'))

@section('content')
    <div class="erp-single-panel bg-white" x-data="bomForm">

        <!-- Validation Errors & Warning Banners (Rendered via Toast Component) -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error"
                title="{{ __('production.validation_failed') ?? 'Validation Failed' }}: {{ $errors->first() }}" />
        @endif

        <form method="POST" action="{{ route('production.boms.store') }}" @submit="if (!validateBeforeSubmit($event)) return false;">
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
                        <x-ui.odoo-form-ui type="input" :label="__('production.bom_number')" name="bom_number"
                            placeholder="{{ __('production.bom_number_placeholder') }}" :value="old('bom_number', 'AUTO')"
                            :required="true" :error-text="$errors->first('bom_number')" alpineError="errors.bom_number" />
                        <x-ui.odoo-form-ui type="select" :label="__('production.item_to_produce')" name="product_id"
                            id="product_id" :required="true" data-master="product"
                            :error-text="$errors->first('product_id')" alpineError="errors.product_id">
                            <option value="">{{ __('production.select_product') }}</option>
                            <option value="__ADD_NEW__" class="fw-bold text-primary">{{ __('production.add_new_product') }}
                            </option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" data-uom-id="{{ $p->uom_id }}" @selected(old('product_id', $selectedProductId ?? '') == $p->id)>
                                    {{ $p->name }} ({{ $p->sku }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" :label="__('production.bom_name')" name="bom_name"
                            placeholder="{{ __('production.bom_name_placeholder') }}" :value="old('bom_name')"
                            :required="true" :error-text="$errors->first('bom_name')" alpineError="errors.bom_name" />

                        <x-ui.odoo-form-ui type="select" :label="__('production.bom_type')" name="bom_type" :required="true"
                            :error-text="$errors->first('bom_type')" alpineError="errors.bom_type">
                            <option value="manufacturing" @selected(old('bom_type', 'manufacturing') === 'manufacturing')>
                                {{ __('production.bom_type_manufacturing') }}</option>
                            <option value="engineering" @selected(old('bom_type') === 'engineering')>
                                {{ __('production.bom_type_engineering') }}</option>
                            <option value="sales" @selected(old('bom_type') === 'sales')>{{ __('production.bom_type_sales') }}
                            </option>
                            <option value="phantom" @selected(old('bom_type') === 'phantom')>
                                {{ __('production.bom_type_phantom') }}</option>
                            <option value="subcontracting" @selected(old('bom_type') === 'subcontracting')>
                                {{ __('production.bom_type_subcontracting') }}</option>
                        </x-ui.odoo-form-ui>
                        <small class="form-text text-muted fs-11 mb-3 d-block">
                            <i class="feather-info me-1 text-primary"></i>
                            <strong>Manufacturing:</strong> Standard live production & MRP. 
                            <strong>Engineering:</strong> Design review only. 
                            <strong>Sales Kit:</strong> Sales bundles. 
                            <strong>Phantom:</strong> Blow-through sub-assembly. 
                            <strong>Subcontracting:</strong> Outsourced structure.
                        </small>

                        <x-ui.odoo-form-ui type="select" :label="__('production.usage_context')" name="usage_context"
                            :required="true" :error-text="$errors->first('usage_context')"
                            alpineError="errors.usage_context">
                            <option value="manufacturing" @selected(old('usage_context', 'manufacturing') === 'manufacturing')>{{ __('production.usage_context_manufacturing') }}
                            </option>
                            <option value="engineering" @selected(old('usage_context') === 'engineering')>
                                {{ __('production.usage_context_engineering') }}</option>
                            <option value="prototype" @selected(old('usage_context') === 'prototype')>
                                {{ __('production.usage_context_prototype') }}</option>
                            <option value="costing" @selected(old('usage_context') === 'costing')>
                                {{ __('production.usage_context_costing') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.base_quantity')" name="base_quantity"
                            inputType="number" step="any" placeholder="1.0" :value="old('base_quantity', '1.00')"
                            :required="true" :error-text="$errors->first('base_quantity')"
                            alpineError="errors.base_quantity" />

                        @php
                            $initBaseUomId = old('base_uom_id', $products->firstWhere('id', old('product_id', $selectedProductId ?? null))?->uom_id ?? '');
                        @endphp
                        <input type="hidden" name="base_uom_id" id="base_uom_id" value="{{ $initBaseUomId }}">
                        <x-ui.odoo-form-ui type="select" :label="__('production.base_uom')" id="base_uom_id_display"
                            :disabled="true" :error-text="$errors->first('base_uom_id')"
                            alpineError="errors.base_uom_id" class="bg-light text-muted"
                            style="pointer-events: none; cursor: not-allowed; background-color: #f8f9fa;">
                            <option value="">{{ __('production.select_uom') }}</option>
                            @foreach ($uoms as $uom)
                                <option value="{{ $uom->id }}" @selected($initBaseUomId == $uom->id)>{{ $uom->name }}
                                    ({{ $uom->code }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <small class="form-text text-muted fs-11 mb-3 d-block">
                            <i class="feather-lock me-1 text-secondary"></i>Auto-assigned from product master (read-only)
                        </small>

                        <x-ui.odoo-form-ui type="select" :label="__('production.process_routing') ?? 'Process Routing'" name="routing_id"
                            id="routing_id" :required="true" data-master="routing"
                            :error-text="$errors->first('routing_id')" alpineError="errors.routing_id">
                            <option value="">-- {{ __('production.select_routing') ?? 'Select Process Routing' }} --</option>
                            @foreach ($routings as $r)
                                <option value="{{ $r->id }}" @selected(old('routing_id') == $r->id)>
                                    {{ $r->routing_number }} - {{ $r->name }} (v{{ $r->version }}) [{{ ucfirst($r->status) }}]
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <small class="form-text text-muted fs-11 mb-3 d-block">
                            <i class="feather-git-commit me-1 text-primary"></i>Process route defining operations and work centers
                        </small>

                        <x-ui.odoo-form-ui type="textarea" :label="__('production.revision_notes')" name="notes"
                            placeholder="{{ __('production.notes_placeholder') }}" rows="3"
                            :error-text="$errors->first('notes')"
                            alpineError="errors.notes">{{ old('notes') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Advanced Configuration Collapsible settings using Alpine.js -->
                <div class="border rounded mb-4">
                    <div class="bg-light py-2 px-3 fs-13 fw-semibold text-dark d-flex justify-content-between align-items-center c-pointer"
                        @click="showAdvanced = !showAdvanced">
                        <span><i
                                class="feather-settings me-2"></i>{{ __('production.advanced_configuration') ?? 'Advanced Configuration & Lifecycle Dates' }}</span>
                        <i class="feather-chevron-down transition-all" style="transition: transform 0.2s;"
                            :style="showAdvanced ? 'transform: rotate(180deg);' : ''"></i>
                    </div>
                    <div class="bg-white"
                        style="transition: max-height 0.25s ease-out, opacity 0.25s ease-out, padding 0.25s ease-out; overflow: hidden;"
                        :style="showAdvanced ? 'max-height: 1000px; padding: 1rem; opacity: 1; border-top: 1px solid #dee2e6;' : 'max-height: 0px; padding: 0 1rem; opacity: 0; border-top: none;'">
                        <div class="row g-3 fs-13 text-dark">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('production.version')" name="version"
                                    placeholder="{{ __('production.version_placeholder') }}" :value="old('version', '1.0.0')" :required="true" :error-text="$errors->first('version')"
                                    alpineError="errors.version" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('production.start_date') ?? 'Start Date'"
                                    name="effective_date" inputType="date" :value="old('effective_date', date('Y-m-d'))"
                                    min="{{ date('Y-m-d') }}"
                                    :required="true" :error-text="$errors->first('effective_date')"
                                    alpineError="errors.effective_date" />
                            </div>
                            <div class="col-md-12">
                                <x-ui.odoo-form-ui type="input" :label="__('production.revision_reason') ?? 'Revision Reason'" name="revision_reason"
                                    placeholder="{{ __('production.revision_reason_placeholder') }}"
                                    :value="old('revision_reason')" :error-text="$errors->first('revision_reason')"
                                    alpineError="errors.revision_reason" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Headers -->
                <x-ui.horizontal-tabs id="bomFormTabs" :tabs="[
            ['id' => 'tab-components', 'label' => __('production.components'), 'active' => true, 'icon' => 'feather-list']
        ]" />

                <div class="tab-content mt-3">
                    <!-- Tab 1: Components Panel -->
                    <div class="tab-pane fade show active" id="tab-components" role="tabpanel"
                        aria-labelledby="tab-components-tab">

                        <!-- State 0: No Routing Selected Alert -->
                        <template x-if="!selectedRoutingId || !availableOperations.length">
                            <div class="alert alert-info border d-flex align-items-center p-4 rounded-2 mb-4">
                                <i class="feather-git-commit fs-24 text-info me-3"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">{{ __('production.select_routing_first') }}</h6>
                                    <p class="mb-0 fs-12 text-muted">{{ __('production.no_routing_selected_alert') }}</p>
                                </div>
                            </div>
                        </template>

                        <!-- State 1: Routing Selected with Operations Workspace -->
                        <div x-show="selectedRoutingId && availableOperations.length > 0">
                            <!-- Workspace Toolbar -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary fs-12" @click="expandAllOps()">
                                        <i class="feather-maximize-2 me-1"></i>{{ __('production.expand_all') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary fs-12" @click="collapseAllOps()">
                                        <i class="feather-minimize-2 me-1"></i>{{ __('production.collapse_all') }}
                                    </button>
                                </div>
                                <div class="text-muted fs-12">
                                    <span class="fw-semibold text-dark" x-text="items.filter(it => it.material_id).length"></span>
                                    <span x-text="items.filter(it => it.material_id).length === 1 ? ' material configured' : ' materials configured'"></span>
                                    across
                                    <span class="fw-semibold text-dark" x-text="availableOperations.length"></span>
                                    <span> operations</span>
                                </div>
                            </div>

                            <!-- Sequential Operation Cards -->
                            <template x-for="(op, opIndex) in availableOperations" :key="op.id">
                                <div class="card border shadow-none mb-3 operation-card"
                                    :class="isOpExpanded(op.id) ? 'border-primary-subtle' : ''">
                                    <!-- Card Header / Accordion Bar -->
                                    <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center c-pointer select-none"
                                        @click="toggleOp(op.id)"
                                        style="cursor: pointer;">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <i class="feather-chevron-down text-muted transition-all"
                                                style="transition: transform 0.2s;"
                                                :style="isOpExpanded(op.id) ? 'transform: rotate(0deg);' : 'transform: rotate(-90deg);'"></i>
                                            <span class="badge bg-primary text-white fw-bold fs-11 px-2 py-1">
                                                Op <span x-text="op.sequence"></span>
                                            </span>
                                            <span class="fw-bold text-dark fs-13" x-text="op.name"></span>

                                            <!-- Work Center / Section Badge -->
                                            <template x-if="op.work_center && op.work_center.name">
                                                <span class="badge bg-soft-secondary text-secondary border fs-11 fw-normal">
                                                    <i class="feather-box me-1"></i><span x-text="op.work_center.name"></span>
                                                    <template x-if="op.section && op.section.name">
                                                        <span x-text="' / ' + op.section.name"></span>
                                                    </template>
                                                </span>
                                            </template>

                                            <!-- Metrics: Setup / Processing / Yield -->
                                            <div class="d-none d-md-flex align-items-center gap-2 text-muted fs-11 ms-2">
                                                <template x-if="op.setup_time_minutes > 0">
                                                    <span>Setup: <strong x-text="op.setup_time_minutes + 'm'"></strong></span>
                                                </template>
                                                <template x-if="op.processing_time_minutes > 0">
                                                    <span>• Run: <strong x-text="op.processing_time_minutes + 'm/unit'"></strong></span>
                                                </template>
                                                <template x-if="op.expected_yield_percentage && op.expected_yield_percentage < 100">
                                                    <span>• Yield: <strong x-text="op.expected_yield_percentage + '%'"></strong></span>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <!-- Materials count badge -->
                                            <span class="badge rounded-pill fs-11 px-2.5 py-1"
                                                :class="getItemsForOp(op.id).length > 0 ? 'bg-soft-primary text-primary border border-primary-subtle' : 'bg-light text-muted border'">
                                                <i class="feather-layers me-1"></i>
                                                <span x-text="getItemsForOp(op.id).length + ' ' + (getItemsForOp(op.id).length === 1 ? 'Material' : 'Materials')"></span>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Card Body: Materials Sub-Table (Collapsible) -->
                                    <div class="card-body p-3" x-show="isOpExpanded(op.id)">
                                        <!-- When Operation has Materials -->
                                        <div x-show="getItemsForOp(op.id).length > 0">
                                            <div class="table-responsive">
                                                <table class="table table-sm align-middle table-hover mb-2 fs-12">
                                                    <thead class="table-light fs-11 text-uppercase text-muted border-bottom">
                                                        <tr>
                                                            <th style="width: 4%" class="text-center">#</th>
                                                            <th style="width: 32%">{{ __('production.component_product') }}</th>
                                                            <th style="width: 18%">{{ __('production.quantity') }}</th>
                                                            <th style="width: 10%">
                                                                {{ __('production.uom') }}
                                                                <i class="feather-lock text-muted ms-1 fs-11" title="Auto-selected from component product (read-only)"></i>
                                                            </th>
                                                            <th style="width: 8%">{{ __('production.scrap_percent') }}</th>
                                                            <th style="width: 24%">{{ __('production.alternative') }}</th>
                                                            <th style="width: 4%" class="text-center">{{ __('production.action') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-for="(item, subIndex) in getItemsForOp(op.id)" :key="item.uid">
                                                            <tr class="bom-component-row" :data-row-uid="item.uid" x-init="$nextTick(() => initRowSelects($el, item))">
                                                                <!-- Sequence inside this operation -->
                                                                <td class="fw-bold text-center align-middle text-muted" x-text="subIndex + 1"></td>

                                                                <!-- Material Selection -->
                                                                <td class="align-middle">
                                                                    <!-- Locked Operation ID -->
                                                                    <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][routing_operation_id]'" :value="op.id">

                                                                    <x-ui.odoo-form-ui type="select" name="items[][material_id]"
                                                                        x-bind:name="'items['+getItemIndex(item)+'][material_id]'" class="odoo-table-select"
                                                                        x-model="item.material_id" required select2Selector="default"
                                                                        data-master="product"
                                                                        alpineError="errors['items.' + getItemIndex(item) + '.material_id']">
                                                                        <option value="">{{ __('production.select_component') }}</option>
                                                                        <option value="__ADD_NEW__" class="fw-bold text-primary">
                                                                            {{ __('production.add_new_product') }}</option>
                                                                        @foreach($materials as $material)
                                                                            <option value="{{ $material->id }}" data-type="{{ $material->type }}"
                                                                                data-sku="{{ $material->sku }}" data-uom-id="{{ $material->uom_id }}">{{ $material->name }}
                                                                                ({{ $material->sku }})</option>
                                                                        @endforeach
                                                                    </x-ui.odoo-form-ui>
                                                                    <template x-if="!item.child_bom_versions || item.child_bom_versions.length === 0">
                                                                        <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][child_bom_id]'"
                                                                            x-model="item.child_bom_id">
                                                                    </template>
                                                                    <template x-if="item.child_bom_loading">
                                                                        <div class="mt-1 text-muted fs-11">
                                                                            <span class="spinner-border spinner-border-sm text-secondary me-1"
                                                                                role="status"
                                                                                style="width: 10px; height: 10px;"></span>{{ __('production.checking_child_bom') }}
                                                                        </div>
                                                                    </template>
                                                                    <template
                                                                        x-if="!item.child_bom_loading && item.child_bom_versions && item.child_bom_versions.length > 0">
                                                                        <div class="mt-1 d-flex align-items-center gap-1.5 flex-wrap fs-11">
                                                                            <span class="text-muted fs-11 me-1">Sub-BOM:</span>
                                                                            <x-ui.odoo-form-ui type="select" name="items[][child_bom_id]"
                                                                                x-bind:name="'items['+getItemIndex(item)+'][child_bom_id]'"
                                                                                class="odoo-table-select d-inline-block w-auto fs-11"
                                                                                x-model="item.child_bom_id" select2Selector="default"
                                                                                data-master="bom">
                                                                                <option value="">{{ __('production.explicit_selection') }}
                                                                                </option>
                                                                                <template x-for="v in item.child_bom_versions" :key="v.id">
                                                                                    <option :value="v.id"
                                                                                        x-text="'v' + v.version + ' (' + v.status + ')'">
                                                                                    </option>
                                                                                </template>
                                                                            </x-ui.odoo-form-ui>

                                                                            <template x-if="item.child_bom_id">
                                                                                <span class="d-inline-flex align-items-center gap-1.5 ms-1">
                                                                                    <a :href="'{{ url('production/boms') }}/' + item.child_bom_id"
                                                                                        target="_blank"
                                                                                        class="text-primary text-decoration-none fs-11 fw-semibold"
                                                                                        title="{{ __('production.view') }}">
                                                                                        <i
                                                                                            class="feather-eye me-0.5"></i>{{ __('production.view') }}
                                                                                    </a>
                                                                                    <template
                                                                                        x-if="item.child_bom_id && (!item.child_bom_versions.find(x => x.id == item.child_bom_id) || !['approved', 'active', 'scheduled'].includes(item.child_bom_versions.find(x => x.id == item.child_bom_id)?.status))">
                                                                                        <span
                                                                                            class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 px-1 py-0.5"
                                                                                            title="Draft, inactive, or non-approved version"><i
                                                                                                class="feather-alert-triangle me-0.5"></i>{{ __('production.non_approved') }}</span>
                                                                                    </template>
                                                                                </span>
                                                                            </template>
                                                                        </div>
                                                                    </template>
                                                                    <template
                                                                        x-if="!item.child_bom_loading && (!item.child_bom_versions || item.child_bom_versions.length === 0) && (getMaterialType(item.material_id) === 'semi_finished' || getMaterialType(item.material_id) === 'finished_good')">
                                                                        <div class="mt-1 fs-11">
                                                                            <a x-bind:href="'{{ route('production.boms.create') }}?product_id=' + item.material_id + '&parent_product_id=' + (document.getElementById('product_id')?.value || '')"
                                                                                target="_blank"
                                                                                class="text-primary text-decoration-none fw-semibold">
                                                                                <i
                                                                                    class="feather-plus-circle me-0.5"></i>{{ __('production.configure_sub_bom') }}
                                                                            </a>
                                                                        </div>
                                                                    </template>
                                                                    <div class="mt-1">
                                                                        <input type="text" x-bind:name="'items['+getItemIndex(item)+'][notes]'" class="odoo-table-input fs-11 text-muted" placeholder="Component notes (optional)..." x-model="item.notes" />
                                                                    </div>
                                                                </td>

                                                                <!-- Quantity & Type -->
                                                                <td class="align-middle" style="min-width: 195px;">
                                                                    <div class="d-flex flex-column gap-1">
                                                                        <div class="d-flex gap-2 align-items-center">
                                                                            <x-ui.odoo-form-ui type="input" inputType="number" step="any" min="0.0001" required
                                                                                name="items[][quantity]"
                                                                                x-bind:name="'items['+getItemIndex(item)+'][quantity]'"
                                                                                class="odoo-table-input text-end"
                                                                                style="width: 85px;"
                                                                                x-model="item.quantity"
                                                                                placeholder="0.00"
                                                                                alpineError="errors['items.' + getItemIndex(item) + '.quantity']" />

                                                                            <select class="odoo-table-select"
                                                                                name="items[][quantity_type]"
                                                                                x-bind:name="'items['+getItemIndex(item)+'][quantity_type]'"
                                                                                x-model="item.quantity_type"
                                                                                style="width: 100px;">
                                                                                <option value="fixed">Fixed</option>
                                                                                <option value="formula">Formula</option>
                                                                            </select>
                                                                        </div>

                                                                        <!-- Formula Expression Box (Shown when Formula is selected) -->
                                                                        <div x-show="item.quantity_type === 'formula'" 
                                                                            x-transition:enter="transition ease-out duration-100"
                                                                            class="mt-1" style="width: 190px;">
                                                                            <div class="d-flex align-items-center gap-1">
                                                                                <input type="text"
                                                                                    name="items[][formula]"
                                                                                    x-bind:name="'items['+getItemIndex(item)+'][formula]'"
                                                                                    class="odoo-table-input font-monospace fs-11"
                                                                                    style="flex: 1 1 auto;"
                                                                                    x-model="item.formula"
                                                                                    placeholder="e.g. length * width"
                                                                                    :class="errors['items.' + getItemIndex(item) + '.formula'] ? 'is-invalid' : ''" />
                                                                                <button type="button" 
                                                                                    class="btn btn-outline-primary btn-sm px-2 py-0 d-inline-flex align-items-center justify-content-center"
                                                                                    style="height: 32px; border-radius: var(--ax-radius-sm, 6px);"
                                                                                    @click="previewFormula(item.formula)"
                                                                                    title="Test formula evaluation">
                                                                                    <i class="feather-play fs-11"></i>
                                                                                </button>
                                                                            </div>
                                                                            <template x-if="errors['items.' + getItemIndex(item) + '.formula']">
                                                                                <div class="invalid-feedback d-block fs-10 mt-1" x-text="Array.isArray(errors['items.' + getItemIndex(item) + '.formula']) ? errors['items.' + getItemIndex(item) + '.formula'][0] : errors['items.' + getItemIndex(item) + '.formula']"></div>
                                                                            </template>
                                                                            <div class="d-flex align-items-center flex-wrap gap-1 mt-1 text-muted" style="font-size: 10px;">
                                                                                <span>Vars:</span>
                                                                                <code class="text-primary c-pointer text-decoration-underline" @click="item.formula = (item.formula ? item.formula + ' ' : '') + 'length'">length</code>,
                                                                                <code class="text-primary c-pointer text-decoration-underline" @click="item.formula = (item.formula ? item.formula + ' ' : '') + 'width'">width</code>,
                                                                                <code class="text-primary c-pointer text-decoration-underline" @click="item.formula = (item.formula ? item.formula + ' ' : '') + 'height'">height</code>,
                                                                                <code class="text-primary c-pointer text-decoration-underline" @click="item.formula = (item.formula ? item.formula + ' ' : '') + 'quantity'">qty</code>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <!-- UOM -->
                                                                <td class="align-middle">
                                                                    <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][uom_id]'" x-model="item.uom_id">
                                                                    <select class="odoo-table-select bg-light text-muted bom-row-uom-select"
                                                                        x-model="item.uom_id"
                                                                        disabled
                                                                        tabindex="-1"
                                                                        data-select2-selector="default"
                                                                        style="pointer-events: none; cursor: not-allowed; background-color: #f8f9fa;"
                                                                        :class="errors['items.' + getItemIndex(item) + '.uom_id'] ? 'is-invalid' : ''">
                                                                        <option value="">{{ __('production.select_uom') }}</option>
                                                                        @foreach($uoms as $uom)
                                                                            <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->code }})</option>
                                                                        @endforeach
                                                                    </select>
                                                                    <template x-if="errors['items.' + getItemIndex(item) + '.uom_id']">
                                                                        <div class="invalid-feedback d-block fs-11 mt-1" x-text="Array.isArray(errors['items.' + getItemIndex(item) + '.uom_id']) ? errors['items.' + getItemIndex(item) + '.uom_id'][0] : errors['items.' + getItemIndex(item) + '.uom_id']"></div>
                                                                    </template>
                                                                </td>

                                                                <!-- Material Scrap Percentage -->
                                                                <td class="align-middle">
                                                                    <x-ui.odoo-form-ui type="input" inputType="number" step="any"
                                                                        name="items[][material_scrap_percentage]"
                                                                        x-bind:name="'items['+getItemIndex(item)+'][material_scrap_percentage]'"
                                                                        class="odoo-table-input text-end text-danger"
                                                                        x-model="item.material_scrap_percentage" placeholder="0.00" min="0"
                                                                        max="100"
                                                                        alpineError="errors['items.' + getItemIndex(item) + '.material_scrap_percentage']" />
                                                                </td>

                                                                <!-- Alternative material options + Embedded Priority -->
                                                                <td class="align-middle" style="min-width: 250px;">
                                                                    <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][row_uid]'" :value="item.uid">
                                                                    <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][is_alternative]'" :value="item.is_alternative ? 1 : 0">
                                                                    <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][replaces_row_uid]'" :value="item.is_alternative ? (item.replaces_row_uid || '') : ''">
                                                                    <div class="d-flex flex-column gap-1.5">
                                                                        <div class="form-check form-switch mb-0">
                                                                            <input class="form-check-input" type="checkbox"
                                                                                x-model="item.is_alternative"
                                                                                @change="onAlternativeToggle(item)"
                                                                                x-bind:id="'is_alternative_create_' + item.uid">
                                                                            <label class="form-check-label fs-11 fw-semibold" x-bind:for="'is_alternative_create_' + item.uid"
                                                                                x-text="item.is_alternative ? '{{ __('production.alternative_component') }}' : '{{ __('production.primary_component') }}'">
                                                                            </label>
                                                                        </div>
                                                                        <div x-show="item.is_alternative" class="mt-1">
                                                                            <div class="d-flex gap-2 align-items-end">
                                                                                <div class="flex-grow-1" style="min-width: 150px;">
                                                                                    <label class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">
                                                                                        {{ __('production.alternative_of') }} <span class="text-danger">*</span>
                                                                                    </label>
                                                                                    <select class="form-select form-select-sm fs-11 bom-alt-select"
                                                                                        data-select2-selector="alt"
                                                                                        style="width: 100%;"
                                                                                        :required="item.is_alternative">
                                                                                        <option value="">-- {{ __('production.select_primary_component') }} --</option>
                                                                                    </select>
                                                                                </div>
                                                                                <div style="width: 70px;">
                                                                                    <label class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">
                                                                                        {{ __('production.priority') }}
                                                                                    </label>
                                                                                    <input type="number"
                                                                                        x-bind:name="'items['+getItemIndex(item)+'][priority]'"
                                                                                        class="odoo-table-input text-end"
                                                                                        style="width: 100%;"
                                                                                        x-model="item.priority"
                                                                                        placeholder="1" min="1">
                                                                                </div>
                                                                            </div>
                                                                            <template x-if="errors['items.' + getItemIndex(item) + '.replaces_row_uid']">
                                                                                <div class="invalid-feedback d-block fs-11 mt-1" x-text="Array.isArray(errors['items.' + getItemIndex(item) + '.replaces_row_uid']) ? errors['items.' + getItemIndex(item) + '.replaces_row_uid'][0] : errors['items.' + getItemIndex(item) + '.replaces_row_uid']"></div>
                                                                            </template>
                                                                        </div>
                                                                        <template x-if="!item.is_alternative">
                                                                            <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][priority]'" value="1">
                                                                        </template>
                                                                    </div>
                                                                </td>

                                                                <!-- Remove Row Action -->
                                                                <td class="text-center align-middle">
                                                                    <button type="button"
                                                                        class="btn btn-outline-danger btn-sm p-0 d-inline-flex align-items-center justify-content-center"
                                                                        style="width: 24px; height: 24px; border-radius: 4px;"
                                                                        @click="removeItem(getItemIndex(item))"
                                                                        title="{{ __('production.delete') }}">
                                                                        <i class="feather-trash-2 fs-12"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="mt-2">
                                                <button type="button" class="btn btn-link p-0 text-primary fw-semibold text-decoration-none shadow-none border-0 bg-transparent fs-12"
                                                    @click="addItemToOp(op.id)">
                                                    <i class="feather-plus me-1 fs-13"></i>{{ __('production.add_material_to_op') }} <span x-text="op.sequence"></span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- When Operation has ZERO Materials -->
                                        <div x-show="getItemsForOp(op.id).length === 0">
                                            <div class="alert alert-light border border-dashed d-flex align-items-center justify-content-between p-3 mb-0 rounded-2">
                                                <div class="d-flex align-items-center text-muted fs-12">
                                                    <i class="feather-info text-secondary me-2 fs-16"></i>
                                                    <div>
                                                        <div class="fw-semibold text-dark">{{ __('production.process_operation_zero_materials') }}</div>
                                                        <div class="fs-11 text-muted">{{ __('production.zero_materials_notice', ['seq' => '']) }}</div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary fs-11" @click="addItemToOp(op.id)">
                                                    <i class="feather-plus me-1"></i>{{ __('production.add_material_to_op') }} <span x-text="op.sequence"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Footer Save and Cancel buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <x-ui.button type="submit" variant="primary" class="px-4">{{ __('production.save_bom') }}</x-ui.button>
                    <x-ui.button href="{{ route('production.boms.index') }}" variant="secondary" class="px-4">{{ __('production.cancel') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>

        <x-ui.confirmation-modal />
    </div>

    @php
        $oldItems = old('items', [
            ['material_id' => '', 'routing_operation_id' => '', 'quantity' => '', 'quantity_type' => 'fixed', 'formula' => '', 'uom_id' => '', 'material_scrap_percentage' => 0.00, 'is_alternative' => false, 'replaces_row_uid' => '', 'priority' => 1]
        ]);
    @endphp

    <style>
        .bom-row-uom-select,
        .bom-row-uom-select + .select2-container,
        .bom-row-uom-select + .select2-container .select2-selection {
            pointer-events: none !important;
            cursor: not-allowed !important;
            background-color: #f8f9fa !important;
        }
        #base_uom_id_display,
        #base_uom_id_display + .select2-container,
        #base_uom_id_display + .select2-container .select2-selection {
            pointer-events: none !important;
            cursor: not-allowed !important;
            background-color: #f8f9fa !important;
        }
        .operation-card {
            border-radius: 6px;
            border-color: #e2e8f0;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .operation-card:hover {
            border-color: #cbd5e1;
        }
        .operation-card.border-primary-subtle {
            border-color: #c7d2fe !important;
        }
        .c-pointer {
            cursor: pointer;
        }
        .select-none {
            user-select: none;
        }
        .bom-select2-dropdown .select2-results__option {
            font-size: 11px;
            padding: 6px 10px;
        }
        .bom-alt-select + .select2-container--bootstrap-5 .select2-selection {
            min-height: 32px !important;
            height: 32px !important;
            font-size: 13px !important;
        }
        .bom-alt-select + .select2-container--bootstrap-5 .select2-selection__rendered {
            line-height: 30px !important;
            padding-left: 8px !important;
            padding-right: 20px !important;
        }
        .select2-container--bootstrap-5 .select2-selection.is-invalid,
        select.is-invalid + .select2-container--bootstrap-5 .select2-selection,
        select.is-invalid + .select2-container .select2-selection {
            border-color: #dc3545 !important;
        }
    </style>

    <script>
        const productsData = @json($products);
        const routingsData = @json($routings->keyBy('id'));

        document.addEventListener('alpine:init', () => {
            Alpine.data('bomForm', () => ({
                items: @json($oldItems),
                errors: @json($errors->toArray()),
                materials: @json($materials),
                routings: routingsData,
                selectedRoutingId: '{{ old('routing_id', '') }}',
                availableOperations: [],
                expandedOps: {},
                activeTab: 'components',
                showAdvanced: true,

                isOpExpanded(opId) {
                    if (this.expandedOps[opId] === undefined) {
                        return true;
                    }
                    return !!this.expandedOps[opId];
                },

                toggleOp(opId) {
                    this.expandedOps[opId] = !this.isOpExpanded(opId);
                    if (this.expandedOps[opId]) {
                        this.$nextTick(() => {
                            var self = this;
                            $('.operation-card[data-op-id="' + opId + '"] .bom-component-row').each(function () {
                                var rowUid = $(this).attr('data-row-uid');
                                var item = self.items.find(it => it.uid === rowUid);
                                if (item && item.is_alternative) {
                                    self.initAltSelect(this, item);
                                }
                            });
                        });
                    }
                },

                expandAllOps() {
                    this.availableOperations.forEach(op => {
                        this.expandedOps[op.id] = true;
                    });
                    this.$nextTick(() => {
                        this.initAllRowSelects();
                    });
                },

                collapseAllOps() {
                    this.availableOperations.forEach(op => {
                        this.expandedOps[op.id] = false;
                    });
                },

                getItemsForOp(opId) {
                    return this.items.filter(it => String(it.routing_operation_id) === String(opId));
                },

                getItemIndex(item) {
                    const idx = this.items.indexOf(item);
                    return idx >= 0 ? idx : 0;
                },

                updateAvailableOperations(routingId, autoAssignDefault = true) {
                    if (!routingId || !this.routings[routingId] || !this.routings[routingId].operations) {
                        this.availableOperations = [];
                        return;
                    }
                    this.availableOperations = (this.routings[routingId].operations || []).slice().sort((a, b) => a.sequence - b.sequence);

                    const firstOpId = this.availableOperations.length > 0 ? String(this.availableOperations[0].id) : '';
                    const validOpIds = this.availableOperations.map(op => String(op.id));

                    if (autoAssignDefault) {
                        this.items.forEach(item => {
                            if (!item.routing_operation_id || !validOpIds.includes(String(item.routing_operation_id))) {
                                item.routing_operation_id = firstOpId;
                            }
                        });
                    }
                },

                getFirstOperationId() {
                    if (this.availableOperations && this.availableOperations.length > 0) {
                        return String(this.availableOperations[0].id);
                    }
                    return '';
                },

                getMaterialType(materialId) {
                    if (!materialId) return '';
                    var mat = this.materials.find(m => m.id == materialId);
                    return mat ? mat.type : '';
                },

                fetchChildBomStatus(item) {
                    if (!item || !item.material_id) {
                        if (item) {
                            item.child_bom_status = 'none';
                            item.child_bom_versions = [];
                            item.child_bom_loading = false;
                        }
                        return;
                    }
                    if (item.child_bom_loading) {
                        return;
                    }
                    item.child_bom_loading = true;
                    fetch('{{ url('production/boms/check-child') }}/' + item.material_id)
                        .then(res => res.json())
                        .then(data => {
                            item.child_bom_status = data.status;
                            item.child_bom_id = item.child_bom_id || (data.bom_id ? String(data.bom_id) : null);
                            item.child_bom_number = data.bom_number;
                            item.child_bom_version = data.version;
                            item.child_bom_name = data.bom_name;
                            item.child_bom_versions = data.versions || [];
                            item.child_bom_loading = false;
                            this.$nextTick(() => {
                                this.initAllRowSelects();
                            });
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
                        var initialUomId = item.uom_id || '';
                        if (!initialUomId && item.material_id) {
                            var foundMat = this.materials.find(m => String(m.id) === String(item.material_id));
                            if (foundMat && foundMat.uom_id) {
                                initialUomId = String(foundMat.uom_id);
                            }
                        }
                        return {
                            uid: item.uid || item.row_uid || 'row_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9) + '_' + idx,
                            material_id: item.material_id || '',
                            routing_operation_id: item.routing_operation_id ? String(item.routing_operation_id) : '',
                            child_bom_id: item.child_bom_id || null,
                            quantity: item.quantity || '',
                            quantity_type: item.quantity_type || 'fixed',
                            formula: item.formula || '',
                            notes: item.notes || '',
                            uom_id: initialUomId,
                            material_scrap_percentage: item.material_scrap_percentage || item.wastage_percentage || 0,
                            is_alternative: !!parseInt(item.is_alternative) || item.is_alternative === true,
                            replaces_row_uid: item.replaces_row_uid || '',
                            priority: item.priority || 1,
                            child_bom_status: 'none',
                            child_bom_versions: [],
                            child_bom_loading: false
                        };
                    });

                    // Initial routing and operations setup
                    const initialRoutingId = $('#routing_id').val() || this.selectedRoutingId;
                    this.selectedRoutingId = initialRoutingId;
                    this.updateAvailableOperations(initialRoutingId, false);

                    const firstOp = this.getFirstOperationId();
                    if (firstOp) {
                        this.items.forEach(item => {
                            if (!item.routing_operation_id) {
                                item.routing_operation_id = firstOp;
                            }
                        });
                    }

                    this.expandAllOps();

                    this.$nextTick(() => {
                        this.items.forEach(item => {
                            if (item.material_id) {
                                this.fetchChildBomStatus(item);
                            }
                        });
                        this.initAllRowSelects();
                    });

                    const syncHeaderUom = (productId) => {
                        var prodUomId = '';
                        if (productId) {
                            var selectedOption = $('#product_id').find('option[value="' + productId + '"]');
                            prodUomId = selectedOption.attr('data-uom-id') || '';
                            if (!prodUomId) {
                                var foundProd = productsData.find(p => String(p.id) === String(productId));
                                if (foundProd && foundProd.uom_id) {
                                    prodUomId = foundProd.uom_id;
                                }
                            }
                        }
                        $('#base_uom_id').val(prodUomId);
                        var $baseUomDisplay = $('#base_uom_id_display');
                        $baseUomDisplay.val(prodUomId);
                        if ($baseUomDisplay.data('select2')) {
                            $baseUomDisplay.trigger('change.select2');
                        } else {
                            $baseUomDisplay.trigger('change');
                        }
                    };

                    $('#routing_id').on('change.select2 change', (e) => {
                        var routingVal = e.target.value;
                        this.selectedRoutingId = routingVal;
                        this.updateAvailableOperations(routingVal, true);
                        this.expandAllOps();
                        this.$nextTick(() => {
                            this.initAllRowSelects();
                            this.validateDuplicateMaterials();
                        });
                    });

                    // Initial header UOM on load
                    const initialProductId = $('#product_id').val();
                    syncHeaderUom(initialProductId);

                    $('#product_id').on('change.select2 change', (e) => {
                        var parentProductId = e.target.value;
                        syncHeaderUom(parentProductId);

                        $('.odoo-table-select[name*="[material_id]"]').each(function () {
                            var $select = $(this);
                            $select.find('option').each(function () {
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
                        this.validateDuplicateMaterials();
                    });

                    this.$nextTick(() => {
                        this.initAllRowSelects();
                        this.validateDuplicateMaterials();
                    });
                },

                previewFormula(formula) {
                    if (!formula || !formula.trim()) {
                        if (typeof window.confirmAction === 'function') {
                            window.confirmAction({
                                title: 'Formula Required',
                                message: 'Please enter a valid formula expression first (e.g. length * width).',
                                variant: 'warning',
                                confirmText: 'OK',
                                cancelButtonText: ''
                            });
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'warning', title: 'Formula Required', text: 'Please enter a valid formula expression first (e.g. length * width).' });
                        } else {
                            alert('Please enter a valid formula expression first (e.g. length * width)');
                        }
                        return;
                    }
                    var sampleParams = { length: 2.5, width: 1.2, height: 1.0, quantity: 1 };
                    fetch('{{ route("production.boms.preview-formula") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            formula: formula,
                            sample_parameters: sampleParams
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            var resultMsg = 'Formula: ' + formula + '\nInputs: length=2.5, width=1.2, height=1.0, quantity=1\nCalculated Result: ' + data.result;
                            if (typeof window.confirmAction === 'function') {
                                window.confirmAction({
                                    title: 'Formula Preview Success',
                                    message: resultMsg,
                                    variant: 'success',
                                    confirmText: 'Great!',
                                    cancelButtonText: 'Close'
                                });
                            } else if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'success', title: 'Formula Preview Success', text: resultMsg });
                            } else {
                                alert('Formula Preview Success!\n' + resultMsg);
                            }
                        } else {
                            var errorMsg = data.error || 'Invalid expression syntax.';
                            if (typeof window.confirmAction === 'function') {
                                window.confirmAction({
                                    title: 'Formula Syntax Error',
                                    message: errorMsg,
                                    variant: 'danger',
                                    confirmText: 'Close',
                                    cancelButtonText: ''
                                });
                            } else if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'error', title: 'Formula Syntax Error', text: errorMsg });
                            } else {
                                alert('Formula Error: ' + errorMsg);
                            }
                        }
                    })
                    .catch(err => {
                        var failMsg = err.message || 'Server error while evaluating formula.';
                        if (typeof window.confirmAction === 'function') {
                            window.confirmAction({
                                title: 'Evaluation Failed',
                                message: failMsg,
                                variant: 'danger',
                                confirmText: 'Close',
                                cancelButtonText: ''
                            });
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Evaluation Failed', text: failMsg });
                        } else {
                            alert('Formula evaluation failed: ' + failMsg);
                        }
                    });
                },

                getEligiblePrimaryComponents(currentIndex) {
                    const currentItem = this.items[currentIndex];
                    if (!currentItem) return [];
                    const opItems = this.getItemsForOp(currentItem.routing_operation_id);
                    return opItems
                        .map((it, subIdx) => {
                            const mat = this.materials.find(m => String(m.id) === String(it.material_id));
                            return {
                                uid: it.uid,
                                seq: subIdx + 1,
                                material_id: it.material_id,
                                routing_operation_id: it.routing_operation_id,
                                name: mat ? mat.name : (it.material_id ? ('Component #' + it.material_id) : ('Item #' + (subIdx + 1))),
                                sku: mat ? mat.sku : '—',
                                is_alternative: it.is_alternative,
                            };
                        })
                        .filter(it => {
                            if (it.uid === currentItem.uid) return false;
                            if (it.is_alternative) return false;
                            if (!it.material_id) return false;
                            return true;
                        });
                },

                initAltSelect(rowEl, item) {
                    var self = this;
                    if (!rowEl) return;
                    var $altSelect = $(rowEl).find('select.bom-alt-select');
                    if (!$altSelect.length) return;

                    var eligible = self.getEligiblePrimaryComponents(self.getItemIndex(item));
                    var currentVal = item.replaces_row_uid || '';

                    if ($altSelect.data('select2')) {
                        $altSelect.select2('destroy');
                    }

                    $altSelect.empty();
                    $altSelect.append(new Option('-- {{ __('production.select_primary_component') }} --', ''));
                    eligible.forEach(function (target) {
                        var label = '[' + (target.sku || 'No SKU') + '] ' + target.name + ' (#' + target.seq + ')';
                        var isSelected = String(target.uid) === String(currentVal);
                        var opt = new Option(label, target.uid, isSelected, isSelected);
                        $altSelect.append(opt);
                    });

                    if (currentVal && !eligible.some(t => String(t.uid) === String(currentVal))) {
                        item.replaces_row_uid = '';
                        currentVal = '';
                    }

                    $altSelect.val(currentVal);

                    $altSelect.select2({
                        theme: "bootstrap-5",
                        width: '100%',
                        dropdownParent: $('body'),
                        dropdownCssClass: 'bom-select2-dropdown'
                    });

                    $altSelect.off('change.bom-alt-select').on('change.bom-alt-select', function () {
                        var val = $(this).val();
                        item.replaces_row_uid = val;
                    });
                },

                refreshAltSelectsForOp(opId) {
                    var self = this;
                    this.$nextTick(() => {
                        var opRows = self.getItemsForOp(opId);
                        opRows.forEach(item => {
                            if (item.is_alternative) {
                                var rowEl = document.querySelector('.bom-component-row[data-row-uid="' + item.uid + '"]');
                                if (rowEl) {
                                    self.initAltSelect(rowEl, item);
                                }
                            }
                        });
                    });
                },

                onAlternativeToggle(item) {
                    var self = this;
                    if (!item.is_alternative) {
                        item.replaces_row_uid = '';
                        var rowEl = document.querySelector('.bom-component-row[data-row-uid="' + item.uid + '"]');
                        if (rowEl) {
                            var $altSelect = $(rowEl).find('select.bom-alt-select');
                            if ($altSelect.data('select2')) {
                                $altSelect.select2('destroy');
                            }
                        }
                    } else {
                        // Prevent this item from remaining target of another alternative
                        this.items.forEach(other => {
                            if (other.is_alternative && other.replaces_row_uid === item.uid) {
                                other.replaces_row_uid = '';
                            }
                        });
                        this.$nextTick(() => {
                            var rowEl = document.querySelector('.bom-component-row[data-row-uid="' + item.uid + '"]');
                            if (rowEl) {
                                self.initAltSelect(rowEl, item);
                            }
                        });
                    }
                    self.refreshAltSelectsForOp(item.routing_operation_id);
                },

                addItemToOp(opId) {
                    this.items.push({
                        uid: 'row_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                        material_id: '',
                        routing_operation_id: String(opId),
                        child_bom_id: null,
                        quantity: '',
                        quantity_type: 'fixed',
                        formula: '',
                        notes: '',
                        uom_id: '',
                        material_scrap_percentage: 0,
                        is_alternative: false,
                        replaces_row_uid: '',
                        priority: 1,
                        child_bom_status: 'none',
                        child_bom_versions: [],
                        child_bom_loading: false
                    });
                    this.expandedOps[opId] = true;
                    this.$nextTick(() => {
                        this.initAllRowSelects();
                        this.validateDuplicateMaterials();
                    });
                },

                addItem() {
                    const firstOp = this.getFirstOperationId();
                    this.addItemToOp(firstOp);
                },

                removeItem(index) {
                    const removedItem = this.items[index];
                    if (!removedItem) return;
                    const opId = removedItem.routing_operation_id;

                    this.items.forEach(it => {
                        if (it.is_alternative && it.replaces_row_uid === removedItem.uid) {
                            it.replaces_row_uid = '';
                        }
                    });
                    this.items.splice(index, 1);
                    this.$nextTick(() => {
                        this.initAllRowSelects();
                        if (opId) {
                            this.refreshAltSelectsForOp(opId);
                        }
                        this.validateDuplicateMaterials();
                    });
                },

                validateDuplicateMaterials() {
                    const parentProductId = String($('#product_id').val() || '');
                    const seen = {};
                    const updatedErrors = { ...this.errors };

                    // 1. Clear previous live material errors
                    Object.keys(updatedErrors).forEach(key => {
                        if (key.startsWith('items.') && key.endsWith('.material_id')) {
                            const val = Array.isArray(updatedErrors[key]) ? updatedErrors[key][0] : updatedErrors[key];
                            if (
                                typeof val === 'string' && (
                                    val.includes('Duplicate material component') ||
                                    val.includes('A finished product cannot be its own component')
                                )
                            ) {
                                delete updatedErrors[key];
                            }
                        }
                    });

                    // 2. Evaluate duplicates per routing operation and self-material
                    this.items.forEach((item, index) => {
                        const matId = String(item.material_id || '');
                        if (!matId) return;

                        if (parentProductId && matId === parentProductId) {
                            updatedErrors['items.' + index + '.material_id'] = 'A finished product cannot be its own component material.';
                            return;
                        }

                        const opId = String(item.routing_operation_id || '0');
                        const comboKey = matId + '_' + opId;

                        if (!seen[comboKey]) {
                            seen[comboKey] = [];
                        }
                        seen[comboKey].push(index);
                    });

                    // 3. Mark 2nd and subsequent duplicates with error message
                    Object.keys(seen).forEach(comboKey => {
                        const indices = seen[comboKey];
                        if (indices.length > 1) {
                            for (let i = 1; i < indices.length; i++) {
                                updatedErrors['items.' + indices[i] + '.material_id'] = 'Duplicate material component found for the same routing operation.';
                            }
                        }
                    });

                    this.errors = updatedErrors;

                    // 4. Update Select2 visual border in DOM
                    this.$nextTick(() => {
                        this.items.forEach((item, index) => {
                            const hasErr = !!this.errors['items.' + index + '.material_id'];
                            const $row = $('tr.bom-component-row[data-row-uid="' + item.uid + '"]');
                            const $select = $row.find('select[name*="[material_id]"]');
                            const $s2Selection = $select.next('.select2-container').find('.select2-selection');

                            if (hasErr) {
                                $select.addClass('is-invalid');
                                $s2Selection.addClass('is-invalid');
                            } else {
                                $select.removeClass('is-invalid');
                                $s2Selection.removeClass('is-invalid');
                            }
                        });
                    });
                },

                validateBeforeSubmit(e) {
                    if (!this.selectedRoutingId) {
                        e.preventDefault();
                        if (typeof window.confirmAction === 'function') {
                            window.confirmAction({
                                title: 'Process Routing Required',
                                message: 'Please select a Process Routing in the header before saving the BOM.',
                                variant: 'warning',
                                confirmText: 'OK',
                                cancelButtonText: ''
                            });
                        } else {
                            alert('Please select a Process Routing before saving.');
                        }
                        return false;
                    }
                    const configuredItems = this.items.filter(it => it.material_id);
                    if (configuredItems.length === 0) {
                        e.preventDefault();
                        if (typeof window.confirmAction === 'function') {
                            window.confirmAction({
                                title: 'Materials Required',
                                message: 'Please add at least one material component to the Bill of Materials.',
                                variant: 'warning',
                                confirmText: 'OK',
                                cancelButtonText: ''
                            });
                        } else {
                            alert('Please add at least one material component to the Bill of Materials.');
                        }
                        return false;
                    }

                    this.validateDuplicateMaterials();
                    const hasMaterialErrors = Object.keys(this.errors).some(k => 
                        k.startsWith('items.') && k.endsWith('.material_id') && !!this.errors[k]
                    );
                    if (hasMaterialErrors) {
                        e.preventDefault();
                        const firstMsg = Object.keys(this.errors)
                            .filter(k => k.startsWith('items.') && k.endsWith('.material_id'))
                            .map(k => Array.isArray(this.errors[k]) ? this.errors[k][0] : this.errors[k])[0];

                        if (typeof window.confirmAction === 'function') {
                            window.confirmAction({
                                title: 'Duplicate Component Error',
                                message: firstMsg || 'Duplicate material components found for the same routing operation. Please resolve duplicate components before saving.',
                                variant: 'warning',
                                confirmText: 'OK',
                                cancelButtonText: ''
                            });
                        } else {
                            alert(firstMsg || 'Duplicate material components found for the same routing operation. Please resolve duplicate components before saving.');
                        }
                        return false;
                    }

                    return true;
                },

                initRowSelects(rowEl, item) {
                    var self = this;
                    $(rowEl).find('[data-select2-selector="default"]').each(function () {
                        var $select = $(this);

                        if ($select.hasClass('bom-alt-select')) {
                            return;
                        }

                        if ($select.data('select2-initialized') && $select.hasClass('select2-hidden-accessible')) {
                            return;
                        }

                        // Disable parent product if selected
                        var parentProductId = $('#product_id').val();
                        if ($select.attr('name') && $select.attr('name').indexOf('material_id') !== -1) {
                            $select.find('option').each(function () {
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

                        // Capture type and sync UOM on init
                        var nameAttr = $select.attr('name') || '';
                        if (nameAttr.indexOf('material_id') !== -1) {
                            var val = $select.val();
                            if (val) {
                                item.material_id = val;
                                var selectedOption = $select.find('option[value="' + val + '"]');
                                item.material_type = selectedOption.attr('data-type') || '';
                                if (!item.uom_id) {
                                    var initMatUomId = selectedOption.attr('data-uom-id');
                                    if (!initMatUomId) {
                                        var foundMat = self.materials.find(m => String(m.id) === String(val));
                                        if (foundMat && foundMat.uom_id) {
                                            initMatUomId = foundMat.uom_id;
                                        }
                                    }
                                    if (initMatUomId) {
                                        item.uom_id = String(initMatUomId);
                                        var $uomSelect = $(rowEl).find('select.bom-row-uom-select');
                                        $uomSelect.val(item.uom_id);
                                        if ($uomSelect.data('select2')) {
                                            $uomSelect.trigger('change.select2');
                                        }
                                    }
                                }
                            }
                        }

                        // Sync select2 changes to Alpine.js
                        $select.off('change.bom-row-select').on('change.bom-row-select', function () {
                            var val = $select.val();
                            var nameAttr = $select.attr('name') || '';

                            if (nameAttr.indexOf('material_id') !== -1) {
                                item.material_id = val;
                                var selectedOption = $select.find('option[value="' + val + '"]');
                                item.material_type = selectedOption.attr('data-type') || '';
                                
                                // Auto-select component UOM based on selected material
                                var matUomId = selectedOption.attr('data-uom-id');
                                if (!matUomId && val) {
                                    var foundMat = self.materials.find(m => String(m.id) === String(val));
                                    if (foundMat && foundMat.uom_id) {
                                        matUomId = foundMat.uom_id;
                                    }
                                }
                                item.uom_id = matUomId ? String(matUomId) : '';
                                var $uomSelect = $(rowEl).find('select.bom-row-uom-select');
                                $uomSelect.val(item.uom_id);
                                if ($uomSelect.data('select2')) {
                                    $uomSelect.trigger('change.select2');
                                } else {
                                    $uomSelect.trigger('change');
                                }

                                self.fetchChildBomStatus(item);
                                self.refreshAltSelectsForOp(item.routing_operation_id);
                                self.validateDuplicateMaterials();
                            } else if (nameAttr.indexOf('uom_id') !== -1) {
                                item.uom_id = val;
                            } else if (nameAttr.indexOf('child_bom_id') !== -1) {
                                item.child_bom_id = val;
                            }

                            // Trigger native events to propagate up to Alpine
                            this.dispatchEvent(new Event('input', { bubbles: true }));
                        });
                    });

                    if (item.is_alternative) {
                        self.initAltSelect(rowEl, item);
                    }
                },

                initAllRowSelects() {
                    var self = this;
                    $('.bom-component-row').each(function () {
                        var rowUid = $(this).attr('data-row-uid') || $(this).find('input[name*="[row_uid]"]').val();
                        var item = self.items.find(it => it.uid === rowUid);
                        if (item) {
                            self.initRowSelects(this, item);
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

    {{-- Global confirmation modal --}}
    <x-ui.confirmation-modal />

    {{-- Global master quick-create modals --}}
    <x-ui.master-modals :masters="['product', 'uom']" />
@endsection