@extends('layouts.duralux')

@section('title', 'New Bank Reconciliation | SaaS ERP')
@section('page-title', 'New Bank Reconciliation')
@section('breadcrumb', 'Accounting / Bank Reconciliation / New')

@section('content')
    <div class="erp-single-panel bg-white">
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
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-0">Start Reconciliation</h5>
                    <x-ui.button href="{{ route('accounting.bank-reconciliation.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                </div>

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

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('accounting.bank-reconciliation.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Start Reconciliation</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
