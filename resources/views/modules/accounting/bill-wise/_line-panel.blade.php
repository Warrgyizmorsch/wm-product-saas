{{--
    Bill-wise box for voucher / journal lines (Tally's bill allocation).
    A line on a bill-wise ledger shows "Pick party & bills"; clicking it opens
    this panel under the lines table, where the party and the bills it
    settles (Agst Ref) or opens (New Ref) are chosen. Whatever is left of the
    line goes On Account. Values are kept in hidden inputs on the line:
    items[i][party] = "type:id", items[i][bill_allocations] = JSON.

    Include once per form: formKey (unique on the page), accounts (ledgers listed on the form).
--}}
<div class="bw-panel border rounded-3 p-3 mt-3 d-none" id="bwPanel-{{ $formKey }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold fs-13 mb-0">Bill-wise details — <span class="bw-panel-line text-muted fw-semibold"></span></h6>
        <button type="button" class="btn-close bw-close" aria-label="Close without saving"></button>
    </div>
    <div class="row g-3 align-items-end mb-2">
        <div class="col-md-6">
            <label class="form-label fs-12 fw-semibold mb-1">Party</label>
            <select class="form-select bw-party-select"></select>
        </div>
        <div class="col-md-6 fs-12 text-muted bw-party-info"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm fs-12 mb-2 bw-table align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 17%;">Type</th>
                    <th>Bill / Reference</th>
                    <th style="width: 17%;">Due Date</th>
                    <th class="text-end" style="width: 18%;">Amount</th>
                    <th style="width: 4%;"></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light btn-sm border bw-add"><i class="feather-plus me-1"></i>Add row</button>
            <button type="button" class="btn btn-light btn-sm border bw-auto">Settle oldest bills first</button>
        </div>
        <div class="fs-12">
            Line <b class="bw-line-amount">0.00</b>
            · Allocated <b class="bw-allocated">0.00</b>
            · On Account <b class="bw-remaining">0.00</b>
        </div>
        <button type="button" class="btn btn-primary btn-sm bw-done">Done</button>
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.BillWiseBox = (function () {
                const cfg = { billWise: new Set(), partiesUrl: @json(route('accounting.bill-wise.parties')), openBillsUrl: @json(route('accounting.bill-wise.open-bills')) };
                const TYPES = { against_ref: 'Agst Ref', new_ref: 'New Ref', advance: 'Advance', on_account: 'On Account' };
                const round2 = (n) => Math.round(n * 100) / 100;
                const fmt = (n) => round2(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                function configure(ids) {
                    ids.forEach((id) => cfg.billWise.add(String(id)));
                }

                function rowAmount($row) {
                    const net = (parseFloat($row.find('.debit-input').val()) || 0) - (parseFloat($row.find('.credit-input').val()) || 0);
                    return { amount: round2(Math.abs(net)), side: net >= 0 ? 'debit' : 'credit' };
                }

                function isBillWise($row) {
                    return cfg.billWise.has(String($row.find('.account-select').val() || ''));
                }

                function parseAllocs($row) {
                    try { return JSON.parse($row.find('.bw-alloc-input').val() || '[]') || []; } catch (e) { return []; }
                }

                /** Markup to put in a line's account cell. */
                function rowFields() {
                    return `<div class="bw-summary fs-11 mt-1 d-none"><a href="#" class="bw-open"><i class="feather-file-text me-1"></i><span class="bw-summary-text">Pick party &amp; bills</span></a></div>`
                        + `<input type="hidden" class="bw-party-input" value=""><input type="hidden" class="bw-alloc-input" value="">`;
                }

                function rename($row, index) {
                    $row.find('.bw-party-input').attr('name', `items[${index}][party]`);
                    $row.find('.bw-alloc-input').attr('name', `items[${index}][bill_allocations]`);
                }

                function updateSummary($row) {
                    const party = $row.data('bwPartyText') || '';
                    let text = 'Pick party & bills';
                    if (party) {
                        const parts = parseAllocs($row).map((a) => `${TYPES[a.ref_type] || a.ref_type}${a.ref_label ? ' ' + a.ref_label : ''} ${fmt(a.amount)}`);
                        text = party + ' · ' + (parts.length ? parts.join(', ') : 'On Account');
                    }
                    $row.find('.bw-summary-text').text(text);
                }

                /** Show / clear the box link after the line's ledger changed. */
                function refresh($row) {
                    const on = isBillWise($row);
                    $row.find('.bw-summary').toggleClass('d-none', !on);
                    if (!on) {
                        $row.find('.bw-party-input, .bw-alloc-input').val('');
                        $row.data('bwPartyText', '');
                    }
                    updateSummary($row);
                }

                function attach(formKey, $table) {
                    const $panel = $('#bwPanel-' + formKey);
                    const $party = $panel.find('.bw-party-select');
                    const $body = $panel.find('.bw-table tbody');
                    let $current = null;
                    let bills = [];

                    if (typeof $.fn.select2 === 'function') {
                        $party.select2({
                            theme: 'bootstrap-5', width: '100%', allowClear: true,
                            placeholder: 'Search customer, supplier, employee, transporter…',
                            dropdownParent: $panel.closest('.offcanvas, body'),
                            ajax: { url: cfg.partiesUrl, dataType: 'json', delay: 250, data: (p) => ({ q: p.term || '' }), processResults: (d) => d },
                        });
                    }

                    // Bills this line can settle: a debit line settles what we owe (Cr bills), a credit line what they owe (Dr bills).
                    function settleable() {
                        const side = $current ? rowAmount($current).side : 'debit';
                        return bills.filter((b) => side === 'debit' ? b.outstanding < 0 : b.outstanding > 0);
                    }

                    function billOptions(selected) {
                        let html = '<option value="">Select bill…</option>';
                        settleable().forEach((b) => {
                            html += `<option value="${b.id}" ${String(b.id) === String(selected ?? '') ? 'selected' : ''} data-open="${Math.abs(b.outstanding)}" data-label="${esc(b.ref_number)}">`
                                + `${esc(b.ref_number)}${b.due_date ? ' · due ' + esc(b.due_date) : ''} · ${fmt(Math.abs(b.outstanding))}</option>`;
                        });
                        return html;
                    }

                    function syncType($tr) {
                        const t = $tr.find('.bw-type').val();
                        $tr.find('.bw-bill').toggleClass('d-none', t !== 'against_ref');
                        $tr.find('.bw-ref, .bw-due').toggleClass('d-none', t !== 'new_ref');
                    }

                    function addAllocRow(a) {
                        a = a || {};
                        const type = a.ref_type || 'against_ref';
                        const $tr = $(`<tr>
                            <td><select class="form-select form-select-sm bw-type">${Object.entries(TYPES).map(([k, v]) => `<option value="${k}" ${k === type ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
                            <td>
                                <select class="form-select form-select-sm bw-bill">${billOptions(a.bill_reference_id)}</select>
                                <input type="text" class="form-control form-control-sm bw-ref" maxlength="100" placeholder="Bill / reference no." value="${esc(a.ref_number || '')}">
                            </td>
                            <td><input type="date" class="form-control form-control-sm bw-due" value="${esc(a.due_date || '')}"></td>
                            <td><input type="number" class="form-control form-control-sm text-end bw-amount" min="0" step="0.01" value="${a.amount ?? ''}"></td>
                            <td class="text-center"><button type="button" class="btn btn-link btn-sm text-danger p-0 bw-remove" title="Remove"><i class="feather-x"></i></button></td>
                        </tr>`);
                        $body.append($tr);
                        syncType($tr);
                        return $tr;
                    }

                    function collect() {
                        const rows = [];
                        $body.find('tr').each(function () {
                            const $tr = $(this);
                            const t = $tr.find('.bw-type').val();
                            const amount = round2(parseFloat($tr.find('.bw-amount').val()) || 0);
                            if (amount <= 0) return;
                            const r = { ref_type: t, amount: amount };
                            if (t === 'against_ref') {
                                r.bill_reference_id = $tr.find('.bw-bill').val();
                                r.ref_label = $tr.find('.bw-bill option:selected').data('label') || '';
                            }
                            if (t === 'new_ref') {
                                r.ref_number = $tr.find('.bw-ref').val().trim();
                                r.due_date = $tr.find('.bw-due').val();
                                r.ref_label = r.ref_number;
                            }
                            rows.push(r);
                        });
                        return rows;
                    }

                    function totals() {
                        if (!$current) return 0;
                        const line = rowAmount($current);
                        const allocated = collect().reduce((s, r) => s + r.amount, 0);
                        const remaining = round2(line.amount - allocated);
                        $panel.find('.bw-line-amount').text(fmt(line.amount) + (line.side === 'debit' ? ' Dr' : ' Cr'));
                        $panel.find('.bw-allocated').text(fmt(allocated));
                        $panel.find('.bw-remaining').text(fmt(remaining)).toggleClass('text-danger', remaining < 0);
                        return remaining;
                    }

                    function loadBills() {
                        bills = [];
                        const party = $party.val();
                        const accountId = $current ? $current.find('.account-select').val() : null;
                        $panel.find('.bw-party-info').text('');
                        if (!party || !accountId) {
                            return $.Deferred().resolve().promise();
                        }
                        const [type, id] = party.split(':');
                        return $.getJSON(cfg.openBillsUrl, { party_type: type, party_id: id, account_id: accountId }).then(function (d) {
                            bills = d.bills || [];
                            const open = bills.reduce((s, b) => s + b.outstanding, 0);
                            let info = `Open bills: <b>${bills.length}</b> · Outstanding: <b>${fmt(Math.abs(open))} ${open >= 0 ? 'Dr' : 'Cr'}</b>`;
                            if (Math.abs(d.on_account || 0) >= 0.01) {
                                info += ` · On Account: <b>${fmt(Math.abs(d.on_account))} ${d.on_account >= 0 ? 'Dr' : 'Cr'}</b>`;
                            }
                            $panel.find('.bw-party-info').html(info);
                        }, function () {
                            $panel.find('.bw-party-info').text('Could not load this party\'s bills.');
                        });
                    }

                    function close() {
                        $current = null;
                        $panel.addClass('d-none');
                    }

                    function open($row) {
                        $current = $row;
                        $panel.find('.bw-panel-line').text('Line ' + ($row.index() + 1) + ': ' + $row.find('.account-select option:selected').text());
                        $body.empty();
                        $party.empty();
                        const partyVal = $row.find('.bw-party-input').val();
                        if (partyVal) {
                            $party.append(new Option($row.data('bwPartyText') || partyVal, partyVal, true, true));
                        }
                        $party.trigger('change.select2');
                        const allocs = parseAllocs($row);
                        loadBills().always(function () {
                            allocs.forEach((a) => addAllocRow(a));
                            totals();
                        });
                        $panel.removeClass('d-none');
                        $panel[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }

                    $table.on('change', '.account-select', function () {
                        const $row = $(this).closest('tr');
                        refresh($row);
                        if ($current && $current.is($row)) close();
                    });
                    $table.on('click', '.bw-open', function (e) {
                        e.preventDefault();
                        open($(this).closest('tr'));
                    });
                    $table.on('input', '.debit-input, .credit-input', function () {
                        if ($current && $current.is($(this).closest('tr'))) totals();
                    });
                    $table.on('click', '.remove-row-btn', function () {
                        if ($current && $current.is($(this).closest('tr'))) close();
                    });

                    $party.on('change', function () {
                        loadBills().always(function () {
                            $body.find('.bw-bill').each(function () {
                                $(this).html(billOptions($(this).val()));
                            });
                            totals();
                        });
                    });

                    $panel.on('change', '.bw-type', function () { syncType($(this).closest('tr')); totals(); });
                    $panel.on('input', '.bw-amount', totals);
                    $panel.on('change', '.bw-bill', function () {
                        const $tr = $(this).closest('tr');
                        const openAmount = parseFloat($(this).find('option:selected').data('open')) || 0;
                        if (!parseFloat($tr.find('.bw-amount').val()) && openAmount > 0) {
                            const left = Math.max(0, totals());
                            $tr.find('.bw-amount').val(round2(Math.min(openAmount, left || openAmount)).toFixed(2));
                        }
                        totals();
                    });
                    $panel.on('click', '.bw-remove', function () { $(this).closest('tr').remove(); totals(); });
                    $panel.on('click', '.bw-add', function () {
                        const left = totals();
                        addAllocRow({ ref_type: settleable().length ? 'against_ref' : 'new_ref', amount: left > 0 ? left.toFixed(2) : '' });
                        totals();
                    });
                    $panel.on('click', '.bw-auto', function () {
                        $body.find('tr').filter(function () { return $(this).find('.bw-type').val() === 'against_ref'; }).remove();
                        let left = totals();
                        settleable().forEach((b) => {
                            if (left <= 0) return;
                            const take = round2(Math.min(Math.abs(b.outstanding), left));
                            addAllocRow({ ref_type: 'against_ref', bill_reference_id: b.id, amount: take.toFixed(2) });
                            left = round2(left - take);
                        });
                        totals();
                    });
                    $panel.on('click', '.bw-close', close);
                    $panel.on('click', '.bw-done', function () {
                        if (!$current) return;
                        const rows = collect();
                        const party = $party.val() || '';
                        if (rows.length && !party) { alert('Choose the party first.'); return; }
                        if (rows.some((r) => r.ref_type === 'against_ref' && !r.bill_reference_id)) { alert('Pick the bill on every Agst Ref row.'); return; }
                        if (rows.some((r) => r.ref_type === 'new_ref' && !r.ref_number)) { alert('Enter the bill / reference number on every New Ref row.'); return; }
                        if (totals() < -0.005) { alert('Bill-wise amounts are more than the line amount.'); return; }

                        $current.find('.bw-party-input').val(party);
                        $current.data('bwPartyText', party ? $party.find('option:selected').text() : '');
                        $current.find('.bw-alloc-input').val(rows.length ? JSON.stringify(rows) : '');
                        updateSummary($current);
                        close();
                    });
                }

                return { configure, rowFields, rename, refresh, attach };
            })();
        </script>
    @endpush
@endonce

@push('scripts')
    <script>
        window.BillWiseBox.configure(@json(collect($accounts)->filter(fn ($a) => (bool) ($a->bill_wise ?? false))->pluck('id')->values()));
    </script>
@endpush
