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
                                <div class="d-flex justify-content-end gap-1">
                                    <x-ui.icon-btn variant="soft-primary" icon="feather-eye" class="btn-view-branch" data-bs-toggle="modal" data-bs-target="#viewBranchModal" data-branch="{{ base64_encode($br->toJson()) }}" title="View" />
                                    <x-ui.icon-btn variant="soft-info" icon="feather-edit" class="btn-edit-branch" data-bs-toggle="modal" data-bs-target="#editBranchModal" data-branch="{{ base64_encode($br->toJson()) }}" title="Edit" />
                                    <form action="{{ route('hrms.branch.destroy', $br->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this branch?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" icon="feather-trash-2" title="Delete" />
                                    </form>
                                </div>
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
    document.addEventListener("DOMContentLoaded", function() {
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
                
                document.getElementById('modal_view_branch_name').innerText = branch.name;
                document.getElementById('modal_view_branch_bu').innerText = (branch.business_unit && branch.business_unit.name) ? branch.business_unit.name : ((branch.company && branch.company.company_name) ? branch.company.company_name : 'N/A');
                document.getElementById('modal_view_branch_code').innerText = branch.code;
                document.getElementById('modal_view_branch_manager').innerText = (branch.manager) ? (branch.manager.first_name + ' ' + branch.manager.last_name) : 'N/A';
                document.getElementById('modal_view_branch_phone').innerText = branch.phone || 'N/A';
                document.getElementById('modal_view_branch_email').innerText = branch.email || 'N/A';
                document.getElementById('modal_view_branch_country').innerText = branch.country || 'N/A';
                document.getElementById('modal_view_branch_state').innerText = branch.state || 'N/A';
                document.getElementById('modal_view_branch_city').innerText = branch.city || 'N/A';
                document.getElementById('modal_view_branch_zip').innerText = branch.postal_code || 'N/A';
                document.getElementById('modal_view_branch_address').innerText = branch.address || 'N/A';
                
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
                
                document.getElementById('edit_branch_name').value = branch.name || '';
                document.getElementById('edit_branch_code').value = branch.code || '';
                document.getElementById('edit_branch_bu_id').value = branch.business_unit_id || '';
                document.getElementById('edit_branch_company_id').value = branch.company_id || '';
                document.getElementById('edit_branch_manager_id').value = branch.manager_employee_id || '';
                document.getElementById('edit_branch_phone').value = branch.phone || '';
                document.getElementById('edit_branch_email').value = branch.email || '';
                document.getElementById('edit_branch_country').value = branch.country || '';
                document.getElementById('edit_branch_state').value = branch.state || '';
                document.getElementById('edit_branch_city').value = branch.city || '';
                document.getElementById('edit_branch_postal_code').value = branch.postal_code || '';
                document.getElementById('edit_branch_address').value = branch.address || '';
                
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
    });
</script>
