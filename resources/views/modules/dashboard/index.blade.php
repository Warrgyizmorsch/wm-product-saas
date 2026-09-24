@extends('layouts.duralux')

@section('title', __('ui.dashboard') . ' | SaaS ERP')
@section('page-title', __('ui.executive_dashboard'))
@section('breadcrumb', __('ui.executive_dashboard'))

@section('page-actions')
    @include('partials.dashboard.actions')
@endsection

@section('content')
    <div class="card mb-3" id="dash-filters">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted small">Showing</span>
            <select id="dash-f-preset" class="form-select form-select-sm" style="width:auto">
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <span id="dash-f-custom" class="align-items-center gap-2" style="display:none">
                <input type="date" id="dash-f-from" class="form-control form-control-sm" style="width:auto">
                <span class="text-muted">to</span>
                <input type="date" id="dash-f-to" class="form-control form-control-sm" style="width:auto">
            </span>
            @if ($multiCompany)
                <select id="dash-f-scope" class="form-select form-select-sm" style="width:auto">
                    <option value="current">Selected company</option>
                    <option value="all">All companies</option>
                </select>
            @endif
        </div>
    </div>

    @include('partials.dashboard.grid')
@endsection

@push('scripts')
    <script>
        // The common dashboard's filter (period + company scope), remembered per browser.
        (function () {
            const FILTER_KEY = 'dash-filters';
            const filters = {preset: 'this_month', from: '', to: '', scope: 'current'};
            try { Object.assign(filters, JSON.parse(localStorage.getItem(FILTER_KEY) || '{}')); } catch (e) { /* storage unavailable */ }
            if (!Object.keys(@json($periods)).includes(filters.preset)) filters.preset = 'this_month';

            const el = id => document.getElementById(id);
            window.dashboardBaseQuery = () => ({preset: filters.preset, from: filters.from, to: filters.to, scope: filters.scope});

            function sync(reload) {
                el('dash-f-preset').value = filters.preset;
                el('dash-f-custom').style.display = filters.preset === 'custom' ? 'flex' : 'none';
                el('dash-f-from').value = filters.from; el('dash-f-to').value = filters.to;
                if (el('dash-f-scope')) el('dash-f-scope').value = filters.scope;
                try { localStorage.setItem(FILTER_KEY, JSON.stringify(filters)); } catch (e) { /* storage unavailable */ }
                if (reload && window.dashboardReload) window.dashboardReload();
            }

            el('dash-f-preset').onchange = e => { filters.preset = e.target.value; sync(filters.preset !== 'custom' || (filters.from && filters.to)); };
            el('dash-f-from').onchange = e => { filters.from = e.target.value; if (filters.from && filters.to) sync(true); };
            el('dash-f-to').onchange = e => { filters.to = e.target.value; if (filters.from && filters.to) sync(true); };
            if (el('dash-f-scope')) el('dash-f-scope').onchange = e => { filters.scope = e.target.value; sync(true); };

            sync(false);
        })();
    </script>
@endpush
