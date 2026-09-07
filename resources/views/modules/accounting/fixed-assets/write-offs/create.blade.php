@extends('layouts.duralux')

@section('title', 'New Write-off | SaaS ERP')
@section('page-title', 'New Asset Write-off')
@section('breadcrumb', 'Accounting / Fixed Assets / Write-offs / Create')

@section('content')
    <div class="erp-single-panel bg-white">
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
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-0">Write-off Details</h5>
                    <x-ui.button href="{{ route('accounting.fixed-assets.write-offs.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
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
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Write-off Date" name="write_off_date" :value="old('write_off_date', now()->toDateString())" :required="true" />
                    </div>
                    <div class="col-12">
                        <x-ui.odoo-form-ui type="textarea" label="Reason" name="reason" rows="3" placeholder="Why is this asset being written off? (e.g. damaged beyond repair, lost)" :required="true" />
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('accounting.fixed-assets.write-offs.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Submit for Approval</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
