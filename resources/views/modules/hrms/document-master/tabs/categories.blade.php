<!-- DOCUMENT CATEGORIES TAB -->
<div class="tab-pane fade {{ request()->query('active_tab') === 'categories' ? 'show active' : '' }}" id="categories-pane" role="tabpanel" aria-labelledby="categories-tab">
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-size: 16px;">Document Categories</h5>
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
                    
                    <input type="hidden" name="category_sort" id="category_sort" value="{{ request('category_sort', 'name_asc') }}">
                    
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="category_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search categories..." value="{{ request('category_search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <div class="d-flex gap-2">
                        <x-ui.sort-dropdown label="Sort">
                            <a class="dropdown-item py-2 {{ request('category_sort', 'name_asc') == 'name_asc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_asc', this); event.preventDefault();">Name (A-Z)</a>
                            <a class="dropdown-item py-2 {{ request('category_sort') == 'name_desc' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'name_desc', this); event.preventDefault();">Name (Z-A)</a>
                            <a class="dropdown-item py-2 {{ request('category_sort') == 'newest' ? 'active' : '' }}" href="#" onclick="changeSort('category', 'newest', this); event.preventDefault();">Newest</a>
                        </x-ui.sort-dropdown>

                        @if(request()->filled('category_search'))
                            <a href="{{ route('hrms.documents-master.index', ['active_tab' => 'categories']) }}" class="btn btn-sm btn-light border px-2 d-flex align-items-center justify-content-center" style="height: 38px; border-radius: 6px; font-size: 12px;" title="Clear Search">
                                <i class="feather-x"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center" style="table-layout: fixed; width: 100%;">
                <thead class="table-light text-uppercase fs-11" style="letter-spacing: 0.5px;">
                    <tr>
                        <th class="text-start px-4" style="width: 35%;">Category Name</th>
                        <th style="width: 20%;">Company</th>
                        <th style="width: 20%;">Total Documents Linked</th>
                        <th style="width: 15%;">Created At</th>
                        <th class="text-end px-4" style="width: 10%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td class="text-start px-4" style="word-break: break-word; overflow-wrap: anywhere; white-space: normal;">
                                <div class="fw-bold text-dark fs-13">{{ $category->name }}</div>
                                @if($category->description)
                                    <div class="text-muted fs-11 mt-1" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;">{{ $category->description }}</div>
                                @else
                                    <div class="text-muted fs-11 mt-1 fst-italic">No description provided.</div>
                                @endif
                            </td>
                            <td>
                                <span class="fs-12 fw-bold text-secondary">{{ $category->company->company_name ?? 'All Companies' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-soft-primary text-primary px-2.5 py-1.5 fs-11">
                                    {{ $category->documentMasters()->count() }}
                                </span>
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
                                            <i class="feather-edit me-1.5 text-muted"></i> Edit
                                        </a>
                                    </li>
                                    <li>
                                        <form action="{{ route('hrms.documents-master.categories.destroy', $category->id) }}" method="POST" id="deleteCategoryForm_{{ $category->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item py-2 text-danger" onclick="return confirm('Are you sure you want to delete this document category?');" {{ $category->documentMasters()->exists() ? 'disabled' : '' }}>
                                                <i class="feather-trash-2 me-1.5"></i> Delete
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
                                    <i class="feather-info me-1 fs-16 text-primary"></i> No document categories found.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
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
                    <i class="feather-sliders me-2 text-primary"></i>Create Document Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.documents-master.categories.store') }}" method="POST">
                @csrf
                <div class="modal-body text-start">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Company" name="company_id" :required="true" select2-selector="default">
                                <option value="">-- Select Company --</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Category Name" name="name" placeholder="e.g. Identification Proofs, Academic Certificates" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Brief details about what documents go into this category..." />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Create Category</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
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
                    <i class="feather-sliders me-2 text-primary"></i>Edit Document Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body text-start">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Company" name="company_id" id="edit_category_company_id" :required="true" select2-selector="default">
                                <option value="">-- Select Company --</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Category Name" name="name" id="edit_category_name" placeholder="e.g. Identification Proofs, Academic Certificates" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_category_description" placeholder="Brief details about what documents go into this category..." />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 gap-2">
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Save Changes</button>
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                </div>
            </form>
        </div>
    </div>
</div>
