@extends('layouts.duralux')

@section('title', 'ORG STRUCTURE | SaaS ERP')
@section('page-title', 'Create Department')
@section('breadcrumb', 'HRMS / Org Structure / Departments / Create')

@section('page-actions')
    <a href="{{ route('hrms.org.index', ['tab' => 'departments']) }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Org Structure
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="card border-top-0">
                <div>
                    <div class="card-body personal-info">
                        <form action="{{ route('hrms.department.store') }}" method="POST">
                            @csrf
                            <div class="mb-4 d-flex align-items-center justify-content-between">
                                <h5 class="fw-bold mb-0 me-4">
                                    <span class="d-block mb-2">Department Information:</span>
                                </h5>
                                <button type="submit" class="btn btn-lg btn-light-brand">Add New</button>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="name">Department Name: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-users"></i></div>
                                        <input type="text" class="form-control" id="name" name="name" placeholder="Enter Department Name" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="code">Department Code: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-hash"></i></div>
                                        <input type="text" class="form-control" id="code" name="code" placeholder="Enter Department Code" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="branch_id">Parent Branch: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-map-pin"></i></div>
                                        <select class="form-control" id="branch_id" name="branch_id" required>
                                            <option value="">Select Branch</option>
                                            @foreach($branches as $branch)
                                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="head_employee_id">Department Head: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-user"></i></div>
                                        <select class="form-control" id="head_employee_id" name="head_employee_id">
                                            <option value="">Select Department Head</option>
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
