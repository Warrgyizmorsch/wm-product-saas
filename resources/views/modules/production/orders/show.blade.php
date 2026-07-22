@extends('layouts.duralux')

@section('title', 'Production Order ' . $order->order_number . ' | SaaS ERP')

@push('styles')
    <style>
        .production-sidebar-sticky {
            position: sticky;
            top: 85px;
            align-self: flex-start;
            max-height: calc(100vh - 260px);
            min-height: 480px;
            overflow-y: auto;
        }
        .production-main-content-scroll {
            max-height: calc(100vh - 260px);
            min-height: 480px;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 8px;
            padding-top: 10px;
            background-color: #F8FAFC;
        }
        .production-main-content-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .production-main-content-scroll::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.15);
            border-radius: 4px;
        }
        .production-main-content-scroll::-webkit-scrollbar-thumb:hover {
            background-color: rgba(0, 0, 0, 0.3);
        }
        .erp-vertical-tabs .nav-link {
            font-size: 11.5px !important;
            padding: 7.5px 10px !important;
            letter-spacing: 0.15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .erp-vertical-tabs .nav-link i {
            font-size: 13px !important;
            margin-right: 7px !important;
            flex-shrink: 0;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }
        @media (max-width: 767.98px) {
            .production-sidebar-sticky {
                display: none !important;
            }
            .production-main-content-scroll {
                max-height: none !important;
                min-height: auto !important;
                overflow-y: visible !important;
                padding-right: 0 !important;
            }
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        {{-- Back to List Button --}}
        <x-ui.icon-btn href="{{ route('production.orders.index') }}" icon="feather-arrow-left" variant="transparent-dark" title="{{ __('production.back_to_list') }}">
            {{ __('production.back_to_list') }}
        </x-ui.icon-btn>

        {{-- Grouped Header Actions Dropdown --}}
        <x-ui.action-dropdown id="headerActionsDropdown">
            @if($order->isDraft())
                <li>
                    <a href="{{ route('production.orders.edit', $order->id) }}" class="dropdown-item py-1.5 fs-12">
                        <i class="feather-edit me-2 text-primary fs-12"></i>{{ __('production.edit_order') }}
                    </a>
                </li>
                <li>
                    <form method="POST" action="{{ route('production.orders.release', $order->id) }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-success py-1.5 fs-12">
                            <i class="feather-play-circle me-2 text-success fs-12"></i>{{ __('production.release_order') }}
                        </button>
                    </form>
                </li>
                <li>
                    <form method="POST" action="{{ route('production.orders.destroy', $order->id) }}" onsubmit="return confirm('{{ __('production.confirm_delete_draft') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger py-1.5 fs-12">
                            <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_order') }}
                        </button>
                    </form>
                </li>
            @endif

            @if($order->isReleased() || $order->isInProgress())
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal" data-bs-target="#progressModal">
                        <i class="feather-edit-3 me-2 text-primary fs-12"></i>{{ __('production.log_progress') }}
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal" data-bs-target="#issueModal">
                        <i class="feather-log-in me-2 text-info fs-12"></i>{{ __('production.issue_materials') }}
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal" data-bs-target="#returnModal">
                        <i class="feather-log-out me-2 text-secondary fs-12"></i>{{ __('production.return_materials') }}
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal" data-bs-target="#receiptModal">
                        <i class="feather-download me-2 text-warning fs-12"></i>{{ __('production.receive_fg') }}
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal" data-bs-target="#scrapReworkModal">
                        <i class="feather-alert-triangle me-2 text-danger fs-12"></i>{{ __('production.log_scrap_rework') }}
                    </a>
                </li>
                <li>
                    <form method="POST" action="{{ route('production.orders.complete', $order->id) }}" onsubmit="return confirm('{{ __('production.confirm_complete_order') }}');">
                        @csrf
                        <button type="submit" class="dropdown-item text-success py-1.5 fs-12">
                            <i class="feather-check-circle me-2 text-success fs-12"></i>{{ __('production.complete_order') }}
                        </button>
                    </form>
                </li>
                <li>
                    <form method="POST" action="{{ route('production.orders.cancel', $order->id) }}" onsubmit="return confirm('{{ __('production.confirm_cancel_order') }}');">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger py-1.5 fs-12">
                            <i class="feather-slash me-2 text-danger fs-12"></i>{{ __('production.cancel_order') }}
                        </button>
                    </form>
                </li>
            @endif

            @if($order->isCompleted())
                <li>
                    <form method="POST" action="{{ route('production.orders.close', $order->id) }}" onsubmit="return confirm('{{ __('production.confirm_close_order') }}');">
                        @csrf
                        <button type="submit" class="dropdown-item text-secondary py-1.5 fs-12">
                            <i class="feather-archive me-2 text-secondary fs-12"></i>{{ __('production.close_archive_order') }}
                        </button>
                    </form>
                </li>
            @endif
        </x-ui.action-dropdown>
    </div>
@endsection


@section('content')
<div class="erp-single-panel bg-white">

    @if(session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if(session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    {{-- ── Header Identity Row ──────────────────────────────────────────── --}}
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <h4 class="fw-bold text-dark mb-0">{{ __('production.production_order') }} ({{ $order->order_number }})</h4>
        <div>
            @if($order->isDraft())
                <span class="erp-badge-draft">{{ __('production.draft') }}</span>
            @elseif($order->isReleased())
                <span class="erp-badge-pending">{{ __('production.released') }}</span>
            @elseif($order->isInProgress())
                <span class="badge bg-soft-info text-info">{{ __('production.in_progress') }}</span>
            @elseif($order->isCompleted())
                <span class="erp-badge-active">{{ __('production.completed') }}</span>
            @elseif($order->isClosed())
                <span class="badge bg-soft-dark text-dark">{{ __('production.closed') }}</span>
            @elseif($order->isCancelled())
                <span class="badge bg-soft-danger text-danger">{{ __('production.cancelled') }}</span>
            @endif
        </div>
    </div>

    {{-- ── Identity / KPI Grid ──────────────────────────────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 border-end">
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.finished_product') }}:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $order->product->name }} ({{ $order->product->sku }})</span></div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.bom_reference') }}:</span></div>
                <div class="col-md-8">
                    <a href="{{ route('production.boms.show', $order->bom_id ?? 0) }}" class="fw-bold text-primary fs-13">
                        {{ $order->bom->bom_number ?? 'N/A' }} (v{{ $order->bom->version ?? '—' }})
                    </a>
                </div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.routing_reference') }}:</span></div>
                <div class="col-md-8">
                    <a href="{{ route('production.routing.show', $order->routing_id ?? 0) }}" class="fw-bold text-primary fs-13">
                        {{ $order->routing->routing_number ?? 'N/A' }} — {{ $order->routing->name ?? '' }} (v{{ $order->routing->version ?? '—' }})
                    </a>
                </div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.source_plan') }}:</span></div>
                <div class="col-md-8">
                    @if($order->plan)
                        <a href="{{ route('production.plans.show', $order->production_plan_id) }}" class="fw-bold text-primary fs-13">
                            {{ $order->plan->plan_number }}
                        </a>
                    @else
                        <span class="text-dark fw-bold fs-13">{{ __('production.direct_order_no_plan') }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.quantity_ordered') }}:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ number_format($order->quantity_ordered, 2) }} units</span></div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.quantity_produced') }}:</span></div>
                <div class="col-md-8">
                    @php $progressPct = $order->quantity_ordered > 0 ? min(100.0, ($order->quantity_produced / $order->quantity_ordered) * 100) : 0.0; @endphp
                    <span class="text-success fw-bold fs-13">{{ number_format($order->quantity_produced, 2) }} units</span>
                    <div class="progress mt-1" style="height:5px;">
                        <div class="progress-bar bg-success" style="width:{{ $progressPct }}%;"></div>
                    </div>
                    <div class="text-muted fs-11 mt-1">{{ round($progressPct, 1) }}% {{ __('production.completed') }}</div>
                </div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.scheduled_dates') }}:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $order->start_date->format('Y-m-d') }} → {{ $order->end_date->format('Y-m-d') }}</span></div>
            </div>
            <div class="row erp-form-row mb-2">
                <div class="col-md-4"><span class="fw-semibold text-muted fs-13">{{ __('production.created_by') }}:</span></div>
                <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $order->creator->name ?? 'System' }} at {{ $order->created_at->format('Y-m-d H:i') }}</span></div>
            </div>
        </div>
    </div>

    {{-- ── 2-Column Vertical Tabs Layout ────────────────────────────────── --}}
    @php
        $activeTab = request('tab', request('active_tab', 'vtab-overview'));
        if (request()->has('adjustments_page') && !request()->has('tab')) {
            $activeTab = 'vtab-cost-adjustments';
        }

        $verticalTabs = [
            ['id' => 'vtab-overview',         'label' => __('production.overview'),         'active' => $activeTab === 'vtab-overview',         'icon' => 'feather-activity'],
            ['id' => 'vtab-operations',       'label' => __('production.operations_routing'),'active' => $activeTab === 'vtab-operations',       'icon' => 'feather-cpu'],
            ['id' => 'vtab-wip',              'label' => __('production.wip_tracking'),     'active' => $activeTab === 'vtab-wip',              'icon' => 'feather-layers'],
            ['id' => 'vtab-reservations',     'label' => __('production.material_reservations'), 'active' => $activeTab === 'vtab-reservations', 'icon' => 'feather-archive'],
            ['id' => 'vtab-issues',           'label' => __('production.material_issues'),  'active' => $activeTab === 'vtab-issues',           'icon' => 'feather-arrow-up-right'],
            ['id' => 'vtab-progress',         'label' => __('production.progress_logs'),    'active' => $activeTab === 'vtab-progress',         'icon' => 'feather-clock'],
            ['id' => 'vtab-scrap',            'label' => __('production.scrap_rework'),     'active' => $activeTab === 'vtab-scrap',            'icon' => 'feather-alert-triangle'],
            ['id' => 'vtab-cost',             'label' => __('production.cost_analysis'),    'active' => $activeTab === 'vtab-cost',             'icon' => 'feather-pie-chart'],
            ['id' => 'vtab-cost-adjustments', 'label' => __('production.cost_adjustments'), 'active' => $activeTab === 'vtab-cost-adjustments', 'icon' => 'feather-dollar-sign'],
            ['id' => 'vtab-procurement',      'label' => __('production.procurement_requisitions'), 'active' => $activeTab === 'vtab-procurement', 'icon' => 'feather-shopping-cart'],
            ['id' => 'vtab-audit',            'label' => __('production.audit_trail_events'), 'active' => $activeTab === 'vtab-audit',        'icon' => 'feather-file-text'],
        ];
    @endphp

    <div class="row mt-4">
        {{-- Left Vertical Navigation Sidebar Column (Desktop & Tablet) --}}
        <div class="col-md-3 col-lg-2 border-end pe-md-3 mb-4 mb-md-0 production-sidebar-sticky d-none d-md-block">
            <x-ui.vertical-tabs id="productionOrderVerticalTabs" :tabs="$verticalTabs" />
        </div>

        {{-- Right Content Area Column --}}
        <div class="col-md-9 col-lg-10 ps-md-2 production-main-content-scroll">

            {{-- Top Horizontal Navigation Bar (Mobile Screens Only) --}}
            <div class="d-block d-md-none mb-3 bg-white p-2 rounded border">
                <x-ui.horizontal-tabs id="mobileProductionOrderTabs" :tabs="$verticalTabs" />
            </div>
            <div class="tab-content" style="background-color: white; padding: 10px;" id="productionOrderVerticalTabsContent">

                {{-- Tab 1: Overview --}}
                <div class="tab-pane fade {{ $activeTab === 'vtab-overview' ? 'show active' : '' }}" id="vtab-overview" role="tabpanel" aria-labelledby="vtab-overview-tab">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <h5 class="fw-bold text-dark mb-3">{{ __('production.remarks_notes') }}</h5>
                            <p class="text-dark fs-13">{{ $order->description ?? __('production.no_remarks_logged') }}</p>

                            <h6 class="fw-bold text-muted text-uppercase fs-11 mb-3 mt-4">{{ __('production.actual_execution_timeline') }}</h6>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded">
                                        <div class="text-muted fs-11 text-uppercase mb-1">{{ __('production.scheduled_window') }}</div>
                                        <div class="text-dark fw-bold fs-14">
                                            {{ $order->start_date->format('Y-m-d') }} → {{ $order->end_date->format('Y-m-d') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-3 rounded">
                                        <div class="text-muted fs-11 text-uppercase mb-1">{{ __('production.actual_execution_dates') }}</div>
                                        <div class="text-dark fw-bold fs-14">
                                            {{ $order->actual_start_date ? $order->actual_start_date->format('Y-m-d H:i') : '—' }}
                                            →
                                            {{ $order->actual_end_date ? $order->actual_end_date->format('Y-m-d H:i') : '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <h5 class="fw-bold text-dark mb-3">{{ __('production.frozen_engineering_references') }}</h5>
                            <div class="mb-3 pb-2 border-bottom">
                                <div class="text-muted fs-11 text-uppercase mb-1">{{ __('production.bom_version_frozen') }}</div>
                                <a href="{{ route('production.boms.show', $order->bom_id ?? 0) }}" class="fw-bold text-primary">
                                    {{ $order->bom->bom_number ?? __('production.bom_reference') }} (v{{ $order->bom->version ?? '1.0' }})
                                </a>
                                <div class="fs-12 text-muted mt-1">{{ $order->bom->bom_name ?? 'Default BOM' }}</div>
                            </div>
                            <div class="mb-3 pb-2 border-bottom">
                                <div class="text-muted fs-11 text-uppercase mb-1">{{ __('production.routing_version_frozen') }}</div>
                                <a href="{{ route('production.routing.show', $order->routing_id ?? 0) }}" class="fw-bold text-primary">
                                    {{ $order->routing->routing_number ?? __('production.routing_reference') }}
                                </a>
                                <div class="fs-12 text-muted mt-1">{{ $order->routing->name ?? 'Default Routing' }} (v{{ $order->routing->version ?? '1.0' }})</div>
                            </div>
                            <div>
                                <div class="text-muted fs-11 text-uppercase mb-1">{{ __('production.source_plan') }}</div>
                                @if($order->plan)
                                    <a href="{{ route('production.plans.show', $order->production_plan_id) }}" class="fw-bold text-primary">
                                        {{ $order->plan->plan_number }}
                                    </a>
                                @else
                                    <span class="text-dark fw-bold">{{ __('production.direct_order_no_plan') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

        {{-- Tab 3: WIP Tracking --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-wip' ? 'show active' : '' }}" id="vtab-wip" role="tabpanel" aria-labelledby="vtab-wip-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="feather-activity text-primary me-2"></i> {{ __('production.wip_tracking_status') }}</h5>
                <span class="fs-12 text-muted">{{ __('production.wip_tracking_desc') }}</span>
            </div>

            @if($order->wips->isNotEmpty())
                @foreach($order->wips as $wip)
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="card shadow-sm border-0 bg-light p-3">
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">{{ __('production.wip_reference') }}: WIP-#{{ str_pad($wip->id, 5, '0', STR_PAD_LEFT) }}</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">{{ __('production.current_stage') }}</small>
                                        <span class="fw-bold text-dark">{{ $wip->currentRoutingOperation ? $wip->currentRoutingOperation->name : 'N/A' }}</span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">{{ __('production.work_center') }}</small>
                                        <span class="fw-bold text-dark">{{ $wip->currentWorkCenter ? $wip->currentWorkCenter->name : 'N/A' }}</span>
                                    </div>
                                    <div class="col-6 border-top pt-2">
                                        <small class="text-muted d-block">{{ __('production.available_qty') }}</small>
                                        <span class="fw-bold fs-15 text-dark">{{ number_format($wip->available_quantity, 2) }}</span>
                                    </div>
                                    <div class="col-6 border-top pt-2">
                                        <small class="text-muted d-block">{{ __('production.quantity_produced') }}</small>
                                        <span class="fw-bold fs-15 text-success">{{ number_format($wip->completed_quantity, 2) }}</span>
                                    </div>
                                    <div class="col-6 border-top pt-2">
                                        <small class="text-muted d-block">{{ __('production.rejected_rework') }} / {{ __('production.scrapped_qty') }}</small>
                                        <span class="fw-semibold text-danger">{{ number_format($wip->rejected_quantity, 2) }} / {{ number_format($wip->scrap_quantity, 2) }}</span>
                                    </div>
                                    <div class="col-6 border-top pt-2">
                                        <small class="text-muted d-block">{{ __('production.status') }}</small>
                                        @if($wip->status === 'active')
                                            <span class="badge bg-soft-success text-success text-uppercase">{{ __('production.active') ?? 'Active' }}</span>
                                        @elseif($wip->status === 'quality_hold')
                                            <span class="badge bg-soft-warning text-warning text-uppercase">Quality Hold</span>
                                        @elseif($wip->status === 'rework')
                                            <span class="badge bg-soft-danger text-danger text-uppercase">Rework</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary text-uppercase">{{ __('production.completed') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="{{ route('production.wip.show', $wip->id) }}" class="btn btn-sm btn-primary w-100">
                                        <i class="feather-external-link me-1"></i> {{ __('production.open_wip_card') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card shadow-sm border-0 bg-light p-3">
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">{{ __('production.wip_costing_sheet') }}</h6>
                                <table class="w-100 fs-13">
                                    <tr class="border-bottom py-1">
                                        <td class="text-muted py-2">{{ __('production.material_costs') }}</td>
                                        <td class="text-end fw-semibold text-dark">{{ format_currency($wip->material_cost) }}</td>
                                    </tr>
                                    <tr class="border-bottom py-1">
                                        <td class="text-muted py-2">{{ __('production.labor_cost') }}</td>
                                        <td class="text-end fw-semibold text-dark">{{ format_currency($wip->labor_cost) }}</td>
                                    </tr>
                                    <tr class="border-bottom py-1">
                                        <td class="text-muted py-2">{{ __('production.machine_utilization_cost') }}</td>
                                        <td class="text-end fw-semibold text-dark">{{ format_currency($wip->machine_cost) }}</td>
                                    </tr>
                                    <tr class="border-bottom py-1">
                                        <td class="text-muted py-2">{{ __('production.work_center_overhead') }}</td>
                                        <td class="text-end fw-semibold text-dark">{{ format_currency($wip->overhead_cost) }}</td>
                                    </tr>
                                    <tr class="py-1">
                                        <td class="fw-bold text-primary pt-2">{{ __('production.total_cost') }}</td>
                                        <td class="text-end fw-bold text-primary pt-2 fs-15">{{ format_currency($wip->total_value) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <h6 class="fw-bold text-dark mb-2">{{ __('production.stage_transaction_log') }}</h6>
                            <div class="table-responsive">
                                <table class="erp-thin-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('production.date') }}</th>
                                            <th>{{ __('production.type') }}</th>
                                            <th>{{ __('production.from_stage') }}</th>
                                            <th>{{ __('production.to_stage') }}</th>
                                            <th class="text-end">{{ __('production.quantity') }}</th>
                                            <th class="text-end">{{ __('production.cost_added') }}</th>
                                            <th>{{ __('production.remarks') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($wip->transactions as $tx)
                                            <tr>
                                                <td class="font-monospace text-muted">{{ $tx->transaction_at->format('Y-m-d H:i') }}</td>
                                                <td><span class="badge bg-soft-secondary text-secondary text-uppercase">{{ str_replace('_', ' ', $tx->transaction_type) }}</span></td>
                                                <td>{{ $tx->fromOperation ? $tx->fromOperation->name : '—' }}</td>
                                                <td>{{ $tx->toOperation ? $tx->toOperation->name : '—' }}</td>
                                                <td class="text-end fw-semibold">{{ number_format($tx->quantity, 2) }}</td>
                                                <td class="text-end text-success fw-semibold">{{ $tx->cost_added > 0 ? '+' . format_currency($tx->cost_added) : '—' }}</td>
                                                <td class="text-muted">{{ $tx->remarks }}</td>
                                            </tr>

                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-3">{{ __('production.no_daily_cost_history') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center py-5 text-muted bg-light rounded">
                    <i class="feather-alert-circle fs-24 mb-2 d-block text-warning"></i>
                    {{ __('production.no_wip_active') }}
                </div>
            @endif
        </div>

        {{-- Tab 2: Operations --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-operations' ? 'show active' : '' }}" id="vtab-operations" role="tabpanel" aria-labelledby="vtab-operations-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">{{ __('production.routing_ops_title') }}</h5>
                <span class="fs-12 text-muted">{{ __('production.ops_sequential_note') }}</span>
            </div>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:5%" class="text-center">{{ __('production.seq') }}</th>
                            <th style="width:20%">{{ __('production.operation') }}</th>
                            <th style="width:15%">{{ __('production.work_center') }}</th>
                            <th style="width:12%">{{ __('production.machine') }}</th>
                            <th style="width:12%" class="text-center">{{ __('production.planned_setup_run') }}</th>
                            <th style="width:12%" class="text-center">{{ __('production.actual_setup_run') }}</th>
                            <th style="width:12%" class="text-center">{{ __('production.produced_scrap') }}</th>
                            <th style="width:7%">{{ __('production.status') }}</th>
                            <th style="width:5%" class="text-end">{{ __('production.log') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->operations as $op)
                            <tr>
                                <td class="text-center fw-semibold text-muted">#{{ $op->sequence }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $op->operation_number }}</div>
                                    <small class="text-muted">{{ $op->name }}</small>
                                </td>
                                <td>{{ $op->workCenter->name }}</td>
                                <td>{{ $op->machine->name ?? 'Any' }}</td>
                                <td class="text-center text-muted">{{ $op->setup_time_planned }}m / {{ $op->processing_time_planned }}m</td>
                                <td class="text-center fw-semibold text-dark">{{ $op->setup_time_actual }}m / {{ $op->processing_time_actual }}m</td>
                                <td class="text-center">
                                    <span class="text-success fw-bold">{{ number_format($op->quantity_produced, 2) }}</span>
                                    /
                                    <span class="text-danger">{{ number_format($op->quantity_scrapped + $op->quantity_rejected, 2) }}</span>
                                </td>
                                <td>
                                    @if($op->status === 'waiting')
                                        <span class="badge bg-secondary text-white">Waiting</span>
                                    @elseif($op->status === 'ready')
                                        <span class="badge bg-primary text-white">Ready</span>
                                    @elseif($op->status === 'running')
                                        <span class="badge bg-info text-white">Running</span>
                                    @elseif($op->status === 'paused')
                                        <span class="badge bg-warning text-dark">Paused</span>
                                    @elseif($op->status === 'completed')
                                        <span class="badge bg-success text-white">{{ __('production.completed') }}</span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ $op->status }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(($order->isReleased() || $order->isInProgress()) && $op->status !== 'completed')
                                        <x-ui.action-dropdown id="opActionDropdown{{ $op->id }}">
                                            <li>
                                                <a class="dropdown-item py-1.5 fs-12" href="javascript:void(0)"
                                                   data-bs-toggle="modal" data-bs-target="#progressModal"
                                                   onclick="var selectEl = document.getElementById('op_select_id'); if (selectEl) { selectEl.value = '{{ $op->id }}'; selectEl.dispatchEvent(new Event('change')); if (window.jQuery && jQuery().select2) { $(selectEl).trigger('change'); } }">
                                                    <i class="feather-edit-3 me-2 text-primary fs-12"></i>{{ __('production.log_execution_progress') }}
                                                </a>
                                            </li>
                                        </x-ui.action-dropdown>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 4: Reservations --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-reservations' ? 'show active' : '' }}" id="vtab-reservations" role="tabpanel" aria-labelledby="vtab-reservations-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">{{ __('production.component_reservations') }}</h5>
                @if(($order->isReleased() || $order->isInProgress()) && !$order->isCompleted() && !$order->isClosed() && !$order->isCancelled())
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#requestAdditionalMaterialModal">
                        <i class="feather-plus-circle me-1"></i> {{ __('production.request_additional_material') }}
                    </button>
                @endif
            </div>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:30%">{{ __('production.material_component') }}</th>
                            <th style="width:14%">{{ __('production.warehouse') }}</th>
                            <th style="width:15%" class="text-center">{{ __('production.planned_qty') }}</th>
                            <th style="width:15%" class="text-center">{{ __('production.reserved_qty') }}</th>
                            <th style="width:15%" class="text-center">{{ __('production.issued_qty') }}</th>
                            <th style="width:10%">UOM</th>
                            <th style="width:15%" class="text-end">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->reservations as $res)
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">{{ $res->product->name }}</div>
                                    <small class="text-muted font-monospace fs-10">{{ $res->product->sku }}</small>
                                </td>
                                <td class="text-muted">{{ $res->warehouse?->name ?? __('production.not_reserved') }}</td>
                                <td class="text-center fw-semibold text-dark">{{ number_format($res->quantity_planned, 2) }}</td>
                                <td class="text-center fw-bold" style="color: var(--bs-info);">{{ number_format($res->quantity_reserved, 2) }}</td>
                                <td class="text-center fw-bold text-success">{{ number_format($res->quantity_issued, 2) }}</td>
                                <td>{{ $res->uom->name }}</td>
                                <td class="text-end">
                                    @if($order->isReleased() || $order->isInProgress())
                                        <x-ui.action-dropdown id="resActionDropdown{{ $res->id }}">
                                            <li>
                                                <a class="dropdown-item py-1.5 fs-12" href="javascript:void(0)"
                                                   data-bs-toggle="modal" data-bs-target="#issueModal"
                                                   onclick="document.getElementById('issue_reservation_id').value = '{{ $res->id }}';">
                                                    <i class="feather-log-in me-2 text-info fs-12"></i>{{ __('production.issue_materials') }}
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-1.5 fs-12" href="javascript:void(0)"
                                                   data-bs-toggle="modal" data-bs-target="#returnModal"
                                                   onclick="document.getElementById('return_reservation_id').value = '{{ $res->id }}';">
                                                    <i class="feather-log-out me-2 text-secondary fs-12"></i>{{ __('production.return_materials') }}
                                                </a>
                                            </li>
                                        </x-ui.action-dropdown>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 5: Material Issues --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-issues' ? 'show active' : '' }}" id="vtab-issues" role="tabpanel" aria-labelledby="vtab-issues-tab">
            <h5 class="fw-bold text-dark mb-3">{{ __('production.material_issues_log') }}</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:13%">{{ __('production.date') }}</th>
                            <th style="width:12%">{{ __('production.sku') }}</th>
                            <th style="width:22%">{{ __('production.product_name') }}</th>
                            <th style="width:14%">{{ __('production.warehouse') }}</th>
                            <th style="width:10%" class="text-center">{{ __('production.ordered_qty') }}</th>
                            <th style="width:10%">{{ __('production.type') }}</th>
                            <th style="width:12%">{{ __('production.operator') }}</th>
                            <th>{{ __('production.remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->issues as $iss)
                            <tr>
                                <td class="text-muted">{{ $iss->issued_at->format('Y-m-d H:i') }}</td>
                                <td class="fw-bold text-dark font-monospace fs-12">{{ $iss->product->sku }}</td>
                                <td>{{ $iss->product->name }}</td>
                                <td class="text-muted">{{ $iss->warehouse?->name ?? '—' }}</td>
                                <td class="text-center fw-bold {{ $iss->quantity_issued < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($iss->quantity_issued, 2) }}
                                </td>
                                <td>
                                    @if($iss->issue_type === 'standard')
                                        <span class="badge bg-light text-success border border-success">Standard</span>
                                    @elseif($iss->issue_type === 'additional')
                                        <span class="badge bg-light text-warning border border-warning">Additional</span>
                                    @elseif($iss->issue_type === 'return')
                                        <span class="badge bg-light text-danger border border-danger">Return</span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ $iss->issue_type }}</span>
                                    @endif
                                </td>
                                <td>{{ $iss->user->name ?? 'System' }}</td>
                                <td class="text-muted">{{ $iss->remarks ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-info fs-20 d-block mb-2"></i>{{ __('production.no_issues_logged') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 6: Progress Logs --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-progress' ? 'show active' : '' }}" id="vtab-progress" role="tabpanel" aria-labelledby="vtab-progress-tab">
            {{-- KPI Summary Row --}}
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="bg-light rounded p-3 text-center border">
                        <div class="text-muted fs-11 text-uppercase fw-bold mb-1">{{ __('production.planned_target') }}</div>
                        <h3 class="text-dark fw-bold mb-0">{{ number_format($order->quantity_ordered, 2) }}</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bg-soft-success rounded p-3 text-center border border-success">
                        <div class="text-success fs-11 text-uppercase fw-bold mb-1">{{ __('production.actual_produced') }}</div>
                        <h3 class="text-success fw-bold mb-0">{{ number_format($order->quantity_produced, 2) }}</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bg-soft-danger rounded p-3 text-center border border-danger">
                        <div class="text-danger fs-11 text-uppercase fw-bold mb-1">{{ __('production.scrapped_qty') }}</div>
                        <h3 class="text-danger fw-bold mb-0">{{ number_format($order->quantity_scrapped, 2) }}</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bg-soft-warning rounded p-3 text-center border border-warning">
                        <div class="text-warning fs-11 text-uppercase fw-bold mb-1">{{ __('production.rejected_rework') }}</div>
                        <h3 class="text-warning fw-bold mb-0">{{ number_format($order->quantity_rejected, 2) }}</h3>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold text-dark mb-3">{{ __('production.fg_receipts_log') }}</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:18%">{{ __('production.receipt_date') }}</th>
                            <th style="width:15%" class="text-center">{{ __('production.qty_received') }}</th>
                            <th style="width:15%">{{ __('production.quality_status') }}</th>
                            <th style="width:15%">{{ __('production.receiver') }}</th>
                            <th>{{ __('production.remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->receipts as $rec)
                            <tr>
                                <td class="text-muted">{{ $rec->received_at->format('Y-m-d H:i') }}</td>
                                <td class="text-center fw-bold text-success">{{ number_format($rec->quantity_received, 2) }}</td>
                                <td>
                                    @if($rec->quality_status === 'passed')
                                        <span class="badge bg-success text-white">Passed</span>
                                    @elseif($rec->quality_status === 'quarantine')
                                        <span class="badge bg-warning text-dark">Quarantine</span>
                                    @elseif($rec->quality_status === 'failed')
                                        <span class="badge bg-danger text-white">Failed</span>
                                    @endif
                                </td>
                                <td>{{ $rec->user->name ?? 'System' }}</td>
                                <td class="text-muted">{{ $rec->remarks ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="feather-info fs-20 d-block mb-2"></i>{{ __('production.no_receipts_logged') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h5 class="fw-bold text-dark mt-4 mb-3">{{ __('production.daily_execution_logs') }}</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:18%">{{ __('production.log_date') }}</th>
                            <th style="width:25%">{{ __('production.operation_step') }}</th>
                            <th style="width:12%" class="text-center">{{ __('production.qty_produced') }}</th>
                            <th style="width:12%" class="text-center">{{ __('production.qty_rejected') }}</th>
                            <th style="width:12%" class="text-center">{{ __('production.qty_scrapped') }}</th>
                            <th style="width:10%" class="text-center">{{ __('production.time_spent') }}</th>
                            <th style="width:10%">{{ __('production.logged_by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->progressLogs as $log)
                            <tr>
                                <td class="text-muted">{{ $log->recorded_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $log->operation->name ?? '—' }}</span>
                                    <small class="text-muted d-block font-monospace fs-10">Op: {{ $log->operation->operation_number ?? '—' }}</small>
                                </td>
                                <td class="text-center text-success fw-bold">{{ number_format($log->quantity_produced, 2) }}</td>
                                <td class="text-center text-warning fw-bold">{{ number_format($log->quantity_rejected, 2) }}</td>
                                <td class="text-center text-danger fw-bold">{{ number_format($log->quantity_scrapped, 2) }}</td>
                                <td class="text-center">{{ number_format(($log->setup_minutes_logged + $log->run_minutes_logged)/60, 2) }} hrs</td>
                                <td>{{ $log->user->name ?? 'Operator' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-info fs-20 d-block mb-2"></i>{{ __('production.no_progress_logs_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 7: Scrap & Rework --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-scrap' ? 'show active' : '' }}" id="vtab-scrap" role="tabpanel" aria-labelledby="vtab-scrap-tab">
            <div class="row g-4">
                <div class="col-md-6">
                    <h5 class="fw-bold text-dark mb-3">{{ __('production.scrap_log_entries') }}</h5>
                    <div class="table-responsive">
                        <table class="erp-thin-table">
                            <thead>
                                <tr>
                                    <th style="width:20%">{{ __('production.date') }}</th>
                                    <th style="width:30%">{{ __('production.item_component') }}</th>
                                    <th style="width:15%" class="text-center">{{ __('production.ordered_qty') }}</th>
                                    <th>{{ __('production.reason') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->scraps as $scr)
                                    <tr>
                                        <td class="text-muted">{{ $scr->recorded_at->format('m-d H:i') }}</td>
                                        <td>
                                            <span class="fw-bold text-dark">{{ $scr->product ? $scr->product->sku : 'Finished Good' }}</span>
                                            @if($scr->operation)
                                                <div class="text-muted fs-11">Op: {{ $scr->operation->operation_number }}</div>
                                            @endif
                                        </td>
                                        <td class="text-center text-danger fw-bold">{{ number_format($scr->quantity, 2) }}</td>
                                        <td class="text-muted fs-12">{{ $scr->reason ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">{{ __('production.no_scrap_logged') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="fw-bold text-dark mb-3">{{ __('production.rework_events_track') }}</h5>
                    <div class="table-responsive">
                        <table class="erp-thin-table">
                            <thead>
                                <tr>
                                    <th style="width:20%">{{ __('production.date') }}</th>
                                    <th style="width:25%">{{ __('production.operation') }}</th>
                                    <th style="width:12%" class="text-center">{{ __('production.ordered_qty') }}</th>
                                    <th style="width:15%">{{ __('production.status') }}</th>
                                    <th>{{ __('production.reason') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->reworks as $rew)
                                    <tr>
                                        <td class="text-muted">{{ $rew->recorded_at->format('m-d H:i') }}</td>
                                        <td class="fw-bold text-dark">
                                            {{ $rew->operation ? $rew->operation->operation_number : 'Header Order' }}
                                        </td>
                                        <td class="text-center text-warning fw-bold">{{ number_format($rew->quantity, 2) }}</td>
                                        <td>
                                            @if($rew->status === 'completed')
                                                <span class="badge bg-success text-white">Resolved</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Rework Pending</span>
                                            @endif
                                        </td>
                                        <td class="text-muted fs-12">{{ $rew->reason ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">{{ __('production.no_reworks_tracked') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab: Cost Analysis --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-cost' ? 'show active' : '' }}" id="vtab-cost" role="tabpanel" aria-labelledby="vtab-cost-tab">
            {{-- Cost KPI Row --}}
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="bg-light rounded p-4 text-center border">
                        <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.total_planned_cost') }}</span>
                        <h2 class="text-dark fw-bold mt-2 mb-0">{{ format_currency($costs['totals']['planned']) }}</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-light rounded p-4 text-center border">
                        <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.total_actual_cost') }}</span>
                        <h2 class="text-dark fw-bold mt-2 mb-0">{{ format_currency($costs['totals']['actual']) }}</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    @php $vVal = $costs['totals']['variance']; @endphp
                    <div class="bg-light rounded p-4 text-center border {{ $vVal > 0 ? 'border-danger' : ($vVal < 0 ? 'border-success' : '') }}">
                        <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.variance') }}</span>
                        <h2 class="fw-bold mt-2 mb-0 {{ $vVal > 0 ? 'text-danger' : ($vVal < 0 ? 'text-success' : 'text-muted') }}">
                            {{ format_currency($vVal) }}
                            <span class="fs-12 fw-normal">({{ $costs['totals']['variance_percentage'] }}%)</span>
                        </h2>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold text-dark mb-3">{{ __('production.variance_analysis_matrix') }}</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:35%">{{ __('production.cost_element') }}</th>
                            <th style="width:20%" class="text-end">{{ __('production.planned_cost') }}</th>
                            <th style="width:20%" class="text-end">{{ __('production.actual_cost') }}</th>
                            <th style="width:25%" class="text-end">{{ __('production.variance') }} ({{ active_currency_symbol() }} / %)</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach([
                            ['label' => __('production.material_costs'),           'key' => 'material'],
                            ['label' => __('production.labor_cost'),               'key' => 'labor'],
                            ['label' => __('production.machine_utilization_cost'), 'key' => 'machine'],
                            ['label' => __('production.work_center_overhead'),     'key' => 'overhead'],
                        ] as $row)
                            <tr>
                                <td class="fw-bold text-dark">{{ $row['label'] }}</td>
                                <td class="text-end">{{ format_currency($costs[$row['key']]['planned']) }}</td>
                                <td class="text-end">{{ format_currency($costs[$row['key']]['actual']) }}</td>
                                <td class="text-end fw-bold {{ $costs[$row['key']]['variance'] > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ format_currency($costs[$row['key']]['variance']) }}
                                </td>
                            </tr>
                        @endforeach
                        <tr class="table-light">
                            <td class="fw-bold text-dark text-uppercase fs-12">{{ __('production.total_cost') }}</td>
                            <td class="text-end fw-bold text-dark">{{ format_currency($costs['totals']['planned']) }}</td>
                            <td class="text-end fw-bold text-dark">{{ format_currency($costs['totals']['actual']) }}</td>
                            <td class="text-end fw-bold {{ $costs['totals']['variance'] > 0 ? 'text-danger' : 'text-success' }}">
                                {{ format_currency($costs['totals']['variance']) }}
                                ({{ $costs['totals']['variance_percentage'] }}%)
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Final Manufacturing Cost Summary (Automatic + Manual Adjustments) --}}
            <h5 class="fw-bold text-dark mt-5 mb-3"><i class="feather-pie-chart text-primary me-2"></i> {{ __('production.final_cost_breakdown') }}</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:25%">{{ __('production.cost_component') }}</th>
                            <th style="width:25%" class="text-end">{{ __('production.automatic_cost') }}</th>
                            <th style="width:25%" class="text-end">{{ __('production.manual_adjustments') }}</th>
                            <th style="width:25%" class="text-end">{{ __('production.final_cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold text-dark">{{ __('production.material_costs') }}</td>
                            <td class="text-end">{{ format_currency($finalCostingSummary['material']['auto']) }}</td>
                            <td class="text-end text-warning fw-semibold">{{ format_currency($finalCostingSummary['material']['manual']) }}</td>
                            <td class="text-end fw-bold text-dark">{{ format_currency($finalCostingSummary['material']['final']) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark">{{ __('production.labor_cost') }}</td>
                            <td class="text-end">{{ format_currency($finalCostingSummary['labor']['auto']) }}</td>
                            <td class="text-end text-warning fw-semibold">{{ format_currency($finalCostingSummary['labor']['manual']) }}</td>
                            <td class="text-end fw-bold text-dark">{{ format_currency($finalCostingSummary['labor']['final']) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark">{{ __('production.machine_utilization_cost') }}</td>
                            <td class="text-end">{{ format_currency($finalCostingSummary['machine']['auto']) }}</td>
                            <td class="text-end text-warning fw-semibold">{{ format_currency($finalCostingSummary['machine']['manual']) }}</td>
                            <td class="text-end fw-bold text-dark">{{ format_currency($finalCostingSummary['machine']['final']) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark">{{ __('production.work_center_overhead') }}</td>
                            <td class="text-end">{{ format_currency($finalCostingSummary['overhead']['auto']) }}</td>
                            <td class="text-end text-warning fw-semibold">{{ format_currency($finalCostingSummary['overhead']['manual']) }}</td>
                            <td class="text-end fw-bold text-dark">{{ format_currency($finalCostingSummary['overhead']['final']) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-dark">{{ __('production.other_uncategorized_expenses') }}</td>
                            <td class="text-end text-muted">{{ format_currency(0) }}</td>
                            <td class="text-end text-warning fw-semibold">{{ format_currency($finalCostingSummary['other']['manual']) }}</td>
                            <td class="text-end fw-bold text-dark">{{ format_currency($finalCostingSummary['other']['final']) }}</td>
                        </tr>
                        <tr class="table-light">
                            <td class="fw-bold text-dark text-uppercase fs-12">{{ __('production.total_manufacturing_cost') }}</td>
                            <td class="text-end fw-bold text-dark">{{ format_currency($finalCostingSummary['totals']['auto']) }}</td>
                            <td class="text-end fw-bold text-warning">{{ format_currency($finalCostingSummary['totals']['manual']) }}</td>
                            <td class="text-end fw-bold text-primary fs-14">{{ format_currency($finalCostingSummary['totals']['final']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Day-Wise Production & Costing History Table --}}
            <h5 class="fw-bold text-dark mt-5 mb-3"><i class="feather-calendar text-primary me-2"></i> {{ __('production.day_wise_costing_history') }}</h5>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:9%">{{ __('production.date') }}</th>
                            <th style="width:13%">{{ __('production.ops_worked') }}</th>
                            <th style="width:10%" class="text-center">{{ __('production.produced_scrap') }}</th>
                            <th style="width:8%" class="text-center">{{ __('production.hours') }}</th>
                            <th style="width:12%">{{ __('production.operators_machines') }}</th>
                            <th style="width:9%" class="text-end">{{ __('production.auto_cost') }}</th>
                            <th style="width:9%" class="text-end">{{ __('production.manual_adj') }}</th>
                            <th style="width:10%" class="text-end">{{ __('production.final_daily_cost') }}</th>
                            <th style="width:10%" class="text-end">{{ __('production.cumul_auto') }}</th>
                            <th style="width:10%" class="text-end">{{ __('production.cumul_adj') }}</th>
                            <th style="width:10%" class="text-end">{{ __('production.cumul_final') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailyHistory as $day)
                            <tr>
                                <td class="fw-bold text-dark font-monospace">{{ $day['date'] }}</td>
                                <td>{{ $day['operations_worked'] ?: '—' }}</td>
                                <td class="text-center">
                                    <span class="text-success fw-bold">{{ number_format($day['quantity_produced'], 2) }}</span>
                                    /
                                    <span class="text-danger">{{ number_format($day['quantity_scrapped'] + $day['quantity_rejected'], 2) }}</span>
                                </td>
                                <td class="text-center fw-semibold">{{ number_format($day['total_minutes'] / 60, 2) }}h</td>
                                <td>
                                    <small class="d-block text-dark">{{ $day['operators'] ?: '—' }}</small>
                                    <small class="text-muted font-monospace fs-10">{{ $day['machines'] ?: '—' }}</small>
                                </td>
                                <td class="text-end fw-semibold text-dark">{{ format_currency($day['automatic_daily_cost']) }}</td>
                                <td class="text-end text-warning fw-semibold">{{ format_currency($day['manual_daily_adjustment']) }}</td>
                                <td class="text-end fw-bold text-primary">{{ format_currency($day['final_daily_cost']) }}</td>
                                <td class="text-end text-muted">{{ format_currency($day['cumulative_automatic_cost']) }}</td>
                                <td class="text-end text-warning">{{ format_currency($day['cumulative_manual_adjustment']) }}</td>
                                <td class="text-end fw-bold text-dark">{{ format_currency($day['cumulative_final_cost']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="feather-info me-1"></i>{{ __('production.no_daily_cost_history') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab: Cost Adjustments --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-cost-adjustments' ? 'show active' : '' }}" id="vtab-cost-adjustments" role="tabpanel" aria-labelledby="vtab-cost-adjustments-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0"><i class="feather-dollar-sign text-primary me-2"></i> {{ __('production.manual_cost_adjustments') }}</h5>
                    <span class="fs-12 text-muted">{{ __('production.manual_cost_adjustments_desc') }}</span>
                </div>
                @if(!$order->isCompleted() && !$order->isClosed() && !$order->isCancelled())
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCostAdjustmentModal">
                        <i class="feather-plus-circle me-1"></i> {{ __('production.add_cost_adjustment') }}
                    </button>
                @endif
            </div>

            {{-- Summary Cards --}}
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card border bg-light py-2 px-3 text-center">
                        <span class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.total_manual_adjustments') }}</span>
                        <h4 class="fw-bold text-primary mb-0 mt-1">{{ format_currency($finalCostingSummary['totals']['manual']) }}</h4>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border bg-light py-2 px-3 text-center">
                        <span class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.adjustment_records') }}</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1">{{ $costAdjustments->total() }}</h4>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:12%">{{ __('production.date') }}</th>
                            <th style="width:12%">{{ __('production.component') }}</th>
                            <th style="width:18%">{{ __('production.category') }}</th>
                            <th style="width:25%">{{ __('production.description') }}</th>
                            <th style="width:12%" class="text-end">{{ __('production.amount') }}</th>
                            <th style="width:10%">{{ __('production.attachment') }}</th>
                            <th style="width:11%">{{ __('production.created_by') }}</th>
                            <th style="width:10%" class="text-end">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($costAdjustments as $adj)
                            <tr>
                                <td class="fw-bold text-dark font-monospace">{{ $adj->adjustment_date ? $adj->adjustment_date->format('Y-m-d') : '—' }}</td>
                                <td>
                                    <span class="badge bg-soft-info text-info text-uppercase fs-10">
                                        {{ $costComponents[$adj->cost_component] ?? ucfirst($adj->cost_component) }}
                                    </span>
                                </td>
                                <td class="fw-semibold text-dark">{{ $adj->category }}</td>
                                <td>
                                    {{ $adj->description }}
                                    @if($adj->notes)
                                        <small class="d-block text-muted">{{ $adj->notes }}</small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-danger">{{ format_currency($adj->amount) }}</td>

                                <td>
                                    @if($adj->attachment_path)
                                        <a href="{{ route('production.cost-adjustments.download', $adj->id) }}" class="btn btn-xs btn-outline-secondary" title="{{ __('production.download_attachment') }}">
                                            <i class="feather-paperclip me-1"></i> File
                                        </a>
                                    @else
                                        <span class="text-muted fs-11">—</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-dark">{{ $adj->creator ? $adj->creator->name : 'System' }}</small>
                                </td>
                                <td class="text-end">
                                    @if(!$order->isCompleted() && !$order->isClosed() && !$order->isCancelled())
                                        <x-ui.action-dropdown id="adjActionDropdown{{ $adj->id }}">
                                            <li>
                                                <a class="dropdown-item py-1.5 fs-12" href="javascript:void(0)"
                                                   data-bs-toggle="modal" data-bs-target="#editCostAdjustmentModal{{ $adj->id }}">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_adjustment') }}
                                                </a>
                                            </li>
                                            @if($adj->attachment_path)
                                                <li>
                                                    <a class="dropdown-item py-1.5 fs-12" href="{{ route('production.cost-adjustments.download', $adj->id) }}">
                                                        <i class="feather-paperclip me-2 text-muted fs-12"></i>{{ __('production.download_attachment') }}
                                                    </a>
                                                </li>
                                            @endif
                                            <li>
                                                <form method="POST" action="{{ route('production.cost-adjustments.destroy', $adj->id) }}" onsubmit="return confirm('Are you sure you want to soft-delete this cost adjustment?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger py-1.5 fs-12">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_adjustment') }}
                                                    </button>
                                                </form>
                                            </li>
                                        </x-ui.action-dropdown>
                                    @else
                                        <span class="text-muted fs-11">Locked</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="feather-info me-1"></i>{{ __('production.no_cost_adjustments_recorded') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($costAdjustments->hasPages())
                <div class="mt-3">
                    {{ $costAdjustments->links() }}
                </div>
            @endif
        </div>

        {{-- Tab: Material Requests & Procurement --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-procurement' ? 'show active' : '' }}" id="vtab-procurement" role="tabpanel" aria-labelledby="vtab-procurement-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="feather-truck text-primary me-2"></i> {{ __('production.procurement_status') }}</h5>
                <span class="fs-12 text-muted">{{ __('production.procurement_status_desc') }}</span>
            </div>

            @php
                $slips = $order->requisitionSlips;
                $totalSlips = $slips->count();
                $pendingSlips = $slips->where('status', 'pending')->count();
                $allPrs = collect();
                foreach($slips as $s) {
                    foreach($s->purchaseRequisitions as $pr) {
                        $allPrs->push($pr);
                    }
                }
                $pendingPrs = $allPrs->where('status', 'Draft')->count();
            @endphp

            {{-- Summary Badges --}}
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border bg-light py-2 px-3 text-center">
                        <span class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.total_requisition_slips') }}</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1">{{ $totalSlips }}</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border bg-light py-2 px-3 text-center">
                        <span class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.pending_material_requests') }}</span>
                        <h4 class="fw-bold text-warning mb-0 mt-1">{{ $pendingSlips }}</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border bg-light py-2 px-3 text-center">
                        <span class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.linked_purchase_reqs') }}</span>
                        <h4 class="fw-bold text-primary mb-0 mt-1">{{ $allPrs->count() }}</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border bg-light py-2 px-3 text-center">
                        <span class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.pending_pr_approval') }}</span>
                        <h4 class="fw-bold text-info mb-0 mt-1">{{ $pendingPrs }}</h4>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th style="width:15%">{{ __('production.slip_hash') }}</th>
                            <th style="width:12%">{{ __('production.request_date') }}</th>
                            <th style="width:10%">{{ __('production.status') }}</th>
                            <th style="width:10%" class="text-center">{{ __('production.items') }}</th>
                            <th style="width:25%">{{ __('production.linked_pr') }}</th>
                            <th style="width:18%">{{ __('production.pr_status') }}</th>
                            <th style="width:10%" class="text-end">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($slips as $slip)
                            <tr>
                                <td>
                                    <a href="{{ route('sales.material-requests.show', $slip->id) }}" class="fw-bold text-primary font-monospace">
                                        {{ $slip->requisition_number }}
                                    </a>
                                </td>
                                <td class="text-muted">{{ $slip->requisition_date }}</td>
                                <td>
                                    @if($slip->status === 'completed')
                                        <span class="badge bg-soft-success text-success text-uppercase">{{ __('production.completed') }}</span>
                                    @elseif($slip->status === 'partial')
                                        <span class="badge bg-soft-warning text-warning text-uppercase">Partial</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger text-uppercase">Pending</span>
                                    @endif
                                </td>
                                <td class="text-center fw-semibold">{{ $slip->items->count() }}</td>
                                <td>
                                    @if($slip->purchaseRequisitions->isNotEmpty())
                                        @foreach($slip->purchaseRequisitions as $pr)
                                            <div>
                                                @if(Route::has('purchase.requisitions.show'))
                                                    <a href="{{ route('purchase.requisitions.show', $pr->id) }}" class="fw-bold text-primary font-monospace">
                                                        {{ $pr->requisition_number }}
                                                    </a>
                                                @else
                                                    <span class="fw-bold text-dark font-monospace">{{ $pr->requisition_number }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <span class="text-muted fs-12">Not required (In Stock)</span>
                                    @endif
                                </td>
                                <td>
                                    @if($slip->purchaseRequisitions->isNotEmpty())
                                        @foreach($slip->purchaseRequisitions as $pr)
                                            <div>
                                                @if($pr->status === 'Approved')
                                                    <span class="badge bg-soft-success text-success">PR Approved</span>
                                                @elseif($pr->status === 'Cancelled')
                                                    <span class="badge bg-soft-danger text-danger">PR Cancelled</span>
                                                @else
                                                    <span class="badge bg-soft-warning text-warning">PR Pending Review</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <x-ui.action-dropdown :viewUrl="route('sales.material-requests.show', $slip->id)">
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-info fs-20 d-block mb-2"></i>{{ __('production.no_slips_recorded') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 8: Audit Trail --}}
        <div class="tab-pane fade {{ $activeTab === 'vtab-audit' ? 'show active' : '' }}" id="vtab-audit" role="tabpanel" aria-labelledby="vtab-audit-tab">
            <h5 class="fw-bold text-dark mb-3">{{ __('production.audit_logs_trail') }}</h5>
            <ul class="list-unstyled mb-0 fs-13">
                <li class="mb-3 d-flex align-items-start">
                    <div class="avatar-text avatar-sm bg-soft-primary text-primary me-3 mt-1 rounded-circle">
                        <i class="feather-user fs-14"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark">{{ __('production.order_created') }}</div>
                        <div class="text-muted fs-11">By: {{ $order->creator->name ?? 'System' }} at {{ $order->created_at->format('Y-m-d H:i:s') }}</div>
                    </div>
                </li>
                @if($order->released_at)
                    <li class="mb-3 d-flex align-items-start">
                        <div class="avatar-text avatar-sm bg-soft-success text-success me-3 mt-1 rounded-circle">
                            <i class="feather-play-circle fs-14"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">{{ __('production.order_released') }}</div>
                            <div class="text-muted fs-11">By: {{ $order->releaser->name ?? 'System' }} at {{ $order->released_at->format('Y-m-d H:i:s') }}</div>
                        </div>
                    </li>
                @endif
                @if($order->completed_at)
                    <li class="mb-3 d-flex align-items-start">
                        <div class="avatar-text avatar-sm bg-soft-success text-success me-3 mt-1 rounded-circle">
                            <i class="feather-check-circle fs-14"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">{{ __('production.order_completed') }}</div>
                            <div class="text-muted fs-11">By: {{ $order->completer->name ?? 'System' }} at {{ $order->completed_at->format('Y-m-d H:i:s') }}</div>
                        </div>
                    </li>
                @endif
                @if($order->closed_at)
                    <li class="mb-3 d-flex align-items-start">
                        <div class="avatar-text avatar-sm bg-soft-dark text-dark me-3 mt-1 rounded-circle">
                            <i class="feather-archive fs-14"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">Order Closed &amp; Archived</div>
                            <div class="text-muted fs-11">By: {{ $order->closer->name ?? 'System' }} at {{ $order->closed_at->format('Y-m-d H:i:s') }}</div>
                        </div>
                    </li>
                @endif
            </ul>
        </div>

            </div>{{-- end .tab-content --}}
        </div>{{-- end right content col --}}
    </div>{{-- end 2-column row --}}

    {{-- ── MODALS (using x-ui.modal component — body.appendChild fixes z-index) ── --}}

    {{-- Log Progress Modal --}}
    <x-ui.modal id="progressModal" title="{{ __('production.log_operation_execution') }}" size="lg" class="text-start">
        <form method="POST" action="{{ route('production.orders.log-progress', $order->id) }}" id="progressForm">
            @csrf
            
            <x-ui.odoo-form-ui type="select" label="{{ __('production.select_operation') }}" name="operation_id" id="op_select_id" :required="true">
                @foreach($order->operations as $op)
                    @if($op->status !== 'completed')
                        <option value="{{ $op->id }}">{{ $op->operation_number }} — {{ $op->name }}</option>
                    @endif
                @endforeach
            </x-ui.odoo-form-ui>

            <div class="row g-2 mb-1 fs-13 text-dark">
                <div class="col-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('production.qty_produced') }}" name="quantity_produced" inputType="number" step="0.0001" value="0" :required="true" />
                </div>
                <div class="col-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('production.qty_rejected') }}" name="quantity_rejected" inputType="number" step="0.0001" value="0" :required="true" />
                </div>
                <div class="col-4">
                    <x-ui.odoo-form-ui type="input" label="{{ __('production.qty_scrapped') }}" name="quantity_scrapped" inputType="number" step="0.0001" value="0" :required="true" />
                </div>
            </div>

            <div class="row g-2 mb-1 fs-13 text-dark">
                <div class="col-6">
                    <x-ui.odoo-form-ui type="input" label="{{ __('production.setup_minutes') }}" name="setup_minutes_logged" inputType="number" value="0" :required="true" />
                </div>
                <div class="col-6">
                    <x-ui.odoo-form-ui type="input" label="{{ __('production.run_minutes') }}" name="run_minutes_logged" inputType="number" value="0" :required="true" />
                </div>
            </div>

            <x-ui.odoo-form-ui type="input" label="{{ __('production.remarks') }}" name="remarks" placeholder="E.g. operator name, work center notes" />

            <div class="odoo-form-group">
                <label class="odoo-form-label">{{ __('production.completion') }}</label>
                <div class="flex-grow-1">
                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" name="complete_operation" value="1" id="complete_operation">
                        <label class="form-check-label fw-bold text-dark fs-12 ms-2" for="complete_operation">{{ __('production.mark_operation_completed') }}</label>
                    </div>
                </div>
            </div>
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('progressForm').submit();">{{ __('production.save_progress_log') }}</button>
        </x-slot>
    </x-ui.modal>

    {{-- Issue Materials Modal --}}
    <x-ui.modal id="issueModal" title="{{ __('production.issue_raw_material') }}" class="text-start">
        <form method="POST" action="{{ route('production.orders.issue', $order->id) }}" id="issueForm">
            @csrf
            
            <x-ui.odoo-form-ui type="select" label="{{ __('production.reservation') }}" name="reservation_id" id="issue_reservation_id" :required="true">
                @foreach($order->reservations as $res)
                    <option value="{{ $res->id }}">
                        {{ $res->product->name }} ({{ $res->product->sku }}) — Reserved: {{ number_format($res->quantity_reserved, 2) }}
                    </option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="select" label="{{ __('production.warehouse') }}" name="warehouse_id">
                <option value="">{{ __('production.use_reservation_warehouse') }}</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="input" label="{{ __('production.ordered_qty') }}" name="quantity" inputType="number" step="0.0001" :required="true" />
            
            <x-ui.odoo-form-ui type="input" label="{{ __('production.remarks') }}" name="remarks" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="submit" class="btn btn-info text-white" onclick="document.getElementById('issueForm').submit();">{{ __('production.log_material_issue') }}</button>
        </x-slot>
    </x-ui.modal>

    {{-- Return Materials Modal --}}
    <x-ui.modal id="returnModal" title="{{ __('production.return_materials_stock') }}" class="text-start">
        <form method="POST" action="{{ route('production.orders.return', $order->id) }}" id="returnForm">
            @csrf

            <x-ui.odoo-form-ui type="select" label="{{ __('production.reservation') }}" name="reservation_id" id="return_reservation_id" :required="true">
                @foreach($order->reservations as $res)
                    <option value="{{ $res->id }}">
                        {{ $res->product->name }} ({{ $res->product->sku }}) — Issued: {{ number_format($res->quantity_issued, 2) }}
                    </option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="select" label="{{ __('production.warehouse') }}" name="warehouse_id">
                <option value="">{{ __('production.use_reservation_warehouse') }}</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="input" label="{{ __('production.return_qty') }}" name="quantity" inputType="number" step="0.0001" :required="true" />

            <x-ui.odoo-form-ui type="input" label="{{ __('production.remarks') }}" name="remarks" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('returnForm').submit();">{{ __('production.process_return') }}</button>
        </x-slot>
    </x-ui.modal>

    {{-- Receive Finished Goods Modal --}}
    <x-ui.modal id="receiptModal" title="{{ __('production.receive_fg_title') }}" class="text-start">
        <form method="POST" action="{{ route('production.orders.receive-fg', $order->id) }}" id="receiptForm">
            @csrf
            
            <div class="mb-3 bg-light p-3 rounded fs-13 text-dark">
                <label class="form-label fw-bold text-muted fs-11 text-uppercase mb-1">{{ __('production.target_product') }}</label>
                <div class="text-dark fw-bold">{{ $order->product->name }} ({{ $order->product->sku }})</div>
            </div>

            <x-ui.odoo-form-ui type="input" label="{{ __('production.qty_received') }}" name="quantity_received" inputType="number" step="0.0001" :required="true" />

            <x-ui.odoo-form-ui type="select" label="{{ __('production.warehouse') }}" name="warehouse_id">
                <option value="">{{ __('production.use_default_warehouse') }}</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                @endforeach
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="select" label="{{ __('production.quality_status') }}" name="quality_status" :required="true">
                <option value="passed">{{ __('production.passed_inventory') }}</option>
                <option value="quarantine">{{ __('production.quarantine_inspection') }}</option>
                <option value="failed">{{ __('production.failed_defective') }}</option>
            </x-ui.odoo-form-ui>

            <x-ui.odoo-form-ui type="input" label="{{ __('production.remarks') }}" name="remarks" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="submit" class="btn btn-warning text-dark" onclick="document.getElementById('receiptForm').submit();">{{ __('production.confirm_fg_receipt') }}</button>
        </x-slot>
    </x-ui.modal>

    {{-- Log Scrap / Rework Modal --}}
    <x-ui.modal id="scrapReworkModal" title="{{ __('production.log_scrap_rework_title') }}" size="lg" class="text-start">
        {{-- Inner tab nav --}}
        <ul class="nav nav-tabs mb-3" id="scrapReworkTabNav" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="sr-scrap-tab" data-bs-toggle="tab" data-bs-target="#sr-scrap" type="button" role="tab">{{ __('production.log_scrap_tab') }}</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="sr-rework-tab" data-bs-toggle="tab" data-bs-target="#sr-rework" type="button" role="tab">{{ __('production.log_rework_tab') }}</button>
            </li>
        </ul>

        <div class="tab-content" id="scrapReworkTabContent">
            {{-- Scrap Tab --}}
            <div class="tab-pane fade show active" id="sr-scrap" role="tabpanel">
                <form method="POST" action="{{ route('production.orders.log-scrap', $order->id) }}" id="scrapForm">
                    @csrf

                    <x-ui.odoo-form-ui type="select" label="{{ __('production.operation') }}">
                        <option value="">{{ __('production.order_header_whole_assembly') }}</option>
                        @foreach($order->operations as $op)
                            <option value="{{ $op->id }}">Op {{ $op->operation_number }} — {{ $op->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="select" label="{{ __('production.scrap_target') }}">
                        <option value="">{{ __('production.finished_good') }} ({{ $order->product->sku }})</option>
                        @foreach($order->reservations as $res)
                            <option value="{{ $res->product_id }}">{{ $res->product->name }} ({{ $res->product->sku }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" label="{{ __('production.scrapped_qty') }}" name="quantity" inputType="number" step="0.0001" :required="true" />

                    <x-ui.odoo-form-ui type="input" label="{{ __('production.reason') }}" name="reason" placeholder="E.g. material defect, processing error" :required="true" />

                    <div class="text-end mt-3 border-top pt-2">
                        <button type="button" class="btn btn-light-brand me-2" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('production.log_scrap_tab') }}</button>
                    </div>
                </form>
            </div>

            {{-- Rework Tab --}}
            <div class="tab-pane fade" id="sr-rework" role="tabpanel">
                <form method="POST" action="{{ route('production.orders.log-rework', $order->id) }}" id="reworkForm">
                    @csrf

                    <x-ui.odoo-form-ui type="select" label="{{ __('production.rework_target') }}" name="operation_id" :required="true">
                        @foreach($order->operations as $op)
                            <option value="{{ $op->id }}">Op {{ $op->operation_number }} — {{ $op->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" label="{{ __('production.rework_qty') }}" name="quantity" inputType="number" step="0.0001" :required="true" />

                    <x-ui.odoo-form-ui type="input" label="{{ __('production.rework_notes') }}" name="reason" placeholder="Describe issue and corrective actions" :required="true" />

                    <div class="text-end mt-3 border-top pt-2">
                        <button type="button" class="btn btn-light-brand me-2" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                        <button type="submit" class="btn btn-warning text-dark">{{ __('production.log_rework_tab') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Empty footer slot --}}
        <x-slot name="footer"></x-slot>
    </x-ui.modal>

    {{-- Request Additional Material Modal --}}
    <x-ui.modal id="requestAdditionalMaterialModal" title="{{ __('production.request_additional_material') }}" size="lg" class="text-start">
        <form method="POST" action="{{ route('production.orders.request-additional-material', $order->id) }}" id="additionalMaterialForm">
            @csrf
            
            <div class="bg-light p-3 rounded mb-3 border fs-13">
                <div class="row g-2">
                    <div class="col-6">
                        <span class="text-muted d-block fs-11 text-uppercase">{{ __('production.production_order') }}</span>
                        <strong class="text-dark fs-14">{{ $order->order_number }}</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block fs-11 text-uppercase">{{ __('production.target_product') }}</span>
                        <strong class="text-dark fs-14">{{ $order->product->name }} ({{ $order->product->sku }})</strong>
                    </div>
                </div>
            </div>

            <p class="fs-12 text-muted mb-2">Select components and enter the additional quantity requested from warehouse or procurement:</p>

            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered align-middle fs-12 mb-0">
                    <thead class="bg-soft-light text-uppercase fs-11 fw-semibold text-muted">
                        <tr>
                            <th style="width:5%" class="text-center">{{ __('production.action') }}</th>
                            <th style="width:30%">{{ __('production.component') }}</th>
                            <th style="width:12%" class="text-center">{{ __('production.planned_cost') }}</th>
                            <th style="width:12%" class="text-center">{{ __('production.issued_qty') }}</th>
                            <th style="width:12%" class="text-center">{{ __('production.shortage') }}</th>
                            <th style="width:14%" class="text-center">{{ __('production.requested_qty') }}</th>
                            <th style="width:15%">{{ __('production.remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->reservations as $idx => $res)
                            @php
                                $shortage = max(0.0, $res->quantity_planned - $res->quantity_issued);
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="items[{{ $idx }}][selected]" value="1" class="form-check-input item-checkbox" id="chk_{{ $idx }}" checked>
                                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $res->product_id }}">
                                </td>
                                <td>
                                    <label for="chk_{{ $idx }}" class="fw-bold text-dark mb-0 cursor-pointer">{{ $res->product->name }}</label>
                                    <div class="text-muted font-monospace fs-10">{{ $res->product->sku }}</div>
                                </td>
                                <td class="text-center fw-semibold text-dark">{{ number_format($res->quantity_planned, 2) }}</td>
                                <td class="text-center text-success fw-bold">{{ number_format($res->quantity_issued, 2) }}</td>
                                <td class="text-center text-danger fw-bold">{{ number_format($shortage, 2) }}</td>
                                <td>
                                    <input type="number" name="items[{{ $idx }}][quantity]" class="form-control form-control-sm" step="0.0001" min="0.0001" value="{{ $shortage > 0 ? $shortage : 1 }}">
                                </td>
                                <td>
                                    <input type="text" name="items[{{ $idx }}][notes]" class="form-control form-control-sm" placeholder="e.g. Extra scrap">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.odoo-form-ui type="input" label="{{ __('production.requisition_notes_reason') }}" name="notes" placeholder="Reason for additional material request..." />

        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="button" class="btn btn-primary" onclick="submitAdHocForm()">{{ __('production.submit_requisition') }}</button>
        </x-slot>
    </x-ui.modal>

    <script>
        function submitAdHocForm() {
            document.querySelectorAll('#additionalMaterialForm tbody tr').forEach(row => {
                const chk = row.querySelector('.item-checkbox');
                if (chk && !chk.checked) {
                    row.querySelectorAll('input').forEach(i => i.disabled = true);
                }
            });
            document.getElementById('additionalMaterialForm').submit();
        }
    </script>

    {{-- Add Cost Adjustment Modal --}}
    <x-ui.modal id="addCostAdjustmentModal" title="{{ __('production.add_manual_cost_adjustment') }}" size="lg" class="text-start" :showFooter="true">
        <form method="POST" action="{{ route('production.orders.cost-adjustments.store', $order->id) }}" enctype="multipart/form-data" id="addCostAdjustmentForm">
            @csrf
            
            <div class="row g-3">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="{{ __('production.date') }}" name="adjustment_date" inputType="date" value="{{ now()->toDateString() }}" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('production.cost_component') }}" name="cost_component" :required="true">
                        @foreach($costComponents as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="{{ __('production.category') }}" name="category" :required="true">
                        @foreach($categories as $catKey => $catLabel)
                            <option value="{{ $catKey }}">{{ $catLabel }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="{{ __('production.amount') }} ({{ active_currency_symbol() }})" name="amount" inputType="number" step="0.01" min="0.01" placeholder="0.00" :required="true" />
                </div>
            </div>

            <x-ui.odoo-form-ui type="input" label="{{ __('production.description') }}" name="description" placeholder="Brief explanation of manual expense" :required="true" />

            <div class="mb-3">
                <label class="form-label fs-12 fw-semibold text-dark">{{ __('production.attachment_label') }}</label>
                <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip">
                <small class="text-muted fs-11">{{ __('production.supported_formats') }}</small>
            </div>

            <x-ui.odoo-form-ui type="textarea" label="{{ __('production.remarks') }}" name="notes" placeholder="Additional details or remarks..." rows="2" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('addCostAdjustmentForm').submit();">{{ __('production.save_cost_adjustment') }}</button>
        </x-slot>
    </x-ui.modal>

    {{-- Edit Cost Adjustment Modals --}}
    @foreach($costAdjustments as $adj)
        <x-ui.modal id="editCostAdjustmentModal{{ $adj->id }}" title="{{ __('production.edit_cost_adjustment', ['id' => $adj->id]) }}" size="lg" class="text-start" :showFooter="true">
            <form method="POST" action="{{ route('production.cost-adjustments.update', $adj->id) }}" enctype="multipart/form-data" id="editCostAdjustmentForm{{ $adj->id }}">
                @csrf
                @method('PUT')
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="{{ __('production.date') }}" name="adjustment_date" inputType="date" value="{{ $adj->adjustment_date ? $adj->adjustment_date->format('Y-m-d') : '' }}" :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="{{ __('production.cost_component') }}" name="cost_component" :required="true">
                            @foreach($costComponents as $key => $label)
                                <option value="{{ $key }}" {{ $adj->cost_component === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="{{ __('production.category') }}" name="category" :required="true">
                            @foreach($categories as $catKey => $catLabel)
                                <option value="{{ $catKey }}" {{ $adj->category === $catKey ? 'selected' : '' }}>{{ $catLabel }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="{{ __('production.amount') }} ({{ active_currency_symbol() }})" name="amount" inputType="number" step="0.01" min="0.01" value="{{ number_format(convert_from_base($adj->amount), 2, '.', '') }}" :required="true" />
                    </div>
                </div>


                <x-ui.odoo-form-ui type="input" label="{{ __('production.description') }}" name="description" value="{{ $adj->description }}" :required="true" />

                <div class="mb-3">
                    <label class="form-label fs-12 fw-semibold text-dark">{{ __('production.attachment_label') }}</label>
                    <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip">
                    @if($adj->attachment_path)
                        <small class="text-success d-block mt-1">Existing file uploaded. Selecting a new file will replace it.</small>
                    @endif
                </div>

                <x-ui.odoo-form-ui type="textarea" label="{{ __('production.remarks') }}" name="notes" value="{{ $adj->notes }}" rows="2" />
            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('editCostAdjustmentForm{{ $adj->id }}').submit();">{{ __('production.update_adjustment') }}</button>
            </x-slot>
        </x-ui.modal>
    @endforeach

</div>{{-- end .erp-single-panel --}}

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Synchronize URL query parameter & active classes when user switches tabs (desktop & mobile)
        document.querySelectorAll('#productionOrderVerticalTabs, #mobileProductionOrderTabs').forEach(tabContainer => {
            tabContainer.addEventListener('click', function(e) {
                const button = e.target.closest('button[data-bs-toggle="pill"], button[data-bs-toggle="tab"]');
                if (button) {
                    const targetId = button.getAttribute('data-bs-target')?.replace('#', '');
                    if (targetId) {
                        const url = new URL(window.location.href);
                        url.searchParams.set('tab', targetId);
                        window.history.replaceState(null, '', url.toString());

                        // Sync active class across both vertical (desktop) and horizontal (mobile) tab buttons
                        document.querySelectorAll(`[data-bs-target="#${targetId}"]`).forEach(btn => {
                            btn.classList.add('active');
                            btn.setAttribute('aria-selected', 'true');
                        });
                        document.querySelectorAll(`[data-bs-target]:not([data-bs-target="#${targetId}"])`).forEach(btn => {
                            if (btn.closest('#productionOrderVerticalTabs, #mobileProductionOrderTabs')) {
                                btn.classList.remove('active');
                                btn.setAttribute('aria-selected', 'false');
                            }
                        });
                    }
                }
            });
        });

        // 2. Automatically attach current active tab to all form submissions on the page
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const currentTab = new URLSearchParams(window.location.search).get('tab');
                if (currentTab) {
                    let hiddenInput = form.querySelector('input[name="tab"]');
                    if (!hiddenInput) {
                        hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'tab';
                        form.appendChild(hiddenInput);
                    }
                    hiddenInput.value = currentTab;
                }
            });
        });

        // 3. Handle initial page load from URL parameter or adjustments_page
        const urlParams = new URLSearchParams(window.location.search);
        const activeTabFromUrl = urlParams.get('tab') || (urlParams.has('adjustments_page') ? 'vtab-cost-adjustments' : null);
        if (activeTabFromUrl) {
            document.querySelectorAll(`[data-bs-target="#${activeTabFromUrl}"]`).forEach(activeBtn => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    bootstrap.Tab.getOrCreateInstance(activeBtn).show();
                } else {
                    activeBtn.click();
                }
            });
        }
    });
</script>
@endsection
