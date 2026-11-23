{{--
    Branches & Sites panel for Customer or Vendor show page (FRS 2.3 / C-17).
    Expects: $party (Customer|Vendor), $type ('customer'|'vendor').
--}}
@php
    $isCustomer = $type === 'customer';
    $routePrefix = $isCustomer ? 'crm.customers.branches' : 'purchase.vendors.branches';
    $branches = $party->branches()->with('submitter', 'verifier')->orderByDesc('is_head_office')->orderBy('name')->get();
    $verification = app(\App\Services\PartyVerificationService::class);
    $user = auth()->user();
    $modalPrefix = $type . '_' . $party->id;
@endphp

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h6 class="fw-bold text-dark mb-0"><i class="feather-map-pin me-2 text-primary"></i>Branches &amp; Sites</h6>
        <span class="text-muted fs-11">Manage billing addresses, delivery sites, and branch GSTINs (must share the parent PAN).</span>
    </div>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPartyBranchModal_{{ $modalPrefix }}">
        <i class="feather-plus me-1"></i>Add Branch / Site
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 fs-13">
        <thead class="table-light">
            <tr>
                <th>Branch / Site</th>
                <th>Type</th>
                <th>GSTIN / State</th>
                <th>Address</th>
                <th>Usage / Defaults</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($branches as $br)
                <tr>
                    <td>
                        <div class="fw-bold text-dark">{{ $br->name }}</div>
                        <div class="text-muted fs-11 font-monospace">{{ $br->code }}
                            @if($br->is_head_office)
                                <span class="badge bg-soft-primary text-primary ms-1">Head Office</span>
                            @endif
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border fs-11">{{ \App\Models\PartyBranch::TYPES[$br->branch_type] ?? ucfirst($br->branch_type) }}</span></td>
                    <td>
                        @if($br->gstin)
                            <div class="font-monospace fw-semibold text-primary fs-12">{{ $br->gstin }}</div>
                        @else
                            <span class="text-muted fs-12">Same as Parent</span>
                        @endif
                        @if($br->effectiveStateCode())
                            <div class="text-muted fs-11">State: {{ $br->effectiveStateCode() }} {{ $br->state ? '(' . $br->state . ')' : '' }}</div>
                        @endif
                    </td>
                    <td class="fs-12 text-muted" style="max-width: 240px;">{{ $br->formattedAddress() ?: '—' }}</td>
                    <td class="fs-11">
                        @if($br->is_billing)
                            <span class="badge {{ $br->is_default_billing ? 'bg-primary text-white' : 'bg-soft-primary text-primary' }} me-1">
                                {{ $br->is_default_billing ? 'Default Bill-To' : 'Bill-To' }}
                            </span>
                        @endif
                        @if($br->is_shipping)
                            <span class="badge {{ $br->is_default_shipping ? 'bg-info text-white' : 'bg-soft-info text-info' }}">
                                {{ $br->is_default_shipping ? 'Default Ship-To' : 'Ship-To' }}
                            </span>
                        @endif
                    </td>
                    <td>
                        @if(! $br->isVerified())
                            <span class="badge {{ $br->isReturnedForCorrection() ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning' }}">
                                {{ $br->verificationLabel() }}
                            </span>
                        @elseif(strtolower($br->status) === 'active')
                            <span class="badge bg-soft-success text-success">Active</span>
                        @else
                            <span class="badge bg-soft-secondary text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-xs btn-light border" data-bs-toggle="modal" data-bs-target="#editPartyBranchModal_{{ $br->id }}">
                            <i class="feather-edit-2"></i>
                        </button>
                        @if(! $br->is_head_office)
                            @if(strtolower($br->status) === 'active')
                                <form method="POST" action="{{ route($routePrefix . '.deactivate', [$party->id, $br->id]) }}" id="deactivateBranchForm_{{ $br->id }}" class="d-inline">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-outline-warning" onclick="if (typeof confirmAction === 'function') { confirmAction({ title: 'Deactivate Branch', message: 'Are you sure you want to deactivate branch {{ addslashes($br->name) }}?', variant: 'warning', confirmText: 'Deactivate', onConfirm: function() { document.getElementById('deactivateBranchForm_{{ $br->id }}').submit(); } }); } else if (confirm('Are you sure you want to deactivate branch {{ addslashes($br->name) }}?')) { document.getElementById('deactivateBranchForm_{{ $br->id }}').submit(); }">Deactivate</button>
                                </form>
                            @elseif($br->isVerified())
                                <form method="POST" action="{{ route($routePrefix . '.activate', [$party->id, $br->id]) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-outline-success">Activate</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>

                {{-- Inline branch-level verification bar when pending/returned --}}
                @if($verification->enabled() && ! $br->isVerified() && ! $br->is_head_office)
                    <tr class="table-warning">
                        <td colspan="7" class="p-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <div class="fs-12">
                                    <strong>Branch Verification Required:</strong>
                                    This branch cannot be selected on new transactions until a second person verifies it.
                                    <em>Parent {{ $type }} remains active.</em>
                                    @if($br->isReturnedForCorrection() && $br->verification_remarks)
                                        <span class="text-danger ms-2">Returned: {{ $br->verification_remarks }}</span>
                                    @endif
                                </div>
                                <span class="fs-11 text-muted">Submitted by {{ $br->submitter?->name ?? 'unknown' }}</span>
                            </div>

                            @if($br->isReturnedForCorrection())
                                <form method="POST" action="{{ route($routePrefix . '.resubmit', [$party->id, $br->id]) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-primary">Resubmit Branch</button>
                                </form>
                            @elseif($verification->canVerify($user, $br))
                                @if($verification->isMaker($user, $br))
                                    <span class="text-muted fs-12">You created or last edited this branch — another authorised user must verify it.</span>
                                @else
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <form method="POST" action="{{ route($routePrefix . '.verify', [$party->id, $br->id]) }}" class="d-flex flex-wrap gap-2 align-items-center">
                                            @csrf
                                            @if($br->gstin)
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="checks[gstin]" value="1" id="chk_br_gstin_{{ $br->id }}" required>
                                                    <label class="form-check-label fs-12" for="chk_br_gstin_{{ $br->id }}">Checked GSTIN {{ $br->gstin }}</label>
                                                </div>
                                            @endif
                                            <input type="text" name="remarks" class="form-control form-control-sm" style="width: 180px;" placeholder="Remarks (optional)">
                                            <button type="submit" class="btn btn-xs btn-success">Verify Branch</button>
                                        </form>
                                        <form method="POST" action="{{ route($routePrefix . '.verification.return', [$party->id, $br->id]) }}" class="d-flex gap-1 align-items-center">
                                            @csrf
                                            <input type="text" name="reason" class="form-control form-control-sm" style="width: 180px;" placeholder="Return reason" required>
                                            <button type="submit" class="btn btn-xs btn-outline-danger">Return</button>
                                        </form>
                                    </div>
                                @endif
                            @else
                                <span class="text-muted fs-12">Waiting for an authorised verifier.</span>
                            @endif
                        </td>
                    </tr>
                @endif

                {{-- Edit Branch Modal --}}
                <div class="modal fade" id="editPartyBranchModal_{{ $br->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title fw-bold">Edit Branch: {{ $br->name }}</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="{{ route($routePrefix . '.update', [$party->id, $br->id]) }}">
                                @csrf
                                @method('PUT')
                                <div class="modal-body">
                                    @if($br->is_head_office)
                                        <div class="alert alert-info fs-12 py-2">
                                            Head Office address and GSTIN are owned by the parent {{ $type }}. Edit the parent record to update them; here you can toggle Bill-To/Ship-To defaults.
                                        </div>
                                    @endif
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fs-12 fw-semibold">Branch / Site Name</label>
                                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $br->name }}" @disabled($br->is_head_office) required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 fw-semibold">Code</label>
                                            <input type="text" name="code" class="form-control form-control-sm" value="{{ $br->code }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 fw-semibold">Type</label>
                                            <select name="branch_type" class="form-select form-select-sm" @disabled($br->is_head_office)>
                                                @foreach(\App\Models\PartyBranch::TYPES as $val => $lbl)
                                                    <option value="{{ $val }}" @selected($br->branch_type === $val)>{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs-12 fw-semibold">GSTIN (same PAN as parent)</label>
                                            <input type="text" name="gstin" class="form-control form-control-sm text-uppercase" maxlength="20" value="{{ $br->gstin }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 fw-semibold">Phone</label>
                                            <input type="text" name="phone" class="form-control form-control-sm" value="{{ $br->phone }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 fw-semibold">Email</label>
                                            <input type="email" name="email" class="form-control form-control-sm" value="{{ $br->email }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs-12 fw-semibold">Address Line 1</label>
                                            <input type="text" name="address_line1" class="form-control form-control-sm" value="{{ $br->address_line1 }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fs-12 fw-semibold">Address Line 2</label>
                                            <input type="text" name="address_line2" class="form-control form-control-sm" value="{{ $br->address_line2 }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 fw-semibold">City</label>
                                            <input type="text" name="city" class="form-control form-control-sm" value="{{ $br->city }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 fw-semibold">State</label>
                                            <input type="text" name="state" class="form-control form-control-sm" value="{{ $br->state }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 fw-semibold">Postal Code</label>
                                            <input type="text" name="postal_code" class="form-control form-control-sm" value="{{ $br->postal_code }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fs-12 fw-semibold">Country</label>
                                            <input type="text" name="country" class="form-control form-control-sm" value="{{ $br->country }}" @disabled($br->is_head_office)>
                                        </div>
                                        <div class="col-12 d-flex flex-wrap gap-3 pt-2 border-top">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_billing" value="1" id="ed_bill_{{ $br->id }}" @checked($br->is_billing)>
                                                <label class="form-check-label fs-12" for="ed_bill_{{ $br->id }}">Billing Address</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_shipping" value="1" id="ed_ship_{{ $br->id }}" @checked($br->is_shipping)>
                                                <label class="form-check-label fs-12" for="ed_ship_{{ $br->id }}">Shipping / Site Address</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_default_billing" value="1" id="ed_dbill_{{ $br->id }}" @checked($br->is_default_billing)>
                                                <label class="form-check-label fs-12" for="ed_dbill_{{ $br->id }}">Default Bill-To</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_default_shipping" value="1" id="ed_dship_{{ $br->id }}" @checked($br->is_default_shipping)>
                                                <label class="form-check-label fs-12" for="ed_dship_{{ $br->id }}">Default Ship-To</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer py-2">
                                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No branches recorded yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Add Branch Modal --}}
<div class="modal fade" id="addPartyBranchModal_{{ $modalPrefix }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Add Branch / Site for {{ $party->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route($routePrefix . '.store', $party->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Branch / Site Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Bengaluru Office / Site A">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-semibold">Code (Optional)</label>
                            <input type="text" name="code" class="form-control form-control-sm" placeholder="Auto e.g. BR-01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-semibold">Type</label>
                            <select name="branch_type" class="form-select form-select-sm">
                                @foreach(\App\Models\PartyBranch::TYPES as $val => $lbl)
                                    @if($val !== 'head_office')
                                        <option value="{{ $val }}">{{ $lbl }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Branch GSTIN (must match parent PAN)</label>
                            <input type="text" name="gstin" class="form-control form-control-sm text-uppercase" maxlength="20" placeholder="Leave blank to inherit parent GSTIN">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Address Line 1</label>
                            <input type="text" name="address_line1" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Address Line 2</label>
                            <input type="text" name="address_line2" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-semibold">City</label>
                            <input type="text" name="city" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-semibold">State</label>
                            <input type="text" name="state" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-semibold">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-semibold">Country</label>
                            <input type="text" name="country" class="form-control form-control-sm" value="India">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-3 pt-2 border-top">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_billing" value="1" id="add_bill_{{ $modalPrefix }}" checked>
                                <label class="form-check-label fs-12" for="add_bill_{{ $modalPrefix }}">Billing Address</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_shipping" value="1" id="add_ship_{{ $modalPrefix }}" checked>
                                <label class="form-check-label fs-12" for="add_ship_{{ $modalPrefix }}">Shipping / Site Address</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default_billing" value="1" id="add_dbill_{{ $modalPrefix }}">
                                <label class="form-check-label fs-12" for="add_dbill_{{ $modalPrefix }}">Set Default Bill-To</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default_shipping" value="1" id="add_dship_{{ $modalPrefix }}">
                                <label class="form-check-label fs-12" for="add_dship_{{ $modalPrefix }}">Set Default Ship-To</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>
