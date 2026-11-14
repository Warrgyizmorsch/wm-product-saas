{{--
    3-way match result for one bill: each line against its PO line (rate) and
    GRN line (accepted qty). Expects $bill; optional $canRelease.
--}}
@php
    $match = $bill->match_details;
    $lines = collect($match['lines'] ?? [])->where('basis', '!=', 'none');
    $fmtQty = fn ($q) => $q === null ? '—' : rtrim(rtrim(number_format((float) $q, 3, '.', ','), '0'), '.');

    // Auto-expand if bill is on hold or has mismatch; keep compact/collapsed if matched cleanly
    $hasMismatch = ($bill->match_status !== 'matched');
    $isDefaultOpen = $bill->isOnHold() || $hasMismatch;
@endphp

@if ($bill->match_status && $lines->isNotEmpty())
    <div class="card border shadow-xs rounded-3 mb-3 bg-white overflow-hidden">
        {{-- Slim, compact summary bar --}}
        <div class="card-header bg-white py-2 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom-0" style="min-height: 44px;">
            <div class="d-flex align-items-center flex-wrap gap-2">
                @if ($bill->match_status === 'matched')
                    <span class="badge bg-soft-success text-success fw-bold fs-11 px-2.5 py-1 d-inline-flex align-items-center gap-1 rounded-pill">
                        <i class="feather-check-circle fs-12"></i> 3-way match: Matched
                    </span>
                @else
                    <span class="badge bg-soft-danger text-danger fw-bold fs-11 px-2.5 py-1 d-inline-flex align-items-center gap-1 rounded-pill">
                        <i class="feather-alert-triangle fs-12"></i> 3-way match: Mismatch
                    </span>
                @endif

                @if ($bill->isOnHold())
                    <span class="badge bg-soft-warning text-warning fw-bold fs-11 px-2.5 py-1 rounded-pill">
                        <i class="feather-pause-circle fs-12 me-1"></i>On Hold
                    </span>
                @endif

                <span class="text-muted fw-normal fs-11 ms-1 d-none d-md-inline">
                    (Tolerance: qty {{ $match['qty_tolerance_percent'] ?? 0 }}% · price {{ $match['price_tolerance_percent'] ?? 0 }}%)
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                @if ($bill->isOnHold() && ($canRelease ?? false) && (int) $bill->created_by !== (int) auth()->id())
                    <button type="button" class="btn btn-xs btn-primary fw-semibold px-2.5 py-1"
                        data-bs-toggle="modal" data-bs-target="#releaseHoldModal"
                        data-release-url="{{ route('purchase.bills.release-hold', $bill->id) }}"
                        data-bill-number="{{ $bill->bill_number }}">
                        <i class="feather-unlock me-1"></i>Release Hold
                    </button>
                @endif

                <button type="button" 
                        class="btn btn-xs btn-light border text-secondary px-2.5 py-1 d-inline-flex align-items-center gap-1 rounded-2 fw-semibold" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#matchTableCollapse" 
                        aria-expanded="{{ $isDefaultOpen ? 'true' : 'false' }}" 
                        id="toggleMatchDetailsBtn">
                    <span class="toggle-text">{{ $isDefaultOpen ? 'Hide Details' : 'View Details' }}</span>
                    <i class="feather-{{ $isDefaultOpen ? 'chevron-up' : 'chevron-down' }} fs-12" id="toggleMatchDetailsIcon"></i>
                </button>
            </div>
        </div>

        {{-- Collapsible Details Section --}}
        <div class="collapse {{ $isDefaultOpen ? 'show' : '' }}" id="matchTableCollapse">
            <div class="border-top">
                @if ($bill->isOnHold())
                    <div class="px-4 py-3 bg-soft-primary border-bottom">
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap fs-13 text-primary">
                            <span><i class="feather-info me-1"></i><strong>Notice:</strong> This bill is on hold — not posted to the ledger and cannot be paid until someone releases it.</span>
                            @if (($canRelease ?? false) && (int) $bill->created_by !== (int) auth()->id())
                                <button type="button" class="btn btn-xs btn-primary fw-semibold"
                                    data-bs-toggle="modal" data-bs-target="#releaseHoldModal"
                                    data-release-url="{{ route('purchase.bills.release-hold', $bill->id) }}"
                                    data-bill-number="{{ $bill->bill_number }}">
                                    <i class="feather-unlock me-1"></i>Release Hold
                                </button>
                            @endif
                        </div>
                    </div>
                @elseif ($bill->hold_released_at)
                    <div class="px-4 py-2 bg-light fs-12 text-muted border-bottom">
                        <i class="feather-unlock me-1 text-success"></i>Hold released by <strong>{{ $bill->holdReleasedBy?->name ?? 'unknown' }}</strong> on {{ $bill->hold_released_at->format('d M Y H:i') }} — <em>{{ $bill->hold_release_reason }}</em>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-13 text-dark">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4 py-2.5">Item</th>
                                <th class="py-2.5">Basis</th>
                                <th class="text-end py-2.5">PO rate ({{ active_currency_symbol() }})</th>
                                <th class="text-end py-2.5">Bill rate ({{ active_currency_symbol() }})</th>
                                <th class="text-end py-2.5">Rate diff</th>
                                <th class="text-end py-2.5">Accepted / ordered</th>
                                <th class="text-end py-2.5">Billed earlier</th>
                                <th class="text-end py-2.5">This bill</th>
                                <th class="pe-4 py-2.5">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $line)
                                @php
                                    $isDirectGrn = ($line['basis'] === 'grn-only') || ($line['basis'] === '3-way' && $line['po_rate'] === null && empty($line['po_qty']));
                                    $basisDisplay = $isDirectGrn ? 'GRN only' : ($line['basis'] === '3-way' ? 'PO + GRN' : 'PO only');
                                @endphp
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">{{ $line['product'] }}</td>
                                    <td><span class="badge bg-light text-secondary border fs-11">{{ $basisDisplay }}</span></td>
                                    <td class="text-end font-monospace">{{ $line['po_rate'] !== null ? format_currency($line['po_rate']) : '—' }}</td>
                                    <td class="text-end font-monospace">{{ format_currency($line['bill_rate']) }}</td>
                                    <td class="text-end font-monospace {{ $line['price_ok'] ? 'text-muted' : 'text-danger fw-bold' }}">
                                        {{ $line['price_variance_percent'] !== null ? ($line['price_variance_percent'] > 0 ? '+' : '') . $line['price_variance_percent'] . '%' : '—' }}
                                    </td>
                                    <td class="text-end font-monospace">{{ $fmtQty($line['received_qty'] ?? $line['po_qty']) }}</td>
                                    <td class="text-end font-monospace text-muted">{{ $fmtQty($line['billed_before_qty']) }}</td>
                                    <td class="text-end font-monospace {{ $line['qty_ok'] ? 'text-dark' : 'text-danger fw-bold' }}">{{ $fmtQty($line['bill_qty']) }}</td>
                                    <td class="pe-4">
                                        @if ($line['qty_ok'] && $line['price_ok'])
                                            <span class="badge bg-soft-success text-success fw-bold fs-11 px-2 py-0.5">OK</span>
                                        @else
                                            @foreach ($line['messages'] as $message)
                                                <div class="fs-11 text-danger fw-semibold"><i class="feather-x-circle me-1"></i>{{ $message }}</div>
                                            @endforeach
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var collapseEl = document.getElementById('matchTableCollapse');
                var toggleBtn = document.getElementById('toggleMatchDetailsBtn');
                var toggleText = toggleBtn ? toggleBtn.querySelector('.toggle-text') : null;
                var toggleIcon = document.getElementById('toggleMatchDetailsIcon');

                if (collapseEl && toggleBtn) {
                    collapseEl.addEventListener('show.bs.collapse', function () {
                        if (toggleText) toggleText.textContent = 'Hide Details';
                        if (toggleIcon) {
                            toggleIcon.classList.remove('feather-chevron-down');
                            toggleIcon.classList.add('feather-chevron-up');
                        }
                    });
                    collapseEl.addEventListener('hide.bs.collapse', function () {
                        if (toggleText) toggleText.textContent = 'View Details';
                        if (toggleIcon) {
                            toggleIcon.classList.remove('feather-chevron-up');
                            toggleIcon.classList.add('feather-chevron-down');
                        }
                    });
                }
            });
        </script>
    @endpush

    @if ($bill->isOnHold() && ($canRelease ?? false))
        @include('modules.purchase.bill-matching._release-modal')
    @endif
@endif
