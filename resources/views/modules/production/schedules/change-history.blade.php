@extends('layouts.duralux')

@section('title', 'Schedule Change History | SaaS ERP')

@section('page-back-button')
    <x-ui.icon-btn href="{{ route('production.schedules.show', $schedule->id) }}" icon="feather-arrow-left" variant="transparent-dark" title="Back to Schedule Details" />
@endsection

@section('page-actions')
    <a href="{{ route('production.schedules.dispatch-board', ['schedule_id' => $schedule->id]) }}" class="btn btn-sm btn-primary me-2">
        <i class="feather-grid me-1"></i> Dispatch Board
    </a>
    <a href="{{ route('production.schedules.show', $schedule->id) }}" class="btn btn-sm btn-outline-secondary">
        <i class="feather-arrow-left me-1"></i> Schedule Details
    </a>
@endsection

@section('content')
    {{-- Workflow Guide Component placed ABOVE erp-single-panel --}}
    <x-ui.workflow-guide title="Schedule Change Audit Log">
        Review planner adjustment history for Schedule [{{ $schedule->schedule_number }}]. Tracks all manual timing shifts, machine reassignments, ripple propagation, and shift mode reasons.
    </x-ui.workflow-guide>

    <div class="erp-single-panel">
        <x-ui.odoo-form-ui type="sheet">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                        <i class="feather-clock text-primary"></i> Schedule Change History
                    </h4>
                    <p class="text-muted fs-13 mb-0">
                        Schedule <span class="font-monospace fw-bold text-primary">#{{ $schedule->schedule_number }}</span> | Production Order: <strong>{{ $schedule->order->order_number ?? 'N/A' }}</strong> ({{ $schedule->order->product->name ?? 'N/A' }})
                    </p>
                </div>
            </div>

            <x-ui.odoo-form-ui type="table">
                <thead class="table-light fs-12">
                    <tr>
                        <th style="width: 15%">Timestamp / User</th>
                        <th style="width: 18%">Operation</th>
                        <th style="width: 12%">Change Type</th>
                        <th style="width: 10%">Shift Mode</th>
                        <th style="width: 20%">Original Timings</th>
                        <th style="width: 20%">New Timings</th>
                        <th style="width: 15%">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="fs-12">
                                <span class="fw-bold text-dark">{{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '—' }}</span>
                                <br><small class="text-muted"><i class="feather-user me-1"></i>{{ $log->changedBy->name ?? 'System' }}</small>
                            </td>
                            <td class="fs-12 fw-semibold">
                                {{ $log->operation->orderOperation->name ?? 'Op #' . ($log->operation->sequence ?? '—') }}
                                <br><small class="text-muted font-monospace">{{ $log->operation->orderOperation->operation_number ?? '' }}</small>
                            </td>
                            <td class="fs-12">
                                @if($log->change_type === 'manual_shift')
                                    <span class="badge bg-soft-warning text-warning"><i class="feather-edit-2 me-1"></i> Manual Shift</span>
                                @elseif($log->change_type === 'ripple_shift')
                                    <span class="badge bg-soft-info text-info"><i class="feather-refresh-cw me-1"></i> Ripple Shift</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">{{ $log->change_type }}</span>
                                @endif
                            </td>
                            <td class="fs-12 font-monospace">
                                <span class="badge bg-light text-dark text-capitalize">{{ $log->shift_mode ?? 'isolated' }}</span>
                            </td>
                            <td class="fs-11 font-monospace text-muted">
                                <div><strong>Start:</strong> {{ $log->old_planned_start ? \Illuminate\Support\Carbon::parse($log->old_planned_start)->format('d/m H:i') : '—' }}</div>
                                <div><strong>Finish:</strong> {{ $log->old_planned_finish ? \Illuminate\Support\Carbon::parse($log->old_planned_finish)->format('d/m H:i') : '—' }}</div>
                                <div><strong>Machine:</strong> {{ $log->oldMachine->name ?? 'Unassigned' }}</div>
                            </td>
                            <td class="fs-11 font-monospace text-dark fw-bold">
                                <div><strong>Start:</strong> {{ $log->new_planned_start ? \Illuminate\Support\Carbon::parse($log->new_planned_start)->format('d/m H:i') : '—' }}</div>
                                <div><strong>Finish:</strong> {{ $log->new_planned_finish ? \Illuminate\Support\Carbon::parse($log->new_planned_finish)->format('d/m H:i') : '—' }}</div>
                                <div><strong>Machine:</strong> {{ $log->newMachine->name ?? 'Unassigned' }}</div>
                            </td>
                            <td class="fs-12 text-muted">
                                {{ $log->reason ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="feather-clock fs-24 mb-2 d-block text-muted"></i>
                                No schedule adjustments recorded yet for this schedule.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>

            {{-- Standard ERP UI Pagination Component --}}
            <x-ui.pagination
                :currentPage="$logs->currentPage()"
                :totalPages="$logs->lastPage()"
                :totalResults="$logs->total()"
                :perPage="$logs->perPage()"
            />
        </x-ui.odoo-form-ui>
    </div>
@endsection
