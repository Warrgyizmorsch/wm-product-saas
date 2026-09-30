@extends('layouts.duralux')

@section('title', $issue->issue_code . ' | ' . __('projects.issues') . ' | SaaS ERP')
@section('page-title', $issue->issue_code . ': ' . $issue->title)
@section('breadcrumb', __('projects.title') . ' / ' . $project->project_code . ' / ' . __('projects.issues') . ' / ' . $issue->issue_code)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ $backUrl }}" class="btn btn-light">
            <i class="feather-arrow-left me-2"></i>{{ __('projects.back') }}
        </a>
    </div>
@endsection

@include('modules.projects._panel-styles')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border">
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">{{ __('projects.validation_errors') }}</h6>
                <ul class="mb-0 fs-12 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        {{-- Hero Header --}}
        @php
            $sevBadgeClass = match($issue->severity) {
                'Critical', 'critical' => 'badge bg-danger-subtle text-danger border border-danger-subtle',
                'Major', 'major' => 'badge bg-warning-subtle text-warning border border-warning-subtle',
                'Minor', 'minor' => 'badge bg-secondary-subtle text-secondary border border-secondary-subtle',
                default => 'badge bg-secondary-subtle text-secondary',
            };
            $prioBadgeClass = match($issue->priority) {
                'Critical', 'urgent', 'critical' => 'badge bg-danger text-white',
                'High', 'high' => 'badge bg-warning text-dark',
                'Medium', 'medium' => 'badge bg-primary-subtle text-primary border border-primary-subtle',
                'Low', 'low' => 'badge bg-light text-muted border',
                default => 'badge bg-light text-muted',
            };
            $statusBadgeClass = match($issue->status) {
                'Open' => 'badge bg-secondary-subtle text-secondary border',
                'Assigned' => 'badge bg-info-subtle text-info border border-info-subtle',
                'In Progress' => 'badge bg-primary-subtle text-primary border border-primary-subtle',
                'Resolved' => 'badge bg-warning-subtle text-warning border border-warning-subtle',
                'Closed' => 'badge bg-success-subtle text-success border border-success-subtle',
                default => 'badge bg-secondary-subtle text-secondary',
            };
        @endphp

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="fs-18 fw-bold font-monospace text-primary">{{ $issue->issue_code }}</span>
                    <span class="{{ $statusBadgeClass }} fs-12">{{ __('projects.issue_statuses.' . $issue->status) }}</span>
                    <span class="{{ $sevBadgeClass }} fs-12">{{ __('projects.severities.' . $issue->severity) }}</span>
                    <span class="{{ $prioBadgeClass }} fs-12">{{ __('projects.priorities.' . $issue->priority) }}</span>
                </div>
                <h4 class="fw-bold text-dark mb-0">
                    @if ($canManageIssue)
                        <x-ui.inline-edit field="title" :value="$issue->title" :url="route('projects.issues.field', [$project, $issue])" />
                    @else
                        {{ $issue->title }}
                    @endif
                </h4>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if ($issue->status === \App\Domains\Projects\Models\Issue::STATUS_RESOLVED && $canRetestIssue)
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#retestIssueModal">
                        <i class="feather-check-circle me-1"></i>{{ __('projects.retest_issue') }}
                    </button>
                @endif

                @if (!in_array($issue->status, [\App\Domains\Projects\Models\Issue::STATUS_RESOLVED, \App\Domains\Projects\Models\Issue::STATUS_CLOSED], true) && ($canResolveIssue ?? $canManageIssue))
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resolveIssueModal">
                        <i class="feather-check-square me-1"></i>{{ __('projects.resolve_issue') }}
                    </button>
                @endif

                @can('delete', $issue)
                    <form method="POST" action="{{ route('projects.issues.destroy', [$project, $issue]) }}" onsubmit="return confirm('{{ __('projects.confirm_delete_issue') }}')" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="feather-trash-2 me-1"></i>{{ __('projects.delete') }}
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Main 2-column workspace layout --}}
        <div class="row g-4">
            {{-- Left column --}}
            <div class="col-lg-8">
                {{-- Description Card --}}
                <div class="card border rounded-3 mb-4 shadow-none">
                    <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center">
                        <span class="fw-bold fs-12 text-uppercase text-muted"><i class="feather-file-text me-1 text-primary"></i>{{ __('projects.description') }}</span>
                    </div>
                    <div class="card-body p-3">
                        @if ($canManageIssue)
                            <x-ui.inline-edit field="description" :value="$issue->description" :url="route('projects.issues.field', [$project, $issue])" type="textarea" :label="__('projects.description')" />
                        @else
                            <p class="mb-0 fs-13 text-dark">{{ $issue->description ?: '—' }}</p>
                        @endif
                    </div>
                </div>

                {{-- Steps to Reproduce Card --}}
                <div class="card border rounded-3 mb-4 shadow-none">
                    <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center">
                        <span class="fw-bold fs-12 text-uppercase text-muted"><i class="feather-list me-1 text-primary"></i>{{ __('projects.steps_to_reproduce') }}</span>
                    </div>
                    <div class="card-body p-3">
                        @if ($canManageIssue)
                            <x-ui.inline-edit field="steps_to_reproduce" :value="$issue->steps_to_reproduce" :url="route('projects.issues.field', [$project, $issue])" type="textarea" :label="__('projects.steps_to_reproduce')" />
                        @else
                            <p class="mb-0 fs-13 text-dark" style="white-space: pre-wrap;">{{ $issue->steps_to_reproduce ?: '—' }}</p>
                        @endif
                    </div>
                </div>

                {{-- Resolution & Retest Details --}}
                @if ($issue->resolution_notes || $issue->retest_notes || $issue->status === \App\Domains\Projects\Models\Issue::STATUS_RESOLVED || $issue->status === \App\Domains\Projects\Models\Issue::STATUS_CLOSED)
                    <div class="card border rounded-3 mb-4 shadow-none">
                        <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center">
                            <span class="fw-bold fs-12 text-uppercase text-muted"><i class="feather-check-circle me-1 text-success"></i>{{ __('projects.resolution_notes') }}</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.resolution_notes') }}</label>
                                @if ($canManageIssue)
                                    <x-ui.inline-edit field="resolution_notes" :value="$issue->resolution_notes" :url="route('projects.issues.field', [$project, $issue])" type="textarea" :label="__('projects.resolution_notes')" />
                                @else
                                    <p class="mb-0 fs-13 text-dark">{{ $issue->resolution_notes ?: '—' }}</p>
                                @endif
                            </div>

                            @if ($issue->retest_notes)
                                <div class="pt-3 border-top">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.retest_notes') }}</label>
                                    <p class="mb-0 fs-13 text-dark">{{ $issue->retest_notes }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Attached Documents Card --}}
                <div class="card border rounded-3 mb-4 shadow-none">
                    <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-bold fs-12 text-uppercase text-muted"><i class="feather-paperclip me-1 text-primary"></i>{{ __('projects.documents') }}</span>
                        <button type="button" class="btn btn-sm btn-primary py-0 px-2 fs-11" data-bs-toggle="modal" data-bs-target="#uploadIssueDocumentModal">
                            <i class="feather-upload me-1"></i>{{ __('projects.upload_document') }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        @php
                            $issueDocs = $issue->documents;
                        @endphp
                        @if ($issueDocs->isNotEmpty())
                            <ul class="list-group list-group-flush">
                                @foreach ($issueDocs as $doc)
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="{{ $doc->file_icon }} fs-4 text-muted"></i>
                                            <div>
                                                <span class="fw-semibold text-dark fs-13 d-block">{{ $doc->title }}</span>
                                                <span class="fs-11 text-muted">{{ $doc->file_name }} ({{ $doc->human_file_size }})</span>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('projects.documents.preview', [$project, $doc]) }}" target="_blank" class="btn btn-sm btn-icon btn-light" title="{{ __('projects.preview') }}">
                                                <i class="feather-eye"></i>
                                            </a>
                                            <a href="{{ route('projects.documents.download', [$project, $doc]) }}" class="btn btn-sm btn-icon btn-light" title="{{ __('projects.download') }}">
                                                <i class="feather-download"></i>
                                            </a>
                                            @can('delete', $doc)
                                                <form method="POST" action="{{ route('projects.documents.destroy', [$project, $doc]) }}" onsubmit="return confirm('{{ __('projects.confirm_delete_document') }}')" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-icon btn-light text-danger" title="{{ __('projects.delete') }}">
                                                        <i class="feather-trash-2"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="p-3 text-center text-muted fs-12">
                                {{ __('projects.no_documents_found') }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Activity Timeline --}}
                <div class="card border rounded-3 shadow-none">
                    <div class="card-header bg-light py-2 px-3 border-bottom">
                        <span class="fw-bold fs-12 text-uppercase text-muted"><i class="feather-activity me-1 text-primary"></i>{{ __('projects.activity') }}</span>
                    </div>
                    <div class="card-body p-3">
                        @if ($activities->isNotEmpty())
                            <div class="timeline">
                                @foreach ($activities as $log)
                                    <div class="d-flex gap-3 mb-3">
                                        <div class="timeline-icon bg-light rounded-circle border d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                                            <i class="feather-clock fs-12 text-primary"></i>
                                        </div>
                                        <div>
                                            @php
                                                $logTitle = $log->title;
                                                if (\Illuminate\Support\Str::startsWith($logTitle, 'projects.')) {
                                                    $meta = $log->metadata ?? [];
                                                    $logTitle = __($logTitle, [
                                                        'number' => $meta['issue_number'] ?? $issue->issue_number,
                                                        'title'  => $meta['title'] ?? $issue->title,
                                                        'user'   => $log->triggeredBy?->name ?? __('projects.system'),
                                                        'hours'  => $meta['hours'] ?? '',
                                                        'task'   => $issue->task?->task_code ?? '',
                                                    ]);
                                                }
                                            @endphp
                                            <div class="fs-13 fw-semibold text-dark">{{ $logTitle }}</div>
                                            <div class="fs-11 text-muted">{{ $log->created_at->diffForHumans() }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center text-muted fs-12 py-3">
                                {{ __('projects.no_activity') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right Rail --}}
            <div class="col-lg-4">
                {{-- Properties Card --}}
                <div class="card border rounded-3 mb-4 shadow-none">
                    <div class="card-header bg-light py-2 px-3 border-bottom">
                        <span class="fw-bold fs-12 text-uppercase text-muted">{{ __('projects.status') }} & {{ __('projects.priority') }}</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.status') }}</label>
                            @if ($canManageIssue)
                                @php
                                    $statusOptions = collect(\App\Domains\Projects\Models\Issue::STATUSES)
                                        ->mapWithKeys(fn($st) => [$st => __('projects.issue_statuses.' . $st)]);
                                @endphp
                                <x-ui.inline-edit field="status" :value="$issue->status" :url="route('projects.issues.field', [$project, $issue])" type="select" :options="$statusOptions" :label="__('projects.status')" />
                            @else
                                <span class="{{ $statusBadgeClass }} fs-12">{{ __('projects.issue_statuses.' . $issue->status) }}</span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.severity') }}</label>
                            @if ($canManageIssue)
                                @php
                                    $sevOptions = collect(\App\Domains\Projects\Models\Issue::SEVERITIES)
                                        ->mapWithKeys(fn($sv) => [$sv => __('projects.severities.' . $sv)]);
                                @endphp
                                <x-ui.inline-edit field="severity" :value="$issue->severity" :url="route('projects.issues.field', [$project, $issue])" type="select" :options="$sevOptions" :label="__('projects.severity')" />
                            @else
                                <span class="{{ $sevBadgeClass }} fs-12">{{ __('projects.severities.' . $issue->severity) }}</span>
                            @endif
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.priority') }}</label>
                            @if ($canManageIssue)
                                @php
                                    $prioOptions = collect(\App\Domains\Projects\Models\Issue::PRIORITIES)
                                        ->mapWithKeys(fn($pr) => [$pr => __('projects.priorities.' . $pr)]);
                                @endphp
                                <x-ui.inline-edit field="priority" :value="$issue->priority" :url="route('projects.issues.field', [$project, $issue])" type="select" :options="$prioOptions" :label="__('projects.priority')" />
                            @else
                                <span class="{{ $prioBadgeClass }} fs-12">{{ __('projects.priorities.' . $issue->priority) }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- People Card --}}
                <div class="card border rounded-3 mb-4 shadow-none">
                    <div class="card-header bg-light py-2 px-3 border-bottom">
                        <span class="fw-bold fs-12 text-uppercase text-muted">{{ __('projects.collaborators') }}</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.assignee') }}</label>
                            @if ($canManageIssue)
                                @php
                                    $memberOptions = $availableMembers->pluck('name', 'id')->prepend(__('projects.none_option'), '');
                                @endphp
                                <x-ui.inline-edit field="assignee_id" :value="$issue->assignee_id" :url="route('projects.issues.field', [$project, $issue])" type="select2" :options="$memberOptions" :label="__('projects.assignee')" />
                            @else
                                <div class="fs-13 text-dark fw-semibold">{{ $issue->assignee?->name ?: '—' }}</div>
                            @endif
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.reporter') }}</label>
                            <div class="fs-13 text-dark fw-semibold">{{ $issue->reporter?->name ?: '—' }}</div>
                        </div>
                    </div>
                </div>

                {{-- Linked Task & Meta Card --}}
                <div class="card border rounded-3 shadow-none">
                    <div class="card-header bg-light py-2 px-3 border-bottom">
                        <span class="fw-bold fs-12 text-uppercase text-muted">{{ __('projects.details') }}</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.linked_task') }}</label>
                            @if ($issue->task)
                                <a href="{{ route('projects.tasks.show', [$project, $issue->task]) }}" class="d-block fs-13 text-primary text-decoration-none fw-semibold">
                                    <i class="feather-check-square me-1"></i>{{ $issue->task->task_number }} - {{ $issue->task->title }}
                                </a>
                            @else
                                <span class="fs-13 text-muted">{{ __('projects.no_linked_task') }}</span>
                            @endif
                        </div>

                        <div class="mb-2">
                            <span class="fs-11 text-muted text-uppercase fw-bold d-block">{{ __('projects.reported_on') }}</span>
                            <span class="fs-13 text-dark">{{ $issue->created_at?->format('Y-m-d H:i') }}</span>
                        </div>

                        @if ($issue->resolution_date)
                            <div class="mb-0">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block">{{ __('projects.resolution_date') }}</span>
                                <span class="fs-13 text-dark">{{ $issue->resolution_date->format('Y-m-d H:i') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Retest Modal --}}
    @if ($canRetestIssue)
        @include('modules.projects.issues._retest_modal')
    @endif

    {{-- Resolve Modal --}}
    @if ($canResolveIssue ?? $canManageIssue)
        <x-ui.modal id="resolveIssueModal" :title="__('projects.resolve_issue')" size="md" :showFooter="false">
            <form method="POST" action="{{ route('projects.issues.update', [$project, $issue]) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="{{ \App\Domains\Projects\Models\Issue::STATUS_RESOLVED }}">

                <div class="mb-3">
                    <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.resolution_notes') }} <span class="text-danger">*</span></label>
                    <x-ui.odoo-form-ui type="textarea" name="resolution_notes" rows="4" :placeholder="__('projects.resolution_notes_placeholder')" required>{{ $issue->resolution_notes }}</x-ui.odoo-form-ui>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="feather-check-square me-1"></i>{{ __('projects.resolve_issue') }}
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    {{-- Upload Document Modal for Issue --}}
    <x-ui.modal id="uploadIssueDocumentModal" :title="__('projects.upload_document')" size="md" :showFooter="false">
        <form method="POST" action="{{ route('projects.documents.store', $project) }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="attachable_type" value="issue">
            <input type="hidden" name="attachable_id" value="{{ $issue->id }}">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.category') }} <span class="text-danger">*</span></label>
                    <x-ui.odoo-form-ui type="select" name="category" required>
                        @foreach (\App\Domains\Projects\Models\ProjectDocument::CATEGORIES as $cat)
                            <option value="{{ $cat }}" @selected($cat === 'qa')>
                                {{ __('projects.document_categories.' . $cat) }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.file_name') }} ({{ __('ui.optional') }})</label>
                    <x-ui.odoo-form-ui type="input" name="title" :placeholder="__('projects.file_name')" />
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.document') }} <span class="text-danger">*</span></label>
                    <input type="file" name="file" class="form-control form-control-sm" required>
                    <div class="form-text fs-11 text-muted">{{ __('projects.max_file_size_hint') }}</div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.file_remarks') }}</label>
                    <x-ui.odoo-form-ui type="textarea" name="remarks" rows="2" :placeholder="__('projects.file_remarks')" />
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
                <button type="submit" class="btn btn-primary">
                    <i class="feather-upload me-1"></i>{{ __('projects.upload_document') }}
                </button>
            </div>
        </form>
    </x-ui.modal>

    @push('scripts')
        <script type="module" src="{{ asset('assets/js/inline-edit/index.js') }}"></script>
    @endpush
@endsection
