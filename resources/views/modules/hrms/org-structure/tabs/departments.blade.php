<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="Departments" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                    Add Department
                </x-ui.button>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">#</th>
                            <!-- <th width="80">Avatar</th> -->
                            <th>Department Name</th>
                            <th>Department Code</th>
                            <th>Branch</th>
                            <th>Department Head</th>
                            <th>Status</th>
                            <th width="150" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($departments as $d)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <!-- <td>
                                <div class="avatar-text avatar-md rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center fw-bold fs-12" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                    {{ substr($d->name ?? 'DP', 0, 2) }}
                                </div>
                            </td> -->
                            <td><span class="fw-bold text-dark">{{ $d->name }}</span></td>
                            <td><code>{{ $d->code }}</code></td>
                            <td>{{ $d->branch->name ?? ($d->businessUnit->name ?? ($d->company->company_name ?? 'N/A')) }}</td>
                            <td>{{ $d->head ? ($d->head->first_name . ' ' . $d->head->last_name) : 'N/A' }}</td>
                            <td>
                                @if($d->status)
                                    <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <x-ui.icon-btn variant="soft-primary" icon="feather-eye" class="btn-view-dept" data-bs-toggle="modal" data-bs-target="#viewDeptModal" data-dept="{{ base64_encode($d->toJson()) }}" title="View" />
                                    <x-ui.icon-btn variant="primary" icon="feather-edit" class="btn-edit-dept" data-bs-toggle="modal" data-bs-target="#editDeptModal" data-dept="{{ base64_encode($d->toJson()) }}" title="Edit" />
                                    <form action="{{ route('hrms.department.destroy', $d->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this department?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" icon="feather-trash-2" title="Delete" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        @if($departments->isEmpty())
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                No Departments found. Click "Add Department" to create one.
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
            document.querySelectorAll('.btn-view-dept').forEach(btn => {
                btn.addEventListener('click', function() {
                    let dept = JSON.parse(atob(this.dataset.dept));
                    
                    let nameEl = document.getElementById('modal_view_dept_name');
                    if (nameEl) nameEl.innerText = dept.name;
                    
                    let branchEl = document.getElementById('modal_view_dept_branch');
                    if (branchEl) branchEl.innerText = (dept.branch && dept.branch.name) ? dept.branch.name : ((dept.business_unit && dept.business_unit.name) ? dept.business_unit.name : ((dept.company && dept.company.company_name) ? dept.company.company_name : 'N/A'));
                    
                    let codeEl = document.getElementById('modal_view_dept_code');
                    if (codeEl) codeEl.innerText = dept.code;
                    
                    let headEl = document.getElementById('modal_view_dept_head');
                    if (headEl) headEl.innerText = (dept.head) ? (dept.head.first_name + ' ' + dept.head.last_name) : 'N/A';
                    
                    let descEl = document.getElementById('modal_view_dept_desc');
                    if (descEl) descEl.innerText = dept.description || 'No description provided.';
                    
                    let avatarEl = document.getElementById('modal_view_dept_avatar');
                    if (avatarEl) {
                        avatarEl.innerText = getInitials(dept.name, 'DP');
                    }
                    
                    let statusEl = document.getElementById('modal_view_dept_status');
                    if (statusEl) {
                        if (dept.status === true || dept.status === 1 || dept.status === '1') {
                            statusEl.innerHTML = '<span class="badge bg-soft-success text-success">Active</span>';
                        } else {
                            statusEl.innerHTML = '<span class="badge bg-soft-danger text-danger">Inactive</span>';
                        }
                    }
                });
            });

            // Edit Action Trigger
            document.querySelectorAll('.btn-edit-dept').forEach(btn => {
                btn.addEventListener('click', function() {
                    let dept = JSON.parse(atob(this.dataset.dept));
                    
                    let nameEl = document.getElementById('edit_dept_name');
                    if (nameEl) nameEl.value = dept.name || '';
                    
                    let codeEl = document.getElementById('edit_dept_code');
                    if (codeEl) codeEl.value = dept.code || '';
                    
                    let branchEl = document.getElementById('edit_dept_branch_id');
                    if (branchEl) branchEl.value = dept.branch_id || '';
                    
                    let companyEl = document.getElementById('edit_dept_company_id');
                    if (companyEl) companyEl.value = dept.company_id || '';
                    
                    let buEl = document.getElementById('edit_dept_bu_id');
                    if (buEl) buEl.value = dept.business_unit_id || '';
                    
                    let headEl = document.getElementById('edit_dept_head_id');
                    if (headEl) headEl.value = dept.head_employee_id || '';
                    
                    let descEl = document.getElementById('edit_dept_description');
                    if (descEl) descEl.value = dept.description || '';
                    
                    let statusSelect = document.getElementById('edit_dept_status');
                    if (statusSelect) {
                        statusSelect.value = (dept.status === true || dept.status === 1 || dept.status === '1') ? '1' : '0';
                    }
                    
                    let form = document.getElementById('dept_edit_form');
                    if (form) {
                        form.action = '/hrms/org/department/update/' + dept.id;
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
