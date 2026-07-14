@extends('layouts.duralux')

@section('title', $project->project_code . ' | ' . __('projects.title') . ' | SaaS ERP')
@section('page-title', $project->name)
@section('breadcrumb', __('projects.title') . ' / ' . $project->project_code)

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="javascript:void(0);" onclick="openActivityDrawer('{{ route('projects.activity', $project) }}')" class="btn btn-light">
            <i class="feather-activity me-2"></i>{{ __('projects.activity') }}
        </a>
        @can('update', $project)
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#editProjectModal-{{ $project->id }}">
                <i class="feather-edit-2 me-2"></i>{{ __('projects.edit') }}
            </button>
        @endcan
        <form action="{{ route('projects.destroy', $project) }}" method="POST"
              onsubmit="return confirmFormSubmit(event, @js(__('projects.confirm_delete')));">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i class="feather-trash-2 me-2"></i>{{ __('projects.delete') }}
            </button>
        </form>
        <a href="{{ route('projects.index') }}" class="btn btn-light">
            <i class="feather-arrow-left me-2"></i>{{ __('projects.back') }}
        </a>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        {{-- The edit-project modal shows its own scoped inline errors (see _form-fields.blade.php),
             so this page-level banner is suppressed for that form to avoid showing the same
             errors twice. It still covers tasklist/task forms, which don't have inline errorText. --}}
        @if ($errors->any() && !old('_modal'))
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 pb-3 border-bottom">
            <h4 class="fw-bold text-dark mb-0">
                <i class="feather-briefcase me-2 text-primary"></i>{{ $project->project_code }} — {{ $project->name }}
            </h4>
            @php
                $projectStatusVariant = match ($project->status) {
                    'Active' => 'success',
                    'On Hold' => 'warning',
                    'Completed' => 'primary',
                    'Closed' => 'dark',
                    default => 'secondary',
                };
            @endphp
            <x-ui.badge variant="{{ $projectStatusVariant }}" soft>
                {{ __('projects.statuses.' . $project->status) }}
            </x-ui.badge>
        </div>

        {{-- Identity / Meta Grid --}}
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.client') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $project->customer?->name ?: '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.project_owner') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $project->owner?->name ?: '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.project_manager') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $project->manager?->name ?: '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.priority') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ __('projects.priorities.' . $project->priority) }}</span></div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.start_date') }} / {{ __('projects.end_date') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $project->start_date?->format('d/m/Y') ?: '—' }} – {{ $project->end_date?->format('d/m/Y') ?: '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.billing_method') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $project->billing_method ? __('projects.billing_methods.' . $project->billing_method) : '—' }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.budget_type') }} / {{ __('projects.budget_amount') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $project->budget_type ? __('projects.budget_types.' . $project->budget_type) : '—' }} @if ($project->budget_amount !== null) ({{ number_format((float) $project->budget_amount, 2) }}) @endif</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('projects.budget_hours') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $project->budget_hours !== null ? number_format((float) $project->budget_hours, 2) : '—' }}</span></div>
                </div>
            </div>
            <div class="col-lg-4">
                @include('modules.projects._collaborators')
            </div>
        </div>

        @if ($project->description)
            <div class="mb-4 bg-light p-3 rounded border border-dashed">
                <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-2">{{ __('projects.description') }}</span>
                <p class="mb-0 text-dark fs-13">{{ $project->description }}</p>
            </div>
        @endif

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
            <div class="tab-pane fade {{ $activeProjectTab === 'summary' ? 'show active' : '' }}" id="tab-summary" role="tabpanel" aria-labelledby="tab-summary-tab">
                @include('modules.projects._dashboard-stats')
                @include('modules.projects._project-widgets')
            </div>
            <div class="tab-pane fade {{ $activeProjectTab === 'milestones' ? 'show active' : '' }}" id="tab-milestones" role="tabpanel" aria-labelledby="tab-milestones-tab">
                @include('modules.projects._milestones')
            </div>
            <div class="tab-pane fade {{ $activeProjectTab === 'tasklists' ? 'show active' : '' }}" id="tab-tasklists" role="tabpanel" aria-labelledby="tab-tasklists-tab">
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

    @can('update', $project)
        @include('modules.projects._edit-modal', ['project' => $project, 'customers' => $customers, 'users' => $users])
    @endcan

    @include('modules.projects._modal-reopen-script')
@endsection
