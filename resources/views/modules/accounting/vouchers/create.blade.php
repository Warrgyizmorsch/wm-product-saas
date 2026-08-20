@extends('layouts.duralux')

@section('title', 'New ' . $label . ' | SaaS ERP')
@section('page-title', 'New ' . $label)
@section('breadcrumb', 'Accounting / ' . $label . 's / Create')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
                <h6 class="alert-heading fw-bold mb-1">Cannot post this {{ strtolower($label) }}</h6>
                <ul class="fs-12 mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form action="{{ route('accounting.vouchers.' . $type . '.store') }}" method="POST" id="voucherForm">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-0">{{ $label }} Details</h5>
                    <x-ui.button href="{{ route('accounting.vouchers.' . $type . '.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" inputType="date" label="{{ $label }} Date" name="voucher_date" :value="old('voucher_date', date('Y-m-d'))" :required="true" />
                    </div>
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" label="Party Name" name="party_name" :value="old('party_name')" placeholder="Who is this to/from?" />
                    </div>
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" label="Reference No." name="reference_no" :value="old('reference_no')" placeholder="Cheque / UTR / UPI ref" />
                    </div>
                </div>
                <div class="row g-4 fs-13 text-dark mt-1">
                    <div class="col-md-4">
                        <x-ui.select label="Payment Method" name="payment_method" :selected="old('payment_method')" :options="[
                            '' => '— Select —', 'cash' => 'Cash', 'bank_transfer' => 'Bank Transfer',
                            'cheque' => 'Cheque', 'upi' => 'UPI', 'card' => 'Card', 'other' => 'Other',
                        ]" />
                    </div>
                    <div class="col-md-8">
                        <x-ui.odoo-form-ui type="input" label="Memo" name="memo" :value="old('memo')" placeholder="Short description of this entry" />
                    </div>
                </div>

                <div class="border-top pt-4 mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0 fs-14">{{ $label }} Lines</h5>
                        <span id="balanceIndicator" class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 fw-semibold">Enter both lines</span>
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Account</th>
                                    <th style="width: 27%;">Description</th>
                                    <th class="text-end" style="width: 19%;">Debit</th>
                                    <th class="text-end" style="width: 19%;">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dynamic Rows -->
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                    @if ($type === 'contra')
                        <p class="fs-11 text-muted mt-2 mb-0"><i class="feather-info"></i> Both lines of a Contra voucher must be Cash or Bank accounts.</p>
                    @endif
                </div>

                <div class="row mt-4 pt-3 border-top text-dark fs-13">
                    <div class="col-md-7"></div>
                    <div class="col-md-5">
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted fw-semibold">Total Debit:</span>
                            <span class="fw-bold text-dark" id="calcDebit">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted fw-semibold">Total Credit:</span>
                            <span class="fw-bold text-dark" id="calcCredit">0.00</span>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('accounting.vouchers.' . $type . '.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Post {{ $label }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            const voucherType = @json($type);

            @php
                $mapAccounts = fn ($list) => $list->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name]);
            @endphp
            const allAccountsList = @json($mapAccounts($accounts));
            const cashBankAccountsList = @json($mapAccounts($cashBankAccounts));

            // Which account list each of the 2 static rows should default to, per voucher type.
            // Row 0 / Row 1 order mirrors the Busy reference: Contra restricts both sides to
            // Cash/Bank; Payment's paying side (row 0) and Receipt's receiving side (row 0)
            // default to Cash/Bank; Credit/Debit Notes are unrestricted on both sides.
            const rowAccountLists = {
                contra: [cashBankAccountsList, cashBankAccountsList],
                payment: [cashBankAccountsList, allAccountsList],
                receipt: [cashBankAccountsList, allAccountsList],
                credit_note: [allAccountsList, allAccountsList],
                debit_note: [allAccountsList, allAccountsList],
            };
            const rowLabels = {
                contra: ['Cash / Bank Account', 'Cash / Bank Account'],
                payment: ['Paid From (Cash / Bank)', 'Paid To (Expense / Payable)'],
                receipt: ['Received Into (Cash / Bank)', 'Received From'],
                credit_note: ['Account', 'Account'],
                debit_note: ['Account', 'Account'],
            };

            function escapeHtml(string) {
                return String(string).replace(/[&<>"']/g, function (s) {
                    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': '&quot;', "'": '&#39;' }[s];
                });
            }

            function buildAccountOptions(list) {
                let opts = '<option value="">Select Account...</option>';
                list.forEach(function(a) {
                    opts += `<option value="${a.id}">${escapeHtml(a.code)} - ${escapeHtml(a.name)}</option>`;
                });
                return opts;
            }

            function getRowHtml(index) {
                const list = (rowAccountLists[voucherType] || [allAccountsList, allAccountsList])[index];
                const rowLabel = (rowLabels[voucherType] || ['Account', 'Account'])[index];

                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-3">
                            <select name="items[${index}][chart_of_account_id]" class="form-select odoo-table-select odoo-select2 account-select" required data-placeholder="${escapeHtml(rowLabel)}">
                                ${buildAccountOptions(list)}
                            </select>
                        </td>
                        <td>
                            <input type="text" name="items[${index}][description]" class="odoo-table-input" placeholder="Line description...">
                        </td>
                        <td>
                            <input type="number" name="items[${index}][debit]" class="odoo-table-input text-end debit-input" value="0.00" min="0" step="0.01" style="width: 110px; margin-left: auto;">
                        </td>
                        <td>
                            <input type="number" name="items[${index}][credit]" class="odoo-table-input text-end credit-input" value="0.00" min="0" step="0.01" style="width: 110px; margin-left: auto;">
                        </td>
                    </tr>
                `;
            }

            // Mutual exclusivity: entering a debit clears that row's credit, and vice versa.
            $(document).on('input', '.debit-input', function() {
                if (parseFloat($(this).val()) > 0) {
                    $(this).closest('tr').find('.credit-input').val('0.00');
                }
                calculateTotals();
            });
            $(document).on('input', '.credit-input', function() {
                if (parseFloat($(this).val()) > 0) {
                    $(this).closest('tr').find('.debit-input').val('0.00');
                }
                calculateTotals();
            });

            function addRow(index) {
                const newRow = $(getRowHtml(index));
                $('#itemsTable tbody').append(newRow);

                if (typeof $.fn.select2 === 'function') {
                    newRow.find('.account-select').select2({ theme: "bootstrap-5", width: "100%" });
                }
            }

            function calculateTotals() {
                let totalDebit = 0;
                let totalCredit = 0;

                $('.item-row').each(function() {
                    totalDebit += parseFloat($(this).find('.debit-input').val()) || 0;
                    totalCredit += parseFloat($(this).find('.credit-input').val()) || 0;
                });

                $('#calcDebit').text(totalDebit.toFixed(2));
                $('#calcCredit').text(totalCredit.toFixed(2));

                const indicator = $('#balanceIndicator');
                if (totalDebit === 0 && totalCredit === 0) {
                    indicator.removeClass('bg-soft-success text-success bg-soft-danger text-danger')
                             .addClass('bg-soft-secondary text-secondary').text('Enter both lines');
                } else if (Math.abs(totalDebit - totalCredit) < 0.005) {
                    indicator.removeClass('bg-soft-secondary text-secondary bg-soft-danger text-danger')
                             .addClass('bg-soft-success text-success').text('Balanced');
                } else {
                    const diff = Math.abs(totalDebit - totalCredit).toFixed(2);
                    indicator.removeClass('bg-soft-secondary text-secondary bg-soft-success text-success')
                             .addClass('bg-soft-danger text-danger').text('Out of balance by ' + diff);
                }
            }

            // Vouchers are always exactly 2 lines in this phase — no add/remove-row controls.
            addRow(0);
            addRow(1);
            calculateTotals();
        });
    </script>
@endpush
