{{--
    QR-15 / QR-16 (docs/production_quality_rules.md): routing operation QC modal.
    - Ticking QC opens this modal; an eye icon next to the tick reopens it. Unticking discards the choices.
    - Filter dropdown (Section plans / Process general / Both) + searchable multi-select + Add → Selected plans list
      with a Mandatory tick, in-modal Preview of the plan's checks and Remove.
    - Choices live in the browser (operation.quality_plans) until the routing is saved; no extra column on the form.
    Included inside the routing form's x-data scope; the form's x-data is wrapped with routingQcMixin(...).
    @var array $qcPlanCatalog   plan id => option (QualityPlanService::planOption)
    @var array $qcInitial       per operation row (form order): [[id, is_mandatory], …]
--}}
<style>
    [x-cloak] { display: none !important; }
    .rqc-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); z-index: 1065; display: flex; align-items: flex-start; justify-content: center; padding: 6vh 16px; overflow-y: auto; }
    .rqc-dialog { background: #fff; border-radius: 10px; width: 100%; max-width: 760px; box-shadow: 0 10px 30px rgba(0,0,0,.18); }
    .rqc-list { border: 1px solid #dee2e6; border-radius: 6px; max-height: 240px; overflow-y: auto; background: #fff; }
    .rqc-opt { padding: 6px 10px; display: flex; gap: 8px; align-items: center; cursor: pointer; border-bottom: 1px solid #f1f3f5; font-size: 13px; }
    .rqc-opt:hover { background: #f8f9fa; }
    .rqc-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; background: #e7f1ff; color: #0b5ed7; border-radius: 4px; padding: 1px 6px; margin: 2px; }
    .rqc-field { border: 1px solid #ced4da; border-radius: 6px; min-height: 34px; padding: 2px 6px; display: flex; flex-wrap: wrap; align-items: center; cursor: text; background: #fff; }
    .rqc-field input { border: 0; outline: 0; flex: 1; min-width: 120px; font-size: 13px; height: 28px; background: transparent; }
    .rqc-eye { background: none; border: 0; padding: 0 2px; color: var(--bs-primary, #0d6efd); line-height: 1; }
    html.app-skin-dark .rqc-dialog, html.app-skin-dark .rqc-list, html.app-skin-dark .rqc-field { background: #1e2433; color: #e9ecef; border-color: #2f3747; }
    html.app-skin-dark .rqc-opt:hover { background: #262d3d; }
</style>

<div class="rqc-overlay" x-show="qc.open" x-cloak x-transition.opacity @keydown.escape.window="qc.open && qcCancel()">
    <div class="rqc-dialog" @click.outside="qc.listOpen = false">
        <div class="d-flex justify-content-between align-items-start p-3 border-bottom">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    Quality checks for <span x-text="qc.op ? ('Op ' + (qc.op.sequence || '') + ' · ' + (qc.op.name || 'Unnamed operation')) : ''"></span>
                </h5>
                <div class="fs-12 text-muted">
                    <span x-text="qcSectionLabel()"></span> · only Approved plans are offered
                </div>
            </div>
            <button type="button" class="btn-close" aria-label="Close" @click="qcCancel()"></button>
        </div>

        <div class="p-3">
            <div class="fs-12 fw-bold text-dark mb-2">Add plans</div>
            <div class="d-flex gap-2 align-items-start flex-wrap">
                <select class="form-select form-select-sm" style="width: 170px;" x-model="qc.filter" @change="qc.pending = []; qc.search = ''">
                    <option value="section">Section plans</option>
                    <option value="process">Process general</option>
                    <option value="both">Both</option>
                </select>

                <div class="flex-grow-1" style="min-width: 240px;">
                    <div class="rqc-field" @click="qc.listOpen = true; $nextTick(() => $refs.qcSearch && $refs.qcSearch.focus())">
                        <template x-for="id in qc.pending" :key="'chip-' + id">
                            <span class="rqc-chip">
                                <span x-text="qcPlanTitle(id)"></span>
                                <button type="button" class="btn btn-link p-0 text-decoration-none" style="font-size: 13px; line-height: 1;" @click.stop="qcTogglePending(id)" aria-label="Remove">&times;</button>
                            </span>
                        </template>
                        <input type="text" x-ref="qcSearch" x-model="qc.search" placeholder="Search plans by name or number" @focus="qc.listOpen = true" autocomplete="off">
                    </div>
                    <div class="rqc-list mt-1" x-show="qc.listOpen">
                        <template x-if="qc.filter === 'section' && !(qc.op && qc.op.section_id)">
                            <div class="p-2 fs-12 text-muted">Pick a section on this operation first to see its section plans.</div>
                        </template>
                        <template x-for="p in qcOptions()" :key="'opt-' + p.id">
                            <div class="rqc-opt" @click="qcTogglePending(p.id)">
                                <input type="checkbox" class="form-check-input m-0" :checked="qc.pending.includes(p.id)" tabindex="-1">
                                <span class="flex-grow-1">
                                    <span class="text-dark" x-text="p.name"></span>
                                    <span class="text-muted fs-11" x-text="' ' + (p.plan_number || '') + ' · v' + p.version + ' · ' + p.checks_count + ' checks' + (qc.filter === 'both' ? ' · ' + p.scope_label : '')"></span>
                                </span>
                            </div>
                        </template>
                        <template x-if="qcOptions().length === 0 && !(qc.filter === 'section' && !(qc.op && qc.op.section_id))">
                            <div class="p-2 fs-12 text-muted">No more approved plans for this filter.</div>
                        </template>
                    </div>
                </div>

                <button type="button" class="btn btn-sm btn-primary" @click="qcAdd()"><i class="feather-plus me-1"></i>Add</button>
            </div>
            <div class="text-danger fs-12 mt-1" x-show="qc.addError" x-text="qc.addError"></div>

            <div class="fs-12 fw-bold text-dark mt-4 mb-2">Selected plans</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 fs-13">
                    <thead>
                        <tr class="text-muted fs-11 text-uppercase">
                            <th style="width: 46%">Plan</th>
                            <th style="width: 18%">Scope</th>
                            <th style="width: 16%" class="text-center">Mandatory</th>
                            <th style="width: 20%" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in qc.selected" :key="'sel-' + row.id">
                            <tr>
                                <td colspan="4" class="p-0 border-0">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <td style="width: 46%">
                                                <div class="text-dark fw-semibold" x-text="qcPlan(row.id).name"></div>
                                                <div class="fs-11 text-muted" x-text="(qcPlan(row.id).plan_number || '') + ' · v' + qcPlan(row.id).version + ' · ' + qcPlan(row.id).checks_count + ' checks'"></div>
                                                <template x-if="qcPlan(row.id).status !== 'approved'">
                                                    <div class="fs-11 text-danger" x-text="qcPlan(row.id).status_label + ' — replace it with an approved plan.'"></div>
                                                </template>
                                                <template x-if="qcPlan(row.id).newer_version">
                                                    <div class="fs-11 text-warning" x-text="'Newer approved version v' + qcPlan(row.id).newer_version + ' exists.'"></div>
                                                </template>
                                            </td>
                                            <td style="width: 18%" x-text="qcPlan(row.id).scope_label"></td>
                                            <td style="width: 16%" class="text-center">
                                                <input type="checkbox" class="form-check-input" x-model="row.is_mandatory">
                                            </td>
                                            <td style="width: 20%" class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-light border" @click="qc.preview = (qc.preview === row.id ? null : row.id)" :aria-label="'Preview checks'">
                                                    <i :class="qc.preview === row.id ? 'feather-eye-off' : 'feather-eye'"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" @click="qcRemove(row.id)" aria-label="Remove plan">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr x-show="qc.preview === row.id">
                                            <td colspan="4" class="bg-light">
                                                <div class="fs-11 text-muted mb-1" x-text="'Checks in ' + qcPlan(row.id).name + ' v' + qcPlan(row.id).version + ' (read only)'"></div>
                                                <template x-for="(c, ci) in (qcPlan(row.id).checks || [])" :key="'chk-' + row.id + '-' + ci">
                                                    <div class="d-flex gap-2 fs-12 py-1 border-bottom">
                                                        <span style="width: 32%" class="text-dark">
                                                            <span x-text="c.name"></span><span class="text-danger" x-show="c.mandatory"> *</span>
                                                        </span>
                                                        <span style="width: 20%" class="text-muted" x-text="c.type"></span>
                                                        <span class="flex-grow-1" x-text="c.limits"></span>
                                                    </div>
                                                </template>
                                                <a :href="qcPlan(row.id).show_url" target="_blank" rel="noopener" class="fs-12 d-inline-block mt-2">
                                                    Open full plan in a new tab <i class="feather-external-link"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="fs-12 text-muted py-2" x-show="qc.selected.length === 0">No plans added yet.</div>
            </div>
            <div class="text-danger fs-12 mt-2" x-show="qc.saveError" x-text="qc.saveError"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <span class="fs-12 text-muted" x-text="qc.selected.length + ' plan(s) · ' + qc.selected.filter(r => r.is_mandatory).length + ' mandatory'"></span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-light border" @click="qcCancel()">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" @click="qcSave()">Save choices</button>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            /**
             * QR-16: adds the QC modal state + methods to the routing form's Alpine object (wraps init / sectionChanged).
             */
            function routingQcMixin(base, catalog, initial) {
                const origInit = base.init;
                const origSectionChanged = base.sectionChanged;

                Object.assign(base, {
                    qcCatalog: catalog || {},
                    qc: { open: false, op: null, filter: 'section', search: '', listOpen: false, pending: [], selected: [], preview: null, addError: '', saveError: '', fresh: false },

                    init() {
                        if (typeof origInit === 'function') origInit.call(this);
                        (this.operations || []).forEach((op, i) => {
                            const rows = Array.isArray(initial) && Array.isArray(initial[i]) ? initial[i] : [];
                            op.quality_plans = op.quality_required ? rows.map(r => ({ id: Number(r.id), is_mandatory: !!r.is_mandatory })) : [];
                        });
                    },

                    sectionChanged(operation, $row) {
                        const result = typeof origSectionChanged === 'function' ? origSectionChanged.call(this, operation, $row) : undefined;
                        this.qcPruneSection(operation);
                        return result;
                    },

                    qcPlan(id) {
                        return this.qcCatalog[id] || { id: id, name: 'Plan #' + id, plan_number: '', version: '?', checks_count: 0, checks: [], scope: '', scope_label: '—', status: 'unknown', status_label: 'Unavailable', show_url: '#' };
                    },
                    qcPlanTitle(id) {
                        const p = this.qcPlan(id);
                        return p.name + ' v' + p.version;
                    },
                    qcSectionLabel() {
                        const op = this.qc.op;
                        if (!op || !op.section_id) return 'No section selected';
                        const sec = (op.availableSections || []).find(s => String(s.id) === String(op.section_id));
                        return 'Section: ' + (sec ? sec.label : ('#' + op.section_id));
                    },
                    qcOptions() {
                        const op = this.qc.op;
                        if (!op) return [];
                        const term = (this.qc.search || '').toLowerCase().trim();
                        const taken = this.qc.selected.map(r => r.id);
                        return Object.values(this.qcCatalog).filter(p => {
                            if (p.status !== 'approved' || taken.includes(p.id)) return false;
                            const isSection = p.scope === 'section' && op.section_id && String(p.section_id) === String(op.section_id);
                            const isProcess = p.scope === 'process';
                            if (this.qc.filter === 'section' && !isSection) return false;
                            if (this.qc.filter === 'process' && !isProcess) return false;
                            if (this.qc.filter === 'both' && !isSection && !isProcess) return false;
                            if (!term) return true;
                            return (p.name || '').toLowerCase().includes(term) || (p.plan_number || '').toLowerCase().includes(term);
                        });
                    },

                    // Tick / untick of the QC gate
                    qcToggle(operation) {
                        if (operation.quality_required) {
                            operation.quality_plans = operation.quality_plans || [];
                            this.qcOpen(operation, true);
                        } else {
                            operation.quality_plans = [];
                            if (this.qc.open && this.qc.op === operation) {
                                this.qc.open = false;
                                this.qc.op = null;
                            }
                        }
                    },
                    qcOpen(operation, fresh = false) {
                        this.qc.op = operation;
                        this.qc.selected = (operation.quality_plans || []).map(r => ({ id: Number(r.id), is_mandatory: !!r.is_mandatory }));
                        this.qc.fresh = !!fresh && this.qc.selected.length === 0;
                        this.qc.filter = operation.section_id ? 'section' : 'process';
                        this.qc.search = '';
                        this.qc.pending = [];
                        this.qc.preview = null;
                        this.qc.addError = '';
                        this.qc.saveError = '';
                        this.qc.listOpen = false;
                        this.qc.open = true;
                    },
                    qcTogglePending(id) {
                        id = Number(id);
                        this.qc.pending = this.qc.pending.includes(id) ? this.qc.pending.filter(x => x !== id) : this.qc.pending.concat([id]);
                        this.qc.addError = '';
                    },
                    qcAdd() {
                        if (this.qc.pending.length === 0) {
                            this.qc.addError = 'Choose at least one plan in the dropdown first.';
                            return;
                        }
                        this.qc.pending.forEach(id => {
                            if (!this.qc.selected.some(r => r.id === id)) {
                                this.qc.selected.push({ id: id, is_mandatory: true });
                            }
                        });
                        this.qc.pending = [];
                        this.qc.search = '';
                        this.qc.listOpen = false;
                        this.qc.saveError = '';
                    },
                    qcRemove(id) {
                        this.qc.selected = this.qc.selected.filter(r => r.id !== id);
                        if (this.qc.preview === id) this.qc.preview = null;
                    },
                    qcSave() {
                        if (this.qc.selected.length === 0) {
                            this.qc.saveError = 'Add at least one plan, or untick QC.';
                            return;
                        }
                        this.qc.op.quality_plans = this.qc.selected.map(r => ({ id: r.id, is_mandatory: !!r.is_mandatory }));
                        this.qc.open = false;
                        this.qc.op = null;
                    },
                    qcCancel() {
                        const op = this.qc.op;
                        if (op && this.qc.fresh && (!op.quality_plans || op.quality_plans.length === 0)) {
                            op.quality_required = false; // a fresh tick with nothing saved is undone
                        }
                        this.qc.open = false;
                        this.qc.op = null;
                    },
                    // A changed section drops picks from the old section (Process General picks stay)
                    qcPruneSection(operation) {
                        if (!operation || !Array.isArray(operation.quality_plans)) return;
                        operation.quality_plans = operation.quality_plans.filter(r => {
                            const p = this.qcCatalog[r.id];
                            return !p || p.scope !== 'section' || String(p.section_id) === String(operation.section_id || '');
                        });
                    },
                    qcCountLabel(operation) {
                        const n = (operation.quality_plans || []).length;
                        return n ? (n + ' QC plan(s) — click to review') : 'No QC plan picked — click to add';
                    }
                });

                return base;
            }
        </script>
    @endpush
@endonce
