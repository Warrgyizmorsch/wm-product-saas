@props([
    'label' => 'Import Excel/CSV',
    'action' => '#',
    'buttonText' => 'Upload & Calculate',
    'fieldLabel' => null,
    'fieldName' => 'source',
    'fieldOptions' => [], // ['value' => 'Label']
    'icon' => 'feather-upload',
])

@once
    @push('styles')
        <style>
            .erp-upload-dropdown {
                position: relative !important;
            }
            .erp-upload-dropdown .dropdown-menu {
                position: absolute !important;
                top: 100% !important;
                right: 0 !important;
                left: auto !important;
                transform: none !important;
                display: none !important;
                min-width: 280px !important;
                padding: 16px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 10px !important;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
                margin-top: 5px !important;
                z-index: 1050 !important;
                background-color: #ffffff !important;
            }
            .erp-upload-dropdown .dropdown-menu.show {
                display: block !important;
            }
        </style>
    @endpush
@endonce

<div class="dropdown erp-upload-dropdown d-inline-block" {{ $attributes }}>
    <x-ui.icon-btn type="button"
                   variant="transparent-dark"
                   :icon="$icon"
                   title="{{ $label }}"
                   class="upload-dropdown-toggle-custom"
                   aria-expanded="false" />
    <div class="dropdown-menu dropdown-menu-end">
        <h6 class="fs-12 text-uppercase text-muted fw-bold mb-3">{{ $label }}</h6>
        <form action="{{ $action }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($fieldOptions)
                <select name="{{ $fieldName }}" class="form-select form-select-sm mb-2">
                    @if($fieldLabel)<option value="">{{ $fieldLabel }}</option>@endif
                    @foreach($fieldOptions as $value => $optionLabel)
                        <option value="{{ $value }}">{{ $optionLabel }}</option>
                    @endforeach
                </select>
            @endif
            <input type="file" name="file" class="form-control form-control-sm mb-3">
            <button type="submit" class="btn btn-primary btn-sm w-100">{{ $buttonText }}</button>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            $(document).ready(function() {
                $(document).on('click', '.upload-dropdown-toggle-custom', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var parent = $(this).closest('.erp-upload-dropdown');
                    var menu = parent.find('.dropdown-menu');

                    $('.dropdown-menu.show').not(menu).removeClass('show');
                    $('.dropdown.show').not(parent).removeClass('show');

                    parent.toggleClass('show');
                    menu.toggleClass('show');
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.erp-upload-dropdown').length) {
                        $('.erp-upload-dropdown .dropdown-menu.show').removeClass('show');
                        $('.erp-upload-dropdown.show').removeClass('show');
                    }
                });
            });
        </script>
    @endpush
@endonce
