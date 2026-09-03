@extends('layouts.duralux')

@section('title', 'CRM & Sales Settings | SaaS ERP')
@section('page-title', 'CRM & Sales Settings')
@section('breadcrumb', 'Revenue Cycle / CRM / Settings')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-settings me-2 text-primary"></i>CRM & Sales Settings</h4>
            <p class="text-muted fs-12 mb-0">Configure invoicing policy and workflow automation rules for Revenue Cycle.</p>
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
                        <i class="feather-file-text me-2 text-primary"></i>Invoicing Policy & Workflow Mode
                    </h6>
                    <span class="badge bg-soft-primary text-primary font-monospace fs-11">Revenue Cycle Setting</span>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('crm.settings.update-invoicing-policy') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-13">Select Default Invoicing Policy</label>
                            <p class="text-muted fs-12 mb-3">
                                Controls how customer invoices are generated in the system and configures option visibility on the Generate Invoice screen.
                            </p>

                            <div class="row g-3">
                                <!-- Option 1: Sales Order Only -->
                                <div class="col-md-12">
                                    <div class="form-check custom-option-card border rounded p-3 @if($invoicingPolicy === 'sales_order') border-primary bg-soft-primary-light @endif">
                                        <input class="form-check-input mt-1" type="radio" name="invoicing_policy" id="policy_so" value="sales_order" @checked($invoicingPolicy === 'sales_order')>
                                        <label class="form-check-label ms-2 cursor-pointer w-100" for="policy_so">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark fs-14">
                                                    <i class="feather-file-text me-1.5 text-primary"></i>Against Sales Order Only
                                                </span>
                                                <span class="badge bg-soft-primary text-primary fs-11">Order-Based Billing</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                Invoices are generated directly against Sales Orders. Hides the top 3-mode selection radio bar on the Generate Invoice screen and defaults strictly to Sales Order mode.
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
                                                    <i class="feather-truck me-1.5 text-info"></i>Against Dispatch Order Only
                                                </span>
                                                <span class="badge bg-soft-info text-info fs-11">Delivery-Based Billing</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                Invoices are generated strictly against Dispatch / Delivery Orders. Hides the top 3-mode selection radio bar on the Generate Invoice screen and defaults to Dispatch Order mode.
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
                                                    <i class="feather-layers me-1.5 text-success"></i>Both Options Allowed (Flexible Mode)
                                                </span>
                                                <span class="badge bg-soft-success text-success fs-11">Default Full Access</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                Allows full flexibility. Displays the 3-mode selection radio bar (Against Sales Order, Against Dispatch Order, Standalone Direct Invoice) on top of the Generate Invoice screen for user selection.
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-3 border-top">
                            <button type="submit" class="btn btn-primary fw-semibold px-4">
                                <i class="feather-save me-1.5"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="feather-info me-2 text-primary"></i>Invoicing Policy Summary</h6>
                    <p class="text-muted fs-12 leading-relaxed mb-3">
                        Choosing an Invoicing Policy helps streamline your billing process according to your enterprise workflow:
                    </p>
                    <ul class="text-muted fs-12 ps-3 mb-0">
                        <li class="mb-2"><strong>Order-Based:</strong> Best for prepayments, advance billing, or services.</li>
                        <li class="mb-2"><strong>Delivery-Based:</strong> Best for physical goods manufacturing & trading where billing depends on actual dispatched quantities.</li>
                        <li><strong>Flexible Mode:</strong> Recommended if your business uses a mix of both workflows.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
