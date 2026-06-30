@extends('layouts.duralux')

@section('title', 'Routing Management | SaaS ERP')
@section('page-title', 'Routing Master Data')
@section('breadcrumb', 'Routings')

@section('page-actions')
    @can('create', App\Domains\Production\Models\Routing::class)
        <a href="{{ route('production.routing.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Create New Routing
        </a>
    @endcan
@endsection

@section('content')
    <!-- Success & Error Alerts -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                    <p class="fs-12 mb-0">{{ session('error') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filters Card -->
    <x-ui.card class="mb-4">
        <form method="GET" action="{{ route('production.routing.index') }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <x-ui.input label="Search Routing" name="search" placeholder="Number, name, or finished product SKU..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-3">
                    <x-ui.select label="Filter by Product" name="product_id" :options="['' => 'All Products'] + $products->pluck('name', 'id')->toArray()" selected="{{ request('product_id') }}" />
                </div>
                <div class="col-md-3">
                    <x-ui.select label="Filter by Status" name="status" :options="[
                        '' => 'All Statuses',
                        'draft' => 'Draft',
                        'pending_approval' => 'Pending Approval',
                        'active' => 'Active',
                        'historical' => 'Historical',
                        'cancelled' => 'Cancelled'
                    ]" selected="{{ request('status') }}" />
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-light-brand h-42">
                            <i class="feather-filter me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </x-ui.card>

    <!-- Routings List Table -->
    <x-ui.card>
        <x-ui.table title="Manufacturing Process Routings" striped hoverable>
            <thead>
                <tr>
                    <th>Routing Number</th>
                    <th>Routing Name</th>
                    <th>Product to Manufacture</th>
                    <th>Ver</th>
                    <th class="text-center">Operations</th>
                    <th>Effective From</th>
                    <th>Effective To</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($routings as $routing)
                    <tr>
                        <td>
                            <a href="{{ route('production.routing.show', $routing->id) }}" class="fw-bold text-primary">
                                {{ $routing->routing_number }}
                            </a>
                        </td>
                        <td>{{ $routing->name }}</td>
                        <td>
                            @if ($routing->product)
                                <span class="fw-semibold text-dark">{{ $routing->product->name }}</span>
                                <small class="text-muted d-block">{{ $routing->product->sku }}</small>
                            @else
                                <span class="text-muted">No Product</span>
                            @endif
                        </td>
                        <td>
                            {{ $routing->version }}
                            @if ($routing->revision > 0)
                                <small class="text-muted">(Rev {{ $routing->revision }})</small>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-soft-info text-info rounded-pill">
                                {{ $routing->operations_count }}
                            </span>
                        </td>
                        <td>{{ $routing->effective_from ? $routing->effective_from->format('Y-m-d') : 'Immediate' }}</td>
                        <td>{{ $routing->effective_to ? $routing->effective_to->format('Y-m-d') : 'Indefinite' }}</td>
                        <td>
                            @if ($routing->is_default)
                                <span class="badge bg-soft-success text-success fs-10">Primary</span>
                            @else
                                <span class="badge bg-soft-warning text-warning fs-10">Alternative</span>
                            @endif
                        </td>
                        <td>
                            @if ($routing->isDraft())
                                <span class="badge bg-soft-secondary text-secondary">Draft</span>
                            @elseif ($routing->isPendingApproval())
                                <span class="badge bg-soft-warning text-warning">Pending Approval</span>
                            @elseif ($routing->isActive())
                                <span class="badge bg-soft-success text-success">Active</span>
                            @elseif ($routing->isHistorical())
                                <span class="badge bg-soft-info text-info">Historical</span>
                            @else
                                <span class="badge bg-soft-danger text-danger">Cancelled</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('production.routing.show', $routing->id) }}" class="btn btn-icon btn-light" title="View Details">
                                    <i class="feather-eye"></i>
                                </a>
                                @can('update', $routing)
                                    <a href="{{ route('production.routing.edit', $routing->id) }}" class="btn btn-icon btn-light" title="Edit">
                                        <i class="feather-edit"></i>
                                    </a>
                                @endcan
                                @if ($routing->isDraft())
                                    @can('delete', $routing)
                                        <form action="{{ route('production.routing.destroy', $routing->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this draft routing?');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-light text-danger" title="Delete Draft">
                                                <i class="feather-trash-2"></i>
                                            </button>
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
        </x-ui.table>
    </x-ui.card>
@endsection
