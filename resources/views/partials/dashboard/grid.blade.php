{{--
    The customizable widget grid shared by every dashboard: grid, widget gallery, settings
    dialog and the editor script. Needs: $dashboard, $layout, $catalog, $canManage, $periods.
    Optional: $layoutPresets (ready-made layouts), $initial (widget payloads already computed
    on the server, by layout item id).

    The page decides which filters apply by defining window.dashboardBaseQuery() (returns
    {preset, from, to, scope, cost_center_id}) and calls window.dashboardReload() when they change.
--}}
@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack.min.css">
    <style>
        .dash-widget { height: 100%; display: flex; flex-direction: column; }
        .dash-widget .card-body { flex: 1; min-height: 0; overflow: auto; padding: .5rem 1rem; }
        .dash-widget-title { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color, #6c757d); }
        .dash-kpi { height: 100%; display: flex; flex-direction: column; justify-content: center; }
        .dash-kpi-value { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
        .dash-bare { position: relative; height: 100%; overflow: hidden; }
        .dash-bare:hover { overflow-y: auto; overflow-x: hidden; }
        .dash-bare > .card, .dash-bare > .row > [class*="col"] > .card { height: 100%; margin-bottom: 0 !important; }
        .dash-tools { position: absolute; top: 6px; right: 6px; z-index: 5; display: none; gap: 4px; background: #fffc; border-radius: 6px; padding: 2px; }
        .dash-editing .dash-tools { display: flex; }
        .dash-remove, .dash-handle, .dash-settings { display: none; }
        .dash-editing .dash-remove, .dash-editing .dash-settings { display: inline-flex; }
        .dash-editing .dash-handle { display: inline-flex; cursor: move; }
        .dash-editing .grid-stack-item-content > .card, .dash-editing .grid-stack-item-content > .dash-bare { outline: 2px dashed var(--bs-primary, #3454d1); outline-offset: -2px; }
        .dash-gallery { position: fixed; top: 0; right: 0; bottom: 0; width: 360px; max-width: 100vw; z-index: 1060; transform: translateX(100%); transition: transform .2s; overflow-y: auto; }
        .dash-gallery.open { transform: none; }
        .dash-modal { position: fixed; inset: 0; z-index: 1070; background: #0006; display: none; align-items: center; justify-content: center; padding: 16px; }
        .dash-modal.open { display: flex; }
        .dash-modal .card { width: 420px; max-width: 100%; }
        .dash-link { display: block; padding: .35rem 0; border-bottom: 1px solid #0000000f; }
        .dash-skeleton { height: 14px; border-radius: 4px; background: linear-gradient(90deg, #0000000d, #0000001a, #0000000d); }
        @media (max-width: 767px) { .grid-stack > .grid-stack-item { position: static !important; width: 100% !important; height: auto !important; min-height: 140px; } }
    </style>
@endpush

<div id="dash-root">
    <div id="dash-empty" class="card d-none">
        <div class="card-body text-center py-5">
            <h5 class="mb-2">Your dashboard is empty</h5>
            <p class="text-muted mb-3">Pick the widgets you want to see here.</p>
            <button type="button" class="btn btn-primary" id="dash-empty-add">Add widgets</button>
        </div>
    </div>
    <div class="grid-stack" id="dash-grid"></div>
</div>

<div class="dash-modal" id="dash-modal">
    <div class="card">
        <div class="card-header"><h6 class="mb-0" id="dash-modal-heading">Widget settings</h6></div>
        <div class="card-body">
            <label class="form-label small">Title</label>
            <input type="text" id="dash-s-title" class="form-control mb-3" maxlength="60">
            <div id="dash-s-period-wrap" class="mb-3">
                <label class="form-label small">Period</label>
                <select id="dash-s-period" class="form-select">
                    <option value="">Follow the dashboard filter</option>
                    @foreach ($periods as $value => $label)
                        @if ($value !== 'custom')
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div id="dash-s-limit-wrap" class="mb-3">
                <label class="form-label small">Rows to show</label>
                <input type="number" id="dash-s-limit" class="form-control" min="3" max="20" placeholder="8">
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light" id="dash-s-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="dash-s-apply">Apply</button>
            </div>
        </div>
    </div>
</div>

<div class="dash-gallery card" id="dash-gallery">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Add widget</h6>
        <button type="button" class="btn btn-sm btn-light" id="dash-gallery-close">Close</button>
    </div>
    <div class="card-body">
        <input type="search" id="dash-gallery-search" class="form-control mb-3" placeholder="Search widgets">
        <div id="dash-gallery-list"></div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/vendors/js/apexcharts.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/gridstack@10.3.1/dist/gridstack-all.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const DASHBOARD = @json($dashboard);
            const CATALOG = @json($catalog);
            const INITIAL = @json($layout);
            const PRELOADED = @json($initial ?? new stdClass());
            const PRESETS = @json($layoutPresets ?? new stdClass());
            const URLS = {
                widget: key => @json(url('/dashboard/widgets')) + '/' + encodeURIComponent(key),
                layout: @json(route('dashboard.layout.save')),
            };
            const CSRF = document.querySelector('meta[name="csrf-token"]').content;
            const catalogByKey = Object.fromEntries(CATALOG.map(w => [w.key, w]));

            let currentSource = @json($source ?? 'starter');
            const root = document.getElementById('dash-root');
            const emptyEl = document.getElementById('dash-empty');
            const gallery = document.getElementById('dash-gallery');
            const modal = document.getElementById('dash-modal');
            const el = id => document.getElementById(id);
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
            const fmt = (value, digits) => Number(value).toLocaleString(undefined, {minimumFractionDigits: digits, maximumFractionDigits: digits});

            const meta = new Map();      // grid item id -> {key, config}
            const charts = new Map();    // grid item id -> [ApexCharts]
            let saved = JSON.parse(JSON.stringify(INITIAL));
            let preloaded = PRELOADED;

            const grid = GridStack.init({
                column: 12, cellHeight: 70, margin: 8, float: false, disableDrag: true, disableResize: true,
                handle: '.dash-handle', minRow: 1,
            }, '#dash-grid');

            // ── Requests ──
            function query(id) {
                const {key, config} = meta.get(id);
                const def = catalogByKey[key] || {settings: {}};
                const base = typeof window.dashboardBaseQuery === 'function' ? window.dashboardBaseQuery() : {};
                const own = def.settings.period && config.period;
                const p = new URLSearchParams({dashboard: DASHBOARD});
                Object.entries(base).forEach(([k, v]) => { if (v !== '' && v != null && !(own && ['from', 'to'].includes(k))) p.set(k, v); });
                if (own) p.set('preset', own);
                if (def.settings.limit && config.limit) p.set('limit', config.limit);
                return p.toString();
            }

            async function send(method, body) {
                const res = await fetch(URLS.layout, {method, credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
                    body: JSON.stringify(Object.assign({dashboard: DASHBOARD}, body || {}))});
                if (!res.ok) throw new Error('HTTP ' + res.status);
            }

            // ── Widgets ──
            function itemHtml(id, def, config) {
                const title = esc(config.title || def.title);
                if (def.chrome === false) {
                    return `<div class="dash-bare" data-body="${esc(id)}"><div class="dash-tools">
                        <button type="button" class="btn btn-sm btn-icon btn-light dash-settings" data-settings="${esc(id)}" title="Settings"><i class="feather-settings"></i></button>
                        <span class="dash-handle btn btn-sm btn-icon btn-light" title="Move"><i class="feather-move"></i></span>
                        <button type="button" class="btn btn-sm btn-icon btn-light dash-remove" data-remove="${esc(id)}" title="Remove"><i class="feather-x"></i></button></div>
                        <div class="card"><div class="card-body"><div class="dash-skeleton w-50 mb-2"></div><div class="dash-skeleton w-75"></div></div></div></div>`;
                }
                return `<div class="card dash-widget"><div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="dash-widget-title"><i class="${esc(def.icon)} me-2"></i><span data-title="${esc(id)}">${title}</span></span>
                    <span><button type="button" class="btn btn-sm btn-icon btn-light dash-settings me-1" data-settings="${esc(id)}" title="Settings"><i class="feather-settings"></i></button><span class="dash-handle me-2 text-muted" title="Move"><i class="feather-move"></i></span>
                    <button type="button" class="btn btn-sm btn-icon btn-light dash-remove" data-remove="${esc(id)}" title="Remove"><i class="feather-x"></i></button></span>
                </div><div class="card-body" data-body="${esc(id)}"><div class="dash-skeleton w-50 mb-2"></div><div class="dash-skeleton w-75"></div></div></div>`;
            }

            function addWidget(item) {
                const def = catalogByKey[item.key];
                if (!def) return;
                meta.set(item.id, {key: item.key, config: item.config || {}});
                const minW = def.min_w || def.minW || 2;
                const minH = def.min_h || def.minH || 1;
                const node = {id: item.id, x: item.x, y: item.y, w: item.w, h: item.h, minW: minW, minH: minH, content: itemHtml(item.id, def, item.config || {})};
                if (item.x === undefined) { delete node.x; delete node.y; }
                grid.addWidget(node);

                if (preloaded[item.id]) show(item.id, preloaded[item.id]); else load(item.id);
            }

            async function load(id) {
                if (!meta.has(id)) return;
                try {
                    const res = await fetch(URLS.widget(meta.get(id).key) + '?' + query(id), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
                    if (!res.ok) throw new Error(res.status);
                    show(id, await res.json());
                } catch (e) {
                    const body = root.querySelector(`[data-body="${CSS.escape(id)}"]`);
                    if (body) body.innerHTML = '<div class="card"><div class="card-body text-muted small">This widget could not be loaded.</div></div>';
                }
            }

            function show(id, d) {
                const holder = root.querySelector(`[data-body="${CSS.escape(id)}"]`);
                if (!holder) return;
                (charts.get(id) || []).forEach(c => c.destroy()); charts.delete(id);

                if (d.type === 'html') {
                    // The block draws its own card; keep the edit tools that sit in front of it.
                    const tools = holder.querySelector('.dash-tools');
                    holder.innerHTML = (tools ? tools.outerHTML : '') + d.html;
                    
                    // Relocate embedded modals directly to document.body to prevent parent container stacking/blur issues
                    holder.querySelectorAll('.modal').forEach(modal => {
                        if (modal.id) {
                            const existing = document.querySelectorAll(`body > #${CSS.escape(modal.id)}`);
                            existing.forEach(el => el.remove());
                        }
                        document.body.appendChild(modal);
                    });

                    const list = [];
                    (d.charts || []).forEach(spec => {
                        const target = holder.querySelector('#' + CSS.escape(spec.id));
                        if (!target) return;
                        const options = Object.assign({dataLabels: {enabled: false}}, spec.options);
                        if (spec.format) {
                            options.yaxis = Object.assign({}, options.yaxis, {labels: {formatter: v => fmt(v, spec.format.axis)}});
                            options.tooltip = Object.assign({}, options.tooltip, {y: {formatter: v => fmt(v, spec.format.tooltip)}});
                        }
                        const chart = new ApexCharts(target, options);
                        chart.render();
                        list.push(chart);
                    });
                    charts.set(id, list);
                    fit(id);
                    return;
                }

                const body = holder;
                if (d.type === 'kpi') {
                    body.innerHTML = `<div class="dash-kpi"><div class="dash-kpi-value text-${esc(d.tone || 'primary')}">${esc(d.value)}</div>` + (d.sub ? `<div class="text-muted small mt-1">${esc(d.sub)}</div>` : '') + '</div>';
                } else if (d.type === 'list') {
                    body.innerHTML = d.rows.length
                        ? '<ul class="list-unstyled mb-0">' + d.rows.map(r => `<li class="d-flex justify-content-between py-1 border-bottom"><span>${esc(r.label)}</span><span class="text-muted">${esc(r.value)}</span></li>`).join('') + '</ul>'
                        : '<div class="text-muted small">Nothing to show.</div>';
                } else if (d.type === 'links') {
                    body.innerHTML = d.rows.length
                        ? d.rows.map(r => `<a class="dash-link" href="${esc(r.url)}">${esc(r.label)}</a>`).join('')
                        : '<div class="text-muted small">Nothing to show.</div>';
                } else if (d.type === 'line') {
                    if (!d.labels.length) { body.innerHTML = '<div class="text-muted small">No data yet.</div>'; return; }
                    body.innerHTML = '<div></div>';
                    const chart = new ApexCharts(body.firstChild, {chart: {type: 'line', height: '100%', toolbar: {show: false}}, series: d.series, xaxis: {categories: d.labels}, stroke: {curve: 'smooth', width: 3}, legend: {position: 'bottom'}});
                    chart.render();
                    charts.set(id, [chart]);
                } else if (d.type === 'donut' || d.type === 'bar') {
                    if (!d.values.length) { body.innerHTML = '<div class="text-muted small">No data yet.</div>'; return; }
                    body.innerHTML = '<div></div>';
                    const opts = d.type === 'donut'
                        ? {chart: {type: 'donut', height: '100%'}, series: d.values, labels: d.labels, legend: {position: 'bottom'}}
                        : {chart: {type: 'bar', height: '100%', toolbar: {show: false}}, series: [{data: d.values}], xaxis: {categories: d.labels}, plotOptions: {bar: {distributed: true, borderRadius: 4}}, legend: {show: false}};
                    const chart = new ApexCharts(body.firstChild, opts);
                    chart.render();
                    charts.set(id, [chart]);
                }
            }

            // A block that draws its own card grows until its content fits (except fixed KPI cards & saved user layouts).
            function fit(id) {
                if (currentSource !== 'starter') return;
                const node = grid.engine.nodes.find(n => n.id === id);
                if (!node) return;
                const key = meta.get(id)?.key || '';
                if (key.includes('kpi')) return; // Keep top KPI summary boxes at fixed height
                const content = node.el && node.el.querySelector('.grid-stack-item-content');
                if (!content) return;
                const holder = content.querySelector('.dash-bare');
                if (!holder || holder.scrollHeight <= holder.clientHeight + 2) return;
                const unit = 70 + 8;
                grid.update(node.el, {h: Math.ceil((holder.scrollHeight + 8) / unit)});
            }

            // ── Editing ──
            function setEditing(on) {
                root.classList.toggle('dash-editing', on);
                grid.enableMove(on); grid.enableResize(on);
                el('dash-view-actions').classList.toggle('d-none', on);
                el('dash-edit-actions').classList.toggle('d-none', !on);
                el('dash-edit-actions').classList.toggle('d-flex', on);
                if (!on) gallery.classList.remove('open');
            }

            function refreshEmpty() { emptyEl.classList.toggle('d-none', meta.size > 0); }

            function build(layout, usePreloaded) {
                preloaded = usePreloaded ? PRELOADED : {};
                grid.removeAll(true); meta.clear(); charts.forEach(list => list.forEach(c => c.destroy())); charts.clear();
                grid.batchUpdate(); layout.forEach(addWidget); grid.commit();
                refreshEmpty();
                // Server-rendered blocks are already in place; size them once the grid has laid out.
                if (usePreloaded) requestAnimationFrame(() => meta.forEach((_, id) => fit(id)));
            }

            function current() {
                return grid.save(false).map(n => ({id: n.id, key: meta.get(n.id).key, x: n.x, y: n.y, w: n.w, h: n.h, config: meta.get(n.id).config}));
            }

            function drawGallery(q = '') {
                const term = q.trim().toLowerCase();
                const groups = {};
                CATALOG.filter(w => !term || (w.title + ' ' + w.module + ' ' + w.description).toLowerCase().includes(term))
                    .forEach(w => (groups[w.module] ||= []).push(w));
                el('dash-gallery-list').innerHTML = Object.keys(groups).sort().map(m =>
                    `<div class="text-uppercase text-muted small fw-bold mt-3 mb-2">${esc(m)}</div>` + groups[m].map(w =>
                        `<div class="d-flex align-items-start justify-content-between border rounded p-2 mb-2"><div><div class="fw-semibold"><i class="${esc(w.icon)} me-2"></i>${esc(w.title)}</div><div class="text-muted small">${esc(w.description)}</div></div>
                        <button type="button" class="btn btn-sm btn-primary ms-2" data-add="${esc(w.key)}">Add</button></div>`).join('')
                ).join('') || '<div class="text-muted small">No widgets match.</div>';
            }

            el('dash-customize').onclick = () => setEditing(true);
            el('dash-add').onclick = el('dash-empty-add').onclick = () => { setEditing(true); drawGallery(); gallery.classList.add('open'); };
            el('dash-gallery-close').onclick = () => gallery.classList.remove('open');
            el('dash-gallery-search').oninput = e => drawGallery(e.target.value);
            el('dash-cancel').onclick = () => { build(saved, true); setEditing(false); if (el('dash-preset')) el('dash-preset').value = ''; };

            if (el('dash-preset')) el('dash-preset').onchange = e => { if (PRESETS[e.target.value]) build(PRESETS[e.target.value], false); };

            el('dash-gallery-list').addEventListener('click', e => {
                const key = e.target.closest('[data-add]')?.dataset.add;
                if (!key) return;
                const def = catalogByKey[key];
                addWidget({id: key + '-' + Math.random().toString(36).slice(2, 8), key, w: def.w, h: def.h, config: {}});
                refreshEmpty();
            });

            root.addEventListener('click', e => {
                const rm = e.target.closest('[data-remove]')?.dataset.remove;
                if (rm) {
                    const node = grid.engine.nodes.find(n => n.id === rm);
                    (charts.get(rm) || []).forEach(c => c.destroy()); charts.delete(rm); meta.delete(rm);
                    if (node) grid.removeWidget(node.el);
                    refreshEmpty();
                    return;
                }
                const st = e.target.closest('[data-settings]')?.dataset.settings;
                if (st) openSettings(st);
            });

            // ── Per-widget settings ──
            let settingsFor = null;

            function openSettings(id) {
                const {key, config} = meta.get(id);
                const def = catalogByKey[key];
                settingsFor = id;
                el('dash-modal-heading').textContent = def.title + ' - settings';
                el('dash-s-title').value = config.title || '';
                el('dash-s-title').placeholder = def.title;
                el('dash-s-title').disabled = def.chrome === false;
                el('dash-s-period').value = config.period || '';
                el('dash-s-limit').value = config.limit || '';
                el('dash-s-period-wrap').style.display = def.settings.period ? '' : 'none';
                el('dash-s-limit-wrap').style.display = def.settings.limit ? '' : 'none';
                modal.classList.add('open');
            }

            function closeSettings() { modal.classList.remove('open'); settingsFor = null; }

            el('dash-s-cancel').onclick = closeSettings;
            el('dash-s-apply').onclick = () => {
                if (!settingsFor) return;
                const id = settingsFor, m = meta.get(id), def = catalogByKey[m.key], config = {};
                const title = el('dash-s-title').value.trim();
                if (title && def.chrome !== false) config.title = title;
                if (def.settings.period && el('dash-s-period').value) config.period = el('dash-s-period').value;
                const limit = parseInt(el('dash-s-limit').value, 10);
                if (def.settings.limit && limit) config.limit = Math.max(3, Math.min(20, limit));
                m.config = config;
                const titleEl = root.querySelector(`[data-title="${CSS.escape(id)}"]`);
                if (titleEl) titleEl.textContent = config.title || def.title;
                closeSettings();
                preloaded = {};
                load(id);
            };

            // ── Saving ──
            const scope = el('dash-scope');
            if (scope) scope.onchange = () => el('dash-role').classList.toggle('d-none', scope.value !== 'role');

            el('dash-save').onclick = async () => {
                const btn = el('dash-save'); btn.disabled = true;
                try {
                    const layout = current();
                    const s = scope ? scope.value : 'personal';
                    await send('PUT', {scope: s, role_id: s === 'role' ? el('dash-role').value : null, widgets: layout});
                    if (s === 'personal') {
                        saved = layout;
                        currentSource = 'personal';
                    }
                    setEditing(false);
                } catch (e) {
                    alert('Could not save the dashboard. Please try again.');
                } finally { btn.disabled = false; }
            };

            el('dash-reset').onclick = async () => {
                if (!confirm('Discard your customization and go back to the default dashboard?')) return;
                try { await send('DELETE'); window.location.reload(); } catch (e) { alert('Could not reset the dashboard.'); }
            };

            // Live Web Punch Digital Clock Ticker
            setInterval(function () {
                const clockEls = document.querySelectorAll('.live-web-clock');
                if (clockEls.length > 0) {
                    const now = new Date();
                    const timeStr = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    clockEls.forEach(el => { el.textContent = timeStr; });
                }
            }, 1000);

            // Universal Modal Backdrop & Stacking Context Handler for Dashboard Widgets
            document.addEventListener('show.bs.modal', function (e) {
                if (e.target && e.target.classList.contains('modal')) {
                    if (e.target.parentElement !== document.body) {
                        document.body.appendChild(e.target);
                    }
                    e.target.style.zIndex = '1060';
                    setTimeout(function () {
                        document.querySelectorAll('.modal-backdrop').forEach(b => {
                            b.style.zIndex = '1050';
                        });
                    }, 10);
                }
            }, true);

            document.addEventListener('hidden.bs.modal', function () {
                if (!document.querySelector('.modal.show')) {
                    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            }, true);

            setEditing(false);
            build(saved, true);
        });
    </script>
@endpush
