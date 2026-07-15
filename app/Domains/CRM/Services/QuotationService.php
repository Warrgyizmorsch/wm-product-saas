<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Repositories\QuotationRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(
        private readonly QuotationRepository $quotations,
    ) {
    }

    public function latest(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->quotations->latest();
    }

    public function find(int $id): ?Quotation
    {
        return $this->quotations->find($id);
    }

    public function getNextQuotationNumber(): string
    {
        $latest = Quotation::query()->whereNull('parent_id')->latest('id')->first();

        if (!$latest) {
            return 'QT-0001';
        }

        $rawNum = $latest->getRawOriginal('quotation_number');
        $nextSeq = intval($rawNum) + 1;
        
        return 'QT-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data, array $items): Quotation
    {
        return DB::transaction(function () use ($data, $items) {
            // Check if quotation number is provided or fallback
            if (empty($data['quotation_number'])) {
                $data['quotation_number'] = $this->getNextQuotationNumber();
            }

            // Calculate totals
            $subtotal = 0;
            $tax = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;

                $amount = $qty * $price;
                $itemTax = $amount * ($taxRate / 100);

                $subtotal += $amount;
                $tax += $itemTax;

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = \App\Domains\Inventory\Models\Product::find($productId);
                    if ($product) {
                        $itemName = $product->name;
                    }
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'item_name' => $itemName,
                    'description' => $item['description'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'amount' => $amount,
                ];
            }

            $discount = floatval($data['discount'] ?? 0);
            $totalAmount = $subtotal + $tax - $discount;

            $data['subtotal'] = $subtotal;
            $data['tax'] = $tax;
            $data['total_amount'] = $totalAmount;
            $data['is_current'] = true;
            $data['revision_number'] = 0;

            // Save Quotation
            $quotation = $this->quotations->create($data);

            // Save Items
            $quotation->items()->createMany($itemsData);

            return $quotation;
        });
    }

    public function update(Quotation $quotation, array $data, array $items): Quotation
    {
        return DB::transaction(function () use ($quotation, $data, $items) {
            $rootParentId = $quotation->parent_id ?: $quotation->id;

            // Get max revision number for this group
            $latestRevision = Quotation::query()
                ->where(function ($query) use ($rootParentId) {
                    $query->where('parent_id', $rootParentId)
                          ->orWhere('id', $rootParentId);
                })
                ->max('revision_number') ?? 0;

            $newRevisionNumber = $latestRevision + 1;

            // Extract base number
            $rawNum = $quotation->getRawOriginal('quotation_number');
            $baseNum = explode('-R', $rawNum)[0];
            $newQuotationNumber = $baseNum . '-R' . $newRevisionNumber;

            // Calculate totals
            $subtotal = 0;
            $tax = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;

                $amount = $qty * $price;
                $itemTax = $amount * ($taxRate / 100);

                $subtotal += $amount;
                $tax += $itemTax;

                $itemName = $item['item_name'] ?? 'Product/Service';
                if ($productId) {
                    $product = \App\Domains\Inventory\Models\Product::find($productId);
                    if ($product) {
                        $itemName = $product->name;
                    }
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'item_name' => $itemName,
                    'description' => $item['description'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'amount' => $amount,
                ];
            }

            $discount = floatval($data['discount'] ?? 0);
            $totalAmount = $subtotal + $tax - $discount;

            // Prepare active revision data
            $revData = [
                'tenant_id' => $quotation->tenant_id,
                'lead_id' => $data['lead_id'] ?? $quotation->lead_id,
                'sales_person_id' => $data['sales_person_id'] ?? $quotation->sales_person_id,
                'quotation_number' => $newQuotationNumber,
                'quotation_date' => $data['quotation_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'status' => $data['status'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total_amount' => $totalAmount,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'notes' => $data['notes'] ?? null,
                'parent_id' => $rootParentId,
                'revision_number' => $newRevisionNumber,
                'is_current' => true,
            ];

            // Mark all older revisions as inactive
            Quotation::query()
                ->where(function ($query) use ($rootParentId) {
                    $query->where('parent_id', $rootParentId)
                          ->orWhere('id', $rootParentId);
                })
                ->update(['is_current' => false]);

            // Save new active Quotation revision
            $newQuotation = $this->quotations->create($revData);

            // Save new items
            $newQuotation->items()->createMany($itemsData);

            return $newQuotation;
        });
    }

    public function delete(Quotation $quotation): bool
    {
        return $quotation->delete();
    }
}
