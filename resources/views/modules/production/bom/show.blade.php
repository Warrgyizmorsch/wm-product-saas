@extends('layouts.duralux')

@section('title', __('production.bom_details') . ' | SaaS ERP')

@section('page-actions')
    <x-ui.button href="{{ route('production.boms.index') }}" variant="secondary" icon="feather-arrow-left" class="me-2">
        {{ __('production.back_to_list') }}
    </x-ui.button>
    
    @if($bom->isDraft() || $bom->isUnderRevision())
        <x-ui.button href="{{ route('production.boms.edit', $bom->id) }}" variant="primary" icon="feather-edit" class="me-2">
            {{ __('production.edit_draft') }}
        </x-ui.button>

        <form method="POST" action="{{ route('production.boms.submit', $bom->id) }}" class="d-inline me-2">
            @csrf
            <x-ui.button type="submit" variant="info" icon="feather-send">
                {{ __('production.submit_approval') }}
            </x-ui.button>
        </form>

        @can('delete', $bom)
            <form id="delete-draft-bom-form" method="POST" action="{{ route('production.boms.destroy', $bom->id) }}" class="d-inline me-2">
                @csrf
                @method('DELETE')
                <x-ui.button type="button" variant="danger" icon="feather-trash-2" onclick="deleteDraftBom()">
                    {{ __('production.delete') }}
                </x-ui.button>
            </form>
        @endcan
    @endif

    @if($bom->isPendingApproval())
        <form method="POST" action="{{ route('production.boms.approve', $bom->id) }}" class="d-inline me-2">
            @csrf
            <x-ui.button type="submit" variant="success" icon="feather-check-circle">
                {{ __('production.approve_bom') }}
            </x-ui.button>
        </form>
        <x-ui.button type="button" variant="danger" icon="feather-x-circle" class="me-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
            {{ __('production.reject') }}
        </x-ui.button>
    @endif

    @if($bom->isApproved())
        <x-ui.button type="button" variant="danger" icon="feather-slash" class="me-2" data-bs-toggle="modal" data-bs-target="#cancelModal">
            {{ __('production.cancel_bom') }}
        </x-ui.button>
    @endif

    <x-ui.button type="button" variant="light-brand" icon="feather-copy" data-bs-toggle="modal" data-bs-target="#duplicateModal">
        {{ __('production.duplicate_version') }}
    </x-ui.button>
@endsection

@push('styles')
    <style>
        .bom-table-enhanced {
            font-variant-numeric: tabular-nums;
        }
        .bom-table-enhanced th,
        .bom-table-enhanced td {
            padding: 9px 12px !important;
            vertical-align: middle !important;
            font-family: inherit !important;
        }
        .bom-table-enhanced thead th {
            background-color: #f8fafc !important;
            border-bottom: 2px solid #e2e8f0 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            color: #475569 !important;
            text-transform: uppercase;
        }
        .bom-table-enhanced tbody tr {
            transition: background-color 0.15s ease-in-out;
        }
        .bom-table-enhanced tbody tr:hover {
            background-color: #f8fafc !important;
        }
        .sub-bom-link-box {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 600;
            background-color: rgba(40, 167, 69, 0.1) !important;
            color: #198754 !important;
            border: 1px solid rgba(40, 167, 69, 0.28) !important;
            border-radius: 6px;
            text-decoration: none !important;
            transition: all 0.2s ease-in-out;
            max-width: 100%;
            line-height: 1.35;
        }
        .sub-bom-link-box:hover {
            background-color: rgba(40, 167, 69, 0.2) !important;
            border-color: #198754 !important;
            color: #0f5132 !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(40, 167, 69, 0.15);
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white">
        <!-- Success & Error Banners (Rendered via Toast Component) -->
        @if(isset($parentProduct))
            <div class="alert alert-success border-success bg-soft-success d-flex align-items-center justify-content-between p-3 mb-4 rounded shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <div class="avatar-text avatar-md bg-success text-white me-3">
                        <i class="feather-check-circle"></i>
                    </div>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1 text-success">{{ __('production.child_bom_success') }}</h6>
                        <p class="fs-12 mb-0 text-success-800">Configure child BOM for <strong>{{ $bom->product?->name ?? 'Product' }}</strong>. The parent form has been updated automatically. You can close this tab now to return to the parent form.</p>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    @if(isset($parentBom))
                        <x-ui.button href="{{ route('production.boms.show', $parentBom->id) }}" variant="success" class="text-white" icon="feather-arrow-left">
                            {{ __('production.return_to_parent') }}
                        </x-ui.button>
                        <x-ui.button href="{{ route('production.boms.edit', $parentBom->id) }}" variant="outline-success" class="bg-white" icon="feather-edit">
                            {{ __('production.edit_parent') }}
                        </x-ui.button>
                    @else
                        <x-ui.button href="{{ route('production.boms.create') }}?product_id={{ $parentProduct->id }}" variant="success" class="text-white" icon="feather-plus">
                            {{ __('production.return_to_add_parent') }}
                        </x-ui.button>
                    @endif
                    <x-ui.button type="button" variant="secondary" class="ms-2" onclick="window.close();" icon="feather-x">
                        {{ __('production.close_tab') }}
                    </x-ui.button>
                </div>
            </div>
        @elseif (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <!-- BOM Details Header Grid -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <h4 class="fw-bold text-dark mb-0">{{ __('production.bom_details') }} (BOM #{{ $bom->bom_number }})</h4>
            <div>
                @php
                    $todayStr = now()->toDateString();
                    $effDateStr = $bom->effective_date ? $bom->effective_date->toDateString() : null;
                    $expDateStr = $bom->expiry_date ? $bom->expiry_date->toDateString() : null;
                @endphp
                @if($bom->status === 'draft')
                    <span class="erp-badge-draft">{{ __('production.draft') }}</span>
                @elseif($bom->status === 'pending_approval')
                    <span class="erp-badge-pending">{{ __('production.pending') }}</span>
                @elseif($bom->status === 'cancelled')
                    <span class="erp-badge-cancelled">{{ __('production.cancelled') }}</span>
                @elseif($bom->status === 'scheduled' || (in_array($bom->status, ['approved', 'active']) && $effDateStr && $effDateStr > $todayStr))
                    <span class="badge bg-soft-warning text-warning rounded-pill px-2 py-1 font-monospace fw-bold">{{ __('production.scheduled') }}</span>
                @elseif($bom->status === 'inactive' || ($expDateStr && $expDateStr <= $todayStr && !$bom->isCurrentlyActive()))
                    <span class="erp-badge-inactive">{{ __('production.inactive') }}</span>
                @elseif(in_array($bom->status, ['approved', 'active']))
                    <span class="erp-badge-active">{{ __('production.active') }}</span>
                @else
                    <span class="erp-badge-draft text-uppercase">{{ __('production.' . $bom->status) ?? $bom->status }}</span>
                @endif

                @php
                    $bomComponentWarnings = $bom->getComponentWarnings();
                    $isCancelledOrInactive = in_array($bom->status, ['cancelled', 'inactive'], true);
                @endphp
                @if(!$isCancelledOrInactive && (!empty($bomComponentWarnings) || $bom->has_warning))
                    <span class="badge bg-soft-warning text-warning border border-warning-subtle rounded-pill px-2 py-1 font-monospace fw-bold ms-1"
                          title="{{ $bom->warning_reason ?: $bom->getComponentWarningSummary() }}">
                        <i class="feather-alert-triangle me-1"></i>Component Warning
                    </span>
                @endif
            </div>
        </div>

        @if(!$isCancelledOrInactive && !empty($bomComponentWarnings))
            <div class="alert alert-warning border-warning bg-soft-warning p-3 mb-4 rounded shadow-sm" role="alert">
                <div class="d-flex align-items-start">
                    <div class="avatar-text avatar-sm bg-warning text-white me-3 mt-1 flex-shrink-0">
                        <i class="feather-alert-triangle"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading fw-bold mb-1 text-warning-800">
                            Component BOM Warning
                        </h6>
                        <ul class="mb-0 ps-3 fs-12 text-dark">
                            @foreach($bomComponentWarnings as $warn)
                                <li>{{ $warn['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-4 mb-4">
            <div class="col-md-6 border-end">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">{{ __('production.bom_name') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $bom->bom_name ?: 'N/A' }}</span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">{{ __('production.item_to_produce') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $bom->product?->name ?? '—' }} @if($bom->product?->sku)({{ $bom->product->sku }})@endif</span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">{{ __('production.bom_type') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-soft-info text-info text-capitalize">
                            @if($bom->bom_type === 'manufacturing')
                                {{ __('production.bom_type_manufacturing') }}
                            @elseif($bom->bom_type === 'engineering')
                                {{ __('production.bom_type_engineering') }}
                            @elseif($bom->bom_type === 'sales')
                                {{ __('production.bom_type_sales') }}
                            @elseif($bom->bom_type === 'phantom')
                                {{ __('production.bom_type_phantom') }}
                            @elseif($bom->bom_type === 'subcontracting')
                                {{ __('production.bom_type_subcontracting') }}
                            @else
                                {{ $bom->bom_type }}
                            @endif
                        </span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">{{ __('production.base_quantity') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ number_format($bom->base_quantity, 2) }} {{ $bom->baseUom ? $bom->baseUom->code : 'PCS' }}</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">{{ __('production.version') }} &amp; {{ __('production.revision') ?? 'Revision' }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ __('production.version') }}: v{{ $bom->version }} ({{ __('production.revision') ?? 'Revision' }}: r{{ $bom->revision }})</span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">{{ __('production.routing_reference') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $bom->routing ? $bom->routing->routing_number . ' - ' . $bom->routing->name : __('production.no_routing') }}</span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">{{ __('production.validity') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            {{ __('production.start_date') }}: {{ $bom->effective_date ? $bom->effective_date->format('d/m/Y') : 'N/A' }} 
                            {{ $bom->expiry_date ? ' ' . __('production.expiry_date') . ': ' . $bom->expiry_date->format('d/m/Y') : ' (' . (__('production.no_expiry') ?? 'No Expiry') . ')' }}
                        </span>
                    </div>
                </div>

                @if($bom->approved_at)
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-4">
                            <span class="fw-semibold text-muted fs-13">Action / Approved Date:</span>
                        </div>
                        <div class="col-md-8">
                            <span class="text-dark fw-bold fs-13">
                                {{ $bom->approved_at->format('d/m/Y') }}
                            </span>
                        </div>
                    </div>
                @endif

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">{{ __('production.revision_reason') }}:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-semibold italic fs-12">{{ $bom->revision_reason ?: (__('production.no_revision_reason') ?? 'No revision reason provided.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($bom->notes)
            <div class="mb-4 bg-light p-3 rounded border border-dashed">
                <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-2">{{ __('production.revision_notes') }}</span>
                <p class="mb-0 text-dark fs-13 text-justify">{{ $bom->notes }}</p>
            </div>
        @endif

        <!-- TAB NAVIGATION -->
        <x-ui.horizontal-tabs id="bomDetailsTabs" :tabs="[
            ['id' => 'tab-components', 'label' => __('production.components'), 'active' => true, 'icon' => 'feather-list'],
            ['id' => 'tab-explosion', 'label' => __('production.multilevel_bom_explosion'), 'icon' => 'feather-activity'],
            ['id' => 'tab-routing', 'label' => __('production.workflow_routing'), 'icon' => 'feather-sliders'],
            ['id' => 'tab-costing', 'label' => __('production.est_material_cost_summary'), 'icon' => 'feather-dollar-sign'],
            ['id' => 'tab-history', 'label' => __('production.audit_log_history'), 'icon' => 'feather-clock'],
            ['id' => 'tab-whereused', 'label' => __('production.where_used') ?? 'Where Used', 'icon' => 'feather-git-merge']
        ]" />

        <!-- TAB CONTENT CONTAINER -->
        <div class="tab-content mt-3">
            <!-- Tab 1: Components -->
            <div class="tab-pane fade show active" id="tab-components" role="tabpanel" aria-labelledby="tab-components-tab">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.bom_specification') }}</h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" class="bom-table-enhanced">
                        <thead>
                            <tr>
                                <th style="width: 6%" class="text-center">{{ __('production.seq') }}</th>
                                <th style="width: 29%">{{ __('production.component_product') }}</th>
                                <th style="width: 11%" class="text-end text-nowrap">{{ __('production.net_qty') }}</th>
                                <th style="width: 8%" class="text-end text-nowrap">{{ __('production.scrap_percent') }}</th>
                                <th style="width: 11%" class="text-end text-nowrap">{{ __('production.gross_qty') }}</th>
                                <th style="width: 11%" class="text-end text-nowrap">{{ __('production.unit_cost') }}</th>
                                <th style="width: 11%" class="text-end text-nowrap">{{ __('production.net_cost') }}</th>
                                <th style="width: 13%" class="text-end text-nowrap">{{ __('production.gross_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                if (!function_exists('renderComponentTreeRows')) {
                                    function renderComponentTreeRows($items, $level = 1, $seqPrefix = '', $parentMultiplier = 1.0) {
                                        $idx = 1;
                                        foreach ($items as $item) {
                                            $padding = ($level - 1) * 20;
                                            
                                            // Hierarchical sequence numbering (e.g. 1, 1.1, 1.1.1)
                                            $itemSeqNum = $item->sequence ?: $idx;
                                            $seq = $seqPrefix !== '' ? ($seqPrefix . '.' . $itemSeqNum) : (string) $itemSeqNum;
                                            
                                            // Effective Scaled Quantities down the multi-level tree
                                            $effectiveNetQty = (float) $item->quantity * $parentMultiplier;
                                            $scrapPct = (float) ($item->material_scrap_percentage ?? 0.0);
                                            $scrapFactor = 1 + ($scrapPct / 100);
                                            $effectiveGrossQty = $effectiveNetQty * $scrapFactor;
                                            
                                            // Financial Costing
                                            $unitCost = (float) ($item->material?->unit_cost ?? 0.0);
                                            $netCost = $effectiveNetQty * $unitCost;
                                            $grossCost = $effectiveGrossQty * $unitCost;
                                            $uomCode = $item->uom?->code ?? $item->material?->uom?->code ?? 'PCS';
                                            
                                            echo '<tr class="' . ($level > 1 ? 'table-light bg-light-soft erp-child-bom-row' : '') . '">';
                                            
                                            // 1. Sequence Number
                                            echo '<td class="text-center align-middle">';
                                            echo '<span class="badge bg-light text-secondary border px-2 py-1 fs-11 rounded-1 fw-medium">' . e($seq) . '</span>';
                                            echo '</td>';
                                            
                                            // 2. Component Product with Hierarchy Indentation
                                            echo '<td class="align-middle">';
                                            echo '<div style="padding-left: ' . $padding . 'px;" class="d-flex align-items-center">';
                                            if ($level > 1) {
                                                echo '<i class="feather-corner-down-right text-muted me-2 fs-11 opacity-75"></i>';
                                            }
                                            echo '<div class="d-flex flex-column">';
                                            $matName = $item->material?->name ?? 'Unknown Component';
                                            $matSku = $item->material?->sku ?? '—';
                                            echo '<span class="fw-semibold text-dark fs-13 mb-0.5">' . e($matName) . '</span>';
                                            echo '<span class="text-muted fs-11">' . e($matSku) . '</span>';
                                            
                                            if (!empty($item->notes)) {
                                                echo '<small class="text-muted fst-italic fs-11 mt-0.5"><i class="feather-file-text me-1 text-secondary"></i>' . e($item->notes) . '</small>';
                                            }
                                            
                                            if ($item->is_alternative) {
                                                $parentName = $item->primaryItem?->material?->name ?? ('Primary #' . $item->replaces_item_id);
                                                $parentSku = $item->primaryItem?->material?->sku ?? '';
                                                echo '<span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 mt-1 d-inline-block px-1.5 py-0.5">';
                                                echo '<i class="feather-shuffle me-1"></i>' . __('production.alternative_of') . ': ' . e($parentName) . ($parentSku ? ' (' . e($parentSku) . ')' : '') . ' • Priority ' . $item->priority;
                                                echo '</span>';
                                            }
                                            echo '</div>';
                                            echo '</div>';
                                            echo '</td>';
                                            
                                            // 3. Net Quantity with UOM
                                            echo '<td class="text-end align-middle text-nowrap">';
                                            if ($item->quantity_type === 'formula' && !empty($item->formula)) {
                                                echo '<span class="badge bg-soft-primary text-primary fs-10 mb-0.5 d-inline-block"><i class="feather-cpu me-1"></i>Formula</span>';
                                                echo '<span class="fw-semibold text-dark d-block fs-13">' . number_format($effectiveNetQty, (round($effectiveNetQty, 2) == 0 && $effectiveNetQty > 0) ? 4 : 2) . ' <span class="text-muted fw-normal fs-11 ms-1">' . e($uomCode) . '</span></span>';
                                                echo '<small class="text-muted fs-10 d-block" title="Formula Expression">Fx: ' . e($item->formula) . '</small>';
                                            } else {
                                                echo '<span class="fw-semibold text-dark fs-13">' . number_format($effectiveNetQty, (round($effectiveNetQty, 2) == 0 && $effectiveNetQty > 0) ? 4 : 2) . ' <span class="text-muted fw-normal fs-11 ms-1">' . e($uomCode) . '</span></span>';
                                            }
                                            echo '</td>';
                                            
                                            // 4. Scrap %
                                            echo '<td class="text-end align-middle text-nowrap">';
                                            if ($scrapPct > 0) {
                                                echo '<span class="badge bg-soft-danger text-danger border border-danger-subtle fs-11 fw-semibold px-2 py-0.5">+' . number_format($scrapPct, 2) . '%</span>';
                                            } else {
                                                echo '<span class="text-danger fs-12 fw-medium" style="opacity: 0.85;">0.00%</span>';
                                            }
                                            echo '</td>';
                                            
                                            // 5. Gross Qty with UOM
                                            echo '<td class="text-end align-middle text-nowrap">';
                                            if ($scrapPct > 0) {
                                                echo '<span class="fw-semibold text-danger fs-13">' . number_format($effectiveGrossQty, (round($effectiveGrossQty, 2) == 0 && $effectiveGrossQty > 0) ? 4 : 2) . ' <span class="text-muted fw-normal fs-11 ms-1">' . e($uomCode) . '</span></span>';
                                            } else {
                                                echo '<span class="fw-semibold text-dark fs-13">' . number_format($effectiveGrossQty, (round($effectiveGrossQty, 2) == 0 && $effectiveGrossQty > 0) ? 4 : 2) . ' <span class="text-muted fw-normal fs-11 ms-1">' . e($uomCode) . '</span></span>';
                                            }
                                            echo '</td>';
                                            
                                            // 6. Unit Cost
                                            echo '<td class="text-end align-middle text-nowrap">';
                                            echo '<span class="text-secondary fs-12 fw-normal">' . format_currency($unitCost) . '</span>';
                                            echo '</td>';
                                            
                                            // 7. Net Cost
                                            echo '<td class="text-end align-middle text-nowrap">';
                                            echo '<span class="text-secondary fs-12 fw-semibold">' . format_currency($netCost) . '</span>';
                                            echo '</td>';
                                            
                                            // 8. Gross Total Cost
                                            echo '<td class="text-end align-middle text-nowrap">';
                                            echo '<span class="fw-bold text-dark fs-13 d-block">' . format_currency($grossCost) . '</span>';
                                            if ($item->is_alternative) {
                                                echo '<span class="badge bg-soft-secondary text-muted border fs-10 mt-0.5 d-inline-block">Alternative</span>';
                                            }
                                            echo '</td>';
                                            
                                            echo '</tr>';
                                            
                                            // Recursively render child components with scaled quantities
                                            if ($item->childBom && $item->childBom->items && $item->childBom->items->count() > 0) {
                                                $childBase = (float) ($item->childBom->base_quantity > 0 ? $item->childBom->base_quantity : 1.0);
                                                $childMultiplier = $parentMultiplier * ((float) $item->quantity / $childBase);
                                                renderComponentTreeRows($item->childBom->items, $level + 1, $seq, $childMultiplier);
                                            }
                                            
                                            $idx++;
                                        }
                                    }
                                }
                            @endphp

                            @if($bom->items->count() > 0)
                                @php renderComponentTreeRows($bom->items) @endphp
                            @else
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">{{ __('production.no_components_spec_yet') }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Tab 2: Expanded Material Explosion -->
            <div class="tab-pane fade" id="tab-explosion" role="tabpanel" aria-labelledby="tab-explosion-tab">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.multilevel_bom_explosion') }}</h5>
                <p class="text-muted fs-12 mb-3">{{ __('production.explosion_desc') }}</p>
                
                @php
                    if (!function_exists('renderExplosionTableRows')) {
                        function renderExplosionTableRows($node, $level = 1) {
                            $padding = ($level - 1) * 20;
                            $isLeaf = empty($node['children']);
                            $hasSubBom = !empty($node['has_sub_bom']) && !empty($node['bom_id']);
                            $bomVersion = $node['bom_version'] ?? null;
                            $qty = isset($node['net_quantity']) ? $node['net_quantity'] : $node['quantity'];
                            $uom = $node['uom_code'] ?? 'PCS';
                            
                            echo '<tr>';
                            // 1. Level
                            echo '<td class="text-center align-middle">';
                            echo '<span class="badge rounded-circle ' . ($level === 1 ? 'bg-primary text-white shadow-sm' : ($level === 2 ? 'bg-info text-white' : 'bg-light text-secondary border')) . ' fs-11 fw-semibold" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center;">' . $level . '</span>';
                            echo '</td>';
                            
                            // 2. Component Product
                            echo '<td class="align-middle">';
                            echo '<div style="padding-left: ' . $padding . 'px;" class="d-flex align-items-center">';
                            if ($level === 1) {
                                echo '<i class="feather-layers text-primary me-2 fs-14"></i>';
                            } elseif (!$isLeaf) {
                                echo '<i class="feather-package text-info me-2 fs-13"></i>';
                            } else {
                                echo '<i class="feather-box text-muted me-2 fs-12"></i>';
                            }
                            echo '<div class="d-flex flex-column">';
                            echo '<div class="d-flex align-items-center flex-wrap gap-1">';
                            echo '<span class="fw-semibold text-dark fs-13">' . e($node['product_name']) . '</span>';
                            if ($level === 1) {
                                echo '<span class="badge bg-soft-primary text-primary border border-primary-subtle fs-10 rounded-pill px-2 py-0.5 fw-semibold ms-1">Finished Product</span>';
                            }
                            echo '</div>';
                            echo '<span class="text-muted fs-11">' . e($node['product_sku']) . '</span>';
                            echo '</div>';
                            echo '</div>';
                            echo '</td>';
                            
                            // 3. Linked Sub-BOM (Clickable Green Box)
                            echo '<td class="align-middle">';
                            if ($hasSubBom) {
                                $bomTitle = $node['bom_name'] ?: $node['bom_number'];
                                $bomRoute = route('production.boms.show', $node['bom_id']);
                                echo '<a href="' . $bomRoute . '" class="sub-bom-link-box" title="View Sub-BOM Details">';
                                echo '<i class="feather-link fs-11 flex-shrink-0"></i>';
                                echo '<span>Sub-BOM: <strong>' . e($bomTitle) . '</strong></span>';
                                if (!empty($bomVersion)) {
                                    echo '<span class="badge bg-success text-white fs-10 px-1.5 py-0.2 ms-1 rounded-1">v' . e($bomVersion) . '</span>';
                                }
                                echo '</a>';
                                if (!empty($node['bom_number']) && !empty($node['bom_name']) && $node['bom_name'] !== $node['bom_number']) {
                                    echo '<span class="text-muted fs-10 d-block mt-0.5 ps-1">' . e($node['bom_number']) . '</span>';
                                }
                            } else {
                                echo '<span class="text-muted fs-12 opacity-50">—</span>';
                            }
                            echo '</td>';
                            
                            // 4. BOM Base Qty
                            echo '<td class="text-end align-middle">';
                            if ($hasSubBom) {
                                echo '<span class="text-secondary fw-semibold fs-12">' . number_format($node['bom_base_quantity'] ?? 1.0, 2) . '</span>';
                            } else {
                                echo '<span class="text-muted fs-12 opacity-50">—</span>';
                            }
                            echo '</td>';
                            
                            // 5. Qty Required for Production
                            echo '<td class="text-end fw-bold text-dark align-middle fs-13">';
                            echo number_format($qty, (round($qty, 2) == 0 && $qty > 0) ? 4 : 2);
                            echo '</td>';
                            
                            // 6. UOM
                            echo '<td class="text-center align-middle">';
                            echo '<span class="badge bg-light text-secondary border fs-11 px-2 py-0.5 rounded-1 fw-medium">' . e($uom) . '</span>';
                            echo '</td>';
                            
                            // 7. Version
                            echo '<td class="text-center align-middle">';
                            if ($hasSubBom && $bomVersion) {
                                echo '<span class="badge bg-light text-dark border fs-11 px-1.5 py-0.5 rounded-1 fw-medium">v' . e($bomVersion) . '</span>';
                            } else {
                                echo '<span class="text-muted fs-12 opacity-50">—</span>';
                            }
                            echo '</td>';
                            
                            // 8. Lifecycle Status
                            echo '<td class="text-center align-middle">';
                            if ($hasSubBom) {
                                $st = strtolower($node['bom_status'] ?? 'approved');
                                if ($st === 'approved' || $st === 'active') {
                                    echo '<span class="badge bg-soft-success text-success border border-success-subtle fs-11 px-2 py-0.5 rounded-1">' . __('production.active') . '</span>';
                                } elseif ($st === 'cancelled') {
                                    echo '<span class="badge bg-soft-danger text-danger border border-danger-subtle fs-11 px-2 py-0.5 rounded-1">Cancelled</span>';
                                } elseif (in_array($st, ['inactive', 'superseded'])) {
                                    echo '<span class="badge bg-soft-secondary text-secondary border fs-11 px-2 py-0.5 rounded-1">' . ucfirst($st) . '</span>';
                                } else {
                                    echo '<span class="badge bg-soft-warning text-warning border fs-11 px-2 py-0.5 rounded-1">' . ucfirst($st) . '</span>';
                                }
                            } else {
                                echo '<span class="text-muted fs-12 opacity-50">—</span>';
                            }
                            echo '</td>';
                            echo '</tr>';
                            
                            if (!empty($node['children'])) {
                                foreach ($node['children'] as $child) {
                                    renderExplosionTableRows($child, $level + 1);
                                }
                            }
                        }
                    }
                @endphp

                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" class="bom-table-enhanced">
                        <thead>
                            <tr>
                                <th style="width: 6%" class="text-center">{{ __('production.level') }}</th>
                                <th style="width: 27%">{{ __('production.component_product') }}</th>
                                <th style="width: 24%">{{ __('production.linked_sub_bom') }}</th>
                                <th style="width: 11%" class="text-end text-nowrap">{{ __('production.bom_base_qty') }}</th>
                                <th style="width: 14%" class="text-end text-nowrap">{{ __('production.qty_required') }}</th>
                                <th style="width: 6%" class="text-center">{{ __('production.uom') ?? 'UOM' }}</th>
                                <th style="width: 6%" class="text-center">{{ __('production.version') }}</th>
                                <th style="width: 6%" class="text-center">{{ __('production.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(!empty($explosion['tree']))
                                @php renderExplosionTableRows($explosion['tree']) @endphp
                            @else
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">{{ __('production.no_components_spec_yet') }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Tab 3: Routing Process -->
            <div class="tab-pane fade" id="tab-routing" role="tabpanel" aria-labelledby="tab-routing-tab">
                @if($bom->routing)
                    <div class="mb-4 p-3 bg-light rounded border border-light">
                        <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-1">{{ __('production.routing_reference') }}</span>
                        <h5 class="fw-bold text-dark mb-1">{{ $bom->routing->name }} ({{ $bom->routing->routing_number }})</h5>
                        <span class="fs-12 text-muted">{{ __('production.version') }}: v{{ $bom->routing->version }} | {{ __('production.status') }}: 
                            <span class="badge bg-soft-success text-success text-uppercase font-monospace fs-10">{{ $bom->routing->status }}</span>
                        </span>
                    </div>

                    <h5 class="fw-bold text-dark mb-3">{{ __('production.workflow_routing') }}</h5>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table">
                            <thead>
                                <tr>
                                    <th style="width: 5%" class="text-center">{{ __('production.seq') }}</th>
                                    <th style="width: 25%">{{ __('production.operation_stage') }}</th>
                                    <th style="width: 15%">{{ __('production.type') }}</th>
                                    <th style="width: 20%">{{ __('production.work_center') }}</th>
                                    <th style="width: 15%">{{ __('production.machine') }}</th>
                                    <th class="text-end" style="width: 10%">{{ __('production.times_yield') }}</th>
                                    <th class="text-center" style="width: 10%">QC Gate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($bom->routing->operations as $op)
                                    <tr>
                                        <td class="fw-bold text-center font-monospace align-middle">{{ $op->sequence }}</td>
                                        <td class="align-middle">
                                            <span class="fw-bold text-dark">{{ $op->name }}</span>
                                            <span class="badge bg-soft-primary text-primary font-monospace ms-1 fs-9">{{ $op->operation_number }}</span>
                                            @if ($op->description)
                                                <small class="text-muted d-block mt-1">{{ $op->description }}</small>
                                            @endif
                                            @if ($op->is_external)
                                                <span class="badge bg-soft-danger text-danger mt-1 fs-9 text-uppercase">{{ __('production.outsourced') ?? 'Outsourced' }}</span>
                                            @endif
                                            
                                            <!-- Consumed operation-level materials -->
                                            @if($op->materials->count() > 0)
                                                <div class="mt-2 bg-white p-2 rounded border border-dashed">
                                                    <small class="fw-bold text-muted d-block mb-1 text-uppercase fs-9">{{ __('production.allocated_consumed_materials') ?? 'Allocated Consumed Materials:' }}</small>
                                                    <ul class="mb-0 ps-3 fs-10 text-muted">
                                                        @foreach($op->materials as $opMat)
                                                            <li>
                                                                 <span class="text-secondary fw-semibold">{{ __('production.seq') }} {{ $opMat->sequence }}:</span>
                                                                <strong>{{ $opMat->material?->name ?? 'Material' }}</strong>: {{ number_format($opMat->quantity, 2) }} {{ $opMat->uom?->code ?? 'PCS' }}
                                                                <span class="badge bg-light text-dark fs-8">{{ $opMat->consumption_type ?? 'manual' }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="align-middle">
                                            <span class="badge bg-soft-secondary text-secondary text-uppercase fs-10">
                                                {{ config('production.operation_types')[$op->operation_type] ?? $op->operation_type }}
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            @if ($op->workCenter)
                                                <div class="d-flex flex-column">
                                                    <a href="{{ route('production.work-centers.show', $op->work_center_id) }}" class="fw-bold text-primary fs-13">
                                                        {{ $op->workCenter->name }}
                                                    </a>
                                                    @if($op->workCenter->parent)
                                                        <small class="text-muted fs-10">
                                                            {{ $op->workCenter->parent->parent ? $op->workCenter->parent->parent->name . ' › ' : '' }}{{ $op->workCenter->parent->name }}
                                                        </small>
                                                    @endif
                                                    <small class="text-secondary font-monospace fs-10 mt-1">{{ $op->workCenter->code }}</small>
                                                </div>
                                            @else
                                                <span class="text-danger">{{ __('production.no_selection') }}</span>
                                            @endif
                                        </td>
                                        <td class="align-middle">
                                            @if ($op->machine)
                                                <span class="fw-semibold text-dark">{{ $op->machine->name }}</span>
                                                <small class="text-muted d-block fs-10">{{ $op->machine->code }}</small>
                                            @else
                                                <span class="text-muted">{{ __('production.generic_capacity') ?? 'Generic Capacity' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end align-middle font-monospace fs-11">
                                            <div>{{ __('production.setup') }}: {{ number_format($op->setup_time_minutes, 1) }} min</div>
                                            <div>{{ __('production.run') }}: {{ number_format($op->processing_time_minutes, 1) }} min</div>
                                        </td>
                                        <td class="text-center align-middle">
                                            @if ($op->quality_required)
                                                <span class="badge bg-soft-danger text-danger"><i class="feather-shield"></i> QC</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">{{ __('production.no_operations_defined') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                @else
                    <div class="p-4 text-center border rounded bg-light text-muted">
                        <i class="feather-info me-2"></i>{{ __('production.no_routing') }}
                    </div>
                @endif
            </div>

            <!-- Tab 4: Cost Summary -->
            <div class="tab-pane fade" id="tab-costing" role="tabpanel" aria-labelledby="tab-costing-tab">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.est_material_cost_summary') }}</h5>
                <div class="row g-3 mb-4">
                    <!-- Net Material Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.component_cost_net') }}</span>
                            <h4 class="text-dark fw-bold mt-1">{{ format_currency($costSummary['material_cost'] - $costSummary['scrap_adjustment']) }}</h4>
                            <small class="text-muted">{{ __('production.net_scrap_loss_desc') ?? 'Net of scrap loss' }}</small>
                        </div>
                    </div>
                    <!-- Scrap Loss -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold text-danger">{{ __('production.scrap_loss') ?? 'Scrap Loss' }}</span>
                            <h4 class="text-danger fw-bold mt-1">{{ format_currency($costSummary['scrap_adjustment']) }}</h4>
                            <small class="text-muted">{{ __('production.expected_scrap_desc') ?? 'Expected material scrap value' }}</small>
                        </div>
                    </div>
                    <!-- Labor Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.labor_cost') ?? 'Labor Cost' }}</span>
                            <h4 class="text-dark fw-bold mt-1">{{ format_currency($costSummary['labor_cost']) }}</h4>
                            <small class="text-muted">{{ __('production.labor_desc') ?? 'Routing setup & run labor' }}</small>
                        </div>
                    </div>
                    <!-- Machine Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('production.machine_cost') ?? 'Machine Cost' }}</span>
                            <h4 class="text-dark fw-bold mt-1">{{ format_currency($costSummary['machine_cost']) }}</h4>
                            <small class="text-muted">{{ __('production.machine_desc') ?? 'Machine runtime cost' }}</small>
                        </div>
                    </div>
                    <!-- Overhead Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Work Center Overhead</span>
                            <h4 class="text-dark fw-bold mt-1">{{ format_currency($costSummary['overhead_cost']) }}</h4>
                            <small class="text-muted">WC hourly overhead rate x duration</small>
                        </div>
                    </div>
                    <!-- Total Mfg Cost -->
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded border text-center bg-soft-primary border-primary">
                            <span class="text-primary fs-11 text-uppercase fw-bold">{{ __('production.total_mfg_cost') ?? 'Total Manufacturing Cost' }}</span>
                            <h4 class="text-primary fw-bold mt-1">{{ format_currency($costSummary['total_cost']) }}</h4>
                            <small class="text-primary">{{ __('production.mfg_desc') ?? 'Sum of all cost layers' }}</small>
                        </div>
                    </div>
                </div>

                <!-- Cost Details Item Table -->
                <h6 class="fw-bold text-dark mb-3">{{ __('production.component_cost_gross') }}</h6>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 40%">{{ __('production.component_product') }}</th>
                                <th style="width: 15%" class="text-end">{{ __('production.base_qty') }}</th>
                                <th style="width: 10%" class="text-end">{{ __('production.scrap_percent') }}</th>
                                <th style="width: 15%" class="text-end">{{ __('production.gross_qty_required') }}</th>
                                <th style="width: 10%" class="text-end">{{ __('production.unit_cost') }}</th>
                                <th style="width: 10%" class="text-end">{{ __('production.total_item_cost') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($costSummary['items'] as $cItem)
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark">{{ $cItem['material_name'] }}</span>
                                            <small class="text-muted font-monospace fs-10">{{ $cItem['material_sku'] }}</small>
                                        </div>
                                    </td>
                                    <td class="text-end">{{ number_format($cItem['quantity'], 2) }} {{ $cItem['uom_code'] }}</td>
                                    <td class="text-end text-danger">{{ number_format($cItem['scrap_percentage'], 2) }}%</td>
                                    <td class="text-end fw-bold">{{ number_format($cItem['gross_quantity'], 2) }} {{ $cItem['uom_code'] }}</td>
                                    <td class="text-end">{{ format_currency($cItem['unit_cost']) }}</td>
                                    <td class="text-end text-dark fw-bold">{{ format_currency($cItem['total_cost']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">{{ __('production.no_pricing_components') }}</td>
                                </tr>
                            @endforelse
                            <tr class="table-light fw-bold">
                                <td colspan="5" class="text-end">{{ __('production.est_total_material_cost') }}</td>
                                <td class="text-end text-primary fs-14">{{ format_currency($materialCost) }}</td>
                            </tr>
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

            </div>

            <!-- Tab 5: Approval History -->
            <div class="tab-pane fade" id="tab-history" role="tabpanel" aria-labelledby="tab-history-tab">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.audit_log_history') }}</h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 20%">{{ __('production.timestamp') }}</th>
                                <th style="width: 20%">{{ __('production.user') }}</th>
                                <th style="width: 20%">{{ __('production.transition') }}</th>
                                <th style="width: 40%">{{ __('production.notes_reasons') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bom->approvals as $approval)
                                <tr>
                                    <td>{{ $approval->created_at->format('d/m/Y H:i:s') }}</td>
                                    <td>{{ $approval->user ? $approval->user->name : __('production.system_auto') }}</td>
                                    <td>
                                        <span class="badge bg-soft-info text-info text-capitalize">{{ str_replace('_', ' ', $approval->action ?? $approval->transition_type ?? '') }}</span>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ $approval->comments ?: '—' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">{{ __('production.no_history_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Tab 6: Where Used -->
            <div class="tab-pane fade" id="tab-whereused" role="tabpanel" aria-labelledby="tab-whereused-tab">
                <h5 class="fw-bold text-dark mb-3"><i class="feather-git-merge me-2 text-primary"></i>{{ __('production.where_used') }}</h5>
                @if($whereUsedBoms->count() > 0)
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table">
                            <thead>
                                <tr>
                                    <th style="width: 30%">{{ __('production.parent_product_name') }}</th>
                                    <th style="width: 20%">{{ __('production.bom_number') }}</th>
                                    <th style="width: 10%">{{ __('production.version') }}</th>
                                    <th style="width: 15%">{{ __('production.status') }}</th>
                                    <th style="width: 10%">{{ __('production.start_date') }}</th>
                                    <th style="width: 10%">{{ __('production.expiry_date') }}</th>
                                    <th style="width: 5%" class="text-end">{{ __('production.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($whereUsedBoms as $wBom)
                                    <tr>
                                        <td class="fw-bold text-dark">
                                            {{ $wBom->product?->name ?? '—' }}
                                            <small class="text-muted d-block font-monospace fs-10">{{ $wBom->product?->sku ?? '—' }}</small>
                                        </td>
                                        <td>{{ $wBom->bom_number }}</td>
                                        <td>v{{ $wBom->version }}</td>
                                        <td>
                                            @if($wBom->status === 'approved')
                                                <span class="badge bg-soft-success text-success">{{ __('production.active') }}</span>
                                            @elseif($wBom->status === 'draft')
                                                <span class="badge bg-soft-warning text-warning">{{ __('production.draft') }}</span>
                                            @elseif($wBom->status === 'pending_approval')
                                                <span class="badge bg-soft-info text-info">{{ __('production.pending') }}</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary text-uppercase">{{ $wBom->status }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $wBom->effective_date ? $wBom->effective_date->format('d/m/Y') : 'N/A' }}</td>
                                        <td>{{ $wBom->expiry_date ? $wBom->expiry_date->format('d/m/Y') : 'No Expiry' }}</td>
                                        <td class="text-end">
                                            <x-ui.button href="{{ route('production.boms.show', $wBom->id) }}" variant="soft-primary" class="px-2 py-1 fs-11 text-nowrap" icon="feather-eye">
                                                {{ __('production.view') }} BOM
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                @else
                    <div class="p-3 text-center border rounded bg-light text-muted fs-12">
                        <i class="feather-info me-2"></i>This product is not consumed in any other parent assembly BOMs.
                    </div>
                @endif
            </div>
        </div>

        <!-- Modals -->
        <!-- Duplicate Modal -->
        <x-ui.modal id="duplicateModal" :title="__('production.duplicate_bom_version')" :submit-text="__('production.create_version')" class="text-start">
            <form method="POST" action="{{ route('production.boms.duplicate', $bom->id) }}" id="dupForm">
                @csrf
                <p class="fs-13 text-muted">{{ __('production.duplicate_modal_body') }}</p>
                <x-ui.input :label="__('production.new_version_name')" name="new_version" placeholder="e.g. 1.1.0 or 2.0.0" required />
            </form>
            <x-slot name="footer">
                <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" onclick="document.getElementById('dupForm').submit();">{{ __('production.duplicate_version') }}</x-ui.button>
            </x-slot>
        </x-ui.modal>

        <!-- Reject Modal -->
        <x-ui.modal id="rejectModal" :title="__('production.reject_bom_version')" :submit-text="__('production.reject_version')" class="text-start">
            <form method="POST" action="{{ route('production.boms.reject', $bom->id) }}" id="rejectForm">
                @csrf
                <p class="fs-13 text-muted">{{ __('production.reject_modal_body') }}</p>
                <x-ui.input :label="__('production.rejection_reason')" name="comments" placeholder="e.g. Scrap percentage is too high" required />
            </form>
            <x-slot name="footer">
                <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="danger" onclick="document.getElementById('rejectForm').submit();">{{ __('production.reject_bom') }}</x-ui.button>
            </x-slot>
        </x-ui.modal>

        <!-- Cancel Modal -->
        <x-ui.modal id="cancelModal" :title="__('production.cancel_bom_version')" :submit-text="__('production.cancel_version')" class="text-start">
            <form method="POST" action="{{ route('production.boms.cancel', $bom->id) }}" id="cancelForm">
                @csrf
                <p class="fs-13 text-muted">{{ __('production.cancel_modal_body') }}</p>
                <x-ui.input :label="__('production.cancellation_reason')" name="comments" placeholder="e.g. Product design obsolete" required />
            </form>
            <x-slot name="footer">
                <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">{{ __('production.cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="danger" onclick="document.getElementById('cancelForm').submit();">{{ __('production.cancel_bom') }}</x-ui.button>
            </x-slot>
        </x-ui.modal>
    </div>

    <script>
        function deleteDraftBom() {
            var message = '{{ addslashes(__('production.confirm_delete_bom') ?: 'Are you sure you want to delete this draft BOM?') }}';
            var options = {
                title: '{{ addslashes(__('production.delete_bom') ?: 'Delete BOM') }}',
                confirmButtonText: '{{ addslashes(__('production.delete') ?: 'Delete') }}',
                confirmButtonClass: 'btn-danger',
                variant: 'danger'
            };

            if (typeof confirmAction === 'function') {
                confirmAction(message, function() {
                    document.getElementById('delete-draft-bom-form').submit();
                }, options);
            } else if (confirm(message)) {
                document.getElementById('delete-draft-bom-form').submit();
            }
        }

        @if(isset($parentProduct))
            // Notify opener (parent BOM page) about the newly created child BOM
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage({
                    type: 'CHILD_BOM_CREATED',
                    product_id: '{{ $bom->product_id }}'
                }, '*');
            }
        @endif
    </script>
@endsection
