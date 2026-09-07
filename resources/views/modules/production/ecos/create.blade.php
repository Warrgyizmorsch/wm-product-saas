@extends('layouts.duralux')

@section('title', 'Create Engineering Change Order | SaaS ERP')
@section('page-title', 'Create Engineering Change Order')
@section('breadcrumb', 'Engineering Change Orders')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        <form method="POST" action="{{ route('production.ecos.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">New Engineering Change Order (ECO)</h4>
                    <a href="{{ route('production.ecos.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                <!-- ECO Header Fields -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="ECO Title" name="title"
                            placeholder="e.g. Reduce RM-001 quantity & optimize OP20 run time" value="{{ old('title') }}"
                            :required="true" :error-text="$errors->first('title')" />
                    </div>

                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="select" label="Change Type" name="change_type" :required="true">
                            <option value="BOM_CHANGE" {{ old('change_type') == 'BOM_CHANGE' ? 'selected' : '' }}>BOM Change</option>
                            <option value="ROUTING_CHANGE" {{ old('change_type') == 'ROUTING_CHANGE' ? 'selected' : '' }}>Routing Change</option>
                            <option value="BOM_AND_ROUTING_CHANGE" {{ old('change_type') == 'BOM_AND_ROUTING_CHANGE' ? 'selected' : '' }}>BOM & Routing Change</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="select" label="Target Product" name="product_id" :required="true">
                            <option value="">Select Product...</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }} ({{ $product->sku }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Proposed BOM (Optional)" name="proposed_bom_id">
                            <option value="">Select Proposed BOM Version...</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" data-product-id="{{ $bom->product_id }}">{{ $bom->bom_number }} - {{ $bom->bom_name }} (v{{ $bom->version }} / Rev {{ $bom->revision }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Proposed Routing (Optional)" name="proposed_routing_id">
                            <option value="">Select Proposed Routing Version...</option>
                            @foreach($routings as $routing)
                                <option value="{{ $routing->id }}" data-product-id="{{ $routing->product_id }}">{{ $routing->routing_number }} - {{ $routing->name }} (v{{ $routing->version }} / Rev {{ $routing->revision }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" label="Effective Date" name="effective_date" type-attr="date"
                            value="{{ old('effective_date', date('Y-m-d')) }}" />
                    </div>

                    <div class="col-md-8">
                        <x-ui.odoo-form-ui type="input" label="Reason for Engineering Change" name="reason"
                            placeholder="e.g. Design optimization, cost reduction, process enhancement" value="{{ old('reason') }}" />
                    </div>

                    <div class="col-md-12">
                        <x-ui.odoo-form-ui type="textarea" label="Detailed Description / Engineering Notes" name="description"
                            placeholder="Provide detailed explanation of proposed changes..." value="{{ old('description') }}" rows="4" />
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('production.ecos.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-check-circle me-1"></i> Create ECO Draft
                    </button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const allBoms = @json($boms->map(fn($b) => [
            'id' => $b->id,
            'product_id' => (string)$b->product_id,
            'text' => "{$b->bom_number} - {$b->bom_name} (v{$b->version} / Rev {$b->revision})"
        ]));

        const allRoutings = @json($routings->map(fn($r) => [
            'id' => $r->id,
            'product_id' => (string)$r->product_id,
            'text' => "{$r->routing_number} - {$r->name} (v{$r->version} / Rev {$r->revision})"
        ]));

        const $productSelect = $('select[name="product_id"]');
        const $bomSelect = $('select[name="proposed_bom_id"]');
        const $routingSelect = $('select[name="proposed_routing_id"]');

        function filterOptions() {
            const selectedProductId = String($productSelect.val() || '');

            // 1. Filter BOMs
            const filteredBoms = selectedProductId
                ? allBoms.filter(b => b.product_id === selectedProductId)
                : allBoms;

            let bomHtml = '<option value="">Select Proposed BOM Version...</option>';
            filteredBoms.forEach(b => {
                bomHtml += `<option value="${b.id}">${b.text}</option>`;
            });
            $bomSelect.html(bomHtml).val('');
            if ($bomSelect.hasClass('select2-hidden-accessible')) {
                $bomSelect.trigger('change');
            }

            // 2. Filter Routings
            const filteredRoutings = selectedProductId
                ? allRoutings.filter(r => r.product_id === selectedProductId)
                : allRoutings;

            let routingHtml = '<option value="">Select Proposed Routing Version...</option>';
            filteredRoutings.forEach(r => {
                routingHtml += `<option value="${r.id}">${r.text}</option>`;
            });
            $routingSelect.html(routingHtml).val('');
            if ($routingSelect.hasClass('select2-hidden-accessible')) {
                $routingSelect.trigger('change');
            }
        }

        $productSelect.on('change', filterOptions);

        if ($productSelect.val()) {
            filterOptions();
        }
    });
</script>
@endpush
