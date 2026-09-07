@extends('layouts.duralux')

@section('title', 'ECO ' . $eco->eco_number . ' | SaaS ERP')
@section('page-title', 'ECO Details: ' . $eco->eco_number)
@section('breadcrumb', 'ECO Management')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('production.ecos.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="feather-arrow-left me-1"></i> Back to ECO List
        </a>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('warning'))
            <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" />
        @endif

        <x-ui.odoo-form-ui type="sheet">
            <!-- Header with Action Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold text-dark mb-0">{{ $eco->eco_number }}</h3>
                        @if($eco->status === 'draft')
                            <span class="badge bg-soft-secondary text-secondary">Draft</span>
                        @elseif($eco->status === 'under_review')
                            <span class="badge bg-soft-warning text-warning">Under Review</span>
                        @elseif($eco->status === 'approved')
                            <span class="badge bg-soft-primary text-primary">Approved</span>
                        @elseif($eco->status === 'released')
                            <span class="badge bg-soft-success text-success">Released</span>
                        @elseif($eco->status === 'rejected')
                            <span class="badge bg-soft-danger text-danger">Rejected</span>
                        @elseif($eco->status === 'closed')
                            <span class="badge bg-soft-dark text-dark">Closed</span>
                        @else
                            <span class="badge bg-soft-light text-dark">{{ $eco->status }}</span>
                        @endif
                    </div>
                    <p class="text-muted fs-13 mb-0">{{ $eco->title }} &bull; Target Product: <strong class="text-dark">{{ $eco->product ? $eco->product->name : 'N/A' }}</strong></p>
                </div>

                <div class="d-flex gap-2">
                    @if($eco->isDraft())
                        <form method="POST" action="{{ route('production.ecos.submit', $eco->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-warning">
                                <i class="feather-send me-1"></i> Submit for Review
                            </button>
                        </form>
                    @endif

                    @if($eco->isUnderReview())
                        <form method="POST" action="{{ route('production.ecos.approve', $eco->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="feather-check me-1"></i> Approve
                            </button>
                        </form>
                        <form method="POST" action="{{ route('production.ecos.reject', $eco->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="feather-x me-1"></i> Reject
                            </button>
                        </form>
                    @endif

                    @if($eco->isApproved())
                        <form method="POST" action="{{ route('production.ecos.release', $eco->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-play me-1"></i> Release Engineering Change
                            </button>
                        </form>
                    @endif

                    @if($eco->isReleased())
                        <form method="POST" action="{{ route('production.ecos.close', $eco->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-dark">
                                <i class="feather-slash me-1"></i> Close ECO
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Details Card -->
            <div class="row g-4 mb-4 fs-13 text-dark">
                <div class="col-md-3">
                    <span class="text-uppercase fs-11 fw-bold text-muted d-block">Change Type</span>
                    <span class="fw-bold text-dark">{{ $eco->change_type }}</span>
                </div>
                <div class="col-md-3">
                    <span class="text-uppercase fs-11 fw-bold text-muted d-block">Effective Date</span>
                    <span class="fw-bold text-dark">{{ $eco->effective_date ? $eco->effective_date->format('Y-m-d') : 'Immediate' }}</span>
                </div>
                <div class="col-md-3">
                    <span class="text-uppercase fs-11 fw-bold text-muted d-block">Created By</span>
                    <span class="fw-bold text-dark">{{ $eco->creator ? $eco->creator->name : 'System' }}</span>
                </div>
                <div class="col-md-3">
                    <span class="text-uppercase fs-11 fw-bold text-muted d-block">Approved By</span>
                    <span class="fw-bold text-dark">{{ $eco->approver ? $eco->approver->name : 'N/A' }}</span>
                </div>
                @if($eco->reason)
                    <div class="col-md-12">
                        <span class="text-uppercase fs-11 fw-bold text-muted d-block">Reason</span>
                        <p class="mb-0 text-dark">{{ $eco->reason }}</p>
                    </div>
                @endif
                @if($eco->description)
                    <div class="col-md-12">
                        <span class="text-uppercase fs-11 fw-bold text-muted d-block">Description</span>
                        <p class="mb-0 text-dark">{{ $eco->description }}</p>
                    </div>
                @endif
            </div>

            <!-- Impact Analysis Component Table -->
            @if(isset($impact))
                <h5 class="fw-bold text-dark mt-4 mb-3"><i class="feather-activity text-primary me-2"></i>Impact Analysis</h5>
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>Impact Domain</th>
                            <th>Active Standard / Existing State</th>
                            <th>Proposed Revision / New State</th>
                            <th>Impact Level & Assessment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold">BOM Revision</td>
                            <td>v{{ $impact['bom']['current_version'] ?? 'N/A' }} (Rev {{ $impact['bom']['current_revision'] ?? 0 }})</td>
                            <td>v{{ $impact['bom']['proposed_version'] ?? 'N/A' }} (Rev {{ $impact['bom']['proposed_revision'] ?? 0 }})</td>
                            <td>
                                @if(!empty($impact['bom']['has_change']))
                                    <span class="badge bg-soft-warning text-warning">Revision Changed</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">No Change</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Routing Revision</td>
                            <td>v{{ $impact['routing']['current_version'] ?? 'N/A' }} (Rev {{ $impact['routing']['current_revision'] ?? 0 }})</td>
                            <td>v{{ $impact['routing']['proposed_version'] ?? 'N/A' }} (Rev {{ $impact['routing']['proposed_revision'] ?? 0 }})</td>
                            <td>
                                @if(!empty($impact['routing']['has_change']))
                                    <span class="badge bg-soft-warning text-warning">Revision Changed</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">No Change</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold">WIP Impact</td>
                            <td colspan="2">Active Production Orders in WIP</td>
                            <td>
                                <span class="badge bg-soft-info text-info">{{ $impact['wip_orders_count'] ?? 0 }} Orders Affected</span>
                            </td>
                        </tr>
                    </tbody>
                </x-ui.odoo-form-ui>

                @if(!empty($impact['material_impact']['quantity_changed']) || !empty($impact['material_impact']['added']) || !empty($impact['material_impact']['removed']))
                    <h6 class="fw-bold text-dark mt-4 mb-2"><i class="feather-layers text-primary me-2"></i>BOM Component Changes Breakdown</h6>
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th>Component Product</th>
                                <th>Change Type</th>
                                <th class="text-end">Previous Qty</th>
                                <th class="text-end">Proposed Qty</th>
                                <th class="text-end">Difference (Delta)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($impact['material_impact']['quantity_changed'] as $qc)
                                <tr>
                                    <td class="fw-semibold">{{ $qc['material_name'] }}</td>
                                    <td><span class="badge bg-soft-warning text-warning">Quantity Modified</span></td>
                                    <td class="text-end">{{ number_format($qc['old_quantity'], 4) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($qc['new_quantity'], 4) }}</td>
                                    <td class="text-end {{ $qc['delta_quantity'] > 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                        {{ $qc['delta_quantity'] > 0 ? '+' : '' }}{{ number_format($qc['delta_quantity'], 4) }}
                                    </td>
                                </tr>
                            @endforeach
                            @foreach($impact['material_impact']['added'] as $add)
                                <tr>
                                    <td class="fw-semibold">{{ $add['material_name'] }}</td>
                                    <td><span class="badge bg-soft-success text-success">Added Component</span></td>
                                    <td class="text-end text-muted">-</td>
                                    <td class="text-end fw-bold">{{ number_format($add['quantity'], 4) }}</td>
                                    <td class="text-end text-success fw-bold">+{{ number_format($add['quantity'], 4) }}</td>
                                </tr>
                            @endforeach
                            @foreach($impact['material_impact']['removed'] as $rem)
                                <tr>
                                    <td class="fw-semibold">{{ $rem['material_name'] }}</td>
                                    <td><span class="badge bg-soft-danger text-danger">Removed Component</span></td>
                                    <td class="text-end">{{ number_format($rem['quantity'], 4) }}</td>
                                    <td class="text-end text-muted">-</td>
                                    <td class="text-end text-danger fw-bold">-{{ number_format($rem['quantity'], 4) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.odoo-form-ui>
                @endif
            @endif
        </x-ui.odoo-form-ui>
    </div>
@endsection
