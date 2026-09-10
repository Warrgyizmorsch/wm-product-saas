@extends('layouts.duralux')

@section('title', 'Convert to Customer | SaaS ERP')
@section('page-title', 'Convert to Customer')
@section('breadcrumb')
    <a href="{{ route('crm.deals.index') }}">CRM</a> &gt; Convert to Customer
@endsection

@section('content')
@php
    $targetTitle = isset($deal) && $deal ? ($deal->account?->name ?: $deal->title) : ($lead ? ($lead->company_name ?: ($lead->contact_person ?: "Lead #{$lead->id}")) : ($quotation ? "Quotation #{$quotation->quotation_number}" : 'Client'));
    $formAction = isset($deal) && $deal ? route('crm.deals.processConvert', $deal->id) : ($lead ? route('crm.leads.processConvert', $lead->id) : route('crm.quotations.processConvert', $quotation->id));
    $cancelUrl = isset($deal) && $deal ? route('crm.deals.show', $deal->id) : ($lead ? route('crm.leads.show', $lead->id) : route('crm.quotations.show', $quotation->id));
    $firstMatch = $matchedCustomers->first();
@endphp

<div class="erp-single-panel bg-white p-4 p-md-5 rounded-3 border-0 shadow-sm">
    <div class="row">
        <!-- Main Form Column -->
        <div class="col-lg-8 border-end-lg pe-lg-5">
            <!-- Header Title -->
            <h4 class="fw-bold text-dark mb-3 fs-18">
                Convert {{ isset($deal) ? 'Deal' : ($lead ? 'Lead' : 'Quotation') }} <span class="text-secondary fw-normal">({{ $targetTitle }})</span>
            </h4>

            @if(!empty($matchReasons) && count($matchReasons) > 0)
                <p class="fs-13 text-dark mb-4">
                    Customer/Contact with similar details in <strong>{{ implode(', ', $matchReasons) }}</strong> already exist.
                </p>
            @endif

            <form action="{{ $formAction }}" method="POST" id="convertForm">
                @csrf
                @if(request()->has('quotation_id'))
                    <input type="hidden" name="quotation_id" value="{{ request('quotation_id') }}">
                @endif

                <div class="mb-4">
                    @if($matchedCustomers->isNotEmpty())
                        <!-- Option 1: Add to existing Customer -->
                        <div class="form-check mb-3 align-items-center">
                            <input class="form-check-input me-2 mt-1" type="radio" name="conversion_mode" id="mode_existing" value="existing" checked onchange="toggleConversionMode('existing')">
                            <label class="form-check-label fs-14 text-dark me-2" for="mode_existing">
                                Add to existing Customer / Contact
                            </label>
                            <a href="javascript:void(0)" class="fs-13 text-primary text-decoration-none fw-semibold me-3" data-bs-toggle="modal" data-bs-target="#viewMatchedCustomerModal">
                                View
                            </a>

                            <div id="existingCustomerSelectBox" class="mt-2.5 ms-4" style="max-width: 480px;">
                                <label class="form-label fs-11 text-uppercase text-muted fw-bold mb-1">Select Matched Customer:</label>
                                <x-ui.select 
                                    name="existing_customer_id" 
                                    id="existing_customer_id" 
                                    class="select2 form-select-sm erp-premium-select" 
                                    onchange="updateCustomerModal(this)">
                                    @foreach($matchedCustomers as $cust)
                                        <option value="{{ $cust->id }}" 
                                                data-name="{{ $cust->name }}"
                                                data-email="{{ $cust->email ?: 'N/A' }}" 
                                                data-phone="{{ $cust->phone ?: 'N/A' }}" 
                                                data-gstin="{{ $cust->gstin ?: 'N/A' }}"
                                                data-company="{{ $cust->company_name ?: $cust->name }}">
                                            {{ $cust->name }} {{ $cust->email ? "({$cust->email})" : '' }} {{ $cust->phone ? "- {$cust->phone}" : '' }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                        </div>
                    @endif

                    <!-- Option 2: Create New Customer -->
                    <div class="form-check mb-4 align-items-center">
                        <input class="form-check-input me-2 mt-1" type="radio" name="conversion_mode" id="mode_new" value="create_new" {{ $matchedCustomers->isEmpty() ? 'checked' : '' }} onchange="toggleConversionMode('new')">
                        <label class="form-check-label fs-14 text-dark me-2" for="mode_new">
                            Create New Customer:
                        </label>
                        <span class="bg-light text-secondary border px-2.5 py-1 rounded fs-13 fw-normal" style="background-color: #f1f5f9 !important;">
                            {{ $targetTitle }}
                        </span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex align-items-center gap-3 pt-3 mt-4 border-top">
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold fs-13">
                        Convert
                    </button>
                    <a href="{{ $cancelUrl }}" class="btn btn-light border px-4 py-2 fs-13 text-dark">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Right Info Sidebar (Zoho Style) -->
        <div class="col-lg-4 ps-lg-5 mt-4 mt-lg-0">
            <div class="p-4 p-md-4.5 bg-light rounded-3 border-0 shadow-2xs" style="background-color: #f8fafc !important;">
                <h6 class="fw-bold text-dark fs-14 mb-3 d-flex align-items-center">
                    <i class="feather-info text-primary me-2 fs-15"></i> Quick Info
                </h6>
                <div class="fs-12 text-muted mb-3.5 lh-base">
                    Converting this record will link the Deal and Quotation to an active Customer Account in your CRM & Sales master databases.
                </div>
                <ul class="list-unstyled fs-12 text-secondary mb-0">
                    <li class="mb-2.5 d-flex align-items-start"><i class="feather-check-circle text-success me-2 mt-0.5 fs-13 flex-shrink-0"></i> <span>Prevents duplicate customer entries</span></li>
                    <li class="mb-2.5 d-flex align-items-start"><i class="feather-check-circle text-success me-2 mt-0.5 fs-13 flex-shrink-0"></i> <span>Enables Sales Order generation</span></li>
                    <li class="d-flex align-items-start"><i class="feather-check-circle text-success me-2 mt-0.5 fs-13 flex-shrink-0"></i> <span>Syncs customer history & invoices</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- View Matched Customer Modal using common component x-ui.modal -->
@if($matchedCustomers->isNotEmpty())
    <x-ui.modal 
        id="viewMatchedCustomerModal" 
        title="<i class='feather-user me-1.5 text-primary'></i> Matched Customer Details" 
        size="md" 
        :centered="true"
        :showFooter="true">
        
        <div class="mb-3 border-bottom pb-2">
            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Customer / Account Name</span>
            <h5 class="fw-bold text-dark mb-0" id="modal_cust_name">{{ $firstMatch?->name }}</h5>
        </div>
        <div class="row g-3 fs-13">
            <div class="col-6">
                <span class="fs-11 text-uppercase text-muted fw-bold d-block">Email</span>
                <span class="text-dark fw-medium" id="modal_cust_email">{{ $firstMatch?->email ?: 'N/A' }}</span>
            </div>
            <div class="col-6">
                <span class="fs-11 text-uppercase text-muted fw-bold d-block">Phone</span>
                <span class="text-dark fw-medium" id="modal_cust_phone">{{ $firstMatch?->phone ?: 'N/A' }}</span>
            </div>
            <div class="col-6">
                <span class="fs-11 text-uppercase text-muted fw-bold d-block">GSTIN / Tax ID</span>
                <span class="text-dark fw-medium" id="modal_cust_gstin">{{ $firstMatch?->gstin ?: 'N/A' }}</span>
            </div>
            <div class="col-6">
                <span class="fs-11 text-uppercase text-muted fw-bold d-block">Status</span>
                <span class="badge bg-soft-success text-success fw-bold">Active Customer</span>
            </div>
        </div>

        <x-slot name="footer">
            <button type="button" class="btn btn-sm btn-secondary px-4 py-1.5 fs-13" data-bs-dismiss="modal">Close</button>
        </x-slot>
    </x-ui.modal>
@endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script>
        function updateCustomerModal(selectEl) {
            var selectedOpt = selectEl.options[selectEl.selectedIndex];
            if (!selectedOpt) return;

            var nameEl = document.getElementById('modal_cust_name');
            var emailEl = document.getElementById('modal_cust_email');
            var phoneEl = document.getElementById('modal_cust_phone');
            var gstinEl = document.getElementById('modal_cust_gstin');

            if (nameEl) nameEl.innerText = selectedOpt.getAttribute('data-name') || selectedOpt.text;
            if (emailEl) emailEl.innerText = selectedOpt.getAttribute('data-email') || 'N/A';
            if (phoneEl) phoneEl.innerText = selectedOpt.getAttribute('data-phone') || 'N/A';
            if (gstinEl) gstinEl.innerText = selectedOpt.getAttribute('data-gstin') || 'N/A';
        }

        function toggleConversionMode(mode) {
            var selectBox = document.getElementById('existingCustomerSelectBox');
            if (selectBox) {
                if (mode === 'existing') {
                    selectBox.style.display = 'block';
                } else {
                    selectBox.style.display = 'none';
                }
            }
        }

        $(document).ready(function() {
            if ($.fn.select2) {
                $('#existing_customer_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });

                $(document).on('change change.select2', '#existing_customer_id', function() {
                    updateCustomerModal(this);
                });
            }
        });
    </script>
@endpush
