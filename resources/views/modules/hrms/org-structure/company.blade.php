@extends('layouts.duralux')

@section('title', 'ORG STRUCTURE | SaaS ERP')
@section('page-title', 'Legal Entities')
@section('breadcrumb', 'HRMS / Org Structure / Legal Entities')

@section('page-actions')
    <a href="{{ route('crm.customers.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Customers
    </a>
@endsection

@section('content')
    <style>
        .company-link {
            transition: all 0.2s ease-in-out;
        }
        .company-link.active-entity {
            background-color: rgba(40, 167, 69, 0.08) !important;
        }
        .company-link.active-entity td:first-child {
            border-left: 4px solid var(--bs-success, #28a745) !important;
        }
    </style>
    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full">
                <div class="card-body task-header d-lg-flex align-items-center justify-content-between">
                    <div class="mb-4 mb-lg-0">
                        <h4 class="mb-3 fw-bold text-truncate-1-line">LEGAL ENTITIES</h4>
                        <span class="badge bg-soft-success text-success">Registered Companies</span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="/org/company/create" class="btn btn-success" data-bs-toggle="tooltip" title="Timesheets">
                            <i class="feather-plus"></i>
                            <span>Add Legal Entity</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-8">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Entity Details</h5>
                    <a href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Update Description">
                        <i class="feather-edit"></i>
                    </a>
                </div>
                <div class="card-body">
                    @php
                        $company = $companies->first();
                    @endphp
                    @if($company)
                    <div class="row g-4">

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Company Name</label>
                            <p class="mb-3" id="company_name">{{ $company->company_name }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Legal Name</label>
                            <p class="mb-3" id="legal_name">{{ $company->legal_name }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">GST Number</label>
                            <p class="mb-3" id="gst_number">{{ $company->gst_number }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">PAN Number</label>
                            <p class="mb-3" id="pan_number">{{ $company->pan_number }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">CIN Number</label>
                            <p class="mb-3" id="cin_number">{{ $company->cin_number }}</p>
                        </div>

                        <div class="col-md-4">
                                <label class="fs-12 fw-semibold text-muted mb-1">Registration Number</label>
                            <p class="mb-3" id="registration_number">{{ $company->registration_number }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Email</label>
                            <p class="mb-3" id="email">{{ $company->email }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Phone</label>
                            <p class="mb-3" id="phone">{{ $company->phone }}</p>
                        </div>

                         <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Website</label>
                            <p class="mb-3" id="website">{{ $company->website }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Currency</label>
                            <p class="mb-3" id="currency">{{ $company->currency }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Timezone</label>
                            <p class="mb-3" id="timezone">{{ $company->timezone }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Status</label>
                            <p class="mb-3" id="status">
                                @if($company->status)
                                    <span class="badge bg-soft-success text-success">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Country</label>
                            <p class="mb-3" id="country">{{ $company->country }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">State</label>
                            <p class="mb-3" id="state">{{ $company->state }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">City</label>
                            <p class="mb-3" id="city">{{ $company->city }}</p>
                        </div>

                        <div class="col-md-4">
                            <label class="fs-12 fw-semibold text-muted mb-1">Postal Code</label>
                            <p class="mb-3" id="postal_code">{{ $company->postal_code }}</p>
                        </div>

                        <div class="col-12">
                            <label class="fs-12 fw-semibold text-muted mb-1">Address</label>
                            <p class="mb-0" id="address">{{ $company->address }}</p>
                        </div>

                    </div>
                    @else
                    <div class="text-center py-5">
                        <p class="text-muted mb-0">No Legal Entities found. Click "Add Legal Entity" to create one.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xxl-4">
            <div class="card stretch stretch-full">
                <div class="card-header">
                                <h5 class="card-title">Legal Entities</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
                                            <div data-bs-toggle="tooltip" title="Options">
                                                <i class="feather-more-vertical"></i>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-at-sign"></i>New</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-calendar"></i>Event</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-bell"></i>Snoozed</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-trash-2"></i>Deleted</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-settings"></i>Settings</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-life-buoy"></i>Tips & Tricks</a>
                                        </div>
                                    </div>
                                </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <tbody>
                            @foreach($companies as $company)
                            <tr class="company-link @if($loop->first) active-entity @endif"
                                data-company='@json($company)'
                                style="cursor: pointer;">

                                <td>
                                    <div class="hstack gap-3">
                                        <div class="avatar-image avatar-lg rounded">
                                            <img class="img-fluid"
                                                src="{{ asset('assets/images/gallery/1.png') }}"
                                                alt="">
                                        </div>

                                        <div>
                                            <span class="d-block fw-semibold">
                                                {{ $company->company_name }}
                                            </span>

                                            <span class="fs-12 text-muted">
                                                {{ $company->legal_name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td width="220">
                                    <small class="text-muted d-block">GST Number</small>
                                    <span>{{ $company->gst_number ?? 'N/A' }}</span>
                                </td>

                                <td width="120" class="text-end">
                                    @if($company->status)
                                        <span class="badge bg-soft-success text-success">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger">
                                            Inactive
                                        </span>
                                    @endif
                                </td>

                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center">More Products</a>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.company-link').forEach(link => {

            link.addEventListener('click', function(e){

                e.preventDefault();

                // Toggle active highlights on list rows
                document.querySelectorAll('.company-link').forEach(row => {
                    row.classList.remove('active-entity');
                });
                this.classList.add('active-entity');

                let company = JSON.parse(this.dataset.company);

                document.getElementById('company_name').innerText = company.company_name;
                document.getElementById('legal_name').innerText = company.legal_name;
                document.getElementById('gst_number').innerText = company.gst_number;
                document.getElementById('pan_number').innerText = company.pan_number;
                document.getElementById('cin_number').innerText = company.cin_number;
                document.getElementById('registration_number').innerText = company.registration_number;
                document.getElementById('email').innerText = company.email;
                document.getElementById('phone').innerText = company.phone;
                document.getElementById('website').innerText = company.website;
                document.getElementById('email').innerText = company.email;
                document.getElementById('phone').innerText = company.phone;
                document.getElementById('website').innerText = company.website;
                document.getElementById('logo').innerText = company.logo;
                document.getElementById('address').innerText = company.address;
                document.getElementById('city').innerText = company.city;
                document.getElementById('state').innerText = company.state;
                document.getElementById('country').innerText = company.country;
                document.getElementById('postal_code').innerText = company.postal_code;
                document.getElementById('currency').innerText = company.currency;
                document.getElementById('timezone').innerText = company.timezone;
                document.getElementById('status').innerText = company.status;
            });

        });
    </script>
@endsection