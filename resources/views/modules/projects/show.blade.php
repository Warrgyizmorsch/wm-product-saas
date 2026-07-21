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
        .project-details-accordion .accordion-item {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .project-details-accordion .accordion-button {
            border-left: 3px solid var(--bs-primary);
        }
        .project-details-accordion .accordion-body {
            border-top: 1px solid #eef0f5;
        }
        .project-header-activity-btn {
            height: 32px;
            display: inline-flex;
            align-items: center;
            padding: 0 14px;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel project-show-panel">
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
                    {{ __('projects.statuses.' . $project->status) }}
                </x-ui.badge>
            </h4>
            <div class="d-flex flex-wrap align-items-center gap-2">
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
                        <i class="feather-info me-2 text-primary"></i>{{ __('projects.details') }}
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
                                @php
                                    $ownerOptions = $users->pluck('name', 'id');
                                @endphp
                                <x-ui.inline-edit field="owner_id" :value="$project->owner_id" :url="route('projects.field', $project)" type="select2" :options="$ownerOptions" :label="__('projects.project_owner')" />
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
                                @php
                                    $managerOptions = $users->pluck('name', 'id')
                                        ->prepend(__('projects.none_option'), '');
                                @endphp
                                <x-ui.inline-edit field="manager_id" :value="$project->manager_id" :url="route('projects.field', $project)" type="select2" :options="$managerOptions" :label="__('projects.project_manager')" />
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
                                {{ __('projects.statuses.' . $project->status) }}
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
                            class="fw-semibold text-muted fs-13">{{ __('projects.budget_amount') }}:</span></div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if ($canUpdateProject)
                                <x-ui.inline-edit field="budget_amount" :value="$project->budget_amount"
                                    :url="route('projects.field', $project)" type="number" :label="__('projects.budget_amount')" />
                            @else
                                {{ $project->budget_amount !== null ? number_format((float) $project->budget_amount, 2) : '—' }}
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
            $activeProjectTab = in_array(request('tab'), ['summary', 'milestones', 'tasklists'], true)
                ? request('tab')
                : (in_array(old('_tasklist_form'), ['add', 'edit'], true) ? 'tasklists'
                    : (in_array(old('_milestone_form'), ['add', 'edit'], true) ? 'milestones' : 'summary'));
            $projectDetailTabs = [
                ['id' => 'tab-summary', 'label' => __('projects.summary'), 'icon' => 'feather-grid', 'active' => $activeProjectTab === 'summary'],
                ['id' => 'tab-milestones', 'label' => __('projects.milestones'), 'icon' => 'feather-flag', 'active' => $activeProjectTab === 'milestones'],
                ['id' => 'tab-tasklists', 'label' => __('projects.tasklists'), 'icon' => 'feather-list', 'active' => $activeProjectTab === 'tasklists'],
            ];
        @endphp
        <x-ui.horizontal-tabs id="projectDetailsTabs" :tabs="$projectDetailTabs" />

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
            <div class="tab-pane fade {{ $activeProjectTab === 'tasklists' ? 'show active' : '' }}" id="tab-tasklists"
                role="tabpanel" aria-labelledby="tab-tasklists-tab">
                @include('modules.projects._tasklists')
            </div>
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

    @push('scripts')
        <script type="module" src="{{ asset('assets/js/inline-edit/index.js') }}"></script>
        <script src="{{ asset('assets/js/milestones/inline-create.js') }}"></script>
        <script src="{{ asset('assets/js/tasklists/inline-create.js') }}"></script>
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