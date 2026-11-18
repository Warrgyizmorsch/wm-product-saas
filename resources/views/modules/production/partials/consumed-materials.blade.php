{{-- Temp no-BOM flow: raw materials consumed by this shop floor log. Rows post as consumed_materials[i][product_id|quantity|warehouse_id]. --}}
@if(config('production.temp_flow'))
    @php
        $cmId = $cmId ?? 'cm' . uniqid();
        $cmProducts = \App\Domains\Inventory\Models\Product::query()
            ->where(fn ($q) => $q->whereNull('item_type')->orWhere('item_type', '!=', 'Service'))
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->orderBy('name')->get(['id', 'name', 'sku']);
        $cmWarehouses = \App\Domains\Inventory\Models\Warehouse::query()
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']);
    @endphp
    <div class="mb-3 text-start" id="{{ $cmId }}">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label uppercase font-semibold fs-11 text-muted mb-0">Materials consumed</label>
            <button type="button" class="btn btn-sm btn-light-brand" data-cm-add>+ Add material</button>
        </div>
        <div data-cm-rows></div>
        <small class="text-muted fs-11">Stock is deducted from the chosen warehouse when the log is saved.</small>

        <template data-cm-template>
            <div class="row g-2 mb-2 align-items-center" data-cm-row>
                <div class="col-5">
                    <select class="form-select form-select-sm" data-name="product_id">
                        <option value="">— Material —</option>
                        @foreach($cmProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}{{ $p->sku ? ' (' . $p->sku . ')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-3">
                    <input type="number" class="form-control form-control-sm" data-name="quantity" min="0" step="0.0001" placeholder="Qty">
                </div>
                <div class="col-3">
                    <select class="form-select form-select-sm" data-name="warehouse_id">
                        @foreach($cmWarehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-1 text-end">
                    <button type="button" class="btn btn-sm btn-soft-danger" data-cm-remove>&times;</button>
                </div>
            </div>
        </template>
    </div>
    <script>
        (function () {
            const root = document.getElementById(@json($cmId));
            if (!root) return;
            const rows = root.querySelector('[data-cm-rows]');
            const tpl = root.querySelector('[data-cm-template]');
            let idx = 0;
            const renumber = () => {
                rows.querySelectorAll('[data-cm-row]').forEach((row, i) => {
                    row.querySelectorAll('[data-name]').forEach(el => {
                        el.name = 'consumed_materials[' + i + '][' + el.dataset.name + ']';
                    });
                });
            };
            root.querySelector('[data-cm-add]').addEventListener('click', () => {
                rows.appendChild(tpl.content.cloneNode(true));
                renumber();
            });
            rows.addEventListener('click', (e) => {
                if (e.target.closest('[data-cm-remove]')) {
                    e.target.closest('[data-cm-row]').remove();
                    renumber();
                }
            });
        })();
    </script>
@endif
