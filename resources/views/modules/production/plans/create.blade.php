@extends('layouts.duralux')

@section('title', 'Create Production Plan | SaaS ERP')
@section('page-title', 'Create Production Plan')
@section('breadcrumb', 'Create Production Plan')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            // Setup AJAX dynamic dropdown triggers
            $('#product_select').on('change', function() {
                var productId = $(this).val();
                if (!productId) {
                    $('#bom_select').html('<option value="">None / Auto Select</option>').trigger('change');
                    $('#routing_select').html('<option value="">None / Auto Select</option>').trigger('change');
                    return;
                }

                // Show loading
                $('#bom_select').html('<option value="">Loading...</option>').trigger('change');
                $('#routing_select').html('<option value="">Loading...</option>').trigger('change');

                $.ajax({
                    url: "{{ route('production.plans.engineering-options') }}",
                    method: 'GET',
                    data: { product_id: productId },
                    success: function(response) {
                        var bomHtml = '<option value="">None / Auto-select (Latest Approved)</option>';
                        if (response.boms && response.boms.length > 0) {
                            response.boms.forEach(function(bom) {
                                bomHtml += '<option value="' + bom.id + '">' + bom.bom_number + ' - ' + (bom.bom_name || '') + ' (v' + bom.version + ')</option>';
                            });
                        } else {
                            bomHtml += '<option value="" disabled>No approved BOM found for this product</option>';
                        }
                        $('#bom_select').html(bomHtml).trigger('change');

                        var routingHtml = '<option value="">None / Auto-select (Default Active)</option>';
                        if (response.routings && response.routings.length > 0) {
                            response.routings.forEach(function(rt) {
                                routingHtml += '<option value="' + rt.id + '">' + rt.routing_number + ' - ' + rt.name + ' (v' + rt.version + ')</option>';
                            });
                        } else {
                            routingHtml += '<option value="" disabled>No active Routing found for this product</option>';
                        }
                        $('#routing_select').html(routingHtml).trigger('change');
                    },
                    error: function() {
                        $('#bom_select').html('<option value="">Error loading options</option>').trigger('change');
                        $('#routing_select').html('<option value="">Error loading options</option>').trigger('change');
                    }
                });
            });
        });
    </script>
@endpush

@section('content')
    <div class="erp-single-panel">
        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">Validation Errors!</h6>
                <ul class="mb-0 fs-12 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        <form method="POST" action="{{ route('production.plans.store') }}">
            @csrf
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">New Production Plan</h4>
                    <a href="{{ route('production.plans.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" label="Plan Name" name="name" placeholder="e.g. Q3 E-Bike Production Batch" :value="old('name')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" label="Item to Produce" name="product_id" id="product_select" :required="true">
                            <option value="">Select Product to Produce</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Bill of Materials" name="bom_id" id="bom_select">
                            <option value="">None / Auto-select (Latest Approved)</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" @selected(old('bom_id') == $bom->id)>
                                    {{ $bom->bom_number }} - {{ $bom->bom_name }} (v{{ $bom->version }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Routing" name="routing_id" id="routing_select">
                            <option value="">None / Auto-select (Default Active)</option>
                            @foreach($routings as $rt)
                                <option value="{{ $rt->id }}" @selected(old('routing_id') == $rt->id)>
                                    {{ $rt->routing_number }} - {{ $rt->name }} (v{{ $rt->version }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Target Quantity" name="quantity" inputType="number" placeholder="e.g. 50.00" :value="old('quantity', '1.00')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Planned Start Date" name="start_date" inputType="date" :value="old('start_date', date('Y-m-d'))" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Planned End Date" name="end_date" inputType="date" :value="old('end_date', date('Y-m-d', strtotime('+7 days')))" :required="true" />

                        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Specify production targets, scheduling notes, or customer references..." rows="4">{{ old('description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">Create Plan</button>
                    <a href="{{ route('production.plans.index') }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
