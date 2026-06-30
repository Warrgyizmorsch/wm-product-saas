@extends('layouts.duralux')

@section('title', 'Edit Routing | SaaS ERP')
@section('page-title', 'Edit Process Routing')
@section('breadcrumb', 'Edit Routing')

@section('page-actions')
    <a href="{{ route('production.routing.show', $routing->id) }}" class="btn btn-secondary">
        <i class="feather-arrow-left me-2"></i>Back to Details
    </a>
@endsection

@push('scripts')
    <!-- Load Alpine.js for dynamic operations grid management -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')
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

    <form method="POST" action="{{ route('production.routing.update', $routing->id) }}" x-data="routingForm()">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <!-- Header Information -->
            <div class="col-xl-12">
                <x-ui.card title="Routing General Header">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <x-ui.input label="Routing Document Number" name="routing_number" value="{{ $routing->routing_number }}" readonly required />
                        </div>
                        <div class="col-md-3">
                            <x-ui.input label="Routing Process Name" name="name" value="{{ old('name', $routing->name) }}" required />
                        </div>
                        <div class="col-md-4">
                            <x-ui.select label="Target Finished/Semi-Finished Product" name="product_id" :options="['' => 'Select Product'] + $products->pluck('name', 'id')->toArray()" selected="{{ old('product_id', $routing->product_id) }}" required />
                        </div>
                        <div class="col-md-2">
                            <x-ui.input label="Version ID" name="version" value="{{ old('version', $routing->version) }}" required />
                        </div>

                        <div class="col-md-3">
                            <x-ui.input label="Effective Start Date" name="effective_from" type="date" value="{{ old('effective_from', $routing->effective_from ? $routing->effective_from->format('Y-m-d') : '') }}" required />
                        </div>
                        <div class="col-md-3">
                            <x-ui.input label="Effective Expiry Date" name="effective_to" type="date" value="{{ old('effective_to', $routing->effective_to ? $routing->effective_to->format('Y-m-d') : '') }}" />
                        </div>
                        <div class="col-md-3">
                            <x-ui.select label="Routing Class Type" name="is_default" :options="[
                                '1' => 'Primary / Standard Routing',
                                '0' => 'Alternative / Backup Routing'
                            ]" selected="{{ old('is_default', $routing->is_default ? '1' : '0') }}" required />
                        </div>
                        <div class="col-md-3 d-flex align-items-center mt-4">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="auto_sequence" x-model="autoSequence">
                                <label class="form-check-label fw-semibold text-dark fs-12 ms-2" for="auto_sequence">Auto-Manage Sequence Numbers</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark fs-12 text-uppercase mb-2">Process Overview / Engineering Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Enter high-level description, manufacturing objectives, or routing change logs...">{{ old('description', $routing->description) }}</textarea>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <!-- Operations Dynamic Grid -->
            <div class="col-xl-12">
                <x-ui.card title="Routing Operations Sequence Grid">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light fs-11 text-uppercase text-dark">
                                <tr>
                                    <th style="width: 6%">Seq</th>
                                    <th style="width: 22%">Operation Name</th>
                                    <th style="width: 14%">Type</th>
                                    <th style="width: 18%">Work Center</th>
                                    <th style="width: 18%">Machine</th>
                                    <th style="width: 8%">Setup (Min)</th>
                                    <th style="width: 8%">Process (Min)</th>
                                    <th style="width: 6%" class="text-center">QC?</th>
                                    <th style="width: 10%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(operation, index) in operations" :key="operation.uid">
                                    <tr>
                                        <!-- Sequence -->
                                        <td>
                                            <input type="number" x-bind:name="'operations['+index+'][sequence]'" class="form-control text-center font-monospace" x-model="operation.sequence" x-bind:readonly="autoSequence" required min="1">
                                        </td>
                                        
                                        <!-- Operation Name -->
                                        <td>
                                            <input type="text" x-bind:name="'operations['+index+'][name]'" class="form-control" placeholder="e.g. Drilling pilot holes" x-model="operation.name" required>
                                            
                                            <!-- Subcontracting / External check box -->
                                            <div class="mt-2 d-flex align-items-center gap-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" x-model="operation.is_external" x-bind:name="'operations['+index+'][is_external]'" x-bind:id="'ext_' + operation.uid">
                                                    <label class="form-check-label fs-11 text-muted ms-1" x-bind:for="'ext_' + operation.uid">External/Outsourced Operation</label>
                                                </div>
                                            </div>
                                            
                                            <!-- Yield Percentage Input -->
                                            <div class="mt-2 row g-2 align-items-center">
                                                <div class="col-auto">
                                                    <span class="fs-11 text-muted">Expected Yield %:</span>
                                                </div>
                                                <div class="col-6">
                                                    <input type="number" step="any" x-bind:name="'operations['+index+'][expected_yield_percentage]'" class="form-control form-control-sm text-end" x-model="operation.expected_yield_percentage" min="0.01" max="100.00" required>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Operation Type -->
                                        <td>
                                            <select x-bind:name="'operations['+index+'][operation_type]'" class="form-select" x-model="operation.operation_type" required>
                                                @foreach ($operationTypes as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        
                                        <!-- Work Center -->
                                        <td>
                                            <select x-bind:name="'operations['+index+'][work_center_id]'" class="form-select" x-model="operation.work_center_id" x-on:change="workCenterChanged(index)" required>
                                                <option value="">Select Center...</option>
                                                @foreach ($workCenters as $wc)
                                                    <option value="{{ $wc->id }}">{{ $wc->name }} ({{ $wc->code }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        
                                        <!-- Machine -->
                                        <td>
                                            <select x-bind:name="'operations['+index+'][machine_id]'" class="form-select" x-model="operation.machine_id" x-bind:disabled="!operation.work_center_id">
                                                <option value="">No Specific Machine</option>
                                                <template x-for="m in operation.availableMachines" :key="m.id">
                                                    <option :value="m.id" x-text="m.label" :selected="m.id == operation.machine_id"></option>
                                                </template>
                                            </select>
                                        </td>
                                        
                                        <!-- Setup Time -->
                                        <td>
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][setup_time_minutes]'" class="form-control text-end" x-model="operation.setup_time_minutes" min="0" required>
                                        </td>
                                        
                                        <!-- Process Time -->
                                        <td>
                                            <input type="number" step="any" x-bind:name="'operations['+index+'][processing_time_minutes]'" class="form-control text-end" x-model="operation.processing_time_minutes" min="0" required>
                                        </td>
                                        
                                        <!-- Quality Required -->
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" x-model="operation.quality_required" x-bind:name="'operations['+index+'][quality_required]'" value="1">
                                            </div>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn btn-icon btn-light btn-sm" x-on:click="moveUp(index)" :disabled="index === 0" title="Move Up">
                                                    <i class="feather-arrow-up"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-light btn-sm" x-on:click="moveDown(index)" :disabled="index === operations.length - 1" title="Move Down">
                                                    <i class="feather-arrow-down"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-light btn-sm text-primary" x-on:click="duplicateOperation(index)" title="Duplicate Operation">
                                                    <i class="feather-copy"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-light btn-sm text-danger" x-on:click="removeOperation(index)" title="Remove Operation">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <button type="button" class="btn btn-soft-primary" x-on:click="addOperation()">
                            <i class="feather-plus me-2"></i>Add Operation Stage
                        </button>
                        
                        <div class="text-muted fs-12">
                            Total Operations: <span class="fw-bold text-dark" x-text="operations.length"></span>
                        </div>
                    </div>
                </x-ui.card>
            </div>
            
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="feather-save me-2"></i>Update Routing
                </button>
            </div>
        </div>
    </form>

    <script>
        function routingForm() {
            return {
                operations: [],
                autoSequence: true,
                uidCounter: 1,

                init() {
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
                            expected_yield_percentage: "{{ number_format($op->expected_yield_percentage, 2, '.', '') }}",
                            quality_required: {{ $op->quality_required ? 'true' : 'false' }},
                            is_external: {{ $op->is_external ? 'true' : 'false' }},
                            availableMachines: []
                        });
                        // Fetch machines for the preloaded work center
                        this.loadMachinesForOperation(this.operations.length - 1);
                    @endforeach

                    if (this.operations.length === 0) {
                        this.addOperation();
                    }

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
                    clone.sequence = parseInt(original.sequence) + 5;
                    
                    this.operations.splice(index + 1, 0, clone);
                    this.recalculateSequences();
                    
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
