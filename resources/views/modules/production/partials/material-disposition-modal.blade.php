@php
    $modalId = $modalId ?? ('materialDispositionModal' . ($op->id ?? ''));
    $order = $order ?? $op->order ?? $op->productionOrder;
    $scrappableMats = $op->scrappable_materials ?? collect();
    $reservations = $order ? $order->reservations()->with(['product.uom', 'uom', 'warehouse'])->get() : collect();
    $modalTitle = '<i class="feather-package text-primary me-2"></i>Material Disposition — <span class="fs-12 text-muted fw-normal">Order: <strong>' . e($order->order_number ?? 'N/A') . '</strong> | Operation: <strong>' . e($op->name ?? ('Op #' . ($op->sequence ?? 1))) . '</strong></span>';
@endphp

<x-ui.modal id="{{ $modalId }}" :title="$modalTitle" size="lg" :centered="true" class="text-start">
    {{-- 1. "What happened to this material?" - 3 Distinct Disposition Choices --}}
    <div class="mb-3">
        <label class="form-label fs-11 fw-bold text-uppercase text-dark mb-1.5">
            <i class="feather-help-circle text-primary me-1"></i>What happened to this material?
        </label>
        <div class="row g-2">
            <div class="col-4">
                <input type="radio" class="btn-check" name="disposition_choice_{{ $modalId }}" id="disp_choice_scrap_{{ $modalId }}" value="scrap" checked autocomplete="off" onchange="toggleDispositionTab('{{ $modalId }}', 'scrap')">
                <label class="btn btn-outline-danger w-100 p-2.5 text-start h-100 d-flex flex-column justify-content-between rounded-3 shadow-2xs" for="disp_choice_scrap_{{ $modalId }}">
                    <div>
                        <div class="d-flex align-items-center mb-1">
                            <i class="feather-trash-2 text-danger me-1.5 fs-15"></i>
                            <strong class="fs-13 text-danger">SCRAP</strong>
                        </div>
                        <span class="d-block fs-11 text-muted" style="line-height: 1.25;">Damaged or unusable material that cannot be reused.</span>
                    </div>
                </label>
            </div>
            <div class="col-4">
                <input type="radio" class="btn-check" name="disposition_choice_{{ $modalId }}" id="disp_choice_offcut_{{ $modalId }}" value="offcut" autocomplete="off" onchange="toggleDispositionTab('{{ $modalId }}', 'offcut')">
                <label class="btn btn-outline-info w-100 p-2.5 text-start h-100 d-flex flex-column justify-content-between rounded-3 shadow-2xs" for="disp_choice_offcut_{{ $modalId }}">
                    <div>
                        <div class="d-flex align-items-center mb-1">
                            <i class="feather-scissors text-info me-1.5 fs-15"></i>
                            <strong class="fs-13 text-info">OFFCUT</strong>
                        </div>
                        <span class="d-block fs-11 text-muted" style="line-height: 1.25;">Usable leftover material stored for future production.</span>
                    </div>
                </label>
            </div>
            <div class="col-4">
                <input type="radio" class="btn-check" name="disposition_choice_{{ $modalId }}" id="disp_choice_return_{{ $modalId }}" value="return" autocomplete="off" onchange="toggleDispositionTab('{{ $modalId }}', 'return')">
                <label class="btn btn-outline-secondary w-100 p-2.5 text-start h-100 d-flex flex-column justify-content-between rounded-3 shadow-2xs" for="disp_choice_return_{{ $modalId }}">
                    <div>
                        <div class="d-flex align-items-center mb-1">
                            <i class="feather-corner-up-left text-secondary me-1.5 fs-15"></i>
                            <strong class="fs-13 text-dark">RETURN UNUSED</strong>
                        </div>
                        <span class="d-block fs-11 text-muted" style="line-height: 1.25;">Intact material to return back to warehouse stock.</span>
                    </div>
                </label>
            </div>
        </div>
    </div>

    {{-- 2. FORM A: SCRAP --}}
    <div id="disp_panel_scrap_{{ $modalId }}" class="disp-panel">
        <form method="POST" action="{{ route('production.mes.scrap', $op->id) }}" id="disp_form_scrap_{{ $modalId }}">
            @csrf
            <div class="bg-soft-danger p-2.5 rounded mb-3 border border-danger-subtle">
                <div class="d-flex align-items-center text-danger">
                    <i class="feather-trash-2 me-2 fs-14"></i>
                    <span class="fs-12 fw-bold text-uppercase">Record Operational Scrap — Unusable / Defective Material</span>
                </div>
                <span class="fs-11 text-muted">Damaged or unusable material. It will be recorded as scrap and will not become reusable stock.</span>
            </div>

            <div class="row g-2.5 text-start">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Material / Component" name="product_id" :required="true" :searchable="false" class="disp-mat-select" onchange="onDispMatChange('{{ $modalId }}', 'scrap', this)">
                        @foreach($scrappableMats as $idx => $mat)
                            <option value="{{ $mat['id'] }}"
                                data-measurement-type="{{ $mat['measurement_type'] ?? 'linear' }}"
                                data-balance-text="{{ $mat['balance_text'] ?? '' }}"
                                data-warehouse-id="{{ $mat['warehouse_id'] ?? '' }}"
                                data-remaining="{{ $mat['remaining_qty'] ?? 0 }}"
                                {{ $idx === 0 ? 'selected' : '' }}>
                                {{ $mat['name'] }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Measurement Type" name="measurement_type" id="disp_scrap_type_{{ $modalId }}" :required="true" :searchable="false" onchange="toggleDispFields('{{ $modalId }}', 'scrap', this.value)">
                        <option value="linear">Linear (Pipes, Bars, Profiles)</option>
                        <option value="sheet">Sheet (Plates, Sheets, Boards)</option>
                        <option value="weight">Weight (Coils, Granules, Resin)</option>
                        <option value="count">Count (Intact Discrete Pieces)</option>
                        <option value="direct">Direct Quantity (Manual / Legacy UOM)</option>
                    </x-ui.odoo-form-ui>
                </div>

                {{-- Material Balance Context Badge --}}
                <div class="col-12">
                    <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-between">
                        <span class="fs-11 fw-bold text-dark">
                            <i class="feather-info text-primary me-1"></i>Material Balance:
                        </span>
                        <span id="disp_scrap_balance_{{ $modalId }}" class="badge bg-white text-dark border fs-11 py-1 px-2 font-monospace">
                            {{ $scrappableMats->first()['balance_text'] ?? 'No issue history' }}
                        </span>
                    </div>
                </div>

                {{-- Linear Fields --}}
                <div class="col-md-6 disp-field-group disp-linear-field-{{ $modalId }}-scrap">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Scrap Length (mm)" name="length" id="disp_scrap_len_{{ $modalId }}" step="0.1" placeholder="e.g. 1800" />
                </div>
                <div class="col-md-6 disp-field-group disp-linear-field-{{ $modalId }}-scrap">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Pieces" name="pieces" value="1" min="1" />
                </div>

                {{-- Sheet Fields --}}
                <div class="col-md-4 disp-field-group disp-sheet-field-{{ $modalId }}-scrap d-none">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Length (mm)" name="sheet_length_dummy" id="disp_scrap_sheet_len_{{ $modalId }}" step="0.1" placeholder="e.g. 1200" oninput="document.getElementById('disp_scrap_len_{{ $modalId }}').value = this.value" />
                </div>
                <div class="col-md-4 disp-field-group disp-sheet-field-{{ $modalId }}-scrap d-none">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Width (mm)" name="width" step="0.1" placeholder="e.g. 800" />
                </div>
                <div class="col-md-4 disp-field-group disp-sheet-field-{{ $modalId }}-scrap d-none">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Thickness (mm)" name="thickness" step="0.01" placeholder="e.g. 2.0" />
                </div>

                {{-- Weight Fields --}}
                <div class="col-md-6 disp-field-group disp-weight-field-{{ $modalId }}-scrap d-none">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Weight" name="weight" step="0.001" placeholder="e.g. 14.500" />
                </div>
                <div class="col-md-6 disp-field-group disp-weight-field-{{ $modalId }}-scrap d-none">
                    <x-ui.odoo-form-ui type="select" label="Unit" name="weight_unit" :searchable="false">
                        <option value="kg" selected>Kilograms (kg)</option>
                        <option value="g">Grams (g)</option>
                    </x-ui.odoo-form-ui>
                </div>

                {{-- Direct Scrap Quantity Field --}}
                <div class="col-md-12 disp-field-group disp-direct-field-{{ $modalId }}-scrap d-none">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Scrap Quantity (Canonical UOM)" name="quantity" step="any" placeholder="e.g. 1.8" />
                </div>

                {{-- Reason and Classification --}}
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Scrap Reason Category" name="reason" :required="true" :searchable="false">
                        <option value="Damaged / Bent">Damaged / Bent</option>
                        <option value="Cutting Error / Wrong Dimension">Cutting Error / Wrong Dimension</option>
                        <option value="Setup Damage / Calibration Loss">Setup Damage / Calibration Loss</option>
                        <option value="Machine Breakdown / Tool Defect">Machine Breakdown / Tool Defect</option>
                        <option value="Raw Material Void / Internal Defect">Raw Material Void / Internal Defect</option>
                        <option value="Operator Mishap / Handling Damage">Operator Mishap / Handling Damage</option>
                        <option value="Other Operational Loss">Other Operational Loss</option>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Scrap Classification" name="scrap_type" :searchable="false">
                        <option value="unusable_defect" selected>Unusable Defect / Damaged Material</option>
                        <option value="offcut_scrap">Unusable Offcut / Drop Scrap</option>
                        <option value="process_waste">Process Waste / Shavings / Chips</option>
                        <option value="spoilage">Material Spoilage / Degradation</option>
                    </x-ui.odoo-form-ui>
                </div>

                {{-- Physical Storage Destination --}}
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Scrap Warehouse / Staging" name="scrap_warehouse_id" :searchable="false">
                        <option value="">Default Scrap Location</option>
                        @foreach($warehouses ?? [] as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Scrap Bin / Shelf Location" name="storage_location" placeholder="e.g. Scrap Hopper B-01" />
                </div>

                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" label="Scrap Observations & Notes" name="remarks" rows="2" placeholder="Provide additional details regarding scrap cause..." />
                </div>
            </div>
        </form>
    </div>

    {{-- 3. FORM B: OFFCUT --}}
    <div id="disp_panel_offcut_{{ $modalId }}" class="disp-panel d-none">
        <form method="POST" action="{{ route('production.mes.remnant', $op->id) }}" id="disp_form_offcut_{{ $modalId }}">
            @csrf
            <div class="bg-soft-info p-2.5 rounded mb-3 border border-info-subtle">
                <div class="d-flex align-items-center text-info">
                    <i class="feather-scissors me-2 fs-14"></i>
                    <span class="fs-12 fw-bold">SAVE REUSABLE OFFCUT / REMNANT</span>
                </div>
                <span class="fs-11 text-muted">Usable leftover material. It will be tagged as a remnant and can be used by future production orders.</span>
            </div>

            <div class="row g-2.5 text-start">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Material / Component" name="product_id" :required="true" :searchable="false" class="disp-mat-select" onchange="onDispMatChange('{{ $modalId }}', 'offcut', this)">
                        @foreach($scrappableMats as $idx => $mat)
                            <option value="{{ $mat['id'] }}"
                                data-measurement-type="{{ $mat['measurement_type'] ?? 'linear' }}"
                                data-balance-text="{{ $mat['balance_text'] ?? '' }}"
                                data-warehouse-id="{{ $mat['warehouse_id'] ?? '' }}"
                                data-remaining="{{ $mat['remaining_qty'] ?? 0 }}"
                                {{ $idx === 0 ? 'selected' : '' }}>
                                {{ $mat['name'] }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Measurement Type" name="measurement_type" id="disp_remnant_type_{{ $modalId }}" :required="true" :searchable="false" onchange="toggleDispFields('{{ $modalId }}', 'offcut', this.value)">
                        <option value="linear">Linear (Pipes, Bars, Profiles)</option>
                        <option value="sheet">Sheet (Plates, Sheets, Boards)</option>
                        <option value="weight">Weight (Coils, Granules, Resin)</option>
                        <option value="count">Count (Intact Discrete Pieces)</option>
                    </x-ui.odoo-form-ui>
                </div>

                {{-- Material Balance Context Badge --}}
                <div class="col-12">
                    <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-between">
                        <span class="fs-11 fw-bold text-dark">
                            <i class="feather-info text-primary me-1"></i>Material Balance:
                        </span>
                        <span id="disp_offcut_balance_{{ $modalId }}" class="badge bg-white text-dark border fs-11 py-1 px-2 font-monospace">
                            {{ $scrappableMats->first()['balance_text'] ?? 'No issue history' }}
                        </span>
                    </div>
                </div>

                {{-- Linear Fields --}}
                <div class="col-md-6 disp-field-group disp-linear-field-{{ $modalId }}-offcut">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Cut Off Length (mm)" name="length" id="disp_remnant_len_{{ $modalId }}" step="0.1" placeholder="e.g. 1800" />
                </div>
                <div class="col-md-6 disp-field-group disp-linear-field-{{ $modalId }}-offcut">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Pieces" name="pieces" value="1" min="1" />
                </div>

                {{-- Sheet Fields --}}
                <div class="col-md-4 disp-field-group disp-sheet-field-{{ $modalId }}-offcut d-none">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Length (mm)" name="sheet_length_dummy" id="disp_remnant_sheet_len_{{ $modalId }}" step="0.1" placeholder="e.g. 1200" oninput="document.getElementById('disp_remnant_len_{{ $modalId }}').value = this.value" />
                </div>
                <div class="col-md-4 disp-field-group disp-sheet-field-{{ $modalId }}-offcut d-none">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Width (mm)" name="width" step="0.1" placeholder="e.g. 800" />
                </div>
                <div class="col-md-4 disp-field-group disp-sheet-field-{{ $modalId }}-offcut d-none">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Thickness (mm)" name="thickness" step="0.01" placeholder="e.g. 2.0" />
                </div>

                {{-- Weight Fields --}}
                <div class="col-md-6 disp-field-group disp-weight-field-{{ $modalId }}-offcut d-none">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Weight" name="weight" step="0.001" placeholder="e.g. 14.500" />
                </div>
                <div class="col-md-6 disp-field-group disp-weight-field-{{ $modalId }}-offcut d-none">
                    <x-ui.odoo-form-ui type="select" label="Unit" name="weight_unit" :searchable="false">
                        <option value="kg" selected>Kilograms (kg)</option>
                        <option value="g">Grams (g)</option>
                    </x-ui.odoo-form-ui>
                </div>

                {{-- Storage Destination --}}
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Target Warehouse" name="warehouse_id" id="disp_remnant_wh_{{ $modalId }}" :required="true" :searchable="false">
                        @foreach($warehouses ?? [] as $wh)
                            <option value="{{ $wh->id }}" {{ $wh->is_default ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Store Rack / Bin Location" name="warehouse_location" placeholder="e.g. Rack B-03, Shelf 2" />
                </div>

                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="input" label="Remnant Notes / Observations" name="notes" placeholder="e.g. Clean square offcut from cutting operation" />
                </div>
            </div>
        </form>
    </div>

    {{-- 4. FORM C: RETURN UNUSED --}}
    <div id="disp_panel_return_{{ $modalId }}" class="disp-panel d-none">
        <form method="POST" action="{{ route('production.mes.return-material', $op->id) }}" id="disp_form_return_{{ $modalId }}">
            @csrf
            <div class="bg-soft-secondary p-2.5 rounded mb-3 border">
                <div class="d-flex align-items-center text-dark">
                    <i class="feather-corner-up-left me-2 fs-14"></i>
                    <span class="fs-12 fw-bold">RETURN INTACT RAW MATERIAL TO STORE</span>
                </div>
                <span class="fs-11 text-muted">Completely unused and intact material. It will return to normal warehouse stock and credit WIP.</span>
            </div>

            <div class="row g-2.5 text-start">
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="select" label="Select Material Reservation Line" name="reservation_id" id="disp_return_res_{{ $modalId }}" :required="true" :searchable="false" onchange="onDispReturnResChange('{{ $modalId }}', this)">
                        @forelse($reservations as $idx => $r)
                            @php
                                $resBal = $op->getMaterialBalance($r->product_id);
                                $remVal = $resBal['remaining_qty'];
                            @endphp
                            <option value="{{ $r->id }}"
                                data-remaining="{{ $remVal }}"
                                data-balance-text="Issued: {{ number_format($r->quantity_issued, 2) }} {{ $r->uom?->code }} • Consumed: {{ number_format($resBal['consumed_qty'], 2) }} {{ $r->uom?->code }} • Remaining: {{ number_format($remVal, 2) }} {{ $r->uom?->code }}"
                                data-warehouse-id="{{ $r->warehouse_id }}"
                                {{ $idx === 0 ? 'selected' : '' }}>
                                {{ $r->product->name }} — (Remaining: {{ number_format($remVal, 2) }} {{ $r->uom?->code }})
                            </option>
                        @empty
                            <option value="">No material reservations found</option>
                        @endforelse
                    </x-ui.odoo-form-ui>
                </div>

                {{-- Material Balance Context Badge for Return --}}
                <div class="col-12">
                    <div class="p-2 rounded bg-light border d-flex align-items-center justify-content-between">
                        <span class="fs-11 fw-bold text-dark">
                            <i class="feather-info text-primary me-1"></i>Reservation Balance:
                        </span>
                        <span id="disp_return_balance_{{ $modalId }}" class="badge bg-white text-dark border fs-11 py-1 px-2 font-monospace">
                            @if($reservations->isNotEmpty())
                                @php
                                    $firstRes = $reservations->first();
                                    $firstBal = $op->getMaterialBalance($firstRes->product_id);
                                @endphp
                                Issued: {{ number_format($firstRes->quantity_issued, 2) }} {{ $firstRes->uom?->code }} • Consumed: {{ number_format($firstBal['consumed_qty'], 2) }} {{ $firstRes->uom?->code }} • Remaining: {{ number_format($firstBal['remaining_qty'], 2) }} {{ $firstRes->uom?->code }}
                            @else
                                No issued reservations
                            @endif
                        </span>
                    </div>
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Quantity to Return" name="quantity" id="disp_return_qty_{{ $modalId }}"
                        value="{{ $reservations->isNotEmpty() ? $op->getMaterialBalance($reservations->first()->product_id)['remaining_qty'] : 1 }}"
                        step="any" min="0.0001" :required="true" />
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Destination Warehouse" name="warehouse_id" id="disp_return_wh_{{ $modalId }}" :searchable="false">
                        <option value="">Issuing Warehouse (Default)</option>
                        @foreach($warehouses ?? [] as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="input" label="Return Remarks / Reason" name="remarks" placeholder="e.g. Uncut intact stock returned to warehouse" />
                </div>
            </div>
        </form>
    </div>

    {{-- Modal Footer with Dynamic Primary Action Button --}}
    <x-slot name="footer">
        <div class="d-flex justify-content-between align-items-center w-100">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="border">Cancel</x-ui.button>
            <x-ui.button type="button" variant="danger" id="disp_submit_btn_{{ $modalId }}" class="fw-bold px-4"
                onclick="submitActiveDisposition('{{ $modalId }}')">
                <i class="feather-trash-2 me-1"></i>Record Scrap
            </x-ui.button>
        </div>
    </x-slot>
</x-ui.modal>

<script>
    if (typeof window.toggleDispositionTab === 'undefined') {
        window.toggleDispositionTab = function(modalId, mode) {
            const scrapPanel = document.getElementById('disp_panel_scrap_' + modalId);
            const offcutPanel = document.getElementById('disp_panel_offcut_' + modalId);
            const returnPanel = document.getElementById('disp_panel_return_' + modalId);
            const submitBtn = document.getElementById('disp_submit_btn_' + modalId);

            if (scrapPanel) scrapPanel.classList.toggle('d-none', mode !== 'scrap');
            if (offcutPanel) offcutPanel.classList.toggle('d-none', mode !== 'offcut');
            if (returnPanel) returnPanel.classList.toggle('d-none', mode !== 'return');

            if (submitBtn) {
                if (mode === 'scrap') {
                    submitBtn.className = 'btn btn-animated btn-danger fw-bold px-4';
                    submitBtn.innerHTML = '<i class="feather-trash-2 me-1"></i>Record Scrap';
                } else if (mode === 'offcut') {
                    submitBtn.className = 'btn btn-animated btn-info text-white fw-bold px-4';
                    submitBtn.innerHTML = '<i class="feather-scissors me-1"></i>Save Offcut';
                } else if (mode === 'return') {
                    submitBtn.className = 'btn btn-animated btn-secondary fw-bold px-4';
                    submitBtn.innerHTML = '<i class="feather-corner-up-left me-1"></i>Return Material';
                }
            }
        };

        window.toggleDispFields = function(modalId, mode, type) {
            const modalEl = document.getElementById(modalId);
            if (!modalEl) return;
            modalEl.querySelectorAll('.disp-field-group').forEach(function(el) {
                if (el.className.includes('-' + mode)) {
                    el.classList.add('d-none');
                }
            });

            if (type === 'linear') {
                modalEl.querySelectorAll('.disp-linear-field-' + modalId + '-' + mode).forEach(function(el) {
                    el.classList.remove('d-none');
                });
            } else if (type === 'sheet') {
                modalEl.querySelectorAll('.disp-sheet-field-' + modalId + '-' + mode).forEach(function(el) {
                    el.classList.remove('d-none');
                });
            } else if (type === 'weight') {
                modalEl.querySelectorAll('.disp-weight-field-' + modalId + '-' + mode).forEach(function(el) {
                    el.classList.remove('d-none');
                });
            } else if (type === 'direct') {
                modalEl.querySelectorAll('.disp-direct-field-' + modalId + '-' + mode).forEach(function(el) {
                    el.classList.remove('d-none');
                });
            }
        };

        window.onDispMatChange = function(modalId, mode, selectEl) {
            const opt = selectEl.options[selectEl.selectedIndex];
            if (!opt) return;
            const measType = opt.getAttribute('data-measurement-type') || (mode === 'scrap' ? 'direct' : 'linear');
            const balanceText = opt.getAttribute('data-balance-text') || '';
            const whId = opt.getAttribute('data-warehouse-id');

            const typeSelect = document.getElementById('disp_' + mode + '_type_' + modalId);
            if (typeSelect) {
                typeSelect.value = measType;
                toggleDispFields(modalId, mode, measType);
            }
            const balanceBadge = document.getElementById('disp_' + mode + '_balance_' + modalId);
            if (balanceBadge && balanceText) {
                balanceBadge.textContent = balanceText;
            }
            if (mode === 'offcut') {
                const whSelect = document.getElementById('disp_remnant_wh_' + modalId);
                if (whSelect && whId) {
                    whSelect.value = whId;
                }
            }
        };

        window.onDispReturnResChange = function(modalId, selectEl) {
            const opt = selectEl.options[selectEl.selectedIndex];
            if (!opt) return;
            const remVal = opt.getAttribute('data-remaining');
            const balanceText = opt.getAttribute('data-balance-text');
            const whId = opt.getAttribute('data-warehouse-id');

            const balanceBadge = document.getElementById('disp_return_balance_' + modalId);
            if (balanceBadge && balanceText) {
                balanceBadge.textContent = balanceText;
            }
            const qtyInput = document.getElementById('disp_return_qty_' + modalId);
            if (qtyInput && remVal) {
                qtyInput.value = remVal;
            }
            const whSelect = document.getElementById('disp_return_wh_' + modalId);
            if (whSelect && whId) {
                whSelect.value = whId;
            }
        };

        window.submitActiveDisposition = function(modalId) {
            const scrapChoice = document.getElementById('disp_choice_scrap_' + modalId);
            const offcutChoice = document.getElementById('disp_choice_offcut_' + modalId);
            const returnChoice = document.getElementById('disp_choice_return_' + modalId);

            if (scrapChoice && scrapChoice.checked) {
                const form = document.getElementById('disp_form_scrap_' + modalId);
                if (form) {
                    if (form.reportValidity && !form.reportValidity()) return;
                    form.submit();
                }
            } else if (offcutChoice && offcutChoice.checked) {
                const form = document.getElementById('disp_form_offcut_' + modalId);
                if (form) {
                    if (form.reportValidity && !form.reportValidity()) return;
                    form.submit();
                }
            } else if (returnChoice && returnChoice.checked) {
                const form = document.getElementById('disp_form_return_' + modalId);
                if (form) {
                    if (form.reportValidity && !form.reportValidity()) return;
                    form.submit();
                }
            }
        };
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('{{ $modalId }}');
        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function() {
                const scrapSelect = modalEl.querySelector('#disp_form_scrap_{{ $modalId }} .disp-mat-select');
                if (scrapSelect) onDispMatChange('{{ $modalId }}', 'scrap', scrapSelect);
                const offcutSelect = modalEl.querySelector('#disp_form_offcut_{{ $modalId }} .disp-mat-select');
                if (offcutSelect) onDispMatChange('{{ $modalId }}', 'offcut', offcutSelect);
                const returnSelect = document.getElementById('disp_return_res_{{ $modalId }}');
                if (returnSelect) onDispReturnResChange('{{ $modalId }}', returnSelect);
            });
        }
    });
</script>
