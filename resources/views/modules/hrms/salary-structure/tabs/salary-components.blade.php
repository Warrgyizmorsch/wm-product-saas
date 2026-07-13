@php
    $selectedPayGroup = $selectedPayGroup ?? null;
    $salaryComponents = $salaryComponents ?? collect();
    $recurringComponents = $recurringComponents ?? $salaryComponents->filter(fn ($component) => !($component->is_adhoc ?? false));
    $adhocComponents = $adhocComponents ?? $salaryComponents->filter(fn ($component) => (bool) ($component->is_adhoc ?? false));
@endphp

<style>
    #componentSubTabs .nav-link {
        border: 1px solid #e2e8f0;
        background-color: #fff;
        color: #64748b;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 6px;
        transition: all 0.2s ease-in-out;
    }
    #componentSubTabs .nav-link:hover {
        color: var(--bs-primary);
        background-color: #f8fafc;
        border-color: #cbd5e1;
    }
    #componentSubTabs .nav-link.active {
        color: #fff !important;
        background-color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
    }
</style>

<ul class="nav nav-pills gap-2 border-bottom pb-3 mb-4" id="componentSubTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ request()->get('subtab', 'recurring') === 'recurring' ? 'active' : '' }} px-4 py-2" id="recurring-subtab" data-bs-toggle="tab" data-bs-target="#recurring-pane" type="button" role="tab" aria-controls="recurring-pane" aria-selected="true">
            Recurring Components
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ request()->get('subtab') === 'adhoc' ? 'active' : '' }} px-4 py-2" id="adhoc-subtab" data-bs-toggle="tab" data-bs-target="#adhoc-pane" type="button" role="tab" aria-controls="adhoc-pane" aria-selected="false">
            Ad-hoc Components
        </button>
    </li>
</ul>

<div class="tab-content" id="componentSubTabsContent">
    <!-- RECURRING COMPONENTS -->
    <div class="tab-pane fade {{ request()->get('subtab', 'recurring') === 'recurring' ? 'show active' : '' }}" id="recurring-pane" role="tabpanel" aria-labelledby="recurring-subtab">
        <div class="row">
            <div class="col-12">
                <x-ui.card title="Recurring Components (Fixed CTC)" stretch bodyClass="p-0">
                    <x-slot name="headerAction">
                        <x-ui.button variant="primary" size="sm" icon="feather-plus" class="add-component-trigger" data-pay-group-id="{{ $selectedPayGroup ? $selectedPayGroup->id : '' }}" data-is-adhoc="0" data-bs-toggle="modal" data-bs-target="#addSalaryComponentModal">
                            Add Component
                        </x-ui.button>
                    </x-slot>

                    <div class="px-4 py-3 border-bottom bg-white d-flex align-items-center justify-content-end gap-2 flex-wrap">
                        <!-- Search Input (Placed before sort and filter in same line) -->
                        <div class="theme-search-container" style="max-width: 300px;">
                            <form method="GET" action="{{ route('hrms.salary-structure.index') }}">
                                <input type="hidden" name="pay_group_id" value="{{ $selectedPayGroup ? $selectedPayGroup->id : '' }}">
                                <input type="hidden" name="tab" value="components">
                                <input type="hidden" name="subtab" value="recurring">
                                <input type="hidden" name="rec_status" value="{{ request('rec_status') }}">
                                <input type="hidden" name="rec_type" value="{{ request('rec_type') }}">
                                <input type="hidden" name="rec_sort" value="{{ request('rec_sort') }}">
                                <i class="feather-search"></i>
                                <input type="text" name="rec_search" class="theme-search-input" placeholder="Search components..." value="{{ request('rec_search') }}">
                            </form>
                        </div>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown label="SORT">
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('rec_sort') === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['rec_sort' => 'name_asc']) }}">
                                <span>Name (A-Z)</span>
                                @if(request('rec_sort') === 'name_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('rec_sort') === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['rec_sort' => 'name_desc']) }}">
                                <span>Name (Z-A)</span>
                                @if(request('rec_sort') === 'name_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('rec_sort') === 'code_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['rec_sort' => 'code_asc']) }}">
                                <span>Code (A-Z)</span>
                                @if(request('rec_sort') === 'code_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('rec_sort') === 'code_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['rec_sort' => 'code_desc']) }}">
                                <span>Code (Z-A)</span>
                                @if(request('rec_sort') === 'code_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter label="FILTER">
                            <form method="GET" action="{{ route('hrms.salary-structure.index') }}" class="theme-filter-form">
                                <input type="hidden" name="pay_group_id" value="{{ $selectedPayGroup ? $selectedPayGroup->id : '' }}">
                                <input type="hidden" name="tab" value="components">
                                <input type="hidden" name="subtab" value="recurring">
                                <input type="hidden" name="rec_search" value="{{ request('rec_search') }}">
                                <input type="hidden" name="rec_sort" value="{{ request('rec_sort') }}">
                                
                                <h6 class="theme-filter-heading">Status</h6>
                                <div class="theme-filter-radio-group">
                                    <div class="theme-filter-radio-item">
                                        <input type="radio" name="rec_status" id="recStatusAll" value="" @checked(!request()->filled('rec_status'))>
                                        <label for="recStatusAll">All Statuses</label>
                                    </div>
                                    <div class="theme-filter-radio-item">
                                        <input type="radio" name="rec_status" id="recStatusActive" value="1" @checked(request('rec_status') === '1')>
                                        <label for="recStatusActive">Active Only</label>
                                    </div>
                                    <div class="theme-filter-radio-item">
                                        <input type="radio" name="rec_status" id="recStatusInactive" value="0" @checked(request('rec_status') === '0')>
                                        <label for="recStatusInactive">Inactive Only</label>
                                    </div>
                                </div>

                                <h6 class="theme-filter-heading">Type</h6>
                                <select name="rec_type" class="theme-filter-select">
                                    <option value="">All Types</option>
                                    <option value="earning" @selected(request('rec_type') === 'earning')>Earning</option>
                                    <option value="deduction" @selected(request('rec_type') === 'deduction')>Deduction</option>
                                </select>
                            </form>
                        </x-ui.filter>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th width="60">#</th>
                                    <th>Component Name</th>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th width="150" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recurringComponents as $sc)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><span class="fw-bold text-dark">{{ $sc->name }}</span></td>
                                    <td><code>{{ $sc->code }}</code></td>
                                    <td>
                                        @if($sc->type == 'earning')
                                            <x-ui.badge variant="success" soft>Earning</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="warning" soft>Deduction</x-ui.badge>
                                        @endif
                                    </td>
                                    <td>
                                        @if($sc->status)
                                            <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form action="{{ request()->routeIs('hrms.salary-structure.index') ? route('hrms.salary-structure.destroy', ['salaryComponent' => $sc->id]) : route('hrms.salary-component.destroy', ['salaryComponent' => $sc->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this salary component?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.action-dropdown>
                                                <li>
                                                    <a class="dropdown-item btn-edit-salary-component" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editSalaryComponentModal" data-component="{{ base64_encode($sc->toJson()) }}">
                                                        <i class="feather feather-edit-3 me-3"></i>
                                                        <span>Edit</span>
                                                    </a>
                                                </li>
                                                <li class="dropdown-divider"></li>
                                                <li>
                                                    <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                        <i class="feather feather-trash-2 me-3"></i>
                                                        <span>Delete</span>
                                                    </button>
                                                </li>
                                            </x-ui.action-dropdown>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        No Recurring Components configured yet. Click "Add Component" to configure.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>

    <!-- AD-HOC COMPONENTS -->
    <div class="tab-pane fade {{ request()->get('subtab') === 'adhoc' ? 'show active' : '' }}" id="adhoc-pane" role="tabpanel" aria-labelledby="adhoc-subtab">
        <div class="row">
            <div class="col-12">
                <x-ui.card title="Ad-hoc Components (Variable / One-time)" stretch bodyClass="p-0">
                    <x-slot name="headerAction">
                        <x-ui.button variant="primary" size="sm" icon="feather-plus" class="add-component-trigger" data-pay-group-id="{{ $selectedPayGroup ? $selectedPayGroup->id : '' }}" data-is-adhoc="1" data-bs-toggle="modal" data-bs-target="#addSalaryComponentModal">
                            Add Component
                        </x-ui.button>
                    </x-slot>

                    <div class="px-4 py-3 border-bottom bg-white d-flex align-items-center justify-content-end gap-2 flex-wrap">
                        <!-- Search Input (Placed before sort and filter in same line) -->
                        <div class="theme-search-container" style="max-width: 300px;">
                            <form method="GET" action="{{ route('hrms.salary-structure.index') }}">
                                <input type="hidden" name="pay_group_id" value="{{ $selectedPayGroup ? $selectedPayGroup->id : '' }}">
                                <input type="hidden" name="tab" value="components">
                                <input type="hidden" name="subtab" value="adhoc">
                                <input type="hidden" name="adhoc_status" value="{{ request('adhoc_status') }}">
                                <input type="hidden" name="adhoc_type" value="{{ request('adhoc_type') }}">
                                <input type="hidden" name="adhoc_sort" value="{{ request('adhoc_sort') }}">
                                <i class="feather-search"></i>
                                <input type="text" name="adhoc_search" class="theme-search-input" placeholder="Search components..." value="{{ request('adhoc_search') }}">
                            </form>
                        </div>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown label="SORT">
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('adhoc_sort') === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['adhoc_sort' => 'name_asc']) }}">
                                <span>Name (A-Z)</span>
                                @if(request('adhoc_sort') === 'name_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('adhoc_sort') === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['adhoc_sort' => 'name_desc']) }}">
                                <span>Name (Z-A)</span>
                                @if(request('adhoc_sort') === 'name_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('adhoc_sort') === 'code_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['adhoc_sort' => 'code_asc']) }}">
                                <span>Code (A-Z)</span>
                                @if(request('adhoc_sort') === 'code_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('adhoc_sort') === 'code_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['adhoc_sort' => 'code_desc']) }}">
                                <span>Code (Z-A)</span>
                                @if(request('adhoc_sort') === 'code_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter label="FILTER">
                            <form method="GET" action="{{ route('hrms.salary-structure.index') }}" class="theme-filter-form">
                                <input type="hidden" name="pay_group_id" value="{{ $selectedPayGroup ? $selectedPayGroup->id : '' }}">
                                <input type="hidden" name="tab" value="components">
                                <input type="hidden" name="subtab" value="adhoc">
                                <input type="hidden" name="adhoc_search" value="{{ request('adhoc_search') }}">
                                <input type="hidden" name="adhoc_sort" value="{{ request('adhoc_sort') }}">
                                
                                <h6 class="theme-filter-heading">Status</h6>
                                <div class="theme-filter-radio-group">
                                    <div class="theme-filter-radio-item">
                                        <input type="radio" name="adhoc_status" id="adhocStatusAll" value="" @checked(!request()->filled('adhoc_status'))>
                                        <label for="adhocStatusAll">All Statuses</label>
                                    </div>
                                    <div class="theme-filter-radio-item">
                                        <input type="radio" name="adhoc_status" id="adhocStatusActive" value="1" @checked(request('adhoc_status') === '1')>
                                        <label for="adhocStatusActive">Active Only</label>
                                    </div>
                                    <div class="theme-filter-radio-item">
                                        <input type="radio" name="adhoc_status" id="adhocStatusInactive" value="0" @checked(request('adhoc_status') === '0')>
                                        <label for="adhocStatusInactive">Inactive Only</label>
                                    </div>
                                </div>

                                <h6 class="theme-filter-heading">Type</h6>
                                <select name="adhoc_type" class="theme-filter-select">
                                    <option value="">All Types</option>
                                    <option value="earning" @selected(request('adhoc_type') === 'earning')>Earning</option>
                                    <option value="deduction" @selected(request('adhoc_type') === 'deduction')>Deduction</option>
                                </select>
                            </form>
                        </x-ui.filter>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th width="60">#</th>
                                    <th>Component Name</th>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th width="150" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($adhocComponents as $sc)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><span class="fw-bold text-dark">{{ $sc->name }}</span></td>
                                    <td><code>{{ $sc->code }}</code></td>
                                    <td>
                                        @if($sc->type == 'earning')
                                            <x-ui.badge variant="success" soft>Earning</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="warning" soft>Deduction</x-ui.badge>
                                        @endif
                                    </td>
                                    <td>
                                        @if($sc->status)
                                            <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form action="{{ request()->routeIs('hrms.salary-structure.index') ? route('hrms.salary-structure.destroy', ['salaryComponent' => $sc->id]) : route('hrms.salary-component.destroy', ['salaryComponent' => $sc->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this salary component?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.action-dropdown>
                                                <li>
                                                    <a class="dropdown-item btn-edit-salary-component" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editSalaryComponentModal" data-component="{{ base64_encode($sc->toJson()) }}">
                                                        <i class="feather feather-edit-3 me-3"></i>
                                                        <span>Edit</span>
                                                    </a>
                                                </li>
                                                <li class="dropdown-divider"></li>
                                                <li>
                                                    <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                        <i class="feather feather-trash-2 me-3"></i>
                                                        <span>Delete</span>
                                                    </button>
                                                </li>
                                            </x-ui.action-dropdown>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        No Ad-hoc Components configured yet. Click "Add Component" to configure.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Track component subtab in URL parameter
        $('#componentSubTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            const subtabId = e.target.id.replace('-subtab', '');
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('subtab', subtabId);
            const newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + urlParams.toString();
            window.history.pushState({path:newurl}, '', newurl);
        });

        // Add Action Trigger to pre-populate pay_group_id and is_adhoc
        $(document).on('click', '.add-component-trigger', function() {
            let pgId = $(this).attr('data-pay-group-id');
            let isAdhocVal = $(this).attr('data-is-adhoc') || '0';
            
            let inputPayGroup = document.getElementById('add_component_pay_group_id');
            if (inputPayGroup) inputPayGroup.value = pgId;

            let inputAdhoc = document.getElementById('add_component_is_adhoc');
            if (inputAdhoc) inputAdhoc.value = isAdhocVal;
        });

        // Edit Action Trigger for Salary Components
        document.querySelectorAll('.btn-edit-salary-component').forEach(btn => {
            btn.addEventListener('click', function() {
                // Decode component data
                let component = JSON.parse(atob(this.dataset.component));
                
                // Populate input fields in the Edit modal
                document.getElementById('edit_sc_name').value = component.name || '';
                document.getElementById('edit_sc_code').value = component.code || '';
                document.getElementById('edit_sc_type').value = component.type || 'earning';
                document.getElementById('edit_sc_calculation_type').value = component.calculation_type || 'fixed';
                document.getElementById('edit_sc_company_id').value = component.company_id || '';
                document.getElementById('edit_sc_pay_group_id').value = component.pay_group_id || '';
                document.getElementById('edit_sc_is_adhoc').value = component.is_adhoc ? '1' : '0';
                document.getElementById('edit_sc_description').value = component.description || '';
                
                // Populate status select dropdown
                let statusSelect = document.getElementById('edit_sc_status');
                if (statusSelect) {
                    statusSelect.value = (component.status === true || component.status === 1 || component.status === '1') ? '1' : '0';
                }
                
                // Trigger Change event on all select elements to notify Select2 to refresh its displayed value
                $('#editSalaryComponentModal select').trigger('change');
                
                // Update form action URL to target this specific component id on the correct route
                let form = document.getElementById('salary_component_edit_form');
                if (form) {
                    form.action = form.dataset.updateRoute.replace('__ID__', component.id);
                }
            });
        });
    });
</script>
