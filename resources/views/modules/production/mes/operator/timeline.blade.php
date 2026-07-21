@extends('layouts.duralux')

@section('title', 'Central Event Timeline | SaaS ERP')
@section('page-title', 'Central Production Event Timeline')
@section('breadcrumb', 'Event Timeline')

@section('content')
<div class="erp-single-panel bg-white p-4">
    <!-- Toolbar: Sort, Filters -->
    <div class="d-flex align-items-center mb-4">
        <h5 class="fw-bold text-dark mb-0">
            <i class="feather-activity me-2"></i>Manufacturing Audit Logs Stream
        </h5>
        <div class="d-flex gap-2 ms-auto">
            <!-- Custom Filter Component -->
            <form action="{{ route('production.mes.timeline.index') }}" method="GET" class="d-inline">
                <x-ui.filter label="Filter" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Machine</label>
                        <x-ui.odoo-form-ui type="select" name="machine_id">
                            <option value="">All Machines</option>
                            @foreach($machines as $machine)
                                <option value="{{ $machine->id }}" {{ request('machine_id') == $machine->id ? 'selected' : '' }}>{{ $machine->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Production Order</label>
                        <x-ui.odoo-form-ui type="select" name="production_order_id">
                            <option value="">All Orders</option>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" {{ request('production_order_id') == $order->id ? 'selected' : '' }}>{{ $order->order_number }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Operator</label>
                        <x-ui.odoo-form-ui type="select" name="operator_id">
                            <option value="">All Operators</option>
                            @foreach($operators as $operator)
                                <option value="{{ $operator->id }}" {{ request('operator_id') == $operator->id ? 'selected' : '' }}>{{ $operator->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Severity</label>
                        <x-ui.odoo-form-ui type="select" name="severity">
                            <option value="">All Severities</option>
                            <option value="info" {{ request('severity') === 'info' ? 'selected' : '' }}>Info</option>
                            <option value="success" {{ request('severity') === 'success' ? 'selected' : '' }}>Success</option>
                            <option value="warning" {{ request('severity') === 'warning' ? 'selected' : '' }}>Warning</option>
                            <option value="critical" {{ request('severity') === 'critical' ? 'selected' : '' }}>Critical</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Event Source</label>
                        <x-ui.odoo-form-ui type="select" name="event_source">
                            <option value="">All Sources</option>
                            <option value="ProductionOrderService" {{ request('event_source') === 'ProductionOrderService' ? 'selected' : '' }}>ProductionOrderService</option>
                            <option value="SchedulingService" {{ request('event_source') === 'SchedulingService' ? 'selected' : '' }}>SchedulingService</option>
                            <option value="MesExecutionService" {{ request('event_source') === 'MesExecutionService' ? 'selected' : '' }}>MesExecutionService</option>
                            <option value="BatchProductionService" {{ request('event_source') === 'BatchProductionService' ? 'selected' : '' }}>BatchProductionService</option>
                            <option value="SerialNumberService" {{ request('event_source') === 'SerialNumberService' ? 'selected' : '' }}>SerialNumberService</option>
                            <option value="MachineStateService" {{ request('event_source') === 'MachineStateService' ? 'selected' : '' }}>MachineStateService</option>
                            <option value="DowntimeService" {{ request('event_source') === 'DowntimeService' ? 'selected' : '' }}>DowntimeService</option>
                            <option value="System" {{ request('event_source') === 'System' ? 'selected' : '' }}>System</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Batch Code</label>
                        <x-ui.odoo-form-ui type="input" name="batch_code" value="{{ request('batch_code') }}" placeholder="Search batch number..." />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Serial Code</label>
                        <x-ui.odoo-form-ui type="input" name="serial_code" value="{{ request('serial_code') }}" placeholder="Search serial number..." />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Event Date</label>
                        <x-ui.odoo-form-ui type="input" inputType="date" name="date" value="{{ request('date') }}" />
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('production.mes.timeline.index') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

    @if($events->count() > 0)
        <div class="position-relative ps-4 border-start border-2 border-light ms-3">
            @foreach($events as $event)
                @php
                    $sevColor = match($event->severity) {
                        'success' => 'bg-success text-white',
                        'warning' => 'bg-warning text-dark',
                        'critical' => 'bg-danger text-white',
                        default => 'bg-primary text-white',
                    };
                    $icon = match($event->severity) {
                        'success' => 'feather-check',
                        'warning' => 'feather-alert-triangle',
                        'critical' => 'feather-alert-circle',
                        default => 'feather-info',
                    };
                @endphp
                <div class="mb-4 position-relative">
                    {{-- Timeline Dot --}}
                    <div class="position-absolute rounded-circle d-flex align-items-center justify-content-center {{ $sevColor }}" 
                         style="width: 28px; height: 28px; left: -45px; top: 0px; box-shadow: 0 0 0 4px #fff;">
                        <i class="{{ $icon }} fs-12"></i>
                    </div>

                    {{-- Event Card --}}
                    <div class="card border shadow-sm mb-0">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-soft-secondary text-secondary fs-10 font-bold text-uppercase">{{ $event->event_type }}</span>
                                        <span class="badge {{ $event->severity === 'success' ? 'bg-soft-success text-success' : ($event->severity === 'warning' ? 'bg-soft-warning text-warning' : ($event->severity === 'critical' ? 'bg-soft-danger text-danger' : 'bg-soft-primary text-primary')) }} fs-10 font-bold text-uppercase">{{ $event->severity }}</span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1 fs-13">{{ $event->title }}</h6>
                                    <p class="text-muted fs-12 mb-2">{{ $event->description }}</p>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block fs-11 fw-semibold"><i class="feather-clock me-1"></i>{{ $event->event_time->format('d/m/Y H:i:s') }}</small>
                                    <span class="badge bg-soft-info text-info fs-10 mt-1">Source: {{ $event->event_source }}</span>
                                </div>
                            </div>

                            {{-- Links / Meta section --}}
                            <div class="d-flex flex-wrap gap-3 mt-2 pt-2 border-top border-light fs-11 text-muted">
                                @if($event->order)
                                    <div>
                                        <i class="feather-hash me-1"></i>Order: <strong class="text-dark">{{ $event->order->order_number }}</strong>
                                    </div>
                                @endif
                                @if($event->operation)
                                    <div>
                                        <i class="feather-play me-1"></i>Operation: <strong class="text-dark">{{ $event->operation->name }}</strong>
                                    </div>
                                @endif
                                @if($event->machine)
                                    <div>
                                        <i class="feather-cpu me-1"></i>Machine: <strong class="text-dark">{{ $event->machine->name }}</strong>
                                    </div>
                                @endif
                                @if($event->batch)
                                    <div>
                                        <i class="feather-package me-1"></i>Batch: <strong class="text-dark">{{ $event->batch->batch_number }}</strong>
                                    </div>
                                @endif
                                @if($event->serialNumber)
                                    <div>
                                        <i class="feather-tag me-1"></i>Serial: <strong class="text-dark">{{ $event->serialNumber->serial_number }}</strong>
                                    </div>
                                @endif
                                @if($event->operator)
                                    <div>
                                        <i class="feather-user me-1"></i>Operator: <strong class="text-dark">{{ $event->operator->name }}</strong>
                                    </div>
                                @elseif($event->triggerUser)
                                    <div>
                                        <i class="feather-user me-1"></i>By: <strong class="text-dark">{{ $event->triggerUser->name }}</strong>
                                    </div>
                                @endif
                            </div>

                            @if($event->metadata)
                                <div class="mt-2 p-2 bg-light rounded text-monospace fs-11" style="max-height: 100px; overflow-y: auto;">
                                    <pre class="mb-0 text-muted">{{ json_encode($event->metadata, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination Links --}}
        <div class="mt-4">
            {{ $events->links() }}
        </div>
    @else
        <div class="text-center py-5 text-muted border rounded">
            <i class="feather-activity fs-36 mb-3 d-block"></i>
            <p class="fs-13 mb-0">No manufacturing events found matching the selection criteria.</p>
        </div>
    @endif
</div>
@endsection
