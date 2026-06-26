@props([
    'title' => null,
    'bodyClass' => null,
    'stretch' => false
])

<div {{ $attributes->class([
    'card',
    'stretch stretch-full' => $stretch
]) }}>
    @if($title || isset($headerAction))
        <div class="card-header">
            @if($title)
                <h5 class="card-title">{{ $title }}</h5>
            @endif
            @if(isset($headerAction))
                <div class="card-header-action">
                    {{ $headerAction }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $bodyClass ? 'card-body ' . $bodyClass : 'card-body' }}">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endif
</div>