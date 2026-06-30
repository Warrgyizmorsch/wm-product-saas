@extends('layouts.duralux')

@section('title', 'Routing Management | SaaS ERP')
@section('page-title', 'Routing Master Data')
@section('breadcrumb', 'Routings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    @can('create', App\Domains\Production\Models\Routing::class)
        <a href="{{ route('production.routing.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Create New Routing
        </a>
    @endcan
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        <!-- Success & Error Alerts -->
        @if (session('success'))
            <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                <p class="fs-12 mb-0">{{ session('success') }}</p>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        @if (session('error'))
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                <p class="fs-12 mb-0">{{ session('error') }}</p>
            </x-ui.alert>
            <div class="mb-4"></div>
        @endif

        <!-- Inlined Filters Section -->
        <form method="GET" action="{{ route('production.routing.index') }}" class="mb-4 pb-3 border-bottom">
            <div class="row g-3">
                <div class="col-md-4">
                    <x-ui.input name="search" placeholder="Search number, name, or SKU..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <x-ui.select name="product_id" :options="['' => 'All Finished Products'] + $products->pluck('name', 'id')->toArray()" selected="{{ request('product_id') }}" data-select2-selector="default" />
                </div>
                <div class="col-md-3">
                    <x-ui.select name="status" :options="[
                        '' => 'All Statuses',
                        'draft' => 'Draft',
                        'pending_approval' => 'Pending Approval',
                        'active' => 'Active',
                        'historical' => 'Historical',
                        'cancelled' => 'Cancelled'
                    ]" selected="{{ request('status') }}" data-select2-selector="default" />
                </div>
                <div class="col-md-2 d-flex align-items-start">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-secondary h-40">
                            <i class="feather-filter me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Routings Table (Dense & Thin row) -->
        <div class="table-responsive">
            <table class="erp-thin-table">
                <thead>
                    <tr>
                        <th style="width: 12%">Routing Number</th>
                        <th style="width: 18%">Routing Name</th>
                        <th style="width: 22%">Product to Manufacture</th>
                        <th style="width: 8%">Version</th>
                        <th style="width: 8%" class="text-center">Operations</th>
                        <th style="width: 10%">Effective From</th>
                        <th style="width: 10%">Effective To</th>
                        <th style="width: 6%">Type</th>
                        <th style="width: 6%">Status</th>
                        <th style="width: 5%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($routings as $routing)
                        <tr>
                            <td class="align-middle">
                                <a href="{{ route('production.routing.show', $routing->id) }}" class="fw-bold text-primary">
                                    {{ $routing->routing_number }}
                                </a>
                            </td>
                            <td class="align-middle text-dark fw-medium">{{ $routing->name }}</td>
                            <td class="align-middle">
                                @if ($routing->product)
                                    <span class="fw-semibold text-dark">{{ $routing->product->name }}</span>
                                    <small class="text-muted d-block fs-10">{{ $routing->product->sku }}</small>
                                @else
                                    <span class="text-muted">No Product</span>
                                @endif
                            </td>
                            <td class="align-middle">
                                {{ $routing->version }}
                                @if ($routing->revision > 0)
                                    <small class="text-muted">(Rev {{ $routing->revision }})</small>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                <span class="badge bg-soft-info text-info rounded-pill px-2 py-1 fw-bold">
                                    {{ $routing->operations_count }}
                                </span>
                            </td>
                            <td class="align-middle text-muted">{{ $routing->effective_from ? $routing->effective_from->format('Y-m-d') : 'Immediate' }}</td>
                            <td class="align-middle text-muted">{{ $routing->effective_to ? $routing->effective_to->format('Y-m-d') : 'Indefinite' }}</td>
                            <td class="align-middle">
                                @if ($routing->is_default)
                                    <span class="badge bg-soft-success text-success px-2 py-1 rounded-pill fs-10">Primary</span>
                                @else
                                    <span class="badge bg-soft-warning text-warning px-2 py-1 rounded-pill fs-10">Alt</span>
                                @endif
                            </td>
                            <td class="align-middle">
                                @if ($routing->isDraft())
                                    <span class="badge bg-soft-secondary text-secondary rounded-pill px-2 py-1">Draft</span>
                                @elseif ($routing->isPendingApproval())
                                    <span class="badge bg-soft-warning text-warning rounded-pill px-2 py-1">Pending</span>
                                @elseif ($routing->isActive())
                                    <span class="badge bg-soft-success text-success rounded-pill px-2 py-1">Active</span>
                                @elseif ($routing->isHistorical())
                                    <span class="badge bg-soft-info text-info rounded-pill px-2 py-1">Historical</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger rounded-pill px-2 py-1">Cancelled</span>
                                @endif
                            </td>
                            <td class="text-end align-middle">
                                <div class="d-inline-flex gap-1">
                                    <x-ui.icon-btn href="{{ route('production.routing.show', $routing->id) }}" variant="light" size="sm" icon="feather-eye" title="View Details" />
                                    @can('update', $routing)
                                        <x-ui.icon-btn href="{{ route('production.routing.edit', $routing->id) }}" variant="light" size="sm" icon="feather-edit" title="Edit" />
                                    @endcan
                                    @if ($routing->isDraft())
                                        @can('delete', $routing)
                                            <form action="{{ route('production.routing.destroy', $routing->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this draft routing?');" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.icon-btn type="submit" variant="light" size="sm" icon="feather-trash-2" class="text-danger" title="Delete Draft" />
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="feather-info me-2"></i>No process routings found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
