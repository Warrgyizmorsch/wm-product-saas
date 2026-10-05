@extends('layouts.duralux')

@section('title', $project->project_code . ' | ' . __('projects.title') . ' | SaaS ERP')
@section('page-title', $project->name)
@section('breadcrumb', __('projects.title') . ' / ' . $project->project_code)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('projects.index') }}" class="btn btn-light">
            <i class="feather-arrow-left me-2"></i>{{ __('projects.back') }}
        </a>
    </div>
@endsection

@include('modules.projects._panel-styles')

@push('styles')
    <style>
        .project-header-activity-btn {
            height: 32px;
            display: inline-flex;
            align-items: center;
            padding: 0 14px;
        }
    </style>
@endpush

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

        @if ($project->isClosed())
            <div class="alert alert-dark d-flex align-items-center mb-4 border-0 shadow-sm" role="alert" style="background: #f8fafc; border-left: 4px solid #475569 !important;">
                <i class="feather-archive fs-3 me-3 text-secondary"></i>
                <div class="flex-grow-1">
                    <h6 class="alert-heading fw-bold mb-1 text-dark">{{ __('projects.project_is_closed') }}</h6>
                    <p class="mb-0 fs-12 text-muted">
                        {{ __('projects.project_is_closed_description') }}
                        @if ($project->closure_date)
                            — <strong>{{ __('projects.closed_on') }}:</strong> {{ $project->closure_date->format('d M Y') }}
                        @endif
                        @if ($project->closedBy)
                            | <strong>{{ __('projects.closed_by') }}:</strong> {{ $project->closedBy->name }}
                        @endif
                        @if ($project->client_approval_ref)
                            | <strong>{{ __('projects.client_approval_ref') }}:</strong> {{ $project->client_approval_ref }}
                        @endif
                    </p>
                    @if ($project->final_remarks)
                        <div class="mt-1 fs-12 text-secondary fst-italic">
                            "{{ $project->final_remarks }}"
                        </div>
                    @endif
                </div>
                <span class="badge bg-secondary text-uppercase ms-3 px-3 py-2 fs-11">{{ __('projects.statuses.Closed') }}</span>
            </div>
        @endif

        {{-- Header Identity Row --}}
        @php
            $projectStatusVariant = match ($project->status) {
                'Draft' => 'secondary',
                'Active' => 'success',
                'On Hold' => 'warning',
                'Completed' => 'info',
                'Closed' => 'dark',
                'Cancelled' => 'danger',
                default => 'secondary',
            };
        @endphp
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 pb-3 border-bottom">
            <h4 class="fw-bold text-dark mb-0 d-flex flex-wrap align-items-center gap-2">
                <span>
                    <i class="feather-briefcase me-2 text-primary"></i>{{ $project->project_code }} —
                    @if ($canUpdateProject)
                        <x-ui.inline-edit field="name" :value="$project->name" :url="route('projects.field', $project)" />
                    @else
                        {{ $project->name }}
                    @endif
                </span>
                <x-ui.badge variant="{{ $projectStatusVariant }}" soft>
                    {{ __('projects.statuses.' . ($project->status ?: 'Draft')) }}
                </x-ui.badge>
            </h4>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if (!$project->isClosed() && $canCloseProject)
                    <button type="button" class="btn btn-outline-danger project-header-activity-btn" data-bs-toggle="modal" data-bs-target="#modalCloseProject">
                        <i class="feather-check-circle me-1"></i>{{ __('projects.close_project') }}
                    </button>
                @endif
                <a href="javascript:void(0);" onclick="openActivityDrawer('{{ route('projects.activity', $project) }}')"
                    class="btn btn-primary project-header-activity-btn">
                    <i class="feather-activity me-2"></i>{{ __('projects.activity') }}
                </a>
                @can('delete', $project)
                    <x-ui.action-dropdown id="projectHeaderActions">
                        <li>
                            <form action="{{ route('projects.destroy', $project) }}" method="POST"
                                onsubmit="return confirmFormSubmit(event, @js(__('projects.confirm_delete')));">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="feather-trash-2 me-2"></i>{{ __('projects.delete') }}
                                </button>
                            </form>
                        </li>
                    </x-ui.action-dropdown>
                @endcan
            </div>
        </div>

        {{-- Identity / Meta Grid --}}
        <div class="accordion mb-4 project-details-accordion" id="projectDetailsAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#projectDetailsCollapse" aria-expanded="true"
                        aria-controls="projectDetailsCollapse">
                        <i class="feather-info me-2 text-primary"></i>{{ __('projects.details_and_collaborators') }}
                    </button>
                </h2>
                <div id="projectDetailsCollapse" class="accordion-collapse collapse show"
                    data-bs-parent="#projectDetailsAccordion">
                    <div class="accordion-body">
                        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.client') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                @php
                                    $clientOptions = $customers->pluck('name', 'id')
                                        ->prepend(__('projects.none_option'), '');
                                @endphp
                                <x-ui.inline-edit field="customer_id" :value="$project->customer_id"
                                    :url="route('projects.field', $project)" type="select2" :options="$clientOptions" :label="__('projects.client')" />
                            @else
                                {{ $project->customer?->name ?: '—' }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('projects.project_owner') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                @if ($activeMemberOptions->isEmpty())
                                    @include('modules.projects._no_collaborators_notice')
                                @else
                                    @php
                                        $ownerOptions = $activeMemberOptions->pluck('name', 'id');
                                    @endphp
                                    <x-ui.inline-edit field="owner_id" :value="$project->owner_id" :url="route('projects.field', $project)" type="select2" :options="$ownerOptions" :label="__('projects.project_owner')" />
                                @endif
                            @else
                                {{ $project->owner?->name ?: '—' }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('projects.project_manager') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                @if ($activeMemberOptions->isEmpty())
                                    @include('modules.projects._no_collaborators_notice')
                                @else
                                    @php
                                        $managerOptions = $activeMemberOptions->pluck('name', 'id')
                                            ->prepend(__('projects.none_option'), '');
                                    @endphp
                                    <x-ui.inline-edit field="manager_id" :value="$project->manager_id" :url="route('projects.field', $project)" type="select2" :options="$managerOptions" :label="__('projects.project_manager')" />
                                @endif
                            @else
                                {{ $project->manager?->name ?: '—' }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.priority') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                @php
                                    $priorityOptions = collect(\App\Domains\Projects\Models\Project::PRIORITIES)
                                        ->mapWithKeys(fn($priority) => [$priority => __('projects.priorities.' . $priority)]);
                                @endphp
                                <x-ui.inline-edit field="priority" :value="$project->priority" :url="route('projects.field', $project)" type="select" :options="$priorityOptions" :label="__('projects.priority')" />
                            @else
                                {{ __('projects.priorities.' . $project->priority) }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.status') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                @php
                                    $statusOptions = collect($statusTransitions)
                                        ->mapWithKeys(fn($status) => [$status => __('projects.statuses.' . $status)]);
                                @endphp
                                <x-ui.inline-edit field="status" :value="$project->status" :url="route('projects.field', $project)" type="select" :options="$statusOptions" :label="__('projects.status')" />
                            @else
                                {{ __('projects.statuses.' . ($project->status ?: 'Draft')) }}
                            @endif
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.start_date') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                <x-ui.inline-edit field="start_date" :value="$project->start_date" :url="route('projects.field', $project)" type="date" :label="__('projects.start_date')" />
                            @else
                                {{ $project->start_date?->format('d/m/Y') ?: '—' }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.end_date') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                <x-ui.inline-edit field="end_date" :value="$project->end_date" :url="route('projects.field', $project)" type="date" :label="__('projects.end_date')" />
                            @else
                                {{ $project->end_date?->format('d/m/Y') ?: '—' }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('projects.billing_method') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                @php
                                    $billingMethodOptions = collect(\App\Domains\Projects\Models\Project::BILLING_METHODS)
                                        ->mapWithKeys(fn($method) => [$method => __('projects.billing_methods.' . $method)])
                                        ->prepend(__('projects.none_option'), '');
                                @endphp
                                <x-ui.inline-edit field="billing_method" :value="$project->billing_method"
                                    :url="route('projects.field', $project)" type="select2" :options="$billingMethodOptions" :label="__('projects.billing_method')" />
                            @else
                                {{ $project->billing_method ? __('projects.billing_methods.' . $project->billing_method) : '—' }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('projects.budget_type') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                @php
                                    $budgetTypeOptions = collect(\App\Domains\Projects\Models\Project::BUDGET_TYPES)
                                        ->mapWithKeys(fn($type) => [$type => __('projects.budget_types.' . $type)])
                                        ->prepend(__('projects.none_option'), '');
                                @endphp
                                <x-ui.inline-edit field="budget_type" :value="$project->budget_type"
                                    :url="route('projects.field', $project)" type="select2" :options="$budgetTypeOptions" :label="__('projects.budget_type')" />
                            @else
                                {{ $project->budget_type ? __('projects.budget_types.' . $project->budget_type) : '—' }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('projects.budget_amount') }} ({{ active_currency_symbol() }}):</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                <x-ui.inline-edit field="budget_amount" :value="$project->budget_amount"
                                    :url="route('projects.field', $project)" type="number" :label="__('projects.budget_amount') . ' (' . active_currency_symbol() . ')'" />
                            @else
                                {{ $project->budget_amount !== null ? format_currency($project->budget_amount) : '—' }}
                            @endif
                        </span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('projects.budget_hours') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                <x-ui.inline-edit field="budget_hours" :value="$project->budget_hours"
                                    :url="route('projects.field', $project)" type="number" :label="__('projects.budget_hours')" />
                            @else
                                {{ $project->budget_hours !== null ? number_format((float) $project->budget_hours, 2) : '—' }}
                            @endif
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                @include('modules.projects._collaborators')
            </div>
                        </div>
                        <div class="mt-4 pt-3 border-top">
                            <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-2">{{ __('projects.description') }}</span>
                            @if ($canUpdateProject)
                                <div class="text-dark fs-13">
                                    <x-ui.inline-edit field="description" :value="$project->description" :url="route('projects.field', $project)" type="textarea" :label="__('projects.description')" />
                                </div>
                            @else
                                <p class="mb-0 text-dark fs-13">{{ $project->description ?: '—' }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab Navigation --}}
        @php
            $allowedTabs = ['summary', 'milestones', 'timeline', 'issues', 'documents', 'reviews', 'billing'];
            $activeProjectTab = in_array(request('tab'), $allowedTabs, true)
                ? request('tab')
                : (in_array(old('_milestone_form'), ['add', 'edit'], true)
                    ? 'milestones'
                    : (old('_issue_form')
                        ? 'issues'
                        : (old('_document_form')
                            ? 'documents'
                            : (old('_review_form') || old('_cr_form') ? 'reviews' : 'summary'))));

            $projectDetailTabs = [
                ['id' => 'tab-summary', 'label' => __('projects.summary'), 'icon' => 'feather-grid', 'active' => $activeProjectTab === 'summary'],
                ['id' => 'tab-milestones', 'label' => __('projects.milestones'), 'icon' => 'feather-flag', 'active' => $activeProjectTab === 'milestones'],
                ['id' => 'tab-timeline', 'label' => __('projects.timeline'), 'icon' => 'feather-calendar', 'active' => $activeProjectTab === 'timeline'],
            ];

            if ($canViewIssues) {
                $projectDetailTabs[] = [
                    'id' => 'tab-issues',
                    'label' => __('projects.issues') . ($issues->isNotEmpty() ? ' (' . $issues->count() . ')' : ''),
                    'icon' => 'feather-alert-circle',
                    'active' => $activeProjectTab === 'issues',
                ];
            }

            if ($canViewDocuments) {
                $projectDetailTabs[] = [
                    'id' => 'tab-documents',
                    'label' => __('projects.documents') . ($documents->isNotEmpty() ? ' (' . $documents->count() . ')' : ''),
                    'icon' => 'feather-folder',
                    'active' => $activeProjectTab === 'documents',
                ];
            }

            if ($canViewReviews || $canViewCRs) {
                $reviewsTotalCount = $reviews->count() + $changeRequests->count();
                $projectDetailTabs[] = [
                    'id' => 'tab-reviews',
                    'label' => __('projects.reviews_and_cr') . ($reviewsTotalCount > 0 ? ' (' . $reviewsTotalCount . ')' : ''),
                    'icon' => 'feather-check-circle',
                    'active' => $activeProjectTab === 'reviews',
                ];
            }

            if ($canViewBilling) {
                $invoicesCount = $billingSummary['invoices_count'] ?? 0;
                $projectDetailTabs[] = [
                    'id'     => 'tab-billing',
                    'label'  => __('projects.billing') . ($invoicesCount > 0 ? ' (' . $invoicesCount . ')' : ''),
                    'icon'   => 'feather-file-text',
                    'active' => $activeProjectTab === 'billing',
                ];
            }
        @endphp
        <x-ui.horizontal-tabs id="projectDetailsTabs" :tabs="$projectDetailTabs" :syncUrl="true" />

        <div class="tab-content mt-3">
            <div class="tab-pane fade {{ $activeProjectTab === 'summary' ? 'show active' : '' }}" id="tab-summary"
                role="tabpanel" aria-labelledby="tab-summary-tab">
                @include('modules.projects._dashboard-stats')
                @include('modules.projects._project-widgets')
            </div>
            <div class="tab-pane fade {{ $activeProjectTab === 'milestones' ? 'show active' : '' }}" id="tab-milestones"
                role="tabpanel" aria-labelledby="tab-milestones-tab">
                @include('modules.projects._milestones')
            </div>
            <div class="tab-pane fade {{ $activeProjectTab === 'timeline' ? 'show active' : '' }}" id="tab-timeline"
                role="tabpanel" aria-labelledby="tab-timeline-tab">
                @include('modules.projects._timeline', ['project' => $project, 'milestoneId' => null, 'milestones' => $milestones])
            </div>
            @if ($canViewIssues)
                <div class="tab-pane fade {{ $activeProjectTab === 'issues' ? 'show active' : '' }}" id="tab-issues"
                    role="tabpanel" aria-labelledby="tab-issues-tab">
                    @include('modules.projects._issues')
                </div>
            @endif
            @if ($canViewDocuments)
                <div class="tab-pane fade {{ $activeProjectTab === 'documents' ? 'show active' : '' }}" id="tab-documents"
                    role="tabpanel" aria-labelledby="tab-documents-tab">
                    @include('modules.projects._documents')
                </div>
            @endif
            @if ($canViewReviews || $canViewCRs)
                <div class="tab-pane fade {{ $activeProjectTab === 'reviews' ? 'show active' : '' }}" id="tab-reviews"
                    role="tabpanel" aria-labelledby="tab-reviews-tab">
                    @include('modules.projects._reviews')
                </div>
            @endif
            @if ($canViewBilling)
                <div class="tab-pane fade {{ $activeProjectTab === 'billing' ? 'show active' : '' }}" id="tab-billing"
                    role="tabpanel" aria-labelledby="tab-billing-tab">
                    @include('modules.projects._billing')
                </div>
            @endif
        </div>

        <x-ui.drawer id="activityLogDrawer" title="Activity History" position="end" style="width: 480px; max-width: 100%;">
            <div id="activityLogDrawerContent">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                    <div class="fs-12">{{ __('ui.loading') }}</div>
                </div>
            </div>
        </x-ui.drawer>
    </div>

    @if ($canCreateIssues)
        @include('modules.projects.issues._modal')
    @endif
    @if ($canUploadDocuments)
        @include('modules.projects.documents._upload_modal')
    @endif
    @if ($canViewBilling && $canGenerateInvoice)
        @include('modules.projects._generate_invoice_modal')
    @endif
    @if (!$project->isClosed() && $canCloseProject)
        @include('modules.projects._close_project_modal')
    @endif

    @push('scripts')
        <script type="module" src="{{ asset('assets/js/inline-edit/index.js') }}"></script>
        <script src="{{ asset('assets/js/milestones/inline-create.js') }}"></script>
        <script src="{{ asset('assets/js/projects/collaborators.js') }}"></script>
        <script>
            function openActivityDrawer(url) {
                var drawerEl = document.getElementById('activityLogDrawer');
                if (!drawerEl) return;

                var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
                offcanvas.show();

                var contentEl = document.getElementById('activityLogDrawerContent');
                contentEl.innerHTML = `
                            <div class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                                <div class="fs-12">{{ __('ui.loading') }}</div>
                            </div>
                        `;

                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.text())
                    .then(html => {
                        contentEl.innerHTML = html;
                    })
                    .catch(err => {
                        console.error(err);
                        contentEl.innerHTML = `
                                <div class="text-center py-5 text-danger">
                                    <i class="feather-alert-triangle fs-2 mb-2 d-block"></i>
                                    Failed to load activities.
                                </div>
                            `;
                    });
            }
        </script>
    @endpush

    @include('modules.projects._modal-reopen-script')
@endsection