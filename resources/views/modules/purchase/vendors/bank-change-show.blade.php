@extends('layouts.duralux')

@section('title', 'Bank change request | ' . $vendor->name)
@section('page-title', 'Bank change request')
@section('breadcrumb', $vendor->name)

@section('page-actions')
    <a href="{{ route('purchase.vendors.show', $vendor->id) }}" class="btn btn-light border">Back to vendor</a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm" style="max-width: 860px;">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">{{ $vendor->name }}</h5>
            <span class="badge {{ $change->status === 'pending' ? 'bg-soft-warning text-warning' : ($change->status === 'approved' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary') }}">{{ ucfirst($change->status) }}</span>
        </div>

        <div class="row g-3 mb-3 fs-13">
            <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100">
                    <div class="fw-semibold mb-1">Current (verified)</div>
                    <div><span class="text-muted">Bank:</span> {{ $change->old_bank_name ?: 'N/A' }}</div>
                    <div><span class="text-muted">Account:</span> <span class="font-monospace">{{ $change->old_account_number ?: 'N/A' }}</span></div>
                    <div><span class="text-muted">IFSC:</span> <span class="font-monospace">{{ $change->old_ifsc_code ?: 'N/A' }}</span></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100 border-warning">
                    <div class="fw-semibold mb-1">Requested</div>
                    <div><span class="text-muted">Bank:</span> {{ $change->new_bank_name ?: 'N/A' }}</div>
                    <div><span class="text-muted">Account:</span> <span class="font-monospace">{{ $change->new_account_number ?: 'N/A' }}</span></div>
                    <div><span class="text-muted">IFSC:</span> <span class="font-monospace">{{ $change->new_ifsc_code ?: 'N/A' }}</span></div>
                </div>
            </div>
        </div>

        <div class="fs-13 mb-3">
            <div><span class="text-muted">Raised by:</span> {{ $change->requester?->name ?? '-' }} on {{ $change->requested_at?->format('d M Y H:i') }}</div>
            <div><span class="text-muted">Reason:</span> {{ $change->reason }}</div>
        </div>

        @if($change->status === 'approved')
            <div class="alert alert-success fs-13">
                Approved by {{ $change->decider?->name ?? '-' }} on {{ $change->decided_at?->format('d M Y H:i') }}.
                Callback to {{ $change->callback_number }}, spoke to {{ $change->callback_contact }}.
                @if($change->callback_notes) Notes: {{ $change->callback_notes }} @endif
            </div>
        @elseif($change->status === 'rejected')
            <div class="alert alert-secondary fs-13">Rejected by {{ $change->decider?->name ?? '-' }}: {{ $change->decision_remarks }}</div>
        @elseif($change->status === 'cancelled')
            <div class="alert alert-secondary fs-13">Cancelled by the requester.</div>
        @elseif($change->isPending())
            @if($isRequester)
                <div class="alert alert-warning fs-13">
                    You raised this request, so a different person must do the callback and approve it.
                </div>
                <form method="POST" action="{{ route('purchase.vendors.bank-changes.cancel', $change->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel request</button>
                </form>
            @elseif($canApprove)
                <div class="border rounded-3 p-3 mb-3">
                    <div class="fw-semibold mb-1">Step 1: call the vendor</div>
                    <p class="fs-13 mb-2">
                        Phone on file when this request was raised:
                        <strong class="font-monospace">{{ $change->phone_on_file }}</strong>.
                        Call this number only. Do not use a number from the request, an email or an invoice.
                    </p>
                    <div class="fw-semibold mb-1">Step 2: record the call and approve</div>
                    <form method="POST" action="{{ route('purchase.vendors.bank-changes.approve', $change->id) }}">
                        @csrf
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label fs-12 mb-1">Number you called</label>
                                <input type="text" name="callback_number" class="form-control form-control-sm" value="{{ old('callback_number') }}" required maxlength="30">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-12 mb-1">Person you spoke to</label>
                                <input type="text" name="callback_contact" class="form-control form-control-sm" value="{{ old('callback_contact') }}" required maxlength="150">
                            </div>
                        </div>
                        <textarea name="callback_notes" rows="2" class="form-control form-control-sm mb-2" maxlength="1000" placeholder="Call notes (optional)">{{ old('callback_notes') }}</textarea>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="number_from_master" value="1" id="nfm">
                            <label class="form-check-label fs-13" for="nfm">I called the number on the vendor record, not one supplied with the request</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="confirmed" value="1" id="cnf">
                            <label class="form-check-label fs-13" for="cnf">The vendor confirmed the new bank details on the call</label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-success">Approve and apply new bank details</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('purchase.vendors.bank-changes.reject', $change->id) }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="reason" class="form-control form-control-sm" maxlength="1000" required placeholder="Reason for rejecting">
                    <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">Reject</button>
                </form>
            @else
                <p class="text-muted fs-13">Waiting for an authorised approver.</p>
            @endif
        @endif
    </div>
@endsection
