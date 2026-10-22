@extends('layouts.duralux')

@section('title', 'External Worker Pay Grades | SaaS ERP')
@section('page-title', 'External Worker Pay Grades')
@section('breadcrumb', 'Pay Grades')

@section('page-actions')
    @can('create', App\Domains\Production\Models\ExternalWorkerPayGrade::class)
        <a href="{{ route('production.pay-grades.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>{{ __('production.create_pay_grade') }}
        </a>
    @endcan
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- Toolbar: Search, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Registered Pay Grades</h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.pay-grades.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i>{{ __('production.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                            <x-ui.input name="search" placeholder="Search by code, name, description..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="active" @selected(request('status') === 'active')>{{ __('production.status_active') }}</option>
                                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('production.wc_status_inactive') }}</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.pay-grades.index') }}" class="btn btn-sm btn-light border">{{ __('production.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('production.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Pay Grades Table -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 15%">{{ __('production.pay_grade_code') }}</th>
                    <th style="width: 25%">{{ __('production.pay_grade_name') }}</th>
                    <th style="width: 15%">{{ __('production.hourly_rate') }}</th>
                    <th style="width: 20%">Description</th>
                    <th style="width: 12%">Allocations</th>
                    <th style="width: 8%">{{ __('production.status') }}</th>
                    <th class="text-end" style="width: 5%">{{ __('production.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payGrades as $payGrade)
                    <tr>
                        <td class="font-monospace fw-bold text-dark">
                            <a href="{{ route('production.pay-grades.edit', $payGrade->id) }}" class="text-dark hover-primary">
                                {{ $payGrade->code }}
                            </a>
                        </td>
                        <td class="fw-semibold text-dark">{{ $payGrade->name }}</td>
                        <td class="font-monospace text-dark fw-bold">
                            {{ format_currency((float) $payGrade->hourly_rate) }} <span class="fs-10 text-muted fw-normal">/ hr</span>
                        </td>
                        <td class="text-muted fs-12">
                            {{ $payGrade->description ? \Illuminate\Support\Str::limit($payGrade->description, 50) : '—' }}
                        </td>
                        <td>
                            @if(($payGrade->active_allocations_count ?? 0) > 0)
                                <span class="badge bg-soft-info text-info rounded-pill px-2 py-0.5" title="Active worker allocations referencing this pay grade">
                                    <i class="feather-users me-1 fs-10"></i>{{ $payGrade->active_allocations_count }} Active
                                </span>
                            @else
                                <span class="text-muted fs-11">0 Active</span>
                            @endif
                        </td>
                        <td>
                            @if($payGrade->active)
                                <span class="erp-badge-active">{{ __('production.status_active') }}</span>
                            @else
                                <span class="erp-badge-draft">{{ __('production.wc_status_inactive') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <x-ui.action-dropdown>
                                <li>
                                    <a href="{{ route('production.pay-grades.edit', $payGrade->id) }}" class="dropdown-item">
                                        <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('production.edit') }}
                                    </a>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('production.pay-grades.toggle-status', $payGrade->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item">
                                            @if($payGrade->active)
                                                <i class="feather-slash me-2 text-warning fs-12"></i>Deactivate
                                            @else
                                                <i class="feather-check me-2 text-success fs-12"></i>Activate
                                            @endif
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('production.pay-grades.destroy', $payGrade->id) }}"
                                          onsubmit="return confirm('Are you sure you want to delete pay grade \'{{ $payGrade->name }}\'?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('production.delete_permanent') }}
                                        </button>
                                    </form>
                                </li>
                            </x-ui.action-dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="feather-info me-2 fs-16"></i>No external worker pay grades configured.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-3">
            {{ $payGrades->links() }}
        </div>
    </div>
@endsection
