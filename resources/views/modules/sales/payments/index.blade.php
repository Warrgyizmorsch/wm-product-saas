@extends('layouts.duralux')

@section('title', __('crm.customer_payments') . ' | SaaS ERP')
@section('page-title', __('crm.customer_payments'))
@section('breadcrumb', __('crm.sales') . ' / ' . __('crm.payments'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="customer-payments" 
            :can-import="false" 
            :can-download-template="false" 
            export-route="{{ route('sales.payments.export') }}" />
        <a href="{{ route('sales.payments.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>{{ __('crm.record_payment') }}
        </a>
    </div>
@endsection

@section('content')

    <div class="erp-single-panel card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <span class="me-2 text-primary fw-bold fs-16">{{ active_currency_symbol() }}</span>{{ __('crm.customer_payments_receipts_title') }}
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">{{ __('crm.payment_number_col') }}</th>
                            <th>{{ __('crm.date') }}</th>
                            <th>{{ __('crm.customer') }}</th>
                            <th>{{ __('crm.method') }}</th>
                            <th>{{ __('crm.reference_no') }}</th>
                            <th class="text-end">{{ __('crm.amount') }}</th>
                            <th>{{ __('crm.status') }}</th>
                            <th class="text-end pe-4">{{ __('crm.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="{{ route('sales.payments.show', $payment->id) }}">
                                        {{ $payment->payment_number }}
                                    </a>
                                </td>
                                <td>{{ date('d/m/Y', strtotime($payment->payment_date)) }}</td>
                                <td>
                                    <span class="fw-bold">{{ $payment->customer?->name }}</span>
                                </td>
                                <td>{{ $payment->payment_method }}</td>
                                <td class="text-muted">{{ $payment->reference_no ?: '—' }}</td>
                                <td class="text-end fw-bold text-dark">{{ format_currency($payment->amount) }}</td>
                                <td>
                                    @php
                                        $badgeClass = 'bg-soft-secondary text-secondary';
                                        if ($payment->status == 'Confirmed') $badgeClass = 'bg-soft-success text-success';
                                        elseif ($payment->status == 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $payment->status }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2 align-items-center">
                                        <a href="{{ route('sales.payments.show', $payment->id) }}" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="{{ __('crm.view_payment_details') }}">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <span class="fs-1 text-gray-400 mb-2 d-block fw-bold opacity-50">{{ active_currency_symbol() }}</span>
                                    {{ __('crm.no_customer_payments_recorded') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection



