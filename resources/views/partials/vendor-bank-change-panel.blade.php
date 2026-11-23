{{--
    Bank change status on the vendor page (FRS 2.3, C-16 bank part). Expects: $vendor.
    Warns while a request is open so nobody pays to unconfirmed details.
--}}
@php
    $bankChange = app(\App\Services\PartyBankChangeService::class);
    $open = $bankChange->enabled() ? $bankChange->openRequest($vendor) : null;
@endphp

@if($bankChange->enabled())
    @if($open)
        <div class="alert alert-warning py-2 fs-13 mb-3">
            <strong>A bank change request is open for this vendor.</strong>
            The bank details shown here are still the last verified ones. Do not pay to any new account until the request is approved.
            <a href="{{ route('purchase.vendors.bank-changes.show', $open->id) }}" class="ms-1">View request</a>
        </div>
    @endif
@endif
