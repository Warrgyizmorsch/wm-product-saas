@extends('layouts.duralux')

@section('title', 'ORG STRUCTURE | SaaS ERP')
@section('page-title', 'Create Branch')
@section('breadcrumb', 'HRMS / Org Structure / Branches / Create')

@section('page-actions')
    <a href="{{ route('hrms.org.index', ['tab' => 'branches']) }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Org Structure
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="card border-top-0">
                <div>
                    <div class="card-body personal-info">
                        <form action="{{ route('hrms.branch.store') }}" method="POST">
                            @csrf
                            <div class="mb-4 d-flex align-items-center justify-content-between">
                                <h5 class="fw-bold mb-0 me-4">
                                    <span class="d-block mb-2">Branch Information:</span>
                                </h5>
                                <button type="submit" class="btn btn-lg btn-light-brand">Add New</button>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="name">Branch Name: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-map-pin"></i></div>
                                        <input type="text" class="form-control" id="name" name="name" placeholder="Enter Branch Name" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="code">Branch Code: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-hash"></i></div>
                                        <input type="text" class="form-control" id="code" name="code" placeholder="Enter Branch Code" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="business_unit_id">Parent Business Unit: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-briefcase"></i></div>
                                        <select class="form-control" id="business_unit_id" name="business_unit_id" required>
                                            <option value="">Select Business Unit</option>
                                            @foreach($businessUnits as $buUnit)
                                                <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="manager_employee_id">Branch Manager: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-user"></i></div>
                                        <select class="form-control" id="manager_employee_id" name="manager_employee_id">
                                            <option value="">Select Manager</option>
                                            @foreach($employees as $employee)
                                                <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="phone">Phone: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-phone"></i></div>
                                        <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter Phone Number">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="email">Email: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-mail"></i></div>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Enter Email Address">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="country">Country: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-globe"></i></div>
                                        <input type="text" class="form-control" id="country" name="country" placeholder="Enter Country">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="state">State: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-map"></i></div>
                                        <input type="text" class="form-control" id="state" name="state" placeholder="Enter State">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="city">City: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-map"></i></div>
                                        <input type="text" class="form-control" id="city" name="city" placeholder="Enter City">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="postal_code">Postal Code: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-hash"></i></div>
                                        <input type="text" class="form-control" id="postal_code" name="postal_code" placeholder="Enter Postal Code">
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
                                    <label class="form-label fw-semibold" for="address">Address: </label>
                                    <textarea class="form-control" id="address" name="address" placeholder="Enter Address details..." rows="3"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
