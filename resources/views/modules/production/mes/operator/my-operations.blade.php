@extends('layouts.duralux')

@section('title', 'My Assigned Operations | SaaS ERP')
@section('page-title', 'My Assigned Operations')
@section('breadcrumb', 'My Operations')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('production.mes.operator.dashboard') }}" variant="light" icon="feather-arrow-left" class="border px-3">
            Dashboard
        </x-ui.button>
    </div>
@endsection

@section('content')
    @php
        $hasFilters = request()->hasAny(['search', 'production_order_id', 'operation_status', 'assignment_status']);
    @endphp

    <div class="erp-single-panel">
        <!-- Toolbar: Title, Filter (Matching ERP Single Panel Style) -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-check-square me-2 text-primary"></i> My Assigned Operations
                </h5>
                <span class="badge bg-soft-primary text-primary font-monospace ms-1">
                    {{ $assignments->count() }} {{ Str::plural('Task', $assignments->count()) }}
                </span>
                @if($groupedAssignments->count() > 0)
                    <span class="badge bg-soft-secondary text-secondary font-monospace ms-1">
                        {{ $groupedAssignments->count() }} {{ Str::plural('Order', $groupedAssignments->count()) }}
                    </span>
                @endif
            </div>

            <div class="d-flex align-items-center gap-2 ms-auto">
                <!-- Custom Filter Component (Common ERP Filter) -->
                <form method="GET" action="{{ route('production.mes.operator.my-operations') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3">
                            <i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}
                        </h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Operation name, code, machine..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.production_order') }}</label>
                            <x-ui.odoo-form-ui type="select" name="production_order_id">
                                <option value="">{{ __('production.all_production_orders') }}</option>
                                @foreach($orders as $ord)
                                    <option value="{{ $ord->id }}" {{ request('production_order_id') == $ord->id ? 'selected' : '' }}>
                                        {{ $ord->order_number }}{{ $ord->product ? ' — ' . $ord->product->name : '' }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.operation_status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="operation_status">
                                <option value="">All Operation Statuses</option>
                                <option value="ready" {{ request('operation_status') === 'ready' ? 'selected' : '' }}>{{ __('production.ready') }}</option>
                                <option value="waiting" {{ request('operation_status') === 'waiting' ? 'selected' : '' }}>{{ __('production.waiting') }}</option>
                                <option value="running" {{ request('operation_status') === 'running' ? 'selected' : '' }}>{{ __('production.running') }}</option>
                                <option value="paused" {{ request('operation_status') === 'paused' ? 'selected' : '' }}>{{ __('production.paused') }}</option>
                                <option value="completed" {{ request('operation_status') === 'completed' ? 'selected' : '' }}>{{ __('production.completed_schedules') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Assignment Status</label>
                            <x-ui.odoo-form-ui type="select" name="assignment_status">
                                <option value="">All Assignment Statuses</option>
                                <option value="assigned" {{ request('assignment_status') === 'assigned' ? 'selected' : '' }}>Pending Acceptance</option>
                                <option value="accepted" {{ request('assignment_status') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                                <option value="rejected" {{ request('assignment_status') === 'rejected' ? 'selected' : '' }}>{{ __('production.rejected') }}</option>
                                <option value="completed" {{ request('assignment_status') === 'completed' ? 'selected' : '' }}>{{ __('production.completed_schedules') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <x-ui.button href="{{ route('production.mes.operator.my-operations') }}" variant="light" class="border">
                                {{ __('production.reset') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary">
                                {{ __('production.apply_filters') }}
                            </x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>

                @if($hasFilters)
                    <x-ui.button href="{{ route('production.mes.operator.my-operations') }}" variant="light" icon="feather-x" class="border text-danger">{{ __('production.reset') }}</x-ui.button>
                @endif
            </div>
        </div>

        @if($hasFilters)
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 pb-2 border-bottom">
                <span class="fs-12 text-muted fw-semibold">{{ __('production.active_filters') }}</span>
                @if(request('search'))
                    <span class="badge bg-soft-primary text-primary">Keyword: "{{ request('search') }}"</span>
                @endif
                @if(request('production_order_id'))
                    @php $filterOrder = $orders->firstWhere('id', request('production_order_id')); @endphp
                    <span class="badge bg-soft-primary text-primary">Order: {{ $filterOrder->order_number ?? request('production_order_id') }}</span>
                @endif
                @if(request('operation_status'))
                    <span class="badge bg-soft-info text-info">Operation: {{ ucfirst(request('operation_status')) }}</span>
                @endif
                @if(request('assignment_status'))
                    <span class="badge bg-soft-warning text-warning">Assignment: {{ ucfirst(request('assignment_status')) }}</span>
                @endif
                <a href="{{ route('production.mes.operator.my-operations') }}" class="text-danger fs-12 fw-semibold ms-1">Clear all</a>
            </div>
        @endif

        <!-- Operations Grouped by Production Order (Card Based, In Strict Sequence) -->
        <div class="d-flex flex-column gap-3">
            @forelse($groupedAssignments as $orderId => $orderAssignments)
                @php
                    $firstAssign = $orderAssignments->first();
                    $order = $firstAssign->operation->order ?? null;
                    $orderProduct = $order->product ?? null;
                    $orderStatus = strtolower($order->status ?? 'unknown');
                    $orderStatusClass = match($orderStatus) {
                        'in_progress', 'running' => 'bg-soft-success text-success',
                        'released' => 'bg-soft-info text-info',
                        'completed' => 'bg-soft-secondary text-secondary',
                        default => 'bg-soft-primary text-primary',
                    };
                @endphp
                <div class="card border border-light shadow-sm overflow-hidden mb-1">
                    <!-- Production Order Header -->
                    <div class="card-header bg-light py-2.5 px-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="feather-package fs-14"></i>
                            </div>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <h6 class="fw-bold text-dark mb-0 fs-14">
                                        Order: {{ $order->order_number ?? 'General Floor Tasks' }}
                                    </h6>
                                    @if($order)
                                        <span class="badge {{ $orderStatusClass }} font-monospace fs-10 text-uppercase">
                                            {{ str_replace('_', ' ', $order->status) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-muted fs-12 mt-0.5 d-flex flex-wrap align-items-center gap-1">
                                    @if($orderProduct)
                                        <span>Product: <strong class="text-dark">{{ $orderProduct->name }}</strong></span>
                                    @endif
                                    @if($order && $order->quantity)
                                        <span class="mx-1 text-muted opacity-50">•</span>
                                        <span>Qty: <strong>{{ number_format($order->quantity, 0) }}</strong></span>
                                    @endif
                                    @if($order && ($order->planned_end_date || $order->planned_start_date))
                                        <span class="mx-1 text-muted opacity-50">•</span>
                                        <span>Due: <strong>{{ \Carbon\Carbon::parse($order->planned_end_date ?? $order->planned_start_date)->format('d M Y') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-white text-secondary border px-2.5 py-1.5 fs-11 font-monospace">
                                <i class="feather-layers me-1 text-primary"></i> 
                                {{ $orderAssignments->count() }} {{ Str::plural('Operation', $orderAssignments->count()) }} (In Sequence)
                            </span>
                        </div>
                    </div>

                    <!-- Sequential Operation Cards for this Order -->
                    <div class="card-body p-3 bg-light-subtle">
                        <div class="d-flex flex-column gap-2.5">
                            @foreach($orderAssignments as $assign)
                                @php
                                    $opStatus = strtolower($assign->operation->status ?? 'waiting');
                                    $opStatusBadge = match($opStatus) {
                                        'running' => ['class' => 'bg-soft-success text-success border border-success-subtle', 'icon' => 'feather-play-circle'],
                                        'paused' => ['class' => 'bg-soft-warning text-warning border border-warning-subtle', 'icon' => 'feather-pause-circle'],
                                        'completed' => ['class' => 'bg-soft-secondary text-secondary border border-secondary-subtle', 'icon' => 'feather-check-circle'],
                                        'ready' => ['class' => 'bg-soft-primary text-primary border border-primary-subtle', 'icon' => 'feather-arrow-right-circle'],
                                        'waiting' => ['class' => 'bg-soft-warning text-warning border border-warning-subtle fw-semibold', 'icon' => 'feather-clock'],
                                        default => ['class' => 'bg-soft-primary text-primary border border-primary-subtle', 'icon' => 'feather-clock'],
                                    };
                                    $avatarClass = match($opStatus) {
                                        'running' => 'bg-soft-success text-success',
                                        'paused' => 'bg-soft-warning text-warning',
                                        'completed' => 'bg-soft-secondary text-secondary',
                                        'waiting' => 'bg-soft-warning text-warning',
                                        default => 'bg-soft-primary text-primary',
                                    };
                                @endphp
                                <div class="card border border-light bg-white shadow-xs mb-2">
                                    <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                        <!-- Left Side: Avatar, Name & Details -->
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-text avatar-md {{ $avatarClass }} rounded me-3 d-flex align-items-center justify-content-center flex-shrink-0">
                                                <i class="{{ $opStatusBadge['icon'] }} fs-18"></i>
                                            </div>
                                            <div>
                                                <div class="d-flex flex-wrap align-items-center gap-2">
                                                    @if($assign->operation->sequence)
                                                        <span class="badge bg-soft-secondary text-dark font-monospace fw-bold fs-11">
                                                            #{{ $assign->operation->sequence }}
                                                        </span>
                                                    @endif
                                                    <h5 class="fw-bold text-dark mb-0 fs-14">{{ $assign->operation->name ?? '—' }}</h5>
                                                    @if(!empty($assign->operation->operation_number))
                                                        <span class="badge bg-soft-secondary font-monospace fs-10">{{ $assign->operation->operation_number }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-muted fs-12 mt-1 d-flex flex-wrap align-items-center gap-1">
                                                    <span>WC: <strong class="text-dark">{{ $assign->operation->workCenter->name ?? '—' }}</strong></span>
                                                    @if($assign->operation->machine)
                                                        <span class="text-muted opacity-50 mx-1">|</span>
                                                        <span>Machine: <strong class="text-dark">{{ $assign->operation->machine->name }}</strong></span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Side: Fixed Position Status Badges & Action Buttons -->
                                        <div class="d-flex align-items-center gap-3 ms-md-auto flex-wrap">
                                            <!-- Operation Status (Fixed Column / Position) -->
                                            <div class="text-start" style="min-width: 110px;">
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.operation') }}</span>
                                                <span class="badge {{ $opStatusBadge['class'] }} font-monospace text-uppercase fs-11 px-2.5 py-1.5 d-inline-flex align-items-center gap-1">
                                                    <i class="{{ $opStatusBadge['icon'] }}"></i> {{ $opStatus }}
                                                </span>
                                            </div>

                                            <!-- Assignment Status (Fixed Column / Position) -->
                                            <div class="text-start" style="min-width: 140px;">
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-1">Assignment</span>
                                                @if($assign->status === 'assigned')
                                                    <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2.5 py-1.5 d-inline-flex align-items-center gap-1 fs-11 fw-semibold">
                                                        <i class="feather-clock"></i> Pending Acceptance
                                                    </span>
                                                @elseif($assign->status === 'accepted')
                                                    <span class="badge bg-soft-success text-success border border-success-subtle px-2.5 py-1.5 d-inline-flex align-items-center gap-1 fs-11 fw-semibold">
                                                        <i class="feather-check-circle"></i> Accepted
                                                    </span>
                                                @elseif($assign->status === 'rejected')
                                                    <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2.5 py-1.5 d-inline-flex align-items-center gap-1 fs-11 fw-bold">
                                                        <i class="feather-x-circle"></i>{{ __('production.rejected') }}</span>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary border border-secondary-subtle px-2.5 py-1.5 d-inline-flex align-items-center gap-1 fs-11">
                                                        <i class="feather-check"></i>{{ __('production.completed_schedules') }}</span>
                                                @endif
                                            </div>

                                            <!-- Action Buttons (Fixed Position) -->
                                            <div class="d-flex gap-2" style="min-width: 110px; justify-content: flex-end;">
                                                @if($assign->status === 'assigned')
                                                    <form method="POST" action="{{ route('production.mes.assignments.accept', $assign->id) }}">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="success" icon="feather-check" class="px-3 py-1 fs-12">Accept</x-ui.button>
                                                    </form>
                                                    <form method="POST" action="{{ route('production.mes.assignments.reject', $assign->id) }}">
                                                        @csrf
                                                        <x-ui.button type="submit" variant="outline-danger" title="Reject" class="px-2.5 py-1 fs-12"><i class="feather-x"></i></x-ui.button>
                                                    </form>
                                                @elseif($assign->status === 'accepted')
                                                    @if($assign->operation && $assign->operation->status === 'completed')
                                                        <x-ui.button href="{{ route('production.mes.operator.execution', $assign->operation->id) }}" variant="secondary" icon="feather-eye" class="px-3 py-1 fs-12">{{ __('production.view') }}</x-ui.button>
                                                    @else
                                                        <x-ui.button href="{{ route('production.mes.operator.execution', $assign->operation->id) }}" variant="primary" icon="feather-play" class="px-3 py-1 fs-12">
                                                            Execute
                                                        </x-ui.button>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border border-light shadow-sm">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="feather-info fs-36 d-block mb-3 text-primary opacity-50"></i>
                        <h6 class="fw-bold text-dark mb-1">No Assigned Operations Found</h6>
                        <p class="fs-13 text-muted mb-3">
                            @if($hasFilters)
                                No operations match your selected filter criteria. Try clearing or adjusting filters.
                            @else
                                You do not currently have any assigned manufacturing operations.
                            @endif
                        </p>
                        @if($hasFilters)
                            <x-ui.button href="{{ route('production.mes.operator.my-operations') }}" variant="light" class="border">{{ __('production.clear_filters') }}</x-ui.button>
                        @endif
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endsection
