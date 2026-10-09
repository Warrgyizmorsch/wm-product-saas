<!-- DOCUMENT CATEGORIES TAB -->
<div class="tab-pane fade {{ request()->query('active_tab') === 'categories' ? 'show active' : '' }}" id="categories-pane" role="tabpanel" aria-labelledby="categories-tab">
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">{{ __('hrms.document_master.categories_title') }}</h5>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Search & Filters -->
                <form method="GET" action="{{ route('hrms.documents-master.index') }}" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="active_tab" value="categories">
                    
                    @if(request()->filled('doc_search'))
                        <input type="hidden" name="doc_search" value="{{ request('doc_search') }}">
                    @endif
                    @if(request()->filled('doc_category_id'))
                        <input type="hidden" name="doc_category_id" value="{{ request('doc_category_id') }}">
                    @endif
                    @if(request()->filled('doc_status'))
                        <input type="hidden" name="doc_status" value="{{ request('doc_status') }}">
                    @endif
                    @if(request()->filled('doc_sort'))
                        <input type="hidden" name="doc_sort" value="{{ request('doc_sort') }}">
                    @endif
                    
                    <input type="hidden" name="category_sort" id="category_sort" value="{{ request('category_sort', 'name_asc') }}">
                    
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="category_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.document_master.search_categories') }}" value="{{ request('category_search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <div class="d-flex gap-2">
                        <x-ui.sort-dropdown :label="__('hrms.document_master.sort')">
                            <a class="dropdown-item py-2 {{ request('category_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_asc', this); event.preventDefault();">{{ __('hrms.document_master.sort_name_asc') }}</a>
                            <a class="dropdown-item py-2 {{ request('category_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_desc', this); event.preventDefault();">{{ __('hrms.document_master.sort_name_desc') }}</a>
                            <a class="dropdown-item py-2 {{ request('category_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'newest', this); event.preventDefault();">{{ __('hrms.document_master.sort_newest') }}</a>
                        </x-ui.sort-dropdown>

                        <x-ui.filter :label="__('hrms.document_master.filter')" offset="0, 5" :reset-url="route('hrms.documents-master.index', ['active_tab' => 'categories'])">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.document_master.filter_options') }}</h6>
                            
                            <div class="mb-3" style="min-width: 250px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.document_master.company') }}</label>
                                <x-ui.odoo-form-ui type="select" name="category_company_id">
                                    <option value="">{{ __('hrms.document_master.all_companies') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ request('category_company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->company_name }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        </x-ui.filter>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                    <tr>
                        <th class="text-start px-4" style="width: 35%;">{{ __('hrms.document_master.category_name') }}</th>
                        <th style="width: 20%;">{{ __('hrms.document_master.company') }}</th>
                        <th style="width: 20%;">{{ __('hrms.document_master.total_documents_linked') }}</th>
                        <th style="width: 15%;">{{ __('hrms.document_master.created_at') }}</th>
                        <th class="text-end px-4" style="width: 10%;">{{ __('hrms.document_master.actions') }}</th>
                    </tr>
                </thead>
                <tbody id="categoriesTableBody">
                    @forelse($categories as $category)
                        <tr>
                            <td class="text-start px-4" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                <div class="fw-bold text-dark fs-13">{{ $category->name }}</div>
                                @if($category->description)
                                    <div class="text-muted fs-11 mt-1" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;">{{ $category->description }}</div>
                                @else
                                    <div class="text-muted fs-11 mt-1 fst-italic">{{ __('hrms.document_master.no_description') }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="fs-12 fw-bold text-secondary">{{ $category->company->company_name ?? __('hrms.document_master.all_companies') }}</span>
                            </td>
                            <td>
                                <x-ui.badge variant="primary" soft>
                                    {{ $category->document_masters_count ?? $category->documentMasters()->count() }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <div class="fs-12 text-muted">{{ $category->created_at->format('M d, Y') }}</div>
                                <div class="fs-10 text-muted mt-0.5">{{ $category->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="text-end px-4">
                                <x-ui.action-dropdown id="catActions-{{ $category->id }}">
                                    <li>
                                        <a class="dropdown-item py-2" 
                                           href="javascript:void(0)"
                                           data-bs-toggle="modal" 
                                           data-bs-target="#editCategoryModal"
                                           data-category-id="{{ $category->id }}"
                                           data-company-id="{{ $category->company_id }}"
                                           data-name="{{ $category->name }}"
                                           data-description="{{ $category->description }}">
                                            <i class="feather-edit me-1.5 text-muted"></i> {{ __('hrms.document_master.edit') }}
                                        </a>
                                    </li>
                                    <li>
                                        <form action="{{ route('hrms.documents-master.categories.destroy', $category->id) }}" method="POST" id="deleteCategoryForm_{{ $category->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item py-2 text-danger" onclick="return confirm('{{ __('hrms.document_master.confirm_delete_category') }}');" {{ ($category->document_masters_count ?? $category->documentMasters()->count()) > 0 ? 'disabled' : '' }}>
                                                <i class="feather-trash-2 me-1.5"></i> {{ __('hrms.document_master.delete') }}
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted fs-14">
                                    <i class="feather-info me-1 fs-16 text-primary"></i> {{ __('hrms.document_master.empty_categories') }}
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3" id="categoriesPaginationWrapper">
            <x-ui.pagination 
                :currentPage="$categories->currentPage()" 
                :totalPages="$categories->lastPage()" 
                :totalResults="$categories->total()" 
                :perPage="$categories->perPage()" 
                pageParam="category_page"
                tab="categories"
            />
        </div>
    </div>
</div>

<!-- MODAL: ADD CATEGORY -->
<div class="modal fade" id="addCategoryModal" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="addCategoryModalLabel">
                    <i class="feather-sliders me-2 text-primary"></i>{{ __('hrms.document_master.create_category_title') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.documents-master.categories.store') }}" method="POST">
                @csrf
                <div class="modal-body text-start">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.company')" name="company_id" :required="true" select2-selector="default">
                                <option value="">{{ __('hrms.document_master.select_company_placeholder') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.category_name')" name="name" :placeholder="__('hrms.document_master.category_name_placeholder')" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.document_master.description')" name="description" :placeholder="__('hrms.document_master.category_description_placeholder')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.create_category_btn') }}</x-ui.button>
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
                    <i class="feather-sliders me-2 text-primary"></i>{{ __('hrms.document_master.edit_category_title') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body text-start">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.document_master.company')" name="company_id" id="edit_category_company_id" :required="true" select2-selector="default">
                                <option value="">{{ __('hrms.document_master.select_company_placeholder') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" :label="__('hrms.document_master.category_name')" name="name" id="edit_category_name" :placeholder="__('hrms.document_master.category_name_placeholder')" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.document_master.description')" name="description" id="edit_category_description" :placeholder="__('hrms.document_master.category_description_placeholder')" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" class="px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.document_master.save_changes') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
