@extends('layouts.duralux')

@section('title', __('crm.sales_order_label') . ' ' . $order->sales_order_number . ' | SaaS ERP')
@section('page-title', __('crm.sales_order_label') . ' ' . $order->sales_order_number)
@section('breadcrumb', __('crm.sales') . ' / ' . __('crm.sales_orders') . ' / ' . $order->sales_order_number)

@section('page-back-button')
    <x-ui.button href="{{ route('sales.orders.index') }}" variant="light" size="xs"
        class="border shadow-2xs d-inline-flex align-items-center justify-content-center p-0"
        style="width: 28px; height: 28px;" icon="feather-arrow-left"
        title="{{ __('crm.back_to_listing') ?? 'Back to Orders' }}" />
@endsection

@section('page-actions')
    @php
        $totalOrderedQty = $order->items->sum('quantity');
        $totalPhysicalQty = $order->items->filter(fn($item) => !$item->isService())->sum('quantity');
        $totalServiceQty = $order->items->filter(fn($item) => $item->isService())->sum('quantity');
        $totalInvoicedQty = \App\Domains\Sales\Models\InvoiceItem::whereHas('invoice', function ($q) use ($order) {
            $q->where('sales_order_id', $order->id)->where('status', '!=', 'Cancelled');
        })->sum('quantity');
        $totalReturnedBeforeInvoiceQty = \App\Domains\Sales\Models\SalesReturnItem::whereHas('salesReturn', function ($q) use ($order) {
            $q->where('sales_order_id', $order->id)->where('status', 'Completed')->whereNull('invoice_id');
        })->sum('quantity');
        $hasUnbilledQty = ($totalOrderedQty - $totalInvoicedQty - $totalReturnedBeforeInvoiceQty) > 0.0001;
        $invoicingPolicy = tenant()?->settings['invoicing_policy'] ?? 'both';
        $totalDispatchedQty = $order->dispatches->where('status', '!=', 'Cancelled')->flatMap->items->sum(function ($item) {
            return $item->quantity_dispatched ?? $item->quantity ?? 0;
        });
    @endphp

    <style>
        /* ── Chevron / Arrow Status Pipeline (Odoo / ERP Style) ── */
        .so-status-pipeline {
            display: inline-flex;
            align-items: center;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .so-status-pipeline .pipeline-step {
            position: relative;
            padding: 6px 16px 6px 24px;
            background-color: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            line-height: 1.2;
            white-space: nowrap;
        }

        .so-status-pipeline .pipeline-step:first-child {
            padding-left: 16px;
        }

        .so-status-pipeline .pipeline-step::after {
            content: "";
            position: absolute;
            top: 0;
            right: -10px;
            width: 0;
            height: 0;
            border-top: 14px solid transparent;
            border-bottom: 14px solid transparent;
            border-left: 10px solid #f8fafc;
            z-index: 10;
            transition: all 0.2s ease;
        }

        .so-status-pipeline .pipeline-step::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 0;
            border-top: 14px solid transparent;
            border-bottom: 14px solid transparent;
            border-left: 10px solid #ffffff;
            z-index: 5;
            transition: all 0.2s ease;
        }

        .so-status-pipeline .pipeline-step:first-child::before {
            display: none;
        }

        .so-status-pipeline .pipeline-step.active {
            background-color: var(--bs-primary, #1e40af);
            color: #ffffff;
            z-index: 11;
        }

        .so-status-pipeline .pipeline-step.active::after {
            border-left-color: var(--bs-primary, #1e40af);
        }

        .so-status-pipeline .pipeline-step.completed {
            background-color: #e2e8f0;
            color: #334155;
            z-index: 6;
        }

        .so-status-pipeline .pipeline-step.completed::after {
            border-left-color: #e2e8f0;
        }

        .so-status-pipeline .pipeline-step.active.step-warning {
            background-color: #d97706 !important;
            color: #ffffff !important;
        }

        .so-status-pipeline .pipeline-step.active.step-warning::after {
            border-left-color: #d97706 !important;
        }

        .so-status-pipeline .pipeline-step.active.step-success {
            background-color: #10b981 !important;
            color: #ffffff !important;
        }

        .so-status-pipeline .pipeline-step.active.step-success::after {
            border-left-color: #10b981 !important;
        }

        .so-status-pipeline .pipeline-step.active.step-danger {
            background-color: #dc2626 !important;
            color: #ffffff !important;
        }

        .so-status-pipeline .pipeline-step.active.step-danger::after {
            border-left-color: #dc2626 !important;
        }

        .so-status-pipeline .pipeline-step:hover:not(.active) {
            background-color: #e2e8f0;
            color: #0f172a;
            z-index: 8;
        }

        .so-status-pipeline .pipeline-step:hover:not(.active)::after {
            border-left-color: #e2e8f0;
        }

        /* Dark mode support */
        html.app-skin-dark .so-status-pipeline {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step {
            background-color: #0f172a;
            color: #94a3b8;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step::after {
            border-left-color: #0f172a;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step::before {
            border-left-color: #1e293b;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step.active {
            background-color: var(--bs-primary, #1e40af) !important;
            color: #ffffff !important;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step.active::after {
            border-left-color: var(--bs-primary, #1e40af) !important;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step.active.step-warning {
            background-color: #b45309 !important;
            color: #ffffff !important;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step.active.step-warning::after {
            border-left-color: #b45309 !important;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step.active.step-danger {
            background-color: #b91c1c !important;
            color: #ffffff !important;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step.active.step-danger::after {
            border-left-color: #b91c1c !important;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step.completed {
            background-color: #1e293b;
            color: #cbd5e1;
        }

        html.app-skin-dark .so-status-pipeline .pipeline-step.completed::after {
            border-left-color: #1e293b;
        }

        /* ── Modern Odoo / Lead Style Tabs ── */
        .odoo-notebook-tabs {
            border-bottom: 2px solid #e2e8f0;
            background: #ffffff;
            gap: 6px;
        }

        .odoo-notebook-tabs .nav-link {
            color: #64748b;
            border: none;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding: 12px 20px;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }

        .odoo-notebook-tabs .nav-link:hover {
            color: var(--bs-primary, #1e40af);
            border-bottom-color: rgba(30, 64, 175, 0.3);
        }

        .odoo-notebook-tabs .nav-link.active {
            color: var(--bs-primary, #1e40af) !important;
            font-weight: 700 !important;
            border-bottom: 2px solid var(--bs-primary, #1e40af) !important;
            background: transparent !important;
        }

        .odoo-notebook-tabs .nav-link .badge {
            margin-left: 10px !important;
            padding: 3px 9px !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            border-radius: 20px !important;
            line-height: 1.2 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        html.app-skin-dark .odoo-notebook-tabs {
            background: #0f172a !important;
            border-bottom-color: #1e293b !important;
        }

        html.app-skin-dark .odoo-notebook-tabs .nav-link {
            color: #94a3b8 !important;
        }

        html.app-skin-dark .odoo-notebook-tabs .nav-link.active {
            color: #60a5fa !important;
            border-bottom-color: #3b82f6 !important;
        }

        /* Key Metric Card Styles */
        .so-metric-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            transition: all 0.2s ease;
        }

        .so-metric-box:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        html.app-skin-dark .so-metric-box {
            background: #0f172a;
            border-color: #1e293b;
        }

        /* Info Row Labels */
        .so-info-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .so-info-label {
            width: 140px;
            flex-shrink: 0;
            color: #64748b;
            font-weight: 500;
        }

        .so-info-value {
            flex-grow: 1;
            color: #0f172a;
            font-weight: 600;
        }

        html.app-skin-dark .so-info-label {
            color: #94a3b8;
        }

        html.app-skin-dark .so-info-value {
            color: #f1f5f9;
        }
    </style>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- Chevron Arrow Status Pipeline -->
        <div class="so-status-pipeline d-inline-flex align-items-center d-print-none me-1">
            @php
                $isServiceOnly = $order->hasOnlyServices();
                if ($isServiceOnly) {
                    $statuses = ['Draft', 'Confirmed'];
                    if ($order->status === 'Completed' || $order->isFullyInvoiced()) {
                        $statuses[] = 'Completed';
                    } elseif ($order->invoices->isNotEmpty()) {
                        $statuses[] = 'Invoiced';
                    }
                } else {
                    $statuses = ['Draft', 'Confirmed'];
                    if ($order->status === 'In Fulfillment') {
                        $statuses[] = 'In Fulfillment';
                    } elseif ($order->status === 'Partially Shipped') {
                        $statuses[] = 'Partially Shipped';
                    }
                    $statuses[] = 'Shipped';
                    $statuses[] = 'Completed';
                }

                if ($order->status === 'Short-Closed') {
                    $statuses[] = 'Short-Closed';
                } elseif ($order->status === 'Cancelled') {
                    $statuses[] = 'Cancelled';
                }

                $displayStatus = $order->status;
                if ($isServiceOnly && in_array($order->status, ['In Fulfillment', 'Partially Shipped', 'Shipped'])) {
                    $displayStatus = 'Confirmed';
                }
                $currentIndex = array_search($displayStatus, $statuses);
            @endphp
            @foreach($statuses as $index => $state)
                @php
                    $stepClass = '';
                    if ($displayStatus === $state) {
                        $stepClass = 'active';
                        if ($state === 'Cancelled') {
                            $stepClass .= ' step-danger';
                        } elseif ($state === 'Short-Closed' || $state === 'Partially Shipped') {
                            $stepClass .= ' step-warning';
                        } elseif ($state === 'Completed') {
                            $stepClass .= ' step-success';
                        }
                    } elseif ($currentIndex !== false && $index < $currentIndex) {
                        $stepClass = 'completed';
                    }
                @endphp
                <span class="pipeline-step {{ $stepClass }}">
                    @if($state === 'Draft') {{ __('crm.status_draft') ?? 'Draft' }}
                    @elseif($state === 'Confirmed') {{ __('crm.status_confirmed') ?? 'Confirmed' }}
                    @elseif($state === 'In Fulfillment') In Fulfillment
                    @elseif($state === 'Partially Shipped') {{ __('crm.status_partially_shipped') ?? 'Partially Shipped' }}
                    @elseif($state === 'Shipped') {{ __('crm.status_shipped') ?? 'Shipped' }}
                    @elseif($state === 'Short-Closed') Short-Closed
                    @elseif($state === 'Invoiced') Invoiced
                    @elseif($state === 'Completed') Completed
                    @elseif($state === 'Cancelled') {{ __('crm.status_cancelled') ?? 'Cancelled' }}
                    @else {{ $state }}
                    @endif
                </span>
            @endforeach
        </div>

        <!-- Primary Action Buttons -->
        @if ($order->status === 'Draft')
            <form action="{{ route('sales.orders.confirm', $order->id) }}" method="POST" class="d-inline d-print-none"
                id="confirmSoForm">
                @csrf
                <button type="button" class="btn btn-sm btn-success fw-bold px-3 shadow-2xs"
                    onclick="confirmAction({ title: '{{ __('crm.confirm_order') }}', message: 'Confirm Sales Order {{ $order->sales_order_number }}?', variant: 'success', confirmText: '{{ __('crm.confirm') }}' }, function() { document.getElementById('confirmSoForm').submit(); })">
                    <i class="feather-check-circle me-1.5"></i>{{ __('crm.confirm_order') ?? 'Confirm Order' }}
                </button>
            </form>

            <a href="{{ route('sales.orders.edit', $order->id) }}"
                class="btn btn-sm btn-light border fw-semibold px-2.5 d-print-none">
                <i class="feather-edit me-1 text-muted"></i>{{ __('crm.edit') ?? 'Edit' }}
            </a>
        @elseif (in_array($order->status, ['Confirmed', 'Partially Shipped', 'Shipped', 'In Fulfillment']))
            @php
                $soMr = $order->materialRequirements->first();
            @endphp
            @if ($soMr)
                @if ($soMr->status === 'Released')
                    <a href="{{ route('inventory.material-requirements.show', $soMr->id) }}"
                        class="btn btn-sm btn-outline-success fw-semibold px-2.5 d-print-none"
                        title="Material Requirement Released for Dispatch">
                        <i class="feather-check-circle me-1 text-success"></i>MR Released
                    </a>
                @elseif (in_array($soMr->status, ['Pending', 'Draft', 'Processing', 'Partially Ready']))
                    <a href="{{ route('inventory.material-requirements.show', $soMr->id) }}"
                        class="btn btn-sm btn-outline-warning fw-semibold px-2.5 d-print-none"
                        title="Material Requirement Pending Store Release">
                        <i class="feather-clock me-1 text-warning"></i>MR Pending Release
                    </a>
                @endif
            @endif

            @if ($hasUnbilledQty)
                <a href="{{ route('sales.invoices.create', ['sales_order_id' => $order->id, 'mode' => 'sales_order']) }}"
                    class="btn btn-sm btn-primary fw-bold px-3 d-print-none shadow-2xs">
                    <i class="feather-file-text me-1.5"></i>{{ __('crm.create_invoice') ?? 'Create Invoice' }}
                </a>
            @endif
        @endif

        <!-- More Actions Dropdown -->
        <div class="dropdown d-inline-flex align-items-center d-print-none">
            <button class="btn btn-sm btn-light border fw-semibold px-2.5 dropdown-toggle d-inline-flex align-items-center"
                type="button"
                data-bs-toggle="dropdown"
                data-bs-auto-close="true"
                aria-expanded="false"
                title="More Order Actions">
                <i class="feather-more-horizontal me-1 text-muted"></i>More
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border p-1" style="min-width: 220px; z-index: 1060; right: 0 !important; left: auto !important;">
                <li>
                    <a class="dropdown-item py-1.5 fs-13" href="{{ route('sales.orders.download', $order->id) }}" target="_blank">
                        <i class="feather-download me-2 text-muted"></i>Download PDF
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-1.5 fs-13" href="javascript:void(0)" onclick="window.print()">
                        <i class="feather-printer me-2 text-muted"></i>Print Order
                    </a>
                </li>

                @if ($order->projects && $order->projects->isNotEmpty())
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item py-1.5 fs-13" href="{{ route('projects.show', $order->projects->first()->project_code) }}">
                            <i class="feather-folder me-2 text-info"></i>{{ __('projects.view_project') }} ({{ $order->projects->first()->project_code }})
                        </a>
                    </li>
                @elseif (!in_array($order->status, ['Draft', 'Cancelled', 'Short-Closed']) && auth()->user()->can('create', \App\Domains\Projects\Models\Project::class))
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <button type="button" class="dropdown-item py-1.5 fs-13" data-bs-toggle="modal" data-bs-target="#convertToProjectModal">
                            <i class="feather-folder-plus me-2 text-primary"></i>{{ __('projects.convert_to_project') }}
                        </button>
                    </li>
                @endif

                @if (in_array($order->status, ['Confirmed', 'Partially Shipped', 'In Fulfillment']))
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <button type="button" class="dropdown-item py-1.5 fs-13 text-warning" data-bs-toggle="modal" data-bs-target="#shortCloseOrderModal">
                            <i class="feather-minus-circle me-2 text-warning"></i>Short-Close Order
                        </button>
                    </li>
                @endif

                @if (!in_array($order->status, ['Shipped', 'Cancelled', 'Short-Closed']))
                    @if (!in_array($order->status, ['Confirmed', 'Partially Shipped', 'In Fulfillment']))
                        <li><hr class="dropdown-divider my-1"></li>
                    @endif
                    <li>
                        <button type="button" class="dropdown-item py-1.5 fs-13 text-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                            <i class="feather-x-circle me-2 text-danger"></i>{{ __('crm.cancel') ?? 'Cancel Order' }}
                        </button>
                    </li>
                @endif
            </ul>
        </div>
    </div>
@endsection

@section('content')

    @if (isset($errors) && $errors->any())
        <x-ui.toast :auto="true" type="error" title="{{ $errors->first() }}" />
    @endif

    @php
        // Financial Calculations
        $grossSubtotal = 0;
        $totalItemDiscount = 0;
        $itemsTaxAmount = 0;
        $taxMode = $order->effective_tax_mode ?? 'exclusive';

        foreach ($order->items as $it) {
            $lineGross = ($it->quantity * $it->unit_price);
            $grossSubtotal += $lineGross;
            $totalItemDiscount += $it->discount;
            if ($order->tax_type === 'item_wise_tax') {
                $lineTaxable = max(0, $lineGross - $it->discount);
                if ($taxMode === 'inclusive') {
                    $lineBase = ($it->tax_rate > 0) ? round($lineTaxable / (1 + ($it->tax_rate / 100)), 4) : $lineTaxable;
                    $itemsTaxAmount += ($lineTaxable - $lineBase);
                } else {
                    $itemsTaxAmount += ($lineTaxable * ($it->tax_rate / 100));
                }
            }
        }
        $effectiveDiscount = ($order->discount_type === 'order_wise') ? (float) $order->discount : $totalItemDiscount;

        if ($taxMode === 'inclusive') {
            if ($order->tax_type === 'order_wise_tax') {
                $netAmount = max(0, $grossSubtotal - $effectiveDiscount);
                $orderTaxRate = (float) ($order->order_tax_rate ?: 18);
                $itemsTaxAmount = $netAmount - ($netAmount / (1 + ($orderTaxRate / 100)));
            } elseif ($order->tax_type === 'without_tax') {
                $itemsTaxAmount = 0;
            }
            $itemsTaxAmount = round($itemsTaxAmount, 2);
            $taxableBase = max(0, ($grossSubtotal - $effectiveDiscount) - $itemsTaxAmount);
            $itemsTotalInclGst = max(0, $grossSubtotal - $effectiveDiscount);
        } else {
            $taxableBase = max(0, $grossSubtotal - $effectiveDiscount);
            if ($order->tax_type === 'order_wise_tax') {
                $itemsTaxAmount = $taxableBase * (($order->order_tax_rate ?: 18) / 100);
            } elseif ($order->tax_type === 'without_tax') {
                $itemsTaxAmount = 0;
            }
            $itemsTaxAmount = round($itemsTaxAmount, 2);
            $itemsTotalInclGst = $taxableBase + $itemsTaxAmount;
        }

        $freightAmount = ($order->freight_terms === 'To Be Billed') ? (float) ($order->freight_amount ?: $order->shipping_charges ?: 0) : 0;
        $adjustment = (float) $order->adjustment;
        $grandTotal = (float) ($order->total_amount ?? ($itemsTotalInclGst + $freightAmount + $adjustment));
        $gstType = $order->gst_type ?? 'cgst_sgst';

        $totalInvoicedAmount = $order->invoices->where('status', '!=', 'Cancelled')->sum(function ($inv) {
            return $inv->total_amount ?? $inv->grand_total ?? 0;
        });
        $totalPaidAmount = $order->allocations->sum('allocated_amount');
        $balanceDue = max(0, $grandTotal - $totalPaidAmount);

        $badgeClass = 'bg-soft-secondary text-secondary';
        if ($order->status === 'Confirmed')
            $badgeClass = 'bg-soft-primary text-primary';
        elseif ($order->status === 'In Fulfillment')
            $badgeClass = 'bg-soft-info text-info border border-info-subtle';
        elseif ($order->status === 'Partially Shipped')
            $badgeClass = 'bg-soft-warning text-warning border border-warning-subtle';
        elseif ($order->status === 'Shipped')
            $badgeClass = 'bg-soft-success text-success';
        elseif ($order->status === 'Completed')
            $badgeClass = 'bg-soft-success text-success border border-success-subtle';
        elseif ($order->status === 'Short-Closed')
            $badgeClass = 'bg-soft-warning text-warning border border-warning-subtle';
        elseif ($order->status === 'Cancelled')
            $badgeClass = 'bg-soft-danger text-danger border border-danger-subtle';
    @endphp

    <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white print-area">

        <!-- ==================== TOP SHEET HEADER ==================== -->
        <div class="p-4 bg-white border-bottom">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center mb-2 flex-wrap" style="gap: 14px !important;">
                        <h2 class="fw-bold text-dark mb-0 fs-22 tracking-tight me-1">
                            {{ $order->sales_order_number }}
                        </h2>
                        <span
                            class="badge {{ $badgeClass }} px-3 py-1.5 fs-11 fw-bold text-uppercase shadow-2xs d-inline-flex align-items-center">
                            {{ $order->status }}
                        </span>
                        @if($order->branch)
                            <span
                                class="badge bg-soft-secondary text-secondary px-3 py-1.5 fs-11 fw-semibold border border-secondary-subtle d-inline-flex align-items-center">
                                <i class="feather-map-pin me-1.5 text-muted"></i>{{ $order->branch->name }}
                            </span>
                        @endif
                    </div>

                    <div class="d-flex align-items-center gap-3 flex-wrap text-muted fs-12">
                        @if($order->customer)
                            <span>
                                <i class="feather-user me-1 text-primary"></i>
                                <a href="{{ route('crm.customers.show', $order->customer_id) }}"
                                    class="text-dark fw-bold hover-underline">
                                    {{ $order->customer->name }}
                                </a>
                            </span>
                            <span>&bull;</span>
                        @endif
                        <span>
                            <i class="feather-calendar me-1 text-muted"></i>
                            <strong>Order Date:</strong>
                            {{ $order->order_date ? $order->order_date->format('d/m/Y') : '—' }}
                        </span>
                        @if($order->quotation)
                            <span>&bull;</span>
                            <span>
                                <i class="feather-file-text me-1 text-info"></i>
                                <strong>Quotation Ref:</strong>
                                <a href="{{ route('crm.quotations.show', $order->quotation_id) }}"
                                    class="text-primary fw-bold hover-underline">
                                    {{ $order->quotation->quotation_number }}
                                </a>
                            </span>
                        @endif
                        @if($order->payment_terms)
                            <span>&bull;</span>
                            <span class="badge bg-soft-info text-info px-2 py-0.5 fs-11">
                                {{ \App\Domains\Platform\Models\PaymentTerm::getLabel($order->payment_terms) }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Right Quick Details -->
                <div class="text-sm-end text-start">
                    <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Total Order Value</span>
                    <h3 class="fw-extrabold text-primary mb-0 fs-20">{{ format_currency($grandTotal) }}</h3>
                    <span class="text-muted fs-11">
                        {{ $order->items->count() }} item(s) &bull; {{ $totalOrderedQty }} units
                    </span>
                </div>
            </div>

            @if(in_array($order->status, ['Short-Closed', 'Cancelled']) && !empty($order->closure_reason))
                <div class="mt-3 p-2.5 rounded-2 {{ $order->status === 'Cancelled' ? 'bg-soft-danger text-danger border border-danger-subtle' : 'bg-soft-warning text-warning-emphasis border border-warning-subtle' }} fs-12 d-flex align-items-center">
                    <i class="{{ $order->status === 'Cancelled' ? 'feather-alert-circle text-danger' : 'feather-info text-warning' }} me-2 fs-14 flex-shrink-0"></i>
                    <div>
                        <strong>{{ $order->status }} Reason:</strong> {{ $order->closure_reason }}
                    </div>
                </div>
            @endif
        </div>

        <!-- ==================== KEY KPI METRICS RIBBON (Lead Style) ==================== -->
        <div class="px-4 py-3 bg-light bg-opacity-50 border-bottom d-print-none">
            <div class="row g-3">
                <!-- KPI 1: Order Total -->
                <div class="col-sm-6 col-lg-3">
                    <div class="so-metric-box">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Order Grand Total</span>
                            <i class="feather-shopping-bag text-primary fs-14"></i>
                        </div>
                        <div class="fs-16 fw-bold text-dark mb-0.5">{{ format_currency($grandTotal) }}</div>
                        <div class="text-muted fs-11">Taxable: {{ format_currency($taxableBase) }}</div>
                    </div>
                </div>

                <!-- KPI 2: Delivery & Fulfillment -->
                <div class="col-sm-6 col-lg-3">
                    <div class="so-metric-box">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span
                                class="text-muted fs-11 text-uppercase fw-bold">{{ $order->hasOnlyServices() ? 'Service Fulfillment' : 'Fulfillment / Dispatch' }}</span>
                            <i
                                class="{{ $order->hasOnlyServices() ? 'feather-zap text-primary' : 'feather-truck text-info' }} fs-14"></i>
                        </div>
                        @if ($order->hasOnlyServices())
                            <div class="d-flex align-items-center gap-2 mb-0.5">
                                <span class="fs-16 fw-bold text-success"><i class="feather-check-circle me-1 fs-14"></i>Direct
                                    Service</span>
                            </div>
                            <div class="text-muted fs-11">Non-inventory &bull; Direct billing</div>
                        @else
                            <div class="d-flex align-items-center gap-2 mb-0.5">
                                <span class="fs-16 fw-bold text-dark">{{ (float) $totalDispatchedQty }} /
                                    {{ (float) $totalPhysicalQty }}</span>
                                @php
                                    $fulfillmentRate = ($totalPhysicalQty > 0) ? min(100, round(($totalDispatchedQty / $totalPhysicalQty) * 100)) : 0;
                                @endphp
                                <span
                                    class="badge {{ $fulfillmentRate >= 100 ? 'bg-soft-success text-success' : 'bg-soft-primary text-primary' }} fs-10 px-1.5 py-0.5">
                                    {{ $fulfillmentRate }}%
                                </span>
                            </div>
                            <div class="text-muted fs-11">
                                {{ $order->dispatches->count() }} Delivery Challan(s) / DO
                                @if ($totalServiceQty > 0)
                                    &bull; <span class="text-primary font-monospace">{{ (float) $totalServiceQty }} Service (Direct
                                        Bill)</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- KPI 3: Invoicing Status -->
                <div class="col-sm-6 col-lg-3">
                    <div class="so-metric-box">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Invoiced Amount</span>
                            <i class="feather-file-text text-warning fs-14"></i>
                        </div>
                        <div class="fs-16 fw-bold text-dark mb-0.5">{{ format_currency($totalInvoicedAmount) }}</div>
                        <div class="text-muted fs-11">
                            {{ $order->invoices->count() }} Invoice(s) &bull; Unbilled:
                            {{ format_currency(max(0, $grandTotal - $totalInvoicedAmount)) }}
                        </div>
                    </div>
                </div>

                <!-- KPI 4: Payments & Balance -->
                <div class="col-sm-6 col-lg-3">
                    <div class="so-metric-box">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Payments Collected</span>
                            <i class="feather-dollar-sign text-success fs-14"></i>
                        </div>
                        <div class="fs-16 fw-bold text-dark mb-0.5">{{ format_currency($totalPaidAmount) }}</div>
                        <div class="fs-11 fw-semibold {{ $balanceDue > 0.01 ? 'text-danger' : 'text-success' }}">
                            Balance Due: {{ format_currency($balanceDue) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== NOTEBOOK NAVIGATION TABS ==================== -->
        <div class="px-4 pt-2 d-print-none bg-white">
            <ul class="nav nav-tabs odoo-notebook-tabs" id="salesOrderTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-order-link" data-bs-toggle="tab" href="#tab-order" role="tab">
                        <i class="feather-shopping-bag me-2 text-primary fs-14"></i>
                        {{ __('crm.tab_so_details') ?? 'Sales Order Details' }}
                    </a>
                </li>
                @if(!$order->hasOnlyServices())
                    <li class="nav-item">
                        <a class="nav-link" id="tab-dispatches-link" data-bs-toggle="tab" href="#tab-dispatches" role="tab">
                            <i class="feather-truck me-2 text-muted fs-14"></i>
                            {{ __('crm.tab_dispatches') ?? 'Delivery Challans / DO' }}
                            <span
                                class="badge bg-soft-primary text-primary ms-2 px-2.5 py-1 fs-11 fw-bold rounded-pill border border-primary-subtle shadow-2xs">{{ $order->dispatches->count() }}</span>
                        </a>
                    </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link" id="tab-invoices-link" data-bs-toggle="tab" href="#tab-invoices" role="tab">
                        <i class="feather-file-text me-2 text-muted fs-14"></i>
                        {{ __('crm.tab_invoices') ?? 'Invoices' }}
                        <span
                            class="badge bg-soft-primary text-primary ms-2 px-2.5 py-1 fs-11 fw-bold rounded-pill border border-primary-subtle shadow-2xs">{{ $order->invoices->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-payments-link" data-bs-toggle="tab" href="#tab-payments" role="tab">
                        <i class="feather-dollar-sign me-2 text-muted fs-14"></i>
                        {{ __('crm.tab_payments') ?? 'Payments' }}
                        <span
                            class="badge bg-soft-primary text-primary ms-2 px-2.5 py-1 fs-11 fw-bold rounded-pill border border-primary-subtle shadow-2xs">{{ $order->allocations->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-returns-link" data-bs-toggle="tab" href="#tab-returns" role="tab">
                        <i class="feather-rotate-ccw me-2 text-muted fs-14"></i>
                        {{ __('crm.tab_returns') ?? 'Returns' }}
                        <span
                            class="badge bg-soft-secondary text-secondary ms-2 px-2.5 py-1 fs-11 fw-bold rounded-pill border border-secondary-subtle shadow-2xs">{{ $order->returns->count() }}</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- ==================== TAB PANES CONTENT ==================== -->
        <div class="card-body p-4">
            <div class="tab-content" id="salesOrderTabContent">

                <!-- TAB 1: Order Details & Items -->
                <div class="tab-pane fade show active" id="tab-order" role="tabpanel">

                    <!-- 2-COLUMN STRUCTURED INFORMATION CARDS -->
                    <div class="row g-4 mb-4">

                        <!-- Left Column: Customer & Billing Profile -->
                        <div class="col-lg-6">
                            <div class="p-3 rounded-3 border bg-white h-100 shadow-2xs"
                                style="border-color: #e2e8f0 !important;">
                                <div class="d-flex align-items-center justify-content-between pb-2 mb-2.5 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <div
                                            class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle fw-bold">
                                            <i class="feather-user fs-13"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase tracking-wider">
                                            {{ __('crm.customer_info') ?? 'CUSTOMER INFO' }}
                                        </h6>
                                    </div>
                                    @if($order->customer)
                                        <a href="{{ route('crm.customers.show', $order->customer_id) }}"
                                            class="btn btn-xs btn-outline-primary px-2 py-0.5 fs-11">
                                            View Customer
                                        </a>
                                    @endif
                                </div>

                                <div class="so-info-row">
                                    <div class="so-info-label">{{ __('crm.customer_name') ?? 'Customer Name' }}</div>
                                    <div class="so-info-value">
                                        <strong class="text-dark">{{ $order->customer?->name ?: '—' }}</strong>
                                        @if($order->customer?->company_name && $order->customer->company_name !== $order->customer->name)
                                            <span class="text-muted d-block fs-11">({{ $order->customer->company_name }})</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="so-info-row">
                                    <div class="so-info-label">{{ __('crm.email') ?? 'Email' }}</div>
                                    <div class="so-info-value">
                                        @if($order->customer?->email)
                                            <a href="mailto:{{ $order->customer->email }}" class="text-primary hover-underline">
                                                {{ $order->customer->email }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="so-info-row">
                                    <div class="so-info-label">{{ __('crm.contact_phone') ?? 'Contact Phone' }}</div>
                                    <div class="so-info-value">
                                        @if($order->customer?->phone)
                                            <a href="tel:{{ $order->customer->phone }}" class="text-dark hover-underline">
                                                {{ $order->customer->phone }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="so-info-row">
                                    <div class="so-info-label">GSTIN / Tax ID</div>
                                    <div class="so-info-value font-monospace text-dark">
                                        {{ $order->customer?->gstin ?: '—' }}
                                    </div>
                                </div>

                                <div class="mt-3 pt-2 border-top">
                                    <div
                                        class="d-flex align-items-center gap-1.5 text-muted fs-11 fw-bold text-uppercase mb-1.5">
                                        <i class="feather-map-pin text-primary fs-12"></i>
                                        {{ __('crm.billing_address') ?? 'BILLING ADDRESS' }}
                                    </div>
                                    <div class="p-2.5 rounded bg-light bg-opacity-70 fs-12 text-dark"
                                        style="min-height: 48px; white-space: pre-line; line-height: 1.45;">
                                        {{ $order->billing_address ?: ($order->customer?->billing_address ?: __('crm.no_billing_address') ?? 'No billing address provided.') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Order Logistics & Schedule -->
                        <div class="col-lg-6">
                            <div class="p-3 rounded-3 border bg-white h-100 shadow-2xs"
                                style="border-color: #e2e8f0 !important;">
                                <div class="d-flex align-items-center justify-content-between pb-2.5 mb-3 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-text avatar-sm bg-soft-info text-info rounded-circle fw-bold">
                                            <i class="feather-truck fs-13"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase tracking-wider">
                                            {{ __('crm.order_schedule') ?? 'ORDER SCHEDULE' }}
                                        </h6>
                                    </div>
                                    <span class="badge bg-soft-secondary text-secondary fs-11 px-2 py-0.5">
                                        {{ $order->freight_terms ?: 'Freight Standard' }}
                                    </span>
                                </div>

                                <div class="so-info-row">
                                    <div class="so-info-label">{{ __('crm.order_date') ?? 'Order Date' }}</div>
                                    <div class="so-info-value text-dark">
                                        <i class="feather-calendar me-1 text-muted fs-11"></i>
                                        {{ $order->order_date ? $order->order_date->format('d/m/Y') : '—' }}
                                    </div>
                                </div>

                                <div class="so-info-row">
                                    <div class="so-info-label">{{ __('crm.est_shipment') ?? 'Est. Shipment' }}</div>
                                    <div class="so-info-value text-dark">
                                        <i class="feather-clock me-1 text-muted fs-11"></i>
                                        {{ $order->shipment_date ? $order->shipment_date->format('d/m/Y') : (__('crm.not_scheduled') ?? 'Not Scheduled') }}
                                    </div>
                                </div>

                                <div class="so-info-row">
                                    <div class="so-info-label">{{ __('crm.payment_terms') ?? 'Payment Terms' }}</div>
                                    <div class="so-info-value">
                                        <span class="badge bg-soft-primary text-primary fs-11 px-2 py-0.5">
                                            {{ \App\Domains\Platform\Models\PaymentTerm::getLabel($order->payment_terms) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="so-info-row">
                                    <div class="so-info-label">{{ __('crm.sales_rep') ?? 'Sales Rep' }}</div>
                                    <div class="so-info-value d-flex align-items-center gap-1.5">
                                        @if($order->salesPerson)
                                            <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle fw-bold"
                                                style="width: 20px; height: 20px; font-size: 10px;">
                                                {{ strtoupper(substr($order->salesPerson->name, 0, 1)) }}
                                            </div>
                                            <span>{{ $order->salesPerson->name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3 pt-2.5 border-top">
                                    <div
                                        class="d-flex align-items-center gap-1.5 text-muted fs-11 fw-bold text-uppercase mb-1.5">
                                        <i class="feather-truck text-info fs-12"></i>
                                        {{ __('crm.shipping_address') ?? 'SHIPPING ADDRESS' }}
                                    </div>
                                    <div class="p-2.5 rounded bg-light bg-opacity-70 fs-12 text-dark"
                                        style="min-height: 48px; white-space: pre-line; line-height: 1.45;">
                                        {{ $order->shipping_address ?: ($order->customer?->shipping_address ?: __('crm.no_shipping_address') ?? 'No shipping address provided.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== ITEMS TABLE CARD ==================== -->
                    <div class="border rounded-3 bg-white mb-4 overflow-hidden shadow-2xs"
                        style="border-color: #e2e8f0 !important;">
                        <div
                            class="p-3 bg-light bg-opacity-60 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="feather-package text-primary fs-14"></i>
                                <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase tracking-wider">
                                    {{ __('crm.product_details') ?? 'PRODUCT DETAILS' }}
                                </h6>
                                <span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 rounded-pill ms-1">
                                    {{ $order->items->count() }}
                                </span>
                            </div>
                            <span class="fs-12 text-muted">
                                Tax Type: <strong
                                    class="text-dark">{{ ucwords(str_replace('_', ' ', $order->tax_type ?? 'item_wise_tax')) }}</strong>
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0 text-start">
                                <thead class="fs-11 text-uppercase fw-bold text-muted"
                                    style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                    <tr>
                                        <th class="ps-3 py-2.5 text-center" style="width: 3%;">#</th>
                                        <th class="py-2.5 ps-3" style="width: 29%;">
                                            {{ __('crm.product_details') ?? 'PRODUCT DETAILS' }}</th>
                                        <th class="py-2.5 text-center" style="width: 11%;">
                                            {{ __('crm.warehouse') ?? 'WAREHOUSE' }}</th>
                                        <th class="text-center py-2.5" style="width: 8%;">{{ __('crm.qty') ?? 'ORDER QTY' }}</th>
                                        <th class="text-center py-2.5" style="width: 17%;">FULFILLMENT STATUS</th>
                                        <th class="text-end py-2.5 pe-3" style="width: 10%;">
                                            {{ __('crm.unit_price') ?? 'UNIT PRICE' }}</th>
                                        <th class="text-end py-2.5 pe-3" style="width: 7%;">
                                            {{ __('crm.discount') ?? 'DISCOUNT' }}</th>
                                        <th class="text-end py-2.5 pe-3" style="width: 5%;">
                                            {{ __('crm.taxes_percent') ?? 'TAXES (%)' }}</th>
                                        <th class="text-end pe-4 py-2.5" style="width: 10%;">
                                            {{ __('crm.amount') ?? 'AMOUNT' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-12 text-dark">
                                    @foreach ($order->items as $index => $item)
                                        @php
                                            $qty = (float) $item->quantity;
                                            $price = (float) $item->unit_price;
                                            $untaxed = $qty * $price;
                                            $discount = (float) $item->discount;
                                            $taxable = max(0, $untaxed - $discount);
                                            $taxRate = (float) $item->tax_rate;
                                            $lineTax = ($order->tax_type === 'order_wise_tax') ? 0 : ($taxable * ($taxRate / 100));
                                            $lineTotalInclTax = $taxable + $lineTax;
                                            $method = $item->product?->supplier_method ?? 'buy';
                                        @endphp
                                        <tr>
                                            <td class="ps-3 text-muted text-center py-2">{{ $index + 1 }}</td>
                                            <td class="py-2 ps-3">
                                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                                    <div>
                                                        <strong class="text-dark fs-13">{{ $item->item_name }}</strong>
                                                        @if($item->product?->sku)
                                                            <span
                                                                class="badge bg-light text-muted border px-1.5 py-0.2 ms-1 font-monospace"
                                                                style="font-size: 10px;">
                                                                (SKU: {{ $item->product->sku }})
                                                            </span>
                                                        @endif
                                                        @if($item->description)
                                                            <small class="text-muted d-block mt-0.5"
                                                                style="font-size: 11px;">{{ $item->description }}</small>
                                                        @endif
                                                    </div>
                                                    <div class="text-end ms-2">
                                                        @if ($item->isService())
                                                            <span
                                                                class="badge bg-soft-info text-info px-2 py-0.5 fs-10 fw-semibold rounded-pill"><i
                                                                    class="feather-zap me-0.5"
                                                                    style="font-size: 8px;"></i>Service</span>
                                                        @elseif ($method === 'manufacture')
                                                            <span
                                                                class="badge bg-soft-warning text-warning px-2 py-0.5 fs-10 fw-semibold rounded-pill">Manufacture</span>
                                                            @php
                                                                $linkedMo = $order->productionOrders->firstWhere('sales_order_item_id', $item->id);
                                                            @endphp
                                                            @if ($linkedMo)
                                                                <div class="mt-0.5">
                                                                    <a href="{{ route('production.orders.show', $linkedMo->id) }}"
                                                                        class="text-primary fw-bold fs-9 bg-soft-primary px-1.5 py-0.2 rounded border border-primary border-opacity-10">
                                                                        <i class="feather-cpu" style="font-size: 8px;"></i>
                                                                        {{ $linkedMo->order_number }}
                                                                    </a>
                                                                </div>
                                                            @endif
                                                        @else
                                                            <span
                                                                class="badge bg-soft-success text-success px-2 py-0.5 fs-10 fw-semibold rounded-pill">Trade</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2 text-center">
                                                @if ($item->isService())
                                                    <span class="badge bg-light text-muted border px-2 py-0.5 fs-11"
                                                        title="Service item - Non-inventory">
                                                        <i class="feather-minus me-1"></i>Non-Inventory
                                                    </span>
                                                @else
                                                    <span class="badge bg-light text-dark border px-2 py-1 fs-11">
                                                        <i
                                                            class="feather-box me-1 text-muted"></i>{{ $item->warehouse?->name ?: '—' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center py-2">
                                                <span class="fw-bold fs-13 text-dark">{{ (float) $item->quantity }}</span>
                                                @if(!$item->isService())
                                                    <div class="text-muted fs-10">Open: <strong class="{{ $item->open_allocatable_qty > 0 ? 'text-primary' : 'text-muted' }}">{{ (float)$item->open_allocatable_qty }}</strong></div>
                                                @endif
                                            </td>
                                            <td class="text-center py-2">
                                                @if ($item->isService())
                                                    @php
                                                        $serviceInvoicedQty = \App\Domains\Sales\Models\InvoiceItem::where('sales_order_item_id', $item->id)
                                                            ->whereHas('invoice', fn($q) => $q->where('status', '!=', 'Cancelled'))
                                                            ->sum('quantity');
                                                        $isServiceInvoiced = $serviceInvoicedQty >= (float)$item->quantity && (float)$item->quantity > 0;
                                                    @endphp
                                                    @if ($isServiceInvoiced)
                                                        <span class="badge bg-soft-success text-success border border-success-subtle fs-11 px-2 py-0.5 d-inline-flex align-items-center mb-1">
                                                            <i class="feather-check-circle me-1"></i>Fulfilled (Invoiced)
                                                        </span>
                                                    @elseif ($serviceInvoicedQty > 0)
                                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-11 px-2 py-0.5 d-inline-flex align-items-center mb-1">
                                                            <i class="feather-clock me-1"></i>Partially Invoiced
                                                        </span>
                                                    @else
                                                        <span class="badge bg-soft-info text-info border border-info-subtle fs-11 px-2 py-0.5 d-inline-flex align-items-center mb-1">
                                                            <i class="feather-file-text me-1"></i>Direct Billing
                                                        </span>
                                                    @endif
                                                    <div class="d-flex align-items-center justify-content-center gap-1.5 fs-10 text-muted flex-wrap">
                                                        <span>Non-Inventory</span>
                                                        <span>&bull;</span>
                                                        <span class="{{ $serviceInvoicedQty > 0 ? 'text-success fw-bold' : '' }}">Inv: {{ (float)$serviceInvoicedQty }}</span>
                                                    </div>
                                                @else
                                                    @php
                                                        $rawStatus = $item->status ?: 'Pending';
                                                        $statusConfig = match($rawStatus) {
                                                            'Allocated' => ['badge' => 'bg-soft-primary text-primary border-primary-subtle', 'icon' => 'feather-box', 'label' => 'Allocated (In DO)'],
                                                            'Partially Dispatched' => ['badge' => 'bg-soft-warning text-warning border-warning-subtle', 'icon' => 'feather-truck', 'label' => 'Partially Dispatched'],
                                                            'Fulfilled', 'Dispatched' => ['badge' => 'bg-soft-success text-success border-success-subtle', 'icon' => 'feather-check-circle', 'label' => 'Dispatched'],
                                                            'Short-Closed' => ['badge' => 'bg-soft-secondary text-secondary border-secondary-subtle', 'icon' => 'feather-minus-circle', 'label' => 'Short-Closed'],
                                                            'Cancelled' => ['badge' => 'bg-soft-danger text-danger border-danger-subtle', 'icon' => 'feather-x-circle', 'label' => 'Cancelled'],
                                                            default => ['badge' => 'bg-soft-secondary text-muted border-secondary-subtle', 'icon' => 'feather-clock', 'label' => 'Pending Allocation'],
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $statusConfig['badge'] }} border fs-11 px-2 py-0.5 d-inline-flex align-items-center mb-1">
                                                        <i class="{{ $statusConfig['icon'] }} me-1 fs-11"></i>{{ $statusConfig['label'] }}
                                                    </span>
                                                    <div class="d-flex align-items-center justify-content-center gap-1.5 fs-10 text-muted flex-wrap">
                                                        <span title="Allocated in Dispatch Orders" class="{{ $item->allocated_qty > 0 ? 'text-primary fw-bold' : '' }}">
                                                            Alloc: {{ (float)$item->allocated_qty }}
                                                        </span>
                                                        <span>&bull;</span>
                                                        <span title="Dispatched / Outward" class="{{ $item->dispatched_qty > 0 ? 'text-success fw-bold' : '' }}">
                                                            Disp: {{ (float)$item->dispatched_qty }}
                                                        </span>
                                                        @if($item->invoiced_qty > 0)
                                                            <span>&bull;</span>
                                                            <span title="Invoiced" class="text-info fw-bold">
                                                                Inv: {{ (float)$item->invoiced_qty }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end text-muted py-2 pe-3 font-monospace">
                                                {{ format_currency($item->unit_price) }}</td>
                                            <td class="text-end text-danger py-2 pe-3 font-monospace">
                                                @if($item->discount > 0)
                                                    -{{ format_currency($item->discount) }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end text-muted py-2 pe-3">
                                                @if($order->tax_type !== 'without_tax')
                                                    {{ number_format($item->tax_rate, 2) }}%
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-end pe-4 fw-extrabold text-dark py-2 font-monospace fs-13">
                                                {{ format_currency($lineTotalInclTax) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ==================== FINANCIAL SUMMARY & REMARKS ==================== -->
                    <div class="row g-3 text-start mt-1">

                        <!-- Left: Terms & Notes -->
                        <div class="col-lg-7 col-xl-8">
                            @if($order->terms_conditions)
                                <div class="p-3 rounded-3 border bg-white mb-2 shadow-2xs"
                                    style="border-color: #e2e8f0 !important;">
                                    <div
                                        class="d-flex align-items-center gap-1.5 text-muted fs-11 fw-bold text-uppercase mb-1.5">
                                        <i class="feather-file-text text-primary"></i>
                                        {{ __('crm.terms_conditions') ?? 'Terms & Conditions' }}
                                    </div>
                                    <div class="text-muted fs-12 terms-conditions-content" style="line-height: 1.5;">
                                        {!! $order->terms_conditions !!}
                                    </div>
                                </div>
                            @endif

                            @if($order->notes)
                                <div class="p-3 rounded-3 border bg-white shadow-2xs" style="border-color: #e2e8f0 !important;">
                                    <div
                                        class="d-flex align-items-center gap-1.5 text-muted fs-11 fw-bold text-uppercase mb-1.5">
                                        <i class="feather-edit-3 text-primary"></i>
                                        {{ __('crm.internal_notes_remarks') ?? 'Internal Remarks / Notes' }}
                                    </div>
                                    <p class="text-muted fs-12 mb-0" style="white-space: pre-line; line-height: 1.5;">
                                        {{ $order->notes }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- Right: Financial Summary Card (Clean, Balanced & Compact) -->
                        <div class="col-lg-5 col-xl-4 ms-auto">
                            <div class="card border rounded-3 bg-white shadow-2xs overflow-hidden mb-0"
                                style="border-color: #e2e8f0 !important;">
                                <!-- Card Header -->
                                <div class="border-bottom bg-light bg-opacity-50 d-flex align-items-center justify-content-between"
                                    style="padding: 7px 12px !important;">
                                    <div class="d-flex align-items-center">
                                        <i class="feather-pie-chart text-primary me-1 fs-13"></i>
                                        <span class="fw-bold text-dark fs-11 text-uppercase tracking-wider">Financial
                                            Summary</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span
                                            class="badge bg-soft-primary text-primary border border-primary-subtle px-1.5 py-0.5 fs-10 fw-semibold text-uppercase">
                                            {{ $taxMode === 'inclusive' ? 'Tax Inclusive' : 'Tax Exclusive' }}
                                        </span>
                                        <span
                                            class="badge bg-white text-muted border border-secondary-subtle px-1.5 py-0.5 fs-10 fw-semibold text-uppercase">
                                            {{ str_replace('_', ' ', $order->tax_type ?? 'Tax Summary') }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Card Body: Balanced Padding (8px top/bottom, 12px left/right) -->
                                <div class="bg-white" style="padding: 8px 12px !important;">
                                    <!-- Subtotal -->
                                    <div class="d-flex justify-content-between align-items-center py-0.5 fs-12">
                                        <span
                                            class="text-muted fw-medium">{{ $taxMode === 'inclusive' ? 'Subtotal (Price incl. GST)' : 'Subtotal (Excl. Tax)' }}</span>
                                        <span
                                            class="fw-bold text-dark font-monospace fs-12">{{ format_currency($grossSubtotal) }}</span>
                                    </div>

                                    <!-- Discounts -->
                                    @if($order->discount_type !== 'without_discount' && $effectiveDiscount > 0)
                                        <div class="d-flex justify-content-between align-items-center py-0.5 fs-12 text-danger">
                                            <span class="fw-medium d-flex align-items-center gap-1">
                                                <i class="feather-tag fs-11"></i>Less: Discounts
                                            </span>
                                            <span
                                                class="fw-bold font-monospace fs-12">-{{ format_currency($effectiveDiscount) }}</span>
                                        </div>
                                    @endif

                                    <!-- Taxable Base Amount -->
                                    <div
                                        class="d-flex justify-content-between align-items-center py-0.5 fs-12 border-top border-light-subtle">
                                        <span
                                            class="text-dark fw-semibold">{{ $taxMode === 'inclusive' ? 'Taxable Value (Excl. Tax)' : 'Taxable Base Amount' }}</span>
                                        <span
                                            class="fw-bold text-dark font-monospace fs-12">{{ format_currency($taxableBase) }}</span>
                                    </div>

                                    <!-- GST Tax Breakdown Inset -->
                                    @if($order->tax_type !== 'without_tax' && $itemsTaxAmount > 0)
                                        <div class="my-1 rounded-2 bg-light bg-opacity-60 border border-light-subtle"
                                            style="padding: 6px 10px !important;">
                                            <div
                                                class="d-flex justify-content-between align-items-center mb-1 fs-10 text-muted text-uppercase fw-bold">
                                                <span>Tax Breakdown {{ $taxMode === 'inclusive' ? '(Included)' : '' }}</span>
                                                <span
                                                    class="badge bg-soft-info text-info fs-10 py-0.5 px-1.5">{{ strtoupper(str_replace('_', ' + ', $gstType)) }}</span>
                                            </div>
                                            @if($gstType === 'cgst_sgst')
                                                <div
                                                    class="d-flex justify-content-between align-items-center py-0.5 fs-11 text-muted">
                                                    <span>Central GST (CGST)
                                                        {{ $taxMode === 'inclusive' ? '(Included)' : '' }}</span>
                                                    <span
                                                        class="font-monospace fw-semibold text-dark">{{ ($taxMode === 'inclusive' ? '' : '+') . format_currency($itemsTaxAmount / 2) }}</span>
                                                </div>
                                                <div
                                                    class="d-flex justify-content-between align-items-center py-0.5 fs-11 text-muted">
                                                    <span>State GST (SGST) {{ $taxMode === 'inclusive' ? '(Included)' : '' }}</span>
                                                    <span
                                                        class="font-monospace fw-semibold text-dark">{{ ($taxMode === 'inclusive' ? '' : '+') . format_currency($itemsTaxAmount / 2) }}</span>
                                                </div>
                                            @else
                                                <div
                                                    class="d-flex justify-content-between align-items-center py-0.5 fs-11 text-muted">
                                                    <span>Integrated GST (IGST)
                                                        {{ $taxMode === 'inclusive' ? '(Included)' : '' }}</span>
                                                    <span
                                                        class="font-monospace fw-semibold text-dark">{{ ($taxMode === 'inclusive' ? '' : '+') . format_currency($itemsTaxAmount) }}</span>
                                                </div>
                                            @endif
                                            <div
                                                class="d-flex justify-content-between align-items-center pt-1 mt-0.5 border-top border-secondary-subtle fs-11">
                                                <span class="fw-bold text-dark">Total Tax
                                                    {{ $taxMode === 'inclusive' ? '(Included)' : '' }}</span>
                                                <span
                                                    class="fw-bold text-dark font-monospace">{{ ($taxMode === 'inclusive' ? '' : '+') . format_currency($itemsTaxAmount) }}</span>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Freight Charges -->
                                    @if($freightAmount > 0)
                                        <div class="d-flex justify-content-between align-items-center py-0.5 fs-12">
                                            <span class="text-muted d-flex align-items-center gap-1">
                                                <i class="feather-truck fs-11 text-muted"></i>Freight Charges
                                            </span>
                                            <span
                                                class="fw-bold text-primary font-monospace fs-12">+{{ format_currency($freightAmount) }}</span>
                                        </div>
                                    @endif

                                    <!-- Adjustment / Round-off -->
                                    @if($adjustment != 0)
                                        <div class="d-flex justify-content-between align-items-center py-0.5 fs-12">
                                            <span class="text-muted">Round-off / Adjustment</span>
                                            <span
                                                class="fw-bold text-dark font-monospace fs-12">{{ ($adjustment > 0 ? '+' : '') . format_currency($adjustment) }}</span>
                                        </div>
                                    @endif

                                    <!-- Grand Total Line -->
                                    <div class="d-flex justify-content-between align-items-center mt-1 mb-0 rounded-2"
                                        style="padding: 7px 10px !important; background: linear-gradient(135deg, rgba(30, 64, 175, 0.08) 0%, rgba(59, 130, 246, 0.04) 100%); border: 1px solid rgba(30, 64, 175, 0.2);">
                                        <div>
                                            <span
                                                class="d-block fw-bold text-dark fs-11 text-uppercase tracking-wider">Grand
                                                Total</span>
                                            <span class="text-muted fs-10">All taxes & charges included</span>
                                        </div>
                                        <span
                                            class="fw-extrabold text-primary fs-16 font-monospace">{{ format_currency($grandTotal) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(!$order->hasOnlyServices())
                    <!-- ==================== TAB 2: Delivery Challans (Dispatches) ==================== -->
                    <div class="tab-pane fade" id="tab-dispatches" role="tabpanel">
                        <div
                            class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light bg-opacity-40 rounded-top">
                            <div class="d-flex align-items-center gap-2">
                                <i class="feather-truck fs-16 text-primary"></i>
                                <h6 class="mb-0 fw-bold text-dark fs-14">
                                    {{ __('crm.delivery_challans_dispatches') ?? 'Delivery Challans & Dispatches' }}</h6>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="soDispatchesTable">
                                <thead>
                                    <tr>
                                        <th class="ps-4" style="width: 18%;">{{ __('crm.dispatch_num') ?? 'Challan Number' }}
                                        </th>
                                        <th style="width: 12%;">{{ __('crm.date') ?? 'Date' }}</th>
                                        <th style="width: 22%;">{{ __('crm.transporter_carrier') ?? 'Carrier / Transporter' }}
                                        </th>
                                        <th style="width: 14%;">{{ __('crm.vehicle_no') ?? 'Vehicle No' }}</th>
                                        <th style="width: 10%;">{{ __('crm.status') ?? 'Status' }}</th>
                                        <th class="text-end pe-4" style="width: 24%;">{{ __('crm.actions') ?? 'Actions' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13 text-dark">
                                    @forelse ($order->dispatches as $dispatch)
                                        @php
                                            $dispBadge = 'bg-soft-secondary text-secondary';
                                            if ($dispatch->status === 'Pending')
                                                $dispBadge = 'bg-soft-warning text-warning';
                                            elseif ($dispatch->status === 'Confirmed')
                                                $dispBadge = 'bg-soft-primary text-primary';
                                            elseif ($dispatch->status === 'Dispatched' || $dispatch->status === 'Shipped')
                                                $dispBadge = 'bg-soft-info text-info';
                                            elseif ($dispatch->status === 'Delivered')
                                                $dispBadge = 'bg-soft-success text-success';
                                            elseif ($dispatch->status === 'Cancelled')
                                                $dispBadge = 'bg-soft-danger text-danger';

                                            $isDispatchInvoiced = false;
                                            if ($dispatch->status === 'Invoiced' || !empty($dispatch->invoice_id)) {
                                                $isDispatchInvoiced = true;
                                            } elseif (!$hasUnbilledQty) {
                                                $isDispatchInvoiced = true;
                                            } elseif ($order->invoices) {
                                                $isDispatchInvoiced = $order->invoices->where('status', '!=', 'Cancelled')->contains(function ($inv) use ($dispatch) {
                                                    return ($dispatch->invoice_id && $inv->id == $dispatch->invoice_id)
                                                        || ($dispatch->material_requirement_id && $inv->material_requirement_id == $dispatch->material_requirement_id);
                                                });
                                            }
                                        @endphp
                                        <tr>
                                            <td class="ps-4 fw-bold">
                                                <a href="{{ route('sales.dispatches.show', $dispatch->id) }}"
                                                    class="text-primary hover-underline">
                                                    {{ $dispatch->dispatch_number }}
                                                </a>
                                            </td>
                                            <td class="text-muted">
                                                {{ $dispatch->dispatch_date ? $dispatch->dispatch_date->format('d/m/Y') : '—' }}
                                            </td>
                                            <td>
                                                <span
                                                    class="fw-semibold text-dark">{{ $dispatch->transporter?->name ?: ($dispatch->carrier ?: '—') }}</span>
                                            </td>
                                            <td class="font-monospace text-muted">{{ $dispatch->vehicle_number ?: '—' }}</td>
                                            <td>
                                                <span
                                                    class="badge {{ $dispBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $dispatch->status }}</span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="hstack gap-2 justify-content-end align-items-center">
                                                    @if(!$isDispatchInvoiced && in_array($dispatch->status, ['Confirmed', 'Shipped', 'Dispatched', 'Delivered']))
                                                        <x-ui.button
                                                            href="{!! route('sales.invoices.create', ['dispatch_order_id' => $dispatch->id, 'material_requirement_id' => $dispatch->material_requirement_id, 'sales_order_id' => $order->id, 'mode' => 'dispatch_order']) !!}"
                                                            variant="soft-primary" size="xs" icon="feather-file-text"
                                                            class="fw-bold px-2.5 py-1 fs-11 text-nowrap"
                                                            title="Create Invoice against this Dispatch Order">
                                                            {{ __('crm.create_invoice_btn') ?? 'Create Invoice' }}
                                                        </x-ui.button>
                                                    @elseif($isDispatchInvoiced)
                                                        <span
                                                            class="badge bg-soft-success text-success fs-10 fw-semibold px-2 py-0.5"><i
                                                                class="feather-check me-1"></i>{{ __('crm.invoiced_badge') ?? 'Invoiced' }}</span>
                                                    @endif

                                                    <x-ui.action-dropdown :viewUrl="route('sales.dispatches.show', $dispatch->id)"
                                                        id="dispAction-{{ $dispatch->id }}">
                                                        <x-ui.dropdown-item
                                                            href="{{ route('sales.dispatches.show', $dispatch->id) }}"
                                                            icon="feather-eye me-2">
                                                            {{ __('crm.view_dispatch') ?? 'View Dispatch' }}
                                                        </x-ui.dropdown-item>
                                                        @if(!$isDispatchInvoiced && in_array($dispatch->status, ['Confirmed', 'Shipped', 'Dispatched', 'Delivered']))
                                                            <x-ui.dropdown-item
                                                                href="{!! route('sales.invoices.create', ['dispatch_order_id' => $dispatch->id, 'material_requirement_id' => $dispatch->material_requirement_id, 'sales_order_id' => $order->id, 'mode' => 'dispatch_order']) !!}"
                                                                icon="feather-file-text me-2" class="text-success fw-semibold">
                                                                {{ __('crm.create_invoice_btn') ?? 'Create Invoice' }}
                                                            </x-ui.dropdown-item>
                                                        @endif
                                                        <x-ui.dropdown-item
                                                            href="{{ route('sales.dispatches.download-challan', $dispatch->id) }}"
                                                            icon="feather-download me-2" target="_blank">
                                                            {{ __('crm.download_challan_pdf') ?? 'Download Challan' }}
                                                        </x-ui.dropdown-item>
                                                    </x-ui.action-dropdown>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="feather-truck fs-1 mb-2 d-block text-gray-300"></i>
                                                {{ __('crm.no_dispatches_text') ?? 'No delivery challans recorded for this sales order yet.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                @endif

                <!-- ==================== TAB 3: Invoices ==================== -->
                <div class="tab-pane fade" id="tab-invoices" role="tabpanel">
                    <div
                        class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light bg-opacity-40 rounded-top">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-file-text fs-16 text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark fs-14">
                                {{ __('crm.sales_invoices_header') ?? 'Sales Invoices' }}</h6>
                        </div>
                        @if (($order->status === 'Confirmed' || $order->status === 'Partially Shipped' || $order->status === 'Shipped') && ($hasUnbilledQty ?? true) && ($invoicingPolicy !== 'dispatch_order' || $order->hasOnlyServices()))
                            <x-ui.button
                                href="{{ route('sales.invoices.create', ['sales_order_id' => $order->id, 'mode' => 'sales_order']) }}"
                                variant="primary" size="sm" icon="feather-plus" class="fw-bold">
                                {{ __('crm.create_invoice_btn') ?? 'Create Invoice' }}
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soInvoicesTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 25%;">
                                        {{ __('crm.invoice_number_col') ?? 'Invoice Number' }}</th>
                                    <th style="width: 18%;">{{ __('crm.date') ?? 'Date' }}</th>
                                    <th style="width: 25%;">{{ __('crm.source_shipment') ?? 'Reference / Shipment' }}</th>
                                    <th class="text-end" style="width: 17%;">{{ __('crm.grand_total') ?? 'Total Amount' }}
                                    </th>
                                    <th style="width: 15%;">{{ __('crm.status') ?? 'Status' }}</th>
                                    <th class="text-end pe-4" style="width: 15%;">{{ __('crm.actions') ?? 'Actions' }}</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->invoices as $inv)
                                    @php
                                        $invBadge = 'bg-soft-secondary text-secondary';
                                        if ($inv->status === 'Paid')
                                            $invBadge = 'bg-soft-success text-success';
                                        elseif ($inv->status === 'Partially Paid')
                                            $invBadge = 'bg-soft-warning text-warning';
                                        elseif ($inv->status === 'Sent' || $inv->status === 'Posted')
                                            $invBadge = 'bg-soft-info text-info';
                                        elseif ($inv->status === 'Cancelled')
                                            $invBadge = 'bg-soft-danger text-danger';
                                    @endphp
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="{{ route('sales.invoices.show', $inv->id) }}"
                                                class="text-primary hover-underline">
                                                {{ $inv->invoice_number }}
                                            </a>
                                        </td>
                                        <td class="text-muted">{{ date('d/m/Y', strtotime($inv->invoice_date)) }}</td>
                                        <td>
                                            @if ($inv->dispatchOrders && $inv->dispatchOrders->isNotEmpty())
                                                <a href="{{ route('sales.dispatches.show', $inv->dispatchOrders->first()->id) }}"
                                                    class="text-primary fw-semibold font-monospace">
                                                    <i
                                                        class="feather-truck me-1"></i>{{ $inv->dispatchOrders->first()->dispatch_number }}
                                                </a>
                                            @elseif ($inv->materialRequirement)
                                                <a href="{{ route('inventory.material-requirements.show', $inv->material_requirement_id) }}"
                                                    class="text-muted fw-semibold font-monospace">
                                                    {{ $inv->materialRequirement->requirement_number }}
                                                </a>
                                            @else
                                                <span
                                                    class="text-muted fs-12">{{ __('crm.advance_so_billing') ?? 'Direct SO Invoicing' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold text-dark font-monospace">
                                            {{ format_currency($inv->total_amount ?? $inv->grand_total) }}</td>
                                        <td>
                                            <span
                                                class="badge {{ $invBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $inv->status }}</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <x-ui.action-dropdown :viewUrl="route('sales.invoices.show', $inv->id)"
                                                id="invAction-{{ $inv->id }}">
                                                <x-ui.dropdown-item href="{{ route('sales.invoices.show', $inv->id) }}"
                                                    icon="feather-eye me-2">
                                                    {{ __('crm.view_invoice') ?? 'View Invoice' }}
                                                </x-ui.dropdown-item>

                                                @if(in_array($inv->status, ['Sent', 'Partially Paid', 'Posted', 'Draft']))
                                                    <x-ui.dropdown-item :href="route('sales.payments.create', ['invoice_id' => $inv->id, 'customer_id' => $order->customer_id])"
                                                        icon="feather-dollar-sign me-2" class="text-success fw-semibold">
                                                        {{ __('crm.register_payment') ?? 'Register Payment' }}
                                                    </x-ui.dropdown-item>
                                                @endif
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-file-text fs-1 mb-2 d-block text-gray-300"></i>
                                            {{ __('crm.no_invoices_text') ?? 'No invoices created for this sales order yet.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- ==================== TAB 4: Payments & Receipts ==================== -->
                <div class="tab-pane fade" id="tab-payments" role="tabpanel">
                    <div
                        class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light bg-opacity-40 rounded-top">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-dollar-sign fs-16 text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark fs-14">
                                {{ __('crm.payments_allocations_header') ?? 'Payments & Receipts' }}</h6>
                        </div>
                        @if ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
                            <x-ui.button :href="route('sales.payments.create', ['sales_order_id' => $order->id, 'customer_id' => $order->customer_id, 'allocate_to' => 'sales_order'])" variant="primary" size="sm"
                                icon="feather-plus" class="fw-bold">
                                {{ __('crm.record_receipt_advance') ?? 'Record Payment' }}
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soPaymentsTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 25%;">
                                        {{ __('crm.payment_number_col') ?? 'Receipt Number' }}</th>
                                    <th style="width: 20%;">{{ __('crm.date') ?? 'Date' }}</th>
                                    <th style="width: 20%;">{{ __('crm.method') ?? 'Payment Mode' }}</th>
                                    <th style="width: 20%;">{{ __('crm.reference_no') ?? 'Reference No' }}</th>
                                    <th class="text-end" style="width: 20%;">
                                        {{ __('crm.allocated_amount') ?? 'Allocated Amount' }}</th>
                                    <th class="text-end pe-4" style="width: 15%;">{{ __('crm.actions') ?? 'Actions' }}</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->allocations as $alloc)
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="{{ route('sales.payments.show', $alloc->payment->id) }}"
                                                class="text-primary hover-underline">
                                                {{ $alloc->payment->payment_number }}
                                            </a>
                                        </td>
                                        <td class="text-muted">{{ date('d/m/Y', strtotime($alloc->payment->payment_date)) }}
                                        </td>
                                        <td><span class="fw-semibold text-dark">{{ $alloc->payment->payment_method }}</span>
                                        </td>
                                        <td class="text-muted font-monospace">{{ $alloc->payment->reference_no ?: '—' }}</td>
                                        <td class="text-end fw-bold text-dark font-monospace">
                                            {{ format_currency($alloc->allocated_amount) }}</td>
                                        <td class="text-end pe-4">
                                            <x-ui.action-dropdown :viewUrl="route('sales.payments.show', $alloc->payment->id)"
                                                id="payAction-{{ $alloc->payment->id }}">
                                                <x-ui.dropdown-item
                                                    href="{{ route('sales.payments.show', $alloc->payment->id) }}"
                                                    icon="feather-eye me-2">
                                                    {{ __('crm.view_receipt') ?? 'View Receipt' }}
                                                </x-ui.dropdown-item>
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-dollar-sign fs-1 mb-2 d-block text-gray-300"></i>
                                            {{ __('crm.no_payments_text') ?? 'No payment allocations or advance receipts found.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- ==================== TAB 5: Sales Returns ==================== -->
                <div class="tab-pane fade" id="tab-returns" role="tabpanel">
                    <div
                        class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light bg-opacity-40 rounded-top">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-rotate-ccw fs-16 text-primary"></i>
                            <h6 class="mb-0 fw-bold text-dark fs-14">{{ __('crm.sales_returns_header') ?? 'Sales Returns' }}
                            </h6>
                        </div>
                        @if ($order->status === 'Partially Shipped' || $order->status === 'Shipped')
                            <x-ui.button href="{{ route('sales.returns.create', ['sales_order_id' => $order->id]) }}"
                                variant="primary" size="sm" icon="feather-plus" class="fw-bold">
                                {{ __('crm.create_sales_return') ?? 'Create Return' }}
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="soReturnsTable">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 25%;">
                                        {{ __('crm.return_number_col') ?? 'Return Number' }}</th>
                                    <th style="width: 20%;">{{ __('crm.date') ?? 'Date' }}</th>
                                    <th class="text-end" style="width: 20%;">
                                        {{ __('crm.refund_amount') ?? 'Refund Amount' }}</th>
                                    <th style="width: 20%;">{{ __('crm.status') ?? 'Status' }}</th>
                                    <th class="text-end pe-4" style="width: 15%;">{{ __('crm.actions') ?? 'Actions' }}</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($order->returns as $ret)
                                    @php
                                        $retBadge = 'bg-soft-secondary text-secondary';
                                        if ($ret->status === 'Completed')
                                            $retBadge = 'bg-soft-success text-success';
                                        elseif ($ret->status === 'Cancelled')
                                            $retBadge = 'bg-soft-danger text-danger';
                                    @endphp
                                    <tr>
                                        <td class="ps-4 fw-bold">
                                            <a href="{{ route('sales.returns.show', $ret->id) }}"
                                                class="text-primary hover-underline">
                                                {{ $ret->return_number }}
                                            </a>
                                        </td>
                                        <td class="text-muted">{{ date('d/m/Y', strtotime($ret->return_date)) }}</td>
                                        <td class="text-end fw-bold text-dark font-monospace">
                                            {{ format_currency($ret->total_refund_amount) }}</td>
                                        <td><span
                                                class="badge {{ $retBadge }} px-2 py-0.5 fs-11 fw-semibold">{{ $ret->status }}</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <x-ui.action-dropdown :viewUrl="route('sales.returns.show', $ret->id)"
                                                id="retAction-{{ $ret->id }}">
                                                <x-ui.dropdown-item href="{{ route('sales.returns.show', $ret->id) }}"
                                                    icon="feather-eye me-2">
                                                    {{ __('crm.view_return') ?? 'View Return' }}
                                                </x-ui.dropdown-item>
                                            </x-ui.action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="feather-rotate-ccw fs-1 mb-2 d-block text-gray-300"></i>
                                            {{ __('crm.no_returns_text') ?? 'No sales returns recorded for this order.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @if (!in_array($order->status, ['Draft', 'Cancelled']) && auth()->user()->can('create', \App\Domains\Projects\Models\Project::class))
        <x-ui.modal id="convertToProjectModal" :title="'<i class=\'feather-folder-plus me-2 text-primary\'></i>' . __('projects.convert_to_project')" :centered="true" size="lg" :formAction="route('sales.orders.convert-to-project', $order->id)" formMethod="POST">

            <p class="text-muted fs-13 mb-3">
                {{ __('projects.convert_so_to_project_desc', ['order' => $order->sales_order_number]) ?: "Convert this Sales Order into a Project. Delivery milestones will be created for each order line item automatically." }}
            </p>

            <div class="row g-3">
                <div class="col-12">
                    <x-ui.odoo-form-ui type="input" name="project_name" :label="__('projects.project_name')" :value="'Delivery: ' . $order->sales_order_number" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" name="start_date" :label="__('projects.start_date')"
                        :value="$order->order_date ? $order->order_date->format('Y-m-d') : date('Y-m-d')" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" name="end_date" :label="__('projects.end_date')"
                        :value="$order->shipment_date ? $order->shipment_date->format('Y-m-d') : date('Y-m-d', strtotime('+30 days'))" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="budget_amount"
                        :label="__('projects.budget_amount')" :value="$order->total_amount" />
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="light" size="sm" data-bs-dismiss="modal">{{ __('crm.cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold" icon="feather-check-circle">
                    {{ __('projects.create_project') }}
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <!-- Short-Close Order Modal -->
    @if (in_array($order->status, ['Confirmed', 'Partially Shipped', 'In Fulfillment']))
        <x-ui.modal id="shortCloseOrderModal" :title="'<i class=\'feather-minus-circle me-2 text-warning\'></i>' . 'Short-Close Sales Order'" :centered="true" :formAction="route('sales.orders.short-close', $order->id)" formMethod="POST">
            <div class="mb-3">
                <div class="alert alert-warning py-2.5 px-3 fs-13 mb-3 d-flex align-items-start">
                    <i class="feather-alert-triangle me-2 fs-16 text-warning mt-0.5 flex-shrink-0"></i>
                    <div>
                        <strong>Notice:</strong> Short-closing will conclude this sales order prematurely. All remaining open or unallocated item quantities will be cancelled, and pending un-shipped dispatch orders will be cancelled.
                    </div>
                </div>
                <label for="shortCloseReason" class="form-label fw-semibold fs-13">Reason for Short-Closing <span class="text-danger">*</span></label>
                <textarea name="reason" id="shortCloseReason" class="form-control fs-13" rows="3" required placeholder="Enter reason (e.g. Customer requested partial order cancellation, item discontinued...)"></textarea>
            </div>

            <x-slot:footer>
                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('crm.close') ?? 'Close' }}</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark">
                    <i class="feather-minus-circle me-1"></i>Short-Close Order
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    <!-- Cancel Order Modal -->
    @if (!in_array($order->status, ['Shipped', 'Cancelled', 'Short-Closed']))
        <x-ui.modal id="cancelOrderModal" :title="'<i class=\'feather-x-circle me-2 text-danger\'></i>' . 'Cancel Sales Order'" :centered="true" :formAction="route('sales.orders.cancel', $order->id)" formMethod="POST">
            <div class="mb-3">
                <div class="alert alert-danger py-2.5 px-3 fs-13 mb-3 d-flex align-items-start">
                    <i class="feather-alert-circle me-2 fs-16 text-danger mt-0.5 flex-shrink-0"></i>
                    <div>
                        <strong>Are you sure?</strong> You are about to cancel Sales Order <strong>{{ $order->sales_order_number }}</strong>. Any pending un-shipped dispatch orders linked to this sales order will also be cancelled automatically.
                    </div>
                </div>
                <label for="cancelReason" class="form-label fw-semibold fs-13">Reason for Cancellation</label>
                <textarea name="reason" id="cancelReason" class="form-control fs-13" rows="3" placeholder="Enter reason (e.g. Customer cancelled requirement, duplicate entry...)"></textarea>
            </div>

            <x-slot:footer>
                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('crm.close') ?? 'Close' }}</button>
                <button type="submit" class="btn btn-danger fw-bold">
                    <i class="feather-x-circle me-1"></i>Confirm Cancellation
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
@endsection

@push('styles')
    <style>
        .terms-conditions-content p {
            margin-bottom: 4px !important;
            line-height: 1.45 !important;
        }

        .terms-conditions-content p:last-child {
            margin-bottom: 0 !important;
        }

        @media print {
            @page {
                margin: 0 !important;
            }

            .nxl-sidebar,
            .nxl-header,
            .page-header,
            .d-print-none,
            .alert,
            header,
            footer,
            aside,
            nav {
                display: none !important;
            }

            body {
                background: #fff !important;
                margin: 0 !important;
                padding: 8mm 12mm !important;
            }

            .nxl-container,
            .nxl-content,
            .main-content,
            .content-body,
            .container-fluid {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                transform: none !important;
                top: 0 !important;
                position: static !important;
            }

            .print-area {
                border: 0 !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                position: static !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(function () {
            // Activate tab based on URL hash if present (e.g. #tab-invoices)
            var hash = window.location.hash;
            if (hash) {
                var triggerEl = document.querySelector('#salesOrderTabs a[href="' + hash + '"]')
                    || document.querySelector('#salesOrderTabs button[data-bs-target="' + hash + '"]');
                if (triggerEl) {
                    var tab = new bootstrap.Tab(triggerEl);
                    tab.show();
                }
            }

            // Keep URL hash updated when switching tabs
            $('#salesOrderTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                if (history.replaceState) {
                    history.replaceState(null, null, e.target.getAttribute('href'));
                } else {
                    window.location.hash = e.target.getAttribute('href');
                }
            });
        });
    </script>
@endpush