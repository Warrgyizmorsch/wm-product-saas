@props([
    'column',
    'label',
])

@once
    @push('styles')
        <style>
            .sort-header-link {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                color: inherit;
                text-decoration: none;
                cursor: pointer;
                transition: color 0.15s ease-in-out;
            }
            .sort-header-link:hover {
                color: var(--bs-primary);
                text-decoration: underline;
            }
            .sort-header-link--active {
                color: #0f172a;
            }
            .sort-header-icon {
                font-size: 11px;
                line-height: 1;
            }
        </style>
    @endpush
@endonce

@php
    $currentSort = request('sort');
    $currentDirection = strtolower((string) request('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
    $isActive = $currentSort === $column;
    $nextDirection = $isActive && $currentDirection === 'asc' ? 'desc' : 'asc';
    $ariaSort = $isActive ? ($currentDirection === 'asc' ? 'ascending' : 'descending') : 'none';
    $url = request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => $nextDirection,
        'page' => 1,
    ]);
@endphp

<th {{ $attributes->merge(['class' => 'fw-bold text-dark']) }} aria-sort="{{ $ariaSort }}">
    <a href="{{ $url }}" class="sort-header-link {{ $isActive ? 'sort-header-link--active' : '' }}">
        <span>{{ $label }}</span>
        @if ($isActive)
            <i class="sort-header-icon">{{ $currentDirection === 'asc' ? '▲' : '▼' }}</i>
        @endif
    </a>
</th>
