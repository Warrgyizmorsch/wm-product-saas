@extends('layouts.duralux')

@section('title', 'Rework Execution Track | SaaS ERP')
@section('page-title', 'Rework Execution Shopfloor')
@section('breadcrumb', 'Rework details')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm" style="max-width: 900px;">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">{{ $rework->rework_number }}</h4>
                <div class="text-muted fs-12">Linked Non-Conformance: <strong>{{ $rework->ncr->ncr_number ?? '—' }}</strong></div>
            </div>
            <div>
                <span class="badge bg-soft-primary text-primary px-3 py-2 text-uppercase">{{ $rework->status }}</span>
            </div>
        </div>

        {{-- Costs summary --}}
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="border p-3 rounded text-center">
                    <div class="text-muted fs-11 text-uppercase">Rework Cost Estimate</div>
                    <h5 class="fw-bold text-dark mt-1">${{ number_format($rework->cost_estimate, 2) }}</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 rounded text-center border-warning">
                    <div class="text-muted fs-11 text-uppercase">Actual Rework Cost</div>
                    <h5 class="fw-bold text-warning mt-1">${{ number_format($rework->actual_cost, 2) }}</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 rounded text-center">
                    <div class="text-muted fs-11 text-uppercase">Labor Hours logged</div>
                    <h5 class="fw-bold text-dark mt-1">{{ number_format($rework->labor_hours_actual, 2) }} hrs</h5>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border p-3 rounded text-center">
                    <div class="text-muted fs-11 text-uppercase">Machine Hours logged</div>
                    <h5 class="fw-bold text-dark mt-1">{{ number_format($rework->machine_hours_actual, 2) }} hrs</h5>
                </div>
            </div>
        </div>

        {{-- Operations queue list --}}
        <h6 class="fw-bold text-dark mb-3">Rework Operations Queue</h6>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Seq</th>
                        <th>Operation Name</th>
                        <th>Work Center</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rework->operations as $op)
                        <tr>
                            <td>{{ $op->sequence }}</td>
                            <td class="fw-bold text-dark">{{ $op->name }}</td>
                            <td>{{ $op->workCenter->name ?? '—' }}</td>
                            <td>
                                @php
                                    $opClass = match($op->status) {
                                        'waiting' => 'bg-soft-secondary text-secondary',
                                        'running' => 'bg-soft-primary text-primary',
                                        'completed' => 'bg-soft-success text-success',
                                        default => 'bg-soft-dark text-dark',
                                    };
                                @endphp
                                <span class="badge {{ $opClass }} text-uppercase">{{ $op->status }}</span>
                            </td>
                            <td>
                                @if($op->status === 'waiting')
                                    <form method="POST" action="{{ route('production.quality.rework.ops.start', $op->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-primary">Start Op</button>
                                    </form>
                                @elseif($op->status === 'running')
                                    <form method="POST" action="{{ route('production.quality.rework.ops.complete', $op->id) }}">
                                        @csrf
                                        <div class="d-flex align-items-center gap-1">
                                            <input type="number" step="0.1" name="setup_time_actual" class="form-control form-control-sm" placeholder="Setup mins" style="width: 80px;" required>
                                            <button type="submit" class="btn btn-xs btn-success">Complete Op</button>
                                        </div>
                                    </form>
                                @else
                                    <span class="text-success"><i class="feather-check-circle me-1"></i>Completed</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
