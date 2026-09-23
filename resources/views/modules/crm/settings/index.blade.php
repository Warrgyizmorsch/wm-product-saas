@extends('layouts.duralux')

@section('title', __('crm.crm_settings') . ' | SaaS ERP')
@section('page-title', __('crm.crm_settings'))
@section('breadcrumb', __('crm.revenue_cycle') . ' / CRM / ' . __('crm.settings'))

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-settings me-2 text-primary"></i>{{ __('crm.crm_settings') }}</h4>
            <p class="text-muted fs-12 mb-0">{{ __('crm.configure_quotation_approval_subtext') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Quotation Approval Policy Card -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="feather-check-square me-2 text-primary"></i>{{ __('crm.quotation_approval_policy') }}
                    </h6>
                    <span class="badge bg-soft-info text-info font-monospace fs-11">{{ __('crm.approval_automation') }}</span>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('crm.settings.update-quotation-approval-policy') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-13">{{ __('crm.select_quotation_approval_mode') }}</label>
                            <p class="text-muted fs-12 mb-3">
                                {{ __('crm.controls_quotation_approval_desc') }}
                            </p>

                            <div class="row g-3">
                                <!-- Option 1: Approval Required (Standard Approval Workflow) -->
                                <div class="col-md-12">
                                    <div class="form-check custom-option-card border rounded p-3 @if(($quotationApprovalPolicy ?? 'approval_required') === 'approval_required') border-primary bg-soft-primary-light @endif">
                                        <input class="form-check-input mt-1" type="radio" name="quotation_approval_policy" id="policy_approval_req" value="approval_required" @checked(($quotationApprovalPolicy ?? 'approval_required') === 'approval_required')>
                                        <label class="form-check-label ms-2 cursor-pointer w-100" for="policy_approval_req">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark fs-14">
                                                    <i class="feather-shield me-1.5 text-primary"></i>{{ __('crm.require_approval_standard') }}
                                                </span>
                                                <span class="badge bg-soft-primary text-primary fs-11">{{ __('crm.multi_stage_approval') }}</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                {{ __('crm.require_approval_desc') }}
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Option 2: Auto-Approve (Direct Approval) -->
                                <div class="col-md-12">
                                    <div class="form-check custom-option-card border rounded p-3 @if(($quotationApprovalPolicy ?? 'approval_required') === 'auto_approve') border-success bg-soft-success-light @endif">
                                        <input class="form-check-input mt-1" type="radio" name="quotation_approval_policy" id="policy_auto_approve" value="auto_approve" @checked(($quotationApprovalPolicy ?? 'approval_required') === 'auto_approve')>
                                        <label class="form-check-label ms-2 cursor-pointer w-100" for="policy_auto_approve">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark fs-14">
                                                    <i class="feather-check-circle me-1.5 text-success"></i>{{ __('crm.auto_approve_direct') }}
                                                </span>
                                                <span class="badge bg-soft-success text-success fs-11">{{ __('crm.fast_track_mode') }}</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                {{ __('crm.auto_approve_desc') }}
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-3 border-top">
                            <button type="submit" class="btn btn-primary fw-semibold px-4">
                                <i class="feather-save me-1.5"></i>{{ __('crm.save_approval_policy') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="feather-help-circle me-2 text-info"></i>{{ __('crm.approval_policy_summary') }}</h6>
                    <p class="text-muted fs-12 leading-relaxed mb-3">
                        {{ __('crm.configure_approval_intro') }}
                    </p>
                    <ul class="text-muted fs-12 ps-3 mb-0">
                        <li class="mb-2"><strong>{{ __('crm.standard_approval_colon') }}</strong> {{ __('crm.standard_approval_summary_desc') }}</li>
                        <li><strong>{{ __('crm.auto_approve_colon') }}</strong> {{ __('crm.auto_approve_summary_desc') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.custom-option-card {
    transition: all 0.2s ease-in-out;
    cursor: pointer;
}
.custom-option-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transform: translateY(-1px);
}
.bg-soft-primary-light {
    background-color: rgba(37, 99, 235, 0.04);
}
.bg-soft-success-light {
    background-color: rgba(22, 163, 74, 0.04);
}
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('.custom-option-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (e.target.tagName !== 'INPUT') {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        }
    });
});
</script>
@endpush
