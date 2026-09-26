@props([
    'paginator' => null,
    'currentPage' => 1,
    'totalPages' => 1,
    'totalResults' => 0,
    'perPage' => 10,
    'pageParam' => 'page',
    'tab' => null,
    'onEachSide' => 2
])

@php
    if ($paginator instanceof \Illuminate\Contracts\Pagination\Paginator || $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
        $currentPage  = $paginator->currentPage();
        $totalPages   = method_exists($paginator, 'lastPage') ? $paginator->lastPage() : (method_exists($paginator, 'hasMorePages') && $paginator->hasMorePages() ? $currentPage + 1 : $currentPage);
        $totalResults = method_exists($paginator, 'total') ? $paginator->total() : $paginator->count();
        $perPage      = $paginator->perPage();
        $pageParam    = $paginator->getPageName();
    }

    $activeTab = $tab ?: request()->query('tab');
    $queryParams = $activeTab ? ['tab' => $activeTab] : [];

    // Calculate smart window around current page
    $start = max(1, $currentPage - $onEachSide);
    $end   = min($totalPages, $currentPage + $onEachSide);

    if ($currentPage <= $onEachSide + 1) {
        $end = min($totalPages, 1 + ($onEachSide * 2));
    } elseif ($currentPage >= $totalPages - $onEachSide) {
        $start = max(1, $totalPages - ($onEachSide * 2));
    }

    $from = $totalResults > 0 ? min(($currentPage - 1) * $perPage + 1, $totalResults) : 0;
    $to   = min($currentPage * $perPage, $totalResults);
@endphp

@once
    <style>
        .erp-pagination-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: auto !important;
            padding-top: 15px;
            padding-bottom: 5px;
            border-top: 1px solid #f1f5f9;
        }
        .erp-pagination {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px;
            margin-bottom: 0;
            padding-left: 0;
            list-style: none;
        }
        .erp-pagination .page-item {
            display: inline-block;
        }
        .erp-pagination .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50% !important;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            cursor: pointer;
        }
        .erp-pagination .page-link:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent);
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }
        .erp-pagination .page-item.active .page-link {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 10px color-mix(in srgb, var(--bs-primary) 20%, transparent);
        }
        .erp-pagination .page-item.disabled .page-link {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }
        .erp-pagination .page-item.ellipsis .page-link {
            background: transparent !important;
            border-color: transparent !important;
            color: #64748b !important;
            cursor: default !important;
            width: 28px;
            box-shadow: none !important;
        }
        .erp-pagination-info {
            font-size: 12px;
            color: #64748b;
        }

        /* Dark Mode Support */
        html.app-skin-dark .erp-pagination-container {
            border-top-color: #1b2436 !important;
        }
        html.app-skin-dark .erp-pagination .page-link {
            border-color: #283c50 !important;
            background-color: #1c2438 !important;
            color: #cbd5e1 !important;
        }
        html.app-skin-dark .erp-pagination .page-link:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 20%, #1c2438) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }
        html.app-skin-dark .erp-pagination .page-item.active .page-link {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 10px color-mix(in srgb, var(--bs-primary) 35%, transparent) !important;
        }
        html.app-skin-dark .erp-pagination .page-item.disabled .page-link {
            background-color: #121a2d !important;
            border-color: #1b2436 !important;
            color: #475569 !important;
        }
        html.app-skin-dark .erp-pagination .page-item.ellipsis .page-link {
            background: transparent !important;
            border-color: transparent !important;
            color: #94a3b8 !important;
        }
        html.app-skin-dark .erp-pagination-info {
            color: #94a3b8 !important;
        }
    </style>
@endonce

@if($totalPages > 1)
<div class="erp-pagination-container" {{ $attributes }}>
    <ul class="erp-pagination">
        <!-- Previous Page Link -->
        <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $currentPage <= 1 ? 'javascript:void(0);' : request()->fullUrlWithQuery(array_merge($queryParams, [$pageParam => $currentPage - 1])) }}" aria-label="Previous">
                <i class="feather-chevron-left"></i>
            </a>
        </li>

        <!-- First Page Link (if start > 1) -->
        @if($start > 1)
            <li class="page-item">
                <a class="page-link" href="{{ request()->fullUrlWithQuery(array_merge($queryParams, [$pageParam => 1])) }}">1</a>
            </li>
            @if($start > 2)
                <li class="page-item ellipsis disabled">
                    <span class="page-link">…</span>
                </li>
            @endif
        @endif

        <!-- Windowed Page Numbers -->
        @for ($i = $start; $i <= $end; $i++)
            <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
                <a class="page-link" href="{{ request()->fullUrlWithQuery(array_merge($queryParams, [$pageParam => $i])) }}">{{ $i }}</a>
            </li>
        @endfor

        <!-- Last Page Link (if end < totalPages) -->
        @if($end < $totalPages)
            @if($end < $totalPages - 1)
                <li class="page-item ellipsis disabled">
                    <span class="page-link">…</span>
                </li>
            @endif
            <li class="page-item">
                <a class="page-link" href="{{ request()->fullUrlWithQuery(array_merge($queryParams, [$pageParam => $totalPages])) }}">{{ $totalPages }}</a>
            </li>
        @endif

        <!-- Next Page Link -->
        <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $currentPage >= $totalPages ? 'javascript:void(0);' : request()->fullUrlWithQuery(array_merge($queryParams, [$pageParam => $currentPage + 1])) }}" aria-label="Next">
                <i class="feather-chevron-right"></i>
            </a>
        </li>
    </ul>

    <div class="erp-pagination-info">
        Showing {{ $from }} to {{ $to }} of {{ $totalResults }} entries
    </div>
</div>
@endif
