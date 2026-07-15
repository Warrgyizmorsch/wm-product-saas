@extends('layouts.duralux')

@section('title', 'New Journal | SaaS ERP')
@section('page-title', 'New Journal')
@section('breadcrumb', 'Accounting / Journals / Create')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
                <h6 class="alert-heading fw-bold mb-1">Cannot post this journal</h6>
                <ul class="fs-12 mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form action="{{ route('accounting.journals.store') }}" method="POST" id="journalForm">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-0">Journal Details</h5>
                    <x-ui.button href="{{ route('accounting.journals.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Journal Date" name="journal_date" :value="old('journal_date', date('Y-m-d'))" :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Memo" name="memo" :value="old('memo')" placeholder="Short description of this entry" />
                    </div>
                </div>

                <div class="border-top pt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0 fs-14">Journal Lines</h5>
                        <span id="balanceIndicator" class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 fw-semibold">Add at least 2 lines</span>
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Account</th>
                                    <th style="width: 25%;">Description</th>
                                    <th class="text-end" style="width: 17%;">Debit</th>
                                    <th class="text-end" style="width: 17%;">Credit</th>
                                    <th class="text-center" style="width: 6%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dynamic Rows -->
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                            <i class="feather-plus me-1"></i>Add a line
                        </button>
                    </div>
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
                    <x-ui.button href="{{ route('accounting.journals.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Post Journal</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            let rowIndex = 0;

            @php
                $mappedAccounts = $accounts->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name]);
            @endphp
            const accountsList = @json($mappedAccounts);

            function escapeHtml(string) {
                return String(string).replace(/[&<>"']/g, function (s) {
                    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': '&quot;', "'": '&#39;' }[s];
                });
            }

            function buildAccountOptions(selectedId = '') {
                let opts = '<option value="">Select Account...</option>';
                accountsList.forEach(function(a) {
                    const sel = (a.id == selectedId) ? ' selected' : '';
                    opts += `<option value="${a.id}"${sel}>${escapeHtml(a.code)} - ${escapeHtml(a.name)}</option>`;
                });
                return opts;
            }

            function getRowHtml(index) {
                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-3">
                            <select name="items[${index}][chart_of_account_id]" class="form-select odoo-table-select odoo-select2 account-select" required>
                                ${buildAccountOptions()}
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
                        <td class="text-center">
                            <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn mt-1">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }

            $('#addItemRow').on('click', function() {
                addRow();
            });

            $(document).on('click', '.remove-row-btn', function() {
                const rowsCount = $('.item-row').length;
                if (rowsCount > 2) {
                    $(this).closest('tr').remove();
                    calculateTotals();
                } else {
                    alert('A journal requires at least two lines.');
                }
            });

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

            function addRow() {
                const newRow = $(getRowHtml(rowIndex));
                $('#itemsTable tbody').append(newRow);

                if (typeof $.fn.select2 === 'function') {
                    newRow.find('.account-select').select2({ theme: "bootstrap-5", width: "100%" });
                }

                rowIndex++;
                calculateTotals();
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
                             .addClass('bg-soft-secondary text-secondary').text('Add at least 2 lines');
                } else if (Math.abs(totalDebit - totalCredit) < 0.005) {
                    indicator.removeClass('bg-soft-secondary text-secondary bg-soft-danger text-danger')
                             .addClass('bg-soft-success text-success').text('Balanced');
                } else {
                    const diff = Math.abs(totalDebit - totalCredit).toFixed(2);
                    indicator.removeClass('bg-soft-secondary text-secondary bg-soft-success text-success')
                             .addClass('bg-soft-danger text-danger').text('Out of balance by ' + diff);
                }
            }

            // Journals need at least 2 lines to be postable — start with 2 blank rows.
            addRow();
            addRow();
        });
    </script>
@endpush
