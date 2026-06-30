<style>
    .branch-link {
        transition: all 0.2s ease-in-out;
    }
    .branch-link.active-entity {
        background-color: rgba(13, 110, 253, 0.08) !important;
    }
    .branch-link.active-entity td:first-child {
        border-left: 4px solid var(--bs-primary, #0d6efd) !important;
    }
</style>

<div class="row">

    <!-- Details Card -->
    <div class="col-xxl-8">
        <div class="card stretch stretch-full">
            <div class="card-body">
                @php
                    $branch = $branches->first();
                @endphp
                @if($branch)
                <div id="branch_details_view_mode">
                    <div class="d-flex justify-content-between">
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                            <div class="avatar-text avatar-lg rounded bg-soft-primary text-primary" id="detail_branch_avatar" style="width: 50px; height: 50px; min-width: 50px; min-height: 50px; font-size: 16px;">
                                {{ substr($branch->name ?? 'BR', 0, 2) }}
                            </div>
                            <div>
                                <h4 class="mb-1" id="detail_branch_name">{{ $branch->name }}</h4>
                                <span class="fs-12 text-muted" id="detail_branch_bu_name">{{ $branch->businessUnit->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <a id="edit_branch_details_toggle" href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit Branch Details">
                            <i class="feather-edit"></i>
                        </a>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Branch Name</label>
                            <p class="mb-3" id="branch_name">{{ $branch->name }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Branch Code</label>
                            <p class="mb-3" id="branch_code">{{ $branch->code }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Parent Business Unit</label>
                            <p class="mb-3" id="branch_bu_name_text">{{ $branch->businessUnit->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Branch Manager</label>
                            <p class="mb-3" id="branch_manager_name">
                                {{ $branch->manager ? ($branch->manager->first_name . ' ' . $branch->manager->last_name) : 'N/A' }}
                            </p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Phone</label>
                            <p class="mb-3" id="branch_phone">{{ $branch->phone ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Email</label>
                            <p class="mb-3" id="branch_email">{{ $branch->email ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Status</label>
                            <p class="mb-3" id="branch_status">
                                @if($branch->status)
                                    <span class="badge bg-soft-success text-success">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Country</label>
                            <p class="mb-3" id="branch_country">{{ $branch->country ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">State</label>
                            <p class="mb-3" id="branch_state">{{ $branch->state ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">City</label>
                            <p class="mb-3" id="branch_city">{{ $branch->city ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Postal Code</label>
                            <p class="mb-3" id="branch_postal_code">{{ $branch->postal_code ?? 'N/A' }}</p>
                        </div>
                        <div class="col-12">
                            <label class="fs-12 fw-semibold text-muted mb-1">Address</label>
                            <p class="mb-0" id="branch_address">{{ $branch->address ?? 'N/A' }}</p>
                        </div>
                    </div> <!-- Close row g-4 -->
                </div> <!-- Close branch_details_view_mode -->

                <!-- Edit Mode Container -->
                <div id="branch_details_edit_mode" class="d-none">
                    <form id="branch_edit_form" action="{{ route('hrms.branch.update', $branch->id ?? 0) }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Branch Name</label>
                                <input type="text" class="form-control" name="name" id="edit_branch_name" value="{{ $branch->name }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Branch Code</label>
                                <input type="text" class="form-control" name="code" id="edit_branch_code" value="{{ $branch->code }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Parent Business Unit</label>
                                <select class="form-control" name="business_unit_id" id="edit_branch_bu_id">
                                    @foreach($businessUnits as $buUnit)
                                        <option value="{{ $buUnit->id }}" {{ $buUnit->id == $branch->business_unit_id ? 'selected' : '' }}>
                                            {{ $buUnit->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Branch Manager</label>
                                <select class="form-control" name="manager_employee_id" id="edit_branch_manager_id">
                                    <option value="">Select Manager</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ $employee->id == $branch->manager_employee_id ? 'selected' : '' }}>
                                            {{ $employee->first_name }} {{ $employee->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="text" class="form-control" name="phone" id="edit_branch_phone" value="{{ $branch->phone }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_branch_email" value="{{ $branch->email }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-control" name="status" id="edit_branch_status">
                                    <option value="1" {{ $branch->status ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !$branch->status ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Country</label>
                                <input type="text" class="form-control" name="country" id="edit_branch_country" value="{{ $branch->country }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">State</label>
                                <input type="text" class="form-control" name="state" id="edit_branch_state" value="{{ $branch->state }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">City</label>
                                <input type="text" class="form-control" name="city" id="edit_branch_city" value="{{ $branch->city }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" id="edit_branch_postal_code" value="{{ $branch->postal_code }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Address</label>
                                <textarea class="form-control" name="address" id="edit_branch_address" rows="3">{{ $branch->address }}</textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-light me-2" id="cancel_branch_edit_btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Details</button>
                        </div>
                    </form>
                </div>
                @else
                <div class="text-center py-5">
                    <p class="text-muted mb-0">No Branches found. Click "Add Branch" to create one.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar List Card -->
    <div class="col-xxl-4 h-100">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Branches</h5>
                <a href="/hrms/org/branch/create" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Add Branch">
                    <i class="feather-plus"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <tbody>
                        @foreach($branches as $br)
                        <tr class="branch-link @if($loop->first) active-entity @endif"
                            data-branch='@json($br)'
                            style="cursor: pointer;">

                            <td>
                                <div class="hstack gap-3">
                                    <div class="avatar-text avatar-lg rounded bg-soft-primary text-primary" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                        {{ substr($br->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <span class="d-block fw-semibold">
                                            {{ $br->name }}
                                        </span>
                                        <span class="fs-12 text-muted">
                                            {{ $br->businessUnit->name ?? 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- <td width="120">
                                <small class="text-muted d-block">Code</small>
                                <span>{{ $br->code }}</span>
                            </td> -->

                            <td class="text-end">
                                @if($br->status)
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
        let isBranchEditMode = false;
        const viewModeBranch = document.getElementById('branch_details_view_mode');
        const editModeBranch = document.getElementById('branch_details_edit_mode');
        const toggleBranchBtn = document.getElementById('edit_branch_details_toggle');
        const cancelBranchBtn = document.getElementById('cancel_branch_edit_btn');

        function toggleBranchEdit(edit) {
            isBranchEditMode = edit;
            if (!viewModeBranch || !editModeBranch) return;
            if (isBranchEditMode) {
                viewModeBranch.classList.add('d-none');
                editModeBranch.classList.remove('d-none');
                if (toggleBranchBtn) {
                    toggleBranchBtn.innerHTML = '<i class="feather-x"></i>';
                    toggleBranchBtn.setAttribute('title', 'Cancel Edit');
                }
            } else {
                viewModeBranch.classList.remove('d-none');
                editModeBranch.classList.add('d-none');
                if (toggleBranchBtn) {
                    toggleBranchBtn.innerHTML = '<i class="feather-edit"></i>';
                    toggleBranchBtn.setAttribute('title', 'Edit Branch Details');
                }
            }
        }

        if (toggleBranchBtn) {
            toggleBranchBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleBranchEdit(!isBranchEditMode);
            });
        }

        if (cancelBranchBtn) {
            cancelBranchBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleBranchEdit(false);
            });
        }

        // Sidebar click handler to select branch
        document.querySelectorAll('.branch-link').forEach(link => {
            link.addEventListener('click', function(e){
                e.preventDefault();

                // Close edit mode on switch
                toggleBranchEdit(false);

                // Toggle active highlights on list rows
                document.querySelectorAll('.branch-link').forEach(row => {
                    row.classList.remove('active-entity');
                });
                this.classList.add('active-entity');

                let branch = JSON.parse(this.dataset.branch);

                // Update text fields dynamically
                if (document.getElementById('branch_name')) document.getElementById('branch_name').innerText = branch.name || '';
                if (document.getElementById('branch_code')) document.getElementById('branch_code').innerText = branch.code || '';
                if (document.getElementById('branch_bu_name_text')) {
                    document.getElementById('branch_bu_name_text').innerText = (branch.business_unit && branch.business_unit.name) ? branch.business_unit.name : 'N/A';
                }
                if (document.getElementById('branch_manager_name')) {
                    document.getElementById('branch_manager_name').innerText = (branch.manager) ? (branch.manager.first_name + ' ' + branch.manager.last_name) : 'N/A';
                }
                if (document.getElementById('branch_phone')) document.getElementById('branch_phone').innerText = branch.phone || 'N/A';
                if (document.getElementById('branch_email')) document.getElementById('branch_email').innerText = branch.email || 'N/A';
                if (document.getElementById('branch_country')) document.getElementById('branch_country').innerText = branch.country || 'N/A';
                if (document.getElementById('branch_state')) document.getElementById('branch_state').innerText = branch.state || 'N/A';
                if (document.getElementById('branch_city')) document.getElementById('branch_city').innerText = branch.city || 'N/A';
                if (document.getElementById('branch_postal_code')) document.getElementById('branch_postal_code').innerText = branch.postal_code || 'N/A';
                if (document.getElementById('branch_address')) document.getElementById('branch_address').innerText = branch.address || 'N/A';
                
                if (document.getElementById('detail_branch_name')) document.getElementById('detail_branch_name').innerText = branch.name || '';
                if (document.getElementById('detail_branch_bu_name')) {
                    document.getElementById('detail_branch_bu_name').innerText = (branch.business_unit && branch.business_unit.name) ? branch.business_unit.name : 'N/A';
                }

                // Update Initials Avatar
                let avatarEl = document.getElementById('detail_branch_avatar');
                if (avatarEl) {
                    avatarEl.innerText = (branch.name) ? branch.name.substring(0, 2) : 'BR';
                }

                // Update edit fields dynamically
                if (document.getElementById('edit_branch_name')) document.getElementById('edit_branch_name').value = branch.name || '';
                if (document.getElementById('edit_branch_code')) document.getElementById('edit_branch_code').value = branch.code || '';
                if (document.getElementById('edit_branch_bu_id')) document.getElementById('edit_branch_bu_id').value = branch.business_unit_id || '';
                if (document.getElementById('edit_branch_manager_id')) document.getElementById('edit_branch_manager_id').value = branch.manager_employee_id || '';
                if (document.getElementById('edit_branch_phone')) document.getElementById('edit_branch_phone').value = branch.phone || '';
                if (document.getElementById('edit_branch_email')) document.getElementById('edit_branch_email').value = branch.email || '';
                if (document.getElementById('edit_branch_country')) document.getElementById('edit_branch_country').value = branch.country || '';
                if (document.getElementById('edit_branch_state')) document.getElementById('edit_branch_state').value = branch.state || '';
                if (document.getElementById('edit_branch_city')) document.getElementById('edit_branch_city').value = branch.city || '';
                if (document.getElementById('edit_branch_postal_code')) document.getElementById('edit_branch_postal_code').value = branch.postal_code || '';
                if (document.getElementById('edit_branch_address')) document.getElementById('edit_branch_address').value = branch.address || '';
                if (document.getElementById('edit_branch_status')) {
                    document.getElementById('edit_branch_status').value = (branch.status === true || branch.status === 1 || branch.status === '1') ? '1' : '0';
                }

                // Update form action URL to point to current branch
                let editForm = document.getElementById('branch_edit_form');
                if (editForm) {
                    editForm.action = '/hrms/org/branch/update/' + branch.id;
                }

                // Update Status Badge
                let statusEl = document.getElementById('branch_status');
                if (statusEl) {
                    if (branch.status === true || branch.status === 1 || branch.status === '1') {
                        statusEl.innerHTML = '<span class="badge bg-soft-success text-success">Active</span>';
                    } else {
                        statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">Inactive</span>';
                    }
                }
            });
        });
    });
</script>
