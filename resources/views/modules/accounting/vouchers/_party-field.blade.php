{{--
    The voucher's Party: a searchable list of customers, suppliers, employees
    and transporters (picking one tags the Payables / Receivables line with
    it and opens that party's bills, like choosing a party ledger in Tally),
    or any typed name kept as a label only.

    Posts party = "type:id" (empty for a name only) and party_name.
--}}
<label class="form-label fw-semibold fs-13 text-dark mb-2" for="voucherParty-{{ $type }}">Party</label>
<select class="form-select voucher-party-select" id="voucherParty-{{ $type }}"
    data-ledgers='@json($partyLedgers ?? [])' data-parties-url="{{ route('accounting.bill-wise.parties') }}"></select>
<input type="hidden" name="party" value="{{ old('party') }}">
<input type="hidden" name="party_name" value="{{ old('party_name') }}">
<div class="fs-11 text-muted mt-1">Pick a customer, supplier or employee, or type any other name.</div>

@once
    @push('scripts')
        <script>
            window.VoucherParty = (function () {
                const plain = (text) => String(text || '').replace(/\s*\((Customer|Supplier|Employee|Transporter)\)\s*$/, '');

                function init(type) {
                    const $form = $('#voucherForm-' + type);
                    const $select = $('#voucherParty-' + type);
                    if (!$select.length || typeof $.fn.select2 !== 'function') return;

                    const $party = $form.find('input[name="party"]');
                    const $name = $form.find('input[name="party_name"]');
                    const ledgers = $select.data('ledgers') || {};

                    $select.select2({
                        theme: 'bootstrap-5', width: '100%', allowClear: true,
                        placeholder: 'Search customer, supplier, employee… or type a name',
                        dropdownParent: $select.closest('.offcanvas, body'),
                        tags: true,
                        createTag: (p) => {
                            const term = (p.term || '').trim();
                            return term ? { id: 'name:' + term, text: term, nameOnly: true } : null;
                        },
                        insertTag: (data, tag) => data.push(tag),
                        templateResult: (d) => d.nameOnly ? $('<span>').text('Use "' + d.text + '" as a name only') : d.text,
                        ajax: {
                            url: $select.data('partiesUrl'), dataType: 'json', delay: 250,
                            data: (p) => ({ q: p.term || '' }), processResults: (d) => d,
                        },
                    });

                    // Back after a failed post: show what was chosen.
                    if ($party.val()) {
                        $select.append(new Option($name.val() || $party.val(), $party.val(), true, true));
                    } else if ($name.val()) {
                        $select.append(new Option($name.val(), 'name:' + $name.val(), true, true));
                    }
                    $select.trigger('change.select2');

                    $select.on('select2:select', function (e) {
                        const d = e.params.data;
                        const id = String(d.id);
                        if (id.startsWith('name:')) {
                            $party.val('');
                            $name.val(id.slice(5));
                            if (window.BillWiseBox) BillWiseBox.clearVoucherParty(type);
                            return;
                        }
                        $party.val(id);
                        $name.val(plain(d.text));
                        if (window.BillWiseBox) BillWiseBox.useVoucherParty(type, id, d.text, ledgers[id.split(':')[0]] || null, Object.values(ledgers));
                    });

                    $select.on('select2:clear', function () {
                        $party.val('');
                        $name.val('');
                        if (window.BillWiseBox) BillWiseBox.clearVoucherParty(type);
                    });
                }

                /** A party picked in a line's own box shows at the top when nothing is chosen there yet. */
                function showIfEmpty($form, party, text) {
                    const $select = $form.find('.voucher-party-select');
                    if (!$select.length || $select.val()) return;
                    $select.append(new Option(text, party, true, true)).trigger('change.select2');
                    $form.find('input[name="party"]').val(party);
                    $form.find('input[name="party_name"]').val(plain(text));
                }

                return { init, showIfEmpty };
            })();
        </script>
    @endpush
@endonce
