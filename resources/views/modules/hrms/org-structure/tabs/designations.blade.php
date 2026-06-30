<style>
    .desig-link {
        transition: all 0.2s ease-in-out;
    }
    .desig-link.active-entity {
        background-color: rgba(13, 110, 253, 0.08) !important;
    }
    .desig-link.active-entity td:first-child {
        border-left: 4px solid var(--bs-primary, #0d6efd) !important;
    }
</style>

<div class="row">

    <!-- Details Card -->
    <div class="col-xxl-8">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Designation Details</h5>
                
            </div>
            <div class="card-body">
                @php
                    $desig = $designations->first();
                @endphp
                @if($desig)
                <div id="desig_details_view_mode">
                    <div class="d-flex justify-content-between">
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                            <div class="avatar-text avatar-lg rounded bg-soft-primary text-primary" id="detail_desig_avatar" style="width: 50px; height: 50px; min-width: 50px; min-height: 50px; font-size: 16px;">
                                {{ substr($desig->name ?? 'DS', 0, 2) }}
                            </div>
                            <div>
                                <h4 class="mb-1" id="detail_desig_name">{{ $desig->name }}</h4>
                                <span class="fs-12 text-muted" id="detail_desig_dept_name">{{ $desig->department->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <a id="edit_desig_details_toggle" href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit Designation Details">
                            <i class="feather-edit"></i>
                        </a>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Designation Name</label>
                            <p class="mb-3" id="desig_name">{{ $desig->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Level</label>
                            <p class="mb-3" id="desig_level">{{ $desig->level ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Parent Department</label>
                            <p class="mb-3" id="desig_dept_name_text">{{ $desig->department->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Status</label>
                            <p class="mb-3" id="desig_status">
                                @if($desig->status)
                                    <span class="badge bg-soft-success text-success">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="fs-12 fw-semibold text-muted mb-1">Description</label>
                            <p class="mb-0" id="desig_description">{{ $desig->description ?? 'No description provided.' }}</p>
                        </div>
                    </div> <!-- Close row g-4 -->
                </div> <!-- Close desig_details_view_mode -->

                <!-- Edit Mode Container -->
                <div id="desig_details_edit_mode" class="d-none">
                    <form id="desig_edit_form" action="{{ route('hrms.designation.update', $desig->id ?? 0) }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Designation Name</label>
                                <input type="text" class="form-control" name="name" id="edit_desig_name" value="{{ $desig->name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Level</label>
                                <input type="text" class="form-control" name="level" id="edit_desig_level" value="{{ $desig->level }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Parent Department</label>
                                <select class="form-control" name="department_id" id="edit_desig_dept_id">
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" {{ $department->id == $desig->department_id ? 'selected' : '' }}>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-control" name="status" id="edit_desig_status">
                                    <option value="1" {{ $desig->status ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !$desig->status ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" name="description" id="edit_desig_description" rows="3">{{ $desig->description }}</textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-light me-2" id="cancel_desig_edit_btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Details</button>
                        </div>
                    </form>
                </div>
                @else
                <div class="text-center py-5">
                    <p class="text-muted mb-0">No Designations found. Click "Add Designation" to create one.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar List Card -->
    <div class="col-xxl-4 h-100">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Designations</h5>
                <a href="/hrms/org/designation/create" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Add Designation">
                    <i class="feather-plus"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <tbody>
                        @foreach($designations as $ds)
                        <tr class="desig-link @if($loop->first) active-entity @endif"
                            data-desig='@json($ds)'
                            style="cursor: pointer;">

                            <td>
                                <div class="hstack gap-3">
                                    <div class="avatar-text avatar-lg rounded bg-soft-primary text-primary" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                        {{ substr($ds->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <span class="d-block fw-semibold">
                                            {{ $ds->name }}
                                        </span>
                                        <span class="fs-12 text-muted">
                                            {{ $ds->department->name ?? 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- <td width="120">
                                <small class="text-muted d-block">Level</small>
                                <span>{{ $ds->level ?? 'N/A' }}</span>
                            </td> -->

                            <td class="text-end">
                                @if($ds->status)
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
        let isDesigEditMode = false;
        const viewModeDesig = document.getElementById('desig_details_view_mode');
        const editModeDesig = document.getElementById('desig_details_edit_mode');
        const toggleDesigBtn = document.getElementById('edit_desig_details_toggle');
        const cancelDesigBtn = document.getElementById('cancel_desig_edit_btn');

        function toggleDesigEdit(edit) {
            isDesigEditMode = edit;
            if (!viewModeDesig || !editModeDesig) return;
            if (isDesigEditMode) {
                viewModeDesig.classList.add('d-none');
                editModeDesig.classList.remove('d-none');
                if (toggleDesigBtn) {
                    toggleDesigBtn.innerHTML = '<i class="feather-x"></i>';
                    toggleDesigBtn.setAttribute('title', 'Cancel Edit');
                }
            } else {
                viewModeDesig.classList.remove('d-none');
                editModeDesig.classList.add('d-none');
                if (toggleDesigBtn) {
                    toggleDesigBtn.innerHTML = '<i class="feather-edit"></i>';
                    toggleDesigBtn.setAttribute('title', 'Edit Designation Details');
                }
            }
        }

        if (toggleDesigBtn) {
            toggleDesigBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleDesigEdit(!isDesigEditMode);
            });
        }

        if (cancelDesigBtn) {
            cancelDesigBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleDesigEdit(false);
            });
        }

        // Sidebar click handler to select designation
        document.querySelectorAll('.desig-link').forEach(link => {
            link.addEventListener('click', function(e){
                e.preventDefault();

                // Close edit mode on switch
                toggleDesigEdit(false);

                // Toggle active highlights on list rows
                document.querySelectorAll('.desig-link').forEach(row => {
                    row.classList.remove('active-entity');
                });
                this.classList.add('active-entity');

                let desig = JSON.parse(this.dataset.desig);

                // Update text fields dynamically
                if (document.getElementById('desig_name')) document.getElementById('desig_name').innerText = desig.name || '';
                if (document.getElementById('desig_level')) document.getElementById('desig_level').innerText = desig.level || 'N/A';
                if (document.getElementById('desig_dept_name_text')) {
                    document.getElementById('desig_dept_name_text').innerText = (desig.department && desig.department.name) ? desig.department.name : 'N/A';
                }
                if (document.getElementById('desig_description')) document.getElementById('desig_description').innerText = desig.description || 'No description provided.';
                if (document.getElementById('detail_desig_name')) document.getElementById('detail_desig_name').innerText = desig.name || '';
                if (document.getElementById('detail_desig_dept_name')) {
                    document.getElementById('detail_desig_dept_name').innerText = (desig.department && desig.department.name) ? desig.department.name : 'N/A';
                }

                // Update Initials Avatar
                let avatarEl = document.getElementById('detail_desig_avatar');
                if (avatarEl) {
                    avatarEl.innerText = (desig.name) ? desig.name.substring(0, 2) : 'DS';
                }

                // Update edit fields dynamically
                if (document.getElementById('edit_desig_name')) document.getElementById('edit_desig_name').value = desig.name || '';
                if (document.getElementById('edit_desig_level')) document.getElementById('edit_desig_level').value = desig.level || '';
                if (document.getElementById('edit_desig_dept_id')) document.getElementById('edit_desig_dept_id').value = desig.department_id || '';
                if (document.getElementById('edit_desig_description')) document.getElementById('edit_desig_description').value = desig.description || '';
                if (document.getElementById('edit_desig_status')) {
                    document.getElementById('edit_desig_status').value = (desig.status === true || desig.status === 1 || desig.status === '1') ? '1' : '0';
                }

                // Update form action URL to point to current designation
                let editForm = document.getElementById('desig_edit_form');
                if (editForm) {
                    editForm.action = '/hrms/org/designation/update/' + desig.id;
                }

                // Update Status Badge
                let statusEl = document.getElementById('desig_status');
                if (statusEl) {
                    if (desig.status === true || desig.status === 1 || desig.status === '1') {
                        statusEl.innerHTML = '<span class="badge bg-soft-success text-success">Active</span>';
                    } else {
                        statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">Inactive</span>';
                    }
                }
            });
        });
    });
</script>
