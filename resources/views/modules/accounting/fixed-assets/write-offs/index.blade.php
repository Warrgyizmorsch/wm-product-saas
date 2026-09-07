@extends('layouts.duralux')

@section('title', 'Asset Write-offs | SaaS ERP')
@section('page-title', 'Asset Write-offs')
@section('breadcrumb', 'Accounting / Fixed Assets / Write-offs')

@section('page-actions')
    <x-ui.button href="{{ route('accounting.fixed-assets.write-offs.create') }}" variant="primary" icon="feather-plus">
        New Write-off
    </x-ui.button>
@endsection

@section('content')
    <x-ui.card bodyClass="p-0">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Asset</th>
                    <th>Date</th>
                    <th>Reason</th>
                    <th class="text-end">Net Book Value</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($writeOffs as $writeOff)
                    <tr>
                        <td class="ps-4">
                            <a href="{{ route('accounting.fixed-assets.show', $writeOff->asset_id) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                {{ $writeOff->asset->asset_code }}
                            </a>
                        </td>
                        <td>{{ $writeOff->write_off_date->format('d M Y') }}</td>
                        <td class="text-muted text-truncate" style="max-width: 260px;">{{ $writeOff->reason }}</td>
                        <td class="text-end">{{ number_format($writeOff->net_book_value, 2) }}</td>
                        <td><x-ui.status-badge :status="$writeOff->status" /></td>
                        <td class="text-end pe-4" style="white-space: nowrap;">
                            @if ($writeOff->status === 'pending_approval' && $canApprove)
                                <form action="{{ route('accounting.fixed-assets.write-offs.approve', $writeOff) }}" method="POST" class="d-inline-flex" id="approveWriteOff_{{ $writeOff->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-soft-success border-0" title="Approve" onclick="confirmAction({title: 'Approve Write-off', message: 'Approve this write-off request?', variant: 'success', confirmText: 'Approve'}, function() { document.getElementById('approveWriteOff_{{ $writeOff->id }}').submit(); })">
                                        <i class="feather-check-circle"></i>
                                    </button>
                                </form>
                                <form action="{{ route('accounting.fixed-assets.write-offs.reject', $writeOff) }}" method="POST" class="d-inline-flex" id="rejectWriteOff_{{ $writeOff->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-soft-danger border-0" title="Reject" onclick="confirmAction({title: 'Reject Write-off', message: 'Reject this write-off request?', variant: 'danger', confirmText: 'Reject'}, function() { document.getElementById('rejectWriteOff_{{ $writeOff->id }}').submit(); })">
                                        <i class="feather-x-circle"></i>
                                    </button>
                                </form>
                            @elseif ($writeOff->status === 'approved' && $canApprove)
                                <form action="{{ route('accounting.fixed-assets.write-offs.post', $writeOff) }}" method="POST" class="d-inline-flex" id="postWriteOff_{{ $writeOff->id }}">
                                    @csrf
                                    <button type="button" class="btn btn-xs btn-primary" title="Post to GL" onclick="confirmAction({title: 'Post Write-off', message: 'Post this write-off to the general ledger? This cannot be undone.', variant: 'primary', confirmText: 'Post'}, function() { document.getElementById('postWriteOff_{{ $writeOff->id }}').submit(); })">
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
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="feather-slash fs-1 mb-2 d-block"></i>
                            No write-off requests found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$writeOffs->currentPage()"
            :totalPages="$writeOffs->lastPage()"
            :totalResults="$writeOffs->total()"
            :perPage="$writeOffs->perPage()" />
    </x-ui.card>
@endsection
