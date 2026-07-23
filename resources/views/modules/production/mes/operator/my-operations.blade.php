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
        @media (max-width: 576px) {
            .btn-touch {
                min-height: 38px !important;
                font-size: 12px !important;
                padding: 0.25rem 0.5rem !important;
                border-radius: 6px !important;
            }
        }
    </style>
@endpush

@section('page-actions')
    <x-ui.button href="{{ route('production.mes.operator.dashboard') }}" variant="light" icon="feather-arrow-left" class="btn-touch border px-3">
        Dashboard
    </x-ui.button>
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
                                        <div class="d-flex flex-column align-items-end gap-1">
                                            <div>
                                                @php
                                                    $opStatus = $assign->operation->status ?? 'waiting';
                                                    $opStatusClass = match($opStatus) {
                                                        'running' => 'bg-soft-success text-success',
                                                        'paused' => 'bg-soft-warning text-warning',
                                                        'completed' => 'bg-soft-secondary text-secondary',
                                                        default => 'bg-soft-primary text-primary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $opStatusClass }} px-3 py-2 font-monospace">{{ strtoupper($opStatus) }}</span>
                                            </div>
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
                                        </div>
                                        <div class="d-flex gap-2 ms-3">
                                            @if($assign->status === 'assigned')
                                                <form method="POST" action="{{ route('production.mes.assignments.accept', $assign->id) }}">
                                                    @csrf
                                                    <x-ui.button type="submit" variant="success" icon="feather-check" class="btn-touch px-4">Accept</x-ui.button>
                                                </form>
                                                <form method="POST" action="{{ route('production.mes.assignments.reject', $assign->id) }}">
                                                    @csrf
                                                    <x-ui.button type="submit" variant="outline-danger" icon="feather-x" class="btn-touch px-3"></x-ui.button>
                                                </form>
                                            @elseif($assign->status === 'accepted')
                                                @if($assign->operation && $assign->operation->status === 'completed')
                                                    <x-ui.button href="{{ route('production.mes.operator.execution', $assign->operation->id) }}" variant="secondary" icon="feather-eye" class="px-4">
                                                        View
                                                    </x-ui.button>
                                                @else
                                                    <x-ui.button href="{{ route('production.mes.operator.execution', $assign->operation->id) }}" variant="primary" icon="feather-play" class="px-4">
                                                        Execute
                                                    </x-ui.button>
                                                @endif
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
