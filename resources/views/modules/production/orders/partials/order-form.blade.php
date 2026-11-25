    {{--
        ONE order form for Add and Edit (M-34: "the edit form has all the features of the Add form").
        Add: $editOrder = null.  Edit: $editOrder = the draft order; the controller prefills $selectedProductId / $selectedQty /
        $selectedSoItemId / $productionOrderRequestId / $salesOrder / $project* and passes $editOps / $editMats (the operation and
        material choices saved on the order, so Tab 1 opens with them ticked).
    --}}
    @php
        $editOrder = $editOrder ?? null;
        $isEdit = $editOrder !== null;
        $formAction = $isEdit ? route('production.orders.update', $editOrder->id) : route('production.orders.store');
        $cancelUrl = $isEdit ? route('production.orders.show', $editOrder->id) : route('production.orders.index');
        $poT = [
            'missing_stage' => __('production.po_missing_stage'),
            'missing_material' => __('production.po_missing_material'),
            'primary' => __('production.po_primary'),
            'primary_op' => __('production.po_primary_op'),
            'alt_op' => __('production.po_alt_op'),
            'primary_auto' => __('production.po_primary_auto'),
            'parent_and_child' => __('production.po_parent_and_child'),
            'via_child_only' => __('production.po_via_child_only'),
        ];
    @endphp
        <form method="POST" action="{{ $formAction }}"
              x-data="poCreateWorkspace({ url: @js(route('production.orders.engineering-status')), orderId: @js($editOrder?->id), productId: @js($selectedProductId ?? null), qty: @js(old('quantity_ordered', $selectedQty ?? 1)), oldOps: @js(array_values((array) old('selected_operation_ids', $editOps ?? []))), oldMats: @js(array_values((array) old('selected_material_ids', $editMats ?? []))), t: @js($poT) })"
              @po-product.window="onProduct($event.detail)"
              @po-qty.window="onQty($event.detail)"
              @submit="onSubmit($event)">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif
            <script>window.poOrderFormMode = @js($isEdit ? 'edit' : 'create');</script>

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        @if($isEdit)
                            <h4 class="fw-bold text-dark mb-0">{{ __('production.edit_production_order_number', ['number' => $editOrder->order_number]) }}</h4>
                            <small class="text-muted fs-12">{{ __('production.status') }}: <span class="fw-semibold text-uppercase text-primary">{{ $editOrder->status }}</span> &mdash; {{ __('production.edit_order_draft_notice') }}</small>
                        @else
                            <h4 class="fw-bold text-dark mb-0">{{ __('production.new_production_order') }}</h4>
                            <small class="text-muted fs-12">{{ __('production.bom_routing_frozen_notice') }}</small>
                        @endif
                    </div>
                    <a href="{{ $cancelUrl }}" class="btn btn-sm btn-light border">{{ __('production.cancel') }}</a>
                </div>

                @if($isEdit)
                    {{-- Edit: what is saved on the order now, and what saving does --}}
                    <div class="alert alert-info fs-12 py-2 mb-4" id="edit_rebuild_notice">
                        <div class="fw-semibold mb-1"><i class="feather-info me-1"></i>{{ __('production.edit_saved_plan_title') }}</div>
                        <div>
                            {{ __('production.bill_of_materials') }}:
                            <strong>{{ $editOrder->bom ? ($editOrder->bom->bom_number . ' (v' . $editOrder->bom->version . ')') : '—' }}</strong>
                            &nbsp;·&nbsp; {{ __('production.process_routing') }}:
                            <strong>{{ $editOrder->routing ? ($editOrder->routing->routing_number . ' (v' . $editOrder->routing->version . ')') : '—' }}</strong>
                            &nbsp;·&nbsp; {{ __('production.edit_saved_operations', ['count' => $editOrder->operations->count()]) }}
                        </div>
                        <div class="mt-1">{{ __('production.edit_rebuild_notice') }}</div>
                    </div>
                @endif

                @if(isset($project) && $project)
                    <div class="alert alert-info d-flex align-items-center justify-content-between mb-4 border-info">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-folder text-info fs-18"></i>
                            <div>
                                <strong>{{ __('projects.linked_to_project') ?: 'Linked to Project' }}:</strong>
                                <span class="badge bg-soft-info text-info ms-1">{{ $project->project_code }} — {{ $project->name }}</span>
                                @if(isset($projectTask) && $projectTask)
                                    <span class="mx-1 text-muted">/</span>
                                    <strong>{{ __('projects.task') ?: 'Task' }}:</strong>
                                    <span class="badge bg-soft-primary text-primary ms-1">{{ $projectTask->task_code }} — {{ $projectTask->title }}</span>
                                @endif
                                @if(isset($projectMilestone) && $projectMilestone)
                                    <span class="mx-1 text-muted">/</span>
                                    <strong>{{ __('projects.milestone') ?: 'Milestone' }}:</strong>
                                    <span class="badge bg-soft-secondary text-secondary ms-1">{{ $projectMilestone->name }}</span>
                                @endif
                            </div>
                        </div>
                        @if(isset($projectTask) && $projectTask)
                            <a href="{{ route('projects.tasks.show', [$project, $projectTask]) }}" class="btn btn-xs btn-outline-info" target="_blank">
                                <i class="feather-external-link me-1"></i>{{ __('projects.view_task') ?: 'View Task' }}
                            </a>
                        @else
                            <a href="{{ route('projects.show', $project->project_code) }}" class="btn btn-xs btn-outline-info" target="_blank">
                                <i class="feather-external-link me-1"></i>{{ __('projects.view_project') ?: 'View Project' }}
                            </a>
                        @endif
                    </div>
                @endif

                <input type="hidden" name="project_id" value="{{ old('project_id', $project?->id) }}">
                <input type="hidden" name="project_milestone_id" value="{{ old('project_milestone_id', $projectMilestone?->id) }}">
                <input type="hidden" name="project_task_id" value="{{ old('project_task_id', $projectTask?->id) }}">

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="select" :label="__('production.sales_order_request')" name="production_order_request_id" id="production_order_request_select">
                            <option value="">{{ __('production.select_sales_request') }}</option>
                            @foreach($productionOrderRequests as $request)
                                @php
                                    $deliveryItem = $request->materialRequirementItem;
                                    $delivery = $deliveryItem?->materialRequirement;
                                    $sales = $delivery?->salesOrder ?? $deliveryItem?->salesOrderItem?->salesOrder;
                                    $product = $request->product;
                                @endphp
                                <option value="{{ $request->id }}"
                                    data-product-id="{{ $request->product_id }}"
                                    data-qty="{{ $request->quantity_requested }}"
                                    data-sales-order-id="{{ $sales?->id }}"
                                    data-sales-order-item-id="{{ $deliveryItem?->sales_order_item_id }}"
                                    @selected(old('production_order_request_id', $productionOrderRequestId ?? null) == $request->id)>
                                    {{ $sales?->sales_order_number ?? 'Sales Order #' . ($sales?->id ?? 'N/A') }}
                                    @if($delivery)
                                        / {{ $delivery->requirement_number }}
                                    @endif
                                    @if($product)
                                        — {{ $product->name }} ({{ $product->sku }})
                                    @endif
                                    — Qty: {{ format_decimal((float) $request->quantity_requested) }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <input type="hidden" name="sales_order_id" id="sales_order_id" value="{{ old('sales_order_id', $salesOrder?->id) }}">
                        <input type="hidden" name="sales_order_item_id" id="sales_order_item_id" value="{{ old('sales_order_item_id', $selectedSoItemId ?? '') }}">

                        @if(isset($salesOrder) && $salesOrder)
                            <x-ui.odoo-form-ui type="input" label="Sales Order Reference" name="sales_order_number" :value="$salesOrder->sales_order_number" readonly="true" style="font-weight: bold; background-color: #f8f9fa;" />
                        @endif

                        <x-ui.odoo-form-ui type="select" :label="isset($salesOrder) && $salesOrder ? __('production.target_product_so') : __('production.target_product')" name="product_id" id="product_select" :required="true">
                            <option value="">{{ __('production.select_target_product') }}</option>
                            @foreach($products as $product)
                                @php
                                    $soItem = $salesOrderItems->firstWhere('product_id', $product->id);
                                @endphp
                                <option value="{{ $product->id }}"
                                    @if($soItem) data-qty="{{ $soItem->quantity }}" data-so-item-id="{{ $soItem->id }}" @endif
                                    @selected(($selectedProductId ?? null) == $product->id)>
                                    {{ $product->name }} ({{ $product->sku }}) @if($soItem) — Qty: {{ format_decimal((float) $soItem->quantity) }} @endif
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        {{-- PO-01: read-only BOM & Routing, resolved from the product (no dropdowns) --}}
                        <div class="odoo-form-group">
                            <label class="odoo-form-label">{{ __('production.bill_of_materials') }}</label>
                            <div class="flex-grow-1">
                                <input type="hidden" name="bom_id" :value="requiresEngineering && data.bom ? data.bom.id : ''">
                                <input type="text" id="bom_display" class="form-control form-control-sm bg-light" readonly
                                       :value="loading ? @js(__('production.po_loading_engineering')) : (!productId ? @js(__('production.po_select_product_first')) : (!requiresEngineering ? @js(__('production.po_not_required')) : (data.bom ? data.bom.bom_number + ' — ' + (data.bom.bom_name || '') + ' (v' + data.bom.version + ', ' + data.bom.status + ')' : @js(__('production.po_no_bom')))))">
                                <template x-if="bomErrors.length">
                                    <div id="bom_warning_box" class="alert alert-danger py-1 px-2 mt-1 mb-0 fs-12">
                                        <template x-for="msg in bomErrors" :key="msg"><div><i class="feather-alert-triangle me-1"></i><span x-text="msg"></span></div></template>
                                    </div>
                                </template>
                            </div>
                        </div>
                        @if(config('production.temp_flow'))
                        {{-- Temp no-BOM flow: pick any routing (a list of operations) or leave empty for one default operation --}}
                        <div class="odoo-form-group">
                            <label class="odoo-form-label">{{ __('production.process_routing') }}</label>
                            <div class="flex-grow-1">
                                <select name="routing_id" id="routing_temp_select" class="form-select form-select-sm">
                                    <option value="">— No routing (single default operation) —</option>
                                    @foreach(\App\Domains\Production\Models\Routing::query()->orderBy('name')->get(['id', 'name', 'routing_number', 'version']) as $tempRouting)
                                        <option value="{{ $tempRouting->id }}" @selected(old('routing_id', $editOrder?->routing_id) == $tempRouting->id)>{{ $tempRouting->routing_number }} — {{ $tempRouting->name }} (v{{ $tempRouting->version }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @else
                        <div class="odoo-form-group">
                            <label class="odoo-form-label">{{ __('production.process_routing') }}</label>
                            <div class="flex-grow-1">
                                <input type="hidden" name="routing_id" :value="requiresEngineering && data.routing ? data.routing.id : ''">
                                <input type="text" id="routing_display" class="form-control form-control-sm bg-light" readonly
                                       :value="loading ? @js(__('production.po_loading_engineering')) : (!productId ? @js(__('production.po_select_product_first')) : (!requiresEngineering ? @js(__('production.po_not_required')) : (data.routing ? data.routing.routing_number + ' — ' + data.routing.name + ' (v' + data.routing.version + ', ' + (data.routing.cost_status === 'calculated' ? @js(__('production.po_costs_calculated')) : @js(__('production.po_costs_blocked'))) + ')' : @js(__('production.po_no_routing')))))">
                                <template x-if="routingErrors.length">
                                    <div id="routing_warning_box" class="alert alert-danger py-1 px-2 mt-1 mb-0 fs-12">
                                        <template x-for="msg in routingErrors" :key="msg"><div><i class="feather-alert-triangle me-1"></i><span x-text="msg"></span></div></template>
                                    </div>
                                </template>
                            </div>
                        </div>
                        @endif

                        <x-ui.odoo-form-ui type="select" label="{{ __('production.production_mode') }}" name="production_mode" id="production_mode_select">
                            @php $modeNow = old('production_mode', $editOrder?->production_mode ?? 'standard'); @endphp
                            <option value="standard" @selected($modeNow === 'standard')>{{ __('production.mode_standard') }}</option>
                            <option value="batch" @selected($modeNow === 'batch')>{{ __('production.mode_batch') }}</option>
                            <option value="serial" @selected($modeNow === 'serial')>{{ __('production.mode_serial') }}</option>
                            <option value="batch_and_serial" @selected($modeNow === 'batch_and_serial')>{{ __('production.mode_batch_and_serial') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.qty_to_manufacture')" name="quantity_ordered" inputType="number" placeholder="e.g. 10" :value="old('quantity_ordered', $selectedQty ?? '1')" :required="true" step="0.0001" />

                        <x-ui.odoo-form-ui type="input" :label="__('production.start_date')" name="start_date" inputType="date" :value="old('start_date', $editOrder ? $editOrder->start_date->format('Y-m-d') : date('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="input" :label="__('production.end_date')" name="end_date" inputType="date" :value="old('end_date', $editOrder ? $editOrder->end_date->format('Y-m-d') : date('Y-m-d', strtotime('+3 days')))" :required="true" />

                        <x-ui.odoo-form-ui type="textarea" :label="__('production.remarks')" name="description" :placeholder="__('production.remarks_placeholder')" rows="4">{{ old('description', $editOrder?->description) }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- PO-02: blocking alert --}}
                <template x-if="blocked">
                    <div id="engineering_block_alert" class="alert alert-danger mt-4 mb-0">
                        <div class="fw-bold mb-1"><i class="feather-slash me-1"></i>{{ __('production.po_blocked_title') }}</div>
                        <div class="fs-12 mb-1">{{ __('production.po_blocked_body') }}</div>
                        <ul class="mb-0 fs-12 ps-3">
                            <template x-for="msg in data.validation.blocking_reasons" :key="msg"><li x-text="msg"></li></template>
                        </ul>
                    </div>
                </template>
                <template x-if="loadError">
                    <div class="alert alert-warning mt-4 mb-0 fs-12"><span x-text="loadError"></span></div>
                </template>

                {{-- PO-06: hidden mirrors so locked (disabled) radios still post --}}
                <template x-if="requiresEngineering">
                    <div>
                        <input type="hidden" name="engineering_selection_submitted" value="1">
                        <template x-for="id in selectedOperationIds" :key="'op' + id"><input type="hidden" name="selected_operation_ids[]" :value="id"></template>
                        <template x-for="id in selectedMaterialIds" :key="'m' + id"><input type="hidden" name="selected_material_ids[]" :value="id"></template>
                    </div>
                </template>

                {{-- PO-05: read-only 2-tab engineering workspace --}}
                <div class="po-ws card border mt-4" x-show="requiresEngineering && data && data.bom && stages.length" x-cloak>
                    <div class="card-header bg-light py-2 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item"><a href="#" class="nav-link" :class="tab === 'tab1' && 'active'" @click.prevent="tab = 'tab1'"><i class="feather-layers me-1"></i>{{ __('production.po_tab_components') }}</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" :class="tab === 'tab2' && 'active'" @click.prevent="tab = 'tab2'"><i class="feather-bar-chart-2 me-1"></i>{{ __('production.po_tab_rollup') }} <span class="badge bg-soft-danger text-danger ms-1" x-show="shortageCount" x-text="shortageCount"></span></a></li>
                        </ul>
                        <span class="fs-11 text-muted">{{ __('production.po_branch_stock_note') }}</span>
                    </div>

                    {{-- PO-07: completeness banner --}}
                    <template x-if="submitTried && missing.length">
                        <div class="alert alert-danger m-3 mb-0 fs-12">
                            <div class="fw-bold">{{ __('production.po_incomplete_title') }}</div>
                            <template x-for="m in missing" :key="m.text"><div x-text="m.text"></div></template>
                        </div>
                    </template>

                    <div class="card-body">
                        {{-- ===== Tab 1 ===== --}}
                        <div x-show="tab === 'tab1'">
                            <template x-for="stage in stages" :key="stage.stage_key">
                                <div class="po-stage">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-bold text-dark">{{ __('production.po_stage') }} <span x-text="stage.operation_number"></span></span>
                                        <span class="text-muted" x-text="stage.name"></span>
                                        <span class="badge bg-soft-warning text-warning" x-show="stage.has_alternative_ops">{{ __('production.po_switchable') }}</span>
                                    </div>
                                    <template x-for="op in stage.operations" :key="op.id">
                                        <div class="po-op-card" :id="'po-op-card-' + op.id"
                                             :class="{ 'is-dimmed': !isOpSelected(stage, op), 'is-highlight': highlightOp === op.id, 'is-incomplete': isIncomplete(op) }">
                                            <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom">
                                                <input type="radio" class="form-check-input mt-0" :name="'po_stage_' + stage.stage_key"
                                                       :checked="isOpSelected(stage, op)" :disabled="!stage.has_alternative_ops" @change="pickOp(stage, op)">
                                                <span class="fw-semibold" x-text="op.operation_number + ' · ' + op.name"></span>
                                                <span class="badge" :class="op.is_alternative ? 'bg-soft-warning text-warning' : 'bg-soft-primary text-primary'"
                                                      x-text="op.is_alternative ? @js(__('production.po_alt_op')) + ' P' + (op.priority ?? '') : @js(__('production.po_primary_op'))"></span>
                                                <span class="badge bg-soft-success text-success" x-show="!stage.has_alternative_ops">{{ __('production.po_auto_selected') }}</span>
                                                <span class="ms-auto fs-11 text-muted">
                                                    <span x-text="op.work_center_code || op.work_center || ''"></span>
                                                    <span x-show="op.resource" x-text="' · ' + op.resource"></span>
                                                    · {{ __('production.bom_setup_per_run') }} <span x-text="fmtMin(op.setup_time_minutes)"></span>
                                                    · {{ __('production.bom_setup_per_piece') }} <span x-text="fmtMin(op.setup_time_per_piece_minutes)"></span>
                                                    · {{ __('production.bom_run_per_unit') }} <span x-text="fmtMin(op.processing_time_minutes)"></span>
                                                    <span x-show="op.expected_yield_percentage !== null"> · {{ __('production.bom_yield') }} <span x-text="fmt(op.expected_yield_percentage) + '%'"></span></span>
                                                </span>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0 fs-12">
                                                    <thead class="text-muted text-uppercase fs-11">
                                                        <tr>
                                                            <th style="width:32px"></th>
                                                            <th>{{ __('production.component_name') }}</th>
                                                            <th>{{ __('production.bom_rollup_role') }}</th>
                                                            <th class="text-end">{{ __('production.po_qty_per_base') }}</th>
                                                            <th class="text-end">{{ __('production.po_scrap') }}</th>
                                                            <th class="text-end">{{ __('production.po_qty_for_order') }}</th>
                                                            <th>{{ __('production.uom') }}</th>
                                                            <th>{{ __('production.po_child_bom') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-if="!op.material_slots.length">
                                                            <tr><td colspan="8" class="text-muted fst-italic">{{ __('production.po_no_materials_on_op') }}</td></tr>
                                                        </template>
                                                        <template x-for="pair in slotRows(op)" :key="pair.m.id">
                                                                <tr :class="!isMatSelected(op, pair.slot, pair.m) && 'text-muted'" x-data="{ get slot() { return pair.slot }, get m() { return pair.m } }">
                                                                    <td>
                                                                        <input type="radio" class="form-check-input" :name="'po_slot_' + op.id + '_' + slot.slot_key"
                                                                               :checked="isMatSelected(op, slot, m)" :disabled="!slot.has_alternatives || !isOpSelected(stage, op)" @change="pickMat(op, slot, m)">
                                                                    </td>
                                                                    <td><div class="fw-semibold text-dark" x-text="m.name"></div><div class="fs-11 text-muted" x-text="m.sku"></div></td>
                                                                    <td>
                                                                        <span class="badge" :class="m.is_alternative ? 'bg-soft-warning text-warning' : 'bg-soft-primary text-primary'" x-text="m.is_alternative ? 'Alt P' + (m.priority ?? '') : @js(__('production.po_primary'))"></span>
                                                                        <span class="badge bg-soft-success text-success" x-show="!slot.has_alternatives">{{ __('production.po_auto_selected') }}</span>
                                                                    </td>
                                                                    <td class="po-num" x-text="fmt(m.quantity)"></td>
                                                                    <td class="po-num" x-text="fmt(m.material_scrap_percentage) + '%'"></td>
                                                                    <td class="po-num fw-semibold" x-text="fmt(m.gross_quantity * scale)"></td>
                                                                    <td x-text="m.uom"></td>
                                                                    <td>
                                                                        <a href="#" x-show="m.child_bom_id" class="badge bg-soft-info text-info" @click.prevent.stop="openChild(m.child_bom_id)" x-text="(m.child_bom_number || @js(__('production.po_child_bom'))) + ' ↗'"></a>
                                                                    </td>
                                                                </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- ===== Tab 2 ===== --}}
                        <div x-show="tab === 'tab2'">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <span class="po-pill bg-soft-primary text-primary"><span x-text="plan.rows.length"></span> {{ __('production.po_items') }}</span>
                                <span class="po-pill bg-soft-danger text-danger"><span x-text="shortageCount"></span> {{ __('production.po_shortages') }}</span>
                                <span class="po-pill bg-soft-warning text-warning"><span x-text="produceCount"></span> {{ __('production.po_to_produce_count') }}</span>
                                <div class="btn-group btn-group-sm ms-auto">
                                    <button type="button" class="btn" :class="filter === 'all' ? 'btn-primary' : 'btn-light border'" @click="filter = 'all'">{{ __('production.po_filter_all') }}</button>
                                    <button type="button" class="btn" :class="filter === 'short' ? 'btn-primary' : 'btn-light border'" @click="filter = 'short'">{{ __('production.po_filter_short') }}</button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 fs-12">
                                    <thead class="text-muted text-uppercase fs-11">
                                        <tr>
                                            <th>{{ __('production.component_name') }}</th>
                                            <th class="text-end">{{ __('production.po_parent_req') }}</th>
                                            <th class="text-end">{{ __('production.po_child_req') }}</th>
                                            <th class="text-end">{{ __('production.po_total_req') }}</th>
                                            <th class="text-end">{{ __('production.po_branch_avail') }}</th>
                                            <th>{{ __('production.uom') }}</th>
                                            <th>{{ __('production.po_status_action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody x-show="!visibleRows.length">
                                        <tr><td colspan="7" class="text-muted fst-italic">{{ __('production.po_no_rows') }}</td></tr>
                                    </tbody>
                                        <template x-for="r in visibleRows" :key="r.product_id">
                                            <tbody>
                                                <tr class="po-row" @click="toggleRow(r)">
                                                    <td>
                                                        <i class="feather-chevron-right me-1" :class="openRows[r.product_id] && 'feather-chevron-down'"></i>
                                                        <span class="fw-semibold text-dark" x-text="r.name"></span>
                                                        <span class="fs-11 text-muted" x-text="r.sku"></span>
                                                        <span class="badge bg-soft-secondary text-secondary ms-1" x-show="rowBadge(r)" x-text="rowBadge(r)"></span>
                                                        <a href="#" class="badge bg-soft-info text-info ms-1" x-show="r.child_bom_id" @click.prevent.stop="openChild(r.child_bom_id)" x-text="(r.child_bom_number || @js(__('production.po_child_bom'))) + ' ↗'"></a>
                                                    </td>
                                                    <td class="po-num" x-text="r.parent_qty > 0 ? fmt(r.parent_qty) : '—'"></td>
                                                    <td class="po-num" x-text="r.child_qty > 0 ? '+' + fmt(r.child_qty) : '—'"></td>
                                                    <td class="po-num fw-bold" x-text="fmt(r.total_req)"></td>
                                                    <td class="po-num" x-text="fmt(r.branch_avail)"></td>
                                                    <td x-text="r.uom"></td>
                                                    <td>
                                                        <template x-if="r.child_bom_id && r.to_produce > 0">
                                                            <span class="text-warning fw-semibold">⚙ <span x-text="@js(__('production.po_produce')).replace(':qty', fmt(r.to_produce))"></span> <span class="text-muted fw-normal" x-text="'(' + @js(__('production.po_from_stock')).replace(':qty', fmt(r.from_stock)) + ')'"></span></span>
                                                        </template>
                                                        <template x-if="r.child_bom_id && r.to_produce <= 0">
                                                            <span class="text-success">✔ {{ __('production.po_covered_by_stock') }}</span>
                                                        </template>
                                                        <template x-if="!r.child_bom_id && r.shortage > 0">
                                                            <span class="text-danger fw-semibold">⚠ <span x-text="@js(__('production.po_short')).replace(':qty', fmt(r.shortage))"></span> <span class="text-muted fw-normal" x-text="'(' + @js(__('production.po_from_stock')).replace(':qty', fmt(r.from_stock)) + ')'"></span></span>
                                                        </template>
                                                        <template x-if="!r.child_bom_id && r.shortage <= 0">
                                                            <span class="text-success">✔ {{ __('production.po_sufficient') }}</span>
                                                        </template>
                                                    </td>
                                                </tr>
                                                {{-- PO-09: source allocation breakdown drawer --}}
                                                <tr class="po-drawer" x-show="openRows[r.product_id]">
                                                    <td colspan="7">
                                                        <div class="fs-12 mb-2">
                                                            {{ __('production.po_parent_req') }} <strong x-text="fmt(r.parent_qty)"></strong>
                                                            + {{ __('production.po_child_req') }} <strong x-text="fmt(r.child_qty)"></strong>
                                                            = <strong x-text="fmt(r.total_req)"></strong>
                                                            | {{ __('production.po_branch_avail') }} <strong x-text="fmt(r.branch_avail)"></strong>
                                                        </div>
                                                        <table class="table table-sm table-bordered bg-white mb-0 fs-11">
                                                            <thead class="text-muted">
                                                                <tr>
                                                                    <th>{{ __('production.po_source_level') }}</th>
                                                                    <th>{{ __('production.bom_operation') }}</th>
                                                                    <th>{{ __('production.po_selection') }}</th>
                                                                    <th class="text-end">{{ __('production.po_net_qty') }}</th>
                                                                    <th class="text-end">{{ __('production.po_scrap') }}</th>
                                                                    <th class="text-end">{{ __('production.po_gross_qty') }}</th>
                                                                    <th></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <template x-for="(s, i) in r.parent_sources" :key="'p' + i">
                                                                    <tr>
                                                                        <td>{{ __('production.po_l1_parent') }} <span x-text="data.bom.bom_number"></span></td>
                                                                        <td x-text="s.operation || '—'"></td>
                                                                        <td x-text="s.selection"></td>
                                                                        <td class="po-num" x-text="fmt(s.net_qty)"></td>
                                                                        <td class="po-num" x-text="fmt(s.scrap_pct) + '%'"></td>
                                                                        <td class="po-num" x-text="fmt(s.gross_qty)"></td>
                                                                        <td><button type="button" class="btn btn-xs btn-light border" x-show="s.operation_id" @click.stop="jumpToOp(s.operation_id)">{{ __('production.po_jump_to_op') }}</button></td>
                                                                    </tr>
                                                                </template>
                                                                <template x-for="(s, i) in r.child_sources" :key="'c' + i">
                                                                    <tr>
                                                                        <td><span x-text="'L' + s.level"></span> · <span x-text="s.bom_number"></span></td>
                                                                        <td x-text="(s.operation || '—') + ' (' + @js(__('production.po_producing')) + ' ' + fmt(s.producing_qty) + ')'"></td>
                                                                        <td x-text="s.selection"></td>
                                                                        <td class="po-num" x-text="fmt(s.net_qty)"></td>
                                                                        <td class="po-num" x-text="fmt(s.scrap_pct) + '%'"></td>
                                                                        <td class="po-num" x-text="fmt(s.gross_qty)"></td>
                                                                        <td><button type="button" class="btn btn-xs btn-light border" @click.stop="openChild(s.bom_id)">{{ __('production.po_view_child_bom') }}</button></td>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </template>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PO-11: in-page child BOM modal (primary operations + primary materials only) --}}
                <template x-if="modalBom">
                    <div class="po-modal-backdrop" @click.self="closeModal()" @keydown.escape.window="closeModal()">
                        <div class="po-modal po-ws">
                            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                                <button type="button" class="btn btn-xs btn-light border" x-show="modalStack.length > 1" @click="modalBack()">{{ __('production.po_back') }}</button>
                                <div class="fs-12 text-muted">
                                    <template x-for="(id, i) in modalStack" :key="'bc' + i">
                                        <span><span x-show="i > 0"> › </span><span x-text="data.child_boms[id].product_name + ' (' + data.child_boms[id].bom_number + ')'"></span></span>
                                    </template>
                                </div>
                                <button type="button" class="btn-close ms-auto" @click="closeModal()" aria-label="{{ __('production.po_close') }}"></button>
                            </div>
                            <div class="px-3 py-2 bg-light border-bottom fs-12 d-flex flex-wrap gap-3">
                                <span><strong x-text="modalBom.bom_number"></strong> v<span x-text="modalBom.version"></span> · <span x-text="modalBom.status"></span></span>
                                <span x-text="modalBom.product_name + ' (' + (modalBom.product_sku || '') + ')'"></span>
                                <span>{{ __('production.po_modal_po_needs') }}: <strong x-text="fmt(modalInfo ? modalInfo.required : 0)"></strong></span>
                                <span>{{ __('production.po_branch_avail') }}: <strong x-text="fmt(modalInfo ? modalInfo.avail : 0)"></strong></span>
                            </div>
                            <div class="px-3 pt-3">
                                <div class="alert py-2 fs-12 mb-2" :class="modalProducing > 0 ? 'alert-warning' : 'alert-success'">
                                    <span x-show="modalProducing > 0" x-text="@js(__('production.po_modal_producing')).replace(':qty', fmt(modalProducing))"></span>
                                    <span x-show="modalProducing <= 0" x-text="@js(__('production.po_modal_covered')).replace(':base', fmt(modalBom.base_quantity))"></span>
                                </div>
                                <div class="fs-11 text-muted mb-2">{{ __('production.po_modal_primary_only') }}</div>
                            </div>
                            <div class="px-3 pb-3">
                                <template x-for="op in modalBom.operations" :key="'mo' + op.id">
                                    <div class="po-op-card">
                                        <div class="px-3 py-2 border-bottom fw-semibold" x-text="op.operation_number + ' · ' + op.name"></div>
                                        <table class="table table-sm mb-0 fs-12">
                                            <thead class="text-muted text-uppercase fs-11">
                                                <tr>
                                                    <th>{{ __('production.component_name') }}</th>
                                                    <th class="text-end">{{ __('production.po_qty_per_base') }}</th>
                                                    <th class="text-end" x-text="modalProducing > 0 ? @js(__('production.po_qty_for_shortage')) : @js(__('production.po_base_preview_qty'))"></th>
                                                    <th>{{ __('production.uom') }}</th>
                                                    <th class="text-end">{{ __('production.po_scrap') }}</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="m in op.materials" :key="'mm' + m.id">
                                                    <tr>
                                                        <td>
                                                            <span class="fw-semibold text-dark" x-text="m.name"></span>
                                                            <span class="badge bg-soft-secondary text-secondary ms-1" x-show="inParent(m.material_id)">{{ __('production.po_also_in_parent') }}</span>
                                                        </td>
                                                        <td class="po-num" x-text="fmt(m.quantity)"></td>
                                                        <td class="po-num fw-semibold" x-text="fmt(m.gross_quantity * modalScale)"></td>
                                                        <td x-text="m.uom"></td>
                                                        <td class="po-num" x-text="fmt(m.material_scrap_percentage) + '%'"></td>
                                                        <td>
                                                            <span class="text-success fs-11">{{ __('production.po_primary_auto') }}</span>
                                                            <button type="button" class="btn btn-xs btn-light border ms-1" x-show="m.child_bom_id && data.child_boms[m.child_bom_id]" @click="openChild(m.child_bom_id, true)" x-text="@js(__('production.po_view_child_bom')) + ' →'"></button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Footer Actions --}}
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" id="submit_production_order_btn" class="btn btn-primary px-4" :disabled="loading || blocked">
                        <i class="feather-check-circle me-1"></i>{{ $isEdit ? __('production.save_changes') : __('production.create_production_order') }}
                    </button>
                    <x-ui.button :href="$cancelUrl" variant="secondary" class="px-4">{{ __('production.cancel') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
