@extends('layouts.duralux')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@section('title', __('hrms.expense_master.page_title_limits') . ' — ' . $policy->name . ' | SaaS ERP')
@section('page-title', __('hrms.expense_master.page_title_limits'))
@section('breadcrumb', __('hrms.expense_master.breadcrumb_policies') . ' / ' . $policy->name)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('hrms.expense-policy.index') }}" variant="light" class="border fw-bold text-uppercase" icon="feather-arrow-left">
            {{ __('hrms.expense_master.btn_back') }}
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addRuleModal" class="fw-bold text-uppercase">
            {{ __('hrms.expense_master.btn_add_category_limit') }}
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
                            {{ __('hrms.expense_master.designation_label') }} <strong class="text-dark">{{ $policy->designation->name }}</strong>
                        @elseif($policy->department)
                            {{ __('hrms.expense_master.department_label') }} <strong class="text-dark">{{ $policy->department->name }}</strong>
                        @else
                            <strong class="text-dark">{{ __('hrms.expense_master.all_employees') }}</strong>
                        @endif
                    </span>
                    <span>
                        <x-ui.badge variant="{{ $policy->status ? 'success' : 'secondary' }}" soft class="px-2 py-1 fs-11 rounded-pill">
                            {{ $policy->status ? __('hrms.expense_master.status_active') : __('hrms.expense_master.status_inactive') }}
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
                <i class="feather-list text-primary"></i> {{ __('hrms.expense_master.category_spending_limits') }}
                <x-ui.badge variant="primary" soft class="fs-11 rounded-pill px-2">{{ $rules->count() }}</x-ui.badge>
            </h6>
            
            @if(!empty($filters['search']) || (!empty($filters['receipt']) && $filters['receipt'] !== ''))
                <div class="d-flex align-items-center gap-2">
                    @if(!empty($filters['search']))
                        <x-ui.badge variant="primary" soft class="px-2 py-1 fs-11 rounded-pill">
                            <i class="feather-search me-1"></i>{{ $filters['search'] }}
                        </x-ui.badge>
                    @endif
                    @if(!empty($filters['receipt']) && $filters['receipt'] !== '')
                        <x-ui.badge variant="secondary" soft class="px-2 py-1 fs-11 rounded-pill">
                            {{ __('hrms.expense_master.tbl_receipt_required') }}: 
                            @if($filters['receipt'] === 'always')
                                {{ __('hrms.expense_master.always_required') }}
                            @elseif($filters['receipt'] === 'threshold')
                                {{ __('hrms.expense_master.above_threshold_only') }}
                            @elseif($filters['receipt'] === 'not_required')
                                {{ __('hrms.expense_master.not_required') }}
                            @else
                                {{ ucfirst(str_replace('_', ' ', $filters['receipt'])) }}
                            @endif
                        </x-ui.badge>
                    @endif
                    <a href="{{ route('hrms.expense-policy.rules', $policy) }}" class="text-danger fs-12 fw-semibold">
                        <i class="feather-x"></i> {{ __('hrms.expense_master.btn_clear') }}
                    </a>
                </div>
            @endif

            @php
                $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
                $totalCategories = \App\Domains\HRMS\Models\ExpenseCategory::where('tenant_id', $tenantId)->count();
            @endphp
            @if($totalCategories === 0)
                <span class="fs-12 text-danger">
                    <i class="feather-alert-circle me-1"></i>{{ __('hrms.expense_master.total_categories_warning_prefix') }} 
                    <a href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}" class="fw-bold text-decoration-underline text-danger">{{ __('hrms.expense_master.create_categories_link') }}</a> 
                    {{ __('hrms.expense_master.total_categories_warning_suffix') }}
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
                <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-13 text-dark" placeholder="{{ __('hrms.expense_master.search_limit_placeholder') }}" value="{{ $filters['search'] }}" style="box-shadow:none; outline:none; height:32px;">
            </form>

            {{-- Sort Dropdown --}}
            <x-ui.sort-dropdown label="{{ __('hrms.expense_master.sort_label') }}">
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'category_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'category_asc']) }}">
                    <span>{{ __('hrms.expense_master.sort_category_asc') }}</span>
                    @if($filters['sort'] === 'category_asc') <i class="feather-check ms-3"></i> @endif
                </a>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'category_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'category_desc']) }}">
                    <span>{{ __('hrms.expense_master.sort_category_desc') }}</span>
                    @if($filters['sort'] === 'category_desc') <i class="feather-check ms-3"></i> @endif
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'limit_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'limit_desc']) }}">
                    <span>{{ __('hrms.expense_master.sort_limit_desc') }}</span>
                    @if($filters['sort'] === 'limit_desc') <i class="feather-check ms-3"></i> @endif
                </a>
                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'limit_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'limit_asc']) }}">
                    <span>{{ __('hrms.expense_master.sort_limit_asc') }}</span>
                    @if($filters['sort'] === 'limit_asc') <i class="feather-check ms-3"></i> @endif
                </a>
            </x-ui.sort-dropdown>

            {{-- Filter Dropdown --}}
            <x-ui.filter label="{{ __('hrms.expense_master.filter_label') }}">
                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.expense_master.filter_options') }}</h6>
                <form method="GET" action="{{ route('hrms.expense-policy.rules', $policy) }}" id="rulesFilterForm">
                    <input type="hidden" name="search" value="{{ $filters['search'] }}">
                    <input type="hidden" name="sort"   value="{{ $filters['sort'] }}">
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.expense_master.filter_receipt_required') }}</label>
                        <x-ui.odoo-form-ui type="select" name="receipt" id="rule_filter_receipt">
                            <option value="">{{ __('hrms.expense_master.all_rules') }}</option>
                            <option value="always" @selected($filters['receipt'] === 'always')>{{ __('hrms.expense_master.always_required') }}</option>
                            <option value="threshold" @selected($filters['receipt'] === 'threshold')>{{ __('hrms.expense_master.above_threshold_only') }}</option>
                            <option value="not_required" @selected($filters['receipt'] === 'not_required')>{{ __('hrms.expense_master.not_required') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="dropdown-divider my-3"></div>
                    <div class="d-flex gap-2">
                        <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.expense_master.btn_apply_filters') }}</x-ui.button>
                        <a href="{{ route('hrms.expense-policy.rules', $policy) }}" class="btn btn-sm btn-light border flex-grow-1 d-flex align-items-center justify-content-center" style="font-size: 12px; font-weight: 500;">{{ __('hrms.expense_master.btn_reset') }}</a>
                    </div>
                </form>
            </x-ui.filter>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:13px;">
            <thead class="table-light">
                <tr>
                    <th style="width: 22%;">{{ __('hrms.expense_master.tbl_category') }}</th>
                    <th style="width: 14%;">{{ __('hrms.expense_master.tbl_per_claim') }}</th>
                    <th style="width: 14%;">{{ __('hrms.expense_master.tbl_per_day') }}</th>
                    <th style="width: 14%;">{{ __('hrms.expense_master.tbl_per_month') }}</th>
                    <th style="width: 16%;">{{ __('hrms.expense_master.tbl_receipt_required') }}</th>
                    <th style="width: 12%;">{{ __('hrms.expense_master.tbl_notes') }}</th>
                    <th style="width: 8%;" class="text-end">{{ __('hrms.expense_master.tbl_actions') }}</th>
                </tr>
            </thead>
            <tbody id="rulesTableBody">
                @forelse($rules as $rule)
                    <tr>
                        <td>
                            <span class="fw-bold text-primary">{{ $rule->category->name }}</span>
                            <span class="text-muted fs-11 d-block">{{ $rule->category->code }}</span>
                        </td>
                        <td>{!! $rule->max_limit_per_claim ? '₹' . number_format($rule->max_limit_per_claim, 2) : '<span class="text-muted">' . __('hrms.expense_master.no_limit') . '</span>' !!}</td>
                        <td>{!! $rule->max_daily_limit ? '₹' . number_format($rule->max_daily_limit, 2) : '<span class="text-muted">—</span>' !!}</td>
                        <td>{!! $rule->max_monthly_limit ? '₹' . number_format($rule->max_monthly_limit, 2) : '<span class="text-muted">—</span>' !!}</td>
                        <td>
                            @if($rule->receipt_required)
                                <x-ui.badge variant="warning" soft class="px-2 py-1 fs-11">{{ __('hrms.expense_master.receipt_always') }}</x-ui.badge>
                            @elseif($rule->receipt_required_threshold)
                                <span class="fs-12 text-muted">{{ __('hrms.expense_master.receipt_above_amount', ['amount' => number_format($rule->receipt_required_threshold, 2)]) }}</span>
                            @else
                                <span class="text-muted fs-12">{{ __('hrms.expense_master.receipt_not_required') }}</span>
                            @endif
                        </td>
                        <td class="text-muted fs-12">{{ $rule->notes ?: '—' }}</td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <x-ui.icon-btn type="button" variant="soft-primary" size="sm" class="btn-edit-rule"
                                    icon="feather-edit-3"
                                    title="{{ __('hrms.expense_master.btn_edit_limit') }}"
                                    data-id="{{ $rule->id }}"
                                    data-category-id="{{ $rule->expense_category_id }}"
                                    data-category-name="{{ $rule->category->name }} ({{ $rule->category->code }})"
                                    data-claim="{{ $rule->max_limit_per_claim }}"
                                    data-daily="{{ $rule->max_daily_limit }}"
                                    data-monthly="{{ $rule->max_monthly_limit }}"
                                    data-threshold="{{ $rule->receipt_required_threshold }}"
                                    data-receipt="{{ $rule->receipt_required ? 1 : 0 }}"
                                    data-notes="{{ $rule->notes }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editRuleModal"
                                />
                                <form method="POST" action="{{ route('hrms.expense-policy.rules.destroy', [$policy, $rule]) }}"
                                      onsubmit="return confirmFormSubmit(event, '{{ __('hrms.expense_master.confirm_remove_limit') }}', { title: '{{ __('hrms.expense_master.title_remove_limit') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.expense_master.btn_remove') }}' });" class="m-0 d-flex">
                                    @csrf @method('DELETE')
                                    <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="{{ __('hrms.expense_master.btn_remove_limit') }}" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="feather-list fs-24 d-block mb-2 text-secondary"></i>
                            <p class="mb-0">{{ __('hrms.expense_master.empty_limits_title') }}</p>
                            @if($availableCategories->isNotEmpty())
                                <p class="fs-12 text-muted mt-1">{{ __('hrms.expense_master.empty_limits_desc') }}</p>
                            @else
                                @if($totalCategories === 0)
                                    <span class="fs-12 text-danger">
                                        {{ __('hrms.expense_master.total_categories_warning_prefix') }} <a href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}" class="fw-bold text-decoration-underline text-danger">{{ __('hrms.expense_master.create_categories_link') }}</a> {{ __('hrms.expense_master.total_categories_warning_suffix') }}
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

{{-- Modal 1: Add Rule Modal --}}
<x-ui.modal id="addRuleModal"
    :title="'<i class=\'feather-sliders me-2 text-primary\'></i>' . __('hrms.expense_master.modal_add_limit_title')"
    centered
    formAction="{{ route('hrms.expense-policy.rules.store', $policy) }}"
    formMethod="POST"
    submitText="{{ __('hrms.expense_master.btn_save_limit') }}"
    closeText="{{ __('hrms.expense_master.btn_cancel') }}">

    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.expense_master.field_expense_category') }}" name="expense_category_id" id="rule_category" select2-selector="default" :required="true">
            <option value="" disabled selected>{{ __('hrms.expense_master.field_select_category') }}</option>
            <option value="add_new_category" class="text-primary fw-bold">{{ __('hrms.expense_master.field_add_new_category_opt') }}</option>
            @foreach($availableCategories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->code }})</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.expense_master.field_max_per_claim') }}" name="max_limit_per_claim" id="rule_claim" placeholder="{{ __('hrms.expense_master.placeholder_limit_claim') }}" step="0.01" min="0" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.expense_master.field_max_per_day') }}" name="max_daily_limit" id="rule_daily" placeholder="{{ __('hrms.expense_master.placeholder_limit_daily') }}" step="0.01" min="0" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.expense_master.field_max_per_month') }}" name="max_monthly_limit" id="rule_monthly" placeholder="{{ __('hrms.expense_master.placeholder_limit_monthly') }}" step="0.01" min="0" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.expense_master.field_receipt_threshold') }}" name="receipt_required_threshold" id="rule_threshold" placeholder="{{ __('hrms.expense_master.placeholder_limit_threshold') }}" step="0.01" min="0" />
        
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.expense_master.field_always_receipt') }}" name="receipt_required" id="rule_receipt" select2-selector="default">
            <option value="0" selected>{{ __('hrms.expense_master.field_receipt_threshold_opt') }}</option>
            <option value="1">{{ __('hrms.expense_master.field_receipt_always_opt') }}</option>
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.expense_master.field_notes') }}" name="notes" id="rule_notes" placeholder="{{ __('hrms.expense_master.placeholder_limit_notes') }}" />
    </div>
</x-ui.modal>

{{-- Modal 2: Edit Rule Modal --}}
<x-ui.modal id="editRuleModal"
    :title="'<i class=\'feather-edit-3 me-2 text-primary\'></i>' . __('hrms.expense_master.modal_edit_limit_title')"
    centered
    formAction="{{ route('hrms.expense-policy.rules.store', $policy) }}"
    formMethod="POST"
    submitText="{{ __('hrms.expense_master.btn_update_limit') }}"
    closeText="{{ __('hrms.expense_master.btn_cancel') }}">

    <input type="hidden" name="expense_category_id" id="edit_rule_category_id">

    <div class="d-flex flex-column gap-3">
        <div>
            <label class="form-label fw-bold fs-12 text-dark mb-1">{{ __('hrms.expense_master.field_expense_category') }}</label>
            <input type="text" class="form-control bg-light" id="edit_rule_category_name" readonly>
        </div>

        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.expense_master.field_max_per_claim') }}" name="max_limit_per_claim" id="edit_rule_claim" placeholder="{{ __('hrms.expense_master.placeholder_limit_claim') }}" step="0.01" min="0" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.expense_master.field_max_per_day') }}" name="max_daily_limit" id="edit_rule_daily" placeholder="{{ __('hrms.expense_master.placeholder_limit_daily') }}" step="0.01" min="0" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.expense_master.field_max_per_month') }}" name="max_monthly_limit" id="edit_rule_monthly" placeholder="{{ __('hrms.expense_master.placeholder_limit_monthly') }}" step="0.01" min="0" />
        <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.expense_master.field_receipt_threshold') }}" name="receipt_required_threshold" id="edit_rule_threshold" placeholder="{{ __('hrms.expense_master.placeholder_limit_threshold') }}" step="0.01" min="0" />
        
        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.expense_master.field_always_receipt') }}" name="receipt_required" id="edit_rule_receipt" select2-selector="default">
            <option value="0">{{ __('hrms.expense_master.field_receipt_threshold_opt') }}</option>
            <option value="1">{{ __('hrms.expense_master.field_receipt_always_opt') }}</option>
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="input" label="{{ __('hrms.expense_master.field_notes') }}" name="notes" id="edit_rule_notes" placeholder="{{ __('hrms.expense_master.placeholder_limit_notes') }}" />
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

            // Populate Edit Rule Modal (delegation)
            $(document).on('click', '.btn-edit-rule', function() {
                var catId     = this.getAttribute('data-category-id');
                var catName   = this.getAttribute('data-category-name');
                var claim     = this.getAttribute('data-claim');
                var daily     = this.getAttribute('data-daily');
                var monthly   = this.getAttribute('data-monthly');
                var threshold = this.getAttribute('data-threshold');
                var receipt   = this.getAttribute('data-receipt');
                var notes     = this.getAttribute('data-notes');

                document.getElementById('edit_rule_category_id').value = catId || '';
                document.getElementById('edit_rule_category_name').value = catName || '';
                document.getElementById('edit_rule_claim').value = claim || '';
                document.getElementById('edit_rule_daily').value = daily || '';
                document.getElementById('edit_rule_monthly').value = monthly || '';
                document.getElementById('edit_rule_threshold').value = threshold || '';
                document.getElementById('edit_rule_notes').value = notes || '';

                var receiptSelect = document.getElementById('edit_rule_receipt');
                if (receiptSelect) {
                    receiptSelect.value = parseInt(receipt) === 1 ? '1' : '0';
                    if (window.$ && $(receiptSelect).hasClass('select2-hidden-accessible')) {
                        $(receiptSelect).trigger('change.select2');
                    }
                }
            });
        });
    </script>
@endpush

