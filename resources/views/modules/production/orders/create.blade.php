@extends('layouts.duralux')

@section('title', __('production.create_production_order') . ' | SaaS ERP')
@section('page-title', __('production.create_direct_order'))
@section('breadcrumb', __('production.create_production_order'))

{{-- The Add and the Edit screen share ONE form: partials/order-form (+ its styles and scripts), so a change is made once. --}}
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

        @include('modules.production.orders.partials.order-form', ['editOrder' => null])
    </div>
@endsection
