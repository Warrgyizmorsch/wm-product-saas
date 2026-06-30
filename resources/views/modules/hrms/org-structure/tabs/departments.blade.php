<style>
    .dept-link {
        transition: all 0.2s ease-in-out;
    }
    .dept-link.active-entity {
        background-color: rgba(13, 110, 253, 0.08) !important;
    }
    .dept-link.active-entity td:first-child {
        border-left: 4px solid var(--bs-primary, #0d6efd) !important;
    }
</style>

<div class="row">

    <!-- Details Card -->
    <div class="col-xxl-8">
        <div class="card stretch stretch-full">
            <div class="card-body">
                @php
                    $dept = $departments->first();
                @endphp
                @if($dept)
                <div id="dept_details_view_mode">
                    <div class="d-flex justify-content-between">
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                            <div class="avatar-text avatar-lg rounded bg-soft-primary text-primary" id="detail_dept_avatar" style="width: 50px; height: 50px; min-width: 50px; min-height: 50px; font-size: 16px;">
                                {{ substr($dept->name ?? 'DP', 0, 2) }}
                            </div>
                            <div>
                                <h4 class="mb-1" id="detail_dept_name">{{ $dept->name }}</h4>
                                <span class="fs-12 text-muted" id="detail_dept_branch_name">{{ $dept->branch->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <a id="edit_dept_details_toggle" href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit Department Details">
                            <i class="feather-edit"></i>
                        </a>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Department Name</label>
                            <p class="mb-3" id="dept_name">{{ $dept->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Department Code</label>
                            <p class="mb-3" id="dept_code">{{ $dept->code }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Parent Branch</label>
                            <p class="mb-3" id="dept_branch_name_text">{{ $dept->branch->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Department Head</label>
                            <p class="mb-3" id="dept_head_name">
                                {{ $dept->head ? ($dept->head->first_name . ' ' . $dept->head->last_name) : 'N/A' }}
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="fs-12 fw-semibold text-muted mb-1">Status</label>
                            <p class="mb-3" id="dept_status">
                                @if($dept->status)
                                    <span class="badge bg-soft-success text-success">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="fs-12 fw-semibold text-muted mb-1">Description</label>
                            <p class="mb-0" id="dept_description">{{ $dept->description ?? 'No description provided.' }}</p>
                        </div>
                    </div> <!-- Close row g-4 -->
                </div> <!-- Close dept_details_view_mode -->

                <!-- Edit Mode Container -->
                <div id="dept_details_edit_mode" class="d-none">
                    <form id="dept_edit_form" action="{{ route('hrms.department.update', $dept->id ?? 0) }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Department Name</label>
                                <input type="text" class="form-control" name="name" id="edit_dept_name" value="{{ $dept->name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Department Code</label>
                                <input type="text" class="form-control" name="code" id="edit_dept_code" value="{{ $dept->code }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Parent Branch</label>
                                <select class="form-control" name="branch_id" id="edit_dept_branch_id">
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ $branch->id == $dept->branch_id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Department Head</label>
                                <select class="form-control" name="head_employee_id" id="edit_dept_head_id">
                                    <option value="">Select Department Head</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ $employee->id == $dept->head_employee_id ? 'selected' : '' }}>
                                            {{ $employee->first_name }} {{ $employee->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-control" name="status" id="edit_dept_status">
                                    <option value="1" {{ $dept->status ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ !$dept->status ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" name="description" id="edit_dept_description" rows="3">{{ $dept->description }}</textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-light me-2" id="cancel_dept_edit_btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Details</button>
                        </div>
                    </form>
                </div>
                @else
                <div class="text-center py-5">
                    <p class="text-muted mb-0">No Departments found. Click "Add Department" to create one.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar List Card -->
    <div class="col-xxl-4 h-100">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Departments</h5>
                <a href="/hrms/org/department/create" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Add Department">
                    <i class="feather-plus"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <tbody>
                        @foreach($departments as $d)
                        <tr class="dept-link @if($loop->first) active-entity @endif"
                            data-dept='@json($d)'
                            style="cursor: pointer;">

                            <td>
                                <div class="hstack gap-3">
                                    <div class="avatar-text avatar-lg rounded bg-soft-primary text-primary" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                        {{ substr($d->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <span class="d-block fw-semibold">
                                            {{ $d->name }}
                                        </span>
                                        <span class="fs-12 text-muted">
                                            {{ $d->branch->name ?? 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- <td width="120">
                                <small class="text-muted d-block">Code</small>
                                <span>{{ $d->code }}</span>
                            </td> -->

                            <td class="text-end">
                                @if($d->status)
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
        let isDeptEditMode = false;
        const viewModeDept = document.getElementById('dept_details_view_mode');
        const editModeDept = document.getElementById('dept_details_edit_mode');
        const toggleDeptBtn = document.getElementById('edit_dept_details_toggle');
        const cancelDeptBtn = document.getElementById('cancel_dept_edit_btn');

        function toggleDeptEdit(edit) {
            isDeptEditMode = edit;
            if (!viewModeDept || !editModeDept) return;
            if (isDeptEditMode) {
                viewModeDept.classList.add('d-none');
                editModeDept.classList.remove('d-none');
                if (toggleDeptBtn) {
                    toggleDeptBtn.innerHTML = '<i class="feather-x"></i>';
                    toggleDeptBtn.setAttribute('title', 'Cancel Edit');
                }
            } else {
                viewModeDept.classList.remove('d-none');
                editModeDept.classList.add('d-none');
                if (toggleDeptBtn) {
                    toggleDeptBtn.innerHTML = '<i class="feather-edit"></i>';
                    toggleDeptBtn.setAttribute('title', 'Edit Department Details');
                }
            }
        }

        if (toggleDeptBtn) {
            toggleDeptBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleDeptEdit(!isDeptEditMode);
            });
        }

        if (cancelDeptBtn) {
            cancelDeptBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleDeptEdit(false);
            });
        }

        // Sidebar click handler to select department
        document.querySelectorAll('.dept-link').forEach(link => {
            link.addEventListener('click', function(e){
                e.preventDefault();

                // Close edit mode on switch
                toggleDeptEdit(false);

                // Toggle active highlights on list rows
                document.querySelectorAll('.dept-link').forEach(row => {
                    row.classList.remove('active-entity');
                });
                this.classList.add('active-entity');

                let dept = JSON.parse(this.dataset.dept);

                // Update text fields dynamically
                if (document.getElementById('dept_name')) document.getElementById('dept_name').innerText = dept.name || '';
                if (document.getElementById('dept_code')) document.getElementById('dept_code').innerText = dept.code || '';
                if (document.getElementById('dept_branch_name_text')) {
                    document.getElementById('dept_branch_name_text').innerText = (dept.branch && dept.branch.name) ? dept.branch.name : 'N/A';
                }
                if (document.getElementById('dept_head_name')) {
                    document.getElementById('dept_head_name').innerText = (dept.head) ? (dept.head.first_name + ' ' + dept.head.last_name) : 'N/A';
                }
                if (document.getElementById('dept_description')) document.getElementById('dept_description').innerText = dept.description || 'No description provided.';
                if (document.getElementById('detail_dept_name')) document.getElementById('detail_dept_name').innerText = dept.name || '';
                if (document.getElementById('detail_dept_branch_name')) {
                    document.getElementById('detail_dept_branch_name').innerText = (dept.branch && dept.branch.name) ? dept.branch.name : 'N/A';
                }

                // Update Initials Avatar
                let avatarEl = document.getElementById('detail_dept_avatar');
                if (avatarEl) {
                    avatarEl.innerText = (dept.name) ? dept.name.substring(0, 2) : 'DP';
                }

                // Update edit fields dynamically
                if (document.getElementById('edit_dept_name')) document.getElementById('edit_dept_name').value = dept.name || '';
                if (document.getElementById('edit_dept_code')) document.getElementById('edit_dept_code').value = dept.code || '';
                if (document.getElementById('edit_dept_branch_id')) document.getElementById('edit_dept_branch_id').value = dept.branch_id || '';
                if (document.getElementById('edit_dept_head_id')) document.getElementById('edit_dept_head_id').value = dept.head_employee_id || '';
                if (document.getElementById('edit_dept_description')) document.getElementById('edit_dept_description').value = dept.description || '';
                if (document.getElementById('edit_dept_status')) {
                    document.getElementById('edit_dept_status').value = (dept.status === true || dept.status === 1 || dept.status === '1') ? '1' : '0';
                }

                // Update form action URL to point to current department
                let editForm = document.getElementById('dept_edit_form');
                if (editForm) {
                    editForm.action = '/hrms/org/department/update/' + dept.id;
                }

                // Update Status Badge
                let statusEl = document.getElementById('dept_status');
                if (statusEl) {
                    if (dept.status === true || dept.status === 1 || dept.status === '1') {
                        statusEl.innerHTML = '<span class="badge bg-soft-success text-success">Active</span>';
                    } else {
                        statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">Inactive</span>';
                    }
                }
            });
        });
    });
</script>
