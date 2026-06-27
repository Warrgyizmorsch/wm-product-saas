@props([
    'masters' => [] // array of master keys e.g. ['product', 'uom']
])

{{--
    x-ui.master-modals
    ==================
    Global quick-create modal registry. Render this ONCE per page,
    passing an array of master keys. Each master must have a registered
    definition in the $masterDefinitions map below.

    Usage:
        <x-ui.master-modals :masters="['product', 'uom']" />

    Then any <x-ui.select master="product" /> on the page will wire to
    the correct modal automatically via JS (no duplicate modals needed).
--}}

@php
$masterDefinitions = [
    'product' => [
        'label'  => 'Product',
        'route'  => 'products.quick-create',
        'fields' => [
            ['component' => 'input',  'props' => ['label' => 'Product Name',          'name' => 'name',      'placeholder' => 'e.g. Steel Sheet',    'required' => true]],
            ['component' => 'input',  'props' => ['label' => 'SKU / Item Code',        'name' => 'sku',       'placeholder' => 'e.g. RM-STEEL-01',    'required' => true]],
            ['component' => 'select', 'props' => ['label' => 'Product Type',           'name' => 'type',      'required' => true, 'selected' => 'semi_finished', 'options' => [
                'finished_good'  => 'Finished Good (Standard Sales/Assembly)',
                'semi_finished'  => 'Semi Finished Product (Sub-Assembly)',
                'raw_material'   => 'Raw Material',
                'component'      => 'Component / Hardware',
            ]]],
            ['component' => 'input',  'props' => ['label' => 'Standard Unit Cost',    'name' => 'unit_cost', 'type' => 'number', 'step' => 'any', 'placeholder' => '0.00', 'value' => '0.00']],
        ],
    ],
    'uom' => [
        'label'  => 'UOM',
        'route'  => 'uoms.quick-create',
        'fields' => [
            ['component' => 'input',  'props' => ['label' => 'UOM Name',               'name' => 'name', 'placeholder' => 'e.g. Pieces',  'required' => true]],
            ['component' => 'input',  'props' => ['label' => 'UOM Code / Abbreviation','name' => 'code', 'placeholder' => 'e.g. PCS',     'required' => true]],
        ],
    ],
    'routing' => [
        'label'  => 'Routing',
        'route'  => 'routings.quick-create',
        'fields' => [
            ['component' => 'input',  'props' => ['label' => 'Routing Name', 'name' => 'name', 'placeholder' => 'e.g. Standard Assembly Line', 'required' => true]],
            ['component' => 'select', 'props' => ['label' => 'Status',       'name' => 'status', 'required' => true, 'selected' => 'active', 'options' => [
                'active'   => 'Active',
                'inactive' => 'Inactive',
            ]]],
        ],
    ],
    'customer' => [
        'label'  => 'Customer',
        'route'  => 'customers.quick-create',
        'fields' => [
            ['component' => 'input', 'props' => ['label' => 'Customer Name',  'name' => 'name',  'placeholder' => 'e.g. Acme Corp',          'required' => true]],
            ['component' => 'input', 'props' => ['label' => 'Email Address',  'name' => 'email', 'placeholder' => 'e.g. contact@acme.com',   'type' => 'email']],
            ['component' => 'input', 'props' => ['label' => 'Phone Number',   'name' => 'phone', 'placeholder' => 'e.g. +91-9876543210']],
        ],
    ],
    'supplier' => [
        'label'  => 'Supplier',
        'route'  => 'suppliers.quick-create',
        'fields' => [
            ['component' => 'input', 'props' => ['label' => 'Supplier Name',  'name' => 'name',  'placeholder' => 'e.g. Steel Works Ltd',    'required' => true]],
            ['component' => 'input', 'props' => ['label' => 'Email Address',  'name' => 'email', 'placeholder' => 'e.g. sales@steelworks.com', 'type' => 'email']],
            ['component' => 'input', 'props' => ['label' => 'Phone Number',   'name' => 'phone', 'placeholder' => 'e.g. +91-9876543210']],
        ],
    ],
    'warehouse' => [
        'label'  => 'Warehouse',
        'route'  => 'warehouses.quick-create',
        'fields' => [
            ['component' => 'input', 'props' => ['label' => 'Warehouse Name', 'name' => 'name',     'placeholder' => 'e.g. Main Store',    'required' => true]],
            ['component' => 'input', 'props' => ['label' => 'Location Code',  'name' => 'location', 'placeholder' => 'e.g. MAIN-WH-01']],
        ],
    ],
];
@endphp

@foreach($masters as $masterKey)
    @if(isset($masterDefinitions[$masterKey]))
        @php $def = $masterDefinitions[$masterKey]; @endphp
        @if(\Illuminate\Support\Facades\Route::has($def['route']))
        <x-ui.modal id="quickCreateModal_{{ $masterKey }}" title="Quick Create {{ $def['label'] }}">
            <div data-action="{{ route($def['route']) }}"
                 class="quick-create-form"
                 id="quickCreateForm_{{ $masterKey }}">
                @csrf
                @foreach($def['fields'] as $field)
                    @if($field['component'] === 'input')
                        <x-ui.input
                            :label="$field['props']['label']"
                            :name="$field['props']['name']"
                            :placeholder="$field['props']['placeholder'] ?? ''"
                            :type="$field['props']['type'] ?? 'text'"
                            :value="$field['props']['value'] ?? ''"
                            :step="$field['props']['step'] ?? null"
                            :required="!empty($field['props']['required'])"
                        />
                    @elseif($field['component'] === 'select')
                        <x-ui.select
                            :label="$field['props']['label']"
                            :name="$field['props']['name']"
                            :options="$field['props']['options'] ?? []"
                            :selected="$field['props']['selected'] ?? null"
                            :required="!empty($field['props']['required'])"
                        />
                    @endif
                @endforeach
            </div>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
                <button type="button"
                        class="btn btn-primary btn-save-master"
                        data-form="quickCreateForm_{{ $masterKey }}">
                    Save {{ $def['label'] }}
                </button>
            </x-slot>
        </x-ui.modal>
        @endif
    @endif
@endforeach
