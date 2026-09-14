@extends('layouts.duralux')

@section('title', 'Production Settings')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-settings me-2 text-primary"></i>Production & Subcontract Settings</h4>
            <p class="text-muted fs-12 mb-0">Configure tenant manufacturing policy, procurement automation, and financial threshold rules.</p>
        </div>
        <div>
            <a href="{{ route('production.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="feather-arrow-left me-1"></i> Dashboard</a>
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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark"><i class="feather-truck me-2 text-info"></i>Subcontract Procurement Automation Policy</h6>
                    <span class="badge bg-soft-primary text-primary font-monospace fs-11">Enterprise Mode</span>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('production.settings.subcontract.update') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark fs-13">Procurement Workflow Mode</label>
                            <p class="text-muted fs-12 mb-3">Controls how purchase requisitions and purchase orders are generated when a Manufacturing Order is released with external operations.</p>
                            
                            <div class="row g-3">
                                <!-- Manual PR -> PO Mode -->
                                <div class="col-md-12">
                                    <div class="form-check custom-option-card border rounded p-3 @if($currentWorkflow === 'manual_pr_po') border-primary bg-soft-primary-light @endif">
                                        <input class="form-check-input mt-1" type="radio" name="subcontract_procurement_workflow" id="mode_manual" value="manual_pr_po" @checked($currentWorkflow === 'manual_pr_po')>
                                        <label class="form-check-label ms-2 cursor-pointer w-100" for="mode_manual">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark fs-14">Manual PR → PO Workflow</span>
                                                <span class="badge bg-secondary text-white fs-10">Traditional</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                MO Release creates a Subcontract Purchase Requisition. Purchase team manually approves PR, converts to PO, and submits PO for approval before dispatch.
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Auto-Draft PO Mode (Recommended) -->
                                <div class="col-md-12">
                                    <div class="form-check custom-option-card border rounded p-3 @if($currentWorkflow === 'auto_draft_po') border-success bg-soft-success-light @endif">
                                        <input class="form-check-input mt-1" type="radio" name="subcontract_procurement_workflow" id="mode_auto_draft" value="auto_draft_po" @checked($currentWorkflow === 'auto_draft_po')>
                                        <label class="form-check-label ms-2 cursor-pointer w-100" for="mode_auto_draft">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark fs-14">Auto-Draft PO — <span class="text-success fw-bold">Recommended</span></span>
                                                <span class="badge bg-success text-white fs-10">1-Click Approval</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                MO Release skips PR creation and directly auto-generates a Draft Subcontract PO with routing cost snapshot. Purchase Manager approves PO in 1-click.
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Auto-Approved PO Mode -->
                                <div class="col-md-12">
                                    <div class="form-check custom-option-card border rounded p-3 @if($currentWorkflow === 'auto_approved_po') border-info bg-soft-info-light @endif">
                                        <input class="form-check-input mt-1" type="radio" name="subcontract_procurement_workflow" id="mode_auto_approved" value="auto_approved_po" @checked($currentWorkflow === 'auto_approved_po') x-data @change="$dispatch('mode-changed', 'auto_approved_po')">
                                        <label class="form-check-label ms-2 cursor-pointer w-100" for="mode_auto_approved">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-dark fs-14">Auto-Approved PO (Fully Automated)</span>
                                                <span class="badge bg-info text-white fs-10">Fast-Track</span>
                                            </div>
                                            <div class="text-muted fs-12 mt-1">
                                                MO Release directly generates an Approved PO ready for immediate Delivery Challan dispatch, provided total value does not exceed the financial threshold.
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Auto Approval Limit Field -->
                        <div class="mb-4 pt-3 border-top" id="limitContainer">
                            <label for="subcontract_auto_approval_limit" class="form-label fw-semibold text-dark fs-13">Subcontract Auto Approval Limit (₹ / Currency)</label>
                            <p class="text-muted fs-12 mb-2">If an auto-approved subcontract PO total cost exceeds this threshold, the system automatically falls back to <strong>Draft PO</strong> for manual purchase review.</p>
                            <div class="input-group input-group-sm" style="max-width: 300px;">
                                <span class="input-group-text bg-light fw-bold text-muted">₹</span>
                                <input type="number" step="0.01" min="0" name="subcontract_auto_approval_limit" id="subcontract_auto_approval_limit" class="form-control font-monospace" value="{{ old('subcontract_auto_approval_limit', $autoApprovalLimit) }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-2">
                            <button type="submit" class="btn btn-primary btn-sm px-4"><i class="feather-save me-1"></i> Save Subcontract Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark"><i class="feather-shield me-2 text-warning"></i>Audit & Compliance Safeguards</h6>
                </div>
                <div class="card-body p-3">
                    <ul class="list-unstyled mb-0 fs-12 text-muted space-y-3">
                        <li class="d-flex gap-2">
                            <i class="feather-check text-success fs-14 mt-1"></i>
                            <div><strong>Idempotency Enforced:</strong> Re-releasing MOs or operations will never duplicate PRs or POs.</div>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="feather-check text-success fs-14 mt-1"></i>
                            <div><strong>Service Stock Isolation:</strong> Subcontract GRNs advance WIP without polluting warehouse stock.</div>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="feather-check text-success fs-14 mt-1"></i>
                            <div><strong>Segregation of Duties:</strong> Machine operators cannot approve POs or reconcile vendor material.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
