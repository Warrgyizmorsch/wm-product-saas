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

            // Track last mouse down element to detect click outside
            var lastClickTarget = null;
            document.addEventListener('mousedown', function (e) {
                lastClickTarget = e.target;
            }, true);

            // Prevent closing when clicking outside the drawer
            drawerEl.addEventListener('hide.bs.offcanvas', function (e) {
                if (lastClickTarget) {
                    var clickedInside = drawerEl.contains(lastClickTarget);
                    var isCloseBtn = lastClickTarget.closest('[data-bs-dismiss="offcanvas"]');
                    if (!clickedInside && !isCloseBtn) {
                        e.preventDefault();
                    }
                }
            });

            // Clean up backdrop on hide if it gets stuck
            drawerEl.addEventListener('hidden.bs.offcanvas', function () {
                document.querySelectorAll('.offcanvas-backdrop').forEach(function (backdrop) {
                    backdrop.remove();
                });
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            });
        }
    })();
</script>
