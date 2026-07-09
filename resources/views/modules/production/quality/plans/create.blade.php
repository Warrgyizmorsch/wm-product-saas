@extends('layouts.duralux')

@section('title', 'Configure Quality Plan | SaaS ERP')
@section('page-title', 'Configure Quality Control Plan')
@section('breadcrumb', 'Configure Plan')

@section('content')
    <div class="erp-single-panel bg-white" x-data="qualityPlanForm()">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <form method="POST" action="{{ route('production.quality-plans.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Configure New Quality Plan</h4>
                    <a href="{{ route('production.quality-plans.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- Form Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Plan Name" name="name" placeholder="e.g. Standard Final Quality Checklist" :value="old('name')" :required="true" :error-text="$errors->first('name')" />
                        <x-ui.odoo-form-ui type="input" label="Version" name="version" placeholder="e.g. 1.0" :value="old('version', '1.0')" :required="true" :error-text="$errors->first('version')" />
                        
                        <x-ui.odoo-form-ui type="select" label="Scope Type" name="type" id="type" x-model="scopeType" :required="true" :error-text="$errors->first('type')">
                            <option value="product">Product Specific</option>
                            <option value="work_center">Work Center Specific</option>
                            <option value="process">Process General</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="col-md-6">
                        <!-- Product Selection -->
                        <div x-show="scopeType === 'product'" x-transition class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Target Product" name="product_id" id="product_id" :error-text="$errors->first('product_id')">
                                <option value="">Select Product...</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Work Center Selection -->
                        <div x-show="scopeType === 'work_center'" x-transition class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Target Work Center" name="work_center_id" id="work_center_id" :error-text="$errors->first('work_center_id')">
                                <option value="">Select Work Center...</option>
                                @foreach($workCenters as $wc)
                                    <option value="{{ $wc->id }}">{{ $wc->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <x-ui.odoo-form-ui type="select" label="Approval Status" name="status" id="status" :error-text="$errors->first('status')">
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                            <option value="approved">Approved & Active</option>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Parameters Section -->
                <div class="border-top pt-4">
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">Checklist Parameters</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" @click="addParameter()">
                            <i class="feather-plus me-1"></i>Add Rule
                        </button>
                    </div>

                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table">
                            <thead>
                                <tr>
                                    <th style="width: 25%">Parameter Name</th>
                                    <th style="width: 15%">Type</th>
                                    <th style="width: 12%">Min Value</th>
                                    <th style="width: 12%">Max Value</th>
                                    <th style="width: 12%">Unit</th>
                                    <th style="width: 12%">Mandatory</th>
                                    <th class="text-end" style="width: 12%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(param, index) in parameters" :key="index">
                                    <tr>
                                        <td>
                                            <input type="text" :name="`parameters[${index}][name]`" x-model="param.name" class="form-control form-control-sm" placeholder="e.g. Thickness Check" required />
                                        </td>
                                        <td>
                                            <select :name="`parameters[${index}][type]`" x-model="param.type" class="form-select form-select-sm">
                                                <option value="numeric">Numeric Value</option>
                                                <option value="pass_fail">Pass / Fail</option>
                                                <option value="text">Custom Text Notes</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="any" :name="`parameters[${index}][min_value]`" x-model="param.min_value" class="form-control form-control-sm" placeholder="Min" :disabled="param.type !== 'numeric'" />
                                        </td>
                                        <td>
                                            <input type="number" step="any" :name="`parameters[${index}][max_value]`" x-model="param.max_value" class="form-control form-control-sm" placeholder="Max" :disabled="param.type !== 'numeric'" />
                                        </td>
                                        <td>
                                            <input type="text" :name="`parameters[${index}][unit_of_measure]`" x-model="param.unit_of_measure" class="form-control form-control-sm" placeholder="e.g. mm, kg" :disabled="param.type !== 'numeric'" />
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" :name="`parameters[${index}][is_mandatory]`" value="1" x-model="param.is_mandatory" class="form-check-input" />
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeParameter(index)">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.quality-plans.index') }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Quality Plan</button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        function qualityPlanForm() {
            return {
                scopeType: 'product',
                parameters: [
                    { name: '', type: 'numeric', min_value: '', max_value: '', unit_of_measure: '', is_mandatory: true }
                ],
                init() {
                    // Initialize select2 or other components if necessary
                },
                addParameter() {
                    this.parameters.push({
                        name: '',
                        type: 'numeric',
                        min_value: '',
                        max_value: '',
                        unit_of_measure: '',
                        is_mandatory: true
                    });
                },
                removeParameter(index) {
                    if (this.parameters.length > 1) {
                        this.parameters.splice(index, 1);
                    } else {
                        alert('A quality control plan must contain at least one checklist parameter.');
                    }
                }
            }
        }
    </script>
@endpush
