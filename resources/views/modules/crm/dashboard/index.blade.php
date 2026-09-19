@extends('layouts.duralux')

@section('title', __('crm.dashboard.title') . ' | SaaS ERP')
@section('page-title', __('crm.dashboard.title'))
@section('breadcrumb', __('crm.dashboard.breadcrumb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        {{-- Export Dropdown --}}
        <div class="dropdown">
            <x-ui.button variant="light-brand" class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" icon="feather-download">
                {{ __('crm.dashboard.export') }}
            </x-ui.button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <a class="dropdown-item" href="{{ route('crm.dashboard.export', ['format' => 'pdf'] + $query) }}">
                        <i class="feather-file-text me-2 text-danger"></i>{{ __('crm.dashboard.pdf_report') }}
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('crm.dashboard.export', ['format' => 'csv'] + $query) }}">
                        <i class="feather-grid me-2 text-success"></i>{{ __('crm.dashboard.excel_csv') }}
                    </a>
                </li>
            </ul>
        </div>

        {{-- Quick Action Shortcuts --}}
        <x-ui.button href="{{ route('crm.leads.create') }}" variant="primary" icon="feather-plus">
            {{ __('crm.dashboard.new_lead') }}
        </x-ui.button>
        <x-ui.button href="{{ route('crm.deals.create') }}" variant="soft-success" icon="feather-briefcase">
            {{ __('crm.dashboard.new_deal') }}
        </x-ui.button>
        <x-ui.button href="{{ route('crm.deals.kanban') }}" variant="light-brand" icon="feather-columns" title="{{ __('crm.dashboard.kanban') }}">
            {{ __('crm.dashboard.kanban') }}
        </x-ui.button>
        <x-ui.button href="{{ route('platform.whatsappSettings.index') }}" variant="soft-warning" icon="feather-message-square" title="{{ __('crm.dashboard.whatsapp_setup') }}">
            {{ __('crm.dashboard.whatsapp_setup') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
@php
    $currencySymbol = active_currency_symbol();
    $formatCurrency = fn($amount) => format_currency((float) $amount);

    $growthBadge = function(float $change) {
        if ($change > 0) {
            return '<span class="badge bg-soft-success text-success fs-11 fw-semibold"><i class="feather-arrow-up me-1"></i>+' . $change . '%</span>';
        } elseif ($change < 0) {
            return '<span class="badge bg-soft-danger text-danger fs-11 fw-semibold"><i class="feather-arrow-down me-1"></i>' . $change . '%</span>';
        }
        return '<span class="badge bg-soft-secondary text-secondary fs-11 fw-semibold">0.0%</span>';
    };
@endphp

{{-- Advanced Multi-Filter Toolbar --}}
<div class="card stretch stretch-full mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('crm.dashboard') }}" class="row g-2 align-items-end" id="crm-filter-form">
            <input type="hidden" name="view" value="{{ $activeView }}">
            
            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="preset"><i class="feather-calendar me-1"></i>{{ __('crm.dashboard.time_period') }}</label>
                <select name="preset" id="preset" class="form-select form-select-sm border-gray-300" onchange="this.form.submit()">
                    <option value="today" @selected($preset === 'today')>{{ __('crm.dashboard.today') }}</option>
                    <option value="this_month" @selected($preset === 'this_month')>{{ __('crm.dashboard.this_month') }}</option>
                    <option value="last_month" @selected($preset === 'last_month')>{{ __('crm.dashboard.last_month') }}</option>
                    <option value="this_quarter" @selected($preset === 'this_quarter')>{{ __('crm.dashboard.this_quarter') }}</option>
                    <option value="this_year" @selected($preset === 'this_year')>{{ __('crm.dashboard.this_year') }}</option>
                    <option value="all_time" @selected($preset === 'all_time')>{{ __('crm.dashboard.all_time') }}</option>
                    <option value="custom" @selected($preset === 'custom')>{{ __('crm.dashboard.custom_range') }}</option>
                </select>
            </div>

            @if ($companies->count() > 1)
                <div class="col-xl-2 col-md-3 col-sm-6">
                    <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="company_scope"><i class="feather-briefcase me-1"></i>{{ __('crm.dashboard.company_scope') }}</label>
                    <select name="company_scope" id="company_scope" class="form-select form-select-sm border-gray-300" onchange="this.form.submit()">
                        <option value="current" @selected($companyScope === 'current')>{{ __('crm.dashboard.selected_company') }}</option>
                        <option value="all" @selected($companyScope === 'all')>{{ __('crm.dashboard.all_companies_consolidated') }}</option>
                    </select>
                </div>
            @endif

            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="owner_id"><i class="feather-user me-1"></i>{{ __('crm.dashboard.sales_representative') }}</label>
                <select name="owner_id" id="owner_id" class="form-select form-select-sm border-gray-300" onchange="this.form.submit()">
                    <option value="">{{ __('crm.dashboard.all_sales_reps') }}</option>
                    @foreach ($salesOwners as $owner)
                        <option value="{{ $owner->id }}" @selected((string)$ownerId === (string)$owner->id)>{{ $owner->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="lead_type"><i class="feather-tag me-1"></i>{{ __('crm.dashboard.category') }}</label>
                <select name="lead_type" id="lead_type" class="form-select form-select-sm border-gray-300" onchange="this.form.submit()">
                    <option value="">{{ __('crm.dashboard.all_categories') }}</option>
                    <option value="B2B" @selected($leadType === 'B2B')>{{ __('crm.dashboard.b2b_wholesale') }}</option>
                    <option value="B2C" @selected($leadType === 'B2C')>{{ __('crm.dashboard.b2c_retail') }}</option>
                </select>
            </div>

            @if ($preset === 'custom')
                <div class="col-xl-2 col-md-3">
                    <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="from">{{ __('crm.dashboard.from_date') }}</label>
                    <input type="date" name="from" id="from" class="form-control form-control-sm" value="{{ request('from', $startDate->toDateString()) }}">
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="to">{{ __('crm.dashboard.to_date') }}</label>
                    <input type="date" name="to" id="to" class="form-control form-control-sm" value="{{ request('to', $endDate->toDateString()) }}">
                </div>
                <div class="col-xl-1 col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="feather-filter"></i></button>
                </div>
            @endif

            <div class="col ms-auto text-end d-none d-xl-block">
                <span class="fs-12 text-muted fw-semibold">
                    {{ __('crm.dashboard.data_period') }}: <span class="text-dark fw-bold">{{ $startDate->format('d M Y') }}</span> {{ __('crm.dashboard.to') }} <span class="text-dark fw-bold">{{ $endDate->format('d M Y') }}</span>
                </span>
            </div>
        </form>
    </div>
</div>

{{-- Navigation View Tabs (Executive Overview vs Sales Velocity & Reps) --}}
<div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-2">
    <div class="d-flex align-items-center gap-2" id="crmDashboardTabs">
        <x-ui.button 
            href="{{ route('crm.dashboard', array_merge($query, ['view' => 'overview'])) }}" 
            variant="{{ $activeView === 'overview' ? 'primary' : 'light-brand' }}"
            icon="feather-pie-chart"
            class="fw-bold"
        >
            {{ __('crm.dashboard.executive_overview') }}
        </x-ui.button>
        <x-ui.button 
            href="{{ route('crm.dashboard', array_merge($query, ['view' => 'operations'])) }}" 
            variant="{{ $activeView === 'operations' ? 'primary' : 'light-brand' }}"
            icon="feather-activity"
            class="fw-bold"
        >
            {{ __('crm.dashboard.sales_velocity_reps') }}
        </x-ui.button>
    </div>
    <span class="fs-11 text-muted"><i class="feather-info me-1"></i>{{ __('crm.dashboard.realtime_metrics_synced') }}</span>
</div>

@if ($activeView === 'overview')
    {{-- Executive KPI Metrics Row --}}
    <div class="row g-3 mb-4">
        {{-- Total Leads --}}
        <div class="col-xxl col-xl-4 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-11 fw-bold text-uppercase text-muted">{{ __('crm.dashboard.total_leads_captured') }}</span>
                        <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3">
                            <i class="feather-users fs-16"></i>
                        </div>
                    </div>
                    <h3 class="fw-bolder mb-1 text-dark">{{ number_format($currentLeadsCount) }}</h3>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        {!! $growthBadge($leadsGrowth) !!}
                        <span class="fs-11 text-muted">{{ __('crm.dashboard.vs_previous_period') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Active Pipeline Value --}}
        <div class="col-xxl col-xl-4 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-11 fw-bold text-uppercase text-muted">{{ __('crm.dashboard.active_revenue_pipeline') }}</span>
                        <div class="avatar-text avatar-md bg-soft-warning text-warning rounded-3">
                            <i class="feather-briefcase fs-16"></i>
                        </div>
                    </div>
                    <h3 class="fw-bolder mb-1 text-dark">{{ $formatCurrency($pipelineValue) }}</h3>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <span class="badge bg-soft-warning text-warning fs-11 fw-semibold">{{ $openDealsCount }} {{ __('crm.dashboard.open_deals') }}</span>
                        <span class="fs-11 text-muted">{{ __('crm.dashboard.active_opps') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Won Deals Revenue --}}
        <div class="col-xxl col-xl-4 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-11 fw-bold text-uppercase text-muted">{{ __('crm.dashboard.closed_won_revenue') }}</span>
                        <div class="avatar-text avatar-md bg-soft-success text-success rounded-3">
                            <i class="feather-trending-up fs-16"></i>
                        </div>
                    </div>
                    <h3 class="fw-bolder mb-1 text-dark">{{ $formatCurrency($wonRevenue) }}</h3>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <span class="badge bg-soft-success text-success fs-11 fw-semibold"><i class="feather-award me-1"></i>{{ __('crm.dashboard.win_rate') }} {{ $winRate }}%</span>
                        <span class="fs-11 text-muted">{{ $wonCount }} {{ __('crm.dashboard.deals_won') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quotations Value --}}
        <div class="col-xxl col-xl-6 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-11 fw-bold text-uppercase text-muted">{{ __('crm.dashboard.quotations_issued') }}</span>
                        <div class="avatar-text avatar-md bg-soft-info text-info rounded-3">
                            <i class="feather-file-text fs-16"></i>
                        </div>
                    </div>
                    <h3 class="fw-bolder mb-1 text-dark">{{ $formatCurrency($totalQuotationValue) }}</h3>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <span class="badge bg-soft-info text-info fs-11 fw-semibold">{{ $totalQuotationsCount }} {{ __('crm.dashboard.sent') }}</span>
                        <span class="fs-11 text-muted">{{ $pendingQuotationsCount }} {{ __('crm.dashboard.pending_approval') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- WhatsApp Bot Integration --}}
        <div class="col-xxl col-xl-6 col-md-12">
            <div class="card stretch stretch-full border-0 shadow-sm h-100 bg-soft-success">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-11 fw-bold text-uppercase text-success">{{ __('crm.dashboard.whatsapp_bot_automation') }}</span>
                        <div class="avatar-text avatar-md bg-success text-white rounded-3">
                            <i class="feather-message-square fs-16"></i>
                        </div>
                    </div>
                    <h3 class="fw-bolder mb-1 text-success">{{ number_format($whatsappLeadsCount) }} <span class="fs-13 fw-normal">{{ __('crm.dashboard.bot_leads') }}</span></h3>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <span class="badge bg-success text-white fs-11 fw-semibold"><i class="feather-check-circle me-1"></i>{{ $whatsappQualificationRate }}% {{ __('crm.dashboard.auto_qualified') }}</span>
                        <span class="fs-11 text-success font-bold">{{ __('crm.dashboard.ai_engine_active') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Analytics Charts & Funnel Section --}}
    <div class="row g-4 mb-4">
        {{-- Sales Funnel & Lead Status Breakdown --}}
        <div class="col-xxl-4 col-xl-5">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-header border-bottom-0 pb-0">
                    <h5 class="card-title text-dark font-bold"><i class="feather-filter text-primary me-2"></i>{{ __('crm.dashboard.sales_funnel_stage_health') }}</h5>
                    <div class="card-header-action">
                        <span class="badge bg-soft-primary text-primary">{{ __('crm.dashboard.live_conversion') }}</span>
                    </div>
                </div>
                <div class="card-body pt-3">
                    @php
                        $maxFunnel = max(array_values($funnelStages)) ?: 1;
                        $stageColors = [
                            'New'           => 'primary',
                            'Contacted'     => 'info',
                            'Qualified'     => 'warning',
                            'Quotation'     => 'purple',
                            'Converted/Won' => 'success',
                        ];
                    @endphp
                    @foreach ($funnelStages as $stageName => $count)
                        @php
                            $pct = round(($count / $maxFunnel) * 100);
                            $color = $stageColors[$stageName] ?? 'primary';
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fs-12 fw-semibold text-dark">{{ $stageName }}</span>
                                <span class="fs-12 fw-bold text-dark">{{ number_format($count) }} <span class="text-muted fs-11">({{ $pct }}%)</span></span>
                            </div>
                            <div class="progress ht-8 rounded-pill bg-light">
                                <div class="progress-bar bg-{{ $color }} rounded-pill" role="progressbar" style="width: {{ max($pct, 4) }}%" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    @endforeach

                    <hr class="my-3 text-gray-300">

                    <div class="d-flex align-items-center justify-content-between pt-1">
                        <div>
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('crm.dashboard.period_customers') }}</span>
                            <h5 class="fw-bolder mb-0 text-dark">{{ number_format($totalCustomers) }}</h5>
                        </div>
                        <div>
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('crm.dashboard.period_accounts') }}</span>
                            <h5 class="fw-bolder mb-0 text-dark">{{ number_format($totalAccounts) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Monthly Acquisition & Revenue Trend Chart --}}
        <div class="col-xxl-8 col-xl-7">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-header border-bottom-0 pb-0">
                    <div class="d-flex align-items-center justify-content-between w-100">
                        <div>
                            <h5 class="card-title text-dark font-bold"><i class="feather-bar-chart-2 text-success me-2"></i>{{ __('crm.dashboard.revenue_lead_acquisition_trend') }}</h5>
                            <p class="fs-11 text-muted mb-0">{{ __('crm.dashboard.performance_overview_6_months') }}</p>
                        </div>
                        <div class="card-header-action">
                            <span class="badge bg-soft-success text-success"><i class="feather-trending-up me-1"></i>{{ __('crm.dashboard.realtime_sync') }}</span>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-2 pb-3">
                    <div style="position: relative; height: 230px; max-height: 230px; width: 100%;">
                        <canvas id="crmTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Deal Pipeline Stage Cards & Lead Sources --}}
    <div class="row g-4 mb-4">
        {{-- Deal Pipeline Stage Distribution --}}
        <div class="col-xxl-8 col-xl-7">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-layers text-warning me-2"></i>{{ __('crm.dashboard.deal_pipeline_stage_distribution') }}</h5>
                    <a href="{{ route('crm.deals.index') }}" class="btn btn-xs btn-light-brand">{{ __('crm.dashboard.view_all_deals') }}</a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @php
                            $standardStages = [
                                'qualification' => ['name' => __('crm.statuses.Qualified'), 'color' => 'info', 'icon' => 'feather-check-square'],
                                'proposal' => ['name' => __('crm.quotation_status'), 'color' => 'warning', 'icon' => 'feather-file-text'],
                                'negotiation' => ['name' => __('crm.dashboard.active_opps'), 'color' => 'danger', 'icon' => 'feather-refresh-cw'],
                                'won' => ['name' => __('crm.statuses.Won'), 'color' => 'success', 'icon' => 'feather-award'],
                            ];
                        @endphp
                        @foreach ($standardStages as $key => $meta)
                            @php
                                $stageData = $dealStages->get($key);
                                $cnt = $stageData?->count ?? 0;
                                $val = $stageData?->total_value ?? 0;
                            @endphp
                            <div class="col">
                                <div class="p-3 rounded-3 bg-soft-{{ $meta['color'] }} border border-{{ $meta['color'] }} border-opacity-10 text-center h-100">
                                    <i class="{{ $meta['icon'] }} fs-20 text-{{ $meta['color'] }} mb-2"></i>
                                    <h6 class="fs-12 fw-bold text-dark mb-1">{{ $meta['name'] }}</h6>
                                    <h4 class="fw-bolder text-{{ $meta['color'] }} mb-1">{{ $cnt }}</h4>
                                    <span class="fs-11 fw-semibold text-muted">{{ $formatCurrency($val) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Lead Sources Breakdown --}}
        <div class="col-xxl-4 col-xl-5">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-pie-chart text-info me-2"></i>{{ __('crm.dashboard.lead_acquisition_channels') }}</h5>
                </div>
                <div class="card-body">
                    @if (empty($sourceBreakdown))
                        <div class="text-center py-4 text-muted">
                            <i class="feather-inbox fs-30 mb-2"></i>
                            <p class="fs-12 mb-0">{{ __('crm.dashboard.no_lead_source_data') }}</p>
                        </div>
                    @else
                        @php
                            $totalSourceCount = array_sum($sourceBreakdown) ?: 1;
                            $sourceColors = ['WhatsApp Bot' => 'success', 'Web Form' => 'primary', 'Direct' => 'info', 'Referral' => 'warning', 'Cold Call' => 'secondary'];
                        @endphp
                        @foreach ($sourceBreakdown as $sourceName => $count)
                            @php
                                $sourcePct = round(($count / $totalSourceCount) * 100);
                                $clr = $sourceColors[$sourceName] ?? 'primary';
                            @endphp
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar-text avatar-xs bg-soft-{{ $clr }} text-{{ $clr }} rounded-circle">
                                        <i class="feather-hash"></i>
                                    </span>
                                    <span class="fs-12 fw-semibold text-dark">{{ ($sourceName && \Illuminate\Support\Facades\Lang::has('crm.sources.' . $sourceName)) ? __('crm.sources.' . $sourceName) : ($sourceName ?: __('crm.sources.Direct Inquiry')) }}</span>
                                </div>
                                <div class="text-end">
                                    <span class="fs-12 fw-bold text-dark me-2">{{ number_format($count) }}</span>
                                    <span class="badge bg-soft-{{ $clr }} text-{{ $clr }} fs-11">{{ $sourcePct }}%</span>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Top Deals & Recent Leads Action Tables --}}
    <div class="row g-4">
        {{-- Top Open High-Value Opportunities --}}
        <div class="col-xxl-7 col-xl-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-award text-success me-2"></i>{{ __('crm.dashboard.top_high_value_open_opps') }}</h5>
                    <a href="{{ route('crm.deals.index') }}" class="btn btn-xs btn-light-brand">{{ __('crm.dashboard.view_all') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.deal_name_no') }}</th>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.account_client') }}</th>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.value') }}</th>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.stage') }}</th>
                                    <th class="fs-11 text-uppercase text-muted text-end">{{ __('crm.dashboard.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topOpenDeals as $deal)
                                    <tr>
                                        <td>
                                            <a href="{{ route('crm.deals.show', $deal->id) }}" class="fw-bold text-dark d-block">
                                                {{ $deal->title }}
                                            </a>
                                            <span class="fs-11 text-muted">{{ $deal->deal_number }}</span>
                                        </td>
                                        <td>
                                            <span class="fs-12 fw-semibold text-dark">{{ $deal->account?->company_name ?: ($deal->contact?->first_name ?: 'Direct Client') }}</span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">{{ $formatCurrency($deal->estimated_value) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-primary text-primary fs-11 fw-semibold">{{ ucfirst($deal->stage) }}</span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('crm.deals.show', $deal->id) }}" class="btn btn-xs btn-icon btn-light-brand" title="View Deal Details">
                                                <i class="feather-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted fs-12">
                                            {{ __('crm.dashboard.no_active_open_deals') }} <a href="{{ route('crm.deals.create') }}" class="text-primary font-bold">{{ __('crm.dashboard.create_new_deal_link') }}</a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Inbound Leads & WhatsApp Stream --}}
        <div class="col-xxl-5 col-xl-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-user-plus text-primary me-2"></i>{{ __('crm.dashboard.recent_inbound_leads') }}</h5>
                    <a href="{{ route('crm.leads.index') }}" class="btn btn-xs btn-light-brand">{{ __('crm.dashboard.all_leads_btn') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse ($recentLeads as $lead)
                            <div class="list-group-item p-3 border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle fw-bold">
                                        {{ strtoupper(substr($lead->contact_person ?: ($lead->company_name ?: 'L'), 0, 2)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('crm.leads.show', $lead->id) }}" class="fw-bold text-dark fs-13 d-block">
                                            {{ $lead->contact_person ?: $lead->company_name }}
                                        </a>
                                        <span class="fs-11 text-muted me-2"><i class="feather-phone me-1"></i>{{ $lead->phone ?: $lead->company_phone }}</span>
                                        <span class="badge bg-soft-info text-info fs-10">{{ $lead->source ?: 'Direct' }}</span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($lead->phone || $lead->company_phone)
                                        @php
                                            $cleanPhone = preg_replace('/\D/', '', $lead->phone ?: $lead->company_phone);
                                        @endphp
                                        <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" class="btn btn-xs btn-soft-success btn-icon" title="Chat on WhatsApp">
                                            <i class="feather-message-circle"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-xs btn-light-brand btn-icon">
                                        <i class="feather-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted fs-12">
                                {{ __('crm.dashboard.no_recent_leads') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    {{-- Operations & Sales Rep Velocity Tab --}}
    <div class="row g-4 mb-4">
        {{-- Sales Leaderboard Table --}}
        <div class="col-xxl-8 col-xl-7">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title text-dark font-bold"><i class="feather-award text-warning me-2"></i>{{ __('crm.dashboard.sales_reps_leaderboard') }}</h5>
                    <span class="badge bg-soft-warning text-warning fs-11">{{ __('crm.dashboard.period_rankings') }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.rank_sales_rep') }}</th>
                                    <th class="fs-11 text-uppercase text-muted text-center">{{ __('crm.dashboard.leads_assigned') }}</th>
                                    <th class="fs-11 text-uppercase text-muted text-center">{{ __('crm.dashboard.deals_handled') }}</th>
                                    <th class="fs-11 text-uppercase text-muted text-center">{{ __('crm.dashboard.won_deals') }}</th>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.won_revenue') }}</th>
                                    <th class="fs-11 text-uppercase text-muted text-end">{{ __('crm.dashboard.win_rate') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($salesLeaderboard as $index => $rep)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge {{ $index === 0 ? 'bg-warning text-dark' : ($index === 1 ? 'bg-secondary text-white' : 'bg-light text-dark') }} rounded-circle">#{{ $index + 1 }}</span>
                                                <span class="fw-bold text-dark fs-13">{{ $rep['name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="text-center fw-semibold text-dark">{{ $rep['leads_count'] }}</td>
                                        <td class="text-center fw-semibold text-dark">{{ $rep['deals_count'] }}</td>
                                        <td class="text-center fw-bold text-success">{{ $rep['won_count'] }}</td>
                                        <td class="fw-bold text-success">{{ $formatCurrency($rep['won_revenue']) }}</td>
                                        <td class="text-end">
                                            <span class="badge bg-soft-success text-success fs-11 fw-bold">{{ $rep['win_rate'] }}%</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted fs-12">
                                            {{ __('crm.dashboard.no_sales_rep_data') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Win / Loss Reason Breakdown --}}
        <div class="col-xxl-4 col-xl-5">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-header">
                    <h5 class="card-title text-dark font-bold"><i class="feather-pie-chart text-danger me-2"></i>{{ __('crm.dashboard.win_loss_reason_analysis') }}</h5>
                </div>
                <div class="card-body">
                    @if (empty($winLossReasons))
                        <div class="text-center py-4 text-muted">
                            <i class="feather-help-circle fs-30 mb-2 text-muted"></i>
                            <p class="fs-12 mb-0">{{ __('crm.dashboard.no_closed_deal_reasons') }}</p>
                        </div>
                    @else
                        @php
                            $totalReasons = array_sum($winLossReasons) ?: 1;
                        @endphp
                        @foreach ($winLossReasons as $reason => $cnt)
                            @php $pct = round(($cnt / $totalReasons) * 100); @endphp
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="fs-12 fw-semibold text-dark">{{ $reason }}</span>
                                <div>
                                    <span class="fs-12 fw-bold text-dark me-2">{{ $cnt }}</span>
                                    <span class="badge bg-soft-primary text-primary fs-11">{{ $pct }}%</span>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Quotations & Scheduled Follow-ups --}}
    <div class="row g-4">
        {{-- Recent Quotations Stream --}}
        <div class="col-xxl-6 col-xl-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title text-dark font-bold"><i class="feather-file-text text-info me-2"></i>{{ __('crm.dashboard.recent_issued_quotations') }}</h5>
                    <a href="{{ route('crm.quotations.index') }}" class="btn btn-xs btn-light-brand">{{ __('crm.dashboard.view_all') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.quotation_no') }}</th>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.total_amount') }}</th>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.stage') }}</th>
                                    <th class="fs-11 text-uppercase text-muted text-end">{{ __('crm.dashboard.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentQuotations as $quote)
                                    <tr>
                                        <td>
                                            <a href="{{ route('crm.quotations.show', $quote->id) }}" class="fw-bold text-dark">
                                                {{ $quote->quotation_number }}
                                            </a>
                                            <span class="fs-11 text-muted d-block">{{ $quote->created_at ? $quote->created_at->format('d M Y') : '' }}</span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark">{{ $formatCurrency($quote->total_amount) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-info text-info fs-11">{{ ucfirst($quote->status) }}</span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('crm.quotations.show', $quote->id) }}" class="btn btn-xs btn-icon btn-light-brand">
                                                <i class="feather-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted fs-12">{{ __('crm.dashboard.no_quotations_period') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending Scheduled Follow-ups --}}
        <div class="col-xxl-6 col-xl-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="card-title text-dark font-bold"><i class="feather-clock text-warning me-2"></i>{{ __('crm.dashboard.upcoming_sales_followups') }}</h5>
                    <a href="{{ route('crm.activities.index') }}" class="btn btn-xs btn-light-brand">{{ __('crm.dashboard.all_followups_btn') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse ($pendingFollowups as $followup)
                            <div class="list-group-item p-3 border-bottom d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fw-bold text-dark fs-13 d-block">{{ $followup->lead?->contact_person ?: ($followup->lead?->company_name ?: 'Sales Task') }}</span>
                                    <span class="fs-11 text-muted me-2"><i class="feather-calendar me-1"></i>{{ $followup->followup_date ? \Carbon\Carbon::parse($followup->followup_date)->format('d M Y, h:i A') : 'Scheduled' }}</span>
                                    <span class="badge bg-soft-warning text-warning fs-10">{{ ucfirst($followup->type ?: 'Call') }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    @if ($followup->lead?->phone || $followup->lead?->company_phone)
                                        <a href="tel:{{ $followup->lead?->phone ?: $followup->lead?->company_phone }}" class="btn btn-xs btn-soft-primary btn-icon" title="Call Now">
                                            <i class="feather-phone"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('crm.leads.show', $followup->lead_id) }}" class="btn btn-xs btn-light-brand btn-icon">
                                        <i class="feather-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted fs-12">{{ __('crm.dashboard.no_pending_followups') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('styles')
<style>
    .ht-8 { height: 8px !important; }
    .bg-soft-purple { background-color: rgba(147, 51, 234, 0.12) !important; color: #9333ea !important; }
    .text-purple { color: #9333ea !important; }
    .bg-purple { background-color: #9333ea !important; }
</style>
@endpush

@push('scripts')
@if ($activeView === 'overview')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const trendData = @json($monthlyTrend);

        const ctx = document.getElementById('crmTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendData.labels,
                datasets: [
                    {
                        label: 'Leads Created',
                        data: trendData.leads,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'yLeads'
                    },
                    {
                        label: 'Revenue Won (' + (window.AppCurrency?.symbol || @json(active_currency_symbol())) + ')',
                        data: trendData.revenue,
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'yRevenue'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'Inter', size: 12 } }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    yLeads: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Leads Count' },
                        grid: { borderDash: [2, 4] }
                    },
                    yRevenue: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Won Revenue' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    });
</script>
@endif
@endpush
