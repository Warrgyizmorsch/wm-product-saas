@extends('layouts.duralux')

@section('title', 'My Assigned Operations | SaaS ERP')
@section('page-title', 'My Assigned Operations')
@section('breadcrumb', 'My Operations')

@push('styles')
    <style>
        .touch-list-card {
            border-radius: 10px;
            margin-bottom: 12px;
            transition: box-shadow 0.2s;
        }
        .touch-list-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }
        .btn-touch {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
@endpush

@section('page-actions')
    <a href="{{ route('production.mes.operator.dashboard') }}" class="btn btn-touch btn-light border px-3">
        <i class="feather-arrow-left me-2"></i> Dashboard
    </a>
@endsection

@section('content')
    <div class="container-fluid py-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    @forelse($assignments as $assign)
                        <div class="col-12">
                            <div class="card border border-light touch-list-card">
                                <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                                    <div class="d-flex align-items-center mb-3 mb-md-0">
                                        <div class="avatar-text avatar-md bg-soft-primary text-primary rounded me-3">
                                            <i class="feather-activity"></i>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2">
                                                <h5 class="fw-bold text-dark mb-0">{{ $assign->operation->name ?? '—' }}</h5>
                                                <span class="badge bg-soft-secondary font-monospace">{{ $assign->operation->operation_number ?? '' }}</span>
                                            </div>
                                            <div class="text-muted fs-13 mt-1">
                                                Order: <strong>{{ $assign->operation->order->order_number ?? '—' }}</strong> 
                                                | WC: {{ $assign->operation->workCenter->name ?? '—' }}
                                                @if($assign->operation->machine)
                                                    | Machine: <strong>{{ $assign->operation->machine->name }}</strong>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 align-items-center">
                                        <div>
                                            @if($assign->status === 'assigned')
                                                <span class="badge bg-soft-primary text-primary px-3 py-2">Assigned</span>
                                            @elseif($assign->status === 'accepted')
                                                <span class="badge bg-soft-success text-success px-3 py-2">Accepted</span>
                                            @elseif($assign->status === 'rejected')
                                                <span class="badge bg-soft-danger text-danger px-3 py-2">Rejected</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary px-3 py-2">Completed</span>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-2 ms-3">
                                            @if($assign->status === 'assigned')
                                                <form method="POST" action="{{ route('production.mes.assignments.accept', $assign->id) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-touch btn-success px-4"><i class="feather-check me-1"></i> Accept</button>
                                                </form>
                                                <form method="POST" action="{{ route('production.mes.assignments.reject', $assign->id) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-touch btn-outline-danger px-3"><i class="feather-x"></i></button>
                                                </form>
                                            @elseif($assign->status === 'accepted')
                                                <a href="{{ route('production.mes.operator.execution', $assign->operation->id) }}" class="btn btn-touch btn-primary px-4">
                                                    <i class="feather-play me-1"></i> Execute
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-5 text-muted">
                            <i class="feather-info fs-32 d-block mb-2"></i>
                            No assigned operations found.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
