@extends('layouts.duralux')

@section('title', 'Payment Terms Master | SaaS ERP')
@section('page-title', 'Payment Terms Master')
@section('breadcrumb', 'Workspace / Tenant Console / Payment Terms')

@section('page-actions')
    <x-ui.button href="{{ route('platform.payment-terms.create') }}" variant="primary" icon="feather-plus">
        NEW PAYMENT TERM
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'due_days');
        $sortOrder = request('sort_order', 'asc');
    @endphp

    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
        {{-- Toolbar --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">Payment Terms Directory</h5>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Search Box -->
                <form method="GET" action="{{ route('platform.payment-terms.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 250px;">
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search payment terms..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('platform.payment-terms.index') }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="SORT">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'due_days', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'due_days' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Due Days (Shortest First)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'due_days', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'due_days' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Due Days (Longest First)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Term Name (A - Z)</span>
                    </a>
                </x-ui.sort-dropdown>
            </div>
        </div>

        {{-- Odoo Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="paymentTermsTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="width: 40px; background-color: #e8ecf1 !important;" class="text-center">#</th>
                        <th style="background-color: #e8ecf1 !important;">Payment Term Name</th>
                        <th style="background-color: #e8ecf1 !important;">Code</th>
                        <th style="background-color: #e8ecf1 !important;" class="text-center">Due Days</th>
                        <th style="background-color: #e8ecf1 !important;">Description</th>
                        <th style="background-color: #e8ecf1 !important;" class="text-center">Status</th>
                        <th style="width: 5%; background-color: #e8ecf1 !important;" class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($paymentTerms as $index => $term)
                        <tr>
                            <td class="text-center font-monospace text-muted">{{ $paymentTerms->firstItem() + $index }}</td>
                            <td>
                                <span class="fw-bold text-dark d-block">{{ $term->name }}</span>
                            </td>
                            <td>
                                <span class="badge bg-soft-primary text-primary font-monospace fs-11 px-2 py-1">{{ $term->code }}</span>
                            </td>
                            <td class="text-center font-monospace fw-bold text-dark fs-13">
                                @if($term->due_days == 0)
                                    <span class="badge bg-soft-success text-success">Immediate (0 Days)</span>
                                @else
                                    {{ $term->due_days }} Days
                                @endif
                            </td>
                            <td class="fs-12 text-muted">
                                {{ Str::limit($term->description, 65) ?: 'N/A' }}
                            </td>
                            <td class="text-center">
                                @if ($term->is_active)
                                    <x-ui.status-badge status="active" label="Active" size="sm" />
                                @else
                                    <x-ui.status-badge status="inactive" label="Inactive" size="sm" />
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown>
                                    <li>
                                        <a href="{{ route('platform.payment-terms.edit', $term) }}" class="dropdown-item fs-12 py-1.5">
                                            <i class="feather-edit-2 me-2 text-primary"></i>Edit Term
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <form action="{{ route('platform.payment-terms.toggle-status', $term) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item fs-12 py-1.5 text-{{ $term->is_active ? 'warning' : 'success' }}">
                                                <i class="feather-{{ $term->is_active ? 'pause-circle' : 'play-circle' }} me-2"></i>
                                                Mark as {{ $term->is_active ? 'Inactive' : 'Active' }}
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="{{ route('platform.payment-terms.destroy', $term) }}" method="POST" onsubmit="return confirm('Delete payment term {{ $term->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item fs-12 py-1.5 text-danger">
                                                <i class="feather-trash-2 me-2 text-danger"></i>Delete Term
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="feather-credit-card fs-30 d-block mb-2 text-muted"></i>
                                No payment terms defined.
                                <div class="mt-2">
                                    <a href="{{ route('platform.payment-terms.create') }}" class="btn btn-sm btn-primary"><i class="feather-plus me-1"></i>Create Payment Term</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        @if($paymentTerms->hasPages())
            <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                <span class="fs-12 text-muted">Showing {{ $paymentTerms->firstItem() }} to {{ $paymentTerms->lastItem() }} of {{ $paymentTerms->total() }} terms</span>
                {{ $paymentTerms->links() }}
            </div>
        @endif
    </div>
@endsection
