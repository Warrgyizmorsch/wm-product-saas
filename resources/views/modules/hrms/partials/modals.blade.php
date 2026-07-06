@php
    $companies = $companies ?? \App\Domains\HRMS\Models\Company::all();
    $businessUnits = $businessUnits ?? \App\Domains\HRMS\Models\BusinessUnit::all();
    $branches = $branches ?? \App\Domains\HRMS\Models\Branch::all();
    $departments = $departments ?? \App\Domains\HRMS\Models\Department::all();
    $employees = $employees ?? \App\Domains\HRMS\Models\Employee::all();
    $salaryComponents = $salaryComponents ?? \App\Domains\HRMS\Models\SalaryComponent::all();
@endphp

<!-- ============================================ -->
<!--            LEGAL ENTITIES MODALS             -->
<!-- ============================================ -->

<!-- View Company Modal -->
<div class="modal fade" id="viewCompanyModal" tabindex="-1" aria-labelledby="viewCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewCompanyModalLabel"><i class="feather-info me-2"></i>Legal Entity Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_logo_container" class="avatar-image avatar-xl rounded-3 border border-2 border-white shadow-sm overflow-hidden" style="width: 64px; height: 64px;">
                        <!-- Dynamically generated -->
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_company_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_legal_name"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">Registration Information</span>
                            <div class="mb-2"><strong class="fs-12 text-muted">GST:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_gst"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">PAN:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_pan"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">CIN:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_cin"></span></div>
                            <div class="mb-0"><strong class="fs-12 text-muted">Reg No:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_reg"></span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">Contact & Locale</span>
                            <div class="mb-2"><strong class="fs-12 text-muted">Email:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_email"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">Phone:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_phone"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">Currency:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_currency"></span></div>
                            <div class="mb-0"><strong class="fs-12 text-muted">Timezone:</strong> <span class="fs-13 text-dark fw-bold text-truncate d-inline-block" style="max-width: 200px;" id="modal_view_timezone"></span></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">Location Details</span>
                            <div class="row g-2">
                                <div class="col-sm-3"><strong class="fs-12 text-muted">Country:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_country"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">State:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_state"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">City:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_city"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">Zip Code:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_zip"></span></div>
                                <div class="col-12 mt-2 pt-2 border-top"><strong class="fs-12 text-muted">Full Address:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_address"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Company Modal -->
<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-labelledby="addCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addCompanyModalLabel"><i class="feather-plus me-2 text-primary"></i>Add Legal Entity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.company.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Logo: </label>
                        <div class="d-flex gap-4 align-items-center">
                            <div class="wd-100 ht-100 position-relative overflow-hidden border border-gray-200 rounded">
                                <img src="{{ asset('assets/images/avatar/1.png') }}" class="add-upload-pic img-fluid rounded h-100 w-100" id="add_logo_preview" alt="">
                                <div class="position-absolute start-50 top-50 end-0 translate-middle h-100 w-100 hstack align-items-center justify-content-center c-pointer add-upload-button upload-button" style="background: rgba(0,0,0,0.3); color: white;">
                                    <i class="feather feather-camera" aria-hidden="true"></i>
                                </div>
                                <input class="add-file-upload" type="file" name="logo" accept="image/*" style="display: none;">
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <div class="fs-11 text-gray-500"># Avatar size 150x150</div>
                                <div class="fs-11 text-gray-500"># Max upload size 2mb</div>
                                <div class="fs-11 text-gray-500"># Allowed: png, jpg, jpeg</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.input label="Company Name" name="company_name" :required="true" placeholder="Enter Company Name" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Legal Name" name="legal_name" :required="true" placeholder="Enter Legal Name" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="GST Number" name="gst_number" placeholder="Enter GST Number" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="PAN Number" name="pan_number" placeholder="Enter PAN Number" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="CIN Number" name="cin_number" placeholder="Enter CIN Number" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Registration Number" name="registration_number" placeholder="Enter Registration Number" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Email" name="email" type="email" placeholder="Enter Email Address" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Phone" name="phone" placeholder="Enter Phone Number" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Website" name="website" placeholder="Enter Website URL" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Currency" name="currency" placeholder="e.g. INR, USD" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Timezone" name="time_zone" placeholder="e.g. Asia/Kolkata" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Status" name="status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="Country" name="country" placeholder="Country" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="State" name="state" placeholder="State" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="City" name="city" placeholder="City" />
                        </div>
                        <div class="col-md-12">
                            <x-ui.input label="Postal Code" name="postal_code" placeholder="Postal Code" />
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Address" name="address" rows="3" placeholder="Address Details" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Entity</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-labelledby="editCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editCompanyModalLabel"><i class="feather-edit me-2 text-primary"></i>Edit Legal Entity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="company_edit_form" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Logo: </label>
                        <div class="d-flex gap-4 align-items-center">
                            <div class="wd-100 ht-100 position-relative overflow-hidden border border-gray-200 rounded">
                                <img src="" class="edit-upload-pic img-fluid rounded h-100 w-100" id="edit_logo_preview" alt="">
                                <div class="position-absolute start-50 top-50 end-0 translate-middle h-100 w-100 hstack align-items-center justify-content-center c-pointer edit-upload-button upload-button" style="background: rgba(0,0,0,0.3); color: white;">
                                    <i class="feather feather-camera" aria-hidden="true"></i>
                                </div>
                                <input class="edit-file-upload" type="file" name="logo" accept="image/*" style="display: none;">
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <div class="fs-11 text-gray-500"># Upload new logo to replace</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.input label="Company Name" name="company_name" id="edit_company_name" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Legal Name" name="legal_name" id="edit_legal_name" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="GST Number" name="gst_number" id="edit_gst_number" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="PAN Number" name="pan_number" id="edit_pan_number" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="CIN Number" name="cin_number" id="edit_cin_number" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Registration Number" name="registration_number" id="edit_registration_number" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Email" name="email" id="edit_email" type="email" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Phone" name="phone" id="edit_phone" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Website" name="website" id="edit_website" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Currency" name="currency" id="edit_currency" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Timezone" name="time_zone" id="edit_timezone" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Status" name="status" id="edit_status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="Country" name="country" id="edit_country" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="State" name="state" id="edit_state" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="City" name="city" id="edit_city" />
                        </div>
                        <div class="col-md-12">
                            <x-ui.input label="Postal Code" name="postal_code" id="edit_postal_code" />
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Address" name="address" id="edit_address" rows="3" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Entity</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================ -->
<!--            BUSINESS UNITS MODALS             -->
<!-- ============================================ -->

<!-- View BU Modal -->
<div class="modal fade" id="viewBuModal" tabindex="-1" aria-labelledby="viewBuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewBuModalLabel"><i class="feather-info me-2"></i>Business Unit Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_bu_avatar" class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center fw-bold fs-16" style="width: 54px; height: 54px;">
                        BU
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_bu_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_bu_company"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Business Unit Code</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_bu_code"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Unit Head</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_bu_head"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Status</label>
                        <div id="modal_view_bu_status"></div>
                    </div>
                    <div class="col-12 mt-2">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Description</label>
                        <p class="fs-13 fw-semibold text-dark mb-0" id="modal_view_bu_desc"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add BU Modal -->
<div class="modal fade" id="addBuModal" tabindex="-1" aria-labelledby="addBuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addBuModalLabel"><i class="feather-plus me-2 text-primary"></i>Add Business Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.business-unit.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Business Unit Name" name="name" :required="true" placeholder="Enter Unit Name" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Business Unit Code" name="code" :required="true" placeholder="Enter Code" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Company" name="company_id" :required="true" data-select2-selector="default">
                                <option value="">Select Parent Company</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Description" name="description" rows="3" placeholder="Enter description details" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Business Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit BU Modal -->
<div class="modal fade" id="editBuModal" tabindex="-1" aria-labelledby="editBuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editBuModalLabel"><i class="feather-edit me-2 text-primary"></i>Edit Business Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bu_edit_form" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Business Unit Name" name="name" id="edit_bu_name" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Business Unit Code" name="code" id="edit_bu_code" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Company" name="company_id" id="edit_bu_company_id" :required="true" data-select2-selector="default">
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" id="edit_bu_status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Description" name="description" id="edit_bu_description" rows="3" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Business Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================ -->
<!--               BRANCHES MODALS                -->
<!-- ============================================ -->

<!-- View Branch Modal -->
<div class="modal fade" id="viewBranchModal" tabindex="-1" aria-labelledby="viewBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewBranchModalLabel"><i class="feather-info me-2"></i>Branch Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_branch_avatar" class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center fw-bold fs-16" style="width: 54px; height: 54px;">
                        BR
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_branch_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_branch_bu"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">Overview</span>
                            <div class="mb-2"><strong class="fs-12 text-muted">Branch Code:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_code"></span></div>
                            <div class="mb-2"><strong class="fs-12 text-muted">Manager:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_manager"></span></div>
                            <div class="mb-0"><strong class="fs-12 text-muted">Status:</strong> <span id="modal_view_branch_status"></span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">Contact info</span>
                            <div class="mb-2"><strong class="fs-12 text-muted">Phone:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_phone"></span></div>
                            <div class="mb-0"><strong class="fs-12 text-muted">Email:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_email"></span></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 border rounded-3 bg-light-soft" style="background-color: #f8fafc;">
                            <span class="fs-11 fw-semibold text-muted text-uppercase d-block mb-2 text-primary">Location Details</span>
                            <div class="row g-2">
                                <div class="col-sm-3"><strong class="fs-12 text-muted">Country:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_country"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">State:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_state"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">City:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_city"></span></div>
                                <div class="col-sm-3"><strong class="fs-12 text-muted">Zip Code:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_zip"></span></div>
                                <div class="col-12 mt-2 pt-2 border-top"><strong class="fs-12 text-muted">Full Address:</strong> <span class="fs-13 text-dark fw-bold" id="modal_view_branch_address"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addBranchModalLabel"><i class="feather-plus me-2 text-primary"></i>Add Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.branch.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.input label="Branch Name" name="name" :required="true" placeholder="Enter Branch Name" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Branch Code" name="code" :required="true" placeholder="Enter Code" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Parent Company / Legal Entity" name="company_id" data-select2-selector="default">
                                <option value="">Select Company (Required if no Business Unit)</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Parent Business Unit" name="business_unit_id" data-select2-selector="default">
                                <option value="">Select Business Unit (Optional)</option>
                                @foreach($businessUnits as $buUnit)
                                    <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Branch Manager" name="manager_employee_id" data-select2-selector="default">
                                <option value="">Select Manager (Optional)</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Phone" name="phone" placeholder="Enter Phone" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Email" name="email" type="email" placeholder="Enter Email" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="Country" name="country" placeholder="Country" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="State" name="state" placeholder="State" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="City" name="city" placeholder="City" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Postal Code" name="postal_code" placeholder="Postal Code" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Status" name="status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Address" name="address" rows="3" placeholder="Address Details" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editBranchModalLabel"><i class="feather-edit me-2 text-primary"></i>Edit Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="branch_edit_form" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.input label="Branch Name" name="name" id="edit_branch_name" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Branch Code" name="code" id="edit_branch_code" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Parent Company / Legal Entity" name="company_id" id="edit_branch_company_id" data-select2-selector="default">
                                <option value="">Select Company (Required if no Business Unit)</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Parent Business Unit" name="business_unit_id" id="edit_branch_bu_id" data-select2-selector="default">
                                <option value="">Select Business Unit (Optional)</option>
                                @foreach($businessUnits as $buUnit)
                                    <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Branch Manager" name="manager_employee_id" id="edit_branch_manager_id" data-select2-selector="default">
                                <option value="">Select Manager (Optional)</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Phone" name="phone" id="edit_branch_phone" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Email" name="email" id="edit_branch_email" type="email" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="Country" name="country" id="edit_branch_country" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="State" name="state" id="edit_branch_state" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.input label="City" name="city" id="edit_branch_city" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.input label="Postal Code" name="postal_code" id="edit_branch_postal_code" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.select label="Status" name="status" id="edit_branch_status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Address" name="address" id="edit_branch_address" rows="3" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================ -->
<!--              DEPARTMENTS MODALS              -->
<!-- ============================================ -->

<!-- View Dept Modal -->
<div class="modal fade" id="viewDeptModal" tabindex="-1" aria-labelledby="viewDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewDeptModalLabel"><i class="feather-info me-2"></i>Department Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_dept_avatar" class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center fw-bold fs-16" style="width: 54px; height: 54px;">
                        DP
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_dept_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_dept_branch"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Department Code</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_dept_code"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Department Head</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_dept_head"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Status</label>
                        <div id="modal_view_dept_status"></div>
                    </div>
                    <div class="col-12 mt-2">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Description</label>
                        <p class="fs-13 fw-semibold text-dark mb-0" id="modal_view_dept_desc"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Dept Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-labelledby="addDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addDeptModalLabel"><i class="feather-plus me-2 text-primary"></i>Add Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.department.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Department Name" name="name" :required="true" placeholder="Enter Department Name" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Department Code" name="code" :required="true" placeholder="Enter Code" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Company / Legal Entity" name="company_id" data-select2-selector="default">
                                <option value="">Select Company (Required if no Branch/Business Unit)</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Business Unit" name="business_unit_id" data-select2-selector="default">
                                <option value="">Select Business Unit (Optional)</option>
                                @foreach($businessUnits as $buUnit)
                                    <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Branch" name="branch_id" data-select2-selector="default">
                                <option value="">Select Branch (Optional)</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Department Head" name="head_employee_id" data-select2-selector="default">
                                <option value="">Select Department Head (Optional)</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Description" name="description" rows="3" placeholder="Enter description details" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Dept Modal -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-labelledby="editDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editDeptModalLabel"><i class="feather-edit me-2 text-primary"></i>Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="dept_edit_form" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Department Name" name="name" id="edit_dept_name" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Department Code" name="code" id="edit_dept_code" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Company / Legal Entity" name="company_id" id="edit_dept_company_id" data-select2-selector="default">
                                <option value="">Select Company (Required if no Branch/Business Unit)</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Business Unit" name="business_unit_id" id="edit_dept_bu_id" data-select2-selector="default">
                                <option value="">Select Business Unit (Optional)</option>
                                @foreach($businessUnits as $buUnit)
                                    <option value="{{ $buUnit->id }}">{{ $buUnit->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Branch" name="branch_id" id="edit_dept_branch_id" data-select2-selector="default">
                                <option value="">Select Branch (Optional)</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Department Head" name="head_employee_id" id="edit_dept_head_id" data-select2-selector="default">
                                <option value="">Select Department Head (Optional)</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" id="edit_dept_status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Description" name="description" id="edit_dept_description" rows="3" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ============================================ -->
<!--              DESIGNATIONS MODALS             -->
<!-- ============================================ -->

<!-- View Desig Modal -->
<div class="modal fade" id="viewDesigModal" tabindex="-1" aria-labelledby="viewDesigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-soft-primary text-primary py-3">
                <h5 class="modal-title fw-bold" id="viewDesigModalLabel"><i class="feather-info me-2"></i>Designation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                    <div id="modal_view_desig_avatar" class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center fw-bold fs-16" style="width: 54px; height: 54px;">
                        DS
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="modal_view_desig_name"></h4>
                        <span class="fs-12 text-muted" id="modal_view_desig_dept"></span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Grade Level</label>
                        <span class="fs-13 fw-bold text-dark" id="modal_view_desig_level"></span>
                    </div>
                    <div class="col-sm-6">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Status</label>
                        <div id="modal_view_desig_status"></div>
                    </div>
                    <div class="col-12 mt-2">
                        <label class="fs-11 fw-semibold text-muted text-uppercase d-block mb-1">Description</label>
                        <p class="fs-13 fw-semibold text-dark mb-0" id="modal_view_desig_desc"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Desig Modal -->
<div class="modal fade" id="addDesigModal" tabindex="-1" aria-labelledby="addDesigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addDesigModalLabel"><i class="feather-plus me-2 text-primary"></i>Add Designation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.designation.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Designation Name" name="name" :required="true" placeholder="Enter Designation Name" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Grade Level" name="level" placeholder="Enter Grade Level (e.g. L1, L2)" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Department" name="department_id" :required="true" data-select2-selector="default">
                                <option value="">Select Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Description" name="description" rows="3" placeholder="Enter description details" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Designation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Desig Modal -->
<div class="modal fade" id="editDesigModal" tabindex="-1" aria-labelledby="editDesigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editDesigModalLabel"><i class="feather-edit me-2 text-primary"></i>Edit Designation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="desig_edit_form" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Designation Name" name="name" id="edit_desig_name" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Grade Level" name="level" id="edit_desig_level" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Parent Department" name="department_id" id="edit_desig_dept_id" :required="true" data-select2-selector="default">
                                <option value="">Select Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" id="edit_desig_status" data-select2-selector="default">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Description" name="description" id="edit_desig_description" rows="3" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Designation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Salary Component Modal -->
<div class="modal fade" id="addSalaryComponentModal" tabindex="-1" aria-labelledby="addSalaryComponentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addSalaryComponentModalLabel"><i class="feather-plus me-2 text-primary"></i>Add Salary Component</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ request()->routeIs('hrms.salary-structure.index') ? route('hrms.salary-structure.store') : route('hrms.salary-component.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Component Name" name="name" :required="true" placeholder="Enter Component Name (e.g. Basic Salary)" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Code" name="code" :required="true" placeholder="Enter Component Code (e.g. BASIC)" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Type" name="type" :required="true" data-select2-selector="default">
                                <option value="earning">Earning</option>
                                <option value="deduction">Deduction</option>
                            </x-ui.select>
                        </div>
                        <input type="hidden" name="calculation_type" value="fixed">
                        <div class="col-12">
                            <x-ui.select label="Parent Company / Legal Entity" name="company_id" data-select2-selector="default">
                                <option value="">All Entities (Global)</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" data-select2-selector="status">
                                <option value="1" data-bg="bg-success" selected>Active</option>
                                <option value="0" data-bg="bg-danger">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Description" name="description" rows="3" placeholder="Enter description details" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Component</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Salary Component Modal -->
<div class="modal fade" id="editSalaryComponentModal" tabindex="-1" aria-labelledby="editSalaryComponentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editSalaryComponentModalLabel"><i class="feather-edit me-2 text-primary"></i>Edit Salary Component</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="salary_component_edit_form" method="POST" data-update-route="{{ request()->routeIs('hrms.salary-structure.index') ? route('hrms.salary-structure.update', ['salaryComponent' => '__ID__']) : route('hrms.salary-component.update', ['salaryComponent' => '__ID__']) }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.input label="Component Name" name="name" id="edit_sc_name" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.input label="Code" name="code" id="edit_sc_code" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Type" name="type" id="edit_sc_type" :required="true" data-select2-selector="default">
                                <option value="earning">Earning</option>
                                <option value="deduction">Deduction</option>
                            </x-ui.select>
                        </div>
                        <input type="hidden" name="calculation_type" id="edit_sc_calculation_type" value="fixed">
                        <div class="col-12">
                            <x-ui.select label="Parent Company / Legal Entity" name="company_id" id="edit_sc_company_id" data-select2-selector="default">
                                <option value="">All Entities (Global)</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.select label="Status" name="status" id="edit_sc_status" data-select2-selector="status">
                                <option value="1" data-bg="bg-success">Active</option>
                                <option value="0" data-bg="bg-danger">Inactive</option>
                            </x-ui.select>
                        </div>
                        <div class="col-12">
                            <x-ui.textarea label="Description" name="description" id="edit_sc_description" rows="3" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Component</button>
                </div>
            </form>
        </div>
    </div>
</div>
