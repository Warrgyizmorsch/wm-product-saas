{{--
    3-way match result for one bill: each line against its PO line (rate) and
    GRN line (accepted qty). Expects $bill; optional $canRelease.
--}}
@php
    $match = $bill->match_details;
    $lines = collect($match['lines'] ?? [])->where('basis', '!=', 'none');
    $fmtQty = fn ($q) => $q === null ? '—' : rtrim(rtrim(number_format((float) $q, 3, '.', ','), '0'), '.');
@endphp

@if ($bill->match_status && $lines->isNotEmpty())
    <x-ui.card class="mb-4" bodyClass="p-0">
        <x-slot:title>
            3-way match
            @if ($bill->match_status === 'matched')
                <x-ui.badge variant="success" soft class="ms-2">Matched</x-ui.badge>
            @else
                <x-ui.badge variant="danger" soft class="ms-2">Mismatch</x-ui.badge>
            @endif
            <span class="text-muted fw-normal fs-12 ms-2">Tolerance: qty {{ $match['qty_tolerance_percent'] ?? 0 }}% · price {{ $match['price_tolerance_percent'] ?? 0 }}%</span>
        </x-slot:title>

        @if ($bill->isOnHold())
            <div class="px-4 pt-3">
                <x-ui.alert variant="primary" icon="feather-pause-circle" class="fs-13 mb-0">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                        <span>On hold — not posted to the ledger and can't be paid until someone releases it.</span>
                        @if (($canRelease ?? false) && (int) $bill->created_by !== (int) auth()->id())
                            <x-ui.button type="button" variant="primary" size="sm" icon="feather-unlock"
                                data-bs-toggle="modal" data-bs-target="#releaseHoldModal"
                                data-release-url="{{ route('purchase.bills.release-hold', $bill->id) }}"
                                data-bill-number="{{ $bill->bill_number }}">Release hold</x-ui.button>
                        @endif
                    </div>
                </x-ui.alert>
            </div>
        @elseif ($bill->hold_released_at)
            <div class="px-4 pt-3 fs-12 text-muted">
                <i class="feather-unlock me-1"></i>Hold released by {{ $bill->holdReleasedBy?->name ?? 'unknown' }} on {{ $bill->hold_released_at->format('d M Y H:i') }} — {{ $bill->hold_release_reason }}
            </div>
        @endif

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Item</th>
                    <th>Basis</th>
                    <th class="text-end">PO rate</th>
                    <th class="text-end">Bill rate</th>
                    <th class="text-end">Rate diff</th>
                    <th class="text-end">Accepted / ordered</th>
                    <th class="text-end">Billed earlier</th>
                    <th class="text-end">This bill</th>
                    <th class="pe-4">Result</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @foreach ($lines as $line)
                    <tr>
                        <td class="ps-4 fw-semibold">{{ $line['product'] }}</td>
                        <td class="text-muted">{{ $line['basis'] === '3-way' ? 'PO + GRN' : 'PO only' }}</td>
                        <td class="text-end">{{ $line['po_rate'] !== null ? number_format($line['po_rate'], 2) : '—' }}</td>
                        <td class="text-end">{{ number_format($line['bill_rate'], 2) }}</td>
                        <td class="text-end {{ $line['price_ok'] ? '' : 'text-danger fw-semibold' }}">
                            {{ $line['price_variance_percent'] !== null ? ($line['price_variance_percent'] > 0 ? '+' : '') . $line['price_variance_percent'] . '%' : '—' }}
                        </td>
                        <td class="text-end">{{ $fmtQty($line['received_qty'] ?? $line['po_qty']) }}</td>
                        <td class="text-end">{{ $fmtQty($line['billed_before_qty']) }}</td>
                        <td class="text-end {{ $line['qty_ok'] ? '' : 'text-danger fw-semibold' }}">{{ $fmtQty($line['bill_qty']) }}</td>
                        <td class="pe-4">
                            @if ($line['qty_ok'] && $line['price_ok'])
                                <x-ui.badge variant="success" soft>OK</x-ui.badge>
                            @else
                                @foreach ($line['messages'] as $message)
                                    <div class="fs-12 text-danger">{{ $message }}</div>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.card>

    @if ($bill->isOnHold() && ($canRelease ?? false))
        @include('modules.purchase.bill-matching._release-modal')
    @endif
@endif
