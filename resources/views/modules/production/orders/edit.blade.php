@extends('layouts.duralux')

@section('title', 'Edit Production Order ' . $order->order_number . ' | SaaS ERP')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
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

        @if(session('error'))
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                <p class="fs-12 mb-0">{{ session('error') }}</p>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        <form method="POST" action="{{ route('production.orders.update', $order->id) }}">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Edit Production Order — {{ $order->order_number }}</h4>
                        <small class="text-muted fs-12">Status: <span class="fw-semibold text-uppercase text-primary">{{ $order->status }}</span> &mdash; Edits are only allowed while the order remains in Draft.</small>
                    </div>
                    <a href="{{ route('production.orders.show', $order->id) }}" class="btn btn-sm btn-light border">Cancel</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" label="Target Product" name="product_name" :value="$order->product->name" :readonly="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="BOM Reference" name="bom_ref" :value="$order->bom ? ($order->bom->bom_number . ' - ' . ($order->bom->bom_name ?? '') . ' (v' . $order->bom->version . ')') : 'No BOM assigned'" :readonly="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="Routing Reference" name="routing_ref" :value="$order->routing ? ($order->routing->routing_number . ' - ' . $order->routing->name . ' (v' . $order->routing->version . ')') : 'No Routing assigned'" :readonly="true" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Qty to Manufacture" name="quantity_ordered" inputType="number" placeholder="e.g. 10.0000" :value="old('quantity_ordered', $order->quantity_ordered)" :required="true" />

                        <x-ui.odoo-form-ui type="input" label="Start Date" name="start_date" inputType="date" :value="old('start_date', $order->start_date->format('Y-m-d'))" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="End Date" name="end_date" inputType="date" :value="old('end_date', $order->end_date->format('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="textarea" label="Remarks" name="description" placeholder="Enter special manufacturing instructions, operator notes..." rows="4">{{ old('description', $order->description) }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="feather-check-circle me-2"></i>Save Changes
                    </button>
                    <a href="{{ route('production.orders.show', $order->id) }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
