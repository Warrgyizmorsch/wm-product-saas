{{--
    Content panel with a header row (the reference's "Income vs Expense" card).

    <x-ui.section title="Income vs Expense" subtitle="Last 6 months" badge="6 Months Trend">
        <x-slot:actions><a href="#" class="btn btn-sm btn-light">P&amp;L</a></x-slot:actions>
        ...body...
        <x-slot:footer>...</x-slot:footer>
    </x-ui.section>

    flush  true → body has no padding (for edge-to-edge tables)
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'badge' => null,
    'icon' => null,
    'flush' => false,
])

<section {{ $attributes->class(['ax-section']) }}>
    @if ($title || isset($actions))
        <div class="ax-section-head">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="ax-section-title">
                        @if ($icon)<i class="{{ $icon }} text-muted"></i>@endif
                        {{ $title }}
                        @if ($badge)<x-ui.chip tone="neutral">{{ $badge }}</x-ui.chip>@endif
                    </h2>
                @endif
                @if ($subtitle)
                    <p class="ax-section-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="d-flex flex-wrap align-items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['ax-section-body', 'p-0' => $flush])>
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="ax-section-foot">{{ $footer }}</div>
    @endisset
</section>
