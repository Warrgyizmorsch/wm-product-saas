<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="Legal Entities" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search Form -->
                    <form method="GET" action="{{ route('hrms.org.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 240px;">
                        <input type="hidden" name="tab" value="legal-entities">
                        <input type="hidden" name="co_status" value="{{ $filters['co_status'] }}">
                        <input type="hidden" name="co_sort" value="{{ $filters['co_sort'] }}">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="co_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search legal entities..." value="{{ $filters['co_search'] }}" style="box-shadow: none; height: 32px;">
                    </form>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="SORT">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['co_sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'legal-entities', 'co_sort' => 'name_asc']) }}">
                            <span>Name (A-Z)</span>
                            @if($filters['co_sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['co_sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'legal-entities', 'co_sort' => 'name_desc']) }}">
                            <span>Name (Z-A)</span>
                            @if($filters['co_sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['co_sort'] === 'legal_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'legal-entities', 'co_sort' => 'legal_asc']) }}">
                            <span>Legal Name (A-Z)</span>
                            @if($filters['co_sort'] === 'legal_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['co_sort'] === 'legal_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'legal-entities', 'co_sort' => 'legal_desc']) }}">
                            <span>Legal Name (Z-A)</span>
                            @if($filters['co_sort'] === 'legal_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="FILTER">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                        <form method="GET" action="{{ route('hrms.org.index') }}">
                            <input type="hidden" name="tab" value="legal-entities">
                            <input type="hidden" name="co_search" value="{{ $filters['co_search'] }}">
                            <input type="hidden" name="co_sort" value="{{ $filters['co_sort'] }}">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">STATUS</label>
                                <select name="co_status" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                    <option value="">All Statuses</option>
                                    <option value="1" @selected($filters['co_status'] === '1')>Active</option>
                                    <option value="0" @selected($filters['co_status'] === '0')>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('hrms.org.index', ['tab' => 'legal-entities']) }}" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;">RESET</a>
                                <button type="submit" class="btn btn-sm text-uppercase fw-bold py-2 px-3 text-white bg-primary border-primary">APPLY FILTERS</button>
                            </div>
                        </form>
                    </x-ui.filter>
                </div>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">#</th>
                            <th width="80">Logo</th>
                            <th>Company Name</th>
                            <th>Legal Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th width="150" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="companiesTableBody">
                        @foreach($companies as $company)
                        <tr>
                            <td>{{ $companies->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="avatar-image avatar-md rounded bg-light p-1 border border-light overflow-hidden" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                    @if($company->logo)
                                        <img class="img-fluid h-100 w-100 object-fit-cover" src="{{ asset('storage/' . $company->logo) }}" alt="">
                                    @else
                                        <div class="avatar-text avatar-md bg-soft-primary text-primary h-100 w-100 d-flex align-items-center justify-content-center fw-bold fs-12">
                                            @php
                                                $companyNameParts = preg_split('/\s+/', trim($company->company_name ?? 'CO'));
                                                $companyInitials = count($companyNameParts) > 1
                                                    ? strtoupper(substr($companyNameParts[0], 0, 1) . substr($companyNameParts[1], 0, 1))
                                                    : strtoupper(substr($companyNameParts[0] ?? 'CO', 0, 1));
                                            @endphp
                                            {{ $companyInitials }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td><span class="fw-bold text-dark">{{ $company->company_name }}</span></td>
                            <td>{{ $company->legal_name }}</td>
                            <td>{{ $company->email ?? 'N/A' }}</td>
                            <td>
                                @if($company->status)
                                    <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('hrms.company.destroy', $company->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this company?');">
                                    @csrf
                                    @method('DELETE')
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="javascript:void(0)" class="action-dropdown-btn btn-view-company" data-bs-toggle="modal" data-bs-target="#viewCompanyModal" data-company="{{ base64_encode($company->toJson()) }}" title="View Details" data-bs-toggle="tooltip">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item btn-edit-company" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editCompanyModal" data-company="{{ base64_encode($company->toJson()) }}">
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
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                        @if($companies->isEmpty())
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                No Legal Entities found. Click "Add Legal Entity" to create one.
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div id="companiesPaginationWrapper">
                @php
                    $currentPage = $companies->currentPage();
                    $totalPages = $companies->lastPage();
                    $totalResults = $companies->total();
                    $perPage = $companies->perPage();
                @endphp
                <x-ui.pagination 
                    class="px-4 py-3 border-top"
                    :current-page="$currentPage"
                    :total-pages="$totalPages"
                    :total-results="$totalResults"
                    :per-page="$perPage"
                    tab="legal-entities"
                />
            </div>
        </x-ui.card>
    </div>
</div>

<script>
    (function() {
        function init() {
            function getInitials(name, fallback) {
                const words = String(name || fallback || '').trim().split(/\s+/).filter(Boolean);

                if (words.length >= 2) {
                    return (words[0][0] + words[1][0]).toUpperCase();
                }

                return (words[0] || fallback || '').substring(0, 1).toUpperCase();
            }

            // Edit Mode Image Upload preview scripts (vanilla JS)
            document.addEventListener("click", function(e) {
                if (e.target && (e.target.classList.contains("edit-upload-button") || e.target.closest(".edit-upload-button"))) {
                    let fileUploadInput = document.querySelector(".edit-file-upload");
                    if (fileUploadInput) fileUploadInput.click();
                }
                if (e.target && (e.target.classList.contains("add-upload-button") || e.target.closest(".add-upload-button"))) {
                    let fileUploadInput = document.querySelector(".add-file-upload");
                    if (fileUploadInput) fileUploadInput.click();
                }
            });

            document.addEventListener("change", function(e) {
                if (e.target && e.target.classList.contains("edit-file-upload")) {
                    let fileInput = e.target;
                    if (fileInput.files && fileInput.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            let previewImg = document.getElementById("edit_logo_preview");
                            if (previewImg) previewImg.src = e.target.result;
                        };
                        reader.readAsDataURL(fileInput.files[0]);
                    }
                }
                if (e.target && e.target.classList.contains("add-file-upload")) {
                    let fileInput = e.target;
                    if (fileInput.files && fileInput.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            let previewImg = document.getElementById("add_logo_preview");
                            if (previewImg) previewImg.src = e.target.result;
                        };
                        reader.readAsDataURL(fileInput.files[0]);
                    }
                }
            });

            // View Trigger
            document.querySelectorAll('.btn-view-company').forEach(btn => {
                btn.addEventListener('click', function() {
                    let company = JSON.parse(atob(this.dataset.company));
                    
                    let nameEl = document.getElementById('modal_view_company_name');
                    if (nameEl) nameEl.innerText = company.company_name;
                    
                    let legalEl = document.getElementById('modal_view_legal_name');
                    if (legalEl) legalEl.innerText = company.legal_name || 'N/A';
                    
                    let gstEl = document.getElementById('modal_view_gst');
                    if (gstEl) gstEl.innerText = company.gst_number || 'N/A';
                    
                    let panEl = document.getElementById('modal_view_pan');
                    if (panEl) panEl.innerText = company.pan_number || 'N/A';
                    
                    let cinEl = document.getElementById('modal_view_cin');
                    if (cinEl) cinEl.innerText = company.cin_number || 'N/A';
                    
                    let regEl = document.getElementById('modal_view_reg');
                    if (regEl) regEl.innerText = company.registration_number || 'N/A';
                    
                    let emailEl = document.getElementById('modal_view_email');
                    if (emailEl) emailEl.innerText = company.email || 'N/A';
                    
                    let phoneEl = document.getElementById('modal_view_phone');
                    if (phoneEl) phoneEl.innerText = company.phone || 'N/A';
                    
                    let currEl = document.getElementById('modal_view_currency');
                    if (currEl) currEl.innerText = company.currency || 'N/A';
                    
                    let tzEl = document.getElementById('modal_view_timezone');
                    if (tzEl) tzEl.innerText = company.timezone || 'N/A';
                    
                    let countryEl = document.getElementById('modal_view_country');
                    if (countryEl) countryEl.innerText = company.country || 'N/A';
                    
                    let stateEl = document.getElementById('modal_view_state');
                    if (stateEl) stateEl.innerText = company.state || 'N/A';
                    
                    let cityEl = document.getElementById('modal_view_city');
                    if (cityEl) cityEl.innerText = company.city || 'N/A';
                    
                    let zipEl = document.getElementById('modal_view_zip');
                    if (zipEl) zipEl.innerText = company.postal_code || 'N/A';
                    
                    let addrEl = document.getElementById('modal_view_address');
                    if (addrEl) addrEl.innerText = company.address || 'N/A';
                    
                    let logoContainer = document.getElementById('modal_view_logo_container');
                    if (logoContainer) {
                        if (company.logo) {
                            logoContainer.innerHTML = `<img class="img-fluid h-100 w-100 object-fit-cover" src="/storage/${company.logo}" alt="">`;
                        } else {
                            let initials = getInitials(company.company_name, 'CO');
                            logoContainer.innerHTML = `<div class="avatar-text avatar-lg bg-soft-primary text-primary h-100 w-100 d-flex align-items-center justify-content-center fw-bold fs-16">${initials}</div>`;
                        }
                    }
                });
            });

            // Edit Trigger
            document.querySelectorAll('.btn-edit-company').forEach(btn => {
                btn.addEventListener('click', function() {
                    let company = JSON.parse(atob(this.dataset.company));
                    
                    let nameEl = document.getElementById('edit_company_name');
                    if (nameEl) nameEl.value = company.company_name || '';
                    
                    let legalEl = document.getElementById('edit_legal_name');
                    if (legalEl) legalEl.value = company.legal_name || '';
                    
                    let gstEl = document.getElementById('edit_gst_number');
                    if (gstEl) gstEl.value = company.gst_number || '';
                    
                    let panEl = document.getElementById('edit_pan_number');
                    if (panEl) panEl.value = company.pan_number || '';
                    
                    let cinEl = document.getElementById('edit_cin_number');
                    if (cinEl) cinEl.value = company.cin_number || '';
                    
                    let regEl = document.getElementById('edit_registration_number');
                    if (regEl) regEl.value = company.registration_number || '';
                    
                    let emailEl = document.getElementById('edit_email');
                    if (emailEl) emailEl.value = company.email || '';
                    
                    let phoneEl = document.getElementById('edit_phone');
                    if (phoneEl) phoneEl.value = company.phone || '';
                    
                    let webEl = document.getElementById('edit_website');
                    if (webEl) webEl.value = company.website || '';
                    
                    let currEl = document.getElementById('edit_currency');
                    if (currEl) currEl.value = company.currency || '';
                    
                    let tzEl = document.getElementById('edit_timezone');
                    if (tzEl) tzEl.value = company.timezone || '';
                    
                    let countryEl = document.getElementById('edit_country');
                    if (countryEl) countryEl.value = company.country || '';
                    
                    let stateEl = document.getElementById('edit_state');
                    if (stateEl) stateEl.value = company.state || '';
                    
                    let cityEl = document.getElementById('edit_city');
                    if (cityEl) cityEl.value = company.city || '';
                    
                    let zipEl = document.getElementById('edit_postal_code');
                    if (zipEl) zipEl.value = company.postal_code || '';
                    
                    let addrEl = document.getElementById('edit_address');
                    if (addrEl) addrEl.value = company.address || '';
                    
                    let statusSelect = document.getElementById('edit_status');
                    if (statusSelect) {
                        statusSelect.value = (company.status === true || company.status === 1 || company.status === '1') ? '1' : '0';
                    }
                    
                    let previewImg = document.getElementById('edit_logo_preview');
                    if (previewImg) {
                        previewImg.src = company.logo ? '/storage/' + company.logo : '/assets/images/avatar/1.png';
                    }
                    
                    let form = document.getElementById('company_edit_form');
                    if (form) {
                        form.action = '/hrms/org/company/update/' + company.id;
                    }

                    let companyIdInput = document.getElementById('edit_company_id');
                    if (companyIdInput) {
                        companyIdInput.value = company.id || '';
                    }
                });
            });
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", init);
        } else {
            init();
        }
    })();
</script>
