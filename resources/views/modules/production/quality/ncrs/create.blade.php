@extends('layouts.duralux')

@section('title', 'Log Non-Conformance Report | SaaS ERP')
@section('page-title', 'Log Quality Defect (NCR)')
@section('breadcrumb', 'New NCR')

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

        <form method="POST" action="{{ route('production.ncrs.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                {{-- Header with Close Button --}}
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">Log Quality Non-Conformance Report</h4>
                    <a href="{{ route('production.ncrs.index') }}" class="text-muted hover-danger fs-18">
                        <i class="feather-x"></i>
                    </a>
                </div>

                {{-- Form Fields --}}
                <div class="row g-4 mb-4 fs-13 text-dark">
                    {{-- Left Column --}}
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="select" label="Defect Category" name="category" :required="true" :error-text="$errors->first('category')">
                            <option value="material" @selected(old('category') === 'material')>Material Defect</option>
                            <option value="process" @selected(old('category') === 'process')>Process Defect</option>
                            <option value="machine" @selected(old('category') === 'machine')>Machine Failure</option>
                            <option value="human_error" @selected(old('category') === 'human_error')>Operator Mistake</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Production Order (Optional)" name="production_order_id" :error-text="$errors->first('production_order_id')">
                            <option value="">None / General NCR</option>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" @selected(old('production_order_id') == $order->id)>
                                    Order #{{ $order->id }} ({{ $order->product->name ?? '—' }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    {{-- Right Column --}}
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="textarea" label="Detailed Defect Description" name="description" placeholder="Describe the non-conformance details, affected quantities, and root cause observations..." rows="6" :required="true" :error-text="$errors->first('description')">{{ old('description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- Footer Action Buttons --}}
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="feather-alert-triangle me-2"></i>Record Defect Report (NCR)
                    </button>
                    <a href="{{ route('production.ncrs.index') }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
