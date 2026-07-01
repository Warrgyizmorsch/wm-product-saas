<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="Legal Entities" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                    Add Legal Entity
                </x-ui.button>
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
                    <tbody>
                        @foreach($companies as $company)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
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
                                <div class="d-flex justify-content-end gap-1">
                                    <x-ui.icon-btn variant="soft-primary" icon="feather-eye" class="btn-view-company" data-bs-toggle="modal" data-bs-target="#viewCompanyModal" data-company="{{ base64_encode($company->toJson()) }}" title="View" />
                                    <x-ui.icon-btn variant="soft-info" icon="feather-edit" class="btn-edit-company" data-bs-toggle="modal" data-bs-target="#editCompanyModal" data-company="{{ base64_encode($company->toJson()) }}" title="Edit" />
                                    <form action="{{ route('hrms.company.destroy', $company->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this company?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" icon="feather-trash-2" title="Delete" />
                                    </form>
                                </div>
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
        </x-ui.card>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
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
                
                document.getElementById('modal_view_company_name').innerText = company.company_name;
                document.getElementById('modal_view_legal_name').innerText = company.legal_name || 'N/A';
                document.getElementById('modal_view_gst').innerText = company.gst_number || 'N/A';
                document.getElementById('modal_view_pan').innerText = company.pan_number || 'N/A';
                document.getElementById('modal_view_cin').innerText = company.cin_number || 'N/A';
                document.getElementById('modal_view_reg').innerText = company.registration_number || 'N/A';
                document.getElementById('modal_view_email').innerText = company.email || 'N/A';
                document.getElementById('modal_view_phone').innerText = company.phone || 'N/A';
                document.getElementById('modal_view_currency').innerText = company.currency || 'N/A';
                document.getElementById('modal_view_timezone').innerText = company.timezone || 'N/A';
                document.getElementById('modal_view_country').innerText = company.country || 'N/A';
                document.getElementById('modal_view_state').innerText = company.state || 'N/A';
                document.getElementById('modal_view_city').innerText = company.city || 'N/A';
                document.getElementById('modal_view_zip').innerText = company.postal_code || 'N/A';
                document.getElementById('modal_view_address').innerText = company.address || 'N/A';
                
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
                
                document.getElementById('edit_company_name').value = company.company_name || '';
                document.getElementById('edit_legal_name').value = company.legal_name || '';
                document.getElementById('edit_gst_number').value = company.gst_number || '';
                document.getElementById('edit_pan_number').value = company.pan_number || '';
                document.getElementById('edit_cin_number').value = company.cin_number || '';
                document.getElementById('edit_registration_number').value = company.registration_number || '';
                document.getElementById('edit_email').value = company.email || '';
                document.getElementById('edit_phone').value = company.phone || '';
                document.getElementById('edit_website').value = company.website || '';
                document.getElementById('edit_currency').value = company.currency || '';
                document.getElementById('edit_timezone').value = company.timezone || '';
                document.getElementById('edit_country').value = company.country || '';
                document.getElementById('edit_state').value = company.state || '';
                document.getElementById('edit_city').value = company.city || '';
                document.getElementById('edit_postal_code').value = company.postal_code || '';
                document.getElementById('edit_address').value = company.address || '';
                
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
            });
        });
    });
</script>
