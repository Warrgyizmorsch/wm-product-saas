@extends('layouts.duralux')

@section('title', 'Register Asset | SaaS ERP')
@section('page-title', 'Register Asset')
@section('breadcrumb', 'Accounting / Fixed Assets / Register Asset')

@section('content')
    <div class="erp-single-panel">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        <form method="POST" action="{{ route('accounting.fixed-assets.register.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Register a Fixed Asset</h4>
                        <small class="text-muted fs-12">For assets acquired outside a Purchase Order (e.g. opening balances, direct entry). Once saved, capitalize it from the Asset Register to start depreciation.</small>
                    </div>
                    <a href="{{ route('accounting.fixed-assets.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="select" label="Asset Category" name="asset_category_id" :required="true">
                            <option value="">Select category...</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('asset_category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" label="Asset Name" name="name" placeholder="e.g. Dell 27&quot; 4K Monitor" :value="old('name')" :required="true" />

                        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Optional details..." :value="old('description')" />

                        <x-ui.odoo-form-ui type="input" label="Brand" name="brand" :value="old('brand')" />

                        <x-ui.odoo-form-ui type="input" label="Model Number" name="model_number" :value="old('model_number')" />
                    </div>

                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Asset Code" name="asset_code" placeholder="e.g. AST-MON-001" :value="old('asset_code')" :required="true" />

                        <x-ui.odoo-form-ui type="input" label="Serial Number" name="serial_number" :value="old('serial_number')" />

                        <x-ui.odoo-form-ui type="select" label="Condition" name="condition" :required="true">
                            <option value="new" @selected(old('condition', 'new') == 'new')>New</option>
                            <option value="good" @selected(old('condition') == 'good')>Good</option>
                            <option value="fair" @selected(old('condition') == 'fair')>Fair</option>
                            <option value="damaged" @selected(old('condition') == 'damaged')>Damaged</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" label="Purchase Date" name="purchase_date" inputType="date" :value="old('purchase_date')" />

                        <x-ui.odoo-form-ui type="input" label="Purchase Cost" name="purchase_cost" inputType="number" step="0.01" min="0" :value="old('purchase_cost')" />

                        <x-ui.odoo-form-ui type="textarea" label="Notes" name="notes" :value="old('notes')" />
                    </div>
                </div>

                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">Register Asset</button>
                    <a href="{{ route('accounting.fixed-assets.index') }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
