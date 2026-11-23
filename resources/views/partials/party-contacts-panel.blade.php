{{--
    Unified Contacts panel for Customer or Vendor show page (FRS 2.3 / C-17).
    Expects: $party (Customer|Vendor), $type ('customer'|'vendor').
--}}
@php
    $isCustomer = $type === 'customer';
    $routePrefix = $isCustomer ? 'crm.customers.contacts' : 'purchase.vendors.contacts';
    $contacts = $party->contacts()->with(['roles', 'partyBranch'])->orderByDesc('is_primary')->orderBy('name')->get();
    $branches = $party->branches()->orderByDesc('is_head_office')->orderBy('name')->get();
    $allRoles = \App\Models\ContactRole::availableForTenant((int) ($party->tenant_id ?? tenant_id() ?? 1))->get();
    $modalPrefix = $type . '_' . $party->id;
@endphp

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h6 class="fw-bold text-dark mb-0"><i class="feather-users me-2 text-primary"></i>Contacts &amp; Roles</h6>
        <span class="text-muted fs-11">Stakeholders with role badges (Decision Maker, Architect, Accounts, Site Receiver, etc.) linked optionally to a branch/site.</span>
    </div>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPartyContactModal_{{ $modalPrefix }}">
        <i class="feather-user-plus me-1"></i>Add Contact
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 fs-13">
        <thead class="table-light">
            <tr>
                <th>Name &amp; Designation</th>
                <th>Roles</th>
                <th>Branch / Site</th>
                <th>Contact Details</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contacts as $contact)
                <tr>
                    <td>
                        <div class="fw-bold text-dark">
                            {{ $contact->name }}
                            @if($contact->is_primary)
                                <span class="badge bg-soft-success text-success ms-1 fs-10">Primary</span>
                            @endif
                        </div>
                        @if($contact->designation)
                            <div class="text-muted fs-11">{{ $contact->designation }}</div>
                        @endif
                    </td>
                    <td>
                        @forelse($contact->roles as $role)
                            <span class="badge bg-soft-primary text-primary me-1 mb-1 fs-11">{{ $role->name }}</span>
                        @empty
                            <span class="text-muted fs-11">—</span>
                        @endforelse
                    </td>
                    <td class="fs-12">
                        @if($contact->partyBranch)
                            <span class="badge bg-light text-dark border">{{ $contact->partyBranch->name }}</span>
                        @else
                            <span class="text-muted">All / Company-wide</span>
                        @endif
                    </td>
                    <td class="fs-12">
                        @if($contact->email) <div><i class="feather-mail me-1 text-muted"></i>{{ $contact->email }}</div> @endif
                        @if($contact->phone || $contact->mobile) <div><i class="feather-phone me-1 text-muted"></i>{{ $contact->mobile ?: $contact->phone }}</div> @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-xs btn-light border" data-bs-toggle="modal" data-bs-target="#editPartyContactModal_{{ $contact->id }}">
                            <i class="feather-edit-2"></i>
                        </button>
                        <form method="POST" action="{{ route($routePrefix . '.destroy', [$party->id, $contact->id]) }}" id="deleteContactForm_{{ $contact->id }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="confirmAction({ title: 'Remove Contact', message: 'Are you sure you want to remove contact {{ addslashes($contact->name) }}?', variant: 'danger', confirmText: 'Remove', onConfirm: function() { document.getElementById('deleteContactForm_{{ $contact->id }}').submit(); } })"><i class="feather-trash-2"></i></button>
                        </form>
                    </td>
                </tr>

                {{-- Edit Contact Modal --}}
                <div class="modal fade" id="editPartyContactModal_{{ $contact->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title fw-bold">Edit Contact: {{ $contact->name }}</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="{{ route($routePrefix . '.update', [$party->id, $contact->id]) }}">
                                @csrf
                                @method('PUT')
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fs-12 fw-semibold">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $contact->name }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs-12 fw-semibold">Designation</label>
                                            <input type="text" name="designation" class="form-control form-control-sm" value="{{ $contact->designation }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs-12 fw-semibold">Email</label>
                                            <input type="email" name="email" class="form-control form-control-sm" value="{{ $contact->email }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs-12 fw-semibold">Phone / Mobile</label>
                                            <input type="text" name="phone" class="form-control form-control-sm" value="{{ $contact->phone ?: $contact->mobile }}">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fs-12 fw-semibold">Linked Branch / Site (Optional)</label>
                                            <select name="party_branch_id" class="form-select form-select-sm">
                                                <option value="">Company-wide (No specific branch)</option>
                                                @foreach($branches as $br)
                                                    <option value="{{ $br->id }}" @selected((int) $contact->party_branch_id === (int) $br->id)>{{ $br->name }} ({{ $br->code }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fs-12 fw-semibold">Roles (select one or more)</label>
                                            @php $assignedRoleIds = $contact->roles->pluck('id')->all(); @endphp
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach($allRoles as $role)
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="role_ids[]" value="{{ $role->id }}" id="ed_crole_{{ $contact->id }}_{{ $role->id }}" @checked(in_array($role->id, $assignedRoleIds))>
                                                        <label class="form-check-label fs-12" for="ed_crole_{{ $contact->id }}_{{ $role->id }}">{{ $role->name }}</label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="ed_cprim_{{ $contact->id }}" @checked($contact->is_primary)>
                                                <label class="form-check-label fs-12" for="ed_cprim_{{ $contact->id }}">Primary Contact</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer py-2">
                                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-sm btn-primary">Save Contact</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">No contacts added yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Add Contact Modal --}}
<div class="modal fade" id="addPartyContactModal_{{ $modalPrefix }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Add Contact for {{ $party->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route($routePrefix . '.store', $party->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Phone / Mobile</label>
                            <input type="text" name="phone" class="form-control form-control-sm">
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-12 fw-semibold">Linked Branch / Site (Optional)</label>
                            <select name="party_branch_id" class="form-select form-select-sm">
                                <option value="">Company-wide (No specific branch)</option>
                                @foreach($branches as $br)
                                    <option value="{{ $br->id }}">{{ $br->name }} ({{ $br->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-12 fw-semibold">Roles (select one or more)</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($allRoles as $role)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="role_ids[]" value="{{ $role->id }}" id="add_crole_{{ $modalPrefix }}_{{ $role->id }}">
                                        <label class="form-check-label fs-12" for="add_crole_{{ $modalPrefix }}_{{ $role->id }}">{{ $role->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="add_cprim_{{ $modalPrefix }}">
                                <label class="form-check-label fs-12" for="add_cprim_{{ $modalPrefix }}">Set as Primary Contact</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-sm btn-primary">Add Contact</button>
                </div>
            </form>
        </div>
    </div>
</div>
