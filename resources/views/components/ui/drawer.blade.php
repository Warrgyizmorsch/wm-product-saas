@props([
    'id',
    'title' => 'Drawer Title',
    'position' => 'end', // start, end, top, bottom
    'scroll' => false,
    'backdrop' => true
])

@php
    $backdropValue = 'true';
    if ($backdrop === false) {
        $backdropValue = 'false';
    } elseif ($backdrop === 'static') {
        $backdropValue = 'static';
    }
@endphp

<div class="offcanvas offcanvas-{{ $position }}" 
     {{ $scroll ? 'data-bs-scroll=true' : '' }} 
     data-bs-backdrop="{{ $backdropValue }}" 
     tabindex="-1" 
     id="{{ $id }}" 
     aria-labelledby="{{ $id }}Label" 
     {{ $attributes->merge(['class' => '']) }}>
     
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="{{ $id }}Label">{{ $title }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    
    <div class="offcanvas-body">
        {{ $slot }}
    </div>
    
    @if(isset($footer))
        <div class="p-3 border-top d-flex justify-content-end gap-2 bg-light">
            {{ $footer }}
        </div>
    @endif
</div>

<script>
    (function () {
        var drawerEl = document.getElementById('{{ $id }}');
        if (drawerEl) {
            document.body.appendChild(drawerEl);
        }
    })();
</script>
