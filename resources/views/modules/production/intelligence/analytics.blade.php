@extends('layouts.duralux')

@section('title', __('production.historical_analytics_trends') . ' | SaaS ERP')
@section('page-title', __('production.historical_intelligence_trends'))
@section('breadcrumb', __('production.trend_analytics'))

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        {{-- Filters form with Odoo UI Components --}}
        <form method="GET" action="{{ route('production.intelligence.analytics') }}" class="row g-3 mb-4 pb-4 border-bottom align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">{{ __('production.col_machine') ?? 'Machine' }}</label>
                <x-ui.odoo-form-ui type="select" name="machine_id">
                    <option value="">{{ __('production.all_machines') }}</option>
                    @foreach($machines as $m)
                        <option value="{{ $m->id }}" {{ request('machine_id') == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">{{ __('production.col_work_center') }}</label>
                <x-ui.odoo-form-ui type="select" name="work_center_id">
                    <option value="">{{ __('production.all_work_centers') }}</option>
                    @foreach($workCenters as $wc)
                        <option value="{{ $wc->id }}" {{ request('work_center_id') == $wc->id ? 'selected' : '' }}>{{ $wc->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">{{ __('production.period_grouping') }}</label>
                <x-ui.odoo-form-ui type="select" name="period">
                    <option value="daily" {{ request('period') === 'daily' ? 'selected' : '' }}>{{ __('production.daily') }}</option>
                    <option value="weekly" {{ request('period') === 'weekly' ? 'selected' : '' }}>{{ __('production.weekly') }}</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">{{ __('production.date_range') }}</label>
                <x-ui.odoo-form-ui type="input" inputType="date" name="date_start" value="{{ request('date_start', today()->subDays(6)->toDateString()) }}" />
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="feather-filter me-2"></i>{{ __('production.analyze') }}</button>
            </div>
        </form>

        {{-- Trends Data Grid --}}
        <div class="row g-4">
            {{-- OEE & Availability Trend points table --}}
            <div class="col-md-6">
                <div class="card border border-light shadow-sm h-100">
                    <div class="card-header bg-light py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-trending-up me-2 text-primary"></i>{{ __('production.historical_oee_trends') }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('production.interval') }}</th>
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
                                            <td class="text-primary fw-bold">{{ number_format($oeeTrend['datasets'][0]['data'][$index], 1) }}%</td>
                                            <td>{{ number_format($oeeTrend['datasets'][1]['data'][$index], 1) }}%</td>
                                            <td>{{ number_format($oeeTrend['datasets'][2]['data'][$index], 1) }}%</td>
                                            <td class="text-success">{{ number_format($oeeTrend['datasets'][3]['data'][$index], 1) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Production vs Plan trend points --}}
            <div class="col-md-6">
                <div class="card border border-light shadow-sm h-100">
                    <div class="card-header bg-light py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-bar-chart-2 me-2 text-info"></i>{{ __('production.planned_vs_actual_output') }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('production.interval') }}</th>
                                        <th>{{ __('production.planned_qty') }}</th>
                                        <th>{{ __('production.actual_qty') }}</th>
                                        <th>{{ __('production.variance_qty') }}</th>
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
                                            <td>{{ number_format($planned, 0) }}</td>
                                            <td>{{ number_format($actual, 0) }}</td>
                                            <td class="{{ $variance >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold' }}">
                                                {{ $variance >= 0 ? '+' : '' }}{{ number_format($variance, 0) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Downtime Trends table --}}
            <div class="col-md-12">
                <div class="card border border-light shadow-sm">
                    <div class="card-header bg-light py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="feather-alert-triangle me-2 text-danger"></i>{{ __('production.historical_downtime_trends') }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('production.interval') }}</th>
                                        <th>{{ __('production.todays_downtime') }} %</th>
                                        <th>{{ __('production.status') ?? 'Status' }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($downTrend['labels'] as $index => $label)
                                        @php
                                            $dtRate = $downTrend['datasets'][0]['data'][$index] ?? 0.0;
                                        @endphp
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $label }}</td>
                                            <td class="text-danger fw-bold">{{ number_format($dtRate, 2) }}%</td>
                                            <td>
                                                <span class="badge {{ $dtRate == 0 ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }}">
                                                    {{ $dtRate == 0 ? __('production.steady') : __('production.breakdown') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
