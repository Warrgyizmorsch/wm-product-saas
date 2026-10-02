{{--
    One sidebar entry (a link, or a group with its submenu). $item comes from MenuBuilder;
    $class adds classes to the <li>; $nested = true inside the current app's list, where
    entries render as text rows (no icon), like the reference's Accounting sub-items.
--}}
@php
    $hasChildren = $item['children'] !== [];
    $nested ??= false;
    $badge = $item['badge'] ?? null;
@endphp
<li class="nxl-item {{ $hasChildren ? 'nxl-hasmenu' : '' }} {{ $item['active'] ? 'active nxl-trigger' : '' }} {{ $class ?? '' }}">
    <a href="{{ $hasChildren ? 'javascript:void(0);' : $item['url'] }}" class="nxl-link">
        @unless ($nested)
            <span class="nxl-micon"><i class="{{ $item['icon'] }}"></i></span>
        @endunless
        <span class="nxl-mtext">{{ $item['label'] }}</span>
        @if ($badge !== null)
            <span class="ax-nav-badge">{{ $badge }}</span>
        @endif
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
