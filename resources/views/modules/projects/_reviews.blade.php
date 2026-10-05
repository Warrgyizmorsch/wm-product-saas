@php
    $totalMilestones = $milestones->count();
    $completedMilestones = $milestones->where('status', \App\Domains\Projects\Models\Milestone::STATUS_COMPLETED)->count();
    $isUatReady = $totalMilestones > 0 && $completedMilestones === $totalMilestones;
    $pendingReview = $reviews->firstWhere('status', \App\Domains\Projects\Models\ProjectReview::STATUS_PENDING);
    $reworkReviews = $reviews->where('status', \App\Domains\Projects\Models\ProjectReview::STATUS_REWORK_REQUIRED);
@endphp

{{-- Milestone Readiness Banner --}}
@if (!$isUatReady)
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <i class="feather-alert-triangle fs-4 me-3 text-warning"></i>
        <div>
            <div class="fw-bold">{{ __('projects.uat_readiness_blocked') }}</div>
            <div class="fs-12 text-muted">
                {{ __('projects.milestones_breakdown', ['active' => $totalMilestones - $completedMilestones, 'completed' => $completedMilestones]) }}
            </div>
        </div>
    </div>
@else
    <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
        <i class="feather-check-circle fs-4 me-3 text-success"></i>
        <div>
            <div class="fw-bold">{{ __('projects.uat_readiness_ready') }}</div>
            <div class="fs-12 text-muted">
                {{ __('projects.milestones_breakdown', ['active' => 0, 'completed' => $completedMilestones]) }}
            </div>
        </div>
    </div>
@endif

{{-- Pending UAT Review Approval Banner (Production Standard Approval UX) --}}
@if ($pendingReview)
    <div class="alert alert-warning border-warning bg-soft-warning d-flex align-items-center justify-content-between p-3 mb-4 rounded shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <div class="avatar-text avatar-md bg-warning text-white me-3 d-flex align-items-center justify-content-center rounded" style="width: 38px; height: 38px;">
                <i class="feather-clock fs-18"></i>
            </div>
            <div>
                <h6 class="alert-heading fw-bold mb-1 text-dark">{{ __('projects.pending_uat_review_title', ['default' => 'Client UAT Review Pending Approval']) }}</h6>
                <p class="fs-12 mb-0 text-muted">{{ __('projects.pending_uat_review_desc', ['default' => 'A formal UAT review cycle is awaiting review and sign-off before project closure can proceed.']) }}</p>
            </div>
        </div>
        @if (auth()->user()->can('signoff', $pendingReview))
            <button type="button" class="btn btn-warning text-dark btn-sm fw-semibold" onclick="openSignOffModal({{ $pendingReview->id }})">
                <i class="feather-check-square me-1"></i>{{ __('projects.sign_off') }}
            </button>
        @endif
    </div>
@endif

{{-- Pending Change Requests Approval Banner (Production Standard Approval UX) --}}
@php
    $pendingCrCount = $changeRequests->where('status', \App\Domains\Projects\Models\ChangeRequest::STATUS_PENDING)->count();
@endphp
@if ($pendingCrCount > 0)
    <div class="alert alert-info border-info bg-soft-info d-flex align-items-center justify-content-between p-3 mb-4 rounded shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <div class="avatar-text avatar-md bg-info text-white me-3 d-flex align-items-center justify-content-center rounded" style="width: 38px; height: 38px;">
                <i class="feather-alert-circle fs-18"></i>
            </div>
            <div>
                <h6 class="alert-heading fw-bold mb-1 text-dark">{{ __('projects.pending_cr_approval_title', ['default' => 'Change Requests Awaiting Approval']) }}</h6>
                <p class="fs-12 mb-0 text-muted">{{ __('projects.pending_cr_approval_desc', ['default' => ':count change request(s) require project leadership review and budget authorization.', 'count' => $pendingCrCount]) }}</p>
            </div>
        </div>
        <span class="badge bg-info text-white fs-12 px-3 py-2">{{ $pendingCrCount }} {{ __('projects.pending') }}</span>
    </div>
@endif

{{-- Section A: Client Reviews / UAT --}}
<div class="card mb-4 border shadow-none">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0 fw-bold fs-15">{{ __('projects.client_reviews_uat') }}</h5>
            <div class="text-muted fs-12 mt-1">{{ __('projects.reviews_and_cr') }}</div>
        </div>
        @if ($canCreateReviews)
            <div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#initiateReviewModal"
                    @disabled(!$isUatReady || $pendingReview !== null)>
                    <i class="feather-plus-circle me-1"></i>{{ __('projects.initiate_review') }}
                </button>
            </div>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" tableClass="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted fs-11 text-uppercase border-bottom">
                        <th style="width: 120px;">{{ __('projects.review_date') }}</th>
                        <th style="width: 140px;">{{ __('projects.status') }}</th>
                        <th style="width: 160px;">{{ __('projects.reviewer') }}</th>
                        <th style="width: 140px;">{{ __('projects.sign_off_ref') }}</th>
                        <th>{{ __('projects.evidence') }}</th>
                        <th>{{ __('projects.description') }}</th>
                        <th style="width: 130px;" class="text-end">{{ __('projects.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reviews as $review)
                        @php
                            $reviewBadgeClass = match($review->status) {
                                \App\Domains\Projects\Models\ProjectReview::STATUS_PENDING => 'badge bg-warning-subtle text-warning border border-warning-subtle',
                                \App\Domains\Projects\Models\ProjectReview::STATUS_APPROVED => 'badge bg-success-subtle text-success border border-success-subtle',
                                \App\Domains\Projects\Models\ProjectReview::STATUS_REWORK_REQUIRED => 'badge bg-danger-subtle text-danger border border-danger-subtle',
                                default => 'badge bg-secondary-subtle text-secondary',
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $review->review_date?->format('Y-m-d') ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="{{ $reviewBadgeClass }}">
                                    {{ __('projects.review_statuses.' . $review->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $review->reviewer_display_name }}</div>
                            </td>
                            <td>
                                <span class="font-monospace fs-12">{{ $review->sign_off_ref ?? '—' }}</span>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    @forelse ($review->documents as $doc)
                                        <div class="d-flex align-items-center gap-1 fs-12">
                                            <i class="feather-paperclip text-muted"></i>
                                            <a href="{{ route('projects.documents.download', [$project, $doc]) }}" class="text-primary text-decoration-none text-truncate" style="max-width: 180px;" title="{{ $doc->file_name }}">
                                                {{ $doc->file_name }}
                                            </a>
                                        </div>
                                    @empty
                                        <span class="text-muted fs-12">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <div class="fs-12 text-muted text-truncate" style="max-width: 250px;" title="{{ $review->comments }}">
                                    {{ $review->comments ?? '—' }}
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    @if ($canUploadDocuments)
                                        <button type="button" class="btn btn-sm btn-icon btn-light" 
                                            onclick="openEvidenceModal({{ $review->id }})" 
                                            title="{{ __('projects.attach_evidence') }}">
                                            <i class="feather-upload"></i>
                                        </button>
                                    @endif
                                    @if ($review->isPending() && auth()->user()->can('signoff', $review))
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="openSignOffModal({{ $review->id }})">
                                            <i class="feather-check-square me-1"></i>{{ __('projects.sign_off') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="feather-check-circle fs-2 d-block mb-2 text-muted"></i>
                                <div class="fw-semibold">{{ __('projects.no_reviews_found') }}</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>
    </div>
</div>

{{-- Section B: Change Requests (CR) --}}
<div class="card mb-4 border shadow-none">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0 fw-bold fs-15">{{ __('projects.change_requests') }}</h5>
            <div class="text-muted fs-12 mt-1">{{ __('projects.change_requests') }}</div>
        </div>
        @if ($canCreateCRs)
            <div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createCrModal">
                    <i class="feather-plus-circle me-1"></i>{{ __('projects.new_change_request') }}
                </button>
            </div>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" tableClass="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted fs-11 text-uppercase border-bottom">
                        <th style="width: 140px;">{{ __('projects.cr_number') }}</th>
                        <th>{{ __('projects.cr_title') }}</th>
                        <th style="width: 150px;">{{ __('projects.review_reference') }}</th>
                        <th style="width: 180px;">{{ __('projects.budget_amount') }} / {{ __('projects.budget_hours') }}</th>
                        <th style="width: 120px;">{{ __('projects.status') }}</th>
                        <th style="width: 140px;">{{ __('projects.requested_by') }}</th>
                        <th style="width: 140px;">{{ __('projects.approved_by') }}</th>
                        <th style="width: 150px;" class="text-end">{{ __('projects.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($changeRequests as $cr)
                        @php
                            $crBadgeClass = match($cr->status) {
                                \App\Domains\Projects\Models\ChangeRequest::STATUS_PENDING => 'badge bg-warning-subtle text-warning border border-warning-subtle',
                                \App\Domains\Projects\Models\ChangeRequest::STATUS_APPROVED => 'badge bg-success-subtle text-success border border-success-subtle',
                                \App\Domains\Projects\Models\ChangeRequest::STATUS_REJECTED => 'badge bg-danger-subtle text-danger border border-danger-subtle',
                                \App\Domains\Projects\Models\ChangeRequest::STATUS_IMPLEMENTED => 'badge bg-info-subtle text-info border border-info-subtle',
                                default => 'badge bg-secondary-subtle text-secondary',
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-bold font-monospace text-primary">{{ $cr->cr_number }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $cr->title }}</div>
                                @if ($cr->description)
                                    <div class="fs-12 text-muted text-truncate" style="max-width: 250px;" title="{{ $cr->description }}">
                                        {{ $cr->description }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($cr->review)
                                    <span class="badge bg-light text-dark border">
                                        <i class="feather-link me-1"></i>Review #{{ $cr->review->id }}
                                    </span>
                                @else
                                    <span class="text-muted fs-12">{{ __('projects.standalone_cr') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="fs-12">
                                    <div><strong>+{{ format_currency($cr->impact_budget_amount) }}</strong></div>
                                    <div class="text-muted">+{{ number_format((float) $cr->impact_budget_hours, 1) }} hrs · +{{ $cr->impact_schedule_days }} d</div>
                                </div>
                            </td>
                            <td>
                                <span class="{{ $crBadgeClass }}">
                                    {{ __('projects.cr_statuses.' . $cr->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="fs-12 fw-semibold">{{ $cr->requester?->name ?? '—' }}</div>
                            </td>
                            <td>
                                @if ($cr->approver)
                                    <div class="fs-12 fw-semibold">{{ $cr->approver->name }}</div>
                                    <div class="fs-11 text-muted">{{ $cr->approved_at?->format('Y-m-d') }}</div>
                                @elseif ($cr->rejection_remarks)
                                    <div class="fs-11 text-danger text-truncate" style="max-width: 140px;" title="{{ $cr->rejection_remarks }}">
                                        {{ $cr->rejection_remarks }}
                                    </div>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end align-items-center gap-1">
                                    @if ($cr->isPending() && auth()->user()->can('approve', $cr))
                                        <form method="POST" action="{{ route('projects.change-requests.approve', [$project, $cr]) }}" 
                                            onsubmit="return confirm('{{ __('projects.confirm_approve_cr') }}')" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('projects.approve') }}">
                                                <i class="feather-check"></i> {{ __('projects.approve') }}
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="openRejectCrModal({{ $cr->id }})" title="{{ __('projects.reject') }}">
                                            <i class="feather-x"></i>
                                        </button>
                                    @elseif ($cr->isApproved() && (auth()->user()->can('markImplemented', $cr) || auth()->user()->can('implement', $cr)))
                                        <form method="POST" action="{{ route('projects.change-requests.implement', [$project, $cr]) }}" 
                                            onsubmit="return confirm('{{ __('projects.confirm_implement_cr') }}')" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary" title="{{ __('projects.mark_implemented') }}">
                                                <i class="feather-check-circle me-1"></i>{{ __('projects.mark_implemented') }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-file-text fs-2 d-block mb-2 text-muted"></i>
                                <div class="fw-semibold">{{ __('projects.no_change_requests_found') }}</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>
    </div>
</div>

{{-- Modal 1: Initiate Review Modal --}}
<x-ui.modal id="initiateReviewModal" :title="__('projects.initiate_uat_review')" size="md" :showFooter="false">
    <form method="POST" action="{{ route('projects.reviews.store', $project) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_modal" value="initiateReviewModal">

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.reviewer') }}</label>
                <x-ui.odoo-form-ui type="select" name="reviewer_id">
                    <option value="">{{ __('projects.select_option') }}</option>
                    @foreach ($activeMemberOptions as $memberUser)
                        <option value="{{ $memberUser->id }}" @selected(old('reviewer_id') == $memberUser->id)>
                            {{ $memberUser->name }} ({{ $memberUser->email }})
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.reviewer_name') }} ({{ __('projects.optional') }})</label>
                <x-ui.odoo-form-ui type="input" name="reviewer_name" :placeholder="__('projects.reviewer_name')" :value="old('reviewer_name')" />
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.review_date') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="input" inputType="date" name="review_date" :value="old('review_date', date('Y-m-d'))" required />
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.comments') }}</label>
                <x-ui.odoo-form-ui type="textarea" name="comments" rows="3" :placeholder="__('projects.comments')">{{ old('comments') }}</x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.evidence') }} ({{ __('projects.optional') }})</label>
                <input type="file" name="evidence_file" class="form-control form-control-sm">
                <div class="form-text fs-11 text-muted">{{ __('projects.max_file_size_hint') }}</div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="feather-check-circle me-1"></i>{{ __('projects.initiate_review') }}
            </button>
        </div>
    </form>
</x-ui.modal>

{{-- Modal 2: Sign Off Review Modal --}}
<x-ui.modal id="signOffReviewModal" :title="__('projects.sign_off_review')" size="md" :showFooter="false">
    <form id="signOffReviewForm" method="POST" action="" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_modal" value="signOffReviewModal">

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.status') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="select" name="status" required>
                    <option value="{{ \App\Domains\Projects\Models\ProjectReview::STATUS_APPROVED }}">
                        {{ __('projects.review_statuses.Approved') }}
                    </option>
                    <option value="{{ \App\Domains\Projects\Models\ProjectReview::STATUS_REWORK_REQUIRED }}">
                        {{ __('projects.review_statuses.Rework Required') }}
                    </option>
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.sign_off_ref') }} ({{ __('projects.optional') }})</label>
                <x-ui.odoo-form-ui type="input" name="sign_off_ref" :placeholder="__('projects.sign_off_ref')" :value="old('sign_off_ref')" />
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.comments') }}</label>
                <x-ui.odoo-form-ui type="textarea" name="comments" rows="3" :placeholder="__('projects.comments')">{{ old('comments') }}</x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.evidence') }} ({{ __('projects.optional') }})</label>
                <input type="file" name="evidence_file" class="form-control form-control-sm">
                <div class="form-text fs-11 text-muted">{{ __('projects.max_file_size_hint') }}</div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="feather-check-square me-1"></i>{{ __('projects.submit_signoff') }}
            </button>
        </div>
    </form>
</x-ui.modal>

{{-- Modal 3: Create Change Request Modal --}}
<x-ui.modal id="createCrModal" :title="__('projects.create_change_request')" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('projects.change-requests.store', $project) }}">
        @csrf
        <input type="hidden" name="_modal" value="createCrModal">

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.review_reference') }} ({{ __('projects.optional') }})</label>
                <x-ui.odoo-form-ui type="select" name="project_review_id">
                    <option value="">{{ __('projects.standalone_cr') }}</option>
                    @foreach ($reworkReviews as $rr)
                        <option value="{{ $rr->id }}" @selected(old('project_review_id') == $rr->id)>
                            Review #{{ $rr->id }} ({{ $rr->review_date?->format('Y-m-d') }} - {{ __('projects.review_statuses.Rework Required') }})
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.cr_title') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="input" name="title" :placeholder="__('projects.cr_title')" :value="old('title')" required />
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.description') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="textarea" name="description" rows="3" :placeholder="__('projects.description')" required>{{ old('description') }}</x-ui.odoo-form-ui>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.requested_by') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="select" name="requested_by" required>
                    @foreach ($activeMemberOptions as $memberUser)
                        <option value="{{ $memberUser->id }}" @selected(old('requested_by', auth()->id()) == $memberUser->id)>
                            {{ $memberUser->name }} ({{ $memberUser->email }})
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.impact_schedule_days') }}</label>
                <x-ui.odoo-form-ui type="input" inputType="number" name="impact_schedule_days" min="0" :value="old('impact_schedule_days', 0)" />
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.impact_budget_amount') }} ({{ active_currency_symbol() }})</label>
                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" min="0" name="impact_budget_amount" :value="old('impact_budget_amount', '0.00')" />
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.impact_budget_hours') }}</label>
                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" min="0" name="impact_budget_hours" :value="old('impact_budget_hours', '0.00')" />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="feather-check-circle me-1"></i>{{ __('projects.save_change_request') }}
            </button>
        </div>
    </form>
</x-ui.modal>

{{-- Modal 4: Reject Change Request Modal --}}
<x-ui.modal id="rejectCrModal" :title="__('projects.reject_change_request')" size="md" :showFooter="false">
    <form id="rejectCrForm" method="POST" action="">
        @csrf
        <input type="hidden" name="_modal" value="rejectCrModal">

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.rejection_remarks') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="textarea" name="rejection_remarks" rows="4" :placeholder="__('projects.rejection_remarks')" required>{{ old('rejection_remarks') }}</x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-danger">
                <i class="feather-x me-1"></i>{{ __('projects.reject') }}
            </button>
        </div>
    </form>
</x-ui.modal>

{{-- Modal 5: Upload Review Evidence Modal --}}
<x-ui.modal id="uploadReviewEvidenceModal" :title="__('projects.upload_review_evidence')" size="md" :showFooter="false">
    <form id="uploadReviewEvidenceForm" method="POST" action="{{ route('projects.documents.store', $project) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_document_form" value="upload">
        <input type="hidden" name="_modal" value="uploadReviewEvidenceModal">
        <input type="hidden" name="attachable_type" value="review">
        <input type="hidden" name="attachable_id" id="uploadEvidenceReviewId" value="">
        <input type="hidden" name="category" value="{{ \App\Domains\Projects\Models\ProjectDocument::CATEGORY_TEST_CASE }}">

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.file_name') }} ({{ __('projects.optional') }})</label>
                <x-ui.odoo-form-ui type="input" name="title" :placeholder="__('projects.file_name')" :value="old('title')" />
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.document') }} <span class="text-danger">*</span></label>
                <input type="file" name="file" class="form-control form-control-sm" required>
                <div class="form-text fs-11 text-muted">{{ __('projects.max_file_size_hint') }}</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.file_remarks') }}</label>
                <x-ui.odoo-form-ui type="textarea" name="remarks" rows="2" :placeholder="__('projects.file_remarks')">{{ old('remarks') }}</x-ui.odoo-form-ui>
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
<script>
    function openSignOffModal(reviewId) {
        var modalEl = document.getElementById('signOffReviewModal');
        if (!modalEl) return;
        var form = document.getElementById('signOffReviewForm');
        if (form) {
            form.action = "{{ route('projects.reviews.signoff', [$project, ':id']) }}".replace(':id', reviewId);
        }
        if (window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function openRejectCrModal(crId) {
        var modalEl = document.getElementById('rejectCrModal');
        if (!modalEl) return;
        var form = document.getElementById('rejectCrForm');
        if (form) {
            form.action = "{{ route('projects.change-requests.reject', [$project, ':id']) }}".replace(':id', crId);
        }
        if (window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function openEvidenceModal(reviewId) {
        var modalEl = document.getElementById('uploadReviewEvidenceModal');
        if (!modalEl) return;
        var reviewIdInput = document.getElementById('uploadEvidenceReviewId');
        if (reviewIdInput) {
            reviewIdInput.value = reviewId;
        }
        if (window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
</script>
@endpush
