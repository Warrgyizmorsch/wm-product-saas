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

        @foreach($bom->routing_advisory as $advisory)
            @php
                $advCode = $advisory['code'] ?? 'info';
                $advAlertClass = match ($advCode) {
                    'operation_removed' => 'alert-danger border-danger bg-soft-danger',
                    'not_active'        => 'alert-warning border-warning bg-soft-warning',
                    default             => 'alert-info border-info bg-soft-info',
                };
                $advIconClass = match ($advCode) {
                    'operation_removed' => 'feather-alert-octagon text-danger',
                    'not_active'        => 'feather-alert-triangle text-warning',
                    default             => 'feather-info text-info',
                };
            @endphp
            <div class="alert {{ $advAlertClass }} p-3 mb-3 rounded shadow-sm d-flex align-items-start" role="alert">
                <i class="{{ $advIconClass }} me-2 mt-1"></i>
                <div class="fs-12 text-dark">
                    <strong>{{ __('production.routing_advisory') }}:</strong> {{ $advisory['message'] ?? '' }}
                    @if($bom->routing)
                        <a href="{{ route('production.routing.show', $bom->routing_id) }}" class="ms-1 fw-semibold">{{ $bom->routing->routing_number }}</a>
                    @endif
                </div>
            </div>
        @endforeach

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
                        <span class="text-dark fw-bold fs-13">{{ format_decimal($bom->base_quantity) }} {{ $bom->baseUom ? $bom->baseUom->code : 'PCS' }}</span>
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
            <!-- Tab 1: Components (Grouped by Operation, BM-IMPL-13 & BM-IMPL-15) -->
            <div class="tab-pane fade show active" id="tab-components" role="tabpanel" aria-labelledby="tab-components-tab">
                <h5 class="fw-bold text-dark mb-3">{{ __('production.bom_specification') }}</h5>
                {{-- BM-IMPL-15 #1–#3 / BM-IMPL-17: master materials (one row per material) + allocation drawer --}}
                @php
                    $masterRows = $bom->items->sortBy(fn ($i) => $i->material?->name)->values();
                    $allocByMaterial = $bom->operationMaterials->groupBy('material_id');
                @endphp
                @if($masterRows->isNotEmpty())
                    <div class="card border shadow-none mb-3">
                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark fs-13"><i class="feather-package text-primary me-1"></i>{{ __('production.bom_master_materials') }}</span>
                            <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-11">{{ $masterRows->count() }} {{ __('production.bom_unique_components') }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 fs-12 bom-master-table">
                                <thead class="table-light fs-11 text-uppercase text-muted">
                                    <tr>
                                        <th style="width: 3%"></th>
                                        <th>{{ __('production.component_product') }}</th>
                                        <th>{{ __('production.type') }}</th>
                                        <th>{{ __('production.bom_rollup_role') }}</th>
                                        <th class="text-end">{{ __('production.quantity') }}</th>
                                        <th class="text-end">{{ __('production.bom_alternative_qty') }}</th>
                                        <th class="text-end">{{ __('production.gross_qty') }}</th>
                                        <th class="text-end">{{ __('production.bom_effective_scrap') }}</th>
                                        <th>{{ __('production.uom') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($masterRows as $mRow)
                                        @php
                                            $mNet = (float) $mRow->quantity;
                                            $mAlt = (float) ($mRow->alternative_quantity ?? 0);
                                            $mGross = (float) ($mRow->gross_quantity ?? $mNet);
                                            $mScrap = $mNet > 0 ? format_decimal(($mGross - $mNet) / $mNet * 100) . '%' : '—';
                                            $mAllocs = $allocByMaterial->get($mRow->material_id, collect());
                                            $drawerId = 'bomMatDrawer' . $mRow->id;
                                        @endphp
                                        <tr class="c-pointer" data-bs-toggle="collapse" data-bs-target="#{{ $drawerId }}" style="cursor: pointer;">
                                            <td class="text-center text-muted"><i class="feather-chevron-right"></i></td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $mRow->material?->name ?? ('#' . $mRow->material_id) }}</div>
                                                <small class="text-muted">{{ $mRow->material?->sku }}</small>
                                            </td>
                                            <td><span class="badge bg-soft-secondary text-secondary text-capitalize">{{ str_replace('_', ' ', $mRow->material?->type ?? '') }}</span></td>
                                            <td>
                                                @if($mRow->allocation_role === 'mixed')
                                                    <span class="badge bg-soft-info text-info border border-info-subtle fs-11">{{ __('production.bom_mixed_roles') }}</span>
                                                @elseif($mRow->allocation_role === 'alternative')
                                                    <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-11">{{ __('production.bom_alternative_only') }}</span>
                                                @else
                                                    <span class="badge bg-soft-success text-success border border-success-subtle fs-11">{{ __('production.bom_primary_only') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end fw-bold text-dark">{{ format_decimal($mNet) }}</td>
                                            <td class="text-end">
                                                @if($mAlt > 0)
                                                    <span class="text-muted">{{ format_decimal($mAlt) }}</span>
                                                    <div><span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10">{{ __('production.bom_alt_not_in_total') }}</span></div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end fw-bold text-primary">{{ format_decimal($mGross) }}</td>
                                            <td class="text-end text-danger">{{ $mScrap }}</td>
                                            <td>{{ $mRow->uom?->code ?? $mRow->material?->uom?->code ?? '' }}</td>
                                        </tr>
                                        <tr class="collapse bg-light-subtle" id="{{ $drawerId }}">
                                            <td colspan="9" class="p-2">
                                                <table class="table table-sm mb-0 fs-11 bg-white border">
                                                    <thead class="table-light text-muted">
                                                        <tr>
                                                            <th>{{ __('production.bom_operation') }}</th>
                                                            <th>{{ __('production.bom_role_substitution') }}</th>
                                                            <th class="text-end">{{ __('production.net_qty') }}</th>
                                                            <th class="text-end">{{ __('production.scrap_percent') }}</th>
                                                            <th class="text-end">{{ __('production.gross_qty') }}</th>
                                                            <th>{{ __('production.notes') }}</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($mAllocs as $al)
                                                            @php
                                                                $alOp = $al->routingOperation;
                                                                $alTarget = $al->is_alternative ? $bom->operationMaterials->firstWhere('id', $al->replaces_routing_material_id) : null;
                                                                $alGross = $al->gross_quantity !== null ? (float) $al->gross_quantity : (float) $al->quantity * (1 + (float) $al->material_scrap_percentage / 100);
                                                            @endphp
                                                            <tr>
                                                                <td>
                                                                    <span class="badge {{ $alOp && $alOp->trashed() ? 'bg-danger' : 'bg-primary' }} text-white">{{ $alOp?->operation_number ?? ('Op ' . $alOp?->sequence) }}</span>
                                                                    {{ $alOp?->name }}
                                                                    @if($alOp && $alOp->is_alternative)
                                                                        <span class="badge bg-soft-warning text-warning fs-10">{{ __('production.bom_alternative_operation') }}</span>
                                                                    @endif
                                                                    @if($alOp && $alOp->trashed())
                                                                        <span class="badge bg-danger text-white fs-10">{{ __('production.operation_deleted') }}</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if($al->is_alternative)
                                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle">{{ __('production.bom_alternative_priority') }} {{ $al->priority }}</span>
                                                                        <div class="text-muted fs-10">{{ __('production.bom_substitutes') }}: {{ $alTarget?->material?->name ?? '—' }}</div>
                                                                    @else
                                                                        <span class="badge bg-soft-success text-success border border-success-subtle">{{ __('production.bom_primary_component') }}</span>
                                                                    @endif
                                                                </td>
                                                                <td class="text-end">{{ format_decimal($al->quantity) }}</td>
                                                                <td class="text-end">{{ format_decimal($al->material_scrap_percentage) }}%</td>
                                                                <td class="text-end fw-semibold">{{ format_decimal($alGross) }}</td>
                                                                <td class="text-muted">{{ $al->notes }}</td>
                                                                <td class="text-end">
                                                                    <a href="#" class="fs-10" onclick="event.preventDefault(); document.querySelector('#tab-routing-tab')?.click(); setTimeout(() => document.getElementById('bom-routing-op-{{ $al->routing_operation_id }}')?.scrollIntoView({behavior: 'smooth', block: 'center'}), 250);">{{ __('production.bom_view_in_routing') }}</a>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="p-4 text-center border rounded bg-light text-muted">
                        {{ __('production.no_components_spec_yet') }}
                    </div>
                @endif
            </div>

            <!-- Tab 2: Expanded Material Explosion -->
            <div class="tab-pane fade" id="tab-explosion" role="tabpanel" aria-labelledby="tab-explosion-tab">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">{{ __('production.multilevel_bom_explosion') }}</h5>
                        <p class="text-muted fs-12 mb-0">
                            {{ __('production.explosion_desc') }}
                            <span class="ms-1 text-primary fw-medium">Default view shows L1 (Finished Good) and L2 (Direct Components). Click <strong>›</strong> on any Sub-BOM row to expand deeper levels.</span>
                        </p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="d-flex align-items-center gap-1.5">
                            <label for="bomExplosionCalcQty" class="fs-11 text-muted fw-semibold text-nowrap mb-0">Produce Qty:</label>
                            <input type="number" step="0.0001" min="0.0001" id="bomExplosionCalcQty" value="{{ format_decimal($calcQty) }}"
                                   class="form-control form-control-sm text-end font-monospace" style="width: 96px; height: 31px;"
                                   onkeydown="if (event.key === 'Enter') { event.preventDefault(); recalculateBomExplosion(); }">
                            <button type="button" id="bomExplosionCalcBtn" class="btn btn-sm btn-outline-primary text-nowrap" style="height: 31px;" onclick="recalculateBomExplosion()">
                                Calculate Materials
                            </button>
                        </div>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-light border fs-11 fw-semibold" style="height: 31px;" onclick="expandAllBomExplosion()">
                                <i class="feather-chevrons-down me-1"></i>Expand All
                            </button>
                            <button type="button" class="btn btn-light border fs-11 fw-semibold" style="height: 31px;" onclick="collapseBomExplosionToL2()">
                                <i class="feather-minimize-2 me-1"></i>Default (L1–L2 Only)
                            </button>
                        </div>
                    </div>
                </div>
                <div id="bomExplosionAjaxError" class="alert alert-danger py-1.5 px-3 fs-12 mb-2 d-none"></div>

                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" class="bom-table-enhanced">
                        <thead>
                            <tr>
                                <th style="width: 31%">{{ __('production.component_product') }}</th>
                                <th style="width: 26%">{{ __('production.linked_sub_bom') }}</th>
                                <th style="width: 11%" class="text-end text-nowrap">{{ __('production.bom_base_qty') }}</th>
                                <th style="width: 14%" class="text-end text-nowrap">{{ __('production.qty_required') }}</th>
                                <th style="width: 6%" class="text-center">{{ __('production.uom') ?? 'UOM' }}</th>
                                <th style="width: 6%" class="text-center">{{ __('production.version') }}</th>
                                <th style="width: 6%" class="text-center">{{ __('production.status') }}</th>
                            </tr>
                        </thead>
                        <tbody id="bomExplosionTableBody">
                            @include('modules.production.bom.partials._explosion_rows', ['explosion' => $explosion])
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Tab 3: Routing Process -->
            <div class="tab-pane fade" id="tab-routing" role="tabpanel" aria-labelledby="tab-routing-tab">
                @if($bom->routing)
                    @php
                        $routingPrimaryCount = $bom->routing->operations->where('is_alternative', false)->count();
                        $routingAltCount = $bom->routing->operations->where('is_alternative', true)->count();
                    @endphp
                    <div class="mb-4 p-3 bg-light rounded border d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-1">{{ __('production.routing_reference') }}</span>
                            <h5 class="fw-bold text-dark mb-1">{{ $bom->routing->name }} ({{ $bom->routing->routing_number }})</h5>
                            <div class="d-flex flex-wrap align-items-center gap-2 fs-12 text-muted">
                                <span>{{ __('production.version') }}: v{{ $bom->routing->version }}</span>
                                <span>|</span>
                                <span>{{ __('production.status') }}:
                                    <span class="badge bg-soft-success text-success text-uppercase font-monospace fs-10">{{ $bom->routing->status }}</span>
                                </span>
                                <span>|</span>
                                <span>{{ $routingPrimaryCount }} Primary stage(s)</span>
                                @if($routingAltCount > 0)
                                    <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10">+{{ $routingAltCount }} Alternative branch(es)</span>
                                @endif
                            </div>
                        </div>
                        <x-ui.button href="{{ route('production.routing.show', $bom->routing_id) }}" variant="outline-primary" class="btn-sm" icon="feather-external-link">
                            View Routing
                        </x-ui.button>
                    </div>

                    <h5 class="fw-bold text-dark mb-3">{{ __('production.workflow_routing') }}</h5>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" class="bom-table-enhanced">
                            <thead>
                                <tr>
                                    <th style="width: 5%" class="text-center">{{ __('production.seq') }}</th>
                                    <th style="width: 30%">{{ __('production.operation_stage') }}</th>
                                    <th style="width: 12%">{{ __('production.type') }}</th>
                                    <th style="width: 18%">{{ __('production.work_center') }}</th>
                                    <th style="width: 15%">{{ __('production.machine') }}</th>
                                    <th class="text-end" style="width: 12%">{{ __('production.times_yield') }}</th>
                                    <th class="text-center" style="width: 8%">QC Gate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($bom->routing->operations as $op)
                                    @php
                                        $isAltOp = (bool) $op->is_alternative;
                                        $bomOpMaterials = $bom->operationMaterials
                                            ? $bom->operationMaterials->where('routing_operation_id', $op->id)
                                            : collect();
                                        $opPrimaries = $bomOpMaterials->where('is_alternative', false)->sortBy('sequence');
                                        $opAltsByTarget = $bomOpMaterials->where('is_alternative', true)->groupBy('replaces_routing_material_id');
                                        $orderedOpMats = collect();
                                        $placedOpMatIds = [];
                                        foreach ($opPrimaries as $pMat) {
                                            $orderedOpMats->push($pMat);
                                            $placedOpMatIds[$pMat->id] = true;
                                            foreach ($opAltsByTarget->get($pMat->id, collect())->sortBy('priority') as $aMat) {
                                                $orderedOpMats->push($aMat);
                                                $placedOpMatIds[$aMat->id] = true;
                                            }
                                        }
                                        foreach ($bomOpMaterials as $remMat) {
                                            if (!isset($placedOpMatIds[$remMat->id])) {
                                                $orderedOpMats->push($remMat);
                                            }
                                        }
                                    @endphp
                                    <tr id="bom-routing-op-{{ $op->id }}" @if($isAltOp) style="background-color: rgba(245, 158, 11, 0.05);" @endif>
                                        <td class="fw-bold text-center font-monospace align-middle" @if($isAltOp) style="border-left: 3px solid #f59e0b;" @endif>
                                            {{ $op->sequence }}
                                        </td>
                                        <td class="align-middle">
                                            <div class="d-flex flex-wrap align-items-center gap-1.5">
                                                <span class="fw-bold text-dark">{{ $op->name }}</span>
                                                <span class="badge bg-soft-primary text-primary font-monospace fs-10">{{ $op->operation_number }}</span>
                                                @if ($isAltOp)
                                                    <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10">
                                                        <i class="feather-git-branch me-1"></i>{{ __('production.bom_alternative_operation') }}
                                                        &middot; {{ __('production.bom_substitutes') }} {{ $op->primaryOperation?->operation_number ?? ('Op #' . $op->replaces_operation_id) }}
                                                        &middot; Priority {{ $op->priority ?? 1 }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-soft-success text-success border border-success-subtle fs-10">Primary</span>
                                                @endif
                                                @if ($op->is_external)
                                                    <span class="badge bg-soft-danger text-danger fs-10 text-uppercase">{{ __('production.outsourced') ?? 'Outsourced' }}</span>
                                                @endif
                                                @if ($bom->usesPreviousOpOutput($op))
                                                    {{-- TF-01 --}}
                                                    <span class="badge bg-soft-info text-info fs-10" title="Works on the previous operation's output">Previous OP materials</span>
                                                @endif
                                            </div>
                                            @if ($op->description)
                                                <small class="text-muted d-block mt-1">{{ $op->description }}</small>
                                            @endif

                                            @if($orderedOpMats->isNotEmpty())
                                                <div class="mt-2 bg-white p-2 rounded border {{ $isAltOp ? 'border-warning-subtle' : 'border-dashed' }}">
                                                    <small class="fw-bold text-muted d-block mb-1 text-uppercase fs-10">
                                                        {{ __('production.allocated_consumed_materials') ?? 'Allocated Consumed Materials:' }}
                                                        @if($isAltOp)
                                                            <span class="text-warning text-none fw-semibold ms-1">(Alternative route — used only when {{ $op->operation_number }} runs)</span>
                                                        @endif
                                                    </small>
                                                    <ul class="mb-0 ps-3 fs-11 text-muted">
                                                        @foreach($orderedOpMats as $opMat)
                                                            @php
                                                                $opMatScrap = (float) ($opMat->material_scrap_percentage ?? 0);
                                                                $opMatGross = $opMat->gross_quantity !== null
                                                                    ? (float) $opMat->gross_quantity
                                                                    : \App\Domains\Production\Models\RoutingOperationMaterial::calculateGrossQuantity((float) $opMat->quantity, $opMatScrap);
                                                                $opMatUom = $opMat->uom?->code ?? $opMat->material?->uom?->code ?? 'PCS';
                                                            @endphp
                                                            <li class="mb-0.5">
                                                                <span class="text-secondary fw-semibold">{{ __('production.seq') }} {{ $opMat->sequence }}:</span>
                                                                <strong class="text-dark">{{ $opMat->material?->name ?? 'Material' }}</strong>:
                                                                {{ format_decimal($opMat->quantity) }} {{ $opMatUom }}
                                                                @if($opMatScrap > 0)
                                                                    <span class="text-danger fs-10">(+{{ format_decimal($opMatScrap) }}% scrap &rarr; {{ format_decimal($opMatGross) }} {{ $opMatUom }})</span>
                                                                @endif
                                                                @if($opMat->is_alternative)
                                                                    <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 ms-1">
                                                                        Alternative (P{{ $opMat->priority ?? 1 }}) &middot; {{ __('production.bom_substitutes') }} {{ $opMat->replacesMaterial?->material?->name ?? 'Primary' }}
                                                                    </span>
                                                                @elseif($isAltOp)
                                                                    <span class="badge bg-soft-warning text-warning fs-10 ms-1">Branch Primary</span>
                                                                @else
                                                                    <span class="badge bg-soft-primary text-primary fs-10 ms-1">Primary</span>
                                                                @endif
                                                                @if($opMat->childBom)
                                                                    <a href="{{ route('production.boms.show', $opMat->child_bom_id) }}" class="badge bg-soft-success text-success border border-success-subtle text-decoration-none fs-10 ms-1">
                                                                        Sub-BOM: {{ $opMat->childBom->bom_number }}
                                                                    </a>
                                                                @endif
                                                                @if($opMat->consumption_type)
                                                                    <span class="badge bg-light text-dark border fs-10 ms-1">{{ $opMat->consumption_type }}</span>
                                                                @endif
                                                                @if(\App\Domains\Production\Services\ProductionOrderEngineeringService::isVendorSuppliedAllocation($opMat, $op))
                                                                    {{-- TF-02 --}}
                                                                    <span class="badge bg-soft-purple text-purple fs-10 ms-1" title="The vendor brings it — not requested from the store">Vendor-supplied</span>
                                                                @endif
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
                                                    @if($op->section)
                                                        <small class="text-dark fs-11">{{ $op->section->name }}</small>
                                                    @endif
                                                    <small class="text-secondary font-monospace fs-10 mt-0.5">{{ $op->workCenter->code }}</small>
                                                </div>
                                            @elseif ($op->is_external)
                                                <span class="badge bg-soft-danger text-danger fs-10">External Vendor</span>
                                            @else
                                                <span class="text-danger">{{ __('production.no_selection') }}</span>
                                            @endif
                                        </td>
                                        <td class="align-middle">
                                            @if ($op->machine)
                                                <span class="fw-semibold text-dark d-block">{{ $op->machine->name }}</span>
                                                <small class="text-muted d-block font-monospace fs-10">{{ $op->machine->code }}</small>
                                                <span class="badge bg-soft-primary text-primary fs-10 mt-0.5">Machine</span>
                                            @elseif ($op->manualProduction)
                                                <span class="fw-semibold text-dark d-block">{{ $op->manualProduction->batch_name }}</span>
                                                @if($op->manualProduction->shift)
                                                    <small class="text-muted d-block fs-10">{{ $op->manualProduction->shift->name }}</small>
                                                @endif
                                                <span class="badge bg-soft-info text-info fs-10 mt-0.5">Manual Batch</span>
                                            @elseif ($op->is_external)
                                                <span class="fw-semibold text-dark d-block">{{ $op->vendor?->name ?? 'External Subcontractor' }}</span>
                                                <span class="badge bg-soft-danger text-danger fs-10 mt-0.5">Subcontractor</span>
                                            @else
                                                <span class="text-muted">{{ __('production.generic_capacity') ?? 'Generic Capacity' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end align-middle font-monospace fs-11">
                                            <div>{{ __('production.bom_setup_per_run') }}: {{ format_duration($op->setup_time_minutes) }}</div>
                                            @if((float) ($op->setup_time_per_piece_minutes ?? 0) > 0)
                                                <div>{{ __('production.bom_setup_per_piece') }}: {{ format_duration($op->setup_time_per_piece_minutes) }}</div>
                                            @endif
                                            <div>{{ __('production.run') }}: {{ format_duration($op->processing_time_minutes) }}</div>
                                            <div class="text-success fw-semibold">{{ __('production.bom_yield') }}: {{ format_decimal($op->expected_yield_percentage > 0 ? $op->expected_yield_percentage : 100) }}%</div>
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
                @php
                    $simAltTotalCount = (int) ($costSimulatorData['alt_ops_count'] ?? 0) + (int) ($costSimulatorData['alt_mats_count'] ?? 0);
                    $hasSimAlternatives = !empty($costSimulatorData['has_alternatives']);
                    $primaryCostItems = collect($costSummary['items'])->where('is_excluded_from_baseline', false)->values();
                    $excludedCostItems = collect($costSummary['items'])->where('is_excluded_from_baseline', true)->values();
                @endphp

                <!-- Header Bar: Primary Baseline Notice + Simulate Cost Trigger -->
                <div class="p-3 rounded border bg-light mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h5 class="fw-bold text-dark mb-0">{{ __('production.est_material_cost_summary') }}</h5>
                            @if($hasSimAlternatives)
                                <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-11">
                                    +{{ $simAltTotalCount }} alternative(s) available to simulate
                                </span>
                            @endif
                        </div>
                        <p class="text-muted fs-12 mb-0 mt-1">
                            <i class="feather-info text-primary me-1"></i>Primary route &amp; primary materials baseline (excludes alternative operations &amp; alternative materials). Scaled to base quantity: <strong>{{ format_decimal($bom->base_quantity) }} {{ $bom->baseUom?->code ?? 'PCS' }}</strong>.
                        </p>
                    </div>
                    @if($hasSimAlternatives)
                        <x-ui.button type="button" variant="primary" icon="feather-sliders" data-bs-toggle="modal" data-bs-target="#bomCostSimulationModal">
                            Simulate Cost
                        </x-ui.button>
                    @endif
                </div>

                {{-- Primary Cost Layer Cards --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-4 col-lg">
                        <div class="bg-light p-3 rounded border text-center h-100">
                            <span class="text-muted fs-11 text-uppercase fw-bold d-block">{{ __('production.component_cost_net') }}</span>
                            <h4 class="text-dark fw-bold mt-1 mb-1">{{ format_currency($costSummary['net_material_cost'], 4) }}</h4>
                            <small class="text-muted fs-11">Direct primary net (catalog unit cost)</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg">
                        <div class="bg-light p-3 rounded border text-center h-100">
                            <span class="text-danger fs-11 text-uppercase fw-bold d-block">{{ __('production.scrap_loss') ?? 'Scrap Loss' }}</span>
                            <h4 class="text-danger fw-bold mt-1 mb-1">{{ format_currency($costSummary['scrap_adjustment'], 4) }}</h4>
                            <small class="text-muted fs-11">{{ __('production.expected_scrap_desc') ?? 'Primary material scrap value' }}</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg">
                        <div class="bg-light p-3 rounded border text-center h-100">
                            <span class="text-muted fs-11 text-uppercase fw-bold d-block">{{ __('production.labor_cost') ?? 'Labor Cost' }}</span>
                            <h4 class="text-dark fw-bold mt-1 mb-1">{{ format_currency($costSummary['labor_cost'], 4) }}</h4>
                            <small class="text-muted fs-11">Saved primary labor/unit &times; base qty</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg">
                        <div class="bg-light p-3 rounded border text-center h-100">
                            <span class="text-muted fs-11 text-uppercase fw-bold d-block">{{ __('production.machine_cost') ?? 'Machine Cost' }}</span>
                            <h4 class="text-dark fw-bold mt-1 mb-1">{{ format_currency($costSummary['machine_cost'], 4) }}</h4>
                            <small class="text-muted fs-11">Saved primary machine/unit &times; base qty</small>
                        </div>
                    </div>
                    @if(($costSummary['subcontract_cost'] ?? 0) > 0)
                        <div class="col-md-4 col-lg">
                            <div class="bg-light p-3 rounded border text-center h-100">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block">Subcontract Cost</span>
                                <h4 class="text-dark fw-bold mt-1 mb-1">{{ format_currency($costSummary['subcontract_cost'], 4) }}</h4>
                                <small class="text-muted fs-11">Outsourced ops/unit &times; base qty</small>
                            </div>
                        </div>
                    @endif
                    <div class="col-md-4 col-lg">
                        <div class="bg-light p-3 rounded border text-center h-100">
                            <span class="text-warning fs-11 text-uppercase fw-bold d-block">Setup Cost / Run</span>
                            <h4 class="text-dark fw-bold mt-1 mb-1">{{ format_currency($costSummary['setup_cost_per_run'] ?? 0, 4) }}</h4>
                            <small class="text-muted fs-11">Once per run (separate from unit cost)</small>
                        </div>
                    </div>
                </div>

                <!-- Direct Catalog Total vs Rolled-Up Multi-Level Total -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 rounded border border-primary bg-soft-primary d-flex justify-content-between align-items-center h-100">
                            <div>
                                <span class="text-primary fs-11 text-uppercase fw-bold d-block">Direct Product Cost Total (Catalog Basis)</span>
                                <div class="text-muted fs-11 mt-1">
                                    Direct Materials {{ format_currency($costSummary['material_cost'], 4) }} + Routing {{ format_currency($costSummary['routing_cost'], 4) }}
                                    <span class="d-block">Excludes once-per-run setup ({{ format_currency($costSummary['setup_cost_per_run'] ?? 0, 4) }})</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <h4 class="text-primary fw-bold mb-0">{{ format_currency($costSummary['total_cost'], 4) }}</h4>
                                <small class="text-primary font-monospace fs-11">{{ format_currency($costSummary['cost_per_unit'], 4) }} / unit</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded border border-info bg-soft-info d-flex justify-content-between align-items-center h-100">
                            <div>
                                <span class="text-info fs-11 text-uppercase fw-bold d-block">Rolled-Up Sub-BOM Cost Total (Multi-Level Basis)</span>
                                <div class="text-muted fs-11 mt-1">
                                    Raw Leaves {{ format_currency($rollupSummary['rolled_raw_material_cost'], 4) }} + All-Level Routing {{ format_currency($rollupSummary['rolled_routing_cost'], 4) }}
                                    <span class="d-block">Combined setups across tree: {{ format_currency($rollupSummary['rolled_setup_cost_per_run'], 4) }} / run</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <h4 class="text-info fw-bold mb-0">{{ format_currency($rollupSummary['rolled_total_excl_setup'], 4) }}</h4>
                                <small class="text-info font-monospace fs-11">{{ format_currency($rollupSummary['rolled_cost_per_unit'], 4) }} / unit</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cost Details Item Table -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0">{{ __('production.component_cost_gross') }} (Catalog vs. Sub-BOM Roll-Up)</h6>
                    <small class="text-muted fs-11">Only primary rows on primary operations are included in baseline totals.</small>
                </div>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" class="bom-table-enhanced">
                        <thead>
                            <tr>
                                <th style="width: 12%">{{ __('production.bom_operation') }}</th>
                                <th style="width: 28%">{{ __('production.component_product') }}</th>
                                <th style="width: 10%" class="text-end">{{ __('production.net_qty') }}</th>
                                <th style="width: 8%" class="text-end">{{ __('production.scrap_percent') }}</th>
                                <th style="width: 11%" class="text-end">{{ __('production.gross_qty') }}</th>
                                <th style="width: 10%" class="text-end">{{ __('production.unit_cost') }}</th>
                                <th style="width: 10%" class="text-end">Direct Total</th>
                                <th style="width: 11%" class="text-end">Sub-BOM Roll-Up</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($primaryCostItems as $cItem)
                                <tr>
                                    <td>
                                        <span class="badge bg-soft-primary text-primary font-monospace fs-10">{{ $cItem['operation_number'] }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <div class="d-flex flex-wrap align-items-center gap-1">
                                                <span class="fw-bold text-dark">{{ $cItem['material_name'] }}</span>
                                                @if(!empty($cItem['has_sub_bom']) && !empty($cItem['sub_bom_number']))
                                                    <span class="badge bg-soft-success text-success border border-success-subtle fs-10">{{ $cItem['sub_bom_number'] }}</span>
                                                @endif
                                            </div>
                                            <small class="text-muted font-monospace fs-10">{{ $cItem['material_sku'] }}</small>
                                        </div>
                                    </td>
                                    <td class="text-end">{{ format_decimal($cItem['quantity']) }} {{ $cItem['uom_code'] }}</td>
                                    <td class="text-end text-danger">{{ format_decimal($cItem['scrap_percentage']) }}%</td>
                                    <td class="text-end fw-bold">{{ format_decimal($cItem['gross_quantity']) }} {{ $cItem['uom_code'] }}</td>
                                    <td class="text-end">{{ format_currency($cItem['unit_cost'], 4) }}</td>
                                    <td class="text-end text-dark fw-bold">{{ format_currency($cItem['total_cost'], 4) }}</td>
                                    <td class="text-end fw-bold text-info">
                                        {{ format_currency($cItem['rollup_cost'] ?? $cItem['total_cost'], 4) }}
                                        @if(!empty($cItem['has_sub_bom']))
                                            <small class="d-block text-muted fw-normal fs-10">Sub-BOM</small>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">{{ __('production.no_pricing_components') }}</td>
                                </tr>
                            @endforelse

                            @if($excludedCostItems->isNotEmpty())
                                <tr class="bg-light-subtle">
                                    <td colspan="8" class="fs-11 fw-semibold text-warning py-2">
                                        <i class="feather-git-branch me-1"></i>Excluded from Primary Baseline (Available in Simulate Cost)
                                    </td>
                                </tr>
                                @foreach($excludedCostItems as $xItem)
                                    <tr class="text-muted bg-light-subtle">
                                        <td>
                                            <span class="badge bg-soft-warning text-warning font-monospace fs-10">{{ $xItem['operation_number'] }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <div class="d-flex flex-wrap align-items-center gap-1">
                                                    <span class="fw-semibold text-secondary">{{ $xItem['material_name'] }}</span>
                                                    @if($xItem['operation_is_alternative'])
                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10">Excluded &mdash; Alt Op</span>
                                                    @endif
                                                    @if($xItem['is_alternative'])
                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10">
                                                            Excluded &mdash; Alt Material (P{{ $xItem['priority'] ?? 1 }})
                                                            @if(!empty($xItem['replaces_material_name']))
                                                                &middot; Replaces {{ $xItem['replaces_material_name'] }}
                                                            @endif
                                                        </span>
                                                    @endif
                                                </div>
                                                <small class="text-muted font-monospace fs-10">{{ $xItem['material_sku'] }}</small>
                                            </div>
                                        </td>
                                        <td class="text-end">{{ format_decimal($xItem['quantity']) }} {{ $xItem['uom_code'] }}</td>
                                        <td class="text-end">{{ format_decimal($xItem['scrap_percentage']) }}%</td>
                                        <td class="text-end">{{ format_decimal($xItem['gross_quantity']) }} {{ $xItem['uom_code'] }}</td>
                                        <td class="text-end">{{ format_currency($xItem['unit_cost'], 4) }}</td>
                                        <td class="text-end text-decoration-line-through">{{ format_currency($xItem['total_cost'], 4) }}</td>
                                        <td class="text-end text-muted fs-11">Simulate</td>
                                    </tr>
                                @endforeach
                            @endif

                            <tr class="table-light fw-bold">
                                <td colspan="6" class="text-end">{{ __('production.est_total_material_cost') }} (Primary Only)</td>
                                <td class="text-end text-primary fs-14">{{ format_currency($materialCost, 4) }}</td>
                                <td class="text-end text-info fs-12">
                                    {{ format_currency($rollupSummary['rolled_raw_material_cost'], 4) }} raw
                                    @if(($rollupSummary['rolled_sub_routing_cost'] ?? 0) > 0)
                                        <small class="d-block text-muted fw-normal fs-10">+ {{ format_currency($rollupSummary['rolled_sub_routing_cost'], 4) }} sub-routing</small>
                                    @endif
                                </td>
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

        <!-- Simulate Cost Modal (Routes + Materials) -->
        @if(!empty($costSimulatorData['has_alternatives']))
            <div class="modal fade" id="bomCostSimulationModal" tabindex="-1" aria-labelledby="bomCostSimulationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
                     x-data="bomCostSimulator({{ Js::from($costSimulatorData) }})">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-white border-bottom py-3 px-4">
                            <div class="d-flex align-items-center gap-2.5">
                                <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    <i class="feather-sliders fs-18"></i>
                                </div>
                                <div>
                                    <h5 class="modal-title fw-bold text-dark mb-0" id="bomCostSimulationModalLabel">
                                        Simulate BOM Cost (Routes + Materials)
                                    </h5>
                                    <span class="fs-12 text-muted">Select alternative operations and/or alternative materials, then click "Run Calculations" to preview total cost impact</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body p-4 bg-light-subtle">
                            <div class="alert alert-info py-2 px-3 fs-12 mb-3 d-flex align-items-center gap-2 rounded border-info-subtle">
                                <i class="feather-info fs-16 text-primary flex-shrink-0"></i>
                                <div>
                                    Stages without alternatives are locked to Primary. Switching an operation variant updates both its routing cost and its consumed material list.
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-3 mb-4">
                                <template x-for="stage in stages" :key="stage.primary_id">
                                    <div class="card border rounded shadow-none mb-0">
                                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-soft-primary text-primary font-monospace fw-bold fs-11">
                                                    Stage <span x-text="stage.stage_number"></span>
                                                </span>
                                                <span class="fw-bold text-dark fs-12 font-monospace" x-text="ops[stage.primary_id].operation_number + ': ' + ops[stage.primary_id].name"></span>
                                            </div>
                                            <div>
                                                <template x-if="!stage.has_alternatives">
                                                    <span class="badge bg-soft-secondary text-secondary fs-10">
                                                        <i class="feather-lock me-1"></i>Locked (Primary Route)
                                                    </span>
                                                </template>
                                                <template x-if="stage.has_alternatives">
                                                    <span class="badge bg-soft-warning text-warning fs-10">
                                                        <i class="feather-git-branch me-1"></i>Alternative Route Available
                                                    </span>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="card-body p-3 bg-light space-y-2">
                                            <!-- Operation Variant Selection -->
                                            <div class="d-flex flex-column gap-2 mb-3">
                                                <template x-for="opId in stage.options" :key="opId">
                                                    <label class="p-2.5 rounded border d-flex justify-content-between align-items-center mb-0 bg-white"
                                                           :class="selectedOps['stage_' + stage.primary_id] == opId ? 'border-primary shadow-sm' : 'border-light-subtle'"
                                                           style="cursor: pointer;"
                                                           @click="selectOp(stage.primary_id, opId)">
                                                        <div class="d-flex align-items-start gap-2.5">
                                                            <div class="form-check mt-0.5" x-show="stage.has_alternatives">
                                                                <input class="form-check-input" type="radio"
                                                                       :name="'bom_sim_stage_' + stage.primary_id"
                                                                       :value="opId"
                                                                       :checked="selectedOps['stage_' + stage.primary_id] == opId"
                                                                       @change="selectOp(stage.primary_id, opId)">
                                                            </div>
                                                            <div>
                                                                <div class="d-flex flex-wrap align-items-center gap-1.5 mb-0.5">
                                                                    <template x-if="!ops[opId].is_alternative">
                                                                        <span class="badge bg-soft-primary text-primary fs-10">Primary Route</span>
                                                                    </template>
                                                                    <template x-if="ops[opId].is_alternative">
                                                                        <span class="badge bg-soft-warning text-warning fs-10">
                                                                            <i class="feather-git-branch me-0.5"></i>Alt Route (P<span x-text="ops[opId].priority"></span>)
                                                                        </span>
                                                                    </template>
                                                                    <span class="fw-bold text-dark fs-12 font-monospace" x-text="ops[opId].operation_number + ': ' + ops[opId].name"></span>
                                                                </div>
                                                                <small class="text-muted fs-11">
                                                                    <span x-text="ops[opId].work_center"></span> &bull;
                                                                    <span x-text="ops[opId].section"></span> &bull;
                                                                    <span x-text="ops[opId].resource"></span>
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <div class="text-end font-monospace">
                                                            <div class="fw-bold fs-12 text-dark">
                                                                Routing: <span x-text="currencySym + fmt4(ops[opId].routing_cost)"></span>
                                                            </div>
                                                            <small class="text-muted fs-10">
                                                                Setup/Run: <span x-text="currencySym + fmt4(ops[opId].setup_cost_per_run)"></span>
                                                            </small>
                                                        </div>
                                                    </label>
                                                </template>
                                            </div>

                                            <!-- Material Slots for Selected Operation -->
                                            <template x-if="activeOpForStage(stage).material_slots.length > 0">
                                                <div class="bg-white rounded border p-2.5">
                                                    <div class="fs-10 fw-bold text-muted text-uppercase mb-2">
                                                        Consumed Materials on <span class="text-primary" x-text="activeOpForStage(stage).operation_number"></span>
                                                    </div>
                                                    <div class="d-flex flex-column gap-2">
                                                        <template x-for="slot in activeOpForStage(stage).material_slots" :key="slot.slot_key">
                                                            <div class="p-2 rounded border bg-light-subtle">
                                                                <template x-if="!slot.has_alternatives">
                                                                    <div class="d-flex justify-content-between align-items-center fs-12">
                                                                        <div>
                                                                            <span class="badge bg-soft-secondary text-secondary fs-10 me-1">Fixed</span>
                                                                            <strong class="text-dark" x-text="slot.options[0].material_name"></strong>
                                                                            <span class="text-muted ms-1" x-text="fmt4(slot.options[0].gross_quantity) + ' ' + slot.options[0].uom_code"></span>
                                                                            <template x-if="slot.options[0].has_sub_bom">
                                                                                <span class="badge bg-soft-success text-success fs-10 ms-1" x-text="slot.options[0].sub_bom_number"></span>
                                                                            </template>
                                                                        </div>
                                                                        <div class="text-end font-monospace">
                                                                            <span class="fw-bold text-dark" x-text="currencySym + fmt4(slot.options[0].direct_cost)"></span>
                                                                            <template x-if="slot.options[0].has_sub_bom">
                                                                                <small class="d-block text-info fs-10" x-text="'Roll-up: ' + currencySym + fmt4(slot.options[0].rollup_cost)"></small>
                                                                            </template>
                                                                        </div>
                                                                    </div>
                                                                </template>

                                                                <template x-if="slot.has_alternatives">
                                                                    <div class="d-flex flex-column gap-1.5">
                                                                        <div class="fs-11 fw-semibold text-warning mb-0.5">
                                                                            <i class="feather-shuffle me-1"></i>Choose material variant:
                                                                        </div>
                                                                        <template x-for="mOpt in slot.options" :key="mOpt.id">
                                                                            <label class="p-2 rounded border bg-white d-flex justify-content-between align-items-center mb-0 fs-12"
                                                                                   :class="selectedMatForSlot(slot) == mOpt.id ? 'border-primary shadow-sm' : 'border-light-subtle'"
                                                                                   style="cursor: pointer;"
                                                                                   @click="selectMat(slot.slot_key, mOpt.id)">
                                                                                <div class="d-flex align-items-center gap-2">
                                                                                    <input class="form-check-input mt-0" type="radio"
                                                                                           :name="'bom_sim_slot_' + slot.slot_key"
                                                                                           :value="mOpt.id"
                                                                                           :checked="selectedMatForSlot(slot) == mOpt.id"
                                                                                           @change="selectMat(slot.slot_key, mOpt.id)">
                                                                                    <div>
                                                                                        <span class="badge fs-10 me-1"
                                                                                              :class="mOpt.is_alternative ? 'bg-soft-warning text-warning' : 'bg-soft-primary text-primary'"
                                                                                              x-text="mOpt.is_alternative ? ('Alt P' + mOpt.priority) : 'Primary'"></span>
                                                                                        <strong class="text-dark" x-text="mOpt.material_name"></strong>
                                                                                        <span class="text-muted ms-1" x-text="fmt4(mOpt.gross_quantity) + ' ' + mOpt.uom_code + ' @ ' + currencySym + fmt4(mOpt.unit_cost)"></span>
                                                                                        <template x-if="mOpt.has_sub_bom">
                                                                                            <span class="badge bg-soft-success text-success fs-10 ms-1" x-text="mOpt.sub_bom_number"></span>
                                                                                        </template>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="text-end font-monospace">
                                                                                    <span class="fw-bold text-dark" x-text="currencySym + fmt4(mOpt.direct_cost)"></span>
                                                                                    <template x-if="mOpt.has_sub_bom">
                                                                                        <small class="d-block text-info fs-10" x-text="'Roll-up: ' + currencySym + fmt4(mOpt.rollup_cost)"></small>
                                                                                    </template>
                                                                                </div>
                                                                            </label>
                                                                        </template>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Action Bar -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 bg-white rounded border mb-3 shadow-sm">
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-primary fw-bold px-4" @click="runCalculations()">
                                        <i class="feather-zap me-1"></i>RUN CALCULATIONS
                                    </button>
                                    <button type="button" class="btn btn-light border text-muted px-3 fs-12" @click="resetToPrimary()">
                                        <i class="feather-rotate-ccw me-1"></i>Reset to Primary
                                    </button>
                                </div>
                                <span class="fs-12 text-muted" x-show="!hasCalculated">
                                    Click RUN CALCULATIONS to compare against baseline
                                </span>
                                <span class="badge bg-soft-success text-success fs-11 px-2 py-1" x-show="hasCalculated">
                                    <i class="feather-check-circle me-1"></i>Scenario Calculated
                                </span>
                            </div>

                            <!-- Results Panel -->
                            <div x-show="hasCalculated" x-transition class="card border border-primary bg-white shadow-sm mb-0">
                                <div class="card-header bg-soft-primary border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-primary fs-12 text-uppercase font-monospace">
                                        <i class="feather-activity me-1"></i>Simulated BOM Cost Scenario
                                    </span>
                                    <div class="d-flex gap-1.5">
                                        <span class="badge bg-primary text-white fs-10" x-text="results.altOpsSelected + ' Alt Route(s)'"></span>
                                        <span class="badge bg-warning text-dark fs-10" x-text="results.altMatsSelected + ' Alt Material(s)'"></span>
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <div class="p-3 bg-soft-primary rounded border border-primary">
                                                <div class="text-primary fs-10 text-uppercase fw-bold">Simulated Direct Total (Catalog Basis)</div>
                                                <div class="d-flex justify-content-between align-items-baseline mt-1">
                                                    <span class="fs-20 fw-bold text-primary font-monospace" x-text="currencySym + fmt4(results.directTotal)"></span>
                                                    <span class="fs-12 text-primary font-monospace" x-text="currencySym + fmt4(results.directCostPerUnit) + ' / unit'"></span>
                                                </div>
                                                <div class="mt-1 fs-11">
                                                    <template x-if="Math.abs(results.directDelta) < 0.0001">
                                                        <span class="badge bg-soft-secondary text-secondary">Identical to Primary Baseline</span>
                                                    </template>
                                                    <template x-if="results.directDelta <= -0.0001">
                                                        <span class="badge bg-soft-success text-success font-monospace fw-bold" x-text="'Saves ' + currencySym + fmt4(Math.abs(results.directDelta)) + ' vs Baseline'"></span>
                                                    </template>
                                                    <template x-if="results.directDelta >= 0.0001">
                                                        <span class="badge bg-soft-danger text-danger font-monospace fw-bold" x-text="'+' + currencySym + fmt4(results.directDelta) + ' vs Baseline'"></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 bg-soft-info rounded border border-info">
                                                <div class="text-info fs-10 text-uppercase fw-bold">Simulated Rolled-Up Total (Multi-Level Basis)</div>
                                                <div class="d-flex justify-content-between align-items-baseline mt-1">
                                                    <span class="fs-20 fw-bold text-info font-monospace" x-text="currencySym + fmt4(results.rolledTotal)"></span>
                                                    <span class="fs-12 text-info font-monospace" x-text="currencySym + fmt4(results.rolledCostPerUnit) + ' / unit'"></span>
                                                </div>
                                                <div class="mt-1 fs-11">
                                                    <template x-if="Math.abs(results.rolledDelta) < 0.0001">
                                                        <span class="badge bg-soft-secondary text-secondary">Identical to Rolled-Up Baseline</span>
                                                    </template>
                                                    <template x-if="results.rolledDelta <= -0.0001">
                                                        <span class="badge bg-soft-success text-success font-monospace fw-bold" x-text="'Saves ' + currencySym + fmt4(Math.abs(results.rolledDelta)) + ' vs Baseline'"></span>
                                                    </template>
                                                    <template x-if="results.rolledDelta >= 0.0001">
                                                        <span class="badge bg-soft-danger text-danger font-monospace fw-bold" x-text="'+' + currencySym + fmt4(results.rolledDelta) + ' vs Baseline'"></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-2 text-center">
                                        <div class="col-6 col-md-3">
                                            <div class="p-2 bg-light rounded border">
                                                <span class="text-muted fs-10 text-uppercase d-block">Direct Materials</span>
                                                <span class="fw-bold text-dark font-monospace" x-text="currencySym + fmt4(results.directMaterial)"></span>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="p-2 bg-light rounded border">
                                                <span class="text-muted fs-10 text-uppercase d-block">Scrap Loss</span>
                                                <span class="fw-bold text-danger font-monospace" x-text="currencySym + fmt4(results.scrapCost)"></span>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="p-2 bg-light rounded border">
                                                <span class="text-muted fs-10 text-uppercase d-block">Routing (Labor+Mach+Sub)</span>
                                                <span class="fw-bold text-dark font-monospace" x-text="currencySym + fmt4(results.routingCost)"></span>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="p-2 bg-light rounded border">
                                                <span class="text-muted fs-10 text-uppercase d-block">Setup Cost / Run</span>
                                                <span class="fw-bold text-warning font-monospace" x-text="currencySym + fmt4(results.setupCostPerRun)"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        window.fmt4 = function (v) {
            const n = Math.round((parseFloat(v) || 0) * 10000) / 10000;
            return Number.isInteger(n) ? String(n) : n.toFixed(4);
        };

        function toggleBomExplosionNode(nodeId) {
            const row = document.querySelector('tr.bom-exp-row[data-node-id="' + nodeId + '"]');
            if (!row) return;
            const isExpanded = row.getAttribute('data-expanded') === 'true';
            if (isExpanded) {
                collapseExplosionSubtree(nodeId);
            } else {
                row.setAttribute('data-expanded', 'true');
                setExplosionChevron(row, true);
                document.querySelectorAll('tr.bom-exp-row[data-parent-id="' + nodeId + '"]').forEach(child => {
                    child.classList.remove('d-none');
                });
            }
        }

        function collapseExplosionSubtree(nodeId) {
            const row = document.querySelector('tr.bom-exp-row[data-node-id="' + nodeId + '"]');
            if (row) {
                row.setAttribute('data-expanded', 'false');
                setExplosionChevron(row, false);
            }
            document.querySelectorAll('tr.bom-exp-row[data-parent-id="' + nodeId + '"]').forEach(child => {
                child.classList.add('d-none');
                const childId = child.getAttribute('data-node-id');
                if (childId && child.getAttribute('data-has-children') === '1') {
                    collapseExplosionSubtree(childId);
                }
            });
        }

        function setExplosionChevron(row, expanded) {
            const btn = row.querySelector('.bom-exp-toggle');
            if (!btn) return;
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            const icon = btn.querySelector('i');
            if (!icon) return;
            icon.classList.toggle('feather-chevron-down', expanded);
            icon.classList.toggle('feather-chevron-right', !expanded);
        }

        function expandAllBomExplosion() {
            document.querySelectorAll('tr.bom-exp-row').forEach(row => {
                row.classList.remove('d-none');
                if (row.getAttribute('data-has-children') === '1') {
                    row.setAttribute('data-expanded', 'true');
                    setExplosionChevron(row, true);
                }
            });
        }

        function collapseBomExplosionToL2() {
            document.querySelectorAll('tr.bom-exp-row').forEach(row => {
                const lvl = parseInt(row.getAttribute('data-level') || '1', 10);
                if (lvl > 2) {
                    row.classList.add('d-none');
                } else {
                    row.classList.remove('d-none');
                }
                if (row.getAttribute('data-has-children') === '1') {
                    const expanded = lvl === 1;
                    row.setAttribute('data-expanded', expanded ? 'true' : 'false');
                    setExplosionChevron(row, expanded);
                }
            });
        }

        function bomCostSimulator(data) {
            return {
                stages: data?.stages || [],
                ops: data?.operations || {},
                baseline: data?.baseline || {},
                baseQty: parseFloat(data?.base_quantity || 1) || 1,
                currencySym: data?.currency_symbol || '₹',
                selectedOps: {},
                selectedMats: {},
                hasCalculated: false,
                results: {
                    directMaterial: 0,
                    scrapCost: 0,
                    laborCost: 0,
                    machineCost: 0,
                    subcontractCost: 0,
                    routingCost: 0,
                    setupCostPerRun: 0,
                    directTotal: 0,
                    directCostPerUnit: 0,
                    directDelta: 0,
                    rolledRawMaterial: 0,
                    rolledSubRouting: 0,
                    rolledTotal: 0,
                    rolledCostPerUnit: 0,
                    rolledSetup: 0,
                    rolledDelta: 0,
                    altOpsSelected: 0,
                    altMatsSelected: 0
                },

                init() {
                    this.resetToPrimary();
                },

                resetToPrimary() {
                    this.selectedOps = {};
                    this.selectedMats = {};
                    this.stages.forEach(st => {
                        this.selectedOps['stage_' + st.primary_id] = st.primary_id;
                    });
                    Object.values(this.ops).forEach(op => {
                        (op.material_slots || []).forEach(slot => {
                            this.selectedMats[slot.slot_key] = slot.primary_id;
                        });
                    });
                    this.hasCalculated = false;
                },

                activeOpForStage(stage) {
                    const id = this.selectedOps['stage_' + stage.primary_id] ?? stage.primary_id;
                    return this.ops[id] || this.ops[stage.primary_id] || { material_slots: [], operation_number: '' };
                },

                selectOp(stagePrimaryId, opId) {
                    this.selectedOps['stage_' + stagePrimaryId] = opId;
                    this.hasCalculated = false;
                },

                selectedMatForSlot(slot) {
                    return this.selectedMats[slot.slot_key] ?? slot.primary_id;
                },

                selectMat(slotKey, matId) {
                    this.selectedMats[slotKey] = matId;
                    this.hasCalculated = false;
                },

                runCalculations() {
                    let directMaterial = 0, scrapCost = 0, labor = 0, machine = 0, subcontract = 0, setupRun = 0;
                    let rolledRaw = 0, rolledSubRouting = 0, rolledSubSetup = 0;
                    let altOpsSelected = 0, altMatsSelected = 0;

                    this.stages.forEach(st => {
                        const op = this.activeOpForStage(st);
                        if (!op) return;
                        labor += parseFloat(op.labor_cost || 0);
                        machine += parseFloat(op.machine_cost || 0);
                        subcontract += parseFloat(op.subcontract_cost || 0);
                        setupRun += parseFloat(op.setup_cost_per_run || 0);
                        if (op.is_alternative) altOpsSelected++;

                        (op.material_slots || []).forEach(slot => {
                            const chosenId = this.selectedMatForSlot(slot);
                            const mOpt = (slot.options || []).find(o => String(o.id) === String(chosenId)) || (slot.options || [])[0];
                            if (!mOpt) return;
                            directMaterial += parseFloat(mOpt.direct_cost || 0);
                            scrapCost += parseFloat(mOpt.scrap_cost || 0);
                            rolledRaw += parseFloat(mOpt.rollup_raw_material_cost || 0);
                            rolledSubRouting += parseFloat(mOpt.rollup_sub_routing_cost || 0);
                            rolledSubSetup += parseFloat(mOpt.rollup_sub_setup_per_run || 0);
                            if (mOpt.is_alternative) altMatsSelected++;
                        });
                    });

                    const routingCost = labor + machine + subcontract;
                    const directTotal = directMaterial + routingCost;
                    const rolledTotal = rolledRaw + routingCost + rolledSubRouting;

                    this.results = {
                        directMaterial,
                        scrapCost,
                        laborCost: labor,
                        machineCost: machine,
                        subcontractCost: subcontract,
                        routingCost,
                        setupCostPerRun: setupRun,
                        directTotal,
                        directCostPerUnit: this.baseQty > 0 ? (directTotal / this.baseQty) : 0,
                        directDelta: directTotal - parseFloat(this.baseline.direct_total || 0),
                        rolledRawMaterial: rolledRaw,
                        rolledSubRouting,
                        rolledTotal,
                        rolledCostPerUnit: this.baseQty > 0 ? (rolledTotal / this.baseQty) : 0,
                        rolledSetup: setupRun + rolledSubSetup,
                        rolledDelta: rolledTotal - parseFloat(this.baseline.rolled_total || 0),
                        altOpsSelected,
                        altMatsSelected
                    };

                    this.hasCalculated = true;
                }
            };
        }

        async function recalculateBomExplosion() {
            const input = document.getElementById('bomExplosionCalcQty');
            const btn = document.getElementById('bomExplosionCalcBtn');
            const tbody = document.getElementById('bomExplosionTableBody');
            const errorBox = document.getElementById('bomExplosionAjaxError');
            if (!input || !btn || !tbody) return;

            const qty = parseFloat(input.value);
            if (!qty || qty <= 0) {
                if (errorBox) {
                    errorBox.textContent = 'Please enter a produce quantity greater than zero.';
                    errorBox.classList.remove('d-none');
                }
                return;
            }

            if (errorBox) errorBox.classList.add('d-none');

            // Remember currently expanded child-BOM nodes so user's open tree stays open
            const openIds = new Set();
            tbody.querySelectorAll('tr.bom-exp-row[data-expanded="true"]').forEach(r => {
                const nid = r.getAttribute('data-node-id');
                if (nid) openIds.add(nid);
            });

            const origLabel = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Calculating...';

            try {
                const url = '{{ route('production.boms.explosion', $bom->id) }}?calc_qty=' + encodeURIComponent(qty);
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const payload = await res.json();
                if (!res.ok) {
                    throw new Error(payload?.message || 'Unable to recalculate explosion.');
                }

                tbody.innerHTML = payload.html;

                // Re-apply expanded nodes from before the AJAX update
                openIds.forEach(nid => {
                    const row = tbody.querySelector('tr.bom-exp-row[data-node-id="' + nid + '"]');
                    if (row && row.getAttribute('data-has-children') === '1') {
                        row.setAttribute('data-expanded', 'true');
                        setExplosionChevron(row, true);
                        tbody.querySelectorAll('tr.bom-exp-row[data-parent-id="' + nid + '"]').forEach(child => {
                            child.classList.remove('d-none');
                        });
                    }
                });
            } catch (err) {
                if (errorBox) {
                    errorBox.textContent = err?.message || 'Failed to recalculate explosion.';
                    errorBox.classList.remove('d-none');
                }
            } finally {
                btn.disabled = false;
                btn.textContent = origLabel;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (window.location.hash === '#tab-explosion') {
                document.querySelector('#tab-explosion-tab')?.click();
            }
        });

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
