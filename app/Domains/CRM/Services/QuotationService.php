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
        $latest = Quotation::query()->latest('id')->first();

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

                $amount = $qty * $price;
                $itemTax = $amount * ($taxRate / 100);

                $subtotal += $amount;
                $tax += $itemTax;

                $itemsData[] = [
                    'item_name' => $item['item_name'],
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
            // Calculate totals
            $subtotal = 0;
            $tax = 0;
            $itemsData = [];

            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price'] ?? 0);
                $taxRate = floatval($item['tax_rate'] ?? 0);

                $amount = $qty * $price;
                $itemTax = $amount * ($taxRate / 100);

                $subtotal += $amount;
                $tax += $itemTax;

                $itemsData[] = [
                    'item_name' => $item['item_name'],
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

            // Update Quotation metadata
            $quotation->update($data);

            // Recreate Items (delete old, create new)
            $quotation->items()->delete();
            $quotation->items()->createMany($itemsData);

            return $quotation;
        });
    }

    public function delete(Quotation $quotation): bool
    {
        return $quotation->delete();
    }
}
