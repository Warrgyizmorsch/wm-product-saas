<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="Branches" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                    Add Branch
                </x-ui.button>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">#</th>
                            <!-- <th width="80">Avatar</th> -->
                            <th>Branch Name</th>
                            <th>Branch Code</th>
                            <th>Business Unit</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th width="150" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($branches as $br)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <!-- <td>
                                <div class="avatar-text avatar-md rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center fw-bold fs-12" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                    {{ substr($br->name ?? 'BR', 0, 2) }}
                                </div>
                            </td> -->
                            <td><span class="fw-bold text-dark">{{ $br->name }}</span></td>
                            <td><code>{{ $br->code }}</code></td>
                            <td>{{ $br->businessUnit->name ?? ($br->company->company_name ?? 'N/A') }}</td>
                            <td>{{ $br->manager ? ($br->manager->first_name . ' ' . $br->manager->last_name) : 'N/A' }}</td>
                            <td>
                                @if($br->status)
                                    <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('hrms.branch.destroy', $br->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this branch?');">
                                    @csrf
                                    @method('DELETE')
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="javascript:void(0)" class="action-dropdown-btn btn-view-branch" data-bs-toggle="modal" data-bs-target="#viewBranchModal" data-branch="{{ base64_encode($br->toJson()) }}" title="View Details" data-bs-toggle="tooltip">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item btn-edit-branch" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editBranchModal" data-branch="{{ base64_encode($br->toJson()) }}">
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
                        @if($branches->isEmpty())
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                No Branches found. Click "Add Branch" to create one.
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
    (function() {
        function init() {
            function getInitials(name, fallback) {
                const words = String(name || fallback || '').trim().split(/\s+/).filter(Boolean);

                if (words.length >= 2) {
                    return (words[0][0] + words[1][0]).toUpperCase();
                }

                return (words[0] || fallback || '').substring(0, 2).toUpperCase();
            }

            // View Action Trigger
            document.querySelectorAll('.btn-view-branch').forEach(btn => {
                btn.addEventListener('click', function() {
                    let branch = JSON.parse(atob(this.dataset.branch));
                    
                    let nameEl = document.getElementById('modal_view_branch_name');
                    if (nameEl) nameEl.innerText = branch.name;
                    
                    let buEl = document.getElementById('modal_view_branch_bu');
                    if (buEl) buEl.innerText = (branch.business_unit && branch.business_unit.name) ? branch.business_unit.name : ((branch.company && branch.company.company_name) ? branch.company.company_name : 'N/A');
                    
                    let codeEl = document.getElementById('modal_view_branch_code');
                    if (codeEl) codeEl.innerText = branch.code;
                    
                    let managerEl = document.getElementById('modal_view_branch_manager');
                    if (managerEl) managerEl.innerText = (branch.manager) ? (branch.manager.first_name + ' ' + branch.manager.last_name) : 'N/A';
                    
                    let phoneEl = document.getElementById('modal_view_branch_phone');
                    if (phoneEl) phoneEl.innerText = branch.phone || 'N/A';
                    
                    let emailEl = document.getElementById('modal_view_branch_email');
                    if (emailEl) emailEl.innerText = branch.email || 'N/A';
                    
                    let countryEl = document.getElementById('modal_view_branch_country');
                    if (countryEl) countryEl.innerText = branch.country || 'N/A';
                    
                    let stateEl = document.getElementById('modal_view_branch_state');
                    if (stateEl) stateEl.innerText = branch.state || 'N/A';
                    
                    let cityEl = document.getElementById('modal_view_branch_city');
                    if (cityEl) cityEl.innerText = branch.city || 'N/A';
                    
                    let zipEl = document.getElementById('modal_view_branch_zip');
                    if (zipEl) zipEl.innerText = branch.postal_code || 'N/A';
                    
                    let addressEl = document.getElementById('modal_view_branch_address');
                    if (addressEl) addressEl.innerText = branch.address || 'N/A';
                    
                    let avatarEl = document.getElementById('modal_view_branch_avatar');
                    if (avatarEl) {
                        avatarEl.innerText = getInitials(branch.name, 'BR');
                    }
                    
                    let statusEl = document.getElementById('modal_view_branch_status');
                    if (statusEl) {
                        if (branch.status === true || branch.status === 1 || branch.status === '1') {
                            statusEl.innerHTML = '<span class="badge bg-soft-success text-success fw-bold fs-13">Active</span>';
                        } else {
                            statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger fw-bold fs-13">Inactive</span>';
                        }
                    }
                });
            });

            // Edit Action Trigger
            document.querySelectorAll('.btn-edit-branch').forEach(btn => {
                btn.addEventListener('click', function() {
                    let branch = JSON.parse(atob(this.dataset.branch));
                    
                    let nameEl = document.getElementById('edit_branch_name');
                    if (nameEl) nameEl.value = branch.name || '';
                    
                    let codeEl = document.getElementById('edit_branch_code');
                    if (codeEl) codeEl.value = branch.code || '';
                    
                    let buEl = document.getElementById('edit_branch_bu_id');
                    if (buEl) buEl.value = branch.business_unit_id || '';
                    
                    let companyEl = document.getElementById('edit_branch_company_id');
                    if (companyEl) companyEl.value = branch.company_id || '';
                    
                    let managerEl = document.getElementById('edit_branch_manager_id');
                    if (managerEl) managerEl.value = branch.manager_employee_id || '';
                    
                    let phoneEl = document.getElementById('edit_branch_phone');
                    if (phoneEl) phoneEl.value = branch.phone || '';
                    
                    let emailEl = document.getElementById('edit_branch_email');
                    if (emailEl) emailEl.value = branch.email || '';
                    
                    let countryEl = document.getElementById('edit_branch_country');
                    if (countryEl) countryEl.value = branch.country || '';
                    
                    let stateEl = document.getElementById('edit_branch_state');
                    if (stateEl) stateEl.value = branch.state || '';
                    
                    let cityEl = document.getElementById('edit_branch_city');
                    if (cityEl) cityEl.value = branch.city || '';
                    
                    let postalEl = document.getElementById('edit_branch_postal_code');
                    if (postalEl) postalEl.value = branch.postal_code || '';
                    
                    let addressEl = document.getElementById('edit_branch_address');
                    if (addressEl) addressEl.value = branch.address || '';
                    
                    let statusSelect = document.getElementById('edit_branch_status');
                    if (statusSelect) {
                        statusSelect.value = (branch.status === true || branch.status === 1 || branch.status === '1') ? '1' : '0';
                    }
                    
                    let form = document.getElementById('branch_edit_form');
                    if (form) {
                        form.action = '/hrms/org/branch/update/' + branch.id;
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
