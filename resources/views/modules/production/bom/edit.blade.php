@extends('layouts.duralux')

@section('title', 'Edit BOM | SaaS ERP')
@section('page-title', 'Edit Bill of Materials')
@section('breadcrumb', 'Edit BOM')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Keep input components inside table cells extremely compact */
        .erp-thin-table td .mb-3 {
            margin-bottom: 0 !important;
        }
        .erp-thin-table td .form-control,
        .erp-thin-table td .form-select {
            height: 34px !important;
            padding: 4px 8px !important;
            font-size: 12px !important;
        }
        .erp-thin-table td .select2-container--bootstrap-5 .select2-selection {
            min-height: 34px !important;
            height: 34px !important;
            padding: 3px 8px !important;
            font-size: 12px !important;
        }
        .c-pointer {
            cursor: pointer;
        }
    </style>
@endpush

@push('scripts')
    <!-- Load Alpine.js for dynamic component grid management -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="erp-single-panel bg-white" x-data="bomForm">
        <!-- Header with Close Button -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h4 class="fw-bold text-dark mb-0">Edit Bill of Materials (BOM #{{ $bom->bom_number }})</h4>
            <a href="{{ route('production.boms.show', $bom->id) }}" class="text-muted hover-danger fs-18">
                <i class="feather-x"></i>
            </a>
        </div>

        <!-- Validation Errors & Warning Banners -->
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">Validation Errors!</h6>
                <ul class="mb-0 fs-12 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        @if (session('error'))
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                <p class="fs-12 mb-0">{{ session('error') }}</p>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        <form method="POST" action="{{ route('production.boms.update', $bom->id) }}">
            @csrf
            @method('PUT')
            @if(request()->has('parent_product_id'))
                <input type="hidden" name="parent_product_id" value="{{ request('parent_product_id') }}">
            @endif

            <!-- BOM Header Fields using Redesigned Common Components -->
            <div class="row g-4 mb-4">
                <!-- Left Column -->
                <div class="col-md-6">
                    <x-ui.input label="Bill of Material#*" name="bom_number" placeholder="e.g. BOM-XYZ-001" value="{{ old('bom_number', $bom->bom_number) }}" required />
                    
                    <x-ui.select label="Item to Produce*" name="product_id" id="product_id" :options="['' => 'Select Product'] + $products->pluck('name', 'id')->toArray()" selected="{{ old('product_id', $bom->product_id) }}" data-select2-selector="default" master="product" required />

                    <x-ui.input label="BOM Description Name" name="bom_name" placeholder="e.g. Standard Red Door BOM" value="{{ old('bom_name', $bom->bom_name) }}" required />

                    <x-ui.select label="BOM Type*" name="bom_type" :options="[
                        'manufacturing' => 'Manufacturing BOM (Standard)',
                        'engineering' => 'Engineering BOM (R&D)',
                        'sales' => 'Sales BOM (Kit)',
                        'phantom' => 'Phantom (Blow-Through)',
                        'subcontracting' => 'Subcontracting (Outsourced)'
                    ]" selected="{{ old('bom_type', $bom->bom_type) }}" data-select2-selector="default" required />
                </div>

                <!-- Right Column -->
                <div class="col-md-6">
                    <x-ui.input label="Quantity*" name="base_quantity" type="number" step="any" placeholder="1.0" value="{{ old('base_quantity', $bom->base_quantity) }}" required />
                    
                    <x-ui.select label="Base Unit*" name="base_uom_id" :options="['' => 'Select UOM'] + $uoms->pluck('name', 'id')->toArray()" selected="{{ old('base_uom_id', $bom->base_uom_id) }}" data-select2-selector="default" master="uom" required />
                    
                    <x-ui.textarea label="Description" name="notes" placeholder="Max. 500 characters" value="{{ old('notes', $bom->notes) }}" rows="3" />
                </div>
            </div>

            <!-- Advanced Configuration Collapsible settings using Alpine.js -->
            <div class="border rounded mb-4">
                <div class="bg-light py-2 px-3 fs-13 fw-semibold text-dark d-flex justify-content-between align-items-center c-pointer" @click="showAdvanced = !showAdvanced">
                    <span><i class="feather-settings me-2"></i>Advanced Configuration & Lifecycle Dates</span>
                    <i class="feather-chevron-down transition-all" style="transition: transform 0.2s;" :style="showAdvanced ? 'transform: rotate(180deg);' : ''"></i>
                </div>
                <div class="bg-white" 
                     style="transition: max-height 0.25s ease-out, opacity 0.25s ease-out, padding 0.25s ease-out; overflow: hidden;"
                     :style="showAdvanced ? 'max-height: 1000px; padding: 1rem; opacity: 1; border-top: 1px solid #dee2e6;' : 'max-height: 0px; padding: 0 1rem; opacity: 0; border-top: none;'">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.input label="Version ID*" name="version" placeholder="e.g. 1.0.0" value="{{ old('version', $bom->version) }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Routing Reference" name="routing_id" id="routing_id" :options="['' => 'No Routing Reference'] + $routings->pluck('name', 'id')->toArray()" selected="{{ old('routing_id', $bom->routing_id) }}" data-select2-selector="default" x-model="selectedRoutingId" @change="loadOperations()" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Effective Start Date*" name="effective_date" type="date" value="{{ old('effective_date', $bom->effective_date ? $bom->effective_date->format('Y-m-d') : date('Y-m-d')) }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Effective Expiry Date" name="expiry_date" type="date" value="{{ old('expiry_date', $bom->expiry_date ? $bom->expiry_date->format('Y-m-d') : '') }}" />
                        </div>
                        <div class="col-md-12">
                            <x-ui.input label="Revision Reason" name="revision_reason" placeholder="e.g. Change in raw steel specifications" value="{{ old('revision_reason', $bom->revision_reason) }}" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Headers -->
            <div class="erp-tabs-nav">
                <a href="javascript:void(0)" class="erp-tabs-link" :class="activeTab === 'components' ? 'active' : ''" @click="activeTab = 'components'">Components</a>
                <a href="javascript:void(0)" class="erp-tabs-link" :class="activeTab === 'operations' ? 'active' : ''" @click="activeTab = 'operations'; loadOperations()">Operations*</a>
            </div>

            <!-- Tab 1: Components Panel -->
            <div x-show="activeTab === 'components'" x-transition>
                <h5 class="fw-bold text-dark mb-3">Add Component*</h5>
                <div class="table-responsive mb-4">
                    <table class="erp-thin-table">
                        <thead>
                            <tr>
                                <th style="width: 5%" class="text-center">Seq</th>
                                <th style="width: 25%">Material Component</th>
                                <th style="width: 10%">Quantity</th>
                                <th style="width: 12%">UOM</th>
                                <th style="width: 10%">Scrap %</th>
                                <th style="width: 8%">Priority</th>
                                <th style="width: 15%">Validity (From - To)</th>
                                <th style="width: 12%">Alternative</th>
                                <th style="width: 5%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="item.uid">
                                <tr x-init="$nextTick(() => initRowSelects($el, item))">
                                    <!-- Sequence -->
                                    <td class="fw-bold text-center align-middle" x-text="index + 1"></td>
                                    
                                    <!-- Material Selection -->
                                    <td class="align-middle">
                                        <x-ui.select x-bind:name="'items['+index+'][material_id]'" class="fs-13" x-model="item.material_id" required data-select2-selector="default" master="product">
                                            <option value="">Select Material...</option>
                                            @foreach($materials as $material)
                                                <option value="{{ $material->id }}" data-type="{{ $material->type }}" data-sku="{{ $material->sku }}">{{ $material->name }} ({{ $material->sku }})</option>
                                            @endforeach
                                        </x-ui.select>
                                        <template x-if="errors && errors['items.' + index + '.material_id']">
                                            <span class="text-danger fs-11 mt-1 d-block" x-text="errors['items.' + index + '.material_id'][0]"></span>
                                        </template>
                                        <template x-if="getMaterialType(item.material_id) === 'semi_finished'">
                                            <div class="erp-child-bom-cta mt-2">
                                                <a x-bind:href="'{{ route('production.boms.create') }}?product_id=' + item.material_id + '&parent_product_id=' + (document.getElementById('product_id')?.value || '')" target="_blank" class="btn btn-soft-primary">
                                                    <i class="feather-plus me-1"></i>Create Child BOM
                                                </a>
                                                <a x-bind:href="'{{ route('production.boms.index') }}?product_id=' + item.material_id" target="_blank" class="btn btn-soft-info">
                                                    <i class="feather-eye me-1"></i>View BOMs
                                                </a>
                                            </div>
                                        </template>
                                    </td>
                                    
                                    <!-- Quantity -->
                                    <td class="align-middle">
                                        <x-ui.input type="number" step="any" x-bind:name="'items['+index+'][quantity]'" class="text-end fs-13" x-model="item.quantity" placeholder="0.00" required min="0.0001" />
                                        <template x-if="errors && errors['items.' + index + '.quantity']">
                                            <span class="text-danger fs-11 mt-1 d-block" x-text="errors['items.' + index + '.quantity'][0]"></span>
                                        </template>
                                    </td>
                                    
                                    <!-- UOM -->
                                    <td class="align-middle">
                                        <x-ui.select x-bind:name="'items['+index+'][uom_id]'" class="fs-13" x-model="item.uom_id" required data-select2-selector="default" master="uom">
                                            <option value="">Select UOM...</option>
                                            @foreach($uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->code }})</option>
                                            @endforeach
                                        </x-ui.select>
                                        <template x-if="errors && errors['items.' + index + '.uom_id']">
                                            <span class="text-danger fs-11 mt-1 d-block" x-text="errors['items.' + index + '.uom_id'][0]"></span>
                                        </template>
                                    </td>
                                    
                                    <!-- Material Scrap Percentage -->
                                    <td class="align-middle">
                                        <x-ui.input type="number" step="any" x-bind:name="'items['+index+'][material_scrap_percentage]'" class="text-end fs-13 text-danger" x-model="item.material_scrap_percentage" placeholder="0.00" min="0" max="100" />
                                        <template x-if="errors && errors['items.' + index + '.material_scrap_percentage']">
                                            <span class="text-danger fs-11 mt-1 d-block" x-text="errors['items.' + index + '.material_scrap_percentage'][0]"></span>
                                        </template>
                                    </td>

                                    <!-- Priority -->
                                    <td class="align-middle">
                                        <x-ui.input type="number" x-bind:name="'items['+index+'][priority]'" class="text-end fs-13" x-model="item.priority" placeholder="1" min="1" />
                                        <template x-if="errors && errors['items.' + index + '.priority']">
                                            <span class="text-danger fs-11 mt-1 d-block" x-text="errors['items.' + index + '.priority'][0]"></span>
                                        </template>
                                    </td>

                                    <!-- Validity limits -->
                                    <td class="align-middle">
                                        <div class="d-flex flex-column gap-1">
                                            <x-ui.input type="date" x-bind:name="'items['+index+'][effective_from]'" class="form-control-sm fs-11" x-model="item.effective_from" />
                                            <template x-if="errors && errors['items.' + index + '.effective_from']">
                                                <span class="text-danger fs-10 mt-1 d-block" x-text="errors['items.' + index + '.effective_from'][0]"></span>
                                            </template>
                                            <x-ui.input type="date" x-bind:name="'items['+index+'][effective_to]'" class="form-control-sm fs-11" x-model="item.effective_to" />
                                            <template x-if="errors && errors['items.' + index + '.effective_to']">
                                                <span class="text-danger fs-10 mt-1 d-block" x-text="errors['items.' + index + '.effective_to'][0]"></span>
                                            </template>
                                        </div>
                                    </td>
                                    
                                    <!-- Alternative material options -->
                                    <td class="align-middle">
                                        <div class="d-flex flex-column gap-2">
                                            <div>
                                                <input type="hidden" x-bind:name="'items['+index+'][is_alternative]'" :value="item.is_alternative ? 1 : 0">
                                                
                                                <x-ui.checkbox 
                                                    x-model="item.is_alternative" 
                                                    class="fs-11" 
                                                    x-bind:id="'is_alternative_edit_' + index"
                                                />
                                                <label class="form-check-label c-pointer fw-medium text-dark fs-11 ms-1" x-bind:for="'is_alternative_edit_' + index">
                                                    Is Alternative
                                                </label>
                                            </div>
                                            <div x-show="item.is_alternative">
                                                <x-ui.input type="text" x-bind:name="'items['+index+'][alternative_group]'" class="form-control-sm fs-11" placeholder="Alt Group Code..." x-model="item.alternative_group" />
                                                <template x-if="errors && errors['items.' + index + '.alternative_group']">
                                                    <span class="text-danger fs-11 mt-1 d-block" x-text="errors['items.' + index + '.alternative_group'][0]"></span>
                                                </template>
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
                    </table>
                </div>

                <!-- Add Row Action -->
                <div class="mb-4">
                    <button type="button" class="btn btn-light-brand" @click="addItem()">
                        <i class="feather-plus me-2"></i>Add Component Row
                    </button>
                </div>
            </div>

            <!-- Tab 2: Operations Panel (Read-only viewed, linked to Routing reference dropdown) -->
            <div x-show="activeTab === 'operations'" x-transition style="display: none;">
                <h5 class="fw-bold text-dark mb-3">Routing Operations List</h5>
                
                <template x-if="!selectedRoutingId">
                    <div class="p-4 text-center border rounded bg-light text-muted">
                        <i class="feather-info me-2"></i>Select routing to see operations
                    </div>
                </template>
                
                <template x-if="selectedRoutingId">
                    <div>
                        <template x-if="loadingOperations">
                            <div class="p-4 text-center">
                                <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading operations...
                            </div>
                        </template>
                        
                        <template x-if="!loadingOperations && operations.length === 0">
                            <div class="p-4 text-center border rounded bg-light text-muted">
                                No operations defined for this routing.
                            </div>
                        </template>
                        
                        <template x-if="!loadingOperations && operations.length > 0">
                            <div class="table-responsive">
                                <table class="erp-thin-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 10%" class="text-center">Seq</th>
                                            <th style="width: 25%">Operation Stage</th>
                                            <th style="width: 15%">Type</th>
                                            <th style="width: 20%">Work Center</th>
                                            <th style="width: 15%">Machine</th>
                                            <th style="width: 15%" class="text-end">Times & Yield</th>
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
                                                    <div class="fs-11 text-muted">Setup: <span class="fw-semibold text-dark" x-text="op.setup_time_minutes + 'm'"></span></div>
                                                    <div class="fs-11 text-muted">Run: <span class="fw-semibold text-dark" x-text="op.processing_time_minutes + 'm'"></span></div>
                                                    <div class="fs-11 text-muted">Yield: <span class="fw-semibold text-dark" x-text="op.expected_yield_percentage + '%'"></span></div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Footer Save and Cancel buttons -->
            <div class="d-flex gap-2 pt-3 border-top mt-4">
                <button type="submit" class="btn btn-primary px-4">Update BOM</button>
                <a href="{{ route('production.boms.show', $bom->id) }}" class="btn btn-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>

    @php
        $oldItems = old('items', $bom->items->toArray());
    @endphp

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bomForm', () => ({
                items: @json($oldItems),
                errors: @json($errors->toArray()),
                materials: @json($materials),
                selectedRoutingId: '{{ old("routing_id", $bom->routing_id) }}',
                operations: [],
                loadingOperations: false,
                activeTab: 'components',
                showAdvanced: true,

                getMaterialType(materialId) {
                    if (!materialId) return '';
                    var mat = this.materials.find(m => m.id == materialId);
                    return mat ? mat.type : '';
                },

                init() {
                    this.items = this.items.map((item, idx) => ({
                        uid: item.uid || 'row_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9) + '_' + idx,
                        material_id: item.material_id || '',
                        quantity: item.quantity || '',
                        uom_id: item.uom_id || '',
                        material_scrap_percentage: item.material_scrap_percentage || 0,
                        is_alternative: !!parseInt(item.is_alternative) || item.is_alternative === true,
                        alternative_group: item.alternative_group || '',
                        priority: item.priority || 1,
                        effective_from: item.effective_from || '',
                        effective_to: item.effective_to || ''
                    }));
                    if(this.items.length === 0) {
                        this.addItem();
                    }

                    // Hook select2 change event for routing reference
                    $('#routing_id').on('change.select2', (e) => {
                        this.selectedRoutingId = e.target.value;
                        this.loadOperations();
                    });

                    // Trigger initial load
                    this.loadOperations();
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
                        quantity: '',
                        uom_id: '',
                        material_scrap_percentage: 0,
                        is_alternative: false,
                        alternative_group: '',
                        priority: 1,
                        effective_from: '',
                        effective_to: ''
                    });
                },

                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    } else {
                        alert("BOM must contain at least one material component row.");
                    }
                },

                initRowSelects(rowEl, item) {
                    $(rowEl).find('[data-select2-selector="default"]').each(function() {
                        var $select = $(this);
                        
                        // Initialize select2 with bootstrap-5 theme
                        $select.select2({
                            theme: "bootstrap-5",
                            dropdownParent: $(rowEl).closest('.table-responsive')
                        });
                        
                        // Capture type on init
                        var nameAttr = $select.attr('name') || '';
                        if (nameAttr.indexOf('material_id') !== -1) {
                            var val = $select.val();
                            var selectedOption = $select.find('option[value="' + val + '"]');
                            item.material_type = selectedOption.attr('data-type') || '';
                        }
                        
                        // Sync select2 changes to Alpine.js
                        $select.on('change.select2', function () {
                            var val = $select.val();
                            var nameAttr = $select.attr('name') || '';
                            
                            if (nameAttr.indexOf('material_id') !== -1) {
                                item.material_id = val;
                                var selectedOption = $select.find('option[value="' + val + '"]');
                                item.material_type = selectedOption.attr('data-type') || '';
                            } else if (nameAttr.indexOf('uom_id') !== -1) {
                                item.uom_id = val;
                            }
                            
                            // Trigger native events to propagate up to Alpine
                            this.dispatchEvent(new Event('input', { bubbles: true }));
                            this.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });
                }
            }));
        });
    </script>

    {{-- Global master quick-create modals --}}
    <x-ui.master-modals :masters="['product', 'uom']" />
@endsection
