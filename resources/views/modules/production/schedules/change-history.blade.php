@extends('layouts.duralux')

@section('title', 'Schedule Change History | SaaS ERP')

@section('page-back-button')
    <x-ui.icon-btn href="{{ route('production.schedules.show', $schedule->id) }}" icon="feather-arrow-left" variant="transparent-dark" title="Back to Schedule Details" />
@endsection

@section('content')
    <div class="erp-single-panel">
        <x-ui.workflow-guide title="Schedule Change Audit Log">
        Review planner adjustment history for Schedule [{{ $schedule->schedule_number }}]. Tracks all manual timing shifts, machine reassignments, ripple propagation, and shift mode reasons.
    </x-ui.workflow-guide>

    <x-ui.odoo-form-ui type="sheet">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    <i class="feather-clock text-primary me-2"></i>Schedule Change History
                </h4>
                <p class="text-muted fs-13 mb-0">Schedule #{{ $schedule->schedule_number }} | Production Order: {{ $schedule->order->order_number ?? '' }}</p>
            </div>
            <x-ui.button href="{{ route('production.schedules.show', $schedule->id) }}" variant="outline-secondary" size="sm" icon="feather-arrow-left">
                Back to Schedule Details
            </x-ui.button>
        </div>

        <x-ui.odoo-form-ui type="table">
            <thead class="table-light fs-12">
                <tr>
                    <th style="width: 15%">Timestamp / User</th>
                    <th style="width: 15%">Operation</th>
                    <th style="width: 12%">Change Type</th>
                    <th style="width: 10%">Shift Mode</th>
                    <th style="width: 20%">Original Timings</th>
                    <th style="width: 20%">New Timings</th>
                    <th style="width: 8%">Reason</th>
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
                        <td colspan="7" class="text-center py-4 text-muted">No schedule adjustments recorded yet for this schedule.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="d-flex justify-content-end mt-3">
            {{ $logs->links() }}
        </div>
    </x-ui.odoo-form-ui>
    </div>
@endsection
