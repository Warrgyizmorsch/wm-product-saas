@extends('layouts.duralux')

@section('title', 'Quality Management & Yield | SaaS ERP')
@section('page-title', 'Quality Management Dashboard')
@section('breadcrumb', 'Quality Dashboard')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        {{-- Yield & Exception KPI summary cards --}}
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">First Pass Yield (FPY)</div>
                        <h2 class="fw-bold text-success mt-2">{{ number_format($fpy, 2) }}%</h2>
                        <span class="badge bg-soft-success text-success fs-10 mt-1">Quality Rate</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Active Rework Orders</div>
                        <h2 class="fw-bold text-warning mt-2">{{ $reworkCount }}</h2>
                        <span class="badge bg-soft-warning text-warning fs-10 mt-1">In Process</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Open NCRs</div>
                        <h2 class="fw-bold text-danger mt-2">{{ $ncrOpen }}</h2>
                        <span class="badge bg-soft-danger text-danger fs-10 mt-1">Pending Disposition</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Open CAPAs</div>
                        <h2 class="fw-bold text-primary mt-2">{{ $capaOpen }}</h2>
                        <span class="badge bg-soft-primary text-primary fs-10 mt-1">Under Investigation</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Visual Quality Metrics grid --}}
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border border-light shadow-sm">
                    <div class="card-header bg-light py-3">
                        <h6 class="fw-bold text-dark mb-0">Quality Resolution Pipelines</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col">
                                <div class="text-muted fs-11 text-uppercase">NCRs Resolved</div>
                                <h4 class="fw-bold text-dark mt-1">{{ $ncrClosed }}</h4>
                            </div>
                            <div class="col">
                                <div class="text-muted fs-11 text-uppercase">CAPAs Verified</div>
                                <h4 class="fw-bold text-success mt-1">{{ $capaClosed }}</h4>
                            </div>
                            <div class="col">
                                <div class="text-muted fs-11 text-uppercase">Total Scrapped</div>
                                <h4 class="fw-bold text-danger mt-1">{{ $scrapCount }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border border-light shadow-sm">
                    <div class="card-header bg-light py-3">
                        <h6 class="fw-bold text-dark mb-0">Quality Pareto Analysis (Pareto Chart Placeholder)</h6>
                    </div>
                    <div class="card-body text-center py-5">
                        <i class="feather-bar-chart-2 fs-32 text-muted"></i>
                        <p class="text-muted fs-12 mt-2">Defects counts pareto visualization by machine categories</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
