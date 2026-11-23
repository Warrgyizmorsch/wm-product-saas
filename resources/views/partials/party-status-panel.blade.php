{{--
    Inactive reason banner and status actions on a customer/vendor page (FRS 2.3, C-16).
    Expects: $party, $type ('customer'|'vendor'). Shows nothing for an unverified party
    (the verification panel covers that).
--}}
@php
    $statusService = app(\App\Services\PartyStatusService::class);
    $isCustomer = $type === 'customer';
    $deactivateRoute = $isCustomer ? route('crm.customers.deactivate.create', $party) : route('purchase.vendors.deactivate.create', $party->id);
    $reactivateRoute = $isCustomer ? route('crm.customers.reactivate.create', $party) : route('purchase.vendors.reactivate.create', $party->id);
    $blocked = $statusService->reactivationBlockedReason(auth()->user(), $party);
    $risk = $statusService->isRiskLocked($party);
@endphp

@if($party->isMerged())
    @php
        $targetParty = $party->mergedInto;
        $targetUrl = $isCustomer 
            ? ($targetParty ? route('crm.customers.show', $targetParty) : null)
            : ($targetParty ? route('purchase.vendors.show', $targetParty) : null);
    @endphp
    <div class="alert alert-warning py-2.5 px-3 fs-13 mb-3 border-warning d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-sm">
        <div>
            <i class="feather-git-merge text-warning me-1.5 fs-16"></i>
            <strong>Merged Record (FRS 2.3 / C-18):</strong> This {{ $type }} was permanently merged into
            @if($targetParty && $targetUrl)
                <a href="{{ $targetUrl }}" class="fw-bold text-dark text-decoration-underline">{{ $targetParty->name }} (#{{ $targetParty->id }})</a>
            @else
                <strong>#{{ $party->merged_into_id }}</strong>
            @endif
            @if($party->merged_at) on {{ $party->merged_at->format('d M Y H:i') }} @endif
            @if($party->merger) by {{ $party->merger->name }} @endif.
            All historical orders, invoices, payments, branches, and ledgers have been consolidated into the surviving master record.
        </div>
        @if($targetParty && $targetUrl)
            <a href="{{ $targetUrl }}" class="btn btn-sm btn-dark">
                View Surviving {{ ucfirst($type) }} <i class="feather-arrow-right ms-1"></i>
            </a>
        @endif
    </div>
@elseif($statusService->enabled() && $party->isVerified())
    @if($statusService->isActive($party))
        <div class="mb-3">
            <a href="{{ $deactivateRoute }}" class="btn btn-sm btn-outline-danger">Make inactive</a>
        </div>
    @else
        <div class="alert {{ $risk ? 'alert-danger' : 'alert-secondary' }} py-2 fs-13 mb-3">
            <strong>Inactive: {{ $statusService->reasonLabel($party->inactive_reason) }}.</strong>
            @if($party->inactivated_at)
                Since {{ $party->inactivated_at->format('d M Y H:i') }}@if($party->inactivator) by {{ $party->inactivator->name }}@endif.
            @endif
            @if($party->inactive_note) <span class="text-muted">{{ $party->inactive_note }}</span> @endif
            <div class="mt-1">
                New {{ $isCustomer ? 'sales orders and dispatches' : ($statusService->blocksPayments($party->inactive_reason) ? 'purchase orders, RFQs, advances, subcontract challans and vendor payments' : 'purchase orders, RFQs, advances and subcontract challans') }} are blocked.
                @if($blocked)
                    <span class="d-block text-muted">{{ $blocked }}</span>
                @else
                    <a href="{{ $reactivateRoute }}" class="btn btn-sm btn-success ms-2">Reactivate</a>
                @endif
            </div>
        </div>
    @endif
@endif
