@extends('layouts.duralux')

@section('title', 'Asset Categories | SaaS ERP')
@section('page-title', 'Asset Categories')
@section('breadcrumb', 'Accounting / Fixed Assets / Categories')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('accounting.fixed-assets.index') }}" variant="light" icon="feather-list" class="border">
            Asset Register
        </x-ui.button>
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            Add Category
        </x-ui.button>
    </div>
    <x-ui.filter label="Filters">
        <form method="GET">
            <x-ui.input label="Search" name="search" :value="request('search')" placeholder="Category name or description..." />
            <x-ui.select label="Company" name="company_id" :selected="request('company_id')" :options="
                ['' => 'All Companies'] + $companies->pluck('company_name', 'id')->all()
            " />
            <div class="d-flex gap-2">
                <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">Apply</x-ui.button>
                <x-ui.button href="{{ route('accounting.fixed-assets.categories.index') }}" variant="light" size="sm" class="border flex-grow-1">Reset</x-ui.button>
            </div>
        </form>
    </x-ui.filter>
@endsection

@section('content')
    <x-ui.card bodyClass="p-0">
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <form method="GET" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 280px; max-width: 360px;">
                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control border-0 bg-transparent p-0 fs-13"
                       placeholder="Search category name or description..." style="box-shadow: none; height: 32px;">
                @if (!empty(request('search')))
                    <a href="{{ route('accounting.fixed-assets.categories.index', collect(request()->query())->except('search')->filter()->all()) }}" class="text-muted ms-2" title="Clear search">
                        <i class="feather-x" style="font-size: 14px;"></i>
                    </a>
                @endif
                @if (!empty(request('company_id')))
                    <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                @endif
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-4">Category</th>
                        <th>Company</th>
                        <th>Fixed Asset A/C</th>
                        <th>Accum. Dep. A/C</th>
                        <th>Dep. Expense A/C</th>
                        <th class="text-end">Assets</th>
                        <th>Type</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @forelse($categories as $category)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold">{{ $category->name }}</div>
                                @if($category->description)
                                    <div class="text-muted fs-11">{{ $category->description }}</div>
                                @endif
                            </td>
                            <td class="text-muted">{{ $category->company->company_name ?? '—' }}</td>
                            <td class="text-muted fs-12">{{ $category->chartOfAccount ? $category->chartOfAccount->code . ' - ' . $category->chartOfAccount->name : '—' }}</td>
                            <td class="text-muted fs-12">{{ $category->accumulatedDepreciationAccount ? $category->accumulatedDepreciationAccount->code . ' - ' . $category->accumulatedDepreciationAccount->name : '—' }}</td>
                            <td class="text-muted fs-12">{{ $category->depreciationExpenseAccount ? $category->depreciationExpenseAccount->code . ' - ' . $category->depreciationExpenseAccount->name : '—' }}</td>
                            <td class="text-end">
                                <span class="badge bg-soft-primary text-primary rounded-pill">{{ $category->assets()->count() }}</span>
                            </td>
                            <td>
                                @if($category->is_production_machinery)
                                    <span class="badge bg-soft-info text-info rounded-pill px-2 py-1"><i class="feather-tool fs-10 me-1"></i>Machinery</span>
                                @else
                                    <span class="text-muted fs-11">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown>
                                    <li>
                                        <a class="dropdown-item edit-category-btn" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                            data-action="{{ route('accounting.fixed-assets.categories.update', $category->id) }}"
                                            data-company-id="{{ $category->company_id }}"
                                            data-name="{{ $category->name }}"
                                            data-description="{{ $category->description }}"
                                            data-fixed-asset-account-id="{{ $category->fixed_asset_account_id }}"
                                            data-accumulated-depreciation-account-id="{{ $category->accumulated_depreciation_account_id }}"
                                            data-depreciation-expense-account-id="{{ $category->depreciation_expense_account_id }}"
                                            data-default-depreciation-method="{{ $category->default_depreciation_method }}"
                                            data-default-useful-life-months="{{ $category->default_useful_life_months }}"
                                            data-is-production-machinery="{{ $category->is_production_machinery ? '1' : '0' }}">
                                            <i class="feather-edit me-2 text-muted fs-12"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <form action="{{ route('accounting.fixed-assets.categories.destroy', $category->id) }}" method="POST"
                                              onsubmit="return confirm('Delete this asset category? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-sliders fs-1 mb-2 d-block"></i>
                                No asset categories yet. Add one to start capitalizing assets.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-ui.pagination
            :currentPage="$categories->currentPage()"
            :totalPages="$categories->lastPage()"
            :totalResults="$categories->total()"
            :perPage="$categories->perPage()" />
    </x-ui.card>

    <!-- MODAL: ADD CATEGORY -->
    <x-ui.modal id="addCategoryModal" title="<i class='feather-sliders me-2 text-primary'></i>Add Asset Category" size="lg" centered :showFooter="false">
        <form action="{{ route('accounting.fixed-assets.categories.store') }}" method="POST">
            @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Company" name="company_id" :required="true" :options="['' => 'Select company...'] + $companies->pluck('company_name', 'id')->all()" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.icon-input label="Category Name" icon="feather-tag" name="name" placeholder="e.g. IT Hardware, Plant Machinery" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Brief details about what items go into this category..." />
                            </div>
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Fixed Asset Account" name="fixed_asset_account_id" :options="['' => 'Use default (1500)'] + $chartOfAccounts->mapWithKeys(fn ($acc) => [$acc->id => $acc->code . ' - ' . $acc->name])->all()" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Accumulated Depreciation Account" name="accumulated_depreciation_account_id" :options="['' => 'Use default (1510)'] + $chartOfAccounts->mapWithKeys(fn ($acc) => [$acc->id => $acc->code . ' - ' . $acc->name])->all()" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Depreciation Expense Account" name="depreciation_expense_account_id" :options="['' => 'Use default (5800)'] + $chartOfAccounts->mapWithKeys(fn ($acc) => [$acc->id => $acc->code . ' - ' . $acc->name])->all()" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Default Depreciation Method" name="default_depreciation_method" :options="[
                                    '' => 'Straight Line (default)',
                                    'straight_line' => 'Straight Line',
                                    'wdv' => 'Written Down Value (WDV)',
                                ]" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.icon-input label="Default Useful Life (months)" icon="feather-clock" type="number" name="default_useful_life_months" placeholder="e.g. 36" />
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_production_machinery" value="1" id="add_category_is_production_machinery">
                                    <label class="form-check-label fs-12" for="add_category_is_production_machinery">
                                        Production machinery — purchases in this category will be surfaced in Production for machine registration
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="submit" class="btn btn-primary px-4">Add Category</button>
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    </div>
        </form>
    </x-ui.modal>

    <!-- MODAL: EDIT CATEGORY -->
    <x-ui.modal id="editCategoryModal" title="<i class='feather-sliders me-2 text-primary'></i>Edit Asset Category" size="lg" centered :showFooter="false">
        <form id="editCategoryForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Company" name="company_id" id="edit_category_company_id" :required="true" :options="['' => 'Select company...'] + $companies->pluck('company_name', 'id')->all()" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.icon-input label="Category Name" icon="feather-tag" name="name" id="edit_category_name" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_category_description" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Fixed Asset Account" name="fixed_asset_account_id" id="edit_category_fixed_asset_account_id" :options="['' => 'Use default (1500)'] + $chartOfAccounts->mapWithKeys(fn ($acc) => [$acc->id => $acc->code . ' - ' . $acc->name])->all()" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Accumulated Depreciation Account" name="accumulated_depreciation_account_id" id="edit_category_accumulated_depreciation_account_id" :options="['' => 'Use default (1510)'] + $chartOfAccounts->mapWithKeys(fn ($acc) => [$acc->id => $acc->code . ' - ' . $acc->name])->all()" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Depreciation Expense Account" name="depreciation_expense_account_id" id="edit_category_depreciation_expense_account_id" :options="['' => 'Use default (5800)'] + $chartOfAccounts->mapWithKeys(fn ($acc) => [$acc->id => $acc->code . ' - ' . $acc->name])->all()" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.select :stacked="true" class="odoo-select2" label="Default Depreciation Method" name="default_depreciation_method" id="edit_category_default_depreciation_method" :options="[
                                    '' => 'Straight Line (default)',
                                    'straight_line' => 'Straight Line',
                                    'wdv' => 'Written Down Value (WDV)',
                                ]" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.icon-input label="Default Useful Life (months)" icon="feather-clock" type="number" name="default_useful_life_months" id="edit_category_default_useful_life_months" />
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_production_machinery" value="1" id="edit_category_is_production_machinery">
                                    <label class="form-check-label fs-12" for="edit_category_is_production_machinery">
                                        Production machinery — purchases in this category will be surfaced in Production for machine registration
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    </div>
        </form>
    </x-ui.modal>

    @push('scripts')
        <script>
            document.getElementById('editCategoryModal').addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                const form = document.getElementById('editCategoryForm');
                form.action = btn.getAttribute('data-action');

                // select2-enhanced <select> elements (odoo-form-ui type="select")
                // render their own visual box that does NOT sync from a plain
                // `.value = x` assignment — it has to be told via jQuery + a
                // 'change' trigger, otherwise the native <select> holds the right
                // value (form still submits correctly) but the widget keeps
                // showing its placeholder, which looked like the field was blank.
                function setSelect2(id, value) {
                    $('#' + id).val(value || '').trigger('change');
                }

                setSelect2('edit_category_company_id', btn.getAttribute('data-company-id'));
                document.getElementById('edit_category_name').value = btn.getAttribute('data-name') || '';
                document.getElementById('edit_category_description').value = btn.getAttribute('data-description') || '';
                setSelect2('edit_category_fixed_asset_account_id', btn.getAttribute('data-fixed-asset-account-id'));
                setSelect2('edit_category_accumulated_depreciation_account_id', btn.getAttribute('data-accumulated-depreciation-account-id'));
                setSelect2('edit_category_depreciation_expense_account_id', btn.getAttribute('data-depreciation-expense-account-id'));
                setSelect2('edit_category_default_depreciation_method', btn.getAttribute('data-default-depreciation-method'));
                document.getElementById('edit_category_default_useful_life_months').value = btn.getAttribute('data-default-useful-life-months') || '';
                document.getElementById('edit_category_is_production_machinery').checked = btn.getAttribute('data-is-production-machinery') === '1';
            });
        </script>
    @endpush
@endsection
