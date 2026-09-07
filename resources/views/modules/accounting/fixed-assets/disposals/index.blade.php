@extends('layouts.duralux')

@section('title', 'Asset Disposals | SaaS ERP')
@section('page-title', 'Asset Disposals')
@section('breadcrumb', 'Accounting / Fixed Assets / Disposals')

@section('page-actions')
    <x-ui.select :selected="$status" :options="[
        '' => 'All Statuses', 'pending_approval' => 'Pending Approval', 'approved' => 'Approved',
        'rejected' => 'Rejected', 'posted' => 'Posted',
    ]" name="status" onchange="window.location = updateQueryParam('status', this.value)" />
    <x-ui.button href="{{ route('accounting.fixed-assets.disposals.create') }}" variant="primary" icon="feather-plus">
        New Disposal
    </x-ui.button>
@endsection

@section('content')
    <x-ui.card bodyClass="p-0">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Asset</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th class="text-end">Net Book Value</th>
                    <th class="text-end">Gain / (Loss)</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($disposals as $disposal)
                    <tr>
                        <td class="ps-4">
                            <a href="{{ route('accounting.fixed-assets.show', $disposal->asset_id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                {{ $disposal->asset->asset_code }}
                            </a>
                        </td>
                        <td class="text-capitalize">{{ $disposal->disposal_type }}</td>
                        <td>{{ $disposal->disposal_date->format('d M Y') }}</td>
                        <td class="text-end">{{ number_format($disposal->net_book_value, 2) }}</td>
                        <td class="text-end {{ $disposal->gain_loss_amount >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($disposal->gain_loss_amount, 2) }}
                        </td>
                        <td><x-ui.status-badge :status="$disposal->status" /></td>
                        <td class="text-end pe-4" style="white-space: nowrap;">
                            @if ($disposal->status === 'pending_approval' && $canApprove)
                                <form action="{{ route('accounting.fixed-assets.disposals.approve', $disposal) }}" method="POST" class="d-inline-flex" id="approveDisposal_{{ $disposal->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-soft-success border-0" title="Approve" onclick="confirmAction({title: 'Approve Disposal', message: 'Approve this disposal request?', variant: 'success', confirmText: 'Approve'}, function() { document.getElementById('approveDisposal_{{ $disposal->id }}').submit(); })">
                                        <i class="feather-check-circle"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-xs btn-soft-danger border-0" title="Reject" onclick="openRejectModal('{{ route('accounting.fixed-assets.disposals.reject', $disposal) }}', '{{ $disposal->asset->asset_code }}')">
                                    <i class="feather-x-circle"></i>
                                </button>
                            @elseif ($disposal->status === 'approved' && $canApprove)
                                <form action="{{ route('accounting.fixed-assets.disposals.post', $disposal) }}" method="POST" class="d-inline-flex" id="postDisposal_{{ $disposal->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-primary" title="Post to GL" onclick="confirmAction({title: 'Post Disposal', message: 'Post this disposal to the general ledger? This cannot be undone.', variant: 'primary', confirmText: 'Post'}, function() { document.getElementById('postDisposal_{{ $disposal->id }}').submit(); })">
                                        <i class="feather-upload"></i> Post
                                    </button>
                                </form>
                            @else
                                <span class="text-muted fs-11">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="feather-trash-2 fs-1 mb-2 d-block"></i>
                            No disposal requests found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$disposals->currentPage()"
            :totalPages="$disposals->lastPage()"
            :totalResults="$disposals->total()"
            :perPage="$disposals->perPage()" />
    </x-ui.card>

    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="rejectActionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <form id="rejectActionForm" method="POST" action="">
                    @csrf
                    <div class="modal-header bg-soft-danger text-danger border-bottom-0">
                        <h5 class="modal-title fw-bold">
                            <i class="feather-x-circle me-2"></i>Reject Disposal <span id="rejectModalDocNumber" class="text-dark"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold text-dark fs-12 mb-1">Rejection reason isn't required by this workflow &mdash; approver decision only.</label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 px-4 py-3">
                        <button type="button" class="btn btn-light btn-sm border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function updateQueryParam(key, value) {
        const url = new URL(window.location.href);
        if (value) { url.searchParams.set(key, value); } else { url.searchParams.delete(key); }
        return url.toString();
    }

    function openRejectModal(actionUrl, docNumber) {
        document.getElementById('rejectActionForm').action = actionUrl;
        document.getElementById('rejectModalDocNumber').innerText = docNumber ? '(' + docNumber + ')' : '';
        var modal = new bootstrap.Modal(document.getElementById('rejectActionModal'));
        modal.show();
    }
</script>
@endpush
