@extends('layouts.duralux')

@section('title', 'Manufacturing Reports & BI | SaaS ERP')
@section('page-title', 'Manufacturing Performance Reports')
@section('breadcrumb', 'Intelligence Reports')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        <h5 class="fw-bold text-dark mb-4"><i class="feather-printer me-2"></i>Select Report to Generate</h5>

        <div class="row g-4">
            {{-- Machine Performance --}}
            <div class="col-md-4">
                <div class="card border border-light shadow-sm h-100 touch-card">
                    <div class="card-body">
                        <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded mb-3">
                            <i class="feather-cpu"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Machine Performance Report</h5>
                        <p class="text-muted fs-13">Analyze OEE components, availability, cycle time losses, and production quantities for individual machines.</p>
                        
                        <form method="GET" action="{{ route('production.intelligence.reports.show', 'machine') }}" target="_blank" class="mt-3">
                            <div class="mb-3">
                                <label class="form-label fs-11 text-uppercase text-muted">Machine</label>
                                <select name="machine_id" class="form-select form-select-sm">
                                    <option value="">All Machines</option>
                                    @foreach($machines as $m)
                                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-primary flex-fill">View Report</button>
                                <button type="submit" name="print" value="1" class="btn btn-sm btn-outline-dark"><i class="feather-printer"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Work Center Report --}}
            <div class="col-md-4">
                <div class="card border border-light shadow-sm h-100 touch-card">
                    <div class="card-body">
                        <div class="avatar-text avatar-lg bg-soft-success text-success rounded mb-3">
                            <i class="feather-settings"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Work Center Report</h5>
                        <p class="text-muted fs-13">Evaluate capacity loading, running vs breakdown machines, bottleneck queue times, and average OEE averages.</p>
                        
                        <form method="GET" action="{{ route('production.intelligence.reports.show', 'work-center') }}" target="_blank" class="mt-3">
                            <div class="mb-3">
                                <label class="form-label fs-11 text-uppercase text-muted">Work Center</label>
                                <select name="work_center_id" class="form-select form-select-sm">
                                    <option value="">All Work Centers</option>
                                    @foreach($workCenters as $wc)
                                        <option value="{{ $wc->id }}">{{ $wc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-success flex-fill">View Report</button>
                                <button type="submit" name="print" value="1" class="btn btn-sm btn-outline-dark"><i class="feather-printer"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Downtime Breakdown --}}
            <div class="col-md-4">
                <div class="card border border-light shadow-sm h-100 touch-card">
                    <div class="card-body">
                        <div class="avatar-text avatar-lg bg-soft-danger text-danger rounded mb-3">
                            <i class="feather-alert-triangle"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Downtime Breakdown Report</h5>
                        <p class="text-muted fs-13">Trace equipment failure downtime occurrences, reasons, setup & adjustments times, and root causes summaries.</p>
                        
                        <form method="GET" action="{{ route('production.intelligence.reports.show', 'downtime') }}" target="_blank" class="mt-3">
                            <div class="mb-3">
                                <label class="form-label fs-11 text-uppercase text-muted">Filter by Machine</label>
                                <select name="machine_id" class="form-select form-select-sm">
                                    <option value="">All Machines</option>
                                    @foreach($machines as $m)
                                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-danger flex-fill">View Report</button>
                                <button type="submit" name="print" value="1" class="btn btn-sm btn-outline-dark"><i class="feather-printer"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
