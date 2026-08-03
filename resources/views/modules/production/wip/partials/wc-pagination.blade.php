{{--
    Work-Center AJAX Pagination Partial
    Matches x-ui.pagination styling (circular page buttons) but fires loadWorkCenterBatchPage() JS.
    Variables: $paginator (LengthAwarePaginator), $orderId (int), $workCenterId (int)
--}}
@php
    $currentPage = $paginator->currentPage();
    $lastPage    = $paginator->lastPage();
    $total       = $paginator->total();
    $perPage     = $paginator->perPage();
    $from        = min(($currentPage - 1) * $perPage + 1, $total);
    $to          = min($currentPage * $perPage, $total);
@endphp

@if($lastPage > 1)
<div class="erp-pagination-container border-top">
    <ul class="erp-pagination">
        {{-- Previous --}}
        <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
            <button type="button" class="page-link"
                {{ $currentPage <= 1 ? 'disabled' : '' }}
                onclick="loadWorkCenterBatchPage({{ $orderId }}, {{ $workCenterId }}, {{ $currentPage - 1 }})"
                aria-label="Previous">
                <i class="feather-chevron-left"></i>
            </button>
        </li>

        {{-- Page numbers (show up to 5 around current page) --}}
        @php
            $start = max(1, $currentPage - 2);
            $end   = min($lastPage, $currentPage + 2);
        @endphp
        @if($start > 1)
            <li class="page-item">
                <button type="button" class="page-link" onclick="loadWorkCenterBatchPage({{ $orderId }}, {{ $workCenterId }}, 1)">1</button>
            </li>
            @if($start > 2)
                <li class="page-item disabled"><span class="page-link">…</span></li>
            @endif
        @endif

        @for($i = $start; $i <= $end; $i++)
            <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
                <button type="button" class="page-link" onclick="loadWorkCenterBatchPage({{ $orderId }}, {{ $workCenterId }}, {{ $i }})">{{ $i }}</button>
            </li>
        @endfor

        @if($end < $lastPage)
            @if($end < $lastPage - 1)
                <li class="page-item disabled"><span class="page-link">…</span></li>
            @endif
            <li class="page-item">
                <button type="button" class="page-link" onclick="loadWorkCenterBatchPage({{ $orderId }}, {{ $workCenterId }}, {{ $lastPage }})">{{ $lastPage }}</button>
            </li>
        @endif

        {{-- Next --}}
        <li class="page-item {{ $currentPage >= $lastPage ? 'disabled' : '' }}">
            <button type="button" class="page-link"
                {{ $currentPage >= $lastPage ? 'disabled' : '' }}
                onclick="loadWorkCenterBatchPage({{ $orderId }}, {{ $workCenterId }}, {{ $currentPage + 1 }})"
                aria-label="Next">
                <i class="feather-chevron-right"></i>
            </button>
        </li>
    </ul>
    <div class="erp-pagination-info">
        Showing {{ $from }} to {{ $to }} of {{ $total }} batches
    </div>
</div>
@endif
