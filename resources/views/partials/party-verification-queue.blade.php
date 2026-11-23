{{-- Queue table for the verification screens. Expects $parties (paginator), $type ('customer'|'vendor'). --}}
@php
    $isCustomer = $type === 'customer';
    $user = auth()->user();
    $showRoute = $isCustomer ? 'crm.customers.show' : 'purchase.vendors.show';
@endphp

<div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
    <h5 class="fw-bold mb-1">{{ $isCustomer ? 'Customers' : 'Vendors' }} pending verification</h5>
    <p class="text-muted fs-12 mb-3">
        Open a record to check its GSTIN, PAN and bank details against the supporting documents.
        You cannot verify a record you created or last edited.
    </p>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>GSTIN</th>
                    <th>PAN</th>
                    <th>Bank</th>
                    <th>Status</th>
                    <th>Submitted by</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($parties as $party)
                    <tr>
                        <td class="fw-semibold">{{ $party->name }}</td>
                        <td>{{ $party->gstin ?: '-' }}</td>
                        <td>{{ $party->pan ?: '-' }}</td>
                        <td>{{ $party->bank_name ?: '-' }}</td>
                        <td>
                            <span class="badge {{ $party->isReturnedForCorrection() ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning' }}">
                                {{ $party->verificationLabel() }}
                            </span>
                        </td>
                        <td>{{ $party->submitter?->name ?? '-' }}</td>
                        <td>{{ $party->submitted_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td class="text-end">
                            @if($verification->isMaker($user, $party))
                                <span class="text-muted fs-12">Waiting for another verifier</span>
                            @else
                                <a href="{{ route($showRoute, $party->id) }}" class="btn btn-sm btn-primary">Review</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Nothing is waiting for verification.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $parties->links() }}</div>
</div>
