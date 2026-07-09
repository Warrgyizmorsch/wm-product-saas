@extends('layouts.duralux')

@section('title', 'KPI Target Configuration | SaaS ERP')
@section('page-title', 'KPI Target Configuration')
@section('breadcrumb', 'KPI Targets')

@section('content')
    <div class="container-fluid py-2">
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
            <form method="POST" action="{{ route('production.kpi-targets.store') }}">
                @csrf

                <x-ui.odoo-form-ui type="sheet">
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                        <h4 class="fw-bold text-dark mb-0"><i class="feather-target text-primary me-2"></i>KPI Target Configurations</h4>
                        <a href="{{ route('production.intelligence.dashboard') }}" class="text-muted hover-danger fs-18">
                            <i class="feather-x"></i>
                        </a>
                    </div>

                    <p class="fs-13 text-muted mb-4">
                        Configure target thresholds for your manufacturing metrics. These targets are evaluated by the Manufacturing Intelligence KPI Engine to calculate OEE metrics and display targets/variances across the production floor.
                    </p>

                    <div class="row g-4 mb-4">
                        @foreach($targets as $kpiName => $target)
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui 
                                    type="input" 
                                    label="{{ $target['label'] }} ({{ $target['unit'] }})" 
                                    name="{{ $kpiName }}" 
                                    inputType="number" 
                                    step="0.01" 
                                    :value="old($kpiName, number_format($target['value'], 2, '.', ''))" 
                                    :required="true" 
                                    :error-text="$errors->first($kpiName)" 
                                />
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-1"></i> Save KPI Targets
                        </button>
                        <a href="{{ route('production.intelligence.dashboard') }}" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </x-ui.odoo-form-ui>
            </form>
        </div>
    </div>
@endsection
