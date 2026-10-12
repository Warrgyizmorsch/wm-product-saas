<!-- ==================== LEFT MAIN SHEET (Scrollable) ==================== -->
<div class="odoo-sheet-col flex-grow-1 h-100 p-0" style="scroll-behavior: smooth; background-color: #ffffff; overflow-x: hidden !important; overflow-y: auto !important;" id="odooMainScrollable">


    
    @if ((request()->has('edit_lead') || old('form_type') === 'lead_edit') && !in_array(strtolower($lead->status ?? ''), ['dealing', 'won']))
        <!-- ==================== STATE: EDIT LEAD FORM ==================== -->
        <div class="p-4 p-xl-4 bg-white w-100 odoo-sheet-paper border-0 shadow-none rounded-0">
            <div>
                <form action="{{ route('crm.leads.update', $lead->id) }}" method="POST" class="odoo-sheet" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_type" value="lead_edit">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                        <h5 class="fw-bold text-dark mb-0">{{ __('crm.edit_lead_details') }}</h5>
                        <div class="d-flex gap-2">
                            <x-ui.button href="{{ route('crm.leads.show', $lead->id) }}" variant="light" size="sm" class="border">
                                {{ __('crm.cancel') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm">
                                {{ __('crm.save_changes') }}
                            </x-ui.button>
                        </div>
                    </div>

                    <div class="row g-4 fs-13 text-dark">
                        <!-- Left Column -->
                        <div class="col-md-6 border-end">
                             <div class="mb-3 p-3 bg-soft-primary rounded-3 border border-primary-subtle shadow-2xs">
                                 <label class="fw-bold text-dark mb-2 d-block fs-13"><i class="feather-layers me-1 text-primary"></i> {{ __('crm.customer_type_lead_segment') }}</label>
                                 <div class="d-flex gap-4">
                                     <div class="form-check form-check-inline">
                                         <input class="form-check-input" type="radio" name="lead_type" id="edit_lead_type_b2b" value="b2b" {{ old('lead_type', $lead->lead_type ?: 'b2b') === 'b2b' ? 'checked' : '' }} onchange="toggleLeadType('b2b')">
                                         <label class="form-check-label fw-bold text-dark cursor-pointer" for="edit_lead_type_b2b">
                                             {{ __('crm.b2b_business_client') }}
                                         </label>
                                     </div>
                                     <div class="form-check form-check-inline">
                                         <input class="form-check-input" type="radio" name="lead_type" id="edit_lead_type_b2c" value="b2c" {{ old('lead_type', $lead->lead_type) === 'b2c' ? 'checked' : '' }} onchange="toggleLeadType('b2c')">
                                         <label class="form-check-label fw-bold text-dark cursor-pointer" for="edit_lead_type_b2c">
                                             {{ __('crm.b2c_individual_customer') }}
                                         </label>
                                     </div>
                                 </div>
                             </div>

                            <h6 class="fw-bold text-primary mb-3">{{ __('crm.company_contact_info') }}</h6>
                            <x-ui.odoo-form-ui type="input" :label="__('crm.call_date')" name="call_date" id="lead_call_date_picker" :value="old('call_date', $lead->call_date ? $lead->call_date->format('Y-m-d h:i A') : '')" required="true" :errorText="$errors->first('call_date')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.company_name')" name="company_name" id="edit_company_name_input" :value="old('company_name', $lead->company_name)" :placeholder="__('crm.company_name')" :errorText="$errors->first('company_name')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.gstin_tax_no')" name="gstin" id="edit_gstin_input" :value="old('gstin', $lead->gstin ?? '')" :placeholder="__('crm.gstin_placeholder')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.company_email')" name="company_email" id="edit_company_email_input" inputType="email" :value="old('company_email', $lead->company_email ?? '')" :placeholder="__('crm.company_email_placeholder')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.company_phone')" name="company_phone" id="edit_company_phone_input" :value="old('company_phone', $lead->company_phone ?? '')" :placeholder="__('crm.company_phone_placeholder')" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.contact_person')" name="contact_person" :value="old('contact_person', $lead->contact_person)" :placeholder="__('crm.contact_person')" :errorText="$errors->first('contact_person')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.designation_role')" name="designation" :value="old('designation', $lead->designation)" :placeholder="__('crm.designation_placeholder')" :errorText="$errors->first('designation')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.contact_email')" name="email" inputType="email" :value="old('email', $lead->email)" placeholder="email@address.com" :errorText="$errors->first('email')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.contact_phone')" name="phone" :value="old('phone', $lead->phone)" :placeholder="__('crm.contact_phone')" :errorText="$errors->first('phone')" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                            
                            <x-ui.odoo-form-ui type="select" :label="__('crm.lead_owner')" name="lead_owner_id" :errorText="$errors->first('lead_owner_id')">
                                <option value="">{{ __('crm.unassigned') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('lead_owner_id', $lead->lead_owner_id) == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="textarea" :label="__('crm.requirements_notes')" name="requirement" rows="3" :placeholder="__('crm.requirements_placeholder')">{{ old('requirement', $lead->requirement) }}</x-ui.odoo-form-ui>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-3">{{ __('crm.lead_classification') ?? 'Lead Classification & Address' }}</h6>
                            <x-ui.odoo-form-ui type="input" :label="__('crm.street_address')" name="address" :value="old('address', $lead->address)" :placeholder="__('crm.street_address_placeholder')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.city')" name="city" :value="old('city', $lead->city)" :placeholder="__('crm.city_placeholder')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.state')" name="state" :value="old('state', $lead->state)" :placeholder="__('crm.state_placeholder')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.country')" name="country" :value="old('country', $lead->country)" :placeholder="__('crm.country')" />

                            <x-ui.odoo-form-ui type="select" :label="__('crm.priority')" name="priority">
                                @foreach (['Low', 'Medium', 'High'] as $prioOption)
                                    <option value="{{ $prioOption }}" @selected(old('priority', $lead->priority) === $prioOption)>{{ __('crm.priorities.' . $prioOption) }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('crm.lead_source')" name="source">
                                <option value="">{{ __('crm.select_an_option') }}</option>
                                @foreach (['Website', 'Referral', 'Cold Call', 'Expo', 'Other'] as $srcOption)
                                    <option value="{{ $srcOption }}" @selected(old('source', $lead->source) === $srcOption)>{{ __('crm.sources.' . $srcOption) ?? $srcOption }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.industry_type')" name="industry_type" :value="old('industry_type', $lead->industry_type)" :placeholder="__('crm.industry_placeholder')" />

                            <x-ui.odoo-form-ui type="select" :label="__('crm.segment')" name="segment" :errorText="$errors->first('segment')">
                                <option value="">{{ __('crm.select_an_option') }}</option>
                                @foreach (['SMB', 'Mid-Market', 'Enterprise'] as $segOption)
                                    <option value="{{ $segOption }}" @selected(old('segment', $lead->segment) === $segOption)>{{ __('crm.segments.' . $segOption) ?? $segOption }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            @php
                                $savedItems = old('items', $lead->product_items ?: []);
                                if (empty($savedItems) && !empty($lead->product_ids)) {
                                    foreach ($lead->product_ids as $pid) {
                                        $savedItems[] = ['product_id' => (int)$pid, 'quantity' => 1];
                                    }
                                }
                                if (empty($savedItems)) {
                                    $savedItems = [['product_id' => '', 'quantity' => 1]];
                                }
                                $allProds = $products ?? collect();
                                $finished = $allProds->filter(fn($p) => $p->type === 'finished_good');
                                $semiFinished = $allProds->filter(fn($p) => $p->type === 'semi_finished');
                                $services = $allProds->filter(fn($p) => $p->item_type === 'Service' || $p->type === 'service');
                                $others = $allProds->filter(fn($p) => !in_array($p->type, ['finished_good', 'semi_finished', 'service']) && $p->item_type !== 'Service');
                            @endphp

                            <!-- Ultra Compact Product & Quantity Repeater Table -->
                            <style>
                                #productItemsTable {
                                    table-layout: fixed !important;
                                    width: 100% !important;
                                }
                                #productItemsTable .select2-container {
                                    width: 100% !important;
                                }
                                #productItemsTable .select2-container .select2-selection--single {
                                    height: 32px !important;
                                    padding: 2px 8px !important;
                                    font-size: 13px !important;
                                    border-color: #dee2e6 !important;
                                }
                                #productItemsTable .select2-container .select2-selection--single .select2-selection__rendered {
                                    line-height: 26px !important;
                                    white-space: nowrap !important;
                                    overflow: hidden !important;
                                    text-overflow: ellipsis !important;
                                    padding-left: 0 !important;
                                    padding-right: 15px !important;
                                }
                                #productItemsTable .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
                                    height: 30px !important;
                                }
                                #productItemsTable .qty-row-input {
                                    height: 32px !important;
                                    font-size: 13px !important;
                                    font-weight: 600 !important;
                                    border-color: #dee2e6;
                                }
                                html.app-skin-dark #productItemsTable .qty-row-input {
                                    background-color: #121a2d !important;
                                    border-color: #283c50 !important;
                                    color: #ffffff !important;
                                }
                                .remove-product-row-btn {
                                    transition: transform 0.15s ease-in-out, opacity 0.15s ease-in-out;
                                }
                                .remove-product-row-btn:hover {
                                    opacity: 1 !important;
                                    transform: scale(1.15);
                                }
                            </style>

                            <div class="mb-3 mt-3" id="productItemsContainer">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-package me-1 text-primary"></i>{{ __('crm.product_and_quantity') ?? 'Products & Quantity' }}
                                    </label>
                                    <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-2 py-1 fs-11" id="addProductRowBtn" style="border-radius: 6px;">
                                        <i class="feather-plus me-1"></i>{{ __('crm.add_product_btn') ?? '+ Add Product' }}
                                    </button>
                                </div>
                                
                                <div class="border rounded-3 bg-white p-2 shadow-sm" style="max-height: 270px; overflow-y: auto;">
                                    <table class="table table-sm table-borderless align-middle mb-0" id="productItemsTable">
                                        <thead>
                                            <tr class="border-bottom text-muted fs-11" style="background-color: #f8fafc;">
                                                <th style="width: 65%; font-weight: 600;" class="py-1 ps-2">{{ __('crm.product') ?? 'Product' }}</th>
                                                <th style="width: 23%; font-weight: 600;" class="py-1 text-center">{{ __('crm.qty') ?? 'Qty' }}</th>
                                                <th style="width: 12%; font-weight: 600;" class="py-1 text-center"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="productItemsBody">
                                            @foreach($savedItems as $idx => $item)
                                                <tr class="lead-item-row border-bottom">
                                                    <td class="py-1 ps-1 pe-1 align-top">
                                                        <select name="items[{{ $idx }}][product_id]" class="form-select form-select-sm product-row-select" searchable="true" data-master="product">
                                                            <option value="">{{ __('crm.select_product') ?? 'Select Product' }}</option>
                                                            <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ {{ __('crm.add_new_product') ?? 'Add New Product' }}</option>
                                                            
                                                            @if($finished->count())
                                                                <optgroup label="{{ __('crm.optgroup_finished_goods') ?? 'Finished Goods' }}">
                                                                    @foreach($finished as $p)
                                                                        @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                                        <option value="{{ $p->id }}" data-price="{{ $pPrice }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                            {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                        </option>
                                                                    @endforeach
                                                                </optgroup>
                                                            @endif

                                                            @if($semiFinished->count())
                                                                <optgroup label="{{ __('crm.optgroup_semi_finished') ?? 'Semi-Finished Goods' }}">
                                                                    @foreach($semiFinished as $p)
                                                                        @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                                        <option value="{{ $p->id }}" data-price="{{ $pPrice }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                            {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                        </option>
                                                                    @endforeach
                                                                </optgroup>
                                                            @endif

                                                            @if($services->count())
                                                                <optgroup label="{{ __('crm.optgroup_services') ?? 'Services' }}">
                                                                    @foreach($services as $p)
                                                                        @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                                        <option value="{{ $p->id }}" data-price="{{ $pPrice }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                            {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                        </option>
                                                                    @endforeach
                                                                </optgroup>
                                                            @endif

                                                            @if($others->count())
                                                                <optgroup label="{{ __('crm.optgroup_raw_materials') ?? 'Other / Raw Materials' }}">
                                                                    @foreach($others as $p)
                                                                        @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                                        <option value="{{ $p->id }}" data-price="{{ $pPrice }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                            {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                        </option>
                                                                    @endforeach
                                                                </optgroup>
                                                            @endif
                                                        </select>
                                                    </td>
                                                    <td class="py-1 px-1 align-top">
                                                        <input type="text" inputmode="decimal" autocomplete="off" name="items[{{ $idx }}][quantity]" class="form-control form-control-sm text-center qty-row-input @error('items.'.$idx.'.quantity') is-invalid @enderror" value="{{ $item['quantity'] ?? 1 }}">
                                                        @error('items.'.$idx.'.quantity')
                                                            <div class="text-danger fs-11 mt-1 fw-semibold text-center qty-error-msg">{{ $message }}</div>
                                                        @enderror
                                                    </td>
                                                    <td class="py-1 text-center align-top pt-2">
                                                        <button type="button" class="btn btn-link text-danger p-0 opacity-75 remove-product-row-btn" title="{{ __('crm.remove_product') ?? 'Remove Product' }}">
                                                            <i class="feather-trash-2 fs-13"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <template id="productRowSelectTemplate">
                                <select class="form-select form-select-sm product-row-select" searchable="true" data-master="product">
                                    <option value="">{{ __('crm.select_product') ?? 'Select Product' }}</option>
                                    <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ {{ __('crm.add_new_product') ?? 'Add New Product' }}</option>
                                    
                                    @if($finished->count())
                                        <optgroup label="{{ __('crm.optgroup_finished_goods') ?? 'Finished Goods' }}">
                                            @foreach($finished as $p)
                                                @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                <option value="{{ $p->id }}" data-price="{{ $pPrice }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                            @endforeach
                                        </optgroup>
                                    @endif

                                    @if($semiFinished->count())
                                        <optgroup label="{{ __('crm.optgroup_semi_finished') ?? 'Semi-Finished Goods' }}">
                                            @foreach($semiFinished as $p)
                                                @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                <option value="{{ $p->id }}" data-price="{{ $pPrice }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                            @endforeach
                                        </optgroup>
                                    @endif

                                    @if($services->count())
                                        <optgroup label="{{ __('crm.optgroup_services') ?? 'Services' }}">
                                            @foreach($services as $p)
                                                @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                <option value="{{ $p->id }}" data-price="{{ $pPrice }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                            @endforeach
                                        </optgroup>
                                    @endif

                                    @if($others->count())
                                        <optgroup label="{{ __('crm.optgroup_raw_materials') ?? 'Other / Raw Materials' }}">
                                            @foreach($others as $p)
                                                @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                <option value="{{ $p->id }}" data-price="{{ $pPrice }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </template>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.expected_revenue_label')" name="expected_amount" inputType="number" :value="old('expected_amount', $lead->expected_amount)" min="0" step="0.01" :placeholder="__('crm.expected_revenue_label')" :errorText="$errors->first('expected_amount')" />
                            <x-ui.odoo-form-ui type="input" :label="__('crm.expected_sale_date')" name="expected_sale_date" inputType="date" :value="old('expected_sale_date', $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : '')" :errorText="$errors->first('expected_sale_date')" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @else
        <!-- ==================== ODOO SHEET PAPER ==================== -->
        <div class="p-4 p-xl-4 bg-white w-100 odoo-sheet-paper border-0 shadow-none rounded-0" id="detailedFieldsContainer">
            
            <!-- Top Sheet Header: Company Name & Metadata on Left, Action Buttons on Right -->
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3 pb-3 border-bottom">
                <div>
                    <h2 class="fw-bold text-dark mb-1 fs-20" style="letter-spacing: -0.2px; font-family: 'Inter', sans-serif;">
                        {{ $lead->company_name ?: ($lead->contact_person ?: 'Lead #' . $lead->id) }}
                    </h2>
                    <div class="d-flex align-items-center gap-2 flex-wrap text-muted fs-12">
                        @if($lead->contact_person && $lead->company_name && strcasecmp(trim($lead->contact_person), trim($lead->company_name)) !== 0)
                            <span class="text-dark fw-medium"><i class="feather-user me-1 text-primary"></i>{{ $lead->contact_person }}</span>
                            <span>&bull;</span>
                        @endif
                        <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-10 fw-semibold text-uppercase">{{ $lead->lead_type ?: 'B2B' }}</span>
                        @if($lead->segment && $lead->segment !== 'Select an Option')
                            <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-10 fw-semibold">{{ $lead->segment }}</span>
                        @endif
                        <button type="button" class="btn btn-xs btn-outline-secondary zoho-tag-btn d-inline-flex align-items-center text-muted px-2 py-0.5 border" style="font-size: 10px; border-radius: 3px;">
                            <i class="feather-tag me-1 fs-9"></i> {{ __('crm.add_tags') }}
                        </button>
                    </div>
                </div>

                <!-- Right: Action Buttons & Odoo Smart Buttons -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if($lead->crm_deal_id)
                        <x-ui.button href="{{ route('crm.deals.show', $lead->crm_deal_id) }}" variant="primary" size="xs" icon="feather-git-branch" title="Open Deal Pipeline">
                            {{ __('crm.view_deal') ?? 'View Deal' }}
                        </x-ui.button>
                    @else
                        <form action="{{ route('crm.leads.qualify', $lead->id) }}" method="POST" class="d-inline m-0 p-0">
                            @csrf
                            @method('PATCH')
                            <x-ui.button type="submit" variant="primary" size="xs" icon="feather-user-check">
                                {{ __('crm.convert_to_deal') }}
                            </x-ui.button>
                        </form>
                    @endif

                    @if (!in_array(strtolower($lead->status ?? ''), ['dealing', 'won']))
                        <x-ui.button href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}" variant="light" size="xs" class="border" icon="feather-edit">
                            {{ __('crm.edit_lead') }}
                        </x-ui.button>
                    @endif

                    @if($lead->crm_account_id)
                        <x-ui.button href="{{ route('crm.accounts.show', $lead->crm_account_id) }}" variant="light" size="xs" class="border" icon="feather-briefcase">
                            {{ __('crm.view_account') }}
                        </x-ui.button>
                    @endif
                </div>
            </div>

            <!-- Key Metrics Row (Expected Revenue, Priority Stars, Expected Closing Date) -->
            <div class="row g-3 p-3 rounded-3 mb-4 border" style="background-color: #f8fafc; border-color: #e2e8f0 !important;">
                <div class="col-sm-4 border-end">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">{{ __('crm.expected_revenue_label') }}</div>
                    <div class="fs-18 fw-extrabold text-dark">{{ format_currency($lead->expected_amount ?? 0) }}</div>
                </div>
                <div class="col-sm-4 border-end">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">{{ __('crm.priority') }}</div>
                    <div class="d-flex align-items-center gap-1.5">
                        @php
                            $prioBadge = 'bg-secondary';
                            if($lead->priority === 'High') $prioBadge = 'bg-danger text-white';
                            elseif($lead->priority === 'Medium') $prioBadge = 'bg-warning text-dark';
                            elseif($lead->priority === 'Low') $prioBadge = 'bg-info text-white';
                        @endphp
                        <span class="badge {{ $prioBadge }} px-2 py-0.5 fs-11 fw-bold">{{ ($lead->priority && $lead->priority !== 'Select an Option') ? __('crm.priorities.' . $lead->priority) : '—' }}</span>
                        @if($lead->priority === 'High')
                            <span class="text-warning fs-13">★★★</span>
                        @elseif($lead->priority === 'Medium')
                            <span class="text-warning fs-13">★★<span class="text-muted opacity-50">★</span></span>
                        @else
                            <span class="text-warning fs-13">★<span class="text-muted opacity-50">★★</span></span>
                        @endif
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">{{ __('crm.expected_sale_date') }}</div>
                    <div class="fs-13 fw-bold text-dark">
                        <i class="feather-calendar me-1 text-primary fs-12"></i>
                        {{ $lead->expected_sale_date ? $lead->expected_sale_date->format('d/m/Y') : '—' }}
                    </div>
                </div>
            </div>

            <!-- 2-Column Fields Grid -->
            <div class="row g-4 fs-13 text-dark pb-3 border-bottom">
                <!-- Left Column -->
                <div class="col-md-6 pe-md-3">
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.contact_person') }}</div>
                        <div class="zoho-field-value text-dark fw-bold">{{ $lead->contact_person ?: '—' }}</div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.designation_role') }}</div>
                        <div class="zoho-field-value text-dark">{{ $lead->designation ?: '—' }}</div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.contact_email') }}</div>
                        <div class="zoho-field-value">
                            @if($lead->email)
                                <a href="mailto:{{ $lead->email }}" class="text-primary hover-underline">{{ $lead->email }}</a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.contact_phone') }}</div>
                        <div class="zoho-field-value text-dark fw-semibold">{{ $lead->phone ?: '—' }}</div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.company_name') }}</div>
                        <div class="zoho-field-value text-dark fw-bold">{{ $lead->company_name ?: '—' }}</div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.gstin_tax_no') }}</div>
                        <div class="zoho-field-value text-dark">{{ $lead->gstin ?: '—' }}</div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.company_email') }}</div>
                        <div class="zoho-field-value">
                            @if($lead->company_email)
                                <a href="mailto:{{ $lead->company_email }}" class="text-primary hover-underline">{{ $lead->company_email }}</a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.company_phone') }}</div>
                        <div class="zoho-field-value text-dark">{{ $lead->company_phone ?: '—' }}</div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-md-6 ps-md-3">
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.lead_owner') }}</div>
                        <div class="zoho-field-value text-dark fw-bold d-flex align-items-center gap-1.5">
                            <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle fw-bold" style="width: 22px; height: 22px; font-size: 10px;">
                                {{ strtoupper(substr($lead->owner?->name ?: 'U', 0, 1)) }}
                            </div>
                            <span>{{ $lead->owner?->name ?: 'Unassigned' }}</span>
                        </div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.lead_status') }}</div>
                        <div class="zoho-field-value">
                            @php
                                $statusVal = $lead->status ?: 'New';
                                $badgeClass = match(strtolower($statusVal)) {
                                    'new' => 'bg-soft-primary text-primary border border-primary-subtle',
                                    'qualified' => 'bg-soft-teal text-teal border border-teal-subtle',
                                    'dealing' => 'bg-soft-info text-info border border-info-subtle',
                                    'won' => 'bg-soft-success text-success border border-success-subtle',
                                    'lost' => 'bg-soft-danger text-danger border border-danger-subtle',
                                    default => 'bg-soft-secondary text-secondary border border-secondary-subtle',
                                };
                                $dotColor = match(strtolower($statusVal)) {
                                    'new' => '#2563eb',
                                    'qualified' => '#0d9488',
                                    'dealing' => '#0284c7',
                                    'won' => '#16a34a',
                                    'lost' => '#dc2626',
                                    default => '#64748b',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }} rounded-pill px-2.5 py-1 fs-12 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-2xs">
                                @if(strtolower($statusVal) === 'won')
                                    <i class="feather-check-circle fs-11"></i>
                                @elseif(strtolower($statusVal) === 'lost')
                                    <i class="feather-x-circle fs-11"></i>
                                @else
                                    <span class="rounded-circle" style="width: 7px; height: 7px; background-color: {{ $dotColor }};"></span>
                                @endif
                                <span>{{ $statusVal }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.call_date') }}</div>
                        <div class="zoho-field-value text-dark">{{ $lead->call_date ? $lead->call_date->format('d/m/Y h:i A') : '—' }}</div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.industry_type') }}</div>
                        <div class="zoho-field-value text-dark">{{ $lead->industry_type ?: '—' }}</div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.segment') }}</div>
                        <div class="zoho-field-value text-dark">{{ ($lead->segment && $lead->segment !== 'Select an Option') ? __('crm.segments.' . $lead->segment) : '—' }}</div>
                    </div>
                    <div class="zoho-field-row">
                        <div class="zoho-field-label">{{ __('crm.lead_source') }}</div>
                        <div class="zoho-field-value">
                            <span class="badge bg-light text-dark border px-2 py-0.5" style="font-size: 11px;">{{ ($lead->source && !in_array($lead->source, ['Select an Option', 'Select an option', 'Select Option'], true)) ? (\Illuminate\Support\Facades\Lang::has('crm.sources.' . $lead->source) ? __('crm.sources.' . $lead->source) : $lead->source) : '—' }}</span>
                        </div>
                    </div>

                    @php $allAddlContacts = $lead->additional_contacts ?: []; @endphp
                    @if(!empty($allAddlContacts))
                        <div class="mt-3 p-2.5 border rounded-2 bg-light-50">
                            <span class="fs-11 fw-bold text-uppercase text-primary letter-spacing-1 d-block mb-1.5">
                                <i class="feather-users me-1 text-primary"></i> Additional Contacts ({{ count($allAddlContacts) }})
                            </span>
                            <div class="d-flex flex-column gap-1.5">
                                @foreach($allAddlContacts as $ac)
                                    @if(!empty($ac['name']) || !empty($ac['phone']) || !empty($ac['email']))
                                        <div class="p-1.5 border rounded-1 bg-white fs-11">
                                            <strong class="text-dark">{{ $ac['name'] ?: 'N/A' }}</strong>
                                            @if(!empty($ac['designation'])) <span class="text-muted">({{ $ac['designation'] }})</span> @endif
                                            @if(!empty($ac['phone'])) &bull; <span class="text-dark">{{ $ac['phone'] }}</span> @endif
                                            @if(!empty($ac['email'])) &bull; <a href="mailto:{{ $ac['email'] }}" class="text-primary">{{ $ac['email'] }}</a> @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Odoo Notebook Horizontal Tabs -->
            <div class="odoo-notebook mt-3 pt-2">
                @php
                    $leadItems = $lead->product_items ?: [];
                    if (empty($leadItems) && !empty($lead->product_ids)) {
                        foreach ($lead->product_ids as $pid) {
                            $leadItems[] = ['product_id' => (int)$pid, 'quantity' => 1.0];
                        }
                    }
                    $leadPIds = !empty($leadItems) ? array_column($leadItems, 'product_id') : [];
                    $leadProductsMap = !empty($leadPIds) ? \App\Domains\Inventory\Models\Product::whereIn('id', $leadPIds)->get()->keyBy('id') : collect();
                @endphp
                <ul class="nav nav-tabs odoo-notebook-tabs border-bottom" id="odooNotebookTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold fs-12 px-3 py-2" id="nb-requirements-tab" data-bs-toggle="tab" data-bs-target="#nb-requirements-pane" type="button" role="tab" aria-controls="nb-requirements-pane" aria-selected="true">
                            <i class="feather-file-text me-1 text-primary"></i>{{ __('crm.requirements') }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold fs-12 px-3 py-2" id="nb-products-tab" data-bs-toggle="tab" data-bs-target="#nb-products-pane" type="button" role="tab" aria-controls="nb-products-pane" aria-selected="false">
                            <i class="feather-box me-1 text-primary"></i>{{ __('crm.product_and_quantity') }}
                            @if(!empty($leadItems))
                                <span class="badge bg-soft-primary text-primary rounded-pill px-1.5 py-0.2 fs-10 ms-1">{{ count($leadItems) }}</span>
                            @endif
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold fs-12 px-3 py-2" id="nb-address-tab" data-bs-toggle="tab" data-bs-target="#nb-address-pane" type="button" role="tab" aria-controls="nb-address-pane" aria-selected="false">
                            <i class="feather-map-pin me-1 text-primary"></i>{{ __('crm.address_details') }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold fs-12 px-3 py-2" id="nb-documents-tab" data-bs-toggle="tab" data-bs-target="#nb-documents-pane" type="button" role="tab" aria-controls="nb-documents-pane" aria-selected="false">
                            <i class="feather-paperclip me-1 text-primary"></i>{{ __('crm.lead_documents') }}
                            <span class="badge bg-soft-primary text-primary rounded-pill px-1.5 py-0.2 fs-10 ms-1">{{ $lead->leadDocuments->count() }}</span>
                        </button>
                    </li>
                    @if(!empty($lead->utm_source) || !empty($lead->utm_medium) || !empty($lead->utm_campaign) || !empty($lead->utm_term) || !empty($lead->utm_content))
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold fs-12 px-3 py-2" id="nb-utm-tab" data-bs-toggle="tab" data-bs-target="#nb-utm-pane" type="button" role="tab" aria-controls="nb-utm-pane" aria-selected="false">
                                <i class="feather-compass me-1 text-primary"></i>UTM Parameters
                            </button>
                        </li>
                    @endif
                </ul>

                <div class="tab-content pt-3" id="odooNotebookTabsContent">
                    
                    <!-- ==================== PANE 1: REQUIREMENTS ==================== -->
                    <div class="tab-pane fade show active" id="nb-requirements-pane" role="tabpanel" aria-labelledby="nb-requirements-tab">
                        <div class="p-3 border rounded-3 bg-white" style="border-color: #e2e8f0 !important;" id="sectionRequirements">
                            <div class="d-flex align-items-center justify-content-between pb-2 border-bottom mb-3">
                                <h6 class="fs-13 text-dark fw-bold mb-0">
                                    <i class="feather-file-text text-primary me-1.5"></i>{{ __('crm.requirements_details') }}
                                </h6>
                                <span class="text-muted fs-11"><i class="feather-info me-1 text-primary"></i>{{ __('crm.click_box_to_edit') }}</span>
                            </div>

                            <!-- View Mode (Clickable to Edit) -->
                            <div id="viewRequirementBlock">
                                @if ($lead->requirement)
                                    <div class="position-relative requirement-clickable-box p-3 rounded shadow-2xs" onclick="enableRequirementEdit()" title="Click anywhere to edit requirement">
                                        <div class="d-flex align-items-start justify-content-between gap-3">
                                            <div class="text-dark fs-13 flex-grow-1" style="white-space: pre-wrap; line-height: 1.6; font-family: 'Inter', sans-serif;" id="viewRequirementText">{{ $lead->requirement }}</div>
                                            <span class="badge bg-white text-primary border shadow-2xs px-2.5 py-1.5 fs-11 flex-shrink-0 edit-hint-badge" style="border-color: #cbd5e1 !important; transition: all 0.2s ease;">
                                                <i class="feather-edit-2 me-1"></i>{{ __('crm.click_to_edit') }}
                                            </span>
                                        </div>
                                    </div>
                                @else
                                    <div class="position-relative requirement-empty-box p-4 rounded text-center cursor-pointer" onclick="enableRequirementEdit()" title="Click to add requirement">
                                        <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2">
                                            <i class="feather-edit-3 fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark fs-13 mb-1">{{ __('crm.no_requirements_details_specified') }}</h6>
                                        <p class="text-muted fs-12 mb-0">{{ __('crm.click_here_to_add_requirements') }}</p>
                                    </div>
                                @endif
                            </div>

                            <!-- Edit Mode -->
                            <div id="editRequirementBlock" style="display: none;">
                                <form id="ajaxRequirementForm" action="{{ route('crm.leads.updateRequirement', $lead->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <div class="mb-2">
                                        <textarea name="requirement" id="requirementInput" rows="4" class="form-control form-control-sm shadow-2xs fs-13" placeholder="Enter detailed requirements or specifications for this lead..." style="border-color: var(--bs-primary); border-radius: 6px; font-family: 'Inter', sans-serif;" oninput="updateReqCharCount(this)">{{ old('requirement', $lead->requirement) }}</textarea>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <span class="text-muted fs-11">
                                            <i class="feather-corner-down-left me-1"></i>{{ __('crm.press_ctrl_enter_or_save') }}
                                        </span>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted fs-11 me-2" id="reqCharCounter">0 chars</span>
                                            <button type="button" class="btn btn-xs btn-light border px-3 py-1.5 fw-bold rounded" onclick="cancelRequirementEdit()">{{ strtoupper(__('crm.cancel')) }}</button>
                                            <button type="submit" id="btnSaveRequirement" class="btn btn-xs btn-primary px-3 py-1.5 fw-bold shadow-2xs text-white rounded d-inline-flex align-items-center" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
                                                <i class="feather-check me-1"></i> {{ strtoupper(__('crm.save_requirement')) }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== PANE 2: PRODUCTS & QUANTITY ==================== -->
                    <div class="tab-pane fade" id="nb-products-pane" role="tabpanel" aria-labelledby="nb-products-tab">
                        <div class="p-3 border rounded-3 bg-white" style="border-color: #e2e8f0 !important;" id="sectionLeadProducts">
                            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                                <h6 class="fs-13 text-dark fw-bold mb-0">
                                    <i class="feather-box text-primary me-1.5"></i>{{ __('crm.product_and_quantity') }}
                                </h6>
                                @if($leadProductsMap->isNotEmpty())
                                    <span class="badge bg-soft-primary text-primary fs-11 fw-semibold">{{ count($leadItems) }} {{ __('crm.products_selected') }}</span>
                                @endif
                            </div>
                            @if($leadProductsMap->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0 fs-13">
                                        <thead class="table-light text-muted">
                                            <tr>
                                                <th>{{ __('crm.product_description') }}</th>
                                                <th>SKU</th>
                                                <th class="text-center">{{ __('crm.quantity') }}</th>
                                                <th class="text-end">{{ __('crm.unit_price') }} ({{ active_currency_symbol() }})</th>
                                                <th class="text-end">{{ __('crm.total_estimated_value') }} ({{ active_currency_symbol() }})</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $grandLeadProductTotal = 0; @endphp
                                            @foreach($leadItems as $item)
                                                @php
                                                    $pObj = $leadProductsMap->get($item['product_id']);
                                                    if (!$pObj) continue;
                                                    $pQty = floatval($item['quantity'] ?? 1);
                                                    $pPrice = floatval($pObj->selling_price ?: $pObj->unit_cost ?: 0);
                                                    $lineVal = $pQty * $pPrice;
                                                    $grandLeadProductTotal += $lineVal;
                                                @endphp
                                                <tr>
                                                    <td class="fw-bold text-dark">
                                                        <a href="{{ route('inventory.products.show', $pObj) }}" class="text-dark hover-underline" target="_blank">{{ $pObj->name }}</a>
                                                    </td>
                                                    <td class="font-monospace text-muted">{{ $pObj->sku ?: '—' }}</td>
                                                    <td class="text-center fw-bold text-primary">{{ number_format($pQty, 0) }} {{ $pObj->uom?->code ?? 'Pcs' }}</td>
                                                    <td class="text-end">{{ format_currency($pPrice) }}</td>
                                                    <td class="text-end fw-bold text-success">{{ format_currency($lineVal) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        @if($grandLeadProductTotal > 0)
                                            <tfoot class="table-light fw-bold">
                                                <tr>
                                                    <td colspan="4" class="text-end text-uppercase fs-12">{{ __('crm.total_estimated_product_value') }}:</td>
                                                    <td class="text-end text-success fs-14">{{ format_currency($grandLeadProductTotal) }}</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            @else
                                <p class="text-muted fs-12 mb-0 py-2">{{ __('crm.no_specific_products') }}</p>
                            @endif
                        </div>
                    </div>

                    <!-- ==================== PANE 3: ADDRESS DETAILS ==================== -->
                    <div class="tab-pane fade" id="nb-address-pane" role="tabpanel" aria-labelledby="nb-address-tab">
                        <div class="p-3 border rounded-3 bg-white" style="border-color: #e2e8f0 !important;" id="sectionAddressInfo">
                            <h6 class="fs-13 text-dark fw-bold pb-2 border-bottom mb-3">
                                <i class="feather-map-pin text-primary me-1.5"></i>{{ __('crm.address_details') }}
                            </h6>
                            <div class="row g-0">
                                <div class="col-md-6 pe-md-4">
                                    <div class="zoho-field-row">
                                        <div class="zoho-field-label">{{ __('crm.street') }}</div>
                                        <div class="zoho-field-value text-wrap text-dark" style="max-width: 350px;">{{ $lead->address ?: __('crm.no_street_address') }}</div>
                                    </div>
                                    <div class="zoho-field-row">
                                        <div class="zoho-field-label">{{ __('crm.state') }}</div>
                                        <div class="zoho-field-value text-dark">{{ $lead->state ?: '—' }}</div>
                                    </div>
                                    <div class="zoho-field-row">
                                        <div class="zoho-field-label">{{ __('crm.country') }}</div>
                                        <div class="zoho-field-value text-dark">{{ $lead->country ?: '—' }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6 ps-md-4">
                                    <div class="zoho-field-row">
                                        <div class="zoho-field-label">{{ __('crm.city') }}</div>
                                        <div class="zoho-field-value text-dark">{{ $lead->city ?: '—' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== PANE 4: LEAD DOCUMENTS ==================== -->
                    <div class="tab-pane fade" id="nb-documents-pane" role="tabpanel" aria-labelledby="nb-documents-tab">
                        <div class="p-3 border rounded-3 bg-white" style="border-color: #e2e8f0 !important;" id="sectionDocuments">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-folder me-2 text-primary"></i>{{ __('crm.lead_documents') }} ({{ $lead->leadDocuments->count() }})</h6>
                                <form action="{{ route('crm.leads.documents.upload', $lead->id) }}" method="POST" enctype="multipart/form-data" class="m-0 p-0" id="leadDocUploadForm">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-primary fw-bold" onclick="document.getElementById('leadDocInput').click();" style="background-color: #1e40af; border-color: #1e40af;"><i class="feather-upload me-1"></i> {{ __('crm.upload') }}</button>
                                    <input type="file" name="documents[]" id="leadDocInput" onchange="if (this.files &amp;&amp; this.files.length > 0) { document.getElementById('leadDocUploadForm').submit(); }" multiple style="display: none;">
                                </form>
                            </div>

                            @if($lead->leadDocuments->isEmpty())
                                <div class="text-center py-4 border border-dashed rounded bg-light-subtle">
                                    <i class="feather-file-text fs-24 text-muted mb-1 d-block opacity-50"></i>
                                    <div class="text-muted fs-12">{{ __('crm.no_documents_uploaded') }}</div>
                                </div>
                            @else
                                <div class="row g-3">
                                    @foreach($lead->leadDocuments as $document)
                                        @php
                                            $ext = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION) ?: $document->file_type);
                                        @endphp
                                        <div class="col-md-6">
                                            <div class="p-3 border rounded-3 d-flex align-items-center justify-content-between h-100 shadow-2xs" style="background-color: #f8fafc; border-color: #e2e8f0 !important;">
                                                <div class="d-flex align-items-center overflow-hidden me-2" style="gap: 12px;">
                                                    <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle fw-bold flex-shrink-0">
                                                        <i class="feather-file fs-13"></i>
                                                    </div>
                                                    <div class="overflow-hidden">
                                                        <a href="{{ route('crm.leads.documents.view', $document->id) }}" target="_blank" class="fw-bold text-dark text-decoration-none hover-primary fs-12 text-truncate d-block mb-0.5" title="{{ $document->file_name }}">
                                                            {{ $document->file_name }}
                                                        </a>
                                                        <div class="text-muted fs-11 d-flex align-items-center gap-1.5 flex-wrap">
                                                            <span class="badge bg-white text-secondary border px-1.5 py-0.5 text-uppercase fw-semibold" style="font-size: 9px; border-color: #cbd5e1 !important;">{{ strtoupper($ext) }}</span>
                                                            <span>{{ round($document->size / 1024, 2) }} KB</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                    <a href="{{ route('crm.leads.documents.download', $document->id) }}" class="btn btn-xs btn-soft-success rounded-circle p-0 d-inline-flex align-items-center justify-content-center border" style="width: 28px; height: 28px; border-color: #bbf7d0 !important;" title="Download">
                                                        <i class="feather-download fs-12 text-success"></i>
                                                    </a>
                                                    <form action="{{ route('crm.leads.documents.delete', $document->id) }}" method="POST" class="m-0 p-0" id="deleteDocForm_{{ $document->id }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-xs btn-soft-danger rounded-circle p-0 d-inline-flex align-items-center justify-content-center border" style="width: 28px; height: 28px; border-color: #fecdd3 !important;" title="Delete" onclick="confirmAction({ title: 'Delete Document', message: '{{ __('crm.confirm_delete_document') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deleteDocForm_{{ $document->id }}').submit(); })">
                                                            <i class="feather-trash-2 fs-12 text-danger"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- ==================== PANE 5: UTM PARAMETERS ==================== -->
                    @if(!empty($lead->utm_source) || !empty($lead->utm_medium) || !empty($lead->utm_campaign) || !empty($lead->utm_term) || !empty($lead->utm_content))
                        <div class="tab-pane fade" id="nb-utm-pane" role="tabpanel" aria-labelledby="nb-utm-tab">
                            <div class="p-3 border rounded-3 bg-white" style="border-color: #e2e8f0 !important;" id="sectionUtmParams">
                                <h6 class="fs-13 text-dark fw-bold pb-2 border-bottom mb-3">
                                    <i class="feather-compass text-primary me-1.5"></i>UTM Parameters (Marketing Attribution)
                                </h6>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">UTM Source</div>
                                            <div class="zoho-field-value">
                                                @if($lead->utm_source)
                                                    <span class="badge bg-soft-primary text-primary font-monospace px-2 py-1 fs-11">{{ $lead->utm_source }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">UTM Medium</div>
                                            <div class="zoho-field-value">
                                                @if($lead->utm_medium)
                                                    <span class="badge bg-soft-info text-info font-monospace px-2 py-1 fs-11">{{ $lead->utm_medium }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">UTM Campaign</div>
                                            <div class="zoho-field-value">
                                                @if($lead->utm_campaign)
                                                    <span class="badge bg-soft-teal text-teal font-monospace px-2 py-1 fs-11">{{ $lead->utm_campaign }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">UTM Term</div>
                                            <div class="zoho-field-value text-dark font-monospace fs-12">{{ $lead->utm_term ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">UTM Content</div>
                                            <div class="zoho-field-value text-dark font-monospace fs-12">{{ $lead->utm_content ?: '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

        </div> <!-- End odoo-sheet-paper -->
    @endif
</div> <!-- End odoo-sheet-col -->
