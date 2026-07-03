@extends('layouts.duralux')

@section('title', 'Create Routing | SaaS ERP')
@section('page-title', 'Create Process Routing')
@section('breadcrumb', 'Create Routing')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
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

        <form method="POST" action="{{ route('production.routing.store') }}" x-data="routingForm()">
            @csrf
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">New Process Routing</h4>
                    <a href="{{ route('production.routing.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                </div>

                <!-- Routing General Header -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" label="Routing Number" name="routing_number" placeholder="e.g. RTG-2026-001 or AUTO" :value="old('routing_number', 'AUTO')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Routing Name" name="name" placeholder="e.g. Standard Cabinet Assembly Process" :value="old('name')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" label="Target Product" name="product_id" :required="true">
                            <option value="">Select Product</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id', $selectedProductId ?? '') == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" label="Version ID" name="version" placeholder="e.g. 1.0.0" :value="old('version', '1.0.0')" :required="true" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Start Date" name="effective_from" inputType="date" :value="old('effective_from', date('Y-m-d'))" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Expiry Date" name="effective_to" inputType="date" :value="old('effective_to')" />
                        
                        <x-ui.odoo-form-ui type="select" label="Routing Class" name="is_default" :required="true">
                            <option value="1" @selected(old('is_default', '1') == '1')>Primary / Standard Routing</option>
                            <option value="0" @selected(old('is_default') == '0')>Alternative / Backup Routing</option>
                        </x-ui.odoo-form-ui>

                        <div class="odoo-form-group">
                            <label class="odoo-form-label">Sequence Control</label>
                            <div class="flex-grow-1">
                                <div class="form-check form-switch pt-1">
                                    <input class="form-check-input" type="checkbox" id="auto_sequence" x-model="autoSequence" checked>
                                    <label class="form-check-label fw-semibold text-dark fs-12 ms-2" for="auto_sequence">Auto-Manage Sequence Numbers</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 border-top pt-3">
                        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Enter high-level description, manufacturing objectives..." rows="3">{{ old('description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Operations Dynamic Grid -->
                <div class="border-top pt-4 mb-4">
                    <h5 class="fw-bold text-dark mb-3 fs-14">Routing Operations Sequence Grid</h5>
                    
                    <div class="table-responsive mb-3">
                        <x-ui.odoo-form-ui type="table">
                            <thead>
                                <tr>
                                    <th style="width: 8%" class="text-center">Seq</th>
                                    <th style="width: 25%">Operation Name &amp; Yield</th>
                                    <th style="width: 14%">Type</th>
                                    <th style="width: 18%">Work Center</th>
                                    <th style="width: 18%">Machine</th>
                                    <th style="width: 8%" class="text-end">Setup (m)</th>
                                    <th style="width: 8%" class="text-end">Run (m)</th>
                                    <th style="width: 5%" class="text-center">QC?</th>
                                    <th style="width: 8%" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(operation, index) in operations" :key="operation.uid">
                                    <tr>
                                        <!-- Sequence -->
                                        <td class="align-middle">
                                            <input type="number" x-bind:name="'operations['+index+'][sequence]'" class="odoo-table-input text-center font-monospace" x-model="operation.sequence" x-bind:readonly="autoSequence" required min="1" />
                                        </td>
                                        
                                        <!-- Operation Name & Details -->
                                        <td class="align-middle">
                                            <input type="text" x-bind:name="'operations['+index+'][name]'" class="odoo-table-input" placeholder="e.g. Drilling pilot holes" x-model="operation.name" required />
                                            
                                            <div class="d-flex flex-wrap gap-3 mt-1.5 align-items-center">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" x-model="operation.is_external" x-bind:name="'operations['+index+'][is_external]'" x-bind:id="'ext_' + operation.uid" value="1">
                                                    <label class="form-check-label fs-11 text-muted ms-1" x-bind:for="'ext_' + operation.uid">External/Outsourced</label>
                                                </div>
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <span class="fs-10 text-muted">Yield %:</span>
                                                    <input type="number" step="any" x-bind:name="'operations['+index+'][expected_yield_percentage]'" class="odoo-table-input text-center" style="width: 60px; padding: 2px !important;" x-model="operation.expected_yield_percentage" min="0.01" max="100.00" required />
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Operation Type -->
                                        <td class="align-middle">
                                            <select x-bind:name="'operations['+index+'][operation_type]'" class="odoo-table-select" x-model="operation.operation_type" required>
                                                @foreach ($operationTypes as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        
                                        <!-- Work Center -->
                                        <td class="align-middle">
                                            <select x-bind:name="'operations['+index+'][work_center_id]'" class="odoo-table-select" x-model="operation.work_center_id" x-on:change="workCenterChanged(index)" required>
                                                <option value="">Select Center...</option>
                                                @foreach ($workCenters as $wc)
                                                    <option value="{{ $wc->id }}">{{ $wc->name }} ({{ $wc->code }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        
                                        <!-- Machine -->
                                        <td class="align-middle">
                                            <select x-bind:name="'operations['+index+'][machine_id]'" class="odoo-table-select" x-model="operation.machine_id" x-bind:disabled="!operation.work_center_id">
                                                <option value="">No Specific Machine</option>
                                                <template x-for="m in operation.availableMachines" :key="m.id">
                                                    <option :value="m.id" x-text="m.label" :selected="m.id == operation.machine_id"></option>
                                                </template>
                                            </select>
                                        </td>
                                        
                                        <!-- Setup Time -->
                                        <td class="align-middle">
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][setup_time_minutes]'" class="odoo-table-input text-end" x-model="operation.setup_time_minutes" min="0" required />
                                        </td>
                                        
                                        <!-- Process Time -->
                                        <td class="align-middle">
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][processing_time_minutes]'" class="odoo-table-input text-end" x-model="operation.processing_time_minutes" min="0" required />
                                        </td>
                                        
                                        <!-- Quality Required -->
                                        <td class="text-center align-middle">
                                            <input type="checkbox" class="form-check-input" x-model="operation.quality_required" x-bind:name="'operations['+index+'][quality_required]'" value="1" />
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="text-end align-middle">
                                            <div class="d-inline-flex gap-1">
                                                <x-ui.icon-btn variant="light" size="sm" icon="feather-arrow-up" x-on:click="moveUp(index)" x-bind:disabled="index === 0" title="Move Up" />
                                                <x-ui.icon-btn variant="light" size="sm" icon="feather-arrow-down" x-on:click="moveDown(index)" x-bind:disabled="index === operations.length - 1" title="Move Down" />
                                                <x-ui.icon-btn variant="light" size="sm" icon="feather-copy" class="text-primary" x-on:click="duplicateOperation(index)" title="Duplicate Operation" />
                                                <x-ui.icon-btn variant="light" size="sm" icon="feather-trash-2" class="text-danger" x-on:click="removeOperation(index)" title="Remove Operation" />
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <button type="button" class="btn btn-light-brand" x-on:click="addOperation()">
                            <i class="feather-plus me-2"></i>Add Operation Stage
                        </button>
                        
                        <div class="text-muted fs-12 fw-medium">
                            Total Operations: <span class="fw-bold text-dark" x-text="operations.length"></span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">Save Routing Draft</button>
                    <a href="{{ route('production.routing.index') }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>

    <script>
        function routingForm() {
            return {
                operations: [],
                autoSequence: true,
                uidCounter: 1,

                init() {
                    // Add initial operation row
                    this.addOperation();

                    // Watch autoSequence changes to keep sequences updated
                    this.$watch('autoSequence', value => {
                        if (value) {
                            this.recalculateSequences();
                        }
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
                        work_center_id: '',
                        machine_id: '',
                        setup_time_minutes: '0.00',
                        processing_time_minutes: '0.00',
                        expected_yield_percentage: '100.00',
                        quality_required: false,
                        is_external: false,
                        availableMachines: []
                    });
                },

                removeOperation(index) {
                    if (this.operations.length > 1) {
                        this.operations.splice(index, 1);
                        this.recalculateSequences();
                    } else {
                        alert('A process routing requires at least one operation stage.');
                    }
                },

                duplicateOperation(index) {
                    const original = this.operations[index];
                    const clone = JSON.parse(JSON.stringify(original));
                    
                    clone.uid = this.uidCounter++;
                    clone.sequence = parseInt(original.sequence) + 5; // insert slightly after
                    
                    // Insert clone after original
                    this.operations.splice(index + 1, 0, clone);
                    this.recalculateSequences();
                    
                    // If cloned row had a work center, reload its machines
                    if (clone.work_center_id) {
                        this.loadMachinesForOperation(index + 1);
                    }
                },

                moveUp(index) {
                    if (index > 0) {
                        const temp = this.operations[index];
                        this.operations[index] = this.operations[index - 1];
                        this.operations[index - 1] = temp;
                        this.recalculateSequences();
                    }
                },

                moveDown(index) {
                    if (index < this.operations.length - 1) {
                        const temp = this.operations[index];
                        this.operations[index] = this.operations[index + 1];
                        this.operations[index + 1] = temp;
                        this.recalculateSequences();
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
                },

                loadMachinesForOperation(index) {
                    const op = this.operations[index];
                    fetch(`/production/machines/by-work-center/${op.work_center_id}`)
                        .then(res => res.json())
                        .then(data => {
                            op.availableMachines = data;
                        })
                        .catch(err => console.error('Failed to load machines:', err));
                }
            }
        }
    </script>
@endsection
