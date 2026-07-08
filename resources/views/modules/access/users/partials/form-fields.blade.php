@php
    $isCreate = $isCreate ?? false;
    $suffix = $user->id ?? 'new';
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" label="Name" name="name" :value="old('name', $user->name)" :required="true" class="@error('name') is-invalid @enderror" />
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <x-ui.odoo-form-ui type="input" inputType="email" label="Email" name="email" :value="old('email', $user->email)" :required="true" class="@error('email') is-invalid @enderror" />
        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <div class="odoo-form-group">
            <label class="odoo-form-label" for="password_{{ $suffix }}" style="{{ $isCreate ? 'color: #dc3545 !important;' : '' }}">
                Password @if($isCreate)<span class="text-danger">*</span>@endif
            </label>
            <div class="flex-grow-1 position-relative">
                <input type="password" name="password" id="password_{{ $suffix }}" class="odoo-form-control pe-4 @error('password') is-invalid @enderror" {{ $isCreate ? 'required' : '' }}>
                <button type="button" class="password-toggle-btn btn btn-link btn-sm p-0 position-absolute end-0 top-0" data-target="password_{{ $suffix }}" tabindex="-1" aria-label="Toggle password visibility">
                    <i class="feather-eye fs-14 text-muted"></i>
                </button>
                <div class="text-muted fs-11 mt-1">{{ $isCreate ? 'Minimum 8 characters.' : 'Leave blank to keep the current password.' }}</div>
                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="odoo-form-group">
            <label class="odoo-form-label" for="password_confirmation_{{ $suffix }}">Confirm</label>
            <div class="flex-grow-1 position-relative">
                <input type="password" name="password_confirmation" id="password_confirmation_{{ $suffix }}" class="odoo-form-control pe-4">
                <button type="button" class="password-toggle-btn btn btn-link btn-sm p-0 position-absolute end-0 top-0" data-target="password_confirmation_{{ $suffix }}" tabindex="-1" aria-label="Toggle password visibility">
                    <i class="feather-eye fs-14 text-muted"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <x-ui.odoo-form-ui type="select" label="Role" name="role_id" :required="true" :searchable="false" class="@error('role_id') is-invalid @enderror">
            @php $currentRoleId = old('role_id', $user->role_id); @endphp
            <option value="">Select a role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) $currentRoleId === (string) $role->id)>
                    {{ $role->name }} @if($role->tenant_id === null) (Global) @endif
                </option>
            @endforeach
        </x-ui.odoo-form-ui>
        @error('role_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (e) {
                var toggle = e.target.closest('.password-toggle-btn');
                if (!toggle) return;

                var input = document.getElementById(toggle.dataset.target);
                if (!input) return;

                var isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');

                var icon = toggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('feather-eye', !isPassword);
                    icon.classList.toggle('feather-eye-off', isPassword);
                }
            });
        </script>
    @endpush
@endonce
