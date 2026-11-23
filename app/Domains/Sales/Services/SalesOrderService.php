<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Repositories\SalesOrderRepository;
use App\Domains\Inventory\Models\Product;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Models\GstConfiguration;
use App\Models\PartyBranch;
use App\Services\TaxIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class SalesOrderService
{
    public function __construct(
        private readonly SalesOrderRepository $salesOrders,
    ) {
    }

    public function latest(): Collection
    {
        return $this->salesOrders->latest();
    }

    public function find(int $id): ?SalesOrder
    {
        return $this->salesOrders->find($id);
    }

    public function getNextSalesOrderNumber(): string
    {
        $tenantId = tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $year = now()->format('Y');

        // Fetch all order numbers for this tenant matching current year (handling DB raw column and prefixed formats)
        $allNumbers = SalesOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($year) {
                $q->where('sales_order_number', 'like', "{$year}-%")
                  ->orWhere('sales_order_number', 'like', "SO-{$year}-%");
            })
            ->pluck('sales_order_number');

        $maxNum = 0;
        foreach ($allNumbers as $soNum) {
            $cleanNum = preg_replace('/^(SO-)?' . $year . '-/i', '', $soNum);
            if (is_numeric($cleanNum)) {
                $intVal = intval($cleanNum);
                if ($intVal > $maxNum) {
                    $maxNum = $intVal;
                }
            }
        }

        $nextNum = $maxNum + 1;
        $nextFormatted = str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        $nextDbNumber = "{$year}-{$nextFormatted}";

        // Ensure uniqueness against both raw DB format (YYYY-XXXX) and model format (SO-YYYY-XXXX)
        while (SalesOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($nextDbNumber) {
                $q->where('sales_order_number', $nextDbNumber)
                  ->orWhere('sales_order_number', "SO-{$nextDbNumber}");
            })
            ->exists()) {
            $nextNum++;
            $nextFormatted = str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            $nextDbNumber = "{$year}-{$nextFormatted}";
        }

        return "SO-{$nextDbNumber}";
    }

    public function create(array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['sales_order_number'])) {
                $data['sales_order_number'] = $this->getNextSalesOrderNumber();
            }

            $taxMode = $data['tax_mode'] ?? 'exclusive';
            $discountType = $data['discount_type'] ?? 'item_wise';
            $taxType = $data['tax_type'] ?? 'item_wise_tax';
            $orderTaxRate = floatval($data['order_tax_rate'] ?? 0);

            // Resolve & freeze party branches + GSTIN snapshots (FRS 2.3 / C-17)
            $data = $this->applyBranchAndGstSnapshots($data);

            // Auto-resolve gst_type if empty and tax_type is not without_tax
            if (empty($data['gst_type']) && $taxType !== 'without_tax' && !empty($data['customer_id'])) {
                $data['gst_type'] = $this->resolveGstType(
                    (int) $data['customer_id'],
                    $data['company_id'] ?? null,
                    $data['tenant_id'] ?? null,
                    $data['branch_id'] ?? null,
                    $data['place_of_supply_state'] ?? null,
                    $data['bill_to_gstin'] ?? null
                );
            }

            $subtotal = 0;
            $rawLines = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = convert_to_base((float)($item['unit_price'] ?? 0));
                $taxRate = ($taxType === 'item_wise_tax') ? floatval($item['tax_rate'] ?? 0) : 0;
                $lineDiscount = ($discountType === 'item_wise') ? convert_to_base((float)($item['discount'] ?? 0)) : 0;
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $warehouseId = !empty($item['warehouse_id']) ? intval($item['warehouse_id']) : null;

                $lineGross = $qty * $price;
                $subtotal += $lineGross;

                $rawLines[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'item' => $item,
                    'qty' => $qty,
                    'price' => $price,
                    'tax_rate' => $taxRate,
                    'line_discount' => $lineDiscount,
                    'line_gross' => $lineGross,
                ];
            }

            $discountVal = 0;
            if ($discountType === 'item_wise') {
                foreach ($rawLines as $rl) {
                    $discountVal += floatval($rl['line_discount'] ?? 0);
                }
            } elseif ($discountType === 'order_wise') {
                $discountVal = convert_to_base((float)($data['discount'] ?? 0));
            }

            $tax = 0;
            $itemsData = [];

            foreach ($rawLines as $rl) {
                $lineGross = $rl['line_gross'];
                $taxRate = $rl['tax_rate'];
                $qty = $rl['qty'];
                $price = $rl['price'];
                $productId = $rl['product_id'];
                $warehouseId = $rl['warehouse_id'];
                $item = $rl['item'];

                $lineDisc = ($discountType === 'item_wise') 
                    ? $rl['line_discount'] 
                    : (($subtotal > 0 && $discountVal > 0) ? ($lineGross / $subtotal) * $discountVal : 0);

                if ($taxMode === 'inclusive') {
                    $lineNet = max(0, $lineGross - $lineDisc);
                    $lineBase = ($taxRate > 0 && $taxType === 'item_wise_tax') ? round($lineNet / (1 + ($taxRate / 100)), 4) : $lineNet;
                    $itemTax = ($taxType === 'item_wise_tax') ? ($lineNet - $lineBase) : 0;
                    $tax += $itemTax;
                    $amount = $lineGross - ($discountType === 'item_wise' ? $lineDisc : 0);
                } else {
                    $lineTaxable = max(0, $lineGross - $lineDisc);
                    $itemTax = ($taxType === 'item_wise_tax') ? ($lineTaxable * ($taxRate / 100)) : 0;
                    $tax += $itemTax;
                    $amount = $lineGross - ($discountType === 'item_wise' ? $lineDisc : 0);
                }

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = Product::find($productId);
                    if ($product) {
                        $itemName = $product->name;
                    }
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'item_name' => $itemName,
                    'description' => $item['description'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'discount' => ($discountType === 'item_wise') ? $rl['line_discount'] : 0,
                    'amount' => $amount,
                ];
            }

            if ($taxType === 'order_wise_tax') {
                $taxableAmount = max(0, $subtotal - $discountVal);
                if ($taxMode === 'inclusive') {
                    $tax = ($orderTaxRate > 0) ? ($taxableAmount - ($taxableAmount / (1 + ($orderTaxRate / 100)))) : 0;
                } else {
                    $tax = $taxableAmount * ($orderTaxRate / 100);
                }
            } elseif ($taxType === 'without_tax') {
                $tax = 0;
            }

            $tax = round($tax, 2);

            $freightTerms = $data['freight_terms'] ?? 'To Pay';
            $freightAmount = convert_to_base((float)($data['freight_amount'] ?? $data['shipping_charges'] ?? 0));
            $adjustment = convert_to_base((float)($data['adjustment'] ?? 0));

            $effectiveFreight = ($freightTerms === 'To Be Billed') ? $freightAmount : 0;
            $totalTax = $tax;

            if ($taxMode === 'inclusive') {
                $totalAmount = max(0, $subtotal - $discountVal) + $effectiveFreight + $adjustment;
            } else {
                $taxableSubtotal = max(0, $subtotal - $discountVal);
                $totalAmount = $taxableSubtotal + $totalTax + $effectiveFreight + $adjustment;
            }

            $data['tax_mode'] = $taxMode;
            $data['freight_terms'] = $freightTerms;
            $data['freight_amount'] = $freightAmount;
            $data['shipping_charges'] = $freightAmount;
            $data['discount'] = $discountVal;
            $data['subtotal'] = $subtotal;
            $data['tax'] = $totalTax;
            $data['adjustment'] = $adjustment;
            $data['total_amount'] = max(0, $totalAmount);

            $salesOrder = $this->salesOrders->create($data);
            $salesOrder->items()->createMany($itemsData);

            if (!empty($data['quotation_id'])) {
                $quotation = Quotation::find($data['quotation_id']);
                if ($quotation) {
                    $quotation->update(['status' => 'Converted']);
                    if ($quotation->lead_id) {
                        $lead = Lead::find($quotation->lead_id);
                        if ($lead) {
                            $lead->update([
                                'status' => 'Converted',
                                'is_customer' => true,
                                'converted_at' => now(),
                            ]);
                        }
                    }
                    if ($quotation->crm_deal_id) {
                        $deal = CrmDeal::find($quotation->crm_deal_id);
                        if ($deal) {
                            $deal->update([
                                'stage' => 'Won',
                                'probability' => 100,
                                'estimated_value' => $salesOrder->total_amount,
                            ]);
                        }
                    }
                }
            }

            return $salesOrder;
        });
    }

    public function update(SalesOrder $salesOrder, array $data, array $items): SalesOrder
    {
        if ($salesOrder->status !== 'Draft') {
            throw new \Exception('Only Draft Sales Orders can be updated.');
        }

        return DB::transaction(function () use ($salesOrder, $data, $items) {
            $data = $this->applyBranchAndGstSnapshots(array_merge([
                'tenant_id' => $salesOrder->tenant_id,
                'company_id' => $salesOrder->company_id,
                'branch_id' => $salesOrder->branch_id,
                'customer_id' => $salesOrder->customer_id,
            ], $data));

            $taxMode = $data['tax_mode'] ?? $salesOrder->tax_mode ?? 'exclusive';
            $discountType = $data['discount_type'] ?? $salesOrder->discount_type ?? 'item_wise';
            $taxType = $data['tax_type'] ?? $salesOrder->tax_type ?? 'item_wise_tax';
            $orderTaxRate = floatval($data['order_tax_rate'] ?? $salesOrder->order_tax_rate ?? 0);

            // Auto-resolve gst_type if empty and tax_type is not without_tax
            if (empty($data['gst_type']) && $taxType !== 'without_tax') {
                $customerId = $data['customer_id'] ?? $salesOrder->customer_id;
                if ($customerId) {
                    $data['gst_type'] = $this->resolveGstType(
                        (int) $customerId,
                        $data['company_id'] ?? $salesOrder->company_id,
                        $data['tenant_id'] ?? $salesOrder->tenant_id,
                        $data['branch_id'] ?? $salesOrder->branch_id,
                        $data['place_of_supply_state'] ?? null,
                        $data['bill_to_gstin'] ?? null
                    );
                }
            }

            $subtotal = 0;
            $rawLines = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = convert_to_base((float)($item['unit_price'] ?? 0));
                $taxRate = ($taxType === 'item_wise_tax') ? floatval($item['tax_rate'] ?? 0) : 0;
                $lineDiscount = ($discountType === 'item_wise') ? convert_to_base((float)($item['discount'] ?? 0)) : 0;
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $warehouseId = !empty($item['warehouse_id']) ? intval($item['warehouse_id']) : null;

                $lineGross = $qty * $price;
                $subtotal += $lineGross;

                $rawLines[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'item' => $item,
                    'qty' => $qty,
                    'price' => $price,
                    'tax_rate' => $taxRate,
                    'line_discount' => $lineDiscount,
                    'line_gross' => $lineGross,
                ];
            }

            $discountVal = 0;
            if ($discountType === 'item_wise') {
                foreach ($rawLines as $rl) {
                    $discountVal += floatval($rl['line_discount'] ?? 0);
                }
            } elseif ($discountType === 'order_wise') {
                $discountVal = convert_to_base((float)($data['discount'] ?? 0));
            }

            $tax = 0;
            $itemsData = [];

            foreach ($rawLines as $rl) {
                $lineGross = $rl['line_gross'];
                $taxRate = $rl['tax_rate'];
                $qty = $rl['qty'];
                $price = $rl['price'];
                $productId = $rl['product_id'];
                $warehouseId = $rl['warehouse_id'];
                $item = $rl['item'];

                $lineDisc = ($discountType === 'item_wise') 
                    ? $rl['line_discount'] 
                    : (($subtotal > 0 && $discountVal > 0) ? ($lineGross / $subtotal) * $discountVal : 0);

                if ($taxMode === 'inclusive') {
                    $lineNet = max(0, $lineGross - $lineDisc);
                    $lineBase = ($taxRate > 0 && $taxType === 'item_wise_tax') ? round($lineNet / (1 + ($taxRate / 100)), 4) : $lineNet;
                    $itemTax = ($taxType === 'item_wise_tax') ? ($lineNet - $lineBase) : 0;
                    $tax += $itemTax;
                    $amount = $lineGross - ($discountType === 'item_wise' ? $lineDisc : 0);
                } else {
                    $lineTaxable = max(0, $lineGross - $lineDisc);
                    $itemTax = ($taxType === 'item_wise_tax') ? ($lineTaxable * ($taxRate / 100)) : 0;
                    $tax += $itemTax;
                    $amount = $lineGross - ($discountType === 'item_wise' ? $lineDisc : 0);
                }

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = Product::find($productId);
                    if ($product) {
                        $itemName = $product->name;
                    }
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'item_name' => $itemName,
                    'description' => $item['description'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'discount' => ($discountType === 'item_wise') ? $rl['line_discount'] : 0,
                    'amount' => $amount,
                ];
            }

            if ($taxType === 'order_wise_tax') {
                $taxableAmount = max(0, $subtotal - $discountVal);
                if ($taxMode === 'inclusive') {
                    $tax = ($orderTaxRate > 0) ? ($taxableAmount - ($taxableAmount / (1 + ($orderTaxRate / 100)))) : 0;
                } else {
                    $tax = $taxableAmount * ($orderTaxRate / 100);
                }
            } elseif ($taxType === 'without_tax') {
                $tax = 0;
            }

            $tax = round($tax, 2);

            $freightTerms = $data['freight_terms'] ?? $salesOrder->freight_terms ?? 'To Pay';
            $freightAmount = convert_to_base((float)($data['freight_amount'] ?? $data['shipping_charges'] ?? 0));
            $adjustment = convert_to_base((float)($data['adjustment'] ?? 0));

            $effectiveFreight = ($freightTerms === 'To Be Billed') ? $freightAmount : 0;
            $totalTax = $tax;

            if ($taxMode === 'inclusive') {
                $totalAmount = max(0, $subtotal - $discountVal) + $effectiveFreight + $adjustment;
            } else {
                $taxableSubtotal = max(0, $subtotal - $discountVal);
                $totalAmount = $taxableSubtotal + $totalTax + $effectiveFreight + $adjustment;
            }

            $data['tax_mode'] = $taxMode;
            $data['freight_terms'] = $freightTerms;
            $data['freight_amount'] = $freightAmount;
            $data['shipping_charges'] = $freightAmount;
            $data['discount'] = $discountVal;
            $data['subtotal'] = $subtotal;
            $data['tax'] = $totalTax;
            $data['adjustment'] = $adjustment;
            $data['total_amount'] = max(0, $totalAmount);

            $salesOrder->update($data);
            $salesOrder->items()->delete();
            $salesOrder->items()->createMany($itemsData);

            return $salesOrder;
        });
    }

    public function delete(SalesOrder $salesOrder): bool
    {
        return $salesOrder->delete();
    }

    /**
     * Resolve GST type (cgst_sgst for Intra-state, igst for Inter-state)
     * comparing our active branch's effective GSTIN/state vs customer's Place of Supply / Bill-To GSTIN.
     */
    public function resolveGstType(
        Customer|int $customer,
        ?int $companyId = null,
        ?int $tenantId = null,
        ?int $branchId = null,
        ?string $placeOfSupplyState = null,
        ?string $billToGstin = null
    ): string {
        if (is_numeric($customer)) {
            $customer = Customer::find($customer);
        }
        if (!$customer) {
            return 'cgst_sgst';
        }

        $tenantId ??= $customer->tenant_id ?? (tenant_id() ?? auth()->user()?->tenant_id ?? 1);
        $branchId ??= (function_exists('branch_id') ? branch_id() : null)
            ?? (function_exists('current_branch_id') ? current_branch_id() : null);

        $branch = $branchId ? Branch::withoutGlobalScopes()->with('company')->find($branchId) : null;
        $company = $branch?->company;

        if (!$company && $companyId) {
            $company = Company::withoutGlobalScopes()->find($companyId);
        }
        if (!$company && function_exists('company') && company()) {
            $company = company();
        }
        if (!$company && function_exists('current_company_id') && current_company_id()) {
            $company = Company::withoutGlobalScopes()->find(current_company_id());
        }
        if (!$company) {
            $company = Company::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        }

        // Seller effective GSTIN: active branch GSTIN -> branch GstConfig -> company GSTIN -> default GstConfig
        $sellerGstin = $branch?->gst_number ? TaxIdentityService::normalizeGstin($branch->gst_number) : null;
        if (empty($sellerGstin)) {
            $gstConfig = GstConfiguration::getForCurrentContext($company?->id, $branch?->id);
            if ($gstConfig && $gstConfig->branch_id && $gstConfig->seller_gstin) {
                $sellerGstin = TaxIdentityService::normalizeGstin($gstConfig->seller_gstin);
            }
        }
        if (empty($sellerGstin)) {
            $sellerGstin = TaxIdentityService::normalizeGstin($company?->gst_number ?? $company?->tax_number ?? $company?->gstin ?? null);
        }
        if (empty($sellerGstin)) {
            $gstConfig = GstConfiguration::getForCurrentContext($company?->id, $branch?->id);
            if ($gstConfig && $gstConfig->seller_gstin) {
                $sellerGstin = TaxIdentityService::normalizeGstin($gstConfig->seller_gstin);
            }
        }

        $sellerStateCode = ($branch && $branch->gst_number && $branch->gst_state_code)
            ? str_pad((string) $branch->gst_state_code, 2, '0', STR_PAD_LEFT)
            : TaxIdentityService::extractStateCode($sellerGstin);

        $targetStateCode = $placeOfSupplyState
            ? str_pad((string) $placeOfSupplyState, 2, '0', STR_PAD_LEFT)
            : TaxIdentityService::extractStateCode($billToGstin ?: ($customer->gstin ?? null));

        // 1. Primary rule: Compare 2-digit state codes
        if (!empty($sellerStateCode) && !empty($targetStateCode)) {
            return ($sellerStateCode === $targetStateCode) ? 'cgst_sgst' : 'igst';
        }

        // 2. Fallback: Compare state names if GSTINs / state codes are not available
        $sellerStateName = strtolower(trim((string) (($branch && $branch->gst_number ? $branch->state : null) ?: $company?->state ?: $branch?->state ?: '')));
        $customerState = strtolower(trim((string) ($customer->billing_state ?? $customer->state ?? '')));
        if (!empty($sellerStateName) && !empty($customerState)) {
            return ($sellerStateName === $customerState) ? 'cgst_sgst' : 'igst';
        }

        return 'cgst_sgst';
    }

    /**
     * Resolve and freeze seller_gstin, bill_to_gstin, ship_to_gstin, place_of_supply_state
     * and branch addresses onto SalesOrder data at creation/update time.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyBranchAndGstSnapshots(array $data): array
    {
        $tenantId = $data['tenant_id'] ?? tenant_id() ?? auth()->user()?->tenant_id ?? 1;
        $branchId = $data['branch_id'] ?? (function_exists('branch_id') ? branch_id() : null) ?? (function_exists('current_branch_id') ? current_branch_id() : null);
        $companyId = $data['company_id'] ?? (function_exists('company_id') ? company_id() : null) ?? (function_exists('current_company_id') ? current_company_id() : null);

        $ownBranch = $branchId ? Branch::withoutGlobalScopes()->with('company')->find($branchId) : null;
        $org = billing_organization_details($ownBranch);

        if (empty($data['seller_gstin']) && !empty($org['gstin'])) {
            $data['seller_gstin'] = $org['gstin'];
        }
        if (empty($data['seller_state_code']) && !empty($org['state_code'])) {
            $data['seller_state_code'] = $org['state_code'];
        }

        $customer = !empty($data['customer_id']) ? Customer::withoutGlobalScopes()->find($data['customer_id']) : null;
        if (!$customer) {
            return $data;
        }

        $billBranch = !empty($data['billing_branch_id'])
            ? PartyBranch::withoutGlobalScopes()->find($data['billing_branch_id'])
            : $customer->defaultBillingBranch();
        $shipBranch = !empty($data['shipping_branch_id'])
            ? PartyBranch::withoutGlobalScopes()->find($data['shipping_branch_id'])
            : $customer->defaultShippingBranch();

        if ($billBranch && empty($data['billing_branch_id'])) {
            $data['billing_branch_id'] = $billBranch->id;
        }
        if ($shipBranch && empty($data['shipping_branch_id'])) {
            $data['shipping_branch_id'] = $shipBranch->id;
        }

        if ($billBranch && empty($data['billing_address'])) {
            $formatted = $billBranch->formattedAddress();
            if ($formatted !== '') {
                $data['billing_address'] = $formatted;
            }
        }
        if ($shipBranch && empty($data['shipping_address'])) {
            $formatted = $shipBranch->formattedAddress();
            if ($formatted !== '') {
                $data['shipping_address'] = $formatted;
            }
        }

        $billGstin = $billBranch?->effectiveGstin() ?: TaxIdentityService::normalizeGstin($customer->gstin);
        $shipGstin = $shipBranch?->effectiveGstin() ?: $billGstin;

        $data['bill_to_gstin'] = $data['bill_to_gstin'] ?? $billGstin;
        $data['ship_to_gstin'] = $data['ship_to_gstin'] ?? $shipGstin;

        // Place of Supply (IGST Sec 10(1)(b)): Bill-To GSTIN state for B2B; Ship-To state when unregistered/B2C.
        $billState = TaxIdentityService::extractStateCode($data['bill_to_gstin']) ?: $billBranch?->effectiveStateCode();
        $shipState = TaxIdentityService::extractStateCode($data['ship_to_gstin'])
            ?: $shipBranch?->effectiveStateCode()
            ?: \App\Domains\Accounting\Support\GstStates::findInText($data['shipping_address'] ?? null);

        if (empty($data['place_of_supply_state'])) {
            $data['place_of_supply_state'] = !empty($data['bill_to_gstin'])
                ? ($billState ?: $shipState)
                : ($shipState ?: $billState);
        }

        return $data;
    }
}
