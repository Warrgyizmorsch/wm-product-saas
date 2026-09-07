@extends('layouts.duralux')

@section('title', 'Asset Revaluations | SaaS ERP')
@section('page-title', 'Asset Revaluations')
@section('breadcrumb', 'Accounting / Fixed Assets / Revaluations')

@section('page-actions')
    <x-ui.button href="{{ route('accounting.fixed-assets.revaluations.create') }}" variant="primary" icon="feather-plus">
        New Revaluation
    </x-ui.button>
@endsection

@section('content')
    <x-ui.card bodyClass="p-0">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Asset</th>
                    <th>Date</th>
                    <th class="text-end">Previous Book Value</th>
                    <th class="text-end">Revalued Amount</th>
                    <th class="text-end">Surplus / (Deficit)</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($revaluations as $revaluation)
                    <tr>
                        <td class="ps-4">
                            <a href="{{ route('accounting.fixed-assets.show', $revaluation->asset_id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                {{ $revaluation->asset->asset_code }}
                            </a>
                        </td>
                        <td>{{ $revaluation->revaluation_date->format('d M Y') }}</td>
                        <td class="text-end">{{ number_format($revaluation->previous_book_value, 2) }}</td>
                        <td class="text-end">{{ number_format($revaluation->revalued_amount, 2) }}</td>
                        <td class="text-end {{ $revaluation->revaluation_surplus_deficit >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($revaluation->revaluation_surplus_deficit, 2) }}
                        </td>
                        <td><x-ui.status-badge :status="$revaluation->status" /></td>
                        <td class="text-end pe-4" style="white-space: nowrap;">
                            @if ($revaluation->status === 'pending_approval' && $canApprove)
                                <form action="{{ route('accounting.fixed-assets.revaluations.approve', $revaluation) }}" method="POST" class="d-inline-flex" id="approveRevaluation_{{ $revaluation->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-soft-success border-0" title="Approve" onclick="confirmAction({title: 'Approve Revaluation', message: 'Approve this revaluation request?', variant: 'success', confirmText: 'Approve'}, function() { document.getElementById('approveRevaluation_{{ $revaluation->id }}').submit(); })">
                                        <i class="feather-check-circle"></i>
                                    </button>
                                </form>
                                <form action="{{ route('accounting.fixed-assets.revaluations.reject', $revaluation) }}" method="POST" class="d-inline-flex" id="rejectRevaluation_{{ $revaluation->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-soft-danger border-0" title="Reject" onclick="confirmAction({title: 'Reject Revaluation', message: 'Reject this revaluation request?', variant: 'danger', confirmText: 'Reject'}, function() { document.getElementById('rejectRevaluation_{{ $revaluation->id }}').submit(); })">
                                        <i class="feather-x-circle"></i>
                                    </button>
                                </form>
                            @elseif ($revaluation->status === 'approved' && $canApprove)
                                <form action="{{ route('accounting.fixed-assets.revaluations.post', $revaluation) }}" method="POST" class="d-inline-flex" id="postRevaluation_{{ $revaluation->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-primary" title="Post to GL" onclick="confirmAction({title: 'Post Revaluation', message: 'Post this revaluation to the general ledger? This cannot be undone.', variant: 'primary', confirmText: 'Post'}, function() { document.getElementById('postRevaluation_{{ $revaluation->id }}').submit(); })">
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
                            <i class="feather-trending-up fs-1 mb-2 d-block"></i>
                            No revaluation requests found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$revaluations->currentPage()"
            :totalPages="$revaluations->lastPage()"
            :totalResults="$revaluations->total()"
            :perPage="$revaluations->perPage()" />
    </x-ui.card>
@endsection
