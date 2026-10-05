@extends('layouts.duralux')

@section('title', 'Pending Approvals | SaaS ERP')
@section('page-title', 'Pending Approvals')
@section('breadcrumb', 'Accounting / Pending Approvals')

@section('content')
    @if ($canConfigure)
        <x-ui.card class="mb-4">
            <x-slot:title>Approval settings</x-slot:title>
            <form method="POST" action="{{ route('accounting.approvals.settings') }}">
                @csrf
                @method('PUT')
                {{-- Three equal columns, each: label → control → help text, all top-aligned. --}}
                <div class="row g-4 approval-settings">
                    <div class="col-md-4">
                        <span class="approval-settings-label">Approval</span>
                        <div class="approval-settings-control">
                            <x-ui.checkbox name="enabled" id="approvalEnabled" label="Require approval for manual journals and vouchers"
                                :checked="$settings['enabled']" />
                        </div>
                        <small class="text-muted fs-11 d-block mt-1">Automatic postings from Sales, Purchase, payments and bank reconciliation are not affected.</small>
                    </div>
                    <div class="col-md-4">
                        <label for="approvalThreshold" class="approval-settings-label">Only when amount is at least</label>
                        <div class="approval-settings-control">
                            <x-ui.input type="number" name="threshold" id="approvalThreshold" step="0.01" min="0"
                                :value="old('threshold', number_format($settings['threshold'], 2, '.', ''))" />
                        </div>
                        <small class="text-muted fs-11 d-block mt-1">0 sends every manual entry for approval.</small>
                    </div>
                    <div class="col-md-4">
                        <span class="approval-settings-label">Approvers' own entries</span>
                        <div class="approval-settings-control">
                            <x-ui.checkbox name="approvers_post_directly" id="approversPostDirectly" label="Post directly, without a second approver"
                                :checked="$settings['approvers_post_directly']" />
                        </div>
                        <small class="text-muted fs-11 d-block mt-1">Turn off for strict four-eyes: then even an approver's entry waits for a second approver.</small>
                    </div>
                </div>
                <div class="d-flex justify-content-end border-top pt-3 mt-3">
                    <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save">Save settings</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @elseif (!$settings['enabled'])
        <x-ui.alert variant="info" icon="feather-info" class="fs-12 mb-4">
            Approval is currently switched off, so manual journals and vouchers post directly. An owner or admin can switch it on here.
        </x-ui.alert>
    @endif

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <x-slot:title>Waiting for approval <span class="text-muted fw-normal">({{ $pending->total() }})</span></x-slot:title>

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Date</th>
                    <th>Number</th>
                    <th>Type</th>
                    <th>Entered by</th>
                    <th>Lines</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($pending as $journal)
                    @php
                        $isOwn = (int) $journal->posted_by === $currentUserId;
                        $showUrl = $journal->voucher_type
                            ? route('accounting.vouchers.' . $journal->voucher_type . '.show', $journal)
                            : route('accounting.journals.show', $journal);
                    @endphp
                    <tr>
                        <td class="ps-4 text-muted" style="white-space: nowrap;">{{ $journal->journal_date->format('d M Y') }}</td>
                        <td><a href="{{ $showUrl }}" class="fw-semibold">{{ $journal->journal_number }}</a></td>
                        <td class="text-capitalize">{{ $journal->voucher_type ? str_replace('_', ' ', $journal->voucher_type) . ' voucher' : 'Journal' }}</td>
                        <td>
                            {{ $journal->postedBy?->name ?? '—' }}
                            <span class="d-block fs-11 text-muted">{{ $journal->created_at?->diffForHumans() }}</span>
                        </td>
                        <td class="fs-12">
                            @foreach ($journal->entries->sortByDesc(fn ($e) => $e->debit > 0) as $entry)
                                <div>
                                    <span class="text-muted">{{ $entry->debit > 0 ? 'Dr' : 'Cr' }}</span>
                                    {{ $entry->account?->code }} {{ $entry->account?->name }}
                                    <span class="text-muted">{{ number_format(max($entry->debit, $entry->credit), 2) }}</span>
                                </div>
                            @endforeach
                            @if ($journal->memo)
                                <div class="text-muted fst-italic mt-1">{{ $journal->memo }}</div>
                            @endif
                        </td>
                        <td class="text-end fw-semibold">{{ number_format($journal->total_debit, 2) }}</td>
                        <td class="text-end pe-4" style="white-space: nowrap;">
                            @if ($isOwn)
                                <span class="text-muted fs-12" title="Someone else has to approve an entry you made">Your entry</span>
                            @else
                                <div class="d-inline-flex align-items-center gap-2">
                                    <form method="POST" action="{{ route('accounting.approvals.approve', $journal) }}" class="m-0">
                                        @csrf
                                        <x-ui.button type="submit" variant="primary" size="sm" icon="feather-check">Approve</x-ui.button>
                                    </form>
                                    <x-ui.button type="button" variant="light-brand" size="sm" icon="feather-x"
                                        data-bs-toggle="modal" data-bs-target="#rejectJournalModal"
                                        data-reject-url="{{ route('accounting.approvals.reject', $journal) }}"
                                        data-journal-number="{{ $journal->journal_number }}">Reject</x-ui.button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Nothing is waiting for approval.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$pending->currentPage()"
            :totalPages="$pending->lastPage()"
            :totalResults="$pending->total()"
            :perPage="$pending->perPage()" />
    </x-ui.card>

    @include('modules.accounting.approvals._reject-modal')
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
            vertical-align: middle;
        }

        .approval-settings-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #374151;
            margin-bottom: 8px;
        }

        /* Same height for the checkbox rows and the input, so all three line up. */
        .approval-settings-control {
            min-height: 42px;
            display: flex;
            align-items: center;
        }

        .approval-settings-control .form-check,
        .approval-settings-control > .mb-3 {
            margin-bottom: 0 !important;
        }

        .approval-settings-control > .mb-3 {
            width: 100%;
        }
    </style>
@endpush
