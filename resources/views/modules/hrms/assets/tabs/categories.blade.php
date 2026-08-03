<!-- 2. ASSET CATEGORIES TAB -->
<div class="tab-pane fade" id="categories-pane" role="tabpanel" aria-labelledby="categories-tab">
    <div class="card border rounded bg-white shadow-sm">
        <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center py-3 px-4 bg-white gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.assets.categories_title') }}</h5>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Categories Search & Filter Form -->
                <form method="GET" action="{{ route('hrms.assets.index') }}" class="d-flex align-items-center gap-2 m-0">
                    @foreach(['registry_search', 'registry_category_id', 'registry_status', 'registry_condition', 'request_search', 'request_category_id', 'request_company_id', 'request_status'] as $param)
                        @if(request()->filled($param))
                            <input type="hidden" name="{{ $param }}" value="{{ request($param) }}">
                        @endif
                    @endforeach
                    <input type="hidden" name="category_sort" id="category_sort" value="{{ request('category_sort', 'name_asc') }}">
                    
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="category_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.assets.search_categories_placeholder') }}" value="{{ request('category_search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <div class="d-flex gap-2">
                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                            <a class="dropdown-item py-2 {{ request('category_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_asc', this); event.preventDefault();">{{ __('hrms.common.sort_name_asc') }}</a>
                            <a class="dropdown-item py-2 {{ request('category_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_desc', this); event.preventDefault();">{{ __('hrms.common.sort_name_desc') }}</a>
                            <a class="dropdown-item py-2 {{ request('category_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'newest', this); event.preventDefault();">{{ __('hrms.assets.sort_newest') }}</a>
                        </x-ui.sort-dropdown>

                        <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                            
                            <div class="mb-3" style="min-width: 250px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.assets.org_entity') }}</label>
                                <x-ui.odoo-form-ui type="select" name="category_company_id">
                                    <option value="">{{ __('hrms.common.all_companies') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ request('category_company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->company_name }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('hrms.assets.index', request()->except(['category_search', 'category_company_id'])) }}" class="btn btn-sm btn-light border">{{ __('hrms.common.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('hrms.common.apply') }}</button>
                            </div>
                        </x-ui.filter>

                    @if(request()->anyFilled(['category_search', 'category_company_id']))
                        <a href="{{ route('hrms.assets.index', request()->except(['category_search', 'category_company_id'])) }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="Clear Filters">
                            <i class="feather-x"></i>
                        </a>
                    @endif
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                    <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="text-start px-4" style="width: 40%;">{{ __('hrms.assets.category_name') }} & {{ __('hrms.assets.tbl_description') }}</th>
                            <th style="width: 15%;">{{ __('hrms.assets.total_assets') }}</th>
                            <th style="width: 25%;">{{ __('hrms.assets.org_entity') }}</th>
                            <th style="width: 20%;">{{ __('hrms.assets.created_at') }}</th>
                            <th class="text-end px-4" style="width: 110px; white-space: nowrap;">{{ __('hrms.assets.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($filteredCategories as $category)
                            <tr>
                                <td class="text-start px-4" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                    <div class="fw-bold text-dark fs-13">{{ $category->name }}</div>
                                    @if($category->description)
                                        <div class="text-muted fs-11 mt-1" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;">{{ $category->description }}</div>
                                    @else
                                        <div class="text-muted fs-11 mt-1 fst-italic">{{ __('hrms.assets.no_description_provided') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-soft-primary text-primary px-3 py-1 rounded-pill">{{ $category->assets()->count() }}</span>
                                </td>
                                <td style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">{{ $category->company->company_name }}</td>
                                <td class="text-muted fs-12">{{ $category->created_at->format('d M, Y') }}</td>
                                <td class="text-end px-4">
                                     <x-ui.action-dropdown>
                                         <li>
                                             <a class="dropdown-item edit-category-btn" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                 data-category-id="{{ $category->id }}"
                                                 data-company-id="{{ $category->company_id }}"
                                                 data-name="{{ $category->name }}"
                                                 data-description="{{ $category->description }}">
                                                 <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('hrms.assets.edit') }}
                                             </a>
                                         </li>
                                         <li>
                                             <form action="{{ route('hrms.assets.category.destroy', $category->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.assets.confirm_delete_category') ?? 'Are you sure you want to delete this asset category? This action cannot be undone.' }}', { title: 'Delete Asset Category', variant: 'danger', confirmButtonText: 'Delete' });">
                                                 @csrf
                                                 @method('DELETE')
                                                 <button type="submit" class="dropdown-item text-danger">
                                                     <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('hrms.assets.delete') }}
                                                 </button>
                                             </form>
                                         </li>
                                     </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted fs-12">
                                    <i class="feather-sliders fs-32 d-block mb-3 text-secondary"></i>
                                    <div class="fw-bold mb-1">{{ __('hrms.assets.empty_categories_title') }}</div>
                                    <div>{{ __('hrms.assets.empty_categories_desc') }}</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @php
            $categoryCurrentPage = $filteredCategories->currentPage();
            $categoryTotalPages = $filteredCategories->lastPage();
            $categoryTotalResults = $filteredCategories->total();
            $categoryPerPage = $filteredCategories->perPage();
        @endphp
        @if($filteredCategories->hasPages())
            <div class="card-footer bg-white border-top px-4 py-3">
                <x-ui.pagination
                    class="px-0 py-0"
                    :current-page="$categoryCurrentPage"
                    :total-pages="$categoryTotalPages"
                    :total-results="$categoryTotalResults"
                    :per-page="$categoryPerPage"
                    page-param="category_page"
                />
            </div>
        @endif
    </div>
</div>

<!-- MODAL: ADD CATEGORY -->
<div class="modal fade" id="addCategoryModal" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="addCategoryModalLabel">
                    <i class="feather-sliders me-2 text-primary"></i>{{ __('hrms.assets.create_category') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.assets.category.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.belongs_to_company') }}" name="company_id" :required="true" select2-selector="default">
                                <option value="">{{ __('hrms.assets.select_company') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.category_name') }}" name="name" placeholder="e.g. IT Laptops, Office Car Keys" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.description') }}" name="description" placeholder="Brief details about what items go into this category..." />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.assets.add_category') }}</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDIT CATEGORY -->
<div class="modal fade" id="editCategoryModal" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="editCategoryModalLabel">
                    <i class="feather-sliders me-2 text-primary"></i>{{ __('hrms.assets.edit_category') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.assets.belongs_to_company') }}" name="company_id" id="edit_category_company_id" :required="true" select2-selector="default">
                                <option value="">{{ __('hrms.assets.select_company') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.category_name') }}" name="name" id="edit_category_name" placeholder="e.g. IT Laptops, Office Car Keys" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.description') }}" name="description" id="edit_category_description" placeholder="Brief details about what items go into this category..." />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.common.save_changes') }}</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
