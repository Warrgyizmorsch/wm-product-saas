{{-- One sidebar entry (a link, or a group with its submenu). $item comes from MenuBuilder; $class adds classes to the <li>. --}}
@php
    $hasChildren = $item['children'] !== [];
@endphp
<li class="nxl-item {{ $hasChildren ? 'nxl-hasmenu' : '' }} {{ $item['active'] ? 'active nxl-trigger' : '' }} premium-module-child {{ $class ?? '' }}">
    <a href="{{ $hasChildren ? 'javascript:void(0);' : $item['url'] }}" class="nxl-link">
        <span class="nxl-micon"><i class="{{ $item['icon'] }}"></i></span>
        <span class="nxl-mtext">{{ $item['label'] }}</span>
        @if ($hasChildren)
            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
        @endif
    </a>
    @if ($hasChildren)
        <ul class="nxl-submenu">
            @foreach ($item['children'] as $child)
                <li class="nxl-item {{ $child['active'] ? 'active' : '' }}">
                    <a class="nxl-link" href="{{ $child['url'] }}">{{ $child['label'] }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</li>
