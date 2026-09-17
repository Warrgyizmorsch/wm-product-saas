{{-- Shared revaluation form — included standalone (create.blade.php, for
     direct-URL/back-compat access) and inside the drawer on index.blade.php. --}}
@php $embedded = $embedded ?? false; @endphp

@if ($errors->any())
    <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
        <h6 class="alert-heading fw-bold mb-1">Cannot submit this revaluation</h6>
        <ul class="fs-12 mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif

<form action="{{ route('accounting.fixed-assets.revaluations.store') }}" method="POST">
    @csrf

    <x-ui.odoo-form-ui type="sheet">
        @unless ($embedded)
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <h5 class="fw-bold text-dark mb-0">Revaluation Details</h5>
                <x-ui.button href="{{ route('accounting.fixed-assets.revaluations.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
            </div>
        @endunless

        @if ($embedded)
            <div class="row g-3 fs-13 text-dark">
                <div class="col-12">
                    <label class="form-label fw-semibold fs-13 text-dark mb-2">Asset <span class="text-danger">*</span></label>
                    <x-ui.select name="asset_id" :required="true" :selected="old('asset_id')" :options="['' => 'Select Asset...'] + $assets->mapWithKeys(fn ($a) => [$a->id => $a->asset_code . ' — ' . $a->name . ' (Book Value: ' . number_format($a->book_value ?? $a->capitalization_cost, 2) . ')'])->all()" />
                </div>
                <div class="col-md-6">
                    <x-ui.icon-input label="Revaluation Date" icon="feather-calendar" type="date" name="revaluation_date" :value="old('revaluation_date', now()->toDateString())" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.icon-input label="Revalued Amount" icon="feather-dollar-sign" type="number" name="revalued_amount" :value="old('revalued_amount')" placeholder="New fair value" :required="true" helperText="Surplus posts to Revaluation Reserve; deficit posts as Impairment Loss." />
                </div>
                <div class="col-md-6">
                    <x-ui.icon-input label="Revised Useful Life (months)" icon="feather-clock" type="number" name="revised_useful_life_months" :value="old('revised_useful_life_months')" placeholder="Optional — leave blank to keep current" />
                </div>
                <div class="col-md-6">
                    <x-ui.icon-input label="Revised Residual Value" icon="feather-hash" type="number" name="revised_residual_value" :value="old('revised_residual_value')" placeholder="Optional — leave blank to keep current" />
                </div>
                <div class="col-12">
                    <x-ui.icon-input label="Reason" icon="feather-file-text" name="reason" :value="old('reason')" placeholder="Basis for this revaluation (e.g. independent market appraisal)" :required="true" />
                </div>
            </div>
        @else
            <div class="row g-4 fs-13 text-dark">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Asset" name="asset_id" :required="true">
                        <option value="">Select Asset...</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}" @selected(old('asset_id') == $asset->id)>{{ $asset->asset_code }} — {{ $asset->name }} (Book Value: {{ number_format($asset->book_value ?? $asset->capitalization_cost, 2) }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Revaluation Date" name="revaluation_date" :value="old('revaluation_date', now()->toDateString())" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Revalued Amount" name="revalued_amount" :value="old('revalued_amount')" placeholder="New fair value" :required="true" helperText="Surplus (increase) posts to Revaluation Reserve; deficit (decrease) posts as Impairment Loss." />
                </div>
                <div class="col-md-6"></div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Revised Useful Life (months)" name="revised_useful_life_months" :value="old('revised_useful_life_months')" placeholder="Optional — leave blank to keep current" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Revised Residual Value" name="revised_residual_value" :value="old('revised_residual_value')" placeholder="Optional — leave blank to keep current" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Reason" name="reason" rows="3" placeholder="Basis for this revaluation (e.g. independent market appraisal, impairment assessment)" :required="true" />
                </div>
            </div>
        @endif

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            @unless ($embedded)
                <x-ui.button href="{{ route('accounting.fixed-assets.revaluations.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
            @endunless
            <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Submit for Approval</x-ui.button>
        </div>
    </x-ui.odoo-form-ui>
</form>
