@php
    $matchLabels = ['contains' => 'contains', 'starts_with' => 'starts with', 'equals' => 'is exactly', 'regex' => 'matches regex'];
    $directionLabels = ['any' => 'In or out', 'in' => 'Money in', 'out' => 'Money out'];
    $accountOptions = $postableAccounts->mapWithKeys(fn ($a) => [$a->id => $a->code . ' — ' . $a->name])->all();
@endphp

@extends('layouts.duralux')

@section('title', 'Bank Rules | SaaS ERP')
@section('page-title', 'Bank Rules')
@section('breadcrumb', 'Accounting / Bank Reconciliation / Rules')

@section('page-actions')
    <div class="d-flex gap-2">
        <x-ui.button href="{{ route('accounting.bank-reconciliation.index') }}" variant="light" size="sm" class="border">Bank Reconciliation</x-ui.button>
        @if ($canManage)
            <x-ui.button type="button" variant="primary" size="sm" icon="feather-plus" class="br-rule-open" data-rule="{}">New Rule</x-ui.button>
        @endif
    </div>
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-3">{{ session('success') }}</x-ui.alert>
    @endif
    @if ($errors->any())
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-3">
            <ul class="mb-0 ps-3 fs-12">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <x-ui.card class="mb-3">
        <div class="fs-12 text-muted">
            Rules tell bank reconciliation which ledger (and party) a bank line belongs to, from its narration. They are suggested on every unmatched line and pre-filled in <strong>Post entry…</strong> and <strong>Post selected…</strong>.
            Rules marked <strong>Auto-post</strong> are posted and matched by <strong>Auto-Match</strong> without asking — use that for routine items such as bank charges and interest.
            <strong>Learned</strong> rules are created automatically from entries you post; edit one to make it your own.
        </div>
    </x-ui.card>

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <form method="GET" class="d-flex flex-wrap gap-2 p-3 border-bottom">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Search name, pattern, party…">
            <select name="source" class="form-select form-select-sm" style="max-width: 160px;">
                <option value="">All sources</option>
                <option value="manual" @selected(($filters['source'] ?? '') === 'manual')>Manual</option>
                <option value="learned" @selected(($filters['source'] ?? '') === 'learned')>Learned</option>
            </select>
            <select name="status" class="form-select form-select-sm" style="max-width: 160px;">
                <option value="">Active and paused</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Paused</option>
            </select>
            <x-ui.button type="submit" variant="light" size="sm" class="border">Filter</x-ui.button>
        </form>

        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-3">Rule</th>
                        <th>When narration…</th>
                        <th>Direction / amount</th>
                        <th>Post to</th>
                        <th>Bank</th>
                        <th class="text-end">Used</th>
                        <th>Status</th>
                        <th class="pe-3"></th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @forelse ($rules as $rule)
                        <tr class="{{ $rule->is_active ? '' : 'text-muted' }}">
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $rule->name }}</div>
                                <div class="fs-11 text-muted">Priority {{ $rule->priority }} · {{ $rule->source === 'learned' ? 'Learned' : 'Manual' }}</div>
                            </td>
                            <td>{{ $matchLabels[$rule->match_type] ?? $rule->match_type }} <code>{{ $rule->pattern }}</code></td>
                            <td class="fs-12">
                                {{ $directionLabels[$rule->direction] ?? $rule->direction }}
                                @if ($rule->min_amount !== null || $rule->max_amount !== null)
                                    <div class="text-muted">{{ $rule->min_amount !== null ? number_format($rule->min_amount, 2) : '0' }} – {{ $rule->max_amount !== null ? number_format($rule->max_amount, 2) : 'any' }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $rule->targetAccount?->code }} — {{ $rule->targetAccount?->name }}
                                @if ($rule->party_name)<div class="fs-11 text-muted">Party: {{ $rule->party_name }}</div>@endif
                            </td>
                            <td class="fs-12">{{ $rule->bankAccount?->name ?? 'All banks' }}</td>
                            <td class="text-end fs-12">{{ $rule->hits }}<div class="text-muted fs-11">{{ $rule->last_used_at?->format('d M Y') }}</div></td>
                            <td>
                                @if (! $rule->is_active)
                                    <x-ui.badge variant="secondary" soft>Paused</x-ui.badge>
                                @elseif ($rule->auto_post)
                                    <x-ui.badge variant="success" soft>Auto-post</x-ui.badge>
                                @else
                                    <x-ui.badge variant="primary" soft>Suggest</x-ui.badge>
                                @endif
                            </td>
                            <td class="pe-3 text-end text-nowrap">
                                @if ($canManage)
                                    <button type="button" class="btn btn-sm btn-link p-0 me-2 br-rule-open" data-rule="{{ json_encode($rule->only(['id', 'name', 'match_type', 'pattern', 'direction', 'min_amount', 'max_amount', 'bank_account_id', 'target_account_id', 'party_name', 'narration', 'priority', 'auto_post', 'is_active'])) }}">Edit</button>
                                    <form action="{{ route('accounting.bank-rules.toggle', $rule) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-link p-0 me-2">{{ $rule->is_active ? 'Pause' : 'Activate' }}</button>
                                    </form>
                                    <form action="{{ route('accounting.bank-rules.destroy', $rule) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this rule?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5">No rules yet. Create one, or post a few bank lines while reconciling and rules will be learned from them.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-ui.pagination :currentPage="$rules->currentPage()" :totalPages="$rules->lastPage()" :totalResults="$rules->total()" :perPage="$rules->perPage()" />
    </x-ui.card>

    @if ($canManage)
        <div class="modal fade" id="brRuleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" id="brRuleForm" data-store="{{ route('accounting.bank-rules.store') }}" data-update="{{ route('accounting.bank-rules.update', ['rule' => '__ID__']) }}">
                    @csrf
                    <input type="hidden" name="_method" id="brRuleMethod" value="POST">
                    <div class="modal-header"><h5 class="modal-title" id="brRuleTitle">New rule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body row g-3 fs-13">
                        <div class="col-md-6"><label class="form-label fw-semibold">Rule name *</label><input name="name" class="form-control" required maxlength="255" placeholder="e.g. Bank charges"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Priority</label><input name="priority" type="number" min="1" max="999" class="form-control" value="100"><div class="fs-11 text-muted">Lower runs first.</div></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Bank account</label>
                            <select name="bank_account_id" class="form-select"><option value="">All banks</option>@foreach ($bankAccounts as $bank)<option value="{{ $bank->id }}">{{ $bank->name }}</option>@endforeach</select>
                        </div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Narration</label>
                            <select name="match_type" class="form-select">@foreach ($matchLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        </div>
                        <div class="col-md-9"><label class="form-label fw-semibold">Text *</label><input name="pattern" class="form-control" required maxlength="255" placeholder="e.g. SMS CHARGES"><div class="fs-11 text-muted">Case and punctuation are ignored for contains / starts with / is exactly.</div></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Direction</label>
                            <select name="direction" class="form-select">@foreach ($directionLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        </div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Min amount</label><input name="min_amount" type="number" step="0.01" min="0" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Max amount</label><input name="max_amount" type="number" step="0.01" min="0" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Post to ledger *</label>
                            <select name="target_account_id" class="form-select" required><option value="">Select account…</option>@foreach ($accountOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Party name</label><input name="party_name" class="form-control" maxlength="255"></div>
                        <div class="col-12"><label class="form-label fw-semibold">Voucher narration</label><input name="narration" class="form-control" maxlength="255" placeholder="Leave blank to use the bank's narration"></div>
                        <div class="col-md-6 form-check form-switch ms-2"><input type="hidden" name="auto_post" value="0"><input class="form-check-input" type="checkbox" name="auto_post" value="1" id="brRuleAuto"><label class="form-check-label" for="brRuleAuto">Auto-post during Auto-Match</label></div>
                        <div class="col-md-5 form-check form-switch"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="brRuleActive" checked><label class="form-check-label" for="brRuleActive">Active</label></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save rule</button></div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('styles')
    <style>.accounting-dense table th, .accounting-dense table td { padding: 6px 10px !important; font-size: 12px !important; }</style>
@endpush

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('brRuleModal');
    if (!modal) return;
    document.body.appendChild(modal);
    const form = document.getElementById('brRuleForm');
    const set = (name, value) => {
        const inputs = form.querySelectorAll(`[name="${name}"]`);
        const el = inputs[inputs.length - 1];
        if (!el) return;
        if (el.type === 'checkbox') el.checked = !!value; else el.value = value ?? '';
    };
    document.querySelectorAll('.br-rule-open').forEach((btn) => btn.addEventListener('click', () => {
        const rule = JSON.parse(btn.dataset.rule || '{}');
        const editing = !!rule.id;
        form.reset();
        form.action = editing ? form.dataset.update.replace('__ID__', rule.id) : form.dataset.store;
        document.getElementById('brRuleMethod').value = editing ? 'PUT' : 'POST';
        document.getElementById('brRuleTitle').textContent = editing ? 'Edit rule' : 'New rule';
        ['name', 'match_type', 'pattern', 'direction', 'min_amount', 'max_amount', 'bank_account_id', 'target_account_id', 'party_name', 'narration', 'priority'].forEach((f) => { if (editing || rule[f] !== undefined) set(f, rule[f]); });
        set('auto_post', editing ? rule.auto_post : false);
        set('is_active', editing ? rule.is_active : true);
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }));
})();
</script>
@endpush
