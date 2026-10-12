@extends('layouts.duralux')

@section('title', 'Accounting Dashboard | SaaS ERP')
@section('page-title', 'Accounting Dashboard')
@section('breadcrumb', 'Accounting / Dashboard')

@if ($booksClosedThrough)
    @section('page-badge')
        <span class="ax-ref ax-tone-positive" title="Periods up to here are closed — nothing more can post into them">Books Closed: {{ $booksClosedThrough->format('M Y') }}</span>
    @endsection
@endif

@if ($canPostJournals)
    @section('header-cta')
        <a href="{{ route('accounting.journals.create') }}" class="btn btn-primary ax-create-btn"><i class="feather-plus"></i><span class="d-none d-sm-inline">New Journal</span></a>
    @endsection
@endif

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap align-items-center">
        {{-- Figures come from a cached summary; say how fresh they are and let people refresh. --}}
        <span class="ax-sync-pill d-none d-xl-inline-flex">
            <span class="ax-dot is-live"></span>
            Last sync <strong class="ax-mono">{{ ($generatedAt ?? now())->format('h:i A') }}</strong>
            <a href="{{ route('accounting.dashboard', $query + ['refresh' => 1]) }}" class="ax-sync-refresh" title="Refresh now"><i class="feather-refresh-cw"></i></a>
        </span>
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="feather-download"></i>Export<i class="feather-chevron-down"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('accounting.dashboard.export', ['format' => 'pdf'] + $query) }}"><i class="feather-file-text me-2"></i>PDF</a></li>
                <li><a class="dropdown-item" href="{{ route('accounting.dashboard.export', ['format' => 'xlsx'] + $query) }}"><i class="feather-grid me-2"></i>Excel</a></li>
            </ul>
        </div>
        <button type="button" class="btn btn-light btn-icon" onclick="window.print()" title="Print"><i class="feather-printer"></i></button>
        @include('partials.dashboard.actions')
    </div>
@endsection

@section('content')
    @php
        // Ledger amounts carry no symbol, like every Accounting screen — the layout
        // shows "Amounts in <company currency>" once per page.
        $mixedCurrencies = $currencyNote && str_starts_with($currencyNote, 'Warning');
        // The one period control: a pill per preset (keeping the other filters), plus Custom.
        $quickPresets = ['this_month' => 'MTD', 'last_month' => 'Last Month', 'this_quarter' => 'QTD', 'fiscal_year' => 'YTD'];
        $presetUrl = fn (string $preset) => route('accounting.dashboard', array_diff_key($query, array_flip(['preset', 'from', 'to'])) + ['preset' => $preset]);
        $presetRange = fn (string $preset) => \App\Domains\Accounting\Support\DashboardPeriod::resolve(['preset' => $preset], $today, $fiscalYearStart)->label();
        $isCustom = $period->preset === 'custom';
    @endphp

    {{-- Filters: they apply to every block on the page. --}}
    <x-ui.filter-panel formId="dashboard-period-form" :resetUrl="route('accounting.dashboard')" submitLabel="Filter Ledger">
        <input type="hidden" name="view" value="{{ $activeView }}">
        <div class="col-auto">
            <label class="form-label d-block">Accounting Period</label>
            {{-- Pills switch straight away; Custom opens the date fields, applied with Filter Ledger. --}}
            <input type="hidden" name="preset" id="preset" value="{{ $period->preset }}">
            <div class="ax-segmented" role="group" aria-label="Accounting period">
                @foreach ($quickPresets as $key => $label)
                    <a href="{{ $presetUrl($key) }}" title="{{ $presets[$key] }}: {{ $presetRange($key) }}"
                       @class(['active' => $period->preset === $key]) @if ($period->preset === $key) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
                <button type="button" id="custom-period-toggle" @class(['active' => $isCustom]) aria-expanded="{{ $isCustom ? 'true' : 'false' }}">Custom</button>
            </div>
        </div>
        <div class="col-sm-3 col-lg-auto custom-range {{ $isCustom ? '' : 'd-none' }}">
            <label class="form-label" for="from">From</label>
            <input type="date" name="from" id="from" class="form-control" value="{{ $period->from->toDateString() }}" @disabled(! $isCustom)>
        </div>
        <div class="col-sm-3 col-lg-auto custom-range {{ $isCustom ? '' : 'd-none' }}">
            <label class="form-label" for="to">To</label>
            <input type="date" name="to" id="to" class="form-control" value="{{ $period->to->toDateString() }}" @disabled(! $isCustom)>
        </div>
        @if ($companyCount > 1)
            <div class="col-sm-6 col-lg-auto">
                <label class="form-label" for="company_scope">Companies</label>
                <div class="ax-select-chip">
                    <i class="feather-briefcase"></i>
                    <select name="company_scope" id="company_scope" class="form-select">
                        <option value="current" @selected(! $filters['consolidated'])>{{ company()?->company_name ?? 'Selected company' }}</option>
                        <option value="all" @selected($filters['consolidated'])>All companies (consolidated)</option>
                    </select>
                </div>
            </div>
        @endif
        @if ($costCenters->isNotEmpty())
            <div class="col-sm-6 col-lg-auto">
                <label class="form-label" for="cost_center_id">Cost Center</label>
                <div class="ax-select-chip">
                    <i class="feather-layers"></i>
                    <select name="cost_center_id" id="cost_center_id" class="form-select">
                        <option value="">All Cost Centers (Consolidated)</option>
                        @foreach ($costCenters as $costCenter)
                            <option value="{{ $costCenter->id }}" @selected($filters['cost_center']?->id === $costCenter->id)>{{ $costCenter->code }} — {{ $costCenter->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        <x-slot:meta>
            <span class="d-inline-flex flex-wrap align-items-center gap-2">
                <span class="ax-ref ax-tone-brand">{{ $period->preset === 'custom' ? 'CUSTOM' : 'LIVE' }}</span>
                <span class="text-subtle">·</span>
                <span>Current snapshot: <strong class="text-dark fw-medium">{{ $period->presetLabel() }} · {{ $period->label() }} ({{ $period->days() }} {{ \Illuminate\Support\Str::plural('day', $period->days()) }})</strong></span>
                <span class="ax-footer-sep">|</span>
                <span>Compared with: <span class="fw-medium">{{ $period->previousLabel() }}</span></span>
            </span>
            <span class="d-inline-flex align-items-center gap-2">
                <span class="ax-overline" style="font-size: 11px;">Ledger:</span>
                <span class="ax-ref">{{ $filters['consolidated'] ? 'ALL COMPANIES' : (company()?->company_name ?? '—') }}{{ $filters['cost_center'] ? ' · '.$filters['cost_center']->code : '' }}</span>
            </span>
        </x-slot:meta>
    </x-ui.filter-panel>

    @if ($mixedCurrencies)
        <x-ui.alert variant="warning" icon="feather-alert-triangle" class="mb-3">{{ $currencyNote }}</x-ui.alert>
    @endif
    @if ($filters['cost_center'])
        <div class="alert alert-info fs-13 py-2 mb-3">
            <i class="feather-filter me-1"></i>
            Showing <strong>{{ $filters['cost_center']->code }} — {{ $filters['cost_center']->name }}</strong> for revenue, expenses, trend and budgets. Cash, receivables, payables, ratios and the close checklist are for the whole {{ $filters['consolidated'] ? 'group' : 'company' }}.
        </div>
    @endif

    {{-- Everything below the filters is a widget: rearrange, add or remove them with Customize. --}}
    @include('partials.dashboard.grid')
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            // The widgets read the page's own filters.
            window.dashboardBaseQuery = () => @json($widgetQuery);

            // Custom: show the date fields and send preset=custom with them;
            // closing it again goes back to the preset the page is showing.
            const customToggle = document.getElementById('custom-period-toggle');
            const presetInput = document.getElementById('preset');
            const shownPreset = presetInput ? presetInput.value : 'this_month';
            if (customToggle && presetInput) {
                customToggle.addEventListener('click', function () {
                    const open = this.getAttribute('aria-expanded') !== 'true';
                    this.setAttribute('aria-expanded', open ? 'true' : 'false');
                    this.classList.toggle('active', open);
                    presetInput.value = open ? 'custom' : (shownPreset === 'custom' ? 'this_month' : shownPreset);
                    document.querySelectorAll('#dashboard-period-form .custom-range').forEach((el) => {
                        el.classList.toggle('d-none', !open);
                        el.querySelectorAll('input').forEach((input) => { input.disabled = !open; });
                    });
                    if (open) {
                        document.getElementById('from')?.focus();
                    }
                });
            }
            ['company_scope', 'cost_center_id'].forEach((id) => {
                const select = document.getElementById(id);
                if (select) {
                    select.addEventListener('change', function () { this.form.submit(); });
                }
            });
        })();
    </script>
@endpush
