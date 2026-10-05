{{-- Shared bank reconciliation start form — included standalone (create.blade.php,
     for direct-URL/back-compat access) and inside the drawer on index.blade.php. --}}
@php
    $embedded = $embedded ?? false;
    $lastReconciled = $lastReconciled ?? [];
@endphp

@if ($errors->any())
    <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
        <ul class="fs-12 mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif

<form action="{{ route('accounting.bank-reconciliation.store') }}" method="POST" id="brStartForm">
    @csrf

    <x-ui.odoo-form-ui type="sheet">
        @unless ($embedded)
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <h5 class="fw-bold text-dark mb-0">Start Reconciliation</h5>
                <x-ui.button href="{{ route('accounting.bank-reconciliation.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
            </div>
        @endunless

        <div class="row g-3 fs-13 text-dark">
            <div class="col-12">
                <label class="form-label fw-semibold fs-13 text-dark mb-2">Bank / Cash Account <span class="text-danger">*</span></label>
                <select name="chart_of_account_id" class="form-select" required id="brStartAccount">
                    <option value="">Select account…</option>
                    @foreach ($cashBankAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('chart_of_account_id') == $account->id)
                            @if (isset($lastReconciled[$account->id]))
                                data-last-date="{{ $lastReconciled[$account->id]['date'] }}"
                                data-next="{{ $lastReconciled[$account->id]['next'] }}"
                                data-closing="{{ $lastReconciled[$account->id]['closing'] }}"
                            @endif>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                <div class="fs-11 text-muted mt-1" id="brStartHint">Pick the bank account whose statement you are reconciling.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold fs-13 text-dark mb-2">Statement period from</label>
                <input type="date" name="statement_from_date" id="brStartFrom" class="form-control" value="{{ old('statement_from_date') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold fs-13 text-dark mb-2">Statement date (to) <span class="text-danger">*</span></label>
                <input type="date" name="statement_date" class="form-control" required value="{{ old('statement_date', now()->endOfMonth()->min(now())->toDateString()) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold fs-13 text-dark mb-2">Opening balance as per bank</label>
                <input type="number" step="0.01" name="opening_balance" id="brStartOpening" class="form-control" value="{{ old('opening_balance') }}" placeholder="0.00">
                <div class="fs-11 text-muted mt-1">Carried forward from the last reconciliation when left blank.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold fs-13 text-dark mb-2">Closing balance as per bank <span class="text-danger">*</span></label>
                <input type="number" step="0.01" name="closing_balance" class="form-control" required value="{{ old('closing_balance') }}" placeholder="0.00">
                <div class="fs-11 text-muted mt-1">As printed on the bank statement. Overdrawn = negative.</div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold fs-13 text-dark mb-2">Notes</label>
                <textarea name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            @unless ($embedded)
                <x-ui.button href="{{ route('accounting.bank-reconciliation.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
            @endunless
            <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Start Reconciliation</x-ui.button>
        </div>
    </x-ui.odoo-form-ui>
</form>

@push('scripts')
<script>
(function () {
    const select = document.getElementById('brStartAccount');
    if (!select) return;
    const sync = () => {
        const option = select.selectedOptions[0];
        const hint = document.getElementById('brStartHint');
        if (option && option.dataset.lastDate) {
            hint.textContent = 'Last reconciled up to ' + option.dataset.lastDate + ' with a bank closing balance of ' + Number(option.dataset.closing).toFixed(2) + '.';
            const from = document.getElementById('brStartFrom');
            const opening = document.getElementById('brStartOpening');
            if (!from.value) from.value = option.dataset.next;
            if (!opening.value) opening.value = Number(option.dataset.closing).toFixed(2);
        } else {
            hint.textContent = option && option.value ? 'First reconciliation for this account — enter the opening balance from the bank statement.' : 'Pick the bank account whose statement you are reconciling.';
        }
    };
    select.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
