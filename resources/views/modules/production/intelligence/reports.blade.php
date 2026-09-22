@extends('layouts.duralux')

@section('title', __('production.manufacturing_reports_bi') . ' | SaaS ERP')
@section('page-title', __('production.manufacturing_performance_reports'))
@section('breadcrumb', __('production.intelligence_reports'))

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="feather-printer me-2 text-primary"></i>{{ __('production.select_report_to_generate') }}</h5>
                <p class="text-muted fs-13 mb-0">Select a report and configure filters or date periods below to generate detailed intelligence views and CSV exports.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- Custom Filter Component (just like used in BOM) -->
                <form method="GET" action="{{ route('production.intelligence.reports.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.date_start') }}</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date_start" :value="request('date_start', now()->subMonth()->toDateString())" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.date_end') }}</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date_end" :value="request('date_end', now()->toDateString())" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <x-ui.button href="{{ route('production.intelligence.reports.index') }}" variant="light"  class="border">
                                {{ __('production.reset') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" >
                                {{ __('production.apply_filters') }}
                            </x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Active Filter Period Badge --}}
        <div class="d-flex align-items-center gap-2 mb-4">
            <span class="text-muted fs-12 fw-medium">Active Report Period:</span>
            <span class="badge bg-soft-primary text-primary fs-12 px-2 py-1 border border-primary-subtle">
                <i class="feather-calendar me-1"></i>
                {{ request('date_start', now()->subMonth()->toDateString()) }} &nbsp;to&nbsp; {{ request('date_end', now()->toDateString()) }}
            </span>
            @if(request('date_start') || request('date_end'))
                <a href="{{ route('production.intelligence.reports.index') }}" class="text-muted fs-12 ms-1 text-decoration-none" title="Reset date filter">
                    <i class="feather-x-circle"></i> Reset
                </a>
            @endif
        </div>

        <div class="row g-4">
            {{-- Machine Performance --}}
            <div class="col-md-4">
                <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                    <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded mb-3">
                        <i class="feather-cpu"></i>
                    </div>
                    <h5 class="fw-bold text-dark">{{ __('production.machine_performance_report') }}</h5>
                    <p class="text-muted fs-13">{{ __('production.machine_report_desc') }}</p>
                    
                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'machine') }}" target="_blank" class="mt-3 fs-13 text-dark report-form">
                        <input type="hidden" name="date_start" value="{{ request('date_start', now()->subMonth()->toDateString()) }}">
                        <input type="hidden" name="date_end" value="{{ request('date_end', now()->toDateString()) }}">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('production.col_machine') ?? 'Machine'" name="machine_id">
                                <option value="">{{ __('production.all_machines') }}</option>
                                @foreach($machines as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'machine') }}'; this.form.target='_blank';" variant="primary"  class="flex-fill">{{ __('production.view_report') }}</x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'machine') }}'; this.form.target='_blank';" name="print" value="1" variant="light"  class="border" title="{{ __('production.print') }}"><i class="feather-printer"></i></x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'machine') }}'; this.form.target='_self';" variant="light"  class="border" title="{{ __('production.export_csv') }}"><i class="feather-download"></i></x-ui.button>
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
                    
                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'work-center') }}" target="_blank" class="mt-3 fs-13 text-dark report-form">
                        <input type="hidden" name="date_start" value="{{ request('date_start', now()->subMonth()->toDateString()) }}">
                        <input type="hidden" name="date_end" value="{{ request('date_end', now()->toDateString()) }}">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('production.col_work_center')" name="work_center_id">
                                <option value="">{{ __('production.all_work_centers') }}</option>
                                @foreach($workCenters as $wc)
                                    <option value="{{ $wc->id }}">{{ $wc->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'work-center') }}'; this.form.target='_blank';" variant="primary"  class="flex-fill">{{ __('production.view_report') }}</x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'work-center') }}'; this.form.target='_blank';" name="print" value="1" variant="light"  class="border" title="{{ __('production.print') }}"><i class="feather-printer"></i></x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'work-center') }}'; this.form.target='_self';" variant="light"  class="border" title="{{ __('production.export_csv') }}"><i class="feather-download"></i></x-ui.button>
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
                    
                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'downtime') }}" target="_blank" class="mt-3 fs-13 text-dark report-form">
                        <input type="hidden" name="date_start" value="{{ request('date_start', now()->subMonth()->toDateString()) }}">
                        <input type="hidden" name="date_end" value="{{ request('date_end', now()->toDateString()) }}">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('production.col_machine') ?? 'Machine'" name="machine_id">
                                <option value="">{{ __('production.all_machines') }}</option>
                                @foreach($machines as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'downtime') }}'; this.form.target='_blank';" variant="primary"  class="flex-fill">{{ __('production.view_report') }}</x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'downtime') }}'; this.form.target='_blank';" name="print" value="1" variant="light"  class="border" title="{{ __('production.print') }}"><i class="feather-printer"></i></x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'downtime') }}'; this.form.target='_self';" variant="light"  class="border" title="{{ __('production.export_csv') }}"><i class="feather-download"></i></x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            {{-- Production Order Summary & Output --}}
            <div class="col-md-4">
                <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                    <div class="avatar-text avatar-lg bg-soft-info text-info rounded mb-3">
                        <i class="feather-package"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Production Orders & Output</h5>
                    <p class="text-muted fs-13">Cross-order summary of planned vs produced volume, scrap, yield %, and completion status.</p>
                    
                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'production-orders') }}" target="_blank" class="mt-3 fs-13 text-dark report-form">
                        <input type="hidden" name="date_start" value="{{ request('date_start', now()->subMonth()->toDateString()) }}">
                        <input type="hidden" name="date_end" value="{{ request('date_end', now()->toDateString()) }}">
                        <div class="mb-2">
                            <x-ui.odoo-form-ui type="select" label="Product" name="product_id">
                                <option value="">All Products</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Status" name="status">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $st)
                                    <option value="{{ $st }}">{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'production-orders') }}'; this.form.target='_blank';" variant="primary"  class="flex-fill">View Report</x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'production-orders') }}'; this.form.target='_blank';" name="print" value="1" variant="light"  class="border" title="Print"><i class="feather-printer"></i></x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'production-orders') }}'; this.form.target='_self';" variant="light"  class="border" title="Export CSV"><i class="feather-download"></i></x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            {{-- Material Consumption & Variance --}}
            <div class="col-md-4">
                <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                    <div class="avatar-text avatar-lg bg-soft-warning text-warning rounded mb-3">
                        <i class="feather-layers"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Material Consumption & Variance</h5>
                    <p class="text-muted fs-13">Compare planned vs actual issued raw materials with quantity variances and cost impacts.</p>
                    
                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'material-consumption') }}" target="_blank" class="mt-3 fs-13 text-dark report-form">
                        <input type="hidden" name="date_start" value="{{ request('date_start', now()->subMonth()->toDateString()) }}">
                        <input type="hidden" name="date_end" value="{{ request('date_end', now()->toDateString()) }}">
                        <div class="mb-2">
                            <x-ui.odoo-form-ui type="select" label="Production Order" name="order_id">
                                <option value="">All Orders</option>
                                @foreach($orders as $ord)
                                    <option value="{{ $ord->id }}">{{ $ord->order_number }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Component / Raw Material" name="material_id">
                                <option value="">All Materials</option>
                                @foreach($materials as $mat)
                                    <option value="{{ $mat->id }}">{{ $mat->name }} ({{ $mat->sku }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'material-consumption') }}'; this.form.target='_blank';" variant="primary"  class="flex-fill">View Report</x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'material-consumption') }}'; this.form.target='_blank';" name="print" value="1" variant="light"  class="border" title="Print"><i class="feather-printer"></i></x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'material-consumption') }}'; this.form.target='_self';" variant="light"  class="border" title="Export CSV"><i class="feather-download"></i></x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            {{-- Production Cost & Variance --}}
            <div class="col-md-4">
                <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                    <div class="avatar-text avatar-lg bg-soft-success text-success rounded mb-3">
                        <i class="feather-dollar-sign"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Production Cost & Variance</h5>
                    <p class="text-muted fs-13">Cross-order manufacturing cost breakdown comparing planned vs actual material, labor, machine & overhead.</p>
                    
                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'cost-variance') }}" target="_blank" class="mt-3 fs-13 text-dark report-form">
                        <input type="hidden" name="date_start" value="{{ request('date_start', now()->subMonth()->toDateString()) }}">
                        <input type="hidden" name="date_end" value="{{ request('date_end', now()->toDateString()) }}">
                        <div class="mb-2">
                            <x-ui.odoo-form-ui type="select" label="Product" name="product_id">
                                <option value="">All Products</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Status" name="status">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $st)
                                    <option value="{{ $st }}">{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'cost-variance') }}'; this.form.target='_blank';" variant="primary"  class="flex-fill">View Report</x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'cost-variance') }}'; this.form.target='_blank';" name="print" value="1" variant="light"  class="border" title="Print"><i class="feather-printer"></i></x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'cost-variance') }}'; this.form.target='_self';" variant="light"  class="border" title="Export CSV"><i class="feather-download"></i></x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>
            {{-- Production Order Detail (Job Card / Shop Traveler) --}}
            <div class="col-md-4">
                <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                    <div class="avatar-text avatar-lg bg-soft-dark text-dark rounded mb-3">
                        <i class="feather-file-text"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Production Order Detail</h5>
                    <p class="text-muted fs-13">Full job card for a single order — operation stages, work center locations, material consumption, scrap events, and live WIP position.</p>

                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'order-detail') }}" target="_blank" class="mt-3 fs-13 text-dark report-form">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Production Order" name="order_id" :required="true">
                                <option value="">— Select an Order —</option>
                                @foreach($orders as $ord)
                                    <option value="{{ $ord->id }}">{{ $ord->order_number }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <x-ui.button type="submit" onclick="if(!this.form.order_id.value){alert('Please select an order first.');return false;}this.form.action='{{ route('production.intelligence.reports.show', 'order-detail') }}'; this.form.target='_blank';" variant="primary" class="flex-fill">View Report</x-ui.button>
                            <x-ui.button type="submit" onclick="if(!this.form.order_id.value){alert('Please select an order first.');return false;}this.form.action='{{ route('production.intelligence.reports.show', 'order-detail') }}'; this.form.target='_blank';" name="print" value="1" variant="light" class="border" title="Print"><i class="feather-printer"></i></x-ui.button>
                            <x-ui.button type="submit" onclick="if(!this.form.order_id.value){alert('Please select an order first.');return false;}this.form.action='{{ route('production.intelligence.reports.export', 'order-detail') }}'; this.form.target='_self';" variant="light" class="border" title="Export CSV"><i class="feather-download"></i></x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            {{-- Sales Order Tracking Report (Order-to-Delivery Pipeline) --}}
            <div class="col-md-4">
                <x-ui.card class="border border-light shadow-sm h-100 touch-card">
                    <div class="avatar-text avatar-lg bg-soft-info text-info rounded mb-3">
                        <i class="feather-activity"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Sales Order Tracking</h5>
                    <p class="text-muted fs-13">End-to-end order fulfillment pipeline: Sales Order ➔ MO ➔ Requisition & Indent ➔ PO ➔ Dispatch & Delivery.</p>

                    <form method="GET" action="{{ route('production.intelligence.reports.show', 'sales-order-tracking') }}" target="_blank" class="mt-3 fs-13 text-dark report-form">
                        <input type="hidden" name="date_start" value="{{ request('date_start', now()->subMonths(3)->toDateString()) }}">
                        <input type="hidden" name="date_end" value="{{ request('date_end', now()->toDateString()) }}">
                        <div class="mb-2">
                            <x-ui.odoo-form-ui type="select" label="Customer" name="customer_id">
                                <option value="">All Customers</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="SO Status" name="status">
                                <option value="">All Statuses</option>
                                <option value="Open">Open</option>
                                <option value="Confirmed">Confirmed</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Partially Invoiced">Partially Invoiced</option>
                                <option value="Completed">Completed</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'sales-order-tracking') }}'; this.form.target='_blank';" variant="primary" class="flex-fill">View Report</x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.show', 'sales-order-tracking') }}'; this.form.target='_blank';" name="print" value="1" variant="light" class="border" title="Print"><i class="feather-printer"></i></x-ui.button>
                            <x-ui.button type="submit" onclick="this.form.action='{{ route('production.intelligence.reports.export', 'sales-order-tracking') }}'; this.form.target='_self';" variant="light" class="border" title="Export CSV"><i class="feather-download"></i></x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection
