<div class="row g-4">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="Salary Structures (Slabs)" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <x-ui.button variant="light" icon="feather-activity" data-bs-toggle="offcanvas" data-bs-target="#ctcCalculatorDrawer" class="me-2">
                    CTC Calculator
                </x-ui.button>
                <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addSalaryStructureModal">
                    Add Structure
                </x-ui.button>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">#</th>
                            <th>Structure Name</th>
                            <th>Legal Entity</th>
                            <th>Min Yearly CTC</th>
                            <th>Max Yearly CTC</th>
                            <th>Rules Count</th>
                            <th>Status</th>
                            <th width="150" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salaryStructures as $structure)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <span class="fw-bold text-dark">{{ $structure->name }}</span>
                                </td>
                                <td>
                                    {{ $structure->company ? $structure->company->company_name : 'All Companies' }}
                                </td>
                                <td>
                                    <span class="fw-semibold">₹{{ number_format($structure->min_ctc, 2) }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold">₹{{ number_format($structure->max_ctc, 2) }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-soft-info text-info rounded-pill px-2">
                                        {{ $structure->items->count() }} Components
                                    </span>
                                </td>
                                <td>
                                    @if($structure->status)
                                        <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <x-ui.icon-btn variant="soft-primary" icon="feather-chevron-down" class="toggle-structure-details" data-target="#structure-details-{{ $structure->id }}" title="Show Components" />
                                        <x-ui.icon-btn variant="primary" icon="feather-edit" class="edit-structure-btn" data-structure="{{ base64_encode($structure->toJson()) }}" title="Edit" />
                                        <form action="{{ route('hrms.salary-structure.structure.destroy', $structure->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this structure slab?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="submit" variant="soft-danger" icon="feather-trash-2" title="Delete" />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr id="structure-details-{{ $structure->id }}" class="table-light d-none">
                                <td></td>
                                <td colspan="7" class="p-3">
                                    <div class="card border-0 shadow-none m-0 bg-light">
                                        <div class="card-body p-3">
                                            <div class="row g-3">
                                                @forelse($structure->items as $item)
                                                    @php
                                                        $calcTypeLabel = match($item->calculation_type) {
                                                            'fixed' => 'Fixed Amount',
                                                            'percentage_of_ctc' => 'of CTC',
                                                            'percentage_of_basic' => 'of Basic',
                                                            'balancing' => 'Balancing / Remainder',
                                                            default => $item->calculation_type
                                                        };
                                                        $typeBadge = $item->component->type == 'earning' 
                                                            ? '<span class="badge bg-soft-success text-success">Earning</span>' 
                                                            : '<span class="badge bg-soft-danger text-danger">Deduction</span>';
                                                    @endphp
                                                    <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                                                        <div class="p-3 border rounded bg-white shadow-sm h-100 d-flex flex-column justify-content-between">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <span class="fw-bold text-dark text-uppercase" style="font-size: 14px; letter-spacing: 0.5px;">{{ $item->component->code }}</span>
                                                                {!! $typeBadge !!}
                                                            </div>
                                                            <div class="border-top pt-2 mt-auto">
                                                                <div class="d-flex align-items-center">
                                                                    <span class="fw-bold text-primary" style="font-size: 14px;">
                                                                        @if($item->calculation_type === 'fixed')
                                                                            ₹{{ number_format($item->value, 2) }}
                                                                        @elseif($item->calculation_type === 'percentage_of_ctc' || $item->calculation_type === 'percentage_of_basic')
                                                                            {{ floatval($item->value) }}%
                                                                        @else
                                                                            <span class="text-secondary">-</span>
                                                                        @endif
                                                                    </span>
                                                                    <span class="text-muted ms-1" style="font-size: 12px; max-width: 65%;">{{ $calcTypeLabel }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @empty
                                                    <div class="col-12 text-muted text-center py-2">
                                                        <i class="feather-alert-circle me-1"></i>No components configured for this structure.
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="feather-alert-circle fs-32 mb-2 d-block text-secondary"></i>
                                    No Salary Structure slabs defined yet. Click "Add Structure" to get started.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>
</div>

<!-- ========================================================================= -->
<!-- ADD SALARY STRUCTURE MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="addSalaryStructureModal" tabindex="-1" aria-labelledby="addSalaryStructureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addSalaryStructureModalLabel">Create Salary Structure Slab</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.salary-structure.structure.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <x-ui.input label="Structure/Slab Name" name="name" placeholder="e.g. Slab 0 - 6 Lakhs" required />
                        </div>
                        <div class="col-md-6 col-12">
                            <x-ui.select label="Legal Entity (Company)" name="company_id" data-select2-selector="default">
                                <option value="">Apply to All Companies</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-md-6 col-12">
                            <x-ui.input label="Min Yearly CTC (₹)" name="min_ctc" type="number" placeholder="0" min="0" required />
                        </div>
                        <div class="col-md-6 col-12">
                            <x-ui.input label="Max Yearly CTC (₹)" name="max_ctc" type="number" placeholder="600000" min="0" required />
                        </div>
                        <div class="col-md-12 col-12">
                            <x-ui.select label="Status" name="status" data-select2-selector="default" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                    </div>

                    <!-- Component Configuration Table -->
                    <div class="mt-4">
                        <h6 class="fw-bold border-bottom pb-2 mb-3">Configure Component Calculation Rules</h6>
                        <div class="table-responsive border rounded bg-light">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Component Name</th>
                                        <th>Type</th>
                                        <th>Calculation Rule</th>
                                        <th width="160">Rule Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($salaryComponents as $comp)
                                        <tr>
                                            <td>
                                                <span class="fw-bold">{{ $comp->name }}</span>
                                                <code class="ms-1">{{ $comp->code }}</code>
                                            </td>
                                            <td>
                                                @if($comp->type == 'earning')
                                                    <span class="badge bg-soft-success text-success">Earning</span>
                                                @else
                                                    <span class="badge bg-soft-warning text-warning">Deduction</span>
                                                @endif
                                            </td>
                                            <td>
                                                <select name="components[{{ $comp->id }}][calculation_type]" 
                                                        class="form-select form-select-sm add-calc-type-select" 
                                                        data-comp-id="{{ $comp->id }}" 
                                                        onchange="handleCalcTypeChange('add', {{ $comp->id }})">
                                                    <option value="not_included">Not Included</option>
                                                    <option value="fixed">Fixed Amount</option>
                                                    <option value="percentage_of_ctc">Percentage of CTC</option>
                                                    <option value="percentage_of_basic">Percentage of Basic</option>
                                                    <option value="balancing">Balancing / Remainder</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" 
                                                       name="components[{{ $comp->id }}][value]" 
                                                       class="form-control form-control-sm add-value-input" 
                                                       id="add-value-{{ $comp->id }}" 
                                                       placeholder="0.00" disabled>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No components found. Create components first.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-ui.button variant="light" data-bs-dismiss="modal">Close</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Create Structure</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- EDIT SALARY STRUCTURE MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="editSalaryStructureModal" tabindex="-1" aria-labelledby="editSalaryStructureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editSalaryStructureModalLabel">Edit Salary Structure Slab</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editSalaryStructureForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <x-ui.input label="Structure/Slab Name" name="name" id="edit_name" required />
                        </div>
                        <div class="col-md-6 col-12">
                            <x-ui.select label="Legal Entity (Company)" name="company_id" id="edit_company_id" data-select2-selector="default">
                                <option value="">Apply to All Companies</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="col-md-6 col-12">
                            <x-ui.input label="Min Yearly CTC (₹)" name="min_ctc" id="edit_min_ctc" type="number" required />
                        </div>
                        <div class="col-md-6 col-12">
                            <x-ui.input label="Max Yearly CTC (₹)" name="max_ctc" id="edit_max_ctc" type="number" required />
                        </div>
                        <div class="col-md-12 col-12">
                            <x-ui.select label="Status" name="status" id="edit_status" data-select2-selector="default" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </x-ui.select>
                        </div>
                    </div>

                    <!-- Component Configuration Table -->
                    <div class="mt-4">
                        <h6 class="fw-bold border-bottom pb-2 mb-3">Configure Component Calculation Rules</h6>
                        <div class="table-responsive border rounded bg-light">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Component Name</th>
                                        <th>Type</th>
                                        <th>Calculation Rule</th>
                                        <th width="160">Rule Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($salaryComponents as $comp)
                                        <tr>
                                            <td>
                                                <span class="fw-bold">{{ $comp->name }}</span>
                                                <code class="ms-1">{{ $comp->code }}</code>
                                            </td>
                                            <td>
                                                @if($comp->type == 'earning')
                                                    <span class="badge bg-soft-success text-success">Earning</span>
                                                @else
                                                    <span class="badge bg-soft-warning text-warning">Deduction</span>
                                                @endif
                                            </td>
                                            <td>
                                                <select name="components[{{ $comp->id }}][calculation_type]" 
                                                        class="form-select form-select-sm edit-calc-type-select" 
                                                        data-comp-id="{{ $comp->id }}" 
                                                        id="edit-calc-type-{{ $comp->id }}"
                                                        onchange="handleCalcTypeChange('edit', {{ $comp->id }})">
                                                    <option value="not_included">Not Included</option>
                                                    <option value="fixed">Fixed Amount</option>
                                                    <option value="percentage_of_ctc">Percentage of CTC</option>
                                                    <option value="percentage_of_basic">Percentage of Basic</option>
                                                    <option value="balancing">Balancing / Remainder</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" 
                                                       name="components[{{ $comp->id }}][value]" 
                                                       class="form-control form-control-sm edit-value-input" 
                                                       id="edit-value-{{ $comp->id }}" 
                                                       placeholder="0.00" disabled>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-ui.button variant="light" data-bs-dismiss="modal">Close</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Save Changes</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CTC CALCULATOR DRAWER -->
<x-ui.drawer id="ctcCalculatorDrawer" title="CTC Calculator & Simulator" position="end" :close-on-outside-click="true" style="width: 540px; max-width: 100%;">
    <div class="mb-4">
        <label for="sim_ctc" class="form-label fw-semibold text-dark">Enter Yearly CTC (₹)</label>
        <div class="input-group">
            <span class="input-group-text bg-light text-dark fw-bold">₹</span>
            <input type="number" id="sim_ctc" class="form-control" placeholder="e.g. 600000" onkeyup="calculateSimulator()" onchange="calculateSimulator()">
        </div>
        <small class="text-muted d-block mt-1">Slabs will automatically match based on range limits.</small>
    </div>

    <div id="sim-error-msg" class="alert alert-soft-danger py-3 px-3 align-items-center" style="display: none;"></div>

    <div id="sim-results-card" style="display: none;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">Matched Slab:</span>
            <span id="sim-slab-name" class="badge bg-soft-primary text-primary px-3 py-1 fw-bold fs-12"></span>
        </div>

        <div class="table-responsive border rounded bg-white">
            <table class="table table-sm table-hover mb-0 align-middle" style="font-size: 13px;">
                <thead class="table-light">
                    <tr>
                        <th>Component</th>
                        <th>Type</th>
                        <th class="text-end">Monthly</th>
                        <th class="text-end">Yearly</th>
                    </tr>
                </thead>
                <tbody id="sim-results-body">
                </tbody>
            </table>
        </div>
    </div>

    <x-slot name="footer">
        <x-ui.button variant="light" data-bs-dismiss="offcanvas">Close Panel</x-ui.button>
    </x-slot>
</x-ui.drawer>

<script>
    // Enable/disable rule value fields depending on the selected calculation type
    function handleCalcTypeChange(prefix, compId) {
        let select = $(`#${prefix}-calc-type-${compId}`);
        let input = $(`#${prefix}-value-${compId}`);
        let val = select.val();

        if (val === 'not_included' || val === 'balancing') {
            input.val('').prop('disabled', true);
        } else {
            input.prop('disabled', false);
        }
    }

    // Modal populate edit scripts & detail toggles
    document.addEventListener("DOMContentLoaded", function() {
        // Load initial simulator values
        calculateSimulator();

        // Toggle structure components details row
        $(document).on('click', '.toggle-structure-details', function() {
            let targetId = $(this).attr('data-target');
            let targetRow = $(targetId);
            let icon = $(this).find('i');
            
            if (targetRow.hasClass('d-none')) {
                targetRow.removeClass('d-none');
                icon.removeClass('feather-chevron-down').addClass('feather-chevron-up');
                $(this).attr('title', 'Hide Components');
            } else {
                targetRow.addClass('d-none');
                icon.removeClass('feather-chevron-up').addClass('feather-chevron-down');
                $(this).attr('title', 'Show Components');
            }
        });

        // Use robust event delegation for the edit handler
        $(document).on('click', '.edit-structure-btn', function() {
            let dataStr = $(this).attr('data-structure');
            if (!dataStr) return;

            // Decode structure data from base64
            let structure = JSON.parse(atob(dataStr));
            
            let id = structure.id;
            let name = structure.name;
            let company_id = structure.company_id;
            let min_ctc = parseFloat(structure.min_ctc);
            let max_ctc = parseFloat(structure.max_ctc);
            let status = (structure.status === true || structure.status === 1 || structure.status === '1') ? '1' : '0';
            let items = structure.items;

            $('#editSalaryStructureForm').attr('action', `/hrms/salary-structure/structure/update/${id}`);
            $('#edit_name').val(name);
            $('#edit_company_id').val(company_id || '');
            $('#edit_min_ctc').val(min_ctc);
            $('#edit_max_ctc').val(max_ctc);
            $('#edit_status').val(status);

            // Reset all selects to not_included and disable inputs
            $('.edit-calc-type-select').val('not_included');
            $('.edit-value-input').val('').prop('disabled', true);

            // Populate rules from items database
            if (items && Array.isArray(items)) {
                items.forEach(function(item) {
                    let select = $(`#edit-calc-type-${item.salary_component_id}`);
                    let input = $(`#edit-value-${item.salary_component_id}`);
                    
                    select.val(item.calculation_type);
                    if (item.calculation_type !== 'not_included' && item.calculation_type !== 'balancing') {
                        input.val(parseFloat(item.value)).prop('disabled', false);
                    }
                });
            }

            // Open the modal programmatically to avoid tooltip/modal toggle attribute collision
            $('#editSalaryStructureModal').modal('show');
        });
    });

    // Real-time client-side calculator
    function calculateSimulator() {
        let ctc = parseFloat($('#sim_ctc').val());
        if (isNaN(ctc) || ctc < 0) {
            $('#sim-error-msg').text('Please enter a valid CTC amount.').show();
            $('#sim-results-card').hide();
            return;
        }

        let structures = @json($salaryStructures);
        let matched = null;

        // Loop through structures and check matching min_ctc <= CTC <= max_ctc
        for (let s of structures) {
            let min = parseFloat(s.min_ctc);
            let max = parseFloat(s.max_ctc);
            if (ctc >= min && ctc <= max && s.status) {
                matched = s;
                break;
            }
        }

        if (!matched) {
            $('#sim-error-msg').html(`<i class="feather-alert-triangle me-2"></i>No active Salary Structure slab matches the entered CTC of <strong>₹${ctc.toLocaleString('en-IN')}</strong>. Please configure a matching slab.`).show();
            $('#sim-results-card').hide();
            return;
        }

        $('#sim-error-msg').hide();

        let basicYearly = 0;
        let totalAllocatedYearly = 0;
        let rows = [];

        // Sort items by evaluation sort_order
        let items = matched.items.slice().sort((a, b) => a.sort_order - b.sort_order);

        // First pass: Calculate all non-balancing items
        for (let item of items) {
            if (item.calculation_type === 'balancing') continue;

            let valYearly = 0;
            let ruleText = '';

            if (item.calculation_type === 'fixed') {
                valYearly = parseFloat(item.value);
                ruleText = `Fixed: ₹${valYearly.toLocaleString('en-IN')}`;
            } else if (item.calculation_type === 'percentage_of_ctc') {
                valYearly = (parseFloat(item.value) / 100) * ctc;
                ruleText = `${parseFloat(item.value)}% of CTC`;
            } else if (item.calculation_type === 'percentage_of_basic') {
                valYearly = (parseFloat(item.value) / 100) * basicYearly;
                ruleText = `${parseFloat(item.value)}% of Basic`;
            }

            if (item.component.code.toLowerCase() === 'basic') {
                basicYearly = valYearly;
            }

            totalAllocatedYearly += valYearly;

            rows.push({
                name: item.component.name,
                code: item.component.code,
                type: item.component.type,
                rule: ruleText,
                yearly: valYearly,
                monthly: valYearly / 12
            });
        }

        // Second pass: Calculate balancing item
        for (let item of items) {
            if (item.calculation_type !== 'balancing') continue;

            let valYearly = Math.max(0, ctc - totalAllocatedYearly);

            rows.push({
                name: item.component.name,
                code: item.component.code,
                type: item.component.type,
                rule: 'Remaining Balance (Balancing)',
                yearly: valYearly,
                monthly: valYearly / 12
            });
        }

        // Render rows
        let tbody = $('#sim-results-body');
        tbody.empty();
        let totalYearly = 0;
        let totalMonthly = 0;

        rows.forEach((r, idx) => {
            let typeBadge = r.type === 'earning' 
                ? '<span class="badge bg-soft-success text-success">Earning</span>' 
                : '<span class="badge bg-soft-danger text-danger">Deduction</span>';

            tbody.append(`
                <tr>
                    <td>
                        <div class="fw-bold">${r.name}</div>
                        <code style="font-size: 11px;">${r.code}</code>
                    </td>
                    <td>${typeBadge}</td>
                    <td class="text-end fw-semibold">₹${(r.monthly).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td class="text-end fw-semibold">₹${(r.yearly).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                </tr>
            `);

            totalYearly += r.yearly;
            totalMonthly += r.monthly;
        });

        // Add summary row
        tbody.append(`
            <tr class="table-light fw-bold">
                <td colspan="2">Total (CTC)</td>
                <td class="text-end text-primary">₹${totalMonthly.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="text-end text-primary">₹${totalYearly.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            </tr>
        `);

        $('#sim-slab-name').text(matched.name);
        $('#sim-results-card').fadeIn();
    }
</script>
