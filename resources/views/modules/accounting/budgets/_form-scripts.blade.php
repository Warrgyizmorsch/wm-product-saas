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
                            <input type="number" name="lines[${index}][amount]" class="odoo-table-input text-end amount-input" value="${line.amount ?? ''}" min="0.01" step="0.01" style="width: 130px; margin-left: auto;" required>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn mt-1">
                                <i class="feather-trash-2"></i>
                            </button>
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

            $('#addItemRow').on('click', function() {
                addRow();
            });

            $(document).on('click', '.remove-row-btn', function() {
                const rowsCount = $('.item-row').length;
                if (rowsCount > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert('A budget requires at least one line.');
                }
            });

            function addRow(line) {
                const newRow = $(getRowHtml(rowIndex, line));
                $('#itemsTable tbody').append(newRow);

                if (line) {
                    const type = line.cost_center_id ? 'cost_center' : (line.department_id ? 'department' : (line.project_id ? 'project' : ''));
                    renameDimensionValueSelect(newRow, type);
                }

                if (typeof $.fn.select2 === 'function') {
                    newRow.find('.account-select').select2({ theme: "bootstrap-5", width: "100%" });
                    newRow.find('.dimension-value-select').select2({ theme: "bootstrap-5", width: "100%" });
                }

                rowIndex++;
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
