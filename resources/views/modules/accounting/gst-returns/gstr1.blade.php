@php
    use App\Domains\Accounting\Services\Gst\Gstr1ReturnService as G;
    use App\Domains\Accounting\Support\GstStates;

    $from = $built['from'];
    $to = $built['to'];
    $seller = $built['seller'];
    $groups = $built['groups'];
    $blocked = $built['blockers'] !== [];
    $money = fn ($v) => number_format((float) $v, 2);
    $periodQuery = ['from' => $from->toDateString(), 'to' => $to->toDateString()];

    $stateBadges = [
        G::STATE_PENDING => ['New', 'bg-soft-primary text-primary'],
        G::STATE_MODIFIED => ['Modified after upload', 'bg-soft-warning text-warning'],
        G::STATE_DELETE => ['To delete (cancelled)', 'bg-soft-danger text-danger'],
        G::STATE_DELETE_REQUESTED => ['Delete requested', 'bg-soft-danger text-danger'],
        G::STATE_UPLOADED => ['Uploaded', 'bg-soft-success text-success'],
        G::STATE_REMOVED => ['Deleted from portal', 'bg-soft-secondary text-muted'],
        G::STATE_EXCEPTION => ['Needs correction', 'bg-soft-danger text-danger'],
    ];

    $bySection = function (array $docs) {
        $out = [];
        foreach (array_keys(G::SECTION_LABELS) as $section) {
            $rows = array_filter($docs, fn ($doc) => $doc['section'] === $section);
            if ($rows !== []) {
                $out[$section] = $rows;
            }
        }
        return $out;
    };

    $sum = fn (array $docs, string $field) => array_sum(array_map(fn ($doc) => $doc['sign'] * $doc[$field], $docs));

    $months = collect(range(0, 12))->map(fn ($i) => now()->subMonthsNoOverflow($i)->startOfMonth());
@endphp

@extends('layouts.duralux')

@section('title', 'Upload GST Returns | SaaS ERP')
@section('page-title', 'Upload GST Returns')
@section('breadcrumb', 'Accounting / GST / Upload GSTR-1')

@section('page-actions')
    <a href="{{ route('accounting.reports.gstr1', $periodQuery) }}" class="btn btn-light border btn-sm">
        <i class="feather-file-text me-1"></i>GSTR-1 Report
    </a>
@endsection

@section('content')
<div class="gstr-upload" id="gstrUpload">
    <x-ui.card class="mb-3">
        <form method="GET" id="gstrPeriodForm" class="row g-2 align-items-end">
            @if ($includeUploaded)
                <input type="hidden" name="include_uploaded" value="1">
            @endif
            <div class="col-12 col-md-3">
                <label class="form-label fs-12 mb-1">Return period</label>
                <select class="form-select form-select-sm" id="gstrMonth" aria-label="Return month">
                    <option value="">Custom…</option>
                    @foreach ($months as $month)
                        <option value="{{ $month->toDateString() }}|{{ $month->copy()->endOfMonth()->toDateString() }}"
                            @selected($from->isSameDay($month) && $to->isSameDay($month->copy()->endOfMonth()))>
                            {{ $month->format('F Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label fs-12 mb-1" for="gstrFrom">From</label>
                <input type="date" name="from" id="gstrFrom" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label fs-12 mb-1" for="gstrTo">To</label>
                <input type="date" name="to" id="gstrTo" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Show</button>
            </div>
        </form>
    </x-ui.card>

    @foreach ($built['blockers'] as $blocker)
        <div class="alert alert-warning py-2 fs-13 mb-2"><i class="feather-alert-triangle me-1"></i>{{ $blocker }}</div>
    @endforeach

    <div class="row g-3">
        <div class="col-12 col-xl-9">
            <form method="POST" id="gstrForm" action="{{ route('accounting.gst-returns.gstr1.status', $periodQuery) }}">
                @csrf
                <input type="hidden" name="action" id="gstrAction" value="">
                <input type="hidden" name="scope" id="gstrScope" value="all">

                <div class="card stretch mb-3">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                        <div>
                            <h6 class="mb-0 fw-bold">Upload GSTR-1</h6>
                            <div class="fs-11 text-muted">
                                {{ $seller['name'] ?? 'Your business' }} · GSTIN
                                <span class="font-monospace">{{ $seller['gstin'] ?: 'not set' }}</span>
                                @if ($seller['state']) · {{ GstStates::name($seller['state']) }} @endif
                            </div>
                        </div>
                        <div class="text-end fs-12">
                            <div class="fw-bold">{{ $from->format('j-M-y') }} to {{ $to->format('j-M-y') }}</div>
                            <div class="text-muted">Return period {{ $built['fp'] ? substr($built['fp'], 0, 2) . '/' . substr($built['fp'], 2) : '—' }}</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 gstr-table align-middle">
                            <thead class="table-light fs-11 text-uppercase text-muted">
                                <tr>
                                    <th class="ps-3" style="width:32px">
                                        <input type="checkbox" class="form-check-input" id="gstrAll" aria-label="Select all">
                                    </th>
                                    <th>Date</th>
                                    <th>Particulars</th>
                                    <th>Vch Type</th>
                                    <th>Vch No.</th>
                                    <th class="text-end">Taxable Amount</th>
                                    <th class="text-end">Tax Amount</th>
                                    <th class="text-end pe-3">Invoice Amount</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13">
                                {{-- Pending --}}
                                <tr class="gstr-group">
                                    <td colspan="8" class="ps-3 fw-bold">
                                        Pending to Upload (Voucher Count - {{ count($groups['pending']) }}; Summary Count - {{ $built['summary_count'] }})
                                    </td>
                                </tr>
                                @forelse ($bySection($groups['pending']) as $section => $docs)
                                    @include('modules.accounting.gst-returns.partials.gstr1-rows', ['docs' => $docs, 'heading' => G::SECTION_LABELS[$section]])
                                @empty
                                    <tr><td></td><td colspan="7" class="text-muted py-2">Nothing pending. Every voucher of this period is uploaded.</td></tr>
                                @endforelse

                                {{-- Exceptions --}}
                                @if ($groups['exception'] !== [])
                                    <tr class="gstr-group">
                                        <td colspan="8" class="ps-3 fw-bold text-danger">
                                            Not Ready to Upload — needs correction (Voucher Count - {{ count($groups['exception']) }})
                                        </td>
                                    </tr>
                                    @foreach ($bySection($groups['exception']) as $section => $docs)
                                        @include('modules.accounting.gst-returns.partials.gstr1-rows', ['docs' => $docs, 'heading' => G::SECTION_LABELS[$section]])
                                    @endforeach
                                @endif

                                @if ($includeUploaded)
                                    <tr class="gstr-group">
                                        <td colspan="8" class="ps-3 fw-bold text-success">Uploaded (Voucher Count - {{ count($groups['uploaded']) }})</td>
                                    </tr>
                                    @forelse ($bySection($groups['uploaded']) as $section => $docs)
                                        @include('modules.accounting.gst-returns.partials.gstr1-rows', ['docs' => $docs, 'heading' => G::SECTION_LABELS[$section]])
                                    @empty
                                        <tr><td></td><td colspan="7" class="text-muted py-2">No uploaded vouchers in this period.</td></tr>
                                    @endforelse

                                    @if ($groups['removed'] !== [])
                                        <tr class="gstr-group">
                                            <td colspan="8" class="ps-3 fw-bold text-muted">Deleted from Portal (Voucher Count - {{ count($groups['removed']) }})</td>
                                        </tr>
                                        @include('modules.accounting.gst-returns.partials.gstr1-rows', ['docs' => $groups['removed'], 'heading' => null])
                                    @endif
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer d-flex flex-wrap justify-content-center align-items-center gap-3 py-3">
                        @if ($canFile)
                            <div class="form-check fs-12 mb-0">
                                <input class="form-check-input" type="checkbox" name="mark_uploaded" value="1" id="gstrMark" checked>
                                <label class="form-check-label" for="gstrMark">Mark exported vouchers as uploaded</label>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="gstrExport" @disabled($blocked)
                                data-url="{{ route('accounting.gst-returns.gstr1.export', $periodQuery) }}">
                                <span class="text-primary opacity-75 me-1">X:</span>Export (Offline)
                            </button>
                        @endif
                        <span class="d-inline-flex align-items-center gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                                <span class="opacity-75 me-1">S:</span>Send (Online)
                            </button>
                            <i class="feather-info text-primary" tabindex="0" data-bs-toggle="tooltip"
                               title="Direct upload needs a GST Suvidha Provider (GSP) connection. Until one is set up, export the JSON here and upload it at gst.gov.in → Returns → GSTR-1 → Prepare Offline → Upload."></i>
                        </span>
                    </div>
                </div>
            </form>

            {{-- Summaries sent with every upload --}}
            <div class="card stretch mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0 fw-bold">Summaries (sent in full with every upload)</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="nav nav-tabs nav-tabs-custom-style px-3 pt-2 fs-12" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#gstrB2cs" type="button">B2C (Small) - 7 <span class="text-muted">({{ count($built['b2cs']) }})</span></button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#gstrHsn" type="button">HSN/SAC Summary - 12 <span class="text-muted">({{ count($built['hsn']['b2b']) + count($built['hsn']['b2c']) }})</span></button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#gstrDocs" type="button">Documents Issued - 13 <span class="text-muted">({{ count($built['doc_issue']) }})</span></button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="gstrB2cs">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 fs-12">
                                    <thead class="table-light text-muted text-uppercase fs-11">
                                        <tr><th class="ps-3">Place of Supply</th><th>Type</th><th class="text-end">Rate</th><th class="text-end">Taxable</th><th class="text-end">IGST</th><th class="text-end">CGST</th><th class="text-end pe-3">SGST</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($built['b2cs'] as $row)
                                            <tr>
                                                <td class="ps-3">{{ $row['pos'] }}-{{ GstStates::name($row['pos']) }}</td>
                                                <td>{{ $row['sply_ty'] === 'INTER' ? 'Inter-state' : 'Intra-state' }}</td>
                                                <td class="text-end">{{ rtrim(rtrim(number_format($row['rt'], 2), '0'), '.') }}%</td>
                                                <td class="text-end">{{ $money($row['txval']) }}</td>
                                                <td class="text-end">{{ $money($row['iamt']) }}</td>
                                                <td class="text-end">{{ $money($row['camt']) }}</td>
                                                <td class="text-end pe-3">{{ $money($row['samt']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="ps-3 text-muted py-2">No B2C (Small) supplies.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="gstrHsn">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 fs-12">
                                    <thead class="table-light text-muted text-uppercase fs-11">
                                        <tr><th class="ps-3">Tab</th><th>HSN/SAC</th><th>Description</th><th>UQC</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Taxable</th><th class="text-end pe-3">Tax</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse (array_merge(array_map(fn ($r) => $r + ['tab' => 'B2B'], $built['hsn']['b2b']), array_map(fn ($r) => $r + ['tab' => 'B2C'], $built['hsn']['b2c'])) as $row)
                                            <tr>
                                                <td class="ps-3">{{ $row['tab'] }}</td>
                                                <td class="font-monospace">{{ $row['hsn_sc'] }}</td>
                                                <td>{{ $row['desc'] }}</td>
                                                <td>{{ $row['uqc'] }}</td>
                                                <td class="text-end">{{ rtrim(rtrim(number_format($row['qty'], 3), '0'), '.') }}</td>
                                                <td class="text-end">{{ rtrim(rtrim(number_format($row['rt'], 2), '0'), '.') }}%</td>
                                                <td class="text-end">{{ $money($row['txval']) }}</td>
                                                <td class="text-end pe-3">{{ $money($row['iamt'] + $row['camt'] + $row['samt']) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="8" class="ps-3 text-muted py-2">No HSN/SAC summary — set HSN/SAC on your products.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="gstrDocs">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 fs-12">
                                    <thead class="table-light text-muted text-uppercase fs-11">
                                        <tr><th class="ps-3">Nature of document</th><th>From</th><th>To</th><th class="text-end">Total</th><th class="text-end">Cancelled</th><th class="text-end pe-3">Net issued</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($built['doc_issue'] as $row)
                                            <tr>
                                                <td class="ps-3">{{ $row['doc_typ'] }}</td>
                                                <td class="font-monospace">{{ $row['from'] }}</td>
                                                <td class="font-monospace">{{ $row['to'] }}</td>
                                                <td class="text-end">{{ $row['totnum'] }}</td>
                                                <td class="text-end">{{ $row['cancel'] }}</td>
                                                <td class="text-end pe-3">{{ $row['net_issue'] }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="ps-3 text-muted py-2">No documents issued in this period.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tally-style button bar --}}
        <div class="col-12 col-xl-3">
            <div class="card stretch mb-3 gstr-keys">
                <div class="list-group list-group-flush fs-13">
                    <button type="button" class="list-group-item list-group-item-action" data-key="F2" id="gstrKeyPeriod">
                        <span class="gstr-key">F2</span>Period
                    </button>
                    <a class="list-group-item list-group-item-action {{ $includeUploaded ? 'active' : '' }}" data-key="F8" id="gstrKeyUploaded"
                       href="{{ route('accounting.gst-returns.gstr1', $periodQuery + ($includeUploaded ? [] : ['include_uploaded' => 1])) }}">
                        <span class="gstr-key">F8</span>{{ $includeUploaded ? 'Exclude Uploaded' : 'Include Uploaded' }}
                    </a>
                    @if ($canFile)
                        <button type="button" class="list-group-item list-group-item-action" data-action="mark_uploaded" data-key="U">
                            <span class="gstr-key">U</span>Mark as Uploaded
                        </button>
                        <button type="button" class="list-group-item list-group-item-action" data-action="delete_request" data-key="D">
                            <span class="gstr-key">D</span>Delete (from portal)
                        </button>
                        <button type="button" class="list-group-item list-group-item-action" data-action="reset_delete" data-key="Q">
                            <span class="gstr-key">Q</span>Reset Delete Request
                        </button>
                        <button type="button" class="list-group-item list-group-item-action" data-action="reset" data-key="R">
                            <span class="gstr-key">R</span>Reset Upload Status
                        </button>
                    @endif
                </div>
                <div class="card-footer fs-11 text-muted py-2">
                    Space selects a row. Delete and the resets apply to uploaded vouchers — turn on F8 to see them.
                </div>
            </div>

            <div class="card stretch mb-3">
                <div class="card-header py-2"><h6 class="mb-0 fw-bold fs-13">Return summary</h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 fs-12">
                        <tbody>
                            @foreach ($built['sections'] as $section => $row)
                                <tr>
                                    <td class="ps-3">{{ $row['label'] }}</td>
                                    <td class="text-end text-muted">{{ $row['count'] }}</td>
                                    <td class="text-end pe-3">{{ $money($row['taxable']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card stretch mb-3">
                <div class="card-header py-2"><h6 class="mb-0 fw-bold fs-13">Recent uploads</h6></div>
                <div class="list-group list-group-flush fs-12">
                    @forelse ($filings as $filing)
                        <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold">
                                    {{ substr($filing->return_period, 0, 2) }}/{{ substr($filing->return_period, 2) }}
                                    · {{ $filing->action === 'export_offline' ? 'Exported' : 'Marked uploaded' }}
                                </div>
                                <div class="text-muted">
                                    {{ $filing->voucher_count }} voucher(s)@if ($filing->delete_count), {{ $filing->delete_count }} deletion(s)@endif
                                    · {{ $filing->created_at->format('d M, H:i') }} · {{ $filing->creator?->name ?? '—' }}
                                </div>
                            </div>
                            @if ($filing->payload)
                                <a href="{{ route('accounting.gst-returns.filings.download', $filing) }}" class="btn btn-light border btn-sm py-0 px-2" title="Download JSON again">
                                    <i class="feather-download"></i>
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Nothing uploaded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .gstr-table .gstr-group td { background: var(--bs-light, #f5f6fa); }
    .gstr-table .gstr-section td { color: var(--bs-secondary-color, #6c757d); font-size: 12px; font-weight: 600; }
    .gstr-table tr.is-selected td { background: rgba(var(--bs-primary-rgb, 52, 84, 209), .08); }
    .gstr-table .gstr-issue { font-size: 12px; }
    .gstr-keys .gstr-key { display: inline-block; min-width: 26px; color: var(--bs-primary, #3454d1); font-weight: 600; }
    .gstr-keys .list-group-item.active .gstr-key { color: inherit; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('gstrUpload');
    const form = document.getElementById('gstrForm');
    const boxes = () => [...form.querySelectorAll('input.gstr-row')];
    const checked = () => boxes().filter((b) => b.checked);

    const month = document.getElementById('gstrMonth');
    month?.addEventListener('change', () => {
        if (!month.value) return;
        const [from, to] = month.value.split('|');
        document.getElementById('gstrFrom').value = from;
        document.getElementById('gstrTo').value = to;
        document.getElementById('gstrPeriodForm').submit();
    });

    const paint = () => boxes().forEach((b) => b.closest('tr').classList.toggle('is-selected', b.checked));
    document.getElementById('gstrAll')?.addEventListener('change', (e) => {
        boxes().forEach((b) => { b.checked = e.target.checked; });
        paint();
    });
    form.addEventListener('change', (e) => { if (e.target.matches('input.gstr-row')) paint(); });

    // Clicking a row (not a link) selects it, like Space in Tally.
    form.querySelectorAll('tr[data-key]').forEach((tr) => {
        tr.addEventListener('click', (e) => {
            if (e.target.closest('a, input, button')) return;
            const box = tr.querySelector('input.gstr-row');
            if (box) { box.checked = !box.checked; paint(); }
        });
    });

    const act = (action) => {
        if (!checked().length) {
            alertBox('Select the vouchers first (click a row or press Space).');
            return;
        }
        document.getElementById('gstrAction').value = action;
        form.submit();
    };

    root.querySelectorAll('[data-action]').forEach((el) => el.addEventListener('click', () => act(el.dataset.action)));

    const exportBtn = document.getElementById('gstrExport');
    exportBtn?.addEventListener('click', () => {
        if (exportBtn.disabled) return;
        document.getElementById('gstrScope').value = checked().length ? 'selected' : 'all';
        const action = form.action;
        form.action = exportBtn.dataset.url;
        form.submit();
        form.action = action;
        // The JSON downloads; refresh afterwards so the statuses show.
        setTimeout(() => window.location.reload(), 1500);
    });

    document.getElementById('gstrKeyPeriod')?.addEventListener('click', () => document.getElementById('gstrFrom').focus());

    function alertBox(message) {
        let box = document.getElementById('gstrNotice');
        if (!box) {
            box = document.createElement('div');
            box.id = 'gstrNotice';
            box.className = 'alert alert-info py-2 fs-13 mb-2';
            root.insertBefore(box, root.children[1]);
        }
        box.textContent = message;
    }

    // Tally keys: F2 period, F8 include uploaded, X export, U/D/Q/R actions, Space select.
    let cursor = -1;
    const rows = () => [...form.querySelectorAll('tr[data-key]')];
    document.addEventListener('keydown', (e) => {
        if (e.target.closest('input, select, textarea, [contenteditable]') || e.ctrlKey || e.metaKey || e.altKey) return;
        const key = e.key;
        if (key === 'F2') { e.preventDefault(); document.getElementById('gstrFrom').focus(); return; }
        if (key === 'F8') { e.preventDefault(); document.getElementById('gstrKeyUploaded').click(); return; }
        if (key === 'ArrowDown' || key === 'ArrowUp') {
            const list = rows();
            if (!list.length) return;
            e.preventDefault();
            cursor = Math.max(0, Math.min(list.length - 1, cursor + (key === 'ArrowDown' ? 1 : -1)));
            list.forEach((r, i) => r.classList.toggle('table-active', i === cursor));
            list[cursor].scrollIntoView({ block: 'nearest' });
            return;
        }
        if (key === ' ' && cursor >= 0) {
            e.preventDefault();
            const box = rows()[cursor]?.querySelector('input.gstr-row');
            if (box) { box.checked = !box.checked; paint(); }
            return;
        }
        const upper = key.toUpperCase();
        if (upper === 'X') { exportBtn?.click(); return; }
        const target = root.querySelector(`[data-action][data-key="${upper}"]`);
        if (target) { e.preventDefault(); target.click(); }
    });

    if (window.bootstrap?.Tooltip) {
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
    }
});
</script>
@endsection
