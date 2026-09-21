{{-- Header buttons for a customizable dashboard: Customize, then the edit-mode toolbar. Pairs with partials.dashboard.grid. --}}
<div id="dash-view-actions">
    <button type="button" class="btn btn-primary" id="dash-customize"><i class="feather-sliders me-2"></i>Customize</button>
</div>
<div id="dash-edit-actions" class="d-none align-items-center gap-2 flex-wrap">
    <button type="button" class="btn btn-light-brand" id="dash-add"><i class="feather-plus me-2"></i>Add widget</button>
    @if (! empty($layoutPresets))
        <select id="dash-preset" class="form-select" style="width:auto" title="Replace the layout with a ready-made one (nothing changes until you save)">
            <option value="">Load a layout…</option>
            @foreach (array_keys($layoutPresets) as $name)
                <option value="{{ $name }}">{{ ucfirst($name) }} layout</option>
            @endforeach
        </select>
    @endif
    @if ($canManage)
        <select id="dash-scope" class="form-select" style="width:auto">
            <option value="personal">Save as my dashboard</option>
            <option value="tenant">Save as company default</option>
            <option value="role">Save as role default…</option>
        </select>
        <select id="dash-role" class="form-select d-none" style="width:auto">
            @foreach ($roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
        </select>
    @endif
    <button type="button" class="btn btn-light" id="dash-reset" title="Discard my customization and use the default"><i class="feather-rotate-ccw me-2"></i>Reset</button>
    <button type="button" class="btn btn-light" id="dash-cancel">Cancel</button>
    <button type="button" class="btn btn-primary" id="dash-save"><i class="feather-check me-2"></i>Save</button>
</div>
