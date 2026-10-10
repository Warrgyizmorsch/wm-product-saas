@props([
    'workOrder',
    'assignments' => null,
])

@php
    $assignmentsList = $assignments ?? $workOrder->assignments;
    if ($assignmentsList instanceof \Illuminate\Database\Eloquent\Collection) {
        $assignmentsList = $assignmentsList->sortBy('assigned_at');
    }
    $assignedCount = $assignmentsList ? $assignmentsList->count() : 0;
    $combinedRate = (float) ($assignmentsList ? $assignmentsList->sum('hourly_rate') : 0.0);

    $isOngoing = $workOrder->status === \App\Domains\Production\Models\ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS;

    $getAssignmentHours = function ($assignment) use ($workOrder) {
        if (in_array($workOrder->status, ['completed', 'cancelled'])) {
            return (float) ($assignment->worked_hours ?: 0.0);
        }
        if ($workOrder->status === 'in_progress') {
            $maintStart = $workOrder->actual_start
                ? \Carbon\Carbon::parse($workOrder->actual_start)
                : ($workOrder->planned_start ? \Carbon\Carbon::parse($workOrder->planned_start) : \Carbon\Carbon::parse($workOrder->created_at));
            $assignmentAt = $assignment->assigned_at ? \Carbon\Carbon::parse($assignment->assigned_at) : $maintStart;
            $referenceStart = $assignmentAt->greaterThan($maintStart) ? $assignmentAt : $maintStart;
            $seconds = max(0, $referenceStart->diffInSeconds(now(), false));
            $totalMinutes = (int) round($seconds / 60);
            $h = intdiv($totalMinutes, 60);
            $m = $totalMinutes % 60;
            $accumulated = (float) sprintf('%d.%02d', $h, $m);
            return max((float) ($assignment->worked_hours ?: 0.0), $accumulated);
        }
        return (float) ($assignment->worked_hours ?: 0.0);
    };

    $combinedHours = (float) ($assignmentsList ? $assignmentsList->sum(function ($asn) use ($getAssignmentHours) {
        return $getAssignmentHours($asn);
    }) : 0.0);

    $totalMechanicCost = (float) ($assignmentsList ? $assignmentsList->sum(function ($asn) use ($getAssignmentHours) {
        return round(hours_to_decimal($getAssignmentHours($asn)) * (float) $asn->hourly_rate, 2);
    }) : 0.0);
@endphp

<!-- Assigned Technicians Details Dialog -->
<div class="modal fade" id="assignedTechniciansModal" tabindex="-1" aria-labelledby="assignedTechniciansModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle">
                        <i class="feather-users"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="assignedTechniciansModalLabel">
                            Assigned Technicians Details
                        </h5>
                        <small class="text-muted">
                            Work Order #{{ $workOrder->work_order_number }} &bull; Machine: {{ $workOrder->machine?->name }}
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-soft-primary text-primary fs-12 px-2 py-1">
                        {{ $assignedCount }} {{ \Illuminate\Support\Str::plural('Technician', $assignedCount) }}
                    </span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body p-0">
                @if(!$assignmentsList || $assignmentsList->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="feather-users fs-24 mb-2 d-block text-secondary"></i>
                        <p class="mb-0 fs-13">No technicians have been assigned to this work order yet.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="bg-light text-muted">
                                <tr>
                                    <th>Technician</th>
                                    <th>Type</th>
                                    <th class="text-end">Hourly Rate</th>
                                    <th class="text-end">Hours Worked</th>
                                    <th class="text-end">Subtotal Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($assignmentsList as $assignment)
                                    @php
                                        $hours = $getAssignmentHours($assignment);
                                        $cost = round(hours_to_decimal($hours) * (float) $assignment->hourly_rate, 2);
                                        $techName = $assignment->assignment_type === 'internal'
                                            ? ($assignment->technician?->name ?? $assignment->technician_name)
                                            : ($assignment->technician_name ?: 'External technician');
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $techName }}</div>
                                            @if($assignment->notes)
                                                <small class="text-muted d-block mt-1 fst-italic">
                                                    <i class="feather-file-text me-1"></i>{{ $assignment->notes }}
                                                </small>
                                            @endif
                                            @if($assignment->assigned_at)
                                                <small class="text-muted d-block fs-11">
                                                    Assigned: {{ $assignment->assigned_at->format('Y-m-d H:i') }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($assignment->assignment_type === 'internal')
                                                <span class="badge bg-soft-primary text-primary">Internal / In-House</span>
                                            @else
                                                <span class="badge bg-soft-info text-info">External</span>
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">
                                            {{ format_currency((float) $assignment->hourly_rate) }}/hr
                                        </td>
                                        <td class="text-end font-monospace">
                                            <span>{{ format_hours($hours) }}</span>
                                            @if($isOngoing)
                                                <span class="badge bg-soft-warning text-warning fs-11 ms-1">Ongoing</span>
                                            @endif
                                            @if((float) $assignment->expected_work_hours > 0)
                                                <small class="text-muted d-block font-sans fs-11">
                                                    Expected: {{ format_hours((float) $assignment->expected_work_hours) }}
                                                </small>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold text-dark font-monospace">
                                            {{ format_currency($cost) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold border-top">
                                <tr>
                                    <td colspan="2">
                                        Total ({{ $assignedCount }} {{ \Illuminate\Support\Str::plural('Technician', $assignedCount) }})
                                    </td>
                                    <td class="text-end text-dark font-monospace">
                                        {{ format_currency($combinedRate) }}/hr
                                    </td>
                                    <td class="text-end text-dark font-monospace">
                                        <span>{{ format_hours($assignmentsList ?? $combinedHours) }}</span>
                                        @if($isOngoing)
                                            <span class="badge bg-soft-warning text-warning fs-11 ms-1">Ongoing</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-primary fs-14 font-monospace">
                                        {{ format_currency($totalMechanicCost) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

            <div class="modal-footer border-top d-flex justify-content-between py-2">
                <div>
                    @if(!in_array($workOrder->status, ['completed', 'cancelled']))
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#assignModal">
                            <i class="feather-user-plus me-1"></i> Manage Assignments
                        </button>
                    @endif
                </div>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        function moveModalToBody() {
            var modalEl = document.getElementById('assignedTechniciansModal');
            if (modalEl && modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
        }

        // Move to document.body immediately so it is never trapped inside .nxl-container or .main-content
        moveModalToBody();

        function initAssignedTechniciansUI() {
            moveModalToBody();

            var modalEl = document.getElementById('assignedTechniciansModal');
            var triggerList = document.querySelectorAll('.assigned-technicians-trigger');

            triggerList.forEach(function (triggerEl) {
                if (!triggerEl._techniciansHoverInit) {
                    var hoverTimeout = null;

                    triggerEl.addEventListener('mouseenter', function () {
                        hoverTimeout = setTimeout(function () {
                            if (modalEl) {
                                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                                modal.show();
                            }
                        }, 500);
                    });

                    triggerEl.addEventListener('mouseleave', function () {
                        if (hoverTimeout) {
                            clearTimeout(hoverTimeout);
                            hoverTimeout = null;
                        }
                    });

                    triggerEl.addEventListener('click', function () {
                        if (hoverTimeout) {
                            clearTimeout(hoverTimeout);
                            hoverTimeout = null;
                        }
                    });

                    triggerEl._techniciansHoverInit = true;
                }
            });

            if (modalEl && !modalEl._techniciansModalDismissInit) {
                modalEl.addEventListener('show.bs.modal', function () {
                    moveModalToBody();
                });
                modalEl._techniciansModalDismissInit = true;
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAssignedTechniciansUI);
        } else {
            initAssignedTechniciansUI();
        }
    })();
</script>
