@extends('layouts.duralux')

@section('title', __('production.create_production_order') . ' | SaaS ERP')
@section('page-title', __('production.create_direct_order'))
@section('breadcrumb', __('production.create_production_order'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* PO-IMPL-06: read-only engineering workspace */
        .po-ws .po-op-card { border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; background: #fff; transition: box-shadow .2s, opacity .2s; }
        .po-ws .po-op-card.is-dimmed { opacity: .55; }
        .po-ws .po-op-card.is-highlight { box-shadow: 0 0 0 3px rgba(59,130,246,.45); }
        .po-ws .po-op-card.is-incomplete { border-color: #dc3545; }
        .po-ws .po-stage { border-left: 3px solid #3b82f6; padding-left: 12px; margin-bottom: 18px; }
        .po-ws .po-num { font-family: var(--bs-font-monospace, monospace); text-align: right; white-space: nowrap; }
        .po-ws .po-drawer td { background: #f8fafc; }
        .po-ws tr.po-row { cursor: pointer; }
        .po-ws .po-pill { font-size: 12px; padding: 4px 10px; border-radius: 999px; }
        .po-modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 1060; display: flex; align-items: flex-start; justify-content: center; padding: 40px 16px; overflow-y: auto; }
        .po-modal { background: #fff; border-radius: 10px; width: 100%; max-width: 960px; box-shadow: 0 20px 50px rgba(0,0,0,.25); }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('#production_order_request_select').on('change', function () {
                var selectedOption = $(this).find('option:selected');
                var productId = selectedOption.data('product-id');
                var qty = selectedOption.data('qty');
                var salesOrderId = selectedOption.data('sales-order-id');
                var salesOrderItemId = selectedOption.data('sales-order-item-id');

                if (qty !== undefined && qty !== '') {
                    $('input[name="quantity_ordered"]').val(qty).trigger('input');
                }
                if (productId !== undefined && productId !== '') {
                    $('#product_select').val(productId).trigger('change');
                }
                if (salesOrderId !== undefined && salesOrderId !== '') {
                    $('#sales_order_id').val(salesOrderId);
                }
                if (salesOrderItemId !== undefined && salesOrderItemId !== '') {
                    $('#sales_order_item_id').val(salesOrderItemId);
                }
            });

            // Sales Order item autofill + PO-IMPL-06: load BOM / Routing workspace for the chosen product
            $('#product_select').on('change', function () {
                var selectedOption = $(this).find('option:selected');
                var qty = selectedOption.data('qty');
                var soItemId = selectedOption.data('so-item-id');

                if (qty !== undefined && qty !== '') {
                    $('input[name="quantity_ordered"]').val(qty);
                }
                if (soItemId !== undefined && soItemId !== '') {
                    $('#sales_order_item_id').val(soItemId);
                }
                window.dispatchEvent(new CustomEvent('po-product', { detail: { id: $(this).val(), qty: $('input[name="quantity_ordered"]').val() } }));
            });

            $('input[name="quantity_ordered"]').on('input change', function () {
                window.dispatchEvent(new CustomEvent('po-qty', { detail: $(this).val() }));
            });
        });

        // PO-IMPL-06 (PO-05 .. PO-11) — Alpine workspace. Same math as ProductionOrderEngineeringService::computeMultiLevelPlan().
        function poCreateWorkspace(cfg) {
            const fmt = (n) => {
                const v = Math.round(Number(n || 0) * 10000) / 10000;
                return Number.isInteger(v) ? String(v) : v.toFixed(4);
            };
            return {
                url: cfg.url,
                t: cfg.t,
                loading: false,
                data: null,
                loadError: null,
                productId: cfg.productId || null,
                qty: Number(cfg.qty) || 1,
                oldOps: (cfg.oldOps || []).map(Number),
                oldMats: (cfg.oldMats || []).map(Number),
                selectedOps: {},
                selectedMats: {},
                tab: 'tab1',
                filter: 'all',
                openRows: {},
                highlightOp: null,
                modalStack: [],
                plan: { rows: [], children: {} },
                submitTried: false,
                fmt,
                fmtMin(m) {
                    m = Number(m || 0);
                    if (m > 0 && m * 60 < 1) return '< 1s';
                    return fmt(m) + ' min';
                },

                init() {
                    if (this.productId) this.load(this.productId);
                },
                onProduct(detail) {
                    if (detail.qty !== undefined && detail.qty !== '') this.qty = Number(detail.qty) || 1;
                    this.oldOps = []; this.oldMats = [];
                    this.load(detail.id);
                },
                onQty(v) { this.qty = Number(v) || 0; this.recompute(); },

                async load(pid) {
                    this.productId = pid || null;
                    this.data = null; this.loadError = null; this.openRows = {}; this.modalStack = [];
                    this.selectedOps = {}; this.selectedMats = {};
                    this.plan = { rows: [], children: {} };
                    if (!pid) return;
                    this.loading = true;
                    try {
                        const res = await fetch(this.url + '?' + new URLSearchParams({ product_id: pid, quantity: this.qty || 1 }), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        this.data = await res.json();
                        this.initDefaults();
                        this.recompute();
                    } catch (e) {
                        this.loadError = e.message;
                    } finally {
                        this.loading = false;
                    }
                },

                // PO-06: primary operation of every stage, primary material of every slot (alternative ops too)
                initDefaults() {
                    this.stages.forEach((stage) => {
                        const oldOp = stage.operations.find((o) => this.oldOps.includes(Number(o.id)));
                        this.selectedOps[stage.stage_key] = oldOp ? oldOp.id : stage.default_selected_operation_id;
                        stage.operations.forEach((op) => {
                            op.material_slots.forEach((slot) => {
                                const oldMat = slot.options.find((m) => this.oldMats.includes(Number(m.id)));
                                this.selectedMats[op.id + ':' + slot.slot_key] = oldMat ? oldMat.id : slot.primary_material_id;
                            });
                        });
                    });
                },

                get requiresEngineering() { return !!(this.data && this.data.validation && this.data.validation.requires_engineering); },
                get blocked() { return this.requiresEngineering && !this.data.validation.can_create_order; },
                get stages() { return (this.data && this.data.parent_workspace && this.data.parent_workspace.stages) || []; },
                get bomErrors() { return (this.data && this.data.validation.errors.bom_id) || []; },
                get routingErrors() { return (this.data && this.data.validation.errors.routing_id) || []; },
                get baseQty() { return (this.data && this.data.bom && this.data.bom.base_quantity) || 1; },
                get scale() { return (Number(this.qty) || 0) / this.baseQty; },
                slotRows(op) { return op.material_slots.flatMap((slot) => slot.options.map((m) => ({ slot, m }))); },
                isOpSelected(stage, op) { return Number(this.selectedOps[stage.stage_key]) === Number(op.id); },
                isMatSelected(op, slot, m) { return Number(this.selectedMats[op.id + ':' + slot.slot_key]) === Number(m.id); },
                pickOp(stage, op) { this.selectedOps[stage.stage_key] = op.id; this.recompute(); },
                pickMat(op, slot, m) { this.selectedMats[op.id + ':' + slot.slot_key] = m.id; this.recompute(); },

                get selectedOperationIds() {
                    return this.stages.map((s) => this.selectedOps[s.stage_key]).filter((v) => v);
                },
                get selectedMaterialIds() {
                    const ids = [];
                    this.stages.forEach((stage) => {
                        const op = stage.operations.find((o) => this.isOpSelected(stage, o));
                        if (!op) return;
                        op.material_slots.forEach((slot) => {
                            const v = this.selectedMats[op.id + ':' + slot.slot_key];
                            if (v) ids.push(v);
                        });
                    });
                    return ids;
                },
                // PO-07: one op per stage, one material per slot of every chosen op
                get missing() {
                    const out = [];
                    this.stages.forEach((stage) => {
                        const op = stage.operations.find((o) => this.isOpSelected(stage, o));
                        if (!op) { out.push({ op: null, text: this.t.missing_stage.replace(':stage', stage.operation_number + ' ' + stage.name) }); return; }
                        op.material_slots.forEach((slot) => {
                            const v = this.selectedMats[op.id + ':' + slot.slot_key];
                            if (!slot.options.some((m) => Number(m.id) === Number(v))) {
                                out.push({ op: op.id, text: this.t.missing_material.replace(':op', op.operation_number).replace(':material', slot.options[0].name) });
                            }
                        });
                    });
                    return out;
                },
                isIncomplete(op) { return this.submitTried && this.missing.some((m) => m.op === op.id); },

                // PO-08: level-order explosion, child BOMs only for their shortage, primary ops + mats only, stock pooled once
                recompute() {
                    const d = this.data;
                    if (!d || !d.bom || !this.requiresEngineering) { this.plan = { rows: [], children: {} }; return; }
                    if (this.stages.length === 0 && d.default_preview) {
                        this.plan = { rows: d.default_preview.rows.map((r) => ({ ...r, parent_sources: r.parent_sources || [], child_sources: r.child_sources || [] })), children: {} };
                        return;
                    }
                    const stock = d.branch_available_by_product || {};
                    const avail = (pid) => Number(stock[pid] || 0);
                    const rows = new Map();
                    const ensure = (m) => {
                        if (!rows.has(m.material_id)) {
                            rows.set(m.material_id, { product_id: m.material_id, name: m.name, sku: m.sku, type: m.type, uom: m.uom, child_bom_id: m.child_bom_id || null, child_bom_number: m.child_bom_number || null, parent_qty: 0, child_qty: 0, parent_sources: [], child_sources: [] });
                        }
                        const r = rows.get(m.material_id);
                        if (m.child_bom_id && !r.child_bom_id) { r.child_bom_id = m.child_bom_id; r.child_bom_number = m.child_bom_number; }
                        return r;
                    };
                    const pending = new Map();
                    const register = (cbId, pid, level, q, by) => {
                        if (!pending.has(cbId)) pending.set(cbId, { pid, level, queued: 0, requestedBy: [] });
                        const p = pending.get(cbId);
                        p.queued += q; p.level = Math.max(p.level, level); p.requestedBy.push(by + ': ' + fmt(q));
                    };
                    const scale = this.scale;
                    this.stages.forEach((stage) => {
                        const op = stage.operations.find((o) => this.isOpSelected(stage, o));
                        if (!op) return;
                        op.material_slots.forEach((slot) => {
                            const m = slot.options.find((x) => this.isMatSelected(op, slot, x));
                            if (!m) return;
                            const net = m.quantity * scale, gross = m.gross_quantity * scale;
                            const r = ensure(m);
                            r.parent_qty += gross;
                            r.parent_sources.push({ level: 1, operation_id: op.id, operation: op.operation_number + ' · ' + op.name, selection: (op.is_alternative ? this.t.alt_op : this.t.primary_op) + ' / ' + (m.is_alternative ? 'Alt P' + (m.priority ?? '') : this.t.primary), net_qty: net, scrap_pct: m.material_scrap_percentage, gross_qty: gross });
                            if (m.child_bom_id) register(m.child_bom_id, m.material_id, 2, gross, d.bom.bom_number);
                        });
                    });
                    const remaining = {}, children = {};
                    for (let level = 2; level <= 21; level++) {
                        const batch = [...pending.entries()].filter(([, p]) => p.level <= level && p.queued > 0);
                        if (batch.length === 0) { if ([...pending.values()].every((p) => p.queued <= 0)) break; continue; }
                        batch.forEach(([cbId, p]) => {
                            const q = p.queued; p.queued = 0;
                            if (remaining[p.pid] === undefined) remaining[p.pid] = avail(p.pid);
                            const fromStock = Math.min(q, Math.max(0, remaining[p.pid]));
                            remaining[p.pid] -= fromStock;
                            const toProduce = Math.max(0, q - fromStock);
                            const child = (d.child_boms || {})[cbId];
                            if (!children[cbId]) children[cbId] = { id: cbId, level, required: 0, fromStock: 0, toProduce: 0, avail: avail(p.pid), requestedBy: [], bom: child };
                            const c = children[cbId];
                            c.required += q; c.fromStock += fromStock; c.toProduce += toProduce; c.requestedBy = p.requestedBy;
                            if (toProduce <= 0 || !child) return;
                            const cScale = toProduce / (child.base_quantity || 1);
                            child.operations.forEach((cOp) => {
                                cOp.materials.forEach((m) => {
                                    const net = m.quantity * cScale, gross = m.gross_quantity * cScale;
                                    const r = ensure(m);
                                    r.child_qty += gross;
                                    r.child_sources.push({ level, bom_id: child.id, bom_number: child.bom_number, producing_qty: toProduce, operation: cOp.operation_number + ' · ' + cOp.name, selection: this.t.primary_auto, net_qty: net, scrap_pct: m.material_scrap_percentage, gross_qty: gross });
                                    if (m.child_bom_id) register(m.child_bom_id, m.material_id, level + 1, gross, child.bom_number);
                                });
                            });
                        });
                    }
                    const list = [...rows.values()].map((r) => {
                        const total = r.parent_qty + r.child_qty, a = avail(r.product_id);
                        const c = r.child_bom_id ? children[r.child_bom_id] : null;
                        return { ...r, total_req: total, branch_avail: a, from_stock: Math.min(total, a), shortage: Math.max(0, total - a), to_produce: c ? c.toProduce : 0 };
                    });
                    this.plan = { rows: list, children };
                },
                get visibleRows() {
                    return this.filter === 'short' ? this.plan.rows.filter((r) => r.shortage > 0 || r.to_produce > 0) : this.plan.rows;
                },
                get shortageCount() { return this.plan.rows.filter((r) => r.shortage > 0 && !r.child_bom_id).length; },
                get produceCount() { return this.plan.rows.filter((r) => r.to_produce > 0).length; },
                rowBadge(r) {
                    if (r.parent_qty > 0 && r.child_qty > 0) return this.t.parent_and_child;
                    if (r.parent_qty <= 0 && r.child_qty > 0) return this.t.via_child_only;
                    return null;
                },
                toggleRow(r) { this.openRows[r.product_id] = !this.openRows[r.product_id]; },
                jumpToOp(opId) {
                    this.tab = 'tab1';
                    this.highlightOp = opId;
                    this.$nextTick(() => {
                        const el = document.getElementById('po-op-card-' + opId);
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => { this.highlightOp = null; }, 2000);
                    });
                },

                // PO-11: in-page child BOM modal, primary ops + mats only, breadcrumb drill-down
                openChild(cbId, push = false) {
                    if (!cbId || !(this.data.child_boms || {})[cbId]) return;
                    if (push) this.modalStack.push(cbId); else this.modalStack = [cbId];
                },
                closeModal() { this.modalStack = []; },
                modalBack() { this.modalStack.pop(); },
                get modalBom() { const id = this.modalStack[this.modalStack.length - 1]; return id ? this.data.child_boms[id] : null; },
                get modalInfo() { const id = this.modalStack[this.modalStack.length - 1]; return id ? (this.plan.children[id] || null) : null; },
                get modalProducing() { return this.modalInfo ? this.modalInfo.toProduce : 0; },
                get modalScale() { const b = this.modalBom; if (!b) return 0; return this.modalProducing > 0 ? this.modalProducing / (b.base_quantity || 1) : 1 / (b.base_quantity || 1); },
                inParent(materialId) { const r = this.plan.rows.find((x) => Number(x.product_id) === Number(materialId)); return !!(r && r.parent_qty > 0); },

                onSubmit(e) {
                    this.submitTried = true;
                    if (!this.requiresEngineering) return;
                    if (this.blocked || this.missing.length > 0) {
                        e.preventDefault();
                        this.tab = 'tab1';
                        const first = this.missing.find((m) => m.op);
                        if (first) this.jumpToOp(first.op);
                    }
                },
            };
        }
    </script>
@endpush

@section('content')
    @php
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
    <div class="erp-single-panel">
        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        <form method="POST" action="{{ route('production.orders.store') }}"
              x-data="poCreateWorkspace({ url: @js(route('production.orders.engineering-status')), productId: @js($selectedProductId ?? null), qty: @js(old('quantity_ordered', $selectedQty ?? 1)), oldOps: @js(array_values((array) old('selected_operation_ids', []))), oldMats: @js(array_values((array) old('selected_material_ids', []))), t: @js($poT) })"
              @po-product.window="onProduct($event.detail)"
              @po-qty.window="onQty($event.detail)"
              @submit="onSubmit($event)">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">{{ __('production.new_production_order') }}</h4>
                        <small class="text-muted fs-12">{{ __('production.bom_routing_frozen_notice') }}</small>
                    </div>
                    <a href="{{ route('production.orders.index') }}" class="btn btn-sm btn-light border">{{ __('production.cancel') }}</a>
                </div>

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

                        <x-ui.odoo-form-ui type="select" label="{{ __('production.production_mode') }}" name="production_mode" id="production_mode_select">
                            <option value="standard" @selected(old('production_mode', 'standard') === 'standard')>{{ __('production.mode_standard') }}</option>
                            <option value="batch" @selected(old('production_mode') === 'batch')>{{ __('production.mode_batch') }}</option>
                            <option value="serial" @selected(old('production_mode') === 'serial')>{{ __('production.mode_serial') }}</option>
                            <option value="batch_and_serial" @selected(old('production_mode') === 'batch_and_serial')>{{ __('production.mode_batch_and_serial') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.qty_to_manufacture')" name="quantity_ordered" inputType="number" placeholder="e.g. 10" :value="old('quantity_ordered', $selectedQty ?? '1')" :required="true" step="0.0001" />

                        <x-ui.odoo-form-ui type="input" :label="__('production.start_date')" name="start_date" inputType="date" :value="old('start_date', date('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="input" :label="__('production.end_date')" name="end_date" inputType="date" :value="old('end_date', date('Y-m-d', strtotime('+3 days')))" :required="true" />

                        <x-ui.odoo-form-ui type="textarea" :label="__('production.remarks')" name="description" :placeholder="__('production.remarks_placeholder')" rows="4">{{ old('description') }}</x-ui.odoo-form-ui>
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
                        <i class="feather-check-circle me-1"></i>{{ __('production.create_production_order') }}
                    </button>
                    <x-ui.button :href="route('production.orders.index')" variant="secondary" class="px-4">{{ __('production.cancel') }}</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
