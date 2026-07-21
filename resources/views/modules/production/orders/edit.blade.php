@extends('layouts.duralux')

@section('title', __('production.edit_production_order') . ' ' . $order->order_number . ' | SaaS ERP')

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
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <form method="POST" action="{{ route('production.orders.update', $order->id) }}">
            @csrf
            @method('PUT')

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">{{ __('production.edit_production_order_number', ['number' => $order->order_number]) }}</h4>
                        <small class="text-muted fs-12">{{ __('production.status') }}: <span class="fw-semibold text-uppercase text-primary">{{ $order->status }}</span> &mdash; {{ __('production.edit_order_draft_notice') }}</small>
                    </div>
                    <a href="{{ route('production.orders.show', $order->id) }}" class="btn btn-sm btn-light border">{{ __('production.cancel') }}</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" :label="__('production.target_product')" name="product_name" :value="$order->product->name" :readonly="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.bill_of_materials')" name="bom_ref" :value="$order->bom ? ($order->bom->bom_number . ' - ' . ($order->bom->bom_name ?? '') . ' (v' . $order->bom->version . ')') : 'No BOM assigned'" :readonly="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.process_routing')" name="routing_ref" :value="$order->routing ? ($order->routing->routing_number . ' - ' . $order->routing->name . ' (v' . $order->routing->version . ')') : 'No Routing assigned'" :readonly="true" />
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.qty_to_manufacture')" name="quantity_ordered" inputType="number" placeholder="e.g. 10.0000" :value="old('quantity_ordered', $order->quantity_ordered)" :required="true" />

                        <x-ui.odoo-form-ui type="input" :label="__('production.start_date')" name="start_date" inputType="date" :value="old('start_date', $order->start_date->format('Y-m-d'))" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.end_date')" name="end_date" inputType="date" :value="old('end_date', $order->end_date->format('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="textarea" :label="__('production.remarks')" name="description" :placeholder="__('production.remarks_placeholder')" rows="4">{{ old('description', $order->description) }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="feather-check-circle me-2"></i>{{ __('production.save_changes') }}
                    </button>
                    <a href="{{ route('production.orders.show', $order->id) }}" class="btn btn-secondary px-4">{{ __('production.cancel') }}</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
