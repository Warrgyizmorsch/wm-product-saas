@php
    // $partyType: 'customer' | 'vendor'; $party: the model being viewed
    $historyService = app(\App\Domains\Party\Services\PartyHistoryService::class);
    $allHistory = $historyService->entries($partyType, (int) $party->id, auth()->user());
    $typeOptions = $allHistory->pluck('type_label', 'type')->unique();
    $filterType = request('history_type');
    $filtered = $filterType ? $allHistory->where('type', $filterType)->values() : $allHistory;
    $historyPage = $historyService->paginate(
        $filtered,
        max(1, (int) request('history_page', 1)),
        15,
        array_filter(['history_type' => $filterType])
    );
@endphp

<div id="history-pane-body">
    <form method="GET" class="d-flex align-items-center gap-2 mb-3">
        <label class="fs-12 fw-bold text-muted mb-0" for="history_type">Show</label>
        <select name="history_type" id="history_type" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <option value="">All activity ({{ $allHistory->count() }})</option>
            @foreach($typeOptions as $key => $label)
                <option value="{{ $key }}" @selected($filterType === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 fs-13">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($historyPage as $h)
                    <tr>
                        <td class="text-nowrap">{{ optional($h['date'])->format('d M Y') ?? '—' }}</td>
                        <td>{{ $h['type_label'] }}</td>
                        <td>
                            <a href="{{ $h['url'] }}" class="fw-semibold">{{ $h['number'] ?: ($h['title'] ?: '#') }}</a>
                            @if($h['number'] && $h['title'])
                                <div class="text-muted fs-11">{{ \Illuminate\Support\Str::limit($h['title'], 60) }}</div>
                            @endif
                        </td>
                        <td class="text-end font-monospace">{{ $h['amount'] !== null && $h['amount'] !== '' ? number_format((float) $h['amount'], 2) : '—' }}</td>
                        <td><span class="badge bg-soft-secondary text-secondary">{{ $h['status'] ?: '—' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No history yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($historyPage->hasPages())
        <div class="mt-3">{{ $historyPage->links() }}</div>
    @endif
</div>

@if(request()->hasAny(['history_page', 'history_type']))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var t = document.querySelector('[data-history-tab]');
            if (t && window.bootstrap) { bootstrap.Tab.getOrCreateInstance(t).show(); }
        });
    </script>
@endif
