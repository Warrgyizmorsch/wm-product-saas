@extends('layouts.duralux')

@section('title', 'Posting Failures | SaaS ERP')
@section('page-title', 'Accounting Posting Failures')
@section('breadcrumb', 'Accounting / Posting Failures')

@section('content')
    <x-ui.card class="mb-4">
        <p class="fs-13 text-muted mb-0">
            Transactions in this list completed successfully in their own module (Sales, Purchase, HRMS, Inventory)
            but their automatic accounting journal could not be posted — usually because the accounting period was
            closed/locked, or a required Chart of Accounts entry was missing. The source transaction was never
            blocked, so these need to be resolved manually: fix the underlying cause (reopen the period, add the
            missing account) then click <strong>Retry</strong>, or <strong>Dismiss</strong> if you already corrected
            the books with a manual journal.
        </p>
    </x-ui.card>

    <x-ui.card title="Unresolved Failures" bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Occurred</th>
                    <th>Source</th>
                    <th>Reason</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($failures as $failure)
                    @php $model = $failure->model(); @endphp
                    <tr>
                        <td class="ps-4 text-muted">{{ $failure->occurred_at->format('d M Y H:i') }}</td>
                        <td>
                            <span class="fw-semibold">{{ class_basename($failure->model_class) }} #{{ $failure->model_id }}</span>
                            @if ($model === null)
                                <span class="d-block fs-11 text-danger">Original record no longer exists</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $failure->message }}</td>
                        <td class="text-end pe-4">
                            <form method="POST" action="{{ route('accounting.posting-failures.retry', $failure) }}" class="d-inline">
                                @csrf
                                <x-ui.button type="submit" variant="primary" size="sm" icon="feather-refresh-cw" :disabled="$model === null">
                                    Retry
                                </x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('accounting.posting-failures.dismiss', $failure) }}" class="d-inline"
                                  onsubmit="return confirm('Dismiss without posting a journal? Only do this if you already corrected the books manually.');">
                                @csrf
                                <x-ui.button type="submit" variant="light" size="sm" class="border">
                                    Dismiss
                                </x-ui.button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">No unresolved posting failures. Books are in sync with the ERP transactions.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
    </style>
@endpush
