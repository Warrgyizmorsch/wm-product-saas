@extends('layouts.duralux')

@section('title', 'Historical Analytics & Trend Analysis | SaaS ERP')
@section('page-title', 'Historical Intelligence & Trends')
@section('breadcrumb', 'Trend Analytics')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        {{-- Filters form --}}
        <form method="GET" action="{{ route('production.intelligence.analytics') }}" class="row g-3 mb-4 pb-4 border-bottom align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">Machine</label>
                <select name="machine_id" class="form-select">
                    <option value="">All Machines</option>
                    @foreach($machines as $m)
                        <option value="{{ $m->id }}" {{ request('machine_id') == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Work Center</label>
                <select name="work_center_id" class="form-select">
                    <option value="">All Work Centers</option>
                    @foreach($workCenters as $wc)
                        <option value="{{ $wc->id }}" {{ request('work_center_id') == $wc->id ? 'selected' : '' }}>{{ $wc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Period Grouping</label>
                <select name="period" class="form-select">
                    <option value="daily" {{ request('period') === 'daily' ? 'selected' : '' }}>Daily</option>
                    <option value="weekly" {{ request('period') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Date Range</label>
                <input type="date" name="date_start" class="form-control" value="{{ request('date_start', today()->subDays(6)->toDateString()) }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="feather-filter me-2"></i>Analyze</button>
            </div>
        </form>

        {{-- Trends Data Grid --}}
        <div class="row g-4">
            {{-- OEE & Availability Trend points table --}}
            <div class="col-md-6">
                <div class="card border border-light shadow-sm">
                    <div class="card-header bg-light py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-trending-up me-2 text-primary"></i>Historical OEE Trends</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Interval</th>
                                        <th>OEE %</th>
                                        <th>Availability %</th>
                                        <th>Performance %</th>
                                        <th>Quality %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($oeeTrend['labels'] as $index => $label)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $label }}</td>
                                            <td class="text-primary fw-bold">{{ $oeeTrend['datasets'][0]['data'][$index] }}%</td>
                                            <td>{{ $oeeTrend['datasets'][1]['data'][$index] }}%</td>
                                            <td>{{ $oeeTrend['datasets'][2]['data'][$index] }}%</td>
                                            <td class="text-success">{{ $oeeTrend['datasets'][3]['data'][$index] }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Production vs Plan trend points --}}
            <div class="col-md-6">
                <div class="card border border-light shadow-sm">
                    <div class="card-header bg-light py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-bar-chart-2 me-2 text-info"></i>Planned vs Actual Output</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Interval</th>
                                        <th>Planned Qty</th>
                                        <th>Actual Qty</th>
                                        <th>Variance Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($prodTrend['labels'] as $index => $label)
                                        @php
                                            $planned = $prodTrend['datasets'][0]['data'][$index];
                                            $actual = $prodTrend['datasets'][1]['data'][$index];
                                            $variance = $actual - $planned;
                                        @endphp
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $label }}</td>
                                            <td>{{ $planned }}</td>
                                            <td>{{ $actual }}</td>
                                            <td class="{{ $variance >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold' }}">
                                                {{ $variance >= 0 ? '+' : '' }}{{ $variance }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
