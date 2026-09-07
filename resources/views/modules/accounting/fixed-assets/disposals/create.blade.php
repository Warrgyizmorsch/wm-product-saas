@extends('layouts.duralux')

@section('title', 'New Disposal | SaaS ERP')
@section('page-title', 'New Asset Disposal')
@section('breadcrumb', 'Accounting / Fixed Assets / Disposals / Create')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
                <h6 class="alert-heading fw-bold mb-1">Cannot submit this disposal</h6>
                <ul class="fs-12 mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form action="{{ route('accounting.fixed-assets.disposals.store') }}" method="POST">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-0">Disposal Details</h5>
                    <x-ui.button href="{{ route('accounting.fixed-assets.disposals.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                </div>

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
                        <x-ui.odoo-form-ui type="select" label="Disposal Type" name="disposal_type" :required="true">
                            <option value="">Select Type...</option>
                            <option value="sale" @selected(old('disposal_type') === 'sale')>Sale</option>
                            <option value="scrap" @selected(old('disposal_type') === 'scrap')>Scrap</option>
                            <option value="lost" @selected(old('disposal_type') === 'lost')>Lost</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Disposal Date" name="disposal_date" :value="old('disposal_date', now()->toDateString())" :required="true" />
                    </div>
                    <div class="col-md-6"></div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="number" label="Sale Proceeds" name="sale_proceeds" :value="old('sale_proceeds', 0)" placeholder="0.00" helperText="Leave 0 for scrap/lost with no recovery." />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="number" label="Tax Amount" name="tax_amount" :value="old('tax_amount', 0)" placeholder="0.00" />
                    </div>
                    <div class="col-12">
                        <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks" rows="3" placeholder="Optional notes about this disposal" />
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('accounting.fixed-assets.disposals.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Submit for Approval</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
