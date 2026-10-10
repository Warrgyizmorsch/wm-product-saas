@extends('layouts.duralux')

@section('title', __('crm.dashboard.title') . ' | SaaS ERP')
@section('page-title', __('crm.dashboard.title'))
@section('breadcrumb', __('crm.dashboard.breadcrumb'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

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
                <select name="preset" id="preset" class="form-select form-select-sm border-gray-300" data-select2-selector="default">
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
                    <select name="company_scope" id="company_scope" class="form-select form-select-sm border-gray-300" data-select2-selector="default">
                        <option value="current" @selected($companyScope === 'current')>{{ __('crm.dashboard.selected_company') }}</option>
                        <option value="all" @selected($companyScope === 'all')>{{ __('crm.dashboard.all_companies_consolidated') }}</option>
                    </select>
                </div>
            @endif

            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="owner_id"><i class="feather-user me-1"></i>{{ __('crm.dashboard.sales_representative') }}</label>
                <select name="owner_id" id="owner_id" class="form-select form-select-sm border-gray-300" data-select2-selector="default">
                    <option value="">{{ __('crm.dashboard.all_sales_reps') }}</option>
                    @foreach ($salesOwners as $owner)
                        <option value="{{ $owner->id }}" @selected((string)$ownerId === (string)$owner->id)>{{ $owner->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="lead_type"><i class="feather-tag me-1"></i>{{ __('crm.dashboard.category') }}</label>
                <select name="lead_type" id="lead_type" class="form-select form-select-sm border-gray-300" data-select2-selector="default">
                    <option value="">{{ __('crm.dashboard.all_categories') }}</option>
                    <option value="B2B" @selected($leadType === 'B2B')>{{ __('crm.dashboard.b2b_wholesale') }}</option>
                    <option value="B2C" @selected($leadType === 'B2C')>{{ __('crm.dashboard.b2c_retail') }}</option>
                </select>
            </div>

            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="campaign"><i class="feather-target me-1"></i>{{ __('crm.dashboard.campaign_name') }}</label>
                <select name="campaign" id="campaign" class="form-select form-select-sm border-gray-300" data-select2-selector="default">
                    <option value="">{{ __('crm.dashboard.all_campaigns') }}</option>
                    @foreach ($availableCampaigns as $camp)
                        <option value="{{ $camp }}" @selected((string)$campaign === (string)$camp)>{{ $camp }}</option>
                    @endforeach
                </select>
            </div>

            @if(count($availableAdsets) > 0)
                <div class="col-xl-2 col-md-3 col-sm-6">
                    <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="adset"><i class="feather-layers me-1"></i>{{ __('crm.dashboard.adset_label') }}</label>
                    <select name="adset" id="adset" class="form-select form-select-sm border-gray-300" data-select2-selector="default">
                        <option value="">{{ __('crm.dashboard.all_adsets') }}</option>
                        @foreach ($availableAdsets as $aset)
                            <option value="{{ $aset }}" @selected((string)$adset === (string)$aset)>{{ $aset }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(count($availableAds) > 0)
                <div class="col-xl-2 col-md-3 col-sm-6">
                    <label class="form-label fs-11 text-uppercase fw-bold text-muted mb-1" for="ad_name"><i class="feather-image me-1"></i>{{ __('crm.dashboard.ad_creative_label') }}</label>
                    <select name="ad_name" id="ad_name" class="form-select form-select-sm border-gray-300" data-select2-selector="default">
                        <option value="">{{ __('crm.dashboard.all_ads') }}</option>
                        @foreach ($availableAds as $ad)
                            <option value="{{ $ad }}" @selected((string)$adName === (string)$ad)>{{ $ad }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

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
        <a href="{!! route('crm.dashboard', array_merge($query, ['view' => 'overview'])) !!}" 
           class="btn {{ $activeView === 'overview' ? 'btn-primary text-white shadow-sm' : 'btn-light-brand text-muted' }} fw-bold d-inline-flex align-items-center">
            <i class="feather-pie-chart me-2"></i>{{ __('crm.dashboard.executive_overview') }}
        </a>
        <a href="{!! route('crm.dashboard', array_merge($query, ['view' => 'operations'])) !!}" 
           class="btn {{ $activeView === 'operations' ? 'btn-primary text-white shadow-sm' : 'btn-light-brand text-muted' }} fw-bold d-inline-flex align-items-center">
            <i class="feather-activity me-2"></i>{{ __('crm.dashboard.sales_velocity_reps') }}
        </a>
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
                        <span class="badge bg-soft-success text-success fs-11 fw-semibold"><i class="feather-award me-1"></i>{{ $winRate }}%</span>
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

        {{-- Third-Party Leads --}}
        <div class="col-xxl col-xl-6 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-11 fw-bold text-uppercase text-muted">{{ __('crm.dashboard.third_party_leads_title') }}</span>
                        <div class="avatar-text avatar-md bg-soft-purple text-purple rounded-3">
                            <i class="feather-share-2 fs-16"></i>
                        </div>
                    </div>
                    <h3 class="fw-bolder mb-1 text-dark">{{ number_format($thirdPartyLeadsCount) }}</h3>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <span class="badge bg-soft-purple text-purple fs-11 fw-semibold"><i class="feather-award me-1"></i>{{ $thirdPartyWinRate }}% {{ __('crm.dashboard.win_label') }}</span>
                        <span class="fs-11 text-muted">{{ $thirdPartyWonCount }} {{ __('crm.dashboard.deals_won') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Enterprise Multi-Stage Visual Sales Funnel & Monthly Trend --}}
    <div class="row g-4 mb-4">
        {{-- Enterprise Connected Multi-Stage Sales Funnel --}}
        <div class="col-xxl-5 col-xl-5">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-header border-bottom-0 pb-1 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="card-title text-dark font-bold mb-0">
                            <i class="feather-filter text-primary me-2"></i>{{ __('crm.dashboard.enterprise_funnel_title') }}
                        </h5>
                        <span class="fs-11 text-muted">{{ __('crm.dashboard.enterprise_funnel_sub') }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <ul class="nav nav-pills nav-pills-sm bg-light p-0.5 rounded-2" id="funnelTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-1 px-2 fs-11 fw-semibold" id="funnel-flow-tab" data-bs-toggle="pill" data-bs-target="#funnel-flow-pane" type="button" role="tab">
                                    <i class="feather-git-commit me-1"></i>{{ __('crm.dashboard.funnel_tab_flow') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-1 px-2 fs-11 fw-semibold" id="funnel-chart-tab" data-bs-toggle="pill" data-bs-target="#funnel-chart-pane" type="button" role="tab">
                                    <i class="feather-bar-chart-2 me-1"></i>{{ __('crm.dashboard.funnel_tab_chart') }}
                                </button>
                            </li>
                        </ul>
                        <span class="badge bg-soft-success text-success fw-bold px-2 py-1 fs-11 border border-success border-opacity-25">
                            <i class="feather-award me-1"></i>{{ __('crm.dashboard.win_rate') }}: {{ $funnelDetailed['overall_conversion'] }}%
                        </span>
                    </div>
                </div>
                <div class="card-body pt-2 pb-3">
                    <div class="tab-content" id="funnelTabContent">
                        {{-- Tab 1: Visual Funnel Pipeline Flow --}}
                        <div class="tab-pane fade show active" id="funnel-flow-pane" role="tabpanel">
                            <div class="funnel-pipeline-wrapper">
                                @foreach ($funnelDetailed['stages'] as $index => $stg)
                                    @if ($index > 0)
                                        <div class="d-flex align-items-center justify-content-center my-1.5 position-relative">
                                            <div class="border-top border-2 border-dashed w-100 position-absolute" style="z-index: 1;"></div>
                                            <span class="badge bg-white text-muted border shadow-xs px-2 py-0.5 fs-10 position-relative" style="z-index: 2;">
                                                <i class="feather-arrow-down me-1 text-primary"></i>
                                                <strong class="text-dark">{{ $stg['conversion_from_prev'] }}%</strong> {{ __('crm.dashboard.step_conversion') }}
                                                @php $dropOff = round(100 - $stg['conversion_from_prev'], 1); @endphp
                                                @if ($dropOff > 0)
                                                    <span class="text-danger ms-1">({{ $dropOff }}% {{ __('crm.dashboard.drop_off') }})</span>
                                                @endif
                                            </span>
                                        </div>
                                    @endif

                                    <div class="funnel-stage-item p-2.5 rounded-3 border bg-white shadow-xs position-relative" style="border-left: 4px solid var(--bs-{{ $stg['color'] }}) !important;">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar-text avatar-sm bg-soft-{{ $stg['color'] }} text-{{ $stg['color'] }} rounded-circle flex-shrink-0">
                                                    <i class="{{ $stg['icon'] }} fs-13"></i>
                                                </div>
                                                <div>
                                                    <span class="fs-12 fw-bold text-dark d-block lh-sm">{{ $stg['name'] }}</span>
                                                    <span class="fs-10 text-muted">{{ $stg['sub'] }}</span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fs-13 fw-bolder text-dark">
                                                    {{ number_format($stg['count']) }}
                                                    @if(isset($stg['value']) && $stg['value'] > 0)
                                                        <span class="fs-11 text-success font-monospace ms-1">({{ $formatCurrency($stg['value']) }})</span>
                                                    @endif
                                                </div>
                                                <div class="fs-10 text-muted">
                                                    <span class="badge bg-soft-{{ $stg['color'] }} text-{{ $stg['color'] }} py-0.5 px-1.5 fs-10 fw-semibold">{{ $stg['pct_of_total'] }}% {{ __('crm.dashboard.of_top_funnel') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="progress ht-6 rounded-pill bg-light border mt-2">
                                            <div class="progress-bar bg-{{ $stg['color'] }} rounded-pill" role="progressbar" style="width: {{ max($stg['pct_of_total'], 5) }}%" aria-valuenow="{{ $stg['pct_of_total'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Tab 2: Interactive Funnel Chart --}}
                        <div class="tab-pane fade" id="funnel-chart-pane" role="tabpanel">
                            <div style="position: relative; height: 320px; max-height: 320px; width: 100%;">
                                <canvas id="crmFunnelChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Monthly Acquisition & Revenue Trend Chart --}}
        <div class="col-xxl-7 col-xl-7">
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
                    <div style="position: relative; height: 320px; max-height: 320px; width: 100%;">
                        <canvas id="crmTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Marketing Campaigns ROI & Performance Section --}}
    <div class="row g-4 mb-4">
        {{-- Campaign Performance Comparison Chart --}}
        <div class="col-xxl-6 col-xl-6">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-header border-bottom-0 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="card-title text-dark font-bold mb-0"><i class="feather-target text-primary me-2"></i>{{ __('crm.dashboard.top_campaigns_chart_title') }}</h5>
                        <p class="fs-11 text-muted mb-0">{{ __('crm.dashboard.top_campaigns_chart_sub') }}</p>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <div class="btn-group btn-group-sm p-0.5 bg-light rounded-2" role="group">
                            <a href="{{ route('crm.dashboard', array_merge($query, ['campaign_dimension' => 'campaign'])) }}" 
                               class="btn btn-xs {{ $campaignDimension === 'campaign' ? 'btn-primary text-white' : 'btn-light text-muted' }} py-1 px-2 fs-10 fw-bold rounded-1">
                               {{ __('crm.dashboard.by_campaign') }}
                            </a>
                            <a href="{{ route('crm.dashboard', array_merge($query, ['campaign_dimension' => 'adset'])) }}" 
                               class="btn btn-xs {{ $campaignDimension === 'adset' ? 'btn-primary text-white' : 'btn-light text-muted' }} py-1 px-2 fs-10 fw-bold rounded-1">
                               {{ __('crm.dashboard.by_adset') }}
                            </a>
                            <a href="{{ route('crm.dashboard', array_merge($query, ['campaign_dimension' => 'ad_name'])) }}" 
                               class="btn btn-xs {{ $campaignDimension === 'ad_name' ? 'btn-primary text-white' : 'btn-light text-muted' }} py-1 px-2 fs-10 fw-bold rounded-1">
                               {{ __('crm.dashboard.by_ad') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-3 pb-3">
                    @if($campaignPerformance->isNotEmpty())
                    <div class="row g-3 align-items-center h-100">
                        {{-- Left Column: Data Type / Campaign Select Filter --}}
                        <div class="col-md-4 col-sm-12">
                            <div class="pe-md-1">
                                <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1.5 d-flex align-items-center gap-1">
                                    <i class="feather-filter text-primary"></i> {{ __('crm.dashboard.select_data_type') }}
                                </label>
                                <select class="form-select form-select-sm fs-12 fw-semibold border-secondary border-opacity-25 rounded-2 shadow-none py-1.5" id="campaignFunnelSelect" onchange="updateCampaignFunnel(this.value)">
                                    <option value="all">
                                        {{ $campaignDimension === 'adset' ? __('crm.dashboard.all_adsets') : ($campaignDimension === 'ad_name' ? __('crm.dashboard.all_ads') : __('crm.dashboard.all_campaigns')) }}
                                    </option>
                                    @foreach($campaignPerformance as $idx => $camp)
                                        <option value="{{ $idx }}">{{ Str::limit($camp->campaign_name, 22) }}</option>
                                    @endforeach
                                </select>
                                
                                <div class="mt-3 p-2.5 rounded-3 bg-light border border-light-subtle">
                                    <span class="fs-10 text-uppercase fw-bold text-muted d-block">{{ __('crm.dashboard.overall_conversion') }}</span>
                                    <div class="d-flex align-items-baseline gap-1 mt-0.5">
                                        <h4 class="fs-16 fw-bolder text-success mb-0" id="funnelOverallConv">{{ $campaignFunnel['overall_conv_rate'] ?? 0 }}%</h4>
                                        <span class="fs-10 text-muted">{{ __('crm.dashboard.leads_label') }} ➔ {{ __('crm.dashboard.win_label') }}</span>
                                    </div>
                                    <div class="fs-10 text-muted mt-1" id="funnelRevenueNote">
                                        {{ __('crm.dashboard.won_revenue') }}: <b class="text-dark">{{ $formatCurrency($campaignFunnel['revenue'] ?? 0) }}</b>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Center Column: Clean Linear Inverted Triangular Funnel SVG --}}
                        <div class="col-md-5 col-sm-6 text-center">
                            <div class="funnel-chart-wrapper mx-auto" style="max-width: 250px;">
                                <svg viewBox="0 0 320 230" width="100%" height="220" class="funnel-svg" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.05));">
                                    <!-- Slice 0: Total Leads -->
                                    <polygon id="funnelPoly0" points="20,6 300,6 277,41 43,41" fill="#DE6C37" class="funnel-slice" style="cursor: pointer; transition: all 0.25s ease;" />
                                    <text id="funnelTxt0" x="160" y="24" text-anchor="middle" dominant-baseline="central" fill="#FFFFFF" font-weight="700" font-size="13" font-family="'Inter', sans-serif">{{ $campaignFunnel['total_leads'] }}</text>

                                    <!-- Slice 1: Contacted / Followup -->
                                    <polygon id="funnelPoly1" points="43,43 277,43 254,78 66,78" fill="#DEB841" class="funnel-slice" style="cursor: pointer; transition: all 0.25s ease;" />
                                    <text id="funnelTxt1" x="160" y="61" text-anchor="middle" dominant-baseline="central" fill="#FFFFFF" font-weight="700" font-size="13" font-family="'Inter', sans-serif">{{ $campaignFunnel['contacted'] }}</text>

                                    <!-- Slice 2: Qualified Leads -->
                                    <polygon id="funnelPoly2" points="66,80 254,80 231,115 89,115" fill="#6CAE9B" class="funnel-slice" style="cursor: pointer; transition: all 0.25s ease;" />
                                    <text id="funnelTxt2" x="160" y="98" text-anchor="middle" dominant-baseline="central" fill="#FFFFFF" font-weight="700" font-size="13" font-family="'Inter', sans-serif">{{ $campaignFunnel['qualified'] }}</text>

                                    <!-- Slice 3: Opportunities / Deals -->
                                    <polygon id="funnelPoly3" points="89,117 231,117 208,152 112,152" fill="#2D6CB4" class="funnel-slice" style="cursor: pointer; transition: all 0.25s ease;" />
                                    <text id="funnelTxt3" x="160" y="135" text-anchor="middle" dominant-baseline="central" fill="#FFFFFF" font-weight="700" font-size="13" font-family="'Inter', sans-serif">{{ $campaignFunnel['deals'] }}</text>

                                    <!-- Slice 4: Proposals / Quotations -->
                                    <polygon id="funnelPoly4" points="112,154 208,154 185,189 135,189" fill="#8A89E6" class="funnel-slice" style="cursor: pointer; transition: all 0.25s ease;" />
                                    <text id="funnelTxt4" x="160" y="172" text-anchor="middle" dominant-baseline="central" fill="#FFFFFF" font-weight="700" font-size="12" font-family="'Inter', sans-serif">{{ $campaignFunnel['proposals'] }}</text>

                                    <!-- Slice 5: Closed & Won -->
                                    <polygon id="funnelPoly5" points="135,191 185,191 170,224 150,224" fill="#E668B8" class="funnel-slice" style="cursor: pointer; transition: all 0.25s ease;" />
                                    <text id="funnelTxt5" x="160" y="207" text-anchor="middle" dominant-baseline="central" fill="#FFFFFF" font-weight="700" font-size="12" font-family="'Inter', sans-serif">{{ $campaignFunnel['won'] }}</text>
                                </svg>
                            </div>
                        </div>

                        {{-- Right Column: Stage Legend List --}}
                        <div class="col-md-3 col-sm-6">
                            <div class="d-flex flex-column gap-2 ps-md-1">
                                <div class="d-flex align-items-center justify-content-between p-1 rounded-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="width: 13px; height: 13px; background-color: #DE6C37; border-radius: 2px;" class="d-inline-block flex-shrink-0"></span>
                                        <span class="fs-11 text-muted fw-semibold text-truncate">{{ __('crm.dashboard.leads_label') }}</span>
                                    </div>
                                    <b class="fs-12 text-dark font-monospace" id="legendCnt0">{{ number_format($campaignFunnel['total_leads']) }}</b>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-1 rounded-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="width: 13px; height: 13px; background-color: #DEB841; border-radius: 2px;" class="d-inline-block flex-shrink-0"></span>
                                        <span class="fs-11 text-muted fw-semibold text-truncate">{{ __('crm.dashboard.funnel_contacted') }}</span>
                                    </div>
                                    <b class="fs-12 text-dark font-monospace" id="legendCnt1">{{ number_format($campaignFunnel['contacted']) }}</b>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-1 rounded-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="width: 13px; height: 13px; background-color: #6CAE9B; border-radius: 2px;" class="d-inline-block flex-shrink-0"></span>
                                        <span class="fs-11 text-muted fw-semibold text-truncate">{{ __('crm.dashboard.funnel_qualified') }}</span>
                                    </div>
                                    <b class="fs-12 text-dark font-monospace" id="legendCnt2">{{ number_format($campaignFunnel['qualified']) }}</b>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-1 rounded-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="width: 13px; height: 13px; background-color: #2D6CB4; border-radius: 2px;" class="d-inline-block flex-shrink-0"></span>
                                        <span class="fs-11 text-muted fw-semibold text-truncate">{{ __('crm.dashboard.funnel_deals') }}</span>
                                    </div>
                                    <b class="fs-12 text-dark font-monospace" id="legendCnt3">{{ number_format($campaignFunnel['deals']) }}</b>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-1 rounded-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="width: 13px; height: 13px; background-color: #8A89E6; border-radius: 2px;" class="d-inline-block flex-shrink-0"></span>
                                        <span class="fs-11 text-muted fw-semibold text-truncate">{{ __('crm.dashboard.funnel_quotations') }}</span>
                                    </div>
                                    <b class="fs-12 text-dark font-monospace" id="legendCnt4">{{ number_format($campaignFunnel['proposals']) }}</b>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-1 rounded-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="width: 13px; height: 13px; background-color: #E668B8; border-radius: 2px;" class="d-inline-block flex-shrink-0"></span>
                                        <span class="fs-11 text-muted fw-semibold text-truncate">{{ __('crm.dashboard.funnel_won') }}</span>
                                    </div>
                                    <b class="fs-12 text-dark font-monospace" id="legendCnt5">{{ number_format($campaignFunnel['won']) }}</b>
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="text-center py-5">
                        <div class="avatar-text avatar-lg bg-soft-primary text-primary mx-auto mb-3 rounded-circle">
                            <i class="feather-filter fs-20"></i>
                        </div>
                        <h6 class="fs-13 fw-bold text-dark mb-1">{{ __('crm.dashboard.no_campaign_data') }}</h6>
                        <p class="fs-11 text-muted mb-0">{{ __('crm.dashboard.meta_utm_populate_notice') }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Campaign Performance & Revenue Matrix Table --}}
        <div class="col-xxl-6 col-xl-6">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0"><i class="feather-grid text-success me-2"></i>{{ __('crm.dashboard.campaign_matrix_title') }}</h5>
                        <span class="fs-11 text-muted">{{ __('crm.dashboard.campaign_matrix_sub') }}</span>
                    </div>
                    <span class="badge bg-soft-primary text-primary fs-11 fw-bold">{{ $dimensionLabel }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="fs-11 text-uppercase text-muted ps-3">{{ $dimensionLabel }}</th>
                                    <th class="fs-11 text-uppercase text-muted text-center">{{ __('crm.dashboard.leads_label') }}</th>
                                    <th class="fs-11 text-uppercase text-muted text-center">{{ __('crm.dashboard.won_deals') }}</th>
                                    <th class="fs-11 text-uppercase text-muted">{{ __('crm.dashboard.won_revenue') }}</th>
                                    <th class="fs-11 text-uppercase text-muted text-end pe-3">{{ __('crm.dashboard.conv_rate') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($campaignPerformance as $camp)
                                    <tr>
                                        <td class="ps-3">
                                            <strong class="text-dark d-block fs-12">{{ $camp->campaign_name }}</strong>
                                            <span class="badge bg-light text-secondary border fs-10">{{ $camp->channel }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 fw-bold">{{ number_format($camp->total_leads) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-success text-success fs-11 fw-bold">{{ number_format($camp->won_deals) }}</span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark fs-12">{{ $formatCurrency($camp->won_revenue) }}</span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <span class="badge bg-soft-{{ $camp->conversion_rate > 20 ? 'success' : ($camp->conversion_rate > 10 ? 'warning' : 'secondary') }} text-{{ $camp->conversion_rate > 20 ? 'success' : ($camp->conversion_rate > 10 ? 'warning' : 'secondary') }} fs-11 fw-bold">
                                                {{ $camp->conversion_rate }}%
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted fs-12">
                                            <i class="feather-target fs-24 mb-1 d-block opacity-50"></i>
                                            {{ __('crm.dashboard.no_campaign_data') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Deal Pipeline Stage Cards & Lead Sources --}}
    <div class="row g-4 mb-4">
        {{-- Deal Pipeline Stage Distribution --}}
        <div class="col-xxl-7 col-xl-7">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="feather-layers text-warning me-2"></i>{{ __('crm.dashboard.deal_pipeline_stage_distribution') }}</h5>
                    <a href="{{ route('crm.deals.index') }}" class="btn btn-xs btn-light-brand">{{ __('crm.dashboard.view_all_deals') }}</a>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
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
                            <div class="col-sm-6 col-md-3">
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

        {{-- Lead Acquisition Channels Breakdown & Doughnut Chart --}}
        <div class="col-xxl-5 col-xl-5">
            <div class="card stretch stretch-full border-0 shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="feather-pie-chart text-info me-2"></i>{{ __('crm.dashboard.lead_acquisition_channels') }}</h5>
                    <span class="badge bg-soft-info text-info">{{ array_sum($sourceBreakdown) }} {{ __('crm.dashboard.leads_label') }}</span>
                </div>
                <div class="card-body pt-2">
                    @if (empty($sourceBreakdown))
                        <div class="text-center py-4 text-muted">
                            <i class="feather-inbox fs-30 mb-2"></i>
                            <p class="fs-12 mb-0">{{ __('crm.dashboard.no_lead_source_data') }}</p>
                        </div>
                    @else
                        <div class="row align-items-center">
                            <div class="col-sm-5 text-center mb-3 mb-sm-0">
                                <div style="position: relative; height: 160px; max-height: 160px; width: 100%;">
                                    <canvas id="crmSourceChart"></canvas>
                                </div>
                            </div>
                            <div class="col-sm-7">
                                @php
                                    $totalSourceCount = array_sum($sourceBreakdown) ?: 1;
                                    $sourceColors = ['WhatsApp Bot' => 'success', 'Web Form' => 'primary', 'Direct' => 'info', 'Referral' => 'warning', 'Cold Call' => 'secondary', 'Meta Ads' => 'primary'];
                                @endphp
                                @foreach ($sourceBreakdown as $sourceName => $count)
                                    @php
                                        $sourcePct = round(($count / $totalSourceCount) * 100);
                                        $clr = $sourceColors[$sourceName] ?? 'primary';
                                    @endphp
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar-text avatar-xs bg-soft-{{ $clr }} text-{{ $clr }} rounded-circle">
                                                <i class="feather-hash"></i>
                                            </span>
                                            <span class="fs-12 fw-semibold text-dark">{{ ($sourceName && \Illuminate\Support\Facades\Lang::has('crm.sources.' . $sourceName)) ? __('crm.sources.' . $sourceName) : ($sourceName ?: __('crm.sources.Direct Inquiry')) }}</span>
                                        </div>
                                        <div class="text-end">
                                            <span class="fs-12 fw-bold text-dark me-2">{{ number_format($count) }}</span>
                                            <span class="badge bg-soft-{{ $clr }} text-{{ $clr }} fs-10">{{ $sourcePct }}%</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
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
<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
    $(document).ready(function() {
        $('#preset, #company_scope, #owner_id, #lead_type, #campaign, #adset, #ad_name').on('change', function() {
            $('#crm-filter-form').submit();
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@if ($activeView === 'overview')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const currencySymbol = window.AppCurrency?.symbol || @json(active_currency_symbol());

        // 1. Revenue & Lead Acquisition Trend Chart
        const trendData = @json($monthlyTrend);
        const trendEl = document.getElementById('crmTrendChart');
        if (trendEl) {
            new Chart(trendEl.getContext('2d'), {
                type: 'line',
                data: {
                    labels: trendData.labels,
                    datasets: [
                        {
                            label: '{{ __("crm.dashboard.leads_label") }}',
                            data: trendData.leads,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.12)',
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            pointBackgroundColor: '#3B82F6',
                            pointRadius: 4,
                            yAxisID: 'yLeads'
                        },
                        {
                            label: '{{ __("crm.dashboard.won_revenue") }} (' + currencySymbol + ')',
                            data: trendData.revenue,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.12)',
                            borderWidth: 2.5,
                            tension: 0.35,
                            fill: true,
                            pointBackgroundColor: '#10B981',
                            pointRadius: 4,
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
                            labels: { font: { family: 'Inter', size: 12, weight: '500' }, usePointStyle: true, boxWidth: 8 }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            padding: 10,
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            titleFont: { family: 'Inter', size: 12, weight: 'bold' },
                            bodyFont: { family: 'Inter', size: 12 }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Inter', size: 11 } }
                        },
                        yLeads: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: '{{ __("crm.dashboard.leads_label") }}', font: { family: 'Inter', size: 11, weight: '600' } },
                            grid: { borderDash: [2, 4], color: 'rgba(0,0,0,0.06)' }
                        },
                        yRevenue: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: '{{ __("crm.dashboard.won_revenue") }}', font: { family: 'Inter', size: 11, weight: '600' } },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        }

        // 2. Enterprise Interactive Funnel Horizontal Bar Chart
        const funnelData = @json($funnelDetailed['stages']);
        const funnelEl = document.getElementById('crmFunnelChart');
        if (funnelEl) {
            const funnelLabels = funnelData.map(s => s.name);
            const funnelCounts = funnelData.map(s => s.count);
            const funnelColors = ['#3B82F6', '#06B6D4', '#F59E0B', '#8B5CF6', '#10B981'];

            new Chart(funnelEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: funnelLabels,
                    datasets: [{
                        label: '{{ __("crm.dashboard.leads_label") }} / {{ __("crm.dashboard.active_opps") }}',
                        data: funnelCounts,
                        backgroundColor: funnelColors,
                        borderRadius: 6,
                        barThickness: 24
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const stg = funnelData[context.dataIndex];
                                    let str = ' ' + context.parsed.x + ' (' + stg.pct_of_total + '% {{ __("crm.dashboard.of_top_funnel") }})';
                                    if (stg.value > 0) {
                                        str += ' | ' + currencySymbol + ' ' + Number(stg.value).toLocaleString();
                                    }
                                    return str;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { borderDash: [2, 4], color: 'rgba(0,0,0,0.06)' },
                            ticks: { font: { family: 'Inter', size: 11 } }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { family: 'Inter', size: 11, weight: '500' } }
                        }
                    }
                }
            });
        }

        // 3. Interactive Campaign Funnel Data Handler
        const campaignFunnelData = {
            all: @json($campaignFunnel),
            items: @json($campaignPerformance)
        };
        const currencySym = window.AppCurrency?.symbol || @json(active_currency_symbol());

        window.updateCampaignFunnel = function(val) {
            let data = {};
            if (val === 'all') {
                data = campaignFunnelData.all;
            } else {
                const item = campaignFunnelData.items[val];
                if (!item) return;
                const leads = Number(item.total_leads) || 0;
                const qual = Number(item.qualified_leads) || 0;
                const contacted = qual > 0 ? qual : (leads > 0 ? 1 : 0);
                const deals = Number(item.converted_deals) || 0;
                const won = Number(item.won_deals) || 0;
                const conv = Number(item.conversion_rate) || 0;
                const rev = Number(item.won_revenue) || 0;

                data = {
                    total_leads: leads,
                    contacted: contacted,
                    qualified: qual,
                    deals: deals,
                    proposals: deals,
                    won: won,
                    overall_conv_rate: conv,
                    revenue: rev
                };
            }

            // Update SVG numbers
            const txt0 = document.getElementById('funnelTxt0');
            const txt1 = document.getElementById('funnelTxt1');
            const txt2 = document.getElementById('funnelTxt2');
            const txt3 = document.getElementById('funnelTxt3');
            const txt4 = document.getElementById('funnelTxt4');
            const txt5 = document.getElementById('funnelTxt5');

            if (txt0) txt0.textContent = data.total_leads;
            if (txt1) txt1.textContent = data.contacted;
            if (txt2) txt2.textContent = data.qualified;
            if (txt3) txt3.textContent = data.deals;
            if (txt4) txt4.textContent = data.proposals;
            if (txt5) txt5.textContent = data.won;

            // Update legend numbers
            const l0 = document.getElementById('legendCnt0');
            const l1 = document.getElementById('legendCnt1');
            const l2 = document.getElementById('legendCnt2');
            const l3 = document.getElementById('legendCnt3');
            const l4 = document.getElementById('legendCnt4');
            const l5 = document.getElementById('legendCnt5');

            if (l0) l0.textContent = Number(data.total_leads).toLocaleString();
            if (l1) l1.textContent = Number(data.contacted).toLocaleString();
            if (l2) l2.textContent = Number(data.qualified).toLocaleString();
            if (l3) l3.textContent = Number(data.deals).toLocaleString();
            if (l4) l4.textContent = Number(data.proposals).toLocaleString();
            if (l5) l5.textContent = Number(data.won).toLocaleString();

            // Update stats
            const overallEl = document.getElementById('funnelOverallConv');
            if (overallEl) overallEl.textContent = data.overall_conv_rate + '%';

            const revEl = document.getElementById('funnelRevenueNote');
            if (revEl) {
                const revFormatted = Number(data.revenue || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                revEl.innerHTML = '{{ __("crm.dashboard.won_revenue") }}: <b class="text-dark">' + currencySym + ' ' + revFormatted + '</b>';
            }
        };

        // 4. Lead Acquisition Channels Doughnut Chart
        const sourceData = @json($sourceBreakdown);
        const sourceEl = document.getElementById('crmSourceChart');
        if (sourceEl && Object.keys(sourceData).length > 0) {
            const srcLabels = Object.keys(sourceData);
            const srcValues = Object.values(sourceData);
            const srcColors = ['#10B981', '#3B82F6', '#06B6D4', '#F59E0B', '#64748B', '#8B5CF6'];

            new Chart(sourceEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: srcLabels,
                    datasets: [{
                        data: srcValues,
                        backgroundColor: srcColors,
                        borderWidth: 2,
                        borderColor: '#FFFFFF',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = srcValues.reduce((a, b) => a + b, 0) || 1;
                                    const val = context.parsed;
                                    const pct = Math.round((val / total) * 100);
                                    return ' ' + context.label + ': ' + val + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    });
</script>
@endif
@endpush
