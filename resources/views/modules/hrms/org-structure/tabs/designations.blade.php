<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="Designations" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addDesigModal">
                    Add Designation
                </x-ui.button>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">#</th>
                            <!-- <th width="80">Avatar</th> -->
                            <th>Designation Name</th>
                            <th>Grade Level</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th width="150" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($designations as $ds)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <!-- <td>
                                <div class="avatar-text avatar-md rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center fw-bold fs-12" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                    {{ substr($ds->name ?? 'DS', 0, 2) }}
                                </div>
                            </td> -->
                            <td><span class="fw-bold text-dark">{{ $ds->name }}</span></td>
                            <td><code>{{ $ds->level ?? 'N/A' }}</code></td>
                            <td>{{ $ds->department->name ?? 'N/A' }}</td>
                            <td>
                                @if($ds->status)
                                    <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('hrms.designation.destroy', $ds->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this designation?');">
                                    @csrf
                                    @method('DELETE')
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="javascript:void(0)" class="action-dropdown-btn btn-view-desig" data-bs-toggle="modal" data-bs-target="#viewDesigModal" data-desig="{{ base64_encode($ds->toJson()) }}" title="View Details" data-bs-toggle="tooltip">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item btn-edit-desig" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editDesigModal" data-desig="{{ base64_encode($ds->toJson()) }}">
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
                        @if($designations->isEmpty())
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                No Designations found. Click "Add Designation" to create one.
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
            document.querySelectorAll('.btn-view-desig').forEach(btn => {
                btn.addEventListener('click', function() {
                    let desig = JSON.parse(atob(this.dataset.desig));
                    
                    let nameEl = document.getElementById('modal_view_desig_name');
                    if (nameEl) nameEl.innerText = desig.name;
                    
                    let deptEl = document.getElementById('modal_view_desig_dept');
                    if (deptEl) deptEl.innerText = (desig.department && desig.department.name) ? desig.department.name : 'N/A';
                    
                    let levelEl = document.getElementById('modal_view_desig_level');
                    if (levelEl) levelEl.innerText = desig.level || 'N/A';
                    
                    let descEl = document.getElementById('modal_view_desig_desc');
                    if (descEl) descEl.innerText = desig.description || 'No description provided.';
                    
                    let avatarEl = document.getElementById('modal_view_desig_avatar');
                    if (avatarEl) {
                        avatarEl.innerText = getInitials(desig.name, 'DS');
                    }
                    
                    let statusEl = document.getElementById('modal_view_desig_status');
                    if (statusEl) {
                        if (desig.status === true || desig.status === 1 || desig.status === '1') {
                            statusEl.innerHTML = '<span class="badge bg-soft-success text-success">Active</span>';
                        } else {
                            statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">Inactive</span>';
                        }
                    }
                });
            });

            // Edit Action Trigger
            document.querySelectorAll('.btn-edit-desig').forEach(btn => {
                btn.addEventListener('click', function() {
                    let desig = JSON.parse(atob(this.dataset.desig));
                    
                    let nameEl = document.getElementById('edit_desig_name');
                    if (nameEl) nameEl.value = desig.name || '';
                    
                    let levelEl = document.getElementById('edit_desig_level');
                    if (levelEl) levelEl.value = desig.level || '';
                    
                    let deptEl = document.getElementById('edit_desig_dept_id');
                    if (deptEl) deptEl.value = desig.department_id || '';
                    
                    let descEl = document.getElementById('edit_desig_description');
                    if (descEl) descEl.value = desig.description || '';
                    
                    let statusSelect = document.getElementById('edit_desig_status');
                    if (statusSelect) {
                        statusSelect.value = (desig.status === true || desig.status === 1 || desig.status === '1') ? '1' : '0';
                    }
                    
                    let form = document.getElementById('desig_edit_form');
                    if (form) {
                        form.action = '/hrms/org/designation/update/' + desig.id;
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
