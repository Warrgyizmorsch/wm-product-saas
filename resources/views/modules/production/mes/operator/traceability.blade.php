@extends('layouts.duralux')

@section('title', 'Enterprise Lot Traceability | SaaS ERP')
@section('page-title', 'Enterprise Lot Traceability')
@section('breadcrumb', 'Lot Traceability')

@section('content')
    <div class="container-fluid py-2">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Search Card --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="feather-search text-primary me-2"></i> Trace Batch / Serial / Order</h5>
                <form method="GET" action="{{ route('production.mes.traceability.search') }}">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Entity Type</label>
                            <select name="type" class="form-select form-select-lg">
                                <option value="batch" @if(request('type') === 'batch') selected @endif>Batch Number</option>
                                <option value="serial" @if(request('type') === 'serial') selected @endif>Serial Number</option>
                                <option value="order" @if(request('type') === 'order') selected @endif>Production Order</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Unique Code / Tag</label>
                            <input type="text" name="code" class="form-control form-control-lg fw-bold font-monospace" placeholder="Enter batch/serial/order number..." value="{{ request('code') }}" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-lg w-100" style="min-height: 48px;">
                                <i class="feather-sliders me-1"></i> Trace
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

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
