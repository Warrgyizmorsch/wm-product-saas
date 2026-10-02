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
                        <th>{{ __('projects.billable', ['default' => 'Billable']) }}</th>
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
                                @if ($log->is_billable)
                                    <x-ui.badge variant="success" soft>{{ __('projects.billable', ['default' => 'Billable']) }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="secondary" soft>{{ __('projects.non_billable', ['default' => 'Non-Billable']) }}</x-ui.badge>
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
                            <td class="text-end">
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
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" name="log_date" :label="__('projects.date', ['default' => 'Date'])" :value="date('Y-m-d')" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" name="hours" :label="__('projects.hours', ['default' => 'Hours'])" step="0.25" min="0.01" max="24" placeholder="e.g. 2.50" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="time" name="start_time" :label="__('projects.start_time', ['default' => 'Start Time'])" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="time" name="end_time" :label="__('projects.end_time', ['default' => 'End Time'])" />
            </div>
            <div class="col-md-6 d-flex align-items-center">
                <x-ui.odoo-form-ui type="checkbox" name="is_billable" value="1" id="isBillableSwitch" checked :label="__('projects.billable_time', ['default' => 'Billable to Client'])" />
            </div>
            <div class="col-md-6" id="hourlyRateGroup">
                @php
                    $currentMember = $project->members?->firstWhere('user_id', auth()->id());
                    $defaultMemberRate = $currentMember?->rate_per_hour;
                @endphp
                <x-ui.odoo-form-ui type="input" inputType="number" name="hourly_rate" :label="__('projects.rate_per_hour', ['default' => 'Rate / Hour (₹)'])" step="0.01" min="0" placeholder="e.g. 500.00" :value="$defaultMemberRate" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" name="description" :label="__('projects.description', ['default' => 'Work Summary / Description'])" :rows="3" placeholder="{{ __('projects.describe_work_done', ['default' => 'Describe work performed...']) }}" />
            </div>
        </div>
    </x-ui.modal>
@endif
