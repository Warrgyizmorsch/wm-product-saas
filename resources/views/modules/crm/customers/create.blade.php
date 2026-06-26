@extends('layouts.duralux')

@section('title', 'Create Customer | SaaS ERP')
@section('page-title', 'Create Customer')
@section('breadcrumb', 'CRM / Customers / Create')

@section('page-actions')
    <a href="{{ route('crm.customers.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Customers
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-xxl-7 col-xl-8 mx-auto">
            <form action="{{ route('crm.customers.store') }}" method="POST">
                @csrf

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="feather-user-plus me-2 text-primary"></i>Customer Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Email</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Phone</label>
                                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold text-dark">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
                        <a href="{{ route('crm.customers.index') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-check-circle me-2"></i>Save Customer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
