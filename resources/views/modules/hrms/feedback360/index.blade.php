@extends('layouts.duralux')

@section('title', __('hrms.feedback_360.title_index'))
@section('page-title', __('hrms.feedback_360.page_title'))
@section('breadcrumb', __('hrms.feedback_360.breadcrumb_index'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        @if($isHrAdmin)
            <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createCycleModal" class="fw-bold">
                {{ __('hrms.feedback_360.btn_create_cycle') }}
            </x-ui.button>
        @endif
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
        .competency-select-card {
            cursor: pointer;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            transition: all 0.15s ease-in-out;
            user-select: none;
        }
        .competency-select-card:hover {
            border-color: #cbd5e1;
            background-color: #f8fafc;
        }
        .competency-select-card.is-selected {
            border-color: #0d6efd !important;
            background-color: #f8faff !important;
        }
        .compact-dropdown-menu {
            min-width: 165px !important;
            padding: 4px !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
        }
        .compact-dropdown-menu .dropdown-item {
            padding: 6px 10px !important;
            font-size: 12.5px !important;
            border-radius: 6px !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            line-height: 1.35 !important;
        }
        .compact-dropdown-menu .dropdown-divider {
            margin: 4px 0 !important;
            border-color: #f1f5f9 !important;
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
                    'label' => __('hrms.feedback_360.tab_cycles'),
                    'active' => ($activeTab === 'cycles'),
                    'icon' => 'feather-layers',
                ],
                [
                    'id' => 'my-evaluations',
                    'label' => __('hrms.feedback_360.tab_my_evaluations'),
                    'active' => ($activeTab === 'my_evaluations'),
                    'icon' => 'feather-user-check',
                ],
                [
                    'id' => 'my-reviews',
                    'label' => __('hrms.feedback_360.tab_my_reviews') . ($statistics['my_pending_count'] > 0 ? " ({$statistics['my_pending_count']})" : ''),
                    'active' => ($activeTab === 'my_reviews'),
                    'icon' => 'feather-edit-3',
                ],
                [
                    'id' => 'peer-approvals',
                    'label' => __('hrms.feedback_360.tab_peer_approvals') . ($statistics['pending_approvals_count'] > 0 ? " ({$statistics['pending_approvals_count']})" : ''),
                    'active' => ($activeTab === 'peer_approvals'),
                    'icon' => 'feather-check-square',
                ],
                [
                    'id' => 'competencies-questions',
                    'label' => __('hrms.feedback_360.tab_competencies_questions'),
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
                        <h5 class="fw-bold text-dark mb-0 fs-16">{{ __('hrms.feedback_360.cycles_header_title') }}</h5>
                        <small class="text-muted">{{ __('hrms.feedback_360.cycles_header_subtitle') }}</small>
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
                                placeholder="{{ __('hrms.feedback_360.search_cycles_placeholder') }}" 
                                value="{{ request('search') }}"
                                style="box-shadow: none;"
                            >
                            @if(request('search'))
                                <a href="{{ route('hrms.feedback360.index', ['active_tab' => 'cycles', 'status' => request('status'), 'sort_by' => request('sort_by'), 'sort_dir' => request('sort_dir')]) }}" class="text-muted ms-1"><i class="feather-x"></i></a>
                            @endif
                        </form>

                        <!-- Standard Sort Dropdown Component -->
                        <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ (!request('sort_by') || request('sort_by') === 'created_at') ? 'active' : '' }}" href="{{ route('hrms.feedback360.index', array_merge(request()->all(), ['sort_by' => 'created_at', 'sort_dir' => 'desc', 'active_tab' => 'cycles'])) }}">
                                <span>{{ __('hrms.feedback_360.sort_newest_first') }}</span>
                                @if(!request('sort_by') || request('sort_by') === 'created_at') <i class="feather-check ms-3 text-primary"></i> @endif
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort_by') === 'name' ? 'active' : '' }}" href="{{ route('hrms.feedback360.index', array_merge(request()->all(), ['sort_by' => 'name', 'sort_dir' => 'asc', 'active_tab' => 'cycles'])) }}">
                                <span>{{ __('hrms.feedback_360.sort_name_asc') }}</span>
                                @if(request('sort_by') === 'name') <i class="feather-check ms-3 text-primary"></i> @endif
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort_by') === 'start_date' ? 'active' : '' }}" href="{{ route('hrms.feedback360.index', array_merge(request()->all(), ['sort_by' => 'start_date', 'sort_dir' => 'desc', 'active_tab' => 'cycles'])) }}">
                                <span>{{ __('hrms.feedback_360.sort_start_date') }}</span>
                                @if(request('sort_by') === 'start_date') <i class="feather-check ms-3 text-primary"></i> @endif
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Standard Filter Component -->
                        <x-ui.filter :label="__('hrms.common.filter')">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                            <form method="GET" action="{{ route('hrms.feedback360.index') }}">
                                <input type="hidden" name="active_tab" value="cycles">
                                <input type="hidden" name="search" value="{{ request('search') }}">
                                <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                                <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
                                
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.feedback_360.filter_cycle_status')" name="status">
                                    <option value="">{{ __('hrms.feedback_360.all_stages') }}</option>
                                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('hrms.feedback_360.stage_draft') }}</option>
                                    <option value="nomination" {{ request('status') === 'nomination' ? 'selected' : '' }}>{{ __('hrms.feedback_360.stage_nomination') }}</option>
                                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('hrms.feedback_360.stage_in_progress') }}</option>
                                    <option value="review" {{ request('status') === 'review' ? 'selected' : '' }}>{{ __('hrms.feedback_360.stage_review') }}</option>
                                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('hrms.feedback_360.stage_completed') }}</option>
                                </x-ui.odoo-form-ui>

                                <div class="pt-2 d-flex gap-2">
                                    <x-ui.button type="submit" variant="primary" size="sm" class="w-100 fw-bold">{{ __('hrms.feedback_360.apply_filters') }}</x-ui.button>
                                    <x-ui.button variant="light" size="sm" href="{{ route('hrms.feedback360.index', ['active_tab' => 'cycles']) }}" class="w-100 border">{{ __('hrms.common.reset') }}</x-ui.button>
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
                        <h6 class="fw-bold text-dark">{{ __('hrms.feedback_360.no_cycles_found') }}</h6>
                        <p class="text-muted fs-13 mb-0">{{ __('hrms.feedback_360.no_cycles_desc') }}</p>
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
                                    'draft'       => __('hrms.feedback_360.stage_draft'),
                                    'nomination'  => __('hrms.feedback_360.stage_nomination'),
                                    'in_progress' => __('hrms.feedback_360.stage_in_progress'),
                                    'review'      => __('hrms.feedback_360.stage_review'),
                                    'completed'   => __('hrms.feedback_360.stage_completed'),
                                    'closed'      => __('hrms.feedback_360.stage_closed'),
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
                                            <div class="dropdown-menu dropdown-menu-end compact-dropdown-menu border">
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#addParticipantsModal{{ $cycle->id }}">
                                                    <i class="feather-user-plus text-success fs-13"></i>
                                                    <span>{{ __('hrms.feedback_360.btn_add_participants') }}</span>
                                                </button>
                                                <div class="dropdown-divider"></div>
                                                <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteCycleModal{{ $cycle->id }}">
                                                    <i class="feather-trash-2 fs-13"></i>
                                                    <span>{{ __('hrms.feedback_360.btn_delete_cycle') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Cycle Confirmation Modal -->
                                    <x-ui.modal id="deleteCycleModal{{ $cycle->id }}" :title="__('hrms.feedback_360.modal_delete_cycle_title')" size="sm" :centered="true" :showFooter="false">
                                        <div class="text-center py-2">
                                            <div class="avatar-initials bg-danger-subtle text-danger mx-auto mb-3" style="width: 50px; height: 50px; font-size: 20px;">
                                                <i class="feather-trash-2"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1">{{ __('hrms.feedback_360.modal_delete_cycle_confirm') }}</h6>
                                            <p class="text-muted fs-12 mb-3">
                                                {{ __('hrms.feedback_360.modal_delete_cycle_msg', ['name' => $cycle->name]) }}
                                            </p>
                                            <form action="{{ route('hrms.feedback360.cycles.destroy', $cycle->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <div class="d-flex justify-content-center gap-2">
                                                    <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                                    <x-ui.button variant="danger" size="sm" type="submit" class="fw-bold">{{ __('hrms.feedback_360.btn_yes_delete') }}</x-ui.button>
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
                                             <span class="text-muted fw-semibold">{{ __('hrms.feedback_360.review_completion') }}</span>
                                             <span class="fw-bold text-dark fs-12">{{ $completionPct }}%</span>
                                         </div>
                                         <div class="progress" style="height: 6px; border-radius: 4px; background-color: #f1f5f9;">
                                             <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $completionPct }}%; border-radius: 4px;"></div>
                                         </div>
                                     </div>

                                     <!-- Quick Stats Strip -->
                                     <div class="cycle-stats-strip d-flex justify-content-between align-items-center mb-4 mt-auto">
                                         <div class="text-center flex-grow-1 py-0.5">
                                             <span class="text-muted d-block fs-10 text-uppercase fw-bold mb-0.5" style="letter-spacing: 0.5px;">{{ __('hrms.feedback_360.stat_reviewees') }}</span>
                                             <strong class="text-dark fs-14">{{ $cycle->participants_count }}</strong>
                                         </div>
                                         <div class="border-start text-center flex-grow-1 py-0.5">
                                             <span class="text-muted d-block fs-10 text-uppercase fw-bold mb-0.5" style="letter-spacing: 0.5px;">{{ __('hrms.feedback_360.stat_total_reviews') }}</span>
                                             <strong class="text-dark fs-14">{{ $cycle->nominations_count }}</strong>
                                         </div>
                                         <div class="border-start text-center flex-grow-1 py-0.5">
                                             <span class="text-muted d-block fs-10 text-uppercase fw-bold mb-0.5" style="letter-spacing: 0.5px;">{{ __('hrms.feedback_360.stat_peer_privacy') }}</span>
                                             <strong class="text-dark fs-13">{{ $cycle->is_peer_anonymous ? __('hrms.feedback_360.privacy_anonymous') : __('hrms.feedback_360.privacy_named') }}</strong>
                                         </div>
                                     </div>

                                     <div>
                                         <x-ui.button variant="primary" class="w-100 fw-bold justify-content-center" href="{{ route('hrms.feedback360.cycles.show', $cycle->id) }}">
                                             {{ __('hrms.feedback_360.btn_manage_roster_reports') }}
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
                        <h5 class="fw-bold text-dark mb-0 fs-16">{{ __('hrms.feedback_360.my_reviews_title') }}</h5>
                        <small class="text-muted">{{ __('hrms.feedback_360.my_reviews_subtitle') }}</small>
                    </div>
                </div>

                <!-- Pending Section -->
                <h6 class="fs-13 text-uppercase text-muted fw-bold mb-3 mt-4">
                    <i class="feather-clock me-1 text-warning"></i> {{ __('hrms.feedback_360.pending_reviews_heading', ['count' => $myPendingReviews->count()]) }}
                </h6>

                @if($myPendingReviews->isEmpty())
                    <div class="alert alert-light border text-muted fs-13 py-3 mb-4">
                        <i class="feather-check-circle text-success me-2"></i>{{ __('hrms.feedback_360.no_pending_reviews') }}
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
                                    'self'          => __('hrms.feedback_360.role_self_review'),
                                    'manager'       => __('hrms.feedback_360.role_manager_appraisal'),
                                    'peer'          => __('hrms.feedback_360.role_peer_review', ['privacy' => $rev->is_anonymous ? __('hrms.feedback_360.privacy_anonymous') : __('hrms.feedback_360.privacy_named')]),
                                    'direct_report' => __('hrms.feedback_360.role_direct_report_review'),
                                    default         => ucfirst(str_replace('_', ' ', $rev->reviewer_type)),
                                };
                            @endphp
                            <div class="col-xl-4 col-md-6">
                                <div class="review-card h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <x-ui.badge :variant="$roleVariant" soft>{{ $roleLabel }}</x-ui.badge>
                                            @if($rev->status === 'in_progress')
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0.5 fs-10 fw-semibold">{{ __('hrms.feedback_360.draft_saved') }}</span>
                                            @endif
                                        </div>
                                        <span class="text-muted fs-11">
                                            {{ __('hrms.feedback_360.due_colon') }} <strong>{{ $rev->cycle?->submission_deadline ? $rev->cycle->submission_deadline->format('M d') : __('hrms.feedback_360.open_deadline') }}</strong>
                                        </span>
                                    </div>

                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <div class="avatar-initials">
                                            {{ substr($rev->employee?->first_name ?? 'E', 0, 1) }}{{ substr($rev->employee?->last_name ?? '', 0, 1) }}
                                        </div>
                                        <div class="overflow-hidden">
                                            <h6 class="fw-bold text-dark mb-0.5 fs-15 text-truncate">{{ $rev->employee?->full_name }}</h6>
                                            <span class="text-muted fs-12 text-truncate d-block">{{ $rev->employee?->designation->name ?? __('hrms.feedback_360.staff_fallback') }} &bull; {{ $rev->employee?->department->name ?? __('hrms.feedback_360.general_fallback') }}</span>
                                        </div>
                                    </div>

                                    <div class="cycle-info-pill d-flex align-items-center gap-2 text-dark mb-4">
                                        <i class="feather-layers text-primary fs-14 flex-shrink-0"></i>
                                        <span class="text-truncate fw-semibold fs-12">{{ $rev->cycle?->name }}</span>
                                    </div>

                                    <div class="mt-auto">
                                        <x-ui.button variant="primary" class="w-100 fw-bold justify-content-center" icon="feather-edit-2" href="{{ route('hrms.feedback360.review', $rev->id) }}">
                                            {{ $rev->status === 'in_progress' ? __('hrms.feedback_360.btn_continue_evaluation') : __('hrms.feedback_360.btn_start_evaluation') }}
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
                        <i class="feather-check-circle me-1 text-success"></i> {{ __('hrms.feedback_360.submitted_reviews_heading', ['count' => $mySubmittedReviews->count()]) }}
                    </h6>

                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('hrms.feedback_360.th_reviewee') }}</th>
                                    <th>{{ __('hrms.feedback_360.th_evaluation_role') }}</th>
                                    <th>{{ __('hrms.feedback_360.th_feedback_cycle') }}</th>
                                    <th>{{ __('hrms.feedback_360.th_submitted_on') }}</th>
                                    <th class="text-end">{{ __('hrms.feedback_360.th_status') }}</th>
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
                                                <i class="feather-check me-1"></i> {{ __('hrms.feedback_360.badge_submitted') }}
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
                        <h5 class="fw-bold text-dark mb-0 fs-16">{{ __('hrms.feedback_360.my_evaluations_title') }}</h5>
                        <small class="text-muted">{{ __('hrms.feedback_360.my_evaluations_subtitle') }}</small>
                    </div>
                </div>

                @if($myEvaluations->isEmpty())
                    <div class="text-center py-5 border rounded bg-light">
                        <div class="avatar-initials mx-auto mb-3" style="width: 56px; height: 56px; font-size: 22px;">
                            <i class="feather-user-check"></i>
                        </div>
                        <h6 class="fw-bold text-dark">{{ __('hrms.feedback_360.no_active_evaluations') }}</h6>
                        <p class="text-muted fs-13">{{ __('hrms.feedback_360.no_active_evaluations_desc') }}</p>
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
                                            {{ $eval->status === 'published' ? __('hrms.feedback_360.badge_report_ready') : __('hrms.feedback_360.badge_in_progress') }}
                                        </x-ui.badge>
                                    </div>

                                    <div class="text-muted fs-12 mb-3">
                                        {{ __('hrms.feedback_360.manager_colon') }} <strong class="text-dark">{{ $eval->manager?->full_name ?? __('hrms.feedback_360.unassigned') }}</strong> &bull; {{ __('hrms.feedback_360.period_colon') }} {{ $eval->cycle?->start_date ? $eval->cycle->start_date->format('M Y') : 'N/A' }} &ndash; {{ $eval->cycle?->end_date ? $eval->cycle->end_date->format('M Y') : 'N/A' }}
                                    </div>

                                    <!-- Progress stats -->
                                    <div class="bg-light p-3 rounded-3 border mb-3">
                                        <div class="d-flex justify-content-between align-items-center fs-12 mb-2">
                                            <span class="text-muted fw-semibold">{{ __('hrms.feedback_360.responses_collected') }}</span>
                                            <span class="fw-bold text-dark fs-12">{{ __('hrms.feedback_360.raters_count_ratio', ['completed' => $completedRaters, 'total' => $totalRaters, 'pct' => $pct]) }}</span>
                                        </div>
                                        <div class="progress" style="height: 6px; border-radius: 4px; background-color: #e2e8f0;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $pct }}%; border-radius: 4px;"></div>
                                        </div>
                                    </div>

                                    <!-- Structured Reviewers Status Roster -->
                                    <div class="mb-3">
                                        <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-2" style="letter-spacing: 0.5px;">{{ __('hrms.feedback_360.reviewers_roster_status') }}</span>
                                        <div class="row g-2">
                                            @foreach(['self' => __('hrms.feedback_360.roster_self'), 'manager' => __('hrms.feedback_360.roster_manager'), 'peer' => __('hrms.feedback_360.roster_peers'), 'direct_report' => __('hrms.feedback_360.roster_direct_reports')] as $typeKey => $typeTitle)
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
                                                            {{ __('hrms.feedback_360.done_ratio', ['done' => $doneCount, 'total' => $tCount]) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="mt-auto pt-3 border-top d-flex gap-2 align-items-center">
                                        @if($eval->cycle?->status === 'draft')
                                            <div class="text-muted fs-11 fst-italic flex-grow-1 py-1">
                                                <i class="feather-clock text-warning me-1"></i> {{ __('hrms.feedback_360.draft_nominations_notice') }}
                                            </div>
                                        @elseif($eval->cycle?->allow_self_nomination && in_array($eval->cycle?->status, ['nomination', 'in_progress']))
                                            <x-ui.button variant="light" size="sm" class="border flex-grow-1 fw-semibold justify-content-center" icon="feather-user-plus" data-bs-toggle="modal" data-bs-target="#nominatePeersModal{{ $eval->id }}">
                                                {{ ($eval->employee?->directReports && $eval->employee->directReports->isNotEmpty()) ? __('hrms.feedback_360.btn_nominate_reviewers') : __('hrms.feedback_360.btn_nominate_peers') }}
                                            </x-ui.button>
                                        @endif

                                        @if($eval->status === 'published' || $isHrAdmin)
                                            <x-ui.button variant="primary" size="sm" class="flex-grow-1 fw-bold justify-content-center" icon="feather-pie-chart" href="{{ route('hrms.feedback360.report', $eval->id) }}">
                                                {{ __('hrms.feedback_360.btn_view_radar_report') }}
                                            </x-ui.button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-light border flex-grow-1 text-muted fw-semibold justify-content-center" disabled title="{{ __('hrms.feedback_360.report_pending_calibration_title') }}">
                                                <i class="feather-lock me-1"></i> {{ __('hrms.feedback_360.report_pending_calibration') }}
                                            </button>
                                        @endif
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
                        <h5 class="fw-bold text-dark mb-0 fs-16">{{ __('hrms.feedback_360.nomination_approvals_title') }}</h5>
                        <small class="text-muted">{{ __('hrms.feedback_360.nomination_approvals_subtitle') }}</small>
                    </div>
                    @if($pendingApprovals->isNotEmpty())
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1.5 rounded-pill fs-12 fw-semibold">
                                <i class="feather-clock me-1"></i> {{ __('hrms.feedback_360.nominees_pending_badge', ['count' => $pendingApprovals->count()]) }}
                            </span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fs-12 fw-semibold">
                                <i class="feather-user-check me-1"></i> {{ trans_choice('hrms.feedback_360.reviewee_requests_badge', $groupedApprovals->count(), ['count' => $groupedApprovals->count()]) }}
                            </span>
                        </div>
                    @endif
                </div>

                @if($pendingApprovals->isEmpty())
                    <div class="alert alert-light border text-muted fs-13 py-3 rounded">
                        <i class="feather-check-circle text-success me-2"></i>{{ __('hrms.feedback_360.no_pending_nominations') }}
                    </div>
                @else
                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center text-muted fw-semibold">{{ __('hrms.feedback_360.th_hash') }}</th>
                                    <th>{{ __('hrms.feedback_360.th_subject_employee') }}</th>
                                    <th>{{ __('hrms.feedback_360.th_feedback_cycle') }}</th>
                                    <th>{{ __('hrms.feedback_360.th_nominated_reviewers') }}</th>
                                    <th>{{ __('hrms.feedback_360.th_requested_date') }}</th>
                                    <th class="text-end" style="min-width: 170px;">{{ __('hrms.feedback_360.th_action') }}</th>
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
                                        $peersCount = $group->where('reviewer_type', 'peer')->count();
                                        $reportsCount = $group->where('reviewer_type', 'direct_report')->count();
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
                                                    <span class="text-muted fs-11 mt-1 d-block lh-1 text-truncate">{{ $subjectEmployee?->department?->name ?? __('hrms.feedback_360.team_fallback') }} &bull; {{ $subjectEmployee?->designation?->name ?? __('hrms.feedback_360.employee_fallback') }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <x-ui.badge soft variant="primary" class="fs-11 py-1">
                                                {{ $cycle?->name ?? __('hrms.feedback_360.th_feedback_cycle') }}
                                            </x-ui.badge>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                @if($peersCount > 0)
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 fs-12 fw-semibold">
                                                        <i class="feather-users me-1"></i>{{ trans_choice('hrms.feedback_360.peer_count', $peersCount, ['count' => $peersCount]) }}
                                                    </span>
                                                @endif
                                                @if($reportsCount > 0)
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fs-12 fw-semibold">
                                                        <i class="feather-user-check me-1"></i>{{ trans_choice('hrms.feedback_360.direct_report_count', $reportsCount, ['count' => $reportsCount]) }}
                                                    </span>
                                                @endif
                                                <span class="text-muted fs-11">
                                                    ({{ $group->pluck('reviewer.full_name')->filter()->take(2)->join(', ') }}{{ $group->count() > 2 ? ' ' . __('hrms.feedback_360.and_more_count', ['count' => $group->count() - 2]) : '' }})
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark fs-12">{{ $firstNom->created_at->format('M d, Y') }}</span>
                                            <span class="text-muted fs-11 d-block">{{ $firstNom->created_at->diffForHumans() }}</span>
                                        </td>
                                        <td class="text-end">
                                            <x-ui.button variant="primary" size="sm" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" class="fw-semibold px-3 py-1.5 shadow-xs">
                                                {{ __('hrms.feedback_360.btn_review_approve') }}
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
                        <h5 class="fw-bold text-dark mb-0 fs-16">{{ __('hrms.feedback_360.comp_bank_title') }}</h5>
                        <small class="text-muted">{{ __('hrms.feedback_360.comp_bank_subtitle') }}</small>
                    </div>
                    <div>
                        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCompetencyModal" class="fw-bold">
                            {{ __('hrms.feedback_360.btn_add_competency_questions') }}
                        </x-ui.button>
                    </div>
                </div>

                <div class="row g-3">
                    @forelse($competencies as $comp)
                        <div class="col-xl-6 col-12">
                            <div class="card border shadow-sm p-3.5 review-card" style="border-radius: 12px !important;">
                                <div class="d-flex justify-content-between align-items-start mb-1.5">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1 fs-15">{{ $comp->name }}</h6>
                                    </div>
                                    <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" :title="__('hrms.feedback_360.modal_delete_comp_title')" data-bs-toggle="modal" data-bs-target="#deleteCompModal{{ $comp->id }}" />
                                </div>

                                @if($comp->description)
                                    <p class="text-muted fs-12 mb-2 lh-base">{{ $comp->description }}</p>
                                @endif

                                <!-- Delete Competency Confirmation Modal -->
                                <x-ui.modal id="deleteCompModal{{ $comp->id }}" :title="__('hrms.feedback_360.modal_delete_comp_title')" size="sm" :centered="true" :static="true" :showFooter="false">
                                    <div class="text-center py-2">
                                        <div class="avatar-initials bg-danger-subtle text-danger mx-auto mb-3" style="width: 48px; height: 48px; font-size: 18px;">
                                            <i class="feather-trash-2"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">{{ __('hrms.feedback_360.modal_delete_comp_confirm') }}</h6>
                                        <p class="text-muted fs-12 mb-3">
                                            {{ __('hrms.feedback_360.modal_delete_comp_msg', ['name' => $comp->name]) }}
                                        </p>
                                        <form action="{{ route('hrms.feedback360.competencies.destroy', $comp->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <div class="d-flex justify-content-center gap-2">
                                                <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                                <x-ui.button variant="danger" size="sm" type="submit" icon="feather-trash-2" class="fw-bold">{{ __('hrms.feedback_360.btn_yes_delete') }}</x-ui.button>
                                            </div>
                                        </form>
                                    </div>
                                </x-ui.modal>

                                <div class="border-top pt-2.5 mt-2">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-muted fs-11 fw-bold text-uppercase d-inline-flex align-items-center gap-1">
                                            <i class="feather-help-circle text-primary"></i> {{ __('hrms.feedback_360.evaluation_questions_label') }}
                                        </span>
                                        <span class="badge bg-light text-secondary border rounded-pill fs-10 px-2 py-0.5">
                                            {{ __('hrms.feedback_360.prompts_count', ['count' => $comp->questions->count()]) }}
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
                                                                {{ $q->question_type === 'rating_scale' ? __('hrms.feedback_360.scale_1_to_5') : __('hrms.feedback_360.open_text_comment') }}
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
                                                    <x-ui.icon-btn type="submit" icon="feather-x" variant="soft-danger" size="sm" :title="__('hrms.feedback_360.tooltip_remove_question')" />
                                                </form>
                                            </div>
                                        @empty
                                            <div class="text-muted fs-11 fst-italic py-2 text-center bg-light rounded">{{ __('hrms.feedback_360.no_questions_linked') }}</div>
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
                                <h6 class="fw-bold text-dark mb-1">{{ __('hrms.feedback_360.no_competencies_found') }}</h6>
                                <p class="text-muted fs-12 mb-3">{{ __('hrms.feedback_360.no_competencies_desc') }}</p>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCompetencyModal" class="fw-bold">
                                    {{ __('hrms.feedback_360.btn_create_first_competency') }}
                                </x-ui.button>
                            </div>
                        </div>
                    @endforelse


                </div>
            </div>

        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CREATE FEEDBACK CYCLE -->
<!-- ========================================================================= -->
<x-ui.modal id="createCycleModal" :title="__('hrms.feedback_360.modal_create_cycle_title')" size="lg" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.cycles.store') }}" method="POST" id="createCycleForm">
        @csrf
        <div class="row g-4">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.feedback_360.field_cycle_name')" name="name" :placeholder="__('hrms.feedback_360.placeholder_cycle_name')" :required="true" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.feedback_360.field_start_date')" name="start_date" :value="date('Y-m-01')" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.feedback_360.field_end_date')" name="end_date" :value="date('Y-m-t', strtotime('+1 month'))" :required="true" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.feedback_360.field_nomination_due')" name="nomination_deadline" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.feedback_360.field_feedback_due')" name="submission_deadline" />
            </div>

            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.feedback_360.field_description')" name="description" rows="2" :placeholder="__('hrms.feedback_360.placeholder_cycle_description')" />
            </div>

            <div class="col-12">
                <div class="p-3 bg-light rounded border">
                    <h6 class="fw-bold text-dark fs-12 text-uppercase mb-3">{{ __('hrms.feedback_360.section_anonymity_governance') }}</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.feedback_360.field_peer_privacy')" name="is_peer_anonymous" value="1" checked>
                                {{ __('hrms.feedback_360.label_anonymous_peer') }}
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.feedback_360.field_upward_privacy')" name="is_direct_report_anonymous" value="1" checked>
                                {{ __('hrms.feedback_360.label_anonymous_upward') }}
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.feedback_360.field_self_nomination')" name="allow_self_nomination" value="1" checked>
                                {{ __('hrms.feedback_360.label_allow_self_nomination') }}
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="checkbox" :label="__('hrms.feedback_360.field_approvals')" name="require_manager_approval" value="1" checked>
                                {{ __('hrms.feedback_360.label_require_manager_approval') }}
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.feedback_360.field_participants')" name="employee_ids[]" :multiple="true" :searchable="true" :helperText="__('hrms.feedback_360.helper_participants_enroll')">
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->designation->name ?? __('hrms.feedback_360.staff_fallback') }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <!-- Competencies Selection for this Cycle -->
            <div class="col-12">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-0.5">
                                <h6 class="fw-bold text-dark fs-12 text-uppercase mb-0" style="letter-spacing: 0.5px;">
                                    {{ __('hrms.feedback_360.section_competencies_eval') }} <span class="text-danger">*</span>
                                </h6>
                                <span id="selectedCompCountBadge" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fs-11 fw-semibold">
                                    {{ __('hrms.feedback_360.badge_selected_comp_count', ['selected' => $competencies->count(), 'total' => $competencies->count()]) }}
                                </span>
                            </div>
                            <span class="text-muted fs-11">{{ __('hrms.feedback_360.helper_competencies_select') }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary px-3 py-1 fw-bold fs-12 rounded shadow-xs" onclick="toggleCycleCompetencies(true)">
                                {{ __('hrms.feedback_360.btn_select_all') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1 fw-semibold fs-12 rounded shadow-xs" onclick="toggleCycleCompetencies(false)">
                                {{ __('hrms.feedback_360.btn_deselect_all') }}
                            </button>
                        </div>
                    </div>

                    <div id="cycleCompetencyAlert" class="alert alert-danger py-2 px-3 fs-12 mb-2 d-none">
                        <i class="feather-alert-triangle me-1"></i>{{ __('hrms.feedback_360.alert_select_at_least_one_comp') }}
                    </div>

                    @if($competencies->isEmpty())
                        <div class="text-center py-4 bg-white rounded-3 border">
                            <i class="feather-info text-primary fs-20 mb-1"></i>
                            <div class="text-dark fw-semibold fs-13">{{ __('hrms.feedback_360.no_master_comp_found') }}</div>
                            <div class="text-muted fs-11">{{ __('hrms.feedback_360.no_master_comp_desc') }}</div>
                        </div>
                    @else
                        <div class="d-flex flex-column gap-2" style="max-height: 280px; overflow-y: auto; padding-right: 2px;">
                            @foreach($competencies as $comp)
                                <label for="comp_check_{{ $comp->id }}" class="competency-select-card is-selected d-flex align-items-start gap-2.5 p-2.5 rounded-3 mb-0 shadow-xs">
                                    <div class="m-0 pt-0.5 flex-shrink-0" style="padding-left: 0 !important; width: 20px;">
                                        <input type="checkbox"
                                               name="competency_ids[]"
                                               value="{{ $comp->id }}"
                                               id="comp_check_{{ $comp->id }}"
                                               class="form-check-input cycle-comp-checkbox"
                                               checked
                                               onchange="updateSelectedCompCount()"
                                               style="width: 18px !important; height: 18px !important; margin: 0 !important; float: none !important; cursor: pointer;">
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                            <span class="fw-bold text-dark fs-13 text-break">{{ $comp->name }}</span>
                                            <span class="badge bg-light text-muted border fs-11 px-2.5 py-1 rounded-pill flex-shrink-0">
                                                {{ __('hrms.feedback_360.questions_count_badge', ['count' => $comp->questions_count ?? $comp->questions->count()]) }}
                                            </span>
                                        </div>
                                        @if($comp->description)
                                            <p class="text-muted fs-12 lh-sm text-break mb-0 mt-1">{{ $comp->description }}</p>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.feedback_360.btn_discard') }}</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">{{ __('hrms.feedback_360.btn_create_cycle_submit') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL: CREATE COMPETENCY & EVALUATION QUESTIONS (UNIFIED FORM) -->
<!-- ========================================================================= -->
<x-ui.modal id="createCompetencyModal" :title="__('hrms.feedback_360.modal_create_comp_title')" size="lg" :centered="true" :static="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.competencies.store') }}" method="POST" id="createCompetencyWithQuestionsForm">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.feedback_360.field_comp_name')" name="name" :placeholder="__('hrms.feedback_360.placeholder_comp_name')" :required="true" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.feedback_360.field_comp_desc')" name="description" rows="2" :placeholder="__('hrms.feedback_360.placeholder_comp_desc')" />
            </div>
        </div>

        <!-- Dynamic Questions Section -->
        <div class="mt-4 pt-3 border-top">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('hrms.feedback_360.questions_for_comp_heading') }}</h6>
                    <span class="text-muted fs-11">{{ __('hrms.feedback_360.questions_for_comp_sub') }}</span>
                </div>
                <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" id="addCompQuestionRowBtn" class="border fw-semibold shadow-xs">
                    {{ __('hrms.feedback_360.btn_add_question_row') }}
                </x-ui.button>
            </div>

            <div class="table-responsive border rounded bg-white">
                <table class="table table-hover align-middle mb-0" id="compQuestionsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 48%; min-width: 300px;" class="ps-3 py-2.5 text-uppercase fs-11 fw-bold text-muted">{{ __('hrms.feedback_360.th_question_prompt') }} <span class="text-danger">*</span></th>
                            <th style="width: 26%; min-width: 190px;" class="py-2.5 text-uppercase fs-11 fw-bold text-muted">{{ __('hrms.feedback_360.th_question_type') }}</th>
                            <th style="width: 20%; min-width: 180px;" class="py-2.5 text-uppercase fs-11 fw-bold text-muted">{{ __('hrms.feedback_360.th_target_rater') }}</th>
                            <th style="width: 6%; min-width: 55px;" class="text-center pe-3 py-2.5 text-uppercase fs-11 fw-bold text-muted">{{ __('hrms.feedback_360.th_action_short') }}</th>
                        </tr>
                    </thead>
                    <tbody id="compQuestionsTbody">
                        <tr>
                            <td class="ps-3 py-2.5">
                                <input type="text" name="questions[0][question_text]" class="odoo-table-input fs-13" placeholder="{{ __('hrms.feedback_360.placeholder_q1') }}" required>
                            </td>
                            <td class="py-2.5">
                                <x-ui.odoo-form-ui type="select" name="questions[0][question_type]" :searchable="true">
                                    <option value="rating_scale" selected>{{ __('hrms.feedback_360.option_scale_1_5') }}</option>
                                    <option value="text">{{ __('hrms.feedback_360.option_open_text') }}</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td class="py-2.5">
                                <x-ui.odoo-form-ui type="select" name="questions[0][target_reviewer_type]" :searchable="true">
                                    <option value="all" selected>{{ __('hrms.feedback_360.target_all') }}</option>
                                    <option value="manager">{{ __('hrms.feedback_360.target_manager') }}</option>
                                    <option value="peer">{{ __('hrms.feedback_360.target_peer') }}</option>
                                    <option value="direct_report">{{ __('hrms.feedback_360.target_direct_report') }}</option>
                                    <option value="self">{{ __('hrms.feedback_360.target_self') }}</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td class="text-center pe-3 py-2.5">
                                <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" class="delete-comp-q-row" :title="__('hrms.feedback_360.tooltip_remove_q_row')" />
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-3 py-2.5">
                                <input type="text" name="questions[1][question_text]" class="odoo-table-input fs-13" placeholder="{{ __('hrms.feedback_360.placeholder_q2') }}">
                            </td>
                            <td class="py-2.5">
                                <x-ui.odoo-form-ui type="select" name="questions[1][question_type]" :searchable="true">
                                    <option value="rating_scale" selected>{{ __('hrms.feedback_360.option_scale_1_5') }}</option>
                                    <option value="text">{{ __('hrms.feedback_360.option_open_text') }}</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td class="py-2.5">
                                <x-ui.odoo-form-ui type="select" name="questions[1][target_reviewer_type]" :searchable="true">
                                    <option value="all" selected>{{ __('hrms.feedback_360.target_all') }}</option>
                                    <option value="manager">{{ __('hrms.feedback_360.target_manager') }}</option>
                                    <option value="peer">{{ __('hrms.feedback_360.target_peer') }}</option>
                                    <option value="direct_report">{{ __('hrms.feedback_360.target_direct_report') }}</option>
                                    <option value="self">{{ __('hrms.feedback_360.target_self') }}</option>
                                </x-ui.odoo-form-ui>
                            </td>
                            <td class="text-center pe-3 py-2.5">
                                <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" class="delete-comp-q-row" :title="__('hrms.feedback_360.tooltip_remove_q_row')" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.feedback_360.btn_discard') }}</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" icon="feather-check" class="fw-bold px-3">{{ __('hrms.feedback_360.btn_save_comp_questions') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL: CREATE QUESTION -->
<!-- ========================================================================= -->
<x-ui.modal id="createQuestionModal" :title="__('hrms.feedback_360.modal_create_question_title')" size="md" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.questions.store') }}" method="POST">
        @csrf
        <div class="d-flex flex-column gap-3">
            <x-ui.odoo-form-ui type="select" :label="__('hrms.feedback_360.field_competency')" name="competency_id" :searchable="true">
                <option value="">{{ __('hrms.feedback_360.option_general_no_comp') }}</option>
                @foreach($competencies as $comp)
                    <option value="{{ $comp->id }}">{{ $comp->name }}</option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.feedback_360.field_question_text')" name="question_text" rows="2" :placeholder="__('hrms.feedback_360.placeholder_question_text')" :required="true" />

            <x-ui.odoo-form-ui type="select" :label="__('hrms.feedback_360.th_question_type')" name="question_type" :required="true" :searchable="false">
                <option value="rating_scale">{{ __('hrms.feedback_360.option_scale_1_5') }}</option>
                <option value="text">{{ __('hrms.feedback_360.option_open_text') }}</option>
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="select" :label="__('hrms.feedback_360.th_target_rater')" name="target_reviewer_type" :required="true" :searchable="false">
                <option value="all">{{ __('hrms.feedback_360.target_all') }}</option>
                <option value="manager">{{ __('hrms.feedback_360.target_manager') }}</option>
                <option value="peer">{{ __('hrms.feedback_360.target_peer') }}</option>
                <option value="direct_report">{{ __('hrms.feedback_360.target_direct_report') }}</option>
                <option value="self">{{ __('hrms.feedback_360.target_self') }}</option>
            </x-ui.odoo-form-ui>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.feedback_360.btn_discard') }}</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">{{ __('hrms.feedback_360.btn_save_question') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

@foreach($cycles as $cycle)
    <!-- Modal: Add Participants for this cycle -->
    <x-ui.modal id="addParticipantsModal{{ $cycle->id }}" :title="__('hrms.feedback_360.modal_enroll_participants_title', ['name' => $cycle->name])" size="md" :centered="true" :showFooter="false">
        <form action="{{ route('hrms.feedback360.cycles.participants', $cycle->id) }}" method="POST">
            @csrf
            <x-ui.odoo-form-ui type="select" :label="__('hrms.feedback_360.field_employees')" name="employee_ids[]" :multiple="true" :searchable="true" :required="true" :helperText="__('hrms.feedback_360.helper_enroll_participants')">
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}">
                        {{ $emp->full_name }} ({{ $emp->designation->name ?? __('hrms.feedback_360.staff_fallback') }} - {{ $emp->department->name ?? __('hrms.feedback_360.general_fallback') }})
                    </option>
                @endforeach
            </x-ui.odoo-form-ui>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.feedback_360.btn_discard') }}</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">{{ __('hrms.feedback_360.btn_enroll_participants') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endforeach

@foreach($myEvaluations as $eval)
    @php
        $directReports = $eval->employee?->directReports ?? collect();
        $isManagerReviewee = $directReports->isNotEmpty();
        $assignedDrIds = $eval->nominations->where('reviewer_type', 'direct_report')->pluck('reviewer_id')->toArray();
        $assignedPeerIds = $eval->nominations->where('reviewer_type', 'peer')->pluck('reviewer_id')->toArray();
    @endphp
    <!-- Modal: Nominate Reviewers for this Evaluation -->
    <x-ui.modal id="nominatePeersModal{{ $eval->id }}" :title="__('hrms.feedback_360.modal_nominate_reviewers_title', ['name' => $eval->cycle?->name])" size="{{ $isManagerReviewee ? 'lg' : 'md' }}" :centered="true" :showFooter="false">
        <form action="{{ route('hrms.feedback360.participants.nominate-peers', $eval->id) }}" method="POST">
            @csrf

            <!-- Section 1: Peer Reviewers -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-12 fw-bold text-uppercase text-dark" style="letter-spacing: 0.5px;">
                        <i class="feather-users text-primary me-1"></i> {{ __('hrms.feedback_360.section_peer_raters') }}
                    </span>
                    <span class="text-muted fs-11">
                        {{ __('hrms.feedback_360.min_max_peers', ['min' => $eval->cycle?->min_peer_nominations ?? 2, 'max' => $eval->cycle?->max_peer_nominations ?? 5]) }}
                    </span>
                </div>
                <x-ui.odoo-form-ui type="select" :label="__('hrms.feedback_360.field_peer_raters')" name="peer_ids[]" :multiple="true" :searchable="true" :helperText="__('hrms.feedback_360.helper_peer_raters')">
                    @foreach($employees as $emp)
                        @if($emp->id !== $eval->employee_id && !$directReports->pluck('id')->contains($emp->id))
                            <option value="{{ $emp->id }}" {{ in_array($emp->id, $assignedPeerIds) ? 'selected' : '' }}>
                                {{ $emp->full_name }} ({{ $emp->designation->name ?? __('hrms.feedback_360.staff_fallback') }} - {{ $emp->department->name ?? __('hrms.feedback_360.general_fallback') }})
                            </option>
                        @endif
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <!-- Section 2: Direct Reports (Shown only if employee has reporting subordinates) -->
            @if($isManagerReviewee)
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fs-12 fw-bold text-uppercase text-primary" style="letter-spacing: 0.5px;">
                            <i class="feather-user-check me-1"></i> {{ __('hrms.feedback_360.section_direct_reports_upward') }}
                        </span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">
                            {{ __('hrms.feedback_360.badge_team_size', ['count' => $directReports->count()]) }}
                        </span>
                    </div>
                    <p class="text-muted fs-12 mb-2">
                        {{ __('hrms.feedback_360.direct_reports_desc') }}
                    </p>
                    <x-ui.odoo-form-ui type="select" :label="__('hrms.feedback_360.field_direct_report_raters')" name="direct_report_ids[]" :multiple="true" :searchable="true" :helperText="__('hrms.feedback_360.helper_direct_report_raters')">
                        @foreach($directReports as $dr)
                            <option value="{{ $dr->id }}" {{ in_array($dr->id, $assignedDrIds) ? 'selected' : '' }}>
                                {{ $dr->full_name }} ({{ $dr->designation->name ?? __('hrms.feedback_360.staff_fallback') }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
            @endif

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.feedback_360.btn_discard') }}</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">{{ __('hrms.feedback_360.btn_submit_nominations') }}</x-ui.button>
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
    <x-ui.modal id="{{ $modalId }}" :title="__('hrms.feedback_360.modal_review_nominations_title', ['name' => $subjectEmployee?->full_name])" size="lg" :centered="true" :showFooter="false">
        <form action="{{ route('hrms.feedback360.nominations.batch-approve') }}" method="POST">
            @csrf

            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                <div class="avatar-initials bg-primary-subtle text-primary fw-bold me-3 flex-shrink-0" style="width: 40px; height: 40px; min-width: 40px; font-size: 14px;">
                    {{ substr($subjectEmployee?->first_name ?? 'E', 0, 1) }}{{ substr($subjectEmployee?->last_name ?? '', 0, 1) }}
                </div>
                <div>
                    <h6 class="fw-bold text-dark fs-14 mb-0.5">{{ $subjectEmployee?->full_name }}</h6>
                    <span class="text-muted fs-12">{{ $subjectEmployee?->department?->name ?? __('hrms.feedback_360.team_fallback') }} &bull; {{ $subjectEmployee?->designation?->name ?? __('hrms.feedback_360.employee_fallback') }} &bull; <strong class="text-primary">{{ $cycle?->name }}</strong></span>
                </div>
            </div>

            <div class="alert alert-light border text-muted fs-12 mb-3 py-2 px-3 rounded d-flex align-items-center gap-2">
                <i class="feather-info text-primary fs-14"></i>
                <span>{{ __('hrms.feedback_360.nomination_checklist_info') }}</span>
            </div>

            <!-- Select All / Total Strip -->
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll_{{ $modalId }}" checked onchange="document.querySelectorAll('.nom-check-{{ $modalId }}').forEach(cb => cb.checked = this.checked);">
                    <label class="form-check-label fw-bold text-dark fs-12 cursor-pointer" for="selectAll_{{ $modalId }}">
                        {{ __('hrms.feedback_360.select_all_nominees', ['count' => $group->count()]) }}
                    </label>
                </div>
                <span class="text-muted fs-12">
                    <i class="feather-calendar me-1"></i>{{ __('hrms.feedback_360.requested_date_label', ['date' => $firstNom->created_at->format('M d, Y')]) }}
                </span>
            </div>

            <!-- Nominee Checklist Items -->
            <div class="d-flex flex-column gap-2 mb-3">
                @foreach($group as $nom)
                    <label class="d-flex align-items-center gap-3 p-3 border rounded bg-white hover-bg-light cursor-pointer mb-0" for="nom_check_{{ $nom->id }}" style="transition: all 0.15s ease-in-out;">
                        <input class="form-check-input flex-shrink-0 mt-0 nom-check-{{ $modalId }}" type="checkbox" name="nomination_ids[]" value="{{ $nom->id }}" id="nom_check_{{ $nom->id }}" checked>
                        <div class="avatar-initials {{ $nom->reviewer_type === 'direct_report' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info' }} fw-bold flex-shrink-0 me-2" style="width: 36px; height: 36px; min-width: 36px;">
                            {{ substr($nom->reviewer?->first_name ?? 'R', 0, 1) }}
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <span class="fw-bold text-dark fs-13">{{ $nom->reviewer?->full_name }}</span>
                                <span class="badge {{ $nom->reviewer_type === 'direct_report' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-info-subtle text-info border border-info-subtle' }} rounded-pill px-2.5 py-0.5 fs-11 fw-semibold">
                                    {{ $nom->reviewer_type === 'direct_report' ? __('hrms.feedback_360.badge_direct_report_upward') : __('hrms.feedback_360.badge_peer') }}
                                </span>
                            </div>
                            <div class="text-muted fs-11 mt-0.5">
                                <span>{{ $nom->reviewer?->department?->name ?? __('hrms.feedback_360.team_fallback') }}</span>
                                <span class="text-secondary">&bull;</span>
                                <span>{{ $nom->reviewer?->designation?->name ?? __('hrms.feedback_360.badge_peer') }}</span>
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
                <label class="form-label text-muted fs-12 fw-semibold mb-1">{{ __('hrms.feedback_360.optional_rejection_reason') }}</label>
                <input type="text" name="reason" class="form-control form-control-sm fs-12" placeholder="{{ __('hrms.feedback_360.rejection_reason_placeholder') }}">
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <x-ui.button variant="light" size="sm" data-bs-dismiss="modal" class="border">
                    {{ __('hrms.common.cancel') }}
                </x-ui.button>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="danger" size="sm" outline type="submit" name="action" value="reject" icon="feather-x">
                        {{ __('hrms.feedback_360.btn_reject_selected') }}
                    </x-ui.button>
                    <x-ui.button variant="success" size="sm" type="submit" name="action" value="approve" icon="feather-check-circle" class="fw-bold px-3">
                        {{ __('hrms.feedback_360.btn_approve_selected') }}
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

function updateSelectedCompCount() {
    const checkboxes = document.querySelectorAll('.cycle-comp-checkbox');
    const total = checkboxes.length;
    let checked = 0;

    checkboxes.forEach(function(cb) {
        const card = cb.closest('.competency-select-card');
        if (cb.checked) {
            checked++;
            if (card) {
                card.classList.add('is-selected');
                card.style.borderColor = '#0d6efd';
                card.style.backgroundColor = '#f8faff';
            }
        } else {
            if (card) {
                card.classList.remove('is-selected');
                card.style.borderColor = '#e2e8f0';
                card.style.backgroundColor = '#ffffff';
            }
        }
    });

    const badge = document.getElementById('selectedCompCountBadge');
    if (badge) {
        const ofWord = @json(__('hrms.feedback_360.badge_selected_comp_count', ['selected' => ':selected', 'total' => ':total'])).replace(':selected', checked).replace(':total', total);
        badge.textContent = ofWord;
        if (checked === 0) {
            badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fs-11 fw-semibold';
        } else if (checked === total) {
            badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fs-11 fw-semibold';
        } else {
            badge.className = 'badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 fs-11 fw-semibold';
        }
    }

    const alertEl = document.getElementById('cycleCompetencyAlert');
    if (alertEl && checked > 0) {
        alertEl.classList.add('d-none');
    }
}

function toggleCycleCompetencies(select) {
    document.querySelectorAll('.cycle-comp-checkbox').forEach(function(cb) {
        cb.checked = select;
    });
    updateSelectedCompCount();
}

window.toggleCycleCompetencies = toggleCycleCompetencies;
window.updateSelectedCompCount = updateSelectedCompCount;

document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCompCount();

    const cycleForm = document.getElementById('createCycleForm');
    if (cycleForm) {
        cycleForm.addEventListener('submit', function(e) {
            const checked = cycleForm.querySelectorAll('.cycle-comp-checkbox:checked');
            const alertEl = document.getElementById('cycleCompetencyAlert');
            if (checked.length === 0) {
                e.preventDefault();
                if (alertEl) {
                    alertEl.classList.remove('d-none');
                    alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
        });
    }
});
</script>
@endpush

@endsection
