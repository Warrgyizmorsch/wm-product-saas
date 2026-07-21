@extends('layouts.duralux')

@section('title', 'Quality Management & Yield | SaaS ERP')
@section('page-title', 'Quality Management Dashboard')
@section('breadcrumb', 'Quality Dashboard')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        {{-- Row 1: Quality Inspection Metrics --}}
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Total Inspections</div>
                        <h2 class="fw-bold text-dark mt-2">{{ number_format($totalInspections) }}</h2>
                        <span class="badge bg-soft-primary text-primary fs-10 mt-1">All Recorded</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Pending Inspections</div>
                        <h2 class="fw-bold text-warning mt-2">{{ number_format($pendingInspections) }}</h2>
                        <span class="badge bg-soft-warning text-warning fs-10 mt-1">Awaiting Audit</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Passed Inspections</div>
                        <h2 class="fw-bold text-success mt-2">{{ number_format($passedInspections) }}</h2>
                        <span class="badge bg-soft-success text-success fs-10 mt-1">Met Acceptance</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Failed Inspections</div>
                        <h2 class="fw-bold text-danger mt-2">{{ number_format($failedInspections) }}</h2>
                        <span class="badge bg-soft-danger text-danger fs-10 mt-1">Defects Found</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 2: Yield, Exception & Quality Resolution Stats --}}
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">First Pass Yield (FPY)</div>
                        <h2 class="fw-bold text-success mt-2">{{ number_format($fpy, 2) }}%</h2>
                        <span class="badge bg-soft-success text-success fs-10 mt-1">Final Stage Quality Rate</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Active NCRs</div>
                        <h2 class="fw-bold text-danger mt-2">{{ number_format($ncrOpen) }}</h2>
                        <span class="badge bg-soft-danger text-danger fs-10 mt-1">Open Non-Conformances (Closed: {{ $ncrClosed }})</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Active CAPAs</div>
                        <h2 class="fw-bold text-primary mt-2">{{ number_format($capaOpen) }}</h2>
                        <span class="badge bg-soft-primary text-primary fs-10 mt-1">Under Action (Verified: {{ $capaClosed }})</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border border-light shadow-sm text-center">
                    <div class="card-body">
                        <div class="fs-12 text-muted text-uppercase fw-bold">Rework & Scrap</div>
                        <h2 class="fw-bold text-dark mt-2">{{ number_format($reworkCount) }} / {{ number_format($scrapCount) }}</h2>
                        <span class="badge bg-soft-secondary text-secondary fs-10 mt-1">Rework Orders / Scrap Items</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 3: Active Inspections & NCR Data Tables --}}
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border border-light shadow-sm h-100">
                    <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-check-circle text-primary me-2"></i> Recent Quality Inspections</h6>
                        <a href="{{ route('production.inspections.index') }}" class="btn btn-sm btn-outline-primary fs-11">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Stage</th>
                                        <th>Order</th>
                                        <th>Result</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentInspections as $insp)
                                        <tr>
                                            <td class="fw-bold text-dark">#{{ $insp->id }}</td>
                                            <td><span class="badge bg-soft-info text-info text-capitalize">{{ $insp->stage }}</span></td>
                                            <td>{{ $insp->order?->order_number ?? 'N/A' }}</td>
                                            <td>
                                                @if($insp->result === 'passed')
                                                    <span class="badge bg-soft-success text-success">PASSED</span>
                                                @elseif($insp->result === 'failed')
                                                    <span class="badge bg-soft-danger text-danger">FAILED</span>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary">PENDING</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-primary text-primary text-capitalize">{{ $insp->status ?? 'Draft' }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No quality inspections recorded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border border-light shadow-sm h-100">
                    <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-alert-triangle text-danger me-2"></i> Active Non-Conformance Reports (NCRs)</h6>
                        <a href="{{ route('production.ncrs.index') }}" class="btn btn-sm btn-outline-danger fs-11">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="table-light">
                                    <tr>
                                        <th>NCR #</th>
                                        <th>Category</th>
                                        <th>Order</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentNcrs as $ncr)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $ncr->ncr_number }}</td>
                                            <td><span class="badge bg-soft-warning text-warning text-capitalize">{{ $ncr->category }}</span></td>
                                            <td>{{ $ncr->order?->order_number ?? 'N/A' }}</td>
                                            <td>
                                                @if($ncr->status === 'open')
                                                    <span class="badge bg-soft-danger text-danger">OPEN</span>
                                                @else
                                                    <span class="badge bg-soft-success text-success">CLOSED</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No open non-conformance reports.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
