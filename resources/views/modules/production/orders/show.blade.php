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
            overflow-x: auto;
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

        .table-responsive {
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

@section('page-back-button')
    <x-ui.icon-btn href="{{ route('production.orders.index') }}" icon="feather-arrow-left" variant="transparent-dark"
        title="{{ __('production.back_to_list') }}" />
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        {{-- Print Order Label Button --}}
        <x-ui.button href="{{ route('production.labels.orders.print', $order->id) }}" target="_blank"
            icon="feather-printer me-1" variant="outline-secondary" size="sm">
            {{ __('production.print_label') }}
        </x-ui.button>

        @if($order->isDraft())
            @php
                $latestSlip = $order->requisitionSlips->last();
                $slipStatusLower = strtolower($latestSlip?->status ?? '');
                $hasIssuedMaterial = in_array($slipStatusLower, ['fully issued', 'partially issued', 'completed', 'issued', 'partial']);
            @endphp

            @if($hasIssuedMaterial)
                {{-- Release Order Button (Enabled) --}}
                <form method="POST" action="{{ route('production.orders.release', $order->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1.5">
                        <i class="feather-play-circle"></i> {{ __('production.release_order') }}
                    </button>
                </form>
            @else
                {{-- Release Order Button (Disabled until store issues raw materials) --}}
                <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Material issue required: Store must issue raw materials (fully or partially) before releasing order to shopfloor.">
                    <button type="button" class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1.5 opacity-65" disabled>
                        <i class="feather-lock"></i> {{ __('production.release_order') }}
                    </button>
                </span>
            @endif

            {{-- Grouped Header Actions Dropdown --}}
            <x-ui.action-dropdown id="headerActionsDropdownDraft">
                <li>
                    <a href="{{ route('production.orders.edit', $order->id) }}" class="dropdown-item py-1.5 fs-12">
                        <i class="feather-edit me-2 text-primary fs-12"></i>{{ __('production.edit_order') }}
                    </a>
                </li>
                <li>
                    <form method="POST" action="{{ route('production.orders.destroy', $order->id) }}"
                        onsubmit="return confirm('{{ __('production.confirm_delete_draft') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger py-1.5 fs-12">
                            <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_order') }}
                        </button>
                    </form>
                </li>
            </x-ui.action-dropdown>
        @endif

        @if($order->isReleased() || $order->isInProgress())
            @if($order->schedules->isEmpty())
                {{-- Generate Schedule Button --}}
                <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal"
                    data-bs-target="#scheduleModal">
                    <i class="feather-calendar"></i> {{ __('production.generate_schedule') }}
                </button>
            @endif

            {{-- Grouped Actions Dropdown --}}
            <x-ui.action-dropdown id="headerActionsDropdown">
                <li>
                    <form method="POST" action="{{ route('production.orders.complete', $order->id) }}"
                        onsubmit="return confirm('{{ __('production.confirm_complete_order') }}');">
                        @csrf
                        <button type="submit" class="dropdown-item py-1.5 fs-12 text-success">
                            <i class="feather-check-circle me-2 text-success fs-12"></i>{{ __('production.complete_order') }}
                        </button>
                    </form>
                </li>
                <li>
                    <hr class="dropdown-divider my-1">
                </li>
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal"
                        data-bs-target="#progressModal">
                        <i class="feather-edit-3 me-2 text-primary fs-12"></i>{{ __('production.log_progress') }}
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal"
                        data-bs-target="#issueModal">
                        <i class="feather-log-in me-2 text-info fs-12"></i>{{ __('production.issue_materials') }}
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal"
                        data-bs-target="#returnModal">
                        <i class="feather-log-out me-2 text-secondary fs-12"></i>{{ __('production.return_materials') }}
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal"
                        data-bs-target="#receiptModal">
                        <i class="feather-download me-2 text-warning fs-12"></i>{{ __('production.receive_fg') }}
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="dropdown-item py-1.5 fs-12" data-bs-toggle="modal"
                        data-bs-target="#scrapReworkModal">
                        <i class="feather-alert-triangle me-2 text-warning fs-12"></i>{{ __('production.log_scrap_rework') }}
                    </a>
                </li>
                <li>
                    <hr class="dropdown-divider my-1">
                </li>
                <li>
                    <form method="POST" action="{{ route('production.orders.cancel', $order->id) }}"
                        onsubmit="return confirm('{{ __('production.confirm_cancel_order') }}');">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger py-1.5 fs-12">
                            <i class="feather-slash me-2 text-danger fs-12"></i>{{ __('production.cancel_order') }}
                        </button>
                    </form>
                </li>
            </x-ui.action-dropdown>
        @endif

        @if($order->isCompleted())
            {{-- Close & Archive Order Button --}}
            <form method="POST" action="{{ route('production.orders.close', $order->id) }}"
                onsubmit="return confirm('{{ __('production.confirm_close_order') }}');"
                class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-secondary d-inline-flex align-items-center gap-1.5">
                    <i class="feather-archive"></i> {{ __('production.close_archive_order') }}
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')

    {{-- ── Workflow Next Step Guidance Component (Placed outside panel, matching mockup) ── --}}
    <x-ui.workflow-guide title="What's Next?">
        @if($order->isDraft())
            Requisition request has been sent to Store. Track material issue under <a href="?tab=vtab-reservations"
                class="fw-bold text-primary text-decoration-underline">Material Reservations</a>. Once items are issued by
            Store, click <span class="badge bg-soft-success text-success border border-success-subtle fw-semibold">Release
                Order</span>.
        @elseif($order->isReleased())
            @php
                $existingSchedule = $order->schedules->whereNotIn('status', ['cancelled'])->first();
                $hasOperator = $order->operations->contains(function ($op) {
                    return !is_null($op->operator_id) || ($op->operatorAssignments && $op->operatorAssignments->isNotEmpty());
                });
            @endphp
            @if($hasOperator)
                Operators assigned to operations. You can see assigned operation tasks under <a
                    href="{{ route('production.mes.operator.dashboard') }}"
                    class="fw-bold text-primary text-decoration-underline">MES Operator Console</a>.
            @elseif($existingSchedule)
                The schedule <a href="{{ route('production.schedules.show', $existingSchedule->id) }}"
                    class="fw-bold text-primary text-decoration-underline">[{{ $existingSchedule->schedule_number }}]</a> has been
                generated for this order. You can assign operators under <a href="?tab=vtab-operations"
                    class="fw-bold text-primary text-decoration-underline">Operations Routing Tab</a> to begin execution.
            @else
                Order released. Click <a href="javascript:void(0)" class="fw-bold text-primary text-decoration-underline"
                    data-bs-toggle="modal" data-bs-target="#scheduleModal">{{ __('production.generate_schedule') }}</a> to plan work centers.
            @endif
        @elseif($order->isInProgress())
            Production is active on shop floor. Track live progress in <a href="?tab=vtab-wip"
                class="fw-bold text-primary text-decoration-underline">WIP Tracking</a> or <a
                href="{{ route('production.mes.dashboard') }}" class="fw-bold text-primary text-decoration-underline">Shop Floor
                (MES)</a>. Once finished goods are ready, log output & complete the order.
        @elseif($order->isCompleted())
            Order completed. Final output received. Review cost variances under <a href="?tab=vtab-cost"
                class="fw-bold text-primary text-decoration-underline">Cost Analysis</a> and close the order when ready.
        @else
            Order Status: {{ ucfirst($order->status) }}
        @endif
    </x-ui.workflow-guide>

    @php
        $latestSlip = $order->requisitionSlips->last();
        $slipStatusLower = strtolower($latestSlip?->status ?? '');
        $isPartiallyIssued = in_array($slipStatusLower, ['partially issued', 'partial']);
    @endphp

    @if($isPartiallyIssued)
        <div class="alert alert-warning border border-warning shadow-sm mb-3 d-flex align-items-center justify-content-between gap-3 rounded-3 p-3" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="feather-alert-circle fs-18 text-warning-emphasis"></i>
                <div>
                    <strong class="text-warning-emphasis">Store Material Partially Issued:</strong>
                    <span class="fs-13 text-dark">Raw materials for this Production Order have been partially issued by the store. Track issued and remaining items under <a href="?tab=vtab-procurement" class="fw-bold text-dark text-decoration-underline">Procurement & Requisitions</a>.</span>
                </div>
            </div>
            <a href="?tab=vtab-procurement" class="btn btn-sm btn-warning text-dark fw-bold px-3 shadow-sm">
                View Material Status
            </a>
        </div>
    @endif

    <div class="erp-single-panel bg-white">

        {{-- ── Header Identity Row ──────────────────────────────────────────── --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold text-dark mb-0">{{ __('production.production_order') }} ({{ $order->order_number }})</h4>
                @php
                    $model = $order->production_model ?? 'pure_manufacturing';
                @endphp
                @if($model === 'subcontract_complete')
                    <span class="badge bg-soft-warning text-warning border border-warning-subtle ms-2"><i class="feather-truck me-1"></i>Complete Subcontracting</span>
                @elseif($model === 'subcontract_company_material')
                    <span class="badge bg-soft-primary text-primary border border-primary-subtle ms-2"><i class="feather-package me-1"></i>Company Material Subcontracting</span>
                @elseif($model === 'hybrid')
                    <span class="badge bg-soft-success text-success border border-success-subtle ms-2"><i class="feather-cpu me-1"></i>Hybrid Manufacturing + Subcontracting</span>
                @else
                    <span class="badge bg-soft-info text-info border border-info-subtle ms-2"><i class="feather-settings me-1"></i>Pure Manufacturing</span>
                @endif
            </div>
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
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('production.finished_product') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $order->product->name }}
                            ({{ $order->product->sku }})</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('production.bom_reference') }}:</span></div>
                    <div class="col-md-8">
                        <a href="{{ route('production.boms.show', $order->bom_id ?? 0) }}"
                            class="fw-bold text-primary fs-13">
                            {{ $order->bom->bom_number ?? 'N/A' }} (v{{ $order->bom->version ?? '—' }})
                        </a>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('production.routing_reference') }}:</span></div>
                    <div class="col-md-8">
                        <a href="{{ route('production.routing.show', $order->routing_id ?? 0) }}"
                            class="fw-bold text-primary fs-13">
                            {{ $order->routing->routing_number ?? 'N/A' }} — {{ $order->routing->name ?? '' }}
                            (v{{ $order->routing->version ?? '—' }})
                        </a>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('production.source_plan') }}:</span></div>
                    <div class="col-md-8">
                        @if($order->plan)
                            <a href="{{ route('production.plans.show', $order->production_plan_id) }}"
                                class="fw-bold text-primary fs-13">
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
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('production.quantity_ordered') }}:</span></div>
                    <div class="col-md-8"><span
                            class="text-dark fw-bold fs-13">{{ number_format($order->quantity_ordered, 2) }} units</span>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('production.quantity_produced') }}:</span></div>
                    <div class="col-md-8">
                        @php $progressPct = $order->quantity_ordered > 0 ? min(100.0, ($order->quantity_produced / $order->quantity_ordered) * 100) : 0.0; @endphp
                        <span class="text-success fw-bold fs-13">{{ number_format($order->quantity_produced, 2) }}
                            units</span>
                        <div class="progress mt-1" style="height:5px;">
                            <div class="progress-bar bg-success" style="width:{{ $progressPct }}%;"></div>
                        </div>
                        <div class="text-muted fs-11 mt-1">{{ round($progressPct, 1) }}% {{ __('production.completed') }}
                        </div>
                    </div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('production.scheduled_dates') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $order->start_date->format('Y-m-d') }} →
                            {{ $order->end_date->format('Y-m-d') }}</span></div>
                </div>
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4"><span
                            class="fw-semibold text-muted fs-13">{{ __('production.created_by') }}:</span></div>
                    <div class="col-md-8"><span class="text-dark fw-bold fs-13">{{ $order->creator->name ?? 'System' }} at
                            {{ $order->created_at->format('Y-m-d H:i') }}</span></div>
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
                ['id' => 'vtab-overview', 'label' => __('production.overview'), 'active' => $activeTab === 'vtab-overview', 'icon' => 'feather-activity'],
                ['id' => 'vtab-operations', 'label' => __('production.operations_routing'), 'active' => $activeTab === 'vtab-operations', 'icon' => 'feather-cpu'],
                ['id' => 'vtab-component-plan', 'label' => 'Component Plan', 'active' => $activeTab === 'vtab-component-plan', 'icon' => 'feather-grid'],
            ];

            if ($order->production_model !== 'pure_manufacturing' || $order->operations->contains('is_external', true)) {
                $verticalTabs[] = ['id' => 'vtab-subcontract', 'label' => 'Subcontracting & Vendor WIP', 'active' => $activeTab === 'vtab-subcontract', 'icon' => 'feather-truck'];
            }

            $verticalTabs[] = ['id' => 'vtab-wip', 'label' => __('production.wip_tracking'), 'active' => $activeTab === 'vtab-wip', 'icon' => 'feather-layers'];

            if ($order->production_mode === 'batch') {
                $verticalTabs[] = ['id' => 'vtab-batches', 'label' => __('production.production_batches'), 'active' => $activeTab === 'vtab-batches', 'icon' => 'feather-box'];
            } elseif ($order->production_mode === 'serial') {
                $verticalTabs[] = ['id' => 'vtab-serials', 'label' => __('production.serial_numbers'), 'active' => $activeTab === 'vtab-serials', 'icon' => 'feather-hash'];
            } elseif ($order->production_mode === 'batch_and_serial') {
                $verticalTabs[] = ['id' => 'vtab-batches', 'label' => __('production.batches_and_serials'), 'active' => $activeTab === 'vtab-batches', 'icon' => 'feather-box'];
            }

            $verticalTabs = array_merge($verticalTabs, [
                ['id' => 'vtab-reservations', 'label' => __('production.material_reservations'), 'active' => $activeTab === 'vtab-reservations', 'icon' => 'feather-archive'],
                ['id' => 'vtab-issues', 'label' => __('production.material_issues'), 'active' => $activeTab === 'vtab-issues', 'icon' => 'feather-arrow-up-right'],
                ['id' => 'vtab-progress', 'label' => __('production.progress_logs'), 'active' => $activeTab === 'vtab-progress', 'icon' => 'feather-clock'],
                ['id' => 'vtab-scrap', 'label' => __('production.scrap_rework'), 'active' => $activeTab === 'vtab-scrap', 'icon' => 'feather-alert-triangle'],
                ['id' => 'vtab-cost', 'label' => __('production.cost_analysis'), 'active' => $activeTab === 'vtab-cost', 'icon' => 'feather-pie-chart'],
                ['id' => 'vtab-cost-adjustments', 'label' => __('production.cost_adjustments'), 'active' => $activeTab === 'vtab-cost-adjustments', 'icon' => 'feather-dollar-sign'],
                ['id' => 'vtab-procurement', 'label' => __('production.procurement_requisitions'), 'active' => $activeTab === 'vtab-procurement', 'icon' => 'feather-shopping-cart'],
                ['id' => 'vtab-audit', 'label' => __('production.audit_trail_events'), 'active' => $activeTab === 'vtab-audit', 'icon' => 'feather-file-text'],
            ]);
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
                <div class="tab-content" style="background-color: white; padding: 10px;"
                    id="productionOrderVerticalTabsContent">

                    {{-- Tab 1: Overview --}}
                    <div class="tab-pane fade {{ $activeTab === 'vtab-overview' ? 'show active' : '' }}" id="vtab-overview"
                        role="tabpanel" aria-labelledby="vtab-overview-tab">
                        <div class="row g-4">
                            <div class="col-md-8">
                                <h5 class="fw-bold text-dark mb-3">{{ __('production.remarks_notes') }}</h5>
                                <p class="text-dark fs-13">{{ $order->description ?? __('production.no_remarks_logged') }}
                                </p>

                                <h6 class="fw-bold text-muted text-uppercase fs-11 mb-3 mt-4">
                                    {{ __('production.actual_execution_timeline') }}</h6>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded">
                                            <div class="text-muted fs-11 text-uppercase mb-1">
                                                {{ __('production.scheduled_window') }}</div>
                                            <div class="text-dark fw-bold fs-14">
                                                {{ $order->start_date->format('Y-m-d') }} →
                                                {{ $order->end_date->format('Y-m-d') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded">
                                            <div class="text-muted fs-11 text-uppercase mb-1">
                                                {{ __('production.actual_execution_dates') }}</div>
                                            <div class="text-dark fw-bold fs-14">
                                                {{ $order->actual_start_date ? $order->actual_start_date->format('Y-m-d H:i') : '—' }}
                                                →
                                                {{ $order->actual_end_date ? $order->actual_end_date->format('Y-m-d H:i') : '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-muted text-uppercase fs-11 mb-3 mt-4">
                                    {{ __('production.production_schedules') ?? 'Production Schedules' }}</h6>
                                @if($order->schedules && $order->schedules->isNotEmpty())
                                    <x-ui.table :bordered="true" :hoverable="true" class="fs-12 text-dark">
                                        <thead>
                                            <tr>
                                                <th>Schedule #</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Scheduled At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($order->schedules as $schedule)
                                                <tr>
                                                    <td class="fw-bold font-monospace">{{ $schedule->schedule_number }}</td>
                                                    <td>{{ ucfirst($schedule->scheduling_type) }}</td>
                                                    <td>
                                                        <span
                                                            class="badge bg-soft-{{ $schedule->status === 'released' ? 'success' : ($schedule->status === 'cancelled' ? 'danger' : 'secondary') }} text-{{ $schedule->status === 'released' ? 'success' : ($schedule->status === 'cancelled' ? 'danger' : 'secondary') }}">
                                                            {{ ucfirst($schedule->status) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $schedule->scheduled_at ? $schedule->scheduled_at->format('Y-m-d H:i') : '—' }}
                                                    </td>
                                                    <td>
                                                        <x-ui.action-dropdown :viewUrl="route('production.schedules.show', $schedule->id)" />
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </x-ui.table>
                                @else
                                    <div class="alert alert-warning py-2 px-3 fs-12 mb-0">
                                        <i class="feather-alert-triangle me-1"></i> No schedule has been generated for this production order yet.
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-4">
                                <h5 class="fw-bold text-dark mb-3">{{ __('production.frozen_engineering_references') }}</h5>
                                <div class="mb-3 pb-2 border-bottom">
                                    <div class="text-muted fs-11 text-uppercase mb-1">
                                        {{ __('production.bom_version_frozen') }}</div>
                                    <a href="{{ route('production.boms.show', $order->bom_id ?? 0) }}"
                                        class="fw-bold text-primary">
                                        {{ $order->bom->bom_number ?? __('production.bom_reference') }}
                                        (v{{ $order->bom->version ?? '1.0' }})
                                    </a>
                                    <div class="fs-12 text-muted mt-1">{{ $order->bom->bom_name ?? 'Default BOM' }}</div>
                                </div>
                                <div class="mb-3 pb-2 border-bottom">
                                    <div class="text-muted fs-11 text-uppercase mb-1">
                                        {{ __('production.routing_version_frozen') }}</div>
                                    <a href="{{ route('production.routing.show', $order->routing_id ?? 0) }}"
                                        class="fw-bold text-primary">
                                        {{ $order->routing->routing_number ?? __('production.routing_reference') }}
                                    </a>
                                    <div class="fs-12 text-muted mt-1">{{ $order->routing->name ?? 'Default Routing' }}
                                        (v{{ $order->routing->version ?? '1.0' }})</div>
                                </div>
                                <div>
                                    <div class="text-muted fs-11 text-uppercase mb-1">{{ __('production.source_plan') }}
                                    </div>
                                    @if($order->plan)
                                        <a href="{{ route('production.plans.show', $order->production_plan_id) }}"
                                            class="fw-bold text-primary">
                                            {{ $order->plan->plan_number }}
                                        </a>
                                    @else
                                        <span class="text-dark fw-bold">{{ __('production.direct_order_no_plan') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>



                    {{-- Tab 2.5: Subcontracting & Vendor WIP --}}
                    @if($order->production_model !== 'pure_manufacturing' || $order->operations->contains('is_external', true))
                        <div class="tab-pane fade {{ $activeTab === 'vtab-subcontract' ? 'show active' : '' }}" id="vtab-subcontract"
                            role="tabpanel" aria-labelledby="vtab-subcontract-tab">
                            @php
                                $subcost = app(\App\Domains\Production\Services\ProductionCostService::class)->calculateSubcontractCost($order);
                                $user = auth()->user();
                                $accessService = app(\App\Services\Access\AccessService::class);
                                $canPurchase = $user && ($accessService->allows($user, 'purchase.orders.view') || $accessService->allows($user, 'purchase.requisitions.view'));
                                $canQuality = $user && $accessService->allows($user, 'production.quality.view');
                                $canInventory = $user && $accessService->allows($user, 'inventory.transfers.view');
                                $matBalanceService = app(\App\Domains\Production\Services\SubcontractMaterialBalanceService::class);
                            @endphp

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1"><i class="feather-truck text-primary me-2"></i>Subcontracting & Vendor WIP Execution</h5>
                                    <span class="fs-12 text-muted">Turnkey procurement, vendor dispatches, GRN receipts, quality clearance, and subcontract costing.</span>
                                </div>
                                <div class="d-flex gap-2">
                                     <x-ui.button href="{{ route('production.subcontract.delivery-challans.index') }}" variant="outline-info" size="sm" icon="feather-truck me-1">
                                         Delivery Challans
                                     </x-ui.button>
                                    @if($canPurchase && \Illuminate\Support\Facades\Route::has('purchase.orders.index'))
                                        <x-ui.button href="{{ route('purchase.orders.index') }}" variant="outline-primary" size="sm" icon="feather-shopping-cart me-1">
                                            Purchase Orders
                                        </x-ui.button>
                                    @endif
                                    @if($canQuality && \Illuminate\Support\Facades\Route::has('production.quality.rework.index'))
                                        <x-ui.button href="{{ route('production.quality.rework.index') }}" variant="outline-warning" size="sm" icon="feather-shield me-1">
                                            Quality & Rework
                                        </x-ui.button>
                                    @endif
                                </div>
                            </div>

                            {{-- Top Summary Cards --}}
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card shadow-sm border p-3 bg-light h-100">
                                        <span class="fs-11 text-uppercase text-muted fw-bold">Subcontract Model</span>
                                        <div class="fs-14 fw-bold text-dark mt-1">{{ str_replace('_', ' ', ucfirst($order->effective_production_model ?? $order->production_model ?? 'subcontract')) }}</div>
                                        <small class="text-muted fs-11 mt-1">{{ $order->operations->where('is_external', true)->count() }} outsourced step(s)</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card shadow-sm border p-3 bg-soft-primary h-100">
                                        <span class="fs-11 text-uppercase text-primary fw-bold">Authoritative Cost</span>
                                        <div class="fs-18 fw-bold text-primary mt-1">{{ format_currency($subcost['authoritative'] ?? 0) }}</div>
                                        <small class="text-muted fs-11 mt-1">Lifecycle: {{ ($subcost['actual'] ?? 0) > 0 ? 'Actual Vendor Bill' : (($subcost['committed'] ?? 0) > 0 ? 'PO Committed' : 'Routing Estimate') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card shadow-sm border p-3 bg-light h-100">
                                        <span class="fs-11 text-uppercase text-muted fw-bold">Cost Breakdown</span>
                                        <div class="fs-12 text-dark mt-1">
                                            <div>Estimate: <strong>{{ format_currency($subcost['estimated'] ?? 0) }}</strong></div>
                                            <div>Committed: <strong>{{ format_currency($subcost['committed'] ?? 0) }}</strong></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card shadow-sm border p-3 bg-soft-warning h-100">
                                        <span class="fs-11 text-uppercase text-warning fw-bold">Vendor WIP Status</span>
                                        @php
                                            $vendorWipUnits = $order->wips->where('currentRoutingOperation.is_external', true)->sum('quantity_available');
                                        @endphp
                                        <div class="fs-18 fw-bold text-dark mt-1">{{ number_format($vendorWipUnits, 2) }} <small class="fs-12 text-muted">units at vendor</small></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Outsourced Operations Table --}}
                            <div class="card border shadow-sm mb-4">
                                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold text-dark mb-0"><i class="feather-list me-1 text-primary"></i>Outsourced Operations & Vendor Status</h6>
                                    <span class="badge bg-soft-secondary text-secondary fs-11">{{ $order->operations->where('is_external', true)->count() }} Operation(s)</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0 fs-12 table-hover">
                                        <thead class="bg-light text-uppercase fs-11 text-muted">
                                            <tr>
                                                <th>Op #</th>
                                                <th>Operation</th>
                                                <th>Vendor & Supply Type</th>
                                                <th>Lead & Buffers</th>
                                                <th>PR Reference</th>
                                                <th>PO Reference</th>
                                                <th>Timing (Planned / Actual)</th>
                                                <th>Quantity & QC Status</th>
                                                <th>Subcontract Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($order->operations->where('is_external', true) as $extOp)
                                                @php
                                                    $poItem = $extOp->purchaseOrderItem ?? \App\Domains\Purchase\Models\PurchaseOrderItem::where('production_order_operation_id', $extOp->id)->first();
                                                    $po = $poItem?->purchaseOrder ?? $extOp->purchaseOrder;
                                                    $prItem = \App\Domains\Purchase\Models\PurchaseRequisitionItem::whereHas('requisition', function($q) use ($order) {
                                                        $q->whereIn('source_type', ['mo', 'ProductionOrder'])->where('source_id', $order->id);
                                                    })->first();
                                                    $pr = $prItem?->requisition ?? \App\Domains\Purchase\Models\PurchaseRequisition::whereIn('source_type', ['mo', 'ProductionOrder'])->where('source_id', $order->id)->first();

                                                    $opWips = $order->wips->where('current_routing_operation_id', $extOp->id);
                                                    $sentQty = $extOp->quantity_transferred_out ?? 0;
                                                    $atVendorQty = $opWips->sum('quantity_available');
                                                    $receivedQty = $extOp->quantity_produced ?? 0;
                                                    $rejectedQty = $extOp->quantity_rejected ?? 0;
                                                    $scrappedQty = $extOp->quantity_scrapped ?? 0;
                                                    $qcPending = $order->wips->where('current_routing_operation_id', $extOp->id)->where('status', 'quality_hold')->sum('quantity_available');

                                                    $plannedDispatch = $order->start_date ? \Carbon\Carbon::parse($order->start_date)->subDays($extOp->dispatch_buffer_days ?? 0)->format('d/m/Y') : '—';
                                                    $actualDispatch = $extOp->actual_start_time ? $extOp->actual_start_time->format('d/m/Y H:i') : '—';
                                                    $expectedReturn = $order->start_date ? \Carbon\Carbon::parse($order->start_date)->addDays(($extOp->subcontract_lead_time_days ?? 0) + ($extOp->return_buffer_days ?? 0))->format('d/m/Y') : '—';
                                                    $actualReturn = $extOp->actual_end_time ? $extOp->actual_end_time->format('d/m/Y H:i') : '—';
                                                @endphp
                                                <tr>
                                                    <td class="fw-bold font-monospace align-top">{{ $extOp->operation_number }}</td>
                                                    <td class="align-top">
                                                        <div class="fw-bold text-dark">{{ $extOp->name }}</div>
                                                        <span class="badge bg-soft-secondary text-secondary fs-10 mt-1">{{ ucfirst($extOp->status ?? 'ready') }}</span>
                                                    </td>
                                                    <td class="align-top">
                                                         <div class="fw-bold text-dark"><i class="feather-truck me-1 text-primary"></i>{{ $extOp->vendor->name ?? 'Subcontractor' }}</div>
                                                         @if(($extOp->material_supply_type ?? 'company_supplied') === 'vendor_supplied')
                                                             <span class="badge bg-soft-info text-info border border-info-subtle fs-10 mt-1"><i class="feather-box me-1"></i>Vendor Supplied</span>
                                                         @else
                                                             <span class="badge bg-soft-warning text-dark border border-warning-subtle fs-10 mt-1"><i class="feather-truck me-1"></i>Company Supplied</span>
                                                         @endif
                                                    </td>
                                                    <td class="align-top font-monospace fs-11">
                                                        <div>Lead: <strong>{{ $extOp->subcontract_lead_time_days ?? 0 }}d</strong></div>
                                                        <div class="text-muted">Buffers: {{ $extOp->dispatch_buffer_days ?? 0 }}d disp / {{ $extOp->return_buffer_days ?? 0 }}d ret</div>
                                                    </td>
                                                    <td class="align-top">
                                                        @if($pr)
                                                            @if($canPurchase && \Illuminate\Support\Facades\Route::has('purchase.requisitions.show'))
                                                                <a href="{{ route('purchase.requisitions.show', $pr->id) }}" class="fw-bold text-primary">
                                                                    {{ $pr->requisition_number }}
                                                                </a>
                                                            @else
                                                                <span class="fw-bold text-dark">{{ $pr->requisition_number }}</span>
                                                            @endif
                                                            <div class="fs-10"><span class="badge bg-soft-info text-info">{{ ucfirst($pr->status) }}</span></div>
                                                        @else
                                                            <div class="mb-1"><span class="badge bg-soft-secondary text-secondary">Awaiting PR</span></div>
                                                            @if($canPurchase && \Illuminate\Support\Facades\Route::has('production.orders.generate-subcontract-pr'))
                                                                <form action="{{ route('production.orders.generate-subcontract-pr', ['order' => $order->id, 'operation' => $extOp->id]) }}" method="POST" class="d-inline">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-xs btn-outline-primary py-0.5 px-1.5 shadow-sm" title="Generate Purchase Requisition">
                                                                        <i class="feather-plus-circle me-1"></i> Generate PR
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="align-top">
                                                         @if($po)
                                                             @if($canPurchase && \Illuminate\Support\Facades\Route::has('purchase.orders.show'))
                                                                 <a href="{{ route('purchase.orders.show', $po->id) }}" class="fw-bold text-primary">
                                                                     {{ $po->purchase_order_number ?? $po->po_number }}
                                                                 </a>
                                                             @else
                                                                 <span class="fw-bold text-dark">{{ $po->purchase_order_number ?? $po->po_number }}</span>
                                                             @endif
                                                             <div class="fs-10"><span class="badge bg-soft-success text-success">{{ ucfirst($po->status) }}</span></div>
                                                         @else
                                                             <span class="text-muted fs-11">Awaiting PO</span>
                                                         @endif
                                                    </td>
                                                    <td class="align-top fs-11 font-monospace">
                                                         @php
                                                             $expectedReturnDate = $order->start_date ? \Carbon\Carbon::parse($order->start_date)->addDays(($extOp->subcontract_lead_time_days ?? 0) + ($extOp->return_buffer_days ?? 0)) : null;
                                                             $isOverdue = $expectedReturnDate && $expectedReturnDate->isPast() && !in_array($extOp->status, ['completed', 'cancelled']);
                                                             $isDueToday = $expectedReturnDate && $expectedReturnDate->isToday() && !in_array($extOp->status, ['completed', 'cancelled']);
                                                         @endphp
                                                         <div>Disp: {{ $plannedDispatch }} <small class="text-muted">({{ $actualDispatch }})</small></div>
                                                         <div>Ret: {{ $expectedReturn }} <small class="text-muted">({{ $actualReturn }})</small></div>
                                                         @if($isOverdue)
                                                             <span class="badge bg-danger text-white fs-10 mt-1" title="Vendor return date exceeded expected lead time"><i class="feather-alert-circle me-1"></i>Overdue</span>
                                                         @elseif($isDueToday)
                                                             <span class="badge bg-warning text-dark fs-10 mt-1" title="Vendor return expected today"><i class="feather-clock me-1"></i>Due Today</span>
                                                         @elseif($extOp->status === 'completed')
                                                             <span class="badge bg-soft-success text-success fs-10 mt-1"><i class="feather-check-circle me-1"></i>On Time</span>
                                                         @endif
                                                    </td>
                                                    <td class="align-top fs-11">
                                                         <div>Req: <strong>{{ number_format($order->quantity_ordered, 2) }}</strong> | Rec: <strong class="text-success">{{ number_format($receivedQty, 2) }} / {{ number_format($order->quantity_ordered, 2) }}</strong></div>
                                                         <div>At Vendor: <strong class="text-warning">{{ number_format($atVendorQty, 2) }}</strong> | QC Pend: <strong class="text-info">{{ number_format($qcPending, 2) }}</strong></div>
                                                         @if($receivedQty > 0 && $receivedQty < $order->quantity_ordered)
                                                             <div class="badge bg-soft-warning text-warning-emphasis border border-warning fs-10 mt-1">
                                                                 Partial Receipt: {{ number_format($receivedQty, 0) }}/{{ number_format($order->quantity_ordered, 0) }} units
                                                             </div>
                                                         @endif
                                                         @if($rejectedQty > 0 || $scrappedQty > 0)
                                                             <div class="text-danger fs-10 fw-bold mt-1">Accepted: {{ number_format($receivedQty - $rejectedQty, 2) }} | Rej: {{ number_format($rejectedQty, 2) }} | Scrap: {{ number_format($scrappedQty, 2) }}</div>
                                                         @endif
                                                    </td>
                                                    <td class="align-top font-monospace fw-bold text-end">
                                                        {{ format_currency($extOp->subcontract_cost_per_unit * $order->quantity_ordered) }}
                                                        <div class="fs-10 text-muted">@ {{ format_currency($extOp->subcontract_cost_per_unit) }}/unit</div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-3">No outsourced operations defined on this order.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Company Material Balance Section (For Company Supplied Materials) --}}
                            @php
                                $hasCompanyMaterialOps = $order->operations->contains(function($op) {
                                    return $op->is_external && ($op->material_supply_type === 'company_supplied' || is_null($op->material_supply_type));
                                });
                            @endphp

                            @if($hasCompanyMaterialOps || $order->production_model === 'subcontract_company_material' || $order->production_model === 'hybrid')
                                <div class="card border shadow-sm mb-4">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="fw-bold text-dark mb-0"><i class="feather-box me-1 text-warning"></i>Company Material Balance at Subcontractor</h6>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0 fs-12">
                                            <thead class="bg-light text-uppercase fs-11 text-muted">
                                                <tr>
                                                    <th>Operation</th>
                                                    <th>Material / Item</th>
                                                    <th class="text-end">Sent to Vendor</th>
                                                    <th class="text-end">Consumed</th>
                                                    <th class="text-end">Returned Unused</th>
                                                    <th class="text-end">Scrapped</th>
                                                    <th class="text-end">Remaining at Vendor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($order->operations->where('is_external', true)->filter(fn($op) => ($op->material_supply_type ?? 'company_supplied') === 'company_supplied') as $extOp)
                                                    @php
                                                        $bal = $matBalanceService->getMaterialBalance($order->tenant_id, $order->id, $extOp->id);
                                                    @endphp
                                                    <tr>
                                                        <td class="fw-bold text-dark">{{ $extOp->operation_number }} — {{ $extOp->name }}</td>
                                                        <td>{{ $order->product->name }} (Component/Raw)</td>
                                                        <td class="text-end font-monospace">{{ number_format($bal['sent'], 2) }}</td>
                                                        <td class="text-end font-monospace text-success">{{ number_format($bal['consumed'], 2) }}</td>
                                                        <td class="text-end font-monospace text-info">{{ number_format($bal['returned'], 2) }}</td>
                                                        <td class="text-end font-monospace text-danger">{{ number_format($bal['scrapped'], 2) }}</td>
                                                        <td class="text-end font-monospace fw-bold text-warning">{{ number_format($bal['remaining'], 2) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7" class="text-center text-muted py-3">No company material balance records found.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Tab 3: WIP Tracking --}}
                    <div class="tab-pane fade {{ $activeTab === 'vtab-wip' ? 'show active' : '' }}" id="vtab-wip"
                        role="tabpanel" aria-labelledby="vtab-wip-tab">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><i class="feather-layers text-primary me-2"></i>
                                    {{ __('production.wip_tracking_status') }}</h5>
                                <span class="fs-12 text-muted">Shop floor work center breakdown, batch progress tracking,
                                    and accrued costing sheets.</span>
                            </div>
                        </div>

                        @if(isset($wipWorkCenterSummaries) && $wipWorkCenterSummaries->isNotEmpty())
                            {{-- Summary Metrics Cards --}}
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card shadow-sm border-0 bg-light p-3">
                                        <span class="fs-11 text-uppercase text-muted fw-bold">Active Shop Floor WIP</span>
                                        <div class="fs-20 fw-bold text-dark mt-1">
                                            {{ number_format($wipWorkCenterSummaries->sum('total_available'), 2) }} <small
                                                class="fs-12 text-muted fw-normal">pcs</small></div>
                                        <small class="text-muted fs-11 mt-1">In-process across
                                            {{ $wipWorkCenterSummaries->count() }} active
                                            {{ Str::plural('work center', $wipWorkCenterSummaries->count()) }}</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card shadow-sm border-0 bg-soft-success p-3">
                                        <span class="fs-11 text-uppercase text-success fw-bold">Completed Output (Ready)</span>
                                        <div class="fs-20 fw-bold text-success mt-1">
                                            {{ number_format($wipWorkCenterSummaries->sum('total_completed'), 2) }} <small
                                                class="fs-12 text-success fw-normal">pcs</small></div>
                                        <small class="text-success fs-11 mt-1">Final stage output ready for FG transfer</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card shadow-sm border-0 bg-light p-3">
                                        <span class="fs-11 text-uppercase text-muted fw-bold">Quality Scrapped / Rejected</span>
                                        <div class="fs-20 fw-bold text-danger mt-1">
                                            {{ number_format($wipWorkCenterSummaries->sum('total_rejected'), 2) }} <small
                                                class="fs-11 text-muted">rej</small> /
                                            {{ number_format($wipWorkCenterSummaries->sum('total_scrap'), 2) }} <small
                                                class="fs-11 text-muted">scrap</small>
                                        </div>
                                        <small class="text-muted fs-11 mt-1">Defects logged across tracking cards</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card shadow-sm border-0 bg-soft-primary p-3">
                                        <span class="fs-11 text-uppercase text-primary fw-bold">Total Accrued WIP Value</span>
                                        <div class="fs-20 fw-bold text-primary mt-1">
                                            {{ format_currency($wipWorkCenterSummaries->sum('accrued_value')) }}</div>
                                        <small class="text-primary fs-11 mt-1">Material + Labor + Machine + Overhead</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Work Center Wise & Batch Wise Progress Tables --}}
                            <div class="mb-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="feather-grid me-2 text-primary"></i> Work Center &
                                    Batch Tracking Progress</h6>

                                @foreach($wipWorkCenterSummaries as $s)
                                    @php
                                        $wcId = $s['work_center_id'];
                                        $tenantId = require_tenant_id();
                                        // Only call paginator for real work centers; null means unassigned WIPs
                                        $initialBatchesPaginator = $wcId !== null
                                            ? app(\App\Domains\Production\Services\ProductionWipService::class)
                                                ->getPaginatedWorkCenterWips($tenantId, $order->id, $wcId, null, null, 5)
                                            : null;
                                    @endphp
                                    @if($wcId === null && $initialBatchesPaginator === null)
                                        @continue
                                    @endif
                                    <div class="card shadow-sm border mb-4">
                                        <div
                                            class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                            <div class="d-flex align-items-center">
                                                <i class="feather-cpu text-primary fs-16 me-2"></i>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0">{{ $s['work_center_name'] }}</h6>
                                                    <span class="fs-11 text-muted">{{ $s['batch_count'] }}
                                                        {{ Str::plural('Batch Card', $s['batch_count']) }} at this Work
                                                        Center</span>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-3 text-end fs-12">
                                                <div>
                                                    <span class="text-muted">Floor Qty:</span>
                                                    <strong
                                                        class="text-dark me-2">{{ number_format($s['total_available'], 2) }}</strong>
                                                </div>
                                                <div>
                                                    <span class="text-muted">Ready Output:</span>
                                                    <strong
                                                        class="text-success me-2">{{ number_format($s['total_completed'], 2) }}</strong>
                                                </div>
                                                <div>
                                                    <span class="text-muted">Total Cost:</span>
                                                    <strong class="text-primary">{{ format_currency($s['accrued_value']) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0 fs-12">
                                                <thead class="bg-light fs-11 text-muted text-uppercase">
                                                    <tr>
                                                        <th>WIP Card #</th>
                                                        <th>Batch #</th>
                                                        <th>Current Stage</th>
                                                        <th class="text-end">Available Qty</th>
                                                        <th class="text-end">Completed Qty</th>
                                                        <th class="text-end">WIP Value</th>
                                                        <th>Status</th>
                                                        <th class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="wc-batches-tbody-{{ $order->id }}-{{ $wcId }}">
                                                    @include('modules.production.wip.partials.work-center-batch-rows', ['wips' => $initialBatchesPaginator->items()])
                                                </tbody>
                                            </table>
                                        </div>
                                        <div id="wc-batches-pagination-{{ $order->id }}-{{ $wcId }}">
                                            @if($initialBatchesPaginator->lastPage() > 1)
                                                @include('modules.production.wip.partials.wc-pagination', [
                                                    'paginator' => $initialBatchesPaginator,
                                                    'orderId' => $order->id,
                                                    'workCenterId' => $wcId,
                                                ])
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Consolidated Order Stage Movement Transaction Ledger --}}
                            @php
                                $allTransactions = $order->wips->flatMap->transactions->sortByDesc('transaction_at');
                            @endphp
                            <div class="card shadow-sm border">
                                <div class="card-header bg-light py-2 px-3">
                                    <h6 class="fw-bold text-dark mb-0"><i class="feather-clock me-2 text-primary"></i> Order
                                        Stage Movement & Cost Transaction Ledger</h6>
                                </div>
                                <div class="table-responsive">
                                    <table class="erp-thin-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>{{ __('production.date') }}</th>
                                                <th>WIP / Batch</th>
                                                <th>{{ __('production.type') }}</th>
                                                <th>{{ __('production.from_stage') }}</th>
                                                <th>{{ __('production.to_stage') }}</th>
                                                <th class="text-end">{{ __('production.quantity') }}</th>
                                                <th class="text-end">{{ __('production.cost_added') }}</th>
                                                <th>{{ __('production.remarks') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($allTransactions as $tx)
                                                <tr>
                                                    <td class="font-monospace text-muted fs-11">
                                                        {{ $tx->transaction_at ? $tx->transaction_at->format('Y-m-d H:i') : '—' }}
                                                    </td>
                                                    <td class="font-monospace fs-11">
                                                        WIP-#{{ str_pad($tx->wip_id, 5, '0', STR_PAD_LEFT) }}
                                                    </td>
                                                    <td>
                                                        @php
                                                            $badgeClass = match ($tx->transaction_type) {
                                                                'created' => 'bg-soft-primary text-primary',
                                                                'operation_started' => 'bg-soft-info text-info',
                                                                'progress_logged' => 'bg-soft-primary text-primary',
                                                                'operation_completed' => 'bg-soft-success text-success',
                                                                'transferred' => 'bg-soft-warning text-warning',
                                                                'rework_completed' => 'bg-soft-success text-success',
                                                                'converted_to_finished_goods' => 'bg-success text-white',
                                                                default => 'bg-soft-secondary text-secondary'
                                                            };
                                                        @endphp
                                                        <span
                                                            class="badge {{ $badgeClass }} text-uppercase fs-10">{{ str_replace('_', ' ', $tx->transaction_type) }}</span>
                                                    </td>
                                                    <td>{{ $tx->fromOperation ? $tx->fromOperation->name : '—' }}</td>
                                                    <td>{{ $tx->toOperation ? $tx->toOperation->name : '—' }}</td>
                                                    <td class="text-end fw-semibold">{{ number_format($tx->quantity, 2) }}</td>
                                                    <td class="text-end text-success fw-semibold">
                                                        {{ $tx->cost_added > 0 ? '+' . format_currency($tx->cost_added) : '—' }}
                                                    </td>
                                                    <td class="text-muted fs-11">{{ $tx->remarks }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-3">
                                                        {{ __('production.no_daily_cost_history') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-5 text-muted bg-light rounded">
                                <i class="feather-alert-circle fs-24 mb-2 d-block text-warning"></i>
                                {{ __('production.no_wip_active') }}
                            </div>
                        @endif
                    </div>

                    {{-- Tab: Batches & Serials --}}
                    @if($order->production_mode === 'batch' || $order->production_mode === 'batch_and_serial')
                        <div class="tab-pane fade {{ $activeTab === 'vtab-batches' ? 'show active' : '' }}" id="vtab-batches"
                            role="tabpanel" aria-labelledby="vtab-batches-tab">
                            <div class="row g-4">
                                {{-- Active Batches --}}
                                <div class="{{ $order->production_mode === 'batch_and_serial' ? 'col-lg-6' : 'col-12' }}">
                                    <x-ui.card title="Active Production Batches">
                                        <x-ui.odoo-form-ui type="table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 30%">Batch Number</th>
                                                    <th style="width: 20%" class="text-end">Planned Qty</th>
                                                    <th style="width: 20%" class="text-end">Actual Qty</th>
                                                    <th style="width: 15%" class="text-center">Status</th>
                                                    <th style="width: 15%" class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($order->batches as $batch)
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold text-dark font-monospace">{{ $batch->batch_number }}
                                                            </div>
                                                            @if($batch->barcode)
                                                                <div class="fs-10 text-muted"><i
                                                                        class="feather-tag me-1"></i>{{ $batch->barcode }}</div>
                                                            @endif
                                                        </td>
                                                        <td class="text-end fw-semibold text-dark">
                                                            {{ number_format($batch->planned_quantity, 2) }}</td>
                                                        <td class="text-end text-success fw-semibold">
                                                            {{ number_format($batch->actual_quantity, 2) }}</td>
                                                        <td class="text-center">
                                                            <span
                                                                class="badge bg-soft-primary text-primary fs-11">{{ strtoupper($batch->status) }}</span>
                                                        </td>
                                                        <td class="text-center">
                                                            <x-ui.button size="sm" variant="light" class="border py-1 px-2"
                                                                href="{{ route('production.labels.batches.print', $batch->id) }}"
                                                                target="_blank" title="Print Barcode Label">
                                                                <i class="feather-printer me-1"></i> Print Label
                                                            </x-ui.button>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center py-4 text-muted">No batches generated for
                                                            this order yet.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </x-ui.odoo-form-ui>
                                    </x-ui.card>
                                </div>

                                @if($order->production_mode === 'batch_and_serial')
                                    {{-- Registered Serials inside combined view --}}
                                    <div class="col-lg-6">
                                        <x-ui.card title="Registered Serial Numbers">
                                            <x-ui.odoo-form-ui type="table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 45%">Serial Number</th>
                                                        <th style="width: 25%">Status</th>
                                                        <th style="width: 30%" class="text-center">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($order->serialNumbers as $serial)
                                                        <tr>
                                                            <td class="fw-bold text-dark font-monospace">{{ $serial->serial_number }}
                                                            </td>
                                                            <td>
                                                                <span
                                                                    class="badge bg-soft-info text-info fs-11">{{ strtoupper($serial->status) }}</span>
                                                            </td>
                                                            <td class="text-center">
                                                                <x-ui.button size="sm" variant="light" class="border py-1 px-2"
                                                                    href="{{ route('production.labels.serials.print', $serial->id) }}"
                                                                    target="_blank" title="Print Barcode Label">
                                                                    <i class="feather-printer me-1"></i> Print Label
                                                                </x-ui.button>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="3" class="text-center py-4 text-muted">No serial numbers
                                                                registered yet.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </x-ui.odoo-form-ui>
                                        </x-ui.card>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($order->production_mode === 'serial')
                        <div class="tab-pane fade {{ $activeTab === 'vtab-serials' ? 'show active' : '' }}" id="vtab-serials"
                            role="tabpanel" aria-labelledby="vtab-serials-tab">
                            <x-ui.card title="Registered Serial Numbers">
                                <x-ui.odoo-form-ui type="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 45%">Serial Number</th>
                                            <th style="width: 25%">Status</th>
                                            <th style="width: 30%" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($order->serialNumbers as $serial)
                                            <tr>
                                                <td class="fw-bold text-dark font-monospace">{{ $serial->serial_number }}</td>
                                                <td>
                                                    <span
                                                        class="badge bg-soft-info text-info fs-11">{{ strtoupper($serial->status) }}</span>
                                                </td>
                                                <td class="text-center">
                                                    <x-ui.button size="sm" variant="light" class="border py-1 px-2"
                                                        href="{{ route('production.labels.serials.print', $serial->id) }}"
                                                        target="_blank" title="Print Barcode Label">
                                                        <i class="feather-printer me-1"></i> Print Label
                                                    </x-ui.button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted">No serial numbers registered
                                                    yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </x-ui.odoo-form-ui>
                            </x-ui.card>
                        </div>
                    @endif

                    {{-- Tab 2: Operations --}}
                    <div class="tab-pane fade {{ $activeTab === 'vtab-operations' ? 'show active' : '' }}"
                        id="vtab-operations" role="tabpanel" aria-labelledby="vtab-operations-tab">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0"><i class="feather-cpu text-primary me-2"></i>{{ __('production.routing_ops_title') }}</h5>
                            <span class="fs-12 text-muted">{{ __('production.ops_sequential_note') }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="erp-thin-table">
                                <thead>
                                    <tr>
                                        <th style="width:5%" class="text-center">{{ __('production.seq') }}</th>
                                        <th style="width:18%">{{ __('production.operation') }}</th>
                                        <th style="width:12%">{{ __('production.work_center') }}</th>
                                        <th style="width:10%">{{ __('production.machine') }}</th>
                                        <th style="width:15%">Operator</th>
                                        <th style="width:10%" class="text-center">{{ __('production.planned_setup_run') }}
                                        </th>
                                        <th style="width:10%" class="text-center">{{ __('production.actual_setup_run') }}
                                        </th>
                                        <th style="width:10%" class="text-center">{{ __('production.produced_scrap') }}</th>
                                        <th style="width:7%">{{ __('production.status') }}</th>
                                        <th style="width:3%" class="text-end">{{ __('production.log') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->operations as $op)
                                        <tr>
                                            <td class="text-center fw-semibold text-muted">#{{ $op->sequence }}</td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $op->operation_number }}</div>
                                                <small class="text-muted">{{ html_entity_decode($op->name ?? '', ENT_QUOTES, 'UTF-8') }}</small>
                                                @if($op->sourceProduct && $op->source_product_id !== $order->product_id)
                                                    <span class="badge bg-soft-info text-info border border-info-subtle ms-1"><i class="feather-box me-1"></i>{{ $op->sourceProduct->name }} (Level {{ $op->bom_level ?? 1 }})</span>
                                                @elseif($op->bom_level > 1)
                                                    <span class="badge bg-soft-secondary text-secondary ms-1">Level {{ $op->bom_level }}</span>
                                                @endif
                                                @if($op->is_external)
                                                    <span class="badge bg-soft-warning text-dark border border-warning ms-1"><i class="feather-external-link me-1"></i>Subcontract</span>
                                                    @if($op->purchaseOrder)
                                                        @if($op->purchaseOrder->status === 'Approved')
                                                            <a href="{{ route('purchase.orders.show', $op->purchaseOrder->id) }}" class="badge bg-soft-success text-success border border-success-subtle text-decoration-none ms-1" title="Subcontract PO Approved">
                                                                <i class="feather-check-circle me-1"></i>PO Approved (#{{ $op->purchaseOrder->purchase_order_number }})
                                                            </a>
                                                        @else
                                                            <a href="{{ route('purchase.orders.show', $op->purchaseOrder->id) }}" class="badge bg-soft-info text-info border border-info-subtle text-decoration-none ms-1" title="Subcontract PO {{ $op->purchaseOrder->status }}">
                                                                <i class="feather-file-text me-1"></i>PO {{ $op->purchaseOrder->status }} (#{{ $op->purchaseOrder->purchase_order_number }})
                                                            </a>
                                                        @endif
                                                    @endif
                                                    @if(($op->material_supply_type ?? 'company_supplied') === 'company_supplied')
                                                        <div class="mt-1">
                                                            @php
                                                                $existingChallan = $op->latestDeliveryChallan;
                                                            @endphp
                                                            @if($existingChallan)
                                                                @if($existingChallan->status === 'draft')
                                                                    <a href="{{ route('production.subcontract.delivery-challans.show', $existingChallan->id) }}" class="badge bg-soft-warning text-dark border border-warning fs-10 text-decoration-none" title="Draft Gate Pass pending dispatch">
                                                                        <i class="feather-clock me-1"></i>Draft Gate Pass (#{{ $existingChallan->challan_number }})
                                                                    </a>
                                                                @elseif($existingChallan->status === 'dispatched')
                                                                    <a href="{{ route('production.subcontract.delivery-challans.show', $existingChallan->id) }}" class="badge bg-soft-info text-info border border-info-subtle fs-10 text-decoration-none" title="Material Dispatched to Vendor">
                                                                        <i class="feather-truck me-1"></i>Dispatched (#{{ $existingChallan->challan_number }})
                                                                    </a>
                                                                @else
                                                                    <a href="{{ route('production.subcontract.delivery-challans.show', $existingChallan->id) }}" class="badge bg-soft-success text-success border border-success-subtle fs-10 text-decoration-none" title="Delivery Challan Completed">
                                                                        <i class="feather-check-circle me-1"></i>Challan Completed (#{{ $existingChallan->challan_number }})
                                                                    </a>
                                                                @endif
                                                            @else
                                                                <a href="{{ route('production.subcontract.delivery-challans.create', ['production_order_id' => $order->id, 'operation_id' => $op->id]) }}" class="badge bg-soft-primary text-primary border border-primary-subtle fs-10 text-decoration-none" title="Generate Subcontract Material Delivery Challan / Gate Pass to Vendor">
                                                                    <i class="feather-truck me-1"></i>Dispatch Material (Delivery Challan)
                                                                </a>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endif
                                            </td>
                                            <td>
                                                @if($op->is_external)
                                                    <span class="fw-semibold text-dark"><i class="feather-truck me-1 text-primary"></i>{{ $op->vendor->name ?? 'Subcontract Vendor' }}</span>
                                                    <div class="fs-10 text-muted">Lead: {{ $op->subcontract_lead_time_days ?? 0 }}d</div>
                                                @else
                                                    {{ $op->workCenter->name }}
                                                @endif
                                            </td>
                                            <td>
                                                @if($op->is_external)
                                                    <span class="badge bg-light text-muted">N/A (Subcontract)</span>
                                                @else
                                                    {{ $op->machine->name ?? 'Any' }}
                                                @endif
                                            </td>
                                            <td>
                                                @if($op->is_external)
                                                    <span class="text-muted fs-11"><i class="feather-user-check me-1"></i>External Vendor</span>
                                                @else
                                                    @php
                                                        $activeAssignment = $op->operatorAssignments->whereIn('status', ['assigned', 'accepted'])->first();
                                                    @endphp
                                                    @if($activeAssignment)
                                                        <div>
                                                            <span
                                                                class="fw-semibold text-dark fs-12">{{ $activeAssignment->user->name }}</span>
                                                            <div class="d-flex align-items-center gap-1.5">
                                                                @if($activeAssignment->status === 'accepted')
                                                                    <span
                                                                        class="badge bg-soft-success text-success fs-9 py-0.5 px-1">Accepted</span>
                                                                @else
                                                                    <span
                                                                        class="badge bg-soft-warning text-warning fs-9 py-0.5 px-1">Pending</span>
                                                                @endif
                                                                @if(($order->isReleased() || $order->isInProgress()) && $op->status !== 'completed')
                                                                    @if($order->schedules->isNotEmpty())
                                                                        <button type="button"
                                                                            class="btn btn-xs btn-outline-secondary p-0.5 border-0"
                                                                            title="Reassign" data-bs-toggle="modal"
                                                                            data-bs-target="#orderAssignOperatorModal"
                                                                            onclick="document.getElementById('assign_op_id').value = '{{ $op->id }}';">
                                                                            <i class="feather-edit fs-11"></i>
                                                                        </button>
                                                                    @else
                                                                        <button type="button"
                                                                            class="btn btn-xs btn-outline-secondary p-0.5 border-0" disabled
                                                                            title="Generate a schedule first to enable operator assignment"
                                                                            data-bs-toggle="tooltip">
                                                                            <i class="feather-edit fs-11"></i>
                                                                        </button>
                                                                    @endif
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="d-flex align-items-center gap-1.5">
                                                            <span class="text-muted fs-12">—</span>
                                                            @if(($order->isReleased() || $order->isInProgress()) && $op->status !== 'completed')
                                                                @if($order->schedules->isNotEmpty())
                                                                    <button type="button" class="btn btn-xs btn-outline-primary py-0.5 px-1.5"
                                                                        data-bs-toggle="modal" data-bs-target="#orderAssignOperatorModal"
                                                                        onclick="document.getElementById('assign_op_id').value = '{{ $op->id }}';">
                                                                        <i class="feather-user-plus me-1"></i> Assign
                                                                    </button>
                                                                @else
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-1.5"
                                                                        disabled title="Generate a schedule first to enable operator assignment"
                                                                        data-bs-toggle="tooltip">
                                                                        <i class="feather-user-plus me-1"></i> Assign
                                                                    </button>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="text-center text-muted">
                                                @if($op->is_external)
                                                    Lead: {{ $op->subcontract_lead_time_days ?? 0 }}d
                                                @else
                                                    {{ $op->setup_time_planned }}m / {{ $op->processing_time_planned }}m
                                                @endif
                                            </td>
                                            <td class="text-center fw-semibold text-dark">
                                                @if($op->is_external)
                                                    Disp: {{ $op->dispatch_buffer_days ?? 0 }}d / Ret: {{ $op->return_buffer_days ?? 0 }}d
                                                @else
                                                    {{ $op->setup_time_actual }}m / {{ $op->processing_time_actual }}m
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="text-success fw-bold">{{ number_format($op->quantity_produced, 2) }}</span>
                                                /
                                                <span
                                                    class="text-danger">{{ number_format($op->quantity_scrapped + $op->quantity_rejected, 2) }}</span>
                                            </td>
                                            <td>
                                                @if($op->status === 'waiting')
                                                    <span class="badge bg-secondary text-white">Waiting</span>
                                                @elseif($op->status === 'ready')
                                                    <span class="badge bg-primary text-white">Ready</span>
                                                @elseif($op->status === 'running')
                                                    <span class="badge bg-info text-white">Running</span>
                                                @elseif($op->status === 'vendor_dispatched')
                                                    <span class="badge bg-soft-info text-info border border-info-subtle"><i class="feather-truck me-1"></i>At Vendor (Dispatched)</span>
                                                @elseif($op->status === 'paused')
                                                    <span class="badge bg-warning text-dark">Paused</span>
                                                @elseif($op->status === 'completed')
                                                    <span
                                                        class="badge bg-success text-white">{{ __('production.completed') }}</span>
                                                @else
                                                    <span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $op->status)) }}</span>
                                                @endif

                                                @if($op->scheduleOperation && ($op->scheduleOperation->status === 'running' || $op->scheduleOperation->status === 'paused'))
                                                    <div class="live-timer" data-status="{{ $op->scheduleOperation->status }}"
                                                        data-start="{{ $op->scheduleOperation->actual_start ? $op->scheduleOperation->actual_start->toIso8601String() : '' }}"
                                                        data-paused-at="{{ $op->scheduleOperation->last_paused_at ? $op->scheduleOperation->last_paused_at->toIso8601String() : '' }}"
                                                        data-accumulated-paused="{{ $op->scheduleOperation->accumulated_paused_seconds ?? 0 }}">
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @if($op->is_external)
                                                    <a href="?tab=vtab-subcontract"
                                                        class="btn btn-sm btn-outline-warning py-1 px-2 fs-11 d-inline-flex align-items-center gap-1">
                                                        <i class="feather-truck"></i> Subcontract
                                                    </a>
                                                @elseif(($order->isReleased() || $order->isInProgress()) && $op->status !== 'completed')
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-primary py-1 px-2 fs-11 d-inline-flex align-items-center gap-1"
                                                        data-bs-toggle="modal" data-bs-target="#progressModal"
                                                        onclick="var selectEl = document.getElementById('op_select_id'); if (selectEl) { selectEl.value = '{{ $op->id }}'; selectEl.dispatchEvent(new Event('change')); if (window.jQuery && jQuery().select2) { $(selectEl).trigger('change'); } }">
                                                        <i class="feather-edit-3"></i> {{ __('production.log_progress') }}
                                                    </button>
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

                    {{-- Tab 2.1: Component Production Plan --}}
                    <div class="tab-pane fade {{ $activeTab === 'vtab-component-plan' ? 'show active' : '' }}"
                        id="vtab-component-plan" role="tabpanel" aria-labelledby="vtab-component-plan-tab">
                        
                        {{-- Section Summary Header Context Banner --}}
                        <div class="card mb-3 border shadow-sm rounded-3 bg-light">
                            <div class="card-body p-3">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-text bg-soft-primary text-primary rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                <i class="feather-layers fs-16"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark fs-14 d-flex align-items-center gap-2">
                                                    <span>{{ $order->order_number }}</span>
                                                    <span class="text-muted">&middot;</span>
                                                    <span class="text-primary">{{ $order->product->name ?? 'Finished Good' }}</span>
                                                </div>
                                                <div class="fs-12 text-muted">
                                                    Finished Good Target: <strong class="text-dark">{{ number_format($order->quantity_ordered, 2) }} {{ $order->product->uom->code ?? 'units' }}</strong>
                                                    <span class="mx-1">&bull;</span> Due: <strong>{{ $order->due_date ? \Carbon\Carbon::parse($order->due_date)->format('d M Y') : 'N/A' }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        @php
                                            $cTotalOps = $order->operations->count();
                                            $cCompletedOps = $order->operations->where('status', 'completed')->count();
                                            $cRunningOps = $order->operations->where('status', 'running')->count();
                                            $cWaitingOps = $order->operations->whereIn('status', ['ready', 'waiting', 'scheduled'])->count();
                                            $cRoutingProgress = $cTotalOps > 0 ? round(($cCompletedOps / $cTotalOps) * 100, 1) : 0;
                                        @endphp
                                        <div class="row text-center g-2 fs-11">
                                            <div class="col-3">
                                                <div class="p-2 border rounded bg-white">
                                                    <span class="text-muted d-block fs-10 text-uppercase fw-semibold">Total Operations</span>
                                                    <span class="fw-bold fs-13 text-dark">{{ $cTotalOps }}</span>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="p-2 border rounded bg-white">
                                                    <span class="text-muted d-block fs-10 text-uppercase fw-semibold">Completed</span>
                                                    <span class="fw-bold fs-13 text-success">{{ $cCompletedOps }}</span>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="p-2 border rounded bg-white">
                                                    <span class="text-muted d-block fs-10 text-uppercase fw-semibold">Active / Running</span>
                                                    <span class="fw-bold fs-13 text-primary">{{ $cRunningOps }}</span>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="p-2 border rounded bg-white">
                                                    <span class="text-muted d-block fs-10 text-uppercase fw-semibold">Routing Progress</span>
                                                    <span class="fw-bold fs-13 text-info">{{ $cRoutingProgress }}%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <style>
                            #componentPlanSubTabs .nav-link {
                                transition: all 0.2s ease-in-out;
                            }
                            #componentPlanSubTabs .nav-link.active {
                                background-color: var(--bs-primary, #0000FF) !important;
                                color: #ffffff !important;
                                box-shadow: 0 2px 6px rgba(0, 0, 255, 0.25) !important;
                            }
                            .accordion-rotate-icon {
                                transition: transform 0.25s ease-in-out;
                                display: inline-block;
                            }
                            .collapsed .accordion-rotate-icon {
                                transform: rotate(0deg);
                            }
                            button:not(.collapsed) .accordion-rotate-icon {
                                transform: rotate(90deg);
                            }
                        </style>

                        {{-- Sub-view Navigation Toggle: Matrix View vs Hierarchical Tree View --}}
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <ul class="nav nav-pills nav-pills-sm border p-1 rounded-3 bg-white shadow-sm" id="componentPlanSubTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active py-1.5 px-3 fs-12 fw-bold rounded-2" id="cp-matrix-tab" data-bs-toggle="pill" data-bs-target="#cp-matrix-view" type="button" role="tab">
                                        <i class="feather-grid me-1.5"></i> Component Plan Matrix
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link py-1.5 px-3 fs-12 fw-bold rounded-2" id="cp-tree-tab" data-bs-toggle="pill" data-bs-target="#cp-tree-view" type="button" role="tab">
                                        <i class="feather-list me-1.5"></i> Hierarchical Process & BOM Tree
                                    </button>
                                </li>
                            </ul>
                            <div class="fs-12 text-muted">
                                <i class="feather-info me-1 text-primary"></i> Process operations & component materials mapped automatically via BOM & Routing
                            </div>
                        </div>

                        <div class="tab-content" id="componentPlanSubTabsContent">
                            {{-- View 1: Standard Component Matrix View --}}
                            <div class="tab-pane fade show active" id="cp-matrix-view" role="tabpanel">
                                <div class="table-responsive border rounded-3 shadow-sm">
                                    <x-ui.odoo-form-ui type="table">
                                        <thead class="bg-light text-uppercase fs-11 text-muted border-bottom">
                                            <tr>
                                                <th style="width: 25%">Item / Component</th>
                                                <th style="width: 17%">Operation</th>
                                                <th style="width: 17%">Resource</th>
                                                <th style="width: 13%" class="text-end">Requirement</th>
                                                <th style="width: 12%">Schedule</th>
                                                <th style="width: 11%">Execution</th>
                                                <th style="width: 5%" class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($order->operations as $cOp)
                                                @php
                                                    $itemProduct = $cOp->sourceProduct ?? $order->product;
                                                    $bomItemRatio = 1.0;
                                                    if ($itemProduct && (int) $itemProduct->id !== (int) $order->product_id) {
                                                        $bItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $cOp->tenant_id)
                                                            ->where('bom_id', $order->bom_id)
                                                            ->where('material_id', $itemProduct->id)
                                                            ->first();
                                                        if ($bItem && (float) $bItem->quantity > 0) {
                                                            $bBase = $order->bom?->base_quantity > 0 ? (float) $order->bom->base_quantity : 1.0;
                                                            $bomItemRatio = (float) $bItem->quantity / $bBase;
                                                        }
                                                    }
                                                    $targetQty = (float) ($cOp->target_produced_qty > 0 ? $cOp->target_produced_qty : ($order->quantity_ordered * $bomItemRatio));
                                                    $doneQty = (float) $cOp->quantity_produced;
                                                    $rejectedQty = (float) $cOp->quantity_rejected;
                                                    $scrappedQty = (float) $cOp->quantity_scrapped;
                                                    $reworkQty = (float) ($cOp->quantity_rework ?? 0);
                                                    $pendingQty = max(0.0, $targetQty - $doneQty);
                                                    $pct = $targetQty > 0 ? min(100.0, ($doneQty / $targetQty) * 100) : 0.0;
                                                    $itemUom = $itemProduct->uom->code ?? 'Pcs';
                                                    
                                                    $isSfg = ($itemProduct && (int) $itemProduct->id !== (int) $order->product_id);
                                                    $isExternal = (bool) $cOp->is_external;
                                                    $level = $cOp->bom_level ?? ($isSfg ? 2 : 1);
                                                @endphp
                                                <tr class="bg-white">
                                                    {{-- Cell 1: Item / Component --}}
                                                    <td class="bg-white">
                                                        <div class="d-flex align-items-start gap-2">
                                                            <button type="button" class="btn btn-xs btn-outline-primary p-1 border-0 text-primary bg-transparent rounded-2 shadow-none me-1 collapsed" 
                                                                data-bs-toggle="collapse" data-bs-target="#op-detail-{{ $cOp->id }}" 
                                                                aria-expanded="false" aria-controls="op-detail-{{ $cOp->id }}" title="Toggle operation details">
                                                                <i class="feather-chevron-right fs-12 accordion-rotate-icon"></i>
                                                            </button>
                                                            <div>
                                                                <div class="d-flex align-items-center gap-1.5 mb-0.5">
                                                                    <span class="badge {{ $level > 1 ? 'bg-soft-purple text-purple border border-purple-subtle' : 'bg-soft-primary text-primary border' }} fs-10 py-0.5 px-1.5">
                                                                        {{ $level > 1 ? 'L'.$level.' SFG' : 'L1 FG' }}
                                                                    </span>
                                                                    <span class="fw-bold text-dark fs-12">{{ $itemProduct->name }}</span>
                                                                </div>
                                                                <div class="fs-10 text-muted font-monospace d-flex align-items-center gap-2">
                                                                    <span><i class="feather-tag me-0.5"></i>{{ $itemProduct->sku ?? 'SKU-N/A' }}</span>
                                                                    <span>&bull;</span>
                                                                    <span class="text-secondary fw-semibold">{{ number_format($bomItemRatio, 2) }} {{ $itemUom }}/FG</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    {{-- Cell 2: Operation --}}
                                                    <td class="bg-white">
                                                        <div class="fw-bold text-primary fs-12">
                                                            OP{{ $cOp->sequence ?? $loop->iteration * 10 }} &middot; {{ $cOp->name }}
                                                        </div>
                                                        <div class="fs-10 text-muted">
                                                            @if($isExternal)
                                                                <span class="badge bg-soft-purple text-purple border fs-10 px-1.5 py-0.5">
                                                                    <i class="feather-truck me-1"></i>External Subcontract
                                                                </span>
                                                            @else
                                                                <span class="badge bg-soft-secondary text-secondary border fs-10 px-1.5 py-0.5">
                                                                    <i class="feather-cpu me-1"></i>Internal Routing
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </td>

                                                    {{-- Cell 3: Resource --}}
                                                    <td class="bg-white">
                                                        @if($isExternal)
                                                            <div class="fw-semibold text-dark fs-11"><i class="feather-users me-1 text-purple"></i>{{ $cOp->vendor->name ?? 'Outsourced Vendor' }}</div>
                                                            <div class="fs-10 text-muted">Subcontract Vendor</div>
                                                        @else
                                                            <div class="fw-semibold text-dark fs-11"><i class="feather-grid me-1 text-primary"></i>{{ $cOp->workCenter->name ?? 'Workstation Unassigned' }}</div>
                                                            <div class="fs-10 text-muted">
                                                                @if($cOp->machine)
                                                                    <i class="feather-cpu me-1"></i>{{ $cOp->machine->name }}
                                                                @else
                                                                    <span class="fst-italic">Machine not assigned</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>

                                                    {{-- Cell 4: Requirement --}}
                                                    <td class="text-end bg-white">
                                                        <div class="fw-bold text-dark fs-13 font-monospace">
                                                            {{ number_format($targetQty, 2) }} <small class="text-muted fs-10">{{ $itemUom }}</small>
                                                        </div>
                                                        <div class="fs-10 text-muted">
                                                            {{ number_format($bomItemRatio, 2) }} &times; {{ number_format($order->quantity_ordered, 0) }} FG
                                                        </div>
                                                    </td>

                                                    {{-- Cell 5: Schedule --}}
                                                    <td class="fs-11 text-muted bg-white">
                                                        @if($cOp->scheduleOperation)
                                                            @php
                                                                $isOverdue = $cOp->scheduleOperation->planned_finish && $cOp->scheduleOperation->planned_finish->isPast() && $cOp->status !== 'completed';
                                                            @endphp
                                                            <div class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}">
                                                                <i class="feather-calendar me-1"></i>{{ $cOp->scheduleOperation->planned_start ? $cOp->scheduleOperation->planned_start->format('d M H:i') : '-' }}
                                                            </div>
                                                            <div class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}">
                                                                <i class="feather-flag me-1"></i>{{ $cOp->scheduleOperation->planned_finish ? $cOp->scheduleOperation->planned_finish->format('d M H:i') : '-' }}
                                                            </div>
                                                        @else
                                                            <span class="badge bg-soft-secondary text-secondary border fs-10">Not Scheduled</span>
                                                        @endif
                                                    </td>

                                                    {{-- Cell 6: Execution Progress --}}
                                                    <td class="bg-white">
                                                        <div class="d-flex justify-content-between fs-11 mb-1">
                                                            <span class="fw-bold text-success font-monospace">{{ number_format($doneQty, 1) }}</span>
                                                            <span class="text-muted font-monospace">/ {{ number_format($targetQty, 0) }} {{ $itemUom }}</span>
                                                        </div>
                                                        <div class="progress style-3" style="height: 6px;">
                                                            <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : ($pct > 0 ? 'bg-primary' : 'bg-secondary') }}" 
                                                                role="progressbar" style="width: {{ $pct }}%" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        @if($rejectedQty > 0 || $scrappedQty > 0 || $reworkQty > 0)
                                                            <div class="fs-10 mt-1 d-flex flex-wrap gap-1">
                                                                @if($rejectedQty > 0)
                                                                    <span class="badge bg-soft-danger text-danger fs-9 p-0.5 px-1">Rej: {{ number_format($rejectedQty, 0) }}</span>
                                                                @endif
                                                                @if($scrappedQty > 0)
                                                                    <span class="badge bg-soft-dark text-dark fs-9 p-0.5 px-1">Scrap: {{ number_format($scrappedQty, 0) }}</span>
                                                                @endif
                                                                @if($reworkQty > 0)
                                                                    <span class="badge bg-soft-warning text-warning fs-9 p-0.5 px-1">Rework: {{ number_format($reworkQty, 0) }}</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>

                                                    {{-- Cell 7: Status --}}
                                                    <td class="text-center bg-white">
                                                        @if($cOp->status === 'completed')
                                                            <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-1 fs-11"><i class="feather-check-circle me-1"></i>Completed</span>
                                                        @elseif($cOp->status === 'running')
                                                            <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-1 fs-11"><i class="feather-play me-1"></i>Running</span>
                                                        @elseif($cOp->status === 'ready')
                                                            <span class="badge bg-soft-info text-info border border-info-subtle px-2 py-1 fs-11"><i class="feather-clock me-1"></i>Ready</span>
                                                        @elseif($cOp->status === 'qc_hold')
                                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-1 fs-11"><i class="feather-shield me-1"></i>QC Hold</span>
                                                        @elseif($cOp->status === 'rework')
                                                            <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-1 fs-11"><i class="feather-alert-triangle me-1"></i>Rework</span>
                                                        @else
                                                            <span class="badge bg-soft-secondary text-secondary border border-secondary-subtle px-2 py-1 fs-11">{{ ucfirst($cOp->status) }}</span>
                                                        @endif
                                                    </td>
                                                </tr>

                                                {{-- Expandable Drawer Detail Panel --}}
                                                <tr id="op-detail-{{ $cOp->id }}" class="collapse bg-white border-bottom">
                                                    <td colspan="7" class="p-3 bg-white">
                                                        <div class="card border border-light-subtle shadow-sm mb-0 rounded-3 bg-light-subtle">
                                                            <div class="card-body p-3">
                                                                <div class="row g-3 fs-11">
                                                                    {{-- Column 1: Material Inputs Required --}}
                                                                    <div class="col-md-4 border-end">
                                                                        <h6 class="fw-bold text-dark fs-12 mb-2 d-flex align-items-center gap-1">
                                                                            <i class="feather-box text-primary"></i>Material Inputs Required
                                                                        </h6>
                                                                        @php
                                                                            $bomMaterials = \App\Domains\Production\Models\RoutingOperationMaterial::where('routing_operation_id', $cOp->routing_operation_id)->get();
                                                                        @endphp
                                                                        @if($bomMaterials->isNotEmpty())
                                                                            <ul class="list-group list-group-flush border rounded-2 fs-11">
                                                                                @foreach($bomMaterials as $bMat)
                                                                                    @php
                                                                                        $matProd = \App\Domains\Inventory\Models\Product::find($bMat->material_id);
                                                                                    @endphp
                                                                                    <li class="list-group-item d-flex justify-content-between align-items-center py-1.5 px-2 bg-white">
                                                                                        <span>{{ $matProd->name ?? 'Material Item' }}</span>
                                                                                        <span class="fw-bold text-dark font-monospace">{{ number_format($bMat->quantity * $order->quantity_ordered, 2) }} {{ $matProd->uom->code ?? 'units' }}</span>
                                                                                    </li>
                                                                                @endforeach
                                                                            </ul>
                                                                        @else
                                                                            <div class="text-muted fs-11 fst-italic">Standard BOM component routing operation</div>
                                                                        @endif
                                                                    </div>

                                                                    {{-- Column 2: Execution & Quality Metrics --}}
                                                                    <div class="col-md-4 border-end">
                                                                        <h6 class="fw-bold text-dark fs-12 mb-2 d-flex align-items-center gap-1">
                                                                            <i class="feather-check-square text-success"></i>Execution & Quality Summary
                                                                        </h6>
                                                                        <div class="bg-white p-2 border rounded-2">
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-muted">Assigned Operator:</span>
                                                                                <strong class="text-dark">{{ $cOp->operator->name ?? 'Unassigned' }}</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-muted">Target Requirement:</span>
                                                                                <strong class="text-dark">{{ number_format($targetQty, 2) }} {{ $itemUom }}</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-muted">Good Output Produced:</span>
                                                                                <strong class="text-success">{{ number_format($doneQty, 2) }} {{ $itemUom }}</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span class="text-muted">Pending Balance:</span>
                                                                                <strong class="text-primary">{{ number_format($pendingQty, 2) }} {{ $itemUom }}</strong>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    {{-- Column 3: Contextual Quick Actions --}}
                                                                    <div class="col-md-4">
                                                                        <h6 class="fw-bold text-dark fs-12 mb-2 d-flex align-items-center gap-1">
                                                                            <i class="feather-command text-info"></i>Contextual Action Shortcuts
                                                                        </h6>
                                                                        <div class="d-flex flex-wrap gap-2">
                                                                            <a href="{{ route('production.mes.dashboard') }}" class="btn btn-sm btn-outline-primary fs-11 py-1 px-2">
                                                                                <i class="feather-play me-1"></i>Open MES Console
                                                                            </a>
                                                                            @if($isExternal)
                                                                                <a href="{{ route('production.subcontract.delivery-challans.index') }}" class="btn btn-sm btn-outline-purple fs-11 py-1 px-2">
                                                                                    <i class="feather-file-text me-1"></i>Manage Subcontract
                                                                                </a>
                                                                            @endif
                                                                            <a href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']) }}" class="btn btn-sm btn-outline-secondary fs-11 py-1 px-2">
                                                                                <i class="feather-edit me-1"></i>View Operations Tab
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>

                            {{-- View 2: Hierarchical Process & BOM Tree (Image Format) --}}
                            <div class="tab-pane fade" id="cp-tree-view" role="tabpanel">
                                <div class="table-responsive border rounded-3 shadow-sm">
                                    <x-ui.odoo-form-ui type="table">
                                        <thead class="bg-light text-uppercase fs-11 text-muted border-bottom">
                                            <tr>
                                                <th style="width: 8%" class="ps-3">Sr. No</th>
                                                <th style="width: 32%">Process Name</th>
                                                <th style="width: 35%">Component Name</th>
                                        <th style="width: 13%" class="text-end">Requirement</th>
                                        <th style="width: 12%" class="text-center">Status / Scrap</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->operations as $opIndex => $treeOp)
                                        @php
                                            $opSrNo = $opIndex + 1; // 1, 2, 3, 4...
                                            $opProduct = $treeOp->sourceProduct ?? $order->product;
                                            $bomMaterials = \App\Domains\Production\Models\RoutingOperationMaterial::where('routing_operation_id', $treeOp->routing_operation_id)->get();
                                            $targetQty = (float) ($treeOp->target_produced_qty > 0 ? $treeOp->target_produced_qty : $order->quantity_ordered);
                                        @endphp

                                        {{-- Operation Step Row --}}
                                        <tr class="fw-semibold bg-light-subtle">
                                            <td class="ps-3 text-dark fw-bold font-monospace fs-12">{{ $opSrNo }}</td>
                                            <td>
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <i class="feather-cpu text-primary fs-13"></i>
                                                    <span class="fw-bold text-dark fs-12">{{ $treeOp->name }}</span>
                                                    <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-9 font-monospace">OP{{ $treeOp->sequence }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-dark fw-medium">{{ $opProduct->name }}</span>
                                                @if($opProduct?->sku)
                                                    <small class="text-muted font-monospace fs-10">({{ $opProduct->sku }})</small>
                                                @endif
                                            </td>
                                            <td class="text-end font-monospace fw-bold">{{ number_format($targetQty, 2) }} {{ $opProduct->uom->code ?? 'Pcs' }}</td>
                                            <td class="text-center">
                                                @if($treeOp->status === 'completed')
                                                    <span class="badge bg-soft-success text-success border border-success-subtle fs-10 px-2 py-0.5"><i class="feather-check-circle me-1"></i>Completed</span>
                                                @elseif($treeOp->status === 'running')
                                                    <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-10 px-2 py-0.5"><i class="feather-play me-1"></i>Running</span>
                                                @elseif($treeOp->status === 'ready')
                                                    <span class="badge bg-soft-info text-info border border-info-subtle fs-10 px-2 py-0.5"><i class="feather-clock me-1"></i>Ready</span>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary border border-secondary-subtle fs-10 px-2 py-0.5">{{ ucfirst($treeOp->status) }}</span>
                                                @endif
                                            </td>
                                        </tr>

                                        {{-- Child Material Consumption Rows under this Process Step --}}
                                        @forelse($bomMaterials as $matIdx => $bMat)
                                            @php
                                                $subSrNo = $opSrNo . "." . ($matIdx + 1); // 1.1, 1.2, 2.1, 2.2...
                                                $matProd = \App\Domains\Inventory\Models\Product::find($bMat->material_id);
                                                $reqQty = (float) $bMat->quantity * $order->quantity_ordered;
                                            @endphp
                                            <tr>
                                                <td class="ps-4 text-muted font-monospace fs-11">{{ $subSrNo }}</td>
                                                <td class="ps-4 text-muted fs-11">
                                                    <i class="feather-corner-down-right text-secondary me-1"></i> {{ $treeOp->name }} (Input Material)
                                                </td>
                                                <td class="ps-4 fw-medium text-dark fs-11">
                                                    <i class="feather-box text-primary me-1 fs-11"></i>
                                                    <span>{{ $matProd->name ?? 'Material Component' }}</span>
                                                    @if($matProd?->sku)
                                                        <small class="text-muted font-monospace fs-10">({{ $matProd->sku }})</small>
                                                    @endif
                                                </td>
                                                <td class="text-end font-monospace fs-11">{{ number_format($reqQty, 2) }} {{ $matProd->uom->code ?? 'units' }}</td>
                                                <td class="text-center fs-11">
                                                    @if(($treeOp->quantity_scrapped + $treeOp->quantity_rejected) > 0)
                                                        <span class="badge bg-soft-danger text-danger border border-danger-subtle fs-10">Scrap: {{ number_format($treeOp->quantity_scrapped + $treeOp->quantity_rejected, 1) }}</span>
                                                    @else
                                                        <span class="text-muted fs-10">OK</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="ps-4 text-muted font-monospace fs-11">{{ $opSrNo }}.1</td>
                                                <td class="ps-4 text-muted fs-11"><i class="feather-corner-down-right text-secondary me-1"></i> Direct Processing</td>
                                                <td class="ps-4 text-muted fs-11 fst-italic">Standard BOM process routing component</td>
                                                <td class="text-end font-monospace fs-11">—</td>
                                                <td class="text-center text-muted fs-11">—</td>
                                            </tr>
                                        @endforelse
                                    @endforeach

                                    {{-- LAST ROW: Final Assembly & FG Finished Good Receipt --}}
                                    <tr class="table-primary border-top border-primary-subtle fw-bold fs-12">
                                        <td class="text-center text-primary fs-11"><i class="feather-check-square"></i></td>
                                        <td class="text-primary fw-bold">
                                            <i class="feather-package me-1"></i> Final Product Receipt (FG Assembly)
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark">{{ $order->product->name }}</span>
                                            <span class="badge bg-primary text-white ms-1 fs-9">Finished Good Output</span>
                                        </td>
                                        <td class="text-end font-monospace text-dark fw-bold">{{ number_format($order->quantity_ordered, 2) }} {{ $order->product->uom->code ?? 'Pcs' }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-1 fs-11">
                                                <i class="feather-check-circle me-1"></i>{{ number_format($order->quantity_produced, 2) }} Received
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </x-ui.table>
                        </div>
                    </div>
                </div>
            </div>

                    {{-- Tab 4: Reservations & Store Requisitions --}}
                    <div class="tab-pane fade {{ $activeTab === 'vtab-reservations' ? 'show active' : '' }}"
                        id="vtab-reservations" role="tabpanel" aria-labelledby="vtab-reservations-tab">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0">Component Material Reservations & Store Requisitions</h5>
                            @if(($order->isReleased() || $order->isInProgress()) && !$order->isCompleted() && !$order->isClosed() && !$order->isCancelled())
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#requestAdditionalMaterialModal">
                                    <i class="feather-plus-circle me-1"></i> {{ __('production.request_additional_material') }}
                                </button>
                            @endif
                        </div>

                        {{-- Store Requisition Slip Banner --}}
                        @if($order->requisitionSlips && $order->requisitionSlips->isNotEmpty())
                            @php
                                $latestSlip = $order->requisitionSlips->last();
                                $latestStatusLower = strtolower($latestSlip->status ?? 'pending');
                                $slipBadge = match (true) {
                                    in_array($latestStatusLower, ['fully issued', 'completed', 'issued']) => 'bg-soft-success text-success border-success',
                                    in_array($latestStatusLower, ['partially issued', 'partial', 'reserved']) => 'bg-soft-warning text-warning border-warning',
                                    $latestStatusLower === 'approved' => 'bg-soft-primary text-primary border-primary',
                                    default => 'bg-soft-warning text-warning border-warning'
                                };
                                $slipStatusText = match (true) {
                                    in_array($latestStatusLower, ['fully issued', 'completed', 'issued']) => 'Fully Issued',
                                    in_array($latestStatusLower, ['partially issued', 'partial']) => 'Partially Issued',
                                    $latestStatusLower === 'reserved' => 'Reserved in Store',
                                    $latestStatusLower === 'approved' => 'Approved - Store Preparing Material',
                                    default => 'Pending Store Release'
                                };
                            @endphp
                            <div
                                class="p-3 mb-3 bg-light-subtle rounded border d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div
                                        class="avatar-sm bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center fw-bold fs-16">
                                        <i class="feather-file-text"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold text-dark fs-13">Material Requisition Slip:</span>
                                            <span
                                                class="font-monospace fw-bold text-primary fs-13">{{ $latestSlip->requisition_number }}</span>
                                            <span
                                                class="badge border {{ $slipBadge }} fs-10 px-2 py-0.5 text-uppercase ms-1">{{ $slipStatusText }}</span>
                                        </div>
                                        <small class="text-muted">Requested on
                                            {{ $latestSlip->requisition_date ? \Carbon\Carbon::parse($latestSlip->requisition_date)->format('d/m/Y') : $latestSlip->created_at->format('d/m/Y') }}
                                            &bull; Store Requisition Request Sent</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="text-end">
                                         <span class="fs-11 text-muted d-block">Requested Components</span>
                                        <span class="fw-bold text-dark fs-13">{{ $order->reservations->pluck('product_id')->unique()->count() }} items</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="erp-thin-table">
                                <thead>
                                    <tr>
                                        <th style="width:28%">{{ __('production.material_component') }}</th>
                                        <th style="width:14%">Classification</th>
                                        <th style="width:13%">{{ __('production.warehouse') }}</th>
                                        <th style="width:10%" class="text-center">{{ __('production.planned_qty') }}</th>
                                        <th style="width:10%" class="text-center">{{ __('production.reserved_qty') }}</th>
                                        <th style="width:10%" class="text-center">{{ __('production.issued_qty') }}</th>
                                        <th style="width:12%">Store Status</th>
                                        <th style="width:8%">UOM</th>
                                        <th style="width:10%" class="text-end">{{ __('production.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $displayReservations = $order->reservations->groupBy('product_id')->map(function($group) {
                                            $first = clone $group->first();
                                            $first->quantity_planned = $group->sum('quantity_planned');
                                            $first->quantity_reserved = $group->sum('quantity_reserved');
                                            $first->quantity_issued = $group->sum('quantity_issued');
                                            return $first;
                                        });
                                    @endphp
                                    @foreach($displayReservations as $res)
                                        @php
                                            $isSemiFinished = ($res->product->type === 'semi_finished' || $res->product->supplier_method === 'manufacture');

                                            // Line level store status
                                            if ($res->quantity_issued >= $res->quantity_planned && $res->quantity_planned > 0) {
                                                $lineStatusBadge = 'bg-soft-success text-success';
                                                $lineStatusText = 'Issued';
                                            } elseif ($res->quantity_issued > 0) {
                                                $lineStatusBadge = 'bg-soft-warning text-warning';
                                                $lineStatusText = 'Partially Issued';
                                            } elseif ($res->quantity_reserved >= $res->quantity_planned && $res->quantity_planned > 0) {
                                                $lineStatusBadge = 'bg-soft-info text-info';
                                                $lineStatusText = 'Reserved in Store';
                                            } elseif ($res->quantity_reserved > 0) {
                                                $lineStatusBadge = 'bg-soft-warning text-warning';
                                                $lineStatusText = 'Partial Reserved';
                                            } else {
                                                $lineStatusBadge = 'bg-soft-danger text-danger';
                                                $lineStatusText = 'Store Request Sent';
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <div class="fw-bold text-dark fs-13">{{ $res->product->name }}</div>
                                                        <small
                                                            class="text-muted font-monospace fs-10">{{ $res->product->sku }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if($isSemiFinished)
                                                    <span
                                                        class="badge bg-soft-primary text-primary border border-primary fs-10 fw-bold">
                                                        <i class="feather-layers me-1"></i>Semi-Finished
                                                    </span>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary fs-10">
                                                        <i class="feather-box me-1"></i>Raw Material
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-muted fs-12">
                                                {{ $res->warehouse?->name ?? __('production.not_reserved') }}</td>
                                            <td
                                                class="text-center {{ $isSemiFinished ? 'fw-bold text-dark' : 'fw-semibold text-dark' }}">
                                                {{ number_format($res->quantity_planned, 2) }}</td>
                                            <td class="text-center fw-bold" style="color: var(--bs-info);">
                                                {{ number_format($res->quantity_reserved, 2) }}</td>
                                            <td class="text-center fw-bold text-success">
                                                {{ number_format($res->quantity_issued, 2) }}</td>
                                            <td>
                                                <span
                                                    class="badge {{ $lineStatusBadge }} fs-10 text-uppercase">{{ $lineStatusText }}</span>
                                            </td>
                                            <td class="fs-12">{{ $res->uom->name }}</td>
                                            <td class="text-end">
                                                @if($order->isReleased() || $order->isInProgress())
                                                    <x-ui.action-dropdown id="resActionDropdown{{ $res->id }}">
                                                        <li>
                                                            <a class="dropdown-item py-1.5 fs-12" href="javascript:void(0)"
                                                                data-bs-toggle="modal" data-bs-target="#issueModal"
                                                                onclick="document.getElementById('issue_reservation_id').value = '{{ $res->id }}';">
                                                                <i
                                                                    class="feather-log-in me-2 text-info fs-12"></i>{{ __('production.issue_materials') }}
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item py-1.5 fs-12" href="javascript:void(0)"
                                                                data-bs-toggle="modal" data-bs-target="#returnModal"
                                                                onclick="document.getElementById('return_reservation_id').value = '{{ $res->id }}';">
                                                                <i
                                                                    class="feather-log-out me-2 text-secondary fs-12"></i>{{ __('production.return_materials') }}
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
                    <div class="tab-pane fade {{ $activeTab === 'vtab-issues' ? 'show active' : '' }}" id="vtab-issues"
                        role="tabpanel" aria-labelledby="vtab-issues-tab">
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
                                            <td
                                                class="text-center fw-bold {{ $iss->quantity_issued < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format($iss->quantity_issued, 2) }}
                                            </td>
                                            <td>
                                                @if($iss->issue_type === 'standard')
                                                    <span class="badge bg-light text-success border border-success">Standard</span>
                                                @elseif($iss->issue_type === 'additional')
                                                    <span
                                                        class="badge bg-light text-warning border border-warning">Additional</span>
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
                                                <i
                                                    class="feather-info fs-20 d-block mb-2"></i>{{ __('production.no_issues_logged') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 6: Progress Logs --}}
                    <div class="tab-pane fade {{ $activeTab === 'vtab-progress' ? 'show active' : '' }}" id="vtab-progress"
                        role="tabpanel" aria-labelledby="vtab-progress-tab">
                        {{-- KPI Summary Row --}}
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="bg-light rounded p-3 text-center border">
                                    <div class="text-muted fs-11 text-uppercase fw-bold mb-1">
                                        {{ __('production.planned_target') }}</div>
                                    <h3 class="text-dark fw-bold mb-0">{{ number_format($order->quantity_ordered, 2) }}</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-soft-success rounded p-3 text-center border border-success">
                                    <div class="text-success fs-11 text-uppercase fw-bold mb-1">
                                        {{ __('production.actual_produced') }}</div>
                                    <h3 class="text-success fw-bold mb-0">{{ number_format($order->quantity_produced, 2) }}
                                    </h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-soft-danger rounded p-3 text-center border border-danger">
                                    <div class="text-danger fs-11 text-uppercase fw-bold mb-1">
                                        {{ __('production.scrapped_qty') }}</div>
                                    <h3 class="text-danger fw-bold mb-0">{{ number_format($order->quantity_scrapped, 2) }}
                                    </h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="bg-soft-warning rounded p-3 text-center border border-warning">
                                    <div class="text-warning fs-11 text-uppercase fw-bold mb-1">
                                        {{ __('production.rejected_rework') }}</div>
                                    <h3 class="text-warning fw-bold mb-0">{{ number_format($order->quantity_rejected, 2) }}
                                    </h3>
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
                                            <td class="text-center fw-bold text-success">
                                                {{ number_format($rec->quantity_received, 2) }}</td>
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
                                                <i
                                                    class="feather-info fs-20 d-block mb-2"></i>{{ __('production.no_receipts_logged') }}
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
                                                <small class="text-muted d-block font-monospace fs-10">Op:
                                                    {{ $log->operation->operation_number ?? '—' }}</small>
                                            </td>
                                            <td class="text-center text-success fw-bold">
                                                {{ number_format($log->quantity_produced, 2) }}</td>
                                            <td class="text-center text-warning fw-bold">
                                                {{ number_format($log->quantity_rejected, 2) }}</td>
                                            <td class="text-center text-danger fw-bold">
                                                {{ number_format($log->quantity_scrapped, 2) }}</td>
                                            <td class="text-center">
                                                {{ number_format(($log->setup_minutes_logged + $log->run_minutes_logged) / 60, 2) }}
                                                hrs</td>
                                            <td>{{ $log->user->name ?? 'Operator' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i
                                                    class="feather-info fs-20 d-block mb-2"></i>{{ __('production.no_progress_logs_found') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 7: Scrap & Rework --}}
                    <div class="tab-pane fade {{ $activeTab === 'vtab-scrap' ? 'show active' : '' }}" id="vtab-scrap"
                        role="tabpanel" aria-labelledby="vtab-scrap-tab">
                        {{-- Horizontal Sub-tabs Navigation --}}
                        <x-ui.horizontal-tabs id="scrapReworkSubTabs" class="mb-4" :tabs="[
            ['id' => 'scrap-subtab', 'label' => __('production.scrap_log_entries'), 'active' => true, 'icon' => 'feather-trash-2 text-danger'],
            ['id' => 'rework-subtab', 'label' => __('production.rework_events_track'), 'active' => false, 'icon' => 'feather-refresh-cw text-warning']
        ]" />

                        <div class="tab-content border-0 p-0" id="scrapReworkSubTabsContent">
                            {{-- Scrap Logs Sub-tab --}}
                            <div class="tab-pane fade show active" id="scrap-subtab" role="tabpanel"
                                aria-labelledby="scrap-subtab-tab">
                                <div class="table-responsive">
                                    <table class="erp-thin-table">
                                        <thead>
                                            <tr>
                                                <th style="width:15%">{{ __('production.date') }}</th>
                                                <th style="width:25%">{{ __('production.item_component') }}</th>
                                                <th style="width:12%" class="text-center">{{ __('production.ordered_qty') }}
                                                </th>
                                                <th style="width:28%">{{ __('production.reason') }}</th>
                                                <th style="width:20%">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($order->scraps as $scr)
                                                <tr>
                                                    <td class="text-muted">{{ $scr->recorded_at->format('m-d H:i') }}</td>
                                                    <td>
                                                        <span
                                                            class="fw-bold text-dark">{{ $scr->product ? $scr->product->sku : 'Finished Good' }}</span>
                                                        @if($scr->operation)
                                                            <div class="text-muted fs-11">Op:
                                                                {{ $scr->operation->operation_number }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="text-center text-danger fw-bold">
                                                        {{ number_format($scr->quantity, 2) }}</td>
                                                    <td class="text-muted fs-12">{{ $scr->reason ?? '—' }}</td>
                                                    <td>
                                                        @if($scr->disposal)
                                                            @if($scr->disposal->status === 'approved')
                                                                <span class="badge bg-success text-white">Approved</span>
                                                            @elseif($scr->disposal->status === 'pending_approval')
                                                                <span class="badge bg-warning text-dark">Pending Approval</span>
                                                            @else
                                                                <span
                                                                    class="badge bg-secondary text-white">{{ ucfirst($scr->disposal->status) }}</span>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        {{ __('production.no_scrap_logged') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Rework Events Sub-tab --}}
                            <div class="tab-pane fade" id="rework-subtab" role="tabpanel"
                                aria-labelledby="rework-subtab-tab">
                                <div class="table-responsive">
                                    <table class="erp-thin-table">
                                        <thead>
                                            <tr>
                                                <th style="width:20%">{{ __('production.date') }}</th>
                                                <th style="width:25%">{{ __('production.operation') }}</th>
                                                <th style="width:12%" class="text-center">{{ __('production.ordered_qty') }}
                                                </th>
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
                                                    <td class="text-center text-warning fw-bold">
                                                        {{ number_format($rew->quantity, 2) }}</td>
                                                    <td>
                                                        @if($rew->status === 'completed')
                                                            <span class="badge bg-success text-white">Resolved (Recovered)</span>
                                                        @elseif($rew->status === 'failed')
                                                            <span class="badge bg-danger text-white">Failed (Scrapped)</span>
                                                        @elseif($rew->status === 'cancelled')
                                                            <span class="badge bg-secondary text-white">Cancelled</span>
                                                        @else
                                                            <span class="badge bg-warning text-dark">Rework Pending</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-muted fs-12">{{ $rew->reason ?? '—' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        {{ __('production.no_reworks_tracked') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tab: Cost Analysis --}}
                    <div class="tab-pane fade {{ $activeTab === 'vtab-cost' ? 'show active' : '' }}" id="vtab-cost"
                        role="tabpanel" aria-labelledby="vtab-cost-tab">
                        {{-- Cost KPI Row --}}
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="bg-light rounded p-4 text-center border">
                                    <span
                                        class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.total_planned_cost') }}</span>
                                    <h2 class="text-dark fw-bold mt-2 mb-0">
                                        {{ format_currency($costs['totals']['planned']) }}</h2>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-light rounded p-4 text-center border">
                                    <span
                                        class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.total_actual_cost') }}</span>
                                    <h2 class="text-dark fw-bold mt-2 mb-0">
                                        {{ format_currency($costs['totals']['actual']) }}</h2>
                                </div>
                            </div>
                            <div class="col-md-4">
                                @php $vVal = $costs['totals']['variance']; @endphp
                                <div
                                    class="bg-light rounded p-4 text-center border {{ $vVal > 0 ? 'border-danger' : ($vVal < 0 ? 'border-success' : '') }}">
                                    <span
                                        class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.variance') }}</span>
                                    <h2
                                        class="fw-bold mt-2 mb-0 {{ $vVal > 0 ? 'text-danger' : ($vVal < 0 ? 'text-success' : 'text-muted') }}">
                                        {{ format_currency($vVal) }}
                                        <span
                                            class="fs-12 fw-normal">({{ $costs['totals']['variance_percentage'] }}%)</span>
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
                                        <th style="width:25%" class="text-end">{{ __('production.variance') }}
                                            ({{ active_currency_symbol() }} / %)</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach([
                                            ['label' => __('production.material_costs'), 'key' => 'material'],
                                            ['label' => __('production.labor_cost'), 'key' => 'labor'],
                                            ['label' => __('production.machine_utilization_cost'), 'key' => 'machine'],
                                            ['label' => __('production.work_center_overhead'), 'key' => 'overhead'],
                                        ] as $row)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $row['label'] }}</td>
                                            <td class="text-end">{{ format_currency($costs[$row['key']]['planned']) }}</td>
                                            <td class="text-end">{{ format_currency($costs[$row['key']]['actual']) }}</td>
                                            <td
                                                class="text-end fw-bold {{ $costs[$row['key']]['variance'] > 0 ? 'text-danger' : 'text-success' }}">
                                                {{ format_currency($costs[$row['key']]['variance']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr class="table-light">
                                        <td class="fw-bold text-dark text-uppercase fs-12">{{ __('production.total_cost') }}
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            {{ format_currency($costs['totals']['planned']) }}</td>
                                        <td class="text-end fw-bold text-dark">
                                            {{ format_currency($costs['totals']['actual']) }}</td>
                                        <td
                                            class="text-end fw-bold {{ $costs['totals']['variance'] > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ format_currency($costs['totals']['variance']) }}
                                            ({{ $costs['totals']['variance_percentage'] }}%)
                                        </td>

                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Final Manufacturing Cost Summary (Automatic + Manual Adjustments) --}}
                        <h5 class="fw-bold text-dark mt-5 mb-3"><i class="feather-pie-chart text-primary me-2"></i>
                            {{ __('production.final_cost_breakdown') }}</h5>
                        <div class="table-responsive">
                            <table class="erp-thin-table">
                                <thead>
                                    <tr>
                                        <th style="width:25%">{{ __('production.cost_component') }}</th>
                                        <th style="width:25%" class="text-end">{{ __('production.automatic_cost') }}</th>
                                        <th style="width:25%" class="text-end">{{ __('production.manual_adjustments') }}
                                        </th>
                                        <th style="width:25%" class="text-end">{{ __('production.final_cost') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="fw-bold text-dark">{{ __('production.material_costs') }}</td>
                                        <td class="text-end">{{ format_currency($finalCostingSummary['material']['auto']) }}
                                        </td>
                                        <td class="text-end text-warning fw-semibold">
                                            {{ format_currency($finalCostingSummary['material']['manual']) }}</td>
                                        <td class="text-end fw-bold text-dark">
                                            {{ format_currency($finalCostingSummary['material']['final']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-dark">{{ __('production.labor_cost') }}</td>
                                        <td class="text-end">{{ format_currency($finalCostingSummary['labor']['auto']) }}
                                        </td>
                                        <td class="text-end text-warning fw-semibold">
                                            {{ format_currency($finalCostingSummary['labor']['manual']) }}</td>
                                        <td class="text-end fw-bold text-dark">
                                            {{ format_currency($finalCostingSummary['labor']['final']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-dark">{{ __('production.machine_utilization_cost') }}</td>
                                        <td class="text-end">{{ format_currency($finalCostingSummary['machine']['auto']) }}
                                        </td>
                                        <td class="text-end text-warning fw-semibold">
                                            {{ format_currency($finalCostingSummary['machine']['manual']) }}</td>
                                        <td class="text-end fw-bold text-dark">
                                            {{ format_currency($finalCostingSummary['machine']['final']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-dark">{{ __('production.work_center_overhead') }}</td>
                                        <td class="text-end">{{ format_currency($finalCostingSummary['overhead']['auto']) }}
                                        </td>
                                        <td class="text-end text-warning fw-semibold">
                                            {{ format_currency($finalCostingSummary['overhead']['manual']) }}</td>
                                        <td class="text-end fw-bold text-dark">
                                            {{ format_currency($finalCostingSummary['overhead']['final']) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-dark">{{ __('production.other_uncategorized_expenses') }}
                                        </td>
                                        <td class="text-end text-muted">{{ format_currency(0) }}</td>
                                        <td class="text-end text-warning fw-semibold">
                                            {{ format_currency($finalCostingSummary['other']['manual']) }}</td>
                                        <td class="text-end fw-bold text-dark">
                                            {{ format_currency($finalCostingSummary['other']['final']) }}</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td class="fw-bold text-dark text-uppercase fs-12">
                                            {{ __('production.total_manufacturing_cost') }}</td>
                                        <td class="text-end fw-bold text-dark">
                                            {{ format_currency($finalCostingSummary['totals']['auto']) }}</td>
                                        <td class="text-end fw-bold text-warning">
                                            {{ format_currency($finalCostingSummary['totals']['manual']) }}</td>
                                        <td class="text-end fw-bold text-primary fs-14">
                                            {{ format_currency($finalCostingSummary['totals']['final']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Day-Wise Production & Costing History Table --}}
                        <h5 class="fw-bold text-dark mt-5 mb-3"><i class="feather-calendar text-primary me-2"></i>
                            {{ __('production.day_wise_costing_history') }}</h5>
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
                                                <span
                                                    class="text-success fw-bold">{{ number_format($day['quantity_produced'], 2) }}</span>
                                                /
                                                <span
                                                    class="text-danger">{{ number_format($day['quantity_scrapped'] + $day['quantity_rejected'], 2) }}</span>
                                            </td>
                                            <td class="text-center fw-semibold">
                                                {{ number_format($day['total_minutes'] / 60, 2) }}h</td>
                                            <td>
                                                <small class="d-block text-dark">{{ $day['operators'] ?: '—' }}</small>
                                                <small
                                                    class="text-muted font-monospace fs-10">{{ $day['machines'] ?: '—' }}</small>
                                            </td>
                                            <td class="text-end fw-semibold text-dark">
                                                {{ format_currency($day['automatic_daily_cost']) }}</td>
                                            <td class="text-end text-warning fw-semibold">
                                                {{ format_currency($day['manual_daily_adjustment']) }}</td>
                                            <td class="text-end fw-bold text-primary">
                                                {{ format_currency($day['final_daily_cost']) }}</td>
                                            <td class="text-end text-muted">
                                                {{ format_currency($day['cumulative_automatic_cost']) }}</td>
                                            <td class="text-end text-warning">
                                                {{ format_currency($day['cumulative_manual_adjustment']) }}</td>
                                            <td class="text-end fw-bold text-dark">
                                                {{ format_currency($day['cumulative_final_cost']) }}</td>
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
                    <div class="tab-pane fade {{ $activeTab === 'vtab-cost-adjustments' ? 'show active' : '' }}"
                        id="vtab-cost-adjustments" role="tabpanel" aria-labelledby="vtab-cost-adjustments-tab">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-0"><i class="feather-dollar-sign text-primary me-2"></i>
                                    {{ __('production.manual_cost_adjustments') }}</h5>
                                <span class="fs-12 text-muted">{{ __('production.manual_cost_adjustments_desc') }}</span>
                            </div>
                            @if(!$order->isCompleted() && !$order->isClosed() && !$order->isCancelled())
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#addCostAdjustmentModal">
                                    <i class="feather-plus-circle me-1"></i> {{ __('production.add_cost_adjustment') }}
                                </button>
                            @endif
                        </div>

                        {{-- Summary Cards --}}
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card border bg-light py-2 px-3 text-center">
                                    <span
                                        class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.total_manual_adjustments') }}</span>
                                    <h4 class="fw-bold text-primary mb-0 mt-1">
                                        {{ format_currency($finalCostingSummary['totals']['manual']) }}</h4>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border bg-light py-2 px-3 text-center">
                                    <span
                                        class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.adjustment_records') }}</span>
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
                                            <td class="fw-bold text-dark font-monospace">
                                                {{ $adj->adjustment_date ? $adj->adjustment_date->format('Y-m-d') : '—' }}</td>
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
                                                    <a href="{{ route('production.cost-adjustments.download', $adj->id) }}"
                                                        class="btn btn-xs btn-outline-secondary"
                                                        title="{{ __('production.download_attachment') }}">
                                                        <i class="feather-paperclip me-1"></i> File
                                                    </a>
                                                @else
                                                    <span class="text-muted fs-11">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small
                                                    class="text-dark">{{ $adj->creator ? $adj->creator->name : 'System' }}</small>
                                            </td>
                                            <td class="text-end">
                                                @if(!$order->isCompleted() && !$order->isClosed() && !$order->isCancelled())
                                                    <x-ui.action-dropdown id="adjActionDropdown{{ $adj->id }}">
                                                        <li>
                                                            <a class="dropdown-item py-1.5 fs-12" href="javascript:void(0)"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editCostAdjustmentModal{{ $adj->id }}">
                                                                <i
                                                                    class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit_adjustment') }}
                                                            </a>
                                                        </li>
                                                        @if($adj->attachment_path)
                                                            <li>
                                                                <a class="dropdown-item py-1.5 fs-12"
                                                                    href="{{ route('production.cost-adjustments.download', $adj->id) }}">
                                                                    <i
                                                                        class="feather-paperclip me-2 text-muted fs-12"></i>{{ __('production.download_attachment') }}
                                                                </a>
                                                            </li>
                                                        @endif
                                                        <li>
                                                            <form method="POST"
                                                                action="{{ route('production.cost-adjustments.destroy', $adj->id) }}"
                                                                onsubmit="return confirm('Are you sure you want to soft-delete this cost adjustment?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                    class="dropdown-item text-danger py-1.5 fs-12">
                                                                    <i
                                                                        class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_adjustment') }}
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
                                                <i
                                                    class="feather-info me-1"></i>{{ __('production.no_cost_adjustments_recorded') }}
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
                    <div class="tab-pane fade {{ $activeTab === 'vtab-procurement' ? 'show active' : '' }}"
                        id="vtab-procurement" role="tabpanel" aria-labelledby="vtab-procurement-tab">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0"><i class="feather-truck text-primary me-2"></i>
                                {{ __('production.procurement_status') }}</h5>
                            <span class="fs-12 text-muted">{{ __('production.procurement_status_desc') }}</span>
                        </div>

                        @php
                            $slips = $order->requisitionSlips;
                            $totalSlips = $slips->count();
                            $pendingSlips = $slips->filter(function ($s) {
                                return strtolower($s->status ?? '') === 'pending';
                            })->count();
                            $allPrs = collect();
                            foreach ($slips as $s) {
                                foreach ($s->purchaseRequisitions as $pr) {
                                    $allPrs->push($pr);
                                }
                            }
                            $pendingPrs = $allPrs->where('status', 'Draft')->count();
                        @endphp

                        {{-- Summary Badges --}}
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="card border bg-light py-2 px-3 text-center">
                                    <span
                                        class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.total_requisition_slips') }}</span>
                                    <h4 class="fw-bold text-dark mb-0 mt-1">{{ $totalSlips }}</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border bg-light py-2 px-3 text-center">
                                    <span
                                        class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.pending_material_requests') }}</span>
                                    <h4 class="fw-bold text-warning mb-0 mt-1">{{ $pendingSlips }}</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border bg-light py-2 px-3 text-center">
                                    <span
                                        class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.linked_purchase_reqs') }}</span>
                                    <h4 class="fw-bold text-primary mb-0 mt-1">{{ $allPrs->count() }}</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border bg-light py-2 px-3 text-center">
                                    <span
                                        class="fs-11 text-muted text-uppercase fw-semibold">{{ __('production.pending_pr_approval') }}</span>
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
                                                <a href="{{ route('sales.material-requests.show', $slip->id) }}"
                                                    class="fw-bold text-primary font-monospace">
                                                    {{ $slip->requisition_number }}
                                                </a>
                                            </td>
                                            <td class="text-muted">{{ $slip->requisition_date }}</td>
                                            <td>
                                                @php
                                                    $stLower = strtolower($slip->status ?? 'pending');
                                                @endphp
                                                @if(in_array($stLower, ['fully issued', 'completed', 'issued']))
                                                    <span
                                                        class="badge bg-soft-success text-success text-uppercase">Fully Issued</span>
                                                @elseif(in_array($stLower, ['partially issued', 'partial', 'reserved']))
                                                    <span class="badge bg-soft-warning text-warning text-uppercase">{{ $stLower === 'reserved' ? 'Reserved' : 'Partially Issued' }}</span>
                                                @elseif($stLower === 'approved')
                                                    <span class="badge bg-soft-primary text-primary text-uppercase">Approved</span>
                                                @else
                                                    <span class="badge bg-soft-danger text-danger text-uppercase">Pending</span>
                                                @endif
                                            </td>
                                            <td class="text-center fw-semibold">{{ $slip->items->pluck('product_id')->unique()->count() }}</td>
                                            <td>
                                                @if($slip->purchaseRequisitions->isNotEmpty())
                                                    @foreach($slip->purchaseRequisitions as $pr)
                                                        <div>
                                                            @if(Route::has('purchase.requisitions.show'))
                                                                <a href="{{ route('purchase.requisitions.show', $pr->id) }}"
                                                                    class="fw-bold text-primary font-monospace">
                                                                    {{ $pr->requisition_number }}
                                                                </a>
                                                            @else
                                                                <span
                                                                    class="fw-bold text-dark font-monospace">{{ $pr->requisition_number }}</span>
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
                                                <i
                                                    class="feather-info fs-20 d-block mb-2"></i>{{ __('production.no_slips_recorded') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 8: Audit Trail --}}
                    <div class="tab-pane fade {{ $activeTab === 'vtab-audit' ? 'show active' : '' }}" id="vtab-audit"
                        role="tabpanel" aria-labelledby="vtab-audit-tab">
                        <h5 class="fw-bold text-dark mb-3">{{ __('production.audit_logs_trail') }}</h5>
                        <ul class="list-unstyled mb-0 fs-13">
                            <li class="mb-3 d-flex align-items-start">
                                <div class="avatar-text avatar-sm bg-soft-primary text-primary me-3 mt-1 rounded-circle">
                                    <i class="feather-user fs-14"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ __('production.order_created') }}</div>
                                    <div class="text-muted fs-11">By: {{ $order->creator->name ?? 'System' }} at
                                        {{ $order->created_at->format('Y-m-d H:i:s') }}</div>
                                </div>
                            </li>
                            @if($order->released_at)
                                <li class="mb-3 d-flex align-items-start">
                                    <div class="avatar-text avatar-sm bg-soft-success text-success me-3 mt-1 rounded-circle">
                                        <i class="feather-play-circle fs-14"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ __('production.order_released') }}</div>
                                        <div class="text-muted fs-11">By: {{ $order->releaser->name ?? 'System' }} at
                                            {{ $order->released_at->format('Y-m-d H:i:s') }}</div>
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
                                        <div class="text-muted fs-11">By: {{ $order->completer->name ?? 'System' }} at
                                            {{ $order->completed_at->format('Y-m-d H:i:s') }}</div>
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
                                        <div class="text-muted fs-11">By: {{ $order->closer->name ?? 'System' }} at
                                            {{ $order->closed_at->format('Y-m-d H:i:s') }}</div>
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

                <x-ui.odoo-form-ui type="select" label="{{ __('production.select_operation') }}" name="operation_id"
                    id="op_select_id" :required="true">
                    @foreach($order->operations as $op)
                        @if($op->status !== 'completed')
                            <option value="{{ $op->id }}">{{ $op->operation_number }} — {{ html_entity_decode($op->name ?? '', ENT_QUOTES, 'UTF-8') }}</option>
                        @endif
                    @endforeach
                </x-ui.odoo-form-ui>

                {{-- Operation Stats Block --}}
                <div id="op_stats_container" class="mb-3 bg-light border p-3 rounded d-none text-start">
                    <div class="row g-3 fs-12 text-dark">
                        <div class="col-6">
                            <span class="text-muted d-block fs-10 text-uppercase fw-bold">Operation Target</span>
                            <strong class="text-dark fs-14" id="stat_planned_qty">0.00</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block fs-10 text-uppercase fw-bold">Remaining Limit</span>
                            <strong class="text-info fs-14" id="stat_remaining_qty">0.00</strong>
                        </div>
                        <div class="col-4 border-top pt-2">
                            <span class="text-muted d-block fs-10 text-uppercase fw-bold">Already Produced</span>
                            <strong class="text-success" id="stat_produced_qty">0.00</strong>
                        </div>
                        <div class="col-4 border-top pt-2">
                            <span class="text-muted d-block fs-10 text-uppercase fw-bold">Already Rejected</span>
                            <strong class="text-warning" id="stat_rejected_qty">0.00</strong>
                        </div>
                        <div class="col-4 border-top pt-2">
                            <span class="text-muted d-block fs-10 text-uppercase fw-bold">Already Scrapped</span>
                            <strong class="text-danger" id="stat_scrapped_qty">0.00</strong>
                        </div>
                    </div>
                </div>

                <div id="modal_validation_warning" class="alert alert-danger d-none py-2 px-3 fs-12 mb-3 text-start">
                    <i class="feather-alert-triangle me-1"></i>
                    <span>The sum of Entered Quantities exceeds the remaining limit!</span>
                </div>

                <div class="row g-2 mb-1 fs-13 text-dark">
                    <div class="col-4">
                        <x-ui.odoo-form-ui type="input" label="{{ __('production.qty_produced') }}" name="quantity_produced"
                            inputType="number" step="0.0001" value="0" :required="true" />
                    </div>
                    <div class="col-4">
                        <x-ui.odoo-form-ui type="input" label="{{ __('production.qty_rejected') }}" name="quantity_rejected"
                            inputType="number" step="0.0001" value="0" :required="true" />
                    </div>
                    <div class="col-4">
                        <x-ui.odoo-form-ui type="input" label="{{ __('production.qty_scrapped') }}" name="quantity_scrapped"
                            inputType="number" step="0.0001" value="0" :required="true" />
                    </div>
                </div>

                <div class="row g-2 mb-1 fs-13 text-dark">
                    <div class="col-6">
                        <x-ui.odoo-form-ui type="input" label="{{ __('production.setup_minutes') }}"
                            name="setup_minutes_logged" inputType="number" value="0" :required="true" />
                    </div>
                    <div class="col-6">
                        <x-ui.odoo-form-ui type="input" label="{{ __('production.run_minutes') }}" name="run_minutes_logged"
                            inputType="number" value="0" :required="true" />
                    </div>
                </div>

                <x-ui.odoo-form-ui type="input" label="{{ __('production.remarks') }}" name="remarks"
                    placeholder="E.g. operator name, work center notes" />

                <div class="odoo-form-group">
                    <label class="odoo-form-label">{{ __('production.completion') }}</label>
                    <div class="flex-grow-1">
                        <div class="form-check form-switch pt-1">
                            <input class="form-check-input" type="checkbox" name="complete_operation" value="1"
                                id="complete_operation">
                            <label class="form-check-label fw-bold text-dark fs-12 ms-2"
                                for="complete_operation">{{ __('production.mark_operation_completed') }}</label>
                        </div>
                    </div>
                </div>
            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand"
                    data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                <button type="submit" class="btn btn-primary" id="btn_save_progress"
                    onclick="document.getElementById('progressForm').submit();">{{ __('production.save_progress_log') }}</button>
            </x-slot>
        </x-ui.modal>

        @php
            $opsWithTarget = $order->operations->map(function ($op) use ($order) {
                $arr = $op->toArray();
                if (!empty($op->target_produced_qty) && (float) $op->target_produced_qty > 0) {
                    $arr['computed_target_qty'] = (float) $op->target_produced_qty;
                } elseif (!empty($op->source_product_id) && $op->source_product_id !== $order->product_id) {
                    $bomItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $op->tenant_id)
                        ->where('bom_id', $order->bom_id)
                        ->where('material_id', $op->source_product_id)
                        ->first();
                    $ratio = ($bomItem && (float) $bomItem->quantity > 0) ? (float) $bomItem->quantity : 1.0;
                    $arr['computed_target_qty'] = (float) $order->quantity_ordered * $ratio;
                } else {
                    $arr['computed_target_qty'] = (float) ($order->quantity_ordered ?? 0.0);
                }
                return $arr;
            });
        @endphp

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const operationsData = @json($opsWithTarget);
                const plannedQty = {{ (float) $order->quantity_ordered }};

                const opSelect = document.getElementById('op_select_id');
                const statsContainer = document.getElementById('op_stats_container');
                const statPlanned = document.getElementById('stat_planned_qty');
                const statRemaining = document.getElementById('stat_remaining_qty');
                const statProduced = document.getElementById('stat_produced_qty');
                const statRejected = document.getElementById('stat_rejected_qty');
                const statScrapped = document.getElementById('stat_scrapped_qty');

                const inputProduced = document.querySelector('input[name="quantity_produced"]');
                const inputRejected = document.querySelector('input[name="quantity_rejected"]');
                const inputScrapped = document.querySelector('input[name="quantity_scrapped"]');
                const inputSetup = document.querySelector('input[name="setup_minutes_logged"]');
                const inputRun = document.querySelector('input[name="run_minutes_logged"]');

                const warningBox = document.getElementById('modal_validation_warning');
                const submitBtn = document.getElementById('btn_save_progress');

                function updateStatsAndValidation(shouldAutofill = false) {
                    const opId = parseInt(opSelect.value);
                    const op = operationsData.find(o => o.id === opId);

                    if (!op) {
                        statsContainer.classList.add('d-none');
                        return;
                    }

                    statsContainer.classList.remove('d-none');

                    // Calculate cumulative scrap from preceding operations in sequence order
                    const precedingScrap = operationsData
                        .filter(o => o.sequence < op.sequence)
                        .reduce((acc, o) => acc + parseFloat(o.quantity_scrapped || 0), 0);

                    // Operation target: use operation-level computed target (for SFGs/subassemblies) if available, minus preceding scrap
                    const baseTarget = (op.computed_target_qty !== undefined && op.computed_target_qty !== null)
                        ? parseFloat(op.computed_target_qty)
                        : plannedQty;
                    const opTarget = Math.max(0, baseTarget - precedingScrap);

                    const produced = parseFloat(op.quantity_produced || 0);
                    const rejected = parseFloat(op.quantity_rejected || 0);
                    const scrapped = parseFloat(op.quantity_scrapped || 0);

                    // Remaining Limit for this operation is opTarget - (already produced + already rejected + already scrapped)
                    const processed = produced + rejected + scrapped;
                    const remaining = Math.max(0, opTarget - processed);

                    statPlanned.textContent = opTarget.toFixed(2);
                    statRemaining.textContent = remaining.toFixed(2);
                    statProduced.textContent = produced.toFixed(2);
                    statRejected.textContent = rejected.toFixed(2);
                    statScrapped.textContent = scrapped.toFixed(2);

                    if (shouldAutofill && inputProduced) {
                        inputProduced.value = remaining.toFixed(4);
                    }

                    const completeSwitch = document.getElementById('complete_operation');
                    if (completeSwitch && remaining <= 0) {
                        completeSwitch.checked = true;
                    }

                    // Autofill run minutes from shopfloor schedule operation
                    if (inputRun && op.schedule_operation && op.schedule_operation.actual_start) {
                        const startDt = new Date(op.schedule_operation.actual_start);

                        let endDt;
                        if (op.schedule_operation.status === 'completed') {
                            endDt = op.schedule_operation.actual_finish ? new Date(op.schedule_operation.actual_finish) : new Date();
                        } else if (op.schedule_operation.status === 'paused') {
                            endDt = op.schedule_operation.last_paused_at ? new Date(op.schedule_operation.last_paused_at) : new Date();
                        } else {
                            endDt = new Date();
                        }

                        const diffSeconds = (endDt.getTime() - startDt.getTime()) / 1000;
                        const pausedSec = op.schedule_operation.accumulated_paused_seconds || 0;
                        const activeSeconds = Math.max(0, diffSeconds - pausedSec);
                        const runMinutes = Math.round(activeSeconds / 60);
                        inputRun.value = runMinutes;
                    } else if (inputRun) {
                        inputRun.value = 0;
                    }

                    // Validate inputs
                    const inProduced = parseFloat(inputProduced.value || 0);
                    const inRejected = parseFloat(inputRejected.value || 0);
                    const inScrapped = parseFloat(inputScrapped.value || 0);

                    const sum = inProduced + inRejected + inScrapped;

                    if (sum > remaining) {
                        warningBox.classList.remove('d-none');
                        warningBox.querySelector('span').textContent = `The sum of Entered Quantities (${sum.toFixed(2)}) exceeds the remaining limit of ${remaining.toFixed(2)}!`;
                        submitBtn.disabled = true;
                    } else {
                        warningBox.classList.add('d-none');
                        submitBtn.disabled = false;
                    }
                }

                if (opSelect) {
                    opSelect.addEventListener('change', () => updateStatsAndValidation(true));
                    if (window.jQuery) {
                        window.jQuery(opSelect).on('change', () => updateStatsAndValidation(true));
                    }

                    [inputProduced, inputRejected, inputScrapped].forEach(input => {
                        if (input) {
                            input.addEventListener('input', () => updateStatsAndValidation(false));
                            input.addEventListener('change', () => updateStatsAndValidation(false));
                        }
                    });

                    const progressModalEl = document.getElementById('progressModal');
                    if (progressModalEl) {
                        progressModalEl.addEventListener('shown.bs.modal', function () {
                            updateStatsAndValidation(true);
                        });
                    }

                    // Initialize
                    updateStatsAndValidation(true);
                }

                // Live Timers for Running/Paused Operations in the UI
                const liveTimers = document.querySelectorAll('.live-timer');
                if (liveTimers.length > 0) {
                    function updateLiveTimers() {
                        const now = new Date();
                        liveTimers.forEach(el => {
                            const status = el.getAttribute('data-status');
                            const startVal = el.getAttribute('data-start');
                            if (!startVal) {
                                el.textContent = '';
                                return;
                            }

                            const start = new Date(startVal);
                            let end;

                            if (status === 'running') {
                                end = now;
                            } else if (status === 'paused') {
                                const pausedAtVal = el.getAttribute('data-paused-at');
                                end = pausedAtVal ? new Date(pausedAtVal) : now;
                            } else {
                                el.textContent = '';
                                return;
                            }

                            const diffSeconds = (end.getTime() - start.getTime()) / 1000;
                            const pausedSec = parseInt(el.getAttribute('data-accumulated-paused') || 0);
                            const activeSeconds = Math.max(0, diffSeconds - pausedSec);

                            const minutes = Math.floor(activeSeconds / 60);
                            const seconds = Math.floor(activeSeconds % 60);

                            if (status === 'running') {
                                el.innerHTML = `<span class="badge bg-soft-info text-info border border-info fs-10 py-0.5 px-1.5 d-inline-flex align-items-center gap-1 mt-1"><i class="feather-clock fs-10"></i> ${minutes}m ${seconds}s</span>`;
                            } else if (status === 'paused') {
                                el.innerHTML = `<span class="badge bg-soft-warning text-warning border border-warning fs-10 py-0.5 px-1.5 d-inline-flex align-items-center gap-1 mt-1"><i class="feather-pause-circle fs-10"></i> ${minutes}m ${seconds}s</span>`;
                            }
                        });
                    }

                    updateLiveTimers();
                    setInterval(updateLiveTimers, 1000);
                }
            });
        </script>

        {{-- Issue Materials Modal --}}
        <x-ui.modal id="issueModal" title="{{ __('production.issue_raw_material') }}" class="text-start">
            <form method="POST" action="{{ route('production.orders.issue', $order->id) }}" id="issueForm">
                @csrf

                <x-ui.odoo-form-ui type="select" label="{{ __('production.reservation') }}" name="reservation_id"
                    id="issue_reservation_id" :required="true">
                    @foreach($order->reservations as $res)
                        <option value="{{ $res->id }}">
                            {{ $res->product->name }} ({{ $res->product->sku }}) — Reserved:
                            {{ number_format($res->quantity_reserved, 2) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>

                <x-ui.odoo-form-ui type="select" label="{{ __('production.warehouse') }}" name="warehouse_id">
                    <option value="">{{ __('production.use_reservation_warehouse') }}</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>

                <x-ui.odoo-form-ui type="input" label="{{ __('production.ordered_qty') }}" name="quantity"
                    inputType="number" step="0.0001" :required="true" />

                <x-ui.odoo-form-ui type="input" label="{{ __('production.remarks') }}" name="remarks" />
            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand"
                    data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                <button type="submit" class="btn btn-info text-white"
                    onclick="document.getElementById('issueForm').submit();">{{ __('production.log_material_issue') }}</button>
            </x-slot>
        </x-ui.modal>

        {{-- Return Materials Modal --}}
        <x-ui.modal id="returnModal" title="{{ __('production.return_materials_stock') }}" class="text-start">
            <form method="POST" action="{{ route('production.orders.return', $order->id) }}" id="returnForm">
                @csrf

                <x-ui.odoo-form-ui type="select" label="{{ __('production.reservation') }}" name="reservation_id"
                    id="return_reservation_id" :required="true">
                    @foreach($order->reservations as $res)
                        <option value="{{ $res->id }}">
                            {{ $res->product->name }} ({{ $res->product->sku }}) — Issued:
                            {{ number_format($res->quantity_issued, 2) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>

                <x-ui.odoo-form-ui type="select" label="{{ __('production.warehouse') }}" name="warehouse_id">
                    <option value="">{{ __('production.use_reservation_warehouse') }}</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>

                <x-ui.odoo-form-ui type="input" label="{{ __('production.return_qty') }}" name="quantity" inputType="number"
                    step="0.0001" :required="true" />

                <x-ui.odoo-form-ui type="input" label="{{ __('production.remarks') }}" name="remarks" />
            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand"
                    data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                <button type="submit" class="btn btn-primary"
                    onclick="document.getElementById('returnForm').submit();">{{ __('production.process_return') }}</button>
            </x-slot>
        </x-ui.modal>

        {{-- Receive Finished Goods Modal --}}
        <x-ui.modal id="receiptModal" title="{{ __('production.receive_fg_title') }}" class="text-start">
            <form method="POST" action="{{ route('production.orders.receive-fg', $order->id) }}" id="receiptForm">
                @csrf

                <div class="mb-3 bg-light p-3 rounded fs-13 text-dark">
                    <label
                        class="form-label fw-bold text-muted fs-11 text-uppercase mb-1">{{ __('production.target_product') }}</label>
                    <div class="text-dark fw-bold">{{ $order->product->name }} ({{ $order->product->sku }})</div>
                </div>

                <x-ui.odoo-form-ui type="input" label="{{ __('production.qty_received') }}" name="quantity_received"
                    inputType="number" step="0.0001" :required="true" />

                <x-ui.odoo-form-ui type="select" label="{{ __('production.warehouse') }}" name="warehouse_id">
                    <option value="">{{ __('production.use_default_warehouse') }}</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>

                <x-ui.odoo-form-ui type="select" label="{{ __('production.quality_status') }}" name="quality_status"
                    :required="true">
                    <option value="passed">{{ __('production.passed_inventory') }}</option>
                    <option value="quarantine">{{ __('production.quarantine_inspection') }}</option>
                    <option value="failed">{{ __('production.failed_defective') }}</option>
                </x-ui.odoo-form-ui>

                <x-ui.odoo-form-ui type="input" label="{{ __('production.remarks') }}" name="remarks" />
            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand"
                    data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                <button type="submit" class="btn btn-warning text-dark"
                    onclick="document.getElementById('receiptForm').submit();">{{ __('production.confirm_fg_receipt') }}</button>
            </x-slot>
        </x-ui.modal>

        {{-- Log Scrap / Rework Modal --}}
        <x-ui.modal id="scrapReworkModal" title="{{ __('production.log_scrap_rework_title') }}" size="lg"
            class="text-start">
            {{-- Inner tab nav --}}
            <ul class="nav nav-tabs mb-3" id="scrapReworkTabNav" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="sr-scrap-tab" data-bs-toggle="tab" data-bs-target="#sr-scrap"
                        type="button" role="tab">{{ __('production.log_scrap_tab') }}</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="sr-rework-tab" data-bs-toggle="tab" data-bs-target="#sr-rework"
                        type="button" role="tab">{{ __('production.log_rework_tab') }}</button>
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
                                <option value="{{ $res->product_id }}">{{ $res->product->name }} ({{ $res->product->sku }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" label="{{ __('production.scrapped_qty') }}" name="quantity"
                            inputType="number" step="0.0001" :required="true" />

                        <x-ui.odoo-form-ui type="input" label="{{ __('production.reason') }}" name="reason"
                            placeholder="E.g. material defect, processing error" :required="true" />

                        <div class="text-end mt-3 border-top pt-2">
                            <button type="button" class="btn btn-light-brand me-2"
                                data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                            <button type="submit" class="btn btn-danger">{{ __('production.log_scrap_tab') }}</button>
                        </div>
                    </form>
                </div>

                {{-- Rework Tab --}}
                <div class="tab-pane fade" id="sr-rework" role="tabpanel">
                    <form method="POST" action="{{ route('production.orders.log-rework', $order->id) }}" id="reworkForm">
                        @csrf

                        <x-ui.odoo-form-ui type="select" label="{{ __('production.rework_target') }}" name="operation_id"
                            :required="true">
                            @foreach($order->operations as $op)
                                <option value="{{ $op->id }}">Op {{ $op->operation_number }} — {{ $op->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" label="{{ __('production.rework_qty') }}" name="quantity"
                            inputType="number" step="0.0001" :required="true" />

                        <x-ui.odoo-form-ui type="input" label="{{ __('production.rework_notes') }}" name="reason"
                            placeholder="Describe issue and corrective actions" :required="true" />

                        <div class="text-end mt-3 border-top pt-2">
                            <button type="button" class="btn btn-light-brand me-2"
                                data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                            <button type="submit"
                                class="btn btn-warning text-dark">{{ __('production.log_rework_tab') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Empty footer slot --}}
            <x-slot name="footer"></x-slot>
        </x-ui.modal>

        {{-- Request Additional Material Modal --}}
        <x-ui.modal id="requestAdditionalMaterialModal" title="{{ __('production.request_additional_material') }}" size="lg"
            class="text-start">
            <form method="POST" action="{{ route('production.orders.request-additional-material', $order->id) }}"
                id="additionalMaterialForm">
                @csrf

                <div class="bg-light p-3 rounded mb-3 border fs-13">
                    <div class="row g-2">
                        <div class="col-6">
                            <span
                                class="text-muted d-block fs-11 text-uppercase">{{ __('production.production_order') }}</span>
                            <strong class="text-dark fs-14">{{ $order->order_number }}</strong>
                        </div>
                        <div class="col-6">
                            <span
                                class="text-muted d-block fs-11 text-uppercase">{{ __('production.target_product') }}</span>
                            <strong class="text-dark fs-14">{{ $order->product->name }}
                                ({{ $order->product->sku }})</strong>
                        </div>
                    </div>
                </div>

                <p class="fs-12 text-muted mb-2">Select components and enter the additional quantity requested from
                    warehouse or procurement:</p>

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
                                        <input type="checkbox" name="items[{{ $idx }}][selected]" value="1"
                                            class="form-check-input item-checkbox" id="chk_{{ $idx }}" checked>
                                        <input type="hidden" name="items[{{ $idx }}][product_id]"
                                            value="{{ $res->product_id }}">
                                    </td>
                                    <td>
                                        <label for="chk_{{ $idx }}"
                                            class="fw-bold text-dark mb-0 cursor-pointer">{{ $res->product->name }}</label>
                                        <div class="text-muted font-monospace fs-10">{{ $res->product->sku }}</div>
                                    </td>
                                    <td class="text-center fw-semibold text-dark">{{ number_format($res->quantity_planned, 2) }}
                                    </td>
                                    <td class="text-center text-success fw-bold">{{ number_format($res->quantity_issued, 2) }}
                                    </td>
                                    <td class="text-center text-danger fw-bold">{{ number_format($shortage, 2) }}</td>
                                    <td>
                                        <input type="number" name="items[{{ $idx }}][quantity]"
                                            class="form-control form-control-sm" step="0.0001" min="0.0001"
                                            value="{{ $shortage > 0 ? $shortage : 1 }}">
                                    </td>
                                    <td>
                                        <input type="text" name="items[{{ $idx }}][notes]" class="form-control form-control-sm"
                                            placeholder="e.g. Extra scrap">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.odoo-form-ui type="input" label="{{ __('production.requisition_notes_reason') }}" name="notes"
                    placeholder="Reason for additional material request..." />

            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand"
                    data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                <button type="button" class="btn btn-primary"
                    onclick="submitAdHocForm()">{{ __('production.submit_requisition') }}</button>
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
        <x-ui.modal id="addCostAdjustmentModal" title="{{ __('production.add_manual_cost_adjustment') }}" size="lg"
            class="text-start" :showFooter="true">
            <form method="POST" action="{{ route('production.orders.cost-adjustments.store', $order->id) }}"
                enctype="multipart/form-data" id="addCostAdjustmentForm">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="{{ __('production.date') }}" name="adjustment_date"
                            inputType="date" value="{{ now()->toDateString() }}" :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="{{ __('production.cost_component') }}" name="cost_component"
                            :required="true">
                            @foreach($costComponents as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" label="{{ __('production.category') }}" name="category"
                            :required="true">
                            @foreach($categories as $catKey => $catLabel)
                                <option value="{{ $catKey }}">{{ $catLabel }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input"
                            label="{{ __('production.amount') }} ({{ active_currency_symbol() }})" name="amount"
                            inputType="number" step="0.01" min="0.01" placeholder="0.00" :required="true" />
                    </div>
                </div>

                <x-ui.odoo-form-ui type="input" label="{{ __('production.description') }}" name="description"
                    placeholder="Brief explanation of manual expense" :required="true" />

                <div class="mb-3">
                    <label class="form-label fs-12 fw-semibold text-dark">{{ __('production.attachment_label') }}</label>
                    <input type="file" name="attachment" class="form-control form-control-sm"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip">
                    <small class="text-muted fs-11">{{ __('production.supported_formats') }}</small>
                </div>

                <x-ui.odoo-form-ui type="textarea" label="{{ __('production.remarks') }}" name="notes"
                    placeholder="Additional details or remarks..." rows="2" />
            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand"
                    data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                <button type="button" class="btn btn-primary"
                    onclick="document.getElementById('addCostAdjustmentForm').submit();">{{ __('production.save_cost_adjustment') }}</button>
            </x-slot>
        </x-ui.modal>

        {{-- Edit Cost Adjustment Modals --}}
        @foreach($costAdjustments as $adj)
            <x-ui.modal id="editCostAdjustmentModal{{ $adj->id }}"
                title="{{ __('production.edit_cost_adjustment', ['id' => $adj->id]) }}" size="lg" class="text-start"
                :showFooter="true">
                <form method="POST" action="{{ route('production.cost-adjustments.update', $adj->id) }}"
                    enctype="multipart/form-data" id="editCostAdjustmentForm{{ $adj->id }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="{{ __('production.date') }}" name="adjustment_date"
                                inputType="date"
                                value="{{ $adj->adjustment_date ? $adj->adjustment_date->format('Y-m-d') : '' }}"
                                :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('production.cost_component') }}" name="cost_component"
                                :required="true">
                                @foreach($costComponents as $key => $label)
                                    <option value="{{ $key }}" {{ $adj->cost_component === $key ? 'selected' : '' }}>{{ $label }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('production.category') }}" name="category"
                                :required="true">
                                @foreach($categories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}" {{ $adj->category === $catKey ? 'selected' : '' }}>{{ $catLabel }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input"
                                label="{{ __('production.amount') }} ({{ active_currency_symbol() }})" name="amount"
                                inputType="number" step="0.01" min="0.01"
                                value="{{ number_format(convert_from_base($adj->amount), 2, '.', '') }}" :required="true" />
                        </div>
                    </div>

                    <x-ui.odoo-form-ui type="input" label="{{ __('production.description') }}" name="description"
                        value="{{ $adj->description }}" :required="true" />

                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold text-dark">{{ __('production.attachment_label') }}</label>
                        <input type="file" name="attachment" class="form-control form-control-sm"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip">
                        @if($adj->attachment_path)
                            <small class="text-success d-block mt-1">Existing file uploaded. Selecting a new file will replace
                                it.</small>
                        @endif
                    </div>

                    <x-ui.odoo-form-ui type="textarea" label="{{ __('production.remarks') }}" name="notes"
                        value="{{ $adj->notes }}" rows="2" />
                </form>
                <x-slot name="footer">
                    <button type="button" class="btn btn-light-brand"
                        data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                    <button type="button" class="btn btn-primary"
                        onclick="document.getElementById('editCostAdjustmentForm{{ $adj->id }}').submit();">{{ __('production.update_adjustment') }}</button>
                </x-slot>
            </x-ui.modal>
        @endforeach

        {{-- Assign Operator Modal (for Production Order view) --}}
        <x-ui.modal id="orderAssignOperatorModal" title="Assign Operator to Operation" class="text-start"
            :showFooter="true">
            <form method="POST" action="{{ route('production.mes.assignments.assign') }}" id="orderAssignForm">
                @csrf

                <input type="hidden" name="production_order_operation_id" id="assign_op_id" value="">

                <x-ui.odoo-form-ui type="select" label="Select Operator" name="user_id" :required="true">
                    <option value="">-- Choose Operator --</option>
                    @foreach($operators as $operator)
                        <option value="{{ $operator->id }}">{{ $operator->name }} ({{ ucfirst($operator->role) }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>

                <x-ui.odoo-form-ui type="textarea" label="Remarks / Instructions" name="remarks"
                    placeholder="Provide shift remarks or operation requirements..." rows="3" />
            </form>
            <x-slot name="footer">
                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary"
                    onclick="document.getElementById('orderAssignForm').submit();">Assign Operator</button>
            </x-slot>
        </x-ui.modal>

        @if($order->schedules->isEmpty())
            {{-- Generate Schedule Modal --}}
            <x-ui.modal id="scheduleModal" title="{{ __('production.generate_schedule') }}" class="text-start">
                <form method="POST" action="{{ route('production.schedules.store') }}" id="scheduleForm">
                    @csrf

                    <input type="hidden" name="production_order_id" value="{{ $order->id }}">

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted fs-12">{{ __('production.production_order') }}</label>
                        <div class="p-2.5 bg-light rounded text-dark fs-13 border">
                            <strong>ID:</strong> {{ $order->id }} <br>
                            <strong>{{ __('production.order_number') }}:</strong> {{ $order->order_number }}
                        </div>
                    </div>

                    <x-ui.odoo-form-ui type="input" :label="__('production.schedule_start_date')" name="start_date"
                        inputType="datetime-local" :value="old('start_date', now()->format('Y-m-d\TH:i'))" :required="true" />

                    <x-ui.odoo-form-ui type="select" :label="__('production.scheduling_type')" name="scheduling_type"
                        :required="true">
                        <option value="forward" {{ old('scheduling_type', 'forward') === 'forward' ? 'selected' : '' }}>
                            {{ __('production.forward_scheduling') }}
                        </option>
                        <option value="backward" {{ old('scheduling_type') === 'backward' ? 'selected' : '' }}>
                            {{ __('production.backward_scheduling') }}
                        </option>
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="textarea" :label="__('production.description') ?? 'Notes'" name="notes"
                        placeholder="Optional scheduling notes or remarks..." :value="old('notes')" />
                </form>
                <x-slot name="footer">
                    <button type="button" class="btn btn-light-brand"
                        data-bs-dismiss="modal">{{ __('production.cancel') }}</button>
                    <button type="submit" class="btn btn-primary text-white"
                        onclick="document.getElementById('scheduleForm').submit();">{{ __('production.generate_schedule') }}</button>
                </x-slot>
            </x-ui.modal>
        @endif

    </div>{{-- end .erp-single-panel --}}

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // 1. Synchronize URL query parameter & active classes when user switches tabs (desktop & mobile)
            document.querySelectorAll('#productionOrderVerticalTabs, #mobileProductionOrderTabs').forEach(tabContainer => {
                tabContainer.addEventListener('click', function (e) {
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
                form.addEventListener('submit', function () {
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

    <script>
        function loadWorkCenterBatchPage(orderId, workCenterId, page) {
            var tbody = document.getElementById('wc-batches-tbody-' + orderId + '-' + workCenterId);
            var paginationContainer = document.getElementById('wc-batches-pagination-' + orderId + '-' + workCenterId);
            if (!tbody) return;

            tbody.style.opacity = '0.5';
            var url = "{{ url('production/wip/orders') }}/" + orderId + "/work-centers/" + workCenterId + "/batches?page=" + page;

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    tbody.style.opacity = '1';
                    if (data.success) {
                        tbody.innerHTML = data.html;
                        if (paginationContainer) {
                            renderWcPaginationControls(paginationContainer, orderId, workCenterId, data.current_page, data.last_page, data.total, data.per_page);
                        }
                    }
                })
                .catch(function () { tbody.style.opacity = '1'; });
        }

        function renderWcPaginationControls(container, orderId, workCenterId, currentPage, lastPage, total, perPage) {
            if (lastPage <= 1) { container.innerHTML = ''; return; }
            total = total || (lastPage * (perPage || 5));
            perPage = perPage || 5;
            var from = Math.min((currentPage - 1) * perPage + 1, total);
            var to = Math.min(currentPage * perPage, total);

            var html = '<div class="erp-pagination-container border-top"><ul class="erp-pagination">';

            // Prev
            html += '<li class="page-item' + (currentPage <= 1 ? ' disabled' : '') + '">';
            html += '<button type="button" class="page-link"' + (currentPage <= 1 ? ' disabled' : '') + ' onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',' + (currentPage - 1) + ')" aria-label="Previous"><i class="feather-chevron-left"></i></button></li>';

            // Page numbers (window of 5)
            var start = Math.max(1, currentPage - 2);
            var end = Math.min(lastPage, currentPage + 2);
            if (start > 1) {
                html += '<li class="page-item"><button type="button" class="page-link" onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',1)">1</button></li>';
                if (start > 2) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            for (var i = start; i <= end; i++) {
                html += '<li class="page-item' + (currentPage === i ? ' active' : '') + '">';
                html += '<button type="button" class="page-link" onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',' + i + ')">' + i + '</button></li>';
            }
            if (end < lastPage) {
                if (end < lastPage - 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
                html += '<li class="page-item"><button type="button" class="page-link" onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',' + lastPage + ')">' + lastPage + '</button></li>';
            }

            // Next
            html += '<li class="page-item' + (currentPage >= lastPage ? ' disabled' : '') + '">';
            html += '<button type="button" class="page-link"' + (currentPage >= lastPage ? ' disabled' : '') + ' onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',' + (currentPage + 1) + ')" aria-label="Next"><i class="feather-chevron-right"></i></button></li>';

            html += '</ul><div class="erp-pagination-info">Showing ' + from + ' to ' + to + ' of ' + total + ' batches</div></div>';
            container.innerHTML = html;
        }
    </script>
@endsection