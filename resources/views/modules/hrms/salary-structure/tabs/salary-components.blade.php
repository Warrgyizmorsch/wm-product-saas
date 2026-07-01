<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="Salary Components" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addSalaryComponentModal">
                    Add Component
                </x-ui.button>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">#</th>
                            <th>Component Name</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Default Calculation</th>
                            <th>Legal Entity</th>
                            <th>Status</th>
                            <th width="150" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salaryComponents as $sc)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><span class="fw-bold text-dark">{{ $sc->name }}</span></td>
                            <td><code>{{ $sc->code }}</code></td>
                            <td>
                                @if($sc->type == 'earning')
                                    <x-ui.badge variant="success" soft>Earning</x-ui.badge>
                                @else
                                    <x-ui.badge variant="warning" soft>Deduction</x-ui.badge>
                                @endif
                            </td>
                            <td>
                                @if($sc->calculation_type == 'fixed')
                                    <span>Fixed</span> @if($sc->default_value) ({{ $sc->default_value }}) @endif
                                @elseif($sc->calculation_type == 'percentage')
                                    <span>Percentage</span> @if($sc->default_value) ({{ $sc->default_value }}%) @endif
                                @else
                                    <span>Formula</span> @if($sc->default_value) (<code>{{ $sc->default_value }}</code>) @endif
                                @endif
                            </td>
                            <td>{{ $sc->company->company_name ?? 'All Entities' }}</td>
                            <td>
                                @if($sc->status)
                                    <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <x-ui.icon-btn variant="soft-info" icon="feather-edit" class="btn-edit-salary-component" data-bs-toggle="modal" data-bs-target="#editSalaryComponentModal" data-component="{{ base64_encode($sc->toJson()) }}" title="Edit" />
                                    <form action="{{ request()->routeIs('hrms.salary-structure.index') ? route('hrms.salary-structure.destroy', ['salaryComponent' => $sc->id]) : route('hrms.salary-component.destroy', ['salaryComponent' => $sc->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this salary component?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" icon="feather-trash-2" title="Delete" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        @if($salaryComponents->isEmpty())
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                No Salary Components found. Click "Add Component" to create one.
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
        // Edit Action Trigger for Salary Components
        document.querySelectorAll('.btn-edit-salary-component').forEach(btn => {
            btn.addEventListener('click', function() {
                // Decode component data
                let component = JSON.parse(atob(this.dataset.component));
                
                // Populate input fields in the Edit modal
                document.getElementById('edit_sc_name').value = component.name || '';
                document.getElementById('edit_sc_code').value = component.code || '';
                document.getElementById('edit_sc_type').value = component.type || 'earning';
                document.getElementById('edit_sc_calculation_type').value = component.calculation_type || 'fixed';
                document.getElementById('edit_sc_default_value').value = component.default_value || '';
                document.getElementById('edit_sc_company_id').value = component.company_id || '';
                document.getElementById('edit_sc_description').value = component.description || '';
                
                // Populate status select dropdown
                let statusSelect = document.getElementById('edit_sc_status');
                if (statusSelect) {
                    statusSelect.value = (component.status === true || component.status === 1 || component.status === '1') ? '1' : '0';
                }
                
                // Trigger Change event on all select elements to notify Select2 to refresh its displayed value
                $('#editSalaryComponentModal select').trigger('change');
                
                // Update form action URL to target this specific component id on the correct route
                let form = document.getElementById('salary_component_edit_form');
                if (form) {
                    form.action = form.dataset.updateRoute.replace('__ID__', component.id);
                }
            });
        });
    });
</script>
