@extends('layouts.duralux')

@section('title', 'Enterprise Lot Traceability | SaaS ERP')
@section('page-title', 'Enterprise Lot Traceability')
@section('breadcrumb', 'Lot Traceability')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Search Sheet --}}
        <x-ui.odoo-form-ui type="sheet">
            <h5 class="fw-bold mb-3 text-dark"><i class="feather-search text-primary me-2"></i> Trace Batch / Serial / Order</h5>
            <form method="GET" action="{{ route('production.mes.traceability.search') }}">
                <div class="row g-3 align-items-end fs-13 text-dark">
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="select" label="Entity Type" name="type" id="type" :required="true">
                            <option value="batch" @selected(request('type') === 'batch')>Batch Number</option>
                            <option value="serial" @selected(request('type') === 'serial')>Serial Number</option>
                            <option value="order" @selected(request('type') === 'order')>Production Order</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Unique Code" name="code" placeholder="Enter batch/serial/order number..." value="{{ request('code') }}" :required="true" />
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="feather-sliders me-1"></i> Trace Entity
                        </button>
                    </div>
                </div>
            </form>
        </x-ui.odoo-form-ui>

        {{-- Trace Genealogy Output --}}
        @if(isset($nodes))
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-bottom pt-4 pb-3">
                            <h5 class="fw-bold text-dark mb-0">Trace Result: {{ strtoupper($searchedType) }} [{{ $searchedCode }}]</h5>
                        </div>
                        <div class="card-body">
                            {{-- Visual Genealogy Tree Timeline --}}
                            <div class="position-relative ps-4 border-start border-2 border-primary" style="margin-left: 20px;">
                                @php
                                    // Sort nodes by depth
                                    $sortedNodes = collect($nodes)->sortBy('depth');
                                @endphp

                                @foreach($sortedNodes as $node)
                                    <div class="mb-4 position-relative">
                                        <div class="position-absolute start-0 translate-middle-x bg-primary rounded-circle border border-white" style="width: 16px; height: 16px; left: -29px; top: 12px;"></div>
                                        
                                        <div class="card border border-light shadow-sm">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="badge bg-soft-primary text-primary text-uppercase font-monospace fs-11">{{ $node['type'] }}</span>
                                                    <span class="badge bg-soft-secondary text-secondary fs-12">{{ $node['date'] }}</span>
                                                </div>
                                                <h6 class="fw-bold text-dark mb-1">{{ $node['label'] }}</h6>
                                                <p class="text-muted fs-13 mb-2">{{ $node['detail'] }}</p>
                                                @if(isset($node['status']))
                                                    <span class="badge bg-soft-success text-success">{{ strtoupper($node['status']) }}</span>
                                                @endif

                                                {{-- Edges / Relationships --}}
                                                @php
                                                    $nodeEdges = collect($edges)->where('target_key', $node['key']);
                                                @endphp
                                                @if($nodeEdges->isNotEmpty())
                                                    <div class="mt-2 pt-2 border-top text-muted fs-11">
                                                        <i class="feather-corner-down-right me-1"></i> Derived from: 
                                                        @foreach($nodeEdges as $edge)
                                                            <span class="badge bg-light text-dark font-monospace border mx-1">
                                                                {{ $edge['source_key'] }} (Qty: {{ number_format($edge['quantity'], 2) }})
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
