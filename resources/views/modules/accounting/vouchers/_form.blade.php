{{-- Shared voucher create form — included both standalone (create.blade.php,
     for direct-URL/back-compat access) and inside the drawer on index.blade.php.
     $embedded (bool) drops the page-style heading/border since the drawer
     already supplies its own header/footer chrome. --}}
@php $embedded = $embedded ?? false; @endphp

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

<form action="{{ route('accounting.vouchers.' . $type . '.store') }}" method="POST" id="voucherForm-{{ $type }}">
    @csrf

    <x-ui.odoo-form-ui type="sheet">
        @unless ($embedded)
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <h5 class="fw-bold text-dark mb-0">{{ $label }} Details</h5>
                <x-ui.button href="{{ route('accounting.vouchers.' . $type . '.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
            </div>
        @endunless

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
                <span id="balanceIndicator-{{ $type }}" class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 fw-semibold">Enter both lines</span>
            </div>
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" id="itemsTable-{{ $type }}">
                    <thead>
                        <tr>
                            <th style="width: 32%;">Account</th>
                            <th style="width: 25%;">Description</th>
                            <th class="text-end" style="width: 17%;">Debit</th>
                            <th class="text-end" style="width: 17%;">Credit</th>
                            <th class="text-center" style="width: 9%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic Rows -->
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
            <button type="button" class="btn btn-light-brand btn-sm mt-2 addLineBtn" data-voucher-type="{{ $type }}">
                <i class="feather-plus me-1"></i>Add Line
            </button>
            @if ($type === 'contra')
                <p class="fs-11 text-muted mt-2 mb-0"><i class="feather-info"></i> Every line of a Contra voucher must be a Cash or Bank account.</p>
            @endif
        </div>

        <div class="row mt-4 pt-3 border-top text-dark fs-13">
            <div class="col-md-7"></div>
            <div class="col-md-5">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted fw-semibold">Total Debit:</span>
                    <span class="fw-bold text-dark" id="calcDebit-{{ $type }}">0.00</span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted fw-semibold">Total Credit:</span>
                    <span class="fw-bold text-dark" id="calcCredit-{{ $type }}">0.00</span>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            @unless ($embedded)
                <x-ui.button href="{{ route('accounting.vouchers.' . $type . '.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
            @endunless
            <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Post {{ $label }}</x-ui.button>
        </div>
    </x-ui.odoo-form-ui>
</form>

@once
    @push('scripts')
        <script>
            function escapeHtmlVoucherLine(string) {
                return String(string).replace(/[&<>"']/g, function (s) {
                    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': '&quot;', "'": '&#39;' }[s];
                });
            }

            function initVoucherForm(type, config) {
                const $table = $('#itemsTable-' + type);
                const $indicator = $('#balanceIndicator-' + type);

                function buildAccountOptions(list) {
                    let opts = '<option value="">Select Account...</option>';
                    list.forEach(function(a) {
                        opts += `<option value="${a.id}">${escapeHtmlVoucherLine(a.code)} - ${escapeHtmlVoucherLine(a.name)}</option>`;
                    });
                    return opts;
                }

                function getRowHtml(index, values) {
                    values = values || {};
                    const isFixedRow = index < 2;
                    const list = isFixedRow ? config.rowAccountLists[index] : config.allAccountsList;
                    const rowLabel = isFixedRow ? config.rowLabels[index] : 'Account';

                    let opts = buildAccountOptions(list);
                    if (values.accountId) {
                        opts = opts.replace(`value="${values.accountId}"`, `value="${values.accountId}" selected`);
                    }

                    return `
                        <tr class="item-row">
                            <td class="ps-3">
                                <select name="" class="form-select odoo-table-select odoo-select2 account-select" required data-placeholder="${escapeHtmlVoucherLine(rowLabel)}">
                                    ${opts}
                                </select>
                            </td>
                            <td>
                                <input type="text" name="" class="odoo-table-input description-input" placeholder="Line description..." value="${escapeHtmlVoucherLine(values.description || '')}">
                            </td>
                            <td>
                                <input type="number" name="" class="odoo-table-input text-end debit-input" value="${values.debit ?? '0.00'}" min="0" step="0.01" style="width: 110px; margin-left: auto;">
                            </td>
                            <td>
                                <input type="number" name="" class="odoo-table-input text-end credit-input" value="${values.credit ?? '0.00'}" min="0" step="0.01" style="width: 110px; margin-left: auto;">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-icon btn-sm clone-row-btn" title="Clone this line" data-bs-toggle="tooltip">
                                    <i class="feather-copy fs-13"></i>
                                </button>
                                <button type="button" class="btn btn-icon btn-sm text-danger remove-row-btn" title="Remove this line" data-bs-toggle="tooltip">
                                    <i class="feather-trash-2 fs-13"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }

                function reindexRows() {
                    $table.find('tbody .item-row').each(function(index) {
                        $(this).find('.account-select').attr('name', `items[${index}][chart_of_account_id]`);
                        $(this).find('.description-input').attr('name', `items[${index}][description]`);
                        $(this).find('.debit-input').attr('name', `items[${index}][debit]`);
                        $(this).find('.credit-input').attr('name', `items[${index}][credit]`);
                    });

                    const rowCount = $table.find('tbody .item-row').length;
                    $table.find('.remove-row-btn').prop('disabled', rowCount <= 2);
                }

                function addRow(index, values) {
                    const newRow = $(getRowHtml(index, values));
                    $table.find('tbody').append(newRow);

                    if (typeof $.fn.select2 === 'function') {
                        newRow.find('.account-select').select2({ theme: "bootstrap-5", width: "100%", dropdownParent: newRow.closest('.offcanvas, body') });
                    }

                    reindexRows();

                    return newRow;
                }

                function calculateTotals() {
                    let totalDebit = 0;
                    let totalCredit = 0;

                    $table.find('.item-row').each(function() {
                        totalDebit += parseFloat($(this).find('.debit-input').val()) || 0;
                        totalCredit += parseFloat($(this).find('.credit-input').val()) || 0;
                    });

                    $('#calcDebit-' + type).text(totalDebit.toFixed(2));
                    $('#calcCredit-' + type).text(totalCredit.toFixed(2));

                    if (totalDebit === 0 && totalCredit === 0) {
                        $indicator.removeClass('bg-soft-success text-success bg-soft-danger text-danger')
                                 .addClass('bg-soft-secondary text-secondary').text('Enter both lines');
                    } else if (Math.abs(totalDebit - totalCredit) < 0.005) {
                        $indicator.removeClass('bg-soft-secondary text-secondary bg-soft-danger text-danger')
                                 .addClass('bg-soft-success text-success').text('Balanced');
                    } else {
                        const diff = Math.abs(totalDebit - totalCredit).toFixed(2);
                        $indicator.removeClass('bg-soft-secondary text-secondary bg-soft-success text-success')
                                 .addClass('bg-soft-danger text-danger').text('Out of balance by ' + diff);
                    }
                }

                $(document).on('input', '#itemsTable-' + type + ' .debit-input', function() {
                    if (parseFloat($(this).val()) > 0) {
                        $(this).closest('tr').find('.credit-input').val('0.00');
                    }
                    calculateTotals();
                });
                $(document).on('input', '#itemsTable-' + type + ' .credit-input', function() {
                    if (parseFloat($(this).val()) > 0) {
                        $(this).closest('tr').find('.debit-input').val('0.00');
                    }
                    calculateTotals();
                });

                $(document).on('click', '.addLineBtn[data-voucher-type="' + type + '"]', function() {
                    addRow($table.find('tbody .item-row').length);
                    calculateTotals();
                });

                $(document).on('click', '#itemsTable-' + type + ' .clone-row-btn', function() {
                    const $row = $(this).closest('tr');
                    const newRow = addRow($table.find('tbody .item-row').length, {
                        accountId: $row.find('.account-select').val(),
                        description: $row.find('.description-input').val(),
                        debit: $row.find('.debit-input').val(),
                        credit: $row.find('.credit-input').val(),
                    });
                    newRow.insertAfter($row);
                    reindexRows();
                    calculateTotals();
                });

                $(document).on('click', '#itemsTable-' + type + ' .remove-row-btn', function() {
                    if ($table.find('tbody .item-row').length <= 2) {
                        return;
                    }
                    $(this).closest('tr').remove();
                    reindexRows();
                    calculateTotals();
                });

                // Reset to the two fixed rows and re-init whenever the form becomes
                // visible (drawer opened) so a previous fill doesn't linger.
                $table.find('tbody').empty();
                addRow(0);
                addRow(1);
                calculateTotals();
            }

            window.voucherFormConfigs = window.voucherFormConfigs || {};
        </script>
    @endpush
@endonce

@push('scripts')
    <script>
        (function() {
            const mapAccounts = (list) => list.map((a) => ({ id: a.id, code: a.code, name: a.name }));
            const allAccountsList = mapAccounts(@json($accounts));
            const cashBankAccountsList = mapAccounts(@json($cashBankAccounts));

            const rowAccountListsByType = {
                contra: [cashBankAccountsList, cashBankAccountsList],
                payment: [cashBankAccountsList, allAccountsList],
                receipt: [cashBankAccountsList, allAccountsList],
                credit_note: [allAccountsList, allAccountsList],
                debit_note: [allAccountsList, allAccountsList],
            };
            const rowLabelsByType = {
                contra: ['Cash / Bank Account', 'Cash / Bank Account'],
                payment: ['Paid From (Cash / Bank)', 'Paid To (Expense / Payable)'],
                receipt: ['Received Into (Cash / Bank)', 'Received From'],
                credit_note: ['Account', 'Account'],
                debit_note: ['Account', 'Account'],
            };

            const type = @json($type);
            window.voucherFormConfigs = window.voucherFormConfigs || {};
            window.voucherFormConfigs[type] = {
                allAccountsList: allAccountsList,
                rowAccountLists: rowAccountListsByType[type] || [allAccountsList, allAccountsList],
                rowLabels: rowLabelsByType[type] || ['Account', 'Account'],
            };

            $(function() {
                initVoucherForm(type, window.voucherFormConfigs[type]);
            });
        })();
    </script>
@endpush
