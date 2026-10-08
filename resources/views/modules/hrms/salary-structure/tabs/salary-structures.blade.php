@php
    $selectedPayGroup = $selectedPayGroup ?? null;

    // Load active recurring salary components created for the current pay group (or general)
    $recurringComponentsForStructure = \App\Domains\HRMS\Models\SalaryComponent::where('status', true)
        ->where('is_adhoc', false)
        ->where(function($q) use ($selectedPayGroup) {
            if ($selectedPayGroup) {
                $q->where('pay_group_id', $selectedPayGroup->id)
                  ->orWhereNull('pay_group_id');
            }
        })
        ->orderBy('name', 'asc')
        ->get();

    // Ensure a fixed balancing component ("Other Allowance") exists in every salary structure
    $hasOtherComponent = $recurringComponentsForStructure->contains(function($c) {
        $cCode = strtoupper($c->code);
        $cName = strtolower($c->name);
        return in_array($cCode, ['OTHER', 'OTHER_ALLOWANCE', 'SA', 'SPECIAL_ALLOWANCE']) 
            || \Illuminate\Support\Str::contains($cName, ['other', 'special allowance', 'balancing']);
    });

    if (!$hasOtherComponent) {
        $otherComp = \App\Domains\HRMS\Models\SalaryComponent::firstOrCreate(
            [
                'code' => 'OTHER',
                'pay_group_id' => $selectedPayGroup ? $selectedPayGroup->id : null,
                'is_adhoc' => false,
            ],
            [
                'company_id' => $selectedPayGroup ? $selectedPayGroup->company_id : (\App\Domains\HRMS\Models\Company::first()?->id ?? 1),
                'name' => 'Other Allowance',
                'type' => 'earning',
                'calculation_type' => 'balancing',
                'status' => true,
                'description' => 'Fixed balance remainder component'
            ]
        );
        $recurringComponentsForStructure->push($otherComp);
    }

    $salaryStructures = $salaryStructures ?? collect();
    $rules = $selectedPayGroup->payroll_rules ?? [];
    $isPfEnabled = !isset($rules['enable_pf']) || (bool)$rules['enable_pf'];
    $isEsiEnabled = !isset($rules['enable_esi']) || (bool)$rules['enable_esi'];
@endphp

<div class="row g-4">
    <!-- List Table Card -->
    <div class="col-12">
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
            <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.salary.structures_slabs') }}</h5>
            <div class="d-flex align-items-center gap-2">
                <x-ui.button variant="light" size="sm" icon="feather-activity" data-bs-toggle="offcanvas" data-bs-target="#ctcCalculatorDrawer" class="me-2">
                    {{ __('hrms.salary.ctc_calculator') }}
                </x-ui.button>
                <x-ui.button variant="primary" size="sm" icon="feather-plus" class="add-structure-trigger" data-pay-group-id="{{ $selectedPayGroup ? $selectedPayGroup->id : '' }}" data-bs-toggle="modal" data-bs-target="#addSalaryStructureModal">
                    {{ __('hrms.salary.add_structure') }}
                </x-ui.button>
            </div>
        </div>

            <div class="px-4 py-3 border-bottom bg-white d-flex align-items-center justify-content-end gap-2 flex-wrap" style="position: relative; z-index: 10;">
                <input type="hidden" id="struct_sort_value" value="{{ request('struct_sort', 'name_asc') }}">
                <input type="hidden" id="struct_status_value" value="{{ request('struct_status') }}">

                <!-- Search Input (Placed before sort and filter in same line) -->
                <div class="theme-search-container" style="max-width: 300px;">
                    <i class="feather-search"></i>
                    <input type="text" id="struct_search_input" name="struct_search" class="theme-search-input" placeholder="{{ __('hrms.salary.search_structures') }}" value="{{ request('struct_search') }}">
                </div>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('struct_sort') === 'name_asc' || !request('struct_sort') ? 'active' : '' }}" href="#" data-sort="name_asc" onclick="changeStructSort('name_asc', this); event.preventDefault();">
                        <span>{{ __('hrms.common.sort_name_asc') }}</span>
                        @if(request('struct_sort') === 'name_asc' || !request('struct_sort')) <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('struct_sort') === 'name_desc' ? 'active' : '' }}" href="#" data-sort="name_desc" onclick="changeStructSort('name_desc', this); event.preventDefault();">
                        <span>{{ __('hrms.common.sort_name_desc') }}</span>
                        @if(request('struct_sort') === 'name_desc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('struct_sort') === 'min_ctc_asc' ? 'active' : '' }}" href="#" data-sort="min_ctc_asc" onclick="changeStructSort('min_ctc_asc', this); event.preventDefault();">
                        <span>{{ __('hrms.salary.min_ctc_low_high') }}</span>
                        @if(request('struct_sort') === 'min_ctc_asc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('struct_sort') === 'min_ctc_desc' ? 'active' : '' }}" href="#" data-sort="min_ctc_desc" onclick="changeStructSort('min_ctc_desc', this); event.preventDefault();">
                        <span>{{ __('hrms.salary.min_ctc_high_low') }}</span>
                        @if(request('struct_sort') === 'min_ctc_desc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('struct_sort') === 'max_ctc_asc' ? 'active' : '' }}" href="#" data-sort="max_ctc_asc" onclick="changeStructSort('max_ctc_asc', this); event.preventDefault();">
                        <span>{{ __('hrms.salary.max_ctc_low_high') }}</span>
                        @if(request('struct_sort') === 'max_ctc_asc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('struct_sort') === 'max_ctc_desc' ? 'active' : '' }}" href="#" data-sort="max_ctc_desc" onclick="changeStructSort('max_ctc_desc', this); event.preventDefault();">
                        <span>{{ __('hrms.salary.max_ctc_high_low') }}</span>
                        @if(request('struct_sort') === 'max_ctc_desc') <i class="feather-check ms-3"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <x-ui.filter label="{{ __('hrms.common.filter') }}">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.org.status') }}</label>
                        <x-ui.odoo-form-ui type="select" name="struct_filter_status" id="struct_filter_status">
                            <option value="">{{ __('hrms.common.all_statuses') }}</option>
                            <option value="1" @selected(request('struct_status') === '1')>{{ __('hrms.employees.frm_status_active') }}</option>
                            <option value="0" @selected(request('struct_status') === '0')>{{ __('hrms.employees.frm_status_inactive') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    
                    <div class="dropdown-divider my-3"></div>

                    <div class="d-flex gap-2">
                        <x-ui.button type="button" variant="primary" size="sm" class="flex-grow-1" onclick="applyStructFilter()">{{ __('hrms.common.apply') }}</x-ui.button>
                        <x-ui.button type="button" variant="light" size="sm" class="border flex-grow-1" onclick="resetStructFilters()">{{ __('hrms.common.reset') }}</x-ui.button>
                    </div>
                </x-ui.filter>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="salaryStructuresTable" style="table-layout: fixed; width: 100%;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 45px;">#</th>
                            <th>{{ __('hrms.salary.structure_name') }}</th>
                            <th style="width: 100px; white-space: nowrap;">{{ __('hrms.org.status') }}</th>
                            <th style="width: 110px; white-space: nowrap;" class="text-end">{{ __('hrms.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salaryStructures as $structure)
                            <tr class="structure-row">
                                <td>{{ $loop->iteration }}</td>
                                <td style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                    <div class="fw-bold text-dark structure-name fs-14">{{ $structure->name }}</div>
                                    <div class="fs-12 text-muted mt-1">
                                        ₹{{ number_format($structure->min_ctc, 2) }} - ₹{{ number_format($structure->max_ctc, 2) }}
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge bg-soft-info text-info rounded-pill px-2 fs-11">
                                            {{ $structure->items->count() }} {{ __('hrms.salary.salary_components_tab') }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if($structure->status)
                                        <x-ui.badge variant="success" soft>{{ __('hrms.employees.frm_status_active') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="danger" soft>{{ __('hrms.employees.frm_status_inactive') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="hstack gap-2 justify-content-end align-items-center">
                                        <a href="javascript:void(0)" class="toggle-structure-details action-dropdown-btn" data-target="#structure-details-{{ $structure->id }}" title="{{ __('hrms.salary.show_components') }}" style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="feather feather-chevron-down"></i>
                                        </a>
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item edit-structure-btn" href="javascript:void(0)" data-structure="{{ base64_encode($structure->toJson()) }}">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('hrms.salary.edit') }}
                                                </a>
                                            </li>
                                            <li>
                                                <form action="{{ route('hrms.salary-structure.structure.destroy', $structure->id) }}" method="POST" class="d-inline" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.salary.delete_structure_confirm') }}', { title: '{{ __('hrms.salary.delete') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.common.delete') }}' });">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('hrms.salary.delete') }}
                                                    </button>
                                                </form>
                                            </li>
                                        </x-ui.action-dropdown>
                                    </div>
                                </td>
                            </tr>
                            <tr id="structure-details-{{ $structure->id }}" class="table-light d-none">
                                <td colspan="4" class="p-3">
                                    <div class="bg-white border rounded-3 overflow-hidden shadow-sm">
                                        <table class="table table-sm table-hover align-middle mb-0 fs-12" style="width: 100%; table-layout: fixed;">
                                            <thead class="table-light text-uppercase fs-10 text-muted" style="letter-spacing: 0.5px;">
                                                <tr>
                                                    <th style="width: 38%;" class="ps-3 py-2.5">{{ __('hrms.salary.tbl_component_name') }}</th>
                                                    <th style="width: 26%;" class="py-2.5">{{ __('hrms.salary.tbl_type') }}</th>
                                                    <th style="width: 36%;" class="pe-3 py-2.5 text-end">{{ __('hrms.salary.tbl_value_rate') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($structure->items as $item)
                                                    @php
                                                        $calcTypeLabel = match($item->calculation_type) {
                                                            'fixed' => __('hrms.salary.fixed_amount'),
                                                            'percentage_of_ctc' => __('hrms.salary.of_ctc'),
                                                            'percentage_of_basic' => __('hrms.salary.of_basic'),
                                                            'balancing' => __('hrms.salary.balancing'),
                                                            default => $item->calculation_type
                                                        };
                                                    @endphp
                                                    <tr>
                                                        <td class="ps-3 py-2 fw-semibold text-dark" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">{{ $item->component->name }}</td>
                                                        <td class="py-2" style="white-space: nowrap;">
                                                            @if($item->component->type == 'earning')
                                                                <span class="badge bg-soft-success text-success px-2 py-1 fs-10 rounded-pill">{{ __('hrms.org.earning') }}</span>
                                                            @else
                                                                <span class="badge bg-soft-warning text-warning px-2 py-1 fs-10 rounded-pill">{{ __('hrms.org.deduction') }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="pe-3 py-2 text-end fw-bold text-dark" style="white-space: nowrap;">
                                                            @if($item->calculation_type == 'fixed')
                                                                ₹{{ number_format($item->value, 2) }} <span class="text-muted fw-normal fs-11">({{ __('hrms.salary.fixed_amount') }})</span>
                                                            @elseif(in_array($item->calculation_type, ['percentage_of_ctc', 'percentage_of_basic']))
                                                                {{ number_format($item->value, 2) }}% <span class="text-muted fw-normal fs-11">{{ $calcTypeLabel }}</span>
                                                            @else
                                                                <span class="text-muted fw-normal fs-11">{{ __('hrms.salary.balancing') }}</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-center py-3 text-muted fs-12">
                                                            {{ __('hrms.salary.no_components_configured') }}
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="feather-alert-circle fs-32 mb-2 d-block text-secondary"></i>
                                    {{ __('hrms.salary.no_structures_defined') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @php
                $currentPage = $salaryStructures->currentPage();
                $totalPages = $salaryStructures->lastPage();
                $totalResults = $salaryStructures->total();
                $perPage = $salaryStructures->perPage();
            @endphp
            @if($salaryStructures->hasPages())
                <div class="pt-3 border-top mt-3 struct-pagination-container">
                    <x-ui.pagination
                        class="px-0 py-0"
                        :current-page="$currentPage"
                        :total-pages="$totalPages"
                        :total-results="$totalResults"
                        :per-page="$perPage"
                        page-param="struct_page"
                    />
                </div>
            @endif
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- ADD SALARY STRUCTURE MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="addSalaryStructureModal" tabindex="-1" aria-labelledby="addSalaryStructureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addSalaryStructureModalLabel">{{ __('hrms.salary.create_slab') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.salary-structure.structure.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.salary.structure_slab_name') }}" name="name" placeholder="e.g. Slab 0 - 6 Lakhs" :required="true" :errorText="$errors->first('name')" />
                        </div>
                        <input type="hidden" name="company_id" id="add_structure_company_id">
                        <input type="hidden" name="pay_group_id" id="add_structure_pay_group_id" value="{{ $selectedPayGroup ? $selectedPayGroup->id : '' }}">
                        <div class="col-md-6 col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.salary.min_yearly_ctc_lbl') }}" name="min_ctc" inputType="number" placeholder="0" min="0" :required="true" :errorText="$errors->first('min_ctc')" />
                        </div>
                        <div class="col-md-6 col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.salary.max_yearly_ctc_lbl') }}" name="max_ctc" inputType="number" placeholder="600000" min="0" :required="true" :errorText="$errors->first('max_ctc')" />
                        </div>
                        <div class="col-md-12 col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Component Configuration Table -->
                    <div class="mt-4">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                            <h6 class="fw-bold mb-0">{{ __('hrms.salary.configure_rules') }}</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-semibold fs-11 px-2.5 py-1" onclick="addNewComponentRow('add')">
                                <i class="feather-plus me-1"></i>{{ __('hrms.salary.add_component') }}
                            </button>
                        </div>
                        <div class="border rounded bg-white" style="overflow: visible;">
                            <table class="table table-sm align-middle mb-0 w-100" style="font-size: 13px; table-layout: fixed;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 33%;">{{ __('hrms.org.component_name') }}</th>
                                        <th style="width: 17%;">{{ __('hrms.org.type') }}</th>
                                        <th style="width: 30%;">{{ __('hrms.salary.calculation_rule') }}</th>
                                        <th style="width: 20%;" class="text-end pe-3">{{ __('hrms.salary.rule_value') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="add_structure_components_tbody">
                                    @forelse($recurringComponentsForStructure as $comp)
                                        @php
                                             $cCode = strtoupper($comp->code);
                                             $cName = strtolower($comp->name);
                                             
                                             $isBasic = in_array($cCode, ['BASIC', 'BASIC_PAY']) || \Illuminate\Support\Str::contains($cName, 'basic');
                                             $isDefaultBalancing = !$isBasic && (
                                                 in_array($cCode, ['OTHER', 'OTHER_ALLOWANCE', 'SA', 'SPECIAL_ALLOWANCE', 'BALANCING']) || 
                                                 \Illuminate\Support\Str::contains($cName, ['other', 'special allowance', 'balancing', 'remainder'])
                                             );
                                        @endphp
                                        <tr>
                                            <td style="width: 33%;">
                                                <span class="fw-bold text-dark">{{ $comp->name }}</span>
                                                <code class="ms-1 text-muted" style="font-size: 11px;">{{ $comp->code }}</code>
                                            </td>
                                            <td style="width: 17%;">
                                                @if($comp->type == 'earning')
                                                    <span class="badge bg-soft-success text-success">{{ __('hrms.org.earning') }}</span>
                                                @else
                                                    <span class="badge bg-soft-warning text-warning">{{ __('hrms.org.deduction') }}</span>
                                                @endif
                                            </td>
                                            <td style="width: 30%; position: relative;">
                                                <select name="components[{{ $comp->id }}][calculation_type]"
                                                        class="odoo-table-select add-calc-type-select odoo-select2-table"
                                                        data-comp-id="{{ $comp->id }}"
                                                        id="add-calc-type-{{ $comp->id }}"
                                                        onchange="handleCalcTypeChange('add', {{ $comp->id }})">
                                                    <option value="not_included" @selected(!$isDefaultBalancing)>{{ __('hrms.salary.not_included') }}</option>
                                                    <option value="fixed">{{ __('hrms.salary.fixed_amount') }}</option>
                                                    <option value="percentage_of_ctc">{{ __('hrms.salary.percentage_of_ctc') }}</option>
                                                    <option value="percentage_of_basic">{{ __('hrms.salary.percentage_of_basic') }}</option>
                                                    <option value="balancing" @selected($isDefaultBalancing)>{{ __('hrms.salary.auto_calculated_balancing') }}</option>
                                                </select>
                                            </td>
                                            <td style="width: 20%;" class="pe-3">
                                                <x-ui.odoo-form-ui type="input"
                                                       inputType="number"
                                                       step="0.01"
                                                       name="components[{{ $comp->id }}][value]"
                                                       class="add-value-input text-end"
                                                       id="add-value-{{ $comp->id }}"
                                                       placeholder="0.00"
                                                       :disabled="true" />
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">{{ __('hrms.salary.no_components_found') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ __('hrms.salary.create_structure') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- EDIT SALARY STRUCTURE MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="editSalaryStructureModal" tabindex="-1" aria-labelledby="editSalaryStructureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editSalaryStructureModalLabel">{{ __('hrms.salary.edit_slab') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editSalaryStructureForm" method="POST" action="{{ old('edit_structure_id') ? route('hrms.salary-structure.structure.update', ['salaryStructure' => old('edit_structure_id')]) : '' }}">
                @csrf
                <input type="hidden" name="edit_structure_id" id="edit_structure_id" value="{{ old('edit_structure_id') }}">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.salary.structure_slab_name') }}" name="name" id="edit_name" :required="true" :errorText="$errors->first('name')" />
                        </div>
                        <input type="hidden" name="company_id" id="edit_company_id">
                        <input type="hidden" name="pay_group_id" id="edit_structure_pay_group_id">
                        <div class="col-md-6 col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.salary.min_yearly_ctc_lbl') }}" name="min_ctc" id="edit_min_ctc" inputType="number" :required="true" :errorText="$errors->first('min_ctc')" />
                        </div>
                        <div class="col-md-6 col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.salary.max_yearly_ctc_lbl') }}" name="max_ctc" id="edit_max_ctc" inputType="number" :required="true" :errorText="$errors->first('max_ctc')" />
                        </div>
                        <div class="col-md-12 col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Component Configuration Table -->
                    <div class="mt-4">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                            <h6 class="fw-bold mb-0">{{ __('hrms.salary.configure_rules') }}</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-semibold fs-11 px-2.5 py-1" onclick="addNewComponentRow('edit')">
                                <i class="feather-plus me-1"></i>{{ __('hrms.salary.add_component') }}
                            </button>
                        </div>
                        <div class="border rounded bg-white" style="overflow: visible;">
                            <table class="table table-sm align-middle mb-0 w-100" style="font-size: 13px; table-layout: fixed;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 33%;">{{ __('hrms.org.component_name') }}</th>
                                        <th style="width: 17%;">{{ __('hrms.org.type') }}</th>
                                        <th style="width: 30%;">{{ __('hrms.salary.calculation_rule') }}</th>
                                        <th style="width: 20%;" class="text-end pe-3">{{ __('hrms.salary.rule_value') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="edit_structure_components_tbody">
                                    @foreach($recurringComponentsForStructure as $comp)
                                        <tr>
                                            <td style="width: 33%;">
                                                <span class="fw-bold text-dark">{{ $comp->name }}</span>
                                                <code class="ms-1 text-muted" style="font-size: 11px;">{{ $comp->code }}</code>
                                            </td>
                                            <td style="width: 17%;">
                                                @if($comp->type == 'earning')
                                                    <span class="badge bg-soft-success text-success">{{ __('hrms.org.earning') }}</span>
                                                @else
                                                    <span class="badge bg-soft-warning text-warning">{{ __('hrms.org.deduction') }}</span>
                                                @endif
                                            </td>
                                            <td style="width: 30%; position: relative;">
                                                <select name="components[{{ $comp->id }}][calculation_type]"
                                                        class="odoo-table-select edit-calc-type-select odoo-select2-table"
                                                        data-comp-id="{{ $comp->id }}"
                                                        id="edit-calc-type-{{ $comp->id }}"
                                                        onchange="handleCalcTypeChange('edit', {{ $comp->id }})">
                                                    <option value="not_included">{{ __('hrms.salary.not_included') }}</option>
                                                    <option value="fixed">{{ __('hrms.salary.fixed_amount') }}</option>
                                                    <option value="percentage_of_ctc">{{ __('hrms.salary.percentage_of_ctc') }}</option>
                                                    <option value="percentage_of_basic">{{ __('hrms.salary.percentage_of_basic') }}</option>
                                                    <option value="balancing">{{ __('hrms.salary.auto_calculated_balancing') }}</option>
                                                </select>
                                            </td>
                                            <td style="width: 20%;" class="pe-3">
                                                <x-ui.odoo-form-ui type="input"
                                                       inputType="number"
                                                       step="0.01"
                                                       name="components[{{ $comp->id }}][value]"
                                                       class="edit-value-input text-end"
                                                       id="edit-value-{{ $comp->id }}"
                                                       placeholder="0.00"
                                                       :disabled="true" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ __('hrms.common.save_changes') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CTC CALCULATOR DRAWER -->
<x-ui.drawer id="ctcCalculatorDrawer" title="{{ __('hrms.salary.ctc_calculator_simulator') }}" position="end" :close-on-outside-click="true" style="width: 580px; max-width: 100%;">
    <style>
        #ctcCalculatorDrawer .offcanvas-body {
            overflow-x: hidden !important;
        }
    </style>
    <div class="mb-4">
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.salary.yearly_ctc') }}" name="sim_ctc" id="sim_ctc" placeholder="e.g. 600000" oninput="calculateSimulator()" onkeyup="calculateSimulator()" onchange="calculateSimulator()" />
        <small class="text-muted d-block mt-1" style="margin-left: 170px;">{{ __('hrms.salary.simulator_help') }}</small>
    </div>

    <div id="sim-error-msg" class="alert alert-soft-danger py-3 px-3 align-items-center" style="display: none;"></div>

    <div id="sim-results-card" style="display: none;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">{{ __('hrms.salary.matched_slab') }}</span>
            <span id="sim-slab-name" class="badge bg-soft-primary text-primary px-3 py-1 fw-bold fs-12"></span>
        </div>

        <div class="border rounded bg-white overflow-hidden">
            <table class="table table-sm table-hover mb-0 align-middle w-100" style="font-size: 13px; table-layout: fixed;">
                <colgroup>
                    <col style="width: 38%;">
                    <col style="width: 18%;">
                    <col style="width: 22%;">
                    <col style="width: 22%;">
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-2 text-start">{{ __('hrms.salary.component') }}</th>
                        <th class="py-2 text-start">{{ __('hrms.org.type') }}</th>
                        <th class="py-2 text-end">{{ __('hrms.salary.monthly') }}</th>
                        <th class="pe-3 py-2 text-end">{{ __('hrms.salary.yearly') }}</th>
                    </tr>
                </thead>
                <tbody id="sim-results-body">
                </tbody>
            </table>
        </div>
    </div>

    <x-slot name="footer">
        <x-ui.button variant="light" data-bs-dismiss="offcanvas">{{ __('hrms.salary.close_panel') }}</x-ui.button>
    </x-slot>
</x-ui.drawer>

@push('scripts')
<script>
    // Safe UTF-8 Base64 JSON decoding helper
    function safeBase64Decode(str) {
        if (!str) return null;
        try {
            return JSON.parse(decodeURIComponent(escape(atob(str))));
        } catch(e) {
            try {
                return JSON.parse(atob(str));
            } catch(e2) {
                console.error('Base64 decode error:', e2);
                return null;
            }
        }
    }

    let inlineComponentCounter = 0;

    // Initialize Select2 dropdowns bound directly to their parent table cells
    function initTableSelect2($container) {
        if (!$.fn.select2) return;
        $container.find('select.odoo-select2-table').each(function() {
            var $el = $(this);
            if ($el.data('select2')) {
                $el.select2('destroy');
            }
            $el.select2({
                theme: 'bootstrap-5',
                width: '100%',
                minimumResultsForSearch: Infinity,
                dropdownParent: $el.parent()
            });
        });
    }

    // Dynamically appends an editable new component row directly inside the structure table
    function addNewComponentRow(modalPrefix) {
        inlineComponentCounter++;
        const rowId = 'new_' + inlineComponentCounter;
        const tbodyId = modalPrefix === 'add' ? '#add_structure_components_tbody' : '#edit_structure_components_tbody';
        const tbody = $(tbodyId);

        // Remove empty state placeholder row if present
        tbody.find('tr:has(td[colspan])').remove();

        const newRowHtml = `
            <tr class="inline-component-row border-bottom" id="${modalPrefix}-row-${rowId}" style="background-color: #fbfcfe;">
                <td style="width: 33%;">
                    <div class="d-flex flex-column">
                        <input type="text" 
                               name="components[${rowId}][name]" 
                               class="odoo-table-input fw-semibold" 
                               placeholder="e.g. Special Allowance" 
                               required 
                               oninput="autoGenerateComponentCode(this, '${modalPrefix}', '${rowId}')">
                        <div class="d-flex align-items-center gap-1 mt-1">
                            <span class="text-muted" style="font-size: 10px; font-weight: 600;">CODE:</span>
                            <input type="text" 
                                   name="components[${rowId}][code]" 
                                   id="${modalPrefix}-code-${rowId}" 
                                   class="odoo-table-input text-uppercase py-0 px-1" 
                                   style="width: 80px; font-size: 11px; height: 18px;" 
                                   placeholder="CODE" 
                                   required 
                                   onchange="this.dataset.manuallyEdited='true'">
                        </div>
                    </div>
                </td>
                <td style="width: 17%; position: relative;">
                    <select name="components[${rowId}][type]" 
                            id="${modalPrefix}-type-${rowId}" 
                            class="odoo-table-select odoo-select2-table fw-medium">
                        <option value="earning">{{ __("hrms.org.earning") }} (+)</option>
                        <option value="deduction">{{ __("hrms.org.deduction") }} (-)</option>
                    </select>
                </td>
                <td style="width: 30%; position: relative;">
                    <select name="components[${rowId}][calculation_type]" 
                            class="odoo-table-select odoo-select2-table ${modalPrefix}-calc-type-select" 
                            data-comp-id="${rowId}" 
                            id="${modalPrefix}-calc-type-${rowId}" 
                            onchange="handleCalcTypeChange('${modalPrefix}', '${rowId}')">
                        <option value="fixed">{{ __("hrms.salary.fixed_amount") }}</option>
                        <option value="percentage_of_ctc">{{ __("hrms.salary.percentage_of_ctc") }}</option>
                        <option value="percentage_of_basic">{{ __("hrms.salary.percentage_of_basic") }}</option>
                        <option value="balancing">{{ __("hrms.salary.auto_calculated_balancing") }}</option>
                        <option value="not_included">{{ __("hrms.salary.not_included") }}</option>
                    </select>
                </td>
                <td style="width: 20%;" class="pe-3">
                    <div class="d-flex align-items-center justify-content-end gap-1">
                        <input type="number" 
                               step="0.01" 
                               name="components[${rowId}][value]" 
                               class="odoo-table-input text-end ${modalPrefix}-value-input" 
                               id="${modalPrefix}-value-${rowId}" 
                               placeholder="0.00" 
                               style="width: 75px;" 
                               required>
                        <button type="button" 
                                class="btn btn-icon btn-sm btn-soft-danger rounded-circle" 
                                title="{{ __('hrms.salary.remove_component') }}" 
                                style="width: 26px; height: 26px; min-width: 26px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"
                                onclick="$(this).closest('tr').remove();">
                            <i class="feather-trash-2 fs-12"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;

        tbody.append(newRowHtml);
        // Initialize Select2 on the newly added row
        initTableSelect2($(`#${modalPrefix}-row-${rowId}`));
        // Focus the component name input immediately
        $(`#${modalPrefix}-row-${rowId} input[name="components[${rowId}][name]"]`).focus();
    }

    // Auto-generates a clean uppercase alphanumeric code from the component name unless manually edited
    function autoGenerateComponentCode(nameInput, modalPrefix, rowId) {
        const codeInput = document.getElementById(`${modalPrefix}-code-${rowId}`);
        if (!codeInput || codeInput.dataset.manuallyEdited === 'true') return;

        const val = nameInput.value.trim();
        if (!val) {
            codeInput.value = '';
            return;
        }
        const cleaned = val.replace(/[^a-zA-Z0-9\s]/g, '').trim();
        const words = cleaned.split(/\s+/);
        let code = '';
        if (words.length > 1) {
            code = words.map(w => w[0]).join('').toUpperCase();
        } else {
            code = cleaned.substring(0, 6).toUpperCase();
        }
        codeInput.value = code;
    }

    // Enable/disable rule value fields depending on the selected calculation type
    function handleCalcTypeChange(prefix, compId) {
        let select = $(`#${prefix}-calc-type-${compId}`);
        let input = $(`#${prefix}-value-${compId}`);
        let val = select.val();

        if (val === 'not_included' || val === 'balancing') {
            input.val('').prop('disabled', true);
        } else {
            input.prop('disabled', false);
        }
    }

    // Modal populate edit scripts & detail toggles
    $(document).ready(function() {
        // Setup CTC calculator drawer events
        $('#ctcCalculatorDrawer').on('shown.bs.offcanvas', function () {
            $('#sim_ctc').trigger('focus');
            if ($('#sim_ctc').val() && $('#sim_ctc').val().trim() !== '') {
                calculateSimulator();
            }
        });

        $(document).on('input keyup change', '#sim_ctc', function() {
            calculateSimulator();
        });

        // Initialize Select2 for modal table selects when modal is shown
        $('#addSalaryStructureModal').on('shown.bs.modal', function() {
            initTableSelect2($(this));
        });
        $('#editSalaryStructureModal').on('shown.bs.modal', function() {
            initTableSelect2($(this));
        });

        // Ensure calculation type change enables/disables the value input
        $(document).on('change', '.add-calc-type-select', function() {
            let compId = $(this).data('comp-id');
            if (compId !== undefined) handleCalcTypeChange('add', compId);
        });
        $(document).on('change', '.edit-calc-type-select', function() {
            let compId = $(this).data('comp-id');
            if (compId !== undefined) handleCalcTypeChange('edit', compId);
        });

        // Toggle structure components details row
        $(document).on('click', '.toggle-structure-details', function(e) {
            e.preventDefault();
            e.stopPropagation();
            let btn = $(this).closest('.toggle-structure-details');
            let targetId = btn.attr('data-target');
            let targetRow = $(targetId);
            let icon = btn.find('i');
            
            if (targetRow.hasClass('d-none')) {
                targetRow.removeClass('d-none');
                icon.removeClass('feather-chevron-down').addClass('feather-chevron-up');
                btn.attr('title', '{{ __('hrms.salary.hide_components') }}');
            } else {
                targetRow.addClass('d-none');
                icon.removeClass('feather-chevron-up').addClass('feather-chevron-down');
                btn.attr('title', '{{ __('hrms.salary.show_components') }}');
            }
        });

        // Add structure pre-populates pay_group_id
        $(document).on('click', '.add-structure-trigger', function() {
            let pgId = $(this).attr('data-pay-group-id');
            $('#add_structure_pay_group_id').val(pgId);
            // Clean up any dynamic rows from previous add modal sessions
            $('#add_structure_components_tbody .inline-component-row').remove();
            // Reset existing selects
            $('.add-calc-type-select').val('not_included').trigger('change');
            $('.add-value-input').val('').prop('disabled', true);
        });

        // Use robust event delegation for the edit handler
        $(document).on('click', '.edit-structure-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Close any open action dropdown menu
            $('.dropdown-menu.show').removeClass('show');
            $('.dropdown.show').removeClass('show');

            let btn = $(this).closest('.edit-structure-btn');
            let dataStr = btn.attr('data-structure');
            if (!dataStr) return;

            // Decode structure data from base64 safely (handles UTF-8)
            let structure = safeBase64Decode(dataStr);
            if (!structure) return;
            
            let id = structure.id;
            let name = structure.name;
            let company_id = structure.company_id;
            let pay_group_id = structure.pay_group_id;
            let min_ctc = parseFloat(structure.min_ctc);
            let max_ctc = parseFloat(structure.max_ctc);
            let status = (structure.status === true || structure.status === 1 || structure.status === '1') ? '1' : '0';
            let items = structure.items;

            $('#editSalaryStructureForm').attr('action', `/hrms/salary-structure/structure/update/${id}`);
            $('#edit_structure_id').val(id);
            $('#edit_name').val(name);
            $('#edit_company_id').val(company_id || '');
            $('#edit_structure_pay_group_id').val(pay_group_id || '');
            $('#edit_min_ctc').val(min_ctc);
            $('#edit_max_ctc').val(max_ctc);
            $('#edit_status').val(status);

            // Clean up any dynamic rows from previous edit sessions
            $('#edit_structure_components_tbody .inline-component-row').remove();

            // Reset all selects to not_included and disable inputs
            $('.edit-calc-type-select').val('not_included').trigger('change');
            $('.edit-value-input').val('').prop('disabled', true);

            // Populate rules from items database
            if (items && Array.isArray(items)) {
                items.forEach(function(item) {
                    let select = $(`#edit-calc-type-${item.salary_component_id}`);
                    let input = $(`#edit-value-${item.salary_component_id}`);
                    
                    select.val(item.calculation_type).trigger('change');
                    if (item.calculation_type !== 'not_included' && item.calculation_type !== 'balancing') {
                        input.val(parseFloat(item.value)).prop('disabled', false);
                    }
                });
            }

            // Open the modal programmatically
            $('#editSalaryStructureModal').modal('show');
        });

        // AJAX-based search, sort, filter and pagination for Salary Structures
        function loadStructures(page = 1) {
            var payGroupId = '{{ $selectedPayGroup ? $selectedPayGroup->id : "" }}';
            var search = $('#struct_search_input').val() || '';
            var sort = $('#struct_sort_value').val() || 'name_asc';
            var status = $('#struct_status_value').val() || '';
            
            var url = '{{ route("hrms.salary-structure.index") }}?pay_group_id=' + payGroupId + 
                      '&tab=structures&struct_search=' + encodeURIComponent(search) + 
                      '&struct_sort=' + encodeURIComponent(sort) + 
                      '&struct_status=' + encodeURIComponent(status) + 
                      '&struct_page=' + page;
                      
            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(response, 'text/html');
                    
                    // Update table
                    var oldTable = $('#salaryStructuresTable');
                    var newTable = $(doc).find('#salaryStructuresTable');
                    if (newTable.length && oldTable.length) {
                        oldTable.html(newTable.html());
                    }
                    
                    // Update pagination
                    var oldPagination = $('.struct-pagination-container');
                    var newPagination = $(doc).find('.struct-pagination-container');
                    if (newPagination.length && oldPagination.length) {
                        oldPagination.replaceWith(newPagination);
                    } else if (newPagination.length) {
                        $('#salaryStructuresTable').parent().after(newPagination);
                    } else {
                        oldPagination.empty();
                    }

                    // Push state to sync browser URL with current pagination & filter params
                    if (window.history.pushState) {
                        window.history.pushState({path: url}, '', url);
                    }
                }
            });
        }

        let structSearchTimeout = null;
        $(document).on('input', '#struct_search_input', function() {
            clearTimeout(structSearchTimeout);
            structSearchTimeout = setTimeout(function() {
                loadStructures(1);
            }, 300);
        });

        window.changeStructSort = function(criteria, element) {
            var input = document.getElementById('struct_sort_value');
            if (input) {
                input.value = criteria;
            }

            if (element) {
                var menu = element.closest('.dropdown-menu');
                if (menu) {
                    menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                        el.classList.remove('active');
                        var check = el.querySelector('.feather-check');
                        if (check) check.remove();
                    });
                }
                element.classList.add('active');
                $(element).append('<i class="feather-check ms-3"></i>');
            }

            loadStructures(1);
        };

        window.applyStructFilter = function() {
            var statusVal = $('#struct_filter_status').val() || '';
            var input = document.getElementById('struct_status_value');
            if (input) {
                input.value = statusVal;
            }

            loadStructures(1);
            $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
            $('.erp-filter-dropdown.show').removeClass('show');
        };

        window.resetStructFilters = function() {
            $('#struct_filter_status').val('').trigger('change');
            var input = document.getElementById('struct_status_value');
            if (input) {
                input.value = '';
            }
            $('#struct_search_input').val('');

            loadStructures(1);
            $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
            $('.erp-filter-dropdown.show').removeClass('show');
        };

        $(document).on('click', '.struct-pagination-container a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (!url) return;
            var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
            var page = urlParams.get('struct_page') || 1;
            loadStructures(page);
        });
    });

    // Real-time client-side calculator
    function calculateSimulator() {
        let rawVal = $('#sim_ctc').val();
        if (rawVal === undefined || rawVal === null || rawVal.trim() === '') {
            $('#sim-error-msg').hide();
            $('#sim-results-card').hide();
            return;
        }

        let ctc = parseFloat(rawVal);
        if (isNaN(ctc) || ctc <= 0) {
            $('#sim-error-msg').html('<i class="feather-alert-circle me-2"></i>{{ __('hrms.salary.enter_valid_ctc') }}').show();
            $('#sim-results-card').hide();
            return;
        }

        let structures = @json($allStructuresForSimulator ?? collect());
        if (!Array.isArray(structures)) {
            structures = Object.values(structures);
        }

        let matched = null;

        // Loop through structures and check matching min_ctc <= CTC <= max_ctc
        for (let s of structures) {
            let min = parseFloat(s.min_ctc) || 0;
            let max = parseFloat(s.max_ctc) || 0;
            let isActive = s.status == 1 || s.status === true || s.status === '1';
            if (ctc >= min && ctc <= max && isActive) {
                matched = s;
                break;
            }
        }

        if (!matched) {
            $('#sim-error-msg').html(`<i class="feather-alert-triangle me-2"></i>{{ __('hrms.salary.no_slab_match') }} <strong>₹${ctc.toLocaleString('en-IN')}</strong>.`).show();
            $('#sim-results-card').hide();
            return;
        }

        $('#sim-error-msg').hide();

        let basicYearly = 0;
        let totalAllocatedYearly = 0;
        let rows = [];

        // Sort items by evaluation sort_order
        let items = (matched.items || []).slice().sort((a, b) => (parseInt(a.sort_order) || 0) - (parseInt(b.sort_order) || 0));

        // 1. Calculate Basic first if exists and is not balancing
        for (let item of items) {
            if (item.calculation_type === 'balancing' || item.calculation_type === 'not_included') continue;
            let code = item.component ? (item.component.code || '').toLowerCase() : '';
            let name = item.component ? (item.component.name || '').toLowerCase() : '';
            if (code === 'basic' || name === 'basic' || name === 'basic salary') {
                let itemVal = parseFloat(item.value) || 0;
                if (item.calculation_type === 'fixed') {
                    basicYearly = itemVal;
                } else if (item.calculation_type === 'percentage_of_ctc') {
                    basicYearly = (itemVal / 100) * ctc;
                }
                break;
            }
        }

        // 2. First pass: Calculate all non-balancing, included items
        for (let item of items) {
            if (item.calculation_type === 'balancing' || item.calculation_type === 'not_included') continue;

            let valYearly = 0;
            let ruleText = '';
            let itemVal = parseFloat(item.value) || 0;

            if (item.calculation_type === 'fixed') {
                valYearly = itemVal;
                ruleText = `{{ __('hrms.salary.fixed_amount') }}: ₹${valYearly.toLocaleString('en-IN')}`;
            } else if (item.calculation_type === 'percentage_of_ctc') {
                valYearly = (itemVal / 100) * ctc;
                ruleText = `${itemVal}% {{ __('hrms.salary.of_ctc') }}`;
            } else if (item.calculation_type === 'percentage_of_basic') {
                valYearly = (itemVal / 100) * basicYearly;
                ruleText = `${itemVal}% {{ __('hrms.salary.of_basic') }}`;
            }

            let compName = item.component ? item.component.name : ('Component #' + item.salary_component_id);
            let compCode = item.component ? item.component.code : '';
            let compType = item.component ? item.component.type : 'earning';

            if (compCode && compCode.toLowerCase() === 'basic') {
                basicYearly = valYearly;
            }

            totalAllocatedYearly += valYearly;

            rows.push({
                name: compName,
                code: compCode,
                type: compType,
                rule: ruleText,
                yearly: valYearly,
                monthly: valYearly / 12
            });
        }

        // 3. Second pass: Calculate balancing item
        for (let item of items) {
            if (item.calculation_type !== 'balancing' && item.calculation_type !== 'not_included') continue;
            if (item.calculation_type === 'not_included') continue;

            let valYearly = Math.max(0, ctc - totalAllocatedYearly);
            let compName = item.component ? item.component.name : ('Component #' + item.salary_component_id);
            let compCode = item.component ? item.component.code : '';
            let compType = item.component ? item.component.type : 'earning';

            rows.push({
                name: compName,
                code: compCode,
                type: compType,
                rule: '{{ __('hrms.salary.remaining_balance') }}',
                yearly: valYearly,
                monthly: valYearly / 12
            });
        }

        // Render rows
        let tbody = $('#sim-results-body');
        tbody.empty();

        rows.forEach((r) => {
            let typeBadge = r.type === 'earning' 
                ? '<span class="badge bg-soft-success text-success">{{ __('hrms.org.earning') }}</span>' 
                : '<span class="badge bg-soft-danger text-danger">{{ __('hrms.org.deduction') }}</span>';

            tbody.append(`
                <tr>
                    <td class="ps-3 py-2">
                        <div class="fw-bold text-dark text-truncate" title="${r.name}">${r.name}</div>
                        ${r.code ? `<code style="font-size: 11px;">${r.code}</code>` : ''}
                    </td>
                    <td class="py-2">${typeBadge}</td>
                    <td class="py-2 text-end fw-semibold text-dark">₹${(r.monthly).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td class="pe-3 py-2 text-end fw-semibold text-dark">₹${(r.yearly).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                </tr>
            `);
        });

        let grossYearly = 0;
        let grossMonthly = 0;
        let deductionsYearly = 0;
        let deductionsMonthly = 0;

        rows.forEach(r => {
            if (r.type === 'earning') {
                grossYearly += r.yearly;
                grossMonthly += r.monthly;
            } else if (r.type === 'deduction') {
                deductionsYearly += r.yearly;
                deductionsMonthly += r.monthly;
            }
        });

        let netYearly = grossYearly - deductionsYearly;
        let netMonthly = grossMonthly - deductionsMonthly;

        // Add summary rows
        tbody.append(`
            <tr class="table-light fw-bold border-top">
                <td colspan="2" class="ps-3 py-2 text-dark">{{ __('hrms.salary.gross_salary_ctc') }}</td>
                <td class="py-2 text-end text-success">₹${grossMonthly.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="pe-3 py-2 text-end text-success">₹${grossYearly.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            </tr>
            <tr class="table-light fw-bold">
                <td colspan="2" class="ps-3 py-2 text-dark">{{ __('hrms.salary.total_deductions') }}</td>
                <td class="py-2 text-end text-danger">₹${deductionsMonthly.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="pe-3 py-2 text-end text-danger">₹${deductionsYearly.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            </tr>
            <tr class="fw-bold border-top border-bottom" style="background-color: rgba(30, 64, 175, 0.08) !important;">
                <td colspan="2" class="ps-3 py-2"><span class="text-primary">{{ __('hrms.salary.net_salary') }}</span></td>
                <td class="py-2 text-end text-primary">₹${netMonthly.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="pe-3 py-2 text-end text-primary">₹${netYearly.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            </tr>
        `);

        $('#sim-slab-name').text(matched.name);
        $('#sim-results-card').fadeIn();
    }

</script>
@endpush
