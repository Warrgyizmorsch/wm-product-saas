@php
    $isModal = $modal ?? false;
@endphp

<div class="{{ $isModal ? '' : 'row' }}">
    <div class="{{ $isModal ? '' : 'col-xxl-8 col-xl-9 mx-auto' }}">
        <form action="{{ route('access.roles.store') }}" method="POST" @isset($formId) id="{{ $formId }}" @endisset>
            @csrf

            @if ($isModal)
                <x-ui.odoo-form-ui type="sheet" class="border-0 shadow-none p-0 mb-0">
                    @include('modules.access.roles.partials.form-fields')

                    <div class="pt-4 d-flex justify-content-end gap-2">
                        <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" icon="feather-check-circle">Create Role</x-ui.button>
                    </div>
                </x-ui.odoo-form-ui>
            @else
                <x-ui.card title="New Role" class="border-0 shadow-sm">
                    @include('modules.access.roles.partials.form-fields')

                    <x-slot name="footer">
                        <div class="d-flex justify-content-end gap-2">
                            <x-ui.button href="{{ route('access.roles.index') }}" variant="light">Cancel</x-ui.button>
                            <x-ui.button type="submit" variant="primary" icon="feather-check-circle">Create Role</x-ui.button>
                        </div>
                    </x-slot>
                </x-ui.card>
            @endif
        </form>
    </div>
</div>
