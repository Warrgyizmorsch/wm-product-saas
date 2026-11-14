@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $sourceBom = $isEdit ? $bom : ($copyFromBom ?? null);
    $isCopyMode = !$isEdit && !empty($copyFromBom);

    $defaultBomNumber = $isEdit ? $bom->bom_number : 'AUTO';
    $defaultProductId = $isEdit ? $bom->product_id : ($selectedProductId ?? ($copyFromBom?->product_id ?? ''));
    $defaultBomName = $isEdit
        ? $bom->bom_name
        : ($copyFromBom ? ($copyFromBom->bom_name ?: '') : '');
    $defaultBomType = $isEdit
        ? $bom->bom_type
        : ($copyFromBom?->bom_type ?? 'manufacturing');
    $defaultUsageContext = $isEdit
        ? $bom->usage_context
        : ($copyFromBom?->usage_context ?? 'manufacturing');
    $defaultBaseQty = $isEdit
        ? $bom->base_quantity
        : ($copyFromBom?->base_quantity ?? '1.00');
    $defaultRoutingId = $isEdit
        ? $bom->routing_id
        : ($selectedRoutingId ?? '');
    $defaultNotes = $isEdit
        ? $bom->notes
        : ($copyFromBom ? ('Copied from v' . $copyFromBom->version . ($copyFromBom->notes ? ': ' . $copyFromBom->notes : '')) : '');
    $defaultVersion = $isEdit
        ? $bom->version
        : ($suggestedVersion ?? '1.0.0');
    $defaultEffectiveDate = $isEdit && $bom->effective_date
        ? $bom->effective_date->format('Y-m-d')
        : date('Y-m-d');
    $defaultExpiryDate = $isEdit && $bom->expiry_date
        ? $bom->expiry_date->format('Y-m-d')
        : '';
    $defaultRevisionReason = $isEdit
        ? $bom->revision_reason
        : ($isCopyMode ? (($copyMode === 'revision' ? 'New revision' : 'Duplicated') . ' from v' . $copyFromBom->version) : '');

    $currentProductId = old('product_id', $defaultProductId);
    $initBaseUomId = old('base_uom_id', $products->firstWhere('id', $currentProductId)?->uom_id ?? ($sourceBom?->base_uom_id ?? ''));

    $initialAllocations = $isEdit
        ? ($bom->operationMaterials->isNotEmpty()
            ? $bom->operationMaterials->map(function ($om) {
                return [
                    'id'                        => $om->id,
                    'uid'                       => 'existing_' . $om->id,
                    'routing_operation_id'      => $om->routing_operation_id,
                    'material_id'               => $om->material_id,
                    'quantity'                  => (float) $om->quantity,
                    'uom_id'                    => $om->uom_id,
                    'material_scrap_percentage' => (float) ($om->material_scrap_percentage ?? 0),
                    'is_alternative'            => (bool) $om->is_alternative,
                    'priority'                  => $om->priority ?: 1,
                    'replaces_item_id'          => $om->replaces_routing_material_id,
                    'replaces_row_uid'          => $om->replaces_routing_material_id ? 'existing_' . $om->replaces_routing_material_id : '',
                    'child_bom_id'              => $om->child_bom_id,
                    'notes'                     => $om->notes,
                    'sequence'                  => $om->sequence,
                ];
            })->values()->all()
            : [])
        : ($copyItems ?? []);

    $oldItems = old('items', $initialAllocations);
    $hasServerOldInput = session()->hasOldInput();
    $uomsById = $uoms->keyBy('id');
@endphp

<div class="erp-single-panel bg-white" x-data="bomForm">

    @if ($errors->any())
        <x-ui.toast :auto="true" type="error"
            title="{{ __('production.validation_failed') ?? 'Validation Failed' }}: {{ $errors->first() }}" />
        <div class="alert alert-danger border-danger bg-soft-danger d-flex align-items-center justify-content-between p-2.5 mb-3 rounded-2" role="alert">
            <div class="fs-12 text-dark">
                <i class="feather-alert-circle text-danger me-1.5"></i>
                <strong>{{ __('production.validation_failed') ?? 'Validation Failed' }}:</strong> {{ $errors->first() }}
            </div>
            <button type="button" class="btn btn-xs btn-outline-danger" @click="scrollToFirstError()">
                <i class="feather-arrow-down me-1"></i>{{ __('production.view') }}
            </button>
        </div>
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    @if (session('info'))
        <div class="alert alert-info border-info bg-soft-info d-flex align-items-center p-2.5 mb-3 rounded-2 fs-12 text-dark" role="alert">
            <i class="feather-info text-info me-2 fs-15"></i>
            <span>{{ session('info') }}</span>
        </div>
    @endif

    @if($isCopyMode)
        <div class="alert alert-info border-info bg-soft-info d-flex align-items-center gap-2 p-2.5 mb-3 rounded-2 fs-12 text-dark" role="alert">
            <i class="feather-copy text-info fs-16"></i>
            <span>{{ __('production.bom_copy_notice', [
                'number'  => $copyFromBom->bom_number,
                'version' => $copyFromBom->version,
                'mode'    => $copyMode === 'revision' ? __('production.bom_mode_revision') : __('production.bom_mode_duplicate'),
            ]) }}</span>
        </div>
    @endif

    <form method="POST"
        action="{{ $isEdit ? route('production.boms.update', $bom->id) : route('production.boms.store') }}"
        id="bom-form"
        @submit="if (!validateBeforeSubmit($event)) return false;">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        @if($isCopyMode)
            {{-- BM-IMPL-03 #4: the copy is saved as a new version of the source BOM (same number, revision + 1, audit) --}}
            <input type="hidden" name="source_bom_id" value="{{ old('source_bom_id', $copyFromBom->id) }}">
        @endif
        @if(request()->has('parent_product_id'))
            <input type="hidden" name="parent_product_id" value="{{ request('parent_product_id') }}">
        @endif

        <x-ui.odoo-form-ui type="sheet">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <h4 class="fw-bold text-dark mb-0">
                    @if($isEdit)
                        {{ __('production.edit_bill_of_materials_with_number', ['number' => $bom->bom_number]) }}
                    @else
                        {{ __('production.new_bill_of_materials') }}
                    @endif
                </h4>
                @if($isEdit)
                    <x-ui.button href="{{ route('production.boms.show', $bom->id) }}" variant="light" class="border">
                        {{ __('production.cancel') }}
                    </x-ui.button>
                @else
                    <a href="{{ route('production.boms.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                @endif
            </div>

            <!-- Unsaved Draft Auto-Restored Alert -->
            <template x-if="draftDiscardedStale">
                <div class="alert alert-warning py-2 px-3 fs-12 mb-3">{{ __('production.bom_draft_stale') }}</div>
            </template>
            <template x-if="isDraftRestored">
                <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center justify-content-between p-2.5 mb-4 rounded-2 shadow-sm border border-warning-subtle" role="alert">
                    <div class="d-flex align-items-center gap-2 fs-12 text-dark">
                        <i class="feather-clock text-warning fs-16"></i>
                        <span>
                            <strong>{{ __('production.bom_draft_restored') }}:</strong> {{ __('production.bom_draft_restored_body') }} (<span x-text="draftRestoredTime"></span>).
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-xs btn-outline-danger py-1 px-2.5 fs-11" @click="discardDraft()">
                            <i class="feather-trash-2 me-1"></i>{{ __('production.bom_draft_discard') }}
                        </button>
                        <button type="button" class="btn-close fs-10 p-2 position-static" @click="isDraftRestored = false" aria-label="Close"></button>
                    </div>
                </div>
            </template>

            <!-- BOM Header Fields -->
            <div class="row g-4 mb-4 fs-13 text-dark">
                <!-- Left Column -->
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" :label="__('production.bom_number')" name="bom_number"
                        placeholder="{{ $isEdit ? __('production.bom_number_edit_placeholder') : __('production.bom_number_placeholder') }}"
                        :value="old('bom_number', $defaultBomNumber)"
                        :required="true" :error-text="$errors->first('bom_number')" alpineError="errors.bom_number" />

                    <x-ui.odoo-form-ui type="select" :label="__('production.item_to_produce')" name="product_id"
                        id="product_id" :required="true" data-master="product"
                        :error-text="$errors->first('product_id')" alpineError="errors.product_id">
                        <option value="">{{ __('production.select_product') }}</option>
                        <option value="__ADD_NEW__" class="fw-bold text-primary">{{ __('production.add_new_product') }}</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" data-uom-id="{{ $p->uom_id }}" @selected($currentProductId == $p->id)>
                                {{ $p->name }} ({{ $p->sku }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" :label="__('production.bom_name')" name="bom_name"
                        placeholder="{{ __('production.bom_name_placeholder') }}"
                        :value="old('bom_name', $defaultBomName)"
                        :required="true" :error-text="$errors->first('bom_name')" alpineError="errors.bom_name" />

                    <x-ui.odoo-form-ui type="select" :label="__('production.bom_type')" name="bom_type" :required="true"
                        :error-text="$errors->first('bom_type')" alpineError="errors.bom_type">
                        <option value="manufacturing" @selected(old('bom_type', $defaultBomType) === 'manufacturing')>{{ __('production.bom_type_manufacturing') }}</option>
                        <option value="engineering" @selected(old('bom_type', $defaultBomType) === 'engineering')>{{ __('production.bom_type_engineering') }}</option>
                        <option value="sales" @selected(old('bom_type', $defaultBomType) === 'sales')>{{ __('production.bom_type_sales') }}</option>
                        <option value="phantom" @selected(old('bom_type', $defaultBomType) === 'phantom')>{{ __('production.bom_type_phantom') }}</option>
                        <option value="subcontracting" @selected(old('bom_type', $defaultBomType) === 'subcontracting')>{{ __('production.bom_type_subcontracting') }}</option>
                    </x-ui.odoo-form-ui>
                    <small class="form-text text-muted fs-11 mb-3 d-block">
                        <i class="feather-info me-1 text-primary"></i>
                        <strong>Manufacturing:</strong> Live production &amp; MRP.
                        <strong>Engineering:</strong> Design review only.
                        <strong>Sales Kit:</strong> Sales bundles.
                        <strong>Phantom:</strong> Blow-through sub-assembly.
                        <strong>Subcontracting:</strong> Outsourced structure.
                    </small>

                    <x-ui.odoo-form-ui type="select" :label="__('production.usage_context')" name="usage_context"
                        :required="true" :error-text="$errors->first('usage_context')" alpineError="errors.usage_context">
                        <option value="manufacturing" @selected(old('usage_context', $defaultUsageContext) === 'manufacturing')>{{ __('production.usage_context_manufacturing') }}</option>
                        <option value="engineering" @selected(old('usage_context', $defaultUsageContext) === 'engineering')>{{ __('production.usage_context_engineering') }}</option>
                        <option value="prototype" @selected(old('usage_context', $defaultUsageContext) === 'prototype')>{{ __('production.usage_context_prototype') }}</option>
                        <option value="costing" @selected(old('usage_context', $defaultUsageContext) === 'costing')>{{ __('production.usage_context_costing') }}</option>
                    </x-ui.odoo-form-ui>
                </div>

                <!-- Right Column -->
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" :label="__('production.base_quantity')" name="base_quantity"
                        inputType="number" step="any" placeholder="1.0"
                        :value="old('base_quantity', $defaultBaseQty)"
                        :required="true" :error-text="$errors->first('base_quantity')" alpineError="errors.base_quantity" />

                    <input type="hidden" name="base_uom_id" id="base_uom_id" value="{{ $initBaseUomId }}">
                    <x-ui.odoo-form-ui type="select" :label="__('production.base_uom')" id="base_uom_id_display"
                        :disabled="true" :error-text="$errors->first('base_uom_id')" alpineError="errors.base_uom_id"
                        class="bg-light text-muted" style="pointer-events: none; cursor: not-allowed; background-color: #f8f9fa;">
                        <option value="">{{ __('production.select_uom') }}</option>
                        @foreach ($uoms as $uom)
                            <option value="{{ $uom->id }}" @selected($initBaseUomId == $uom->id)>{{ $uom->name }} ({{ $uom->code }})</option>
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
                            <option value="{{ $r->id }}" @selected(old('routing_id', $defaultRoutingId) == $r->id)>
                                {{ $r->routing_number }} - {{ $r->name }} (v{{ $r->version }}) [{{ $r->status === 'scheduled' ? __('production.scheduled') : ucfirst($r->status) }}]
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <small class="form-text text-muted fs-11 mb-2 d-block">
                        <i class="feather-git-commit me-1 text-primary"></i>Process route defining operations and work centers
                    </small>

                    <!-- BM-IMPL-14: Live Routing Advisory Banner -->
                    <template x-if="currentRoutingAdvisories.length > 0">
                        <div class="d-flex flex-column gap-1.5 mb-3">
                            <template x-for="adv in currentRoutingAdvisories" :key="adv.code + '_' + adv.message">
                                <div class="alert p-2 mb-0 fs-12 d-flex align-items-start gap-2 rounded-2"
                                    :class="adv.code === 'operation_removed' ? 'alert-danger border-danger bg-soft-danger' : (adv.blocks_approval ? 'alert-warning border-warning bg-soft-warning' : 'alert-info border-info bg-soft-info')">
                                    <i class="mt-0.5 flex-shrink-0"
                                        :class="adv.code === 'operation_removed' ? 'feather-alert-octagon text-danger' : (adv.blocks_approval ? 'feather-alert-triangle text-warning' : 'feather-info text-info')"></i>
                                    <div class="text-dark">
                                        <strong x-text="adv.code === 'operation_removed' ? '{{ __('production.routing_op_removed') }}' : (adv.code === 'not_active' ? '{{ __('production.routing_not_active') }}' : '{{ __('production.routing_outdated') }}')"></strong>:
                                        <span x-text="adv.message"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <x-ui.odoo-form-ui type="textarea" :label="__('production.revision_notes')" name="notes"
                        placeholder="{{ __('production.notes_placeholder') }}" rows="3"
                        :error-text="$errors->first('notes')" alpineError="errors.notes">{{ old('notes', $defaultNotes) }}</x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Advanced Configuration -->
            <div class="border rounded mb-4">
                <div class="bg-light py-2 px-3 fs-13 fw-semibold text-dark d-flex justify-content-between align-items-center c-pointer"
                    @click="showAdvanced = !showAdvanced">
                    <span><i class="feather-settings me-2"></i>{{ __('production.advanced_configuration') ?? 'Advanced Configuration & Lifecycle Dates' }}</span>
                    <i class="feather-chevron-down transition-all" style="transition: transform 0.2s;"
                        :style="showAdvanced ? 'transform: rotate(180deg);' : ''"></i>
                </div>
                <div class="bg-white"
                    style="transition: max-height 0.25s ease-out, opacity 0.25s ease-out, padding 0.25s ease-out; overflow: hidden;"
                    :style="showAdvanced ? 'max-height: 1000px; padding: 1rem; opacity: 1; border-top: 1px solid #dee2e6;' : 'max-height: 0px; padding: 0 1rem; opacity: 0; border-top: none;'">
                    <div class="row g-3 fs-13 text-dark">
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('production.version')" name="version"
                                placeholder="{{ __('production.version_placeholder') }}"
                                :value="old('version', $defaultVersion)" :required="true"
                                :error-text="$errors->first('version')" alpineError="errors.version" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('production.start_date') ?? 'Start Date'"
                                name="effective_date" inputType="date"
                                :value="old('effective_date', $defaultEffectiveDate)"
                                min="{{ date('Y-m-d') }}" :required="true"
                                :error-text="$errors->first('effective_date')" alpineError="errors.effective_date" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('production.expiry_date') ?? 'Expiry Date'"
                                name="expiry_date" inputType="date"
                                :value="old('expiry_date', $defaultExpiryDate)"
                                :error-text="$errors->first('expiry_date')" alpineError="errors.expiry_date" />
                        </div>
                        <div class="col-md-12">
                            <x-ui.odoo-form-ui type="input" :label="__('production.revision_reason') ?? 'Revision Reason'" name="revision_reason"
                                placeholder="{{ __('production.revision_reason_placeholder') }}"
                                :value="old('revision_reason', $defaultRevisionReason)"
                                :error-text="$errors->first('revision_reason')" alpineError="errors.revision_reason" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Headers -->
            <x-ui.horizontal-tabs id="bomFormTabs" :tabs="[
                ['id' => 'tab-components', 'label' => __('production.components_by_operation') ?? 'Components (By Operation)', 'active' => true, 'icon' => 'feather-list'],
                ['id' => 'tab-summary', 'label' => __('production.material_rollup') ?? 'Total Material Rollup (Live)', 'active' => false, 'icon' => 'feather-pie-chart']
            ]" />

            <div class="tab-content mt-3">
                <!-- Tab 1: Components Panel -->
                <div class="tab-pane fade show active" id="tab-components" role="tabpanel" aria-labelledby="tab-components-tab">
                    <!-- State 0: No Routing Selected -->
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
                                <span class="fw-semibold text-dark" x-text="activeOperations.length"></span>
                                <span> active operation(s)</span>
                            </div>
                        </div>

                        <!-- Operation Cards (Active ops + Deleted op cards carrying items) -->
                        <template x-for="op in visibleOperations" :key="op.id">
                            <div class="card border shadow-none mb-3 operation-card"
                                :data-op-id="op.id"
                                :id="'op-card-' + op.id"
                                :class="op.is_deleted ? 'border-danger bg-soft-danger' : (isOpExpanded(op.id) ? 'border-primary-subtle' : '')">

                                <!-- Card Header -->
                                <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center c-pointer select-none"
                                    :class="op.is_deleted ? 'bg-soft-danger border-danger-subtle' : 'bg-light'"
                                    @click="toggleOp(op.id)"
                                    style="cursor: pointer;">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <i class="feather-chevron-down text-muted transition-all"
                                            style="transition: transform 0.2s;"
                                            :style="isOpExpanded(op.id) ? 'transform: rotate(0deg);' : 'transform: rotate(-90deg);'"></i>
                                        <span class="badge fw-bold fs-11 px-2 py-1"
                                            :class="op.is_deleted ? 'bg-danger text-white' : 'bg-primary text-white'">
                                            <span x-text="op.operation_number || ('Op ' + op.sequence)"></span>
                                        </span>
                                        <span class="fw-bold text-dark fs-13" x-text="op.name"></span>

                                        <template x-if="op.is_deleted">
                                            <span class="badge bg-danger text-white fs-10 px-2 py-0.5">
                                                <i class="feather-trash-2 me-1"></i>{{ __('production.operation_deleted') }}
                                            </span>
                                        </template>

                                        <template x-if="op.work_center && op.work_center.name">
                                            <span class="badge bg-soft-secondary text-secondary border fs-11 fw-normal">
                                                <i class="feather-box me-1"></i><span x-text="op.work_center.name"></span>
                                                <template x-if="op.section && op.section.name">
                                                    <span x-text="' / ' + op.section.name"></span>
                                                </template>
                                            </span>
                                        </template>

                                        <template x-if="!op.is_deleted && !op.is_alternative">
                                            <span class="badge bg-soft-success text-success border border-success-subtle fs-11 fw-normal">
                                                <i class="feather-check-circle me-1"></i>{{ __('production.primary_operation') ?? 'Primary Operation' }}
                                            </span>
                                        </template>
                                        <template x-if="!op.is_deleted && op.is_alternative">
                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-11 fw-normal">
                                                <i class="feather-shuffle me-1"></i>{{ __('production.alternative_operation') ?? 'Alternative Operation' }}
                                                <template x-if="op.replaces_operation_id">
                                                    <span x-text="' (Substitutes Op ' + getOpSequenceById(op.replaces_operation_id) + ')'"></span>
                                                </template>
                                                <template x-if="op.priority">
                                                    <span x-text="' · Priority ' + op.priority"></span>
                                                </template>
                                            </span>
                                        </template>

                                        <!-- Metrics (RT-25 formatDuration) -->
                                        <div class="d-none d-md-flex align-items-center gap-2 text-muted fs-11 ms-2">
                                            <template x-if="op.setup_time_minutes > 0">
                                                <span>{{ __('production.bom_setup_per_run') }}: <strong x-text="formatDuration(op.setup_time_minutes)"></strong></span>
                                            </template>
                                            <template x-if="op.setup_time_per_piece_minutes > 0">
                                                <span>• {{ __('production.bom_setup_per_piece') }}: <strong x-text="formatDuration(op.setup_time_per_piece_minutes)"></strong></span>
                                            </template>
                                            <template x-if="op.processing_time_minutes > 0">
                                                <span>• {{ __('production.bom_run_per_unit') }}: <strong x-text="formatDuration(op.processing_time_minutes)"></strong></span>
                                            </template>
                                            <template x-if="op.expected_yield_percentage && op.expected_yield_percentage < 100">
                                                <span>• {{ __('production.bom_yield') }}: <strong x-text="fmt4(op.expected_yield_percentage) + '%'"></strong></span>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge rounded-pill fs-11 px-2.5 py-1"
                                            :class="op.is_deleted ? 'bg-danger text-white' : (getItemsForOp(op.id).length > 0 ? 'bg-soft-primary text-primary border border-primary-subtle' : 'bg-light text-muted border')">
                                            <i class="feather-layers me-1"></i>
                                            <span x-text="getItemsForOp(op.id).length + ' ' + (getItemsForOp(op.id).length === 1 ? 'Material' : 'Materials')"></span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Card Body -->
                                <div class="card-body p-3 bg-white" x-show="isOpExpanded(op.id)">
                                    <template x-if="op.is_deleted">
                                        <div class="alert alert-danger border-danger bg-soft-danger py-2 px-3 mb-3 fs-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <div class="text-dark">
                                                <i class="feather-alert-triangle text-danger me-1.5"></i>
                                                {{ __('production.op_removed_banner') }}
                                            </div>
                                        </div>
                                    </template>

                                    <div x-show="getItemsForOp(op.id).length > 0">
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle table-hover mb-2 fs-12">
                                                <thead class="table-light fs-11 text-uppercase text-muted border-bottom">
                                                    <tr>
                                                        <th style="width: 4%" class="text-center">#</th>
                                                        <th style="width: 30%">{{ __('production.component_product') }}</th>
                                                        <th style="width: 11%">{{ __('production.quantity') }}</th>
                                                        <th style="width: 10%">
                                                            {{ __('production.uom') }}
                                                            <i class="feather-lock text-muted ms-1 fs-11" title="Auto-selected from component product (read-only)"></i>
                                                        </th>
                                                        <th style="width: 9%">{{ __('production.scrap_percent') }}</th>
                                                        <th style="width: 11%" class="text-end">{{ __('production.gross_qty') }}</th>
                                                        <th style="width: 21%">{{ __('production.alternative') }}</th>
                                                        <th style="width: 4%" class="text-center">{{ __('production.action') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-for="(item, subIndex) in getSortedItemsForOp(op.id)" :key="item.uid">
                                                        <tr class="bom-component-row"
                                                            :class="item.is_alternative ? 'bom-alt-row bg-light-subtle' : ''"
                                                            :data-row-uid="item.uid"
                                                            x-init="$nextTick(() => initRowSelects($el, item))">
                                                            <td class="fw-bold text-center align-middle text-muted">
                                                                <span x-show="!item.is_alternative" x-text="subIndex + 1"></span>
                                                                <span x-show="item.is_alternative" class="text-warning" title="Alternative">&#8627;</span>
                                                            </td>

                                                            <!-- Component Product + Sub-BOM + Notes -->
                                                            <td class="align-middle" :style="item.is_alternative ? 'padding-left: 18px; border-left: 3px solid #f59e0b;' : ''">
                                                                <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][routing_operation_id]'" :value="item.routing_operation_id">

                                                                <x-ui.odoo-form-ui type="select" name="items[][material_id]"
                                                                    x-bind:name="'items['+getItemIndex(item)+'][material_id]'" class="odoo-table-select"
                                                                    x-model="item.material_id" required select2Selector="default"
                                                                    data-master="product"
                                                                    alpineError="errors['items.' + getItemIndex(item) + '.material_id']">
                                                                    <option value="">{{ __('production.select_component') }}</option>
                                                                    <option value="__ADD_NEW__" class="fw-bold text-primary">{{ __('production.add_new_product') }}</option>
                                                                    @foreach($materials as $material)
                                                                        <option value="{{ $material->id }}" data-type="{{ $material->type }}"
                                                                            data-sku="{{ $material->sku }}" data-uom-id="{{ $material->uom_id }}">{{ $material->name }} ({{ $material->sku }})</option>
                                                                    @endforeach
                                                                </x-ui.odoo-form-ui>

                                                                {{-- BM-IMPL-08 #1: one sub-BOM per material — chosen in the Material Rollup tab, written to every allocation of the material --}}
                                                                <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][child_bom_id]'" :value="item.child_bom_id || ''">
                                                                <template x-if="item.child_bom_loading">
                                                                    <div class="mt-1 text-muted fs-11">
                                                                        <span class="spinner-border spinner-border-sm text-secondary me-1" role="status" style="width: 10px; height: 10px;"></span>{{ __('production.checking_child_bom') }}
                                                                    </div>
                                                                </template>
                                                                <template x-if="!item.child_bom_loading && item.child_bom_id">
                                                                    <div class="mt-1 fs-11 text-muted">
                                                                        {{ __('production.bom_sub_bom') }}:
                                                                        <a :href="'{{ url('production/boms') }}/' + item.child_bom_id" target="_blank" class="text-primary fw-semibold text-decoration-none" x-text="subBomLabel(item)"></a>
                                                                        <a href="#" class="ms-1 text-muted" @click.prevent="activeTab = 'summary'; document.querySelector('#tab-summary-tab')?.click()">{{ __('production.bom_sub_bom_change_in_rollup') }}</a>
                                                                    </div>
                                                                </template>
                                                                <!-- BM-IMPL-08: Missing Sub-BOM warning for manufactured semi-finished items -->
                                                                <template x-if="!item.child_bom_loading && item.material_id && (!item.child_bom_versions || item.child_bom_versions.length === 0) && ['semi_finished', 'semi_finished_goods', 'finished_good', 'finished_goods'].includes(getMaterialType(item.material_id))">
                                                                    <div class="mt-1 fs-11 d-flex align-items-center flex-wrap gap-2">
                                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 px-1.5 py-0.5"
                                                                            x-show="['semi_finished', 'semi_finished_goods'].includes(getMaterialType(item.material_id))">
                                                                            <i class="feather-alert-triangle me-0.5"></i>No active Sub-BOM
                                                                        </span>
                                                                        <a x-bind:href="'{{ route('production.boms.create') }}?product_id=' + item.material_id + '&parent_product_id=' + (document.getElementById('product_id')?.value || '')"
                                                                            target="_blank" class="text-primary text-decoration-none fw-semibold">
                                                                            <i class="feather-plus-circle me-0.5"></i>{{ __('production.configure_sub_bom') }}
                                                                        </a>
                                                                    </div>
                                                                </template>

                                                                <!-- BM-IMPL-13 #1: Move-to-Op selector when row sits on a deleted operation -->
                                                                <template x-if="op.is_deleted">
                                                                    <div class="mt-1.5 p-1.5 bg-soft-danger border border-danger-subtle rounded d-flex align-items-center gap-2">
                                                                        <span class="fs-10 fw-bold text-danger text-uppercase">{{ __('production.move_to_op') }}:</span>
                                                                        <select class="form-select form-select-sm fs-11 py-0.5"
                                                                            @change="if ($event.target.value) { moveItemToOp(item, $event.target.value); $event.target.value = ''; }">
                                                                            <option value="">-- {{ __('production.select_target_op') }} --</option>
                                                                            <template x-for="targetOp in activeOperations" :key="targetOp.id">
                                                                                <option :value="targetOp.id" x-text="(targetOp.operation_number || ('Op ' + targetOp.sequence)) + ' - ' + targetOp.name"></option>
                                                                            </template>
                                                                        </select>
                                                                    </div>
                                                                </template>

                                                                <div class="mt-1">
                                                                    <input type="text" x-bind:name="'items['+getItemIndex(item)+'][notes]'"
                                                                        class="odoo-table-input fs-11 text-muted" placeholder="Component notes (optional)..."
                                                                        x-model="item.notes" />
                                                                </div>
                                                            </td>

                                                            <!-- Net Quantity (BM-IMPL-09: Fixed numeric only, no formula UI) -->
                                                            <td class="align-middle" style="min-width: 100px;">
                                                                <x-ui.odoo-form-ui type="input" inputType="number" step="any" min="0.0001" required
                                                                    name="items[][quantity]"
                                                                    x-bind:name="'items['+getItemIndex(item)+'][quantity]'"
                                                                    class="odoo-table-input text-end"
                                                                    x-model="item.quantity"
                                                                    placeholder="0.00"
                                                                    alpineError="errors['items.' + getItemIndex(item) + '.quantity']" />
                                                            </td>

                                                            <!-- UOM (Auto-selected read-only) -->
                                                            <td class="align-middle">
                                                                <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][uom_id]'" x-model="item.uom_id">
                                                                <select class="odoo-table-select bg-light text-muted bom-row-uom-select"
                                                                    x-model="item.uom_id" disabled tabindex="-1" data-select2-selector="default"
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

                                                            <!-- Scrap % -->
                                                            <td class="align-middle" style="min-width: 85px;">
                                                                <x-ui.odoo-form-ui type="input" inputType="number" step="any"
                                                                    name="items[][material_scrap_percentage]"
                                                                    x-bind:name="'items['+getItemIndex(item)+'][material_scrap_percentage]'"
                                                                    class="odoo-table-input text-end text-danger"
                                                                    x-model="item.material_scrap_percentage" placeholder="0.00" min="0" max="100"
                                                                    alpineError="errors['items.' + getItemIndex(item) + '.material_scrap_percentage']" />
                                                            </td>

                                                            <!-- BM-IMPL-11: Live Read-Only Gross Qty -->
                                                            <td class="align-middle text-end text-nowrap" style="min-width: 95px;">
                                                                <span class="fw-semibold"
                                                                    :class="(parseFloat(item.material_scrap_percentage) || 0) > 0 ? 'text-danger' : 'text-dark'"
                                                                    x-text="formatGrossQty(item)"></span>
                                                                <span class="text-muted fs-10 ms-0.5" x-text="getUomCode(item.uom_id)"></span>
                                                            </td>

                                                            <!-- Alternative & Priority -->
                                                            <td class="align-middle" style="min-width: 230px;">
                                                                <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][row_uid]'" :value="item.uid">
                                                                <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][is_alternative]'" :value="item.is_alternative ? 1 : 0">
                                                                <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][replaces_row_uid]'" :value="item.is_alternative ? (item.replaces_row_uid || '') : ''">
                                                                <div class="d-flex flex-column gap-1.5">
                                                                    <div class="form-check form-switch mb-0">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            x-model="item.is_alternative"
                                                                            @change="onAlternativeToggle(item)"
                                                                            x-bind:id="'is_alternative_' + item.uid">
                                                                        <label class="form-check-label fs-11 fw-semibold" x-bind:for="'is_alternative_' + item.uid"
                                                                            x-text="item.is_alternative ? '{{ __('production.alternative_component') }}' : '{{ __('production.primary_component') }}'"></label>
                                                                    </div>
                                                                    <div x-show="item.is_alternative" class="mt-1">
                                                                        <div class="d-flex gap-2 align-items-end">
                                                                            <div class="flex-grow-1" style="min-width: 140px;">
                                                                                <label class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">
                                                                                    {{ __('production.alternative_of') }} <span class="text-danger">*</span>
                                                                                </label>
                                                                                <select class="form-select form-select-sm fs-11 bom-alt-select"
                                                                                    data-select2-selector="alt" style="width: 100%;" :required="item.is_alternative">
                                                                                    <option value="">-- {{ __('production.select_primary_component') }} --</option>
                                                                                </select>
                                                                            </div>
                                                                            <div style="width: 65px;">
                                                                                <label class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">{{ __('production.priority') }}</label>
                                                                                <input type="number"
                                                                                    x-bind:name="'items['+getItemIndex(item)+'][priority]'"
                                                                                    class="odoo-table-input text-end" style="width: 100%;"
                                                                                    x-model="item.priority" placeholder="1" min="1" :required="item.is_alternative">
                                                                            </div>
                                                                        </div>
                                                                        <template x-if="errors['items.' + getItemIndex(item) + '.replaces_row_uid']">
                                                                            <div class="invalid-feedback d-block fs-11 mt-1" x-text="Array.isArray(errors['items.' + getItemIndex(item) + '.replaces_row_uid']) ? errors['items.' + getItemIndex(item) + '.replaces_row_uid'][0] : errors['items.' + getItemIndex(item) + '.replaces_row_uid']"></div>
                                                                        </template>
                                                                        <template x-if="errors['items.' + getItemIndex(item) + '.priority']">
                                                                            <div class="invalid-feedback d-block fs-11 mt-1" x-text="Array.isArray(errors['items.' + getItemIndex(item) + '.priority']) ? errors['items.' + getItemIndex(item) + '.priority'][0] : errors['items.' + getItemIndex(item) + '.priority']"></div>
                                                                        </template>
                                                                    </div>
                                                                    <template x-if="!item.is_alternative">
                                                                        <input type="hidden" x-bind:name="'items['+getItemIndex(item)+'][priority]'" value="1">
                                                                    </template>
                                                                </div>
                                                            </td>

                                                            <!-- Delete Row -->
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

                                        <template x-if="!op.is_deleted">
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-link p-0 text-primary fw-semibold text-decoration-none shadow-none border-0 bg-transparent fs-12"
                                                    @click="addItemToOp(op.id)">
                                                    <i class="feather-plus me-1 fs-13"></i>{{ __('production.add_material_to_op') }} <span x-text="op.sequence"></span>
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- When Operation has ZERO Materials -->
                                    <div x-show="getItemsForOp(op.id).length === 0 && !op.is_deleted">
                                        <div class="alert alert-light border border-dashed d-flex align-items-center justify-content-between p-3 mb-0 rounded-2">
                                            <div class="d-flex align-items-center text-muted fs-12">
                                                <i class="feather-info text-secondary me-2 fs-16"></i>
                                                <div>
                                                    <div class="fw-semibold text-dark">{{ __('production.process_operation_zero_materials') }}</div>
                                                    <div class="fs-11 text-muted" x-text="@js(__('production.zero_materials_notice', ['seq' => '__SEQ__'])).replace('__SEQ__', op.sequence)"></div>
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

                <!-- Tab 2: Live Material Rollup / Summary Panel -->
                <div class="tab-pane fade" id="tab-summary" role="tabpanel" aria-labelledby="tab-summary-tab">
                    <div class="card border shadow-none mb-3">
                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold fs-13 text-dark">
                                    <i class="feather-pie-chart text-primary me-1.5"></i>{{ __('production.material_rollup') }}
                                </h6>
                                <small class="text-muted fs-11">{{ __('production.bom_rollup_subtitle') }}</small>
                            </div>
                            <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-11">
                                <span x-text="summarizedMaterials.length"></span> {{ __('production.bom_unique_components') }}
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <template x-if="summarizedMaterials.length === 0">
                                <div class="text-center py-5 text-muted">
                                    <i class="feather-box fs-32 text-muted mb-2 d-block"></i>
                                    <p class="mb-1 fw-semibold fs-13">{{ __('production.bom_rollup_empty_title') }}</p>
                                    <p class="fs-12 text-muted mb-0">{{ __('production.bom_rollup_empty_body') }}</p>
                                </div>
                            </template>

                            <template x-if="summarizedMaterials.length > 0">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle table-hover mb-0 fs-12">
                                        <thead class="table-light fs-11 text-uppercase text-muted border-bottom">
                                            <tr>
                                                <th style="width: 3%" class="text-center"></th>
                                                <th style="width: 20%">{{ __('production.component_product') }}</th>
                                                <th style="width: 9%">{{ __('production.type') }}</th>
                                                <th style="width: 10%">{{ __('production.bom_rollup_role') }}</th>
                                                <th style="width: 10%" class="text-end">{{ __('production.quantity') }}</th>
                                                <th style="width: 11%" class="text-end">{{ __('production.bom_alternative_qty') }}</th>
                                                <th style="width: 10%" class="text-end">{{ __('production.gross_qty') }}</th>
                                                <th style="width: 8%" class="text-end">{{ __('production.bom_effective_scrap') }}</th>
                                                <th style="width: 6%">{{ __('production.uom') }}</th>
                                                <th style="width: 13%">{{ __('production.bom_sub_bom') }}</th>
                                            </tr>
                                        </thead>
                                        <template x-for="item in summarizedMaterials" :key="item.material_id">
                                            <tbody class="border-top-0">
                                                <tr class="c-pointer" @click="toggleSummaryRow(item.material_id)" style="cursor: pointer;"
                                                    :class="isSummaryRowExpanded(item.material_id) ? 'table-active fw-medium' : ''">
                                                    <td class="text-center text-muted">
                                                        <i class="feather-chevron-right transition-all"
                                                            :style="isSummaryRowExpanded(item.material_id) ? 'transform: rotate(90deg); display: inline-block;' : 'display: inline-block;'"></i>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold text-dark" x-text="item.name"></div>
                                                        <small class="text-muted" x-text="'SKU: ' + item.sku"></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-soft-secondary text-secondary text-capitalize" x-text="item.type.replace('_', ' ')"></span>
                                                    </td>
                                                    <td>
                                                        <template x-if="item.has_primary && item.has_alternative">
                                                            <span class="badge bg-soft-info text-info border border-info-subtle fs-11">
                                                                <i class="feather-shuffle me-1"></i>{{ __('production.bom_mixed_roles') }}
                                                            </span>
                                                        </template>
                                                        <template x-if="item.has_primary && !item.has_alternative">
                                                            <span class="badge bg-soft-success text-success border border-success-subtle fs-11">
                                                                <i class="feather-check-circle me-1"></i>{{ __('production.bom_primary_only') }}
                                                            </span>
                                                        </template>
                                                        <template x-if="!item.has_primary && item.has_alternative">
                                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-11">
                                                                <i class="feather-tag me-1"></i>{{ __('production.bom_alternative_only') }}
                                                            </span>
                                                        </template>
                                                    </td>
                                                    <td class="text-end fw-bold text-dark" x-text="fmt4(item.primary_qty)"></td>
                                                    <td class="text-end">
                                                        <template x-if="item.has_alternative">
                                                            <div>
                                                                <span class="text-muted" x-text="fmt4(item.alternative_qty)"></span>
                                                                <div><span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10">{{ __('production.bom_alt_not_in_total') }}</span></div>
                                                            </div>
                                                        </template>
                                                        <template x-if="!item.has_alternative"><span class="text-muted">—</span></template>
                                                    </td>
                                                    <td class="text-end fw-bold text-primary" x-text="fmt4(item.primary_gross_qty)"></td>
                                                    <td class="text-end text-danger" x-text="effectiveScrap(item.primary_qty, item.primary_gross_qty)"></td>
                                                    <td x-text="item.uom_name"></td>
                                                    <td @click.stop>
                                                        <template x-if="item.child_bom_loading">
                                                            <span class="spinner-border spinner-border-sm text-secondary" style="width: 10px; height: 10px;"></span>
                                                        </template>
                                                        <template x-if="!item.child_bom_loading && item.child_bom_versions.length > 0">
                                                            <div>
                                                                <select class="form-select form-select-sm fs-11 py-0.5" :value="item.child_bom_id || ''"
                                                                    @change="setSubBomForMaterial(item.material_id, $event.target.value)">
                                                                    <option value="">{{ __('production.bom_sub_bom_none') }}</option>
                                                                    <template x-for="v in item.child_bom_versions" :key="v.id">
                                                                        <option :value="v.id" :selected="String(v.id) === String(item.child_bom_id)" x-text="(v.bom_number || '') + ' v' + v.version + ' (' + v.status + ')'"></option>
                                                                    </template>
                                                                </select>
                                                                <template x-if="subBomWarning(item)">
                                                                    <div class="fs-10 text-warning mt-0.5" x-text="subBomWarning(item)"></div>
                                                                </template>
                                                            </div>
                                                        </template>
                                                        <template x-if="!item.child_bom_loading && item.child_bom_versions.length === 0">
                                                            <span class="text-muted fs-11">—</span>
                                                        </template>
                                                    </td>
                                                </tr>
                                                <tr x-show="isSummaryRowExpanded(item.material_id)" class="bg-light-subtle">
                                                    <td colspan="10" class="p-3 bg-light-subtle border-bottom">
                                                        <div class="card border shadow-none mb-0 bg-white">
                                                            <div class="card-header py-1.5 px-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                                                                <span class="fs-11 fw-bold text-uppercase text-muted">
                                                                    <i class="feather-layers me-1 text-primary"></i>{{ __('production.bom_allocations_breakdown') }}
                                                                </span>
                                                                <span class="fs-11 text-muted" x-text="item.allocations.length + ' {{ __('production.bom_operations_count') }}'"></span>
                                                            </div>
                                                            <div class="card-body p-0">
                                                                <table class="table table-sm mb-0 fs-11">
                                                                    <thead class="table-light text-muted">
                                                                        <tr>
                                                                            <th style="width: 24%">{{ __('production.bom_operation') }}</th>
                                                                            <th style="width: 26%">{{ __('production.bom_role_substitution') }}</th>
                                                                            <th style="width: 14%" class="text-end">{{ __('production.net_qty') }}</th>
                                                                            <th style="width: 10%" class="text-end">{{ __('production.scrap_percent') }}</th>
                                                                            <th style="width: 14%" class="text-end">{{ __('production.gross_qty') }}</th>
                                                                            <th style="width: 12%" class="text-end">{{ __('production.action') }}</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <template x-for="alloc in item.allocations" :key="alloc.uid">
                                                                            <tr>
                                                                                <td>
                                                                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                                                                        <span class="badge bg-primary text-white fw-bold" x-text="'Op ' + alloc.op_sequence"></span>
                                                                                        <span class="fw-semibold text-dark" x-text="alloc.op_name"></span>
                                                                                    </div>
                                                                                    <template x-if="alloc.notes">
                                                                                        <div class="text-muted fs-10 fst-italic mt-0.5" x-text="alloc.notes"></div>
                                                                                    </template>
                                                                                </td>
                                                                                <td>
                                                                                    <template x-if="alloc.is_alternative">
                                                                                        <div>
                                                                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle">
                                                                                                <i class="feather-shuffle me-1"></i>{{ __('production.bom_alternative_priority') }} <span x-text="alloc.priority"></span>
                                                                                            </span>
                                                                                            <template x-if="alloc.replaces_name">
                                                                                                <div class="text-muted fs-10 mt-0.5" x-text="'{{ __('production.bom_substitutes') }}: ' + alloc.replaces_name"></div>
                                                                                            </template>
                                                                                        </div>
                                                                                    </template>
                                                                                    <template x-if="!alloc.is_alternative">
                                                                                        <span class="badge bg-soft-success text-success border border-success-subtle">
                                                                                            <i class="feather-check-circle me-1"></i>{{ __('production.bom_primary_component') }}
                                                                                        </span>
                                                                                    </template>
                                                                                </td>
                                                                                <td class="text-end fw-semibold text-dark" x-text="fmt4(alloc.quantity)"></td>
                                                                                <td class="text-end text-danger" x-text="fmt4(alloc.scrap_percentage) + '%'"></td>
                                                                                <td class="text-end fw-bold text-primary" x-text="fmt4(alloc.gross_quantity)"></td>
                                                                                <td class="text-end">
                                                                                    <button type="button" class="btn btn-xs btn-outline-primary py-0.5 px-2 fs-10"
                                                                                        @click.stop="jumpToOpCard(alloc.routing_operation_id, alloc.uid)">
                                                                                        <i class="feather-corner-down-right me-0.5"></i>{{ __('production.jump_to_op_card') }}
                                                                                    </button>
                                                                                </td>
                                                                            </tr>
                                                                        </template>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </template>
                                    </table>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 pt-3 border-top mt-4">
                <x-ui.button type="submit" variant="primary" class="px-4">{{ __('production.save_bom') }}</x-ui.button>
                <x-ui.button href="{{ $isEdit ? route('production.boms.show', $bom->id) : route('production.boms.index') }}" variant="secondary" class="px-4">{{ __('production.cancel') }}</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</div>

<style>
    .bom-row-uom-select,
    .bom-row-uom-select + .select2-container,
    .bom-row-uom-select + .select2-container .select2-selection,
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
    .operation-card:hover { border-color: #cbd5e1; }
    .operation-card.border-primary-subtle { border-color: #c7d2fe !important; }
    .c-pointer { cursor: pointer; }
    .select-none { user-select: none; }
    .bom-select2-dropdown .select2-results__option { font-size: 11px; padding: 6px 10px; }
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

@include('modules.production.bom.partials._form_script', [
    'isEdit'               => $isEdit,
    'isCopyMode'           => $isCopyMode,
    'bom'                  => $bom ?? null,
    'oldItems'             => $oldItems,
    'hasServerOldInput'    => $hasServerOldInput,
    'defaultRoutingId'     => $defaultRoutingId,
    'products'             => $products,
    'materials'            => $materials,
    'uoms'                 => $uoms,
    'uomsById'             => $uomsById,
    'routings'             => $routings,
    'routingAdvisoriesMap' => $routingAdvisoriesMap ?? [],
])

{{-- Global master quick-create modals --}}
<x-ui.master-modals :masters="['product', 'uom']" />

