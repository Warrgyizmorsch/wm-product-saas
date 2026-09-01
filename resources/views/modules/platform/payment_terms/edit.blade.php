@extends('layouts.duralux')

@section('title', 'Edit Payment Term | ' . $paymentTerm->name)
@section('page-title', 'Edit Payment Term')
@section('breadcrumb', 'Workspace / Tenant Console / Payment Terms / Edit')

@section('page-actions')
    <x-ui.button href="{{ route('platform.payment-terms.index') }}" variant="light" icon="feather-arrow-left" class="border">
        Back to Payment Terms
    </x-ui.button>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <form action="{{ route('platform.payment-terms.update', $paymentTerm) }}" method="POST">
                @csrf
                @method('PUT')

                <x-ui.odoo-form-ui type="sheet" class="shadow-sm rounded border-0">
                    <div class="border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <h5 class="fw-bold text-dark mb-0 me-3"><i class="feather-edit text-primary me-2"></i>Edit Payment Term: {{ $paymentTerm->name }}</h5>
                            <span class="badge bg-soft-primary text-primary px-2.5 py-1 fw-bold fs-11 font-monospace">{{ $paymentTerm->code }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('platform.payment-terms.index') }}" class="btn btn-light border fw-semibold">Cancel</a>
                            <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                                <i class="feather-save me-1"></i>Update Payment Term
                            </button>
                        </div>
                    </div>

                    <div class="p-4 p-md-5">
                        @if ($errors->any())
                            <div class="alert alert-danger mb-4">
                                <h6 class="fw-bold mb-2"><i class="feather-alert-triangle me-1"></i>Validation Errors:</h6>
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Payment Term Name" name="name" id="name" value="{{ old('name', $paymentTerm->name) }}" placeholder="e.g. Net 30 Days" :required="true" :error-text="$errors->first('name')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Code / Identification" name="code" id="code" value="{{ old('code', $paymentTerm->code) }}" placeholder="e.g. NET30" :error-text="$errors->first('code')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="number" min="0" label="Due Days (Credit Period in Days)" name="due_days" id="due_days" value="{{ old('due_days', $paymentTerm->due_days) }}" placeholder="e.g. 30 (0 for immediate due)" :required="true" :error-text="$errors->first('due_days')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Active Status" name="is_active" id="is_active" :required="true" :error-text="$errors->first('is_active')">
                                    <option value="1" {{ old('is_active', $paymentTerm->is_active ? '1' : '0') == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('is_active', $paymentTerm->is_active ? '1' : '0') == '0' ? 'selected' : '' }}>Inactive</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-12">
                                <x-ui.odoo-form-ui type="textarea" label="Term Description & Terms text on Invoice/PO" name="description" id="description" rows="3" placeholder="e.g. Payment is due within 30 days of invoice date." :error-text="$errors->first('description')">{{ old('description', $paymentTerm->description) }}</x-ui.odoo-form-ui>
                            </div>
                        </div>

                    </div>
                </x-ui.odoo-form-ui>
            </form>
        </div>
    </div>
@endsection
