<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
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

                if (!in_array($bomType, ['manufacturing', 'phantom'])) {
                    $rowErrors[] = "BOM Type must be 'manufacturing' or 'phantom'.";
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

                    $compSku = trim($itemData['component_code'] ?? '');
                    $itemQty = trim($itemData['item_quantity'] ?? '');
                    $itemUomCode = trim($itemData['item_uom_code'] ?? '');
                    $scrap = trim($itemData['material_scrap_percentage'] ?? '0');
                    $childBomNum = trim($itemData['child_bom_number'] ?? '');

                    if (empty($compSku) && empty($itemQty)) {
                        continue;
                    }

                    $itemErrors = [];
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

                    if (!empty($itemErrors)) {
                        $rowErrors = array_merge($rowErrors, $itemErrors);
                    } else {
                        $items[] = [
                            'component_code' => $compSku,
                            'material_id' => $compProductId,
                            'quantity' => $itemQty,
                            'item_uom_code' => $itemUomCode,
                            'uom_id' => $itemUomId,
                            'material_scrap_percentage' => $scrap ?: 0.0,
                            'child_bom_number' => $childBomNum
                        ];
                    }
                }

                if (empty($items)) {
                    $rowErrors[] = "BOM must contain at least one valid component item.";
                }

                if ($productSku && empty($rowErrors)) {
                    $cycleError = $this->checkCircularDependency($productSku, $items, $bomsInFile, $tenantId);
                    if ($cycleError) {
                        $rowErrors[] = $cycleError;
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
            $machines = Machine::where('tenant_id', $tenantId)->with('workCenter')->get();
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

                $productId = null;
                if (empty($productSku)) {
                    $rowErrors[] = "Product Code is required.";
                } elseif (!isset($products[$productSku])) {
                    $rowErrors[] = "Product Code '{$productSku}' does not exist.";
                } else {
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
                    $mchCode = trim($opData['machine_code'] ?? '');
                    $setupTime = trim($opData['setup_time_minutes'] ?? '0');
                    $procTime = trim($opData['processing_time_minutes'] ?? '0');
                    $yield = trim($opData['yield_percentage'] ?? '100');
                    $isExt = strtolower(trim($opData['is_external'] ?? 'no'));

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
                            'machine_code' => $mchCode,
                            'setup_time_minutes' => $setupTime,
                            'processing_time_minutes' => $procTime,
                            'expected_yield_percentage' => $yield,
                            'is_external' => in_array($isExt, ['yes', '1', 'true', 'external']),
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

                    $wcId = null;
                    if (empty($op['work_center_code'])) {
                        $opErrors[] = "Work Center Code is required for '{$opNumber}'.";
                    } elseif (!isset($workCenters[$op['work_center_code']])) {
                        $opErrors[] = "Work Center '{$op['work_center_code']}' does not exist.";
                    } else {
                        $wcId = $workCenters[$op['work_center_code']];
                    }

                    $mchId = null;
                    if (!empty($op['machine_code'])) {
                        $mach = $machines->firstWhere('code', $op['machine_code']);
                        if (!$mach) {
                            $opErrors[] = "Machine '{$op['machine_code']}' does not exist.";
                        } else {
                            $mchId = $mach->id;
                            if ($wcId && $mach->work_center_id !== $wcId) {
                                $opErrors[] = "Machine/Work Center mismatch: Machine '{$op['machine_code']}' belongs to Work Center '{$mach->workCenter?->code}', not '{$op['work_center_code']}'.";
                            }
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
                        $op['machine_id'] = $mchId;
                        $operations[] = $op;
                    }
                }

                if (empty($operations)) {
                    $rowErrors[] = "Routing must contain at least one valid operation.";
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
                                'hourly_cost' => $data['hourly_cost'],
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
                            'hourly_cost' => $data['hourly_cost'],
                            'status' => $data['status']
                        ]);
                        $successCount++;
                    }
                }
            } elseif ($type === 'boms') {
                foreach ($validRows as $row) {
                    $data = $row['data'];
                    $bom = ProductionBom::where('tenant_id', $tenantId)->where('bom_number', $data['bom_number'])->first();

                    if ($bom) {
                        if ($strategy === 'update') {
                            $bom->update([
                                'bom_name' => $data['bom_name'],
                                'product_id' => $data['product_id'],
                                'base_quantity' => $data['base_quantity'],
                                'base_uom_id' => $data['base_uom_id'],
                                'version' => $data['version'],
                                'bom_type' => $data['bom_type'],
                                'usage_context' => $data['usage_context'],
                                'effective_date' => $data['effective_date'],
                                'expiry_date' => $data['expiry_date']
                            ]);

                            $bom->items()->delete();
                            foreach ($data['items'] as $item) {
                                $childBomId = null;
                                if (!empty($item['child_bom_number'])) {
                                    $child = ProductionBom::where('tenant_id', $tenantId)->where('bom_number', $item['child_bom_number'])->first();
                                    if ($child) {
                                        $childBomId = $child->id;
                                    }
                                }

                                ProductionBomItem::create([
                                    'tenant_id' => $tenantId,
                                    'bom_id' => $bom->id,
                                    'material_id' => $item['material_id'],
                                    'quantity' => $item['quantity'],
                                    'uom_id' => $item['uom_id'],
                                    'material_scrap_percentage' => $item['material_scrap_percentage'],
                                    'child_bom_id' => $childBomId
                                ]);
                            }
                            $successCount++;
                        } else {
                            $failedCount++;
                        }
                    } else {
                        $bom = ProductionBom::create([
                            'tenant_id' => $tenantId,
                            'bom_number' => $data['bom_number'],
                            'bom_name' => $data['bom_name'],
                            'product_id' => $data['product_id'],
                            'base_quantity' => $data['base_quantity'],
                            'base_uom_id' => $data['base_uom_id'],
                            'version' => $data['version'],
                            'bom_type' => $data['bom_type'],
                            'usage_context' => $data['usage_context'],
                            'effective_date' => $data['effective_date'],
                            'expiry_date' => $data['expiry_date'],
                            'status' => 'draft',
                            'created_by' => $userId
                        ]);

                        foreach ($data['items'] as $item) {
                            $childBomId = null;
                            if (!empty($item['child_bom_number'])) {
                                $child = ProductionBom::where('tenant_id', $tenantId)->where('bom_number', $item['child_bom_number'])->first();
                                if ($child) {
                                    $childBomId = $child->id;
                                }
                            }

                            ProductionBomItem::create([
                                'tenant_id' => $tenantId,
                                'bom_id' => $bom->id,
                                'material_id' => $item['material_id'],
                                'quantity' => $item['quantity'],
                                'uom_id' => $item['uom_id'],
                                'material_scrap_percentage' => $item['material_scrap_percentage'],
                                'child_bom_id' => $childBomId
                            ]);
                        }
                        $successCount++;
                    }
                }
            } elseif ($type === 'routings') {
                foreach ($validRows as $row) {
                    $data = $row['data'];
                    $routing = Routing::where('tenant_id', $tenantId)->where('routing_number', $data['routing_number'])->first();

                    if ($routing) {
                        if ($strategy === 'update') {
                            $routing->update([
                                'name' => $data['name'],
                                'product_id' => $data['product_id'],
                                'version' => $data['version']
                            ]);

                            $opIds = $routing->operations()->withTrashed()->pluck('id');
                            RoutingOperationMaterial::whereIn('routing_operation_id', $opIds)->delete();
                            $routing->operations()->withTrashed()->forceDelete();

                            foreach ($data['operations'] as $op) {
                                $newOp = RoutingOperation::create([
                                    'tenant_id' => $tenantId,
                                    'routing_id' => $routing->id,
                                    'sequence' => $op['sequence'],
                                    'operation_number' => $op['operation_number'],
                                    'name' => $op['name'],
                                    'operation_type' => $op['operation_type'],
                                    'work_center_id' => $op['work_center_id'],
                                    'machine_id' => $op['machine_id'],
                                    'setup_time_minutes' => $op['setup_time_minutes'],
                                    'processing_time_minutes' => $op['processing_time_minutes'],
                                    'expected_yield_percentage' => $op['expected_yield_percentage'],
                                    'is_external' => $op['is_external']
                                ]);

                                foreach ($op['materials'] as $index => $mat) {
                                    RoutingOperationMaterial::create([
                                        'tenant_id' => $tenantId,
                                        'routing_operation_id' => $newOp->id,
                                        'material_id' => $mat['material_id'],
                                        'quantity' => $mat['quantity'],
                                        'uom_id' => $mat['uom_id'],
                                        'sequence' => ($index + 1) * 10
                                    ]);
                                }
                            }
                            $successCount++;
                        } else {
                            $failedCount++;
                        }
                    } else {
                        $routing = Routing::create([
                            'tenant_id' => $tenantId,
                            'routing_number' => $data['routing_number'],
                            'name' => $data['name'],
                            'product_id' => $data['product_id'],
                            'version' => $data['version'],
                            'status' => 'draft',
                            'created_by' => $userId
                        ]);

                        RoutingOperation::withTrashed()->where('routing_id', $routing->id)->forceDelete();

                        foreach ($data['operations'] as $op) {
                            $newOp = RoutingOperation::create([
                                'tenant_id' => $tenantId,
                                'routing_id' => $routing->id,
                                'sequence' => $op['sequence'],
                                'operation_number' => $op['operation_number'],
                                'name' => $op['name'],
                                'operation_type' => $op['operation_type'],
                                'work_center_id' => $op['work_center_id'],
                                'machine_id' => $op['machine_id'],
                                'setup_time_minutes' => $op['setup_time_minutes'],
                                'processing_time_minutes' => $op['processing_time_minutes'],
                                'expected_yield_percentage' => $op['expected_yield_percentage'],
                                'is_external' => $op['is_external']
                            ]);

                            foreach ($op['materials'] as $index => $mat) {
                                RoutingOperationMaterial::create([
                                    'tenant_id' => $tenantId,
                                    'routing_operation_id' => $newOp->id,
                                    'material_id' => $mat['material_id'],
                                    'quantity' => $mat['quantity'],
                                    'uom_id' => $mat['uom_id'],
                                    'sequence' => ($index + 1) * 10
                                ]);
                            }
                        }
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
                    ->with('items.material')
                    ->first();
                if ($existingBom) {
                    foreach ($existingBom->items as $item) {
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
