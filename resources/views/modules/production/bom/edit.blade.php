@extends('layouts.duralux')

@section('title', 'Edit BOM | SaaS ERP')
@section('page-title', 'Edit Bill of Materials')
@section('breadcrumb', 'Edit BOM')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Remove bottom margin of premium form components inside table cells */
        .table-responsive-container td .mb-3,
        .table-responsive td .mb-3 {
            margin-bottom: 0 !important;
        }
    </style>
@endpush

@push('scripts')
    <!-- Load Alpine.js for dynamic component grid management -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <a href="{{ route('production.boms.show', $bom->id) }}" class="btn btn-secondary">
        <i class="feather-x me-2"></i>Cancel
    </a>
@endsection

@section('content')
    <!-- Validation Errors & Warning Banners -->
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Validation Errors!</h6>
                    <ul class="mb-0 fs-12 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Session Error Message -->
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                    <p class="fs-12 mb-0">{{ session('error') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('production.boms.update', $bom->id) }}" x-data="bomForm">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <!-- Left Column: Header Information -->
            <div class="col-xl-12">
                <x-ui.card title="BOM #{{ $bom->bom_number }} Details">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <x-ui.input label="BOM Identifier / Number" name="bom_number" placeholder="e.g. BOM-XYZ-001" value="{{ old('bom_number', $bom->bom_number) }}" required />
                        </div>
                        <div class="col-md-3">
                            <x-ui.input label="BOM Description Name" name="bom_name" placeholder="e.g. Standard Red Door BOM" value="{{ old('bom_name', $bom->bom_name) }}" required />
                        </div>
                        <div class="col-md-3">
                            <x-ui.select label="Target Finished Product" name="product_id" :options="['' => 'Select Product'] + $products->pluck('name', 'id')->toArray()" selected="{{ old('product_id', $bom->product_id) }}" data-select2-selector="default" required />
                        </div>
                        <div class="col-md-3">
                            <x-ui.select label="BOM Type" name="bom_type" :options="[
                                'manufacturing' => 'Manufacturing BOM (Standard)',
                                'engineering' => 'Engineering BOM (R&D)',
                                'sales' => 'Sales BOM (Kit)',
                                'phantom' => 'Phantom (Blow-Through)',
                                'subcontracting' => 'Subcontracting (Outsourced)'
                            ]" selected="{{ old('bom_type', $bom->bom_type) }}" data-select2-selector="default" required />
                        </div>
                        
                        <div class="col-md-3">
                            <x-ui.input label="Base Production Qty" name="base_quantity" type="number" step="any" placeholder="1.0" value="{{ old('base_quantity', $bom->base_quantity) }}" required />
                        </div>
                        <div class="col-md-3">
                            <x-ui.select label="Base UOM" name="base_uom_id" :options="['' => 'Select UOM'] + $uoms->pluck('name', 'id')->toArray()" selected="{{ old('base_uom_id', $bom->base_uom_id) }}" data-select2-selector="default" required />
                        </div>
                        <div class="col-md-2">
                            <x-ui.input label="BOM Version ID" name="version" placeholder="e.g. 1.0.0" value="{{ old('version', $bom->version) }}" required />
                        </div>
                        <div class="col-md-4">
                            <x-ui.select label="Routing Reference" name="routing_id" :options="['' => 'No Routing Reference / Standalone'] + $routings->pluck('name', 'id')->toArray()" selected="{{ old('routing_id', $bom->routing_id) }}" data-select2-selector="default" />
                        </div>

                        <div class="col-md-3">
                            <x-ui.input label="Effective Start Date" name="effective_date" type="date" value="{{ old('effective_date', $bom->effective_date ? $bom->effective_date->format('Y-m-d') : date('Y-m-d')) }}" required />
                        </div>
                        <div class="col-md-3">
                            <x-ui.input label="Effective Expiry Date" name="expiry_date" type="date" value="{{ old('expiry_date', $bom->expiry_date ? $bom->expiry_date->format('Y-m-d') : '') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Revision Reason" name="revision_reason" placeholder="e.g. Change in raw steel specifications" value="{{ old('revision_reason', $bom->revision_reason) }}" />
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark fs-12 text-uppercase mb-2">Recipe Description & Engineering Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Enter process description, instructions, or version change notes...">{{ old('notes', $bom->notes) }}</textarea>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <!-- Full Width: Components Dynamic Grid -->
            <div class="col-xl-12">
                <x-ui.card title="BOM Recipe Structure (Dynamic Components Grid)">
                    <x-ui.table bordered>
                        <thead class="table-light fs-11 text-uppercase text-dark">
                            <tr>
                                <th style="width: 5%">Seq</th>
                                <th style="width: 25%">Material Component</th>
                                <th style="width: 10%">Quantity</th>
                                <th style="width: 12%">UOM</th>
                                <th style="width: 10%">Scrap %</th>
                                <th style="width: 8%">Priority</th>
                                <th style="width: 18%">Validity (From - To)</th>
                                <th style="width: 12%">Alternative</th>
                                <th style="width: 5%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="item.uid">
                                <tr x-init="$nextTick(() => initRowSelects($el, item))">
                                    <!-- Sequence -->
                                    <td class="fw-bold text-center" x-text="index + 1"></td>
                                    
                                    <!-- Material Selection -->
                                    <td>
                                        <x-ui.select x-bind:name="'items['+index+'][material_id]'" class="fs-13" x-model="item.material_id" required data-select2-selector="default">
                                            <option value="">Select Material...</option>
                                            @foreach($materials as $material)
                                                <option value="{{ $material->id }}">{{ $material->name }} ({{ $material->sku }})</option>
                                            @endforeach
                                        </x-ui.select>
                                    </td>
                                    
                                    <!-- Quantity -->
                                    <td>
                                        <x-ui.input type="number" step="any" x-bind:name="'items['+index+'][quantity]'" class="text-end fs-13" x-model="item.quantity" placeholder="0.00" required min="0.0001" />
                                    </td>
                                    
                                    <!-- UOM -->
                                    <td>
                                        <x-ui.select x-bind:name="'items['+index+'][uom_id]'" class="fs-13" x-model="item.uom_id" required data-select2-selector="default">
                                            <option value="">Select UOM...</option>
                                            @foreach($uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->code }})</option>
                                            @endforeach
                                        </x-ui.select>
                                    </td>
                                    
                                    <!-- Material Scrap Percentage -->
                                    <td>
                                        <x-ui.input type="number" step="any" x-bind:name="'items['+index+'][material_scrap_percentage]'" class="text-end fs-13 text-danger" x-model="item.material_scrap_percentage" placeholder="0.00" min="0" max="100" />
                                    </td>

                                    <!-- Priority -->
                                    <td>
                                        <x-ui.input type="number" x-bind:name="'items['+index+'][priority]'" class="text-end fs-13" x-model="item.priority" placeholder="1" min="1" />
                                    </td>

                                    <!-- Validity limits (effective_from, effective_to) -->
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <x-ui.input type="date" x-bind:name="'items['+index+'][effective_from]'" class="form-control-sm fs-11" x-model="item.effective_from" />
                                            <x-ui.input type="date" x-bind:name="'items['+index+'][effective_to]'" class="form-control-sm fs-11" x-model="item.effective_to" />
                                        </div>
                                    </td>
                                    
                                    <!-- Alternative material group options -->
                                    <td>
                                        <div class="d-flex flex-column gap-2">
                                            <div>
                                                <!-- Hidden input to guarantee is_alternative is sent as 0 if unchecked -->
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
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Remove Row Action -->
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-soft-danger" @click="removeItem(index)">
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </x-ui.table>

                    <div class="mt-3">
                        <button type="button" class="btn btn-light-brand" @click="addItem()">
                            <i class="feather-plus me-2"></i>Add Component Row
                        </button>
                    </div>
                </x-ui.card>
            </div>

            <div class="col-xl-12 text-end">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="feather-save me-2"></i>Update Recipe
                </button>
            </div>
        </div>
    </form>

    @php
        $oldItems = old('items', $bom->items->map(fn($item) => [
            'material_id' => $item->material_id,
            'quantity' => $item->quantity,
            'uom_id' => $item->uom_id,
            'material_scrap_percentage' => $item->material_scrap_percentage,
            'is_alternative' => $item->is_alternative,
            'alternative_group' => $item->alternative_group ?? '',
            'priority' => $item->priority,
            'effective_from' => $item->effective_from ? $item->effective_from->format('Y-m-d') : '',
            'effective_to' => $item->effective_to ? $item->effective_to->format('Y-m-d') : ''
        ]));
    @endphp

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bomForm', () => ({
                items: @json($oldItems),

                init() {
                    // Make sure structure is consistent and has a unique key for tracking
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
                            dropdownParent: $(rowEl).closest('.table-responsive-container')
                        });
                        
                        // Sync select2 changes to Alpine.js
                        $select.on('change.select2', function () {
                            var val = $select.val();
                            var nameAttr = $select.attr('name') || '';
                            
                            if (nameAttr.indexOf('material_id') !== -1) {
                                item.material_id = val;
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
@endsection
