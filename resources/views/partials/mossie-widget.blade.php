{{-- Mossie chat widget. Configured in config/services.php (MOSSIE_WIDGET_*); renders nothing when unset. --}}
@if (config('services.mossie.url') && config('services.mossie.key'))
    <script
        src="{{ config('services.mossie.url') }}"
        data-key="{{ config('services.mossie.key') }}"
        data-title="{{ config('services.mossie.title') }}"
        data-open="{{ filter_var(config('services.mossie.open'), FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false' }}"
        defer
    ></script>
@endif
