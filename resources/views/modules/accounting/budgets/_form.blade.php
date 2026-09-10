@php
    $budget = $budget ?? null;
@endphp

<x-ui.odoo-form-ui type="sheet">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
        <h5 class="fw-bold text-dark mb-0">Budget Details</h5>
        <x-ui.button href="{{ route('accounting.budgets.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
    </div>

    <div class="row g-4 fs-13 text-dark">
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="select" label="Fiscal Year" name="fiscal_year_id" :required="true">
                <option value="">Select Fiscal Year...</option>
                @foreach ($fiscalYears as $fy)
                    <option value="{{ $fy->id }}" @selected(old('fiscal_year_id', $budget?->fiscal_year_id) == $fy->id)>{{ $fy->name }} ({{ $fy->status }})</option>
                @endforeach
            </x-ui.odoo-form-ui>
        </div>
        <div class="col-md-6">
            <x-ui.odoo-form-ui type="input" label="Budget Name" name="name" :value="old('name', $budget?->name)" placeholder="e.g. FY26 Operating Budget" :required="true" />
        </div>
    </div>

    <div class="border-top pt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0 fs-14">Budget Lines</h5>
        </div>
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width: 26%;">Account</th>
                        <th style="width: 16%;">Dimension</th>
                        <th style="width: 24%;">Cost Center / Department / Project</th>
                        <th class="text-end" style="width: 18%;">Annual Amount</th>
                        <th class="text-center" style="width: 6%;"></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic Rows -->
                </tbody>
            </x-ui.odoo-form-ui>
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                <i class="feather-plus me-1"></i>Add a line
            </button>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
        <x-ui.button href="{{ route('accounting.budgets.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
        <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">{{ $submitLabel }}</x-ui.button>
    </div>
</x-ui.odoo-form-ui>
