{{-- PDF / Excel export of the report on screen, keeping its current filters. --}}
@php
    $exportQuery = request()->query();
@endphp
<div class="d-flex gap-2">
    <a href="{{ route('accounting.reports.export', ['report' => $report, 'format' => 'pdf'] + $exportQuery) }}" class="btn btn-light-brand btn-sm">
        <i class="feather-file-text me-1"></i>PDF
    </a>
    <a href="{{ route('accounting.reports.export', ['report' => $report, 'format' => 'xlsx'] + $exportQuery) }}" class="btn btn-light-brand btn-sm">
        <i class="feather-grid me-1"></i>Excel
    </a>
</div>
