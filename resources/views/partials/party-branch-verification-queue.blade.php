{{-- Queue of party branches pending verification. Expects $branches (Collection), $type ('customer'|'vendor'), $verification. --}}
@php
    $isCustomer = $type === 'customer';
    $user = auth()->user();
    $showRoute = $isCustomer ? 'crm.customers.show' : 'purchase.vendors.show';
@endphp

@if(($branches ?? collect())->isNotEmpty())
<div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm mt-4">
    <h5 class="fw-bold mb-1">{{ $isCustomer ? 'Customer' : 'Vendor' }} branches pending verification</h5>
    <p class="text-muted fs-12 mb-3">
        Open the parent {{ $type }} to verify the branch GSTIN. Pending branches cannot be used on new orders, while the parent {{ $type }} remains active.
    </p>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Parent {{ ucfirst($type) }}</th>
                    <th>Branch</th>
                    <th>GSTIN</th>
                    <th>Status</th>
                    <th>Submitted by</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($branches as $branch)
                    @php $parent = $branch->parentParty(); @endphp
                    <tr>
                        <td class="fw-semibold">{{ $parent?->name ?? ('#' . $branch->party_id) }}</td>
                        <td>{{ $branch->name }} <span class="text-muted fs-11 font-monospace">({{ $branch->code }})</span></td>
                        <td class="font-monospace">{{ $branch->gstin ?: '-' }}</td>
                        <td>
                            <span class="badge {{ $branch->isReturnedForCorrection() ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning' }}">
                                {{ $branch->verificationLabel() }}
                            </span>
                        </td>
                        <td>{{ $branch->submitter?->name ?? '-' }}</td>
                        <td>{{ $branch->submitted_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td class="text-end">
                            @if($verification->isMaker($user, $branch))
                                <span class="text-muted fs-12">Waiting for another verifier</span>
                            @else
                                <a href="{{ route($showRoute, $branch->party_id) }}" class="btn btn-sm btn-primary">Review</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
