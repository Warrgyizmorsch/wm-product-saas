@extends('layouts.duralux')

@section('title', __('ui.dashboard') . ' | SaaS ERP')
@section('page-title', __('ui.executive_dashboard'))
@section('breadcrumb', __('ui.executive_dashboard'))

@section('page-actions')
    <div id="reportrange" class="reportrange-picker d-flex align-items-center">
        <span class="reportrange-picker-field"></span>
    </div>
    <div class="dropdown filter-dropdown">
        <a class="btn btn-md btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
            <i class="feather-filter me-2"></i>
            <span>{{ __('ui.filter') }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-end">
            @foreach ([__('ui.company'), __('ui.branch'), __('ui.department'), __('ui.owner'), __('ui.status')] as $filter)
                <div class="dropdown-item">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="filter{{ $filter }}" checked>
                        <label class="custom-control-label c-pointer" for="filter{{ $filter }}">{{ $filter }}</label>
                    </div>
                </div>
            @endforeach
            <div class="dropdown-divider"></div>
            <a href="javascript:void(0);" class="dropdown-item">
                <i class="feather-plus me-3"></i>
                <span>{{ __('ui.create_new') }}</span>
            </a>
            <a href="javascript:void(0);" class="dropdown-item">
                <i class="feather-filter me-3"></i>
                <span>{{ __('ui.manage_filter') }}</span>
            </a>
        </div>
    </div>
    <button type="button" class="btn btn-primary">
        <i class="feather-plus me-2"></i>{{ __('ui.new_workflow') }}
    </button>
@endsection

@section('content')
    <div class="row g-4">
        @foreach ([
            ['label' => __('ui.monthly_revenue'), 'value' => '$284.6K', 'trend' => '+12.4%', 'icon' => 'feather-trending-up', 'tone' => 'primary'],
            ['label' => __('ui.open_sales_orders'), 'value' => '428', 'trend' => '36 urgent', 'icon' => 'feather-shopping-cart', 'tone' => 'warning'],
            ['label' => __('ui.inventory_value'), 'value' => '$1.82M', 'trend' => '18 low stock', 'icon' => 'feather-box', 'tone' => 'success'],
            ['label' => __('ui.payroll_this_month'), 'value' => '$96.2K', 'trend' => '214 staff', 'icon' => 'feather-users', 'tone' => 'info'],
        ] as $metric)
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fs-12 text-uppercase">{{ $metric['label'] }}</span>
                                <h3 class="mb-1 mt-2">{{ $metric['value'] }}</h3>
                                <span class="text-{{ $metric['tone'] }} fs-12 fw-semibold">{{ $metric['trend'] }}</span>
                            </div>
                            <div class="avatar-text avatar-lg bg-soft-{{ $metric['tone'] }} text-{{ $metric['tone'] }}">
                                <i class="{{ $metric['icon'] }}"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xxl-8">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">{{ __('ui.erp_module_readiness') }}</h5>
                    <div class="card-header-action">
                        <a href="#" class="avatar-text avatar-md" data-bs-toggle="refresh"><i class="feather-refresh-cw"></i></a>
                    </div>
                </div>
                <div class="card-body">
                    @foreach ([
                        ['name' => __('ui.crm_and_sales'), 'value' => 25, 'color' => 'primary'],
                        ['name' => __('ui.inventory_purchase'), 'value' => 18, 'color' => 'success'],
                        ['name' => __('ui.hrms_payroll'), 'value' => 12, 'color' => 'warning'],
                        ['name' => __('ui.accounting_core'), 'value' => 8, 'color' => 'danger'],
                    ] as $module)
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold text-dark">{{ $module['name'] }}</span>
                                <span class="text-muted">{{ $module['value'] }}%</span>
                            </div>
                            <div class="progress ht-8">
                                <div class="progress-bar bg-{{ $module['color'] }}" style="width: {{ $module['value'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-xxl-4">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">{{ __('ui.platform_setup') }}</h5>
                </div>
                <div class="card-body">
                    @foreach ([
                        [__('ui.tenant_isolation'), __('ui.done'), 'success'],
                        [__('ui.erp_app_shell'), __('ui.done'), 'success'],
                        ['Authentication', __('ui.next'), 'warning'],
                        [__('ui.roles_permissions'), __('ui.next'), 'warning'],
                        ['Audit logging', __('ui.planned'), 'secondary'],
                    ] as $item)
                        <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                            <span class="fw-semibold text-dark">{{ $item[0] }}</span>
                            <span class="badge bg-soft-{{ $item[2] }} text-{{ $item[2] }}">{{ $item[1] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">{{ __('ui.large_scale_erp_domains') }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 erp-domain-grid">
                @foreach ([__('ui.crm'), __('ui.sales'), __('ui.inventory'), __('ui.purchase'), __('ui.production'), __('ui.hrms'), __('ui.projects'), __('ui.accounting'), __('ui.administration')] as $domain)
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                        <a href="#" class="erp-domain-tile">
                            <span>{{ $domain }}</span>
                            <i class="feather-arrow-up-right"></i>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var start = moment().subtract(29, 'days');
            var end = moment();

            function setRangeLabel(startDate, endDate) {
                $('.reportrange-picker-field').html(startDate.format('MMM D, YYYY') + ' - ' + endDate.format('MMM D, YYYY'));
            }

            $('#reportrange').daterangepicker({
                startDate: start,
                endDate: end,
                ranges: {
                    Today: [moment(), moment()],
                    Yesterday: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            }, setRangeLabel);

            setRangeLabel(start, end);
        });
    </script>
@endpush
