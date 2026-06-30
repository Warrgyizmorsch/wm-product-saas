<style>
    .company-link {
        transition: all 0.2s ease-in-out;
    }
    .company-link.active-entity {
        background-color: rgba(13, 110, 253, 0.08) !important;
    }
    .company-link.active-entity td:first-child {
        border-left: 4px solid var(--bs-primary, #0d6efd) !important;
    }
</style>

<div class="row">

    <!-- Details Card -->
    <div class="col-xxl-8">
        <div class="card stretch stretch-full">
            <div class="card-body">
                @php
                    $company = $companies->first();
                @endphp
                @if($company)
                <div id="details_view_mode">
                    <div class='d-flex justify-content-between border-bottom pb-3 mb-4'>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-image avatar-lg rounded">
                                <img id="detail_logo" class="img-fluid" src="{{ $company->logo ? asset('storage/' . $company->logo) : asset('assets/images/gallery/1.png') }}" alt="">
                            </div>
                            <div>
                                <h4 class="mb-1" id="detail_company_name">{{ $company->company_name }}</h4>
                                <span class="fs-12 text-muted" id="detail_legal_name">{{ $company->legal_name }}</span>
                            </div>
                        </div>
                        <div>
                            <a id="edit_details_toggle" href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit Entity Details">
                                <i class="feather-edit"></i>
                            </a>
                        </div>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Company Name</label>
                            <p class="mb-3" id="company_name">{{ $company->company_name }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Legal Name</label>
                            <p class="mb-3" id="legal_name">{{ $company->legal_name }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">GST Number</label>
                            <p class="mb-3" id="gst_number">{{ $company->gst_number }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">PAN Number</label>
                            <p class="mb-3" id="pan_number">{{ $company->pan_number }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">CIN Number</label>
                            <p class="mb-3" id="cin_number">{{ $company->cin_number }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Registration Number</label>
                            <p class="mb-3" id="registration_number">{{ $company->registration_number }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Email</label>
                            <p class="mb-3" id="email">{{ $company->email }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Phone</label>
                            <p class="mb-3" id="phone">{{ $company->phone }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Website</label>
                            <p class="mb-3" id="website">{{ $company->website }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Currency</label>
                            <p class="mb-3" id="currency">{{ $company->currency }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Timezone</label>
                            <p class="mb-3" id="timezone">{{ $company->timezone }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Status</label>
                            <p class="mb-3" id="status">
                                @if($company->status)
                                    <span class="badge bg-soft-success text-success">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Country</label>
                            <p class="mb-3" id="country">{{ $company->country }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">State</label>
                            <p class="mb-3" id="state">{{ $company->state }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">City</label>
                            <p class="mb-3" id="city">{{ $company->city }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Postal Code</label>
                            <p class="mb-3" id="postal_code">{{ $company->postal_code }}</p>
                        </div>
                        <div class="col-12">
                            <label class="fs-12 fw-semibold text-muted mb-1">Address</label>
                            <p class="mb-0" id="address">{{ $company->address }}</p>
                        </div>
                    </div> <!-- Close row g-4 -->
                </div> <!-- Close details_view_mode -->

                <!-- Edit Mode Container -->
                <div id="details_edit_mode" class="d-none">
                    <form id="company_edit_form" action="{{ route('hrms.company.update', $company->id ?? 0) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <!-- Logo Section -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Logo: </label>
                            <div class="mb-4 mb-md-0 d-flex gap-4 your-brand">
                                <div class="wd-100 ht-100 position-relative overflow-hidden border border-gray-2 rounded">
                                    <img src="{{ $company->logo ? asset('storage/' . $company->logo) : asset('assets/images/avatar/1.png') }}" class="edit-upload-pic img-fluid rounded h-100 w-100" alt="" id="edit_logo_preview">
                                    <div class="position-absolute start-50 top-50 end-0 translate-middle h-100 w-100 hstack align-items-center justify-content-center c-pointer edit-upload-button">
                                        <i class="feather feather-camera" aria-hidden="true"></i>
                                    </div>
                                    <input class="edit-file-upload" type="file" name="logo" accept="image/*" style="display: none;">
                                </div>
                                <div class="d-flex flex-column gap-1">
                                    <div class="fs-11 text-gray-500 mt-2"># Upload your profile</div>
                                    <div class="fs-11 text-gray-500"># Avatar size 150x150</div>
                                    <div class="fs-11 text-gray-500"># Max upload size 2mb</div>
                                    <div class="fs-11 text-gray-500"># Allowed file types: png, jpg, jpeg</div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Company Name</label>
                                <input type="text" class="form-control" name="company_name" id="edit_company_name" value="{{ $company->company_name }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Legal Name</label>
                                <input type="text" class="form-control" name="legal_name" id="edit_legal_name" value="{{ $company->legal_name }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">GST Number</label>
                                <input type="text" class="form-control" name="gst_number" id="edit_gst_number" value="{{ $company->gst_number }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">PAN Number</label>
                                <input type="text" class="form-control" name="pan_number" id="edit_pan_number" value="{{ $company->pan_number }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">CIN Number</label>
                                <input type="text" class="form-control" name="cin_number" id="edit_cin_number" value="{{ $company->cin_number }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Registration Number</label>
                                <input type="text" class="form-control" name="registration_number" id="edit_registration_number" value="{{ $company->registration_number }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email" value="{{ $company->email }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="text" class="form-control" name="phone" id="edit_phone" value="{{ $company->phone }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Website</label>
                                <input type="text" class="form-control" name="website" id="edit_website" value="{{ $company->website }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Currency</label>
                                <input type="text" class="form-control" name="currency" id="edit_currency" value="{{ $company->currency }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Timezone</label>
                                <input type="text" class="form-control" name="time_zone" id="edit_timezone" value="{{ $company->timezone }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-control" name="status" id="edit_status">
                                    <option value="1" {{ $company->status ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !$company->status ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Country</label>
                                <input type="text" class="form-control" name="country" id="edit_country" value="{{ $company->country }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">State</label>
                                <input type="text" class="form-control" name="state" id="edit_state" value="{{ $company->state }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">City</label>
                                <input type="text" class="form-control" name="city" id="edit_city" value="{{ $company->city }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" id="edit_postal_code" value="{{ $company->postal_code }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Address</label>
                                <textarea class="form-control" name="address" id="edit_address" rows="3">{{ $company->address }}</textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-light me-2" id="cancel_edit_btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Details</button>
                        </div>
                    </form>
                </div>
                @else
                <div class="text-center py-5">
                    <p class="text-muted mb-0">No Legal Entities found. Click "Add Legal Entity" to create one.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar List Card -->
    <div class="col-xxl-4 h-100">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Legal Entities</h5>
                <a href="/hrms/org/company/create" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Add Company">
                        <i class="feather-plus"></i>
                        <!-- <span>Add</span> -->
                    </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <tbody>
                        @foreach($companies as $company)
                        <tr class="company-link @if($loop->first) active-entity @endif"
                            data-company='@json($company)'
                            style="cursor: pointer;">

                            <td>
                                <div class="hstack gap-3">
                                    <div class="avatar-image avatar-lg rounded">
                                        <img class="img-fluid"
                                            src="{{ $company->logo ? asset('storage/' . $company->logo) : asset('assets/images/gallery/1.png') }}"
                                            alt="">
                                    </div>
                                    <div>
                                        <span class="d-block fw-semibold">
                                            {{ $company->company_name }}
                                        </span>
                                        <span class="fs-12 text-muted">
                                            {{ $company->legal_name }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- <td width="220">
                                <small class="text-muted d-block">GST Number</small>
                                <span>{{ $company->gst_number ?? 'N/A' }}</span>
                            </td> -->

                            <td class="text-end">
                                @if($company->status)
                                    <span class="badge bg-soft-success text-success">
                                        Active
                                    </span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Toggle Edit Mode Logic
        let isEditMode = false;
        const viewModeEl = document.getElementById('details_view_mode');
        const editModeEl = document.getElementById('details_edit_mode');
        const toggleBtn = document.getElementById('edit_details_toggle');
        const cancelBtn = document.getElementById('cancel_edit_btn');

        function toggleEdit(edit) {
            isEditMode = edit;
            if (!viewModeEl || !editModeEl) return;
            if (isEditMode) {
                viewModeEl.classList.add('d-none');
                editModeEl.classList.remove('d-none');
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="feather-x"></i>';
                    toggleBtn.setAttribute('title', 'Cancel Edit');
                }
            } else {
                viewModeEl.classList.remove('d-none');
                editModeEl.classList.add('d-none');
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="feather-edit"></i>';
                    toggleBtn.setAttribute('title', 'Edit Entity Details');
                }
            }
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleEdit(!isEditMode);
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleEdit(false);
            });
        }

        // Edit Mode Image Upload preview script (vanilla JS)
        document.addEventListener("click", function(e) {
            if (e.target && (e.target.classList.contains("edit-upload-button") || e.target.closest(".edit-upload-button"))) {
                let fileUploadInput = document.querySelector(".edit-file-upload");
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
        });

        // Sidebar click handler to select company
        document.querySelectorAll('.company-link').forEach(link => {
            link.addEventListener('click', function(e){
                e.preventDefault();

                // Close edit mode on company switch
                toggleEdit(false);

                // Toggle active highlights on list rows
                document.querySelectorAll('.company-link').forEach(row => {
                    row.classList.remove('active-entity');
                });
                this.classList.add('active-entity');

                let company = JSON.parse(this.dataset.company);

                // Update text fields dynamically
                const textFields = {
                    'company_name': company.company_name,
                    'legal_name': company.legal_name,
                    'gst_number': company.gst_number,
                    'pan_number': company.pan_number,
                    'cin_number': company.cin_number,
                    'registration_number': company.registration_number,
                    'email': company.email,
                    'phone': company.phone,
                    'website': company.website,
                    'address': company.address,
                    'city': company.city,
                    'state': company.state,
                    'country': company.country,
                    'postal_code': company.postal_code,
                    'currency': company.currency,
                    'timezone': company.timezone,
                    'detail_company_name': company.company_name,
                    'detail_legal_name': company.legal_name
                };

                for (const [id, value] of Object.entries(textFields)) {
                    let el = document.getElementById(id);
                    if (el) {
                        el.innerText = value || '';
                    }
                }

                // Update edit fields dynamically
                const editFields = {
                    'edit_company_name': company.company_name,
                    'edit_legal_name': company.legal_name,
                    'edit_gst_number': company.gst_number,
                    'edit_pan_number': company.pan_number,
                    'edit_cin_number': company.cin_number,
                    'edit_registration_number': company.registration_number,
                    'edit_email': company.email,
                    'edit_phone': company.phone,
                    'edit_website': company.website,
                    'edit_currency': company.currency,
                    'edit_timezone': company.timezone,
                    'edit_country': company.country,
                    'edit_state': company.state,
                    'edit_city': company.city,
                    'edit_postal_code': company.postal_code,
                    'edit_address': company.address
                };

                for (const [id, value] of Object.entries(editFields)) {
                    let el = document.getElementById(id);
                    if (el) {
                        el.value = value || '';
                    }
                }

                // Update edit status select option
                let statusSelect = document.getElementById('edit_status');
                if (statusSelect) {
                    statusSelect.value = (company.status === true || company.status === 1 || company.status === '1' || company.status === 'success' || company.status === 'active') ? '1' : '0';
                }

                // Update edit logo preview
                let editLogoPreview = document.getElementById('edit_logo_preview');
                if (editLogoPreview) {
                    editLogoPreview.src = company.logo ? '/storage/' + company.logo : '/assets/images/avatar/1.png';
                }

                // Update form action URL to point to current company
                let editForm = document.getElementById('company_edit_form');
                if (editForm) {
                    editForm.action = '/hrms/org/company/update/' + company.id;
                }

                // Update Detail Logo
                let logoEl = document.getElementById('detail_logo');
                if (logoEl) {
                    logoEl.src = company.logo ? '/storage/' + company.logo : '/assets/images/gallery/1.png';
                }

                // Update Status Badge
                let statusEl = document.getElementById('status');
                if (statusEl) {
                    if (company.status === true || company.status === 1 || company.status === '1' || company.status === 'success' || company.status === 'active') {
                        statusEl.innerHTML = '<span class="badge bg-soft-success text-success">Active</span>';
                    } else {
                        statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">Inactive</span>';
                    }
                }
            });
        });
    });
</script>
