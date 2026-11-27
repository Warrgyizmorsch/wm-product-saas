@php
    if (!function_exists('renderExplosionTableRows')) {
        function renderExplosionTableRows($node, $level = 1, $nodeId = 'exp-1', $parentId = '') {
            $padding = ($level - 1) * 18;
            $isLeaf = empty($node['children']);
            $hasSubBom = !empty($node['has_sub_bom']) && !empty($node['bom_id']);
            $bomVersion = $node['bom_version'] ?? null;
            $qty = $node['gross_quantity'] ?? $node['quantity'];
            $uom = $node['uom_code'] ?? 'PCS';
            $isAltInfo = !empty($node['is_alternative']);
            $isAltOp = !empty($node['is_alternative_operation']);
            $isHiddenByDefault = $level > 2;
            $isInitiallyExpanded = $level === 1 && !$isLeaf;

            $rowClasses = ['bom-exp-row'];
            if ($isAltInfo) {
                $rowClasses[] = 'text-muted bg-light-subtle';
            } elseif ($level === 1) {
                $rowClasses[] = 'bg-soft-primary bg-opacity-25';
            }
            if ($isHiddenByDefault) {
                $rowClasses[] = 'd-none';
            }

            echo '<tr class="' . implode(' ', $rowClasses) . '"'
                . ' data-node-id="' . e($nodeId) . '"'
                . ' data-parent-id="' . e($parentId) . '"'
                . ' data-level="' . (int) $level . '"'
                . ' data-has-children="' . ($isLeaf ? '0' : '1') . '"'
                . ' data-expanded="' . ($isInitiallyExpanded ? 'true' : 'false') . '">';

            // 1. Component Product (Arrows + Subtle Level + Collapsible Child Drawer Toggle)
            echo '<td class="align-middle">';
            echo '<div style="padding-left: ' . $padding . 'px;" class="d-flex align-items-center gap-1.5">';

            if (!$isLeaf) {
                $chevIcon = $isInitiallyExpanded ? 'feather-chevron-down' : 'feather-chevron-right';
                echo '<button type="button" class="btn btn-sm btn-light border px-1 py-0 bom-exp-toggle flex-shrink-0" style="width: 22px; height: 22px; line-height: 1;" onclick="toggleBomExplosionNode(\'' . e($nodeId) . '\')" aria-expanded="' . ($isInitiallyExpanded ? 'true' : 'false') . '" title="Expand or collapse sub-BOM level">';
                echo '<i class="' . $chevIcon . ' fs-11"></i>';
                echo '</button>';
            } else {
                echo '<span class="d-inline-block flex-shrink-0" style="width: 22px;"></span>';
            }

            $levelBadgeClass = $level <= 2
                ? 'bg-soft-primary text-primary border border-primary-subtle'
                : 'bg-light text-secondary border';
            echo '<span class="badge font-monospace fs-10 px-1.5 py-0.5 flex-shrink-0 ' . $levelBadgeClass . '">L' . (int) $level . '</span>';

            if ($level === 1) {
                echo '<i class="feather-layers text-primary fs-13 flex-shrink-0"></i>';
            } else {
                echo '<svg class="flex-shrink-0 ' . ($isAltInfo ? 'text-warning opacity-75' : 'text-secondary') . '" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 2v7h9"/><path d="M9.5 6.5L12 9l-2.5 2.5"/></svg>';
            }

            echo '<div class="d-flex flex-column">';
            echo '<div class="d-flex align-items-center flex-wrap gap-1">';
            echo '<span class="fw-semibold ' . ($isAltInfo ? 'text-secondary' : 'text-dark') . ' fs-13">' . e($node['product_name']) . '</span>';
            if ($level === 1) {
                echo '<span class="badge bg-soft-primary text-primary border border-primary-subtle fs-10 rounded-pill px-2 py-0.5 fw-semibold ms-1">' . e(__('production.bom_finished_product')) . '</span>';
            }
            if ($isAltInfo) {
                $altLabel = __('production.bom_alt_info_row');
                if ($isAltOp && !empty($node['operation_number'])) {
                    $altLabel .= ' · ' . $node['operation_number'];
                }
                echo '<span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 ms-1">' . e($altLabel) . '</span>';
            }
            echo '</div>';
            echo '<span class="text-muted fs-11">' . e($node['product_sku']) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '</td>';

            // 2. Linked Sub-BOM (Clickable Green Box)
            echo '<td class="align-middle">';
            if ($hasSubBom) {
                $bomTitle = $node['bom_name'] ?: $node['bom_number'];
                $bomRoute = route('production.boms.show', $node['bom_id']);
                echo '<a href="' . $bomRoute . '" class="sub-bom-link-box" title="View Sub-BOM Details">';
                echo '<i class="feather-link fs-11 flex-shrink-0"></i>';
                echo '<span>Sub-BOM: <strong>' . e($bomTitle) . '</strong></span>';
                if (!empty($bomVersion)) {
                    echo '<span class="badge bg-success text-white fs-10 px-1.5 py-0.2 ms-1 rounded-1">v' . e($bomVersion) . '</span>';
                }
                echo '</a>';
                if (!empty($node['bom_number']) && !empty($node['bom_name']) && $node['bom_name'] !== $node['bom_number']) {
                    echo '<span class="text-muted fs-10 d-block mt-0.5 ps-1">' . e($node['bom_number']) . '</span>';
                }
            } else {
                echo '<span class="text-muted fs-12 opacity-50">—</span>';
            }
            echo '</td>';

            // 3. BOM Base Qty
            echo '<td class="text-end align-middle">';
            if ($hasSubBom) {
                echo '<span class="text-secondary fw-semibold fs-12">' . format_decimal($node['bom_base_quantity'] ?? 1.0) . '</span>';
            } else {
                echo '<span class="text-muted fs-12 opacity-50">—</span>';
            }
            echo '</td>';

            // 4. Qty Required for Production
            echo '<td class="text-end fw-bold text-dark align-middle fs-13">';
            echo format_decimal($qty);
            echo '</td>';

            // 5. UOM
            echo '<td class="text-center align-middle">';
            echo '<span class="badge bg-light text-secondary border fs-11 px-2 py-0.5 rounded-1 fw-medium">' . e($uom) . '</span>';
            echo '</td>';

            // 6. Version
            echo '<td class="text-center align-middle">';
            if ($hasSubBom && $bomVersion) {
                echo '<span class="badge bg-light text-dark border fs-11 px-1.5 py-0.5 rounded-1 fw-medium">v' . e($bomVersion) . '</span>';
            } else {
                echo '<span class="text-muted fs-12 opacity-50">—</span>';
            }
            echo '</td>';

            // 7. Lifecycle Status
            echo '<td class="text-center align-middle">';
            if ($hasSubBom) {
                $st = strtolower($node['bom_status'] ?? 'approved');
                if ($st === 'approved' || $st === 'active') {
                    echo '<span class="badge bg-soft-success text-success border border-success-subtle fs-11 px-2 py-0.5 rounded-1">' . __('production.active') . '</span>';
                } elseif ($st === 'cancelled') {
                    echo '<span class="badge bg-soft-danger text-danger border border-danger-subtle fs-11 px-2 py-0.5 rounded-1">Cancelled</span>';
                } elseif (in_array($st, ['inactive', 'superseded'])) {
                    echo '<span class="badge bg-soft-secondary text-secondary border fs-11 px-2 py-0.5 rounded-1">' . ucfirst($st) . '</span>';
                } else {
                    echo '<span class="badge bg-soft-warning text-warning border fs-11 px-2 py-0.5 rounded-1">' . ucfirst($st) . '</span>';
                }
                if ($level > 1 && $st === 'scheduled') {
                    echo '<div class="fs-10 text-warning mt-0.5">' . e(__('production.bom_sub_bom_scheduled_warning', ['date' => $node['bom_effective_date'] ?? ''])) . '</div>';
                } elseif ($level > 1 && in_array($st, ['inactive', 'cancelled', 'superseded'], true)) {
                    echo '<div class="fs-10 text-danger mt-0.5">' . e(__('production.bom_sub_bom_inactive_warning', ['status' => $st])) . '</div>';
                }
            } else {
                echo '<span class="text-muted fs-12 opacity-50">—</span>';
            }
            echo '</td>';
            echo '</tr>';

            if (!empty($node['children'])) {
                foreach ($node['children'] as $childIdx => $child) {
                    renderExplosionTableRows($child, $level + 1, $nodeId . '-' . $childIdx, $nodeId);
                }
            }
        }
    }
@endphp

@if(!empty($explosion['tree']))
    @php renderExplosionTableRows($explosion['tree']) @endphp
@else
    <tr>
        <td colspan="7" class="text-center py-4 text-muted">{{ __('production.no_components_spec_yet') }}</td>
    </tr>
@endif
