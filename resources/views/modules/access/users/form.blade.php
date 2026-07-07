@php
    $isModal = $modal ?? false;
@endphp

<div class="{{ $isModal ? '' : 'row' }}">
    <div class="{{ $isModal ? '' : 'col-xxl-8 col-xl-9 mx-auto' }}">
        <form action="{{ $action }}" method="POST" @isset($formId) id="{{ $formId }}" @endisset>
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            @if ($isModal)
                <x-ui.odoo-form-ui type="sheet" class="border-0 shadow-none p-0 mb-0">
                    @include('modules.access.users.partials.form-fields', [
                        'user' => $user,
                        'roles' => $roles,
                        'isCreate' => strtoupper($method) === 'POST',
                    ])

                    <div class="pt-4 d-flex justify-content-end gap-2">
                        <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" icon="feather-check-circle">{{ $submitLabel }}</x-ui.button>
                    </div>
                </x-ui.odoo-form-ui>
            @else
                <x-ui.card title="User Details" class="border-0 shadow-sm">
                    @include('modules.access.users.partials.form-fields', [
                        'user' => $user,
                        'roles' => $roles,
                        'isCreate' => strtoupper($method) === 'POST',
                    ])

                    <x-slot name="footer">
                        <div class="d-flex justify-content-end gap-2">
                            <x-ui.button href="{{ route('access.users.index') }}" variant="light">Cancel</x-ui.button>
                            <x-ui.button type="submit" variant="primary" icon="feather-check-circle">{{ $submitLabel }}</x-ui.button>
                        </div>
                    </x-slot>
                </x-ui.card>
            @endif
        </form>
    </div>
</div>
