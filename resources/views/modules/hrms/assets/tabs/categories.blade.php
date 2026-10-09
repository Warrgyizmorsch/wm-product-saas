<!-- 2. ASSET CATEGORIES TAB -->
<div class="tab-pane fade {{ request('tab') === 'categories-pane' ? 'show active' : '' }}" id="categories-pane" role="tabpanel" aria-labelledby="categories-pane-tab">
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.assets.categories_title') }}</h5>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Categories Search & Filter Form -->
                <form method="GET" action="{{ route('hrms.assets.index') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="tab" value="categories-pane">
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
                                <a href="{{ route('hrms.assets.index', array_merge(request()->except(['category_search', 'category_company_id']), ['tab' => 'categories-pane'])) }}" class="btn btn-sm btn-light border">{{ __('hrms.common.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('hrms.common.apply') }}</button>
                            </div>
                        </x-ui.filter>

                        @if(request()->anyFilled(['category_search', 'category_company_id']))
                            <a href="{{ route('hrms.assets.index', array_merge(request()->except(['category_search', 'category_company_id']), ['tab' => 'categories-pane'])) }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="{{ __('hrms.common.reset') }}">
                                <i class="feather-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
        <div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                    <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="text-start px-4" style="width: 35%;">{{ __('hrms.assets.category_name') }} & {{ __('hrms.assets.tbl_description') }}</th>
                            <th style="width: 12%;">{{ __('hrms.assets.total_assets') }}</th>
                            <th style="width: 20%;">{{ __('hrms.assets.org_entity') }}</th>
                            <th style="width: 13%;">{{ __('hrms.common.type') }}</th>
                            <th style="width: 15%;">{{ __('hrms.assets.created_at') }}</th>
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
                                <td>
                                    @if($category->is_production_machinery)
                                        <span class="badge bg-soft-primary text-primary rounded-pill px-2 py-1"><i class="feather-tool fs-10 me-1"></i>{{ __('hrms.assets.machinery') }}</span>
                                    @else
                                        <span class="text-muted fs-11">—</span>
                                    @endif
                                </td>
                                <td class="text-muted fs-12">{{ $category->created_at->format('d M, Y') }}</td>
                                <td class="text-end px-4">
                                     <x-ui.action-dropdown>
                                         <li>
                                             <a class="dropdown-item edit-category-btn" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                 data-category-id="{{ $category->id }}"
                                                 data-company-id="{{ $category->company_id }}"
                                                 data-name="{{ $category->name }}"
                                                 data-description="{{ $category->description }}"
                                                 data-is-production-machinery="{{ $category->is_production_machinery ? '1' : '0' }}">
                                                 <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('hrms.assets.edit') }}
                                             </a>
                                         </li>
                                         <li>
                                             <form action="{{ route('hrms.assets.category.destroy', $category->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.assets.confirm_delete_category') }}', { title: '{{ __('hrms.assets.delete_category_title') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.assets.delete') }}' });">
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
                                <td colspan="6" class="text-center py-5 text-muted fs-12">
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
            <div class="pt-3 border-top mt-3">
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
</div>{{-- /#categories-pane --}}





<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true" data-bs-backdrop="static">
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
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.category_name') }}" name="name" placeholder="{{ __('hrms.assets.placeholder_category_name') }}" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.description') }}" name="description" placeholder="{{ __('hrms.assets.placeholder_category_desc') }}" />
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_production_machinery" value="1" id="add_category_is_production_machinery">
                                <label class="form-check-label fs-12" for="add_category_is_production_machinery">
                                    {{ __('hrms.assets.production_machinery_desc') }}
                                </label>
                            </div>
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
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true" data-bs-backdrop="static">
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
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.assets.category_name') }}" name="name" id="edit_category_name" placeholder="{{ __('hrms.assets.placeholder_category_name') }}" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.assets.description') }}" name="description" id="edit_category_description" placeholder="{{ __('hrms.assets.placeholder_category_desc') }}" />
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_production_machinery" value="1" id="edit_category_is_production_machinery">
                                <label class="form-check-label fs-12" for="edit_category_is_production_machinery">
                                    {{ __('hrms.assets.production_machinery_desc') }}
                                </label>
                            </div>
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

<!-- MODAL: IMPORT CATEGORIES -->
<div class="modal fade" id="importCategoryModal" tabindex="-1" aria-labelledby="importCategoryModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="importCategoryModalLabel">
                    <i class="feather-upload me-2 text-primary" style="font-size: 16px;"></i>{{ __('hrms.assets.import_categories') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.assets.categories.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body text-start">
                    <div class="alert bg-light border-0 d-flex flex-column gap-2 p-3 mb-4 rounded-3 text-dark fs-12">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-info text-primary fs-15"></i>
                            <span class="fw-bold">{{ __('hrms.assets.import_instructions_title') }}</span>
                        </div>
                        <span class="text-muted leading-relaxed">
                            {{ __('hrms.assets.import_cat_instructions_desc') }}
                        </span>
                        <div class="mt-1">
                            <a href="{{ route('hrms.assets.categories.import.template') }}" class="btn btn-xs btn-soft-primary d-inline-flex align-items-center fw-bold py-1.5 px-3" style="border-radius: 6px; font-size: 11px;">
                                <i class="feather-download me-1.5 fs-12"></i> {{ __('hrms.assets.download_template') }}
                            </a>
                        </div>
                    </div>
                    <div class="col-12">
                         <div class="erp-custom-file-upload">
                             <label class="file-upload-label py-3 px-4 w-100" style="cursor: pointer; border-style: dashed; border-width: 2px;" for="category_import_file">
                                  <i class="feather-upload-cloud me-2 text-primary fs-20"></i>
                                  <span class="file-text text-muted" id="category_import_file_text">{{ __('hrms.assets.select_excel_file') }}</span>
                                  <input type="file" name="file" id="category_import_file" class="d-none" required accept=".xlsx,.csv" onchange="document.getElementById('category_import_file_text').innerText = this.files[0]?.name || '{{ __('hrms.assets.select_excel_file') }}'">
                             </label>
                         </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.common.import') }}</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.common.discard') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

