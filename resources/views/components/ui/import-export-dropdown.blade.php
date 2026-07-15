@props([
    'type',
    'label' => '',
    'exportRoute' => null,
    'downloadTemplateRoute' => null,
    'importModalTarget' => null,
])

@once
    @push('styles')
        <style>
            .erp-import-export-dropdown {
                position: relative !important;
            }
            .erp-import-export-dropdown .dropdown-menu {
                position: absolute !important;
                top: 100% !important;
                right: 0 !important;
                left: auto !important;
                transform: none !important;
                display: none !important;
                min-width: 180px !important;
                padding: 6px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
                margin-top: 5px !important;
                z-index: 1050 !important;
                background-color: #ffffff !important;
            }
            .erp-import-export-dropdown .dropdown-menu.show {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            .erp-import-export-dropdown .dropdown-item {
                display: flex !important;
                align-items: center !important;
                padding: 8px 12px !important;
                font-size: 13px !important;
                color: #475569 !important;
                border-radius: 6px !important;
                transition: all 0.2s ease-in-out !important;
                background: transparent !important;
                border: none !important;
                width: 100% !important;
                text-align: left !important;
            }
            .erp-import-export-dropdown .dropdown-item:hover {
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
            }
        </style>
    @endpush
@endonce

<div class="dropdown erp-import-export-dropdown d-inline-block" {{ $attributes }}>
    <x-ui.icon-btn type="button" 
                   variant="transparent-dark"
                   title="Import / Export Options"
                   size="md"
                   icon="feather-paperclip"
                   class="import-export-toggle-custom"
                   aria-expanded="false">
        @if($label)
            <span>{{ $label }}</span>
        @endif
    </x-ui.icon-btn>
    <ul class="dropdown-menu dropdown-menu-end fs-13 shadow-lg">
        <li>
            <a href="{{ $exportRoute ?? route('production.import-export.export', $type) . '?' . http_build_query(request()->all()) }}" class="dropdown-item">
                <i class="feather-download me-2 text-muted fs-12"></i>Export Excel
            </a>
        </li>
        <li>
            <a href="{{ $downloadTemplateRoute ?? route('production.import-export.download-template', $type) }}" class="dropdown-item">
                <i class="feather-file-text me-2 text-muted fs-12"></i>Download Template
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a href="javascript:void(0);" class="dropdown-item" data-bs-toggle="modal" data-bs-target="{{ $importModalTarget ?? '#import' . \Illuminate\Support\Str::studly($type) . 'Modal' }}">
                <i class="feather-upload me-2 text-muted fs-12"></i>Import
            </a>
        </li>
    </ul>
</div>

@once
    @push('scripts')
        <script>
            $(document).ready(function() {
                // Custom toggle handler to prevent Bootstrap double-event conflict
                $(document).on('click', '.import-export-toggle-custom', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var parent = $(this).closest('.erp-import-export-dropdown');
                    var menu = parent.find('.dropdown-menu');
                    
                    // Close other open dropdowns
                    $('.dropdown-menu.show').not(menu).removeClass('show');
                    $('.dropdown.show').not(parent).removeClass('show');
                    
                    parent.toggleClass('show');
                    menu.toggleClass('show');
                });
                
                // Close dropdown when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.erp-import-export-dropdown').length) {
                        $('.erp-import-export-dropdown .dropdown-menu.show').removeClass('show');
                        $('.erp-import-export-dropdown.show').removeClass('show');
                    }
                });
            });
        </script>
    @endpush
@endonce
