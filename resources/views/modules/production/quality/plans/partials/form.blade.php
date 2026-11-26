{{--
    Quality Plan create / edit form — one partial for both screens (docs/production_quality_rules.md).
    QR-01/02: scope Product / Section / Process General switches the target field (scope select is a plain select so Alpine sees changes)
    QR-06:    Min/Max/Unit only for Numeric rows, "Basis / Reason" for Pass/Fail rows; hidden fields are not posted
    QR-07/08: status and version are shown read-only — set by the lifecycle
    @var \App\Domains\Production\Models\ProductionQualityPlan|null $plan
--}}
@php
    $isEdit = $plan !== null;
    $editingApproved = $isEdit && $plan->isApproved();
    $nextVersion = $editingApproved ? ((int) ($plan->latestVersion()?->version ?? $plan->version) + 1) : null;

    $initialParams = old('parameters');
    if ($initialParams === null) {
        $initialParams = $isEdit
            ? $plan->parameters->map(fn ($p) => [
                'name' => $p->name,
                'type' => $p->type,
                'min_value' => $p->min_value,
                'max_value' => $p->max_value,
                'uom_id' => $p->uom_id,
                'basis' => $p->basis,
                'is_mandatory' => (bool) $p->is_mandatory,
            ])->values()->all()
            : [['name' => '', 'type' => 'numeric', 'min_value' => '', 'max_value' => '', 'uom_id' => '', 'basis' => '', 'is_mandatory' => true]];
    }
    $initialParams = collect($initialParams)->map(fn ($p) => [
        'name' => $p['name'] ?? '',
        'type' => $p['type'] ?? 'numeric',
        'min_value' => $p['min_value'] ?? '',
        'max_value' => $p['max_value'] ?? '',
        'uom_id' => isset($p['uom_id']) && $p['uom_id'] !== '' ? (string) $p['uom_id'] : '',
        'basis' => $p['basis'] ?? '',
        'is_mandatory' => filter_var($p['is_mandatory'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ])->values()->all();

    $scope = old('type', $isEdit ? $plan->type : 'product');
    if (!in_array($scope, ['product', 'section', 'process'], true)) {
        $scope = 'product';
    }
    $selectedProduct = old('product_id', $isEdit ? $plan->product_id : null);
    $selectedSection = old('section_id', $isEdit ? $plan->section_id : null);
@endphp

<div x-data="qualityPlanForm(@js($scope), @js($initialParams))">
@if ($editingApproved)
    <div class="alert alert-soft-warning border border-warning d-flex align-items-start gap-2 fs-12 mb-4" role="alert">
        <i class="feather-git-branch mt-1"></i>
        <div>
            You are editing <strong>approved version {{ $plan->version }}</strong>. Saving creates <strong>version {{ $nextVersion }}</strong> as a draft.
            Version {{ $plan->version }} stays approved and in use until version {{ $nextVersion }} is approved; then it becomes historical.
        </div>
    </div>
@endif

<div class="row g-4 mb-4 fs-13 text-dark">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Plan Name" name="name" placeholder="Standard final quality checklist" :value="old('name', $isEdit ? $plan->name : '')" :required="true" :error-text="$errors->first('name')" />

        <div class="odoo-form-group">
            <label class="odoo-form-label">Version</label>
            <div class="flex-grow-1 fs-13 fw-semibold text-dark">
                @if (!$isEdit)
                    1 <span class="text-muted fw-normal fs-12">— set automatically</span>
                @elseif ($editingApproved)
                    {{ $nextVersion }} <span class="text-muted fw-normal fs-12">— new version of v{{ $plan->version }}</span>
                @else
                    {{ $plan->version }} <span class="text-muted fw-normal fs-12">— set automatically</span>
                @endif
            </div>
        </div>

        <x-ui.odoo-form-ui type="select" label="Scope Type" name="type" id="qp_scope_type" x-model="scopeType" :searchable="false" :required="true" :error-text="$errors->first('type')">
            <option value="product" @selected($scope === 'product')>Product specific</option>
            <option value="section" @selected($scope === 'section')>Section specific</option>
            <option value="process" @selected($scope === 'process')>Process general</option>
        </x-ui.odoo-form-ui>
    </div>

    <div class="col-md-6">
        <div x-show="scopeType === 'product'" x-cloak>
            @php
                // QM-14: one plan in use per product — a product held by another plan family is disabled
                $ownRootId = $isEdit ? ($plan->root_plan_id ?: $plan->id) : null;
                $holders = collect($productPlanHolders ?? [])->reject(fn ($holder) => $ownRootId && (int) ($holder->root_plan_id ?: $holder->id) === (int) $ownRootId);
            @endphp
            <x-ui.odoo-form-ui type="select" label="Target Product" name="product_id" id="qp_product_id" x-bind:disabled="scopeType !== 'product'" :required="true" :error-text="$errors->first('product_id')"
                helper-text="{{ $holders->isNotEmpty() ? 'Products that already have a quality plan are disabled — edit that plan instead. A new plan can be created only after it becomes historical.' : '' }}">
                <option value="">Select product…</option>
                @foreach ($products as $p)
                    @php $holder = $holders->get($p->id); @endphp
                    <option value="{{ $p->id }}" @selected((string) $selectedProduct === (string) $p->id) @disabled($holder)>
                        {{ $p->sku }} - {{ $p->name }}@if ($holder) — quality plan {{ $holder->plan_number }} ({{ $holder->statusLabel() }}) already exists, edit that plan @endif
                    </option>
                @endforeach
            </x-ui.odoo-form-ui>
        </div>

        <div x-show="scopeType === 'section'" x-cloak>
            <x-ui.odoo-form-ui type="select" label="Target Section" name="section_id" id="qp_section_id" x-bind:disabled="scopeType !== 'section'" :required="true" :error-text="$errors->first('section_id')">
                <option value="">Select section…</option>
                @foreach ($sections as $s)
                    <option value="{{ $s->id }}" @selected((string) $selectedSection === (string) $s->id)>{{ $s->workCenter?->name ? $s->workCenter->name . ' / ' : '' }}{{ $s->name }}{{ $s->code ? ' (' . $s->code . ')' : '' }}</option>
                @endforeach
            </x-ui.odoo-form-ui>
        </div>

        <div x-show="scopeType === 'process'" x-cloak class="odoo-form-group">
            <label class="odoo-form-label">Applies to</label>
            <div class="flex-grow-1 fs-12 text-muted">Any process — pick it on a routing operation's QC tick.</div>
        </div>

        <div class="odoo-form-group">
            <label class="odoo-form-label">Status</label>
            <div class="flex-grow-1">
                <span class="badge bg-soft-secondary text-secondary text-uppercase font-monospace fs-10">Draft</span>
                <span class="text-muted fs-12 ms-1">Submit for approval from the plan's View screen.</span>
            </div>
        </div>
    </div>
</div>

<div class="border-top pt-4">
    <div class="d-flex align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">Checklist Parameters</h5>
        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" @click="addParameter()">
            <i class="feather-plus me-1"></i>Add Rule
        </button>
    </div>

    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 26%">Parameter Name</th>
                    <th style="width: 16%">Type</th>
                    <th style="width: 40%">Limits / Basis</th>
                    <th class="text-center" style="width: 9%">Mandatory</th>
                    <th class="text-end" style="width: 9%">{{ __('production.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(param, index) in parameters" :key="param.uid">
                    <tr>
                        <td class="align-top">
                            <input type="text" :name="`parameters[${index}][name]`" x-model="param.name" class="form-control form-control-sm" placeholder="Thickness check" required />
                        </td>
                        <td class="align-top">
                            <select :name="`parameters[${index}][type]`" x-model="param.type" @change="onTypeChange(param)" class="form-select form-select-sm">
                                <option value="numeric">Numeric value</option>
                                <option value="pass_fail">Pass / fail</option>
                                <option value="text">Text notes</option>
                            </select>
                        </td>
                        <td class="align-top">
                            <template x-if="param.type === 'numeric'">
                                <div class="d-flex gap-2">
                                    <input type="number" step="any" :name="`parameters[${index}][min_value]`" x-model="param.min_value" class="form-control form-control-sm" placeholder="Min" />
                                    <input type="number" step="any" :name="`parameters[${index}][max_value]`" x-model="param.max_value" class="form-control form-control-sm" placeholder="Max" />
                                    <select :name="`parameters[${index}][uom_id]`" x-model="param.uom_id" class="form-select form-select-sm" title="Unit of measure">
                                        <option value="">Unit…</option>
                                        @foreach ($uoms as $u)
                                            <option value="{{ $u->id }}">{{ $u->code }} — {{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </template>
                            <template x-if="param.type === 'pass_fail'">
                                <input type="text" :name="`parameters[${index}][basis]`" x-model="param.basis" class="form-control form-control-sm" placeholder="Basis / reason — e.g. no holes visible on light table" />
                            </template>
                            <template x-if="param.type === 'text'">
                                <span class="text-muted fs-12">Free notes are typed at inspection time.</span>
                            </template>
                        </td>
                        <td class="text-center align-top">
                            <input type="hidden" :name="`parameters[${index}][is_mandatory]`" :value="param.is_mandatory ? 1 : 0" />
                            <input type="checkbox" x-model="param.is_mandatory" class="form-check-input mt-1" />
                        </td>
                        <td class="text-end align-top">
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeParameter(index)" aria-label="Remove rule">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </x-ui.odoo-form-ui>
    </div>
    @error('parameters')<div class="text-danger fs-12 mt-1">{{ $message }}</div>@enderror
    @foreach ($errors->getMessages() as $key => $msgs)
        @if (str_starts_with($key, 'parameters.'))
            <div class="text-danger fs-12 mt-1">{{ $msgs[0] }}</div>
        @endif
    @endforeach
</div>
</div>

@push('styles')
    <style>[x-cloak]{display:none !important;}</style>
@endpush

@push('scripts')
    <script>
        function qualityPlanForm(initialScope, initialParams) {
            let seq = 0;
            const withUid = (p) => Object.assign({ uid: ++seq }, p);
            return {
                scopeType: initialScope || 'product',
                parameters: (initialParams || []).map(withUid),
                addParameter() {
                    this.parameters.push(withUid({ name: '', type: 'numeric', min_value: '', max_value: '', uom_id: '', basis: '', is_mandatory: true }));
                },
                removeParameter(index) {
                    if (this.parameters.length > 1) {
                        this.parameters.splice(index, 1);
                    } else {
                        alert('A quality plan needs at least one checklist parameter.');
                    }
                },
                // QR-06: values that do not belong to the new type are cleared so nothing stale is sent
                onTypeChange(param) {
                    if (param.type !== 'numeric') {
                        param.min_value = '';
                        param.max_value = '';
                        param.uom_id = '';
                    }
                    if (param.type !== 'pass_fail') {
                        param.basis = '';
                    }
                }
            };
        }
    </script>
@endpush
