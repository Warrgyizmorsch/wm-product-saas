<div class="row g-3">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Name" name="name" :value="old('name')" :required="true" placeholder="e.g. Warehouse Supervisor" class="@error('name') is-invalid @enderror" />
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Slug" name="slug" :value="old('slug')" placeholder="auto-created if empty" helperText="Used internally to reference this role." class="@error('slug') is-invalid @enderror" />
        @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="number" label="Level" name="level" :value="old('level')" placeholder="100" helperText="Lower numbers outrank higher ones (Super Admin = 1)." min="1" max="65535" class="@error('level') is-invalid @enderror" />
        @error('level')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12">
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" :value="old('description')" :rows="3" class="@error('description') is-invalid @enderror" />
        @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
