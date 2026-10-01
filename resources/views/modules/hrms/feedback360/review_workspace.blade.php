@extends('layouts.duralux')

@section('title', '360° Review: ' . $nomination->employee?->full_name . ' | HRMS')
@section('page-title', '360° Evaluation Workspace')
@section('breadcrumb', 'HRMS / 360° Feedback / Review Workspace')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.feedback360.index', ['active_tab' => 'my_reviews']) }}" class="border fw-semibold">
            Back to Reviews
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    .avatar-initials {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.12) !important;
        color: var(--bs-primary) !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 15px;
        flex-shrink: 0;
    }

    /* Competency Card */
    .competency-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #ffffff;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }

    .competency-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 14px 20px;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }

    .question-row {
        padding: 16px 20px;
        transition: background-color 0.15s ease;
    }
    .question-row:not(:last-child) {
        border-bottom: 1px solid #f1f5f9;
    }
    .question-row:hover {
        background-color: #fafbfc;
    }

    /* Compact Segmented Rating Bar */
    .segmented-rating-bar {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 6px;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .segmented-rating-item {
        position: relative;
        margin: 0;
        cursor: pointer;
    }

    .segmented-rating-item input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .rating-pill-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 38px;
        padding: 0 8px;
        border-radius: 7px;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        background: transparent;
        transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
    }

    .rating-num-badge {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #334155;
        font-size: 11px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.18s ease;
    }

    .segmented-rating-item:hover .rating-pill-btn {
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .segmented-rating-item:hover .rating-num-badge {
        background: #cbd5e1;
    }

    /* Active / Checked State */
    .segmented-rating-item input[type="radio"]:checked + .rating-pill-btn {
        background: var(--bs-primary);
        color: #ffffff !important;
        box-shadow: 0 3px 8px rgba(var(--bs-primary-rgb), 0.35);
    }

    .segmented-rating-item input[type="radio"]:checked + .rating-pill-btn .rating-num-badge {
        background: #ffffff;
        color: var(--bs-primary) !important;
        font-weight: 800;
    }

    /* Progress bar */
    .progress-track-wrapper {
        position: sticky;
        top: 0;
        z-index: 100;
        background: #ffffff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    @media (max-width: 991px) {
        .segmented-rating-bar {
            grid-template-columns: 1fr;
            gap: 4px;
        }
        .rating-pill-btn {
            justify-content: flex-start;
            padding: 0 12px;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <form id="feedbackForm" action="{{ route('hrms.feedback360.review.submit', $nomination->id) }}" method="POST">
        @csrf

        <!-- Subject Header Banner Card -->
        <div class="bg-white p-3 p-md-4 rounded-3 border mb-3 shadow-sm">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-initials">
                        {{ substr($nomination->employee?->first_name ?? 'E', 0, 1) }}{{ substr($nomination->employee?->last_name ?? '', 0, 1) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="fw-bold text-dark mb-0">{{ $nomination->employee?->full_name }}</h5>
                            @php
                                $roleBadge = match($nomination->reviewer_type) {
                                    'self'          => ['bg' => 'bg-primary-subtle text-primary border border-primary-subtle', 'label' => 'Self Review'],
                                    'manager'       => ['bg' => 'bg-purple-subtle text-purple border border-purple-subtle', 'label' => 'Direct Manager Review'],
                                    'peer'          => ['bg' => 'bg-info-subtle text-info border border-info-subtle', 'label' => 'Peer Review'],
                                    'direct_report' => ['bg' => 'bg-success-subtle text-success border border-success-subtle', 'label' => 'Upward Feedback (Direct Report)'],
                                    default         => ['bg' => 'bg-light text-dark border', 'label' => ucfirst($nomination->reviewer_type)],
                                };
                            @endphp
                            <span class="badge {{ $roleBadge['bg'] }} rounded-pill px-2.5 py-1 fs-11 fw-semibold">
                                {{ $roleBadge['label'] }}
                            </span>
                        </div>
                        <div class="text-muted fs-12 mt-0.5">
                            {{ $nomination->employee?->designation->name ?? 'Staff' }} &bull; {{ $nomination->employee?->department->name ?? 'General' }} &bull; Cycle: <strong>{{ $nomination->cycle?->name }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Anonymity Notice -->
                @if($nomination->is_anonymous)
                    <div class="d-inline-flex align-items-center gap-2 bg-success-subtle text-success px-3 py-1.5 rounded-pill fs-12 fw-semibold border border-success-subtle">
                        <i class="feather-shield fs-13"></i>
                        <span>Anonymous Feedback Protected</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sticky Live Evaluation Progress Bar -->
        <div class="progress-track-wrapper p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1.5">
                <span class="fs-12 fw-bold text-dark">
                    <i class="feather-check-circle text-primary me-1"></i> Evaluation Progress: <span id="progressText">0 / 0 Answered</span>
                </span>
                <span class="fs-12 fw-bold text-primary" id="progressPercent">0%</span>
            </div>
            <div class="progress" style="height: 6px; background-color: #e2e8f0; border-radius: 4px;">
                <div id="progressBar" class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>

        <!-- Questions Grouped by Competency -->
        @foreach($competencyGroups as $groupTitle => $groupQuestions)
            <div class="competency-card">
                <div class="competency-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-dark mb-0 fs-14 d-flex align-items-center gap-2">
                            <i class="feather-award text-primary fs-15"></i>
                            {{ $groupTitle }}
                        </h6>
                        @if($groupQuestions->first()?->competency?->description)
                            <p class="text-muted fs-11 mb-0 mt-0.5">{{ $groupQuestions->first()->competency->description }}</p>
                        @endif
                    </div>
                    <span class="badge bg-white text-muted border fs-11 fw-semibold">{{ $groupQuestions->count() }} Question{{ $groupQuestions->count() > 1 ? 's' : '' }}</span>
                </div>

                <div class="p-0">
                    @foreach($groupQuestions as $q)
                        @php
                            $existing = $existingResponses[$q->id] ?? null;
                            $curRating = $existing?->rating_value ? (int) $existing->rating_value : null;
                            $curText   = $existing?->text_response ?? '';
                        @endphp

                        <div class="question-row" data-question-item="true">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <label class="form-label fw-semibold text-dark fs-13 mb-0 lh-base">
                                    <span class="text-muted fw-bold me-1">{{ $loop->iteration }}.</span> {{ $q->question_text }}
                                    @if($q->is_required) <span class="text-danger">*</span> @endif
                                </label>
                            </div>

                            @if($q->description)
                                <p class="text-muted fs-11 mb-2">{{ $q->description }}</p>
                            @endif

                            @if($q->question_type === 'rating_scale')
                                <div class="segmented-rating-bar mt-1">
                                    @php
                                        $levels = [
                                            1 => 'Needs Improvement',
                                            2 => 'Developing',
                                            3 => 'Proficient',
                                            4 => 'Advanced',
                                            5 => 'Role Model',
                                        ];
                                    @endphp
                                    @foreach($levels as $val => $lbl)
                                        <label class="segmented-rating-item" title="{{ $val }} - {{ $lbl }}">
                                            <input type="radio" name="responses[{{ $q->id }}][rating]" value="{{ $val }}" {{ $curRating === $val ? 'checked' : '' }} {{ $q->is_required ? 'required' : '' }} class="rating-input">
                                            <span class="rating-pill-btn">
                                                <span class="rating-num-badge">{{ $val }}</span>
                                                <span class="text-truncate">{{ $lbl }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-2">
                                    <textarea name="responses[{{ $q->id }}][text]" class="form-control text-input fs-13" rows="2" placeholder="Provide specific examples, strengths, or recommendations..." {{ $q->is_required ? 'required' : '' }}>{{ $curText }}</textarea>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <!-- Sticky Floating Submit Bar -->
        <div class="bg-white p-3 shadow-sm rounded-3 border d-flex justify-content-between align-items-center sticky-bottom mb-4">
            <span class="text-muted fs-12 d-none d-sm-inline">
                <i class="feather-info text-primary me-1"></i> Responses auto-save when submitted. You can update answers until the feedback deadline.
            </span>
            <div class="d-flex gap-2 ms-auto">
                <x-ui.button type="submit" variant="light" size="sm" icon="feather-save" name="submit_action" value="draft" formnovalidate class="border fw-semibold">
                    Save Draft & Exit
                </x-ui.button>
                <x-ui.button type="button" variant="primary" size="sm" icon="feather-send" class="fw-bold px-3.5" data-bs-toggle="modal" data-bs-target="#confirmSubmitReviewModal">
                    Submit 360 Feedback
                </x-ui.button>
            </div>
        </div>

        <!-- Hidden input for submission action -->
        <input type="hidden" name="submit_action" id="submitActionInput" value="submit">

    </form>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CONFIRM 360 REVIEW SUBMISSION -->
<!-- ========================================================================= -->
<x-ui.modal id="confirmSubmitReviewModal" title="Submit 360° Evaluation" size="md" :centered="true" :showFooter="false">
    <div class="text-center py-2">
        <div class="avatar-initials bg-primary-subtle text-primary mx-auto mb-3" style="width: 54px; height: 54px; font-size: 22px;">
            <i class="feather-send"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1.5">Submit 360° Feedback?</h5>
        <p class="text-muted fs-13 mb-3">
            You are about to submit your evaluation for <strong>{{ $nomination->employee?->full_name }}</strong>. 
            Once confirmed, your answers will be securely recorded in the cycle dataset.
        </p>

        <div class="p-3 bg-light rounded-3 border mb-4 text-start">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-muted fs-12 fw-semibold">Questions Completed:</span>
                <span class="fw-bold fs-13 text-primary" id="modalProgressText">0 / 0 Answered</span>
            </div>
            <div class="progress" style="height: 5px;">
                <div id="modalProgressBar" class="progress-bar bg-primary" role="progressbar" style="width: 0%;"></div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 border-top pt-3">
            <x-ui.button variant="light" size="sm" class="border px-3" data-bs-dismiss="modal">
                Keep Editing
            </x-ui.button>
            <x-ui.button variant="primary" size="sm" icon="feather-check-circle" class="fw-bold px-4" id="confirmFinalSubmitBtn">
                Confirm & Submit
            </x-ui.button>
        </div>
    </div>
</x-ui.modal>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('feedbackForm');
        const questionItems = document.querySelectorAll('[data-question-item="true"]');
        const totalQuestions = questionItems.length;

        function updateProgress() {
            let answered = 0;

            questionItems.forEach(item => {
                const radio = item.querySelector('input[type="radio"]:checked');
                const text = item.querySelector('textarea');

                if (radio || (text && text.value.trim().length > 0)) {
                    answered++;
                }
            });

            const percent = totalQuestions > 0 ? Math.round((answered / totalQuestions) * 100) : 0;

            document.getElementById('progressText').textContent = `${answered} / ${totalQuestions} Answered`;
            document.getElementById('progressPercent').textContent = `${percent}%`;
            document.getElementById('progressBar').style.width = `${percent}%`;

            const modalText = document.getElementById('modalProgressText');
            const modalBar = document.getElementById('modalProgressBar');
            if (modalText && modalBar) {
                modalText.textContent = `${answered} / ${totalQuestions} (${percent}%)`;
                modalBar.style.width = `${percent}%`;
            }
        }

        form.addEventListener('change', updateProgress);
        form.addEventListener('input', updateProgress);

        // Initial calculate
        updateProgress();

        // Modal confirm submission
        const confirmBtn = document.getElementById('confirmFinalSubmitBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                document.getElementById('submitActionInput').value = 'submit';

                // Check standard validity
                if (!form.reportValidity()) {
                    // Close modal so user can see which required field is missing
                    const modalEl = document.getElementById('confirmSubmitReviewModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    return;
                }

                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';
                form.submit();
            });
        }
    });
</script>
@endpush
