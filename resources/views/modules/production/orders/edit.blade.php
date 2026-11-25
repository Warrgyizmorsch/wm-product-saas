@extends('layouts.duralux')

@section('title', __('production.edit_production_order') . ' ' . $order->order_number . ' | SaaS ERP')

{{-- M-34: the Edit screen is the Add form (partials/order-form) with the saved order filled in — every field and the whole BOM / routing workspace. --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    @include('modules.production.orders.partials.order-form-styles')
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    @include('modules.production.orders.partials.order-form-scripts')
@endpush

@section('content')
    <div class="erp-single-panel">
        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @include('modules.production.orders.partials.order-form', ['editOrder' => $order])
    </div>
@endsection
