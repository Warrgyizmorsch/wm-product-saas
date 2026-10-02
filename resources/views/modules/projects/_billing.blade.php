<div class="project-billing-container">
    {{-- Billing Financial KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card erp-kpi-card shadow-sm border-0 h-100 p-3 bg-light">
                <div class="d-flex align-items-center">
                    <div class="avatar-sm rounded bg-primary-subtle text-primary d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="feather-file-text fs-20"></i>
                    </div>
                    <div>
                        <span class="text-muted fs-12 fw-medium text-uppercase d-block">{{ __('projects.total_invoiced') }}</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1">{{ format_currency($billingSummary['total_invoiced'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card erp-kpi-card shadow-sm border-0 h-100 p-3 bg-light">
                <div class="d-flex align-items-center">
                    <div class="avatar-sm rounded bg-success-subtle text-success d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="feather-check-circle fs-20"></i>
                    </div>
                    <div>
                        <span class="text-muted fs-12 fw-medium text-uppercase d-block">{{ __('projects.total_paid') }}</span>
                        <h4 class="fw-bold text-success mb-0 mt-1">{{ format_currency($billingSummary['total_paid'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card erp-kpi-card shadow-sm border-0 h-100 p-3 bg-light">
                <div class="d-flex align-items-center">
                    <div class="avatar-sm rounded bg-danger-subtle text-danger d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="feather-alert-circle fs-20"></i>
                    </div>
                    <div>
                        <span class="text-muted fs-12 fw-medium text-uppercase d-block">{{ __('projects.balance_due') }}</span>
                        <h4 class="fw-bold text-danger mb-0 mt-1">{{ format_currency($billingSummary['balance_due'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card erp-kpi-card shadow-sm border-0 h-100 p-3 bg-light">
                <div class="d-flex align-items-center">
                    <div class="avatar-sm rounded bg-info-subtle text-info d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="feather-clock fs-20"></i>
                    </div>
                    <div>
                        <span class="text-muted fs-12 fw-medium text-uppercase d-block">{{ __('projects.unbilled_work') }}</span>
                        <h4 class="fw-bold text-info mb-0 mt-1">{{ format_currency($billingSummary['unbilled_total'] ?? 0) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Action Toolbar --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
            <i class="feather-file-text me-2 text-primary"></i>
            {{ __('projects.project_invoices') }}
            @if (!empty($billingSummary['invoices_count']))
                <span class="badge bg-secondary-subtle text-secondary ms-2 fs-12">{{ $billingSummary['invoices_count'] }}</span>
            @endif
        </h5>

        <div class="d-flex gap-2">
            @if ($canGenerateInvoice)
                @if (empty($project->customer_id))
                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="{{ __('projects.assign_customer_to_invoice') }}">
                        <button type="button" class="btn btn-primary btn-sm disabled" disabled>
                            <i class="feather-plus me-1"></i>{{ __('projects.generate_invoice') }}
                        </button>
                    </span>
                @else
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalGenerateInvoice">
                        <i class="feather-plus me-1"></i>{{ __('projects.generate_invoice') }}
                    </button>
                @endif
            @endif
        </div>
    </div>

    @if (empty($project->customer_id))
        <div class="alert alert-warning d-flex align-items-center mb-3 fs-13 py-2">
            <i class="feather-alert-triangle me-2 fs-16"></i>
            <div>{{ __('projects.no_customer_warning') }}</div>
        </div>
    @endif

    {{-- Invoices Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            @php $invoices = $billingSummary['invoices'] ?? collect(); @endphp
            @if ($invoices->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="feather-file-text fs-40 text-muted opacity-50 d-block mb-2"></i>
                    <h6 class="fw-semibold mb-1">{{ __('projects.no_invoices_yet') }}</h6>
                    <p class="fs-12 mb-0">{{ __('projects.no_invoices_description') }}</p>
                </div>
            @else
                <x-ui.odoo-form-ui type="table" class="mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('projects.invoice_number') }}</th>
                            <th>{{ __('projects.invoice_date') }}</th>
                            <th>{{ __('projects.due_date') }}</th>
                            <th>{{ __('projects.customer') }}</th>
                            <th>{{ __('projects.status') }}</th>
                            <th class="text-end">{{ __('projects.total_amount') }}</th>
                            <th class="text-end">{{ __('projects.amount_paid') }}</th>
                            <th class="text-end">{{ __('projects.balance_due') }}</th>
                            <th class="text-end pe-3">{{ __('projects.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <a href="{{ route('sales.invoices.show', $invoice->id) }}" class="text-primary hover-underline">
                                        {{ $invoice->invoice_number }}
                                    </a>
                                </td>
                                <td>{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '—' }}</td>
                                <td>{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '—' }}</td>
                                <td>{{ $invoice->customer?->name ?? '—' }}</td>
                                <td>
                                    @php
                                        $badgeVariant = match($invoice->status) {
                                            'Draft' => 'secondary',
                                            'Posted', 'Sent' => 'primary',
                                            'Paid' => 'success',
                                            'Partially Paid' => 'warning',
                                            'Cancelled' => 'danger',
                                            default => 'info',
                                        };
                                    @endphp
                                    <x-ui.badge variant="{{ $badgeVariant }}" soft>
                                        {{ $invoice->status }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-end fw-semibold">{{ format_currency($invoice->total_amount) }}</td>
                                <td class="text-end text-success">{{ format_currency($invoice->amount_paid) }}</td>
                                <td class="text-end fw-bold {{ $invoice->balance_due > 0 ? 'text-danger' : 'text-muted' }}">
                                    {{ format_currency($invoice->balance_due) }}
                                </td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown id="invoiceActions-{{ $invoice->id }}">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('sales.invoices.show', $invoice->id) }}">
                                                <i class="feather-eye me-2"></i>{{ __('projects.view_invoice') }}
                                            </a>
                                        </li>
                                        @if ($invoice->status === 'Draft' && auth()->user()->can('update', $invoice))
                                            <li>
                                                <form action="{{ route('sales.invoices.post', $invoice->id) }}" method="POST"
                                                      onsubmit="return confirmFormSubmit(event, @js(__('projects.confirm_post_invoice')));">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="feather-check-circle me-2"></i>{{ __('projects.post_invoice') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            @endif
        </div>
    </div>
</div>
