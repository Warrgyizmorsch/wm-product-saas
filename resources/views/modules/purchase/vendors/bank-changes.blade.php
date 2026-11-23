@extends('layouts.duralux')

@section('title', 'Bank change requests')
@section('page-title', 'Bank change requests')
@section('breadcrumb', 'Vendors')

@section('page-actions')
    <a href="{{ route('purchase.vendors.index') }}" class="btn btn-light border">Back to vendors</a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
        <p class="text-muted fs-12 mb-3">
            Open a pending request, call the vendor on the number on file, record the call, then approve or reject.
            You cannot approve a request you raised.
        </p>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Vendor</th><th>Requested by</th><th>Requested</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($requests as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->vendor?->name ?? ('Vendor #' . $row->party_id) }}</td>
                            <td>{{ $row->requester?->name ?? '-' }}</td>
                            <td>{{ $row->requested_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $row->status === 'pending' ? 'bg-soft-warning text-warning' : ($row->status === 'approved' ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary') }}">{{ ucfirst($row->status) }}</span>
                            </td>
                            <td class="text-end">
                                @if($row->status === 'pending' && (int) $row->requested_by === (int) auth()->id())
                                    <span class="text-muted fs-12">Waiting for another approver</span>
                                @else
                                    <a href="{{ route('purchase.vendors.bank-changes.show', $row->id) }}" class="btn btn-sm btn-primary">{{ $row->status === 'pending' ? 'Review' : 'View' }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No bank change requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $requests->links() }}</div>
    </div>
@endsection
