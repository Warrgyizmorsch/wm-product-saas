@extends('layouts.duralux')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@section('title', 'Expense Categories | SaaS ERP')
@section('page-title', 'Expense Categories Master')
@section('breadcrumb', 'HRMS / Masters / Expense Categories')

@section('page-actions')
    <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addCategoryModal" class="fw-bold text-uppercase">
        Add Category
    </x-ui.button>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @if(session('success'))
            <x-ui.alert variant="success" dismissible class="mb-4">
                <i class="feather-check-circle me-2"></i>{{ session('success') }}
            </x-ui.alert>
        @endif

        @if(session('error'))
            <x-ui.alert variant="danger" dismissible class="mb-4">
                <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
            </x-ui.alert>
        @endif

        {{-- ── Toolbar: Search + Sort + Filter ─────────────────────────────── --}}
        <div class="d-flex align-items-center gap-2 flex-wrap mb-4">

            {{-- Search --}}
            <form method="GET" action="{{ route('hrms.expense-categories.index') }}"
                  id="categorySearchForm"
                  class="d-flex align-items-center bg-light border rounded px-3 py-1">
                {{-- Preserve other active filters across searches --}}
                <input type="hidden" name="sort"   value="{{ $filters['sort'] }}">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <i class="feather-search text-muted me-2" style="font-size:14px;"></i>
                <input
                    type="text"
                    name="search"
                    class="form-control border-0 bg-transparent p-0 fs-13"
                    placeholder="Search by name, code, or description..."
                    value="{{ $filters['search'] }}"
                    style="box-shadow:none; height:32px; min-width:220px;"
                >
            </form>

            {{-- Sort --}}
            <x-ui.sort-dropdown label="Sort">
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_asc' ? 'active' : '' }}"
                   href="{{ request()->fullUrlWithQuery(['sort' => 'name_asc']) }}">
                    <span>Name A → Z</span>
                    @if($filters['sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                </a>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_desc' ? 'active' : '' }}"
                   href="{{ request()->fullUrlWithQuery(['sort' => 'name_desc']) }}">
                    <span>Name Z → A</span>
                    @if($filters['sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'code_asc' ? 'active' : '' }}"
                   href="{{ request()->fullUrlWithQuery(['sort' => 'code_asc']) }}">
                    <span>Code A → Z</span>
                    @if($filters['sort'] === 'code_asc') <i class="feather-check ms-3"></i> @endif
                </a>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'code_desc' ? 'active' : '' }}"
                   href="{{ request()->fullUrlWithQuery(['sort' => 'code_desc']) }}">
                    <span>Code Z → A</span>
                    @if($filters['sort'] === 'code_desc') <i class="feather-check ms-3"></i> @endif
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'newest' ? 'active' : '' }}"
                   href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                    <span>Newest First</span>
                    @if($filters['sort'] === 'newest') <i class="feather-check ms-3"></i> @endif
                </a>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'oldest' ? 'active' : '' }}"
                   href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                    <span>Oldest First</span>
                    @if($filters['sort'] === 'oldest') <i class="feather-check ms-3"></i> @endif
                </a>
            </x-ui.sort-dropdown>

            {{-- Filter --}}
            <x-ui.filter label="Filter">
                <h6 class="fw-bold text-dark fs-12 mb-3">
                    <i class="feather-sliders text-primary me-1"></i> Filter Options
                </h6>
                <form method="GET" action="{{ route('hrms.expense-categories.index') }}" id="categoryFilterForm">
                    {{-- Preserve search & sort --}}
                    <input type="hidden" name="search" value="{{ $filters['search'] }}">
                    <input type="hidden" name="sort"   value="{{ $filters['sort'] }}">

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                        <x-ui.odoo-form-ui type="select" name="status" id="cat_filter_status">
                            <option value="">All Statuses</option>
                            <option value="1" @selected($filters['status'] === '1')>Active</option>
                            <option value="0" @selected($filters['status'] === '0')>Inactive</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="dropdown-divider my-3"></div>
                    <div class="d-flex gap-2">
                        <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">Apply Filters</x-ui.button>
                        <a href="{{ route('hrms.expense-categories.index') }}" class="btn btn-sm btn-light border flex-grow-1 d-flex align-items-center justify-content-center" style="font-size: 12px; font-weight: 500;">Reset</a>
                    </div>
                </form>
            </x-ui.filter>

            {{-- Active filter badges --}}
            @if($filters['search'] || $filters['status'] !== '')
                <div class="d-flex align-items-center gap-2 ms-1">
                    @if($filters['search'])
                        <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill">
                            <i class="feather-search me-1"></i>{{ $filters['search'] }}
                        </span>
                    @endif
                    @if($filters['status'] !== '')
                        <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 rounded-pill">
                            Status: {{ $filters['status'] === '1' ? 'Active' : 'Inactive' }}
                        </span>
                    @endif
                    <a href="{{ route('hrms.expense-categories.index') }}" class="text-danger fs-12">
                        <i class="feather-x"></i> Clear all
                    </a>
                </div>
            @endif
        </div>

        {{-- ── Categories Table ─────────────────────────────────────────────── --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%;">Code</th>
                        <th style="width: 25%;">Category Name</th>
                        <th style="width: 35%;">Description</th>
                        <th style="width: 13%;">Status</th>
                        <th style="width: 12%;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="categoriesTableBody">
                    @forelse($categories as $category)
                        <tr>
                            <td class="fw-bold text-primary">{{ $category->code }}</td>
                            <td class="text-dark fw-semibold">{{ $category->name }}</td>
                            <td class="text-muted">{{ $category->description ?: '-' }}</td>
                            <td>
                                <x-ui.badge variant="{{ $category->status ? 'success' : 'danger' }}" soft class="px-2 py-1 fs-11 rounded-pill">
                                    {{ $category->status ? 'Active' : 'Inactive' }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <x-ui.icon-btn
                                        type="button"
                                        variant="soft-primary"
                                        size="sm"
                                        class="btn-edit-category"
                                        icon="feather-edit-3"
                                        title="Edit Category"
                                        data-id="{{ $category->id }}"
                                        data-name="{{ $category->name }}"
                                        data-code="{{ $category->code }}"
                                        data-description="{{ $category->description }}"
                                        data-status="{{ $category->status ? 1 : 0 }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editCategoryModal"
                                    />
                                    <form method="POST" action="{{ route('hrms.expense-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?');" class="m-0 d-flex">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="Delete Category" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="feather-tag fs-24 d-block mb-2 text-secondary"></i>
                                @if($filters['search'] || $filters['status'] !== '')
                                    No categories match your current filters.
                                    <a href="{{ route('hrms.expense-categories.index') }}" class="d-block mt-1 text-primary fs-12">Clear filters</a>
                                @else
                                    No expense categories found. Click <strong>Add Category</strong> to create one.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="categoriesPaginationWrapper">
            @if($categories->hasPages())
                <div class="mt-4">
                    <x-ui.pagination :paginator="$categories" />
                </div>
            @endif
        </div>
    </div>
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     MODALS — x-ui.modal auto-appends to <body> on its own
     ══════════════════════════════════════════════════════════════════════ --}}

{{-- Add Category --}}
<x-ui.modal
    id="addCategoryModal"
    title='<i class="feather-tag me-2 text-primary"></i>Add Expense Category'
    centered
    formAction="{{ route('hrms.expense-categories.store') }}"
    formMethod="POST"
    submitText="Save Category"
    closeText="Cancel"
>
    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="input" label="Code *" name="code" id="add_code"
            placeholder="e.g. MEALS, LODGING" :required="true" />
        <x-ui.odoo-form-ui type="input" label="Category Name *" name="name" id="add_name"
            placeholder="e.g. Food & Dining" :required="true" />
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="add_description"
            placeholder="Optional description..." rows="3" />
        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="add_status" select2-selector="default">
            <option value="1" selected>Active</option>
            <option value="0">Inactive</option>
        </x-ui.odoo-form-ui>
    </div>
</x-ui.modal>

{{-- Edit Category --}}
<x-ui.modal
    id="editCategoryModal"
    title='<i class="feather-edit-3 me-2 text-primary"></i>Edit Expense Category'
    centered
    formAction=""
    formMethod="PUT"
    submitText="Update Category"
    closeText="Cancel"
>
    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="input" label="Code *" name="code" id="edit_code" :required="true" />
        <x-ui.odoo-form-ui type="input" label="Category Name *" name="name" id="edit_name" :required="true" />
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_description" rows="3" />
        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="edit_status" select2-selector="default">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </x-ui.odoo-form-ui>
    </div>
</x-ui.modal>

@push('scripts')
<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Re-initialize select2 on load
        if (window.$ && $.fn.select2) {
            $('.odoo-select2').select2({ theme: 'bootstrap-5', width: '100%' });
        }
        var searchTimeout;
        var activeRequest = null;

        function refreshCategoriesList(url) {
            if (activeRequest) {
                activeRequest.abort();
            }
            var controller = new AbortController();
            activeRequest = controller;

            var tbody = document.getElementById('categoriesTableBody');
            var pagWrapper = document.getElementById('categoriesPaginationWrapper');
            if (tbody) tbody.style.opacity = '0.5';

            fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: controller.signal
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Error reloading categories.');
                return response.text();
            })
            .then(function(html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newTbody = doc.getElementById('categoriesTableBody');
                var oldTbody = document.getElementById('categoriesTableBody');
                if (newTbody && oldTbody) {
                    oldTbody.innerHTML = newTbody.innerHTML;
                }

                var newPag = doc.getElementById('categoriesPaginationWrapper');
                var oldPag = document.getElementById('categoriesPaginationWrapper');
                if (newPag && oldPag) {
                    oldPag.innerHTML = newPag.innerHTML;
                }
                
                history.pushState(null, '', url.toString());
            })
            .catch(function(err) {
                if (err.name !== 'AbortError') {
                    window.location.href = url.toString();
                }
            })
            .finally(function() {
                if (tbody) tbody.style.opacity = '1';
            });
        }

        // Real-time search with debounce (no Enter key required, no page reload)
        $(document).on('input keyup search', '#categorySearchForm input[name="search"]', function() {
            var form = this.closest('form');
            var url = new URL(form.action || window.location.href);
            var formData = new FormData(form);
            for (var [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                refreshCategoriesList(url);
            }, 300);
        });

        // Intercept sorting links (no page reload)
        $(document).on('click', '.dropdown-item[href*="sort="]', function(e) {
            var href = $(this).attr('href');
            if (href && href.indexOf('expense-categories') !== -1) {
                e.preventDefault();
                var url = new URL(href, window.location.origin);
                refreshCategoriesList(url);

                $('.dropdown-item[href*="sort="]').removeClass('active');
                $(this).addClass('active');
            }
        });

        // Intercept filter form submit (no page reload)
        $(document).on('submit', '#categoryFilterForm', function(e) {
            e.preventDefault();
            var form = this;
            var url = new URL(form.action || window.location.href);
            var formData = new FormData(form);
            for (var [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }
            refreshCategoriesList(url);
            $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
        });

        // Populate edit modal with row data (using delegation)
        $(document).on('click', '.btn-edit-category', function () {
            var id          = this.getAttribute('data-id');
            var code        = this.getAttribute('data-code');
            var name        = this.getAttribute('data-name');
            var description = this.getAttribute('data-description');
            var status      = this.getAttribute('data-status');

            // Update form action for this specific record
            var form = document.querySelector('#editCategoryModal form');
            if (form) form.action = '{{ url('hrms/expense-categories') }}/' + id;

            document.getElementById('edit_code').value        = code        || '';
            document.getElementById('edit_name').value        = name        || '';
            document.getElementById('edit_description').value = description || '';

            // Update select2 status dropdown
            var statusSelect = document.getElementById('edit_status');
            if (statusSelect) {
                statusSelect.value = parseInt(status) === 1 ? '1' : '0';
                if (window.$ && $(statusSelect).hasClass('select2-hidden-accessible')) {
                    $(statusSelect).trigger('change.select2');
                }
            }
        });
    });
</script>
@endpush
