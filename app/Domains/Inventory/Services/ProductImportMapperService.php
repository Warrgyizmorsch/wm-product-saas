<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImportMapperService
{
    /**
     * Standard ERP fields available for mapping.
     */
    public const ERP_FIELDS = [
        // === Core Identification ===
        'name' => [
            'label' => 'Item / Product Name',
            'required' => true,
            'description' => 'Name or title of the product',
            'aliases' => ['item_name', 'item name', 'product_name', 'product name', 'item_desc', 'item description', 'description', 'particulars', 'item', 'product', 'title', 'name', 'itemname', 'productname', 'item_title']
        ],
        'sku' => [
            'label' => 'Item Code / SKU',
            'required' => true,
            'description' => 'Unique code, SKU or part number',
            'aliases' => ['sku', 'item_code', 'item code', 'part_no', 'part no', 'part_number', 'product_code', 'code', 'item_id', 'itemcode', 'productcode', 'item no', 'item_no', 'item id']
        ],
        'type' => [
            'label' => 'Material Type',
            'required' => false,
            'description' => 'Finished Good, Raw Material, Semi-Finished, Component, Service',
            'aliases' => ['classification', 'type', 'item_type', 'material_type', 'item type', 'material type', 'product_type', 'category_type', 'product type']
        ],
        'uom' => [
            'label' => 'Unit of Measure (UOM)',
            'required' => false,
            'description' => 'e.g. NOS, KGS, PCS, BOX, MTR, LTR',
            'aliases' => ['unit (uom)', 'uom', 'unit', 'unit_of_measure', 'uom_name', 'uom_code', 'measure', 'qty_unit', 'unit of measure', 'units', 'uom code', 'unit id', 'unit_id']
        ],

        // === Pricing & Valuation ===
        'selling_price' => [
            'label' => 'Selling Price / MRP',
            'required' => false,
            'description' => 'Base sales price or customer rate',
            'aliases' => ['selling_price', 'sale_price', 'sales_price', 'selling price', 'sale price', 'rate', 'sales_rate', 'mrp', 'price', 'unit_price', 'sales rate', 'selling rate', 'sales_rate_a', 'standard_price']
        ],
        'cost_price' => [
            'label' => 'Cost / Purchase Price',
            'required' => false,
            'description' => 'Default purchase price or unit cost',
            'aliases' => ['cost_price', 'purchase_price', 'cost price', 'purchase price', 'cost', 'purchase_rate', 'buy_rate', 'unit_cost', 'purchase rate', 'unit cost', 'cost rate', 'pur_rate', 'buy_price']
        ],

        // === Stock & Reorder Levels ===
        'opening_stock' => [
            'label' => 'Opening Stock Quantity',
            'required' => false,
            'description' => 'Initial physical stock quantity',
            'aliases' => ['opening_quantity', 'opening quantity', 'opening_stock', 'opening stock', 'stock', 'qty', 'opening_qty', 'balance_qty', 'stock_on_hand', 'current_stock', 'quantity', 'opening balance', 'op_qty', 'op_stock', 'op_quantity', 'opening stock (text)']
        ],
        'opening_stock_rate' => [
            'label' => 'Opening Stock Unit Rate',
            'required' => false,
            'description' => 'Valuation rate per unit for opening stock',
            'aliases' => ['opening_stock_rate', 'stock_rate', 'opening_rate', 'valuation_rate', 'op_rate', 'opening stock rate', 'stock rate', 'opening_val_rate']
        ],
        'reorder_point' => [
            'label' => 'Reorder Level / Min Stock',
            'required' => false,
            'description' => 'Minimum stock trigger alert',
            'aliases' => ['reorder_point', 'reorder_level', 'min_stock', 'minimum_level', 'reorder level', 'reorder point', 'min qty', 'min_quantity', 'min quantity', 'safety_stock']
        ],

        // === Taxation & HSN ===
        'hsn_sac' => [
            'label' => 'HSN / SAC Code',
            'required' => false,
            'description' => 'GST Tariff Code (e.g. 8471, 9983)',
            'aliases' => ['hsn', 'hsn_code', 'hsn_sac', 'hsn / sac', 'sac', 'sac_code', 'hsn code', 'sac code', 'tariffs', 'gst_hsn', 'hsn/sac']
        ],
        'gst_rate' => [
            'label' => 'GST / Tax Rate (%)',
            'required' => false,
            'description' => 'e.g. 0, 5, 12, 18, 28',
            'aliases' => ['gst', 'gst_rate', 'tax', 'tax_rate', 'tax_%', 'gst_%', 'vat', 'gst rate', 'tax rate', 'gst percent', 'tax %', 'tax_percentage']
        ],

        // === Chart of Accounts / Ledgers ===
        'inventory_account' => [
            'label' => 'Inventory / Asset Account',
            'required' => false,
            'description' => 'Chart of Accounts asset ledger (e.g. 1200 - Inventory)',
            'aliases' => ['inventory_account', 'inventory account', 'stock_account', 'stock account', 'inventory_asset_account', 'inventory asset account', 'asset_account', 'inventory_ledger', 'inventory_acc']
        ],
        'purchase_account' => [
            'label' => 'Purchase / COGS Account',
            'required' => false,
            'description' => 'Chart of Accounts expense ledger (e.g. 5010 - Cost of Goods Sold / COGS)',
            'aliases' => ['purchase_account', 'purchase account', 'cogs_account', 'cogs account', 'expense_account', 'expense account', 'cogs', 'purchase_expense_account', 'purchase_ledger', 'purchase_acc']
        ],
        'sales_account' => [
            'label' => 'Sales / Revenue Account',
            'required' => false,
            'description' => 'Chart of Accounts income ledger (e.g. 4010 - Sales Revenue)',
            'aliases' => ['sales_account', 'sales account', 'revenue_account', 'revenue account', 'income_account', 'income account', 'sales_revenue_account', 'sales_income', 'sales_ledger', 'sales_acc']
        ],

        // === Vendor, Brand & Barcode ===
        'preferred_vendor' => [
            'label' => 'Preferred Vendor / Supplier',
            'required' => false,
            'description' => 'Primary vendor name or supplier code',
            'aliases' => ['preferred_vendor', 'vendor', 'supplier', 'supplier_name', 'vendor_name', 'preferred_supplier', 'vendor_code']
        ],
        'barcode' => [
            'label' => 'Barcode / UPC / EAN',
            'required' => false,
            'description' => 'Scannable barcode string',
            'aliases' => ['barcode', 'barcode_number', 'upc', 'ean', 'isbn', 'barcode number', 'bar code']
        ],
        'brand' => [
            'label' => 'Brand / Make',
            'required' => false,
            'description' => 'Brand name or make',
            'aliases' => ['brand', 'brand_name', 'make', 'brand name']
        ],
        'manufacturer' => [
            'label' => 'Manufacturer',
            'required' => false,
            'description' => 'Original manufacturer name',
            'aliases' => ['manufacturer', 'mfr', 'mfr_name', 'company_name']
        ],

        // === Variants & Parent-Child ===
        'variation_type' => [
            'label' => 'Variation Type (Single / Variant)',
            'required' => false,
            'description' => 'Single or Variant',
            'aliases' => ['variation_type', 'variant_type', 'variation type', 'is_variant']
        ],
        'parent_sku' => [
            'label' => 'Parent Master SKU (For Variants)',
            'required' => false,
            'description' => 'SKU of the parent template product',
            'aliases' => ['parent_sku', 'parent_product_sku', 'parent_code', 'parent sku']
        ],
        'variant_attributes' => [
            'label' => 'Variant Attributes / Options',
            'required' => false,
            'description' => 'e.g. Color: Red | Size: XL',
            'aliases' => ['variant_attributes', 'attributes', 'variant attributes', 'attributes_config', 'options', 'composition', 'grade', 'model']
        ],

        // === Remarks ===
        'description' => [
            'label' => 'Item Description / Notes / Specs',
            'required' => false,
            'description' => 'Detailed product specification or remarks',
            'aliases' => ['description', 'remarks', 'notes', 'details', 'item_details', 'specification', 'spec']
        ],
    ];

    /**
     * Parse uploaded file, save to temp storage, and return detected headers + suggested mapping.
     */
    public function parseFile(UploadedFile $file, int $tenantId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $token = 'import_' . Str::random(24) . '.' . $extension;
        
        $tempPath = $file->storeAs('temp-imports', $token, 'local');
        $fullPath = Storage::disk('local')->path($tempPath);

        // Read rows from spreadsheet
        $spreadsheet = IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rawRows = $worksheet->toArray();

        if (empty($rawRows) || count($rawRows) < 1) {
            throw new \Exception('Uploaded spreadsheet is empty.');
        }

        // Find header row (first non-empty row)
        $headerRowIndex = 0;
        $headers = [];
        foreach ($rawRows as $idx => $row) {
            $nonEmpty = array_filter($row, fn($cell) => $cell !== null && trim((string)$cell) !== '');
            if (count($nonEmpty) > 0) {
                $headerRowIndex = $idx;
                $headers = array_map(fn($h) => trim((string)$h), $row);
                break;
            }
        }

        if (empty($headers)) {
            throw new \Exception('Could not find a valid header row in the file.');
        }

        // Filter out completely empty trailing header columns
        $filteredHeaders = [];
        foreach ($headers as $colIdx => $headerTitle) {
            if ($headerTitle !== '') {
                $filteredHeaders[$colIdx] = $headerTitle;
            } else {
                // If header has no title but has column index
                $filteredHeaders[$colIdx] = 'Column ' . ($colIdx + 1);
            }
        }

        // Extract 3-5 sample rows
        $sampleRows = [];
        $totalDataRows = 0;
        for ($i = $headerRowIndex + 1; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            $nonEmpty = array_filter($row, fn($cell) => $cell !== null && trim((string)$cell) !== '');
            if (count($nonEmpty) === 0) {
                continue; // skip blank row
            }
            $totalDataRows++;

            if (count($sampleRows) < 5) {
                $sampleRow = [];
                foreach ($filteredHeaders as $colIdx => $headerTitle) {
                    $sampleRow[$headerTitle] = isset($row[$colIdx]) ? (string)$row[$colIdx] : '';
                }
                $sampleRows[] = $sampleRow;
            }
        }

        // Auto-match suggestions
        $suggestedMapping = $this->generateSmartMapping($filteredHeaders);

        return [
            'file_token' => $token,
            'original_name' => $file->getClientOriginalName(),
            'total_rows' => $totalDataRows,
            'headers' => array_values($filteredHeaders),
            'sample_rows' => $sampleRows,
            'fields_schema' => self::ERP_FIELDS,
            'suggested_mapping' => $suggestedMapping,
        ];
    }

    /**
     * Smart fuzzy matcher between file headers and ERP fields.
     */
    protected function generateSmartMapping(array $fileHeaders): array
    {
        $mapping = [];
        $usedHeaders = [];

        foreach (self::ERP_FIELDS as $fieldKey => $fieldMeta) {
            $bestMatch = null;
            $highestScore = 0;

            foreach ($fileHeaders as $colIdx => $header) {
                $normalizedHeader = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $header)));

                foreach ($fieldMeta['aliases'] as $alias) {
                    $normalizedAlias = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $alias)));

                    // Exact match
                    if ($normalizedHeader === $normalizedAlias) {
                        $bestMatch = $header;
                        $highestScore = 100;
                        break 2;
                    }

                    // Partial contains
                    if (str_contains($normalizedHeader, $normalizedAlias) || str_contains($normalizedAlias, $normalizedHeader)) {
                        $score = 80;
                        if ($score > $highestScore) {
                            $bestMatch = $header;
                            $highestScore = $score;
                        }
                    }

                    // Levenshtein similarity
                    similar_text($normalizedHeader, $normalizedAlias, $percent);
                    if ($percent > 75 && $percent > $highestScore) {
                        $bestMatch = $header;
                        $highestScore = $percent;
                    }
                }
            }

            if ($bestMatch && !in_array($bestMatch, $usedHeaders, true)) {
                $mapping[$fieldKey] = $bestMatch;
                $usedHeaders[] = $bestMatch;
            } else {
                $mapping[$fieldKey] = '';
            }
        }

        return $mapping;
    }

    /**
     * Process the actual import using mapped columns.
     */
    public function processImport(
        string $fileToken,
        array $mapping,
        array $options,
        int $tenantId,
        ?int $userId = null,
        ?int $companyId = null,
        ?int $branchId = null
    ): array {
        $filePath = 'temp-imports/' . $fileToken;
        if (!Storage::disk('local')->exists($filePath)) {
            throw new \Exception('Uploaded file session expired. Please upload the file again.');
        }

        $fullPath = Storage::disk('local')->path($filePath);
        $spreadsheet = IOFactory::load($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rawRows = $worksheet->toArray();

        // Options
        $autoCreateUom = !empty($options['auto_create_uom']);
        $updateExisting = !empty($options['update_existing']);
        $defaultType = $options['default_type'] ?? 'finished_good';
        $dryRun = !empty($options['dry_run']);
        
        // Resolve tenant, company, and branch context
        $resolvedCompanyId = $companyId ?? (company_id() ?? auth()->user()?->company_id ?? \App\Models\Company::where('tenant_id', $tenantId)->value('id') ?? 1);
        $resolvedBranchId = $branchId ?? (branch_id() ?? auth()->user()?->branch_id ?? \App\Models\Branch::where('company_id', $resolvedCompanyId)->value('id') ?? null);

        $defaultWarehouseId = Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->value('id') 
            ?? Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->value('id');

        $defaultInventoryAccount = $options['default_inventory_account'] ?? null;
        $defaultPurchaseAccount = $options['default_purchase_account'] ?? null;
        $defaultSalesAccount = $options['default_sales_account'] ?? null;

        // Chart of Accounts cache & resolver
        $allAccounts = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where('is_active', true)
            ->get();

        $accountCodeMap = [];
        $accountNameMap = [];
        $accountIdMap = [];
        foreach ($allAccounts as $acc) {
            if (!empty($acc->code)) {
                $accountCodeMap[strtolower(trim((string)$acc->code))] = $acc->id;
            }
            if (!empty($acc->name)) {
                $accountNameMap[strtolower(trim((string)$acc->name))] = $acc->id;
            }
            $accountIdMap[(string)$acc->id] = $acc->id;
        }

        $resolveAccount = function (?string $rawVal, $fallbackId) use ($accountCodeMap, $accountNameMap, $accountIdMap): ?string {
            if ($rawVal === null || trim((string)$rawVal) === '') {
                return $fallbackId ? (string)$fallbackId : null;
            }
            $clean = trim((string)$rawVal);
            $lower = strtolower($clean);

            if (isset($accountIdMap[$clean])) {
                return (string)$accountIdMap[$clean];
            }
            if (isset($accountCodeMap[$lower])) {
                return (string)$accountCodeMap[$lower];
            }
            if (isset($accountNameMap[$lower])) {
                return (string)$accountNameMap[$lower];
            }
            if (str_contains($clean, '-')) {
                $parts = explode('-', $clean, 2);
                $codePart = strtolower(trim($parts[0]));
                $namePart = strtolower(trim($parts[1]));
                if (isset($accountCodeMap[$codePart])) {
                    return (string)$accountCodeMap[$codePart];
                }
                if (isset($accountNameMap[$namePart])) {
                    return (string)$accountNameMap[$namePart];
                }
            }

            return $fallbackId ? (string)$fallbackId : $clean;
        };

        // Multi-indexed Vendor cache
        $allVendors = \App\Domains\Inventory\Models\Vendor::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->get();

        $vendorMap = [];
        foreach ($allVendors as $v) {
            if (!empty($v->name)) $vendorMap[strtolower(trim((string)$v->name))] = $v->id;
            if (!empty($v->code)) $vendorMap[strtolower(trim((string)$v->code))] = $v->id;
            $vendorMap[(string)$v->id] = $v->id;
        }

        $resolveVendor = function (?string $rawVal) use ($vendorMap): ?int {
            if ($rawVal === null || trim((string)$rawVal) === '') return null;
            $clean = strtolower(trim((string)$rawVal));
            return $vendorMap[$clean] ?? null;
        };

        $cleanBool = function (?string $val, bool $default = false): bool {
            if ($val === null || trim((string)$val) === '') return $default;
            $clean = strtolower(trim((string)$val));
            return in_array($clean, ['1', 'true', 'yes', 'y', 't', 'active', 'enabled'], true);
        };

        $cleanDate = function (?string $val) {
            if ($val === null || trim((string)$val) === '') return null;
            try {
                return \Carbon\Carbon::parse(trim((string)$val));
            } catch (\Exception $e) {
                return null;
            }
        };

        // Locate header row & map column names to indexes
        $headerMap = [];
        $headerRowIndex = 0;
        foreach ($rawRows as $idx => $row) {
            $nonEmpty = array_filter($row, fn($c) => $c !== null && trim((string)$c) !== '');
            if (count($nonEmpty) > 0) {
                $headerRowIndex = $idx;
                foreach ($row as $colIdx => $colName) {
                    $trimmed = trim((string)$colName);
                    if ($trimmed !== '') {
                        $headerMap[$trimmed] = $colIdx;
                    }
                }
                break;
            }
        }

        // Multi-indexed UOM cache (indexed by both Name and Code)
        $allUoms = Uom::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->get();

        $uomMap = [];
        foreach ($allUoms as $u) {
            if (!empty($u->name)) {
                $uomMap[strtolower(trim($u->name))] = $u;
            }
            if (!empty($u->code)) {
                $uomMap[strtolower(trim($u->code))] = $u;
            }
        }
        $defaultUom = $allUoms->first() ?? Uom::first();

        // Safe UOM resolver closure
        $resolveUom = function (?string $rawUomName) use (&$uomMap, $tenantId, $autoCreateUom, $dryRun, $defaultUom): ?int {
            if ($rawUomName === null || trim($rawUomName) === '') {
                return $defaultUom?->id;
            }
            $clean = trim($rawUomName);
            $key = strtolower($clean);

            if (isset($uomMap[$key])) {
                return $uomMap[$key]->id;
            }

            // Direct DB lookup fallback (case-insensitive on name or code)
            $existing = Uom::withoutGlobalScopes()
                ->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->where(function ($q) use ($clean) {
                    $q->where('name', $clean)
                      ->orWhere('code', $clean);
                })
                ->first();

            if ($existing) {
                $uomMap[$key] = $existing;
                if (!empty($existing->name)) $uomMap[strtolower(trim($existing->name))] = $existing;
                if (!empty($existing->code)) $uomMap[strtolower(trim($existing->code))] = $existing;
                return $existing->id;
            }

            if ($autoCreateUom) {
                $cleanCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $clean), 0, 10)) ?: 'UNIT';

                // Check if code exists in tenant
                $codeExists = Uom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('code', $cleanCode)
                    ->first();

                if ($codeExists) {
                    $uomMap[$key] = $codeExists;
                    return $codeExists->id;
                }

                if ($dryRun) {
                    // Simulate created UOM for dry run
                    $simulated = new Uom(['id' => $defaultUom?->id ?: 1, 'name' => strtoupper($clean), 'code' => $cleanCode]);
                    $uomMap[$key] = $simulated;
                    $uomMap[strtolower($cleanCode)] = $simulated;
                    return $simulated->id;
                }

                try {
                    $newUom = Uom::create([
                        'tenant_id' => $tenantId,
                        'name' => strtoupper($clean),
                        'code' => $cleanCode,
                        'status' => 'active',
                    ]);
                    $uomMap[$key] = $newUom;
                    $uomMap[strtolower($cleanCode)] = $newUom;
                    return $newUom->id;
                } catch (\Exception $e) {
                    // Fallback to default UOM if duplicate constraint occurs
                    return $defaultUom?->id;
                }
            }

            return $defaultUom?->id;
        };

        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];
        $processedSkus = [];

        // Helper closure to get cell value by mapped field
        $getValue = function (array $row, string $fieldKey) use ($mapping, $headerMap) {
            if (empty($mapping[$fieldKey])) {
                return null;
            }
            $targetColName = $mapping[$fieldKey];
            if (!isset($headerMap[$targetColName])) {
                return null;
            }
            $colIdx = $headerMap[$targetColName];
            $val = $row[$colIdx] ?? null;
            return $val !== null ? trim((string)$val) : null;
        };

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            for ($i = $headerRowIndex + 1; $i < count($rawRows); $i++) {
                $rowNum = $i + 1;
                $row = $rawRows[$i];
                $nonEmpty = array_filter($row, fn($c) => $c !== null && trim((string)$c) !== '');
                if (count($nonEmpty) === 0) {
                    continue; // skip blank line
                }

                $name = $getValue($row, 'name');
                $sku = $getValue($row, 'sku');

                // Validation
                if (empty($name)) {
                    $errors[] = "Row #{$rowNum}: Skipped because 'Item Name' is empty.";
                    $skippedCount++;
                    continue;
                }

                if (empty($sku)) {
                    // Auto-generate SKU if name is provided
                    $sku = 'ITEM-' . strtoupper(Str::slug(substr($name, 0, 10), '')) . '-' . Str::random(4);
                }

                // Clean and normalize SKU
                $sku = trim((string)$sku);

                // Check duplicate SKU in the same file
                if (in_array($sku, $processedSkus, true) && !$updateExisting) {
                    $errors[] = "Row #{$rowNum}: Duplicate SKU '{$sku}' in this file. Skipped.";
                    $skippedCount++;
                    continue;
                }
                $processedSkus[] = $sku;

                // Resolve Material Type
                $typeRaw = strtolower(trim((string)$getValue($row, 'type')));
                $allowedTypes = ['finished_good', 'semi_finished', 'raw_material', 'component', 'service'];
                $type = $defaultType;

                if (!empty($typeRaw)) {
                    if (in_array($typeRaw, $allowedTypes, true)) {
                        $type = $typeRaw;
                    } elseif (str_contains($typeRaw, 'finish')) {
                        $type = 'finished_good';
                    } elseif (str_contains($typeRaw, 'raw')) {
                        $type = 'raw_material';
                    } elseif (str_contains($typeRaw, 'semi')) {
                        $type = 'semi_finished';
                    } elseif (str_contains($typeRaw, 'comp')) {
                        $type = 'component';
                    } elseif (str_contains($typeRaw, 'serv')) {
                        $type = 'service';
                    }
                }

                // Resolve Material Type & Item Nature
                $itemTypeRaw = $getValue($row, 'item_type');
                $itemType = $type === 'service' ? 'Service' : 'Goods';
                if (!empty($itemTypeRaw)) {
                    $cleanNature = strtolower(trim($itemTypeRaw));
                    $itemType = str_contains($cleanNature, 'serv') ? 'Service' : 'Goods';
                }

                $statusRaw = $getValue($row, 'status');
                $status = 'active';
                if (!empty($statusRaw)) {
                    $cleanStatus = strtolower(trim($statusRaw));
                    if (in_array($cleanStatus, ['active', 'inactive', 'draft', 'discontinued'], true)) {
                        $status = $cleanStatus;
                    } elseif (in_array($cleanStatus, ['0', 'false', 'no', 'deactive', 'disabled'], true)) {
                        $status = 'inactive';
                    }
                }

                $supplierMethodRaw = $getValue($row, 'supplier_method');
                $supplierMethod = $type === 'finished_good' ? 'manufacture' : 'buy';
                if (!empty($supplierMethodRaw)) {
                    $cleanSupp = strtolower(trim($supplierMethodRaw));
                    if (in_array($cleanSupp, ['buy', 'manufacture', 'trade', 'subcontract'], true)) {
                        $supplierMethod = $cleanSupp;
                    }
                }

                $planningTypeRaw = $getValue($row, 'planning_type');
                $planningType = 'stock';
                if (!empty($planningTypeRaw)) {
                    $cleanPlan = strtolower(trim($planningTypeRaw));
                    if (in_array($cleanPlan, ['stock', 'manufacture', 'purchase', 'manual'], true)) {
                        $planningType = $cleanPlan;
                    }
                }

                $defaultProdModelRaw = $getValue($row, 'default_production_model');
                $defaultProdModel = !empty($defaultProdModelRaw) ? trim($defaultProdModelRaw) : null;

                // Resolve UOM safely (matching name or code, or auto-creating)
                $uomId = $resolveUom($getValue($row, 'uom'));

                // Numeric Fields
                $cleanNumeric = function (?string $val, float $default = 0.0): float {
                    if ($val === null || $val === '') return $default;
                    $clean = preg_replace('/[^0-9.-]/', '', $val);
                    return is_numeric($clean) ? (float)$clean : $default;
                };

                $sellingPrice = $cleanNumeric($getValue($row, 'selling_price'), 0.0);
                $costPrice = $cleanNumeric($getValue($row, 'cost_price'), 0.0);
                $rawUnitCost = $getValue($row, 'unit_cost');
                $unitCost = $rawUnitCost !== null ? $cleanNumeric($rawUnitCost, 0.0) : ($costPrice > 0 ? $costPrice : $sellingPrice);

                $openingStock = $cleanNumeric($getValue($row, 'opening_stock'), 0.0);
                $openingStockRate = $cleanNumeric($getValue($row, 'opening_stock_rate'), $costPrice > 0 ? $costPrice : 0.0);
                $gstRate = $cleanNumeric($getValue($row, 'gst_rate'), 0.0);
                $reorderPoint = $cleanNumeric($getValue($row, 'reorder_point'), 0.0);
                $minOrderQty = $cleanNumeric($getValue($row, 'minimum_order_qty'), 0.0);
                $orderMultiple = $cleanNumeric($getValue($row, 'order_multiple'), 0.0);

                $length = $cleanNumeric($getValue($row, 'length'), 0.0);
                $width = $cleanNumeric($getValue($row, 'width'), 0.0);
                $height = $cleanNumeric($getValue($row, 'height'), 0.0);
                $weight = $cleanNumeric($getValue($row, 'weight'), 0.0);

                $dimensionUnit = $getValue($row, 'dimension_unit') ?: 'cm';
                $weightUnit = $getValue($row, 'weight_unit') ?: 'kg';
                $valuationMethod = $getValue($row, 'inventory_valuation_method') ?: 'FIFO';

                // Boolean flags
                $trackSerialNumber = $cleanBool($getValue($row, 'track_serial_number'), false);
                $trackBatch = $cleanBool($getValue($row, 'track_batch'), false);

                // String Identifiers
                $hsnSac = $getValue($row, 'hsn_sac') ?: null;
                $barcode = $getValue($row, 'barcode') ?: null;
                $mpn = $getValue($row, 'mpn') ?: null;
                $upc = $getValue($row, 'upc') ?: null;
                $ean = $getValue($row, 'ean') ?: null;
                $isbn = $getValue($row, 'isbn') ?: null;
                $brand = $getValue($row, 'brand') ?: null;
                $manufacturer = $getValue($row, 'manufacturer') ?: null;
                $description = $getValue($row, 'description') ?: null;
                $preferredVendorId = $resolveVendor($getValue($row, 'preferred_vendor'));

                // Timestamps
                $createdAt = $cleanDate($getValue($row, 'created_at'));
                $updatedAt = $cleanDate($getValue($row, 'updated_at'));

                // Parent / Variant Resolution
                $parentSku = $getValue($row, 'parent_sku');
                $parentId = null;
                $variantValues = [];

                $attrStr = $getValue($row, 'variant_attributes');
                if (!empty($attrStr)) {
                    $pairs = preg_split('/[|;,]/', $attrStr);
                    foreach ($pairs as $pair) {
                        if (str_contains($pair, ':')) {
                            list($k, $v) = explode(':', $pair, 2);
                        } elseif (str_contains($pair, '=')) {
                            list($k, $v) = explode('=', $pair, 2);
                        } else {
                            continue;
                        }
                        $k = trim($k);
                        $v = trim($v);
                        if (!empty($k) && !empty($v)) {
                            $variantValues[$k] = $v;
                        }
                    }
                }

                $variationType = 'Single';
                $varTypeRaw = $getValue($row, 'variation_type');
                if (!empty($varTypeRaw)) {
                    $variationType = str_contains(strtolower($varTypeRaw), 'var') ? 'Variant' : 'Single';
                }

                if (!empty($parentSku)) {
                    $parent = Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sku', $parentSku)->first();
                    if ($parent) {
                        $parentId = $parent->id;
                        $variationType = 'Single';
                    }
                }

                // Resolve Chart of Accounts
                $inventoryAccount = $resolveAccount($getValue($row, 'inventory_account'), $defaultInventoryAccount);
                $purchaseAccount = $resolveAccount($getValue($row, 'purchase_account'), $defaultPurchaseAccount);
                $salesAccount = $resolveAccount($getValue($row, 'sales_account'), $defaultSalesAccount);

                $productPayload = [
                    'tenant_id' => $tenantId,
                    'company_id' => $resolvedCompanyId,
                    'branch_id' => $resolvedBranchId,
                    'name' => $name,
                    'sku' => $sku,
                    'type' => $type,
                    'item_type' => $itemType,
                    'supplier_method' => $supplierMethod,
                    'planning_type' => $planningType,
                    'default_production_model' => $defaultProdModel,
                    'variation_type' => $variationType,
                    'parent_id' => $parentId,
                    'uom_id' => $uomId,
                    'selling_price' => $sellingPrice,
                    'cost_price' => $costPrice,
                    'unit_cost' => $unitCost,
                    'sales_account' => $salesAccount,
                    'purchase_account' => $purchaseAccount,
                    'inventory_account' => $inventoryAccount,
                    'opening_stock' => $openingStock,
                    'opening_stock_rate' => $openingStockRate,
                    'reorder_point' => $reorderPoint,
                    'minimum_order_qty' => $minOrderQty,
                    'order_multiple' => $orderMultiple,
                    'inventory_valuation_method' => $valuationMethod,
                    'hsn_sac' => $hsnSac,
                    'gst_rate' => $gstRate,
                    'preferred_vendor_id' => $preferredVendorId,
                    'barcode' => $barcode,
                    'mpn' => $mpn,
                    'upc' => $upc,
                    'ean' => $ean,
                    'isbn' => $isbn,
                    'brand' => $brand,
                    'manufacturer' => $manufacturer,
                    'length' => $length,
                    'width' => $width,
                    'height' => $height,
                    'dimension_unit' => $dimensionUnit,
                    'weight' => $weight,
                    'weight_unit' => $weightUnit,
                    'track_serial_number' => $trackSerialNumber,
                    'track_batch' => $trackBatch,
                    'description' => $description,
                    'variant_values' => !empty($variantValues) ? $variantValues : null,
                    'status' => $status,
                ];

                if ($createdAt) {
                    $productPayload['created_at'] = $createdAt;
                }
                if ($updatedAt) {
                    $productPayload['updated_at'] = $updatedAt;
                }

                if (!$dryRun) {
                    $existingQuery = Product::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('sku', $sku);

                    if ($resolvedCompanyId) {
                        $existingQuery->where('company_id', $resolvedCompanyId);
                    }

                    $existing = $existingQuery->first();

                    if ($existing) {
                        if ($updateExisting) {
                            $existing->update($productPayload);
                            $productObj = $existing;
                            $updatedCount++;
                        } else {
                            $skippedCount++;
                            continue;
                        }
                    } else {
                        $productObj = Product::create($productPayload);
                        $importedCount++;
                    }

                    // Record initial opening stock in warehouse stock if specified
                    if ($openingStock > 0 && $defaultWarehouseId) {
                        \App\Domains\Inventory\Models\ProductWarehouseStock::updateOrCreate(
                            [
                                'tenant_id' => $tenantId,
                                'company_id' => $resolvedCompanyId,
                                'branch_id' => $resolvedBranchId,
                                'product_id' => $productObj->id,
                                'warehouse_id' => $defaultWarehouseId,
                            ],
                            [
                                'quantity' => $openingStock,
                                'unit_cost' => $openingStockRate ?: $costPrice,
                            ]
                        );
                    }
                } else {
                    // Dry run counting
                    $existingQuery = Product::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('sku', $sku);

                    if ($resolvedCompanyId) {
                        $existingQuery->where('company_id', $resolvedCompanyId);
                    }

                    $existing = $existingQuery->exists();

                    if ($existing) {
                        if ($updateExisting) {
                            $updatedCount++;
                        } else {
                            $skippedCount++;
                        }
                    } else {
                        $importedCount++;
                    }
                }
            }

            if (!$dryRun) {
                DB::commit();
                // Clean up file
                Storage::disk('local')->delete($filePath);
            }

            return [
                'success' => true,
                'dry_run' => $dryRun,
                'imported_count' => $importedCount,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'total_processed' => $importedCount + $updatedCount + $skippedCount,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            throw $e;
        }
    }
}
