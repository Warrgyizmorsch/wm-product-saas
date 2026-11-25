    <script>
        $(document).ready(function () {
            $('#production_order_request_select').on('change', function () {
                var selectedOption = $(this).find('option:selected');
                var productId = selectedOption.data('product-id');
                var qty = selectedOption.data('qty');
                var salesOrderId = selectedOption.data('sales-order-id');
                var salesOrderItemId = selectedOption.data('sales-order-item-id');

                // Edit screen only: taking the sales request OFF the order also takes its sales-order links off
                if (window.poOrderFormMode === 'edit' && !$(this).val()) {
                    $('#sales_order_id').val('');
                    $('#sales_order_item_id').val('');
                }

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
                orderId: cfg.orderId || null, // Edit screen: the order being edited (lets the server authorise by "update" instead of "create")
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
                        const params = { product_id: pid, quantity: this.qty || 1 };
                        if (this.orderId) params.order_id = this.orderId;
                        const res = await fetch(this.url + '?' + new URLSearchParams(params), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
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
