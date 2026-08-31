@extends('layouts.duralux')

@section('title', 'Day Book | SaaS ERP')
@section('page-title', 'Day Book')
@section('breadcrumb', 'Accounting / Reports / Day Book')

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark">Date</label>
                <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="form-control">
            </div>
        </form>
    </x-ui.card>

    <x-ui.card class="mb-3">
        <div class="d-flex justify-content-between align-items-center fs-13">
            <span class="text-muted">All vouchers &amp; journals dated {{ $date->format('d M Y') }}</span>
            <span class="fw-semibold">{{ $journals->count() }} {{ Str::plural('entry', $journals->count()) }}</span>
        </div>
    </x-ui.card>

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable class="mb-0 daybook-table">
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Voucher #</th>
                    <th>Type</th>
                    <th>Memo / Party</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Amount</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($journals as $journal)
                    <tr class="db-group-row" role="button" data-target="jnl-{{ $journal->id }}">
                        <td class="ps-4 fw-bold font-monospace">
                            <i class="feather-chevron-right db-chevron me-1"></i>{{ $journal->journal_number }}
                        </td>
                        <td class="text-capitalize">{{ $journal->voucher_type ? str_replace('_', ' ', $journal->voucher_type) : ucfirst($journal->source) }}</td>
                        <td>{{ $journal->voucherDetail?->party_name ?? $journal->memo }}</td>
                        <td>
                            @if ($journal->status === 'posted')
                                <x-ui.badge variant="success" soft>Posted</x-ui.badge>
                            @elseif ($journal->status === 'reversed')
                                <x-ui.badge variant="secondary" soft>Reversed</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning" soft>{{ ucfirst($journal->status) }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-end pe-4 fw-semibold">{{ number_format($journal->total_debit, 2) }}</td>
                    </tr>
                    @foreach ($journal->entries as $entry)
                        <tr class="db-detail-row d-none" data-group="jnl-{{ $journal->id }}">
                            <td class="ps-5 text-muted" colspan="2">{{ $entry->account?->code }} {{ $entry->account?->name }}</td>
                            <td colspan="2" class="text-muted">{{ $entry->description }}</td>
                            <td class="text-end pe-4">
                                @if ((float) $entry->debit > 0)
                                    Dr {{ number_format($entry->debit, 2) }}
                                @else
                                    Cr {{ number_format($entry->credit, 2) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">No vouchers or journals posted on this date.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($journals->isNotEmpty())
                <tfoot>
                    <tr class="fw-bold fs-13 bg-light">
                        <td class="ps-4" colspan="4">Total</td>
                        <td class="text-end pe-4">{{ number_format($totalDebit, 2) }}</td>
                    </tr>
                </tfoot>
            @endif
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
        .db-group-row {
            cursor: pointer;
        }
        .db-group-row:hover {
            background-color: var(--bs-light, #f8f9fa);
        }
        .db-chevron {
            font-size: 11px;
            transition: transform 0.15s ease;
            display: inline-block;
        }
        .db-group-row.is-open .db-chevron {
            transform: rotate(90deg);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('.db-group-row').forEach(function (header) {
            header.addEventListener('click', function () {
                var target = header.getAttribute('data-target');
                var isOpen = header.classList.toggle('is-open');
                document.querySelectorAll('.db-detail-row[data-group="' + target + '"]').forEach(function (row) {
                    row.classList.toggle('d-none', !isOpen);
                });
            });
        });
    </script>
@endpush
