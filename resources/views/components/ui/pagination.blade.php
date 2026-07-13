@props([
    'currentPage' => 1,
    'totalPages' => 1,
    'totalResults' => 0,
    'perPage' => 10,
    'pageParam' => 'page',
    'tab' => null
])

@once
    @push('styles')
        <style>
            .erp-pagination-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 12px;
                margin-top: auto !important;
                padding-top: 15px;
                border-top: 1px solid #f1f5f9;
            }
            .erp-pagination {
                display: flex;
                align-items: center;
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
                border-radius: 50% !important; /* Circle instead of square */
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
                background-color: rgba(var(--bs-primary-rgb), 0.08);
                border-color: var(--bs-primary);
                color: var(--bs-primary);
            }
            .erp-pagination .page-item.active .page-link {
                background-color: var(--bs-primary) !important;
                border-color: var(--bs-primary) !important;
                color: #ffffff !important;
                box-shadow: 0 4px 10px rgba(var(--bs-primary-rgb), 0.2);
            }
            .erp-pagination .page-item.disabled .page-link {
                background-color: #f8fafc;
                border-color: #e2e8f0;
                color: #94a3b8;
                cursor: not-allowed;
            }
            .erp-pagination-info {
                font-size: 12px;
                color: #64748b;
            }
        </style>
    @endpush
@endonce

@php
    $activeTab = $tab ?: request()->query('tab');
    $queryParams = $activeTab ? ['tab' => $activeTab] : [];
@endphp

@if($totalPages > 1)
<div class="erp-pagination-container" {{ $attributes }}>
    <ul class="erp-pagination">
        <!-- Previous Page Link -->
        <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $currentPage <= 1 ? 'javascript:void(0);' : request()->fullUrlWithQuery(array_merge($queryParams, [$pageParam => $currentPage - 1])) }}" aria-label="Previous">
                <i class="feather-chevron-left"></i>
            </a>
        </li>

        <!-- Page Numbers -->
        @for ($i = 1; $i <= $totalPages; $i++)
            <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
                <a class="page-link" href="{{ request()->fullUrlWithQuery(array_merge($queryParams, [$pageParam => $i])) }}">{{ $i }}</a>
            </li>
        @endfor

        <!-- Next Page Link -->
        <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $currentPage >= $totalPages ? 'javascript:void(0);' : request()->fullUrlWithQuery(array_merge($queryParams, [$pageParam => $currentPage + 1])) }}" aria-label="Next">
                <i class="feather-chevron-right"></i>
            </a>
        </li>
    </ul>

    <div class="erp-pagination-info">
        Showing {{ min(($currentPage - 1) * $perPage + 1, $totalResults) }} to {{ min($currentPage * $perPage, $totalResults) }} of {{ $totalResults }} entries
    </div>
</div>
@endif
