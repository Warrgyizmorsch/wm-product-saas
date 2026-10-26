{{-- One look for the TDS filter bars: label above, equal-height controls, button on the same line. --}}
@push('styles')
    <style>
        .tds-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; }
        .tds-filters .tds-filter { display: flex; flex-direction: column; gap: 4px; }
        .tds-filters .tds-filter label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #64748b; margin: 0; }
        .tds-filters .form-select { height: 38px; min-width: 150px; font-size: 13px; padding-top: 0; padding-bottom: 0; }
        .tds-filters .tds-filter-wide .form-select { min-width: 240px; }
        .tds-filters .btn { height: 38px; padding: 0 18px; display: inline-flex; align-items: center; }
        .tds-filters .tds-filter-summary { margin-left: auto; text-align: right; align-self: center; }
        .tds-filters .tds-filter-actions { margin-left: auto; display: flex; gap: 8px; }
        @media (max-width: 576px) {
            .tds-filters .tds-filter, .tds-filters .form-select { width: 100%; }
            .tds-filters .tds-filter-summary, .tds-filters .tds-filter-actions { margin-left: 0; text-align: left; }
        }
    </style>
@endpush
