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
    <div class="erp-single-panel bg-white">
        <!-- Header with Close Button -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h4 class="fw-bold text-dark mb-0">New Production Plan</h4>
            <a href="{{ route('production.plans.index') }}" class="text-muted hover-danger fs-18">
                <i class="feather-x"></i>
            </a>
        </div>

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
            
            <div class="row g-4">
                <!-- Left Column -->
                <div class="col-md-6">
                    <x-ui.input label="Plan Name*" name="name" placeholder="e.g. Q3 E-Bike Production Batch" value="{{ old('name') }}" required />
                    
                    @php
                        $productList = ['' => 'Select Product to Produce'];
                        foreach ($products as $p) {
                            $productList[$p->id] = "{$p->name} ({$p->sku})";
                        }
                    @endphp
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Item to Produce*</label>
                        <select name="product_id" id="product_select" class="form-select" data-select2-selector="default" required>
                            @foreach($productList as $id => $label)
                                <option value="{{ $id }}" {{ old('product_id') == $id ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Bill of Materials (BOM)</label>
                        <select name="bom_id" id="bom_select" class="form-select" data-select2-selector="default">
                            <option value="">None / Auto-select (Latest Approved)</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" {{ old('bom_id') == $bom->id ? 'selected' : '' }}>
                                    {{ $bom->bom_number }} - {{ $bom->bom_name }} (v{{ $bom->version }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted fs-11 mt-1 d-block">Leave blank to use the default approved BOM version for the product.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Routing</label>
                        <select name="routing_id" id="routing_select" class="form-select" data-select2-selector="default">
                            <option value="">None / Auto-select (Default Active)</option>
                            @foreach($routings as $rt)
                                <option value="{{ $rt->id }}" {{ old('routing_id') == $rt->id ? 'selected' : '' }}>
                                    {{ $rt->routing_number }} - {{ $rt->name }} (v{{ $rt->version }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted fs-11 mt-1 d-block">Leave blank to use the default active Routing version for the product.</small>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-md-6">
                    <x-ui.input label="Target Quantity to Produce*" name="quantity" type="number" step="any" placeholder="e.g. 50.00" value="{{ old('quantity', '1.00') }}" required />
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <x-ui.input label="Planned Start Date*" name="start_date" type="date" value="{{ old('start_date', date('Y-m-d')) }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Planned End Date*" name="end_date" type="date" value="{{ old('end_date', date('Y-m-d', strtotime('+7 days'))) }}" required />
                        </div>
                    </div>

                    <x-ui.textarea label="Description" name="description" placeholder="Specify production targets, scheduling notes, or customer references..." value="{{ old('description') }}" rows="4" />
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="d-flex gap-2 pt-3 border-top mt-4">
                <button type="submit" class="btn btn-primary px-4">Create Plan</button>
                <a href="{{ route('production.plans.index') }}" class="btn btn-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
@endsection
