@extends('layouts.duralux')

@section('title', $account->name . ' - Account 360° | SaaS ERP')
@section('page-title', 'Account 360° View')
@section('breadcrumb', 'Account Details')

@push('styles')
<style>
    .zoho-tab-link {
        font-weight: 600;
        font-size: 13px;
        color: #64748b;
        border-bottom: 2px solid transparent !important;
        padding: 10px 18px !important;
    }
    .zoho-tab-link.active {
        color: #1e40af !important;
        border-bottom: 2.5px solid #1e40af !important;
        background: transparent !important;
    }
    .zoho-field-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 2px;
    }
    .zoho-field-value {
        font-size: 13px;
        color: #0f172a;
    }
</style>
@endpush

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('crm.accounts.index') }}" class="btn btn-light border">
            <i class="feather-arrow-left me-1"></i>Back to Accounts
        </a>
        <a href="{{ route('crm.deals.create', ['account_id' => $account->id]) }}" class="btn btn-soft-warning fw-bold">
            <i class="feather-plus me-1"></i>Create New Deal
        </a>
        <a href="{{ route('crm.accounts.edit', $account) }}" class="btn btn-primary" style="background-color: #1e40af; border-color: #1e40af;">
            <i class="feather-edit me-1"></i>Edit Account
        </a>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show fs-13 py-2.5 mb-3" role="alert">
                <i class="feather-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem;"></button>
            </div>
        @endif

        <!-- Company Summary Hero Banner -->
        <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-xl bg-soft-primary text-primary fs-3 fw-bold shadow-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="feather-briefcase"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h3 class="fw-bold text-dark mb-0 fs-20">{{ $account->name }}</h3>
                            <span class="badge bg-light text-primary border font-monospace px-2.5 py-1 fs-12">{{ $account->account_number }}</span>
                            @if($account->status === 'active')
                                <span class="badge bg-soft-success text-success border border-success-subtle rounded-pill px-2.5 py-1 fs-11 fw-bold">Active Account</span>
                            @else
                                <span class="badge bg-soft-danger text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fs-11 fw-bold">Inactive Account</span>
                            @endif
                        </div>
                        <div class="d-flex gap-3 align-items-center mt-2 text-muted fs-13 flex-wrap">
                            @if($account->gstin)
                                <span><i class="feather-file-text me-1 text-primary"></i>GSTIN: <strong class="text-dark font-monospace">{{ $account->gstin }}</strong></span>
                                <span>•</span>
                            @endif
                            <span><i class="feather-phone me-1 text-primary"></i>Phone: <strong class="text-dark">{{ $account->phone ?: 'N/A' }}</strong></span>
                            <span>•</span>
                            <span><i class="feather-mail me-1 text-primary"></i>Email: <strong class="text-dark">{{ $account->email ?: 'N/A' }}</strong></span>
                            @if($account->city || $account->state)
                                <span>•</span>
                                <span><i class="feather-map-pin me-1 text-primary"></i>Location: <strong class="text-dark">{{ implode(', ', array_filter([$account->city, $account->state])) }}</strong></span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Right Quick Metric Cards -->
                <div class="d-flex gap-3 text-end mt-3 mt-md-0 flex-wrap">
                    <div class="bg-light p-3 rounded-3 border text-center px-4">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-dollar-sign me-1 text-success"></i>Lifetime Revenue</span>
                        <h4 class="fw-bold text-success mb-0">₹{{ number_format($lifetimeRevenue, 2) }}</h4>
                    </div>
                    <div class="bg-light p-3 rounded-3 border text-center px-4">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-layers me-1 text-info"></i>Open Deals</span>
                        <h4 class="fw-bold text-info mb-0">{{ $openDealsCount }}</h4>
                    </div>
                    <div class="bg-light p-3 rounded-3 border text-center px-4">
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1"><i class="feather-pie-chart me-1 text-primary"></i>Avg Order Value</span>
                        <h4 class="fw-bold text-primary mb-0">₹{{ number_format($aov, 2) }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Container Card -->
        <div class="card border-0 shadow-sm bg-white rounded-3">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs border-0 px-3" id="accountTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link active" id="overview-tab" data-bs-toggle="tab" href="#overview-pane" role="tab">
                            <i class="feather-info me-1.5"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link" id="deals-tab" data-bs-toggle="tab" href="#deals-pane" role="tab">
                            <i class="feather-git-branch me-1.5"></i>Deals & Projects ({{ $account->deals->count() }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link" id="contacts-tab" data-bs-toggle="tab" href="#contacts-pane" role="tab">
                            <i class="feather-users me-1.5"></i>Personnel Contacts ({{ $account->contacts->count() }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link" id="quotations-tab" data-bs-toggle="tab" href="#quotations-pane" role="tab">
                            <i class="feather-file-text me-1.5"></i>Quotations ({{ $account->quotations->count() }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link zoho-tab-link" id="orders-tab" data-bs-toggle="tab" href="#orders-pane" role="tab">
                            <i class="feather-shopping-cart me-1.5"></i>Sales Orders ({{ $salesOrders->count() }})
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="accountTabsContent">
                    <!-- Tab 0: Overview -->
                    <div class="tab-pane fade show active" id="overview-pane" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-lg-6 border-end">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-briefcase me-1.5"></i>Company Account Details</h6>
                                <div class="row g-3 fs-13">
                                    <div class="col-6">
                                        <div class="zoho-field-label">Account Name</div>
                                        <div class="zoho-field-value fw-bold text-dark">{{ $account->name }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="zoho-field-label">Account Number</div>
                                        <div class="zoho-field-value font-monospace text-primary fw-bold">{{ $account->account_number }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="zoho-field-label">GSTIN / Tax ID</div>
                                        <div class="zoho-field-value font-monospace fw-bold text-dark">{{ $account->gstin ?: 'Not Available' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="zoho-field-label">Industry Type</div>
                                        <div class="zoho-field-value text-dark">{{ $account->industry_type ?: 'General Business' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="zoho-field-label">Website</div>
                                        <div class="zoho-field-value text-primary">
                                            @if($account->website)
                                                <a href="{{ Str::startsWith($account->website, 'http') ? $account->website : 'https://' . $account->website }}" target="_blank" class="text-primary text-decoration-none fw-semibold">
                                                    <i class="feather-external-link me-1"></i>{{ $account->website }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="zoho-field-label">Credit Limit</div>
                                        <div class="zoho-field-value text-dark fw-bold">₹{{ number_format($account->credit_limit ?: 0, 2) }}</div>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-top">
                                    <h6 class="fw-bold text-primary mb-2"><i class="feather-map-pin me-1.5"></i>Billing & Corporate Address</h6>
                                    <div class="p-3 bg-light rounded-3 text-dark fs-13">
                                        <div><strong>Street:</strong> {{ $account->street ?: '—' }}</div>
                                        <div><strong>City/State:</strong> {{ implode(', ', array_filter([$account->city, $account->state])) ?: '—' }}</div>
                                        <div><strong>Country / Zip:</strong> {{ implode(' - ', array_filter([$account->country, $account->zip_code])) ?: '—' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 ps-lg-4">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-bar-chart-2 me-1.5"></i>Financial Overview & Stats</h6>
                                <div class="row g-3 fs-13">
                                    <div class="col-6">
                                        <div class="zoho-field-label">Total Lifetime Revenue</div>
                                        <div class="zoho-field-value fw-bold text-success fs-16">₹{{ number_format($lifetimeRevenue, 2) }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="zoho-field-label">Active Deals Count</div>
                                        <div class="zoho-field-value fw-bold text-info fs-16">{{ $openDealsCount }} Deals</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="zoho-field-label">Total Quotations</div>
                                        <div class="zoho-field-value text-dark fw-bold">{{ $account->quotations->count() }} Quotes</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="zoho-field-label">Sales Orders</div>
                                        <div class="zoho-field-value text-dark fw-bold">{{ $salesOrders->count() }} Orders</div>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-top">
                                    <h6 class="fw-bold text-primary mb-2"><i class="feather-user me-1.5"></i>Primary Contact & Ownership</h6>
                                    <div class="p-3 bg-light rounded-3 border fs-13">
                                        @php
                                            $primaryContact = $account->contacts->where('is_primary', true)->first() ?: $account->contacts->first();
                                        @endphp
                                        @if($primaryContact)
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold text-dark fs-14"><i class="feather-user-check me-1 text-success"></i>{{ $primaryContact->name }}</span>
                                                <span class="badge bg-soft-primary text-primary">Primary Contact</span>
                                            </div>
                                            <div><strong>Role:</strong> {{ $primaryContact->role ?: 'Decision Maker' }}</div>
                                            <div><strong>Email:</strong> {{ $primaryContact->email ?: 'N/A' }}</div>
                                            <div><strong>Phone:</strong> {{ $primaryContact->mobile ?: ($primaryContact->phone ?: 'N/A') }}</div>
                                        @else
                                            <div class="text-muted">No primary contact added yet. Click on <strong>Personnel Contacts</strong> tab to add.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 1: Deals & Projects -->
                    <div class="tab-pane fade" id="deals-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-dark"><i class="feather-git-branch me-2 text-primary"></i>Linked Projects & Sales Opportunities</h6>
                            <a href="{{ route('crm.deals.create', ['account_id' => $account->id]) }}" class="btn btn-sm btn-soft-primary fw-bold">
                                <i class="feather-plus me-1"></i>New Deal
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table odoo-table align-middle">
                                <thead class="table-light text-muted fs-12 text-uppercase">
                                    <tr>
                                        <th>Deal #</th>
                                        <th>Project Title</th>
                                        <th>Stage</th>
                                        <th class="text-end">Value (₹)</th>
                                        <th>Closing Date</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13 text-dark">
                                    @forelse($account->deals as $deal)
                                        @php
                                            $stageColors = [
                                                'Qualification'  => 'info',
                                                'Needs Analysis' => 'primary',
                                                'Proposal'       => 'warning',
                                                'Negotiation'    => 'purple',
                                                'Won'            => 'success',
                                                'Closed Won'     => 'success',
                                                'Lost'           => 'danger',
                                                'Closed Lost'    => 'danger',
                                            ];
                                            $badgeColor = $stageColors[$deal->stage] ?? 'secondary';
                                        @endphp
                                        <tr>
                                            <td class="font-monospace fw-bold text-primary">{{ $deal->deal_number }}</td>
                                            <td class="fw-bold text-dark">{{ $deal->title }}</td>
                                            <td>
                                                <span class="badge bg-soft-{{ $badgeColor }} text-{{ $badgeColor }} border border-{{ $badgeColor }}-subtle px-2 py-0.5 fw-bold">
                                                    {{ $deal->stage }}
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold text-success">
                                                ₹{{ number_format($deal->actual_value ?: $deal->estimated_value, 2) }}
                                            </td>
                                            <td>{{ $deal->closing_date ? \Illuminate\Support\Carbon::parse($deal->closing_date)->format('d M Y') : '—' }}</td>
                                            <td class="text-end pe-3">
                                                <a href="{{ route('crm.deals.show', $deal) }}" class="btn btn-sm btn-soft-primary">
                                                    <i class="feather-eye me-1"></i>View Deal
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No deals recorded for this account yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 2: Personnel Contacts -->
                    <div class="tab-pane fade" id="contacts-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-dark"><i class="feather-users me-2 text-primary"></i>Company Personnel & Key Contacts</h6>
                            <button type="button" class="btn btn-sm btn-soft-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addContactModal">
                                <i class="feather-plus me-1"></i>Add Contact
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table odoo-table align-middle">
                                <thead class="table-light text-muted fs-12 text-uppercase">
                                    <tr>
                                        <th>Name</th>
                                        <th>Designation</th>
                                        <th>Buying Center Role</th>
                                        <th>Email</th>
                                        <th>Mobile</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13 text-dark">
                                    @forelse($account->contacts as $cnt)
                                        <tr>
                                            <td class="fw-bold text-dark">
                                                {{ $cnt->name }}
                                                @if($cnt->is_primary)
                                                    <span class="badge bg-soft-primary text-primary ms-1">Primary</span>
                                                @endif
                                            </td>
                                            <td>{{ $cnt->designation ?: '—' }}</td>
                                            <td>
                                                <span class="badge bg-light text-dark border px-2 py-0.5 fs-11">{{ $cnt->role ?: 'Decision Maker' }}</span>
                                            </td>
                                            <td>{{ $cnt->email ?: '—' }}</td>
                                            <td>{{ $cnt->mobile ?: $cnt->phone ?: '—' }}</td>
                                            <td>
                                                <span class="badge {{ $cnt->status === 'active' ? 'bg-soft-success text-success' : 'bg-light text-muted' }} px-2 py-0.5">
                                                    {{ ucfirst($cnt->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No contact persons added yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 3: Quotations -->
                    <div class="tab-pane fade" id="quotations-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table odoo-table align-middle">
                                <thead class="table-light text-muted fs-12 text-uppercase">
                                    <tr>
                                        <th>Quotation #</th>
                                        <th>Revision</th>
                                        <th>Date</th>
                                        <th>Total Amount (₹)</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13 text-dark">
                                    @forelse($account->quotations as $q)
                                        <tr>
                                            <td class="font-monospace fw-bold text-primary">{{ $q->quotation_number }}</td>
                                            <td>
                                                <span class="badge bg-light text-dark border font-monospace">Rev {{ $q->revision_number }}</span>
                                            </td>
                                            <td>{{ $q->quotation_date ? \Illuminate\Support\Carbon::parse($q->quotation_date)->format('d M Y') : '—' }}</td>
                                            <td class="fw-bold text-success">₹{{ number_format($q->total_amount, 2) }}</td>
                                            <td>
                                                <span class="badge {{ $q->status === 'Accepted' ? 'bg-soft-success text-success' : 'bg-soft-primary text-primary' }} px-2 py-0.5">
                                                    {{ $q->status }}
                                                </span>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="{{ route('crm.quotations.show', $q) }}" class="btn btn-sm btn-soft-primary">
                                                    <i class="feather-eye me-1"></i>View Quotation
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No quotations linked to this account.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 4: Sales Orders -->
                    <div class="tab-pane fade" id="orders-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table odoo-table align-middle">
                                <thead class="table-light text-muted fs-12 text-uppercase">
                                    <tr>
                                        <th>SO Number #</th>
                                        <th>Order Date</th>
                                        <th>Total Amount (₹)</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13 text-dark">
                                    @forelse($salesOrders as $so)
                                        <tr>
                                            <td class="font-monospace fw-bold text-primary">{{ $so->so_number }}</td>
                                            <td>{{ $so->order_date ? \Illuminate\Support\Carbon::parse($so->order_date)->format('d M Y') : '—' }}</td>
                                            <td class="fw-bold text-success">₹{{ number_format($so->total_amount, 2) }}</td>
                                            <td>
                                                <span class="badge bg-soft-success text-success px-2 py-0.5">{{ $so->status }}</span>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="{{ route('sales.orders.show', $so) }}" class="btn btn-sm btn-soft-primary">
                                                    <i class="feather-eye me-1"></i>View SO
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No sales orders generated yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Add Contact -->
    <div class="modal fade" id="addContactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('crm.accounts.contacts.store', $account) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Add Contact Person to {{ $account->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Contact Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Amit Patel" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control" placeholder="e.g. Director">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Buying Center Role</label>
                            <select name="role" class="form-select">
                                <option value="Purchase Decision Maker">Purchase Decision Maker</option>
                                <option value="Technical Evaluator">Technical Evaluator</option>
                                <option value="Finance">Finance / Accounts</option>
                                <option value="Influencer">Influencer</option>
                                <option value="End User">End User</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="amit@company.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input type="text" name="mobile" class="form-control" placeholder="9876543210">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="isPrimaryCheck">
                            <label class="form-check-label" for="isPrimaryCheck">Set as Primary Contact</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #1e40af; border-color: #1e40af;">Save Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
