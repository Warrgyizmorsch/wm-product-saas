@extends('layouts.duralux')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@section('title', 'Expense Limits — ' . $policy->name . ' | SaaS ERP')
@section('page-title', 'Expense Limits')
@section('breadcrumb', 'HRMS / Masters / Expense Policies / ' . $policy->name)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('hrms.expense-policy.index') }}" variant="light" class="border fw-bold text-uppercase" icon="feather-arrow-left">
            Back
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addRuleModal" class="fw-bold text-uppercase">
            Add Category Limit
        </x-ui.button>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    {{-- Policy header summary --}}
    <div class="border rounded-3 p-4 mb-4 bg-light">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-2 bg-soft-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                <i class="feather-file-text text-primary fs-20"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="fw-bold text-dark mb-1">{{ $policy->name }}</h5>
                <div class="d-flex align-items-center gap-3 fs-12 text-muted">
                    <span><i class="feather-users me-1"></i>
                        @if($policy->designation)
                            Designation: <strong class="text-dark">{{ $policy->designation->name }}</strong>
                        @elseif($policy->department)
                            Department: <strong class="text-dark">{{ $policy->department->name }}</strong>
                        @else
                            <strong class="text-dark">All Employees</strong>
                        @endif
                    </span>
                    <span>
                        <x-ui.badge variant="{{ $policy->status ? 'success' : 'secondary' }}" soft class="px-2 py-1 fs-11 rounded-pill">
                            {{ $policy->status ? 'Active' : 'Inactive' }}
                        </x-ui.badge>
                    </span>
                </div>
                @if($policy->description)
                    <p class="fs-12 text-muted mb-0 mt-1">{{ $policy->description }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Category limits table header --}}
    {{-- Toolbar: Category Spending Limits Header (Left) + Search/Sort/Filter (Right) --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        {{-- Left: Heading, Badge, Warning, and Filter Badges --}}
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2" style="font-size:14px;">
                <i class="feather-list text-primary"></i> Category Spending Limits
                <span class="badge bg-soft-primary text-primary fs-11 rounded-pill px-2">{{ $rules->count() }}</span>
            </h6>
            
            @if(!empty($filters['search']) || (!empty($filters['receipt']) && $filters['receipt'] !== ''))
                <div class="d-flex align-items-center gap-2">
                    @if(!empty($filters['search']))
                        <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill">
                            <i class="feather-search me-1"></i>{{ $filters['search'] }}
                        </span>
                    @endif
                    @if(!empty($filters['receipt']) && $filters['receipt'] !== '')
                        <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 rounded-pill">
                            Receipt: {{ ucfirst(str_replace('_', ' ', $filters['receipt'])) }}
                        </span>
                    @endif
                    <a href="{{ route('hrms.expense-policy.rules', $policy) }}" class="text-danger fs-12 fw-semibold">
                        <i class="feather-x"></i> Clear
                    </a>
                </div>
            @endif

            @php
                $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
                $totalCategories = \App\Domains\HRMS\Models\ExpenseCategory::where('tenant_id', $tenantId)->count();
            @endphp
            @if($totalCategories === 0)
                <span class="fs-12 text-danger">
                    <i class="feather-alert-circle me-1"></i>Please 
                    <a href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}" class="fw-bold text-decoration-underline text-danger">Create Expense Categories</a> 
                    first!
                </span>
            @endif
        </div>

        {{-- Right: Actions (Search Box, Sort Dropdown, Filter Dropdown) --}}
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Search Box --}}
            <form method="GET" action="{{ route('hrms.expense-policy.rules', $policy) }}" id="rulesSearchForm" 
                  class="d-flex align-items-center border rounded px-3 py-1 m-0" 
                  style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                <input type="hidden" name="sort"    value="{{ $filters['sort'] }}">
                <input type="hidden" name="receipt" value="{{ $filters['receipt'] }}">
                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-13 text-dark" placeholder="Search category..." value="{{ $filters['search'] }}" style="box-shadow:none; outline:none; height:32px;">
            </form>

            {{-- Sort Dropdown --}}
            <x-ui.sort-dropdown label="Sort">
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'category_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'category_asc']) }}">
                    <span>Category A → Z</span>
                    @if($filters['sort'] === 'category_asc') <i class="feather-check ms-3"></i> @endif
                </a>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'category_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'category_desc']) }}">
                    <span>Category Z → A</span>
                    @if($filters['sort'] === 'category_desc') <i class="feather-check ms-3"></i> @endif
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'limit_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'limit_desc']) }}">
                    <span>Limit: High → Low</span>
                    @if($filters['sort'] === 'limit_desc') <i class="feather-check ms-3"></i> @endif
                </a>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'limit_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'limit_asc']) }}">
                    <span>Limit: Low → High</span>
                    @if($filters['sort'] === 'limit_asc') <i class="feather-check ms-3"></i> @endif
                </a>
            </x-ui.sort-dropdown>

            {{-- Filter Dropdown --}}
            <x-ui.filter label="Filter">
                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                <form method="GET" action="{{ route('hrms.expense-policy.rules', $policy) }}" id="rulesFilterForm">
                    <input type="hidden" name="search" value="{{ $filters['search'] }}">
                    <input type="hidden" name="sort"   value="{{ $filters['sort'] }}">
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Receipt Required</label>
                        <x-ui.odoo-form-ui type="select" name="receipt" id="rule_filter_receipt">
                            <option value="">All Rules</option>
                            <option value="always" @selected($filters['receipt'] === 'always')>Always Required</option>
                            <option value="threshold" @selected($filters['receipt'] === 'threshold')>Above Threshold Only</option>
                            <option value="not_required" @selected($filters['receipt'] === 'not_required')>Not Required</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="dropdown-divider my-3"></div>
                    <div class="d-flex gap-2">
                        <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">Apply Filters</x-ui.button>
                        <a href="{{ route('hrms.expense-policy.rules', $policy) }}" class="btn btn-sm btn-light border flex-grow-1 d-flex align-items-center justify-content-center" style="font-size: 12px; font-weight: 500;">Reset</a>
                    </div>
                </form>
            </x-ui.filter>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:13px;">
            <thead class="table-light">
                <tr>
                    <th style="width: 22%;">Category</th>
                    <th style="width: 14%;">Per Claim</th>
                    <th style="width: 14%;">Per Day</th>
                    <th style="width: 14%;">Per Month</th>
                    <th style="width: 16%;">Receipt Required</th>
                    <th style="width: 12%;">Notes</th>
                    <th style="width: 8%;" class="text-end">Action</th>
                </tr>
            </thead>
            <tbody id="rulesTableBody">
                @forelse($rules as $rule)
                    <tr>
                        <td>
                            <span class="fw-bold text-primary">{{ $rule->category->name }}</span>
                            <span class="text-muted fs-11 d-block">{{ $rule->category->code }}</span>
                        </td>
                        <td>{!! $rule->max_limit_per_claim ? '₹' . number_format($rule->max_limit_per_claim, 2) : '<span class="text-muted">No limit</span>' !!}</td>
                        <td>{!! $rule->max_daily_limit ? '₹' . number_format($rule->max_daily_limit, 2) : '<span class="text-muted">—</span>' !!}</td>
                        <td>{!! $rule->max_monthly_limit ? '₹' . number_format($rule->max_monthly_limit, 2) : '<span class="text-muted">—</span>' !!}</td>
                        <td>
                            @if($rule->receipt_required)
                                <x-ui.badge variant="warning" soft class="px-2 py-1 fs-11">Always</x-ui.badge>
                            @elseif($rule->receipt_required_threshold)
                                <span class="fs-12 text-muted">Above ₹{{ number_format($rule->receipt_required_threshold, 2) }}</span>
                            @else
                                <span class="text-muted fs-12">Not required</span>
                            @endif
                        </td>
                        <td class="text-muted fs-12">{{ $rule->notes ?: '—' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('hrms.expense-policy.rules.destroy', [$policy, $rule]) }}"
                                  onsubmit="return confirm('Remove this category limit?');" class="m-0 d-inline-flex">
                                @csrf @method('DELETE')
                                <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="Remove Limit" />
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="feather-list fs-24 d-block mb-2 text-secondary"></i>
                            <p class="mb-0">No category limits defined yet.</p>
                            @if($availableCategories->isNotEmpty())
                                <p class="fs-12 text-muted mt-1">Click the <strong>Add Category Limit</strong> button in the top-right corner to get started.</p>
                            @else
                                @if($totalCategories === 0)
                                    <span class="fs-12 text-danger">
                                        Please <a href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}" class="fw-bold text-decoration-underline text-danger">Create Expense Categories</a> first!
                                    </span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

{{-- Add Rule Modal --}}
<x-ui.modal id="addRuleModal"
    title='<i class="feather-sliders me-2 text-primary"></i>Add Category Limit'
    centered
    formAction="{{ route('hrms.expense-policy.rules.store', $policy) }}"
    formMethod="POST"
    submitText="Save Limit"
    closeText="Cancel">

    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="select" label="Expense Category" name="expense_category_id" id="rule_category" select2-selector="default" :required="true">
            <option value="" disabled selected>-- Select Category --</option>
            <option value="add_new_category" class="text-primary fw-bold">+ Add New Category</option>
            @foreach($availableCategories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->code }})</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="input" inputType="number" label="Max per Claim (₹)" name="max_limit_per_claim" id="rule_claim" placeholder="e.g. 500" step="0.01" min="0" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="Max per Day (₹)" name="max_daily_limit" id="rule_daily" placeholder="e.g. 1000" step="0.01" min="0" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="Max per Month (₹)" name="max_monthly_limit" id="rule_monthly" placeholder="e.g. 5000" step="0.01" min="0" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="Receipt Threshold (₹)" name="receipt_required_threshold" id="rule_threshold" placeholder="e.g. 250" step="0.01" min="0" />
        
        <x-ui.odoo-form-ui type="select" label="Always Require Receipt?" name="receipt_required" id="rule_receipt" select2-selector="default">
            <option value="0" selected>No — only above threshold</option>
            <option value="1">Yes — always required</option>
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="input" label="Notes (optional)" name="notes" id="rule_notes" placeholder="e.g. Includes airfare only for Grade A+" />
    </div>
</x-ui.modal>

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Re-initialize select2 for dynamically reloaded filter components
            if (window.$ && $.fn.select2) {
                $('.odoo-select2').select2({ theme: 'bootstrap-5', width: '100%' });
            }
            // Redirect to category master if "+ Add New Category" option is selected
            $('#rule_category').on('change', function() {
                if ($(this).val() === 'add_new_category') {
                    window.location.href = "{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}";
                }
            });

            var rulesSearchTimeout;
            var activeRequest = null;

            function refreshRulesList(url) {
                if (activeRequest) {
                    activeRequest.abort();
                }
                var controller = new AbortController();
                activeRequest = controller;

                var tbody = document.getElementById('rulesTableBody');
                if (tbody) tbody.style.opacity = '0.5';

                fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Error reloading rules.');
                    return response.text();
                })
                .then(function(html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var newTbody = doc.getElementById('rulesTableBody');
                    var oldTbody = document.getElementById('rulesTableBody');
                    if (newTbody && oldTbody) {
                        oldTbody.innerHTML = newTbody.innerHTML;
                    }
                    
                    history.pushState(null, '', url.toString());
                })
                .catch(function(err) {
                    if (err.name !== 'AbortError') {
                        window.location.href = url.toString();
                    }
                })
                .finally(function() {
                    if (tbody) tbody.style.opacity = '1';
                });
            }

            // Real-time search (no Enter key required, no reload)
            $('#rulesSearchForm input[name="search"]').on('input keyup search', function() {
                var form = this.closest('form');
                var url = new URL(form.action || window.location.href);
                var formData = new FormData(form);
                for (var [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }

                clearTimeout(rulesSearchTimeout);
                rulesSearchTimeout = setTimeout(function() {
                    refreshRulesList(url);
                }, 300);
            });

            // Intercept sorting links (no reload)
            $(document).on('click', '.dropdown-item[href*="sort="]', function(e) {
                var href = $(this).attr('href');
                if (href && href.indexOf('rules') !== -1) {
                    e.preventDefault();
                    var url = new URL(href, window.location.origin);
                    refreshRulesList(url);
                    
                    $('.dropdown-item[href*="sort="]').removeClass('active');
                    $(this).addClass('active');
                }
            });

            // Intercept filters submission (no reload)
            $('#rulesFilterForm').on('submit', function(e) {
                e.preventDefault();
                var form = this;
                var url = new URL(form.action || window.location.href);
                var formData = new FormData(form);
                for (var [key, val] of formData.entries()) {
                    url.searchParams.set(key, val);
                }
                refreshRulesList(url);
                $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
            });
        });
    </script>
@endpush
