@extends('layouts.duralux')

@section('title', 'Production Schedules | SaaS ERP')
@section('page-title', 'Production Scheduling')
@section('breadcrumb', 'Schedules')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <a href="{{ route('production.schedules.calendar') }}" class="btn btn-light me-2">
        <i class="feather-calendar me-2"></i>Calendar View
    </a>
    <a href="{{ route('production.schedules.work-center-view') }}" class="btn btn-light me-2">
        <i class="feather-grid me-2"></i>Work Center View
    </a>
    <a href="{{ route('production.schedules.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Create Schedule
    </a>
@endsection

@section('content')
    @php
        $totalSchedules  = array_sum($statusCounts);
        $draftCount      = $statusCounts['draft']     ?? 0;
        $scheduledCount  = $statusCounts['scheduled'] ?? 0;
        $releasedCount   = $statusCounts['released']  ?? 0;
        $completedCount  = $statusCounts['completed'] ?? 0;
        $cancelledCount  = $statusCounts['cancelled'] ?? 0;
    @endphp

    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded me-3">
                                <i class="feather-layers"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $totalSchedules }}</div>
                                <div class="fs-11 text-muted text-uppercase">Total</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-secondary text-secondary rounded me-3">
                                <i class="feather-file"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $draftCount }}</div>
                                <div class="fs-11 text-muted text-uppercase">Draft</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-info text-info rounded me-3">
                                <i class="feather-clock"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $scheduledCount }}</div>
                                <div class="fs-11 text-muted text-uppercase">Scheduled</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-warning text-warning rounded me-3">
                                <i class="feather-play-circle"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $releasedCount }}</div>
                                <div class="fs-11 text-muted text-uppercase">Released</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md col-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-text avatar-md bg-soft-success text-success rounded me-3">
                                <i class="feather-check-circle"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $completedCount }}</div>
                                <div class="fs-11 text-muted text-uppercase">Completed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Schedule List</h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.schedules.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Schedule #, Order #, Product..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="draft"     {{ request('status') === 'draft'     ? 'selected' : '' }}>Draft</option>
                                <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                                <option value="released"  {{ request('status') === 'released'  ? 'selected' : '' }}>Released</option>
                                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Scheduling Type</label>
                            <x-ui.odoo-form-ui type="select" name="scheduling_type">
                                <option value="">All Types</option>
                                <option value="forward"  {{ request('scheduling_type') === 'forward'  ? 'selected' : '' }}>Forward</option>
                                <option value="backward" {{ request('scheduling_type') === 'backward' ? 'selected' : '' }}>Backward</option>
                                <option value="manual"   {{ request('scheduling_type') === 'manual'   ? 'selected' : '' }}>Manual</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.schedules.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 15%">Schedule #</th>
                        <th style="width: 15%">Production Order</th>
                        <th style="width: 20%">Product</th>
                        <th style="width: 10%">Type</th>
                        <th style="width: 12%">Status</th>
                        <th style="width: 10%">Planned Start</th>
                        <th style="width: 10%">Planned Finish</th>
                        <th style="width: 8%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $schedule)
                        @php
                            $firstOp = $schedule->operations->first();
                            $lastOp  = $schedule->operations->last();
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('production.schedules.show', $schedule->id) }}" class="fw-bold text-primary hover-primary">
                                    {{ $schedule->schedule_number }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('production.orders.show', $schedule->order_id ?? $schedule->production_order_id) }}" class="text-muted fw-semibold">
                                    {{ $schedule->order->order_number ?? '—' }}
                                </a>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-dark">{{ $schedule->order->product->name ?? '—' }}</span>
                                    <small class="text-muted font-monospace fs-10">{{ $schedule->order->product->sku ?? '' }}</small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-soft-info text-info text-capitalize">{{ $schedule->scheduling_type }}</span>
                            </td>
                            <td>
                                @if($schedule->status === 'released')
                                    <span class="erp-badge-active">Released</span>
                                @elseif($schedule->status === 'scheduled')
                                    <span class="badge bg-soft-info text-info">Scheduled</span>
                                @elseif($schedule->status === 'draft')
                                    <span class="erp-badge-draft">Draft</span>
                                @elseif($schedule->status === 'completed')
                                    <span class="badge bg-soft-success text-success">Completed</span>
                                @elseif($schedule->status === 'cancelled')
                                    <span class="badge bg-soft-danger text-danger">Cancelled</span>
                                @else
                                    <span class="erp-badge-draft text-uppercase">{{ $schedule->status }}</span>
                                @endif
                            </td>
                            <td class="text-muted fs-12">
                                {{ $firstOp ? $firstOp->planned_start->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="text-muted fs-12">
                                {{ $lastOp ? $lastOp->planned_finish->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('production.schedules.show', $schedule->id)">
                                    @if($schedule->isScheduled())
                                        <li>
                                            <form method="POST" action="{{ route('production.schedules.release', $schedule->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success">
                                                    <i class="feather-play-circle me-2 text-success fs-12"></i>Release to Shop Floor
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if(!$schedule->isFrozen())
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $schedule->id }}">
                                                <i class="feather-slash me-2 text-danger fs-12"></i>Cancel Schedule
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('production.schedules.destroy', $schedule->id) }}" onsubmit="return confirm('Delete this schedule permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>

                                {{-- Cancel Modal --}}
                                <x-ui.modal id="cancelModal{{ $schedule->id }}" title="Cancel Schedule" class="text-start">
                                    <form method="POST" action="{{ route('production.schedules.cancel', $schedule->id) }}" id="cancelForm{{ $schedule->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">Cancelling this schedule will stop all planned operations. This cannot be undone.</p>
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('cancelForm{{ $schedule->id }}').submit();">Cancel Schedule</button>
                                    </x-slot>
                                </x-ui.modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="feather-calendar me-2 fs-16"></i>No schedules found. Click "Create Schedule" to generate one from a released Production Order.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        {{-- Pagination --}}
        <x-ui.pagination
            :currentPage="$schedules->currentPage()"
            :totalPages="$schedules->lastPage()"
            :totalResults="$schedules->total()"
            :perPage="$schedules->perPage()"
        />
    </div>
@endsection
