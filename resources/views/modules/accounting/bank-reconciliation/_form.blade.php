{{-- Shared bank reconciliation start form — included standalone (create.blade.php,
     for direct-URL/back-compat access) and inside the drawer on index.blade.php. --}}
@php $embedded = $embedded ?? false; @endphp

@if ($errors->any())
    <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
        <ul class="fs-12 mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif

<form action="{{ route('accounting.bank-reconciliation.store') }}" method="POST">
    @csrf

    <x-ui.odoo-form-ui type="sheet">
        @unless ($embedded)
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <h5 class="fw-bold text-dark mb-0">Start Reconciliation</h5>
                <x-ui.button href="{{ route('accounting.bank-reconciliation.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
            </div>
        @endunless

        @if ($embedded)
            <div class="row g-3 fs-13 text-dark">
                <div class="col-12">
                    <label class="form-label fw-semibold fs-13 text-dark mb-2">Cash / Bank Account <span class="text-danger">*</span></label>
                    <x-ui.select name="chart_of_account_id" :required="true" :selected="old('chart_of_account_id')" :options="['' => 'Select Account...'] + $cashBankAccounts->mapWithKeys(fn ($a) => [$a->id => $a->code . ' - ' . $a->name])->all()" />
                </div>
                <div class="col-md-4">
                    <x-ui.icon-input label="Statement Date" icon="feather-calendar" type="date" name="statement_date" :value="old('statement_date', date('Y-m-d'))" :required="true" />
                </div>
                <div class="col-md-4">
                    <x-ui.icon-input label="Opening Balance" icon="feather-dollar-sign" type="number" name="opening_balance" :value="old('opening_balance', '0.00')" :required="true" />
                </div>
                <div class="col-md-4">
                    <x-ui.icon-input label="Closing Balance" icon="feather-dollar-sign" type="number" name="closing_balance" :value="old('closing_balance', '0.00')" :required="true" />
                </div>
            </div>
        @else
            <x-ui.odoo-form-ui type="select" label="Cash / Bank Account" name="chart_of_account_id" :required="true">
                <option value="">Select Account...</option>
                @foreach ($cashBankAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('chart_of_account_id') == $account->id)>
                        {{ $account->code }} - {{ $account->name }}
                    </option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="input" inputType="date" label="Statement Date" name="statement_date" :value="old('statement_date', date('Y-m-d'))" :required="true" />
            <x-ui.odoo-form-ui type="input" inputType="number" label="Opening Balance" name="opening_balance" :value="old('opening_balance', '0.00')" :required="true" />
            <x-ui.odoo-form-ui type="input" inputType="number" label="Closing Balance" name="closing_balance" :value="old('closing_balance', '0.00')" :required="true" />
        @endif

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            @unless ($embedded)
                <x-ui.button href="{{ route('accounting.bank-reconciliation.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
            @endunless
            <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Start Reconciliation</x-ui.button>
        </div>
    </x-ui.odoo-form-ui>
</form>
