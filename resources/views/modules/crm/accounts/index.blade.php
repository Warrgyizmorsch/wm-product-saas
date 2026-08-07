@extends('layouts.duralux')

@section('title', 'Accounts (Companies) | SaaS ERP')
@section('page-title', 'CRM Accounts')
@section('breadcrumb', 'Accounts')

@section('page-actions')
    <x-ui.button href="{{ route('crm.accounts.create') }}" variant="primary" icon="feather-plus">
        Add New Account
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if ($errors->any())
            <div class="alert alert-danger mb-3 alert-dismissible fade show fs-12 py-2" role="alert">
                <ul class="mb-0 ps-3 text-start">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem 1rem;"></button>
            </div>
        @endif

        {{-- 1. Header: Title & Actions --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">Accounts Listing</h5>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box (HRMS Style) -->
                <form method="GET" action="{{ route('crm.accounts.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search account name, GSTIN, email, phone..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('crm.accounts.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <x-ui.view-switcher />

                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Latest Accounts</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Company Name (A - Z)</span>
                    </a>
                </x-ui.sort-dropdown>

                <form method="GET" action="{{ route('crm.accounts.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Company, GSTIN, Email..." value="{{ request('search') }}" />
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="{{ route('crm.accounts.index') }}" class="btn btn-xs btn-light border">Reset</a>
                            <button type="submit" class="btn btn-xs btn-primary" style="background-color: #1e40af; border-color: #1e40af;">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Active Filters Badges Row --}}
        @if(request('search'))
            <div class="d-flex align-items-center flex-wrap gap-2 mb-3 bg-light p-2 rounded border">
                <span class="fs-11 fw-bold text-uppercase text-muted me-1"><i class="feather-filter me-1"></i>Active Filters:</span>
                <span class="badge bg-white text-dark border font-monospace fs-11">
                    Search: "{{ request('search') }}"
                    <a href="{{ route('crm.accounts.index', request()->except('search')) }}" class="text-danger ms-1 text-decoration-none">×</a>
                </span>
                <a href="{{ route('crm.accounts.index') }}" class="text-muted fs-11 ms-auto fw-bold text-decoration-none">Clear All</a>
            </div>
        @endif

        {{-- 2. Data Table --}}
        <div class="card border-0 shadow-sm bg-white overflow-hidden" style="border-radius: 4px;">
            <div class="table-responsive">
                <table class="table odoo-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 140px;">ACCOUNT #</th>
                            <th>COMPANY NAME</th>
                            <th>GSTIN</th>
                            <th>PRIMARY CONTACT</th>
                            <th>PHONE / EMAIL</th>
                            <th class="text-end">DEALS</th>
                            <th class="text-end">LIFETIME REVENUE (LTV)</th>
                            <th class="text-end pe-3" style="width: 120px;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse($accounts as $acc)
                            <tr>
                                <td class="font-monospace fw-bold">
                                    <a href="{{ route('crm.accounts.show', $acc) }}" class="text-primary hover-underline">
                                        {{ $acc->account_number }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('crm.accounts.show', $acc) }}" class="fw-bold text-dark text-decoration-none hover-primary">
                                        {{ $acc->name }}
                                    </a>
                                    @if($acc->industry_type)
                                        <div class="text-muted fs-11 mt-0.5">{{ $acc->industry_type }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($acc->gstin)
                                        <span class="badge bg-light text-dark font-monospace border px-2 py-0.5 fs-11">{{ $acc->gstin }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php $pContact = $acc->primaryContact ?: $acc->contacts->first(); @endphp
                                    @if($pContact)
                                        <span class="fw-semibold text-dark">{{ $pContact->name }}</span>
                                        @if($pContact->designation)
                                            <div class="text-muted fs-11">{{ $pContact->designation }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($acc->phone)
                                        <div><i class="feather-phone me-1 text-muted fs-11"></i>{{ $acc->phone }}</div>
                                    @endif
                                    @if($acc->email)
                                        <div class="text-muted fs-11"><i class="feather-mail me-1 text-muted fs-11"></i>{{ $acc->email }}</div>
                                    @endif
                                    @if(!$acc->phone && !$acc->email)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @php
                                        $openCount = $acc->open_deals_count;
                                        $wonCount = $acc->won_deals_count;
                                        $totalDeals = $acc->deals->count();
                                    @endphp
                                    @if($totalDeals > 0)
                                        <div class="d-inline-flex flex-column align-items-end gap-1">
                                            <div class="d-flex gap-1 justify-content-end">
                                                @if($openCount > 0)
                                                    <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fw-bold" title="{{ $openCount }} Active Open Deals">
                                                        🟢 {{ $openCount }} Open
                                                    </span>
                                                @endif
                                                @if($wonCount > 0)
                                                    <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-0.5 fw-bold" title="{{ $wonCount }} Closed Won Deals">
                                                        🔵 {{ $wonCount }} Won
                                                    </span>
                                                @endif
                                                @if($openCount == 0 && $wonCount == 0)
                                                    <span class="badge bg-soft-secondary text-secondary border px-2 py-0.5 fw-bold">
                                                        {{ $totalDeals }} Deals
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="text-muted fs-11 font-monospace">Total: {{ $totalDeals }}</span>
                                        </div>
                                    @else
                                        <span class="badge bg-light text-muted border px-2 py-0.5 font-monospace">0 Deals</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-success fs-14">
                                    ₹{{ number_format($acc->lifetime_revenue, 2) }}
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                        <x-ui.icon-btn href="{{ route('crm.accounts.show', $acc) }}" variant="soft-primary" icon="feather-eye" title="View 360° Dashboard" />
                                        <x-ui.icon-btn href="{{ route('crm.accounts.edit', $acc) }}" variant="soft-info" icon="feather-edit" title="Edit Account" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="feather-briefcase fs-1 text-muted d-block mb-2"></i>
                                    No accounts found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer (Exact Lead Listing Pagination) -->
            @if($accounts->hasPages())
                <div class="p-3 border-top bg-light-50 d-flex justify-content-between align-items-center">
                    <span class="text-muted fs-12">
                        Showing {{ $accounts->firstItem() }} to {{ $accounts->lastItem() }} of {{ $accounts->total() }} accounts
                    </span>
                    <div>
                        {{ $accounts->appends(request()->query())->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
