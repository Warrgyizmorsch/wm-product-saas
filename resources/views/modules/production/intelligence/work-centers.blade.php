@extends('layouts.duralux')

@section('title', 'Work Center OEE & Capacity | SaaS ERP')
@section('page-title', 'Work Center Dashboards')
@section('breadcrumb', 'Work Centers')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        <h5 class="fw-bold text-dark mb-4"><i class="feather-settings me-2"></i>Real-time Work Center Intelligence</h5>

        <div class="row g-4">
            @foreach($wcSummaries as $wc)
                <div class="col-md-6">
                    <div class="card border border-light h-100 shadow-sm">
                        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">Work Center ID: {{ $wc['work_center_id'] }}</h6>
                            <span class="badge bg-soft-primary text-primary">Active</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 text-center mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="text-muted fs-11 text-uppercase">Running Machines</div>
                                    <h4 class="fw-bold text-success mt-1">{{ $wc['running_machines'] }}</h4>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-muted fs-11 text-uppercase">Total Machines</div>
                                    <h4 class="fw-bold text-dark mt-1">{{ $wc['total_machines'] }}</h4>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-muted fs-11 text-uppercase">OEE %</div>
                                    <h4 class="fw-bold text-primary mt-1">{{ $wc['metrics']['oee'] }}%</h4>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="text-muted fs-11 text-uppercase">Availability</div>
                                    <h4 class="fw-bold text-info mt-1">{{ $wc['metrics']['availability'] }}%</h4>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>Average Efficiency</span>
                                    <strong>{{ $wc['metrics']['performance'] }}%</strong>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-info" style="width: {{ $wc['metrics']['performance'] }}%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="d-flex justify-content-between fs-12 mb-1">
                                    <span>Capacity Load Utilization</span>
                                    <strong>{{ $wc['metrics']['availability'] }}%</strong>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-warning" style="width: {{ $wc['metrics']['availability'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
