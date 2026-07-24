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
$uomOptions = [];
$vendorOptions = [];
try {
    if (class_exists(\App\Domains\Inventory\Models\Uom::class)) {
        $uomOptions = \App\Domains\Inventory\Models\Uom::all()->mapWithKeys(function($uom) {
            return [$uom->id => $uom->name . ' (' . $uom->code . ')'];
        })->toArray();
    }
    if (class_exists(\App\Domains\Inventory\Models\Vendor::class)) {
        $vendorOptions = \App\Domains\Inventory\Models\Vendor::where('status', 'active')->pluck('name', 'id')->toArray();
    }
} catch (\Exception $e) {}

$masterDefinitions = [
    'product' => [
        'label'  => 'Product',
        'route'  => 'products.quick-create',
        'fields' => [
            ['component' => 'input',  'props' => ['label' => 'Product Name',          'name' => 'name',      'placeholder' => 'e.g. Steel Sheet',    'required' => true]],
            ['component' => 'input',  'props' => ['label' => 'SKU / Item Code',        'name' => 'sku',       'placeholder' => 'e.g. RM-STEEL-01',    'required' => true]],
            ['component' => 'select', 'props' => ['label' => 'Product Type',           'name' => 'type',      'required' => true, 'selected' => 'semi_finished', 'options' => [
                'finished_good'  => 'Finished Good (Sales/Assembly)',
                'semi_finished'  => 'Semi Finished Product (Sub-Assembly)',
                'raw_material'   => 'Raw Material',
                'component'      => 'Component / Hardware',
                'service'        => 'Service',
            ]]],
            ['component' => 'select', 'props' => ['label' => 'Supplier Method / Source', 'name' => 'supplier_method', 'required' => true, 'selected' => 'buy', 'options' => [
                'buy'          => 'Buy (Purchase / Procurement)',
                'manufacture'  => 'Manufacture (Produce)',
            ]]],
            ['component' => 'select', 'props' => ['label' => 'Unit of Measure (UOM)',  'name' => 'uom_id',    'required' => true, 'options' => $uomOptions]],
            ['component' => 'select', 'props' => ['label' => 'Valuation Method',       'name' => 'inventory_valuation_method', 'required' => true, 'selected' => 'FIFO', 'options' => [
                'FIFO'              => 'FIFO (First-In, First-Out)',
                'Weighted Average'  => 'Weighted Average',
            ]]],
            ['component' => 'input',  'props' => ['label' => 'Standard Unit Cost',    'name' => 'unit_cost', 'type' => 'number', 'step' => 'any', 'placeholder' => '0.00', 'value' => '0.00']],
            ['component' => 'input',  'props' => ['label' => 'Selling Price',         'name' => 'selling_price', 'type' => 'number', 'step' => 'any', 'placeholder' => '0.00', 'value' => '0.00']],
            ['component' => 'select', 'props' => ['label' => 'Sales Account',          'name' => 'sales_account', 'required' => true, 'selected' => 'Sales Income', 'options' => [
                'Sales Income'    => 'Sales Income Account',
                'General Income'  => 'General Income Account',
                'Interest Income' => 'Interest Income Account',
            ]]],
            ['component' => 'select', 'props' => ['label' => 'Purchase Account',       'name' => 'purchase_account', 'required' => true, 'selected' => 'Cost of Goods Sold', 'options' => [
                'Cost of Goods Sold' => 'Cost of Goods Sold (COGS)',
                'Purchases'          => 'Purchases Expense Account',
                'Job Costs'          => 'Job Costs Expense Account',
            ]]],
            ['component' => 'select', 'props' => ['label' => 'Inventory Account',      'name' => 'inventory_account', 'required' => true, 'selected' => 'Inventory Asset', 'options' => [
                'Inventory Asset'      => 'Inventory Asset Account',
                'Raw Materials Stock'  => 'Raw Materials Stock',
                'Finished Goods Stock' => 'Finished Goods Stock',
            ]]],
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
            ['component' => 'input',    'props' => ['label' => 'Warehouse Name', 'name' => 'name', 'placeholder' => 'e.g. Main Store', 'required' => true]],
            ['component' => 'input',    'props' => ['label' => 'Location Code',  'name' => 'code', 'placeholder' => 'e.g. MAIN-WH-01', 'required' => true]],
            ['component' => 'textarea', 'props' => ['label' => 'Address Details', 'name' => 'address', 'placeholder' => 'e.g. 123 Industrial Area, Block B', 'rows' => 2]],
        ],
    ],
];
@endphp

@foreach($masters as $masterKey)
    @if(isset($masterDefinitions[$masterKey]))
        @php $def = $masterDefinitions[$masterKey]; @endphp
        @if(\Illuminate\Support\Facades\Route::has($def['route']))
        <x-ui.modal id="quickCreateModal_{{ $masterKey }}" title="Quick Create {{ $def['label'] }}" size="{{ $masterKey === 'product' ? 'lg' : '' }}">
            @if($masterKey === 'product')
                <!-- Handcrafted Premium Product Modal layout matching Inventory Create screen section headers -->
                <div data-action="{{ route('products.quick-create') }}"
                     class="quick-create-form"
                     id="quickCreateForm_product">
                    @csrf
                    <div class="row g-4 text-dark fs-13">
                        <!-- Column 1: Primary Details -->
                        <div class="col-md-6 border-end-md">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-1.5"></i>Primary Details</h6>
                            
                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                label="Product Name"
                                name="name"
                                placeholder="e.g. Steel Sheet"
                                :required="true"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                label="SKU / Item Code"
                                name="sku"
                                placeholder="e.g. RM-STEEL-01"
                                :required="true"
                            />

                            <x-ui.odoo-form-ui
                                type="select"
                                label="Product Type"
                                name="type"
                                :required="true"
                            >
                                <option value="finished_good">Finished Good (Sales/Assembly)</option>
                                <option value="semi_finished" selected>Semi Finished Product (Sub-Assembly)</option>
                                <option value="raw_material">Raw Material</option>
                                <option value="component">Component / Hardware</option>
                                <option value="service">Service</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                label="Source Method"
                                name="supplier_method"
                                :required="true"
                            >
                                <option value="buy" selected>Buy (Purchase / Procurement)</option>
                                <option value="manufacture">Manufacture (Produce)</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                label="Unit (UOM)"
                                name="uom_id"
                                :required="true"
                            >
                                @foreach($uomOptions as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                label="Pref. Vendor"
                                name="preferred_vendor_id"
                            >
                                <option value="">Select Preferred Supplier...</option>
                                @foreach($vendorOptions as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Column 2: Pricing & Accounts -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-dollar-sign me-1.5"></i>Pricing &amp; Accounts</h6>

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="number"
                                step="0.01"
                                label="Cost Price"
                                name="unit_cost"
                                value="0.00"
                                placeholder="0.00"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="number"
                                step="0.01"
                                label="Selling Price"
                                name="selling_price"
                                value="0.00"
                                placeholder="0.00"
                            />

                            <x-ui.odoo-form-ui
                                type="select"
                                label="Valuation"
                                name="inventory_valuation_method"
                                :required="true"
                            >
                                <option value="FIFO" selected>FIFO (First-In, First-Out)</option>
                                <option value="Weighted Average">Weighted Average</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                label="Sales Account"
                                name="sales_account"
                                :required="true"
                            >
                                <option value="Sales Income" selected>Sales Income Account</option>
                                <option value="General Income">General Income Account</option>
                                <option value="Interest Income">Interest Income Account</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                label="Purchase Acc."
                                name="purchase_account"
                                :required="true"
                            >
                                <option value="Cost of Goods Sold" selected>Cost of Goods Sold (COGS)</option>
                                <option value="Purchases">Purchases Expense Account</option>
                                <option value="Job Costs">Job Costs Expense Account</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                label="Inventory Acc."
                                name="inventory_account"
                                :required="true"
                            >
                                <option value="Inventory Asset" selected>Inventory Asset Account</option>
                                <option value="Raw Materials Stock">Raw Materials Stock</option>
                                <option value="Finished Goods Stock">Finished Goods Stock</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            @else
                <!-- Generic layout for other masters -->
                <div data-action="{{ route($def['route']) }}"
                     class="quick-create-form"
                     id="quickCreateForm_{{ $masterKey }}">
                    @csrf
                    <div class="row g-3">
                        @foreach($def['fields'] as $field)
                            <div class="col-12">
                                @if($field['component'] === 'input')
                                    <x-ui.odoo-form-ui
                                        type="input"
                                        :inputType="$field['props']['type'] ?? 'text'"
                                        :label="$field['props']['label']"
                                        :name="$field['props']['name']"
                                        :placeholder="$field['props']['placeholder'] ?? ''"
                                        :value="$field['props']['value'] ?? ''"
                                        step="{{ $field['props']['step'] ?? '' }}"
                                        :required="!empty($field['props']['required'])"
                                    />
                                @elseif($field['component'] === 'select')
                                    <x-ui.odoo-form-ui
                                        type="select"
                                        :label="$field['props']['label']"
                                        :name="$field['props']['name']"
                                        :required="!empty($field['props']['required'])"
                                    >
                                        @foreach($field['props']['options'] ?? [] as $val => $lbl)
                                            <option value="{{ $val }}" @selected((string)$val === (string)($field['props']['selected'] ?? ''))>{{ $lbl }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                @elseif($field['component'] === 'textarea')
                                    <x-ui.odoo-form-ui
                                        type="textarea"
                                        :label="$field['props']['label']"
                                        :name="$field['props']['name']"
                                        :placeholder="$field['props']['placeholder'] ?? ''"
                                        :rows="$field['props']['rows'] ?? 3"
                                        :required="!empty($field['props']['required'])"
                                    >{{ $field['props']['value'] ?? '' }}</x-ui.odoo-form-ui>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
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
