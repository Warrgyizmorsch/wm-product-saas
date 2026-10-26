{{--
    TDS on the bill: the section (defaults to the vendor's), the TDS worked out
    for it, and what is left payable to the vendor. The amount can be typed
    over; then tds_overridden is sent and the server keeps it.
    Expects $vendorId (or a #vendorSelect / [name=vendor_id] field), and the
    page to call window.refreshBillTds(baseBeforeGst, grandTotal) on every recalc.
--}}
@php
    $tdsSections = app(\App\Domains\Purchase\Services\VendorBillTdsService::class)->sectionOptions();
@endphp
@if ($tdsSections->isNotEmpty())
    <div id="billTdsBlock" class="mt-3 pt-2 border-top">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted fs-13 fw-semibold">TDS section:</span>
            <select name="tds_section_id" id="billTdsSection" class="form-select form-select-sm" style="width: 190px;">
                <option value="">No TDS</option>
                @foreach ($tdsSections as $section)
                    <option value="{{ $section->id }}" @selected((string) old('tds_section_id') === (string) $section->id)>{{ $section->code }} - {{ $section->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="text-muted fs-13 fw-semibold">Less: TDS <span id="billTdsRate" class="text-muted small"></span></span>
            <input type="number" step="0.01" min="0" name="tds_amount" id="billTdsAmount" class="form-control form-control-sm text-end font-monospace" style="width: 150px; height: 30px;" value="{{ old('tds_amount', '0.00') }}">
        </div>
        <input type="hidden" name="tds_overridden" id="billTdsOverridden" value="{{ old('tds_overridden', 0) }}">
        <div class="small text-muted text-end mb-2" id="billTdsNote"></div>
        <div class="d-flex justify-content-between align-items-center pt-1">
            <span class="fw-bold fs-14">Net payable to vendor:</span>
            <input type="text" id="billTdsNetPayable" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px;" readonly value="0.00">
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                var previewUrl = @json(route('purchase.bills.tds-preview'));
                var fixedVendorId = @json($vendorId ?? null);
                var timer = null;
                var lastBase = 0, lastTotal = 0, sectionTouched = @json(filled(old('tds_section_id')));

                function vendorId() {
                    return fixedVendorId || $('[name="vendor_id"]').val();
                }

                function showNet() {
                    var tds = parseFloat($('#billTdsAmount').val()) || 0;
                    $('#billTdsNetPayable').val('₹' + Math.max(0, lastTotal - tds).toFixed(2));
                }

                function fetchTds() {
                    if (!vendorId() || (sectionTouched && !$('#billTdsSection').val())) { showNet(); return; }
                    $.getJSON(previewUrl, {
                        vendor_id: vendorId(),
                        base: lastBase.toFixed(2),
                        bill_date: $('[name="bill_date"]').val(),
                        tds_section_id: sectionTouched ? $('#billTdsSection').val() : ''
                    }).done(function (res) {
                        if (!sectionTouched) {
                            $('#billTdsSection').val(res.section_id || '');
                        }
                        $('#billTdsRate').text(res.rate ? '(' + res.rate + '%)' : '');
                        $('#billTdsNote').text(res.note || '');
                        if ($('#billTdsOverridden').val() !== '1') {
                            $('#billTdsAmount').val((res.amount || 0).toFixed(2));
                        }
                        showNet();
                    });
                }

                window.refreshBillTds = function (base, grandTotal) {
                    lastBase = base || 0;
                    lastTotal = grandTotal || 0;
                    showNet();
                    clearTimeout(timer);
                    timer = setTimeout(fetchTds, 300);
                };

                $(document).on('change', '#billTdsSection', function () {
                    sectionTouched = true;
                    if (!$(this).val()) {
                        // "No TDS" on a TDS vendor: send 0 as a typed amount, or
                        // the server would apply the vendor's own section.
                        $('#billTdsOverridden').val(1);
                        $('#billTdsAmount').val('0.00');
                        $('#billTdsRate, #billTdsNote').text('');
                        showNet();
                        return;
                    }
                    $('#billTdsOverridden').val(0);
                    fetchTds();
                });
                $(document).on('input', '#billTdsAmount', function () {
                    $('#billTdsOverridden').val(1);
                    $('#billTdsNote').text('Entered by hand.');
                    showNet();
                });
                $(document).on('change', '[name="bill_date"], [name="vendor_id"]', fetchTds);
            })();
        </script>
    @endpush
@endif
