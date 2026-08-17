@extends('layouts.duralux')

@section('title', __('production.edit_routing') . ' | SaaS ERP')
@section('page-title', __('production.edit_routing'))
@section('breadcrumb', __('production.edit_routing'))

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
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.item_to_produce')" name="product_id" :required="true">
                            <option value="">{{ __('production.select_product') }}</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id', $routing->product_id) == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.version')" name="version" :value="old('version', $routing->version)" :required="true" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.start_date')" name="effective_from" inputType="date" :value="old('effective_from', $routing->effective_from?->format('Y-m-d'))" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.expiry_date')" name="effective_to" inputType="date" :value="old('effective_to', $routing->effective_to?->format('Y-m-d'))" />
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.routing_type')" name="is_default" :required="true">
                            <option value="1" @selected(old('is_default', $routing->is_default ? '1' : '0') == '1')>{{ __('production.primary') }}</option>
                            <option value="0" @selected(old('is_default', $routing->is_default ? '1' : '0') == '0')>{{ __('production.alternative') }}</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="radio" :label="__('production.sequence_control')">
                            <div class="form-check me-3">
                                <input class="form-check-input" type="radio" name="auto_sequence_radio_edit" id="auto_seq_edit_auto" :value="true" x-model="autoSequence">
                                <label class="form-check-label fw-semibold text-dark fs-12 ms-1 mb-0 c-pointer" for="auto_seq_edit_auto">{{ __('production.auto_manage_sequence') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="auto_sequence_radio_edit" id="auto_seq_edit_manual" :value="false" x-model="autoSequence">
                                <label class="form-check-label fw-semibold text-dark fs-12 ms-1 mb-0 c-pointer" for="auto_seq_edit_manual">Manual Sequence</label>
                            </div>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="col-12 border-top pt-3">
                        <x-ui.odoo-form-ui type="textarea" :label="__('production.description')" name="description" placeholder="{{ __('production.description_placeholder') }}" rows="3">{{ old('description', $routing->description) }}</x-ui.odoo-form-ui>
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
                                    <th style="min-width: 310px;">{{ __('production.operation_name_yield') }}</th>
                                    <th style="width: 125px;">{{ __('production.type') }}</th>
                                    <th style="width: 165px;">{{ __('production.work_center') }}</th>
                                    <th style="width: 165px;">{{ __('production.machine') }}</th>
                                    <th style="width: 65px;" class="text-end">{{ __('production.setup') }} (m)</th>
                                    <th style="width: 65px;" class="text-end">{{ __('production.run') }} (m)</th>
                                    <th style="width: 40px;" class="text-center">{{ __('production.qc_gate') }}</th>
                                    <th style="width: 110px;" class="text-end">{{ __('production.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                 <template x-for="(operation, index) in operations" :key="operation.uid">
                                     <tr>
                                         <!-- Sequence -->
                                         <td class="align-middle text-center">
                                             <input type="number" x-bind:name="'operations['+index+'][sequence]'" class="odoo-table-input text-center font-monospace" x-model="operation.sequence" x-bind:readonly="autoSequence" required min="1" />
                                         </td>
                                         
                                         <!-- Operation Name & Details -->
                                         <td class="align-middle">
                                             <input type="text" x-bind:name="'operations['+index+'][name]'" class="odoo-table-input fw-semibold" placeholder="{{ __('production.operation_placeholder') }}" x-model="operation.name" required />
                                             
                                             <div class="d-flex align-items-center gap-2 mt-1 fs-11 text-muted text-nowrap flex-nowrap overflow-hidden">
                                                 <div class="form-check m-0 p-0 d-inline-flex align-items-center me-1">
                                                     <input type="checkbox" class="form-check-input mt-0 me-1 ms-0" x-model="operation.is_external" x-bind:name="'operations['+index+'][is_external]'" x-bind:id="'ext_edit_' + operation.uid" value="1">
                                                     <label class="form-check-label fs-11 text-secondary c-pointer mb-0" x-bind:for="'ext_edit_' + operation.uid">{{ __('production.outsourced') }}</label>
                                                 </div>

                                                 <span class="text-black-50 me-1">|</span>

                                                 <div class="d-inline-flex align-items-center gap-1 me-1">
                                                     <span class="fs-10 text-muted">{{ __('production.yield') }}%:</span>
                                                     <input type="number" step="any" x-bind:name="'operations['+index+'][expected_yield_percentage]'" class="odoo-table-input text-center py-0 px-1 fs-11" style="width: 45px; height: 20px; min-height: 20px;" x-model="operation.expected_yield_percentage" min="0.01" max="100.00" required />
                                                 </div>

                                                 <span class="text-black-50 me-1">|</span>

                                                 <div class="form-check m-0 p-0 d-inline-flex align-items-center me-1">
                                                     <input type="checkbox" class="form-check-input mt-0 me-1 ms-0" x-model="operation.overlap_enabled" x-bind:name="'operations['+index+'][overlap_enabled]'" x-bind:id="'ovl_edit_' + operation.uid" value="1">
                                                     <label class="form-check-label fs-11 text-info fw-semibold c-pointer mb-0" x-bind:for="'ovl_edit_' + operation.uid">Overlap</label>
                                                 </div>

                                                 <div class="align-items-center gap-1"
                                                      :class="(operation.overlap_enabled == 1 || operation.overlap_enabled === true) ? 'd-inline-flex' : 'd-none'"
                                                      x-show="operation.overlap_enabled == 1 || operation.overlap_enabled === true"
                                                      x-transition:enter="transition ease-out duration-300"
                                                      x-transition:enter-start="opacity-0 transform -translate-x-2"
                                                      x-transition:enter-end="opacity-100 transform translate-x-0"
                                                      x-transition:leave="transition ease-in duration-200"
                                                      x-transition:leave-start="opacity-100 transform translate-x-0"
                                                      x-transition:leave-end="opacity-0 transform -translate-x-2">
                                                     <span class="fs-10 text-muted">Qty:</span>
                                                     <input type="number" step="any" x-bind:name="'operations['+index+'][transfer_batch_quantity]'" class="odoo-table-input text-center py-0 px-1 fs-11" style="width: 45px; height: 20px; min-height: 20px;" x-model="operation.transfer_batch_quantity" min="0.0001" />
                                                 </div>
                                             </div>
                                         </td>
                                         
                                         <!-- Operation Type -->
                                         <td class="align-middle">
                                             <x-ui.odoo-form-ui type="select" x-bind:name="'operations['+index+'][operation_type]'" class="odoo-table-select" x-model="operation.operation_type" required select2Selector="default">
                                                 @foreach ($operationTypes as $val => $label)
                                                     <option value="{{ $val }}">{{ __('production.op_type_' . $val) }}</option>
                                                 @endforeach
                                             </x-ui.odoo-form-ui>
                                         </td>
                                         
                                         <!-- Work Center -->
                                         <td class="align-middle">
                                             <x-ui.odoo-form-ui type="select" x-bind:name="'operations['+index+'][work_center_id]'" class="odoo-table-select" x-model="operation.work_center_id" x-on:change="workCenterChanged(index)" required select2Selector="default">
                                                 <option value="">{{ __('production.select_work_center') }}</option>
                                                 @foreach ($workCenters as $wc)
                                                     <option value="{{ $wc->id }}">{{ $wc->name }} ({{ $wc->code }})</option>
                                                 @endforeach
                                             </x-ui.odoo-form-ui>
                                         </td>
                                         
                                         <!-- Machine -->
                                         <td class="align-middle">
                                             <x-ui.odoo-form-ui type="select" x-bind:name="'operations['+index+'][machine_id]'" class="odoo-table-select" x-model="operation.machine_id" x-bind:disabled="!operation.work_center_id" select2Selector="default">
                                                 <option value="">{{ __('production.no_specific_machine') }}</option>
                                                 <template x-for="m in operation.availableMachines" :key="m.id">
                                                     <option :value="m.id" x-text="m.label" :selected="m.id == operation.machine_id"></option>
                                                 </template>
                                             </x-ui.odoo-form-ui>
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
                                 </template>
                            </tbody>
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
                    <button type="submit" class="btn btn-primary px-4">{{ __('production.update_routing') }}</button>
                </div>
            </x-ui.odoo-form-ui>
            </form>

            <x-ui.confirmation-modal />
        </div>

        <script>
            function routingForm() {
                return {
                    operations: [],
                    autoSequence: {{ $routing->auto_sequence ? 'true' : 'false' }},
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
                                overlap_enabled: {{ $op->overlap_enabled ? 'true' : 'false' }},
                                transfer_batch_quantity: "{{ number_format($op->transfer_batch_quantity ?? 0, 2, '.', '') }}",
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
                            overlap_enabled: false,
                            transfer_batch_quantity: '0.00',
                            availableMachines: []
                        });
                    },

                    removeOperation(index) {
                        if (this.operations.length > 1) {
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
                        fetch(`{{ url('production/machines/by-work-center') }}/${op.work_center_id}`)
                            .then(res => res.json())
                            .then(data => {
                                op.availableMachines = data;
                                this.$nextTick(() => this.initAllOperationSelects());
                            })
                            .catch(err => console.error('Failed to load machines:', err));
                    },
                }
            }
        </script>
@endsection