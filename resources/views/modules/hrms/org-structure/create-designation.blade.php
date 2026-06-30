@extends('layouts.duralux')

@section('title', 'ORG STRUCTURE | SaaS ERP')
@section('page-title', 'Create Designation')
@section('breadcrumb', 'HRMS / Org Structure / Designations / Create')

@section('page-actions')
    <a href="{{ route('hrms.org.index', ['tab' => 'designations']) }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Org Structure
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="card border-top-0">
                <div>
                    <div class="card-body personal-info">
                        <form action="{{ route('hrms.designation.store') }}" method="POST">
                            @csrf
                            <div class="mb-4 d-flex align-items-center justify-content-between">
                                <h5 class="fw-bold mb-0 me-4">
                                    <span class="d-block mb-2">Designation Information:</span>
                                </h5>
                                <button type="submit" class="btn btn-lg btn-light-brand">Add New</button>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="name">Designation Name: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-award"></i></div>
                                        <input type="text" class="form-control" id="name" name="name" placeholder="Enter Designation Name" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="level">Level / Grade: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-trending-up"></i></div>
                                        <input type="text" class="form-control" id="level" name="level" placeholder="Enter Grade (e.g. L1, Senior)">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="department_id">Parent Department: </label>
                                    <div class="input-group">
                                        <div class="input-group-text"><i class="feather-users"></i></div>
                                        <select class="form-control" id="department_id" name="department_id" required>
                                            <option value="">Select Department</option>
                                            @foreach($departments as $department)
                                                <option value="{{ $department->id }}">{{ $department->name }}</option>
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
