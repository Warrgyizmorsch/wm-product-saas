@extends('layouts.duralux')

@section('title', __('production.production_scheduling') . ' | SaaS ERP')
@section('page-title', __('production.production_scheduling'))
@section('breadcrumb', __('production.schedules'))

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
        <i class="feather-calendar me-2"></i>{{ __('production.calendar_view') }}
    </a>
    <a href="{{ route('production.schedules.work-center-view') }}" class="btn btn-light me-2">
        <i class="feather-grid me-2"></i>{{ __('production.work_center_view') }}
    </a>
    <a href="{{ route('production.schedules.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>{{ __('production.create_schedule') }}
    </a>
@endsection

@section('content')
    @php
        $totalSchedules  = array_sum($statusCounts);
        $draftCount      = $statusCounts['draft']       ?? 0;
        $scheduledCount  = $statusCounts['scheduled']   ?? 0;
        $releasedCount   = $statusCounts['released']    ?? 0;
        $inProgressCount = $statusCounts['in_progress'] ?? 0;
        $completedCount  = $statusCounts['completed']   ?? 0;
        $cancelledCount  = $statusCounts['cancelled']   ?? 0;
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
                                <div class="fs-11 text-muted text-uppercase">{{ __('production.total') }}</div>
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
                                <div class="fs-11 text-muted text-uppercase">{{ __('production.draft_schedules') }}</div>
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
                                <div class="fs-11 text-muted text-uppercase">{{ __('production.scheduled_schedules') }}</div>
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
                                <div class="fs-11 text-muted text-uppercase">{{ __('production.released_schedules') }}</div>
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
                                <i class="feather-activity"></i>
                            </div>
                            <div>
                                <div class="fs-18 fw-bold text-dark">{{ $inProgressCount }}</div>
                                <div class="fs-11 text-muted text-uppercase">{{ __('production.in_progress_schedules') }}</div>
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
                                <div class="fs-11 text-muted text-uppercase">{{ __('production.completed_schedules') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('production.schedule_list') }}</h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.schedules.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('production.schedule_number') }}, {{ __('production.production_order') }}, {{ __('production.product') }}..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('production.all_statuses') }}</option>
                                <option value="draft"     {{ request('status') === 'draft'     ? 'selected' : '' }}>{{ __('production.draft_schedules') }}</option>
                                <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>{{ __('production.scheduled_schedules') }}</option>
                                <option value="released"    {{ request('status') === 'released'    ? 'selected' : '' }}>{{ __('production.released_schedules') }}</option>
                                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('production.in_progress_schedules') }}</option>
                                <option value="completed"   {{ request('status') === 'completed'   ? 'selected' : '' }}>{{ __('production.completed_schedules') }}</option>
                                <option value="cancelled"   {{ request('status') === 'cancelled'   ? 'selected' : '' }}>{{ __('production.cancelled_schedules') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.scheduling_type') }}</label>
                            <x-ui.odoo-form-ui type="select" name="scheduling_type">
                                <option value="">{{ __('production.all_types') }}</option>
                                <option value="forward"  {{ request('scheduling_type') === 'forward'  ? 'selected' : '' }}>{{ __('production.forward') }}</option>
                                <option value="backward" {{ request('scheduling_type') === 'backward' ? 'selected' : '' }}>{{ __('production.backward') }}</option>
                                <option value="manual"   {{ request('scheduling_type') === 'manual'   ? 'selected' : '' }}>{{ __('production.manual') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.schedules.index') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
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
                        <th style="width: 15%">{{ __('production.schedule_number') }}</th>
                        <th style="width: 15%">{{ __('production.production_order') }}</th>
                        <th style="width: 20%">{{ __('production.product') }}</th>
                        <th style="width: 10%">{{ __('production.type') }}</th>
                        <th style="width: 12%">{{ __('production.status') }}</th>
                        <th style="width: 10%">{{ __('production.planned_start') }}</th>
                        <th style="width: 10%">{{ __('production.planned_finish') }}</th>
                        <th style="width: 8%" class="text-end">{{ __('production.actions') }}</th>
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
                                <span class="badge bg-soft-info text-info text-capitalize">
                                    {{ __('production.' . $schedule->scheduling_type) ?? $schedule->scheduling_type }}
                                </span>
                            </td>
                            <td>
                                @if($schedule->status === 'released')
                                    <span class="erp-badge-active">{{ __('production.released_schedules') }}</span>
                                @elseif($schedule->status === 'in_progress')
                                    <span class="badge bg-soft-warning text-warning">{{ __('production.in_progress_schedules') }}</span>
                                @elseif($schedule->status === 'scheduled')
                                    <span class="badge bg-soft-info text-info">{{ __('production.scheduled_schedules') }}</span>
                                @elseif($schedule->status === 'draft')
                                    <span class="erp-badge-draft">{{ __('production.draft_schedules') }}</span>
                                @elseif($schedule->status === 'completed')
                                    <span class="badge bg-soft-success text-success">{{ __('production.completed_schedules') }}</span>
                                @elseif($schedule->status === 'cancelled')
                                    <span class="badge bg-soft-danger text-danger">{{ __('production.cancelled_schedules') }}</span>
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
                                                    <i class="feather-play-circle me-2 text-success fs-12"></i>{{ __('production.release_to_shop_floor') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if(!$schedule->isFrozen())
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $schedule->id }}">
                                                <i class="feather-slash me-2 text-danger fs-12"></i>{{ __('production.cancel_schedule') }}
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('production.schedules.destroy', $schedule->id) }}" onsubmit="return confirm('{{ __('production.confirm_delete_plan') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>

                                {{-- Cancel Modal --}}
                                <x-ui.modal id="cancelModal{{ $schedule->id }}" :title="__('production.cancel_schedule')" class="text-start">
                                    <form method="POST" action="{{ route('production.schedules.cancel', $schedule->id) }}" id="cancelForm{{ $schedule->id }}">
                                        @csrf
                                        <p class="fs-13 text-muted">{{ __('production.cancel_schedule_warning') }}</p>
                                    </form>
                                    <x-slot name="footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('production.back') ?? 'Back' }}</button>
                                        <button type="submit" class="btn btn-danger" onclick="document.getElementById('cancelForm{{ $schedule->id }}').submit();">{{ __('production.cancel_schedule') }}</button>
                                    </x-slot>
                                </x-ui.modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="feather-calendar me-2 fs-16"></i>{{ __('production.no_schedules_found') }}
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
