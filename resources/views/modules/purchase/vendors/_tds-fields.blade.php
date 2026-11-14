{{-- TDS setup for a vendor; $vendor is optional (create form). --}}
@php
    $vendor ??= null;
    $tdsSections = app(\App\Domains\Purchase\Services\VendorBillTdsService::class)->sectionOptions();
    $tdsOn = (bool) old('tds_applicable', $vendor?->tds_applicable);
@endphp
@if ($tdsSections->isNotEmpty())
    <div class="mt-3">
        <input type="hidden" name="tds_applicable" value="0">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" name="tds_applicable" id="tdsApplicable" value="1" @checked($tdsOn)>
            <label class="form-check-label fw-semibold fs-13" for="tdsApplicable">Deduct TDS on this vendor's bills</label>
        </div>

        <div id="tdsVendorFields" @style(['display: none' => ! $tdsOn])>
            <x-ui.odoo-form-ui type="select" label="TDS section" name="tds_section_id" id="tds_section_id" :errorText="$errors->first('tds_section_id')">
                <option value="">— Select —</option>
                @foreach ($tdsSections as $section)
                    <option value="{{ $section->id }}" @selected((string) old('tds_section_id', $vendor?->tds_section_id) === (string) $section->id)>{{ $section->code }} - {{ $section->name }}</option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="select" label="Deductee type" name="deductee_type" id="deductee_type" :searchable="false" :errorText="$errors->first('deductee_type')">
                <option value="">— Select —</option>
                @foreach (\App\Domains\Accounting\Models\TdsSection::DEDUCTEE_TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(old('deductee_type', $vendor?->deductee_type) === $value)>{{ $label }}</option>
                @endforeach
            </x-ui.odoo-form-ui>
            <div class="small text-muted mb-3">Individuals/HUF and others pay different rates under 194C. Without a PAN, TDS is at 20% (206AA).</div>

            <details @if (old('ldc_number', $vendor?->ldc_number)) open @endif>
                <summary class="fs-13 fw-semibold text-primary mb-2">Lower deduction certificate (section 197)</summary>
                <x-ui.odoo-form-ui type="input" label="Certificate no." name="ldc_number" :value="old('ldc_number', $vendor?->ldc_number)" :errorText="$errors->first('ldc_number')" />
                <x-ui.odoo-form-ui type="input" inputType="number" label="Rate (%)" name="ldc_rate" step="0.001" min="0" :value="old('ldc_rate', $vendor?->ldc_rate)" :errorText="$errors->first('ldc_rate')" />
                <x-ui.odoo-form-ui type="input" inputType="date" label="Valid from" name="ldc_valid_from" :value="old('ldc_valid_from', $vendor?->ldc_valid_from?->format('Y-m-d'))" :errorText="$errors->first('ldc_valid_from')" />
                <x-ui.odoo-form-ui type="input" inputType="date" label="Valid to" name="ldc_valid_to" :value="old('ldc_valid_to', $vendor?->ldc_valid_to?->format('Y-m-d'))" :errorText="$errors->first('ldc_valid_to')" />
                <x-ui.odoo-form-ui type="input" inputType="number" label="Amount limit" name="ldc_limit" step="0.01" min="0" :value="old('ldc_limit', $vendor?->ldc_limit)" :errorText="$errors->first('ldc_limit')" />
            </details>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('tdsApplicable')?.addEventListener('change', function () {
                document.getElementById('tdsVendorFields').style.display = this.checked ? '' : 'none';
            });
        </script>
    @endpush
@endif
