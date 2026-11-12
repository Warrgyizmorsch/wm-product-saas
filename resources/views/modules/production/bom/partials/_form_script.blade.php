<script>
    const productsData = @json($products);
    const routingsData = @json($routings->keyBy('id'));
    const routingAdvisoriesMap = @json($routingAdvisoriesMap ?? []);

    document.addEventListener('alpine:init', () => {
        Alpine.data('bomForm', () => ({
            items: @json($oldItems),
            errors: @json($errors->toArray()),
            materials: @json($materials),
            uoms: @json($uoms),
            routings: routingsData,
            routingAdvisories: routingAdvisoriesMap,
            selectedRoutingId: '{{ old('routing_id', $defaultRoutingId ?? '') }}',
            previousRoutingId: '{{ old('routing_id', $defaultRoutingId ?? '') }}',
            availableOperations: [],
            expandedOps: {},
            expandedSummaryRows: {},
            activeTab: 'components',
            showAdvanced: true,

            // BM-IMPL-12 #1: one draft per tenant + user + screen (create / edit {id} / copy {source})
            draftStorageKey: @json('bom_draft_' . (tenant_id() ?? 0) . '_' . (auth()->id() ?? 0) . '_' . ($isEdit ? ('edit_' . $bom->id) : (!empty($copyFromBom) ? ('copy_' . $copyFromBom->id) : 'create'))),
            bomUpdatedAt: @json($isEdit ? optional($bom->updated_at)->toIso8601String() : null),
            draftDiscardedStale: false,
            isCopyMode: @json((bool) ($isCopyMode ?? false)),
            isDraftRestored: false,
            draftRestoredTime: '',
            _isRestoringDraft: false,
            _draftRestoredProduct: null,
            _draftRestoredRouting: null,
            _hasServerOldInput: @json((bool) ($hasServerOldInput ?? false)),
            _draftTimeout: null,

            get activeOperations() {
                return (this.availableOperations || []).filter(op => !op.is_deleted);
            },

            get visibleOperations() {
                return (this.availableOperations || []).filter(op => {
                    if (!op.is_deleted) return true;
                    return this.getItemsForOp(op.id).length > 0;
                });
            },

            get currentRoutingAdvisories() {
                if (!this.selectedRoutingId) return [];
                const base = Array.isArray(this.routingAdvisories[this.selectedRoutingId])
                    ? this.routingAdvisories[this.selectedRoutingId].slice()
                    : [];

                // Live operation_removed check based on current client items
                const deletedOpIds = {};
                (this.availableOperations || []).forEach(op => {
                    if (op.is_deleted) deletedOpIds[String(op.id)] = op;
                });
                const seenDeleted = {};
                (this.items || []).forEach(it => {
                    const op = deletedOpIds[String(it.routing_operation_id || '')];
                    if (op && !seenDeleted[op.id]) {
                        seenDeleted[op.id] = true;
                        base.push({
                            code: 'operation_removed',
                            message: 'Operation ' + (op.operation_number || ('#' + op.id)) + ' (' + op.name + ') was removed from the routing. Move or remove its materials before submitting.',
                            blocks_approval: true
                        });
                    }
                });

                return base;
            },

            // 4 decimals only when there are decimal digits (display rule)
            fmt4(v) {
                const n = Math.round((parseFloat(v) || 0) * 10000) / 10000;
                return Number.isInteger(n) ? String(n) : n.toFixed(4);
            },

            // BM-IMPL-17 #1: effective scrap % = (gross − net) ÷ net × 100; net 0 → "—"
            effectiveScrap(net, gross) {
                const n = parseFloat(net) || 0;
                if (n <= 0) return '—';
                return this.fmt4(((parseFloat(gross) || 0) - n) / n * 100) + '%';
            },

            // BM-IMPL-08 #1: one sub-BOM per material, written to every allocation of the material
            setSubBomForMaterial(materialId, bomId) {
                const value = bomId ? String(bomId) : null;
                this.items.forEach(it => {
                    if (String(it.material_id) === String(materialId)) it.child_bom_id = value;
                });
                this.debouncedSaveDraft && this.debouncedSaveDraft();
            },

            subBomLabel(item) {
                const v = (item.child_bom_versions || []).find(x => String(x.id) === String(item.child_bom_id));
                return v ? ((v.bom_number || '') + ' v' + v.version + ' (' + v.status + ')') : ('#' + item.child_bom_id);
            },

            subBomWarning(item) {
                const v = (item.child_bom_versions || []).find(x => String(x.id) === String(item.child_bom_id));
                if (v && v.status === 'scheduled') {
                    return @js(__('production.bom_sub_bom_scheduled_warning')).replace(':date', v.effective_date || '');
                }
                return '';
            },

            formatDuration(minutes) {
                const mins = parseFloat(minutes) || 0;
                if (mins <= 0) return '0m';
                if (mins < 1) return Math.round(mins * 60) + 's';
                if (mins < 60) return Math.round(mins) + ' min';
                const h = Math.floor(mins / 60);
                const m = Math.round(mins % 60);
                return m > 0 ? (h + ' hr ' + m + ' min') : (h + ' hr');
            },

            calcGrossQty(item) {
                const qty = parseFloat(item?.quantity) || 0;
                const scrap = parseFloat(item?.material_scrap_percentage) || 0;
                return qty * (1 + (scrap / 100));
            },

            formatGrossQty(item) {
                const gross = this.calcGrossQty(item);
                if (!gross) return '0.00';
                const fixed4 = gross.toFixed(4);
                return parseFloat(fixed4).toFixed(fixed4.endsWith('00') ? 2 : 4).replace(/(\.\d{2,}?)0+$/, '$1');
            },

            getUomCode(uomId) {
                if (!uomId || !this.uoms) return '';
                const u = this.uoms.find(x => String(x.id) === String(uomId));
                return u ? u.code : '';
            },

            getMaterialType(materialId) {
                if (!materialId || !this.materials) return '';
                const m = this.materials.find(x => String(x.id) === String(materialId));
                return m ? (m.type || '') : '';
            },

            isOpExpanded(opId) {
                if (this.expandedOps[opId] === undefined) return true;
                return !!this.expandedOps[opId];
            },

            toggleOp(opId) {
                this.expandedOps[opId] = !this.isOpExpanded(opId);
                if (this.expandedOps[opId]) {
                    this.$nextTick(() => {
                        var self = this;
                        $('.operation-card[data-op-id="' + opId + '"] .bom-component-row').each(function () {
                            var rowUid = $(this).attr('data-row-uid');
                            var item = self.items.find(it => it.uid === rowUid);
                            if (item && item.is_alternative) {
                                self.initAltSelect(this, item);
                            }
                        });
                    });
                }
            },

            expandAllOps() {
                this.availableOperations.forEach(op => {
                    this.expandedOps[op.id] = true;
                });
                this.$nextTick(() => this.initAllRowSelects());
            },

            collapseAllOps() {
                this.availableOperations.forEach(op => {
                    this.expandedOps[op.id] = false;
                });
            },

            getItemsForOp(opId) {
                return this.items.filter(it => String(it.routing_operation_id) === String(opId));
            },

            getSortedItemsForOp(opId) {
                const opItems = this.getItemsForOp(opId);
                const primaries = opItems.filter(it => !it.is_alternative);
                const alts = opItems.filter(it => !!it.is_alternative);

                const result = [];
                const placed = {};
                primaries.forEach(p => {
                    result.push(p);
                    const matching = alts
                        .filter(a => String(a.replaces_row_uid || '') === String(p.uid))
                        .sort((a, b) => (parseInt(a.priority) || 1) - (parseInt(b.priority) || 1));
                    matching.forEach(a => {
                        result.push(a);
                        placed[a.uid] = true;
                    });
                });

                alts.forEach(a => {
                    if (!placed[a.uid]) result.push(a);
                });

                return result;
            },

            getItemIndex(item) {
                const idx = this.items.indexOf(item);
                return idx >= 0 ? idx : 0;
            },

            getOpSequenceById(opId) {
                if (!opId) return '';
                const found = this.availableOperations.find(o => String(o.id) === String(opId));
                return found ? found.sequence : '';
            },

            toggleSummaryRow(matId) {
                this.expandedSummaryRows[matId] = !this.expandedSummaryRows[matId];
                this.expandedSummaryRows = Object.assign({}, this.expandedSummaryRows);
            },

            isSummaryRowExpanded(matId) {
                return !!this.expandedSummaryRows[matId];
            },

            get summarizedMaterials() {
                const map = {};
                this.items.forEach(item => {
                    const matId = String(item.material_id || '');
                    if (!matId) return;

                    if (!map[matId]) {
                        const matObj = this.materials.find(m => String(m.id) === matId);
                        const uomObj = this.uoms ? this.uoms.find(u => String(u.id) === String(item.uom_id)) : null;
                        map[matId] = {
                            material_id: matId,
                            name: matObj ? matObj.name : 'Component #' + matId,
                            sku: matObj ? matObj.sku : '—',
                            type: matObj ? matObj.type : (item.material_type || 'raw_material'),
                            uom_name: uomObj ? uomObj.name : 'Units',
                            primary_qty: 0,
                            primary_gross_qty: 0,
                            alternative_qty: 0,
                            has_primary: false,
                            has_alternative: false,
                            child_bom_id: null,
                            child_bom_versions: [],
                            child_bom_loading: false,
                            allocations: []
                        };
                    }

                    if (!map[matId].child_bom_id && item.child_bom_id) map[matId].child_bom_id = item.child_bom_id;
                    if ((!map[matId].child_bom_versions || map[matId].child_bom_versions.length === 0) && item.child_bom_versions && item.child_bom_versions.length) map[matId].child_bom_versions = item.child_bom_versions;
                    if (item.child_bom_loading) map[matId].child_bom_loading = true;

                    const qty = parseFloat(item.quantity) || 0;
                    const grossQty = this.calcGrossQty(item);
                    const op = this.availableOperations.find(o => String(o.id) === String(item.routing_operation_id));
                    const isOpAlt = !!(op && (op.is_alternative === true || op.is_alternative === 1 || String(op.is_alternative) === '1'));
                    const isAlt = !!item.is_alternative;
                    const isEffectiveAlt = isAlt || isOpAlt;

                    if (isEffectiveAlt) {
                        map[matId].alternative_qty += qty;
                        map[matId].has_alternative = true;
                    } else {
                        map[matId].primary_qty += qty;
                        map[matId].primary_gross_qty += grossQty;
                        map[matId].has_primary = true;
                    }

                    let replacedName = '';
                    if (isAlt && item.replaces_row_uid) {
                        const targetItem = this.items.find(it => it.uid === item.replaces_row_uid);
                        if (targetItem) {
                            const targetMat = this.materials.find(m => String(m.id) === String(targetItem.material_id));
                            replacedName = targetMat ? targetMat.name : 'Primary Component';
                        }
                    }

                    map[matId].allocations.push({
                        uid: item.uid,
                        routing_operation_id: item.routing_operation_id,
                        op_sequence: op ? op.sequence : '—',
                        op_name: op ? op.name : 'Operation #' + item.routing_operation_id,
                        quantity: qty,
                        gross_quantity: grossQty,
                        is_alternative: isAlt,
                        priority: item.priority || '',
                        replaces_name: replacedName,
                        scrap_percentage: parseFloat(item.material_scrap_percentage) || 0,
                        notes: item.notes || ''
                    });
                });

                return Object.values(map);
            },

            jumpToOpCard(opId, itemUid) {
                if (!opId) return;
                this.activeTab = 'components';
                this.expandedOps = Object.assign({}, this.expandedOps, { [opId]: true });

                const compTabBtn = document.querySelector('#bomFormTabs button[data-bs-target="#tab-components"], #bomFormTabs a[href="#tab-components"], #tab-components-tab');
                if (compTabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    bootstrap.Tab.getOrCreateInstance(compTabBtn).show();
                }

                const executeScroll = () => {
                    const rowEl = itemUid ? document.querySelector('.bom-component-row[data-row-uid="' + itemUid + '"]') : null;
                    if (rowEl && rowEl.offsetParent !== null) {
                        rowEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        rowEl.classList.add('table-warning');
                        setTimeout(() => rowEl.classList.remove('table-warning'), 2500);
                        return true;
                    }
                    const opCard = document.querySelector('.operation-card[data-op-id="' + opId + '"]');
                    if (opCard && opCard.offsetParent !== null) {
                        opCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        return true;
                    }
                    return false;
                };

                this.$nextTick(() => executeScroll());
                setTimeout(() => executeScroll(), 200);
            },

            scrollToFirstError() {
                Object.keys(this.errors || {}).forEach(k => {
                    const match = k.match(/^items\.(\d+)\./);
                    if (match) {
                        const it = this.items[parseInt(match[1], 10)];
                        if (it && it.routing_operation_id) {
                            this.expandedOps[it.routing_operation_id] = true;
                        }
                    }
                });
                this.$nextTick(() => {
                    const errEl = document.querySelector('.is-invalid, .invalid-feedback.d-block');
                    if (errEl) {
                        errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            },

            moveItemToOp(item, targetOpId) {
                if (!item || !targetOpId) return;
                const prevOpId = item.routing_operation_id;
                item.routing_operation_id = String(targetOpId);

                if (item.is_alternative && item.replaces_row_uid) {
                    const target = this.items.find(it => it.uid === item.replaces_row_uid);
                    if (!target || String(target.routing_operation_id) !== String(targetOpId)) {
                        item.replaces_row_uid = '';
                    }
                }
                if (!item.is_alternative) {
                    // Detach any alternatives on old op that used to target this row
                    this.items.forEach(other => {
                        if (other.is_alternative && other.replaces_row_uid === item.uid && String(other.routing_operation_id) !== String(targetOpId)) {
                            other.replaces_row_uid = '';
                        }
                    });
                }

                this.expandedOps[targetOpId] = true;
                this.$nextTick(() => {
                    this.initAllRowSelects();
                    if (prevOpId) this.refreshAltSelectsForOp(prevOpId);
                    this.refreshAltSelectsForOp(targetOpId);
                    this.validateDuplicateMaterials();
                });
            },

            nextPriorityForTarget(opId, targetUid, excludeUid) {
                const sameGroup = this.getItemsForOp(opId).filter(it =>
                    it.is_alternative &&
                    it.uid !== excludeUid &&
                    String(it.replaces_row_uid || '') === String(targetUid || '')
                );
                let maxP = 0;
                sameGroup.forEach(it => {
                    const p = parseInt(it.priority, 10) || 0;
                    if (p > maxP) maxP = p;
                });
                return maxP + 1;
            },

            getSavedDraft() {
                try {
                    const raw = sessionStorage.getItem(this.draftStorageKey) || localStorage.getItem(this.draftStorageKey);
                    if (!raw) return null;
                    const data = JSON.parse(raw);
                    if (data && data.timestamp && Date.now() - data.timestamp > 7 * 24 * 60 * 60 * 1000) {
                        this.clearDraftStorage();
                        return null;
                    }
                    // BM-IMPL-12 #2: the BOM changed after the draft was saved → discard the draft
                    if (this.bomUpdatedAt && data && data.bom_updated_at !== this.bomUpdatedAt) {
                        this.clearDraftStorage();
                        this.draftDiscardedStale = true;
                        return null;
                    }
                    return data;
                } catch (e) {
                    return null;
                }
            },

            clearDraftStorage() {
                try {
                    sessionStorage.removeItem(this.draftStorageKey);
                    localStorage.removeItem(this.draftStorageKey);
                } catch (e) {}
            },

            discardDraft() {
                this.clearDraftStorage();
                this.isDraftRestored = false;
                window.location.reload();
            },

            saveDraft() {
                if (this._isRestoringDraft) return;
                try {
                    const prodId = $('#product_id').val() || '';
                    const routId = $('#routing_id').val() || this.selectedRoutingId || '';
                    const bomName = $('input[name="bom_name"]').val() || '';

                    const itemsToSave = (this.items || []).map(it => ({
                        uid: it.uid,
                        material_id: it.material_id || '',
                        routing_operation_id: it.routing_operation_id ? String(it.routing_operation_id) : '',
                        child_bom_id: it.child_bom_id || null,
                        quantity: it.quantity || '',
                        notes: it.notes || '',
                        uom_id: it.uom_id || '',
                        material_scrap_percentage: it.material_scrap_percentage || 0,
                        is_alternative: !!it.is_alternative,
                        replaces_row_uid: it.replaces_row_uid || '',
                        priority: it.is_alternative ? (it.priority || '') : ''
                    }));

                    if (!prodId && !routId && !bomName && !itemsToSave.some(it => it.material_id)) {
                        return;
                    }

                    const serialized = JSON.stringify({
                        product_id: prodId,
                        routing_id: routId,
                        bom_name: bomName,
                        bom_type: $('select[name="bom_type"]').val() || 'manufacturing',
                        usage_context: $('select[name="usage_context"]').val() || 'manufacturing',
                        base_quantity: $('input[name="base_quantity"]').val() || '1.00',
                        notes: $('textarea[name="notes"]').val() || '',
                        version: $('input[name="version"]').val() || '1.0.0',
                        effective_date: $('input[name="effective_date"]').val() || '',
                        expiry_date: $('input[name="expiry_date"]').val() || '',
                        revision_reason: $('input[name="revision_reason"]').val() || '',
                        items: itemsToSave,
                        activeTab: this.activeTab,
                        bom_updated_at: this.bomUpdatedAt,
                        timestamp: Date.now()
                    });
                    sessionStorage.setItem(this.draftStorageKey, serialized);
                    localStorage.setItem(this.draftStorageKey, serialized);
                } catch (e) {}
            },

            debouncedSaveDraft() {
                clearTimeout(this._draftTimeout);
                this._draftTimeout = setTimeout(() => this.saveDraft(), 350);
            },

            restoreFromDraft(draft) {
                if (!draft) return;
                this._isRestoringDraft = true;

                if (draft.bom_name) $('input[name="bom_name"]').val(draft.bom_name);
                if (draft.bom_type) $('select[name="bom_type"]').val(draft.bom_type);
                if (draft.usage_context) $('select[name="usage_context"]').val(draft.usage_context);
                if (draft.base_quantity) $('input[name="base_quantity"]').val(draft.base_quantity);
                if (draft.notes) $('textarea[name="notes"]').val(draft.notes);
                if (draft.version) $('input[name="version"]').val(draft.version);
                if (draft.effective_date) $('input[name="effective_date"]').val(draft.effective_date);
                if (draft.expiry_date) $('input[name="expiry_date"]').val(draft.expiry_date);
                if (draft.revision_reason) $('input[name="revision_reason"]').val(draft.revision_reason);

                if (draft.product_id) {
                    this._draftRestoredProduct = String(draft.product_id);
                    $('#product_id').val(draft.product_id).trigger('change');
                }

                if (draft.routing_id) {
                    this._draftRestoredRouting = String(draft.routing_id);
                    this.selectedRoutingId = String(draft.routing_id);
                    this.previousRoutingId = String(draft.routing_id);
                    $('#routing_id').val(draft.routing_id).trigger('change');
                    this.updateAvailableOperations(draft.routing_id, false);
                    this.expandAllOps();
                }

                if (Array.isArray(draft.items) && draft.items.length > 0) {
                    this.items = draft.items.map((item, idx) => this.normalizeItem(item, idx));
                }

                this.isDraftRestored = true;
                this.draftRestoredTime = draft.timestamp ? new Date(draft.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

                this.$nextTick(() => {
                    this.initAllRowSelects();
                    this._isRestoringDraft = false;
                });
            },

            normalizeItem(item, idx) {
                var initialUomId = item.uom_id ? String(item.uom_id) : '';
                if (!initialUomId && item.material_id) {
                    var foundMat = this.materials.find(m => String(m.id) === String(item.material_id));
                    if (foundMat && foundMat.uom_id) initialUomId = String(foundMat.uom_id);
                }
                var initialUid = item.uid || item.row_uid || (item.id ? ('existing_' + item.id) : ('row_' + Date.now() + '_' + idx));
                var replacesUid = item.replaces_row_uid || '';
                if (!replacesUid && item.replaces_item_id) replacesUid = 'existing_' + item.replaces_item_id;

                return {
                    uid: initialUid,
                    id: item.id || null,
                    material_id: item.material_id ? String(item.material_id) : '',
                    routing_operation_id: item.routing_operation_id ? String(item.routing_operation_id) : '',
                    child_bom_id: item.child_bom_id || null,
                    quantity: item.quantity ?? '',
                    notes: item.notes || '',
                    uom_id: initialUomId,
                    material_scrap_percentage: item.material_scrap_percentage ?? 0,
                    is_alternative: !!parseInt(item.is_alternative) || item.is_alternative === true,
                    replaces_row_uid: replacesUid,
                    priority: item.priority || "",
                    child_bom_status: 'none',
                    child_bom_versions: [],
                    child_bom_loading: false
                };
            },

            syncDraftSelect2() {
                if (this._draftRestoredProduct) {
                    $('#product_id').val(this._draftRestoredProduct).trigger('change.select2');
                }
                if (this._draftRestoredRouting) {
                    $('#routing_id').val(this._draftRestoredRouting).trigger('change.select2');
                }
            },

            updateAvailableOperations(routingId, clearItems = true) {
                this.selectedRoutingId = String(routingId || '');
                const routing = this.routings[routingId] || null;
                const ops = routing && Array.isArray(routing.operations) ? routing.operations.slice() : [];
                ops.sort((a, b) => {
                    if (!!a.is_deleted !== !!b.is_deleted) return a.is_deleted ? 1 : -1;
                    return (a.sequence || 0) - (b.sequence || 0);
                });
                this.availableOperations = ops;

                if (clearItems) {
                    this.items = [];
                }
                this.availableOperations.forEach(op => {
                    if (this.expandedOps[op.id] === undefined) {
                        this.expandedOps[op.id] = true;
                    }
                });
            },

            getFirstOperationId() {
                const firstActive = this.activeOperations[0] || this.availableOperations[0] || null;
                return firstActive ? String(firstActive.id) : '';
            },

            fetchChildBomStatus(item) {
                if (!item || !item.material_id) {
                    if (item) {
                        item.child_bom_status = 'none';
                        item.child_bom_versions = [];
                        item.child_bom_id = null;
                    }
                    return;
                }
                item.child_bom_loading = true;
                const url = '{{ route("production.boms.check-child", "__PRODUCT_ID__") }}'.replace('__PRODUCT_ID__', item.material_id);
                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(data => {
                        item.child_bom_status = data.status || 'none';
                        item.child_bom_versions = data.versions || [];
                        if (!item.child_bom_id) {
                            const sibling = this.items.find(it => it !== item && String(it.material_id) === String(item.material_id) && it.child_bom_id);
                            item.child_bom_id = sibling ? sibling.child_bom_id : (data.bom_id ? String(data.bom_id) : null);
                        }
                        this.setSubBomForMaterial(item.material_id, item.child_bom_id);
                        item.child_bom_loading = false;
                        this.$nextTick(() => this.initAllRowSelects());
                    })
                    .catch(() => {
                        item.child_bom_status = 'none';
                        item.child_bom_versions = [];
                        item.child_bom_loading = false;
                    });
            },

            refreshChildBomStatusForProduct(productId) {
                this.items.forEach(item => {
                    if (String(item.material_id) === String(productId)) {
                        this.fetchChildBomStatus(item);
                    }
                });
            },

            init() {
                window.bomAlpineInstance = this;

                const syncHeaderUom = (productId) => {
                    var prodUomId = '';
                    if (productId) {
                        var selectedOption = $('#product_id').find('option[value="' + productId + '"]');
                        prodUomId = selectedOption.attr('data-uom-id') || '';
                        if (!prodUomId) {
                            var foundProd = productsData.find(p => String(p.id) === String(productId));
                            if (foundProd && foundProd.uom_id) prodUomId = foundProd.uom_id;
                        }
                    }
                    $('#base_uom_id').val(prodUomId);
                    var $baseUomDisplay = $('#base_uom_id_display');
                    $baseUomDisplay.val(prodUomId).trigger($baseUomDisplay.data('select2') ? 'change.select2' : 'change');
                };

                const savedDraft = this.getSavedDraft();
                // BM-IMPL-12 #3: copy flow — the pre-fill wins on first open; a reload restores the copy draft
                const navEntry = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || null;
                const isReload = !!(navEntry && navEntry.type === 'reload');
                const shouldRestore = savedDraft && !this._hasServerOldInput && (!this.isCopyMode || isReload);

                if (shouldRestore) {
                    this.restoreFromDraft(savedDraft);
                } else {
                    this.items = (this.items || []).map((item, idx) => this.normalizeItem(item, idx));
                    const initialRoutingId = $('#routing_id').val() || this.selectedRoutingId;
                    this.selectedRoutingId = String(initialRoutingId || '');
                    this.previousRoutingId = this.selectedRoutingId;
                    if (this.selectedRoutingId) {
                        this.updateAvailableOperations(this.selectedRoutingId, false);
                        const firstOp = this.getFirstOperationId();
                        if (firstOp) {
                            this.items.forEach(item => {
                                if (!item.routing_operation_id) item.routing_operation_id = firstOp;
                            });
                        }
                        this.expandAllOps();
                    }
                    this.$nextTick(() => {
                        this.items.forEach(item => {
                            if (item.material_id) this.fetchChildBomStatus(item);
                        });
                        this.initAllRowSelects();
                        if (Object.keys(this.errors || {}).length > 0) {
                            this.scrollToFirstError();
                        }
                    });
                }

                var self = this;
                var isReverting = false;

                $('#routing_id').on('change.select2 change', function (e) {
                    if (isReverting) return;
                    var newRoutingId = $(this).val() || '';
                    if (String(newRoutingId) === String(self.previousRoutingId)) return;

                    var applyRoutingChange = function () {
                        self.updateAvailableOperations(newRoutingId, true);
                        self.previousRoutingId = String(newRoutingId);
                        self.expandAllOps();
                        if (!self._isRestoringDraft) self.debouncedSaveDraft();
                    };

                    var revertRouting = function () {
                        isReverting = true;
                        $('#routing_id').val(self.previousRoutingId).trigger('change.select2');
                        self.selectedRoutingId = String(self.previousRoutingId);
                        isReverting = false;
                    };

                    if (self.items.some(it => it.material_id)) {
                        var msg = 'Changing the routing will clear your currently configured material rows. Continue?';
                        if (typeof window.confirmAction === 'function') {
                            var handled = false;
                            var p = window.confirmAction({
                                title: 'Change Process Routing?',
                                message: msg,
                                variant: 'warning',
                                confirmText: 'Yes, Change Routing',
                                cancelButtonText: 'Cancel',
                                onConfirm: () => { if (!handled) { handled = true; applyRoutingChange(); } },
                                onCancel: () => { if (!handled) { handled = true; revertRouting(); } }
                            }, () => { if (!handled) { handled = true; applyRoutingChange(); } });
                            if (p && typeof p.then === 'function') {
                                p.then(res => {
                                    if (handled) return;
                                    handled = true;
                                    if (res && res.isConfirmed) applyRoutingChange();
                                    else revertRouting();
                                }).catch(() => { if (!handled) { handled = true; revertRouting(); } });
                            }
                        } else if (confirm(msg)) {
                            applyRoutingChange();
                        } else {
                            revertRouting();
                        }
                    } else {
                        applyRoutingChange();
                    }
                });

                syncHeaderUom($('#product_id').val());

                $('#product_id').on('change.select2 change', (e) => {
                    var parentProductId = e.target.value;
                    syncHeaderUom(parentProductId);
                    $('.odoo-table-select[name*="[material_id]"]').each(function () {
                        var $s = $(this);
                        $s.find('option').each(function () {
                            $(this).prop('disabled', $(this).val() === parentProductId && parentProductId !== '');
                        });
                        if ($s.data('select2')) $s.trigger('change.select2');
                    });
                    this.validateDuplicateMaterials();
                    if (!this._isRestoringDraft) this.debouncedSaveDraft();
                });

                this.$watch('items', () => {
                    if (!this._isRestoringDraft) this.debouncedSaveDraft();
                });

                $('#bom-form').on('input change', () => {
                    if (!this._isRestoringDraft) this.debouncedSaveDraft();
                });

                $('#bom-form').on('submit', () => this.clearDraftStorage());

                this.$nextTick(() => {
                    this.initAllRowSelects();
                    this.validateDuplicateMaterials();
                });
            },

            getEligiblePrimaryComponents(currentIndex) {
                const currentItem = this.items[currentIndex];
                if (!currentItem) return [];
                const opItems = this.getItemsForOp(currentItem.routing_operation_id);
                return opItems
                    .map((it, subIdx) => {
                        const mat = this.materials.find(m => String(m.id) === String(it.material_id));
                        return {
                            uid: it.uid,
                            seq: subIdx + 1,
                            material_id: it.material_id,
                            routing_operation_id: it.routing_operation_id,
                            name: mat ? mat.name : (it.material_id ? ('Component #' + it.material_id) : ('Item #' + (subIdx + 1))),
                            sku: mat ? mat.sku : '—',
                            is_alternative: it.is_alternative,
                        };
                    })
                    .filter(it => it.uid !== currentItem.uid && !it.is_alternative && !!it.material_id);
            },

            initAltSelect(rowEl, item) {
                var self = this;
                if (!rowEl) return;
                var $altSelect = $(rowEl).find('select.bom-alt-select');
                if (!$altSelect.length) return;

                var eligible = self.getEligiblePrimaryComponents(self.getItemIndex(item));
                var currentVal = item.replaces_row_uid || '';

                if ($altSelect.data('select2')) {
                    $altSelect.select2('destroy');
                }

                $altSelect.empty();
                $altSelect.append(new Option('-- {{ __('production.select_primary_component') }} --', ''));
                eligible.forEach(function (target) {
                    var label = '[' + (target.sku || 'No SKU') + '] ' + target.name + ' (#' + target.seq + ')';
                    var isSelected = String(target.uid) === String(currentVal);
                    $altSelect.append(new Option(label, target.uid, isSelected, isSelected));
                });

                if (currentVal && !eligible.some(t => String(t.uid) === String(currentVal))) {
                    item.replaces_row_uid = '';
                    currentVal = '';
                }

                $altSelect.val(currentVal);
                $altSelect.select2(self.select2RowOptions());

                $altSelect.off('change.bom-alt-select').on('change.bom-alt-select', function () {
                    var val = $(this).val() || '';
                    item.replaces_row_uid = val;
                    if (val) {
                        item.priority = self.nextPriorityForTarget(item.routing_operation_id, val, item.uid);
                    }
                });
            },

            refreshAltSelectsForOp(opId) {
                var self = this;
                this.$nextTick(() => {
                    self.getItemsForOp(opId).forEach(item => {
                        if (item.is_alternative) {
                            var rowEl = document.querySelector('.bom-component-row[data-row-uid="' + item.uid + '"]');
                            if (rowEl) self.initAltSelect(rowEl, item);
                        }
                    });
                });
            },

            onAlternativeToggle(item) {
                var self = this;
                if (!item.is_alternative) {
                    item.replaces_row_uid = '';
                    var rowEl = document.querySelector('.bom-component-row[data-row-uid="' + item.uid + '"]');
                    if (rowEl) {
                        var $altSelect = $(rowEl).find('select.bom-alt-select');
                        if ($altSelect.data('select2')) $altSelect.select2('destroy');
                    }
                } else {
                    this.items.forEach(other => {
                        if (other.is_alternative && other.replaces_row_uid === item.uid) {
                            other.replaces_row_uid = '';
                        }
                    });
                    item.priority = this.nextPriorityForTarget(item.routing_operation_id, item.replaces_row_uid, item.uid);
                    this.$nextTick(() => {
                        var rowEl = document.querySelector('.bom-component-row[data-row-uid="' + item.uid + '"]');
                        if (rowEl) self.initAltSelect(rowEl, item);
                    });
                }
                self.refreshAltSelectsForOp(item.routing_operation_id);
            },

            addItemToOp(opId) {
                this.items.push({
                    uid: 'row_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                    material_id: '',
                    routing_operation_id: String(opId),
                    child_bom_id: null,
                    quantity: '',
                    notes: '',
                    uom_id: '',
                    material_scrap_percentage: 0,
                    is_alternative: false,
                    replaces_row_uid: '',
                    priority: 1,
                    child_bom_status: 'none',
                    child_bom_versions: [],
                    child_bom_loading: false
                });
                this.expandedOps[opId] = true;
                this.$nextTick(() => {
                    this.initAllRowSelects();
                    this.validateDuplicateMaterials();
                });
            },

            removeItem(index) {
                const removedItem = this.items[index];
                if (!removedItem) return;
                const opId = removedItem.routing_operation_id;

                const alts = removedItem.is_alternative ? [] : this.items.filter(it => it.is_alternative && it.replaces_row_uid === removedItem.uid);
                if (alts.length > 0) {
                    const mat = this.materials.find(m => String(m.id) === String(removedItem.material_id));
                    const msg = @js(__('production.bom_remove_primary_with_alts')).replace(':primary', mat ? mat.name : '').replace(':count', alts.length);
                    if (!confirm(msg)) return;
                }
                const removeUids = [removedItem.uid, ...alts.map(a => a.uid)];
                this.items = this.items.filter(it => !removeUids.includes(it.uid));
                this.$nextTick(() => {
                    this.initAllRowSelects();
                    if (opId) this.refreshAltSelectsForOp(opId);
                    this.validateDuplicateMaterials();
                });
            },

            validateDuplicateMaterials() {
                const parentProductId = String($('#product_id').val() || '');
                const seen = {};
                const updatedErrors = { ...this.errors };

                Object.keys(updatedErrors).forEach(key => {
                    if (key.startsWith('items.') && key.endsWith('.material_id')) {
                        const val = Array.isArray(updatedErrors[key]) ? updatedErrors[key][0] : updatedErrors[key];
                        if (typeof val === 'string' && (val.includes('Duplicate material component') || val.includes('is already a primary material') || val.includes('is already an alternative for') || val.includes('cannot be an alternative of itself') || val.includes('A finished product cannot be its own component'))) {
                            delete updatedErrors[key];
                        }
                    }
                });

                this.items.forEach((item, index) => {
                    const matId = String(item.material_id || '');
                    if (!matId) return;

                    if (parentProductId && matId === parentProductId) {
                        updatedErrors['items.' + index + '.material_id'] = 'A finished product cannot be its own component material.';
                        return;
                    }

                    // BM-IMPL-06: primary once per operation; an alternative once per replaced primary; never itself
                    const opId = String(item.routing_operation_id || '0');
                    const op = (this.availableOperations || []).find(o => String(o.id) === opId);
                    const seq = op ? op.sequence : opId;
                    const matName = (this.materials.find(m => String(m.id) === matId) || {}).name || matId;
                    let comboKey;
                    if (item.is_alternative) {
                        const target = this.items.find(it => it.uid === item.replaces_row_uid);
                        if (!target) return;
                        if (String(target.material_id) === matId) {
                            updatedErrors['items.' + index + '.material_id'] = 'A material cannot be an alternative of itself.';
                            return;
                        }
                        const tName = (this.materials.find(m => String(m.id) === String(target.material_id)) || {}).name || target.material_id;
                        comboKey = 'A_' + opId + '_' + target.uid + '_' + matId;
                        item._dupMsg = 'Material ' + matName + ' is already an alternative for ' + tName + ' in operation ' + seq + '.';
                    } else {
                        comboKey = 'P_' + opId + '_' + matId;
                        item._dupMsg = 'Material ' + matName + ' is already a primary material in operation ' + seq + '.';
                    }
                    if (!seen[comboKey]) seen[comboKey] = [];
                    seen[comboKey].push(index);
                });

                Object.keys(seen).forEach(comboKey => {
                    const indices = seen[comboKey];
                    if (indices.length > 1) {
                        for (let i = 1; i < indices.length; i++) {
                            updatedErrors['items.' + indices[i] + '.material_id'] = this.items[indices[i]]._dupMsg;
                        }
                    }
                });

                this.errors = updatedErrors;

                this.$nextTick(() => {
                    this.items.forEach((item, index) => {
                        const hasErr = !!this.errors['items.' + index + '.material_id'];
                        const $row = $('tr.bom-component-row[data-row-uid="' + item.uid + '"]');
                        const $select = $row.find('select[name*="[material_id]"]');
                        const $s2 = $select.next('.select2-container').find('.select2-selection');
                        const $feedback = $row.find('.invalid-feedback');
                        if (hasErr) {
                            $select.addClass('is-invalid');
                            $s2.addClass('is-invalid');
                            $feedback.removeClass('d-none').addClass('d-block');
                        } else {
                            $select.removeClass('is-invalid');
                            $s2.removeClass('is-invalid');
                            $feedback.removeClass('d-block');
                        }
                    });
                });
            },

            validateBeforeSubmit(e) {
                if (!this.selectedRoutingId) {
                    e.preventDefault();
                    alert('Please select a Process Routing in the header before saving the BOM.');
                    return false;
                }
                const configuredItems = this.items.filter(it => it.material_id);
                if (configuredItems.length === 0) {
                    e.preventDefault();
                    alert('Please add at least one material component to the Bill of Materials.');
                    return false;
                }

                this.validateDuplicateMaterials();
                const hasMaterialErrors = Object.keys(this.errors).some(k =>
                    k.startsWith('items.') && k.endsWith('.material_id') && !!this.errors[k]
                );
                if (hasMaterialErrors) {
                    e.preventDefault();
                    this.scrollToFirstError();
                    return false;
                }

                this.clearDraftStorage();
                return true;
            },

            initRowSelects(rowEl, item) {
                var self = this;
                $(rowEl).find('[data-select2-selector="default"]').each(function () {
                    var $select = $(this);
                    if ($select.hasClass('bom-alt-select')) return;
                    if ($select.data('select2-initialized') && $select.hasClass('select2-hidden-accessible')) return;

                    var parentProductId = $('#product_id').val();
                    if ($select.attr('name') && $select.attr('name').indexOf('material_id') !== -1) {
                        $select.find('option').each(function () {
                            $(this).prop('disabled', $(this).val() === parentProductId && parentProductId !== '');
                        });
                    }

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    $select.data('select2-initialized', true);
                    $select.select2(self.select2RowOptions());

                    $select.on('change', function () {
                        var val = $select.val();
                        var nameAttr = $select.attr('name') || '';

                        if (nameAttr.indexOf('material_id') !== -1) {
                            item.material_id = val;
                            var selectedOption = $select.find('option[value="' + val + '"]');
                            item.material_type = selectedOption.attr('data-type') || '';

                            var matUomId = selectedOption.attr('data-uom-id');
                            if (!matUomId && val) {
                                var foundMat = self.materials.find(m => String(m.id) === String(val));
                                if (foundMat && foundMat.uom_id) matUomId = foundMat.uom_id;
                            }
                            item.uom_id = matUomId ? String(matUomId) : '';
                            var $uomSelect = $(rowEl).find('select.bom-row-uom-select');
                            $uomSelect.val(item.uom_id).trigger($uomSelect.data('select2') ? 'change.select2' : 'change');

                            self.fetchChildBomStatus(item);
                            self.refreshAltSelectsForOp(item.routing_operation_id);
                            self.validateDuplicateMaterials();
                        } else if (nameAttr.indexOf('uom_id') !== -1) {
                            item.uom_id = val;
                        } else if (nameAttr.indexOf('child_bom_id') !== -1) {
                            item.child_bom_id = val;
                        }

                        this.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                });

                if (item.is_alternative) {
                    self.initAltSelect(rowEl, item);
                }
            },

            initAllRowSelects() {
                var self = this;
                $('.bom-component-row').each(function () {
                    var rowUid = $(this).attr('data-row-uid') || $(this).find('input[name*="[row_uid]"]').val();
                    var item = self.items.find(it => it.uid === rowUid);
                    if (item) self.initRowSelects(this, item);
                });
            },

            select2RowOptions() {
                return {
                    theme: "bootstrap-5",
                    width: '100%',
                    dropdownParent: $('body'),
                    dropdownCssClass: 'bom-select2-dropdown'
                };
            }
        }));

        $(document).on('shown.bs.tab', '#bomFormTabs button, #bomFormTabs a', function (e) {
            var targetId = $(e.target).attr('aria-controls') || $(e.target).attr('data-bs-target') || $(e.target).attr('href') || '';
            if (window.bomAlpineInstance) {
                if (targetId.indexOf('summary') !== -1) window.bomAlpineInstance.activeTab = 'summary';
                else if (targetId.indexOf('components') !== -1) window.bomAlpineInstance.activeTab = 'components';
                window.bomAlpineInstance.debouncedSaveDraft();
            }
        });

        $(document).ready(function () {
            if (window.bomAlpineInstance) {
                window.bomAlpineInstance.syncDraftSelect2();
            }
        });

        window.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'CHILD_BOM_CREATED' && window.bomAlpineInstance) {
                window.bomAlpineInstance.refreshChildBomStatusForProduct(event.data.product_id);
            }
        });
    });
</script>

