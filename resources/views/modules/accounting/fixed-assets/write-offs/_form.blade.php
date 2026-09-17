{{-- Shared write-off form — included standalone (create.blade.php, for
     direct-URL/back-compat access) and inside the drawer on index.blade.php. --}}
@php $embedded = $embedded ?? false; @endphp

@if ($errors->any())
    <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
        <h6 class="alert-heading fw-bold mb-1">Cannot submit this write-off</h6>
        <ul class="fs-12 mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif

<form action="{{ route('accounting.fixed-assets.write-offs.store') }}" method="POST">
    @csrf

    <x-ui.odoo-form-ui type="sheet">
        @unless ($embedded)
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <h5 class="fw-bold text-dark mb-0">Write-off Details</h5>
                <x-ui.button href="{{ route('accounting.fixed-assets.write-offs.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
            </div>
        @endunless

        @if ($embedded)
            <div class="row g-3 fs-13 text-dark">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-13 text-dark mb-2">Asset <span class="text-danger">*</span></label>
                    <x-ui.select name="asset_id" :required="true" :options="['' => 'Select Asset...'] + $assets->mapWithKeys(fn ($a) => [$a->id => $a->asset_code . ' — ' . $a->name])->all()" :selected="old('asset_id')" />
                </div>
                <div class="col-md-6">
                    <x-ui.icon-input label="Write-off Date" icon="feather-calendar" type="date" name="write_off_date" :value="old('write_off_date', now()->toDateString())" :required="true" />
                </div>
                <div class="col-12">
                    <x-ui.icon-input label="Reason" icon="feather-file-text" name="reason" :value="old('reason')" placeholder="Why is this asset being written off? (e.g. damaged beyond repair, lost)" :required="true" />
                </div>
            </div>
        @else
            <div class="row g-4 fs-13 text-dark">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Asset" name="asset_id" :required="true">
                        <option value="">Select Asset...</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}" @selected(old('asset_id') == $asset->id)>{{ $asset->asset_code }} — {{ $asset->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Write-off Date" name="write_off_date" :value="old('write_off_date', now()->toDateString())" :required="true" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Reason" name="reason" rows="3" placeholder="Why is this asset being written off? (e.g. damaged beyond repair, lost)" :required="true" />
                </div>
            </div>
        @endif

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            @unless ($embedded)
                <x-ui.button href="{{ route('accounting.fixed-assets.write-offs.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
            @endunless
            <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Submit for Approval</x-ui.button>
        </div>
    </x-ui.odoo-form-ui>
</form>
