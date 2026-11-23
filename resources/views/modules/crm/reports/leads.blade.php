@extends('layouts.duralux')

@section('title', 'Lead Reports | CRM | SaaS ERP')
@section('page-title', 'Lead Reports')
@section('breadcrumb', 'CRM / Reports / Leads')

@section('page-actions')
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="fs-12 text-muted mb-0" for="days">Created in last</label>
        <select name="days" id="days" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach([30, 90, 180, 365] as $d)
                <option value="{{ $d }}" @selected($days === $d)>{{ $d }} days</option>
            @endforeach
        </select>
    </form>
@endsection

@section('content')
@php
    $fmt = fn ($v) => function_exists('format_currency') ? format_currency($v) : number_format((float) $v, 2);
    $rt = $responseTime['overall'];
@endphp
<div class="row g-3">

    {{-- Response time --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Response time (assignment to first real response)</div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-6 col-md-2"><div class="fs-11 text-muted">Assigned</div><div class="fw-bold fs-5">{{ $rt['assigned'] }}</div></div>
                    <div class="col-6 col-md-2"><div class="fs-11 text-muted">Responded</div><div class="fw-bold fs-5">{{ $rt['responded'] }}</div></div>
                    <div class="col-6 col-md-2"><div class="fs-11 text-muted">Avg (min)</div><div class="fw-bold fs-5">{{ $rt['avg_minutes'] ?? '—' }}</div></div>
                    <div class="col-6 col-md-2"><div class="fs-11 text-muted">Median (min)</div><div class="fw-bold fs-5">{{ $rt['median_minutes'] ?? '—' }}</div></div>
                    <div class="col-6 col-md-2"><div class="fs-11 text-muted">Within SLA</div><div class="fw-bold fs-5">{{ isset($rt['within_sla_pct']) ? $rt['within_sla_pct'] . '%' : '—' }}</div></div>
                    <div class="col-6 col-md-2"><div class="fs-11 text-muted">SLA breached / awaiting</div><div class="fw-bold fs-5">{{ $rt['breached'] }} / {{ $rt['awaiting'] }}</div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Salesperson</th><th class="text-end">Assigned</th><th class="text-end">Responded</th><th class="text-end">Avg min</th><th class="text-end">Median min</th><th class="text-end">Within SLA</th><th class="text-end">Breached</th><th class="text-end">Awaiting</th></tr></thead>
                        <tbody>
                        @forelse($responseTime['rows'] as $r)
                            <tr><td>{{ $r['owner'] }}</td><td class="text-end">{{ $r['assigned'] }}</td><td class="text-end">{{ $r['responded'] }}</td><td class="text-end">{{ $r['avg_minutes'] ?? '—' }}</td><td class="text-end">{{ $r['median_minutes'] ?? '—' }}</td><td class="text-end">{{ isset($r['within_sla_pct']) ? $r['within_sla_pct'] . '%' : '—' }}</td><td class="text-end">{{ $r['breached'] }}</td><td class="text-end">{{ $r['awaiting'] }}</td></tr>
                        @empty
                            <tr><td colspan="8" class="text-muted text-center">No assigned leads in this period.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Funnel --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Funnel conversion ({{ $funnel['total'] }} leads, {{ $funnel['conversion_pct'] }}% won)</div>
            <div class="card-body">
                @forelse($funnel['stages'] as $st)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between fs-12"><span>{{ $st['stage'] }}</span><span>{{ $st['count'] }} ({{ $st['pct'] }}%)</span></div>
                        <div class="progress" style="height:8px"><div class="progress-bar" style="width: {{ $st['pct'] }}%"></div></div>
                    </div>
                @empty
                    <div class="text-muted">No leads in this period.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Win / loss --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Win and loss reasons (Won {{ $winLoss['won'] }}, Lost {{ $winLoss['lost'] }})</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Loss reason</th><th class="text-end">Leads</th></tr></thead>
                    <tbody>
                    @forelse($winLoss['by_reason'] as $r)
                        <tr><td>{{ $r['reason'] }}</td><td class="text-end">{{ $r['count'] }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted text-center">No lost leads in this period.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Ageing --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Ageing of open leads ({{ $ageing['open_total'] }} open, {{ $ageing['on_hold'] }} on hold)</div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Salesperson</th>@foreach(array_keys($ageing['overall']) as $b)<th class="text-end">{{ $b }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($ageing['by_owner'] as $owner => $cols)
                        <tr><td>{{ $owner }}</td>@foreach($cols as $c)<td class="text-end">{{ $c }}</td>@endforeach</tr>
                    @endforeach
                    <tr class="fw-bold"><td>All</td>@foreach($ageing['overall'] as $c)<td class="text-end">{{ $c }}</td>@endforeach</tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Forecast --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Forecast by salesperson (open leads by expected sale month; open deals weighted by probability)</div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Salesperson</th>@foreach($forecast['months'] as $m)<th class="text-end">{{ $m }}</th>@endforeach<th class="text-end">Leads total</th><th class="text-end">Deals total</th><th class="text-end">Deals weighted</th></tr></thead>
                    <tbody>
                    @forelse($forecast['rows'] as $name => $row)
                        <tr>
                            <td>{{ $name }}</td>
                            @foreach($forecast['months'] as $m)<td class="text-end">{{ $fmt($row['leads'][$m] ?? 0) }}</td>@endforeach
                            <td class="text-end fw-semibold">{{ $fmt($row['lead_total'] ?? 0) }}</td>
                            <td class="text-end">{{ $fmt($row['deal_total'] ?? 0) }}</td>
                            <td class="text-end fw-semibold">{{ $fmt($row['deal_weighted'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($forecast['months']) + 4 }}" class="text-muted text-center">No open leads or deals.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
