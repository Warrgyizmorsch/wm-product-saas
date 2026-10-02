@extends('layouts.duralux')

@section('title', '360° Multi-Rater Feedback | HRMS')
@section('page-title', '360° Multi-Rater Feedback')
@section('breadcrumb', 'HRMS / Performance / 360° Feedback')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createCycleModal" class="fw-bold">
            Create Feedback Cycle
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .avatar-initials {
            width: 36px;
            height: 36px;
            min-width: 36px;
            min-height: 36px;
            border-radius: 8px;
            background-color: rgba(var(--bs-primary-rgb), 0.12) !important;
            color: var(--bs-primary) !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
            border: 1px solid rgba(var(--bs-primary-rgb), 0.2);
            line-height: 1;
        }
        .review-card {
            background-color: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease !important;
            padding: 22px !important;
        }
        .review-card:hover {
            border-color: #cbd5e1 !important;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important;
            transform: translateY(-2px);
        }
        .cycle-stats-strip {
            background-color: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px !important;
            padding: 12px 14px !important;
        }
        .cycle-info-pill {
            background-color: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
        }
        #createCompetencyModal .odoo-form-label {
            width: 155px !important;
            white-space: nowrap !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
<div class="container-fluid p-0">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @php
            $feedbackTabs = [
                [
                    'id' => 'cycles',
                    'label' => 'Feedback Cycles & Campaigns',
                    'active' => ($activeTab === 'cycles'),
                    'icon' => 'feather-layers',
                ],
                [
                    'id' => 'my-reviews',
                    'label' => 'My Reviews to Complete' . ($statistics['my_pending_count'] > 0 ? " ({$statistics['my_pending_count']})" : ''),
                    'active' => ($activeTab === 'my_reviews'),
                    'icon' => 'feather-edit-3',
                ],
                [
                    'id' => 'my-evaluations',
                    'label' => 'My 360 Evaluations & Reports',
                    'active' => ($activeTab === 'my_evaluations'),
                    'icon' => 'feather-user-check',
                ],
                [
                    'id' => 'peer-approvals',
                    'label' => 'Peer Approvals' . ($statistics['pending_approvals_count'] > 0 ? " ({$statistics['pending_approvals_count']})" : ''),
                    'active' => ($activeTab === 'peer_approvals'),
                    'icon' => 'feather-check-square',
                ],
                [
                    'id' => 'competencies-questions',
                    'label' => 'Competencies & Question Bank',
                    'active' => ($activeTab === 'competencies_questions'),
                    'icon' => 'feather-help-circle',
                ],
            ];
        @endphp

        <!-- Navigation Tabs Bar -->
        <div class="mb-4 border-bottom pb-2">
            <x-ui.horizontal-tabs id="feedback360Tabs" :tabs="$feedbackTabs" />
        </div>

        <!-- Tab Content Workspaces -->
        <div class="tab-content" id="feedback360TabsContent">

            <!-- ================================================================= -->
            <!-- TAB 1: CYCLES & CAMPAIGNS -->
            <!-- ================================================================= -->
            <div class="tab-pane fade {{ $activeTab === 'cycles' ? 'show active' : '' }}" id="cycles" role="tabpanel">
                
                <!-- Section Header & Search/Sort/Filter Toolbar -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark mb-0 fs-16">Feedback Cycles & Campaigns</h5>
                        <small class="text-muted">Manage active 360 appraisal cycles, stages, and participants</small>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Standard Search Bar -->
                        <form method="GET" action="{{ route('hrms.feedback360.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 240px; height: 38px;">
                            <input type="hidden" name="active_tab" value="cycles">
                            <input type="hidden" name="status" value="{{ request('status') }}">
                            <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                            <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input 
                                type="text" 
                                name="search" 
                                class="form-control border-0 bg-transparent p-0 fs-13" 
                                placeholder="Search cycles..." 
                                value="{{ request('search') }}"
                                style="box-shadow: none;"
                            >
                            @if(request('search'))
                                <a href="{{ route('hrms.feedback360.index', ['active_tab' => 'cycles', 'status' => request('status'), 'sort_by' => request('sort_by'), 'sort_dir' => request('sort_dir')]) }}" class="text-muted ms-1"><i class="feather-x"></i></a>
                            @endif
                        </form>

                        <!-- Standard Sort Dropdown Component -->
                        <x-ui.sort-dropdown label="Sort">
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ (!request('sort_by') || request('sort_by') === 'created_at') ? 'active' : '' }}" href="{{ route('hrms.feedback360.index', array_merge(request()->all(), ['sort_by' => 'created_at', 'sort_dir' => 'desc', 'active_tab' => 'cycles'])) }}">
                                <span>Newest First</span>
                                @if(!request('sort_by') || request('sort_by') === 'created_at') <i class="feather-check ms-3 text-primary"></i> @endif
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort_by') === 'name' ? 'active' : '' }}" href="{{ route('hrms.feedback360.index', array_merge(request()->all(), ['sort_by' => 'name', 'sort_dir' => 'asc', 'active_tab' => 'cycles'])) }}">
                                <span>Name (A-Z)</span>
                                @if(request('sort_by') === 'name') <i class="feather-check ms-3 text-primary"></i> @endif
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort_by') === 'start_date' ? 'active' : '' }}" href="{{ route('hrms.feedback360.index', array_merge(request()->all(), ['sort_by' => 'start_date', 'sort_dir' => 'desc', 'active_tab' => 'cycles'])) }}">
                                <span>Start Date</span>
                                @if(request('sort_by') === 'start_date') <i class="feather-check ms-3 text-primary"></i> @endif
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Standard Filter Component -->
                        <x-ui.filter label="Filter" :resetUrl="route('hrms.feedback360.index', ['active_tab' => 'cycles'])">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                            <form method="GET" action="{{ route('hrms.feedback360.index') }}">
                                <input type="hidden" name="active_tab" value="cycles">
                                <input type="hidden" name="search" value="{{ request('search') }}">
                                <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                                <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
                                
                                <x-ui.odoo-form-ui type="select" label="Cycle Status" name="status">
                                    <option value="">All Stages</option>
                                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="nomination" {{ request('status') === 'nomination' ? 'selected' : '' }}>Nomination Phase</option>
                                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress (Collecting)</option>
                                    <option value="review" {{ request('status') === 'review' ? 'selected' : '' }}>Manager Review</option>
                                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                                </x-ui.odoo-form-ui>

                                <div class="pt-2 d-flex gap-2">
                                    <x-ui.button type="submit" variant="primary" size="sm" class="w-100 fw-bold">Apply Filters</x-ui.button>
                                    <x-ui.button variant="light" size="sm" href="{{ route('hrms.feedback360.index', ['active_tab' => 'cycles']) }}" class="w-100 border">Reset</x-ui.button>
                                </div>
                            </form>
                        </x-ui.filter>
                    </div>
                </div>

                @if($cycles->isEmpty())
                    <div class="text-center py-5 border rounded bg-light">
                        <div class="avatar-initials mx-auto mb-3" style="width: 56px; height: 56px; font-size: 22px;">
                            <i class="feather-layers"></i>
                        </div>
                        <h6 class="fw-bold text-dark">No 360 Feedback Cycles Found</h6>
                        <p class="text-muted fs-13 mb-0">Use the <strong>"Create Feedback Cycle"</strong> button at the top right to start a multi-rater review campaign.</p>
                    </div>
                @else
                    <div class="row g-3">
                        @foreach($cycles as $cycle)
                            @php
                                $badgeVariant = match($cycle->status) {
                                    'draft'       => 'warning',
                                    'nomination'  => 'info',
                                    'in_progress' => 'primary',
                                    'review'      => 'warning',
                                    'completed'   => 'success',
                                    'closed'      => 'secondary',
                                    default       => 'light',
                                };
                                $badgeLabel = match($cycle->status) {
                                    'draft'       => 'Draft',
                                    'nomination'  => 'Nomination Phase',
                                    'in_progress' => 'In Progress (Collecting)',
                                    'review'      => 'Manager Review',
                                    'completed'   => 'Completed',
                                    'closed'      => 'Closed',
                                    default       => ucfirst($cycle->status),
                                };
                                $completionPct = $cycle->completion_rate;
                            @endphp
                            <div class="col-xl-4 col-md-6">
                                <div class="review-card h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <x-ui.badge :variant="$badgeVariant" soft>{{ $badgeLabel }}</x-ui.badge>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon btn-light border rounded-circle" type="button" data-bs-toggle="dropdown" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">
                                                <i class="feather-more-vertical text-muted fs-13"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <a class="dropdown-item fs-13" href="{{ route('hrms.feedback360.cycles.show', $cycle->id) }}">
                                                    <i class="feather-eye me-2 text-primary"></i> View Dashboard
                                                </a>
                                                <button class="dropdown-item fs-13" data-bs-toggle="modal" data-bs-target="#addParticipantsModal{{ $cycle->id }}">
                                                    <i class="feather-user-plus me-2 text-success"></i> Add Participants
                                                </button>
                                                <div class="dropdown-divider"></div>
                                                <button type="button" class="dropdown-item text-danger fs-13" data-bs-toggle="modal" data-bs-target="#deleteCycleModal{{ $cycle->id }}">
                                                    <i class="feather-trash-2 me-2"></i> Delete Cycle
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Cycle Confirmation Modal -->
                                    <x-ui.modal id="deleteCycleModal{{ $cycle->id }}" title="Delete Feedback Cycle" size="sm" :centered="true" :showFooter="false">
                                        <div class="text-center py-2">
                                            <div class="avatar-initials bg-danger-subtle text-danger mx-auto mb-3" style="width: 50px; height: 50px; font-size: 20px;">
                                                <i class="feather-trash-2"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">Delete Feedback Cycle?</h6>
                                            <p class="text-muted fs-12 mb-3">
                                                Are you sure you want to delete <strong>{{ $cycle->name }}</strong>? This action will remove its roster and responses.
                                            </p>
                                            <form action="{{ route('hrms.feedback360.cycles.destroy', $cycle->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <div class="d-flex justify-content-center gap-2">
                                                    <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
                                                    <x-ui.button variant="danger" size="sm" type="submit" class="fw-bold">Yes, Delete</x-ui.button>
                                                </div>
                                            </form>
                                        </div>
                                    </x-ui.modal>

                                    <h6 class="fw-bold text-dark mb-1.5 fs-15 lh-sm">
                                        <a href="{{ route('hrms.feedback360.cycles.show', $cycle->id) }}" class="text-dark text-decoration-none hover-primary">
                                            {{ $cycle->name }}
                                        </a>
                                    </h6>
                                    <div class="text-muted fs-12 mb-3 d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge bg-light text-dark border px-2 py-0.5 rounded font-monospace fs-11">{{ $cycle->code }}</span>
                                        <span class="text-muted">&bull;</span>
                                        <span>{{ $cycle->start_date->format('M d, Y') }} &ndash; {{ $cycle->end_date->format('M d, Y') }}</span>
                                    </div>

                                    <!-- Completion Progress -->
                                    <div class="mb-3">
                                         <div class="d-flex justify-content-between align-items-center fs-12 mb-1.5">
                                             <span class="text-muted fw-semibold">Review Completion</span>
                                             <span class="fw-bold text-dark fs-12">{{ $completionPct }}%</span>
                                         </div>
                                         <div class="progress" style="height: 6px; border-radius: 4px; background-color: #f1f5f9;">
                                             <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $completionPct }}%; border-radius: 4px;"></div>
                                         </div>
                                     </div>

                                     <!-- Quick Stats Strip -->
                                     <div class="cycle-stats-strip d-flex justify-content-between align-items-center mb-4 mt-auto">
                                         <div class="text-center flex-grow-1 py-0.5">
                                             <span class="text-muted d-block fs-10 text-uppercase fw-bold mb-0.5" style="letter-spacing: 0.5px;">Reviewees</span>
                                             <strong class="text-dark fs-14">{{ $cycle->participants_count }}</strong>
                                         </div>
                                         <div class="border-start text-center flex-grow-1 py-0.5">
                                             <span class="text-muted d-block fs-10 text-uppercase fw-bold mb-0.5" style="letter-spacing: 0.5px;">Total Reviews</span>
                                             <strong class="text-dark fs-14">{{ $cycle->nominations_count }}</strong>
                                         </div>
                                         <div class="border-start text-center flex-grow-1 py-0.5">
                                             <span class="text-muted d-block fs-10 text-uppercase fw-bold mb-0.5" style="letter-spacing: 0.5px;">Peer Privacy</span>
                                             <strong class="text-dark fs-13">{{ $cycle->is_peer_anonymous ? 'Anonymous' : 'Named' }}</strong>
                                         </div>
                                     </div>

                                     <div>
                                         <x-ui.button variant="primary" class="w-100 fw-bold justify-content-center" href="{{ route('hrms.feedback360.cycles.show', $cycle->id) }}">
                                             Manage Roster & Reports
                                             <i class="feather-arrow-right ms-1.5 fs-13"></i>
                                         </x-ui.button>
                                     </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        {{ $cycles->links() }}
                    </div>
                @endif
            </div>

            <!-- ================================================================= -->
            <!-- TAB 2: MY REVIEWS TO COMPLETE -->
            <!-- ================================================================= -->
            <div class="tab-pane fade {{ $activeTab === 'my_reviews' ? 'show active' : '' }}" id="my-reviews" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark mb-0 fs-16">My Reviews to Complete</h5>
                        <small class="text-muted">Complete multi-rater evaluations for your colleagues, direct manager, and direct reports</small>
                    </div>
                </div>

                <!-- Pending Section -->
                <h6 class="fs-13 text-uppercase text-muted fw-bold mb-3 mt-4">
                    <i class="feather-clock me-1 text-warning"></i> Pending Reviews ({{ $myPendingReviews->count() }})
                </h6>

                @if($myPendingReviews->isEmpty())
                    <div class="alert alert-light border text-muted fs-13 py-3 mb-4">
                        <i class="feather-check-circle text-success me-2"></i>You have no pending feedback evaluations at this time. Great job!
                    </div>
                @else
                    <div class="row g-3 mb-4">
                        @foreach($myPendingReviews as $rev)
                            @php
                                $roleVariant = match($rev->reviewer_type) {
                                    'self'          => 'primary',
                                    'manager'       => 'warning',
                                    'peer'          => 'info',
                                    'direct_report' => 'success',
                                    default         => 'secondary',
                                };
                                $roleLabel = match($rev->reviewer_type) {
                                    'self'          => 'Self Review',
                                    'manager'       => 'Manager Appraisal',
                                    'peer'          => 'Peer Review (' . ($rev->is_anonymous ? 'Anonymous' : 'Named') . ')',
                                    'direct_report' => 'Direct Report Review',
                                    default         => ucfirst(str_replace('_', ' ', $rev->reviewer_type)),
                                };
                            @endphp
                            <div class="col-xl-4 col-md-6">
                                <div class="review-card h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <x-ui.badge :variant="$roleVariant" soft>{{ $roleLabel }}</x-ui.badge>
                                            @if($rev->status === 'in_progress')
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0.5 fs-10 fw-semibold">Draft Saved</span>
                                            @endif
                                        </div>
                                        <span class="text-muted fs-11">
                                            Due: <strong>{{ $rev->cycle?->submission_deadline ? $rev->cycle->submission_deadline->format('M d') : 'Open' }}</strong>
                                        </span>
                                    </div>

                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <div class="avatar-initials">
                                            {{ substr($rev->employee?->first_name ?? 'E', 0, 1) }}{{ substr($rev->employee?->last_name ?? '', 0, 1) }}
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="fw-bold text-dark mb-0.5 fs-15 text-truncate">{{ $rev->employee?->full_name }}</h6>
                                            <span class="text-muted fs-12 text-truncate d-block">{{ $rev->employee?->designation->name ?? 'Staff' }} &bull; {{ $rev->employee?->department->name ?? 'General' }}</span>
                                        </div>
                                    </div>

                                    <div class="cycle-info-pill d-flex align-items-center gap-2 text-dark mb-4">
                                        <i class="feather-layers text-primary fs-14 flex-shrink-0"></i>
                                        <span class="text-truncate fw-semibold fs-12">{{ $rev->cycle?->name }}</span>
                                    </div>

                                    <div class="mt-auto">
                                        <x-ui.button variant="primary" class="w-100 fw-bold justify-content-center" icon="feather-edit-2" href="{{ route('hrms.feedback360.review', $rev->id) }}">
                                            {{ $rev->status === 'in_progress' ? 'Continue Evaluation' : 'Start Evaluation' }}
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Completed Section -->
                @if($mySubmittedReviews->isNotEmpty())
                    <h6 class="fs-13 text-uppercase text-muted fw-bold mb-3 mt-4">
                        <i class="feather-check-circle me-1 text-success"></i> Submitted Reviews ({{ $mySubmittedReviews->count() }})
                    </h6>

                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="table-light">
                                <tr>
                                    <th>Reviewee</th>
                                    <th>Evaluation Role</th>
                                    <th>Feedback Cycle</th>
                                    <th>Submitted On</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mySubmittedReviews as $sub)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar-initials" style="width: 28px; height: 28px; font-size: 11px;">
                                                    {{ substr($sub->employee?->first_name ?? 'E', 0, 1) }}
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark">{{ $sub->employee?->full_name }}</span>
                                                    <span class="text-muted fs-11 d-block">{{ $sub->employee?->designation->name ?? '' }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border rounded-pill px-2">
                                                {{ ucfirst(str_replace('_', ' ', $sub->reviewer_type)) }}
                                            </span>
                                        </td>
                                        <td>{{ $sub->cycle?->name }}</td>
                                        <td>{{ $sub->submitted_at ? $sub->submitted_at->format('M d, Y H:i') : '-' }}</td>
                                        <td class="text-end">
                                            <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1">
                                                <i class="feather-check me-1"></i> Submitted
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- ================================================================= -->
            <!-- TAB 3: MY 360 EVALUATIONS & REPORTS -->
            <!-- ================================================================= -->
            <div class="tab-pane fade {{ $activeTab === 'my_evaluations' ? 'show active' : '' }}" id="my-evaluations" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark mb-0 fs-16">My 360 Evaluations & Reports</h5>
                        <small class="text-muted">Track feedback collection progress for yourself, nominate peers, and review published radar reports</small>
                    </div>
                </div>

                @if($myEvaluations->isEmpty())
                    <div class="text-center py-5 border rounded bg-light">
                        <div class="avatar-initials mx-auto mb-3" style="width: 56px; height: 56px; font-size: 22px;">
                            <i class="feather-user-check"></i>
                        </div>
                        <h6 class="fw-bold text-dark">No Active 360 Evaluations</h6>
                        <p class="text-muted fs-13">You are not currently enrolled in any active feedback cycles.</p>
                    </div>
                @else
                    <div class="row g-3">
                        @foreach($myEvaluations as $eval)
                            @php
                                $totalRaters = $eval->nominations->count();
                                $completedRaters = $eval->nominations->where('status', 'completed')->count();
                                $pct = $totalRaters > 0 ? round(($completedRaters / $totalRaters) * 100) : 0;
                                $byType = $eval->nominations->groupBy('reviewer_type');
                            @endphp
                            <div class="col-xl-6 col-12">
                                <div class="review-card h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold text-dark mb-0 fs-15">{{ $eval->cycle?->name }}</h6>
                                        <x-ui.badge :variant="$eval->status === 'published' ? 'success' : 'primary'" soft>
                                            {{ $eval->status === 'published' ? 'Report Ready' : 'In Progress' }}
                                        </x-ui.badge>
                                    </div>

                                    <div class="text-muted fs-12 mb-3">
                                        Manager: <strong class="text-dark">{{ $eval->manager?->full_name ?? 'Unassigned' }}</strong> &bull; Period: {{ $eval->cycle?->start_date ? $eval->cycle->start_date->format('M Y') : 'N/A' }} &ndash; {{ $eval->cycle?->end_date ? $eval->cycle->end_date->format('M Y') : 'N/A' }}
                                    </div>

                                    <!-- Progress stats -->
                                    <div class="bg-light p-3 rounded-3 border mb-3">
                                        <div class="d-flex justify-content-between align-items-center fs-12 mb-2">
                                            <span class="text-muted fw-semibold">Feedback Responses Collected</span>
                                            <span class="fw-bold text-dark fs-12">{{ $completedRaters }} / {{ $totalRaters }} Raters ({{ $pct }}%)</span>
                                        </div>
                                        <div class="progress" style="height: 6px; border-radius: 4px; background-color: #e2e8f0;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $pct }}%; border-radius: 4px;"></div>
                                        </div>
                                    </div>

                                    <!-- Structured Reviewers Status Roster -->
                                    <div class="mb-3">
                                        <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-2" style="letter-spacing: 0.5px;">Reviewers Roster Status</span>
                                        <div class="row g-2">
                                            @foreach(['self' => 'Self Review', 'manager' => 'Manager', 'peer' => 'Peers', 'direct_report' => 'Direct Reports'] as $typeKey => $typeTitle)
                                                @php
                                                    $noms = $byType->get($typeKey, collect());
                                                    if ($noms->isEmpty()) continue;
                                                    $doneCount = $noms->where('status', 'completed')->count();
                                                    $tCount = $noms->count();
                                                    $isAllDone = $doneCount === $tCount;
                                                @endphp
                                                <div class="col-sm-6 col-12">
                                                    <div class="d-flex justify-content-between align-items-center p-2 rounded-2 border bg-light fs-12">
                                                        <span class="fw-semibold text-dark">{{ $typeTitle }}</span>
                                                        <span class="badge {{ $isAllDone ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-light text-muted border' }} fw-bold">
                                                            {{ $doneCount }} / {{ $tCount }} Done
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="mt-auto pt-3 border-top d-flex gap-2 align-items-center">
                                        @if($eval->cycle?->status === 'draft')
                                            <div class="text-muted fs-11 fst-italic flex-grow-1 py-1">
                                                <i class="feather-clock text-warning me-1"></i> Cycle in Draft &bull; Nominations open upon launch
                                            </div>
                                        @elseif($eval->cycle?->allow_self_nomination && in_array($eval->cycle?->status, ['nomination', 'in_progress']))
                                            <x-ui.button variant="light" size="sm" class="border flex-grow-1 fw-semibold justify-content-center" icon="feather-user-plus" data-bs-toggle="modal" data-bs-target="#nominatePeersModal{{ $eval->id }}">
                                                Nominate Peers
                                            </x-ui.button>
                                        @endif

                                        <x-ui.button variant="primary" size="sm" class="flex-grow-1 fw-bold justify-content-center" icon="feather-pie-chart" href="{{ route('hrms.feedback360.report', $eval->id) }}">
                                            View 360 Radar Report
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- ================================================================= -->
            <!-- TAB 4: PEER APPROVALS -->
            <!-- ================================================================= -->
            <div class="tab-pane fade {{ $activeTab === 'peer_approvals' ? 'show active' : '' }}" id="peer-approvals" role="tabpanel">
                @php
                    $groupedApprovals = $pendingApprovals->groupBy(function($nom) {
                        return $nom->participant_id ?: ($nom->employee_id . '_' . $nom->cycle_id);
                    });
                @endphp

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 pb-3 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark mb-0 fs-16">Peer Approvals</h5>
                        <small class="text-muted">Review peer reviewer requests submitted by team members. Click Review & Approve to view the checklist and selectively approve nominees.</small>
                    </div>
                    @if($pendingApprovals->isNotEmpty())
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1.5 rounded-pill fs-12 fw-semibold">
                                <i class="feather-clock me-1"></i> {{ $pendingApprovals->count() }} Peer Nominees Pending
                            </span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fs-12 fw-semibold">
                                <i class="feather-user-check me-1"></i> {{ $groupedApprovals->count() }} Nomination Requests
                            </span>
                        </div>
                    @endif
                </div>

                @if($pendingApprovals->isEmpty())
                    <div class="alert alert-light border text-muted fs-13 py-3 rounded">
                        <i class="feather-check-circle text-success me-2"></i>No pending peer nominations require approval at this time.
                    </div>
                @else
                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center text-muted fw-semibold">#</th>
                                    <th>Subject Employee</th>
                                    <th>Feedback Cycle</th>
                                    <th>Nominated Peers</th>
                                    <th>Requested Date</th>
                                    <th class="text-end" style="min-width: 170px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groupedApprovals as $groupKey => $group)
                                    @php
                                        $modalId = 'approveNomModal_' . $loop->index;
                                        $firstNom = $group->first();
                                        $subjectEmployee = $firstNom->employee;
                                        $cycle = $firstNom->cycle;
                                        $nominator = $firstNom->nominator;
                                    @endphp
                                    <tr>
                                        <td class="text-center text-muted fw-semibold">{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-initials bg-primary-subtle text-primary fw-bold me-3 flex-shrink-0">
                                                    {{ substr($subjectEmployee?->first_name ?? 'E', 0, 1) }}{{ substr($subjectEmployee?->last_name ?? '', 0, 1) }}
                                                </div>
                                                <div class="overflow-hidden">
                                                    <span class="fw-bold text-dark d-block fs-13 lh-sm">{{ $subjectEmployee?->full_name }}</span>
                                                    <span class="text-muted fs-11 mt-1 d-block lh-1 text-truncate">{{ $subjectEmployee?->department?->name ?? 'Team' }} &bull; {{ $subjectEmployee?->designation?->name ?? 'Employee' }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <x-ui.badge soft variant="primary" class="fs-11 py-1">
                                                {{ $cycle?->name ?? 'Feedback Cycle' }}
                                            </x-ui.badge>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 fs-12 fw-semibold">
                                                    <i class="feather-users me-1"></i>{{ $group->count() }} Peer{{ $group->count() > 1 ? 's' : '' }} Nominated
                                                </span>
                                                <span class="text-muted fs-11">
                                                    ({{ $group->pluck('reviewer.full_name')->filter()->take(2)->join(', ') }}{{ $group->count() > 2 ? ' +'.($group->count() - 2).' more' : '' }})
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark fs-12">{{ $firstNom->created_at->format('M d, Y') }}</span>
                                            <span class="text-muted fs-11 d-block">{{ $firstNom->created_at->diffForHumans() }}</span>
                                        </td>
                                        <td class="text-end">
                                            <x-ui.button variant="primary" size="sm" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" class="fw-semibold px-3 py-1.5 shadow-xs">
                                                Review & Approve
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- ================================================================= -->
            <!-- TAB 5: COMPETENCIES & QUESTION BANK -->
            <!-- ================================================================= -->
            <div class="tab-pane fade {{ $activeTab === 'competencies_questions' ? 'show active' : '' }}" id="competencies-questions" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark mb-0 fs-16">Competencies & Question Bank</h5>
                        <small class="text-muted">Standard criteria used for multi-rater feedback evaluations and radar analysis</small>
                    </div>
                    <div>
                        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCompetencyModal" class="fw-bold">
                            Add Competency & Questions
                        </x-ui.button>
                    </div>
                </div>

                <div class="row g-3">
                    @forelse($competencies as $comp)
                        <div class="col-xl-6 col-12">
                            <div class="card border shadow-sm p-3.5 review-card" style="border-radius: 12px !important;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fs-11 fw-semibold">
                                            {{ $comp->category }}
                                        </span>
                                        @if($comp->cycle_id)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 fs-11 fw-semibold">
                                                <i class="feather-target me-1"></i>{{ $comp->cycle?->name }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1 fs-11 fw-medium">
                                                <i class="feather-globe me-1"></i>Global (All Cycles)
                                            </span>
                                        @endif
                                    </div>
                                    <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" title="Delete Competency" data-bs-toggle="modal" data-bs-target="#deleteCompModal{{ $comp->id }}" />
                                </div>

                                <div class="mb-1.5">
                                    <h6 class="fw-bold text-dark mb-1 fs-15">{{ $comp->name }}</h6>
                                    <span class="badge bg-secondary-subtle text-secondary font-monospace fs-10 px-2 py-0.5 rounded">{{ $comp->code }}</span>
                                </div>

                                @if($comp->description)
                                    <p class="text-muted fs-12 mb-2 lh-base">{{ $comp->description }}</p>
                                @endif

                                <!-- Delete Competency Confirmation Modal -->
                                <x-ui.modal id="deleteCompModal{{ $comp->id }}" title="Delete Competency" size="sm" :centered="true" :static="true" :showFooter="false">
                                    <div class="text-center py-2">
                                        <div class="avatar-initials bg-danger-subtle text-danger mx-auto mb-3" style="width: 48px; height: 48px; font-size: 18px;">
                                            <i class="feather-trash-2"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">Delete Competency?</h6>
                                        <p class="text-muted fs-12 mb-3">
                                            Are you sure you want to remove <strong>{{ $comp->name }}</strong> and its evaluation questions?
                                        </p>
                                        <form action="{{ route('hrms.feedback360.competencies.destroy', $comp->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <div class="d-flex justify-content-center gap-2">
                                                <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
                                                <x-ui.button variant="danger" size="sm" type="submit" icon="feather-trash-2" class="fw-bold">Yes, Delete</x-ui.button>
                                            </div>
                                        </form>
                                    </div>
                                </x-ui.modal>

                                <div class="border-top pt-2.5 mt-2">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-muted fs-11 fw-bold text-uppercase d-inline-flex align-items-center gap-1">
                                            <i class="feather-help-circle text-primary"></i> Evaluation Questions
                                        </span>
                                        <span class="badge bg-light text-secondary border rounded-pill fs-10 px-2 py-0.5">
                                            {{ $comp->questions->count() }} prompts
                                        </span>
                                    </div>
                                    <div class="d-flex flex-column gap-1.5 mt-1.5">
                                        @forelse($comp->questions as $q)
                                            <div class="p-2 rounded border d-flex align-items-center justify-content-between gap-2" style="background-color: #f8fafc; border-color: #e2e8f0 !important;">
                                                <div class="d-flex align-items-start gap-2 flex-grow-1 min-w-0">
                                                    <i class="feather-check-circle text-primary mt-0.5 fs-13 flex-shrink-0"></i>
                                                    <div class="min-w-0">
                                                        <span class="text-dark fs-12 fw-medium d-block lh-sm text-break">{{ $q->question_text }}</span>
                                                        <div class="d-flex align-items-center gap-1.5 mt-1">
                                                            <span class="badge bg-white text-muted border fs-10 px-1.5 py-0.5 rounded">
                                                                {{ $q->question_type === 'rating_scale' ? '1 - 5 Rating Scale' : 'Open Text Comment' }}
                                                            </span>
                                                            @if($q->target_reviewer_type && $q->target_reviewer_type !== 'all')
                                                                <span class="badge bg-info-subtle text-info border border-info-subtle fs-10 px-1.5 py-0.5 rounded">
                                                                    {{ ucfirst(str_replace('_', ' ', $q->target_reviewer_type)) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <form action="{{ route('hrms.feedback360.questions.destroy', $q->id) }}" method="POST" class="d-inline m-0 flex-shrink-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.icon-btn type="submit" icon="feather-x" variant="soft-danger" size="sm" title="Remove question" />
                                                </form>
                                            </div>
                                        @empty
                                            <div class="text-muted fs-11 fst-italic py-2 text-center bg-light rounded">No questions linked to this competency yet.</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="text-center py-5 border rounded-3 bg-light">
                                <div class="avatar-initials bg-primary-subtle text-primary mx-auto mb-3" style="width: 52px; height: 52px; font-size: 20px;">
                                    <i class="feather-award"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">No Competencies Found</h6>
                                <p class="text-muted fs-12 mb-3">Define organizational competencies and question sets to start 360 evaluations.</p>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCompetencyModal" class="fw-bold">
                                    Create First Competency
                                </x-ui.button>
                            </div>
                        </div>
                    @endforelse

                    @php
                        $openQuestions = $questions->whereNull('competency_id');
                    @endphp

                    @if($openQuestions->isNotEmpty())
                        <div class="col-xl-6 col-12">
                            <div class="card border shadow-sm p-3.5 review-card" style="border-radius: 12px !important; background-color: #fafbfd !important;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 fs-11 fw-semibold">
                                            Qualitative & Open-Ended
                                        </span>
                                        <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1 fs-11 fw-medium">
                                            <i class="feather-globe me-1"></i>Global
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-1.5">
                                    <h6 class="fw-bold text-dark mb-1 fs-15">General Written Feedback Questions</h6>
                                    <span class="badge bg-secondary-subtle text-secondary font-monospace fs-10 px-2 py-0.5 rounded">GENERAL-TEXT</span>
                                </div>

                                <p class="text-muted fs-12 mb-2 lh-base">Open-ended prompts presented to all reviewers to collect qualitative feedback and growth recommendations.</p>

                                <div class="border-top pt-2.5 mt-2">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-muted fs-11 fw-bold text-uppercase d-inline-flex align-items-center gap-1">
                                            <i class="feather-message-square text-info"></i> Open Comment Prompts
                                        </span>
                                        <span class="badge bg-light text-secondary border rounded-pill fs-10 px-2 py-0.5">
                                            {{ $openQuestions->count() }} prompts
                                        </span>
                                    </div>
                                    <div class="d-flex flex-column gap-1.5 mt-1.5">
                                        @foreach($openQuestions as $oq)
                                            <div class="p-2 rounded border d-flex align-items-center justify-content-between gap-2" style="background-color: #ffffff; border-color: #e2e8f0 !important;">
                                                <div class="d-flex align-items-start gap-2 flex-grow-1 min-w-0">
                                                    <i class="feather-message-square text-info mt-0.5 fs-13 flex-shrink-0"></i>
                                                    <div class="min-w-0">
                                                        <span class="text-dark fs-12 fw-medium d-block lh-sm text-break">{{ $oq->question_text }}</span>
                                                        <div class="d-flex align-items-center gap-1.5 mt-1">
                                                            <span class="badge bg-light text-muted border fs-10 px-1.5 py-0.5 rounded">
                                                                Open Text
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <form action="{{ route('hrms.feedback360.questions.destroy', $oq->id) }}" method="POST" class="d-inline m-0 flex-shrink-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.icon-btn type="submit" icon="feather-x" variant="soft-danger" size="sm" title="Remove question" />
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CREATE FEEDBACK CYCLE -->
<!-- ========================================================================= -->
<x-ui.modal id="createCycleModal" title="Create 360-Degree Feedback Cycle" size="lg" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.cycles.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-md-7">
                <x-ui.odoo-form-ui type="input" label="Cycle Name" name="name" placeholder="e.g. Q3 2026 Leadership & Multi-Rater Review" :required="true" />
            </div>
            <div class="col-md-5">
                <x-ui.odoo-form-ui type="input" label="Cycle Code" name="code" placeholder="e.g. F360-2026-Q3" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" :value="date('Y-m-01')" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" :value="date('Y-m-t', strtotime('+1 month'))" :required="true" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" label="Nomination Due" name="nomination_deadline" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" label="Feedback Due" name="submission_deadline" />
            </div>

            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="2" placeholder="Brief note explaining the purpose and guidance for this review cycle..." />
            </div>

            <div class="col-12">
                <div class="p-3 bg-light rounded border">
                    <h6 class="fw-bold text-dark fs-12 text-uppercase mb-3">Anonymity & Nomination Governance</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="checkbox" label="Peer Privacy" name="is_peer_anonymous" value="1" checked>
                                Anonymous Peer Feedback
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="checkbox" label="Upward Privacy" name="is_direct_report_anonymous" value="1" checked>
                                Anonymous Upward Feedback (Direct Reports)
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="checkbox" label="Self-Nomination" name="allow_self_nomination" value="1" checked>
                                Allow Employees to Nominate Peers
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="checkbox" label="Approvals" name="require_manager_approval" value="1" checked>
                                Require Manager Approval on Nominations
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <x-ui.odoo-form-ui type="select" label="Participants" name="employee_ids[]" :multiple="true" :searchable="true" helperText="You can also enroll more participants later from the cycle dashboard.">
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->designation->name ?? 'Staff' }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Discard</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">Create Cycle</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL: CREATE COMPETENCY & EVALUATION QUESTIONS (UNIFIED FORM) -->
<!-- ========================================================================= -->
<x-ui.modal id="createCompetencyModal" title="Add Competency & Evaluation Questions" size="xl" :centered="true" :static="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.competencies.store') }}" method="POST" id="createCompetencyWithQuestionsForm">
        @csrf
        <div class="row g-3">
            <div class="col-md-7">
                <x-ui.odoo-form-ui type="input" label="Competency Name" name="name" placeholder="e.g. Continuous Growth & Core Values" :required="true" />
            </div>
            <div class="col-md-5">
                <x-ui.odoo-form-ui type="input" label="Category" name="category" placeholder="e.g. Core Values / Leadership" :required="true" />
            </div>
            <div class="col-md-7">
                <x-ui.odoo-form-ui type="select" label="Feedback Cycle Scope" name="cycle_id" helperText="Assign to a specific cycle or keep Global for all company cycles.">
                    <option value="">🌐 Global (All Feedback Cycles)</option>
                    @foreach($cycles as $c)
                        <option value="{{ $c->id }}">🎯 {{ $c->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-5">
                <x-ui.odoo-form-ui type="input" label="Code (Optional)" name="code" placeholder="e.g. COMP-CULT" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="2" placeholder="Define expected behaviors and standards for this competency..." />
            </div>
        </div>

        <!-- Dynamic Questions Section -->
        <div class="mt-4 pt-3 border-top">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold text-dark mb-0 fs-13">Evaluation Questions for this Competency</h6>
                    <span class="text-muted fs-11">Add rating scale statements or open text prompts evaluated under this competency.</span>
                </div>
                <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" id="addCompQuestionRowBtn" class="border fw-semibold shadow-xs">
                    Add Question Row
                </x-ui.button>
            </div>

            <div class="table-responsive border rounded bg-white">
                <table class="table table-hover align-middle mb-0" id="compQuestionsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 48%; min-width: 300px;" class="ps-3 py-2.5 text-uppercase fs-11 fw-bold text-muted">Question Statement / Prompt <span class="text-danger">*</span></th>
                            <th style="width: 26%; min-width: 190px;" class="py-2.5 text-uppercase fs-11 fw-bold text-muted">Question Type</th>
                            <th style="width: 20%; min-width: 180px;" class="py-2.5 text-uppercase fs-11 fw-bold text-muted">Target Rater</th>
                            <th style="width: 6%; min-width: 55px;" class="text-center pe-3 py-2.5 text-uppercase fs-11 fw-bold text-muted">Action</th>
                        </tr>
                    </thead>
                    <tbody id="compQuestionsTbody">
                        <tr>
                            <td class="ps-3 py-2.5">
                                <input type="text" name="questions[0][question_text]" class="odoo-table-input fs-13" placeholder="e.g. Exemplifies core company values, integrity, and ethical conduct..." required>
                            </td>
                            <td class="py-2.5">
                                <x-ui.odoo-form-ui type="select" name="questions[0][question_type]" :searchable="true">
                                    <option value="rating_scale" selected>Rating Scale (1 - 5 Points)</option>
                                    <option value="text">Open-Ended Text Comment</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td class="py-2.5">
                                <x-ui.odoo-form-ui type="select" name="questions[0][target_reviewer_type]" :searchable="true">
                                    <option value="all" selected>All Reviewer Types</option>
                                    <option value="manager">Manager Only</option>
                                    <option value="peer">Peers Only</option>
                                    <option value="direct_report">Direct Reports Only</option>
                                    <option value="self">Self Review Only</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td class="text-center pe-3 py-2.5">
                                <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" class="delete-comp-q-row" title="Remove question row" />
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-3 py-2.5">
                                <input type="text" name="questions[1][question_text]" class="odoo-table-input fs-13" placeholder="e.g. Proactively seeks feedback and adapts positively to organizational changes...">
                            </td>
                            <td class="py-2.5">
                                <x-ui.odoo-form-ui type="select" name="questions[1][question_type]" :searchable="true">
                                    <option value="rating_scale" selected>Rating Scale (1 - 5 Points)</option>
                                    <option value="text">Open-Ended Text Comment</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td class="py-2.5">
                                <x-ui.odoo-form-ui type="select" name="questions[1][target_reviewer_type]" :searchable="true">
                                    <option value="all" selected>All Reviewer Types</option>
                                    <option value="manager">Manager Only</option>
                                    <option value="peer">Peers Only</option>
                                    <option value="direct_report">Direct Reports Only</option>
                                    <option value="self">Self Review Only</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td class="text-center pe-3 py-2.5">
                                <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" class="delete-comp-q-row" title="Remove question row" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Discard</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" icon="feather-check" class="fw-bold px-3">Save Competency & Questions</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL: CREATE QUESTION -->
<!-- ========================================================================= -->
<x-ui.modal id="createQuestionModal" title="Add Question to Bank" size="md" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.questions.store') }}" method="POST">
        @csrf
        <div class="d-flex flex-column gap-3">
            <x-ui.odoo-form-ui type="select" label="Competency" name="competency_id" :searchable="true">
                <option value="">-- General / Open-Ended (No Specific Competency) --</option>
                @foreach($competencies as $comp)
                    <option value="{{ $comp->id }}">{{ $comp->name }} ({{ $comp->category }})</option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="textarea" label="Question Text" name="question_text" rows="2" placeholder="e.g. Demonstrates proactive communication and keeps stakeholders updated..." :required="true" />

            <x-ui.odoo-form-ui type="select" label="Question Type" name="question_type" :required="true" :searchable="false">
                <option value="rating_scale">Rating Scale (1 - 5 Points)</option>
                <option value="text">Open-Ended Text Comment</option>
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="select" label="Target Rater" name="target_reviewer_type" :required="true" :searchable="false">
                <option value="all">All Reviewer Types</option>
                <option value="manager">Manager Only</option>
                <option value="peer">Peers Only</option>
                <option value="direct_report">Direct Reports Only</option>
                <option value="self">Self Review Only</option>
            </x-ui.odoo-form-ui>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Discard</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">Save Question</x-ui.button>
        </div>
    </form>
</x-ui.modal>

@foreach($cycles as $cycle)
    <!-- Modal: Add Participants for this cycle -->
    <x-ui.modal id="addParticipantsModal{{ $cycle->id }}" title="Enroll Participants: {{ $cycle->name }}" size="md" :centered="true" :showFooter="false">
        <form action="{{ route('hrms.feedback360.cycles.participants', $cycle->id) }}" method="POST">
            @csrf
            <x-ui.odoo-form-ui type="select" label="Employees" name="employee_ids[]" :multiple="true" :searchable="true" :required="true" helperText="Selected employees will become reviewees (subjects). Their direct manager and reporting subordinates will be automatically assigned.">
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}">
                        {{ $emp->full_name }} ({{ $emp->designation->name ?? 'Staff' }} - {{ $emp->department->name ?? 'General' }})
                    </option>
                @endforeach
            </x-ui.odoo-form-ui>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Discard</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">Enroll Participants</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endforeach

@foreach($myEvaluations as $eval)
    <!-- Modal: Nominate Peers for this Evaluation -->
    <x-ui.modal id="nominatePeersModal{{ $eval->id }}" title="Nominate Peer Reviewers" size="md" :centered="true" :showFooter="false">
        <form action="{{ route('hrms.feedback360.participants.nominate-peers', $eval->id) }}" method="POST">
            @csrf
            <x-ui.odoo-form-ui type="select" label="Peer Raters" name="peer_ids[]" :multiple="true" :searchable="true" :required="true" helperText="Select colleagues you worked with closely. Min: {{ $eval->cycle?->min_peer_nominations ?? 2 }}, Max: {{ $eval->cycle?->max_peer_nominations ?? 5 }}">
                @foreach($employees as $emp)
                    @if($emp->id !== $eval->employee_id)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->designation->name ?? 'Staff' }})</option>
                    @endif
                @endforeach
            </x-ui.odoo-form-ui>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Discard</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">Submit Nominations</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endforeach

@php
    $groupedApprovalsForModals = $pendingApprovals->groupBy(function($nom) {
        return $nom->participant_id ?: ($nom->employee_id . '_' . $nom->cycle_id);
    });
@endphp

<!-- Peer Nomination Checklist Approval Modals (Using x-ui.modal for proper body append) -->
@foreach($groupedApprovalsForModals as $groupKey => $group)
    @php
        $modalId = 'approveNomModal_' . $loop->index;
        $firstNom = $group->first();
        $subjectEmployee = $firstNom->employee;
        $cycle = $firstNom->cycle;
        $nominator = $firstNom->nominator;
    @endphp
    <x-ui.modal id="{{ $modalId }}" title="Review Peer Nominations: {{ $subjectEmployee?->full_name }}" size="lg" :centered="true" :showFooter="false">
        <form action="{{ route('hrms.feedback360.nominations.batch-approve') }}" method="POST">
            @csrf

            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                <div class="avatar-initials bg-primary-subtle text-primary fw-bold me-3 flex-shrink-0" style="width: 40px; height: 40px; min-width: 40px; font-size: 14px;">
                    {{ substr($subjectEmployee?->first_name ?? 'E', 0, 1) }}{{ substr($subjectEmployee?->last_name ?? '', 0, 1) }}
                </div>
                <div>
                    <h6 class="fw-bold text-dark fs-14 mb-0.5">{{ $subjectEmployee?->full_name }}</h6>
                    <span class="text-muted fs-12">{{ $subjectEmployee?->department?->name ?? 'Team' }} &bull; {{ $subjectEmployee?->designation?->name ?? 'Employee' }} &bull; <strong class="text-primary">{{ $cycle?->name }}</strong></span>
                </div>
            </div>

            <div class="alert alert-light border text-muted fs-12 mb-3 py-2 px-3 rounded d-flex align-items-center gap-2">
                <i class="feather-info text-primary fs-14"></i>
                <span>Select the peers you wish to approve or reject. You can check/uncheck individuals below.</span>
            </div>

            <!-- Select All / Total Strip -->
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll_{{ $modalId }}" checked onchange="document.querySelectorAll('.nom-check-{{ $modalId }}').forEach(cb => cb.checked = this.checked);">
                    <label class="form-check-label fw-bold text-dark fs-12 cursor-pointer" for="selectAll_{{ $modalId }}">
                        Select All ({{ $group->count() }} Nominees)
                    </label>
                </div>
                <span class="text-muted fs-12">
                    <i class="feather-calendar me-1"></i>Requested {{ $firstNom->created_at->format('M d, Y') }}
                </span>
            </div>

            <!-- Nominee Checklist Items -->
            <div class="d-flex flex-column gap-2 mb-3">
                @foreach($group as $nom)
                    <label class="d-flex align-items-center gap-3 p-3 border rounded bg-white hover-bg-light cursor-pointer mb-0" for="nom_check_{{ $nom->id }}" style="transition: all 0.15s ease-in-out;">
                        <input class="form-check-input flex-shrink-0 mt-0 nom-check-{{ $modalId }}" type="checkbox" name="nomination_ids[]" value="{{ $nom->id }}" id="nom_check_{{ $nom->id }}" checked>
                        <div class="avatar-initials bg-info-subtle text-info fw-bold flex-shrink-0 me-2" style="width: 36px; height: 36px; min-width: 36px;">
                            {{ substr($nom->reviewer?->first_name ?? 'P', 0, 1) }}
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <span class="fw-bold text-dark fs-13">{{ $nom->reviewer?->full_name }}</span>
                                <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5 fs-11 fw-semibold">
                                    {{ ucfirst($nom->reviewer_type ?? 'peer') }}
                                </span>
                            </div>
                            <div class="text-muted fs-11 mt-0.5">
                                <span>{{ $nom->reviewer?->department?->name ?? 'Team' }}</span>
                                <span class="text-secondary">&bull;</span>
                                <span>{{ $nom->reviewer?->designation?->name ?? 'Peer Reviewer' }}</span>
                                @if($nom->reviewer?->email)
                                    <span class="text-secondary">&bull;</span>
                                    <span>{{ $nom->reviewer?->email }}</span>
                                @endif
                            </div>
                            @if($nom->nomination_reason)
                                <div class="text-secondary fs-11 fst-italic mt-1 bg-light p-1.5 rounded">
                                    "{{ $nom->nomination_reason }}"
                                </div>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>

            <!-- Optional Rejection Reason -->
            <div class="mt-3">
                <label class="form-label text-muted fs-12 fw-semibold mb-1">Optional Note / Reason (if rejecting):</label>
                <input type="text" name="reason" class="form-control form-control-sm fs-12" placeholder="e.g., Exceeded maximum quota, or on extended leave...">
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <x-ui.button variant="light" size="sm" data-bs-dismiss="modal" class="border">
                    Cancel
                </x-ui.button>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="danger" size="sm" outline type="submit" name="action" value="reject" icon="feather-x">
                        Reject Selected
                    </x-ui.button>
                    <x-ui.button variant="success" size="sm" type="submit" name="action" value="approve" icon="feather-check-circle" class="fw-bold px-3">
                        Approve Selected
                    </x-ui.button>
                </div>
            </div>
        </form>
    </x-ui.modal>
@endforeach

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let qRowIndex = 2;
    const addBtn = document.getElementById('addCompQuestionRowBtn');
    const tbody = document.getElementById('compQuestionsTbody');

    function initCompModalSelect2() {
        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            $('#createCompetencyModal .odoo-select2').each(function() {
                var $select = $(this);
                if (!$select.hasClass('select2-hidden-accessible')) {
                    $select.select2({
                        theme: "bootstrap-5",
                        width: "100%",
                        dropdownParent: $('#createCompetencyModal')
                    });
                }
            });
        }
    }

    if (typeof jQuery !== 'undefined') {
        $('#createCompetencyModal').on('shown.bs.modal', function () {
            initCompModalSelect2();
        });
    }

    if (addBtn && tbody) {
        addBtn.addEventListener('click', function() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-3 py-2.5">
                    <input type="text" name="questions[${qRowIndex}][question_text]" class="odoo-table-input fs-13" placeholder="e.g. Demonstrates strong analytical and problem-solving skills..." required>
                </td>
                <td class="py-2.5">
                    <select name="questions[${qRowIndex}][question_type]" class="odoo-table-select odoo-select2">
                        <option value="rating_scale" selected>Rating Scale (1 - 5 Points)</option>
                        <option value="text">Open-Ended Text Comment</option>
                    </select>
                </td>
                <td class="py-2.5">
                    <select name="questions[${qRowIndex}][target_reviewer_type]" class="odoo-table-select odoo-select2">
                        <option value="all" selected>All Reviewer Types</option>
                        <option value="manager">Manager Only</option>
                        <option value="peer">Peers Only</option>
                        <option value="direct_report">Direct Reports Only</option>
                        <option value="self">Self Review Only</option>
                    </select>
                </td>
                <td class="text-center pe-3 py-2.5">
                    <button type="button" class="btn erp-icon-btn erp-icon-btn--danger btn-sm delete-comp-q-row" title="Remove question row">
                        <i class="feather-trash-2"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            qRowIndex++;
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                $(tr).find('.odoo-select2').select2({
                    theme: "bootstrap-5",
                    width: "100%",
                    dropdownParent: $('#createCompetencyModal')
                });
            }
        });

        tbody.addEventListener('click', function(e) {
            const delBtn = e.target.closest('.delete-comp-q-row');
            if (delBtn) {
                const tr = delBtn.closest('tr');
                if (tbody.querySelectorAll('tr').length > 1) {
                    tr.remove();
                } else {
                    tr.querySelector('input').value = '';
                }
            }
        });
    }
});
</script>
@endpush

@endsection
