@extends('layouts.duralux')

@section('title', __('crm.sales_settings') . ' | SaaS ERP')
@section('page-title', __('crm.sales_settings'))
@section('breadcrumb', __('ui.sales') . ' / ' . __('crm.settings'))

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-settings me-2 text-primary"></i>{{ __('crm.sales_settings') }}</h4>
            <p class="text-muted fs-12 mb-0">{{ __('crm.configure_invoicing_policy_subtext') }}</p>
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
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="feather-file-text me-2 text-primary"></i>{{ __('crm.invoicing_policy_workflow_mode') }}
                    </h6>
                    <span class="badge bg-soft-primary text-primary font-monospace fs-11">{{ __('ui.sales') }}</span>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('sales.settings.update-invoicing-policy') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-13">{{ __('crm.select_default_invoicing_policy') }}</label>
                            <p class="text-muted fs-12 mb-3">
                                {{ __('crm.controls_how_invoices_generated') }}
                            </p>

                            <div class="row g-3">
                                <!-- Option 1: Sales Order Only -->
                                <div class="col-md-12">
                                    <div class="form-check custom-option-card border rounded p-3 @if($invoicingPolicy === 'sales_order') border-primary bg-soft-primary-light @endif">
                                        <input class="form-check-input mt-1" type="radio" name="invoicing_policy" id="policy_so" value="sales_order" @checked($invoicingPolicy === 'sales_order')>
                                        <label class="form-check-label ms-2 cursor-pointer w-100" for="policy_so">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark fs-14">
                                                    <i class="feather-file-text me-1.5 text-primary"></i>{{ __('crm.against_sales_order_only') }}
                                                </span>
                                                <span class="badge bg-soft-primary text-primary fs-11">{{ __('crm.order_based_billing') }}</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                {{ __('crm.so_only_policy_desc') }}
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Option 2: Dispatch Order Only -->
                                <div class="col-md-12">
                                    <div class="form-check custom-option-card border rounded p-3 @if($invoicingPolicy === 'dispatch_order') border-info bg-soft-info-light @endif">
                                        <input class="form-check-input mt-1" type="radio" name="invoicing_policy" id="policy_do" value="dispatch_order" @checked($invoicingPolicy === 'dispatch_order')>
                                        <label class="form-check-label ms-2 cursor-pointer w-100" for="policy_do">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark fs-14">
                                                    <i class="feather-truck me-1.5 text-info"></i>{{ __('crm.against_dispatch_order_only') }}
                                                </span>
                                                <span class="badge bg-soft-info text-info fs-11">{{ __('crm.delivery_based_billing') }}</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                {{ __('crm.do_only_policy_desc') }}
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Option 3: Both Options Allowed (Flexible / Default) -->
                                <div class="col-md-12">
                                    <div class="form-check custom-option-card border rounded p-3 @if($invoicingPolicy === 'both') border-success bg-soft-success-light @endif">
                                        <input class="form-check-input mt-1" type="radio" name="invoicing_policy" id="policy_both" value="both" @checked($invoicingPolicy === 'both')>
                                        <label class="form-check-label ms-2 cursor-pointer w-100" for="policy_both">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark fs-14">
                                                    <i class="feather-layers me-1.5 text-success"></i>{{ __('crm.both_options_allowed_flexible') }}
                                                </span>
                                                <span class="badge bg-soft-success text-success fs-11">{{ __('crm.default_full_access') }}</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                {{ __('crm.both_policy_desc') }}
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-3 border-top">
                            <button type="submit" class="btn btn-primary fw-semibold px-4">
                                <i class="feather-save me-1.5"></i>{{ __('crm.save_settings') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="feather-info me-2 text-primary"></i>{{ __('crm.invoicing_policy_summary') }}</h6>
                    <p class="text-muted fs-12 mb-3">
                        {{ __('crm.choosing_invoicing_policy_intro') }}
                    </p>
                    <ul class="list-unstyled fs-12 text-muted mb-0">
                        <li class="mb-2 d-flex">
                            <i class="feather-chevron-right text-primary me-2 flex-shrink-0 mt-1"></i>
                            <div><strong>Against Sales Order:</strong> Invoices are raised upfront or directly from approved orders.</div>
                        </li>
                        <li class="mb-2 d-flex">
                            <i class="feather-chevron-right text-info me-2 flex-shrink-0 mt-1"></i>
                            <div><strong>Against Dispatch:</strong> Invoices strictly require delivered goods / dispatch order.</div>
                        </li>
                        <li class="d-flex">
                            <i class="feather-chevron-right text-success me-2 flex-shrink-0 mt-1"></i>
                            <div><strong>Both Options:</strong> Gives teams maximum flexibility on each billing step.</div>
                        </li>
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
.bg-soft-info-light {
    background-color: rgba(14, 165, 233, 0.04);
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
