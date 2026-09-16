@once
    @push('styles')
        <style>
            .budget-line-action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 30px;
                height: 30px;
                border-radius: 8px;
                border: 1.5px solid #cbd5e1;
                background-color: #ffffff;
                color: #475569;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }
            .budget-line-action-btn:hover {
                background-color: color-mix(in srgb, var(--bs-primary) 12%, transparent);
                border-color: var(--bs-primary);
                color: var(--bs-primary);
            }
            .budget-line-action-btn--danger:hover {
                background-color: color-mix(in srgb, var(--bs-danger) 12%, transparent);
                border-color: var(--bs-danger);
                color: var(--bs-danger);
            }
            .budget-line-action-btn:disabled {
                opacity: 0.4;
                cursor: not-allowed;
                pointer-events: none;
            }
        </style>
    @endpush
@endonce

@push('scripts')
    <script>
        $(document).ready(function() {
            let rowIndex = 0;

            @php
                $mappedAccounts = $accounts->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name]);
                $mappedCostCenters = $costCenters->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name]);
                $mappedDepartments = $departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name]);
                $mappedProjects = $projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]);
                $existingLines = ($budget ?? null)
                    ? $budget->lines->map(fn ($l) => [
                        'chart_of_account_id' => $l->chart_of_account_id,
                        'cost_center_id' => $l->cost_center_id,
                        'department_id' => $l->department_id,
                        'project_id' => $l->project_id,
                        'amount' => $l->amount,
                    ])
                    : [];
            @endphp
            const accountsList = @json($mappedAccounts);
            const costCentersList = @json($mappedCostCenters);
            const departmentsList = @json($mappedDepartments);
            const projectsList = @json($mappedProjects);
            const existingLines = @json($existingLines);

            function escapeHtml(string) {
                return String(string).replace(/[&<>"']/g, function (s) {
                    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': '&quot;', "'": '&#39;' }[s];
                });
            }

            function buildAccountOptions(selectedId) {
                let opts = '<option value="">Select Account...</option>';
                accountsList.forEach(function(a) {
                    const sel = (a.id == selectedId) ? ' selected' : '';
                    opts += `<option value="${a.id}"${sel}>${escapeHtml(a.code)} - ${escapeHtml(a.name)}</option>`;
                });
                return opts;
            }

            function dimensionOptions(list, selectedId, labelKey) {
                let opts = '<option value="">Select...</option>';
                list.forEach(function(item) {
                    const sel = (item.id == selectedId) ? ' selected' : '';
                    const label = labelKey === 'code_name' ? `${escapeHtml(item.code)} - ${escapeHtml(item.name)}` : escapeHtml(item.name);
                    opts += `<option value="${item.id}"${sel}>${label}</option>`;
                });
                return opts;
            }

            function buildDimensionValueOptions(type, selectedId) {
                if (type === 'cost_center') return dimensionOptions(costCentersList, selectedId, 'code_name');
                if (type === 'department') return dimensionOptions(departmentsList, selectedId, 'name');
                if (type === 'project') return dimensionOptions(projectsList, selectedId, 'name');
                return '<option value="">—</option>';
            }

            function getRowHtml(index, line) {
                line = line || {};
                const dimensionType = line.cost_center_id ? 'cost_center' : (line.department_id ? 'department' : (line.project_id ? 'project' : ''));
                const dimensionId = line.cost_center_id || line.department_id || line.project_id || '';

                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-3">
                            <select name="lines[${index}][chart_of_account_id]" class="form-select odoo-table-select odoo-select2 account-select" required>
                                ${buildAccountOptions(line.chart_of_account_id || '')}
                            </select>
                        </td>
                        <td>
                            <select class="form-select odoo-table-select dimension-type-select">
                                <option value="" ${dimensionType === '' ? 'selected' : ''}>None</option>
                                <option value="cost_center" ${dimensionType === 'cost_center' ? 'selected' : ''}>Cost Center</option>
                                <option value="department" ${dimensionType === 'department' ? 'selected' : ''}>Department</option>
                                <option value="project" ${dimensionType === 'project' ? 'selected' : ''}>Project</option>
                            </select>
                        </td>
                        <td>
                            <select name="lines[${index}][cost_center_id]" class="form-select odoo-table-select odoo-select2 dimension-value-select" ${dimensionType ? '' : 'disabled'}>
                                ${buildDimensionValueOptions(dimensionType, dimensionId)}
                            </select>
                        </td>
                        <td>
                            <input type="number" name="lines[${index}][amount]" class="odoo-table-input text-end amount-input" value="${line.amount ?? ''}" min="0.01" step="0.01" required>
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <button type="button" class="budget-line-action-btn clone-row-btn" title="Clone this line" data-bs-toggle="tooltip">
                                    <i class="feather-copy fs-13"></i>
                                </button>
                                <button type="button" class="budget-line-action-btn budget-line-action-btn--danger remove-row-btn" title="Remove this line" data-bs-toggle="tooltip">
                                    <i class="feather-trash-2 fs-13"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }

            function renameDimensionValueSelect(row, type) {
                const select = row.find('.dimension-value-select');
                const fieldName = type === 'department' ? 'department_id' : (type === 'project' ? 'project_id' : 'cost_center_id');
                select.attr('name', `lines[${row.data('row-id')}][${fieldName}]`);
            }

            $(document).on('change', '.dimension-type-select', function() {
                const row = $(this).closest('tr');
                const type = $(this).val();
                const valueSelect = row.find('.dimension-value-select');

                if (typeof $.fn.select2 === 'function' && valueSelect.hasClass('select2-hidden-accessible')) {
                    valueSelect.select2('destroy');
                }

                valueSelect.html(buildDimensionValueOptions(type, ''));
                valueSelect.prop('disabled', !type);
                renameDimensionValueSelect(row, type);

                if (typeof $.fn.select2 === 'function') {
                    valueSelect.select2({ theme: "bootstrap-5", width: "100%" });
                }
            });

            function reindexRemoveState() {
                const rowCount = $('#itemsTable tbody .item-row').length;
                $('.remove-row-btn').prop('disabled', rowCount <= 1);
            }

            $('#addItemRow').on('click', function() {
                addRow();
                reindexRemoveState();
            });

            $(document).on('click', '.clone-row-btn', function() {
                const $row = $(this).closest('tr');
                const dimensionType = $row.find('.dimension-type-select').val();
                const dimensionId = $row.find('.dimension-value-select').val();
                const newRow = addRow({
                    chart_of_account_id: $row.find('.account-select').val(),
                    cost_center_id: dimensionType === 'cost_center' ? dimensionId : null,
                    department_id: dimensionType === 'department' ? dimensionId : null,
                    project_id: dimensionType === 'project' ? dimensionId : null,
                    amount: $row.find('.amount-input').val(),
                });
                newRow.insertAfter($row);
                reindexRemoveState();
            });

            $(document).on('click', '.remove-row-btn', function() {
                if ($('#itemsTable tbody .item-row').length <= 1) {
                    return;
                }
                $(this).closest('tr').remove();
                reindexRemoveState();
            });

            function addRow(line) {
                const newRow = $(getRowHtml(rowIndex, line));
                $('#itemsTable tbody').append(newRow);

                if (line) {
                    const type = line.cost_center_id ? 'cost_center' : (line.department_id ? 'department' : (line.project_id ? 'project' : ''));
                    renameDimensionValueSelect(newRow, type);
                }

                if (typeof $.fn.select2 === 'function') {
                    newRow.find('.account-select').select2({ theme: "bootstrap-5", width: "100%", dropdownParent: newRow.closest('.offcanvas, body') });
                    newRow.find('.dimension-value-select').select2({ theme: "bootstrap-5", width: "100%", dropdownParent: newRow.closest('.offcanvas, body') });
                }

                rowIndex++;
                reindexRemoveState();

                return newRow;
            }

            if (existingLines.length > 0) {
                existingLines.forEach(function(line) {
                    addRow(line);
                });
            } else {
                addRow();
            }
        });
    </script>
@endpush
