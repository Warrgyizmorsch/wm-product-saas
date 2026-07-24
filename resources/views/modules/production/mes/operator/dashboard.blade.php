@extends('layouts.duralux')

@section('title', 'MES Operator Console | SaaS ERP')
@section('page-title', 'MES Operator Console')
@section('breadcrumb', 'Operator Console')
@push('styles')
    <style>
        .touch-card {
            border-radius: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .touch-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        .status-ribbon {
            position: absolute;
            top: 20px;
            right: -32px;
            width: 120px;
            padding: 3px 0;
            text-align: center;
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            color: #fff;
            transform: rotate(45deg);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
            z-index: 10;
            letter-spacing: 0.5px;
        }
        .status-ribbon-running {
            background: linear-gradient(135deg, #25D366, #128C7E);
        }
        .status-ribbon-paused {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
        }
        .status-ribbon-completed {
            background: linear-gradient(135deg, #6c757d, #495057);
        }
    </style>
@endpush

@section('page-actions')
    <a href="{{ route('production.mes.scanner.index') }}" class="btn btn-primary">
        <i class="feather-camera me-2"></i> Scan Barcode
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Metrics Summary --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm touch-card bg-soft-primary">
                    <div class="card-body py-4 text-center">
                        <div class="fs-24 fw-bold text-primary">{{ $myAssignments->where('status', 'assigned')->count() }}</div>
                        <div class="fs-12 text-muted text-uppercase fw-semibold mt-1">New Assignments</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm touch-card bg-soft-warning">
                    <div class="card-body py-4 text-center">
                        <div class="fs-24 fw-bold text-warning">{{ $running->count() }}</div>
                        <div class="fs-12 text-muted text-uppercase fw-semibold mt-1">Running Floor</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm touch-card bg-soft-info">
                    <div class="card-body py-4 text-center">
                        <div class="fs-24 fw-bold text-info">{{ $ready->count() }}</div>
                        <div class="fs-12 text-muted text-uppercase fw-semibold mt-1">Ready Queue</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm touch-card bg-soft-success">
                    <div class="card-body py-4 text-center">
                        <div class="fs-24 fw-bold text-success">{{ $completedToday }}</div>
                        <div class="fs-12 text-muted text-uppercase fw-semibold mt-1">Completed Today</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Sections --}}
        <div class="row g-4">
            {{-- Operator Assignments --}}
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-user me-2 text-primary"></i> My Assigned Operations</h5>
                        <a href="{{ route('production.mes.operator.my-operations') }}" class="btn btn-sm btn-light border">View All</a>
                    </div>
                    <div class="card-body pt-3">
                        <div class="row g-3">
                            @forelse($myAssignments->take(6) as $assign)
                                @php
                                    $opStatus = $assign->operation->status ?? 'waiting';
                                    $opStatusClass = match($opStatus) {
                                        'running' => 'bg-soft-success text-success',
                                        'paused' => 'bg-soft-warning text-warning',
                                        'completed' => 'bg-soft-secondary text-secondary',
                                        default => 'bg-soft-primary text-primary',
                                    };
                                @endphp
                                <div class="col-md-6">
                                    <div class="card border border-light shadow-sm touch-card h-100">
                                        @if(in_array($opStatus, ['completed', 'running', 'paused']))
                                            <div class="status-ribbon status-ribbon-{{ $opStatus }}">
                                                {{ $opStatus }}
                                            </div>
                                        @endif
                                        <div class="card-body p-3 d-flex flex-column justify-content-around">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <span class="badge bg-soft-secondary font-monospace text-secondary">{{ $assign->operation->operation_number ?? 'OP-??' }}</span>
                                                    @if(!in_array($opStatus, ['completed', 'running', 'paused']))
                                                        <span class="badge {{ $opStatusClass }} fs-9">{{ strtoupper($opStatus) }}</span>
                                                    @endif
                                                    
                                                    @if($assign->status === 'assigned')
                                                        <span class="badge bg-soft-primary text-primary fs-9">Assigned</span>
                                                    @elseif($assign->status === 'accepted')
                                                        <span class="badge bg-soft-success text-success fs-9">Accepted</span>
                                                    @elseif($assign->status === 'rejected')
                                                        <span class="badge bg-soft-danger text-danger fs-9">Rejected</span>
                                                    @else
                                                        <span class="badge bg-soft-secondary text-secondary fs-9">Completed</span>
                                                    @endif
                                                </div>
                                                <h6 class="fw-bold text-dark mb-1">{{ $assign->operation->name ?? '—' }}</h6>
                                                <p class="text-muted fs-12 mb-2">Order: <strong>{{ $assign->operation->order->order_number ?? '—' }}</strong></p>
                                                <div class="fs-11 text-muted"><i class="feather-map-pin me-1"></i> {{ $assign->operation->workCenter->name ?? '—' }}</div>
                                            </div>

                                            <div class="mt-1 pt-1 border-top d-flex gap-2 justify-content-center">
                                                @if($assign->status === 'assigned')
                                                    <form method="POST" action="{{ route('production.mes.assignments.accept', $assign->id) }}" class="flex-fill">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="success" size="sm" icon="feather-check" class="w-100 btn-touch">Accept</x-ui.button>
                                                    </form>
                                                    <form method="POST" action="{{ route('production.mes.assignments.reject', $assign->id) }}" class="flex-fill">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="outline-danger" size="sm" icon="feather-x" class="w-100 btn-touch">Reject</x-ui.button>
                                                    </form>
                                                @elseif($assign->status === 'accepted')
                                                    @if($assign->operation && $assign->operation->status === 'completed')
                                                        <x-ui.button href="{{ route('production.mes.operator.execution', $assign->operation->id) }}" variant="secondary" size="sm" icon="feather-eye" class="">
                                                            View
                                                        </x-ui.button>
                                                    @else
                                                        <x-ui.button href="{{ route('production.mes.operator.execution', $assign->operation->id) }}" variant="primary" size="sm" icon="feather-play" class="">
                                                            Go to Execution
                                                        </x-ui.button>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-center py-5 text-muted">
                                    <i class="feather-info fs-32 d-block mb-2"></i>
                                    No active operator assignments found.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Floor Ready Queue --}}
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 pt-4 pb-0">
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-list me-2 text-info"></i> Ready Queue</h5>
                    </div>
                    <div class="card-body pt-3">
                        <div class="list-group list-group-flush">
                            @forelse($ready->take(5) as $r)
                                <div class="list-group-item px-0 py-3 border-bottom d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold text-dark fs-14">{{ $r->orderOperation->name ?? '—' }}</div>
                                        <small class="text-muted">Order: <strong>{{ $r->schedule->order->order_number ?? '—' }}</strong> | WC: {{ $r->workCenter->name ?? '—' }}</small>
                                    </div>
                                    <x-ui.button href="{{ route('production.mes.operator.execution', $r->production_order_operation_id) }}" variant="light" size="sm" icon="feather-arrow-right" class="btn-touch border"></x-ui.button>
                                </div>
                            @empty
                                <div class="text-center py-5 text-muted">
                                    <i class="feather-check-circle fs-32 d-block mb-2 text-success"></i>
                                    Ready queue is empty.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
