@extends('layouts.duralux')

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
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2" style="font-size:14px;">
            <i class="feather-list text-primary"></i> Category Spending Limits
            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill px-2">{{ $policy->rules->count() }}</span>
        </h6>
        @if($availableCategories->isEmpty())
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
            @else
                <span class="fs-12 text-muted">
                    <i class="feather-check-circle me-1 text-success"></i>All categories configured for this policy
                </span>
            @endif
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:13px;">
            <thead class="table-light">
                <tr>
                    <th>Category</th>
                    <th>Per Claim</th>
                    <th>Per Day</th>
                    <th>Per Month</th>
                    <th>Receipt Required</th>
                    <th>Notes</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($policy->rules as $rule)
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
                                  onsubmit="return confirm('Remove this category limit?');" class="d-inline">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="light" size="sm" class="border text-danger" icon="feather-trash-2" />
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Redirect to category master if "+ Add New Category" option is selected
            $('#rule_category').on('change', function() {
                if ($(this).val() === 'add_new_category') {
                    window.location.href = "{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}";
                }
            });
        });
    </script>
@endpush
