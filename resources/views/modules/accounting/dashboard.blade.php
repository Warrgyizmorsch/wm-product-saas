@extends('layouts.duralux')

@section('title', 'Accounting Dashboard | SaaS ERP')
@section('page-title', 'Accounting Dashboard')
@section('breadcrumb', 'Accounting / Dashboard')

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <div class="dropdown">
            <button class="btn btn-light-brand dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="feather-download me-2"></i>Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('accounting.dashboard.export', ['format' => 'pdf'] + $query) }}"><i class="feather-file-text me-2"></i>PDF</a></li>
                <li><a class="dropdown-item" href="{{ route('accounting.dashboard.export', ['format' => 'xlsx'] + $query) }}"><i class="feather-grid me-2"></i>Excel</a></li>
            </ul>
        </div>
        @if ($canPostJournals)
            <a href="{{ route('accounting.journals.create') }}" class="btn btn-primary">
                <i class="feather-plus me-2"></i>New Journal
            </a>
        @endif
        @include('partials.dashboard.actions')
    </div>
@endsection

@section('content')
    @php
        // Ledger amounts carry no symbol, like every Accounting screen — the layout
        // shows "Amounts in <company currency>" once per page.
        $mixedCurrencies = $currencyNote && str_starts_with($currencyNote, 'Warning');
    @endphp

    {{-- Filters: they apply to every block on the page. --}}
    <x-ui.card class="mb-3">
        <x-ui.filter-toolbar formId="dashboard-period-form" :resetUrl="route('accounting.dashboard')">
            <input type="hidden" name="view" value="{{ $activeView }}">
            <x-ui.filter-field label="Period" col="col-md-2">
                <select name="preset" id="preset" class="form-select form-select-sm">
                    @foreach ($presets as $value => $label)
                        <option value="{{ $value }}" @selected($period->preset === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-ui.filter-field>
            <x-ui.filter-field label="From" col="col-md-2" class="custom-range {{ $period->preset === 'custom' ? '' : 'd-none' }}">
                <input type="date" name="from" id="from" class="form-control form-control-sm" value="{{ $period->from->toDateString() }}">
            </x-ui.filter-field>
            <x-ui.filter-field label="To" col="col-md-2" class="custom-range {{ $period->preset === 'custom' ? '' : 'd-none' }}">
                <input type="date" name="to" id="to" class="form-control form-control-sm" value="{{ $period->to->toDateString() }}">
            </x-ui.filter-field>
            @if ($companyCount > 1)
                <x-ui.filter-field label="Companies" col="col-md-2">
                    <select name="company_scope" id="company_scope" class="form-select form-select-sm">
                        <option value="current" @selected(! $filters['consolidated'])>{{ company()?->company_name ?? 'Selected company' }}</option>
                        <option value="all" @selected($filters['consolidated'])>All companies (consolidated)</option>
                    </select>
                </x-ui.filter-field>
            @endif
            @if ($costCenters->isNotEmpty())
                <x-ui.filter-field label="Cost center" col="col-md-2">
                    <select name="cost_center_id" id="cost_center_id" class="form-select form-select-sm">
                        <option value="">All cost centers</option>
                        @foreach ($costCenters as $costCenter)
                            <option value="{{ $costCenter->id }}" @selected($filters['cost_center']?->id === $costCenter->id)>{{ $costCenter->code }} — {{ $costCenter->name }}</option>
                        @endforeach
                    </select>
                </x-ui.filter-field>
            @endif
        </x-ui.filter-toolbar>
        <div class="text-end fs-12 text-muted mt-2 pt-2 border-top">
            <strong class="text-dark">{{ $period->label() }}</strong> ({{ $period->days() }} days) · vs {{ $period->previousLabel() }}
            · Figures as of {{ ($generatedAt ?? now())->format('H:i') }}
            · <a href="{{ route('accounting.dashboard', $query + ['refresh' => 1]) }}"><i class="feather-refresh-cw" style="vertical-align: middle;"></i> Refresh</a>
        </div>
    </x-ui.card>

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

            const presetSelect = document.getElementById('preset');
            if (presetSelect) {
                presetSelect.addEventListener('change', function () {
                    const isCustom = this.value === 'custom';
                    document.querySelectorAll('#dashboard-period-form .custom-range').forEach((el) => el.classList.toggle('d-none', !isCustom));
                    if (!isCustom) {
                        this.form.submit();
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
