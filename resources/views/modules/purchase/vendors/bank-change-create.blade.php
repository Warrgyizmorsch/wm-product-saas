@extends('layouts.duralux')

@section('title', 'Request bank change | ' . $vendor->name)
@section('page-title', 'Request bank change')
@section('breadcrumb', $vendor->name)

@section('page-actions')
    <a href="{{ route('purchase.vendors.show', $vendor->id) }}" class="btn btn-light border">Back to vendor</a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm" style="max-width: 760px;">
        <h5 class="fw-bold mb-1">Change bank details for {{ $vendor->name }}</h5>
        <p class="text-muted fs-12 mb-3">
            The current bank details stay in use until a different person phones the vendor on the number already on file,
            confirms the new account, and approves this request.
        </p>

        @if($openRequest)
            <div class="alert alert-warning fs-13">
                A request is already open for this vendor.
                <a href="{{ route('purchase.vendors.bank-changes.show', $openRequest->id) }}">View it</a>.
            </div>
        @endif

        <div class="border rounded-3 p-3 mb-3 bg-light fs-13">
            <div class="fw-semibold mb-1">Current (verified) details</div>
            <div><span class="text-muted">Bank:</span> {{ $vendor->bank_name ?: 'N/A' }}</div>
            <div><span class="text-muted">Account number:</span> <span class="font-monospace">{{ $vendor->account_number ?: 'N/A' }}</span></div>
            <div><span class="text-muted">IFSC:</span> <span class="font-monospace">{{ $vendor->ifsc_code ?: 'N/A' }}</span></div>
            <div class="mt-1"><span class="text-muted">Phone on record (used for the callback):</span> <strong>{{ $vendor->phone ?: 'None, add a verified contact number first' }}</strong></div>
        </div>

        <form method="POST" action="{{ route('purchase.vendors.bank-changes.store', $vendor->id) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">New bank name</label>
                <input type="text" name="new_bank_name" class="form-control @error('new_bank_name') is-invalid @enderror" value="{{ old('new_bank_name') }}" maxlength="150">
                @error('new_bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">New account number <span class="text-danger">*</span></label>
                <input type="text" name="new_account_number" class="form-control @error('new_account_number') is-invalid @enderror" value="{{ old('new_account_number') }}" maxlength="50" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                @error('new_account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">New IFSC <span class="text-danger">*</span></label>
                <input type="text" name="new_ifsc_code" class="form-control @error('new_ifsc_code') is-invalid @enderror" value="{{ old('new_ifsc_code') }}" maxlength="30" required>
                @error('new_ifsc_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Reason for the change <span class="text-danger">*</span></label>
                <textarea name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" maxlength="1000" required>{{ old('reason') }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary" @disabled($openRequest)>Raise request</button>
        </form>
    </div>
@endsection
