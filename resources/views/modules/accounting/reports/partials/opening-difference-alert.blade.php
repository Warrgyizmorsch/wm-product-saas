{{-- Openings that don't agree leave a balance on Opening Balance Difference,
     which otherwise sits silently inside capital. Expects $openingDifference. --}}
@if (abs((float) ($openingDifference ?? 0)) >= 0.01)
    <x-ui.alert variant="warning" icon="feather-alert-triangle" class="mb-4">
        <strong>Opening balances don't agree.</strong>
        Opening Balance Difference (3050) carries {{ number_format(abs($openingDifference), 2) }}
        {{ $openingDifference > 0 ? 'Cr' : 'Dr' }}, which is included in capital.
        Check the opening balances of ledgers, customers and suppliers until this is zero.
    </x-ui.alert>
@endif
