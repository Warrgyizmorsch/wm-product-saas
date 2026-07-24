@extends('layouts.duralux')

@section('title', 'Start Quality Inspection | SaaS ERP')
@section('page-title', 'Select Inspection Quality Plan')
@section('breadcrumb', 'New Checklist')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <form method="POST" action="{{ route('production.inspections.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                {{-- Header with Close Button --}}
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Start Quality Inspection Checklist</h4>
                    <a href="{{ route('production.inspections.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                {{-- Form Fields --}}
                <div class="row g-4 mb-4 fs-13 text-dark">
                    {{-- Left Column --}}
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="select" label="Quality Plan Template" name="quality_plan_id" :required="true" :error-text="$errors->first('quality_plan_id')">
                            <option value="">Select Plan...</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('quality_plan_id') == $plan->id)>
                                    {{ $plan->name }} (v{{ $plan->version }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Inspection Stage" name="stage" :required="true" :error-text="$errors->first('stage')">
                            <option value="incoming" @selected(old('stage') === 'incoming')>Incoming Inspection</option>
                            <option value="in_process" @selected(old('stage', 'in_process') === 'in_process')>In Process Inspection</option>
                            <option value="final" @selected(old('stage') === 'final')>Final Inspection</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    {{-- Right Column --}}
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="Production Order (Optional)" name="production_order_id" :error-text="$errors->first('production_order_id')">
                            <option value="">No Production Order Restriction</option>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" @selected(old('production_order_id') == $order->id)>
                                    {{ $order->order_number }} - {{ $order->product->name }} (Qty: {{ number_format($order->quantity_ordered, 0) }}, {{ ucfirst(str_replace('_', ' ', $order->status)) }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- Footer Action Buttons --}}
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="feather-check-circle me-2"></i>Initialize Checklist
                    </button>
                    <a href="{{ route('production.inspections.index') }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
