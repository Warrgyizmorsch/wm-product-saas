@extends('layouts.duralux')

@section('title', __('production.manufacturing_reports_bi') . ' | SaaS ERP')
@section('page-title', __('production.manufacturing_performance_reports'))
@section('breadcrumb', __('production.intelligence_reports'))

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        <h5 class="fw-bold text-dark mb-4"><i class="feather-printer me-2"></i>{{ __('production.select_report_to_generate') }}</h5>

        <div class="row g-4">
            {{-- Machine Performance --}}
            <div class="col-md-4">
                <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                    <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded mb-3">
                        <i class="feather-cpu"></i>
                    </div>
                    <h5 class="fw-bold text-dark">{{ __('production.machine_performance_report') }}</h5>
                    <p class="text-muted fs-13">{{ __('production.machine_report_desc') }}</p>
                    
                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'machine') }}" target="_blank" class="mt-3 fs-13 text-dark">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('production.col_machine') ?? 'Machine'" name="machine_id">
                                <option value="">{{ __('production.all_machines') }}</option>
                                @foreach($machines as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'machine') }}'; this.form.target='_blank';" class="btn btn-sm btn-primary flex-fill">{{ __('production.view_report') }}</button>
                            <button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'machine') }}'; this.form.target='_blank';" name="print" value="1" class="btn btn-sm btn-outline-dark" title="{{ __('production.print') }}"><i class="feather-printer"></i></button>
                            <button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'machine') }}'; this.form.target='_self';" class="btn btn-sm btn-outline-primary" title="{{ __('production.export_csv') }}"><i class="feather-download"></i></button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            {{-- Work Center Report --}}
            <div class="col-md-4">
                <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                    <div class="avatar-text avatar-lg bg-soft-success text-success rounded mb-3">
                        <i class="feather-settings"></i>
                    </div>
                    <h5 class="fw-bold text-dark">{{ __('production.work_center_report') }}</h5>
                    <p class="text-muted fs-13">{{ __('production.work_center_report_desc') }}</p>
                    
                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'work-center') }}" target="_blank" class="mt-3 fs-13 text-dark">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('production.col_work_center')" name="work_center_id">
                                <option value="">{{ __('production.all_work_centers') }}</option>
                                @foreach($workCenters as $wc)
                                    <option value="{{ $wc->id }}">{{ $wc->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'work-center') }}'; this.form.target='_blank';" class="btn btn-sm btn-success flex-fill">{{ __('production.view_report') }}</button>
                            <button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'work-center') }}'; this.form.target='_blank';" name="print" value="1" class="btn btn-sm btn-outline-dark" title="{{ __('production.print') }}"><i class="feather-printer"></i></button>
                            <button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'work-center') }}'; this.form.target='_self';" class="btn btn-sm btn-outline-success" title="{{ __('production.export_csv') }}"><i class="feather-download"></i></button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            {{-- Downtime Breakdown --}}
            <div class="col-md-4">
                <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                    <div class="avatar-text avatar-lg bg-soft-danger text-danger rounded mb-3">
                        <i class="feather-alert-triangle"></i>
                    </div>
                    <h5 class="fw-bold text-dark">{{ __('production.downtime_breakdown_report') }}</h5>
                    <p class="text-muted fs-13">{{ __('production.downtime_report_desc') }}</p>
                    
                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'downtime') }}" target="_blank" class="mt-3 fs-13 text-dark">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('production.col_machine') ?? 'Machine'" name="machine_id">
                                <option value="">{{ __('production.all_machines') }}</option>
                                @foreach($machines as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'downtime') }}'; this.form.target='_blank';" class="btn btn-sm btn-danger flex-fill">{{ __('production.view_report') }}</button>
                            <button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'downtime') }}'; this.form.target='_blank';" name="print" value="1" class="btn btn-sm btn-outline-dark" title="{{ __('production.print') }}"><i class="feather-printer"></i></button>
                            <button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'downtime') }}'; this.form.target='_self';" class="btn btn-sm btn-outline-danger" title="{{ __('production.export_csv') }}"><i class="feather-download"></i></button>
                        </div>
                    </form>
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection
