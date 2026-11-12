@php
    $timeLogs = $task->timeLogs()->with(['user', 'approver'])->orderByDesc('log_date')->orderByDesc('id')->get();
    $totalHours = (float) $task->actual_hours;
    $canLogTime = auth()->user()?->can('create', [\App\Domains\Projects\Models\TimeLog::class, $project]);
@endphp

<div class="border rounded-3 p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <h6 class="fw-bold text-dark mb-0">{{ __('projects.time_logs', ['default' => 'Time Logs']) }}</h6>
            <span class="badge bg-soft-primary text-primary">{{ number_format($totalHours, 2) }} {{ __('projects.hours_logged', ['default' => 'hrs approved']) }}</span>
        </div>
        @if ($canLogTime)
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#logTimeModal">
                <i class="feather-plus me-1"></i>{{ __('projects.log_time', ['default' => 'Log Time']) }}
            </button>
        @endif
    </div>

    @if ($timeLogs->isEmpty())
        <div class="text-center py-3 text-muted fs-12">
            <i class="feather-clock fs-18 mb-1 d-block"></i>
            {{ __('projects.no_timelogs_recorded', ['default' => 'No time logs recorded against this task yet.']) }}
        </div>
    @else
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" class="align-middle mb-0">
                <thead class="table-light fs-11 text-uppercase text-muted">
                    <tr>
                        <th>{{ __('projects.user', ['default' => 'User']) }}</th>
                        <th>{{ __('projects.date', ['default' => 'Date']) }}</th>
                        <th>{{ __('projects.hours', ['default' => 'Hours']) }}</th>
                        <th>{{ __('projects.rate', ['default' => 'Rate']) }}</th>
                        <th>{{ __('projects.amount', ['default' => 'Amount']) }}</th>
                        <th>{{ __('projects.status', ['default' => 'Status']) }}</th>
                        <th>{{ __('projects.description', ['default' => 'Description']) }}</th>
                        <th class="text-end">{{ __('projects.actions', ['default' => 'Actions']) }}</th>
                    </tr>
                </thead>
                <tbody class="fs-12">
                    @foreach ($timeLogs as $log)
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">{{ $log->user?->name ?: '—' }}</div>
                            </td>
                            <td>{{ $log->log_date?->format('d/m/Y') }}</td>
                            <td><span class="fw-bold text-dark font-monospace">{{ number_format((float) $log->hours, 2) }}</span> hrs</td>
                            <td>
                                @if ($log->hourly_rate !== null && (float) $log->hourly_rate > 0)
                                    <span class="font-monospace text-dark">{{ active_currency_symbol() }}{{ number_format((float) $log->hourly_rate, 2) }}</span>/hr
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($log->hourly_rate !== null && (float) $log->hourly_rate > 0 && (float) $log->hours > 0)
                                    <span class="fw-bold font-monospace text-primary">{{ active_currency_symbol() }}{{ number_format((float) $log->hourly_rate * (float) $log->hours, 2) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($log->approval_status === 'Approved')
                                    <x-ui.badge variant="success" title="{{ $log->approver ? 'Approved by ' . $log->approver->name : 'Approved' }}">
                                        <i class="feather-check-circle me-1"></i>{{ __('projects.approved', ['default' => 'Approved']) }}
                                    </x-ui.badge>
                                @elseif ($log->approval_status === 'Rejected')
                                    <x-ui.badge variant="danger" title="{{ $log->rejection_remarks }}">
                                        <i class="feather-x-circle me-1"></i>{{ __('projects.rejected', ['default' => 'Rejected']) }}
                                    </x-ui.badge>
                                @else
                                    <x-ui.badge variant="warning">
                                        <i class="feather-clock me-1"></i>{{ __('projects.pending', ['default' => 'Pending']) }}
                                    </x-ui.badge>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted text-truncate d-inline-block" style="max-width: 180px;" title="{{ $log->description }}">
                                    {{ $log->description ?: '—' }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($log->approval_status === 'Pending' && (auth()->user()?->can('approve', $log) || auth()->id() === (int)$project->owner_id || auth()->id() === (int)$project->manager_id || auth()->user()?->hasAnyRole('owner', 'tenant_owner', 'admin', 'super_admin')))
                                    <form method="POST" action="{{ route('projects.timesheets.approve', $log) }}" class="d-inline" onsubmit="return confirm('{{ __('projects.confirm_approve_timelog', ['default' => 'Approve this time entry?']) }}');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-icon btn-light text-success me-1" title="{{ __('projects.approve', ['default' => 'Approve']) }}">
                                            <i class="feather-check"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('projects.timesheets.reject', $log) }}" class="d-inline" onsubmit="return promptRejectRemarks(event, this);">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="rejection_remarks" value="">
                                        <button type="submit" class="btn btn-sm btn-icon btn-light text-warning me-1" title="{{ __('projects.reject', ['default' => 'Reject']) }}">
                                            <i class="feather-x"></i>
                                        </button>
                                    </form>
                                @endif

                                @if ($log->approval_status !== 'Approved' && auth()->user()?->can('delete', $log))
                                    <form method="POST" action="{{ route('projects.tasks.timelogs.destroy', [$project, $task, $log]) }}" class="d-inline" onsubmit="return confirm('{{ __('projects.confirm_delete_timelog', ['default' => 'Delete this time entry?']) }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon btn-light text-danger" title="{{ __('projects.delete', ['default' => 'Delete']) }}">
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.odoo-form-ui>
        </div>
    @endif
</div>

{{-- Modal: Log Time --}}
@if ($canLogTime)
    <x-ui.modal id="logTimeModal"
        :title="'<i class=\'feather-clock me-1 text-primary\'></i> ' . e(__('projects.log_time_for_task', ['default' => 'Log Time'])) . ' — ' . e($task->task_code)"
        formAction="{{ route('projects.tasks.timelogs.store', [$project, $task]) }}"
        formMethod="POST"
        :centered="true"
        :submitText="__('projects.submit_time_log', ['default' => 'Save Time Log'])"
        :closeText="__('projects.cancel', ['default' => 'Cancel'])">

        <div class="row g-3">
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" inputType="date" name="log_date" :label="__('projects.date', ['default' => 'Date'])" :value="date('Y-m-d')" :required="true" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" inputType="time" name="start_time" id="timeLogStartTime" :label="__('projects.start_time', ['default' => 'Start Time'])" />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui type="input" inputType="time" name="end_time" id="timeLogEndTime" :label="__('projects.end_time', ['default' => 'End Time'])" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" inputType="number" name="hours" id="timeLogHoursInput" :label="__('projects.hours', ['default' => 'Hours'])" step="0.01" min="0.01" max="24" inputmode="decimal" placeholder="e.g. 2.50" :required="true" />
                <div id="logTimeHoursHint" class="fs-11 text-muted mt-1" data-default-hint="{{ __('projects.hours_hint') }}">
                    {{ __('projects.hours_hint') }}
                </div>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" name="description" :label="__('projects.description', ['default' => 'Work Summary / Description'])" :rows="3" placeholder="{{ __('projects.describe_work_done', ['default' => 'Describe work performed...']) }}" />
            </div>
        </div>
    </x-ui.modal>

    @push('scripts')
        <script>
            (function ($) {
                function syncTimeLogHours() {
                    var modal = $('#logTimeModal');
                    if (!modal.length) return;

                    var startInput = modal.find('input[name="start_time"]');
                    var endInput = modal.find('input[name="end_time"]');
                    var hoursInput = modal.find('input[name="hours"]');
                    var hintEl = modal.find('#logTimeHoursHint');

                    var startVal = (startInput.val() || '').trim();
                    var endVal = (endInput.val() || '').trim();

                    if (startVal && endVal) {
                        var sParts = startVal.split(':');
                        var eParts = endVal.split(':');

                        if (sParts.length >= 2 && eParts.length >= 2) {
                            var sMin = parseInt(sParts[0], 10) * 60 + parseInt(sParts[1], 10);
                            var eMin = parseInt(eParts[0], 10) * 60 + parseInt(eParts[1], 10);

                            if (eMin > sMin) {
                                var diffHours = ((eMin - sMin) / 60).toFixed(2);
                                hoursInput.val(diffHours);
                                hoursInput.prop('readonly', true);
                                hoursInput.addClass('bg-light').removeClass('is-invalid');
                                endInput.removeClass('is-invalid');
                                if (hintEl.length) {
                                    hintEl.html('<span class="text-success fw-semibold"><i class="feather-lock me-1"></i>Locked to calculated interval: ' + diffHours + ' hrs</span>');
                                }
                            } else {
                                endInput.addClass('is-invalid');
                                hoursInput.prop('readonly', false).removeClass('bg-light');
                                if (hintEl.length) {
                                    hintEl.html('<span class="text-danger fw-semibold"><i class="feather-alert-circle me-1"></i>End time must be after start time</span>');
                                }
                            }
                        }
                    } else {
                        hoursInput.prop('readonly', false);
                        hoursInput.removeClass('bg-light is-invalid');
                        endInput.removeClass('is-invalid');
                        if (hintEl.length) {
                            var defHint = hintEl.data('default-hint') || 'Auto-calculated from Start/End time, or enter directly.';
                            hintEl.html('<span class="text-muted">' + defHint + '</span>');
                        }
                    }
                }

                // Global event delegation on document so it works anywhere
                $(document).on('input change keyup blur', '#logTimeModal input[name="start_time"], #logTimeModal input[name="end_time"]', function () {
                    syncTimeLogHours();
                });

                $(document).on('show.bs.modal shown.bs.modal', '#logTimeModal', function () {
                    syncTimeLogHours();
                });

                $(document).on('submit', '#logTimeModal form', function (e) {
                    var modal = $('#logTimeModal');
                    var startInput = modal.find('input[name="start_time"]');
                    var endInput = modal.find('input[name="end_time"]');
                    var hoursInput = modal.find('input[name="hours"]');

                    var startVal = (startInput.val() || '').trim();
                    var endVal = (endInput.val() || '').trim();

                    if (startVal && endVal) {
                        var sParts = startVal.split(':');
                        var eParts = endVal.split(':');
                        var sMin = parseInt(sParts[0], 10) * 60 + parseInt(sParts[1], 10);
                        var eMin = parseInt(eParts[0], 10) * 60 + parseInt(eParts[1], 10);

                        if (eMin <= sMin) {
                            e.preventDefault();
                            e.stopPropagation();
                            endInput.addClass('is-invalid');
                            if (window.showAppToast) {
                                window.showAppToast('error', 'End time must be after start time.');
                            }
                            return false;
                        }

                        // Always enforce calculated hours
                        var diffHours = ((eMin - sMin) / 60).toFixed(2);
                        hoursInput.val(diffHours);
                    }
                });

                window.promptRejectRemarks = function (event, form) {
                    event.preventDefault();
                    var remarks = window.prompt("Enter rejection reason (optional):", "");
                    if (remarks === null) return false;
                    form.querySelector('input[name="rejection_remarks"]').value = remarks.trim();
                    form.submit();
                    return true;
                };

                $(document).ready(function () {
                    syncTimeLogHours();
                });
            })(jQuery);
        </script>
    @endpush
@endif
