<div class="border rounded-3 p-3 mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold text-dark mb-0">
            <i class="feather-cpu me-2 text-primary"></i>{{ __('projects.manufacturing_and_procurement') }}
        </h6>
        <div class="d-flex gap-2">
            <x-ui.badge variant="info" class="fs-11">
                <i class="feather-package me-1"></i>{{ $task->productionOrders->count() }} {{ __('projects.mo_count') }}
            </x-ui.badge>
            <x-ui.badge variant="primary" class="fs-11">
                <i class="feather-shopping-cart me-1"></i>{{ $task->purchaseRequisitionItems->count() }} {{ __('projects.pr_count') }}
            </x-ui.badge>
        </div>
    </div>

    {{-- Production Orders Section --}}
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
            <span class="fs-12 fw-bold text-uppercase text-muted">
                <i class="feather-tool me-1"></i>{{ __('projects.linked_production_orders') }}
            </span>
            <div class="d-flex align-items-center gap-1.5">
                @if (isset($isTradeItem) && $isTradeItem)
                    <span class="badge bg-soft-info text-info fs-11" title="{{ __('projects.trade_item_notice') ?: 'Trade / Purchased Item' }}">
                        <i class="feather-shopping-bag me-1"></i>{{ __('projects.trade_item') ?: 'Trade Item' }}
                    </span>
                @else
                    @if ($canManageTask && (!isset($canViewProduction) || $canViewProduction))
                        @php
                            $createPoParams = ['project_id' => $project->id, 'project_task_id' => $task->id];
                            if (isset($linkedProduct) && $linkedProduct) {
                                $createPoParams['product_id'] = $linkedProduct->id;
                            }
                            if (isset($linkedSalesOrderItem) && $linkedSalesOrderItem) {
                                $createPoParams['quantity'] = $linkedSalesOrderItem->quantity;
                            }
                        @endphp
                        <x-ui.button variant="outline-success" size="xs" class="py-0 px-2" :href="route('production.orders.create', $createPoParams)" icon="feather-plus-circle" target="_blank">
                            {{ __('projects.create_production_order') }}
                        </x-ui.button>
                    @endif
                    @if ($canManageTask && isset($availableProductionOrders) && $availableProductionOrders->isNotEmpty())
                        <x-ui.button variant="outline-primary" size="xs" class="py-0 px-2" data-bs-toggle="collapse" data-bs-target="#linkPoCollapse" icon="feather-link">
                            {{ __('projects.link_production_order') }}
                        </x-ui.button>
                    @endif
                @endif
            </div>
        </div>

        @if ($canManageTask && isset($availableProductionOrders) && $availableProductionOrders->isNotEmpty())
            <div class="collapse mb-3" id="linkPoCollapse">
                <form action="{{ route('projects.tasks.link-production-order', [$project, $task]) }}" method="POST" class="bg-light p-2.5 rounded border d-flex gap-2 align-items-center">
                    @csrf
                    <div class="flex-grow-1">
                        <x-ui.odoo-form-ui type="select" name="production_order_id" :required="true">
                            <option value="">-- {{ __('projects.select_production_order') }} --</option>
                            @foreach ($availableProductionOrders as $availPo)
                                <option value="{{ $availPo->id }}">
                                    {{ $availPo->order_number }} ({{ $availPo->status }}) — {{ (float) $availPo->quantity_produced }}/{{ (float) $availPo->quantity_ordered }} units
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <x-ui.button type="submit" variant="primary" size="sm" class="text-nowrap" icon="feather-link">
                        {{ __('projects.link') }}
                    </x-ui.button>
                </form>
            </div>
        @endif

        @if ($task->productionOrders->isNotEmpty())
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" tableClass="table table-sm table-hover align-middle mb-0 fs-12">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('projects.order_number') }}</th>
                            <th>{{ __('projects.product') }}</th>
                            <th>{{ __('projects.progress') }}</th>
                            <th>{{ __('projects.status') }}</th>
                            <th class="text-end">{{ __('projects.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($task->productionOrders as $po)
                            @php
                                $ordered = (float) ($po->quantity_ordered ?: 0);
                                $produced = (float) ($po->quantity_produced ?: 0);
                                $pct = $ordered > 0 ? min(100, round(($produced / $ordered) * 100)) : 0;
                            @endphp
                            <tr>
                                <td class="fw-bold">
                                    <a href="{{ route('production.orders.show', $po->id) }}" class="text-primary text-decoration-none" target="_blank">
                                        {{ $po->order_number }}
                                        <i class="feather-external-link ms-1 fs-10 text-muted"></i>
                                    </a>
                                </td>
                                <td>{{ $po->product?->name ?: '—' }}</td>
                                <td style="width: 140px;">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: {{ $pct }}%;"></div>
                                        </div>
                                        <span class="fs-10 text-muted fw-semibold">{{ $produced }}/{{ $ordered }}</span>
                                    </div>
                                </td>
                                <td>
                                    <x-ui.status-badge :status="$po->status" class="fs-10 text-uppercase" />
                                </td>
                                <td class="text-end">
                                    @if ($canManageTask)
                                        <form action="{{ route('projects.tasks.unlink-production-order', [$project, $task, $po->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('projects.confirm_unlink_po') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="outline-danger" size="xs" class="py-0 px-1.5" :title="__('projects.unlink')" icon="feather-trash-2" />
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-muted fs-12 py-2 text-center bg-light rounded border border-dashed">
                @if (isset($isTradeItem) && $isTradeItem)
                    <i class="feather-info me-1 text-primary"></i>{{ __('projects.trade_item_procurement_hint', ['product' => $linkedProduct?->name ?? 'item']) }}
                @else
                    <i class="feather-package me-1 text-muted"></i>{{ __('projects.no_production_orders_linked') }}
                @endif
            </div>
        @endif
    </div>

    {{-- Purchase Requisitions Section --}}
    <div>
        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
            <span class="fs-12 fw-bold text-uppercase text-muted">
                <i class="feather-shopping-bag me-1"></i>{{ __('projects.linked_purchase_requisitions') }}
            </span>
            <div class="d-flex align-items-center gap-1.5">
                @if ($canManageTask)
                    <x-ui.button variant="outline-success" size="xs" class="py-0 px-2" :href="route('purchase.requisitions.create')" icon="feather-plus-circle" target="_blank">
                        {{ __('projects.create_purchase_requisition') ?: 'Create PR' }}
                    </x-ui.button>
                @endif
                @if ($canManageTask && isset($availablePrItems) && $availablePrItems->isNotEmpty())
                    <x-ui.button variant="outline-primary" size="xs" class="py-0 px-2" data-bs-toggle="collapse" data-bs-target="#linkPrCollapse" icon="feather-link">
                        {{ __('projects.link_purchase_requisition') }}
                    </x-ui.button>
                @endif
            </div>
        </div>

        @if ($canManageTask && isset($availablePrItems) && $availablePrItems->isNotEmpty())
            <div class="collapse mb-3" id="linkPrCollapse">
                <form action="{{ route('projects.tasks.link-purchase-requisition', [$project, $task]) }}" method="POST" class="bg-light p-2.5 rounded border d-flex gap-2 align-items-center">
                    @csrf
                    <div class="flex-grow-1">
                        <x-ui.odoo-form-ui type="select" name="purchase_requisition_item_id" :required="true">
                            <option value="">-- {{ __('projects.select_pr_item') }} --</option>
                            @foreach ($availablePrItems as $availPrItem)
                                <option value="{{ $availPrItem->id }}">
                                    {{ $availPrItem->requisition?->requisition_number ?: "PR #{$availPrItem->purchase_requisition_id}" }} — {{ $availPrItem->product?->name }} (Qty: {{ (float) $availPrItem->quantity }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <x-ui.button type="submit" variant="primary" size="sm" class="text-nowrap" icon="feather-link">
                        {{ __('projects.link') }}
                    </x-ui.button>
                </form>
            </div>
        @endif

        @if ($task->purchaseRequisitionItems->isNotEmpty())
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" tableClass="table table-sm table-hover align-middle mb-0 fs-12">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('projects.pr_number') }}</th>
                            <th>{{ __('projects.product') }}</th>
                            <th>{{ __('projects.quantity') }}</th>
                            <th>{{ __('projects.estimated_cost') }}</th>
                            <th class="text-end">{{ __('projects.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($task->purchaseRequisitionItems as $prItem)
                            <tr>
                                <td class="fw-bold">
                                    <a href="{{ route('purchase.requisitions.show', $prItem->purchase_requisition_id) }}" class="text-primary text-decoration-none" target="_blank">
                                        {{ $prItem->requisition?->requisition_number ?: "PR #{$prItem->purchase_requisition_id}" }}
                                        <i class="feather-external-link ms-1 fs-10 text-muted"></i>
                                    </a>
                                </td>
                                <td>{{ $prItem->product?->name ?: '—' }}</td>
                                <td>{{ (float) $prItem->quantity }}</td>
                                <td>{{ format_currency($prItem->estimated_cost) }}</td>
                                <td class="text-end">
                                    @if ($canManageTask)
                                        <form action="{{ route('projects.tasks.unlink-purchase-requisition', [$project, $task, $prItem->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('projects.confirm_unlink_pr') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="outline-danger" size="xs" class="py-0 px-1.5" :title="__('projects.unlink')" icon="feather-trash-2" />
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        @else
            <div class="text-muted fs-12 py-2 text-center bg-light rounded border border-dashed">
                <i class="feather-shopping-cart me-1 text-muted"></i>{{ __('projects.no_purchase_requisitions_linked') }}
                <div class="fs-11 text-muted mt-1">
                    {{ __('projects.pr_link_help') ?: 'Purchase Requisitions allow procurement to source raw materials or traded products for this task.' }}
                </div>
            </div>
        @endif
    </div>
</div>
