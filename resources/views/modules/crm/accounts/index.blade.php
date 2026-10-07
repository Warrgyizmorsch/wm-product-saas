@extends('layouts.duralux')

@section('title', __('crm.accounts_sidebar') . ' | SaaS ERP')
@section('page-title', __('crm.accounts_sidebar'))
@section('breadcrumb', __('crm.accounts_sidebar'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="accounts" 
            :can-import="false" 
            :can-download-template="false" 
            export-route="{{ route('crm.accounts.export') }}" />
        <x-ui.button href="{{ route('crm.accounts.create') }}" variant="primary" icon="feather-plus">
            {{ __('crm.add_new_account') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendors/css/sweetalert2.min.css') }}">
<style>
    .table-account-row:hover {
        background-color: #f8fafc;
    }
    html.app-skin-dark .table-account-row:hover {
        background-color: #162038 !important;
    }
    .account-row-selected {
        background-color: rgba(30, 64, 175, 0.05) !important;
    }
    .cursor-pointer {
        cursor: pointer;
    }
    /* Hide scrollbars on responsive table container */
    .table-responsive {
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .table-responsive::-webkit-scrollbar {
        display: none;
    }
</style>
@endpush

@section('content')

    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if ($errors->any())
            <div class="alert alert-danger mb-3 alert-dismissible fade show fs-12 py-2" role="alert">
                <ul class="mb-0 ps-3 text-start">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem 1rem;"></button>
            </div>
        @endif

        {{-- 1. Header: Title & Actions --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('crm.accounts_listing') }}</h5>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Normal Toolbar (Search, Sort, Filter) -->
                <div id="normal-toolbar" class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Outside Search Box (HRMS Style) -->
                    <form method="GET" action="{{ route('crm.accounts.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 280px; max-width: 360px;">
                        @foreach(request()->except(['search', 'page']) as $k => $v)
                            @if(is_scalar($v) && $v !== '')
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endif
                        @endforeach
                        <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                        <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('crm.search_accounts_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                        @if(request('search'))
                            <a href="{{ route('crm.accounts.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="{{ __('crm.clear_search') }}">
                                <i class="feather-x fs-12"></i>
                            </a>
                        @endif
                    </form>

                    <x-ui.sort-dropdown :label="__('crm.sort')">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('crm.latest_accounts') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('crm.company_name_az') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <form method="GET" action="{{ route('crm.accounts.index') }}" class="d-inline">
                        <x-ui.filter :label="__('crm.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('crm.search_accounts_placeholder')" value="{{ request('search') }}" />
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <a href="{{ route('crm.accounts.index') }}" class="btn btn-xs btn-light border">{{ __('crm.reset') }}</a>
                                <button type="submit" class="btn btn-xs btn-primary" style="background-color: #1e40af; border-color: #1e40af;">{{ __('crm.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>

                <!-- Bulk Actions Toolbar (initially hidden, shows when checkboxes are selected) -->
                <div id="bulk-actions-toolbar" class="d-flex gap-2 d-none">
                    <x-ui.bulk-actions :label="__('crm.selected_actions') . ' (0)'" id="bulk-actions-dropdown">
                        <button type="button" class="dropdown-item text-primary" onclick="openBulkAssignDrawer()">
                            <i class="feather-user-check me-2 text-primary"></i> {{ __('crm.assign_to_sales_rep') }}
                        </button>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item text-secondary" onclick="clearAccountSelections()">
                            <i class="feather-x me-2 text-secondary"></i> {{ __('crm.deselect') }}
                        </button>
                    </x-ui.bulk-actions>
                </div>
            </div>
        </div>

        {{-- Active Filters Badges Row --}}
        @if(request('search'))
            <div class="d-flex align-items-center flex-wrap gap-2 mb-3 bg-light p-2 rounded border">
                <span class="fs-11 fw-bold text-uppercase text-muted me-1"><i class="feather-filter me-1"></i>{{ __('crm.active_filters') }}</span>
                <span class="badge bg-white text-dark border font-monospace fs-11">
                    Search: "{{ request('search') }}"
                    <a href="{{ route('crm.accounts.index', request()->except('search')) }}" class="text-danger ms-1 text-decoration-none">×</a>
                </span>
                <a href="{{ route('crm.accounts.index') }}" class="text-muted fs-11 ms-auto fw-bold text-decoration-none">{{ __('crm.clear_all') }}</a>
            </div>
        @endif

        {{-- 2. Data Table (Matches Leads/Deals/Customers borderless table 100%) --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="accountsTable" class="mb-0">
                <thead>
                    <tr>
                        <th style="width: 35px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllAccountsCheckbox" title="Select All Accounts">
                        </th>
                        <th>{{ __('crm.account_no') }}</th>
                        <th>{{ __('crm.company_name') }}</th>
                        <th>{{ __('crm.account_manager') }}</th>
                        <th>{{ __('crm.gstin') }}</th>
                        <th>{{ __('crm.primary_contact') }}</th>
                        <th>{{ __('crm.phone_email') }}</th>
                        <th class="text-end">{{ __('crm.deals') }}</th>
                        <th class="text-end">{{ __('crm.lifetime_revenue') }}</th>
                        <th style="width: 5%;" class="text-end pe-3">{{ __('crm.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @forelse($accounts as $acc)
                        <tr class="table-account-row" id="accountRow_{{ $acc->id }}">
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input account-select-checkbox" 
                                       value="{{ $acc->id }}" 
                                       data-account-name="{{ e($acc->name) }}">
                            </td>
                            <td class="font-monospace fw-bold">
                                <a href="{{ route('crm.accounts.show', $acc) }}" class="text-primary hover-underline">
                                    {{ $acc->account_number }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('crm.accounts.show', $acc) }}" class="fw-bold text-dark text-decoration-none hover-primary d-block">
                                    {{ $acc->name }}
                                </a>
                                @if($acc->industry_type)
                                    <div class="text-muted fs-11 mt-0.5">{{ $acc->industry_type }}</div>
                                @endif
                            </td>
                            <td id="accountOwnerCell_{{ $acc->id }}">
                                <div class="d-flex align-items-center cursor-pointer p-1 rounded" 
                                     onclick="openSingleAssignDrawer({{ $acc->id }}, '{{ e($acc->name) }}', '{{ $acc->owner_id }}', '{{ e($acc->owner?->name ?: __('crm.unassigned')) }}')"
                                     title="{{ $acc->owner ? __('crm.change_owner') : __('crm.assign_account_manager') }}"
                                     style="transition: background-color 0.15s ease;">
                                    <div class="rounded-circle me-2 d-flex align-items-center justify-content-center text-white fw-bold shadow-xs owner-avatar-circle" 
                                         style="width: 28px; height: 28px; background-color: {{ $acc->owner ? '#1e40af' : '#64748b' }}; font-size: 11px; flex-shrink: 0;"
                                         title="{{ $acc->owner?->name ?: __('crm.unassigned') }}">
                                        {{ strtoupper(substr($acc->owner?->name ?: 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        @if($acc->owner)
                                            <span class="d-block fw-semibold text-dark fs-12 owner-name-text" style="line-height: 1.2;">{{ $acc->owner->name }}</span>
                                            <span class="text-muted fs-10 d-block owner-email-text">{{ $acc->owner->email }}</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 fw-semibold d-inline-flex align-items-center gap-1 py-0.5 px-2">
                                                <i class="feather-user-plus fs-9"></i> {{ __('crm.unassigned') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($acc->gstin)
                                    <span class="badge bg-light text-dark font-monospace border px-2 py-0.5 fs-11">{{ $acc->gstin }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php $pContact = $acc->primaryContact ?: $acc->contacts->first(); @endphp
                                @if($pContact)
                                    <span class="fw-semibold text-dark">{{ $pContact->name }}</span>
                                    @if($pContact->designation)
                                        <div class="text-muted fs-11">{{ $pContact->designation }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($acc->phone)
                                    <div><i class="feather-phone me-1 text-muted fs-11"></i>{{ $acc->phone }}</div>
                                @endif
                                @if($acc->email)
                                    <div class="text-muted fs-11"><i class="feather-mail me-1 text-muted fs-11"></i>{{ $acc->email }}</div>
                                @endif
                                @if(!$acc->phone && !$acc->email)
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @php
                                    $openCount = $acc->open_deals_count;
                                    $wonCount = $acc->won_deals_count;
                                    $totalDeals = $acc->deals->count();
                                @endphp
                                @if($totalDeals > 0)
                                    <div class="d-inline-flex flex-column align-items-end gap-1">
                                        <div class="d-flex gap-1 justify-content-end">
                                            @if($openCount > 0)
                                                <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fw-bold" title="{{ $openCount }} {{ __('crm.open') }}">
                                                    {{ $openCount }} {{ __('crm.open') }}
                                                </span>
                                            @endif
                                            @if($wonCount > 0)
                                                <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-0.5 fw-bold" title="{{ $wonCount }} {{ __('crm.won') }}">
                                                    {{ $wonCount }} {{ __('crm.won') }}
                                                </span>
                                            @endif
                                            @if($openCount == 0 && $wonCount == 0)
                                                <span class="badge bg-soft-secondary text-secondary border px-2 py-0.5 fw-bold">
                                                    {{ $totalDeals }} {{ __('crm.deals') }}
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-muted fs-11 font-monospace">{{ __('crm.total_colon') }} {{ $totalDeals }}</span>
                                    </div>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-0.5 font-monospace">0 {{ __('crm.deals') }}</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-success fs-14">
                                {{ format_currency($acc->lifetime_revenue) }}
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown :viewUrl="route('crm.accounts.show', $acc)">
                                    {{-- Assign / Change Account Manager --}}
                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item fs-12 py-1.5 text-dark" onclick="openSingleAssignDrawer({{ $acc->id }}, '{{ e($acc->name) }}', '{{ $acc->owner_id }}', '{{ e($acc->owner?->name ?: __('crm.unassigned')) }}')">
                                            <i class="feather-user-check me-2 text-primary fs-12"></i>{{ $acc->owner_id ? __('crm.change_owner') : __('crm.assign_account_manager') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('crm.accounts.edit', $acc) }}" class="dropdown-item fs-12 py-1.5 text-dark">
                                            <i class="feather-edit me-2 text-muted"></i>{{ __('crm.edit_account') }}
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('crm.accounts.destroy', $acc->id) }}" method="POST" id="deleteAccountForm_{{ $acc->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="dropdown-item fs-12 py-1.5 text-danger fw-semibold" onclick="confirmAction({ title: '{{ __('crm.delete') }} Account', message: 'Are you sure you want to delete account {{ addslashes($acc->name) }}?', variant: 'danger', confirmText: '{{ __('crm.delete') }}', onConfirm: function() { document.getElementById('deleteAccountForm_{{ $acc->id }}').submit(); } })">
                                                <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('crm.delete') }}
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="feather-briefcase fs-1 text-muted d-block mb-2"></i>
                                {{ __('crm.no_accounts_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        {{-- 3. Common Component Pagination --}}
        <div class="mt-3">
            <x-ui.pagination 
                :currentPage="$accounts->currentPage()" 
                :totalPages="$accounts->lastPage()" 
                :totalResults="$accounts->total()" 
                :perPage="$accounts->perPage()" 
            />
        </div>
    </div>

    <!-- Offcanvas Drawer: Quick Single & Bulk Account Assignment -->
    <div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="assignAccountOffcanvas" aria-labelledby="assignAccountOffcanvasLabel" style="width: 460px; max-width: 92vw;">
        <div class="offcanvas-header bg-light border-bottom py-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle">
                    <i class="feather-user-check"></i>
                </div>
                <div>
                    <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="assignAccountOffcanvasTitle">{{ __('crm.assign_account_manager') }}</h5>
                    <span class="text-muted fs-11" id="assignAccountOffcanvasSubtitle">{{ __('crm.select_sales_rep') }}</span>
                </div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        
        <div class="offcanvas-body p-4 bg-white">
            <form id="assignAccountForm">
                @csrf
                <input type="hidden" name="assign_mode" id="assignModeInput" value="single">
                <input type="hidden" name="single_account_id" id="assignSingleAccountId" value="">
                <div id="assignBulkAccountIdsContainer"></div>

                <!-- Account Info / Target Preview Card -->
                <div class="p-3 mb-3 bg-light rounded-3 border" id="assignTargetSummaryCard">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted fs-11 fw-bold text-uppercase" id="assignTargetTypeLabel">{{ __('crm.company_name') }}</span>
                        <span class="badge bg-soft-info text-info border border-info-subtle fs-10" id="assignCurrentOwnerBadge">{{ __('crm.unassigned') }}</span>
                    </div>
                    <div class="fw-bold text-dark fs-13" id="assignTargetNameDisplay">Account Name</div>
                </div>

                <!-- Assignee Selector -->
                <div class="mb-3">
                    <label class="form-label fw-bold fs-12 text-dark mb-1">
                        {{ __('crm.select_sales_rep') }} <span class="text-danger">*</span>
                    </label>
                    <select name="account_owner_id" id="assignAccountOwnerSelect" class="form-select form-select-sm fs-12 py-2" required>
                        <option value="">{{ __('crm.choose_sales_rep_placeholder') }}</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" data-email="{{ $u->email }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Optional Assignment Note -->
                <div class="mb-4">
                    <label class="form-label fw-bold fs-12 text-dark mb-1">
                        {{ __('crm.assignment_note_reason') }} <span class="text-muted fw-normal fs-11">({{ __('crm.optional') ?? 'Optional' }})</span>
                    </label>
                    <textarea name="note" id="assignNoteInput" class="form-control fs-12" rows="3" placeholder="e.g. Assigned to enterprise relationship manager..."></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex align-items-center justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-light border px-4 py-2 fs-13 fw-bold text-uppercase" data-bs-dismiss="offcanvas">{{ __('crm.close') }}</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fs-13 fw-bold text-uppercase shadow-sm d-flex align-items-center gap-1.5" id="btnSubmitAccountAssign">
                        <i class="feather-check"></i>
                        <span>{{ __('crm.confirm_assignment') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/js/sweetalert2.all.min.js') }}"></script>
<script>
    $(function () {
        var assignOffcanvasEl = document.getElementById('assignAccountOffcanvas');
        var assignBsOffcanvas = assignOffcanvasEl ? new bootstrap.Offcanvas(assignOffcanvasEl) : null;
        var transAssignAccountManager = @json(__('crm.assign_account_manager'));
        var transBulkAssignAccounts = @json(__('crm.bulk_assign_accounts'));
        var transSelectSalesRep = @json(__('crm.select_sales_rep'));
        var transAccountsSelected = @json(__('crm.accounts_selected'));
        var transAccountSelected = @json(__('crm.account_selected'));
        var transUnassigned = @json(__('crm.unassigned'));
        var transChangeOwner = @json(__('crm.change_owner'));
        var transSelectedActions = @json(__('crm.selected_actions'));

        window.openSingleAssignDrawer = function(accountId, accountName, currentOwnerId, currentOwnerName) {
            $('#assignModeInput').val('single');
            $('#assignSingleAccountId').val(accountId);
            $('#assignBulkAccountIdsContainer').empty();
            
            $('#assignAccountOffcanvasTitle').text(transAssignAccountManager);
            $('#assignAccountOffcanvasSubtitle').text(transSelectSalesRep);
            $('#assignTargetTypeLabel').text(@json(__('crm.company_name')));
            $('#assignTargetNameDisplay').text(accountName || ('Account #' + accountId));

            if (currentOwnerName && currentOwnerName !== 'Unassigned' && currentOwnerName !== transUnassigned && currentOwnerName.trim() !== '') {
                $('#assignCurrentOwnerBadge').text(currentOwnerName).removeClass('bg-soft-secondary text-secondary').addClass('bg-soft-info text-info');
            } else {
                $('#assignCurrentOwnerBadge').text(transUnassigned).removeClass('bg-soft-info text-info').addClass('bg-soft-secondary text-secondary');
            }

            $('#assignAccountOwnerSelect').val(currentOwnerId || '');
            $('#assignNoteInput').val('');

            if (assignBsOffcanvas) {
                assignBsOffcanvas.show();
            }
        };

        window.openBulkAssignDrawer = function() {
            var selectedCheckboxes = $('.account-select-checkbox:checked');
            var count = selectedCheckboxes.length;
            if (count === 0) {
                alert('Please select at least one account from the table checkbox.');
                return;
            }

            $('#assignModeInput').val('bulk');
            $('#assignSingleAccountId').val('');
            var container = $('#assignBulkAccountIdsContainer').empty();

            var accountNames = [];
            selectedCheckboxes.each(function() {
                var aid = $(this).val();
                var aname = $(this).attr('data-account-name');
                container.append('<input type="hidden" name="account_ids[]" value="' + aid + '">');
                if (accountNames.length < 3 && aname) {
                    accountNames.push(aname);
                }
            });

            $('#assignAccountOffcanvasTitle').text(transBulkAssignAccounts);
            $('#assignAccountOffcanvasSubtitle').text(transSelectSalesRep);
            $('#assignTargetTypeLabel').text(count + ' ' + (count === 1 ? transAccountSelected : transAccountsSelected));
            $('#assignTargetNameDisplay').text(accountNames.join(', ') + (count > 3 ? ' and ' + (count - 3) + ' more...' : ''));
            $('#assignCurrentOwnerBadge').text(count + ' ' + (count === 1 ? transAccountSelected : transAccountsSelected)).removeClass('bg-soft-info text-info').addClass('bg-soft-primary text-primary');

            $('#assignAccountOwnerSelect').val('');
            $('#assignNoteInput').val('');

            if (assignBsOffcanvas) {
                assignBsOffcanvas.show();
            }
        };

        window.clearAccountSelections = function() {
            $('.account-select-checkbox').prop('checked', false);
            $('#selectAllAccountsCheckbox').prop('checked', false);
            $('#accountsTable tbody tr').removeClass('account-row-selected');
            updateToolbarVisibility();
        };

        function updateToolbarVisibility() {
            var selectedCheckboxes = $('.account-select-checkbox:checked');
            var count = selectedCheckboxes.length;
            var normalToolbar = document.getElementById('normal-toolbar');
            var bulkActionsToolbar = document.getElementById('bulk-actions-toolbar');
            var bulkActionsLabel = document.querySelector('#bulk-actions-toolbar .bulk-actions-label');

            if (count > 0) {
                if (normalToolbar) normalToolbar.classList.add('d-none');
                if (bulkActionsToolbar) bulkActionsToolbar.classList.remove('d-none');
                if (bulkActionsLabel) {
                    bulkActionsLabel.textContent = transSelectedActions + ' (' + count + ')';
                }
            } else {
                if (normalToolbar) normalToolbar.classList.remove('d-none');
                if (bulkActionsToolbar) bulkActionsToolbar.classList.add('d-none');
                if (bulkActionsLabel) {
                    bulkActionsLabel.textContent = transSelectedActions + ' (0)';
                }
            }
        }

        // Checkbox events
        $('#selectAllAccountsCheckbox').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('.account-select-checkbox').prop('checked', isChecked);
            if (isChecked) {
                $('#accountsTable tbody tr').addClass('account-row-selected');
            } else {
                $('#accountsTable tbody tr').removeClass('account-row-selected');
            }
            updateToolbarVisibility();
        });

        $(document).on('change', '.account-select-checkbox', function() {
            var tr = $(this).closest('tr');
            if ($(this).is(':checked')) {
                tr.addClass('account-row-selected');
            } else {
                tr.removeClass('account-row-selected');
            }
            
            var totalBoxes = $('.account-select-checkbox').length;
            var checkedBoxes = $('.account-select-checkbox:checked').length;
            $('#selectAllAccountsCheckbox').prop('checked', totalBoxes > 0 && totalBoxes === checkedBoxes);

            updateToolbarVisibility();
        });

        // Form Submit AJAX Handler
        $('#assignAccountForm').on('submit', function(e) {
            e.preventDefault();
            var mode = $('#assignModeInput').val();
            var submitBtn = $('#btnSubmitAccountAssign');
            var origBtnHtml = submitBtn.html();

            var ownerId = $('#assignAccountOwnerSelect').val();
            var note = $('#assignNoteInput').val();
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            var postUrl = "{{ route('crm.accounts.bulkAssign') }}";
            var payload = {
                account_owner_id: ownerId || null,
                note: note
            };

            var targetAccountIds = [];
            if (mode === 'single') {
                var singleId = parseInt($('#assignSingleAccountId').val());
                targetAccountIds.push(singleId);
                payload.account_ids = [singleId];
            } else {
                $('input[name="account_ids[]"]').each(function() {
                    targetAccountIds.push(parseInt($(this).val()));
                });
                payload.account_ids = targetAccountIds;
            }

            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...');

            fetch(postUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            })
            .then(function(r) {
                return r.json().then(function(data) {
                    return { ok: r.ok, status: r.status, data: data };
                }).catch(function() {
                    return { ok: r.ok, status: r.status, data: { message: r.statusText } };
                });
            })
            .then(function(resObj) {
                submitBtn.prop('disabled', false).html(origBtnHtml);
                var res = resObj.data || {};
                if (resObj.ok && res.success) {
                    var ownerName = res.owner_name || transUnassigned;
                    var ownerEmail = res.owner_email || '—';
                    var ownerInitial = res.owner_initial || 'U';
                    var isAssigned = !!res.owner_id;

                    // Update DOM for each target account
                    targetAccountIds.forEach(function(accountId) {
                        var cell = $('#accountOwnerCell_' + accountId);
                        if (cell.length) {
                            var row = $('#accountRow_' + accountId);
                            var accountName = row.find('.account-select-checkbox').attr('data-account-name') || ('Account #' + accountId);
                            
                            var newHtml = '';
                            if (isAssigned) {
                                newHtml = '<div class="d-flex align-items-center cursor-pointer p-1 rounded" ' +
                                    'onclick="openSingleAssignDrawer(' + accountId + ', \'' + (accountName.replace(/'/g, "\\'")) + '\', \'' + res.owner_id + '\', \'' + (ownerName.replace(/'/g, "\\'")) + '\')" ' +
                                    'title="' + transChangeOwner + '" style="transition: background-color 0.15s ease;">' +
                                    '<div class="rounded-circle me-2 d-flex align-items-center justify-content-center text-white fw-bold shadow-xs owner-avatar-circle" ' +
                                    'style="width: 28px; height: 28px; background-color: #1e40af; font-size: 11px; flex-shrink: 0;" title="' + ownerName + '">' +
                                    ownerInitial +
                                    '</div>' +
                                    '<div>' +
                                    '<span class="d-block fw-semibold text-dark fs-12 owner-name-text" style="line-height: 1.2;">' + ownerName + '</span>' +
                                    '<span class="text-muted fs-10 d-block owner-email-text">' + ownerEmail + '</span>' +
                                    '</div>' +
                                    '</div>';
                            } else {
                                newHtml = '<div class="d-flex align-items-center cursor-pointer p-1 rounded" ' +
                                    'onclick="openSingleAssignDrawer(' + accountId + ', \'' + (accountName.replace(/'/g, "\\'")) + '\', \'\', \'' + transUnassigned + '\')" ' +
                                    'title="' + transAssignAccountManager + '" style="transition: background-color 0.15s ease;">' +
                                    '<div class="rounded-circle me-2 d-flex align-items-center justify-content-center text-white fw-bold shadow-xs owner-avatar-circle" ' +
                                    'style="width: 28px; height: 28px; background-color: #64748b; font-size: 11px; flex-shrink: 0;" title="' + transUnassigned + '">' +
                                    'U' +
                                    '</div>' +
                                    '<div>' +
                                    '<span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 fw-semibold d-inline-flex align-items-center gap-1 py-0.5 px-2">' +
                                    '<i class="feather-user-plus fs-9"></i> ' + transUnassigned +
                                    '</span>' +
                                    '</div>' +
                                    '</div>';
                            }
                            cell.html(newHtml);
                        }
                    });

                    if (assignBsOffcanvas) {
                        assignBsOffcanvas.hide();
                    }

                    clearAccountSelections();

                    // Standard Duralux Toast Notification
                    if (typeof Swal !== 'undefined') {
                        Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3500,
                            timerProgressBar: true,
                            didOpen: function (toast) {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        }).fire({
                            icon: 'success',
                            title: res.message || 'Account assigned successfully!'
                        });
                    } else if (typeof toastr !== 'undefined') {
                        toastr.success(res.message || 'Account assigned successfully!');
                    }
                } else {
                    var errMsg = res.message;
                    if (res.errors) {
                        errMsg = Object.values(res.errors).flat().join("\n");
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        }).fire({
                            icon: 'error',
                            title: errMsg || 'Error updating account manager.'
                        });
                    } else {
                        alert(errMsg || 'Error updating account manager.');
                    }
                }
            })
            .catch(function(err) {
                submitBtn.prop('disabled', false).html(origBtnHtml);
                console.error('Assign failed:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    }).fire({
                        icon: 'error',
                        title: 'An error occurred while assigning accounts.'
                    });
                } else {
                    alert('An error occurred while assigning accounts.');
                }
            });
        });
    });
</script>
@endpush
