@extends('layouts.duralux')

@section('title', __('projects.timesheet_approvals', ['default' => 'Timesheet Approvals']) . ' | ' . __('projects.title') . ' | SaaS ERP')
@section('page-title', __('projects.timesheet_approvals', ['default' => 'Timesheet Approvals']))
@section('breadcrumb', __('projects.title') . ' / ' . __('projects.timesheets', ['default' => 'Timesheets']) . ' / ' . __('projects.approvals', ['default' => 'Approvals']))

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border">
        <div class="d-flex align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-check-square me-2 text-primary"></i>{{ __('projects.pending_timesheet_approvals', ['default' => 'Pending Timesheet Approvals']) }}
                </h5>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="badge bg-soft-warning text-warning fs-12 px-3 py-2">
                    <i class="feather-clock me-1"></i>{{ $timeLogs->count() }} {{ __('projects.pending_entries', ['default' => 'Pending Entries']) }}
                </span>

                <form method="GET" action="{{ route('projects.timesheets.approval') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('projects.filter_options', ['default' => 'Filter Options']) }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.project', ['default' => 'Project']) }}</label>
                            <x-ui.odoo-form-ui type="select" name="project_id" select2Selector="default">
                                <option value="">{{ __('projects.all_projects', ['default' => 'All Projects']) }}</option>
                                @foreach ($projects as $proj)
                                    <option value="{{ $proj->id }}" @selected(($filters['project_id'] ?? '') == $proj->id)>
                                        [{{ $proj->project_code }}] {{ $proj->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.team_member', ['default' => 'Team Member']) }}</label>
                            <x-ui.odoo-form-ui type="select" name="user_id" select2Selector="default">
                                <option value="">{{ __('projects.all_members', ['default' => 'All Members']) }}</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>
                                        {{ $u->name }} ({{ $u->email }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <x-ui.button href="{{ route('projects.timesheets.approval') }}" variant="light" class="border">
                                {{ __('projects.reset', ['default' => 'Reset']) }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary">
                                {{ __('projects.apply_filters', ['default' => 'Apply Filters']) }}
                            </x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        @if (!empty($filters['project_id']) || !empty($filters['user_id']))
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                @if (!empty($filters['project_id']))
                    @php $filterProject = $projects->firstWhere('id', (int) $filters['project_id']); @endphp
                    <x-ui.badge variant="secondary" soft>{{ __('projects.project', ['default' => 'Project']) }}: {{ $filterProject?->name ?? '—' }}</x-ui.badge>
                @endif
                @if (!empty($filters['user_id']))
                    @php $filterUser = $users->firstWhere('id', (int) $filters['user_id']); @endphp
                    <x-ui.badge variant="secondary" soft>{{ __('projects.team_member', ['default' => 'Team Member']) }}: {{ $filterUser?->name ?? '—' }}</x-ui.badge>
                @endif
                <a href="{{ route('projects.timesheets.approval') }}" class="fs-11 text-danger fw-semibold">
                    <i class="feather-x me-1"></i>{{ __('projects.clear_filters', ['default' => 'Clear Filters']) }}
                </a>
            </div>
        @endif

        @if ($timeLogs->isEmpty())
            <div class="text-center py-5">
                <div class="avatar-lg bg-soft-success text-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="feather-check-circle fs-24"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">{{ __('projects.queue_empty', ['default' => 'All Caught Up!']) }}</h6>
                <p class="text-muted fs-12 mb-0">{{ __('projects.no_pending_timesheets', ['default' => 'There are no pending timesheets requiring approval at this time.']) }}</p>
            </div>
        @else
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" class="align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase text-muted">
                        <tr>
                            <th>{{ __('projects.team_member', ['default' => 'Team Member']) }}</th>
                            <th>{{ __('projects.project', ['default' => 'Project']) }}</th>
                            <th>{{ __('projects.task', ['default' => 'Task']) }}</th>
                            <th>{{ __('projects.date', ['default' => 'Date']) }}</th>
                            <th>{{ __('projects.hours', ['default' => 'Hours']) }}</th>
                            <th>{{ __('projects.billable', ['default' => 'Billable']) }}</th>
                            <th>{{ __('projects.rate_amount', ['default' => 'Rate / Amount']) }}</th>
                            <th>{{ __('projects.description', ['default' => 'Description']) }}</th>
                            <th class="text-end">{{ __('projects.actions', ['default' => 'Actions']) }}</th>
                        </tr>
                    </thead>
                    <tbody class="fs-12">
                        @foreach ($timeLogs as $log)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $log->user?->name ?: '—' }}</div>
                                    <div class="fs-11 text-muted">{{ $log->user?->email }}</div>
                                </td>
                                <td>
                                    @if ($log->project)
                                        <a href="{{ route('projects.show', $log->project) }}" class="fw-semibold text-primary">
                                            {{ $log->project->project_code }}
                                        </a>
                                        <div class="fs-11 text-muted text-truncate" style="max-width: 140px;">{{ $log->project->name }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($log->task && $log->project)
                                        <a href="{{ route('projects.tasks.show', [$log->project, $log->task]) }}" class="fw-semibold text-dark">
                                            {{ $log->task->task_code }}
                                        </a>
                                        <div class="fs-11 text-muted text-truncate" style="max-width: 150px;">{{ $log->task->title }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $log->log_date?->format('d/m/Y') }}</td>
                                <td>
                                    <span class="fw-bold font-monospace text-dark fs-13">{{ number_format((float) $log->hours, 2) }}</span> hrs
                                </td>
                                <td>
                                    @if ($log->is_billable)
                                        <x-ui.badge variant="success" soft>{{ __('projects.billable', ['default' => 'Billable']) }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft>{{ __('projects.non_billable', ['default' => 'Non-Billable']) }}</x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    @if ($log->is_billable && $log->hourly_rate)
                                        <div class="fw-semibold text-dark">{{ number_format((float) $log->hourly_rate, 2) }}/hr</div>
                                        <div class="fs-11 text-success fw-bold">{{ number_format($log->billable_amount, 2) }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted text-truncate d-inline-block" style="max-width: 180px;" title="{{ $log->description }}">
                                        {{ $log->description ?: '—' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if ((int) $log->user_id === (int) auth()->id())
                                        <div class="d-flex justify-content-end align-items-center">
                                            <span class="badge bg-soft-secondary text-muted px-2 py-1 fs-11" title="{{ __('projects.cannot_approve_own_timelog', ['default' => 'A team member cannot approve their own timesheet entry.']) }}">
                                                <i class="feather-user me-1"></i>{{ __('projects.own_entry', ['default' => 'Own Entry (Cannot self-approve)']) }}
                                            </span>
                                        </div>
                                    @else
                                        <div class="d-flex justify-content-end gap-1">
                                            <form method="POST" action="{{ route('projects.timesheets.approve', $log) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-success px-2 py-1 fs-11" title="{{ __('projects.approve', ['default' => 'Approve']) }}">
                                                    <i class="feather-check me-1"></i>{{ __('projects.approve', ['default' => 'Approve']) }}
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-sm btn-light text-danger border px-2 py-1 fs-11" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $log->id }}" title="{{ __('projects.reject', ['default' => 'Reject']) }}">
                                                <i class="feather-x me-1"></i>{{ __('projects.reject', ['default' => 'Reject']) }}
                                            </button>
                                        </div>

                                        {{-- Modal: Reject Time Log --}}
                                        <x-ui.modal id="rejectModal{{ $log->id }}"
                                            :title="'<i class=\'feather-alert-triangle me-1 text-danger\'></i> <span class=\'text-danger fw-bold\'>' . e(__('projects.reject_time_log', ['default' => 'Reject Time Log'])) . '</span>'"
                                            formAction="{{ route('projects.timesheets.reject', $log) }}"
                                            formMethod="PATCH"
                                            :centered="true"
                                            class="text-start">

                                            <p class="fs-12 text-muted mb-3">
                                                {{ __('projects.reject_explanation', ['default' => 'Provide feedback explaining why this timesheet entry is being returned to the team member for correction:']) }}
                                            </p>
                                            <div class="mb-2">
                                                <label class="form-label fs-11 text-uppercase text-muted fw-bold">{{ __('projects.rejection_remarks', ['default' => 'Rejection Remarks']) }}</label>
                                                <x-ui.odoo-form-ui type="textarea" name="rejection_remarks" :rows="3" placeholder="{{ __('projects.rejection_remarks_placeholder', ['default' => 'e.g. Overlapping hours, incorrect task attribution...']) }}" :required="true" />
                                            </div>

                                            <x-slot:footer>
                                                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('projects.cancel', ['default' => 'Cancel']) }}</button>
                                                <button type="submit" class="btn btn-danger">
                                                    {{ __('projects.confirm_rejection', ['default' => 'Reject Entry']) }}
                                                </button>
                                            </x-slot:footer>
                                        </x-ui.modal>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @endif
    </div>
@endsection
