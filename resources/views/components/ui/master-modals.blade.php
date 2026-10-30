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
$salesAccountOptions = [];
$purchaseAccountOptions = [];
$inventoryAccountOptions = [];

try {
    if (class_exists(\App\Domains\Inventory\Models\Uom::class)) {
        $uomOptions = \App\Domains\Inventory\Models\Uom::all()->mapWithKeys(function($uom) {
            return [$uom->id => $uom->name . ' (' . $uom->code . ')'];
        })->toArray();
    }
    if (class_exists(\App\Domains\Inventory\Models\Vendor::class)) {
        $vendorOptions = \App\Domains\Inventory\Models\Vendor::where('status', 'active')->pluck('name', 'id')->toArray();
    }
    if (class_exists(\App\Domains\Accounting\Models\ChartOfAccount::class)) {
        $tenantId = current_tenant_id() ?? tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $coas = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where(function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where('is_active', true)
            ->orderBy('code', 'asc')
            ->get();

        $incomeCoas = $coas->where('type', 'income');
        if ($incomeCoas->isEmpty()) {
            $incomeCoas = $coas;
        }
        foreach ($incomeCoas as $coa) {
            $label = ($coa->code ? $coa->code . ' - ' : '') . $coa->name;
            $salesAccountOptions[$coa->id] = $label;
        }

        $expenseCoas = $coas->where('type', 'expense');
        if ($expenseCoas->isEmpty()) {
            $expenseCoas = $coas;
        }
        foreach ($expenseCoas as $coa) {
            $label = ($coa->code ? $coa->code . ' - ' : '') . $coa->name;
            $purchaseAccountOptions[$coa->id] = $label;
        }

        $assetCoas = $coas->where('type', 'asset');
        if ($assetCoas->isEmpty()) {
            $assetCoas = $coas;
        }
        foreach ($assetCoas as $coa) {
            $label = ($coa->code ? $coa->code . ' - ' : '') . $coa->name;
            $inventoryAccountOptions[$coa->id] = $label;
        }
    }
} catch (\Exception $e) {}

if (empty($salesAccountOptions)) {
    $salesAccountOptions = [
        '4010' => '4010 - Sales Income Account',
        '4020' => '4020 - General Income Account',
        '4030' => '4030 - Interest Income Account',
    ];
}
if (empty($purchaseAccountOptions)) {
    $purchaseAccountOptions = [
        '5010' => '5010 - Cost of Goods Sold (COGS)',
        '5900' => '5900 - Purchases Expense Account',
        '5020' => '5020 - Job Costs Expense Account',
    ];
}
if (empty($inventoryAccountOptions)) {
    $inventoryAccountOptions = [
        '1200' => '1200 - Inventory Asset Account',
        '1210' => '1210 - Raw Materials Stock',
        '1220' => '1220 - Finished Goods Stock',
    ];
}

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
                'buy'          => 'Trade (Purchase / Procurement)',
                'manufacture'  => 'Manufacture (Produce)',
            ]]],
            ['component' => 'select', 'props' => ['label' => 'Unit of Measure (UOM)',  'name' => 'uom_id',    'required' => true, 'options' => $uomOptions]],
            ['component' => 'select', 'props' => ['label' => 'Valuation Method',       'name' => 'inventory_valuation_method', 'required' => true, 'selected' => 'FIFO', 'options' => [
                'FIFO'              => 'FIFO (First-In, First-Out)',
                'Weighted Average'  => 'Weighted Average',
            ]]],
            ['component' => 'input',  'props' => ['label' => 'Standard Unit Cost',    'name' => 'unit_cost', 'type' => 'number', 'step' => 'any', 'placeholder' => '0.00', 'value' => '0.00']],
            ['component' => 'input',  'props' => ['label' => 'Selling Price',         'name' => 'selling_price', 'type' => 'number', 'step' => 'any', 'placeholder' => '0.00', 'value' => '0.00']],
            ['component' => 'select', 'props' => ['label' => 'Sales Account',          'name' => 'sales_account', 'options' => $salesAccountOptions]],
            ['component' => 'select', 'props' => ['label' => 'Purchase Account',       'name' => 'purchase_account', 'options' => $purchaseAccountOptions]],
            ['component' => 'select', 'props' => ['label' => 'Inventory Account',      'name' => 'inventory_account', 'options' => $inventoryAccountOptions]],
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
        'route'  => 'crm.customers.quick-create',
        'fields' => [
            ['component' => 'input', 'props' => ['label' => 'Customer Name',  'name' => 'name',  'placeholder' => 'e.g. Acme Corp or Manish Patidar', 'required' => true]],
            ['component' => 'input', 'props' => ['label' => 'GSTIN',          'name' => 'gstin', 'placeholder' => 'e.g. 22AAAAA0000A1Z5']],
            ['component' => 'input', 'props' => ['label' => 'Email Address',  'name' => 'email', 'placeholder' => 'e.g. contact@acme.com',   'type' => 'email', 'required' => true]],
            ['component' => 'input', 'props' => ['label' => 'Phone Number',   'name' => 'phone', 'placeholder' => '02240001122', 'type' => 'tel']],
            ['component' => 'textarea', 'props' => ['label' => 'Billing Address',  'name' => 'billing_address', 'placeholder' => 'Billing address...', 'rows' => 2]],
            ['component' => 'textarea', 'props' => ['label' => 'Shipping Address', 'name' => 'shipping_address', 'placeholder' => 'Shipping address...', 'rows' => 2]],
        ],
    ],
    'supplier' => [
        'label'  => 'Supplier',
        'route'  => 'suppliers.quick-create',
        'fields' => [
            ['component' => 'input', 'props' => ['label' => 'Supplier Name',  'name' => 'name',  'placeholder' => 'e.g. Steel Works Ltd',    'required' => true]],
            ['component' => 'input', 'props' => ['label' => 'Email Address',  'name' => 'email', 'placeholder' => 'e.g. sales@steelworks.com', 'type' => 'email']],
            ['component' => 'input', 'props' => ['label' => 'Phone Number',   'name' => 'phone', 'placeholder' => '02240001122', 'type' => 'tel']],
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
    'contact' => [
        'label'  => 'Contact Person',
        'route'  => 'crm.contacts.quick-create',
        'fields' => [
            ['component' => 'input', 'props' => ['label' => 'Contact Name', 'name' => 'name', 'placeholder' => 'e.g. Prakash Sharma', 'required' => true]],
            ['component' => 'input', 'props' => ['label' => 'Designation', 'name' => 'designation', 'placeholder' => 'e.g. Purchase Manager']],
            ['component' => 'input', 'props' => ['label' => 'Mobile / Phone', 'name' => 'phone', 'placeholder' => '02240001122', 'type' => 'tel']],
            ['component' => 'input', 'props' => ['label' => 'Email Address', 'name' => 'email', 'placeholder' => 'e.g. prakash@company.com', 'type' => 'email']],
        ],
    ],
    'account' => [
        'label'  => 'Account / Customer',
        'route'  => 'crm.accounts.store',
        'fields' => [],
    ],
];
@endphp

@foreach($masters as $masterKey)
    @if(isset($masterDefinitions[$masterKey]))
        @php $def = $masterDefinitions[$masterKey]; @endphp
        @if($masterKey === 'contact' || $masterKey === 'account' || \Illuminate\Support\Facades\Route::has($def['route']))
        <x-ui.modal id="quickCreateModal_{{ $masterKey }}" :title="match($masterKey) {
            'account' => __('crm.create_new_account_title'),
            'customer' => __('crm.quick_create_customer'),
            'contact' => __('crm.quick_create') . ' ' . __('crm.contact_person'),
            'product' => __('crm.quick_create_product'),
            'warehouse' => __('crm.quick_create_warehouse'),
            'uom' => __('crm.quick_create_uom'),
            'routing' => __('crm.quick_create_routing'),
            default => __('crm.quick_create') . ' ' . $def['label']
        }" size="{{ in_array($masterKey, ['product', 'contact', 'customer', 'account']) ? 'lg' : '' }}">
            @if($masterKey === 'product')
                <!-- Handcrafted Premium Product Modal layout matching Inventory Create screen section headers -->
                <div data-action="{{ route('products.quick-create') }}"
                     class="quick-create-form"
                     id="quickCreateForm_product">
                    @csrf
                    <div class="row g-4 text-dark fs-13">
                        <!-- Column 1: Primary Details -->
                        <div class="col-md-6 border-end-md">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-1.5"></i>{{ __('crm.primary_details') }}</h6>
                            
                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.product_name')"
                                name="name"
                                placeholder="e.g. Steel Sheet"
                                :required="true"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.sku_item_code')"
                                name="sku"
                                placeholder="e.g. RM-STEEL-01"
                                :required="true"
                            />

                            <x-ui.odoo-form-ui
                                type="select"
                                :label="__('crm.product_type')"
                                name="type"
                                :required="true"
                            >
                                <option value="finished_good">{{ __('crm.finished_good_opt') }}</option>
                                <option value="semi_finished" selected>{{ __('crm.semi_finished_opt') }}</option>
                                <option value="raw_material">{{ __('crm.raw_material_opt') }}</option>
                                <option value="component">{{ __('crm.component_opt') }}</option>
                                <option value="service">{{ __('crm.service_opt') }}</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                :label="__('crm.source_method')"
                                name="supplier_method"
                                :required="true"
                            >
                                <option value="buy" selected>{{ __('crm.trade_buy_opt') }}</option>
                                <option value="manufacture">{{ __('crm.manufacture_produce_opt') }}</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                :label="__('crm.unit_uom')"
                                name="uom_id"
                                :required="true"
                            >
                                @foreach($uomOptions as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                :label="__('crm.pref_vendor')"
                                name="preferred_vendor_id"
                            >
                                <option value="">{{ __('crm.select_pref_supplier') }}</option>
                                @foreach($vendorOptions as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Column 2: Pricing & Accounts -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-dollar-sign me-1.5"></i>{{ __('crm.pricing_accounts') }}</h6>

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="number"
                                step="0.01"
                                :label="__('crm.cost_price')"
                                name="unit_cost"
                                value="0.00"
                                placeholder="0.00"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="number"
                                step="0.01"
                                :label="__('crm.selling_price_label')"
                                name="selling_price"
                                value="0.00"
                                placeholder="0.00"
                            />

                            <x-ui.odoo-form-ui
                                type="select"
                                :label="__('crm.valuation_label')"
                                name="inventory_valuation_method"
                                :required="true"
                            >
                                <option value="FIFO" selected>{{ __('crm.valuation_fifo') }}</option>
                                <option value="Weighted Average">{{ __('crm.valuation_weighted_avg') }}</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                :label="__('crm.sales_account_label')"
                                name="sales_account"
                            >
                                <option value="" selected>{{ __('crm.select_sales_account') }}</option>
                                @foreach($salesAccountOptions as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                :label="__('crm.purchase_account_label')"
                                name="purchase_account"
                            >
                                <option value="" selected>{{ __('crm.select_purchase_account') }}</option>
                                @foreach($purchaseAccountOptions as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui
                                type="select"
                                :label="__('crm.inventory_account_label')"
                                name="inventory_account"
                            >
                                <option value="" selected>{{ __('crm.select_inventory_account') }}</option>
                                @foreach($inventoryAccountOptions as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            @elseif($masterKey === 'contact')
                <!-- Handcrafted Contact Person Modal layout -->
                <div data-action="{{ route('crm.contacts.quick-create') }}"
                     class="quick-create-form"
                     id="quickCreateForm_contact">
                    @csrf
                    <div class="row g-3 text-dark fs-13">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.contact_name_label')"
                                name="name"
                                placeholder="e.g. John Doe"
                                :required="true"
                            />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.designation_role_label')"
                                name="designation"
                                placeholder="e.g. Purchase Manager / Doctor"
                            />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="tel"
                                :label="__('crm.mobile_phone_label')"
                                name="phone"
                                placeholder="02240001122"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="email"
                                :label="__('crm.email_address')"
                                name="email"
                                placeholder="e.g. john.doe@gmail.com"
                            />
                        </div>
                    </div>
                </div>
            @elseif($masterKey === 'customer')
                <!-- Handcrafted Customer Quick Create Modal layout -->
                <div data-action="{{ route('crm.customers.quick-create') }}"
                     class="quick-create-form"
                     id="quickCreateForm_customer">
                    @csrf
                    <div class="row g-4 text-dark fs-13">
                        <!-- Column 1: Primary Details -->
                        <div class="col-md-6 border-end-md">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-1.5"></i>{{ __('crm.basic_information') }}</h6>
                            
                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.customer_name')"
                                name="name"
                                :placeholder="__('crm.customer_name_placeholder')"
                                :required="true"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.gstin_tax_id')"
                                name="gstin"
                                :placeholder="__('crm.gstin_placeholder')"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="email"
                                :label="__('crm.email_address')"
                                name="email"
                                :placeholder="__('crm.company_email_placeholder')"
                                :required="true"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="tel"
                                :label="__('crm.phone_number')"
                                name="phone"
                                :placeholder="__('crm.company_phone_placeholder')"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            />
                        </div>

                        <!-- Column 2: Address Details -->
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary mb-0"><i class="feather-map-pin me-1.5"></i>{{ __('crm.address_information') }}</h6>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fs-12 text-primary" onclick="var b = document.querySelector('#quickCreateForm_customer [name=billing_address]'); var s = document.querySelector('#quickCreateForm_customer [name=shipping_address]'); if(b && s) s.value = b.value;">
                                    <i class="feather-copy me-1"></i>{{ __('crm.copy_billing_shipping') }}
                                </button>
                            </div>

                            <x-ui.odoo-form-ui
                                type="textarea"
                                :label="__('crm.billing_address')"
                                name="billing_address"
                                :placeholder="__('crm.address_placeholder')"
                                :rows="3"
                            />

                            <x-ui.odoo-form-ui
                                type="textarea"
                                :label="__('crm.shipping_address')"
                                name="shipping_address"
                                :placeholder="__('crm.address_placeholder')"
                                :rows="3"
                            />
                        </div>
                    </div>
                </div>
            @elseif($masterKey === 'account')
                <!-- Handcrafted Account & Customer Quick Create Modal layout -->
                <div data-action="{{ route('crm.accounts.store') }}"
                     class="quick-create-form"
                     id="quickCreateForm_account">
                    @csrf
                    <div class="row g-4 text-dark fs-13">
                        <!-- Column 1: Company / Account Master Details -->
                        <div class="col-md-6 border-end-md pe-md-3">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-briefcase me-1.5"></i>{{ __('crm.company_master_details') }}</h6>

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.company_name')"
                                name="name"
                                :placeholder="__('crm.company_name_placeholder')"
                                :required="true"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.gstin')"
                                name="gstin"
                                :placeholder="__('crm.gstin_placeholder')"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="email"
                                :label="__('crm.contact_email')"
                                name="email"
                                :placeholder="__('crm.contact_email_placeholder')"
                                :required="true"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="tel"
                                :label="__('crm.contact_phone')"
                                name="phone"
                                :placeholder="__('crm.contact_phone_placeholder')"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.industry_type')"
                                name="industry_type"
                                :placeholder="__('crm.industry_placeholder')"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.website_url')"
                                name="website"
                                :placeholder="__('crm.website_placeholder')"
                            />
                        </div>

                        <!-- Column 2: Primary Contact & Address Details -->
                        <div class="col-md-6 ps-md-3">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-1.5"></i>{{ __('crm.primary_contact_person') }}</h6>

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.primary_contact')"
                                name="contact_name"
                                :placeholder="__('crm.primary_contact_placeholder')"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="text"
                                :label="__('crm.designation_role')"
                                name="designation"
                                :placeholder="__('crm.designation_placeholder')"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="tel"
                                :label="__('crm.contact_phone')"
                                name="contact_phone"
                                :placeholder="__('crm.primary_contact_phone_placeholder')"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            />

                            <x-ui.odoo-form-ui
                                type="input"
                                inputType="email"
                                :label="__('crm.contact_email')"
                                name="contact_email"
                                :placeholder="__('crm.primary_contact_email_placeholder')"
                            />

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-2"><i class="feather-map-pin me-1.5"></i>{{ __('crm.address_location') }}</h6>

                                <x-ui.odoo-form-ui
                                    type="input"
                                    inputType="text"
                                    :label="__('crm.street_address')"
                                    name="street"
                                    :placeholder="__('crm.street_placeholder')"
                                />

                                <x-ui.odoo-form-ui
                                    type="input"
                                    inputType="text"
                                    :label="__('crm.city')"
                                    name="city"
                                    :placeholder="__('crm.city_placeholder')"
                                />

                                <x-ui.odoo-form-ui
                                    type="input"
                                    inputType="text"
                                    :label="__('crm.state')"
                                    name="state"
                                    :placeholder="__('crm.state_placeholder')"
                                />
                            </div>
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
                                        @if(in_array($field['props']['name'], ['phone', 'contact_phone', 'mobile', 'zip_code']))
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                        @endif
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
                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('crm.cancel') }}</button>
                <button type="button"
                        class="btn btn-primary btn-save-master"
                        data-form="quickCreateForm_{{ $masterKey }}">
                    {{ match($masterKey) {
                        'account' => __('crm.save_account'),
                        'customer' => __('crm.save_customer'),
                        'contact' => __('crm.save_contact'),
                        'product' => __('crm.save_product'),
                        'warehouse' => __('crm.save_warehouse'),
                        'uom' => __('crm.save_uom'),
                        'routing' => __('crm.save_routing'),
                        default => __('crm.save_master', ['master' => $def['label']])
                    } }}
                </button>
            </x-slot>
        </x-ui.modal>
        @endif
    @endif
@endforeach
