@extends('layouts.duralux')

@section('title', 'Asset Categories | SaaS ERP')
@section('page-title', 'Asset Categories')
@section('breadcrumb', 'Accounting / Fixed Assets / Categories')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('accounting.fixed-assets.index') }}" variant="light" icon="feather-list" class="border">
            Asset Register
        </x-ui.button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="feather-plus me-2"></i>Add Category
        </button>
    </div>
@endsection

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold fs-12 text-uppercase text-muted mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Category name or description..." value="{{ request('search') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold fs-12 text-uppercase text-muted mb-1">Company</label>
                <select name="company_id" class="form-select form-select-sm">
                    <option value="">All Companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-light border w-100">Apply</button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card bodyClass="p-0">
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
    <div class="modal fade" id="addCategoryModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="feather-sliders me-2 text-primary"></i>Add Asset Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('accounting.fixed-assets.categories.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Company" name="company_id" :required="true">
                                    <option value="">Select company...</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Category Name" name="name" placeholder="e.g. IT Hardware, Plant Machinery" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Brief details about what items go into this category..." />
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="select" label="Fixed Asset Account" name="fixed_asset_account_id">
                                    <option value="">Use default (1500)</option>
                                    @foreach($chartOfAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="select" label="Accumulated Depreciation Account" name="accumulated_depreciation_account_id">
                                    <option value="">Use default (1510)</option>
                                    @foreach($chartOfAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="select" label="Depreciation Expense Account" name="depreciation_expense_account_id">
                                    <option value="">Use default (5800)</option>
                                    @foreach($chartOfAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Default Depreciation Method" name="default_depreciation_method">
                                    <option value="">Straight Line (default)</option>
                                    <option value="straight_line">Straight Line</option>
                                    <option value="wdv">Written Down Value (WDV)</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="number" label="Default Useful Life (months)" name="default_useful_life_months" placeholder="e.g. 36" />
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
            </div>
        </div>
    </div>

    <!-- MODAL: EDIT CATEGORY -->
    <div class="modal fade" id="editCategoryModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="feather-sliders me-2 text-primary"></i>Edit Asset Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editCategoryForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Company" name="company_id" id="edit_category_company_id" :required="true">
                                    <option value="">Select company...</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Category Name" name="name" id="edit_category_name" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_category_description" />
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="select" label="Fixed Asset Account" name="fixed_asset_account_id" id="edit_category_fixed_asset_account_id">
                                    <option value="">Use default (1500)</option>
                                    @foreach($chartOfAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="select" label="Accumulated Depreciation Account" name="accumulated_depreciation_account_id" id="edit_category_accumulated_depreciation_account_id">
                                    <option value="">Use default (1510)</option>
                                    @foreach($chartOfAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="select" label="Depreciation Expense Account" name="depreciation_expense_account_id" id="edit_category_depreciation_expense_account_id">
                                    <option value="">Use default (5800)</option>
                                    @foreach($chartOfAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Default Depreciation Method" name="default_depreciation_method" id="edit_category_default_depreciation_method">
                                    <option value="">Straight Line (default)</option>
                                    <option value="straight_line">Straight Line</option>
                                    <option value="wdv">Written Down Value (WDV)</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="number" label="Default Useful Life (months)" name="default_useful_life_months" id="edit_category_default_useful_life_months" />
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
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('editCategoryModal').addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                const form = document.getElementById('editCategoryForm');
                form.action = btn.getAttribute('data-action');
                document.getElementById('edit_category_company_id').value = btn.getAttribute('data-company-id') || '';
                document.getElementById('edit_category_name').value = btn.getAttribute('data-name') || '';
                document.getElementById('edit_category_description').value = btn.getAttribute('data-description') || '';
                document.getElementById('edit_category_fixed_asset_account_id').value = btn.getAttribute('data-fixed-asset-account-id') || '';
                document.getElementById('edit_category_accumulated_depreciation_account_id').value = btn.getAttribute('data-accumulated-depreciation-account-id') || '';
                document.getElementById('edit_category_depreciation_expense_account_id').value = btn.getAttribute('data-depreciation-expense-account-id') || '';
                document.getElementById('edit_category_default_depreciation_method').value = btn.getAttribute('data-default-depreciation-method') || '';
                document.getElementById('edit_category_default_useful_life_months').value = btn.getAttribute('data-default-useful-life-months') || '';
                document.getElementById('edit_category_is_production_machinery').checked = btn.getAttribute('data-is-production-machinery') === '1';
            });
        </script>
    @endpush
@endsection
