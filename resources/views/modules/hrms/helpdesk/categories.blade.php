@extends('layouts.duralux')

@section('title', 'Helpdesk Categories & SLA Rules | HRMS')
@section('page-title', 'Helpdesk Categories & SLA Rules')
@section('breadcrumb', 'HRMS / Helpdesk / Categories')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-arrow-left" href="{{ route('hrms.helpdesk.tickets.index') }}">
            Back to Helpdesk
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCategoryModal" class="fw-bold">
            Add Category
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="feather-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="border-bottom pb-3 mb-4">
            <h6 class="fw-bold mb-0 text-dark"><i class="feather-settings me-2 text-primary"></i> Category Routing & Service Level Agreement (SLA) Configurations</h6>
        </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category Name</th>
                            <th>Code</th>
                            <th>Target SLA (Hours)</th>
                            <th>Default Assigned Agent</th>
                            <th>Confidential</th>
                            <th>Total Tickets</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr>
                                <td class="fw-bold text-dark">
                                    {{ $cat->name }}
                                    @if($cat->description)
                                        <div class="small text-muted font-normal">{{ $cat->description }}</div>
                                    @endif
                                </td>
                                <td><code>{{ $cat->code }}</code></td>
                                <td>
                                    <x-ui.badge variant="info" soft class="fw-bold">
                                        <i class="feather-clock me-1"></i> {{ $cat->default_sla_hours }} Hours
                                    </x-ui.badge>
                                </td>
                                <td>
                                     @if($cat->defaultAgent)
                                         <span class="badge bg-light text-dark border">
                                             <i class="feather-user me-1"></i> {{ $cat->defaultAgent->full_name ?? trim(($cat->defaultAgent->first_name ?? '') . ' ' . ($cat->defaultAgent->last_name ?? '')) }}
                                         </span>
                                     @else
                                        <span class="text-muted small">None (Unassigned)</span>
                                    @endif
                                </td>
                                <td>
                                    @if($cat->is_confidential)
                                        <x-ui.badge variant="danger" soft><i class="feather-lock me-1"></i> Yes</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft>No</x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.badge variant="secondary" soft class="fs-7">{{ $cat->tickets_count }}</x-ui.badge>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 justify-content-end">
                                        <x-ui.icon-btn variant="soft-primary" size="sm" icon="feather-edit" title="Edit Category" data-bs-toggle="modal" data-bs-target="#editCategoryModal{{ $cat->id }}" />
                                        <form action="{{ route('hrms.helpdesk.categories.destroy', $cat->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Delete this category?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="Delete Category" />
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <x-ui.modal id="editCategoryModal{{ $cat->id }}" title="<i class='feather-edit me-2'></i> Edit Category: {{ $cat->name }}" formAction="{{ route('hrms.helpdesk.categories.update', $cat->id) }}" formMethod="PUT" submitText="Save Changes" centered>
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="input" label="Category Name" name="name" value="{{ $cat->name }}" :required="true" />
                                </div>
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="input" inputType="number" label="Default SLA Target (Hours)" name="default_sla_hours" value="{{ $cat->default_sla_hours }}" min="1" :required="true" />
                                </div>
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="select" label="Default Agent Routing" name="default_agent_id">
                                        <option value="">-- Select Default Agent --</option>
                                         @foreach($agents as $agent)
                                             <option value="{{ $agent->id }}" {{ $cat->default_agent_id == $agent->id ? 'selected' : '' }}>
                                                 {{ $agent->full_name ?? trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')) }} ({{ $agent->employee_id ?? $agent->employee_code }})
                                             </option>
                                         @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" class="form-control" rows="2">{{ $cat->description }}</textarea>
                                </div>
                                <div class="mb-3">
                                    <x-ui.checkbox label="Mark as Confidential Category (e.g. POSH / Harassment)" name="is_confidential" value="1" :checked="$cat->is_confidential" id="editConfSwitch_{{ $cat->id }}" />
                                </div>
                            </x-ui.modal>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No helpdesk categories defined. Click "Add New Category" above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
    </div>
</div>

<!-- Modal: Create Category -->
<x-ui.modal id="createCategoryModal" title="<i class='feather-plus-circle me-2'></i> Add Helpdesk Category" formAction="{{ route('hrms.helpdesk.categories.store') }}" submitText="Create Category" centered>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="Category Name" name="name" placeholder="e.g. Payroll & Taxes" :required="true" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Default SLA Resolution Target (Hours)" name="default_sla_hours" value="24" min="1" :required="true" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="select" label="Default Agent Routing" name="default_agent_id">
            <option value="">-- Select Default Agent --</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->full_name ?? trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')) }} ({{ $agent->employee_id ?? $agent->employee_code }})</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2" placeholder="Short scope description..."></textarea>
    </div>
    <div class="mb-3">
        <x-ui.checkbox label="Mark as Confidential Category (e.g. POSH / Harassment)" name="is_confidential" value="1" id="createConfSwitch" />
    </div>
</x-ui.modal>
@endsection
