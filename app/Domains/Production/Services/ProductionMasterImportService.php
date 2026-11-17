<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Models\SectionManualProduction;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class ProductionMasterImportService
{
    /**
     * Parse and validate uploaded import file for master resources.
     * Returns structured preview rows and validation error counts.
     */
    public function parseAndValidate(string $type, UploadedFile|string $file, int $tenantId): array
    {
        $arrays = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray, \Maatwebsite\Excel\Concerns\WithHeadingRow {
            public function array(array $array) {}
        }, $file);

        $rows = $arrays[0] ?? [];
        $previewRows = [];
        $errorCount = 0;

        if (empty($rows)) {
            return [
                'preview_rows' => [],
                'error_count' => 0,
                'valid_count' => 0,
                'empty' => true,
            ];
        }

        if ($type === 'work-centers') {
            foreach ($rows as $index => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }

                $rowNum = $index + 2;
                $rowErrors = [];

                $code = trim($row['code'] ?? '');
                $name = trim($row['name'] ?? '');
                $capacity = trim($row['capacity_hours_per_day'] ?? '');
                $efficiency = trim($row['efficiency_percentage'] ?? '');
                $activeVal = strtolower(trim($row['active'] ?? ''));

                if (empty($code)) {
                    $rowErrors[] = "Code is required.";
                } elseif (strlen($code) > 50) {
                    $rowErrors[] = "Code cannot exceed 50 characters.";
                }

                if (empty($name)) {
                    $rowErrors[] = "Name is required.";
                }

                if ($capacity !== '' && (!is_numeric($capacity) || $capacity < 0 || $capacity > 24)) {
                    $rowErrors[] = "Capacity must be a number between 0 and 24.";
                }

                if ($efficiency !== '' && (!is_numeric($efficiency) || $efficiency < 0 || $efficiency > 100)) {
                    $rowErrors[] = "Efficiency must be a number between 0 and 100.";
                }

                $active = in_array($activeVal, ['yes', '1', 'true', 'active']);

                $previewRows[] = [
                    'row_number' => $rowNum,
                    'key' => $code ?: 'Row ' . $rowNum,
                    'valid' => empty($rowErrors),
                    'errors' => $rowErrors,
                    'data' => [
                        'code' => $code,
                        'name' => $name,
                        'capacity_hours_per_day' => $capacity ?: 8.0,
                        'efficiency_percentage' => $efficiency !== '' ? $efficiency : 100.0,
                        'status' => $active ? 'active' : 'inactive'
                    ]
                ];

                if (!empty($rowErrors)) {
                    $errorCount++;
                }
            }
        } elseif ($type === 'machines') {
            $workCenters = WorkCenter::where('tenant_id', $tenantId)->pluck('id', 'code')->toArray();

            foreach ($rows as $index => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }

                $rowNum = $index + 2;
                $rowErrors = [];

                $code = trim($row['code'] ?? '');
                $name = trim($row['name'] ?? '');
                $wcCode = trim($row['work_center_code'] ?? '');
                $cost = trim($row['hourly_cost'] ?? '');
                $status = strtolower(trim($row['status'] ?? 'active'));

                if (empty($code)) {
                    $rowErrors[] = "Code is required.";
                }

                if (empty($name)) {
                    $rowErrors[] = "Name is required.";
                }

                if (empty($wcCode)) {
                    $rowErrors[] = "Work Center Code is required.";
                } elseif (!isset($workCenters[$wcCode])) {
                    $rowErrors[] = "Work Center Code '{$wcCode}' does not exist in current tenant.";
                }

                if ($cost !== '' && (!is_numeric($cost) || $cost < 0)) {
                    $rowErrors[] = "Hourly cost must be a positive number.";
                }

                if (!in_array($status, ['active', 'inactive', 'maintenance'])) {
                    $rowErrors[] = "Status must be 'active', 'inactive', or 'maintenance'.";
                }

                $previewRows[] = [
                    'row_number' => $rowNum,
                    'key' => $code ?: 'Row ' . $rowNum,
                    'valid' => empty($rowErrors),
                    'errors' => $rowErrors,
                    'data' => [
                        'code' => $code,
                        'name' => $name,
                        'work_center_code' => $wcCode,
                        'work_center_id' => $workCenters[$wcCode] ?? null,
                        'hourly_cost' => $cost !== '' ? $cost : 0.0,
                        'status' => $status
                    ]
                ];

                if (!empty($rowErrors)) {
                    $errorCount++;
                }
            }
        } elseif ($type === 'boms') {
            $grouped = [];
            foreach ($rows as $index => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }
                $bomNumber = trim($row['bom_number'] ?? '');
                if (empty($bomNumber)) {
                    continue;
                }
                $grouped[$bomNumber][] = [
                    'row_number' => $index + 2,
                    'data' => $row
                ];
            }

            $products = Product::where('tenant_id', $tenantId)->pluck('id', 'sku')->toArray();
            $uoms = Uom::where('tenant_id', $tenantId)->pluck('id', 'code')->toArray();
            $routingsByNumber = Routing::where('tenant_id', $tenantId)
                ->with('operations')
                ->get()
                ->keyBy('routing_number');

            $bomsInFile = [];
            foreach ($grouped as $bomNumber => $group) {
                $firstRow = $group[0]['data'];
                $productCode = trim($firstRow['product_code'] ?? '');
                $bomsInFile[$productCode] = [
                    'bom_number' => $bomNumber,
                    'items' => array_map(fn($item) => $item['data'], $group)
                ];
            }

            foreach ($grouped as $bomNumber => $group) {
                $rowErrors = [];
                $firstRow = $group[0]['data'];
                $rowNum = $group[0]['row_number'];

                $bomName = trim($firstRow['bom_name'] ?? '');
                $productSku = trim($firstRow['product_code'] ?? '');
                $routingNumber = trim($firstRow['routing_number'] ?? '');
                $baseQty = trim($firstRow['base_quantity'] ?? '1');
                $baseUomCode = trim($firstRow['base_uom_code'] ?? '');
                $version = trim($firstRow['version'] ?? '1.0.0');
                $bomType = strtolower(trim($firstRow['bom_type'] ?? 'manufacturing'));
                $usageContext = strtolower(trim($firstRow['usage_context'] ?? 'manufacturing'));
                $effectiveDateVal = trim($firstRow['effective_date'] ?? '');
                $expiryDateVal = trim($firstRow['expiry_date'] ?? '');

                if (empty($bomName)) {
                    $rowErrors[] = "BOM Name is required on the first row of BOM '{$bomNumber}'.";
                }

                $routing = null;
                if (empty($routingNumber)) {
                    $rowErrors[] = "Routing Number is required on the first row of BOM '{$bomNumber}'.";
                } elseif (!$routingsByNumber->has($routingNumber)) {
                    $rowErrors[] = "Routing Number '{$routingNumber}' does not exist.";
                } else {
                    $routing = $routingsByNumber->get($routingNumber);
                    if (!in_array($routing->status, [Routing::STATUS_ACTIVE, Routing::STATUS_SCHEDULED, 'approved'], true)) {
                        $rowErrors[] = "Routing '{$routingNumber}' has status '{$routing->status}'; only active or scheduled routings can be linked.";
                    }
                }

                $productId = null;
                if (empty($productSku)) {
                    $rowErrors[] = "Product Code is required.";
                } elseif (!isset($products[$productSku])) {
                    $rowErrors[] = "Finished/semi-finished Product Code '{$productSku}' does not exist.";
                } else {
                    $productId = $products[$productSku];
                }

                if (!is_numeric($baseQty) || $baseQty <= 0) {
                    $rowErrors[] = "Base quantity must be a positive number.";
                }

                $baseUomId = null;
                if (empty($baseUomCode)) {
                    $rowErrors[] = "Base UOM Code is required.";
                } elseif (!isset($uoms[$baseUomCode])) {
                    $rowErrors[] = "UOM Code '{$baseUomCode}' does not exist.";
                } else {
                    $baseUomId = $uoms[$baseUomCode];
                }

                $allowedBomTypes = ['manufacturing', 'engineering', 'sales', 'phantom', 'subcontracting'];
                if (!in_array($bomType, $allowedBomTypes, true)) {
                    $rowErrors[] = "BOM Type must be one of: " . implode(', ', $allowedBomTypes) . ".";
                }

                $effectiveDate = null;
                if (!empty($effectiveDateVal)) {
                    try {
                        $effectiveDate = Carbon::parse($effectiveDateVal);
                    } catch (\Exception $e) {
                        $rowErrors[] = "Effective Date is invalid.";
                    }
                } else {
                    $effectiveDate = now();
                }

                $expiryDate = null;
                if (!empty($expiryDateVal)) {
                    try {
                        $expiryDate = Carbon::parse($expiryDateVal);
                    } catch (\Exception $e) {
                        $rowErrors[] = "Expiry Date is invalid.";
                    }
                }

                $items = [];
                foreach ($group as $itemRow) {
                    $itemData = $itemRow['data'];
                    $itemRowNum = $itemRow['row_number'];

                    $opSeqRaw = trim((string)($itemData['operation_sequence'] ?? $itemData['operation_number'] ?? ''));
                    $compSku = trim((string)($itemData['component_code'] ?? ''));
                    $itemQty = trim((string)($itemData['item_quantity'] ?? ''));
                    $itemUomCode = trim((string)($itemData['item_uom_code'] ?? ''));
                    $scrap = trim((string)($itemData['material_scrap_percentage'] ?? '0'));
                    $isAltRaw = strtolower(trim((string)($itemData['is_alternative'] ?? '0')));
                    $isAlternative = in_array($isAltRaw, ['1', 'true', 'yes', 'y'], true);
                    $alternativeOf = trim((string)($itemData['alternative_of'] ?? ''));
                    $priorityRaw = trim((string)($itemData['priority'] ?? ''));
                    $childBomNum = trim((string)($itemData['child_bom_number'] ?? ''));
                    $notes = trim((string)($itemData['notes'] ?? '')) ?: null;

                    if (empty($compSku) && empty($itemQty)) {
                        continue;
                    }

                    $itemErrors = [];
                    $routingOperationId = null;
                    if ($routing) {
                        if ($opSeqRaw === '') {
                            $itemErrors[] = "Row {$itemRowNum}: Operation Sequence is required.";
                        } else {
                            $matchedOp = $routing->operations->first(function ($op) use ($opSeqRaw) {
                                return (string)$op->sequence === $opSeqRaw
                                    || (string)$op->operation_number === $opSeqRaw;
                            });
                            if (!$matchedOp) {
                                $itemErrors[] = "Row {$itemRowNum}: Operation Sequence '{$opSeqRaw}' does not exist on Routing '{$routingNumber}'.";
                            } else {
                                $routingOperationId = $matchedOp->id;
                            }
                        }
                    }

                    $compProductId = null;
                    if (empty($compSku)) {
                        $itemErrors[] = "Row {$itemRowNum}: Component product code is required.";
                    } elseif (!isset($products[$compSku])) {
                        $itemErrors[] = "Row {$itemRowNum}: Component product '{$compSku}' does not exist.";
                    } else {
                        $compProductId = $products[$compSku];
                    }

                    if (!is_numeric($itemQty) || $itemQty <= 0) {
                        $itemErrors[] = "Row {$itemRowNum}: Item quantity must be a positive number.";
                    }

                    $itemUomId = null;
                    if (empty($itemUomCode)) {
                        $itemErrors[] = "Row {$itemRowNum}: Item UOM Code is required.";
                    } elseif (!isset($uoms[$itemUomCode])) {
                        $itemErrors[] = "Row {$itemRowNum}: Item UOM Code '{$itemUomCode}' does not exist.";
                    } else {
                        $itemUomId = $uoms[$itemUomCode];
                    }

                    if ($scrap !== '' && (!is_numeric($scrap) || $scrap < 0 || $scrap > 100)) {
                        $itemErrors[] = "Row {$itemRowNum}: Scrap percentage must be a number between 0 and 100.";
                    }

                    if ($priorityRaw !== '' && (!ctype_digit($priorityRaw) || (int)$priorityRaw < 1)) {
                        $itemErrors[] = "Row {$itemRowNum}: Priority must be a positive integer.";
                    }

                    if ($isAlternative && $alternativeOf === '') {
                        $itemErrors[] = "Row {$itemRowNum}: 'Alternative Of' primary Component Code is required for alternative items.";
                    }

                    if (!empty($itemErrors)) {
                        $rowErrors = array_merge($rowErrors, $itemErrors);
                    } else {
                        $items[] = [
                            'row_number'                => $itemRowNum,
                            'routing_operation_id'      => $routingOperationId,
                            'operation_sequence'        => $opSeqRaw,
                            'component_code'            => $compSku,
                            'material_id'               => $compProductId,
                            'quantity'                  => (float)$itemQty,
                            'item_uom_code'             => $itemUomCode,
                            'uom_id'                    => $itemUomId,
                            'material_scrap_percentage' => $scrap !== '' ? (float)$scrap : 0.0,
                            'is_alternative'            => $isAlternative,
                            'alternative_of'            => $alternativeOf,
                            'priority'                  => $priorityRaw !== '' ? (int)$priorityRaw : null,
                            'child_bom_number'          => $childBomNum,
                            'notes'                     => $notes,
                        ];
                    }
                }

                // Resolve alternative_of -> replaces_item_id (0-based index of primary on same operation)
                if (!empty($items)) {
                    $hasPrimary = collect($items)->contains(fn ($it) => !$it['is_alternative']);
                    if (!$hasPrimary) {
                        $rowErrors[] = "BOM must contain at least one primary component item.";
                    }

                    foreach ($items as $idx => &$itemRef) {
                        if (!$itemRef['is_alternative']) {
                            continue;
                        }
                        $primaryIdx = null;
                        foreach ($items as $pIdx => $candidate) {
                            if (!$candidate['is_alternative']
                                && $candidate['component_code'] === $itemRef['alternative_of']
                                && $candidate['routing_operation_id'] === $itemRef['routing_operation_id']
                            ) {
                                $primaryIdx = $pIdx;
                                break;
                            }
                        }
                        if ($primaryIdx === null) {
                            $rowErrors[] = "Row {$itemRef['row_number']}: Alternative '{$itemRef['component_code']}' could not find primary '{$itemRef['alternative_of']}' on operation '{$itemRef['operation_sequence']}'.";
                        } else {
                            $itemRef['replaces_item_id'] = $primaryIdx;
                        }
                    }
                    unset($itemRef);
                } else {
                    $rowErrors[] = "BOM must contain at least one valid component item.";
                }

                if ($productSku && empty($rowErrors)) {
                    $cycleError = $this->checkCircularDependency($productSku, $items, $bomsInFile, $tenantId);
                    if ($cycleError) {
                        $rowErrors[] = $cycleError;
                    }
                }

                // BM-IMPL-18 #2–#3: existing BOMs are updated only while draft; then the SAME rules as the form / API
                if (empty($rowErrors)) {
                    $existingBom = ProductionBom::where('tenant_id', $tenantId)->where('bom_number', $bomNumber)->first();
                    if ($existingBom && !$existingBom->isDraft()) {
                        $rowErrors[] = "BOM '{$bomNumber}' is {$existingBom->status}. Only draft BOMs can be updated by import.";
                    } else {
                        $payload = [
                            'bom_number' => $bomNumber, 'bom_name' => $bomName, 'product_id' => $productId,
                            'routing_id' => $routing?->id, 'base_quantity' => $baseQty, 'base_uom_id' => $baseUomId,
                            'version' => $version, 'bom_type' => $bomType, 'usage_context' => $usageContext,
                            'effective_date' => $effectiveDate ? $effectiveDate->toDateString() : null,
                            'expiry_date' => $expiryDate ? $expiryDate->toDateString() : null,
                            'items' => $this->resolveBomImportItems($items, $routing?->id, $routingNumber, $tenantId),
                        ];
                        foreach (\App\Domains\Production\Requests\ImportBomRowValidation::errorsFor($payload, $existingBom) as $err) {
                            $rowErrors[] = "BOM '{$bomNumber}': {$err}";
                        }
                    }
                }

                $previewRows[] = [
                    'row_number' => $rowNum,
                    'key' => $bomNumber,
                    'valid' => empty($rowErrors),
                    'errors' => $rowErrors,
                    'data' => [
                        'bom_number' => $bomNumber,
                        'bom_name' => $bomName,
                        'product_code' => $productSku,
                        'product_id' => $productId,
                        'routing_number' => $routingNumber,
                        'routing_id' => $routing?->id,
                        'base_quantity' => $baseQty,
                        'base_uom_id' => $baseUomId,
                        'version' => $version,
                        'bom_type' => $bomType,
                        'usage_context' => $usageContext,
                        'effective_date' => $effectiveDate ? $effectiveDate->toDateString() : null,
                        'expiry_date' => $expiryDate ? $expiryDate->toDateString() : null,
                        'items' => $items
                    ]
                ];

                if (!empty($rowErrors)) {
                    $errorCount++;
                }
            }
        } elseif ($type === 'routings') {
            $grouped = [];
            foreach ($rows as $index => $row) {
                if (empty(array_filter($row))) {
                    continue;
                }
                $routingCode = trim($row['routing_code'] ?? '');
                if (empty($routingCode)) {
                    continue;
                }
                $grouped[$routingCode][] = [
                    'row_number' => $index + 2,
                    'data' => $row
                ];
            }

            $products = Product::where('tenant_id', $tenantId)->pluck('id', 'sku')->toArray();
            $workCenters = WorkCenter::where('tenant_id', $tenantId)->pluck('id', 'code')->toArray();
            $machines = Machine::where('tenant_id', $tenantId)->with(['workCenter', 'section'])->get();
            $sections = WorkCenterSection::where('tenant_id', $tenantId)->get();
            $manualBatches = SectionManualProduction::where('tenant_id', $tenantId)->get();
            $uoms = Uom::where('tenant_id', $tenantId)->pluck('id', 'code')->toArray();

            foreach ($grouped as $routingCode => $group) {
                $rowErrors = [];
                $firstRow = $group[0]['data'];
                $rowNum = $group[0]['row_number'];

                $routingName = trim($firstRow['routing_name'] ?? '');
                $productSku = trim($firstRow['product_code'] ?? '');
                $version = trim($firstRow['version'] ?? '1.0.0');

                if (empty($routingName)) {
                    $rowErrors[] = "Routing Name is required on the first row of routing '{$routingCode}'.";
                }

                // [INVERSION-DECOUPLE-TRACK-11] Routings are independent of products. Product code is optional/ignored.
                $productId = null;
                if (!empty($productSku) && isset($products[$productSku])) {
                    $productId = $products[$productSku];
                }

                $operations = [];
                $opsGrouped = [];

                foreach ($group as $opRow) {
                    $opData = $opRow['data'];
                    $opRowNum = $opRow['row_number'];

                    $seq = trim($opData['operation_sequence'] ?? '');
                    $opName = trim($opData['operation_name'] ?? '');
                    $opNumber = trim($opData['operation_code'] ?? '');
                    $opType = strtolower(trim($opData['operation_type'] ?? 'manufacturing'));
                    $wcCode = trim($opData['work_center_code'] ?? '');
                    $secCode = trim($opData['section_code'] ?? '');
                    $mchCode = trim($opData['machine_code'] ?? '');
                    $batchName = trim($opData['manual_batch_name'] ?? '');
                    $isAlt = in_array(strtolower(trim($opData['is_alternative'] ?? 'no')), ['yes', '1', 'true']);
                    $replacesOpNum = trim($opData['replaces_operation_number'] ?? '');
                    $priority = !empty($opData['priority']) ? (int) $opData['priority'] : 1;
                    $setupTime = trim($opData['setup_time_minutes'] ?? '0');
                    $setupPerPiece = trim((string) ($opData['setup_time_per_piece_minutes'] ?? '')); // RT-IMPL-13
                    $procTime = trim($opData['processing_time_minutes'] ?? '0');
                    $yield = trim($opData['yield_percentage'] ?? '100');
                    $isExt = strtolower(trim($opData['is_external'] ?? 'no'));
                    $vendorCode = trim((string) ($opData['vendor_code'] ?? ''));               // RT-IMPL-16: external operations
                    $subcontractCost = trim((string) ($opData['subcontract_cost_per_unit'] ?? ''));

                    $matCode = trim($opData['material_code'] ?? '');
                    $matQty = trim($opData['material_quantity'] ?? '');

                    if (empty($seq) && empty($opNumber) && empty($opName) && empty($matCode)) {
                        continue;
                    }

                    if (!empty($seq)) {
                        $opsGrouped[$opNumber] = [
                            'sequence' => $seq,
                            'name' => $opName,
                            'operation_number' => $opNumber,
                            'operation_type' => $opType,
                            'work_center_code' => $wcCode,
                            'section_code' => $secCode,
                            'machine_code' => $mchCode,
                            'manual_batch_name' => $batchName,
                            'is_alternative' => $isAlt,
                            'replaces_operation_number' => $replacesOpNum,
                            'priority' => $priority,
                            'setup_time_minutes' => $setupTime,
                            'setup_time_per_piece_minutes' => $setupPerPiece === '' ? null : $setupPerPiece,
                            'processing_time_minutes' => $procTime,
                            'expected_yield_percentage' => $yield,
                            'is_external' => in_array($isExt, ['yes', '1', 'true', 'external']),
                            'vendor_code' => $vendorCode,
                            'subcontract_cost_per_unit' => $subcontractCost === '' ? 0 : $subcontractCost,
                            'materials' => []
                        ];
                    }

                    if (!empty($matCode) && !empty($opNumber)) {
                        $matErrors = [];
                        $matProductId = null;
                        if (!isset($products[$matCode])) {
                            $matErrors[] = "Row {$opRowNum}: Material Code '{$matCode}' does not exist.";
                        } else {
                            $matProductId = $products[$matCode];
                        }

                        if (!is_numeric($matQty) || $matQty <= 0) {
                            $matErrors[] = "Row {$opRowNum}: Material quantity must be positive.";
                        }

                        if (!empty($matErrors)) {
                            $rowErrors = array_merge($rowErrors, $matErrors);
                        } elseif (isset($opsGrouped[$opNumber])) {
                            $prod = Product::find($matProductId);
                            $opsGrouped[$opNumber]['materials'][] = [
                                'material_id' => $matProductId,
                                'quantity' => $matQty,
                                'uom_id' => $prod ? $prod->uom_id : null
                            ];
                        }
                    }
                }

                $seenSequences = [];
                foreach ($opsGrouped as $opNumber => &$op) {
                    $opErrors = [];

                    if (!is_numeric($op['sequence']) || $op['sequence'] <= 0) {
                        $opErrors[] = "Operation sequence for '{$opNumber}' must be a positive integer.";
                    } elseif (isset($seenSequences[$op['sequence']])) {
                        $opErrors[] = "Duplicate operation sequence '{$op['sequence']}' found in operation '{$opNumber}' (conflicts with '{$seenSequences[$op['sequence']]}'). Operation sequences within a routing must be unique.";
                    } else {
                        $seenSequences[$op['sequence']] = $opNumber;
                    }

                    if (empty($op['name'])) {
                        $opErrors[] = "Operation name is required for '{$opNumber}'.";
                    }

                    if (!in_array($op['operation_type'], ['manufacturing', 'inspection', 'outsourcing', 'transport', 'maintenance'])) {
                        $opErrors[] = "Operation type '{$op['operation_type']}' for '{$opNumber}' is invalid.";
                    }

                    $isExternal = $op['is_external'];
                    $wcId = null;
                    $secId = null;
                    $mchId = null;
                    $batchId = null;

                    if (!$isExternal) {
                        if (empty($op['work_center_code'])) {
                            $opErrors[] = "Work Center Code is required for internal operation '{$opNumber}'.";
                        } elseif (!isset($workCenters[$op['work_center_code']])) {
                            $opErrors[] = "Work Center '{$op['work_center_code']}' does not exist.";
                        } else {
                            $wcId = $workCenters[$op['work_center_code']];
                        }

                        if (empty($op['section_code'])) {
                            $opErrors[] = "Section Code is required for internal operation '{$opNumber}'."; // RT-IMPL-16 (RT-06)
                        } else {
                            $sec = $sections->firstWhere('code', $op['section_code']);
                            if (!$sec) {
                                $opErrors[] = "Section '{$op['section_code']}' does not exist.";
                            } elseif ($wcId && $sec->work_center_id !== $wcId) {
                                $opErrors[] = "Section '{$op['section_code']}' does not belong to Work Center '{$op['work_center_code']}'.";
                            } else {
                                $secId = $sec->id;
                            }
                        }

                        // Mutual exclusivity: Machine XOR Manual Batch
                        $hasMachine = !empty($op['machine_code']);
                        $hasBatch = !empty($op['manual_batch_name']);

                        if (!$hasMachine && !$hasBatch) {
                            $opErrors[] = "Operation '{$opNumber}' must specify a Machine or a Manual Batch."; // RT-IMPL-16 (RT-07)
                        } elseif ($hasMachine && $hasBatch) {
                            $opErrors[] = "Operation '{$opNumber}' cannot specify both a Machine and a Manual Batch.";
                        } elseif ($hasMachine) {
                            $mach = $machines->firstWhere('code', $op['machine_code']);
                            if (!$mach) {
                                $opErrors[] = "Machine '{$op['machine_code']}' does not exist.";
                            } else {
                                $mchId = $mach->id;
                                if ($secId && $mach->section_id !== $secId) {
                                    $opErrors[] = "Machine '{$op['machine_code']}' does not belong to Section '{$op['section_code']}'.";
                                }
                            }
                        } elseif ($hasBatch) {
                            $batch = $manualBatches->first(fn ($b) => $b->batch_name === $op['manual_batch_name'] && (!$secId || $b->section_id === $secId));
                            if (!$batch) {
                                $opErrors[] = "Manual Batch '{$op['manual_batch_name']}' does not exist in Section '{$op['section_code']}'.";
                            } else {
                                $batchId = $batch->id;
                            }
                        }
                    }

                    $vendorId = null;
                    if ($isExternal) {
                        if ($op['vendor_code'] === '') {
                            $opErrors[] = "Vendor Code is required for outsourced operation '{$opNumber}'.";
                        } else {
                            $vendorId = \App\Domains\Inventory\Models\Vendor::where('tenant_id', $tenantId)->where('code', $op['vendor_code'])->value('id');
                            if (!$vendorId) {
                                $opErrors[] = "Vendor '{$op['vendor_code']}' does not exist.";
                            }
                        }
                    }

                    if ($op['is_alternative']) {
                        if (empty($op['replaces_operation_number'])) {
                            $opErrors[] = "Alternative operation '{$opNumber}' must specify 'replaces_operation_number'.";
                        } elseif (!isset($opsGrouped[$op['replaces_operation_number']])) {
                            $opErrors[] = "Alternative operation '{$opNumber}' references non-existent operation '{$op['replaces_operation_number']}'.";
                        } elseif ($op['priority'] < 1) {
                            $opErrors[] = "Priority for alternative operation '{$opNumber}' must be at least 1.";
                        }
                    }

                    if (!is_numeric($op['setup_time_minutes']) || $op['setup_time_minutes'] < 0) {
                        $opErrors[] = "Setup time must be a non-negative number.";
                    }

                    if (!is_numeric($op['processing_time_minutes']) || $op['processing_time_minutes'] < 0) {
                        $opErrors[] = "Processing time must be a non-negative number.";
                    }

                    if (!is_numeric($op['expected_yield_percentage']) || $op['expected_yield_percentage'] < 0 || $op['expected_yield_percentage'] > 100) {
                        $opErrors[] = "Expected yield must be between 0 and 100.";
                    }

                    if (!empty($opErrors)) {
                        $rowErrors = array_merge($rowErrors, $opErrors);
                    } else {
                        $op['work_center_id'] = $wcId;
                        $op['section_id'] = $secId;
                        $op['machine_id'] = $mchId;
                        $op['section_manual_production_id'] = $batchId;
                        $op['vendor_id'] = $vendorId;
                        $operations[] = $op;
                    }
                }

                if (empty($operations)) {
                    $rowErrors[] = "Routing must contain at least one valid operation.";
                }

                // RT-IMPL-16 (RT-27 rule 8): an existing routing is updated by import only while it is a draft
                $existingRouting = Routing::where('tenant_id', $tenantId)->where('routing_number', $routingCode)->first();
                if ($existingRouting && !$existingRouting->isDraft()) {
                    $rowErrors[] = "Routing '{$routingCode}' is {$existingRouting->status}. Only draft routings can be updated by import.";
                }

                // RT-IMPL-16 (RT-27 rule 6–7): figures worked out by the backend from the latest section snapshot
                // (same calculator as the form), then the SAME operation checks as the web form / API
                if (empty($rowErrors) && !empty($operations)) {
                    $payloadOps = $this->routingImportPayload($operations, $rowErrors);
                    if (empty($rowErrors)) {
                        $rowErrors = app(RoutingImportValidator::class)->validate($payloadOps);
                    }
                    if (empty($rowErrors)) {
                        $operations = $payloadOps;
                    }
                }

                $previewRows[] = [
                    'row_number' => $rowNum,
                    'key' => $routingCode,
                    'valid' => empty($rowErrors),
                    'errors' => $rowErrors,
                    'data' => [
                        'routing_number' => $routingCode,
                        'name' => $routingName,
                        'product_id' => $productId,
                        'product_code' => $productSku,
                        'version' => $version,
                        'operations' => $operations
                    ]
                ];

                if (!empty($rowErrors)) {
                    $errorCount++;
                }
            }
        } else {
            throw new InvalidArgumentException("Unsupported import master type: {$type}");
        }

        $validCount = collect($previewRows)->where('valid', true)->count();

        return [
            'preview_rows' => $previewRows,
            'error_count' => $errorCount,
            'valid_count' => $validCount,
            'empty' => false,
        ];
    }

    /**
     * Commit valid preview rows to database within a transaction.
     */
    public function commit(string $type, array $validRows, string $strategy, int $tenantId, ?int $userId = null): array
    {
        $successCount = 0;
        $failedCount = 0;

        DB::beginTransaction();
        try {
            if ($type === 'work-centers') {
                foreach ($validRows as $row) {
                    $data = $row['data'];
                    $wc = WorkCenter::where('tenant_id', $tenantId)->where('code', $data['code'])->first();

                    if ($wc) {
                        if ($strategy === 'update') {
                            $wc->update([
                                'name' => $data['name'],
                                'capacity_hours_per_day' => $data['capacity_hours_per_day'],
                                'efficiency_percentage' => $data['efficiency_percentage'],
                                'status' => $data['status']
                            ]);
                            $successCount++;
                        } else {
                            $failedCount++;
                        }
                    } else {
                        WorkCenter::create([
                            'tenant_id' => $tenantId,
                            'code' => $data['code'],
                            'name' => $data['name'],
                            'capacity_hours_per_day' => $data['capacity_hours_per_day'],
                            'efficiency_percentage' => $data['efficiency_percentage'],
                            'status' => $data['status']
                        ]);
                        $successCount++;
                    }
                }
            } elseif ($type === 'machines') {
                foreach ($validRows as $row) {
                    $data = $row['data'];
                    $machine = Machine::where('tenant_id', $tenantId)->where('code', $data['code'])->first();

                    if ($machine) {
                        if ($strategy === 'update') {
                            $machine->update([
                                'name' => $data['name'],
                                'work_center_id' => $data['work_center_id'],
                                // Short Full Review Point 2: real column, converted ONCE (sheet is in the active currency)
                                'operating_cost_per_hour' => round(convert_to_base((float) $data['hourly_cost']), 4),
                                'status' => $data['status']
                            ]);
                            $successCount++;
                        } else {
                            $failedCount++;
                        }
                    } else {
                        Machine::create([
                            'tenant_id' => $tenantId,
                            'code' => $data['code'],
                            'name' => $data['name'],
                            'work_center_id' => $data['work_center_id'],
                            'operating_cost_per_hour' => round(convert_to_base((float) $data['hourly_cost']), 4),
                            'status' => $data['status']
                        ]);
                        $successCount++;
                    }
                }
            } elseif ($type === 'boms') {
                $bomService = app(ProductionBomService::class);
                foreach ($validRows as $row) {
                    $data = $row['data'];
                    $bom = ProductionBom::where('tenant_id', $tenantId)->where('bom_number', $data['bom_number'])->first();

                    if ($bom && $strategy !== 'update') {
                        $failedCount++;
                        continue;
                    }

                    if ($bom && !$bom->isDraft()) { // BM-IMPL-18 #3 (also reported at preview)
                        $failedCount++;
                        continue;
                    }

                    $routingId = $data['routing_id'] ?? (!empty($data['routing_number'])
                        ? Routing::where('tenant_id', $tenantId)->where('routing_number', $data['routing_number'])->value('id')
                        : null);
                    $resolvedItems = $this->resolveBomImportItems($data['items'], $routingId, $data['routing_number'] ?? null, $tenantId);

                    $payload = [
                        'bom_number'     => $data['bom_number'],
                        'bom_name'       => $data['bom_name'],
                        'product_id'     => $data['product_id'],
                        'routing_id'     => $routingId ?? $bom?->routing_id,
                        'base_quantity'  => $data['base_quantity'],
                        'base_uom_id'    => $data['base_uom_id'],
                        'version'        => $data['version'],
                        'bom_type'       => $data['bom_type'],
                        'usage_context'  => $data['usage_context'],
                        'effective_date' => $data['effective_date'],
                        'expiry_date'    => $data['expiry_date'],
                        'items'          => $resolvedItems,
                    ];

                    $dto = ProductionBomDTO::fromArray($payload);

                    if ($bom) {
                        $bomService->update($bom->id, $dto);
                    } else {
                        $bomService->create($dto);
                    }
                    $successCount++;
                }
            } elseif ($type === 'routings') {
                // RT-IMPL-16: create / update through RoutingService — same save as the form (figures saved as checked,
                // operations updated IN PLACE by operation number, removed ones soft-deleted, BOM allocations never deleted)
                $routingService = app(RoutingService::class);
                foreach ($validRows as $row) {
                    $data = $row['data'];
                    $routing = Routing::where('tenant_id', $tenantId)->where('routing_number', $data['routing_number'])->first();
                    $payload = [
                        'routing_number' => $data['routing_number'],
                        'name'           => $data['name'],
                        'version'        => $data['version'],
                        'operations'     => $data['operations'],
                    ];

                    if ($routing) {
                        if ($strategy === 'update' && $routing->isDraft()) {
                            $start = $routing->effective_from && $routing->effective_from->toDateString() >= now()->toDateString()
                                ? $routing->effective_from->toDateString() : now()->toDateString();
                            $routingService->update($routing->id, \App\Domains\Production\DTO\RoutingDTO::fromArray(array_merge($payload, [
                                'effective_from' => $start,
                                'effective_to'   => $routing->effective_to?->toDateString(),
                                'description'    => $routing->description,
                                'is_default'     => $routing->is_default,
                            ])));
                            $successCount++;
                        } else {
                            $failedCount++;
                        }
                    } else {
                        $routingService->create(\App\Domains\Production\DTO\RoutingDTO::fromArray($payload), $tenantId, $userId);
                        $successCount++;
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ];
    }

    /**
     * RT-IMPL-16: import rows → the create payload the form / API send, with every figure worked out from the latest
     * section snapshot by the one calculator (RT-IMPL-14). Setup times and yield come from the file; run time comes
     * from the machine / batch capacity (the file's processing time is not used). Alternatives link by sequence.
     *
     * @param  array<int, array>  $operations  parsed import operations
     * @param  array<int, string> $errors      filled with "section not calculated" / "resource not in snapshot" errors
     */
    private function routingImportPayload(array $operations, array &$errors): array
    {
        $calculator = app(RoutingOperationCostCalculator::class);
        $sectionService = app(SectionCalculationService::class);
        $seqByNumber = collect($operations)->pluck('sequence', 'operation_number');
        $snapshots = [];
        $payload = [];

        foreach ($operations as $op) {
            $row = [
                'sequence'                     => (int) $op['sequence'],
                'operation_number'             => $op['operation_number'],
                'name'                         => $op['name'],
                'operation_type'               => $op['operation_type'],
                'work_center_id'               => $op['work_center_id'],
                'section_id'                   => $op['section_id'],
                'machine_id'                   => $op['machine_id'],
                'section_manual_production_id' => $op['section_manual_production_id'],
                'is_alternative'               => (bool) $op['is_alternative'],
                'replaces_sequence'            => $op['is_alternative'] ? ($seqByNumber[$op['replaces_operation_number']] ?? null) : null,
                'priority'                     => (int) ($op['priority'] ?? 1),
                'setup_time_minutes'           => (float) $op['setup_time_minutes'],
                'setup_time_per_piece_minutes' => $op['setup_time_per_piece_minutes'] !== null ? (float) $op['setup_time_per_piece_minutes'] : null,
                'expected_yield_percentage'    => (float) $op['expected_yield_percentage'],
                'is_external'                  => (bool) $op['is_external'],
                'vendor_id'                    => $op['vendor_id'] ?? null,
                // Short Full Review Point 2: the sheet is in the active currency → DB base once, before the base calculator runs
                'subcontract_cost_per_unit'    => round(convert_to_base((float) ($op['subcontract_cost_per_unit'] ?? 0)), 4),
            ];

            $resources = null;
            if (!$row['is_external'] && $row['section_id']) {
                if (!array_key_exists($row['section_id'], $snapshots)) {
                    $section = WorkCenterSection::find($row['section_id']);
                    $snapshots[$row['section_id']] = $section ? $sectionService->getSnapshotResourceCosts($section) : null;
                }
                $resources = $snapshots[$row['section_id']];
                if (!$resources || !empty($resources['not_calculated'])) {
                    $code = WorkCenterSection::find($row['section_id'])?->code ?? $row['section_id'];
                    $errors[] = "Section {$code} has not been calculated yet. Recalculate the Work Center first.";
                    continue;
                }
            }

            $calc = $calculator->calculate($row, $resources);
            if ($calc['error'] !== null) {
                $errors[] = "Operation {$row['operation_number']}: the machine / batch is not in the latest section calculation (inactive or added after the last recalculation).";
                continue;
            }
            foreach ($calc['figures'] as $k => $v) {
                $row[$k] = round((float) $v, 4);
            }
            if ($resources) {
                $row['section_calculation_id'] = $resources['section_calculation_id'] ?? null;
                $row['section_snapshot_number'] = $resources['snapshot_number'] ?? null;
                $row['work_center_calculation_id'] = $resources['work_center_calculation_id'] ?? null;
            }
            $row['resource_snapshot'] = $calculator->buildResourceSnapshot($row, $calc['resource'], $resources); // DB base
            $payload[] = $row;
        }

        $errors = array_values(array_unique($errors));

        return $payload;
    }

    /**
     * BM-IMPL-18: import item rows → the allocation payload the BOM form / API send (operation by sequence or
     * operation number of the BOM's routing, alternative link by row, sub-BOM by number). Priority is not defaulted
     * (BM-IMPL-07 — the shared rules require it on alternatives).
     */
    private function resolveBomImportItems(array $items, ?int $routingId, ?string $routingNumber, int $tenantId): array
    {
        $routing = $routingId
            ? Routing::where('tenant_id', $tenantId)->with('operations')->find($routingId)
            : (!empty($routingNumber) ? Routing::where('tenant_id', $tenantId)->where('routing_number', $routingNumber)->with('operations')->first() : null);

        $resolved = [];
        foreach ($items as $idx => $item) {
            $childBomId = null;
            if (!empty($item['child_bom_number'])) {
                $childBomId = ProductionBom::where('tenant_id', $tenantId)->where('bom_number', $item['child_bom_number'])->value('id');
            }

            $routingOpId = $item['routing_operation_id'] ?? null;
            if (!$routingOpId && $routing && isset($item['operation_sequence'])) {
                $opSeq = (string) $item['operation_sequence'];
                $routingOpId = $routing->operations->first(fn ($op) => (string) $op->sequence === $opSeq || (string) $op->operation_number === $opSeq)?->id;
            }

            $isAlt = (bool) ($item['is_alternative'] ?? false);
            $resolved[] = [
                'row_uid'                   => 'imp_' . $idx,
                'routing_operation_id'      => $routingOpId,
                'material_id'               => $item['material_id'],
                'quantity'                  => $item['quantity'],
                'uom_id'                    => $item['uom_id'],
                'material_scrap_percentage' => $item['material_scrap_percentage'] ?? 0,
                'sequence'                  => ($idx + 1) * 10,
                'is_alternative'            => $isAlt,
                'replaces_row_uid'          => ($isAlt && isset($item['replaces_item_id']) && is_numeric($item['replaces_item_id'])) ? ('imp_' . (int) $item['replaces_item_id']) : null,
                'priority'                  => isset($item['priority']) && $item['priority'] !== '' ? (int) $item['priority'] : null,
                'child_bom_id'              => $childBomId,
                'notes'                     => $item['notes'] ?? null,
            ];
        }

        return $resolved;
    }

    /**
     * Direct import for API execution: parse, validate, and commit.
     */
    public function import(string $type, UploadedFile $file, string $strategy, int $tenantId, ?int $userId = null): array
    {
        $preview = $this->parseAndValidate($type, $file, $tenantId);

        if ($preview['empty']) {
            throw new InvalidArgumentException('The uploaded file is empty or could not be parsed.');
        }

        $validRows = collect($preview['preview_rows'])->where('valid', true)->all();
        $errorRows = collect($preview['preview_rows'])->filter(fn($r) => !$r['valid'])->values()->all();

        if (empty($validRows)) {
            return [
                'status' => 'failed',
                'total_rows' => count($preview['preview_rows']),
                'valid_rows' => 0,
                'error_rows' => count($errorRows),
                'success_count' => 0,
                'failed_count' => count($preview['preview_rows']),
                'errors' => array_map(fn($r) => [
                    'row' => $r['row_number'],
                    'key' => $r['key'],
                    'errors' => $r['errors'],
                ], $errorRows),
            ];
        }

        $commitResult = $this->commit($type, $validRows, $strategy, $tenantId, $userId);

        return [
            'status' => count($errorRows) > 0 ? 'partially_successful' : 'completed',
            'total_rows' => count($preview['preview_rows']),
            'valid_rows' => count($validRows),
            'error_rows' => count($errorRows),
            'success_count' => $commitResult['success_count'],
            'failed_count' => $commitResult['failed_count'],
            'errors' => array_map(fn($r) => [
                'row' => $r['row_number'],
                'key' => $r['key'],
                'errors' => $r['errors'],
            ], $errorRows),
        ];
    }

    /**
     * Circular dependency check utilizing DFS cycle detection.
     */
    private function checkCircularDependency(string $productSku, array $items, array $allBomsInFile, int $tenantId): ?string
    {
        $visited = [];

        $dfs = function (string $sku) use (&$dfs, &$visited, $allBomsInFile, $tenantId) {
            if (($visited[$sku] ?? 0) === 1) {
                return true;
            }
            if (($visited[$sku] ?? 0) === 2) {
                return false;
            }

            $visited[$sku] = 1;

            $components = [];
            if (isset($allBomsInFile[$sku])) {
                foreach ($allBomsInFile[$sku]['items'] as $item) {
                    if (!empty($item['component_code'])) {
                        $components[] = $item['component_code'];
                    }
                }
            } else {
                $existingBom = ProductionBom::where('tenant_id', $tenantId)
                    ->whereHas('product', function($q) use ($sku) {
                        $q->where('sku', $sku);
                    })
                    ->with(['operationMaterials.material', 'items.material'])
                    ->first();
                if ($existingBom) {
                    $existingRows = $existingBom->operationMaterials->isNotEmpty()
                        ? $existingBom->operationMaterials
                        : $existingBom->items;
                    foreach ($existingRows as $item) {
                        if ($item->material?->sku) {
                            $components[] = $item->material->sku;
                        }
                    }
                }
            }

            foreach ($components as $componentSku) {
                if ($dfs($componentSku)) {
                    return true;
                }
            }

            $visited[$sku] = 2;
            return false;
        };

        $visited[$productSku] = 1;

        foreach ($items as $item) {
            $compSku = $item['component_code'] ?? null;
            if ($compSku) {
                if ($compSku === $productSku) {
                    return "Circular dependency: A product cannot have itself as a component.";
                }
                if ($dfs($compSku)) {
                    return "Circular dependency detected: component '{$compSku}' transitively depends on product '{$productSku}'.";
                }
            }
        }

        return null;
    }
}
