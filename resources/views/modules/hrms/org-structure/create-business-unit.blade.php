@extends('layouts.duralux')

@section('title', 'ORG STRUCTURE | SaaS ERP')
@section('page-title', 'Create Business Unit')
@section('breadcrumb', 'HRMS / Org Structure / Business Units / Create')

@section('page-actions')
    <a href="{{ route('hrms.org.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Org Structure
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="card border-top-0">
                <div>
                    <div class="card-body personal-info">
                        <form action="{{ route('hrms.business-unit.store') }}" method="POST">
                            @csrf
                            <div class="mb-4 d-flex align-items-center justify-content-between">
                                <h5 class="fw-bold mb-0 me-4">
                                    <span class="d-block mb-2">Business Unit Information:</span>
                                </h5>
                                <button type="submit" class="btn btn-lg btn-light-brand">Add New</button>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="name">Business Unit Name: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-briefcase"></i></div>
                                        <input type="text" class="form-control" id="name" name="name" placeholder="Enter Business Unit Name" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="code">Business Unit Code: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-hash"></i></div>
                                        <input type="text" class="form-control" id="code" name="code" placeholder="Enter Business Unit Code" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="company_id">Parent Company: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-home"></i></div>
                                        <select class="form-control" id="company_id" name="company_id" required>
                                            <option value="">Select Company</option>
                                            @foreach($companies as $company)
                                                <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="head_employee_id">Unit Head: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-user"></i></div>
                                        <select class="form-control" id="head_employee_id" name="head_employee_id">
                                            <option value="">Select Unit Head</option>
                                            @foreach($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="status">Status: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-check-circle"></i></div>
                                        <select class="form-control" id="status" name="status" required>
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="description">Description: </label>
                                    <textarea class="form-control" id="description" name="description" placeholder="Enter description..." rows="4"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
